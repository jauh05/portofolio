"""Threads-only Living AI Office command consumer for JaukiContentBot."""

import json
import logging
import threading
import time
import urllib.parse
import urllib.request
import uuid
from datetime import datetime, timezone
from typing import Any, Dict, Optional

import office_bridge


logger = logging.getLogger(__name__)

AGENT_ID = "jauki-threads"
POLL_INTERVAL_SECONDS = 5
IDLE_DELAY_SECONDS = 8
HTTP_TIMEOUT_SECONDS = 5.0

_worker_lock = threading.Lock()
_worker_thread: Optional[threading.Thread] = None


class OfficeCommandClient:
    """Minimal same-origin HTTP client for the command lifecycle endpoints."""

    def _request(self, method: str, path: str, payload: Dict[str, Any]) -> Dict[str, Any]:
        url = office_bridge.office_endpoint(path)
        token = office_bridge.OFFICE_BRIDGE_TOKEN
        if not url or not token:
            raise RuntimeError("Office Bridge URL or token is not configured")

        request = urllib.request.Request(
            url,
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Authorization": "Bearer " + token,
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            method=method,
        )
        with urllib.request.urlopen(request, timeout=HTTP_TIMEOUT_SECONDS) as response:
            body = response.read()
            return json.loads(body.decode("utf-8")) if body else {}

    def claim(self) -> Optional[Dict[str, Any]]:
        response = self._request("POST", "/api/office/commands/claim", {"agent_id": AGENT_ID})
        command = response.get("command")
        return command if isinstance(command, dict) else None

    def update(self, command_id: str, status: str, error: Optional[str] = None) -> None:
        payload: Dict[str, Any] = {"status": status}
        if error is not None:
            payload["error"] = error
        safe_id = urllib.parse.quote(str(command_id), safe="")
        self._request("PATCH", "/api/office/commands/" + safe_id, payload)


def _threads_core():
    import threads_posting

    return threads_posting


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def _safe_error(error: Exception) -> str:
    message = " ".join(str(error).split()) or error.__class__.__name__
    token = office_bridge.OFFICE_BRIDGE_TOKEN
    if token:
        message = message.replace(token, "[redacted]")
    return message[:500]


def _emit_required(event: str, **fields: Any) -> None:
    fields.setdefault("agent_id", AGENT_ID)
    fields.setdefault("parent_system", office_bridge.PARENT_SYSTEM)
    if not office_bridge.emit_event_sync(event, **fields):
        raise RuntimeError("Could not report " + event + " to Living AI Office")


def _start_task(context: Dict[str, Any], title: str, task_type: str, activity: str) -> None:
    task_id = str(uuid.uuid4())
    context["task_id"] = task_id
    _emit_required(
        "task.started",
        activity=activity,
        task={"id": task_id, "title": title, "type": task_type, "target": "threads"},
    )
    context["task_started"] = True


def _progress(context: Dict[str, Any], progress: int, activity: str) -> None:
    _emit_required("task.progress", task_id=context["task_id"], progress=progress, activity=activity)


def handle_generate_threads(context: Dict[str, Any]) -> None:
    core = _threads_core()
    _start_task(context, "Generate Threads", "threads_generation", "Preparing Threads content")
    _progress(context, 20, "Selecting content strategy")

    generated = core.generate_threads_content()
    if not isinstance(generated, str) or not generated.strip():
        raise RuntimeError("Threads generator returned empty content")

    _progress(context, 80, "Saving Threads draft")
    core.save_state({"text": generated, "uploaded": False, "generated_at": _now_iso()})
    _emit_required(
        "content.preview_ready",
        activity="Threads draft ready",
        content={
            "platform": "threads",
            "content_type": "thread",
            "title": "Threads post",
            "text": generated,
        },
    )
    _emit_required(
        "task.completed",
        task_id=context["task_id"],
        progress=100,
        result={"title": "Threads draft ready", "stage": "preview_ready"},
    )


def _publication_details(result: Any) -> Dict[str, str]:
    if isinstance(result, dict):
        media_id = result.get("media_id") or result.get("id")
        public_url = result.get("public_url") or result.get("permalink") or result.get("url")
    else:
        media_id = result
        public_url = None

    if media_id is None or not str(media_id).strip():
        raise RuntimeError("Threads publisher did not return a media ID")

    details = {"media_id": str(media_id)}
    if isinstance(public_url, str):
        parsed = urllib.parse.urlsplit(public_url)
        if parsed.scheme in ("http", "https") and parsed.netloc:
            details["public_url"] = public_url
    return details


def handle_publish_threads(context: Dict[str, Any]) -> None:
    core = _threads_core()
    state = core.load_state()
    if not isinstance(state, dict):
        raise RuntimeError("No saved Threads draft is available")
    text = state.get("text")
    if not isinstance(text, str) or not text.strip():
        raise RuntimeError("Saved Threads draft is empty")
    if state.get("uploaded"):
        raise RuntimeError("Saved Threads draft was already published")

    _start_task(context, "Publish Threads", "threads_publish", "Preparing Threads publication")
    _progress(context, 40, "Publishing Threads post")
    publication = _publication_details(core.publish_threads_text(text))

    updated_state = dict(state)
    updated_state.update({
        "uploaded": True,
        "media_id": publication["media_id"],
        "uploaded_at": _now_iso(),
    })
    core.save_state(updated_state)

    content = {
        "platform": "threads",
        "content_type": "thread",
        "title": "Threads post",
        "text": text,
        "external_id": publication["media_id"],
        "media_id": publication["media_id"],
    }
    if "public_url" in publication:
        content["public_url"] = publication["public_url"]
    _emit_required("content.published", activity="Threads post published", content=content)
    _emit_required(
        "task.completed",
        task_id=context["task_id"],
        progress=100,
        result={"title": "Threads post published", "stage": "published"},
    )


DISPATCH = {
    ("jauki-threads", "generate_threads"): handle_generate_threads,
    ("jauki-threads", "publish_last"): handle_publish_threads,
}


def process_command(
    command: Dict[str, Any],
    client: OfficeCommandClient,
    idle_delay: float = IDLE_DELAY_SECONDS,
) -> bool:
    command_id = str(command.get("id") or "")
    if not command_id:
        logger.warning("Ignoring claimed command without an ID")
        return False

    context: Dict[str, Any] = {"task_id": None, "task_started": False}
    client.update(command_id, "running")
    handler = DISPATCH.get((command.get("agent_id"), command.get("action")))

    try:
        if handler is None:
            raise RuntimeError("Command is not allowlisted for the Threads worker")
        handler(context)
    except Exception as error:
        safe_error = _safe_error(error)
        if context["task_started"]:
            if not office_bridge.emit_event_sync(
                "task.failed",
                agent_id=AGENT_ID,
                parent_system=office_bridge.PARENT_SYSTEM,
                task_id=context["task_id"],
                error=safe_error,
            ):
                logger.warning("Could not report task.failed for command %s", command_id)
        try:
            client.update(command_id, "failed", safe_error)
        except Exception as update_error:
            logger.warning("Could not mark command %s failed: %s", command_id, _safe_error(update_error))
        return False

    client.update(command_id, "completed")
    if idle_delay > 0:
        time.sleep(idle_delay)
    office_bridge.emit_status(AGENT_ID, "idle", "Waiting for Threads task", 0)
    return True


def poll_once(client: Optional[OfficeCommandClient] = None, idle_delay: float = IDLE_DELAY_SECONDS) -> bool:
    command_client = client or OfficeCommandClient()
    command = command_client.claim()
    if command is None:
        return False
    process_command(command, command_client, idle_delay=idle_delay)
    return True


def _worker_loop(interval: float) -> None:
    client = OfficeCommandClient()
    while True:
        try:
            poll_once(client)
        except Exception as error:
            logger.warning("Office command poll failed: %s", _safe_error(error))
        time.sleep(interval)


def start_command_worker(interval: float = POLL_INTERVAL_SECONDS) -> Optional[threading.Thread]:
    """Start one daemon command worker inside the existing bot process."""
    global _worker_thread

    if not office_bridge.OFFICE_BRIDGE_URL or not office_bridge.OFFICE_BRIDGE_TOKEN:
        logger.warning("Office command worker not started: bridge URL or token is missing")
        return None

    with _worker_lock:
        if _worker_thread is not None and _worker_thread.is_alive():
            return _worker_thread
        _worker_thread = threading.Thread(
            target=_worker_loop,
            args=(interval,),
            name="office-threads-command-worker",
            daemon=True,
        )
        _worker_thread.start()
        return _worker_thread
