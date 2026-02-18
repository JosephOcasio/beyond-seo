#!/usr/bin/env python3
"""Lattice-valued bottleneck path optimizer for high-interference flyby comms."""

from __future__ import annotations

import argparse
import heapq
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List, Tuple


@dataclass
class Edge:
    src: str
    dst: str
    bandwidth_mbps: float
    snr_db: float
    latency_ms: float
    loss: float


def normalize(value: float, lo: float, hi: float) -> float:
    if hi <= lo:
        return 0.0
    x = (value - lo) / (hi - lo)
    return max(0.0, min(1.0, x))


def edge_quality(edge: Edge, interference: float) -> Dict[str, float | bool]:
    # Interference in [0, 1] degrades channel.
    bw = edge.bandwidth_mbps * (1.0 - 0.70 * interference)
    snr = edge.snr_db - 12.0 * interference
    loss = min(1.0, edge.loss + 0.20 * interference)
    lat = edge.latency_ms * (1.0 + 0.40 * interference)

    bw_n = normalize(bw, 0.5, 50.0)
    snr_n = normalize(snr, 3.0, 30.0)
    rel_n = 1.0 - loss

    # Max-min lattice valuation: q = meet(bw, snr, reliability)
    q = min(bw_n, snr_n, rel_n)
    admissible = (snr >= 3.0) and (loss <= 0.25)

    return {
        "admissible": admissible,
        "q": q,
        "bw_mbps": bw,
        "snr_db": snr,
        "latency_ms": lat,
        "loss": loss,
    }


def build_graph(edges: List[Edge]) -> Dict[str, List[Edge]]:
    g: Dict[str, List[Edge]] = {}
    for e in edges:
        g.setdefault(e.src, []).append(e)
    return g


def widest_path(edges: List[Edge], src: str, dst: str, interference: float) -> Dict[str, object]:
    g = build_graph(edges)
    best_q: Dict[str, float] = {src: 1.0}
    best_lat: Dict[str, float] = {src: 0.0}
    parent: Dict[str, str] = {}

    # max-heap via negative q, then latency tie-break
    heap: List[Tuple[float, float, str]] = [(-1.0, 0.0, src)]

    while heap:
        neg_q, lat, node = heapq.heappop(heap)
        q_now = -neg_q
        if node == dst:
            break
        if q_now < best_q.get(node, -1.0) - 1e-12:
            continue

        for e in g.get(node, []):
            metrics = edge_quality(e, interference)
            if not metrics["admissible"]:
                continue

            q_edge = float(metrics["q"])
            q_candidate = min(q_now, q_edge)
            lat_candidate = lat + float(metrics["latency_ms"])

            old_q = best_q.get(e.dst, -1.0)
            old_lat = best_lat.get(e.dst, float("inf"))

            improve = (q_candidate > old_q + 1e-12) or (
                abs(q_candidate - old_q) <= 1e-12 and lat_candidate < old_lat
            )
            if improve:
                best_q[e.dst] = q_candidate
                best_lat[e.dst] = lat_candidate
                parent[e.dst] = node
                heapq.heappush(heap, (-q_candidate, lat_candidate, e.dst))

    if dst not in best_q:
        return {
            "status": "NO_PATH",
            "reason": "No admissible path under current interference",
            "interference": interference,
        }

    # Reconstruct path
    path = [dst]
    cur = dst
    while cur != src:
        cur = parent[cur]
        path.append(cur)
    path.reverse()

    return {
        "status": "VERIFIED",
        "interference": interference,
        "path": path,
        "bottleneck_quality": best_q[dst],
        "total_latency_ms": best_lat[dst],
    }


def default_edges() -> List[Edge]:
    return [
        Edge("probe", "relay_a", 35.0, 18.0, 120.0, 0.03),
        Edge("probe", "relay_b", 18.0, 14.0, 100.0, 0.04),
        Edge("relay_a", "dsn_goldstone", 42.0, 24.0, 85.0, 0.02),
        Edge("relay_b", "dsn_goldstone", 28.0, 20.0, 90.0, 0.03),
        Edge("relay_a", "dsn_canberra", 26.0, 16.0, 95.0, 0.03),
        Edge("relay_b", "dsn_canberra", 31.0, 22.0, 110.0, 0.02),
    ]


def parse_edges(path: str) -> List[Edge]:
    raw = json.loads(Path(path).read_text(encoding="utf-8"))
    edges = []
    for r in raw:
        edges.append(
            Edge(
                src=r["src"],
                dst=r["dst"],
                bandwidth_mbps=float(r["bandwidth_mbps"]),
                snr_db=float(r["snr_db"]),
                latency_ms=float(r["latency_ms"]),
                loss=float(r["loss"]),
            )
        )
    return edges


def main() -> int:
    parser = argparse.ArgumentParser(description="Lattice-valued bottleneck transmission optimizer")
    parser.add_argument("--src", default="probe")
    parser.add_argument("--dst", default="dsn_goldstone")
    parser.add_argument("--interference", type=float, default=0.5)
    parser.add_argument("--edges-json", default="", help="Optional JSON edge list")
    parser.add_argument("--out", default="")
    args = parser.parse_args()

    if not (0.0 <= args.interference <= 1.0):
        raise SystemExit("--interference must be in [0,1]")

    edges = parse_edges(args.edges_json) if args.edges_json else default_edges()
    result = widest_path(edges, args.src, args.dst, args.interference)

    if args.out:
        Path(args.out).parent.mkdir(parents=True, exist_ok=True)
        Path(args.out).write_text(json.dumps(result, indent=2), encoding="utf-8")

    print(json.dumps(result, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
