# Reasoner Async API

This backend exposes two endpoints for asynchronous `step_level_reasoner.py` runs:

- `POST /api/reasoner/run`
- `GET /api/reasoner/jobs/<task_id>`

## Install

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r /Users/josephocasio/Documents/New\ project/backend/requirements.txt
```

## Run API

```bash
cd "/Users/josephocasio/Documents/New project"
python3 backend/run.py
```

## Run Worker

```bash
cd "/Users/josephocasio/Documents/New project"
celery -A backend.tasks.celery worker --loglevel=info
```

## Example

```bash
curl -X POST http://localhost:8000/api/reasoner/run \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <jwt>" \
  -d '{"institutional_operational":true}'
```

## RBAC / JWT

By default RBAC is enabled.

Required token claims:
- `sub` (or `user_id`)
- `exp`
- `iat`
- `roles` (array) or `role` (string)

Role gates:
- `POST /api/reasoner/run`: `admin` or `auditor` or `operator`
- `GET /api/reasoner/jobs/<id>`: `admin` or `auditor` or `operator` or `viewer` or `signer`
- `sign=true` on run endpoint: must include `admin` or `signer`

Config env vars:
- `RBAC_ENFORCE=true|false` (default: `true`)
- `JWT_SECRET=<shared-secret>`
- `JWT_ALGORITHM=HS256` (default)
- `JWT_AUDIENCE=<optional>`
- `JWT_ISSUER=<optional>`
