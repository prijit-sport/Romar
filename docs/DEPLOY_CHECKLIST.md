# Deploy Checklist

## 1) Alert Config in Repository (No GitHub Secrets)
This project now reads alert settings from:

- `config/alerts.internal.env`
- `config/e2e.internal.env`

Update values there for:

- `ROMAR_ALERT_WINDOW_MIN`
- `ROMAR_ALERT_RATE_LIMIT`
- `ROMAR_ALERT_ACCESS_DENIED`
- `ROMAR_ALERT_LOGIN_FAILED`
- `ROMAR_ALERT_FAIL`
- `ROMAR_ALERT_CHANNEL`
- `ROMAR_ALERT_WEBHOOK_URL`
- `ROMAR_ALERT_EMAIL_TO`
- `ROMAR_INCIDENT_OWNER`
- `ROMAR_LOG_ARCHIVE_RETENTION_DAYS`
- `ROMAR_E2E_ENABLE`
- `ROMAR_TEST_DB_HOST`
- `ROMAR_TEST_DB_USER`
- `ROMAR_TEST_DB_PASS`
- `ROMAR_TEST_DB_NAME`

## 2) Server Environment Setup
Use `.env.production.example` as baseline and set:

- `ROMAR_APP_ENV=production`
- `ROMAR_APP_DEBUG=0`
- `ROMAR_DB_HOST`
- `ROMAR_DB_USER`
- `ROMAR_DB_PASS`
- `ROMAR_DB_NAME`

Optional retention overrides:

- `ROMAR_LOG_MAX_BYTES`
- `ROMAR_LOG_MAX_FILES`
- `ROMAR_LOG_ARCHIVE_RETENTION_DAYS`

Run preflight:

```powershell
php scripts/ops/deploy_preflight.php
```

## 3) GitHub Actions Secrets (optional for full tests)
Add Secrets for DB tests:

```
ROMAR_TEST_DB_HOST
ROMAR_TEST_DB_USER
ROMAR_TEST_DB_PASS
ROMAR_TEST_DB_NAME
```

Lint/pre flight pass without.

## 4) Automated GitHub Deploy
`.github/workflows/deploy.yml` on main push:

- Runs preflight
- Creates tagged release (v1, v2...)
- Placeholder for server deploy

Push to main auto-deploys release. Enable branch protection for quality-gates.

## 5) Security Log Monitoring
- CI: `.github/workflows/security-monitor.yml`
- Local: `php tests/security_alert_check.php`

