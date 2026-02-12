#!/usr/bin/env python3
"""
Orchestrate Fetch -> Seal -> Analyze for federal audit data.

Flow:
  1) Pull Treasury debt data + USAspending data
  2) Seal each artifact with provenance sidecar metadata
  3) Build GAO recommendation aging outputs
"""

from __future__ import annotations

import argparse
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from federal_ingest import FedDataPuller
from integrity_vault import seal_artifact


ROOT = Path("/Users/josephocasio/Documents/New project")
DEFAULT_INGEST_DIR = ROOT / "out" / "us_audit" / "federal_ingest"
DEFAULT_RUN_DIR = ROOT / "out" / "us_audit" / "orchestrator_runs"


def _slug(s: str) -> str:
    return "".join(ch if ch.isalnum() else "-" for ch in s.lower()).strip("-")


def _parse_agency_specs(raw: str) -> list[tuple[str, str]]:
    """
    Parse agencies into (name, tier).
    Accepts entries like:
      - "Department of Defense"
      - "Internal Revenue Service|subtier"
    """
    out: list[tuple[str, str]] = []
    for item in [x.strip() for x in raw.split(",") if x.strip()]:
        if "|" in item:
            name, tier = [p.strip() for p in item.split("|", 1)]
            tier = tier.lower()
            tier = "subtier" if tier == "subtier" else "toptier"
            out.append((name, tier))
            continue
        # sensible default mapping
        if item.lower() == "internal revenue service":
            out.append((item, "subtier"))
        else:
            out.append((item, "toptier"))
    return out


def run_orchestration(
    *,
    out_dir: Path,
    start_date: str | None,
    fiscal_year: int,
    award_limit: int,
    tx_limit: int,
    tx_start_date: str | None,
    tx_end_date: str | None,
    agencies: list[tuple[str, str]],
    parser_version: str,
    run_gao_aging: bool,
    run_priority_saturation: bool,
    stop_on_error: bool,
) -> dict[str, Any]:
    run_started = datetime.now(timezone.utc).isoformat()
    run_id = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    puller = FedDataPuller(base_dir=out_dir)
    actions: list[dict[str, Any]] = []
    errors: list[dict[str, Any]] = []

    def _record_ok(step: str, artifact: Path, source_url: str, doc_id: str, meta_path: Path) -> None:
        actions.append(
            {
                "step": step,
                "status": "ok",
                "artifact": str(artifact),
                "source_url": source_url,
                "doc_id": doc_id,
                "meta": str(meta_path),
            }
        )

    def _record_err(step: str, exc: Exception) -> None:
        errors.append({"step": step, "error": str(exc)})

    # 1) Pull + seal Treasury debt
    try:
        artifact, source = puller.get_fiscal_debt(start_date=start_date, page_size=1)
        doc_id = "TREAS-DEBT-001"
        meta = seal_artifact(
            file_path=artifact,
            source_url=source,
            doc_id=doc_id,
            parser_version=parser_version,
        )
        _record_ok("treasury_debt", artifact, source, doc_id, meta)
    except Exception as exc:  # noqa: BLE001
        _record_err("treasury_debt", exc)
        if stop_on_error:
            return _finalize(run_id, run_started, actions, errors)

    # 2) Pull + seal USAspending award + transaction for each agency
    for agency, tier in agencies:
        agency_slug = _slug(agency)
        try:
            artifact, source = puller.search_spending_by_agency_award(
                agency_name=agency,
                tier=tier,
                fiscal_year=fiscal_year,
                limit=award_limit,
            )
            doc_id = f"USASPEND-AWARD-{agency_slug.upper()}-FY{fiscal_year}"
            meta = seal_artifact(
                file_path=artifact,
                source_url=source,
                doc_id=doc_id,
                parser_version=parser_version,
            )
            _record_ok(f"usaspending_award:{agency}", artifact, source, doc_id, meta)
        except Exception as exc:  # noqa: BLE001
            _record_err(f"usaspending_award:{agency}", exc)
            if stop_on_error:
                return _finalize(run_id, run_started, actions, errors)

        if tx_start_date and tx_end_date:
            try:
                artifact, source = puller.search_spending_by_agency_transaction(
                    agency_name=agency,
                    tier=tier,
                    start_date=tx_start_date,
                    end_date=tx_end_date,
                    limit=tx_limit,
                )
                doc_id = (
                    f"USASPEND-TX-{agency_slug.upper()}-{tx_start_date.replace('-', '')}"
                    f"-{tx_end_date.replace('-', '')}"
                )
                meta = seal_artifact(
                    file_path=artifact,
                    source_url=source,
                    doc_id=doc_id,
                    parser_version=parser_version,
                )
                _record_ok(f"usaspending_transaction:{agency}", artifact, source, doc_id, meta)
            except Exception as exc:  # noqa: BLE001
                _record_err(f"usaspending_transaction:{agency}", exc)
                if stop_on_error:
                    return _finalize(run_id, run_started, actions, errors)

    # 3) Analyze GAO recommendation aging
    if run_gao_aging:
        try:
            cmd = [
                "python3",
                str(ROOT / "tools" / "build_gao_recommendation_aging.py"),
            ]
            proc = subprocess.run(
                cmd,
                cwd=str(ROOT),
                capture_output=True,
                text=True,
                check=False,
            )
            actions.append(
                {
                    "step": "gao_recommendation_aging",
                    "status": "ok" if proc.returncode == 0 else "error",
                    "command": cmd,
                    "returncode": proc.returncode,
                    "stdout_tail": (proc.stdout or "")[-2000:],
                    "stderr_tail": (proc.stderr or "")[-2000:],
                }
            )
            if proc.returncode != 0:
                errors.append(
                    {
                        "step": "gao_recommendation_aging",
                        "error": f"non-zero return code {proc.returncode}",
                    }
                )
                if stop_on_error:
                    return _finalize(run_id, run_started, actions, errors)
        except Exception as exc:  # noqa: BLE001
            _record_err("gao_recommendation_aging", exc)
            if stop_on_error:
                return _finalize(run_id, run_started, actions, errors)

    # 4) Priority saturation ranking
    if run_priority_saturation:
        try:
            cmd = [
                "python3",
                str(ROOT / "tools" / "priority_saturation_analysis.py"),
            ]
            proc = subprocess.run(
                cmd,
                cwd=str(ROOT),
                capture_output=True,
                text=True,
                check=False,
            )
            actions.append(
                {
                    "step": "priority_saturation_analysis",
                    "status": "ok" if proc.returncode == 0 else "error",
                    "command": cmd,
                    "returncode": proc.returncode,
                    "stdout_tail": (proc.stdout or "")[-2000:],
                    "stderr_tail": (proc.stderr or "")[-2000:],
                }
            )
            if proc.returncode != 0:
                errors.append(
                    {
                        "step": "priority_saturation_analysis",
                        "error": f"non-zero return code {proc.returncode}",
                    }
                )
                if stop_on_error:
                    return _finalize(run_id, run_started, actions, errors)
        except Exception as exc:  # noqa: BLE001
            _record_err("priority_saturation_analysis", exc)
            if stop_on_error:
                return _finalize(run_id, run_started, actions, errors)

    return _finalize(run_id, run_started, actions, errors)


def _finalize(
    run_id: str,
    run_started_at_utc: str,
    actions: list[dict[str, Any]],
    errors: list[dict[str, Any]],
) -> dict[str, Any]:
    return {
        "run_id": run_id,
        "run_started_at_utc": run_started_at_utc,
        "run_finished_at_utc": datetime.now(timezone.utc).isoformat(),
        "actions_count": len(actions),
        "errors_count": len(errors),
        "actions": actions,
        "errors": errors,
        "status": "ok" if not errors else "partial_error",
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Run Fetch -> Seal -> Analyze for federal audit inputs.")
    parser.add_argument(
        "--out-dir",
        default=str(DEFAULT_INGEST_DIR),
        help="Artifact output directory.",
    )
    parser.add_argument("--start-date", default=None, help="Treasury debt filter lower bound (YYYY-MM-DD).")
    parser.add_argument("--fiscal-year", type=int, default=2025)
    parser.add_argument("--award-limit", type=int, default=100)
    parser.add_argument("--tx-limit", type=int, default=100)
    parser.add_argument("--tx-start-date", default=None, help="Optional transaction pull start date (YYYY-MM-DD).")
    parser.add_argument("--tx-end-date", default=None, help="Optional transaction pull end date (YYYY-MM-DD).")
    parser.add_argument(
        "--agencies",
        default="Department of Defense,Department of the Treasury",
        help='Comma-separated top-tier agency names. Example: "Department of Defense,Department of the Treasury"',
    )
    parser.add_argument("--parser-version", default="1.0.0")
    parser.add_argument("--skip-gao-aging", action="store_true")
    parser.add_argument("--skip-priority-saturation", action="store_true")
    parser.add_argument("--stop-on-error", action="store_true")
    args = parser.parse_args()

    agencies = _parse_agency_specs(args.agencies)
    if (args.tx_start_date and not args.tx_end_date) or (args.tx_end_date and not args.tx_start_date):
        raise SystemExit("Use both --tx-start-date and --tx-end-date together.")

    summary = run_orchestration(
        out_dir=Path(args.out_dir),
        start_date=args.start_date,
        fiscal_year=args.fiscal_year,
        award_limit=args.award_limit,
        tx_limit=args.tx_limit,
        tx_start_date=args.tx_start_date,
        tx_end_date=args.tx_end_date,
        agencies=agencies,
        parser_version=args.parser_version,
        run_gao_aging=not args.skip_gao_aging,
        run_priority_saturation=not args.skip_priority_saturation,
        stop_on_error=args.stop_on_error,
    )

    DEFAULT_RUN_DIR.mkdir(parents=True, exist_ok=True)
    out = DEFAULT_RUN_DIR / f"orchestrator_run_{summary['run_id']}.json"
    out.write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(f"status: {summary['status']}")
    print(f"actions: {summary['actions_count']}")
    print(f"errors: {summary['errors_count']}")
    print(f"summary: {out}")
    return 0 if summary["errors_count"] == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
