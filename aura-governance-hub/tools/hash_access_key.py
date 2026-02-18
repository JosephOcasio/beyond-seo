#!/usr/bin/env python3
"""Generate SHA-256 hash for internal access key configuration."""

from __future__ import annotations

import argparse
import hashlib


def main() -> int:
  parser = argparse.ArgumentParser(description="Generate SHA-256 hash for AURA internal access key")
  parser.add_argument("--key", required=True, help="Plaintext access key")
  args = parser.parse_args()

  digest = hashlib.sha256(args.key.encode("utf-8")).hexdigest()
  print(digest)
  return 0


if __name__ == "__main__":
  raise SystemExit(main())
