<?php
require_once 'auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/rx_template_lib.php';

$phraseTemplateType = 'advice';
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
    'default_rows' => rx_phrase_static_rows($phraseTemplateType),
];
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
.phrase-btn:disabled { opacity: 0.5; cursor: not-allowed; }
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
.phrase-table { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
.phrase-table th, .phrase-table td { border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; padding: 0.42rem 0.48rem; vertical-align: middle; }
.phrase-table th:first-child, .phrase-table td:first-child { border-left: 1px solid #e2e8f0; }
.phrase-table th { background: #f8fafc; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; text-align: left; border-top: 1px solid #e2e8f0; }
.phrase-table td:last-child, .phrase-table th:last-child { border-right: 0; }
.phrase-row.hidden td { background: #fff1f2; }
.phrase-row.pinned td { background: #fffbeb; }
.phrase-handle { position: relative; cursor: grab; text-align: center; color: #64748b; font-size: 1rem; user-select: none; background: transparent; }
.phrase-handle:active { cursor: grabbing; }
.phrase-handle svg { pointer-events: none; }
.phrase-handle-pin { position: absolute; top: 3px; left: 3px; width: 14px; height: 14px; object-fit: contain; pointer-events: none; z-index: 2; opacity: 0.85; }
.phrase-pin-btn, .phrase-row-btn, .phrase-add-btn, .phrase-save-btn, .phrase-bulk-btn { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 9px; padding: 0.36rem 0.52rem; cursor: pointer; font: inherit; font-size: 0.84rem; font-weight: 400; }
.phrase-pin-btn.active { color: #b45309; border-color: #f59e0b; background: #fff7ed; }
.phrase-pin-btn:disabled { cursor: not-allowed; opacity: 0.45; background: #f8fafc; color: #64748b; border-color: #cbd5e1; }
.phrase-add-btn, .phrase-save-btn { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.phrase-save-btn { min-width: 140px; font-weight: 700; }
.phrase-bulk-btn.danger { border-color: #fecaca; color: #b91c1c; background: #fff1f2; }
.phrase-bulk-btn.active { border-color: #f97316; color: #9a3412; background: #ffedd5; }
.phrase-pin-cell, .phrase-actions-cell { text-align: center; }
.phrase-pin-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem; white-space: nowrap; min-width: 58px; }
.phrase-btn { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 9px; padding: 0.36rem 0.52rem; cursor: pointer; font: inherit; font-size: 0.84rem; font-weight: 400; white-space: nowrap; }
.phrase-btn.primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.phrase-row-btn { white-space: nowrap; padding: 0.34rem 0.5rem; }
.phrase-row-btn:hover { background: #f8fafc; }

.phrase-sortable-th { padding: 0 !important; }
.phrase-sort-button { width: 100%; border: none; background: transparent; padding: 0.52rem 0.45rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.18rem; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; cursor: pointer; }
.phrase-sort-button:hover { background: rgba(37, 99, 235, 0.05); }
.phrase-sort-button.active { color: #1d4ed8; }
.phrase-sort-label { line-height: 1.2; }
.phrase-sort-icon { width: 10px; display: flex; flex-direction: column; align-items: center; gap: 2px; color: #94a3b8; line-height: 0; }
.phrase-sort-triangle { width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; opacity: 0.4; }
.phrase-sort-triangle.up { border-bottom: 5px solid currentColor; }
.phrase-sort-triangle.down { border-top: 5px solid currentColor; }
.phrase-sort-button.active .phrase-sort-icon { color: #1d4ed8; }
.phrase-sort-button.active[data-sort-direction="desc"] .phrase-sort-triangle.down,
.phrase-sort-button.active[data-sort-direction="asc"] .phrase-sort-triangle.up { opacity: 1; }

.phrase-row-btn.toggle-hidden { min-width: 88px; }
.phrase-row-btn.toggle-hidden.is-hidden { border-color: #16a34a; color: #166534; background: #f0fdf4; }
.phrase-actions { display: flex; flex-wrap: nowrap; gap: 0.28rem; align-items: center; justify-content: center; width: 100%; }
.phrase-actions .phrase-btn { padding: 0.34rem 0.5rem; white-space: nowrap; }
.phrase-sl, .phrase-usage { text-align: center; font-weight: 700; }
.phrase-usage { color: #1d4ed8; }
.phrase-tags { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
.phrase-tag { display: inline-flex; border-radius: 999px; padding: 0.16rem 0.45rem; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.phrase-tag.system { background: #eff6ff; color: #1d4ed8; }
.phrase-tag.custom { background: #ecfccb; color: #3f6212; }
.phrase-tag.added { background: #ede9fe; color: #6d28d9; }
.phrase-tag.hidden { background: #fee2e2; color: #b91c1c; }
.phrase-text { min-height: 34px; padding: 0.35rem 0.5rem; color: #0f172a; line-height: 1.35; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
.phrase-status { min-height: 22px; color: #0f766e; font-weight: 600; }
.phrase-modal-backdrop[hidden] { display: none; }
.phrase-modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.35); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; padding: 1.25rem; z-index: 3000; }
.phrase-modal { width: min(760px, 100%); max-height: calc(100vh - 3rem); display: flex; flex-direction: column; background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24); overflow: visible; }
.phrase-modal-header { padding: 1rem 1.2rem; border-bottom: 1px solid #e2e8f0; }
.phrase-modal-header h2 { margin: 0; font-size: 1.08rem; color: #0f172a; }
.phrase-modal-body { padding: 1rem 1.2rem; display: grid; gap: 0.85rem; overflow-y: auto; }
.phrase-modal-field { display: grid; gap: 0.35rem; }
.phrase-modal-field label { font-size: 0.82rem; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.03em; }
.phrase-modal-field input, .phrase-modal-field textarea { width: 100%; min-height: 42px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0.55rem 0.7rem; font: inherit; color: #0f172a; background: #fff; }
.phrase-modal-field input:focus, .phrase-modal-field textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.phrase-modal-actions { padding: 0 1.2rem 1.15rem; display: flex; justify-content: flex-end; gap: 0.7rem; }
.phrase-modal-btn { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 10px; padding: 0.55rem 0.95rem; cursor: pointer; font: inherit; font-weight: 600; }
.phrase-modal-btn.primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.phrase-col-category { width: 200px; }
.phrase-col-usage { width: 90px; }

/* Redesign drag/drop and list styling */
.category-dragging td { opacity: 0.4; }
.drop-target td { background-color: #dbeafe !important; }
.modal-advice-row { display: grid; grid-template-columns: 32px 1fr 1fr 40px; gap: 0.75rem; align-items: center; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0.75rem; transition: border-color 0.15s ease-in-out; }
.modal-advice-row:hover { border-color: #2563eb; }
.modal-advice-textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 8px; font-size: 0.88rem; height: 38px; resize: none; font-family: inherit; box-sizing: border-box; overflow: hidden; }
.modal-advice-textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
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

<script>
(() => {
    const initial = <?= json_encode($phrasePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const type = initial.config.type;
    let state = initial;
    let editCategoryKey = null;
    let modalAdvices = [];
    let draggedCategoryKey = null;

    const body = document.getElementById('phrase-body');
    const status = document.getElementById('phrase-status');
    const search = document.getElementById('phrase-search');
    const field = document.getElementById('phrase-filter-field');
    const showCustom = document.getElementById('phrase-show-custom');
    const modal = document.getElementById('phrase-modal');
    
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    const norm = (value) => String(value ?? '').toLowerCase().trim();

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
            if (f === 'all' || f === 'category') values.push(row.category_bn, row.category_en, row.category_search_alias);
            if (f === 'all' || f === 'bn') values.push(row.value_bn);
            if (f === 'all' || f === 'en') values.push(row.value_en);
            return values.some(value => norm(value).includes(q));
        });
    };

    const groupRowsByCategory = (rows) => {
        const groups = [];
        const groupMap = new Map();

        rows.forEach(row => {
            const catKey = (row.category_en || row.category_bn || 'Uncategorized').trim();
            if (!groupMap.has(catKey)) {
                const group = {
                    key: catKey,
                    category_en: row.category_en || '',
                    category_bn: row.category_bn || '',
                    category_search_alias: row.category_search_alias || '',
                    is_pinned: 0,
                    is_hidden: 1,
                    sort_order: row.sort_order,
                    advices: [],
                    kinds: new Set(),
                };
                groupMap.set(catKey, group);
                groups.push(group);
            }
            const group = groupMap.get(catKey);
            group.advices.push(row);
            group.kinds.add(row.kind);
            if (Number(row.is_pinned) === 1) {
                group.is_pinned = 1;
            }
            if (Number(row.is_hidden) === 0) {
                group.is_hidden = 0;
            }
            if (row.sort_order < group.sort_order) {
                group.sort_order = row.sort_order;
            }
        });

        if (state.settings.show_mode === 'usage') {
            groups.sort((a, b) => {
                if (a.is_pinned !== b.is_pinned) {
                    return b.is_pinned - a.is_pinned;
                }
                const aMaxUsage = Math.max(...a.advices.map(ad => ad.usage_count), 0);
                const bMaxUsage = Math.max(...b.advices.map(ad => ad.usage_count), 0);
                if (bMaxUsage !== aMaxUsage) {
                    return bMaxUsage - aMaxUsage;
                }
                return a.sort_order - b.sort_order;
            });
        } else {
            groups.sort((a, b) => {
                if (a.is_pinned !== b.is_pinned) {
                    return b.is_pinned - a.is_pinned;
                }
                return a.sort_order - b.sort_order;
            });
        }

        return groups;
    };

    const render = () => {
        document.querySelectorAll('[data-mode]').forEach(btn => btn.classList.toggle('active', btn.dataset.mode === state.settings.show_mode));
        showCustom.checked = Number(state.settings.show_custom_typed) === 1;

        const rows = filteredRows();
        const groups = groupRowsByCategory(rows);

        let html = '';
        let categoryIndex = 0;
        groups.forEach((group) => {
            const numAdvices = group.advices.length;
            const categoryTags = [];
            group.kinds.forEach(kind => {
                if (kind === 'system') categoryTags.push('<span class="phrase-tag system">System</span>');
                if (kind === 'custom_typed') categoryTags.push('<span class="phrase-tag custom">Custom Typed</span>');
                if (kind === 'added') categoryTags.push('<span class="phrase-tag added">Added</span>');
            });
            if (group.is_hidden) {
                categoryTags.push('<span class="phrase-tag hidden">Hidden</span>');
            }
            const categoryUsage = group.advices.reduce((sum, ad) => sum + Number(ad.usage_count), 0);

            group.advices.forEach((row, adviceIndex) => {
                const isFirst = (adviceIndex === 0);
                const rowClass = `phrase-row ${row.is_hidden ? 'hidden' : ''} ${row.is_pinned ? 'pinned' : ''}`;
                
                html += `<tr class="${rowClass}" data-category-key="${esc(group.key)}" data-advice-id="${row.id}" data-static-id="${row.static_id}">`;

                if (isFirst) {
                    const pinLabel = group.is_pinned ? 'Unpin' : 'Pin it';
                    const hideLabel = group.is_hidden ? 'Restore' : 'Remove';
                    html += `
                        <td class="phrase-handle category-handle" rowspan="${numAdvices}" title="Drag to reorder Category" draggable="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                            ${group.is_pinned ? '<img class="phrase-handle-pin" src="assets/images/pin.svg" alt="Pinned">' : ''}
                        </td>
                        <td rowspan="${numAdvices}" style="text-align:center;">
                            <div class="phrase-actions">
                                <button class="phrase-pin-btn btn-category-pin ${group.is_pinned ? 'active' : ''}" data-category-key="${esc(group.key)}" title="${group.is_pinned ? 'Unpin this category' : 'Pin this category to the top'}">
                                    ${pinLabel}
                                </button>
                                <button class="phrase-row-btn btn-category-edit" data-category-key="${esc(group.key)}">Edit</button>
                                <button class="phrase-row-btn toggle-hidden btn-category-hide ${group.is_hidden ? 'is-hidden' : ''}" data-category-key="${esc(group.key)}">${hideLabel}</button>
                                <button class="phrase-row-btn btn-category-reset" data-category-key="${esc(group.key)}">Reset</button>
                            </div>
                        </td>
                        <td class="phrase-sl" rowspan="${numAdvices}">${group.is_pinned ? '<div style="display:flex; align-items:center; justify-content:center; gap:0.25rem;"><span style="color:#b45309;">&#x25F3;</span><span>' + (categoryIndex + 1) + '</span></div>' : (categoryIndex + 1)}</td>
                        <td rowspan="${numAdvices}"><div class="phrase-tags">${categoryTags.join('')}</div></td>
                        <td rowspan="${numAdvices}"><div class="phrase-text" style="font-weight:700;">${esc(group.category_en || group.category_bn)}</div></td>
                    `;
                }

                html += `
                    <td><div class="phrase-text">${esc(row.value_bn)}</div></td>
                    <td><div class="phrase-text">${esc(row.value_en)}</div></td>
                `;

                if (isFirst) {
                    html += `<td class="phrase-usage" rowspan="${numAdvices}">${categoryUsage}</td>`;
                }

                html += `</tr>`;
            });
            categoryIndex++;
        });

        body.innerHTML = html;
        setupDragAndDrop();
    };

    const setupDragAndDrop = () => {
        body.addEventListener('dragstart', (e) => {
            const handle = e.target.closest('.category-handle');
            if (!handle) {
                e.preventDefault();
                return;
            }
            const tr = handle.closest('tr');
            draggedCategoryKey = tr.dataset.categoryKey;
            tr.classList.add('category-dragging');
        });

        body.addEventListener('dragover', (e) => {
            e.preventDefault();
            const tr = e.target.closest('tr');
            if (!tr) return;
            const targetCategoryKey = tr.dataset.categoryKey;
            if (!targetCategoryKey || targetCategoryKey === draggedCategoryKey) return;
            
            body.querySelectorAll('tr').forEach(row => {
                row.classList.toggle('drop-target', row.dataset.categoryKey === targetCategoryKey);
            });
        });

        body.addEventListener('dragleave', (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;
            const relatedTr = e.relatedTarget ? e.relatedTarget.closest('tr') : null;
            if (relatedTr && relatedTr.dataset.categoryKey === tr.dataset.categoryKey) {
                return;
            }
            body.querySelectorAll('.drop-target').forEach(row => row.classList.remove('drop-target'));
        });

        body.addEventListener('drop', async (e) => {
            e.preventDefault();
            body.querySelectorAll('.drop-target').forEach(row => row.classList.remove('drop-target'));
            const tr = e.target.closest('tr');
            if (!tr) return;
            const targetCategoryKey = tr.dataset.categoryKey;
            if (!targetCategoryKey || targetCategoryKey === draggedCategoryKey) return;

            const groups = groupRowsByCategory(state.rows);
            const draggedIndex = groups.findIndex(g => g.key === draggedCategoryKey);
            const targetIndex = groups.findIndex(g => g.key === targetCategoryKey);
            if (draggedIndex === -1 || targetIndex === -1) return;

            const [draggedGroup] = groups.splice(draggedIndex, 1);
            groups.splice(targetIndex, 0, draggedGroup);

            const newRows = [];
            let order = 1;
            groups.forEach(g => {
                g.advices.forEach(ad => {
                    ad.sort_order = order++;
                    newRows.push(ad);
                });
            });

            state.rows = newRows;
            render();
            await api({ action: 'save_all', rows: state.rows, settings: state.settings });
            setStatus('Category order saved');
        });

        body.addEventListener('dragend', () => {
            body.querySelectorAll('.category-dragging').forEach(row => row.classList.remove('category-dragging'));
            body.querySelectorAll('.drop-target').forEach(row => row.classList.remove('drop-target'));
            draggedCategoryKey = null;
        });
    };

    const syncModalAdvicesFromDom = () => {
        const rowsInDom = Array.from(document.querySelectorAll('.modal-advice-row'));
        rowsInDom.forEach((rowDom) => {
            const originalIndex = parseInt(rowDom.dataset.index);
            const advice = modalAdvices[originalIndex];
            if (!advice) return;
            advice.value_bn = rowDom.querySelector('.advice-bn-input').value.trim();
            advice.value_en = rowDom.querySelector('.advice-en-input').value.trim();
        });
    };

    let draggedAdviceIndex = null;
    const setupModalDragAndDrop = () => {
        const container = document.getElementById('modal-advices-container');
        if (!container) return;

        container.addEventListener('mousedown', (e) => {
            const handle = e.target.closest('.advice-row-drag-handle');
            const row = e.target.closest('.modal-advice-row');
            if (row) {
                row.draggable = !!handle;
            }
        });

        container.addEventListener('dragstart', (e) => {
            const row = e.target.closest('.modal-advice-row');
            if (!row || !row.draggable) {
                e.preventDefault();
                return;
            }
            syncModalAdvicesFromDom();
            draggedAdviceIndex = parseInt(row.dataset.index);
            row.style.opacity = '0.4';
        });

        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            const row = e.target.closest('.modal-advice-row');
            if (!row) return;
            const targetIndex = parseInt(row.dataset.index);
            if (targetIndex === draggedAdviceIndex) return;
            
            container.querySelectorAll('.modal-advice-row').forEach(r => r.style.borderTop = '');
            row.style.borderTop = '2px solid #2563eb';
        });

        container.addEventListener('dragleave', (e) => {
            const row = e.target.closest('.modal-advice-row');
            if (row) {
                row.style.borderTop = '';
            }
        });

        container.addEventListener('drop', (e) => {
            e.preventDefault();
            const row = e.target.closest('.modal-advice-row');
            if (!row) return;
            const targetIndex = parseInt(row.dataset.index);
            
            if (targetIndex !== draggedAdviceIndex) {
                const [draggedItem] = modalAdvices.splice(draggedAdviceIndex, 1);
                modalAdvices.splice(targetIndex, 0, draggedItem);
                renderModalAdvices();
            }
        });

        container.addEventListener('dragend', (e) => {
            container.querySelectorAll('.modal-advice-row').forEach(r => {
                r.style.borderTop = '';
                r.style.opacity = '1';
                r.draggable = false;
            });
            draggedAdviceIndex = null;
        });
    };

    const renderModalAdvices = () => {
        const container = document.getElementById('modal-advices-container');
        
        container.innerHTML = modalAdvices.map((advice, originalIndex) => {
            if (Number(advice.is_hidden) === 1) return '';
            
            return `
                <div class="modal-advice-row" data-index="${originalIndex}">
                    <!-- Drag Handle -->
                    <div class="advice-row-drag-handle" style="cursor: grab; display: flex; align-items: center; justify-content: center; color: #94a3b8; user-select: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="12" r="1"></circle><circle cx="9" cy="5" r="1"></circle><circle cx="9" cy="19" r="1"></circle>
                            <circle cx="15" cy="12" r="1"></circle><circle cx="15" cy="5" r="1"></circle><circle cx="15" cy="19" r="1"></circle>
                        </svg>
                    </div>
                    
                    <!-- Bangla Input -->
                    <div>
                        <textarea class="advice-bn-input modal-advice-textarea" placeholder="Advice Bangla" rows="1">${esc(advice.value_bn)}</textarea>
                    </div>

                    <!-- English Input -->
                    <div>
                        <textarea class="advice-en-input modal-advice-textarea" placeholder="Advice English" rows="1">${esc(advice.value_en)}</textarea>
                    </div>

                    <!-- Delete Button -->
                    <div style="display: flex; justify-content: center;">
                        <button type="button" class="phrase-modal-btn btn-advice-delete-row" style="width: 32px; height: 32px; min-height: unset; padding: 0; border-radius: 6px; display: flex; align-items: center; justify-content: center; border-width: 1px; border-style: solid; border-color: #fecaca; background: #fff1f2; color: #b91c1c; cursor: pointer;" title="Delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        setupModalDragAndDrop();
        
        setTimeout(() => {
            const container = document.getElementById('modal-advices-container');
            container.querySelectorAll('.modal-advice-textarea').forEach(ta => {
                ta.style.height = '';
                ta.style.height = Math.max(38, ta.scrollHeight) + 'px';
            });
        }, 10);
    };

    const openModal = (categoryKey = null) => {
        editCategoryKey = categoryKey;
        document.getElementById('phrase-modal-title').textContent = categoryKey ? 'Edit Category & Advices' : 'Add New Category';
        
        if (categoryKey) {
            const firstMatch = state.rows.find(row => (row.category_en || row.category_bn || 'Uncategorized').trim() === categoryKey);
            document.getElementById('modal-category-bn').value = firstMatch?.category_bn || '';
            document.getElementById('modal-category-en').value = firstMatch?.category_en || '';
            document.getElementById('modal-category-alias').value = firstMatch?.category_search_alias || '';
            
            modalAdvices = state.rows
                .filter(row => (row.category_en || row.category_bn || 'Uncategorized').trim() === categoryKey)
                .map(row => ({ ...row })); // Clone objects
        } else {
            document.getElementById('modal-category-bn').value = '';
            document.getElementById('modal-category-en').value = '';
            document.getElementById('modal-category-alias').value = '';
            modalAdvices = [{
                id: 0,
                static_id: 99,
                doctor_id: state.rows[0]?.doctor_id || 1,
                value_bn: '',
                value_en: '',
                is_pinned: 0,
                is_hidden: 0,
                usage_count: 0,
                kind: 'added',
                category_bn: '',
                category_en: '',
                category_search_alias: ''
            }];
        }
        
        renderModalAdvices();
        modal.hidden = false;
        
        // Trigger resize on textareas
        setTimeout(() => {
            modal.querySelectorAll('.modal-advice-textarea').forEach(ta => {
                ta.style.height = '';
                ta.style.height = Math.max(38, ta.scrollHeight) + 'px';
            });
        }, 10);
    };

    const closeModal = () => {
        modal.hidden = true;
        editCategoryKey = null;
        modalAdvices = [];
    };

    document.getElementById('phrase-add').addEventListener('click', () => openModal());
    document.getElementById('phrase-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });

    modal.addEventListener('input', (e) => {
        if (e.target.matches('.modal-advice-textarea')) {
            e.target.style.height = '';
            e.target.style.height = Math.max(38, e.target.scrollHeight) + 'px';
        }
    });

    // Handle clicks inside the modal advices container (Delete)
    document.getElementById('modal-advices-container').addEventListener('click', (event) => {
        const delBtn = event.target.closest('.btn-advice-delete-row');
        
        if (!delBtn) return;
        
        const rowDom = event.target.closest('.modal-advice-row');
        const index = parseInt(rowDom.dataset.index);
        
        if (delBtn) {
            syncModalAdvicesFromDom();
            const advice = modalAdvices[index];
            if (advice) {
                if (advice.kind === 'system') {
                    advice.is_hidden = 1;
                } else {
                    modalAdvices.splice(index, 1);
                }
                renderModalAdvices();
            }
        }
    });

    document.getElementById('modal-add-advice-btn').addEventListener('click', () => {
        syncModalAdvicesFromDom();
        const isPinned = modalAdvices.some(ad => Number(ad.is_pinned) === 1) ? 1 : 0;
        modalAdvices.push({
            id: 0,
            static_id: 99,
            doctor_id: state.rows[0]?.doctor_id || 1,
            value_bn: '',
            value_en: '',
            is_pinned: isPinned,
            is_hidden: 0,
            usage_count: 0,
            kind: 'added',
            category_bn: document.getElementById('modal-category-bn').value.trim(),
            category_en: document.getElementById('modal-category-en').value.trim()
        });
        renderModalAdvices();
    });

    document.getElementById('phrase-save').addEventListener('click', async () => {
        syncModalAdvicesFromDom();
        const categoryBn = document.getElementById('modal-category-bn').value.trim();
        const categoryEn = document.getElementById('modal-category-en').value.trim();
        const categoryAlias = document.getElementById('modal-category-alias').value.trim();
        
        if (!categoryBn && !categoryEn) {
            alert('Please specify Category Bangla or Category English.');
            return;
        }
        
        if (modalAdvices.length === 0) {
            alert('Please add at least one advice.');
            return;
        }
        
        modalAdvices.forEach(ad => {
            ad.category_bn = categoryBn;
            ad.category_en = categoryEn;
            ad.name = categoryEn;
            ad.category_search_alias = categoryAlias;
        });

        if (editCategoryKey) {
            let insertIndex = state.rows.findIndex(r => (r.category_en || r.category_bn || 'Uncategorized').trim() === editCategoryKey);
            if (insertIndex === -1) {
                insertIndex = state.rows.length;
            }
            
            // Remove old advices
            state.rows = state.rows.filter(r => (r.category_en || r.category_bn || 'Uncategorized').trim() !== editCategoryKey);
            
            // Insert updated ones
            state.rows.splice(insertIndex, 0, ...modalAdvices);
        } else {
            state.rows.push(...modalAdvices);
        }

        // Reassign sort orders
        state.rows.forEach((r, idx) => {
            r.sort_order = idx + 1;
        });

        setStatus('Saving...');
        try {
            await api({ action: 'save_all', rows: state.rows, settings: state.settings });
            closeModal();
            setStatus('Saved');
        } catch (e) {
            setStatus('Error: ' + e.message);
        }
    });

    body.addEventListener('click', async event => {
        const editButton = event.target.closest('.btn-category-edit');
        if (editButton) {
            const categoryKey = editButton.dataset.categoryKey;
            if (categoryKey) {
                openModal(categoryKey);
            }
            return;
        }

        const pinBtn = event.target.closest('.btn-category-pin');
        if (pinBtn) {
            const categoryKey = pinBtn.dataset.categoryKey;
            const categoryAdvices = state.rows.filter(r => (r.category_en || r.category_bn || 'Uncategorized').trim() === categoryKey);
            if (categoryAdvices.length > 0) {
                const anyUnpinned = categoryAdvices.some(r => Number(r.is_pinned) === 0);
                const targetPin = anyUnpinned ? 1 : 0;
                categoryAdvices.forEach(r => r.is_pinned = targetPin);
                
                setStatus('Saving...');
                await api({ action: 'save_all', rows: state.rows, settings: state.settings });
                setStatus('Saved');
            }
            return;
        }

        const hideBtn = event.target.closest('.btn-category-hide');
        if (hideBtn) {
            const categoryKey = hideBtn.dataset.categoryKey;
            const categoryAdvices = state.rows.filter(r => (r.category_en || r.category_bn || 'Uncategorized').trim() === categoryKey);
            if (categoryAdvices.length > 0) {
                const anyVisible = categoryAdvices.some(r => Number(r.is_hidden) === 0);
                const targetHidden = anyVisible ? 1 : 0;
                categoryAdvices.forEach(r => r.is_hidden = targetHidden);
                
                setStatus('Saving...');
                await api({ action: 'save_all', rows: state.rows, settings: state.settings });
                setStatus('Saved');
            }
            return;
        }

        const resetBtn = event.target.closest('.btn-category-reset');
        if (resetBtn) {
            if (!confirm('Reset this category? This cannot be reversed.')) return;
            const categoryKey = resetBtn.dataset.categoryKey;
            const categoryAdvices = state.rows.filter(r => (r.category_en || r.category_bn || 'Uncategorized').trim() === categoryKey);
            if (categoryAdvices.length > 0) {
                categoryAdvices.forEach(row => {
                    if (row.static_id > 0 && row.static_id !== 99 && state.default_rows) {
                        const defaultRow = state.default_rows.find(d => d.id === row.static_id);
                        if (defaultRow) {
                            row.value_bn = defaultRow.body || defaultRow.value_bn || '';
                            row.value_en = defaultRow.advice_en || defaultRow.value_en || '';
                            row.category_bn = defaultRow.category_bn || '';
                            row.category_en = defaultRow.category_en || defaultRow.name || '';
                            row.name = row.category_en;
                        }
                    }
                    row.is_pinned = 0;
                    row.is_hidden = 0;
                    row.usage_count = 0;
                    row.is_edited = 0;
                });
                
                setStatus('Resetting...');
                await api({ action: 'save_all', rows: state.rows, settings: state.settings });
                setStatus('Saved');
            }
            return;
        }
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
        setStatus('Saved');
    });

    search.addEventListener('input', render);
    field.addEventListener('change', render);
    
    render();
})();
</script>

<?php include 'footer.php'; ?>
