"""SQLAlchemy models for claims, scores, and append-only ledger events."""

from __future__ import annotations

from datetime import datetime, timezone

from sqlalchemy import DateTime, Float, ForeignKey, Integer, String, Text
from sqlalchemy.orm import Mapped, mapped_column, relationship

from backend.app.db import Base


def utc_now() -> datetime:
    return datetime.now(timezone.utc)


class Claim(Base):
    __tablename__ = "claims"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    external_id: Mapped[str] = mapped_column(String(64), nullable=False, unique=True, index=True)
    source_name: Mapped[str] = mapped_column(String(255), nullable=False, index=True)
    source_type: Mapped[str] = mapped_column(String(32), nullable=False)
    source_path: Mapped[str] = mapped_column(String(1024), nullable=False, default="")
    content_hash: Mapped[str] = mapped_column(String(128), nullable=False, index=True)
    content_text: Mapped[str] = mapped_column(Text, nullable=False)
    metadata_json: Mapped[str] = mapped_column(Text, nullable=False, default="{}")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now, nullable=False)

    scores: Mapped[list[ConstraintScore]] = relationship(
        "ConstraintScore", back_populates="claim", cascade="all, delete-orphan", passive_deletes=True
    )
    ledger_events: Mapped[list[LedgerEvent]] = relationship(
        "LedgerEvent", back_populates="claim", cascade="all, delete-orphan", passive_deletes=True
    )


class ConstraintScore(Base):
    __tablename__ = "constraint_scores"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    claim_id: Mapped[int] = mapped_column(ForeignKey("claims.id", ondelete="CASCADE"), nullable=False, index=True)
    measurement_json: Mapped[str] = mapped_column(Text, nullable=False)
    admissibility_score: Mapped[float] = mapped_column(Float, nullable=False)
    drift_score: Mapped[float] = mapped_column(Float, nullable=False)
    governance_status: Mapped[str] = mapped_column(String(32), nullable=False)
    governance_reason: Mapped[str] = mapped_column(String(255), nullable=False, default="")
    pipeline_version: Mapped[str] = mapped_column(String(32), nullable=False, default="v1")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now, nullable=False)

    claim: Mapped[Claim] = relationship("Claim", back_populates="scores")


class LedgerEvent(Base):
    __tablename__ = "ledger_events"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    claim_id: Mapped[int] = mapped_column(ForeignKey("claims.id", ondelete="CASCADE"), nullable=False, index=True)
    stage: Mapped[str] = mapped_column(String(64), nullable=False, index=True)
    event_type: Mapped[str] = mapped_column(String(64), nullable=False, default="decision")
    payload_json: Mapped[str] = mapped_column(Text, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now, nullable=False, index=True)

    claim: Mapped[Claim] = relationship("Claim", back_populates="ledger_events")
