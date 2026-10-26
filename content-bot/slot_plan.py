"""Logika jadwal slot Reels: kapan video harus dirender dan kapan harus diposting.

Murni tanpa ffmpeg atau jaringan, supaya bisa dites langsung.
Setiap slot punya jam tetap (hari 1-7 dengan Senin=1, dan HH:MM). Video dirender
`lead_minutes` sebelum jam slot, lalu diposting tepat setelah jam slot.
"""

from dataclasses import dataclass
from datetime import datetime, timedelta

DEFAULT_LEAD_MINUTES = 120
LOOKBACK = timedelta(hours=6)
LOOKAHEAD = timedelta(days=7)


@dataclass(frozen=True)
class Action:
    kind: str  # "render" | "post" | "missed"
    key: str
    slot: dict
    occurrence: datetime


def occurrence_key(slot, occurrence):
    return f"{slot['id']}@{occurrence.isoformat()}"


def occurrences(slot, now, tz):
    """Jam kemunculan slot di sekitar `now` (6 jam ke belakang sampai 7 hari ke depan)."""
    hour, minute = (int(part) for part in slot["time"].split(":"))
    found = []
    start_day = (now - timedelta(days=1)).date()
    for offset in range(10):
        day = start_day + timedelta(days=offset)
        if day.isoweekday() != int(slot["day"]):
            continue
        at = datetime(day.year, day.month, day.day, hour, minute, tzinfo=tz)
        if now - LOOKBACK <= at <= now + LOOKAHEAD:
            found.append(at)
    return found


def due_actions(slots, state, now, tz):
    """Daftar tindakan yang harus dilakukan sekarang, berdasarkan slot dan status tiap kemunculan."""
    actions = []
    for slot in slots:
        if not slot.get("active", True):
            continue
        lead = timedelta(minutes=int(slot.get("lead_minutes", DEFAULT_LEAD_MINUTES)))
        for at in occurrences(slot, now, tz):
            key = occurrence_key(slot, at)
            status = (state.get(key) or {}).get("status")
            if status is None:
                if at - lead <= now < at:
                    actions.append(Action("render", key, slot, at))
                elif now >= at:
                    actions.append(Action("missed", key, slot, at))
            elif status == "rendered" and now >= at:
                actions.append(Action("post", key, slot, at))
    return actions
