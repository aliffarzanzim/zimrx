<?php
declare(strict_types=1);

// Dosage phrase template manager: bilingual doses (e.g. 1+0+1), dosage form associations, and ordering.

require_once 'auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/rx_template_lib.php';

$phraseTemplateType = 'dose';
$phraseConfig = rx_template_config($phraseTemplateType);
$phraseDoctorId = rx_active_doctor_id();
$phrasePayload = [
    'config' => [
        'type' => $phraseConfig['type'],
        'title' => $phraseConfig['title'],
        'label_bn' => $phraseConfig['label_bn'],
        'label_en' => $phraseConfig['label_en'],
        'has_default_form' => $phraseConfig['has_default_form'],
    ],
    'settings' => rx_phrase_settings($phraseTemplateType, $phraseDoctorId),
    'rows' => rx_phrase_rows($phraseTemplateType, $phraseDoctorId, true),
];
$phraseDefaultFormHasValues = false;
foreach ($phrasePayload['rows'] as $phraseTemplateRow) {
    if (!empty(array_filter(rx_json_string_list($phraseTemplateRow['default_dosage_form'] ?? '')))) {
        $phraseDefaultFormHasValues = true;
        break;
    }
}
$page_title = $phraseConfig['title'];
include 'header.php';
?>
<link rel="stylesheet" href="assets/css/pages/phrase_template.css">

<div class="phrase-template-page zrx-page-container">
    <div class="phrase-template-card">
        <div class="phrase-hero">
            <div>
                <h1><?= htmlspecialchars($phraseConfig['title']) ?></h1>
                <p>Manage system, custom typed, and doctor-specific template order for the Rx <?= htmlspecialchars($phraseConfig['type']) ?> dropdown.</p>
            </div>
            <div class="phrase-status" id="phrase-status"></div>
        </div>
        <div class="phrase-toolbar">
            <div class="phrase-toolbar-main">
                <div class="phrase-settings-box">
                    <div class="phrase-settings-box-title">
                        <span class="phrase-settings-box-title-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"></path>
                                <path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.49 1a7.2 7.2 0 0 0-1.69-.98L14.5 2.42A.5.5 0 0 0 14 2h-4a.5.5 0 0 0-.5.42L9.12 5.07c-.61.24-1.18.56-1.69.98l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.04.32-.07.65-.07.98s.02.66.07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.49-1c.51.42 1.08.74 1.69.98l.38 2.65a.5.5 0 0 0 .5.42h4a.5.5 0 0 0 .5-.42l.38-2.65c.61-.24 1.18-.56 1.69-.98l2.49 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65Z"></path>
                            </svg>
                        </span>
                        <span>Sorting in prescription settings:</span>
                    </div>
                    <div class="phrase-settings-box-body">
                        <div class="phrase-segment">
                            <button type="button" data-mode="serial">Show as per this serial</button>
                            <button type="button" data-mode="usage">Show as per usage</button>
                        </div>
                        <label class="phrase-toggle"><input type="checkbox" id="phrase-show-custom"> Show custom typed templates also</label>
                        <div class="phrase-settings-reset-wrap">
                            <button type="button" class="phrase-btn phrase-settings-reset-btn" id="phrase-reset-settings">Reset view settings</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="phrase-bulk">
            <button type="button" class="phrase-btn primary" id="phrase-add">Add New</button>
                <div class="phrase-dropdown">
                    <button type="button" class="phrase-btn phrase-dropdown-trigger">Reset</button>
                <div class="phrase-dropdown-menu">
                    <button type="button" class="phrase-dropdown-item" data-bulk="reset_usage">Reset usage count</button>
                    <button type="button" class="phrase-dropdown-item danger" data-bulk="reset_all">Reset full</button>
                </div>
            </div>
            <div class="phrase-dropdown">
                    <button type="button" class="phrase-btn phrase-dropdown-trigger">Remove</button>
                <div class="phrase-dropdown-menu">
                    <button type="button" class="phrase-dropdown-item danger" data-bulk="remove_all">Remove All</button>
                    <button type="button" class="phrase-dropdown-item" data-bulk="remove_custom_typed">Remove custom typed templates</button>
                    <button type="button" class="phrase-dropdown-item" data-bulk="remove_added">Remove added templates</button>
                </div>
            </div>
            <div class="phrase-bulk-spacer"></div>
            <div class="phrase-filter">
                <select id="phrase-filter-field">
                    <option value="all">All (default)</option>
                    <option value="bn"><?= htmlspecialchars($phraseConfig['label_bn']) ?></option>
                    <option value="en"><?= htmlspecialchars($phraseConfig['label_en']) ?></option>
                    <option value="alias">Search alias</option>
                    <?php if ($phraseConfig['has_default_form']): ?><option value="form">Default dosage form</option><?php endif; ?>
                </select>
                <input type="search" id="phrase-search" placeholder="Search templates">
            </div>
        </div>
        <div class="phrase-table-wrap">
            <table class="phrase-table">
                <thead>
                    <tr>
                        <th style="width:42px;"></th>
                        <th style="width:270px;">Actions</th>
                        <th style="width:70px;">SL</th>
                        <th style="width:130px;">Tags</th>
                        <th><?= htmlspecialchars($phraseConfig['label_bn']) ?></th>
                        <th><?= htmlspecialchars($phraseConfig['label_en']) ?></th>
                        <th>Search Alias</th>
                        <?php if ($phraseConfig['has_default_form']): ?><th<?= !$phraseDefaultFormHasValues ? ' style="width:120px;"' : '' ?>>Default Dosage Form</th><?php endif; ?>
                        <th style="width:90px;">Usage Count</th>
                    </tr>
                </thead>
                <tbody id="phrase-body"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="phrase-modal-backdrop" id="phrase-modal" hidden>
    <div class="phrase-modal">
        <header id="phrase-modal-title">Edit Template</header>
        <div class="phrase-modal-body">
            <input type="hidden" id="phrase-row-id">
            <input type="hidden" id="phrase-static-id">
            <label class="phrase-toggle"><input type="checkbox" id="phrase-row-pinned"> Pin it</label>
            <div class="phrase-modal-field">
                <label><?= htmlspecialchars($phraseConfig['label_bn']) ?></label>
                <textarea id="phrase-value-bn" rows="2"></textarea>
            </div>
            <div class="phrase-modal-field">
                <label><?= htmlspecialchars($phraseConfig['label_en']) ?></label>
                <input type="text" id="phrase-value-en">
            </div>
            <div class="phrase-modal-field">
                <label>Search Alias</label>
                <textarea id="phrase-search-alias" rows="2"></textarea>
            </div>
            <?php if ($phraseConfig['has_default_form']): ?>
            <div class="phrase-modal-field">
                <label>Default Dosage Form</label>
                <input type="text" id="phrase-default-form" placeholder="Comma separated forms">
            </div>
            <?php endif; ?>
            <div class="phrase-modal-field" id="phrase-add-position-wrap">
                <label>Add position</label>
                <select id="phrase-add-position">
                    <option value="last">Add at the last</option>
                    <option value="first">Add at the first</option>
                </select>
            </div>
        </div>
        <div class="phrase-modal-actions">
            <button type="button" class="phrase-btn" id="phrase-cancel">Cancel</button>
            <button type="button" class="phrase-btn primary" id="phrase-save">Save</button>
        </div>
    </div>
</div>

<script id="phrasePayloadData" type="application/json"><?= json_encode($phrasePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="assets/js/pages/phrase_template.js"></script>

<?php include 'footer.php'; ?>

