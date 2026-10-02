// Pharmaceutical manufacturer preferences page managing custom rankings, hidden brands, and directories.
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

    // Custom ranked list rendering
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

    // Directory list rendering
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

    // Reordering handlers
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

    // Directory actions
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

    // Filter chip handlers
    document.querySelectorAll('.mpref-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mpref-chip').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            renderDirectoryList();
        });
    });

    // Search filtering
    searchInput.addEventListener('input', () => {
        renderDirectoryList();
    });

    // Save preferences
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

    // Reset to defaults
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

    // Drag and drop reordering
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
