"""Penulis prompt Muse, caption, dan hashtag untuk satu postingan.

Masukan: data brand (brand-data/<brand>.json), isi postingan (analisis dashboard atau jadwal), template terpilih
(template-styles.json), dan format (feed 4:5 atau story 9:16). Keluaran: prompt Muse yang memuat teks gambar persis,
caption Indonesia, dan hashtag.

Prompt selalu bisa dibuat tanpa model teks (cadangan dari kartu gaya template). Bila model teks dikonfigurasi, ia menulis
versi yang lebih hidup, lalu hasilnya diperiksa: teks persis, warna hanya dari palet, harga hanya dari data, tanpa klaim
terlarang. Bila tidak lolos setelah satu perbaikan, dipakai cadangan. Modul ini tidak memanggil Muse.
"""

from __future__ import annotations

import json
import random
import re
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any, Callable, Mapping, Optional

from visual_memory import MEMORY_DIR

ASPECT_TEXT = {
    "feed": "portrait 4:5 composition",
    "story": ("tall 9:16 story composition, with all text and key subjects kept in the central area and the top 15 percent "
              "and bottom 18 percent left free of any text or important content"),
}
ASPECT_MARK = {"feed": "4:5", "story": "9:16"}
MARGIN_RULE = {
    "feed": ("Leave the left and right 10 percent of the width and the top and bottom 6 percent of the height free of any text or "
             "important object, and never let anything touch or get cut off by an edge."),
    "story": ("Leave the left and right 20 percent of the width free of any text or important object because the sides will be trimmed, "
              "keep everything below the top 15 percent and above the bottom 18 percent of the height, and never let anything touch or get cut off by an edge."),
}
FOOTER_WHERE = {"feed": "At the very bottom", "story": "Near the bottom, inside the safe area"}
CAPTION_LIMIT = 2200
HASHTAG_RANGE = (3, 12)


class WriterError(RuntimeError):
    pass


def load_brand_data(slug: str, path: Optional[Path] = None) -> dict[str, Any]:
    target = path or MEMORY_DIR / "brand-data" / f"{slug}.json"
    if not Path(target).exists():
        raise WriterError(f"Brand data not found: {target}")
    return json.loads(Path(target).read_text(encoding="utf-8"))


def load_styles(path: Optional[Path] = None) -> dict[str, Any]:
    return json.loads((path or MEMORY_DIR / "template-styles.json").read_text(encoding="utf-8"))["templates"]


def _clip_words(text: str, max_words: int, max_chars: int) -> str:
    words = " ".join(str(text or "").replace('"', "").split()).split()[:max_words]
    while words and len(" ".join(words)) > max_chars:
        words.pop()
    return " ".join(words).strip(" ,;:-")


def _clip_headline(text: str, max_words: int, max_chars: int) -> str:
    """Pangkas judul di batas klausa dulu (sebelum koma), baru di batas kata, supaya maknanya tetap utuh."""
    cleaned = " ".join(str(text or "").replace('"', "").split())
    if len(cleaned.split()) <= max_words and len(cleaned) <= max_chars:
        return cleaned.strip(" ,;:-")
    clause = re.split(r"[,:;\u2013\u2014]", cleaned)[0]
    return _clip_words(clause, max_words, max_chars)


def _short_line(text: str, max_words: int, max_chars: int) -> str:
    text = " ".join(str(text or "").replace('"', "").split())
    sentence = re.match(r"(.+?[.!?])(?:\s|$)", text)
    if sentence and len(sentence.group(1).split()) <= max_words and len(sentence.group(1)) <= max_chars:
        return sentence.group(1)
    return _clip_words(text, max_words, max_chars)


def derive_copy(source: Mapping[str, Any], style: Mapping[str, Any]) -> dict[str, Any]:
    """Teks yang tampil di gambar, dipangkas sesuai slot template. `source` berisi headline, subheadline, items."""
    slots = style["slots"]
    copy: dict[str, Any] = {"headline": _clip_headline(source.get("headline"), slots["headline"]["max_words"], slots["headline"]["max_chars"]).upper()}
    if not copy["headline"]:
        raise WriterError("Headline is empty")
    if slots.get("subline") and source.get("subheadline"):
        line = _short_line(source["subheadline"], slots["subline"]["max_words"], slots["subline"]["max_chars"])
        if line:
            copy["subline"] = line
    chips_slot = slots.get("chips")
    if chips_slot:
        upper = "capitals" in chips_slot["where"]
        chips = [_clip_words(item, 3, chips_slot["max_chars"]) for item in (source.get("items") or [])]
        chips = [chip.upper() if upper else chip for chip in chips if chip][: chips_slot["max"]]
        if len(chips) >= chips_slot["min"]:
            copy["chips"] = chips
    return copy


def _brandify(text: str, visual: Mapping[str, Any]) -> str:
    """Kartu gaya memakai kata warna Kauiz. Untuk merek lain kata itu diganti dengan nama warna merek tersebut."""
    for pattern, key, default in (
        (r"\blighter navy\b", "alt_name", "lighter navy"), (r"\bdeep navy\b", "ink_name", "deep navy"), (r"\bnavy\b", "ink_short", "navy"),
        (r"\bcream\b", "paper_name", "cream"), (r"\bwarm yellow\b", "accent_name", "warm yellow"),
    ):
        text = re.sub(pattern, visual.get(key, default), text)
    return text


def _quoted(items: list[str]) -> str:
    quoted = [f'"{item}"' for item in items]
    return quoted[0] if len(quoted) == 1 else ", ".join(quoted[:-1]) + " and " + quoted[-1]


def _format_args(brand_data: Mapping[str, Any], fmt: str, subject: str, headline: str) -> dict[str, str]:
    visual = brand_data["visual"]
    return {
        "aspect": ASPECT_TEXT[fmt], "ink": visual["ink"], "accent": visual["accent"], "palette": visual["palette_prompt"],
        "headline_style": visual["headline_style"], "subject": subject, "headline": headline,
    }


def required_texts(copy: Mapping[str, Any], cta_label: Optional[str], footer: str) -> list[str]:
    texts = [copy["headline"]]
    if copy.get("subline"):
        texts.append(copy["subline"])
    texts += list(copy.get("chips") or [])
    if cta_label:
        texts.append(cta_label)
    texts.append(footer)
    return texts


def deterministic_prompt(brand_data: Mapping[str, Any], style: Mapping[str, Any], copy: Mapping[str, Any], fmt: str,
                         subject: str, cta_label: str, footer: str) -> str:
    """Prompt dari kartu gaya template. Selalu tersedia, tanpa model teks."""
    visual = brand_data["visual"]
    args = _format_args(brand_data, fmt, subject, copy["headline"])
    slots = style["slots"]
    parts = [style["prompt_template"].format(**args)]
    style = {**style, "text_color": _brandify(style["text_color"], visual)}
    if copy.get("subline") and slots.get("subline"):
        parts.append(f'{slots["subline"]["where"]}, a small regular sans-serif line in {style["text_color"]} reads exactly "{copy["subline"]}".')
    if copy.get("chips") and slots.get("chips"):
        parts.append(f'{slots["chips"]["where"].format(**args)}; the texts read exactly {_quoted(copy["chips"])}.')
    use_cta = bool(slots.get("cta")) and bool(cta_label)
    if use_cta:
        parts.append(f'{slots["cta"]["where"]}, a rounded {visual["accent"]} pill button with {visual.get("ink_name", "deep navy")} bold text reads exactly "{cta_label}".')
    parts.append(f'{FOOTER_WHERE[fmt]}, {visual["footer_style"]} reads exactly "{footer}".')
    count = len(required_texts(copy, cta_label if use_cta else None, footer))
    parts.append(
        f"These {count} texts are the only text in the image, spelled exactly as written, sharp and fully legible, with generous "
        f"margins and no overlap with the main subject. {MARGIN_RULE[fmt]} The palette is limited to {visual['palette_prompt']}, plus black and white "
        "photography where mentioned. Clean editorial graphic design, crisp details, premium marketing look."
    )
    return _brandify(" ".join(parts), visual)


def deterministic_caption(brand_data: Mapping[str, Any], source: Mapping[str, Any], seed: int = 0) -> tuple[str, list[str]]:
    """Caption dan hashtag tanpa model teks. Dari bank (caption_text atau pain dan tip) bila ada, jika tidak dari data brand."""
    headline = " ".join(str(source.get("headline") or "").split()).rstrip(".!?")
    brief = " ".join(str(source.get("subheadline") or "").split())
    features = brand_data.get("features", [])
    hashtag_data = brand_data["hashtags"]
    topic_tags = random.Random(seed).sample(list(hashtag_data["topic"]), k=min(5, len(hashtag_data["topic"])))
    hashtags = list(hashtag_data["brand"]) + topic_tags
    if source.get("caption_text"):
        return str(source["caption_text"]).strip() + "\n\n" + " ".join(hashtags), hashtags
    haystack = f"{headline} {brief}".lower()
    named = {item["name"]: item for item in features}
    feature = named.get(source.get("feature") or "") or next(
        (item for item in features if any(word in haystack for word in item["name"].lower().split() if len(word) > 4)),
        features[seed % len(features)] if features else None)
    lines = [f"{headline}."]
    if source.get("pain"):
        lines += ["", str(source["pain"]).strip()]
    if source.get("tip"):
        lines += ["", str(source["tip"]).strip()]
    elif brief:
        lines += ["", brief]
    if feature and (source.get("feature") or not source.get("pain")):
        benefit = feature["benefit"][0].lower() + feature["benefit"][1:]
        lines += ["", f"Di {brand_data['name']} ada {feature['name']}: {benefit}."]
    if brand_data.get("cta_required", True):
        cta = brand_data["cta"]
        lines += ["", f"{cta['link_in_bio_text']} dan {cta['primary_text'][0].lower() + cta['primary_text'][1:]}."]
    return "\n".join(lines) + "\n\n" + " ".join(hashtags), hashtags


def _normalize_price(text: str) -> str:
    return re.sub(r"\s+", "", text).lower().rstrip(".")


def validate_content(content: Mapping[str, Any], brand_data: Mapping[str, Any], style: Mapping[str, Any], fmt: str,
                     cta_label: Optional[str], footer: str) -> list[str]:
    """Daftar masalah pada hasil model teks. Kosong berarti lolos."""
    errors: list[str] = []
    slots = style["slots"]
    headline = str(content.get("headline") or "")
    if not headline or len(headline.split()) > slots["headline"]["max_words"] or len(headline) > slots["headline"]["max_chars"]:
        errors.append(f"headline harus 1-{slots['headline']['max_words']} kata dan maksimal {slots['headline']['max_chars']} karakter")
    chips = list(content.get("chips") or [])
    chips_slot = slots.get("chips")
    if chips_slot and chips and not chips_slot["min"] <= len(chips) <= chips_slot["max"]:
        errors.append(f"chips harus {chips_slot['min']}-{chips_slot['max']} item")
    if chips and any(len(chip) > (chips_slot or {"max_chars": 18})["max_chars"] for chip in chips):
        errors.append("ada chip yang terlalu panjang")
    copy = {"headline": headline, "subline": content.get("subline") or None, "chips": chips if chips_slot else []}
    prompt = str(content.get("muse_prompt") or "")
    if not 400 <= len(prompt) <= 3200:
        errors.append("panjang muse_prompt harus 400-3200 karakter")
    if ASPECT_MARK[fmt] not in prompt:
        errors.append(f"muse_prompt harus menyebut rasio {ASPECT_MARK[fmt]}")
    if not re.search(r"left and right (10|20) percent", prompt):
        errors.append("muse_prompt harus memuat aturan margin (sisi kiri dan kanan dibiarkan kosong (10 percent feed, 20 percent story))")
    use_cta = cta_label if slots.get("cta") and cta_label else None
    for text in required_texts({k: v for k, v in copy.items() if v}, use_cta, footer):
        if f'"{text}"' not in prompt:
            errors.append(f'muse_prompt harus memuat teks persis dalam tanda kutip: "{text}"')
    allowed = {code.upper() for code in brand_data["visual"]["allowed_hex"]}
    extra = {code.upper() for code in re.findall(r"#[0-9A-Fa-f]{6}", prompt)} - allowed
    if extra:
        errors.append("warna di luar palet: " + ", ".join(sorted(extra)))
    scanned = (prompt + " " + str(content.get("caption") or "")).lower()
    banned = [claim for claim in brand_data.get("avoid_claims", []) if re.search(r"(?<!\w)" + re.escape(claim.lower()) + r"(?!\w)", scanned)]
    if banned:
        errors.append("klaim terlarang: " + ", ".join(banned))
    if fmt == "feed":
        caption = str(content.get("caption") or "")
        hashtags = list(content.get("hashtags") or [])
        if not caption.strip() or len(caption) > CAPTION_LIMIT:
            errors.append(f"caption harus terisi dan maksimal {CAPTION_LIMIT} karakter")
        if not HASHTAG_RANGE[0] <= len(hashtags) <= HASHTAG_RANGE[1] or not all(re.fullmatch(r"#[A-Za-z0-9_]+", tag) for tag in hashtags):
            errors.append(f"hashtags harus {HASHTAG_RANGE[0]}-{HASHTAG_RANGE[1]} tag berformat #kata")
        if not all(tag in caption for tag in hashtags):
            errors.append("semua hashtag harus tertulis di akhir caption")
        if brand_data.get("cta_required", True) and "link di bio" not in caption.lower() and brand_data["website"] not in caption:
            errors.append("caption harus mengarahkan ke link di bio atau situs")
        allowed_prices = {_normalize_price(text) for text in brand_data.get("allowed_price_texts", [])}
        for price in re.findall(r"Rp\s?[\d.]+", caption):
            if _normalize_price(price) not in allowed_prices:
                errors.append(f"harga tidak ada di data: {price}")
    return errors


def _trim_brand(brand_data: Mapping[str, Any]) -> dict[str, Any]:
    keep = ("name", "website", "one_liner", "audience", "problems_solved", "features", "mentors", "plans", "privacy_facts", "tone",
            "cta", "content_pillars", "avoid_claims", "hashtags", "allowed_price_texts")
    return {key: brand_data[key] for key in keep if key in brand_data}


def build_messages(brand_data: Mapping[str, Any], style: Mapping[str, Any], post: Mapping[str, Any], fmt: str, example_prompt: str,
                   cta_label: str, footer: str, feedback: Optional[list[str]] = None) -> list[dict[str, str]]:
    slots = style["slots"]
    system = (
        f"Kamu penulis prompt gambar dan caption Instagram untuk brand {brand_data['name']}. Balas HANYA dengan satu objek JSON valid, "
        'tanpa teks lain, dengan kunci: "headline", "subline", "chips", "muse_prompt", "caption", "hashtags".\n'
        "Aturan:\n"
        "- Semua teks yang tampil di gambar (headline, subline, chips, tombol, footer) berbahasa Indonesia, pendek, dan memakai fakta dari data brand saja.\n"
        f"- headline maksimal {slots['headline']['max_words']} kata dan {slots['headline']['max_chars']} karakter, huruf kapital.\n"
        "- muse_prompt berbahasa Inggris, satu paragraf, mengikuti struktur dan gaya contoh prompt, tetapi adegan, subjek, dan diksinya "
        "kamu sesuaikan dengan topik. Setiap teks gambar ditulis persis dalam tanda kutip ganda dengan kata 'reads exactly'. "
        "Sebut rasio gambar dan pertahankan kalimat aturan margin dari contoh prompt apa adanya. Pakai hanya kode warna dari contoh prompt. Tegaskan bahwa teks itu satu-satunya teks di gambar.\n"
        "- caption berbahasa Indonesia, nada sesuai data tone, maksimal 1500 karakter: pembuka yang menarik, satu manfaat konkret dari data, "
        "ajakan 'Klik link di bio', lalu semua hashtag di baris terakhir. Untuk story, caption dikosongkan.\n"
        "- hashtags: 5 sampai 10 tag berformat #kata, ikuti hashtag brand dan topik di data, boleh menambah yang relevan.\n"
        "- Harga hanya dari allowed_price_texts atau tidak disebut. Jangan pernah memakai atau menyiratkan avoid_claims. Jangan mengarang fitur, angka, atau janji."
    )
    user = {
        "brand": _trim_brand(brand_data),
        "post": dict(post),
        "format": fmt,
        "style": {"concept": style["concept"], "text_color": style["text_color"], "slots": slots, "subject_hint": style["subject_hint"]},
        "contoh_prompt": example_prompt,
        "teks_tetap": {"tombol": cta_label if slots.get("cta") else None, "footer": footer},
    }
    if feedback:
        user["perbaiki"] = feedback
    return [{"role": "system", "content": system}, {"role": "user", "content": json.dumps(user, ensure_ascii=False)}]


def _post_json(url: str, headers: Mapping[str, str], body: Mapping[str, Any], timeout: float = 120.0) -> dict[str, Any]:
    request = urllib.request.Request(url, data=json.dumps(body).encode("utf-8"), method="POST",
                                     headers={**headers, "Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as err:
        raise WriterError(f"LLM HTTP {err.code}: {err.read().decode('utf-8', errors='replace')[:300]}") from err
    except (urllib.error.URLError, TimeoutError, ValueError) as err:
        raise WriterError(f"panggilan LLM gagal: {err}") from err


def chat_json(*, base_url: str, api_key: str, model: str, messages: list[dict[str, str]],
              transport: Optional[Callable[..., Any]] = None) -> dict[str, Any]:
    payload = (transport or _post_json)(
        f"{base_url.rstrip('/')}/chat/completions", {"Authorization": f"Bearer {api_key}"} if api_key else {},
        {"model": model, "messages": messages, "temperature": 0.8, "max_tokens": 4096},  # model dengan reasoning memakai token untuk berpikir
    )
    try:
        text = payload["choices"][0]["message"]["content"]
    except (KeyError, IndexError, TypeError) as err:
        raise WriterError("respons LLM tidak berisi pesan") from err
    start, end = text.find("{"), text.rfind("}")
    if start < 0 or end <= start:
        raise WriterError("respons LLM bukan JSON")
    try:
        return json.loads(text[start:end + 1])
    except ValueError as err:
        raise WriterError("JSON dari LLM rusak") from err


def write_content(brand_slug: str, source: Mapping[str, Any], post: Mapping[str, Any], template_id: str, fmt: str, subject: str, *,
                  cta_label: str, footer: str, seed: int = 0, llm: Optional[Mapping[str, Any]] = None,
                  brand_data: Optional[Mapping[str, Any]] = None, styles: Optional[Mapping[str, Any]] = None) -> dict[str, Any]:
    """Prompt Muse, caption, dan hashtag untuk satu postingan.

    `source` berisi headline, subheadline, items untuk teks gambar. `post` adalah ringkasan (topik, analisis) untuk model teks.
    `llm` berisi base_url, api_key, model, dan opsional transport, atau daftar endpoint (utama lebih dulu, lalu cadangan).
    Tanpa `llm`, atau bila semua endpoint gagal, dipakai cadangan dari kartu gaya.
    """
    brand_data = brand_data or load_brand_data(brand_slug)
    style = (styles or load_styles())[template_id]
    copy = derive_copy(source, style)
    use_cta = cta_label if style["slots"].get("cta") and cta_label else None
    prompt = deterministic_prompt(brand_data, style, copy, fmt, subject, cta_label, footer)
    caption, hashtags = deterministic_caption(brand_data, source, seed) if fmt == "feed" else ("", [])
    result = {
        "source": "template", "muse_prompt": prompt, "caption": caption, "hashtags": hashtags,
        "copy": {**copy, "cta_label": use_cta, "footer": footer}, "errors": [],
    }
    endpoints = [llm] if isinstance(llm, Mapping) else list(llm or [])
    result["model"] = None
    for endpoint in endpoints:
        feedback: list[str] = []
        for attempt in range(2):
            try:
                content = chat_json(
                    base_url=endpoint["base_url"], api_key=endpoint.get("api_key", ""), model=endpoint["model"],
                    transport=endpoint.get("transport"),
                    messages=build_messages(brand_data, style, post, fmt, prompt, cta_label, footer, feedback or None),
                )
            except WriterError as err:
                result["errors"].append(f"{endpoint['model']}: {err}")  # gangguan endpoint tidak diulang, lanjut ke endpoint berikutnya
                break
            if fmt == "story":
                content["caption"], content["hashtags"] = "", []
            feedback = validate_content(content, brand_data, style, fmt, cta_label, footer)
            if not feedback:
                result.update({
                    "source": "llm", "model": endpoint["model"], "muse_prompt": content["muse_prompt"],
                    "caption": str(content.get("caption") or ""), "hashtags": list(content.get("hashtags") or []),
                    "copy": {"headline": content["headline"], "subline": content.get("subline") or None,
                             "chips": list(content.get("chips") or []), "cta_label": use_cta, "footer": footer},
                })
                return result
            result["errors"] += [f"{endpoint['model']} percobaan {attempt + 1}: {item}" for item in feedback]
    return result
