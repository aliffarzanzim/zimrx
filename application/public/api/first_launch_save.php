<?php
declare(strict_types=1);

// First launch onboarding save handler: provisions initial credentials, practice mode, and emergency recovery key.
// Unauthenticated by design - runs only during initial system setup before accounts exist.

require_once dirname(__DIR__) . '/init.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/CountryService.php';

use ZimRx\Services\CountryService;

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

    $practiceType = in_array($payload['practice_type'] ?? '', ['solo', 'multi', 'humanitarian'], true)
        ? $payload['practice_type']
        : 'solo';
    $installType = in_array($payload['install_type'] ?? '', ['local', 'server'], true)
        ? $payload['install_type']
        : 'local';
    $country = preg_replace('/[^A-Z0-9_-]/', '', strtoupper(trim((string)($payload['country'] ?? 'INT'))));
    if ($country === '') {
        $country = 'INT';
    }
    $hasFormulary = CountryService::hasFormulary($country);
    $rawPrescribingMode = trim((string)($payload['prescribing_mode'] ?? ''));
    $prescribingMode = (in_array($rawPrescribingMode, ['brand', 'dual'], true) && $hasFormulary) ? 'brand' : 'generic';
    $clinicalLang = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string)($payload['clinical_lang'] ?? 'en'))));
    if ($clinicalLang === '') {
        $clinicalLang = 'en';
    }
    $interfaceLang = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string)($payload['interface_lang'] ?? 'en'))));
    if ($interfaceLang === '') {
        $interfaceLang = 'en';
    }
    $helpLang = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string)($payload['help_lang'] ?? ''))));
    if ($helpLang === '') {
        $helpLang = $clinicalLang;
    }
    $email        = filter_var(trim($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $password     = trim($payload['password'] ?? '');
    $username     = trim((string)($payload['username'] ?? $payload['admin_username'] ?? ''));

    $isLoopback = zimrx_is_loopback_request();

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

    $isSoloMode = in_array($practiceType, ['solo', 'humanitarian'], true);

    if ($username === '') {
        $username = $isSoloMode ? 'doctor' : 'admin';
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]{2,50}$/', $username)) {
        http_response_code(422);
        echo json_encode(['error' => 'Username must be between 2 and 50 characters (letters, numbers, underscores, dots, or hyphens).']);
        exit;
    }

    $hasExistingPassword = false;
    $existingPasswordHash = '';
    if (zimrx_db_table_exists($pdo, 'zimrx_user_accounts')) {
        if ($isSoloMode) {
            $existing = $pdo->prepare("SELECT id, display_name, password_hash FROM zimrx_user_accounts WHERE doctor_id = 1 LIMIT 1");
            $existing->execute();
            $account = $existing->fetch(PDO::FETCH_ASSOC);
            if ($account && !empty($account['password_hash'])) {
                $hasExistingPassword = true;
                $existingPasswordHash = (string)$account['password_hash'];
            }
        } else {
            $existing = $pdo->prepare("SELECT id, display_name, password_hash FROM zimrx_user_accounts WHERE username = :u OR role IN ('admin', 'superadmin') ORDER BY id ASC LIMIT 1");
            $existing->execute(['u' => $username]);
            $account = $existing->fetch(PDO::FETCH_ASSOC);
            if ($account && !empty($account['password_hash'])) {
                $hasExistingPassword = true;
                $existingPasswordHash = (string)$account['password_hash'];
            }
        }
    }

    if ($password === '') {
        if (!$hasExistingPassword) {
            http_response_code(422);
            echo json_encode(['error' => 'Password is required.']);
            exit;
        }
        $passwordHash = $existingPasswordHash;
    } else {
        if (!zimrx_validate_password_strength($password)) {
            http_response_code(422);
            echo json_encode(['error' => 'Password must contain at least 8 characters.']);
            exit;
        }
        $passwordHash = zimrx_password_hash($password);
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

    if ($isSoloMode) {
        // Find existing account for doctor_id = 1 (or any account if seeded as root/superadmin)
        $existing = $pdo->prepare("SELECT id, display_name FROM zimrx_user_accounts WHERE doctor_id = 1 LIMIT 1");
        $existing->execute();
        $account = $existing->fetch(PDO::FETCH_ASSOC);

        if ($account) {
            $userId = (int)$account['id'];
            $displayName = (!empty($account['display_name']) && $account['display_name'] !== 'Root Admin')
                ? (string)$account['display_name']
                : 'Doctor';
            $pdo->prepare(
                "UPDATE zimrx_user_accounts
                 SET username = :username, password_hash = :hash, role = 'doctor', display_name = :dname, is_active = 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            )->execute([
                'username' => $username,
                'hash'     => $passwordHash,
                'dname'    => $displayName,
                'id'       => $userId,
            ]);
        } else {
            $pdo->prepare(
                "INSERT INTO zimrx_user_accounts (username, password_hash, display_name, role, doctor_id, is_active)
                 VALUES (:username, :hash, 'Doctor', 'doctor', 1, 1)"
            )->execute(['username' => $username, 'hash' => $passwordHash]);
            $userId = (int)$pdo->lastInsertId();
            $displayName = 'Doctor';
        }

        // Update email on doctor record
        if ($email !== '') {
            $pdo->prepare(
                "UPDATE zimrx_doctors SET email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = 1"
            )->execute(['email' => $email]);
        }

        $sessionRole = 'doctor';
        $sessionDoctorId = 1;
        $redirectUrl = 'first_launch.php?step=4&lang=' . urlencode($interfaceLang)
            . '&country=' . urlencode($country)
            . '&prescribing_mode=' . urlencode($prescribingMode)
            . '&clinical_lang=' . urlencode($clinicalLang)
            . '&help_lang=' . urlencode($helpLang);
    } else {
        // Multi-doctor: create or update admin account
        $adminUsername = $username;
        $existing = $pdo->prepare(
            "SELECT id, display_name FROM zimrx_user_accounts WHERE username = :u OR role IN ('admin', 'superadmin') ORDER BY id ASC LIMIT 1"
        );
        $existing->execute(['u' => $adminUsername]);
        $account = $existing->fetch(PDO::FETCH_ASSOC);

        if ($account) {
            $userId = (int)$account['id'];
            $displayName = 'Administrator';
            $pdo->prepare(
                "UPDATE zimrx_user_accounts
                 SET username = :username, password_hash = :hash, role = 'admin', display_name = 'Administrator', is_active = 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            )->execute([
                'username' => $adminUsername,
                'hash'     => $passwordHash,
                'id'       => $userId,
            ]);
        } else {
            $pdo->prepare(
                "INSERT INTO zimrx_user_accounts (username, password_hash, display_name, role, doctor_id, is_active)
                 VALUES (:u, :hash, 'Administrator', 'admin', NULL, 1)"
            )->execute(['u' => $adminUsername, 'hash' => $passwordHash]);
            $userId = (int)$pdo->lastInsertId();
            $displayName = 'Administrator';
        }

        $sessionRole = 'admin';
        $sessionDoctorId = 0;
        $redirectUrl = 'admin.php';
    }

    // Save app config
    app_config_set($pdo, 'practice_type', $practiceType);
    app_config_set($pdo, 'practice_country', $country);
    app_config_set($pdo, 'prescribing_mode', $prescribingMode);
    app_config_set($pdo, 'clinical_lang', $clinicalLang);
    app_config_set($pdo, 'help_lang', $helpLang);
    app_config_set($pdo, 'interface_lang', $interfaceLang);
    app_config_set($pdo, 'install_type', $installType);
    app_config_set($pdo, 'auto_login', '0');
    app_config_set($pdo, 'recovery_email', $email);
    // Solo practice finalizes setup after Step 4 (save or skip); multi-doctor finalizes immediately
    $isComplete = ($practiceType === 'multi') ? '1' : '0';
    app_config_set($pdo, 'setup_complete', $isComplete);

    $formularyPack = CountryService::getFormulary($country);
    if ($formularyPack && !empty($formularyPack['currency'])) {
        app_config_set($pdo, 'practice_currency', (string)$formularyPack['currency']);
    }

    if ($sessionDoctorId > 0 && zimrx_db_table_exists($pdo, 'zimrx_interface_settings')) {
        $pdo->prepare("
            INSERT INTO zimrx_interface_settings (doctor_id, setting_scope, setting_key, setting_value, updated_at)
            VALUES (:doc_id, 'prescribing', 'prescribing_mode', :mode, CURRENT_TIMESTAMP)
            ON CONFLICT(doctor_id, setting_scope, setting_key) DO UPDATE SET
                setting_value = :mode, updated_at = CURRENT_TIMESTAMP
        ")->execute(['doc_id' => $sessionDoctorId, 'mode' => $prescribingMode]);
    }

    if ($sessionDoctorId > 0 && zimrx_db_table_exists($pdo, 'zimrx_prescription_header_settings')) {
        $onboardPath = ZIMRX_BASE_DIR . '/config/onboarding_defaults.php';
        $allDefs = is_file($onboardPath) ? require $onboardPath : [];
        $onboardDefs = ($country !== '' && isset($allDefs[$country]))
            ? $allDefs[$country]
            : ($allDefs['INT'] ?? ($allDefs['BD'] ?? []));
        $defaultFooter = (string)($onboardDefs['footer_html'] ?? '');
        if ($defaultFooter !== '') {
            $pdo->prepare("
                INSERT INTO zimrx_prescription_header_settings (doctor_id, doctor_name, footer_html)
                VALUES (:doc_id, :dname, :footer)
                ON CONFLICT(doctor_id) DO UPDATE SET
                    footer_html = CASE WHEN footer_html IS NULL OR trim(footer_html) = '' THEN :footer ELSE footer_html END
            ")->execute(['doc_id' => $sessionDoctorId, 'dname' => $displayName, 'footer' => $defaultFooter]);
        }
    }

    $pdo->commit();

    // Dynamically compile regional clinical reference databases based on clinician selection
    \ZimRx\Db\DbConnections::ensureReferenceDatabaseExists(ZIMRX_DB_SYSTEMDATA, $country);
    \ZimRx\Db\DbConnections::ensureReferenceDatabaseExists(ZIMRX_DB_STATIC, $country);

    // Clean up one-time network setup token if it existed
    if (isset($setupTokenFile) && file_exists($setupTokenFile)) {
        @unlink($setupTokenFile);
    }

    // Read or generate recovery key and save to userdata/
    $recoveryDir = defined('ZIMRX_USERDATA_DIR') ? ZIMRX_USERDATA_DIR : dirname(__DIR__) . '/userdata';
    if (!is_dir($recoveryDir) && !@mkdir($recoveryDir, 0750, true) && !is_dir($recoveryDir)) {
        throw new RuntimeException('Cannot create userdata directory.');
    }
    $recoveryPath = $recoveryDir . '/recovery.key';
    if (file_exists($recoveryPath) && filesize($recoveryPath) > 0) {
        $recoveryKey = trim((string)file_get_contents($recoveryPath));
    } else {
        $recoveryKey = strtoupper(bin2hex(random_bytes(16)));
        if (file_put_contents($recoveryPath, $recoveryKey, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write recovery key.');
        }
        @chmod($recoveryPath, 0600); // owner read-only - no group, no world
    }

    $_SESSION['lang'] = $interfaceLang;
    $_SESSION['setup_lang'] = $interfaceLang;
    $_SESSION['help_lang'] = $helpLang;
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $sessionRole;
    $_SESSION['user_name'] = $displayName;
    $_SESSION['doctor_id'] = $sessionDoctorId;

    echo json_encode([
        'ok' => true,
        'redirect' => $redirectUrl,
        'recovery_key' => $recoveryKey,
        'help_lang' => $helpLang
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ZimRx] first_launch_save error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while saving the initial configuration. Please try again.']);
}
