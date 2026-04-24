#Requires -Version 5.1
<#
.SYNOPSIS
    Polls the Romar notifications API and fires Windows toast alerts.

.DESCRIPTION
    Uses BurntToast together with a short-lived web session to log in via
    auth/login.php, fetch api/getnotifications.php, and display every unread
    ticket notification. After showing a toast it calls api/marknotificationread.php
    so that the notification is not re-shown.

.PARAMETER BaseUrl
    Base URL of the Romar system (default http://192.168.2.99/Romar).

.PARAMETER PollIntervalSeconds
    Delay between polling cycles. Keeping this above ~30 seconds avoids stressing the server.

.PARAMETER Credential
    Windows credential object for the Romar account that will receive the notifications.

.PARAMETER MarkAllRead
    After showing each batch, mark everything as read (useful when the toast handler
    consumes many old notifications at once).

.PARAMETER Username
    Romar username to use when a credential object is not available.

.PARAMETER Password
    Plain-text Romar password (not recommended for shared machines; prefer -Credential).
#>
param (
    [Parameter()]
    [string]$BaseUrl = 'http://192.168.2.99/Romar',

    [Parameter()]
    [int]$PollIntervalSeconds = 45,

    [Parameter()]
    [string]$Username,

    [Parameter()]
    [System.Security.SecureString]$Password,

    [Parameter()]
    [System.Management.Automation.PSCredential]$Credential,

    [Parameter()]
    [switch]$MarkAllRead
)

Set-StrictMode -Version Latest

# ============================================================
# Helper: Ensure BurntToast is available
# ============================================================
function Test-BurntToastModule {
    if (-not (Get-Module -ListAvailable -Name BurntToast)) {
        Write-Host 'BurntToast module not found. Installing from PSGallery...' -ForegroundColor Yellow
        Install-Module -Name BurntToast -Scope CurrentUser -Force -AllowClobber
    }
    Import-Module BurntToast -ErrorAction Stop
}

# ============================================================
# Helper: Convert SecureString to plain text
# ============================================================
function ConvertTo-PlainPassword {
    param ([System.Security.SecureString]$SecureString)
    if (-not $SecureString) { return '' }
    return [System.Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureString)
    )
}

# ============================================================
# Helper: Extract CSRF token from login page
# ============================================================
function Get-CsrfFromLoginPage {
    param ([Microsoft.PowerShell.Commands.WebRequestSession]$Session)
    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" -WebSession $Session -UseBasicParsing
    $match = [regex]::Match($loginPage.Content, 'name="csrf_token"\s+value="([^"]+)"')
    if ($match.Success) {
        return $match.Groups[1].Value
    }
    throw 'Unable to find csrf_token on login page'
}

# ============================================================
# Core: Log in to Romar and return a session
# ============================================================
function Connect-ToRomar {
    param(
        [string]$UserName,
        [string]$PlainPassword
    )
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrfToken = Get-CsrfFromLoginPage -Session $session

    $loginBody = @{
        username   = $UserName
        password   = $PlainPassword
        csrf_token = $csrfToken
    }

    $response = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" `
        -Method Post -Body $loginBody -WebSession $session -UseBasicParsing

    # Validate login succeeded (check for redirect or absence of error)
    if ($response.Content -match 'Invalid username|Incorrect password|login.*error') {
        throw "Login failed for user '$UserName'. Check credentials."
    }

    Write-Host "Logged in as $UserName" -ForegroundColor Green
    return $session
}

# ============================================================
# Core: Fetch unread notifications from API
# ============================================================
function Get-RomarNotifications {
    param([Microsoft.PowerShell.Commands.WebRequestSession]$Session)

    $resp = Invoke-RestMethod -Uri "$BaseUrl/api/getnotifications.php" `
        -Method Get -WebSession $Session -ErrorAction Stop

    if ($resp -and $resp.notifications) {
        return $resp.notifications
    }
    return @()
}

# ============================================================
# Core: Mark a single notification as read
# ============================================================
function Set-NotificationRead {
    param(
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [int]$NotifId
    )
    $body = @{ notif_id = $NotifId } | ConvertTo-Json
    Invoke-RestMethod -Uri "$BaseUrl/api/marknotificationread.php" `
        -WebSession $Session -Method Post -Body $body `
        -ContentType 'application/json' -ErrorAction SilentlyContinue | Out-Null
}

# ============================================================
# Core: Mark all notifications as read
# ============================================================
function Set-AllNotificationsRead {
    param ([Microsoft.PowerShell.Commands.WebRequestSession]$Session)
    $body = @{ mark_all_read = $true } | ConvertTo-Json
    Invoke-RestMethod -Uri "$BaseUrl/api/marknotificationread.php" `
        -WebSession $Session -Method Post -Body $body `
        -ContentType 'application/json' -ErrorAction SilentlyContinue | Out-Null
}

# ============================================================
# Core: Display a Windows toast for a notification
# ============================================================
function Show-TicketToast {
    param ($Notification)

    $title = if ($Notification.ticket_number) {
        "$($Notification.ticket_number) - $($Notification.ticket_title)"
    } else {
        'Romar Ticket'
    }

    $lines = @($Notification.message)
    if ($Notification.ticket_status) {
        $lines += "Status: $($Notification.ticket_status)"
    }
    if ($Notification.ticket_priority) {
        $lines += "Priority: $($Notification.ticket_priority)"
    }

    New-BurntToastNotification -Text $title, ($lines | Where-Object { $_ }) -Silent
}

# ============================================================
# Determine credentials
# ============================================================
$finalUsername = $null
$plainPassword = $null

if ($Credential) {
    $finalUsername = $Credential.UserName
    $plainPassword = ConvertTo-PlainPassword -SecureString $Credential.Password
} else {
    if ($Username) {
        $finalUsername = $Username
    } else {
        $finalUsername = Read-Host -Prompt 'Romar username'
    }

    if ($Password) {
        $plainPassword = ConvertTo-PlainPassword -SecureString $Password
    } else {
        $secure = Read-Host -AsSecureString -Prompt 'Romar password'
        $plainPassword = ConvertTo-PlainPassword -SecureString $secure
    }
}

# ============================================================
# Main loop
# ============================================================
Test-BurntToastModule

$session = Connect-ToRomar -UserName $finalUsername -Password $plainPassword
$seenIds = New-Object 'System.Collections.Generic.HashSet[int]'

Write-Host "Starting notifier. Polling every ${PollIntervalSeconds}s. Press Ctrl+C to stop." -ForegroundColor Cyan

while ($true) {
    try {
        $notifications = Get-RomarNotifications -Session $session
    } catch {
        Write-Warning "Unable to refresh notifications ($_). Retrying login..."
        Start-Sleep -Seconds 5
        try {
            $session = Connect-ToRomar -UserName $finalUsername -Password $plainPassword
        } catch {
            Write-Error "Re-login failed: $_"
            Start-Sleep -Seconds 10
        }
        continue
    }

    $batch = $notifications |
        Where-Object { -not $seenIds.Contains($_.notif_id) -and (-not $_.is_read) }

    if ($batch) {
        foreach ($notif in $batch) {
            Show-TicketToast -Notification $notif
            Set-NotificationRead -Session $session -NotifId $notif.notif_id
            $seenIds.Add($notif.notif_id) | Out-Null
        }

        if ($MarkAllRead) {
            Set-AllNotificationsRead -Session $session
        }
    }

    Start-Sleep -Seconds $PollIntervalSeconds
}

