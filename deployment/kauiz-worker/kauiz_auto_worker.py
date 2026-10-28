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
import base64
import contextlib
import fcntl
import hashlib
import http.client
import io
import json
import logging
import logging.handlers
import os
import random
import re
import shutil
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from pathlib import Path
from typing import Optional
from zoneinfo import ZoneInfo

from PIL import Image, ImageChops, ImageDraw, ImageFilter, ImageFont, ImageOps

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
    "primary": "#0B1537",
    "accent": "#FFCB3D",
    "paper": "#FBF6EA",
    "white": "#FFFFFF",
}

# Mode gambar: "pillow" (default, daftar topik bawaan) atau "muse" (latar dari Muse, teks dari Pillow).
IMAGE_MODES = ("pillow", "muse")
# Penulis prompt untuk materi baru dari analisis dashboard. Memakai API key OpenRouter yang sama dengan Muse.
LLM_MODEL_DEFAULT = "meta-llama/llama-3.1-8b-instruct"
# Muse dipanggil lewat OpenRouter Images, sama dengan bot Jauki: model meta/muse-image, respons JSON.
OPENROUTER_BASE_URL = "https://openrouter.ai/api/v1"
MUSE_MODEL = "meta/muse-image"
# Format worker -> output mode di visual-template-memory.json.
OUTPUT_MODE = {"feed": "feed_portrait", "story": "story"}
# Rasio yang dikirim ke OpenRouter, sama dengan generate_muse_image di bot Jauki.
ASPECT_RATIO = {"feed": "4:5", "story": "9:16"}

# Kode error Graph API. Periksa ulang dengan dokumentasi Meta saat versi API dinaikkan.
RATE_LIMIT_CODES = {4, 17, 32, 613, 80001}
AUTH_CODES = {102, 190}
TRANSIENT_CODES = {1, 2, 9007}
# Subkode 2207006 (media belum ditemukan) muncul sesaat setelah container dibuat; dicoba lagi seperti 9007.
TRANSIENT_SUBCODES = {2207006}

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
    if status >= 500 or code in TRANSIENT_CODES or details["subcode"] in TRANSIENT_SUBCODES:
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


def draw_layout(image, topic, fmt, font_bold=None, font_regular=None):
    """Menulis label, headline, isi, dan CTA di atas gambar yang sudah ada (latar Pillow atau Muse)."""
    width, height = CANVAS[fmt]
    edge = EDGE[fmt]
    headline_font = load_font(font_bold, 104)
    body_font = load_font(font_regular, 46)
    label_font = load_font(font_bold, 44)
    cta_font = load_font(font_bold, 52)
    url_font = load_font(font_regular, 40)
    text_width = width - 2 * MARGIN

    draw = ImageDraw.Draw(image)

    draw.rounded_rectangle((MARGIN, edge, MARGIN + 300, edge + 96), radius=48, fill=PALETTE["primary"])
    draw.text((MARGIN + 150, edge + 48), "KAUIZ", font=label_font, fill=PALETTE["white"], anchor="mm")

    y = edge + 176
    headline_step = line_height(draw, headline_font, 1.1)
    for line in wrap_text(draw, topic["headline"].upper(), headline_font, text_width):
        draw.text((MARGIN, y), line, font=headline_font, fill=PALETTE["primary"])
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
                           fill=PALETTE["accent"])
    text_y = note_top + note_pad
    for line in note_lines:
        draw.text((MARGIN + note_pad, text_y), line, font=body_font, fill=PALETTE["primary"])
        text_y += body_step

    cta_label = "KLIK LINK DI BIO" if fmt == "feed" else "LINK DI BIO KAUIZ"
    draw.rounded_rectangle((MARGIN, cta_top, width - MARGIN, cta_top + 150), radius=75,
                           fill=PALETTE["primary"])
    draw.text((width // 2, cta_top + 58), cta_label, font=cta_font, fill=PALETTE["white"], anchor="mm")
    draw.text((width // 2, cta_top + 110), "kauiz.web.id", font=url_font, fill=PALETTE["white"], anchor="mm")



def render_image(topic, fmt, out_path, font_bold=None, font_regular=None):
    image = Image.new("RGB", CANVAS[fmt], PALETTE["paper"])
    draw_layout(image, topic, fmt, font_bold, font_regular)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    image.save(out_path, "JPEG", quality=92)
    return out_path


class MuseError(RuntimeError):
    pass


class MuseTransientError(MuseError):
    """Gangguan sementara (koneksi, respons terputus, 429, 5xx) yang layak dicoba lagi."""


def http_post_json(url, headers, body, timeout=360.0):
    request = urllib.request.Request(
        url, data=json.dumps(body).encode("utf-8"), method="POST",
        headers={**headers, "Content-Type": "application/json"},
    )
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as err:
        detail = err.read().decode("utf-8", errors="replace")[:300]
        kind = MuseTransientError if err.code == 429 or err.code >= 500 else MuseError
        raise kind(f"OpenRouter HTTP {err.code}: {detail}") from err
    except (http.client.HTTPException, OSError) as err:
        raise MuseTransientError(f"koneksi atau respons OpenRouter terputus: {err!r}") from err
    except ValueError as err:
        raise MuseError("respons OpenRouter bukan JSON") from err


def http_get_bytes(url, timeout=300.0):
    try:
        with urllib.request.urlopen(url, timeout=timeout) as response:
            return response.read()
    except (http.client.HTTPException, OSError) as err:
        raise MuseTransientError(f"unduh gambar Muse gagal: {err!r}") from err


def muse_body(prompt, model, fmt, aspect_mode="image_config"):
    """Body request Muse. aspect_mode: param (aspect_ratio di body), image_config (di dalam image_config), none (rasio hanya di prompt)."""
    body = {"model": model, "prompt": prompt, "output_format": "jpeg"}
    if aspect_mode == "param":
        body["aspect_ratio"] = ASPECT_RATIO[fmt]
    elif aspect_mode == "image_config":
        body["image_config"] = {"aspect_ratio": ASPECT_RATIO[fmt]}
    return body


def generate_background(prompt, *, api_key, base_url, model, fmt, transport=None, fetch=None, sleep=time.sleep, waits=(5, 15),
                        aspect_mode="image_config"):
    """Latar dari OpenRouter Images. Body dan respons sama dengan generate_muse_image di bot Jauki."""
    if not api_key:
        raise MuseError("OPENROUTER_API_KEY belum diisi")
    transport = transport or http_post_json
    fetch = fetch or http_get_bytes
    for attempt in range(len(waits) + 1):
        try:
            payload = transport(
                f"{base_url.rstrip('/')}/images",
                {"Authorization": f"Bearer {api_key}"},
                muse_body(prompt, model, fmt, aspect_mode),
            )
            items = payload.get("data") or []
            item = items[0] if items else {}
            if item.get("b64_json"):
                return base64.b64decode(item["b64_json"])
            if item.get("url"):
                return fetch(item["url"])
            raise MuseError("respons Muse tidak berisi gambar")
        except MuseTransientError as err:
            if attempt == len(waits):
                raise
            LOG.warning("Muse gangguan sementara (%s); percobaan %d dari %d, coba lagi dalam %d detik", err, attempt + 1, len(waits) + 1, waits[attempt])
            sleep(waits[attempt])


def solid_background(scaled, canvas, position):
    """Isian polos: tiap sisi memakai warna rata-rata tepi gambar yang berbatasan dengannya."""
    width, height = canvas
    left, top = position
    background = Image.new("RGB", canvas, scaled.resize((1, 1), Image.BOX).getpixel((0, 0)))
    draw = ImageDraw.Draw(background)

    def edge(box):
        return scaled.crop(box).resize((1, 1), Image.BOX).getpixel((0, 0))

    w, h = scaled.size
    if top > 0:
        draw.rectangle((0, 0, width, top), fill=edge((0, 0, w, min(24, h))))
        draw.rectangle((0, top + h, width, height), fill=edge((0, max(0, h - 24), w, h)))
    if left > 0:
        draw.rectangle((0, 0, left, height), fill=edge((0, 0, min(24, w), h)))
        draw.rectangle((left + w, 0, width, height), fill=edge((max(0, w - 24), 0, w, h)))
    return background


def pad_to_canvas(source, canvas, feather=48, fill="blur"):
    """Memasang seluruh gambar di dalam kanvas tanpa memotong. Sisa area diisi versi buram gambar itu sendiri."""
    width, height = canvas
    scale = min(width / source.width, height / source.height)
    size = (round(source.width * scale), round(source.height * scale))
    scaled = source.resize(size, Image.LANCZOS)
    position = ((width - size[0]) // 2, (height - size[1]) // 2)
    if fill == "solid":
        background = solid_background(scaled, canvas, position)
    else:
        background = ImageOps.fit(source, canvas, method=Image.LANCZOS).filter(ImageFilter.GaussianBlur(40))
    vertical, horizontal = Image.new("L", size, 255), Image.new("L", size, 255)
    if size[1] < height:
        for row in range(min(feather, size[1] // 2)):
            value = round(255 * row / feather)
            ImageDraw.Draw(vertical).line((0, row, size[0], row), fill=value)
            ImageDraw.Draw(vertical).line((0, size[1] - 1 - row, size[0], size[1] - 1 - row), fill=value)
    if size[0] < width:
        for col in range(min(feather, size[0] // 2)):
            value = round(255 * col / feather)
            ImageDraw.Draw(horizontal).line((col, 0, col, size[1]), fill=value)
            ImageDraw.Draw(horizontal).line((size[0] - 1 - col, 0, size[0] - 1 - col, size[1]), fill=value)
    background.paste(scaled, position, ImageChops.multiply(vertical, horizontal))
    return background


def fit_to_canvas(image_bytes, fmt, feed_fit="pad", fill="solid"):
    """Menyesuaikan gambar Muse ke kanvas. Muse selalu mengembalikan 2:3. Bawaan: seluruh gambar dipasang utuh dengan isian
    buram di area sisa (story: atas dan bawah yang tertutup tampilan Instagram; feed: sisi kiri dan kanan), jadi tidak ada
    teks yang terpotong. feed_fit="crop" memotong feed ke 4:5 seperti sebelumnya."""
    with Image.open(io.BytesIO(image_bytes)) as source:
        rgb = source.convert("RGB")
    target = CANVAS[fmt][0] / CANVAS[fmt][1]
    if abs(rgb.width / rgb.height - target) / target <= 0.015:
        return ImageOps.fit(rgb, CANVAS[fmt], method=Image.LANCZOS)
    if fmt == "story" or feed_fit != "crop":
        LOG.info("gambar Muse %dx%d dipasang utuh dengan isian buram untuk %s", rgb.width, rgb.height, ASPECT_RATIO[fmt])
        return pad_to_canvas(rgb, CANVAS[fmt], fill=fill)
    LOG.warning("gambar Muse %dx%d tidak sesuai rasio %s, tepinya terpotong", rgb.width, rgb.height, ASPECT_RATIO[fmt])
    return ImageOps.fit(rgb, CANVAS[fmt], method=Image.LANCZOS)


# Pola konten untuk pembobotan template, subjek visual (bahasa Inggris, untuk Muse), dan poin untuk topik bawaan.
TOPIC_VISUALS = {
    "activerecall": ("tips", "an open notebook turned face-down beside a sticky note and a pencil", "focused and confident",
                     ["Tutup catatan", "Tulis dari ingatan", "Cek lalu perbaiki"]),
    "spacedrepetition": ("explainer", "a wall calendar with spaced reminder stickers", "calm and organized",
                         ["Hari ke-1", "Hari ke-3", "Hari ke-7"]),
    "pomodoro": ("tips", "a minimalist kitchen timer on a tidy study desk", "focused and energetic",
                 ["25 menit fokus", "5 menit istirahat", "Ulangi 4 kali"]),
    "kuis": ("tips", "a stack of quiz cards with a checkmark and a pencil", "playful and motivating",
             ["Pilih satu bab", "Buat 3-5 soal", "Jawab tanpa melihat"]),
    "verifikasi": ("explainer", "a magnifying glass over an open textbook next to a laptop", "careful and trustworthy",
                   ["Baca jawaban AI", "Cocokkan ke materi", "Catat sumbernya"]),
    "petakonsep": ("explainer", "a concept map made of paper notes and string on a corkboard", "clear and inspiring",
                   ["Tulis ide utama", "Hubungkan dengan panah", "Tambah kata kunci"]),
    "jadwal": ("tips", "a planner open to a weekly schedule with a pen and a small clock", "organized and calm",
               ["Pilih jam", "Tentukan durasi", "Anggap janji penting"]),
    "istirahat": ("tips", "a glass of water and a small plant beside a closed laptop", "relaxed and refreshing",
                  ["Berdiri", "Minum air", "Kembali fokus"]),
}
PATTERN_KEYWORDS = (
    ("statistics", ("persen", "%", "data", "survei", "riset")),
    ("event", ("webinar", "event", "acara", "promo", "diskon", "launching", "peluncuran")),
    ("technology", ("ai ", "chatgpt", "aplikasi", "teknologi", "fitur")),
    ("explainer", ("apa itu", "kenapa", "mengapa", "konsep", "metode", "teknik")),
    ("tips", ("tips", "cara", "langkah", "trik", "coba")),
)
SUBJECT_HINTS = (
    (("kuis", "soal", "quiz", "latihan"), "a stack of quiz cards with a checkmark and a pencil"),
    (("jadwal", "waktu", "kalender", "fokus", "pomodoro", "istirahat"), "a planner and a small timer on a tidy desk"),
    (("ai ", "chatgpt", "teknologi", "aplikasi"), "a laptop beside an open textbook with a magnifying glass"),
    (("ujian", "tes", "nilai"), "a calm student desk with textbooks and a pencil"),
    (("catatan", "ringkas", "rangkum", "note"), "an open notebook with sticky notes and a pencil"),
)
DEFAULT_SUBJECT = "a tidy student study desk with notebooks, a pencil, and sticky notes"
DEFAULT_INTENT = "focused, friendly, and motivating"
HISTORY_LIMIT = 20


def infer_pattern(plan):
    if plan.get("pattern"):
        return plan["pattern"]
    text = " ".join(str(plan.get(key) or "") for key in ("topic", "key_message", "brief")).lower() + " "
    for pattern, words in PATTERN_KEYWORDS:
        if any(word in text for word in words):
            return pattern
    return "tips"


def subject_hint(plan):
    text = " ".join(str(plan.get(key) or "") for key in ("topic", "key_message", "brief")).lower() + " "
    for words, subject in SUBJECT_HINTS:
        if any(word in text for word in words):
            return subject
    return DEFAULT_SUBJECT


def plan_from_topic(topic):
    """Data acak: topik bawaan dijadikan rencana lengkap dengan pola, subjek visual, dan poin."""
    pattern, subject, intent, points = TOPIC_VISUALS.get(topic["tag"], ("tips", DEFAULT_SUBJECT, DEFAULT_INTENT, []))
    return {
        "topic": topic["headline"], "key_message": topic["headline"], "brief": topic["body"],
        "pattern": pattern, "visual_subject": subject, "visual_intent": intent, "key_points": list(points),
    }


def short_line(text, limit=110):
    """Satu kalimat pendek untuk subline: potong di akhir kalimat, atau di batas kata dengan elipsis."""
    text = " ".join(str(text or "").split())
    if len(text) <= limit:
        return text
    sentence = re.match(r".{20,%d}?[.!?](?:\s|$)" % limit, text)
    if sentence:
        return sentence.group(0).strip()
    return text[:limit].rsplit(" ", 1)[0] + "…"


def overlay_copy(plan, topic):
    points = [short_line(point, 40) for point in (plan.get("key_points") or []) if point][:4]
    return {"headline": topic["headline"], "subheadline": short_line(topic["body"]), "items": points}


def load_visual_modules(visual_memory_dir):
    if visual_memory_dir is None:
        raise MuseError("KAUIZ_VISUAL_MEMORY_DIR belum diisi")
    os.environ.setdefault("VISUAL_MEMORY_DIR", str(visual_memory_dir))
    if str(visual_memory_dir) not in sys.path:
        sys.path.insert(0, str(visual_memory_dir))
    import visual_memory
    import visual_overlay

    return visual_memory, visual_overlay


def load_bank_module(visual_memory_dir):
    load_visual_modules(visual_memory_dir)
    import prompt_bank

    return prompt_bank


def llm_endpoints(config):
    """Endpoint model teks: utama (OpenRouter, key sama dengan Muse), lalu cadangan bila diatur. None bila tidak ada yang siap."""
    endpoints = []
    local = ("127.0.0.1", "localhost")
    if config.llm_model and (config.llm_api_key or any(host in config.llm_base_url for host in local)):
        endpoints.append({"base_url": config.llm_base_url, "api_key": config.llm_api_key, "model": config.llm_model})
    if config.llm_fallback_model and config.llm_fallback_base_url:
        endpoints.append({"base_url": config.llm_fallback_base_url, "api_key": config.llm_fallback_api_key, "model": config.llm_fallback_model})
    return endpoints or None


def muse_job(config, plan, fmt, slot_key, history, with_prompt=True):
    """Memilih template (dengan riwayat). Untuk mode teks Pillow, sekaligus menyusun prompt dari compiler visual memory."""
    visual_memory, _ = load_visual_modules(config.visual_memory_dir)
    item = dict(plan)
    item["pattern"] = infer_pattern(plan)
    item["visual_subject"] = plan.get("visual_prompt") or plan.get("visual_subject") or subject_hint(plan)
    item["visual_intent"] = plan.get("visual_intent") or DEFAULT_INTENT
    analysis = visual_memory.analysis_from_plan_item(item)
    seed = int(hashlib.sha256(f"{slot_key}:{fmt}".encode("utf-8")).hexdigest(), 16) % 2**32
    mode = OUTPUT_MODE[fmt]
    if text_in_muse(config):
        # Mode teks Muse: hanya tata letak yang dipilih (bergilir, tidak berulang dalam 3 terakhir). Adegan dan gaya teks ditentukan Muse.
        import visual_prompt_writer

        layout_ids = sorted(visual_prompt_writer.load_layout_styles())
        recent = {entry.get("template_id") for entry in history[-3:]}
        layout_id = random.Random(seed).choice([lid for lid in layout_ids if lid not in recent] or layout_ids)
        return {
            "prompt": None, "template_id": layout_id, "variation": {"hero_position": "free", "background_treatment": "free"},
            "mode": mode, "subject": item["visual_subject"], "pattern": item["pattern"], "seed": seed,
        }
    selected = visual_memory.VisualTemplateSelector().select(analysis, history=history, seed=seed)
    prompt = None
    if with_prompt:
        prompt = visual_memory.VisualPromptCompiler().compile_prompt(
            config.brand, analysis, selected["template_id"], selected["variation"], mode,
        )["prompt"]
    return {
        "prompt": prompt, "template_id": selected["template_id"], "variation": selected["variation"], "mode": mode,
        "subject": item["visual_subject"], "pattern": item["pattern"], "seed": seed,
    }


def topic_from_plan(plan):
    """Teks dari slot dashboard, dipakai untuk overlay dan caption."""
    headline = (plan.get("key_message") or plan.get("topic") or "").strip()
    tag = re.sub(r"[^a-z0-9]", "", (plan.get("topic") or headline).lower())[:30] or "kauiz"
    return {"tag": tag, "headline": headline, "body": (plan.get("brief") or "").strip()}


def apply_overlay(config, job, plan, topic, image):
    visual_memory, visual_overlay = load_visual_modules(config.visual_memory_dir)
    brand = visual_memory.VisualBrandMemory().get(config.brand)
    layout = visual_overlay.load_layouts()["templates"][job["template_id"]][job["mode"]]
    try:
        return visual_overlay.render_overlay(image, layout, brand, overlay_copy(plan, topic), mode=job["mode"], variation=job["variation"])
    except visual_overlay.OverlayError as err:
        raise MuseError(f"overlay {job['template_id']} gagal: {err}") from err


def text_in_muse(config):
    return config.text_mode == "muse"


def write_content(config, plan, topic, fmt, slot_key, job, use_llm):
    """Prompt Muse, caption, dan hashtag dari penulis prompt (model teks bila dikonfigurasi, jika tidak dari kartu gaya)."""
    visual_memory, _ = load_visual_modules(config.visual_memory_dir)
    import visual_prompt_writer

    brand = visual_memory.VisualBrandMemory().get(config.brand)
    cta = brand.get("cta", {})
    brand_data = visual_prompt_writer.load_brand_data(config.brand)
    source = {"headline": topic["headline"], "subheadline": topic["body"], "items": [p for p in (plan.get("key_points") or []) if p]}
    for key in ("pain", "tip", "feature", "caption_text"):
        if plan.get(key):
            source[key] = plan[key]
    post = {key: plan.get(key) for key in ("topic", "brief", "key_message", "key_points", "cta", "hook_direction", "objective", "angle") if plan.get(key)}
    post["pattern"] = job["pattern"]
    llm = llm_endpoints(config) if use_llm else None
    try:
        return visual_prompt_writer.write_content(
            config.brand, source, post, job["template_id"], fmt, job["subject"],
            cta_label=cta.get("label_story" if fmt == "story" else "label", "LINK DI BIO"),
            footer=brand_data.get("footer_text") or cta.get("url", ""), brand_data=brand_data,
            seed=job["seed"], llm=llm, styles=visual_prompt_writer.load_layout_styles(),
        )
    except visual_prompt_writer.WriterError as err:
        raise MuseError(f"penulis prompt gagal: {err}") from err


def write_prompt_preview(config, plan, topic, fmt, slot_key, state):
    """Dry run: prompt, pilihan template, dan caption ditulis ke output tanpa memanggil Muse."""
    job = muse_job(config, plan, fmt, slot_key, state.get("history", []), with_prompt=not text_in_muse(config))
    config.output_dir.mkdir(parents=True, exist_ok=True)
    base = config.output_dir / f"{slot_key}-{fmt}"
    info = {"template_id": job["template_id"], "variation": job["variation"]}
    if text_in_muse(config):
        content = write_content(config, plan, topic, fmt, slot_key, job, use_llm=config.writer_in_dryrun and plan.get("source") != "bank")
        prompt = content["muse_prompt"]
        info.update({"writer": content["source"], "copy": content["copy"], "hashtags": content["hashtags"], "writer_errors": content["errors"]})
        if content["caption"]:
            base.with_name(base.name + ".caption.txt").write_text(content["caption"] + "\n", encoding="utf-8")
        if content["source"] == "llm":
            # Hasil model teks disimpan: run live memakai teks yang sama yang sudah kamu periksa, tanpa memanggil model lagi.
            base.with_name(base.name + ".writer.json").write_text(json.dumps(content, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    else:
        prompt = job["prompt"]
        info["copy"] = overlay_copy(plan, topic)
        visual_memory, visual_overlay = load_visual_modules(config.visual_memory_dir)
        background = visual_overlay.placeholder_background(visual_memory.VisualBrandMemory().get(config.brand), job["mode"], 1)
        apply_overlay(config, job, plan, topic, background).save(base.with_name(base.name + ".preview.jpg"), "JPEG", quality=88)
    path = base.with_name(base.name + ".prompt.txt")
    path.write_text(prompt + "\n", encoding="utf-8")
    base.with_name(base.name + ".plan.json").write_text(json.dumps(info, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    return path, job["template_id"]


def render_muse_image(config, plan, topic, fmt, slot_key, out_path, state):
    today = datetime.now(ZoneInfo(config.timezone)).date().isoformat()
    background_path = out_path.with_name(out_path.stem + ".bg.jpg")
    out_path.parent.mkdir(parents=True, exist_ok=True)
    job = muse_job(config, plan, fmt, slot_key, state.get("history", []), with_prompt=not text_in_muse(config))
    writer_path = out_path.with_name(out_path.stem + ".writer.json")
    prompt = job["prompt"]
    if text_in_muse(config):
        if writer_path.exists():
            content = json.loads(writer_path.read_text(encoding="utf-8"))
        else:
            content = write_content(config, plan, topic, fmt, slot_key, job, use_llm=plan.get("source") != "bank")
            writer_path.write_text(json.dumps(content, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        prompt = content["muse_prompt"]
        if content["caption"]:
            out_path.with_name(out_path.stem + ".caption.txt").write_text(content["caption"] + "\n", encoding="utf-8")
    if background_path.exists():
        raw = background_path.read_bytes()  # latar sudah dibayar: dipakai ulang bila langkah sesudahnya gagal
    else:
        used = state.setdefault("images", {}).get(today, 0)
        if used >= config.max_images_per_day:
            raise MuseError(f"batas gambar harian tercapai ({used}/{config.max_images_per_day})")
        raw = generate_background(
            prompt, api_key=config.openrouter_api_key, base_url=config.openrouter_base_url,
            model=config.muse_model, fmt=fmt, aspect_mode=config.muse_aspect,
        )
        background_path.parent.mkdir(parents=True, exist_ok=True)
        background_path.write_bytes(raw)
        state["images"] = {today: used + 1}
        if plan.get("bank_id"):
            load_bank_module(config.visual_memory_dir).consume(state, config.brand, fmt, plan["bank_id"])
        state["history"] = (state.get("history", []) + [{
            "template_id": job["template_id"], "hero_position": job["variation"]["hero_position"],
            "background_treatment": job["variation"]["background_treatment"], "slot_key": slot_key,
        }])[-HISTORY_LIMIT:]
        save_state(config.state_file, state)
    image = fit_to_canvas(raw, fmt, config.feed_fit, config.fill_mode)
    if not text_in_muse(config):
        image = apply_overlay(config, job, plan, topic, image)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    image.save(out_path, "JPEG", quality=92)
    return out_path


def feed_caption(config, image_path, topic, fmt):
    """Caption feed: dari penulis prompt bila ada (mode teks Muse), jika tidak dari kerangka bawaan. Story tanpa caption."""
    if fmt != "feed":
        return None
    saved = image_path.with_name(image_path.stem + ".caption.txt")
    if config.image_mode == "muse" and text_in_muse(config) and saved.exists():
        return saved.read_text(encoding="utf-8").strip()
    return build_feed_caption(topic)


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
    image_mode: str = "pillow"
    openrouter_api_key: str = ""
    openrouter_base_url: str = OPENROUTER_BASE_URL
    muse_model: str = MUSE_MODEL
    visual_memory_dir: Optional[Path] = None
    max_images_per_day: int = 12
    feed_fit: str = "pad"
    fill_mode: str = "solid"
    brand: str = "kauiz"
    muse_aspect: str = "image_config"
    text_mode: str = "muse"
    llm_model: str = LLM_MODEL_DEFAULT
    llm_base_url: str = OPENROUTER_BASE_URL
    llm_api_key: str = ""
    writer_in_dryrun: bool = False
    llm_fallback_model: str = ""
    llm_fallback_base_url: str = ""
    llm_fallback_api_key: str = ""

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
        image_mode=get("KAUIZ_IMAGE_MODE", "pillow"),
        openrouter_api_key=get("OPENROUTER_API_KEY"),
        openrouter_base_url=get("KAUIZ_OPENROUTER_BASE_URL", OPENROUTER_BASE_URL),
        muse_model=get("KAUIZ_MUSE_MODEL", MUSE_MODEL),
        visual_memory_dir=Path(get("KAUIZ_VISUAL_MEMORY_DIR")) if get("KAUIZ_VISUAL_MEMORY_DIR") else None,
        max_images_per_day=int(get("KAUIZ_MAX_IMAGES_PER_DAY", "12")),
        feed_fit=get("KAUIZ_FEED_FIT", "pad"),
        fill_mode=get("KAUIZ_FILL", "solid"),
        brand=get("KAUIZ_BRAND", "kauiz"),
        muse_aspect=get("KAUIZ_MUSE_ASPECT", "image_config"),
        text_mode=get("KAUIZ_TEXT_MODE", "muse"),
        llm_model=get("KAUIZ_LLM_MODEL", LLM_MODEL_DEFAULT),
        llm_base_url=get("KAUIZ_LLM_BASE_URL", OPENROUTER_BASE_URL),
        llm_api_key=get("KAUIZ_LLM_API_KEY") or get("OPENROUTER_API_KEY"),
        writer_in_dryrun=get("KAUIZ_WRITER_IN_DRYRUN", "0") == "1",
        llm_fallback_model=get("KAUIZ_LLM_FALLBACK_MODEL"),
        llm_fallback_base_url=get("KAUIZ_LLM_FALLBACK_BASE_URL"),
        llm_fallback_api_key=get("KAUIZ_LLM_FALLBACK_API_KEY") or get("LITELLM_API_KEY"),
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
    plan: dict = field(default_factory=dict)


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
                plan=slot if isinstance(slot, dict) else {},
            ))
    return sorted(jobs, key=lambda job: job.at)


def run_auto(config, *, live, formats, now, slot=None, client=None, slot_key=None, topic=None, publish=True, plan=None):
    slot = slot or ("am" if now.hour < 12 else "pm")
    slot_key = slot_key or f"{now.date().isoformat()}-{slot}"
    topic = topic or pick_topic(now, slot)
    if config.image_mode not in IMAGE_MODES or config.text_mode not in ("muse", "pillow"):
        LOG.error("KAUIZ_IMAGE_MODE atau KAUIZ_TEXT_MODE tidak dikenali: %s, %s", config.image_mode, config.text_mode)
        return EXIT_CONFIG
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
                caption = None
                if config.image_mode == "muse":
                    # Muse hanya dipanggil saat live. Dry run cukup menulis prompt untuk diperiksa.
                    if not live and not image_path.exists():
                        preview, template_id = write_prompt_preview(config, plan or {}, topic, fmt, slot_key, state)
                        LOG.info("%s: template %s, prompt Muse ditulis ke %s, belum memanggil OpenRouter", key, template_id, preview)
                        continue
                    if not image_path.exists():
                        render_muse_image(config, plan or {}, topic, fmt, slot_key, image_path, state)
                elif not image_path.exists():
                    render_image(topic, fmt, image_path, config.font_bold, config.font_regular)
                caption = feed_caption(config, image_path, topic, fmt)
            except (OSError, ValueError, MuseError) as err:
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
        topic, plan = job.topic, job.plan
        if config.image_mode == "muse":
            # Mode Muse memakai isi jadwal dashboard. Tanpa isi, dipakai topik bawaan sebagai data acak.
            topic = topic_from_plan(job.plan)
            if topic["headline"]:
                plan = {**job.plan, "source": "slot"}
            else:
                bank = load_bank_module(config.visual_memory_dir)
                item = bank.next_item(load_state(config.state_file), config.brand, job.fmt)
                if item is None:
                    LOG.error("slot %s dilewati: bank materi %s habis dan jadwal tidak punya isi", job.slot_key, job.fmt)
                    failures += 1
                    continue
                plan = bank.to_plan(item, job.fmt)
                topic = topic_from_plan(plan)
                LOG.info("slot %s tanpa isi jadwal, memakai materi bank %s", job.slot_key, item["id"])
        code = run_auto(config, live=live, formats=(job.fmt,), now=now, client=client,
                        slot_key=job.slot_key, topic=topic, publish=job.publish, plan=plan)
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
