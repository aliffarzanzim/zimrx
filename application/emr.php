<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/visit_identity.php';
require_once __DIR__ . '/emr_identity_lib.php';
require_once __DIR__ . '/particulars_audit_lib.php';

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
        } catch (Throwable $e) {}
    }
    $age = $fallbackAge ?: '--';
    $unit = $fallbackUnit ?: 'Years';
    return ['age' => $age, 'unit' => $unit, 'formatted' => $age !== '--' ? "$age $unit" : '--'];
}

$userRole = current_user_role();
$isNonClinical = ($userRole === 'assistant' || $userRole === 'admin');
$currentDoctorId = function_exists('current_user_doctor_id') ? current_user_doctor_id() : (int)($_SESSION['doctor_id'] ?? 1);
if ($currentDoctorId <= 0) {
    $stmtDoc = $pdo->query("SELECT id FROM zimrx_doctors ORDER BY id ASC LIMIT 1");
    $currentDoctorId = $stmtDoc ? (int)$stmtDoc->fetchColumn() : 1;
}

// ── Routing Logic based on URL Parameters ──
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
    // ── Active EMR Encounter Mode ──
    $viewMode = 'ACTIVE';
    if ($requestedVisitRecordId > 0) {
        $stmtV = $pdo->prepare("SELECT * FROM zimrx_visits WHERE id = :id LIMIT 1");
        $stmtV->execute(['id' => $requestedVisitRecordId]);
    } else {
        $stmtV = $pdo->prepare("SELECT * FROM zimrx_visits WHERE visit_id = :vid LIMIT 1");
        $stmtV->execute(['vid' => $requestedVisit]);
    }
    $activeVisit = $stmtV->fetch(PDO::FETCH_ASSOC);

    if ($activeVisit) {
        // Assistants and Admins should not view clinical prescription editor
        if ($isNonClinical) {
            header('Location: emr.php?patient_id=' . (int)$activeVisit['patient_id']);
            exit;
        }

        $stmtP = $pdo->prepare("SELECT * FROM zimrx_patients WHERE id = :id LIMIT 1");
        $stmtP->execute(['id' => (int)$activeVisit['patient_id']]);
        $patient = $stmtP->fetch(PDO::FETCH_ASSOC);

        // Fetch previous visits for the Past Rx Reference Drawer
        $stmtPast = $pdo->prepare(
            "SELECT v.id, v.visit_id, v.visit_no, v.visit_date, v.clinical_snapshot_json, v.prescription_html AS print_html, v.rich_text_json
             FROM zimrx_visits v
             WHERE v.patient_id = :pid AND v.id != :curr_id
             ORDER BY v.visit_date DESC, v.id DESC LIMIT 15"
        );
        $stmtPast->execute(['pid' => (int)$activeVisit['patient_id'], 'curr_id' => (int)$activeVisit['id']]);
        $pastVisitsForDrawer = $stmtPast->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($isNonClinical) {
        header('Location: emr.php');
        exit;
    }
} elseif ($requestedReg !== '' || $requestedPatientId > 0) {
    // ── Patient Master Profile Mode ──
    $viewMode = 'MASTER';
    if ($requestedPatientId > 0) {
        $stmtP = $pdo->prepare("SELECT * FROM zimrx_patients WHERE id = :id LIMIT 1");
        $stmtP->execute(['id' => $requestedPatientId]);
    } else {
        $stmtP = $pdo->prepare("SELECT * FROM zimrx_patients WHERE reg_no = :reg LIMIT 1");
        $stmtP->execute(['reg' => $requestedReg]);
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
             WHERE v.patient_id = :pid
             ORDER BY v.visit_date DESC, v.id DESC"
        );
        $stmtVisits->execute(['pid' => $patientId]);
        $timeline = $stmtVisits->fetchAll(PDO::FETCH_ASSOC);

        // Get Allergies
        $stmtAllergies = $pdo->prepare(
            "SELECT DISTINCT generic_name
             FROM zimrx_prescription_drugs
             WHERE patient_id = :pid AND is_history = 1"
        );
        $stmtAllergies->execute(['pid' => $patientId]);
        while ($rowA = $stmtAllergies->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($rowA['generic_name'])) $allergies[] = $rowA['generic_name'];
        }
    }
}

// If Hub mode, load recent patients
$recentPatients = [];
if ($viewMode === 'HUB') {
    $stmtRecent = $pdo->query(
        "SELECT id, reg_no, full_name, mobile, gender, age, age_unit, blood_group, address, updated_at
         FROM zimrx_patients
         ORDER BY id DESC LIMIT 12"
    );
    $recentPatients = $stmtRecent ? $stmtRecent->fetchAll(PDO::FETCH_ASSOC) : [];
}

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="assets/css/emr.css">
<script src="assets/js/emr_scanner.js" defer></script>

<?php
$patientCurrentAge = $patient ? zimrx_calculate_current_age($patient['dob'] ?? '', $patient['age'] ?? '', $patient['age_unit'] ?? 'Years') : ['formatted' => '--'];
$auditCount = 0;
if ($patient && !empty($patient['id'])) {
    try {
        ensure_patient_particulars_audit_schema($pdo);
        $stmtAC = $pdo->prepare("SELECT count(*) FROM zimrx_patient_particulars_audit WHERE patient_id = :pid");
        $stmtAC->execute(['pid' => (int)$patient['id']]);
        $auditCount = (int)$stmtAC->fetchColumn();
    } catch (Throwable $e) {}
}
?>

<div class="emr-page">

    <?php if ($viewMode === 'MASTER' && $patient): ?>
        <!-- ═══════════════════════════════════════════════════════
             PATIENT MASTER PROFILE VIEW (emr.php?reg=...)
        ════════════════════════════════════════════════════════ -->

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
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" onclick="openParticularsAuditModal()" title="View particulars change audit trail">
                            🕒 History (<?= $auditCount ?>)
                        </button>
                        <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" onclick="openEditDemographicsModal()">Edit</button>
                    </div>
                </h3>
                <div class="emr-demo-list">
                    <div class="emr-demo-item">
                        <span class="label">Master Reg ID</span>
                        <span class="val" style="color: #047857;"><?= htmlspecialchars($patient['reg_no'] ?: 'P' . $patient['id']) ?></span>
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
                        <span class="val" style="color: #dc2626;"><?= htmlspecialchars($patient['blood_group'] ?: '--') ?></span>
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
                        <span style="font-size: 0.82rem; color: #94a3b8;">No documented drug allergies recorded.</span>
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
                        <div style="text-align: center; color: #94a3b8; padding: 2rem;">No previous visits on record for this patient.</div>
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
                                    <th style="text-align: right;">Action</th>
                                    <?php else: ?>
                                    <th>Next Revisit</th>
                                    <th style="text-align: right;">Status</th>
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
                                    <td style="text-align: right;">
                                        <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" onclick="openPastRxDrawer('<?= htmlspecialchars($t['visit_id'] ?: (string)$t['visit_record_id']) ?>')">
                                            👁 View Rx
                                        </button>
                                    </td>
                                    <?php else: ?>
                                    <td><?= !empty($t['next_visit']) ? htmlspecialchars(date('d M Y', strtotime($t['next_visit']))) : '--' ?></td>
                                    <td style="text-align: right;"><span class="emr-meta-pill" style="color: #047857; background: #ecfdf5; border-color: #a7f3d0;">Completed</span></td>
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
                        <div style="text-align: center; color: #94a3b8; padding: 2.5rem 1rem; grid-column: span 2;">
                            Loading patient trajectories...
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($viewMode === 'ACTIVE' && $activeVisit && $patient): ?>
        <!-- ═══════════════════════════════════════════════════════
             ACTIVE EMR ENCOUNTER WORKSPACE (emr.php?visit=...)
        ════════════════════════════════════════════════════════ -->

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
                    <div class="emr-form-group" style="margin: 0;">
                        <label>Drug Search</label>
                        <input type="text" id="emr-rx-drug-name" placeholder="Search Brand / Generic..." autocomplete="off">
                    </div>
                    <div class="emr-form-group" style="margin: 0;">
                        <label>Dose</label>
                        <input type="text" id="emr-rx-dose" placeholder="1+0+1">
                    </div>
                    <div class="emr-form-group" style="margin: 0;">
                        <label>Duration</label>
                        <input type="text" id="emr-rx-duration" placeholder="7 Days">
                    </div>
                    <div class="emr-form-group" style="margin: 0;">
                        <label>Instruction</label>
                        <input type="text" id="emr-rx-instruction" placeholder="After meal">
                    </div>
                    <button type="button" class="btn-emr btn-emr-success" id="btn-add-rx-item" style="height: 34px;">
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

                <div style="margin-top: 1rem; padding-top: 0.85rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" id="emr-schedule-call" style="width: auto;">
                    <label for="emr-schedule-call" style="font-size: 0.82rem; font-weight: 600; color: #475569; cursor: pointer;">Schedule Assistant Check-in Call</label>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ═══════════════════════════════════════════════════════
             EMR HUB & OMNI SEARCH LANDING VIEW (emr.php)
        ════════════════════════════════════════════════════════ -->

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
                            <th style="text-align: right;">Action</th>
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
                            <td><span style="color: #dc2626; font-weight: 700;"><?= htmlspecialchars($rp['blood_group'] ?: '--') ?></span></td>
                            <td style="text-align: right;">
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

<!-- ═══════════════════════════════════════════════════════
     SLIDING PAST RX REFERENCE DRAWER
════════════════════════════════════════════════════════ -->
<div class="emr-drawer-overlay" id="emr-drawer-overlay" onclick="closePastRxDrawer()"></div>
<div class="emr-past-rx-drawer" id="emr-past-rx-drawer">
    <div class="emr-drawer-header">
        <h3>📋 Historical Prescription Reference</h3>
        <button type="button" class="emr-drawer-close-btn" onclick="closePastRxDrawer()">&times;</button>
    </div>
    <div class="emr-drawer-body" id="emr-drawer-content">
        <div style="text-align: center; color: #94a3b8; padding: 2rem;">Loading encounter details...</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT DEMOGRAPHICS MODAL
════════════════════════════════════════════════════════ -->
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
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                    <?= htmlspecialchars($patient['full_name'] ?? '') ?> (Reg: <?= htmlspecialchars(($patient['reg_no'] ?? '') ?: 'P' . ($patient['id'] ?? '')) ?>)
                </div>
            </div>
            <button type="button" class="btn-emr-close" onclick="closeParticularsAuditModal()">&times;</button>
        </div>
        <div class="emr-modal-body" id="emr-particulars-audit-body">
            <div style="text-align: center; color: #64748b; padding: 2.5rem 1rem;">
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
    <div class="emr-modal-card" style="max-width: 460px;">
        <div class="emr-modal-header">
            <div>
                <h3 id="log-reading-modal-title">Log Home Reading</h3>
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                    Record self-monitored home reading or clinic desk check
                </div>
            </div>
            <button type="button" class="btn-emr-close" onclick="closeLogReadingModal()">&times;</button>
        </div>
        <form id="emr-log-reading-form">
            <input type="hidden" name="action" value="save_metric_reading">
            <input type="hidden" name="patient_id" value="<?= (int)($patient['id'] ?? 0) ?>">
            <input type="hidden" name="metric_type" id="log-metric-type" value="">

            <div style="display: flex; flex-direction: column; gap: 0.85rem; padding: 0.5rem 0;">
                <div class="emr-form-group">
                    <label>Source</label>
                    <select name="source" id="log-source" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                        <option value="home" selected>🏠 Home / Self-Reported (Patient Logbook)</option>
                        <option value="clinic">🏥 Clinic / Desk Check</option>
                    </select>
                </div>

                <div class="emr-form-group">
                    <label id="log-reading-value-label">Reading Value</label>
                    <div style="display: flex; gap: 8px;" id="log-reading-input-wrap">
                        <input type="text" name="reading_value" id="log-reading-value" required placeholder="e.g. 125/80" style="flex: 1; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; font-weight: 600;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <div class="emr-form-group">
                        <label>Date</label>
                        <input type="date" name="reading_date" id="log-reading-date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                    <div class="emr-form-group">
                        <label>Time (Optional)</label>
                        <input type="time" name="reading_time" id="log-reading-time" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                </div>

                <div class="emr-form-group">
                    <label>Context / Notes (Optional)</label>
                    <input type="text" name="notes" placeholder="e.g. Morning fasting, post-exercise, before pills" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
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

<script>
// ── EMR UI Interactions & API Handlers ──
function openPastRxDrawer(visitIdentifier) {
    const drawer = document.getElementById('emr-past-rx-drawer');
    const overlay = document.getElementById('emr-drawer-overlay');
    const content = document.getElementById('emr-drawer-content');
    
    if (drawer && overlay) {
        drawer.classList.add('open');
        overlay.classList.add('open');
        content.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 2rem;">Loading encounter data...</div>';

        fetch('api/emr_api.php?action=get_visit_details&visit_id=' + encodeURIComponent(visitIdentifier))
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.visit) {
                    content.innerHTML = '<div style="color: #dc2626; padding: 1.5rem;">Could not load past prescription details.</div>';
                    return;
                }

                let html = '<div style="margin-bottom: 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.85rem;">' +
                    '<h4 style="margin: 0 0 0.35rem; font-size: 1.1rem; color: #0f172a;">Encounter ' + (data.visit.visit_id || ('V' + data.visit.id)) + '</h4>' +
                    '<div style="font-size: 0.82rem; color: #64748b;">Date: ' + data.visit.visit_date + '</div>' +
                    '</div>';

                if (data.drugs && data.drugs.length > 0) {
                    html += '<h5 style="font-size: 0.85rem; text-transform: uppercase; color: #475569; margin: 1rem 0 0.5rem;">Prescribed Medications (Rx)</h5>';
                    html += '<ul style="padding-left: 1.25rem; font-size: 0.88rem; line-height: 1.6;">';
                    data.drugs.forEach(d => {
                        html += '<li><strong>' + (d.drug_name || d.brand_name || d.generic_name) + '</strong> (' + (d.form || '') + ' ' + (d.strength || '') + ')<br>' +
                                '<span style="color: #64748b; font-size: 0.82rem;">' + (d.dosage || d.dose || '') + ' - ' + (d.duration || '') + ' - ' + (d.instructions || d.instruction || '') + '</span></li>';
                    });
                    html += '</ul>';
                }

                if (data.prescription && data.prescription.module_json) {
                    try {
                        const mod = JSON.parse(data.prescription.module_json);
                        if (mod.chief_complaints && mod.chief_complaints.length > 0) {
                            html += '<h5 style="font-size: 0.85rem; text-transform: uppercase; color: #475569; margin: 1rem 0 0.5rem;">Chief Complaints</h5>';
                            html += '<p style="font-size: 0.86rem; color: #1e293b;">' + mod.chief_complaints.map(c => c.name).join(', ') + '</p>';
                        }
                        if (mod.diagnosis && mod.diagnosis.length > 0) {
                            html += '<h5 style="font-size: 0.85rem; text-transform: uppercase; color: #475569; margin: 1rem 0 0.5rem;">Diagnosis</h5>';
                            html += '<p style="font-size: 0.86rem; color: #1e293b;">' + mod.diagnosis.map(d => d.name).join(', ') + '</p>';
                        }
                    } catch(e) {}
                }

                content.innerHTML = html;
            })
            .catch(err => {
                content.innerHTML = '<div style="color: #dc2626; padding: 1.5rem;">Error fetching encounter: ' + err.message + '</div>';
            });
    }
}

function closePastRxDrawer() {
    const drawer = document.getElementById('emr-past-rx-drawer');
    const overlay = document.getElementById('emr-drawer-overlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
}

function openEditDemographicsModal() {
    const modal = document.getElementById('emr-edit-demographics-modal');
    if (modal) modal.classList.add('open');
}

function closeEditDemographicsModal() {
    const modal = document.getElementById('emr-edit-demographics-modal');
    if (modal) modal.classList.remove('open');
}

document.addEventListener('DOMContentLoaded', () => {
    // Start New Visit handler
    const btnStartVisit = document.getElementById('btn-start-visit');
    if (btnStartVisit) {
        btnStartVisit.addEventListener('click', () => {
            const patientId = btnStartVisit.dataset.patientId;
            const regNo = btnStartVisit.dataset.reg;

            btnStartVisit.disabled = true;
            btnStartVisit.textContent = 'Generating Visit...';

            const formData = new FormData();
            formData.append('action', 'start_new_visit');
            formData.append('patient_id', patientId);
            formData.append('reg_no', regNo);

            fetch('api/emr_api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.visit_id) {
                    window.location.href = 'emr.php?visit=' + encodeURIComponent(data.visit_id);
                } else {
                    alert('Error starting new visit: ' + (data.message || 'Unknown error'));
                    btnStartVisit.disabled = false;
                    btnStartVisit.textContent = 'Start New Visit';
                }
            })
            .catch(err => {
                alert('Network error: ' + err.message);
                btnStartVisit.disabled = false;
                btnStartVisit.textContent = 'Start New Visit';
            });
        });
    }

    // Edit Demographics form submit
    const editForm = document.getElementById('emr-edit-demographics-form');
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);

            fetch('api/emr_api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update demographics');
                }
            })
            .catch(err => alert('Error: ' + err.message));
        });
    }

    // Past Rx Drawer trigger in active workspace
    const btnOpenDrawer = document.getElementById('btn-open-past-rx-drawer');
    if (btnOpenDrawer) {
        btnOpenDrawer.addEventListener('click', () => {
            openPastRxDrawer('<?= !empty($pastVisitsForDrawer[0]['visit_id']) ? htmlspecialchars($pastVisitsForDrawer[0]['visit_id']) : '' ?>');
        });
    }
});

function openParticularsAuditModal() {
    const modal = document.getElementById('emr-particulars-audit-modal');
    const body = document.getElementById('emr-particulars-audit-body');
    if (!modal) return;
    modal.classList.add('open');
    body.innerHTML = '<div style="text-align: center; color: #64748b; padding: 2.5rem 1rem;">Loading audit trail...</div>';

    fetch('api/emr_api.php?action=get_particulars_audit_log&patient_id=<?= (int)($patient['id'] ?? 0) ?>')
        .then(res => res.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.history) || data.history.length === 0) {
                body.innerHTML = `
                    <div class="emr-audit-empty">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <p style="margin: 0.75rem 0 0.25rem; font-weight: 600; color: #475569;">No changes logged yet</p>
                        <span style="font-size: 0.82rem; color: #94a3b8;">The particulars for this patient are at their initial registration values.</span>
                    </div>
                `;
                return;
            }

            let html = '<div class="emr-audit-timeline">';
            data.history.forEach(item => {
                const sourceMap = {
                    'prescription': '<span class="audit-source-pill source-prescription">Prescription Desk</span>',
                    'appointment': '<span class="audit-source-pill source-appointment">Appointment Desk</span>',
                    'emr': '<span class="audit-source-pill source-emr">EMR Portal</span>'
                };
                const sourceBadge = sourceMap[item.action_source] || `<span class="audit-source-pill">${escapeHtml(item.action_source)}</span>`;
                const roleBadge = `<span class="audit-role-pill role-${escapeHtml(item.changed_by_role || 'staff')}">${escapeHtml(item.changed_by_role || 'Staff')}</span>`;

                let diffsHtml = '';
                const changes = item.changes || {};
                if (changes.initial) {
                    diffsHtml = `<div class="audit-diff-item initial-reg">✨ Initial patient profile registered</div>`;
                } else {
                    for (const [key, diff] of Object.entries(changes)) {
                        diffsHtml += `
                            <div class="audit-diff-item">
                                <span class="diff-field">${escapeHtml(diff.label || key)}:</span>
                                <span class="diff-old">${escapeHtml(diff.old)}</span>
                                <span class="diff-arrow">➔</span>
                                <span class="diff-new">${escapeHtml(diff.new)}</span>
                            </div>
                        `;
                    }
                }

                html += `
                    <div class="audit-timeline-event">
                        <div class="audit-event-dot"></div>
                        <div class="audit-event-header">
                            <div class="audit-event-actor">
                                <strong>${escapeHtml(item.changed_by_name || 'Staff')}</strong>
                                ${roleBadge}
                                ${sourceBadge}
                            </div>
                            <div class="audit-event-time">${escapeHtml(item.created_at_formatted)}</div>
                        </div>
                        <div class="audit-event-diffs">
                            ${diffsHtml}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = `<div style="color: #dc2626; padding: 1.5rem; text-align: center;">Failed to load audit history: ${escapeHtml(err.message)}</div>`;
        });
}

function closeParticularsAuditModal() {
    const modal = document.getElementById('emr-particulars-audit-modal');
    if (modal) modal.classList.remove('open');
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ═════════════════════════════════════════════════════════════════════
// ── Modular Vitals & Longitudinal Trajectories Engine ──
// ═════════════════════════════════════════════════════════════════════
const METRIC_CONFIG = {
    // 1. Daily & General Vitals
    'weight':        { title: 'Weight Trajectory', unit: 'kg', icon: '⚖️', placeholder: 'e.g. 78.5', color: '#2563eb' },
    'bp':            { title: 'Blood Pressure', unit: 'mmHg', icon: '❤️', placeholder: 'e.g. 125/80', colorSys: '#dc2626', colorDia: '#059669' },
    'pulse':         { title: 'Pulse / Heart Rate', unit: 'bpm', icon: '💓', placeholder: 'e.g. 74', color: '#e11d48' },
    'spo2':          { title: 'Oxygen Saturation', unit: '%', icon: '🫁', placeholder: 'e.g. 98', color: '#0284c7' },
    'temp':          { title: 'Body Temperature', unit: '°F', icon: '🌡️', placeholder: 'e.g. 98.6', color: '#ea580c' },
    'rr':            { title: 'Respiratory Rate', unit: '/min', icon: '💨', placeholder: 'e.g. 18', color: '#0d9488' },

    // 2. Metabolic & Chronic Care
    'glucose':       { title: 'Blood Glucose (FBS/PP)', unit: 'mmol/L', icon: '🩸', placeholder: 'e.g. 6.8', color: '#8b5cf6' },
    'hba1c':         { title: 'Glycated Hb (HbA1c)', unit: '%', icon: '📊', placeholder: 'e.g. 7.2', color: '#ec4899' },
    'creatinine':    { title: 'Serum Creatinine', unit: 'mg/dL', icon: '🧪', placeholder: 'e.g. 1.1', color: '#6366f1' },
    'egfr':          { title: 'eGFR Filtration Rate', unit: 'mL/min', icon: '📉', placeholder: 'e.g. 88', color: '#10b981' },
    'ldl':           { title: 'LDL Cholesterol', unit: 'mg/dL', icon: '🫀', placeholder: 'e.g. 110', color: '#d97706' },
    'triglycerides': { title: 'Serum Triglycerides', unit: 'mg/dL', icon: '🥓', placeholder: 'e.g. 150', color: '#b45309' },
    'tsh':           { title: 'Thyroid TSH', unit: 'µIU/mL', icon: '🦋', placeholder: 'e.g. 2.5', color: '#7c3aed' },
    'uric_acid':     { title: 'Serum Uric Acid', unit: 'mg/dL', icon: '🦶', placeholder: 'e.g. 6.2', color: '#c026d3' },

    // 3. Pediatric Growth
    'height':        { title: 'Height / Length', unit: 'inch', icon: '📏', placeholder: 'e.g. 66', color: '#059669' },
    'ofc':           { title: 'Head Circumference (OFC)', unit: 'cm', icon: '👶', placeholder: 'e.g. 42.5', color: '#f59e0b' },
    'muac':          { title: 'Arm Circumference (MUAC)', unit: 'cm', icon: '📐', placeholder: 'e.g. 14.5', color: '#14b8a6' },

    // 4. Special Clinical Curves
    'platelets':     { title: 'Platelet Count', unit: 'k/µL', icon: '🩸', placeholder: 'e.g. 185', color: '#e11d48' },
    'hb':            { title: 'Hemoglobin (Hb)', unit: 'g/dL', icon: '🔴', placeholder: 'e.g. 13.2', color: '#be123c' },
    'crp':           { title: 'C-Reactive Protein (CRP)', unit: 'mg/L', icon: '⚡', placeholder: 'e.g. 4.5', color: '#f97316' },
    'esr':           { title: 'ESR (1st Hour)', unit: 'mm/h', icon: '⏱️', placeholder: 'e.g. 15', color: '#9333ea' },
    'sfh':           { title: 'Fundal Height (SFH)', unit: 'cm', icon: '🤰', placeholder: 'e.g. 28', color: '#db2777' }
};

let currentTrackedMetrics = ['weight'];
let currentSeriesData = {};

function loadTrajectories() {
    const grid = document.getElementById('emr-trajectories-grid');
    if (!grid) return;

    fetch('api/emr_api.php?action=get_patient_trajectories&patient_id=<?= (int)($patient['id'] ?? 0) ?>')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                grid.innerHTML = `<div style="color: #dc2626; padding: 1.5rem; text-align: center; grid-column: span 2;">${escapeHtml(data.message || 'Failed to load trajectories')}</div>`;
                return;
            }
            currentTrackedMetrics = Array.isArray(data.tracked_metrics) && data.tracked_metrics.length ? data.tracked_metrics : ['weight'];
            currentSeriesData = data.series || {};
            renderTrajectories();
        })
        .catch(err => {
            grid.innerHTML = `<div style="color: #dc2626; padding: 1.5rem; text-align: center; grid-column: span 2;">Network error loading trajectories: ${escapeHtml(err.message)}</div>`;
        });
}

function renderTrajectories() {
    const grid = document.getElementById('emr-trajectories-grid');
    if (!grid) return;

    if (currentTrackedMetrics.length === 0) {
        grid.innerHTML = `
            <div style="text-align: center; color: #64748b; padding: 2.5rem 1rem; grid-column: 1 / -1; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc;">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">📊</div>
                <div style="font-weight: 700; color: #334155; margin-bottom: 0.25rem;">No Trajectories Active for This Patient</div>
                <div style="font-size: 0.82rem; color: #94a3b8; margin-bottom: 1rem;">Click "Add Tracker ▾" above to select and monitor metrics for this patient.</div>
                <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" onclick="addTracker('weight')">＋ Show Weight Trajectory</button>
            </div>
        `;
        return;
    }

    let html = '';
    currentTrackedMetrics.forEach(mType => {
        const conf = METRIC_CONFIG[mType] || { title: mType, unit: '', icon: '📈', color: '#2563eb' };
        const points = currentSeriesData[mType] || [];
        const latestPt = points.length ? points[points.length - 1] : null;
        let latestText = '--';
        if (latestPt) {
            latestText = latestPt.value + (conf.unit && String(latestPt.value).indexOf(conf.unit) === -1 ? ' ' + conf.unit : '');
        }

        // Trend delta calculation
        let deltaHtml = '';
        if (points.length >= 2 && mType !== 'bp') {
            const lastVal = parseFloat(points[points.length - 1].value);
            const prevVal = parseFloat(points[points.length - 2].value);
            if (!isNaN(lastVal) && !isNaN(prevVal)) {
                const diff = lastVal - prevVal;
                if (Math.abs(diff) > 0.0001) {
                    const isUp = diff > 0;
                    const sign = isUp ? '▲ +' : '▼ ';
                    const diffFormatted = Math.abs(diff) >= 10 ? Math.round(Math.abs(diff)) : Math.abs(diff).toFixed(1);
                    deltaHtml = `<span class="emr-trend-delta ${isUp ? 'delta-up' : 'delta-down'}">${sign}${diffFormatted}</span>`;
                } else {
                    deltaHtml = `<span class="emr-trend-delta delta-stable">— Stable</span>`;
                }
            }
        }

        html += `
            <div class="emr-trend-box" id="tracker-box-${mType}">
                <div class="emr-trend-box-header">
                    <div class="emr-trend-box-title">
                        <span>${conf.icon}</span>
                        <span>${escapeHtml(conf.title)}</span>
                        ${latestPt ? `<span class="emr-trend-latest-val">${escapeHtml(latestText)}</span>` : ''}
                        ${deltaHtml}
                    </div>
                    <div class="emr-trend-actions">
                        <button type="button" class="btn-trend-action" onclick="openLogReadingModal('${mType}')" title="Log Home or Desk Reading">
                            ＋ Log
                        </button>
                        <button type="button" class="btn-trend-action btn-trend-remove" onclick="removeTracker('${mType}')" title="Hide this trajectory for this patient">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="emr-trend-svg-wrap">
                    ${buildTrajectorySvg(mType, points, conf)}
                </div>
            </div>
        `;
    });

    grid.innerHTML = html;
}

const NORMAL_RANGES = {
    'pulse':        { min: 60, max: 100, label: 'Normal (60-100 bpm)' },
    'spo2':         { min: 95, max: 100, label: 'Normal (95-100%)' },
    'temp':         { min: 97.0, max: 99.0, label: 'Normal (97-99°F)' },
    'rr':           { min: 12, max: 20, label: 'Normal (12-20/min)' },
    'glucose':      { min: 4.0, max: 7.8, label: 'Normal (4.0-7.8 mmol/L)' },
    'hba1c':        { min: 4.0, max: 5.7, label: 'Normal (<5.7%)' },
    'creatinine':   { min: 0.6, max: 1.2, label: 'Normal (0.6-1.2 mg/dL)' },
    'egfr':         { min: 60, max: 120, label: 'Normal (>60 mL/min)' },
    'ldl':          { min: 50, max: 100, label: 'Optimal (<100 mg/dL)' },
    'triglycerides':{ min: 50, max: 150, label: 'Normal (<150 mg/dL)' },
    'tsh':          { min: 0.4, max: 4.0, label: 'Normal (0.4-4.0 µIU/mL)' },
    'uric_acid':    { min: 3.5, max: 7.2, label: 'Normal (3.5-7.2 mg/dL)' },
    'platelets':    { min: 150, max: 450, label: 'Normal (150-450 k/µL)' },
    'hb':           { min: 12.0, max: 16.5, label: 'Normal (12.0-16.5 g/dL)' },
    'crp':          { min: 0, max: 5.0, label: 'Normal (<5.0 mg/L)' },
    'esr':          { min: 0, max: 20, label: 'Normal (<20 mm/h)' }
};

function formatChartDate(dateStr) {
    if (!dateStr) return '';
    try {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const m = parseInt(parts[1], 10) - 1;
            const d = parseInt(parts[2], 10);
            return `${d} ${months[m] || parts[1]}`;
        }
    } catch (e) {}
    return dateStr.length > 5 ? dateStr.slice(5) : dateStr;
}

function calculateNiceTicks(minVal, maxVal, ticksCount = 4) {
    let span = maxVal - minVal;
    if (span <= 0.001) {
        span = Math.max(1.5, minVal * 0.15);
    }
    // Generous breathing room (25% buffer) so data points never hug the axes
    const pad = Math.max(span * 0.25, 1.0);
    const rawMin = Math.max(0, minVal - pad);
    const rawMax = maxVal + pad;

    const rawRange = rawMax - rawMin;
    const rawStep = rawRange / ticksCount;
    const mag = Math.floor(Math.log10(rawStep));
    const magPow = Math.pow(10, mag);
    const magMsd = rawStep / magPow;

    let stepSize;
    if (magMsd > 5) stepSize = 10 * magPow;
    else if (magMsd > 2) stepSize = 5 * magPow;
    else if (magMsd > 1) stepSize = 2 * magPow;
    else stepSize = magPow;

    const niceMin = Math.floor(rawMin / stepSize) * stepSize;
    const niceMax = Math.ceil(rawMax / stepSize) * stepSize;

    const ticks = [];
    for (let v = niceMin; v <= niceMax + (stepSize * 0.001); v += stepSize) {
        ticks.push(Number(v.toFixed(2)));
    }
    return { niceMin, niceMax, ticks };
}

function getSmoothCurvePath(pts) {
    if (!pts || pts.length === 0) return "";
    if (pts.length === 1) return `M ${pts[0].x},${pts[0].y}`;
    if (pts.length === 2) {
        return `M ${pts[0].x.toFixed(1)},${pts[0].y.toFixed(1)} L ${pts[1].x.toFixed(1)},${pts[1].y.toFixed(1)}`;
    }

    let d = `M ${pts[0].x.toFixed(1)},${pts[0].y.toFixed(1)}`;
    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i === 0 ? 0 : i - 1];
        const p1 = pts[i];
        const p2 = pts[i + 1];
        const p3 = pts[i + 2] || p2;

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${cp1x.toFixed(1)},${cp1y.toFixed(1)} ${cp2x.toFixed(1)},${cp2y.toFixed(1)} ${p2.x.toFixed(1)},${p2.y.toFixed(1)}`;
    }
    return d;
}

function getSmoothAreaPath(pts, baselineY) {
    if (!pts || pts.length === 0) return "";
    if (pts.length === 1) return "";
    const curve = getSmoothCurvePath(pts);
    return `${curve} L ${pts[pts.length - 1].x.toFixed(1)},${baselineY} L ${pts[0].x.toFixed(1)},${baselineY} Z`;
}

function buildTrajectorySvg(mType, points, conf) {
    if (!points || points.length === 0) {
        return `
            <div class="emr-chart-empty">
                <span style="font-weight: 600;">No readings recorded yet</span>
                <span style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;">Click <strong>＋ Log</strong> to add home readings or clinic measurements.</span>
            </div>
        `;
    }

    const width = 600;
    const height = 210;
    const padding = { top: 22, right: 25, bottom: 35, left: 45 };
    const chartW = width - padding.left - padding.right;
    const chartH = height - padding.top - padding.bottom;
    const baselineY = padding.top + chartH;

    // Single reading graceful baseline milestone (No ugly triangles!)
    if (points.length === 1) {
        const pt = points[0];
        let valNum = parseFloat(pt.value) || 0;
        let diaNum = 0;
        let isBp = (mType === 'bp');
        if (isBp) {
            valNum = parseFloat(pt.sys) || 120;
            diaNum = parseFloat(pt.dia) || 80;
        }

        const { niceMin, niceMax, ticks } = calculateNiceTicks(
            isBp ? Math.min(valNum, diaNum) : valNum,
            isBp ? Math.max(valNum, diaNum) : valNum,
            4
        );

        const getY = (val) => padding.top + chartH - ((val - niceMin) / (niceMax - niceMin)) * chartH;
        const x = padding.left + (chartW / 2);
        const y = getY(valNum);
        const isHome = (pt.source === 'home');
        const color = isHome ? '#f59e0b' : (conf.color || '#2563eb');

        // Y grid lines
        const gridHtml = ticks.map(tickVal => {
            const yTick = getY(tickVal);
            return `
                <g>
                    <line x1="${padding.left}" y1="${yTick.toFixed(1)}" x2="${width - padding.right}" y2="${yTick.toFixed(1)}" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="2 3" />
                    <text x="${padding.left - 8}" y="${(yTick + 3.5).toFixed(1)}" text-anchor="end" font-size="10" font-family="monospace" fill="#94a3b8">${tickVal}</text>
                </g>
            `;
        }).join('');

        let pointVisual = '';
        if (isBp) {
            const yDia = getY(diaNum);
            pointVisual = `
                <line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="#dc2626" stroke-width="1.2" stroke-dasharray="4 4" stroke-opacity="0.3" />
                <line x1="${padding.left}" y1="${yDia.toFixed(1)}" x2="${width - padding.right}" y2="${yDia.toFixed(1)}" stroke="#059669" stroke-width="1.2" stroke-dasharray="4 4" stroke-opacity="0.3" />
                
                <circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="14" fill="#dc2626" fill-opacity="0.12" />
                <circle id="dot-${mType}-sys-0" cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="5" fill="#ffffff" stroke="#dc2626" stroke-width="2.5" />
                <text x="${x.toFixed(1)}" y="${(y - 12).toFixed(1)}" text-anchor="middle" font-size="11" font-weight="700" fill="#dc2626">Sys: ${pt.sys}</text>

                <circle cx="${x.toFixed(1)}" cy="${yDia.toFixed(1)}" r="14" fill="#059669" fill-opacity="0.12" />
                <circle id="dot-${mType}-dia-0" cx="${x.toFixed(1)}" cy="${yDia.toFixed(1)}" r="5" fill="#ffffff" stroke="#059669" stroke-width="2.5" />
                <text x="${x.toFixed(1)}" y="${(yDia + 20).toFixed(1)}" text-anchor="middle" font-size="11" font-weight="700" fill="#059669">Dia: ${pt.dia}</text>
            `;
        } else {
            pointVisual = `
                <line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="${color}" stroke-width="1.2" stroke-dasharray="4 4" stroke-opacity="0.35" />
                <circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="16" fill="${color}" fill-opacity="0.10" />
                <circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="9" fill="${color}" fill-opacity="0.22" />
                <circle id="dot-${mType}-0" cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="5" fill="#ffffff" stroke="${color}" stroke-width="2.8" />
                <text x="${x.toFixed(1)}" y="${(y - 14).toFixed(1)}" text-anchor="middle" font-size="11" font-weight="800" fill="${color}">${pt.value} ${conf.unit || ''}</text>
            `;
        }

        return `
            <svg viewBox="0 0 ${width} ${height}" class="emr-chart-svg" id="svg-${mType}">
                ${gridHtml}
                ${pointVisual}
                <text x="${x.toFixed(1)}" y="${baselineY + 16}" text-anchor="middle" font-size="10.5" font-weight="600" fill="#64748b">${formatChartDate(pt.date)} (Baseline)</text>
                <rect x="${padding.left}" y="${padding.top}" width="${chartW}" height="${chartH}"
                      fill="transparent" class="chart-hit-slice"
                      onmouseenter="onTrajectorySliceHover(event, '${mType}', 0)"
                      onmouseleave="onTrajectorySliceLeave('${mType}')" />
            </svg>
            <div class="emr-chart-tooltip" id="tooltip-${mType}" style="display:none;"></div>
        `;
    }

    // Multiple points (>= 2)
    if (mType === 'bp') {
        const sysVals = points.map(p => Number(p.sys) || 0).filter(v => v > 0);
        const diaVals = points.map(p => Number(p.dia) || 0).filter(v => v > 0);
        const rawMin = Math.min(40, ...diaVals.length ? diaVals : [60]);
        const rawMax = Math.max(180, ...sysVals.length ? sysVals : [140]);
        const { niceMin, niceMax, ticks } = calculateNiceTicks(rawMin, rawMax, 4);

        const getY = (val) => padding.top + chartH - ((val - niceMin) / (niceMax - niceMin)) * chartH;
        const getX = (idx) => padding.left + (idx / (points.length - 1)) * chartW;

        const sysPts = points.map((p, i) => ({ x: getX(i), y: getY(p.sys || 120) }));
        const diaPts = points.map((p, i) => ({ x: getX(i), y: getY(p.dia || 80) }));

        // Grid lines Y
        let gridHtml = ticks.map(tickVal => {
            const y = getY(tickVal);
            return `
                <g>
                    <line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="2 3" />
                    <text x="${padding.left - 8}" y="${(y + 3.5).toFixed(1)}" text-anchor="end" font-size="10" font-family="monospace" fill="#94a3b8">${tickVal}</text>
                </g>
            `;
        }).join('');

        // Grid lines X (dates)
        const stepX = points.length <= 6 ? 1 : Math.ceil(points.length / 5);
        points.forEach((p, i) => {
            if (i % stepX === 0 || i === points.length - 1) {
                const x = getX(i);
                gridHtml += `
                    <g>
                        <line x1="${x.toFixed(1)}" y1="${padding.top}" x2="${x.toFixed(1)}" y2="${baselineY}" stroke="#f8fafc" stroke-width="1" stroke-dasharray="3 3" />
                        <text x="${x.toFixed(1)}" y="${baselineY + 16}" text-anchor="middle" font-size="10" fill="#64748b" font-weight="600">${formatChartDate(p.date)}</text>
                    </g>
                `;
            }
        });

        // Smooth Bezier paths
        const sysPathD = getSmoothCurvePath(sysPts);
        const sysAreaD = getSmoothAreaPath(sysPts, baselineY);
        const diaPathD = getSmoothCurvePath(diaPts);
        const diaAreaD = getSmoothAreaPath(diaPts, baselineY);

        // Point dots & value tags
        let pointsHtml = '';
        const showAllLabelsBp = (points.length <= 12);
        points.forEach((pt, i) => {
            const x = getX(i);
            const ySys = getY(pt.sys || 120);
            const yDia = getY(pt.dia || 80);
            const isHome = (pt.source === 'home');
            const strokeSys = isHome ? '#f59e0b' : '#dc2626';
            const strokeDia = isHome ? '#f59e0b' : '#059669';
            const fillDot = isHome ? '#fef3c7' : '#ffffff';

            pointsHtml += `
                <circle id="dot-${mType}-sys-${i}" class="emr-chart-dot" cx="${x.toFixed(1)}" cy="${ySys.toFixed(1)}" r="4.5" fill="${fillDot}" stroke="${strokeSys}" stroke-width="2.5" />
                <circle id="dot-${mType}-dia-${i}" class="emr-chart-dot" cx="${x.toFixed(1)}" cy="${yDia.toFixed(1)}" r="4.5" fill="${fillDot}" stroke="${strokeDia}" stroke-width="2.5" />
                <text id="val-tag-sys-${mType}-${i}" class="emr-chart-point-val ${showAllLabelsBp ? 'visible' : ''}" data-always="${showAllLabelsBp ? '1' : '0'}" x="${x.toFixed(1)}" y="${(ySys - 9).toFixed(1)}" text-anchor="middle" font-size="10.5" font-weight="700" fill="#dc2626">${pt.sys}</text>
                <text id="val-tag-dia-${mType}-${i}" class="emr-chart-point-val ${showAllLabelsBp ? 'visible' : ''}" data-always="${showAllLabelsBp ? '1' : '0'}" x="${x.toFixed(1)}" y="${(yDia + 16).toFixed(1)}" text-anchor="middle" font-size="10.5" font-weight="700" fill="#059669">${pt.dia}</text>
            `;
        });

        // Hit-test overlay slices
        let hitSlicesHtml = '';
        const sliceW = chartW / (points.length - 1);
        points.forEach((pt, i) => {
            const x = i === 0 ? padding.left : getX(i) - (sliceW / 2);
            const w = (i === 0 || i === points.length - 1) ? (sliceW / 2) : sliceW;
            hitSlicesHtml += `
                <rect x="${x.toFixed(1)}" y="${padding.top}" width="${w.toFixed(1)}" height="${chartH}"
                      fill="transparent" class="chart-hit-slice"
                      onmouseenter="onTrajectorySliceHover(event, '${mType}', ${i})"
                      onmouseleave="onTrajectorySliceLeave('${mType}')" />
            `;
        });

        return `
            <svg viewBox="0 0 ${width} ${height}" class="emr-chart-svg" id="svg-${mType}">
                <defs>
                    <linearGradient id="grad-${mType}-sys" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#dc2626" stop-opacity="0.18" />
                        <stop offset="100%" stop-color="#dc2626" stop-opacity="0.01" />
                    </linearGradient>
                    <linearGradient id="grad-${mType}-dia" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#059669" stop-opacity="0.16" />
                        <stop offset="100%" stop-color="#059669" stop-opacity="0.01" />
                    </linearGradient>
                </defs>

                ${gridHtml}
                <path d="${sysAreaD}" fill="url(#grad-${mType}-sys)" />
                <path d="${diaAreaD}" fill="url(#grad-${mType}-dia)" />
                <path d="${sysPathD}" fill="none" stroke="#dc2626" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" />
                <path d="${diaPathD}" fill="none" stroke="#059669" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4 3" />
                ${pointsHtml}

                <line id="crosshair-${mType}" class="emr-chart-crosshair" x1="0" y1="${padding.top}" x2="0" y2="${baselineY}" style="display:none;" />
                ${hitSlicesHtml}
            </svg>
            <div class="emr-chart-tooltip" id="tooltip-${mType}" style="display:none;"></div>
        `;
    }

    // Single value metrics (Weight, Glucose, HbA1c, Pulse, Platelets, etc.)
    const vals = points.map(p => Number(p.value) || 0).filter(v => v > 0);
    const rawMin = Math.min(...vals.length ? vals : [0]);
    const rawMax = Math.max(...vals.length ? vals : [100]);
    const { niceMin, niceMax, ticks } = calculateNiceTicks(rawMin, rawMax, 4);

    const getY = (val) => padding.top + chartH - ((val - niceMin) / (niceMax - niceMin)) * chartH;
    const getX = (idx) => padding.left + (idx / (points.length - 1)) * chartW;

    const pts = points.map((p, i) => ({ x: getX(i), y: getY(Number(p.value) || 0) }));

    // Reference Normal Range Band (if applicable)
    let normalBandHtml = '';
    const norm = NORMAL_RANGES[mType];
    if (norm && norm.min !== undefined && norm.max !== undefined) {
        const yTop = Math.max(padding.top, getY(norm.max));
        const yBtm = Math.min(baselineY, getY(norm.min));
        const h = Math.max(0, yBtm - yTop);
        if (h > 0 && yTop < baselineY && yBtm > padding.top) {
            normalBandHtml = `
                <rect x="${padding.left}" y="${yTop.toFixed(1)}" width="${chartW}" height="${h.toFixed(1)}"
                      fill="#10b981" fill-opacity="0.04" stroke="#10b981" stroke-opacity="0.16" stroke-dasharray="3 3" stroke-width="0.8" />
                <text x="${width - padding.right - 4}" y="${(yTop + 11).toFixed(1)}" text-anchor="end" font-size="8.5" font-weight="700" fill="#10b981" opacity="0.85">${escapeHtml(norm.label)}</text>
            `;
        }
    }

    // Grid lines Y
    let gridHtml = ticks.map(tickVal => {
        const y = getY(tickVal);
        return `
            <g>
                <line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="2 3" />
                <text x="${padding.left - 8}" y="${(y + 3.5).toFixed(1)}" text-anchor="end" font-size="10" font-family="monospace" fill="#94a3b8">${tickVal}</text>
            </g>
        `;
    }).join('');

    // Grid lines X (dates)
    const stepX = points.length <= 6 ? 1 : Math.ceil(points.length / 5);
    points.forEach((p, i) => {
        if (i % stepX === 0 || i === points.length - 1) {
            const x = getX(i);
            gridHtml += `
                <g>
                    <line x1="${x.toFixed(1)}" y1="${padding.top}" x2="${x.toFixed(1)}" y2="${baselineY}" stroke="#f8fafc" stroke-width="1" stroke-dasharray="3 3" />
                    <text x="${x.toFixed(1)}" y="${baselineY + 16}" text-anchor="middle" font-size="10" fill="#64748b" font-weight="600">${formatChartDate(p.date)}</text>
                </g>
            `;
        }
    });

    // Smooth Bezier Paths
    const pathD = getSmoothCurvePath(pts);
    const areaD = getSmoothAreaPath(pts, baselineY);

    // Points & value tags above dots
    let pointsHtml = '';
    const showAllLabels = (points.length <= 14);
    points.forEach((pt, i) => {
        const x = getX(i);
        const y = getY(Number(pt.value) || 0);
        const isHome = (pt.source === 'home');
        const strokeColor = isHome ? '#f59e0b' : (conf.color || '#2563eb');
        const fillDot = isHome ? '#fef3c7' : '#ffffff';

        // Keep label inside bounds
        const yTag = (y - 9 < padding.top + 6) ? (y + 16) : (y - 9);

        pointsHtml += `
            <circle id="dot-${mType}-${i}" class="emr-chart-dot" cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="4.5" fill="${fillDot}" stroke="${strokeColor}" stroke-width="2.5" />
            <text id="val-tag-${mType}-${i}" class="emr-chart-point-val ${showAllLabels ? 'visible' : ''}" data-always="${showAllLabels ? '1' : '0'}" x="${x.toFixed(1)}" y="${yTag.toFixed(1)}" text-anchor="middle">${escapeHtml(pt.value)}</text>
        `;
    });

    // Hit-test overlay slices
    let hitSlicesHtml = '';
    const sliceW = chartW / (points.length - 1);
    points.forEach((pt, i) => {
        const x = i === 0 ? padding.left : getX(i) - (sliceW / 2);
        const w = (i === 0 || i === points.length - 1) ? (sliceW / 2) : sliceW;
        hitSlicesHtml += `
            <rect x="${x.toFixed(1)}" y="${padding.top}" width="${w.toFixed(1)}" height="${chartH}"
                  fill="transparent" class="chart-hit-slice"
                  onmouseenter="onTrajectorySliceHover(event, '${mType}', ${i})"
                  onmouseleave="onTrajectorySliceLeave('${mType}')" />
        `;
    });

    return `
        <svg viewBox="0 0 ${width} ${height}" class="emr-chart-svg" id="svg-${mType}">
            <defs>
                <linearGradient id="grad-${mType}" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="${conf.color || '#2563eb'}" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="${conf.color || '#2563eb'}" stop-opacity="0.01" />
                </linearGradient>
            </defs>

            ${normalBandHtml}
            ${gridHtml}
            <path d="${areaD}" fill="url(#grad-${mType})" />
            <path d="${pathD}" fill="none" stroke="${conf.color || '#2563eb'}" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
            ${pointsHtml}

            <line id="crosshair-${mType}" class="emr-chart-crosshair" x1="0" y1="${padding.top}" x2="0" y2="${baselineY}" style="display:none;" />
            ${hitSlicesHtml}
        </svg>
        <div class="emr-chart-tooltip" id="tooltip-${mType}" style="display:none;"></div>
    `;
}

function onTrajectorySliceHover(event, mType, idx) {
    const points = currentSeriesData[mType] || [];
    const pt = points[idx];
    if (!pt) return;

    const conf = METRIC_CONFIG[mType] || { title: mType, unit: '', color: '#2563eb' };
    const width = 600;
    const height = 210;
    const padding = { top: 22, right: 25, bottom: 35, left: 45 };
    const chartW = width - padding.left - padding.right;

    // Locate the exact point dot in the SVG DOM to extract its precise x and y coordinates
    const dot = document.getElementById(`dot-${mType}-${idx}`) || document.getElementById(`dot-${mType}-sys-${idx}`);
    let x = points.length <= 1 ? (padding.left + chartW / 2) : padding.left + (idx / (points.length - 1)) * chartW;
    let y = 100;

    if (dot) {
        x = parseFloat(dot.getAttribute('cx')) || x;
        y = parseFloat(dot.getAttribute('cy')) || y;
    }

    // 1. Move & Show Crosshair
    const crosshair = document.getElementById(`crosshair-${mType}`);
    if (crosshair) {
        crosshair.setAttribute('x1', x.toFixed(1));
        crosshair.setAttribute('x2', x.toFixed(1));
        crosshair.style.display = 'block';
    }

    // 2. Highlight point dot
    document.querySelectorAll(`.emr-chart-dot[id^="dot-${mType}-"]`).forEach(d => d.classList.remove('active'));
    if (dot) dot.classList.add('active');
    const dotSys = document.getElementById(`dot-${mType}-sys-${idx}`);
    if (dotSys) dotSys.classList.add('active');
    const dotDia = document.getElementById(`dot-${mType}-dia-${idx}`);
    if (dotDia) dotDia.classList.add('active');

    // 3. Highlight point value text label right above dot
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-${mType}-"]`).forEach(t => t.classList.remove('active'));
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-sys-${mType}-"]`).forEach(t => t.classList.remove('active'));
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-dia-${mType}-"]`).forEach(t => t.classList.remove('active'));

    const tag = document.getElementById(`val-tag-${mType}-${idx}`);
    if (tag) tag.classList.add('active', 'visible');
    const tagSys = document.getElementById(`val-tag-sys-${mType}-${idx}`);
    if (tagSys) tagSys.classList.add('active', 'visible');
    const tagDia = document.getElementById(`val-tag-dia-${mType}-${idx}`);
    if (tagDia) tagDia.classList.add('active', 'visible');

    // 4. Populate and Position Full Clinical Details Tooltip Above Exact Point
    const tooltip = document.getElementById(`tooltip-${mType}`);
    if (tooltip) {
        const isHome = (pt.source === 'home');
        const fullDate = pt.date + (pt.time ? ' ' + pt.time : '');
        const delBtn = (pt.id && String(pt.id).indexOf('visit_') === -1) 
            ? `<button type="button" onclick="event.stopPropagation(); deleteLogReading(${pt.id}, '${mType}')" style="background:none; border:none; color:#dc2626; font-size:0.68rem; font-weight:700; cursor:pointer; padding:0 2px;" title="Delete logbook entry">✕ Delete</button>` 
            : '';

        let metricContent = '';
        if (mType === 'bp') {
            metricContent = `
                <div class="tooltip-metric-row">
                    <span class="tooltip-metric-title" style="color: #dc2626;">Systolic</span>
                    <strong class="tooltip-metric-value" style="color: #dc2626;">${pt.sys || '--'} <small style="font-weight:600; color:#64748b;">mmHg</small></strong>
                </div>
                <div class="tooltip-metric-row">
                    <span class="tooltip-metric-title" style="color: #059669;">Diastolic</span>
                    <strong class="tooltip-metric-value" style="color: #059669;">${pt.dia || '--'} <small style="font-weight:600; color:#64748b;">mmHg</small></strong>
                </div>
            `;
        } else {
            metricContent = `
                <div class="tooltip-metric-row">
                    <span class="tooltip-metric-title">${escapeHtml(conf.title)}</span>
                    <strong class="tooltip-metric-value" style="color: ${conf.color || '#2563eb'}; font-size: 0.88rem;">${pt.value} ${conf.unit || ''}</strong>
                </div>
            `;
        }

        tooltip.innerHTML = `
            <div class="tooltip-header">
                <span class="tooltip-date">${escapeHtml(fullDate)}</span>
                <span class="tooltip-badge ${isHome ? 'badge-home' : 'badge-clinic'}">
                    ${isHome ? '🏠 Home Logbook' : '🏥 Clinic Consultation'}
                </span>
            </div>
            ${metricContent}
            ${pt.notes ? `<div class="tooltip-notes"><strong>Note:</strong> ${escapeHtml(pt.notes)}</div>` : ''}
            ${delBtn ? `<div style="text-align: right; margin-top: 4px;">${delBtn}</div>` : ''}
        `;

        // Position floating comfortably above the exact hovered point
        const leftPct = Math.max(16, Math.min(84, (x / width) * 100));
        const topPct = (y / height) * 100;
        const isNearTop = (y < 75);

        tooltip.style.left = `${leftPct}%`;
        tooltip.style.top = `${topPct}%`;
        tooltip.style.right = 'auto';

        if (isNearTop) {
            // High peak near top: float 16px below the dot so it doesn't collide with card header
            tooltip.style.transform = 'translate(-50%, 16px)';
            tooltip.className = 'emr-chart-tooltip caret-top';
        } else {
            // Normal: float 16px above the dot so the graph line, dot, and curve stay fully visible
            tooltip.style.transform = 'translate(-50%, calc(-100% - 16px))';
            tooltip.className = 'emr-chart-tooltip caret-bottom';
        }
        tooltip.style.display = 'block';
    }
}

function onTrajectorySliceLeave(mType) {
    const crosshair = document.getElementById(`crosshair-${mType}`);
    if (crosshair) crosshair.style.display = 'none';

    const tooltip = document.getElementById(`tooltip-${mType}`);
    if (tooltip) tooltip.style.display = 'none';

    document.querySelectorAll(`.emr-chart-dot[id^="dot-${mType}-"]`).forEach(d => d.classList.remove('active'));

    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-${mType}-"]`).forEach(t => {
        t.classList.remove('active');
        if (t.getAttribute('data-always') !== '1') t.classList.remove('visible');
    });
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-sys-${mType}-"]`).forEach(t => {
        t.classList.remove('active');
        if (t.getAttribute('data-always') !== '1') t.classList.remove('visible');
    });
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-dia-${mType}-"]`).forEach(t => {
        t.classList.remove('active');
        if (t.getAttribute('data-always') !== '1') t.classList.remove('visible');
    });
}

function deleteLogReading(readingId, metricType) {
    if (!confirm('Are you sure you want to delete this logbook entry?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_metric_reading');
    formData.append('id', readingId);
    formData.append('patient_id', '<?= (int)($patient['id'] ?? 0) ?>');

    fetch('api/emr_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadTrajectories();
            } else {
                alert(data.message || 'Failed to delete reading');
            }
        });
}

function toggleAddTrackerMenu() {
    const menu = document.getElementById('add-tracker-menu');
    if (menu) menu.classList.toggle('open');
}

document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.emr-dropdown-wrap');
    const menu = document.getElementById('add-tracker-menu');
    if (menu && wrap && !wrap.contains(e.target)) {
        menu.classList.remove('open');
    }
});

function addTracker(metricType) {
    const menu = document.getElementById('add-tracker-menu');
    if (menu) menu.classList.remove('open');

    if (!currentTrackedMetrics.includes(metricType)) {
        currentTrackedMetrics.push(metricType);
        saveTrackedMetrics();
    }
}

function removeTracker(metricType) {
    currentTrackedMetrics = currentTrackedMetrics.filter(m => m !== metricType);
    saveTrackedMetrics();
}

function saveTrackedMetrics() {
    const formData = new FormData();
    formData.append('action', 'update_tracked_metrics');
    formData.append('patient_id', '<?= (int)($patient['id'] ?? 0) ?>');
    currentTrackedMetrics.forEach(m => formData.append('metrics[]', m));

    fetch('api/emr_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderTrajectories();
            }
        });
}

function openLogReadingModal(metricType) {
    const modal = document.getElementById('emr-log-reading-modal');
    const titleEl = document.getElementById('log-reading-modal-title');
    const typeInput = document.getElementById('log-metric-type');
    const valInput = document.getElementById('log-reading-value');
    const valLabel = document.getElementById('log-reading-value-label');

    const conf = METRIC_CONFIG[metricType] || { title: metricType, unit: '', placeholder: '' };
    if (titleEl) titleEl.innerText = `Log ${conf.title} Reading`;
    if (typeInput) typeInput.value = metricType;
    if (valLabel) valLabel.innerText = `Reading Value (${conf.unit || 'units'})`;
    if (valInput) {
        valInput.value = '';
        valInput.placeholder = conf.placeholder || 'e.g. value';
    }

    if (modal) modal.classList.add('open');
    setTimeout(() => valInput && valInput.focus(), 60);
}

function closeLogReadingModal() {
    const modal = document.getElementById('emr-log-reading-modal');
    if (modal) modal.classList.remove('open');
}

// Form submit listener for log reading
const logReadingForm = document.getElementById('emr-log-reading-form');
if (logReadingForm) {
    logReadingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-log-reading');
        if (btn) btn.disabled = true;

        const formData = new FormData(this);
        fetch('api/emr_api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (btn) btn.disabled = false;
                if (data.success) {
                    closeLogReadingModal();
                    loadTrajectories();
                } else {
                    alert(data.message || 'Failed to save reading');
                }
            })
            .catch(err => {
                if (btn) btn.disabled = false;
                alert('Network error: ' + err.message);
            });
    });
}

// Auto-run loadTrajectories on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTrajectories();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
