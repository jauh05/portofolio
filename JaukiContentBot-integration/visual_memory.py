"""Offline visual-memory selection and Muse prompt compilation.

This module does not call the image provider or alter the production worker.
"""

from __future__ import annotations

import copy
import json
import random
import re
from pathlib import Path
from typing import Any, Mapping


MEMORY_DIR = Path(__file__).resolve().parents[1] / "resources" / "office-ai" / "visual-memory"
OUTPUT_MODES = ("feed_square", "feed_portrait", "story", "article")
TOKEN = re.compile(r"{{\s*([a-z_]+(?:\.[a-z_]+)?)\s*}}")


def _read_json(name: str) -> dict[str, Any]:
    with (MEMORY_DIR / name).open(encoding="utf-8") as source:
        return json.load(source)


def _short(value: Any, words: int) -> str:
    if value is None:
        return ""
    text = re.sub(r"#[0-9a-fA-F]{3,8}\b", "[brand color]", " ".join(str(value).split()))
    return " ".join(text.split()[:words])


class VisualBrandMemory:
    def __init__(self, data: Mapping[str, Any] | None = None):
        self.data = dict(data) if data is not None else _read_json("brands.json")

    def get(self, slug: str) -> dict[str, Any]:
        brand = self.data["brands"].get(slug.lower())
        if brand is None:
            raise ValueError(f"Unknown visual brand: {slug}")
        if not brand.get("palette_locked") or not brand.get("palette_verified") or not brand.get("palette"):
            raise ValueError(f"Verified, locked palette required for {slug}")
        palette = brand["palette"]
        if not all(re.fullmatch(r"#[0-9a-fA-F]{6}", color) for color in palette.values()):
            raise ValueError(f"Invalid palette token for {slug}")
        typography = brand.get("typography")
        if not isinstance(typography, dict) or not all(
            isinstance(font, str) and font.strip() for font in typography.values()
        ) or not {"headline", "body"}.issubset(typography):
            raise ValueError(f"Named headline and body fonts required for {slug}")
        if brand.get("dominant_color_family") not in {"green", "blue"}:
            raise ValueError(f"Dominant color family required for {slug}")
        if not isinstance(brand.get("typography_prompt"), str) or not brand["typography_prompt"].strip():
            raise ValueError(f"Typography prompt style required for {slug}")
        return copy.deepcopy(brand)


class VisualTemplateRepository:
    def __init__(self, data: Mapping[str, Any] | None = None):
        self.data = dict(data) if data is not None else _read_json("visual-template-memory.json")
        self.templates = {item["id"]: item for item in self.data["templates"]}
        self.validate()

    def validate(self) -> None:
        expected = {f"T{number:02d}" for number in range(1, 28)}
        if len(self.data["templates"]) != 27 or set(self.templates) != expected:
            raise ValueError("Visual memory must contain exactly T01–T27")
        for template in self.templates.values():
            if template.get("model") != "meta/muse-image" or template.get("stream") is not True:
                raise ValueError(f"Invalid Muse payload in {template['id']}")
            if template.get("palette_source") != "brand_memory":
                raise ValueError(f"Template palette must come from brand memory in {template['id']}")
            if re.search(r"#[0-9a-fA-F]{3,8}\b", json.dumps(template)):
                raise ValueError(f"Template-specific color in {template['id']}")
            if set(template.get("supported_outputs", [])) != set(OUTPUT_MODES):
                raise ValueError(f"Missing output mode in {template['id']}")
            if set(template.get("layout_by_output", {})) != set(OUTPUT_MODES):
                raise ValueError(f"Missing layout reflow in {template['id']}")
            for mode in OUTPUT_MODES:
                if not template["layout_by_output"][mode].strip():
                    raise ValueError(f"Empty layout instruction in {template['id']} / {mode}")

    def get(self, template_id: str) -> dict[str, Any]:
        try:
            return self.templates[template_id]
        except KeyError as error:
            raise ValueError(f"Unknown visual template: {template_id}") from error


class VisualTemplateSelector:
    HERO_POSITIONS = ("left", "right", "center", "lower_center")
    TREATMENTS = ("solid", "soft_gradient", "editorial_texture", "light_studio")
    FIELD_WEIGHTS = {
        "pattern": 2.0,
        "topic": 1.5,
        "pillar": 1.2,
        "visual_subject": 1.7,
        "visual_intent": 1.2,
        "key_points": 0.8,
        "stat_label": 1.1,
    }
    DENSE_FAMILIES = {"diagram_explainer", "feature_list", "information_panel", "survey_results", "analytical_checklist", "component_grid", "floating_cards", "benefit_list"}
    SPARSE_FAMILIES = {"hero_object", "conceptual_photography", "typography_hero", "human_editorial", "surreal_hero", "sculptural_hero", "cinematic_photo"}

    def __init__(self, repository: VisualTemplateRepository | None = None, rules: Mapping[str, Any] | None = None):
        self.repository = repository or VisualTemplateRepository()
        self.rules = dict(rules) if rules is not None else _read_json("selection-rules.json")

    def select(self, analysis: Mapping[str, Any], history: list[Mapping[str, Any]] | None = None, seed: int | None = None) -> dict[str, Any]:
        history = history or []
        rng = random.Random(seed)
        if not isinstance(analysis.get("key_points", []), list):
            raise ValueError("key_points must be a list")
        fields = {
            key: " ".join(str(item) for item in analysis.get(key, [])) if key == "key_points"
            else str(analysis.get(key) or "")
            for key in self.FIELD_WEIGHTS
        }
        fields = {key: value.lower().strip() for key, value in fields.items()}
        stat_value = str(analysis.get("stat_value") or "").strip()
        if not any(fields.values()) and not stat_value:
            raise ValueError("Content analysis needs a topic, pattern, pillar, visual subject, visual intent, key point, or statistic")
        candidates: dict[str, float] = {template_id: 0.05 for template_id in self.repository.templates}
        for rule_name, weights in self.rules["pattern_weights"].items():
            for field, field_text in fields.items():
                if re.search(r"\b" + re.escape(rule_name) + r"\b", field_text):
                    for template_id, weight in weights.items():
                        candidates[template_id] += weight * self.FIELD_WEIGHTS[field]
        for template in self.repository.templates.values():
            for field, field_text in fields.items():
                matches = sum(bool(re.search(r"\b" + re.escape(tag.lower()) + r"\b", field_text)) for tag in template["content_matches"])
                candidates[template["id"]] += matches * 3 * self.FIELD_WEIGHTS[field]
        if stat_value:
            for template_id, weight in self.rules["pattern_weights"]["statistics"].items():
                candidates[template_id] += weight * 1.5
        density = str(analysis.get("text_density") or "low").lower()
        if density not in {"low", "medium", "high"}:
            raise ValueError("text_density must be low, medium, or high")
        for template_id, template in self.repository.templates.items():
            if density in {"medium", "high"} and template["family"] in self.DENSE_FAMILIES:
                candidates[template_id] += 3 if density == "medium" else 6
            if density == "low" and template["family"] in self.SPARSE_FAMILIES:
                candidates[template_id] += 2

        gap = self.rules["minimum_template_gap"]
        recent = history[-gap:]
        recent_ids = {entry.get("template_id") for entry in recent}
        candidates = {key: value for key, value in candidates.items() if key not in recent_ids}
        recent_families = {self.repository.get(entry["template_id"])["family"] for entry in recent if entry.get("template_id") in self.repository.templates}
        for key in candidates:
            if self.repository.get(key)["family"] in recent_families:
                candidates[key] /= self.rules["recent_family_penalty"]
        chosen_id = rng.choices(list(candidates), weights=list(candidates.values()), k=1)[0]
        template = self.repository.get(chosen_id)

        def least_recent(options: tuple[str, ...], field: str) -> str:
            latest = {entry.get(field, entry.get("background_treatment") if field == "visual_treatment" else None) for entry in recent}
            available = [option for option in options if option not in latest] or list(options)
            return rng.choice(available)

        variation = {
            "hero_position": least_recent(self.HERO_POSITIONS, "hero_position"),
            "headline_alignment": rng.choice(("left", "center", "right")),
            "background_treatment": least_recent(self.TREATMENTS, "visual_treatment"),
            "image_crop": rng.choice(("generous", "close", "contextual")),
            "card_position": rng.choice(("lower", "side", "staggered")),
            "object_scale": rng.choice(("medium", "large")),
            "visual_weight": rng.choice(("balanced", "subject_dominant", "type_dominant")),
            "text_density": "medium" if density in {"medium", "high"} else "low",
            "decorative_density": rng.choice(("minimal", "restrained")),
        }
        return {"template_id": chosen_id, "template": template, "variation": variation, "score": candidates[chosen_id]}


class VisualPromptCompiler:
    def __init__(self, brands: VisualBrandMemory | None = None, templates: VisualTemplateRepository | None = None):
        self.brands = brands or VisualBrandMemory()
        self.templates = templates or VisualTemplateRepository()

    def compile_prompt(self, brand_slug: str, analysis: Mapping[str, Any], template_id: str, variation: Mapping[str, Any], output_mode: str) -> dict[str, Any]:
        if output_mode not in OUTPUT_MODES:
            raise ValueError(f"Unknown output mode: {output_mode}")
        brand = self.brands.get(brand_slug)
        template = self.templates.get(template_id)
        if output_mode not in template["supported_outputs"]:
            raise ValueError(f"Unsupported output mode: {output_mode}")
        allowed = {
            "hero_position": {"left", "right", "center", "lower_center"},
            "headline_alignment": {"left", "center", "right"},
            "background_treatment": {"solid", "soft_gradient", "editorial_texture", "light_studio"},
            "image_crop": {"generous", "close", "contextual"},
            "card_position": {"lower", "side", "staggered"},
            "object_scale": {"medium", "large"},
            "visual_weight": {"balanced", "subject_dominant", "type_dominant"},
            "text_density": {"low", "medium"},
            "decorative_density": {"minimal", "restrained"},
        }
        if any(name not in allowed or value not in allowed[name] for name, value in variation.items()):
            raise ValueError("Invalid controlled variation")
        palette = ", ".join(f"{name} {color}" for name, color in brand["palette"].items())
        typography = ", ".join(f"{role.replace('_', ' ')}: {font}" for role, font in brand["typography"].items())
        key_points = analysis.get("key_points", [])
        if not isinstance(key_points, list):
            raise ValueError("key_points must be a list")
        fields = {
            "brand.name": brand["name"],
            "brand.palette": palette,
            "brand.visual_personality": brand["visual_personality"],
            "brand.series_label": brand["series_label"],
            "content.topic": _short(analysis.get("topic"), 15),
            "content.headline": _short(analysis.get("headline"), 12),
            "content.subheadline": _short(analysis.get("subheadline"), 20),
            "content.cta": _short(analysis.get("cta"), 6),
            "analysis.pattern": _short(analysis.get("pattern"), 6),
            "analysis.pillar": _short(analysis.get("pillar"), 6),
            "analysis.visual_subject": _short(analysis.get("visual_subject"), 18),
            "analysis.visual_intent": _short(analysis.get("visual_intent"), 12),
            "analysis.key_points": "; ".join(_short(point, 8) for point in key_points[:5]),
            "analysis.stat_value": _short(analysis.get("stat_value"), 5),
            "analysis.stat_label": _short(analysis.get("stat_label"), 8),
            "analysis.text_density": variation.get("text_density", "low"),
            "output.aspect_ratio": self.templates.data["output_modes"][output_mode]["aspect_ratio"],
            "output.layout_instruction": template["layout_by_output"][output_mode],
            "variation": ", ".join(f"{name.replace('_', ' ')}: {_short(value, 4)}" for name, value in variation.items()),
        }
        prompt = TOKEN.sub(lambda match: str(fields[match.group(1)]), template["prompt"])
        if "{{" in prompt:
            raise ValueError(f"Unresolved prompt token in {template_id}")
        prompt += f" Brand visual personality: {brand['visual_personality']}."
        prompt += f" Brand font metadata (style cues, not exact font-file rendering): {typography}. Typography direction: {brand['typography_prompt']}."
        prompt += f" Dominant color family: {brand['dominant_color_family']}. Use primary and secondary for dominant surfaces; remaining colors in the active brand palette are supporting accents."
        prompt += f" Use only these palette colors: {palette}. Never use reference palette, branding, or copy."
        prompt += " The image model cannot guarantee exact font families; preserve exact font assignment for a future deterministic text overlay. "
        prompt += "The output-specific composition and subject placement take precedence over controlled variation; apply variation only within that layout. "
        prompt += "Keep text to a short headline, short subheadline, short badge, one statistic, or short CTA; leave detailed copy for a precise downstream overlay. "
        prompt += f"Canvas {fields['output.aspect_ratio']}; compose directly for this canvas, with safe margins."
        return {"model": template["model"], "prompt": prompt, "stream": template["stream"]}
