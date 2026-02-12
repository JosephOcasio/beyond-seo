#!/usr/bin/env python3
"""
Build a local DOD + IRS citizen-audit pack from official sources.

Outputs:
  out/us_audit/dod_irs_all/
    - summary.md
    - metrics.json
    - manifest.json
    - raw/<source files>
"""

from __future__ import annotations

import hashlib
import json
import re
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List, Optional

import requests


TIMEOUT = 45
USER_AGENT = "citizen-audit-pack/1.0 (+local)"


@dataclass
class Source:
    key: str
    url: str
    kind: str  # "json" | "html"
    note: str


SOURCES: List[Source] = [
    Source(
        key="treasury_debt_to_penny_latest",
        url=(
            "https://api.fiscaldata.treasury.gov/services/api/fiscal_service/"
            "v2/accounting/od/debt_to_penny?sort=-record_date&page[size]=1"
        ),
        kind="json",
        note="Official latest national debt snapshot.",
    ),
    Source(
        key="usaspending_toptier_agencies",
        url="https://api.usaspending.gov/api/v2/references/toptier_agencies/",
        kind="json",
        note="Top-tier agency spending and obligations summary.",
    ),
    Source(
        key="gao_high_risk_list",
        url="https://www.gao.gov/high-risk-list",
        kind="html",
        note="GAO high-risk overview (federal vulnerabilities).",
    ),
    Source(
        key="gao_high_risk_2025_report",
        url="https://www.gao.gov/products/gao-25-107743",
        kind="html",
        note="GAO 2025 High-Risk report landing page.",
    ),
    Source(
        key="gao_dod_financial_management_2025",
        url="https://www.gao.gov/products/gao-25-108191",
        kind="html",
        note="DOD financial management + fraud risk testimony/report.",
    ),
    Source(
        key="gao_dod_remediation_status_2025",
        url="https://www.gao.gov/products/gao-25-107427",
        kind="html",
        note="DOD remediation status against audit mandate.",
    ),
    Source(
        key="gao_dod_balance_sheet_auditability_2025",
        url="https://www.gao.gov/products/gao-25-108052",
        kind="html",
        note="DOD FY2024 balance sheet auditability insights.",
    ),
    Source(
        key="dodig_fy2025_auditor_reports",
        url=(
            "https://www.dodig.mil/Reports/Audits-and-Evaluations/Article/4365178/"
            "independent-auditors-reports-on-the-dod-fy-2025-financial-statements/"
        ),
        kind="html",
        note="DoD OIG: FY2025 independent auditor reports.",
    ),
    Source(
        key="irs_tax_gap",
        url="https://www.irs.gov/statistics/irs-the-tax-gap",
        kind="html",
        note="IRS tax-gap official page (gross/net).",
    ),
    Source(
        key="gao_irs_tax_filing_2024_2025_report",
        url="https://www.gao.gov/products/gao-25-107375",
        kind="html",
        note="GAO: IRS filing season progress/challenges.",
    ),
    Source(
        key="irs_data_book_index",
        url="https://www.irs.gov/statistics/soi-tax-stats-all-years-irs-data-books",
        kind="html",
        note="IRS Data Book annual index.",
    ),
]


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def fetch(url: str) -> requests.Response:
    headers = {"User-Agent": USER_AGENT}
    return requests.get(url, headers=headers, timeout=TIMEOUT)


def post_json(url: str, payload: Dict[str, object]) -> requests.Response:
    headers = {"User-Agent": USER_AGENT}
    return requests.post(url, json=payload, headers=headers, timeout=TIMEOUT)


def parse_irs_tax_gap_numbers(html: str) -> Dict[str, Optional[str]]:
    text = re.sub(r"<[^>]+>", " ", html)
    text = re.sub(r"\s+", " ", text)

    gross = re.search(r"gross tax gap[^$]{0,120}\$\s?([0-9][0-9,]*)\s?billion", text, re.I)
    net = re.search(r"net tax gap[^$]{0,120}\$\s?([0-9][0-9,]*)\s?billion", text, re.I)
    vcr = re.search(r"projected VCR is\s?([0-9]+(?:\.[0-9]+)?)\s?percent", text, re.I)

    return {
        "gross_tax_gap_billion": gross.group(1).replace(",", "") if gross else None,
        "net_tax_gap_billion": net.group(1).replace(",", "") if net else None,
        "voluntary_compliance_rate_percent": vcr.group(1) if vcr else None,
    }


def usaspending_contract_codes() -> List[str]:
    resp = fetch("https://api.usaspending.gov/api/v2/references/award_types/")
    if resp.status_code != 200:
        return []
    payload = resp.json()
    return list(payload.get("contracts", {}).keys())


def fetch_top_transactions(
    agency_filter: Dict[str, str],
    contract_codes: List[str],
    start_date: Optional[str],
    end_date: Optional[str],
    limit: int = 10,
) -> Dict[str, object]:
    filters: Dict[str, object] = {
        "agencies": [agency_filter],
        "award_type_codes": contract_codes,
    }
    if start_date and end_date:
        filters["time_period"] = [{"start_date": start_date, "end_date": end_date}]

    payload: Dict[str, object] = {
        "filters": filters,
        "fields": [
            "Transaction Amount",
            "Action Date",
            "Recipient Name",
            "Award ID",
            "Awarding Agency",
        ],
        "page": 1,
        "limit": limit,
        "sort": "Transaction Amount",
        "order": "desc",
    }
    resp = post_json(
        "https://api.usaspending.gov/api/v2/search/spending_by_transaction/",
        payload,
    )
    if resp.status_code != 200:
        return {"status_code": resp.status_code, "results": []}
    data = resp.json()
    return {"status_code": 200, "results": data.get("results", [])}


def build_summary_md(
    generated_at: str,
    metrics: Dict[str, object],
    source_index: List[Dict[str, object]],
) -> str:
    debt = metrics.get("national_debt_latest", {})
    dod = metrics.get("dod_toptier", {})
    treas = metrics.get("treasury_toptier", {})
    irs_tax_gap = metrics.get("irs_tax_gap", {})
    gross_gap = irs_tax_gap.get("gross_tax_gap_billion")
    net_gap = irs_tax_gap.get("net_tax_gap_billion")
    dod_sep = metrics.get("dod_sep_last_week_top_transactions", [])
    irs_sep = metrics.get("irs_sep_last_week_top_transactions", [])

    lines = []
    lines.append("# DOD + IRS Citizen Audit Pack")
    lines.append("")
    lines.append(f"Generated: {generated_at}")
    lines.append("")
    lines.append("## Snapshot Metrics")
    lines.append("")
    lines.append(
        f"- National debt (`Debt to the Penny`): **${debt.get('tot_pub_debt_out_amt', 'N/A')}** "
        f"on **{debt.get('record_date', 'N/A')}**"
    )
    lines.append(
        f"- USAspending DOD (active FY/FQ obligations): **${dod.get('obligated_amount', 'N/A')}** "
        f"(FY {dod.get('active_fy', 'N/A')}, FQ {dod.get('active_fq', 'N/A')})"
    )
    lines.append(
        f"- USAspending Treasury (active FY/FQ obligations): **${treas.get('obligated_amount', 'N/A')}** "
        f"(FY {treas.get('active_fy', 'N/A')}, FQ {treas.get('active_fq', 'N/A')})"
    )
    gross_fmt = f"${gross_gap}B" if gross_gap else "N/A"
    net_fmt = f"${net_gap}B" if net_gap else "N/A"
    lines.append(f"- IRS projected gross tax gap (TY2022): **{gross_fmt}**")
    lines.append(f"- IRS projected net tax gap (TY2022): **{net_fmt}**")
    lines.append("")
    lines.append("## Late-September Contract Activity (2025-09-24 to 2025-09-30)")
    lines.append("")
    lines.append("### DOD: Top transactions by amount")
    lines.append("")
    if dod_sep:
        for row in dod_sep[:10]:
            lines.append(
                f"- ${row.get('Transaction Amount', 0):,.2f} | {row.get('Action Date')} | "
                f"{row.get('Recipient Name')} | Award {row.get('Award ID')}"
            )
    else:
        lines.append("- No transaction rows returned.")
    lines.append("")
    lines.append("### IRS (Treasury Sub-tier): Top transactions by amount")
    lines.append("")
    if irs_sep:
        for row in irs_sep[:10]:
            lines.append(
                f"- ${row.get('Transaction Amount', 0):,.2f} | {row.get('Action Date')} | "
                f"{row.get('Recipient Name')} | Award {row.get('Award ID')}"
            )
    else:
        lines.append("- No transaction rows returned.")
    lines.append("")
    lines.append("## DOD Audit Posture")
    lines.append("")
    lines.append("- DOD remains under recurring disclaimer opinions at the department-wide level.")
    lines.append("- Key references included in this pack:")
    lines.append("  - GAO-25-108191")
    lines.append("  - GAO-25-107427")
    lines.append("  - GAO-25-108052")
    lines.append("  - DoD OIG FY2025 independent auditor reports")
    lines.append("")
    lines.append("## IRS Audit Posture")
    lines.append("")
    lines.append("- IRS tax administration remains a GAO high-risk area (Enforcement of Tax Laws).")
    lines.append("- Key references included in this pack:")
    lines.append("  - GAO-25-107375")
    lines.append("  - IRS tax-gap official page")
    lines.append("  - IRS Data Book index")
    lines.append("")
    lines.append("## Source Index")
    lines.append("")
    for src in source_index:
        lines.append(
            f"- `{src['key']}` — [{src['url']}]({src['url']}) "
            f"(status: {src['status_code']}, sha256: `{src['sha256'][:16]}...`)"
        )
    lines.append("")
    return "\n".join(lines)


def main() -> None:
    root = Path.cwd()
    out_dir = root / "out" / "us_audit" / "dod_irs_all"
    raw_dir = out_dir / "raw"
    out_dir.mkdir(parents=True, exist_ok=True)
    raw_dir.mkdir(parents=True, exist_ok=True)

    generated_at = datetime.now(timezone.utc).isoformat()
    manifest: List[Dict[str, object]] = []
    errors: List[Dict[str, object]] = []

    national_debt_latest: Dict[str, object] = {}
    dod_toptier: Dict[str, object] = {}
    treasury_toptier: Dict[str, object] = {}
    irs_tax_gap: Dict[str, Optional[str]] = {}
    dod_sep_last_week_top_transactions: List[Dict[str, object]] = []
    irs_sep_last_week_top_transactions: List[Dict[str, object]] = []

    for source in SOURCES:
        try:
            resp = fetch(source.url)
            content = resp.content
            digest = sha256_bytes(content)

            ext = ".json" if source.kind == "json" else ".html"
            file_path = raw_dir / f"{source.key}{ext}"
            file_path.write_bytes(content)

            entry = {
                "key": source.key,
                "url": source.url,
                "note": source.note,
                "status_code": resp.status_code,
                "bytes": len(content),
                "sha256": digest,
                "saved_to": str(file_path),
            }
            manifest.append(entry)

            if resp.status_code != 200:
                errors.append({"key": source.key, "url": source.url, "status_code": resp.status_code})
                continue

            if source.key == "treasury_debt_to_penny_latest":
                payload = resp.json()
                if payload.get("data"):
                    national_debt_latest = payload["data"][0]

            if source.key == "usaspending_toptier_agencies":
                payload = resp.json()
                agencies = payload.get("results", [])
                for a in agencies:
                    if a.get("abbreviation") == "DOD":
                        dod_toptier = a
                    if a.get("abbreviation") == "TREAS":
                        treasury_toptier = a

            if source.key == "irs_tax_gap":
                irs_tax_gap = parse_irs_tax_gap_numbers(resp.text)

        except Exception as exc:
            errors.append({"key": source.key, "url": source.url, "error": str(exc)})

    # Extra USAspending pulls for the "year-end spend spike" lens.
    try:
        contract_codes = usaspending_contract_codes()
        if not contract_codes:
            errors.append(
                {
                    "key": "usaspending_contract_award_type_codes",
                    "error": "No contract award type codes returned.",
                }
            )
        else:
            dod_sep = fetch_top_transactions(
                agency_filter={"type": "awarding", "tier": "toptier", "name": "Department of Defense"},
                contract_codes=contract_codes,
                start_date="2025-09-24",
                end_date="2025-09-30",
                limit=25,
            )
            irs_sep = fetch_top_transactions(
                agency_filter={"type": "awarding", "tier": "subtier", "name": "Internal Revenue Service"},
                contract_codes=contract_codes,
                start_date="2025-09-24",
                end_date="2025-09-30",
                limit=25,
            )

            if dod_sep["status_code"] != 200:
                errors.append(
                    {
                        "key": "dod_sep_last_week_transactions",
                        "status_code": dod_sep["status_code"],
                    }
                )
            else:
                dod_sep_last_week_top_transactions = dod_sep["results"]
                (raw_dir / "dod_sep_last_week_transactions.json").write_text(
                    json.dumps(dod_sep_last_week_top_transactions, indent=2),
                    encoding="utf-8",
                )

            if irs_sep["status_code"] != 200:
                errors.append(
                    {
                        "key": "irs_sep_last_week_transactions",
                        "status_code": irs_sep["status_code"],
                    }
                )
            else:
                irs_sep_last_week_top_transactions = irs_sep["results"]
                (raw_dir / "irs_sep_last_week_transactions.json").write_text(
                    json.dumps(irs_sep_last_week_top_transactions, indent=2),
                    encoding="utf-8",
                )
    except Exception as exc:
        errors.append({"key": "usaspending_extra_queries", "error": str(exc)})

    metrics = {
        "generated_at_utc": generated_at,
        "national_debt_latest": national_debt_latest,
        "dod_toptier": dod_toptier,
        "treasury_toptier": treasury_toptier,
        "irs_tax_gap": irs_tax_gap,
        "dod_sep_last_week_top_transactions": dod_sep_last_week_top_transactions,
        "irs_sep_last_week_top_transactions": irs_sep_last_week_top_transactions,
        "errors": errors,
    }

    summary_md = build_summary_md(generated_at, metrics, manifest)
    (out_dir / "summary.md").write_text(summary_md, encoding="utf-8")
    (out_dir / "metrics.json").write_text(json.dumps(metrics, indent=2), encoding="utf-8")
    (out_dir / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")

    print(f"Wrote: {out_dir / 'summary.md'}")
    print(f"Wrote: {out_dir / 'metrics.json'}")
    print(f"Wrote: {out_dir / 'manifest.json'}")
    if errors:
        print(f"Completed with {len(errors)} fetch/parsing errors. See metrics.json errors[]")
    else:
        print("Completed without fetch/parsing errors.")


if __name__ == "__main__":
    main()
