"""Tes offline untuk kauiz_auto_worker. Tidak ada panggilan jaringan."""

import base64
import fcntl
import io
import json
import logging
import os
import re
import tempfile
import unittest
from datetime import datetime, timedelta
from unittest import mock
from pathlib import Path
from urllib.error import URLError
from zoneinfo import ZoneInfo

from PIL import Image

import kauiz_auto_worker as worker

BRANDS = worker.WORKER_DIR.parents[1] / "resources" / "office-ai" / "visual-memory" / "brands.json"
NOW = datetime(2026, 10, 25, 9, 0)  # jam 9 pagi: slot "am"


class FakeClient:
    def __init__(self, fail_story_with=None):
        self.calls = []
        self.fail_story_with = fail_story_with

    def create_container(self, image_url, *, caption=None, story=False):
        if story and self.fail_story_with:
            raise self.fail_story_with
        self.calls.append(("create", image_url, caption, story))
        return f"creation-{len(self.calls)}"

    def publish(self, creation_id):
        self.calls.append(("publish", creation_id))
        return f"media-{creation_id}"


def scripted(responses):
    """Transport palsu: tiap panggilan mengembalikan respons berikutnya, atau melempar exception."""
    queue = list(responses)
    calls = []

    def transport(url, form):
        calls.append((url, dict(form)))
        item = queue.pop(0)
        if isinstance(item, BaseException):
            raise item
        return item

    transport.calls = calls
    return transport


def make_config(root, **overrides):
    values = {
        "ig_user_id": "1784",
        "access_token": "secret-token",
        "public_dir": root / "public",
        "public_base_url": "https://media.example.com/kauiz",
        "output_dir": root / "out",
        "state_file": root / "state.json",
        "lock_file": root / "worker.lock",
        "log_file": root / "logs" / "worker.log",
        "llm_model": "",
    }
    values.update(overrides)
    return worker.Config(**values)


class PaletteTests(unittest.TestCase):
    def test_palette_matches_kauiz_brand_file(self):
        brand = json.loads(BRANDS.read_text(encoding="utf-8"))["brands"]["kauiz"]["palette"]
        for name, value in worker.PALETTE.items():
            self.assertEqual(value.upper(), brand[name].upper(), name)


class CopyTests(unittest.TestCase):
    def test_every_feed_caption_has_cta_and_fits_limit(self):
        for topic in worker.TOPICS:
            caption = worker.build_feed_caption(topic)
            self.assertIn("kauiz.web.id", caption)
            self.assertIn("Klik link di bio", caption)
            self.assertIn("Komentar KAUIZ", caption)
            self.assertLessEqual(len(caption), worker.CAPTION_LIMIT)

    def test_consecutive_slots_cycle_through_all_topics(self):
        seen = []
        for offset in range(len(worker.TOPICS) // 2):
            day = NOW + timedelta(days=offset)
            for slot in ("am", "pm"):
                seen.append(worker.pick_topic(day, slot)["tag"])
        self.assertEqual(len(set(seen)), len(worker.TOPICS))
        for previous, current in zip(seen, seen[1:]):
            self.assertNotEqual(previous, current)


class RenderTests(unittest.TestCase):
    def test_every_topic_renders_both_formats_at_canvas_size(self):
        with tempfile.TemporaryDirectory() as tmp:
            for topic in worker.TOPICS:
                for fmt in worker.FORMATS:
                    path = Path(tmp) / f"{topic['tag']}-{fmt}.jpg"
                    worker.render_image(topic, fmt, path)
                    with Image.open(path) as image:
                        self.assertEqual(image.format, "JPEG")
                        self.assertEqual(image.size, worker.CANVAS[fmt])


PLAN = {
    "topic": "Active recall",
    "key_message": "Tutup catatan, lalu ingat lagi",
    "brief": "Latihan mengingat tanpa membuka catatan.",
    "cta": "Coba sekarang",
    "hook_direction": "pertanyaan tentang kebiasaan belajar",
    "objective": "edukasi",
}


def png_bytes(size=(640, 480), color=(20, 40, 90)):
    buffer = io.BytesIO()
    Image.new("RGB", size, color).save(buffer, "PNG")
    return buffer.getvalue()


class MuseConfigTests(unittest.TestCase):
    def test_defaults_keep_pillow_mode(self):
        config = worker.load_config({})
        self.assertEqual(config.image_mode, "pillow")
        self.assertIsNone(config.visual_memory_dir)

    def test_muse_mode_reads_key_and_memory_dir(self):
        config = worker.load_config({"KAUIZ_IMAGE_MODE": "muse", "OPENROUTER_API_KEY": "k", "KAUIZ_VISUAL_MEMORY_DIR": "/opt/vm"})
        self.assertEqual(config.image_mode, "muse")
        self.assertEqual(config.openrouter_api_key, "k")
        self.assertEqual(config.visual_memory_dir, Path("/opt/vm"))


class MuseTests(unittest.TestCase):
    def setUp(self):
        self._saved_env = os.environ.get("VISUAL_MEMORY_DIR")
        os.environ["VISUAL_MEMORY_DIR"] = str(BRANDS.parent)
        self.visual_dir = worker.WORKER_DIR.parents[1] / "JaukiContentBot-integration"

    def tearDown(self):
        if self._saved_env is None:
            os.environ.pop("VISUAL_MEMORY_DIR", None)
        else:
            os.environ["VISUAL_MEMORY_DIR"] = self._saved_env

    def test_request_is_model_and_prompt_with_bearer_key(self):
        captured = {}

        def transport(url, headers, body):
            captured.update(url=url, headers=headers, body=body)
            return {"data": [{"b64_json": base64.b64encode(png_bytes()).decode()}]}

        worker.generate_background("prompt teks", api_key="test-key", base_url="https://openrouter.ai/api/v1/",
                                   model="meta/muse-image", fmt="feed", transport=transport)
        self.assertEqual(captured["url"], "https://openrouter.ai/api/v1/images")
        self.assertEqual(captured["headers"], {"Authorization": "Bearer test-key"})
        self.assertEqual(captured["body"], {"model": "meta/muse-image", "prompt": "prompt teks",
                                            "aspect_ratio": "4:5", "output_format": "jpeg"})

    def test_b64_and_url_responses_return_image_bytes(self):
        raw = png_bytes()
        from_b64 = worker.generate_background(
            "p", api_key="k", base_url="https://x", model="m", fmt="feed",
            transport=lambda url, headers, body: {"data": [{"b64_json": base64.b64encode(raw).decode()}]},
        )
        self.assertEqual(from_b64, raw)
        from_url = worker.generate_background(
            "p", api_key="k", base_url="https://x", model="m", fmt="feed",
            transport=lambda url, headers, body: {"data": [{"url": "https://img.example/a.png"}]},
            fetch=lambda url: raw,
        )
        self.assertEqual(from_url, raw)

    def test_empty_response_and_missing_key_raise(self):
        with self.assertRaises(worker.MuseError):
            worker.generate_background("p", api_key="k", base_url="https://x", model="m", fmt="feed",
                                       transport=lambda url, headers, body: {"data": []})
        with self.assertRaises(worker.MuseError):
            worker.generate_background("p", api_key="", base_url="https://x", model="m", fmt="feed",
                                       transport=lambda url, headers, body: {})

    def test_story_is_padded_not_cropped_so_side_content_survives(self):
        source = Image.new("RGB", (1280, 1920), (255, 255, 255))
        draw = __import__("PIL.ImageDraw", fromlist=["ImageDraw"]).Draw(source)
        draw.rectangle((0, 0, 39, 1919), fill=(255, 0, 0))
        draw.rectangle((1240, 0, 1279, 1919), fill=(0, 0, 255))
        buffer = io.BytesIO()
        source.save(buffer, "PNG")
        story = worker.fit_to_canvas(buffer.getvalue(), "story")
        self.assertEqual(story.size, worker.CANVAS["story"])
        left, right = story.getpixel((2, 960)), story.getpixel((1077, 960))
        self.assertGreater(left[0], 200)
        self.assertLess(left[1], 60)
        self.assertGreater(right[2], 200)
        self.assertLess(right[0], 60)

    def feed_source_with_top_band(self):
        source = Image.new("RGB", (1280, 1920), (255, 255, 255))
        __import__("PIL.ImageDraw", fromlist=["ImageDraw"]).Draw(source).rectangle((0, 0, 1279, 39), fill=(255, 0, 0))
        buffer = io.BytesIO()
        source.save(buffer, "PNG")
        return buffer.getvalue()

    def test_feed_is_padded_by_default_so_the_top_survives(self):
        feed = worker.fit_to_canvas(self.feed_source_with_top_band(), "feed")
        self.assertEqual(feed.size, worker.CANVAS["feed"])
        pixel = feed.getpixel((540, 3))
        self.assertGreater(pixel[0], 200)
        self.assertLess(pixel[1], 60)

    def test_feed_crop_mode_still_crops_the_top_off(self):
        feed = worker.fit_to_canvas(self.feed_source_with_top_band(), "feed", "crop")
        self.assertEqual(feed.size, worker.CANVAS["feed"])
        self.assertGreater(feed.getpixel((540, 3))[1], 200)  # pita merah di atas sudah terpotong

    def test_muse_body_variants_for_the_aspect_ratio(self):
        self.assertEqual(worker.muse_body("p", "m", "feed", "param")["aspect_ratio"], "4:5")
        self.assertEqual(worker.muse_body("p", "m", "story", "image_config")["image_config"], {"aspect_ratio": "9:16"})
        none = worker.muse_body("p", "m", "feed", "none")
        self.assertNotIn("aspect_ratio", none)
        self.assertNotIn("image_config", none)

    def test_aspect_mode_reaches_the_request(self):
        captured = {}

        def transport(url, headers, body):
            captured.update(body)
            return {"data": [{"b64_json": base64.b64encode(png_bytes()).decode()}]}

        worker.generate_background("p", api_key="k", base_url="https://x", model="m", fmt="feed", transport=transport, aspect_mode="none")
        self.assertNotIn("aspect_ratio", captured)

    def test_solid_fill_uses_edge_colors_and_keeps_side_content(self):
        source = Image.new("RGB", (1280, 1920), (10, 20, 90))
        __import__("PIL.ImageDraw", fromlist=["ImageDraw"]).Draw(source).rectangle((0, 0, 1279, 30), fill=(255, 203, 61))
        buffer = io.BytesIO()
        source.save(buffer, "PNG")
        story = worker.fit_to_canvas(buffer.getvalue(), "story", fill="solid")
        self.assertEqual(story.size, worker.CANVAS["story"])
        top_pad = story.getpixel((540, 5))
        self.assertGreater(top_pad[0], 150)   # pita kuning di tepi atas menurunkan warna isian atas
        self.assertGreater(story.getpixel((540, 1900))[2], 60)

    def test_transient_muse_failures_are_retried_then_succeed(self):
        raw = png_bytes()
        calls, sleeps = [], []

        def transport(url, headers, body):
            calls.append(1)
            if len(calls) < 3:
                raise worker.MuseTransientError("respons terputus")
            return {"data": [{"b64_json": base64.b64encode(raw).decode()}]}

        result = worker.generate_background("p", api_key="k", base_url="https://x", model="m", fmt="feed", transport=transport, sleep=sleeps.append)
        self.assertEqual((result, len(calls), sleeps), (raw, 3, [5, 15]))

    def test_permanent_muse_errors_are_not_retried_and_exhausted_retries_raise(self):
        calls = []

        def permanent(url, headers, body):
            calls.append(1)
            raise worker.MuseError("OpenRouter HTTP 400")

        with self.assertRaises(worker.MuseError):
            worker.generate_background("p", api_key="k", base_url="https://x", model="m", fmt="feed", transport=permanent, sleep=lambda s: None)
        self.assertEqual(len(calls), 1)
        failing = []

        def transient(url, headers, body):
            failing.append(1)
            raise worker.MuseTransientError("putus")

        with self.assertRaises(worker.MuseTransientError):
            worker.generate_background("p", api_key="k", base_url="https://x", model="m", fmt="feed", transport=transient, sleep=lambda s: None)
        self.assertEqual(len(failing), 3)

    def test_truncated_http_response_becomes_a_transient_error(self):
        import http.client
        with mock.patch.object(worker.urllib.request, "urlopen", side_effect=http.client.IncompleteRead(b"")):
            with self.assertRaises(worker.MuseTransientError):
                worker.http_post_json("https://x", {}, {})
        with mock.patch.object(worker.urllib.request, "urlopen", side_effect=ConnectionResetError("reset")):
            with self.assertRaises(worker.MuseTransientError):
                worker.http_get_bytes("https://x")

    def test_fit_to_canvas_matches_each_format(self):
        for fmt in worker.FORMATS:
            with self.subTest(fmt=fmt):
                self.assertEqual(worker.fit_to_canvas(png_bytes((800, 600)), fmt).size, worker.CANVAS[fmt])

    def muse_config(self, root, **overrides):
        values = {"image_mode": "muse", "text_mode": "pillow", "openrouter_api_key": "k", "visual_memory_dir": self.visual_dir, **overrides}
        return make_config(root, **values)

    def test_muse_job_uses_kauiz_palette_forbids_text_and_reserves_zones(self):
        with tempfile.TemporaryDirectory() as tmp:
            job = worker.muse_job(self.muse_config(Path(tmp)), PLAN, "story", "2026-10-27-2030", [])
        self.assertIn("#0B1537", job["prompt"])
        self.assertIn("Do not render any text", job["prompt"])
        self.assertIn("Reserved areas for text added later", job["prompt"])
        self.assertEqual(job["mode"], "story")

    def test_muse_render_overlays_text_on_background_at_canvas_size(self):
        topic = worker.topic_from_plan(PLAN)
        for fmt in worker.FORMATS:
            with self.subTest(fmt=fmt), tempfile.TemporaryDirectory() as tmp:
                root = Path(tmp)
                config = self.muse_config(root)
                state = {"published": {}}
                out = root / f"slot-{fmt}.jpg"
                with mock.patch.object(worker, "generate_background", return_value=png_bytes((900, 900))) as generate:
                    worker.render_muse_image(config, PLAN, topic, fmt, "slot", out, state)
                    worker.render_muse_image(config, PLAN, topic, fmt, "slot", out, state)  # latar dipakai ulang
                self.assertEqual(generate.call_count, 1)
                self.assertEqual(len(state["history"]), 1)
                with Image.open(out) as image:
                    self.assertEqual(image.format, "JPEG")
                    self.assertEqual(image.size, worker.CANVAS[fmt])

    def test_daily_image_cap_blocks_the_next_muse_call(self):
        topic = worker.topic_from_plan(PLAN)
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            config = self.muse_config(root, max_images_per_day=1)
            state = {"published": {}}
            with mock.patch.object(worker, "generate_background", return_value=png_bytes((900, 900))) as generate:
                worker.render_muse_image(config, PLAN, topic, "feed", "slot-a", root / "a.jpg", state)
                with self.assertRaises(worker.MuseError):
                    worker.render_muse_image(config, PLAN, topic, "feed", "slot-b", root / "b.jpg", state)
            self.assertEqual(generate.call_count, 1)

    def test_dry_run_writes_prompt_plan_and_layout_preview_and_never_calls_muse(self):
        with tempfile.TemporaryDirectory() as tmp:
            config = self.muse_config(Path(tmp))
            topic = worker.topic_from_plan(PLAN)
            with mock.patch.object(worker, "generate_background", side_effect=AssertionError("Muse tidak boleh dipanggil")):
                code = worker.run_auto(config, live=False, formats=("feed",), now=NOW, slot_key="2026-10-27-2030",
                                       topic=topic, publish=True, plan=PLAN)
            self.assertEqual(code, worker.EXIT_OK)
            for suffix in ("prompt.txt", "plan.json", "preview.jpg"):
                self.assertTrue((config.output_dir / f"2026-10-27-2030-feed.{suffix}").exists(), suffix)

    def test_templates_do_not_repeat_within_the_gap_and_cover_many_templates(self):
        with tempfile.TemporaryDirectory() as tmp:
            config = self.muse_config(Path(tmp))
            history, chosen = [], []
            for index in range(60):
                job = worker.muse_job(config, PLAN, "feed", f"2026-11-{index:02d}-1300", history)
                chosen.append(job["template_id"])
                history = (history + [{"template_id": job["template_id"], "hero_position": job["variation"]["hero_position"],
                                       "background_treatment": job["variation"]["background_treatment"]}])[-worker.HISTORY_LIMIT:]
        for index in range(3, len(chosen)):
            self.assertNotIn(chosen[index], chosen[index - 3:index])
        self.assertGreaterEqual(len(set(chosen)), 12)

    def empty_slot_config(self, root, **overrides):
        slots = root / "slots.json"
        slots.write_text(json.dumps([{"format": "feed", "day": 2, "time": "13:40", "timezone": "Asia/Jakarta", "lead_minutes": 120}]))
        return self.muse_config(root, slots_file=slots, text_mode="muse", **overrides)

    def test_empty_slot_uses_a_bank_item_and_a_dry_run_does_not_consume_it(self):
        with tempfile.TemporaryDirectory() as tmp:
            config = self.empty_slot_config(Path(tmp))
            now = datetime(2026, 10, 27, 13, 45, tzinfo=ZoneInfo("Asia/Jakarta"))
            with mock.patch.object(worker, "generate_background", side_effect=AssertionError("Muse tidak boleh dipanggil")):
                self.assertEqual(worker.run_due(config, live=False, now=now), worker.EXIT_OK)
            self.assertTrue(any(config.output_dir.glob("*.plan.json")))
            self.assertNotIn("bank", worker.load_state(config.state_file))

    def test_live_runs_consume_different_bank_items_and_never_repeat(self):
        bank = worker.load_bank_module(self.visual_dir)
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            config = self.muse_config(root, text_mode="muse")
            state = {"published": {}}
            seen = []
            for index in range(5):
                item = bank.next_item(state, "kauiz", "story")
                seen.append(item["id"])
                plan = bank.to_plan(item, "story")
                with mock.patch.object(worker, "generate_background", return_value=png_bytes((900, 900))):
                    worker.render_muse_image(config, plan, worker.topic_from_plan(plan), "story", f"slot-{index}", root / f"s{index}.jpg", state)
            self.assertEqual(len(set(seen)), 5)
            self.assertEqual(sorted(state["bank"]["kauiz:story"]), sorted(seen))

    def test_exhausted_bank_skips_the_slot_instead_of_repeating(self):
        bank = worker.load_bank_module(self.visual_dir)
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            config = self.empty_slot_config(root)
            worker.save_state(config.state_file, {"published": {}, "bank": {"kauiz:feed": [item["id"] for item in bank.load_bank("kauiz", "feed")]}})
            now = datetime(2026, 10, 27, 13, 45, tzinfo=ZoneInfo("Asia/Jakarta"))
            with mock.patch.object(worker, "generate_background", side_effect=AssertionError("Muse tidak boleh dipanggil")):
                self.assertEqual(worker.run_due(config, live=True, now=now), worker.EXIT_FAILED)

    def test_text_model_is_used_for_slot_content_but_not_for_bank_items(self):
        worker.load_visual_modules(self.visual_dir)
        import visual_prompt_writer

        def fake_chat(**kwargs):
            example = json.loads(kwargs["messages"][1]["content"])["contoh_prompt"]
            return {"headline": re.search(r'reads exactly "([^"]+)"', example).group(1), "subline": None, "chips": [], "muse_prompt": example,
                    "caption": "Hook.\n\nKlik link di bio.\n\n#kauiz #belajarefektif #tipsbelajar", "hashtags": ["#kauiz", "#belajarefektif", "#tipsbelajar"]}

        bank = worker.load_bank_module(self.visual_dir)
        bank_plan = bank.to_plan(bank.next_item({}, "kauiz", "feed"), "feed")
        with tempfile.TemporaryDirectory() as tmp:
            config = self.muse_config(Path(tmp), text_mode="muse", llm_model="m", llm_api_key="k", writer_in_dryrun=True)
            with mock.patch.object(visual_prompt_writer, "chat_json", side_effect=fake_chat) as chat:
                worker.write_prompt_preview(config, {**PLAN, "source": "slot"}, worker.topic_from_plan(PLAN), "feed", "slot-a", {})
                self.assertEqual(chat.call_count, 1)
                worker.write_prompt_preview(config, bank_plan, worker.topic_from_plan(bank_plan), "feed", "slot-b", {})
                self.assertEqual(chat.call_count, 1)  # materi bank tidak memanggil model

    def test_llm_endpoints_use_the_muse_key_and_an_optional_fallback(self):
        config = worker.load_config({"OPENROUTER_API_KEY": "muse-key", "KAUIZ_LLM_FALLBACK_MODEL": "g", "KAUIZ_LLM_FALLBACK_BASE_URL": "http://127.0.0.1:4000/v1"})
        endpoints = worker.llm_endpoints(config)
        self.assertEqual(config.llm_model, "meta-llama/llama-3.1-8b-instruct")
        self.assertEqual([e["model"] for e in endpoints], ["meta-llama/llama-3.1-8b-instruct", "g"])
        self.assertEqual(endpoints[0]["api_key"], "muse-key")
        self.assertIsNone(worker.llm_endpoints(worker.load_config({})))

    def test_text_in_muse_dry_run_writes_exact_prompt_and_caption_without_calling_muse(self):
        with tempfile.TemporaryDirectory() as tmp:
            config = self.muse_config(Path(tmp), text_mode="muse")
            topic = worker.topic_from_plan(PLAN)
            with mock.patch.object(worker, "generate_background", side_effect=AssertionError("Muse tidak boleh dipanggil")):
                code = worker.run_auto(config, live=False, formats=("feed",), now=NOW, slot_key="2026-10-27-2030",
                                       topic=topic, publish=True, plan={**PLAN, "source": "bank"})
            self.assertEqual(code, worker.EXIT_OK)
            base = config.output_dir / "2026-10-27-2030-feed"
            prompt = base.with_name(base.name + ".prompt.txt").read_text(encoding="utf-8")
            self.assertIn('reads exactly "TUTUP CATATAN, LALU INGAT LAGI"', prompt)
            self.assertIn('"kauiz.web.id"', prompt)
            self.assertIn("#kauiz", base.with_name(base.name + ".caption.txt").read_text(encoding="utf-8"))
            self.assertFalse(base.with_name(base.name + ".preview.jpg").exists())

    def test_text_in_muse_live_run_sends_prompt_without_overlay_and_posts_writer_caption(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            config = self.muse_config(root, text_mode="muse")
            topic = worker.topic_from_plan(PLAN)
            client = FakeClient()
            with mock.patch.object(worker, "generate_background", return_value=png_bytes((900, 900))) as generate:
                code = worker.run_auto(config, live=True, formats=("feed",), now=NOW, slot_key="2026-10-27-2030",
                                       topic=topic, publish=True, plan={**PLAN, "source": "bank"}, client=client)
            self.assertEqual(code, worker.EXIT_OK)
            self.assertEqual(generate.call_count, 1)
            self.assertIn('reads exactly "TUTUP CATATAN, LALU INGAT LAGI"', generate.call_args.args[0])
            caption = client.calls[0][2]
            self.assertTrue(caption.startswith("Tutup catatan, lalu ingat lagi."))
            self.assertIn("#kauiz", caption)
            with Image.open(config.output_dir / "2026-10-27-2030-feed.jpg") as image:
                self.assertEqual(image.size, worker.CANVAS["feed"])
            self.assertTrue((config.output_dir / "2026-10-27-2030-feed.writer.json").exists())

    def test_story_in_text_mode_muse_has_no_caption(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            config = self.muse_config(root, text_mode="muse")
            topic = worker.topic_from_plan(PLAN)
            client = FakeClient()
            with mock.patch.object(worker, "generate_background", return_value=png_bytes((900, 900))):
                worker.run_auto(config, live=True, formats=("story",), now=NOW, slot_key="2026-10-27-2030",
                                topic=topic, publish=True, plan={**PLAN, "source": "bank"}, client=client)
            self.assertIsNone(client.calls[0][2])

    def test_llm_output_from_dry_run_is_reused_by_the_live_run(self):
        worker.load_visual_modules(self.visual_dir)
        import visual_prompt_writer

        def fake_chat(**kwargs):
            user = json.loads(kwargs["messages"][1]["content"])
            example = user["contoh_prompt"]
            return {
                "headline": re.search(r'reads exactly "([^"]+)"', example).group(1), "subline": None, "chips": [],
                "muse_prompt": example + " Extra scene detail.",
                "caption": "Hook dari model teks.\n\nKlik link di bio ya.\n\n#kauiz #belajarefektif #tipsbelajar",
                "hashtags": ["#kauiz", "#belajarefektif", "#tipsbelajar"],
            }

        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            config = self.muse_config(root, text_mode="muse", llm_model="m", llm_api_key="k", writer_in_dryrun=True)
            topic = worker.topic_from_plan(PLAN)
            slot_plan = {**PLAN, "source": "slot"}
            with mock.patch.object(visual_prompt_writer, "chat_json", side_effect=fake_chat) as chat:
                worker.run_auto(config, live=False, formats=("feed",), now=NOW, slot_key="2026-10-27-2030", topic=topic, publish=True, plan=slot_plan)
                self.assertEqual(chat.call_count, 1)
                client = FakeClient()
                with mock.patch.object(worker, "generate_background", return_value=png_bytes((900, 900))) as generate:
                    worker.run_auto(config, live=True, formats=("feed",), now=NOW, slot_key="2026-10-27-2030", topic=topic,
                                    publish=True, plan=slot_plan, client=client)
                self.assertEqual(chat.call_count, 1)  # live memakai hasil dry run, model tidak dipanggil lagi
            self.assertIn("Extra scene detail", generate.call_args.args[0])
            self.assertTrue(client.calls[0][2].startswith("Hook dari model teks."))

    def test_text_mode_muse_picks_layouts_not_templates_and_rotates_them(self):
        with tempfile.TemporaryDirectory() as tmp:
            config = self.muse_config(Path(tmp), text_mode="muse")
            history, chosen = [], []
            for index in range(40):
                job = worker.muse_job(config, PLAN, "feed", f"2026-11-{index:02d}-1300", history, with_prompt=False)
                chosen.append(job["template_id"])
                history = (history + [{"template_id": job["template_id"], "hero_position": "free", "background_treatment": "free"}])[-worker.HISTORY_LIMIT:]
        self.assertTrue(all(lid.startswith("L") for lid in chosen))
        self.assertEqual(len(set(chosen)), 8)
        for index in range(3, len(chosen)):
            self.assertNotIn(chosen[index], chosen[index - 3:index])

    def test_pattern_subject_and_short_line_helpers(self):
        self.assertEqual(worker.infer_pattern({"topic": "Tips belajar efektif"}), "tips")
        self.assertEqual(worker.infer_pattern({"topic": "Webinar gratis"}), "event")
        self.assertIn("quiz", worker.subject_hint({"topic": "Latihan soal harian"}))
        self.assertLessEqual(len(worker.short_line("kata " * 60, 110)), 111)
        for topic in worker.TOPICS:
            plan = worker.plan_from_topic(topic)
            self.assertTrue(plan["visual_subject"].isascii())
            self.assertEqual(len(plan["key_points"]), 3)

    def test_topic_from_plan_and_due_jobs_carry_the_plan(self):
        topic = worker.topic_from_plan(PLAN)
        self.assertEqual(topic["headline"], "Tutup catatan, lalu ingat lagi")
        self.assertEqual(topic["body"], "Latihan mengingat tanpa membuka catatan.")
        self.assertEqual(topic["tag"], "activerecall")
        slot = {"format": "feed", "day": 2, "time": "13:40", "timezone": "Asia/Jakarta", "lead_minutes": 120, **PLAN}
        now = datetime(2026, 10, 27, 13, 45, tzinfo=ZoneInfo("Asia/Jakarta"))
        jobs = worker.due_jobs([slot], now, "Asia/Jakarta")
        self.assertEqual(len(jobs), 1)
        self.assertEqual(jobs[0].plan["topic"], "Active recall")


class ErrorClassificationTests(unittest.TestCase):
    def test_media_not_found_right_after_creation_is_transient(self):
        error = worker.classify_error(400, {}, {"error": {"code": 24, "error_subcode": 2207006, "message": "The requested resource does not exist"}})
        self.assertIsInstance(error, worker.TransientError)
        self.assertTrue(error.retryable)

    def test_rate_limit_codes_and_429_are_rate_limit(self):
        for status, code in ((429, None), (400, 4), (400, 17), (400, 32), (400, 613), (400, 80001)):
            error = worker.classify_error(status, {}, {"error": {"code": code, "message": "limit"}})
            self.assertIsInstance(error, worker.RateLimitError, (status, code))
            self.assertTrue(error.retryable)

    def test_token_error_is_not_retryable(self):
        error = worker.classify_error(400, {}, {"error": {"code": 190, "message": "expired"}})
        self.assertIsInstance(error, worker.AuthError)
        self.assertFalse(error.retryable)

    def test_retry_after_header_is_parsed(self):
        error = worker.classify_error(429, {"Retry-After": "42"}, {})
        self.assertEqual(error.retry_after, 42)


class GraphClientTests(unittest.TestCase):
    def test_rate_limit_waits_retry_after_then_succeeds(self):
        transport = scripted([
            (429, {"Retry-After": "2"}, {"error": {"code": 4, "message": "limit"}}),
            (200, {}, {"id": "17"}),
        ])
        sleeps = []
        client = worker.GraphClient("tok", "1784", transport=transport, sleep=sleeps.append)
        self.assertEqual(client.publish("c1"), "17")
        self.assertEqual(sleeps, [2])
        self.assertEqual(len(transport.calls), 2)

    def test_token_goes_in_form_not_url(self):
        transport = scripted([(200, {}, {"id": "9"})])
        client = worker.GraphClient("secret-token", "1784", transport=transport, sleep=lambda s: None)
        client.publish("c1")
        url, form = transport.calls[0]
        self.assertNotIn("secret-token", url)
        self.assertEqual(form["access_token"], "secret-token")

    def test_long_rate_limit_is_not_waited_out(self):
        transport = scripted([(429, {"Retry-After": "3600"}, {"error": {"code": 4}})])
        sleeps = []
        client = worker.GraphClient("tok", "1784", transport=transport, sleep=sleeps.append, max_wait=300)
        with self.assertRaises(worker.RateLimitError):
            client.publish("c1")
        self.assertEqual(sleeps, [])
        self.assertEqual(len(transport.calls), 1)

    def test_expired_token_raises_without_retry(self):
        transport = scripted([(400, {}, {"error": {"code": 190, "message": "expired"}})])
        client = worker.GraphClient("tok", "1784", transport=transport, sleep=lambda s: None)
        with self.assertRaises(worker.AuthError):
            client.publish("c1")
        self.assertEqual(len(transport.calls), 1)

    def test_connection_drop_is_retried_then_succeeds(self):
        transport = scripted([URLError("connection reset"), (200, {}, {"id": "9"})])
        sleeps = []
        client = worker.GraphClient("tok", "1784", transport=transport, sleep=sleeps.append)
        self.assertEqual(client.publish("c1"), "9")
        self.assertEqual(sleeps, [10])

    def test_connection_drop_gives_up_after_max_retries(self):
        transport = scripted([URLError("down"), URLError("down")])
        sleeps = []
        client = worker.GraphClient("tok", "1784", transport=transport, sleep=sleeps.append, max_retries=1)
        with self.assertRaises(worker.NetworkError):
            client.publish("c1")
        self.assertEqual(sleeps, [10])
        self.assertEqual(len(transport.calls), 2)

    def test_media_not_ready_is_retried(self):
        transport = scripted([
            (400, {}, {"error": {"code": 9007, "error_subcode": 2207027, "message": "not ready"}}),
            (200, {}, {"id": "5"}),
        ])
        sleeps = []
        client = worker.GraphClient("tok", "1784", transport=transport, sleep=sleeps.append)
        self.assertEqual(client.publish("c1"), "5")
        self.assertEqual(sleeps, [15])

    def test_story_container_uses_stories_media_type(self):
        transport = scripted([(200, {}, {"id": "s1"})])
        client = worker.GraphClient("tok", "1784", transport=transport, sleep=lambda s: None)
        client.create_container("https://media.example.com/kauiz/x.jpg", story=True)
        _, form = transport.calls[0]
        self.assertEqual(form["media_type"], "STORIES")
        self.assertNotIn("caption", form)


class RunAutoTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)

    def tearDown(self):
        self.tmp.cleanup()

    def test_dry_run_renders_only_and_makes_no_calls(self):
        client = FakeClient()
        config = make_config(self.root)
        code = worker.run_auto(config, live=False, formats=worker.FORMATS, now=NOW, client=client)
        self.assertEqual(code, worker.EXIT_OK)
        self.assertEqual(client.calls, [])
        self.assertTrue((self.root / "out" / "2026-10-25-am-feed.jpg").exists())
        self.assertTrue((self.root / "out" / "2026-10-25-am-story.jpg").exists())
        self.assertFalse((self.root / "state.json").exists())
        self.assertFalse((self.root / "public").exists())

    def test_live_run_publishes_and_is_idempotent(self):
        client = FakeClient()
        config = make_config(self.root)
        code = worker.run_auto(config, live=True, formats=worker.FORMATS, now=NOW, client=client)
        self.assertEqual(code, worker.EXIT_OK)
        self.assertEqual(len(client.calls), 4)
        state = json.loads((self.root / "state.json").read_text(encoding="utf-8"))
        self.assertEqual(set(state["published"]), {"2026-10-25-am:feed", "2026-10-25-am:story"})
        self.assertEqual(len(list((self.root / "public").glob("*.jpg"))), 2)

        again = FakeClient()
        with self.assertLogs("kauiz.worker", level="INFO") as logs:
            code = worker.run_auto(config, live=True, formats=worker.FORMATS, now=NOW, client=again)
        self.assertEqual(code, worker.EXIT_OK)
        self.assertEqual(again.calls, [])
        self.assertTrue(any("sudah pernah dipublish" in line for line in logs.output))

    def test_partial_failure_is_logged_and_retried_next_run(self):
        config = make_config(self.root)
        failing = FakeClient(fail_story_with=worker.AuthError("token expired", code=190))
        with self.assertLogs("kauiz.worker", level="ERROR") as logs:
            code = worker.run_auto(config, live=True, formats=worker.FORMATS, now=NOW, client=failing)
        self.assertEqual(code, worker.EXIT_FAILED)
        self.assertTrue(any("token ditolak" in line for line in logs.output))
        state = json.loads((self.root / "state.json").read_text(encoding="utf-8"))
        self.assertEqual(set(state["published"]), {"2026-10-25-am:feed"})

        code = worker.run_auto(config, live=True, formats=worker.FORMATS, now=NOW, client=FakeClient())
        self.assertEqual(code, worker.EXIT_OK)
        state = json.loads((self.root / "state.json").read_text(encoding="utf-8"))
        self.assertEqual(len(state["published"]), 2)

    def test_rate_limit_is_logged_as_skipped_slot(self):
        config = make_config(self.root)
        limited = FakeClient(fail_story_with=worker.RateLimitError("too many calls", code=4))
        with self.assertLogs("kauiz.worker", level="ERROR") as logs:
            code = worker.run_auto(config, live=True, formats=("story",), now=NOW, client=limited)
        self.assertEqual(code, worker.EXIT_FAILED)
        self.assertTrue(any("rate limit Instagram" in line for line in logs.output))

    def test_live_run_without_credentials_stops_before_work(self):
        config = make_config(self.root, ig_user_id="", access_token="")
        with self.assertLogs("kauiz.worker", level="ERROR") as logs:
            code = worker.run_auto(config, live=True, formats=worker.FORMATS, now=NOW, client=FakeClient())
        self.assertEqual(code, worker.EXIT_CONFIG)
        self.assertTrue(any("KAUIZ_IG_USER_ID" in line for line in logs.output))
        self.assertFalse((self.root / "out").exists())

    def test_corrupt_state_refuses_to_publish(self):
        config = make_config(self.root)
        (self.root / "state.json").write_text("{not json", encoding="utf-8")
        with self.assertRaises(RuntimeError):
            worker.run_auto(config, live=True, formats=worker.FORMATS, now=NOW, client=FakeClient())

    def test_second_worker_is_refused_by_lock(self):
        config = make_config(self.root)
        config.lock_file.parent.mkdir(parents=True, exist_ok=True)
        with config.lock_file.open("w") as held:
            fcntl.flock(held, fcntl.LOCK_EX | fcntl.LOCK_NB)
            with self.assertRaises(worker.LockBusy):
                worker.run_auto(config, live=False, formats=worker.FORMATS, now=NOW)


class LoggingTests(unittest.TestCase):
    def test_access_token_is_redacted_from_log_messages(self):
        record = logging.LogRecord("kauiz.worker", logging.ERROR, __file__, 1,
                                   "gagal ke %s", ("https://x/y?access_token=abc123&a=1",), None)
        worker.TokenRedactor().filter(record)
        self.assertIn("access_token=***", record.getMessage())
        self.assertNotIn("abc123", record.getMessage())


class ConfigTests(unittest.TestCase):
    def test_env_file_does_not_override_existing_variables(self):
        with tempfile.TemporaryDirectory() as tmp:
            env_file = Path(tmp) / "worker.env"
            env_file.write_text("# komentar\nexport KAUIZ_TIMEZONE=UTC\nKAUIZ_IG_USER_ID=\"42\"\n", encoding="utf-8")
            saved = {key: os.environ.get(key) for key in ("KAUIZ_TIMEZONE", "KAUIZ_IG_USER_ID")}
            try:
                os.environ["KAUIZ_TIMEZONE"] = "Asia/Jakarta"
                os.environ.pop("KAUIZ_IG_USER_ID", None)
                worker.load_env_file(env_file)
                self.assertEqual(os.environ["KAUIZ_TIMEZONE"], "Asia/Jakarta")
                self.assertEqual(os.environ["KAUIZ_IG_USER_ID"], "42")
            finally:
                for key, value in saved.items():
                    if value is None:
                        os.environ.pop(key, None)
                    else:
                        os.environ[key] = value


ZONE = ZoneInfo("Asia/Jakarta")
# Minggu 25 Oktober 2026, jam 09.00 WIB. Hari 7 berarti Minggu.
SUNDAY_SLOT = {
    "id": 7, "label": "Feed Kauiz", "format": "feed", "platform": "instagram", "day": 7,
    "time": "09:00", "timezone": "Asia/Jakarta", "lead_minutes": 120,
}


class SlotScheduleTests(unittest.TestCase):
    def test_job_is_generated_two_hours_before_and_posted_at_slot_time(self):
        generate, = worker.due_jobs([SUNDAY_SLOT], datetime(2026, 10, 25, 7, 30, tzinfo=ZONE), "Asia/Jakarta")
        post, = worker.due_jobs([SUNDAY_SLOT], datetime(2026, 10, 25, 9, 2, tzinfo=ZONE), "Asia/Jakarta")

        self.assertEqual(generate.slot_key, "2026-10-25-0900")
        self.assertFalse(generate.publish)
        self.assertTrue(post.publish)
        self.assertEqual(post.slot_key, generate.slot_key)
        self.assertEqual(post.topic, generate.topic)

    def test_slot_outside_window_is_not_due(self):
        too_early = datetime(2026, 10, 25, 6, 59, tzinfo=ZONE)
        too_late = datetime(2026, 10, 25, 10, 0, tzinfo=ZONE)
        self.assertEqual(worker.due_jobs([SUNDAY_SLOT], too_early, "Asia/Jakarta"), [])
        self.assertEqual(worker.due_jobs([SUNDAY_SLOT], too_late, "Asia/Jakarta"), [])

    def test_slot_on_other_weekday_is_not_due(self):
        monday = datetime(2026, 10, 26, 9, 0, tzinfo=ZONE)
        self.assertEqual(worker.due_jobs([SUNDAY_SLOT], monday, "Asia/Jakarta"), [])

    def test_bad_slots_are_logged_and_skipped(self):
        wrong_format = {"format": "reels", "day": 7, "time": "09:00"}
        wrong_hour = {"format": "feed", "day": 7, "time": "25:00"}
        now = datetime(2026, 10, 25, 9, 0, tzinfo=ZONE)
        with self.assertLogs("kauiz.worker", level="ERROR") as logs:
            jobs = worker.due_jobs([wrong_format, wrong_hour, SUNDAY_SLOT], now, "Asia/Jakarta")
        self.assertEqual(len(jobs), 1)
        self.assertEqual(len(logs.output), 2)

    def test_slot_without_time_is_skipped_silently(self):
        now = datetime(2026, 10, 25, 9, 0, tzinfo=ZONE)
        without_time = {"format": "feed", "day": 7, "time": None}
        with self.assertNoLogs("kauiz.worker", level="ERROR"):
            jobs = worker.due_jobs([without_time, SUNDAY_SLOT], now, "Asia/Jakarta")
        self.assertEqual(len(jobs), 1)


class RunDueTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        self.config = make_config(self.root, slots_file=self.root / "slots.json")

    def tearDown(self):
        self.tmp.cleanup()

    def write_slots(self, slots):
        (self.root / "slots.json").write_text(json.dumps(slots), encoding="utf-8")

    def test_missing_slots_file_does_nothing(self):
        client = FakeClient()
        code = worker.run_due(self.config, live=True, now=datetime(2026, 10, 25, 9, 0, tzinfo=ZONE), client=client)
        self.assertEqual(code, worker.EXIT_OK)
        self.assertEqual(client.calls, [])

    def test_image_is_generated_before_slot_and_posted_once_at_slot_time(self):
        self.write_slots([SUNDAY_SLOT])
        image = self.root / "out" / "2026-10-25-0900-feed.jpg"

        early = FakeClient()
        code = worker.run_due(self.config, live=True, now=datetime(2026, 10, 25, 7, 30, tzinfo=ZONE), client=early)
        self.assertEqual(code, worker.EXIT_OK)
        self.assertEqual(early.calls, [])
        self.assertTrue(image.exists())
        self.assertFalse((self.root / "state.json").exists())

        on_time = FakeClient()
        code = worker.run_due(self.config, live=True, now=datetime(2026, 10, 25, 9, 2, tzinfo=ZONE), client=on_time)
        self.assertEqual(code, worker.EXIT_OK)
        self.assertEqual([call[0] for call in on_time.calls], ["create", "publish"])
        state = json.loads((self.root / "state.json").read_text(encoding="utf-8"))
        self.assertEqual(set(state["published"]), {"2026-10-25-0900:feed"})

        again = FakeClient()
        worker.run_due(self.config, live=True, now=datetime(2026, 10, 25, 9, 4, tzinfo=ZONE), client=again)
        self.assertEqual(again.calls, [])

    def test_generation_does_not_need_instagram_credentials(self):
        self.write_slots([SUNDAY_SLOT])
        config = make_config(self.root, slots_file=self.root / "slots.json", ig_user_id="", access_token="")
        code = worker.run_due(config, live=True, now=datetime(2026, 10, 25, 7, 30, tzinfo=ZONE), client=FakeClient())
        self.assertEqual(code, worker.EXIT_OK)
        self.assertTrue((self.root / "out" / "2026-10-25-0900-feed.jpg").exists())

    def test_corrupt_slots_file_refuses_to_run(self):
        (self.root / "slots.json").write_text("{rusak", encoding="utf-8")
        with self.assertRaises(RuntimeError):
            worker.run_due(self.config, live=True, now=datetime(2026, 10, 25, 9, 0, tzinfo=ZONE), client=FakeClient())


if __name__ == "__main__":
    unittest.main()
