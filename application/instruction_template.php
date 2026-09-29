<?php
declare(strict_types=1);

// Instruction template manager: bilingual intake directions, dosage form linking, aliases, and prescription ordering.

require_once 'auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/rx_regimen_lib.php';
$page_title = 'Instruction Template';

$instructionTemplateDoctorId = rx_active_doctor_id();
$instructionTemplateInitialPayload = [
    'settings' => rx_instruction_template_settings($instructionTemplateDoctorId),
    'rows' => rx_instruction_template_rows($instructionTemplateDoctorId, true),
    'default_rows' => rx_static_instruction_rows(),
];

foreach ($instructionTemplateInitialPayload['rows'] as $index => &$instructionTemplateRow) {
    $instructionTemplateRow['client_key'] = 'row-' . ($index + 1);
}
unset($instructionTemplateRow);

function instruction_template_escape(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function instruction_template_tags(array $row): string {
    $tags = [];
    $kind = (string)($row['kind'] ?? '');

    if ($kind === 'system') {
        $tags[] = '<span class="instruction-tag system">System</span>';
        if ((int)($row['is_edited'] ?? 0) === 1) {
            $tags[] = '<span class="instruction-tag edit">Edit</span>';
        }
    } elseif ($kind === 'custom_typed') {
        $tags[] = '<span class="instruction-tag custom">Custom Typed</span>';
    } elseif ($kind === 'added') {
        $tags[] = '<span class="instruction-tag added">Added</span>';
    }

    if ((int)($row['is_hidden'] ?? 0) === 1) {
        $tags[] = '<span class="instruction-tag hidden">Hidden</span>';
    }

    return implode('', $tags);
}

function instruction_template_pin_cell(array $row): string {
    $isPinned = (int)($row['is_pinned'] ?? 0) === 1;
    $pinLabel = $isPinned ? 'Unpin' : 'Pin it';
    return '<button type="button" class="instruction-pin-btn ' . ($isPinned ? 'active' : '') . '" data-action="toggle-pin">'
        . $pinLabel .
    '</button>';
}

function instruction_template_sl_cell(array $row): string {
    $sortOrder = (int)($row['sort_order'] ?? 0);
    if ((int)($row['is_pinned'] ?? 0) === 1) {
        return '<div style="display:flex; align-items:center; justify-content:center; gap:0.25rem;"><span style="color:var(--zrx-amber-700);">&#x25F3;</span><span>' . $sortOrder . '</span></div>';
    }
    return (string)$sortOrder;
}

function instruction_template_handle_cell(array $row): string {
    $pinMarkup = (int)($row['is_pinned'] ?? 0) === 1
        ? '<img class="instruction-handle-pin" src="assets/images/pin.svg" alt="Pinned">'
        : '';

    return $pinMarkup
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>';
}

function instruction_template_dosage_form_text($value): string {
    return implode(', ', instruction_template_dosage_form_list($value));
}

function instruction_template_dosage_form_list($value): array {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $decoded)), static fn($item) => $item !== ''));
        }

        $value = trim($value);
        if ($value === '' || $value === '[]') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn($item) => $item !== ''));
    }

    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', array_map('strval', $value)), static fn($item) => $item !== ''));
    }

    return [];
}

function instruction_template_dosage_form_chips($value): string {
    $items = instruction_template_dosage_form_list($value);
    if (!$items) {
        return '<span class="instruction-form-empty">-</span>';
    }

    $visible = array_slice($items, 0, 1);
    $markup = '<div class="instruction-form-chips table collapsed">';
    foreach ($visible as $item) {
        $markup .= '<span class="instruction-form-chip">' . instruction_template_escape($item) . '</span>';
    }
    $remaining = count($items) - count($visible);
    if ($remaining > 0) {
        $markup .= '<button type="button" class="instruction-form-more" data-action="toggle-forms">Show all (+' . $remaining . ')</button>';
    }
    $markup .= '</div>';
    return $markup;
}

include 'header.php';
?>
<link rel="stylesheet" href="assets/css/pages/instruction_template.css">

<div class="instruction-template-page zrx-page-container">
    <div class="instruction-template-card">
        <div class="instruction-template-hero">
            <div>
                <h1>Instruction Template</h1>
                <p>Manage system instructions, custom typed instructions, and doctor-specific ordering for the Rx instruction dropdown.</p>
            </div>
            <div class="instruction-template-status" id="instruction-template-status"></div>
        </div>

        <div class="instruction-template-toolbar">
            <div class="instruction-toolbar-main">
                <div class="instruction-settings-box">
                    <div class="instruction-settings-box-title">
                        <span class="instruction-settings-box-title-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"></path>
                                <path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.49 1a7.2 7.2 0 0 0-1.69-.98L14.5 2.42A.5.5 0 0 0 14 2h-4a.5.5 0 0 0-.5.42L9.12 5.07c-.61.24-1.18.56-1.69.98l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.04.32-.07.65-.07.98s.02.66.07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.49-1c.51.42 1.08.74 1.69.98l.38 2.65a.5.5 0 0 0 .5.42h4a.5.5 0 0 0 .5-.42l.38-2.65c.61-.24 1.18-.56 1.69-.98l2.49 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65Z"></path>
                            </svg>
                        </span>
                        <span>Sorting in prescription settings:</span>
                    </div>
                    <div class="instruction-settings-box-body">
                        <div class="instruction-template-segment" aria-label="Instruction order mode">
                            <button type="button" data-show-mode="serial" class="active">Show as per this serial</button>
                            <button type="button" data-show-mode="usage">Show as per usage</button>
                        </div>
                        <label class="instruction-template-toggle">
                            <input type="checkbox" id="instruction-show-custom-typed" checked>
                            <span>Show custom typed templates also</span>
                        </label>
                        <div class="instruction-settings-reset-wrap">
                            <button type="button" class="instruction-bulk-btn instruction-settings-reset-btn" id="instruction-reset-default-settings">Reset view settings</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="instruction-template-bulk">
            <button type="button" class="instruction-add-btn" id="instruction-add-new">Add New</button>
            <div class="instruction-dropdown" id="instruction-reset-dropdown">
                <button type="button" class="instruction-bulk-btn instruction-dropdown-trigger" id="instruction-reset-menu" aria-haspopup="true" aria-expanded="false">Reset</button>
                <div class="instruction-dropdown-menu" id="instruction-reset-menu-list">
                    <button type="button" class="instruction-dropdown-item" id="instruction-reset-default-order">Reset to default order</button>
                    <button type="button" class="instruction-dropdown-item" id="instruction-reset-usage">Reset usage count</button>
                    <button type="button" class="instruction-dropdown-item danger" id="instruction-reset-full">Reset full</button>
                </div>
            </div>
            <div class="instruction-dropdown" id="instruction-delete-dropdown">
                <button type="button" class="instruction-bulk-btn instruction-dropdown-trigger" id="instruction-delete-menu" aria-haspopup="true" aria-expanded="false">Remove</button>
                <div class="instruction-dropdown-menu" id="instruction-delete-menu-list">
                    <button type="button" class="instruction-dropdown-item danger" id="instruction-delete-all-system">Remove All</button>
                    <button type="button" class="instruction-dropdown-item" id="instruction-remove-custom-typed">Remove custom typed instructions</button>
                    <button type="button" class="instruction-dropdown-item" id="instruction-remove-added">Remove added instructions</button>
                    <button type="button" class="instruction-dropdown-item" id="instruction-show-deleted-system">Show deleted items(system)</button>
                </div>
            </div>
            <div class="instruction-bulk-spacer"></div>
            <div class="instruction-filter-box" aria-label="Instruction table search">
                <select class="instruction-filter-select" id="instruction-filter-field">
                    <option value="all">All (default)</option>
                    <option value="instruction_bn">Bangla instruction</option>
                    <option value="instruction_en">English instruction</option>
                    <option value="default_dosage_form">Default dosage form</option>
                    <option value="search_alias">Search alias</option>
                    <option value="new_line">New line</option>
                </select>
                <input class="instruction-filter-input" id="instruction-filter-input" type="search" placeholder="Search instructions">
            </div>
        </div>

        <div class="instruction-template-table-wrap">
            <table class="instruction-template-table">
                <thead>
                    <tr>
                        <th style="width:44px;"></th>
                        <th style="width:265px;">Actions</th>
                        <th class="instruction-sortable-th" style="width:60px; text-align:center;">
                            <button type="button" class="instruction-sort-button" data-sort-key="sl" data-sort-direction="desc">
                                <span class="instruction-sort-label">SL</span>
                                <span class="instruction-sort-icon" aria-hidden="true">
                                    <span class="instruction-sort-triangle up"></span>
                                    <span class="instruction-sort-triangle down"></span>
                                </span>
                            </button>
                        </th>
                        <th style="width:118px;">Tags</th>
                        <th>Instruction Bangla</th>
                        <th style="width:170px;">Instruction English</th>
                        <th style="width:190px;">Search Alias</th>
                        <th style="width:240px;">Default Dosage Form</th>
                        <th style="width:80px; text-align:center;">New Line</th>
                        <th class="instruction-sortable-th" style="width:88px; text-align:center;">
                            <button type="button" class="instruction-sort-button" data-sort-key="usage_count" data-sort-direction="desc">
                                <span class="instruction-sort-label">Usage Count</span>
                                <span class="instruction-sort-icon" aria-hidden="true">
                                    <span class="instruction-sort-triangle up"></span>
                                    <span class="instruction-sort-triangle down"></span>
                                </span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody id="instruction-template-body">
                    <?php foreach ($instructionTemplateInitialPayload['rows'] as $row): ?>
                        <tr data-row-key="<?= instruction_template_escape($row['client_key'] ?? '') ?>" class="<?= (int)($row['is_hidden'] ?? 0) === 1 ? 'hidden-row' : '' ?>">
                            <td class="instruction-handle" title="Drag to reorder">
                                <?= instruction_template_handle_cell($row) ?>
                            </td>
                            <td class="instruction-actions-cell">
                                <div class="instruction-actions">
                                    <?= instruction_template_pin_cell($row) ?>
                                    <button type="button" class="instruction-row-btn" data-action="toggle-edit">Edit</button>
                                    <button type="button" class="instruction-row-btn toggle-hidden <?= (int)($row['is_hidden'] ?? 0) === 1 ? 'is-hidden' : '' ?>" data-action="delete"><?= (int)($row['is_hidden'] ?? 0) === 1 ? 'Restore' : 'Remove' ?></button>
                                    <button type="button" class="instruction-row-btn" data-action="reset">Reset</button>
                                </div>
                            </td>
                            <td class="instruction-sl"><?= instruction_template_sl_cell($row) ?></td>
                            <td><div class="instruction-tags"><?= instruction_template_tags($row) ?></div></td>
                            <td><div class="instruction-table-text"><?= instruction_template_escape($row['instruction_bn'] ?? '') ?></div></td>
                            <td><div class="instruction-table-text"><?= instruction_template_escape($row['instruction_en'] ?? '') ?></div></td>
                            <td><div class="instruction-table-text"><?= instruction_template_escape($row['search_alias'] ?? '') ?></div></td>
                            <td><?= instruction_template_dosage_form_chips($row['default_dosage_form'] ?? '[]') ?></td>
                            <td><div class="instruction-table-bool"><?= (int)($row['default_instruction_in_another_row'] ?? 0) === 1 ? 'Yes' : 'No' ?></div></td>
                            <td class="instruction-usage"><?= (int)($row['usage_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="instruction-modal-backdrop" id="instruction-edit-modal" hidden>
    <div class="instruction-modal" role="dialog" aria-modal="true" aria-labelledby="instruction-edit-modal-title">
        <div class="instruction-modal-header">
            <h2 id="instruction-edit-modal-title">Instruction</h2>
        </div>
        <div class="instruction-modal-body">
            <div class="instruction-modal-field">
                <label for="instruction-modal-bn">Instruction Bangla</label>
                <input id="instruction-modal-bn" type="text">
            </div>
            <div class="instruction-modal-field">
                <label for="instruction-modal-en">Instruction English</label>
                <input id="instruction-modal-en" type="text">
            </div>
            <div class="instruction-modal-field">
                <label for="instruction-modal-alias">Search Alias</label>
                <input id="instruction-modal-alias" type="text">
            </div>
            <div class="instruction-modal-field" id="instruction-modal-add-position-field">
                <label for="instruction-modal-add-position">Add Position</label>
                <select id="instruction-modal-add-position">
                    <option value="last" selected>Add at the last</option>
                    <option value="first">Add at the first</option>
                </select>
            </div>
            <div class="instruction-modal-field">
                <label for="instruction-modal-dosage-form">Default Dosage Form</label>
                <div class="instruction-form-editor" id="instruction-modal-dosage-editor">
                    <div class="instruction-form-chips" id="instruction-modal-dosage-chips"></div>
                    <input id="instruction-modal-dosage-form" type="text" autocomplete="off" placeholder="Type dosage form">
                    <ul class="instruction-form-suggestions" id="instruction-modal-dosage-suggestions" hidden></ul>
                </div>
            </div>
            <label class="instruction-modal-check">
                <input id="instruction-modal-another-row" type="checkbox">
                <span>Show instruction in another row by default</span>
            </label>
            <label class="instruction-modal-check">
                <input id="instruction-modal-pin" type="checkbox">
                <span>Pin it</span>
            </label>
            <div class="instruction-modal-error" id="instruction-edit-modal-error"></div>
        </div>
        <div class="instruction-modal-actions">
            <button type="button" class="instruction-modal-btn" id="instruction-edit-cancel">Cancel</button>
            <button type="button" class="instruction-modal-btn primary" id="instruction-edit-save">Save</button>
        </div>
    </div>
</div>

<div class="instruction-modal-backdrop" id="instruction-delete-modal" hidden>
    <div class="instruction-modal" role="dialog" aria-modal="true" aria-labelledby="instruction-delete-modal-title">
        <div class="instruction-modal-header">
            <h2 id="instruction-delete-modal-title">Delete Instruction</h2>
        </div>
        <div class="instruction-modal-body">
            <p>Choose how you want to delete this instruction.</p>
            <div class="instruction-modal-error" id="instruction-delete-modal-error"></div>
        </div>
        <div class="instruction-modal-actions">
            <button type="button" class="instruction-modal-btn" id="instruction-delete-cancel">Cancel</button>
            <button type="button" class="instruction-modal-btn warn" id="instruction-delete-temporary">Delete Temporarily</button>
            <button type="button" class="instruction-modal-btn danger" id="instruction-delete-permanent">Delete Permanently</button>
        </div>
    </div>
</div>

<div class="instruction-modal-backdrop" id="instruction-warning-modal" hidden>
    <div class="instruction-modal" role="dialog" aria-modal="true" aria-labelledby="instruction-warning-modal-title">
        <div class="instruction-modal-header">
            <h2 id="instruction-warning-modal-title">Confirm Reset</h2>
        </div>
        <div class="instruction-modal-body">
            <p id="instruction-warning-modal-message">This action cannot be reversed.</p>
        </div>
        <div class="instruction-modal-actions">
            <button type="button" class="instruction-modal-btn" id="instruction-warning-no">No</button>
            <button type="button" class="instruction-modal-btn danger" id="instruction-warning-yes">Yes</button>
        </div>
    </div>
</div>

<script id="instructionTemplateInitialPayload" type="application/json"><?= json_encode($instructionTemplateInitialPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="assets/js/pages/instruction_template.js"></script>
<?php include 'footer.php'; ?>
