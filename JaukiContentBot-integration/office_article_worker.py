"""Worker artikel Living AI Office untuk Kauiz dan Jauki.

Mengambil perintah agent `jauki-article` dari dashboard:
- generate_article: model teks menulis artikel dari analisis jadwal, Muse membuat sampul 16:9, hasilnya disimpan sebagai DRAF di situs
  brand (kauiz.web.id atau jaukitugas.my.id) dan dilaporkan ke kartu jadwal lewat content_id.
- publish_article: menerbitkan draf yang tertaut ke kartu itu.
- schedule_article: menjadwalkan draf lewat API situs (scheduled_at).

Tidak ada yang terbit tanpa perintah publish dari dashboard. Dijalankan sebagai layanan terpisah (lihat deployment/article-worker).
Perintah uji: `preview` (hanya model teks, tanpa Muse dan tanpa situs) dan `draft --live` (Muse dan draf di situs, tidak diterbitkan).
"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import logging
import os
import sys
import time
import uuid
from datetime import datetime
from pathlib import Path
from typing import Any, Callable, Dict, Mapping, Optional
from zoneinfo import ZoneInfo



def _bootstrap_paths() -> None:
    """Modul bersama (prompt_bank, visual_prompt_writer, visual_memory) ada di VISUAL_MEMORY_DIR. Dibaca dari lingkungan atau worker.env di sebelah modul ini."""
    directory = os.environ.get("VISUAL_MEMORY_DIR", "")
    env_file = Path(__file__).resolve().parent / "worker.env"
    if not directory and env_file.exists():
        for line in env_file.read_text(encoding="utf-8").splitlines():
            if line.strip().startswith("VISUAL_MEMORY_DIR="):
                directory = line.split("=", 1)[1].strip().strip('"').strip("'")
    if directory and directory not in sys.path:
        sys.path.insert(0, directory)


_bootstrap_paths()

import article_content as content_writer  # noqa: E402  (setelah _bootstrap_paths)
import article_site  # noqa: E402
import office_bridge  # noqa: E402
import office_command_worker as base  # noqa: E402
import threads_content  # noqa: E402

LOG = logging.getLogger("office_article_worker")
AGENT_ID = "jauki-article"
TIMEZONE = "Asia/Jakarta"
COVER_LIMIT_KB = {"kauiz": 4500, "jauki": 1800}   # di bawah batas situs (5 MB dan 2 MB)
HISTORY_KEPT = 12
DEFAULT_OUTPUT = Path(__file__).resolve().parent / "article_output"
DEFAULT_STATE = Path(__file__).resolve().parent / "article_state.json"
LLM_MODEL_DEFAULT = threads_content.LLM_MODEL_DEFAULT


class Settings:
    def __init__(self, env: Mapping[str, str]):
        self.env = env
        self.state_path = Path(env.get("ARTICLE_STATE_FILE") or DEFAULT_STATE)
        self.output_dir = Path(env.get("ARTICLE_OUTPUT_DIR") or DEFAULT_OUTPUT)
        self.worker_dir = env.get("KAUIZ_WORKER_DIR", "")
        self.openrouter_key = env.get("OPENROUTER_API_KEY", "")
        self.openrouter_base = env.get("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1")

    def site(self, brand: str, send: Optional[Callable[..., Any]] = None) -> article_site.ArticleSite:
        key = brand.upper()
        return article_site.ArticleSite(brand, self.env.get(f"ARTICLE_{key}_TOKEN", ""), self.env.get(f"ARTICLE_{key}_BASE_URL") or None, send=send)

    def endpoints(self) -> list[dict[str, str]]:
        if not self.openrouter_key:
            return []
        return [{"base_url": self.openrouter_base, "api_key": self.openrouter_key, "model": self.env.get("ARTICLE_LLM_MODEL", LLM_MODEL_DEFAULT)}]


# -- status dan sampul ------------------------------------------------------------------------
def load_state(path: Path) -> dict[str, Any]:
    return threads_content._load_state(path)


def save_state(path: Path, state: Mapping[str, Any]) -> None:
    threads_content._save_state(path, state)


def _seed(key: str) -> int:
    return int(hashlib.sha256(key.encode("utf-8")).hexdigest(), 16) % 2**32


def _kauiz_worker(settings: Settings):
    """Mesin Muse dan pas-gambar milik worker feed (kauiz_auto_worker), dipakai ulang supaya perilakunya sama."""
    if settings.worker_dir and settings.worker_dir not in sys.path:
        sys.path.insert(0, settings.worker_dir)
    import kauiz_auto_worker

    return kauiz_auto_worker


def encode_jpeg(image: Any, limit_kb: int) -> bytes:
    """JPEG di bawah batas ukuran situs: kualitas diturunkan bertahap."""
    for quality in (90, 84, 78, 72, 66, 60, 54):
        buffer = io.BytesIO()
        image.save(buffer, "JPEG", quality=quality, optimize=True)
        if buffer.tell() <= limit_kb * 1024:
            return buffer.getvalue()
    raise RuntimeError(f"gambar sampul tidak bisa dikecilkan di bawah {limit_kb} KB")


def make_cover(settings: Settings, brand: str, article: Mapping[str, Any], key: str, state: Dict[str, Any]) -> tuple[bytes, str, str]:
    """(jpeg, layout_id, prompt). Tata letak bergilir per brand dan tidak berulang dalam 3 terakhir."""
    history = list(state.setdefault("layouts", {}).get(brand, []))
    layout = content_writer.pick_cover_layout(brand, history, _seed(key))
    prompt = content_writer.cover_prompt(brand, article, layout)
    worker = _kauiz_worker(settings)
    raw = worker.generate_background(prompt, api_key=settings.openrouter_key, base_url=settings.openrouter_base, model=worker.MUSE_MODEL, fmt="article")
    jpeg = encode_jpeg(worker.fit_to_canvas(raw, "article"), COVER_LIMIT_KB[brand])
    state["layouts"][brand] = (history + [layout])[-HISTORY_KEPT:]
    return jpeg, layout, prompt


def jakarta_timestamp(value: str, brand: str) -> str:
    moment = datetime.fromisoformat(str(value).replace("Z", "+00:00"))
    if moment.tzinfo is None:
        moment = moment.replace(tzinfo=ZoneInfo(TIMEZONE))
    return moment.astimezone(ZoneInfo(TIMEZONE)).strftime(article_site.SITES[brand].timestamp_format)


# -- penanganan perintah ----------------------------------------------------------------------
def _start(context: Dict[str, Any], title: str, task_type: str, activity: str) -> None:
    task_id = str(uuid.uuid4())
    context["task_id"] = task_id
    base._emit_required("task.started", agent_id=AGENT_ID, activity=activity, task={"id": task_id, "title": title, "type": task_type, "target": "article"})
    context["task_started"] = True


def _progress(context: Dict[str, Any], progress: int, activity: str) -> None:
    base._emit_required("task.progress", agent_id=AGENT_ID, task_id=context["task_id"], progress=progress, activity=activity)


def _complete(context: Dict[str, Any], title: str, stage: str) -> None:
    base._emit_required("task.completed", agent_id=AGENT_ID, task_id=context["task_id"], progress=100, result={"title": title, "stage": stage})


def _content_id(payload: Mapping[str, Any]) -> str:
    value = payload.get("content_id")
    if value in (None, ""):
        raise RuntimeError("Perintah tidak membawa content_id kartu jadwal")
    return str(value)


def _preview_text(article: Mapping[str, Any]) -> str:
    body = content_writer.plain_text(article["content"])
    return f"{article['excerpt']}\n\n{body[:700]}{'...' if len(body) > 700 else ''}"


def handle_generate_article(context: Dict[str, Any], settings: Settings, send: Optional[Callable[..., Any]] = None) -> None:
    payload = context.get("payload") or {}
    brand = content_writer.brand_slug(payload.get("brand"))
    key = _content_id(payload)
    _start(context, f"Generate artikel {brand}", "article_generation", f"Menulis artikel {brand}")
    site = settings.site(brand, send)            # gagal lebih awal bila token situs belum diatur
    _progress(context, 15, "Menulis artikel dari analisis")
    article = content_writer.write_article(brand, payload, settings.endpoints())

    state = load_state(settings.state_path)
    _progress(context, 45, "Membuat gambar sampul 16:9")
    image: Optional[bytes] = None
    note = ""
    try:
        image, layout, _ = make_cover(settings, brand, article, key, state)
    except Exception as error:  # sampul gagal tidak boleh menghilangkan artikel yang sudah ditulis
        LOG.warning("sampul artikel %s gagal: %s", key, base._safe_error(error))
        note = " (tanpa sampul)"

    _progress(context, 75, "Menyimpan draf di situs")
    entry = state.setdefault("articles", {}).get(key)
    form = {**article, "tags": article["tags"]}
    if entry and entry.get("brand") == brand and entry.get("status") == "draft":
        summary = site.update_draft(entry["article_id"], form, image)
    else:
        summary = site.create_draft(form, image)
    state["articles"][key] = {"brand": brand, "article_id": summary["id"], "slug": summary.get("slug"), "title": article["title"], "status": "draft", "url": summary.get("url")}
    save_state(settings.state_path, state)

    settings.output_dir.mkdir(parents=True, exist_ok=True)
    (settings.output_dir / f"{brand}-{key}.html").write_text(article["content"], encoding="utf-8")
    base._emit_required("content.preview_ready", agent_id=AGENT_ID, activity=f"Draf artikel {brand} siap{note}", content={
        "platform": "article", "content_type": "article", "content_id": payload["content_id"], "title": article["title"],
        "text": _preview_text(article), "image_url": summary.get("image_url"), "external_id": str(summary["id"]),
        "metadata": {"brand": brand, "article_id": summary["id"], "slug": summary.get("slug"), "site_status": "draft", "model": article.get("model")},
    })
    _complete(context, "Draf artikel siap", "preview_ready")


def _entry(settings: Settings, payload: Mapping[str, Any]) -> tuple[str, Dict[str, Any], Dict[str, Any]]:
    key = _content_id(payload)
    state = load_state(settings.state_path)
    entry = state.get("articles", {}).get(key)
    if not entry:
        raise RuntimeError("Belum ada draf artikel untuk kartu ini, jalankan generate dulu")
    return key, state, entry


def handle_publish_article(context: Dict[str, Any], settings: Settings, send: Optional[Callable[..., Any]] = None) -> None:
    payload = context.get("payload") or {}
    key, state, entry = _entry(settings, payload)
    if entry.get("status") == "published":
        raise RuntimeError("Artikel ini sudah terbit")
    _start(context, f"Publish artikel {entry['brand']}", "article_publish", "Menerbitkan artikel")
    _progress(context, 50, "Menerbitkan di situs")
    summary = settings.site(entry["brand"], send).publish(entry["article_id"])
    entry.update({"status": "published", "url": summary.get("url") or entry.get("url")})
    save_state(settings.state_path, state)
    base._emit_required("content.published", agent_id=AGENT_ID, activity="Artikel terbit", content={
        "platform": "article", "content_type": "article", "content_id": payload["content_id"], "title": entry["title"],
        "external_id": str(entry["article_id"]), "public_url": entry.get("url"),
    })
    _complete(context, "Artikel terbit", "published")


def handle_schedule_article(context: Dict[str, Any], settings: Settings, send: Optional[Callable[..., Any]] = None) -> None:
    payload = context.get("payload") or {}
    key, state, entry = _entry(settings, payload)
    if entry.get("status") == "published":
        raise RuntimeError("Artikel ini sudah terbit")
    if not payload.get("scheduled_at"):
        raise RuntimeError("Perintah jadwal tidak membawa scheduled_at")
    _start(context, f"Jadwalkan artikel {entry['brand']}", "article_schedule", "Menjadwalkan artikel")
    stamp = jakarta_timestamp(payload["scheduled_at"], entry["brand"])
    settings.site(entry["brand"], send).schedule(entry["article_id"], stamp)
    entry["status"] = "scheduled"
    save_state(settings.state_path, state)
    _complete(context, f"Artikel dijadwalkan {stamp}", "scheduled")


HANDLERS = {"generate_article": handle_generate_article, "publish_article": handle_publish_article, "schedule_article": handle_schedule_article}


class ArticleCommandClient(base.OfficeCommandClient):
    def claim(self) -> Optional[Dict[str, Any]]:
        response = self._request("POST", "/api/office/commands/claim", {"agent_id": AGENT_ID})
        command = response.get("command")
        return command if isinstance(command, dict) else None


def process_command(command: Dict[str, Any], client: Any, settings: Settings, send: Optional[Callable[..., Any]] = None) -> bool:
    command_id = str(command.get("id") or "")
    if not command_id:
        LOG.warning("Perintah tanpa ID diabaikan")
        return False
    payload = command.get("payload")
    context: Dict[str, Any] = {"task_id": None, "task_started": False, "payload": payload if isinstance(payload, dict) else {}}
    client.update(command_id, "running")
    handler = HANDLERS.get(command.get("action")) if command.get("agent_id") == AGENT_ID else None
    try:
        if handler is None:
            raise RuntimeError("Perintah tidak diizinkan untuk worker artikel")
        handler(context, settings, send)
    except Exception as error:
        safe = base._safe_error(error)
        LOG.warning("perintah %s gagal: %s", command_id, safe)
        if context["task_started"] and not office_bridge.emit_event_sync(
                "task.failed", agent_id=AGENT_ID, parent_system=office_bridge.PARENT_SYSTEM, task_id=context["task_id"], error=safe):
            LOG.warning("task.failed untuk perintah %s tidak terkirim", command_id)
        try:
            client.update(command_id, "failed", safe)
        except Exception as update_error:
            LOG.warning("perintah %s tidak bisa ditandai gagal: %s", command_id, base._safe_error(update_error))
        return False
    client.update(command_id, "completed")
    return True


def poll_once(settings: Settings, client: Optional[Any] = None) -> bool:
    client = client or ArticleCommandClient()
    command = client.claim()
    if command is None:
        return False
    process_command(command, client, settings)
    return True


def run_daemon(settings: Settings, interval: float = 5.0) -> None:
    client = ArticleCommandClient()
    LOG.info("worker artikel berjalan, polling tiap %.0f detik", interval)
    while True:
        try:
            poll_once(settings, client)
        except Exception as error:
            LOG.warning("polling gagal: %s", base._safe_error(error))
        time.sleep(interval)


# -- perintah uji -----------------------------------------------------------------------------
def load_env_file(path: Path, env: Dict[str, str]) -> None:
    if not path.exists():
        return
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        name, value = line.split("=", 1)
        env.setdefault(name.strip(), value.strip().strip('"').strip("'"))


def cli(argv: Optional[list[str]] = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    parser.add_argument("mode", choices=("daemon", "preview", "draft"))
    parser.add_argument("--brand", choices=content_writer.BRANDS)
    parser.add_argument("--topic")
    parser.add_argument("--brief", default="")
    parser.add_argument("--env-file", default=str(Path(__file__).resolve().parent / "worker.env"))
    parser.add_argument("--live", action="store_true", help="draft: benar-benar memanggil Muse dan membuat draf di situs (tidak diterbitkan)")
    args = parser.parse_args(argv)
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    env = dict(os.environ)
    load_env_file(Path(args.env_file), env)
    for name in ("OFFICE_BRIDGE_URL", "OFFICE_BRIDGE_TOKEN", "VISUAL_MEMORY_DIR"):
        if env.get(name):
            os.environ.setdefault(name, env[name])
    if env.get("VISUAL_MEMORY_DIR") and env["VISUAL_MEMORY_DIR"] not in sys.path:
        sys.path.insert(0, env["VISUAL_MEMORY_DIR"])
    settings = Settings(env)
    if args.mode == "daemon":
        run_daemon(settings)
        return 0
    if not args.brand or not args.topic:
        parser.error("--brand dan --topic wajib untuk preview dan draft")
    payload = {"brand": args.brand, "topic": args.topic, "brief": args.brief, "content_id": "uji-" + hashlib.sha1(args.topic.encode()).hexdigest()[:8]}
    article = content_writer.write_article(args.brand, payload, settings.endpoints())
    words = len(content_writer.plain_text(article["content"]).split())
    print(json.dumps({k: article[k] for k in ("title", "excerpt", "category", "tags", "seo_title", "seo_description", "cover_headline", "cover_subline", "cover_subject")}, ensure_ascii=False, indent=1))
    print(f"isi: {words} kata, model {article.get('model')}")
    state = load_state(settings.state_path)
    layout = content_writer.pick_cover_layout(args.brand, state.get("layouts", {}).get(args.brand, []), _seed(payload["content_id"]))
    print(f"tata letak sampul: {layout}\n{content_writer.cover_prompt(args.brand, article, layout)}")
    settings.output_dir.mkdir(parents=True, exist_ok=True)
    out = settings.output_dir / f"{args.brand}-{payload['content_id']}.html"
    out.write_text(article["content"], encoding="utf-8")
    print(f"isi HTML: {out}")
    if args.mode == "preview":
        return 0
    if not args.live:
        print("draft tanpa --live tidak membuat apa pun di situs dan tidak memanggil Muse")
        return 0
    cover, layout, _ = make_cover(settings, args.brand, article, payload["content_id"], state)
    summary = settings.site(args.brand).create_draft(article, cover)
    save_state(settings.state_path, state)
    (settings.output_dir / f"{args.brand}-{payload['content_id']}.jpg").write_bytes(cover)
    print(f"draf dibuat di situs: id {summary['id']}, status {summary.get('status')}, gambar {summary.get('image_url')}")
    return 0


if __name__ == "__main__":
    raise SystemExit(cli())
