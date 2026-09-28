<?php
declare(strict_types=1);

/**
 * Migration 014 — Visit-identity columns and patient-particulars audit schema
 *
 * Absorbs every ALTER TABLE / CREATE TABLE that was previously executed at
 * request-time by zimrx_ensure_visit_identity_schema() (visit_identity.php)
 * and ensure_patient_particulars_audit_schema() (particulars_audit_lib.php).
 *
 * After this migration runs, those runtime functions are replaced by
 * fail-closed assertions; no DDL is executed during normal web requests.
 */
class Migration014VisitIdentityAndAuditSchema {

    public function up(PDO $pdo): void {

        // ── 1. Patient-particulars audit table ────────────────────────────────
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_patient_particulars_audit (
                id "    . DbSql::autoIncrement() . ",
                patient_id INTEGER NOT NULL,
                patient_reg_no TEXT,
                action_source TEXT NOT NULL,
                changed_by_user_id INTEGER,
                changed_by_role TEXT,
                changed_by_name TEXT,
                changes_json TEXT NOT NULL,
                summary_text TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_audit_patient
             ON zimrx_patient_particulars_audit(patient_id, created_at)"
        );

        // ── 2. zimrx_appointments — visit-identity column set ─────────────────
        if (DbSchema::tableExists($pdo, 'zimrx_appointments') &&
            !DbSchema::isView($pdo, 'zimrx_appointments')) {

            // Column renames (idempotent — skipped if already done)
            if (DbSchema::columnExists($pdo, 'zimrx_appointments', 'visit_id') &&
                !DbSchema::columnExists($pdo, 'zimrx_appointments', 'visit_record_id')) {
                try {
                    $pdo->exec('ALTER TABLE zimrx_appointments RENAME COLUMN visit_id TO visit_record_id');
                } catch (Throwable) { /* already renamed or DB does not support RENAME COLUMN */ }
            }
            if (DbSchema::columnExists($pdo, 'zimrx_appointments', 'visit_code') &&
                !DbSchema::columnExists($pdo, 'zimrx_appointments', 'visit_id')) {
                try {
                    $pdo->exec('ALTER TABLE zimrx_appointments RENAME COLUMN visit_code TO visit_id');
                } catch (Throwable) { /* already renamed */ }
            }

            $apptColumns = [
                'visit_record_id'    => 'INTEGER',
                'visit_id'           => 'TEXT',
                'referral_category'  => "TEXT NOT NULL DEFAULT 'self'",
                'referral_name'      => 'TEXT',
                'visit_fee'          => 'REAL',
                'discount'           => 'REAL',
                'discount_note'      => 'TEXT',
                'paid_amount'        => 'REAL',
                'payment_updated_at' => 'TEXT',
                'bp'                 => 'TEXT',
                'pulse'              => 'TEXT',
                'temperature'        => 'TEXT',
                'spo2'               => 'TEXT',
                'resp_rate'          => 'TEXT',
                'vitals_note'        => 'TEXT',
                'vitals_entered_by'  => 'INTEGER',
                'vitals_entered_at'  => 'TEXT',
            ];
            foreach ($apptColumns as $col => $def) {
                if (!DbSchema::columnExists($pdo, 'zimrx_appointments', $col)) {
                    $pdo->exec("ALTER TABLE zimrx_appointments ADD COLUMN {$col} {$def}");
                }
            }
        }

        // ── 3. zimrx_visits — visit-identity column set ───────────────────────
        if (DbSchema::tableExists($pdo, 'zimrx_visits') &&
            !DbSchema::isView($pdo, 'zimrx_visits')) {

            if (DbSchema::columnExists($pdo, 'zimrx_visits', 'visit_code') &&
                !DbSchema::columnExists($pdo, 'zimrx_visits', 'visit_id')) {
                try {
                    $pdo->exec('ALTER TABLE zimrx_visits RENAME COLUMN visit_code TO visit_id');
                } catch (Throwable) { /* already renamed */ }
            }

            $visitsColumns = [
                'doctor_id'               => 'INTEGER NOT NULL DEFAULT 1',
                'appointment_id'          => 'INTEGER',
                'visit_id'                => 'TEXT',
                'referral_category'       => "TEXT NOT NULL DEFAULT 'self'",
                'referral_name'           => 'TEXT',
                'prescription_html'       => 'TEXT',
                'clinical_snapshot_json'  => 'TEXT',
            ];
            foreach ($visitsColumns as $col => $def) {
                if (!DbSchema::columnExists($pdo, 'zimrx_visits', $col)) {
                    $pdo->exec("ALTER TABLE zimrx_visits ADD COLUMN {$col} {$def}");
                }
            }
        }

        // ── 4. zimrx_user_patient_referrals — visit-identity column set ───────
        if (DbSchema::tableExists($pdo, 'zimrx_user_patient_referrals')) {
            if (DbSchema::columnExists($pdo, 'zimrx_user_patient_referrals', 'visit_id') &&
                !DbSchema::columnExists($pdo, 'zimrx_user_patient_referrals', 'visit_record_id')) {
                try {
                    $pdo->exec('ALTER TABLE zimrx_user_patient_referrals RENAME COLUMN visit_id TO visit_record_id');
                } catch (Throwable) { /* already renamed */ }
            }
            if (DbSchema::columnExists($pdo, 'zimrx_user_patient_referrals', 'visit_code') &&
                !DbSchema::columnExists($pdo, 'zimrx_user_patient_referrals', 'visit_id')) {
                try {
                    $pdo->exec('ALTER TABLE zimrx_user_patient_referrals RENAME COLUMN visit_code TO visit_id');
                } catch (Throwable) { /* already renamed */ }
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_user_patient_referrals', 'visit_record_id')) {
                $pdo->exec('ALTER TABLE zimrx_user_patient_referrals ADD COLUMN visit_record_id INTEGER');
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_user_patient_referrals', 'visit_id')) {
                $pdo->exec('ALTER TABLE zimrx_user_patient_referrals ADD COLUMN visit_id TEXT');
            }
        }
    }
}
