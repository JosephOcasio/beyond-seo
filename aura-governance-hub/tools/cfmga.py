#!/usr/bin/env python3
"""
CFMGA v1.0 - Constraint-First Measurement & Governance Architecture

Single-file implementation skeleton:
- SNOA: Symbolic Numeric Overrepresentation Analysis (hotspot detection)
- OE:   Ocasio Equation (admissibility gates + scoring)
- Drift: Constraint Drift ledger (append-only JSONL)
- Artifacts: run_manifest.json, hotspots.csv, admissibility.json, drift_trace.jsonl, code_hash.txt

Design choices:
- Deterministic randomness (seeded) for reproducibility
- Multiple null models (start with N1/N2; N3/N4 stubbed)
- Empirical p-values via Monte Carlo sims
- FDR via Benjamini–Hochberg, plus Bonferroni
- Clear separation: measurement produces candidates; OE ranks them

Dependencies: Python 3.10+
Optional: pandas (for CSV convenience). If absent, CSV is written via csv module.
"""

from __future__ import annotations

import argparse
import csv
import dataclasses
import hashlib
import json
import os
import random
import re
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List, Optional, Sequence, Tuple, Any, Iterable

# Optional pandas
try:
    import pandas as pd  # type: ignore
except Exception:
    pd = None


# ----------------------------
# Utilities
# ----------------------------

def utc_now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()

def sha256_bytes(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()

def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()

def ensure_dir(path: Path) -> None:
    path.mkdir(parents=True, exist_ok=True)

def stable_code_hash() -> str:
    """
    Hash this file's content for provenance.
    If frozen packaging is used, replace with git commit hash.
    """
    try:
        this_file = Path(__file__).resolve()
        return sha256_file(this_file)
    except Exception:
        # Fallback: hash argv[0] if available or empty string
        return sha256_bytes(str(sys.argv[0]).encode("utf-8"))


# ----------------------------
# Normalization
# ----------------------------

_HEBREW_DIACRITICS_RE = re.compile(r"[\u0591-\u05BD\u05BF\u05C1-\u05C7]")  # cantillation + niqqud
_HEBREW_PUNCT_RE = re.compile(r"[^\u0590-\u05FF\s]")  # keep Hebrew block + whitespace

_GREEK_DIACRITICS_RE = re.compile(r"[\u0300-\u036F]")  # combining marks
_GREEK_PUNCT_RE = re.compile(r"[^\u0370-\u03FF\u1F00-\u1FFF\s]")  # Greek blocks + whitespace

_WHITESPACE_RE = re.compile(r"\s+")

def normalize_text(text: str, language: str) -> str:
    """
    Freeze normalization rules per corpus family.
    Keep it explicit and logged.
    """
    lang = language.strip().lower()

    if lang in ("hebrew", "heb", "he"):
        # Remove cantillation + vowel points
        text = _HEBREW_DIACRITICS_RE.sub("", text)
        # Drop punctuation outside Hebrew block
        text = _HEBREW_PUNCT_RE.sub(" ", text)
        # Normalize final forms not handled here (optional)
        # Keep as-is by default: ךםןףץ
    elif lang in ("greek", "grc", "el"):
        # Decompose + remove combining marks (basic)
        # If you want true normalization, add unicodedata.normalize("NFD", text)
        import unicodedata
        text = unicodedata.normalize("NFD", text)
        text = _GREEK_DIACRITICS_RE.sub("", text)
        text = unicodedata.normalize("NFC", text)
        text = _GREEK_PUNCT_RE.sub(" ", text)
    else:
        # Generic: keep letters/numbers and whitespace; strip punctuation
        text = re.sub(r"[^\w\s]", " ", text, flags=re.UNICODE)

    text = _WHITESPACE_RE.sub(" ", text).strip()
    return text

def tokenize_words(text: str) -> List[str]:
    if not text:
        return []
    return text.split(" ")


# ----------------------------
# Mappings (letter -> integer)
# ----------------------------

def hebrew_gematria_mapping() -> Dict[str, int]:
    """
    Standard Hebrew gematria (mispar hechrechi).
    Note: final forms mapped to same as non-final.
    """
    base = {
        "א": 1,  "ב": 2,  "ג": 3,  "ד": 4,  "ה": 5,  "ו": 6,  "ז": 7,  "ח": 8,  "ט": 9,
        "י": 10, "כ": 20, "ל": 30, "מ": 40, "נ": 50, "ס": 60, "ע": 70, "פ": 80, "צ": 90,
        "ק": 100,"ר": 200,"ש": 300,"ת": 400,
        # finals
        "ך": 20, "ם": 40, "ן": 50, "ף": 80, "ץ": 90,
    }
    return base

def greek_isopsephy_mapping() -> Dict[str, int]:
    """
    Common Greek isopsephy (classical/koine variants).
    This mapping is simplified; expand as needed.
    """
    base = {
        "α": 1, "β": 2, "γ": 3, "δ": 4, "ε": 5, "ϛ": 6, "ζ": 7, "η": 8, "θ": 9,
        "ι": 10,"κ": 20,"λ": 30,"μ": 40,"ν": 50,"ξ": 60,"ο": 70,"π": 80,"ϟ": 90,
        "ρ": 100,"σ": 200,"ς": 200,"τ": 300,"υ": 400,"φ": 500,"χ": 600,"ψ": 700,"ω": 800,"ϡ": 900
    }
    # Uppercase support (map to lowercase)
    upper = {k.upper(): v for k, v in base.items() if k.isalpha()}
    base.update(upper)
    return base


# ----------------------------
# Windowing + Projection
# ----------------------------

def sum_word(word: str, mapping: Dict[str, int]) -> int:
    return sum(mapping.get(ch, 0) for ch in word)

def window_sums_words(tokens: Sequence[str], mapping: Dict[str, int], window_size: int) -> List[int]:
    """
    Sliding word windows: sum gematria over all characters of words in the window.
    """
    if window_size <= 0:
        raise ValueError("window_size must be positive")
    if len(tokens) < window_size:
        return []
    # Precompute per-word sums to accelerate
    w_sums = [sum_word(w, mapping) for w in tokens]
    out: List[int] = []
    current = sum(w_sums[:window_size])
    out.append(current)
    for i in range(window_size, len(w_sums)):
        current += w_sums[i] - w_sums[i - window_size]
        out.append(current)
    return out

def freq_map(values: Sequence[int]) -> Dict[int, int]:
    fm: Dict[int, int] = {}
    for v in values:
        fm[v] = fm.get(v, 0) + 1
    return fm


# ----------------------------
# Null Models (start minimal, extend later)
# ----------------------------

def null_N1_letter_shuffle(tokens: Sequence[str], rng: random.Random) -> List[str]:
    """
    Letter-frequency preserving shuffle:
    - Flatten all characters across tokens.
    - Shuffle characters.
    - Re-slice into original token lengths.
    Preserves total character multiset and token length profile.
    """
    chars: List[str] = []
    lengths: List[int] = []
    for t in tokens:
        lengths.append(len(t))
        chars.extend(list(t))
    rng.shuffle(chars)

    out: List[str] = []
    idx = 0
    for L in lengths:
        out.append("".join(chars[idx: idx + L]))
        idx += L
    return out

def null_N2_word_length_profile_shuffle(tokens: Sequence[str], rng: random.Random) -> List[str]:
    """
    Word-length profile preserving shuffle:
    - Shuffle the order of words (keeps each word intact).
    This preserves token multiset and lengths, but destroys local ordering.
    """
    out = list(tokens)
    rng.shuffle(out)
    return out

def null_N3_block_permutation(tokens: Sequence[str], rng: random.Random, block_size: int = 20) -> List[str]:
    """
    Stub / minimal implementation:
    - Partition tokens into blocks of fixed size and shuffle blocks.
    Preserves local structure within blocks.
    """
    if block_size <= 1:
        return null_N2_word_length_profile_shuffle(tokens, rng)
    blocks = [list(tokens[i:i+block_size]) for i in range(0, len(tokens), block_size)]
    rng.shuffle(blocks)
    out: List[str] = []
    for b in blocks:
        out.extend(b)
    return out

def null_N4_markov_generator_stub(tokens: Sequence[str], rng: random.Random) -> List[str]:
    """
    Stub: Real Markov n-gram generator requires training on corpus.
    For now, return N2 as placeholder.
    """
    return null_N2_word_length_profile_shuffle(tokens, rng)

NULL_MODEL_REGISTRY = {
    "N1": null_N1_letter_shuffle,
    "N2": null_N2_word_length_profile_shuffle,
    "N3": null_N3_block_permutation,
    "N4": null_N4_markov_generator_stub,
}


# ----------------------------
# Stats: empirical p-values + multiple testing
# ----------------------------

def benjamini_hochberg(pvals: Dict[int, float]) -> Dict[int, float]:
    """
    Return q-values (FDR) using BH procedure.
    Input: dict key->p
    Output: dict key->q
    """
    items = sorted(pvals.items(), key=lambda kv: kv[1])
    m = len(items)
    if m == 0:
        return {}
    q: Dict[int, float] = {}
    prev_q = 1.0
    for rank, (k, p) in enumerate(reversed(items), start=1):
        i = m - rank + 1
        q_i = min(prev_q, p * m / i)
        q[k] = q_i
        prev_q = q_i
    return q

def bonferroni(p: float, m: int) -> float:
    return min(1.0, p * m)

def empirical_p_value(obs: int, sims: Sequence[int], tail: str = "right") -> float:
    """
    Empirical p:
    right tail: P(sim >= obs)
    left tail:  P(sim <= obs)
    two:        2*min(tails)
    Adds +1 smoothing to avoid 0.
    """
    if not sims:
        return 1.0
    n = len(sims)
    if tail == "right":
        count = sum(1 for s in sims if s >= obs)
        return (count + 1) / (n + 1)
    if tail == "left":
        count = sum(1 for s in sims if s <= obs)
        return (count + 1) / (n + 1)
    if tail == "two":
        right = empirical_p_value(obs, sims, "right")
        left = empirical_p_value(obs, sims, "left")
        return min(1.0, 2.0 * min(right, left))
    raise ValueError("tail must be right/left/two")


# ----------------------------
# Data Models
# ----------------------------

@dataclass
class Windowing:
    type: str  # "word" (we implement); verse/char can be added
    size: int

@dataclass
class NormalizationSpec:
    language: str

@dataclass
class MappingSpec:
    name: str
    table_hash: str

@dataclass
class RunManifest:
    run_id: str
    timestamp: str
    method_version: str
    oe_version: str
    corpus: Dict[str, str]
    normalization: Dict[str, Any]
    mapping: Dict[str, Any]
    windowing: List[Dict[str, Any]]
    null_models: List[str]
    simulations: int
    random_seed: int
    code_hash: str
    notes: str = ""

@dataclass
class HotspotRow:
    value: int
    f_obs: float
    mu_null: float
    sigma_null: float
    z: float
    p_emp: float
    q_fdr: float
    p_bonf: float
    null_model: str
    windowing_type: str
    windowing_size: int


# ----------------------------
# OE (Ocasio Equation) Structures
# ----------------------------

@dataclass
class CCS:
    PC: float
    IC: float
    TC: float
    ML: float
    NDR: float

@dataclass
class AdmissibilityRecord:
    claim_id: str
    w1: bool
    w2: bool
    w3: bool
    w4: bool
    ccs: CCS
    oe_score: float
    klass: str  # A/B/C/D
    assumptions: List[str]


# ----------------------------
# Drift Ledger
# ----------------------------

def append_drift_event(path: Path, event: Dict[str, Any]) -> None:
    ensure_dir(path.parent)
    with path.open("a", encoding="utf-8") as f:
        f.write(json.dumps(event, ensure_ascii=False) + "\n")


# ----------------------------
# SNOA Core
# ----------------------------

def run_snoa(
    tokens: List[str],
    mapping: Dict[str, int],
    windowing: Windowing,
    null_models: List[str],
    simulations: int,
    seed: int,
    tail: str = "right",
) -> List[HotspotRow]:
    """
    Runs SNOA:
    - compute observed window sums -> freq
    - for each null model:
        simulate freq(v) distribution for each v
        compute empirical p-values (frequency inflation)
        compute q-values (FDR) + Bonferroni
        return hotspot rows for values seen in observed
    """
    if windowing.type != "word":
        raise NotImplementedError("Only word windowing is implemented in this skeleton.")

    obs_sums = window_sums_words(tokens, mapping, windowing.size)
    obs_freq = freq_map(obs_sums)

    if not obs_freq:
        return []

    rng = random.Random(seed)
    rows: List[HotspotRow] = []

    for nm in null_models:
        if nm not in NULL_MODEL_REGISTRY:
            raise ValueError(f"Unknown null model: {nm}. Known: {sorted(NULL_MODEL_REGISTRY.keys())}")

        sim_freqs_by_value: Dict[int, List[int]] = {v: [] for v in obs_freq.keys()}

        # Monte Carlo sims
        for _ in range(simulations):
            if nm == "N3":
                sim_tokens = NULL_MODEL_REGISTRY[nm](tokens, rng, 20)  # block_size default
            else:
                sim_tokens = NULL_MODEL_REGISTRY[nm](tokens, rng)

            sim_sums = window_sums_words(sim_tokens, mapping, windowing.size)
            sim_freq = freq_map(sim_sums)

            for v in obs_freq.keys():
                sim_freqs_by_value[v].append(sim_freq.get(v, 0))

        # Stats per v
        pvals: Dict[int, float] = {}
        mu: Dict[int, float] = {}
        sigma: Dict[int, float] = {}

        for v, sims in sim_freqs_by_value.items():
            # mean/variance
            m = sum(sims) / len(sims)
            var = sum((x - m) ** 2 for x in sims) / max(1, (len(sims) - 1))
            s = var ** 0.5
            mu[v] = m
            sigma[v] = s if s > 0 else 1e-9  # avoid zero division
            pvals[v] = empirical_p_value(obs_freq[v], sims, tail=tail)

        qvals = benjamini_hochberg(pvals)
        mtests = len(pvals)

        for v in obs_freq.keys():
            z = (obs_freq[v] - mu[v]) / sigma[v]
            rows.append(
                HotspotRow(
                    value=v,
                    f_obs=float(obs_freq[v]),
                    mu_null=float(mu[v]),
                    sigma_null=float(sigma[v]),
                    z=float(z),
                    p_emp=float(pvals[v]),
                    q_fdr=float(qvals.get(v, 1.0)),
                    p_bonf=float(bonferroni(pvals[v], mtests)),
                    null_model=nm,
                    windowing_type=windowing.type,
                    windowing_size=windowing.size,
                )
            )

    # Sort by q then p then z descending
    rows.sort(key=lambda r: (r.q_fdr, r.p_emp, -r.z))
    return rows


# ----------------------------
# OE (Ocasio Equation) Core
# ----------------------------

def oe_score(ccs: CCS, weights: Dict[str, float]) -> float:
    return (
        weights.get("PC", 1.0) * ccs.PC
        + weights.get("IC", 1.0) * ccs.IC
        + weights.get("TC", 1.0) * ccs.TC
        - weights.get("ML", 1.0) * ccs.ML
        - weights.get("NDR", 1.0) * ccs.NDR
    )

def oe_classify(w1: bool, w2: bool, w3: bool, w4: bool, score: float) -> str:
    if not w1:
        return "D"
    if not (w2 and w3 and w4):
        return "C"  # sandbox if gates not fully met
    # Gate pass: classify by score
    if score >= 3.5:
        return "A"
    if score >= 2.0:
        return "B"
    return "C"

def build_admissibility_for_hotspots(
    hotspots: List[HotspotRow],
    alpha_q: float = 0.05,
    weights: Optional[Dict[str, float]] = None,
) -> List[AdmissibilityRecord]:
    """
    Minimal admissibility logic:
    - W1: always True (no physics violation for a stat result)
    - W2: True if q_fdr <= alpha_q and z > 0
    - W3: False by default (requires correlation model across corpora); mark as conditional
    - W4: True if survives at least two nulls (requires grouping by value across nulls)
    This is intentionally conservative and forces you to implement W3 properly later.
    """
    if weights is None:
        weights = {"PC": 1.0, "IC": 1.0, "TC": 1.0, "ML": 1.0, "NDR": 1.0}

    # Group by (value, window size) across nulls
    grouped: Dict[Tuple[int, int], List[HotspotRow]] = {}
    for h in hotspots:
        grouped.setdefault((h.value, h.windowing_size), []).append(h)

    records: List[AdmissibilityRecord] = []
    for (val, wsize), hs in grouped.items():
        # W2: at least one null says significant
        w2 = any(h.q_fdr <= alpha_q and h.z > 0 for h in hs)

        # W4: survives at least two nulls (significant in >=2)
        w4 = sum(1 for h in hs if h.q_fdr <= alpha_q and h.z > 0) >= 2

        # W3: independence modeling not done here
        w3 = False

        w1 = True

        # CCS heuristic (replace with your real scoring):
        # PC: stronger if z is large and stable
        best = min(hs, key=lambda h: h.q_fdr)
        PC = max(0.0, min(5.0, best.z / 5.0))  # scale z≈25 -> 5
        IC = 0.5  # conservative until you model corpus independence
        TC = 0.5  # needs cross-tradition replication
        ML = 1.0  # measurement-level claim is low mediation
        NDR = 0.5 # low narrative risk if you keep it distribution-only

        ccs = CCS(PC=PC, IC=IC, TC=TC, ML=ML, NDR=NDR)
        score = oe_score(ccs, weights)
        klass = oe_classify(w1, w2, w3, w4, score)

        assumptions = []
        if not w3:
            assumptions.append("W3 independence/correlation modeling not implemented; treat as sandbox until modeled.")
        if not w4:
            assumptions.append("W4 robustness not met across >=2 strong nulls.")
        if not w2:
            assumptions.append("W2 identifiability not met under current alpha; treat as non-signal.")

        claim_id = f"hotspot:value={val}:word_window={wsize}"

        records.append(
            AdmissibilityRecord(
                claim_id=claim_id,
                w1=w1, w2=w2, w3=w3, w4=w4,
                ccs=ccs,
                oe_score=score,
                klass=klass,
                assumptions=assumptions,
            )
        )

    # Sort: A first, then B, then C, then D; by score desc
    order = {"A": 0, "B": 1, "C": 2, "D": 3}
    records.sort(key=lambda r: (order[r.klass], -r.oe_score))
    return records


# ----------------------------
# Artifact Writers
# ----------------------------

def write_manifest(out_dir: Path, manifest: RunManifest) -> Path:
    ensure_dir(out_dir)
    path = out_dir / "run_manifest.json"
    with path.open("w", encoding="utf-8") as f:
        json.dump(dataclasses.asdict(manifest), f, indent=2, ensure_ascii=False)
    return path

def write_hotspots_csv(out_dir: Path, rows: List[HotspotRow]) -> Path:
    ensure_dir(out_dir)
    path = out_dir / "hotspots.csv"
    if pd is not None:
        df = pd.DataFrame([dataclasses.asdict(r) for r in rows])
        df.to_csv(path, index=False)
        return path

    # fallback csv module
    with path.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(dataclasses.asdict(rows[0]).keys()) if rows else [])
        w.writeheader()
        for r in rows:
            w.writerow(dataclasses.asdict(r))
    return path

def write_admissibility(out_dir: Path, records: List[AdmissibilityRecord]) -> Path:
    ensure_dir(out_dir)
    path = out_dir / "admissibility.json"
    payload = []
    for r in records:
        payload.append({
            "claim_id": r.claim_id,
            "w1": r.w1, "w2": r.w2, "w3": r.w3, "w4": r.w4,
            "ccs": dataclasses.asdict(r.ccs),
            "oe_score": r.oe_score,
            "class": r.klass,
            "assumptions": r.assumptions,
        })
    with path.open("w", encoding="utf-8") as f:
        json.dump(payload, f, indent=2, ensure_ascii=False)
    return path

def write_code_hash(out_dir: Path, code_hash: str) -> Path:
    ensure_dir(out_dir)
    path = out_dir / "code_hash.txt"
    path.write_text(code_hash + "\n", encoding="utf-8")
    return path


# ----------------------------
# CLI Commands
# ----------------------------

def cmd_run(args: argparse.Namespace) -> int:
    in_path = Path(args.corpus).expanduser().resolve()
    out_dir = Path(args.out_dir).expanduser().resolve()
    ensure_dir(out_dir)

    if not in_path.exists():
        print(f"ERROR: corpus file not found: {in_path}", file=sys.stderr)
        return 2

    raw = in_path.read_text(encoding="utf-8", errors="ignore")
    norm = normalize_text(raw, args.language)
    tokens = tokenize_words(norm)

    if not tokens:
        print("ERROR: No tokens after normalization; check language/normalization rules.", file=sys.stderr)
        return 2

    # Mapping selection
    if args.mapping.lower() in ("hebrew", "heb"):
        mapping = hebrew_gematria_mapping()
        mapping_name = "hebrew_gematria_standard"
    elif args.mapping.lower() in ("greek", "grc"):
        mapping = greek_isopsephy_mapping()
        mapping_name = "greek_isopsephy_standard"
    else:
        print(f"ERROR: Unknown mapping '{args.mapping}'. Use hebrew|greek.", file=sys.stderr)
        return 2

    mapping_hash = sha256_bytes(json.dumps(mapping, sort_keys=True, ensure_ascii=False).encode("utf-8"))

    # Parse window spec
    windowing = Windowing(type="word", size=int(args.window))
    nulls = [n.strip() for n in args.nulls.split(",") if n.strip()]
    sims = int(args.sims)
    seed = int(args.seed)

    # Run SNOA
    hotspots = run_snoa(
        tokens=tokens,
        mapping=mapping,
        windowing=windowing,
        null_models=nulls,
        simulations=sims,
        seed=seed,
        tail=args.tail,
    )

    # OE admissibility
    oe_records = build_admissibility_for_hotspots(
        hotspots=hotspots,
        alpha_q=float(args.alpha_q),
        weights={"PC": 1.0, "IC": 1.0, "TC": 1.0, "ML": 1.0, "NDR": 1.0},
    )

    # Artifacts
    run_id = args.run_id or f"run_{int(time.time())}_{seed}"
    code_hash = stable_code_hash()

    manifest = RunManifest(
        run_id=run_id,
        timestamp=utc_now_iso(),
        method_version="SNOA-1.0",
        oe_version="OE-1.0",
        corpus={"name": args.corpus_name, "language": args.language, "source": str(in_path)},
        normalization={"language": args.language},
        mapping={"name": mapping_name, "table_hash": mapping_hash},
        windowing=[{"type": windowing.type, "size": windowing.size}],
        null_models=nulls,
        simulations=sims,
        random_seed=seed,
        code_hash=code_hash,
        notes=args.notes or "",
    )

    write_manifest(out_dir, manifest)
    write_hotspots_csv(out_dir, hotspots)
    write_admissibility(out_dir, oe_records)
    write_code_hash(out_dir, code_hash)

    # Drift ledger: record measurement->artifact transform (optional)
    drift_path = out_dir / "drift_trace.jsonl"
    append_drift_event(drift_path, {
        "ts": utc_now_iso(),
        "claim_id": "RUN",
        "stage_from": "measurement",
        "stage_to": "artifacts",
        "delta_constraints": 0.0,
        "delta_narrative": 0.0,
        "delta_agency": 0.0,
        "reversible": True,
        "actor": "cfmga.py",
        "notes": f"Generated artifacts for run_id={run_id}",
    })

    print(f"OK: wrote artifacts to {out_dir}")
    print(f"Hotspots rows: {len(hotspots)}")
    print(f"Admissibility records: {len(oe_records)}")
    return 0

def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(prog="cfmga", description="CFMGA v1.0 - SNOA + OE + Drift artifacts")
    sub = p.add_subparsers(dest="cmd", required=True)

    run = sub.add_parser("run", help="Run SNOA + OE and emit reproducibility artifacts")
    run.add_argument("--corpus", required=True, help="Path to corpus text file (utf-8)")
    run.add_argument("--corpus-name", default="CORPUS", help="Human name (e.g., MT, DSS, Aramaic)")
    run.add_argument("--language", required=True, help="Language family for normalization (hebrew|greek|generic)")
    run.add_argument("--mapping", required=True, help="Mapping table (hebrew|greek)")
    run.add_argument("--window", type=int, default=7, help="Word window size")
    run.add_argument("--nulls", default="N1,N2", help="Comma list: N1,N2,N3,N4")
    run.add_argument("--sims", type=int, default=200, help="Monte Carlo simulations per null model")
    run.add_argument("--seed", type=int, default=1337, help="Random seed")
    run.add_argument("--tail", default="right", choices=["right","left","two"], help="Empirical p-value tail")
    run.add_argument("--alpha-q", default="0.05", help="FDR alpha threshold for OE W2/W4 heuristics")
    run.add_argument("--out-dir", required=True, help="Output directory for artifacts")
    run.add_argument("--run-id", default="", help="Optional run ID (otherwise auto)")
    run.add_argument("--notes", default="", help="Free-form run notes")
    run.set_defaults(func=cmd_run)

    return p

def main(argv: Optional[List[str]] = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    return args.func(args)

if __name__ == "__main__":
    raise SystemExit(main())
