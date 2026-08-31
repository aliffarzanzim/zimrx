<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../visit_identity.php';
require_once __DIR__ . '/../emr_identity_lib.php';
require_once __DIR__ . '/../particulars_audit_lib.php';

header('Content-Type: application/json; charset=utf-8');

function emr_json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    global $pdo;
    $pdo = $pdo instanceof PDO ? $pdo : DbConnections::userdata();
    $pdo->exec('PRAGMA busy_timeout = 5000');
} catch (Throwable $e) {
    emr_json_response(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()], 500);
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));
$userRole = current_user_role();
$currentDoctorId = function_exists('current_user_doctor_id') ? current_user_doctor_id() : (int)($_SESSION['doctor_id'] ?? 1);

// Fallback doctor ID if none selected
if ($currentDoctorId <= 0) {
    $stmtDoc = $pdo->query("SELECT id FROM zimrx_doctors ORDER BY id ASC LIMIT 1");
    $currentDoctorId = $stmtDoc ? (int)$stmtDoc->fetchColumn() : 1;
}

switch ($action) {
    case 'search':
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            emr_json_response(['success' => true, 'patients' => [], 'visits' => []]);
        }

        // Direct pattern check
        $isRegPattern = preg_match('/^P\d+/i', $q);
        $isVisitPattern = preg_match('/^V\d+/i', $q);

        // Search patients
        $stmtP = $pdo->prepare(
            "SELECT id, reg_no, full_name, mobile, gender, age, age_unit, blood_group, address
             FROM zimrx_patients
             WHERE reg_no LIKE :q_exact OR full_name LIKE :q_like OR mobile LIKE :q_like
             ORDER BY id DESC LIMIT 20"
        );
        $stmtP->execute([
            'q_exact' => $q,
            'q_like' => '%' . $q . '%'
        ]);
        $patients = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        // Search visits
        $stmtV = $pdo->prepare(
            "SELECT v.id, v.visit_id, v.visit_no, v.visit_date, v.patient_id, p.full_name as patient_name, p.reg_no, p.mobile
             FROM zimrx_visits v
             LEFT JOIN zimrx_patients p ON v.patient_id = p.id
             WHERE v.visit_id LIKE :q_like OR p.reg_no LIKE :q_like OR p.full_name LIKE :q_like
             ORDER BY v.id DESC LIMIT 15"
        );
        $stmtV->execute(['q_like' => '%' . $q . '%']);
        $visits = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        emr_json_response([
            'success' => true,
            'query' => $q,
            'is_reg_pattern' => (bool)$isRegPattern,
            'is_visit_pattern' => (bool)$isVisitPattern,
            'patients' => $patients,
            'visits' => $visits
        ]);
        break;

    case 'get_patient':
        $patientId = (int)($_GET['patient_id'] ?? 0);
        $regNo = trim((string)($_GET['reg'] ?? ''));

        if ($patientId <= 0 && $regNo === '') {
            emr_json_response(['success' => false, 'message' => 'Missing patient identifier'], 400);
        }

        if ($patientId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM zimrx_patients WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $patientId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM zimrx_patients WHERE reg_no = :reg LIMIT 1");
            $stmt->execute(['reg' => $regNo]);
        }
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            emr_json_response(['success' => false, 'message' => 'Patient not found'], 404);
        }

        $patientId = (int)$patient['id'];

        // Get all historical visits for this patient
        $stmtVisits = $pdo->prepare(
            "SELECT v.id as visit_record_id, v.visit_id, v.visit_no, v.visit_date, v.next_visit,
                    v.age_at_visit, v.weight_at_visit, v.weight_unit_at_visit, v.height_at_visit, v.height_unit_at_visit,
                    v.metrics_json, v.billing_json, v.clinical_snapshot_json, v.prescription_html, v.rich_text_json,
                    v.id as prescription_id, v.created_at as rx_created_at
             FROM zimrx_visits v
             WHERE v.patient_id = :patient_id
             ORDER BY v.visit_date DESC, v.id DESC"
        );
        $stmtVisits->execute(['patient_id' => $patientId]);
        $rawVisits = $stmtVisits->fetchAll(PDO::FETCH_ASSOC);

        $timeline = [];
        $trends = [
            'dates' => [],
            'weight' => [],
            'bp_systolic' => [],
            'bp_diastolic' => [],
            'pulse' => []
        ];

        // Parse visits and build timeline & aggregate trends
        foreach ($rawVisits as $v) {
            $ccList = [];
            $dxList = [];

            if (!empty($v['clinical_snapshot_json'])) {
                $snap = json_decode($v['clinical_snapshot_json'], true);
                if (is_array($snap)) {
                    $clinical = $snap['clinical'] ?? $snap;
                    if (!empty($clinical['pc']) && is_array($clinical['pc'])) {
                        foreach ($clinical['pc'] as $item) {
                            $val = is_array($item) ? ($item['name'] ?? $item['term'] ?? '') : (string)$item;
                            if (trim($val) !== '') $ccList[] = trim($val);
                        }
                    }
                    if (!empty($clinical['dx']) && is_array($clinical['dx'])) {
                        foreach ($clinical['dx'] as $item) {
                            $val = is_array($item) ? ($item['name'] ?? $item['diagnosis'] ?? '') : (string)$item;
                            if (trim($val) !== '') $dxList[] = trim($val);
                        }
                    }
                }
            }

            // Extract Vitals from metrics_json or visit_vitals
            $vitals = [];
            if (!empty($v['metrics_json'])) {
                $vitalsDecoded = json_decode($v['metrics_json'], true);
                if (is_array($vitalsDecoded)) {
                    $vitals = $vitalsDecoded;
                }
            }

            $weightVal = (float)($v['weight_at_visit'] ?? ($vitals['weight'] ?? 0));
            $bpStr = trim((string)($vitals['bp'] ?? ''));
            $pulseVal = (int)($vitals['pulse'] ?? 0);

            $sys = null;
            $dia = null;
            if ($bpStr !== '' && strpos($bpStr, '/') !== false) {
                $parts = explode('/', $bpStr);
                $sys = (int)trim($parts[0]);
                $dia = (int)trim($parts[1]);
            }

            $visitDateFmt = date('d M Y', strtotime($v['visit_date']));

            $timeline[] = [
                'visit_record_id' => (int)$v['visit_record_id'],
                'visit_id' => $v['visit_id'] ?: ('V' . $v['visit_record_id']),
                'visit_no' => (int)$v['visit_no'],
                'visit_date' => $v['visit_date'],
                'visit_date_formatted' => $visitDateFmt,
                'chief_complaints' => implode(', ', $ccList),
                'primary_diagnosis' => implode(', ', $dxList),
                'vitals_summary' => $bpStr !== '' ? "BP: $bpStr" . ($pulseVal > 0 ? " | Pulse: $pulseVal" : "") : "",
                'weight' => $v['weight_at_visit'] ? ($v['weight_at_visit'] . ' ' . ($v['weight_unit_at_visit'] ?: 'kg')) : '',
                'has_prescription' => !empty($v['prescription_id'])
            ];

            // Add to chronological trend series
            if ($weightVal > 0 || $sys !== null || $pulseVal > 0) {
                $trends['dates'][] = $visitDateFmt;
                $trends['weight'][] = $weightVal > 0 ? $weightVal : null;
                $trends['bp_systolic'][] = $sys;
                $trends['bp_diastolic'][] = $dia;
                $trends['pulse'][] = $pulseVal > 0 ? $pulseVal : null;
            }
        }

        // Reverse trend arrays so they appear chronologically left-to-right
        $trends['dates'] = array_reverse($trends['dates']);
        $trends['weight'] = array_reverse($trends['weight']);
        $trends['bp_systolic'] = array_reverse($trends['bp_systolic']);
        $trends['bp_diastolic'] = array_reverse($trends['bp_diastolic']);
        $trends['pulse'] = array_reverse($trends['pulse']);

        // Allergies extraction
        $allergies = [];
        $stmtAllergies = $pdo->prepare(
            "SELECT DISTINCT generic_name
             FROM zimrx_prescription_drugs
             WHERE patient_id = :patient_id AND is_history = 1"
        );
        $stmtAllergies->execute(['patient_id' => $patientId]);
        while ($rowA = $stmtAllergies->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($rowA['generic_name'])) $allergies[] = $rowA['generic_name'];
        }

        if ($userRole === 'assistant' || $userRole === 'admin') {
            $trends = ['weight' => [], 'bp' => [], 'pulse' => []];
            $allergies = [];
            foreach ($timeline as &$tItem) {
                unset($tItem['chief_complaints'], $tItem['primary_diagnosis'], $tItem['vitals_summary']);
            }
            unset($tItem);
        }

        emr_json_response([
            'success' => true,
            'patient' => $patient,
            'timeline' => $timeline,
            'trends' => $trends,
            'allergies' => $allergies
        ]);
        break;

    case 'get_visit_details':
        if ($userRole === 'assistant' || $userRole === 'admin') {
            emr_json_response(['success' => false, 'message' => 'Clinical prescription access is restricted.'], 403);
        }

        $visitId = trim((string)($_GET['visit_id'] ?? ''));
        $visitRecordId = (int)($_GET['visit_record_id'] ?? 0);

        if ($visitId === '' && $visitRecordId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Missing visit identifier'], 400);
        }

        if ($visitRecordId > 0) {
            $stmtV = $pdo->prepare("SELECT * FROM zimrx_visits WHERE id = :id LIMIT 1");
            $stmtV->execute(['id' => $visitRecordId]);
        } else {
            $stmtV = $pdo->prepare("SELECT * FROM zimrx_visits WHERE visit_id = :vid LIMIT 1");
            $stmtV->execute(['vid' => $visitId]);
        }
        $visit = $stmtV->fetch(PDO::FETCH_ASSOC);

        if (!$visit) {
            emr_json_response(['success' => false, 'message' => 'Visit record not found'], 404);
        }

        $visitRecordId = (int)$visit['id'];

        // Get prescription snapshot from visit row
        $prescription = [
            'prescription_html' => (string)($visit['prescription_html'] ?? ''),
            'clinical_snapshot_json' => (string)($visit['clinical_snapshot_json'] ?? ''),
            'rich_text_json' => (string)($visit['rich_text_json'] ?? ''),
        ];

        // Get prescribed drugs
        $stmtDrugs = $pdo->prepare("SELECT * FROM zimrx_prescription_drugs WHERE visit_id = :vid ORDER BY sort_order ASC, id ASC");
        $stmtDrugs->execute(['vid' => $visitRecordId]);
        $drugs = $stmtDrugs->fetchAll(PDO::FETCH_ASSOC);

        // Get vitals
        $stmtVitals = $pdo->prepare("SELECT * FROM zimrx_visit_vitals WHERE visit_id = :vid LIMIT 1");
        $stmtVitals->execute(['vid' => $visitRecordId]);
        $vitals = $stmtVitals->fetch(PDO::FETCH_ASSOC);

        // Get revisions history
        $revisions = [];
        if (DbSchema::tableExists($pdo, 'zimrx_visit_revisions')) {
            $stmtRevs = $pdo->prepare("SELECT id, revision_no, created_at, reason FROM zimrx_visit_revisions WHERE visit_record_id = :vid ORDER BY revision_no DESC");
            $stmtRevs->execute(['vid' => $visitRecordId]);
            $revisions = $stmtRevs->fetchAll(PDO::FETCH_ASSOC);
        }

        emr_json_response([
            'success' => true,
            'visit' => $visit,
            'prescription' => $prescription,
            'drugs' => $drugs,
            'vitals' => $vitals,
            'revisions' => $revisions
        ]);
        break;

    case 'get_visit_revision_detail':
        $revisionId = (int)($_GET['revision_id'] ?? 0);
        if ($revisionId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Missing revision ID'], 400);
        }
        $stmtRev = $pdo->prepare("SELECT * FROM zimrx_visit_revisions WHERE id = :id LIMIT 1");
        $stmtRev->execute(['id' => $revisionId]);
        $revision = $stmtRev->fetch(PDO::FETCH_ASSOC);
        if (!$revision) {
            emr_json_response(['success' => false, 'message' => 'Revision not found'], 404);
        }
        emr_json_response([
            'success' => true,
            'revision' => $revision
        ]);
        break;

    case 'start_new_visit':
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $regNo = trim((string)($_POST['reg_no'] ?? ''));

        if ($patientId <= 0 && $regNo !== '') {
            $stmtP = $pdo->prepare("SELECT id FROM zimrx_patients WHERE reg_no = :reg LIMIT 1");
            $stmtP->execute(['reg' => $regNo]);
            $patientId = (int)$stmtP->fetchColumn();
        }

        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient for new visit'], 400);
        }

        // Get next visit no
        $stmtNext = $pdo->prepare("SELECT COALESCE(MAX(visit_no), 0) + 1 FROM zimrx_visits WHERE patient_id = :pid AND doctor_id = :did");
        $stmtNext->execute(['pid' => $patientId, 'did' => $currentDoctorId]);
        $nextVisitNo = max(1, (int)$stmtNext->fetchColumn());

        // Generate visit ID
        $newVisitId = zimrx_generate_visit_id($pdo);

        // Create visit entry
        $stmtInsert = $pdo->prepare(
            "INSERT INTO zimrx_visits (patient_id, doctor_id, visit_no, visit_id, visit_date, created_at, updated_at)
             VALUES (:patient_id, :doctor_id, :visit_no, :visit_id, CURRENT_DATE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $stmtInsert->execute([
            'patient_id' => $patientId,
            'doctor_id' => $currentDoctorId,
            'visit_no' => $nextVisitNo,
            'visit_id' => $newVisitId
        ]);
        $newVisitRecordId = (int)$pdo->lastInsertId();

        emr_json_response([
            'success' => true,
            'visit_record_id' => $newVisitRecordId,
            'visit_id' => $newVisitId,
            'visit_no' => $nextVisitNo
        ]);
        break;

    case 'update_patient_demographics':
        $patientId = (int)($_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient ID'], 400);
        }

        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? ''));
        $age = trim((string)($_POST['age'] ?? ''));
        $ageUnit = trim((string)($_POST['age_unit'] ?? 'Years'));
        $dob = trim((string)($_POST['dob'] ?? ''));
        $bloodGroup = trim((string)($_POST['blood_group'] ?? ''));
        $occupation = trim((string)($_POST['occupation'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($fullName === '') {
            emr_json_response(['success' => false, 'message' => 'Patient name is required'], 422);
        }

        // Fetch current row for audit trail
        $oldStmt = $pdo->prepare("SELECT * FROM zimrx_patients WHERE id = :id LIMIT 1");
        $oldStmt->execute(['id' => $patientId]);
        $oldPatient = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmtUpdate = $pdo->prepare(
            "UPDATE zimrx_patients
             SET full_name = :full_name,
                 mobile = :mobile,
                 gender = :gender,
                 age = :age,
                 age_unit = :age_unit,
                 dob = :dob,
                 blood_group = :blood_group,
                 occupation = :occupation,
                 address = :address,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmtUpdate->execute([
            'full_name' => $fullName,
            'mobile' => $mobile,
            'gender' => $gender,
            'age' => $age,
            'age_unit' => $ageUnit,
            'dob' => $dob,
            'blood_group' => $bloodGroup,
            'occupation' => $occupation,
            'address' => $address,
            'id' => $patientId
        ]);

        log_patient_particulars_audit($pdo, $patientId, (string)($oldPatient['reg_no'] ?? ''), $oldPatient, $_POST, 'emr');
        if ($occupation !== '') {
            $emrDoctorId = function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1;
            zimrx_record_user_occupation($pdo, $emrDoctorId, $occupation);
        }

        emr_json_response(['success' => true, 'message' => 'Patient particulars updated successfully']);
        break;

    case 'get_particulars_audit_log':
        $patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient ID'], 400);
        }
        $history = get_patient_particulars_audit_history($pdo, $patientId);
        emr_json_response(['success' => true, 'history' => $history]);
        break;

    case 'get_patient_trajectories':
        ensure_trajectories_schema($pdo);
        $patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient ID'], 400);
        }

        // Get patient tracked metrics list
        $stmtP = $pdo->prepare("SELECT tracked_metrics_json FROM zimrx_patients WHERE id = :id LIMIT 1");
        $stmtP->execute(['id' => $patientId]);
        $jsonStr = $stmtP->fetchColumn();
        $tracked = json_decode((string)$jsonStr, true);
        if (!is_array($tracked) || empty($tracked)) {
            $tracked = ['weight'];
        }

        $series = [
            // 1. Daily / General Vitals
            'weight' => [],
            'bp' => [],
            'pulse' => [],
            'spo2' => [],
            'temp' => [],
            'rr' => [],

            // 2. Metabolic & Chronic Disease
            'glucose' => [],
            'hba1c' => [],
            'creatinine' => [],
            'egfr' => [],
            'ldl' => [],
            'triglycerides' => [],
            'tsh' => [],
            'uric_acid' => [],

            // 3. Pediatric & Growth
            'height' => [],
            'ofc' => [],
            'muac' => [],

            // 4. Special Clinical Curves
            'platelets' => [],
            'hb' => [],
            'crp' => [],
            'esr' => [],
            'sfh' => []
        ];

        // 1. Fetch consultation visits for clinic points
        $stmtV = $pdo->prepare(
            "SELECT id, visit_id, visit_no, visit_date, weight_at_visit, weight_unit_at_visit, height_at_visit, height_unit_at_visit, metrics_json, clinical_snapshot_json
             FROM zimrx_visits
             WHERE patient_id = :pid
             ORDER BY visit_date ASC, id ASC"
        );
        $stmtV->execute(['pid' => $patientId]);
        $visits = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        foreach ($visits as $v) {
            $date = substr((string)$v['visit_date'], 0, 10);
            $visitLabel = $v['visit_id'] ?: ('V' . $v['visit_no']);

            // Weight
            if (!empty($v['weight_at_visit']) && is_numeric($v['weight_at_visit'])) {
                $series['weight'][] = [
                    'id' => 'visit_' . $v['id'],
                    'date' => $date,
                    'time' => '',
                    'value' => (float)$v['weight_at_visit'],
                    'unit' => $v['weight_unit_at_visit'] ?: 'kg',
                    'source' => 'clinic',
                    'label' => "Clinic Visit #{$v['visit_no']} ($visitLabel)",
                    'notes' => ''
                ];
            }

            // Height
            if (!empty($v['height_at_visit']) && is_numeric($v['height_at_visit'])) {
                $series['height'][] = [
                    'id' => 'visit_' . $v['id'],
                    'date' => $date,
                    'time' => '',
                    'value' => (float)$v['height_at_visit'],
                    'unit' => $v['height_unit_at_visit'] ?: 'inch',
                    'source' => 'clinic',
                    'label' => "Clinic Visit #{$v['visit_no']}",
                    'notes' => ''
                ];
            }

            // Metrics from JSON (bp, pulse, spo2, temp, rr, etc.)
            $metrics = json_decode((string)$v['metrics_json'], true) ?: [];
            if (!empty($metrics['bp']) && strpos((string)$metrics['bp'], '/') !== false) {
                $parts = explode('/', (string)$metrics['bp']);
                $sys = (float)trim($parts[0]);
                $dia = (float)trim($parts[1]);
                if ($sys > 0) {
                    $series['bp'][] = [
                        'id' => 'visit_' . $v['id'],
                        'date' => $date,
                        'time' => '',
                        'sys' => $sys,
                        'dia' => $dia,
                        'value' => "{$sys}/{$dia}",
                        'source' => 'clinic',
                        'label' => "Clinic Visit #{$v['visit_no']} ($visitLabel)",
                        'notes' => ''
                    ];
                }
            }

            $vitalsMap = [
                'pulse' => ['key' => 'pulse', 'unit' => 'bpm'],
                'spo2' => ['key' => 'spo2', 'unit' => '%'],
                'temp' => ['key' => 'temp', 'unit' => '°F'],
                'rr' => ['key' => 'rr', 'unit' => '/min'],
                'glucose' => ['key' => 'glucose', 'unit' => 'mmol/L'],
                'hba1c' => ['key' => 'hba1c', 'unit' => '%'],
                'creatinine' => ['key' => 'creatinine', 'unit' => 'mg/dL'],
                'egfr' => ['key' => 'egfr', 'unit' => 'mL/min'],
                'ldl' => ['key' => 'ldl', 'unit' => 'mg/dL'],
                'triglycerides' => ['key' => 'triglycerides', 'unit' => 'mg/dL'],
                'tsh' => ['key' => 'tsh', 'unit' => 'µIU/mL'],
                'uric_acid' => ['key' => 'uric_acid', 'unit' => 'mg/dL'],
                'ofc' => ['key' => 'ofc', 'unit' => 'cm'],
                'muac' => ['key' => 'muac', 'unit' => 'cm'],
                'platelets' => ['key' => 'platelets', 'unit' => 'k/µL'],
                'hb' => ['key' => 'hb', 'unit' => 'g/dL'],
                'crp' => ['key' => 'crp', 'unit' => 'mg/L'],
                'esr' => ['key' => 'esr', 'unit' => 'mm/h'],
                'sfh' => ['key' => 'sfh', 'unit' => 'cm']
            ];

            foreach ($vitalsMap as $mType => $cfg) {
                $val = $metrics[$cfg['key']] ?? null;
                if ($val !== null && is_numeric($val)) {
                    $series[$mType][] = [
                        'id' => 'visit_' . $v['id'],
                        'date' => $date,
                        'time' => '',
                        'value' => (float)$val,
                        'unit' => $cfg['unit'],
                        'source' => 'clinic',
                        'label' => "Clinic Visit #{$v['visit_no']}",
                        'notes' => ''
                    ];
                }
            }
        }

        // 2. Fetch logged readings (home logbooks, standalone clinic checks)
        $stmtR = $pdo->prepare(
            "SELECT id, metric_type, reading_value, secondary_value, reading_date, reading_time, source, notes
             FROM zimrx_patient_metric_readings
             WHERE patient_id = :pid
             ORDER BY reading_date ASC, reading_time ASC, id ASC"
        );
        $stmtR->execute(['pid' => $patientId]);
        $readings = $stmtR->fetchAll(PDO::FETCH_ASSOC);

        foreach ($readings as $r) {
            $mType = (string)$r['metric_type'];
            if (!isset($series[$mType])) {
                $series[$mType] = [];
            }
            $val = $r['reading_value'];
            $sec = $r['secondary_value'];
            $src = $r['source'] ?: 'home';

            if ($mType === 'bp') {
                $sys = 0; $dia = 0;
                if (strpos($val, '/') !== false) {
                    $parts = explode('/', $val);
                    $sys = (float)trim($parts[0]);
                    $dia = (float)trim($parts[1]);
                } elseif (!empty($sec)) {
                    $sys = (float)$val;
                    $dia = (float)$sec;
                }
                $series['bp'][] = [
                    'id' => (int)$r['id'],
                    'date' => $r['reading_date'],
                    'time' => $r['reading_time'] ?: '',
                    'sys' => $sys,
                    'dia' => $dia,
                    'value' => "{$sys}/{$dia}",
                    'source' => $src,
                    'label' => ($src === 'home' ? 'Home Logbook' : 'Clinic Desk'),
                    'notes' => $r['notes'] ?: ''
                ];
            } else {
                $series[$mType][] = [
                    'id' => (int)$r['id'],
                    'date' => $r['reading_date'],
                    'time' => $r['reading_time'] ?: '',
                    'value' => is_numeric($val) ? (float)$val : $val,
                    'secondary_value' => $sec,
                    'source' => $src,
                    'label' => ($src === 'home' ? 'Home Logbook' : 'Clinic Desk'),
                    'notes' => $r['notes'] ?: ''
                ];
            }
        }

        // Sort each series chronologically by date
        foreach ($series as $mType => &$list) {
            usort($list, function($a, $b) {
                $cmp = strcmp($a['date'], $b['date']);
                if ($cmp === 0) {
                    return strcmp($a['time'] ?? '', $b['time'] ?? '');
                }
                return $cmp;
            });
        }
        unset($list);

        emr_json_response([
            'success' => true,
            'tracked_metrics' => $tracked,
            'series' => $series
        ]);
        break;

    case 'save_metric_reading':
        ensure_trajectories_schema($pdo);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $metricType = trim((string)($_POST['metric_type'] ?? ''));
        $readingValue = trim((string)($_POST['reading_value'] ?? ''));
        $secondaryValue = trim((string)($_POST['secondary_value'] ?? ''));
        $readingDate = trim((string)($_POST['reading_date'] ?? ''));
        $readingTime = trim((string)($_POST['reading_time'] ?? ''));
        $source = trim((string)($_POST['source'] ?? 'home'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($patientId <= 0 || $metricType === '' || $readingValue === '') {
            emr_json_response(['success' => false, 'message' => 'Patient, metric type, and reading value are required'], 400);
        }
        if ($readingDate === '') {
            $readingDate = date('Y-m-d');
        }

        $stmtIns = $pdo->prepare(
            "INSERT INTO zimrx_patient_metric_readings (
                patient_id, metric_type, reading_value, secondary_value,
                reading_date, reading_time, source, notes, created_by
            ) VALUES (
                :pid, :mtype, :rval, :sval, :rdate, :rtime, :source, :notes, :uid
            )"
        );
        $stmtIns->execute([
            'pid' => $patientId,
            'mtype' => $metricType,
            'rval' => $readingValue,
            'sval' => $secondaryValue,
            'rdate' => $readingDate,
            'rtime' => $readingTime,
            'source' => $source,
            'notes' => $notes,
            'uid' => current_user_id()
        ]);

        // Auto-ensure metricType is added to tracked_metrics_json
        $stmtP = $pdo->prepare("SELECT tracked_metrics_json FROM zimrx_patients WHERE id = :id LIMIT 1");
        $stmtP->execute(['id' => $patientId]);
        $tracked = json_decode((string)$stmtP->fetchColumn(), true);
        if (!is_array($tracked)) $tracked = ['weight'];
        if (!in_array($metricType, $tracked, true)) {
            $tracked[] = $metricType;
            $stmtUp = $pdo->prepare("UPDATE zimrx_patients SET tracked_metrics_json = :tm WHERE id = :id");
            $stmtUp->execute(['tm' => json_encode($tracked), 'id' => $patientId]);
        }

        emr_json_response(['success' => true, 'message' => 'Reading logged successfully']);
        break;

    case 'delete_metric_reading':
        ensure_trajectories_schema($pdo);
        $readingId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
        if ($readingId <= 0 || $patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid reading ID or patient ID'], 400);
        }

        $stmtDel = $pdo->prepare("DELETE FROM zimrx_patient_metric_readings WHERE id = :id AND patient_id = :pid");
        $stmtDel->execute(['id' => $readingId, 'pid' => $patientId]);
        emr_json_response(['success' => true, 'message' => 'Reading deleted successfully']);
        break;

    case 'update_tracked_metrics':
        ensure_trajectories_schema($pdo);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient ID'], 400);
        }
        $metrics = $_POST['metrics'] ?? [];
        if (is_string($metrics)) {
            $metrics = json_decode($metrics, true) ?: [];
        }
        if (!is_array($metrics)) {
            $metrics = ['weight'];
        }

        $stmtUp = $pdo->prepare("UPDATE zimrx_patients SET tracked_metrics_json = :tm WHERE id = :id");
        $stmtUp->execute(['tm' => json_encode(array_values($metrics)), 'id' => $patientId]);
        emr_json_response(['success' => true, 'message' => 'Tracked trajectories updated']);
        break;

    default:
        emr_json_response(['success' => false, 'message' => 'Invalid or missing action parameter'], 400);
        break;
}

function ensure_trajectories_schema(PDO $pdo): void {
    if (!DbSchema::columnExists($pdo, 'zimrx_patients', 'tracked_metrics_json')) {
        $pdo->exec("ALTER TABLE zimrx_patients ADD COLUMN tracked_metrics_json TEXT DEFAULT '[\"weight\"]'");
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_patient_metric_readings (
            id " . DbSql::autoIncrement() . ",
            patient_id INTEGER NOT NULL,
            metric_type TEXT NOT NULL,
            reading_value TEXT NOT NULL,
            secondary_value TEXT,
            reading_date TEXT NOT NULL,
            reading_time TEXT,
            source TEXT NOT NULL DEFAULT 'clinic',
            notes TEXT,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_metric_pt ON zimrx_patient_metric_readings(patient_id, metric_type, reading_date)");
}
