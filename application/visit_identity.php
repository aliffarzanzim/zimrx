<?php

function zimrx_column_exists(PDO $pdo, string $table, string $column): bool {
    return DbSchema::tableExists($pdo, $table) && DbSchema::columnExists($pdo, $table, $column);
}

function zimrx_rename_column_if_exists(PDO $pdo, string $table, string $oldColumn, string $newColumn, string $definition): void {
    if (!DbSchema::tableExists($pdo, $table)
        || DbSchema::isView($pdo, $table)
        || !DbSchema::columnExists($pdo, $table, $oldColumn)
        || DbSchema::columnExists($pdo, $table, $newColumn)
    ) {
        return;
    }

    try {
        $pdo->exec("ALTER TABLE $table RENAME COLUMN $oldColumn TO $newColumn");
    } catch (Throwable $e) {
        zimrx_db_ensure_column($pdo, $table, $newColumn, $definition);
        $pdo->exec("UPDATE $table SET $newColumn = $oldColumn WHERE $newColumn IS NULL OR $newColumn = ''");
    }
}

function zimrx_ensure_visit_identity_schema(PDO $pdo): void {
    foreach (['zimrx_appointments'] as $table) {
        if (!DbSchema::tableExists($pdo, $table) || DbSchema::isView($pdo, $table)) {
            continue;
        }
        zimrx_rename_column_if_exists($pdo, $table, 'visit_id', 'visit_record_id', 'INTEGER');
        zimrx_rename_column_if_exists($pdo, $table, 'visit_code', 'visit_id', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'visit_record_id', 'INTEGER');
        zimrx_db_ensure_column($pdo, $table, 'visit_id', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'referral_category', "TEXT NOT NULL DEFAULT 'self'");
        zimrx_db_ensure_column($pdo, $table, 'referral_name', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'visit_fee', 'REAL');
        zimrx_db_ensure_column($pdo, $table, 'discount', 'REAL');
        zimrx_db_ensure_column($pdo, $table, 'discount_note', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'paid_amount', 'REAL');
        zimrx_db_ensure_column($pdo, $table, 'payment_updated_at', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'bp', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'pulse', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'temperature', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'spo2', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'resp_rate', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'vitals_note', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'vitals_entered_by', 'INTEGER');
        zimrx_db_ensure_column($pdo, $table, 'vitals_entered_at', 'TEXT');
    }

    foreach (['zimrx_visits'] as $table) {
        if (!DbSchema::tableExists($pdo, $table) || DbSchema::isView($pdo, $table)) {
            continue;
        }
        zimrx_rename_column_if_exists($pdo, $table, 'visit_code', 'visit_id', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'doctor_id', 'INTEGER NOT NULL DEFAULT 1');
        zimrx_db_ensure_column($pdo, $table, 'appointment_id', 'INTEGER');
        zimrx_db_ensure_column($pdo, $table, 'visit_id', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'referral_category', "TEXT NOT NULL DEFAULT 'self'");
        zimrx_db_ensure_column($pdo, $table, 'referral_name', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'prescription_html', 'TEXT');
        zimrx_db_ensure_column($pdo, $table, 'clinical_snapshot_json', 'TEXT');
    }

    if (DbSchema::tableExists($pdo, 'zimrx_user_patient_referrals')) {
        zimrx_rename_column_if_exists($pdo, 'zimrx_user_patient_referrals', 'visit_id', 'visit_record_id', 'INTEGER');
        zimrx_rename_column_if_exists($pdo, 'zimrx_user_patient_referrals', 'visit_code', 'visit_id', 'TEXT');
        zimrx_db_ensure_column($pdo, 'zimrx_user_patient_referrals', 'visit_record_id', 'INTEGER');
        zimrx_db_ensure_column($pdo, 'zimrx_user_patient_referrals', 'visit_id', 'TEXT');
    }
}
