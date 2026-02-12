"""Celery tasks for asynchronous step-level reasoner execution."""

from __future__ import annotations

import json
import os
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from backend.celery_app import celery


PROJECT_ROOT = Path("/Users/josephocasio/Documents/New project")
DEFAULT_REASONER_SCRIPT = PROJECT_ROOT / "tools" / "step_level_reasoner.py"
DEFAULT_LOG_DIR = PROJECT_ROOT / "out" / "us_audit" / "reasoner_jobs"
DEFAULT_OUTPUT_DIR = PROJECT_ROOT / "out" / "us_audit" / "reasoner"


def _now_stamp() -> str:
    return datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")


def _extract_diagnostics_path(stdout_text: str) -> str | None:
    """
    Parse the path printed by tools/step_level_reasoner.py:
    Diagnostics: /absolute/path/to/file.json
    """
    for line in stdout_text.splitlines():
        if line.startswith("Diagnostics: "):
            maybe_path = line.replace("Diagnostics: ", "", 1).strip()
            if maybe_path:
                return maybe_path
    return None


def _latest_reasoner_file(out_dir: Path) -> str | None:
    files = sorted(out_dir.glob("step_reasoner_*.json"), key=lambda p: p.stat().st_mtime)
    if not files:
        return None
    return str(files[-1])


@celery.task(bind=True, name="reasoner.run_step_level_reasoner")
def run_step_level_reasoner(
    self,
    *,
    matrix: str = str(PROJECT_ROOT / "out" / "us_audit" / "final_gap_closed_matrix.csv"),
    manifest: str = str(PROJECT_ROOT / "out" / "us_audit" / "manual_vault" / "manifest.json"),
    zero_point: str = "2026-03-04T17:00:00+00:00",
    institutional_operational: bool = False,
    jacobian_cmd: str = "",
    min_propulsion: float = 60.0,
    out_dir: str = str(DEFAULT_OUTPUT_DIR),
    sign: bool = False,
    signer: str = "",
    ledger: str = str(PROJECT_ROOT / "out" / "us_audit" / "reasoner" / "step_level_ledger.jsonl"),
    requested_by: str = "",
) -> dict[str, Any]:
    """
    Run the local reasoner script asynchronously.
    Returns metadata with subprocess exit code, logs, and diagnostics output path.
    """
    DEFAULT_LOG_DIR.mkdir(parents=True, exist_ok=True)
    out_path = Path(out_dir)
    out_path.mkdir(parents=True, exist_ok=True)

    stamp = _now_stamp()
    job_log = DEFAULT_LOG_DIR / f"step_reasoner_job_{stamp}.log"

    matrix = matrix or str(PROJECT_ROOT / "out" / "us_audit" / "final_gap_closed_matrix.csv")
    manifest = manifest or str(PROJECT_ROOT / "out" / "us_audit" / "manual_vault" / "manifest.json")
    out_dir = out_dir or str(DEFAULT_OUTPUT_DIR)
    ledger = ledger or str(PROJECT_ROOT / "out" / "us_audit" / "reasoner" / "step_level_ledger.jsonl")

    cmd = [
        "python3",
        str(DEFAULT_REASONER_SCRIPT),
        "--matrix",
        matrix,
        "--manifest",
        manifest,
        "--zero-point",
        zero_point,
        "--min-propulsion",
        str(min_propulsion),
        "--out-dir",
        out_dir,
        "--ledger",
        ledger,
    ]

    if institutional_operational:
        cmd.append("--institutional-operational")
    if jacobian_cmd:
        cmd.extend(["--jacobian-cmd", jacobian_cmd])
    if sign:
        cmd.append("--sign")
        if not signer:
            raise ValueError("sign=true requires signer")
        cmd.extend(["--signer", signer])

    self.update_state(
        state="STARTED",
        meta={
            "cmd": cmd,
            "started_at_utc": datetime.now(timezone.utc).isoformat(),
            "requested_by": requested_by,
        },
    )

    proc = subprocess.run(
        cmd,
        cwd=str(PROJECT_ROOT),
        capture_output=True,
        text=True,
        env=os.environ.copy(),
    )

    combined_log = (proc.stdout or "") + ("\n" + proc.stderr if proc.stderr else "")
    job_log.write_text(combined_log, encoding="utf-8")

    diagnostics_json = _extract_diagnostics_path(proc.stdout or "") or _latest_reasoner_file(out_path)

    summary: dict[str, Any] | None = None
    if diagnostics_json and Path(diagnostics_json).exists():
        try:
            summary = json.loads(Path(diagnostics_json).read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            summary = None

    return {
        "returncode": proc.returncode,
        "state_hint": "PASS" if proc.returncode == 0 else ("VETO" if proc.returncode == 2 else "HOLD_OR_ERROR"),
        "diagnostics_json": diagnostics_json,
        "job_log": str(job_log),
        "requested_by": requested_by,
        "stdout_tail": (proc.stdout or "")[-4000:],
        "stderr_tail": (proc.stderr or "")[-4000:],
        "summary": summary,
    }
