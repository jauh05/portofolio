"""Tes offline untuk kauiz_auto_worker. Tidak ada panggilan jaringan."""

import fcntl
import json
import logging
import os
import tempfile
import unittest
from datetime import datetime, timedelta
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


class ErrorClassificationTests(unittest.TestCase):
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
