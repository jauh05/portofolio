"""Living AI Office command worker for Trend/Data Analyst."""

import json
import os
import logging
import subprocess
import sys
import threading
import time
import urllib.parse
import urllib.request
import uuid
from typing import Any, Dict, List, Optional
from datetime import datetime, timezone

AUTH_SCHEME = "Bearer"

BRIDGE_DIR = os.path.join(os.path.dirname(__file__), "JaukiContentBot-integration")
if BRIDGE_DIR not in sys.path:
    sys.path.insert(0, BRIDGE_DIR)


def _load_env_file(path: str = ".env") -> None:
    """Load required bridge env vars without logging their values."""
    env_path = os.path.join(os.path.dirname(__file__), path)
    try:
        with open(env_path, "r", encoding="utf-8") as handle:
            for raw_line in handle:
                line = raw_line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, value = line.split("=", 1)
                key = key.strip()
                if key not in {"OFFICE_BRIDGE_URL", "OFFICE_BRIDGE_TOKEN", "APP_URL"}:
                    continue
                value = value.strip().strip('"').strip("'")
                os.environ.setdefault(key, value)
    except FileNotFoundError:
        return


_load_env_file()
if not os.getenv("OFFICE_BRIDGE_URL") and os.getenv("APP_URL"):
    os.environ["OFFICE_BRIDGE_URL"] = os.getenv("APP_URL", "")

import office_bridge

if not hasattr(office_bridge, "emit_event"):
    office_bridge.emit_event = office_bridge.emit_event_sync

logger = logging.getLogger(__name__)


def _bridge_enabled() -> bool:
    return bool(office_bridge.OFFICE_BRIDGE_URL and office_bridge.OFFICE_BRIDGE_TOKEN)


AGENT_ID = "jauki-analyst"
POLL_INTERVAL_SECONDS = 5
HTTP_TIMEOUT_SECONDS = 5.0

_worker_lock = threading.Lock()
_worker_thread: Optional[threading.Thread] = None


def _office_api_endpoint(path: str) -> Optional[str]:
    url = office_bridge.office_endpoint(path)
    if not url:
        return None

    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme == "http" and parsed.netloc == "jauharfauzi.my.id":
        parsed = parsed._replace(scheme="https")
    return urllib.parse.urlunsplit(parsed)


class OfficeCommandClient:
    def _request(
        self,
        method: str,
        path: str,
        payload: Dict[str, Any],
    ) -> Dict[str, Any]:
        url = _office_api_endpoint(path)
        token = office_bridge.OFFICE_BRIDGE_TOKEN

        if not url or not token:
            raise RuntimeError(
                "Office Bridge URL or token is not configured"
            )

        request = urllib.request.Request(
            url,
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Authorization": f"{AUTH_SCHEME} {token}",
                "Content-Type": "application/json",
                "Accept": "application/json",
                "User-Agent": "JaukiContentBot-LivingOffice/1.0",
            },
            method=method,
        )

        try:
            with urllib.request.urlopen(
                request,
                timeout=HTTP_TIMEOUT_SECONDS,
            ) as response:
                body = response.read().decode("utf-8")
                if not body:
                    return {}
                return json.loads(body)
        except Exception as exc:
            logger.error(
                "office_command_client request failed: %s %s (%s)",
                method,
                path,
                type(exc).__name__,
            )
            return {}

    def claim(self):
        parsed = self._request(
            "POST",
            "/api/office/commands/claim",
            {"agent_id": AGENT_ID},
        )
        if "message" in parsed and "errors" in parsed:
            return None
        command = parsed.get("command")
        if command and (command.get("agent_id") == AGENT_ID or command.get("agent_id") is None):
            return command
        return None

    def update(
        self,
        command_id: str,
        status: str,
        error: Optional[str] = None,
        result: Optional[Dict[str, Any]] = None,
    ):
        payload = {
            "status": status,
        }
        if error is not None:
            payload["error"] = error
        if result is not None:
            payload["result"] = result

        return self._request(
            "PATCH",
            f"/api/office/commands/{urllib.parse.quote(command_id)}",
            payload,
        )

def _safe_error(exc: Exception) -> str:
    msg = str(exc)
    if "token" in msg.lower() or "key" in msg.lower() or "secret" in msg.lower() or "password" in msg.lower():
        return "Internal error (redacted)"
    return msg


def _emit(event: str, **fields):
    office_bridge.emit_event(
        event,
        agent_id=AGENT_ID,
        **fields,
    )


def run_mcporter_exa(query: str, objective: str, num_results: int = 5) -> List[Dict[str, Any]]:
    cmd = [
        "mcporter", "call", "exa.web_search_exa",
        f"query={query}",
        f"numResults={num_results}",
        f"objective={objective}",
        "--output", "json"
    ]
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, check=True)
        # Parse JSON output from mcporter
        out = proc.stdout.strip()
        # Mcporter might wrap the response in some text or just return JSON.
        # Simple extraction strategy: find first '{' or '[' and parse it.
        start_idx = -1
        for i, char in enumerate(out):
             if char in ('{', '['):
                 start_idx = i
                 break
        if start_idx != -1:
            try:
                parsed = json.loads(out[start_idx:])

                # Check for MCP format wrapped output first (mcporter typical format)
                if isinstance(parsed, dict) and "content" in parsed:
                    # Extract text content from MCP format
                    content_text = ""
                    for item in parsed["content"]:
                        if item.get("type") == "text":
                            content_text += item.get("text", "")

                    # We only have raw text string containing formatted Exa results,
                    # parse it roughly into our source dict format
                    sources = []
                    import re
                    # Simple heuristic parser for the text dump Exa produces
                    blocks = content_text.split("---")
                    for block in blocks:
                        title_match = re.search(r"Title:\s*(.+)", block)
                        url_match = re.search(r"URL:\s*(.+)", block)
                        published_match = re.search(r"Published:\s*(.+)", block)
                        author_match = re.search(r"Author:\s*(.+)", block)
                        highlights_match = re.search(r"Highlights:\s*(.+)", block, re.DOTALL)
                        
                        if title_match and url_match:
                            source = {
                                "title": title_match.group(1).strip(),
                                "url": url_match.group(1).strip()
                            }
                            if published_match and published_match.group(1).strip() != "None":
                                source["published_at"] = published_match.group(1).strip()
                            if author_match and author_match.group(1).strip() != "None":
                                source["author"] = author_match.group(1).strip()
                            if highlights_match and highlights_match.group(1).strip() != "None":
                                source["snippet"] = highlights_match.group(1).strip().split("\n")[0] # Take first line of highlights as snippet
                            sources.append(source)
                    return sources

                if isinstance(parsed, dict) and "results" in parsed:
                     return parsed["results"]
                elif isinstance(parsed, list):
                     return parsed
                else:
                    logger.warning("Unexpected mcporter output format: %s", out)
            except json.JSONDecodeError:
                logger.warning("Could not parse mcporter output as JSON: %s", out)

        return []
    except subprocess.CalledProcessError as e:
        logger.error("mcporter call failed: %s\nStderr: %s", e, e.stderr)
        raise RuntimeError(f"Tool execution failed: {e}") from e
    except Exception as e:
         logger.error("Error executing mcporter: %s", e)
         raise RuntimeError(f"Tool execution failed: {e}") from e

def _execute(
    client: OfficeCommandClient,
    command_id: str,
    action: str,
    payload: Dict[str, Any],
):
    try:
        # Create a task start payload matching the expected format
        task_data = {
            "id": command_id,
            "title": f"Analyst: {action}",
            "type": "research",
            "target": payload.get("topic", "General"),
        }
        _emit("task.started", command_id=command_id, action=action, task=task_data)
        client.update(command_id, "running")

        _emit("task.progress", command_id=command_id, action=action, progress=10, detail="Starting research", task_id=command_id)

        topic = payload.get("topic", "General Trends")
        language = payload.get("language", "id")
        limit = payload.get("limit", 3)

        # Determine query and objective based on action
        query = ""
        objective = ""
        if action == "research_trends":
            query = f"Current trends about {topic} in {language} language"
            objective = f"Find the latest trend signals, market shifts, and emerging topics related to {topic}."
        elif action == "research_topic":
             query = f"Deep dive into {topic} in {language} language"
             objective = f"Find comprehensive information, facts, and key concepts about {topic}."
        elif action == "find_content_ideas":
             query = f"Content ideas and inspiration for {topic} in {language} language"
             objective = f"Find popular content, viral posts, and audience questions about {topic}."
        elif action == "analyze_sources":
             # Simplified for V1: we just search about the topic. Real fetch requires web_fetch_exa.
             query = f"Analysis of {topic} in {language} language"
             objective = f"Analyze context and sentiment around {topic}."
        else:
            raise ValueError(f"Unsupported action: {action}")

        _emit("task.progress", command_id=command_id, action=action, progress=30, detail="Searching EXA", task_id=command_id)

        exa_results = run_mcporter_exa(query, objective, limit)

        _emit("task.progress", command_id=command_id, action=action, progress=70, detail="Synthesizing results", task_id=command_id)

        # Build structured result
        # Ensure sources has correct structure based on run_mcporter_exa output
        sources = exa_results if isinstance(exa_results, list) else []

        result = {
            "topic": topic,
            "summary": f"Found {len(sources)} sources related to {topic}. " +
                       (f"Top result: {sources[0]['title']}" if sources else "No specific sources found."),
            "trend_signals": [
                {
                    "signal": "High interest based on search results" if len(sources) >= 3 else "Low to medium interest",
                    "evidence_count": len(sources)
                }
            ],
            "audience_problems": ["Understanding current landscape (inferred)"],
            "content_opportunities": [f"Deep dive into {topic}"],
            "content_ideas": {
                "feed": [f"5 things to know about {topic}"],
                "story": [f"Did you know this about {topic}?"],
                "threads": [f"Let's talk about {topic}. Here are my findings."],
                "article": [f"Comprehensive Guide to {topic} Trends"]
            },
            "sources": sources,
            "researched_at": datetime.now(timezone.utc).isoformat(),
            "model": "mcporter-exa-only-v1" # In V1 dry-run we just use Exa directly without LLM to save quota, or use mock LLM synthesis.
        }

        _emit("research.completed", command_id=command_id, action=action, topic=topic, result=result, task_id=command_id)
        client.update(command_id, "completed", result=result)
        _emit("task.completed", command_id=command_id, action=action, result=result, task_id=command_id)

    except Exception as exc:
        safe_msg = _safe_error(exc)
        logger.error(
            "Analyst command %s failed: %s",
            command_id,
            safe_msg,
            exc_info=exc,
        )
        client.update(command_id, "failed", error=safe_msg)
        _emit(
            "task.failed",
            command_id=command_id,
            action=action,
            error=safe_msg,
            task_id=command_id,
        )


def _loop():
    client = OfficeCommandClient()

    while True:
        try:
            if not _bridge_enabled():
                time.sleep(POLL_INTERVAL_SECONDS)
                continue

            command = client.claim()

            if not command or not (command.get("command_id") or command.get("id")):
                time.sleep(POLL_INTERVAL_SECONDS)
                continue

            command_id = command.get("command_id") or command["id"]
            action = command.get("action", "")
            payload = command.get("payload", {})

            logger.info("Claimed Analyst command: %s (%s)", command_id, action)

            _execute(client, command_id, action, payload)

        except Exception as exc:
            logger.error("Analyst loop error: %s", exc)
            time.sleep(POLL_INTERVAL_SECONDS * 2)


def start_analyst_worker():
    global _worker_thread

    if not _bridge_enabled():
        return

    with _worker_lock:
        if _worker_thread and _worker_thread.is_alive():
            return

        _worker_thread = threading.Thread(
            target=_loop,
            name="OfficeAnalystWorker",
            daemon=True,
        )
        _worker_thread.start()
        logger.info("Office Analyst Worker started")


def main():
    logging.basicConfig(
        level=os.getenv("LOG_LEVEL", "INFO"),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )
    if not _bridge_enabled():
        raise SystemExit("Office Bridge URL or token is not configured")
    logger.info("Starting Office Analyst Worker for %s", AGENT_ID)
    _loop()


if __name__ == "__main__":
    main()
