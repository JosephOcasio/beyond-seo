#!/usr/bin/env python3
"""Lattice-valued bottleneck optimizer for flyby transmission planning."""

from __future__ import annotations

import argparse
import functools
import json
from pathlib import Path
from typing import Dict, List, Sequence


class DataPriorityLattice:
    """
    Linear distributive lattice over integer priority levels.

    Levels (default interpretation):
    0 = Routine
    1 = Anomaly Data
    2 = Invariant Verification
    3 = Hard Logic Lock
    """

    @staticmethod
    def meet(a: int, b: int) -> int:
        return min(a, b)

    @staticmethod
    def join(a: int, b: int) -> int:
        return max(a, b)


def calculate_path_capacity(path: Sequence[str], capacities: Dict[str, int]) -> int:
    if not path:
        raise ValueError("Path must contain at least one edge.")

    values: List[int] = []
    for edge in path:
        if edge not in capacities:
            raise KeyError(f"Missing capacity for edge '{edge}'.")
        values.append(int(capacities[edge]))

    return functools.reduce(DataPriorityLattice.meet, values)


def optimize_transmission_flow(all_paths: Sequence[Sequence[str]], capacities: Dict[str, int]) -> dict:
    if not all_paths:
        raise ValueError("No paths provided.")

    per_path = []
    for idx, path in enumerate(all_paths, start=1):
        bottleneck = calculate_path_capacity(path, capacities)
        per_path.append(
            {
                "path_id": f"path_{idx}",
                "edges": list(path),
                "bottleneck_priority": int(bottleneck),
            }
        )

    best_priority = functools.reduce(
        DataPriorityLattice.join,
        [row["bottleneck_priority"] for row in per_path],
    )

    best_paths = [row for row in per_path if row["bottleneck_priority"] == best_priority]

    return {
        "status": "VERIFIED",
        "max_flow_priority": int(best_priority),
        "all_path_bottlenecks": per_path,
        "best_paths": best_paths,
        "w5_integrity_risk": bool(best_priority < 2),
    }


def load_json(path: str) -> dict:
    return json.loads(Path(path).read_text(encoding="utf-8"))


def main() -> int:
    parser = argparse.ArgumentParser(description="Lattice-valued bottleneck duality optimizer")
    parser.add_argument(
        "--capacities-json",
        default="",
        help="Optional JSON file mapping edge->priority integer.",
    )
    parser.add_argument(
        "--paths-json",
        default="",
        help="Optional JSON file containing a list of paths (each path is list of edge IDs).",
    )
    parser.add_argument("--out", default="", help="Optional output JSON path.")
    args = parser.parse_args()

    if args.capacities_json:
        capacities = load_json(args.capacities_json)
    else:
        capacities = {
            "P_R": 3,
            "R_S": 1,
            "S_D": 2,
            "P_D": 2,
            "P_A": 2,
            "A_D": 3,
        }

    if args.paths_json:
        all_paths = load_json(args.paths_json)
    else:
        all_paths = [
            ["P_R", "R_S", "S_D"],
            ["P_D"],
            ["P_A", "A_D"],
        ]

    result = optimize_transmission_flow(all_paths, capacities)

    if args.out:
        out_path = Path(args.out).expanduser().resolve()
        out_path.parent.mkdir(parents=True, exist_ok=True)
        out_path.write_text(json.dumps(result, indent=2), encoding="utf-8")

    print(json.dumps(result, indent=2))

    if result["w5_integrity_risk"]:
        print("ALERT: W5 Gate (Integrity) at Risk. Data Compression Mandatory.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
