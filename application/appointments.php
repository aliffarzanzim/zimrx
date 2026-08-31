<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'visit_identity.php';

zimrx_ensure_visit_identity_schema($pdo);

$appointmentDoctorOptions = zimrx_doctor_options_for_user($pdo, current_user_id(), current_user_role(), current_user_doctor_id());
$initialDoctorId = current_user_role() === 'assistant' && count($appointmentDoctorOptions) !== 1
    ? 0
    : (int)($appointmentDoctorOptions[0]['id'] ?? current_user_doctor_id());
$initialDoctorName = '';
foreach ($appointmentDoctorOptions as $doctorOption) {
    if ((int)$doctorOption['id'] === $initialDoctorId) {
        $initialDoctorName = (string)$doctorOption['display_name'];
        break;
    }
}

function appointment_page_dmy_to_iso(string $date): string {
    $date = trim($date);
    $dt = DateTime::createFromFormat('d/m/Y', $date);
    if ($dt instanceof DateTime) {
        return $dt->format('Y-m-d');
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTime ? $dt->format('Y-m-d') : date('Y-m-d');
}

function appointment_page_iso_to_dmy(string $date): string {
    $dt = DateTime::createFromFormat('Y-m-d', trim($date));
    return $dt instanceof DateTime ? $dt->format('d/m/Y') : date('d/m/Y');
}

function appointment_page_h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function appointment_page_money($value): string {
    $amount = is_numeric($value) ? (float)$value : 0.0;
    return fmod($amount, 1.0) === 0.0 ? number_format($amount, 0, '.', '') : number_format($amount, 2, '.', '');
}

function appointment_page_settings(PDO $pdo, int $doctorId): array {
    $defaults = [
        'default_start_time' => '14:00',
        'minutes_per_patient' => 5,
        'blank_slots' => 3,
        'visit_fee' => 500,
        'revisit_fee' => 400,
        'revisit_validity_days' => 60,
        'weekday_overrides' => [],
    ];

    try {
        $stmt = $pdo->prepare(
            "SELECT settings_json
             FROM zimrx_appointment_settings
             WHERE doctor_id = :doctor_id
             LIMIT 1"
        );
        $stmt->execute(['doctor_id' => $doctorId]);
        $stored = json_decode((string)($stmt->fetchColumn() ?: ''), true);
        if (is_array($stored)) {
            $defaults = array_merge($defaults, $stored);
        }
    } catch (Throwable $e) {
    }

    $defaults['minutes_per_patient'] = max(1, (int)($defaults['minutes_per_patient'] ?? 5));
    $defaults['blank_slots'] = max(0, (int)($defaults['blank_slots'] ?? 3));
    $defaults['visit_fee'] = max(0, (float)($defaults['visit_fee'] ?? 500));
    $defaults['revisit_fee'] = max(0, (float)($defaults['revisit_fee'] ?? 400));
    $defaults['revisit_validity_days'] = max(0, (int)($defaults['revisit_validity_days'] ?? 60));
    $defaults['weekday_overrides'] = is_array($defaults['weekday_overrides'] ?? null) ? $defaults['weekday_overrides'] : [];
    return $defaults;
}

function appointment_page_day_rule(array $settings, string $date): array {
    $dt = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
    $weekday = (string)(int)$dt->format('w');
    $rule = is_array($settings['weekday_overrides'][$weekday] ?? null) ? $settings['weekday_overrides'][$weekday] : [];
    $startTime = trim((string)($rule['start_time'] ?? '')) ?: (string)$settings['default_start_time'];
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        $startTime = '14:00';
    }
    return ['closed' => !empty($rule['closed']), 'start_time' => $startTime];
}

function appointment_page_calculate_time(int $appointmentNo, string $date, array $settings): string {
    $rule = appointment_page_day_rule($settings, $date);
    if ($rule['closed']) {
        return '';
    }
    $position = max(0, $appointmentNo - (int)$settings['blank_slots'] - 1);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $rule['start_time']);
    if (!$dt) {
        return '';
    }
    if ($position > 0) {
        $dt->modify('+' . ($position * (int)$settings['minutes_per_patient']) . ' minutes');
    }
    return $dt->format('H:i');
}

function appointment_page_next_no(PDO $pdo, string $date, array $settings, int $doctorId): int {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(appointment_no), 0) + 1
         FROM zimrx_appointments
         WHERE doctor_id = :doctor_id AND appointment_date = :date"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'date' => $date]);
    return max((int)$stmt->fetchColumn(), (int)$settings['blank_slots'] + 1, 1);
}

function appointment_page_last_visit_label(?string $lastVisitDate, string $appointmentDate): string {
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
    return $days >= 0 ? $formatted . ' (' . $days . ' Days ago)' : $formatted;
}

function appointment_page_last_visit_html(string $label): string {
    if (preg_match('/^(.+?)\s+(\(.+\))$/', $label, $matches)) {
        return appointment_page_h($matches[1]) . '<span>' . appointment_page_h($matches[2]) . '</span>';
    }
    return appointment_page_h($label);
}

function appointment_page_revisit_discount(array $row, array $settings, string $date): float {
    if (($row['visit_fee'] ?? '') !== '') {
        return max(0, (float)($row['discount'] ?? 0));
    }
    $lastVisitDate = (string)($row['last_visit_date'] ?? '');
    $last = $lastVisitDate !== ''
        ? (DateTime::createFromFormat('Y-m-d H:i:s', $lastVisitDate) ?: DateTime::createFromFormat('Y-m-d', substr($lastVisitDate, 0, 10)))
        : null;
    $current = DateTime::createFromFormat('Y-m-d', $date);
    if ($last && $current) {
        $days = (int)$last->diff($current)->format('%r%a');
        if ($days >= 0 && $days <= (int)$settings['revisit_validity_days']) {
            return max(0, (float)$settings['visit_fee'] - (float)$settings['revisit_fee']);
        }
    }
    return max(0, (float)($row['discount'] ?? 0));
}

function appointment_page_rows(PDO $pdo, string $date, array $settings, int $doctorId): array {
    if ($doctorId <= 0) {
        return [];
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
            coalesce(nullif(a.mobile, ''), p.mobile, '') AS mobile,
            coalesce(nullif(a.address, ''), p.address, '') AS address,
            coalesce(nullif(a.referral_category, ''), 'self') AS referral_category,
            coalesce(a.referral_name, '') AS referral_name,
            a.visit_no,
            a.visit_id,
            a.visit_id AS visit_code,
            a.visit_fee,
            a.discount,
            a.discount_note,
            a.paid_amount,
            a.status,
            a.notes,
            a.bp,
            a.pulse,
            a.temperature,
            a.spo2,
            a.resp_rate,
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
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $fee = ($row['visit_fee'] ?? '') !== '' ? (float)$row['visit_fee'] : (float)$settings['visit_fee'];
        $discount = appointment_page_revisit_discount($row, $settings, $date);
        $paidAmount = max(0, (float)($row['paid_amount'] ?? 0));
        $payable = max(0, $fee - $discount);
        $row['visit_fee'] = $fee;
        $row['paid_status'] = $payable > 0 && $paidAmount >= $payable ? 'Paid' : 'Not Paid';
        $row['last_visit_label'] = appointment_page_last_visit_label($row['last_visit_date'] ?? null, $date);
    }
    unset($row);
    return $rows;
}

function appointment_page_followup_rows(PDO $pdo, int $doctorId): array {
    if ($doctorId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT
            v.id AS visit_id,
            v.patient_id,
            coalesce(nullif(v.patient_reg_no, ''), p.reg_no, '') AS reg_no,
            coalesce(nullif(v.patient_name, ''), p.full_name, '') AS patient_name,
            substr(v.visit_date, 1, 10) AS last_visit_date,
            v.next_visit,
            coalesce(nullif(p.mobile, ''), '') AS mobile,
            coalesce(nullif(p.age, ''), v.age_at_visit, '') AS age,
            coalesce(nullif(p.gender, ''), '') AS gender,
            coalesce(nullif(p.address, ''), '') AS address
         FROM zimrx_visits v
         LEFT JOIN zimrx_patients p ON p.id = v.patient_id
         WHERE v.doctor_id = :doctor_id
           AND v.next_visit IS NOT NULL
           AND trim(v.next_visit) != ''
         ORDER BY v.next_visit ASC, v.id DESC"
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$initialQueueDateIso = appointment_page_dmy_to_iso(date('d/m/Y'));
$initialQueueDateDisplay = appointment_page_iso_to_dmy($initialQueueDateIso);
$initialSettings = $initialDoctorId > 0 ? appointment_page_settings($pdo, $initialDoctorId) : appointment_page_settings($pdo, current_user_doctor_id());
$initialAppointments = $initialDoctorId > 0 ? appointment_page_rows($pdo, $initialQueueDateIso, $initialSettings, $initialDoctorId) : [];
$initialFollowups = $initialDoctorId > 0 ? appointment_page_followup_rows($pdo, $initialDoctorId) : [];
$initialAppointmentNo = $initialDoctorId > 0 ? appointment_page_next_no($pdo, $initialQueueDateIso, $initialSettings, $initialDoctorId) : '';
$initialAppointmentTime = $initialDoctorId > 0 && $initialAppointmentNo !== '' ? appointment_page_calculate_time((int)$initialAppointmentNo, $initialQueueDateIso, $initialSettings) : '';

$totalAppointments = count($initialAppointments);
$pendingCount = 0;
$doneCount = 0;
$totalCollected = 0.0;
foreach ($initialAppointments as $a) {
    if (strtolower((string)($a['status'] ?? '')) === 'done') {
        $doneCount++;
    } else {
        $pendingCount++;
    }
    $totalCollected += max(0, (float)($a['paid_amount'] ?? 0));
}

// Compute Follow-up Statistics
$totalFollowups = count($initialFollowups);
$dueTodayCount = 0;
$upcomingCount = 0;
$overdueCount = 0;
$todayIso = date('Y-m-d');
foreach ($initialFollowups as $f) {
    $nextDate = substr(trim((string)$f['next_visit']), 0, 10);
    if ($nextDate === $todayIso) {
        $dueTodayCount++;
    } elseif ($nextDate > $todayIso) {
        $upcomingCount++;
    } elseif ($nextDate !== '') {
        $overdueCount++;
    }
}

$page_title = "ZimRx - Appointments";
$body_class = trim(($body_class ?? '') . ' zimrx-appointments-hold');
$extra_css = ['assets/css/appointments.css'];
include 'header.php';
?>

<main class="appointments-page">

    <!-- ── Streamlined Segmented Tab Switcher ─────────────── -->
    <div class="apt-segmented-wrap">
        <div class="apt-segmented-bar" role="tablist">
            <button type="button" class="apt-segment-btn active" id="btn-tab-queue" onclick="switchAppointmentTab('queue')" role="tab" aria-selected="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Appointment Queue</span>
                <span class="apt-segment-badge" id="queue-tab-count"><?= $totalAppointments ?></span>
            </button>

            <button type="button" class="apt-segment-btn" id="btn-tab-followup" onclick="switchAppointmentTab('followup')" role="tab" aria-selected="false">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <span>Follow-up Patients</span>
                <span class="apt-segment-badge badge-followup" id="followup-tab-count"><?= $totalFollowups ?></span>
            </button>
        </div>
    </div>

    <!-- ── TAB 1: Appointments Queue & Registration Desk ─────── -->
    <div id="panel-tab-queue">

    <?php if (current_user_role() === 'assistant'): ?>
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; background: #ffffff; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
        <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
            <label style="font-weight: 700; font-size: 0.88rem; color: #334155;">Desk Doctor:</label>
            <div class="appointment-doctor-selector autocomplete-wrapper" id="appointment-doctor-wrapper" style="flex: 1; max-width: 320px;">
                <input type="hidden" id="active-doctor-id" value="<?= $initialDoctorId ?: '' ?>">
                <input type="text" id="appointment-doctor-search" placeholder="<?= count($appointmentDoctorOptions) > 1 ? 'Select doctor for appointment desk...' : 'Assigned doctor' ?>" value="<?= htmlspecialchars($initialDoctorName) ?>" <?= count($appointmentDoctorOptions) === 1 ? 'readonly' : '' ?>>
                <ul class="autocomplete-list appointment-lookup-list" id="appointment-doctor-list"></ul>
            </div>
        </div>
    </div>
    <?php else: ?>
    <input type="hidden" id="active-doctor-id" value="<?= $initialDoctorId ?: current_user_doctor_id() ?>">
    <?php endif; ?>
    <input type="hidden" id="appointment-date-filter" value="<?= appointment_page_h($initialQueueDateDisplay) ?>">

    <form id="appointment-form" class="appointment-patient-card patient-particulars" autocomplete="off" novalidate>
        <input type="hidden" id="appointment-id">
        <input type="hidden" id="patient-id">

        <div class="patient-info">
            <div class="p-row p-row-1">
                <div class="p-field">
                    <label><?= zrx_icon('user', 12) ?>Patient Name</label>
                    <input type="text" id="patient-name" placeholder="Full Patient Name" required>
                </div>

                <div class="p-field">
                    <label><?= zrx_icon('clock', 12) ?>Age</label>
                    <div class="input-group">
                        <input type="text" id="patient-age" placeholder="Age">
                        <select id="patient-age-unit">
                            <option>Years</option>
                            <option>Months</option>
                            <option>Weeks</option>
                            <option>Days</option>
                        </select>
                    </div>
                </div>

                <div class="p-field">
                    <label>
                        <?= zrx_icon('calendar', 12) ?>DOB
                        <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="dob" title="জন্মতারিখ (DOB) নির্দেশিকা" aria-label="DOB Help">
                            <?= zrx_icon('help-circle', 13) ?>
                        </span>
                    </label>
                    <input type="text" class="custom-date-picker" id="patient-dob" placeholder="DD/MM/YYYY">
                </div>

                <div class="p-field">
                    <label><?= zrx_icon('users', 12) ?>Sex</label>
                    <select id="patient-gender">
                        <option value="" selected>--</option>
                        <option>Male</option>
                        <option>Female</option>
                        <option>Others</option>
                    </select>
                </div>

                <div class="p-field">
                    <label><?= zrx_icon('droplet', 12) ?>BG</label>
                    <select id="patient-blood-group">
                        <option value="" selected>--</option>
                        <option>A+</option>
                        <option>A-</option>
                        <option>B+</option>
                        <option>B-</option>
                        <option>O+</option>
                        <option>O-</option>
                        <option>AB+</option>
                        <option>AB-</option>
                    </select>
                </div>

                <div class="p-field">
                    <label><?= zrx_icon('calendar', 12) ?>Date</label>
                    <input type="text" class="custom-date-picker" id="appointment-date" value="<?= appointment_page_h($initialQueueDateDisplay) ?>" placeholder="DD/MM/YYYY" required>
                </div>

                <div class="p-field vis-field">
                    <label>
                        <?= zrx_icon('clipboard', 12) ?>Visit ID
                        <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="visit-id" title="ভিজিট আইডি (Visit ID) নির্দেশিকা" aria-label="Visit ID Help">
                            <?= zrx_icon('help-circle', 13) ?>
                        </span>
                    </label>
                    <input type="text" id="visit-code" placeholder="Auto on save" readonly>
                </div>
            </div>

            <div class="p-row p-row-2">
                <div class="p-field reg-field lookup-field">
                    <label>
                        <?= zrx_icon('hash', 12) ?>
                        Reg No
                        <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="appt-reg" title="রেজিস্ট্রেশন নম্বর নির্দেশিকা" aria-label="Reg No Help">
                            <?= zrx_icon('help-circle', 13) ?>
                        </span>
                    </label>
                    <div class="autocomplete-wrapper" id="reg-wrapper">
                        <input type="text" id="patient-reg-no" placeholder="Auto on save or search old Reg No">
                        <ul class="autocomplete-list appointment-lookup-list zrx-dropdown patient-lookup-list" id="reg-list"></ul>
                    </div>
                </div>

                <div class="p-field lookup-field reg-field">
                    <label>
                        <?= zrx_icon('phone', 12) ?>
                        Mobile
                        <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="mobile" title="ফোন নম্বর নির্দেশিকা" aria-label="Mobile Help">
                            <?= zrx_icon('help-circle', 13) ?>
                        </span>
                    </label>
                    <div class="autocomplete-wrapper" id="mobile-wrapper">
                        <input type="text" id="patient-mobile" placeholder="01XXX-XXXXXX">
                        <ul class="autocomplete-list appointment-lookup-list zrx-dropdown patient-lookup-list" id="mobile-list"></ul>
                    </div>
                </div>

                <div class="p-field">
                    <label>
                        <?= zrx_icon('briefcase', 12) ?>Occupation
                        <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="occupation" title="পেশা (Occupation) নির্দেশিকা ও ম্যানেজমেন্ট" aria-label="Occupation Help">
                            <?= zrx_icon('help-circle', 13) ?>
                        </span>
                    </label>
                    <div class="autocomplete-wrapper" id="occ-wrapper">
                        <input type="text" id="patient-occupation" placeholder="Occupation...">
                        <ul class="autocomplete-list appointment-lookup-list zrx-dropdown patient-lookup-list" id="occupation-list"></ul>
                    </div>
                </div>

                <div class="p-field">
                    <label>
                        <?= zrx_icon('map-pin', 12) ?>Address
                        <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="address" title="ঠিকানা (Address) নির্দেশিকা ও ম্যানেজমেন্ট" aria-label="Address Help">
                            <?= zrx_icon('help-circle', 13) ?>
                        </span>
                    </label>
                    <div class="autocomplete-wrapper" id="address-wrapper">
                        <input type="text" id="patient-address" placeholder="Village, Union, Upazila...">
                        <ul class="autocomplete-list appointment-lookup-list zrx-dropdown patient-lookup-list" id="address-list"></ul>
                    </div>
                </div>

                <div class="p-field">
                    <label><?= zrx_icon('scale', 12) ?>Weight</label>
                    <div class="input-group">
                        <input type="text" id="patient-weight" placeholder="Wt">
                        <select id="patient-weight-unit">
                            <option>kg</option>
                            <option>lb</option>
                        </select>
                    </div>
                </div>

                <div class="p-field">
                    <label><?= zrx_icon('ruler', 12) ?>Height</label>
                    <div class="input-group">
                        <input type="text" id="patient-height" placeholder="Ht">
                        <select id="patient-height-unit">
                            <option value="inch" selected>inch</option>
                            <option value="feet">feet</option>
                            <option value="cm">cm</option>
                            <option value="meter">meter</option>
                        </select>
                    </div>
                </div>

                <div class="p-field">
                    <label><?= zrx_icon('users', 12) ?>Referred by</label>
                    <div class="patient-referral-control" id="patient-referral-control">
                        <select id="patient-ref-type">
                            <option value="Self" selected>Self</option>
                            <option value="Other Patient">Other Patient</option>
                            <option value="Doctor">Doctor</option>
                            <option value="Others">Others</option>
                        </select>
                        <input type="text" id="patient-ref-by" placeholder="Doctor name" autocomplete="off" list="patient-referral-list" hidden>
                        <datalist id="patient-referral-list"></datalist>
                    </div>
                </div>

                <div class="p-field vis-field">
                    <label><?= zrx_icon('activity', 12) ?>Visit No</label>
                    <input type="text" id="visit-no" placeholder="Auto on save" readonly>
                </div>
            </div>
        </div>

        <div class="appointment-side-panel">
            <label>
                <span>Serial</span>
                <input type="number" id="appointment-no" min="1" value="<?= appointment_page_h((string)$initialAppointmentNo) ?>" placeholder="Auto">
                <small class="field-note">Preview only. Final serial is confirmed on save.</small>
            </label>
            <label>
                <span>Time</span>
                <input type="time" id="appointment-time" value="<?= appointment_page_h($initialAppointmentTime) ?>">
            </label>
            <label>
                <span>Notes</span>
                <textarea id="appointment-notes" rows="3" placeholder="Short appointment note"></textarea>
            </label>
            <div class="appointment-actions">
                <button type="button" class="btn btn-outline" id="appointment-reset">Clear</button>
                <button type="submit" class="btn btn-primary">Save Appointment</button>
            </div>
        </div>
    </form>

    <!-- Queue KPI Summary Strip -->
    <div class="queue-kpi-strip">
        <div class="queue-kpi-item">
            <div class="queue-kpi-item-left">
                <span class="queue-kpi-label">Today's Bookings</span>
                <span class="queue-kpi-number" id="kpi-queue-total"><?= $totalAppointments ?></span>
            </div>
            <div class="kpi-icon-wrap" style="background: #eff6ff; color: #2563eb;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>

        <div class="queue-kpi-item">
            <div class="queue-kpi-item-left">
                <span class="queue-kpi-label">Waiting in Queue</span>
                <span class="queue-kpi-number" id="kpi-queue-pending" style="color: #2563eb;"><?= $pendingCount ?></span>
            </div>
            <div class="kpi-icon-wrap" style="background: #eff6ff; color: #2563eb;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>

        <div class="queue-kpi-item">
            <div class="queue-kpi-item-left">
                <span class="queue-kpi-label">Consultations Done</span>
                <span class="queue-kpi-number" id="kpi-queue-done" style="color: #059669;"><?= $doneCount ?></span>
            </div>
            <div class="kpi-icon-wrap" style="background: #ecfdf5; color: #059669;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>

        <div class="queue-kpi-item">
            <div class="queue-kpi-item-left">
                <span class="queue-kpi-label">Revenue Collected</span>
                <span class="queue-kpi-number" id="kpi-queue-paid" style="color: #047857;">৳ <?= number_format($totalCollected) ?></span>
            </div>
            <div class="kpi-icon-wrap" style="background: #ecfdf5; color: #047857;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
        </div>
    </div>

    <section class="appointment-list-card">
        <div class="appointment-list-header">
            <div>
                <h2>
                    Queue
                    <input type="text" class="custom-date-picker zimrx-date-input" id="queue-date-input" value="<?= appointment_page_h($initialQueueDateDisplay) ?>" placeholder="DD/MM/YYYY" inputmode="numeric" aria-label="Queue date">
                </h2>
                <p id="appointment-count"><?= count($initialAppointments) ?> appointment<?= count($initialAppointments) === 1 ? '' : 's' ?></p>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="appointment-refresh">Refresh</button>
        </div>
        <div class="appointment-table-wrap">
            <table class="appointment-table">
                <colgroup>
                    <col class="col-start">
                    <col class="col-status">
                    <col class="col-serial">
                    <col class="col-reg">
                    <col class="col-patient">
                    <col class="col-mobile">
                    <col class="col-visit-no">
                    <col class="col-last-visit">
                    <col class="col-fee">
                    <col class="col-paid">
                    <col class="col-time">
                    <col class="col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th>Start</th>
                        <th>Status</th>
                        <th>Serial No</th>
                        <th>Reg No</th>
                        <th>Patient</th>
                        <th>Mobile</th>
                        <th>Visit No</th>
                        <th>Last Visit</th>
                        <th>Visit Fee</th>
                        <th>Paid Status</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="appointment-list">
                    <?php if ($initialDoctorId <= 0): ?>
                    <tr><td colspan="12" class="empty-appointments">Select a doctor to load appointments.</td></tr>
                    <?php elseif (!$initialAppointments): ?>
                    <tr><td colspan="12" class="empty-appointments">No appointments for this date.</td></tr>
                    <?php else: ?>
                        <?php foreach ($initialAppointments as $item): ?>
                            <?php
                            $status = (string)($item['status'] ?: 'Pending');
                            $statusClass = strtolower($status);
                            $paidClass = strtolower(preg_replace('/\s+/', '-', (string)($item['paid_status'] ?? 'Not Paid')));
                            $params = [
                                'appointment_id' => (string)($item['id'] ?? ''),
                                'patient_id' => (string)($item['patient_id'] ?? ''),
                                'reg_no' => (string)($item['reg_no'] ?? ''),
                                'visit_no' => (string)($item['visit_no'] ?? ''),
                                'visit_id' => (string)($item['visit_id'] ?? ''),
                                'visit_code' => (string)($item['visit_id'] ?? ''),
                                'referral_category' => (string)($item['referral_category'] ?? 'self'),
                                'referral_name' => (string)($item['referral_name'] ?? ''),
                            ];
                            $prescribeHref = 'prescription.php?' . http_build_query($params);
                            $tokenHref = 'appointment_token_print.php?id=' . rawurlencode((string)($item['id'] ?? '')) . ($initialDoctorId > 0 ? '&doctor_id=' . rawurlencode((string)$initialDoctorId) : '');
                            $vitals = array_filter([
                                $item['bp'] ? 'BP ' . $item['bp'] : '',
                                $item['pulse'] ? 'Pulse ' . $item['pulse'] : '',
                                $item['temperature'] ? 'Temp ' . $item['temperature'] : '',
                                $item['spo2'] ? 'SpO2 ' . $item['spo2'] : '',
                                $item['resp_rate'] ? 'RR ' . $item['resp_rate'] : '',
                            ]);
                            ?>
                            <tr>
                                <td class="queue-start-cell"><a class="queue-start-btn prescribe-btn" href="<?= appointment_page_h($prescribeHref) ?>">Prescribe</a></td>
                                <td><button type="button" class="status-pill <?= appointment_page_h($statusClass) ?>"><?= appointment_page_h($status) ?></button></td>
                                <td class="serial-cell"><?= appointment_page_h((string)($item['appointment_no'] ?? '')) ?></td>
                                <td class="reg-cell"><?= appointment_page_h((string)($item['reg_no'] ?? '')) ?></td>
                                <td>
                                    <strong><?= appointment_page_h((string)($item['patient_name'] ?? '')) ?></strong>
                                    <?php if ($vitals): ?><span class="vitals-summary"><?= appointment_page_h(implode(' | ', $vitals)) ?></span><?php endif; ?>
                                    <?php if (!empty($item['address'])): ?><span><?= appointment_page_h((string)$item['address']) ?></span><?php endif; ?>
                                </td>
                                <td><?= appointment_page_h((string)($item['mobile'] ?? '')) ?></td>
                                <td><?= appointment_page_h((string)($item['visit_no'] ?? '')) ?></td>
                                <td class="last-visit-cell"><?= appointment_page_last_visit_html((string)($item['last_visit_label'] ?? '')) ?></td>
                                <td class="money-cell"><?= appointment_page_h(appointment_page_money($item['visit_fee'] ?? 0)) ?></td>
                                <td><span class="payment-pill <?= appointment_page_h($paidClass) ?>"><?= appointment_page_h((string)($item['paid_status'] ?? 'Not Paid')) ?></span></td>
                                <td><?= appointment_page_h((string)($item['appointment_time'] ?? '')) ?></td>
                                <td class="row-actions">
                                    <button type="button" data-action="edit">Edit</button>
                                    <button type="button" data-action="done"><?= $statusClass === 'done' ? 'Set Pending' : 'Set Done' ?></button>
                                    <button type="button" data-action="payment">Payment</button>
                                    <a href="<?= appointment_page_h($tokenHref) ?>" target="_blank">Print</a>
                                    <button type="button" data-action="history">Appointment History</button>
                                    <button type="button" data-action="cancel">Cancel</button>
                                    <button type="button" data-action="delete">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    </div><!-- /#panel-tab-queue -->

    <!-- ── TAB 2: Follow-up Patient Tracker ───────────────────── -->
    <div id="panel-tab-followup" hidden>
        <section class="appointment-hero" style="margin-bottom: 1.25rem;">
            <div>
                <p class="eyebrow">Patient Revisit Hub</p>
                <h1>Follow-up Patient Tracker</h1>
                <p class="appointment-subtitle">Monitor scheduled revisit consultations from previous prescriptions and queue them for today with 1-click.</p>
            </div>
        </section>

        <!-- KPI Summary Cards -->
        <div class="followup-kpi-grid">
            <div class="followup-kpi-card" onclick="filterFollowupStatus('all', document.querySelector('.followup-chip-btn[data-filter=all]'))">
                <div class="kpi-icon-wrap" style="background: #eff6ff; color: #2563eb;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <span class="kpi-val" id="kpi-all"><?= $totalFollowups ?></span>
                    <span class="kpi-lbl">Total Scheduled</span>
                </div>
            </div>

            <div class="followup-kpi-card" onclick="filterFollowupStatus('due', document.querySelector('.followup-chip-btn[data-filter=due]'))">
                <div class="kpi-icon-wrap" style="background: #ecfdf5; color: #059669;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                </div>
                <div>
                    <span class="kpi-val" id="kpi-due" style="color: #059669;"><?= $dueTodayCount ?></span>
                    <span class="kpi-lbl">Due Today</span>
                </div>
            </div>

            <div class="followup-kpi-card" onclick="filterFollowupStatus('upcoming', document.querySelector('.followup-chip-btn[data-filter=upcoming]'))">
                <div class="kpi-icon-wrap" style="background: #f0f9ff; color: #0284c7;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div>
                    <span class="kpi-val" id="kpi-upcoming" style="color: #0284c7;"><?= $upcomingCount ?></span>
                    <span class="kpi-lbl">Upcoming Revisit</span>
                </div>
            </div>

            <div class="followup-kpi-card" onclick="filterFollowupStatus('overdue', document.querySelector('.followup-chip-btn[data-filter=overdue]'))">
                <div class="kpi-icon-wrap" style="background: #fffbeb; color: #d97706;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <span class="kpi-val" id="kpi-overdue" style="color: #d97706;"><?= $overdueCount ?></span>
                    <span class="kpi-lbl">Overdue Follow-ups</span>
                </div>
            </div>
        </div>

        <!-- Filter and Search Bar -->
        <div class="followup-filter-bar">
            <div class="followup-filter-chips">
                <button type="button" class="followup-chip-btn active" data-filter="all" onclick="filterFollowupStatus('all', this)">All (<?= count($initialFollowups) ?>)</button>
                <button type="button" class="followup-chip-btn" data-filter="due" onclick="filterFollowupStatus('due', this)">Due Today (<?= $dueTodayCount ?>)</button>
                <button type="button" class="followup-chip-btn" data-filter="upcoming" onclick="filterFollowupStatus('upcoming', this)">Upcoming (<?= $upcomingCount ?>)</button>
                <button type="button" class="followup-chip-btn" data-filter="overdue" onclick="filterFollowupStatus('overdue', this)">Overdue (<?= $overdueCount ?>)</button>
            </div>
            <input type="text" id="followup-search-input" class="followup-search-input" placeholder="Search by name, reg no, mobile..." oninput="filterFollowupStatus(getCurrentFollowupFilter())">
        </div>

        <!-- Follow-ups Table Card -->
        <section class="appointment-list-card">
            <div class="appointment-list-header">
                <div>
                    <h2>Scheduled Revisit List</h2>
                    <p id="followup-count-label"><?= count($initialFollowups) ?> scheduled follow-up<?= count($initialFollowups) === 1 ? '' : 's' ?></p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print Call Sheet
                </button>
            </div>
            <div class="appointment-table-wrap">
                <table class="appointment-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Reg No</th>
                            <th>Patient Details</th>
                            <th>Contact</th>
                            <th>Last Prescribed</th>
                            <th>Follow-up Due</th>
                            <th>Estimated Fee</th>
                        </tr>
                    </thead>
                    <tbody id="followup-table-body">
                        <?php if (empty($initialFollowups)): ?>
                        <tr><td colspan="8" class="empty-appointments">No follow-up records found for this doctor.</td></tr>
                        <?php else: ?>
                            <?php foreach ($initialFollowups as $f): ?>
                                <?php
                                $nextVisitRaw = trim((string)$f['next_visit']);
                                $lastVisitRaw = trim((string)$f['last_visit_date']);
                                $todayStr = date('Y-m-d');
                                $dueStatus = 'upcoming';
                                $dueBadgeClass = 'status-badge-upcoming';
                                $dueText = 'Upcoming';

                                $dtNext = DateTime::createFromFormat('Y-m-d', substr($nextVisitRaw, 0, 10));
                                $dtToday = DateTime::createFromFormat('Y-m-d', $todayStr);
                                $displayNext = $dtNext ? $dtNext->format('d/m/Y') : $nextVisitRaw;
                                $displayLast = $lastVisitRaw ? appointment_page_iso_to_dmy($lastVisitRaw) : '—';

                                $daysAgo = 999;
                                if ($lastVisitRaw) {
                                    $dtLast = DateTime::createFromFormat('Y-m-d', $lastVisitRaw);
                                    if ($dtLast && $dtToday) {
                                        $daysAgo = (int)$dtLast->diff($dtToday)->format('%r%a');
                                    }
                                }

                                $revisitValidDays = (int)($initialSettings['revisit_validity_days'] ?? 60);
                                $isRevisitValid = $daysAgo >= 0 && $daysAgo <= $revisitValidDays;
                                $estFee = $isRevisitValid ? (float)($initialSettings['revisit_fee'] ?? 400) : (float)($initialSettings['visit_fee'] ?? 500);

                                if ($dtNext && $dtToday) {
                                    $diff = (int)$dtToday->diff($dtNext)->format('%r%a');
                                    if ($diff === 0) {
                                        $dueStatus = 'due';
                                        $dueBadgeClass = 'status-badge-due';
                                        $dueText = 'Due Today';
                                    } elseif ($diff < 0) {
                                        $dueStatus = 'overdue';
                                        $dueBadgeClass = 'status-badge-overdue';
                                        $dueText = abs($diff) . 'd overdue';
                                    } else {
                                        $dueStatus = 'upcoming';
                                        $dueBadgeClass = 'status-badge-upcoming';
                                        $dueText = 'In ' . $diff . 'd';
                                    }
                                }

                                $fDataJson = json_encode([
                                    'patient_id' => $f['patient_id'],
                                    'reg_no' => $f['reg_no'],
                                    'patient_name' => $f['patient_name'],
                                    'mobile' => $f['mobile'],
                                    'age' => $f['age'],
                                    'gender' => $f['gender'],
                                    'address' => $f['address'],
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

                                $cleanMobile = preg_replace('/\D/', '', (string)$f['mobile']);
                                $waPhone = (strlen($cleanMobile) === 11 && strpos($cleanMobile, '01') === 0) ? '88' . $cleanMobile : $cleanMobile;
                                $waText = "Dear " . ($f['patient_name'] ?? 'Patient') . ", reminder: your scheduled follow-up consultation is on " . $displayNext . ". Please call or visit us for appointment booking.";
                                $waUrl = "https://api.whatsapp.com/send?phone=" . rawurlencode($waPhone) . "&text=" . rawurlencode($waText);
                                ?>
                                <tr class="followup-row" data-status="<?= $dueStatus ?>">
                                    <td class="queue-start-cell">
                                        <button type="button" class="queue-start-btn" onclick='quickQueueFollowup(<?= $fDataJson ?>)' style="background: #2563eb; color: #ffffff; border: none; padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 700; white-space: nowrap; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);">+ Queue Today</button>
                                        <a class="queue-start-btn prescribe-btn" href="prescription.php?patient_id=<?= urlencode((string)$f['patient_id']) ?>&reg_no=<?= urlencode((string)$f['reg_no']) ?>" style="margin-left: 0.35rem; white-space: nowrap;">Prescribe</a>
                                    </td>
                                    <td>
                                        <span class="<?= $dueBadgeClass ?>"><?= htmlspecialchars($dueText) ?></span>
                                    </td>
                                    <td class="reg-cell"><strong><?= htmlspecialchars((string)$f['reg_no']) ?></strong></td>
                                    <td>
                                        <strong style="color: #0f172a; font-size: 0.92rem;"><?= htmlspecialchars((string)$f['patient_name']) ?></strong>
                                        <?php if (!empty($f['age']) || !empty($f['gender'])): ?>
                                        <span style="font-size: 0.78rem; color: #64748b; display: block; margin-top: 0.1rem;"><?= htmlspecialchars(trim(($f['age'] ? $f['age'] . 'y ' : '') . ($f['gender'] ?: ''))) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($f['address'])): ?>
                                        <span style="font-size: 0.75rem; color: #94a3b8; display: block;"><?= htmlspecialchars((string)$f['address']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($f['mobile'])): ?>
                                            <div style="display: flex; align-items: center; gap: 0.25rem;">
                                                <a href="tel:<?= htmlspecialchars((string)$f['mobile']) ?>" class="quick-contact-link" title="Call patient">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l2.28-2.28a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                                    <?= htmlspecialchars((string)$f['mobile']) ?>
                                                </a>
                                                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-reminder-btn" title="Send WhatsApp Revisit Reminder">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.699c.983.537 1.776.84 2.796.84 3.185 0 5.77-2.587 5.77-5.766.001-3.187-2.575-5.77-5.77-5.772zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.072-2.155-.544-1.859-.785-3.045-2.673-3.137-2.797-.093-.124-.755-1.006-.755-1.921s.478-1.366.647-1.554c.17-.188.371-.235.495-.235.124 0 .247.002.355.007.113.006.264-.043.413.315.154.371.526 1.282.573 1.376.046.093.077.202.015.326-.062.124-.093.202-.185.31-.093.108-.196.241-.28.324-.093.093-.19.194-.082.38.109.186.481.794 1.033 1.285.711.634 1.31.83 1.496.923.186.093.294.077.402-.047.109-.124.465-.542.589-.728.124-.186.248-.155.418-.093.17.062 1.082.51 1.268.603.186.093.309.139.355.216.047.078.047.45-.097.855z"/></svg>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 0.8rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span><?= htmlspecialchars($displayLast) ?></span>
                                        <?php if ($daysAgo < 999): ?>
                                        <span style="font-size: 0.74rem; color: #64748b; display: block;"><?= $daysAgo ?>d ago</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong style="color: #0f172a;"><?= htmlspecialchars($displayNext) ?></strong></td>
                                    <td>
                                        <?php if ($isRevisitValid): ?>
                                            <span style="display: inline-block; font-size: 0.76rem; font-weight: 700; color: #059669; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.15rem 0.45rem; border-radius: 4px;">Revisit: ৳<?= (int)$estFee ?></span>
                                        <?php else: ?>
                                            <span style="display: inline-block; font-size: 0.76rem; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 0.15rem 0.45rem; border-radius: 4px;">Standard: ৳<?= (int)$estFee ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div><!-- /#panel-tab-followup -->

</main>

<?php if (current_user_role() !== 'assistant'): ?>
<div class="appointment-modal" id="appointment-settings-modal" hidden>
    <div class="appointment-modal-backdrop" data-settings-close></div>
    <form class="appointment-modal-panel appointment-settings-panel" id="appointment-settings-form">
        <div class="appointment-modal-header">
            <div>
                <p class="eyebrow">Chamber Timing</p>
                <h2>Appointment Settings</h2>
                <span>Configure estimated queue time, blank safety slots, fees, and token fields.</span>
            </div>
            <button type="button" class="appointment-modal-close" data-settings-close>&times;</button>
        </div>

        <div class="settings-form-grid">
            <label>
                <span>Appointment starts from</span>
                <input type="time" id="setting-default-start-time">
            </label>
            <label>
                <span>Appointment time per patient</span>
                <div class="unit-input">
                    <input type="number" id="setting-minutes-per-patient" min="1" value="5">
                    <b>Minute</b>
                </div>
            </label>
            <label>
                <span>Keep first Few slots blank everyday</span>
                <input type="number" id="setting-blank-slots" min="0" value="3">
            </label>
            <label>
                <span>Appointment Fee</span>
                <div class="unit-input">
                    <input type="number" id="setting-visit-fee" min="0" value="500">
                    <b>Tk</b>
                </div>
            </label>
            <label>
                <span>Revisit Fees</span>
                <div class="unit-input">
                    <input type="number" id="setting-revisit-fee" min="0" value="400">
                    <b>Tk</b>
                </div>
            </label>
            <label>
                <span>Revisit Validity</span>
                <div class="unit-input">
                    <input type="number" id="setting-revisit-validity" min="0" value="60">
                    <b>Day(s)</b>
                </div>
            </label>
        </div>

        <div class="blank-slot-explanation">
            As in Bangladesh, our chamber system is a serial-based contract, consulting or treating an emergency patient or a fellow colleague (doctor) out of serial might be a breach of contract. So to be safe, we are setting these few empty serials where we can place an emergency patient, a fellow colleague, or a first-degree relative. These won't count as nepotism, as treating an emergency patient is necessary, and treating a doctor earlier is better as it is our professional courtesy, as well as giving him an opportunity to go back to his workplace earlier and treat more patients.
        </div>

        <div class="settings-section-title">Weekly Exceptions</div>
        <div class="weekday-settings" id="weekday-settings">
            <?php
            $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($weekdays as $index => $day):
            ?>
            <div class="weekday-row" data-weekday="<?= $index ?>">
                <strong><?= htmlspecialchars($day) ?></strong>
                <label><input type="checkbox" class="weekday-closed"> Closed</label>
                <label>Start <input type="time" class="weekday-start-time"></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="settings-section-title">Appointment Token Settings to include</div>
        <div class="token-field-grid" id="token-field-grid">
            <label><input type="checkbox" value="name"> Name</label>
            <label><input type="checkbox" value="age"> Age</label>
            <label><input type="checkbox" value="sex"> Sex</label>
            <label><input type="checkbox" value="reg"> Reg</label>
            <label><input type="checkbox" value="visit_no"> Visit No</label>
            <label><input type="checkbox" value="visit_id"> Visit ID</label>
            <label><input type="checkbox" value="visit_fee"> Visit Fee</label>
            <label><input type="checkbox" value="discount"> Discount</label>
            <label><input type="checkbox" value="paid"> Paid</label>
        </div>

        <div class="appointment-modal-actions">
            <button type="button" class="btn btn-outline" data-settings-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="appointment-modal" id="payment-modal" hidden>
    <div class="appointment-modal-backdrop" data-payment-close></div>
    <form class="appointment-modal-panel" id="payment-form">
        <input type="hidden" id="payment-appointment-id">
        <div class="appointment-modal-header">
            <div>
                <p class="eyebrow">Appointment Payment</p>
                <h2>Payment</h2>
                <span id="payment-patient-label"></span>
            </div>
            <button type="button" class="appointment-modal-close" data-payment-close>&times;</button>
        </div>
        <div class="settings-form-grid">
            <label>
                <span>Visit Fee</span>
                <input type="number" id="payment-visit-fee" min="0">
            </label>
            <label>
                <span>Discount</span>
                <input type="number" id="payment-discount" min="0">
            </label>
            <label>
                <span>Payable Amount</span>
                <input type="number" id="payment-payable-amount" readonly>
            </label>
            <label class="span-2">
                <span>Discount Note</span>
                <input type="text" id="payment-discount-note" list="discount-cause-list" placeholder="Reason for discount">
                <datalist id="discount-cause-list"></datalist>
            </label>
            <label>
                <span>Paid Amount</span>
                <input type="number" id="payment-paid-amount" min="0">
            </label>
        </div>
        <div class="appointment-modal-actions">
            <button type="button" class="btn btn-outline" data-payment-close>Cancel</button>
            <button type="submit" class="btn btn-secondary" data-payment-mode="save">Save Only</button>
            <button type="submit" class="btn btn-primary" data-payment-mode="print">Save and Print</button>
        </div>
    </form>
</div>

<div class="appointment-modal" id="vitals-modal" hidden>
    <div class="appointment-modal-backdrop" data-vitals-close></div>
    <form class="appointment-modal-panel" id="vitals-form">
        <input type="hidden" id="vitals-appointment-id">
        <div class="appointment-modal-header">
            <div>
                <p class="eyebrow">Assistant Desk</p>
                <h2>Enter Vitals</h2>
                <span id="vitals-patient-label"></span>
            </div>
            <button type="button" class="appointment-modal-close" data-vitals-close>&times;</button>
        </div>
        <div class="vitals-grid">
            <label>
                <span>BP</span>
                <input type="text" id="vitals-bp" placeholder="120/80">
            </label>
            <label>
                <span>Pulse</span>
                <input type="text" id="vitals-pulse" placeholder="78/min">
            </label>
            <label>
                <span>Temperature</span>
                <input type="text" id="vitals-temperature" placeholder="98.4 F">
            </label>
            <label>
                <span>SpO2</span>
                <input type="text" id="vitals-spo2" placeholder="98%">
            </label>
            <label>
                <span>Resp. Rate</span>
                <input type="text" id="vitals-resp-rate" placeholder="18/min">
            </label>
            <label class="span-2">
                <span>Vitals Note</span>
                <textarea id="vitals-note" rows="3" placeholder="Any short triage note"></textarea>
            </label>
        </div>
        <div class="appointment-modal-actions">
            <button type="button" class="btn btn-outline" data-vitals-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Save Vitals</button>
        </div>
    </form>
</div>

<div class="appointment-modal" id="history-modal" hidden>
    <div class="appointment-modal-backdrop" data-history-close></div>
    <div class="appointment-modal-panel appointment-history-panel">
        <div class="appointment-modal-header">
            <div>
                <p class="eyebrow">Appointment History</p>
                <h2 id="history-patient-title">Previous Appointments</h2>
                <span id="history-patient-label"></span>
            </div>
            <button type="button" class="appointment-modal-close" data-history-close>&times;</button>
        </div>
        <div class="history-table-wrap">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Serial</th>
                        <th>Visit No</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Paid</th>
                    </tr>
                </thead>
                <tbody id="history-list"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.ZIMRX_APPOINTMENT_ROLE = <?= json_encode(current_user_role()) ?>;
    window.ZIMRX_APPOINTMENT_DOCTORS = <?= json_encode($appointmentDoctorOptions, JSON_UNESCAPED_UNICODE) ?>;
    window.ZIMRX_APPOINTMENT_INITIAL = <?= json_encode([
        'date' => $initialQueueDateIso,
        'appointments' => $initialAppointments,
        'appointment_no' => $initialAppointmentNo,
        'appointment_time' => $initialAppointmentTime,
        'settings' => $initialSettings,
        'active_doctor_id' => $initialDoctorId,
    ], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="assets/js/layout/boot.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/boot.js') ?>"></script>
<script src="assets/js/appointments.js?v=<?= filemtime(__DIR__ . '/assets/js/appointments.js') ?>"></script>
<script>
    (function () {
        let revealed = false;

        function revealAppointmentsPage() {
            if (revealed || !document.body) {
                return;
            }

            revealed = true;
            requestAnimationFrame(function () {
                document.body.classList.add('zimrx-appointments-ready');
            });
        }

        if (document.readyState === 'complete') {
            revealAppointmentsPage();
        } else {
            window.addEventListener('load', revealAppointmentsPage, { once: true });
            window.setTimeout(revealAppointmentsPage, 2500);
        }
    })();
</script>
<script>
function switchAppointmentTab(tab) {
    const isQueue = tab === 'queue';
    const btnQueue = document.getElementById('btn-tab-queue');
    const btnFollowup = document.getElementById('btn-tab-followup');
    const panelQueue = document.getElementById('panel-tab-queue');
    const panelFollowup = document.getElementById('panel-tab-followup');

    if (btnQueue) {
        btnQueue.classList.toggle('active', isQueue);
        btnQueue.setAttribute('aria-selected', isQueue ? 'true' : 'false');
    }
    if (btnFollowup) {
        btnFollowup.classList.toggle('active', !isQueue);
        btnFollowup.setAttribute('aria-selected', !isQueue ? 'true' : 'false');
    }
    if (panelQueue) panelQueue.hidden = !isQueue;
    if (panelFollowup) panelFollowup.hidden = isQueue;

    try {
        if (history.replaceState) {
            history.replaceState(null, null, isQueue ? '#appointments' : '#followups');
        }
    } catch (e) {}
}

function getCurrentFollowupFilter() {
    const activeChip = document.querySelector('.followup-chip-btn.active');
    return activeChip ? (activeChip.getAttribute('data-filter') || 'all') : 'all';
}

function filterFollowupStatus(status, btn) {
    if (btn) {
        document.querySelectorAll('.followup-chip-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    const targetFilter = status || getCurrentFollowupFilter();

    const rows = document.querySelectorAll('#followup-table-body tr.followup-row');
    const query = (document.getElementById('followup-search-input')?.value || '').toLowerCase().trim();
    let visibleCount = 0;

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status') || '';
        const rowText = row.textContent.toLowerCase();
        const matchesStatus = (targetFilter === 'all' || rowStatus === targetFilter);
        const matchesQuery = !query || rowText.includes(query);
        const show = matchesStatus && matchesQuery;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    const countLabel = document.getElementById('followup-count-label');
    if (countLabel) {
        countLabel.textContent = `${visibleCount} scheduled follow-up${visibleCount === 1 ? '' : 's'}`;
    }
}

function quickQueueFollowup(p) {
    switchAppointmentTab('queue');
    if (!p) return;

    if (p.reg_no) {
        const regInput = document.getElementById('patient-reg-no');
        if (regInput) {
            regInput.value = p.reg_no;
            regInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    if (p.patient_name) {
        const nameInput = document.getElementById('patient-name');
        if (nameInput) nameInput.value = p.patient_name;
    }
    if (p.mobile) {
        const mobInput = document.getElementById('patient-mobile');
        if (mobInput) mobInput.value = p.mobile;
    }
    if (p.age) {
        const ageInput = document.getElementById('patient-age');
        if (ageInput) ageInput.value = p.age;
    }
    if (p.gender) {
        const genderSelect = document.getElementById('patient-gender');
        if (genderSelect) genderSelect.value = p.gender;
    }
    if (p.address) {
        const addrInput = document.getElementById('patient-address');
        if (addrInput) addrInput.value = p.address;
    }

    const formCard = document.getElementById('appointment-form');
    if (formCard) {
        formCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        formCard.style.outline = '3px solid #2563eb';
        setTimeout(() => { formCard.style.outline = ''; }, 1800);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#followups' || window.location.hash === '#followup') {
        switchAppointmentTab('followup');
    }
});
</script>
<?php include 'footer.php'; ?>
