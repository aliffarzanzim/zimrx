<?php
declare(strict_types=1);

// Emergency offline password recovery using cryptographic master key.

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

// If already logged in, redirect to appropriate workspace
if (is_logged_in()) {
    header("Location: " . (current_user_role() === 'admin' ? 'admin.php' : (current_user_role() === 'assistant' ? 'appointments.php' : 'prescription.php')));
    exit();
}

$error = "";
$success = false;
$rotatedRecoveryKey = "";

$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
$ipHash = hash('sha256', $clientIp);
$attemptKey = 'recovery_attempts_' . $ipHash;
$lockoutKey = 'recovery_lockout_' . $ipHash;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $lockoutUntil = (int)($_SESSION[$lockoutKey] ?? 0);
    if ($lockoutUntil > time()) {
        $remainingSec = $lockoutUntil - time();
        $error = "Too many failed recovery attempts. Please wait {$remainingSec} seconds before trying again.";
    } else {
        $csrfToken       = $_POST['csrf_token'] ?? null;
        $username        = trim((string)($_POST['username'] ?? ''));
        $inputKeyRaw     = (string)($_POST['recovery_key'] ?? '');
        $inputKey        = strtoupper(trim(preg_replace('/[^a-zA-Z0-9]/', '', $inputKeyRaw)));
        $password        = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($csrfToken !== null && !zimrx_verify_csrf((string)$csrfToken)) {
            $error = "Session expired or invalid security token. Please refresh and try again.";
        } elseif ($username === '') {
            $error = "Please enter your username.";
        } elseif ($inputKey === '') {
            $error = "Please enter your 32-character Emergency Recovery Key.";
        } elseif (!zimrx_validate_password_strength($password)) {
            $error = "New password must be at least 8 characters.";
        } elseif ($password !== $confirmPassword) {
            $error = "Password confirmation does not match.";
        } else {
            $recoveryPath = ZIMRX_USERDATA_DIR . '/recovery.key';

            if (!file_exists($recoveryPath) || !is_readable($recoveryPath)) {
                $error = "Emergency recovery key file is missing on the server host.";
            } else {
                $savedKey = strtoupper(trim((string)@file_get_contents($recoveryPath)));

                if ($savedKey === '' || !hash_equals($savedKey, $inputKey)) {
                    $failedCount = ((int)($_SESSION[$attemptKey] ?? 0)) + 1;
                    $_SESSION[$attemptKey] = $failedCount;
                    if ($failedCount >= 5) {
                        $_SESSION[$lockoutKey] = time() + 600; // 10 minutes lockout
                        $_SESSION[$attemptKey] = 0;
                        $error = "Too many failed recovery attempts. System recovery locked for 10 minutes.";
                    } else {
                        $remaining = 5 - $failedCount;
                        $error = "Invalid recovery key or username. ({$remaining} attempts remaining before lockout)";
                    }
                    error_log("[ZimRx Security] Failed emergency recovery attempt #{$failedCount} for '{$username}' from {$clientIp}");
                } else {
                    $stmt = $pdo->prepare(
                        "SELECT id, username FROM zimrx_user_accounts WHERE username = :u AND is_active = 1 LIMIT 1"
                    );
                    $stmt->execute(['u' => $username]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        $error = "No active user account found with username '{$username}'.";
                    } else {
                        // Update password hash
                        $newHash = zimrx_password_hash($password);
                        $updateStmt = $pdo->prepare(
                            "UPDATE zimrx_user_accounts
                             SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP
                             WHERE id = :id"
                        );
                        $updateStmt->execute(['hash' => $newHash, 'id' => (int)$user['id']]);

                        // Rotate recovery key for forward secrecy
                        $newRecoveryKey = strtoupper(bin2hex(random_bytes(16)));
                        @file_put_contents($recoveryPath, $newRecoveryKey, LOCK_EX);
                        @chmod($recoveryPath, 0600);

                        // Clear lockout tracking
                        unset($_SESSION[$attemptKey], $_SESSION[$lockoutKey]);
                        session_regenerate_id(true);

                        error_log("[ZimRx Security] Emergency password reset completed for '{$username}' (ID: {$user['id']}) from {$clientIp}");

                        $success = true;
                        $rotatedRecoveryKey = $newRecoveryKey;
                    }
                }
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
    <title>ZimRx - Emergency Account Recovery</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/layout/global.css">
    <link rel="stylesheet" href="assets/css/layout/login.css?v=<?= filemtime(__DIR__ . '/assets/css/layout/login.css') ?>">
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <?= zrx_icon('shield', 48) ?>
            <h1>Emergency Recovery</h1>
            <p>Offline cryptographic password reset</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <strong>Password Successfully Reset!</strong><br>
                Your account password has been updated. As a security standard, your Emergency Recovery Key has been automatically rotated:
            </div>

            <div class="recovery-key-card" title="Click or select to copy"><?= htmlspecialchars($rotatedRecoveryKey) ?></div>

            <p style="font-size: 0.82rem; color: var(--zrx-text-muted); line-height: 1.5; margin-bottom: 1.25rem;">
                Please note this new key down safely. It has also been updated in <code>userdata/recovery.key</code> on your device.
            </p>

            <a href="index.php" class="btn btn-primary btn-login" style="display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">Proceed to Sign In</a>
        <?php else: ?>
            <div class="recovery-info-box">
                Enter your username, new password, and the 32-character Emergency Recovery Key generated at setup (stored locally in <code>userdata/recovery.key</code>).
            </div>

            <form method="POST">
                <?= zimrx_csrf_field() ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="login-input" placeholder="doctor" value="<?= htmlspecialchars($_POST['username'] ?? 'doctor') ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="recovery_key">Emergency Recovery Key</label>
                    <input type="text" id="recovery_key" name="recovery_key" class="login-input" placeholder="32-character hexadecimal key" style="font-family: monospace; letter-spacing: 0.05em;" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" class="login-input" placeholder="•••••••• (min 8 chars)" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="login-input" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary btn-login">Reset Password & Rotate Key</button>
            </form>

            <div style="margin-top: 1rem; margin-bottom: 0.25rem;">
                <a href="index.php" style="font-size: 0.85rem; color: var(--zrx-primary); text-decoration: none; font-weight: 500;">&larr; Return to Sign In</a>
            </div>
        <?php endif; ?>

        <div class="login-footer">
            Powered by ZimRx EMR System
        </div>
    </div>

</body>
</html>
