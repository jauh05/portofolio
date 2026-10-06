"""Offline adapter from Living Office content payloads to visual memory.

This module only selects a template and compiles a prompt. It has no provider,
bridge, persistence, or publishing client.
"""

from __future__ import annotations

from typing import Any, Mapping, Sequence

from visual_memory import VisualBrandMemory, VisualPromptCompiler, VisualTemplateSelector


CONTENT_OUTPUT_MODES = {
    "feed": "feed_square",
    "feed_square": "feed_square",
    "feed_portrait": "feed_portrait",
    "story": "story",
    "article": "article",
}


def output_mode_for(content_type: str, requested_mode: str | None = None) -> str:
    content_type = str(content_type).strip().lower()
    if content_type not in CONTENT_OUTPUT_MODES:
        raise ValueError(f"Unsupported visual content type: {content_type}")
    mode = str(requested_mode).strip().lower() if requested_mode is not None else CONTENT_OUTPUT_MODES[content_type]
    if mode not in CONTENT_OUTPUT_MODES.values():
        raise ValueError(f"Unsupported visual output mode: {mode}")
    if content_type in {"feed", "feed_square", "feed_portrait"} and mode not in {"feed_square", "feed_portrait"}:
        raise ValueError("Feed content requires a feed output mode")
    if content_type == "story" and mode != "story":
        raise ValueError("Story content requires story output mode")
    if content_type == "article" and mode != "article":
        raise ValueError("Article content requires article output mode")
    return mode


def normalize_analysis(payload: Mapping[str, Any]) -> dict[str, Any]:
    """Use only fields actually supplied by the Analyst/content result."""
    supplied = payload.get("analysis")
    if supplied is not None and not isinstance(supplied, Mapping):
        raise ValueError("analysis must be an object")
    source = {**payload, **(supplied or {})}
    fields = (
        "topic", "pattern", "pillar", "visual_subject", "visual_intent",
        "text_density", "headline", "subheadline", "cta", "stat_value", "stat_label",
    )
    analysis = {key: source[key] for key in fields if source.get(key) is not None}
    if "key_points" in source and source["key_points"] is not None:
        analysis["key_points"] = source["key_points"]
    elif isinstance(source.get("key_findings"), list):
        points = []
        for finding in source["key_findings"]:
            if isinstance(finding, str) and finding.strip():
                points.append(finding)
            elif isinstance(finding, Mapping):
                point = finding.get("title") or finding.get("description")
                if isinstance(point, str) and point.strip():
                    points.append(point)
        if points:
            analysis["key_points"] = points
    statistics = source.get("statistics")
    if isinstance(statistics, Mapping):
        if "stat_value" not in analysis and statistics.get("value") is not None:
            analysis["stat_value"] = statistics["value"]
        if "stat_label" not in analysis and statistics.get("label") is not None:
            analysis["stat_label"] = statistics["label"]
    return analysis


def preview_visual_memory(
    payload: Mapping[str, Any], *, history: Sequence[Mapping[str, Any]] | None = None,
    seed: int | None = None, dry_run: bool = True,
) -> dict[str, Any]:
    """Compile a local preview. Caller supplies real history when available."""
    if dry_run is not True:
        raise ValueError("Visual memory worker integration is preview-only")
    brand_slug = str(payload.get("brand_slug") or payload.get("brand") or "").strip().lower()
    brand_memory = VisualBrandMemory()
    brand = brand_memory.get(brand_slug)
    output_mode = output_mode_for(payload.get("content_type", ""), payload.get("output_mode"))
    analysis = normalize_analysis(payload)
    selector = VisualTemplateSelector()
    selected = selector.select(analysis, list(history) if history is not None else None, seed)
    compiled = VisualPromptCompiler(brands=brand_memory, templates=selector.repository).compile_prompt(
        brand_slug, analysis, selected["template_id"], selected["variation"], output_mode
    )
    return {
        "brand": brand_slug,
        "analysis_input": analysis,
        "template_id": selected["template_id"],
        "template_family": selected["template"]["family"],
        "output_mode": output_mode,
        "variation": selected["variation"],
        "palette": brand["palette"],
        "typography": brand["typography"],
        "compiled_prompt": compiled["prompt"],
        "provider_payload_preview": {"model": compiled["model"], "stream": compiled["stream"]},
        "dry_run": True,
    }
