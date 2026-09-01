@echo off
TITLE ZimRx Prescription System - FrankenPHP
cd /d "%~dp0"
set "APP_ROOT=%CD%"

set "ZIMRX_HTTP_PORT="
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

if exist "logs\frankenphp.pid" (
    for /f "usebackq delims=" %%P in ("logs\frankenphp.pid") do powershell -NoLogo -NoProfile -Command "$Env:POWERSHELL_UPDATECHECK='Off'; Stop-Process -Id %%P -Force -ErrorAction SilentlyContinue" >nul 2>&1
    del /q "logs\frankenphp.pid" >nul 2>&1
)

echo [1/2] Detecting free HTTP port...
for /f %%P in ('powershell -NoLogo -NoProfile -Command "$Env:POWERSHELL_UPDATECHECK='Off'; $p=8080; try{$l=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$p);$l.Start();$l.Stop();$p}catch{$l=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,0);$l.Start();$p=$l.LocalEndpoint.Port;$l.Stop();$p}"') do set "ZIMRX_HTTP_PORT=%%P"
if "%ZIMRX_HTTP_PORT%"=="" set "ZIMRX_HTTP_PORT=8080"

echo [2/2] Starting FrankenPHP on port %ZIMRX_HTTP_PORT%...
start /b "" runtime\frankenphp\frankenphp.exe run --config Caddyfile --adapter caddyfile --pidfile logs\frankenphp.pid > logs\frankenphp.log 2>&1

:: Wait for TCP port to be actively listening
powershell -NoLogo -NoProfile -Command "$Env:POWERSHELL_UPDATECHECK='Off'; for ($i=0; $i -lt 30; $i++) { try { $t=New-Object Net.Sockets.TcpClient('127.0.0.1', %ZIMRX_HTTP_PORT%); $t.Close(); break } catch { Start-Sleep -Milliseconds 150 } }"

echo.
echo ==========================================
echo   System Ready!
echo.
echo   Doctor / this computer:
echo   http://localhost:%ZIMRX_HTTP_PORT%
echo.
echo   Assistant / other computer on same WiFi:
powershell -NoLogo -NoProfile -WindowStyle Hidden -Command "$Env:POWERSHELL_UPDATECHECK='Off'; $ips=Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notmatch '^(127\.|169\.254\.|192\.168\.56\.)'} | ForEach-Object {Write-Output \"  http://$($_.IPAddress):%ZIMRX_HTTP_PORT%\"}"
echo.
echo   Runtime:
echo   runtime\frankenphp
echo.
echo   Logs:
echo   logs\frankenphp.log
echo   logs\php-errors.log
echo ==========================================
echo.
echo ZimRx is running. Press any key to stop the server...

start "" "http://localhost:%ZIMRX_HTTP_PORT%"

pause >nul

echo.
echo Stopping FrankenPHP...
if exist "logs\frankenphp.pid" (
    for /f "usebackq delims=" %%P in ("logs\frankenphp.pid") do powershell -NoLogo -NoProfile -Command "$Env:POWERSHELL_UPDATECHECK='Off'; Stop-Process -Id %%P -Force -ErrorAction SilentlyContinue" >nul 2>&1
    del /q "logs\frankenphp.pid" >nul 2>&1
)
exit /b 0
