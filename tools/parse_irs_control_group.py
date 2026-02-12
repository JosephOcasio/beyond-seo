#!/usr/bin/env python3
"""
Build an IRS control-group snapshot from already fetched local artifacts.
"""

from __future__ import annotations

import json
import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List, Optional


ROOT = Path("/Users/josephocasio/Documents/New project")
AUDIT_DIR = ROOT / "out" / "us_audit" / "dod_irs_all"
RAW_DIR = AUDIT_DIR / "raw"


def _read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="ignore")


def parse_irs_tax_gap(html: str) -> Dict[str, Optional[float]]:
    text = re.sub(r"<[^>]+>", " ", html)
    text = re.sub(r"\s+", " ", text)

    gross = re.search(r"TY 2022 is \$\s*([0-9,]+)\s*billion", text, flags=re.I)
    vcr = re.search(r"projected VCR is\s*([0-9]+(?:\.[0-9]+)?)\s*percent", text, flags=re.I)
    nonfiling = re.search(r"Nonfiling[^$]{0,80}\$\s*([0-9,]+)\s*billion", text, flags=re.I)
    underreporting = re.search(r"Underreporting[^$]{0,120}\$\s*([0-9,]+)\s*billion", text, flags=re.I)
    underpayment = re.search(r"Underpayment[^$]{0,120}\$\s*([0-9,]+)\s*billion", text, flags=re.I)
    net = re.search(r"net tax gap[^$]{0,120}\$\s*([0-9,]+)\s*billion", text, flags=re.I)

    def f(m: Optional[re.Match]) -> Optional[float]:
        if not m:
            return None
        return float(m.group(1).replace(",", ""))

    return {
        "gross_tax_gap_billion_ty2022": f(gross),
        "net_tax_gap_billion_ty2022": f(net),
        "voluntary_compliance_rate_percent": f(vcr),
        "nonfiling_gap_billion": f(nonfiling),
        "underreporting_gap_billion": f(underreporting),
        "underpayment_gap_billion": f(underpayment),
    }


def parse_gao_irs_title(html: str) -> Dict[str, Optional[str]]:
    m = re.search(r"<title>(.*?)</title>", html, flags=re.I | re.S)
    title = None
    if m:
        title = re.sub(r"\s+", " ", m.group(1)).strip()
    report_id = None
    if title:
        rid = re.search(r"(GAO-[0-9]{2}-[0-9]{6})", title)
        if rid:
            report_id = rid.group(1)
    return {"report_title": title, "report_id": report_id}


def summarize_transactions(rows: List[Dict[str, object]]) -> Dict[str, object]:
    if not rows:
        return {
            "count": 0,
            "sum_transaction_amount": 0.0,
            "max_transaction_amount": 0.0,
            "max_transaction_record": None,
        }

    amounts = [float(r.get("Transaction Amount", 0.0) or 0.0) for r in rows]
    max_idx = max(range(len(rows)), key=lambda i: amounts[i])
    return {
        "count": len(rows),
        "sum_transaction_amount": round(sum(amounts), 2),
        "max_transaction_amount": round(max(amounts), 2),
        "max_transaction_record": rows[max_idx],
    }


def main() -> None:
    irs_tax_gap_html = _read_text(RAW_DIR / "irs_tax_gap.html")
    gao_irs_html = _read_text((ROOT / "inbox" / "us_sources" / "GAO-25-107375.html"))

    tx_path = RAW_DIR / "irs_sep_last_week_transactions.json"
    tx_rows: List[Dict[str, object]] = json.loads(tx_path.read_text(encoding="utf-8")) if tx_path.exists() else []

    payload = {
        "generated_at_utc": datetime.now(timezone.utc).isoformat(),
        "source_files": {
            "irs_tax_gap_html": str(RAW_DIR / "irs_tax_gap.html"),
            "gao_25_107375_html": str(ROOT / "inbox" / "us_sources" / "GAO-25-107375.html"),
            "irs_sep_last_week_transactions_json": str(tx_path),
        },
        "irs_tax_gap": parse_irs_tax_gap(irs_tax_gap_html),
        "gao_25_107375": parse_gao_irs_title(gao_irs_html),
        "irs_sep_last_week_transactions_summary": summarize_transactions(tx_rows),
    }

    out_json = AUDIT_DIR / "irs_control_group.json"
    out_md = AUDIT_DIR / "irs_control_group.md"

    out_json.write_text(json.dumps(payload, indent=2), encoding="utf-8")

    tg = payload["irs_tax_gap"]
    tx = payload["irs_sep_last_week_transactions_summary"]
    gao = payload["gao_25_107375"]
    net_gap = tg.get("net_tax_gap_billion_ty2022")
    net_gap_str = "N/A" if net_gap is None else f"${net_gap}B"
    underpayment = tg.get("underpayment_gap_billion")
    underpayment_str = "N/A" if underpayment is None else f"${underpayment}B"
    md = []
    md.append("# IRS Control Group Snapshot")
    md.append("")
    md.append(f"Generated: {payload['generated_at_utc']}")
    md.append("")
    md.append("## Tax Gap (TY2022)")
    md.append("")
    md.append(f"- Gross tax gap: **${tg.get('gross_tax_gap_billion_ty2022')}B**")
    md.append(f"- Net tax gap: **{net_gap_str}**")
    md.append(f"- Voluntary compliance rate: **{tg.get('voluntary_compliance_rate_percent')}%**")
    md.append(f"- Nonfiling: **${tg.get('nonfiling_gap_billion')}B**")
    md.append(f"- Underreporting: **${tg.get('underreporting_gap_billion')}B**")
    md.append(f"- Underpayment: **{underpayment_str}**")
    md.append("")
    md.append("## GAO IRS Report")
    md.append("")
    md.append(f"- Report ID: **{gao.get('report_id')}**")
    md.append(f"- Title: **{gao.get('report_title')}**")
    md.append("")
    md.append("## IRS Late-September 2025 Contract Transactions")
    md.append("")
    md.append(f"- Row count: **{tx.get('count')}**")
    md.append(f"- Sum transaction amount: **${tx.get('sum_transaction_amount'):,.2f}**")
    md.append(f"- Max transaction amount: **${tx.get('max_transaction_amount'):,.2f}**")
    max_rec = tx.get("max_transaction_record")
    if max_rec:
        md.append(
            f"- Largest transaction: `{max_rec.get('Award ID')}` | "
            f"{max_rec.get('Recipient Name')} | {max_rec.get('Action Date')}"
        )

    out_md.write_text("\n".join(md) + "\n", encoding="utf-8")
    print(out_json)
    print(out_md)


if __name__ == "__main__":
    main()
