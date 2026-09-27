<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (ini_get('session.use_cookies')) {
        $currentParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $currentParams['lifetime'] ?? 0,
            'path'     => '/',
            'domain'   => $currentParams['domain'] ?? '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

/**
 * Check if user is logged in
 */
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
    $doctorId = (int)($_SESSION['doctor_id'] ?? 1);
    return $doctorId > 0 ? $doctorId : 1;
}

function is_admin_user(): bool {
    return current_user_role() === 'admin';
}

/**
 * Hash a password using modern cryptographically secure password_hash (Bcrypt/Argon2id).
 */
function zimrx_password_hash(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a hash with backward-compatible legacy SHA-256 fallback.
 */
function zimrx_password_verify(string $password, string $storedHash): bool {
    if (password_verify($password, $storedHash)) {
        return true;
    }
    // Backward compatibility fallback for legacy unsalted SHA-256
    if (hash_equals($storedHash, hash('sha256', $password))) {
        return true;
    }
    return false;
}

/**
 * CSRF Protection Helpers
 */
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
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    if (!is_string($token) || $token === '') {
        return false;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

/**
 * Require login for a page
 */
function require_login() {
    if (!is_logged_in()) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $redirectParam = $currentUri ? '?redirect=' . urlencode($currentUri) : '';
        header("Location: index.php" . $redirectParam);
        exit();
    }

    // Never redirect API requests to HTML pages
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    if (strpos($script, '/api/') !== false) {
        return;
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
?>
