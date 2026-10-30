<?php
declare(strict_types=1);

// Appointments view template: daily queue roster, registration desk form, follow-up tracker, and payment modals.

if (!function_exists('appointment_page_h')) {
    function appointment_page_h(?string $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('appointment_page_money')) {
    function appointment_page_money($value): string {
        $amount = is_numeric($value) ? (float)$value : 0.0;
        return fmod($amount, 1.0) === 0.0 ? number_format($amount, 0, '.', '') : number_format($amount, 2, '.', '');
    }
}

if (!function_exists('appointment_page_last_visit_html')) {
    function appointment_page_last_visit_html(string $label): string {
        if (preg_match('/^(.+?)\s+(\(.+\))$/', $label, $matches)) {
            return appointment_page_h($matches[1]) . '<span>' . appointment_page_h($matches[2]) . '</span>';
        }
        return appointment_page_h($label);
    }
}

if (!function_exists('appointment_page_iso_to_dmy')) {
    function appointment_page_iso_to_dmy(string $date): string {
        return AppointmentService::isoToDmy($date);
    }
}

if (!function_exists('appointment_page_dmy_to_iso')) {
    function appointment_page_dmy_to_iso(string $date): string {
        return AppointmentService::dmyToIso($date);
    }
}
?>
<main class="appointments-page zrx-page-container">
<?php require ZIMRX_BASE_DIR . '/views/components/section_navigation.php'; ?>

    <!-- Tab 1: Appointments Queue & Registration Desk -->


    <input type="hidden" id="appointment-date-filter" value="<?= appointment_page_h($initialQueueDateDisplay) ?>">

    <form id="appointment-form" class="appointment-patient-card patient-particulars" autocomplete="off" novalidate>
        <input type="hidden" id="appointment-id">
        <input type="hidden" id="patient-id">

        <div class="patient-info">
            <?php
            $is_appointment_modal = true;
            $prescription_prefill = ['appointment_date' => $initialQueueDateDisplay];
            include ZIMRX_BASE_DIR . '/views/components/patient_particulars_fields.php';
            ?>
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
        </div>

        <div class="queue-kpi-item">
            <div class="queue-kpi-item-left">
                <span class="queue-kpi-label">Waiting in Queue</span>
                <span class="queue-kpi-number zrx-c-primary" id="kpi-queue-pending"><?= $pendingCount ?></span>
            </div>
        </div>

        <div class="queue-kpi-item">
            <div class="queue-kpi-item-left">
                <span class="queue-kpi-label">Consultations Done</span>
                <span class="queue-kpi-number zrx-c-success" id="kpi-queue-done"><?= $doneCount ?></span>
            </div>
        </div>

        <div class="queue-kpi-item">
            <div class="queue-kpi-item-left">
                <span class="queue-kpi-label">Revenue Collected</span>
                <span class="queue-kpi-number num-emerald" id="kpi-queue-paid">৳ <?= number_format($totalCollected) ?></span>
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

<script id="zimrxAppointmentBootstrap" type="application/json">
<?= json_encode([
    'initial' => [
        'date' => $initialQueueDateIso,
        'appointments' => $initialAppointments,
        'appointment_no' => $initialAppointmentNo,
        'appointment_time' => $initialAppointmentTime,
        'settings' => $initialSettings,
        'queue_followup' => $queueFollowupPatient,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="assets/js/layout/help_guidelines.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/layout/help_guidelines.js') ?>"></script>
<script src="assets/js/layout/table_column_resizer.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/layout/table_column_resizer.js') ?>"></script>
<script src="assets/js/layout/grid_navigation.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/layout/grid_navigation.js') ?>"></script>
<script src="assets/js/layout/boot.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/layout/boot.js') ?>"></script>
<script src="assets/js/modules/patient_particulars.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/patient_particulars.js') ?>"></script>
<script src="assets/js/pages/appointments.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/pages/appointments.js') ?>"></script>

