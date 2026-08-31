<?php
require_once 'auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/rx_regimen_lib.php';
$page_title = 'Instruction Template';

$instructionTemplateDoctorId = rx_active_doctor_id();
rx_instruction_template_ensure_schema(rx_user_pdo());
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
        return '<div style="display:flex; align-items:center; justify-content:center; gap:0.25rem;"><span style="color:#b45309;">&#x25F3;</span><span>' . $sortOrder . '</span></div>';
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
<style>
html { overflow-y: scroll; scrollbar-gutter: stable; }
.instruction-template-page { margin-bottom: 2rem; }
.instruction-template-card { background: #fff; border: 1px solid #dbe3ee; border-radius: 14px; box-shadow: 0 10px 30px rgba(15,23,42,0.08); overflow: visible; }
.instruction-template-hero { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding: 1.2rem 1.35rem; background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); border-bottom: 1px solid #dbe3ee; border-top-left-radius: 14px; border-top-right-radius: 14px; overflow: hidden; background-clip: padding-box; }
.instruction-template-hero h1 { margin: 0; font-size: 1.4rem; color: #0f172a; }
.instruction-template-hero p { margin: 0.35rem 0 0; color: #64748b; font-size: 0.92rem; }
.instruction-template-toolbar, .instruction-template-bulk { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; padding: 1rem 1.35rem; border-bottom: 1px solid #e2e8f0; }
.instruction-template-toolbar { background: #fff; }
.instruction-template-bulk { background: #f8fafc; }
.instruction-toolbar-main { display: grid; gap: 0.85rem; min-width: 0; width: 100%; }
.instruction-bulk-spacer { flex: 1 1 auto; }
.instruction-dropdown { position: relative; display: inline-flex; padding-bottom: 6px; margin-bottom: -6px; }
.instruction-dropdown-trigger { position: relative; padding-right: 1.1rem; display: inline-flex; align-items: center; gap: 0.2rem; }
.instruction-dropdown-trigger::after {
    content: '';
    display: inline-block;
    width: 10px;
    height: 6px;
    margin-left: 0.4rem;
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
    opacity: 0.8;
    transform: translateY(0);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8' fill='none'%3E%3Cpath d='M2 2.5L6 6.5L10 2.5' stroke='currentColor' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}
.instruction-dropdown-menu { position: absolute; top: calc(100% - 1px); left: 0; z-index: 2500; min-width: 240px; padding: 0.35rem; background: #fff; border: 1px solid #dbe3ee; border-radius: 12px; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.14); display: none; gap: 0.25rem; }
.instruction-dropdown:hover .instruction-dropdown-menu,
.instruction-dropdown:focus-within .instruction-dropdown-menu { display: grid; }
.instruction-dropdown-item { width: 100%; text-align: left; border: 1px solid transparent; background: #fff; color: #0f172a; border-radius: 9px; padding: 0.48rem 0.68rem; cursor: pointer; font: inherit; font-size: 0.84rem; font-weight: 400; }
.instruction-dropdown-item:hover { background: #eff6ff; }
.instruction-dropdown-item.danger { color: #b91c1c; }
.instruction-dropdown-item.danger:hover { background: #fff1f2; }
.instruction-filter-box { display: flex; align-items: stretch; gap: 0; flex: 1 1 360px; min-width: 280px; max-width: 520px; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; background: #fff; }
.instruction-filter-select,
.instruction-filter-input { height: 40px; border: none; background: transparent; color: #0f172a; border-radius: 0; font: inherit; font-size: 0.88rem; }
.instruction-filter-select { flex: 0 0 205px; padding: 0 0.65rem; border-right: 1px solid #cbd5e1; }
.instruction-filter-input { flex: 1 1 auto; min-width: 0; padding: 0 0.75rem; }
.instruction-filter-box:focus-within { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.instruction-filter-select:focus,
.instruction-filter-input:focus { outline: none; }
.instruction-settings-box { display: flex; flex-wrap: wrap; gap: 0.85rem 1rem; align-items: center; padding: 0.2rem 0 0; min-width: 0; }
.instruction-settings-box-title { margin: 0; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; font-weight: 700; color: #475569; white-space: nowrap; }
.instruction-settings-box-title-icon { width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; color: #64748b; flex: 0 0 auto; }
.instruction-settings-box-title-icon svg { width: 100%; height: 100%; display: block; }
.instruction-settings-box-body { display: flex; flex-wrap: wrap; gap: 0.85rem 1rem; align-items: center; min-width: 0; flex: 1 1 auto; }
.instruction-settings-reset-wrap { flex: 1 1 220px; display: flex; justify-content: flex-end; }
.instruction-settings-reset-btn { min-width: 190px; }
.instruction-template-segment { display: inline-flex; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
.instruction-template-segment button { border: none; background: transparent; padding: 0.7rem 1.1rem; cursor: pointer; font: inherit; color: #334155; font-weight: 600; }
.instruction-template-segment button.active { background: #1d4ed8; color: #fff; }
.instruction-template-toggle { display: inline-flex; gap: 0.6rem; align-items: center; color: #334155; font-weight: 500; background: #f8fafc; border: 1px solid #dbe3ee; border-radius: 12px; padding: 0.72rem 0.95rem; }
.instruction-template-toggle input { margin: 0; accent-color: #1d4ed8; }
.instruction-template-table-wrap { overflow-x: auto; overflow-y: visible; background: #fff; }
.instruction-template-table { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
.instruction-template-table th, .instruction-template-table td { border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; padding: 0.42rem 0.48rem; vertical-align: middle; }
.instruction-template-table th:last-child, .instruction-template-table td:last-child { border-right: none; }
.instruction-template-table th { background: #f8fafc; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; text-align: left; }
.instruction-sortable-th { padding: 0 !important; }
.instruction-sort-button { width: 100%; border: none; background: transparent; padding: 0.52rem 0.45rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.18rem; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; cursor: pointer; }
.instruction-sort-button:hover { background: rgba(37, 99, 235, 0.05); }
.instruction-sort-button.active { color: #1d4ed8; }
.instruction-sort-label { line-height: 1.2; }
.instruction-sort-icon { width: 10px; display: flex; flex-direction: column; align-items: center; gap: 2px; color: #94a3b8; line-height: 0; }
.instruction-sort-triangle { width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; opacity: 0.4; }
.instruction-sort-triangle.up { border-bottom: 5px solid currentColor; }
.instruction-sort-triangle.down { border-top: 5px solid currentColor; }
.instruction-sort-button.active .instruction-sort-icon { color: #1d4ed8; }
.instruction-sort-button.active[data-sort-direction="desc"] .instruction-sort-triangle.down,
.instruction-sort-button.active[data-sort-direction="asc"] .instruction-sort-triangle.up { opacity: 1; }
.instruction-template-table td:nth-child(2) { padding-left: 0.3rem; padding-right: 0.3rem; }
.instruction-template-table tbody tr.hidden-row td { background: #fff1f2; }
.instruction-template-table tbody tr.hidden-row .instruction-tag.hidden { background: #fecdd3; color: #9f1239; }
.instruction-template-table tbody tr.pinned-row td { background: #fffbeb; }
.instruction-template-table tbody tr.pinned-row td:first-child { background: #fffbeb; }
.instruction-template-table tbody tr.hidden-row.pinned-row td { background: #fff1f2; }
.instruction-template-table tbody tr.instruction-row-dragging td { opacity: 0.6; background: #dbeafe; }
.instruction-template-table tbody tr.drop-target td { box-shadow: inset 0 2px 0 #2563eb, inset 0 -2px 0 #2563eb; }
.instruction-handle { position: relative; cursor: grab; text-align: center; color: #64748b; font-size: 1rem; user-select: none; background: transparent; }
.instruction-handle:active { cursor: grabbing; }
.instruction-handle svg { pointer-events: none; }
.instruction-handle-pin { position: absolute; top: 3px; left: 3px; width: 14px; height: 14px; object-fit: contain; pointer-events: none; z-index: 2; opacity: 0.85; }
.instruction-pin-btn, .instruction-row-btn, .instruction-add-btn, .instruction-save-btn, .instruction-bulk-btn { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 9px; padding: 0.36rem 0.52rem; cursor: pointer; font: inherit; font-size: 0.84rem; font-weight: 400; }
.instruction-pin-btn.active { color: #b45309; border-color: #f59e0b; background: #fff7ed; }
.instruction-pin-btn:disabled { cursor: not-allowed; opacity: 0.45; background: #f8fafc; color: #64748b; border-color: #cbd5e1; }
.instruction-add-btn, .instruction-save-btn { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.instruction-save-btn { min-width: 140px; font-weight: 700; }
.instruction-bulk-btn.danger { border-color: #fecaca; color: #b91c1c; background: #fff1f2; }
.instruction-bulk-btn.active { border-color: #f97316; color: #9a3412; background: #ffedd5; }
.instruction-pin-cell,
.instruction-actions-cell { text-align: center; }
.instruction-pin-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem; white-space: nowrap; min-width: 58px; }
.instruction-actions { display: flex; flex-wrap: nowrap; gap: 0.28rem; align-items: center; justify-content: center; width: 100%; }
.instruction-row-btn { white-space: nowrap; padding: 0.34rem 0.5rem; }
.instruction-row-btn.toggle-hidden { min-width: 88px; }
.instruction-row-btn.toggle-hidden.is-hidden { border-color: #16a34a; color: #166534; background: #f0fdf4; }
.instruction-table-input { width: 100%; height: 34px; min-height: 34px; border: 1px solid transparent; border-radius: 8px; padding: 0.35rem 0.5rem; background: transparent; font: inherit; color: #0f172a; line-height: 1.35; }
.instruction-table-input[readonly] { cursor: default; }
.instruction-table-input:not([readonly]) { background: #fff; border-color: #bfdbfe; box-shadow: inset 0 0 0 1px rgba(37,99,235,0.18); }
.instruction-table-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.instruction-table-text { min-height: 34px; padding: 0.35rem 0.5rem; color: #0f172a; line-height: 1.35; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
.instruction-table-bool { min-height: 34px; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #334155; }
.instruction-sl { font-weight: 700; color: #334155; text-align: center; }
.instruction-usage { font-weight: 700; color: #1d4ed8; text-align: center; }
.instruction-tags { display: flex; flex-wrap: nowrap; gap: 0.35rem; align-items: center; white-space: nowrap; }
.instruction-tag { display: inline-flex; align-items: center; gap: 0.25rem; border-radius: 999px; padding: 0.18rem 0.48rem; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.instruction-tag.system { background: #eff6ff; color: #1d4ed8; }
.instruction-tag.edit { background: #fef3c7; color: #92400e; }
.instruction-tag.custom { background: #ecfccb; color: #3f6212; }
.instruction-tag.added { background: #ede9fe; color: #6d28d9; }
.instruction-tag.hidden { background: #fee2e2; color: #b91c1c; }
.instruction-form-chips { display: flex; align-items: center; gap: 0.3rem; min-width: 0; }
.instruction-form-chips.table { min-height: 34px; padding: 0.28rem 0.4rem; }
.instruction-form-chips.table.collapsed { flex-wrap: nowrap; overflow: hidden; }
.instruction-template-table .instruction-form-chips.table.collapsed > .instruction-form-chip:nth-of-type(n+2) { display: none !important; }
.instruction-form-chips.table.expanded { flex-wrap: wrap; align-items: flex-start; }
.instruction-form-chip { display: inline-flex; align-items: center; max-width: none; border: 1px solid #bfdbfe; background: #eff6ff; color: #1e40af; border-radius: 999px; padding: 0.18rem 0.46rem; font-size: 0.74rem; font-weight: 700; line-height: 1.15; white-space: nowrap; overflow: visible; text-overflow: clip; flex: 0 0 auto; }
.instruction-form-chip.remove { padding-right: 0.28rem; background: #f8fafc; border-color: #cbd5e1; color: #334155; }
.instruction-form-chip-remove { border: none; background: transparent; color: #475569; margin-left: 0.28rem; cursor: pointer; font: inherit; font-weight: 800; line-height: 1; padding: 0; }
.instruction-form-more { border: 1px solid #cbd5e1; background: #fff; color: #334155; border-radius: 999px; padding: 0.17rem 0.44rem; cursor: pointer; font: inherit; font-size: 0.72rem; font-weight: 700; white-space: nowrap; flex: 0 0 auto; }
.instruction-form-empty { color: #94a3b8; padding-left: 0.5rem; }
.instruction-form-editor { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; min-height: 42px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0.38rem 0.45rem; background: #fff; overflow: visible; }
.instruction-form-editor:focus-within { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.instruction-form-editor > .instruction-form-chips { flex: 1 1 100%; flex-wrap: wrap; align-items: flex-start; overflow: visible; padding-right: 0.15rem; }
.instruction-form-editor > .instruction-form-chips:empty { display: none; }
.instruction-form-editor > .instruction-form-chips .instruction-form-chip { max-width: 100%; white-space: normal; overflow-wrap: anywhere; }
.instruction-form-editor input { flex: 1 1 130px; width: auto; min-width: 120px; height: 30px; border: none; padding: 0.1rem 0.2rem; box-shadow: none; }
.instruction-form-editor input:focus { outline: none; box-shadow: none; border-color: transparent; }
.instruction-form-suggestions[hidden] { display: none; }
.instruction-form-suggestions { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 3010; max-height: 190px; overflow: auto; margin: 0; padding: 0.25rem; list-style: none; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 14px 34px rgba(15,23,42,0.16); }
.instruction-form-suggestions button { width: 100%; border: none; background: transparent; text-align: left; border-radius: 8px; padding: 0.45rem 0.55rem; cursor: pointer; font: inherit; color: #0f172a; }
.instruction-form-suggestions button:hover { background: #eff6ff; }
.instruction-template-status { min-height: 22px; color: #0f766e; font-weight: 600; }
.instruction-modal-backdrop[hidden] { display: none; }
.instruction-modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.35); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; padding: 1.25rem; z-index: 3000; }
.instruction-modal { width: min(760px, 100%); max-height: calc(100vh - 3rem); display: flex; flex-direction: column; background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24); overflow: visible; }
.instruction-modal-header { padding: 1rem 1.2rem; border-bottom: 1px solid #e2e8f0; }
.instruction-modal-header h2 { margin: 0; font-size: 1.08rem; color: #0f172a; }
.instruction-modal-body { padding: 1rem 1.2rem; display: grid; gap: 0.85rem; overflow-y: auto; }
.instruction-modal-body p { margin: 0; color: #475569; line-height: 1.5; }
.instruction-modal-field { display: grid; gap: 0.35rem; }
.instruction-modal-field label { font-size: 0.82rem; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.03em; }
.instruction-modal-field input,
.instruction-modal-field select { width: 100%; height: 42px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0.55rem 0.7rem; font: inherit; color: #0f172a; background: #fff; }
.instruction-modal-field input:focus,
.instruction-modal-field select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.instruction-modal-field .instruction-form-editor input { flex: 1 1 130px; width: auto; min-width: 120px; height: 30px; border: none; border-radius: 0; padding: 0.1rem 0.2rem; box-shadow: none; }
.instruction-modal-field .instruction-form-editor input:focus { border-color: transparent; box-shadow: none; }
.instruction-modal-check { display: inline-flex; align-items: center; gap: 0.65rem; color: #334155; font-weight: 600; }
.instruction-modal-check input { margin: 0; accent-color: #1d4ed8; }
.instruction-modal-actions { padding: 0 1.2rem 1.15rem; display: flex; justify-content: flex-end; gap: 0.7rem; }
.instruction-modal-btn { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 10px; padding: 0.55rem 0.95rem; cursor: pointer; font: inherit; font-weight: 600; }
.instruction-modal-btn.primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.instruction-modal-btn.danger { background: #dc2626; border-color: #dc2626; color: #fff; }
.instruction-modal-btn.warn { background: #f59e0b; border-color: #f59e0b; color: #fff; }
.instruction-modal-error { min-height: 20px; color: #b91c1c; font-size: 0.85rem; font-weight: 600; }
@media (max-width: 1100px) {
    .instruction-template-table { min-width: 1480px; }
    .instruction-settings-box { align-items: flex-start; }
    .instruction-settings-box-title { width: 100%; white-space: normal; }
    .instruction-settings-box-body { flex-direction: column; align-items: stretch; width: 100%; }
    .instruction-template-segment { width: 100%; }
    .instruction-template-segment button { flex: 1 1 0; text-align: center; }
    .instruction-template-toggle { width: 100%; }
    .instruction-bulk-spacer { display: none; }
    .instruction-filter-box { width: 100%; max-width: none; flex: 1 1 100%; }
    .instruction-template-bulk .instruction-add-btn,
    .instruction-template-bulk .instruction-save-btn { width: 100%; }
}
</style>

<div class="instruction-template-page">
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.getElementById('instruction-template-body');
    const status = document.getElementById('instruction-template-status');
    const addBtn = document.getElementById('instruction-add-new');
    const resetMenuBtn = document.getElementById('instruction-reset-menu');
    const resetMenuList = document.getElementById('instruction-reset-menu-list');
    const deleteMenuBtn = document.getElementById('instruction-delete-menu');
    const deleteMenuList = document.getElementById('instruction-delete-menu-list');
    const deleteAllSystemBtn = document.getElementById('instruction-delete-all-system');
    const showDeletedSystemBtn = document.getElementById('instruction-show-deleted-system');
    const filterField = document.getElementById('instruction-filter-field');
    const filterInput = document.getElementById('instruction-filter-input');
    const showCustomTypedInput = document.getElementById('instruction-show-custom-typed');
    const modeButtons = Array.from(document.querySelectorAll('[data-show-mode]'));
    const sortButtons = Array.from(document.querySelectorAll('[data-sort-key]'));
    const editModal = document.getElementById('instruction-edit-modal');
    const editModalTitle = document.getElementById('instruction-edit-modal-title');
    const editModalBn = document.getElementById('instruction-modal-bn');
    const editModalEn = document.getElementById('instruction-modal-en');
    const editModalAlias = document.getElementById('instruction-modal-alias');
    const editModalAddPositionField = document.getElementById('instruction-modal-add-position-field');
    const editModalAddPosition = document.getElementById('instruction-modal-add-position');
    const editModalDosageForm = document.getElementById('instruction-modal-dosage-form');
    const editModalDosageChips = document.getElementById('instruction-modal-dosage-chips');
    const editModalDosageSuggestions = document.getElementById('instruction-modal-dosage-suggestions');
    const editModalAnotherRow = document.getElementById('instruction-modal-another-row');
    const editModalPin = document.getElementById('instruction-modal-pin');
    const editModalError = document.getElementById('instruction-edit-modal-error');
    const editModalCancel = document.getElementById('instruction-edit-cancel');
    const editModalSave = document.getElementById('instruction-edit-save');
    const deleteModal = document.getElementById('instruction-delete-modal');
    const deleteModalError = document.getElementById('instruction-delete-modal-error');
    const deleteModalCancel = document.getElementById('instruction-delete-cancel');
    const deleteModalTemporary = document.getElementById('instruction-delete-temporary');
    const deleteModalPermanent = document.getElementById('instruction-delete-permanent');
    const resetDefaultSettingsBtn = document.getElementById('instruction-reset-default-settings');
    const warningModal = document.getElementById('instruction-warning-modal');
    const warningModalTitle = document.getElementById('instruction-warning-modal-title');
    const warningModalMessage = document.getElementById('instruction-warning-modal-message');
    const warningModalNo = document.getElementById('instruction-warning-no');
    const warningModalYes = document.getElementById('instruction-warning-yes');
    const initialPayload = <?= json_encode($instructionTemplateInitialPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const state = {
        rows: [],
        settings: { show_mode: 'serial', show_custom_typed: 1 },
        defaultMap: {},
        nextKey: 1,
        dragKey: null,
        dragRow: null,
        dragSection: '',
        dragSerialSnapshot: [],
        dropSucceeded: false,
        dirty: false,
        modalRowKey: null,
        deleteRowKey: null,
        columnSort: { key: '', direction: 'desc' },
        modalDoseForms: [],
        filterField: 'all',
        filterQuery: '',
        showDeletedSystem: false,
    };
    let dosageSuggestionTimer = null;
    let pendingWarningAction = null;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function rowKindFromStaticId(staticId) {
        if (Number(staticId || 0) === 99) return 'added';
        if (Number(staticId || 0) === 0) return 'custom_typed';
        return 'system';
    }

    function normalizeDosageFormStorage(value) {
        if (Array.isArray(value)) {
            const items = uniqueDosageForms(value);
            return JSON.stringify(items);
        }

        const text = String(value ?? '').trim();
        if (!text || text === '[]') {
            return '[]';
        }

        try {
            const parsed = JSON.parse(text);
            if (Array.isArray(parsed)) {
                const items = uniqueDosageForms(parsed);
                return JSON.stringify(items);
            }
        } catch (error) {
        }

        const items = uniqueDosageForms(text.split(','));
        return JSON.stringify(items);
    }

    function uniqueDosageForms(items) {
        const seen = new Set();
        const forms = [];
        (items || []).forEach((item) => {
            const text = String(item || '').trim();
            const key = text.toLowerCase();
            if (!text || seen.has(key)) return;
            seen.add(key);
            forms.push(text);
        });
        return forms;
    }

    function dosageFormsToArray(value) {
        const normalized = normalizeDosageFormStorage(value);
        try {
            const parsed = JSON.parse(normalized);
            return Array.isArray(parsed) ? uniqueDosageForms(parsed) : [];
        } catch (error) {
            return [];
        }
    }

    function dosageFormsToText(value) {
        return dosageFormsToArray(value).join(', ');
    }

    function dosageFormsFromText(text) {
        return normalizeDosageFormStorage(text);
    }

    function dosageFormsFromArray(items) {
        return normalizeDosageFormStorage(items);
    }

    function normalizeAnotherRowFlag(value) {
        return value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true' ? 1 : 0;
    }

    function nextRowKey() {
        const key = `row-${state.nextKey}`;
        state.nextKey += 1;
        return key;
    }

    function normalizeRow(row, fallbackSortOrder) {
        return {
            client_key: row.client_key || nextRowKey(),
            id: Number(row.id || 0),
            static_id: Number(row.static_id || 0),
            doctor_id: Number(row.doctor_id || 0),
            usage_count: Math.max(0, Number(row.usage_count || 0)),
            instruction_bn: String(row.instruction_bn || ''),
            instruction_en: String(row.instruction_en || ''),
            search_alias: String(row.search_alias || ''),
            is_pinned: Number(row.is_pinned || 0) === 1 ? 1 : 0,
            is_hidden: Number(row.is_hidden || 0) === 1 ? 1 : 0,
            sort_order: Math.max(1, Number(row.sort_order || fallbackSortOrder || 1)),
            default_dosage_form: normalizeDosageFormStorage(row.default_dosage_form || '[]'),
            default_instruction_in_another_row: normalizeAnotherRowFlag(row.default_instruction_in_another_row ?? 0),
            is_edited: Number(row.is_edited || 0) === 1 ? 1 : 0,
            kind: row.kind || rowKindFromStaticId(row.static_id),
            editing: Boolean(row.editing),
        };
    }

    function syncNextKey() {
        state.nextKey = Math.max(1, state.rows.length + 1);
    }

    function systemTags(row) {
        const tags = [];
        if (row.kind === 'system') {
            tags.push('<span class="instruction-tag system">System</span>');
            if (row.is_edited) {
                tags.push('<span class="instruction-tag edit">Edit</span>');
            }
        } else if (row.kind === 'custom_typed') {
            tags.push('<span class="instruction-tag custom">Custom Typed</span>');
        } else if (row.kind === 'added') {
            tags.push('<span class="instruction-tag added">Added</span>');
        }
        if (row.is_hidden) {
            tags.push('<span class="instruction-tag hidden">Hidden</span>');
        }
        return tags.join('');
    }

    function dosageFormChipsHtml(row) {
        const forms = dosageFormsToArray(row.default_dosage_form);
        if (!forms.length) {
            return '<span class="instruction-form-empty">-</span>';
        }

        const expanded = Boolean(row.forms_expanded);
        const visibleForms = expanded ? forms : forms.slice(0, 1);
        const chipHtml = visibleForms
            .map((form) => `<span class="instruction-form-chip" title="${escapeHtml(form)}">${escapeHtml(form)}</span>`)
            .join('');
        const remaining = forms.length - visibleForms.length;
        const moreButton = remaining > 0
            ? `<button type="button" class="instruction-form-more" data-action="toggle-forms">Show all (+${remaining})</button>`
            : '';
        const lessButton = expanded && forms.length > 1
            ? '<button type="button" class="instruction-form-more" data-action="toggle-forms">Show less</button>'
            : '';

        return `<div class="instruction-form-chips table ${expanded ? 'expanded' : 'collapsed'}">${chipHtml}${moreButton}${lessButton}</div>`;
    }

    function renderModalDoseFormChips() {
        editModalDosageChips.innerHTML = state.modalDoseForms.map((form, index) => `
            <span class="instruction-form-chip remove" title="${escapeHtml(form)}">
                ${escapeHtml(form)}
                <button type="button" class="instruction-form-chip-remove" data-dose-form-index="${index}" aria-label="Remove ${escapeHtml(form)}">x</button>
            </span>
        `).join('');
    }

    function hideDosageSuggestions() {
        editModalDosageSuggestions.hidden = true;
        editModalDosageSuggestions.innerHTML = '';
    }

    function addModalDoseForm(value) {
        const text = String(value || '').trim();
        if (!text) return;
        state.modalDoseForms = uniqueDosageForms([...state.modalDoseForms, text]);
        editModalDosageForm.value = '';
        renderModalDoseFormChips();
        hideDosageSuggestions();
    }

    function removeModalDoseForm(index) {
        state.modalDoseForms.splice(index, 1);
        renderModalDoseFormChips();
    }

    function renderDosageSuggestions(forms) {
        const filtered = uniqueDosageForms(forms).filter((form) => {
            const key = form.toLowerCase();
            return !state.modalDoseForms.some((current) => current.toLowerCase() === key);
        });

        if (!filtered.length) {
            hideDosageSuggestions();
            return;
        }

        editModalDosageSuggestions.innerHTML = filtered.map((form) => `
            <li><button type="button" data-dose-form-suggestion="${escapeHtml(form)}">${escapeHtml(form)}</button></li>
        `).join('');
        editModalDosageSuggestions.hidden = false;
    }

    function fetchDosageSuggestions(query) {
        clearTimeout(dosageSuggestionTimer);
        dosageSuggestionTimer = setTimeout(async () => {
            try {
                const response = await fetch(`api/instruction_template.php?action=form_suggestions&q=${encodeURIComponent(query || '')}`);
                const data = await response.json();
                renderDosageSuggestions(Array.isArray(data.forms) ? data.forms : []);
            } catch (error) {
                hideDosageSuggestions();
            }
        }, 120);
    }

    function markDirty(message = 'Unsaved changes') {
        state.dirty = true;
        status.textContent = message;
    }

    function setStatus(message = '') {
        status.textContent = message;
    }

    function setSettingsUi() {
        showCustomTypedInput.checked = state.settings.show_custom_typed === 1;
        modeButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.showMode === state.settings.show_mode);
        });
        sortButtons.forEach((button) => {
            const isActive = button.dataset.sortKey === state.columnSort.key;
            button.classList.toggle('active', isActive);
            if (!isActive) {
                button.dataset.sortDirection = 'desc';
                return;
            }
            button.dataset.sortDirection = state.columnSort.direction;
        });
    }

    function compareRows(a, b) {
        if (a.is_hidden !== b.is_hidden) return a.is_hidden - b.is_hidden;
        if (a.is_pinned !== b.is_pinned) return b.is_pinned - a.is_pinned;
        if (state.columnSort.key === 'sl') {
            const diff = state.columnSort.direction === 'desc'
                ? b.sort_order - a.sort_order
                : a.sort_order - b.sort_order;
            if (diff !== 0) return diff;
        }
        if (state.columnSort.key === 'usage_count') {
            const diff = state.columnSort.direction === 'desc'
                ? b.usage_count - a.usage_count
                : a.usage_count - b.usage_count;
            if (diff !== 0) return diff;
        }
        if (state.settings.show_mode === 'usage' && a.usage_count !== b.usage_count) return b.usage_count - a.usage_count;
        if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
        return a.instruction_bn.localeCompare(b.instruction_bn, 'bn');
    }

    function compareSerialRows(a, b) {
        if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
        return a.instruction_bn.localeCompare(b.instruction_bn, 'bn');
    }

    function sortRowsForView() {
        return state.rows.slice().sort(compareRows);
    }

    function normalizeSearchText(value) {
        return String(value ?? '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function rowFilterText(row, field) {
        if (field === 'instruction_bn') return row.instruction_bn;
        if (field === 'instruction_en') return row.instruction_en;
        if (field === 'default_dosage_form') return dosageFormsToText(row.default_dosage_form);
        if (field === 'search_alias') return row.search_alias;
        if (field === 'new_line') return row.default_instruction_in_another_row === 1 ? 'yes true new line another row' : 'no false same row';

        return [
            row.instruction_bn,
            row.instruction_en,
            dosageFormsToText(row.default_dosage_form),
            row.search_alias,
            row.default_instruction_in_another_row === 1 ? 'yes true new line another row' : 'no false same row',
        ].join(' ');
    }

    function filterRowsForView(rows) {
        const query = normalizeSearchText(state.filterQuery);
        return rows.filter((row) => {
            const matchesQuery = !query || normalizeSearchText(rowFilterText(row, state.filterField)).includes(query);
            if (!matchesQuery) return false;

            if (state.showDeletedSystem) {
                return row.kind === 'system' && row.is_hidden === 1;
            }

            if (query) {
                return true;
            }

            return row.is_hidden !== 1;
        });
    }

    function setDeletedSystemUi() {
        showDeletedSystemBtn.classList.toggle('active', state.showDeletedSystem);
        showDeletedSystemBtn.textContent = state.showDeletedSystem ? 'Show main list' : 'Show deleted items(system)';
    }

    function serialRowsSnapshot() {
        return state.rows.slice().sort(compareSerialRows);
    }

    function assignSequentialSortOrder(rows) {
        rows.forEach((row, index) => {
            row.sort_order = index + 1;
        });
    }

    /**
     * Rebuild the full row list with new sequential sort_orders after a drag-drop.
     *
     * For unpinned drag:
     *   - Unpinned rows are in their new display order.
     *   - Each pinned row is re-inserted at its logical position: after the last unpinned
     *     row whose ORIGINAL sort_order is less than the pinned row's original sort_order.
     *   - This keeps pinned rows anchored relative to the items that originally surrounded
     *     them, even when unpinned items cross the pinned item's position.
     *
     * For pinned drag:
     *   - The set of sort_order slots used by pinned rows is redistributed among them in
     *     their new display order, so they swap positions in the logical list.
     *   - Unpinned rows are unchanged.
     */
    function buildNewRowOrder(domOrderedKeys, previousRows, draggedSection) {
        const rowMap = new Map(previousRows.map((r) => [r.client_key, r]));
        const origSort = new Map(previousRows.map((r) => [r.client_key, r.sort_order]));

        const domRows = domOrderedKeys.map((k) => rowMap.get(k)).filter(Boolean);
        const pinnedInDom = domRows.filter((r) => r.is_pinned === 1);
        const unpinnedInDom = domRows.filter((r) => r.is_pinned !== 1);

        let combined = [];

        if (draggedSection === 'pinned' && pinnedInDom.length > 1) {
            // Slot-swap: assign old sort_order values to pinned rows in their new DOM order.
            const pinnedSlots = previousRows
                .filter((r) => r.is_pinned === 1)
                .sort((a, b) => a.sort_order - b.sort_order)
                .map((r) => r.sort_order);

            pinnedInDom.forEach((row, i) => {
                row.sort_order = pinnedSlots[i] !== undefined ? pinnedSlots[i] : row.sort_order;
            });

            // Re-sort all rows by (now updated) sort_order to build the combined list.
            combined = previousRows.slice().sort((a, b) => a.sort_order - b.sort_order);
        } else {
            // Unpinned drag: insert each pinned row after the last unpinned row whose
            // original sort_order is strictly less than the pinned row's original sort_order.
            const sortedPinnedByOrig = pinnedInDom
                .slice()
                .sort((a, b) => (origSort.get(a.client_key) || 0) - (origSort.get(b.client_key) || 0));

            let pi = 0;
            for (let i = 0; i < unpinnedInDom.length; i++) {
                combined.push(unpinnedInDom[i]);
                const curOrig = origSort.get(unpinnedInDom[i].client_key) || 0;
                const nextOrig = i + 1 < unpinnedInDom.length
                    ? (origSort.get(unpinnedInDom[i + 1].client_key) || Infinity)
                    : Infinity;

                // Insert any pinned rows that logically belong between current and next unpinned.
                while (pi < sortedPinnedByOrig.length) {
                    const pOrig = origSort.get(sortedPinnedByOrig[pi].client_key) || 0;
                    if (pOrig > curOrig && pOrig <= nextOrig) {
                        combined.push(sortedPinnedByOrig[pi++]);
                    } else {
                        break;
                    }
                }
            }

            // Any pinned rows with original sort_order greater than all unpinned go at the end.
            while (pi < sortedPinnedByOrig.length) {
                combined.push(sortedPinnedByOrig[pi++]);
            }
        }

        assignSequentialSortOrder(combined);
        return combined;
    }

    function updateSystemEditedFlag(row) {
        if (row.kind !== 'system') {
            row.is_edited = 0;
            return;
        }
        const defaults = state.defaultMap[row.static_id];
        if (!defaults) {
            row.is_edited = 0;
            return;
        }
        row.is_edited = (
            row.instruction_bn !== String(defaults.instruction_bn || '')
            || row.instruction_en !== String(defaults.instruction_en || '')
            || row.search_alias !== String(defaults.search_alias || '')
            || row.default_dosage_form !== normalizeDosageFormStorage(defaults.default_dosage_form || '[]')
            || row.default_instruction_in_another_row !== normalizeAnotherRowFlag(defaults.default_instruction_in_another_row ?? 0)
        ) ? 1 : 0;
    }

    function resetRowToDefault(row) {
        if (row.kind === 'system') {
            const defaults = state.defaultMap[row.static_id];
            if (defaults) {
                row.instruction_bn = String(defaults.instruction_bn || '');
                row.instruction_en = String(defaults.instruction_en || '');
                row.search_alias = String(defaults.search_alias || '');
                row.default_dosage_form = normalizeDosageFormStorage(defaults.default_dosage_form || '[]');
                row.default_instruction_in_another_row = normalizeAnotherRowFlag(defaults.default_instruction_in_another_row ?? 0);
            }
            row.is_edited = 0;
        }
        row.is_hidden = 0;
        row.is_pinned = 0;
        row.editing = false;
    }

    function render() {
        const viewRows = filterRowsForView(sortRowsForView());
        body.innerHTML = '';
        setDeletedSystemUi();

        viewRows.forEach((row) => {
            const tr = document.createElement('tr');
            tr.dataset.rowKey = row.client_key;
            const classes = [];
            if (row.is_hidden) classes.push('hidden-row');
            if (row.is_pinned) classes.push('pinned-row');
            tr.className = classes.join(' ');

            const pinLabel = row.is_pinned ? 'Unpin' : 'Pin it';
            const pinDisabled = row.is_hidden === 1 ? 'disabled' : '';
            const hiddenButtonLabel = row.is_hidden ? 'Restore' : 'Remove';
            const handleMarkup = `${row.is_pinned ? '<img class="instruction-handle-pin" src="assets/images/pin.svg" alt="Pinned">' : ''}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>`;
            tr.innerHTML = `
                <td class="instruction-handle" title="Drag to reorder">
                    ${handleMarkup}
                </td>
                <td class="instruction-actions-cell">
                    <div class="instruction-actions">
                        <button type="button" class="instruction-pin-btn ${row.is_pinned ? 'active' : ''}" data-action="toggle-pin" ${pinDisabled}>
                            ${pinLabel}
                        </button>
                        <button type="button" class="instruction-row-btn" data-action="toggle-edit">Edit</button>
                        <button type="button" class="instruction-row-btn toggle-hidden ${row.is_hidden ? 'is-hidden' : ''}" data-action="delete">${hiddenButtonLabel}</button>
                        <button type="button" class="instruction-row-btn" data-action="reset">Reset</button>
                    </div>
                </td>
                <td class="instruction-sl">${row.is_pinned ? '<div style="display:flex; align-items:center; justify-content:center; gap:0.25rem;"><span style="color:#b45309;">&#x25F3;</span><span>' + row.sort_order + '</span></div>' : row.sort_order}</td>
                <td><div class="instruction-tags">${systemTags(row)}</div></td>
                <td><div class="instruction-table-text">${escapeHtml(row.instruction_bn)}</div></td>
                <td><div class="instruction-table-text">${escapeHtml(row.instruction_en)}</div></td>
                <td><div class="instruction-table-text">${escapeHtml(row.search_alias)}</div></td>
                <td>${dosageFormChipsHtml(row)}</td>
                <td><div class="instruction-table-bool">${row.default_instruction_in_another_row === 1 ? 'Yes' : 'No'}</div></td>
                <td class="instruction-usage">${row.usage_count}</td>
            `;
            body.appendChild(tr);
        });
    }

    function applyPayload(payload) {
        state.settings = {
            show_mode: payload.settings?.show_mode === 'usage' ? 'usage' : 'serial',
            show_custom_typed: Number(payload.settings?.show_custom_typed || 0) === 1 ? 1 : 0,
        };
        state.defaultMap = {};
        (payload.default_rows || []).forEach((row) => {
            state.defaultMap[Number(row.id || 0)] = row;
        });
        state.rows = (payload.rows || []).map((row, index) => normalizeRow(row, index + 1));
        syncNextKey();
        setSettingsUi();
        render();
    }

    function buildSavePayload() {
        const rowsForSave = serialRowsSnapshot();
        return {
            action: 'save_all',
            settings: state.settings,
            rows: rowsForSave.map((row) => ({
                static_id: row.static_id,
                usage_count: row.usage_count,
                instruction_bn: row.instruction_bn,
                instruction_en: row.instruction_en,
                search_alias: row.search_alias,
                is_pinned: row.is_pinned,
                is_hidden: row.is_hidden,
                sort_order: row.sort_order,
                default_dosage_form: row.default_dosage_form,
                default_instruction_in_another_row: row.default_instruction_in_another_row,
                kind: row.kind,
            })),
        };
    }

    async function persistRows({ savingMessage = 'Saving...', savedMessage = 'Saved' } = {}) {
        const payload = buildSavePayload();
        setStatus(savingMessage);
        const response = await fetch('api/instruction_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (data.error) {
            setStatus(data.error);
            return false;
        }
        applyPayload(data);
        state.dirty = false;
        setStatus(savedMessage);
        return true;
    }

    function findRowByKey(rowKey) {
        return state.rows.find((row) => row.client_key === rowKey);
    }

    function closeEditModal() {
        state.modalRowKey = null;
        editModal.hidden = true;
        editModalError.textContent = '';
    }

    function openEditModal(row = null) {
        state.modalRowKey = row ? row.client_key : null;
        editModalTitle.textContent = row ? 'Edit Instruction' : 'Add Instruction';
        editModalBn.value = row ? row.instruction_bn : '';
        editModalEn.value = row ? row.instruction_en : '';
        editModalAlias.value = row ? row.search_alias : '';
        state.modalDoseForms = row ? dosageFormsToArray(row.default_dosage_form) : [];
        editModalDosageForm.value = '';
        renderModalDoseFormChips();
        hideDosageSuggestions();
        editModalAnotherRow.checked = row ? row.default_instruction_in_another_row === 1 : false;
        editModalPin.checked = row ? row.is_pinned === 1 : false;
        editModalAddPosition.value = 'last';
        editModalAddPositionField.hidden = Boolean(row);
        editModalError.textContent = '';
        editModal.hidden = false;
        setTimeout(() => editModalBn.focus(), 0);
    }

    function closeDeleteModal() {
        state.deleteRowKey = null;
        deleteModal.hidden = true;
        deleteModalError.textContent = '';
    }

    function openDeleteModal(row) {
        state.deleteRowKey = row.client_key;
        deleteModalError.textContent = '';
        deleteModal.hidden = false;
    }

    function openWarningModal(title, message, action) {
        pendingWarningAction = action;
        warningModalTitle.textContent = title;
        warningModalMessage.textContent = message;
        warningModal.hidden = false;
    }

    function closeWarningModal() {
        pendingWarningAction = null;
        warningModal.hidden = true;
    }

    async function runWarningAction() {
        const action = pendingWarningAction;
        if (!action) {
            closeWarningModal();
            return;
        }
        pendingWarningAction = null;
        warningModal.hidden = true;
        await action();
    }

    async function saveEditModal() {
        const instructionBn = editModalBn.value.trim();
        const instructionEn = editModalEn.value.trim();
        const searchAlias = editModalAlias.value.trim();
        if (editModalDosageForm.value.trim() !== '') {
            addModalDoseForm(editModalDosageForm.value);
        }
        const defaultDosageForm = dosageFormsFromArray(state.modalDoseForms);
        const defaultInstructionInAnotherRow = editModalAnotherRow.checked ? 1 : 0;
        const isPinned = editModalPin.checked ? 1 : 0;

        if (!instructionBn && !instructionEn && !searchAlias) {
            editModalError.textContent = 'Write at least one value before saving.';
            return;
        }

        let row = state.modalRowKey ? findRowByKey(state.modalRowKey) : null;
        if (!row) {
            const addPosition = editModalAddPosition.value === 'first' ? 'first' : 'last';
            const maxSort = state.rows.reduce((max, item) => Math.max(max, Number(item.sort_order || 0)), 0);
            if (addPosition === 'first') {
                state.rows.forEach((item) => {
                    item.sort_order = Number(item.sort_order || 0) + 1;
                });
            }
            row = normalizeRow({
                static_id: 99,
                usage_count: 0,
                instruction_bn: '',
                instruction_en: '',
                search_alias: '',
                is_pinned: isPinned,
                is_hidden: 0,
                sort_order: addPosition === 'first' ? 1 : maxSort + 1,
                default_dosage_form: '[]',
                default_instruction_in_another_row: 0,
                is_edited: 0,
                kind: 'added',
            }, addPosition === 'first' ? 1 : maxSort + 1);
            state.rows.push(row);
            syncNextKey();
        }

        row.instruction_bn = instructionBn;
        row.instruction_en = instructionEn;
        row.search_alias = searchAlias;
        row.is_pinned = isPinned;
        row.default_dosage_form = defaultDosageForm;
        row.default_instruction_in_another_row = defaultInstructionInAnotherRow;
        updateSystemEditedFlag(row);
        render();

        const saved = await persistRows({
            savingMessage: 'Saving instruction...',
            savedMessage: 'Instruction saved',
        });
        if (saved) {
            closeEditModal();
        }
    }

    body.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        const tr = event.target.closest('tr');
        if (!button || !tr) return;
        const row = findRowByKey(tr.dataset.rowKey);
        if (!row) return;

        const action = button.dataset.action;
        if (action === 'toggle-forms') {
            row.forms_expanded = !row.forms_expanded;
            render();
            return;
        }

        if (action === 'toggle-pin') {
            if (row.is_hidden === 1) {
                return;
            }
            row.is_pinned = row.is_pinned === 1 ? 0 : 1;
            render();
            persistRows({
                savingMessage: 'Updating pin...',
                savedMessage: 'Pin updated',
            });
            return;
        }

        if (action === 'toggle-edit') {
            openEditModal(row);
            return;
        }

        if (action === 'delete') {
            if (row.is_hidden === 1) {
                row.is_hidden = 0;
                state.showDeletedSystem = false;
                render();
                persistRows({
                    savingMessage: 'Restoring instruction...',
                    savedMessage: 'Instruction restored',
                });
                return;
            }

            if (row.kind === 'system') {
                row.is_hidden = 1;
                row.is_pinned = 0;
                render();
                persistRows({
                    savingMessage: 'Hiding instruction...',
                    savedMessage: 'Instruction hidden',
                });
                return;
            }

            openDeleteModal(row);
            return;
        }

        if (action === 'reset') {
            openWarningModal('Reset instruction?', 'This will reset this instruction. This action cannot be reversed.', async () => {
                resetRowToDefault(row);
                state.showDeletedSystem = false;
                render();
                await persistRows({
                    savingMessage: 'Resetting instruction...',
                    savedMessage: 'Instruction reset',
                });
            });
        }
    });

    addBtn.addEventListener('click', () => {
        openEditModal(null);
    });

    deleteAllSystemBtn.addEventListener('click', () => {
        openWarningModal('Remove all system instructions?', 'This will hide all system instructions and cannot be reversed. Continue?', async () => {
            let changed = false;
            state.rows.forEach((row) => {
                if (row.kind === 'system' && row.is_hidden !== 1) {
                    row.is_hidden = 1;
                    row.is_pinned = 0;
                    changed = true;
                }
            });
            if (!changed) {
                setStatus('All system instructions are already hidden');
                return;
            }
            state.showDeletedSystem = false;
            render();
            await persistRows({
                savingMessage: 'Removing all system instructions...',
                savedMessage: 'System instructions hidden',
            });
        });
    });

    showDeletedSystemBtn.addEventListener('click', () => {
        state.showDeletedSystem = !state.showDeletedSystem;
        render();
    });

    filterField.addEventListener('change', () => {
        state.filterField = filterField.value || 'all';
        render();
    });

    filterInput.addEventListener('input', () => {
        state.filterQuery = filterInput.value || '';
        render();
    });

    modeButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const nextMode = button.dataset.showMode === 'usage' ? 'usage' : 'serial';
            if (state.settings.show_mode === nextMode) {
                return;
            }
            state.settings.show_mode = nextMode;
            setSettingsUi();
            render();
            await persistRows({
                savingMessage: 'Saving sorting setting...',
                savedMessage: 'Sorting setting saved',
            });
        });
    });

    showCustomTypedInput.addEventListener('change', async () => {
        state.settings.show_custom_typed = showCustomTypedInput.checked ? 1 : 0;
        await persistRows({
            savingMessage: 'Saving template setting...',
            savedMessage: 'Template setting saved',
        });
    });

    resetDefaultSettingsBtn.addEventListener('click', async () => {
        state.settings.show_mode = 'serial';
        state.settings.show_custom_typed = 1;
        setSettingsUi();
        render();
        await persistRows({
            savingMessage: 'Resetting view settings...',
            savedMessage: 'View settings reset',
        });
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.dataset.sortKey || '';
            if (state.columnSort.key === key) {
                state.columnSort.direction = state.columnSort.direction === 'desc' ? 'asc' : 'desc';
            } else {
                state.columnSort.key = key;
                state.columnSort.direction = 'desc';
            }
            setSettingsUi();
            render();
        });
    });

    document.getElementById('instruction-reset-default-order').addEventListener('click', () => {
        openWarningModal('Reset default order?', 'This will reset system instruction order to the default order. This action cannot be reversed.', async () => {
            state.rows.forEach((row) => {
                if (row.kind === 'system') {
                    const defaults = state.defaultMap[row.static_id];
                    row.sort_order = Number(defaults?.sort_order || row.sort_order);
                }
            });
            render();
            await persistRows({
                savingMessage: 'Resetting default order...',
                savedMessage: 'Default order reset',
            });
        });
    });

    document.getElementById('instruction-remove-custom-typed').addEventListener('click', () => {
        openWarningModal('Remove custom typed instructions?', 'This will hide all custom typed instructions and cannot be reversed. Continue?', async () => {
            state.rows.forEach((row) => {
                if (row.kind === 'custom_typed') {
                    row.is_hidden = 1;
                }
            });
            render();
            await persistRows({
                savingMessage: 'Removing custom typed instructions...',
                savedMessage: 'Custom typed instructions removed',
            });
        });
    });

    document.getElementById('instruction-remove-added').addEventListener('click', () => {
        openWarningModal('Remove added instructions?', 'This will remove all added instructions and cannot be reversed. Continue?', async () => {
            state.rows = state.rows.filter((row) => row.kind !== 'added');
            syncNextKey();
            render();
            await persistRows({
                savingMessage: 'Removing added instructions...',
                savedMessage: 'Added instructions removed',
            });
        });
    });

    document.getElementById('instruction-reset-usage').addEventListener('click', () => {
        openWarningModal('Reset usage count?', 'This will reset all instruction usage counts to 0. This action cannot be reversed.', async () => {
            state.rows.forEach((row) => {
                row.usage_count = 0;
            });
            render();
            await persistRows({
                savingMessage: 'Resetting usage count...',
                savedMessage: 'Usage count reset',
            });
        });
    });

    document.getElementById('instruction-reset-full').addEventListener('click', async () => {
        openWarningModal('Reset full template?', 'This will delete all instruction entries from the user database and reload defaults. This action cannot be reversed.', async () => {
            setStatus('Resetting full instruction template...');
            const response = await fetch('api/instruction_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset_full' }),
            });
            const data = await response.json();
            if (data.error) {
                setStatus(data.error);
                return;
            }
            applyPayload(data);
            state.dirty = false;
            setStatus('Full reset complete');
        });
    });

    warningModalNo.addEventListener('click', closeWarningModal);
    warningModalYes.addEventListener('click', runWarningAction);
    editModalCancel.addEventListener('click', closeEditModal);
    editModalSave.addEventListener('click', saveEditModal);
    editModalDosageForm.addEventListener('input', () => {
        fetchDosageSuggestions(editModalDosageForm.value.trim());
    });
    editModalDosageForm.addEventListener('focus', () => {
        fetchDosageSuggestions(editModalDosageForm.value.trim());
    });
    editModalDosageForm.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addModalDoseForm(editModalDosageForm.value);
            return;
        }
        if (event.key === 'Backspace' && editModalDosageForm.value === '' && state.modalDoseForms.length > 0) {
            removeModalDoseForm(state.modalDoseForms.length - 1);
        }
    });
    editModalDosageChips.addEventListener('click', (event) => {
        const button = event.target.closest('[data-dose-form-index]');
        if (!button) return;
        removeModalDoseForm(Number(button.dataset.doseFormIndex || 0));
        editModalDosageForm.focus();
    });
    editModalDosageSuggestions.addEventListener('mousedown', (event) => {
        const button = event.target.closest('[data-dose-form-suggestion]');
        if (!button) return;
        event.preventDefault();
        addModalDoseForm(button.dataset.doseFormSuggestion || '');
        editModalDosageForm.focus();
    });
    document.addEventListener('mousedown', (event) => {
        if (!editModal.hidden && !event.target.closest('#instruction-modal-dosage-editor')) {
            hideDosageSuggestions();
        }
    });
    deleteModalCancel.addEventListener('click', closeDeleteModal);
    deleteModalTemporary.addEventListener('click', async () => {
        const row = findRowByKey(state.deleteRowKey);
        if (!row) {
            closeDeleteModal();
            return;
        }
        row.is_hidden = 1;
        render();
        const saved = await persistRows({
            savingMessage: 'Temporarily deleting instruction...',
            savedMessage: 'Instruction hidden',
        });
        if (saved) {
            closeDeleteModal();
        }
    });
    deleteModalPermanent.addEventListener('click', async () => {
        const rowKey = state.deleteRowKey;
        if (!rowKey) {
            closeDeleteModal();
            return;
        }
        state.rows = state.rows.filter((row) => row.client_key !== rowKey);
        syncNextKey();
        render();
        const saved = await persistRows({
            savingMessage: 'Permanently deleting instruction...',
            savedMessage: 'Instruction deleted',
        });
        if (saved) {
            closeDeleteModal();
        }
    });

    body.addEventListener('mousedown', (event) => {
        const handle = event.target.closest('.instruction-handle');
        if (!handle) return;
        const tr = handle.closest('tr');
        if (!tr) return;
        const row = findRowByKey(tr.dataset.rowKey);
        if (!row) return;
        if (row.is_hidden === 1) {
            tr.draggable = false;
            tr.removeAttribute('data-drag-ready');
            tr.removeAttribute('data-drag-section');
            return;
        }

        const pinnedRows = state.rows.filter((item) => item.is_pinned === 1);
        const dragSection = row.is_pinned === 1 ? 'pinned' : 'unpinned';
        if (dragSection === 'pinned' && pinnedRows.length < 2) {
            tr.draggable = false;
            tr.removeAttribute('data-drag-ready');
            tr.removeAttribute('data-drag-section');
            return;
        }

        tr.draggable = true;
        tr.dataset.dragReady = '1';
        tr.dataset.dragSection = dragSection;
    });

    body.addEventListener('dragstart', (event) => {
        const tr = event.target.closest('tr');
        if (!tr || !tr.draggable || tr.dataset.dragReady !== '1') {
            event.preventDefault();
            return;
        }
        state.dragKey = tr.dataset.rowKey;
        state.dragRow = tr;
        state.dragSection = tr.dataset.dragSection || '';
        state.dragSerialSnapshot = serialRowsSnapshot();
        tr.classList.add('instruction-row-dragging');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', state.dragKey);
        }
    });

    body.addEventListener('dragover', (event) => {
        if (!state.dragRow) return;
        // Always prevent default so the browser registers tbody as a valid drop target
        // and the 'drop' event will fire on release, even if we don't move the row.
        event.preventDefault();

        const targetTr = event.target.closest('tr');
        if (!targetTr || targetTr === state.dragRow) return;
        const targetRow = findRowByKey(targetTr.dataset.rowKey);
        if (!targetRow) return;
        const targetSection = targetRow.is_pinned === 1 ? 'pinned' : 'unpinned';
        if (targetSection !== state.dragSection) return;

        const rect = targetTr.getBoundingClientRect();
        const insertAfter = event.clientY > rect.top + rect.height / 2;
        body.insertBefore(state.dragRow, insertAfter ? targetTr.nextSibling : targetTr);
    });

    body.addEventListener('drop', async (event) => {
        if (!state.dragRow) return;
        event.preventDefault();
        state.dropSucceeded = true;

        // Collect all row keys from the DOM in their current visual order.
        const domOrderedKeys = Array.from(body.querySelectorAll('tr[data-row-key]'))
            .map((tr) => tr.dataset.rowKey);

        const previousRows = state.dragSerialSnapshot.length
            ? state.dragSerialSnapshot
            : serialRowsSnapshot();

        state.rows = buildNewRowOrder(domOrderedKeys, previousRows, state.dragSection);
        markDirty();
        render();
        await persistRows({
            savingMessage: 'Saving instruction order...',
            savedMessage: 'Instruction order saved',
        });
    });

    body.addEventListener('dragend', () => {
        const wasCancelled = !state.dropSucceeded && !!state.dragRow;

        // Clean up any dragging visual state still in the DOM.
        body.querySelectorAll('.drop-target, .instruction-row-dragging').forEach((row) => {
            row.classList.remove('drop-target', 'instruction-row-dragging');
            row.draggable = false;
            row.removeAttribute('data-drag-ready');
            row.removeAttribute('data-drag-section');
        });

        state.dragKey = null;
        state.dragRow = null;
        state.dragSection = '';
        state.dragSerialSnapshot = [];
        state.dropSucceeded = false;

        // If the drag was cancelled (Escape / drop outside) the dragover handler may
        // have already moved DOM rows.  Re-render from state to keep them in sync.
        if (wasCancelled) {
            render();
        }
    });

    applyPayload(initialPayload);
    state.dirty = false;
    setStatus('');
});
</script>
<?php include 'footer.php'; ?>
