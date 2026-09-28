<?php
require_once 'auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/rx_template_lib.php';

$phraseTemplateType = 'advice';
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
    'default_rows' => rx_phrase_static_rows($phraseTemplateType),
];
$page_title = $phraseConfig['title'];
include 'header.php';
?>
<link rel="stylesheet" href="assets/css/advice_template.css">

<div class="phrase-template-page">
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
                            <button type="button" class="phrase-btn phrase-settings-reset-btn" id="phrase-reset-settings">Reset default settings</button>
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
                    <option value="category">Category</option>
                    <option value="bn"><?= htmlspecialchars($phraseConfig['label_bn']) ?></option>
                    <option value="en"><?= htmlspecialchars($phraseConfig['label_en']) ?></option>
                </select>
                <input type="search" id="phrase-search" placeholder="Search templates">
            </div>
        </div>
        <div class="phrase-table-wrap">
            <table class="phrase-table">
                <thead>
                    <tr>
                        <th style="width:44px;"></th>
                        <th style="width:265px; text-align:center;">Actions</th>
                        <th class="phrase-sortable-th" style="width:60px; text-align:center;">
                            <button type="button" class="phrase-sort-button" data-sort-direction="desc">
                                <span class="phrase-sort-label">SL</span>
                                <span class="phrase-sort-icon">
                                    <span class="phrase-sort-triangle up"></span>
                                    <span class="phrase-sort-triangle down"></span>
                                </span>
                            </button>
                        </th>
                        <th style="width:130px;">Tags</th>
                        <th class="phrase-col-category" style="width:200px;">Category</th>
                        <th><?= htmlspecialchars($phraseConfig['label_bn']) ?></th>
                        <th><?= htmlspecialchars($phraseConfig['label_en']) ?></th>
                        <th class="phrase-sortable-th" style="width:90px; text-align:center;">
                            <button type="button" class="phrase-sort-button" data-sort-direction="desc">
                                <span class="phrase-sort-label">USAGE<br>COUNT</span>
                                <span class="phrase-sort-icon">
                                    <span class="phrase-sort-triangle up"></span>
                                    <span class="phrase-sort-triangle down"></span>
                                </span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody id="phrase-body"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="phrase-modal-backdrop" id="phrase-modal" hidden>
    <div class="phrase-modal">
        <header class="phrase-modal-header"><h2 id="phrase-modal-title">Edit Category & Advices</h2></header>
        <div class="phrase-modal-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="phrase-modal-field">
                    <label>Category Bangla</label>
                    <textarea id="modal-category-bn" placeholder="Category Bangla" rows="1" class="modal-advice-textarea"></textarea>
                </div>
                <div class="phrase-modal-field">
                    <label>Category English</label>
                    <textarea id="modal-category-en" placeholder="Category English" rows="1" class="modal-advice-textarea"></textarea>
                </div>
                <div class="phrase-modal-field" style="grid-column: 1 / -1;">
                    <label>Category Search Alias</label>
                    <textarea id="modal-category-alias" placeholder="Category Search Alias" rows="1" class="modal-advice-textarea"></textarea>
                </div>
            </div>
            <div>
                <label style="font-size: 0.78rem; font-weight: 800; text-transform: uppercase; color: #334155; letter-spacing: 0.04em; margin-bottom: 0.5rem; display: block;">Advices inside this Category</label>
                <div style="display: grid; grid-template-columns: 32px 1fr 1fr 40px; gap: 0.75rem; padding: 0 0.75rem; margin-bottom: 0.5rem; align-items: center;">
                    <div></div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Advice Bangla</div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Advice English</div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; text-align: center;">Delete</div>
                </div>
                <div id="modal-advices-container" style="display: grid; gap: 1rem;"></div>
                <button type="button" class="phrase-btn primary" id="modal-add-advice-btn" style="margin-top: 1rem;">+ Add Advice</button>
            </div>
        </div>
        <div class="phrase-modal-actions">
            <button type="button" class="phrase-modal-btn" id="phrase-cancel">Cancel</button>
            <button type="button" class="phrase-modal-btn primary" id="phrase-save">Save Category</button>
        </div>
    </div>
</div>

<script id="phrasePayloadData" type="application/json"><?= json_encode($phrasePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="assets/js/pages/advice_template.js"></script>

<?php include 'footer.php'; ?>
