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

- `ROMAR_APP_ENV=prod`
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

## 3) GitHub Actions Secrets (for full test suite)
For quality-gates.yml full pass, add Secrets in repo Settings > Secrets and variables > Actions:

**Optional** - Add for full DB tests:
```
ROMAR_TEST_DB_HOST = your_test_db_host
ROMAR_TEST_DB_USER = test_user
ROMAR_TEST_DB_PASS = test_pass
ROMAR_TEST_DB_NAME = romar_test
```
Lint/smoke pass without secrets. DB tests continue-on-error.

## 4) Security Log Monitoring
Monitoring options:

- CI schedule: `.github/workflows/security-monitor.yml`
- Local/cron command:

```powershell
php tests/security_alert_check.php
```

Examples:

- Strict mode (non-zero exit on alert): set `ROMAR_ALERT_FAIL=1`
- JSON output: set `ROMAR_ALERT_OUTPUT_JSON=security-alert-report.json`
- Webhook notification: set `ROMAR_ALERT_WEBHOOK_URL`
- Incident owner: set `ROMAR_INCIDENT_OWNER`

Override config file path if needed:

```powershell
$env:ROMAR_ALERT_CONFIG_FILE='C:\path\custom-alerts.env'
php tests/security_alert_check.php
```
