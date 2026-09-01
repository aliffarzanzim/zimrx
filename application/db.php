<?php
// application/db.php

/**
 * ZimRx Database Layer
 *
 * This file provides backward-compatible database access through:
 * 1. DbConnections class: New unified database manager (recommended)
 * 2. Global $pdo variable: Legacy compatibility
 * 3. zimrx_get_pdo() function: Legacy compatibility
 *
 * All access ultimately goes through DbConnections which supports
 * SQLite, MySQL, MariaDB, PostgreSQL, and other databases.
 *
 * Database engine can be changed in config.php without modifying this file.
 */

// 1. Include Configuration and Database Manager
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DbConnections.php';
require_once __DIR__ . '/DbSchema.php';
require_once __DIR__ . '/DbSql.php';
require_once __DIR__ . '/DbMigrator.php';

// 2. Initialize DbConnections with configuration
DbConnections::configure(DB_CONFIG);

// =====================================================================
// LEGACY COMPATIBILITY LAYER
// =====================================================================
// These functions maintain backward compatibility with existing code.
// New code should use DbConnections directly.

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;

// Include Composer Autoloader (for Doctrine DBAL / other Composer packages).
// Prefer application/vendor so the application folder can be moved by itself.
function zimrx_composer_autoload_path(): ?string {
    $candidates = [
        getenv('ZIMRX_COMPOSER_AUTOLOAD') ?: null,
        __DIR__ . '/vendor/autoload.php',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate && file_exists($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$autoload_path = zimrx_composer_autoload_path();
if ($autoload_path !== null) {
    require_once $autoload_path;
}

/**
 * [LEGACY] Get a Doctrine DBAL Connection (if available)
 *
 * @deprecated Use DbConnections::userdata() instead
 */
function zimrx_get_db_conn(string $dbPath): Connection {
    static $connections = [];
    $key = realpath($dbPath) ?: $dbPath;

    if (!isset($connections[$key])) {
        $connections[$key] = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path'   => $dbPath,
        ]);

        $nativePdo = $connections[$key]->getNativeConnection();
        if ($nativePdo instanceof PDO) {
            $nativePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $nativePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $nativePdo->exec('PRAGMA journal_mode = WAL;');
            $nativePdo->exec('PRAGMA synchronous = NORMAL;');
            $nativePdo->exec('PRAGMA foreign_keys = ON;');
        }
    }

    return $connections[$key];
}

/**
 * [LEGACY] Get a native PDO connection for a specific database path
 *
 * This function now delegates to DbConnections but maintains
 * backward compatibility by detecting the database type.
 *
 * @deprecated Use DbConnections::userdata(), DbConnections::staticDb(), etc.
 */
function zimrx_get_pdo(string $dbPath): PDO {
    $samePath = static function (string $left, string $right): bool {
        $leftPath = realpath($left) ?: $left;
        $rightPath = realpath($right) ?: $right;
        $normalize = static function (string $path): string {
            return strtolower(str_replace('\\', '/', $path));
        };

        return $normalize($leftPath) === $normalize($rightPath);
    };

    // Map legacy paths to new DbConnections methods
    if ($samePath($dbPath, ZIMRX_DB_USERDATA) || strpos($dbPath, 'zimrx_userdata') !== false) {
        return DbConnections::userdata();
    } elseif ($samePath($dbPath, ZIMRX_DB_STATIC) || strpos($dbPath, 'zimrx_static') !== false) {
        return DbConnections::staticDb();
    } elseif ($samePath($dbPath, ZIMRX_DB_SYSTEMDATA)
        || strpos($dbPath, 'zimrx_drugs') !== false
        || strpos($dbPath, 'drug_data') !== false
    ) {
        return DbConnections::systemDb();
    } else {
        // For completely custom paths, try Doctrine DBAL if available
        if (class_exists('Doctrine\DBAL\DriverManager')) {
            return zimrx_get_db_conn($dbPath)->getNativeConnection();
        }
        throw new RuntimeException("Cannot determine database connection for path: $dbPath");
    }
}

/**
 * [LEGACY] Global PDO for backward compatibility
 *
 * @deprecated New code should use DbConnections::userdata() directly
 * For accessing specific database, use:
 *   - DbConnections::userdata()  // User data
 *   - DbConnections::staticDb()  // Static lookups
 *   - DbConnections::systemDb()  // System data
 */
$conn = null;  // Doctrine DBAL connection (legacy)
$pdo = null;   // PDO connection (legacy)

// Initialize legacy $pdo variable if not in lightweight mode
if (!defined('ZIMRX_DB_LIGHTWEIGHT')) {
    try {
        $pdo = DbConnections::userdata();
        // Also set up Doctrine connection if available
        if (class_exists('Doctrine\DBAL\DriverManager') && DB_DRIVER === 'sqlite') {
            $conn = zimrx_get_db_conn(ZIMRX_DB_USERDATA);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        die(json_encode(["error" => "Database Connection failed: " . $e->getMessage()]));
    }
}

// =====================================================================
// DATABASE HELPER FUNCTIONS (Universal)
// =====================================================================

function zimrx_db_table_exists(PDO $pdo, string $table): bool {
    return DbSchema::tableExists($pdo, $table);
}

function zimrx_db_column_exists(PDO $pdo, string $table, string $column): bool {
    return DbSchema::columnExists($pdo, $table, $column);
}

function zimrx_db_table_sql(PDO $pdo, string $table): string {
    return DbSchema::createTableSql($pdo, $table);
}

function zimrx_db_ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (
        zimrx_db_table_exists($pdo, $table)
        && !DbSchema::isView($pdo, $table)
        && !zimrx_db_column_exists($pdo, $table, $column)
    ) {
        $pdo->exec("ALTER TABLE \"$table\" ADD COLUMN \"$column\" $definition");
    }
}

function zimrx_db_backfill_doctor_id(PDO $pdo, string $table, int $doctorId = 1): void {
    if (zimrx_db_column_exists($pdo, $table, 'doctor_id')) {
        $pdo->exec("UPDATE \"$table\" SET doctor_id = $doctorId WHERE doctor_id IS NULL OR doctor_id <= 0");
    }
}

function zimrx_db_normalize_doctor_appointment_serials(PDO $pdo): void {
    if (!zimrx_db_table_exists($pdo, 'zimrx_appointments') || !zimrx_db_column_exists($pdo, 'zimrx_appointments', 'doctor_id')) {
        return;
    }

    $rows = $pdo->query(
        "SELECT doctor_id, appointment_date, appointment_no, group_concat(id) AS ids, count(*) AS total
         FROM zimrx_appointments
         GROUP BY doctor_id, appointment_date, appointment_no
         HAVING total > 1"
    )->fetchAll();

    foreach ($rows as $row) {
        $ids = array_map('intval', explode(',', (string)$row['ids']));
        sort($ids);
        array_shift($ids);
        foreach ($ids as $id) {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(MAX(appointment_no), 0) + 1
                 FROM zimrx_appointments
                 WHERE doctor_id = :doctor_id AND appointment_date = :appointment_date"
            );
            $stmt->execute([
                'doctor_id' => (int)$row['doctor_id'],
                'appointment_date' => (string)$row['appointment_date'],
            ]);
            $nextNo = max(1, (int)$stmt->fetchColumn());
            $update = $pdo->prepare(
                "UPDATE zimrx_appointments
                 SET appointment_no = :appointment_no,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $update->execute(['appointment_no' => $nextNo, 'id' => $id]);
        }
    }
}

function zimrx_db_rebuild_doctor_singleton_table(PDO $pdo, string $table, string $createSql, array $columns, array $defaults = []): void {
    if (!zimrx_db_table_exists($pdo, $table)) {
        $pdo->exec($createSql);
        return;
    }

    $tableSql = zimrx_db_table_sql($pdo, $table);
    $needsRebuild = !zimrx_db_column_exists($pdo, $table, 'doctor_id')
        || stripos($tableSql, 'CHECK (id = 1)') !== false
        || stripos($tableSql, 'CHECK(id=1)') !== false;
    if (!$needsRebuild) {
        return;
    }

    $backup = $table . '_legacy_doctor_scope';
    $legacyColumns = [];
    foreach (DbSchema::columnInfo($pdo, $table) as $row) {
        $legacyColumns[(string)$row['name']] = true;
    }

    $selectExpressions = [];
    foreach ($columns as $column) {
        if ($column === 'doctor_id') {
            $selectExpressions[] = isset($legacyColumns['doctor_id'])
                ? "COALESCE(NULLIF(doctor_id, 0), 1)"
                : "1";
            continue;
        }
        if (isset($legacyColumns[$column])) {
            $selectExpressions[] = "\"$column\"";
            continue;
        }
        $selectExpressions[] = $defaults[$column] ?? "NULL";
    }

    $columnSql = '"' . implode('", "', $columns) . '"';
    $selectSql = implode(', ', $selectExpressions);

    $pdo->beginTransaction();
    try {
        $pdo->exec("DROP TABLE IF EXISTS \"$backup\"");
        $pdo->exec("ALTER TABLE \"$table\" RENAME TO \"$backup\"");
        $pdo->exec($createSql);
        $pdo->exec("INSERT INTO \"$table\" ($columnSql) SELECT $selectSql FROM \"$backup\"");
        $pdo->exec("DROP TABLE \"$backup\"");
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function zimrx_db_ensure_pc_schema(PDO $pdo): void {
    $createSql = "CREATE TABLE zimrx_user_pc (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL DEFAULT 1,
        category TEXT NOT NULL,
        term TEXT NOT NULL,
        usage_count INTEGER NOT NULL DEFAULT 0,
        is_pinned INTEGER NOT NULL DEFAULT 0,
        is_hidden INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        source TEXT DEFAULT 'user',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(doctor_id, category, term)
    )";

    if (!zimrx_db_table_exists($pdo, 'zimrx_user_pc')) {
        $pdo->exec($createSql);
    }

    zimrx_db_ensure_column($pdo, 'zimrx_user_pc', 'doctor_id', 'INTEGER NOT NULL DEFAULT 1');
    zimrx_db_ensure_column($pdo, 'zimrx_user_pc', 'usage_count', 'INTEGER NOT NULL DEFAULT 0');
    zimrx_db_ensure_column($pdo, 'zimrx_user_pc', 'is_pinned', 'INTEGER NOT NULL DEFAULT 0');
    zimrx_db_ensure_column($pdo, 'zimrx_user_pc', 'is_hidden', 'INTEGER NOT NULL DEFAULT 0');
    zimrx_db_ensure_column($pdo, 'zimrx_user_pc', 'sort_order', 'INTEGER NOT NULL DEFAULT 0');
    zimrx_db_ensure_column($pdo, 'zimrx_user_pc', 'source', "TEXT NOT NULL DEFAULT 'user'");
    zimrx_db_backfill_doctor_id($pdo, 'zimrx_user_pc');
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_pc_category_term ON zimrx_user_pc(doctor_id, category, term)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_pc_category_usage ON zimrx_user_pc(doctor_id, category, usage_count DESC, updated_at DESC, id DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_pc_category_recent ON zimrx_user_pc(doctor_id, category, updated_at DESC, id DESC)");
}

function zimrx_pc_priority_defaults(): array {
    return [
        ['source' => 'most_used', 'sort_order' => 1, 'is_enabled' => 1],
        ['source' => 'custom', 'sort_order' => 2, 'is_enabled' => 1],
        ['source' => 'static_pc', 'sort_order' => 3, 'is_enabled' => 1],
        ['source' => 'icd', 'sort_order' => 4, 'is_enabled' => 1],
    ];
}

function zimrx_db_ensure_pc_settings_schema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_user_pc_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            setting_key TEXT NOT NULL,
            setting_value TEXT,
            source TEXT NOT NULL DEFAULT '',
            term TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_enabled INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id, setting_key, source, term)
        )"
    );
    zimrx_db_backfill_doctor_id($pdo, 'zimrx_user_pc_settings');
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_pc_settings_doctor_key ON zimrx_user_pc_settings(doctor_id, setting_key, source, term)");

    // Migrate any legacy 'snomed' source references to 'static_pc'
    try {
        $pdo->exec("UPDATE zimrx_user_pc_settings SET source = 'static_pc' WHERE source = 'snomed'");
    } catch (Throwable $_) {}
}

function zimrx_db_ensure_medical_history_settings_schema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_user_medical_history_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            category VARCHAR(100) NOT NULL,
            condition_key VARCHAR(100) NOT NULL,
            display_label VARCHAR(150) NOT NULL,
            full_name VARCHAR(255) NOT NULL DEFAULT '',
            field_type VARCHAR(50) NOT NULL DEFAULT 'none',
            dropdown_options TEXT DEFAULT '',
            placeholder TEXT DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 10,
            is_custom INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id, condition_key)
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_med_history_doc_active ON zimrx_user_medical_history_settings(doctor_id, is_active, sort_order)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_med_history_doc_cat ON zimrx_user_medical_history_settings(doctor_id, category)");
}

function zimrx_db_ensure_physical_examination_settings_schema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_user_physical_examination_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            system VARCHAR(50) NOT NULL,
            category VARCHAR(100) NOT NULL,
            item_code VARCHAR(100) NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            full_name VARCHAR(255) NOT NULL DEFAULT '',
            input_type VARCHAR(50) NOT NULL DEFAULT 'dropdown+textbox',
            delimiter VARCHAR(10) DEFAULT '',
            default_unit VARCHAR(30) DEFAULT '',
            normal_value TEXT DEFAULT '',
            dropdown_options TEXT DEFAULT '',
            finding_wordlists TEXT DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 10,
            is_custom INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id, item_code)
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_pe_doc_active ON zimrx_user_physical_examination_settings(doctor_id, is_active, sort_order)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_pe_doc_sys ON zimrx_user_physical_examination_settings(doctor_id, system)");
}

function zimrx_db_ensure_pc_source_priority_rows(PDO $pdo, int $doctorId): void {
    zimrx_db_ensure_pc_settings_schema($pdo);
    foreach (zimrx_pc_priority_defaults() as $row) {
        $stmt = $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_user_pc_settings',
                'doctor_id, setting_key, source, term, sort_order, is_enabled, created_at, updated_at',
                ':doctor_id, :setting_key, :source, :term, :sort_order, :is_enabled, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP'
            )
        );
        $stmt->execute([
            'doctor_id'  => max(1, $doctorId),
            'setting_key' => 'source_priority',
            'source'     => $row['source'],
            'term'       => '',
            'sort_order' => $row['sort_order'],
            'is_enabled' => $row['is_enabled'],
        ]);
    }
}

function zimrx_ensure_doctor_scope(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS doctors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_code TEXT UNIQUE,
            display_name TEXT NOT NULL,
            qualifications TEXT,
            specialty TEXT,
            bmdc_no TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $doctorName = 'Doctor';
    if (zimrx_db_table_exists($pdo, 'zimrx_user_accounts')) {
        $row = $pdo->query("SELECT display_name FROM zimrx_user_accounts WHERE role = 'doctor' ORDER BY id ASC LIMIT 1")->fetch();
        if ($row && trim((string)$row['display_name']) !== '') {
            $doctorName = trim((string)$row['display_name']);
        }
    }

    $stmt = $pdo->prepare(
        DbSql::insertIgnore('zimrx_doctors', 'id, doctor_code, display_name', "1, 'D001', :display_name")
    );
    $stmt->execute(['display_name' => $doctorName]);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_user_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            display_name TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'doctor',
            doctor_id INTEGER NOT NULL DEFAULT 1,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    zimrx_db_ensure_column($pdo, 'zimrx_user_accounts', 'doctor_id', 'INTEGER NOT NULL DEFAULT 1');
    zimrx_db_backfill_doctor_id($pdo, 'zimrx_user_accounts');
    $pdo->prepare(
        DbSql::insertIgnore(
            'zimrx_user_accounts',
            'username, password_hash, display_name, role, doctor_id, is_active',
            "'root', :password_hash, 'Root Admin', 'admin', 1, 1"
        )
    )->execute(['password_hash' => hash('sha256', '123')]);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_doctor_assistants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            assistant_user_id INTEGER NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id, assistant_user_id)
        )"
    );
    $pdo->exec(
        DbSql::insertIgnoreSelect(
            'zimrx_doctor_assistants',
            'doctor_id, assistant_user_id, is_active',
            "SELECT COALESCE(NULLIF(doctor_id, 0), 1), id, 1
         FROM zimrx_user_accounts
         WHERE role = 'assistant'"
        )
    );

    $doctorScopedTables = [
        'zimrx_patients',
        'zimrx_appointments',
        'zimrx_visits',
        'zimrx_visit_revisions',
        'zimrx_prescription_clinical_findings',
        'zimrx_user_pc',
        'zimrx_user_drugs',
        'zimrx_drug_template',
        'zimrx_prescription_template',
        'zimrx_regimen_template',
        'zimrx_doctor_assistants',
        'zimrx_patient_doctor_access',
    ];
    foreach ($doctorScopedTables as $table) {
        zimrx_db_ensure_column($pdo, $table, 'doctor_id', 'INTEGER NOT NULL DEFAULT 1');
        zimrx_db_backfill_doctor_id($pdo, $table);
    }
    zimrx_db_ensure_pc_schema($pdo);
    zimrx_db_ensure_pc_settings_schema($pdo);
    zimrx_db_ensure_pc_source_priority_rows($pdo, 1);
    zimrx_db_ensure_medical_history_settings_schema($pdo);
    zimrx_db_ensure_physical_examination_settings_schema($pdo);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_patient_doctor_access (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            doctor_id INTEGER NOT NULL,
            can_view INTEGER NOT NULL DEFAULT 1,
            can_write INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(patient_id, doctor_id)
        )"
    );
    if (zimrx_db_table_exists($pdo, 'zimrx_patients')) {
        $pdo->exec(
            DbSql::insertIgnoreSelect(
                'zimrx_patient_doctor_access',
                'patient_id, doctor_id',
                "SELECT id, COALESCE(NULLIF(doctor_id, 0), 1) FROM zimrx_patients"
            )
        );
    }

    zimrx_db_rebuild_doctor_singleton_table(
        $pdo,
        'zimrx_appointment_settings',
        "CREATE TABLE zimrx_appointment_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            settings_json TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id)
        )",
        ['id', 'doctor_id', 'settings_json', 'updated_at'],
        ['settings_json' => "'{}'", 'updated_at' => "CURRENT_TIMESTAMP"]
    );

    zimrx_db_rebuild_doctor_singleton_table(
        $pdo,
        'zimrx_prescription_print_layout_settings',
        "CREATE TABLE zimrx_prescription_print_layout_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            page_width_cm REAL NOT NULL DEFAULT 21,
            page_height_cm REAL NOT NULL DEFAULT 29.7,
            header_height_cm REAL NOT NULL DEFAULT 4.8,
            patient_info_height_cm REAL NOT NULL DEFAULT 1.4,
            left_width_cm REAL NOT NULL DEFAULT 7.2,
            footer_height_cm REAL NOT NULL DEFAULT 1.8,
            body_font_size_pt REAL NOT NULL DEFAULT 10,
            rx_font_size_pt REAL NOT NULL DEFAULT 11,
            line_height_pt REAL NOT NULL DEFAULT 16,
            show_header INTEGER NOT NULL DEFAULT 1,
            show_footer INTEGER NOT NULL DEFAULT 1,
            print_settings_json TEXT NOT NULL DEFAULT '{}',
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id)
        )",
        [
            'id', 'doctor_id', 'page_width_cm', 'page_height_cm', 'header_height_cm',
            'patient_info_height_cm', 'left_width_cm', 'footer_height_cm',
            'body_font_size_pt', 'rx_font_size_pt', 'line_height_pt',
            'show_header', 'show_footer', 'print_settings_json', 'updated_at',
        ],
        ['print_settings_json' => "'{}'", 'updated_at' => "CURRENT_TIMESTAMP"]
    );
    zimrx_db_ensure_column($pdo, 'zimrx_prescription_print_layout_settings', 'print_settings_json', "TEXT NOT NULL DEFAULT '{}'");

    zimrx_db_rebuild_doctor_singleton_table(
        $pdo,
        'zimrx_prescription_header_settings',
        "CREATE TABLE zimrx_prescription_header_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            doctor_name TEXT NOT NULL DEFAULT 'Doctor',
            qualifications TEXT,
            specialty TEXT,
            bmdc_no TEXT,
            chamber_name TEXT,
            chamber_address TEXT,
            chamber_phone TEXT,
            header_note TEXT,
            footer_note TEXT,
            logo_path TEXT,
            display_logo TEXT NOT NULL DEFAULT 'yes',
            bg_color TEXT NOT NULL DEFAULT 'FFFFFF',
            footer_html TEXT,
            left_block_html TEXT,
            right_block_html TEXT,
            left_line_1 TEXT,
            left_line_2 TEXT,
            left_line_3 TEXT,
            left_line_4 TEXT,
            left_line_5 TEXT,
            left_line_6 TEXT,
            left_line_7 TEXT,
            left_line_8 TEXT,
            left_line_9 TEXT,
            left_line_10 TEXT,
            right_line_1 TEXT,
            right_line_2 TEXT,
            right_line_3 TEXT,
            right_line_4 TEXT,
            right_line_5 TEXT,
            right_line_6 TEXT,
            right_line_7 TEXT,
            right_line_8 TEXT,
            right_line_9 TEXT,
            right_line_10 TEXT,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id)
        )",
        [
            'id', 'doctor_id', 'doctor_name', 'qualifications', 'specialty',
            'bmdc_no', 'chamber_name', 'chamber_address', 'chamber_phone',
            'header_note', 'footer_note', 'logo_path', 'display_logo',
            'bg_color', 'footer_html', 'left_block_html', 'right_block_html',
            'left_line_1', 'left_line_2', 'left_line_3', 'left_line_4', 'left_line_5',
            'left_line_6', 'left_line_7', 'left_line_8', 'left_line_9', 'left_line_10',
            'right_line_1', 'right_line_2', 'right_line_3', 'right_line_4', 'right_line_5',
            'right_line_6', 'right_line_7', 'right_line_8', 'right_line_9', 'right_line_10',
            'updated_at',
        ],
        [
            'doctor_name' => "'Doctor'",
            'display_logo' => "'yes'",
            'bg_color' => "'FFFFFF'",
            'updated_at' => "CURRENT_TIMESTAMP",
        ]
    );
    $headerTables = ['zimrx_prescription_header_settings', 'zimrx_prescription_header_settings'];
    foreach ($headerTables as $hTable) {
        zimrx_db_ensure_column($pdo, $hTable, 'display_logo', "TEXT NOT NULL DEFAULT 'yes'");
        zimrx_db_ensure_column($pdo, $hTable, 'bg_color', "TEXT NOT NULL DEFAULT 'FFFFFF'");
        zimrx_db_ensure_column($pdo, $hTable, 'header_type', "TEXT NOT NULL DEFAULT 'text'");
        zimrx_db_ensure_column($pdo, $hTable, 'full_body_header_path', 'TEXT');
        zimrx_db_ensure_column($pdo, $hTable, 'footer_html', 'TEXT');
        zimrx_db_ensure_column($pdo, $hTable, 'left_block_html', 'TEXT');
        zimrx_db_ensure_column($pdo, $hTable, 'right_block_html', 'TEXT');
        zimrx_db_ensure_column($pdo, $hTable, 'bg_image_path', 'TEXT');
        zimrx_db_ensure_column($pdo, $hTable, 'bg_image_opacity', "REAL NOT NULL DEFAULT 0.15");
        zimrx_db_ensure_column($pdo, $hTable, 'bg_image_scale', "REAL NOT NULL DEFAULT 1.0");
        zimrx_db_ensure_column($pdo, $hTable, 'bg_image_angle', "REAL NOT NULL DEFAULT 0.0");
        zimrx_db_ensure_column($pdo, $hTable, 'bg_image_offset_x', "REAL NOT NULL DEFAULT 0.0");
        zimrx_db_ensure_column($pdo, $hTable, 'bg_image_offset_y', "REAL NOT NULL DEFAULT 0.0");
        for ($i = 1; $i <= 10; $i++) {
            zimrx_db_ensure_column($pdo, $hTable, "left_line_$i", 'TEXT');
            zimrx_db_ensure_column($pdo, $hTable, "right_line_$i", 'TEXT');
        }
    }

    $pdo->exec(DbSql::insertIgnore('zimrx_prescription_print_layout_settings', 'doctor_id', '1'));
    $stmt = $pdo->prepare(DbSql::insertIgnore('zimrx_prescription_header_settings', 'doctor_id, doctor_name', '1, :doctor_name'));
    $stmt->execute(['doctor_name' => $doctorName]);

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_doctor_id ON zimrx_user_accounts(doctor_id)");
    if (zimrx_db_table_exists($pdo, 'zimrx_patients')) {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patients_doctor_reg ON zimrx_patients(doctor_id, reg_no)");
    }
    if (zimrx_db_table_exists($pdo, 'zimrx_appointments')) {
        zimrx_db_normalize_doctor_appointment_serials($pdo);
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_doctor_date ON zimrx_appointments(doctor_id, appointment_date)");
        $pdo->exec("DROP INDEX IF EXISTS uid_appointments_date_no");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_appointments_doctor_date_no ON zimrx_appointments(doctor_id, appointment_date, appointment_no)");
    }
    if (zimrx_db_table_exists($pdo, 'zimrx_visits')) {
        zimrx_db_ensure_column($pdo, 'zimrx_visits', 'referral_category', "TEXT NOT NULL DEFAULT 'self'");
        zimrx_db_ensure_column($pdo, 'zimrx_visits', 'referral_name', 'TEXT');
        zimrx_db_ensure_column($pdo, 'zimrx_visits', 'prescription_html', 'TEXT');
        zimrx_db_ensure_column($pdo, 'zimrx_visits', 'clinical_snapshot_json', 'TEXT');
        if (zimrx_db_table_exists($pdo, 'zimrx_user_patient_referrals')) {
            $pdo->exec(
                "UPDATE zimrx_visits
                 SET referral_category = COALESCE((
                         SELECT r.category
                         FROM zimrx_user_patient_referrals r
                         WHERE r.doctor_id = zimrx_visits.doctor_id
                           AND r.visit_record_id = zimrx_visits.id
                         LIMIT 1
                     ), referral_category),
                     referral_name = COALESCE((
                         SELECT r.referral_name
                         FROM zimrx_user_patient_referrals r
                         WHERE r.doctor_id = zimrx_visits.doctor_id
                           AND r.visit_record_id = zimrx_visits.id
                         LIMIT 1
                     ), referral_name)
                 WHERE COALESCE(referral_name, '') = ''
                   AND EXISTS (
                       SELECT 1
                       FROM zimrx_user_patient_referrals r
                       WHERE r.doctor_id = zimrx_visits.doctor_id
                         AND r.visit_record_id = zimrx_visits.id
                         AND COALESCE(r.referral_name, '') <> ''
                   )"
            );
        }
        $pdo->exec(
            "UPDATE zimrx_visits
             SET referral_category = 'doctor',
                 referral_name = referred_by
             WHERE COALESCE(referral_name, '') = ''
               AND COALESCE(referred_by, '') <> ''"
        );
        $pdo->exec("DROP INDEX IF EXISTS idx_visits_patient_visit_no");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_doctor_patient ON zimrx_visits(doctor_id, patient_id, visit_no)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_doctor_patient_visit_no ON zimrx_visits(doctor_id, patient_id, visit_no) WHERE visit_no IS NOT NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_referrals ON zimrx_visits(doctor_id, referral_category, referral_name)");
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_visit_revisions (
            id " . DbSql::autoIncrement() . ",
            visit_record_id INTEGER NOT NULL,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            revision_no INTEGER NOT NULL DEFAULT 1,
            patient_id INTEGER,
            patient_reg_no TEXT,
            visit_no INTEGER,
            visit_id TEXT,
            clinical_snapshot_json TEXT,
            prescription_html TEXT,
            rich_text_json TEXT,
            billing_json TEXT,
            reason TEXT,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visit_revisions_lookup ON zimrx_visit_revisions(doctor_id, visit_record_id, revision_no)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rx_usage_doctor_brand ON zimrx_user_drugs(doctor_id, brand_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_doctor_assistants_doctor ON zimrx_doctor_assistants(doctor_id, is_active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_doctor_assistants_assistant ON zimrx_doctor_assistants(assistant_user_id, is_active)");
}

function zimrx_doctor_options_for_user(PDO $pdo, int $userId, string $role, int $doctorId = 1): array {
    $role = strtolower($role);
    if ($role === 'admin') {
        return $pdo->query(
            "SELECT id, doctor_code, display_name, qualifications_en AS qualifications, specialty_en AS specialty, is_active
             FROM zimrx_doctors
             WHERE is_active = 1
             ORDER BY display_name ASC, id ASC"
        )->fetchAll();
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
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare(
        "SELECT id, doctor_code, display_name, qualifications_en AS qualifications, specialty_en AS specialty, is_active
         FROM zimrx_doctors
         WHERE id = :doctor_id
         LIMIT 1"
    );
    $stmt->execute(['doctor_id' => max(1, $doctorId)]);
    return $stmt->fetchAll();
}

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

// 4. Run schema migrations and legacy doctor-scope initialization.
//
// DbMigrator handles all DDL going forward (versioned, idempotent).
// zimrx_ensure_doctor_scope() is kept as a legacy safety net for existing
// databases that pre-date the migration system — it is idempotent.
if (!defined('ZIMRX_DB_LIGHTWEIGHT')) {
    try {
        (new DbMigrator())->run($pdo);
    } catch (Throwable $e) {
        error_log('[ZimRx] DbMigrator::run() error: ' . $e->getMessage());
    }

    // Legacy path: runs ensure_doctor_scope for databases that were already
    // initialized before the migration system existed (schema_migrations absent
    // or doctors table missing). This is safe to call on every boot — all
    // CREATE TABLE IF NOT EXISTS / INSERT OR IGNORE inside are idempotent.
    if (!zimrx_db_table_exists($pdo, 'zimrx_doctors')) {
        zimrx_ensure_doctor_scope($pdo);
    }

    // Sync layout settings from DB to Cookies
    zimrx_sync_interface_layout($pdo);
}

/**
 * Sync interface settings from the database to cookies.
 * This runs on every boot to keep layouts synchronized across devices.
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

