"""Overlay teks per template untuk gambar Muse.

Muse hanya membuat latar tanpa teks. Modul ini menulis headline, subline, poin, CTA, dan footer di atas latar itu
sesuai zona di template-layouts.json, memakai font brand dari folder fonts/. Teks tidak pernah keluar dari zonanya.
Modul ini tidak memanggil jaringan dan dipakai bersama oleh worker Kauiz dan preview Jauki.

    python3 visual_overlay.py --brand kauiz --template T11 --mode feed_portrait --out contoh.jpg
    python3 visual_overlay.py --brand jauki --mode story --sheet kontak.jpg
"""

from __future__ import annotations

import argparse
import json
import random
from functools import lru_cache
from pathlib import Path
from typing import Any, Mapping, Optional

from PIL import Image, ImageDraw, ImageFont, ImageStat

from visual_memory import MEMORY_DIR, clear_zone_sentence  # noqa: F401  (clear_zone_sentence diekspor ulang)

CANVAS = {"feed_portrait": (1080, 1350), "feed_square": (1080, 1080), "story": (1080, 1920)}
MIN_CONTRAST = 4.0
BUSY_STDDEV = 48.0

SAMPLE_COPY = {
    "headline": "Tutup catatan, lalu ingat lagi",
    "subheadline": "Latihan mengingat tanpa membuka catatan.",
    "items": ["Tulis dari ingatan", "Cek catatan", "Ulangi besok"],
    "stat": "7 hari",
}


class OverlayError(RuntimeError):
    pass


def load_layouts(path: Optional[Path] = None) -> dict[str, Any]:
    with (path or MEMORY_DIR / "template-layouts.json").open(encoding="utf-8") as source:
        return json.load(source)


def _rgb(code: str) -> tuple[int, int, int]:
    code = code.lstrip("#")
    return tuple(int(code[i:i + 2], 16) for i in (0, 2, 4))  # type: ignore[return-value]


def _luminance(rgb: tuple[int, int, int]) -> float:
    def channel(value: int) -> float:
        value /= 255
        return value / 12.92 if value <= 0.03928 else ((value + 0.055) / 1.055) ** 2.4
    r, g, b = (channel(value) for value in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def contrast_ratio(a: tuple[int, int, int], b: tuple[int, int, int]) -> float:
    high, low = sorted((_luminance(a), _luminance(b)), reverse=True)
    return (high + 0.05) / (low + 0.05)


def color_of(brand: Mapping[str, Any], token: str) -> tuple[int, int, int]:
    key = brand.get("roles", {}).get(token, token)
    try:
        return _rgb(brand["palette"][key])
    except KeyError as error:
        raise OverlayError(f"Unknown color token '{token}' for brand {brand.get('name')}") from error


def _axis_name(axis: Mapping[str, Any]) -> str:
    name = axis["name"]
    return (name.decode("utf-8", "ignore") if isinstance(name, bytes) else str(name)).lower()


@lru_cache(maxsize=512)
def _font(path: str, size: int, weight: Optional[int]) -> ImageFont.FreeTypeFont:
    try:
        font = ImageFont.truetype(path, size)
    except OSError as error:
        raise OverlayError(f"Font file not found or unreadable: {path}") from error
    if weight is not None:
        try:
            values = [weight if _axis_name(axis) in ("weight", "wght") else axis["default"] for axis in font.get_variation_axes()]
            font.set_variation_by_axes(values)
        except (OSError, AttributeError):
            pass  # font statis: bobot tetap dari file
    return font


def load_font(brand: Mapping[str, Any], role: str, size: int, fonts_dir: Path) -> ImageFont.FreeTypeFont:
    files = brand.get("font_files") or {}
    spec = files.get(role) or files.get("body")
    if not spec:
        raise OverlayError(f"Brand {brand.get('name')} has no font_files for role {role}")
    return _font(str(Path(fonts_dir) / spec["file"]), int(size), spec.get("weight"))


def _wrap(draw: ImageDraw.ImageDraw, text: str, font: ImageFont.FreeTypeFont, max_width: float) -> list[str]:
    lines: list[str] = []
    current = ""
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


def fit_text(draw, text, brand, role, box_w, box_h, size_min, size_max, max_lines, line_height, fonts_dir):
    """Ukuran font terbesar yang membuat teks muat di kotak. Bila ukuran terkecil pun tidak muat, teks dipotong dengan elipsis."""
    text = " ".join(str(text).split())
    if not text:
        raise OverlayError("Empty text for zone")

    def attempt(size: int, value: str):
        font = load_font(brand, role, size, fonts_dir)
        lines = _wrap(draw, value, font, box_w)
        ok = (
            len(lines) <= max_lines
            and all(draw.textlength(line, font=font) <= box_w for line in lines)
            and len(lines) * size * line_height <= box_h
        )
        return ok, font, lines

    size_min, size_max = int(size_min), max(int(size_min), int(size_max))
    ok, font, lines = attempt(size_min, text)
    if not ok:
        words = text.split()
        while words:
            words.pop()
            ok, font, lines = attempt(size_min, " ".join(words) + "…")
            if ok:
                return font, lines, size_min
        raise OverlayError("Text cannot fit its zone even at the minimum size")
    low, high = size_min, size_max
    while low < high:
        mid = (low + high + 1) // 2
        if attempt(mid, text)[0]:
            low = mid
        else:
            high = mid - 1
    _, font, lines = attempt(low, text)
    return font, lines, low


def _box_px(box, size):
    width, height = size
    x, y, w, h = box
    return (round(x * width), round(y * height), round((x + w) * width), round((y + h) * height))


def _stats(image: Image.Image, box):
    crop = image.crop(box).convert("RGB")
    mean = crop.resize((1, 1), Image.BOX).getpixel((0, 0))
    return mean, max(ImageStat.Stat(crop).stddev)


def _panel(image: Image.Image, box, fill, alpha: float, radius: int) -> None:
    left, top, right, bottom = box
    left, top = max(0, left), max(0, top)
    right, bottom = min(image.width, right), min(image.height, bottom)
    if right <= left or bottom <= top:
        return
    mask = Image.new("L", (right - left, bottom - top), 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, right - left - 1, bottom - top - 1), radius=radius, fill=round(255 * alpha))
    image.paste(Image.new("RGB", mask.size, fill), (left, top), mask)


def _has(copy: Mapping[str, Any], key: Optional[str]) -> bool:
    return not key or bool(copy.get(key))


def _text_color(image, brand, zone, box):
    token = zone.get("color", "ink")
    mean, deviation = _stats(image, box)
    if token == "auto":
        candidates = [color_of(brand, "ink"), color_of(brand, "light")]
        color = max(candidates, key=lambda candidate: contrast_ratio(candidate, mean))
    else:
        color = color_of(brand, token)
    low = contrast_ratio(color, mean) < MIN_CONTRAST or deviation > BUSY_STDDEV
    return color, low and zone.get("scrim", "auto") != "never"


def _draw_block(image, draw, brand, zone, text, fonts_dir, variation):
    box = _box_px(zone["box"], image.size)
    width = image.width
    box_w, box_h = box[2] - box[0], box[3] - box[1]
    if zone.get("upper"):
        text = str(text).upper()
    font, lines, font_size = fit_text(
        draw, text, brand, zone.get("font", "body"), box_w, box_h,
        zone["size"][0] * width, zone["size"][1] * width, zone.get("max_lines", 3), zone.get("lh", 1.12), fonts_dir,
    )
    align = zone.get("align", "left")
    if align == "auto":
        align = (variation or {}).get("headline_alignment", "left")
    line_h = font_size * zone.get("lh", 1.12)
    block_h = line_h * len(lines)
    anchor = zone.get("anchor", "top")
    top = box[1] if anchor == "top" else box[1] + (box_h - block_h) / 2 if anchor == "middle" else box[3] - block_h
    widths = [draw.textlength(line, font=font) for line in lines]
    color, needs_panel = _text_color(image, brand, zone, box)
    if needs_panel:
        pad = round(0.02 * width)
        left_edge = {"left": box[0], "center": (box[0] + box[2] - max(widths)) / 2, "right": box[2] - max(widths)}[align]
        _panel(image, (round(left_edge - pad), round(top - pad), round(left_edge + max(widths) + pad), round(top + block_h + pad)),
               color_of(brand, "paper"), 0.88, round(0.025 * width))
        color = color_of(brand, "ink")
        draw = ImageDraw.Draw(image)
    for index, line in enumerate(lines):
        x = {"left": box[0], "center": (box[0] + box[2] - widths[index]) / 2, "right": box[2] - widths[index]}[align]
        draw.text((x, top + index * line_h), line, font=font, fill=color)


def _draw_pill(image, draw, brand, zone, text, fonts_dir, fill_token, text_token):
    box = _box_px(zone["box"], image.size)
    box_h = box[3] - box[1]
    draw.rounded_rectangle(box, radius=box_h // 2, fill=color_of(brand, fill_token))
    pad = round(box_h * 0.35)
    font, lines, _ = fit_text(
        draw, text, brand, zone.get("font", "body_bold"), box[2] - box[0] - 2 * pad, box_h * 0.7,
        box_h * 0.22, box_h * 0.46, 1, 1.0, fonts_dir,
    )
    draw.text(((box[0] + box[2]) / 2, (box[1] + box[3]) / 2), lines[0], font=font, fill=color_of(brand, text_token), anchor="mm")


def _draw_items(image, draw, brand, zone, items, fonts_dir):
    box = _box_px(zone["box"], image.size)
    rows = items[: zone.get("n", 3)]
    if not rows:
        return
    row_h = (box[3] - box[1]) / len(rows)
    marker = min(row_h * 0.62, image.width * 0.085)
    for index, item in enumerate(rows):
        cy = box[1] + row_h * index + row_h / 2
        draw.ellipse((box[0], cy - marker / 2, box[0] + marker, cy + marker / 2), fill=color_of(brand, zone.get("marker", "accent")))
        number_font = load_font(brand, "body_bold", round(marker * 0.5), fonts_dir)
        draw.text((box[0] + marker / 2, cy), str(index + 1), font=number_font, fill=color_of(brand, "ink"), anchor="mm")
        text_left = box[0] + marker * 1.35
        font, lines, size = fit_text(
            draw, item, brand, zone.get("font", "body_bold"), box[2] - text_left, row_h * 0.9,
            zone["size"][0] * image.width, zone["size"][1] * image.width, zone.get("max_lines", 2), 1.12, fonts_dir,
        )
        top = cy - size * 1.12 * len(lines) / 2
        for line_index, line in enumerate(lines):
            draw.text((text_left, top + line_index * size * 1.12), line, font=font, fill=color_of(brand, zone.get("color", "ink")))


def render_overlay(image: Image.Image, layout: Mapping[str, Any], brand: Mapping[str, Any], copy: Mapping[str, Any], *,
                   mode: str, variation: Optional[Mapping[str, Any]] = None, fonts_dir: Optional[Path] = None) -> Image.Image:
    """Menulis teks ke `image` (RGB, ukuran kanvas mode) sesuai layout, lalu mengembalikan gambar yang sama."""
    if image.size != CANVAS[mode]:
        raise OverlayError(f"Image size {image.size} does not match canvas {CANVAS[mode]} for {mode}")
    fonts_dir = Path(fonts_dir or MEMORY_DIR / "fonts")
    cta = brand.get("cta", {})
    zones = layout["zones"]
    draw = ImageDraw.Draw(image)

    for decor in layout.get("decor", []):
        if _has(copy, decor.get("needs")):
            _panel(image, _box_px(decor["box"], image.size), color_of(brand, decor.get("fill", "card")),
                   decor.get("alpha", 0.92), round(decor.get("radius", 0.03) * image.width))
    draw = ImageDraw.Draw(image)

    for name in ("headline", "subline", "stat"):
        zone = zones.get(name)
        source = {"headline": "headline", "subline": "subheadline", "stat": "stat"}[name]
        if zone and copy.get(source):
            _draw_block(image, draw, brand, zone, copy[source], fonts_dir, variation)
            draw = ImageDraw.Draw(image)
    if zones.get("items") and copy.get("items"):
        _draw_items(image, draw, brand, zones["items"], list(copy["items"]), fonts_dir)
    if zones.get("badge"):
        _draw_pill(image, draw, brand, zones["badge"], copy.get("badge") or cta.get("badge") or brand["name"], fonts_dir,
                   zones["badge"].get("fill", "ink"), zones["badge"].get("text", "light"))
    if zones.get("cta"):
        label = copy.get("cta_label") or cta.get("label_story" if mode == "story" else "label") or "LINK DI BIO"
        _draw_pill(image, draw, brand, zones["cta"], label, fonts_dir, zones["cta"].get("fill", "ink"), zones["cta"].get("text", "light"))
    if zones.get("footer") and cta.get("url"):
        _draw_pill(image, draw, brand, zones["footer"], cta["url"], fonts_dir, zones["footer"].get("fill", "ink"), zones["footer"].get("text", "light"))
    return image


def placeholder_background(brand: Mapping[str, Any], mode: str, seed: int) -> Image.Image:
    rng = random.Random(seed)
    width, height = CANVAS[mode]
    image = Image.new("RGB", (width, height), color_of(brand, "paper"))
    draw = ImageDraw.Draw(image)
    for _ in range(9):
        radius = rng.randint(120, 380)
        x, y = rng.randint(0, width), rng.randint(0, height)
        draw.ellipse((x - radius, y - radius, x + radius, y + radius), fill=color_of(brand, rng.choice(["ink", "ink_alt", "accent", "card"])))
    return image


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description="Render overlay contoh di atas latar placeholder.")
    parser.add_argument("--brand", default="kauiz")
    parser.add_argument("--mode", default="feed_portrait", choices=("feed_portrait", "story"))
    parser.add_argument("--template")
    parser.add_argument("--out")
    parser.add_argument("--sheet")
    args = parser.parse_args(argv)
    brand = json.loads((MEMORY_DIR / "brands.json").read_text(encoding="utf-8"))["brands"][args.brand]
    layouts = load_layouts()["templates"]
    if args.sheet:
        columns = 9
        thumb = (216, 270) if args.mode == "feed_portrait" else (180, 320)
        sheet = Image.new("RGB", (columns * thumb[0], 3 * thumb[1]), "white")
        for index, template_id in enumerate(sorted(layouts)):
            image = render_overlay(placeholder_background(brand, args.mode, index), layouts[template_id][args.mode], brand, SAMPLE_COPY, mode=args.mode)
            sheet.paste(image.resize(thumb, Image.LANCZOS), ((index % columns) * thumb[0], (index // columns) * thumb[1]))
        sheet.save(args.sheet, quality=90)
        return 0
    image = render_overlay(placeholder_background(brand, args.mode, 1), layouts[args.template][args.mode], brand, SAMPLE_COPY, mode=args.mode)
    image.save(args.out or "overlay.jpg", quality=92)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
