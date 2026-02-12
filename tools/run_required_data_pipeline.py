#!/usr/bin/env python3
"""
Run local audit scripts from required_data_manifest.yaml and produce a single run report.
"""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import yaml


ROOT = Path("/Users/josephocasio/Documents/New project")
DEFAULT_MANIFEST = ROOT / "required_data_manifest.yaml"
DEFAULT_RUNS_DIR = ROOT / "out" / "us_audit" / "pipeline_runs"


@dataclass
class StageResult:
    stage_id: str
    command: list[str]
    returncode: int
    started_at_utc: str
    ended_at_utc: str
    stdout_tail: str
    stderr_tail: str
    outputs_ok: bool
    output_checks: list[dict[str, Any]]


def load_manifest(path: Path) -> dict[str, Any]:
    with path.open("r", encoding="utf-8") as f:
        manifest = yaml.safe_load(f)
    if not isinstance(manifest, dict):
        raise ValueError("Manifest did not parse as an object.")
    return manifest


def resolve_outputs(expected_outputs: list[str], root: Path) -> list[dict[str, Any]]:
    checks: list[dict[str, Any]] = []
    for item in expected_outputs:
        p = (root / item).resolve()
        checks.append(
            {
                "path": str(p),
                "exists": p.exists(),
                "is_dir": p.is_dir() if p.exists() else False,
            }
        )
    return checks


def run_stage(stage: dict[str, Any], root: Path, dry_run: bool) -> StageResult:
    stage_id = str(stage["id"])
    script = str(stage["script"])
    args = [str(a) for a in stage.get("args", [])]
    cmd = ["python3", str((root / script).resolve()), *args]
    started = datetime.now(timezone.utc).isoformat()

    if dry_run:
        checks = resolve_outputs(stage.get("expected_outputs", []), root)
        return StageResult(
            stage_id=stage_id,
            command=cmd,
            returncode=0,
            started_at_utc=started,
            ended_at_utc=datetime.now(timezone.utc).isoformat(),
            stdout_tail="[dry-run] skipped execution",
            stderr_tail="",
            outputs_ok=all(c["exists"] for c in checks) if checks else True,
            output_checks=checks,
        )

    proc = subprocess.run(
        cmd,
        cwd=str(root),
        capture_output=True,
        text=True,
        env=None,
    )
    ended = datetime.now(timezone.utc).isoformat()
    checks = resolve_outputs(stage.get("expected_outputs", []), root)
    outputs_ok = all(c["exists"] for c in checks) if checks else True

    return StageResult(
        stage_id=stage_id,
        command=cmd,
        returncode=proc.returncode,
        started_at_utc=started,
        ended_at_utc=ended,
        stdout_tail=(proc.stdout or "")[-4000:],
        stderr_tail=(proc.stderr or "")[-4000:],
        outputs_ok=outputs_ok,
        output_checks=checks,
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Run required-data pipeline from YAML manifest.")
    parser.add_argument("--manifest", default=str(DEFAULT_MANIFEST), help="Path to required_data_manifest.yaml")
    parser.add_argument("--dry-run", action="store_true", help="Print and validate stages without executing scripts.")
    parser.add_argument("--stop-on-error", action="store_true", help="Stop after the first non-zero stage.")
    parser.add_argument(
        "--only",
        default="",
        help="Comma-separated stage ids to run (e.g. fetch_us_baseline,build_gap_closed_matrix)",
    )
    args = parser.parse_args()

    manifest_path = Path(args.manifest).resolve()
    manifest = load_manifest(manifest_path)

    root = Path(manifest.get("project_root", ROOT)).resolve()
    stages = manifest.get("pipeline", {}).get("stages", [])
    if not isinstance(stages, list):
        raise ValueError("pipeline.stages must be a list.")

    only = {s.strip() for s in args.only.split(",") if s.strip()}
    active_stages = []
    for s in sorted(stages, key=lambda x: int(x.get("order", 9999))):
        if not s.get("enabled", True):
            continue
        sid = str(s.get("id", ""))
        if only and sid not in only:
            continue
        active_stages.append(s)

    run_started = datetime.now(timezone.utc).isoformat()
    results: list[dict[str, Any]] = []
    failed = False

    for stage in active_stages:
        res = run_stage(stage, root=root, dry_run=args.dry_run)
        result_obj = {
            "stage_id": res.stage_id,
            "command": res.command,
            "returncode": res.returncode,
            "outputs_ok": res.outputs_ok,
            "output_checks": res.output_checks,
            "started_at_utc": res.started_at_utc,
            "ended_at_utc": res.ended_at_utc,
            "stdout_tail": res.stdout_tail,
            "stderr_tail": res.stderr_tail,
        }
        results.append(result_obj)
        print(f"[{res.stage_id}] returncode={res.returncode} outputs_ok={res.outputs_ok}")
        if res.returncode != 0:
            failed = True
            if args.stop_on_error:
                break

    summary = {
        "manifest_path": str(manifest_path),
        "project_root": str(root),
        "run_started_at_utc": run_started,
        "run_finished_at_utc": datetime.now(timezone.utc).isoformat(),
        "dry_run": bool(args.dry_run),
        "stop_on_error": bool(args.stop_on_error),
        "stage_count": len(results),
        "failed_stage_count": sum(1 for r in results if r["returncode"] != 0),
        "stages": results,
    }

    DEFAULT_RUNS_DIR.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    out_json = DEFAULT_RUNS_DIR / f"required_data_run_{stamp}.json"
    out_json.write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(f"summary: {out_json}")

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())

