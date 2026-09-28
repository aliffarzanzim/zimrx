<?php
/**
 * Migration 013 — Backfill: Mobile queue context columns, sync UUIDs, and delta-sync journal
 *
 * Migration 012 was shipped containing both CREATE TABLE and ALTER TABLE guards.
 * Any installation that applied 012 in its original form (before patient_id / visit_record_id /
 * active_revision were added) already has version "012" recorded in schema_migrations and will
 * never re-run 012.  This migration delivers those missing columns and indexes as a safe,
 * idempotent backfill for all such installations.
 *
 * New installations (which apply 012 in its current form first) will see all guards here
 * evaluate to false (columns already exist) and this migration becomes a safe no-op.
 */
class Migration013MobileQueueContextAndSyncBackfill {

    public function up(PDO $pdo): void {
        // ── 1. Backfill missing columns on zimrx_mobile_upload_queue ─────────────────
        if (DbSchema::tableExists($pdo, 'zimrx_mobile_upload_queue')) {
            if (!DbSchema::columnExists($pdo, 'zimrx_mobile_upload_queue', 'patient_id')) {
                $pdo->exec("ALTER TABLE zimrx_mobile_upload_queue ADD COLUMN patient_id " . DbSql::intType() . " NULL");
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_mobile_upload_queue', 'visit_record_id')) {
                $pdo->exec("ALTER TABLE zimrx_mobile_upload_queue ADD COLUMN visit_record_id " . DbSql::intType() . " NULL");
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_mobile_upload_queue', 'active_revision')) {
                $pdo->exec("ALTER TABLE zimrx_mobile_upload_queue ADD COLUMN active_revision " . DbSql::intType() . " NOT NULL DEFAULT 1");
            }
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mobile_upload_queue_pending ON zimrx_mobile_upload_queue (doctor_id, claimed_at, created_at)");
        }

        // ── 2. Backfill sync_id / revision / deleted_at on zimrx_visits ─────────────
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

        // ── 3. Backfill sync_id / revision / deleted_at on zimrx_patients ────────────
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

        // ── 3b. Backfill sync_id / revision / deleted_at on zimrx_patient_metric_readings ─
        if (DbSchema::tableExists($pdo, 'zimrx_patient_metric_readings')) {
            if (!DbSchema::columnExists($pdo, 'zimrx_patient_metric_readings', 'sync_id')) {
                $pdo->exec("ALTER TABLE zimrx_patient_metric_readings ADD COLUMN sync_id TEXT NULL");
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_patient_metric_readings', 'revision')) {
                $pdo->exec("ALTER TABLE zimrx_patient_metric_readings ADD COLUMN revision " . DbSql::intType() . " NOT NULL DEFAULT 1");
            }
            if (!DbSchema::columnExists($pdo, 'zimrx_patient_metric_readings', 'deleted_at')) {
                $pdo->exec("ALTER TABLE zimrx_patient_metric_readings ADD COLUMN deleted_at TEXT NULL");
            }
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_metric_readings_sync_id ON zimrx_patient_metric_readings(sync_id)");
        }

        // ── 4. Backfill RFC 4122 v4 UUIDs for records that still lack sync_id ────────
        $uuidGen = static function (): string {
            $data = random_bytes(16);
            $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
            $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        };

        if (DbSchema::tableExists($pdo, 'zimrx_patients') && DbSchema::columnExists($pdo, 'zimrx_patients', 'sync_id')) {
            $stmt = $pdo->query("SELECT id FROM zimrx_patients WHERE sync_id IS NULL OR sync_id = ''");
            $ids  = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($ids)) {
                $up = $pdo->prepare("UPDATE zimrx_patients SET sync_id = :sync_id WHERE id = :id");
                foreach ($ids as $id) {
                    $up->execute(['sync_id' => $uuidGen(), 'id' => $id]);
                }
            }
        }

        if (DbSchema::tableExists($pdo, 'zimrx_visits') && DbSchema::columnExists($pdo, 'zimrx_visits', 'sync_id')) {
            $stmt = $pdo->query("SELECT id FROM zimrx_visits WHERE sync_id IS NULL OR sync_id = ''");
            $ids  = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($ids)) {
                $up = $pdo->prepare("UPDATE zimrx_visits SET sync_id = :sync_id WHERE id = :id");
                foreach ($ids as $id) {
                    $up->execute(['sync_id' => $uuidGen(), 'id' => $id]);
                }
            }
        }

        if (DbSchema::tableExists($pdo, 'zimrx_patient_metric_readings') && DbSchema::columnExists($pdo, 'zimrx_patient_metric_readings', 'sync_id')) {
            $stmt = $pdo->query("SELECT id FROM zimrx_patient_metric_readings WHERE sync_id IS NULL OR sync_id = ''");
            $ids  = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($ids)) {
                $up = $pdo->prepare("UPDATE zimrx_patient_metric_readings SET sync_id = :sync_id WHERE id = :id");
                foreach ($ids as $id) {
                    $up->execute(['sync_id' => $uuidGen(), 'id' => $id]);
                }
            }
        }

        // ── 5. Create delta-sync changes journal (safe no-op if already exists) ──────
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

        // ── 6. Create transactional mobile active context table ───────────────────
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_mobile_active_context (
                doctor_id " . DbSql::intType() . " PRIMARY KEY,
                patient_id " . DbSql::intType() . " NULL,
                visit_record_id " . DbSql::intType() . " NULL,
                active_revision " . DbSql::intType() . " NOT NULL DEFAULT 1,
                patient_name TEXT NULL,
                patient_reg TEXT NULL,
                patient_age TEXT NULL,
                patient_gender TEXT NULL,
                patient_date TEXT NULL,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
    }
}
