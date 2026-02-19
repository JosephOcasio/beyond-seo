"""create prototype tables

Revision ID: 20260218_01
Revises:
Create Date: 2026-02-18 23:50:00
"""

from __future__ import annotations

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = "20260218_01"
down_revision = None
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.create_table(
        "claims",
        sa.Column("id", sa.Integer(), primary_key=True, nullable=False),
        sa.Column("external_id", sa.String(length=64), nullable=False),
        sa.Column("source_name", sa.String(length=255), nullable=False),
        sa.Column("source_type", sa.String(length=32), nullable=False),
        sa.Column("source_path", sa.String(length=1024), nullable=False, server_default=""),
        sa.Column("content_hash", sa.String(length=128), nullable=False),
        sa.Column("content_text", sa.Text(), nullable=False),
        sa.Column("metadata_json", sa.Text(), nullable=False, server_default="{}"),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_claims_external_id", "claims", ["external_id"], unique=True)
    op.create_index("ix_claims_source_name", "claims", ["source_name"], unique=False)
    op.create_index("ix_claims_content_hash", "claims", ["content_hash"], unique=False)

    op.create_table(
        "constraint_scores",
        sa.Column("id", sa.Integer(), primary_key=True, nullable=False),
        sa.Column("claim_id", sa.Integer(), sa.ForeignKey("claims.id", ondelete="CASCADE"), nullable=False),
        sa.Column("measurement_json", sa.Text(), nullable=False),
        sa.Column("admissibility_score", sa.Float(), nullable=False),
        sa.Column("drift_score", sa.Float(), nullable=False),
        sa.Column("governance_status", sa.String(length=32), nullable=False),
        sa.Column("governance_reason", sa.String(length=255), nullable=False, server_default=""),
        sa.Column("pipeline_version", sa.String(length=32), nullable=False, server_default="v1"),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_constraint_scores_claim_id", "constraint_scores", ["claim_id"], unique=False)

    op.create_table(
        "ledger_events",
        sa.Column("id", sa.Integer(), primary_key=True, nullable=False),
        sa.Column("claim_id", sa.Integer(), sa.ForeignKey("claims.id", ondelete="CASCADE"), nullable=False),
        sa.Column("stage", sa.String(length=64), nullable=False),
        sa.Column("event_type", sa.String(length=64), nullable=False, server_default="decision"),
        sa.Column("payload_json", sa.Text(), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_ledger_events_claim_id", "ledger_events", ["claim_id"], unique=False)
    op.create_index("ix_ledger_events_stage", "ledger_events", ["stage"], unique=False)
    op.create_index("ix_ledger_events_created_at", "ledger_events", ["created_at"], unique=False)


def downgrade() -> None:
    op.drop_index("ix_ledger_events_created_at", table_name="ledger_events")
    op.drop_index("ix_ledger_events_stage", table_name="ledger_events")
    op.drop_index("ix_ledger_events_claim_id", table_name="ledger_events")
    op.drop_table("ledger_events")

    op.drop_index("ix_constraint_scores_claim_id", table_name="constraint_scores")
    op.drop_table("constraint_scores")

    op.drop_index("ix_claims_content_hash", table_name="claims")
    op.drop_index("ix_claims_source_name", table_name="claims")
    op.drop_index("ix_claims_external_id", table_name="claims")
    op.drop_table("claims")
