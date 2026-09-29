<?php
declare(strict_types=1);

// First launch onboarding save handler: provisions initial credentials, practice mode, and emergency recovery key.
// Unauthenticated by design — runs only during initial system setup before accounts exist.

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

// Helper to read/write app config
function app_config_get(PDO $pdo, string $key): string {
    $stmt = $pdo->prepare("SELECT config_value FROM zimrx_app_config WHERE config_key = :key LIMIT 1");
    $stmt->execute(['key' => $key]);
    return (string)($stmt->fetchColumn() ?? '');
}

function app_config_set(PDO $pdo, string $key, string $value): void {
    $pdo->prepare(
        "INSERT INTO zimrx_app_config (config_key, config_value, updated_at)
         VALUES (:key, :value, CURRENT_TIMESTAMP)
         ON CONFLICT(config_key) DO UPDATE SET config_value = :value, updated_at = CURRENT_TIMESTAMP"
    )->execute(['key' => $key, 'value' => $value]);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request.']);
        exit;
    }

    $practiceType = in_array($payload['practice_type'] ?? '', ['solo', 'multi'], true)
        ? $payload['practice_type']
        : 'solo';
    $installType = in_array($payload['install_type'] ?? '', ['local', 'server'], true)
        ? $payload['install_type']
        : 'local';
    $autoLogin    = ($payload['auto_login'] ?? false) ? '1' : '0';
    $email        = filter_var(trim($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $password     = trim($payload['password'] ?? '');
    $adminUser    = trim($payload['admin_username'] ?? 'doctor');

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLoopback = in_array($remoteAddr, ['127.0.0.1', '::1', ''], true)
        || str_starts_with($remoteAddr, '127.')
        || (isset($_SERVER['SERVER_ADDR']) && $remoteAddr === $_SERVER['SERVER_ADDR']);

    $setupTokenFile = ZIMRX_USERDATA_DIR . '/setup_token.txt';

    if (!$isLoopback) {
        if (!file_exists($setupTokenFile)) {
            $token = bin2hex(random_bytes(16));
            @file_put_contents($setupTokenFile, $token);
            @chmod($setupTokenFile, 0600);
        } else {
            $token = trim((string)@file_get_contents($setupTokenFile));
        }

        $providedToken = trim((string)($payload['setup_token'] ?? $_SERVER['HTTP_X_SETUP_TOKEN'] ?? ''));
        if ($providedToken === '' || !hash_equals($token, $providedToken)) {
            http_response_code(403);
            echo json_encode([
                'error' => 'Remote installation setup requires the initial security token located in userdata/setup_token.txt on the server.'
            ]);
            exit;
        }
    }

    if ($password === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Password is required.']);
        exit;
    }
    if (mb_strlen($password) < 14) {
        http_response_code(422);
        echo json_encode(['error' => 'Password must contain at least 14 characters.']);
        exit;
    }

    // Ensure setup_complete key exists
    $pdo->prepare(
        "INSERT INTO zimrx_app_config (config_key, config_value, updated_at)
         VALUES ('setup_complete', '0', CURRENT_TIMESTAMP)
         ON CONFLICT(config_key) DO NOTHING"
    )->execute();

    $pdo->beginTransaction();

    // Atomically claim setup state to prevent race conditions
    $claim = $pdo->prepare(
        "UPDATE zimrx_app_config
         SET config_value = 'initializing', updated_at = CURRENT_TIMESTAMP
         WHERE config_key = 'setup_complete'
           AND config_value = '0'"
    );
    $claim->execute();

    if ($claim->rowCount() !== 1) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(409);
        echo json_encode(['error' => 'Setup has already been claimed or completed.']);
        exit;
    }

    $passwordHash = zimrx_password_hash($password);

    if ($practiceType === 'solo') {
        // Update the default doctor account password
        $pdo->prepare(
            "UPDATE zimrx_user_accounts
             SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP
             WHERE role = 'doctor' AND doctor_id = 1"
        )->execute(['hash' => $passwordHash]);

        // Update email on doctor record
        if ($email !== '') {
            $pdo->prepare(
                "UPDATE zimrx_doctors SET email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = 1"
            )->execute(['email' => $email]);
        }
    } else {
        // Multi-doctor: create admin account
        $adminUsername = $adminUser !== '' ? $adminUser : 'admin';
        $existing = $pdo->prepare(
            "SELECT id FROM zimrx_user_accounts WHERE username = :u AND role = 'admin' LIMIT 1"
        );
        $existing->execute(['u' => $adminUsername]);
        if (!$existing->fetch()) {
            $pdo->prepare(
                "INSERT INTO zimrx_user_accounts (username, password_hash, display_name, role, doctor_id, is_active)
                 VALUES (:u, :hash, 'Administrator', 'admin', NULL, 1)"
            )->execute(['u' => $adminUsername, 'hash' => $passwordHash]);
        } else {
            $pdo->prepare(
                "UPDATE zimrx_user_accounts SET password_hash = :hash WHERE username = :u AND role = 'admin'"
            )->execute(['hash' => $passwordHash, 'u' => $adminUsername]);
        }
    }

    // Save app config
    app_config_set($pdo, 'practice_type', $practiceType);
    app_config_set($pdo, 'install_type', $installType);
    app_config_set($pdo, 'auto_login', $practiceType === 'solo' ? $autoLogin : '0');
    app_config_set($pdo, 'recovery_email', $email);
    app_config_set($pdo, 'setup_complete', '1');

    $pdo->commit();

    // Clean up one-time network setup token if it existed
    if (isset($setupTokenFile) && file_exists($setupTokenFile)) {
        @unlink($setupTokenFile);
    }

    // Generate recovery key and save to userdata/
    $recoveryKey = strtoupper(bin2hex(random_bytes(16)));
    $recoveryDir = defined('ZIMRX_USERDATA_DIR') ? ZIMRX_USERDATA_DIR : dirname(__DIR__) . '/userdata';
    if (!is_dir($recoveryDir) && !@mkdir($recoveryDir, 0750, true) && !is_dir($recoveryDir)) {
        throw new RuntimeException('Cannot create userdata directory.');
    }
    $recoveryPath = $recoveryDir . '/recovery.key';
    if (file_put_contents($recoveryPath, $recoveryKey, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write recovery key.');
    }
    @chmod($recoveryPath, 0600); // owner read-only — no group, no world

    // Start session and log in
    if ($practiceType === 'solo') {
        $stmt = $pdo->prepare(
            'SELECT id, display_name FROM zimrx_user_accounts WHERE role = :role AND doctor_id = :did LIMIT 1'
        );
        $stmt->execute([':role' => 'doctor', ':did' => 1]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new RuntimeException('Doctor account not found after first launch setup.');
        }
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['user_role'] = 'doctor';
        $_SESSION['user_name'] = $user['display_name'];
        $_SESSION['doctor_id'] = 1;
        echo json_encode(['ok' => true, 'redirect' => 'first_launch.php?step=3', 'recovery_key' => $recoveryKey]);
    } else {
        $adminUser = $pdo->prepare(
            "SELECT id, display_name FROM zimrx_user_accounts WHERE role = 'admin' LIMIT 1"
        );
        $adminUser->execute();
        $admin = $adminUser->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            throw new RuntimeException('Admin account not found after first launch setup.');
        }
        $_SESSION['user_id']   = (int)$admin['id'];
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_name'] = $admin['display_name'];
        $_SESSION['doctor_id'] = 0;
        echo json_encode(['ok' => true, 'redirect' => 'admin.php', 'recovery_key' => $recoveryKey]);
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ZimRx] first_launch_save error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while saving the initial configuration. Please try again.']);
}
