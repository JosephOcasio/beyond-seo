# AURA Governance Hub

Public-safe frontend plus private internal audit console for `runGovernanceAudit`.

## Access Model

- Public route: `/`
- Internal route: `/internal` (disabled in production by default)
- Internal console is enabled only when:
  - local dev (`npm run dev`), or
  - `VITE_ENABLE_INTERNAL_CONSOLE=true` at build time
  - if disabled at build time, internal console code is excluded from the production bundle

## Security Defaults

- Public page is high-level only (no proprietary method details).
- Internal access key is held in memory only in the browser (not persisted to storage).
- Backend key hash supports env override:
  - `INTERNAL_ACCESS_KEY_HASH` (preferred)
  - falls back to default hash in source if env not set
- Generated artifacts and local corpus data are git-ignored.

## Development

```bash
npm install
npm run dev
```

Open:
- Public: `http://localhost:5173/`
- Internal: `http://localhost:5173/internal`

## Base44

```bash
base44 entities push
base44 deploy
```

If you rotate the default fallback key hash:

```bash
python3 tools/rotate_internal_access_key.py --key "NEW_KEY"
```
