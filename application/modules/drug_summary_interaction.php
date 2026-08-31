<div class="drug-summary-wrapper" id="drug-summary-module">
    <div class="drug-summary-header">
        <div>
            <h3>Drug Summary & Interaction</h3>
            <div class="drug-summary-subline" id="drug-summary-status">No drug selected</div>
        </div>
        <button type="button" class="drug-interaction-open-btn" id="drug-interaction-open">Drug Interaction</button>
    </div>

    <div class="drug-summary-metrics">
        <div class="drug-summary-metric">
            <span>Total drug prescribed</span>
            <strong id="drug-summary-total">0</strong>
        </div>
        <div class="drug-summary-metric">
            <span>Daily expense</span>
            <strong id="drug-summary-daily">Tk 0.00</strong>
        </div>
        <div class="drug-summary-metric">
            <span>Total expense</span>
            <strong id="drug-summary-course">Tk 0.00</strong>
        </div>
        <div class="drug-summary-metric">
            <span>Antibiotic</span>
            <strong id="drug-summary-antibiotic">No</strong>
        </div>
    </div>

    <div class="drug-antibiotic-note" id="drug-antibiotic-note" hidden>
        Please counsel about completing full dose and AMR.
    </div>

    <div class="drug-summary-grid">
        <section class="drug-summary-section" id="drug-summary-interaction-section">
            <div class="drug-summary-section-head">
                <span>Expense per drug</span>
            </div>
            <div class="drug-summary-table-wrap">
                <table class="drug-summary-table">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Daily</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="drug-summary-expense-rows">
                        <tr><td colspan="3" class="drug-summary-empty">No selected drugs</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="drug-summary-section">
            <div class="drug-summary-section-head">
                <span>Interactions between chosen drugs</span>
                <strong id="drug-summary-interaction-count">0</strong>
            </div>
            <div class="drug-interaction-list" id="drug-summary-interactions">
                <div class="drug-summary-empty">No interaction checked yet</div>
            </div>
        </section>
    </div>
</div>

<div class="drug-interaction-modal" id="drug-interaction-modal" hidden>
    <div class="drug-interaction-backdrop" data-drug-interaction-close></div>
    <div class="drug-interaction-panel" role="dialog" aria-modal="true" aria-labelledby="drug-interaction-title">
        <div class="drug-interaction-header">
            <h3 id="drug-interaction-title">Drug Interaction</h3>
            <button type="button" class="drug-interaction-close" data-drug-interaction-close aria-label="Close Drug Interaction">&times;</button>
        </div>

        <div class="drug-interaction-body">
            <div class="drug-interaction-inputs">
                <label>
                    <span>Drug A</span>
                    <input type="text" class="drug-interaction-drug-input" id="drug-interaction-a" autocomplete="off">
                    <small id="drug-interaction-a-meta"></small>
                </label>
                <label>
                    <span>Drug B</span>
                    <input type="text" class="drug-interaction-drug-input" id="drug-interaction-b" autocomplete="off">
                    <small id="drug-interaction-b-meta"></small>
                </label>
            </div>
            <button type="button" class="drug-interaction-check-btn" id="drug-interaction-check">Check Drug</button>
            <div class="drug-interaction-result" id="drug-interaction-result">Select two drugs to check.</div>
        </div>
    </div>
</div>

<script>
(function () {
    const wrapper = document.getElementById('drug-summary-module');
    const rxTbody = document.getElementById('rx-tbody');
    if (!wrapper || !rxTbody) return;
    const RX_SETTINGS_KEY = 'zimrx_rx_settings';

    const els = {
        status: document.getElementById('drug-summary-status'),
        total: document.getElementById('drug-summary-total'),
        daily: document.getElementById('drug-summary-daily'),
        course: document.getElementById('drug-summary-course'),
        antibiotic: document.getElementById('drug-summary-antibiotic'),
        antibioticNote: document.getElementById('drug-antibiotic-note'),
        expenseRows: document.getElementById('drug-summary-expense-rows'),
        interactions: document.getElementById('drug-summary-interactions'),
        interactionCount: document.getElementById('drug-summary-interaction-count'),
        interactionSection: document.getElementById('drug-summary-interaction-section'),
        interactionOpen: document.getElementById('drug-interaction-open'),
        modal: document.getElementById('drug-interaction-modal'),
        inputA: document.getElementById('drug-interaction-a'),
        inputB: document.getElementById('drug-interaction-b'),
        metaA: document.getElementById('drug-interaction-a-meta'),
        metaB: document.getElementById('drug-interaction-b-meta'),
        result: document.getElementById('drug-interaction-result')
    };

    let updateTimer = null;
    let interactionRequestId = 0;
    let lookupTimer = null;
    let lookupDropdown = null;
    let activeLookupInput = null;
    let lookupIndex = -1;

    function getRxSettings() {
        try {
            return JSON.parse(localStorage.getItem(RX_SETTINGS_KEY) || '{}') || {};
        } catch (err) {
            return {};
        }
    }

    function interactionsEnabled() {
        return getRxSettings().showInteractions !== false;
    }

    function applyInteractionVisibility() {
        const enabled = interactionsEnabled();
        if (els.interactionSection) els.interactionSection.hidden = !enabled;
        if (els.interactionOpen) els.interactionOpen.hidden = !enabled;
        if (!enabled) {
            closeModal();
            els.interactionCount.textContent = '0';
            els.interactions.innerHTML = '<div class="drug-summary-empty">Interaction checking is hidden from Rx settings</div>';
        }
        return enabled;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function normalizeDigits(value) {
        const map = {
            '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4',
            '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9'
        };
        return String(value || '').replace(/[০-৯]/g, (digit) => map[digit] || digit);
    }

    function numberFromText(value) {
        const normalized = normalizeDigits(value).replace(/,/g, '');
        const match = normalized.match(/\d+(?:\.\d+)?/);
        return match ? Number(match[0]) : null;
    }

    function parseDosePart(part) {
        const text = part.trim();
        if (!text || text === '0') return 0;
        if (/^\d+(?:\.\d+)?\/\d+(?:\.\d+)?$/.test(text)) {
            const [left, right] = text.split('/').map(Number);
            return right ? left / right : 0;
        }
        const num = Number(text);
        return Number.isFinite(num) ? num : 0;
    }

    function parseDailyUnits(value) {
        const text = normalizeDigits(value).toLowerCase().replace(/\s+/g, ' ').trim();
        if (!text) return null;

        const pattern = text.match(/(\d+(?:\.\d+)?(?:\/\d+(?:\.\d+)?)?)(?:\s*[+\-x]\s*(\d+(?:\.\d+)?(?:\/\d+(?:\.\d+)?)?)){1,5}/);
        if (pattern) {
            const parts = pattern[0].split(/[+\-x]/);
            return parts.reduce((sum, part) => sum + parseDosePart(part), 0);
        }

        if (/\b(qid|qds|four times|4 times)\b/.test(text)) return 4;
        if (/\b(tid|tds|three times|3 times)\b/.test(text)) return 3;
        if (/\b(bid|bd|twice|2 times)\b/.test(text)) return 2;
        if (/\b(od|daily|once|hs|night|mane|morning)\b/.test(text)) return 1;

        const numeric = numberFromText(text);
        return numeric && numeric <= 12 ? numeric : null;
    }

    function parseDurationDays(value) {
        const text = normalizeDigits(value).toLowerCase().replace(/\s+/g, ' ').trim();
        if (!text) return null;
        const amount = numberFromText(text);
        if (!amount) return null;
        if (/\b(year|years|yr|yrs)\b|বছর/.test(text)) return amount * 365;
        if (/\b(month|months|mo)\b|মাস/.test(text)) return amount * 30;
        if (/\b(week|weeks|wk|wks)\b|সপ্তাহ/.test(text)) return amount * 7;
        if (/\b(day|days|d)\b|দিন/.test(text)) return amount;
        return amount;
    }

    function formatMoney(value) {
        return Number.isFinite(value) ? `Tk ${value.toFixed(2)}` : 'N/A';
    }

    function truthy(value) {
        return value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true';
    }

    function getSelectedDrug(row) {
        if (!row || !row.dataset.selectedDrug) return null;
        try {
            return JSON.parse(row.dataset.selectedDrug);
        } catch (err) {
            return null;
        }
    }

    function getDisplayName(item, row) {
        const manualBrand = row?.querySelector('.rx-brand-input')?.value?.trim() || '';
        return item?.full_form_brand_name || item?.pres_new_upper || item?.brand_name || manualBrand || '';
    }

    function collectDrugRows() {
        return Array.from(rxTbody.querySelectorAll('tr')).map((row, index) => {
            const item = getSelectedDrug(row);
            const brandInput = row.querySelector('.rx-brand-input')?.value?.trim() || '';
            const dose = row.querySelector('.rx-dose-input')?.value?.trim() || '';
            const duration = row.querySelector('.rx-duration-input')?.value?.trim() || '';
            const name = getDisplayName(item, row);
            const price = numberFromText(item?.price);
            const dailyUnits = parseDailyUnits(dose);
            const durationDays = parseDurationDays(duration);
            const dailyCost = price != null && dailyUnits != null ? price * dailyUnits : NaN;
            const totalCost = Number.isFinite(dailyCost) && durationDays != null ? dailyCost * durationDays : NaN;

            return {
                index: index + 1,
                row,
                item,
                name,
                brandInput,
                dose,
                duration,
                price,
                dailyUnits,
                durationDays,
                dailyCost,
                totalCost,
                genericId: item?.generic_id ? String(item.generic_id).trim() : '',
                genericName: item?.generic || item?.generic_name || row.querySelector('.rx-generic-input')?.value?.trim() || '',
                isAntibiotic: truthy(item?.is_antibiotic)
            };
        }).filter((drug) => drug.name || drug.brandInput || drug.dose || drug.duration);
    }

    function selectedGenericIds(drugs) {
        return Array.from(new Set(drugs.map((drug) => drug.genericId).filter(Boolean)));
    }

    function renderExpenseRows(drugs) {
        const selected = drugs.filter((drug) => drug.item);
        if (!selected.length) {
            els.expenseRows.innerHTML = '<tr><td colspan="3" class="drug-summary-empty">No selected drugs</td></tr>';
            return;
        }

        els.expenseRows.innerHTML = selected.map((drug) => `
            <tr>
                <td>
                    <strong>${escapeHtml(drug.name)}</strong>
                    <span>${escapeHtml(drug.genericName || 'Generic not selected')}</span>
                </td>
                <td>${escapeHtml(formatMoney(drug.dailyCost))}</td>
                <td>${escapeHtml(formatMoney(drug.totalCost))}</td>
            </tr>
        `).join('');
    }

    function renderInteractions(items, lifestyles = []) {
        const interactions = Array.isArray(items) ? items : [];
        const lifestyleList = Array.isArray(lifestyles) ? lifestyles : [];
        const totalCount = interactions.length + lifestyleList.length;
        els.interactionCount.textContent = String(totalCount);
        if (!totalCount) {
            els.interactions.innerHTML = '<div class="drug-summary-empty">No interaction found between selected drugs</div>';
            return;
        }

        let html = '';
        if (interactions.length) {
            html += interactions.map((item) => {
                const names = Array.isArray(item.selected_drug_names) && item.selected_drug_names.length
                    ? item.selected_drug_names.join(' + ')
                    : [item.drug_a, item.drug_b].filter(Boolean).join(' + ');
                const type = item.type === 'duplicate_component' ? 'Duplicate' : 'Interaction';
                return `
                    <div class="drug-interaction-item ${item.type === 'duplicate_component' ? 'warning' : 'danger'}">
                        <strong>${escapeHtml(type)}: ${escapeHtml(names)}</strong>
                        <span>${escapeHtml(item.interaction || '')}</span>
                    </div>
                `;
            }).join('');
        }

        if (lifestyleList.length) {
            html += lifestyleList.map((item) => `
                <div class="drug-interaction-item warning" style="border-left-color: #f59e0b; background: #fffbeb;">
                    <strong style="color: #b45309;">⚠️ Patient Advisory: ${escapeHtml(item.drug)} vs ${escapeHtml(item.substance)}</strong>
                    <span style="color: #92400e;">${escapeHtml(item.interaction || '')}</span>
                </div>
            `).join('');
        }

        els.interactions.innerHTML = html;
    }

    function fetchInteractions(genericIds) {
        const requestId = ++interactionRequestId;
        if (!applyInteractionVisibility()) return;
        if (genericIds.length < 1) {
            renderInteractions([], []);
            return;
        }

        els.interactions.innerHTML = '<div class="drug-summary-empty">Checking interactions...</div>';
        fetch(`api/check_drug_interactions.php?generic_ids=${encodeURIComponent(genericIds.join(','))}`)
            .then((res) => res.json())
            .then((data) => {
                if (requestId !== interactionRequestId) return;
                renderInteractions(
                    data && Array.isArray(data.interactions) ? data.interactions : [],
                    data && Array.isArray(data.lifestyle_advisories) ? data.lifestyle_advisories : []
                );
            })
            .catch(() => {
                if (requestId !== interactionRequestId) return;
                els.interactions.innerHTML = '<div class="drug-summary-empty">Interaction check failed</div>';
                els.interactionCount.textContent = '0';
            });
    }

    function updateSummary() {
        const drugs = collectDrugRows();
        const selected = drugs.filter((drug) => drug.item);
        const dailyKnown = selected.filter((drug) => Number.isFinite(drug.dailyCost));
        const totalKnown = selected.filter((drug) => Number.isFinite(drug.totalCost));
        const dailyTotal = dailyKnown.reduce((sum, drug) => sum + drug.dailyCost, 0);
        const courseTotal = totalKnown.reduce((sum, drug) => sum + drug.totalCost, 0);
        const hasAntibiotic = selected.some((drug) => drug.isAntibiotic);
        const missingDaily = selected.length > dailyKnown.length;
        const missingTotal = selected.length > totalKnown.length;

        els.total.textContent = String(drugs.length);
        els.daily.textContent = `${formatMoney(dailyTotal)}${missingDaily ? ' +' : ''}`;
        els.course.textContent = `${formatMoney(courseTotal)}${missingTotal ? ' +' : ''}`;
        els.antibiotic.textContent = hasAntibiotic ? 'Yes' : 'No';
        els.antibiotic.classList.toggle('danger', hasAntibiotic);
        els.antibioticNote.hidden = !hasAntibiotic;
        els.status.textContent = selected.length
            ? `${selected.length} selected from drug database`
            : (drugs.length ? `${drugs.length} row entered manually` : 'No drug selected');

        renderExpenseRows(drugs);
        if (applyInteractionVisibility()) {
            fetchInteractions(selectedGenericIds(selected));
        }
    }

    function scheduleUpdate() {
        window.clearTimeout(updateTimer);
        updateTimer = window.setTimeout(updateSummary, 120);
    }

    function closeLookupDropdown() {
        if (lookupDropdown) lookupDropdown.remove();
        lookupDropdown = null;
        activeLookupInput = null;
        lookupIndex = -1;
    }

    function positionLookupDropdown(input) {
        if (!lookupDropdown) return;
        const rect = input.getBoundingClientRect();
        lookupDropdown.style.top = `${rect.bottom + window.scrollY}px`;
        lookupDropdown.style.left = `${rect.left + window.scrollX}px`;
        lookupDropdown.style.minWidth = `${rect.width}px`;
    }

    function setManualDrug(input, item) {
        const side = input === els.inputA ? 'a' : 'b';
        const meta = side === 'a' ? els.metaA : els.metaB;
        input.value = item.label || '';
        input.dataset.genericId = item.generic_id || '';
        input.dataset.genericName = item.generic_name || '';
        meta.textContent = item.type === 'brand'
            ? `${item.brand_name || item.label} - ${item.generic_name || ''}`
            : (item.generic_name || item.label || '');
        closeLookupDropdown();
    }

    function renderLookupDropdown(input, items) {
        closeLookupDropdown();
        if (!Array.isArray(items) || !items.length || items.error) return;
        const ul = document.createElement('ul');
        ul.className = 'rx-dropdown drug-interaction-lookup';
        ul.style.position = 'absolute';
        ul.style.zIndex = '10005';

        items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'rx-dropdown-item';
            if (index === 0) li.classList.add('active');
            const badge = item.type === 'generic' ? 'Generic' : 'Brand';
            li.innerHTML = `
                <div class="rx-dropdown-main">
                    <strong>${escapeHtml(item.label || '')}</strong>
                    <span>${escapeHtml(badge)}${item.generic_name ? ` - ${escapeHtml(item.generic_name)}` : ''}</span>
                </div>
            `;
            li.addEventListener('mousedown', (event) => {
                event.preventDefault();
                setManualDrug(input, item);
            });
            ul.appendChild(li);
        });

        document.body.appendChild(ul);
        lookupDropdown = ul;
        activeLookupInput = input;
        lookupIndex = 0;
        positionLookupDropdown(input);
    }

    function lookupDrug(input) {
        window.clearTimeout(lookupTimer);
        input.dataset.genericId = '';
        input.dataset.genericName = '';
        const meta = input === els.inputA ? els.metaA : els.metaB;
        meta.textContent = '';
        const q = input.value.trim();
        if (!q) {
            closeLookupDropdown();
            return;
        }
        lookupTimer = window.setTimeout(() => {
            fetch(`api/drug_lookup.php?q=${encodeURIComponent(q)}`)
                .then((res) => res.json())
                .then((items) => renderLookupDropdown(input, items))
                .catch(closeLookupDropdown);
        }, 150);
    }

    function openModal() {
        if (!interactionsEnabled()) return;
        const selected = collectDrugRows().filter((drug) => drug.item && drug.genericId);
        [els.inputA, els.inputB].forEach((input) => {
            input.value = '';
            input.dataset.genericId = '';
            input.dataset.genericName = '';
        });
        els.metaA.textContent = '';
        els.metaB.textContent = '';
        els.result.textContent = 'Select two drugs to check.';

        if (selected[0]) {
            setManualDrug(els.inputA, {
                type: 'brand',
                label: selected[0].name,
                brand_name: selected[0].name,
                generic_name: selected[0].genericName,
                generic_id: selected[0].genericId
            });
        }
        if (selected[1]) {
            setManualDrug(els.inputB, {
                type: 'brand',
                label: selected[1].name,
                brand_name: selected[1].name,
                generic_name: selected[1].genericName,
                generic_id: selected[1].genericId
            });
        }

        els.modal.hidden = false;
        (els.inputA.value ? els.inputB : els.inputA).focus();
    }

    function closeModal() {
        els.modal.hidden = true;
        closeLookupDropdown();
    }

    function checkManualInteraction() {
        const ids = [els.inputA.dataset.genericId || '', els.inputB.dataset.genericId || ''].filter(Boolean);
        if (ids.length < 2) {
            els.result.textContent = 'Select two drugs to check.';
            return;
        }

        els.result.textContent = 'Checking...';
        fetch(`api/check_drug_interactions.php?generic_ids=${encodeURIComponent(ids.join(','))}`)
            .then((res) => res.json())
            .then((data) => {
                const interactions = data && Array.isArray(data.interactions) ? data.interactions : [];
                if (!interactions.length) {
                    els.result.textContent = 'No interaction found.';
                    return;
                }
                els.result.innerHTML = interactions.map((item) => `
                    <div class="drug-interaction-item ${item.type === 'duplicate_component' ? 'warning' : 'danger'}">
                        <strong>${escapeHtml(item.type === 'duplicate_component' ? 'Duplicate' : 'Interaction')}</strong>
                        <span>${escapeHtml(item.interaction || '')}</span>
                    </div>
                `).join('');
            })
            .catch(() => {
                els.result.textContent = 'Interaction check failed.';
            });
    }

    document.getElementById('drug-interaction-open')?.addEventListener('click', openModal);
    document.getElementById('drug-interaction-check')?.addEventListener('click', checkManualInteraction);
    document.addEventListener('mousedown', (event) => {
        if (event.target.closest('[data-drug-interaction-close]')) {
            closeModal();
            return;
        }
        if (lookupDropdown && !lookupDropdown.contains(event.target) && !event.target.closest('.drug-interaction-drug-input')) {
            closeLookupDropdown();
        }
    });

    [els.inputA, els.inputB].forEach((input) => {
        input.addEventListener('input', () => lookupDrug(input));
        input.addEventListener('focus', () => lookupDrug(input));
        input.addEventListener('keydown', (event) => {
            if (!lookupDropdown || activeLookupInput !== input) return;
            const items = Array.from(lookupDropdown.querySelectorAll('.rx-dropdown-item'));
            if (!items.length) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                items[lookupIndex]?.classList.remove('active');
                lookupIndex = (lookupIndex + 1) % items.length;
                items[lookupIndex].classList.add('active');
                items[lookupIndex].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                items[lookupIndex]?.classList.remove('active');
                lookupIndex = (lookupIndex - 1 + items.length) % items.length;
                items[lookupIndex].classList.add('active');
                items[lookupIndex].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter') {
                event.preventDefault();
                items[lookupIndex]?.dispatchEvent(new MouseEvent('mousedown'));
            } else if (event.key === 'Escape') {
                closeLookupDropdown();
            }
        });
    });

    rxTbody.addEventListener('input', scheduleUpdate);
    rxTbody.addEventListener('mousedown', scheduleUpdate);
    rxTbody.addEventListener('keyup', scheduleUpdate);

    const observer = new MutationObserver(scheduleUpdate);
    observer.observe(rxTbody, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-selected-drug', 'value']
    });

    window.addEventListener('resize', closeLookupDropdown);
    window.addEventListener('zimrx-rx-settings-changed', () => {
        applyInteractionVisibility();
        scheduleUpdate();
    });
    applyInteractionVisibility();
    updateSummary();
})();
</script>
