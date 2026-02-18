#!/usr/bin/env python3
"""Generate a signed ECM bundle example plus key manifest for verifier demos."""

from __future__ import annotations

import base64
import json
from pathlib import Path

from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.hazmat.primitives.serialization import (
    Encoding,
    PrivateFormat,
    PublicFormat,
    NoEncryption,
)

from ecm_reference import attach_signature_ed25519


def main() -> int:
    repo = Path('/Users/josephocasio/Documents/New project/aura-governance-hub')
    bundle_path = repo / 'docs' / 'ecm_bundle.example.json'
    out_dir = repo / 'output' / 'ecm'
    out_dir.mkdir(parents=True, exist_ok=True)

    bundle = json.loads(bundle_path.read_text(encoding='utf-8'))

    # Remove placeholder signatures before signing
    bundle['promotion_proof']['signatures'] = []

    private_key = Ed25519PrivateKey.generate()
    private_bytes = private_key.private_bytes(Encoding.Raw, PrivateFormat.Raw, NoEncryption())
    public_bytes = private_key.public_key().public_bytes(Encoding.Raw, PublicFormat.Raw)

    key_id = 'lucidity_attestation_key_demo_2026'

    signed_proof = attach_signature_ed25519(
        proof=bundle['promotion_proof'],
        key_id=key_id,
        private_key_bytes=private_bytes,
    )

    signed_bundle = {
        'promotion_request': bundle['promotion_request'],
        'promotion_proof': signed_proof,
    }

    key_manifest = {
        'keys': [
            {
                'key_id': key_id,
                'algo': 'Ed25519',
                'public_key_b64': base64.b64encode(public_bytes).decode('ascii'),
                'created_for': 'ECM verifier demo',
            }
        ]
    }

    signed_bundle_path = out_dir / 'ecm_bundle.signed.example.json'
    key_manifest_path = out_dir / 'ecm_key_manifest.example.json'

    signed_bundle_path.write_text(json.dumps(signed_bundle, indent=2) + '\n', encoding='utf-8')
    key_manifest_path.write_text(json.dumps(key_manifest, indent=2) + '\n', encoding='utf-8')

    # private key saved only for local test reproduction; do not use in production
    private_key_path = out_dir / 'ecm_private_key.raw.demo.bin'
    private_key_path.write_bytes(private_bytes)

    print(json.dumps({
        'signed_bundle': str(signed_bundle_path),
        'key_manifest': str(key_manifest_path),
        'private_key_demo': str(private_key_path),
    }, indent=2))

    return 0


if __name__ == '__main__':
    raise SystemExit(main())
