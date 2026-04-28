#Requires -Version 5.1
<#
.SYNOPSIS
    Create a permanent Windows Scheduled Task for Romar Ticket Notifier.

.DESCRIPTION
    - Checks for saved credentials (run save-credentials.ps1 first!)
    - Creates a Scheduled Task that starts at logon
    - Task auto-restarts if it fails, runs hidden

.PARAMETER BaseUrl
    Override Romar base URL (optional, auto-read from .env.local)
#>
param(
    [string]$BaseUrl = ''
)

$ErrorActionPreference = 'Stop'

# Paths
$scriptPath = Join-Path $PSScriptRoot 'windows-ticket-notifier.ps1'
$credFile   = Join-Path $env:LOCALAPPDATA 'Romar\romar_notifier.cred.xml'
$envFile    = Join-Path $PSScriptRoot '..' '.env.local'
$taskName   = 'Romar Ticket Notifier'

# Validate notifier script exists
if (-not (Test-Path $scriptPath)) {
    throw "Notifier script not found: $scriptPath"
}

# Load base URL from .env.local if not provided
if (-not $BaseUrl -and (Test-Path $envFile)) {
    Get-Content $envFile | ForEach-Object {
        if ($_ -match '^BASE_URL=(.*)$') { $BaseUrl = $matches[1].Trim() }
    }
}
if (-not $BaseUrl) { $BaseUrl = 'http://localhost/Romar' }

# Check for saved credentials
if (-not (Test-Path $credFile)) {
    Write-Host "❌ Saved credentials not found!" -ForegroundColor Red
    Write-Host "   Run this first: .\save-credentials.ps1" -ForegroundColor Yellow
    exit 1
}

Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host "  Romar Ticket Notifier – Permanent Setup               " -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " Script:       $scriptPath"
Write-Host " Base URL:     $BaseUrl"
Write-Host " Credentials:  $credFile"
Write-Host ""

# Remove old task if exists
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Removing old scheduled task..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    Write-Host "   ✓ Old task removed"
}

# Build action: run notifier script with -UseSavedCreds
$action = New-ScheduledTaskAction `
    -Execute 'powershell.exe' `
    -Argument "-ExecutionPolicy Bypass -NoProfile -WindowStyle Hidden -File `"$scriptPath`" -UseSavedCreds -BaseUrl `'$BaseUrl`'"

# Trigger: at user logon
$trigger = New-ScheduledTaskTrigger -AtLogon

# Settings: hidden, restart if fails, run on batteries
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -Hidden

# Principal: run as current user (limited, no admin needed)
$principal = New-ScheduledTaskPrincipal `
    -UserId $env:USERNAME `
    -RunLevel Limited

# Register task
Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Principal $principal `
    -Force | Out-Null

Write-Host ""
Write-Host "✅ Scheduled Task '$taskName' created successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "Task Details:"
Write-Host "  Name:         $taskName"
Write-Host "  Trigger:      At logon ($env:USERNAME)"
Write-Host "  Action:       Run powershell.exe (hidden window)"
Write-Host "  Auto-restart: Yes"
Write-Host ""
Write-Host "📌 To start now:          Start-ScheduledTask -TaskName '$taskName'"
Write-Host "📌 To stop:               schtasks /end /tn '$taskName'"
Write-Host "📌 To remove:             schtasks /delete /tn '$taskName' /f"
Write-Host "📌 To view status:        Get-ScheduledTask -TaskName '$taskName'"
Write-Host ""

# Optional: start now for testing
$response = Read-Host "Start notifier now for testing? (y/n)"
if ($response -eq 'y') {
    Start-ScheduledTask -TaskName $taskName
    Write-Host "   ▶ Task started. Check Action Center in ~45 seconds." -ForegroundColor Cyan
}

