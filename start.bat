@echo off
TITLE ZimRx Prescription System - FrankenPHP
cd /d "%~dp0"
set "APP_ROOT=%CD%"
set "PHPRC=%APP_ROOT%\runtime\frankenphp"

cls
echo ==========================================
echo   ZimRx Digital Prescription System
echo   Starting with FrankenPHP...
echo ==========================================
echo.

if not exist "runtime\frankenphp\frankenphp.exe" (
    echo FrankenPHP runtime was not found at runtime\frankenphp\frankenphp.exe
    echo.
    set /p "DO_SETUP=Would you like to automatically download and configure FrankenPHP now? (Y/n): "
    if /i not "%DO_SETUP%"=="n" (
        call "%~dp0setup-franken-for-dev.bat"
    )
    if not exist "runtime\frankenphp\frankenphp.exe" (
        echo.
        echo [ERROR] FrankenPHP runtime was not found. Please run setup-franken-for-dev.bat manually.
        pause
        exit /b 1
    )
)

if not exist "application\userdata\database" mkdir application\userdata\database
if not exist "application\userdata\uploads" mkdir application\userdata\uploads
if not exist "logs" mkdir logs

:: Kill any leftover frankenphp instances before starting
taskkill /f /im frankenphp.exe >nul 2>&1
if exist "logs\frankenphp.pid" del /q "logs\frankenphp.pid" >nul 2>&1

set "ZIMRX_HTTP_PORT=8080"

echo [1/2] Launching FrankenPHP web server on port %ZIMRX_HTTP_PORT%...
start "" /min runtime\frankenphp\frankenphp.exe run --config Caddyfile --adapter caddyfile

echo [2/2] Initializing application environment...
timeout /t 2 /nobreak >nul

echo.
echo ==========================================
echo   System Ready!
echo.
echo   Doctor / this computer:
echo   http://localhost:%ZIMRX_HTTP_PORT%
echo.
echo   Assistant / other computer on same WiFi:
powershell -NoLogo -NoProfile -Command "$ips = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.|192\.168\.56\.)' } | ForEach-Object { Write-Host \"  http://$($_.IPAddress):%ZIMRX_HTTP_PORT%\" }"
echo.
echo ==========================================
echo.
echo ZimRx is actively running. Keep this window open.
echo To stop ZimRx, press any key in this window...

start "" "http://localhost:%ZIMRX_HTTP_PORT%"

pause >nul

echo.
echo Stopping ZimRx server...
taskkill /f /im frankenphp.exe >nul 2>&1
exit /b 0
