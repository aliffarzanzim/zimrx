// Diagnostic reports module managing test entries, parameter autocomplete, drag-and-drop rows, and file uploads.
(function() {
    const wrapper = document.getElementById('reports-wrapper');
    if (!wrapper) return;

    const tbody = document.getElementById('reports-tbody');
    const template = document.getElementById('reports-row-template');
    const addBtn = wrapper.querySelector('.reports-add-row-btn');

    let repDropdown = null;
    let repActiveIndex = -1;
    let repTimer = null;
    let dragSrcRow = null;

    // Manual row management
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
        if (delBtn) {
            e.stopPropagation();
            const row = delBtn.closest('tr');
            if (row) {
                closeDropdown();
                row.remove();
                updateRowNumbers();
            }
        }
    });

    // Autocomplete dropdown for parameters and units
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

    // Input and focus listeners for parameter name
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

    // Keyboard navigation
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

    // Drag and drop row reordering
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

    // Upload management
    const fileInput = document.getElementById('report-file-input');
    const uploadBtn = document.getElementById('report-upload-btn');
    const uploadTbody = document.getElementById('reports-upload-tbody');
    const uploadContainer = document.getElementById('reports-upload-table-container');
    const uploadTemplate = document.getElementById('reports-upload-template');

    function updateUploadRowNumbers() {
        const rows = uploadTbody.querySelectorAll('tr.pc-row');
        rows.forEach((row, index) => {
            const noCell = row.querySelector('.pc-row-no');
            if (noCell) noCell.textContent = index + 1;
        });

        if (rows.length > 0) {
            uploadContainer.style.display = 'block';
        } else {
            uploadContainer.style.display = 'none';
        }
    }

    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', async (e) => {
            if (!fileInput.files.length) return;

            const file = fileInput.files[0];
            const formData = new FormData();
            formData.append('file', file);

            const originalText = uploadBtn.innerHTML;
            uploadBtn.textContent = 'Uploading...';
            uploadBtn.disabled = true;

            try {
                const res = await fetch('api/upload_report.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.ok) {
                    const tr = uploadTemplate.content.firstElementChild.cloneNode(true);
                    const fileNameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                    tr.querySelector('.upload-name-input').value = fileNameWithoutExt;
                    tr.querySelector('.upload-filename-display').textContent = file.name;

                    const today = new Date();
                    const d = String(today.getDate()).padStart(2, '0');
                    const m = String(today.getMonth() + 1).padStart(2, '0');
                    const y = today.getFullYear();
                    const dateInput = tr.querySelector('.upload-date-input');
                    dateInput.value = `${d}/${m}/${y}`;

                    let cleanPath = (data.file_path || '').trim();
                    if (cleanPath.startsWith('userdata/uploads/reports/') || cleanPath.startsWith('uploads/reports/')) {
                        const fileName = cleanPath.split('/').pop();
                        cleanPath = 'api/view_report.php?file=' + encodeURIComponent(fileName);
                    } else if (!cleanPath.startsWith('api/view_report.php') && cleanPath !== '') {
                        const fileName = cleanPath.split('/').pop();
                        cleanPath = 'api/view_report.php?file=' + encodeURIComponent(fileName);
                    }
                    tr.querySelector('.upload-view-btn').href = cleanPath;
                    tr.querySelector('.upload-file-path').value = cleanPath;

                    if (typeof flatpickr !== 'undefined') {
                        flatpickr(dateInput, { dateFormat: "d/m/Y", allowInput: true });
                    }

                    uploadTbody.appendChild(tr);
                    updateUploadRowNumbers();
                } else {
                    alert('Upload failed: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Upload error: ' + err.message);
            } finally {
                uploadBtn.innerHTML = originalText;
                uploadBtn.disabled = false;
                fileInput.value = '';
            }
        });
    }

    if (uploadTbody) {
        uploadTbody.addEventListener('click', (e) => {
            if (e.target.classList.contains('upload-del-btn')) {
                e.preventDefault();
                if (confirm('Are you sure you want to remove this report?')) {
                    e.target.closest('tr').remove();
                    updateUploadRowNumbers();
                }
            }
        });
    }
})();
