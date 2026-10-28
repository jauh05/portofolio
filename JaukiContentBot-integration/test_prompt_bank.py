"""Tes offline untuk bank materi (prompt_bank) dan isinya. Tidak ada panggilan jaringan."""

import re
import unittest

import prompt_bank as bank
import visual_prompt_writer as writer

BRANDS = ("kauiz", "jauki")
SARA = ["agama", "islam", "kristen", "katolik", "hindu", "buddha", "budha", "yahudi", "cina", "tionghoa", "pribumi", "jawa", "batak", "sunda",
        "papua", "madura", "bugis", "arab", "kafir", "ateis", "suku", "etnis", "gay", "lesbi", "bencong", "cacat", "gila", "idiot", "bodoh",
        "tolol", "goblok", "partai", "politik", "pilpres"]
COUNTS = {"feed": 100, "story": 200}


def texts(item):
    return " ".join(str(value) for value in item.values()).lower()


class BankContentTests(unittest.TestCase):
    def test_each_brand_has_100_feed_and_200_story_items_with_unique_ids_and_hooks(self):
        for brand in BRANDS:
            for fmt, count in COUNTS.items():
                items = bank.load_bank(brand, fmt)
                self.assertEqual(len(items), count, (brand, fmt))
                self.assertEqual(len({item["id"] for item in items}), count)
                self.assertEqual(len({item["hook"].lower() for item in items}), count, f"hook ganda di {brand} {fmt}")

    def test_items_fit_every_template_slot(self):
        styles = writer.load_styles()
        for brand in BRANDS:
            for fmt in COUNTS:
                for item in bank.load_bank(brand, fmt):
                    self.assertTrue(item["subject"].isascii(), item["id"])
                    self.assertLessEqual(len(item["hook"].split()), 5, item["id"])
                    for style in styles.values():
                        copy = writer.derive_copy({"headline": item["hook"], "subheadline": item["line"], "items": item["chips"]}, style)
                        self.assertEqual(copy["headline"], item["hook"].upper(), (item["id"], "headline terpotong"))

    def test_no_banned_claims_prices_or_sensitive_words(self):
        for brand in BRANDS:
            data = writer.load_brand_data(brand)
            avoid = [claim.lower() for claim in data["avoid_claims"]]
            allowed = {re.sub(r"\s+", "", price).lower() for price in data.get("allowed_price_texts", [])}
            for fmt in COUNTS:
                for item in bank.load_bank(brand, fmt):
                    blob = texts(item)
                    for claim in avoid:
                        self.assertNotRegex(blob, r"\b" + re.escape(claim) + r"\b", (item["id"], claim))
                    for word in SARA:
                        self.assertNotRegex(blob, r"\b" + re.escape(word) + r"\b", (item["id"], word))
                    for price in re.findall(r"rp\s?[\d.]+", blob):
                        self.assertIn(re.sub(r"\s+", "", price).rstrip("."), allowed, (item["id"], price))

    def test_kauiz_items_name_only_real_features(self):
        names = {feature["name"] for feature in writer.load_brand_data("kauiz")["features"]}
        for fmt in COUNTS:
            for item in bank.load_bank("kauiz", fmt):
                self.assertTrue(item["feature"] is None or item["feature"] in names, item["id"])

    def test_jauki_items_are_humor_without_service_promotion(self):
        for item in bank.load_bank("jauki", "feed"):
            self.assertGreaterEqual(len(item["caption"]), 40)
            self.assertNotIn("jaukitugas.my.id", item["caption"])


class BankPromptTests(unittest.TestCase):
    def test_every_item_builds_a_valid_prompt_and_caption_on_a_rotating_template(self):
        styles = writer.load_styles()
        template_ids = sorted(styles)
        for brand in BRANDS:
            data = writer.load_brand_data(brand)
            cta = "KLIK LINK DI BIO" if brand == "kauiz" else None
            footer = "kauiz.web.id" if brand == "kauiz" else "@jaukitugas"
            for fmt in COUNTS:
                for index, item in enumerate(bank.load_bank(brand, fmt)):
                    plan = bank.to_plan(item, fmt)
                    template_id = template_ids[index % len(template_ids)]
                    source = {"headline": plan["key_message"], "subheadline": plan["brief"], "items": plan["key_points"]}
                    source.update({k: plan[k] for k in ("pain", "tip", "feature", "caption_text") if plan.get(k)})
                    result = writer.write_content(brand, source, {}, template_id, fmt, plan["visual_subject"], cta_label=cta, footer=footer,
                                                  seed=index, brand_data=data, styles=styles)
                    content = {"headline": result["copy"]["headline"], "subline": result["copy"].get("subline"), "chips": result["copy"].get("chips", []),
                               "muse_prompt": result["muse_prompt"], "caption": result["caption"], "hashtags": result["hashtags"]}
                    self.assertEqual(writer.validate_content(content, data, styles[template_id], fmt, cta, footer), [], (brand, fmt, item["id"], template_id))
                    if brand == "jauki":
                        self.assertNotRegex(result["muse_prompt"].lower(), r"navy|cream\b(?<!warm cream)")
                    else:
                        self.assertIn("#0B1537", result["muse_prompt"])

    def test_kauiz_feed_caption_carries_pain_tip_and_cta(self):
        item = bank.load_bank("kauiz", "feed")[0]
        plan = bank.to_plan(item, "feed")
        result = writer.write_content("kauiz", {"headline": plan["key_message"], "subheadline": plan["brief"], "items": plan["key_points"],
                                                "pain": plan["pain"], "tip": plan["tip"], "feature": plan["feature"]}, {}, "T01", "feed",
                                      plan["visual_subject"], cta_label="KLIK LINK DI BIO", footer="kauiz.web.id")
        self.assertIn(item["pain"], result["caption"])
        self.assertIn(item["tip"], result["caption"])
        self.assertIn("link di bio", result["caption"].lower())

    def test_jauki_caption_has_no_call_to_action(self):
        item = bank.load_bank("jauki", "feed")[0]
        plan = bank.to_plan(item, "feed")
        result = writer.write_content("jauki", {"headline": plan["key_message"], "subheadline": plan["brief"], "items": plan["key_points"],
                                                "caption_text": plan["caption_text"]}, {}, "T01", "feed", plan["visual_subject"],
                                      cta_label=None, footer="@jaukitugas")
        self.assertNotIn("link di bio", result["caption"].lower())
        self.assertIn("#jaukitugas", result["caption"])


class BankUsageTests(unittest.TestCase):
    def test_items_are_never_repeated_until_the_bank_is_exhausted(self):
        state, seen = {}, []
        for _ in range(200):
            item = bank.next_item(state, "kauiz", "story")
            self.assertIsNotNone(item)
            seen.append(item["id"])
            bank.consume(state, "kauiz", "story", item["id"])
        self.assertEqual(len(set(seen)), 200)
        self.assertIsNone(bank.next_item(state, "kauiz", "story"))
        self.assertEqual(bank.remaining(state, "kauiz", "story"), 0)

    def test_next_item_is_stable_until_consumed_and_formats_are_tracked_separately(self):
        state = {}
        first = bank.next_item(state, "kauiz", "feed")
        self.assertEqual(bank.next_item(state, "kauiz", "feed")["id"], first["id"])
        bank.consume(state, "kauiz", "feed", first["id"])
        bank.consume(state, "kauiz", "feed", first["id"])
        self.assertEqual(state["bank"]["kauiz:feed"].count(first["id"]), 1)
        self.assertEqual(bank.remaining(state, "kauiz", "feed"), 99)
        self.assertEqual(bank.remaining(state, "kauiz", "story"), 200)

    def test_order_is_shuffled_not_sequential(self):
        ordered = []
        state = {}
        for _ in range(10):
            item = bank.next_item(state, "kauiz", "feed")
            ordered.append(item["id"])
            bank.consume(state, "kauiz", "feed", item["id"])
        self.assertNotEqual(ordered, [f"kf-{i:03d}" for i in range(1, 11)])

    def test_to_plan_marks_the_source_and_carries_caption_fields(self):
        plan = bank.to_plan(bank.load_bank("kauiz", "feed")[0], "feed")
        self.assertEqual(plan["source"], "bank")
        self.assertTrue(plan["bank_id"].startswith("kf-"))
        self.assertIn("pain", plan)
        story = bank.to_plan(bank.load_bank("kauiz", "story")[0], "story")
        self.assertNotIn("pain", story)


if __name__ == "__main__":
    unittest.main()
