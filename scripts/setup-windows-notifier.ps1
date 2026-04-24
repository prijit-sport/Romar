#Requires -Version 5.1
<#
.SYNOPSIS
    One-click setup for Romar Windows Ticket Notifier.

.DESCRIPTION
    - Checks / installs BurntToast module
    - Verifies API connectivity
    - Creates Windows Scheduled Task to run notifier at logon
    - Tests a sample toast notification

.PARAMETER BaseUrl
    Romar base URL (default: http://localhost/Romar)

.PARAMETER Username
    Romar admin/IT username for the notifier

.PARAMETER Password
    Romar password (plain text; for interactive use prefer prompt)

.PARAMETER PollInterval
    Seconds between polls (default: 45)

.PARAMETER SkipTask
    Skip creating the Scheduled Task (just test BurntToast + API)
#>
param(
    [string]$BaseUrl = 'http://localhost/Romar',
    [string]$Username = '',
    [string]$Password = '',
    [int]$PollInterval = 45,
    [switch]$SkipTask
)

$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host "`n[+] $Message" -ForegroundColor Cyan
}

function Write-Ok {
    param([string]$Message)
    Write-Host "    OK: $Message" -ForegroundColor Green
}

function Write-Warn {
    param([string]$Message)
    Write-Host "    WARN: $Message" -ForegroundColor Yellow
}

function Write-Err {
    param([string]$Message)
    Write-Host "    FAIL: $Message" -ForegroundColor Red
}

# ============================================================
# 1. BurntToast check / install
# ============================================================
Write-Step 'Checking BurntToast module...'
try {
    if (-not (Get-Module -ListAvailable -Name BurntToast)) {
        Write-Warn 'BurntToast not found. Installing...'
        Install-Module -Name BurntToast -Scope CurrentUser -Force -AllowClobber
        Write-Ok 'BurntToast installed'
    } else {
        Write-Ok 'BurntToast already installed'
    }
    Import-Module BurntToast -ErrorAction Stop
    Write-Ok 'BurntToast imported'
} catch {
    Write-Err "BurntToast setup failed: $_"
    exit 1
}

# ============================================================
# 2. Test toast
# ============================================================
Write-Step 'Testing Windows toast notification...'
try {
    New-BurntToastNotification -Text 'Romar Setup', 'Toast test successful! You will see ticket alerts like this.' -Silent
    Write-Ok 'Toast displayed. Check Action Center / bottom-right corner.'
} catch {
    Write-Err "Toast failed: $_"
    exit 1
}

# ============================================================
# 3. API connectivity test
# ============================================================
Write-Step "Testing API connectivity to $BaseUrl ..."
try {
    $resp = Invoke-RestMethod -Uri "$BaseUrl/api/getnotifications.php" -Method Get -SessionVariable sess -ErrorAction Stop
    if ($resp.notifications -is [array]) {
        Write-Ok "API reachable. Unread count: $($resp.unread_count)"
    } else {
        Write-Warn 'API returned unexpected format (maybe not logged in). This is OK; notifier will log in.'
    }
} catch {
    Write-Warn "API probe failed (not logged in or server unreachable): $_"
}

# ============================================================
# 4. Prompt for credentials if not provided
# ============================================================
if (-not $Username) {
    $Username = Read-Host -Prompt 'Enter Romar username for notifier'
}
if (-not $Password) {
    $secure = Read-Host -AsSecureString -Prompt 'Enter Romar password'
    $Password = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    )
}

# ============================================================
# 5. Create Scheduled Task
# ============================================================
if (-not $SkipTask) {
    Write-Step 'Creating Windows Scheduled Task...'
    $taskName = 'Romar Ticket Notifier'
    $scriptPath = Join-Path $PSScriptRoot 'windows-ticket-notifier.ps1'

    if (-not (Test-Path $scriptPath)) {
        Write-Err "Notifier script not found at: $scriptPath"
        exit 1
    }

    # Remove old task if exists
    $existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    if ($existing) {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
        Write-Ok 'Removed old scheduled task'
    }

    $action = New-ScheduledTaskAction `
        -Execute 'powershell.exe' `
        -Argument "-ExecutionPolicy Bypass -NoProfile -WindowStyle Hidden -File `"$scriptPath`" -BaseUrl `'$BaseUrl`' -Username `'$Username`' -Password `'$Password`' -PollIntervalSeconds $PollInterval"

    $trigger = New-ScheduledTaskTrigger -AtLogon
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
    $principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -RunLevel Limited

    try {
        Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
        Write-Ok "Scheduled task '$taskName' created. It will start at next logon."
    } catch {
        Write-Err "Failed to create scheduled task: $_"
        exit 1
    }

    # Start now for testing
    Write-Step 'Starting notifier now for a quick test...'
    Start-ScheduledTask -TaskName $taskName
    Start-Sleep -Seconds 3
    $task = Get-ScheduledTask -TaskName $taskName
    Write-Ok "Task state: $($task.State). Check Action Center in ~$PollInterval seconds for ticket alerts."
} else {
    Write-Step 'Skipped scheduled task creation (-SkipTask).'
}

# ============================================================
# 6. Summary
# ============================================================
Write-Host "`n========================================" -ForegroundColor Green
Write-Host "  Romar Windows Notifier Setup Complete" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host " Base URL:     $BaseUrl"
Write-Host " Username:     $Username"
Write-Host " Poll Interval: ${PollInterval}s"
Write-Host "`n Next steps:"
Write-Host "  1. Ensure Windows Settings > Notifications allows PowerShell / BurntToast."
Write-Host "  2. Create a test ticket in Romar; you should see a toast within ${PollInterval}s."
Write-Host "  3. To stop:  schtasks /end /tn 'Romar Ticket Notifier'"
Write-Host "  4. To remove: schtasks /delete /tn 'Romar Ticket Notifier' /f"
Write-Host "========================================`n"

