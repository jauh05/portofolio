"""Tes offline untuk visual_overlay dan template-layouts.json. Tidak ada panggilan jaringan."""

import json
import tempfile
import unittest
from itertools import combinations
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw

import visual_overlay as overlay
from visual_memory import MEMORY_DIR, VisualBrandMemory, VisualPromptCompiler, clear_zone_sentence

MODES = ("feed_portrait", "story")
TEXT_ZONES = ("headline", "subline", "stat", "badge", "cta", "footer")
SAMPLE = {**overlay.SAMPLE_COPY, "items": ["Tulis dari ingatan", "Cek catatan", "Ulangi besok", "Tidur cukup"]}


class LayoutSpecTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.layouts = overlay.load_layouts()["templates"]

    def test_all_27_templates_have_both_modes_with_headline_and_footer(self):
        self.assertEqual(sorted(self.layouts), [f"T{i:02d}" for i in range(1, 28)])
        for template_id, entry in self.layouts.items():
            for mode in MODES:
                zones = entry[mode]["zones"]
                self.assertIn("headline", zones, (template_id, mode))
                self.assertIn("footer", zones, (template_id, mode))

    def test_every_zone_and_panel_stays_inside_the_canvas(self):
        for template_id, entry in self.layouts.items():
            for mode in MODES:
                boxes = [zone["box"] for zone in entry[mode]["zones"].values()] + [decor["box"] for decor in entry[mode]["decor"]]
                for x, y, w, h in boxes:
                    self.assertTrue(0 <= x and 0 <= y and w > 0 and h > 0 and x + w <= 1.0001 and y + h <= 1.0001, (template_id, mode, (x, y, w, h)))

    def test_text_zones_do_not_overlap_each_other(self):
        for template_id, entry in self.layouts.items():
            for mode in MODES:
                zones = {name: zone["box"] for name, zone in entry[mode]["zones"].items() if name in TEXT_ZONES}
                for (a, (ax, ay, aw, ah)), (b, (bx, by, bw, bh)) in combinations(zones.items(), 2):
                    separate = ax + aw <= bx or bx + bw <= ax or ay + ah <= by or by + bh <= ay
                    self.assertTrue(separate, (template_id, mode, a, b))

    def test_story_text_stays_inside_the_instagram_safe_area(self):
        for template_id, entry in self.layouts.items():
            for name, zone in entry["story"]["zones"].items():
                x, y, w, h = zone["box"]
                self.assertGreaterEqual(y, 0.13, (template_id, name))
                self.assertLessEqual(y + h, 0.88 + 0.0001, (template_id, name))

    def test_templates_use_more_than_one_layout_shape(self):
        shapes = {json.dumps(entry["feed_portrait"], sort_keys=True) for entry in self.layouts.values()}
        self.assertGreaterEqual(len(shapes), 20)


class RenderTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.layouts = overlay.load_layouts()["templates"]
        cls.brands = {slug: VisualBrandMemory().get(slug) for slug in ("kauiz", "jauki")}

    def allowed_mask(self, layout, size):
        mask = Image.new("L", size, 0)
        draw = ImageDraw.Draw(mask)
        pad = round(0.04 * size[0])
        for box in [zone["box"] for zone in layout["zones"].values()] + [decor["box"] for decor in layout["decor"]]:
            left, top, right, bottom = overlay._box_px(box, size)
            draw.rectangle((left - pad, top - pad, right + pad, bottom + pad), fill=255)
        return mask

    def test_every_template_brand_and_mode_renders_inside_its_zones(self):
        for slug, brand in self.brands.items():
            for template_id, entry in self.layouts.items():
                for mode in MODES:
                    with self.subTest(brand=slug, template=template_id, mode=mode):
                        base = Image.new("RGB", overlay.CANVAS[mode], overlay.color_of(brand, "paper"))
                        result = overlay.render_overlay(base.copy(), entry[mode], brand, SAMPLE, mode=mode,
                                                        variation={"headline_alignment": "center"})
                        self.assertEqual(result.size, overlay.CANVAS[mode])
                        diff = ImageChops.difference(base, result).convert("L").point(lambda value: 255 if value else 0)
                        outside = ImageChops.multiply(diff, ImageChops.invert(self.allowed_mask(entry[mode], base.size)))
                        self.assertIsNone(outside.getbbox(), "teks keluar dari zonanya")

    def test_long_text_is_fitted_or_truncated_instead_of_overflowing(self):
        brand = self.brands["kauiz"]
        copy = {**SAMPLE, "headline": "judul yang sangat panjang sekali " * 8, "subheadline": "kalimat pendukung panjang " * 30}
        for template_id in ("T01", "T11", "T20"):
            for mode in MODES:
                base = Image.new("RGB", overlay.CANVAS[mode], overlay.color_of(brand, "paper"))
                overlay.render_overlay(base, self.layouts[template_id][mode], brand, copy, mode=mode)

    def test_text_color_follows_background_brightness(self):
        brand = self.brands["kauiz"]
        box = (0, 0, 200, 100)
        dark = Image.new("RGB", (400, 200), overlay.color_of(brand, "ink"))
        light = Image.new("RGB", (400, 200), overlay.color_of(brand, "paper"))
        zone = {"color": "auto"}
        self.assertEqual(overlay._text_color(dark, brand, zone, box)[0], overlay.color_of(brand, "light"))
        self.assertEqual(overlay._text_color(light, brand, zone, box)[0], overlay.color_of(brand, "ink"))

    def test_busy_background_gets_a_panel_behind_text(self):
        brand = self.brands["kauiz"]
        busy = Image.new("RGB", (400, 200), "white")
        draw = ImageDraw.Draw(busy)
        for x in range(0, 400, 20):
            draw.rectangle((x, 0, x + 10, 200), fill=overlay.color_of(brand, "ink"))
        self.assertTrue(overlay._text_color(busy, brand, {"color": "ink"}, (0, 0, 400, 200))[1])
        self.assertFalse(overlay._text_color(busy, brand, {"color": "ink", "scrim": "never"}, (0, 0, 400, 200))[1])

    def test_wrong_canvas_size_and_missing_font_fail_loudly(self):
        brand = self.brands["kauiz"]
        layout = self.layouts["T01"]["feed_portrait"]
        with self.assertRaises(overlay.OverlayError):
            overlay.render_overlay(Image.new("RGB", (100, 100)), layout, brand, SAMPLE, mode="feed_portrait")
        with tempfile.TemporaryDirectory() as tmp, self.assertRaises(overlay.OverlayError):
            overlay.render_overlay(Image.new("RGB", overlay.CANVAS["feed_portrait"]), layout, brand, SAMPLE,
                                   mode="feed_portrait", fonts_dir=Path(tmp))

    def test_brand_fonts_are_the_files_listed_in_brands_json(self):
        for brand in self.brands.values():
            for spec in brand["font_files"].values():
                self.assertTrue((MEMORY_DIR / "fonts" / spec["file"]).is_file(), spec["file"])


class PromptZoneTests(unittest.TestCase):
    def test_prompt_reserves_the_text_zones_for_every_template_brand_and_mode(self):
        compiler = VisualPromptCompiler()
        analysis = {"topic": "Active recall", "pattern": "tips", "visual_subject": "an open notebook", "visual_intent": "focused"}
        for brand in ("kauiz", "jauki"):
            for template_id in compiler.layouts["templates"]:
                for mode in MODES:
                    prompt = compiler.compile_prompt(brand, analysis, template_id, {}, mode)["prompt"]
                    self.assertIn("Reserved areas for text added later", prompt, (brand, template_id, mode))
                    self.assertNotIn("{{", prompt)

    def test_clear_zone_sentence_describes_bands_and_covered_cards(self):
        layouts = overlay.load_layouts()["templates"]
        sentence = clear_zone_sentence(layouts["T11"]["feed_portrait"])
        self.assertIn("full-width band", sentence)
        self.assertIn("A flat card will cover", sentence)


if __name__ == "__main__":
    unittest.main()
