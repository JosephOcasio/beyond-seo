#!/usr/bin/env python3
"""Build Jacobian report PDF template for compliance evidence packages."""

from __future__ import annotations

from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.pagesizes import LETTER
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import inch
from reportlab.platypus import (
    SimpleDocTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
    PageBreak,
)


def table(rows, col_widths):
    t = Table(rows, colWidths=col_widths, repeatRows=1)
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#0F172A")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
                ("FONTSIZE", (0, 0), (-1, -1), 9),
                ("GRID", (0, 0), (-1, -1), 0.5, colors.HexColor("#CBD5E1")),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F8FAFC")]),
                ("LEFTPADDING", (0, 0), (-1, -1), 5),
                ("RIGHTPADDING", (0, 0), (-1, -1), 5),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ]
        )
    )
    return t


def build_pdf(out_pdf: Path) -> None:
    out_pdf.parent.mkdir(parents=True, exist_ok=True)

    doc = SimpleDocTemplate(
        str(out_pdf),
        pagesize=LETTER,
        leftMargin=0.7 * inch,
        rightMargin=0.7 * inch,
        topMargin=0.75 * inch,
        bottomMargin=0.7 * inch,
        title="Jacobian Gate Compliance Report Template",
        author="Lucidity Governance",
    )

    styles = getSampleStyleSheet()
    h1 = ParagraphStyle(
        "H1",
        parent=styles["Heading1"],
        fontName="Helvetica-Bold",
        fontSize=18,
        leading=22,
        textColor=colors.HexColor("#0F172A"),
        spaceAfter=8,
    )
    h2 = ParagraphStyle(
        "H2",
        parent=styles["Heading2"],
        fontName="Helvetica-Bold",
        fontSize=12,
        leading=15,
        textColor=colors.HexColor("#0F172A"),
        spaceBefore=10,
        spaceAfter=5,
    )
    body = ParagraphStyle(
        "Body",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=9.5,
        leading=13,
        textColor=colors.HexColor("#111827"),
    )
    mono = ParagraphStyle(
        "Mono",
        parent=body,
        fontName="Courier",
        fontSize=8.5,
        leading=11,
        backColor=colors.HexColor("#F1F5F9"),
        borderColor=colors.HexColor("#CBD5E1"),
        borderWidth=0.5,
        borderPadding=4,
    )

    story = []

    # Cover
    story.append(Paragraph("Jacobian Gate Compliance Report", h1))
    story.append(Paragraph("Template for DOJ and Federal Procurement Evidence Package", h2))
    story.append(Spacer(1, 0.12 * inch))
    story.append(
        Paragraph(
            "Use this template to produce deterministic, signed Jacobian and uncertainty compliance evidence aligned to EO 14319 and OMB M-26-04.",
            body,
        )
    )
    story.append(Spacer(1, 0.2 * inch))

    cover_rows = [
        ["Report ID", "REPLACE_REPORT_ID"],
        ["Operator", "REPLACE_OPERATOR"],
        ["Prepared Date (UTC)", "YYYY-MM-DDTHH:MM:SSZ"],
        ["System ID", "REPLACE_SYSTEM_ID"],
        ["Model Version", "REPLACE_MODEL_VERSION"],
        ["Manifest Ref", "compliance_manifest.template.yaml -> concrete version"],
    ]
    story.append(table([["Field", "Value"], *cover_rows], [2.0 * inch, 4.5 * inch]))

    story.append(Spacer(1, 0.18 * inch))
    story.append(Paragraph("Decision Scalar Mapping", h2))
    story.append(
        table(
            [
                ["Decision", "Scalar", "Meaning"],
                ["PASS", "0", "Admissible under active constraints"],
                ["REFUSE_UNCERTAIN", "1", "Underdetermined or insufficient evidence"],
                ["VETO_POLICY / HARD_STOP", "3", "Constraint or policy violation"],
            ],
            [1.9 * inch, 0.8 * inch, 3.8 * inch],
        )
    )

    story.append(PageBreak())

    # Section 1 - Executive summary
    story.append(Paragraph("1. Executive Summary", h1))
    story.append(Paragraph("Complete this section after running all audits.", body))
    story.append(Spacer(1, 0.08 * inch))
    story.append(
        table(
            [
                ["Control Area", "Status", "Evidence"],
                ["C1 Uncertainty disclosure", "REPLACE", "report section + artifact URI"],
                ["C2 Neutrality guardrails", "REPLACE", "evaluation artifact URI"],
                ["C3 Solicitation clauses", "REPLACE", "contract template reference"],
                ["C5 Policy/reporting by 2026-03-11", "REPLACE", "policy URI + ticket workflow"],
                ["C6 Minimum transparency packet", "REPLACE", "AUP/card/resources/feedback refs"],
            ],
            [2.3 * inch, 1.0 * inch, 3.2 * inch],
        )
    )

    story.append(Spacer(1, 0.14 * inch))
    story.append(Paragraph("Inference Refusal Register", h2))
    story.append(
        table(
            [
                ["Missing Input", "Impact", "Owner", "Target Date"],
                ["Legal numeric uncertainty thresholds", "Cannot finalize tau_refuse", "Legal", "REPLACE"],
                ["System list + model hashes", "Cannot attest full scope", "ML Ops", "REPLACE"],
                ["Dataset access authorization", "Cannot run case specific fairness audit", "Data Gov", "REPLACE"],
            ],
            [2.2 * inch, 2.1 * inch, 1.0 * inch, 1.2 * inch],
        )
    )

    story.append(PageBreak())

    # Section 2 - Jacobian results
    story.append(Paragraph("2. Jacobian Gate Diagnostics", h1))
    story.append(
        Paragraph(
            "Attach one row per evaluated model endpoint and one appendix per detailed SVD spectrum.",
            body,
        )
    )
    story.append(Spacer(1, 0.08 * inch))

    jac_rows = [
        [
            "Model Endpoint",
            "Input Shape",
            "J Shape",
            "Rank",
            "Rank Min",
            "Condition",
            "Decision",
            "Artifact URI",
        ],
        ["REPLACE", "REPLACE", "REPLACE", "REPLACE", "REPLACE", "REPLACE", "REPLACE", "s3://..."],
        ["REPLACE", "REPLACE", "REPLACE", "REPLACE", "REPLACE", "REPLACE", "REPLACE", "s3://..."],
    ]
    story.append(table(jac_rows, [1.1 * inch, 0.8 * inch, 0.7 * inch, 0.45 * inch, 0.55 * inch, 0.8 * inch, 0.9 * inch, 1.65 * inch]))

    story.append(Spacer(1, 0.12 * inch))
    story.append(Paragraph("Deterministic execution metadata", h2))
    story.append(
        Paragraph(
            "Record: script version, container hash, Python version, dependency lockfile hash, and random seed policy.",
            body,
        )
    )
    story.append(
        Paragraph(
            "script=jacobian_gate_audit.py@REPLACE<br/>container=sha256:REPLACE<br/>python=REPLACE<br/>deps_lock=sha256:REPLACE<br/>seed_policy=REPLACE",
            mono,
        )
    )

    story.append(PageBreak())

    # Section 3 - Uncertainty, refusal, signatures
    story.append(Paragraph("3. Uncertainty and Refusal Enforcement", h1))
    story.append(Paragraph("Document the live policy and observed runtime behavior.", body))

    story.append(Spacer(1, 0.08 * inch))
    story.append(
        table(
            [
                ["Metric", "Threshold", "Observed P50", "Observed P95", "Refusal Rate", "Status"],
                ["predictive_entropy", "REPLACE", "REPLACE", "REPLACE", "REPLACE", "REPLACE"],
                ["softmax_max", "REPLACE", "REPLACE", "REPLACE", "REPLACE", "REPLACE"],
            ],
            [1.3 * inch, 0.8 * inch, 0.85 * inch, 0.85 * inch, 0.8 * inch, 0.8 * inch],
        )
    )

    story.append(Spacer(1, 0.12 * inch))
    story.append(Paragraph("4. Signature and Chain of Custody", h1))
    story.append(
        table(
            [
                ["Item", "Value"],
                ["Attestation schema", "lucidity_attestation.schema.json"],
                ["Signature algorithm", "ed25519 or rsa-pss-sha256"],
                ["Key ID", "REPLACE_KEY_ID"],
                ["Signed artifact URI", "s3://REPLACE_BUCKET/attestations/REPLACE.json"],
                ["Artifact digest (sha256)", "REPLACE_DIGEST"],
            ],
            [2.1 * inch, 4.4 * inch],
        )
    )

    story.append(Spacer(1, 0.14 * inch))
    story.append(Paragraph("Approvals", h2))
    story.append(
        table(
            [
                ["Role", "Name", "Timestamp (UTC)", "Signature Ref"],
                ["Engineering Owner", "REPLACE", "REPLACE", "REPLACE"],
                ["Compliance / Legal", "REPLACE", "REPLACE", "REPLACE"],
                ["Security Owner", "REPLACE", "REPLACE", "REPLACE"],
            ],
            [1.5 * inch, 1.7 * inch, 1.6 * inch, 1.7 * inch],
        )
    )

    doc.build(story)


def main() -> None:
    out_pdf = Path('/Users/josephocasio/Documents/New project/output/pdf/jacobian_report_template.pdf')
    build_pdf(out_pdf)
    print(out_pdf)


if __name__ == '__main__':
    main()
