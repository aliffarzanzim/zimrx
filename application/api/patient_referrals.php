<?php
// Referral autocomplete suggestions and recent patient referral lookup across visits, appointments, and user referral logs.
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/visit_identity.php';

header('Content-Type: application/json');

function referral_json(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
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

function resolve_referral_doctor_id(PDO $pdo): int {
    $requestedDoctorId = (int)($_GET['doctor_id'] ?? 0);
    $fallbackDoctorId = current_user_doctor_id();
    if ($requestedDoctorId <= 0 || $requestedDoctorId === $fallbackDoctorId) {
        return $fallbackDoctorId;
    }

    foreach (zimrx_doctor_options_for_user($pdo, current_user_id(), current_user_role(), $fallbackDoctorId) as $doctor) {
        if ((int)($doctor['id'] ?? 0) === $requestedDoctorId) {
            return $requestedDoctorId;
        }
    }

    return $fallbackDoctorId;
}

function appointment_referral_source_available(PDO $pdo): bool {
    return DbSchema::tableExists($pdo, 'zimrx_appointments')
        && DbSchema::columnExists($pdo, 'zimrx_appointments', 'referral_category')
        && DbSchema::columnExists($pdo, 'zimrx_appointments', 'referral_name');
}

function visit_referral_source_available(PDO $pdo): bool {
    return DbSchema::tableExists($pdo, 'zimrx_visits')
        && DbSchema::columnExists($pdo, 'zimrx_visits', 'referral_category')
        && DbSchema::columnExists($pdo, 'zimrx_visits', 'referral_name');
}

try {
    $action = strtolower(trim((string)($_GET['action'] ?? 'suggestions')));
    if (!in_array($action, ['suggestions', 'recent'], true)) {
        referral_json(['error' => 'Unsupported action.']);
    }

    $doctorId = resolve_referral_doctor_id($pdo);
    $hasAppointmentReferralSource = appointment_referral_source_available($pdo);
    $hasVisitReferralSource = visit_referral_source_available($pdo);

    if ($action === 'recent') {
        $regNo = trim((string)($_GET['reg_no'] ?? ''));
        if ($regNo === '') {
            referral_json(['referrals' => []]);
        }

        $sources = [];
        if ($hasVisitReferralSource) {
            $sources[] = "SELECT referral_category AS category, referral_name AS name, updated_at, id
                          FROM zimrx_visits
                          WHERE doctor_id = :doctor_id
                            AND patient_reg_no = :patient_reg_no
                            AND referral_category IN ('doctor', 'others')
                            AND COALESCE(referral_name, '') <> ''";
        }
        $sources[] = "SELECT category, referral_name AS name, updated_at, id
                      FROM zimrx_user_patient_referrals
                      WHERE doctor_id = :doctor_id
                        AND patient_reg_no = :patient_reg_no
                        AND category IN ('doctor', 'others')
                        AND COALESCE(referral_name, '') <> ''";
        if ($hasAppointmentReferralSource) {
            $sources[] = "SELECT referral_category AS category, referral_name AS name, updated_at, id
                          FROM zimrx_appointments
                          WHERE doctor_id = :doctor_id
                            AND reg_no = :patient_reg_no
                            AND referral_category IN ('doctor', 'others')
                            AND COALESCE(referral_name, '') <> ''";
        }

        $stmt = $pdo->prepare(
            "SELECT category, MAX(name) AS name, MAX(updated_at) AS last_used, MAX(id) AS last_id
             FROM (" . implode(" UNION ALL ", $sources) . ") referral_sources
             GROUP BY category, lower(trim(name))
             ORDER BY last_used DESC, last_id DESC
             LIMIT 10"
        );
        $stmt->execute(['doctor_id' => $doctorId, 'patient_reg_no' => $regNo]);

        $referrals = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $referrals[] = [
                'category' => referral_category((string)($row['category'] ?? '')),
                'name' => $name,
            ];
        }

        referral_json(['referrals' => $referrals]);
    }

    $category = referral_category((string)($_GET['category'] ?? ''));
    if (!in_array($category, ['doctor', 'others'], true)) {
        referral_json(['suggestions' => []]);
    }

    $q = trim((string)($_GET['q'] ?? ''));

    $visitSql = "
        SELECT referral_category AS category, referral_name AS name, updated_at, id
        FROM zimrx_visits
        WHERE doctor_id = :doctor_id
          AND referral_category = :category
          AND COALESCE(referral_name, '') <> ''
    ";
    $userReferralSql = "
        SELECT category, referral_name AS name, updated_at, id
        FROM zimrx_user_patient_referrals
        WHERE doctor_id = :doctor_id
          AND category = :category
          AND COALESCE(referral_name, '') <> ''
    ";
    $appointmentSql = "
        SELECT referral_category AS category, referral_name AS name, updated_at, id
        FROM zimrx_appointments
        WHERE doctor_id = :doctor_id
          AND referral_category = :category
          AND COALESCE(referral_name, '') <> ''
    ";
    $params = ['doctor_id' => $doctorId, 'category' => $category];

    if ($q !== '') {
        $visitSql .= " AND " . DbSql::ilike('referral_name', ':q');
        $userReferralSql .= " AND " . DbSql::ilike('referral_name', ':q');
        $appointmentSql .= " AND " . DbSql::ilike('referral_name', ':q');
        $params['q'] = '%' . $q . '%';
    }

    $sources = [];
    if ($hasVisitReferralSource) {
        $sources[] = $visitSql;
    }
    $sources[] = $userReferralSql;
    if ($hasAppointmentReferralSource) {
        $sources[] = $appointmentSql;
    }

    $sql = "
        SELECT MAX(name) AS name, MAX(updated_at) AS last_used, MAX(id) AS last_id
        FROM (" . implode(" UNION ALL ", $sources) . ") referral_sources
        GROUP BY lower(trim(name))
        ORDER BY last_used DESC, last_id DESC
        LIMIT 15
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $suggestions = array_values(array_filter(array_map(
        static fn($row) => trim((string)($row['name'] ?? '')),
        $stmt->fetchAll()
    )));

    referral_json(['suggestions' => $suggestions]);
} catch (Throwable $e) {
    error_log('[ZimRx] patient_referrals error: ' . $e->getMessage());
    referral_json(['error' => 'An internal error occurred. Please try again.']);
}
