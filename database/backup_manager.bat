@echo off
REM Database Backup Manager Batch Script (Windows)
REM Usage: backup_manager.bat list
REM        backup_manager.bat archive
REM        backup_manager.bat cleanup

setlocal enabledelayedexpansion

set "BACKUP_DIR=C:\xampp\htdocs\Romar\database\backups"
set "ARCHIVE_DIR=C:\xampp\htdocs\Romar\database\archive"
set "ACTION=%1"

if "%ACTION%"=="" (
    set "ACTION=list"
)

if "%ACTION%"=="list" (
    echo.
    echo ================================ DATABASE BACKUPS STATUS ================================
    echo.
    echo Active Backups:
    dir "%BACKUP_DIR%" /b *.sql 2>nul | find /v "schema_mysql.sql"
    if errorlevel 1 (
        echo   No backups found
    )
    echo.
    echo Archived Backups:
    dir "%ARCHIVE_DIR%" /b *.sql 2>nul
    if errorlevel 1 (
        echo   No archived backups
    )
    echo.
    echo =====================================================================================
    echo.
    goto :EOF
)

if "%ACTION%"=="archive" (
    echo.
    echo [*] Archiving old backups (keeping 3 latest)...
    echo.
    
    REM Get list of files sorted by date
    for /f "tokens=*" %%F in ('dir "%BACKUP_DIR%\*.sql" /b /o-d 2^>nul') do (
        if not "%%F"=="schema_mysql.sql" (
            REM Count files
            for /f %%C in ('dir "%BACKUP_DIR%\*.sql" /b 2^>nul ^| find /c ".*"') do (
                if %%C gtr 3 (
                    @REM This is simplified; in production use PowerShell
                    echo [+] Would archive: %%F
                )
            )
        )
    )
    echo.
    echo [+] Use PowerShell for full archival: powershell -ExecutionPolicy Bypass -File "%~dp0backup_manager.ps1" -Action archive
    echo.
    goto :EOF
)

if "%ACTION%"=="cleanup" (
    echo.
    echo [*] Running full cleanup...
    echo.
    powershell -ExecutionPolicy Bypass -File "%~dp0backup_manager.ps1" -Action cleanup
    goto :EOF
)

echo Usage: %0 [list^|archive^|cleanup]
echo.
echo Options:
echo   list    - Show all backups
echo   archive - Move old backups to archive folder
echo   cleanup - Full cleanup (archive + delete old files)
echo.
