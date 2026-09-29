// Clinical phrase template page managing bilingual presets, aliases, pinned status, and visibility.
(() => {
    const payloadEl = document.getElementById('phrasePayloadData');
    const initial = payloadEl ? JSON.parse(payloadEl.textContent || '{}') : {};
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
                <td class="phrase-handle pc-drag">${Number(row.is_pinned) === 1 ? '<img class="phrase-handle-pin" src="assets/images/pin.svg" alt="Pinned">' : ''}<button type="button" class="pc-row-move-btn zrx-drag-handle" style="width:100%; height:100%; border:none; background:transparent; padding:0; display:flex; align-items:center; justify-content:center; cursor:grab;" title="Move Row">${window.ZimRxIcon ? ZimRxIcon.render('move', 14) : ''}</button></td>
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
