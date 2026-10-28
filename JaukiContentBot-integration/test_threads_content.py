"""Tes offline untuk threads_content dan bank Threads Jauki. Tidak ada panggilan jaringan."""

import json
import re
import tempfile
import unittest
from collections import Counter
from pathlib import Path

import prompt_bank as bank
import threads_content as tc
import visual_prompt_writer as writer

BRAND = writer.load_brand_data("jauki")


def llm_reply(text, tags=("#deadline", "#jaukitugas", "#mahasiswa")):
    return {"choices": [{"message": {"content": json.dumps({"text": text, "hashtags": list(tags)})}}]}


class Transport:
    def __init__(self, *replies):
        self.replies = list(replies)
        self.requests = []

    def __call__(self, url, headers, body, timeout=120.0):
        self.requests.append(body)
        reply = self.replies.pop(0)
        if isinstance(reply, Exception):
            raise reply
        return reply


ENV_WITH_KEY = {"OPENROUTER_API_KEY": "kunci-uji"}


class ThreadsBankTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.items = bank.load_bank("jauki", "threads")

    def test_has_150_unique_posts_split_between_complaints_and_tips(self):
        self.assertEqual(len(self.items), 150)
        self.assertEqual(len({item["id"] for item in self.items}), 150)
        self.assertEqual(len({item["text"].lower() for item in self.items}), 150)
        self.assertTrue(all(item["id"].startswith("jt-") for item in self.items))
        self.assertEqual(Counter(item["kind"] for item in self.items), {"keluhan": 75, "tips": 75})

    def test_every_post_passes_the_same_checks_the_model_output_gets(self):
        for item in self.items:
            self.assertEqual(tc.validate_post(item["text"], item["tags"], BRAND), [], item["id"])
            self.assertEqual(len(item["tags"]), 3, item["id"])
            self.assertEqual(item["tags"][1], "#jaukitugas", item["id"])
            self.assertLessEqual(len(tc.compose(item["text"], item["tags"], 3)), tc.POST_LIMIT, item["id"])

    def test_posts_are_not_one_template_with_a_few_words_swapped(self):
        openers = Counter(item["text"].split()[0].lower() for item in self.items)
        self.assertLess(openers.most_common(1)[0][1], 20)

    def test_the_threads_bank_belongs_to_jauki_only(self):
        with self.assertRaises(ValueError):
            bank.consume({}, "kauiz", "threads", "jt-001")
        with self.assertRaises(ValueError):
            bank.consume({}, "jauki", "threads", "jf-001")
        with self.assertRaises(ValueError):
            bank.load_bank("kauiz", "threads")

    def test_each_post_is_used_once_and_the_bank_runs_out(self):
        state = {}
        seen = []
        for _ in range(150):
            item = bank.next_item(state, "jauki", "threads")
            seen.append(item["id"])
            bank.consume(state, "jauki", "threads", item["id"])
        self.assertEqual(len(set(seen)), 150)
        self.assertIsNone(bank.next_item(state, "jauki", "threads"))
        self.assertEqual(bank.remaining(state, "jauki", "threads"), 0)


class ValidationTests(unittest.TestCase):
    def check(self, text, tags=("#tugaskuliah", "#jaukitugas")):
        return tc.validate_post(text, list(tags), BRAND)

    def test_accepts_a_clean_post(self):
        self.assertEqual(self.check("Revisi datang jam sebelas malam. Kita lanjut besok saja."), [])

    def test_rejects_service_promotion_sensitive_words_links_and_hashtags_in_text(self):
        for text in ("Pakai jasa kami murah", "Dosen itu jahat sekali", "Mahasiswa kampus tertentu bodoh", "Lihat https://contoh.id", "Tugas #deadline lagi"):
            self.assertTrue(self.check(text), text)

    def test_whole_word_matching_does_not_flag_innocent_words(self):
        self.assertEqual(self.check("Menjawab pertanyaan dosen butuh waktu. Gaya tulisan saya masih berubah."), [])

    def test_rejects_long_posts_and_bad_hashtags(self):
        self.assertTrue(self.check("a" * (tc.BODY_LIMIT + 1)))
        self.assertTrue(self.check("Halo", tags=("kata",)))
        self.assertTrue(self.check("Halo", tags=()))

    def test_compose_keeps_two_tags_by_default_and_drops_them_when_too_long(self):
        self.assertEqual(tc.compose("Halo", ["#a", "#b", "#c"]), "Halo\n\n#a #b")
        long_body = "x" * 497
        self.assertEqual(tc.compose(long_body, ["#a", "#b"]), long_body)


class GenerateTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.state_path = Path(self.tmp.name) / "threads.json"

    def run_generate(self, payload=None, env=None, transport=None):
        return tc.generate(payload, env=env if env is not None else {}, state_path=self.state_path, transport=transport)

    def test_empty_schedule_uses_the_bank_once_per_post_and_records_it(self):
        first = self.run_generate({})
        second = self.run_generate(None)
        self.assertEqual((first["source"], second["source"]), ("bank", "bank"))
        self.assertNotEqual(first["bank_id"], second["bank_id"])
        used = json.loads(self.state_path.read_text(encoding="utf-8"))["bank"]["jauki:threads"]
        self.assertEqual(used, [first["bank_id"], second["bank_id"]])
        self.assertIn("#jaukitugas", first["text"])
        self.assertEqual(len(re.findall(r"#\w+", first["text"])), 2)

    def test_schedule_with_analysis_is_written_by_the_model_not_taken_from_the_bank(self):
        transport = Transport(llm_reply("Revisi datang bersamaan dengan deadline. Kita atur napas dulu."))
        payload = {"topic": "Revisi dosen", "key_message": "Revisi itu proses", "angle": "keluh kesah", "brief": "Humor revisi."}
        result = self.run_generate(payload, ENV_WITH_KEY, transport)
        self.assertEqual((result["source"], result["bank_id"]), ("llm", None))
        self.assertIn("Revisi datang", result["text"])
        self.assertIn("meta-llama/llama-3.1-8b-instruct", transport.requests[0]["model"])
        user = json.loads(transport.requests[0]["messages"][1]["content"])
        self.assertEqual(user["analisis"]["topic"], "Revisi dosen")
        self.assertFalse(self.state_path.exists() and "bank" in json.loads(self.state_path.read_text(encoding="utf-8")))

    def test_model_output_that_breaks_the_rules_gets_one_retry_with_feedback(self):
        transport = Transport(llm_reply("Pakai jasa kami murah"), llm_reply("Tugas menumpuk, kopi menipis, semangat tetap ada."))
        result = self.run_generate({"topic": "Tugas menumpuk"}, ENV_WITH_KEY, transport)
        self.assertEqual(result["source"], "llm")
        self.assertIn("perbaiki", json.loads(transport.requests[1]["messages"][1]["content"]))

    def test_without_a_model_the_schedule_text_itself_is_used_with_brand_tags(self):
        result = self.run_generate({"topic": "Printer ngambek", "key_message": "Printer selalu ngambek di hari kumpul", "brief": "Cetak lebih awal."})
        self.assertEqual(result["source"], "fallback")
        self.assertIn("Printer selalu ngambek", result["text"])
        self.assertIn("#jaukitugas", result["text"])

    def test_unsafe_schedule_text_without_a_model_is_refused(self):
        with self.assertRaises(tc.ThreadsContentError):
            self.run_generate({"topic": "Promo", "key_message": "Pesan joki murah sekarang"})

    def test_exhausted_bank_falls_back_to_the_model_and_never_repeats_old_posts(self):
        state = {}
        for item in bank.load_bank("jauki", "threads"):
            bank.consume(state, "jauki", "threads", item["id"])
        self.state_path.write_text(json.dumps(state), encoding="utf-8")
        transport = Transport(llm_reply("Hari ini target kecil saja: satu paragraf selesai."))
        result = self.run_generate({}, ENV_WITH_KEY, transport)
        self.assertEqual((result["source"], result["bank_id"]), ("llm", None))
        with self.assertRaises(tc.BankExhausted):
            self.run_generate({}, {}, None)

    def test_model_outage_does_not_block_a_scheduled_post(self):
        transport = Transport(writer.WriterError("down"))
        result = self.run_generate({"topic": "Deadline", "key_message": "Deadline selalu datang lebih cepat"}, ENV_WITH_KEY, transport)
        self.assertEqual(result["source"], "fallback")


if __name__ == "__main__":
    unittest.main()
