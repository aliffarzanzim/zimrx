<?php
$zimrx_prescription_ob_level = ob_get_level();
ob_start();
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'visit_identity.php';

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
$extra_css = ['assets/css/paediatric_module.css'];
include 'header.php';
?>

    <?php
    // Include the patient particulars component.
    include 'modules/pres_particulars.php';
    ?>

<?php
// Central Central Module Configurations & Mappings
$default_left_layout = [
  "P/C", "AI Analyzer", "History", "P/E", "Dx", "Ix",
  "Plan", "Note", "O/H", "M/H", "Paediatric History", "Bangla Converter"
];

$default_right_layout = [
  "Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators",
  "Text Pad", "OT Note", "Font Format"
];

$module_file_map = [
  "P/C" => "p_c.php",
  "AI Analyzer" => "ai_analyzer.php",
  "History" => "history.php",
  "P/E" => "p_e.php",
  "O/E" => "p_e.php",
  "Dx" => "dx.php",
  "Ix" => "ix.php",
  "Plan" => "plan.php",
  "Note" => "note.php",
  "O/H" => "o_h.php",
  "M/H" => "m_h.php",
  "Paediatric History" => "paediatric_history.php",
  "Bangla Converter" => "bangla_converter.php",
  "Rx" => "rx.php",
  "Drug Summary & Interaction" => "drug_summary_interaction.php",
  "Advice" => "advice.php",
  "Report Entry" => "report_entry.php",
  "Upload Reports & Documents" => "uploaded_reports.php",
  "Uploaded Reports" => "uploaded_reports.php",
  "Reports" => "reports.php",
  "Calculators" => "calculators.php",
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

    <div class="app-container">
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="assets/js/dosage_form_icons.js?v=<?= filemtime(__DIR__ . '/assets/js/dosage_form_icons.js') ?>"></script>
    <script src="assets/js/layout/config.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/config.js') ?>"></script>
    <script src="assets/js/layout/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/dashboard.js') ?>"></script>
    <script src="assets/js/layout/rx_autocomplete.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/rx_autocomplete.js') ?>"></script>
    <script src="assets/js/layout/pc_autocomplete.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/pc_autocomplete.js') ?>"></script>
    <script src="assets/js/layout/o_h_module.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/o_h_module.js') ?>"></script>
    <script src="assets/js/layout/growth_chart_data.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/growth_chart_data.js') ?>"></script>
    <script src="assets/js/layout/paediatric_module.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/paediatric_module.js') ?>"></script>
    <script src="assets/js/layout/prescription_preview.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/prescription_preview.js') ?>"></script>
    <script src="assets/js/layout/ho_diet_dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/ho_diet_dropdown.js') ?>"></script>
    <script src="assets/js/layout/boot.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/boot.js') ?>"></script>



    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // ==========================================
            // 1. FLATPICKR
            // ==========================================
            flatpickr(".custom-date-picker", {
                dateFormat: "d/m/Y",
                allowInput: true,
                onChange: function(_, __, instance) {
                    instance.input.dispatchEvent(new Event('input', { bubbles: true }));
                    instance.input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // ==========================================
            // KEYBOARD & MOUSE DROPDOWN NAVIGATION
            // ==========================================
            let isKeyboardNav = false;
            let keyboardNavTimer = null;

            function setKeyboardNavMode() {
                isKeyboardNav = true;
                clearTimeout(keyboardNavTimer);
                keyboardNavTimer = setTimeout(() => {
                    isKeyboardNav = false;
                }, 200);
            }

            function updateActiveListItem(items, index, shouldScroll = true) {
                Array.from(items).forEach(item => item.classList.remove('active'));
                if (items[index]) {
                    items[index].classList.add('active');
                    if (shouldScroll) {
                        items[index].scrollIntoView({ block: 'nearest' });
                    }
                }
            }

            // ==========================================
            // 2. AGE / DOB CALCULATIONS
            // ==========================================
            const dobInput = document.getElementById('patient-dob');
            const ageInput = document.getElementById('patient-age');
            const ageUnit = document.getElementById('patient-age-unit');

            function setInputValue(field, value) {
                if (!field) return;
                field.value = value ?? '';
            }

            function setSelectValue(field, value) {
                if (!field) return;
                const normalized = String(value ?? '');
                const match = Array.from(field.options).find((option) => option.value === normalized);
                if (match || normalized === '') {
                    field.value = normalized;
                }
            }

            function formatIsoDateForInput(value) {
                const date = String(value || '').slice(0, 10);
                const match = date.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
            }

            function setDateValue(id, value) {
                const field = document.getElementById(id);
                if (!field || !value) return;
                const formatted = formatIsoDateForInput(value);
                if (field._flatpickr) {
                    field._flatpickr.setDate(formatted, false, 'd/m/Y');
                } else {
                    setInputValue(field, formatted);
                }
            }

            function calculateAge() {
                if (!dobInput.value) return;
                let parsedDate = null;
                if (dobInput._flatpickr && dobInput._flatpickr.selectedDates[0]) {
                    parsedDate = dobInput._flatpickr.selectedDates[0];
                } else if (dobInput.value.includes('/')) {
                    const parts = dobInput.value.split('/');
                    parsedDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
                } else {
                    parsedDate = new Date(dobInput.value);
                }
                const dob = parsedDate;
                const today = new Date();
                if (!dob || isNaN(dob.getTime())) return;

                let years = today.getFullYear() - dob.getFullYear();
                let months = today.getMonth() - dob.getMonth();
                let days = today.getDate() - dob.getDate();

                if (days < 0) { months--; days += new Date(today.getFullYear(), today.getMonth(), 0).getDate(); }
                if (months < 0) { years--; months += 12; }

                if (years > 0) {
                    ageInput.value = years;
                    ageUnit.value = 'Years';
                } else if (months > 0) {
                    ageInput.value = months;
                    ageUnit.value = 'Months';
                } else if (days >= 7) {
                    ageInput.value = Math.floor(days / 7);
                    ageUnit.value = 'Weeks';
                } else {
                    ageInput.value = Math.max(0, days);
                    ageUnit.value = 'Days';
                }
            }

            function calculateDOB() {
                if (!ageInput.value) return;
                const age = parseInt(ageInput.value) || 0;
                const unit = ageUnit.value;
                const dob = new Date();

                if (unit === 'Years') {
                    dob.setFullYear(dob.getFullYear() - age);
                    dob.setMonth(0);
                    dob.setDate(1);
                } else if (unit === 'Months') {
                    dob.setMonth(dob.getMonth() - age);
                    dob.setDate(1);
                } else if (unit === 'Weeks') {
                    dob.setDate(dob.getDate() - (age * 7));
                } else {
                    dob.setDate(dob.getDate() - age);
                }

                if (dobInput._flatpickr) {
                    dobInput._flatpickr.setDate(dob);
                } else {
                    const d = String(dob.getDate()).padStart(2, '0');
                    const m = String(dob.getMonth() + 1).padStart(2, '0');
                    const y = dob.getFullYear();
                    dobInput.value = `${d}/${m}/${y}`;
                }
            }

            dobInput.addEventListener('change', calculateAge);
            ageInput.addEventListener('input', calculateDOB);
            ageUnit.addEventListener('change', calculateDOB);

            // ==========================================
            // 3. PATIENT LOOKUP (REG NO / MOBILE)
            // ==========================================
            let lookupTimeout;
            function setupPatientLookup(inputId, listId, wrapId, paramName) {
                const input = document.getElementById(inputId);
                const list = document.getElementById(listId);
                const wrap = document.getElementById(wrapId);
                let activeIdx = -1;

                function close() { list.classList.remove('show'); wrap.classList.remove('open'); activeIdx = -1; }

                input.addEventListener('input', (e) => {
                    const idField = document.getElementById('patient-id');
                    if (idField && idField.value) {
                        idField.value = '';
                        syncPatientProfileButton();
                    }
                    clearTimeout(lookupTimeout);
                    const val = e.target.value.trim();
                    if (val.length < 1) { close(); return; }

                    lookupTimeout = setTimeout(async () => {
                        try {
                            const res = await fetch(`api/appointments.php?action=patient_lookup&${paramName}=${encodeURIComponent(val)}`);
                            const data = await res.json();

                            list.innerHTML = '';

                            if (data.patients && data.patients.length > 0) {
                                data.patients.forEach((p, i) => {
                                    const li = document.createElement('li');
                                    li.className = 'patient-lookup-option';
                                    const codeLabel = paramName === 'mobile' ? (p.mobile || val || '') : (p.reg_no || '');
                                    const subText = paramName === 'mobile' ? (p.reg_no || '') : (p.mobile || 'No Phone');
                                    const addressLabel = p.address ? p.address : 'No Address';
                                    const metaText = subText ? `${subText} | ${addressLabel}` : addressLabel;
                                    li.innerHTML = `<div class="patient-lookup-code">${codeLabel}</div><strong class="patient-lookup-name">${p.full_name || p.patient_name || 'No Name'}</strong><span class="patient-lookup-meta">${metaText}</span>`;
                                    li.addEventListener('mousemove', () => {
                                        isKeyboardNav = false;
                                        const allItems = list.querySelectorAll('li.patient-lookup-option');
                                        const idx = Array.from(allItems).indexOf(li);
                                        if (activeIdx !== idx) {
                                            activeIdx = idx;
                                            updateActiveListItem(allItems, activeIdx, false);
                                        }
                                    });
                                    li.addEventListener('mouseenter', () => {
                                        if (isKeyboardNav) return;
                                        const allItems = list.querySelectorAll('li.patient-lookup-option');
                                        const idx = Array.from(allItems).indexOf(li);
                                        if (activeIdx !== idx) {
                                            activeIdx = idx;
                                            updateActiveListItem(allItems, activeIdx, false);
                                        }
                                    });
                                    li.addEventListener('mousedown', (ev) => {
                                        ev.preventDefault();
                                        selectPatient(p);
                                        close();
                                    });
                                    list.appendChild(li);
                                });
                            }

                            if (paramName === 'mobile') {
                                const li = document.createElement('li');
                                li.className = 'patient-lookup-option new-patient-option';
                                li.innerHTML = `<strong>+ It's a new patient</strong><span>Use ${val}</span>`;
                                li.addEventListener('mousemove', () => {
                                    isKeyboardNav = false;
                                    const allItems = list.querySelectorAll('li.patient-lookup-option');
                                    const idx = Array.from(allItems).indexOf(li);
                                    if (activeIdx !== idx) {
                                        activeIdx = idx;
                                        updateActiveListItem(allItems, activeIdx, false);
                                    }
                                });
                                li.addEventListener('mouseenter', () => {
                                    if (isKeyboardNav) return;
                                    const allItems = list.querySelectorAll('li.patient-lookup-option');
                                    const idx = Array.from(allItems).indexOf(li);
                                    if (activeIdx !== idx) {
                                        activeIdx = idx;
                                        updateActiveListItem(allItems, activeIdx, false);
                                    }
                                });
                                li.addEventListener('mousedown', (ev) => {
                                    ev.preventDefault();
                                    document.getElementById('patient-mobile').value = val;
                                    document.getElementById('patient-reg-no').value = '';
                                    document.getElementById('patient-id').value = '';
                                    if (typeof syncPatientProfileButton === 'function') syncPatientProfileButton();
                                    close();
                                });
                                list.appendChild(li);
                            }

                            if (!list.children.length) {
                                close();
                                return;
                            }

                            if (list.firstElementChild) {
                                list.firstElementChild.classList.add('active');
                                activeIdx = 0;
                            }

                            list.classList.add('show');
                            wrap.classList.add('open');
                        } catch (err) { console.error(err); }
                    }, 300);
                });

                input.addEventListener('blur', close);
                input.addEventListener('change', async () => {
                    const val = input.value.trim();
                    if (!val || document.getElementById('patient-id')?.value) return;
                    if (paramName === 'reg_no') {
                        try {
                            const res = await fetch(`api/appointments.php?action=patient_lookup&reg_no=${encodeURIComponent(val)}`);
                            const data = await res.json();
                            if (data.patients && data.patients.length > 0) {
                                const exact = data.patients.find(p => String(p.reg_no || '').toUpperCase() === val.toUpperCase());
                                if (exact) {
                                    selectPatient(exact);
                                    close();
                                }
                            }
                        } catch (err) { console.error(err); }
                    }
                });
                input.addEventListener('keydown', (e) => {
                    const items = list.querySelectorAll('li.patient-lookup-option');
                    if (!list.classList.contains('show') || items.length === 0) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setKeyboardNavMode();
                        activeIdx = (activeIdx + 1) % items.length;
                        updateActiveListItem(items, activeIdx, true);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setKeyboardNavMode();
                        activeIdx = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
                        updateActiveListItem(items, activeIdx, true);
                    } else if (e.key === 'Enter') {
                        if (activeIdx > -1 && items[activeIdx]) {
                            e.preventDefault();
                            items[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
                        }
                    } else if (e.key === 'Escape') {
                        close();
                    }
                });
            }

            function selectPatient(p) {
                document.getElementById('patient-id').value = p.id || '';
                document.getElementById('patient-reg-no').value = p.reg_no || '';
                document.getElementById('patient-name').value = p.full_name || '';
                document.getElementById('patient-age').value = p.age || '';
                if (p.age_unit) document.getElementById('patient-age-unit').value = p.age_unit;

                const dobEl = document.getElementById('patient-dob');
                if (p.dob) {
                    if (dobEl._flatpickr) dobEl._flatpickr.setDate(p.dob);
                    else {
                        const parts = p.dob.split('-');
                        dobEl.value = parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : p.dob;
                    }
                    if (typeof calculateAge === 'function') calculateAge();
                } else {
                    dobEl.value = '';
                }
                if (p.gender) document.getElementById('patient-gender').value = p.gender;
                if (p.blood_group) document.getElementById('patient-blood-group').value = p.blood_group;
                document.getElementById('patient-mobile').value = p.mobile || '';
                document.getElementById('patient-address').value = p.address || '';
                document.getElementById('patient-occupation').value = p.occupation || '';
                document.getElementById('patient-weight').value = p.weight || '';
                if (p.weight_unit) document.getElementById('patient-weight-unit').value = p.weight_unit;
                document.getElementById('patient-height').value = p.height || '';
                if (p.height_unit) document.getElementById('patient-height-unit').value = p.height_unit;
                document.getElementById('visit-no').value = p.next_visit_no || '';
                document.getElementById('visit-code').value = p.next_visit_code || '';
                const refType = document.getElementById('patient-ref-type');
                const refBy = document.getElementById('patient-ref-by');
                if (refType) refType.value = 'Self';
                if (refBy) refBy.value = '';
                if (typeof loadPreviousPatientReferralOptions === 'function') loadPreviousPatientReferralOptions();
                if (typeof syncPatientReferredByControl === 'function') syncPatientReferredByControl();
                syncPatientProfileButton();
            }

            function syncPatientProfileButton() {
                const btn = document.getElementById('btn-open-patient-profile');
                if (!btn) return;
                const patientId = parseInt(document.getElementById('patient-id')?.value || '0', 10);
                const hasPatient = Number.isInteger(patientId) && patientId > 0;
                btn.disabled = !hasPatient;
                btn.classList.toggle('disabled', !hasPatient);
            }

            const profileBtn = document.getElementById('btn-open-patient-profile');
            if (profileBtn) {
                profileBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const patientId = parseInt(document.getElementById('patient-id')?.value || '0', 10);
                    if (patientId > 0) {
                        window.open(`emr.php?patient_id=${encodeURIComponent(patientId)}`, '_blank');
                    }
                });
            }

            syncPatientProfileButton();

            setupPatientLookup('patient-reg-no', 'reg-list', 'reg-wrapper', 'reg_no');
            setupPatientLookup('patient-mobile', 'mobile-list', 'mobile-wrapper', 'mobile');

            function referralSelectValue(category) {
                const key = String(category || '').trim().toLowerCase().replace(/[-\s]+/g, '_');
                if (key === 'doctor') return 'Doctor';
                if (key === 'others') return 'Others';
                if (key === 'other_patient') return 'Other Patient';
                return 'Self';
            }

            function applyAppointmentToForm(appointment) {
                if (!appointment || typeof appointment !== 'object') return;
                const has = (key) => Object.prototype.hasOwnProperty.call(appointment, key);
                const fillInput = (key, id) => {
                    if (has(key)) setInputValue(document.getElementById(id), appointment[key] || '');
                };
                const fillSelect = (key, id, fallback = '') => {
                    if (has(key)) setSelectValue(document.getElementById(id), appointment[key] || fallback);
                };
                const fillDate = (key, id) => {
                    if (has(key)) setDateValue(id, appointment[key] || '');
                };

                fillInput('id', 'appointment-id');
                fillInput('appointment_no', 'appointment-no');
                fillInput('appointment_time', 'appointment-time');
                fillInput('patient_id', 'patient-id');
                fillInput('reg_no', 'patient-reg-no');
                fillInput('patient_name', 'patient-name');
                fillInput('age', 'patient-age');
                fillSelect('age_unit', 'patient-age-unit', 'Years');
                fillDate('dob', 'patient-dob');
                fillSelect('gender', 'patient-gender');
                fillSelect('blood_group', 'patient-blood-group');
                fillInput('mobile', 'patient-mobile');
                fillInput('occupation', 'patient-occupation');
                fillInput('address', 'patient-address');
                fillInput('weight', 'patient-weight');
                fillSelect('weight_unit', 'patient-weight-unit', 'kg');
                fillInput('height', 'patient-height');
                fillSelect('height_unit', 'patient-height-unit', 'inch');
                fillInput('visit_no', 'visit-no');
                fillInput('visit_code', 'visit-code');
                fillDate('appointment_date', 'patient-date');

                const refType = document.getElementById('patient-ref-type');
                const refBy = document.getElementById('patient-ref-by');
                if (has('referral_category')) setSelectValue(refType, referralSelectValue(appointment.referral_category));
                if (refBy && has('referral_name')) refBy.value = appointment.referral_name || '';
                if (typeof loadPreviousPatientReferralOptions === 'function') loadPreviousPatientReferralOptions();
                if (typeof syncPatientReferredByControl === 'function') syncPatientReferredByControl();
                if (typeof syncPatientProfileButton === 'function') syncPatientProfileButton();
            }

            function applyQueryFallbackToForm() {
                const params = new URLSearchParams(window.location.search || '');
                if (!params.get('appointment_id') && !params.get('patient_id') && !params.get('reg_no')) {
                    return;
                }

                applyAppointmentToForm({
                    id: params.get('appointment_id') || '',
                    patient_id: params.get('patient_id') || '',
                    reg_no: params.get('reg_no') || '',
                    visit_no: params.get('visit_no') || '',
                    visit_code: params.get('visit_code') || '',
                    referral_category: params.get('referral_category') || 'self',
                    referral_name: params.get('referral_name') || ''
                });
            }

            async function loadAppointmentFromQuery() {
                const params = new URLSearchParams(window.location.search || '');
                const appointmentId = params.get('appointment_id') || '';
                if (!appointmentId) {
                    return;
                }

                try {
                    const response = await fetch(`api/appointments.php?action=appointment_detail&id=${encodeURIComponent(appointmentId)}`);
                    const data = await response.json();
                    if (data.appointment) {
                        applyAppointmentToForm(data.appointment);
                    }
                } catch (err) {
                    console.error('Could not load appointment for prescription.', err);
                }
            }

            applyQueryFallbackToForm();
            loadAppointmentFromQuery();

            // ==========================================
            // 4. OCCUPATION AUTOCOMPLETE (SQLite)
            // ==========================================
            let occupationsArray = [];
            let occFocus = -1;
            const occInput = document.getElementById('patient-occupation');
            const occList = document.getElementById('occupation-list');
            const occWrapper = document.getElementById('occ-wrapper');

            function fetchOccupations() {
                fetch('api/get_occupations.php')
                    .then(res => res.json())
                    .then(data => { if(Array.isArray(data)) occupationsArray = data; })
                    .catch(err => console.error('Error fetching occupations:', err));
            }
            window.refreshOccupations = fetchOccupations;
            fetchOccupations();

            function closeOccList() {
                occList.classList.remove('show');
                occWrapper.classList.remove('open');
                occFocus = -1;
            }

            function renderOccList(filterText = '') {
                occList.innerHTML = '';
                const q = (filterText || '').toLowerCase().trim();
                const filtered = occupationsArray.filter(occ => {
                    const name = typeof occ === 'string' ? occ : (occ.name || '');
                    return name.toLowerCase().includes(q);
                });

                if (filtered.length === 0) { closeOccList(); return; }

                filtered.forEach((occ, index) => {
                    const name = typeof occ === 'string' ? occ : (occ.name || '');
                    const isPinned = typeof occ === 'object' && Number(occ.is_pinned) === 1;
                    const li = document.createElement('li');
                    li.style.position = 'relative';

                    if (isPinned) {
                        li.innerHTML = `<img class="rx-dropdown-pin" src="assets/images/pin.svg" alt="Pinned">${name}`;
                    } else {
                        li.textContent = name;
                    }

                    if (index === 0) li.classList.add('active');

                    li.addEventListener('mouseenter', () => {
                        const allItems = occList.getElementsByTagName('li');
                        occFocus = Array.from(allItems).indexOf(li);
                        updateActiveListItem(allItems, occFocus);
                    });

                    li.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        occInput.value = name;
                        closeOccList();
                    });
                    occList.appendChild(li);
                });

                occFocus = 0;
                occList.classList.add('show');
                occWrapper.classList.add('open');
            }

            occInput.addEventListener('click', () => renderOccList(occInput.value));
            occInput.addEventListener('input', (e) => renderOccList(e.target.value));
            occInput.addEventListener('blur', closeOccList);

            occInput.addEventListener('keydown', (e) => {
                let items = occList.getElementsByTagName('li');
                if (!occList.classList.contains('show')) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    occFocus++;
                    if (occFocus >= items.length) occFocus = 0;
                    updateActiveListItem(items, occFocus);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    occFocus--;
                    if (occFocus < 0) occFocus = items.length - 1;
                    updateActiveListItem(items, occFocus);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (occFocus > -1 && items[occFocus]) items[occFocus].dispatchEvent(new MouseEvent('mousedown'));
                } else if (e.key === 'Escape') {
                    closeOccList();
                }
            });

            // ==========================================
            // 5. SMART ADDRESS AUTOCOMPLETE
            // ==========================================
            let addrFocus = -1;
            const addrInput = document.getElementById('patient-address');
            const addrList = document.getElementById('address-list');
            const addrWrapper = document.getElementById('address-wrapper');
            let addrTimeout = null;

            function closeAddrList() {
                addrList.classList.remove('show');
                addrWrapper.classList.remove('open');
                addrFocus = -1;
            }

            function getCurrentSegmentData(inputElem) {
                const text = inputElem.value;
                const cursorPos = inputElem.selectionStart;
                const textBeforeCursor = text.substring(0, cursorPos);
                const segmentIndex = (textBeforeCursor.match(/,/g) || []).length;
                const parts = text.split(',');
                let currentWord = parts[segmentIndex] ? parts[segmentIndex].trim() : '';
                let previousWord = (segmentIndex > 0 && parts[segmentIndex - 1]) ? parts[segmentIndex - 1].trim() : '';
                return { text, segmentIndex, currentWord, previousWord, parts };
            }

            addrInput.addEventListener('input', (e) => {
                clearTimeout(addrTimeout);
                const { currentWord, previousWord, segmentIndex } = getCurrentSegmentData(addrInput);

                if (currentWord.length < 1 && previousWord === '') {
                    closeAddrList();
                    return;
                }

                addrTimeout = setTimeout(() => {
                    const url = `api/search_address.php?q=${encodeURIComponent(currentWord)}&segment=${segmentIndex}&prev=${encodeURIComponent(previousWord)}`;

                    fetch(url).then(res => res.json()).then(data => {
                        addrList.innerHTML = '';
                        if (data.length === 0 || data.error) { closeAddrList(); return; }

                        data.forEach((suggestion, index) => {
                            const li = document.createElement('li');
                            li.textContent = suggestion;
                            if (index === 0) li.classList.add('active');

                            li.addEventListener('mouseenter', () => {
                                const allItems = addrList.getElementsByTagName('li');
                                addrFocus = Array.from(allItems).indexOf(li);
                                updateActiveListItem(allItems, addrFocus);
                            });

                            li.addEventListener('mousedown', (e) => {
                                e.preventDefault();
                                if (suggestion.includes(',')) {
                                    addrInput.value = suggestion;
                                } else {
                                    const { parts, segmentIndex } = getCurrentSegmentData(addrInput);
                                    parts[segmentIndex] = (segmentIndex === 0 ? "" : " ") + suggestion;
                                    addrInput.value = parts.join(',');
                                }
                                addrInput.focus();
                                closeAddrList();
                            });
                            addrList.appendChild(li);
                        });

                        addrFocus = 0;
                        addrList.classList.add('show');
                        addrWrapper.classList.add('open');
                    });
                }, 200);
            });

            addrInput.addEventListener('blur', closeAddrList);
            addrInput.addEventListener('keydown', (e) => {
                let items = addrList.getElementsByTagName('li');
                if (!addrList.classList.contains('show')) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    addrFocus++;
                    if (addrFocus >= items.length) addrFocus = 0;
                    updateActiveListItem(items, addrFocus);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    addrFocus--;
                    if (addrFocus < 0) addrFocus = items.length - 1;
                    updateActiveListItem(items, addrFocus);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (addrFocus > -1 && items[addrFocus]) items[addrFocus].dispatchEvent(new MouseEvent('mousedown'));
                } else if (e.key === 'Escape') {
                    closeAddrList();
                }
            });

            // ==========================================
            // 6. SAVING LOGIC
            // ==========================================
            async function learnRxRegimensFromRows(drugs) {
                if (!Array.isArray(drugs) || drugs.length === 0) {
                    return;
                }

                try {
                    await fetch('api/rx_learn.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ drugs }),
                        keepalive: true
                    });
                } catch (error) {
                    console.error('Could not learn Rx regimens', error);
                }
            }

            async function savePrescription(printAfter = false) {
                const refPayload = typeof getPatientReferralPayload === 'function' ? getPatientReferralPayload() : { category: 'self', name: '' };
                const patientData = {
                    action: 'create',
                    source: 'prescription',
                    status: 'Done', // Auto-complete appointment
                    id: document.getElementById('appointment-id').value,
                    appointment_date: document.getElementById('patient-date').value,
                    appointment_no: document.getElementById('appointment-no').value || 0,
                    appointment_time: document.getElementById('appointment-time').value || '',
                    patient_id: document.getElementById('patient-id').value,
                    reg_no: document.getElementById('patient-reg-no').value,
                    patient_name: document.getElementById('patient-name').value,
                    age: document.getElementById('patient-age').value,
                    age_unit: document.getElementById('patient-age-unit').value,
                    dob: document.getElementById('patient-dob').value,
                    gender: document.getElementById('patient-gender').value,
                    blood_group: document.getElementById('patient-blood-group').value,
                    mobile: document.getElementById('patient-mobile').value,
                    occupation: document.getElementById('patient-occupation').value,
                    address: document.getElementById('patient-address').value,
                    weight: document.getElementById('patient-weight').value,
                    weight_unit: document.getElementById('patient-weight-unit').value,
                    height: document.getElementById('patient-height').value,
                    height_unit: document.getElementById('patient-height-unit').value,
                    referral_category: refPayload.category || 'self',
                    referral_name: refPayload.name || '',
                    visit_no: document.getElementById('visit-no').value,
                    visit_code: document.getElementById('visit-code').value,
                };

                if (!patientData.patient_name) {
                    const nameInput = document.getElementById('patient-name');
                    if (typeof zrxShowFieldValidation === 'function') {
                        zrxShowFieldValidation(nameInput, 'Please fill out this field.');
                    } else if (nameInput) {
                        nameInput.focus();
                    }
                    return;
                }

                try {
                    // 1. Create/Update Patient & Appointment
                    const apptRes = await fetch('api/appointments.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(patientData)
                    });
                    const apptData = await apptRes.json();
                    if (apptData.error) throw new Error(apptData.error);

                    document.getElementById('patient-id').value = apptData.patient.id;
                    document.getElementById('patient-reg-no').value = apptData.patient.reg_no;
                    document.getElementById('appointment-id').value = apptData.id;
                    if (apptData.appointment_no) {
                        document.getElementById('appointment-no').value = apptData.appointment_no;
                    }
                    if (typeof syncPatientProfileButton === 'function') syncPatientProfileButton();

                    // 2. Extract Drugs
                    const drugs = [];
                    document.querySelectorAll('#rx-tbody tr').forEach(tr => {
                        const brand = tr.querySelector('.rx-brand-input')?.value;
                        const generic = tr.querySelector('.rx-generic-input')?.value;
                        const dose = tr.querySelector('.rx-dose-input')?.value;
                        const instruction = tr.querySelector('.rx-instruction-input')?.value;
                        const duration = tr.querySelector('.rx-duration-input')?.value;
                        const brandId = tr.querySelector('.brand_id')?.value;

                        if (brand || dose || instruction || duration) {
                            drugs.push({ brand, generic, dose, instruction, duration, brand_id: brandId });
                        }
                    });

                    // 3. Save Visit Link with full clinical snapshot & rendered preview HTML
                    let clinicalSnapshot = null;
                    if (typeof collectPrescriptionPreviewSnapshot === 'function') {
                        clinicalSnapshot = collectPrescriptionPreviewSnapshot();
                    }
                    let previewHtml = '';
                    const previewElem = document.getElementById('prescription-preview-content') || document.querySelector('.zrx-preview-paper');
                    if (previewElem) {
                        previewHtml = previewElem.outerHTML || previewElem.innerHTML || '';
                    }

                    const visitData = {
                        appointment_id: document.getElementById('appointment-id').value || apptData.id,
                        patient_id: apptData.patient.id,
                        reg_no: apptData.patient.reg_no,
                        visit_no: document.getElementById('visit-no').value || apptData.patient.next_visit_no,
                        visit_code: document.getElementById('visit-code').value || apptData.patient.next_visit_code,
                        referral: typeof getPatientReferralPayload === 'function' ? getPatientReferralPayload() : { category: 'self', name: '' },
                        drugs: drugs,
                        clinical_snapshot: clinicalSnapshot,
                        prescription_html: previewHtml
                    };

                    const visitRes = await fetch('api/save_prescription_visit.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(visitData)
                    });
                    const visitResult = await visitRes.json();
                    if (visitResult.error) throw new Error(visitResult.error);

                    if (visitResult.visit_no) document.getElementById('visit-no').value = visitResult.visit_no;
                    if (visitResult.visit_code) document.getElementById('visit-code').value = visitResult.visit_code;

                    await learnRxRegimensFromRows(drugs);

                    if (typeof learnCurrentPcAutocompletes === 'function') {
                        learnCurrentPcAutocompletes();
                    }

                    // 4. Learn new address segments
                    const addressVal = document.getElementById('patient-address').value.trim();
                    const cleanAddress = addressVal.replace(/,\s*$/, "");
                    if (cleanAddress) {
                        fetch('api/save_custom_address.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ address: cleanAddress })
                        }).catch(console.error);
                    }

                    // 5. Learn custom occupation
                    const occVal = document.getElementById('patient-occupation')?.value?.trim();
                    if (occVal) {
                        fetch('api/save_custom_occupation.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ occupation: occVal })
                        }).catch(console.error);
                    }

                    if (printAfter) {
                        window.open('prescription_preview.php', '_blank');
                    } else {
                        alert('Prescription Saved Successfully!');
                    }

                } catch (e) {
                    alert('Error: ' + e.message);
                }
            }

            function dispatchFieldChange(field) {
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function setFieldValue(field, value, options = {}) {
                field.value = value;
                if (options.dispatch === true) {
                    dispatchFieldChange(field);
                }
            }

            function clearFieldControl(field, options = {}) {
                if (!field || field.disabled) return;
                const type = (field.type || '').toLowerCase();

                if (type === 'checkbox' || type === 'radio') {
                    field.checked = false;
                    if (options.dispatch === true) {
                        dispatchFieldChange(field);
                    }
                    return;
                }

                if (field.tagName === 'SELECT') {
                    field.selectedIndex = 0;
                    if (options.dispatch === true) {
                        dispatchFieldChange(field);
                    }
                    return;
                }

                setFieldValue(field, '', options);
            }

            function closePrescriptionPopups() {
                if (typeof closeOccList === 'function') closeOccList();
                if (typeof closeAddrList === 'function') {
                    window.clearTimeout(addrTimeout);
                    closeAddrList();
                }
                document.querySelectorAll('.autocomplete-list.show, .appointment-lookup-list.show').forEach((list) => {
                    list.classList.remove('show');
                });
                document.querySelectorAll('.autocomplete-wrapper.open').forEach((wrapper) => {
                    wrapper.classList.remove('open');
                });
                document.querySelectorAll('.rx-dropdown').forEach((dropdown) => {
                    dropdown.remove();
                });
                document.querySelectorAll('.ho-diet-dropdown-menu').forEach((menu) => {
                    menu.style.display = 'none';
                });
                document.querySelectorAll('.nav-dropdown[open], .rx-warning-dropdown[open]').forEach((details) => {
                    details.open = false;
                });
                const active = document.activeElement;
                if (active && active.matches('input, textarea, select')) {
                    active.blur();
                }
            }

            function shouldSkipPrescriptionClear(field) {
                return Boolean(
                    field.closest('.patient-particulars') ||
                    field.closest('template') ||
                    field.closest('.rx-settings-modal, .pc-settings-modal, #ai-settings-panel, .tp-sub-options, .ot-print-options') ||
                    field.closest('[class*="settings"]') ||
                    field.closest('#pe-tbody')
                );
            }

            function clearPrescriptionOnly() {
                const root = document.querySelector('.app-container');
                if (!root) return;

                window.dispatchEvent(new CustomEvent('zimrx:clear-prescription-ui'));

                root.querySelectorAll('input, textarea, select').forEach((field) => {
                    if (shouldSkipPrescriptionClear(field)) return;
                    clearFieldControl(field);
                    if (field.dataset) {
                        Object.keys(field.dataset).forEach((key) => delete field.dataset[key]);
                    }
                    field.style.height = '';
                });

                document.querySelectorAll('#pe-tbody tr').forEach((row) => {
                    row.querySelectorAll('td:nth-child(3) input, td:nth-child(3) textarea').forEach(clearFieldControl);
                });

                document.querySelectorAll('#rx-tbody tr').forEach((row) => {
                    delete row.dataset.selectedDrug;
                    row.querySelectorAll('.rx-input, .brand_id').forEach((field) => {
                        clearFieldControl(field);
                        if (field.dataset) {
                            Object.keys(field.dataset).forEach((key) => delete field.dataset[key]);
                        }
                    });
                });

                const rxInfoBar = document.getElementById('rx-info-bar');
                if (rxInfoBar) {
                    rxInfoBar.innerHTML = '<div class="rx-info-empty">Drug details and selected warnings will appear here. Interaction display is controlled from Rx settings.</div>';
                    rxInfoBar.dataset.brandId = '';
                    rxInfoBar.dataset.brandName = '';
                    rxInfoBar.title = 'Drug details will appear here';
                }

                const uploadTbody = document.getElementById('reports-upload-tbody');
                if (uploadTbody) uploadTbody.innerHTML = '';
                const uploadContainer = document.getElementById('reports-upload-table-container');
                if (uploadContainer) uploadContainer.style.display = 'none';
                const reportFileInput = document.getElementById('report-file-input');
                if (reportFileInput) reportFileInput.value = '';

                document.querySelectorAll('.nicEdit-main, [contenteditable="true"]').forEach((editor) => {
                    if (editor.closest('[class*="settings"]')) return;
                    editor.innerHTML = '';
                    editor.dispatchEvent(new Event('input', { bubbles: true }));
                });

                ['zimrx_preview_snapshot', 'zimrx_preview_drugs'].forEach((key) => {
                    sessionStorage.removeItem(key);
                    localStorage.removeItem(key);
                });

                closePrescriptionPopups();
                window.setTimeout(closePrescriptionPopups, 0);
                window.setTimeout(closePrescriptionPopups, 300);
            }

            function todayDmy() {
                const now = new Date();
                const d = String(now.getDate()).padStart(2, '0');
                const m = String(now.getMonth() + 1).padStart(2, '0');
                return `${d}/${m}/${now.getFullYear()}`;
            }

            function clearPatientParticulars() {
                document.querySelectorAll('.patient-particulars input[type="text"], .patient-particulars input[type="number"], .patient-particulars input[type="hidden"]').forEach((field) => {
                    setFieldValue(field, '');
                });
                document.querySelectorAll('.patient-particulars select').forEach((field) => {
                    field.selectedIndex = 0;
                });
                const dateField = document.getElementById('patient-date');
                if (dateField) {
                    if (dateField._flatpickr) dateField._flatpickr.setDate(todayDmy(), false, 'd/m/Y');
                    else setFieldValue(dateField, todayDmy());
                }
                const refType = document.getElementById('patient-ref-type');
                if (refType && typeof removePreviousPatientReferralOptions === 'function') removePreviousPatientReferralOptions(refType);
                if (typeof syncPatientReferredByControl === 'function') syncPatientReferredByControl();
                if (typeof fetchNextRegNo === 'function') fetchNextRegNo();
                if (typeof syncPatientProfileButton === 'function') syncPatientProfileButton();
                closePrescriptionPopups();
                window.setTimeout(closePrescriptionPopups, 0);
                window.setTimeout(closePrescriptionPopups, 300);
            }

            const clearBtn = document.getElementById('btn-clear-fields');
            const clearMenu = document.getElementById('clear-options-menu');

            function closeClearMenu() {
                if (!clearMenu || !clearBtn) return;
                clearMenu.hidden = true;
                clearBtn.setAttribute('aria-expanded', 'false');
            }

            function toggleClearMenu() {
                if (!clearMenu || !clearBtn) return;
                clearMenu.hidden = !clearMenu.hidden;
                clearBtn.setAttribute('aria-expanded', clearMenu.hidden ? 'false' : 'true');
            }

            document.getElementById('btn-save-print').addEventListener('click', (e) => { e.preventDefault(); savePrescription(true); });
            document.getElementById('btn-save-only').addEventListener('click', (e) => { e.preventDefault(); savePrescription(false); });
            clearBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleClearMenu();
            });
            clearMenu?.addEventListener('click', (e) => {
                const action = e.target.closest('[data-clear-action]')?.dataset.clearAction;
                if (!action) return;
                e.preventDefault();
                closeClearMenu();
                if (action === 'all') {
                    clearPrescriptionOnly();
                    clearPatientParticulars();
                    return;
                }
                clearPrescriptionOnly();
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.clear-action-wrap')) {
                    closeClearMenu();
                }
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeClearMenu();
            });

        });
    </script>
    <script>
        (function () {
            let revealed = false;

            function revealPrescriptionPage() {
                if (revealed || !document.body) {
                    return;
                }

                revealed = true;
                requestAnimationFrame(function () {
                    document.body.classList.add('zimrx-prescription-ready');
                });
            }

            if (document.readyState === 'complete') {
                revealPrescriptionPage();
            } else {
                window.addEventListener('load', revealPrescriptionPage, { once: true });
                window.setTimeout(revealPrescriptionPage, 2500);
            }
        })();
    </script>
<?php include 'footer.php'; ?>
<?php
if (ob_get_level() > $zimrx_prescription_ob_level) {
    ob_end_flush();
}
?>
