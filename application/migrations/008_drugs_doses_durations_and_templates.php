<?php
/**
 * Migration 008 — Prescription drugs, clinical findings, custom drugs, doses, durations, templates, and sorting
 */
class Migration008DrugsDosesDurationsAndTemplates {

    public function up(PDO $pdo): void {
        // ---- zimrx_prescription_drugs ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_prescription_drugs (
                id " . DbSql::autoIncrement() . ",
                prescription_id " . DbSql::intType() . ",
                visit_id " . DbSql::intType() . ",
                patient_id " . DbSql::intType() . ",
                doctor_id " . DbSql::intType() . ",
                is_history " . DbSql::intType() . " NOT NULL DEFAULT 0,
                brand_id " . DbSql::intType() . ",
                generic_id " . DbSql::intType() . ",
                drug_name TEXT,
                brand_name TEXT,
                generic_name TEXT,
                strength TEXT,
                form TEXT,
                dosage TEXT,
                dose TEXT,
                duration TEXT,
                instructions TEXT,
                instruction TEXT,
                sort_order " . DbSql::intType() . " DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_prescription_drugs_patient ON zimrx_prescription_drugs(patient_id, doctor_id, generic_name)");

        // ---- zimrx_prescription_clinical_findings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_prescription_clinical_findings (
                id " . DbSql::autoIncrement() . ",
                prescription_id " . DbSql::intType() . ",
                visit_id " . DbSql::intType() . ",
                doctor_id " . DbSql::intType() . ",
                category TEXT NOT NULL,
                label TEXT NOT NULL,
                value TEXT,
                unit TEXT,
                sort_order " . DbSql::intType() . " DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_user_drugs ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drugs (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                brand_id " . DbSql::intType() . ",
                generic_id " . DbSql::intType() . ",
                brand_name TEXT,
                generic_name TEXT NOT NULL DEFAULT '',
                strength TEXT NOT NULL DEFAULT '',
                form TEXT NOT NULL DEFAULT '',
                dose TEXT,
                instruction TEXT,
                duration TEXT,
                normalized_key TEXT,
                use_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                last_used_at TEXT,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, normalized_key)
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_drugs_lookup ON zimrx_user_drugs(doctor_id, brand_name, generic_name)");

        // ---- zimrx_user_custom_drugs ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_custom_drugs (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                brand_name TEXT NOT NULL,
                generic_name TEXT,
                strength TEXT,
                form TEXT,
                dose TEXT,
                instruction TEXT,
                duration TEXT,
                note TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, brand_name, generic_name, strength, form)
            )"
        );

        // ---- zimrx_user_drugs_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drugs_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                drug_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, drug_id, setting_key)
            )"
        );

        // ---- zimrx_user_drug_hidden ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_hidden (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                system_brand_id TEXT NOT NULL,
                brand_snapshot TEXT,
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                hidden_at " . DbSql::timestampColumn() . ",
                restored_at TEXT
            )"
        );
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_user_drug_hidden_brand ON zimrx_user_drug_hidden(doctor_id, system_brand_id)");

        // ---- zimrx_user_drug_override ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_override (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                system_brand_id TEXT NOT NULL,
                local_drug_id TEXT NOT NULL,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_user_drug_override_brand ON zimrx_user_drug_override(doctor_id, system_brand_id)");

        // ---- zimrx_user_drug_prescribe_index ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_prescribe_index (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                source_type TEXT NOT NULL DEFAULT 'custom',
                local_drug_id TEXT NOT NULL,
                system_brand_id TEXT,
                generic_id TEXT,
                brand_name TEXT NOT NULL,
                generic_name TEXT,
                manufacturer_name TEXT,
                strength TEXT,
                form TEXT,
                std_form TEXT,
                price TEXT,
                packsize TEXT,
                prescribe_brand_short TEXT,
                prescribe_brand_full TEXT,
                short_prescription TEXT,
                long_prescription TEXT,
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_user_drug_local_id ON zimrx_user_drug_prescribe_index(local_drug_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_brand_name ON zimrx_user_drug_prescribe_index(brand_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_generic_name ON zimrx_user_drug_prescribe_index(generic_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_system_brand_id ON zimrx_user_drug_prescribe_index(system_brand_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_active ON zimrx_user_drug_prescribe_index(is_active, source_type)");

        // ---- zimrx_user_drug_doses ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_doses (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                dosage_bn TEXT,
                dosage_en TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                static_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                search_alias TEXT,
                default_dosage_form TEXT NOT NULL DEFAULT '[]',
                is_edited " . DbSql::intType() . " NOT NULL DEFAULT 0
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_doses_doctor_static ON zimrx_user_drug_doses(doctor_id, static_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_doses_doctor_usage ON zimrx_user_drug_doses(doctor_id, usage_count DESC, sort_order ASC, id ASC)");

        // ---- zimrx_user_drug_doses_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_doses_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                dose_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, dose_id, setting_key)
            )"
        );
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_zimrx_user_drug_doses_settings_doctor_setting ON zimrx_user_drug_doses_settings(doctor_id, dose_id, setting_key)");

        // ---- zimrx_user_drug_durations ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_durations (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                duration_bn TEXT,
                duration_en TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                static_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                search_alias TEXT,
                default_dosage_form TEXT NOT NULL DEFAULT '[]',
                is_edited " . DbSql::intType() . " NOT NULL DEFAULT 0
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_durations_doctor_static ON zimrx_user_drug_durations(doctor_id, static_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_durations_doctor_usage ON zimrx_user_drug_durations(doctor_id, usage_count DESC, sort_order ASC, id ASC)");

        // ---- zimrx_user_drug_durations_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_durations_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                duration_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, duration_id, setting_key)
            )"
        );
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_zimrx_user_drug_durations_settings_doctor_setting ON zimrx_user_drug_durations_settings(doctor_id, duration_id, setting_key)");

        // ---- zimrx_rx_grid_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_rx_grid_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                show_warnings " . DbSql::intType() . " NOT NULL DEFAULT 1,
                show_interactions " . DbSql::intType() . " NOT NULL DEFAULT 0,
                warning_types_json TEXT,
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_summary_interaction_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_summary_interaction_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_user_manufacturer_sorting ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_manufacturer_sorting (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                manufacturer_id " . DbSql::intType() . ",
                manufacturer_name TEXT NOT NULL,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, manufacturer_name)
            )"
        );

        // ---- zimrx_drug_template ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_drug_template (
                id " . DbSql::autoIncrement() . ",
                template_group_id TEXT,
                template_name TEXT NOT NULL,
                item_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                item_type TEXT,
                brand_id " . DbSql::intType() . ",
                generic_id " . DbSql::intType() . ",
                brand_name TEXT,
                generic_name TEXT,
                strength TEXT,
                form TEXT,
                dose TEXT,
                instruction TEXT,
                duration TEXT,
                section TEXT,
                content TEXT,
                content_json TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                doctor_id " . DbSql::intType() . " NOT NULL,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_regimen_template ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_regimen_template (
                id " . DbSql::autoIncrement() . ",
                template_group_id TEXT,
                template_name TEXT NOT NULL,
                item_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                item_type TEXT,
                brand_id " . DbSql::intType() . ",
                generic_id " . DbSql::intType() . ",
                brand_name TEXT,
                generic_name TEXT,
                strength TEXT,
                form TEXT,
                dose TEXT,
                instruction TEXT,
                duration TEXT,
                section TEXT,
                content TEXT,
                content_json TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                doctor_id " . DbSql::intType() . " NOT NULL,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_prescription_template ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_prescription_template (
                id " . DbSql::autoIncrement() . ",
                template_group_id TEXT,
                template_name TEXT NOT NULL,
                item_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                item_type TEXT,
                brand_id " . DbSql::intType() . ",
                generic_id " . DbSql::intType() . ",
                brand_name TEXT,
                generic_name TEXT,
                strength TEXT,
                form TEXT,
                dose TEXT,
                instruction TEXT,
                duration TEXT,
                section TEXT,
                content TEXT,
                content_json TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                doctor_id " . DbSql::intType() . " NOT NULL,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
    }
}
