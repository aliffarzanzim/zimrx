<?php
declare(strict_types=1);

// Destroys the active session, clears cookies, and redirects to login.

require_once __DIR__ . '/init.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? true)
    );
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: index.php?logout=1');
exit();
