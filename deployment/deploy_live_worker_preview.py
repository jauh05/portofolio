"""Explicit, offline, file-only deployment of the social worker preview bundle.

Dry-run is the default. Applying never restarts a service or contacts a provider.
Run as the account that owns the runtime files, from a trusted repository checkout.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import shutil
import stat
import tempfile
from datetime import datetime, timezone
from pathlib import Path

MANIFEST = Path(__file__).with_name("live-worker-preview-manifest.json")
BACKUP_ROOT = Path("/home/jauhar/backups/jauki-content-bot")
RUNTIME_ROOT = Path("/home/jauhar/jauki-content-bot")
RESOURCE_ROOT = Path("/home/jauhar/resources/office-ai/visual-memory")
ALLOWED_PAIRS = {
    ("JaukiContentBot-integration/" + name, str(RUNTIME_ROOT / name))
    for name in (
        "office_social_worker.py", "visual_preview_bridge.py",
        "visual_worker_preview.py", "visual_memory.py",
    )
} | {
    ("resources/office-ai/visual-memory/" + name, str(RESOURCE_ROOT / name))
    for name in ("brands.json", "visual-template-memory.json", "selection-rules.json")
}


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def require_regular(path: Path, *, existing: bool) -> None:
    if existing:
        mode = path.lstat().st_mode
        if not stat.S_ISREG(mode):
            raise ValueError(f"Refusing non-regular file: {path}")
    elif path.is_symlink():
        raise ValueError(f"Refusing symlink: {path}")


def require_safe_parents(path: Path) -> None:
    for parent in path.parents:
        if parent.is_symlink():
            raise ValueError(f"Refusing symlink parent: {parent}")


def load_manifest(source_root: Path) -> list[tuple[Path, Path]]:
    if not source_root.is_absolute() or source_root.is_symlink() or not source_root.is_dir():
        raise ValueError("--source-root must name an existing absolute directory without a symlink")
    raw = json.loads(MANIFEST.read_text(encoding="utf-8"))
    entries = raw["files"]
    if {(item["source"], item["destination"]) for item in entries} != ALLOWED_PAIRS or len(entries) != len(ALLOWED_PAIRS):
        raise ValueError("Manifest differs from the seven reviewed source/destination pairs")
    result = []
    for item in entries:
        relative = Path(item["source"])
        destination = Path(item["destination"])
        if relative.is_absolute() or ".." in relative.parts:
            raise ValueError(f"Unsafe source: {relative}")
        result.append((source_root / relative, destination))
    if len({destination for _, destination in result}) != len(result):
        raise ValueError("Duplicate destination in manifest")
    return result


def inspect(entries: list[tuple[Path, Path]], *, applying: bool) -> list[dict]:
    plan = []
    for source, destination in entries:
        require_safe_parents(source)
        if applying:
            require_safe_parents(destination)
        if not source.exists():
            raise FileNotFoundError(source)
        require_regular(source, existing=True)
        exists = destination.exists() or destination.is_symlink()
        require_regular(destination, existing=exists)
        if exists and destination.stat().st_uid != os.geteuid():
            raise PermissionError(f"Run as the owner of existing destination: {destination}")
        plan.append({
            "source": str(source),
            "destination": str(destination),
            "existed_before": exists,
            "source_sha256": sha256(source),
            "destination_sha256_before": sha256(destination) if exists else None,
            "backup": None,
            "backup_sha256": None,
            "installed_sha256": None,
            "installed": False,
        })
    return plan


def write_record(path: Path, plan: list[dict]) -> None:
    temporary = path.with_suffix(".tmp")
    temporary.write_text(json.dumps({"files": plan}, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)


def deploy(entries: list[tuple[Path, Path]], backup_root: Path, *, apply: bool) -> tuple[list[dict], Path | None]:
    plan = inspect(entries, applying=apply)
    if not apply:
        return plan, None

    require_safe_parents(backup_root)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S.%fZ")
    backup_dir = backup_root / stamp
    backup_dir.mkdir(parents=True, exist_ok=False)
    record = backup_dir / "deployment-record.json"
    write_record(record, plan)
    for index, item in enumerate(plan):
        source = Path(item["source"])
        destination = Path(item["destination"])
        if sha256(source) != item["source_sha256"]:
            raise RuntimeError(f"Source changed since preflight: {source}")
        if item["existed_before"]:
            require_regular(destination, existing=True)
            if sha256(destination) != item["destination_sha256_before"]:
                raise RuntimeError(f"Destination changed since preflight: {destination}")
            backup = backup_dir / f"{index:02d}-{destination.name}"
            shutil.copy2(destination, backup)
            item["backup"] = str(backup)
            item["backup_sha256"] = sha256(backup)
            if item["backup_sha256"] != item["destination_sha256_before"]:
                raise RuntimeError(f"Backup hash mismatch: {backup}")
        elif destination.exists() or destination.is_symlink():
            raise RuntimeError(f"New destination appeared since preflight: {destination}")
        write_record(record, plan)

        destination.parent.mkdir(parents=True, exist_ok=True)
        fd, temporary_name = tempfile.mkstemp(prefix=f".{destination.name}.", dir=destination.parent)
        temporary = Path(temporary_name)
        try:
            with os.fdopen(fd, "wb") as target, source.open("rb") as original:
                shutil.copyfileobj(original, target)
                target.flush()
                os.fsync(target.fileno())
            mode_from = Path(item["backup"]) if item["existed_before"] else source
            temporary.chmod(stat.S_IMODE(mode_from.stat().st_mode))
            if item["existed_before"]:
                original_group = destination.stat().st_gid
                if temporary.stat().st_gid != original_group:
                    os.chown(temporary, -1, original_group)
            if sha256(temporary) != item["source_sha256"]:
                raise RuntimeError(f"Staged hash mismatch: {source}")
            temporary.replace(destination)
        finally:
            temporary.unlink(missing_ok=True)
        item["installed_sha256"] = sha256(destination)
        item["installed"] = True
        write_record(record, plan)
        if item["installed_sha256"] != item["source_sha256"]:
            raise RuntimeError(f"Installed hash mismatch: {destination}")
    return plan, backup_dir


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source-root", type=Path, required=True, help="Absolute path to the reviewed repo checkout")
    parser.add_argument("--apply", action="store_true", help="Copy the seven allowlisted files; default is dry-run")
    args = parser.parse_args()
    plan, backup_dir = deploy(load_manifest(args.source_root), BACKUP_ROOT, apply=args.apply)
    print(json.dumps({"mode": "apply" if args.apply else "dry-run", "backup_dir": str(backup_dir) if backup_dir else None, "files": plan}, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
