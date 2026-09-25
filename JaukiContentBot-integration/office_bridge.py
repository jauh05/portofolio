import os
import json
import logging
import urllib.request
import urllib.parse
import threading
import uuid
from typing import Optional, Dict, Any

logger = logging.getLogger(__name__)

OFFICE_BRIDGE_URL = os.getenv("OFFICE_BRIDGE_URL")
OFFICE_BRIDGE_TOKEN = os.getenv("OFFICE_BRIDGE_TOKEN")
PARENT_SYSTEM = "jauki-content-bot"

def office_endpoint(path: str) -> Optional[str]:
    """Build an API URL on the configured bridge origin, regardless of its path."""
    if not OFFICE_BRIDGE_URL:
        return None

    parsed = urllib.parse.urlsplit(OFFICE_BRIDGE_URL)
    if parsed.scheme not in ("http", "https") or not parsed.netloc or parsed.username or parsed.password:
        logger.warning("OFFICE_BRIDGE_URL must be an HTTP(S) URL without embedded credentials")
        return None

    return urllib.parse.urlunsplit((parsed.scheme, parsed.netloc, "/" + path.lstrip("/"), "", ""))


def _event_payload(payload: Dict[str, Any]) -> Dict[str, Any]:
    event_payload = dict(payload)
    event_payload.setdefault("event_id", str(uuid.uuid4()))
    return event_payload


def _send_event_sync(payload: Dict[str, Any]) -> bool:
    if not OFFICE_BRIDGE_URL or not OFFICE_BRIDGE_TOKEN:
        return False

    url = office_endpoint("/api/office/events")
    if not url:
        return False
    headers = {
        "Authorization": f"Bearer {OFFICE_BRIDGE_TOKEN}",
        "Content-Type": "application/json"
    }
    data = json.dumps(payload).encode('utf-8')
    
    req = urllib.request.Request(url, data=data, headers=headers, method="POST")
    try:
        # Short timeout (3s) ensures we don't hang the bot if bridge is slow or offline
        with urllib.request.urlopen(req, timeout=3.0) as response:
            if response.status >= 400:
                logger.warning("Office Bridge API returned %s", response.status)
                return False
            return True
    except Exception as e:
        # Completely swallow exceptions (SSL error, timeout, offline, etc.)
        # so the bot workflow NEVER fails due to the bridge.
        logger.warning("Office Bridge integration failed: %s", e)
        return False


def emit_event_sync(event: str, **fields: Any) -> bool:
    """Send one event in sequence, returning False instead of raising on failure."""
    try:
        return _send_event_sync(_event_payload({"event": event, **fields}))
    except Exception as error:
        logger.warning("Office Bridge synchronous event failed: %s", error)
        return False

def _fire_and_forget(payload: Dict[str, Any]):
    """Runs the HTTP request in a background thread to prevent blocking main bot workflow."""
    if not OFFICE_BRIDGE_URL or not OFFICE_BRIDGE_TOKEN:
        return
    event_payload = _event_payload(payload)
    threading.Thread(target=_send_event_sync, args=(event_payload,), daemon=True).start()

def emit_status(agent_id: str, status: str, activity: Optional[str] = None, progress: Optional[int] = None):
    payload = {
        "event": "agent.status.changed",
        "agent_id": agent_id,
        "parent_system": PARENT_SYSTEM,
        "status": status,
    }
    if activity is not None:
        payload["activity"] = activity
    if progress is not None:
        payload["progress"] = progress
    _fire_and_forget(payload)

def emit_task_started(agent_id: str, task_id: str, task_type: str, title: str, target: Optional[str] = None, event_id: Optional[str] = None):
    payload = {
        "event": "task.started",
        "agent_id": agent_id,
        "parent_system": PARENT_SYSTEM,
        "task": {
            "id": task_id,
            "type": task_type,
            "title": title,
        }
    }
    if target:
        payload["task"]["target"] = target
    if event_id is not None:
        payload["event_id"] = event_id
    _fire_and_forget(payload)

def emit_progress(agent_id: str, progress: int, activity: Optional[str] = None, task_id: Optional[str] = None, event_id: Optional[str] = None):
    payload = {
        "event": "task.progress",
        "agent_id": agent_id,
        "parent_system": PARENT_SYSTEM,
        "progress": progress
    }
    if activity is not None:
        payload["activity"] = activity
    if task_id is not None:
        payload["task_id"] = task_id
    if event_id is not None:
        payload["event_id"] = event_id
    _fire_and_forget(payload)

def emit_completed(agent_id: str, result_title: str, result_url: Optional[str] = None, task_id: Optional[str] = None, event_id: Optional[str] = None):
    payload = {
        "event": "task.completed",
        "agent_id": agent_id,
        "parent_system": PARENT_SYSTEM,
        "progress": 100,
        "result": {
            "title": result_title,
        }
    }
    if result_url:
        payload["result"]["url"] = result_url
    if task_id is not None:
        payload["task_id"] = task_id
    if event_id is not None:
        payload["event_id"] = event_id
    _fire_and_forget(payload)

def emit_failed(agent_id: str, error: str, task_id: Optional[str] = None, event_id: Optional[str] = None):
    payload = {
        "event": "task.failed",
        "agent_id": agent_id,
        "parent_system": PARENT_SYSTEM,
        "error": error
    }
    if task_id is not None:
        payload["task_id"] = task_id
    if event_id is not None:
        payload["event_id"] = event_id
    _fire_and_forget(payload)

def emit_heartbeat():
    payload = {
        "event": "system.heartbeat",
        "parent_system": PARENT_SYSTEM
    }
    _fire_and_forget(payload)

# Example heartbeat loop that can be started when the bot starts
def start_heartbeat_loop(interval: int = 60):
    def loop():
        while True:
            emit_heartbeat()
            import time
            time.sleep(interval)
            
    if OFFICE_BRIDGE_URL and OFFICE_BRIDGE_TOKEN:
        threading.Thread(target=loop, daemon=True).start()
