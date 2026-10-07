"""The social worker's visual preview must have no provider or publish effects."""

import unittest
from unittest.mock import patch

from office_social_worker import preview_visual_command
from visual_worker_preview import normalize_analysis, output_mode_for, preview_visual_memory


class VisualWorkerPreviewTests(unittest.TestCase):
    def test_jauki_feed_portrait_uses_locked_green_memory(self):
        result = preview_visual_command({
            "brand": "Jauki", "platform": "instagram", "content_type": "feed",
            "output_mode": "feed_portrait", "topic": "Cara Memilih Jurnal yang Tepat",
            "key_findings": [{"title": "Periksa relevansi"}],
        }, seed=7)
        self.assertRegex(result["template_id"], r"^T(0[1-9]|1[0-9]|2[0-7])$")
        self.assertEqual(result["output_mode"], "feed_portrait")
        self.assertEqual(result["palette"]["primary"], "#173D26")
        self.assertEqual(result["typography"]["headline"], "Baloo 2")
        self.assertEqual(result["typography"]["headline_alt"], "Fredoka")
        self.assertEqual(result["typography"]["body"], "Poppins")
        self.assertEqual(result["typography"]["ui"], "Inter")
        self.assertEqual(result["analysis_input"]["key_points"], ["Periksa relevansi"])
        self.assertIn("Cara Memilih Jurnal", result["compiled_prompt"])
        self.assertIn("4:5", result["compiled_prompt"])
        self.assertTrue(result["dry_run"])
        self.assertEqual(result["provider_payload_preview"], {"model": "meta/muse-image", "stream": True})

    def test_kauiz_story_uses_locked_blue_memory(self):
        result = preview_visual_command({
            "brand_slug": "kauiz", "content_type": "story",
            "topic": "Fokus Satu Tugas dalam Satu Waktu", "visual_intent": "motivational",
        }, seed=7)
        self.assertRegex(result["template_id"], r"^T(0[1-9]|1[0-9]|2[0-7])$")
        self.assertEqual(result["output_mode"], "story")
        self.assertEqual(result["palette"]["primary"], "#1F49E7")
        self.assertEqual(result["typography"]["headline"], "League Spartan")
        self.assertEqual(result["typography"]["headline_alt"], "Archivo Black")
        self.assertEqual(result["typography"]["body"], "Plus Jakarta Sans")
        self.assertNotIn("Inter", result["typography"].values())
        self.assertIn("9:16", result["compiled_prompt"])
        self.assertTrue(result["dry_run"])

    def test_output_mode_mapping_and_article_compiler_only(self):
        self.assertEqual(output_mode_for("feed"), "feed_square")
        self.assertEqual(output_mode_for("feed", "feed_portrait"), "feed_portrait")
        self.assertEqual(output_mode_for("story"), "story")
        self.assertEqual(output_mode_for("article"), "article")
        article = preview_visual_memory({"brand": "Jauki", "content_type": "article", "topic": "Jurnal"}, seed=1)
        self.assertIn("16:9", article["compiled_prompt"])
        with self.assertRaisesRegex(ValueError, "feed or story"):
            preview_visual_command({"brand": "Jauki", "content_type": "article", "topic": "Jurnal"})
        with self.assertRaisesRegex(ValueError, "Story content"):
            output_mode_for("story", "feed_portrait")

    def test_no_provider_or_live_worker_call(self):
        with patch("office_social_worker._do_generate", side_effect=AssertionError("live worker called")), \
             patch("office_social_worker.office_bridge.emit_event_sync", side_effect=AssertionError("event sent")), \
             patch("office_social_worker.urllib.request.urlopen", side_effect=AssertionError("network called")):
            result = preview_visual_command({"brand": "Jauki", "content_type": "feed", "topic": "Jurnal"}, seed=1)
        self.assertTrue(result["dry_run"])

    def test_brand_isolation_independent_of_template_choice(self):
        payload = {"content_type": "feed", "topic": "Study tips", "pattern": "tips"}
        jauki = preview_visual_command({**payload, "brand": "Jauki"}, seed=9)
        kauiz = preview_visual_command({**payload, "brand": "Kauiz"}, seed=9)
        self.assertEqual(jauki["template_id"], kauiz["template_id"])
        self.assertIn("#173D26", jauki["compiled_prompt"])
        self.assertNotIn("#1F49E7", jauki["compiled_prompt"])
        self.assertIn("#1F49E7", kauiz["compiled_prompt"])
        self.assertNotIn("#173D26", kauiz["compiled_prompt"])
        self.assertNotEqual(jauki["palette"], kauiz["palette"])

    def test_missing_optional_analysis_fields(self):
        result = preview_visual_command({"brand": "Jauki", "content_type": "feed", "topic": "Jurnal"}, seed=1)
        self.assertEqual(result["analysis_input"], {"topic": "Jurnal"})
        self.assertTrue(result["compiled_prompt"])
        self.assertEqual(normalize_analysis({"topic": "Jurnal", "statistics": {"value": "42%", "label": "hasil"}}),
                         {"topic": "Jurnal", "stat_value": "42%", "stat_label": "hasil"})

    def test_supplied_history_prevents_three_post_repetition(self):
        payload = {"brand": "Jauki", "content_type": "feed", "topic": "Tips belajar", "pattern": "tips"}
        history = []
        for seed in range(12):
            result = preview_visual_command(payload, history=history, seed=seed)
            self.assertNotIn(result["template_id"], {entry["template_id"] for entry in history[-3:]})
            history.append({
                "template_id": result["template_id"],
                "hero_position": result["variation"]["hero_position"],
                "background_treatment": result["variation"]["background_treatment"],
            })

    def test_preview_cannot_be_switched_to_live_mode(self):
        with self.assertRaisesRegex(ValueError, "preview-only"):
            preview_visual_memory({"brand": "Jauki", "content_type": "feed", "topic": "Jurnal"}, dry_run=False)

    def test_unknown_brand_and_unsupported_content_fail_closed(self):
        with self.assertRaisesRegex(ValueError, "Unknown visual brand"):
            preview_visual_memory({"brand": "unknown", "content_type": "feed", "topic": "Jurnal"})
        with self.assertRaisesRegex(ValueError, "Unsupported visual content type"):
            preview_visual_memory({"brand": "Jauki", "content_type": "thread", "topic": "Jurnal"})


if __name__ == "__main__":
    unittest.main()
