import unittest
from unittest.mock import Mock, patch

import office_bridge
import office_command_worker as worker


class FakeClient:
    def __init__(self, commands=None):
        self.commands = list(commands or [])
        self.updates = []
        self.claim_calls = 0

    def claim(self):
        self.claim_calls += 1
        return self.commands.pop(0) if self.commands else None

    def update(self, command_id, status, error=None):
        self.updates.append((command_id, status, error))


class FakeThreadsCore:
    def __init__(self, generated="Generated Threads copy", state=None, publication="media-123"):
        self.generated = generated
        self.state = state
        self.publication = publication
        self.saved_states = []
        self.published_text = None

    def generate_threads_content(self):
        if isinstance(self.generated, Exception):
            raise self.generated
        return self.generated

    def load_state(self):
        return self.state

    def save_state(self, state):
        self.saved_states.append(state)

    def publish_threads_text(self, text):
        if isinstance(self.publication, Exception):
            raise self.publication
        self.published_text = text
        return self.publication


class OfficeCommandWorkerTests(unittest.TestCase):
    def setUp(self):
        self.events = []
        self.event_patch = patch.object(
            office_bridge,
            "emit_event_sync",
            side_effect=lambda event, **fields: self.events.append((event, fields)) or True,
        )
        self.status_patch = patch.object(office_bridge, "emit_status")
        self.event_patch.start()
        self.status = self.status_patch.start()
        self.addCleanup(self.event_patch.stop)
        self.addCleanup(self.status_patch.stop)

    def command(self, action="generate_threads", agent_id="jauki-threads"):
        return {"id": "command-1", "agent_id": agent_id, "action": action, "payload": {}}

    def test_empty_queue_does_nothing(self):
        client = FakeClient()

        self.assertFalse(worker.poll_once(client, idle_delay=0))
        self.assertEqual(client.claim_calls, 1)
        self.assertEqual(client.updates, [])

    def test_generate_threads_dispatch_and_successful_lifecycle(self):
        client = FakeClient()
        core = FakeThreadsCore()

        with patch.object(worker, "_threads_core", return_value=core):
            self.assertTrue(worker.process_command(self.command(), client, idle_delay=0))

        self.assertEqual([update[1] for update in client.updates], ["running", "completed"])
        self.assertEqual(
            [event for event, _ in self.events],
            ["task.started", "task.progress", "task.progress", "content.preview_ready", "task.completed"],
        )
        task_id = self.events[0][1]["task"]["id"]
        self.assertEqual(self.events[1][1]["task_id"], task_id)
        self.assertEqual(self.events[-1][1]["task_id"], task_id)
        self.assertEqual(core.saved_states[0]["text"], "Generated Threads copy")
        self.assertFalse(core.saved_states[0]["uploaded"])
        self.status.assert_called_once_with("jauki-threads", "idle", "Waiting for Threads task", 0)

    def test_publish_last_dispatch_and_successful_lifecycle(self):
        client = FakeClient()
        core = FakeThreadsCore(
            state={"text": "Saved Threads copy", "uploaded": False, "generated_at": "earlier"},
            publication={"id": "media-456", "permalink": "https://www.threads.net/example"},
        )

        with patch.object(worker, "_threads_core", return_value=core):
            self.assertTrue(worker.process_command(self.command("publish_last"), client, idle_delay=0))

        self.assertEqual([update[1] for update in client.updates], ["running", "completed"])
        self.assertEqual(core.published_text, "Saved Threads copy")
        self.assertTrue(core.saved_states[0]["uploaded"])
        self.assertEqual(core.saved_states[0]["media_id"], "media-456")
        published = next(fields for event, fields in self.events if event == "content.published")
        self.assertEqual(published["content"]["external_id"], "media-456")
        self.assertEqual(published["content"]["public_url"], "https://www.threads.net/example")

    def test_unknown_action_is_rejected_without_dynamic_execution(self):
        client = FakeClient()

        with patch.object(worker, "_threads_core") as threads_core:
            self.assertFalse(worker.process_command(self.command("__import__('os').system('id')"), client, idle_delay=0))

        threads_core.assert_not_called()
        self.assertEqual([update[1] for update in client.updates], ["running", "failed"])
        self.assertIn("not allowlisted", client.updates[-1][2])
        self.assertEqual(self.events, [])

    def test_execution_failure_reports_same_task_id_and_failed_command(self):
        client = FakeClient()
        core = FakeThreadsCore(generated=RuntimeError("generator unavailable"))

        with patch.object(worker, "_threads_core", return_value=core):
            self.assertFalse(worker.process_command(self.command(), client, idle_delay=0))

        self.assertEqual([update[1] for update in client.updates], ["running", "failed"])
        self.assertEqual(self.events[-1][0], "task.failed")
        self.assertEqual(self.events[-1][1]["task_id"], self.events[0][1]["task"]["id"])
        self.assertEqual(self.events[-1][1]["error"], "generator unavailable")

    def test_publish_validation_failure_marks_command_failed_without_starting_task(self):
        client = FakeClient()
        core = FakeThreadsCore(state={"text": "", "uploaded": False})

        with patch.object(worker, "_threads_core", return_value=core):
            self.assertFalse(worker.process_command(self.command("publish_last"), client, idle_delay=0))

        self.assertEqual([update[1] for update in client.updates], ["running", "failed"])
        self.assertEqual(self.events, [])

    def test_claim_request_filters_out_article_commands(self):
        client = worker.OfficeCommandClient()
        with patch.object(client, "_request", return_value={"command": None}) as request:
            self.assertIsNone(client.claim())

        request.assert_called_once_with(
            "POST", "/api/office/commands/claim", {"agent_id": "jauki-threads"}
        )


class OfficeBridgeTests(unittest.TestCase):
    def test_event_payload_adds_uuid_and_preserves_explicit_event_id(self):
        generated = office_bridge._event_payload({"event": "task.progress"})
        explicit = office_bridge._event_payload({"event": "task.progress", "event_id": "retry-id"})

        self.assertTrue(generated["event_id"])
        self.assertEqual(explicit["event_id"], "retry-id")

    def test_same_origin_endpoint_replaces_configured_event_path(self):
        with patch.object(
            office_bridge,
            "OFFICE_BRIDGE_URL",
            "https://jauharfauzi.my.id/api/office/events",
        ):
            self.assertEqual(
                office_bridge.office_endpoint("/api/office/commands/claim"),
                "https://jauharfauzi.my.id/api/office/commands/claim",
            )

    def test_synchronous_event_returns_false_instead_of_raising(self):
        with patch.object(office_bridge, "_send_event_sync", side_effect=RuntimeError("network")):
            self.assertFalse(office_bridge.emit_event_sync("task.started"))


if __name__ == "__main__":
    unittest.main()
