"""Preview-only bridge from a worker command to the offline visual memory preview.

The bridge validates an explicit ``preview_visual`` command, compiles a visual
preview through ``office_social_worker.preview_visual_command`` and returns a
structured result. It has no provider, HTTP, persistence, Office event, or
publishing client, and it never enters the generation/publish dispatch table.

Developer usage (offline, prints JSON):

    python visual_preview_bridge.py '{"brand": "jauki", "topic": "...",
        "output_mode": "feed_portrait", "dry_run": true}'
"""

from __future__ import annotations

import json
import sys
from typing import Any, Dict, Mapping

PREVIEW_VISUAL_ACTION = "preview_visual"
PREVIEW_AGENT_ID = "jauki-social"
MAX_HISTORY_ENTRIES = 50

ANALYSIS_KEYS = frozenset({
    "topic", "pattern", "pillar", "visual_subject", "visual_intent", "text_density",
    "headline", "subheadline", "cta", "stat_value", "stat_label",
    "key_points", "key_findings", "statistics", "analysis",
})
ALLOWED_PAYLOAD_KEYS = ANALYSIS_KEYS | {
    "dry_run", "brand", "brand_slug", "platform", "content_type", "output_mode", "history", "seed",
}
OUTPUT_MODE_CONTENT_TYPES = {"feed_square": "feed", "feed_portrait": "feed", "story": "story"}
SOCIAL_CONTENT_TYPES = frozenset({"feed", "feed_square", "feed_portrait", "story"})


class PreviewValidationError(ValueError):
    """Raised for any command that must be rejected before preview compilation."""


def _rejection(message: str) -> Dict[str, Any]:
    return {"ok": False, "dry_run": True, "error": message[:500], "provider_called": False}


def _validate_command(command: Any) -> Mapping[str, Any]:
    if not isinstance(command, Mapping):
        raise PreviewValidationError("preview_visual command must be an object")
    if command.get("action") != PREVIEW_VISUAL_ACTION:
        raise PreviewValidationError("Command is not a preview_visual command")
    if command.get("agent_id", PREVIEW_AGENT_ID) != PREVIEW_AGENT_ID:
        raise PreviewValidationError("preview_visual is only available on the Social worker")
    payload = command.get("payload")
    if not isinstance(payload, Mapping):
        raise PreviewValidationError("preview_visual payload must be an object")
    if "dry_run" not in payload:
        raise PreviewValidationError("preview_visual requires dry_run=true")
    if payload["dry_run"] is not True:
        raise PreviewValidationError("preview_visual is preview-only; dry_run must be boolean true")
    unknown = sorted(str(key) for key in payload if key not in ALLOWED_PAYLOAD_KEYS)
    if unknown:
        raise PreviewValidationError("Unsupported preview_visual fields: " + ", ".join(unknown)[:200])
    return payload


def _preview_input(payload: Mapping[str, Any]) -> Dict[str, Any]:
    brand = payload.get("brand_slug", payload.get("brand"))
    if not isinstance(brand, str) or not brand.strip():
        raise PreviewValidationError("preview_visual requires a brand")
    platform = payload.get("platform", "instagram")
    if platform != "instagram":
        raise PreviewValidationError("preview_visual supports Instagram content only")

    content_type = payload.get("content_type")
    output_mode = payload.get("output_mode")
    for name, value in (("content_type", content_type), ("output_mode", output_mode)):
        if value is not None and not isinstance(value, str):
            raise PreviewValidationError(f"{name} must be a string")
    if "article" in (content_type, output_mode):
        raise PreviewValidationError(
            "Article preview is not available on the Social worker; persistent article worker is not implemented"
        )
    if content_type is None:
        if output_mode not in OUTPUT_MODE_CONTENT_TYPES:
            raise PreviewValidationError("Unsupported visual output mode: " + str(output_mode)[:40])
        content_type = OUTPUT_MODE_CONTENT_TYPES[output_mode]
    if content_type not in SOCIAL_CONTENT_TYPES:
        raise PreviewValidationError("Unsupported visual content type: " + str(content_type)[:40])

    preview = {key: payload[key] for key in ANALYSIS_KEYS if key in payload}
    preview.update({"brand": brand.strip(), "platform": "instagram", "content_type": content_type})
    if output_mode is not None:
        preview["output_mode"] = output_mode
    return preview


def _history(payload: Mapping[str, Any]):
    history = payload.get("history")
    if history is None:
        return None
    if not isinstance(history, list) or len(history) > MAX_HISTORY_ENTRIES:
        raise PreviewValidationError(f"history must be a list of at most {MAX_HISTORY_ENTRIES} entries")
    if not all(isinstance(entry, Mapping) for entry in history):
        raise PreviewValidationError("history entries must be objects")
    return history


def _seed(payload: Mapping[str, Any]):
    seed = payload.get("seed")
    if seed is not None and (isinstance(seed, bool) or not isinstance(seed, int)):
        raise PreviewValidationError("seed must be an integer")
    return seed


def run_preview_visual(command: Any) -> Dict[str, Any]:
    """Validate and compile a preview. Never raises; always fails closed."""
    try:
        payload = _validate_command(command)
        preview_input = _preview_input(payload)
        history = _history(payload)
        seed = _seed(payload)
        from office_social_worker import preview_visual_command

        preview = preview_visual_command(preview_input, history=history, seed=seed)
    except (PreviewValidationError, ValueError, TypeError) as error:
        return _rejection(" ".join(str(error).split()) or "Invalid preview_visual command")
    except Exception:
        return _rejection("preview_visual failed during local compilation")

    limitations = ["Template history not supplied; repetition guard used no history"] if history is None else []
    return {
        "ok": True,
        "dry_run": True,
        "brand": preview["brand"],
        "template_id": preview["template_id"],
        "template_family": preview["template_family"],
        "output_mode": preview["output_mode"],
        "primary_palette": preview["palette"]["primary"],
        "headline_font": preview["typography"]["headline"],
        "variation": preview["variation"],
        "analysis_input": preview["analysis_input"],
        "history_supplied": history is not None,
        "limitations": limitations,
        "compiled_prompt": preview["compiled_prompt"],
        "provider_payload_preview": dict(preview["provider_payload_preview"]),
        "provider_called": False,
    }


def format_preview_message(result: Mapping[str, Any]) -> str:
    """Compact human-readable summary; the structured result stays the source of truth."""
    if not result.get("ok"):
        return "Visual preview rejected (dry run, provider not called): " + str(result.get("error", ""))
    return (
        f"Visual preview (dry run) | {result['brand']} | {result['template_id']} {result['template_family']} | "
        f"{result['output_mode']} | {result['primary_palette']} | {result['headline_font']} | provider not called"
    )


def main(argv=None) -> int:
    args = sys.argv[1:] if argv is None else argv
    if len(args) != 1:
        print(json.dumps(_rejection("Usage: visual_preview_bridge.py '<json payload with dry_run=true>'")))
        return 2
    try:
        payload = json.loads(args[0])
    except json.JSONDecodeError:
        payload = None
    result = run_preview_visual({"agent_id": PREVIEW_AGENT_ID, "action": PREVIEW_VISUAL_ACTION, "payload": payload})
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["ok"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
