<?php
declare(strict_types=1);

// EMR workspace: patient master profile, longitudinal trajectory tracking, visit timeline, and active encounter editor.

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/emr_identity_lib.php';
require_once ZIMRX_BASE_DIR . '/lib/particulars_audit_lib.php';

$page_title = 'EMR - Electronic Medical Records | ZimRx';
$current_page = 'emr.php';

try {
    global $pdo;
    $pdo = $pdo instanceof PDO ? $pdo : DbConnections::userdata();
    $pdo->exec('PRAGMA busy_timeout = 5000');
} catch (Throwable $e) {
    die('Database connection error: ' . htmlspecialchars($e->getMessage()));
}

function zimrx_calculate_current_age(?string $dob, ?string $fallbackAge, ?string $fallbackUnit = 'Years'): array {
    if (!empty($dob)) {
        try {
            $parts = preg_split('[/.-]', trim($dob));
            if (count($parts) === 3) {
                if (strlen($parts[0]) === 4) {
                    $dobIso = sprintf('%04d-%02d-%02d', (int)$parts[0], (int)$parts[1], (int)$parts[2]);
                } else {
                    $dobIso = sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
                }
                $dobDate = new DateTime($dobIso);
                $now = new DateTime();
                if ($dobDate <= $now) {
                    $diff = $now->diff($dobDate);
                    if ($diff->y > 0) {
                        return ['age' => $diff->y, 'unit' => 'Years', 'formatted' => $diff->y . ' Years'];
                    } elseif ($diff->m > 0) {
                        return ['age' => $diff->m, 'unit' => 'Months', 'formatted' => $diff->m . ' Months'];
                    } elseif ($diff->d >= 7) {
                        $w = (int)floor($diff->d / 7);
                        return ['age' => $w, 'unit' => 'Weeks', 'formatted' => $w . ' Weeks'];
                    } else {
                        return ['age' => max(0, $diff->d), 'unit' => 'Days', 'formatted' => max(0, $diff->d) . ' Days'];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[ZimRx] Invalid patient date of birth: ' . $e->getMessage());
        }
    }
    $age = $fallbackAge ?: '--';
    $unit = $fallbackUnit ?: 'Years';
    return ['age' => $age, 'unit' => $unit, 'formatted' => $age !== '--' ? "$age $unit" : '--'];
}

$userRole = current_user_role();
$isNonClinical = ($userRole === 'assistant' || $userRole === 'admin');
$currentDoctorId = current_user_doctor_id();
if ($currentDoctorId <= 0) {
    http_response_code(403);
    exit('A doctor scope is required to access the EMR.');
}

// Routing logic based on URL parameters
$requestedReg = trim((string)($_GET['reg'] ?? ''));
$requestedPatientId = (int)($_GET['patient_id'] ?? 0);
$requestedVisit = trim((string)($_GET['visit'] ?? ''));
$requestedVisitRecordId = (int)($_GET['visit_id'] ?? 0);

$viewMode = 'HUB'; // 'HUB', 'MASTER', 'ACTIVE'
$patient = null;
$activeVisit = null;
$timeline = [];
$allergies = [];
$pastVisitsForDrawer = [];

if ($requestedVisit !== '' || $requestedVisitRecordId > 0) {
    // Active EMR encounter mode
    $viewMode = 'ACTIVE';
    if ($requestedVisitRecordId > 0) {
        $stmtV = $pdo->prepare("SELECT * FROM zimrx_visits WHERE id = :id AND doctor_id = :did LIMIT 1");
        $stmtV->execute(['id' => $requestedVisitRecordId, 'did' => $currentDoctorId]);
    } else {
        $stmtV = $pdo->prepare("SELECT * FROM zimrx_visits WHERE visit_id = :vid AND doctor_id = :did LIMIT 1");
        $stmtV->execute(['vid' => $requestedVisit, 'did' => $currentDoctorId]);
    }
    $activeVisit = $stmtV->fetch(PDO::FETCH_ASSOC);

    if ($activeVisit) {
        // Assistants and Admins should not view clinical prescription editor
        if ($isNonClinical) {
            header('Location: emr.php?patient_id=' . (int)$activeVisit['patient_id']);
            exit;
        }

        $stmtP = $pdo->prepare("SELECT * FROM zimrx_patients WHERE id = :id AND doctor_id = :did LIMIT 1");
        $stmtP->execute(['id' => (int)$activeVisit['patient_id'], 'did' => $currentDoctorId]);
        $patient = $stmtP->fetch(PDO::FETCH_ASSOC);

        // Fetch previous visits for the Past Rx Reference Drawer
        $stmtPast = $pdo->prepare(
            "SELECT v.id, v.visit_id, v.visit_no, v.visit_date, v.clinical_snapshot_json, v.prescription_html AS print_html, v.rich_text_json
             FROM zimrx_visits v
             WHERE v.patient_id = :pid AND v.id != :curr_id AND v.doctor_id = :did
             ORDER BY v.visit_date DESC, v.id DESC LIMIT 15"
        );
        $stmtPast->execute(['pid' => (int)$activeVisit['patient_id'], 'curr_id' => (int)$activeVisit['id'], 'did' => $currentDoctorId]);
        $pastVisitsForDrawer = $stmtPast->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($isNonClinical) {
        header('Location: emr.php');
        exit;
    }
} elseif ($requestedReg !== '' || $requestedPatientId > 0) {
    // Patient master profile mode
    $viewMode = 'MASTER';
    if ($requestedPatientId > 0) {
        $stmtP = $pdo->prepare("SELECT * FROM zimrx_patients WHERE id = :id AND doctor_id = :did LIMIT 1");
        $stmtP->execute(['id' => $requestedPatientId, 'did' => $currentDoctorId]);
    } else {
        $stmtP = $pdo->prepare("SELECT * FROM zimrx_patients WHERE reg_no = :reg AND doctor_id = :did LIMIT 1");
        $stmtP->execute(['reg' => $requestedReg, 'did' => $currentDoctorId]);
    }
    $patient = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        $patientId = (int)$patient['id'];

        // Get visits timeline
        $stmtVisits = $pdo->prepare(
            "SELECT v.id as visit_record_id, v.visit_id, v.visit_no, v.visit_date, v.next_visit,
                    v.age_at_visit, v.weight_at_visit, v.weight_unit_at_visit, v.metrics_json,
                    v.id as prescription_id, v.clinical_snapshot_json, v.prescription_html AS print_html, v.rich_text_json
             FROM zimrx_visits v
             WHERE v.patient_id = :pid AND v.doctor_id = :did
             ORDER BY v.visit_date DESC, v.id DESC"
        );
        $stmtVisits->execute(['pid' => $patientId, 'did' => $currentDoctorId]);
        $timeline = $stmtVisits->fetchAll(PDO::FETCH_ASSOC);

        // Get Allergies (scoped to this doctor's patients only)
        $stmtAllergies = $pdo->prepare(
            "SELECT DISTINCT pd.generic_name
             FROM zimrx_prescription_drugs pd
             INNER JOIN zimrx_patients p ON p.id = pd.patient_id AND p.doctor_id = :did
             WHERE pd.patient_id = :pid AND pd.is_history = 1"
        );
        $stmtAllergies->execute(['pid' => $patientId, 'did' => $currentDoctorId]);
        while ($rowA = $stmtAllergies->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($rowA['generic_name'])) $allergies[] = $rowA['generic_name'];
        }
    }
}

// If Hub mode, load recent patients scoped to this doctor
$recentPatients = [];
if ($viewMode === 'HUB') {
    $stmtRecent = $pdo->prepare(
        "SELECT id, reg_no, full_name, mobile, gender, age, age_unit, blood_group, address, updated_at
         FROM zimrx_patients
         WHERE doctor_id = :did
         ORDER BY id DESC LIMIT 12"
    );
    $stmtRecent->execute(['did' => $currentDoctorId]);
    $recentPatients = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="assets/css/pages/emr.css">
<script src="assets/js/pages/emr_scanner.js" defer></script>

<?php
$patientCurrentAge = $patient ? zimrx_calculate_current_age($patient['dob'] ?? '', $patient['age'] ?? '', $patient['age_unit'] ?? 'Years') : ['formatted' => '--'];
$auditCount = 0;
if ($patient && !empty($patient['id'])) {
    try {
        ensure_patient_particulars_audit_schema($pdo);
        $stmtAC = $pdo->prepare("SELECT count(*) FROM zimrx_patient_particulars_audit WHERE patient_id = :pid");
        $stmtAC->execute(['pid' => (int)$patient['id']]);
        $auditCount = (int)$stmtAC->fetchColumn();
    } catch (Throwable $e) {
        error_log('[ZimRx] Unable to load patient audit count: ' . $e->getMessage());
    }
}
?>

<div class="emr-page zrx-page-container">

    <?php if ($viewMode === 'MASTER' && $patient): ?>
        <!-- Patient Master Profile View -->

        <?php if (!$isNonClinical && !empty($allergies)): ?>
        <div class="emr-alert-banner allergy-alert">
            <div class="emr-alert-content">
                <span class="emr-alert-badge">Allergy Alert</span>
                <span>Patient has documented severe allergy/history with: <strong><?= htmlspecialchars(implode(', ', $allergies)) ?></strong></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Master Top Card -->
        <div class="emr-top-card">
            <div class="emr-patient-identity">
                <div class="emr-avatar-badge">
                    <?= htmlspecialchars(strtoupper(substr($patient['full_name'], 0, 1))) ?>
                </div>
                <div class="emr-title-group">
                    <h1>
                        <?= htmlspecialchars($patient['full_name']) ?>
                        <span class="emr-meta-pill reg-pill">Reg: <?= htmlspecialchars($patient['reg_no'] ?: 'P' . $patient['id']) ?></span>
                    </h1>
                    <div class="emr-meta-pills">
                        <span class="emr-meta-pill"><?= htmlspecialchars($patientCurrentAge['formatted']) ?></span>
                        <span class="emr-meta-pill"><?= htmlspecialchars($patient['gender'] ?: 'Unspecified') ?></span>
                        <span class="emr-meta-pill">BG: <?= htmlspecialchars($patient['blood_group'] ?: '--') ?></span>
                        <span class="emr-meta-pill">📱 <?= htmlspecialchars($patient['mobile'] ?: '--') ?></span>
                        <span class="emr-meta-pill">📍 <?= htmlspecialchars($patient['address'] ?: '--') ?></span>
                    </div>
                </div>
            </div>
            <div class="emr-top-actions">
                <?php if (!$isNonClinical): ?>
                <button type="button" class="btn-emr btn-emr-primary" id="btn-start-visit" data-patient-id="<?= (int)$patient['id'] ?>" data-reg="<?= htmlspecialchars($patient['reg_no'] ?: '') ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Start New Visit
                </button>
                <?php elseif ($userRole === 'assistant'): ?>
                <a href="appointments.php" class="btn-emr btn-emr-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Appointment Desk
                </a>
                <?php endif; ?>
                <button type="button" class="btn-emr btn-emr-outline" onclick="openEditDemographicsModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Particulars
                </button>
                <a href="emr.php" class="btn-emr btn-emr-outline">
                    EMR Hub
                </a>
            </div>
        </div>

        <div class="emr-master-grid">
            <!-- Left Column: Static Demographics -->
            <div class="emr-demographics-card">
                <h3>
                    <span>Patient Particulars</span>
                    <div class="emr-header-actions-group">
                        <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" onclick="openParticularsAuditModal()" title="View particulars change audit trail">
                            🕒 History (<?= $auditCount ?>)
                        </button>
                        <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" onclick="openEditDemographicsModal()">Edit</button>
                    </div>
                </h3>
                <div class="emr-demo-list">
                    <div class="emr-demo-item">
                        <span class="label">Master Reg ID</span>
                        <span class="val emr-reg-val"><?= htmlspecialchars($patient['reg_no'] ?: 'P' . $patient['id']) ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Full Name</span>
                        <span class="val"><?= htmlspecialchars($patient['full_name']) ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Age / Unit</span>
                        <span class="val"><?= htmlspecialchars($patientCurrentAge['formatted']) ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Date of Birth</span>
                        <span class="val"><?= htmlspecialchars($patient['dob'] ?: '--') ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Sex / Gender</span>
                        <span class="val"><?= htmlspecialchars($patient['gender'] ?: '--') ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Blood Group</span>
                        <span class="val" class="zrx-c-danger"><?= htmlspecialchars($patient['blood_group'] ?: '--') ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Phone / Mobile</span>
                        <span class="val"><?= htmlspecialchars($patient['mobile'] ?: '--') ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Occupation</span>
                        <span class="val"><?= htmlspecialchars($patient['occupation'] ?: '--') ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Address</span>
                        <span class="val"><?= htmlspecialchars($patient['address'] ?: '--') ?></span>
                    </div>
                    <div class="emr-demo-item">
                        <span class="label">Weight / Height</span>
                        <span class="val"><?= htmlspecialchars($patient['weight'] ?: '--') ?> <?= htmlspecialchars($patient['weight_unit'] ?: 'kg') ?> | <?= htmlspecialchars($patient['height'] ?: '--') ?> <?= htmlspecialchars($patient['height_unit'] ?: 'inch') ?></span>
                    </div>
                </div>

                <?php if (!$isNonClinical): ?>
                <div class="emr-allergies-box">
                    <span class="label">Primary Allergies</span>
                    <?php if (!empty($allergies)): ?>
                        <?php foreach ($allergies as $alg): ?>
                            <span class="emr-allergy-tag"><?= htmlspecialchars($alg) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="emr-empty-note">No documented drug allergies recorded.</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Visits Timeline & Trends -->
            <div>
                <!-- Visits Timeline Card -->
                <div class="emr-timeline-card">
                    <h3>
                        <span><?= $isNonClinical ? 'Visits History' : 'Clinical Timeline' ?> (<?= count($timeline) ?> Visits)</span>
                    </h3>
                    <?php if (empty($timeline)): ?>
                        <div class="zrx-empty-block">No previous visits on record for this patient.</div>
                    <?php else: ?>
                    <div class="emr-timeline-table-wrap">
                        <table class="emr-timeline-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Visit ID</th>
                                    <th>Visit No</th>
                                    <?php if (!$isNonClinical): ?>
                                    <th>Chief Complaints</th>
                                    <th>Primary Diagnosis</th>
                                    <th>Vitals</th>
                                    <th class="zrx-ta-r">Action</th>
                                    <?php else: ?>
                                    <th>Next Revisit</th>
                                    <th class="zrx-ta-r">Status</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timeline as $t): 
                                    $mod = !empty($t['module_json']) ? json_decode($t['module_json'], true) : [];
                                    $ccArr = [];
                                    $dxArr = [];
                                    if (!empty($mod['chief_complaints'])) {
                                        foreach ((array)$mod['chief_complaints'] as $c) if (!empty($c['name'])) $ccArr[] = $c['name'];
                                    }
                                    if (!empty($mod['diagnosis'])) {
                                        foreach ((array)$mod['diagnosis'] as $d) if (!empty($d['name'])) $dxArr[] = $d['name'];
                                    }
                                    $metrics = !empty($t['metrics_json']) ? json_decode($t['metrics_json'], true) : [];
                                    $bp = $metrics['bp'] ?? '';
                                ?>
                                <tr>
                                    <td><strong><?= date('d M Y', strtotime($t['visit_date'])) ?></strong></td>
                                    <td><span class="emr-meta-pill visit-pill"><?= htmlspecialchars($t['visit_id'] ?: 'V' . $t['visit_record_id']) ?></span></td>
                                    <td><span class="emr-meta-pill">#<?= htmlspecialchars($t['visit_no'] ?: '1') ?></span></td>
                                    <?php if (!$isNonClinical): ?>
                                    <td><?= htmlspecialchars(!empty($ccArr) ? implode(', ', $ccArr) : '--') ?></td>
                                    <td><?= htmlspecialchars(!empty($dxArr) ? implode(', ', $dxArr) : '--') ?></td>
                                    <td><?= htmlspecialchars($bp ? "BP: $bp" : ($t['weight_at_visit'] ? "Wt: " . $t['weight_at_visit'] . ($t['weight_unit_at_visit'] ?: 'kg') : '--')) ?></td>
                                    <td class="zrx-ta-r">
                                        <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" onclick="openPastRxDrawer('<?= htmlspecialchars($t['visit_id'] ?: (string)$t['visit_record_id']) ?>')">
                                            👁 View Rx
                                        </button>
                                    </td>
                                    <?php else: ?>
                                    <td><?= !empty($t['next_visit']) ? htmlspecialchars(date('d M Y', strtotime($t['next_visit']))) : '--' ?></td>
                                    <td class="zrx-ta-r"><span class="emr-meta-pill emr-meta-pill-green">Completed</span></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$isNonClinical): ?>
                <!-- Modular Vitals & Longitudinal Trajectories Card -->
                <div class="emr-trends-card" id="emr-trajectories-card">
                    <div class="emr-trends-card-header">
                        <div class="emr-trends-title-group">
                            <h3>Vitals &amp; Longitudinal Trajectories</h3>
                            <div class="emr-trajectory-legend">
                                <span class="legend-item"><span class="legend-dot clinic-dot"></span> 🏥 Clinic</span>
                                <span class="legend-item"><span class="legend-dot home-dot"></span> 🏠 Home Logbook</span>
                            </div>
                        </div>
                        <div class="emr-trends-header-actions">
                            <div class="emr-dropdown-wrap">
                                <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" id="btn-add-tracker-dropdown" onclick="toggleAddTrackerMenu()">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add Tracker ▾
                                </button>
                                <div class="emr-dropdown-menu" id="add-tracker-menu">
                                    <div class="emr-dropdown-section-title">1. Daily &amp; General Vitals</div>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('weight')">⚖️ Weight Trajectory (kg)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('bp')">❤️ Blood Pressure (BP)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('pulse')">💓 Pulse / Heart Rate (bpm)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('spo2')">🫁 Oxygen Saturation (SpO2 %)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('temp')">🌡️ Body Temperature (°F)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('rr')">💨 Respiratory Rate (/min)</button>

                                    <div class="emr-dropdown-section-title">2. Metabolic &amp; Chronic Care</div>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('glucose')">🩸 Blood Glucose (FBS / PP)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('hba1c')">📊 Glycated Hb (HbA1c %)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('creatinine')">🧪 Serum Creatinine (mg/dL)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('egfr')">📉 eGFR (mL/min/1.73m²)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('ldl')">🫀 LDL Cholesterol (mg/dL)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('triglycerides')">🥓 Triglycerides (mg/dL)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('tsh')">🦋 Thyroid TSH (µIU/mL)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('uric_acid')">🦶 Serum Uric Acid (mg/dL)</button>

                                    <div class="emr-dropdown-section-title">3. Pediatric Growth</div>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('height')">📏 Height / Length (inch/cm)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('ofc')">👶 Head Circumference (OFC)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('muac')">📐 Arm Circumference (MUAC)</button>

                                    <div class="emr-dropdown-section-title">4. Special Clinical Curves</div>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('platelets')">🩸 Platelet Count (x10³/µL)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('hb')">🔴 Hemoglobin (Hb g/dL)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('crp')">⚡ C-Reactive Protein (CRP)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('esr')">⏱️ ESR (mm/1st hr)</button>
                                    <button type="button" class="emr-dropdown-item" onclick="addTracker('sfh')">🤰 Fundal Height (SFH cm)</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="emr-trends-grid" id="emr-trajectories-grid">
                        <div class="emr-empty-state-grid2">
                            Loading patient trajectories...
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($viewMode === 'ACTIVE' && $activeVisit && $patient): ?>
        <!-- Active EMR Encounter Workspace -->

        <div class="emr-top-card">
            <div class="emr-patient-identity">
                <div class="emr-avatar-badge">
                    <?= htmlspecialchars(strtoupper(substr($patient['full_name'], 0, 1))) ?>
                </div>
                <div class="emr-title-group">
                    <h1>
                        <?= htmlspecialchars($patient['full_name']) ?>
                        <span class="emr-meta-pill visit-pill">Active Encounter: <?= htmlspecialchars($activeVisit['visit_id'] ?: 'V' . $activeVisit['id']) ?></span>
                        <span class="emr-meta-pill reg-pill">Master: <?= htmlspecialchars($patient['reg_no'] ?: 'P' . $patient['id']) ?></span>
                    </h1>
                    <div class="emr-meta-pills">
                        <span class="emr-meta-pill"><?= htmlspecialchars(!empty($activeVisit['age_at_visit']) ? $activeVisit['age_at_visit'] : $patientCurrentAge['formatted']) ?></span>
                        <span class="emr-meta-pill"><?= htmlspecialchars($patient['gender'] ?: '--') ?></span>
                        <span class="emr-meta-pill">BG: <?= htmlspecialchars($patient['blood_group'] ?: '--') ?></span>
                        <span class="emr-meta-pill">Date: <?= date('d M Y', strtotime($activeVisit['visit_date'])) ?></span>
                    </div>
                </div>
            </div>
            <div class="emr-top-actions">
                <button type="button" class="btn-emr btn-emr-outline" id="btn-open-past-rx-drawer">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Past Rx Reference (<?= count($pastVisitsForDrawer) ?>)
                </button>
                <button type="button" class="btn-emr btn-emr-primary" id="btn-save-encounter">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Save &amp; Print
                </button>
                <a href="emr.php?reg=<?= urlencode($patient['reg_no'] ?: 'P' . $patient['id']) ?>" class="btn-emr btn-emr-outline">
                    👤 Master Profile
                </a>
            </div>
        </div>

        <!-- 3-Column Clinical Workspace -->
        <div class="emr-active-workspace">
            <!-- Left Column: Subjective & Objective -->
            <div class="emr-panel-card">
                <div class="emr-panel-header">1. Subjective &amp; Objective Findings</div>
                
                <div class="emr-form-group">
                    <label>Chief Complaints (C/C)</label>
                    <textarea id="emr-cc" rows="3" placeholder="e.g. Fever x 3 days, cough, headache..."></textarea>
                </div>

                <div class="emr-form-group">
                    <label>Vitals &amp; Biometrics</label>
                    <div class="emr-vitals-mini-grid">
                        <input type="text" id="emr-vital-bp" placeholder="BP (120/80)">
                        <input type="text" id="emr-vital-pulse" placeholder="Pulse (72 bpm)">
                        <input type="text" id="emr-vital-temp" placeholder="Temp (98.4 F)">
                        <input type="text" id="emr-vital-spo2" placeholder="SpO2 (98%)">
                        <input type="text" id="emr-vital-wt" placeholder="Weight (kg)">
                        <input type="text" id="emr-vital-ht" placeholder="Height (inch)">
                    </div>
                </div>

                <div class="emr-form-group">
                    <label>Physical Examination</label>
                    <textarea id="emr-pe" rows="3" placeholder="Physical examination observations..."></textarea>
                </div>

                <div class="emr-form-group">
                    <label>Diagnosis (Dx)</label>
                    <textarea id="emr-dx" rows="2" placeholder="Primary clinical diagnosis..."></textarea>
                </div>
            </div>

            <!-- Center Column: Dynamic Rx Grid -->
            <div class="emr-panel-card">
                <div class="emr-panel-header">2. Prescription (Rx) Medication Grid</div>
                
                <div class="emr-rx-entry-bar">
                    <div class="emr-form-group" class="zrx-m0">
                        <label>Drug Search</label>
                        <input type="text" id="emr-rx-drug-name" placeholder="Search Brand / Generic..." autocomplete="off">
                    </div>
                    <div class="emr-form-group" class="zrx-m0">
                        <label>Dose</label>
                        <input type="text" id="emr-rx-dose" placeholder="1+0+1">
                    </div>
                    <div class="emr-form-group" class="zrx-m0">
                        <label>Duration</label>
                        <input type="text" id="emr-rx-duration" placeholder="7 Days">
                    </div>
                    <div class="emr-form-group" class="zrx-m0">
                        <label>Instruction</label>
                        <input type="text" id="emr-rx-instruction" placeholder="After meal">
                    </div>
                    <button type="button" class="btn-emr btn-emr-success btn-emr-add-fixed" id="btn-add-rx-item">
                        + Add
                    </button>
                </div>

                <table class="emr-rx-table" id="emr-rx-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Drug Details</th>
                            <th>Dosage</th>
                            <th>Duration</th>
                            <th>Instructions</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="emr-rx-table-body">
                        <!-- Dynamic Rx rows -->
                    </tbody>
                </table>
            </div>

            <!-- Right Column: Plan & Advice -->
            <div class="emr-panel-card">
                <div class="emr-panel-header">3. Plan &amp; Advice</div>

                <div class="emr-form-group">
                    <label>Investigation Orders (Ix)</label>
                    <textarea id="emr-ix" rows="3" placeholder="CBC, RBS, Serum Creatinine, USG..."></textarea>
                </div>

                <div class="emr-form-group">
                    <label>Diet &amp; Lifestyle Advice</label>
                    <textarea id="emr-advice" rows="4" placeholder="Drink plenty of water, low salt diet, avoid smoking..."></textarea>
                </div>

                <div class="emr-form-group">
                    <label>Next Visit / Follow-up</label>
                    <input type="text" id="emr-next-visit" placeholder="e.g. After 7 days / DD-MM-YYYY">
                </div>

                <div class="emr-schedule-call-row">
                    <input type="checkbox" id="emr-schedule-call" class="emr-check-auto">
                    <label for="emr-schedule-call" class="emr-schedule-label">Schedule Assistant Check-in Call</label>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- EMR Hub View -->

        <div class="emr-hub-hero">
            <h2>Electronic Medical Record (EMR) Hub</h2>
            <p>Scan a patient PVC card, barcode, or search by Master ID (<code>P...</code>), Visit Token (<code>V...</code>), Name, or Mobile number.</p>
            
            <div class="emr-omni-searchbox-wrap">
                <svg class="emr-omni-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="emr-omni-input" id="emr-omni-input" placeholder="Scan Barcode or type Name, Phone, P2610001, V260827001..." autofocus>
            </div>

            <div class="emr-scanner-indicator">
                <span class="emr-scanner-dot"></span>
                <span>Hardware Barcode Scanner Ready</span>
            </div>
        </div>

        <div class="emr-timeline-card">
            <h3>Recent Patients</h3>
            <div class="emr-timeline-table-wrap">
                <table class="emr-timeline-table">
                    <thead>
                        <tr>
                            <th>Master Reg ID</th>
                            <th>Patient Name</th>
                            <th>Age / Sex</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Blood Group</th>
                            <th class="zrx-ta-r">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPatients as $rp): ?>
                        <tr>
                            <td><span class="emr-meta-pill reg-pill"><?= htmlspecialchars($rp['reg_no'] ?: 'P' . $rp['id']) ?></span></td>
                            <td><strong><?= htmlspecialchars($rp['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($rp['age'] ?: '--') ?> <?= htmlspecialchars($rp['age_unit'] ?: 'Y') ?> / <?= htmlspecialchars($rp['gender'] ?: '--') ?></td>
                            <td><?= htmlspecialchars($rp['mobile'] ?: '--') ?></td>
                            <td><?= htmlspecialchars($rp['address'] ?: '--') ?></td>
                            <td><span class="emr-bp-val-danger"><?= htmlspecialchars($rp['blood_group'] ?: '--') ?></span></td>
                            <td class="zrx-ta-r">
                                <a href="emr.php?reg=<?= urlencode($rp['reg_no'] ?: 'P' . $rp['id']) ?>" class="btn-emr btn-emr-primary btn-emr-sm">
                                    Open Master Profile ➔
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- Past Rx Reference Drawer -->
<div class="emr-drawer-overlay" id="emr-drawer-overlay" onclick="closePastRxDrawer()"></div>
<div class="emr-past-rx-drawer" id="emr-past-rx-drawer">
    <div class="emr-drawer-header">
        <h3>📋 Historical Prescription Reference</h3>
        <button type="button" class="emr-drawer-close-btn" onclick="closePastRxDrawer()">&times;</button>
    </div>
    <div class="emr-drawer-body" id="emr-drawer-content">
        <div class="zrx-empty-block">Loading encounter details...</div>
    </div>
</div>

<!-- Edit Demographics Modal -->
<?php if ($patient): ?>
<div class="emr-modal" id="emr-edit-demographics-modal">
    <div class="emr-modal-card">
        <div class="emr-modal-header">
            <h3>Edit Patient Particulars</h3>
            <button type="button" class="emr-drawer-close-btn" onclick="closeEditDemographicsModal()">&times;</button>
        </div>
        <form id="emr-edit-demographics-form">
            <input type="hidden" name="action" value="update_patient_demographics">
            <input type="hidden" name="patient_id" value="<?= (int)$patient['id'] ?>">

            <div class="emr-modal-grid">
                <div class="emr-form-group span-2">
                    <label>Full Patient Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($patient['full_name']) ?>" required>
                </div>
                <div class="emr-form-group">
                    <label>Mobile Number</label>
                    <input type="text" name="mobile" value="<?= htmlspecialchars($patient['mobile'] ?: '') ?>">
                </div>
                <div class="emr-form-group">
                    <label>Gender / Sex</label>
                    <select name="gender">
                        <option value="Male" <?= $patient['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $patient['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Others" <?= $patient['gender'] === 'Others' ? 'selected' : '' ?>>Others</option>
                    </select>
                </div>
                <div class="emr-form-group">
                    <label>Age</label>
                    <input type="text" name="age" value="<?= htmlspecialchars($patient['age'] ?: '') ?>">
                </div>
                <div class="emr-form-group">
                    <label>Age Unit</label>
                    <select name="age_unit">
                        <option value="Years" <?= ($patient['age_unit'] ?? '') === 'Years' ? 'selected' : '' ?>>Years</option>
                        <option value="Months" <?= ($patient['age_unit'] ?? '') === 'Months' ? 'selected' : '' ?>>Months</option>
                        <option value="Weeks" <?= ($patient['age_unit'] ?? '') === 'Weeks' ? 'selected' : '' ?>>Weeks</option>
                        <option value="Days" <?= ($patient['age_unit'] ?? '') === 'Days' ? 'selected' : '' ?>>Days</option>
                    </select>
                </div>
                <div class="emr-form-group">
                    <label>Date of Birth</label>
                    <input type="text" name="dob" value="<?= htmlspecialchars($patient['dob'] ?: '') ?>" placeholder="DD/MM/YYYY">
                </div>
                <div class="emr-form-group">
                    <label>Blood Group</label>
                    <select name="blood_group">
                        <option value="">--</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg): ?>
                        <option value="<?= $bg ?>" <?= $patient['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="emr-form-group">
                    <label>Occupation</label>
                    <input type="text" name="occupation" value="<?= htmlspecialchars($patient['occupation'] ?: '') ?>">
                </div>
                <div class="emr-form-group span-2">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($patient['address'] ?: '') ?>">
                </div>
            </div>

            <div class="emr-modal-footer">
                <button type="button" class="btn-emr btn-emr-outline" onclick="closeEditDemographicsModal()">Cancel</button>
                <button type="submit" class="btn-emr btn-emr-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Particulars Change Audit Log Modal -->
<div class="emr-modal" id="emr-particulars-audit-modal">
    <div class="emr-modal-card emr-audit-modal-card">
        <div class="emr-modal-header">
            <div>
                <h3>Particulars Change Audit History</h3>
                <div class="emr-modal-subtitle">
                    <?= htmlspecialchars($patient['full_name'] ?? '') ?> (Reg: <?= htmlspecialchars(($patient['reg_no'] ?? '') ?: 'P' . ($patient['id'] ?? '')) ?>)
                </div>
            </div>
            <button type="button" class="btn-emr-close" onclick="closeParticularsAuditModal()">&times;</button>
        </div>
        <div class="emr-modal-body" id="emr-particulars-audit-body">
            <div class="emr-empty-state-card">
                Loading audit trail...
            </div>
        </div>
        <div class="emr-modal-footer">
            <button type="button" class="btn-emr btn-emr-outline" onclick="closeParticularsAuditModal()">Close</button>
        </div>
    </div>
</div>

<!-- Quick Log Reading Modal (Home / Clinic standalone data) -->
<div class="emr-modal" id="emr-log-reading-modal">
    <div class="emr-modal-card emr-modal-card-narrow">
        <div class="emr-modal-header">
            <div>
                <h3 id="log-reading-modal-title">Log Home Reading</h3>
                <div class="emr-modal-subtitle">
                    Record self-monitored home reading or clinic desk check
                </div>
            </div>
            <button type="button" class="btn-emr-close" onclick="closeLogReadingModal()">&times;</button>
        </div>
        <form id="emr-log-reading-form">
            <input type="hidden" name="action" value="save_metric_reading">
            <input type="hidden" name="patient_id" value="<?= (int)($patient['id'] ?? 0) ?>">
            <input type="hidden" name="metric_type" id="log-metric-type" value="">

            <div class="emr-log-form-body">
                <div class="emr-form-group">
                    <label>Source</label>
                    <select name="source" id="log-source" class="emr-log-input-standard">
                        <option value="home" selected>🏠 Home / Self-Reported (Patient Logbook)</option>
                        <option value="clinic">🏥 Clinic / Desk Check</option>
                    </select>
                </div>

                <div class="emr-form-group">
                    <label id="log-reading-value-label">Reading Value</label>
                    <div class="emr-log-reading-wrap" id="log-reading-input-wrap">
                        <input type="text" name="reading_value" id="log-reading-value" required placeholder="e.g. 125/80" class="emr-log-reading-val">
                    </div>
                </div>

                <div class="emr-log-grid-2col">
                    <div class="emr-form-group">
                        <label>Date</label>
                        <input type="date" name="reading_date" id="log-reading-date" value="<?= date('Y-m-d') ?>" required class="emr-log-input-standard">
                    </div>
                    <div class="emr-form-group">
                        <label>Time (Optional)</label>
                        <input type="time" name="reading_time" id="log-reading-time" class="emr-log-input-standard">
                    </div>
                </div>

                <div class="emr-form-group">
                    <label>Context / Notes (Optional)</label>
                    <input type="text" name="notes" placeholder="e.g. Morning fasting, post-exercise, before pills" class="emr-log-input-notes">
                </div>
            </div>

            <div class="emr-modal-footer">
                <button type="button" class="btn-emr btn-emr-outline" onclick="closeLogReadingModal()">Cancel</button>
                <button type="submit" class="btn-emr btn-emr-primary" id="btn-save-log-reading">Save Reading</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script id="emrConfigData" type="application/json">
<?= json_encode([
    'patientId' => (int)($patient['id'] ?? 0),
    'firstPastVisitId' => !empty($pastVisitsForDrawer[0]['visit_id']) ? (string)$pastVisitsForDrawer[0]['visit_id'] : '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="assets/js/pages/emr.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/emr.js') ?>"></script>

<?php require_once __DIR__ . '/footer.php'; ?>
