"""Offline contract tests. No image generation or provider calls."""

import copy
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
            self.assertEqual(len(set(template["layout_by_output"].values())), 4)
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
                    if brand == "jauki":
                        payload = self.compiler.compile_prompt(brand, {**self.analysis, "pattern": pattern, "topic": pattern}, selected["template_id"], selected["variation"], "feed_square")
                        self.assertIn("#4834d4", payload["prompt"])

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

    def test_compiler_uses_locked_jauki_palette_and_muse_payload(self):
        result = self.selector.select(self.analysis, seed=1)
        prompts = [self.compiler.compile_prompt("jauki", self.analysis, result["template_id"], result["variation"], mode) for mode in OUTPUT_MODES]
        self.assertEqual([item["model"] for item in prompts], ["meta/muse-image"] * 4)
        self.assertTrue(all(item["stream"] is True for item in prompts))
        self.assertEqual(len({item["prompt"] for item in prompts}), 4)
        for mode, payload in zip(OUTPUT_MODES, prompts):
            self.assertIn(self.repository.data["output_modes"][mode]["aspect_ratio"], payload["prompt"])
            self.assertIn("#4834d4", payload["prompt"])
            self.assertIn("Five AI study tips", payload["prompt"])
            self.assertNotIn("{{", payload["prompt"])

    def test_every_template_compiles_in_every_output_mode(self):
        for template_id in self.repository.templates:
            for mode in OUTPUT_MODES:
                with self.subTest(template=template_id, mode=mode):
                    payload = self.compiler.compile_prompt("jauki", self.analysis, template_id, {}, mode)
                    self.assertIn("#4834d4", payload["prompt"])
                    self.assertNotIn("{{", payload["prompt"])

    def test_unverified_kauiz_fails_closed_and_fixture_palettes_stay_isolated(self):
        with self.assertRaisesRegex(ValueError, "Verified, locked palette"):
            self.compiler.compile_prompt("kauiz", self.analysis, "T18", {}, "story")
        data = copy.deepcopy(VisualBrandMemory().data)
        data["brands"]["kauiz"].update({
            "palette_verified": True, "palette_source": "test fixture", "palette": {"primary": "#ab1200", "ink": "#101010"},
            "visual_personality": "Quiz focused", "typography": "Clear sans", "series_label": "Quiz",
        })
        fixture_compiler = VisualPromptCompiler(brands=VisualBrandMemory(data), templates=self.repository)
        kauiz = fixture_compiler.compile_prompt("kauiz", self.analysis, "T18", {}, "story")["prompt"]
        jauki = fixture_compiler.compile_prompt("jauki", self.analysis, "T18", {}, "story")["prompt"]
        self.assertIn("#ab1200", kauiz)
        self.assertNotIn("#4834d4", kauiz)
        self.assertIn("#4834d4", jauki)
        self.assertNotIn("#ab1200", jauki)
        for pattern in ("quiz", "facts", "challenge", "educational content", "announcement"):
            scenario = {**self.analysis, "pattern": pattern, "topic": pattern}
            selected = self.selector.select(scenario, seed=4)
            payload = fixture_compiler.compile_prompt("kauiz", scenario, selected["template_id"], selected["variation"], "feed_portrait")
            self.assertIn("#ab1200", payload["prompt"])
            self.assertNotIn("#4834d4", payload["prompt"])

    def test_palette_override_through_variation_is_rejected(self):
        with self.assertRaisesRegex(ValueError, "Invalid controlled variation"):
            self.compiler.compile_prompt("jauki", self.analysis, "T18", {"hero_position": "red"}, "feed_square")


if __name__ == "__main__":
    unittest.main()
