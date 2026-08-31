<?php
require_once 'auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/rx_template_lib.php';

$phraseTemplateType = 'duration';
$phraseConfig = rx_template_config($phraseTemplateType);
$phraseDoctorId = rx_active_doctor_id();
rx_phrase_ensure_schema($phraseTemplateType);
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
<style>
html { overflow-y: scroll; scrollbar-gutter: stable; }
.phrase-template-page { margin-bottom: 2rem; }
.phrase-template-card { background: #fff; border: 1px solid #dbe3ee; border-radius: 14px; box-shadow: 0 10px 30px rgba(15,23,42,0.08); overflow: visible; }
.phrase-hero { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding: 1.2rem 1.35rem; background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); border-bottom: 1px solid #dbe3ee; border-top-left-radius: 14px; border-top-right-radius: 14px; overflow: hidden; background-clip: padding-box; }
.phrase-hero h1 { margin: 0; font-size: 1.4rem; color: #0f172a; }
.phrase-hero p { margin: 0.35rem 0 0; color: #64748b; font-size: 0.92rem; }
.phrase-toolbar, .phrase-bulk { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; padding: 1rem 1.35rem; border-bottom: 1px solid #e2e8f0; }
.phrase-toolbar { background: #fff; }
.phrase-bulk { background: #f8fafc; }
.phrase-toolbar-main { display: grid; gap: 0.85rem; min-width: 0; width: 100%; }
.phrase-bulk-spacer { flex: 1 1 auto; }
.phrase-settings-box { display: flex; flex-wrap: wrap; gap: 0.85rem 1rem; align-items: center; padding: 0.2rem 0 0; min-width: 0; }
.phrase-settings-box-title { margin: 0; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; font-weight: 700; color: #475569; white-space: nowrap; }
.phrase-settings-box-title-icon { width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; color: #64748b; flex: 0 0 auto; }
.phrase-settings-box-title-icon svg { width: 100%; height: 100%; display: block; }
.phrase-settings-box-body { display: flex; flex-wrap: wrap; gap: 0.85rem 1rem; align-items: center; min-width: 0; flex: 1 1 auto; }
.phrase-settings-reset-wrap { flex: 1 1 220px; display: flex; justify-content: flex-end; }
.phrase-settings-reset-btn { min-width: 190px; }
.phrase-segment { display: inline-flex; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
.phrase-segment button { border: 0; background: transparent; padding: 0.7rem 1.1rem; cursor: pointer; font: inherit; font-weight: 600; color: #334155; }
.phrase-segment button.active { background: #1d4ed8; color: #fff; }
.phrase-toggle { display: inline-flex; align-items: center; gap: 0.6rem; border: 1px solid #dbe3ee; background: #f8fafc; border-radius: 12px; padding: 0.72rem 0.95rem; color: #334155; }
.phrase-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; line-height: 1.2; white-space: nowrap; border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 9px; padding: 0.36rem 0.52rem; cursor: pointer; font: inherit; font-size: 0.84rem; font-weight: 400; }
.phrase-btn.primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.phrase-btn.danger { color: #b91c1c; border-color: #fecaca; background: #fff1f2; }
.phrase-dropdown { position: relative; display: inline-flex; padding-bottom: 6px; margin-bottom: -6px; }
.phrase-dropdown-trigger { position: relative; padding-right: 1.1rem; display: inline-flex; align-items: center; gap: 0.2rem; }
.phrase-dropdown-trigger::after {
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
.phrase-dropdown-menu { position: absolute; top: calc(100% - 1px); left: 0; z-index: 2500; min-width: 240px; padding: 0.35rem; background: #fff; border: 1px solid #dbe3ee; border-radius: 12px; box-shadow: 0 16px 40px rgba(15,23,42,0.14); display: none; gap: 0.25rem; }
.phrase-dropdown:hover .phrase-dropdown-menu, .phrase-dropdown:focus-within .phrase-dropdown-menu { display: grid; }
.phrase-dropdown-item { width: 100%; text-align: left; border: 1px solid transparent; background: #fff; color: #0f172a; border-radius: 9px; padding: 0.48rem 0.68rem; cursor: pointer; font: inherit; font-size: 0.84rem; font-weight: 400; }
.phrase-dropdown-item:hover { background: #eff6ff; }
.phrase-dropdown-item.danger { color: #b91c1c; }
.phrase-dropdown-item.danger:hover { background: #fff1f2; }
.phrase-filter { margin-left: auto; display: flex; align-items: stretch; gap: 0; flex: 1 1 360px; min-width: 280px; max-width: 520px; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; background: #fff; }
.phrase-filter select, .phrase-filter input { height: 40px; border: 0; background: transparent; color: #0f172a; border-radius: 0; font: inherit; font-size: 0.88rem; padding: 0 0.75rem; min-width: 0; }
.phrase-filter select { flex: 0 0 205px; border-right: 1px solid #cbd5e1; padding: 0 0.65rem; }
.phrase-filter input { flex: 1; }
.phrase-filter:focus-within { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.phrase-filter select:focus, .phrase-filter input:focus { outline: none; }
.phrase-table-wrap { overflow-x: auto; overflow-y: visible; background: #fff; }
.phrase-table { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; min-width: 1180px; }
.phrase-table th, .phrase-table td { border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; padding: 0.42rem 0.48rem; vertical-align: middle; }
.phrase-table th { background: #f8fafc; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; font-size: 0.78rem; text-align: left; }
.phrase-table td:last-child, .phrase-table th:last-child { border-right: 0; }
.phrase-row.hidden td { background: #fff1f2; }
.phrase-row.pinned td { background: #fffbeb; }
.phrase-handle { position: relative; width: 42px; text-align: center; color: #64748b; cursor: grab; user-select: none; background: transparent; }
.phrase-handle-pin { position: absolute; top: 3px; left: 3px; width: 14px; height: 14px; object-fit: contain; pointer-events: none; z-index: 2; opacity: 0.95; }
.phrase-actions { display: flex; flex-wrap: nowrap; gap: 0.28rem; align-items: center; justify-content: center; width: 100%; }
.phrase-actions .phrase-btn { padding: 0.34rem 0.5rem; white-space: nowrap; }
.phrase-sl, .phrase-usage { text-align: center; font-weight: 700; }
.phrase-usage { color: #1d4ed8; }
.phrase-tags { display: flex; flex-wrap: nowrap; gap: 0.35rem; align-items: center; white-space: nowrap; }
.phrase-tag { display: inline-flex; border-radius: 999px; padding: 0.16rem 0.45rem; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.phrase-tag.system { background: #eff6ff; color: #1d4ed8; }
.phrase-tag.custom { background: #ecfccb; color: #3f6212; }
.phrase-tag.added { background: #ede9fe; color: #6d28d9; }
.phrase-tag.hidden { background: #fee2e2; color: #b91c1c; }
.phrase-text { min-height: 34px; padding: 0.35rem 0.5rem; color: #0f172a; line-height: 1.35; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
.phrase-form-chip { display: inline-flex; border: 1px solid #bfdbfe; background: #eff6ff; color: #1e40af; border-radius: 999px; padding: 0.16rem 0.45rem; font-size: 0.72rem; font-weight: 700; margin: 0.1rem; }
.phrase-status { min-height: 22px; color: #0f766e; font-weight: 600; }
.phrase-modal-backdrop[hidden] { display: none; }
.phrase-modal-backdrop { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15,23,42,0.35); backdrop-filter: blur(7px); z-index: 2000; }
.phrase-modal { width: min(720px, 100%); background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(15,23,42,0.24); overflow: hidden; }
.phrase-modal header { padding: 1rem 1.2rem; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 1.05rem; }
.phrase-modal-body { padding: 1rem 1.2rem; display: grid; gap: 0.8rem; }
.phrase-modal-field { display: grid; gap: 0.32rem; }
.phrase-modal-field label { font-size: 0.78rem; font-weight: 800; text-transform: uppercase; color: #334155; letter-spacing: 0.04em; }
.phrase-modal-field input, .phrase-modal-field textarea, .phrase-modal-field select { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0.55rem 0.65rem; font: inherit; }
.phrase-modal-actions { display: flex; justify-content: flex-end; gap: 0.65rem; padding: 1rem 1.2rem; border-top: 1px solid #e2e8f0; }
</style>

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

<script>
(() => {
    const initial = <?= json_encode($phrasePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const type = initial.config.type;
    const hasDefaultForm = Boolean(initial.config.has_default_form);
    let state = initial;
    let editRow = null;
    const body = document.getElementById('phrase-body');
    const status = document.getElementById('phrase-status');
    const search = document.getElementById('phrase-search');
    const field = document.getElementById('phrase-filter-field');
    const showCustom = document.getElementById('phrase-show-custom');
    const modal = document.getElementById('phrase-modal');
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    const norm = (value) => String(value ?? '').toLowerCase().trim();
    const forms = (value) => {
        if (Array.isArray(value)) return value;
        try { const parsed = JSON.parse(value || '[]'); return Array.isArray(parsed) ? parsed : []; } catch { return String(value || '').split(',').map(v => v.trim()).filter(Boolean); }
    };
    const tags = (row) => {
        const items = [];
        if (row.kind === 'system') items.push('<span class="phrase-tag system">System</span>');
        if (row.kind === 'custom_typed') items.push('<span class="phrase-tag custom">Custom Typed</span>');
        if (row.kind === 'added') items.push('<span class="phrase-tag added">Added</span>');
        if (Number(row.is_hidden) === 1) items.push('<span class="phrase-tag hidden">Hidden</span>');
        return items.join('');
    };
    const setStatus = (text) => {
        status.textContent = text || '';
        if (text) window.setTimeout(() => { if (status.textContent === text) status.textContent = ''; }, 1800);
    };
    const api = async (payload = null) => {
        const options = payload ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type, ...payload }) } : {};
        const response = await fetch(`api/rx_template.php?type=${encodeURIComponent(type)}`, options);
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        state = data;
        render();
        return data;
    };
    const filteredRows = () => {
        const q = norm(search.value);
        const f = field.value;
        return state.rows.filter(row => {
            if (!q) return true;
            const values = [];
            if (f === 'all' || f === 'bn') values.push(row.value_bn);
            if (f === 'all' || f === 'en') values.push(row.value_en);
            if (f === 'all' || f === 'alias') values.push(row.search_alias);
            if (hasDefaultForm && (f === 'all' || f === 'form')) values.push(forms(row.default_dosage_form).join(', '));
            return values.some(value => norm(value).includes(q));
        });
    };
    const render = () => {
        document.querySelectorAll('[data-mode]').forEach(btn => btn.classList.toggle('active', btn.dataset.mode === state.settings.show_mode));
        showCustom.checked = Number(state.settings.show_custom_typed) === 1;
        body.innerHTML = filteredRows().map((row, index) => {
            const formMarkup = hasDefaultForm ? `<td>${forms(row.default_dosage_form).length ? forms(row.default_dosage_form).map(item => `<span class="phrase-form-chip">${esc(item)}</span>`).join('') : '-'}</td>` : '';
            const hideLabel = Number(row.is_hidden) === 1 ? 'Restore' : 'Remove';
            const pinLabel = Number(row.is_pinned) === 1 ? 'Unpin' : 'Pin it';
            return `<tr class="phrase-row pc-row ${Number(row.is_hidden) === 1 ? 'hidden' : ''} ${Number(row.is_pinned) === 1 ? 'pinned' : ''}" data-index="${index}" data-id="${row.id}" draggable="true">
                <td class="phrase-handle pc-drag">${Number(row.is_pinned) === 1 ? '<img class="phrase-handle-pin" src="assets/images/pin.svg" alt="Pinned">' : ''}<button type="button" class="pc-row-move-btn zrx-drag-handle" style="width:100%; height:100%; border:none; background:transparent; padding:0; display:flex; align-items:center; justify-content:center; cursor:grab;" title="Move Row"><?= zrx_icon('move', 14) ?></button></td>
                <td><div class="phrase-actions">
                    <button class="phrase-btn" data-action="pin">${pinLabel}</button>
                    <button class="phrase-btn" data-action="edit">Edit</button>
                    <button class="phrase-btn" data-action="hide">${hideLabel}</button>
                    <button class="phrase-btn" data-action="reset">Reset</button>
                </div></td>
                <td class="phrase-sl pc-row-no">${esc(row.sort_order)}</td>
                <td><div class="phrase-tags">${tags(row)}</div></td>
                <td><div class="phrase-text">${esc(row.value_bn)}</div></td>
                <td><div class="phrase-text">${esc(row.value_en)}</div></td>
                <td><div class="phrase-text">${esc(row.search_alias)}</div></td>
                ${formMarkup}
                <td class="phrase-usage">${esc(row.usage_count)}</td>
            </tr>`;
        }).join('');
    };

    body.addEventListener('zrx:reordered', async () => {
        const rowsInDom = Array.from(body.querySelectorAll('.phrase-row'));
        const ids = rowsInDom.map(r => Number(r.dataset.id)).filter(id => id > 0);
        if (ids.length) {
            await api({ action: 'reorder', ids });
            setStatus('Order saved');
        }
    });
    const openModal = (row = null) => {
        editRow = row;
        document.getElementById('phrase-modal-title').textContent = row ? 'Edit Template' : 'Add New Template';
        document.getElementById('phrase-row-id').value = row?.id || '';
        document.getElementById('phrase-static-id').value = row?.static_id ?? 99;
        document.getElementById('phrase-row-pinned').checked = Number(row?.is_pinned || 0) === 1;
        document.getElementById('phrase-value-bn').value = row?.value_bn || '';
        document.getElementById('phrase-value-en').value = row?.value_en || '';
        document.getElementById('phrase-search-alias').value = row?.search_alias || '';
        if (hasDefaultForm) document.getElementById('phrase-default-form').value = forms(row?.default_dosage_form).join(', ');
        document.getElementById('phrase-add-position-wrap').style.display = row ? 'none' : 'grid';
        document.getElementById('phrase-add-position').value = 'last';
        modal.hidden = false;
    };
    const closeModal = () => { modal.hidden = true; editRow = null; };
    document.getElementById('phrase-add').addEventListener('click', () => openModal());
    document.getElementById('phrase-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
    document.getElementById('phrase-save').addEventListener('click', async () => {
        const row = {
            id: Number(document.getElementById('phrase-row-id').value || 0),
            static_id: Number(document.getElementById('phrase-static-id').value || 99),
            value_bn: document.getElementById('phrase-value-bn').value,
            value_en: document.getElementById('phrase-value-en').value,
            search_alias: document.getElementById('phrase-search-alias').value,
            is_pinned: document.getElementById('phrase-row-pinned').checked ? 1 : 0,
            sort_order: editRow?.sort_order || 0,
            usage_count: editRow?.usage_count || 0,
            add_position: document.getElementById('phrase-add-position').value
        };
        if (hasDefaultForm) row.default_dosage_form = document.getElementById('phrase-default-form').value;
        await api({ action: 'save_row', row });
        closeModal();
        setStatus('Saved');
    });
    body.addEventListener('click', async event => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;
        const tr = button.closest('tr');
        const row = filteredRows()[Number(tr.dataset.index)];
        if (!row) return;
        const action = button.dataset.action;
        if (action === 'edit') return openModal(row);
        if (action === 'pin') await api({ action: 'toggle_pin', id: row.id, static_id: row.static_id });
        if (action === 'hide') await api({ action: 'toggle_hidden', id: row.id, static_id: row.static_id });
        if (action === 'reset' && confirm('Reset this template? This cannot be reversed.')) await api({ action: 'reset_row', id: row.id, static_id: row.static_id });
        setStatus('Saved');
    });
    document.querySelectorAll('[data-bulk]').forEach(button => button.addEventListener('click', async () => {
        if (!confirm('This action cannot be reversed. Continue?')) return;
        await api({ action: button.dataset.bulk });
        setStatus('Saved');
    }));
    document.querySelectorAll('[data-mode]').forEach(button => button.addEventListener('click', async () => {
        state.settings.show_mode = button.dataset.mode;
        await api({ action: 'save_settings', settings: state.settings });
        setStatus('Saved');
    }));
    showCustom.addEventListener('change', async () => {
        state.settings.show_custom_typed = showCustom.checked ? 1 : 0;
        await api({ action: 'save_settings', settings: state.settings });
        setStatus('Saved');
    });
    document.getElementById('phrase-reset-settings').addEventListener('click', async () => {
        state.settings = { show_mode: 'serial', show_custom_typed: 1 };
        await api({ action: 'save_settings', settings: state.settings });
        setStatus('View settings reset');
    });
    search.addEventListener('input', render);
    field.addEventListener('change', render);
    render();
})();
</script>

<?php include 'footer.php'; ?>

