#!/usr/bin/env python3
"""ECM reference primitives: deterministic Delta-F and promotion proof utilities."""

from __future__ import annotations

import base64
import copy
import json
from typing import Any, Dict, List, Tuple

import numpy as np
from cryptography.hazmat.primitives.asymmetric.ed25519 import (
    Ed25519PrivateKey,
    Ed25519PublicKey,
)


CLASS_ORDER = [
    "E0_SPECULATIVE",
    "E1_STRUCTURAL",
    "E2_INFORMATIONAL",
    "E3_CAUSAL",
    "E4_HISTORICAL",
    "E5_PHYSICAL",
]


def class_index(class_name: str) -> int:
    if class_name not in CLASS_ORDER:
        raise ValueError(f"Unknown existence class: {class_name}")
    return CLASS_ORDER.index(class_name)


def validate_monotonic_transition(from_class: str, to_class: str, one_step_only: bool = True) -> Tuple[bool, str]:
    fi = class_index(from_class)
    ti = class_index(to_class)
    if ti < fi:
        return False, "NON_MONOTONIC_BACKWARD"
    if one_step_only and ti != fi + 1:
        return False, "NON_MONOTONIC_SKIP"
    return True, "OK"


def canonical_json_bytes(obj: Dict[str, Any]) -> bytes:
    return json.dumps(obj, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode("utf-8")


def effective_dimension_from_singulars(singular_values: List[float]) -> float:
    s = np.asarray(singular_values, dtype=np.float64)
    if s.size == 0:
        return 0.0
    s = np.clip(s, 0.0, None)
    total = float(np.sum(s))
    if total <= 0.0:
        return 0.0
    p = s / total
    eps = np.finfo(np.float64).tiny
    p = np.clip(p, eps, 1.0)
    entropy = -np.sum(p * np.log(p))
    return float(np.exp(entropy))


def deltaf_rank_surrogate(prior_singulars: List[float], posterior_singulars: List[float]) -> float:
    d_prior = effective_dimension_from_singulars(prior_singulars)
    d_post = effective_dimension_from_singulars(posterior_singulars)
    if d_prior <= 0.0:
        return 0.0
    return float(1.0 - (d_post / d_prior))


def deltaf_entropy_surrogate(cov_prior: np.ndarray, cov_post: np.ndarray) -> float:
    def _logdet(mat: np.ndarray) -> float:
        s = np.linalg.svd(mat, compute_uv=False)
        s = np.clip(s, np.finfo(np.float64).tiny, None)
        return float(np.sum(np.log(s)))

    log_prior = _logdet(np.asarray(cov_prior, dtype=np.float64))
    log_post = _logdet(np.asarray(cov_post, dtype=np.float64))
    if log_prior == 0.0:
        return 0.0
    return float(1.0 - (log_post / log_prior))


def deltaf_entropy_from_nats(prior_entropy_nats: float, posterior_entropy_nats: float) -> float:
    if prior_entropy_nats == 0.0:
        return 0.0
    return float(1.0 - (posterior_entropy_nats / prior_entropy_nats))


def proof_payload_for_signing(proof: Dict[str, Any]) -> bytes:
    payload = copy.deepcopy(proof)
    payload.pop("signatures", None)
    return canonical_json_bytes(payload)


def sign_payload_ed25519(payload: bytes, private_key_bytes: bytes) -> str:
    private_key = Ed25519PrivateKey.from_private_bytes(private_key_bytes)
    signature = private_key.sign(payload)
    return base64.b64encode(signature).decode("ascii")


def verify_payload_ed25519(payload: bytes, signature_b64: str, public_key_bytes: bytes) -> bool:
    try:
        signature = base64.b64decode(signature_b64)
        public_key = Ed25519PublicKey.from_public_bytes(public_key_bytes)
        public_key.verify(signature, payload)
        return True
    except Exception:
        return False


def attach_signature_ed25519(proof: Dict[str, Any], key_id: str, private_key_bytes: bytes) -> Dict[str, Any]:
    out = copy.deepcopy(proof)
    payload = proof_payload_for_signing(out)
    sig_b64 = sign_payload_ed25519(payload, private_key_bytes)
    out.setdefault("signatures", [])
    out["signatures"].append({"key_id": key_id, "algo": "Ed25519", "signature_b64": sig_b64})
    return out


def recompute_deltaf_from_proof(proof: Dict[str, Any]) -> float:
    method = proof.get("method")
    prior = proof.get("prior_summary", {})
    post = proof.get("posterior_summary", {})

    if method == "rank_surrogate":
        s_prior = prior.get("singular_values")
        s_post = post.get("singular_values")
        if not isinstance(s_prior, list) or not isinstance(s_post, list):
            raise ValueError("rank_surrogate requires prior/posterior singular_values")
        return deltaf_rank_surrogate(s_prior, s_post)

    if method in {"entropy_surrogate", "gaussian_transfer"}:
        if "entropy_nats" in prior and "entropy_nats" in post:
            return deltaf_entropy_from_nats(float(prior["entropy_nats"]), float(post["entropy_nats"]))
        raise ValueError("entropy method requires entropy_nats in summaries")

    raise ValueError(f"Unsupported or unverifiable method: {method}")


def evaluate_promotion_bundle(bundle: Dict[str, Any], numeric_tolerance: float = 1e-12) -> Dict[str, Any]:
    req = bundle.get("promotion_request", {})
    proof = bundle.get("promotion_proof", {})

    reasons: List[str] = []
    status = "PASS"

    if req.get("request_id") != proof.get("promotion_request_id"):
        return {
            "status": "VETO_POLICY",
            "reasons": ["REQUEST_PROOF_ID_MISMATCH"],
        }

    monotonic_ok, monotonic_reason = validate_monotonic_transition(req.get("from_class", ""), req.get("to_class", ""), True)
    if not monotonic_ok:
        return {"status": "VETO_POLICY", "reasons": [monotonic_reason]}

    try:
        recomputed_deltaf = recompute_deltaf_from_proof(proof)
    except Exception as exc:
        return {"status": "VETO_POLICY", "reasons": [f"DELTAF_RECOMPUTE_ERROR:{exc}"]}

    deltaf_claimed = float(proof.get("deltaF", 0.0))
    if abs(recomputed_deltaf - deltaf_claimed) > numeric_tolerance:
        return {
            "status": "VETO_POLICY",
            "reasons": ["DELTAF_MISMATCH"],
            "recomputed_deltaF": recomputed_deltaf,
            "claimed_deltaF": deltaf_claimed,
        }

    threshold = float(req.get("policy_thresholds", {}).get("deltaF_threshold", 0.0))
    if recomputed_deltaf < threshold:
        status = "REFUSE_UNCERTAIN"
        reasons.append("DELTAF_BELOW_THRESHOLD")

    required_constraints = set(req.get("policy_thresholds", {}).get("required_constraints", []))
    satisfied_constraints = set(proof.get("constraints_satisfied", []))
    missing_constraints = sorted(required_constraints - satisfied_constraints)
    if missing_constraints:
        status = "VETO_POLICY"
        reasons.append("MISSING_REQUIRED_CONSTRAINTS")

    if not reasons:
        reasons = ["OK"]

    return {
        "status": status,
        "reasons": reasons,
        "recomputed_deltaF": recomputed_deltaf,
        "threshold_deltaF": threshold,
        "missing_constraints": missing_constraints,
    }


def verify_bundle_signatures(bundle: Dict[str, Any], key_manifest: Dict[str, Any]) -> Dict[str, Any]:
    proof = bundle.get("promotion_proof", {})
    signatures = proof.get("signatures", [])
    payload = proof_payload_for_signing(proof)

    keys_by_id = {
        item["key_id"]: item
        for item in key_manifest.get("keys", [])
        if "key_id" in item
    }

    checked = 0
    valid = 0
    errors: List[str] = []

    for sig in signatures:
        key_id = sig.get("key_id")
        algo = str(sig.get("algo", "")).lower()
        sig_b64 = sig.get("signature_b64", "")
        checked += 1

        if key_id not in keys_by_id:
            errors.append(f"UNKNOWN_KEY_ID:{key_id}")
            continue

        key_item = keys_by_id[key_id]
        key_algo = str(key_item.get("algo", "")).lower()
        if algo != key_algo:
            errors.append(f"ALGO_MISMATCH:{key_id}")
            continue

        if algo != "ed25519":
            errors.append(f"UNSUPPORTED_ALGO:{algo}")
            continue

        public_key_b64 = key_item.get("public_key_b64", "")
        try:
            public_key_bytes = base64.b64decode(public_key_b64)
        except Exception:
            errors.append(f"BAD_PUBLIC_KEY_B64:{key_id}")
            continue

        if verify_payload_ed25519(payload, sig_b64, public_key_bytes):
            valid += 1
        else:
            errors.append(f"INVALID_SIGNATURE:{key_id}")

    return {
        "checked": checked,
        "valid": valid,
        "all_valid": checked > 0 and valid == checked,
        "errors": errors,
    }
