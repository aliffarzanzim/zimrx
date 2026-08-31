<?php
declare(strict_types=1);

// Print layout settings: margins, prescription header/footer blocks, OT notes, and UI preferences.
class Migration003PrintLayoutSettings {

    public function up(PDO $pdo): void {
        $defaultsPath = dirname(__DIR__) . '/config/print_defaults.php';
        $d = is_file($defaultsPath) ? require $defaultsPath : [];

        $pageWidth    = (float)($d['page_width'] ?? 21.0);
        $pageHeight   = (float)($d['page_height'] ?? 29.7);
        $headerHeight = (float)($d['header_height'] ?? 5.3);
        $ptInfoHeight = (float)($d['pt_info_height'] ?? 1.6);
        $leftWidth    = (float)($d['left_width'] ?? 9.0);
        $footerHeight = (float)($d['footer_height'] ?? 2.0);

        // Physical page dimensions, margins, and typography metrics
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_prescription_print_layout_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                page_width_cm REAL DEFAULT {$pageWidth},
                page_height_cm REAL DEFAULT {$pageHeight},
                header_height_cm REAL DEFAULT {$headerHeight},
                patient_info_height_cm REAL DEFAULT {$ptInfoHeight},
                left_width_cm REAL DEFAULT {$leftWidth},
                footer_height_cm REAL DEFAULT {$footerHeight},
                body_font_size_pt REAL DEFAULT 10,
                rx_font_size_pt REAL DEFAULT 12,
                line_height_pt REAL DEFAULT 14,
                show_header " . DbSql::intType() . " NOT NULL DEFAULT 1,
                show_footer " . DbSql::intType() . " NOT NULL DEFAULT 1,
                print_settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // Seed default A4 layout for primary doctor
        $pdo->exec(
            DbSql::insertIgnore('zimrx_prescription_print_layout_settings', 'doctor_id', '1')
        );

        // Pad header styling: clinic name, doctor credentials, watermark logos, and block texts
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

        // Seed blank header profile for doctor 1
        $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_prescription_header_settings',
                'doctor_id, doctor_name',
                ':doctor_id, :doctor_name'
            )
        )->execute(['doctor_id' => 1, 'doctor_name' => 'Doctor']);

        // Operation theater note formatting options
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

        // Doctor custom UI preferences (collapsed sidebar, panel layouts, theme overrides)
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

