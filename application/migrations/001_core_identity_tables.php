<?php
/**
 * Migration 001 — Core identity, configuration, user accounts, and doctors schema
 */
class Migration001CoreIdentityTables {

    public function up(PDO $pdo): void {
        // ---- zimrx_userdb_version ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_userdb_version (
                id " . DbSql::intType() . " PRIMARY KEY CHECK (id = 1),
                version " . DbSql::intType() . " NOT NULL,
                schema_name TEXT NOT NULL,
                created_at " . DbSql::timestampColumn() . ",
                migrated_from TEXT,
                migration_note TEXT
            )"
        );

        // ---- zimrx_app_config ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_app_config (
                config_key VARCHAR(100) PRIMARY KEY NOT NULL,
                config_value TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_doctors ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_doctors (
                id " . DbSql::autoIncrement() . ",
                doctor_code TEXT UNIQUE,
                display_name TEXT NOT NULL,
                full_name_en TEXT NOT NULL DEFAULT 'Doctor',
                full_name_bn TEXT,
                designation_en TEXT,
                designation_bn TEXT,
                institute_en TEXT,
                institute_bn TEXT,
                qualifications_en TEXT,
                qualifications_bn TEXT,
                specialty_en TEXT,
                specialty_bn TEXT,
                bmdc_no_en TEXT,
                bmdc_no_bn TEXT,
                phone_number TEXT,
                alternate_phone TEXT,
                email TEXT,
                dob TEXT,
                blood_group TEXT,
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Ensure default doctor id=1 exists
        $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_doctors',
                'id, doctor_code, display_name, full_name_en',
                ':id, :code, :name, :fname'
            )
        )->execute(['id' => 1, 'code' => 'D001', 'name' => 'Doctor', 'fname' => 'Doctor']);

        // ---- zimrx_user_accounts ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_accounts (
                id " . DbSql::autoIncrement() . ",
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                display_name TEXT,
                role TEXT NOT NULL CHECK (role IN ('doctor', 'assistant', 'admin', 'superadmin')),
                doctor_id " . DbSql::intType() . ",
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Ensure root admin account exists
        $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_user_accounts',
                'username, password_hash, display_name, role, doctor_id, is_active',
                ":username, :password_hash, 'Root Admin', 'superadmin', 1, 1"
            )
        )->execute([
            'username'      => 'root',
            'password_hash' => hash('sha256', '123'),
        ]);

        // ---- zimrx_assistants ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_assistants (
                id " . DbSql::autoIncrement() . ",
                account_id " . DbSql::intType() . " UNIQUE,
                full_name TEXT,
                display_name TEXT,
                dob TEXT,
                phone TEXT,
                alternate_phone TEXT,
                email TEXT,
                notes TEXT,
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_doctor_assistants ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_doctor_assistants (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                assistant_user_id " . DbSql::intType() . " NOT NULL,
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, assistant_user_id)
            )"
        );

        // Indexes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_doctor_id ON zimrx_user_accounts(doctor_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_doctor_assistants_doctor ON zimrx_doctor_assistants(doctor_id, is_active)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_doctor_assistants_assistant ON zimrx_doctor_assistants(assistant_user_id, is_active)");
    }
}
