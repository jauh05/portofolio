#!/usr/bin/env python3
"""Bot Reels Jauki Academy.

Sekali jalan:
1. Cek jadwal mingguan (config.json -> videos_per_week).
2. Pilih video mentah acak. Potongan diambil dari bagian tengah: skip_head_s
   detik awal dan skip_tail_s detik akhir dibuang. Detik yang sudah tercatat
   di history.json tidak diulang.
3. Minta AI (LiteLLM) membuat overlay, caption, hashtag, dan saran mood musik.
4. Pilih musik acak dari music/<mood>/. Audio asli video mentah dibuang.
5. Render dengan ffmpeg ke MP4 1080x1920 (9:16), cek formatnya, lalu simpan
   bersama file caption .txt.

Contoh:
    python generate_content.py --dry-run     # lihat rencana saja
    python generate_content.py --force       # render sekarang, lewati jadwal
"""

import argparse
import fcntl
import json
import logging
import math
import os
import random
import re
import subprocess
import sys
import tempfile
import textwrap
import uuid
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

import requests

from ig_publisher import GraphError, PublishError, ReelsClient, publish_reel

BASE_DIR = Path(__file__).resolve().parent
RAW_DIR = BASE_DIR / "raw_videos"
OUT_DIR = BASE_DIR / "output"
MUSIC_DIR = BASE_DIR / "music"
LOG_DIR = BASE_DIR / "logs"
HISTORY_FILE = BASE_DIR / "history.json"
CONFIG_FILE = BASE_DIR / "config.json"
ENV_FILE = BASE_DIR / ".env"
LOCK_FILE = BASE_DIR / ".lock"

LLM_URL = "http://127.0.0.1:4000/v1/chat/completions"
LLM_MODEL = "google/gemini-2.5-flash"
LLM_ATTEMPTS = 3
DEFAULT_FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"

WIDTH, HEIGHT, FPS = 1080, 1920, 30
FONT_SIZE = 50
WRAP_CHARS = 28
MUSIC_EXT = {".mp3", ".m4a", ".aac", ".wav", ".ogg"}
RAW_EXT = {".mp4", ".mov"}
MUSIC_FADE_IN_S = 0.5
MUSIC_FADE_OUT_S = 1.5
RECENT_MUSIC_COUNT = 3

# Setiap nilai adalah rantai filter ffmpeg. Satu dipilih acak per video.
# Warnanya dibuat terlihat, teks putih tetap dijaga kontras lewat DIM dan bayangan.
GRADES = {
    "teal_oranye": "colorbalance=rs=-0.12:gs=0.04:bs=0.14:rh=0.08:bh=-0.08,eq=saturation=1.2:contrast=1.08",
    "keemasan": "colorbalance=rs=0.14:gs=0.06:bs=-0.12,eq=saturation=1.15:brightness=0.02",
    "dingin": "colorbalance=rs=-0.1:gs=0.02:bs=0.12,eq=saturation=0.95:contrast=1.05",
    "vivid": "eq=saturation=1.35:contrast=1.08",
    "vintage_pudar": "hue=s=0.7,eq=saturation=0.75:contrast=0.9:brightness=0.05",
    "moody": "eq=brightness=-0.06:contrast=1.15:saturation=0.9",
    "magenta": "colorbalance=rm=0.1:bm=0.06,eq=saturation=1.2",
    "hitam_putih": "hue=s=0,eq=contrast=1.2",
}
# Teks putih dengan garis tepi hitam dan bayangan gelap yang jelas, tanpa kotak di belakang.
TEXT_STYLE = "borderw=3:bordercolor=black@0.9:shadowcolor=black@0.7:shadowx=3:shadowy=3"
# Sedikit menggelapkan video supaya teks putih terbaca.
DIM = "eq=brightness=-0.04"

# (overlay, caption) dipakai jika LiteLLM gagal. Urutannya mengikuti services.
FALLBACK_COPY = [
    ("Tugas numpuk, deadline makin mepet, dan kepala udah mau meledak? Tenang dulu, "
     "kamu nggak harus ngerjain semuanya sendirian. Serahin ke Jauki Academy, "
     "kami bantu rapi dan tepat waktu.",
     "Tugas numpuk? Chat kami sekarang, dikerjakan rapi dan tepat waktu."),
    ("Skripsi udah jadi, daftar pustaka udah rapi, tapi nomor halaman masih berantakan? "
     "Relate banget kan. Biar nggak ada revisi berulang, format skripsi dan makalahmu "
     "kami rapikan sesuai standar kampus. Tinggal kirim, kami kerjain, kamu tinggal cetak.",
     "Formatting skripsi dan makalah sesuai panduan kampus. Cek jaukitugas.my.id."),
    ("Slide presentasi kamu masih polos dan bikin ngantuk? Yuk, kita bikin desain slide "
     "yang estetik, rapi, dan konsisten dari halaman pertama sampai terakhir. "
     "Presentasi kamu jadi beda dan bikin kamu lebih pede tampil di depan kelas.",
     "Desain slide estetik untuk presentasi kelas. DM kami untuk konsultasi."),
    ("Bingung olah data, coding, atau materi kuliah yang rasanya nggak masuk-masuk? "
     "Itu wajar banget, kok. Kamu nggak harus paham sendirian. Jauki Academy siap bimbing "
     "kamu dari dasar, step by step, sampai kamu benar-benar ngerti dan bisa jalan sendiri.",
     "Bimbingan belajar dari dasar sampai paham. Chat kami untuk jadwal."),
    ("Skripsi berat itu bukan tanda kamu gagal. Ada hari kamu capek, ada hari kamu mau nyerah, "
     "dan itu manusiawi banget. Yang penting kamu tetap jalan pelan-pelan, karena satu bab "
     "yang selesai tetap satu langkah lebih dekat ke wisuda.",
     "Kamu tidak sendirian menghadapi skripsi. Yuk, diskusi dulu lewat DM."),
]
FALLBACK_HASHTAGS = ["#jokitugas", "#skripsi", "#mahasiswa", "#desainslide", "#bimbinganbelajar"]
COPY_STYLES = ["relatable mahasiswa", "motivasi", "lucu ringan", "promo halus", "gaya POV"]

# Istilah suku, agama, ras, dan golongan. Teks yang memuatnya ditolak dan dipakai fallback.
SARA_TERMS = [
    "islam", "kristen", "katolik", "katholik", "kristiani", "nasrani", "hindu", "buddha", "budha",
    "konghucu", "kafir", "muslim", "yahudi", "jawa", "sunda", "batak", "madura", "bugis",
    "makassar", "minang", "betawi", "aceh", "melayu", "dayak", "papua", "ambon", "flores", "bali",
    "tionghoa", "cina", "china", "arab", "pribumi", "negro", "suku", "agama", "ras",
]
SARA_PATTERN = re.compile(r"\b(?:" + "|".join(SARA_TERMS) + r")\b")


def has_sara(text):
    return SARA_PATTERN.search(text.lower()) is not None

SYSTEM_PROMPT = (
    "Kamu menulis konten TikTok dan Reels untuk akun Jauki Academy (jaukitugas.my.id). "
    "Layanan: joki tugas, formatting makalah dan skripsi, desain slide presentasi estetik, "
    "dan bimbingan belajar. Gaya bahasanya harus seperti konten TikTok Indonesia yang real dan relatable: "
    "santai, pakai bahasa gaul yang wajar (misalnya banget, sih, deh, relate, real), "
    "seperti curhat mahasiswa yang lagi capek skripsi. Jangan pakai bahasa baku atau kalimat iklan yang kaku. "
    "Dilarang menyinggung atau menyebut suku, agama, ras, atau golongan apa pun, dilarang kasar, "
    "dilarang kekerasan, dan dilarang topik politik. "
    "Balas HANYA dengan JSON valid tanpa markdown, berisi kunci: "
    '"overlay" (22 sampai 40 kata, satu sampai tiga kalimat pendek, tanpa emoji dan tanpa tanda kutip, '
    "tampil di tengah video dengan gaya kutipan atau curhat), "
    '"caption" (satu sampai dua kalimat gaya TikTok, boleh berisi ajakan DM atau chat), '
    '"hashtags" (array 5 sampai 10 string, diawali #, huruf kecil, tanpa spasi), '
    '"mood" (salah satu dari: {moods}; pilih yang paling cocok dengan suasana teksnya).'
)

log = logging.getLogger("content-bot")


class BotError(Exception):
    exit_code = 1


class HistoryError(BotError):
    exit_code = 2


def load_env():
    if not ENV_FILE.exists():
        return
    for line in ENV_FILE.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip("\"'"))


def load_config():
    return json.loads(CONFIG_FILE.read_text(encoding="utf-8"))


def load_history():
    if not HISTORY_FILE.exists():
        return []
    try:
        history = json.loads(HISTORY_FILE.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise HistoryError(f"history.json rusak ({exc}). Perbaiki manual, jangan di-reset.") from exc
    if not isinstance(history, list):
        raise HistoryError("history.json harus berisi list.")
    return history


def save_history(history):
    tmp = HISTORY_FILE.parent / (HISTORY_FILE.name + ".tmp")
    with tmp.open("w", encoding="utf-8") as fh:
        json.dump(history, fh, ensure_ascii=False, indent=2)
        fh.flush()
        os.fsync(fh.fileno())
    os.replace(tmp, HISTORY_FILE)


def iso_week(stamp, tz):
    year, week, _ = datetime.fromisoformat(stamp).astimezone(tz).isocalendar()
    return year, week


def should_run_today(history, per_week, tz, rng):
    """Pilih hari secara acak sampai kuota mingguan terpenuhi."""
    now = datetime.now(tz)
    year, week, weekday = now.isocalendar()
    made = sum(1 for e in history if iso_week(e["created_at"], tz) == (year, week))
    quota = per_week - made
    if quota <= 0:
        return False, f"kuota {per_week}/minggu sudah terpenuhi ({made} video minggu ini)"
    days_left = 8 - weekday  # hari ini ikut dihitung
    if quota >= days_left:
        return True, f"sisa kuota {quota}, sisa hari {days_left}: wajib buat hari ini"
    chance = quota / days_left
    return rng.random() < chance, f"sisa kuota {quota} dari {days_left} hari, peluang {chance:.2f}"


def parse_json(text):
    start, end = text.find("{"), text.rfind("}")
    if start == -1 or end == -1:
        raise ValueError("tidak ada objek JSON di jawaban")
    return json.loads(text[start : end + 1])


def validate_copy(data, moods):
    if not isinstance(data, dict):
        raise ValueError("jawaban bukan objek JSON")
    overlay = " ".join(re.sub(r'["“”]', "", str(data.get("overlay", ""))).split())
    if not 22 <= len(overlay.split()) <= 40:
        raise ValueError(f"overlay tidak valid: {overlay!r}")
    caption = " ".join(str(data.get("caption", "")).split())
    if not caption:
        raise ValueError("caption kosong")
    raw_tags = data.get("hashtags")
    if not isinstance(raw_tags, list):
        raise ValueError("hashtags bukan list")
    tags = []
    for raw in raw_tags:
        tag = "#" + re.sub(r"[^a-z0-9_]", "", str(raw).lower().lstrip("#"))
        if tag != "#" and tag not in tags:
            tags.append(tag)
    if len(tags) < 5:
        raise ValueError("hashtag kurang dari 5")
    if has_sara(" ".join([overlay, caption, *tags])):
        raise ValueError("teks mengandung istilah SARA")
    mood = str(data.get("mood", "")).strip().lower()
    return {
        "overlay": overlay,
        "caption": caption,
        "hashtags": tags[:10],
        "mood": mood if mood in moods else None,
        "copy_source": "llm",
    }


def fetch_copy(topic, moods, rng):
    headers = {"Content-Type": "application/json"}
    api_key = os.environ.get("LITELLM_API_KEY", "")
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"
    body = {
        "model": LLM_MODEL,
        "messages": [
            {"role": "system", "content": SYSTEM_PROMPT.format(moods=", ".join(moods))},
            {
                "role": "user",
                "content": f"Topik: {topic}. Gaya: {rng.choice(COPY_STYLES)}. "
                "Tulis variasi yang belum pernah dipakai.",
            },
        ],
        "temperature": 0.9,
        "max_tokens": 2048,  # Gemini 2.5 ikut memakai token untuk reasoning
    }
    for attempt in range(1, LLM_ATTEMPTS + 1):
        try:
            resp = requests.post(LLM_URL, json=body, headers=headers, timeout=30)
            resp.raise_for_status()
            choice = resp.json()["choices"][0]
            if choice.get("finish_reason") == "length":
                raise ValueError("jawaban terpotong (finish_reason=length)")
            return validate_copy(parse_json(choice["message"]["content"] or ""), moods)
        except (requests.RequestException, ValueError, KeyError, IndexError, TypeError) as exc:
            log.warning("LiteLLM percobaan %d/%d gagal: %s", attempt, LLM_ATTEMPTS, exc)
    return None


def fallback_copy(topic_idx):
    overlay, caption = FALLBACK_COPY[topic_idx % len(FALLBACK_COPY)]
    return {
        "overlay": overlay,
        "caption": caption,
        "hashtags": list(FALLBACK_HASHTAGS),
        "mood": None,
        "copy_source": "fallback",
    }


def pick_music(preferred, moods, history, rng):
    recent = {e.get("music") for e in history[-RECENT_MUSIC_COUNT:]}
    others = [m for m in moods if m != preferred]
    rng.shuffle(others)
    order = ([preferred] if preferred else []) + others
    for mood in order:
        tracks = sorted(p for p in (MUSIC_DIR / mood).glob("*") if p.suffix.lower() in MUSIC_EXT)
        if not tracks:
            continue
        fresh = [p for p in tracks if f"{mood}/{p.name}" not in recent]
        track = rng.choice(fresh or tracks)
        return mood, f"{mood}/{track.name}", track
    raise BotError("Tidak ada lagu di music/<mood>/. Isi dulu dengan musik berlisensi komersial.")


def ffprobe(path, *extra):
    out = subprocess.run(
        ["ffprobe", "-v", "error", "-of", "json", *extra, str(path)],
        capture_output=True,
        text=True,
        check=True,
    )
    return json.loads(out.stdout)


def probe_duration(path):
    return float(ffprobe(path, "-show_format")["format"]["duration"])


def probe_source(path):
    """Durasi dan ukuran tampilan. Rotasi dari HP ikut diperhitungkan."""
    data = ffprobe(path, "-show_format", "-select_streams", "v:0", "-show_streams")
    stream = data["streams"][0]
    width, height = int(stream["width"]), int(stream["height"])
    rotation = int(stream.get("tags", {}).get("rotate", 0))
    for side in stream.get("side_data_list", []):
        if "rotation" in side:
            rotation = int(side["rotation"])
    if abs(rotation) % 180 == 90:
        width, height = height, width
    return float(data["format"]["duration"]), width, height


def pick_slot(history, name, size, src_dur, span, skip_head, skip_tail, rng):
    """Cari titik mulai acak di celah yang belum pernah dipakai, di luar bagian buangan."""
    low, high = skip_head, src_dur - skip_tail
    if high - low < span:
        return None
    used = sorted(
        (e["start_s"], e["end_s"])
        for e in history
        if e["source"] == name and e["source_size"] == size
    )
    gaps, cur = [], low
    for start, end in used:
        if start >= high:
            break
        if start > cur:
            gaps.append((cur, start))
        cur = max(cur, end)
    if cur < high:
        gaps.append((cur, high))
    fits = [(a, b) for a, b in gaps if b - a >= span]
    if not fits:
        return None
    a, b = rng.choices(fits, weights=[b - a for a, b in fits])[0]
    return math.floor(rng.uniform(a, b - span) * 100 + 1e-9) / 100


def pick_segment(history, cfg, rng):
    sources = {}
    for path in sorted(p for p in RAW_DIR.iterdir() if p.suffix.lower() in RAW_EXT and not p.name.startswith(".")):
        try:
            duration, width, height = probe_source(path)
        except (subprocess.CalledProcessError, KeyError, IndexError, ValueError) as exc:
            log.warning("Lewati %s, tidak bisa dibaca: %s", path.name, exc)
            continue
        sources[path] = (duration, width, height, path.stat().st_size)
    if not sources:
        raise BotError("raw_videos/ kosong atau tidak ada .mp4/.mov yang bisa dibaca.")

    items = list(sources.items())
    rng.shuffle(items)
    combos = [(d, s) for d in cfg["durations"] for s in cfg["speed_choices"]]
    rng.shuffle(combos)
    for duration, speed in combos:
        span = duration * speed  # detik sumber yang dipakai sebelum dipercepat
        for path, (src_dur, width, height, size) in items:
            start = pick_slot(
                history, path.name, size, src_dur, span,
                cfg["skip_head_s"], cfg["skip_tail_s"], rng,
            )
            if start is not None:
                return {
                    "path": path,
                    "name": path.name,
                    "size": size,
                    "src_dur": src_dur,
                    "landscape": width > height,
                    "start": start,
                    "span": span,
                    "duration": duration,
                    "speed": speed,
                }
    return None


def build_filter_complex(seg, grade, vignette, text_path, font, volume):
    duration = seg["duration"]
    setpts = f"setpts=PTS/{seg['speed']}," if seg["speed"] != 1.0 else ""
    if seg["landscape"]:
        # Latar blur dari video yang sama, video asli di tengah agar tidak terpotong.
        head = (
            f"[0:v]{setpts}split=2[bg][fg];"
            f"[bg]scale={WIDTH}:{HEIGHT}:force_original_aspect_ratio=increase,"
            f"crop={WIDTH}:{HEIGHT},boxblur=luma_radius=25:luma_power=2[bgb];"
            f"[fg]scale={WIDTH}:{HEIGHT}:force_original_aspect_ratio=decrease[fgs];"
            "[bgb][fgs]overlay=(W-w)/2:(H-h)/2[base];"
        )
    else:
        head = (
            f"[0:v]{setpts}scale={WIDTH}:{HEIGHT}:force_original_aspect_ratio=increase,"
            f"crop={WIDTH}:{HEIGHT}[base];"
        )
    drawtext = (
        f"drawtext=textfile={text_path}:expansion=none:fontfile={font}:fontsize={FONT_SIZE}:"
        f"fontcolor=white:line_spacing=12:{TEXT_STYLE}:"
        "x=(w-text_w)/2:y=(h-text_h)/2"
    )
    effects = [f"fps={FPS}", "setsar=1", DIM, GRADES[grade]]
    if vignette:
        effects.append("vignette=PI/5")
    effects.append(drawtext)
    video = head + "[base]" + ",".join(effects) + "[v]"
    audio = (
        f"[1:a]volume={volume},"
        f"afade=t=in:st=0:d={MUSIC_FADE_IN_S},"
        f"afade=t=out:st={duration - MUSIC_FADE_OUT_S:.2f}:d={MUSIC_FADE_OUT_S}[a]"
    )
    return f"{video};{audio}"


def render(seg, track, music_offset, music_loop, grade, vignette, overlay, volume, font, out_path):
    fd, text_name = tempfile.mkstemp(suffix=".txt")
    os.close(fd)
    text_path = Path(text_name)
    text_path.write_text("\n".join(textwrap.wrap(overlay, WRAP_CHARS, break_long_words=False)), encoding="utf-8")
    cmd = ["ffmpeg", "-y", "-hide_banner", "-loglevel", "error", "-ss", f"{seg['start']:.2f}", "-i", str(seg["path"])]
    if music_loop:
        cmd += ["-stream_loop", "-1"]
    cmd += ["-ss", f"{music_offset:.2f}", "-i", str(track)]
    cmd += [
        "-filter_complex", build_filter_complex(seg, grade, vignette, text_path, font, volume),
        "-map", "[v]", "-map", "[a]", "-t", str(seg["duration"]),
        "-c:v", "libx264", "-preset", "veryfast", "-crf", "22",
        "-profile:v", "high", "-level", "4.2", "-pix_fmt", "yuv420p",
        "-c:a", "aac", "-b:a", "128k", "-ar", "44100", "-ac", "2",
        "-movflags", "+faststart", str(out_path),
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True)
    finally:
        text_path.unlink(missing_ok=True)
    if result.returncode != 0:
        out_path.unlink(missing_ok=True)
        raise BotError(f"ffmpeg gagal:\n{result.stderr[-800:]}")


def check_output(path):
    """Pastikan hasil sesuai format TikTok: MP4, H.264, 1080x1920, dan audio AAC."""
    data = ffprobe(path, "-show_format", "-show_streams")
    video = next((s for s in data["streams"] if s["codec_type"] == "video"), None)
    audio = next((s for s in data["streams"] if s["codec_type"] == "audio"), None)
    problems = []
    if video is None:
        problems.append("tanpa stream video")
    else:
        if (int(video["width"]), int(video["height"])) != (WIDTH, HEIGHT):
            problems.append(f"ukuran {video['width']}x{video['height']}")
        if video["codec_name"] != "h264" or video.get("pix_fmt") != "yuv420p":
            problems.append(f"video {video['codec_name']}/{video.get('pix_fmt')}")
    if audio is None or audio["codec_name"] != "aac":
        problems.append(f"audio {audio['codec_name'] if audio else 'tidak ada'}")
    if "mp4" not in data["format"]["format_name"]:
        problems.append("container bukan MP4")
    if problems:
        path.unlink(missing_ok=True)
        raise BotError("Output tidak sesuai format TikTok: " + ", ".join(problems))
    return float(data["format"]["duration"])


def unique(items):
    return list(dict.fromkeys(items))


def main():
    parser = argparse.ArgumentParser(description="Bot Reels Jauki Academy")
    parser.add_argument("--dry-run", action="store_true", help="tampilkan rencana, tanpa render dan tanpa mengubah history")
    parser.add_argument("--force", action="store_true", help="lewati cek jadwal mingguan")
    parser.add_argument("--no-llm", action="store_true", help="pakai teks fallback, tanpa memanggil LiteLLM")
    parser.add_argument("--live", action="store_true",
                        help="publish ke Instagram Reels; tanpa flag ini tidak ada panggilan API")
    args = parser.parse_args()

    for folder in (RAW_DIR, OUT_DIR, MUSIC_DIR, LOG_DIR):
        folder.mkdir(parents=True, exist_ok=True)
    load_env()

    lock = LOCK_FILE.open("w")
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        log.warning("Proses lain masih berjalan. Lewati.")
        return 0

    cfg = load_config()
    tz = ZoneInfo(cfg["timezone"])
    history = load_history()
    rng = random.Random()

    if not args.force:
        run_today, reason = should_run_today(history, cfg["videos_per_week"], tz, rng)
        log.info("Jadwal: %s", reason)
        if not run_today:
            return 0

    services = cfg["services"]
    topic_idx = len(history) % len(services)
    topic = services[topic_idx]

    copy = None if args.no_llm else fetch_copy(topic, cfg["music_moods"], rng)
    if copy is None:
        copy = fallback_copy(topic_idx)
        log.warning("Memakai teks fallback.")
    hashtags = unique(copy["hashtags"] + cfg["brand_hashtags"])
    preferred_mood = copy["mood"] or rng.choice(cfg["music_moods"])

    mood, music_rel, track = pick_music(preferred_mood, cfg["music_moods"], history, rng)
    seg = pick_segment(history, cfg, rng)
    if seg is None:
        log.info("Semua potongan sudah habis. Tambahkan video ke raw_videos/. Tidak ada yang dibuat.")
        return 0

    duration = seg["duration"]
    last_grade = history[-1].get("grade") if history else None
    grade = rng.choice([g for g in GRADES if g != last_grade])
    vignette = rng.random() < 0.5

    track_dur = probe_duration(track)
    if track_dur >= duration + 1:
        music_offset, music_loop = rng.uniform(0, track_dur - duration), False
    else:
        music_offset, music_loop = 0.0, True

    font = os.environ.get("FONT_PATH", DEFAULT_FONT)
    log.info("Topik: %s | teks: %s | mood: %s | musik: %s", topic, copy["copy_source"], mood, music_rel)
    log.info(
        "Sumber: %s, ambil dari %.2f dtk (buang %d dtk awal dan akhir), span %.2f dtk",
        seg["name"], seg["start"], cfg["skip_head_s"], seg["span"],
    )
    log.info("Output %d dtk, kecepatan %.1fx, grade %s, vignette %s",
             duration, seg["speed"], grade, vignette)
    log.info("Overlay: %s", copy["overlay"])
    log.info("Caption: %s", copy["caption"])

    if args.dry_run:
        log.info("Dry run selesai. Tidak ada file yang dibuat.")
        return 0
    if args.live and not all(os.environ.get(k) for k in ("IG_USER_ID", "IG_ACCESS_TOKEN", "PUBLIC_VIDEO_BASE_URL")):
        raise BotError("Untuk --live, isi IG_USER_ID, IG_ACCESS_TOKEN, dan PUBLIC_VIDEO_BASE_URL di .env.")

    stamp = datetime.now(tz)
    entry_id = uuid.uuid4().hex[:8]
    out_path = OUT_DIR / f"reel_{stamp:%Y%m%d_%H%M%S}_{entry_id}.mp4"
    render(seg, track, music_offset, music_loop, grade, vignette, copy["overlay"],
           cfg["music_volume"], font, out_path)
    actual = check_output(out_path)
    caption_text = f"{copy['caption']}\n\n{' '.join(hashtags)}"
    out_path.with_suffix(".txt").write_text(f"{caption_text}\n", encoding="utf-8")
    post_id, post_error = None, None
    if args.live:
        video_url = f"{os.environ['PUBLIC_VIDEO_BASE_URL'].rstrip('/')}/{out_path.name}"
        try:
            client = ReelsClient(os.environ["IG_ACCESS_TOKEN"], os.environ["IG_USER_ID"])
            post_id = publish_reel(client, video_url, caption_text)
            log.info("Terposting ke Instagram Reels, id media %s", post_id)
        except (GraphError, PublishError) as exc:
            post_error = str(exc)
            log.error("Gagal posting ke Instagram: %s", post_error)

    history.append({
        "id": entry_id,
        "created_at": stamp.isoformat(timespec="seconds"),
        "source": seg["name"],
        "source_size": seg["size"],
        "source_duration": round(seg["src_dur"], 2),
        "start_s": round(seg["start"], 2),
        "end_s": round(seg["start"] + seg["span"], 2),
        "speed": seg["speed"],
        "duration": duration,
        "output_duration": round(actual, 2),
        "output": out_path.name,
        "topic": topic,
        "overlay": copy["overlay"],
        "caption": copy["caption"],
        "hashtags": hashtags,
        "copy_source": copy["copy_source"],
        "mood": mood,
        "music": music_rel,
        "grade": grade,
        "vignette": vignette,
        "instagram_media_id": post_id,
        "post_error": post_error,
    })
    save_history(history)
    log.info("Selesai: %s (%.2f dtk)", out_path.name, actual)
    return 1 if post_error else 0


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s", stream=sys.stdout)
    try:
        sys.exit(main())
    except BotError as exc:
        log.error("%s", exc)
        sys.exit(exc.exit_code)
    except Exception:
        log.exception("Error tak terduga")
        sys.exit(1)
