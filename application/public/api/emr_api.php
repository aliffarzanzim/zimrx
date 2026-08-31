<?php
declare(strict_types=1);

// EMR API endpoint: patient record search, visit history timeline, vitals trend graphing, and sync journal tombstones.

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/PatientCustomFields.php';
require_once ZIMRX_BASE_DIR . '/lib/emr_identity_lib.php';
require_once ZIMRX_BASE_DIR . '/lib/particulars_audit_lib.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/SyncJournalService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';

header('Content-Type: application/json; charset=utf-8');

function emr_json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function emr_enforce_mutation(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        emr_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!zimrx_verify_csrf()) {
        emr_json_response(['success' => false, 'message' => 'CSRF verification failed'], 403);
    }
}

try {
    $pdo = DbConnections::userdata();
    $pdo->exec('PRAGMA busy_timeout = 5000');
} catch (Throwable $e) {
    error_log('[ZimRx] emr_api DB connection error: ' . $e->getMessage());
    emr_json_response(['success' => false, 'message' => 'Database connection failed. Please try again.'], 500);
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));
$userRole = current_user_role();
$activePracticeProfile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
$currentProfileId = (int)($activePracticeProfile['id'] ?? 0);
$currentDoctorId = (int)($activePracticeProfile['doctor_id'] ?? 0);
if ($currentDoctorId <= 0 || $currentProfileId <= 0) {
    emr_json_response(['success' => false, 'message' => 'Doctor scope is required.'], 403);
}

function emr_profile_scope_sql(string $alias, string $sharingColumn, string $prefix = 'profile'): string
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $alias)
        || !preg_match('/^[a-z][a-z0-9_]*$/i', $prefix)
        || !in_array($sharingColumn, ['share_patient_particulars', 'share_emr', 'share_prescriptions'], true)) {
        throw new InvalidArgumentException('Invalid EMR profile scope.');
    }

    return "($alias.profile_id = :{$prefix}_active_profile_id OR EXISTS (
        SELECT 1 FROM zimrx_doctor_profiles shared_profile
        WHERE shared_profile.id = $alias.profile_id
          AND shared_profile.doctor_id = :{$prefix}_doctor_id
          AND shared_profile.is_active = 1
          AND shared_profile.is_deleted = 0
          AND shared_profile.$sharingColumn = 1
    ))";
}

function emr_profile_scope_params(int $profileId, int $doctorId, string $prefix = 'profile'): array
{
    return [
        $prefix . '_active_profile_id' => $profileId,
        $prefix . '_doctor_id' => $doctorId,
    ];
}

function emr_require_patient(PDO $pdo, int $patientId, int $doctorId): array
{
    $profileId = (int)($GLOBALS['currentProfileId'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT p.*
         FROM zimrx_patients p
         WHERE p.id = :id AND p.doctor_id = :doctor_id
           AND ' . emr_profile_scope_sql('p', 'share_patient_particulars', 'patient') . '
         LIMIT 1'
    );
    $stmt->execute(array_merge(
        ['id' => $patientId, 'doctor_id' => $doctorId],
        emr_profile_scope_params($profileId, $doctorId, 'patient')
    ));
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient === false) {
        emr_json_response(['success' => false, 'message' => 'Patient not found.'], 404);
    }

    return $patient;
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

        // Search patients scoped to doctor
        $stmtP = $pdo->prepare(
            "SELECT id, reg_no, full_name, mobile, gender, age, age_unit, blood_group, address
             FROM zimrx_patients
             WHERE doctor_id = :doctor_id
               AND " . emr_profile_scope_sql('zimrx_patients', 'share_patient_particulars', 'patient') . "
               AND (reg_no LIKE :q_exact OR full_name LIKE :q_name OR mobile LIKE :q_mobile)
             ORDER BY id DESC LIMIT 20"
        );
        $stmtP->execute([
            'doctor_id' => $currentDoctorId,
            'patient_active_profile_id' => $currentProfileId,
            'patient_doctor_id' => $currentDoctorId,
            'q_exact' => $q,
            'q_name' => '%' . $q . '%',
            'q_mobile' => '%' . $q . '%'
        ]);
        $patients = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        // Search visits scoped to doctor
        $stmtV = $pdo->prepare(
            "SELECT v.id, v.visit_id, v.visit_no, v.visit_date, v.patient_id, p.full_name as patient_name, p.reg_no, p.mobile
             FROM zimrx_visits v
             INNER JOIN zimrx_patients p ON v.patient_id = p.id AND p.doctor_id = :patient_owner_doctor_id
             WHERE v.doctor_id = :doctor_id
               AND " . emr_profile_scope_sql('v', 'share_prescriptions', 'visit') . "
               AND " . emr_profile_scope_sql('p', 'share_patient_particulars', 'patient') . "
               AND (v.visit_id LIKE :visit_query OR p.reg_no LIKE :patient_reg_query OR p.full_name LIKE :patient_name_query)
             ORDER BY v.id DESC LIMIT 15"
        );
        $stmtV->execute([
            'doctor_id' => $currentDoctorId,
            'patient_owner_doctor_id' => $currentDoctorId,
            'visit_active_profile_id' => $currentProfileId,
            'visit_doctor_id' => $currentDoctorId,
            'patient_active_profile_id' => $currentProfileId,
            'patient_doctor_id' => $currentDoctorId,
            'visit_query' => '%' . $q . '%',
            'patient_reg_query' => '%' . $q . '%',
            'patient_name_query' => '%' . $q . '%'
        ]);
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
            $patient = emr_require_patient($pdo, $patientId, $currentDoctorId);
        } else {
            $stmt = $pdo->prepare(
                'SELECT p.* FROM zimrx_patients p
                 WHERE p.reg_no = :reg AND p.doctor_id = :doctor_id
                   AND ' . emr_profile_scope_sql('p', 'share_patient_particulars', 'patient') . '
                 LIMIT 1'
            );
            $stmt->execute(array_merge(
                ['reg' => $regNo, 'doctor_id' => $currentDoctorId],
                emr_profile_scope_params($currentProfileId, $currentDoctorId, 'patient')
            ));
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$patient) {
                emr_json_response(['success' => false, 'message' => 'Patient not found'], 404);
            }
        }

        $patientId = (int)$patient['id'];
        $rawExtraDemo = json_decode((string)($patient['extra_demographics_json'] ?? '{}'), true);
        $patient['extra_demographics_json'] = json_encode(
            \ZimRx\Services\PatientCustomFields::projectForDoctor(
                is_array($rawExtraDemo) ? $rawExtraDemo : [],
                $currentDoctorId
            ),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        // Get current-profile history and histories explicitly shared by their source profiles.
        $stmtVisits = $pdo->prepare(
            "SELECT v.id as visit_record_id, v.visit_id, v.visit_no, v.visit_date, v.next_visit,
                    v.age_at_visit, v.weight_at_visit, v.weight_unit_at_visit, v.height_at_visit, v.height_unit_at_visit,
                    v.metrics_json, v.clinical_snapshot_json,
                    v.id as prescription_id, v.created_at as rx_created_at
             FROM zimrx_visits v
             WHERE v.patient_id = :patient_id AND v.doctor_id = :doctor_id
               AND " . emr_profile_scope_sql('v', 'share_emr', 'visit') . "
             ORDER BY v.visit_date DESC, v.id DESC"
        );
        $stmtVisits->execute(array_merge(
            ['patient_id' => $patientId, 'doctor_id' => $currentDoctorId],
            emr_profile_scope_params($currentProfileId, $currentDoctorId, 'visit')
        ));
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

        // Allergies extraction scoped to doctor
        $allergies = [];
        $stmtAllergies = $pdo->prepare(
            "SELECT DISTINCT pd.generic_name
             FROM zimrx_prescription_drugs pd
             INNER JOIN zimrx_visits v ON pd.visit_id = v.id AND v.doctor_id = :doctor_id
               AND " . emr_profile_scope_sql('v', 'share_prescriptions') . "
             WHERE pd.patient_id = :patient_id AND pd.is_history = 1"
        );
        $stmtAllergies->execute(array_merge(
            ['patient_id' => $patientId, 'doctor_id' => $currentDoctorId],
            emr_profile_scope_params($currentProfileId, $currentDoctorId)
        ));
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

        $visitIdentifier = $visitRecordId > 0 ? 'v.id = :visit_identifier' : 'v.visit_id = :visit_identifier';
        $stmtV = $pdo->prepare(
            'SELECT v.* FROM zimrx_visits v
             WHERE ' . $visitIdentifier . ' AND v.doctor_id = :doctor_id
               AND ' . emr_profile_scope_sql('v', 'share_prescriptions') . '
             LIMIT 1'
        );
        $stmtV->execute(array_merge(
            ['visit_identifier' => $visitRecordId > 0 ? $visitRecordId : $visitId, 'doctor_id' => $currentDoctorId],
            emr_profile_scope_params($currentProfileId, $currentDoctorId)
        ));
        $visit = $stmtV->fetch(PDO::FETCH_ASSOC);

        if (!$visit) {
            emr_json_response(['success' => false, 'message' => 'Visit record not found'], 404);
        }

        $visitRecordId = (int)$visit['id'];

        // Get prescription snapshot from visit row
        $snapJson = (string)($visit['clinical_snapshot_json'] ?? '');
        $snapArr = !empty($snapJson) ? json_decode($snapJson, true) : [];
        $modArr = is_array($snapArr) ? ($snapArr['modules'] ?? $snapArr['clinical'] ?? $snapArr) : [];

        $prescription = [
            'prescription_html' => (string)($visit['prescription_html'] ?? ''),
            'clinical_snapshot_json' => $snapJson,
            'module_json' => json_encode($modArr, JSON_UNESCAPED_UNICODE),
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
            $stmtRevs = $pdo->prepare(
                "SELECT r.id, r.revision_no, r.created_at, r.reason
                 FROM zimrx_visit_revisions r
                 INNER JOIN zimrx_visits v ON r.visit_record_id = v.id AND v.doctor_id = :doctor_id
                 WHERE r.visit_record_id = :vid
                   AND " . emr_profile_scope_sql('v', 'share_prescriptions') . "
                 ORDER BY r.revision_no DESC"
            );
            $stmtRevs->execute(array_merge(
                ['vid' => $visitRecordId, 'doctor_id' => $currentDoctorId],
                emr_profile_scope_params($currentProfileId, $currentDoctorId)
            ));
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
        $stmtRev = $pdo->prepare(
            "SELECT r.*
             FROM zimrx_visit_revisions r
             INNER JOIN zimrx_visits v ON r.visit_record_id = v.id AND v.doctor_id = :doctor_id
             WHERE r.id = :id
               AND " . emr_profile_scope_sql('v', 'share_prescriptions') . "
             LIMIT 1"
        );
        $stmtRev->execute(array_merge(
            ['id' => $revisionId, 'doctor_id' => $currentDoctorId],
            emr_profile_scope_params($currentProfileId, $currentDoctorId)
        ));
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
        emr_enforce_mutation();
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $regNo = trim((string)($_POST['reg_no'] ?? ''));

        if ($patientId <= 0 && $regNo !== '') {
            $stmtP = $pdo->prepare(
                'SELECT p.id FROM zimrx_patients p
                 WHERE p.reg_no = :reg AND p.doctor_id = :doctor_id
                   AND ' . emr_profile_scope_sql('p', 'share_patient_particulars') . '
                 LIMIT 1'
            );
            $stmtP->execute(array_merge(
                ['reg' => $regNo, 'doctor_id' => $currentDoctorId],
                emr_profile_scope_params($currentProfileId, $currentDoctorId)
            ));
            $patientId = (int)$stmtP->fetchColumn();
        }

        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient for new visit'], 400);
        }

        emr_require_patient($pdo, $patientId, $currentDoctorId);

        $pdo->beginTransaction();
        try {
            // Get next visit no atomically inside immediate transaction
            $stmtNext = $pdo->prepare(
                "SELECT COALESCE(MAX(visit_no), 0) + 1
                 FROM zimrx_visits
                 WHERE patient_id = :pid AND doctor_id = :did AND profile_id = :profile_id"
            );
            $stmtNext->execute(['pid' => $patientId, 'did' => $currentDoctorId, 'profile_id' => $currentProfileId]);
            $nextVisitNo = max(1, (int)$stmtNext->fetchColumn());

            // Generate visit ID
            $newVisitId = zimrx_generate_visit_id($pdo);

            // Create visit entry
            $stmtInsert = $pdo->prepare(
                "INSERT INTO zimrx_visits (patient_id, doctor_id, profile_id, visit_no, visit_id, visit_date, revision, created_at, updated_at)
                 VALUES (:patient_id, :doctor_id, :profile_id, :visit_no, :visit_id, CURRENT_DATE, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $stmtInsert->execute([
                'patient_id' => $patientId,
                'doctor_id' => $currentDoctorId,
                'profile_id' => $currentProfileId,
                'visit_no' => $nextVisitNo,
                'visit_id' => $newVisitId
            ]);
            $newVisitRecordId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        emr_json_response([
            'success' => true,
            'visit_record_id' => $newVisitRecordId,
            'visit_id' => $newVisitId,
            'visit_no' => $nextVisitNo
        ]);
        break;

    case 'update_patient_demographics':
        emr_enforce_mutation();
        $patientId = (int)($_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient ID'], 400);
        }

        $oldPatient = emr_require_patient($pdo, $patientId, $currentDoctorId);
        $optionalText = static function (string $key, string $fallback): string {
            if (!array_key_exists($key, $_POST)) {
                return $fallback;
            }
            if (!is_string($_POST[$key])) {
                emr_json_response(['success' => false, 'message' => 'Invalid patient field value'], 422);
            }
            return trim($_POST[$key]);
        };

        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? ''));
        $age = trim((string)($_POST['age'] ?? ''));
        $ageUnit = trim((string)($_POST['age_unit'] ?? 'Years'));
        $dob = trim((string)($_POST['dob'] ?? ''));
        $dobType = $optionalText('dob_type', (string)($oldPatient['dob_type'] ?? 'exact')) ?: 'exact';
        if (!in_array($dobType, \ZimRx\Services\PatientDemographics::dobTypes(), true)) {
            emr_json_response(['success' => false, 'message' => 'Invalid date of birth type'], 422);
        }
        $bloodGroup = trim((string)($_POST['blood_group'] ?? ''));
        $maritalStatus = $optionalText('marital_status', (string)($oldPatient['marital_status'] ?? ''));
        $email = $optionalText('email', (string)($oldPatient['email'] ?? ''));
        $nidNo = $optionalText('nid_no', (string)($oldPatient['nid_no'] ?? ''));
        $passportNo = $optionalText('passport_no', (string)($oldPatient['passport_no'] ?? ''));
        $occupation = trim((string)($_POST['occupation'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if (array_key_exists('extra_demographics', $_POST)) {
            if (!is_array($_POST['extra_demographics'])) {
                emr_json_response(['success' => false, 'message' => 'Invalid extended demographics'], 422);
            }
            $extraDemo = $_POST['extra_demographics'];
        } else {
            $decodedExtraDemo = json_decode((string)($oldPatient['extra_demographics_json'] ?? '{}'), true);
            $extraDemo = is_array($decodedExtraDemo) ? $decodedExtraDemo : [];
        }
        $decodedOldExtra = json_decode((string)($oldPatient['extra_demographics_json'] ?? '{}'), true);
        try {
            $customDefinitions = \ZimRx\Services\PatientCustomFields::loadForDoctor($pdo, $currentDoctorId);
            $extraDemo = \ZimRx\Services\PatientCustomFields::normalizeExtraValues(
                $extraDemo,
                $customDefinitions,
                is_array($decodedOldExtra) ? $decodedOldExtra : [],
                $currentDoctorId
            );
            $extraDemoJson = json_encode($extraDemo, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\InvalidArgumentException $e) {
            emr_json_response(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\JsonException) {
            emr_json_response(['success' => false, 'message' => 'Invalid extended demographics'], 422);
        }

        if ($fullName === '') {
            emr_json_response(['success' => false, 'message' => 'Patient name is required'], 422);
        }

        $stmtUpdate = $pdo->prepare(
            "UPDATE zimrx_patients
             SET full_name = :full_name,
                 mobile = :mobile,
                 email = :email,
                 gender = :gender,
                 age = :age,
                 age_unit = :age_unit,
                 dob = :dob,
                 dob_type = :dob_type,
                 blood_group = :blood_group,
                 marital_status = :marital_status,
                 nid_no = :nid_no,
                 passport_no = :passport_no,
                 occupation = :occupation,
                 address = :address,
                 extra_demographics_json = :extra_demographics_json,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND doctor_id = :doctor_id
               AND (profile_id = :patient_active_profile_id OR profile_id IN (
                   SELECT source_profile.id FROM zimrx_doctor_profiles source_profile
                   WHERE source_profile.doctor_id = :patient_profile_doctor_id
                     AND source_profile.is_active = 1 AND source_profile.share_patient_particulars = 1
               ))"
        );
        $stmtUpdate->execute([
            'full_name' => $fullName,
            'mobile' => $mobile,
            'email' => $email,
            'gender' => $gender,
            'age' => $age,
            'age_unit' => $ageUnit,
            'dob' => $dob,
            'dob_type' => $dobType,
            'blood_group' => $bloodGroup,
            'marital_status' => $maritalStatus,
            'nid_no' => $nidNo,
            'passport_no' => $passportNo,
            'occupation' => $occupation,
            'address' => $address,
            'extra_demographics_json' => $extraDemoJson,
            'id' => $patientId,
            'doctor_id' => $currentDoctorId,
            'patient_active_profile_id' => $currentProfileId,
            'patient_profile_doctor_id' => $currentDoctorId
        ]);

        log_patient_particulars_audit($pdo, $patientId, (string)($oldPatient['reg_no'] ?? ''), $oldPatient, $_POST, 'emr');
        if ($occupation !== '') {
            zimrx_record_user_occupation($pdo, $currentDoctorId, $occupation);
        }

        emr_json_response(['success' => true, 'message' => 'Patient particulars updated successfully']);
        break;

    case 'get_particulars_audit_log':
        $patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient ID'], 400);
        }
        emr_require_patient($pdo, $patientId, $currentDoctorId);
        $history = get_patient_particulars_audit_history($pdo, $patientId);
        emr_json_response(['success' => true, 'history' => $history]);
        break;

    case 'get_patient_trajectories':
        $patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient ID'], 400);
        }

        $patient = emr_require_patient($pdo, $patientId, $currentDoctorId);

        // Get patient tracked metrics list
        $stmtP = $pdo->prepare(
            'SELECT p.tracked_metrics_json FROM zimrx_patients p
             WHERE p.id = :id AND p.doctor_id = :did
               AND ' . emr_profile_scope_sql('p', 'share_patient_particulars') . ' LIMIT 1'
        );
        $stmtP->execute(array_merge(
            ['id' => $patientId, 'did' => $currentDoctorId],
            emr_profile_scope_params($currentProfileId, $currentDoctorId)
        ));
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

        // 1. Fetch consultation visits for clinic points scoped to doctor
        $stmtV = $pdo->prepare(
            "SELECT id, visit_id, visit_no, visit_date, weight_at_visit, weight_unit_at_visit, height_at_visit, height_unit_at_visit, metrics_json, clinical_snapshot_json
             FROM zimrx_visits
             WHERE patient_id = :pid AND doctor_id = :did
               AND " . emr_profile_scope_sql('zimrx_visits', 'share_emr') . "
             ORDER BY visit_date ASC, id ASC"
        );
        $stmtV->execute(array_merge(
            ['pid' => $patientId, 'did' => $currentDoctorId],
            emr_profile_scope_params($currentProfileId, $currentDoctorId)
        ));
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

        // 2. Fetch logged readings scoped to doctor
        $stmtR = $pdo->prepare(
            "SELECT r.id, r.metric_type, r.reading_value, r.secondary_value, r.reading_date, r.reading_time, r.source, r.notes
             FROM zimrx_patient_metric_readings r
             WHERE r.patient_id = :reading_patient_id
               AND r.deleted_at IS NULL
               AND " . emr_profile_scope_sql('r', 'share_emr') . "
               AND EXISTS (
                   SELECT 1 FROM zimrx_patients p WHERE p.id = :patient_identity_id AND p.doctor_id = :did
                     AND " . emr_profile_scope_sql('p', 'share_patient_particulars', 'patient') . "
               )
             ORDER BY r.reading_date ASC, r.reading_time ASC, r.id ASC"
        );
        $stmtR->execute(array_merge(
            ['reading_patient_id' => $patientId, 'patient_identity_id' => $patientId, 'did' => $currentDoctorId],
            emr_profile_scope_params($currentProfileId, $currentDoctorId),
            emr_profile_scope_params($currentProfileId, $currentDoctorId, 'patient')
        ));
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
        emr_enforce_mutation();
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
        emr_require_patient($pdo, $patientId, $currentDoctorId);

        $allowedMetrics = [
            'weight', 'height', 'bp', 'pulse', 'spo2', 'temp', 'rr',
            'glucose', 'hba1c', 'creatinine', 'egfr', 'ldl',
            'triglycerides', 'tsh', 'uric_acid', 'ofc', 'muac',
            'platelets', 'hb', 'crp', 'esr', 'sfh',
        ];

        if (!in_array($metricType, $allowedMetrics, true)) {
            emr_json_response(['success' => false, 'message' => 'Unsupported metric type.'], 422);
        }

        if ($readingDate === '') {
            $readingDate = date('Y-m-d');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $readingDate);
        if ($date === false || $date->format('Y-m-d') !== $readingDate) {
            emr_json_response(['success' => false, 'message' => 'Invalid reading date.'], 422);
        }

        if ($metricType === 'bp') {
            if (!preg_match('/^([1-9]\d{1,2})\/([1-9]\d{1,2})$/', $readingValue)) {
                emr_json_response(['success' => false, 'message' => 'Blood pressure must use systolic/diastolic form.'], 422);
            }
        } elseif (!is_numeric($readingValue) || !is_finite((float)$readingValue)) {
            emr_json_response(['success' => false, 'message' => 'Metric value must be numeric.'], 422);
        }

        $pdo->beginTransaction();

        try {
            $stmtIns = $pdo->prepare(
                "INSERT INTO zimrx_patient_metric_readings (
                    patient_id, profile_id, metric_type, reading_value, secondary_value,
                    reading_date, reading_time, source, notes, created_by
                ) VALUES (
                    :pid, :profile_id, :mtype, :rval, :sval, :rdate, :rtime, :source, :notes, :uid
                )"
            );
            $stmtIns->execute([
                'pid' => $patientId,
                'profile_id' => $currentProfileId,
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
            $stmtP = $pdo->prepare(
                'SELECT p.tracked_metrics_json FROM zimrx_patients p
                 WHERE p.id = :id AND p.doctor_id = :did
                   AND ' . emr_profile_scope_sql('p', 'share_patient_particulars') . ' LIMIT 1'
            );
            $patientScope = emr_profile_scope_params($currentProfileId, $currentDoctorId);
            $stmtP->execute(array_merge(['id' => $patientId, 'did' => $currentDoctorId], $patientScope));
            $tracked = json_decode((string)$stmtP->fetchColumn(), true);
            if (!is_array($tracked)) $tracked = ['weight'];
            if (!in_array($metricType, $tracked, true)) {
                $tracked[] = $metricType;
                $stmtUp = $pdo->prepare(
                    'UPDATE zimrx_patients SET tracked_metrics_json = :tm, updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND doctor_id = :did
                       AND ' . emr_profile_scope_sql('zimrx_patients', 'share_patient_particulars')
                );
                $stmtUp->execute(array_merge(
                    ['tm' => json_encode($tracked), 'id' => $patientId, 'did' => $currentDoctorId],
                    $patientScope
                ));
            }

            $readingId = (int)$pdo->lastInsertId();
            if (class_exists('SyncJournalService')) {
                SyncJournalService::recordMetricReadingChange($pdo, $readingId, $patientId, 'insert', [
                    'id' => $readingId,
                    'patient_id' => $patientId,
                    'metric_type' => $metricType,
                    'reading_value' => $readingValue,
                    'secondary_value' => $secondaryValue,
                    'reading_date' => $readingDate,
                    'reading_time' => $readingTime,
                    'source' => $source,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        emr_json_response(['success' => true, 'message' => 'Reading logged successfully']);
        break;

    case 'delete_metric_reading':
        emr_enforce_mutation();
        $readingId = (int)($_POST['id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        if ($readingId <= 0 || $patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid reading ID or patient ID'], 400);
        }
        emr_require_patient($pdo, $patientId, $currentDoctorId);

        $pdo->beginTransaction();

        try {
            // Fetch the row's current sync_id and revision to build the tombstone.
            $stmtFetch = $pdo->prepare(
                "SELECT sync_id, revision
                 FROM zimrx_patient_metric_readings
                 WHERE id = :reading_id
                   AND patient_id = :reading_patient_id
                   AND profile_id = :reading_profile_id
                   AND deleted_at IS NULL
                   AND EXISTS (
                       SELECT 1 FROM zimrx_patients p
                       WHERE p.id = :patient_identity_id AND p.doctor_id = :doctor_id
                         AND " . emr_profile_scope_sql('p', 'share_patient_particulars', 'patient') . "
                   )
                 LIMIT 1"
            );
            $stmtFetch->execute([
                'reading_id' => $readingId,
                'reading_patient_id' => $patientId,
                'patient_identity_id' => $patientId,
                'reading_profile_id' => $currentProfileId,
                'doctor_id'  => $currentDoctorId,
                'patient_active_profile_id' => $currentProfileId,
                'patient_doctor_id' => $currentDoctorId,
            ]);
            $delRow = $stmtFetch->fetch(PDO::FETCH_ASSOC);

            if (!$delRow) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                emr_json_response(['success' => false, 'message' => 'Reading not found or already deleted'], 404);
            }

            $baseRevision = (int)($delRow['revision'] ?? 1);
            $newRevision  = $baseRevision + 1;

            // Stable sync_id - assign one if the column exists but the row pre-dates migration.
            $delSyncId = (string)($delRow['sync_id'] ?? '');
            if ($delSyncId === '') {
                $delSyncId = SyncJournalService::generateUuid();
                $pdo->prepare("UPDATE zimrx_patient_metric_readings SET sync_id = :sid WHERE id = :id AND profile_id = :profile_id")
                    ->execute(['sid' => $delSyncId, 'id' => $readingId, 'profile_id' => $currentProfileId]);
            }

            // Soft-delete: mark deleted_at and advance revision.
            $pdo->prepare(
                "UPDATE zimrx_patient_metric_readings
                 SET deleted_at = CURRENT_TIMESTAMP, revision = :rev
                 WHERE id = :reading_id AND patient_id = :patient_id AND profile_id = :profile_id"
            )->execute([
                'rev'        => $newRevision,
                'reading_id' => $readingId,
                'patient_id' => $patientId,
                'profile_id' => $currentProfileId,
            ]);

            // Write tombstone to sync journal so offline peers can reconcile.
            if (class_exists('SyncJournalService') && DbSchema::tableExists($pdo, 'zimrx_sync_changes')) {
                SyncJournalService::logChange(
                    $pdo,
                    'metric_reading',
                    $delSyncId,
                    'delete',
                    $baseRevision,
                    $newRevision,
                    ['id' => $readingId, 'patient_id' => $patientId],
                    'desktop'
                );
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }

        emr_json_response(['success' => true, 'message' => 'Reading deleted successfully']);
        break;

    case 'update_tracked_metrics':
        emr_enforce_mutation();
        $patientId = (int)($_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            emr_json_response(['success' => false, 'message' => 'Invalid patient ID'], 400);
        }
        emr_require_patient($pdo, $patientId, $currentDoctorId);

        $metrics = $_POST['metrics'] ?? [];
        if (is_string($metrics)) {
            $metrics = json_decode($metrics, true) ?: [];
        }
        if (!is_array($metrics)) {
            $metrics = ['weight'];
        }

        $stmtUp = $pdo->prepare(
            'UPDATE zimrx_patients SET tracked_metrics_json = :tm, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND doctor_id = :did
               AND ' . emr_profile_scope_sql('zimrx_patients', 'share_patient_particulars')
        );
        $stmtUp->execute(array_merge(
            ['tm' => json_encode(array_values($metrics)), 'id' => $patientId, 'did' => $currentDoctorId],
            emr_profile_scope_params($currentProfileId, $currentDoctorId)
        ));
        emr_json_response(['success' => true, 'message' => 'Tracked trajectories updated']);
        break;

    default:
        emr_json_response(['success' => false, 'message' => 'Invalid or missing action parameter'], 400);
        break;
}
