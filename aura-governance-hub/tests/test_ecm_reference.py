import base64
import json
import sys
import unittest
from pathlib import Path

from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.hazmat.primitives.serialization import Encoding, PrivateFormat, PublicFormat, NoEncryption
from jsonschema import Draft7Validator

sys.path.insert(0, '/Users/josephocasio/Documents/New project/aura-governance-hub/tools')

from ecm_reference import (  # noqa: E402
    attach_signature_ed25519,
    deltaf_rank_surrogate,
    evaluate_promotion_bundle,
    validate_monotonic_transition,
    verify_bundle_signatures,
)


class TestEcmReference(unittest.TestCase):
    def setUp(self):
        self.bundle_path = Path('/Users/josephocasio/Documents/New project/aura-governance-hub/docs/ecm_bundle.example.json')
        self.schema_path = Path('/Users/josephocasio/Documents/New project/aura-governance-hub/schemas/ecm_bundle.schema.json')
        self.bundle = json.loads(self.bundle_path.read_text(encoding='utf-8'))
        self.schema = json.loads(self.schema_path.read_text(encoding='utf-8'))

    def test_schema_validation(self):
        errors = list(Draft7Validator(self.schema).iter_errors(self.bundle))
        self.assertEqual(errors, [])

    def test_rank_deltaf_positive(self):
        prior = [12.34, 4.56, 1.23, 0.45, 0.1, 0.01, 0.001]
        post = [9.99, 1.5, 0.3, 0.02, 0.001]
        delta = deltaf_rank_surrogate(prior, post)
        self.assertGreater(delta, 0.0)

    def test_non_monotonic_transition_rejected(self):
        ok, reason = validate_monotonic_transition('E3_CAUSAL', 'E1_STRUCTURAL', True)
        self.assertFalse(ok)
        self.assertEqual(reason, 'NON_MONOTONIC_BACKWARD')

    def test_bundle_evaluation_pass_when_signed(self):
        # Build a valid signed bundle from the example
        private_key = Ed25519PrivateKey.generate()
        private_bytes = private_key.private_bytes(Encoding.Raw, PrivateFormat.Raw, NoEncryption())
        public_bytes = private_key.public_key().public_bytes(Encoding.Raw, PublicFormat.Raw)

        proof_no_sig = dict(self.bundle['promotion_proof'])
        proof_no_sig['signatures'] = []
        proof_signed = attach_signature_ed25519(proof_no_sig, 'test-key-01', private_bytes)

        bundle = {
            'promotion_request': self.bundle['promotion_request'],
            'promotion_proof': proof_signed,
        }

        eval_report = evaluate_promotion_bundle(bundle, numeric_tolerance=1e-12)
        self.assertEqual(eval_report['status'], 'PASS')

        key_manifest = {
            'keys': [
                {
                    'key_id': 'test-key-01',
                    'algo': 'Ed25519',
                    'public_key_b64': base64.b64encode(public_bytes).decode('ascii'),
                }
            ]
        }
        sig_report = verify_bundle_signatures(bundle, key_manifest)
        self.assertTrue(sig_report['all_valid'])

    def test_bundle_deltaf_mismatch_veto(self):
        tampered = json.loads(json.dumps(self.bundle))
        tampered['promotion_proof']['deltaF'] = tampered['promotion_proof']['deltaF'] + 0.1
        # Remove placeholder signature so we only test delta logic
        tampered['promotion_proof']['signatures'] = []

        report = evaluate_promotion_bundle(tampered, numeric_tolerance=1e-12)
        self.assertEqual(report['status'], 'VETO_POLICY')
        self.assertIn('DELTAF_MISMATCH', report['reasons'])


if __name__ == '__main__':
    unittest.main()
