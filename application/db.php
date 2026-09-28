<?php
declare(strict_types=1);

/**
 * ZimRx Central Database Layer
 *
 * Configures the unified PDO connection manager and executes versioned
 * database migrations on application boot.
 */

// 1. Include Configuration and Database Subsystems
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db/db_connections.php';
require_once __DIR__ . '/db/db_schema.php';
require_once __DIR__ . '/db/db_sql.php';
require_once __DIR__ . '/db/db_migrator.php';

// 2. Initialize Database Connections
DbConnections::configure(DB_CONFIG);

/**
 * Central PDO instance for active clinic userdata
 */
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

/**
 * Get a PDO connection for a specific database path or context
 */
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

/**
 * Check if a table exists in the database
 */
function zimrx_db_table_exists(PDO $pdo, string $table): bool {
    return DbSchema::tableExists($pdo, $table);
}

/**
 * Active doctor count for multi-doctor tenancy awareness
 */
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

/**
 * Returns true if the clinic is operating in multi-doctor mode
 */
function zimrx_is_multi_doctor(PDO $pdo): bool {
    return zimrx_active_doctor_count($pdo) > 1;
}

/**
 * Sync interface settings from the database to cookies
 */
function zimrx_sync_interface_layout(PDO $pdo): void {
    if (!function_exists('current_user_doctor_id')) {
        return; // auth.php not loaded yet
    }
    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        return;
    }

    if (!DbSchema::tableExists($pdo, 'zimrx_interface_settings')) {
        return;
    }

    // Fetch dashboard settings for this doctor
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

        // If DB has a value and it differs from the cookie, override it
        if ($dbVal !== '' && $dbVal !== $cookieVal) {
            setcookie($cookieName, $dbVal, time() + 31536000, '/', '', false, false);
            $_COOKIE[$cookieName] = $dbVal; // In-memory sync for the current page request
        }
    }
}

// 3. Run automated versioned schema migrations on boot
if (!defined('ZIMRX_DB_LIGHTWEIGHT') && $pdo instanceof PDO) {
    try {
        (new DbMigrator())->run($pdo);
    } catch (Throwable $e) {
        error_log('[ZimRx] Migration failure: ' . $e->getMessage());
        http_response_code(503);
        exit('Database upgrade failed. Clinical operations are unavailable until migrations complete successfully.');
    }

    // Sync layout settings from DB to Cookies
    zimrx_sync_interface_layout($pdo);
}
