<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$page_title = 'ZimRx - Patient Particulars Settings';
$extra_css = [
    'assets/css/pages/settings.css',
    'assets/css/pages/patient_particulars_settings.css',
];
include __DIR__ . '/header.php';
?>

<main class="zrx-patient-particulars-settings zrx-page-container">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>Patient Particulars Settings</h1>
            <p>Choose whether each standard field appears on the main form, under More Info, or stays hidden.</p>
        </div>
    </header>

    <section class="patient-fields-config-panel" id="patient-fields-config-panel" data-settings-page>
        <div class="config-panel-intro">
            <strong>Standard field placement</strong>
            <span>Hidden fields remain part of patient records. Placement changes where fields appear in prescription and appointment forms.</span>
        </div>
        <div class="config-fields-grid" id="config-fields-grid"></div>
        <div class="config-panel-footer">
            <span id="patient-fields-settings-status" role="status" aria-live="polite"></span>
            <div>
                <button type="button" id="btn-reset-field-config" class="btn btn-outline btn-sm">Reset Defaults</button>
                <button type="button" id="btn-save-field-config" class="btn btn-primary btn-sm">Save Placement</button>
            </div>
        </div>
    </section>

    <section class="patient-fields-config-panel" aria-labelledby="custom-patient-fields-heading">
        <div class="config-panel-intro">
            <strong id="custom-patient-fields-heading">Custom patient fields</strong>
            <span>Custom field values are stored with the patient record. Set a field to Hidden to remove it from forms without deleting its values.</span>
        </div>
        <div class="custom-field-add-row">
            <label>
                Field label
                <input type="text" id="new-custom-field-label" maxlength="60" autocomplete="off">
            </label>
            <label>
                Type
                <select id="new-custom-field-type">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="select">Choice</option>
                </select>
            </label>
            <label>
                Placement
                <select id="new-custom-field-placement">
                    <option value="more_info">More Info</option>
                    <option value="main">Main form</option>
                    <option value="hidden">Hidden</option>
                </select>
            </label>
            <label class="custom-field-options-label" id="new-custom-field-options-wrap" hidden>
                Choices
                <input type="text" id="new-custom-field-options" maxlength="500" placeholder="Separate choices with commas" autocomplete="off">
            </label>
            <button type="button" id="btn-add-custom-field" class="btn btn-outline btn-sm">Add Field</button>
        </div>
        <div class="config-fields-grid" id="custom-fields-grid"></div>
        <div class="custom-field-package-actions">
            <input type="file" id="custom-fields-package-file" accept="application/json,.json" hidden>
            <button type="button" id="btn-export-custom-fields" class="btn btn-outline btn-sm">Export Fields</button>
            <button type="button" id="btn-import-custom-fields" class="btn btn-outline btn-sm">Import Fields</button>
        </div>
        <div class="config-panel-footer">
            <span id="custom-fields-settings-status" role="status" aria-live="polite"></span>
            <button type="button" id="btn-save-custom-fields" class="btn btn-primary btn-sm">Save Custom Fields</button>
        </div>
    </section>
</main>

<script src="assets/js/modules/patient_particulars.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/patient_particulars.js') ?>"></script>
<script src="assets/js/pages/patient_particulars_settings.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/patient_particulars_settings.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
