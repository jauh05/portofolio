"""Isi postingan Threads untuk Jauki: bank 150 materi, atau model teks OpenRouter bila ada analisis.

Aturan sumber (sama dengan feed dan story):
- Jadwal membawa topik, pesan kunci, ringkasan, atau analisis: model teks menulis postingannya dari analisis itu.
- Jadwal kosong: materi bank berikutnya yang belum terpakai (jt-001 sampai jt-150), tiap materi hanya sekali.
- Bank habis dan tidak ada analisis: model teks menulis postingan baru dari tema Jauki, dengan daftar teks terakhir sebagai
  larangan mengulang. Tanpa model teks, pembuatan gagal dengan pesan jelas, tidak mengulang materi lama.

Pemakaian bank dicatat di THREADS_BANK_STATE saat draf dibuat. Modul ini tidak memposting apa pun.
"""

from __future__ import annotations

import json
import os
import re
import sys
import tempfile
from pathlib import Path
from typing import Any, Callable, Mapping, Optional

BRAND = "jauki"
FORMAT = "threads"
BODY_LIMIT = 420          # karakter isi tanpa hashtag; batas Threads 500 termasuk hashtag
POST_LIMIT = 500
DEFAULT_MAX_TAGS = 2      # Threads hanya mengenali satu tag topik, sisanya tampil sebagai teks biasa
RECENT_KEPT = 60
LLM_BASE_URL_DEFAULT = "https://openrouter.ai/api/v1"
LLM_MODEL_DEFAULT = "meta-llama/llama-3.1-8b-instruct"
STATE_FILE_DEFAULT = Path(__file__).resolve().parent / "threads_bank_state.json"

# Kata yang tidak boleh muncul: SARA, makian, dan sebutan yang menyindir orang.
SENSITIVE_WORDS = [
    "agama", "islam", "kristen", "katolik", "hindu", "buddha", "budha", "yahudi", "cina", "tionghoa", "pribumi", "jawa", "batak", "sunda",
    "papua", "madura", "bugis", "arab", "kafir", "ateis", "suku", "etnis", "gay", "lesbi", "bencong", "cacat", "gila", "idiot", "bodoh",
    "tolol", "goblok", "dungu", "partai", "politik", "pilpres", "jahat", "pelit", "killer", "sialan", "brengsek", "anjing", "bangsat",
]
ANALYSIS_KEYS = ("key_message", "topic", "brief", "angle", "hook_direction", "objective", "research_summary", "key_findings")


class ThreadsContentError(RuntimeError):
    pass


class BankExhausted(ThreadsContentError):
    pass


def _modules(memory_dir: Optional[str] = None):
    """prompt_bank dan visual_prompt_writer ada di folder memori visual bersama (VISUAL_MEMORY_DIR)."""
    directory = memory_dir or os.environ.get("VISUAL_MEMORY_DIR")
    if directory and directory not in sys.path:
        sys.path.insert(0, directory)
    import prompt_bank
    import visual_prompt_writer

    return prompt_bank, visual_prompt_writer


def has_analysis(payload: Mapping[str, Any]) -> bool:
    return any(payload.get(key) for key in ANALYSIS_KEYS)


def compose(text: str, tags: list[str], max_tags: int = DEFAULT_MAX_TAGS) -> str:
    """Isi dan hashtag dalam satu teks. Hashtag dibuang dari belakang bila melewati batas Threads."""
    body = re.sub(r"\n{3,}", "\n\n", "\n".join(" ".join(line.split()) for line in str(text or "").splitlines())).strip()
    chosen = [tag for tag in tags if tag][:max(0, max_tags)]
    while chosen and len(body) + 2 + len(" ".join(chosen)) > POST_LIMIT:
        chosen.pop()
    return body + ("\n\n" + " ".join(chosen) if chosen else "")


def _whole_word(word: str, blob: str) -> bool:
    return re.search(r"(?<!\w)" + re.escape(word.lower()) + r"(?!\w)", blob) is not None


def validate_post(text: str, tags: list[str], brand_data: Mapping[str, Any]) -> list[str]:
    errors: list[str] = []
    body = str(text or "").strip()
    if not body:
        return ["teks kosong"]
    if len(body) > BODY_LIMIT:
        errors.append(f"teks maksimal {BODY_LIMIT} karakter, sekarang {len(body)}")
    if "#" in body:
        errors.append("hashtag tidak boleh ada di dalam teks, taruh di daftar hashtags")
    if re.search(r"https?://|www\.", body, re.IGNORECASE):
        errors.append("tidak boleh memuat tautan")
    if not tags or not all(re.fullmatch(r"#[A-Za-z0-9_]+", str(tag)) for tag in tags):
        errors.append("hashtags harus berformat #kata dan minimal satu")
    blob = (body + " " + " ".join(tags)).lower()
    banned = [word for word in list(brand_data.get("avoid_claims", [])) + SENSITIVE_WORDS if _whole_word(word, blob)]
    if banned:
        errors.append("kata terlarang: " + ", ".join(sorted(set(banned))))
    return errors


def _load_state(path: Path) -> dict[str, Any]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        return data if isinstance(data, dict) else {}
    except (OSError, ValueError):
        return {}


def _save_state(path: Path, state: Mapping[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp = tempfile.mkstemp(dir=str(path.parent), prefix=".threads-state-")
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as handle:
            json.dump(state, handle, ensure_ascii=False, indent=1)
        os.replace(tmp, path)
    except BaseException:
        try:
            os.unlink(tmp)
        finally:
            raise


def llm_endpoints(env: Mapping[str, str]) -> list[dict[str, str]]:
    """Endpoint model teks dari lingkungan. Kunci OpenRouter sama dengan yang dipakai Muse dan penulis feed."""
    key = env.get("THREADS_LLM_API_KEY") or env.get("KAUIZ_LLM_API_KEY") or env.get("OPENROUTER_API_KEY") or ""
    endpoints: list[dict[str, str]] = []
    if key:
        endpoints.append({"base_url": env.get("THREADS_LLM_BASE_URL", LLM_BASE_URL_DEFAULT), "api_key": key,
                          "model": env.get("THREADS_LLM_MODEL", LLM_MODEL_DEFAULT)})
    if env.get("THREADS_LLM_FALLBACK_MODEL") and env.get("THREADS_LLM_FALLBACK_BASE_URL"):
        endpoints.append({"base_url": env["THREADS_LLM_FALLBACK_BASE_URL"], "api_key": env.get("THREADS_LLM_FALLBACK_API_KEY", ""),
                          "model": env["THREADS_LLM_FALLBACK_MODEL"]})
    return endpoints


def build_messages(brand_data: Mapping[str, Any], payload: Mapping[str, Any], kind: str, recent: list[str],
                   feedback: Optional[list[str]] = None) -> list[dict[str, str]]:
    system = (
        "Kamu penulis postingan Threads untuk akun @jaukitugas, konten ringan seputar tugas dan kehidupan mahasiswa. "
        'Balas HANYA dengan satu objek JSON valid berkunci "text" dan "hashtags", tanpa teks lain.\n'
        "Aturan:\n"
        f"- text berbahasa Indonesia santai, maksimal {BODY_LIMIT - 40} karakter, tanpa hashtag dan tanpa tautan di dalamnya. Satu sampai tiga baris pendek.\n"
        "- Jenis 'keluhan': keluh kesah mahasiswa yang lucu dan relatable. Jenis 'tips': penemuan atau tips praktis tugas kuliah yang benar dan berguna.\n"
        "- Mengolok situasi atau diri sendiri, tidak mengolok orang, dosen, kampus, kelompok, gender, atau kondisi ekonomi. Tanpa SARA, politik, makian, atau candaan melukai diri.\n"
        "- Jangan menyebut atau menyiratkan layanan joki, jasa, harga, diskon, promo, pesan, order, bayar. Jangan mengajak curang atau plagiat.\n"
        "- Pakai hanya fakta dari data yang diberikan. Jangan mengarang angka, penelitian, atau nama.\n"
        "- hashtags: 3 sampai 4 tag huruf kecil berformat #kata. Tag pertama adalah tag topik yang paling cocok, lalu #jaukitugas.\n"
        "- Maksimal satu emoji. Jangan mengulang ide atau kalimat dari daftar 'jangan_diulang'."
    )
    analysis = {key: payload.get(key) for key in ANALYSIS_KEYS if payload.get(key)}
    user: dict[str, Any] = {
        "jenis": kind, "analisis": analysis or "tidak ada, tulis dari tema umum tugas dan mahasiswa",
        "nada": brand_data.get("tone", {}).get("style"), "tag_brand": brand_data.get("hashtags", {}).get("brand"),
        "tag_topik_contoh": brand_data.get("hashtags", {}).get("topic"), "jangan_diulang": recent[-20:],
    }
    if feedback:
        user["perbaiki"] = feedback
    return [{"role": "system", "content": system}, {"role": "user", "content": json.dumps(user, ensure_ascii=False)}]


def _normalize_tags(tags: Any) -> list[str]:
    out: list[str] = []
    for tag in tags or []:
        tag = "#" + re.sub(r"[^A-Za-z0-9_]", "", str(tag).lstrip("#")).lower()
        if len(tag) > 1 and tag not in out:
            out.append(tag)
    return out


def _fallback_from_payload(brand_data: Mapping[str, Any], payload: Mapping[str, Any]) -> Optional[dict[str, Any]]:
    """Tanpa model teks: pesan kunci atau topik dan ringkasan dari jadwal, dengan hashtag brand."""
    head = str(payload.get("key_message") or payload.get("topic") or "").strip()
    brief = str(payload.get("brief") or "").strip()
    if not head:
        return None
    text = head.rstrip(".!?") + "." + (("\n\n" + brief) if brief else "")
    topic_tag = "#" + re.sub(r"[^a-z0-9]", "", str(payload.get("topic") or head).lower())[:24]
    tags = _normalize_tags([topic_tag if len(topic_tag) > 3 else "#tugaskuliah"] + list(brand_data["hashtags"]["brand"]) + ["#mahasiswa"])
    return {"text": text[:BODY_LIMIT].rstrip(), "tags": tags}


def _ask_model(brand_data, payload, kind, recent, endpoints, writer, transport=None):
    errors: list[str] = []
    for endpoint in endpoints:
        feedback: list[str] = []
        for attempt in range(2):
            try:
                content = writer.chat_json(
                    base_url=endpoint["base_url"], api_key=endpoint.get("api_key", ""), model=endpoint["model"],
                    messages=build_messages(brand_data, payload, kind, recent, feedback or None),
                    transport=endpoint.get("transport") or transport,
                )
            except writer.WriterError as err:
                errors.append(f"{endpoint['model']}: {err}")
                break  # gangguan endpoint tidak diulang, lanjut ke endpoint berikutnya
            text = str(content.get("text") or "").strip()
            tags = _normalize_tags(content.get("hashtags"))
            feedback = validate_post(text, tags, brand_data)
            if not feedback and text.lower() in {item.lower() for item in recent}:
                feedback = ["teks sama dengan postingan sebelumnya"]
            if not feedback:
                return {"text": text, "tags": tags, "model": endpoint["model"]}, errors
            errors += [f"{endpoint['model']} percobaan {attempt + 1}: {item}" for item in feedback]
    return None, errors


def generate(payload: Optional[Mapping[str, Any]] = None, *, env: Optional[Mapping[str, str]] = None, state_path: Optional[Path] = None,
             transport: Optional[Callable[..., Any]] = None, memory_dir: Optional[str] = None) -> dict[str, Any]:
    """Satu postingan Threads Jauki: {text, source, bank_id, model, errors}. Memakai dan mencatat bank bila sumbernya bank."""
    payload = dict(payload or {})
    env = os.environ if env is None else env
    bank, writer = _modules(memory_dir)
    brand_data = writer.load_brand_data(BRAND)
    path = Path(state_path or env.get("THREADS_BANK_STATE") or STATE_FILE_DEFAULT)
    state = _load_state(path)
    max_tags = int(env.get("THREADS_MAX_TAGS", DEFAULT_MAX_TAGS))
    recent: list[str] = list(state.get("recent", []))
    endpoints = llm_endpoints(env)
    result: Optional[dict[str, Any]] = None
    errors: list[str] = []

    if has_analysis(payload):
        kind = "tips" if re.search(r"\btips?\b", str(payload.get("angle") or payload.get("topic") or "").lower()) else "keluhan"
        found, errors = _ask_model(brand_data, payload, kind, recent, endpoints, writer, transport)
        fallback = found or _fallback_from_payload(brand_data, payload)
        if fallback and not found and validate_post(fallback["text"], fallback["tags"], brand_data):
            fallback = None
        if not fallback:
            raise ThreadsContentError("Analisis ada tetapi teks Threads tidak lolos pemeriksaan: " + "; ".join(errors or ["isi kosong"]))
        result = {**fallback, "source": "llm" if found else "fallback", "bank_id": None}
    else:
        item = bank.next_item(state, BRAND, FORMAT)
        if item is not None:
            result = {"text": item["text"], "tags": list(item["tags"]), "source": "bank", "bank_id": item["id"]}
        else:
            kind = "tips" if len(recent) % 2 else "keluhan"
            found, errors = _ask_model(brand_data, payload, kind, recent, endpoints, writer, transport)
            if not found:
                raise BankExhausted("Bank Threads Jauki habis (150 dari 150 terpakai) dan model teks tidak menghasilkan postingan yang lolos: "
                                    + "; ".join(errors or ["kunci model teks belum diatur"]))
            result = {**found, "source": "llm", "bank_id": None}

    text = compose(result["text"], result["tags"], max_tags)
    if result["bank_id"]:
        bank.consume(state, BRAND, FORMAT, result["bank_id"])
    state["recent"] = (recent + [result["text"]])[-RECENT_KEPT:]
    _save_state(path, state)
    return {"text": text, "source": result["source"], "bank_id": result["bank_id"], "model": result.get("model"), "errors": errors}
