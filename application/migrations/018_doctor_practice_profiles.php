<?php
declare(strict_types=1);

final class Migration018DoctorPracticeProfiles
{
    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_doctor_profiles (
                id " . DbSql::autoIncrement() . ",
                profile_uuid TEXT NOT NULL UNIQUE,
                doctor_id " . DbSql::intType() . " NOT NULL,
                profile_name TEXT NOT NULL,
                address TEXT NOT NULL DEFAULT '',
                phone TEXT NOT NULL DEFAULT '',
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                is_default " . DbSql::intType() . " NOT NULL DEFAULT 0,
                share_patient_particulars " . DbSql::intType() . " NOT NULL DEFAULT 0,
                share_emr " . DbSql::intType() . " NOT NULL DEFAULT 0,
                share_prescriptions " . DbSql::intType() . " NOT NULL DEFAULT 0,
                settings_json TEXT NOT NULL DEFAULT '{}',
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                archived_at TEXT,
                UNIQUE(doctor_id, profile_name),
                FOREIGN KEY (doctor_id) REFERENCES zimrx_doctors(id)
            )"
        );

        $doctors = $pdo->query("SELECT id FROM zimrx_doctors ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $seed = $pdo->prepare(
            "INSERT INTO zimrx_doctor_profiles
                (profile_uuid, doctor_id, profile_name, is_active, is_default, created_at, updated_at)
             SELECT :uuid, :doctor_id_insert, 'Main Practice', 1, 1, " . DbSql::now() . ', ' . DbSql::now() . '
             WHERE NOT EXISTS (
                SELECT 1 FROM zimrx_doctor_profiles WHERE doctor_id = :doctor_id_check AND is_default = 1
             )'
        );

        foreach ($doctors as $doctorId) {
            $seed->execute([
                'uuid' => self::uuid(),
                'doctor_id_insert' => (int)$doctorId,
                'doctor_id_check' => (int)$doctorId,
            ]);
        }

        $profileScopedTables = [
            'zimrx_patients',
            'zimrx_patients_change_log',
            'zimrx_patient_particulars_audit',
            'zimrx_patient_metric_readings',
            'zimrx_appointments',
            'zimrx_payments',
            'zimrx_visit_vitals',
            'zimrx_visits',
            'zimrx_visit_revisions',
            'zimrx_patient_sequences',
        ];

        foreach ($profileScopedTables as $table) {
            if (!DbSchema::tableExists($pdo, $table) || DbSchema::isView($pdo, $table)) {
                continue;
            }
            if (!DbSchema::columnExists($pdo, $table, 'profile_id')) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN profile_id " . DbSql::intType());
            }
            if (DbSchema::columnExists($pdo, $table, 'doctor_id')) {
                $pdo->exec(
                    "UPDATE {$table}
                     SET profile_id = (
                         SELECT p.id
                         FROM zimrx_doctor_profiles p
                     WHERE p.doctor_id = {$table}.doctor_id
                           AND p.is_default = 1
                         LIMIT 1
                     )
                     WHERE profile_id IS NULL"
                );
            }
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$table}_profile ON {$table}(profile_id)");
        }

        if (DbSchema::tableExists($pdo, 'zimrx_appointments')) {
            $driver = DbConnections::driver();
            if ($driver === 'mysql') {
                if (self::mysqlIndexExists($pdo, 'zimrx_appointments', 'uid_appointments_doctor_date_no')) {
                    $pdo->exec('DROP INDEX uid_appointments_doctor_date_no ON zimrx_appointments');
                }
                if (!self::mysqlIndexExists($pdo, 'zimrx_appointments', 'uid_appointments_profile_date_no')) {
                    $pdo->exec(
                        'CREATE UNIQUE INDEX uid_appointments_profile_date_no
                         ON zimrx_appointments(profile_id, appointment_date, appointment_no)'
                    );
                }
            } else {
                $pdo->exec('DROP INDEX IF EXISTS uid_appointments_doctor_date_no');
                $partialClause = in_array($driver, ['sqlite', 'pgsql'], true) ? ' WHERE profile_id IS NOT NULL' : '';
                $pdo->exec(
                    'CREATE UNIQUE INDEX IF NOT EXISTS uid_appointments_profile_date_no
                     ON zimrx_appointments(profile_id, appointment_date, appointment_no)' . $partialClause
                );
            }
        }

        $pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_doctor_profiles_owner_active
             ON zimrx_doctor_profiles(doctor_id, is_active, profile_name)"
        );
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private static function mysqlIndexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :table_name AND index_name = :index_name
             LIMIT 1'
        );
        $stmt->execute(['table_name' => $table, 'index_name' => $index]);
        return $stmt->fetchColumn() !== false;
    }
}
