<?php
declare(strict_types=1);

// Clinical examination schema: medical history categories, physical exam templates, diagnoses, and doctor notepads.
class Migration009MedicalHistorySettings {

    public function up(PDO $pdo): void {
        // Medical history checklist items (hypertension, diabetes, asthma, surgical history)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_medical_history_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                category VARCHAR(100) NOT NULL,
                condition_key VARCHAR(100) NOT NULL,
                display_label VARCHAR(150) NOT NULL,
                full_name VARCHAR(255) NOT NULL DEFAULT '',
                field_type VARCHAR(50) NOT NULL DEFAULT 'none',
                dropdown_options TEXT DEFAULT '',
                placeholder TEXT DEFAULT '',
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 10,
                is_custom " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, condition_key)
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_med_history_doc_active ON zimrx_user_medical_history_settings(doctor_id, is_active, sort_order)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_med_history_doc_cat ON zimrx_user_medical_history_settings(doctor_id, category)");

        // Doctor UI preferences for history panel
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_history_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Systematic physical examination items and normal finding wordlists
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_physical_examination_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
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
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 10,
                is_custom " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, item_code)
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_pe_doc_active ON zimrx_user_physical_examination_settings(doctor_id, is_active, sort_order)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_pe_doc_sys ON zimrx_user_physical_examination_settings(doctor_id, system)");

        // Doctor UI preferences for physical examination module
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_physical_examination_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Doctor customized diagnosis vocabulary and frequent impressions
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_diagnosis (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                diagnosis TEXT NOT NULL,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, diagnosis)
            )"
        );

        // Diagnosis lookup and presentation configuration
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_diagnosis_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, setting_key)
            )"
        );

        // Diagnosis panel settings (layout, ICD suggestions toggle)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_dx_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Free-form clinical note editor settings
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_notepad_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Reusable text pad and clinical note templates
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_notepad_template (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                template_type TEXT NOT NULL DEFAULT 'note',
                template_name TEXT NOT NULL,
                content TEXT,
                content_json TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS zimrx_user_notepad_template");
        $pdo->exec("DROP TABLE IF EXISTS zimrx_notepad_settings");
        $pdo->exec("DROP TABLE IF EXISTS zimrx_dx_settings");
        $pdo->exec("DROP TABLE IF EXISTS zimrx_user_diagnosis_settings");
        $pdo->exec("DROP TABLE IF EXISTS zimrx_user_diagnosis");
        $pdo->exec("DROP TABLE IF EXISTS zimrx_physical_examination_settings");
        $pdo->exec("DROP TABLE IF EXISTS zimrx_user_physical_examination_settings");
        $pdo->exec("DROP TABLE IF EXISTS zimrx_history_settings");
        $pdo->exec("DROP TABLE IF EXISTS zimrx_user_medical_history_settings");
    }
}

