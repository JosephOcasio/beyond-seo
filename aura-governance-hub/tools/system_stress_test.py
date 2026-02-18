#!/usr/bin/env python3
"""System Stress Test harness for SRV + identifiability-gated inference."""

from __future__ import annotations

import argparse
import json
from dataclasses import dataclass
from typing import Callable, Dict, List, Tuple


@dataclass
class Claim:
    claim_id: str
    scenario: str
    model_rank: int
    model_dim: int
    phi: float
    lambda_score: float
    features: Dict[str, float | int | bool]

    def calculate_delta(self) -> float:
        return float(self.phi) * float(self.lambda_score) ** 2


@dataclass
class ConstraintRule:
    rule_id: str
    description: str
    check: Callable[[Claim], bool]


class VerifierTool:
    def check_entailment(self, claim: Claim, constraints: List[ConstraintRule]) -> Tuple[bool, List[Dict[str, str | bool]]]:
        checks: List[Dict[str, str | bool]] = []
        all_pass = True
        for rule in constraints:
            passed = bool(rule.check(claim))
            checks.append(
                {
                    "rule_id": rule.rule_id,
                    "description": rule.description,
                    "passed": passed,
                }
            )
            if not passed:
                all_pass = False
        return all_pass, checks


def test_identifiability(model_rank: int, model_dim: int) -> bool:
    return model_rank >= model_dim


def verified_inference(claim: Claim, constraints: List[ConstraintRule], verifier_tool: VerifierTool) -> Dict[str, object]:
    if not test_identifiability(claim.model_rank, claim.model_dim):
        return {
            "claim_id": claim.claim_id,
            "scenario": claim.scenario,
            "status": "ERROR",
            "error": "Non-Convergent System (Inference Illegal)",
            "gate": "W2_IDENTIFIABILITY",
            "model_rank": claim.model_rank,
            "model_dim": claim.model_dim,
        }

    is_verified, checks = verifier_tool.check_entailment(claim, constraints)

    if is_verified:
        return {
            "claim_id": claim.claim_id,
            "scenario": claim.scenario,
            "status": "PASS",
            "delta": round(claim.calculate_delta(), 8),
            "checks": checks,
        }

    return {
        "claim_id": claim.claim_id,
        "scenario": claim.scenario,
        "status": "ERROR",
        "error": "Logical Fracture Detected (Reasoning Pruned)",
        "gate": "SRV_ENTAILMENT",
        "checks": checks,
    }


def financial_constraints() -> List[ConstraintRule]:
    return [
        ConstraintRule(
            "FIN_LCR_MIN",
            "Liquidity coverage ratio must be >= 1.0",
            lambda c: float(c.features.get("liquidity_coverage_ratio", 0.0)) >= 1.0,
        ),
        ConstraintRule(
            "FIN_COLLATERAL_QUALITY",
            "Collateral quality score must be >= 0.70",
            lambda c: float(c.features.get("collateral_quality", 0.0)) >= 0.70,
        ),
        ConstraintRule(
            "FIN_FUNDING_STABILITY",
            "Short-term funding stability flag must be true",
            lambda c: bool(c.features.get("short_term_funding_stable", False)),
        ),
    ]


def atlas_constraints() -> List[ConstraintRule]:
    return [
        ConstraintRule(
            "ATL_OBS_ARC",
            "Observation arc (days) must be >= 14",
            lambda c: float(c.features.get("observation_arc_days", 0.0)) >= 14,
        ),
        ConstraintRule(
            "ATL_RMS_RESIDUAL",
            "Orbit fit RMS residual must be <= 1.5",
            lambda c: float(c.features.get("residual_rms", 999.0)) <= 1.5,
        ),
        ConstraintRule(
            "ATL_TRACKLET_COUNT",
            "Tracklet count must be >= 4",
            lambda c: int(c.features.get("tracklet_count", 0)) >= 4,
        ),
    ]


def scenario_claims(name: str) -> Tuple[List[Claim], List[ConstraintRule]]:
    if name == "financial_liquidity":
        claims = [
            Claim(
                claim_id="fin-001-pass",
                scenario=name,
                model_rank=5,
                model_dim=5,
                phi=0.83,
                lambda_score=0.92,
                features={
                    "liquidity_coverage_ratio": 1.18,
                    "collateral_quality": 0.79,
                    "short_term_funding_stable": True,
                },
            ),
            Claim(
                claim_id="fin-002-fracture",
                scenario=name,
                model_rank=5,
                model_dim=5,
                phi=0.77,
                lambda_score=0.88,
                features={
                    "liquidity_coverage_ratio": 0.94,
                    "collateral_quality": 0.66,
                    "short_term_funding_stable": False,
                },
            ),
            Claim(
                claim_id="fin-003-nonconvergent",
                scenario=name,
                model_rank=3,
                model_dim=5,
                phi=0.81,
                lambda_score=0.90,
                features={
                    "liquidity_coverage_ratio": 1.22,
                    "collateral_quality": 0.83,
                    "short_term_funding_stable": True,
                },
            ),
        ]
        return claims, financial_constraints()

    if name == "atlas_trajectory":
        claims = [
            Claim(
                claim_id="atl-001-pass",
                scenario=name,
                model_rank=6,
                model_dim=6,
                phi=0.72,
                lambda_score=0.91,
                features={
                    "observation_arc_days": 19,
                    "residual_rms": 1.2,
                    "tracklet_count": 7,
                },
            ),
            Claim(
                claim_id="atl-002-fracture",
                scenario=name,
                model_rank=6,
                model_dim=6,
                phi=0.69,
                lambda_score=0.89,
                features={
                    "observation_arc_days": 9,
                    "residual_rms": 2.7,
                    "tracklet_count": 3,
                },
            ),
        ]
        return claims, atlas_constraints()

    raise ValueError(f"Unknown scenario: {name}")


def run_scenario(name: str) -> Dict[str, object]:
    claims, constraints = scenario_claims(name)
    verifier = VerifierTool()
    results = [verified_inference(claim, constraints, verifier) for claim in claims]

    status_counts: Dict[str, int] = {}
    for item in results:
        status = str(item.get("status", "UNKNOWN"))
        status_counts[status] = status_counts.get(status, 0) + 1

    return {
        "scenario": name,
        "total_claims": len(results),
        "status_counts": status_counts,
        "results": results,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Run SRV-based system stress tests")
    parser.add_argument(
        "--scenario",
        choices=["financial_liquidity", "atlas_trajectory", "all"],
        default="financial_liquidity",
        help="Stress-test scenario to run",
    )
    parser.add_argument("--out", default="", help="Optional output JSON path")
    args = parser.parse_args()

    payload: Dict[str, object]
    if args.scenario == "all":
        payload = {
            "scenarios": [
                run_scenario("financial_liquidity"),
                run_scenario("atlas_trajectory"),
            ]
        }
    else:
        payload = run_scenario(args.scenario)

    if args.out:
        out_path = args.out
        with open(out_path, "w", encoding="utf-8") as handle:
            json.dump(payload, handle, indent=2)
        print(f"Wrote: {out_path}")

    print(json.dumps(payload, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
