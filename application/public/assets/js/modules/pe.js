// Physical Examination module handling BMI calculation, textarea auto-resize, and parameter settings.
(function () {
    function calcBmi() {
        var h = parseFloat(document.getElementById('pe-height-val')?.value);
        var w = parseFloat(document.getElementById('pe-weight-val')?.value);
        var bmiEl = document.getElementById('pe-bmi-val');
        if (!bmiEl) return;
        if (h > 0 && w > 0) {
            var hm = h / 100;
            bmiEl.value = (w / (hm * hm)).toFixed(1);
        } else {
            bmiEl.value = '';
        }
    }

    function autoResizePeInput(textarea) {
        if (!textarea || textarea.tagName !== 'TEXTAREA') return;
        textarea.style.transition = 'none';
        textarea.style.height = '0';
        var natural = Math.max(30, textarea.scrollHeight);
        textarea.style.height = natural + 'px';
        requestAnimationFrame(() => { textarea.style.transition = ''; });
    }

    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, function (m) {
            switch (m) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#39;';
            }
        });
    }

    function wirePeTableEvents() {
        document.querySelectorAll('#pe-table textarea').forEach(function (textarea) {
            autoResizePeInput(textarea);
        });

        document.getElementById('pe-height-val')?.removeEventListener('input', calcBmi);
        document.getElementById('pe-weight-val')?.removeEventListener('input', calcBmi);
        document.getElementById('pe-height-val')?.addEventListener('input', calcBmi);
        document.getElementById('pe-weight-val')?.addEventListener('input', calcBmi);
    }

    wirePeTableEvents();

    document.getElementById('pe-wrapper')?.addEventListener('input', function (e) {
        if (e.target.tagName === 'TEXTAREA') {
            autoResizePeInput(e.target);
        }
    });

    document.querySelector('#pe-wrapper .pe-add-row-btn')?.addEventListener('click', function (e) {
        e.stopPropagation();
        var template = document.getElementById('pe-row-template');
        var tbody = document.getElementById('pe-tbody');
        if (!template || !tbody) return;
        var row = template.content.firstElementChild.cloneNode(true);
        tbody.appendChild(row);

        var textarea = row.querySelector('.pe-input');
        if (textarea && textarea.tagName === 'TEXTAREA') {
            autoResizePeInput(textarea);
            textarea.focus();
        }
    });

    document.getElementById('pe-wrapper')?.addEventListener('click', function (e) {
        var delBtn = e.target.closest('.pc-del button');
        if (!delBtn) return;
        e.stopPropagation();
        var row = delBtn.closest('.pc-row');
        if (row) row.remove();
    });

    // Physical examination settings engine
    initPhysicalExamSettings();

    function initPhysicalExamSettings() {
        const settingsBtn = document.getElementById('pe-settings-btn');
        const modal = document.getElementById('pe-settings-modal');
        if (!settingsBtn || !modal) return;

        const searchInput = document.getElementById('pe-settings-search');
        const sysListContainer = document.getElementById('pe-settings-sys-list');
        const sysTotalBadge = document.getElementById('pe-sys-total-badge');
        const sectionTitle = document.getElementById('pe-table-section-title');
        const sectionCount = document.getElementById('pe-table-section-count');
        const tbody = document.getElementById('pe-settings-tbody');
        const saveBtn = document.getElementById('pe-settings-save-btn');
        const resetBtn = document.getElementById('pe-settings-reset-btn');
        const toggleAddBtn = document.getElementById('pe-toggle-add-btn');
        const addCard = document.getElementById('pe-add-card');
        const addCardClose = document.getElementById('pe-add-card-close');
        const addCustomBtn = document.getElementById('pe-add-custom-btn');
        const existingSysDatalist = document.getElementById('pe-existing-systems');

        let currentConfig = null;
        try {
            const initialElem = document.getElementById('zimrxInitialPeConfig');
            if (initialElem) {
                currentConfig = JSON.parse(initialElem.textContent || '{}');
            }
        } catch (e) {
            currentConfig = null;
        }

        if (!currentConfig || !Array.isArray(currentConfig.items)) {
            currentConfig = { systems: [], items: [], active_items: [] };
        }

        let activeSystem = 'All';
        let searchQuery = '';

        function getSystems() {
            const list = [];
            (currentConfig.items || []).forEach(item => {
                const sys = (item.system || '').trim();
                if (sys && !list.includes(sys)) list.push(sys);
            });
            return list;
        }

        function renderSystems() {
            if (!sysListContainer) return;
            const systems = getSystems();

            if (existingSysDatalist) {
                existingSysDatalist.innerHTML = systems.map(s => `<option value="${escapeHtml(s)}"></option>`).join('');
            }

            const totalCount = (currentConfig.items || []).length;
            const totalActive = (currentConfig.items || []).filter(i => i.is_active === 1).length;

            if (sysTotalBadge) {
                sysTotalBadge.textContent = `${totalActive}/${totalCount}`;
            }

            let html = `
                <button type="button" class="pe-settings-sys-item ${activeSystem === 'All' ? 'active' : ''}" data-sys="All">
                    <span>All Systems</span>
                    <span class="pe-settings-sys-count">${totalActive}/${totalCount}</span>
                </button>
            `;

            systems.forEach(sys => {
                const sysItems = (currentConfig.items || []).filter(i => i.system === sys);
                const activeCount = sysItems.filter(i => i.is_active === 1).length;
                html += `
                    <button type="button" class="pe-settings-sys-item ${activeSystem === sys ? 'active' : ''}" data-sys="${escapeHtml(sys)}">
                        <span>${escapeHtml(sys)}</span>
                        <span class="pe-settings-sys-count">${activeCount}/${sysItems.length}</span>
                    </button>
                `;
            });

            sysListContainer.innerHTML = html;
        }

        function renderTableRows() {
            if (!tbody) return;

            let filtered = (currentConfig.items || []).slice();

            if (activeSystem !== 'All') {
                filtered = filtered.filter(i => i.system === activeSystem);
            }

            if (searchQuery) {
                const q = searchQuery.toLowerCase();
                filtered = filtered.filter(i => 
                    (i.display_name && i.display_name.toLowerCase().includes(q)) ||
                    (i.full_name && i.full_name.toLowerCase().includes(q)) ||
                    (i.system && i.system.toLowerCase().includes(q)) ||
                    (i.category && i.category.toLowerCase().includes(q)) ||
                    (i.item_code && i.item_code.toLowerCase().includes(q)) ||
                    (i.finding_wordlists && i.finding_wordlists.toLowerCase().includes(q)) ||
                    (i.dropdown_options && i.dropdown_options.toLowerCase().includes(q))
                );
            }

            if (sectionTitle) {
                sectionTitle.textContent = activeSystem === 'All' ? 'All Systems & Parameters' : activeSystem;
            }
            if (sectionCount) {
                const activeInFilter = filtered.filter(i => i.is_active === 1).length;
                sectionCount.textContent = `(${activeInFilter} active of ${filtered.length} shown)`;
            }

            if (filtered.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="pe-cfg-empty-row">
                            No parameters found matching your criteria.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = filtered.map((item) => {
                const isChecked = item.is_active === 1 ? 'checked' : '';
                const code = escapeHtml(item.item_code);
                const name = escapeHtml(item.display_name);
                const sys = escapeHtml(item.system);
                const unit = escapeHtml(item.default_unit || '');
                const delim = escapeHtml(item.delimiter || '');
                const words = escapeHtml(item.finding_wordlists || item.dropdown_options || '');
                const isCustom = item.is_custom === 1;

                return `
                    <tr class="pe-settings-tr ${item.is_active === 1 ? '' : 'row-hidden'}" data-code="${code}" draggable="true">
                        <td class="zrx-ta-c">
                            <span class="pe-row-drag-handle" title="Drag to reorder">⋮⋮</span>
                        </td>
                        <td class="zrx-ta-c">
                            <input type="checkbox" class="pe-row-active-toggle" data-code="${code}" ${isChecked}>
                        </td>
                        <td>
                            <input type="text" class="pe-row-name-input" value="${name}" data-code="${code}">
                        </td>
                        <td>
                            <span class="pe-system-badge">${sys}</span>
                        </td>
                        <td>
                            <select class="pe-row-type-select" data-code="${code}">
                                <option value="dropdown+textbox" ${item.input_type === 'dropdown+textbox' ? 'selected' : ''}>Dropdown+Text</option>
                                <option value="textbox" ${item.input_type === 'textbox' ? 'selected' : ''}>Textbox</option>
                                <option value="double_textbox" ${item.input_type === 'double_textbox' ? 'selected' : ''}>Double Textbox</option>
                                <option value="multiple_textbox" ${item.input_type === 'multiple_textbox' ? 'selected' : ''}>Multiple Textbox</option>
                                <option value="dropdown" ${item.input_type === 'dropdown' ? 'selected' : ''}>Dropdown</option>
                            </select>
                        </td>
                        <td class="zrx-ta-c">
                            <input type="text" class="pe-row-delim-input" value="${delim}" data-code="${code}">
                        </td>
                        <td class="zrx-ta-c">
                            <input type="text" class="pe-row-unit-input" value="${unit}" data-code="${code}">
                        </td>
                        <td>
                            <input type="text" class="pe-row-words-input" value="${words}" data-code="${code}" placeholder="Wordlists or presets">
                        </td>
                        <td class="zrx-ta-c">
                            ${isCustom ? `<button type="button" class="pe-settings-del-btn" data-code="${code}" title="Delete Custom Item">&times;</button>` : ''}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Open Modal
        settingsBtn.addEventListener('click', () => {
            modal.hidden = false;
            modal.removeAttribute('hidden');
            modal.style.display = 'flex';
            renderSystems();
            renderTableRows();
        });

        // Close Modal
        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('hidden', '');
            modal.style.display = 'none';
        }

        modal.querySelectorAll('[data-pe-settings-close]').forEach(el => {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        // System filter click
        if (sysListContainer) {
            sysListContainer.addEventListener('click', (e) => {
                const btn = e.target.closest('.pe-settings-sys-item');
                if (!btn) return;
                activeSystem = btn.dataset.sys || 'All';
                renderSystems();
                renderTableRows();
            });
        }

        // Search input
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                searchQuery = (e.target.value || '').trim();
                renderTableRows();
            });
        }

        // Active checkbox toggle & input updates
        if (tbody) {
            tbody.addEventListener('change', (e) => {
                const toggle = e.target.closest('.pe-row-active-toggle');
                if (toggle) {
                    const code = toggle.dataset.code;
                    const item = (currentConfig.items || []).find(i => i.item_code === code);
                    if (item) {
                        item.is_active = toggle.checked ? 1 : 0;
                        const tr = toggle.closest('tr');
                        if (tr) {
                            tr.classList.toggle('row-hidden', !toggle.checked);
                        }
                        renderSystems();
                        if (sectionCount) {
                            const totalShown = (currentConfig.items || []).filter(i => activeSystem === 'All' || i.system === activeSystem).length;
                            const activeShown = (currentConfig.items || []).filter(i => (activeSystem === 'All' || i.system === activeSystem) && i.is_active === 1).length;
                            sectionCount.textContent = `(${activeShown} active of ${totalShown} shown)`;
                        }
                    }
                    return;
                }

                const nameInput = e.target.closest('.pe-row-name-input');
                if (nameInput) {
                    const item = (currentConfig.items || []).find(i => i.item_code === nameInput.dataset.code);
                    if (item) item.display_name = nameInput.value.trim();
                    return;
                }

                const typeSelect = e.target.closest('.pe-row-type-select');
                if (typeSelect) {
                    const item = (currentConfig.items || []).find(i => i.item_code === typeSelect.dataset.code);
                    if (item) item.input_type = typeSelect.value;
                    return;
                }

                const delimInput = e.target.closest('.pe-row-delim-input');
                if (delimInput) {
                    const item = (currentConfig.items || []).find(i => i.item_code === delimInput.dataset.code);
                    if (item) item.delimiter = delimInput.value.trim();
                    return;
                }

                const unitInput = e.target.closest('.pe-row-unit-input');
                if (unitInput) {
                    const item = (currentConfig.items || []).find(i => i.item_code === unitInput.dataset.code);
                    if (item) item.default_unit = unitInput.value.trim();
                    return;
                }

                const wordsInput = e.target.closest('.pe-row-words-input');
                if (wordsInput) {
                    const item = (currentConfig.items || []).find(i => i.item_code === wordsInput.dataset.code);
                    if (item) item.finding_wordlists = wordsInput.value.trim();
                    return;
                }
            });

            // Delete Custom Item
            tbody.addEventListener('click', (e) => {
                const delBtn = e.target.closest('.pe-settings-del-btn');
                if (delBtn) {
                    const code = delBtn.dataset.code;
                    const idx = (currentConfig.items || []).findIndex(i => i.item_code === code);
                    if (idx !== -1) {
                        currentConfig.items.splice(idx, 1);
                        renderSystems();
                        renderTableRows();
                    }
                }
            });
        }

        // Drag & Drop reordering in Settings table
        let dragSource = null;
        if (tbody) {
            tbody.addEventListener('dragstart', (e) => {
                const tr = e.target.closest('.pe-settings-tr');
                if (!tr) return;
                dragSource = tr;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', tr.dataset.code);
                tr.style.opacity = '0.4';
            });

            tbody.addEventListener('dragover', (e) => {
                e.preventDefault();
                const tr = e.target.closest('.pe-settings-tr');
                if (!tr || tr === dragSource) return;
                const rect = tr.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                if (e.clientY < mid) {
                    tbody.insertBefore(dragSource, tr);
                } else {
                    tbody.insertBefore(dragSource, tr.nextSibling);
                }
            });

            tbody.addEventListener('dragend', (e) => {
                const tr = e.target.closest('.pe-settings-tr');
                if (tr) tr.style.opacity = '';
                dragSource = null;

                const newOrderCodes = Array.from(tbody.querySelectorAll('.pe-settings-tr')).map(r => r.dataset.code);
                const orderMap = {};
                newOrderCodes.forEach((code, idx) => { orderMap[code] = (idx + 1) * 10; });

                (currentConfig.items || []).forEach(item => {
                    if (orderMap[item.item_code] !== undefined) {
                        item.sort_order = orderMap[item.item_code];
                    }
                });

                currentConfig.items.sort((a, b) => (a.sort_order || 10) - (b.sort_order || 10));
            });
        }

        // Toggle Add Form
        if (toggleAddBtn && addCard) {
            toggleAddBtn.addEventListener('click', () => {
                const isHidden = addCard.style.display === 'none';
                addCard.style.display = isHidden ? 'block' : 'none';
            });
        }
        if (addCardClose && addCard) {
            addCardClose.addEventListener('click', () => {
                addCard.style.display = 'none';
            });
        }

        // Add Custom Parameter
        if (addCustomBtn) {
            addCustomBtn.addEventListener('click', () => {
                const sysInput = document.getElementById('pe-custom-system');
                const catInput = document.getElementById('pe-custom-category');
                const labelInput = document.getElementById('pe-custom-label');
                const typeInput = document.getElementById('pe-custom-input-type');
                const delimInput = document.getElementById('pe-custom-delimiter');
                const unitInput = document.getElementById('pe-custom-unit');
                const normalInput = document.getElementById('pe-custom-normal');
                const wordsInput = document.getElementById('pe-custom-wordlists');

                const label = (labelInput?.value || '').trim();
                if (!label) {
                    alert('Please enter a display label for the parameter.');
                    labelInput?.focus();
                    return;
                }

                let code = label.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                const exists = (currentConfig.items || []).some(i => i.item_code === code);
                if (exists) {
                    code = code + '_' + Math.floor(Math.random() * 1000);
                }

                const newItem = {
                    id: 0,
                    static_id: 0,
                    item_code: code,
                    system: (sysInput?.value || '').trim() || 'General',
                    category: (catInput?.value || '').trim() || 'General',
                    display_name: label,
                    full_name: label,
                    input_type: typeInput?.value || 'dropdown+textbox',
                    delimiter: (delimInput?.value || '').trim() || '/',
                    default_unit: (unitInput?.value || '').trim(),
                    normal_value: (normalInput?.value || '').trim(),
                    dropdown_options: '',
                    finding_wordlists: (wordsInput?.value || '').trim(),
                    is_active: 1,
                    sort_order: ((currentConfig.items || []).length + 1) * 10,
                    is_custom: 1,
                    is_default_active: 0
                };

                currentConfig.items.push(newItem);

                if (labelInput) labelInput.value = '';
                if (unitInput) unitInput.value = '';
                if (wordsInput) wordsInput.value = '';
                if (addCard) addCard.style.display = 'none';

                renderSystems();
                renderTableRows();
            });
        }

        // Save settings to backend and rebuild table
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                const origText = saveBtn.textContent;
                saveBtn.textContent = 'Saving...';

                try {
                    const resp = await fetch('api/physical_examination_settings.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'save_config',
                            items: currentConfig.items
                        })
                    });
                    const result = await resp.json();
                    if (result.success && result.data) {
                        currentConfig = result.data;
                        rebuildPeTable(currentConfig.active_items || []);
                        closeModal();
                    } else {
                        alert('Error saving physical examination settings: ' + (result.error || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Network error while saving settings: ' + err.message);
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = origText;
                }
            });
        }

        // Reset settings to factory defaults
        if (resetBtn) {
            resetBtn.addEventListener('click', async () => {
                if (!confirm('Are you sure you want to reset Physical Examination to factory defaults? Your custom visibility and parameters will be restored to defaults.')) {
                    return;
                }

                resetBtn.disabled = true;
                resetBtn.textContent = 'Resetting...';

                try {
                    const resp = await fetch('api/physical_examination_settings.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'reset_default' })
                    });
                    const result = await resp.json();
                    if (result.success && result.data) {
                        currentConfig = result.data;
                        activeSystem = 'All';
                        searchQuery = '';
                        renderSystems();
                        renderTableRows();
                        rebuildPeTable(currentConfig.active_items || []);
                        closeModal();
                    } else {
                        alert('Error resetting settings: ' + (result.error || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Network error while resetting settings: ' + err.message);
                } finally {
                    resetBtn.disabled = false;
                    resetBtn.textContent = 'Reset to Defaults';
                }
            });
        }

        // Rebuild prescription table while preserving entered values
        function rebuildPeTable(activeItems) {
            const tableBody = document.getElementById('pe-tbody');
            if (!tableBody) return;

            const preserved = {};
            tableBody.querySelectorAll('tr.pc-row').forEach(row => {
                const code = row.dataset.itemCode || row.querySelector('textarea')?.value?.trim();
                if (!code) return;
                const unit = row.querySelector('td:nth-child(4) input')?.value || '';

                const part1 = row.querySelector('.pe-val-part[data-part="1"]')?.value || '';
                const part2 = row.querySelector('.pe-val-part[data-part="2"]')?.value || '';
                const part3 = row.querySelector('.pe-val-part[data-part="3"]')?.value || '';
                const singleVal = row.querySelector('td:nth-child(3) input:not(.pe-val-part)')?.value || '';

                preserved[code] = {
                    part1: part1,
                    part2: part2,
                    part3: part3,
                    singleVal: singleVal,
                    unit: unit
                };
            });

            let html = '';
            activeItems.forEach(item => {
                const code = escapeHtml(item.item_code);
                const name = escapeHtml(item.display_name);
                const unit = escapeHtml(item.default_unit || '');
                const inputType = item.input_type;
                const delimiter = escapeHtml(item.delimiter || '/');
                const normalVal = escapeHtml(item.normal_value || '');
                const prev = preserved[item.item_code] || preserved[item.display_name] || null;

                const prevUnit = prev?.unit !== undefined ? escapeHtml(prev.unit) : unit;

                html += `<tr class="pc-row" draggable="true" data-item-code="${code}" data-input-type="${escapeHtml(inputType)}" data-delimiter="${delimiter}">`;
                html += `<td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>`;
                html += `<td><textarea class="pc-input pe-input" autocomplete="off" rows="1">${name}</textarea></td>`;

                if (inputType === 'double_textbox') {
                    const p1 = prev?.part1 !== undefined ? escapeHtml(prev.part1) : '';
                    const p2 = prev?.part2 !== undefined ? escapeHtml(prev.part2) : '';
                    html += `
                        <td class="zrx-va-mid">
                            <div class="pe-parts-wrap-2">
                                <input type="text" class="pe-input pe-val-part pe-val-part-2" data-part="1" value="${p1}" autocomplete="off">
                                <span class="pe-part-separator">${delimiter}</span>
                                <input type="text" class="pe-input pe-val-part pe-val-part-2" data-part="2" value="${p2}" autocomplete="off">
                            </div>
                        </td>
                    `;
                } else if (inputType === 'multiple_textbox') {
                    const p1 = prev?.part1 !== undefined ? escapeHtml(prev.part1) : '';
                    const p2 = prev?.part2 !== undefined ? escapeHtml(prev.part2) : '';
                    const p3 = prev?.part3 !== undefined ? escapeHtml(prev.part3) : '';
                    html += `
                        <td class="zrx-va-mid">
                            <div class="pe-parts-wrap-3">
                                <input type="text" class="pe-input pe-val-part pe-val-part-3" data-part="1" value="${p1}" autocomplete="off">
                                <span class="pe-part-separator-sm">${delimiter}</span>
                                <input type="text" class="pe-input pe-val-part pe-val-part-3" data-part="2" value="${p2}" autocomplete="off">
                                <span class="pe-part-separator-sm">${delimiter}</span>
                                <input type="text" class="pe-input pe-val-part pe-val-part-3" data-part="3" value="${p3}" autocomplete="off">
                            </div>
                        </td>
                    `;
                } else if (item.item_code === 'height') {
                    const val = prev?.singleVal !== undefined ? escapeHtml(prev.singleVal) : '';
                    html += `<td><input type="text" id="pe-height-val" class="pc-input pe-input" value="${val}" class="zrx-ta-c" autocomplete="off"></td>`;
                } else if (item.item_code === 'weight') {
                    const val = prev?.singleVal !== undefined ? escapeHtml(prev.singleVal) : '';
                    html += `<td><input type="text" id="pe-weight-val" class="pc-input pe-input" value="${val}" class="zrx-ta-c" autocomplete="off"></td>`;
                } else if (item.item_code === 'bmi') {
                    const val = prev?.singleVal !== undefined ? escapeHtml(prev.singleVal) : '';
                    html += `<td><input type="text" id="pe-bmi-val" class="pc-input pe-input" value="${val}" class="zrx-ta-c" readonly autocomplete="off"></td>`;
                } else if (item.dropdown_options || item.finding_wordlists) {
                    const val = prev?.singleVal !== undefined ? escapeHtml(prev.singleVal) : '';
                    const ph = normalVal ? `Normal: ${normalVal}` : '';
                    const opts = (item.dropdown_options || '').split('|').map(s => s.trim()).filter(Boolean);
                    const words = (item.finding_wordlists || '').split(',').map(s => s.trim()).filter(Boolean);
                    const allOpts = Array.from(new Set([...opts, ...words]));

                    html += `
                        <td>
                            <input type="text" list="pe-dl-${code}" class="pc-input pe-input" value="${val}" class="zrx-ta-c" autocomplete="off" placeholder="${ph}">
                            <datalist id="pe-dl-${code}">
                                ${allOpts.map(o => `<option value="${escapeHtml(o)}"></option>`).join('')}
                            </datalist>
                        </td>
                    `;
                } else {
                    const val = prev?.singleVal !== undefined ? escapeHtml(prev.singleVal) : '';
                    html += `<td><input type="text" class="pc-input pe-input" value="${val}" class="zrx-ta-c" autocomplete="off"></td>`;
                }

                html += `<td><input type="text" class="pc-input pe-input" class="zrx-ta-c" value="${prevUnit}" autocomplete="off"></td>`;
                html += `
                    <td class="pc-action pc-drag">
                        <button type="button" class="pc-row-move-btn" title="Move Row">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                        </button>
                    </td>
                `;
                html += `</tr>`;
            });

            tableBody.innerHTML = html;
            wirePeTableEvents();
            calcBmi();
        }
    }
})();
