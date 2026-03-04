param(
    [string]$ProjectRoot = "C:\xampp\htdocs\Romar",
    [string]$PhpBin = "php"
)

$script = Join-Path $ProjectRoot "scripts\ops\db_backup.php"
$logDir = Join-Path $ProjectRoot "logs"
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir | Out-Null
}

Set-Location -Path $ProjectRoot

$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$logFile = Join-Path $logDir ("db-backup-" + $stamp + ".log")

& $PhpBin $script *> $logFile
exit $LASTEXITCODE
