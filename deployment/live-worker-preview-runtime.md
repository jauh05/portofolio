# Live worker preview: runtime mapping and future deployment

This branch is a deployment design. Nothing here deploys automatically. The preview bridge is offline; generation and publishing remain on the existing worker paths.

## Dependency and path audit

`office_social_worker.py` imports the existing `office_bridge.py` at module load. Its generation and publishing handlers import the existing `bot.py` only when those actions run. The `preview_visual` path imports `visual_preview_bridge.py`, which calls `office_social_worker.preview_visual_command()`. That method imports `visual_worker_preview.py`, which imports `visual_memory.py`.

`visual_memory.py` sets `MEMORY_DIR = Path(__file__).resolve().parents[1] / "resources" / "office-ai" / "visual-memory"`. In the repository that is `<repo>/resources/office-ai/visual-memory`. When `visual_memory.py` is installed at `/home/jauhar/jauki-content-bot/visual_memory.py`, `parents[1]` is `/home/jauhar`, so the required location is **`/home/jauhar/resources/office-ai/visual-memory`**. The working directory does not change this. The three JSON files loaded by code are `brands.json`, `visual-template-memory.json`, and `selection-rules.json`; the Markdown previews and README are not runtime inputs. No third party Python package is imported by this preview path.

`office_bridge.py` remains a runtime dependency, but it already belongs to the live worker and is not part of this bundle. Its module import reads bridge settings from environment or `.env`; use temporary non-secret environment overrides for offline import checks below. The helper itself never opens `.env` or invokes the worker.

## Explicit manifest

The seven exact source and destination paths are in [live-worker-preview-manifest.json](live-worker-preview-manifest.json). The Python destinations are the four named files directly under `/home/jauhar/jauki-content-bot/`. The JSON destinations are the three named files directly under `/home/jauhar/resources/office-ai/visual-memory/`. No whole-directory copy is allowed.

Before a future deployment, inspect the live file inventory using the helper's dry-run output. This repository has no verified inventory of `/home/jauhar`, so **which destinations will be overwritten and which are new is not yet known**. The output's `existed_before` field is the authoritative per-file inventory for that run. `bot.py`, `office_bridge.py`, `.env`, other runtime files, storage, and databases are excluded from the manifest.

## Future deployment procedure

Run these commands on the host as the owner of the existing runtime files, using a reviewed checkout of the desired commit. Do not run `--apply` until the dry-run inventory and source revision have been reviewed. The helper requires an explicit absolute source root; the fixed absolute destinations are in the manifest.

```sh
python3 -B deployment/deploy_live_worker_preview.py --source-root /absolute/path/to/reviewed/repo
python3 -B deployment/deploy_live_worker_preview.py --source-root /absolute/path/to/reviewed/repo --apply
```

Dry-run performs no writes and prints SHA256 of each source and each existing destination. Apply preflights all seven files, refuses missing or nonregular sources/destinations and symlink paths, and refuses existing destinations owned by another account. It creates `/home/jauhar/backups/jauki-content-bot/<UTC timestamp>/deployment-record.json`. Only overwritten files get backup copies there; new files have `existed_before: false` and no backup. Each backup hash must equal the original destination hash before installation. New content is staged in the destination directory and atomically installed. For an overwritten file, its existing owner, group, and mode are retained (or installation stops if the group cannot be retained); a new file receives the source mode. After each install, the recorded installed SHA256 must equal the recorded source SHA256. A failure stops the operation and leaves the record for inspection and selective rollback. The helper never restarts services, accesses network/providers, or reads/writes secrets.

Review the record and ensure every entry says `installed: true` and `installed_sha256 == source_sha256`. Stop here if any entry fails verification.

## Pre-restart offline validation

From the runtime directory, use the worker's Python interpreter if it has a dedicated virtual environment. The two dummy bridge values prevent `office_bridge` from reading `.env` on import. The command does not make HTTP requests, call a provider, generate an image, or restart the worker.

```sh
cd /home/jauhar/jauki-content-bot
OFFICE_BRIDGE_URL=http://127.0.0.1:9 OFFICE_BRIDGE_TOKEN=offline-check PYTHONDONTWRITEBYTECODE=1 python3 -B - <<'PY'
import office_social_worker, visual_preview_bridge, visual_worker_preview, visual_memory
from visual_memory import VisualBrandMemory, VisualTemplateRepository, VisualTemplateSelector
assert visual_memory.MEMORY_DIR.as_posix() == '/home/jauhar/resources/office-ai/visual-memory'
assert VisualBrandMemory().get('jauki')['palette']['primary'] == '#173D26'
assert VisualBrandMemory().get('kauiz')['palette']['primary'] == '#1F49E7'
assert len(VisualTemplateRepository().templates) == 27
assert VisualTemplateSelector().rules
print('Imports and all three JSON resources: OK')
PY
```

The above constructors load `brands.json`, `visual-template-memory.json`, and `selection-rules.json` respectively. Restart only `jauki-content.service` in a separately approved deployment step after these checks pass.

## Future live preview checks

After a future service restart, submit `preview_visual` commands to the normal Social worker command channel with `agent_id: "jauki-social"` and these payloads:

```json
{"brand":"jauki","topic":"Cara Memilih Jurnal yang Tepat","output_mode":"feed_portrait","dry_run":true}
{"brand":"kauiz","topic":"Fokus Satu Tugas dalam Satu Waktu","output_mode":"story","dry_run":true}
```

The first must return `primary_palette: "#173D26"`, the second `"#1F49E7"`; both must report `provider_called: false`. A payload with `dry_run: false` must be rejected. No generation fallback, real image, or content publication is part of this check. The bridge's standalone CLI can run the same JSON payloads offline before connecting a command channel.

## Rollback contract

Use the specific `deployment-record.json` from the failed or reversed deployment. If service execution needs to be paused, stop **only** `jauki-content.service` first. For each record entry, compare the current destination SHA256 with `installed_sha256` (or `source_sha256` if a failure happened immediately after installation). For `existed_before: true`, verify the backup SHA256 equals `destination_sha256_before`, then restore that backup to that one destination and verify the restored SHA256. If an item was never installed and its current hash already equals the original hash, leave it alone. For `existed_before: false`, remove that named destination **only** when the record confirms it was new and its current hash matches the recorded source hash. Review each such removal explicitly. If a hash differs unexpectedly, stop and investigate instead of overwriting. After verification, start only `jauki-content.service` if it was stopped. Never restore or remove `.env`, `bot.py`, `office_bridge.py`, storage, databases, or unrelated files; never reset the Git checkout.

For each overwritten entry, the concrete operations are `sha256sum <backup> <destination>`, `cp -p -- <backup> <destination>`, then `sha256sum <destination>`; use the exact absolute paths and expected hashes from that entry in the record. For each confirmed new entry, use `sha256sum <destination>` then `rm -- <destination>` only after reviewing `existed_before: false` and matching the recorded source hash. Do not use a recursive copy or remove command. If a stopped service needs to be resumed, use `systemctl start jauki-content.service` only after all selected files have been checked.

The backup directory is outside the runtime directory and must be retained until post-deployment checks and the rollback window are complete.
