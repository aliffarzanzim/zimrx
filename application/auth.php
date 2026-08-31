<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
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

function zimrx_password_hash(string $password): string {
    return hash('sha256', $password);
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
