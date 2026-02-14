@echo off
title Share Folder - Romar Industrial
color 0A

echo ========================================
echo   Romar File Sharing - Python Server
echo ========================================
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Python is not installed!
    echo Please install Python first from:
    echo https://www.python.org/downloads/
    echo.
    pause
    exit
)

echo [OK] Python detected
echo.

REM Get current directory
set "SHARE_PATH=%cd%"
echo Sharing folder: %SHARE_PATH%
echo.

REM Get IP address
echo Your IP addresses:
echo ----------------------------------------
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do echo   http:%%a:8000
echo ----------------------------------------
echo.

echo [INFO] Starting HTTP server on port 8000...
echo [INFO] Press Ctrl+C to stop the server
echo.
echo Share this URL with your colleagues!
echo.

REM Start Python HTTP server
python -m http.server 8000

pause