<div id="report-entry-wrapper" class="reports-wrapper reports-single-wrapper">
    <section class="reports-section reports-entry-section">
        <div class="reports-section-header">
            <div class="reports-section-title">
                <?= zrx_icon('file-text', 14) ?>
                <span>Report Entry (Manual)</span>
            </div>
        </div>

        <div class="reports-panel">
            <div class="pc-table-container reports-table-container">
                <table class="pc-table report-table reports-entry-table" id="reports-table" style="border-style: hidden; margin-bottom: 0;">
                    <colgroup>
                        <col style="width: 32px;">
                        <col style="width: 42px;">
                        <col style="width: 35%;">
                        <col style="width: 20%;">
                        <col style="width: 25%;">
                        <col style="width: 15%;">
                        <col style="width: 38px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="text-align: center; border-left: none;"></th>
                            <th style="text-align: center;">#</th>
                            <th>
                                <div class="pc-header-flex" style="width: 100%;">
                                    <span>Parameter Name</span>
                                </div>
                            </th>
                            <th style="text-align: center;">Date</th>
                            <th style="text-align: center;">Result / Value</th>
                            <th style="text-align: center;">Unit</th>
                            <th style="text-align: center; border-right: none;"></th>
                        </tr>
                    </thead>
                    <tbody id="reports-tbody">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                        <tr class="pc-row" draggable="true">
                            <td class="pc-action pc-del" style="border-left: none;"><button type="button" title="Remove Row">X</button></td>
                            <td class="pc-row-no"><?= $i ?></td>
                            <td><input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid"></td>
                            <td>
                                <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                            </td>
                            <td><input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result"></td>
                            <td><input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit"></td>
                            <td class="pc-action pc-drag" style="border-right: none;">
                                <button type="button" class="pc-row-move-btn" title="Move Row">
                                    <?= zrx_icon('move', 14) ?>
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="reports-table-footer">
                <button type="button" class="pc-add-row-btn reports-add-row-btn">Add More</button>
            </div>
        </div>
    </section>

    <template id="reports-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del" style="border-left: none;"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td><input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid"></td>
            <td>
                <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
            </td>
            <td><input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result"></td>
            <td><input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit"></td>
            <td class="pc-action pc-drag" style="border-right: none;">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<script>
(function() {
    const wrapper = document.getElementById('report-entry-wrapper');
    if (!wrapper) return;

    const tbody = document.getElementById('reports-tbody');
    const template = document.getElementById('reports-row-template');
    const addBtn = wrapper.querySelector('.reports-add-row-btn');

    let repDropdown = null;
    let repActiveIndex = -1;
    let repTimer = null;
    let dragSrcRow = null;

    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('tr.pc-row');
        rows.forEach((row, index) => {
            const noCell = row.querySelector('.pc-row-no');
            if (noCell) noCell.textContent = index + 1;
        });
    }

    function initDatePickers(container = tbody) {
        if (typeof flatpickr !== 'undefined') {
            container.querySelectorAll('.custom-date-picker').forEach(el => {
                if (!el._flatpickr) {
                    flatpickr(el, {
                        dateFormat: "d/m/Y",
                        allowInput: true
                    });
                }
            });
        }
    }

    initDatePickers();

    // --- Row Addition & Removal ---
    if (addBtn) {
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const tr = template.content.firstElementChild.cloneNode(true);
            tbody.appendChild(tr);
            updateRowNumbers();
            initDatePickers(tr);

            const nameInput = tr.querySelector('.rep-name-input');
            if (nameInput) nameInput.focus();
        });
    }

    wrapper.addEventListener('click', (e) => {
        const delBtn = e.target.closest('#reports-tbody .pc-del button');
        if (!delBtn) return;
        e.stopPropagation();
        const row = delBtn.closest('tr');
        if (row) {
            closeDropdown();
            row.remove();
            updateRowNumbers();
        }
    });

    // --- Autocomplete Dropdown Engine for Report Params & Units ---
    function closeDropdown() {
        if (repDropdown) {
            repDropdown.remove();
            repDropdown = null;
        }
        repActiveIndex = -1;
    }

    function positionDropdown(input) {
        if (!repDropdown) return;
        const rect = input.getBoundingClientRect();
        repDropdown.style.position = 'absolute';
        repDropdown.style.zIndex = '99999';
        repDropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        repDropdown.style.left = (rect.left + window.scrollX) + 'px';
        repDropdown.style.minWidth = Math.max(260, rect.width) + 'px';
    }

    function escapeHtml(val) {
        const div = document.createElement('div');
        div.textContent = val == null ? '' : String(val);
        return div.innerHTML;
    }

    function renderParamDropdown(input, items) {
        closeDropdown();
        if (!Array.isArray(items) || !items.length || items.error) return;

        const ul = document.createElement('ul');
        ul.className = 'zrx-dropdown rx-dropdown rep-param-dropdown show';

        items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'zrx-dropdown-item rx-dropdown-item';
            if (index === 0) li.classList.add('active');

            const hasFull = item.full_name && item.full_name.toLowerCase() !== item.short_name.toLowerCase();
            li.innerHTML = `
                <div class="rx-dropdown-main" style="display:flex; justify-content:space-between; align-items:center; width:100%; gap: 8px;">
                    <div style="min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <strong style="color: #0f172a; font-weight:600;">${escapeHtml(item.short_name)}</strong>
                        ${hasFull ? `<span style="color: #64748b; font-size: 0.76rem; margin-left: 6px;">(${escapeHtml(item.full_name)})</span>` : ''}
                    </div>
                    ${item.default_unit ? `<span style="color:#0369a1; font-size:0.72rem; font-weight:600; background:#e0f2fe; padding:1px 6px; border-radius:3px; white-space:nowrap; flex-shrink:0;">${escapeHtml(item.default_unit)}</span>` : ''}
                </div>
            `;

            li.addEventListener('mouseenter', () => {
                const all = ul.querySelectorAll('.zrx-dropdown-item');
                all.forEach(el => el.classList.remove('active'));
                li.classList.add('active');
                repActiveIndex = Array.from(all).indexOf(li);
            });

            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                input.value = item.short_name || '';
                input.dataset.paramId = item.id || '';
                input.dataset.fullName = item.full_name || '';

                const row = input.closest('tr');
                if (row) {
                    const unitInput = row.querySelector('.rep-unit-input');
                    if (unitInput) {
                        unitInput.value = item.default_unit || '';
                        unitInput.dataset.units = JSON.stringify(item.units || (item.default_unit ? [item.default_unit] : []));
                    }
                    const resultInput = row.querySelector('.rep-result-input');
                    if (resultInput) resultInput.focus();
                }
                closeDropdown();
            });

            ul.appendChild(li);
        });

        document.body.appendChild(ul);
        repDropdown = ul;
        repActiveIndex = 0;
        positionDropdown(input);
    }

    function renderUnitDropdown(input, units) {
        closeDropdown();
        if (!Array.isArray(units) || !units.length) return;

        const ul = document.createElement('ul');
        ul.className = 'zrx-dropdown rx-dropdown rep-unit-dropdown show';

        units.forEach((unit, index) => {
            const li = document.createElement('li');
            li.className = 'zrx-dropdown-item rx-dropdown-item';
            if (index === 0) li.classList.add('active');
            li.innerHTML = `<div class="rx-dropdown-main"><strong>${escapeHtml(unit)}</strong></div>`;

            li.addEventListener('mouseenter', () => {
                const all = ul.querySelectorAll('.zrx-dropdown-item');
                all.forEach(el => el.classList.remove('active'));
                li.classList.add('active');
                repActiveIndex = Array.from(all).indexOf(li);
            });

            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                input.value = unit;
                closeDropdown();
            });

            ul.appendChild(li);
        });

        document.body.appendChild(ul);
        repDropdown = ul;
        repActiveIndex = 0;
        positionDropdown(input);
    }

    // --- Input & Focus Listeners for Parameter Name ---
    wrapper.addEventListener('input', (e) => {
        if (!e.target.classList.contains('rep-name-input')) return;
        const input = e.target;
        const q = input.value.trim();
        clearTimeout(repTimer);

        if (q.length < 1) {
            closeDropdown();
            return;
        }

        repTimer = setTimeout(() => {
            fetch('api/search_ix_param.php?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(items => renderParamDropdown(input, items))
                .catch(closeDropdown);
        }, 120);
    });

    wrapper.addEventListener('focusin', (e) => {
        if (e.target.classList.contains('rep-name-input')) {
            const q = e.target.value.trim();
            if (q.length >= 1) {
                fetch('api/search_ix_param.php?q=' + encodeURIComponent(q))
                    .then(res => res.json())
                    .then(items => renderParamDropdown(e.target, items))
                    .catch(closeDropdown);
            }
        } else if (e.target.classList.contains('rep-unit-input')) {
            const input = e.target;
            let units = [];
            try {
                if (input.dataset.units) {
                    units = JSON.parse(input.dataset.units);
                }
            } catch (_) {}

            if (Array.isArray(units) && units.length > 1) {
                renderUnitDropdown(input, units);
            }
        }
    });

    // --- Keyboard Navigation ---
    wrapper.addEventListener('keydown', (e) => {
        const isTarget = e.target.classList.contains('rep-name-input') || e.target.classList.contains('rep-unit-input');
        if (!isTarget || !repDropdown) return;

        const items = Array.from(repDropdown.querySelectorAll('.zrx-dropdown-item'));
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            items[repActiveIndex]?.classList.remove('active');
            repActiveIndex = (repActiveIndex + 1) % items.length;
            items[repActiveIndex].classList.add('active');
            items[repActiveIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            items[repActiveIndex]?.classList.remove('active');
            repActiveIndex = (repActiveIndex - 1 + items.length) % items.length;
            items[repActiveIndex].classList.add('active');
            items[repActiveIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            if (repActiveIndex >= 0 && items[repActiveIndex]) {
                e.preventDefault();
                items[repActiveIndex].dispatchEvent(new MouseEvent('mousedown'));
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeDropdown();
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.rep-param-dropdown') && 
            !e.target.closest('.rep-unit-dropdown') && 
            !e.target.classList.contains('rep-name-input') && 
            !e.target.classList.contains('rep-unit-input')) {
            closeDropdown();
        }
    });

    window.addEventListener('resize', closeDropdown);

    // --- Drag and Drop Row Reordering ---
    tbody.addEventListener('dragstart', (e) => {
        const row = e.target.closest('tr.pc-row');
        if (!row) return;
        dragSrcRow = row;
        e.dataTransfer.effectAllowed = 'move';
        row.classList.add('dragging');
    });

    tbody.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const targetRow = e.target.closest('tr.pc-row');
        if (targetRow && targetRow !== dragSrcRow) {
            const rect = targetRow.getBoundingClientRect();
            const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
            tbody.insertBefore(dragSrcRow, next ? targetRow.nextSibling : targetRow);
        }
    });

    tbody.addEventListener('dragend', () => {
        if (dragSrcRow) {
            dragSrcRow.classList.remove('dragging');
            dragSrcRow = null;
        }
        updateRowNumbers();
    });
})();
</script>
