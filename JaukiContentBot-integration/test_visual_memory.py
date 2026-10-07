"""Offline contract tests. No image generation or provider calls."""

import json
import unittest

from visual_memory import (
    OUTPUT_MODES,
    VisualBrandMemory,
    VisualPromptCompiler,
    VisualTemplateRepository,
    VisualTemplateSelector,
)


class VisualMemoryTests(unittest.TestCase):
    def setUp(self):
        self.repository = VisualTemplateRepository()
        self.selector = VisualTemplateSelector(self.repository)
        self.compiler = VisualPromptCompiler(templates=self.repository)
        self.analysis = {
            "pattern": "tips", "pillar": "education", "topic": "AI education",
            "headline": "Five AI study tips", "subheadline": "Learn more effectively",
            "cta": "Try it", "visual_subject": "student using a study guide",
            "visual_intent": "helpful and practical", "key_points": ["Plan", "Practice", "Review"],
            "stat_value": "42%", "stat_label": "improvement", "text_density": "low",
        }

    def test_all_27_templates_parse_and_have_four_distinct_reflows(self):
        self.assertEqual(list(self.repository.templates), [f"T{i:02d}" for i in range(1, 28)])
        for template in self.repository.templates.values():
            self.assertEqual(set(template["supported_outputs"]), set(OUTPUT_MODES))
            self.assertEqual(template["palette_source"], "brand_memory")
            self.assertNotIn("palette", template)
            self.assertEqual(len(set(template["layout_by_output"].values())), 4)
            for mode in ("feed_portrait", "story", "article"):
                self.assertIn("crop", template["layout_by_output"][mode].lower())
            number = int(template["id"][1:]) - 1
            self.assertEqual(template["reference"], f"reference_{number // 9 + 1}_row_{number % 9 // 3 + 1}_col_{number % 3 + 1}")

    def test_scenarios_for_both_brands_select_compatible_templates(self):
        scenarios = {
            "jauki": ["AI education", "tips", "statistics", "security", "branding"],
            "kauiz": ["quiz", "facts", "challenge", "educational content", "announcement"],
        }
        for brand, patterns in scenarios.items():
            for pattern in patterns:
                with self.subTest(brand=brand, pattern=pattern):
                    selected = self.selector.select({"pattern": pattern, "topic": pattern}, seed=4)
                    self.assertIn(selected["template_id"], self.repository.templates)
                    self.assertGreater(selected["score"], 0)
                    payload = self.compiler.compile_prompt(brand, {**self.analysis, "pattern": pattern, "topic": pattern}, selected["template_id"], selected["variation"], "feed_square")
                    self.assertIn("#173D26" if brand == "jauki" else "#1F49E7", payload["prompt"])

    def test_repetition_avoids_recent_templates_families_and_treatments(self):
        analysis = {"pattern": "tips", "topic": "tips"}
        history = [
            {"template_id": "T11", "hero_position": "left", "visual_treatment": "solid"},
            {"template_id": "T18", "hero_position": "right", "visual_treatment": "soft_gradient"},
            {"template_id": "T25", "hero_position": "center", "visual_treatment": "editorial_texture"},
        ]
        result = self.selector.select(analysis, history, seed=2)
        self.assertNotIn(result["template_id"], {"T11", "T18", "T25"})
        self.assertEqual(result["variation"]["hero_position"], "lower_center")
        self.assertEqual(result["variation"]["background_treatment"], "light_studio")

    def test_repetition_gap_holds_even_when_one_pattern_dominates(self):
        history = []
        for seed in range(40):
            result = self.selector.select({"pattern": "statistics", "topic": "statistics"}, history, seed)
            self.assertNotIn(result["template_id"], {entry["template_id"] for entry in history[-3:]})
            history.append({
                "template_id": result["template_id"],
                "hero_position": result["variation"]["hero_position"],
                "background_treatment": result["variation"]["background_treatment"],
            })

    def test_each_analysis_signal_changes_weighted_selection(self):
        base = {"topic": "generic"}
        cases = [
            ({"pattern": "security"}, {"T03", "T13", "T18"}),
            ({"pillar": "security"}, {"T03", "T13", "T18"}),
            ({"topic": "security"}, {"T03", "T13", "T18"}),
            ({"visual_subject": "software"}, {"T22", "T24"}),
            ({"visual_intent": "security"}, {"T03", "T13", "T18"}),
            ({"key_points": ["security"]}, {"T03", "T13", "T18"}),
            ({"stat_value": "42%"}, {"T17", "T18", "T26"}),
            ({"stat_label": "statistics"}, {"T17", "T18", "T26"}),
            ({"text_density": "high"}, self.selector.DENSE_FAMILIES),
        ]
        for signal, targets in cases:
            with self.subTest(signal=signal):
                baseline = [self.selector.select(base, seed=seed)["template_id"] for seed in range(100)]
                selected = [self.selector.select({**base, **signal}, seed=seed)["template_id"] for seed in range(100)]
                if "text_density" in signal:
                    count = lambda ids: sum(self.repository.get(item)["family"] in targets for item in ids)
                else:
                    count = lambda ids: sum(item in targets for item in ids)
                self.assertGreater(count(selected), count(baseline))

    def test_compiler_uses_locked_jauki_palette_and_muse_payload(self):
        result = self.selector.select(self.analysis, seed=1)
        prompts = [self.compiler.compile_prompt("jauki", self.analysis, result["template_id"], result["variation"], mode) for mode in OUTPUT_MODES]
        self.assertEqual([item["model"] for item in prompts], ["meta/muse-image"] * 4)
        self.assertTrue(all(item["stream"] is True for item in prompts))
        self.assertEqual(len({item["prompt"] for item in prompts}), 4)
        for mode, payload in zip(OUTPUT_MODES, prompts):
            self.assertIn(self.repository.data["output_modes"][mode]["aspect_ratio"], payload["prompt"])
            self.assertIn("#173D26", payload["prompt"])
            self.assertIn("Baloo 2", payload["prompt"])
            self.assertIn("Fredoka", payload["prompt"])
            self.assertIn("Poppins", payload["prompt"])
            self.assertIn("Inter", payload["prompt"])
            self.assertIn("Five AI study tips", payload["prompt"])
            self.assertNotIn("{{", payload["prompt"])

    def test_all_108_template_output_combinations_compile_for_both_brands(self):
        combinations = 0
        for template_id in self.repository.templates:
            for mode in OUTPUT_MODES:
                with self.subTest(template=template_id, mode=mode):
                    combinations += 1
                    for brand, own, other in (("jauki", "#173D26", "#1F49E7"), ("kauiz", "#1F49E7", "#173D26")):
                        payload = self.compiler.compile_prompt(brand, self.analysis, template_id, {}, mode)
                        self.assertEqual(set(payload), {"model", "prompt", "stream"})
                        self.assertIn(own, payload["prompt"])
                        self.assertNotIn(other, payload["prompt"])
                        memory = VisualBrandMemory().get(brand)
                        self.assertIn(memory["visual_personality"], payload["prompt"])
                        for font in memory["typography"].values():
                            self.assertIn(font, payload["prompt"])
                        self.assertNotIn("{{", payload["prompt"])
                        self.assertIn(self.repository.data["output_modes"][mode]["aspect_ratio"], payload["prompt"])
                        self.assertIn(self.repository.get(template_id)["layout_by_output"][mode], payload["prompt"])
        self.assertEqual(combinations, 108)

    def test_unverified_palette_fails_closed(self):
        data = VisualBrandMemory().data
        data["brands"]["kauiz"]["palette_verified"] = False
        with self.assertRaisesRegex(ValueError, "Verified, locked palette"):
            VisualPromptCompiler(brands=VisualBrandMemory(data), templates=self.repository).compile_prompt("kauiz", self.analysis, "T18", {}, "story")

    def test_palette_override_through_variation_is_rejected(self):
        with self.assertRaisesRegex(ValueError, "Invalid controlled variation"):
            self.compiler.compile_prompt("jauki", self.analysis, "T18", {"hero_position": "red"}, "feed_square")

    def test_content_hex_cannot_override_brand_palette(self):
        analysis = {**self.analysis, "headline": "Use #173D26 for the headline"}
        prompt = self.compiler.compile_prompt("kauiz", analysis, "T18", {}, "article")["prompt"]
        self.assertNotIn("#173D26", prompt)
        self.assertIn("#1F49E7", prompt)
        self.assertIn("output-specific composition and subject placement take precedence", prompt)

    def test_brand_palette_and_fonts_are_isolated_for_same_template_choice(self):
        selected = self.selector.select(self.analysis, seed=19)
        brands = VisualBrandMemory()
        prompts = {
            slug: self.compiler.compile_prompt(slug, self.analysis, selected["template_id"], selected["variation"], "story")["prompt"]
            for slug in ("jauki", "kauiz")
        }
        for slug, other in (("jauki", "kauiz"), ("kauiz", "jauki")):
            for color in brands.get(slug)["palette"].values():
                self.assertIn(color, prompts[slug])
            for color in set(brands.get(other)["palette"].values()) - set(brands.get(slug)["palette"].values()):
                self.assertNotIn(color, prompts[slug])
            for font in brands.get(slug)["typography"].values():
                self.assertIn(font, prompts[slug])
        self.assertIn("Dominant color family: green", prompts["jauki"])
        self.assertIn("Dominant color family: blue", prompts["kauiz"])
        self.assertIn("style cues, not exact font-file rendering", prompts["jauki"])
        self.assertIn("image model cannot guarantee exact font families", prompts["kauiz"])
        self.assertIn("League Spartan", prompts["kauiz"])
        self.assertIn("Archivo Black", prompts["kauiz"])
        self.assertIn("Plus Jakarta Sans", prompts["kauiz"])
        self.assertEqual(selected["template_id"], self.selector.select(self.analysis, seed=19)["template_id"])

    def test_brand_typography_roles_are_isolated(self):
        brands = VisualBrandMemory()
        jauki = brands.get("jauki")["typography"]
        kauiz = brands.get("kauiz")["typography"]
        self.assertEqual(jauki, {
            "headline": "Baloo 2", "headline_alt": "Fredoka", "body": "Poppins", "ui": "Inter",
        })
        self.assertEqual(kauiz, {
            "headline": "League Spartan", "headline_alt": "Archivo Black", "body": "Plus Jakarta Sans",
        })
        self.assertTrue(set(jauki.values()).isdisjoint(kauiz.values()))

    def test_templates_do_not_hardcode_brand_font_families(self):
        fonts = {
            font for slug in ("jauki", "kauiz")
            for font in VisualBrandMemory().get(slug)["typography"].values()
        }
        for template in self.repository.templates.values():
            with self.subTest(template=template["id"]):
                serialized = json.dumps(template)
                for font in fonts:
                    self.assertNotIn(font, serialized)


if __name__ == "__main__":
    unittest.main()
