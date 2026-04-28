#Requires -Version 5.1
<#
.SYNOPSIS
    One-time credential saver for Romar Windows Notifier.

.DESCRIPTION
    Securely saves Romar credentials using Windows DPAPI encryption.
    Run this script ONCE after deployment; the notifier will auto-load
    the credentials afterward.

.PARAMETER BaseUrl
    Romar base URL (default: http://localhost/Romar)
#>
param(
    [string]$BaseUrl = 'http://localhost/Romar'
)

$ErrorActionPreference = 'Stop'

# Path for encrypted credential file
$credDir  = Join-Path $env:LOCALAPPDATA 'Romar'
$credFile = Join-Path $credDir 'romar_notifier.cred.xml'
$envFile  = Join-Path $PSScriptRoot '..' '.env.local'

# Ensure directory exists
if (-not (Test-Path $credDir)) {
    New-Item -ItemType Directory -Path $credDir -Force | Out-Null
}

# Prompt for credentials securely
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Romar Notifier – Save Credentials    " -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "This will securely encrypt and store your"
Write-Host "Romar password using Windows DPAPI."
Write-Host ""

$secureCred = Get-Credential -Message 'Enter Romar notifier account'
if (-not $secureCred -or [string]::IsNullOrWhiteSpace($secureCred.UserName)) {
    Write-Error 'No credentials provided. Aborting.'
    exit 1
}

# Export credential as encrypted XML
$secureCred | Export-Clixml -Path $credFile -Force
Write-Host ""
Write-Host "✅ Credentials saved securely to:" -ForegroundColor Green
Write-Host "   $credFile"
Write-Host ""

# Write .env.local for non-secret config
@"
# Romar Notifier Configuration (auto-generated)
BASE_URL=$BaseUrl
POLL_INTERVAL=45
"@ | Set-Content -Path $envFile -Encoding UTF8 -Force

Write-Host "✅ Config saved to:" -ForegroundColor Green
Write-Host "   $envFile"
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  1. Run: .\setup-permanent-task.ps1    (to create auto-start task)"
Write-Host "  2. Or run: .\windows-ticket-notifier.ps1  (manual test)"
Write-Host ""

