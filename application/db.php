<?php
declare(strict_types=1);

// Central database bootstrapper: loads PDO connection manager, doctor tenancy helpers, and runs pending migrations.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db/db_connections.php';
require_once __DIR__ . '/db/db_schema.php';
require_once __DIR__ . '/db/db_sql.php';
require_once __DIR__ . '/db/db_migrator.php';

DbConnections::configure(DB_CONFIG);

// Primary PDO handle for clinical userdata
$pdo = null;

if (!defined('ZIMRX_DB_LIGHTWEIGHT')) {
    try {
        $pdo = DbConnections::userdata();
    } catch (Throwable $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        exit((string)json_encode(["error" => "Database Connection failed: " . $e->getMessage()]));
    }
}

// Maps legacy file paths or connection names to their pooled PDO handle
function zimrx_get_pdo(string $dbPath): PDO {
    if (strpos($dbPath, 'zimrx_userdata') !== false) {
        return DbConnections::userdata();
    } elseif (strpos($dbPath, 'zimrx_static') !== false) {
        return DbConnections::staticDb();
    } elseif (strpos($dbPath, 'zimrx_drugs') !== false || strpos($dbPath, 'drug_data') !== false) {
        return DbConnections::systemDb();
    }
    return DbConnections::userdata();
}

function zimrx_db_table_exists(PDO $pdo, string $table): bool {
    return DbSchema::tableExists($pdo, $table);
}

// Counts active doctor accounts for multi-provider layout toggles
function zimrx_active_doctor_count(PDO $pdo): int {
    try {
        if (!DbSchema::tableExists($pdo, 'zimrx_doctors')) {
            return 1;
        }
        $stmt = $pdo->query("SELECT COUNT(*) FROM zimrx_doctors WHERE is_active = 1");
        return max(1, (int)($stmt->fetchColumn() ?: 1));
    } catch (Throwable $e) {
        return 1;
    }
}

function zimrx_is_multi_doctor(PDO $pdo): bool {
    return zimrx_active_doctor_count($pdo) > 1;
}

// Filter doctor selector based on access rights (admins see all, assistants see assigned doctors)
function zimrx_doctor_options_for_user(PDO $pdo, int $userId, string $role, int $doctorId = 1): array {
    $role = strtolower($role);
    if ($role === 'admin') {
        return $pdo->query(
            "SELECT id, doctor_code, display_name, qualifications_en AS qualifications, specialty_en AS specialty, is_active
             FROM zimrx_doctors
             WHERE is_active = 1
             ORDER BY display_name ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($role === 'assistant') {
        $stmt = $pdo->prepare(
            "SELECT d.id, d.doctor_code, d.display_name, d.qualifications_en AS qualifications, d.specialty_en AS specialty, d.is_active
             FROM zimrx_doctor_assistants da
             JOIN zimrx_doctors d ON d.id = da.doctor_id
             WHERE da.assistant_user_id = :assistant_user_id
               AND da.is_active = 1
               AND d.is_active = 1
             ORDER BY d.display_name ASC, d.id ASC"
        );
        $stmt->execute(['assistant_user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, doctor_code, display_name, qualifications_en AS qualifications, specialty_en AS specialty, is_active
         FROM zimrx_doctors
         WHERE id = :doctor_id
         LIMIT 1"
    );
    $stmt->execute(['doctor_id' => max(1, $doctorId)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Sync persistent dashboard panel layouts from the database into cookies for rapid client-side rendering
function zimrx_sync_interface_layout(PDO $pdo): void {
    if (!function_exists('current_user_doctor_id')) {
        return;
    }
    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        return;
    }

    if (!DbSchema::tableExists($pdo, 'zimrx_interface_settings')) {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT setting_key, setting_value 
         FROM zimrx_interface_settings 
         WHERE doctor_id = :doctor_id AND setting_scope = 'dashboard'"
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $keys = ['left_layout', 'right_layout', 'history_layout'];
    foreach ($keys as $key) {
        $cookieName = 'zimrx_' . $key;
        $dbVal = $dbSettings[$key] ?? '';
        $cookieVal = $_COOKIE[$cookieName] ?? '';

        if ($dbVal !== '' && $dbVal !== $cookieVal) {
            setcookie($cookieName, $dbVal, time() + 31536000, '/', '', false, false);
            $_COOKIE[$cookieName] = $dbVal;
        }
    }
}

// Run pending migrations unless intentionally running in lightweight mode
if (!defined('ZIMRX_DB_LIGHTWEIGHT') && $pdo instanceof PDO) {
    try {
        (new DbMigrator())->run($pdo);
    } catch (Throwable $e) {
        error_log('[ZimRx] Migration failure: ' . $e->getMessage());
        http_response_code(503);
        exit('Database upgrade failed. Clinical operations are unavailable until migrations complete successfully.');
    }

    zimrx_sync_interface_layout($pdo);
}
