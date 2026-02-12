#!/usr/bin/env python3
"""
Watch inbox for new GAO recommendation CSVs and trigger orchestrator automatically.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable


ROOT = Path("/Users/josephocasio/Documents/New project")
DEFAULT_INBOX = ROOT / "inbox" / "us_sources"
DEFAULT_STATE = ROOT / "out" / "us_audit" / "watchdog" / "state.json"
DEFAULT_LOG_DIR = ROOT / "out" / "us_audit" / "watchdog" / "logs"


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def load_state(path: Path) -> dict:
    if not path.exists():
        return {"processed_hashes": [], "runs": []}
    try:
        obj = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(obj, dict):
            return {"processed_hashes": [], "runs": []}
        obj.setdefault("processed_hashes", [])
        obj.setdefault("runs", [])
        return obj
    except Exception:
        return {"processed_hashes": [], "runs": []}


def save_state(path: Path, state: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    state["updated_at_utc"] = utc_now()
    path.write_text(json.dumps(state, indent=2), encoding="utf-8")


def find_new_gao_csvs(inbox: Path, patterns: Iterable[str], processed_hashes: set[str]) -> list[Path]:
    found: list[Path] = []
    for pattern in patterns:
        for file_path in inbox.glob(pattern):
            if not file_path.is_file():
                continue
            digest = sha256_file(file_path)
            if digest in processed_hashes:
                continue
            found.append(file_path)
    # deterministic order
    return sorted(set(found))


def run_command(cmd: list[str], log_dir: Path) -> tuple[int, Path]:
    log_dir.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    log_path = log_dir / f"watchdog_trigger_{stamp}.log"
    with log_path.open("w", encoding="utf-8") as f:
        f.write(f"[{utc_now()}] command: {' '.join(cmd)}\n")
        f.flush()
        proc = subprocess.run(cmd, cwd=str(ROOT), capture_output=True, text=True)
        if proc.stdout:
            f.write("\n--- stdout ---\n")
            f.write(proc.stdout)
        if proc.stderr:
            f.write("\n--- stderr ---\n")
            f.write(proc.stderr)
        f.write(f"\n[{utc_now()}] returncode={proc.returncode}\n")
    return proc.returncode, log_path


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Watch for new GAO recommendation CSV drops and trigger orchestrator."
    )
    parser.add_argument("--inbox", default=str(DEFAULT_INBOX))
    parser.add_argument(
        "--patterns",
        default="*gao*recommend*.csv,*recommend*.csv",
        help='Comma-separated glob patterns, relative to inbox (default: "*gao*recommend*.csv,*recommend*.csv").',
    )
    parser.add_argument("--interval", type=int, default=5, help="Polling interval in seconds.")
    parser.add_argument("--cooldown-sec", type=int, default=30, help="Minimum seconds between trigger runs.")
    parser.add_argument("--once", action="store_true", help="Run one scan and exit.")
    parser.add_argument("--state-file", default=str(DEFAULT_STATE))
    parser.add_argument("--log-dir", default=str(DEFAULT_LOG_DIR))
    parser.add_argument(
        "--trigger-cmd",
        default=(
            "python3 '/Users/josephocasio/Documents/New project/tools/orchestrator.py' "
            "--start-date 2026-01-01 --fiscal-year 2025 "
            "--tx-start-date 2025-09-24 --tx-end-date 2025-09-30 "
            "--agencies 'Department of Defense,Internal Revenue Service|subtier,Social Security Administration'"
        ),
        help="Shell command to execute when new files are detected.",
    )
    args = parser.parse_args()

    inbox = Path(args.inbox)
    if not inbox.exists():
        raise SystemExit(f"Inbox not found: {inbox}")
    patterns = [p.strip() for p in args.patterns.split(",") if p.strip()]
    state_path = Path(args.state_file)
    log_dir = Path(args.log_dir)

    state = load_state(state_path)
    processed_hashes = set(state.get("processed_hashes", []))
    last_run_epoch = 0.0

    def tick() -> None:
        nonlocal last_run_epoch, state, processed_hashes
        new_files = find_new_gao_csvs(inbox=inbox, patterns=patterns, processed_hashes=processed_hashes)
        if not new_files:
            return
        now = time.time()
        if now - last_run_epoch < max(0, args.cooldown_sec):
            return

        cmd = ["zsh", "-lc", args.trigger_cmd]
        returncode, log_path = run_command(cmd=cmd, log_dir=log_dir)
        last_run_epoch = now

        file_records = []
        for fp in new_files:
            digest = sha256_file(fp)
            processed_hashes.add(digest)
            file_records.append({"path": str(fp.resolve()), "sha256": digest})

        state["processed_hashes"] = sorted(processed_hashes)
        state.setdefault("runs", []).append(
            {
                "detected_at_utc": utc_now(),
                "matched_files": file_records,
                "command": args.trigger_cmd,
                "returncode": returncode,
                "log_path": str(log_path.resolve()),
            }
        )
        save_state(state_path, state)
        print(f"{utc_now()} triggered files={len(new_files)} returncode={returncode} log={log_path}")

    if args.once:
        tick()
        return 0

    print(f"watching inbox: {inbox}")
    print(f"patterns: {patterns}")
    print(f"state: {state_path}")
    print(f"log_dir: {log_dir}")
    print(f"trigger_cmd: {args.trigger_cmd}")

    while True:
        tick()
        time.sleep(max(1, args.interval))


if __name__ == "__main__":
    raise SystemExit(main())
