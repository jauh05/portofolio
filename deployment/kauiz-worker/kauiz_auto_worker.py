"""Worker otomatis postingan Instagram Kauiz: generate teks, render gambar, publish.

Jadwal diambil dari file slot yang ditulis dashboard (KAUIZ_SLOTS_FILE). Tiap slot:
gambar dirender sejak lead time sebelum jam tayang, lalu dipublish tepat di jam tayang.
Cron menjalankan `auto --live` berkala; tiap jalan hanya mengerjakan slot yang sedang aktif.
Tiap gambar: generate teks -> render JPEG dengan Pillow -> salin ke folder publik -> Instagram Graph API.

Tanpa --live hanya dry run: gambar dirender ke folder output, tanpa panggilan API.
Publish sungguhan hanya terjadi dengan --live. Posting Instagram tidak bisa ditarik kembali.

    python3 kauiz_auto_worker.py auto                  # dry run
    python3 kauiz_auto_worker.py auto --live           # publish sungguhan

Konfigurasi dibaca dari worker.env di folder ini atau dari environment (lihat worker.env.example).
Panduan crontab dan error log ada di README.md.
"""

from __future__ import annotations

import argparse
import contextlib
import fcntl
import hashlib
import json
import logging
import logging.handlers
import os
import re
import shutil
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from datetime import datetime, timedelta
from pathlib import Path
from typing import Optional
from zoneinfo import ZoneInfo

from PIL import Image, ImageDraw, ImageFont

WORKER_DIR = Path(__file__).resolve().parent
# Token Instagram Login hanya diterima di graph.instagram.com. Token Facebook Login memakai graph.facebook.com.
GRAPH_HOST = "https://graph.instagram.com"
GRAPH_VERSION = "v23.0"
DEFAULT_TIMEZONE = "Asia/Jakarta"
CAPTION_LIMIT = 2200
FORMATS = ("feed", "story")
CANVAS = {"feed": (1080, 1350), "story": (1080, 1920)}
MARGIN = 80
# Story: area atas dan bawah dipakai UI Instagram, jadi konten dijauhkan dari tepinya.
EDGE = {"feed": 80, "story": 250}

# Sama dengan blok "kauiz" di resources/office-ai/visual-memory/brands.json (dicek di test).
PALETTE = {
    "primary": "#1F49E7",
    "dark": "#101828",
    "background": "#F8FAFC",
    "sticky_note": "#F6DD63",
    "white": "#FFFFFF",
}

# Kode error Graph API. Periksa ulang dengan dokumentasi Meta saat versi API dinaikkan.
RATE_LIMIT_CODES = {4, 17, 32, 613, 80001}
AUTH_CODES = {102, 190}
TRANSIENT_CODES = {1, 2, 9007}

EXIT_OK, EXIT_FAILED, EXIT_CONFIG, EXIT_LOCKED = 0, 1, 2, 3

# Generate dimulai sejak lead time sebelum jam tayang. Bisa diubah per slot lewat lead_minutes.
DEFAULT_LEAD_MINUTES = 120
# Posting masih dicoba sampai menit ini setelah jam tayang. Lewat dari itu slot dianggap terlewat.
POST_GRACE_MINUTES = 60

LOG = logging.getLogger("kauiz.worker")

TOPICS = (
    {
        "tag": "activerecall",
        "headline": "Tutup catatan, lalu ingat lagi",
        "body": "Active recall membuat otak menarik ingatan, bukan sekadar membaca ulang. Coba tulis dari ingatan dulu sebelum membuka catatan.",
    },
    {
        "tag": "spacedrepetition",
        "headline": "Ulangi materi sebelum lupa",
        "body": "Ulangi di hari ke-1, ke-3, dan ke-7. Jeda yang makin panjang membuat materi lebih awet di kepala.",
    },
    {
        "tag": "pomodoro",
        "headline": "Fokus 25 menit, istirahat 5 menit",
        "body": "Pomodoro memecah belajar jadi sesi pendek. Satu sesi, satu topik, tanpa notifikasi.",
    },
    {
        "tag": "kuis",
        "headline": "Ubah catatanmu jadi kuis",
        "body": "Dari satu bab, buat tiga sampai lima pertanyaan. Menjawab pertanyaan melatih ingatan lebih kuat daripada menyalin catatan.",
    },
    {
        "tag": "verifikasi",
        "headline": "Cek jawaban AI ke sumbernya",
        "body": "AI bisa terdengar yakin padahal keliru. Sebelum menghafal, cocokkan jawabannya dengan materi yang kamu pegang.",
    },
    {
        "tag": "petakonsep",
        "headline": "Satu bab, satu peta konsep",
        "body": "Hubungkan ide utama dengan panah dan kata kunci. Peta konsep membuatmu melihat gambaran besar sebelum detailnya.",
    },
    {
        "tag": "jadwal",
        "headline": "Jadwalkan belajar seperti janji penting",
        "body": "Sesi yang ada di kalender lebih sering benar-benar terjadi. Tentukan jam dan durasi, lalu anggap seperti pertemuan yang tidak boleh batal.",
    },
    {
        "tag": "istirahat",
        "headline": "Istirahat itu bagian dari belajar",
        "body": "Otak butuh jeda untuk menyimpan apa yang baru dipelajari. Berdiri, minum air, lalu kembali ke topik yang sama.",
    },
)


class GraphError(Exception):
    retryable = False
    wait_hint = 0

    def __init__(self, message, *, status=None, code=None, subcode=None, fbtrace_id=None, retry_after=None):
        super().__init__(message)
        self.status = status
        self.code = code
        self.subcode = subcode
        self.fbtrace_id = fbtrace_id
        self.retry_after = retry_after

    def __str__(self):
        parts = [super().__str__()]
        if self.status:
            parts.append(f"HTTP {self.status}")
        if self.code is not None:
            parts.append(f"code={self.code}")
        if self.subcode:
            parts.append(f"subcode={self.subcode}")
        if self.fbtrace_id:
            parts.append(f"fbtrace_id={self.fbtrace_id}")
        if self.retry_after is not None:
            parts.append(f"retry_after={self.retry_after}s")
        return " | ".join(parts)


class NetworkError(GraphError):
    retryable = True
    wait_hint = 10


class TransientError(GraphError):
    retryable = True
    wait_hint = 15


class RateLimitError(GraphError):
    retryable = True
    wait_hint = 60


class AuthError(GraphError):
    retryable = False


class LockBusy(RuntimeError):
    pass


def classify_error(status, headers, body):
    error = body.get("error") if isinstance(body, dict) else None
    error = error if isinstance(error, dict) else {}
    code = error.get("code")
    details = {
        "status": status,
        "code": code,
        "subcode": error.get("error_subcode"),
        "fbtrace_id": error.get("fbtrace_id"),
        "retry_after": parse_retry_after(headers),
    }
    message = error.get("message") or f"HTTP {status}"
    if status == 429 or code in RATE_LIMIT_CODES:
        return RateLimitError(message, **details)
    if code in AUTH_CODES:
        return AuthError(message, **details)
    if status >= 500 or code in TRANSIENT_CODES:
        return TransientError(message, **details)
    return GraphError(message, **details)


def parse_retry_after(headers):
    value = {key.lower(): val for key, val in headers.items()}.get("retry-after")
    try:
        return max(0, int(value))
    except (TypeError, ValueError):
        return None


def http_post(url, form, timeout=30.0):
    data = urllib.parse.urlencode(form).encode("utf-8")
    request = urllib.request.Request(url, data=data, method="POST")
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return response.status, dict(response.headers.items()), json.loads(response.read() or b"{}")
    except urllib.error.HTTPError as err:
        raw = err.read()
        try:
            body = json.loads(raw or b"{}")
        except ValueError:
            body = {"error": {"message": raw[:300].decode("utf-8", "replace")}}
        return err.code, dict(err.headers.items()), body


class GraphClient:
    """Panggilan Graph API dengan retry untuk koneksi putus, rate limit, dan error sementara."""

    def __init__(self, access_token, ig_user_id, *, host=GRAPH_HOST, version=GRAPH_VERSION, transport=None,
                 sleep=time.sleep, max_retries=3, max_wait=300):
        self.access_token = access_token
        self.ig_user_id = ig_user_id
        self.host = host
        self.version = version
        self.transport = transport or http_post
        self.sleep = sleep
        self.max_retries = max_retries
        # Retry-After atau backoff yang lebih lama dari ini tidak ditunggu. Slot berikutnya yang mencoba lagi.
        self.max_wait = max_wait

    def create_container(self, image_url, *, caption=None, story=False):
        fields = {"image_url": image_url}
        if story:
            fields["media_type"] = "STORIES"
        else:
            fields["caption"] = caption or ""
        return self._id(self._request(f"{self.ig_user_id}/media", fields))

    def publish(self, creation_id):
        return self._id(self._request(f"{self.ig_user_id}/media_publish", {"creation_id": creation_id}))

    def _request(self, path, fields):
        url = f"{self.host}/{self.version}/{path}"
        form = {**fields, "access_token": self.access_token}
        attempts = self.max_retries + 1
        for attempt in range(1, attempts + 1):
            try:
                status, headers, body = self.transport(url, form)
            except OSError as err:
                error = NetworkError(f"koneksi ke Graph API terputus ({type(err).__name__}: {err})")
            else:
                if status < 400 and "error" not in body:
                    return body
                error = classify_error(status, headers, body)
            if not error.retryable:
                raise error
            if error.retry_after is not None:
                wait = error.retry_after
            else:
                wait = error.wait_hint * 2 ** (attempt - 1)
            if attempt == attempts or wait > self.max_wait:
                raise error
            LOG.warning("%s; percobaan %d dari %d gagal, coba lagi dalam %d detik",
                        error, attempt, attempts, wait)
            self.sleep(wait)
        raise AssertionError("unreachable")

    @staticmethod
    def _id(body):
        if not isinstance(body, dict) or not body.get("id"):
            raise GraphError(f"respons Graph API tanpa id: {str(body)[:200]}")
        return str(body["id"])


def describe_error(err):
    if isinstance(err, AuthError):
        return "token ditolak Graph API (kedaluwarsa atau kurang izin), periksa KAUIZ_IG_ACCESS_TOKEN"
    if isinstance(err, RateLimitError):
        return "rate limit Instagram, slot ini dilewati dan tidak diulang"
    if isinstance(err, NetworkError):
        return "koneksi ke Graph API terputus"
    return "Graph API menolak permintaan"


def pick_topic(now, slot):
    """Rotasi deterministik: setiap slot berurutan mendapat topik berikutnya."""
    index = now.toordinal() * 2 + (0 if slot == "am" else 1)
    return TOPICS[index % len(TOPICS)]


def build_feed_caption(topic):
    caption = (
        f"{topic['headline']}\n\n"
        f"{topic['body']}\n\n"
        "🔗 Klik link di bio untuk mulai belajar di kauiz.web.id\n"
        "💬 Mau dicoba? Komentar KAUIZ di bawah.\n\n"
        f"#kauiz #belajarefektif #tipsbelajar #{topic['tag']}"
    )
    if len(caption) > CAPTION_LIMIT:
        raise ValueError(f"caption {len(caption)} karakter, melebihi batas {CAPTION_LIMIT}")
    return caption


def load_font(path, size):
    if path:
        return ImageFont.truetype(path, size)
    return ImageFont.load_default(size=size)


def wrap_text(draw, text, font, max_width):
    lines, current = [], ""
    for word in text.split():
        candidate = f"{current} {word}" if current else word
        if current and draw.textlength(candidate, font=font) > max_width:
            lines.append(current)
            current = word
        else:
            current = candidate
    if current:
        lines.append(current)
    return lines


def line_height(draw, font, factor=1.25):
    left, top, right, bottom = draw.textbbox((0, 0), "Ag", font=font)
    return int((bottom - top) * factor)


def render_image(topic, fmt, out_path, font_bold=None, font_regular=None):
    width, height = CANVAS[fmt]
    edge = EDGE[fmt]
    headline_font = load_font(font_bold, 104)
    body_font = load_font(font_regular, 46)
    label_font = load_font(font_bold, 44)
    cta_font = load_font(font_bold, 52)
    url_font = load_font(font_regular, 40)
    text_width = width - 2 * MARGIN

    image = Image.new("RGB", (width, height), PALETTE["background"])
    draw = ImageDraw.Draw(image)

    draw.rounded_rectangle((MARGIN, edge, MARGIN + 300, edge + 96), radius=48, fill=PALETTE["primary"])
    draw.text((MARGIN + 150, edge + 48), "KAUIZ", font=label_font, fill=PALETTE["white"], anchor="mm")

    y = edge + 176
    headline_step = line_height(draw, headline_font, 1.1)
    for line in wrap_text(draw, topic["headline"].upper(), headline_font, text_width):
        draw.text((MARGIN, y), line, font=headline_font, fill=PALETTE["dark"])
        y += headline_step

    note_pad = 60
    body_step = line_height(draw, body_font)
    note_lines = wrap_text(draw, topic["body"], body_font, text_width - 2 * note_pad)
    note_top = y + 40
    note_bottom = note_top + 2 * note_pad + len(note_lines) * body_step

    cta_top = height - edge - 150
    if note_bottom > cta_top - 40:
        raise ValueError(f"teks '{topic['tag']}' tidak muat di kanvas {fmt}")

    draw.rounded_rectangle((MARGIN, note_top, width - MARGIN, note_bottom), radius=24,
                           fill=PALETTE["sticky_note"])
    text_y = note_top + note_pad
    for line in note_lines:
        draw.text((MARGIN + note_pad, text_y), line, font=body_font, fill=PALETTE["dark"])
        text_y += body_step

    cta_label = "KLIK LINK DI BIO" if fmt == "feed" else "LINK DI BIO KAUIZ"
    draw.rounded_rectangle((MARGIN, cta_top, width - MARGIN, cta_top + 150), radius=75,
                           fill=PALETTE["primary"])
    draw.text((width // 2, cta_top + 58), cta_label, font=cta_font, fill=PALETTE["white"], anchor="mm")
    draw.text((width // 2, cta_top + 110), "kauiz.web.id", font=url_font, fill=PALETTE["white"], anchor="mm")

    out_path.parent.mkdir(parents=True, exist_ok=True)
    image.save(out_path, "JPEG", quality=92)
    return out_path


def publish_public_copy(image_path, public_dir, public_base_url):
    """Instagram mengambil gambar dari URL publik, jadi salin hasil render ke folder yang diservis web server."""
    digest = hashlib.sha256(image_path.read_bytes()).hexdigest()[:12]
    name = f"{image_path.stem}-{digest}.jpg"
    public_dir.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(image_path, public_dir / name)
    return f"{public_base_url.rstrip('/')}/{name}"


@dataclass(frozen=True)
class Config:
    ig_user_id: str = ""
    access_token: str = ""
    graph_host: str = GRAPH_HOST
    graph_version: str = GRAPH_VERSION
    public_dir: Path = WORKER_DIR / "public"
    public_base_url: str = ""
    output_dir: Path = WORKER_DIR / "output"
    state_file: Path = WORKER_DIR / "state.json"
    lock_file: Path = WORKER_DIR / "worker.lock"
    log_file: Path = WORKER_DIR / "logs" / "kauiz-worker.log"
    slots_file: Path = WORKER_DIR / "slots.json"
    font_bold: Optional[str] = None
    font_regular: Optional[str] = None
    timezone: str = DEFAULT_TIMEZONE
    max_retries: int = 3
    max_wait: int = 300

    def missing_for_live(self):
        required = {
            "KAUIZ_IG_USER_ID": self.ig_user_id,
            "KAUIZ_IG_ACCESS_TOKEN": self.access_token,
            "KAUIZ_PUBLIC_BASE_URL": self.public_base_url,
        }
        return [name for name, value in required.items() if not value]


def load_config(env):
    def get(name, default=""):
        return env.get(name, default).strip()

    return Config(
        ig_user_id=get("KAUIZ_IG_USER_ID"),
        access_token=get("KAUIZ_IG_ACCESS_TOKEN"),
        graph_host=get("KAUIZ_GRAPH_HOST", GRAPH_HOST),
        graph_version=get("KAUIZ_GRAPH_VERSION", GRAPH_VERSION),
        public_dir=Path(get("KAUIZ_PUBLIC_DIR", str(WORKER_DIR / "public"))),
        public_base_url=get("KAUIZ_PUBLIC_BASE_URL"),
        output_dir=Path(get("KAUIZ_OUTPUT_DIR", str(WORKER_DIR / "output"))),
        state_file=Path(get("KAUIZ_STATE_FILE", str(WORKER_DIR / "state.json"))),
        lock_file=Path(get("KAUIZ_LOCK_FILE", str(WORKER_DIR / "worker.lock"))),
        log_file=Path(get("KAUIZ_LOG_FILE", str(WORKER_DIR / "logs" / "kauiz-worker.log"))),
        slots_file=Path(get("KAUIZ_SLOTS_FILE", str(WORKER_DIR / "slots.json"))),
        font_bold=get("KAUIZ_FONT_BOLD") or None,
        font_regular=get("KAUIZ_FONT_REGULAR") or None,
        timezone=get("KAUIZ_TIMEZONE", DEFAULT_TIMEZONE),
        max_retries=int(get("KAUIZ_MAX_RETRIES", "3")),
        max_wait=int(get("KAUIZ_MAX_WAIT_SECONDS", "300")),
    )


def load_env_file(path):
    """Membaca KEY=VALUE dari worker.env. Variabel yang sudah ada di environment tidak ditimpa."""
    if not path.is_file():
        return
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if line.startswith("export "):
            line = line[len("export "):]
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip("\"'"))


def load_state(path):
    if not path.exists():
        return {"published": {}}
    try:
        state = json.loads(path.read_text(encoding="utf-8"))
    except ValueError as err:
        # Berhenti, jangan reset diam-diam: state yang hilang bisa membuat posting dobel.
        raise RuntimeError(f"{path} rusak ({err}); periksa manual sebelum menjalankan publish") from err
    state.setdefault("published", {})
    return state


def save_state(path, state):
    tmp = path.with_suffix(".tmp")
    tmp.write_text(json.dumps(state, indent=2, ensure_ascii=False), encoding="utf-8")
    os.replace(tmp, path)


@contextlib.contextmanager
def run_lock(path):
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w") as handle:
        try:
            fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError as err:
            raise LockBusy(f"worker lain masih berjalan ({path})") from err
        yield


def load_slots(path):
    """Slot dari file yang ditulis dashboard. File yang belum ada berarti belum ada jadwal Kauiz."""
    if not path.exists():
        return []
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError) as err:
        # Sama seperti state: berhenti, jangan diam-diam menganggap jadwal kosong.
        raise RuntimeError(f"{path} tidak bisa dibaca ({err}); tidak ada yang diposting sampai file diperbaiki") from err
    if not isinstance(data, list):
        raise RuntimeError(f"{path} harus berisi daftar slot")
    return data


@dataclass(frozen=True)
class Job:
    slot_key: str
    fmt: str
    topic: dict
    at: datetime
    publish: bool


def due_jobs(slots, now, default_timezone):
    """Pekerjaan yang aktif sekarang: gambar dibuat sejak lead time sebelum jam tayang, posting di jam tayang."""
    jobs = []
    for slot in slots:
        # Slot tanpa jam belum siap dijadwalkan, jadi dilewati tanpa error.
        if isinstance(slot, dict) and not slot.get("time"):
            continue
        try:
            fmt = slot["format"]
            day = int(slot["day"])
            hour, minute = (int(part) for part in slot["time"].split(":"))
            zone = ZoneInfo(slot.get("timezone") or default_timezone)
            lead = timedelta(minutes=int(slot.get("lead_minutes", DEFAULT_LEAD_MINUTES)))
        except (AttributeError, KeyError, TypeError, ValueError) as err:
            LOG.error("slot dilewati, data tidak lengkap atau salah: %s (%s)", slot, err)
            continue
        if fmt not in FORMATS or not 1 <= day <= 7 or not (0 <= hour < 24 and 0 <= minute < 60):
            LOG.error("slot dilewati, format, hari, atau jam tidak valid: %s", slot)
            continue

        today = now.astimezone(zone).date()
        # Lead time dan masa tenggang tidak lebih dari sehari, jadi cukup mengecek kemarin, hari ini, dan besok.
        for offset in (-1, 0, 1):
            candidate = today + timedelta(days=offset)
            if candidate.isoweekday() != day:
                continue
            at = datetime.combine(candidate, datetime.min.time().replace(hour=hour, minute=minute), tzinfo=zone)
            if not at - lead <= now < at + timedelta(minutes=POST_GRACE_MINUTES):
                continue
            jobs.append(Job(
                slot_key=at.strftime("%Y-%m-%d-%H%M"),
                fmt=fmt,
                topic=pick_topic(at, "am" if at.hour < 12 else "pm"),
                at=at,
                publish=now >= at,
            ))
    return sorted(jobs, key=lambda job: job.at)


def run_auto(config, *, live, formats, now, slot=None, client=None, slot_key=None, topic=None, publish=True):
    slot = slot or ("am" if now.hour < 12 else "pm")
    slot_key = slot_key or f"{now.date().isoformat()}-{slot}"
    topic = topic or pick_topic(now, slot)
    # publish=False hanya membuat gambar; posting menunggu jam tayang.
    live = live and publish

    if live:
        missing = config.missing_for_live()
        if missing:
            LOG.error("publish dibatalkan, variabel belum diisi: %s", ", ".join(missing))
            return EXIT_CONFIG
        client = client or GraphClient(
            config.access_token, config.ig_user_id, host=config.graph_host, version=config.graph_version,
            max_retries=config.max_retries, max_wait=config.max_wait,
        )

    mode = "LIVE" if live else ("dry run" if publish else "generate")
    LOG.info("slot %s, topik '%s', mode %s, format %s", slot_key, topic["tag"], mode, ",".join(formats))
    failures = 0
    with run_lock(config.lock_file):
        state = load_state(config.state_file)
        for fmt in formats:
            key = f"{slot_key}:{fmt}"
            if key in state["published"]:
                LOG.info("%s sudah pernah dipublish, dilewati", key)
                continue

            image_path = config.output_dir / f"{slot_key}-{fmt}.jpg"
            try:
                caption = build_feed_caption(topic) if fmt == "feed" else None
                if not image_path.exists():
                    render_image(topic, fmt, image_path, config.font_bold, config.font_regular)
            except (OSError, ValueError) as err:
                failures += 1
                LOG.error("generate/render %s gagal: %s", key, err)
                continue

            if not live:
                LOG.info("%s: gambar %s siap, caption %d karakter, belum dikirim ke Instagram",
                         key, image_path, len(caption or ""))
                continue

            try:
                image_url = publish_public_copy(image_path, config.public_dir, config.public_base_url)
                creation_id = client.create_container(image_url, caption=caption, story=fmt == "story")
                media_id = client.publish(creation_id)
            except GraphError as err:
                failures += 1
                LOG.error("publish %s gagal: %s | %s", key, describe_error(err), err)
                continue
            except OSError as err:
                failures += 1
                LOG.error("salin gambar %s ke folder publik gagal: %s", key, err)
                continue

            state["published"][key] = {
                "media_id": media_id,
                "image_url": image_url,
                "published_at": now.isoformat(),
            }
            save_state(config.state_file, state)
            LOG.info("publish %s berhasil, media_id=%s", key, media_id)

    return EXIT_FAILED if failures else EXIT_OK


def run_due(config, *, live, now, client=None):
    """Satu jalan cron: buat gambar slot yang akan tayang, lalu posting slot yang sudah jatuh tempo."""
    state = load_state(config.state_file)
    jobs = due_jobs(load_slots(config.slots_file), now, config.timezone)
    if not jobs:
        LOG.info("tidak ada slot Kauiz yang aktif sekarang")
    failures = 0
    for job in jobs:
        if f"{job.slot_key}:{job.fmt}" in state["published"]:
            continue
        if not job.publish and (config.output_dir / f"{job.slot_key}-{job.fmt}.jpg").exists():
            continue
        code = run_auto(config, live=live, formats=(job.fmt,), now=now, client=client,
                        slot_key=job.slot_key, topic=job.topic, publish=job.publish)
        if code == EXIT_CONFIG:
            return EXIT_CONFIG
        if code != EXIT_OK:
            failures += 1
    return EXIT_FAILED if failures else EXIT_OK


class TokenRedactor(logging.Filter):
    PATTERN = re.compile(r"(access_token=)[^&\s'\"]+")

    def filter(self, record):
        record.msg = self.PATTERN.sub(r"\1***", record.getMessage())
        record.args = ()
        return True


def setup_logging(log_file, verbose):
    if LOG.handlers:
        return
    LOG.setLevel(logging.DEBUG if verbose else logging.INFO)
    LOG.addFilter(TokenRedactor())
    formatter = logging.Formatter("%(asctime)s %(levelname)s %(message)s")
    if sys.stderr.isatty():
        console = logging.StreamHandler(sys.stderr)
        console.setFormatter(formatter)
        LOG.addHandler(console)
    log_file.parent.mkdir(parents=True, exist_ok=True)
    handler = logging.handlers.RotatingFileHandler(
        log_file, maxBytes=5 * 1024 * 1024, backupCount=5, encoding="utf-8",
    )
    handler.setFormatter(formatter)
    LOG.addHandler(handler)


def main(argv=None):
    parser = argparse.ArgumentParser(description="Worker otomatis postingan Instagram Kauiz.")
    commands = parser.add_subparsers(dest="command", required=True)
    auto = commands.add_parser("auto", help="buat gambar dan posting slot Kauiz sesuai jadwal dashboard")
    auto.add_argument("--live", action="store_true", help="publish sungguhan; tanpa flag ini hanya dry run")
    auto.add_argument("--verbose", action="store_true")
    args = parser.parse_args(argv)

    load_env_file(WORKER_DIR / "worker.env")
    config = load_config(os.environ)
    setup_logging(config.log_file, args.verbose)

    try:
        zone = ZoneInfo(config.timezone)
    except (KeyError, ValueError):
        LOG.error("KAUIZ_TIMEZONE tidak dikenali: %s", config.timezone)
        return EXIT_CONFIG
    now = datetime.now(zone)

    try:
        return run_due(config, live=args.live, now=now)
    except LockBusy as err:
        LOG.warning("%s; slot ini dilewati", err)
        return EXIT_LOCKED
    except RuntimeError as err:
        LOG.error("%s", err)
        return EXIT_CONFIG


if __name__ == "__main__":
    sys.exit(main())
