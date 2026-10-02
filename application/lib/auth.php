<?php
declare(strict_types=1);

// Authentication, session lifecycle, CSRF tokens, role routing, and SVG upload sanitizer.

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLoopback = in_array($remoteAddr, ['127.0.0.1', '::1', ''], true)
    || str_starts_with($remoteAddr, '127.')
    || (isset($_SERVER['SERVER_ADDR']) && $remoteAddr === $_SERVER['SERVER_ADDR']);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

// Enforce HTTPS encryption on non-local network/LAN connections to protect clinical data
if (php_sapi_name() !== 'cli' && !$isLoopback && !$isHttps) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>HTTPS Required</title><style>body{font-family:sans-serif;padding:2rem;text-align:center;color:#1e293b;background:#f8fafc;}h1{color:#dc2626;}p{max-width:600px;margin:1rem auto;line-height:1.6;}</style></head><body><h1>Security Requirement: HTTPS Required</h1><p>ZimRx manages sensitive medical and patient health data. Access over a local area network (LAN) or public network requires an encrypted HTTPS connection to safeguard session integrity and patient privacy.</p><p>Please access the system via <strong>localhost</strong> on this machine, or enable HTTPS (TLS/SSL) on your server.</p></body></html>';
    exit();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (ini_get('session.use_cookies')) {
        $currentParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $currentParams['lifetime'] ?? 0,
            'path'     => '/',
            'domain'   => $currentParams['domain'] ?? '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_role() {
    return strtolower((string)($_SESSION['user_role'] ?? ''));
}

function current_user_name() {
    return $_SESSION['user_name'] ?? 'User';
}

function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_user_doctor_id(): int {
    if (isset($_SESSION['doctor_id']) && (int)$_SESSION['doctor_id'] > 0) {
        return (int)$_SESSION['doctor_id'];
    }
    return 0;
}

function is_admin_user(): bool {
    return current_user_role() === 'admin';
}

// Password hashing and verification routines using native algorithms (bcrypt/Argon2)
function zimrx_password_hash(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

function zimrx_password_verify(string $password, string $storedHash): bool {
    return password_verify($password, $storedHash);
}

function zimrx_password_needs_rehash(string $storedHash): bool {
    return password_needs_rehash($storedHash, PASSWORD_DEFAULT);
}

// CSRF prevention using session-bound tokens
function zimrx_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function zimrx_csrf_field(): string {
    $token = htmlspecialchars(zimrx_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function zimrx_verify_csrf(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;
    }
    if (!is_string($token) || $token === '') {
        return false;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

// Access gatekeeper: redirects unauthenticated requests and restricts pages according to user role
function require_login() {
    if (!is_logged_in()) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        if (strpos($script, '/api/') !== false) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE);
            exit();
        }
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $redirectParam = $currentUri ? '?redirect=' . urlencode($currentUri) : '';
        header("Location: index.php" . $redirectParam);
        exit();
    }

    $page = basename($_SERVER['PHP_SELF'] ?? '');
    $role = current_user_role();
    $adminPages = [
        'admin.php', 'admin_doctors.php', 'admin_assistants.php',
        'admin_patients.php', 'admin_payments.php', 'admin_settings.php',
        'emr_settings.php',
        'logout.php',
    ];
    if ($role === 'admin' && !in_array($page, $adminPages, true)) {
        header("Location: admin.php");
        exit();
    }

    if ($role === 'assistant' && !in_array($page, ['appointments.php', 'logout.php'], true)) {
        header("Location: appointments.php");
        exit();
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin_user()) {
        header("Location: index.php");
        exit();
    }
}

// Inspect uploaded SVG files for embedded JavaScript, event handlers, or external entity injections (XXE)
function zimrx_validate_safe_svg(string $filePath): bool {
    $content = @file_get_contents($filePath);
    if ($content === false || strlen($content) < 10) {
        return false;
    }
    $disallowedPatterns = [
        '/<\s*script/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/data\s*:\s*text\/html/i',
        '/<!DOCTYPE/i',
        '/<!ENTITY/i',
        '/<\s*foreignObject/i',
        '/\bon[a-z]+\s*=/i',
        '/<\s*use\s+[^>]*href\s*=\s*["\'](?!#)/i',
    ];
    foreach ($disallowedPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return false;
        }
    }
    $prevErrors = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrors);
    return ($xml !== false && strtolower($xml->getName()) === 'svg');
}
