<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'emr_identity_lib.php';

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
$extra_css = ['assets/css/print_layout_editor.css'];
include 'header.php';
?>

<style>
/* ── Unified EMR Settings Layout matching Print Setup ──────────────────── */
.emr-settings-body {
    margin-top: 1.25rem;
}

.emr-layout-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    align-items: start;
}

@media (max-width: 1024px) {
    .emr-layout-grid {
        grid-template-columns: 1fr;
    }
}

.emr-section-card {
    background: var(--print-surface, #ffffff);
    border: 1px solid var(--print-border-soft, #e2e8f0);
    border-radius: var(--border-radius, 8px);
    box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
}

.emr-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

.emr-card-head h2 {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.emr-chip-tag {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}

.emr-field-group {
    margin-bottom: 1.15rem;
}

.emr-field-group:last-child {
    margin-bottom: 0;
}

.emr-label-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.35rem;
}

.emr-label-wrap label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
}

.emr-label-wrap span {
    font-size: 0.75rem;
    font-weight: 600;
    color: #2563eb;
}

.emr-field-hint {
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.4;
    margin-top: 0.35rem;
}

.emr-input-num {
    width: 100%;
    height: 38px;
    padding: 0 0.75rem;
    border: 1px solid var(--print-border, #cbd5e1);
    border-radius: 6px;
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
    background: #f8fafc;
    transition: all 0.15s ease;
    box-sizing: border-box;
}

.emr-input-num:focus {
    outline: none;
    border-color: var(--primary, #2563eb);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.preset-chip-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.4rem;
}

.preset-chip-list span {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #94a3b8;
    margin-right: 0.15rem;
}

.preset-chip-btn {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.22rem 0.55rem;
    border-radius: 5px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}

.preset-chip-btn:hover {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}

/* ── Mode Selection Cards ──────────────────────────────────────────────── */
.mode-card-deck {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
    margin-top: 0.5rem;
}

@media (max-width: 640px) {
    .mode-card-deck {
        grid-template-columns: 1fr;
    }
}

.mode-select-card {
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.9rem;
    cursor: pointer;
    transition: all 0.15s ease;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.mode-select-card:hover {
    border-color: #93c5fd;
    background: #f8fafc;
}

.mode-select-card.selected {
    border-color: #2563eb;
    background: #f0f7ff;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
}

.mode-head-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #0f172a;
    font-size: 0.9rem;
}

.mode-head-row input[type="radio"] {
    accent-color: #2563eb;
    width: 16px;
    height: 16px;
    margin: 0;
}

.mode-desc-text {
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.35;
    padding-left: 1.5rem;
}

.mode-sample-pill {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.75rem;
    font-weight: 700;
    color: #2563eb;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    display: inline-block;
    align-self: flex-start;
    margin-top: 0.25rem;
    margin-left: 1.5rem;
}

/* ── Live Preview Console ──────────────────────────────────────────────── */
.emr-preview-shell {
    background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
    color: #f8fafc;
    border-radius: var(--border-radius, 8px);
    padding: 1.25rem 1.5rem;
    margin-top: 1.25rem;
    border: 1px solid #334155;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.15);
}

.emr-preview-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.emr-preview-head h3 {
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #38bdf8;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.emr-preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

@media (max-width: 640px) {
    .emr-preview-grid {
        grid-template-columns: 1fr;
    }
}

.emr-preview-item {
    background: rgba(15, 23, 42, 0.7);
    border: 1px solid #334155;
    border-radius: 6px;
    padding: 0.85rem 1rem;
}

.emr-preview-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #94a3b8;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.emr-preview-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 1.2rem;
    font-weight: 800;
    color: #4ade80;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
}

.emr-preview-sub {
    font-size: 0.72rem;
    color: #cbd5e1;
}

/* ── Checkbox Switch ──────────────────────────────────────────────────── */
.emr-toggle-card {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.85rem 1rem;
    cursor: pointer;
    transition: all 0.15s ease;
}

.emr-toggle-card:hover {
    border-color: #cbd5e1;
    background: #ffffff;
}

.emr-toggle-card input[type="checkbox"] {
    margin-top: 0.15rem;
    accent-color: #2563eb;
    width: 17px;
    height: 17px;
    flex-shrink: 0;
}
</style>

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

<script>
const currentYearCode = <?= json_encode(date('y')) ?>;
const currentDateCode = <?= json_encode(date('ymd')) ?>;
const isMultiDoctorMode = <?= json_encode($isMultiDoctor) ?>;

function getDigits(num) {
    const val = Math.max(1, parseInt(num, 10) || 1);
    return String(val).length;
}

function setDailyFlow(val) {
    document.getElementById('daily_patient_flow').value = val;
    renderEmrPreview();
}

function setYearlyFlow(val) {
    document.getElementById('yearly_patient_flow').value = val;
    renderEmrPreview();
}

function updateEmrModes() {
    const regMode = document.querySelector('input[name="reg_id_mode"]:checked')?.value || 'sequential';
    const visitMode = document.querySelector('input[name="visit_id_mode"]:checked')?.value || 'sequential';

    document.getElementById('opt-reg-seq').classList.toggle('selected', regMode === 'sequential');
    document.getElementById('opt-reg-rand').classList.toggle('selected', regMode === 'random');
    document.getElementById('opt-visit-seq').classList.toggle('selected', visitMode === 'sequential');
    document.getElementById('opt-visit-rand').classList.toggle('selected', visitMode === 'random');

    renderEmrPreview();
}

function renderEmrPreview() {
    const dailyFlow = parseInt(document.getElementById('daily_patient_flow').value, 10) || 999;
    const yearlyFlow = parseInt(document.getElementById('yearly_patient_flow').value, 10) || 99999;
    const regMode = document.querySelector('input[name="reg_id_mode"]:checked')?.value || 'sequential';
    const visitMode = document.querySelector('input[name="visit_id_mode"]:checked')?.value || 'sequential';

    const dailyDigits = getDigits(dailyFlow);
    const yearlyDigits = getDigits(yearlyFlow);

    // Update Badges
    document.getElementById('reg-digit-badge').textContent = yearlyDigits + ' Digits (' + yearlyFlow.toLocaleString() + '/yr)';
    document.getElementById('visit-digit-badge').textContent = dailyDigits + ' Digits (' + dailyFlow.toLocaleString() + '/day)';
    document.getElementById('preview-reg-tag').textContent = yearlyDigits + ' Digits';
    document.getElementById('preview-visit-tag').textContent = dailyDigits + ' Digits';

    // Update Card Samples
    document.getElementById('sample-reg-seq').textContent = 'P' + currentYearCode + String(1).padStart(yearlyDigits, '0');
    const randRegSample = String(Math.floor((Math.pow(10, yearlyDigits) - 1) * 0.84932) || 84932).padStart(yearlyDigits, '0').slice(-yearlyDigits);
    document.getElementById('sample-reg-rand').textContent = 'P' + currentYearCode + randRegSample;

    document.getElementById('sample-visit-seq').textContent = 'V' + currentDateCode + String(1).padStart(dailyDigits, '0');
    const randVisitSample = String(Math.floor((Math.pow(10, dailyDigits) - 1) * 0.849) || 849).padStart(dailyDigits, '0').slice(-dailyDigits);
    document.getElementById('sample-visit-rand').textContent = 'V' + currentDateCode + randVisitSample;

    // Registration ID Live Output
    let sampleReg = '';
    if (regMode === 'sequential') {
        sampleReg = 'P' + currentYearCode + String(1).padStart(yearlyDigits, '0');
    } else {
        sampleReg = 'P' + currentYearCode + randRegSample;
    }
    document.getElementById('preview-reg-val').textContent = sampleReg;
    document.getElementById('preview-reg-meta').textContent = `${regMode === 'sequential' ? 'Sequential' : 'Random'} Mode • Up to ${yearlyFlow.toLocaleString()} patients/yr`;

    // Visit ID Live Output
    let sampleVisit = '';
    if (visitMode === 'sequential') {
        sampleVisit = 'V' + currentDateCode + String(1).padStart(dailyDigits, '0');
    } else {
        sampleVisit = 'V' + currentDateCode + randVisitSample;
    }
    document.getElementById('preview-visit-val').textContent = sampleVisit;
    document.getElementById('preview-visit-meta').textContent = `${visitMode === 'sequential' ? 'Sequential' : 'Random'} Mode • Up to ${dailyFlow.toLocaleString()} encounters/day`;
}

// Reset to Defaults Modal Handling
const resetModal = document.getElementById('emr-confirm-modal');
document.getElementById('factory-reset-btn').addEventListener('click', () => {
    resetModal.hidden = false;
});
document.getElementById('confirm-reset-cancel').addEventListener('click', () => {
    resetModal.hidden = true;
});
document.getElementById('confirm-reset-proceed').addEventListener('click', () => {
    resetModal.hidden = true;
    document.getElementById('daily_patient_flow').value = 999;
    document.getElementById('yearly_patient_flow').value = 99999;
    document.querySelector('input[name="reg_id_mode"][value="sequential"]').checked = true;
    document.querySelector('input[name="visit_id_mode"][value="sequential"]').checked = true;
    document.querySelector('input[name="auto_expand"]').checked = true;
    updateEmrModes();
});

document.getElementById('daily_patient_flow').addEventListener('input', renderEmrPreview);
document.getElementById('yearly_patient_flow').addEventListener('input', renderEmrPreview);
document.querySelectorAll('input[name="reg_id_mode"]').forEach(r => r.addEventListener('change', updateEmrModes));
document.querySelectorAll('input[name="visit_id_mode"]').forEach(r => r.addEventListener('change', updateEmrModes));

// Initial render on boot
renderEmrPreview();
</script>

<?php include 'footer.php'; ?>
