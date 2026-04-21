 <#
.SYNOPSIS
Polls the Romar notifications API and fires Windows toast alerts.

.DESCRIPTION
Uses BurntToast together with a short-lived web session to log in via
`auth/login.php`, fetch `api/getnotifications.php`, and display every unread
ticket notification. After showing a toast it calls `api/marknotificationread.php`
so that the notification is not re-shown.

.PARAMETER BaseUrl
Base URL of the Romar system (default `http://192.168.2.99/Romar`).
.PARAMETER PollIntervalSeconds
Delay between polling cycles. Keeping this above ~30 seconds avoids stressing the server.
.PARAMETER Credential
Windows credential object for the Romar account that will receive the notifications.
.PARAMETER MarkAllRead
After showing each batch, mark everything as read (this is useful when the toast handler
consumes many old notifications at once).
.PARAMETER Username
Romar username to use when a credential object is not available.
.PARAMETER Password
Plain-text Romar password (not recommended for shared machines; prefer `-Credential`).
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

function Test-BurntToastModule {
    if (-not (Get-Module -ListAvailable -Name BurntToast)) {
        Write-Host 'BurntToast module not found. Installing from PSGallery...' -ForegroundColor Yellow
        Install-Module -Name BurntToast -Scope CurrentUser -Force -AllowClobber
    }

    Import-Module BurntToast -ErrorAction Stop
}

function ConvertTo-PlainPassword {
    param ([System.Security.SecureString]$SecureString)
    if (-not $SecureString) {
        return ''
    }

    return [System.Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureString)
    )
}

function Get-CsrfFromLoginPage {
    param ([Microsoft.PowerShell.Commands.WebRequestSession]$Session)

    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" -WebSession $Session -UseBasicParsing
    $match = [regex]::Match($loginPage.Content, 'name="csrf_token"\s+value="([^"]+)"')
    if ($match.Success) {
        return $match.Groups[1].Value
    }

    throw 'Unable to find csrf_token on login page.'
}

function Connect-ToRomar {
    param (
        [string]$UserName,
        [System.Security.SecureString]$Password
    )
    if ($Password) {
        $PlainPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($Password))
    } else {
        $PlainPassword = ''
    }
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = Get-CsrfFromLoginPage -Session $session

    $body = @{
        username   = $UserName
        password   = $PlainPassword
        csrf_token = $csrf
    }

    try {
        $response = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" -Method Post -Body $body -WebSession $session -UseBasicParsing -ErrorAction Stop
    } catch {
        throw "Login failed: $_"
    }

    $finalPath = $response.BaseResponse.ResponseUri.AbsolutePath
    if ($finalPath -match 'auth/login\.php') {
        throw 'Login failed: invalid credentials or CSRF token.'
    }

    Write-Host "Logged in as $UserName" -ForegroundColor Green
    return $session
}

function Get-RomarNotifications {
    param ([Microsoft.PowerShell.Commands.WebRequestSession]$Session)

    $data = Invoke-RestMethod -Uri "$BaseUrl/api/getnotifications.php" -WebSession $Session -Method Get -ErrorAction Stop
    if (-not $data.notifications) {
        return @()
    }

    return $data.notifications
}

function Set-NotificationRead {
    param (
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [int]$NotifId
    )

    $body = @{ notif_id = $NotifId } | ConvertTo-Json
    Invoke-RestMethod -Uri "$BaseUrl/api/marknotificationread.php" -WebSession $Session -Method Post -Body $body -ContentType 'application/json' -ErrorAction SilentlyContinue
}

function Set-AllNotificationsRead {
    param ([Microsoft.PowerShell.Commands.WebRequestSession]$Session)

    $body = @{ mark_all_read = $true } | ConvertTo-Json
    Invoke-RestMethod -Uri "$BaseUrl/api/marknotificationread.php" -WebSession $Session -Method Post -Body $body -ContentType 'application/json' -ErrorAction SilentlyContinue
}

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

Test-BurntToastModule

$session = Connect-ToRomar -UserName $finalUsername -Password $Password
$seenIds = New-Object 'System.Collections.Generic.HashSet[int]'

while ($true) {
    try {
        $notifications = Get-RomarNotifications -Session $session
    } catch {
        Write-Warning "Unable to refresh notifications ($_). Retrying login..."
        Start-Sleep -Seconds 5
        $session = Connect-ToRomar -UserName $finalUsername -Password $Password
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
