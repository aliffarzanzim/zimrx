function initRxAutocomplete() {
    let activeDropdown = null;
    let rxFocus = -1;
    let rxTimeout = null;
    let rxLookupGeneration = 0;
    let draggedRxRow = null;

    const getRxInfoBar = () => document.getElementById('rx-info-bar');
    const getRxTopBar = () => document.getElementById('rx-top-bar');
    const getRxTbody = () => document.getElementById('rx-tbody');
    const RX_SETTINGS_KEY = 'zimrx_rx_settings';
    const defaultWarningTypes = {
        immediate: true,
        antibiotic: true,
        highAlert: true,
        renal: true,
        tapering: true,
        pregnancy: false,
        lactation: false,
        hepatic: false,
        paediatric: false
    };
    const defaultRxSettings = {
        prefixMode: 'full',
        genericNameFormat: 'plain',
        autoRowSize: false,
        showWarnings: true,
        warningTypes: { ...defaultWarningTypes },
        showInteractions: true
    };
    let rxSettings = { ...defaultRxSettings };
    let currentRxInfoItem = null;
    const RX_SEARCH_CACHE_TTL = 5 * 60 * 1000;
    const rxSearchCache = new Map();
    let rxDrugModalUrl = '';
    let rxDrugModalLoadedUrl = '';
    let rxDrugModalLoading = false;
    let rxDrugModalPendingOpen = '';

    const fetchRxSearchJson = (cacheKey, url) => {
        const now = Date.now();
        const cached = rxSearchCache.get(cacheKey);
        if (cached && (now - cached.ts) < RX_SEARCH_CACHE_TTL) {
            return Promise.resolve(cached.data);
        }

        return fetch(url)
            .then((res) => res.json())
            .then((data) => {
                rxSearchCache.set(cacheKey, { ts: Date.now(), data });
                return data;
            })
            .catch(() => []);
    };

    const prewarmRxSearch = () => {
        fetchRxSearchJson('drug-search:default', 'api/search_drug.php?q=').catch(() => []);
    };

    const getRxDrugModalElements = () => ({
        modal: document.getElementById('rx-drug-modal'),
        frame: document.getElementById('rx-drug-modal-frame'),
        title: document.getElementById('rx-drug-modal-title'),
        loading: document.getElementById('rx-drug-modal-loading')
    });

    const normalizeRxDrugModalUrl = (url) => {
        try {
            return new URL(String(url || ''), window.location.href).toString();
        } catch (err) {
            return String(url || '');
        }
    };

    const getRxDrugModalUrl = (item) => {
        if (!item || !item.id) return '';
        return normalizeRxDrugModalUrl(`drug_db.php?mode=brand&brand_id=${encodeURIComponent(item.id)}&rx_popup=1`);
    };

    const setRxDrugModalLoadingState = (isLoading) => {
        const { frame, loading } = getRxDrugModalElements();
        if (loading) {
            loading.style.display = isLoading ? 'flex' : 'none';
        }
        if (frame) {
            frame.style.visibility = isLoading ? 'hidden' : 'visible';
        }
        rxDrugModalLoading = isLoading;
    };

    const primeRxDrugModal = (item) => {
        const url = getRxDrugModalUrl(item);
        const { frame } = getRxDrugModalElements();
        if (!url || !frame) return;
        if (rxDrugModalLoadedUrl === url || rxDrugModalUrl === url) return;

        rxDrugModalUrl = url;
        setRxDrugModalLoadingState(true);
        frame.src = url;
    };

    const loadRxSettings = () => {
        try {
            const saved = JSON.parse(localStorage.getItem(RX_SETTINGS_KEY) || '{}');
            rxSettings = { ...defaultRxSettings, ...saved };
        } catch (err) {
            rxSettings = { ...defaultRxSettings };
        }

        if (!['full', 'short'].includes(rxSettings.prefixMode)) {
            rxSettings.prefixMode = 'full';
        }
        if (!['plain', 'prescribe', 'labelled'].includes(rxSettings.genericNameFormat)) {
            rxSettings.genericNameFormat = 'plain';
        }
        rxSettings.autoRowSize = Boolean(rxSettings.autoRowSize);
        rxSettings.showWarnings = rxSettings.showWarnings !== false;
        rxSettings.showInteractions = rxSettings.showInteractions !== false;
        rxSettings.warningTypes = {
            ...defaultWarningTypes,
            ...(rxSettings.warningTypes && typeof rxSettings.warningTypes === 'object' ? rxSettings.warningTypes : {})
        };
    };

    const saveRxSettings = () => {
        try {
            localStorage.setItem(RX_SETTINGS_KEY, JSON.stringify(rxSettings));
        } catch (err) {}
    };

    const getSelectedDrugDisplayName = (item) => {
        if (!item) return '';
        if (rxSettings.prefixMode === 'short') {
            return item.pres_new_upper || item.brand_name || item.full_form_brand_name || '';
        }
        return item.full_form_brand_name || item.pres_new_upper || item.brand_name || '';
    };

    const getGenericDisplayName = (item) => {
        if (!item) return '';
        const fmt = rxSettings.genericNameFormat || 'plain';
        if (fmt === 'prescribe') {
            return item.prescribe_generic_short || item.prescribe_generic_full || item.generic_name || item.generic || '';
        }
        if (fmt === 'labelled') {
            return item.labelled_generic_short || item.labelled_generic_full || item.generic_name || item.generic || '';
        }
        return item.generic_name || item.generic || '';
    };

    const autoSizeRxField = (field) => {
        if (!field || !field.matches('.rx-input')) return;

        if (!rxSettings.autoRowSize) {
            field.style.height = '';
            return;
        }

        field.style.height = 'auto';
        field.style.height = `${Math.max(36, field.scrollHeight)}px`;
    };

    const autoSizeRxRow = (row) => {
        if (!row) return;
        row.querySelectorAll('.rx-input').forEach(autoSizeRxField);
    };

    const autoSizeAllRxRows = () => {
        const tbody = getRxTbody();
        if (!tbody) return;
        tbody.querySelectorAll('tr').forEach(autoSizeRxRow);
    };

    const resetRxAutoSize = () => {
        document.querySelectorAll('.rx-input').forEach((field) => {
            field.style.height = '';
        });
    };

    const syncRxWrapMode = () => {
        document.querySelectorAll('.rx-input').forEach((field) => {
            field.setAttribute('wrap', rxSettings.autoRowSize ? 'soft' : 'off');
        });
    };

    const setRxFieldValue = (field, value) => {
        if (!field) return;
        field.value = value || '';
        autoSizeRxField(field);
    };

    const setRxLanguageValue = (field, value, bengali = '', english = '') => {
        setRxFieldValue(field, value);
        if (!field) return;
        field.dataset.printBengali = bengali || value || '';
        field.dataset.printEnglish = english || value || '';
    };

    const syncRxSettingsControls = () => {
        document.querySelectorAll('input[name="rx-prefix-mode"]').forEach((input) => {
            input.checked = input.value === rxSettings.prefixMode;
        });

        document.querySelectorAll('input[name="rx-generic-name-format"]').forEach((input) => {
            input.checked = input.value === rxSettings.genericNameFormat;
        });

        const autoRowSize = document.getElementById('rx-auto-row-size');
        if (autoRowSize) autoRowSize.checked = rxSettings.autoRowSize;

        const showWarnings = document.getElementById('rx-show-warnings');
        if (showWarnings) showWarnings.checked = rxSettings.showWarnings;

        const showInteractions = document.getElementById('rx-show-interactions');
        if (showInteractions) showInteractions.checked = rxSettings.showInteractions;

        document.querySelectorAll('[data-rx-warning-type]').forEach((input) => {
            const type = input.dataset.rxWarningType;
            input.checked = rxSettings.warningTypes[type] !== false;
            input.disabled = !rxSettings.showWarnings;
        });
    };

    const refreshSelectedBrandDisplays = () => {
        const tbody = getRxTbody();
        if (!tbody) return;

        tbody.querySelectorAll('tr').forEach((row) => {
            const selectedDrug = getRowSelectedDrug(row);
            const brandInput = row.querySelector('.rx-brand-input');
            if (selectedDrug && brandInput) {
                setRxFieldValue(brandInput, getSelectedDrugDisplayName(selectedDrug));
            }
        });
    };

    const applyRxSettingsToUi = (refreshBrands = false) => {
        document.body.classList.toggle('rx-auto-row-size-enabled', rxSettings.autoRowSize);
        document.body.classList.toggle('rx-warnings-disabled', !rxSettings.showWarnings);
        syncRxSettingsControls();
        syncRxWrapMode();

        if (refreshBrands) {
            refreshSelectedBrandDisplays();
        }

        if (rxSettings.autoRowSize) {
            autoSizeAllRxRows();
        } else {
            resetRxAutoSize();
        }

        if (currentRxInfoItem) showRxInfoBar(currentRxInfoItem);
        window.dispatchEvent(new CustomEvent('zimrx-rx-settings-changed', { detail: { ...rxSettings } }));
    };

    const openRxSettingsModal = () => {
        const modal = document.getElementById('rx-settings-modal');
        if (!modal) return;

        syncRxSettingsControls();
        modal.hidden = false;
    };

    const closeRxSettingsModal = () => {
        const modal = document.getElementById('rx-settings-modal');
        if (modal) modal.hidden = true;
    };

    const isRxSettingsModalOpen = () => {
        const modal = document.getElementById('rx-settings-modal');
        return Boolean(modal && !modal.hidden);
    };

    const formatRxPrice = (price) => {
        const numericPrice = parseFloat(price);
        return isNaN(numericPrice) ? (price || 'N/A') : `${numericPrice.toFixed(2)} TK.`;
    };

    const escapeRxHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));

    const truthyRxFlag = (value) => value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true';
    const falseRxFlag = (value) => value === false || value === 0 || value === '0' || String(value).toLowerCase() === 'false';
    const isWarningEnabled = (type) => rxSettings.showWarnings && rxSettings.warningTypes[type] !== false;

    const compactRxText = (value, maxLength = 96) => {
        const text = String(value || '').replace(/\s+/g, ' ').trim();
        if (text.length <= maxLength) return text;
        return `${text.slice(0, maxLength - 1).trim()}...`;
    };

    const parseImmediateWarnings = (value) => {
        const raw = String(value || '').trim();
        if (!raw) return [];

        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                return parsed.map((item) => {
                    if (!item || typeof item !== 'object') return null;
                    const label = item.alert_type || item.severity || 'Immediate warning';
                    const message = item.message || item.trigger_condition || '';
                    return {
                        label: compactRxText(label, 38),
                        title: [label, message].filter(Boolean).join(': ')
                    };
                }).filter(Boolean);
            }
        } catch (err) {}

        return [{ label: compactRxText(raw, 52), title: raw }];
    };

    const rxChipHtml = (label, type = 'info', title = '') => {
        return `<span class="rx-info-chip ${type}" title="${escapeRxHtml(title || label)}">${escapeRxHtml(label)}</span>`;
    };

    const resetRxSettingsToDefaults = () => {
        rxSettings = {
            ...defaultRxSettings,
            warningTypes: { ...defaultWarningTypes }
        };
        saveRxSettings();
        applyRxSettingsToUi(true);
    };

    const createRxRow = (rowNumber) => {
        const row = document.createElement('tr');
        row.className = 'pc-row rx-row';
        row.draggable = true;
        const moveIcon = typeof ZimRxIcon !== 'undefined'
            ? ZimRxIcon.render('move', 14)
            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>';

        row.innerHTML = `
            <td class="rx-action rx-drag pc-action pc-drag" style="padding: 0 !important;">
                <button type="button" class="pc-row-move-btn rx-row-move-btn zrx-drag-handle" title="Move Row">${moveIcon}</button>
            </td>
            <td class="rx-action rx-del pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
            <td class="rx-action rx-no pc-row-no">${rowNumber}</td>
            <td>
                <textarea class="rx-input rx-brand-input" autocomplete="off" rows="1"></textarea>
                <input type="hidden" name="brand_id[]" class="brand_id">
            </td>
            <td><textarea class="rx-input rx-generic-input" autocomplete="off" rows="1"></textarea></td>
            <td><textarea class="rx-input rx-dose-input" autocomplete="off" rows="1"></textarea></td>
            <td><textarea class="rx-input rx-instruction-input" autocomplete="off" rows="1"></textarea></td>
            <td><textarea class="rx-input rx-duration-input" autocomplete="off" rows="1"></textarea></td>
        `;
        row.querySelectorAll('.rx-input').forEach((field) => {
            field.setAttribute('wrap', rxSettings.autoRowSize ? 'soft' : 'off');
        });
        autoSizeRxRow(row);
        return row;
    };

    const renumberRxRows = () => {
        const tbody = getRxTbody();
        if (!tbody) return;
        Array.from(tbody.querySelectorAll('tr')).forEach((row, index) => {
            const noCell = row.querySelector('.rx-no');
            if (noCell) noCell.textContent = String(index + 1);
        });
    };

    const setRowSelectedDrug = (row, item) => {
        if (!row || !item) return;
        row.dataset.selectedDrug = JSON.stringify(item);
    };

    const getRowSelectedDrug = (row) => {
        if (!row || !row.dataset.selectedDrug) return null;
        try {
            return JSON.parse(row.dataset.selectedDrug);
        } catch (err) {
            return null;
        }
    };

    const clearRegimenFields = (row) => {
        if (!row) return;
        ['.rx-dose-input', '.rx-instruction-input', '.rx-duration-input'].forEach((selector) => {
            const input = row.querySelector(selector);
            setRxFieldValue(input, '');
        });
    };

    const clearSelectedDrugState = (row, options = {}) => {
        if (!row) return;

        const {
            clearBrandInput = false,
            clearGenericInput = false,
            clearRegimen = false,
            clearInfoBar = false
        } = options;

        const brandInput = row.querySelector('.rx-brand-input');
        const genericInput = row.querySelector('.rx-generic-input');
        const brandIdInput = row.querySelector('.brand_id');

        if (clearBrandInput) {
            setRxFieldValue(brandInput, '');
        }
        if (clearGenericInput) {
            setRxFieldValue(genericInput, '');
        }
        if (brandInput) {
            delete brandInput.dataset.selectedGeneric;
            delete brandInput.dataset.selectedGenericId;
            delete brandInput.dataset.selectedForm;
            delete brandInput.dataset.selectedStrength;
            delete brandInput.dataset.systemBrandId;
            delete brandInput.dataset.sourceBrandId;
            delete brandInput.dataset.suppressAlternates;
        }
        if (brandIdInput) {
            brandIdInput.value = '';
        }
        delete row.dataset.selectedDrug;

        if (clearRegimen) {
            clearRegimenFields(row);
        }
        if (clearInfoBar) {
            hideRxInfoBar();
        }
    };

    const getDrugContextParams = (row) => {
        const selectedDrug = getRowSelectedDrug(row) || {};
        const brandIdInput = row ? row.querySelector('.brand_id') : null;
        const params = new URLSearchParams();

        params.set('brand_id', brandIdInput ? brandIdInput.value.trim() : (selectedDrug.id || selectedDrug.catalog_id || ''));
        params.set('system_brand_id', selectedDrug.system_brand_id || selectedDrug.brand_id || '');
        params.set('generic_id', selectedDrug.generic_id || '');
        params.set('form', selectedDrug.form_new || selectedDrug.form || '');
        params.set('strength', selectedDrug.strength || '');
        params.set('generic', selectedDrug.generic || selectedDrug.generic_name || '');
        params.set('brand', selectedDrug.full_form_brand_name || selectedDrug.pres_new_upper || selectedDrug.brand_name || '');

        return params;
    };

    const fillRegimenFromSelectedDrug = (row, item) => {
        if (!row || !item || !item.id) return;

        const params = getDrugContextParams(row);
        fetch(`api/rx_regimen.php?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                const currentDrug = getRowSelectedDrug(row);
                if (!currentDrug || String(currentDrug.id || '') !== String(item.id || '')) return;
                if (!data || data.error || !data.found || !data.regimen) return;

                const regimenRows = Array.isArray(data.regimen_rows) && data.regimen_rows.length
                    ? data.regimen_rows
                    : [data.regimen];
                applyRegimenRows(row, regimenRows);
            })
            .catch(() => {});
    };

    function applyRegimenFields(row, regimen) {
        if (!row || !regimen) return;
        setRxLanguageValue(row.querySelector('.rx-dose-input'), regimen.dose || '', regimen.dose_bn, regimen.dose_en);
        setRxLanguageValue(row.querySelector('.rx-instruction-input'), regimen.instruction || '', regimen.instruction_bn, regimen.instruction_en);
        setRxLanguageValue(row.querySelector('.rx-duration-input'), regimen.duration || '', regimen.duration_bn, regimen.duration_en);
    }

    function clearContinuationDrugIdentity(row) {
        clearSelectedDrugState(row, {
            clearBrandInput: true,
            clearGenericInput: true
        });
    }

    function nextRegimenRow(afterRow) {
        const tbody = getRxTbody();
        if (!tbody || !afterRow) return null;

        const nextRow = afterRow.nextElementSibling;
        if (nextRow && isRxRowEmpty(nextRow)) {
            return nextRow;
        }

        const row = createRxRow(tbody.querySelectorAll('tr').length + 1);
        tbody.insertBefore(row, afterRow.nextSibling);
        return row;
    }

    function applyRegimenRows(row, regimenRows) {
        if (!row || !Array.isArray(regimenRows) || !regimenRows.length) return;

        let targetRow = row;
        regimenRows.forEach((regimen, index) => {
            if (index > 0) {
                targetRow = nextRegimenRow(targetRow);
                clearContinuationDrugIdentity(targetRow);
            }
            applyRegimenFields(targetRow, regimen);
        });
        renumberRxRows();
    }

    const isRxRowEmpty = (row) => {
        if (!row) return false;
        return ['.rx-brand-input', '.rx-generic-input', '.rx-dose-input', '.rx-instruction-input', '.rx-duration-input'].every((selector) => {
            const input = row.querySelector(selector);
            return !input || input.value.trim() === '';
        });
    };

    const findAvailableRxRow = () => {
        const tbody = getRxTbody();
        if (!tbody) return null;

        const emptyRow = Array.from(tbody.querySelectorAll('tr')).find(isRxRowEmpty);
        if (emptyRow) return emptyRow;

        const row = createRxRow(tbody.querySelectorAll('tr').length + 1);
        tbody.appendChild(row);
        renumberRxRows();
        return row;
    };

    const applyTemplateRowToRxRow = (row, templateRow) => {
        if (!row || !templateRow) return;

        const brandInput = row.querySelector('.rx-brand-input');
        const genericInput = row.querySelector('.rx-generic-input');
        const doseInput = row.querySelector('.rx-dose-input');
        const instructionInput = row.querySelector('.rx-instruction-input');
        const durationInput = row.querySelector('.rx-duration-input');
        const brandIdInput = row.querySelector('.brand_id');

        if (brandInput) {
            setRxFieldValue(brandInput, templateRow.brand_name || '');
            brandInput.dataset.selectedGeneric = templateRow.generic_name || '';
            brandInput.dataset.selectedGenericId = templateRow.generic_id || '';
            brandInput.dataset.selectedForm = templateRow.form || '';
            brandInput.dataset.selectedStrength = templateRow.strength || '';
            brandInput.dataset.sourceBrandId = templateRow.brand_id || '';
        }
        setRxFieldValue(genericInput, templateRow.generic_name || '');
        setRxLanguageValue(doseInput, templateRow.dose || '', templateRow.dose_bn, templateRow.dose_en);
        setRxLanguageValue(instructionInput, templateRow.instruction || '', templateRow.instruction_bn, templateRow.instruction_en);
        setRxLanguageValue(durationInput, templateRow.duration || '', templateRow.duration_bn, templateRow.duration_en);
        if (brandIdInput) brandIdInput.value = templateRow.catalog_id || '';

        if (templateRow.catalog_id || templateRow.brand_id) {
            setRowSelectedDrug(row, {
                id: templateRow.catalog_id || '',
                system_brand_id: templateRow.brand_id || '',
                generic_id: templateRow.generic_id || '',
                pres_new_upper: templateRow.brand_name || '',
                full_form_brand_name: templateRow.brand_name || '',
                generic: templateRow.generic_name || '',
                strength: templateRow.strength || '',
                form: templateRow.form || ''
            });
        } else {
            delete row.dataset.selectedDrug;
        }
    };

    const applyUserTemplate = (templateId) => {
        if (!templateId) return;

        fetch(`api/rx_user_templates.php?type=drug&id=${encodeURIComponent(templateId)}`)
            .then(res => res.json())
            .then(data => {
                closeRxDropdown();
                if (!data || data.error || !Array.isArray(data.rows)) return;

                data.rows.forEach((templateRow) => {
                    const row = findAvailableRxRow();
                    applyTemplateRowToRxRow(row, templateRow);
                });
                renumberRxRows();
            })
            .catch(() => {});
    };

    const showRxInfoBar = (item) => {
        const infoBar = getRxInfoBar();
        const topBar = getRxTopBar();
        if (!infoBar || !item) return;

        currentRxInfoItem = item;
        const generic = item.generic || item.generic_name || 'N/A';
        const warnings = [];
        if (isWarningEnabled('antibiotic') && truthyRxFlag(item.is_antibiotic)) warnings.push(rxChipHtml('Antibiotic', 'info'));
        if (isWarningEnabled('highAlert') && truthyRxFlag(item.is_high_alert_medicine)) warnings.push(rxChipHtml('High alert medicine', 'danger'));
        if (isWarningEnabled('renal') && truthyRxFlag(item.require_renal_adjustment)) {
            warnings.push(rxChipHtml('Renal dose caution', 'warning', 'Dose adjustment may be needed in renal impairment.'));
        }
        if (isWarningEnabled('tapering') && truthyRxFlag(item.requires_tapering)) warnings.push(rxChipHtml('Tapering needed', 'warning'));
        if (isWarningEnabled('pregnancy') && falseRxFlag(item.is_safe_in_pregnancy)) {
            warnings.push(rxChipHtml('Pregnancy warning', 'warning', 'Review safety before use in pregnancy.'));
        }
        if (isWarningEnabled('lactation') && falseRxFlag(item.is_safe_in_lactation)) {
            warnings.push(rxChipHtml('Lactation warning', 'warning', 'Review safety before use during lactation.'));
        }
        if (isWarningEnabled('hepatic') && falseRxFlag(item.is_safe_in_hepatic_impairment)) {
            warnings.push(rxChipHtml('Hepatic caution', 'warning', 'Review use in hepatic impairment.'));
        }
        if (isWarningEnabled('paediatric') && falseRxFlag(item.is_safe_in_paediatrics)) {
            warnings.push(rxChipHtml('Paediatric caution', 'warning', 'Review paediatric safety before use.'));
        }
        if (isWarningEnabled('immediate')) parseImmediateWarnings(item.immediate_warning).slice(0, 2).forEach((warning) => {
            warnings.push(rxChipHtml(warning.label, 'danger', warning.title));
        });

        infoBar.innerHTML = `
            <div class="rx-info-main">
                <span class="rx-info-piece generic">${escapeRxHtml(generic)}</span>
                <span class="rx-info-separator">|</span>
                <span class="rx-info-piece price">${escapeRxHtml(formatRxPrice(item.price))}</span>
                <span class="rx-info-separator">|</span>
                <span class="rx-info-piece pregnancy">Cat: ${escapeRxHtml(item.preg_cat || 'N/A')}</span>
                <span class="rx-info-separator">|</span>
                <span class="rx-info-piece manufacturer">${escapeRxHtml(item.manufacturer || 'N/A')}</span>
                <span class="rx-info-separator">|</span>
                <span class="rx-info-piece class">${escapeRxHtml(item.cls || 'N/A')}</span>
            </div>
            ${rxSettings.showWarnings ? `<div class="rx-info-alerts">${warnings.join('')}</div>` : ''}
        `;
        infoBar.dataset.brandId = item.id || '';
        infoBar.dataset.brandName = getSelectedDrugDisplayName(item) || item.generic || 'Drug View';
        infoBar.style.display = 'block';
        infoBar.style.cursor = 'pointer';
        infoBar.title = 'Click to open drug view';
        if (topBar) topBar.style.borderRadius = '0';
        primeRxDrugModal(item);
    };

    const hideRxInfoBar = () => {
        const infoBar = getRxInfoBar();
        const topBar = getRxTopBar();
        currentRxInfoItem = null;
        if (infoBar) {
            infoBar.style.display = 'block';
            infoBar.style.cursor = 'default';
            infoBar.title = 'Drug details will appear here';
            infoBar.innerHTML = '<div class="rx-info-empty">Drug details and selected warnings will appear here</div>';
            infoBar.dataset.brandId = '';
            infoBar.dataset.brandName = '';
        }
        if (topBar) {
            topBar.style.borderRadius = '0';
        }
    };

    const applySelectedDrug = (input, item, focusNext = false) => {
        if (!input || !item) return;

        const row = input.closest('tr');
        const previousDrug = row ? getRowSelectedDrug(row) : null;
        const brandChanged = previousDrug && String(previousDrug.id || '') !== String(item.id || '');

        setRxFieldValue(input, getSelectedDrugDisplayName(item));
        input.dataset.selectedGeneric = item.generic || '';
        input.dataset.selectedGenericId = item.generic_id || '';
        input.dataset.selectedForm = item.form || '';
        input.dataset.selectedStrength = item.strength || '';
        input.dataset.systemBrandId = item.system_brand_id || '';
        input.dataset.suppressAlternates = '1';

        if (row) {
            setRowSelectedDrug(row, item);
            if (brandChanged) {
                clearRegimenFields(row);
            }
        }

        const genInp = row ? row.querySelector('.rx-generic-input') : null;
        setRxFieldValue(genInp, getGenericDisplayName(item));

        const bidInp = row ? row.querySelector('.brand_id') : null;
        if (bidInp) bidInp.value = item.id || '';

        showRxInfoBar(item);
        closeRxDropdown();
        fillRegimenFromSelectedDrug(row, item);

        if (focusNext) {
            const doseInput = row ? row.querySelector('.rx-dose-input') : null;
            if (doseInput) doseInput.focus();
        }
    };

    const openRxDrugModal = (item) => {
        if (!item || !item.id) return;

        const { modal, frame, title } = getRxDrugModalElements();
        if (!modal || !frame || !title) return;

        title.textContent = getSelectedDrugDisplayName(item) || item.generic || 'Drug View';
        const url = getRxDrugModalUrl(item);
        if (!url) return;

        if (rxDrugModalLoadedUrl === url) {
            modal.style.display = 'flex';
            return;
        }

        rxDrugModalPendingOpen = url;

        if (rxDrugModalLoadedUrl !== url && rxDrugModalUrl !== url) {
            rxDrugModalUrl = url;
            setRxDrugModalLoadingState(true);
            frame.src = url;
        }
    };

    const closeRxDropdown = () => {
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
        rxFocus = -1;
    };

    const cancelRxLookups = () => {
        rxLookupGeneration += 1;
        clearTimeout(rxTimeout);
        closeRxDropdown();
    };

    const isCurrentRxLookup = (generation, input) => {
        return generation === rxLookupGeneration && input && input.isConnected;
    };

    const positionDropdown = (input, ul) => {
        const anchor = input?.closest('td') || input;
        const rect = anchor.getBoundingClientRect();
        ul.style.position = 'absolute';
        ul.style.top = (rect.bottom + window.scrollY) + 'px';
        ul.style.left = (rect.left + window.scrollX) + 'px';
        ul.style.minWidth = rect.width + 'px';
        ul.style.width = 'max-content';
        ul.style.maxWidth = 'min(90vw, 750px)';
        ul.style.zIndex = '9999';
    };

    document.addEventListener('input', (e) => {
        if (e.target.matches('.rx-input')) {
            autoSizeRxField(e.target);
        }

        if (e.target.matches('.rx-dose-input, .rx-instruction-input, .rx-duration-input')) {
            delete e.target.dataset.printBengali;
            delete e.target.dataset.printEnglish;
        }

        if (e.target.matches('.rx-drug-template-input')) {
            cancelRxLookups();
            handleDrugTemplateAutocomplete(e.target);
        } else if (e.target.matches('.rx-brand-input')) {
            cancelRxLookups();
            const input = e.target;
            const row = input.closest('tr');
            const selectedDrug = row ? getRowSelectedDrug(row) : null;
            delete input.dataset.suppressAlternates;
            const query = input.value.trim();

            if (selectedDrug) {
                const selectedDisplayName = getSelectedDrugDisplayName(selectedDrug).trim();
                if (selectedDisplayName !== '' && query !== '' && query !== selectedDisplayName) {
                    clearSelectedDrugState(row, {
                        clearGenericInput: true,
                        clearRegimen: true,
                        clearInfoBar: true
                    });
                }
            }
            
            if (query.length < 1) {
                // Show default list even when empty
                const generation = rxLookupGeneration;
                fetchRxSearchJson('drug-search:default', 'api/search_drug.php?q=')
                    .then(data => {
                        if (!isCurrentRxLookup(generation, input)) return;
                        renderDrugDropdown(input, data);
                    });
                return;
            }

            const generation = rxLookupGeneration;
            rxTimeout = setTimeout(() => {
                fetchRxSearchJson(`drug-search:q:${query.toLowerCase()}`, `api/search_drug.php?q=${encodeURIComponent(query)}`)
                    .then(data => {
                        if (!isCurrentRxLookup(generation, input)) return;
                        renderDrugDropdown(input, data);
                    });
            }, 110);
        } else if (e.target.matches('.rx-dose-input, .rx-instruction-input, .rx-duration-input')) {
            cancelRxLookups();
            handleGenericAutocomplete(e.target);
        }
    });

    const renderDrugDropdown = (input, data) => {
        closeRxDropdown();
        if (!data || data.length === 0 || data.error) return;

        data.slice(0, 3).forEach((item) => {
            primeRxDrugModal(item);
        });

        const ul = document.createElement('ul');
        ul.className = 'zrx-dropdown rx-dropdown rx-drug-dropdown show';

        data.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'zrx-dropdown-item rx-dropdown-item rx-drug-item' + (index === 0 ? ' active' : '');

            const iconPath = getDosageFormIcon(item.form, item.pres_new_upper);
            const iconHtml = iconPath
                ? `<img class="rx-dd-icon" src="${iconPath}" width="16" height="16" alt="">`
                : '<span class="rx-dd-icon-fallback">★</span>';

            let formattedPrice = parseFloat(item.price).toFixed(2);
            const priceText = isNaN(formattedPrice) ? (item.price || '') : formattedPrice;

            li.innerHTML = `
                ${iconHtml}
                <strong class="rx-dd-brand">${item.pres_new_upper || ''}</strong>
                <span class="rx-dd-generic">${item.generic || ''}</span>
                <span class="rx-dd-man">${item.man_short ? '-' + item.man_short : ''}</span>
                <span class="rx-dd-price">${priceText ? '৳' + priceText : ''}</span>
            `;

            li.addEventListener('mousedown', (ev) => {
                ev.preventDefault();
                applySelectedDrug(input, item, false);
            });
            li.addEventListener('mouseenter', () => {
                const allItems = ul.querySelectorAll('.zrx-dropdown-item, .rx-dropdown-item');
                allItems.forEach(el => el.classList.remove('active'));
                li.classList.add('active');
                rxFocus = index;
                primeRxDrugModal(item);
            });
            li.addEventListener('mousemove', () => {
                if (rxFocus !== index) {
                    const allItems = ul.querySelectorAll('.zrx-dropdown-item, .rx-dropdown-item');
                    allItems.forEach(el => el.classList.remove('active'));
                    li.classList.add('active');
                    rxFocus = index;
                    primeRxDrugModal(item);
                }
            });
            ul.appendChild(li);
        });

        activeDropdown = ul;
        document.body.appendChild(ul);
        positionDropdown(input, ul);
        rxFocus = 0;
    };

    function handleDrugTemplateAutocomplete(input) {
        clearTimeout(rxTimeout);
        const query = input.value.trim();
        const generation = rxLookupGeneration;

        rxTimeout = setTimeout(() => {
            fetch(`api/rx_user_templates.php?type=drug&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (!isCurrentRxLookup(generation, input)) return;
                    closeRxDropdown();
                    if (!Array.isArray(data) || data.length === 0 || data.error) return;

                    const ul = document.createElement('ul');
                    ul.className = 'zrx-dropdown rx-dropdown show';

                    data.forEach((item, index) => {
                        const li = document.createElement('li');
                        li.className = 'zrx-dropdown-item rx-dropdown-item' + (index === 0 ? ' active' : '');
                        li.innerHTML = `
                            <div style="display:flex; align-items:center; justify-content:space-between; width: 100%;">
                                <strong>${item.name}</strong>
                                <span style="color:#64748b; font-size:0.8rem; margin-left:6px;">${item.row_count || 0} rows</span>
                            </div>
                        `;

                        li.addEventListener('mousedown', (ev) => {
                            ev.preventDefault();
                            input.value = item.name || '';
                            applyUserTemplate(item.id);
                        });
                        li.addEventListener('mouseenter', () => {
                            const allItems = ul.querySelectorAll('.zrx-dropdown-item, .rx-dropdown-item');
                            allItems.forEach(el => el.classList.remove('active'));
                            li.classList.add('active');
                            rxFocus = index;
                        });
                        li.addEventListener('mousemove', () => {
                            if (rxFocus !== index) {
                                const allItems = ul.querySelectorAll('.zrx-dropdown-item, .rx-dropdown-item');
                                allItems.forEach(el => el.classList.remove('active'));
                                li.classList.add('active');
                                rxFocus = index;
                            }
                        });
                        ul.appendChild(li);
                    });

                    activeDropdown = ul;
                    document.body.appendChild(ul);
                    positionDropdown(input, ul);
                    rxFocus = 0;
                });
        }, 150);
    }

    function handleGenericAutocomplete(input) {
        clearTimeout(rxTimeout);
        const query = input.value.trim();
        const generation = rxLookupGeneration;

        let type = '';
        let nextSelector = '';

        if (input.classList.contains('rx-dose-input')) {
            type = 'dose';
            nextSelector = '.rx-instruction-input';
        } else if (input.classList.contains('rx-instruction-input')) {
            type = 'instruction';
            nextSelector = '.rx-duration-input';
        } else {
            type = 'duration';
        }

        rxTimeout = setTimeout(() => {
            const row = input.closest('tr');
            const params = getDrugContextParams(row);
            params.set('type', type);
            params.set('term', query);

            fetch(`api/rx_phrase_suggestions.php?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    if (!isCurrentRxLookup(generation, input)) return;
                    closeRxDropdown();
                    if (!data || data.length === 0 || data.error) return;

                    const ul = document.createElement('ul');
                    ul.className = 'zrx-dropdown rx-dropdown show';

                    data.forEach((item, index) => {
                        const li = document.createElement('li');
                        li.className = 'zrx-dropdown-item rx-dropdown-item' + (index === 0 ? ' active' : '');

                        let pinHtml = '';
                        if (item.is_pinned) {
                            li.classList.add('pinned-item');
                            pinHtml = `<img class="rx-dropdown-pin" src="assets/images/pin.svg" alt="Pinned">`;
                        }

                        li.innerHTML = `
                            ${pinHtml}
                            <div style="display:flex; align-items:center; width: 100%;">
                                <strong>${item.label}</strong>
                            </div>
                        `;

                        li.addEventListener('mousedown', (ev) => {
                            ev.preventDefault();
                            setRxLanguageValue(
                                input,
                                item.value || item.label,
                                item.value_bn || item.dosage_bn || item.duration_bn || item.instruction_bn,
                                item.value_en || item.dosage_en || item.duration_en || item.instruction_en
                            );
                            closeRxDropdown();

                            if (nextSelector) {
                                const nextInput = input.closest('tr').querySelector(nextSelector);
                                if (nextInput) nextInput.focus();
                            }
                        });
                        li.addEventListener('mouseenter', () => {
                            const allItems = ul.querySelectorAll('.zrx-dropdown-item, .rx-dropdown-item');
                            allItems.forEach(el => el.classList.remove('active'));
                            li.classList.add('active');
                            rxFocus = index;
                        });
                        li.addEventListener('mousemove', () => {
                            if (rxFocus !== index) {
                                const allItems = ul.querySelectorAll('.zrx-dropdown-item, .rx-dropdown-item');
                                allItems.forEach(el => el.classList.remove('active'));
                                li.classList.add('active');
                                rxFocus = index;
                            }
                        });
                        ul.appendChild(li);
                    });

                    activeDropdown = ul;
                    document.body.appendChild(ul);
                    positionDropdown(input, ul);
                    rxFocus = 0;
                });
        }, 100);
    }

    document.addEventListener('focus', (e) => {
        if (e.target.matches('.rx-drug-template-input')) {
            cancelRxLookups();
            handleDrugTemplateAutocomplete(e.target);
        } else if (e.target.matches('.rx-brand-input')) {
            cancelRxLookups();
            const input = e.target;
            if (input.dataset.suppressAlternates === '1') {
                return;
            }

            const row = input.closest('tr');
            const selectedDrug = row ? getRowSelectedDrug(row) : null;
            const generic = input.dataset.selectedGeneric;
            const query = input.value.trim();
            const selectedDisplayName = selectedDrug ? getSelectedDrugDisplayName(selectedDrug).trim() : '';

            if (query.length === 0) {
                // Show default list on empty focus
                const generation = rxLookupGeneration;
                fetchRxSearchJson('drug-search:default', 'api/search_drug.php?q=')
                    .then(data => {
                        if (!isCurrentRxLookup(generation, input)) return;
                        renderDrugDropdown(input, data);
                    });
                return;
            }

            if (generic && selectedDisplayName !== '' && query === selectedDisplayName) {
                const form = input.dataset.selectedForm;
                const strength = input.dataset.selectedStrength;
                const genericId = input.dataset.selectedGenericId || '';
                const generation = rxLookupGeneration;
                const cacheKey = `drug-search:generic:${genericId || generic}:${form || ''}:${strength || ''}`;
                const url = genericId
                    ? `api/search_drug.php?generic_id=${encodeURIComponent(genericId)}&generic=${encodeURIComponent(generic)}&form=${encodeURIComponent(form || '')}&strength=${encodeURIComponent(strength || '')}`
                    : `api/search_drug.php?generic=${encodeURIComponent(generic)}&form=${encodeURIComponent(form || '')}&strength=${encodeURIComponent(strength || '')}`;

                fetchRxSearchJson(cacheKey, url)
                    .then(data => {
                        if (!isCurrentRxLookup(generation, input)) return;
                        renderDrugDropdown(input, data);
                    });
                return;
            }

            const generation = rxLookupGeneration;
            fetchRxSearchJson(`drug-search:q:${query.toLowerCase()}`, `api/search_drug.php?q=${encodeURIComponent(query)}`)
                .then(data => {
                    if (!isCurrentRxLookup(generation, input)) return;
                    renderDrugDropdown(input, data);
                });
        } else if (e.target.matches('.rx-dose-input, .rx-instruction-input, .rx-duration-input')) {
            cancelRxLookups();
            handleGenericAutocomplete(e.target);
        }
    }, true);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isRxSettingsModalOpen()) {
            closeRxSettingsModal();
            return;
        }

        if (!activeDropdown) return;

        const items = activeDropdown.querySelectorAll('.rx-dropdown-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            items[rxFocus]?.classList.remove('active');
            rxFocus = (rxFocus + 1) % items.length;
            items[rxFocus].classList.add('active');
            items[rxFocus].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            items[rxFocus]?.classList.remove('active');
            rxFocus = (rxFocus - 1 + items.length) % items.length;
            items[rxFocus].classList.add('active');
            items[rxFocus].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            if (rxFocus > -1) {
                e.preventDefault();
                items[rxFocus].dispatchEvent(new MouseEvent('mousedown'));
            }
        } else if (e.key === 'Escape') {
            closeRxDropdown();
        }
    });

    document.addEventListener('mousedown', (e) => {
        const isInput = e.target.matches('.rx-input, .rx-drug-template-input');
        if (activeDropdown && !activeDropdown.contains(e.target) && !isInput) {
            closeRxDropdown();
        }

        const settingsOpenBtn = e.target.closest('#rx-settings-open');
        if (settingsOpenBtn) {
            e.preventDefault();
            openRxSettingsModal();
            return;
        }

        const settingsCloseBtn = e.target.closest('[data-rx-settings-close]');
        if (settingsCloseBtn) {
            closeRxSettingsModal();
            return;
        }

        const restoreSettingsBtn = e.target.closest('#rx-settings-restore-defaults');
        if (restoreSettingsBtn) {
            e.preventDefault();
            resetRxSettingsToDefaults();
            return;
        }

        const addMoreBtn = e.target.closest('#rx-add-more-btn');
        if (addMoreBtn) {
            const tbody = getRxTbody();
            if (!tbody) return;

            const startIndex = tbody.querySelectorAll('tr').length;
            for (let i = 0; i < 5; i++) {
                tbody.appendChild(createRxRow(startIndex + i + 1));
            }
            renumberRxRows();
            return;
        }

        const removeBtn = e.target.closest('.rx-del button');
        if (removeBtn) {
            const row = removeBtn.closest('tr');
            const tbody = getRxTbody();
            if (row && tbody) {
                const hadFocusInsideRow = row.contains(document.activeElement);
                row.remove();
                renumberRxRows();

                if (!tbody.querySelector('tr')) {
                    tbody.appendChild(createRxRow(1));
                    renumberRxRows();
                }

                if (hadFocusInsideRow) {
                    hideRxInfoBar();
                }
            }
            return;
        }

        const infoBar = e.target.closest('#rx-info-bar');
        if (infoBar) {
            const activeInput = document.activeElement && document.activeElement.matches('.rx-input') ? document.activeElement : null;
            const activeRow = activeInput ? activeInput.closest('tr') : null;
            const selectedDrug = activeRow ? getRowSelectedDrug(activeRow) : null;

            if (selectedDrug) {
                openRxDrugModal(selectedDrug);
                return;
            }

            if (infoBar.dataset.brandId) {
                openRxDrugModal({
                    id: infoBar.dataset.brandId,
                    pres_new_upper: infoBar.dataset.brandName
                });
            }
        }

        const modal = e.target.closest('#rx-drug-modal');
        const closeBtn = e.target.closest('#rx-drug-modal-close');
        if (closeBtn || (modal && e.target === modal)) {
            rxDrugModalPendingOpen = '';
            if (modal) modal.style.display = 'none';
        }
    });

    {
        const { frame, modal } = getRxDrugModalElements();
        if (frame) {
            frame.addEventListener('load', () => {
                rxDrugModalLoadedUrl = normalizeRxDrugModalUrl(frame.src || rxDrugModalUrl);
                rxDrugModalUrl = rxDrugModalLoadedUrl;
                setRxDrugModalLoadingState(false);
                if (modal && rxDrugModalPendingOpen && rxDrugModalLoadedUrl === rxDrugModalPendingOpen) {
                    modal.style.display = 'flex';
                    rxDrugModalPendingOpen = '';
                }
            });
        }
    }

    document.addEventListener('blur', (e) => {
        if (e.target.matches('.rx-brand-input, .rx-drug-template-input')) {
            delete e.target.dataset.suppressAlternates;
            closeRxDropdown();
        }
    }, true);

    document.addEventListener('focusin', (e) => {
        if (!e.target.matches('.rx-input')) return;

        const row = e.target.closest('tr');
        const selectedDrug = getRowSelectedDrug(row);
        if (selectedDrug) {
            showRxInfoBar(selectedDrug);
        }
    });

    document.addEventListener('change', (e) => {
        if (e.target.matches('input[name="rx-prefix-mode"]')) {
            rxSettings.prefixMode = e.target.value === 'short' ? 'short' : 'full';
            saveRxSettings();
            applyRxSettingsToUi(true);
        } else if (e.target.matches('input[name="rx-generic-name-format"]')) {
            const val = e.target.value;
            rxSettings.genericNameFormat = ['plain', 'prescribe', 'labelled'].includes(val) ? val : 'plain';
            saveRxSettings();
            // Refresh generic inputs on all rows
            const tbody = getRxTbody();
            if (tbody) {
                tbody.querySelectorAll('tr').forEach((row) => {
                    const selectedDrug = getRowSelectedDrug(row);
                    const genInp = row.querySelector('.rx-generic-input');
                    if (selectedDrug && genInp) {
                        setRxFieldValue(genInp, getGenericDisplayName(selectedDrug));
                    }
                });
            }
            return;
        }

        if (e.target.matches('#rx-auto-row-size')) {
            rxSettings.autoRowSize = e.target.checked;
            saveRxSettings();
            applyRxSettingsToUi();
            return;
        }

        if (e.target.matches('#rx-show-warnings')) {
            rxSettings.showWarnings = e.target.checked;
            saveRxSettings();
            applyRxSettingsToUi();
            return;
        }

        if (e.target.matches('#rx-show-interactions')) {
            rxSettings.showInteractions = e.target.checked;
            saveRxSettings();
            applyRxSettingsToUi();
            return;
        }

        if (e.target.matches('[data-rx-warning-type]')) {
            const type = e.target.dataset.rxWarningType;
            rxSettings.warningTypes = {
                ...defaultWarningTypes,
                ...(rxSettings.warningTypes || {}),
                [type]: e.target.checked
            };
            saveRxSettings();
            applyRxSettingsToUi();
        }
    });

    loadRxSettings();
    renumberRxRows();
    applyRxSettingsToUi();
    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(prewarmRxSearch, { timeout: 1200 });
    } else {
        window.setTimeout(prewarmRxSearch, 300);
    }
    window.addEventListener('zimrx:clear-prescription-ui', cancelRxLookups);
    window.addEventListener('resize', closeRxDropdown);
}
