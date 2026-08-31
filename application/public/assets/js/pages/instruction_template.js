// Drug instruction templates management page handling phrases, dosage form chips, pinned ordering, and drag-and-drop.
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
    const payloadEl = document.getElementById('instructionTemplateInitialPayload');
    const initialPayload = payloadEl ? JSON.parse(payloadEl.textContent || '{}') : {};
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

    // Rebuild full row list with sequential sort orders after drag-and-drop, preserving pinned anchors.
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
