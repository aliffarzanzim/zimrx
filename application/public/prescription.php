<?php
declare(strict_types=1);

// Clinical prescription workspace: manages patient demographic prefill, module card arrangement, and script bindings.

$zimrx_prescription_ob_level = ob_get_level();
ob_start();
require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/emr_identity_lib.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/DashboardModules.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PatientCustomFields.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/VisitFormModules.php';

zimrx_ensure_visit_identity_schema($pdo);
$activePracticeProfile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
$prescriptionProfileId = (int)($activePracticeProfile['id'] ?? 0);
if ($prescriptionProfileId <= 0 || (int)($activePracticeProfile['doctor_id'] ?? 0) <= 0) {
    http_response_code(403);
    while (ob_get_level() > $zimrx_prescription_ob_level) {
        ob_end_clean();
    }
    exit('An active practice profile is required to create a prescription.');
}

function prescription_dmy_date(?string $value): string {
    $date = trim((string)$value);
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $matches)) {
        return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
    }

    return $date;
}

function prescription_referral_select_value(?string $category): string {
    $key = strtolower(trim((string)$category));
    $key = str_replace(['-', ' '], '_', $key);
    return match ($key) {
        'doctor' => 'Doctor',
        'others' => 'Others',
        'other_patient' => 'Other Patient',
        default => 'Self',
    };
}

function prescription_active_doctor_id(): int {
    return (int)($_SESSION['active_profile_doctor_id'] ?? 0);
}

function prescription_merge_patient_prefill(PDO $pdo, array $prefill, int $doctorId, int $profileId): array {
    $patientId = (int)($prefill['patient_id'] ?? 0);
    $regNo = trim((string)($prefill['reg_no'] ?? ''));
    if ($patientId <= 0 && $regNo === '') {
        return $prefill;
    }
    if ($doctorId <= 0 || $profileId <= 0) {
        return $prefill;
    }

    $where = [];
    $params = ['doctor_id' => $doctorId];
    if ($patientId > 0) {
        $where[] = 'p.id = :patient_id';
        $params['patient_id'] = $patientId;
    }
    if ($regNo !== '') {
        $where[] = 'upper(p.reg_no) = upper(:reg_no)';
        $params['reg_no'] = $regNo;
    }

    $stmt = $pdo->prepare(
        "SELECT p.*
         FROM zimrx_patients p
         WHERE (" . implode(' OR ', $where) . ")
           AND (
                (p.doctor_id = :profile_doctor_id AND (
                    p.profile_id = :active_profile_id
                    OR p.profile_id IN (
                        SELECT source_profile.id
                        FROM zimrx_doctor_profiles source_profile
                        WHERE source_profile.doctor_id = :source_doctor_id
                          AND source_profile.is_active = 1
                          AND source_profile.is_deleted = 0
                          AND source_profile.share_patient_particulars = 1
                    )
                ))
                OR EXISTS (
                    SELECT 1
                    FROM zimrx_patient_doctor_access pda
                    WHERE pda.patient_id = p.id
                      AND pda.doctor_id = :doctor_id
                      AND pda.can_view = 1
                      AND p.doctor_id <> :access_owner_doctor_id
                )
           )
         LIMIT 1"
    );
    $stmt->execute($params + [
        'active_profile_id' => $profileId,
        'profile_doctor_id' => $doctorId,
        'source_doctor_id' => $doctorId,
        'access_owner_doctor_id' => $doctorId,
    ]);
    $patient = $stmt->fetch();
    if (!$patient) {
        return $prefill;
    }

    $rawExtra = json_decode((string)($patient['extra_demographics_json'] ?? '{}'), true);
    $patient['extra_demographics_json'] = json_encode(
        \ZimRx\Services\PatientCustomFields::projectForDoctor(
            is_array($rawExtra) ? $rawExtra : [],
            $doctorId
        ),
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $map = [
        'patient_id' => 'id',
        'reg_no' => 'reg_no',
        'patient_name' => 'full_name',
        'age' => 'age',
        'age_unit' => 'age_unit',
        'dob' => 'dob',
        'dob_type' => 'dob_type',
        'gender' => 'gender',
        'blood_group' => 'blood_group',
        'marital_status' => 'marital_status',
        'mobile' => 'mobile',
        'email' => 'email',
        'nid_no' => 'nid_no',
        'passport_no' => 'passport_no',
        'occupation' => 'occupation',
        'address' => 'address',
        'extra_demographics_json' => 'extra_demographics_json',
        'weight' => 'weight',
        'weight_unit' => 'weight_unit',
        'height' => 'height',
        'height_unit' => 'height_unit',
    ];

    foreach ($map as $target => $source) {
        if (($prefill[$target] ?? '') !== '') {
            continue;
        }
        $value = (string)($patient[$source] ?? '');
        if ($target === 'dob') {
            $value = prescription_dmy_date($value);
        }
        if ($value !== '') {
            $prefill[$target] = $value;
        }
    }

    $prefill['age_unit'] = (string)($prefill['age_unit'] ?? '') !== '' ? (string)$prefill['age_unit'] : 'Years';
    $prefill['weight_unit'] = (string)($prefill['weight_unit'] ?? '') !== '' ? (string)$prefill['weight_unit'] : 'kg';
    $prefill['height_unit'] = (string)($prefill['height_unit'] ?? '') !== '' ? (string)$prefill['height_unit'] : 'inch';

    return $prefill;
}

function prescription_query_prefill(): array {
    $prefill = [
        'appointment_id' => trim((string)($_GET['appointment_id'] ?? '')),
        'patient_id' => trim((string)($_GET['patient_id'] ?? '')),
        'reg_no' => trim((string)($_GET['reg_no'] ?? '')),
        'visit_no' => trim((string)($_GET['visit_no'] ?? '')),
        'visit_id' => trim((string)($_GET['visit_id'] ?? ($_GET['visit_code'] ?? ''))),
        'visit_code' => trim((string)($_GET['visit_id'] ?? ($_GET['visit_code'] ?? ''))),
        'ref_type' => prescription_referral_select_value($_GET['referral_category'] ?? ''),
        'referral_name' => trim((string)($_GET['referral_name'] ?? '')),
        'appointment_date' => date('d/m/Y'),
    ];

    $appointmentId = (int)$prefill['appointment_id'];

    try {
        global $pdo;
        global $prescriptionProfileId;
        $pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : DbConnections::userdata();
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $doctorId = prescription_active_doctor_id();
    } catch (Throwable $e) {
        return $prefill;
    }

    if ($appointmentId > 0) {
        try {
            $stmt = $pdo->prepare(
                "SELECT
                    a.id,
                    a.patient_id,
                    a.appointment_no,
                    a.appointment_date,
                    a.appointment_time,
                    coalesce(nullif(a.reg_no, ''), p.reg_no, '') AS reg_no,
                    coalesce(nullif(a.patient_name, ''), p.full_name, '') AS patient_name,
                    coalesce(nullif(a.age, ''), p.age, '') AS age,
                    coalesce(nullif(a.age_unit, ''), p.age_unit, 'Years') AS age_unit,
                    coalesce(nullif(a.dob, ''), p.dob, '') AS dob,
                    coalesce(nullif(a.dob_type, ''), p.dob_type, 'exact') AS dob_type,
                    coalesce(nullif(a.gender, ''), p.gender, '') AS gender,
                    coalesce(nullif(a.blood_group, ''), p.blood_group, '') AS blood_group,
                    coalesce(nullif(a.marital_status, ''), p.marital_status, '') AS marital_status,
                    coalesce(nullif(a.mobile, ''), p.mobile, '') AS mobile,
                    coalesce(nullif(a.email, ''), p.email, '') AS email,
                    coalesce(nullif(a.nid_no, ''), p.nid_no, '') AS nid_no,
                    coalesce(nullif(a.passport_no, ''), p.passport_no, '') AS passport_no,
                    coalesce(nullif(a.occupation, ''), p.occupation, '') AS occupation,
                    coalesce(nullif(a.address, ''), p.address, '') AS address,
                    coalesce(nullif(a.extra_demographics_json, ''), p.extra_demographics_json, '{}') AS extra_demographics_json,
                    a.extra_demographics_json AS appointment_extra_demographics_json,
                    p.extra_demographics_json AS patient_extra_demographics_json,
                    p.doctor_id AS patient_owner_doctor_id,
                    coalesce(nullif(a.weight, ''), p.weight, '') AS weight,
                    coalesce(nullif(a.weight_unit, ''), p.weight_unit, 'kg') AS weight_unit,
                    coalesce(nullif(a.height, ''), p.height, '') AS height,
                    coalesce(nullif(a.height_unit, ''), p.height_unit, 'inch') AS height_unit,
                    coalesce(nullif(a.referral_category, ''), 'self') AS referral_category,
                    coalesce(a.referral_name, '') AS referral_name,
                    a.visit_no,
                    a.visit_id,
                    a.visit_id AS visit_code
                 FROM zimrx_appointments a
                 LEFT JOIN zimrx_patients p ON p.id = a.patient_id
                    AND (p.profile_id = :patient_profile_id OR p.profile_id IN (
                        SELECT source_profile.id FROM zimrx_doctor_profiles source_profile
                        WHERE source_profile.doctor_id = :patient_profile_doctor
                          AND source_profile.is_active = 1 AND source_profile.is_deleted = 0
                          AND source_profile.share_patient_particulars = 1
                    ))
                 WHERE a.id = :id
                   AND a.doctor_id = :doctor_id
                   AND a.profile_id = :profile_id
                 LIMIT 1"
            );
            $stmt->execute([
                'id' => $appointmentId,
                'doctor_id' => $doctorId,
                'profile_id' => $prescriptionProfileId,
                'patient_profile_id' => $prescriptionProfileId,
                'patient_profile_doctor' => $doctorId,
            ]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            $row = null;
        }

        if ($row) {
            $appointmentExtra = trim((string)($row['appointment_extra_demographics_json'] ?? ''));
            $patientExtra = trim((string)($row['patient_extra_demographics_json'] ?? ''));
            $sourceExtra = $appointmentExtra !== '' ? $appointmentExtra : $patientExtra;
            $decodedExtra = json_decode($sourceExtra !== '' ? $sourceExtra : '{}', true);
            $projectedExtra = \ZimRx\Services\PatientCustomFields::projectForDoctor(
                is_array($decodedExtra) ? $decodedExtra : [],
                $doctorId
            );
            $prefill = [
                'appointment_id' => (string)($row['id'] ?? ''),
                'appointment_no' => (string)($row['appointment_no'] ?? ''),
                'appointment_time' => (string)($row['appointment_time'] ?? ''),
                'appointment_date' => prescription_dmy_date($row['appointment_date'] ?? ''),
                'patient_id' => (string)($row['patient_id'] ?? ''),
                'reg_no' => (string)($row['reg_no'] ?? ''),
                'patient_name' => (string)($row['patient_name'] ?? ''),
                'age' => (string)($row['age'] ?? ''),
                'age_unit' => (string)($row['age_unit'] ?? 'Years'),
                'dob' => prescription_dmy_date($row['dob'] ?? ''),
                'dob_type' => (string)($row['dob_type'] ?? 'exact'),
                'gender' => (string)($row['gender'] ?? ''),
                'blood_group' => (string)($row['blood_group'] ?? ''),
                'marital_status' => (string)($row['marital_status'] ?? ''),
                'mobile' => (string)($row['mobile'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'nid_no' => (string)($row['nid_no'] ?? ''),
                'passport_no' => (string)($row['passport_no'] ?? ''),
                'occupation' => (string)($row['occupation'] ?? ''),
                'address' => (string)($row['address'] ?? ''),
                'extra_demographics_json' => json_encode($projectedExtra, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'weight' => (string)($row['weight'] ?? ''),
                'weight_unit' => (string)($row['weight_unit'] ?? 'kg'),
                'height' => (string)($row['height'] ?? ''),
                'height_unit' => (string)($row['height_unit'] ?? 'inch'),
                'visit_no' => (string)($row['visit_no'] ?? ''),
                'visit_id' => (string)($row['visit_id'] ?? ''),
                'visit_code' => (string)($row['visit_id'] ?? ''),
                'ref_type' => prescription_referral_select_value($row['referral_category'] ?? ''),
                'referral_name' => (string)($row['referral_name'] ?? ''),
            ];
        }
    }

    try {
        return prescription_merge_patient_prefill($pdo, $prefill, $doctorId, $prescriptionProfileId);
    } catch (Throwable $e) {
        return $prefill;
    }
}

$prescription_prefill = prescription_query_prefill();
$page_title = "ZimRx - New Prescription";
$extra_css = ['assets/css/pages/prescription.css'];

$dashboardModules = new \ZimRx\Services\DashboardModules(
    ZIMRX_BASE_DIR . '/views/modules'
);
$default_left_layout = $dashboardModules->defaultLayout('left');
$default_right_layout = $dashboardModules->defaultLayout('right');

function module_card_class(string $module_name): string {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $module_name));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'module';
    }
    return 'module-card module-card-' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');
}

function normalize_left_history_layout(array $layout): array {
    $hasHistory = in_array('History', $layout, true);
    $historyInserted = false;
    $normalized = [];

    foreach ($layout as $moduleName) {
        if ($moduleName === 'P/C') {
            $moduleName = 'P/C';
        }
        if ($moduleName === 'D/H') {
            if (!$hasHistory && !$historyInserted) {
                $normalized[] = 'History';
                $historyInserted = true;
            }
            continue;
        }

        if ($moduleName === 'History') {
            if ($historyInserted) {
                continue;
            }
            $historyInserted = true;
        }

        $normalized[] = $moduleName;
    }

    return $normalized;
}

$prescriptionDoctorId = prescription_active_doctor_id();
$layoutV = $_COOKIE['zimrx_layout_v'] ?? '';

// Check database first if doctor is authenticated
$db_left = null;
$db_right = null;
if ($prescriptionDoctorId > 0 && function_exists('zimrx_db_table_exists') && zimrx_db_table_exists($pdo, 'zimrx_interface_settings')) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM zimrx_interface_settings WHERE doctor_id = :doc AND setting_scope = 'dashboard' AND setting_key IN ('left_layout', 'right_layout')");
        $stmt->execute(['doc' => $prescriptionDoctorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($rows['left_layout'])) {
            $dec = json_decode((string)$rows['left_layout'], true);
            if (is_array($dec)) $db_left = array_values(array_filter($dec, fn($m) => $m !== ''));
        }
        if (!empty($rows['right_layout'])) {
            $dec = json_decode((string)$rows['right_layout'], true);
            if (is_array($dec)) $db_right = array_values(array_filter($dec, fn($m) => $m !== ''));
        }
    } catch (Throwable $e) {}
}

if ($db_left !== null) {
    $left_layout = $db_left;
} elseif (isset($_COOKIE['zimrx_left_layout'])) {
    $decoded = json_decode(urldecode($_COOKIE['zimrx_left_layout']), true);
    if (!is_array($decoded)) {
        $decoded = json_decode($_COOKIE['zimrx_left_layout'], true);
    }
    $left_layout = is_array($decoded) ? array_values(array_filter($decoded, fn($m) => $m !== '')) : $default_left_layout;
} else {
    $left_layout = $default_left_layout;
}

if ($db_right !== null) {
    $right_layout = $db_right;
} elseif (isset($_COOKIE['zimrx_right_layout'])) {
    $decoded = json_decode(urldecode($_COOKIE['zimrx_right_layout']), true);
    if (!is_array($decoded)) {
        $decoded = json_decode($_COOKIE['zimrx_right_layout'], true);
    }
    $right_layout = is_array($decoded) ? array_values(array_filter($decoded, fn($m) => $m !== '')) : $default_right_layout;
} else {
    $right_layout = $default_right_layout;
}

// Auto-migrate legacy layouts if version 3 is not yet established
if ($layoutV !== '3') {
    if ($db_left === null) {
        $left_layout = array_values(array_filter($left_layout, fn($m) => $m !== 'Note' && $m !== 'Paediatric History'));
    }
    if ($db_right === null) {
        $right_layout = array_values(array_filter($right_layout, fn($m) => $m !== 'Drug Summary & Interaction'));
    }
    if (!headers_sent()) {
        setcookie('zimrx_layout_v', '3', time() + 31536000, '/', '', false, false);
        setcookie('zimrx_left_layout', json_encode($left_layout, JSON_UNESCAPED_UNICODE), time() + 31536000, '/', '', false, false);
        setcookie('zimrx_right_layout', json_encode($right_layout, JSON_UNESCAPED_UNICODE), time() + 31536000, '/', '', false, false);
    }
}

$left_layout = normalize_left_history_layout($left_layout);
$specialtyExamScripts = [];
$specialtyExamStyles = [];
$hasSpecialtyExamModule = false;
$hasPaediatricModule = false;
foreach ([['left', $left_layout], ['right', $right_layout]] as [$panel, $moduleNames]) {
    foreach ($moduleNames as $moduleName) {
        if (!is_string($moduleName) || !$dashboardModules->supportsPanel($moduleName, $panel)) {
            continue;
        }

        $viewPath = $dashboardModules->viewPathFor($moduleName);
        $viewName = $viewPath !== null ? pathinfo($viewPath, PATHINFO_FILENAME) : '';
        if (!str_starts_with($viewName, 'exam_')) {
            if ($viewName === 'paediatric') {
                $hasPaediatricModule = true;
            }
            continue;
        }

        $hasSpecialtyExamModule = true;
        $scriptPath = 'assets/js/modules/' . $viewName . '.js';
        if (is_file(__DIR__ . '/' . $scriptPath)) {
            $specialtyExamScripts[$scriptPath] = true;
        }

        $stylePath = 'assets/css/modules/' . $viewName . '.css';
        if (is_file(__DIR__ . '/' . $stylePath)) {
            $specialtyExamStyles[$stylePath] = true;
        }
    }
}
$specialtyExamScripts = array_keys($specialtyExamScripts);
$specialtyExamStyles = array_keys($specialtyExamStyles);
if ($hasSpecialtyExamModule) {
    $specialtyExamStyles[] = 'assets/css/modules/exam_specialties.css';
}
if (in_array('Local Examination', $left_layout, true) || in_array('Local Examination', $right_layout, true)) {
    $specialtyExamStyles[] = 'assets/css/modules/exam_abdominal.css';
}
if (in_array('O/H', $left_layout, true)) {
    $specialtyExamStyles[] = 'assets/css/modules/exam_pelvic.css';
}
if ($hasPaediatricModule) {
    $specialtyExamStyles[] = 'assets/css/modules/paediatric.css';
}
$specialtyExamStyles = array_values(array_unique(array_filter(
    $specialtyExamStyles,
    static fn(string $stylePath): bool => is_file(__DIR__ . '/' . $stylePath)
)));
$prescriptionModuleStyles = [
    'assets/css/modules/pres_particulars.css',
    'assets/css/modules/pc.css',
    'assets/css/modules/history.css',
    'assets/css/modules/oh.css',
    'assets/css/modules/rx.css',
    'assets/css/modules/reports.css',
    'assets/css/modules/table_resizer.css',
    'assets/css/modules/modals_dialogs.css',
    'assets/css/modules/ai_analyzer.css',
    'assets/css/modules/calculators.css',
    'assets/css/modules/ot_note.css',
    'assets/css/modules/text_pad.css',
    'assets/css/modules/font_format.css',
];
$extra_css = array_merge($prescriptionModuleStyles, ['assets/css/pages/prescription.css'], $specialtyExamStyles);
$paediatricScriptPath = $hasPaediatricModule ? 'assets/js/modules/paediatric.js' : null;
$visitFormModules = \ZimRx\Services\VisitFormModules::loadForDoctor($pdo, $prescriptionDoctorId);
$visitFormInitialValues = [];
$appointmentId = (int)($prescription_prefill['appointment_id'] ?? 0);
if ($appointmentId > 0 && $prescriptionDoctorId > 0) {
    $savedVisitStmt = $pdo->prepare(
        "SELECT v.clinical_snapshot_json
         FROM zimrx_appointments a
         LEFT JOIN zimrx_visits v ON (v.id = a.visit_record_id OR (a.visit_record_id IS NULL AND v.appointment_id = a.id))
             AND v.doctor_id = a.doctor_id AND v.profile_id = :profile_id
         WHERE a.id = :appointment_id AND a.doctor_id = :doctor_id AND a.profile_id = :profile_id
         LIMIT 1"
    );
    $savedVisitStmt->execute(['appointment_id' => $appointmentId, 'doctor_id' => $prescriptionDoctorId, 'profile_id' => $prescriptionProfileId]);
    $savedSnapshotJson = $savedVisitStmt->fetchColumn();
    $savedSnapshot = is_string($savedSnapshotJson) ? json_decode($savedSnapshotJson, true) : null;
    $savedModules = $savedSnapshot['clinical']['visit_form_modules'] ?? [];
    if (is_array($savedModules)) {
        foreach ($savedModules as $savedModule) {
            if (!is_array($savedModule) || !is_string($savedModule['key'] ?? null) || !is_array($savedModule['fields'] ?? null)) {
                continue;
            }
            foreach ($savedModule['fields'] as $savedField) {
                if (is_array($savedField) && is_string($savedField['key'] ?? null) && is_scalar($savedField['value'] ?? null)) {
                    $visitFormInitialValues[$savedModule['key']][$savedField['key']] = (string)$savedField['value'];
                }
            }
        }
    }
}
$renderVisitFormModules = static function (array $modules, string $column): void {
    foreach ($modules as $module) {
        if (($module['column'] ?? 'left') !== $column) {
            continue;
        }
        echo '<section class="module-card custom-visit-form-card visit-form-module" data-module-key="'
            . htmlspecialchars($module['key'], ENT_QUOTES, 'UTF-8') . '" data-module-title="'
            . htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<header class="module-header"><h2>' . htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8') . '</h2></header>';
        echo '<div class="visit-form-fields">';
        foreach ($module['fields'] as $field) {
            $key = htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8');
            $required = $field['required'] ? ' required' : '';
            echo '<label class="visit-form-field" data-field-label="' . $label . '" data-field-print="' . ($field['print'] ? 'true' : 'false') . '"><span>' . $label . ($field['required'] ? ' *' : '') . '</span>';
            if ($field['type'] === 'textarea') {
                echo '<textarea data-field-key="' . $key . '" maxlength="2000"' . $required . '></textarea>';
            } elseif ($field['type'] === 'select') {
                echo '<select data-field-key="' . $key . '"' . $required . '><option value="">-- Select --</option>';
                foreach ($field['options'] as $option) {
                    $safeOption = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
                    echo '<option value="' . $safeOption . '">' . $safeOption . '</option>';
                }
                echo '</select>';
            } else {
                $type = $field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text');
                echo '<input type="' . $type . '" data-field-key="' . $key . '" maxlength="2000"' . $required . '>';
            }
            echo '</label>';
        }
        echo '</div></section>';
    }
};
$reportsIndex = array_search('Reports', $right_layout, true);
if ($reportsIndex !== false) {
    $reports_replacement = [];
    if (!in_array('Report Entry', $right_layout, true)) {
        $reports_replacement[] = 'Report Entry';
    }
    if (!in_array('Upload Reports & Documents', $right_layout, true) && !in_array('Uploaded Reports', $right_layout, true)) {
        $reports_replacement[] = 'Upload Reports & Documents';
    }
    array_splice($right_layout, $reportsIndex, 1, $reports_replacement);
}

include 'header.php';

$usesRichTextEditor = in_array('Text Pad', $left_layout, true)
    || in_array('Text Pad', $right_layout, true)
    || in_array('OT Note', $left_layout, true)
    || in_array('OT Note', $right_layout, true);
if ($usesRichTextEditor):
    ?>
    <script src="assets/vendor/nicedit/nicEdit-latest.js?v=<?= filemtime(__DIR__ . '/assets/vendor/nicedit/nicEdit-latest.js') ?>" defer></script>
    <script src="assets/vendor/nicedit/nicEdit-zimrx-custom.js?v=<?= filemtime(__DIR__ . '/assets/vendor/nicedit/nicEdit-zimrx-custom.js') ?>" defer></script>
    <?php
endif;
?>

    <?php
    // Include the patient particulars component.
    include ZIMRX_BASE_DIR . '/views/modules/pres_particulars.php';
    ?>

    <div class="app-container zrx-page-container">
        <aside class="sidebar" id="sidebar-modules">
            <?php
            foreach ($left_layout as $module_name) {
                $moduleViewPath = is_string($module_name) && $dashboardModules->supportsPanel($module_name, 'left')
                    ? $dashboardModules->viewPathFor($module_name)
                    : null;
                if ($moduleViewPath !== null) {
                    echo '<div class="' . module_card_class($module_name) . '">';
                    include $moduleViewPath;
                    echo '</div>';
                }
            }
            $renderVisitFormModules($visitFormModules, 'left');
            ?>
        </aside>
        <main class="main-side" id="main-modules">
            <?php
            foreach ($right_layout as $module_name) {
                $moduleViewPath = is_string($module_name) && $dashboardModules->supportsPanel($module_name, 'right')
                    ? $dashboardModules->viewPathFor($module_name)
                    : null;
                if ($moduleViewPath !== null) {
                    echo '<div class="' . module_card_class($module_name) . '">';
                    include $moduleViewPath;
                    echo '</div>';
                }
            }
            $renderVisitFormModules($visitFormModules, 'right');
            ?>
        </main>
    </div>
    <!-- Scripts -->
    <script src="assets/js/modules/patient_particulars.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/patient_particulars.js') ?>" defer></script>
    <script id="visit-form-initial-values" type="application/json"><?= json_encode($visitFormInitialValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
    <script id="dashboard-module-config" type="application/json"><?= json_encode($dashboardModules->clientConfig(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
    <script src="assets/js/modules/visit_form_modules.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/visit_form_modules.js') ?>" defer></script>
    <script src="assets/js/modules/dosage_form_icons.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/dosage_form_icons.js') ?>" defer></script>
    <script src="assets/js/layout/config.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/config.js') ?>" defer></script>
    <script src="assets/js/layout/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/dashboard.js') ?>" defer></script>
    <script src="assets/js/modules/rx_autocomplete.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/rx_autocomplete.js') ?>" defer></script>
    <script src="assets/js/modules/pc.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/pc.js') ?>" defer></script>
    <script src="assets/js/modules/oh.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/oh.js') ?>" defer></script>
    <script src="assets/js/data/growth_chart_data.js?v=<?= filemtime(__DIR__ . '/assets/js/data/growth_chart_data.js') ?>" defer></script>
    <?php if ($paediatricScriptPath !== null): ?>
        <script src="<?= htmlspecialchars($paediatricScriptPath, ENT_QUOTES, 'UTF-8') ?>?v=<?= filemtime(__DIR__ . '/' . $paediatricScriptPath) ?>" defer></script>
    <?php endif; ?>
    <?php if ($specialtyExamScripts !== []): ?>
        <script src="assets/js/modules/exam_presets.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_presets.js') ?>" defer></script>
        <?php foreach ($specialtyExamScripts as $scriptPath): ?>
            <?php $scriptFile = __DIR__ . '/' . $scriptPath; ?>
            <script src="<?= htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8') ?>?v=<?= filemtime($scriptFile) ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="assets/js/pages/prescription_preview.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/prescription_preview.js') ?>" defer></script>
    <script src="assets/js/modules/history.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/history.js') ?>" defer></script>
    <script src="assets/js/layout/help_guidelines.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/help_guidelines.js') ?>" defer></script>
    <script src="assets/js/layout/table_column_resizer.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/table_column_resizer.js') ?>" defer></script>
    <script src="assets/js/layout/grid_navigation.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/grid_navigation.js') ?>" defer></script>
    <script src="assets/js/layout/boot.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/boot.js') ?>" defer></script>

    <script src="assets/js/modules/rx.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/rx.js') ?>" defer></script>
<?php include 'footer.php'; ?>
<?php
if (ob_get_level() > $zimrx_prescription_ob_level) {
    ob_end_flush();
}
?>
