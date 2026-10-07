import json
import tempfile
import unittest
from pathlib import Path

from deploy_live_worker_preview import BACKUP_ROOT, deploy, load_manifest, sha256


class DeployPreviewTests(unittest.TestCase):
    def test_default_backup_root_is_durable(self):
        self.assertEqual(BACKUP_ROOT, Path("/home/jauhar/backups/jauki-content-bot"))

    def test_manifest_maps_resources_to_visual_memory_parent(self):
        source_root = Path(__file__).resolve().parents[1]
        entries = load_manifest(source_root)
        self.assertEqual(len(entries), 7)
        self.assertIn(
            (source_root / "resources/office-ai/visual-memory/brands.json",
             Path("/home/jauhar/resources/office-ai/visual-memory/brands.json")),
            entries,
        )

    def test_dry_run_does_not_write(self):
        with tempfile.TemporaryDirectory() as root:
            base = Path(root).resolve()
            source = base / "source.py"
            destination = base / "runtime" / "source.py"
            source.write_text("new\n")
            destination.parent.mkdir()
            destination.write_text("old\n")
            plan, backup = deploy([(source, destination)], base / "backups", apply=False)
            self.assertIsNone(backup)
            self.assertEqual(destination.read_text(), "old\n")
            self.assertFalse((base / "backups").exists())
            self.assertEqual(plan[0]["destination_sha256_before"], sha256(destination))

    def test_apply_backs_up_only_overwritten_and_records_new(self):
        with tempfile.TemporaryDirectory() as root:
            base = Path(root).resolve()
            old_source = base / "old-source.py"
            new_source = base / "new-source.py"
            old_source.write_text("replacement\n")
            new_source.write_text("introduced\n")
            runtime = base / "runtime"
            runtime.mkdir()
            old_destination = runtime / "old.py"
            new_destination = runtime / "new.py"
            old_destination.write_text("original\n")
            plan, backup_dir = deploy(
                [(old_source, old_destination), (new_source, new_destination)],
                base / "backups", apply=True,
            )
            self.assertEqual(old_destination.read_text(), "replacement\n")
            self.assertEqual(new_destination.read_text(), "introduced\n")
            self.assertEqual(Path(plan[0]["backup"]).read_text(), "original\n")
            self.assertIsNone(plan[1]["backup"])
            self.assertFalse(plan[1]["existed_before"])
            self.assertEqual(plan[0]["destination_sha256_before"], plan[0]["backup_sha256"])
            self.assertTrue(all(item["source_sha256"] == item["installed_sha256"] for item in plan))
            record = json.loads((backup_dir / "deployment-record.json").read_text())
            self.assertEqual(record["files"], plan)
            self.assertEqual(record["files"][0]["backup_sha256"], record["files"][0]["destination_sha256_before"])
            self.assertFalse(record["files"][1]["existed_before"])
            self.assertEqual(record["files"][1]["destination"], str(new_destination))

    def test_missing_source_blocks_all_copies(self):
        with tempfile.TemporaryDirectory() as root:
            base = Path(root).resolve()
            source = base / "source.py"
            source.write_text("new\n")
            destination = base / "runtime.py"
            destination.write_text("old\n")
            with self.assertRaises(FileNotFoundError):
                deploy([(source, destination), (base / "missing.py", base / "new.py")], base / "backups", apply=True)
            self.assertEqual(destination.read_text(), "old\n")
            self.assertFalse((base / "backups").exists())

    def test_symlink_destination_is_rejected(self):
        with tempfile.TemporaryDirectory() as root:
            base = Path(root).resolve()
            source = base / "source.py"
            source.write_text("new\n")
            real = base / "real.py"
            real.write_text("old\n")
            link = base / "link.py"
            link.symlink_to(real)
            with self.assertRaises(ValueError):
                deploy([(source, link)], base / "backups", apply=False)
            self.assertEqual(real.read_text(), "old\n")


if __name__ == "__main__":
    unittest.main()
