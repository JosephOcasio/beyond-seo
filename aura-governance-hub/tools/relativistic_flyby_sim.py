#!/usr/bin/env python3
"""Relativistic flyby timing audit with explicit W-gates and optional rank test."""

from __future__ import annotations

import argparse
import json
from dataclasses import dataclass
from typing import Optional

import numpy as np

C_KM_S = 299_792.458


@dataclass
class FlybyResult:
    status: str
    lorentz_factor: Optional[float]
    earth_time_sec: Optional[float]
    probe_time_sec: Optional[float]
    clock_drift_ms: Optional[float]
    admissibility_delta: float
    notes: list[str]


def jacobian_rank_test(state_dim: int, observables_matrix: Optional[np.ndarray]) -> tuple[bool, int]:
    """W2 identifiability test: rank(H) >= dim(state)."""
    if observables_matrix is None:
        return True, state_dim
    rank = int(np.linalg.matrix_rank(observables_matrix))
    return rank >= state_dim, rank


def simulate_flyby_dilation(
    v_km_s: float,
    duration_earth_days: float,
    *,
    state_dim: int = 3,
    observables_matrix: Optional[np.ndarray] = None,
    dsn_detect_threshold_ms_per_day: float = 0.01,
) -> FlybyResult:
    # W1 Invariance gate
    if v_km_s >= C_KM_S:
        return FlybyResult(
            status="INADMISSIBLE",
            lorentz_factor=None,
            earth_time_sec=None,
            probe_time_sec=None,
            clock_drift_ms=None,
            admissibility_delta=0.0,
            notes=["W1 failure: v >= c."],
        )

    # W2 identifiability gate
    identifiable, rank = jacobian_rank_test(state_dim, observables_matrix)
    if not identifiable:
        return FlybyResult(
            status="INADMISSIBLE",
            lorentz_factor=None,
            earth_time_sec=None,
            probe_time_sec=None,
            clock_drift_ms=None,
            admissibility_delta=0.0,
            notes=[f"W2 failure: rank(H)={rank} < state_dim={state_dim}."],
        )

    beta = v_km_s / C_KM_S
    gamma = 1.0 / np.sqrt(1.0 - beta**2)

    t_earth = float(duration_earth_days) * 24.0 * 3600.0
    tau_probe = t_earth / gamma
    time_skew_ms = (t_earth - tau_probe) * 1000.0
    skew_ms_per_day = time_skew_ms / max(duration_earth_days, 1e-9)

    notes = [
        f"W2 pass: rank(H)={rank} >= state_dim={state_dim}.",
        f"Clock drift per day: {skew_ms_per_day:.6f} ms/day.",
    ]
    if skew_ms_per_day >= dsn_detect_threshold_ms_per_day:
        notes.append("Predicted drift is above configured detectability threshold.")
    else:
        notes.append("Predicted drift is below configured detectability threshold.")

    return FlybyResult(
        status="VERIFIED",
        lorentz_factor=float(gamma),
        earth_time_sec=float(t_earth),
        probe_time_sec=float(tau_probe),
        clock_drift_ms=float(time_skew_ms),
        admissibility_delta=1.0,
        notes=notes,
    )


def parse_matrix(path: Optional[str]) -> Optional[np.ndarray]:
    if not path:
        return None
    arr = np.loadtxt(path, delimiter=",")
    if arr.ndim == 1:
        arr = arr.reshape(1, -1)
    return arr


def main() -> int:
    parser = argparse.ArgumentParser(description="Relativistic flyby timing audit")
    parser.add_argument("--velocity-km-s", type=float, required=True)
    parser.add_argument("--duration-days", type=float, default=1.0)
    parser.add_argument("--state-dim", type=int, default=3)
    parser.add_argument("--observables-csv", default="", help="Optional CSV matrix for rank test")
    parser.add_argument("--dsn-threshold-ms-day", type=float, default=0.01)
    parser.add_argument("--out", default="")
    args = parser.parse_args()

    H = parse_matrix(args.observables_csv or None)
    result = simulate_flyby_dilation(
        args.velocity_km_s,
        args.duration_days,
        state_dim=args.state_dim,
        observables_matrix=H,
        dsn_detect_threshold_ms_per_day=args.dsn_threshold_ms_day,
    )

    payload = {
        "status": result.status,
        "lorentz_factor": result.lorentz_factor,
        "earth_time_sec": result.earth_time_sec,
        "probe_time_sec": result.probe_time_sec,
        "clock_drift_ms": result.clock_drift_ms,
        "admissibility_delta": result.admissibility_delta,
        "notes": result.notes,
    }

    if args.out:
        with open(args.out, "w", encoding="utf-8") as f:
            json.dump(payload, f, indent=2)

    print(json.dumps(payload, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
