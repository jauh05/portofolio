"""Penulis artikel Kauiz dan Jauki: isi HTML aman, metadata SEO, dan teks sampul.

Model teks (OpenRouter, kunci yang sama dengan feed, story, dan Threads) menulis dari analisis jadwal dashboard. Hasilnya diperiksa:
HTML hanya tag yang diizinkan kedua situs, panjang kolom sesuai validasi situs, tanpa kata terlarang, tanpa harga di luar data brand.
Tanpa model teks, pembuatan gagal dengan pesan jelas (artikel tidak punya cadangan template). Modul ini tidak memanggil situs maupun Muse.
"""

from __future__ import annotations

import json
import re
from typing import Any, Mapping, Optional

import visual_prompt_writer as writer
from threads_content import SENSITIVE_WORDS

BRANDS = ("kauiz", "jauki")
# Tag yang lolos sanitasi di KEDUA situs (irisan). Atribut selalu dibuang.
ALLOWED_TAGS = ("p", "h2", "h3", "ul", "ol", "li", "strong", "em", "blockquote")
ANALYSIS_KEYS = ("topic", "brief", "key_message", "angle", "hook_direction", "objective", "audience", "audience_problem",
                 "research_summary", "key_findings", "source_evidence")
WORDS = (350, 1100)
LIMITS = {"title": 90, "excerpt": 300, "seo_title": 60, "seo_description": 155, "category": 60, "tag": 30}
DANGLING = {"untuk", "yang", "dan", "di", "ke", "dari", "pada", "dengan", "ala", "agar", "saat", "atau", "serta", "bagi", "buat", "tanpa", "the", "for", "and"}
PERSON_WORDS = {"student", "students", "person", "people", "man", "woman", "men", "women", "girl", "boy", "someone", "character", "mahasiswa"}
INDONESIAN_HINTS = {"yang", "dan", "dengan", "untuk", "dari", "pada", "meja", "belajar", "buku", "rapi", "sedang", "seorang", "mahasiswa", "kertas", "catatan"}
COVER = {"headline_words": 8, "headline_chars": 56, "subline_words": 12, "subline_chars": 72}


class ArticleError(RuntimeError):
    pass


def brand_slug(name: Any) -> str:
    slug = re.sub(r"[^a-z]", "", str(name or "").lower())
    if slug not in BRANDS:
        raise ArticleError(f"Brand artikel tidak dikenali: {name!r}. Brand yang didukung: {', '.join(BRANDS)}")
    return slug


def sanitize_html(html: str) -> str:
    """Buang semua tag di luar daftar izin, hapus atribut, dan rapikan spasi. Teks di dalam tag yang dibuang tetap dipertahankan."""
    html = re.sub(r"(?is)<(script|style|iframe|object|embed)\b.*?</\1\s*>", "", str(html or ""))
    allowed = "|".join(ALLOWED_TAGS)
    html = re.sub(rf"(?i)<(/?)({allowed})\b[^>]*>", lambda match: f"<{match.group(1)}{match.group(2).lower()}>", html)
    html = re.sub(rf"(?i)<(?!/?(?:{allowed})>)[^>]*>", "", html)
    return re.sub(r"\n{3,}", "\n\n", html).strip()


def plain_text(html: str) -> str:
    return " ".join(re.sub(r"<[^>]+>", " ", str(html or "")).split())


def _word_count(text: str) -> int:
    return len(plain_text(text).split())


def build_messages(brand_data: Mapping[str, Any], payload: Mapping[str, Any], feedback: Optional[list[str]] = None) -> list[dict[str, str]]:
    kauiz = brand_data["brand"] == "kauiz"
    system = (
        f"Kamu penulis artikel blog untuk brand {brand_data['name']} ({brand_data['website']}). Balas HANYA dengan satu objek JSON valid, tanpa teks lain, "
        'dengan kunci: "title", "excerpt", "content", "category", "tags", "seo_title", "seo_description", "cover_headline", "cover_subline", "cover_subject".\n'
        "Aturan:\n"
        f"- Bahasa Indonesia, nada sesuai data tone. Isi {WORDS[0] + 100} sampai {WORDS[1] - 200} kata, struktur jelas: pembuka, minimal dua subjudul <h2>, daftar <ul> atau <ol> bila cocok, penutup.\n"
        f"- content adalah HTML hanya dengan tag {', '.join('<' + tag + '>' for tag in ALLOWED_TAGS)}. Tanpa atribut, tanpa <h1>, tanpa gambar, tanpa tautan, tanpa tabel.\n"
        f"- title maksimal {LIMITS['title']} karakter, excerpt maksimal {LIMITS['excerpt']}, seo_title maksimal {LIMITS['seo_title'] - 5} karakter berupa frasa utuh (jangan berakhir pada kata sambung), seo_description maksimal {LIMITS['seo_description'] - 10}.\n"
        f"- category satu frasa pendek (maksimal {LIMITS['category']} karakter). tags: 3 sampai 5 kata kunci huruf kecil (maksimal {LIMITS['tag']} karakter per tag).\n"
        f"- cover_headline maksimal {COVER['headline_words']} kata dan {COVER['headline_chars']} karakter (judul singkat untuk gambar sampul). cover_subline maksimal "
        f"{COVER['subline_words']} kata dan {COVER['subline_chars']} karakter. cover_subject berbahasa Inggris, satu benda atau sekelompok benda konkret (BUKAN orang), misalnya 'a laptop beside a stack of books and an alarm clock'.\n"
        "- Fakta hanya dari data brand dan bagian 'bahan'. Saran umum yang masuk akal boleh. Jangan mengarang angka, statistik, penelitian, kutipan, nama orang, atau sumber.\n"
        "- Jangan menyebut atau menyiratkan avoid_claims. Harga hanya dari allowed_price_texts atau tidak disebut. Tanpa SARA, politik, makian, atau sindiran kepada orang atau kelompok.\n"
        + ("- Penutup boleh mengajak pembaca mencoba Kauiz di situs brand, tanpa janji berlebihan.\n" if kauiz else
           "- Jangan menyebut atau menyiratkan layanan joki, jasa pengerjaan tugas, harga, atau ajakan memesan. Artikel murni humor dan tips tugas mahasiswa.\n")
    )
    analysis = {key: payload.get(key) for key in ANALYSIS_KEYS if payload.get(key)}
    user: dict[str, Any] = {"brand": writer._trim_brand(brand_data), "bahan": analysis or "tidak ada, tulis dari tema brand", "perintah": payload.get("prompt")}
    if feedback:
        user["perbaiki"] = feedback
    return [{"role": "system", "content": system}, {"role": "user", "content": json.dumps(user, ensure_ascii=False)}]


def validate_article(article: Mapping[str, Any], brand_data: Mapping[str, Any]) -> list[str]:
    errors: list[str] = []
    for key in ("title", "excerpt", "content", "category", "seo_title", "seo_description", "cover_headline", "cover_subline", "cover_subject"):
        if not str(article.get(key) or "").strip():
            errors.append(f"{key} kosong")
    if errors:
        return errors
    for key in ("title", "excerpt", "seo_title", "seo_description", "category"):
        if len(str(article[key])) > LIMITS[key]:
            errors.append(f"{key} maksimal {LIMITS[key]} karakter, sekarang {len(str(article[key]))}")
    content = str(article["content"])
    count = _word_count(content)
    if not WORDS[0] <= count <= WORDS[1]:
        errors.append(f"isi harus {WORDS[0]}-{WORDS[1]} kata, sekarang {count}")
    if len(re.findall(r"(?i)<h2>", content)) < 2:
        errors.append("isi harus punya minimal dua subjudul <h2>")
    if len(re.findall(r"(?i)<p>", content)) < 4:
        errors.append("isi harus punya minimal empat paragraf <p>")
    if sanitize_html(content) != content.strip():
        errors.append("isi memuat tag atau atribut di luar daftar izin")
    tags = article.get("tags")
    if not isinstance(tags, list) or not 3 <= len(tags) <= 5 or any(not str(tag).strip() or len(str(tag)) > LIMITS["tag"] for tag in tags):
        errors.append(f"tags harus 3-5 kata kunci, masing-masing maksimal {LIMITS['tag']} karakter")
    headline, subline = str(article["cover_headline"]), str(article["cover_subline"])
    if len(headline.split()) > COVER["headline_words"] or len(headline) > COVER["headline_chars"]:
        errors.append("cover_headline terlalu panjang")
    if len(subline.split()) > COVER["subline_words"] or len(subline) > COVER["subline_chars"]:
        errors.append("cover_subline terlalu panjang")
    if '"' in headline + subline:
        errors.append("teks sampul tidak boleh memuat tanda kutip ganda")
    if not _english_subject(str(article["cover_subject"])):
        errors.append("cover_subject harus benda atau adegan berbahasa Inggris, bukan orang (kartu sampul sudah menggambar mahasiswa sendiri)")
    blob = " ".join([plain_text(content), *(str(article[key]) for key in ("title", "excerpt", "seo_title", "seo_description", "cover_headline", "cover_subline")),
                     *(str(tag) for tag in tags or [])]).lower()
    avoid = [*brand_data.get("avoid_claims", []), *SENSITIVE_WORDS]
    banned = sorted({word for word in avoid if re.search(r"(?<!\w)" + re.escape(word.lower()) + r"(?!\w)", blob)})
    if banned:
        errors.append("kata terlarang: " + ", ".join(banned))
    allowed_prices = {re.sub(r"\s+", "", text).lower() for text in brand_data.get("allowed_price_texts", [])}
    for price in re.findall(r"Rp\s?[\d.]+", blob.replace("rp", "Rp")):
        if re.sub(r"\s+", "", price).lower().rstrip(".") not in allowed_prices:
            errors.append(f"harga tidak ada di data: {price}")
    return errors


DEFAULT_SUBJECT = "a tidy student study desk with notebooks, a pencil, and sticky notes"


def _clip(text: str, limit: int) -> str:
    """Pangkas rapi: utamakan akhir kalimat, lalu batas klausa (titik dua, strip, koma), baru batas kata."""
    text = " ".join(str(text or "").split())
    if len(text) <= limit:
        return text
    head = text[:limit + 1]
    sentence = max(head.rfind(". "), head.rfind("! "), head.rfind("? "))
    if sentence >= limit * 0.6:
        return head[:sentence + 1].rstrip()
    clause = max(head.rfind(": "), head.rfind(" - "), head.rfind(" | "), head.rfind(", "))
    if clause >= limit * 0.5:
        return head[:clause].rstrip(" ,;:-|")
    words = text[:limit].rsplit(" ", 1)[0].rstrip(" ,;:-|").split(" ")
    while len(words) > 1 and words[-1].lower() in DANGLING:  # jangan berhenti pada kata sambung
        words.pop()
    return " ".join(words).rstrip(" ,;:-|")


def _english_subject(subject: str) -> bool:
    words = set(re.findall(r"[a-z]+", subject.lower()))
    return bool(subject) and subject.isascii() and not words & (INDONESIAN_HINTS | PERSON_WORDS)


def _clean(article: Mapping[str, Any]) -> dict[str, Any]:
    tags: list[str] = []
    for tag in article.get("tags") or []:
        tag = " ".join(str(tag).lstrip("#").lower().split())
        if tag and tag not in tags:
            tags.append(tag)
    out = {key: " ".join(str(article.get(key) or "").split()) for key in
           ("title", "excerpt", "category", "seo_title", "seo_description", "cover_headline", "cover_subline", "cover_subject")}
    out["content"] = sanitize_html(str(article.get("content") or ""))
    out["tags"] = [tag for tag in tags if len(tag) <= LIMITS["tag"]][:5]
    # Kolom pendek dipangkas rapi, bukan ditolak: model kecil sering melewati batas beberapa karakter.
    for key in ("title", "excerpt", "seo_title", "seo_description", "category"):
        out[key] = _clip(out[key], LIMITS[key])
    out["cover_headline"] = writer._clip_headline(out["cover_headline"].replace('"', ""), COVER["headline_words"], COVER["headline_chars"]).rstrip(".").upper()
    out["cover_subline"] = writer._clip_words(out["cover_subline"].replace('"', ""), COVER["subline_words"], COVER["subline_chars"])
    if not _english_subject(out["cover_subject"]):
        out["cover_subject"] = DEFAULT_SUBJECT
    return out


def write_article(brand: str, payload: Mapping[str, Any], endpoints: list[dict[str, str]], *, transport=None,
                  brand_data: Optional[Mapping[str, Any]] = None) -> dict[str, Any]:
    """Artikel lengkap dari analisis jadwal. Dua percobaan per endpoint (yang kedua dengan daftar perbaikan), lalu endpoint berikutnya."""
    slug = brand_slug(brand)
    brand_data = brand_data or writer.load_brand_data(slug)
    if brand_data.get("brand") != slug:
        raise ArticleError(f"Data brand {brand_data.get('brand')} tidak boleh dipakai untuk {slug}")
    if not endpoints:
        raise ArticleError("Kunci model teks belum diatur (OPENROUTER_API_KEY), artikel tidak bisa ditulis")
    errors: list[str] = []
    for endpoint in endpoints:
        feedback: list[str] = []
        for attempt in range(2):
            try:
                content = writer.chat_json(
                    base_url=endpoint["base_url"], api_key=endpoint.get("api_key", ""), model=endpoint["model"],
                    messages=build_messages(brand_data, payload, feedback or None), transport=endpoint.get("transport") or transport,
                )
            except writer.WriterError as err:
                errors.append(f"{endpoint['model']}: {err}")
                break
            article = _clean(content)
            feedback = validate_article(article, brand_data)
            if not feedback:
                return {**article, "model": endpoint["model"], "errors": errors}
            errors += [f"{endpoint['model']} percobaan {attempt + 1}: {item}" for item in feedback]
    raise ArticleError("Artikel tidak lolos pemeriksaan: " + "; ".join(errors[-6:]))


def cover_prompt(brand: str, article: Mapping[str, Any], layout_id: str, *, brand_data: Optional[Mapping[str, Any]] = None) -> str:
    """Prompt Muse sampul 16:9 dari kartu tata letak artikel brand. Teks gambar: judul sampul, kalimat sampul, dan alamat situs."""
    slug = brand_slug(brand)
    brand_data = brand_data or writer.load_brand_data(slug)
    layouts = writer.load_article_layouts(brand_data)
    if layout_id not in layouts:
        raise ArticleError(f"Tata letak sampul {layout_id} bukan milik brand {slug}")
    source = {"headline": article["cover_headline"], "subheadline": article["cover_subline"]}
    return writer.write_content(slug, source, {}, layout_id, "article", str(article["cover_subject"]), cta_label=None,
                                footer=brand_data["website"], brand_data=brand_data, styles=layouts)["muse_prompt"]


def pick_cover_layout(brand: str, history: list[str], seed: int, *, brand_data: Optional[Mapping[str, Any]] = None, no_repeat: int = 3) -> str:
    import random

    slug = brand_slug(brand)
    layouts = sorted(writer.load_article_layouts(brand_data or writer.load_brand_data(slug)))
    recent = set(history[-no_repeat:])
    return random.Random(seed).choice([layout for layout in layouts if layout not in recent] or layouts)
