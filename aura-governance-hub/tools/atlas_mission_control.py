#!/usr/bin/env python3
"""
3I/ATLAS mission-control simulator (Python with optional JAX backend).

Purpose:
- Evaluate intercept executability gate from mission lead-time vs boundary time.
- Simulate communication timing around flyby.
- Estimate special+gravitational time-dilation terms for probe/earth clocks.

This is a deterministic planning model, not an orbital propagator.
"""

from __future__ import annotations

import argparse
import csv
import json
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List

try:
    import jax.numpy as xp  # type: ignore
    BACKEND = "jax"
except Exception:
    import numpy as xp  # type: ignore
    BACKEND = "numpy"


# Physical constants (km, s units)
C_KM_S = 299_792.458
MU_SUN = 132_712_440_018.0
MU_EARTH = 398_600.4418
AU_KM = 149_597_870.7
EARTH_RADIUS_KM = 6_378.1363


@dataclass
class GateResult:
    gate: str
    passed: bool
    details: str


def parse_iso_utc(value: str) -> datetime:
    value = value.strip()
    if value.endswith("Z"):
        value = value[:-1] + "+00:00"
    dt = datetime.fromisoformat(value)
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt.astimezone(timezone.utc)


def inertial_gate(now_utc: datetime, boundary_utc: datetime, mission_lead_days: float) -> GateResult:
    available_days = (boundary_utc - now_utc).total_seconds() / 86_400.0
    passed = mission_lead_days < available_days
    details = (
        f"lead_days={mission_lead_days:.3f}, available_days={available_days:.3f}, "
        f"condition: lead_days < available_days"
    )
    return GateResult(gate="W3_EXECUTABILITY", passed=passed, details=details)


def schwarzschild_rate(mu: float, r_km: float) -> float:
    term = 1.0 - (2.0 * mu) / (r_km * (C_KM_S ** 2))
    if term <= 0:
        return 0.0
    return float(term ** 0.5)


def special_rate(v_km_s: float) -> float:
    beta2 = (v_km_s / C_KM_S) ** 2
    term = 1.0 - beta2
    if term <= 0:
        return 0.0
    return float(term ** 0.5)


def build_profile(
    closest_approach_km: float,
    relative_speed_km_s: float,
    probe_heliocentric_au: float,
    horizon_hours: float,
    step_minutes: float,
) -> Dict[str, object]:
    # Time axis centered at closest approach.
    total_seconds = horizon_hours * 3600.0
    step_seconds = step_minutes * 60.0
    n = int(total_seconds / step_seconds)
    if n < 2:
        n = 2

    times_s = xp.linspace(-total_seconds / 2.0, total_seconds / 2.0, n + 1)
    distance_km = xp.sqrt((closest_approach_km ** 2) + ((relative_speed_km_s * times_s) ** 2))

    one_way_s = distance_km / C_KM_S
    two_way_s = 2.0 * one_way_s

    # Radial line-of-sight velocity approximation for flyby geometry.
    radial_v_km_s = (relative_speed_km_s ** 2 * times_s) / distance_km
    beta = radial_v_km_s / C_KM_S

    # Relativistic Doppler factor (source->observer); clipped for numeric stability.
    beta = xp.clip(beta, -0.999999, 0.999999)
    doppler_factor = xp.sqrt((1.0 - beta) / (1.0 + beta))

    # Clock rates.
    r_probe_km = probe_heliocentric_au * AU_KM
    probe_gr_rate = schwarzschild_rate(MU_SUN, r_probe_km)
    probe_sr_rate = special_rate(relative_speed_km_s)
    probe_clock_rate = probe_gr_rate * probe_sr_rate

    earth_gr_sun = schwarzschild_rate(MU_SUN, AU_KM)
    earth_gr_local = schwarzschild_rate(MU_EARTH, EARTH_RADIUS_KM)
    earth_clock_rate = earth_gr_sun * earth_gr_local

    probe_vs_earth_rate = probe_clock_rate / earth_clock_rate if earth_clock_rate > 0 else 0.0
    dilation_ppm = (1.0 - probe_vs_earth_rate) * 1e6

    profile_rows: List[Dict[str, float]] = []
    for i in range(len(times_s)):  # type: ignore[arg-type]
        profile_rows.append(
            {
                "t_hours": float(times_s[i] / 3600.0),
                "range_km": float(distance_km[i]),
                "one_way_light_time_s": float(one_way_s[i]),
                "two_way_light_time_s": float(two_way_s[i]),
                "radial_v_km_s": float(radial_v_km_s[i]),
                "doppler_factor": float(doppler_factor[i]),
            }
        )

    summary = {
        "backend": BACKEND,
        "closest_approach_km": closest_approach_km,
        "relative_speed_km_s": relative_speed_km_s,
        "probe_heliocentric_au": probe_heliocentric_au,
        "clock_rates": {
            "probe_gr_rate": probe_gr_rate,
            "probe_sr_rate": probe_sr_rate,
            "probe_clock_rate": probe_clock_rate,
            "earth_clock_rate": earth_clock_rate,
            "probe_vs_earth_rate": probe_vs_earth_rate,
            "dilation_ppm": dilation_ppm,
        },
        "latency": {
            "min_one_way_s": float(xp.min(one_way_s)),
            "max_one_way_s": float(xp.max(one_way_s)),
            "min_two_way_s": float(xp.min(two_way_s)),
            "max_two_way_s": float(xp.max(two_way_s)),
        },
    }

    return {"summary": summary, "rows": profile_rows}


def write_csv(path: Path, rows: List[Dict[str, float]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    fieldnames = [
        "t_hours",
        "range_km",
        "one_way_light_time_s",
        "two_way_light_time_s",
        "radial_v_km_s",
        "doppler_factor",
    ]
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        for row in rows:
            writer.writerow(row)


def main() -> int:
    parser = argparse.ArgumentParser(description="3I/ATLAS intercept+comms planning simulator")
    parser.add_argument("--obs-date", default=datetime.now(timezone.utc).isoformat(), help="Observation time UTC ISO8601")
    parser.add_argument("--boundary-date", default="2026-04-12T00:00:00Z", help="Inference boundary UTC ISO8601")
    parser.add_argument("--mission-lead-days", type=float, default=30.0, help="Mission lead time in days")

    parser.add_argument("--closest-approach-km", type=float, default=5_000_000.0, help="Closest approach distance in km")
    parser.add_argument("--relative-speed-km-s", type=float, default=58.0, help="Relative flyby speed km/s")
    parser.add_argument("--probe-heliocentric-au", type=float, default=2.0, help="Probe heliocentric radius AU")
    parser.add_argument("--horizon-hours", type=float, default=48.0, help="Simulation horizon in hours")
    parser.add_argument("--step-minutes", type=float, default=10.0, help="Time step in minutes")

    parser.add_argument("--out-dir", required=True, help="Output directory")
    args = parser.parse_args()

    now_utc = parse_iso_utc(args.obs_date)
    boundary_utc = parse_iso_utc(args.boundary_date)

    gate = inertial_gate(now_utc, boundary_utc, args.mission_lead_days)
    profile = build_profile(
        closest_approach_km=args.closest_approach_km,
        relative_speed_km_s=args.relative_speed_km_s,
        probe_heliocentric_au=args.probe_heliocentric_au,
        horizon_hours=args.horizon_hours,
        step_minutes=args.step_minutes,
    )

    out_dir = Path(args.out_dir).expanduser().resolve()
    out_dir.mkdir(parents=True, exist_ok=True)

    write_csv(out_dir / "comms_profile.csv", profile["rows"])

    report = {
        "scenario": "3I/ATLAS_intercept_planning",
        "timestamp_utc": datetime.now(timezone.utc).isoformat(),
        "inputs": {
            "obs_date": now_utc.isoformat(),
            "boundary_date": boundary_utc.isoformat(),
            "mission_lead_days": args.mission_lead_days,
            "closest_approach_km": args.closest_approach_km,
            "relative_speed_km_s": args.relative_speed_km_s,
            "probe_heliocentric_au": args.probe_heliocentric_au,
            "horizon_hours": args.horizon_hours,
            "step_minutes": args.step_minutes,
        },
        "gates": [
            {
                "gate": gate.gate,
                "passed": gate.passed,
                "details": gate.details,
            }
        ],
        "status": "ADMISSIBLE" if gate.passed else "INADMISSIBLE",
        "profile_summary": profile["summary"],
        "outputs": {
            "report_json": str(out_dir / "mission_report.json"),
            "comms_profile_csv": str(out_dir / "comms_profile.csv"),
        },
        "notes": [
            "This is a planning-grade kinematic+relativistic model.",
            "Use JPL Horizons state vectors for mission-grade trajectory design.",
        ],
    }

    with (out_dir / "mission_report.json").open("w", encoding="utf-8") as handle:
        json.dump(report, handle, indent=2)

    print(json.dumps(report, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
