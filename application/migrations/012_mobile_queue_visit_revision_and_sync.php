<?php
/**
 * Migration 012 — Mobile upload queue, visit revision locking, and patient sync schema
 */
class Migration012MobileQueueVisitRevisionAndSync {

    public function up(PDO $pdo): void {
        // 1. Mobile Upload Queue (patient-bound, transactional queue)
        // NOTE: This CREATE TABLE already includes patient_id, visit_record_id, and
        // active_revision so new installations get the full schema from day one.
        // Existing installations that applied 012 before those columns were added
        // receive them via migration 013 (the immutable backfill migration).
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_mobile_upload_queue (
                id TEXT PRIMARY KEY,
                doctor_id " . DbSql::intType() . " NOT NULL,
                patient_id " . DbSql::intType() . " NULL,
                visit_record_id " . DbSql::intType() . " NULL,
                active_revision " . DbSql::intType() . " NOT NULL DEFAULT 1,
                file_path TEXT NOT NULL,
                original_name TEXT NOT NULL,
                report_name TEXT NOT NULL,
                report_date TEXT NOT NULL,
                created_at " . DbSql::timestampColumn() . ",
                claimed_at TEXT NULL
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mobile_upload_queue_pending ON zimrx_mobile_upload_queue (doctor_id, claimed_at, created_at)");

        // 2. Add revision and sync_id columns to zimrx_visits for optimistic locking & sync
        if (DbSchema::tableExists($pdo, 'zimrx_visits')) {
            if (!DbSchema::columnExists($pdo, 'zimrx_visits', 'sync_id')) {
                $pdo->exec("ALTER TABLE zimrx_visits ADD COLUMN sync_id TEXT NULL");
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_visits', 'revision')) {
                $pdo->exec("ALTER TABLE zimrx_visits ADD COLUMN revision " . DbSql::intType() . " NOT NULL DEFAULT 1");
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_visits', 'deleted_at')) {
                $pdo->exec("ALTER TABLE zimrx_visits ADD COLUMN deleted_at TEXT NULL");
            }
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_visits_sync_id ON zimrx_visits(sync_id)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_visit_patient_doctor_number ON zimrx_visits(patient_id, doctor_id, visit_no)");
        }

        // 3. Add delta-sync fields to zimrx_patients
        if (DbSchema::tableExists($pdo, 'zimrx_patients')) {
            if (!DbSchema::columnExists($pdo, 'zimrx_patients', 'sync_id')) {
                $pdo->exec("ALTER TABLE zimrx_patients ADD COLUMN sync_id TEXT NULL");
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_patients', 'revision')) {
                $pdo->exec("ALTER TABLE zimrx_patients ADD COLUMN revision " . DbSql::intType() . " NOT NULL DEFAULT 1");
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_patients', 'deleted_at')) {
                $pdo->exec("ALTER TABLE zimrx_patients ADD COLUMN deleted_at TEXT NULL");
            }
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_patients_sync_id ON zimrx_patients(sync_id)");
        }

        // 4. Backfill stable RFC 4122 v4 UUIDs for all existing records lacking sync_id
        $uuidGen = static function (): string {
            $data = random_bytes(16);
            $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
            $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        };

        if (DbSchema::tableExists($pdo, 'zimrx_patients') && DbSchema::columnExists($pdo, 'zimrx_patients', 'sync_id')) {
            $stmt = $pdo->query("SELECT id FROM zimrx_patients WHERE sync_id IS NULL OR sync_id = ''");
            $pIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($pIds)) {
                $up = $pdo->prepare("UPDATE zimrx_patients SET sync_id = :sync_id WHERE id = :id");
                foreach ($pIds as $pId) {
                    $up->execute(['sync_id' => $uuidGen(), 'id' => $pId]);
                }
            }
        }

        if (DbSchema::tableExists($pdo, 'zimrx_visits') && DbSchema::columnExists($pdo, 'zimrx_visits', 'sync_id')) {
            $stmt = $pdo->query("SELECT id FROM zimrx_visits WHERE sync_id IS NULL OR sync_id = ''");
            $vIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($vIds)) {
                $up = $pdo->prepare("UPDATE zimrx_visits SET sync_id = :sync_id WHERE id = :id");
                foreach ($vIds as $vId) {
                    $up->execute(['sync_id' => $uuidGen(), 'id' => $vId]);
                }
            }
        }

        // 5. Create delta-sync changes journal
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_sync_changes (
                sequence " . DbSql::autoIncrement() . ",
                entity_type TEXT NOT NULL,
                entity_sync_id TEXT NOT NULL,
                operation TEXT NOT NULL CHECK(operation IN ('insert', 'update', 'delete')),
                base_revision " . DbSql::intType() . " NOT NULL,
                new_revision " . DbSql::intType() . " NOT NULL,
                payload_json TEXT NOT NULL,
                device_id TEXT NOT NULL,
                created_at " . DbSql::timestampColumn() . "
            )"
        );
    }
}
