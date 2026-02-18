#!/usr/bin/env python3
"""Rotate internal access key hash in runGovernanceAudit backend function."""

from __future__ import annotations

import argparse
import hashlib
import re
from pathlib import Path


DEFAULT_FUNCTION_PATH = Path("base44/functions/runGovernanceAudit/index.ts")


def main() -> int:
  parser = argparse.ArgumentParser(description="Rotate INTERNAL_ACCESS_KEY_HASH in backend function")
  parser.add_argument("--key", required=True, help="New plaintext access key")
  parser.add_argument(
    "--file",
    default=str(DEFAULT_FUNCTION_PATH),
    help=f"Function file path (default: {DEFAULT_FUNCTION_PATH})",
  )
  args = parser.parse_args()

  target = Path(args.file).expanduser().resolve()
  if not target.exists():
    raise FileNotFoundError(f"Function file not found: {target}")

  new_hash = hashlib.sha256(args.key.encode("utf-8")).hexdigest()
  text = target.read_text(encoding="utf-8")

  pattern = r'(const INTERNAL_ACCESS_KEY_HASH =\s*")[0-9a-f]{64}(";)'
  updated, count = re.subn(pattern, rf"\g<1>{new_hash}\2", text, count=1)
  if count != 1:
    raise RuntimeError("Could not locate INTERNAL_ACCESS_KEY_HASH constant to update.")

  target.write_text(updated, encoding="utf-8")
  print(f"Updated hash in {target}")
  print("Next: base44 deploy -y")
  return 0


if __name__ == "__main__":
  raise SystemExit(main())
