"""Kirim Reels yang sudah dirender ke Telegram, lengkap dengan jam dan caption untuk diunggah manual ke TikTok.

Hanya pustaka standar. Token bot tidak pernah dicetak, termasuk di pesan error.
Batas Bot API: unggah video sampai 50 MB. File lebih besar dikirim sebagai tautan.
"""

import json
import urllib.error
import urllib.request
import uuid
from datetime import datetime
from pathlib import Path

API = "https://api.telegram.org"
VIDEO_LIMIT = 49 * 1024 * 1024
CAPTION_LIMIT = 1000  # batas Telegram untuk caption video adalah 1024
TEXT_LIMIT = 4000     # batas pesan Telegram adalah 4096
TIKTOK_CAPTION_LIMIT = 2200
DAYS = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"]
MONTHS = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"]


class TelegramError(RuntimeError):
    pass


def split_hashtags(text):
    """(isi, [hashtag]) dengan hashtag diambil dari paragraf terakhir bila isinya hanya hashtag."""
    parts = text.rstrip().rsplit("\n\n", 1)
    if len(parts) == 2:
        tokens = parts[1].split()
        if tokens and all(token.startswith("#") for token in tokens):
            return parts[0].rstrip(), tokens
    return text.strip(), []


def pick_hashtags(tags, limit):
    """Hashtag untuk TikTok: #fyp dan hashtag terakhir (brand, karena caption_for menaruhnya di belakang) selalu ikut, sisanya
    diisi dari yang paling awal. Urutan asli dipertahankan."""
    if len(tags) <= limit:
        return list(tags)
    keep = {index for index, tag in enumerate(tags) if tag.lower() == "#fyp"}
    keep.add(len(tags) - 1)
    for index in range(len(tags)):
        if len(keep) >= limit:
            break
        keep.add(index)
    return [tags[index] for index in sorted(keep)][:limit]


def tiktok_caption(text, max_hashtags=5):
    """Caption TikTok: isi yang sama, tetapi hashtag dibatasi supaya tidak seperti spam dan tetap di bawah batas karakter."""
    body, tags = split_hashtags(text)
    caption = body + ("\n\n" + " ".join(pick_hashtags(tags, max_hashtags)) if tags else "")
    return caption[:TIKTOK_CAPTION_LIMIT].rstrip()


def upload_time_label(slot_at, tz_label="WIB"):
    return f"{DAYS[slot_at.weekday()]}, {slot_at.day} {MONTHS[slot_at.month - 1]} {slot_at.year} jam {slot_at:%H:%M} {tz_label}"


def intro_text(slot_at, label="Reels Jauki"):
    return (f"🎬 {label} siap diunggah ke TikTok\n"
            f"Upload: {upload_time_label(slot_at)}\n"
            "Instagram Reels tayang otomatis di jam yang sama.")


def _multipart(fields, file_field=None, file_path=None):
    boundary = "----jauki" + uuid.uuid4().hex
    chunks = []
    for name, value in fields.items():
        chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode("utf-8"))
    if file_field and file_path:
        chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{file_field}"; filename="{Path(file_path).name}"\r\n'
                       "Content-Type: video/mp4\r\n\r\n").encode("utf-8") + Path(file_path).read_bytes() + b"\r\n")
    chunks.append(f"--{boundary}--\r\n".encode("utf-8"))
    return b"".join(chunks), f"multipart/form-data; boundary={boundary}"


def _call(token, method, fields, file_field=None, file_path=None, timeout=300):
    body, content_type = _multipart(fields, file_field, file_path)
    request = urllib.request.Request(f"{API}/bot{token}/{method}", data=body, method="POST", headers={"Content-Type": content_type})
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as err:
        try:
            payload = json.loads(err.read().decode("utf-8"))
        except ValueError:
            payload = {"ok": False, "description": f"HTTP {err.code}"}
    except (urllib.error.URLError, OSError, ValueError) as err:
        raise TelegramError(f"koneksi ke Telegram gagal: {type(err).__name__}") from None
    if not payload.get("ok"):
        raise TelegramError(f"Telegram menolak {method}: {str(payload.get('description', 'tanpa pesan'))[:160]}")
    return payload


def send_reel(token, chat_id, video_path, slot_at, caption_text, *, label="Reels Jauki", public_url=None, call=None):
    """Kirim video (atau tautan bila terlalu besar) lalu caption TikTok sebagai pesan terpisah supaya mudah disalin."""
    if not token or not chat_id:
        raise TelegramError("TELEGRAM_BOT_TOKEN atau TELEGRAM_CHAT_ID belum diisi")
    call = call or (lambda method, fields, file_field=None, file_path=None: _call(token, method, fields, file_field, file_path))
    video_path = Path(video_path)
    intro = intro_text(slot_at, label)
    size = video_path.stat().st_size if video_path.exists() else None
    if size is None:
        raise TelegramError(f"file video tidak ditemukan: {video_path.name}")
    if size > VIDEO_LIMIT:
        link = public_url or "(unduh dari server)"
        call("sendMessage", {"chat_id": chat_id, "text": f"{intro}\n\nVideo {size // (1024 * 1024)} MB melewati batas Telegram. Unduh di sini:\n{link}"[:TEXT_LIMIT]})
        sent_video = False
    else:
        call("sendVideo", {"chat_id": chat_id, "caption": intro[:CAPTION_LIMIT], "supports_streaming": "true"}, "video", str(video_path))
        sent_video = True
    call("sendMessage", {"chat_id": chat_id, "text": ("Caption TikTok (salin):\n\n" + tiktok_caption(caption_text))[:TEXT_LIMIT]})
    return {"video": sent_video, "size": size}
