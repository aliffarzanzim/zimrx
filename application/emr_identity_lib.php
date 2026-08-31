<?php
require_once __DIR__ . '/db.php';

function zimrx_digits_from_flow(int $flow): int {
    return max(1, strlen((string)max(1, $flow)));
}

function zimrx_get_emr_settings(PDO $pdo): array {
    $isMulti = function_exists('zimrx_is_multi_doctor') ? zimrx_is_multi_doctor($pdo) : false;
    $defaultDaily = 999;
    $defaultYearly = 99999;

    $defaults = [
        'daily_patient_flow' => $defaultDaily,
        'yearly_patient_flow' => $defaultYearly,
        'reg_id_mode' => 'sequential',
        'visit_id_mode' => 'sequential',
        'auto_expand' => 1,
    ];

    try {
        if (!DbSchema::tableExists($pdo, 'zimrx_emr_settings')) {
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
        }
        $stmt = $pdo->query("SELECT * FROM zimrx_emr_settings ORDER BY id ASC LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($row) {
            $defaults['daily_patient_flow'] = max(10, (int)($row['daily_patient_flow'] ?? $defaultDaily));
            $defaults['yearly_patient_flow'] = max(100, (int)($row['yearly_patient_flow'] ?? $defaultYearly));
            $defaults['reg_id_mode'] = strtolower((string)($row['reg_id_mode'] ?? 'sequential')) === 'random' ? 'random' : 'sequential';
            $defaults['visit_id_mode'] = strtolower((string)($row['visit_id_mode'] ?? 'sequential')) === 'random' ? 'random' : 'sequential';
            $defaults['auto_expand'] = isset($row['auto_expand']) ? (int)$row['auto_expand'] : 1;
        }
    } catch (Throwable $e) {
        // Fall back to defaults
    }

    $defaults['daily_digits'] = zimrx_digits_from_flow($defaults['daily_patient_flow']);
    $defaults['yearly_digits'] = zimrx_digits_from_flow($defaults['yearly_patient_flow']);
    $defaults['is_multi_doctor'] = $isMulti;

    return $defaults;
}

function zimrx_save_emr_settings(PDO $pdo, array $payload): array {
    $daily = max(10, (int)($payload['daily_patient_flow'] ?? 999));
    $yearly = max(100, (int)($payload['yearly_patient_flow'] ?? 99999));
    $regMode = strtolower((string)($payload['reg_id_mode'] ?? 'sequential')) === 'random' ? 'random' : 'sequential';
    $visitMode = strtolower((string)($payload['visit_id_mode'] ?? 'sequential')) === 'random' ? 'random' : 'sequential';
    $autoExpand = !empty($payload['auto_expand']) ? 1 : 0;

    if (!DbSchema::tableExists($pdo, 'zimrx_emr_settings')) {
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
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM zimrx_emr_settings")->fetchColumn();
    if ($count > 0) {
        $stmt = $pdo->prepare(
            "UPDATE zimrx_emr_settings
             SET daily_patient_flow = :daily,
                 yearly_patient_flow = :yearly,
                 reg_id_mode = :reg_mode,
                 visit_id_mode = :visit_mode,
                 auto_expand = :auto_expand,
                 updated_at = " . DbSql::now() . "
             WHERE id = (SELECT id FROM zimrx_emr_settings ORDER BY id ASC LIMIT 1)"
        );
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO zimrx_emr_settings (daily_patient_flow, yearly_patient_flow, reg_id_mode, visit_id_mode, auto_expand, updated_at)
             VALUES (:daily, :yearly, :reg_mode, :visit_mode, :auto_expand, " . DbSql::now() . ")"
        );
    }

    $stmt->execute([
        'daily' => $daily,
        'yearly' => $yearly,
        'reg_mode' => $regMode,
        'visit_mode' => $visitMode,
        'auto_expand' => $autoExpand,
    ]);

    return zimrx_get_emr_settings($pdo);
}

function zimrx_generate_reg_no(PDO $pdo, ?string $date = null): string {
    $dt = null;
    if ($date) {
        $dt = DateTime::createFromFormat('Y-m-d', trim($date)) ?: DateTime::createFromFormat('d/m/Y', trim($date));
    }
    if (!$dt) {
        $dt = new DateTime();
    }

    $year = (int)$dt->format('Y');
    $yearCode = $dt->format('y');
    $prefix = 'P' . $yearCode;

    $settings = zimrx_get_emr_settings($pdo);
    $yearlyDigits = (int)$settings['yearly_digits'];
    $autoExpand = !empty($settings['auto_expand']);
    $isRandom = $settings['reg_id_mode'] === 'random';

    $checkStmt = $pdo->prepare("SELECT 1 FROM zimrx_patients WHERE UPPER(reg_no) = UPPER(:reg_no) LIMIT 1");

    if ($isRandom) {
        $digits = $yearlyDigits;
        $maxVal = (int)str_repeat('9', $digits);
        $attempts = 0;

        do {
            $num = random_int(1, $maxVal);
            $candidate = $prefix . str_pad((string)$num, $digits, '0', STR_PAD_LEFT);
            $checkStmt->execute(['reg_no' => $candidate]);
            $exists = (bool)$checkStmt->fetchColumn();
            $attempts++;

            if ($attempts > 50 && $autoExpand) {
                $digits++;
                $maxVal = (int)str_repeat('9', $digits);
                $settings['yearly_patient_flow'] = $maxVal;
                zimrx_save_emr_settings($pdo, $settings);
                $attempts = 0;
            }
        } while ($exists && $attempts < 200);

        return $candidate;
    }

    // Sequential mode: count total patients registered for this year
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM zimrx_patients
         WHERE strftime('%Y', created_at) = :year
            OR created_at LIKE :year_prefix
            OR substr(reg_no, 2, 2) = :year_code"
    );
    $countStmt->execute([
        'year' => (string)$year,
        'year_prefix' => (string)$year . '-%',
        'year_code' => $yearCode,
    ]);
    $totalCount = (int)$countStmt->fetchColumn();

    $target = $totalCount + 1;
    $digits = max(strlen((string)$target), $yearlyDigits);

    // Auto-expand setting check
    if ($target > (int)$settings['yearly_patient_flow'] && $autoExpand) {
        $settings['yearly_patient_flow'] = (int)str_repeat('9', $digits);
        zimrx_save_emr_settings($pdo, $settings);
    }

    // Smart-skip collision loop
    do {
        $candidate = $prefix . str_pad((string)$target, $digits, '0', STR_PAD_LEFT);
        $checkStmt->execute(['reg_no' => $candidate]);
        $exists = (bool)$checkStmt->fetchColumn();
        if ($exists) {
            $target++;
            $digits = max(strlen((string)$target), $digits);
            if ($target > (int)$settings['yearly_patient_flow'] && $autoExpand) {
                $settings['yearly_patient_flow'] = (int)str_repeat('9', $digits);
                zimrx_save_emr_settings($pdo, $settings);
            }
        }
    } while ($exists);

    return $candidate;
}

function zimrx_generate_visit_id(PDO $pdo, ?string $date = null): string {
    $dt = null;
    if ($date) {
        $dt = DateTime::createFromFormat('Y-m-d', trim($date)) ?: DateTime::createFromFormat('d/m/Y', trim($date));
    }
    if (!$dt) {
        $dt = new DateTime();
    }

    $dateStr = $dt->format('Y-m-d');
    $dateCode = $dt->format('ymd');
    $prefix = 'V' . $dateCode;

    $settings = zimrx_get_emr_settings($pdo);
    $dailyDigits = (int)$settings['daily_digits'];
    $autoExpand = !empty($settings['auto_expand']);
    $isRandom = $settings['visit_id_mode'] === 'random';

    $vStmt = $pdo->prepare("SELECT 1 FROM zimrx_visits WHERE UPPER(visit_id) = UPPER(:v) LIMIT 1");
    $aStmt = $pdo->prepare("SELECT 1 FROM zimrx_appointments WHERE UPPER(visit_id) = UPPER(:v) LIMIT 1");

    $isTaken = function(string $candidate) use ($vStmt, $aStmt): bool {
        $vStmt->execute(['v' => $candidate]);
        if ($vStmt->fetchColumn()) return true;
        $aStmt->execute(['v' => $candidate]);
        return (bool)$aStmt->fetchColumn();
    };

    if ($isRandom) {
        $digits = $dailyDigits;
        $maxVal = (int)str_repeat('9', $digits);
        $attempts = 0;

        do {
            $num = random_int(1, $maxVal);
            $candidate = $prefix . str_pad((string)$num, $digits, '0', STR_PAD_LEFT);
            $exists = $isTaken($candidate);
            $attempts++;

            if ($attempts > 50 && $autoExpand) {
                $digits++;
                $maxVal = (int)str_repeat('9', $digits);
                $settings['daily_patient_flow'] = $maxVal;
                zimrx_save_emr_settings($pdo, $settings);
                $attempts = 0;
            }
        } while ($exists && $attempts < 200);

        return $candidate;
    }

    // Sequential mode: count today's visits across zimrx_visits
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM zimrx_visits
         WHERE visit_date LIKE :date_prefix
            OR substr(visit_date, 1, 10) = :date
            OR substr(visit_id, 2, 6) = :date_code"
    );
    $countStmt->execute([
        'date_prefix' => $dateStr . '%',
        'date' => $dateStr,
        'date_code' => $dateCode,
    ]);
    $totalCount = (int)$countStmt->fetchColumn();

    $target = $totalCount + 1;
    $digits = max(strlen((string)$target), $dailyDigits);

    if ($target > (int)$settings['daily_patient_flow'] && $autoExpand) {
        $settings['daily_patient_flow'] = (int)str_repeat('9', $digits);
        zimrx_save_emr_settings($pdo, $settings);
    }

    // Smart-skip collision loop
    do {
        $candidate = $prefix . str_pad((string)$target, $digits, '0', STR_PAD_LEFT);
        $exists = $isTaken($candidate);
        if ($exists) {
            $target++;
            $digits = max(strlen((string)$target), $digits);
            if ($target > (int)$settings['daily_patient_flow'] && $autoExpand) {
                $settings['daily_patient_flow'] = (int)str_repeat('9', $digits);
                zimrx_save_emr_settings($pdo, $settings);
            }
        }
    } while ($exists);

    return $candidate;
}

function zimrx_next_visit_info(PDO $pdo, int $patientId, string $regNo, int $doctorId, ?string $date = null): array {
    $visitNo = 1;
    if ($patientId > 0) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(MAX(visit_no), 0) + 1
             FROM zimrx_visits
             WHERE doctor_id = :doctor_id AND patient_id = :patient_id"
        );
        $stmt->execute(['doctor_id' => $doctorId, 'patient_id' => $patientId]);
        $visitNo = max(1, (int)$stmt->fetchColumn());
    }

    $visitId = zimrx_generate_visit_id($pdo, $date);

    return [
        'visit_no' => $visitNo,
        'visit_id' => $visitId,
        'visit_code' => $visitId,
    ];
}
