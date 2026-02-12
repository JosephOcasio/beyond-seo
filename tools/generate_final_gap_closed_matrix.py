#!/usr/bin/env python3
"""
Generate a gap-closed risk matrix CSV with explicit verification status.

Design principle:
- Keep verified (local primary-source-backed) rows separate from provisional rows.
- Do not silently promote secondary/uncorroborated claims to verified.
"""

from __future__ import annotations

import csv
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List


ROOT = Path("/Users/josephocasio/Documents/New project")
IRS_JSON = ROOT / "out" / "us_audit" / "dod_irs_all" / "irs_control_group.json"
OUT_CSV = ROOT / "out" / "us_audit" / "final_gap_closed_matrix.csv"
OUT_MD = ROOT / "out" / "us_audit" / "final_gap_closed_matrix.md"


def load_irs_control() -> Dict[str, object]:
    if not IRS_JSON.exists():
        return {}
    return json.loads(IRS_JSON.read_text(encoding="utf-8"))


def build_rows(irs: Dict[str, object]) -> List[Dict[str, object]]:
    rows: List[Dict[str, object]] = []
    now = datetime.now(timezone.utc).isoformat()
    tax = irs.get("irs_tax_gap", {}) if isinstance(irs, dict) else {}

    # IRS rows: verified from local ingested artifacts.
    rows.extend(
        [
            {
                "domain": "IRS",
                "metric": "Gross Tax Gap (TY2022)",
                "value": tax.get("gross_tax_gap_billion_ty2022"),
                "unit": "USD_BILLION",
                "verification_status": "VERIFIED_LOCAL_PRIMARY",
                "evidence_type": "official_irs_page",
                "primary_source": "https://www.irs.gov/statistics/irs-the-tax-gap",
                "secondary_source": "https://files.gao.gov/reports/GAO-25-107375/index.html",
                "open_question": "",
                "next_required_artifact": "",
                "last_updated_utc": now,
            },
            {
                "domain": "IRS",
                "metric": "Voluntary Compliance Rate (TY2022)",
                "value": tax.get("voluntary_compliance_rate_percent"),
                "unit": "PERCENT",
                "verification_status": "VERIFIED_LOCAL_PRIMARY",
                "evidence_type": "official_irs_page",
                "primary_source": "https://www.irs.gov/statistics/irs-the-tax-gap",
                "secondary_source": "",
                "open_question": "",
                "next_required_artifact": "",
                "last_updated_utc": now,
            },
            {
                "domain": "IRS",
                "metric": "Late-September 2025 Contract Transactions (Top-25 Sum)",
                "value": (irs.get("irs_sep_last_week_transactions_summary", {}) or {}).get("sum_transaction_amount"),
                "unit": "USD",
                "verification_status": "VERIFIED_LOCAL_PRIMARY",
                "evidence_type": "usaspending_api_extract",
                "primary_source": "https://api.usaspending.gov/api/v2/search/spending_by_transaction/",
                "secondary_source": "",
                "open_question": "",
                "next_required_artifact": "",
                "last_updated_utc": now,
            },
        ]
    )

    # DOD rows: currently provisional until blocked PDFs are manually dropped.
    rows.extend(
        [
            {
                "domain": "DOD",
                "metric": "Material Weaknesses (FY2025)",
                "value": 26,
                "unit": "COUNT",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://comptroller.war.gov/Portals/45/Documents/afr/fy2025/DoD_FY25_Agency_Financial_Report.pdf",
                "secondary_source": "",
                "open_question": "Confirm exact count from FY2025 AFR or related OIG/GAO source text.",
                "next_required_artifact": "DoD_FY25_Agency_Financial_Report.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "DOD Assets Reported (FY2024/FY2025 context)",
                "value": 4.1,
                "unit": "USD_TRILLION",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://www.gao.gov/assets/gao-25-108052.pdf",
                "secondary_source": "https://files.gao.gov/reports/GAO-25-108052/index.html",
                "open_question": "Verify asset total and period labeling directly from GAO-25-108052 PDF.",
                "next_required_artifact": "GAO-25-108052.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "DOD Physical Assets Share of Federal Physical Assets",
                "value": 82,
                "unit": "PERCENT",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://www.gao.gov/assets/gao-25-108191.pdf",
                "secondary_source": "https://files.gao.gov/reports/GAO-25-108191/index.html",
                "open_question": "Confirm percent and denominator definition from GAO-25-108191 primary PDF.",
                "next_required_artifact": "GAO-25-108191.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "Legacy Systems Retirement Savings Through FY2029",
                "value": 760,
                "unit": "USD_MILLION_PER_YEAR",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://www.gao.gov/assets/gao-25-107427.pdf",
                "secondary_source": "",
                "open_question": "Verify projection assumptions and baseline from GAO-25-107427.",
                "next_required_artifact": "GAO-25-107427.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "Legacy Systems Planned for Retirement",
                "value": 89,
                "unit": "COUNT",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://www.gao.gov/assets/gao-25-107427.pdf",
                "secondary_source": "",
                "open_question": "Confirm retirement count and timeline from GAO-25-107427 primary PDF.",
                "next_required_artifact": "GAO-25-107427.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "Clean Audit Goal Deadline",
                "value": "2028-12-31",
                "unit": "DATE",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://www.gao.gov/assets/gao-25-107427.pdf",
                "secondary_source": "",
                "open_question": "Confirm statutory/management source text for 2028 clean-audit target.",
                "next_required_artifact": "GAO-25-107427.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "Components with Clean Opinions",
                "value": 11,
                "unit": "COUNT",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://www.gao.gov/assets/gao-25-108052.pdf",
                "secondary_source": "https://files.gao.gov/reports/GAO-25-108052/index.html",
                "open_question": "Confirm component count and period from GAO-25-108052 primary PDF.",
                "next_required_artifact": "GAO-25-108052.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "Share of DOD Assets with Clean Component Opinions",
                "value": 43,
                "unit": "PERCENT",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://www.gao.gov/assets/gao-25-108052.pdf",
                "secondary_source": "https://files.gao.gov/reports/GAO-25-108052/index.html",
                "open_question": "Confirm clean-opinion asset share from GAO-25-108052 primary PDF.",
                "next_required_artifact": "GAO-25-108052.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "AFR Production Cost",
                "value": 529925,
                "unit": "USD",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://comptroller.war.gov/Portals/45/Documents/afr/fy2025/DoD_FY25_Agency_Financial_Report.pdf",
                "secondary_source": "",
                "open_question": "Confirm reported AFR preparation cost in DoD FY2025 AFR primary document.",
                "next_required_artifact": "DoD_FY25_Agency_Financial_Report.pdf",
                "last_updated_utc": now,
            },
            {
                "domain": "DOD",
                "metric": "Consecutive Disclaimer Count",
                "value": 7,
                "unit": "COUNT",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "claimed_summary",
                "primary_source": "https://comptroller.war.gov/Portals/45/Documents/afr/fy2025/DoD_FY25_Agency_Financial_Report.pdf",
                "secondary_source": "",
                "open_question": "Confirm exact consecutive-disclaimer count for FY2025 in primary AFR/OIG records.",
                "next_required_artifact": "DoD_FY25_Agency_Financial_Report.pdf",
                "last_updated_utc": now,
            },
        ]
    )

    # SSA rows: provisional pending trustees primary.
    rows.extend(
        [
            {
                "domain": "SSA",
                "metric": "OASI Depletion Year",
                "value": 2033,
                "unit": "YEAR",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "secondary_summary",
                "primary_source": "https://www.ssa.gov/OACT/TR/2025/",
                "secondary_source": "https://crr.bc.edu/social-securitys-financial-outlook-the-2025-update-in-perspective/",
                "open_question": "Confirm depletion projection directly from trustees primary artifact.",
                "next_required_artifact": "SSA_2025_Trustees_Report_primary.pdf_or_html_export",
                "last_updated_utc": now,
            },
            {
                "domain": "SSA",
                "metric": "Projected Automatic Benefit Cut at Depletion",
                "value": 23,
                "unit": "PERCENT",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "secondary_summary",
                "primary_source": "https://www.ssa.gov/OACT/TR/2025/",
                "secondary_source": "https://bipartisanpolicy.org/article/2025-social-security-trustees-report-explained/",
                "open_question": "Confirm exact reduction ratio and scope directly in trustees primary report tables.",
                "next_required_artifact": "SSA_2025_Trustees_Report_primary.pdf_or_html_export",
                "last_updated_utc": now,
            },
            {
                "domain": "SSA",
                "metric": "75-year Actuarial Deficit",
                "value": 3.82,
                "unit": "PERCENT_OF_TAXABLE_PAYROLL",
                "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
                "evidence_type": "secondary_summary",
                "primary_source": "https://www.ssa.gov/OACT/TR/2025/",
                "secondary_source": "https://www.everycrsreport.com/reports/IF13045.html",
                "open_question": "Confirm exact actuarial deficit value and definition period.",
                "next_required_artifact": "SSA_2025_Trustees_Report_primary.pdf_or_html_export",
                "last_updated_utc": now,
            },
        ]
    )

    # GAO High-Risk list row.
    rows.append(
        {
            "domain": "FEDERAL_CROSSCUT",
            "metric": "High-Risk Areas Count (2025 update)",
            "value": 38,
            "unit": "COUNT",
            "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
            "evidence_type": "index_html_only",
            "primary_source": "https://www.gao.gov/high-risk-list",
            "secondary_source": "https://files.gao.gov/reports/GAO-25-107743/index.html",
            "open_question": "Confirm full 2025 list and savings figures from PDF body.",
            "next_required_artifact": "GAO-25-107743.pdf",
            "last_updated_utc": now,
        }
    )
    rows.append(
        {
            "domain": "FEDERAL_CROSSCUT",
            "metric": "High-Risk Program Cumulative Savings",
            "value": 759,
            "unit": "USD_BILLION",
            "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
            "evidence_type": "claimed_summary",
            "primary_source": "https://www.gao.gov/assets/gao-25-107743.pdf",
            "secondary_source": "https://files.gao.gov/reports/GAO-25-107743/index.html",
            "open_question": "Confirm cumulative savings figure and reference period from primary GAO-25-107743 PDF.",
            "next_required_artifact": "GAO-25-107743.pdf",
            "last_updated_utc": now,
        }
    )
    rows.append(
        {
            "domain": "FEDERAL_CROSSCUT",
            "metric": "High-Risk Program Average Annual Savings",
            "value": 40,
            "unit": "USD_BILLION_PER_YEAR",
            "verification_status": "PROVISIONAL_NEEDS_PRIMARY_DOC",
            "evidence_type": "claimed_summary",
            "primary_source": "https://www.gao.gov/assets/gao-25-107743.pdf",
            "secondary_source": "https://www.gao.gov/high-risk-list",
            "open_question": "Confirm annualized savings method and baseline period from primary report.",
            "next_required_artifact": "GAO-25-107743.pdf",
            "last_updated_utc": now,
        }
    )

    return rows


def write_outputs(rows: List[Dict[str, object]]) -> None:
    OUT_CSV.parent.mkdir(parents=True, exist_ok=True)
    fieldnames = [
        "domain",
        "metric",
        "value",
        "unit",
        "verification_status",
        "evidence_type",
        "primary_source",
        "secondary_source",
        "open_question",
        "next_required_artifact",
        "last_updated_utc",
    ]
    with OUT_CSV.open("w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=fieldnames)
        w.writeheader()
        w.writerows(rows)

    by_status: Dict[str, int] = {}
    for r in rows:
        s = r["verification_status"]
        by_status[s] = by_status.get(s, 0) + 1

    md = []
    md.append("# Final Gap-Closed Matrix (Current State)")
    md.append("")
    md.append(f"Generated: {datetime.now(timezone.utc).isoformat()}")
    md.append("")
    md.append("## Status counts")
    for k, v in sorted(by_status.items()):
        md.append(f"- {k}: **{v}**")
    md.append("")
    md.append("## Interpretation")
    md.append("- IRS control-group metrics are currently source-verified from local artifacts.")
    md.append("- DOD/SSA/GAO crosscut metrics remain provisional until primary blocked PDFs are added.")
    md.append("")
    md.append("## Output")
    md.append(f"- CSV: `{OUT_CSV}`")
    OUT_MD.write_text("\n".join(md) + "\n", encoding="utf-8")


def main() -> None:
    irs = load_irs_control()
    rows = build_rows(irs)
    write_outputs(rows)
    print(OUT_CSV)
    print(OUT_MD)


if __name__ == "__main__":
    main()
