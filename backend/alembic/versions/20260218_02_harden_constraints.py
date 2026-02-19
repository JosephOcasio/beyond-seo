"""harden prototype constraints

Revision ID: 20260218_02
Revises: 20260218_01
Create Date: 2026-02-19 00:05:00
"""

from __future__ import annotations

from alembic import op


# revision identifiers, used by Alembic.
revision = "20260218_02"
down_revision = "20260218_01"
branch_labels = None
depends_on = None


def upgrade() -> None:
    with op.batch_alter_table("claims", recreate="always") as batch_op:
        batch_op.create_check_constraint(
            "ck_claims_source_type",
            "source_type IN ('text','image','pdf')",
        )
        batch_op.create_check_constraint(
            "ck_claims_external_id_nonempty",
            "length(trim(external_id)) > 0",
        )
        batch_op.create_check_constraint(
            "ck_claims_source_name_nonempty",
            "length(trim(source_name)) > 0",
        )
        batch_op.create_check_constraint(
            "ck_claims_content_hash_len",
            "length(content_hash) >= 32",
        )
        batch_op.create_check_constraint(
            "ck_claims_content_text_nonempty",
            "length(trim(content_text)) > 0",
        )

    with op.batch_alter_table("constraint_scores", recreate="always") as batch_op:
        batch_op.create_check_constraint(
            "ck_constraint_scores_admissibility_range",
            "admissibility_score >= 0.0 AND admissibility_score <= 1.0",
        )
        batch_op.create_check_constraint(
            "ck_constraint_scores_drift_range",
            "drift_score >= 0.0 AND drift_score <= 1.0",
        )
        batch_op.create_check_constraint(
            "ck_constraint_scores_status",
            "governance_status IN ('PASS','HOLD','VETO')",
        )
        batch_op.create_check_constraint(
            "ck_constraint_scores_pipeline_nonempty",
            "length(trim(pipeline_version)) > 0",
        )

    with op.batch_alter_table("ledger_events", recreate="always") as batch_op:
        batch_op.create_check_constraint(
            "ck_ledger_events_stage_nonempty",
            "length(trim(stage)) > 0",
        )
        batch_op.create_check_constraint(
            "ck_ledger_events_event_type_nonempty",
            "length(trim(event_type)) > 0",
        )
        batch_op.create_check_constraint(
            "ck_ledger_events_payload_nonempty",
            "length(trim(payload_json)) > 0",
        )


def downgrade() -> None:
    with op.batch_alter_table("ledger_events", recreate="always") as batch_op:
        batch_op.drop_constraint("ck_ledger_events_payload_nonempty", type_="check")
        batch_op.drop_constraint("ck_ledger_events_event_type_nonempty", type_="check")
        batch_op.drop_constraint("ck_ledger_events_stage_nonempty", type_="check")

    with op.batch_alter_table("constraint_scores", recreate="always") as batch_op:
        batch_op.drop_constraint("ck_constraint_scores_pipeline_nonempty", type_="check")
        batch_op.drop_constraint("ck_constraint_scores_status", type_="check")
        batch_op.drop_constraint("ck_constraint_scores_drift_range", type_="check")
        batch_op.drop_constraint("ck_constraint_scores_admissibility_range", type_="check")

    with op.batch_alter_table("claims", recreate="always") as batch_op:
        batch_op.drop_constraint("ck_claims_content_text_nonempty", type_="check")
        batch_op.drop_constraint("ck_claims_content_hash_len", type_="check")
        batch_op.drop_constraint("ck_claims_source_name_nonempty", type_="check")
        batch_op.drop_constraint("ck_claims_external_id_nonempty", type_="check")
        batch_op.drop_constraint("ck_claims_source_type", type_="check")
