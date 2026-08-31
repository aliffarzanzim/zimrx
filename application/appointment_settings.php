<?php
require_once 'auth.php';
require_login();
require_once 'db.php';

$userRole = current_user_role();
if ($userRole === 'assistant') {
    header('Location: appointments.php');
    exit;
}

$isMultiDoctor = zimrx_is_multi_doctor($pdo);
$currentDoctorId = current_user_doctor_id();
$doctors = zimrx_doctor_options_for_user($pdo, current_user_id(), $userRole, $currentDoctorId);

$selectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : $currentDoctorId;
if ($selectedDoctorId <= 0 && !empty($doctors)) {
    $selectedDoctorId = (int)$doctors[0]['id'];
}
if ($selectedDoctorId <= 0) {
    $selectedDoctorId = 1;
}

$flashMessage = '';
$flashType = 'success';

// Default schema settings
$defaultSettings = [
    'default_start_time' => '14:00',
    'minutes_per_patient' => 5,
    'blank_slots' => 3,
    'visit_fee' => 500,
    'revisit_fee' => 400,
    'revisit_validity_days' => 60,
    'weekday_overrides' => [],
    'token_fields' => [
        'name', 'age', 'sex', 'reg', 'visit_no', 'visit_id',
        'visit_fee', 'discount', 'paid',
    ],
];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $startTime = trim((string)($_POST['default_start_time'] ?? '14:00'));
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            $startTime = '14:00';
        }

        $minutes = max(1, (int)($_POST['minutes_per_patient'] ?? 5));
        $blankSlots = max(0, (int)($_POST['blank_slots'] ?? 3));
        $visitFee = max(0, (float)($_POST['visit_fee'] ?? 500));
        $revisitFee = max(0, (float)($_POST['revisit_fee'] ?? 400));
        $revisitValidity = max(0, (int)($_POST['revisit_validity_days'] ?? 60));

        // Weekly overrides
        $weekdayOverrides = [];
        if (isset($_POST['weekdays']) && is_array($_POST['weekdays'])) {
            foreach ($_POST['weekdays'] as $wIndex => $wData) {
                $wIdxInt = (int)$wIndex;
                $isClosed = !empty($wData['closed']);
                $wStart = trim((string)($wData['start_time'] ?? ''));
                if (!preg_match('/^\d{2}:\d{2}$/', $wStart)) {
                    $wStart = $startTime;
                }
                if ($isClosed || $wStart !== $startTime) {
                    $weekdayOverrides[(string)$wIdxInt] = [
                        'closed' => $isClosed,
                        'start_time' => $wStart,
                    ];
                }
            }
        }

        // Token fields
        $tokenFields = isset($_POST['token_fields']) && is_array($_POST['token_fields'])
            ? array_values(array_intersect($_POST['token_fields'], ['name', 'age', 'sex', 'reg', 'visit_no', 'visit_id', 'visit_fee', 'discount', 'paid']))
            : $defaultSettings['token_fields'];

        $savePayload = [
            'default_start_time' => $startTime,
            'minutes_per_patient' => $minutes,
            'blank_slots' => $blankSlots,
            'visit_fee' => $visitFee,
            'revisit_fee' => $revisitFee,
            'revisit_validity_days' => $revisitValidity,
            'weekday_overrides' => $weekdayOverrides,
            'token_fields' => $tokenFields,
        ];

        $stmt = $pdo->prepare(
            "INSERT INTO zimrx_appointment_settings (doctor_id, settings_json, updated_at)
             VALUES (:doctor_id, :settings_json, " . DbSql::now() . ")
             " . DbSql::upsert('doctor_id', ['settings_json', 'updated_at'], ['updated_at' => DbSql::now()])
        );
        $stmt->execute([
            'doctor_id' => $selectedDoctorId,
            'settings_json' => json_encode($savePayload, JSON_UNESCAPED_UNICODE),
        ]);

        $flashMessage = 'Appointment settings saved successfully!';
    } catch (Throwable $e) {
        $flashMessage = 'Error saving settings: ' . $e->getMessage();
        $flashType = 'error';
    }
}

// Fetch Current Settings
$currentSettings = $defaultSettings;
try {
    $stmt = $pdo->prepare(
        "SELECT settings_json FROM zimrx_appointment_settings WHERE doctor_id = :doctor_id LIMIT 1"
    );
    $stmt->execute(['doctor_id' => $selectedDoctorId]);
    $stored = json_decode((string)($stmt->fetchColumn() ?: ''), true);
    if (is_array($stored)) {
        $currentSettings = array_merge($defaultSettings, $stored);
    }
} catch (Throwable $e) {}

$page_title = "ZimRx - Appointment Settings";
$extra_css = ['assets/css/print_layout_editor.css'];
include 'header.php';
?>

<style>
/* ── Unified Appointment Settings matching Print & Page Setup ──────────── */
.apt-settings-form-body {
    margin-top: 1.25rem;
}

.apt-layout-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    align-items: start;
    margin-bottom: 1.5rem;
}

@media (max-width: 960px) {
    .apt-layout-grid {
        grid-template-columns: 1fr;
    }
}

.apt-section-card {
    background: var(--print-surface, #ffffff);
    border: 1px solid var(--print-border-soft, #e2e8f0);
    border-radius: var(--border-radius, 8px);
    box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
}

.apt-section-card-full {
    grid-column: 1 / -1;
}

.apt-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.15rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

.apt-card-head h2 {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.apt-card-head svg {
    color: var(--print-accent, #2563eb);
}

.apt-form-group {
    margin-bottom: 1.15rem;
}

.apt-form-group:last-child {
    margin-bottom: 0;
}

.apt-label-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.35rem;
}

.apt-label-wrap label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
}

.apt-unit-input {
    display: flex;
    align-items: center;
    border: 1px solid var(--print-border, #cbd5e1);
    border-radius: 6px;
    background: #ffffff;
    overflow: hidden;
    transition: all 0.15s ease;
}

.apt-unit-input:focus-within {
    border-color: var(--primary, #2563eb);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.apt-unit-input input {
    flex: 1;
    height: 38px;
    padding: 0 0.75rem;
    border: none;
    outline: none;
    font-size: 0.92rem;
    font-weight: 600;
    color: #0f172a;
    background: transparent;
}

.apt-unit-input .apt-unit-badge {
    background: #f8fafc;
    border-left: 1px solid var(--print-border, #cbd5e1);
    padding: 0 0.85rem;
    height: 38px;
    display: flex;
    align-items: center;
    font-size: 0.82rem;
    font-weight: 700;
    color: #64748b;
    white-space: nowrap;
}

.apt-explanation-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #2563eb;
    border-radius: 6px;
    padding: 0.9rem 1.1rem;
    font-size: 0.83rem;
    color: #475569;
    line-height: 1.5;
    margin-top: 1rem;
}

.apt-explanation-card strong {
    color: #0f172a;
    font-size: 0.85rem;
    display: block;
    margin-bottom: 0.25rem;
}

/* Weekday Table Grid */
.weekday-grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.75rem;
}

.weekday-item-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f8fafc;
}

.weekday-item-box strong {
    font-size: 0.88rem;
    color: #0f172a;
    min-width: 80px;
}

.weekday-item-box label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
}

.weekday-item-box input[type="time"] {
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 0.25rem 0.45rem;
    font-size: 0.82rem;
    font-weight: 600;
}

/* Token print checkboxes */
.token-grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.65rem;
}

.token-checkbox-card {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}

.token-checkbox-card:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.token-checkbox-card input[type="checkbox"] {
    accent-color: #2563eb;
}

.doctor-select-heading {
    padding: 0.45rem 0.85rem;
    font-size: 0.88rem;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #0f172a;
    outline: none;
}
</style>

<div class="layout-editor-page">

    <!-- ── Upper Heading Box matching Print Setup & Page Setup ── -->
    <div class="layout-editor-heading">
        <div>
            <h1>Appointment Settings</h1>
            <p>Configure chamber start timing, pacing, safety slots, consultation fees, and token print preferences.</p>
        </div>
        <div class="layout-editor-heading-actions">
            <?php if (!empty($doctors) && count($doctors) > 1): ?>
            <select class="doctor-select-heading" onchange="location.href='appointment_settings.php?doctor_id=' + this.value">
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?= (int)$doc['id'] ?>" <?= $selectedDoctorId === (int)$doc['id'] ? 'selected' : '' ?>>
                        Dr. <?= htmlspecialchars($doc['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <a href="appointments.php" class="btn btn-outline">Back to Queue</a>
            <button type="button" class="btn btn-outline" id="factory-reset-btn">Reset to Defaults</button>
            <button type="submit" form="appointment-settings-form" class="btn btn-primary">Save Settings</button>
        </div>
    </div>

    <!-- ── Flash Toast ──────────────────────────────────────── -->
    <?php if ($flashMessage !== ''): ?>
        <div class="admin-flash" style="<?= $flashType === 'error' ? 'background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5;' : 'background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;' ?> margin-top: 1rem; margin-bottom: 0;">
            <?= htmlspecialchars($flashMessage) ?>
        </div>
    <?php endif; ?>

    <!-- ── Reset Confirmation Modal ──────────────────────────── -->
    <div id="apt-confirm-modal" class="print-setup-toast" hidden>
        <div class="print-setup-toast-panel" role="dialog" aria-modal="true" style="width: min(100%, 400px); text-align: center; gap: 1.25rem; background: #ffffff; padding: 1.75rem; border-radius: 12px; box-shadow: 0 15px 35px rgba(0,0,0,0.25); border: 1px solid #cbd5e1;">
            <div style="font-size: 2.25rem; line-height: 1;">⚠️</div>
            <strong style="font-size: 1.15rem; color: #1e293b; display: block;">Reset to Defaults?</strong>
            <p style="font-size: 0.875rem; color: #64748b; margin: 0; line-height: 1.5;">
                Are you sure you want to restore the appointment chamber timings, safety slots, fees, and token print options to default settings?
            </p>
            <div style="display: flex; gap: 0.75rem; width: 100%; justify-content: center; margin-top: 0.5rem;">
                <button type="button" id="confirm-reset-cancel" class="btn btn-outline" style="flex: 1; padding: 0.5rem 1rem;">Cancel</button>
                <button type="button" id="confirm-reset-proceed" class="btn btn-primary" style="flex: 1; padding: 0.5rem 1rem; background: #dc2626; border-color: #dc2626;">Yes, Reset</button>
            </div>
        </div>
    </div>

    <!-- ── Settings Form ────────────────────────────────────── -->
    <form method="POST" id="appointment-settings-form" class="apt-settings-form-body" action="appointment_settings.php<?= $selectedDoctorId ? '?doctor_id=' . $selectedDoctorId : '' ?>">

        <div class="apt-layout-grid">

            <!-- Card 1: Chamber Timing & Pace -->
            <div class="apt-section-card">
                <div class="apt-card-head">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Chamber Timing &amp; Pacing
                    </h2>
                </div>

                <div class="apt-form-group">
                    <div class="apt-label-wrap">
                        <label for="default_start_time">Default Chamber Start Time</label>
                    </div>
                    <div class="apt-unit-input">
                        <input type="time" id="default_start_time" name="default_start_time" value="<?= htmlspecialchars((string)($currentSettings['default_start_time'] ?? '14:00')) ?>" required>
                    </div>
                </div>

                <div class="apt-form-group">
                    <div class="apt-label-wrap">
                        <label for="minutes_per_patient">Consultation Time Per Patient</label>
                    </div>
                    <div class="apt-unit-input">
                        <input type="number" id="minutes_per_patient" name="minutes_per_patient" min="1" max="60" value="<?= (int)($currentSettings['minutes_per_patient'] ?? 5) ?>" required>
                        <span class="apt-unit-badge">Minutes</span>
                    </div>
                </div>

                <div class="apt-form-group">
                    <div class="apt-label-wrap">
                        <label for="blank_slots">Initial Safety Blank Slots Everyday</label>
                    </div>
                    <div class="apt-unit-input">
                        <input type="number" id="blank_slots" name="blank_slots" min="0" max="20" value="<?= (int)($currentSettings['blank_slots'] ?? 3) ?>" required>
                        <span class="apt-unit-badge">Slots</span>
                    </div>
                </div>

                <div class="apt-explanation-card">
                    <strong>Why Safety Slots?</strong>
                    As in Bangladesh, our chamber system is a serial-based contract, consulting or treating an emergency patient or a fellow colleague (doctor) out of serial might be a breach of contract. So to be safe, we are setting these few empty serials where we can place an emergency patient, a fellow colleague, or a first-degree relative. These won't count as nepotism, as treating an emergency patient is necessary, and treating a doctor earlier is better as it is our professional courtesy, as well as giving him an opportunity to go back to his workplace earlier and treat more patients.
                </div>
            </div>

            <!-- Card 2: Consultation & Revisit Fees -->
            <div class="apt-section-card">
                <div class="apt-card-head">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Consultation &amp; Revisit Fees
                    </h2>
                </div>

                <div class="apt-form-group">
                    <div class="apt-label-wrap">
                        <label for="visit_fee">Standard New Patient Consultation Fee</label>
                    </div>
                    <div class="apt-unit-input">
                        <input type="number" id="visit_fee" name="visit_fee" min="0" step="10" value="<?= (float)($currentSettings['visit_fee'] ?? 500) ?>" required>
                        <span class="apt-unit-badge">৳ BDT</span>
                    </div>
                </div>

                <div class="apt-form-group">
                    <div class="apt-label-wrap">
                        <label for="revisit_fee">Follow-up / Revisit Fee</label>
                    </div>
                    <div class="apt-unit-input">
                        <input type="number" id="revisit_fee" name="revisit_fee" min="0" step="10" value="<?= (float)($currentSettings['revisit_fee'] ?? 400) ?>" required>
                        <span class="apt-unit-badge">৳ BDT</span>
                    </div>
                </div>

                <div class="apt-form-group">
                    <div class="apt-label-wrap">
                        <label for="revisit_validity_days">Revisit Fee Validity Window</label>
                    </div>
                    <div class="apt-unit-input">
                        <input type="number" id="revisit_validity_days" name="revisit_validity_days" min="0" max="365" value="<?= (int)($currentSettings['revisit_validity_days'] ?? 60) ?>" required>
                        <span class="apt-unit-badge">Days</span>
                    </div>
                </div>

                <div class="apt-explanation-card">
                    <strong>Revisit Discount Recognition:</strong>
                    Patients visiting within this validity window are automatically calculated for discounted revisit pricing at the appointment registration desk.
                </div>
            </div>

            <!-- Card 3: Weekly Chamber Schedule -->
            <div class="apt-section-card apt-section-card-full">
                <div class="apt-card-head">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Weekly Chamber Schedule &amp; Exceptions
                    </h2>
                </div>

                <div class="weekday-grid-container">
                    <?php
                    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    $overrides = is_array($currentSettings['weekday_overrides'] ?? null) ? $currentSettings['weekday_overrides'] : [];
                    foreach ($dayNames as $dIdx => $dName):
                        $dRule = $overrides[(string)$dIdx] ?? [];
                        $isClosed = !empty($dRule['closed']);
                        $dayStart = !empty($dRule['start_time']) ? $dRule['start_time'] : ($currentSettings['default_start_time'] ?? '14:00');
                    ?>
                    <div class="weekday-item-box">
                        <strong><?= $dName ?></strong>
                        <label>
                            <input type="checkbox" name="weekdays[<?= $dIdx ?>][closed]" value="1" <?= $isClosed ? 'checked' : '' ?> class="weekday-closed-cb">
                            Closed
                        </label>
                        <label>
                            Start:
                            <input type="time" name="weekdays[<?= $dIdx ?>][start_time]" value="<?= htmlspecialchars((string)$dayStart) ?>" class="weekday-time-input">
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Card 4: Appointment Token Print Elements -->
            <div class="apt-section-card apt-section-card-full">
                <div class="apt-card-head">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Appointment Token Print Slip Fields
                    </h2>
                </div>
                <p style="font-size: 0.85rem; color: #64748b; margin-top: -0.5rem; margin-bottom: 1rem;">Select which patient and financial details appear on thermal/slip print tokens issued to queue patients.</p>

                <div class="token-grid-container">
                    <?php
                    $tokenOptions = [
                        'name' => 'Patient Name',
                        'age' => 'Patient Age',
                        'sex' => 'Gender (Sex)',
                        'reg' => 'Registration No',
                        'visit_no' => 'Visit Number',
                        'visit_id' => 'Visit ID Code',
                        'visit_fee' => 'Consultation Fee',
                        'discount' => 'Discount Amount',
                        'paid' => 'Paid Amount',
                    ];
                    $selectedTokens = is_array($currentSettings['token_fields'] ?? null) ? $currentSettings['token_fields'] : array_keys($tokenOptions);
                    foreach ($tokenOptions as $tKey => $tLabel):
                        $isChecked = in_array($tKey, $selectedTokens, true);
                    ?>
                    <label class="token-checkbox-card">
                        <input type="checkbox" name="token_fields[]" value="<?= $tKey ?>" <?= $isChecked ? 'checked' : '' ?> class="token-cb">
                        <span><?= htmlspecialchars($tLabel) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const resetBtn = document.getElementById('factory-reset-btn');
    const modal = document.getElementById('apt-confirm-modal');
    const cancelBtn = document.getElementById('confirm-reset-cancel');
    const proceedBtn = document.getElementById('confirm-reset-proceed');

    if (resetBtn && modal) {
        resetBtn.addEventListener('click', () => {
            modal.hidden = false;
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                modal.hidden = true;
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.hidden = true;
        });

        if (proceedBtn) {
            proceedBtn.addEventListener('click', () => {
                // Reset form fields to defaults
                document.getElementById('default_start_time').value = '14:00';
                document.getElementById('minutes_per_patient').value = '5';
                document.getElementById('blank_slots').value = '3';
                document.getElementById('visit_fee').value = '500';
                document.getElementById('revisit_fee').value = '400';
                document.getElementById('revisit_validity_days').value = '60';

                document.querySelectorAll('.weekday-closed-cb').forEach(cb => {
                    cb.checked = false;
                });
                document.querySelectorAll('.weekday-time-input').forEach(ti => {
                    ti.value = '14:00';
                });
                document.querySelectorAll('.token-cb').forEach(cb => {
                    cb.checked = true;
                });

                modal.hidden = true;

                // Auto-submit to persist defaults
                document.getElementById('appointment-settings-form').submit();
            });
        }
    }
});
</script>

<?php include 'footer.php'; ?>
