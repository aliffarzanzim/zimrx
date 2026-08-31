<?php
$page_title = "Manufacturer Preferences - ZimRx";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$doctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="assets/css/manufacturer_preferences.css?v=<?= filemtime(__DIR__ . '/assets/css/manufacturer_preferences.css') ?>">

<div class="mpref-container">
    <!-- Hero Header -->
    <div class="mpref-header">
        <div class="mpref-title-wrap">
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" color="#2563eb"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                <span>Manufacturer Preferences &amp; Priority Ranking</span>
            </h1>
            <p>Customize which pharmaceutical companies appear first during drug autocomplete in the prescription editor. Prioritize your trusted brands, reorder via drag-and-drop, or hide companies you do not wish to see.</p>
        </div>

        <div class="mpref-header-actions">
            <button type="button" class="btn-mpref-danger" id="btn-reset-defaults" title="Reset all rankings back to national defaults">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                <span>Reset Defaults</span>
            </button>
            <button type="button" class="btn-mpref-primary" id="btn-save-all">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                <span>Save Changes</span>
            </button>
        </div>
    </div>

    <!-- KPI Summary Badges Bar -->
    <div class="mpref-summary-bar">
        <div class="mpref-summary-pill">
            <span class="pill-dot dot-blue"></span>
            <span>My Prioritized Companies:</span>
            <strong id="count-custom" style="color: #2563eb;">0</strong>
        </div>
        <div class="mpref-summary-pill">
            <span class="pill-dot dot-amber"></span>
            <span>Hidden Companies:</span>
            <strong id="count-hidden" style="color: #d97706;">0</strong>
        </div>
        <div class="mpref-summary-pill">
            <span class="pill-dot dot-slate"></span>
            <span>Total Available in Drug DB:</span>
            <strong id="count-total">0</strong>
        </div>
    </div>

    <!-- Main Dual Panels Grid -->
    <div class="mpref-grid">
        <!-- Left Panel: My Custom Ranking (Drag and Drop) -->
        <div class="mpref-panel">
            <div class="mpref-panel-header">
                <h2>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" color="#2563eb"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    <span>My Ranked Priority (Shows First in Search)</span>
                </h2>
                <span id="rank-header-sub">Drag rows or use arrows to reorder</span>
            </div>

            <div class="mpref-list" id="custom-rank-list">
                <!-- Populated dynamically -->
            </div>
        </div>

        <!-- Right Panel: All Manufacturers Directory -->
        <div class="mpref-panel">
            <div class="mpref-panel-header">
                <h2>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" color="#64748b"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Manufacturer Directory</span>
                </h2>
                <span id="directory-count">676 Companies</span>
            </div>

            <div class="mpref-search-toolbar">
                <div class="mpref-search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" color="#94a3b8"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="dir-search-input" placeholder="Search company name (e.g. Square, Renata, Incepta)..." autocomplete="off">
                </div>

                <div class="mpref-chips">
                    <button type="button" class="mpref-chip active" data-filter="all">All</button>
                    <button type="button" class="mpref-chip" data-filter="available">Available</button>
                    <button type="button" class="mpref-chip" data-filter="hidden">Hidden</button>
                </div>
            </div>

            <div class="mpref-list" id="directory-list">
                <!-- Populated dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Floating Toast Feedback -->
<div class="mpref-toast" id="mpref-toast">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
    <span id="toast-message">Changes saved successfully!</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let customRanked = [];
    let allManufacturers = [];
    let hiddenSet = new Set();
    let isDirty = false;

    const customListEl = document.getElementById('custom-rank-list');
    const dirListEl = document.getElementById('directory-list');
    const searchInput = document.getElementById('dir-search-input');
    const countCustomEl = document.getElementById('count-custom');
    const countHiddenEl = document.getElementById('count-hidden');
    const countTotalEl = document.getElementById('count-total');
    const dirCountEl = document.getElementById('directory-count');
    const saveBtn = document.getElementById('btn-save-all');
    const resetBtn = document.getElementById('btn-reset-defaults');
    const toast = document.getElementById('mpref-toast');

    let currentFilter = 'all';

    const showToast = (msg) => {
        const msgEl = document.getElementById('toast-message');
        if (msgEl) msgEl.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    };

    // Load initial data
    const loadData = async () => {
        try {
            customListEl.innerHTML = '<div class="mpref-empty">Loading manufacturer preferences...</div>';
            dirListEl.innerHTML = '<div class="mpref-empty">Loading directory...</div>';

            const resp = await fetch('api/manufacturer_preference_api.php?action=get_list');
            const data = await resp.json();

            if (!data.success) {
                alert('Error loading data: ' + (data.error || 'Unknown error'));
                return;
            }

            customRanked = data.custom_ranked || [];
            allManufacturers = data.all || [];
            hiddenSet = new Set((data.hidden || []).map(h => String(h.id)));

            updateCounts();
            renderCustomList();
            renderDirectoryList();
        } catch (err) {
            alert('Failed to connect to server: ' + err.message);
        }
    };

    const updateCounts = () => {
        countCustomEl.textContent = customRanked.length;
        countHiddenEl.textContent = hiddenSet.size;
        countTotalEl.textContent = allManufacturers.length;
        dirCountEl.textContent = allManufacturers.length + ' Companies';
    };

    // Render Custom Ranked List (Left Panel)
    const renderCustomList = () => {
        if (customRanked.length === 0) {
            customListEl.innerHTML = `
                <div class="mpref-empty">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    <div style="font-weight: 600; color: #475569;">No custom company priorities set yet</div>
                    <div style="font-size: 0.78rem; max-width: 320px; line-height: 1.4;">ZimRx is currently using the national default drug rankings. Click <strong>+ Prioritize</strong> on any company on the right to add them here!</div>
                </div>
            `;
            return;
        }

        let html = '';
        customRanked.forEach((item, index) => {
            const rank = index + 1;
            let rankClass = 'rank-custom';
            if (rank === 1) rankClass = 'rank-top1';
            else if (rank === 2) rankClass = 'rank-top2';
            else if (rank === 3) rankClass = 'rank-top3';

            const isHidden = hiddenSet.has(String(item.id));

            html += `
                <div class="mpref-item" draggable="true" data-index="${index}" data-id="${item.id}">
                    <div class="mpref-item-left">
                        <div class="mpref-drag-handle" title="Drag to reorder">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
                        </div>
                        <div class="mpref-rank-badge ${rankClass}">#${rank}</div>
                        <div class="mpref-item-details">
                            <div class="mpref-item-name" title="${item.name}">${item.name}</div>
                            <div class="mpref-item-meta">
                                <span class="brand-count-pill">${item.brand_count} brands</span>
                                <span style="font-size: 0.7rem; color: #94a3b8;">Default: #${item.default_preference || '-'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mpref-item-actions">
                        ${index > 0 ? `
                            <button type="button" class="btn-item-action btn-move-top" title="Move to Top" data-index="${index}">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline><line x1="5" y1="3" x2="19" y2="3"></line></svg>
                            </button>
                            <button type="button" class="btn-item-action btn-move-up" title="Move Up" data-index="${index}">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"></polyline></svg>
                            </button>
                        ` : ''}
                        ${index < customRanked.length - 1 ? `
                            <button type="button" class="btn-item-action btn-move-down" title="Move Down" data-index="${index}">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                        ` : ''}
                        <button type="button" class="btn-item-action btn-remove-custom" title="Remove from custom list (reverts to default ranking)" data-index="${index}">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                </div>
            `;
        });

        customListEl.innerHTML = html;
        bindCustomDragEvents();
    };

    // Render Directory List (Right Panel)
    const renderDirectoryList = () => {
        const query = searchInput.value.toLowerCase().trim();
        const customIds = new Set(customRanked.map(c => String(c.id)));

        const filtered = allManufacturers.filter(item => {
            const mid = String(item.id);
            const isHidden = hiddenSet.has(mid);
            const isCustom = customIds.has(mid);

            if (currentFilter === 'available' && (isCustom || isHidden)) return false;
            if (currentFilter === 'hidden' && !isHidden) return false;

            if (query !== '') {
                const name = (item.name || '').toLowerCase();
                const short = (item.short_name || '').toLowerCase();
                if (!name.includes(query) && !short.includes(query)) return false;
            }
            return true;
        });

        if (filtered.length === 0) {
            dirListEl.innerHTML = `
                <div class="mpref-empty">
                    <div>No companies match the current search filter.</div>
                </div>
            `;
            return;
        }

        let html = '';
        filtered.slice(0, 100).forEach(item => {
            const mid = String(item.id);
            const isHidden = hiddenSet.has(mid);
            const customIdx = customRanked.findIndex(c => String(c.id) === mid);
            const isCustom = customIdx !== -1;

            let badgeHtml = '';
            if (isHidden) {
                badgeHtml = '<span class="mpref-rank-badge rank-hidden" style="font-size: 0.68rem; min-width: auto; padding: 2px 6px;">Hidden</span>';
            } else if (isCustom) {
                badgeHtml = `<span class="mpref-rank-badge rank-custom">#${customIdx + 1}</span>`;
            } else {
                badgeHtml = `<span class="mpref-rank-badge rank-default">Def #${item.default_preference || '-'}</span>`;
            }

            html += `
                <div class="mpref-item">
                    <div class="mpref-item-left">
                        ${badgeHtml}
                        <div class="mpref-item-details">
                            <div class="mpref-item-name" style="${isHidden ? 'text-decoration: line-through; color: #94a3b8;' : ''}" title="${item.name}">${item.name}</div>
                            <div class="mpref-item-meta">
                                <span class="brand-count-pill">${item.brand_count} brands</span>
                            </div>
                        </div>
                    </div>
                    <div class="mpref-item-actions">
                        ${!isCustom && !isHidden ? `
                            <button type="button" class="btn-item-add btn-add-priority" data-id="${item.id}" data-name="${item.name}" title="Add to top priority list">
                                + Prioritize
                            </button>
                        ` : ''}
                        <button type="button" class="btn-item-action ${isHidden ? 'active-hidden' : ''} btn-toggle-hide" data-id="${item.id}" data-name="${item.name}" data-hidden="${isHidden ? 1 : 0}" title="${isHidden ? 'Unhide company' : 'Hide company from prescription autocomplete'}">
                            ${isHidden ? `
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            ` : `
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            `}
                        </button>
                    </div>
                </div>
            `;
        });

        if (filtered.length > 100) {
            html += `<div style="text-align: center; padding: 0.75rem; font-size: 0.78rem; color: #94a3b8;">Showing top 100 of ${filtered.length} matches. Type more characters to narrow down.</div>`;
        }

        dirListEl.innerHTML = html;
    };

    // Reorder Handlers
    customListEl.addEventListener('click', (e) => {
        const topBtn = e.target.closest('.btn-move-top');
        if (topBtn) {
            const idx = parseInt(topBtn.dataset.index);
            if (idx > 0) {
                const item = customRanked.splice(idx, 1)[0];
                customRanked.unshift(item);
                isDirty = true;
                renderCustomList();
                renderDirectoryList();
            }
            return;
        }

        const upBtn = e.target.closest('.btn-move-up');
        if (upBtn) {
            const idx = parseInt(upBtn.dataset.index);
            if (idx > 0) {
                const item = customRanked.splice(idx, 1)[0];
                customRanked.splice(idx - 1, 0, item);
                isDirty = true;
                renderCustomList();
                renderDirectoryList();
            }
            return;
        }

        const downBtn = e.target.closest('.btn-move-down');
        if (downBtn) {
            const idx = parseInt(downBtn.dataset.index);
            if (idx < customRanked.length - 1) {
                const item = customRanked.splice(idx, 1)[0];
                customRanked.splice(idx + 1, 0, item);
                isDirty = true;
                renderCustomList();
                renderDirectoryList();
            }
            return;
        }

        const remBtn = e.target.closest('.btn-remove-custom');
        if (remBtn) {
            const idx = parseInt(remBtn.dataset.index);
            customRanked.splice(idx, 1);
            isDirty = true;
            updateCounts();
            renderCustomList();
            renderDirectoryList();
            return;
        }
    });

    // Directory Action Handlers
    dirListEl.addEventListener('click', async (e) => {
        const addBtn = e.target.closest('.btn-add-priority');
        if (addBtn) {
            const id = addBtn.dataset.id;
            const name = addBtn.dataset.name;
            const existing = allManufacturers.find(m => String(m.id) === String(id));
            if (existing && !customRanked.some(c => String(c.id) === String(id))) {
                customRanked.push(existing);
                isDirty = true;
                updateCounts();
                renderCustomList();
                renderDirectoryList();
            }
            return;
        }

        const hideBtn = e.target.closest('.btn-toggle-hide');
        if (hideBtn) {
            const id = String(hideBtn.dataset.id);
            const name = hideBtn.dataset.name;
            const currHidden = parseInt(hideBtn.dataset.hidden) === 1;
            const newHidden = currHidden ? 0 : 1;

            try {
                const fd = new FormData();
                fd.append('action', 'toggle_hide');
                fd.append('manufacturer_id', id);
                fd.append('manufacturer_name', name);
                fd.append('is_hidden', newHidden);

                const resp = await fetch('api/manufacturer_preference_api.php', { method: 'POST', body: fd });
                const res = await resp.json();
                if (res.success) {
                    if (newHidden) {
                        hiddenSet.add(id);
                        // Also remove from custom ranked if present
                        customRanked = customRanked.filter(c => String(c.id) !== id);
                    } else {
                        hiddenSet.delete(id);
                    }
                    updateCounts();
                    renderCustomList();
                    renderDirectoryList();
                    showToast(newHidden ? `Hidden: ${name}` : `Restored: ${name}`);
                }
            } catch (err) {
                alert('Error toggling hide status: ' + err.message);
            }
            return;
        }
    });

    // Filter Chips
    document.querySelectorAll('.mpref-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mpref-chip').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            renderDirectoryList();
        });
    });

    // Live Search input
    searchInput.addEventListener('input', () => {
        renderDirectoryList();
    });

    // Save All Changes
    saveBtn.addEventListener('click', async () => {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const items = customRanked.map((c, i) => ({
                id: c.id,
                name: c.name,
                sort_order: i + 1
            }));

            const resp = await fetch('api/manufacturer_preference_api.php?action=save_order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items })
            });
            const res = await resp.json();

            if (res.success) {
                isDirty = false;
                showToast(`Saved ${res.saved_count || customRanked.length} company priority rankings!`);
            } else {
                alert('Error saving preferences: ' + (res.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error saving preferences: ' + err.message);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = `
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                <span>Save Changes</span>
            `;
        }
    });

    // Reset Defaults
    resetBtn.addEventListener('click', async () => {
        if (!confirm('Are you sure you want to reset all manufacturer priorities back to the national default ranking?')) {
            return;
        }

        try {
            const fd = new FormData();
            fd.append('action', 'reset_defaults');
            const resp = await fetch('api/manufacturer_preference_api.php', { method: 'POST', body: fd });
            const res = await resp.json();

            if (res.success) {
                customRanked = [];
                hiddenSet.clear();
                updateCounts();
                renderCustomList();
                renderDirectoryList();
                showToast('Reset all rankings to national defaults!');
            }
        } catch (err) {
            alert('Error resetting defaults: ' + err.message);
        }
    });

    // Drag and Drop implementation
    let draggedIndex = null;

    function bindCustomDragEvents() {
        const items = customListEl.querySelectorAll('.mpref-item');
        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                draggedIndex = parseInt(item.dataset.index);
                item.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('is-dragging');
                items.forEach(it => it.classList.remove('drop-target'));
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                items.forEach(it => it.classList.remove('drop-target'));
                item.classList.add('drop-target');
            });

            item.addEventListener('drop', (e) => {
                e.preventDefault();
                item.classList.remove('drop-target');
                const targetIndex = parseInt(item.dataset.index);
                if (draggedIndex !== null && draggedIndex !== targetIndex) {
                    const movedItem = customRanked.splice(draggedIndex, 1)[0];
                    customRanked.splice(targetIndex, 0, movedItem);
                    isDirty = true;
                    renderCustomList();
                    renderDirectoryList();
                }
            });
        });
    }

    loadData();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
