"""Tes offline untuk article_content (penulis artikel dan prompt sampul). Tidak ada panggilan jaringan."""

import json
import unittest

import article_content as ac
import visual_prompt_writer as writer

SENTENCE = "Mulai dari langkah kecil yang jelas lalu catat apa yang berhasil supaya besok terasa lebih ringan dan tidak membingungkan sama sekali."


def body(paragraphs=6, headings=2):
    parts = []
    for index in range(paragraphs):
        if index < headings:
            parts.append(f"<h2>Subjudul {index + 1}</h2>")
        parts.append("<p>" + " ".join([SENTENCE] * 7) + "</p>")
    return "\n".join(parts)


def valid(**overrides):
    article = {
        "title": "Cara menyusun jadwal belajar yang tidak bikin pusing", "excerpt": "Langkah ringan menyusun jadwal belajar harian.",
        "content": body(), "category": "Tips Belajar", "tags": ["jadwal", "belajar", "fokus"],
        "seo_title": "Jadwal belajar harian yang realistis", "seo_description": "Panduan singkat menyusun jadwal belajar yang bisa diikuti setiap hari.",
        "cover_headline": "Jadwal belajar tanpa pusing", "cover_subline": "Langkah kecil yang bisa kamu mulai hari ini", "cover_subject": "a tidy weekly planner and a pencil",
    }
    article.update(overrides)
    return article


def reply(article):
    return {"choices": [{"message": {"content": json.dumps(article)}}]}


class Transport:
    def __init__(self, *replies):
        self.replies, self.requests = list(replies), []

    def __call__(self, url, headers, body, timeout=120.0):
        self.requests.append(body)
        item = self.replies.pop(0)
        if isinstance(item, Exception):
            raise item
        return item


ENDPOINT = [{"base_url": "https://x", "api_key": "k", "model": "meta-llama/llama-3.1-8b-instruct"}]
KAUIZ, JAUKI = writer.load_brand_data("kauiz"), writer.load_brand_data("jauki")


class SanitizeTests(unittest.TestCase):
    def test_keeps_allowed_tags_and_drops_attributes_scripts_and_other_tags(self):
        dirty = '<h1>Judul</h1><p class="x" onclick="a()">Halo <a href="javascript:x">tautan</a> <img src="a.png"> dunia</p><script>alert(1)</script><table><tr><td>sel</td></tr></table>'
        clean = ac.sanitize_html(dirty)
        self.assertEqual(clean, "Judul<p>Halo tautan  dunia</p>sel")

    def test_allowed_tags_are_normalized_without_attributes(self):
        self.assertEqual(ac.sanitize_html('<P STYLE="x">a</P><H2 id="b">c</H2><UL><LI>d</LI></UL>'), "<p>a</p><h2>c</h2><ul><li>d</li></ul>")


class CleanTests(unittest.TestCase):
    def test_overlong_short_fields_are_trimmed_at_word_boundaries_instead_of_rejected(self):
        messy = valid(seo_description="Panduan praktis menyusun jadwal belajar yang bisa diikuti setiap hari tanpa membuat kepala pusing, lengkap dengan contoh langkah kecil dan cara mencatat hasilnya setiap malam sebelum tidur.",
                      title="Cara menyusun jadwal belajar " + "panjang " * 20, cover_subline="satu dua tiga empat lima enam tujuh delapan sembilan sepuluh sebelas dua belas tiga belas")
        cleaned = ac._clean(messy)
        self.assertLessEqual(len(cleaned["seo_description"]), ac.LIMITS["seo_description"])
        self.assertFalse(cleaned["seo_description"].endswith((" ", ",")))
        self.assertLessEqual(len(cleaned["title"]), ac.LIMITS["title"])
        self.assertLessEqual(len(cleaned["cover_subline"].split()), ac.COVER["subline_words"])
        self.assertEqual(ac.validate_article(cleaned, JAUKI), [])

    def test_titles_are_trimmed_at_a_clause_or_sentence_not_in_the_middle_of_a_phrase(self):
        self.assertEqual(ac._clip("Tips Mengerjakan Tugas Saat Deadline Mepet: Anti Panik Ala Mahasiswa", 60), "Tips Mengerjakan Tugas Saat Deadline Mepet")
        self.assertEqual(ac._clip("Kalimat pertama selesai di sini. Kalimat kedua yang cukup panjang untuk terpotong", 45), "Kalimat pertama selesai di sini.")
        self.assertEqual(ac._clip("pendek", 60), "pendek")
        self.assertLessEqual(len(ac._clip("kata " * 40, 50)), 50)

    def test_a_person_as_cover_subject_is_replaced_because_the_cards_draw_the_student_themselves(self):
        for subject in ("A student looking stressed with a laptop", "a woman at a desk", "mahasiswa sedang belajar"):
            self.assertEqual(ac._clean(valid(cover_subject=subject))["cover_subject"], ac.DEFAULT_SUBJECT, subject)
            self.assertTrue(ac.validate_article(valid(cover_subject=subject), JAUKI), subject)

    def test_non_english_cover_subject_gets_a_safe_default_scene(self):
        self.assertEqual(ac._clean(valid(cover_subject="meja belajar rapi"))["cover_subject"], ac.DEFAULT_SUBJECT)
        self.assertEqual(ac._clean(valid(cover_subject="a laptop and a mug"))["cover_subject"], "a laptop and a mug")

    def test_json_with_raw_newlines_inside_strings_is_accepted(self):
        newline = chr(10)
        broken = {"choices": [{"message": {"content": '{"a": "satu' + newline + 'dua"}'}}]}
        out = writer.chat_json(base_url="https://x", api_key="k", model="m", messages=[], transport=Transport(broken))
        self.assertEqual(out["a"], "satu" + newline + "dua")


class ValidationTests(unittest.TestCase):
    def test_accepts_a_good_article_for_both_brands(self):
        for data in (KAUIZ, JAUKI):
            self.assertEqual(ac.validate_article(valid(), data), [])

    def test_rejects_limits_structure_banned_words_and_unknown_prices(self):
        cases = {
            "title": valid(title="x" * 91), "seo": valid(seo_description="x" * 156), "short": valid(content="<h2>a</h2><p>pendek</p>"),
            "tags": valid(tags=["satu"]), "cover": valid(cover_headline="satu dua tiga empat lima enam tujuh delapan sembilan"),
            "quote": valid(cover_subline='dia bilang "halo"'), "scene": valid(cover_subject="meja belajar rapi"),
            "claim": valid(excerpt="Pakai joki murah sekarang"), "sensitive": valid(content=body() + "<p>dosen itu jahat</p>"),
        }
        for name, article in cases.items():
            self.assertTrue(ac.validate_article(article, JAUKI), name)
        self.assertTrue(ac.validate_article(valid(excerpt="Paket hanya Rp 9.999 per bulan"), KAUIZ))

    def test_brand_slug_rejects_unknown_brands(self):
        self.assertEqual(ac.brand_slug("Jauki"), "jauki")
        with self.assertRaises(ac.ArticleError):
            ac.brand_slug("Lainnya")


class WriteArticleTests(unittest.TestCase):
    PAYLOAD = {"brand": "Kauiz", "topic": "Jadwal belajar", "brief": "Cara menyusun jadwal", "key_message": "Mulai kecil", "content_id": 5}

    def test_writes_from_the_schedule_analysis_and_cleans_the_result(self):
        transport = Transport(reply(valid(content=body().replace("<p>", '<p class="a">'), tags=["#Jadwal", "belajar", "Fokus"], cover_headline="jadwal belajar.")))
        article = ac.write_article("kauiz", self.PAYLOAD, ENDPOINT, transport=transport)
        self.assertNotIn("class=", article["content"])
        self.assertEqual(article["tags"], ["jadwal", "belajar", "fokus"])
        self.assertEqual(article["cover_headline"], "JADWAL BELAJAR")
        user = json.loads(transport.requests[0]["messages"][1]["content"])
        self.assertEqual(user["bahan"]["topic"], "Jadwal belajar")
        self.assertEqual(user["brand"]["name"], "Kauiz")

    def test_jauki_prompt_forbids_service_promotion_and_kauiz_prompt_allows_trying_the_product(self):
        jauki = ac.build_messages(JAUKI, self.PAYLOAD)[0]["content"]
        kauiz = ac.build_messages(KAUIZ, self.PAYLOAD)[0]["content"]
        self.assertIn("joki", jauki)
        self.assertIn("mencoba Kauiz", kauiz)
        self.assertNotIn("mencoba Kauiz", jauki)

    def test_invalid_first_answer_gets_one_retry_with_feedback(self):
        transport = Transport(reply(valid(excerpt="Pakai joki murah")), reply(valid()))
        article = ac.write_article("jauki", {**self.PAYLOAD, "brand": "Jauki"}, ENDPOINT, transport=transport)
        self.assertEqual(article["title"], valid()["title"])
        self.assertIn("perbaiki", json.loads(transport.requests[1]["messages"][1]["content"]))

    def test_no_model_key_or_endless_invalid_output_raises_a_clear_error(self):
        with self.assertRaises(ac.ArticleError):
            ac.write_article("kauiz", self.PAYLOAD, [])
        with self.assertRaises(ac.ArticleError):
            ac.write_article("kauiz", self.PAYLOAD, ENDPOINT, transport=Transport(reply(valid(content="pendek")), reply(valid(content="pendek"))))

    def test_brand_data_of_the_other_brand_is_refused(self):
        with self.assertRaises(ac.ArticleError):
            ac.write_article("kauiz", self.PAYLOAD, ENDPOINT, transport=Transport(reply(valid())), brand_data=JAUKI)


class CoverTests(unittest.TestCase):
    def test_cover_prompt_uses_the_brand_article_layouts_and_three_texts(self):
        for brand, layout, color in (("kauiz", "KA1", "#D8F810"), ("jauki", "JA1", "#F4701B")):
            prompt = ac.cover_prompt(brand, {**valid(), "cover_headline": "JADWAL BELAJAR"}, layout)
            self.assertIn("16:9", prompt)
            self.assertIn(color, prompt)
            self.assertIn("These 3 texts", prompt)
            self.assertIn(f'reads exactly "{writer.load_brand_data(brand)["website"]}"', prompt)

    def test_cover_layout_of_the_other_brand_is_refused(self):
        with self.assertRaises(ac.ArticleError):
            ac.cover_prompt("kauiz", valid(), "JA1")

    def test_layout_rotation_avoids_the_last_three_and_stays_inside_the_brand(self):
        history, chosen = [], []
        for seed in range(30):
            layout = ac.pick_cover_layout("jauki", history, seed)
            chosen.append(layout)
            history.append(layout)
        self.assertTrue(all(item.startswith("JA") for item in chosen))
        for index in range(3, len(chosen)):
            self.assertNotIn(chosen[index], chosen[index - 3:index])


if __name__ == "__main__":
    unittest.main()
