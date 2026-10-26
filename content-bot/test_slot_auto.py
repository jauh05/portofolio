"""Tes mode --auto: keputusan jadwal (slot_plan) dan alur render, posting, dan status (generate_content).

Tanpa ffmpeg dan tanpa Instagram: render dan posting dipalsukan.
Jalankan dari folder content-bot: python3 test_slot_auto.py
"""

import json
import sys
import tempfile
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

import slot_plan as sp
import generate_content as gc

TZ = ZoneInfo("Asia/Jakarta")
FAILS = []


def check(cond, msg):
    print(("OK   " if cond else "FAIL ") + msg)
    if not cond:
        FAILS.append(msg)


# 1. Keputusan jadwal
slot = {"id": "s1", "day": 1, "time": "16:00", "lead_minutes": 120, "active": True, "label": "Reels Jauki"}
kinds = lambda now, state=None: [a.kind for a in sp.due_actions([slot], state or {}, now, TZ)]
check(kinds(datetime(2026, 10, 26, 13, 30, tzinfo=TZ)) == [], "13.30 belum masuk jendela render")
check(kinds(datetime(2026, 10, 26, 14, 10, tzinfo=TZ)) == ["render"], "14.10 masuk jendela render (2 jam sebelum)")
state = {sp.occurrence_key(slot, datetime(2026, 10, 26, 16, 0, tzinfo=TZ)): {"status": "rendered"}}
check(kinds(datetime(2026, 10, 26, 15, 50, tzinfo=TZ), state) == [], "15.50 belum posting")
check(kinds(datetime(2026, 10, 26, 16, 5, tzinfo=TZ), state) == ["post"], "16.05 posting")
state_done = {sp.occurrence_key(slot, datetime(2026, 10, 26, 16, 0, tzinfo=TZ)): {"status": "posted"}}
check(kinds(datetime(2026, 10, 26, 16, 20, tzinfo=TZ), state_done) == [], "sudah posted tidak diulang")
check(kinds(datetime(2026, 10, 26, 10, 0, tzinfo=TZ)) == [], "slot lusa tidak diproses lebih awal")
check(sp.due_actions([dict(slot, active=False)], {}, datetime(2026, 10, 26, 14, 10, tzinfo=TZ), TZ) == [], "slot nonaktif diabaikan")
check(sp.due_actions([dict(slot, time=None)], {}, datetime(2026, 10, 26, 14, 10, tzinfo=TZ), TZ) == [], "slot tanpa jam dilewati tanpa crash")

# 2. Alur auto dengan render dan posting palsu
tmp = Path(tempfile.mkdtemp())
gc.OUT_DIR = tmp / "output"
gc.OUT_DIR.mkdir()
gc.SLOT_STATE_FILE = tmp / "slot_state.json"
gc.HISTORY_FILE = tmp / "history.json"
gc.COPY_BANK_FILE = tmp / "copy_bank.json"
slots_file = tmp / "reels_slots.json"
slots_file.write_text(json.dumps([slot]), encoding="utf-8")
import os
os.environ["REELS_SLOTS_FILE"] = str(slots_file)
gc.save_bank([{"id": "t1", "topic": "x", "overlay": "o " * 25, "caption": "c", "hashtags": ["#a", "#b", "#c", "#d", "#e"],
               "mood": "jj", "used_at": None, "used_by": None}])
gc.save_history([])

cfg = {"music_volume": 0.25, "brand_hashtags": ["#jaukiacademy"], "skip_head_s": 5, "skip_tail_s": 5}
posted = []


def fake_plan(cfg_, item, history, rng):
    return {"mood": "jj", "music_rel": "jj/x.mp3", "track": None, "seg": {"name": "a.mp4", "size": 1, "src_dur": 300.0,
            "start": 10.0, "span": 20.0, "duration": 20, "speed": 1.0}, "grade": "vivid", "vignette": False,
            "music_offset": 0.0, "music_loop": True}


def fake_render(cfg_, plan, item, bank, stamp):
    out = gc.OUT_DIR / f"reel_fake_{stamp:%H%M%S}.mp4"
    out.write_bytes(b"x")
    item["used_at"] = stamp.isoformat(timespec="seconds")
    item["used_by"] = out.name
    gc.save_bank(bank)
    return out, 20.0, "entry01"


def fake_post(out_path, caption):
    posted.append(out_path.name)
    return "MEDIA123"


gc.plan_render = fake_plan
gc.render_plan = fake_render
gc.post_video = fake_post

rng = gc.random.Random(1)
render_time = datetime(2026, 10, 26, 14, 10, tzinfo=TZ)
gc.run_auto(cfg, TZ, rng, True, render_time)
state = gc.load_slot_state()
check(len(state) == 1 and list(state.values())[0]["status"] == "rendered", "render 14.10 tercatat sebagai rendered")
check(gc.load_bank()[0]["used_at"] is not None, "teks bank ditandai terpakai setelah render")
check(len(gc.load_history()) == 1 and gc.load_history()[0]["slot_at"].startswith("2026-10-26T16:00"), "riwayat mencatat jam slot")

gc.run_auto(cfg, TZ, rng, True, render_time)
check(len(gc.load_history()) == 1 and posted == [], "menjalankan ulang sebelum jam slot tidak render dan tidak posting ganda")

post_time = datetime(2026, 10, 26, 16, 5, tzinfo=TZ)
gc.run_auto(cfg, TZ, rng, True, post_time)
check(posted and posted[0].startswith("reel_fake_"), "posting tepat setelah jam slot")
hist = gc.load_history()
check(hist[0]["instagram_media_id"] == "MEDIA123" and hist[0]["posted_at"], "media id dan waktu posting tercatat di riwayat")
gc.run_auto(cfg, TZ, rng, True, post_time)
check(len(posted) == 1, "posting tidak diulang")

# 3. Tanpa --live: hanya mencatat, tidak ada render atau posting
tmp2_state = gc.SLOT_STATE_FILE
gc.SLOT_STATE_FILE = tmp / "state_dry.json"
gc.run_auto(cfg, TZ, rng, False, datetime(2026, 10, 26, 14, 10, tzinfo=TZ))
check(not gc.SLOT_STATE_FILE.exists() and len(gc.load_history()) == 1, "tanpa --live tidak menulis status atau riwayat baru")

print("\nGAGAL:", FAILS if FAILS else "tidak ada")
sys.exit(1 if FAILS else 0)
