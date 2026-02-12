#!/usr/bin/env python3
"""
Watch an inbox directory and ingest files into a vault with SHA-256 manifesting.

Default behavior is COPY (non-destructive). Use --move to move files instead.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def load_manifest(path: Path) -> Dict[str, object]:
    if not path.exists():
        return {"generated_at_utc": utc_now(), "entries": []}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(data, dict):
            raise ValueError("manifest root must be object")
        data.setdefault("entries", [])
        return data
    except Exception:
        # Preserve bad file for debugging
        backup = path.with_suffix(path.suffix + ".corrupt")
        shutil.copy2(path, backup)
        return {"generated_at_utc": utc_now(), "entries": []}


def save_manifest(path: Path, data: Dict[str, object]) -> None:
    data["updated_at_utc"] = utc_now()
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2), encoding="utf-8")


def index_by_hash(entries: List[Dict[str, object]]) -> Dict[str, Dict[str, object]]:
    out: Dict[str, Dict[str, object]] = {}
    for e in entries:
        h = e.get("sha256")
        if isinstance(h, str):
            out[h] = e
    return out


def should_skip(path: Path) -> bool:
    if not path.is_file():
        return True
    if path.name.startswith("."):
        return True
    # Skip local manifests/log files from re-ingest loops.
    lower = path.name.lower()
    if lower.endswith(".json") and "manifest" in lower:
        return True
    return False


def ingest_once(
    inbox: Path,
    vault: Path,
    manifest_path: Path,
    move_files: bool,
) -> Dict[str, int]:
    manifest = load_manifest(manifest_path)
    entries: List[Dict[str, object]] = list(manifest.get("entries", []))
    seen = index_by_hash(entries)

    vault.mkdir(parents=True, exist_ok=True)
    stats = {"processed": 0, "ingested": 0, "duplicate": 0, "skipped": 0, "errors": 0}

    for src in sorted(inbox.iterdir()):
        if should_skip(src):
            stats["skipped"] += 1
            continue

        stats["processed"] += 1
        try:
            digest = sha256_file(src)
            if digest in seen:
                stats["duplicate"] += 1
                continue

            dest = vault / src.name
            # Avoid collisions by suffixing timestamp.
            if dest.exists():
                stem = dest.stem
                suffix = dest.suffix
                dest = vault / f"{stem}_{int(time.time())}{suffix}"

            if move_files:
                shutil.move(str(src), str(dest))
                mode = "move"
            else:
                shutil.copy2(src, dest)
                mode = "copy"

            entry = {
                "id": dest.name,
                "source_name": src.name,
                "source_path": str(src),
                "vault_path": str(dest),
                "sha256": digest,
                "ingest_mode": mode,
                "status": "success",
                "timestamp_utc": utc_now(),
            }
            entries.append(entry)
            seen[digest] = entry
            stats["ingested"] += 1
        except Exception as exc:
            entries.append(
                {
                    "id": src.name,
                    "source_name": src.name,
                    "source_path": str(src),
                    "status": "error",
                    "error": str(exc),
                    "timestamp_utc": utc_now(),
                }
            )
            stats["errors"] += 1

    manifest["entries"] = entries
    save_manifest(manifest_path, manifest)
    return stats


def main() -> None:
    parser = argparse.ArgumentParser(description="Watch and ingest manual source drops.")
    parser.add_argument(
        "--inbox",
        default="/Users/josephocasio/Documents/New project/inbox/us_sources",
        help="Inbox directory to watch.",
    )
    parser.add_argument(
        "--vault",
        default="/Users/josephocasio/Documents/New project/out/us_audit/manual_vault/raw",
        help="Destination vault directory.",
    )
    parser.add_argument(
        "--manifest",
        default="/Users/josephocasio/Documents/New project/out/us_audit/manual_vault/manifest.json",
        help="Manifest path.",
    )
    parser.add_argument("--interval", type=int, default=5, help="Polling interval seconds.")
    parser.add_argument("--once", action="store_true", help="Run one ingest pass and exit.")
    parser.add_argument("--move", action="store_true", help="Move files instead of copying.")
    args = parser.parse_args()

    inbox = Path(args.inbox)
    vault = Path(args.vault)
    manifest = Path(args.manifest)
    if not inbox.exists():
        raise SystemExit(f"Inbox not found: {inbox}")

    if args.once:
        stats = ingest_once(inbox, vault, manifest, move_files=args.move)
        print(stats)
        return

    print(f"Watching: {inbox}")
    print(f"Vault:    {vault}")
    print(f"Manifest: {manifest}")
    while True:
        stats = ingest_once(inbox, vault, manifest, move_files=args.move)
        if stats["ingested"] or stats["errors"]:
            print(f"{utc_now()} stats={stats}")
        time.sleep(max(1, args.interval))


if __name__ == "__main__":
    main()
