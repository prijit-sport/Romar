#Requires -Version 5.1
<#
.SYNOPSIS
    Safe cleanup script for Romar project.
    Moves optional files to archive and removes known junk files.
#>
Param(
    [string]$ProjectRoot = "C:\xampp\htdocs\Romar",
    [switch]$WhatIf,
    [switch]$Force
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$action = { param($desc,$script)
    Write-Host "[$desc] " -NoNewline -ForegroundColor Cyan
    if ($WhatIf) {
        Write-Host "(WhatIf)" -ForegroundColor Yellow
        & $script $true
    } else {
        try {
            & $script $false
            Write-Host "OK" -ForegroundColor Green
        } catch {
            Write-Host "FAIL: $_" -ForegroundColor Red
        }
    }
}

# --- 1. Create archive directories ---
& $action "Creating archive directories" {
    param($isWhatIf)
    $dirs = @("$ProjectRoot\archive\tmp","$ProjectRoot\archive\bak",
              "$ProjectRoot\archive\logs","$ProjectRoot\archive\reports",
              "$ProjectRoot\archive\debug","$ProjectRoot\archive\db")
    foreach ($d in $dirs) {
        if ($isWhatIf) { Write-Host "  Would create $d" }
        else { New-Item -ItemType Directory -Path $d -Force | Out-Null }
    }
}

# --- 2. Remove Python venv (biggest space saver, recreatable from requirements.txt) ---
& $action "Removing Python venv (tests/load/venv)" {
    param($isWhatIf)
    $venvPath = "$ProjectRoot\tests\load\venv"
    if (Test-Path $venvPath) {
        if ($isWhatIf) { Write-Host "  Would remove $venvPath (~2000 files)" }
        else {
            Remove-Item -Path $venvPath -Recurse -Force
            # Add to .gitignore if not present
            $gi = "$ProjectRoot\.gitignore"
            if (Test-Path $gi) {
                $content = Get-Content $gi -Raw
                if ($content -notmatch 'tests/load/venv/') {
                    Add-Content -Path $gi -Value "`r`ntests/load/venv/"
                }
            }
        }
    }
}

# --- 3. Delete all tmp_* / temp_* files ---
& $action "Deleting tmp_* / temp_* files" {
    param($isWhatIf)
    $patterns = @('tmp_*','temp_*')
    foreach ($pat in $patterns) {
        Get-ChildItem -Path $ProjectRoot -Filter $pat -Recurse -File -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -notmatch '\\venv\\' -and $_.FullName -notmatch '\\archive\\' } |
            ForEach-Object {
                if ($isWhatIf) { Write-Host "  Would delete $($_.FullName)" }
                else { Remove-Item $_.FullName -Force }
            }
    }
}

# --- 4. Move .bak files to archive ---
& $action "Moving .bak files to archive" {
    param($isWhatIf)
    Get-ChildItem -Path $ProjectRoot -Filter '*.bak' -Recurse -File -ErrorAction SilentlyContinue |
        ForEach-Object {
            $dest = "$ProjectRoot\archive\bak\$($_.Name)"
            if ($isWhatIf) { Write-Host "  Would move $($_.Name) -> archive/bak/" }
            else { Move-Item $_.FullName $dest -Force }
        }
}

# --- 5. Move old logs to archive ---
& $action "Moving old logs to archive" {
    param($isWhatIf)
    $logDir = "$ProjectRoot\logs"
    $archiveDir = "$ProjectRoot\archive\logs"
    if (Test-Path $logDir) {
        Get-ChildItem -Path $logDir -Filter '*.log' -File -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -match '^security-\d{8}-' -or $_.Name -match '^db-backup-\d{8}_' } |
            ForEach-Object {
                if ($isWhatIf) { Write-Host "  Would move $($_.Name) -> archive/logs/" }
                else { Move-Item $_.FullName $archiveDir -Force }
            }
    }
}

# --- 6. Delete debug/test files ---
& $action "Deleting known debug/test files" {
    param($isWhatIf)
    $toDelete = @(
        'debug.php','test-system.php','test-includes.php','test-loading.php',
        'test-dashboard-error.php','diagnostics.php','insert-users.php'
    )
    foreach ($f in $toDelete) {
        $path = "$ProjectRoot\$f"
        if (Test-Path $path) {
            if ($isWhatIf) { Write-Host "  Would delete $f" }
            else { Remove-Item $path -Force }
        }
    }
}

# --- 7. Delete utility scripts that are no longer needed ---
& $action "Deleting utility scripts" {
    param($isWhatIf)
    $scripts = @('scripts/dups.py','scripts/find_dups.ps1','scripts/cleanup_tmp.bat',
                 'replace_sections.js','replace_texts.ps1')
    foreach ($f in $scripts) {
        $path = "$ProjectRoot\$f"
        if (Test-Path $path) {
            if ($isWhatIf) { Write-Host "  Would delete $f" }
            else { Remove-Item $path -Force }
        }
    }
}

# --- 8. Delete old SQLite database ---
& $action "Deleting old SQLite DB" {
    param($isWhatIf)
    $db = "$ProjectRoot\database\dormitory.db"
    if (Test-Path $db) {
        if ($isWhatIf) { Write-Host "  Would delete dormitory.db" }
        else { Remove-Item $db -Force }
    }
}

# --- 9. Move report/TODO markdown files to archive ---
& $action "Moving report/TODO files to archive" {
    param($isWhatIf)
    $reports = @('ANALYSIS_REPORT.md','ANALYSIS_REPORT_cleanup.md','CLEANUP_REPORT_FINAL.md',
                 'CLEANUP_EXECUTION_REPORT.md','TODO.md','TODO2.md','TODO_cleanup.md',
                 'TODO_deploy.md','TODO_NOTIFICATION_FIX.md','TODO_WINDOWS_NOTIFICATIONS.md',
                 'TODO_FIX_ALL.md','FINAL_COMPREHENSIVE_REPORT.md','ENHANCEMENT_PROGRESS_REPORT.md',
                 'PROJECT_REPORT.md','PROJECT_CLEANUP_REPORT.md','STANDARDIZATION_TEMPLATE.md',
                 'UI_UX_AUDIT_REPORT.md','.pr-body-temp.md')
    foreach ($f in $reports) {
        $path = "$ProjectRoot\$f"
        if (Test-Path $path) {
            if ($isWhatIf) { Write-Host "  Would move $f -> archive/reports/" }
            else { Move-Item $path "$ProjectRoot\archive\reports\$f" -Force }
        }
    }
}

# --- 10. Move backup SQL dumps to archive ---
& $action "Moving backup SQL dumps to archive" {
    param($isWhatIf)
    $bakDir = "$ProjectRoot\database\backups"
    $archiveDir = "$ProjectRoot\archive\db"
    if (Test-Path $bakDir) {
        Get-ChildItem -Path $bakDir -Filter '*.sql' -File -ErrorAction SilentlyContinue |
            ForEach-Object {
                if ($isWhatIf) { Write-Host "  Would move $($_.Name) -> archive/db/" }
                else { Move-Item $_.FullName $archiveDir -Force }
            }
    }
}

# --- 11. Delete unneeded files ---
& $action "Deleting unneeded files" {
    param($isWhatIf)
    $toDelete = @(
        'modules/assets_form.html',
        'modules/userProfile_original_backup.php'
    )
    foreach ($f in $toDelete) {
        $path = "$ProjectRoot\$f"
        if (Test-Path $path) {
            if ($isWhatIf) { Write-Host "  Would delete $f" }
            else { Remove-Item $path -Force }
        }
    }
}

# --- 12. Delete duplicate analysis report from root ---
& $action "Deleting CLEANUP_ANALYSIS_SUMMARY.md from root" {
    param($isWhatIf)
    $f = "$ProjectRoot\CLEANUP_ANALYSIS_SUMMARY.md"
    if (Test-Path $f) {
        if ($isWhatIf) { Write-Host "  Would delete CLEANUP_ANALYSIS_SUMMARY.md" }
        else { Remove-Item $f -Force }
    }
}

Write-Host "`nCleanup completed! Check $ProjectRoot\archive\ for moved files." -ForegroundColor Green

