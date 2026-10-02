#!/usr/bin/env bash
# ZimRx Linux & macOS Launch Script (FrankenPHP / PHP Built-in)

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

ZIMRX_PORT="${ZIMRX_HTTP_PORT:-8080}"

echo "=========================================="
echo "  ZimRx Digital Prescription System"
echo "  Starting local environment..."
echo "=========================================="
echo ""

# Ensure required state directories exist
mkdir -p "application/userdata/database"
mkdir -p "application/userdata/uploads/reports"
mkdir -p "application/userdata/uploads/header-logos"
mkdir -p "application/userdata/uploads/full-body-headers"
mkdir -p "application/userdata/uploads/seal-and-stamps"
mkdir -p "application/userdata/uploads/background-images"
mkdir -p "logs"

# Clean up any stale PID file
if [ -f "logs/frankenphp.pid" ]; then
    rm -f "logs/frankenphp.pid"
fi

cleanup() {
    echo ""
    echo "Stopping ZimRx server..."
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    echo "ZimRx stopped."
    exit 0
}

trap cleanup INT TERM EXIT

# Determine best available runtime: FrankenPHP or PHP CLI
if command -v frankenphp >/dev/null 2>&1; then
    echo "[1/2] Launching with FrankenPHP on port $ZIMRX_PORT..."
    frankenphp run --config Caddyfile --adapter caddyfile >> logs/frankenphp.log 2>&1 &
    SERVER_PID=$!
elif [ -f "runtime/frankenphp/frankenphp" ]; then
    echo "[1/2] Launching with local FrankenPHP binary on port $ZIMRX_PORT..."
    ./runtime/frankenphp/frankenphp run --config Caddyfile --adapter caddyfile >> logs/frankenphp.log 2>&1 &
    SERVER_PID=$!
elif command -v php >/dev/null 2>&1; then
    echo "[1/2] FrankenPHP not detected. Falling back to PHP built-in server on port $ZIMRX_PORT..."
    php -S "0.0.0.0:$ZIMRX_PORT" -t application/public >> logs/php-server.log 2>&1 &
    SERVER_PID=$!
else
    echo "[ERROR] Neither 'frankenphp' nor 'php' CLI was found in your PATH."
    echo "Please install PHP 8.2+ or FrankenPHP (https://frankenphp.dev) to run ZimRx."
    exit 1
fi

sleep 1

# Detect local IP address for clinic Wi-Fi tablet access
LOCAL_IP=""
if command -v hostname >/dev/null 2>&1; then
    LOCAL_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
fi
if [ -z "$LOCAL_IP" ] && command -v ifconfig >/dev/null 2>&1; then
    LOCAL_IP="$(ifconfig 2>/dev/null | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -n 1)"
fi

echo ""
echo "=========================================="
echo "  System Ready!"
echo ""
echo "  Doctor / This Machine:"
echo "  http://localhost:$ZIMRX_PORT"
if [ -n "$LOCAL_IP" ]; then
    echo ""
    echo "  Assistant / Clinic Wi-Fi Device:"
    echo "  http://$LOCAL_IP:$ZIMRX_PORT"
fi
echo ""
echo "=========================================="
echo ""
echo "ZimRx is actively running. Press Ctrl+C in this terminal to stop."
echo ""

# Auto-open browser on desktop environments if available
if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "http://localhost:$ZIMRX_PORT" >/dev/null 2>&1 || true
elif command -v open >/dev/null 2>&1; then
    open "http://localhost:$ZIMRX_PORT" >/dev/null 2>&1 || true
fi

wait "$SERVER_PID"
