"""Tes offline untuk visual_prompt_writer. Tidak ada panggilan jaringan."""

import json
import unittest

import visual_prompt_writer as writer

SOURCE = {
    "headline": "Tutup catatan, lalu ingat lagi",
    "subheadline": "Active recall membuat otak menarik ingatan, bukan sekadar membaca ulang.",
    "items": ["Tutup catatan", "Tulis dari ingatan", "Cek lalu perbaiki", "Ulangi besok"],
}
CTA, FOOTER = "KLIK LINK DI BIO", "kauiz.web.id"


def write(template_id, fmt="feed", **kwargs):
    return writer.write_content("kauiz", SOURCE, {"topic": "Active recall"}, template_id, fmt, "an open notebook beside a sticky note",
                                cta_label=CTA, footer=FOOTER, **kwargs)


class StyleCardTests(unittest.TestCase):
    def test_all_27_cards_build_prompts_with_exact_texts_for_both_formats(self):
        brand = writer.load_brand_data("kauiz")
        allowed = {code.upper() for code in brand["visual"]["allowed_hex"]}
        styles = writer.load_styles()
        self.assertEqual(sorted(styles), [f"T{i:02d}" for i in range(1, 28)])
        for template_id in styles:
            for fmt in ("feed", "story"):
                with self.subTest(template=template_id, fmt=fmt):
                    result = write(template_id, fmt)
                    prompt = result["muse_prompt"]
                    self.assertIn(writer.ASPECT_MARK[fmt], prompt)
                    self.assertIn('"kauiz.web.id"', prompt)
                    for text in writer.required_texts({k: v for k, v in result["copy"].items() if k in ("headline", "subline", "chips") and v},
                                                      result["copy"]["cta_label"], FOOTER):
                        self.assertIn(f'"{text}"', prompt)
                    self.assertTrue({c.upper() for c in __import__("re").findall(r"#[0-9A-Fa-f]{6}", prompt)} <= allowed)
                    self.assertNotIn("{", prompt)
                    self.assertEqual(result["errors"], [])

    def test_templates_with_chips_use_them_and_others_do_not(self):
        self.assertIn("chips", write("T08")["copy"])
        self.assertIn("chips", write("T11")["copy"])
        self.assertNotIn("chips", write("T01")["copy"])

    def test_long_headline_is_cut_at_a_clause_instead_of_mid_phrase(self):
        style = writer.load_styles()["T08"]
        copy = writer.derive_copy({**SOURCE, "headline": "Satu bab, satu peta konsep yang rapi sekali dan lengkap"}, style)
        self.assertEqual(copy["headline"], "SATU BAB")

    def test_every_prompt_states_the_edge_margins(self):
        for template_id in writer.load_styles():
            self.assertIn("left and right 10 percent", write(template_id)["muse_prompt"])
            self.assertIn("left and right 10 percent", write(template_id, "story")["muse_prompt"])
            self.assertIn("top 14 percent", write(template_id)["muse_prompt"])
            self.assertIn("bottom 18 percent", write(template_id, "story")["muse_prompt"])

    def test_story_prompt_keeps_safe_area_and_story_has_no_caption(self):
        result = write("T01", "story")
        self.assertIn("top 20 percent", result["muse_prompt"])
        self.assertIn("left and right 10 percent", result["muse_prompt"])
        self.assertEqual((result["caption"], result["hashtags"]), ("", []))


class LayoutCardTests(unittest.TestCase):
    def test_layout_cards_build_valid_prompts_for_both_brands_and_formats(self):
        layouts = writer.load_layout_styles()
        self.assertEqual(sorted(layouts, key=lambda k: int(k[1:])), [f"L{i}" for i in range(1, 15)])
        for brand, footer in (("kauiz", "kauiz.web.id"), ("jauki", "@jaukitugas")):
            data = writer.load_brand_data(brand)
            for layout_id in layouts:
                for fmt in ("feed", "story"):
                    with self.subTest(brand=brand, layout=layout_id, fmt=fmt):
                        result = writer.write_content(brand, SOURCE, {}, layout_id, fmt, "an open notebook beside a sticky note", cta_label=None,
                                                      footer=footer, brand_data=data, styles=layouts)
                        copy = result["copy"]
                        content = {"headline": copy["headline"], "subline": copy.get("subline"), "chips": copy.get("chips", []),
                                   "muse_prompt": result["muse_prompt"], "caption": result["caption"], "hashtags": result["hashtags"]}
                        self.assertEqual(writer.validate_content(content, data, layouts[layout_id], fmt, None, footer), [])
                        self.assertIn(f'reads exactly "{footer}"', result["muse_prompt"])
                        self.assertIn("left and right 10 percent", result["muse_prompt"])

    def test_both_brands_use_a_white_background_with_their_own_colors(self):
        layouts = writer.load_layout_styles()
        for layout_id in layouts:
            kauiz = writer.write_content("kauiz", SOURCE, {}, layout_id, "feed", "an open notebook", cta_label=None, footer=FOOTER, styles=layouts)["muse_prompt"]
            jauki = writer.write_content("jauki", SOURCE, {}, layout_id, "feed", "an open notebook", cta_label=None, footer="@jaukitugas", styles=layouts)["muse_prompt"]
            self.assertIn("clean white (#FFFFFF)", kauiz, layout_id)
            self.assertNotIn("solid deep navy", kauiz, layout_id)
            self.assertIn("clean white (#FFFFFF)", jauki, layout_id)
            self.assertIn("dark brown (#532923)", jauki, layout_id)
            self.assertNotIn("#0B1537", jauki, layout_id)
            self.assertNotIn("dark-brown", jauki, layout_id)
            self.assertIn("warm-orange (#E07540) footer bar", jauki, layout_id)
            self.assertIn("sky blue (#7CB1EB)", jauki, layout_id)
            self.assertNotIn("#532923", kauiz, layout_id)

    def test_every_layout_shows_headline_subline_and_three_points(self):
        layouts = writer.load_layout_styles()
        for brand, footer in (("kauiz", "kauiz.web.id"), ("jauki", "@jaukitugas")):
            for layout_id in layouts:
                copy = writer.write_content(brand, SOURCE, {}, layout_id, "feed", "an open notebook", cta_label=None, footer=footer, styles=layouts)["copy"]
                self.assertTrue(copy.get("subline"), (brand, layout_id))
                self.assertEqual(len(copy.get("chips", [])), 3, (brand, layout_id))

    def test_jauki_surfaces_are_colorful_not_brown(self):
        layouts = writer.load_layout_styles()
        for layout_id in layouts:
            prompt = writer.write_content("jauki", SOURCE, {}, layout_id, "feed", "an open notebook", cta_label=None, footer="@jaukitugas", styles=layouts)["muse_prompt"]
            self.assertNotIn("brown geometric block", prompt, layout_id)
            self.assertNotIn("brown footer", prompt, layout_id)
        self.assertIn("soft yellow (#F3C75F) geometric block", writer.write_content(
            "jauki", SOURCE, {}, "L2", "feed", "an open notebook", cta_label=None, footer="@jaukitugas", styles=layouts)["muse_prompt"])

    def test_layout_prompts_leave_scene_and_text_style_to_muse(self):
        prompt = write("T01")["muse_prompt"]
        layouts = writer.load_layout_styles()
        result = writer.write_content("kauiz", SOURCE, {}, "L1", "feed", "an open notebook", cta_label=None, footer=FOOTER, styles=layouts)
        self.assertIn("choose a fitting, natural scene", result["muse_prompt"])
        self.assertNotIn("mascot", result["muse_prompt"])
        self.assertNotIn("glass", result["muse_prompt"])
        self.assertTrue(prompt)


class CaptionTests(unittest.TestCase):
    def test_deterministic_content_passes_its_own_validation(self):
        brand, styles = writer.load_brand_data("kauiz"), writer.load_styles()
        for template_id in styles:
            result = write(template_id)
            content = {"headline": result["copy"]["headline"], "subline": result["copy"].get("subline"), "chips": result["copy"].get("chips", []),
                       "muse_prompt": result["muse_prompt"], "caption": result["caption"], "hashtags": result["hashtags"]}
            self.assertEqual(writer.validate_content(content, brand, styles[template_id], "feed", CTA, FOOTER), [], template_id)

    def test_caption_points_to_link_in_bio_and_stays_within_the_limit(self):
        result = write("T01")
        self.assertIn("link di bio", result["caption"].lower())
        self.assertLessEqual(len(result["caption"]), writer.CAPTION_LIMIT)


class ValidationTests(unittest.TestCase):
    def setUp(self):
        self.brand, self.style = writer.load_brand_data("kauiz"), writer.load_styles()["T01"]
        base = write("T01")
        self.content = {"headline": base["copy"]["headline"], "subline": base["copy"].get("subline"), "chips": [],
                        "muse_prompt": base["muse_prompt"], "caption": base["caption"], "hashtags": base["hashtags"]}

    def check(self, **changes):
        return writer.validate_content({**self.content, **changes}, self.brand, self.style, "feed", CTA, FOOTER)

    def test_baseline_is_valid(self):
        self.assertEqual(self.check(), [])

    def test_rejects_text_missing_from_prompt_foreign_colors_and_banned_claims(self):
        self.assertTrue(any("persis" in e for e in self.check(muse_prompt=self.content["muse_prompt"].replace(self.content["headline"], "LAIN"))))
        self.assertTrue(any("warna" in e for e in self.check(muse_prompt=self.content["muse_prompt"] + " Use #FF0000.")))
        self.assertTrue(any("terlarang" in e for e in self.check(caption=self.content["caption"] + " Ada garansi uang kembali.")))

    def test_rejects_unknown_prices_bad_hashtags_and_long_captions(self):
        self.assertTrue(any("harga" in e for e in self.check(caption=self.content["caption"] + " Hanya Rp 5.000.")))
        self.assertTrue(any("hashtags" in e for e in self.check(hashtags=["#ok", "bukan tag"])))
        self.assertTrue(any("caption" in e for e in self.check(caption="x" * 2300)))

    def test_allows_prices_from_the_data(self):
        self.assertEqual(self.check(caption=self.content["caption"] + " Mulai Rp 16.000."), [])


class LlmTests(unittest.TestCase):
    def llm(self, replies):
        calls = []

        def transport(url, headers, body):
            calls.append((url, headers, body))
            return {"choices": [{"message": {"content": replies[min(len(calls) - 1, len(replies) - 1)]}}]}

        return {"base_url": "https://llm.example/v1/", "api_key": "k", "model": "m", "transport": transport}, calls

    def valid_reply(self):
        base = write("T01")
        return json.dumps({
            "headline": base["copy"]["headline"], "subline": base["copy"].get("subline"), "chips": [],
            "muse_prompt": base["muse_prompt"] + " Extra scene detail.", "caption": base["caption"] + "\n",
            "hashtags": base["hashtags"],
        })

    def test_valid_llm_reply_is_used_and_request_is_chat_completions(self):
        llm, calls = self.llm(["```json\n" + self.valid_reply() + "\n```"])
        result = write("T01", llm=llm)
        self.assertEqual(result["source"], "llm")
        self.assertIn("Extra scene detail", result["muse_prompt"])
        self.assertEqual(calls[0][0], "https://llm.example/v1/chat/completions")
        self.assertEqual(calls[0][2]["model"], "m")
        self.assertEqual(calls[0][1], {"Authorization": "Bearer k"})
        self.assertEqual(calls[0][2]["max_tokens"], 4096)

    def test_request_without_api_key_sends_no_authorization_header(self):
        llm, calls = self.llm([self.valid_reply()])
        llm["api_key"] = ""
        write("T01", llm=llm)
        self.assertEqual(calls[0][1], {})

    def test_invalid_reply_gets_one_retry_with_feedback_then_falls_back(self):
        bad = json.loads(self.valid_reply())
        bad["muse_prompt"] += " Use #FF0000."
        llm, calls = self.llm([json.dumps(bad)])
        result = write("T01", llm=llm)
        self.assertEqual(len(calls), 2)
        self.assertIn("perbaiki", calls[1][2]["messages"][1]["content"])
        self.assertEqual(result["source"], "template")
        self.assertTrue(result["errors"])
        self.assertNotIn("#FF0000", result["muse_prompt"])

    def test_retry_can_succeed(self):
        bad = json.loads(self.valid_reply())
        bad["hashtags"] = []
        llm, calls = self.llm([json.dumps(bad), self.valid_reply()])
        self.assertEqual(write("T01", llm=llm)["source"], "llm")
        self.assertEqual(len(calls), 2)

    def test_non_json_reply_falls_back_without_retry(self):
        llm, calls = self.llm(["maaf, tidak bisa"])
        result = write("T01", llm=llm)
        self.assertEqual((result["source"], len(calls)), ("template", 1))

    def test_failing_primary_endpoint_falls_through_to_the_fallback_model(self):
        calls = []

        def broken(url, headers, body):
            calls.append(body["model"])
            raise writer.WriterError("LLM HTTP 429: limit")

        good, good_calls = self.llm([self.valid_reply()])
        result = write("T01", llm=[
            {"base_url": "https://a", "api_key": "", "model": "primary", "transport": broken},
            {**good, "model": "fallback"},
        ])
        self.assertEqual((result["source"], result["model"]), ("llm", "fallback"))
        self.assertEqual(calls, ["primary"])  # panggilan gagal tidak diulang
        self.assertEqual(len(good_calls), 1)
        self.assertTrue(any("primary" in e and "429" in e for e in result["errors"]))

    def test_all_endpoints_failing_gives_the_template_content(self):
        def broken(url, headers, body):
            raise writer.WriterError("mati")

        result = write("T01", llm=[{"base_url": "https://a", "api_key": "", "model": m, "transport": broken} for m in ("a", "b")])
        self.assertEqual(result["source"], "template")
        self.assertEqual(len(result["errors"]), 2)

    def test_story_reply_never_carries_a_caption(self):
        llm, _ = self.llm([self.valid_reply().replace("4:5", "9:16")])
        result = write("T01", "story", llm=llm)
        self.assertEqual(result["caption"], "")


if __name__ == "__main__":
    unittest.main()
