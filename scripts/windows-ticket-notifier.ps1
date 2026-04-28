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
    Base URL of the Romar system (default http://localhost/Romar).

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

.PARAMETER LogPath
    Path to log file for diagnostic output.

.PARAMETER VerboseOutput
    Enable verbose console output.
#>
param (
    [Parameter()]
    [string]$BaseUrl = 'http://localhost/Romar',

    [Parameter()]
    [int]$PollIntervalSeconds = 45,

    [Parameter()]
    [string]$Username,

    [Parameter()]
    [System.Security.SecureString]$Password,

    [Parameter()]
    [System.Management.Automation.PSCredential]$Credential,

    [Parameter()]
    [switch]$MarkAllRead,

    [Parameter()]
    [string]$LogPath = "$env:TEMP\romar-notifier.log",

    [Parameter()]
    [switch]$VerboseOutput,

    [Parameter()]
    [switch]$UseSavedCreds
)

Set-StrictMode -Version Latest

# ============================================================
# Helper: Write to log file
# ============================================================
function Write-Log {
    param(
        [string]$Message,
        [ValidateSet('INFO', 'WARN', 'ERROR', 'DEBUG')]
        [string]$Level = 'INFO'
    )
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $logLine = "[$timestamp] [$Level] $Message"
    
    try {
        Add-Content -Path $LogPath -Value $logLine -ErrorAction SilentlyContinue
    } catch {
        # If logging fails, continue silently
    }
    
    if ($VerboseOutput -or $Level -eq 'ERROR') {
        $colorMap = @{ 'INFO' = 'White'; 'WARN' = 'Yellow'; 'ERROR' = 'Red'; 'DEBUG' = 'Cyan' }
        Write-Host $logLine -ForegroundColor $colorMap[$Level]
    }
}

# ============================================================
# Helper: Ensure BurntToast is available
# ============================================================
function Test-BurntToastModule {
    if (-not (Get-Module -ListAvailable -Name BurntToast)) {
        Write-Log -Message 'BurntToast module not found. Installing from PSGallery...' -Level 'WARN'
        try {
            Install-Module -Name BurntToast -Scope CurrentUser -Force -AllowClobber -ErrorAction Stop
            Write-Log -Message 'BurntToast installed successfully' -Level 'INFO'
        } catch {
            Write-Log -Message "Failed to install BurntToast: $_" -Level 'ERROR'
            throw "BurntToast module is required but could not be installed. Error: $_"
        }
    }
    Import-Module BurntToast -ErrorAction Stop
    Write-Log -Message 'BurntToast module loaded' -Level 'DEBUG'
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
    
    Write-Log -Message 'Fetching CSRF token from login page...' -Level 'DEBUG'
    
    try {
        $loginPage = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" `
            -WebSession $Session -UseBasicParsing -ErrorAction Stop
        
        Write-Log -Message "Login page status: $($loginPage.StatusCode)" -Level 'DEBUG'
        
        $match = [regex]::Match($loginPage.Content, 'name="csrf_token"\s+value="([^"]+)"')
        if ($match.Success) {
            $token = $match.Groups[1].Value
            Write-Log -Message "CSRF token extracted: $token" -Level 'DEBUG'
            return $token
        }
        
        # Try alternative pattern (single quotes)
        $match = [regex]::Match($loginPage.Content, "name='csrf_token'\s+value='([^']+)'")
        if ($match.Success) {
            $token = $match.Groups[1].Value
            Write-Log -Message "CSRF token extracted (alt pattern): $token" -Level 'DEBUG'
            return $token
        }
        
        Write-Log -Message "Login page content snippet: $($loginPage.Content.Substring(0, [Math]::Min(500, $loginPage.Content.Length)))" -Level 'DEBUG'
        throw 'Unable to find csrf_token on login page'
    } catch {
        Write-Log -Message "Failed to get CSRF token: $_" -Level 'ERROR'
        throw
    }
}

# ============================================================
# Core: Log in to Romar and return a session
# ============================================================
function Connect-ToRomar {
    [Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidUsingPlainTextForPassword', '')]
    param(
        [string]$UserName,
        [string]$PlainPassword
    )
    
    Write-Log -Message "Attempting login for user: $UserName" -Level 'INFO'
    
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    
    # Set cookie policy to accept all cookies
    $session.Cookies = New-Object System.Net.CookieContainer
    
    try {
        $csrfToken = Get-CsrfFromLoginPage -Session $session
        
        $loginBody = @{
            username   = $UserName
            password   = $PlainPassword
            csrf_token = $csrfToken
        }
        
        Write-Log -Message "Sending login request..." -Level 'DEBUG'
        
        $response = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" `
            -Method Post -Body $loginBody -WebSession $session -UseBasicParsing `
            -MaximumRedirection 0 -ErrorAction SilentlyContinue
        
        # Check for redirect (successful login)
        $isRedirect = ($response.StatusCode -eq 302) -or ($response.StatusCode -eq 301)
        $hasLocation = $null -ne $response.Headers['Location']
        
        Write-Log -Message "Login response status: $($response.StatusCode), Redirect: $isRedirect, Location: $($response.Headers['Location'])" -Level 'DEBUG'
        
        # Validate login succeeded
        if ($response.Content -match 'Invalid username|Incorrect password|login.*error|authentication failed') {
            Write-Log -Message "Login failed for user '$UserName' - invalid credentials detected in response" -Level 'ERROR'
            throw "Login failed for user '$UserName'. Check credentials."
        }
        
        # Also check if we got redirected to dashboard (success) or back to login (failure)
        if ($isRedirect -and $hasLocation) {
            $location = $response.Headers['Location']
            if ($location -match 'login\.php') {
                Write-Log -Message "Login failed - redirected back to login page" -Level 'ERROR'
                throw "Login failed for user '$UserName'. Redirected back to login."
            }
            Write-Log -Message "Login successful - redirected to: $location" -Level 'INFO'
        } elseif ($response.Content -match 'dashboard|logout|welcome') {
            Write-Log -Message "Login successful - detected dashboard content" -Level 'INFO'
        } else {
            Write-Log -Message "Login response content snippet: $($response.Content.Substring(0, [Math]::Min(300, $response.Content.Length)))" -Level 'DEBUG'
        }
        
        # Verify session by checking cookies
        $cookieCount = $session.Cookies.Count
        Write-Log -Message "Session established with $cookieCount cookie(s)" -Level 'INFO'
        
        return $session
    } catch {
        Write-Log -Message "Login error: $_" -Level 'ERROR'
        throw
    }
}

# ============================================================
# Core: Fetch unread notifications from API
# ============================================================
function Get-RomarNotifications {
    param([Microsoft.PowerShell.Commands.WebRequestSession]$Session)
    
    Write-Log -Message 'Fetching notifications from API...' -Level 'DEBUG'
    
    try {
        $resp = Invoke-RestMethod -Uri "$BaseUrl/api/getnotifications.php" `
            -Method Get -WebSession $Session -ErrorAction Stop
        
        Write-Log -Message "API Response: unread_count=$($resp.unread_count), notifications_count=$($resp.notifications.Count)" -Level 'DEBUG'
        
        if ($resp -and $resp.notifications) {
            return $resp.notifications
        }
        
        Write-Log -Message 'API returned empty or invalid response structure' -Level 'WARN'
        return @()
    } catch {
        Write-Log -Message "API request failed: $_" -Level 'ERROR'
        throw
    }
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
    
    try {
        Invoke-RestMethod -Uri "$BaseUrl/api/marknotificationread.php" `
            -WebSession $Session -Method Post -Body $body `
            -ContentType 'application/json' -ErrorAction SilentlyContinue | Out-Null
        Write-Log -Message "Marked notification $NotifId as read" -Level 'DEBUG'
    } catch {
        Write-Log -Message "Failed to mark notification $NotifId as read: $_" -Level 'WARN'
    }
}

# ============================================================
# Core: Mark all notifications as read
# ============================================================
function Set-AllNotificationsRead {
    param ([Microsoft.PowerShell.Commands.WebRequestSession]$Session)
    
    $body = @{ mark_all_read = $true } | ConvertTo-Json
    
    try {
        Invoke-RestMethod -Uri "$BaseUrl/api/marknotificationread.php" `
            -WebSession $Session -Method Post -Body $body `
            -ContentType 'application/json' -ErrorAction SilentlyContinue | Out-Null
        Write-Log -Message 'Marked all notifications as read' -Level 'INFO'
    } catch {
        Write-Log -Message "Failed to mark all notifications as read: $_" -Level 'WARN'
    }
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
    
    $text = $lines | Where-Object { $_ }
    
    try {
        New-BurntToastNotification -Text $title, ($text -join "`n") -Silent
        Write-Log -Message "Displayed toast: $title" -Level 'INFO'
    } catch {
        Write-Log -Message "Failed to display toast: $_" -Level 'ERROR'
    }
}

# ============================================================
# Load .env.local if present
# ============================================================
$scriptDir = Split-Path -Parent $PSCommandPath
$envLocal = Join-Path $scriptDir '.env.local'
if (Test-Path $envLocal) {
    Get-Content $envLocal | ForEach-Object {
        if ($_ -match '^\s*(\w+)\s*=\s*(.+)\s*$') {
            $val = $Matches[2] -replace '^["'']|["'']$'
            if ($Matches[1] -eq 'BASE_URL') {
                $BaseUrl = $val
                Write-Log -Message "Loaded BaseUrl from .env.local: $BaseUrl" -Level 'INFO'
            }
        }
    }
}

# ============================================================
# Load saved credentials
# ============================================================
$savedCredsPath = Join-Path $scriptDir '.notifier-user.enc'

function Get-SavedCredentials {
    param([string]$Path)
    if (!(Test-Path $Path)) { return $null }
    try {
        $cred = Import-Clixml -Path $Path
        return $cred
    } catch {
        Write-Log -Message "Failed to load saved credentials: $_" -Level 'WARN'
        return $null
    }
}

# ============================================================
# Determine credentials
# ============================================================
$finalUsername = $null
$plainPassword = $null

if ($UseSavedCreds) {
    $savedCreds = Get-SavedCredentials -Path $savedCredsPath
    if ($savedCreds) {
        $finalUsername = $savedCreds.UserName
        $plainPassword = ConvertTo-PlainPassword -SecureString $savedCreds.Password
        Write-Log -Message 'Using saved credentials (UseSavedCreds)' -Level 'INFO'
    } else {
        Write-Error "No saved credentials found. Run scripts\save-credentials.ps1 first."
        exit 1
    }
} elseif ($Credential) {
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
Write-Log -Message '========================================' -Level 'INFO'
Write-Log -Message 'Romar Ticket Notifier Starting' -Level 'INFO'
Write-Log -Message "Base URL: $BaseUrl" -Level 'INFO'
Write-Log -Message "Poll Interval: ${PollIntervalSeconds}s" -Level 'INFO'
Write-Log -Message "Log File: $LogPath" -Level 'INFO'
Write-Log -Message '========================================' -Level 'INFO'

try {
    Test-BurntToastModule
} catch {
    Write-Log -Message "BurntToast module error: $_" -Level 'ERROR'
    Write-Error "Cannot start notifier: $_"
    exit 1
}

# Initial connection
try {
    $session = Connect-ToRomar -UserName $finalUsername -Password $plainPassword
} catch {
    Write-Log -Message "Initial login failed: $_" -Level 'ERROR'
    Write-Error "Cannot start notifier: $_"
    exit 1
}

$seenIds = New-Object 'System.Collections.Generic.HashSet[int]'
$consecutiveErrors = 0
$maxConsecutiveErrors = 5

Write-Log -Message "Starting notifier. Polling every ${PollIntervalSeconds}s. Press Ctrl+C to stop." -Level 'INFO'

while ($true) {
    try {
        $notifications = Get-RomarNotifications -Session $session
        $consecutiveErrors = 0  # Reset error counter on success
    } catch {
        $consecutiveErrors++
        Write-Log -Message "Unable to refresh notifications (attempt $consecutiveErrors/$maxConsecutiveErrors): $_" -Level 'WARN'
        
        if ($consecutiveErrors -ge $maxConsecutiveErrors) {
            Write-Log -Message 'Too many consecutive errors. Attempting re-login...' -Level 'ERROR'
            try {
                Start-Sleep -Seconds 5
                $session = Connect-ToRomar -UserName $finalUsername -Password $plainPassword
                $consecutiveErrors = 0
                $seenIds.Clear()  # Reset seen IDs after re-login
                Write-Log -Message 'Re-login successful' -Level 'INFO'
            } catch {
                Write-Log -Message "Re-login failed: $_" -Level 'ERROR'
                Start-Sleep -Seconds 30
            }
        } else {
            Start-Sleep -Seconds 10
        }
        continue
    }
    
    # Filter: unseen AND unread
    $batch = $notifications |
        Where-Object { -not $seenIds.Contains($_.notif_id) -and (-not $_.is_read) }
    
    if ($batch) {
        Write-Log -Message "Found $($batch.Count) new notification(s) to display" -Level 'INFO'
        
        foreach ($notif in $batch) {
            Show-TicketToast -Notification $notif
            Set-NotificationRead -Session $session -NotifId $notif.notif_id
            $seenIds.Add($notif.notif_id) | Out-Null
        }
        
        if ($MarkAllRead) {
            Set-AllNotificationsRead -Session $session
        }
    } else {
        Write-Log -Message 'No new notifications' -Level 'DEBUG'
    }
    
    Start-Sleep -Seconds $PollIntervalSeconds
}

