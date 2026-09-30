import json
import logging
import os
import threading
import time
import uuid
from datetime import datetime, timezone
from pathlib import Path
from urllib import error, request
from urllib import parse


LOGGER = logging.getLogger("office_bridge")
BASE_DIR = Path(__file__).resolve().parent
ENV_FILE = BASE_DIR / ".env"

PARENT_SYSTEM = "jauki-content-bot"

_heartbeat_thread = None
_heartbeat_lock = threading.Lock()


def _read_env_value(name):
    value = os.getenv(name)
    if value:
        return value.strip()

    if not ENV_FILE.exists():
        return ""

    try:
        for raw_line in ENV_FILE.read_text().splitlines():
            line = raw_line.strip()

            if not line or line.startswith("#"):
                continue

            if not line.startswith(name + "="):
                continue

            return line.split("=", 1)[1].strip().strip('"').strip("'")

    except Exception:
        return ""

    return ""


def _config():
    return (
        _read_env_value("OFFICE_BRIDGE_URL"),
        _read_env_value("OFFICE_BRIDGE_TOKEN"),
    )


def enabled():
    url, token = _config()
    return bool(url and token)


def _timestamp():
    return datetime.now(timezone.utc).isoformat()


def _send_event(payload):
    _, token = _config()
    url = office_endpoint("/api/office/events")

    if not url or not token:
        return False

    try:
        body = json.dumps(payload).encode("utf-8")

        req = request.Request(
            url,
            data=body,
            method="POST",
            headers={
                "Authorization": f"Bearer {token}",
                "X-Office-Bridge-Token": token,
                "Content-Type": "application/json",
                "Accept": "application/json",
                "User-Agent": "JaukiContentBot-LivingOffice/1.0",
            },
        )

        with request.urlopen(req, timeout=3) as response:
            return 200 <= response.status < 300

    except error.HTTPError as exc:
        LOGGER.warning(
            "Living Office Bridge HTTP %s",
            exc.code,
        )

    except Exception as exc:
        LOGGER.warning(
            "Living Office Bridge unavailable: %s",
            type(exc).__name__,
        )

    return False


def _dispatch(payload):
    def runner():
        _send_event(payload)

    thread = threading.Thread(
        target=runner,
        daemon=True,
        name="living-office-event",
    )
    thread.start()


def emit_event(event, **fields):
    payload = {
        "event": event,
        "parent_system": PARENT_SYSTEM,
        "timestamp": _timestamp(),
        **fields,
    }

    if not payload.get("event_id"):
        payload["event_id"] = str(uuid.uuid4())

    _dispatch(payload)


def emit_status(
    agent_id,
    status,
    activity=None,
    progress=None,
):
    payload = {
        "agent_id": agent_id,
        "status": status,
    }

    if activity is not None:
        payload["activity"] = activity

    if progress is not None:
        payload["progress"] = progress

    emit_event(
        "agent.status.changed",
        **payload,
    )


def emit_task_started(
    agent_id,
    title,
    task_type,
    target=None,
    activity=None,
    task_id=None,
):
    task_id = task_id or str(uuid.uuid4())

    task = {
        "id": task_id,
        "type": task_type,
        "title": title,
    }

    if target is not None:
        task["target"] = target

    payload = {
        "agent_id": agent_id,
        "task": task,
    }

    if activity is not None:
        payload["activity"] = activity

    emit_event(
        "task.started",
        **payload,
    )

    return task_id


def emit_progress(
    agent_id,
    progress,
    activity=None,
    task_id=None,
):
    payload = {
        "agent_id": agent_id,
        "progress": progress,
    }

    if activity is not None:
        payload["activity"] = activity

    if task_id is not None:
        payload["task_id"] = task_id

    emit_event(
        "task.progress",
        **payload,
    )


def emit_completed(
    agent_id,
    result=None,
    task_id=None,
):
    payload = {
        "agent_id": agent_id,
        "progress": 100,
    }

    if result is not None:
        payload["result"] = result

    if task_id is not None:
        payload["task_id"] = task_id

    emit_event(
        "task.completed",
        **payload,
    )


def emit_failed(
    agent_id,
    message,
    task_id=None,
):
    payload = {
        "agent_id": agent_id,
        "error": str(message)[:1000],
    }

    if task_id is not None:
        payload["task_id"] = task_id

    emit_event(
        "task.failed",
        **payload,
    )


def emit_heartbeat():
    emit_event(
        "system.heartbeat",
    )


def start_heartbeat_loop(interval=60):
    global _heartbeat_thread

    with _heartbeat_lock:
        if (
            _heartbeat_thread is not None
            and _heartbeat_thread.is_alive()
        ):
            return

        def heartbeat_worker():
            while True:
                emit_heartbeat()
                time.sleep(max(30, interval))

        _heartbeat_thread = threading.Thread(
            target=heartbeat_worker,
            daemon=True,
            name="living-office-heartbeat",
        )

        _heartbeat_thread.start()

def schedule_idle(
    agent_id,
    activity="Waiting for tasks",
    delay=8,
):
    """
    Non-blocking transition back to idle.
    Never blocks Telegram handlers.
    """

    def worker():
        try:
            time.sleep(max(0, delay))

            emit_status(
                agent_id,
                "idle",
                activity,
                0,
            )

        except Exception as exc:
            LOGGER.warning(
                "Living Office idle scheduler failed: %s",
                type(exc).__name__,
            )

    thread = threading.Thread(
        target=worker,
        daemon=True,
        name=f"office-idle-{agent_id}",
    )

    thread.start()

# =========================================================
# LIVING OFFICE V2.1 COMMAND BRIDGE COMPATIBILITY
# =========================================================

# Public read-only configuration used by office_command_worker.
# Values still retain the existing .env fallback behavior.
OFFICE_BRIDGE_URL = _read_env_value("OFFICE_BRIDGE_URL")
OFFICE_BRIDGE_TOKEN = _read_env_value("OFFICE_BRIDGE_TOKEN")


def office_endpoint(path):
    """
    Build a same-origin Living Office API endpoint safely.

    OFFICE_BRIDGE_URL may currently point directly to
    /api/office/events. This helper keeps the same scheme/host
    and replaces only the path.
    """

    base = OFFICE_BRIDGE_URL or _read_env_value(
        "OFFICE_BRIDGE_URL"
    )

    if not base:
        return None

    try:
        parsed = parse.urlsplit(base)

        if (
            parsed.scheme not in ("http", "https")
            or not parsed.netloc
            or parsed.username
            or parsed.password
        ):
            LOGGER.warning(
                "Invalid OFFICE_BRIDGE_URL"
            )
            return None

        return parse.urlunsplit(
            (
                parsed.scheme,
                parsed.netloc,
                "/" + str(path).lstrip("/"),
                "",
                "",
            )
        )

    except Exception:
        return None


def emit_event_sync(event, **fields):
    """
    Synchronous event sender for command sequencing.

    Existing Telegram workflows continue using emit_event()
    asynchronously. This helper is only for operations where
    task.started must arrive before progress/completion.
    """

    payload = {
        "event": event,
        "parent_system": PARENT_SYSTEM,
        "timestamp": _timestamp(),
        **fields,
    }

    if not payload.get("event_id"):
        payload["event_id"] = str(uuid.uuid4())

    try:
        return bool(_send_event(payload))
    except Exception as exc:
        LOGGER.warning(
            "Living Office synchronous event failed: %s",
            type(exc).__name__,
        )
        return False

