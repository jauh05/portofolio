"""Living AI Office command worker for Weekly Content Planner."""

import json
import logging
import threading
import time
import urllib.parse
import urllib.request
import uuid
from typing import Any, Dict, Optional

import office_bridge


logger = logging.getLogger(__name__)

AGENT_ID = "jauki-planner"
POLL_INTERVAL_SECONDS = 5
HTTP_TIMEOUT_SECONDS = 5.0

_worker_lock = threading.Lock()
_worker_thread: Optional[threading.Thread] = None


class OfficeCommandClient:
    def _request(
        self,
        method: str,
        path: str,
        payload: Dict[str, Any],
    ) -> Dict[str, Any]:
        url = office_bridge.office_endpoint(path)
        token = office_bridge.OFFICE_BRIDGE_TOKEN

        if not url or not token:
            raise RuntimeError(
                "Office Bridge URL or token is not configured"
            )

        request = urllib.request.Request(
            url,
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Authorization": "Bearer " + token,
                "Content-Type": "application/json",
                "Accept": "application/json",
                "User-Agent": "JaukiContentBot-LivingOffice/1.0",
            },
            method=method,
        )

        with urllib.request.urlopen(
            request,
            timeout=HTTP_TIMEOUT_SECONDS,
        ) as response:
            body = response.read()
            return json.loads(body.decode("utf-8")) if body else {}

    def claim(self):
        response = self._request(
            "POST",
            "/api/office/commands/claim",
            {"agent_id": AGENT_ID},
        )
        command = response.get("command")
        return command if isinstance(command, dict) else None

    def update(
        self,
        command_id: str,
        status: str,
        error: Optional[str] = None,
    ):
        payload = {"status": status}

        if error is not None:
            payload["error"] = error

        safe_id = urllib.parse.quote(
            str(command_id),
            safe="",
        )

        self._request(
            "PATCH",
            "/api/office/commands/" + safe_id,
            payload,
        )


def _safe_error(exc: Exception) -> str:
    message = " ".join(str(exc).split()) or exc.__class__.__name__

    token = office_bridge.OFFICE_BRIDGE_TOKEN
    if token:
        message = message.replace(token, "[redacted]")

    return message[:500]


def _emit(event: str, **fields):
    fields.setdefault("agent_id", AGENT_ID)
    fields.setdefault(
        "parent_system",
        office_bridge.PARENT_SYSTEM,
    )

    if not office_bridge.emit_event_sync(
        event,
        **fields,
    ):
        raise RuntimeError(
            "Could not report event to Living AI Office: " + event
        )


def run_weekly_analysis():
    import weekly_combined

    task_id = str(uuid.uuid4())

    _emit(
        "task.started",
        activity="Collecting social data from the last 7 days",
        task={
            "id": task_id,
            "title": "Weekly Content Analysis",
            "type": "weekly_analysis",
            "target": "content_strategy",
        },
    )

    try:
        _emit(
            "task.progress",
            task_id=task_id,
            progress=20,
            activity="Collecting Threads and Instagram data",
        )

        report, counts = weekly_combined.create_weekly_report()

        if not isinstance(report, str) or not report.strip():
            raise RuntimeError(
                "Weekly report returned empty content"
            )

        if not isinstance(counts, dict):
            counts = {}

        _emit(
            "task.progress",
            task_id=task_id,
            progress=90,
            activity="Finalizing weekly recommendations",
        )

        threads = int(counts.get("threads", 0) or 0)
        feed = int(counts.get("feed", 0) or 0)
        story = int(counts.get("story", 0) or 0)

        title = (
            f"Weekly analysis · "
            f"{threads} Threads · "
            f"{feed} Feed · "
            f"{story} Story"
        )

        _emit(
            "task.completed",
            task_id=task_id,
            progress=100,
            activity="Weekly content analysis completed",
            result={
                "title": title,
                "report": report,
                "counts": {
                    "threads": threads,
                    "feed": feed,
                    "story": story,
                },
            },
        )

    except Exception as exc:
        error = _safe_error(exc)

        try:
            _emit(
                "task.failed",
                task_id=task_id,
                error=error,
                activity="Weekly content analysis failed",
            )
        except Exception:
            logger.exception(
                "Could not report Weekly Planner failure"
            )

        raise


HANDLERS = {
    "run_weekly_analysis": run_weekly_analysis,
}


def _execute(
    client: OfficeCommandClient,
    command: Dict[str, Any],
):
    command_id = str(command.get("id") or "")
    action = str(command.get("action") or "")

    if not command_id:
        return

    handler = HANDLERS.get(action)

    if handler is None:
        client.update(
            command_id,
            "failed",
            "Unsupported Weekly Planner action: " + action,
        )
        return

    try:
        client.update(command_id, "running")
        handler()
        client.update(command_id, "completed")

    except Exception as exc:
        error = _safe_error(exc)

        logger.exception(
            "Weekly Planner command failed: %s",
            error,
        )

        try:
            client.update(
                command_id,
                "failed",
                error,
            )
        except Exception:
            logger.exception(
                "Could not mark Weekly Planner command failed"
            )


def _loop():
    client = OfficeCommandClient()

    while True:
        try:
            command = client.claim()

            if command:
                _execute(
                    client,
                    command,
                )

        except Exception as exc:
            logger.warning(
                "Weekly Planner poll failed: %s",
                _safe_error(exc),
            )

        time.sleep(POLL_INTERVAL_SECONDS)


def start_weekly_worker():
    global _worker_thread

    if not office_bridge.enabled():
        return None

    with _worker_lock:
        if (
            _worker_thread is not None
            and _worker_thread.is_alive()
        ):
            return _worker_thread

        _worker_thread = threading.Thread(
            target=_loop,
            name="living-office-weekly-planner",
            daemon=True,
        )
        _worker_thread.start()

    return _worker_thread
