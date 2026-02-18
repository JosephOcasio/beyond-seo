#!/usr/bin/env python3
"""
NCEP v1.0 - Notebook Constraint Extraction Protocol

Deterministic, non-LLM pipeline to extract falsifiable constraint primitives,
build a dependency DAG, classify redundancy, and emit geometry artifacts.
"""

from __future__ import annotations

import argparse
import csv
import dataclasses
import hashlib
import json
import re
from collections import defaultdict, deque
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Iterable, List, Optional, Sequence, Set, Tuple


TEXT_EXTENSIONS = {".txt", ".md", ".html", ".htm", ".csv", ".json", ".log", ".tsv"}

STOPWORDS = {
    "a", "an", "and", "are", "as", "at", "be", "by", "for", "from", "has", "have", "in", "is",
    "it", "its", "of", "on", "or", "that", "the", "to", "was", "were", "will", "with", "this",
    "these", "those", "which", "what", "when", "where", "who", "whom", "why", "how", "into", "than",
    "then", "if", "else", "not", "can", "cannot", "must", "should", "may", "might", "would", "could",
    "also", "there", "their", "them", "they", "we", "you", "your", "our", "ours", "i", "he", "she",
}

NARRATIVE_FILTER = {
    "theology", "theological", "god", "divine", "prophecy", "prophetic", "motivational", "inspirational",
    "analogy", "analogical", "story", "narrative", "compliance", "marketing",
}


@dataclass
class ConstraintPrimitive:
    cp_id: str
    kind: str
    condition: str
    consequence: str
    source_path: str
    source_line: int
    raw_text: str
    preconditions: List[str]
    failure_modes: List[str]
    external_assumptions: List[str]
    reduction_strength: str


@dataclass
class Edge:
    source: str
    target: str
    reason: str
    score: float


def normalize_ws(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


def strip_html(text: str) -> str:
    text = re.sub(r"<script[\s\S]*?</script>", " ", text, flags=re.IGNORECASE)
    text = re.sub(r"<style[\s\S]*?</style>", " ", text, flags=re.IGNORECASE)
    return re.sub(r"<[^>]+>", " ", text)


def iter_text_files(src: Path) -> Iterable[Path]:
    for path in sorted(src.rglob("*")):
        if not path.is_file():
            continue
        if path.suffix.lower() in TEXT_EXTENSIONS:
            yield path


def split_sentences(line: str) -> List[str]:
    chunks = re.split(r"(?<=[\.;!?])\s+", line)
    out = []
    for chunk in chunks:
        s = normalize_ws(chunk)
        if s:
            out.append(s)
    return out


def token_set(text: str) -> Set[str]:
    tokens = re.findall(r"[A-Za-z0-9_\-]+", text.lower())
    return {t for t in tokens if t not in STOPWORDS and len(t) > 1}


def split_preconditions(condition: str) -> List[str]:
    parts = re.split(r"\b(?:and|or)\b", condition, flags=re.IGNORECASE)
    out = [normalize_ws(p) for p in parts if normalize_ws(p)]
    return out or [normalize_ws(condition)]


def infer_external_assumptions(text: str) -> List[str]:
    assumptions = []
    if re.search(r"\b(?:assuming|assume|given|provided that)\b", text, flags=re.IGNORECASE):
        assumptions.append("stated_assumption")
    if re.search(r"\b[A-Z]{2,}\b", text):
        assumptions.append("acronym_dependency")
    if re.search(r"\d", text):
        assumptions.append("numeric_parameter")
    return sorted(set(assumptions))


def classify_reduction(kind: str, condition: str, consequence: str) -> str:
    c = condition.lower()
    y = consequence.lower()

    hard_terms = ["cannot", "must not", "forbidden", "invalid", "only if", "unless"]
    has_hard = any(term in c or term in y for term in hard_terms)
    has_ineq = bool(re.search(r"(?:<=|>=|<|>|==|!=|at least|at most|between|threshold)", condition, re.IGNORECASE))
    conjuncts = len(split_preconditions(condition))

    if kind in {"threshold", "function_map"} and has_ineq:
        return "Strong reduction"
    if has_hard and (has_ineq or conjuncts >= 2):
        return "Strong reduction"
    if kind in {"if_then", "requires", "only_if", "cannot_if"}:
        return "Moderate reduction"
    return "Weak refinement"


def make_failure_modes(kind: str, condition: str, consequence: str) -> List[str]:
    condition = normalize_ws(condition)
    consequence = normalize_ws(consequence)
    if kind in {"if_then", "only_if", "requires", "cannot_if", "threshold", "function_map"}:
        return [f"Condition not met: {condition}; consequence unsupported: {consequence}"]
    return ["No explicit failure mode parsed"]


def parse_primitive(sentence: str) -> List[Tuple[str, str, str]]:
    s = normalize_ws(sentence.strip().lstrip("-*•").strip())
    s = s.replace("`", "")
    s = s.replace("**", "")
    low = s.lower()

    if len(s) < 20:
        return []

    if any(term in low for term in NARRATIVE_FILTER):
        # Hard filter for obvious narrative/theological framing.
        return []

    matches: List[Tuple[str, str, str]] = []

    # If X then Y
    m = re.search(r"\bif\b\s+(.+?)\s+\bthen\b\s+(.+)$", s, flags=re.IGNORECASE)
    if m:
        cond = normalize_ws(m.group(1))
        cons = normalize_ws(m.group(2))
        if cond and cons:
            matches.append(("if_then", cond, cons))

    # If X, Y
    m = re.search(r"^\bif\b\s+(.+?)\s*,\s*(.+)$", s, flags=re.IGNORECASE)
    if m:
        cond = normalize_ws(m.group(1))
        cons = normalize_ws(m.group(2))
        if cond and cons:
            matches.append(("if_then", cond, cons))

    # Y requires A and B
    m = re.search(r"^(.+?)\s+\brequires\b\s+(.+)$", s, flags=re.IGNORECASE)
    if m:
        cons = normalize_ws(m.group(1))
        cond = normalize_ws(m.group(2))
        if cond and cons:
            matches.append(("requires", cond, cons))

    # X must Y
    m = re.search(r"^(.+?)\s+\bmust\b\s+(.+)$", s, flags=re.IGNORECASE)
    if m:
        cond = normalize_ws(m.group(1))
        cons = normalize_ws(m.group(2))
        if cond and cons:
            matches.append(("requires", cond, cons))

    # X is required to Y
    m = re.search(r"^(.+?)\s+\bis required to\b\s+(.+)$", s, flags=re.IGNORECASE)
    if m:
        cond = normalize_ws(m.group(1))
        cons = normalize_ws(m.group(2))
        if cond and cons:
            matches.append(("requires", cond, cons))

    # Y only if X
    m = re.search(r"^(.+?)\s+\bonly if\b\s+(.+)$", s, flags=re.IGNORECASE)
    if m:
        cons = normalize_ws(m.group(1))
        cond = normalize_ws(m.group(2))
        if cond and cons:
            matches.append(("only_if", cond, cons))

    # X cannot occur if Z / X cannot occur without Z
    m = re.search(r"^(.+?)\s+\bcannot\b\s+(.+?)\s+\bif\b\s+(.+)$", s, flags=re.IGNORECASE)
    if m:
        cons = normalize_ws(m.group(1) + " cannot " + m.group(2))
        cond = normalize_ws(m.group(3))
        if cond and cons:
            matches.append(("cannot_if", cond, cons))

    m = re.search(r"^(.+?)\s+\bcannot\b\s+(.+?)\s+\bwithout\b\s+(.+)$", s, flags=re.IGNORECASE)
    if m:
        cons = normalize_ws(m.group(1) + " cannot " + m.group(2))
        cond = normalize_ws(m.group(3))
        if cond and cons:
            matches.append(("cannot_if", cond, cons))

    # No X is allowed (in Y)
    m = re.search(r"^No\s+(.+?)\s+is allowed(?:\s+in\s+(.+))?$", s, flags=re.IGNORECASE)
    if m:
        forbidden = normalize_ws(m.group(1))
        scope = normalize_ws(m.group(2) or "active system state")
        cond = scope
        cons = f"{forbidden} is forbidden"
        matches.append(("cannot_if", cond, cons))

    # Threshold boundary
    has_threshold_signal = bool(
        re.search(r"\bthreshold\b|<=|>=|<|>|==|!=|\bat least\b|\bat most\b|\bbetween\b", s, flags=re.IGNORECASE)
    )
    if has_threshold_signal:
        m = re.search(r"^(.+?)\s+(defines|sets|establishes)\s+(.+)$", s, flags=re.IGNORECASE)
        if m:
            cond = normalize_ws(m.group(1))
            cons = normalize_ws(m.group(3))
            if cond and cons:
                matches.append(("threshold", cond, cons))
        elif re.search(r"<=|>=|<|>|==|!=", s):
            matches.append(("threshold", s, "Boundary condition applies"))

    # Function mapping / arrow mapping
    m = re.search(r"^(.+?)\s*->\s*(.+)$", s)
    if m:
        cond = normalize_ws(m.group(1))
        cons = normalize_ws(m.group(2))
        if cond and cons:
            matches.append(("function_map", cond, cons))

    # Function mapping
    if re.search(r"\bfunction\b|\bmaps\b|\bf\s*\(.+\)", s, flags=re.IGNORECASE):
        m = re.search(r"^(.+?)\s+\bmaps\b\s+(.+?)\s+\bto\b\s+(.+)$", s, flags=re.IGNORECASE)
        if m:
            cond = normalize_ws(m.group(1) + " maps " + m.group(2))
            cons = normalize_ws(m.group(3))
            if cond and cons:
                matches.append(("function_map", cond, cons))
        elif "f(" in low:
            matches.append(("function_map", s, "Function relation holds"))

    # Deduplicate tuples per sentence.
    seen = set()
    out = []
    for kind, cond, cons in matches:
        key = (kind, cond.lower(), cons.lower())
        if key in seen:
            continue
        seen.add(key)
        out.append((kind, cond, cons))
    return out


def extract_primitives(src: Path, max_cp: int) -> List[ConstraintPrimitive]:
    cps: List[ConstraintPrimitive] = []
    seen_hashes: Set[str] = set()
    cp_counter = 1

    for path in iter_text_files(src):
        try:
            raw = path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            continue

        if path.suffix.lower() in {".html", ".htm"}:
            raw = strip_html(raw)

        for line_idx, line in enumerate(raw.splitlines(), start=1):
            for sentence in split_sentences(line):
                triples = parse_primitive(sentence)
                for kind, cond, cons in triples:
                    h = hashlib.sha256((kind + "|" + cond + "|" + cons).lower().encode("utf-8")).hexdigest()
                    if h in seen_hashes:
                        continue
                    seen_hashes.add(h)

                    cp_id = f"CP_{cp_counter:04d}"
                    cp_counter += 1

                    pre = split_preconditions(cond)
                    fail = make_failure_modes(kind, cond, cons)
                    assumptions = infer_external_assumptions(sentence)
                    reduction = classify_reduction(kind, cond, cons)

                    cps.append(
                        ConstraintPrimitive(
                            cp_id=cp_id,
                            kind=kind,
                            condition=cond,
                            consequence=cons,
                            source_path=str(path),
                            source_line=line_idx,
                            raw_text=sentence,
                            preconditions=pre,
                            failure_modes=fail,
                            external_assumptions=assumptions,
                            reduction_strength=reduction,
                        )
                    )

                    if max_cp > 0 and len(cps) >= max_cp:
                        return cps

    return cps


def build_edges(cps: Sequence[ConstraintPrimitive]) -> List[Edge]:
    cp_index = {cp.cp_id.lower(): cp.cp_id for cp in cps}
    id_to_num = {cp.cp_id: i for i, cp in enumerate(cps)}

    cond_tokens = {cp.cp_id: token_set(cp.condition) for cp in cps}
    cons_tokens = {cp.cp_id: token_set(cp.consequence) for cp in cps}

    edges: List[Edge] = []

    # Explicit CP references in conditions.
    ref_pattern = re.compile(r"\bcp[_\-]?(\d{1,4})\b", flags=re.IGNORECASE)
    for cp in cps:
        refs = set(ref_pattern.findall(cp.condition))
        for ref in refs:
            key = f"cp_{int(ref):04d}"
            if key in cp_index:
                src = cp_index[key]
                if id_to_num[src] < id_to_num[cp.cp_id]:
                    edges.append(Edge(source=src, target=cp.cp_id, reason="explicit_reference", score=1.0))

    # Lexical overlap dependencies: consequence(src) overlaps condition(dst).
    for src in cps:
        for dst in cps:
            if src.cp_id == dst.cp_id:
                continue
            if id_to_num[src.cp_id] >= id_to_num[dst.cp_id]:
                # Keep DAG by enforcing forward-only edges by CP order.
                continue
            a = cons_tokens[src.cp_id]
            b = cond_tokens[dst.cp_id]
            if not a or not b:
                continue
            common = a & b
            if len(common) < 1:
                continue
            ratio = len(common) / max(1, len(b))
            if ratio >= 0.34:
                edges.append(Edge(source=src.cp_id, target=dst.cp_id, reason="lexical_overlap", score=round(ratio, 4)))

    # De-dup edges keeping highest score/reason precedence.
    best: Dict[Tuple[str, str], Edge] = {}
    for e in edges:
        k = (e.source, e.target)
        if k not in best:
            best[k] = e
            continue
        cur = best[k]
        if e.score > cur.score:
            best[k] = e
        elif e.score == cur.score and cur.reason != "explicit_reference" and e.reason == "explicit_reference":
            best[k] = e

    reduced = list(best.values())
    reduced.sort(key=lambda e: (e.source, e.target, -e.score))
    return transitive_reduce(reduced)


def adjacency(edges: Sequence[Edge]) -> Dict[str, List[str]]:
    g: Dict[str, List[str]] = defaultdict(list)
    for e in edges:
        g[e.source].append(e.target)
    for k in g:
        g[k] = sorted(set(g[k]))
    return g


def reverse_adjacency(edges: Sequence[Edge]) -> Dict[str, List[str]]:
    g: Dict[str, List[str]] = defaultdict(list)
    for e in edges:
        g[e.target].append(e.source)
    for k in g:
        g[k] = sorted(set(g[k]))
    return g


def reachable(g: Dict[str, List[str]], start: str, goal: str, skip_edge: Optional[Tuple[str, str]] = None) -> bool:
    if start == goal:
        return True
    q = deque([start])
    seen = {start}
    while q:
        cur = q.popleft()
        for nxt in g.get(cur, []):
            if skip_edge and (cur, nxt) == skip_edge:
                continue
            if nxt == goal:
                return True
            if nxt in seen:
                continue
            seen.add(nxt)
            q.append(nxt)
    return False


def transitive_reduce(edges: Sequence[Edge]) -> List[Edge]:
    g = adjacency(edges)
    keep: List[Edge] = []
    edge_lookup = {(e.source, e.target): e for e in edges}
    for e in edges:
        if reachable(g, e.source, e.target, skip_edge=(e.source, e.target)):
            continue
        keep.append(edge_lookup[(e.source, e.target)])
    keep.sort(key=lambda x: (x.source, x.target))
    return keep


def is_load_bearing(node: str, g: Dict[str, List[str]], rg: Dict[str, List[str]]) -> bool:
    preds = rg.get(node, [])
    succs = g.get(node, [])
    if not preds or not succs:
        return False

    # Remove node and test if predecessor->successor connectivity depends on node.
    g2: Dict[str, List[str]] = {}
    for k, vs in g.items():
        if k == node:
            continue
        g2[k] = [v for v in vs if v != node]

    for p in preds:
        for s in succs:
            if not reachable(g2, p, s):
                return True
    return False


def label_redundancy(cps: Sequence[ConstraintPrimitive], edges: Sequence[Edge]) -> Dict[str, str]:
    g = adjacency(edges)
    rg = reverse_adjacency(edges)

    by_cons: Dict[str, List[ConstraintPrimitive]] = defaultdict(list)
    for cp in cps:
        key = normalize_ws(cp.consequence).lower()
        by_cons[key].append(cp)

    labels: Dict[str, str] = {}
    for cp in cps:
        if is_load_bearing(cp.cp_id, g, rg):
            labels[cp.cp_id] = "LB"
            continue

        derived = False
        group = by_cons[normalize_ws(cp.consequence).lower()]
        this_cond = token_set(cp.condition)
        for other in group:
            if other.cp_id == cp.cp_id:
                continue
            other_cond = token_set(other.condition)
            if other_cond and this_cond and other_cond.issubset(this_cond):
                derived = True
                break

        labels[cp.cp_id] = "D" if derived else "R"

    return labels


def build_geometry(cps: Sequence[ConstraintPrimitive], labels: Dict[str, str]) -> Dict[str, List[str]]:
    invariant_region = [cp.cp_id for cp in cps if labels.get(cp.cp_id) == "LB"]

    boundary_conditions = [
        cp.cp_id
        for cp in cps
        if cp.kind in {"threshold", "function_map"}
        or re.search(r"<=|>=|<|>|==|!=|at least|at most|between|threshold", cp.condition, flags=re.IGNORECASE)
    ]

    forbidden_regions = [
        cp.cp_id
        for cp in cps
        if re.search(r"\bcannot\b|\bmust not\b|\bforbidden\b|\binvalid\b", cp.condition + " " + cp.consequence, flags=re.IGNORECASE)
    ]

    allowed_parameter_corridors = [
        cp.cp_id
        for cp in cps
        if cp.reduction_strength in {"Weak refinement", "Moderate reduction"}
        and cp.cp_id not in forbidden_regions
    ]

    return {
        "invariant_region": sorted(set(invariant_region)),
        "boundary_conditions": sorted(set(boundary_conditions)),
        "allowed_parameter_corridors": sorted(set(allowed_parameter_corridors)),
        "forbidden_regions": sorted(set(forbidden_regions)),
    }


def write_json(path: Path, obj) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(obj, indent=2, ensure_ascii=False), encoding="utf-8")


def write_cp_csv(path: Path, cps: Sequence[ConstraintPrimitive], labels: Dict[str, str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(
            f,
            fieldnames=[
                "cp_id",
                "kind",
                "condition",
                "consequence",
                "reduction_strength",
                "redundancy_label",
                "source_path",
                "source_line",
            ],
        )
        writer.writeheader()
        for cp in cps:
            writer.writerow(
                {
                    "cp_id": cp.cp_id,
                    "kind": cp.kind,
                    "condition": cp.condition,
                    "consequence": cp.consequence,
                    "reduction_strength": cp.reduction_strength,
                    "redundancy_label": labels.get(cp.cp_id, "R"),
                    "source_path": cp.source_path,
                    "source_line": cp.source_line,
                }
            )


def write_edges_csv(path: Path, edges: Sequence[Edge]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=["source", "target", "reason", "score"])
        writer.writeheader()
        for e in edges:
            writer.writerow(dataclasses.asdict(e))


def cmd_extract(args: argparse.Namespace) -> int:
    src = Path(args.src).expanduser().resolve()
    out = Path(args.out).expanduser().resolve()
    out.mkdir(parents=True, exist_ok=True)

    if not src.exists():
        raise SystemExit(f"Source path not found: {src}")

    cps = extract_primitives(src, max_cp=args.max_cp)
    edges = build_edges(cps)
    labels = label_redundancy(cps, edges)
    geometry = build_geometry(cps, labels)

    cp_json = []
    for cp in cps:
        row = dataclasses.asdict(cp)
        row["redundancy_label"] = labels.get(cp.cp_id, "R")
        cp_json.append(row)

    dag = {
        "nodes": [cp.cp_id for cp in cps],
        "edges": [dataclasses.asdict(e) for e in edges],
    }

    reduction_counts: Dict[str, int] = defaultdict(int)
    label_counts: Dict[str, int] = defaultdict(int)
    for cp in cps:
        reduction_counts[cp.reduction_strength] += 1
        label_counts[labels.get(cp.cp_id, "R")] += 1

    summary = {
        "source": str(src),
        "cp_count": len(cps),
        "edge_count": len(edges),
        "reduction_counts": dict(sorted(reduction_counts.items())),
        "redundancy_counts": dict(sorted(label_counts.items())),
        "outputs": {
            "constraint_primitives_json": str(out / "constraint_primitives.json"),
            "constraint_primitives_csv": str(out / "constraint_primitives.csv"),
            "dependency_dag_json": str(out / "dependency_dag.json"),
            "dependency_edges_csv": str(out / "dependency_edges.csv"),
            "geometry_map_json": str(out / "geometry_map.json"),
            "summary_json": str(out / "summary.json"),
        },
    }

    write_json(out / "constraint_primitives.json", cp_json)
    write_cp_csv(out / "constraint_primitives.csv", cps, labels)
    write_json(out / "dependency_dag.json", dag)
    write_edges_csv(out / "dependency_edges.csv", edges)
    write_json(out / "geometry_map.json", geometry)
    write_json(out / "summary.json", summary)

    print(f"Source: {src}")
    print(f"Constraint primitives: {len(cps)}")
    print(f"DAG edges: {len(edges)}")
    print(f"Wrote outputs to: {out}")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="NCEP v1.0 deterministic constraint extraction")
    sub = parser.add_subparsers(dest="cmd", required=True)

    ex = sub.add_parser("extract", help="Extract CP primitives and build DAG")
    ex.add_argument("--src", required=True, help="Source file or directory")
    ex.add_argument("--out", required=True, help="Output directory")
    ex.add_argument("--max-cp", type=int, default=0, help="Maximum CP count to emit (0=unbounded)")
    ex.set_defaults(func=cmd_extract)

    return parser


def main(argv: Optional[List[str]] = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    return args.func(args)


if __name__ == "__main__":
    raise SystemExit(main())
