"""Tes pengiriman Reels ke Telegram untuk TikTok dan lead 1 hari. Tanpa ffmpeg, Telegram, dan Instagram sungguhan."""

import json
import os
import tempfile
import unittest
import urllib.error
from datetime import datetime
from pathlib import Path
from unittest import mock
from zoneinfo import ZoneInfo

import generate_content as gc
import slot_plan as sp
import telegram_notify as tn

TZ = ZoneInfo("Asia/Jakarta")
MONDAY_11 = datetime(2026, 11, 2, 11, 0, tzinfo=TZ)
CAPTION = "Tugas numpuk tapi semangat jalan terus.\n\n#jaukiacademy #tugas #mahasiswa #kuliah #deadline #humor #relatable"


class FakeCall:
    def __init__(self, fail_with=None):
        self.calls, self.fail_with = [], fail_with

    def __call__(self, method, fields, file_field=None, file_path=None):
        if self.fail_with:
            raise self.fail_with
        self.calls.append((method, dict(fields), file_field))
        return {"ok": True}


class TelegramNotifyTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.video = Path(self.tmp.name) / "reel.mp4"
        self.video.write_bytes(b"x" * 1000)

    def test_tiktok_caption_keeps_the_text_and_limits_hashtags_to_five(self):
        caption = tn.tiktok_caption(CAPTION)
        self.assertTrue(caption.startswith("Tugas numpuk tapi semangat jalan terus."))
        self.assertEqual(caption.split("\n\n")[1].split(), ["#jaukiacademy", "#tugas", "#mahasiswa", "#kuliah", "#deadline"])
        self.assertEqual(tn.tiktok_caption("Tanpa tagar"), "Tanpa tagar")
        self.assertEqual(tn.tiktok_caption("Isi\n\nbukan hanya #tagar saja"), "Isi\n\nbukan hanya #tagar saja")
        self.assertLessEqual(len(tn.tiktok_caption("a" * 5000)), tn.TIKTOK_CAPTION_LIMIT)

    def test_upload_time_is_written_in_indonesian(self):
        self.assertEqual(tn.upload_time_label(MONDAY_11), "Senin, 2 Nov 2026 jam 11:00 WIB")
        self.assertIn("Upload: Senin, 2 Nov 2026 jam 11:00 WIB", tn.intro_text(MONDAY_11))

    def test_video_then_a_separate_copy_ready_caption_are_sent(self):
        call = FakeCall()
        result = tn.send_reel("T", "42", self.video, MONDAY_11, CAPTION, call=call)
        self.assertEqual([c[0] for c in call.calls], ["sendVideo", "sendMessage"])
        self.assertEqual(call.calls[0][1]["chat_id"], "42")
        self.assertEqual(call.calls[0][2], "video")
        self.assertIn("jam 11:00 WIB", call.calls[0][1]["caption"])
        self.assertTrue(call.calls[1][1]["text"].startswith("Caption TikTok (salin):"))
        self.assertTrue(result["video"])

    def test_oversized_video_is_sent_as_a_link(self):
        call = FakeCall()
        with mock.patch.object(tn, "VIDEO_LIMIT", 10):
            result = tn.send_reel("T", "42", self.video, MONDAY_11, CAPTION, public_url="https://v.example/reel.mp4", call=call)
        self.assertEqual([c[0] for c in call.calls], ["sendMessage", "sendMessage"])
        self.assertIn("https://v.example/reel.mp4", call.calls[0][1]["text"])
        self.assertFalse(result["video"])

    def test_missing_settings_or_file_fail_clearly(self):
        with self.assertRaises(tn.TelegramError):
            tn.send_reel("", "42", self.video, MONDAY_11, CAPTION, call=FakeCall())
        with self.assertRaises(tn.TelegramError):
            tn.send_reel("T", "", self.video, MONDAY_11, CAPTION, call=FakeCall())
        with self.assertRaises(tn.TelegramError):
            tn.send_reel("T", "42", Path(self.tmp.name) / "tidak-ada.mp4", MONDAY_11, CAPTION, call=FakeCall())

    def test_the_bot_token_never_appears_in_errors(self):
        with mock.patch("urllib.request.urlopen", side_effect=urllib.error.URLError("https://api.telegram.org/botSECRET-TOKEN/sendVideo")):
            with self.assertRaises(tn.TelegramError) as caught:
                tn.send_reel("SECRET-TOKEN", "42", self.video, MONDAY_11, CAPTION)
        self.assertNotIn("SECRET-TOKEN", str(caught.exception))


class ReelsFlowTests(unittest.TestCase):
    """Render dan posting dipalsukan; yang diuji adalah kapan dan apa yang dikirim ke Telegram."""

    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp())
        self.addCleanup(lambda: __import__("shutil").rmtree(self.tmp, ignore_errors=True))
        out = self.tmp / "output"
        out.mkdir()
        slots = self.tmp / "slots.json"
        slots.write_text(json.dumps([{"id": "s1", "day": 1, "time": "11:00", "label": "Reels Jauki", "active": True}]), encoding="utf-8")
        patches = [
            mock.patch.object(gc, "OUT_DIR", out), mock.patch.object(gc, "SLOT_STATE_FILE", self.tmp / "slot_state.json"),
            mock.patch.object(gc, "HISTORY_FILE", self.tmp / "history.json"), mock.patch.object(gc, "COPY_BANK_FILE", self.tmp / "bank.json"),
            mock.patch.dict(os.environ, {"REELS_SLOTS_FILE": str(slots), "TELEGRAM_BOT_TOKEN": "T", "TELEGRAM_CHAT_ID": "42",
                                          "PUBLIC_VIDEO_BASE_URL": "https://v.example"}),
            mock.patch.object(gc, "plan_render", lambda cfg, item, history, rng: {"mood": "jj", "music_rel": "jj/x.mp3", "track": None, "grade": "vivid",
                "vignette": False, "music_offset": 0.0, "music_loop": True,
                "seg": {"name": "a.mp4", "size": 1, "src_dur": 300.0, "start": 10.0, "span": 20.0, "duration": 20, "speed": 1.0}}),
            mock.patch.object(gc, "render_plan", self.fake_render), mock.patch.object(gc, "post_video", self.fake_post),
        ]
        for patch in patches:
            patch.start()
            self.addCleanup(patch.stop)
        os.environ.pop("REELS_LEAD_MINUTES", None)
        gc.save_bank([{"id": "t1", "topic": "x", "overlay": "o " * 25, "caption": "Tugas numpuk tapi semangat jalan terus.",
                       "hashtags": ["#tugas", "#mahasiswa", "#kuliah", "#deadline", "#humor", "#relatable"], "mood": "jj", "used_at": None, "used_by": None}])
        gc.save_history([])
        self.cfg = {"music_volume": 0.25, "brand_hashtags": ["#jaukiacademy"], "skip_head_s": 5, "skip_tail_s": 5}
        self.rng = gc.random.Random(1)
        self.posted = []

    def fake_render(self, cfg, plan, item, bank, stamp):
        out = gc.OUT_DIR / f"reel_{stamp:%Y%m%d_%H%M%S}.mp4"
        out.write_bytes(b"x" * 100)
        item["used_at"], item["used_by"] = stamp.isoformat(timespec="seconds"), out.name
        gc.save_bank(bank)
        return out, 20.0, "entry01"

    def fake_post(self, out_path, caption):
        self.posted.append(out_path.name)
        return "MEDIA1"

    def run_at(self, when, send=None):
        sender = send or FakeSender()
        with mock.patch.object(tn, "send_reel", sender):
            gc.run_auto(self.cfg, TZ, self.rng, True, when)
        return sender

    def record(self):
        return list(gc.load_slot_state().values())[0]

    def test_render_starts_a_day_ahead_and_sends_the_video_with_the_upload_time(self):
        self.assertEqual(self.run_at(datetime(2026, 11, 1, 10, 0, tzinfo=TZ)).calls, [])  # 25 jam sebelum: belum waktunya
        sender = self.run_at(datetime(2026, 11, 1, 12, 0, tzinfo=TZ))                       # 23 jam sebelum tayang
        self.assertEqual(len(sender.calls), 1)
        args, kwargs = sender.calls[0]
        self.assertEqual((args[0], args[1]), ("T", "42"))
        self.assertEqual(args[3], MONDAY_11)
        self.assertIn("Jaukiacademy".lower(), args[4].lower())
        self.assertEqual(kwargs["public_url"], "https://v.example/" + args[2].name)
        self.assertEqual(self.record()["status"], "rendered")
        self.assertEqual(self.record()["telegram"], "sent")
        self.assertEqual(self.posted, [])  # Instagram baru tayang di jam slot

    def test_a_sent_video_is_not_sent_again_and_instagram_posts_at_the_slot_time(self):
        self.run_at(datetime(2026, 11, 1, 12, 0, tzinfo=TZ))
        again = self.run_at(datetime(2026, 11, 1, 18, 0, tzinfo=TZ))
        self.assertEqual(again.calls, [])
        after = self.run_at(datetime(2026, 11, 2, 11, 3, tzinfo=TZ))
        self.assertEqual(after.calls, [])
        self.assertEqual(len(self.posted), 1)
        self.assertEqual(self.record()["status"], "posted")

    def test_a_failed_send_keeps_the_render_and_is_retried_until_it_works(self):
        self.run_at(datetime(2026, 11, 1, 12, 0, tzinfo=TZ), FakeSender(fail=True))
        self.assertEqual((self.record()["status"], self.record()["telegram"], self.record()["telegram_attempts"]), ("rendered", "failed", 1))
        retried = self.run_at(datetime(2026, 11, 1, 12, 5, tzinfo=TZ))
        self.assertEqual(len(retried.calls), 1)
        self.assertEqual((self.record()["telegram"], self.record()["telegram_attempts"]), ("sent", 2))
        self.assertEqual(len(gc.load_history()), 1)  # tidak render ulang

    def test_retries_stop_after_five_attempts_and_after_the_slot_time(self):
        self.run_at(datetime(2026, 11, 1, 12, 0, tzinfo=TZ), FakeSender(fail=True))
        for minute in range(5, 40, 5):
            self.run_at(datetime(2026, 11, 1, 12, minute, tzinfo=TZ), FakeSender(fail=True))
        self.assertEqual(self.record()["telegram_attempts"], gc.TELEGRAM_RETRIES)
        self.assertEqual(self.run_at(datetime(2026, 11, 1, 13, 0, tzinfo=TZ)).calls, [])

    def test_without_telegram_settings_the_reel_is_still_rendered_and_later_sent_once_configured(self):
        with mock.patch.dict(os.environ, {"TELEGRAM_BOT_TOKEN": "", "TELEGRAM_CHAT_ID": ""}):
            sender = self.run_at(datetime(2026, 11, 1, 12, 0, tzinfo=TZ))
        self.assertEqual(sender.calls, [])
        self.assertEqual((self.record()["status"], self.record()["telegram"]), ("rendered", "skipped"))
        later = self.run_at(datetime(2026, 11, 1, 12, 5, tzinfo=TZ))
        self.assertEqual(len(later.calls), 1)
        self.assertEqual(self.record()["telegram"], "sent")

    def test_lead_time_defaults_to_one_day_and_can_be_changed(self):
        self.assertEqual(sp.DEFAULT_LEAD_MINUTES, 1440)
        self.assertEqual(gc.reels_lead_minutes(), 1440)
        with mock.patch.dict(os.environ, {"REELS_LEAD_MINUTES": "120"}):
            self.assertEqual(gc.reels_lead_minutes(), 120)
            self.assertEqual(self.run_at(datetime(2026, 11, 1, 12, 0, tzinfo=TZ)).calls, [])
        with mock.patch.dict(os.environ, {"REELS_LEAD_MINUTES": "bukan angka"}):
            self.assertEqual(gc.reels_lead_minutes(), 1440)

    def test_slots_saved_with_the_old_two_hour_lead_are_moved_to_a_day_ahead(self):
        slot = {"id": "s1", "day": 1, "time": "11:00", "lead_minutes": 120, "active": True}
        actions = sp.due_actions([slot], {}, datetime(2026, 11, 1, 12, 0, tzinfo=TZ), TZ, lead_minutes=1440)
        self.assertEqual([a.kind for a in actions], ["render"])
        self.assertEqual(sp.due_actions([slot], {}, datetime(2026, 11, 1, 12, 0, tzinfo=TZ), TZ), [])  # tanpa penggantian: 120 menit


class FakeSender:
    def __init__(self, fail=False):
        self.calls, self.fail = [], fail

    def __call__(self, *args, **kwargs):
        if self.fail:
            raise tn.TelegramError("Telegram menolak sendVideo: uji gagal")
        self.calls.append((args, kwargs))
        return {"video": True, "size": 100}


if __name__ == "__main__":
    unittest.main()
