# Database Backup Manager Script (Windows PowerShell)
# Usage: .\backup_manager.ps1 -Action cleanup

param(
    [string]$Action = "list",
    [string]$BackupDir = "C:\xampp\htdocs\Romar\database\backups",
    [string]$ArchiveDir = "C:\xampp\htdocs\Romar\database\archive",
    [int]$MaxActiveBackups = 3,
    [int]$ArchiveAfterDays = 7
)

function Format-FileSize {
    param($bytes)
    if ($bytes -lt 1KB) { return "$bytes B" }
    elseif ($bytes -lt 1MB) { return "{0:N2} KB" -f ($bytes / 1KB) }
    elseif ($bytes -lt 1GB) { return "{0:N2} MB" -f ($bytes / 1MB) }
    else { return "{0:N2} GB" -f ($bytes / 1GB) }
}

function Backup-DisplayStatus {
    Write-Host "`nPowershell Backup Manager - Status`n"
    Write-Host ("=" * 100)
    
    # Get all backups
    $backups = @()
    
    # Active backups
    Get-ChildItem -Path $BackupDir -Filter "*.sql" -ErrorAction SilentlyContinue | ForEach-Object {
        if ($_.Name -ne "schema_mysql.sql") {
            $backups += @{
                File = $_.Name
                Size = $_.Length
                Location = "ACTIVE"
                Date = $_.LastWriteTime
                Path = $_.FullName
            }
        }
    }
    
    # Archived backups
    Get-ChildItem -Path $ArchiveDir -Filter "*.sql" -ErrorAction SilentlyContinue | ForEach-Object {
        $backups += @{
            File = $_.Name
            Size = $_.Length
            Location = "ARCHIVE"
            Date = $_.LastWriteTime
            Path = $_.FullName
        }
    }
    
    # Sort by date (newest first)
    $backups = $backups | Sort-Object { $_.Date } -Descending
    
    if ($backups.Count -eq 0) {
        Write-Host "No backups found`n"
        return
    }
    
    # Display table
    Write-Host ("{0,-50} | {1,-15} | {2,-10} | {3}" -f @("FILENAME", "SIZE", "LOCATION", "DATE"))
    Write-Host ("-" * 100)
    
    $backups | ForEach-Object {
        $sizeStr = Format-FileSize $_.Size
        $dateStr = $_.Date.ToString("yyyy-MM-dd HH:mm")
        $marker = if ($_ -eq $backups[0]) { "LATEST" } else { "      " }
        
        $line = "{0} {1,-43} | {2,-15} | {3,-10} | {4}" -f `
            $marker, $_.File, $sizeStr, $_.Location, $dateStr
        Write-Host $line
    }
    
    Write-Host ("=" * 100)
    Write-Host ("Total: {0} backups`n" -f $backups.Count)
}

function Backup-ArchiveOldFiles {
    Write-Host "`nArchiving old backups...`n"
    
    # Get active backups sorted by date (newest first)
    $activeBackups = Get-ChildItem -Path $BackupDir -Filter "*.sql" -ErrorAction SilentlyContinue | 
                     Where-Object { $_.Name -ne "schema_mysql.sql" } |
                     Sort-Object { $_.LastWriteTime } -Descending
    
    if ($activeBackups.Count -le $MaxActiveBackups) {
        Write-Host "All backups are recent. No archival needed.`n"
        return
    }
    
    # Archive older files
    $toArchive = $activeBackups | Select-Object -Skip $MaxActiveBackups
    $archivedCount = 0
    
    foreach ($file in $toArchive) {
        $destination = Join-Path $ArchiveDir $file.Name
        
        try {
            Move-Item -Path $file.FullName -Destination $destination -Force
            Write-Host ("ARCHIVE: {0}" -f $file.Name)
            $archivedCount++
        }
        catch {
            Write-Host ("ERROR archiving {0}: {1}" -f $file.Name, $_)
        }
    }
    
    Write-Host ("`nArchived {0} backup(s)`n" -f $archivedCount)
}

function Backup-DeleteOldArchives {
    Write-Host "`nDeleting old archived backups...`n"
    
    $cutoffDate = (Get-Date).AddDays(-$ArchiveAfterDays)
    $deletedCount = 0
    
    Get-ChildItem -Path $ArchiveDir -Filter "*.sql" -ErrorAction SilentlyContinue | ForEach-Object {
        if ($_.LastWriteTime -lt $cutoffDate) {
            try {
                Remove-Item -Path $_.FullName -Force
                Write-Host ("DELETE: {0}" -f $_.Name)
                $deletedCount++
            }
            catch {
                Write-Host ("ERROR deleting {0}: {1}" -f $_.Name, $_)
            }
        }
    }
    
    if ($deletedCount -gt 0) {
        Write-Host ("`nDeleted {0} old archived backup(s)`n" -f $deletedCount)
    } else {
        Write-Host "`nNo old archives to delete`n"
    }
}

function Backup-CleanupAll {
    Write-Host "`nRunning full cleanup...`n"
    Backup-ArchiveOldFiles
    Backup-DeleteOldArchives
    Write-Host "Cleanup completed`n"
}

# Main execution
switch ($Action.toLower()) {
    "list" {
        Backup-DisplayStatus
    }
    "archive" {
        Backup-ArchiveOldFiles
    }
    "cleanup" {
        Backup-CleanupAll
    }
    default {
        Write-Host "Usage: .\backup_manager.ps1 -Action [list|archive|cleanup]"
        Write-Host ""
        Write-Host "Options:"
        Write-Host "  list     - Show all backups (active + archived)"
        Write-Host "  archive  - Move old backups to archive/"
        Write-Host "  cleanup  - Full cleanup: archive + delete old"
        Write-Host ""
        Write-Host "Parameters:"
        Write-Host "  -BackupDir              : Active backups directory"
        Write-Host "  -ArchiveDir             : Archive directory"
        Write-Host "  -MaxActiveBackups       : Number of backups to keep active (default: 3)"
        Write-Host "  -ArchiveAfterDays       : Delete archives older than N days (default: 7)"
    }
}
