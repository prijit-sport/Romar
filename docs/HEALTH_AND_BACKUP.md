# Health + DB Backup Operations

## 1) Secured Health Endpoint (Apache/PHP/DB)

Endpoint:
- `GET /Romar/health/db.php`

Auth (required):
- Header: `X-Health-Token: <ROMAR_HEALTH_TOKEN>`
- Optional query fallback: `?token=<ROMAR_HEALTH_TOKEN>`

Responses:
- `200` when checks are healthy
- `503` when any critical check fails
- `401` when token is missing/invalid

Example (PowerShell):
```powershell
Invoke-RestMethod -Uri "http://127.0.0.1/Romar/health/db.php" -Headers @{"X-Health-Token"="change_this_health_token"}
```

## 2) Manual DB Backup

Command:
```powershell
php scripts/ops/db_backup.php
```

Output:
- JSON including `file`, `size_bytes`, `sha256`

## 3) Manual DB Restore

Command:
```powershell
php scripts/ops/db_restore.php --file="database/backups/romar_dormitory_YYYYmmdd_HHMMSS.sql" --yes
```

Optional full reset before import:
```powershell
php scripts/ops/db_restore.php --file="database/backups/romar_dormitory_YYYYmmdd_HHMMSS.sql" --yes --drop-existing
```

## 4) Daily Automatic Backup (Windows Task Scheduler)

Create task:
```powershell
powershell -ExecutionPolicy Bypass -File scripts/ops/install_daily_backup_task.ps1 -ProjectRoot "C:\xampp\htdocs\Romar" -PhpBin "php" -StartTime "01:30"
```

Run script used by task:
- `scripts/ops/run_daily_backup.ps1`
- Logs to `logs/db-backup-YYYYmmdd_HHMMSS.log`

## 5) Required Env Keys

- `ROMAR_HEALTH_TOKEN`
- `ROMAR_DB_BACKUP_DIR`
- `ROMAR_DB_BACKUP_RETENTION_DAYS`
- `ROMAR_MYSQL_BIN`
- `ROMAR_MYSQLDUMP_BIN`
