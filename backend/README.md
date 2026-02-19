# Reasoner Async API + Prototype Pipeline

This backend exposes three surfaces:

1. Existing async reasoner endpoints:
- `POST /api/reasoner/run`
- `GET /api/reasoner/jobs/<task_id>`

2. Deterministic prototype endpoints:
- `GET /api/prototype/health`
- `POST /api/prototype/claims/evaluate`
- `GET /api/prototype/claims/<claim_id>`
- `GET /api/prototype/reports/<claim_id>`

3. Local JRZ meta interface:
- `GET /jrz`
- `GET /api/jrz/capabilities?surface=personal|public`
- `POST /api/jrz/ask`
- `GET /api/jrz/jobs/<task_id>`

## Install

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r backend/requirements.txt
```

## Run API

```bash
python3 backend/run.py
```

## Run Worker

```bash
celery -A backend.tasks.celery worker --loglevel=info
```

## Deterministic Pipeline

Execution order:
`Input -> Measurement -> Admissibility -> Drift Audit -> Governance -> Ledger`

Tables:
- `claims`
- `constraint_scores`
- `ledger_events` (append-only)

Default DB:
- `sqlite:///out/us_audit/prototype.db`

For PostgreSQL, set `DATABASE_URL`.

## Alembic Migrations

Migration files:
- `backend/alembic/versions/20260218_01_create_prototype_tables.py`
- `backend/alembic/versions/20260218_02_harden_constraints.py`

Run migrations:

```bash
alembic -c backend/alembic.ini upgrade head
```

If tables were previously created by app auto-init (without Alembic), stamp first:

```bash
alembic -c backend/alembic.ini stamp 20260218_01
alembic -c backend/alembic.ini upgrade head
```

If you manage schema only through Alembic, disable app auto-create:

```bash
export AUTO_INIT_DB=false
```

## API Examples

Text claim:

```bash
curl -X POST http://localhost:8000/api/prototype/claims/evaluate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <jwt>" \
  -d '{
    "source_name": "constraint-first-systems-v1",
    "source_type": "text",
    "raw_text": "E = mc^2\n\\int_a^b f(x) dx",
    "metadata": {"tag": "manual-seed"},
    "min_admissibility": 0.55,
    "drift_tolerance": 0.20
  }'
```

OCR claim:

```bash
curl -X POST http://localhost:8000/api/prototype/claims/evaluate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <jwt>" \
  -d '{
    "source_name": "whitepaper-scan-01",
    "source_type": "image",
    "file_path": "/Users/josephocasio/Documents/New project/inbox/ocr_watch/proof.png",
    "metadata": {"source": "watch-folder"}
  }'
```

## Tests

Run tests:

```bash
python3 -m pytest backend/tests -q
```

Coverage includes:
- health endpoint
- text claim evaluate/get flow
- input validation failures
- OCR missing-file error path
- RBAC block when token is missing

## RBAC / JWT

RBAC is enabled by default.

Quick local setup:

```bash
export RBAC_ENFORCE=true
export JWT_SECRET="$(python3 -c 'import secrets; print(secrets.token_urlsafe(32))')"
export JWT_ALGORITHM=HS256
```

Required token claims:
- `sub` (or `user_id`)
- `exp`
- `iat`
- `roles` (array) or `role` (string)

## CI

GitHub Actions workflow:
- `.github/workflows/backend-prototype-ci.yml`

Runs on push/PR for backend changes and executes:
1. dependency install
2. static compile check
3. Alembic `upgrade head` + `current`
4. `pytest backend/tests -q`
