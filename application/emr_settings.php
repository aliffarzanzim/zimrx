<?php
declare(strict_types=1);

require_once 'auth.php';
require_login();
require_once 'db.php';
require_once __DIR__ . '/lib/emr_identity_lib.php';

$isMultiDoctor = zimrx_is_multi_doctor($pdo);

// Multi-doctor security check: Only admin can manage global EMR settings in multi-doctor mode
if ($isMultiDoctor && !is_admin_user()) {
    header('Location: prescription.php');
    exit();
}

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $daily = max(10, (int)($_POST['daily_patient_flow'] ?? 999));
        $yearly = max(100, (int)($_POST['yearly_patient_flow'] ?? 99999));
        $regMode = strtolower((string)($_POST['reg_id_mode'] ?? 'sequential')) === 'random' ? 'random' : 'sequential';
        $visitMode = strtolower((string)($_POST['visit_id_mode'] ?? 'sequential')) === 'random' ? 'random' : 'sequential';
        $autoExpand = isset($_POST['auto_expand']) ? 1 : 0;

        zimrx_save_emr_settings($pdo, [
            'daily_patient_flow' => $daily,
            'yearly_patient_flow' => $yearly,
            'reg_id_mode' => $regMode,
            'visit_id_mode' => $visitMode,
            'auto_expand' => $autoExpand,
        ]);

        $flash = 'EMR configuration saved successfully.';
    } catch (Throwable $e) {
        $flash = 'Error saving settings: ' . $e->getMessage();
        $flashType = 'error';
    }
}

$settings = zimrx_get_emr_settings($pdo);

$page_title = 'ZimRx - EMR Settings';
$extra_css = ['assets/css/pages/print_layout_editor.css'];
include 'header.php';
?>

<link rel="stylesheet" href="assets/css/pages/emr_settings.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/emr_settings.css') ?>">

<div class="layout-editor-page">

    <!-- ── Header matching Print Setup & Page Setup ─────────── -->
    <div class="layout-editor-heading">
        <div>
            <h1>EMR Settings</h1>
            <p>Configure patient registration numbering, daily encounter IDs, and traffic capacity rules.</p>
        </div>
        <div class="layout-editor-heading-actions">
            <span class="dimension-chip" style="color: #2563eb; background: #eff6ff; border-color: #bfdbfe;">
                <?= $isMultiDoctor ? '🏢 Multi-Doctor Setup' : '🩺 Solo Doctor Setup' ?>
            </span>
            <button type="button" class="btn btn-outline" id="factory-reset-btn">Reset to Defaults</button>
            <button type="submit" form="emr-settings-form" class="btn btn-primary">Save Settings</button>
        </div>
    </div>

    <!-- ── Flash Toast ──────────────────────────────────────── -->
    <?php if ($flash): ?>
        <div class="admin-flash" style="<?= $flashType === 'error' ? 'background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5;' : 'background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;' ?> margin-top: 1rem; margin-bottom: 0;">
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>

    <!-- ── Reset Confirmation Modal ──────────────────────────── -->
    <div id="emr-confirm-modal" class="print-setup-toast" hidden>
        <div class="print-setup-toast-panel" role="dialog" aria-modal="true" style="width: min(100%, 380px); text-align: center; gap: 1.25rem; background: #ffffff; padding: 1.75rem; border-radius: 12px; box-shadow: 0 15px 35px rgba(0,0,0,0.25); border: 1px solid #cbd5e1;">
            <div style="font-size: 2.25rem; line-height: 1;">⚠️</div>
            <strong style="font-size: 1.15rem; color: #1e293b; display: block;">Reset to Defaults?</strong>
            <p style="font-size: 0.875rem; color: #64748b; margin: 0; line-height: 1.5;">
                Are you sure you want to restore the EMR patient flow limits and identity formats to default settings?
            </p>
            <div style="display: flex; gap: 0.75rem; width: 100%; justify-content: center; margin-top: 0.5rem;">
                <button type="button" id="confirm-reset-cancel" class="btn btn-outline" style="flex: 1; padding: 0.5rem 1rem;">Cancel</button>
                <button type="button" id="confirm-reset-proceed" class="btn btn-primary" style="flex: 1; padding: 0.5rem 1rem; background: #dc2626; border-color: #dc2626;">Yes, Reset</button>
            </div>
        </div>
    </div>

    <!-- ── Settings Form ────────────────────────────────────── -->
    <form method="post" id="emr-settings-form" class="emr-settings-body">

        <div class="emr-layout-grid">

            <!-- ── LEFT COLUMN: Flow Limits & Auto-Expansion ────── -->
            <div>
                <!-- Capacity Limits Card -->
                <div class="emr-section-card">
                    <div class="emr-card-head">
                        <h2>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                            Clinic Flow &amp; Capacity Limits
                        </h2>
                        <span class="emr-chip-tag" id="flow-status-tag">Dynamic Sizing</span>
                    </div>

                    <!-- Daily Flow -->
                    <div class="emr-field-group">
                        <div class="emr-label-wrap">
                            <label for="daily_patient_flow">Daily Patient Flow</label>
                            <span>Sets Visit ID Length</span>
                        </div>
                        <input type="number" name="daily_patient_flow" id="daily_patient_flow" class="emr-input-num" min="10" max="9999999" value="<?= htmlspecialchars((string)$settings['daily_patient_flow']) ?>" required>
                        <div class="emr-field-hint">
                            Governs zero-padding length for <strong>Daily Visit Encounter IDs (`visit_id`)</strong> (e.g. 999 = 3 digits, 99,999 = 5 digits). Increasing or decreasing adjusts future visits.
                        </div>
                        <div class="preset-chip-list">
                            <span>Presets:</span>
                            <button type="button" class="preset-chip-btn" onclick="setDailyFlow(999)">999 (Solo - 3 dig)</button>
                            <button type="button" class="preset-chip-btn" onclick="setDailyFlow(9999)">9,999 (Polyclinic - 4 dig)</button>
                            <button type="button" class="preset-chip-btn" onclick="setDailyFlow(99999)">99,999 (Hospital - 5 dig)</button>
                        </div>
                    </div>

                    <!-- Yearly Flow -->
                    <div class="emr-field-group">
                        <div class="emr-label-wrap">
                            <label for="yearly_patient_flow">Yearly Patient Flow</label>
                            <span>Sets Reg ID Length</span>
                        </div>
                        <input type="number" name="yearly_patient_flow" id="yearly_patient_flow" class="emr-input-num" min="100" max="99999999" value="<?= htmlspecialchars((string)$settings['yearly_patient_flow']) ?>" required>
                        <div class="emr-field-hint">
                            Governs digit capacity for <strong>Patient Registration IDs (`reg_no`)</strong> (e.g. 99,999 = 5 digits, 999,999 = 6 digits). Adjusting updates future registrations.
                        </div>
                        <div class="preset-chip-list">
                            <span>Presets:</span>
                            <button type="button" class="preset-chip-btn" onclick="setYearlyFlow(9999)">9,999 (4 dig)</button>
                            <button type="button" class="preset-chip-btn" onclick="setYearlyFlow(99999)">99,999 (Solo - 5 dig)</button>
                            <button type="button" class="preset-chip-btn" onclick="setYearlyFlow(999999)">999,999 (Polyclinic - 6 dig)</button>
                        </div>
                    </div>
                </div>

                <!-- Auto-Expansion Card -->
                <div class="emr-section-card">
                    <div class="emr-card-head">
                        <h2>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Self-Healing Auto-Expansion
                        </h2>
                    </div>

                    <label class="emr-toggle-card">
                        <input type="checkbox" name="auto_expand" value="1" <?= !empty($settings['auto_expand']) ? 'checked' : '' ?>>
                        <div>
                            <strong style="color: #0f172a; font-size: 0.88rem;">Auto-Upgrade Digit Capacity on Rush Overflows</strong>
                            <p class="emr-field-hint" style="margin-top: 0.2rem;">
                                If your clinic encounters an unexpected patient surge exceeding your limit (e.g. Patient 1,000 on a 3-digit limit), the system automatically expands the ID length without errors and permanently upgrades your settings to maintain uniform string length for all future days.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ── RIGHT COLUMN: Registration ID & Visit ID ─────── -->
            <div>
                <!-- Registration ID Card -->
                <div class="emr-section-card">
                    <div class="emr-card-head">
                        <h2>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="13" y2="12"/></svg>
                            Patient Registration ID (`reg_no`)
                        </h2>
                        <span class="emr-chip-tag" id="reg-digit-badge">5 Digits</span>
                    </div>
                    <div class="emr-field-hint" style="margin-bottom: 0.5rem;">
                        Prefix: <strong>P + 2-digit Year</strong> (e.g. <code>P<?= date('y') ?></code> for <?= date('Y') ?>). Choose whether the annual sequence is sequential or obfuscated random.
                    </div>

                    <div class="mode-card-deck">
                        <label class="mode-select-card <?= $settings['reg_id_mode'] === 'sequential' ? 'selected' : '' ?>" id="opt-reg-seq">
                            <div class="mode-head-row">
                                <input type="radio" name="reg_id_mode" value="sequential" <?= $settings['reg_id_mode'] === 'sequential' ? 'checked' : '' ?> onchange="updateEmrModes()">
                                Sequential Mode
                            </div>
                            <div class="mode-desc-text">
                                Predictable continuous numbers (Patient 1, 2, 3...). Ideal for standard clinic filing &amp; tokens.
                            </div>
                            <div class="mode-sample-pill" id="sample-reg-seq">
                                P<?= date('y') ?>00001
                            </div>
                        </label>

                        <label class="mode-select-card <?= $settings['reg_id_mode'] === 'random' ? 'selected' : '' ?>" id="opt-reg-rand">
                            <div class="mode-head-row">
                                <input type="radio" name="reg_id_mode" value="random" <?= $settings['reg_id_mode'] === 'random' ? 'checked' : '' ?> onchange="updateEmrModes()">
                                Random / Obfuscated
                            </div>
                            <div class="mode-desc-text">
                                Secure non-repeating numbers. Hides total patient volume from outside parties.
                            </div>
                            <div class="mode-sample-pill" id="sample-reg-rand">
                                P<?= date('y') ?>84932
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Visit ID Card -->
                <div class="emr-section-card">
                    <div class="emr-card-head">
                        <h2>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            Visit Encounter ID (`visit_id`)
                        </h2>
                        <span class="emr-chip-tag" id="visit-digit-badge">3 Digits</span>
                    </div>
                    <div class="emr-field-hint" style="margin-bottom: 0.5rem;">
                        Prefix: <strong>V + 6-digit Date</strong> (e.g. <code>V<?= date('ymd') ?></code> for <?= date('d M Y') ?>). Choose whether the daily encounter sequence is sequential or obfuscated.
                    </div>

                    <div class="mode-card-deck">
                        <label class="mode-select-card <?= $settings['visit_id_mode'] === 'sequential' ? 'selected' : '' ?>" id="opt-visit-seq">
                            <div class="mode-head-row">
                                <input type="radio" name="visit_id_mode" value="sequential" <?= $settings['visit_id_mode'] === 'sequential' ? 'checked' : '' ?> onchange="updateEmrModes()">
                                Sequential Mode
                            </div>
                            <div class="mode-desc-text">
                                Daily encounter 1, 2, 3... Matches appointment token queue order.
                            </div>
                            <div class="mode-sample-pill" id="sample-visit-seq">
                                V<?= date('ymd') ?>001
                            </div>
                        </label>

                        <label class="mode-select-card <?= $settings['visit_id_mode'] === 'random' ? 'selected' : '' ?>" id="opt-visit-rand">
                            <div class="mode-head-row">
                                <input type="radio" name="visit_id_mode" value="random" <?= $settings['visit_id_mode'] === 'random' ? 'checked' : '' ?> onchange="updateEmrModes()">
                                Random / Obfuscated
                            </div>
                            <div class="mode-desc-text">
                                Randomized daily codes. Hides day's foot traffic from competing pharmacies.
                            </div>
                            <div class="mode-sample-pill" id="sample-visit-rand">
                                V<?= date('ymd') ?>849
                            </div>
                        </label>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── FULL WIDTH: Live Dynamic ID Preview Console ──────── -->
        <div class="emr-preview-shell">
            <div class="emr-preview-head">
                <h3>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    Live Dynamic ID Preview (Real-Time Output)
                </h3>
                <span style="font-size: 0.72rem; color: #94a3b8; font-family: monospace;">Auto-Updating</span>
            </div>
            <div class="emr-preview-grid">
                <div class="emr-preview-item">
                    <div class="emr-preview-label">
                        <span>Patient Registration ID (`reg_no`)</span>
                        <span id="preview-reg-tag" style="color: #38bdf8;">5 Digits</span>
                    </div>
                    <div class="emr-preview-code" id="preview-reg-val">P<?= date('y') ?>00001</div>
                    <div class="emr-preview-sub" id="preview-reg-meta">Sequential Mode &bull; Up to 99,999 patients/yr</div>
                </div>

                <div class="emr-preview-item">
                    <div class="emr-preview-label">
                        <span>Visit Encounter ID (`visit_id`)</span>
                        <span id="preview-visit-tag" style="color: #38bdf8;">3 Digits</span>
                    </div>
                    <div class="emr-preview-code" id="preview-visit-val">V<?= date('ymd') ?>001</div>
                    <div class="emr-preview-sub" id="preview-visit-meta">Sequential Mode &bull; Up to 999 patients/day</div>
                </div>
            </div>
        </div>

    </form>
</div>

<script id="emrSettingsConfig" type="application/json">
<?= json_encode([
    'currentYearCode' => date('y'),
    'currentDateCode' => date('ymd'),
    'isMultiDoctorMode' => $isMultiDoctor,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="assets/js/pages/emr_settings.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/emr_settings.js') ?>"></script>

<?php include 'footer.php'; ?>
