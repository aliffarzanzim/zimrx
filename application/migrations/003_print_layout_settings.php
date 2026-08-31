<?php
/**
 * Migration 003 — Prescription print layout, header settings, OT note settings, and interface layout
 */
class Migration003PrintLayoutSettings {

    public function up(PDO $pdo): void {
        // ---- zimrx_prescription_print_layout_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_prescription_print_layout_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                page_width_cm REAL DEFAULT 21,
                page_height_cm REAL DEFAULT 29.7,
                header_height_cm REAL DEFAULT 3,
                patient_info_height_cm REAL DEFAULT 2,
                left_width_cm REAL DEFAULT 4,
                footer_height_cm REAL DEFAULT 1.5,
                body_font_size_pt REAL DEFAULT 10,
                rx_font_size_pt REAL DEFAULT 12,
                line_height_pt REAL DEFAULT 14,
                show_header " . DbSql::intType() . " NOT NULL DEFAULT 1,
                show_footer " . DbSql::intType() . " NOT NULL DEFAULT 1,
                print_settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Seed default row for doctor 1
        $pdo->exec(
            DbSql::insertIgnore('zimrx_prescription_print_layout_settings', 'doctor_id', '1')
        );

        // ---- zimrx_prescription_header_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_prescription_header_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                doctor_name TEXT,
                qualifications TEXT,
                specialty TEXT,
                bmdc_no TEXT,
                chamber_name TEXT,
                chamber_address TEXT,
                chamber_phone TEXT,
                header_note TEXT,
                footer_note TEXT,
                logo_path TEXT,
                display_logo " . DbSql::intType() . " NOT NULL DEFAULT 1,
                bg_color TEXT,
                footer_html TEXT,
                left_line_1 TEXT,
                right_line_1 TEXT,
                left_line_2 TEXT,
                right_line_2 TEXT,
                left_line_3 TEXT,
                right_line_3 TEXT,
                left_line_4 TEXT,
                right_line_4 TEXT,
                left_line_5 TEXT,
                right_line_5 TEXT,
                left_line_6 TEXT,
                right_line_6 TEXT,
                left_line_7 TEXT,
                right_line_7 TEXT,
                left_line_8 TEXT,
                right_line_8 TEXT,
                left_line_9 TEXT,
                right_line_9 TEXT,
                left_line_10 TEXT,
                right_line_10 TEXT,
                left_block_html TEXT,
                right_block_html TEXT,
                header_type TEXT NOT NULL DEFAULT 'text',
                full_body_header_path TEXT,
                bg_image_path TEXT,
                bg_image_opacity REAL NOT NULL DEFAULT 0.15,
                bg_image_scale REAL NOT NULL DEFAULT 1.0,
                bg_image_angle REAL NOT NULL DEFAULT 0.0,
                bg_image_offset_x REAL NOT NULL DEFAULT 0.0,
                bg_image_offset_y REAL NOT NULL DEFAULT 0.0,
                has_onboarded " . DbSql::intType() . " DEFAULT 0,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Seed default header row for doctor 1
        $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_prescription_header_settings',
                'doctor_id, doctor_name',
                ':doctor_id, :doctor_name'
            )
        )->execute(['doctor_id' => 1, 'doctor_name' => 'Doctor']);

        // ---- zimrx_ot_note_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_ot_note_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                print_ot_note " . DbSql::intType() . " NOT NULL DEFAULT 1,
                print_layout TEXT NOT NULL DEFAULT 'sidebar',
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_interface_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_interface_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                setting_scope TEXT NOT NULL DEFAULT 'global',
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, setting_scope, setting_key)
            )"
        );
    }
}
