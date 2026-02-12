#!/usr/bin/env python3
"""
SSA depletion-date sensitivity sweep.

Purpose:
- Provide a reproducible, explicit sensitivity model around a baseline
  (for example: 3.82% payroll deficit -> depletion year 2033).
- Compute a local Jacobian (d depletion_year / d deficit_pct).

Important:
- This is a scenario tool, not an official actuarial projection.
- If historical trustee data is provided, the slope is fit from data.
- Otherwise, an assumed slope is used (configurable).
"""

from __future__ import annotations

import argparse
import csv
import json
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import List, Optional, Tuple

import numpy as np


@dataclass
class Model:
    alpha: float
    beta: float  # Jacobian: d(year)/d(deficit_pct)
    mode: str


def load_historical(path: Path) -> List[Tuple[float, float]]:
    rows: List[Tuple[float, float]] = []
    with path.open("r", encoding="utf-8", newline="") as f:
        reader = csv.DictReader(f)
        for r in reader:
            try:
                d = float(r["deficit_pct"])
                y = float(r["depletion_year"])
            except (KeyError, ValueError):
                continue
            rows.append((d, y))
    return rows


def fit_linear(points: List[Tuple[float, float]]) -> Model:
    x = np.array([p[0] for p in points], dtype=float)
    y = np.array([p[1] for p in points], dtype=float)
    A = np.column_stack([np.ones_like(x), x])
    alpha, beta = np.linalg.lstsq(A, y, rcond=None)[0]
    return Model(alpha=float(alpha), beta=float(beta), mode="fit_from_historical")


def assumed_model(base_deficit: float, base_year: float, beta: float) -> Model:
    # y = alpha + beta*x and must pass through baseline point.
    alpha = base_year - beta * base_deficit
    return Model(alpha=float(alpha), beta=float(beta), mode="assumed_slope")


def predict(model: Model, deficit_pct: float) -> float:
    return model.alpha + model.beta * deficit_pct


def main() -> None:
    parser = argparse.ArgumentParser(description="Run SSA depletion-date sensitivity sweep.")
    parser.add_argument("--base-deficit", type=float, default=3.82)
    parser.add_argument("--base-depletion-year", type=float, default=2033.0)
    parser.add_argument("--assumed-beta", type=float, default=-1.5,
                        help="Assumed d(year)/d(deficit_pct) if no historical file is given.")
    parser.add_argument("--grid-min", type=float, default=2.5)
    parser.add_argument("--grid-max", type=float, default=5.5)
    parser.add_argument("--grid-step", type=float, default=0.05)
    parser.add_argument("--historical-csv", type=str, default="",
                        help="Optional CSV with columns: deficit_pct,depletion_year")
    parser.add_argument(
        "--out-dir",
        type=str,
        default="/Users/josephocasio/Documents/New project/out/us_audit/ssa_hhs_deep_dive",
    )
    args = parser.parse_args()

    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)

    model: Model
    historical_points: List[Tuple[float, float]] = []

    if args.historical_csv:
        hp = Path(args.historical_csv)
        if hp.exists():
            historical_points = load_historical(hp)
        if len(historical_points) >= 3:
            model = fit_linear(historical_points)
        else:
            model = assumed_model(args.base_deficit, args.base_depletion_year, args.assumed_beta)
    else:
        model = assumed_model(args.base_deficit, args.base_depletion_year, args.assumed_beta)

    # Sweep grid.
    grid = np.arange(args.grid_min, args.grid_max + 1e-9, args.grid_step)
    rows = []
    for d in grid:
        y = predict(model, float(d))
        rows.append(
            {
                "deficit_pct": round(float(d), 4),
                "estimated_depletion_year": round(float(y), 4),
                "delta_years_vs_baseline": round(float(y - args.base_depletion_year), 4),
            }
        )

    # Write CSV.
    csv_path = out_dir / "ssa_jacobian_sweep.csv"
    with csv_path.open("w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(
            f,
            fieldnames=["deficit_pct", "estimated_depletion_year", "delta_years_vs_baseline"],
        )
        w.writeheader()
        w.writerows(rows)

    # Write metadata JSON.
    meta = {
        "generated_at_utc": datetime.now(timezone.utc).isoformat(),
        "baseline": {
            "deficit_pct": args.base_deficit,
            "depletion_year": args.base_depletion_year,
        },
        "model": {
            "mode": model.mode,
            "alpha": model.alpha,
            "beta_jacobian_dyear_ddeficit": model.beta,
        },
        "historical_points_used": historical_points,
        "notes": [
            "Scenario model only. Not an official SSA actuarial forecast.",
            "Negative beta means higher deficit implies earlier depletion year.",
        ],
    }
    json_path = out_dir / "ssa_jacobian_sweep_meta.json"
    json_path.write_text(json.dumps(meta, indent=2), encoding="utf-8")

    # Small markdown briefing.
    def val(d: float) -> float:
        return round(predict(model, d), 2)

    md = []
    md.append("# SSA Jacobian Sensitivity Sweep")
    md.append("")
    md.append(f"Generated: {meta['generated_at_utc']}")
    md.append("")
    md.append("## Baseline")
    md.append(f"- Deficit: **{args.base_deficit}%** of taxable payroll")
    md.append(f"- Depletion year baseline: **{args.base_depletion_year:.0f}**")
    md.append("")
    md.append("## Local Jacobian")
    md.append(f"- **d(year)/d(deficit_pct) = {model.beta:.4f}**")
    md.append("")
    md.append("## Scenario points")
    for d in [3.5, 3.82, 4.0, 4.25, 4.5]:
        md.append(f"- Deficit {d:.2f}% -> estimated depletion year {val(d)}")
    md.append("")
    md.append("## Files")
    md.append(f"- CSV: `{csv_path}`")
    md.append(f"- Metadata: `{json_path}`")
    md_path = out_dir / "ssa_jacobian_sweep.md"
    md_path.write_text("\n".join(md) + "\n", encoding="utf-8")

    print(csv_path)
    print(json_path)
    print(md_path)


if __name__ == "__main__":
    main()
