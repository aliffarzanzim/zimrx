<?php
/**
 * Migration 002 — Patient, appointment, visit, clinical, vitals, payments, and revisions schema
 */
class Migration002PatientAppointmentVisit {

    public function up(PDO $pdo): void {
        // ---- zimrx_patients ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_patients (
                id " . DbSql::autoIncrement() . ",
                reg_no TEXT,
                full_name TEXT NOT NULL,
                dob TEXT,
                gender TEXT,
                blood_group TEXT,
                address TEXT,
                mobile TEXT,
                occupation TEXT,
                age TEXT,
                age_unit TEXT,
                weight TEXT,
                weight_unit TEXT,
                height TEXT,
                height_unit TEXT,
                doctor_id " . DbSql::intType() . ",
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                tracked_metrics_json TEXT DEFAULT '[\"weight\"]'
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patients_reg_no ON zimrx_patients(reg_no)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patients_mobile ON zimrx_patients(mobile)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patients_doctor ON zimrx_patients(doctor_id, full_name, mobile)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patients_doctor_reg ON zimrx_patients(doctor_id, reg_no)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_reg_no ON zimrx_patients(reg_no)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_mobile ON zimrx_patients(mobile)");

        // ---- zimrx_patient_doctor_access ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_patient_doctor_access (
                id " . DbSql::autoIncrement() . ",
                patient_id " . DbSql::intType() . " NOT NULL,
                doctor_id " . DbSql::intType() . " NOT NULL,
                can_view " . DbSql::intType() . " NOT NULL DEFAULT 1,
                can_write " . DbSql::intType() . " NOT NULL DEFAULT 0,
                access_level TEXT NOT NULL DEFAULT 'particulars',
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(patient_id, doctor_id)
            )"
        );

        // ---- zimrx_patient_doctor_access_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_patient_doctor_access_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                default_access_level TEXT NOT NULL DEFAULT 'particulars',
                can_view_particulars " . DbSql::intType() . " NOT NULL DEFAULT 1,
                can_view_prescriptions " . DbSql::intType() . " NOT NULL DEFAULT 0,
                can_write_prescriptions " . DbSql::intType() . " NOT NULL DEFAULT 0,
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_patients_change_log ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_patients_change_log (
                id " . DbSql::autoIncrement() . ",
                patient_id " . DbSql::intType() . " NOT NULL,
                doctor_id " . DbSql::intType() . ",
                visit_id " . DbSql::intType() . ",
                field_name TEXT NOT NULL,
                old_value TEXT,
                new_value TEXT,
                changed_at " . DbSql::timestampColumn() . ",
                changed_by " . DbSql::intType() . ",
                note TEXT
            )"
        );

        // ---- zimrx_patient_particulars_audit ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_patient_particulars_audit (
                id " . DbSql::autoIncrement() . ",
                patient_id " . DbSql::intType() . " NOT NULL,
                patient_reg_no TEXT,
                action_source TEXT NOT NULL,
                changed_by_user_id " . DbSql::intType() . ",
                changed_by_role TEXT,
                changed_by_name TEXT,
                changes_json TEXT NOT NULL,
                summary_text TEXT,
                created_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_patient ON zimrx_patient_particulars_audit(patient_id, created_at)");

        // ---- zimrx_patient_metric_readings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_patient_metric_readings (
                id " . DbSql::autoIncrement() . ",
                patient_id " . DbSql::intType() . " NOT NULL,
                metric_type TEXT NOT NULL,
                reading_value TEXT NOT NULL,
                secondary_value TEXT,
                reading_date TEXT NOT NULL,
                reading_time TEXT,
                source TEXT NOT NULL DEFAULT 'clinic',
                notes TEXT,
                created_by " . DbSql::intType() . ",
                created_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_metric_pt ON zimrx_patient_metric_readings(patient_id, metric_type, reading_date)");

        // ---- zimrx_appointments ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_appointments (
                id " . DbSql::autoIncrement() . ",
                appointment_no " . DbSql::intType() . ",
                appointment_date TEXT NOT NULL,
                appointment_time TEXT,
                patient_id " . DbSql::intType() . ",
                reg_no TEXT,
                patient_name TEXT NOT NULL,
                age TEXT,
                age_unit TEXT,
                dob TEXT,
                gender TEXT,
                blood_group TEXT,
                mobile TEXT,
                address TEXT,
                occupation TEXT,
                weight TEXT,
                weight_unit TEXT,
                height TEXT,
                height_unit TEXT,
                visit_id " . DbSql::intType() . ",
                visit_no " . DbSql::intType() . ",
                visit_code TEXT,
                status TEXT NOT NULL DEFAULT 'booked',
                notes TEXT,
                created_by " . DbSql::intType() . ",
                doctor_id " . DbSql::intType() . ",
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                visit_record_id " . DbSql::intType() . ",
                referral_category TEXT NOT NULL DEFAULT 'self',
                referral_name TEXT,
                visit_fee REAL,
                discount REAL,
                discount_note TEXT,
                paid_amount REAL,
                payment_updated_at TEXT,
                bp TEXT,
                pulse TEXT,
                temperature TEXT,
                spo2 TEXT,
                resp_rate TEXT,
                vitals_note TEXT,
                vitals_entered_by " . DbSql::intType() . ",
                vitals_entered_at TEXT
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_date_no ON zimrx_appointments(appointment_date, appointment_no)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_doctor_date ON zimrx_appointments(doctor_id, appointment_date)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_appointments_date_doctor ON zimrx_appointments(appointment_date, doctor_id, appointment_no)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_referrals ON zimrx_appointments(doctor_id, referral_category, referral_name)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_appointments_doctor_date_no ON zimrx_appointments(doctor_id, appointment_date, appointment_no)");

        // ---- zimrx_appointment_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_appointment_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                settings_json TEXT NOT NULL DEFAULT '{}',
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_payments ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_payments (
                id " . DbSql::autoIncrement() . ",
                appointment_id " . DbSql::intType() . " UNIQUE,
                visit_id " . DbSql::intType() . ",
                patient_id " . DbSql::intType() . ",
                doctor_id " . DbSql::intType() . ",
                visit_fee REAL DEFAULT 0,
                discount REAL DEFAULT 0,
                discount_note TEXT,
                paid_amount REAL DEFAULT 0,
                payment_method TEXT,
                payment_status TEXT,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                receipt_no TEXT,
                service_type TEXT DEFAULT 'Consultation',
                notes TEXT
            )"
        );

        // ---- zimrx_visit_vitals ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_visit_vitals (
                id " . DbSql::autoIncrement() . ",
                appointment_id " . DbSql::intType() . " UNIQUE,
                visit_id " . DbSql::intType() . ",
                patient_id " . DbSql::intType() . ",
                doctor_id " . DbSql::intType() . ",
                bp TEXT,
                pulse TEXT,
                temperature TEXT,
                spo2 TEXT,
                resp_rate TEXT,
                vitals_note TEXT,
                entered_by " . DbSql::intType() . ",
                entered_at TEXT,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_visits ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_visits (
                id " . DbSql::autoIncrement() . ",
                patient_id " . DbSql::intType() . " NOT NULL,
                patient_reg_no TEXT,
                patient_name TEXT,
                visit_no " . DbSql::intType() . ",
                visit_id TEXT,
                visit_date TEXT NOT NULL DEFAULT CURRENT_DATE,
                next_visit TEXT,
                referred_by TEXT,
                age_at_visit TEXT,
                height_at_visit TEXT,
                height_unit_at_visit TEXT,
                weight_at_visit TEXT,
                weight_unit_at_visit TEXT,
                metrics_json TEXT,
                billing_json TEXT,
                rich_text_json TEXT,
                print_settings TEXT,
                appointment_id " . DbSql::intType() . ",
                doctor_id " . DbSql::intType() . ",
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                referral_category TEXT NOT NULL DEFAULT 'self',
                referral_name TEXT,
                prescription_html TEXT,
                clinical_snapshot_json TEXT
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_doctor_patient ON zimrx_visits(doctor_id, patient_id, visit_no)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_referrals ON zimrx_visits(doctor_id, referral_category, referral_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_visits_patient ON zimrx_visits(patient_id, visit_date)");
        
        if (DbConnections::driver() === 'sqlite' || DbConnections::driver() === 'pgsql') {
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_doctor_patient_visit_no ON zimrx_visits(doctor_id, patient_id, visit_no) WHERE visit_no IS NOT NULL");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_visit_id ON zimrx_visits(visit_id) WHERE visit_id IS NOT NULL");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_appointment_id ON zimrx_visits(appointment_id) WHERE appointment_id IS NOT NULL");
        } else {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_doctor_patient_visit_no ON zimrx_visits(doctor_id, patient_id, visit_no)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_visit_id ON zimrx_visits(visit_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_appointment_id ON zimrx_visits(appointment_id)");
        }

        // ---- zimrx_visit_revisions ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_visit_revisions (
                id " . DbSql::autoIncrement() . ",
                visit_record_id " . DbSql::intType() . " NOT NULL,
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                revision_no " . DbSql::intType() . " NOT NULL DEFAULT 1,
                patient_id " . DbSql::intType() . ",
                patient_reg_no TEXT,
                visit_no " . DbSql::intType() . ",
                visit_id TEXT,
                clinical_snapshot_json TEXT,
                prescription_html TEXT,
                rich_text_json TEXT,
                billing_json TEXT,
                reason TEXT,
                created_by " . DbSql::intType() . ",
                created_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visit_revisions_lookup ON zimrx_visit_revisions(doctor_id, visit_record_id, revision_no)");

        // ---- zimrx_emr_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_emr_settings (
                id " . DbSql::autoIncrement() . ",
                daily_patient_flow " . DbSql::intType() . " NOT NULL DEFAULT 999,
                yearly_patient_flow " . DbSql::intType() . " NOT NULL DEFAULT 99999,
                reg_id_mode TEXT NOT NULL DEFAULT 'sequential',
                visit_id_mode TEXT NOT NULL DEFAULT 'sequential',
                auto_expand " . DbSql::intType() . " NOT NULL DEFAULT 1,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_user_patient_referrals ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_patient_referrals (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                patient_reg_no TEXT,
                visit_record_id " . DbSql::intType() . ",
                visit_id TEXT,
                category TEXT NOT NULL DEFAULT 'self',
                referral_name TEXT,
                normalized_name TEXT,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_referrals_suggestions ON zimrx_user_patient_referrals(doctor_id, category, normalized_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_referrals_visit_record ON zimrx_user_patient_referrals(doctor_id, visit_record_id)");
        if (DbConnections::driver() === 'sqlite' || DbConnections::driver() === 'pgsql') {
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_patient_referrals_doctor_visit_record ON zimrx_user_patient_referrals(doctor_id, visit_record_id) WHERE visit_record_id IS NOT NULL");
        } else {
            $pdo->exec("CREATE INDEX IF NOT EXISTS uid_patient_referrals_doctor_visit_record ON zimrx_user_patient_referrals(doctor_id, visit_record_id)");
        }

        // ---- zimrx_user_occupations ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_occupations (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                name TEXT NOT NULL,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 1,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, name)
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_occupations_doc ON zimrx_user_occupations(doctor_id, name)");
    }
}
