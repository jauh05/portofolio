"""Tes offline untuk office_article_worker. Tidak ada panggilan jaringan; penulis artikel, Muse, dan situs diganti tiruan."""

import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import office_article_worker as worker
import office_bridge
import test_article_content as fixtures

DRAFT = {"success": True, "data": {"id": 7, "slug": "judul", "status": "draft", "url": "https://kauiz.web.id/berita/artikel?slug=judul", "featured_image": "https://kauiz.web.id/v1/article-images/a.jpg"}}
PUBLISHED = {"success": True, "data": {"id": 7, "slug": "judul", "status": "published", "url": "https://kauiz.web.id/berita/artikel?slug=judul"}}


class FakeClient:
    def __init__(self):
        self.updates = []

    def update(self, command_id, status, error=None):
        self.updates.append((command_id, status, error))


class FakeSend:
    def __init__(self, *responses):
        self.responses, self.calls = list(responses), []

    def __call__(self, method, url, headers, body, timeout=60.0):
        if method == "GET" and url.endswith("/articles/0"):  # check_access: 404 berarti token dan jalur API diterima
            self.checks = getattr(self, "checks", 0) + 1
            return getattr(self, "access", (404, {"success": False, "message": "Article not found"}))
        self.calls.append((method, url, body))
        return self.responses.pop(0) if len(self.responses) > 1 else self.responses[0]


class ArticleWorkerTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.settings = worker.Settings({
            "ARTICLE_STATE_FILE": str(Path(self.tmp.name) / "state.json"), "ARTICLE_OUTPUT_DIR": str(Path(self.tmp.name) / "out"),
            "ARTICLE_KAUIZ_TOKEN": "T1", "ARTICLE_JAUKI_TOKEN": "T2", "OPENROUTER_API_KEY": "k",
        })
        self.events = []
        patcher = patch.object(office_bridge, "emit_event_sync", side_effect=lambda event, **fields: self.events.append((event, fields)) or True)
        patcher.start()
        self.addCleanup(patcher.stop)
        self.client = FakeClient()
        self.send = FakeSend((201, DRAFT))
        self.article = {**fixtures.valid(), "model": "m"}

    def command(self, action="generate_article", **payload):
        base = {"content_id": 11, "brand": "Kauiz", "topic": "Jadwal belajar", "brief": "Cara menyusun jadwal"}
        return {"id": "cmd-1", "agent_id": "jauki-article", "action": action, "payload": {**base, **payload}}

    def run_command(self, command, *, cover=True):
        cover_result = (b"jpeg", "KA1", "prompt") if cover is True else cover
        with patch.object(worker.content_writer, "write_article", return_value=self.article) as written, \
                patch.object(worker, "make_cover", side_effect=cover_result if isinstance(cover_result, Exception) else None, return_value=cover_result if not isinstance(cover_result, Exception) else None):
            ok = worker.process_command(command, self.client, self.settings, self.send)
        return ok, written

    def kinds(self):
        return [event for event, _ in self.events]

    def test_generate_writes_a_draft_on_the_brand_site_and_reports_to_the_requesting_card(self):
        ok, written = self.run_command(self.command())
        self.assertTrue(ok)
        self.assertEqual([update[1] for update in self.client.updates], ["running", "completed"])
        self.assertEqual(self.kinds(), ["task.started", "task.progress", "task.progress", "task.progress", "content.preview_ready", "task.completed"])
        method, url, body = self.send.calls[0]
        self.assertEqual((method, url), ("POST", "https://kauiz.web.id/v1/articles"))
        self.assertIn(b'name="featured_image"', body)
        self.assertIn(b'name="status"\r\n\r\ndraft', body)
        content = next(fields for event, fields in self.events if event == "content.preview_ready")["content"]
        self.assertEqual((content["content_id"], content["platform"], content["content_type"], content["external_id"]), (11, "article", "article", "7"))
        self.assertEqual(content["image_url"], DRAFT["data"]["featured_image"])
        self.assertTrue(content["text"].startswith(self.article["excerpt"]))
        state = json.loads(self.settings.state_path.read_text(encoding="utf-8"))
        self.assertEqual(state["articles"]["11"], {"brand": "kauiz", "article_id": 7, "slug": "judul", "title": self.article["title"], "status": "draft", "url": DRAFT["data"]["url"]})
        self.assertTrue((self.settings.output_dir / "kauiz-11.html").exists())

    def test_jauki_commands_go_to_the_jauki_site_with_its_own_token(self):
        self.run_command(self.command(brand="Jauki"))
        self.assertEqual(self.send.calls[0][1], "https://jaukitugas.my.id/api/internal/articles")

    def test_regenerating_the_same_card_updates_the_draft_instead_of_creating_another(self):
        self.send = FakeSend((201, DRAFT), (200, DRAFT))
        self.run_command(self.command())
        self.client.updates.clear()
        self.run_command(self.command())
        self.assertEqual([call[0] for call in self.send.calls], ["POST", "POST"])
        self.assertTrue(self.send.calls[1][1].endswith("/articles/7"))
        self.assertIn(b'name="_method"\r\n\r\nPUT', self.send.calls[1][2])

    def test_a_failed_cover_still_saves_the_article_without_one(self):
        ok, _ = self.run_command(self.command(), cover=RuntimeError("muse down"))
        self.assertTrue(ok)
        self.assertNotIn(b'name="featured_image"', self.send.calls[0][2])
        self.assertIn("tanpa sampul", next(f for e, f in self.events if e == "content.preview_ready")["activity"])

    def test_missing_token_unknown_brand_and_missing_card_fail_before_writing_anything(self):
        self.settings.env = {**self.settings.env, "ARTICLE_KAUIZ_TOKEN": ""}
        for command in (self.command(), self.command(brand="Lain"), self.command(content_id=None)):
            self.client.updates.clear()
            ok, written = self.run_command(command)
            self.assertFalse(ok)
            self.assertEqual(self.client.updates[-1][1], "failed")
            written.assert_not_called()
        self.assertEqual(self.send.calls, [])

    def test_publish_uses_the_draft_linked_to_the_card_and_reports_the_public_url(self):
        self.send = FakeSend((201, DRAFT), (200, PUBLISHED))
        self.run_command(self.command())
        self.events.clear()
        ok, _ = self.run_command(self.command("publish_article"))
        self.assertTrue(ok)
        self.assertEqual(self.send.calls[1][:2], ("POST", "https://kauiz.web.id/v1/articles/7/publish"))
        published = next(fields for event, fields in self.events if event == "content.published")["content"]
        self.assertEqual((published["content_id"], published["public_url"]), (11, PUBLISHED["data"]["url"]))
        self.client.updates.clear()
        ok, _ = self.run_command(self.command("publish_article"))
        self.assertFalse(ok)
        self.assertIn("sudah terbit", self.client.updates[-1][2])

    def test_publish_without_a_draft_fails_clearly_and_does_not_touch_the_site(self):
        ok, _ = self.run_command(self.command("publish_article"))
        self.assertFalse(ok)
        self.assertIn("Belum ada draf", self.client.updates[-1][2])
        self.assertEqual(self.send.calls, [])
        self.assertEqual(self.events, [])

    def test_schedule_sends_jakarta_time_to_the_site(self):
        self.send = FakeSend((201, DRAFT), (200, {"success": True, "data": {"id": 7, "status": "scheduled"}}))
        self.run_command(self.command())
        ok, _ = self.run_command(self.command("schedule_article", scheduled_at="2026-10-29T03:00:00+00:00"))
        self.assertTrue(ok)
        self.assertEqual(json.loads(self.send.calls[1][2]), {"scheduled_at": "2026-10-29 10:00:00"})
        self.assertEqual(json.loads(self.settings.state_path.read_text(encoding="utf-8"))["articles"]["11"]["status"], "scheduled")
        ok, _ = self.run_command(self.command("schedule_article"))
        self.assertFalse(ok)

    def test_other_agents_and_actions_are_rejected(self):
        for command in ({**self.command(), "agent_id": "jauki-threads"}, self.command("__import__('os')")):
            ok, written = self.run_command(command)
            self.assertFalse(ok)
            written.assert_not_called()
        self.assertEqual(self.send.calls, [])

    def test_a_site_that_blocks_the_client_fails_before_the_article_is_written_or_the_cover_is_paid_for(self):
        self.send.access = (403, {})
        ok, written = self.run_command(self.command())
        self.assertFalse(ok)
        self.assertIn("403", self.client.updates[-1][2])
        written.assert_not_called()
        self.assertEqual(self.send.calls, [])
        self.assertEqual(self.send.checks, 1)

    def test_errors_never_contain_the_site_token(self):
        self.send = FakeSend((401, {"success": False}))
        ok, _ = self.run_command(self.command())
        self.assertFalse(ok)
        self.assertNotIn("T1", self.client.updates[-1][2])

    def test_jakarta_timestamp_and_jpeg_size_limit(self):
        self.assertEqual(worker.jakarta_timestamp("2026-10-29T10:00:00", "jauki"), "2026-10-29 10:00:00")
        from PIL import Image
        import os
        noisy = Image.frombytes("RGB", (1600, 900), os.urandom(1600 * 900 * 3))
        self.assertLessEqual(len(worker.encode_jpeg(noisy, 1800)), 1800 * 1024)

    def test_visual_memory_dir_from_worker_env_reaches_the_environment_before_shared_modules_load(self):
        import os
        import sys
        from unittest.mock import patch as env_patch

        env_file = Path(self.tmp.name) / "worker.env"
        env_file.write_text("# komentar\nVISUAL_MEMORY_DIR='/tmp/memori-uji'\nLAIN=1\n", encoding="utf-8")
        with env_patch.dict(os.environ, {}, clear=False):
            os.environ.pop("VISUAL_MEMORY_DIR", None)
            saved_path = list(sys.path)
            try:
                self.assertEqual(worker._bootstrap_paths(env_file), "/tmp/memori-uji")
                self.assertEqual(os.environ["VISUAL_MEMORY_DIR"], "/tmp/memori-uji")
                self.assertIn("/tmp/memori-uji", sys.path)
            finally:
                sys.path[:] = saved_path
                os.environ.pop("VISUAL_MEMORY_DIR", None)

    def cli_args(self, *extra):
        env_file = Path(self.tmp.name) / "worker.env"
        env_file.write_text("ARTICLE_JAUKI_TOKEN=T2\nOPENROUTER_API_KEY=k\nARTICLE_STATE_FILE=%s\nARTICLE_OUTPUT_DIR=%s\n" % (self.settings.state_path, self.settings.output_dir), encoding="utf-8")
        return ["draft", "--brand", "jauki", "--topic", "Tips", "--env-file", str(env_file), *extra]

    def test_cli_draft_checks_the_site_before_writing_or_paying_for_a_cover(self):
        class Blocked:
            def check_access(self):
                raise worker.article_site.SiteError("akses ditolak (403)")

        with patch.object(worker.Settings, "site", return_value=Blocked()), \
                patch.object(worker.content_writer, "write_article", return_value=self.article) as written, \
                patch.object(worker, "make_cover") as cover:
            with self.assertRaises(worker.article_site.SiteError):
                worker.cli(self.cli_args("--live"))
        written.assert_not_called()
        cover.assert_not_called()

    def test_cli_draft_live_creates_a_draft_and_never_publishes(self):
        class Site:
            def __init__(self):
                self.calls = []

            def check_access(self):
                self.calls.append("check")

            def create_draft(self, article, image):
                self.calls.append("create")
                return {"id": 9, "status": "draft", "image_url": "https://img"}

            def publish(self, *_):
                self.calls.append("publish")

        site = Site()
        with patch.object(worker.Settings, "site", return_value=site), \
                patch.object(worker.content_writer, "write_article", return_value=self.article), \
                patch.object(worker, "make_cover", return_value=(b"jpeg", "JA1", "p")):
            self.assertEqual(worker.cli(self.cli_args("--live")), 0)
        self.assertEqual(site.calls, ["check", "create"])

    def test_cli_preview_and_draft_without_live_never_touch_the_site_or_muse(self):
        with patch.object(worker.Settings, "site") as site, patch.object(worker.content_writer, "write_article", return_value=self.article), \
                patch.object(worker, "make_cover") as cover:
            self.assertEqual(worker.cli(["preview", "--brand", "jauki", "--topic", "Tips", "--env-file", str(Path(self.tmp.name) / "x.env")]), 0)
            self.assertEqual(worker.cli(self.cli_args()), 0)
        site.assert_not_called()
        cover.assert_not_called()

    def test_claim_targets_the_article_agent(self):
        calls = []
        api = worker.ArticleCommandClient()
        api._request = lambda method, path, payload: calls.append((method, path, payload)) or {"command": {"id": "1"}}
        self.assertEqual(api.claim(), {"id": "1"})
        self.assertEqual(calls, [("POST", "/api/office/commands/claim", {"agent_id": "jauki-article"})])


if __name__ == "__main__":
    unittest.main()
