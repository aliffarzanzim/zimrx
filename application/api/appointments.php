<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../visit_identity.php';
require_once __DIR__ . '/../emr_identity_lib.php';
require_once __DIR__ . '/../particulars_audit_lib.php';

header('Content-Type: application/json');

function respond($payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function value(array $data, string $key): string {
    return trim((string)($data[$key] ?? ''));
}

function normalized_date(string $date): string {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    return date('Y-m-d');
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    return DbSchema::columnExists($pdo, $table, $column);
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function drop_column_if_exists(PDO $pdo, string $table, string $column): void {
    if (!column_exists($pdo, $table, $column)) {
        return;
    }
    $pdo->exec("ALTER TABLE $table DROP COLUMN $column");
}

function doctor_code_for_visit(PDO $pdo, int $doctorId): string {
    $stmt = $pdo->prepare("SELECT doctor_code FROM zimrx_doctors WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $doctorId]);
    $code = strtoupper(trim((string)$stmt->fetchColumn()));
    return $code !== '' ? $code : 'D' . str_pad((string)$doctorId, 3, '0', STR_PAD_LEFT);
}

function appointment_doctor_options(PDO $pdo): array {
    return zimrx_doctor_options_for_user($pdo, current_user_id(), current_user_role(), current_user_doctor_id());
}

function resolve_appointment_doctor_id(PDO $pdo, int $requestedDoctorId): int {
    $options = appointment_doctor_options($pdo);
    $ids = array_map(fn($row) => (int)$row['id'], $options);
    if (current_user_role() !== 'assistant') {
        return in_array($requestedDoctorId, $ids, true) ? $requestedDoctorId : current_user_doctor_id();
    }

    if (count($ids) === 1) {
        $_SESSION['active_doctor_id'] = $ids[0];
        return $ids[0];
    }
    if ($requestedDoctorId > 0 && in_array($requestedDoctorId, $ids, true)) {
        $_SESSION['active_doctor_id'] = $requestedDoctorId;
        return $requestedDoctorId;
    }
    $sessionDoctorId = (int)($_SESSION['active_doctor_id'] ?? 0);
    return in_array($sessionDoctorId, $ids, true) ? $sessionDoctorId : 0;
}

function normalize_duplicate_appointment_serials(PDO $pdo): void {
    $rows = $pdo->query(
        "SELECT doctor_id, appointment_date, appointment_no, group_concat(id) AS ids, count(*) AS total
         FROM zimrx_appointments
         GROUP BY doctor_id, appointment_date, appointment_no
         HAVING total > 1"
    )->fetchAll();

    foreach ($rows as $row) {
        $ids = array_map('intval', explode(',', (string)$row['ids']));
        sort($ids);
        array_shift($ids); // Keep the oldest row's serial, move later duplicates.
        foreach ($ids as $id) {
            $nextNo = next_appointment_no($pdo, (string)$row['appointment_date'], default_appointment_settings(), (int)($row['doctor_id'] ?? 1));
            $stmt = $pdo->prepare(
                "UPDATE zimrx_appointments
                 SET appointment_no = :appointment_no,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $stmt->execute(['appointment_no' => $nextNo, 'id' => $id]);
        }
    }
}

function ensure_appointment_schema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            reg_no TEXT UNIQUE,
            full_name TEXT NOT NULL,
            age TEXT,
            age_unit TEXT,
            dob TEXT,
            gender TEXT,
            blood_group TEXT,
            address TEXT,
            mobile TEXT,
            occupation TEXT,
            weight TEXT,
            weight_unit TEXT,
            height TEXT,
            height_unit TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            appointment_no INTEGER NOT NULL,
            appointment_date TEXT NOT NULL,
            appointment_time TEXT,
            patient_id INTEGER,
            reg_no TEXT,
            patient_name TEXT NOT NULL,
            age TEXT,
            age_unit TEXT,
            dob TEXT,
            gender TEXT,
            blood_group TEXT,
            mobile TEXT,
            occupation TEXT,
            address TEXT,
            weight TEXT,
            weight_unit TEXT,
            height TEXT,
            height_unit TEXT,
            referral_category TEXT,
            referral_name TEXT,
            visit_record_id INTEGER,
            visit_no INTEGER,
            visit_id TEXT,
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
            vitals_entered_by INTEGER,
            vitals_entered_at TEXT,
            status TEXT NOT NULL DEFAULT 'Pending',
            notes TEXT,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    foreach ([
        'age' => 'TEXT',
        'age_unit' => 'TEXT',
        'doctor_id' => 'INTEGER NOT NULL DEFAULT 1',
        'weight' => 'TEXT',
        'weight_unit' => 'TEXT',
        'height' => 'TEXT',
        'height_unit' => 'TEXT',
    ] as $column => $definition) {
        ensure_column($pdo, 'zimrx_patients', $column, $definition);
    }

    foreach ([
        'patient_id' => 'INTEGER',
        'doctor_id' => 'INTEGER NOT NULL DEFAULT 1',
        'reg_no' => 'TEXT',
        'age_unit' => 'TEXT',
        'dob' => 'TEXT',
        'blood_group' => 'TEXT',
        'occupation' => 'TEXT',
        'weight' => 'TEXT',
        'weight_unit' => 'TEXT',
        'height' => 'TEXT',
        'height_unit' => 'TEXT',
        'referral_category' => 'TEXT',
        'referral_name' => 'TEXT',
        'visit_record_id' => 'INTEGER',
        'visit_no' => 'INTEGER',
        'visit_id' => 'TEXT',
        'visit_fee' => 'REAL',
        'discount' => 'REAL',
        'discount_note' => 'TEXT',
        'paid_amount' => 'REAL',
        'payment_updated_at' => 'TEXT',
        'bp' => 'TEXT',
        'pulse' => 'TEXT',
        'temperature' => 'TEXT',
        'spo2' => 'TEXT',
        'resp_rate' => 'TEXT',
        'vitals_note' => 'TEXT',
        'vitals_entered_by' => 'INTEGER',
        'vitals_entered_at' => 'TEXT',
    ] as $column => $definition) {
        ensure_column($pdo, 'zimrx_appointments', $column, $definition);
    }
    drop_column_if_exists($pdo, 'zimrx_appointments', 'reason');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            patient_id INTEGER NOT NULL,
            appointment_id INTEGER,
            patient_reg_no TEXT,
            patient_name TEXT,
            visit_no INTEGER,
            visit_id TEXT,
            visit_date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            next_visit TEXT,
            referred_by TEXT,
            referral_category TEXT NOT NULL DEFAULT 'self',
            referral_name TEXT,
            age_at_visit TEXT,
            height_at_visit TEXT,
            height_unit_at_visit TEXT,
            weight_at_visit TEXT,
            weight_unit_at_visit TEXT,
            metrics_json TEXT,
            billing_json TEXT,
            rich_text_json TEXT,
            print_settings TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    foreach ([
        'patient_reg_no' => 'TEXT',
        'doctor_id' => 'INTEGER NOT NULL DEFAULT 1',
        'appointment_id' => 'INTEGER',
        'patient_name' => 'TEXT',
        'visit_no' => 'INTEGER',
        'visit_id' => 'TEXT',
        'height_at_visit' => 'TEXT',
        'height_unit_at_visit' => 'TEXT',
        'weight_at_visit' => 'TEXT',
        'weight_unit_at_visit' => 'TEXT',
        'referral_category' => "TEXT NOT NULL DEFAULT 'self'",
        'referral_name' => 'TEXT',
    ] as $column => $definition) {
        ensure_column($pdo, 'zimrx_visits', $column, $definition);
    }

    zimrx_ensure_visit_identity_schema($pdo);

    $patientIndexTable = 'zimrx_patients';
    $appointmentIndexTable = 'zimrx_appointments';
    $visitIndexTable = 'zimrx_visits';

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_reg_no ON $patientIndexTable(reg_no)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_mobile ON $patientIndexTable(mobile)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_date_no ON $appointmentIndexTable(appointment_date, appointment_no)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_doctor_date ON $appointmentIndexTable(doctor_id, appointment_date)");
    normalize_duplicate_appointment_serials($pdo);
    $pdo->exec("DROP INDEX IF EXISTS uid_appointments_date_no");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_appointments_doctor_date_no ON $appointmentIndexTable(doctor_id, appointment_date, appointment_no)");
    $pdo->exec("DROP INDEX IF EXISTS idx_visits_patient_visit_no");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_doctor_patient_visit_no ON $visitIndexTable(doctor_id, patient_id, visit_no) WHERE visit_no IS NOT NULL");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_visit_id ON $visitIndexTable(visit_id) WHERE visit_id IS NOT NULL");

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_appointment_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            settings_json TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id)
        )"
    );
    ensure_column($pdo, 'zimrx_appointment_settings', 'doctor_id', 'INTEGER NOT NULL DEFAULT 1');

    // Note: general_discount_causes is now stored in zimrx_static.db
    // See ZIMRX_DB_STATIC constant in config.php
}

function default_appointment_settings(): array {
    return [
        'default_start_time' => '14:00',
        'minutes_per_patient' => 5,
        'blank_slots' => 3,
        'visit_fee' => 500,
        'revisit_fee' => 400,
        'revisit_validity_days' => 60,
        'weekday_overrides' => new stdClass(),
        'token_fields' => [
            'name', 'age', 'sex', 'reg', 'visit_no', 'visit_id',
            'visit_fee', 'discount', 'paid',
        ],
    ];
}

function get_appointment_settings(PDO $pdo, int $doctorId): array {
    $defaults = default_appointment_settings();
    $stmt = $pdo->prepare(
        "SELECT settings_json
             FROM zimrx_appointment_settings
         WHERE doctor_id = :doctor_id
         LIMIT 1"
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    $row = $stmt->fetch();
    if (!$row) {
        return $defaults;
    }

    $stored = json_decode((string)$row['settings_json'], true);
    if (!is_array($stored)) {
        return $defaults;
    }

    $settings = array_merge($defaults, $stored);
    $settings['minutes_per_patient'] = max(1, (int)($settings['minutes_per_patient'] ?? 5));
    $settings['blank_slots'] = max(0, (int)($settings['blank_slots'] ?? 3));
    $settings['visit_fee'] = max(0, (float)($settings['visit_fee'] ?? 500));
    $settings['revisit_fee'] = max(0, (float)($settings['revisit_fee'] ?? 400));
    $settings['revisit_validity_days'] = max(0, (int)($settings['revisit_validity_days'] ?? 60));
    if (!is_array($settings['weekday_overrides'])) {
        $settings['weekday_overrides'] = [];
    }
    if (!is_array($settings['token_fields'])) {
        $settings['token_fields'] = $defaults['token_fields'];
    }
    return $settings;
}

function save_appointment_settings(PDO $pdo, array $payload, int $doctorId): array {
    $settings = [
        'default_start_time' => value($payload, 'default_start_time') ?: '14:00',
        'minutes_per_patient' => max(1, (int)value($payload, 'minutes_per_patient')),
        'blank_slots' => max(0, (int)value($payload, 'blank_slots')),
        'visit_fee' => max(0, (float)value($payload, 'visit_fee')),
        'revisit_fee' => max(0, (float)value($payload, 'revisit_fee')),
        'revisit_validity_days' => max(0, (int)value($payload, 'revisit_validity_days')),
        'weekday_overrides' => $payload['weekday_overrides'] ?? [],
        'token_fields' => $payload['token_fields'] ?? default_appointment_settings()['token_fields'],
    ];

    if (!preg_match('/^\d{2}:\d{2}$/', $settings['default_start_time'])) {
        $settings['default_start_time'] = '14:00';
    }
    if (!is_array($settings['weekday_overrides'])) {
        $settings['weekday_overrides'] = [];
    }
    if (!is_array($settings['token_fields'])) {
        $settings['token_fields'] = default_appointment_settings()['token_fields'];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_appointment_settings (doctor_id, settings_json, updated_at)
         VALUES (:doctor_id, :settings_json, " . DbSql::now() . ")
         " . DbSql::upsert('doctor_id', ['settings_json', 'updated_at'], ['updated_at' => DbSql::now()])
    );
    $stmt->execute([
        'doctor_id' => $doctorId,
        'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
    ]);
    return get_appointment_settings($pdo, $doctorId);
}

function appointment_day_rule(array $settings, string $date): array {
    $dt = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
    $weekday = (string)(int)$dt->format('w');
    $overrides = $settings['weekday_overrides'] ?? [];
    $rule = is_array($overrides) && isset($overrides[$weekday]) && is_array($overrides[$weekday]) ? $overrides[$weekday] : [];
    $closed = !empty($rule['closed']);
    $startTime = trim((string)($rule['start_time'] ?? '')) ?: (string)$settings['default_start_time'];
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        $startTime = (string)$settings['default_start_time'];
    }
    return ['closed' => $closed, 'start_time' => $startTime];
}

function calculate_appointment_time(int $appointmentNo, string $date, array $settings): string {
    $rule = appointment_day_rule($settings, $date);
    if ($rule['closed']) {
        return '';
    }

    $blankSlots = max(0, (int)$settings['blank_slots']);
    $minutesPerPatient = max(1, (int)$settings['minutes_per_patient']);
    $position = max(0, $appointmentNo - $blankSlots - 1);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $rule['start_time']);
    if (!$dt) {
        return '';
    }
    if ($position > 0) {
        $dt->modify('+' . ($position * $minutesPerPatient) . ' minutes');
    }
    return $dt->format('H:i');
}

function next_appointment_no(PDO $pdo, string $date, array $settings, int $doctorId): int {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(appointment_no), 0) + 1
         FROM zimrx_appointments
         WHERE doctor_id = :doctor_id AND appointment_date = :date"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'date' => $date]);
    $nextNo = (int)$stmt->fetchColumn();
    return max($nextNo, (int)$settings['blank_slots'] + 1, 1);
}

function appointment_serial_is_free(PDO $pdo, string $date, int $appointmentNo, int $doctorId, int $excludeId = 0): bool {
    if ($appointmentNo <= 0) {
        return false;
    }

    $sql = "SELECT COUNT(*) FROM zimrx_appointments WHERE doctor_id = :doctor_id AND appointment_date = :date AND appointment_no = :appointment_no";
    $params = ['doctor_id' => $doctorId, 'date' => $date, 'appointment_no' => $appointmentNo];
    if ($excludeId > 0) {
        $sql .= " AND id <> :exclude_id";
        $params['exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() === 0;
}

function confirmed_appointment_no(PDO $pdo, string $date, int $requestedNo, array $settings, int $doctorId, int $excludeId = 0): int {
    if ($requestedNo > 0 && appointment_serial_is_free($pdo, $date, $requestedNo, $doctorId, $excludeId)) {
        return $requestedNo;
    }
    return next_appointment_no($pdo, $date, $settings, $doctorId);
}

function next_reg_no(PDO $pdo, string $date, int $doctorId): string {
    return zimrx_generate_reg_no($pdo, $date);
}

function is_unique_constraint_error(Throwable $e): bool {
    $message = strtolower($e->getMessage());
    return str_contains($message, 'unique constraint failed')
        || str_contains($message, 'constraint violation');
}

function format_visit_id(string $regNo, int $visitNo, string $doctorCode = ''): string {
    global $pdo;
    return isset($pdo) ? zimrx_generate_visit_id($pdo) : ('V' . date('ymd') . str_pad((string)$visitNo, 3, '0', STR_PAD_LEFT));
}

function appointment_referral_category(string $value): string {
    $key = strtolower(trim($value));
    $key = str_replace(['-', ' '], '_', $key);
    return match ($key) {
        'doctor' => 'doctor',
        'other_patient' => 'other_patient',
        'others' => 'others',
        default => 'self',
    };
}

function appointment_referral_payload(array $payload): array {
    $referral = $payload['referral'] ?? [];
    if (!is_array($referral)) {
        $referral = [];
    }

    $category = appointment_referral_category(
        (string)($referral['category'] ?? $payload['referral_category'] ?? '')
    );
    $name = trim(preg_replace('/\s+/', ' ', (string)($referral['name'] ?? $payload['referral_name'] ?? '')));

    if (!in_array($category, ['doctor', 'others'], true)) {
        $name = '';
    } elseif ($category === 'doctor') {
        if ($name === '' || preg_match('/^dr\.?$/i', $name)) {
            $name = '';
        } elseif (!preg_match('/^dr\.\s*/i', $name)) {
            $name = 'Dr. ' . $name;
        }
    }

    return ['category' => $category, 'name' => $name];
}

function next_visit_info(PDO $pdo, int $patientId, string $regNo, int $doctorId): array {
    return zimrx_next_visit_info($pdo, $patientId, $regNo, $doctorId);
}

function add_visit_info(PDO $pdo, array $patient, int $doctorId): array {
    $visit = next_visit_info($pdo, (int)($patient['id'] ?? 0), (string)($patient['reg_no'] ?? ''), $doctorId);
    $patient['next_visit_no'] = $visit['visit_no'];
    $patient['next_visit_id'] = $visit['visit_id'];
    $patient['next_visit_code'] = $visit['visit_id'];
    return $patient;
}

function patient_payload(array $row): array {
    return [
        'id' => (int)($row['id'] ?? 0),
        'reg_no' => (string)($row['reg_no'] ?? ''),
        'full_name' => (string)($row['full_name'] ?? ''),
        'patient_name' => (string)($row['full_name'] ?? ''),
        'age' => (string)($row['age'] ?? ''),
        'age_unit' => (string)($row['age_unit'] ?? 'Years'),
        'dob' => (string)($row['dob'] ?? ''),
        'gender' => (string)($row['gender'] ?? ''),
        'blood_group' => (string)($row['blood_group'] ?? ''),
        'mobile' => (string)($row['mobile'] ?? ''),
        'occupation' => (string)($row['occupation'] ?? ''),
        'address' => (string)($row['address'] ?? ''),
        'weight' => (string)($row['weight'] ?? ''),
        'weight_unit' => (string)($row['weight_unit'] ?? 'kg'),
        'height' => (string)($row['height'] ?? ''),
        'height_unit' => (string)($row['height_unit'] ?? 'inch'),
    ];
}

function patient_doctor_filter(string $alias = 'p'): string {
    return "(COALESCE(NULLIF($alias.doctor_id, 0), 1) = :doctor_id
        OR EXISTS (
            SELECT 1
            FROM zimrx_patient_doctor_access pda
            WHERE pda.patient_id = $alias.id
              AND pda.doctor_id = :doctor_id
              AND pda.can_view = 1
        ))";
}

function lookup_patients(PDO $pdo, array $query, int $doctorId): array {
    $date = normalized_date(value($query, 'date'));
    $regNo = strtoupper(value($query, 'reg_no'));
    $mobile = value($query, 'mobile');

    if ($regNo !== '') {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM zimrx_patients p
             WHERE " . patient_doctor_filter('p') . "
               AND upper(reg_no) LIKE :reg_no
             ORDER BY reg_no ASC
             LIMIT 10"
        );
        $stmt->execute(['doctor_id' => $doctorId, 'reg_no' => $regNo . '%']);
        return [
            'patients' => array_map(fn($patient) => add_visit_info($pdo, patient_payload($patient), $doctorId), $stmt->fetchAll()),
            'new_reg_no' => next_reg_no($pdo, $date, $doctorId),
        ];
    }

    $cleanMobile = preg_replace('/\D+/', '', $mobile);
    if (strlen($cleanMobile) < 1) {
        return ['patients' => [], 'new_reg_no' => next_reg_no($pdo, $date, $doctorId)];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM zimrx_patients p
         WHERE " . patient_doctor_filter('p') . "
           AND (
                replace(replace(replace(replace(coalesce(mobile, ''), '+88', ''), '-', ''), ' ', ''), '.', '') LIKE :mobile
                OR mobile LIKE :raw_mobile
           )
         ORDER BY updated_at DESC, id DESC
         LIMIT 10"
    );
    $stmt->execute([
        'doctor_id' => $doctorId,
        'mobile' => '%' . $cleanMobile . '%',
        'raw_mobile' => '%' . $mobile . '%',
    ]);

    return [
        'patients' => array_map(fn($patient) => add_visit_info($pdo, patient_payload($patient), $doctorId), $stmt->fetchAll()),
        'new_reg_no' => next_reg_no($pdo, $date, $doctorId),
    ];
}

function format_days_ago(?string $lastVisitDate, string $appointmentDate): string {
    if (!$lastVisitDate) {
        return '';
    }
    $last = DateTime::createFromFormat('Y-m-d H:i:s', $lastVisitDate)
        ?: DateTime::createFromFormat('Y-m-d', substr($lastVisitDate, 0, 10));
    $current = DateTime::createFromFormat('Y-m-d', $appointmentDate);
    if (!$last || !$current) {
        return '';
    }
    $days = (int)$last->diff($current)->format('%r%a');
    $formatted = $last->format('d/m/Y');
    if ($days < 0) {
        return $formatted;
    }
    return $formatted . ' (' . $days . ' Days ago)';
}

function revisit_discount_amount(array $row, array $settings, string $appointmentDate): float {
    if (isset($row['visit_fee']) && $row['visit_fee'] !== null && $row['visit_fee'] !== '') {
        return max(0, (float)($row['discount'] ?? 0));
    }

    $lastVisitDate = $row['last_visit_date'] ?? '';
    if ($lastVisitDate !== '') {
        $last = DateTime::createFromFormat('Y-m-d H:i:s', (string)$lastVisitDate)
            ?: DateTime::createFromFormat('Y-m-d', substr((string)$lastVisitDate, 0, 10));
        $current = DateTime::createFromFormat('Y-m-d', $appointmentDate);
        if ($last && $current) {
            $days = (int)$last->diff($current)->format('%r%a');
            if ($days >= 0 && $days <= (int)$settings['revisit_validity_days']) {
                return max(0, (float)$settings['visit_fee'] - (float)$settings['revisit_fee']);
            }
        }
    }

    return max(0, (float)($row['discount'] ?? 0));
}

function decorate_appointment_rows(array $rows, array $settings, string $date): array {
    foreach ($rows as &$row) {
        $fee = $row['visit_fee'] !== null && $row['visit_fee'] !== '' ? (float)$row['visit_fee'] : (float)$settings['visit_fee'];
        $discount = revisit_discount_amount($row, $settings, $date);
        $discountNote = (string)($row['discount_note'] ?? '');
        if ($discount > 0 && $discountNote === '') {
            $discountNote = 'Revisit Discount';
        }
        $paidAmount = max(0, (float)($row['paid_amount'] ?? 0));
        $payable = max(0, $fee - $discount);
        $row['calculated_visit_fee'] = $fee;
        $row['visit_fee'] = $fee;
        $row['discount'] = $discount;
        $row['discount_note'] = $discountNote;
        $row['paid_amount'] = $paidAmount;
        $row['payable_amount'] = $payable;
        $row['paid_status'] = $payable > 0 && $paidAmount >= $payable ? 'Paid' : 'Not Paid';
        $row['last_visit_label'] = format_days_ago($row['last_visit_date'] ?? null, $date);
    }
    unset($row);
    return $rows;
}

function list_appointments(PDO $pdo, string $date, array $settings, int $doctorId): array {
    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            a.doctor_id,
            a.patient_id,
            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            coalesce(nullif(a.reg_no, ''), p.reg_no, '') AS reg_no,
            coalesce(nullif(a.patient_name, ''), p.full_name, '') AS patient_name,
            coalesce(nullif(a.age, ''), p.age, '') AS age,
            coalesce(nullif(a.age_unit, ''), p.age_unit, 'Years') AS age_unit,
            coalesce(nullif(a.dob, ''), p.dob, '') AS dob,
            coalesce(nullif(a.gender, ''), p.gender, '') AS gender,
            coalesce(nullif(a.blood_group, ''), p.blood_group, '') AS blood_group,
            coalesce(nullif(a.mobile, ''), p.mobile, '') AS mobile,
            coalesce(nullif(a.occupation, ''), p.occupation, '') AS occupation,
            coalesce(nullif(a.address, ''), p.address, '') AS address,
            coalesce(nullif(a.weight, ''), p.weight, '') AS weight,
            coalesce(nullif(a.weight_unit, ''), p.weight_unit, 'kg') AS weight_unit,
            coalesce(nullif(a.height, ''), p.height, '') AS height,
            coalesce(nullif(a.height_unit, ''), p.height_unit, 'inch') AS height_unit,
            coalesce(nullif(a.referral_category, ''), 'self') AS referral_category,
            coalesce(a.referral_name, '') AS referral_name,
            a.visit_record_id,
            a.visit_no,
            a.visit_id,
            a.visit_id AS visit_code,
            a.visit_fee,
            a.discount,
            a.discount_note,
            a.paid_amount,
            a.payment_updated_at,
            a.bp,
            a.pulse,
            a.temperature,
            a.spo2,
            a.resp_rate,
            a.vitals_note,
            a.vitals_entered_by,
            a.vitals_entered_at,
            a.status,
            a.notes,
            a.created_at,
            a.updated_at,
            (
                SELECT max(v.visit_date)
                FROM zimrx_visits v
                WHERE v.patient_id = a.patient_id
                  AND v.doctor_id = a.doctor_id
                  AND date(v.visit_date) < date(a.appointment_date)
            ) AS last_visit_date
         FROM zimrx_appointments a
         LEFT JOIN zimrx_patients p ON p.id = a.patient_id
         WHERE a.doctor_id = :doctor_id
           AND a.appointment_date = :date
         ORDER BY a.appointment_no ASC, a.id ASC"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'date' => $date]);
    return decorate_appointment_rows($stmt->fetchAll(), $settings, $date);
}

function appointment_detail(PDO $pdo, int $appointmentId, array $settings, int $doctorId): ?array {
    if ($appointmentId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            a.doctor_id,
            a.patient_id,
            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            coalesce(nullif(a.reg_no, ''), p.reg_no, '') AS reg_no,
            coalesce(nullif(a.patient_name, ''), p.full_name, '') AS patient_name,
            coalesce(nullif(a.age, ''), p.age, '') AS age,
            coalesce(nullif(a.age_unit, ''), p.age_unit, 'Years') AS age_unit,
            coalesce(nullif(a.dob, ''), p.dob, '') AS dob,
            coalesce(nullif(a.gender, ''), p.gender, '') AS gender,
            coalesce(nullif(a.blood_group, ''), p.blood_group, '') AS blood_group,
            coalesce(nullif(a.mobile, ''), p.mobile, '') AS mobile,
            coalesce(nullif(a.occupation, ''), p.occupation, '') AS occupation,
            coalesce(nullif(a.address, ''), p.address, '') AS address,
            coalesce(nullif(a.weight, ''), p.weight, '') AS weight,
            coalesce(nullif(a.weight_unit, ''), p.weight_unit, 'kg') AS weight_unit,
            coalesce(nullif(a.height, ''), p.height, '') AS height,
            coalesce(nullif(a.height_unit, ''), p.height_unit, 'inch') AS height_unit,
            coalesce(nullif(a.referral_category, ''), 'self') AS referral_category,
            coalesce(a.referral_name, '') AS referral_name,
            a.visit_record_id,
            a.visit_no,
            a.visit_id,
            a.visit_id AS visit_code,
            a.visit_fee,
            a.discount,
            a.discount_note,
            a.paid_amount,
            a.payment_updated_at,
            a.bp,
            a.pulse,
            a.temperature,
            a.spo2,
            a.resp_rate,
            a.vitals_note,
            a.vitals_entered_by,
            a.vitals_entered_at,
            a.status,
            a.notes,
            a.created_at,
            a.updated_at,
            (
                SELECT max(v.visit_date)
                FROM zimrx_visits v
                WHERE v.patient_id = a.patient_id
                  AND v.doctor_id = a.doctor_id
                  AND date(v.visit_date) < date(a.appointment_date)
            ) AS last_visit_date
         FROM zimrx_appointments a
         LEFT JOIN zimrx_patients p ON p.id = a.patient_id
         WHERE a.id = :id
           AND a.doctor_id = :doctor_id
         LIMIT 1"
    );
    $stmt->execute(['id' => $appointmentId, 'doctor_id' => $doctorId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $rows = decorate_appointment_rows([$row], $settings, (string)$row['appointment_date']);
    return $rows[0] ?? $row;
}

function list_appointment_history(PDO $pdo, array $query, array $settings, int $doctorId): array {
    $patientId = (int)value($query, 'patient_id');
    $regNo = value($query, 'reg_no');
    $excludeId = (int)value($query, 'id');

    if ($patientId <= 0 && $regNo === '') {
        return [];
    }

    $where = $patientId > 0
        ? 'a.patient_id = :patient_id'
        : "upper(coalesce(nullif(a.reg_no, ''), p.reg_no, '')) = upper(:reg_no)";
    $params = $patientId > 0 ? ['patient_id' => $patientId] : ['reg_no' => $regNo];
    if ($excludeId > 0) {
        $where .= ' AND a.id <> :exclude_id';
        $params['exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            d.display_name AS doctor_name,
            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            coalesce(nullif(a.reg_no, ''), p.reg_no, '') AS reg_no,
            coalesce(nullif(a.patient_name, ''), p.full_name, '') AS patient_name,
            coalesce(nullif(a.referral_category, ''), 'self') AS referral_category,
            coalesce(a.referral_name, '') AS referral_name,
            a.visit_no,
            a.visit_id,
            a.visit_id AS visit_code,
            a.visit_fee,
            a.discount,
            a.discount_note,
            a.paid_amount,
            a.status
         FROM zimrx_appointments a
         LEFT JOIN zimrx_patients p ON p.id = a.patient_id
         LEFT JOIN zimrx_doctors d ON d.id = a.doctor_id
         WHERE $where
         ORDER BY date(a.appointment_date) DESC, a.appointment_no DESC, a.id DESC
         LIMIT 30"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $fee = $row['visit_fee'] !== null && $row['visit_fee'] !== '' ? (float)$row['visit_fee'] : (float)$settings['visit_fee'];
        $discount = max(0, (float)($row['discount'] ?? 0));
        $paidAmount = max(0, (float)($row['paid_amount'] ?? 0));
        $payable = max(0, $fee - $discount);
        $row['visit_fee'] = $fee;
        $row['discount'] = $discount;
        $row['paid_amount'] = $paidAmount;
        $row['payable_amount'] = $payable;
        $row['paid_status'] = $payable > 0 && $paidAmount >= $payable ? 'Paid' : 'Not Paid';
    }
    unset($row);

    return $rows;
}

function find_patient(PDO $pdo, int $patientId, string $regNo, int $doctorId): ?array {
    if ($patientId > 0) {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM zimrx_patients p
             WHERE p.id = :id AND " . patient_doctor_filter('p') . "
             LIMIT 1"
        );
        $stmt->execute(['id' => $patientId, 'doctor_id' => $doctorId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        $stmt = $pdo->prepare("SELECT * FROM zimrx_patients WHERE upper(reg_no) = upper(:reg_no) LIMIT 1");
        $stmt->execute(['reg_no' => $regNo]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    if ($regNo !== '') {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM zimrx_patients p
             WHERE " . patient_doctor_filter('p') . "
               AND upper(p.reg_no) = upper(:reg_no)
             LIMIT 1"
        );
        $stmt->execute(['doctor_id' => $doctorId, 'reg_no' => $regNo]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    return null;
}

function load_patient(PDO $pdo, int $patientId, int $doctorId): array {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM zimrx_patients p
         WHERE p.id = :id AND " . patient_doctor_filter('p') . "
         LIMIT 1"
    );
    $stmt->execute(['id' => $patientId, 'doctor_id' => $doctorId]);
    $row = $stmt->fetch();
    return $row ? patient_payload($row) : [];
}

function save_patient(PDO $pdo, array $payload, string $date, int $doctorId): array {
    $patientId = (int)value($payload, 'patient_id');
    $regNo = strtoupper(value($payload, 'reg_no'));
    if (in_array($regNo, ['AUTO', 'AUTO ON SAVE'], true)) {
        $regNo = '';
    }
    $name = value($payload, 'patient_name');
    $mobile = value($payload, 'mobile');

    if ($name === '') {
        respond(['error' => 'Patient name is required.']);
    }

    $existing = find_patient($pdo, $patientId, $regNo, $doctorId);

    // Safeguard: If patient_id was not explicitly selected, prevent overwriting existing patient data
    if ($patientId <= 0 && $existing && $regNo !== '') {
        $existingName = trim((string)($existing['full_name'] ?? ''));
        if ($existingName !== '' && strcasecmp($existingName, $name) !== 0) {
            respond([
                'error' => "Reg No '{$existing['reg_no']}' already belongs to '{$existingName}'. Please select the patient from the lookup list, or clear Reg No to register a new patient."
            ]);
        }
    }

    $params = [
        'doctor_id' => $doctorId,
        'reg_no' => $regNo,
        'full_name' => $name,
        'age' => value($payload, 'age'),
        'age_unit' => value($payload, 'age_unit') ?: 'Years',
        'dob' => value($payload, 'dob'),
        'gender' => value($payload, 'gender'),
        'blood_group' => value($payload, 'blood_group'),
        'address' => value($payload, 'address'),
        'mobile' => $mobile,
        'occupation' => value($payload, 'occupation'),
        'weight' => value($payload, 'weight'),
        'weight_unit' => value($payload, 'weight_unit') ?: 'kg',
        'height' => value($payload, 'height'),
        'height_unit' => value($payload, 'height_unit') ?: 'inch',
    ];

    if ($existing) {
        $params['id'] = (int)$existing['id'];
        $params['reg_no'] = !empty($existing['reg_no']) ? $existing['reg_no'] : ($regNo !== '' ? $regNo : '');
        $stmt = $pdo->prepare(
            "UPDATE zimrx_patients
             SET reg_no = :reg_no,
                 doctor_id = COALESCE(NULLIF(doctor_id, 0), :doctor_id),
                 full_name = :full_name,
                 age = coalesce(nullif(:age, ''), age),
                 age_unit = coalesce(nullif(:age_unit, ''), age_unit),
                 dob = coalesce(nullif(:dob, ''), dob),
                 gender = coalesce(nullif(:gender, ''), gender),
                 blood_group = coalesce(nullif(:blood_group, ''), blood_group),
                 address = coalesce(nullif(:address, ''), address),
                 mobile = coalesce(nullif(:mobile, ''), mobile),
                 occupation = coalesce(nullif(:occupation, ''), occupation),
                 weight = coalesce(nullif(:weight, ''), weight),
                 weight_unit = coalesce(nullif(:weight_unit, ''), weight_unit),
                 height = coalesce(nullif(:height, ''), height),
                 height_unit = coalesce(nullif(:height_unit, ''), height_unit),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute($params);
        $pdo->prepare(
            DbSql::insertIgnore('zimrx_patient_doctor_access', 'patient_id, doctor_id', ':patient_id, :doctor_id')
        )->execute(['patient_id' => (int)$existing['id'], 'doctor_id' => $doctorId]);

        $auditSource = value($payload, 'source') ?: 'appointment';
        log_patient_particulars_audit($pdo, (int)$existing['id'], (string)$params['reg_no'], $existing, $params, $auditSource);
        if (!empty($params['occupation'])) {
            zimrx_record_user_occupation($pdo, $doctorId, (string)$params['occupation']);
        }

        return load_patient($pdo, (int)$existing['id'], $doctorId);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_patients (
            doctor_id, reg_no, full_name, age, age_unit, dob, gender, blood_group,
            address, mobile, occupation, weight, weight_unit, height, height_unit
        ) VALUES (
            :doctor_id, :reg_no, :full_name, nullif(:age, ''), nullif(:age_unit, ''), nullif(:dob, ''),
            nullif(:gender, ''), nullif(:blood_group, ''), nullif(:address, ''), nullif(:mobile, ''),
            nullif(:occupation, ''), nullif(:weight, ''), nullif(:weight_unit, ''),
            nullif(:height, ''), nullif(:height_unit, '')
        )"
    );

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $params['reg_no'] = $regNo !== '' ? $regNo : next_reg_no($pdo, $date, $doctorId);
        try {
            $stmt->execute($params);
            $newPatientId = (int)$pdo->lastInsertId();
            $pdo->prepare(
                DbSql::insertIgnore('zimrx_patient_doctor_access', 'patient_id, doctor_id', ':patient_id, :doctor_id')
            )->execute(['patient_id' => $newPatientId, 'doctor_id' => $doctorId]);

            $auditSource = value($payload, 'source') ?: 'appointment';
            log_patient_particulars_audit($pdo, $newPatientId, (string)$params['reg_no'], [], $params, $auditSource);
            if (!empty($params['occupation'])) {
                zimrx_record_user_occupation($pdo, $doctorId, (string)$params['occupation']);
            }

            return load_patient($pdo, $newPatientId, $doctorId);
        } catch (PDOException $e) {
            if ($regNo !== '' || !is_unique_constraint_error($e)) {
                throw $e;
            }
        }
    }

    throw new RuntimeException('Could not allocate a unique patient Reg No. Please try again.');
}

try {
    ensure_appointment_schema($pdo);
    $payload = [];
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
    }

    $requestedDoctorId = $_SERVER['REQUEST_METHOD'] === 'GET'
        ? (int)value($_GET, 'doctor_id')
        : (int)value($payload, 'doctor_id');
    $doctorOptions = appointment_doctor_options($pdo);
    $doctorId = resolve_appointment_doctor_id($pdo, $requestedDoctorId);

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && value($_GET, 'action') === 'doctor_options') {
        respond(['doctors' => $doctorOptions, 'active_doctor_id' => $doctorId]);
    }

    if ($doctorId <= 0) {
        respond([
            'needs_doctor' => true,
            'doctor_options' => $doctorOptions,
            'appointments' => [],
            'settings' => default_appointment_settings(),
        ]);
    }

    $doctorCode = doctor_code_for_visit($pdo, $doctorId);
    $settings = get_appointment_settings($pdo, $doctorId);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = value($_GET, 'action');
        $date = normalized_date(value($_GET, 'date'));

        if ($action === 'settings') {
            respond(['settings' => $settings]);
        }

        if ($action === 'discount_causes') {
            // Query from static database using unified DbConnections
            $stmt = DbConnections::staticDb()->query("SELECT cause FROM zimrx_static_discount_causes ORDER BY sort_order ASC, id ASC");
            respond(['causes' => array_column($stmt->fetchAll(), 'cause')]);
        }

        if ($action === 'patient_lookup') {
            respond(lookup_patients($pdo, $_GET, $doctorId));
        }

        if ($action === 'appointment_history') {
            respond(['history' => list_appointment_history($pdo, $_GET, $settings, $doctorId)]);
        }

        if ($action === 'appointment_detail') {
            $appointment = appointment_detail($pdo, (int)value($_GET, 'id'), $settings, $doctorId);
            if (!$appointment) {
                respond(['error' => 'Appointment not found.']);
            }
            respond([
                'appointment' => $appointment,
                'settings' => $settings,
                'active_doctor_id' => $doctorId,
            ]);
        }

        if ($action === 'next_reg') {
            $newRegNo = next_reg_no($pdo, $date, $doctorId);
            $nextAppointmentNo = next_appointment_no($pdo, $date, $settings, $doctorId);
            respond([
                'new_reg_no' => $newRegNo,
                'appointment_no' => $nextAppointmentNo,
                'appointment_time' => calculate_appointment_time($nextAppointmentNo, $date, $settings),
                'visit_no' => 1,
                'visit_id' => format_visit_id($newRegNo, 1, $doctorCode),
                'visit_code' => format_visit_id($newRegNo, 1, $doctorCode),
                'doctor_options' => $doctorOptions,
                'active_doctor_id' => $doctorId,
                'day_rule' => appointment_day_rule($settings, $date),
            ]);
        }

        $newRegNo = next_reg_no($pdo, $date, $doctorId);
        $nextAppointmentNo = next_appointment_no($pdo, $date, $settings, $doctorId);
        respond([
            'appointments' => list_appointments($pdo, $date, $settings, $doctorId),
            'new_reg_no' => $newRegNo,
            'appointment_no' => $nextAppointmentNo,
            'appointment_time' => calculate_appointment_time($nextAppointmentNo, $date, $settings),
            'visit_no' => 1,
            'visit_id' => format_visit_id($newRegNo, 1, $doctorCode),
            'visit_code' => format_visit_id($newRegNo, 1, $doctorCode),
            'settings' => $settings,
            'doctor_options' => $doctorOptions,
            'active_doctor_id' => $doctorId,
            'day_rule' => appointment_day_rule($settings, $date),
        ]);
    }

    $action = value($payload, 'action') ?: 'create';

    if ($action === 'settings') {
        if (current_user_role() === 'assistant') {
            respond(['error' => 'Only doctors can change appointment settings.']);
        }
        respond(['ok' => true, 'settings' => save_appointment_settings($pdo, $payload, $doctorId)]);
    }

    if ($action === 'delete') {
        $id = (int)value($payload, 'id');
        $stmt = $pdo->prepare("DELETE FROM zimrx_appointments WHERE id = :id AND doctor_id = :doctor_id");
        $stmt->execute(['id' => $id, 'doctor_id' => $doctorId]);
        respond(['ok' => true]);
    }

    if ($action === 'status') {
        $id = (int)value($payload, 'id');
        $status = value($payload, 'status') ?: 'Pending';
        $stmt = $pdo->prepare(
            "UPDATE zimrx_appointments
             SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND doctor_id = :doctor_id"
        );
        $stmt->execute(['id' => $id, 'status' => $status, 'doctor_id' => $doctorId]);
        respond(['ok' => true]);
    }

    if ($action === 'vitals') {
        $id = (int)value($payload, 'id');
        if ($id <= 0) {
            respond(['error' => 'Appointment is required for vitals entry.']);
        }

        $stmt = $pdo->prepare(
            "UPDATE zimrx_appointments
             SET bp = :bp,
                 pulse = :pulse,
                 temperature = :temperature,
                 spo2 = :spo2,
                 resp_rate = :resp_rate,
                 vitals_note = :vitals_note,
                 vitals_entered_by = :vitals_entered_by,
                 vitals_entered_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND doctor_id = :doctor_id"
        );
        $stmt->execute([
            'id' => $id,
            'doctor_id' => $doctorId,
            'bp' => value($payload, 'bp'),
            'pulse' => value($payload, 'pulse'),
            'temperature' => value($payload, 'temperature'),
            'spo2' => value($payload, 'spo2'),
            'resp_rate' => value($payload, 'resp_rate'),
            'vitals_note' => value($payload, 'vitals_note'),
            'vitals_entered_by' => $_SESSION['user_id'] ?? null,
        ]);
        respond(['ok' => true]);
    }

    if ($action === 'payment') {
        $id = (int)value($payload, 'id');
        if ($id <= 0) {
            respond(['error' => 'Appointment is required for payment entry.']);
        }

        $visitFee = max(0, (float)value($payload, 'visit_fee'));
        $discount = max(0, (float)value($payload, 'discount'));
        $paidAmount = max(0, (float)value($payload, 'paid_amount'));
        $stmt = $pdo->prepare(
            "UPDATE zimrx_appointments
             SET visit_fee = :visit_fee,
                 discount = :discount,
                 discount_note = :discount_note,
                 paid_amount = :paid_amount,
                 payment_updated_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND doctor_id = :doctor_id"
        );
        $stmt->execute([
            'id' => $id,
            'doctor_id' => $doctorId,
            'visit_fee' => $visitFee,
            'discount' => $discount,
            'discount_note' => value($payload, 'discount_note'),
            'paid_amount' => $paidAmount,
        ]);
        respond(['ok' => true]);
    }

    $id = (int)value($payload, 'id');
    $date = normalized_date(value($payload, 'appointment_date'));
    $dayRule = appointment_day_rule($settings, $date);
    if (!empty($dayRule['closed'])) {
        respond(['error' => 'This appointment day is marked closed in appointment settings.']);
    }
    $requestedAppointmentNo = (int)value($payload, 'appointment_no');
    $appointmentNo = $requestedAppointmentNo;
    $appointmentTimeInput = value($payload, 'appointment_time');

    $pdo->beginTransaction();
    $patient = save_patient($pdo, $payload, $date, $doctorId);
    $visitNo = (int)value($payload, 'visit_no');
    if ($visitNo <= 0) {
        $visitNo = next_visit_info($pdo, (int)$patient['id'], (string)$patient['reg_no'], $doctorId)['visit_no'];
    }
    $visitIdInput = value($payload, 'visit_id') ?: value($payload, 'visit_code');
    if ($visitIdInput === '' || stripos($visitIdInput, 'auto') !== false) {
        $visitIdInput = '';
    }
    $visitId = $visitIdInput ?: format_visit_id((string)$patient['reg_no'], $visitNo, $doctorCode);
    $referral = appointment_referral_payload($payload);

    $params = [
        'doctor_id' => $doctorId,
        'appointment_no' => confirmed_appointment_no($pdo, $date, $appointmentNo, $settings, $doctorId, $id),
        'appointment_date' => $date,
        'appointment_time' => '',
        'patient_id' => $patient['id'],
        'reg_no' => $patient['reg_no'],
        'patient_name' => $patient['full_name'],
        'age' => $patient['age'],
        'age_unit' => $patient['age_unit'],
        'dob' => $patient['dob'],
        'gender' => $patient['gender'],
        'blood_group' => $patient['blood_group'],
        'mobile' => $patient['mobile'],
        'occupation' => $patient['occupation'],
        'address' => $patient['address'],
        'weight' => $patient['weight'],
        'weight_unit' => $patient['weight_unit'],
        'height' => $patient['height'],
        'height_unit' => $patient['height_unit'],
        'referral_category' => $referral['category'],
        'referral_name' => $referral['name'],
        'visit_no' => $visitNo,
        'visit_id' => $visitId,
        'visit_fee' => value($payload, 'visit_fee') !== '' ? (float)value($payload, 'visit_fee') : null,
        'discount' => value($payload, 'discount') !== '' ? (float)value($payload, 'discount') : 0,
        'discount_note' => value($payload, 'discount_note'),
        'paid_amount' => value($payload, 'paid_amount') !== '' ? (float)value($payload, 'paid_amount') : 0,
        'status' => value($payload, 'status') ?: 'Pending',
        'notes' => value($payload, 'notes'),
        'created_by' => $_SESSION['user_id'] ?? null,
    ];
    $params['appointment_time'] = $appointmentTimeInput ?: calculate_appointment_time((int)$params['appointment_no'], $date, $settings);

    if ($id > 0) {
        $params['id'] = $id;
        $stmt = $pdo->prepare(
            "UPDATE zimrx_appointments
             SET appointment_no = :appointment_no,
                 doctor_id = :doctor_id,
                 appointment_date = :appointment_date,
                 appointment_time = :appointment_time,
                 patient_id = :patient_id,
                 reg_no = :reg_no,
                 patient_name = :patient_name,
                 age = :age,
                 age_unit = :age_unit,
                 dob = :dob,
                 gender = :gender,
                 blood_group = :blood_group,
                 mobile = :mobile,
                 occupation = :occupation,
                 address = :address,
                 weight = :weight,
                 weight_unit = :weight_unit,
                 height = :height,
                 height_unit = :height_unit,
                 referral_category = :referral_category,
                 referral_name = :referral_name,
                 visit_no = :visit_no,
                 visit_id = :visit_id,
                 visit_fee = coalesce(:visit_fee, visit_fee),
                 discount = :discount,
                 discount_note = :discount_note,
                 paid_amount = :paid_amount,
                 status = :status,
                 notes = :notes,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND doctor_id = :doctor_id"
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $params['appointment_no'] = confirmed_appointment_no($pdo, $date, (int)$params['appointment_no'], $settings, $doctorId, $id);
            $params['appointment_time'] = $appointmentTimeInput ?: calculate_appointment_time((int)$params['appointment_no'], $date, $settings);
            try {
                $stmt->execute($params);
                $pdo->commit();
                respond(['ok' => true, 'id' => $id, 'appointment_no' => (int)$params['appointment_no'], 'patient' => $patient]);
            } catch (PDOException $e) {
                if (!is_unique_constraint_error($e)) {
                    throw $e;
                }
                $params['appointment_no'] = next_appointment_no($pdo, $date, $settings, $doctorId);
            }
        }

        throw new RuntimeException('Could not allocate a unique appointment serial. Please try again.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_appointments (
            doctor_id, appointment_no, appointment_date, appointment_time, patient_id, reg_no,
            patient_name, age, age_unit, dob, gender, blood_group, mobile, occupation,
            address, weight, weight_unit, height, height_unit, referral_category, referral_name, visit_no, visit_id,
            visit_fee, discount, discount_note, paid_amount, status, notes, created_by
        ) VALUES (
            :doctor_id, :appointment_no, :appointment_date, :appointment_time, :patient_id, :reg_no,
            :patient_name, :age, :age_unit, :dob, :gender, :blood_group, :mobile, :occupation,
            :address, :weight, :weight_unit, :height, :height_unit, :referral_category, :referral_name, :visit_no, :visit_id,
            :visit_fee, :discount, :discount_note, :paid_amount, :status, :notes, :created_by
        )"
    );

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $params['appointment_no'] = confirmed_appointment_no($pdo, $date, (int)$params['appointment_no'], $settings, $doctorId);
        $params['appointment_time'] = $appointmentTimeInput ?: calculate_appointment_time((int)$params['appointment_no'], $date, $settings);
        try {
            $stmt->execute($params);
            $appointmentId = (int)$pdo->lastInsertId();
            $pdo->commit();
            respond(['ok' => true, 'id' => $appointmentId, 'appointment_no' => (int)$params['appointment_no'], 'patient' => $patient]);
        } catch (PDOException $e) {
            if (!is_unique_constraint_error($e)) {
                throw $e;
            }
            $params['appointment_no'] = next_appointment_no($pdo, $date, $settings, $doctorId);
        }
    }

    throw new RuntimeException('Could not allocate a unique appointment serial. Please try again.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => $e->getMessage()]);
}
