#!/usr/bin/env python3
"""
Pull public U.S. federal data from Treasury FiscalData and USAspending.

Outputs JSON artifacts that can be sealed with tools/integrity_vault.py.
"""

from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, Optional
from urllib.parse import urlparse

import requests


ROOT = Path("/Users/josephocasio/Documents/New project")
DEFAULT_OUT = ROOT / "out" / "us_audit" / "federal_ingest"
USER_AGENT = "federal-ingest/1.0 (+local-audit)"
TIMEOUT = 45


class FedDataPuller:
    def __init__(self, base_dir: Path = DEFAULT_OUT):
        self.base_dir = Path(base_dir)
        self.base_dir.mkdir(parents=True, exist_ok=True)
        self.session = requests.Session()
        self.session.headers.update({"User-Agent": USER_AGENT})
        self._contract_codes_cache: Optional[list[str]] = None

    def contract_award_type_codes(self) -> list[str]:
        """Return USAspending contract award type code list (required by search endpoints)."""
        if self._contract_codes_cache is not None:
            return self._contract_codes_cache
        url = "https://api.usaspending.gov/api/v2/references/award_types/"
        resp = self.session.get(url, timeout=TIMEOUT)
        resp.raise_for_status()
        payload = resp.json()
        self._contract_codes_cache = list(payload.get("contracts", {}).keys())
        return self._contract_codes_cache

    def get_fiscal_debt(self, start_date: Optional[str], page_size: int) -> tuple[Path, str]:
        """Pull Debt to the Penny data."""
        url = (
            "https://api.fiscaldata.treasury.gov/services/api/fiscal_service/"
            "v2/accounting/od/debt_to_penny"
        )
        params: Dict[str, Any] = {"sort": "-record_date", "page[size]": page_size}
        if start_date:
            params["filter"] = f"record_date:gte:{start_date}"
        resp = self.session.get(url, params=params, timeout=TIMEOUT)
        resp.raise_for_status()
        return self._save_payload(
            payload=resp.json(),
            source_url=resp.url,
            stem="treasury_debt_to_penny",
        )

    def search_spending_by_agency_award(
        self,
        agency_name: str,
        fiscal_year: int,
        limit: int,
        tier: str = "toptier",
    ) -> tuple[Path, str]:
        """Pull award-level spending rows from USAspending."""
        url = "https://api.usaspending.gov/api/v2/search/spending_by_award/"
        contract_codes = self.contract_award_type_codes()
        payload = {
            "filters": {
                "agencies": [{"type": "awarding", "tier": tier, "name": agency_name}],
                "time_period": [
                    {"start_date": f"{fiscal_year - 1}-10-01", "end_date": f"{fiscal_year}-09-30"}
                ],
                "award_type_codes": contract_codes,
            },
            "fields": [
                "Award ID",
                "Recipient Name",
                "Award Amount",
                "Award Type",
                "Awarding Agency",
            ],
            "page": 1,
            "limit": limit,
        }
        resp = self.session.post(url, json=payload, timeout=TIMEOUT)
        if resp.status_code >= 400:
            raise requests.HTTPError(
                f"{resp.status_code} {resp.reason} for {url}: {resp.text[:500]}",
                response=resp,
            )
        safe_agency = agency_name.lower().replace(" ", "_").replace("/", "_")
        return self._save_payload(
            payload=resp.json(),
            source_url=url,
            stem=f"usaspending_awards_{safe_agency}_fy{fiscal_year}",
            request_payload=payload,
        )

    def search_spending_by_agency_transaction(
        self,
        agency_name: str,
        start_date: str,
        end_date: str,
        limit: int,
        tier: str = "toptier",
    ) -> tuple[Path, str]:
        """Pull transaction-level spending rows from USAspending."""
        url = "https://api.usaspending.gov/api/v2/search/spending_by_transaction/"
        contract_codes = self.contract_award_type_codes()
        payload = {
            "filters": {
                "agencies": [{"type": "awarding", "tier": tier, "name": agency_name}],
                "time_period": [{"start_date": start_date, "end_date": end_date}],
                "award_type_codes": contract_codes,
            },
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
        resp = self.session.post(url, json=payload, timeout=TIMEOUT)
        if resp.status_code >= 400:
            raise requests.HTTPError(
                f"{resp.status_code} {resp.reason} for {url}: {resp.text[:500]}",
                response=resp,
            )
        safe_agency = agency_name.lower().replace(" ", "_").replace("/", "_")
        return self._save_payload(
            payload=resp.json(),
            source_url=url,
            stem=f"usaspending_transactions_{safe_agency}_{start_date}_to_{end_date}",
            request_payload=payload,
        )

    def _save_payload(
        self,
        payload: dict[str, Any],
        source_url: str,
        stem: str,
        request_payload: Optional[dict[str, Any]] = None,
    ) -> tuple[Path, str]:
        ts = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        host = urlparse(source_url).netloc.replace(".", "_")
        out_path = self.base_dir / f"{stem}_{host}_{ts}.json"
        wrapped = {
            "fetched_at": datetime.now(timezone.utc).isoformat(),
            "source_url": source_url,
            "request_payload": request_payload,
            "data": payload,
        }
        out_path.write_text(json.dumps(wrapped, indent=2), encoding="utf-8")
        return out_path, source_url


def main() -> int:
    parser = argparse.ArgumentParser(description="Pull Treasury/USAspending public data.")
    parser.add_argument(
        "--out-dir",
        default=str(DEFAULT_OUT),
        help="Output directory for JSON artifacts.",
    )

    sub = parser.add_subparsers(dest="command", required=True)

    p_debt = sub.add_parser("debt", help="Pull Treasury debt_to_penny data.")
    p_debt.add_argument("--start-date", default=None, help="Optional YYYY-MM-DD lower bound.")
    p_debt.add_argument("--page-size", type=int, default=1, help="Page size (default 1 = latest row).")

    p_award = sub.add_parser("spending-award", help="Pull USAspending spending_by_award rows.")
    p_award.add_argument("--agency", required=True, help='Top-tier agency name (e.g. "Department of Defense").')
    p_award.add_argument("--tier", default="toptier", choices=["toptier", "subtier"])
    p_award.add_argument("--fiscal-year", type=int, default=2025)
    p_award.add_argument("--limit", type=int, default=100)

    p_tx = sub.add_parser(
        "spending-transaction",
        help="Pull USAspending spending_by_transaction rows.",
    )
    p_tx.add_argument("--agency", required=True, help='Top-tier agency name (e.g. "Department of Defense").')
    p_tx.add_argument("--tier", default="toptier", choices=["toptier", "subtier"])
    p_tx.add_argument("--start-date", required=True, help="YYYY-MM-DD")
    p_tx.add_argument("--end-date", required=True, help="YYYY-MM-DD")
    p_tx.add_argument("--limit", type=int, default=100)

    args = parser.parse_args()
    puller = FedDataPuller(base_dir=Path(args.out_dir))

    if args.command == "debt":
        path, url = puller.get_fiscal_debt(start_date=args.start_date, page_size=args.page_size)
    elif args.command == "spending-award":
        path, url = puller.search_spending_by_agency_award(
            agency_name=args.agency,
            tier=args.tier,
            fiscal_year=args.fiscal_year,
            limit=args.limit,
        )
    else:
        path, url = puller.search_spending_by_agency_transaction(
            agency_name=args.agency,
            tier=args.tier,
            start_date=args.start_date,
            end_date=args.end_date,
            limit=args.limit,
        )

    print(f"saved: {path}")
    print(f"source: {url}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
