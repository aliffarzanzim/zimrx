<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../visit_identity.php';
require_once __DIR__ . '/../emr_identity_lib.php';

header('Content-Type: application/json');

function respond(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function value(array $data, string $key): string {
    return trim((string)($data[$key] ?? ''));
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    return DbSchema::columnExists($pdo, $table, $column);
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function doctor_code_for_visit(PDO $pdo, int $doctorId): string {
    $stmt = $pdo->prepare("SELECT doctor_code FROM zimrx_doctors WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $doctorId]);
    $code = strtoupper(trim((string)$stmt->fetchColumn()));
    return $code !== '' ? $code : 'D' . str_pad((string)$doctorId, 3, '0', STR_PAD_LEFT);
}

function format_visit_id(string $regNo, int $visitNo, string $doctorCode): string {
    global $pdo;
    return isset($pdo) ? zimrx_generate_visit_id($pdo) : ('V' . date('ymd') . str_pad((string)$visitNo, 3, '0', STR_PAD_LEFT));
}

function next_visit_no(PDO $pdo, int $patientId, int $doctorId): int {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(visit_no), 0) + 1
         FROM zimrx_visits
         WHERE doctor_id = :doctor_id AND patient_id = :patient_id"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'patient_id' => $patientId]);
    return max(1, (int)$stmt->fetchColumn());
}

function visit_no_is_free(PDO $pdo, int $patientId, int $visitNo, int $doctorId): bool {
    if ($patientId <= 0 || $visitNo <= 0) {
        return false;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM zimrx_visits
         WHERE doctor_id = :doctor_id AND patient_id = :patient_id AND visit_no = :visit_no"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'patient_id' => $patientId, 'visit_no' => $visitNo]);
    return (int)$stmt->fetchColumn() === 0;
}

function ensure_visit_schema(PDO $pdo): void {
    ensure_column($pdo, 'zimrx_appointments', 'doctor_id', 'INTEGER NOT NULL DEFAULT 1');
    ensure_column($pdo, 'zimrx_appointments', 'visit_record_id', 'INTEGER');
    ensure_column($pdo, 'zimrx_appointments', 'visit_id', 'TEXT');
    ensure_column($pdo, 'zimrx_appointments', 'referral_category', "TEXT NOT NULL DEFAULT 'self'");
    ensure_column($pdo, 'zimrx_appointments', 'referral_name', 'TEXT');
    ensure_column($pdo, 'zimrx_visits', 'doctor_id', 'INTEGER NOT NULL DEFAULT 1');
    ensure_column($pdo, 'zimrx_visits', 'appointment_id', 'INTEGER');
    ensure_column($pdo, 'zimrx_visits', 'referral_category', "TEXT NOT NULL DEFAULT 'self'");
    ensure_column($pdo, 'zimrx_visits', 'referral_name', 'TEXT');
    ensure_column($pdo, 'zimrx_visits', 'prescription_html', 'TEXT');
    ensure_column($pdo, 'zimrx_visits', 'clinical_snapshot_json', 'TEXT');
    zimrx_ensure_visit_identity_schema($pdo);
    $visitIndexTable = 'zimrx_visits';
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_appointment_id ON $visitIndexTable(appointment_id) WHERE appointment_id IS NOT NULL");
    $pdo->exec("DROP INDEX IF EXISTS idx_visits_patient_visit_no");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_doctor_patient_visit_no ON $visitIndexTable(doctor_id, patient_id, visit_no) WHERE visit_no IS NOT NULL");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_visit_id ON $visitIndexTable(visit_id) WHERE visit_id IS NOT NULL");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_doctor_patient ON $visitIndexTable(doctor_id, patient_id, visit_no)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_referrals ON zimrx_visits(doctor_id, referral_category, referral_name)");
}

function ensure_patient_referrals_schema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_user_patient_referrals (
            id " . DbSql::autoIncrement() . ",
            doctor_id INTEGER NOT NULL DEFAULT 1,
            patient_reg_no TEXT,
            visit_record_id INTEGER,
            visit_id TEXT,
            category TEXT NOT NULL DEFAULT 'self',
            referral_name TEXT,
            normalized_name TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    foreach ([
        'doctor_id' => 'INTEGER NOT NULL DEFAULT 1',
        'patient_reg_no' => 'TEXT',
        'visit_record_id' => 'INTEGER',
        'visit_id' => 'TEXT',
        'category' => "TEXT NOT NULL DEFAULT 'self'",
        'referral_name' => 'TEXT',
        'normalized_name' => 'TEXT',
        'created_at' => 'TEXT',
        'updated_at' => 'TEXT',
    ] as $column => $definition) {
        zimrx_db_ensure_column($pdo, 'zimrx_user_patient_referrals', $column, $definition);
    }

    zimrx_ensure_visit_identity_schema($pdo);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_referrals_visit_record ON zimrx_user_patient_referrals(doctor_id, visit_record_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_referrals_suggestions ON zimrx_user_patient_referrals(doctor_id, category, normalized_name)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_patient_referrals_doctor_visit_record ON zimrx_user_patient_referrals(doctor_id, visit_record_id) WHERE visit_record_id IS NOT NULL");
}

function ensure_visit_revisions_schema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_visit_revisions (
            id " . DbSql::autoIncrement() . ",
            visit_record_id INTEGER NOT NULL,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            revision_no INTEGER NOT NULL DEFAULT 1,
            patient_id INTEGER,
            patient_reg_no TEXT,
            visit_no INTEGER,
            visit_id TEXT,
            clinical_snapshot_json TEXT,
            prescription_html TEXT,
            rich_text_json TEXT,
            billing_json TEXT,
            reason TEXT,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visit_revisions_lookup ON zimrx_visit_revisions(doctor_id, visit_record_id, revision_no)");
}

function archive_visit_revision_if_changed(PDO $pdo, array $existingVisit, ?string $newSnapshotJson, ?string $newHtml, ?string $newRichText, int $doctorId, ?int $userId): void {
    $oldSnapshot = (string)($existingVisit['clinical_snapshot_json'] ?? '');
    $oldHtml = (string)($existingVisit['prescription_html'] ?? '');
    $oldRich = (string)($existingVisit['rich_text_json'] ?? '');

    $hasChanges = false;
    if ($newSnapshotJson !== null && $newSnapshotJson !== '' && $newSnapshotJson !== $oldSnapshot && $oldSnapshot !== '') {
        $hasChanges = true;
    }
    if ($newHtml !== '' && $newHtml !== $oldHtml && $oldHtml !== '') {
        $hasChanges = true;
    }
    if ($newRichText !== '' && $newRichText !== $oldRich && $oldRich !== '' && $oldRich !== '{"rx_drugs":[]}') {
        $hasChanges = true;
    }

    if (!$hasChanges) {
        return;
    }

    $visitRecordId = (int)$existingVisit['id'];
    $stmtCount = $pdo->prepare("SELECT COALESCE(MAX(revision_no), 0) + 1 FROM zimrx_visit_revisions WHERE visit_record_id = :id AND doctor_id = :doctor_id");
    $stmtCount->execute(['id' => $visitRecordId, 'doctor_id' => $doctorId]);
    $nextRevNo = max(1, (int)$stmtCount->fetchColumn());

    $stmtInsertRev = $pdo->prepare(
        "INSERT INTO zimrx_visit_revisions (
            visit_record_id, doctor_id, revision_no, patient_id, patient_reg_no, visit_no, visit_id,
            clinical_snapshot_json, prescription_html, rich_text_json, billing_json, created_by, created_at
        ) VALUES (
            :visit_record_id, :doctor_id, :revision_no, :patient_id, :patient_reg_no, :visit_no, :visit_id,
            :clinical_snapshot_json, :prescription_html, :rich_text_json, :billing_json, :created_by, :created_at
        )"
    );
    $stmtInsertRev->execute([
        'visit_record_id' => $visitRecordId,
        'doctor_id' => $doctorId,
        'revision_no' => $nextRevNo,
        'patient_id' => (int)($existingVisit['patient_id'] ?? 0),
        'patient_reg_no' => (string)($existingVisit['patient_reg_no'] ?? ''),
        'visit_no' => (int)($existingVisit['visit_no'] ?? 0),
        'visit_id' => (string)($existingVisit['visit_id'] ?? ''),
        'clinical_snapshot_json' => $oldSnapshot ?: null,
        'prescription_html' => $oldHtml ?: null,
        'rich_text_json' => $oldRich ?: null,
        'billing_json' => (string)($existingVisit['billing_json'] ?? ''),
        'created_by' => $userId,
        'created_at' => (string)($existingVisit['updated_at'] ?? $existingVisit['created_at'] ?? date('Y-m-d H:i:s')),
    ]);
}

function referral_category(string $value): string {
    $key = strtolower(trim($value));
    $key = str_replace(['-', ' '], '_', $key);
    return match ($key) {
        'doctor' => 'doctor',
        'other_patient' => 'other_patient',
        'others' => 'others',
        default => 'self',
    };
}

function normalize_referral_name(string $name): string {
    $normalized = preg_replace('/\s+/u', ' ', trim($name));
    return function_exists('mb_strtolower') ? mb_strtolower($normalized, 'UTF-8') : strtolower($normalized);
}

function clean_referral_name(string $category, string $name): string {
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if (!in_array($category, ['doctor', 'others'], true)) {
        return '';
    }

    if ($category === 'doctor') {
        if ($name === '' || preg_match('/^dr\.?$/i', $name)) {
            return '';
        }
        if (!preg_match('/^dr\.\s*/i', $name)) {
            $name = 'Dr. ' . $name;
        }
    }

    return $name;
}

function payload_referral(array $payload): array {
    $referral = is_array($payload['referral'] ?? null) ? $payload['referral'] : [];
    $rawName = trim((string)($referral['name'] ?? $payload['referral_name'] ?? $payload['ref_by'] ?? ''));
    $rawCategory = trim((string)($referral['category'] ?? $payload['referral_category'] ?? ''));

    if ($rawCategory === '' && $rawName !== '') {
        $rawCategory = 'doctor';
    }

    $category = referral_category($rawCategory);
    $name = clean_referral_name($category, $rawName);

    return [
        'category' => $category,
        'name' => $name,
        'normalized_name' => $name !== '' ? normalize_referral_name($name) : '',
    ];
}

function save_patient_referral(PDO $pdo, int $doctorId, string $regNo, int $visitRecordId, string $visitId, array $payload): void {
    if ($visitRecordId <= 0) {
        return;
    }

    $referral = payload_referral($payload);
    $params = [
        'doctor_id' => $doctorId,
        'patient_reg_no' => $regNo,
        'visit_record_id' => $visitRecordId,
        'visit_id' => $visitId,
        'category' => $referral['category'],
        'referral_name' => $referral['name'],
        'normalized_name' => $referral['normalized_name'],
    ];

    $stmt = $pdo->prepare(
        "UPDATE zimrx_user_patient_referrals
         SET patient_reg_no = :patient_reg_no,
             visit_id = :visit_id,
             category = :category,
             referral_name = :referral_name,
             normalized_name = :normalized_name,
             updated_at = CURRENT_TIMESTAMP
         WHERE doctor_id = :doctor_id AND visit_record_id = :visit_record_id"
    );
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_user_patient_referrals (
            doctor_id, patient_reg_no, visit_record_id, visit_id, category,
            referral_name, normalized_name, created_at, updated_at
        ) VALUES (
            :doctor_id, :patient_reg_no, :visit_record_id, :visit_id, :category,
            :referral_name, :normalized_name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )"
    );
    $stmt->execute($params);
}

function backfill_visit_referrals(PDO $pdo): void {
    if (!DbSchema::tableExists($pdo, 'zimrx_user_patient_referrals')) {
        return;
    }

    $pdo->exec(
        "UPDATE zimrx_visits
         SET referral_category = COALESCE((
                 SELECT r.category
                 FROM zimrx_user_patient_referrals r
                 WHERE r.doctor_id = zimrx_visits.doctor_id
                   AND r.visit_record_id = zimrx_visits.id
                 LIMIT 1
             ), referral_category),
             referral_name = COALESCE((
                 SELECT r.referral_name
                 FROM zimrx_user_patient_referrals r
                 WHERE r.doctor_id = zimrx_visits.doctor_id
                   AND r.visit_record_id = zimrx_visits.id
                 LIMIT 1
             ), referral_name)
         WHERE (referral_name IS NULL OR referral_name = '')
           AND EXISTS (
               SELECT 1
               FROM zimrx_user_patient_referrals r
               WHERE r.doctor_id = zimrx_visits.doctor_id
                 AND r.visit_record_id = zimrx_visits.id
                 AND COALESCE(r.referral_name, '') <> ''
           )"
    );

    $pdo->exec(
        "UPDATE zimrx_visits
         SET referral_category = 'doctor',
             referral_name = referred_by
         WHERE COALESCE(referral_name, '') = ''
           AND COALESCE(referred_by, '') <> ''"
    );
}

function load_appointment(PDO $pdo, int $appointmentId, int $doctorId): ?array {
    if ($appointmentId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT
            a.*,
            coalesce(nullif(a.reg_no, ''), p.reg_no, '') AS resolved_reg_no,
            coalesce(nullif(a.patient_name, ''), p.full_name, '') AS resolved_patient_name,
            coalesce(nullif(a.age, ''), p.age, '') AS resolved_age,
            coalesce(nullif(a.weight, ''), p.weight, '') AS resolved_weight,
            coalesce(nullif(a.weight_unit, ''), p.weight_unit, 'kg') AS resolved_weight_unit,
            coalesce(nullif(a.height, ''), p.height, '') AS resolved_height,
            coalesce(nullif(a.height_unit, ''), p.height_unit, 'inch') AS resolved_height_unit
         FROM zimrx_appointments a
         LEFT JOIN zimrx_patients p ON p.id = a.patient_id
         WHERE a.id = :id AND a.doctor_id = :doctor_id
         LIMIT 1"
    );
    $stmt->execute(['id' => $appointmentId, 'doctor_id' => $doctorId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function load_patient(PDO $pdo, int $patientId, int $doctorId): ?array {
    if ($patientId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT *
         FROM zimrx_patients p
         WHERE p.id = :id
           AND (
                COALESCE(NULLIF(p.doctor_id, 0), 1) = :doctor_id
                OR EXISTS (
                    SELECT 1
                    FROM zimrx_patient_doctor_access pda
                    WHERE pda.patient_id = p.id
                      AND pda.doctor_id = :doctor_id
                      AND pda.can_view = 1
                )
           )
         LIMIT 1"
    );
    $stmt->execute(['id' => $patientId, 'doctor_id' => $doctorId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

try {
    ensure_visit_schema($pdo);
    ensure_visit_revisions_schema($pdo);
    ensure_patient_referrals_schema($pdo);
    backfill_visit_referrals($pdo);
    $doctorId = current_user_doctor_id();
    $userId = current_user_id();
    $doctorCode = doctor_code_for_visit($pdo, $doctorId);

    $rxDrugs = $payload['drugs'] ?? [];
    $richTextJson = json_encode(['rx_drugs' => is_array($rxDrugs) ? $rxDrugs : []], JSON_UNESCAPED_UNICODE);
    $prescriptionHtml = trim((string)($payload['prescription_html'] ?? $payload['html'] ?? ''));
    $clinicalSnapshot = $payload['clinical_snapshot'] ?? $payload['snapshot'] ?? null;
    $clinicalSnapshotJson = is_array($clinicalSnapshot) ? json_encode($clinicalSnapshot, JSON_UNESCAPED_UNICODE) : (is_string($clinicalSnapshot) ? $clinicalSnapshot : null);

    $appointmentId = (int)value($payload, 'appointment_id');
    $appointment = load_appointment($pdo, $appointmentId, $doctorId);

    if ($appointment && !empty($appointment['visit_record_id'])) {
        $visitRecordId = (int)$appointment['visit_record_id'];
        $publicVisitId = (string)($appointment['visit_id'] ?? '');

        $stmtEx = $pdo->prepare("SELECT * FROM zimrx_visits WHERE id = :id AND doctor_id = :doctor_id LIMIT 1");
        $stmtEx->execute(['id' => $visitRecordId, 'doctor_id' => $doctorId]);
        $currentVisitRow = $stmtEx->fetch();
        if ($currentVisitRow) {
            archive_visit_revision_if_changed($pdo, $currentVisitRow, $clinicalSnapshotJson, $prescriptionHtml, $richTextJson, $doctorId, $userId);
        }

        // Update snapshots if provided
        $updateFields = [];
        $updateParams = ['id' => $visitRecordId, 'doctor_id' => $doctorId];
        if ($prescriptionHtml !== '') {
            $updateFields[] = "prescription_html = :prescription_html";
            $updateParams['prescription_html'] = $prescriptionHtml;
        }
        if ($clinicalSnapshotJson !== null && $clinicalSnapshotJson !== '') {
            $updateFields[] = "clinical_snapshot_json = :clinical_snapshot_json";
            $updateParams['clinical_snapshot_json'] = $clinicalSnapshotJson;
        }
        if (!empty($rxDrugs)) {
            $updateFields[] = "rich_text_json = :rich_text_json";
            $updateParams['rich_text_json'] = $richTextJson;
        }
        if (!empty($updateFields)) {
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $pdo->prepare("UPDATE zimrx_visits SET " . implode(', ', $updateFields) . " WHERE id = :id AND doctor_id = :doctor_id")->execute($updateParams);
        }

        respond([
            'ok' => true,
            'visit_record_id' => $visitRecordId,
            'visit_id' => $publicVisitId,
            'visit_no' => (int)($appointment['visit_no'] ?? 0),
            'visit_code' => $publicVisitId,
            'already_saved' => true,
        ]);
    }

    if ($appointmentId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM zimrx_visits WHERE appointment_id = :appointment_id AND doctor_id = :doctor_id LIMIT 1");
        $stmt->execute(['appointment_id' => $appointmentId, 'doctor_id' => $doctorId]);
        $existingVisit = $stmt->fetch();
        if ($existingVisit) {
            $visitRecordId = (int)$existingVisit['id'];
            archive_visit_revision_if_changed($pdo, $existingVisit, $clinicalSnapshotJson, $prescriptionHtml, $richTextJson, $doctorId, $userId);

            $updateFields = [];
            $updateParams = ['id' => $visitRecordId, 'doctor_id' => $doctorId];
            if ($prescriptionHtml !== '') {
                $updateFields[] = "prescription_html = :prescription_html";
                $updateParams['prescription_html'] = $prescriptionHtml;
            }
            if ($clinicalSnapshotJson !== null && $clinicalSnapshotJson !== '') {
                $updateFields[] = "clinical_snapshot_json = :clinical_snapshot_json";
                $updateParams['clinical_snapshot_json'] = $clinicalSnapshotJson;
            }
            if (!empty($rxDrugs)) {
                $updateFields[] = "rich_text_json = :rich_text_json";
                $updateParams['rich_text_json'] = $richTextJson;
            }
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
                $pdo->prepare("UPDATE zimrx_visits SET " . implode(', ', $updateFields) . " WHERE id = :id AND doctor_id = :doctor_id")->execute($updateParams);
            }

            $pdo->prepare("UPDATE zimrx_appointments SET visit_record_id = :visit_record_id WHERE id = :id AND doctor_id = :doctor_id")
                ->execute(['visit_record_id' => $visitRecordId, 'id' => $appointmentId, 'doctor_id' => $doctorId]);
            $publicVisitId = (string)($existingVisit['visit_id'] ?? '');
            respond([
                'ok' => true,
                'visit_record_id' => $visitRecordId,
                'visit_id' => $publicVisitId,
                'visit_no' => (int)($existingVisit['visit_no'] ?? 0),
                'visit_code' => $publicVisitId,
                'already_saved' => true,
            ]);
        }
    }

    $patientId = $appointment ? (int)$appointment['patient_id'] : (int)value($payload, 'patient_id');
    $patient = load_patient($pdo, $patientId, $doctorId);
    if (!$patient && !$appointment) {
        respond(['error' => 'A patient or appointment is required to save a visit.']);
    }

    $regNo = (string)($appointment['resolved_reg_no'] ?? $patient['reg_no'] ?? value($payload, 'reg_no'));
    $patientName = (string)($appointment['resolved_patient_name'] ?? $patient['full_name'] ?? '');
    $visitNo = (int)($appointment['visit_no'] ?? value($payload, 'visit_no'));
    if (!visit_no_is_free($pdo, $patientId, $visitNo, $doctorId)) {
        $visitNo = next_visit_no($pdo, $patientId, $doctorId);
    }
    $publicVisitId = (string)($appointment['visit_id'] ?? '');
    if ($publicVisitId === '') {
        $publicVisitId = value($payload, 'visit_id') ?: value($payload, 'visit_code');
    }
    if ($publicVisitId === '' || !visit_no_is_free($pdo, $patientId, (int)($appointment['visit_no'] ?? 0), $doctorId)) {
        $publicVisitId = format_visit_id($regNo, $visitNo, $doctorCode);
    }

    $payloadReferral = $payload['referral'] ?? [];
    $payloadReferralCategory = is_array($payloadReferral)
        ? referral_category((string)($payloadReferral['category'] ?? ''))
        : 'self';
    $appointmentReferralCategory = referral_category((string)($appointment['referral_category'] ?? ''));
    $appointmentReferralName = trim((string)($appointment['referral_name'] ?? ''));
    if ($payloadReferralCategory === 'self'
        && in_array($appointmentReferralCategory, ['doctor', 'others'], true)
        && $appointmentReferralName !== ''
    ) {
        $payload['referral'] = [
            'category' => $appointmentReferralCategory,
            'name' => $appointmentReferralName,
        ];
    }
    $visitReferral = payload_referral($payload);

    $billingJson = json_encode([
        'appointment_id' => $appointmentId ?: null,
        'visit_fee' => $appointment['visit_fee'] ?? null,
        'discount' => $appointment['discount'] ?? null,
        'paid_amount' => $appointment['paid_amount'] ?? null,
    ], JSON_UNESCAPED_UNICODE);

    $visitDate = $appointment && !empty($appointment['appointment_date'])
        ? trim((string)$appointment['appointment_date'] . ' ' . (string)($appointment['appointment_time'] ?? ''))
        : date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_visits (
            doctor_id, appointment_id, patient_id, patient_reg_no, patient_name, visit_no, visit_id,
            referral_category, referral_name, referred_by,
            visit_date, age_at_visit, height_at_visit, height_unit_at_visit,
            weight_at_visit, weight_unit_at_visit, billing_json, rich_text_json,
            prescription_html, clinical_snapshot_json
        ) VALUES (
            :doctor_id, :appointment_id, :patient_id, :patient_reg_no, :patient_name, :visit_no, :visit_id,
            :referral_category, :referral_name, :referred_by,
            :visit_date, :age_at_visit, :height_at_visit, :height_unit_at_visit,
            :weight_at_visit, :weight_unit_at_visit, :billing_json, :rich_text_json,
            :prescription_html, :clinical_snapshot_json
        )"
    );
    $stmt->execute([
        'doctor_id' => $doctorId,
        'appointment_id' => $appointmentId ?: null,
        'patient_id' => $patientId,
        'patient_reg_no' => $regNo,
        'patient_name' => $patientName,
        'visit_no' => $visitNo,
        'visit_id' => $publicVisitId,
        'referral_category' => $visitReferral['category'],
        'referral_name' => $visitReferral['name'],
        'referred_by' => $visitReferral['name'],
        'visit_date' => $visitDate,
        'age_at_visit' => $appointment['resolved_age'] ?? $patient['age'] ?? '',
        'height_at_visit' => $appointment['resolved_height'] ?? $patient['height'] ?? '',
        'height_unit_at_visit' => $appointment['resolved_height_unit'] ?? $patient['height_unit'] ?? '',
        'weight_at_visit' => $appointment['resolved_weight'] ?? $patient['weight'] ?? '',
        'weight_unit_at_visit' => $appointment['resolved_weight_unit'] ?? $patient['weight_unit'] ?? '',
        'billing_json' => $billingJson,
        'rich_text_json' => $richTextJson,
        'prescription_html' => $prescriptionHtml !== '' ? $prescriptionHtml : null,
        'clinical_snapshot_json' => $clinicalSnapshotJson !== '' ? $clinicalSnapshotJson : null,
    ]);
    $visitRecordId = (int)$pdo->lastInsertId();

    if ($appointmentId > 0) {
        $stmt = $pdo->prepare(
            "UPDATE zimrx_appointments
             SET visit_record_id = :visit_record_id,
                 visit_no = :visit_no,
                 visit_id = :visit_id,
                 status = 'Done',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND doctor_id = :doctor_id"
        );
        $stmt->execute([
            'visit_record_id' => $visitRecordId,
            'visit_no' => $visitNo,
            'visit_id' => $publicVisitId,
            'id' => $appointmentId,
            'doctor_id' => $doctorId,
        ]);
    }

    save_patient_referral($pdo, $doctorId, $regNo, $visitRecordId, $publicVisitId, $payload);

    $snapshotArr = is_array($clinicalSnapshot) ? $clinicalSnapshot : json_decode((string)$clinicalSnapshotJson, true);
    $savedOccupation = trim((string)($snapshotArr['patient_particulars']['occupation'] ?? $payload['occupation'] ?? ''));
    if ($savedOccupation !== '') {
        require_once __DIR__ . '/../particulars_audit_lib.php';
        zimrx_record_user_occupation($pdo, $doctorId, $savedOccupation);
    }

    $pdo->commit();
    respond([
        'ok' => true,
        'visit_record_id' => $visitRecordId,
        'visit_id' => $publicVisitId,
        'visit_no' => $visitNo,
        'visit_code' => $publicVisitId,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => $e->getMessage()]);
}
