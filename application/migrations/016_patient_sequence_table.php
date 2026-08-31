<?php
declare(strict_types=1);

// Migration 016: introduces zimrx_patient_sequences for atomic, race-condition-free patient registration numbers.
class Migration016PatientSequenceTable {

    public function up(PDO $pdo): void {
        if (!DbSchema::tableExists($pdo, 'zimrx_patient_sequences')) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS zimrx_patient_sequences (
                    id " . DbSql::autoIncrement() . ",
                    doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                    prefix TEXT NOT NULL,
                    last_seq " . DbSql::intType() . " NOT NULL DEFAULT 0,
                    updated_at " . DbSql::timestampColumn() . ",
                    UNIQUE(doctor_id, prefix)
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patient_sequences_lookup ON zimrx_patient_sequences(doctor_id, prefix)");
        }
    }
}
