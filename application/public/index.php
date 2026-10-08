<?php
declare(strict_types=1);

// User login gateway: handles credential verification, rate-limited lockouts, and role redirection.

require_once __DIR__ . '/init.php';

// First-launch gate
if (zimrx_db_table_exists($pdo, 'zimrx_app_config')) {
    $stmt = $pdo->query("SELECT config_key, config_value FROM zimrx_app_config");
    $appConfig = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
} else {
    $appConfig = [];
}

if (($appConfig['setup_complete'] ?? '0') !== '1') {
    header('Location: first_launch.php');
    exit();
}


$redirect = zimrx_sanitize_redirect((string)($_REQUEST['redirect'] ?? ''));

// If already logged in, redirect to appropriate page
if (is_logged_in()) {
    if ($redirect !== '') {
        header("Location: " . $redirect);
        exit();
    }
    header("Location: " . (current_user_role() === 'admin' ? 'admin.php' : (current_user_role() === 'assistant' ? 'appointments.php' : 'prescription.php')));
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $remainingSec = zimrx_check_login_rate_limit($clientIp);
    if ($remainingSec !== null) {
        $error = "Too many failed login attempts. Please wait {$remainingSec} seconds before trying again.";
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $csrfToken = $_POST['csrf_token'] ?? null;

        if ($csrfToken !== null && !zimrx_verify_csrf((string)$csrfToken)) {
            $error = "Session expired or invalid security token. Please refresh and try again.";
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, username, password_hash, display_name, role, doctor_id
                 FROM zimrx_user_accounts
                 WHERE username = :username AND is_active = 1
                 LIMIT 1"
            );
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && zimrx_password_verify($password, (string)$user['password_hash'])) {
                // Clear lockout counters on success
                zimrx_clear_login_rate_limit($clientIp);

                // Automatic upgrade: migrate legacy SHA-256 or weak hashes to modern PASSWORD_DEFAULT
                $currentHash = (string)$user['password_hash'];
                if (password_needs_rehash($currentHash, PASSWORD_DEFAULT) || !str_starts_with($currentHash, '$2y$')) {
                    try {
                        $upgradedHash = zimrx_password_hash($password);
                        $rehashStmt = $pdo->prepare(
                            "UPDATE zimrx_user_accounts
                             SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP
                             WHERE id = :id"
                        );
                        $rehashStmt->execute(['hash' => $upgradedHash, 'id' => (int)$user['id']]);
                    } catch (Throwable $e) {
                        error_log('[ZimRx] Password hash auto-upgrade error: ' . $e->getMessage());
                    }
                }

                // Prevent session fixation attack
                session_regenerate_id(true);

                $role = strtolower((string)$user['role']);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_role'] = $role;
                $_SESSION['user_name'] = $user['display_name'];
                $_SESSION['doctor_id'] = !empty($user['doctor_id']) ? (int)$user['doctor_id'] : 0;

                error_log("[ZimRx Security] Successful login for '{$username}' (ID: {$user['id']}, Role: {$role}) from {$clientIp}");

                if ($redirect !== '') {
                    header("Location: " . $redirect);
                    exit();
                }
                header("Location: " . ($role === 'admin' ? 'admin.php' : ($role === 'assistant' ? 'appointments.php' : 'prescription.php')));
                exit();
            } else {
                $attempts = zimrx_record_failed_login($clientIp);
                if ($attempts === 0) {
                    $error = "Too many failed login attempts. Please wait 60 seconds before trying again.";
                } else {
                    $error = "Invalid username or password.";
                }
                error_log("[ZimRx Security] Failed login attempt for '{$username}' from {$clientIp}");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZimRx - Physician Login</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/layout/global.css">
    <link rel="stylesheet" href="assets/css/layout/login.css?v=<?= filemtime(__DIR__ . '/assets/css/layout/login.css') ?>">
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
            </svg>
            <h1>ZimRx Login</h1>
            <p>Please enter your credentials</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= zimrx_csrf_field() ?>
            <?php if ($redirect !== ''): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="login-input" placeholder="doctor" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="login-input" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-login">Sign In</button>
        </form>

        <div style="margin-top: 0.75rem; margin-bottom: 0.25rem;">
            <a href="forgot_password.php" style="font-size: 0.85rem; color: var(--zrx-primary); text-decoration: none; font-weight: 500;">Forgot Password?</a>
        </div>

        <div class="login-footer">
            Powered by ZimRx EMR System
        </div>
    </div>

</body>
</html>
