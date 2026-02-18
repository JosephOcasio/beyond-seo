#!/usr/bin/env python3
"""ACE governance kernel with explicit gates and Doppler verification utilities.

This module is intentionally deterministic and conservative:
- W1 physical invariant gate (velocity bound)
- W2 identifiability gate (Jacobian rank)
- Multiplicative-zero admissibility score
- Optional Doppler consistency check for trajectory telemetry
"""

from __future__ import annotations

from dataclasses import dataclass, asdict
from enum import IntEnum
from typing import Any, Callable, Dict, Optional

import numpy as np


@dataclass
class AuditResult:
    decision: str
    reason: str
    delta: float
    rank: Optional[int] = None
    required_rank: Optional[int] = None
    doppler_ok: Optional[bool] = None


@dataclass
class PromotionDecision:
    allowed: bool
    reason: str
    required_evidence: int


@dataclass
class NodeSplitResult:
    node_unbound: bool
    volume_weighted_plastic_strain: float
    critical_plastic_strain: float
    evidence_score: float
    matrix_is_symmetric: bool
    symmetry_error: float
    tangent_stiffness_2x2: list[list[float]]


class ExistenceClass(IntEnum):
    E0 = 0  # speculative
    E1 = 1  # structural
    E2 = 2  # informational
    E3 = 3  # causal
    E4 = 4  # historical
    E5 = 5  # physical


class AxiomConvergenceEngine:
    """Constraint-gated decision kernel."""

    C_KM_S = 299_792.458

    def __init__(self, foundation_locked: bool = True, registry_count: int = 1594) -> None:
        self.foundation_locked = foundation_locked
        self.registry_count = registry_count

    @staticmethod
    def decision_scalar(decision: str) -> int:
        """Scalar bridge for TS execution layer: 0=pass, 1=uncertain, 3=veto."""
        if decision in {"ADMISSIBLE"}:
            return 0
        if decision in {"REFUSE_UNCERTAIN"}:
            return 1
        return 3

    @staticmethod
    def master_admissibility(phi_utility: float, lambda_invariant: float) -> float:
        """Delta = Phi * Lambda^2 (multiplicative-zero behavior)."""
        return float(phi_utility) * (float(lambda_invariant) ** 2)

    @staticmethod
    def _numerical_jacobian(
        model: Callable[[np.ndarray], np.ndarray],
        theta: np.ndarray,
        eps: float,
    ) -> np.ndarray:
        theta = np.asarray(theta, dtype=float)
        f0 = np.asarray(model(theta), dtype=float)
        if f0.ndim != 1:
            raise ValueError("model(theta) must return a 1D vector.")

        m = f0.size
        p = theta.size
        jac = np.zeros((m, p), dtype=float)

        for i in range(p):
            step = np.zeros_like(theta)
            step[i] = eps
            fp = np.asarray(model(theta + step), dtype=float)
            fm = np.asarray(model(theta - step), dtype=float)
            if fp.shape != f0.shape or fm.shape != f0.shape:
                raise ValueError("model output shape changed under perturbation.")
            jac[:, i] = (fp - fm) / (2.0 * eps)

        return jac

    def test_identifiability(
        self,
        model: Callable[[np.ndarray], np.ndarray],
        theta: np.ndarray,
        eps: float = 1e-6,
        rank_tol: float = 1e-8,
    ) -> tuple[bool, int, int]:
        """Return (pass, rank, required_rank)."""
        jac = self._numerical_jacobian(model=model, theta=theta, eps=eps)
        singular_values = np.linalg.svd(jac, compute_uv=False)
        rank = int(np.sum(singular_values > rank_tol))
        required_rank = int(np.asarray(theta).size)
        return rank >= required_rank, rank, required_rank

    @staticmethod
    def map_acg_root(root: str) -> Dict[str, Any]:
        mappings = {
            "NHSH": {"op": "RECURSIVE_LOOP", "lambda_val": 0.0, "status": "HATA_ERROR"},
            "QWM": {"op": "TERMINAL_LOCK", "lambda_val": 1.0, "status": "STATIONARY"},
            "ARK": {"op": "FOUNDATION_ANCHOR", "lambda_val": 1.0, "status": "PROTECTED"},
        }
        return mappings.get(root, {"op": "TRANSITIONAL", "lambda_val": 1.0, "status": "UNKNOWN"})

    @staticmethod
    def calculate_lucidity(n: int, f_o: float, f_p: float, f_t: float) -> float:
        return float(n) * float(f_o) * float(f_p) * float(f_t)

    @staticmethod
    def evaluate_existence_promotion(
        current_class: ExistenceClass,
        target_class: ExistenceClass,
        new_evidence_count: int,
        new_constraint_count: int,
        coherence: float,
    ) -> PromotionDecision:
        """Monotonic E0->E5 promotion with skepticism scaling."""
        if target_class != current_class + 1:
            return PromotionDecision(
                allowed=False,
                reason="non_monotonic_promotion",
                required_evidence=0,
            )

        if new_constraint_count < 1:
            return PromotionDecision(
                allowed=False,
                reason="no_new_constraint",
                required_evidence=0,
            )

        required_evidence = 1
        if coherence >= 0.8:
            required_evidence += 1
        if coherence >= 0.95:
            required_evidence += 1

        if new_evidence_count < required_evidence:
            return PromotionDecision(
                allowed=False,
                reason="insufficient_evidence",
                required_evidence=required_evidence,
            )

        return PromotionDecision(
            allowed=True,
            reason="promotable",
            required_evidence=required_evidence,
        )

    @staticmethod
    def relativistic_doppler_observed_freq(
        rest_freq_hz: float,
        radial_velocity_km_s: float,
    ) -> float:
        """Return observed frequency for line-of-sight motion.

        Convention: +velocity = receding source (redshift), -velocity = approaching.
        """
        beta = radial_velocity_km_s / AxiomConvergenceEngine.C_KM_S
        if abs(beta) >= 1.0:
            raise ValueError("|v| must be < c for Doppler computation.")
        factor = np.sqrt((1.0 - beta) / (1.0 + beta))
        return float(rest_freq_hz * factor)

    @staticmethod
    def verify_doppler_consistency(
        rest_freq_hz: float,
        measured_freq_hz: float,
        radial_velocity_km_s: float,
        sigma_hz: float,
        z_threshold: float = 3.0,
    ) -> Dict[str, Any]:
        expected = AxiomConvergenceEngine.relativistic_doppler_observed_freq(
            rest_freq_hz=rest_freq_hz,
            radial_velocity_km_s=radial_velocity_km_s,
        )
        residual = float(measured_freq_hz - expected)
        sigma = float(max(sigma_hz, 1e-12))
        z = residual / sigma
        return {
            "expected_freq_hz": expected,
            "measured_freq_hz": float(measured_freq_hz),
            "residual_hz": residual,
            "z_score": z,
            "within_threshold": abs(z) <= float(z_threshold),
        }

    @staticmethod
    def volume_weighted_plastic_strain(plastic_strain: np.ndarray, element_volume: np.ndarray) -> float:
        """Compute average volume-weighted plastic strain."""
        eps_p = np.asarray(plastic_strain, dtype=float)
        vol = np.asarray(element_volume, dtype=float)
        if eps_p.shape != vol.shape:
            raise ValueError("plastic_strain and element_volume must have the same shape.")
        if np.any(vol < 0.0):
            raise ValueError("element_volume must be non-negative.")
        total_volume = float(np.sum(vol))
        if total_volume <= 0.0:
            raise ValueError("sum(element_volume) must be > 0.")
        return float(np.dot(eps_p, vol) / total_volume)

    @staticmethod
    def build_symmetric_tangent_stiffness(
        shear_modulus_pa: np.ndarray,
        contact_area_m2: np.ndarray,
        characteristic_length_m: np.ndarray,
        plastic_strain: np.ndarray,
        critical_plastic_strain: float,
        k_floor: float = 1e-12,
    ) -> np.ndarray:
        """Build a symmetric 2x2 tangential stiffness proxy from elastoplastic terms."""
        g = np.asarray(shear_modulus_pa, dtype=float)
        area = np.asarray(contact_area_m2, dtype=float)
        length = np.asarray(characteristic_length_m, dtype=float)
        eps_p = np.asarray(plastic_strain, dtype=float)

        if not (g.shape == area.shape == length.shape == eps_p.shape):
            raise ValueError("All element arrays must share the same shape.")
        if np.any(g <= 0.0) or np.any(area <= 0.0) or np.any(length <= 0.0):
            raise ValueError("shear_modulus, area, and length must be positive.")
        if critical_plastic_strain <= 0.0:
            raise ValueError("critical_plastic_strain must be > 0.")

        # Elastoplastic reduction; zero once strain exceeds critical threshold.
        reduction = np.clip(1.0 - (eps_p / critical_plastic_strain), 0.0, 1.0)
        k_elastic = g * area / length
        k_eff = np.maximum(k_floor, k_elastic * reduction)
        k_sum = float(np.sum(k_eff))

        # Isotropic tangential proxy preserves exact symmetry.
        return np.array([[k_sum, 0.0], [0.0, k_sum]], dtype=float)

    def evaluate_node_split(
        self,
        shear_modulus_pa: np.ndarray,
        contact_area_m2: np.ndarray,
        characteristic_length_m: np.ndarray,
        plastic_strain: np.ndarray,
        element_volume: np.ndarray,
        critical_plastic_strain: float,
        symmetry_tol: float = 1e-12,
    ) -> NodeSplitResult:
        """Deterministic node-split decision from elastoplastic strain and stiffness."""
        k_tangent = self.build_symmetric_tangent_stiffness(
            shear_modulus_pa=shear_modulus_pa,
            contact_area_m2=contact_area_m2,
            characteristic_length_m=characteristic_length_m,
            plastic_strain=plastic_strain,
            critical_plastic_strain=critical_plastic_strain,
        )

        eps_vw = self.volume_weighted_plastic_strain(
            plastic_strain=plastic_strain,
            element_volume=element_volume,
        )
        node_unbound = bool(eps_vw >= critical_plastic_strain)

        symmetry_error = float(np.max(np.abs(k_tangent - k_tangent.T)))
        matrix_is_symmetric = bool(symmetry_error <= symmetry_tol)

        evidence_score = float(np.clip(eps_vw / critical_plastic_strain, 0.0, 1.0))

        return NodeSplitResult(
            node_unbound=node_unbound,
            volume_weighted_plastic_strain=eps_vw,
            critical_plastic_strain=float(critical_plastic_strain),
            evidence_score=evidence_score,
            matrix_is_symmetric=matrix_is_symmetric,
            symmetry_error=symmetry_error,
            tangent_stiffness_2x2=k_tangent.tolist(),
        )

    def structural_audit(
        self,
        proposed_action: Dict[str, Any],
        identifiability_check: Optional[Callable[[], tuple[bool, int, int]]] = None,
        doppler_check: Optional[Dict[str, Any]] = None,
        node_split_check: Optional[Dict[str, Any]] = None,
    ) -> AuditResult:
        # W1: physical invariant gate
        velocity = float(proposed_action.get("velocity_km_s", 0.0))
        if velocity >= self.C_KM_S:
            return AuditResult(
                decision="W1_VETO",
                reason="velocity_km_s >= c violates W1 physical invariants",
                delta=0.0,
            )

        # W2: identifiability gate
        rank = None
        required_rank = None
        if identifiability_check is not None:
            identifiable, rank, required_rank = identifiability_check()
        else:
            identifiable = bool(proposed_action.get("is_identifiable", False))

        if not identifiable:
            return AuditResult(
                decision="W2_VETO",
                reason="non-identifiable system; refusal to infer",
                delta=0.0,
                rank=rank,
                required_rank=required_rank,
            )

        # Epistemic promotion gate (E0..E5) if promotion payload is provided
        if "current_existence_class" in proposed_action and "target_existence_class" in proposed_action:
            promotion = self.evaluate_existence_promotion(
                current_class=ExistenceClass(int(proposed_action["current_existence_class"])),
                target_class=ExistenceClass(int(proposed_action["target_existence_class"])),
                new_evidence_count=int(proposed_action.get("new_evidence_count", 0)),
                new_constraint_count=int(proposed_action.get("new_constraint_count", 0)),
                coherence=float(proposed_action.get("coherence", 0.0)),
            )
            if not promotion.allowed:
                return AuditResult(
                    decision="EPISTEMIC_VETO",
                    reason=f"existence_promotion_blocked:{promotion.reason}",
                    delta=0.0,
                    rank=rank,
                    required_rank=required_rank,
                )

        # Optional telemetry consistency gate
        doppler_ok: Optional[bool] = None
        if doppler_check is not None:
            doppler_ok = bool(doppler_check.get("within_threshold", False))
            if not doppler_ok:
                return AuditResult(
                    decision="W3_VETO",
                    reason="doppler telemetry inconsistent with trajectory model",
                    delta=0.0,
                    rank=rank,
                    required_rank=required_rank,
                    doppler_ok=False,
                )

        # Optional structural node-split gate
        if node_split_check is not None:
            if not bool(node_split_check.get("matrix_is_symmetric", False)):
                return AuditResult(
                    decision="W4_VETO",
                    reason="non-symmetric tangential stiffness matrix",
                    delta=0.0,
                    rank=rank,
                    required_rank=required_rank,
                    doppler_ok=doppler_ok,
                )

            min_node_evidence = float(proposed_action.get("min_node_evidence_score", 0.0))
            node_evidence = float(node_split_check.get("evidence_score", 0.0))
            if node_evidence < min_node_evidence:
                return AuditResult(
                    decision="REFUSE_UNCERTAIN",
                    reason="insufficient structural evidence from node-split evaluation",
                    delta=0.0,
                    rank=rank,
                    required_rank=required_rank,
                    doppler_ok=doppler_ok,
                )

        # Multiplicative-zero admissibility
        phi = float(proposed_action.get("utility", 0.0))
        lambda_val = float(proposed_action.get("lambda_val", 0.0))
        delta = self.master_admissibility(phi_utility=phi, lambda_invariant=lambda_val)

        if delta <= 0.0:
            return AuditResult(
                decision="HARD_STOP",
                reason="admissibility collapsed to zero",
                delta=0.0,
                rank=rank,
                required_rank=required_rank,
                doppler_ok=doppler_ok,
            )

        return AuditResult(
            decision="ADMISSIBLE",
            reason="all active gates passed",
            delta=delta,
            rank=rank,
            required_rank=required_rank,
            doppler_ok=doppler_ok,
        )


def _demo() -> None:
    ace = AxiomConvergenceEngine()

    def model(theta: np.ndarray) -> np.ndarray:
        # Simple 2-parameter identifiable mapping
        return np.array([theta[0] + 2.0 * theta[1], theta[0] - theta[1]])

    identifiable, rank, required_rank = ace.test_identifiability(model=model, theta=np.array([1.0, 2.0]))

    rest = 8.4e9
    expected = ace.relativistic_doppler_observed_freq(rest_freq_hz=rest, radial_velocity_km_s=95.0)
    doppler = ace.verify_doppler_consistency(
        rest_freq_hz=rest,
        measured_freq_hz=expected + 50.0,
        radial_velocity_km_s=95.0,
        sigma_hz=250.0,
    )
    node_split = asdict(
        ace.evaluate_node_split(
            shear_modulus_pa=np.array([8.0e10, 7.9e10, 8.1e10]),
            contact_area_m2=np.array([2.0e-4, 1.8e-4, 2.1e-4]),
            characteristic_length_m=np.array([1.0e-3, 1.1e-3, 9.0e-4]),
            plastic_strain=np.array([0.09, 0.11, 0.10]),
            element_volume=np.array([1.0, 1.2, 0.8]),
            critical_plastic_strain=0.12,
        )
    )

    action = {
        "velocity_km_s": 95.0,
        "is_identifiable": identifiable,
        "utility": 0.82,
        "lambda_val": 1.0,
        "current_existence_class": 2,  # E2
        "target_existence_class": 3,  # E3
        "new_evidence_count": 2,
        "new_constraint_count": 1,
        "coherence": 0.81,
        "min_node_evidence_score": 0.6,
    }

    result = ace.structural_audit(
        proposed_action=action,
        identifiability_check=lambda: (identifiable, rank, required_rank),
        doppler_check=doppler,
        node_split_check=node_split,
    )

    print(asdict(result))
    print({"decision_scalar": ace.decision_scalar(result.decision)})
    print(doppler)
    print(node_split)


if __name__ == "__main__":
    _demo()
