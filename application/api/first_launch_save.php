<?php
// No require_login() — this runs before any account exists
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
    // Guard: if already set up, reject
    if (app_config_get($pdo, 'setup_complete') === '1') {
        echo json_encode(['error' => 'Setup already complete.']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
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

    if ($password === '') {
        echo json_encode(['error' => 'Password is required.']);
        exit;
    }
    if (strlen($password) < 4) {
        echo json_encode(['error' => 'Password must be at least 4 characters.']);
        exit;
    }

    $passwordHash = zimrx_password_hash($password);
    $pdo->beginTransaction();

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

    // Generate recovery key and save to userdata/
    $recoveryKey = strtoupper(bin2hex(random_bytes(16)));
    $recoveryPath = __DIR__ . '/../../userdata/recovery.key';
    file_put_contents($recoveryPath, $recoveryKey);

    // Start session and log in
    if ($practiceType === 'solo') {
        $user = $pdo->query(
            "SELECT id, display_name FROM zimrx_user_accounts WHERE role = 'doctor' AND doctor_id = 1 LIMIT 1"
        )->fetch();
        if ($user) {
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_role'] = 'doctor';
            $_SESSION['user_name'] = $user['display_name'];
            $_SESSION['doctor_id'] = 1;
        }
        echo json_encode(['ok' => true, 'redirect' => 'first_launch.php?step=3', 'recovery_key' => $recoveryKey]);
    } else {
        $adminUser = $pdo->prepare(
            "SELECT id, display_name FROM zimrx_user_accounts WHERE role = 'admin' LIMIT 1"
        );
        $adminUser->execute();
        $admin = $adminUser->fetch();
        if ($admin) {
            $_SESSION['user_id']   = (int)$admin['id'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_name'] = $admin['display_name'];
            $_SESSION['doctor_id'] = 1;
        }
        echo json_encode(['ok' => true, 'redirect' => 'admin.php', 'recovery_key' => $recoveryKey]);
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
