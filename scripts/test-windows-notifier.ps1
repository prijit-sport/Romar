#Requires -Version 5.1
<#
.SYNOPSIS
    Simple smoke test for Windows Ticket Notifier
#>
param(
    [string]$BaseUrl = 'http://localhost/Romar',
    [string]$Username = 'admin',
    [string]$Password = 'admin123'
)

Write-Host "========== Romar Windows Notification Smoke Test ==========" -ForegroundColor Cyan
Write-Host ""

# 1. Check BurntToast
Write-Host "[1/4] Checking BurntToast module..." -NoNewline
if (Get-Module -ListAvailable -Name BurntToast) {
    Import-Module BurntToast -ErrorAction Stop
    Write-Host " OK" -ForegroundColor Green
    # Show a test toast
    try {
        New-BurntToastNotification -Text 'Romar Test', 'This is a test notification!' -Silent -ErrorAction Stop
        Write-Host "      > Test toast displayed successfully" -ForegroundColor Green
    } catch {
        Write-Host "      > Warning: Toast display failed: $_" -ForegroundColor Yellow
    }
} else {
    Write-Host " MISSING - Installing..." -ForegroundColor Yellow
    try {
        Install-Module -Name BurntToast -Scope CurrentUser -Force -AllowClobber -ErrorAction Stop
        Import-Module BurntToast -ErrorAction Stop
        Write-Host "      > Installed successfully" -ForegroundColor Green
    } catch {
        Write-Host "      > FAILED: $_" -ForegroundColor Red
    }
}

# 2. Check network connectivity to API
Write-Host ""
Write-Host "[2/4] Checking API connectivity to $BaseUrl..." -NoNewline
try {
    $response = Invoke-WebRequest -Uri "$BaseUrl/api/getnotificationcount.php" -UseBasicParsing -Method GET -TimeoutSec 10 -ErrorAction Stop
    if ($response.StatusCode -eq 200) {
        Write-Host " OK (HTTP 200)" -ForegroundColor Green
    } else {
        Write-Host " HTTP $($response.StatusCode)" -ForegroundColor Yellow
    }
} catch {
    Write-Host " FAILED: $_" -ForegroundColor Red
}

# 3. Test login endpoint
Write-Host ""
Write-Host "[3/4] Checking login page CSRF..." -NoNewline
try {
    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" -UseBasicParsing -Method GET -TimeoutSec 10 -ErrorAction Stop
    if ($loginPage.Content -match 'name="csrf_token"') {
        Write-Host " OK (CSRF token found)" -ForegroundColor Green
    } else {
        Write-Host " Warning: No CSRF token detected" -ForegroundColor Yellow
    }
} catch {
    Write-Host " FAILED: $_" -ForegroundColor Red
}

# 4. Test login flow (optional)
Write-Host ""
Write-Host "[4/4] Testing login + API flow with user '$Username'..." -NoNewline
try {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" -WebSession $session -UseBasicParsing -TimeoutSec 10
    $match = [regex]::Match($loginPage.Content, 'name="csrf_token"\s+value="([^"]+)"')
    if (-not $match.Success) { throw "CSRF not found" }
    $csrf = $match.Groups[1].Value
    
    $loginBody = @{ username = $Username; password = $Password; csrf_token = $csrf }
    $loginResp = Invoke-WebRequest -Uri "$BaseUrl/auth/login.php" -Method Post -Body $loginBody -WebSession $session -UseBasicParsing -TimeoutSec 10
    
    # Now test API with session
    $apiResp = Invoke-RestMethod -Uri "$BaseUrl/api/getnotifications.php" -WebSession $session -Method GET -TimeoutSec 10 -ErrorAction Stop
    if ($apiResp.notifications -ne $null) {
        $count = $apiResp.notifications.Count
        Write-Host " OK ($count notifications)" -ForegroundColor Green
    } else {
        Write-Host " OK (no notifications field)" -ForegroundColor Yellow
    }
} catch {
    Write-Host " FAILED: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "========== Smoke Test Complete ==========" -ForegroundColor Cyan
Write-Host ""
Write-Host "To start the notifier when XAMPP is running:" -ForegroundColor Cyan
Write-Host "  powershell -File scripts/windows-ticket-notifier.ps1 -Username admin -Password <your-password>" -ForegroundColor White
Write-Host ""
Write-Host "To install as a scheduled task:" -ForegroundColor Cyan
Write-Host "  powershell -File scripts/setup-windows-notifier.ps1 -Username admin -Password <your-password>" -ForegroundColor White
Write-Host ""

