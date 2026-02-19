"""Typed contracts for admissibility API requests and responses."""

from __future__ import annotations

from datetime import datetime
from typing import Any, Literal, Optional

from pydantic import BaseModel, ConfigDict, Field, model_validator


class EvaluateClaimRequest(BaseModel):
    source_name: str = Field(min_length=1, max_length=255)
    source_type: Literal["text", "image", "pdf"]
    raw_text: Optional[str] = None
    file_path: Optional[str] = None
    metadata: dict[str, Any] = Field(default_factory=dict)
    min_admissibility: float = Field(default=0.60, ge=0.0, le=1.0)
    drift_tolerance: float = Field(default=0.20, ge=0.0, le=1.0)
    enable_ocr: bool = True

    @model_validator(mode="after")
    def validate_source_payload(self) -> "EvaluateClaimRequest":
        if self.source_type == "text":
            if not (self.raw_text or "").strip():
                raise ValueError("raw_text is required when source_type='text'")
            return self

        if not self.enable_ocr:
            raise ValueError("enable_ocr must be true for image/pdf source types")
        if not (self.file_path or "").strip():
            raise ValueError("file_path is required when source_type='image' or source_type='pdf'")
        return self


class Measurement(BaseModel):
    words: int
    characters: int
    lines: int
    formula_count: int
    formula_density: float
    digit_ratio: float
    formulas: list[str] = Field(default_factory=list)


class GovernanceVerdict(BaseModel):
    status: Literal["PASS", "HOLD", "VETO"]
    reason: str


class EvaluateClaimResponse(BaseModel):
    claim_id: str
    source_name: str
    source_type: str
    created_at: datetime
    measurement: Measurement
    admissibility_score: float
    drift_score: float
    governance: GovernanceVerdict
    pipeline_version: str


class LedgerEventOut(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    stage: str
    event_type: str
    payload: dict[str, Any]
    created_at: datetime


class ClaimReportResponse(BaseModel):
    claim_id: str
    source_name: str
    source_type: str
    created_at: datetime
    metadata: dict[str, Any]
    measurement: Measurement
    admissibility_score: float
    drift_score: float
    governance: GovernanceVerdict
    pipeline_version: str
    ledger: list[LedgerEventOut]
