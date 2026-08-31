<?php
declare(strict_types=1);

// Extended demographics schema: email, marital status, NID, passport, metadata JSON, and medico-legal DOB type.
class Migration017ExtendedPatientDemographics {

    public function up(PDO $pdo): void {
        if (DbSchema::tableExists($pdo, 'zimrx_patients') && !DbSchema::isView($pdo, 'zimrx_patients')) {
            $patientCols = [
                'dob_type'                => "TEXT DEFAULT 'exact'",
                'marital_status'          => 'TEXT',
                'email'                   => 'TEXT',
                'nid_no'                  => 'TEXT',
                'passport_no'             => 'TEXT',
                'extra_demographics_json' => "TEXT DEFAULT '{}'",
            ];

            foreach ($patientCols as $col => $def) {
                if (!DbSchema::columnExists($pdo, 'zimrx_patients', $col)) {
                    $pdo->exec("ALTER TABLE zimrx_patients ADD COLUMN {$col} {$def}");
                }
            }

            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patients_nid ON zimrx_patients(nid_no)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patients_passport ON zimrx_patients(passport_no)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_patients_email ON zimrx_patients(email)");
        }

        if (DbSchema::tableExists($pdo, 'zimrx_appointments') && !DbSchema::isView($pdo, 'zimrx_appointments')) {
            $apptCols = [
                'dob_type'                => "TEXT DEFAULT 'exact'",
                'marital_status'          => 'TEXT',
                'email'                   => 'TEXT',
                'nid_no'                  => 'TEXT',
                'passport_no'             => 'TEXT',
                'extra_demographics_json' => "TEXT DEFAULT '{}'",
            ];

            foreach ($apptCols as $col => $def) {
                if (!DbSchema::columnExists($pdo, 'zimrx_appointments', $col)) {
                    $pdo->exec("ALTER TABLE zimrx_appointments ADD COLUMN {$col} {$def}");
                }
            }
        }

        if (DbSchema::tableExists($pdo, 'zimrx_visits') && !DbSchema::isView($pdo, 'zimrx_visits')) {
            $visitCols = [
                'age_unit_at_visit' => "TEXT DEFAULT 'Years'",
                'dob_at_visit'      => 'TEXT',
                'dob_type_at_visit' => "TEXT DEFAULT 'exact'",
            ];

            foreach ($visitCols as $col => $def) {
                if (!DbSchema::columnExists($pdo, 'zimrx_visits', $col)) {
                    $pdo->exec("ALTER TABLE zimrx_visits ADD COLUMN {$col} {$def}");
                }
            }
        }
    }
}
