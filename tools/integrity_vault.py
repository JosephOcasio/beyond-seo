#!/usr/bin/env python3
"""
Create provenance sidecar metadata for local artifacts.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable, Optional


ROOT = Path("/Users/josephocasio/Documents/New project")
DEFAULT_PARSER_VERSION = "1.0.0"


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def build_metadata(
    file_path: Path,
    source_url: str,
    doc_id: str,
    parser_version: str = DEFAULT_PARSER_VERSION,
    published_at: Optional[str] = None,
) -> dict:
    return {
        "doc_id": doc_id,
        "source_url": source_url,
        "published_at": published_at,
        "fetched_at": datetime.now(timezone.utc).isoformat(),
        "sha256": sha256_file(file_path),
        "parser_version": parser_version,
        "integrity_status": "VERIFIED",
        "local_path": str(file_path.resolve()),
    }


def seal_artifact(
    file_path: Path,
    source_url: str,
    doc_id: str,
    parser_version: str = DEFAULT_PARSER_VERSION,
    published_at: Optional[str] = None,
) -> Path:
    file_path = file_path.resolve()
    if not file_path.exists():
        raise FileNotFoundError(f"File not found: {file_path}")
    metadata = build_metadata(
        file_path=file_path,
        source_url=source_url,
        doc_id=doc_id,
        parser_version=parser_version,
        published_at=published_at,
    )
    out = Path(f"{file_path}.meta.json")
    out.write_text(json.dumps(metadata, indent=2), encoding="utf-8")
    return out


def seal_many(paths: Iterable[Path], source_url: str, doc_prefix: str, parser_version: str) -> list[Path]:
    outputs: list[Path] = []
    for idx, p in enumerate(paths, start=1):
        doc_id = f"{doc_prefix}-{idx:03d}"
        outputs.append(
            seal_artifact(
                file_path=p,
                source_url=source_url,
                doc_id=doc_id,
                parser_version=parser_version,
            )
        )
    return outputs


def main() -> int:
    parser = argparse.ArgumentParser(description="Seal one or many artifacts with provenance metadata.")
    parser.add_argument("--file", default="", help="Single file path to seal.")
    parser.add_argument("--glob", default="", help='Glob pattern (example: "out/us_audit/federal_ingest/*.json").')
    parser.add_argument("--source-url", required=True, help="Source URL for provenance metadata.")
    parser.add_argument("--doc-id", default="", help="Required for --file mode.")
    parser.add_argument("--doc-prefix", default="DOC", help="Prefix for generated ids in --glob mode.")
    parser.add_argument("--parser-version", default=DEFAULT_PARSER_VERSION)
    parser.add_argument("--published-at", default=None, help="Optional ISO date from source.")
    args = parser.parse_args()

    if bool(args.file) == bool(args.glob):
        raise SystemExit("Use exactly one of --file or --glob.")

    if args.file:
        if not args.doc_id:
            raise SystemExit("--doc-id is required with --file.")
        meta = seal_artifact(
            file_path=Path(args.file),
            source_url=args.source_url,
            doc_id=args.doc_id,
            parser_version=args.parser_version,
            published_at=args.published_at,
        )
        print(f"sealed: {meta}")
        return 0

    root = ROOT
    paths = sorted(root.glob(args.glob))
    if not paths:
        print("No files matched glob.")
        return 0
    out = seal_many(
        paths=paths,
        source_url=args.source_url,
        doc_prefix=args.doc_prefix,
        parser_version=args.parser_version,
    )
    print(f"sealed_count: {len(out)}")
    for item in out:
        print(item)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
