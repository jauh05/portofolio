"""Bank materi konten (feed dan story) per brand. Tiap materi hanya dipakai sekali.

Pemakaian dicatat di state worker. Urutan diacak dengan seed tetap per brand dan format, jadi hasilnya bervariasi tetapi
bisa diulang. Bila semua materi sudah terpakai, `next_item` mengembalikan None dan pemanggil harus berhenti atau memakai
sumber lain, bukan mengulang.
"""

from __future__ import annotations

import json
import random
from typing import Any, Mapping, Optional

from visual_memory import MEMORY_DIR


# Awalan ID per brand dan format. Dipakai untuk memastikan bank satu brand tidak pernah dipakai oleh brand lain.
ID_PREFIX = {("kauiz", "feed"): "kf-", ("kauiz", "story"): "ks-", ("jauki", "feed"): "jf-", ("jauki", "story"): "js-",
             ("jauki", "threads"): "jt-"}


def _check_id(brand: str, fmt: str, item_id: str) -> None:
    prefix = ID_PREFIX.get((brand, fmt))
    if prefix is None or not str(item_id).startswith(prefix):
        raise ValueError(f"Materi {item_id} bukan milik bank {brand} {fmt}")


def load_bank(brand: str, fmt: str) -> list[dict[str, Any]]:
    if (brand, fmt) not in ID_PREFIX:
        raise ValueError(f"Tidak ada bank {fmt} untuk brand {brand}")
    path = MEMORY_DIR / "prompt-bank" / f"{brand}-{fmt}.json"
    data = json.loads(path.read_text(encoding="utf-8"))
    if data.get("brand") != brand or data.get("format") != fmt:
        raise ValueError(f"{path.name} berlabel {data.get('brand')}/{data.get('format')}, bukan {brand}/{fmt}")
    for item in data["items"]:
        _check_id(brand, fmt, item["id"])
    return data["items"]


def _order(brand: str, fmt: str, items: list[dict[str, Any]]) -> list[dict[str, Any]]:
    ordered = list(items)
    random.Random(f"{brand}:{fmt}:bank-v1").shuffle(ordered)
    return ordered


def _used(state: Mapping[str, Any], brand: str, fmt: str) -> set[str]:
    return set(state.get("bank", {}).get(f"{brand}:{fmt}", []))


def remaining(state: Mapping[str, Any], brand: str, fmt: str) -> int:
    return len([item for item in load_bank(brand, fmt) if item["id"] not in _used(state, brand, fmt)])


def next_item(state: Mapping[str, Any], brand: str, fmt: str) -> Optional[dict[str, Any]]:
    """Materi berikutnya yang belum terpakai, atau None bila bank habis. Tidak mengubah state."""
    used = _used(state, brand, fmt)
    return next((item for item in _order(brand, fmt, load_bank(brand, fmt)) if item["id"] not in used), None)


def consume(state: dict[str, Any], brand: str, fmt: str, item_id: str) -> None:
    _check_id(brand, fmt, item_id)
    used = state.setdefault("bank", {}).setdefault(f"{brand}:{fmt}", [])
    if item_id not in used:
        used.append(item_id)


def to_plan(item: Mapping[str, Any], fmt: str) -> dict[str, Any]:
    """Materi bank dalam bentuk rencana yang dipahami worker dan penulis prompt."""
    plan = {
        "source": "bank", "bank_id": item["id"], "topic": item["hook"], "key_message": item["hook"], "brief": item["line"],
        "key_points": list(item["chips"]), "visual_subject": item["subject"], "feature": item.get("feature"),
    }
    if item.get("pattern"):
        plan["pattern"] = item["pattern"]
    if fmt == "feed":
        for key in ("pain", "tip", "caption"):
            if item.get(key):
                plan["caption_text" if key == "caption" else key] = item[key]
    return plan
