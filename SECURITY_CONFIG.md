# Security Configuration Guide

## Environment Variables

### Required Variables (All Environments)

```bash
# Application
APP_ENV=prod                          # dev, test, prod
APP_DEBUG=false                       # MUST be false in production
```

### Database Credentials (Production)

Inject these via environment at deployment time. **Do NOT commit actual values to .env file.**

```bash
DB_PASSWORD="<secure_password>"
DB_USER="<db_admin_user>"
DB_HOST="<database_host>"
DB_NAME="<database_name>"
```

### API Credentials (Monolith Integration)

```bash
API_ARGUS_MONOLITH_USER="<monolith_api_user>"
API_ARGUS_MONOLITH_PASSWORD="<monolith_api_password>"
```

## Secure .env Template

See [app/.env](app/.env) for the template with placeholders. Example:

```dotenv
APP_ENV=prod
APP_DEBUG=false

# Use environment variable placeholders - do NOT commit actual values
DB_DEFAULT_CONNECTION_PASSWORD="%DB_PASSWORD%"
DB_DEFAULT_CONNECTION_USER="%DB_USER%"
DB_DEFAULT_CONNECTION_HOST="%DB_HOST%"
```

## CI/CD Environment Setup

### GitHub Actions Secrets

Store sensitive values as repository secrets:

1. Go to **Settings > Secrets and variables > Actions**
2. Add:
   - `DB_PASSWORD` 
   - `DB_USER`
   - `API_ARGUS_MONOLITH_USER`
   - `API_ARGUS_MONOLITH_PASSWORD`

Use in workflows:
```yaml
env:
  DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
  DB_USER: ${{ secrets.DB_USER }}
```

### Docker/Kubernetes Environment

Inject at runtime:
```bash
docker run \
  -e APP_ENV=prod \
  -e APP_DEBUG=false \
  -e DB_PASSWORD="$DB_PASSWORD" \
  -e DB_USER="$DB_USER" \
  beyond-seo:latest
```

## Security Validation

### Automated Checks

The CI pipeline (`security-validation.yml`) enforces:

1. ✅ **No hardcoded credentials** in source files or .env
2. ✅ **APP_DEBUG=false** in production
3. ✅ **No exposed secrets** (TruffleHog scanning)
4. ✅ **Valid PHP syntax** across codebase

### Manual Validation

Run locally before committing:

```bash
# Check for hardcoded passwords
grep -r 'password=' app/src --include="*.php" | grep -v 'getEnv\|Config::'

# Verify .env has placeholders only
grep -E 'PASSWORD|TOKEN|SECRET' .env | grep -v '%'

# Validate PHP syntax
find app/src -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Log Redaction

The `LogRedactionMiddleware` automatically redacts sensitive data from logs:

- **Headers:** Authorization, X-API-Key, X-Auth-Token, Cookie
- **Form Fields:** password, token, credential, secret, api_key, auth
- **Patterns:** Bearer tokens, JSON credential assignments

### Usage

Register middleware in Symfony config:

```yaml
# app/config/services.yml
services:
  App\Infrastructure\Middleware\LogRedactionMiddleware:
    tags:
      - { name: http_middleware, priority: 100 }
```

## Credential Rotation

### Annual Tasks

1. **Rotate API credentials:**
   ```bash
   # Generate new API_ARGUS_MONOLITH_PASSWORD
   # Update in secrets manager / CI/CD
   # Notify API provider
   ```

2. **Rotate database password:**
   ```bash
   # Update in MySQL/PostgreSQL
   # Update environment secrets
   # Redeploy services
   ```

3. **Review access logs:**
   - Check for unauthorized access attempts
   - Monitor failed login attempts
   - Verify legitimate API calls

## Audit Trail

- **Last Security Audit:** 2026-02-08
- **Framework:** Beyond-SEO with Symfony integration
- **GitHub Workflows:** 
  - `.github/workflows/security-validation.yml` - Credential & environment checks
  - `.github/workflows/static-analysis.yml` - Code quality & syntax validation

## Questions?

Refer to [SECURITY_AUDIT_20260208.md](SECURITY_AUDIT_20260208.md) for detailed audit findings.
