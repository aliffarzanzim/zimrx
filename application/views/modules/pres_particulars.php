<?php
declare(strict_types=1);

// Patient particulars header section: demographics, visit identifiers, vitals, and action buttons.
$prescription_prefill = is_array($prescription_prefill ?? null) ? $prescription_prefill : [];
$pres_value = static function (string $key, string $default = '') use ($prescription_prefill): string {
    return htmlspecialchars((string)($prescription_prefill[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$pres_selected = static function (string $key, string $value, string $default = '') use ($prescription_prefill): string {
    return (string)($prescription_prefill[$key] ?? $default) === $value ? ' selected' : '';
};
?>
<section class="patient-particulars zrx-page-container">
    <div class="patient-info">
        <input type="hidden" id="patient-id" value="<?= $pres_value('patient_id') ?>">
        <input type="hidden" id="appointment-id" value="<?= $pres_value('appointment_id') ?>">
        <input type="hidden" id="appointment-no" value="<?= $pres_value('appointment_no') ?>">
        <input type="hidden" id="appointment-time" value="<?= $pres_value('appointment_time') ?>">
        <input type="hidden" id="visit-record-id" value="<?= $pres_value('visit_record_id', '0') ?>">
        <input type="hidden" id="visit-revision" value="<?= $pres_value('revision', '1') ?>">
        
        <?php include ZIMRX_BASE_DIR . '/views/components/patient_particulars_fields.php'; ?>


        <?php
        $has_patient_profile = !empty($prescription_prefill['patient_id']) && (int)$prescription_prefill['patient_id'] > 0;
        ?>
        <!-- ROW 3: Patient Profile Quick Link -->
        <div class="p-row-profile-action">
            <button type="button" id="btn-open-patient-profile" class="btn-open-patient-profile"<?= $has_patient_profile ? '' : ' disabled' ?> title="Open Patient Profile (EMR)">
                <?= zrx_icon('user', 14) ?>
                <span>Patient Profile</span>
                <?= zrx_icon('external-link', 12) ?>
            </button>
        </div>

    </div>
    
    <div class="patient-actions">
        <button type="button" class="btn btn-primary" id="btn-save-print"><?= zrx_icon('printer', 14) ?>Save & Print</button>
        <button type="button" class="btn btn-success" id="btn-save-only"><?= zrx_icon('save', 14) ?>Save Only</button>
        <a href="prescription_preview.php" target="_blank" class="btn btn-secondary" id="btn-preview-prescription"><?= zrx_icon('eye', 14) ?>Preview</a>
        <div class="clear-action-wrap">
            <button type="button" class="btn btn-outline" id="btn-clear-fields" aria-haspopup="menu" aria-expanded="false"><?= zrx_icon('download', 14) ?>Clear</button>
            <div class="clear-options-menu" id="clear-options-menu" role="menu" hidden>
                <button type="button" role="menuitem" data-clear-action="all">Clear all including particulars</button>
                <button type="button" role="menuitem" data-clear-action="prescription">Clear the prescription</button>
            </div>
        </div>
    </div>
</section>
