@echo off
TITLE ZimRx - FrankenPHP Development Setup
cd /d "%~dp0"

echo ========================================================
echo   ZimRx - Automated FrankenPHP Runtime Setup
echo ========================================================
echo.

set "RUNTIME_DIR=%~dp0runtime\frankenphp"
set "ZIP_FILE=%RUNTIME_DIR%\frankenphp.zip"
set "DOWNLOAD_URL=https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-windows-x86_64.zip"

if not exist "%RUNTIME_DIR%" mkdir "%RUNTIME_DIR%"

if exist "%RUNTIME_DIR%\frankenphp.exe" (
    echo [OK] FrankenPHP binary already exists at:
    echo      %RUNTIME_DIR%\frankenphp.exe
    echo.
    set /p "REINSTALL=Do you want to re-download and reinstall? (y/N): "
    if /i not "%REINSTALL%"=="y" goto :configure_ini
)

echo [1/4] Downloading latest FrankenPHP (Windows x86_64)...
echo       Source: %DOWNLOAD_URL%
echo.

powershell -NoLogo -NoProfile -Command ^
    "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; " ^
    "$ProgressPreference = 'Continue'; " ^
    "Write-Host 'Downloading runtime package...'; " ^
    "Invoke-WebRequest -Uri '%DOWNLOAD_URL%' -OutFile '%ZIP_FILE%' -UseBasicParsing; " ^
    "if (-not (Test-Path '%ZIP_FILE%')) { exit 1 }"

if errorlevel 1 (
    echo [ERROR] Failed to download FrankenPHP. Please check your internet connection.
    pause
    exit /b 1
)

echo.
echo [2/4] Extracting runtime package...
powershell -NoLogo -NoProfile -Command ^
    "Expand-Archive -Path '%ZIP_FILE%' -DestinationPath '%RUNTIME_DIR%' -Force; " ^
    "Remove-Item -Path '%ZIP_FILE%' -Force -ErrorAction SilentlyContinue"

if not exist "%RUNTIME_DIR%\frankenphp.exe" (
    echo [ERROR] Extraction failed. frankenphp.exe was not found.
    pause
    exit /b 1
)

:configure_ini
echo.
echo [3/4] Configuring php.ini with required extensions...

(
echo [PHP]
echo engine = On
echo short_open_tag = On
echo precision = 14
echo output_buffering = 4096
echo zlib.output_compression = Off
echo implicit_flush = Off
echo serialize_precision = -1
echo zend.enable_gc = On
echo zend.exception_ignore_args = On
echo zend.exception_string_param_max_len = 0
echo.
echo max_execution_time = 60
echo max_input_time = 60
echo memory_limit = 256M
echo.
echo error_reporting = E_ALL ^& ~E_DEPRECATED ^& ~E_STRICT
echo display_errors = Off
echo display_startup_errors = Off
echo log_errors = On
echo log_errors_max_len = 1024
echo ignore_repeated_errors = Off
echo ignore_repeated_source = Off
echo report_memleaks = On
echo error_log = "logs/php-errors.log"
echo.
echo variables_order = "GPCS"
echo request_order = "GP"
echo register_argc_argv = Off
echo auto_globals_jit = On
echo post_max_size = 64M
echo auto_prepend_file =
echo auto_append_file =
echo default_mimetype = "text/html"
echo default_charset = "UTF-8"
echo.
echo extension_dir = "ext"
echo enable_dl = Off
echo file_uploads = On
echo upload_max_filesize = 64M
echo max_file_uploads = 20
echo allow_url_fopen = On
echo allow_url_include = Off
echo default_socket_timeout = 60
echo.
echo extension=bz2
echo extension=curl
echo extension=fileinfo
echo extension=gd
echo extension=intl
echo extension=mbstring
echo extension=openssl
echo extension=pdo_sqlite
echo extension=pdo_mysql
echo extension=sqlite3
echo extension=sodium
echo extension=zip
echo.
echo [Date]
echo date.timezone = "Asia/Dhaka"
echo.
echo [Session]
echo session.save_handler = files
echo session.use_strict_mode = 0
echo session.use_cookies = 1
echo session.use_only_cookies = 1
echo session.name = PHPSESSID
echo session.auto_start = 0
echo session.cookie_lifetime = 0
echo session.cookie_path = /
echo session.cookie_domain =
echo session.cookie_httponly = 1
echo session.cookie_samesite = ""
echo session.serialize_handler = php
echo session.gc_probability = 1
echo session.gc_divisor = 1000
echo session.gc_maxlifetime = 1440
echo session.cache_limiter = nocache
echo session.cache_expire = 180
echo session.use_trans_sid = 0
echo session.sid_length = 26
echo session.trans_sid_tags = "a=href,area=href,frame=src,form="
echo session.sid_bits_per_character = 5
echo.
echo [opcache]
echo opcache.enable = 1
echo opcache.enable_cli = 1
echo opcache.memory_consumption = 128
echo opcache.interned_strings_buffer = 8
echo opcache.max_accelerated_files = 10000
echo opcache.revalidate_freq = 2
echo opcache.fast_shutdown = 1
) > "%RUNTIME_DIR%\php.ini"

echo.
echo [4/4] Verifying PHP runtime and database extensions...
powershell -NoLogo -NoProfile -Command ^
    "$output = & '%RUNTIME_DIR%\php.exe' -c '%RUNTIME_DIR%\php.ini' -m; " ^
    "if ($output -match 'pdo_sqlite' -and $output -match 'sqlite3' -and $output -match 'mbstring') { " ^
    "    Write-Host '     [PASS] All core SQLite and PDO extensions verified!' -ForegroundColor Green " ^
    "} else { " ^
    "    Write-Host '     [WARN] Some extensions could not be verified.' -ForegroundColor Yellow " ^
    "}"

echo.
echo ========================================================
echo   Setup Complete!
echo   You can now launch ZimRx anytime by running: start.bat
echo ========================================================
echo.
pause
