"""The preview_visual worker command must be preview-only and fail closed."""

import contextlib
import json
import socket
import sys
import unittest
import urllib.request
from pathlib import Path
from unittest.mock import Mock, patch

import office_bridge
import office_command_worker
import office_social_worker as worker
import visual_preview_bridge as bridge

TEMPLATE_ID = r"^T(0[1-9]|1[0-9]|2[0-7])$"
LIVE_KEYS = (
    ("jauki-social", "generate_feed"),
    ("jauki-social", "generate_story"),
    ("jauki-social", "publish_last"),
)


class FakeClient:
    def __init__(self):
        self.updates = []

    def update(self, command_id, status, error=None):
        self.updates.append((command_id, status, error))


class TrapModule:
    """Stands in for the runtime ``bot`` module: any attribute access is a failure."""

    def __getattr__(self, name):
        raise AssertionError(f"bot.{name} reached from preview path")


def tripwire(name):
    return Mock(side_effect=AssertionError(name + " must not be called"))


def command(payload, action="preview_visual", agent_id="jauki-social"):
    return {"id": "cmd-1", "agent_id": agent_id, "action": action, "payload": payload}


class PreviewSafetyMixin:
    def setUp(self):
        stack = contextlib.ExitStack()
        self.addCleanup(stack.close)
        self.live = {key: tripwire(key[1]) for key in LIVE_KEYS}
        stack.enter_context(patch.dict(worker.DISPATCH, self.live))
        stack.enter_context(patch.dict(sys.modules, {"bot": TrapModule(), "threads_posting": TrapModule()}))
        self.do_generate = stack.enter_context(patch.object(worker, "_do_generate", tripwire("_do_generate")))
        stack.enter_context(patch.object(urllib.request, "urlopen", tripwire("urlopen")))
        stack.enter_context(patch.object(socket.socket, "connect", tripwire("socket.connect")))
        stack.enter_context(patch.object(office_bridge, "emit_event_sync", tripwire("emit_event_sync")))
        stack.enter_context(patch.object(office_bridge, "emit_event", tripwire("emit_event")))
        stack.enter_context(patch.object(office_bridge, "emit_status", tripwire("emit_status")))
        stack.enter_context(patch.object(worker.time, "sleep", tripwire("sleep")))
        self.results = []
        original = bridge.run_preview_visual
        stack.enter_context(patch.object(
            bridge, "run_preview_visual",
            side_effect=lambda cmd: self.results.append(original(cmd)) or self.results[-1],
        ))

    def dispatch(self, cmd):
        client = FakeClient()
        ok = worker.process_command(cmd, client, idle_delay=0)
        return ok, client

    def assert_no_live_dispatch(self):
        for spy in self.live.values():
            spy.assert_not_called()
        self.do_generate.assert_not_called()


class PreviewBridgeBrandTests(PreviewSafetyMixin, unittest.TestCase):
    def test_jauki_feed_portrait_through_worker_dispatcher(self):
        ok, client = self.dispatch(command({
            "brand": "Jauki", "topic": "Cara Memilih Jurnal yang Tepat",
            "output_mode": "feed_portrait", "dry_run": True,
        }))
        self.assertTrue(ok)
        self.assertEqual([u[1] for u in client.updates], ["running", "completed"])
        result = self.results[-1]
        self.assertTrue(result["ok"])
        self.assertTrue(result["dry_run"])
        self.assertFalse(result["provider_called"])
        self.assertEqual(result["brand"], "jauki")
        self.assertRegex(result["template_id"], TEMPLATE_ID)
        self.assertTrue(result["template_family"])
        self.assertEqual(result["output_mode"], "feed_portrait")
        self.assertEqual(result["primary_palette"], "#173D26")
        self.assertEqual(result["headline_font"], "Baloo 2")
        self.assertIn("Cara Memilih Jurnal", result["compiled_prompt"])
        self.assertIn("4:5", result["compiled_prompt"])
        self.assertEqual(result["provider_payload_preview"], {"model": "meta/muse-image", "stream": True})
        self.assertIsInstance(result["variation"], dict)
        self.assert_no_live_dispatch()

    def test_kauiz_story_through_worker_dispatcher(self):
        ok, client = self.dispatch(command({
            "brand": "Kauiz", "topic": "Fokus Satu Tugas dalam Satu Waktu",
            "output_mode": "story", "dry_run": True,
        }))
        self.assertTrue(ok)
        self.assertEqual([u[1] for u in client.updates], ["running", "completed"])
        result = self.results[-1]
        self.assertRegex(result["template_id"], TEMPLATE_ID)
        self.assertEqual(result["output_mode"], "story")
        self.assertEqual(result["primary_palette"], "#1F49E7")
        self.assertEqual(result["headline_font"], "League Spartan")
        self.assertIn("9:16", result["compiled_prompt"])
        self.assertNotIn("#173D26", result["compiled_prompt"])
        self.assertTrue(result["dry_run"])
        self.assertFalse(result["provider_called"])
        self.assert_no_live_dispatch()

    def test_missing_history_is_reported_not_fabricated(self):
        self.dispatch(command({"brand": "jauki", "topic": "Jurnal", "content_type": "feed", "dry_run": True}))
        result = self.results[-1]
        self.assertFalse(result["history_supplied"])
        self.assertTrue(result["limitations"])
        self.assertEqual(result["analysis_input"], {"topic": "Jurnal"})

    def test_supplied_history_is_used(self):
        history = [{"template_id": "T01"}, {"template_id": "T02"}, {"template_id": "T03"}]
        self.dispatch(command({
            "brand": "jauki", "topic": "Tips belajar", "pattern": "tips",
            "content_type": "feed", "history": history, "seed": 3, "dry_run": True,
        }))
        result = self.results[-1]
        self.assertTrue(result["history_supplied"])
        self.assertEqual(result["limitations"], [])
        self.assertNotIn(result["template_id"], {"T01", "T02", "T03"})

    def test_compact_message_and_no_secret_exposure(self):
        with patch.object(office_bridge, "OFFICE_BRIDGE_TOKEN", "super-secret-token"):
            self.dispatch(command({"brand": "jauki", "topic": "Jurnal", "output_mode": "feed_portrait", "dry_run": True}))
        result = self.results[-1]
        message = bridge.format_preview_message(result)
        self.assertIn("dry run", message)
        self.assertIn("provider not called", message)
        self.assertNotIn("super-secret-token", json.dumps(result))


class PreviewBridgeNegativeTests(PreviewSafetyMixin, unittest.TestCase):
    def assert_rejected(self, cmd, expected):
        ok, client = self.dispatch(cmd)
        self.assertFalse(ok)
        self.assertEqual([u[1] for u in client.updates], ["running", "failed"])
        self.assertRegex(client.updates[-1][2], expected)
        if self.results:
            self.assertFalse(self.results[-1]["ok"])
            self.assertFalse(self.results[-1]["provider_called"])
        self.assert_no_live_dispatch()

    base = {"brand": "jauki", "topic": "Jurnal", "output_mode": "feed_portrait"}

    def test_dry_run_false_rejected(self):
        self.assert_rejected(command({**self.base, "dry_run": False}), "preview-only")

    def test_dry_run_missing_rejected(self):
        self.assert_rejected(command(dict(self.base)), "requires dry_run=true")

    def test_dry_run_malformed_rejected(self):
        for value in ("true", 1, "yes", None, [True]):
            with self.subTest(value=value):
                self.assert_rejected(command({**self.base, "dry_run": value}), "preview-only")

    def test_unknown_brand_rejected(self):
        self.assert_rejected(command({**self.base, "brand": "unknown", "dry_run": True}), "Unknown visual brand")

    def test_missing_brand_rejected(self):
        self.assert_rejected(command({"topic": "Jurnal", "output_mode": "story", "dry_run": True}), "requires a brand")

    def test_unsupported_output_mode_rejected(self):
        self.assert_rejected(command({**self.base, "output_mode": "reel", "dry_run": True}), "Unsupported visual output mode")
        self.assert_rejected(command({**self.base, "content_type": "thread", "output_mode": None, "dry_run": True}),
                             "Unsupported visual content type")
        self.assert_rejected(command({**self.base, "content_type": "story", "output_mode": "feed_portrait", "dry_run": True}),
                             "Story content")

    def test_article_not_available_on_social_bridge(self):
        self.assert_rejected(command({**self.base, "output_mode": "article", "dry_run": True}), "not implemented")

    def test_malformed_payloads_rejected(self):
        cases = [
            (command(None), "payload must be an object"),
            (command(["dry_run", True]), "payload must be an object"),
            (command("dry_run=true"), "payload must be an object"),
            (command({**self.base, "dry_run": True, "publish": True}), "Unsupported preview_visual fields"),
            (command({**self.base, "dry_run": True, "history": "T01"}), "history must be a list"),
            (command({**self.base, "dry_run": True, "history": ["T01"]}), "history entries"),
            (command({**self.base, "dry_run": True, "seed": True}), "seed must be an integer"),
            (command({**self.base, "dry_run": True, "platform": "threads"}), "Instagram"),
            (command({**self.base, "dry_run": True, "brand": 7}), "requires a brand"),
            (command({**self.base, "dry_run": True, "analysis": "x"}), "analysis must be an object"),
            (command({"brand": "jauki", "output_mode": "story", "dry_run": True}), "Content analysis needs"),
            (command({**self.base, "dry_run": True}, agent_id="jauki-threads"), "only available on the Social worker"),
        ]
        for cmd, expected in cases:
            with self.subTest(expected=expected):
                self.assert_rejected(cmd, expected)

    def test_non_mapping_command_fails_closed_in_bridge(self):
        for value in (None, [], "preview_visual", 5):
            self.assertFalse(bridge.run_preview_visual(value)["ok"])

    def test_bridge_import_failure_fails_closed(self):
        with patch.dict(sys.modules, {"visual_preview_bridge": None}):
            self.assert_rejected(command({**self.base, "dry_run": True}), "bridge unavailable")

    def test_compiler_crash_fails_closed(self):
        with patch.object(worker, "preview_visual_command", side_effect=RuntimeError("boom")):
            self.assert_rejected(command({**self.base, "dry_run": True}), "local compilation")


class ExistingCommandSemanticsTests(unittest.TestCase):
    def setUp(self):
        patcher = patch.object(worker, "office_bridge")
        patcher.start()
        self.addCleanup(patcher.stop)

    def test_generate_feed_story_publish_last_still_dispatch_to_original_handlers(self):
        self.assertIs(worker.DISPATCH[LIVE_KEYS[0]], worker.handle_generate_feed)
        self.assertIs(worker.DISPATCH[LIVE_KEYS[1]], worker.handle_generate_story)
        self.assertIs(worker.DISPATCH[LIVE_KEYS[2]], worker.handle_publish_last)
        self.assertEqual(set(worker.DISPATCH), set(LIVE_KEYS))
        for key in LIVE_KEYS:
            spy = Mock()
            with self.subTest(action=key[1]), patch.dict(worker.DISPATCH, {key: spy}), \
                 patch("visual_preview_bridge.run_preview_visual", tripwire("preview")):
                client = FakeClient()
                self.assertTrue(worker.process_command(
                    {"id": "c", "agent_id": key[0], "action": key[1]}, client, idle_delay=0))
                spy.assert_called_once()
                self.assertEqual([u[1] for u in client.updates], ["running", "completed"])

    def test_unknown_action_still_not_allowlisted(self):
        client = FakeClient()
        self.assertFalse(worker.process_command({"id": "c", "agent_id": "jauki-social", "action": "nope"}, client, idle_delay=0))
        self.assertIn("not allowlisted", client.updates[-1][2])

    def test_threads_worker_does_not_route_preview_visual(self):
        client = FakeClient()
        with patch.object(office_command_worker, "_threads_core", tripwire("threads core")):
            self.assertFalse(office_command_worker.process_command(
                {"id": "c", "agent_id": "jauki-threads", "action": "preview_visual", "payload": {"dry_run": True}},
                client, idle_delay=0))
        self.assertIn("not allowlisted", client.updates[-1][2])


class BridgeStaticAndCliTests(unittest.TestCase):
    def test_bridge_source_has_no_network_provider_or_publish_client(self):
        source = Path(bridge.__file__).read_text(encoding="utf-8")
        for forbidden in ("urllib", "requests", "http.client", "socket", "import bot", "from bot",
                          "office_bridge", "generate_muse_image", "publish_instagram", "save_latest_state"):
            self.assertNotIn(forbidden, source)

    def test_developer_cli_requires_dry_run(self):
        with patch("builtins.print") as printed:
            self.assertEqual(bridge.main(['{"brand": "kauiz", "topic": "Fokus", "output_mode": "story", "dry_run": true}']), 0)
            self.assertEqual(json.loads(printed.call_args[0][0])["primary_palette"], "#1F49E7")
            self.assertEqual(bridge.main(['{"brand": "kauiz", "topic": "Fokus", "output_mode": "story"}']), 1)
            self.assertEqual(bridge.main(["not json"]), 1)
            self.assertEqual(bridge.main([]), 2)


if __name__ == "__main__":
    unittest.main()
