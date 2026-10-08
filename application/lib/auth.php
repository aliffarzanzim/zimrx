<?php
declare(strict_types=1);

// Authentication, session lifecycle, CSRF tokens, role routing, and SVG upload sanitizer.

// Centralized loopback and container localhost detector
function zimrx_is_loopback_request(): bool {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($remoteAddr, ['127.0.0.1', '::1', ''], true)
        || str_starts_with($remoteAddr, '127.')
        || (isset($_SERVER['SERVER_ADDR']) && $remoteAddr === $_SERVER['SERVER_ADDR'])
    ) {
        return true;
    }

    if (in_array(getenv('ZIMRX_ALLOW_HTTP'), ['1', 'true', 'yes'], true)) {
        return true;
    }

    $httpHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?? '';
    $isContainer = file_exists('/.dockerenv') || getenv('ZIMRX_IN_DOCKER') !== false;
    if ($isContainer && in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    return false;
}

$isLoopback = zimrx_is_loopback_request();
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

function zimrx_is_setup_complete(): bool {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        try {
            $pdo = DbConnections::userdata();
        } catch (Throwable $e) {
            return false;
        }
    }
    if (!DbSchema::tableExists($pdo, 'zimrx_app_config')) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM zimrx_app_config WHERE config_key = 'setup_complete' LIMIT 1");
        $stmt->execute();
        return (string)($stmt->fetchColumn() ?: '0') === '1';
    } catch (Throwable $e) {
        return false;
    }
}

// Access gatekeeper: redirects unauthenticated requests and restricts pages according to user role
function require_login() {
    if (php_sapi_name() !== 'cli' && !zimrx_is_setup_complete()) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        if (strpos($script, '/api/') !== false) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'System setup is incomplete. Please complete initial setup first.'], JSON_UNESCAPED_UNICODE);
            exit();
        }
        header("Location: first_launch.php");
        exit();
    }

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
        'billings.php', 'emr.php', 'emr_settings.php',
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

// Password complexity policy: minimum 8 characters
function zimrx_validate_password_strength(string $password): bool {
    return mb_strlen($password) >= 8;
}

// Open-redirect protection: sanitizes target redirect URLs to internal relative paths only
function zimrx_sanitize_redirect(string $redirect): string {
    $redirect = trim($redirect);
    if ($redirect !== '') {
        if (str_starts_with($redirect, '//')
            || str_starts_with($redirect, '\\')
            || str_contains($redirect, '\\')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $redirect)
        ) {
            return '';
        }
    }
    return $redirect;
}

// Check remaining lockout duration (seconds) for IP, or null if not locked out
function zimrx_check_login_rate_limit(string $clientIp): ?int {
    $ipHash = hash('sha256', $clientIp);
    $lockoutKey = 'login_lockout_' . $ipHash;
    $lockoutUntil = (int)($_SESSION[$lockoutKey] ?? 0);
    if ($lockoutUntil > time()) {
        return $lockoutUntil - time();
    }
    return null;
}

// Record a failed login attempt; returns current attempt count or 0 if locked out
function zimrx_record_failed_login(string $clientIp, int $maxAttempts = 5, int $lockoutSeconds = 60): int {
    $ipHash = hash('sha256', $clientIp);
    $attemptKey = 'login_attempts_' . $ipHash;
    $lockoutKey = 'login_lockout_' . $ipHash;
    $count = ((int)($_SESSION[$attemptKey] ?? 0)) + 1;
    if ($count >= $maxAttempts) {
        $_SESSION[$lockoutKey] = time() + $lockoutSeconds;
        $_SESSION[$attemptKey] = 0;
        return 0;
    }
    $_SESSION[$attemptKey] = $count;
    return $count;
}

// Clear rate limit counters upon successful authentication
function zimrx_clear_login_rate_limit(string $clientIp): void {
    $ipHash = hash('sha256', $clientIp);
    unset($_SESSION['login_attempts_' . $ipHash], $_SESSION['login_lockout_' . $ipHash]);
}

// Extract doctor ID from report filename (e.g. report-1-1780242361.pdf -> 1)
function zimrx_parse_report_doctor_id(string $filename): int {
    if (preg_match('/^report-(\d+)-/', $filename, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

// Verify that a patient and optional visit belong to the authenticated doctor
function zimrx_verify_patient_ownership(PDO $pdo, int $doctorId, int $patientId, int $visitRecordId = 0): bool {
    if ($patientId === 0 && $visitRecordId === 0) {
        return true; // Walk-in is always permitted
    }
    if ($visitRecordId > 0) {
        $chk = $pdo->prepare(
            "SELECT v.id
             FROM zimrx_visits v
             JOIN zimrx_patients p ON p.id = v.patient_id
             WHERE v.id = :vid AND v.doctor_id = :did
               AND (:pid = 0 OR v.patient_id = :pid)
             LIMIT 1"
        );
        $chk->execute(['vid' => $visitRecordId, 'did' => $doctorId, 'pid' => $patientId]);
        return (bool)$chk->fetch();
    }
    $chk = $pdo->prepare(
        "SELECT id FROM zimrx_patients
         WHERE id = :pid
           AND (COALESCE(NULLIF(doctor_id, 0), 1) = :did
                OR EXISTS (
                    SELECT 1 FROM zimrx_patient_doctor_access
                    WHERE patient_id = :pid AND doctor_id = :did AND can_view = 1
                ))
         LIMIT 1"
    );
    $chk->execute(['pid' => $patientId, 'did' => $doctorId]);
    return (bool)$chk->fetch();
}

