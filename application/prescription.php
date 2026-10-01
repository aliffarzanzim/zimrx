<?php
declare(strict_types=1);

// Clinical prescription workspace: manages patient demographic prefill, module card arrangement, and script bindings.

$zimrx_prescription_ob_level = ob_get_level();
ob_start();
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once __DIR__ . '/lib/visit_identity.php';

zimrx_ensure_visit_identity_schema($pdo);

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
    $activeDoctorId = (int)($_SESSION['active_doctor_id'] ?? 0);
    return $activeDoctorId > 0 ? $activeDoctorId : current_user_doctor_id();
}

function prescription_merge_patient_prefill(PDO $pdo, array $prefill, int $doctorId): array {
    $patientId = (int)($prefill['patient_id'] ?? 0);
    $regNo = trim((string)($prefill['reg_no'] ?? ''));
    if ($patientId <= 0 && $regNo === '') {
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
    $stmt->execute($params);
    $patient = $stmt->fetch();
    if (!$patient) {
        return $prefill;
    }

    $map = [
        'patient_id' => 'id',
        'reg_no' => 'reg_no',
        'patient_name' => 'full_name',
        'age' => 'age',
        'age_unit' => 'age_unit',
        'dob' => 'dob',
        'gender' => 'gender',
        'blood_group' => 'blood_group',
        'mobile' => 'mobile',
        'occupation' => 'occupation',
        'address' => 'address',
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
        $pdo = $pdo instanceof PDO ? $pdo : DbConnections::userdata();
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
                    a.visit_no,
                    a.visit_id,
                    a.visit_id AS visit_code
                 FROM zimrx_appointments a
                 LEFT JOIN zimrx_patients p ON p.id = a.patient_id
                 WHERE a.id = :id
                   AND a.doctor_id = :doctor_id
                 LIMIT 1"
            );
            $stmt->execute([
                'id' => $appointmentId,
                'doctor_id' => $doctorId,
            ]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            $row = null;
        }

        if ($row) {
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
                'gender' => (string)($row['gender'] ?? ''),
                'blood_group' => (string)($row['blood_group'] ?? ''),
                'mobile' => (string)($row['mobile'] ?? ''),
                'occupation' => (string)($row['occupation'] ?? ''),
                'address' => (string)($row['address'] ?? ''),
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
        return prescription_merge_patient_prefill($pdo, $prefill, $doctorId);
    } catch (Throwable $e) {
        return $prefill;
    }
}

$prescription_prefill = prescription_query_prefill();
$page_title = "ZimRx - New Prescription";
$body_class = trim(($body_class ?? '') . ' zimrx-prescription-hold');
$extra_css = ['assets/css/pages/prescription.css'];
include 'header.php';
?>

    <?php
    // Include the patient particulars component.
    include 'modules/pres_particulars.php';
    ?>

<?php
// Clinical module grid layout and file mappings
$default_left_layout = [
  "P/C", "AI Analyzer", "History", "P/E", "Breast Examination", "Local Examination",
  "Burn Assessment", "ENT Examination", "Dental Chart", "Diabetic Foot", "Dermatology", "Psychiatry", "Orthopaedics", "Urology", "Neurology", "Cardiology", "Pulmonology", "Endocrinology",
  "Dx", "Ix", "Plan", "Note", "O/H", "M/H", "Paediatric History"
];

$default_right_layout = [
  "Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators",
  "Ophthalmology", "Text Pad", "OT Note", "Font Format"
];

$module_file_map = [
  "P/C" => "pc.php",
  "AI Analyzer" => "ai_analyzer.php",
  "History" => "history.php",
  "P/E" => "pe.php",
  "O/E" => "pe.php",
  "Breast Examination" => "exam_breast.php",
  "Local Examination" => "exam_local.php",
  "Burn Assessment" => "exam_burn.php",
  "ENT Examination" => "exam_ent.php",
  "Dental Chart" => "exam_dental.php",
  "Diabetic Foot" => "exam_diabetic_foot.php",
  "Dermatology" => "exam_dermatology.php",
  "Psychiatry" => "exam_psychiatry.php",
  "Orthopaedics" => "exam_orthopaedics.php",
  "Urology" => "exam_urology.php",
  "Neurology" => "exam_neurology.php",
  "Cardiology" => "exam_cardiology.php",
  "Pulmonology" => "exam_pulmonology.php",
  "Endocrinology" => "exam_endocrinology.php",
  "Dx" => "dx.php",
  "Ix" => "ix.php",
  "Plan" => "plan.php",
  "Note" => "note.php",
  "O/H" => "oh.php",
  "D/H" => "dh.php",
  "M/H" => "m_h.php",
  "Paediatric History" => "paediatric.php",
  "Rx" => "rx.php",
  "Drug Summary & Interaction" => "drug_summary.php",
  "Advice" => "advice.php",
  "Report Entry" => "report_entry.php",
  "Upload Reports & Documents" => "uploaded_reports.php",
  "Uploaded Reports" => "uploaded_reports.php",
  "Reports" => "reports.php",
  "Calculators" => "calculators.php",
  "Ophthalmology" => "exam_ophthalmology.php",
  "Text Pad" => "text_pad.php",
  "OT Note" => "ot_note.php",
  "Font Format" => "font_format.php"
];

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

// Load user-customized layout if available via Cookies
$left_layout = $default_left_layout;
if (isset($_COOKIE['zimrx_left_layout'])) {
    $decoded = json_decode($_COOKIE['zimrx_left_layout'], true);
    if (is_array($decoded)) {
        $left_layout = $decoded;
    }
}
$left_layout = normalize_left_history_layout($left_layout);
if (in_array('P/E', $left_layout, true)) {
    $peIndex = array_search('P/E', $left_layout, true);
    $specialtyModules = [
        'Breast Examination',
        'Local Examination',
        'Burn Assessment',
        'ENT Examination',
        'Dental Chart',
        'Diabetic Foot',
        'Dermatology',
        'Psychiatry',
        'Orthopaedics',
        'Urology',
        'Neurology',
        'Cardiology',
        'Pulmonology',
        'Endocrinology'
    ];
    $leftInsert = [];
    foreach ($specialtyModules as $specMod) {
        if (!in_array($specMod, $left_layout, true)) {
            $leftInsert[] = $specMod;
        }
    }
    if (!empty($leftInsert)) {
        array_splice($left_layout, $peIndex + 1, 0, $leftInsert);
    }
}

$right_layout = $default_right_layout;
if (isset($_COOKIE['zimrx_right_layout'])) {
    $decoded = json_decode($_COOKIE['zimrx_right_layout'], true);
    if (is_array($decoded)) {
        $right_layout = $decoded;
    }
}
if (in_array('Rx', $right_layout, true) && !in_array('Drug Summary & Interaction', $right_layout, true)) {
    $rxIndex = array_search('Rx', $right_layout, true);
    array_splice($right_layout, $rxIndex + 1, 0, ['Drug Summary & Interaction']);
}
if (in_array('Text Pad', $right_layout, true) && !in_array('Ophthalmology', $right_layout, true)) {
    $tpIndex = array_search('Text Pad', $right_layout, true);
    array_splice($right_layout, $tpIndex, 0, ['Ophthalmology']);
}
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
?>

    <div class="app-container zrx-page-container">
        <aside class="sidebar" id="sidebar-modules">
            <?php
            foreach ($left_layout as $module_name) {
                if ($module_name && isset($module_file_map[$module_name])) {
                    echo '<div class="' . module_card_class($module_name) . '">';
                    include 'modules/' . $module_file_map[$module_name];
                    echo '</div>';
                }
            }
            ?>
        </aside>
        <main class="main-side" id="main-modules">
            <?php
            foreach ($right_layout as $module_name) {
                if ($module_name && isset($module_file_map[$module_name])) {
                    echo '<div class="' . module_card_class($module_name) . '">';
                    include 'modules/' . $module_file_map[$module_name];
                    echo '</div>';
                }
            }
            ?>
        </main>
    </div>
    <!-- Scripts -->
    <script src="vendor/flatpickr/flatpickr.min.js"></script>
    <script src="assets/js/modules/dosage_form_icons.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/dosage_form_icons.js') ?>"></script>
    <script src="assets/js/layout/config.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/config.js') ?>"></script>
    <script src="assets/js/layout/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/dashboard.js') ?>"></script>
    <script src="assets/js/modules/rx_autocomplete.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/rx_autocomplete.js') ?>"></script>
    <script src="assets/js/modules/pc.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/pc.js') ?>"></script>
    <script src="assets/js/modules/oh.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/oh.js') ?>"></script>
    <script src="assets/js/data/growth_chart_data.js?v=<?= filemtime(__DIR__ . '/assets/js/data/growth_chart_data.js') ?>"></script>
    <script src="assets/js/modules/paediatric.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/paediatric.js') ?>"></script>
    <script src="assets/js/modules/exam_presets.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_presets.js') ?>"></script>
    <script src="assets/js/modules/exam_breast.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_breast.js') ?>"></script>
    <script src="assets/js/modules/exam_local.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_local.js') ?>"></script>
    <script src="assets/js/modules/exam_ophthalmology.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_ophthalmology.js') ?>"></script>
    <script src="assets/js/modules/exam_burn.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_burn.js') ?>"></script>
    <script src="assets/js/modules/exam_ent.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_ent.js') ?>"></script>
    <script src="assets/js/modules/exam_dental.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_dental.js') ?>"></script>
    <script src="assets/js/modules/exam_diabetic_foot.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_diabetic_foot.js') ?>"></script>
    <script src="assets/js/modules/exam_dermatology.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_dermatology.js') ?>"></script>
    <script src="assets/js/modules/exam_psychiatry.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_psychiatry.js') ?>"></script>
    <script src="assets/js/modules/exam_orthopaedics.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_orthopaedics.js') ?>"></script>
    <script src="assets/js/modules/exam_urology.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_urology.js') ?>"></script>
    <script src="assets/js/modules/exam_neurology.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_neurology.js') ?>"></script>
    <script src="assets/js/modules/exam_cardiology.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_cardiology.js') ?>"></script>
    <script src="assets/js/modules/exam_pulmonology.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_pulmonology.js') ?>"></script>
    <script src="assets/js/modules/exam_endocrinology.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/exam_endocrinology.js') ?>"></script>
    <script src="assets/js/pages/prescription_preview.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/prescription_preview.js') ?>"></script>
    <script src="assets/js/modules/history.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/history.js') ?>"></script>
    <script src="assets/js/layout/help_guidelines.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/help_guidelines.js') ?>"></script>
    <script src="assets/js/layout/table_column_resizer.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/table_column_resizer.js') ?>"></script>
    <script src="assets/js/layout/grid_navigation.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/grid_navigation.js') ?>"></script>
    <script src="assets/js/layout/boot.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/boot.js') ?>"></script>

    <script src="assets/js/modules/rx.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/rx.js') ?>"></script>
<?php include 'footer.php'; ?>
<?php
if (ob_get_level() > $zimrx_prescription_ob_level) {
    ob_end_flush();
}
?>
