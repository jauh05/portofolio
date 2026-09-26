import json
import logging
import threading
import time
import urllib.parse
import urllib.request
import uuid
from datetime import datetime, timezone
from typing import Any, Dict, Optional
import os

import office_bridge

logger = logging.getLogger(__name__)

AGENT_ID = "jauki-social"
POLL_INTERVAL_SECONDS = 5
IDLE_DELAY_SECONDS = 8
HTTP_TIMEOUT_SECONDS = 5.0

_worker_lock = threading.Lock()
_worker_thread: Optional[threading.Thread] = None


class OfficeCommandClient:
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
                "User-Agent": "JaukiContentBot-LivingOffice/1.0",
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


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def _safe_error(error: Exception) -> str:
    message = " ".join(str(error).split()) or error.__class__.__name__
    token = getattr(office_bridge, 'OFFICE_BRIDGE_TOKEN', None)
    if token and isinstance(token, str):
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
        task={"id": task_id, "title": title, "type": task_type, "target": "instagram"},
    )
    context["task_started"] = True


def _progress(context: Dict[str, Any], progress: int, activity: str) -> None:
    _emit_required("task.progress", task_id=context["task_id"], progress=progress, activity=activity)


def _do_generate(context: Dict[str, Any], mode: str) -> None:
    # Importing bot functions that don't depend on telegram classes
    from bot import generate_content, build_muse_prompt, generate_muse_image, save_latest_state, choose_new_variant, feed_caption
    
    label = "Feed" if mode == "feed" else "Story"
    
    _start_task(context, f"Generate Instagram {label}", f"instagram_{mode}_generation", f"Preparing {label}")
    _progress(context, 30, f"Generating {label} content")

    # This is normally done in asyncio.to_thread in the bot, but here we run it synchronously
    content = generate_content(mode)
    variant = choose_new_variant()
    prompt = build_muse_prompt(content, variant, mode)

    _progress(context, 65, f"Generating {label} image")
    image_path = generate_muse_image(prompt, mode)

    state = {
        "mode": mode,
        "content": content,
        "variant": variant,
        "image_path": image_path,
        "uploaded": False,
        "generated_at": _now_iso()
    }
    save_latest_state(state)

    # For preview, we prepare the output
    preview_content = {
        "platform": "instagram",
        "content_type": mode,
        "title": f"Instagram {label} post",
    }
    
    if mode == "feed":
        preview_content["text"] = feed_caption(content)
        
    # the frontend uses image URL if present, or just knows it's generated
    # (Office needs an accessible URL which might not exist unless we expose it, but text/caption is the main part)
    
    _emit_required(
        "content.preview_ready",
        activity=f"{label} draft ready",
        content=preview_content
    )
    
    _emit_required(
        "task.completed",
        task_id=context["task_id"],
        progress=100,
        result={"title": f"Instagram {label} preview ready", "stage": "preview_ready", "mode": mode},
    )


def handle_generate_feed(context: Dict[str, Any]) -> None:
    _do_generate(context, "feed")


def handle_generate_story(context: Dict[str, Any]) -> None:
    _do_generate(context, "story")


DISPATCH = {
    ("jauki-social", "generate_feed"): handle_generate_feed,
    ("jauki-social", "generate_story"): handle_generate_story,
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
            raise RuntimeError(f"Command '{command.get('action')}' is not allowlisted for the Social worker")
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
    office_bridge.emit_status(AGENT_ID, "idle", "Waiting for social task", 0)
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
            logger.warning("Office social command poll failed: %s", _safe_error(error))
        time.sleep(interval)


def start_social_worker(interval: float = POLL_INTERVAL_SECONDS) -> Optional[threading.Thread]:
    global _worker_thread

    if not office_bridge.OFFICE_BRIDGE_URL or not office_bridge.OFFICE_BRIDGE_TOKEN:
        logger.warning("Office social worker not started: bridge URL or token is missing")
        return None

    with _worker_lock:
        if _worker_thread is not None and _worker_thread.is_alive():
            return _worker_thread
        _worker_thread = threading.Thread(
            target=_worker_loop,
            args=(interval,),
            name="office-social-command-worker",
            daemon=True,
        )
        _worker_thread.start()
        return _worker_thread
