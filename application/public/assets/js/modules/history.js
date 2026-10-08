// Clinical medical history module covering past illnesses, surgical, family, allergy, and habit categories.
(function() {
    function cleanText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function autoResizeTextarea(textarea) {
        if (!textarea || textarea.tagName !== 'TEXTAREA') return;
        textarea.style.transition = 'none';
        textarea.style.height = '0';
        const natural = Math.max(34, textarea.scrollHeight);
        textarea.style.height = natural + 'px';
        requestAnimationFrame(() => { textarea.style.transition = ''; });
    }

    function wireRepeatingRows(options) {
        const wrapper = document.getElementById(options.wrapperId);
        const tbody = document.getElementById(options.tbodyId);
        const template = document.getElementById(options.templateId);
        const addBtn = wrapper?.querySelector(options.addSelector);
        if (!wrapper || !tbody || !template || !addBtn) {
            return;
        }

        let draggedRow = null;

        function updateRowNumbers() {
            tbody.querySelectorAll('.pc-row-no').forEach((cell, index) => {
                cell.textContent = index + 1;
            });
        }

        addBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            const row = template.content.firstElementChild.cloneNode(true);
            tbody.appendChild(row);
            updateRowNumbers();
            const field = row.querySelector('textarea, input');
            if (field?.tagName === 'TEXTAREA') {
                autoResizeTextarea(field);
            }
            field?.focus();
        });

        wrapper.addEventListener('click', (event) => {
            const delBtn = event.target.closest('.pc-del button');
            if (!delBtn) {
                return;
            }
            event.stopPropagation();
            const rows = tbody.querySelectorAll('tr');
            if (rows.length <= 1) {
                rows[0]?.querySelectorAll('input, textarea').forEach((field) => { field.value = ''; });
                rows[0]?.querySelector('textarea') && autoResizeTextarea(rows[0].querySelector('textarea'));
                return;
            }
            delBtn.closest('tr')?.remove();
            updateRowNumbers();
        });

        wrapper.addEventListener('input', (event) => {
            if (event.target.tagName === 'TEXTAREA') {
                autoResizeTextarea(event.target);
            }
        });

        wrapper.addEventListener('pointerdown', (event) => {
            const moveBtn = event.target.closest('.history-row-move-btn, .pc-row-move-btn');
            if (moveBtn) {
                const tr = moveBtn.closest('tr');
                if (tr) {
                    tr.setAttribute('data-drag-ready', '1');
                    tr.draggable = true;
                }
            }
        });

        wrapper.addEventListener('mousedown', (event) => {
            const moveBtn = event.target.closest('.history-row-move-btn, .pc-row-move-btn');
            if (moveBtn) {
                const tr = moveBtn.closest('tr');
                if (tr) {
                    tr.setAttribute('data-drag-ready', '1');
                    tr.draggable = true;
                }
            }
        });

        wrapper.addEventListener('dragstart', (event) => {
            const row = event.target.closest('tr');
            if (!row) {
                event.preventDefault();
                return;
            }
            const moveBtn = event.target.closest('.history-row-move-btn, .pc-row-move-btn');
            if (row.dataset.dragReady !== '1' && !moveBtn && !row.draggable) {
                event.preventDefault();
                return;
            }
            draggedRow = row;
            row.classList.add('history-row-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
        });

        wrapper.addEventListener('dragover', (event) => {
            if (!draggedRow) {
                return;
            }
            const target = event.target.closest('tr');
            if (!target || target === draggedRow || target.parentElement !== tbody) {
                return;
            }
            event.preventDefault();
            const rect = target.getBoundingClientRect();
            const after = event.clientY > rect.top + rect.height / 2;
            tbody.insertBefore(draggedRow, after ? target.nextSibling : target);
            updateRowNumbers();
        });

        wrapper.addEventListener('dragend', () => {
            if (draggedRow) {
                draggedRow.classList.remove('history-row-dragging');
                draggedRow.removeAttribute('data-drag-ready');
            }
            draggedRow = null;
            updateRowNumbers();
        });

        wrapper.querySelectorAll('textarea').forEach(autoResizeTextarea);
        updateRowNumbers();
    }

    function initHypersensitivityChips() {
        const root = document.querySelector('[data-history-chip-input]');
        const list = root?.querySelector('[data-history-chip-list]');
        const input = root?.querySelector('[data-history-chip-text]');
        if (!root || !list || !input) {
            return;
        }

        function currentLabels() {
            return Array.from(list.querySelectorAll('[data-history-chip]'))
                .map((chip) => cleanText(chip.dataset.value))
                .filter(Boolean);
        }

        function addChip(rawValue) {
            const value = cleanText(rawValue).replace(/,$/, '');
            if (!value || currentLabels().some((label) => label.toLowerCase() === value.toLowerCase())) {
                return;
            }
            const chip = document.createElement('span');
            chip.className = 'history-chip';
            chip.dataset.historyChip = '1';
            chip.dataset.value = value;

            const textSpan = document.createElement('span');
            textSpan.textContent = value;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.setAttribute('aria-label', `Remove ${value}`);
            removeBtn.textContent = 'x';

            chip.appendChild(textSpan);
            chip.appendChild(removeBtn);
            list.appendChild(chip);
        }

        function commitInput() {
            input.value.split(',').forEach(addChip);
            input.value = '';
        }

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                commitInput();
            } else if (event.key === 'Backspace' && input.value === '') {
                list.lastElementChild?.remove();
            }
        });

        input.addEventListener('blur', commitInput);
        root.addEventListener('click', (event) => {
            const removeBtn = event.target.closest('.history-chip button');
            if (removeBtn) {
                removeBtn.closest('.history-chip')?.remove();
                return;
            }
            input.focus();
        });
    }

    function initDrugAutocomplete() {
        const wrapper = document.getElementById('dh-wrapper');
        if (!wrapper) {
            return;
        }

        let debounceTimer;
        let activeDropdown = null;

        function closeDropdown() {
            if (activeDropdown) {
                activeDropdown.remove();
                activeDropdown = null;
            }
        }

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.autocomplete-list') && !event.target.classList.contains('dh-input')) {
                closeDropdown();
            }
        });

        wrapper.addEventListener('input', (event) => {
            if (!event.target.classList.contains('dh-input')) {
                return;
            }

            const input = event.target;
            const query = input.value.trim();

            clearTimeout(debounceTimer);
            if (query.length < 2) {
                closeDropdown();
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch('api/search_drug.php?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        closeDropdown();
                        if (!data || data.error || data.length === 0) return;

                        const ul = document.createElement('ul');
                        ul.className = 'autocomplete-list show appointment-lookup-list';
                        ul.style.position = 'absolute';
                        ul.style.width = input.offsetWidth + 'px';
                        ul.style.zIndex = '1000';

                        const rect = input.getBoundingClientRect();
                        ul.style.top = (rect.bottom + window.scrollY) + 'px';
                        ul.style.left = (rect.left + window.scrollX) + 'px';

                        const generics = new Set();
                        data.forEach(item => {
                            if (item.generic) generics.add(item.generic);
                        });

                        const _esc = (v) => ZimRxDropdown ? ZimRxDropdown.escapeHtml(v) : String(v || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        generics.forEach(generic => {
                            const li = document.createElement('li');
                            li.className = 'patient-lookup-option';
                            li.innerHTML = `<strong>${_esc(generic)}</strong><span class="meta">Generic</span>`;
                            li.addEventListener('mousedown', (ev) => {
                                ev.preventDefault();
                                input.value = generic;
                                autoResizeTextarea(input);
                                closeDropdown();
                            });
                            ul.appendChild(li);
                        });

                        data.forEach((item) => {
                            const li = document.createElement('li');
                            li.className = 'patient-lookup-option';
                            const man = item.man_short || item.manufacturer || '';
                            const _e = (v) => ZimRxDropdown.escapeHtml(v);
                            li.innerHTML = `<strong>${_e(item.pres_new_upper)}</strong><span class="meta">${_e(item.generic)} | ${_e(man)}</span>`;

                            li.addEventListener('mousedown', (ev) => {
                                ev.preventDefault();
                                input.value = item.generic ? `${item.generic} (${item.pres_new_upper})` : item.pres_new_upper;
                                autoResizeTextarea(input);
                                closeDropdown();
                            });
                            ul.appendChild(li);
                        });

                        if (ul.firstElementChild) {
                            ul.firstElementChild.classList.add('active');
                        }

                        document.body.appendChild(ul);
                        activeDropdown = ul;
                    })
                    .catch(err => console.error('Drug Search Error:', err));
            }, 300);
        });

        wrapper.addEventListener('keydown', (event) => {
            if (!event.target.classList.contains('dh-input') || !activeDropdown) {
                return;
            }
            const items = activeDropdown.querySelectorAll('li');
            let activeIdx = Array.from(items).findIndex(item => item.classList.contains('active'));

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (activeIdx > -1) items[activeIdx].classList.remove('active');
                activeIdx = (activeIdx + 1) % items.length;
                items[activeIdx].classList.add('active');
                items[activeIdx].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (activeIdx > -1) items[activeIdx].classList.remove('active');
                activeIdx = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
                items[activeIdx].classList.add('active');
                items[activeIdx].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (activeIdx > -1) items[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
            } else if (event.key === 'Escape') {
                closeDropdown();
            }
        });
    }

    function initDietSelectPlaceholder() {
        const select = document.querySelector('[data-history-field="diet-type"]');
        if (!select) return;

        function updatePlaceholderState() {
            select.classList.toggle('history-select-placeholder', !select.value);
        }
        select.addEventListener('change', updatePlaceholderState);
        updatePlaceholderState();
    }

    function initHistoryAccordions() {
        const root = document.querySelector('[data-history-module]');
        const toggleAllBtn = document.querySelector('[data-history-toggle-all]');
        if (!root) return;

        function updateToggleAllBtn() {
            if (!toggleAllBtn) return;
            const headers = Array.from(root.querySelectorAll('.history-accordion-header'));
            const allExpanded = headers.length > 0 && headers.every(h => h.getAttribute('aria-expanded') === 'true');
            const textSpan = toggleAllBtn.querySelector('.history-toggle-all-text');
            const iconSvg = toggleAllBtn.querySelector('svg');
            
            if (allExpanded) {
                if (textSpan) textSpan.textContent = 'Collapse All';
                if (iconSvg) iconSvg.innerHTML = '<polyline points="17 11 12 6 7 11"></polyline><polyline points="17 18 12 13 7 18"></polyline>';
            } else {
                if (textSpan) textSpan.textContent = 'Expand All';
                if (iconSvg) iconSvg.innerHTML = '<polyline points="7 13 12 18 17 13"></polyline><polyline points="7 6 12 11 17 6"></polyline>';
            }
        }

        if (toggleAllBtn) {
            toggleAllBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const headers = Array.from(root.querySelectorAll('.history-accordion-header'));
                const allExpanded = headers.length > 0 && headers.every(h => h.getAttribute('aria-expanded') === 'true');
                const targetState = !allExpanded;

                headers.forEach(headerBtn => {
                    const submodule = headerBtn.closest('.history-submodule');
                    const content = submodule?.querySelector('.history-accordion-content');
                    if (!submodule || !content) return;

                    headerBtn.setAttribute('aria-expanded', targetState);
                    headerBtn.classList.toggle('active', targetState);
                    content.style.display = targetState ? 'block' : 'none';

                    if (targetState) {
                        content.querySelectorAll('textarea').forEach(autoResizeTextarea);
                    }
                });

                updateToggleAllBtn();
            });
        }

        root.addEventListener('click', (e) => {
            const headerBtn = e.target.closest('.history-accordion-header');
            if (!headerBtn) return;
            e.preventDefault();
            const submodule = headerBtn.closest('.history-submodule');
            const content = submodule?.querySelector('.history-accordion-content');
            if (!submodule || !content) return;

            const isExpanded = headerBtn.getAttribute('aria-expanded') === 'true';
            headerBtn.setAttribute('aria-expanded', !isExpanded);
            headerBtn.classList.toggle('active', !isExpanded);
            content.style.display = !isExpanded ? 'block' : 'none';

            if (!isExpanded) {
                content.querySelectorAll('textarea').forEach(autoResizeTextarea);
            }
            updateToggleAllBtn();
        });

        updateToggleAllBtn();
    }

    function initHabitQuantifiers() {
        const root = document.querySelector('[data-history-module]');
        if (!root) return;

        root.addEventListener('change', (e) => {
            const cb = e.target.closest('input[data-history-field="habit"]');
            if (!cb) return;
            const item = cb.closest('.history-habit-item');
            const qtyWrap = item?.querySelector('.history-habit-qty-wrap');
            const calcBtn = item?.querySelector('.history-calc-btn');
            if (qtyWrap) {
                qtyWrap.style.display = cb.checked ? 'inline-flex' : 'none';
                if (cb.checked) {
                    const input = qtyWrap.querySelector('input');
                    if (input) input.focus();
                } else {
                    const input = qtyWrap.querySelector('input');
                    if (input) input.value = '';
                }
            }
            if (calcBtn) {
                calcBtn.style.display = cb.checked ? 'inline-flex' : 'none';
            }
        });
    }

    function initPackYearCalculator() {
        const modal = document.getElementById('packyear-calc-modal');
        if (!modal) return;

        const sticksInput = document.getElementById('packyear-calc-sticks');
        const packsInput = document.getElementById('packyear-calc-packs');
        const yearsInput = document.getElementById('packyear-calc-years');
        const resultNum = document.getElementById('packyear-calc-result');
        const riskBadge = document.getElementById('packyear-calc-risk');
        const applyBtn = document.getElementById('packyear-calc-apply-btn');

        function calculate() {
            const packs = parseFloat(packsInput?.value) || 0;
            const years = parseFloat(yearsInput?.value) || 0;
            const packYears = Math.round(packs * years * 10) / 10;
            
            if (resultNum) resultNum.textContent = packYears.toFixed(1);
            const resultUnit = modal.querySelector('.history-calc-result-unit');
            if (resultUnit) {
                resultUnit.textContent = packYears === 1 ? 'Pack-Year' : 'Pack-Years';
            }

            if (riskBadge) {
                if (packYears === 0) {
                    riskBadge.textContent = 'No Exposure (0 pack-years)';
                    riskBadge.className = 'history-calc-risk-badge';
                } else if (packYears < 10) {
                    riskBadge.textContent = 'Mild Risk (< 10 pack-years)';
                    riskBadge.className = 'history-calc-risk-badge mild';
                } else if (packYears < 20) {
                    riskBadge.textContent = 'Moderate Risk (10–19.9 pack-years)';
                    riskBadge.className = 'history-calc-risk-badge moderate';
                } else {
                    riskBadge.textContent = 'High Risk (≥ 20 pack-years)';
                    riskBadge.className = 'history-calc-risk-badge high';
                }
            }
            return packYears;
        }

        if (sticksInput) {
            sticksInput.addEventListener('input', () => {
                const sticks = parseFloat(sticksInput.value) || 0;
                if (packsInput) packsInput.value = Math.round((sticks / 20) * 10) / 10;
                calculate();
            });
        }

        if (packsInput) {
            packsInput.addEventListener('input', () => {
                const packs = parseFloat(packsInput.value) || 0;
                if (sticksInput) sticksInput.value = Math.round(packs * 20);
                calculate();
            });
        }

        if (yearsInput) {
            yearsInput.addEventListener('input', calculate);
        }

        function openModal() {
            modal.hidden = false;
            modal.removeAttribute('hidden');
            modal.style.display = 'flex';
            calculate();
            if (sticksInput) {
                setTimeout(() => sticksInput.focus(), 50);
            }
        }

        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('hidden', '');
            modal.style.display = 'none';
        }

        document.addEventListener('click', (e) => {
            const openBtn = e.target.closest('[data-history-packyear-open]');
            if (openBtn) {
                e.preventDefault();
                e.stopPropagation();
                openModal();
                return;
            }

            const closeBtn = e.target.closest('[data-history-packyear-close]');
            if (closeBtn) {
                e.preventDefault();
                closeModal();
                return;
            }
        });

        if (applyBtn) {
            applyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const py = calculate();
                const smokingCb = document.querySelector('input[data-history-field="habit"][data-history-label="Smoking"]');
                if (smokingCb) {
                    smokingCb.checked = true;
                    const item = smokingCb.closest('.history-habit-item');
                    const qtyWrap = item?.querySelector('.history-habit-qty-wrap');
                    const calcBtn = item?.querySelector('.history-calc-btn');
                    if (qtyWrap) {
                        qtyWrap.style.display = 'inline-flex';
                        const input = qtyWrap.querySelector('.history-habit-qty-input');
                        if (input) {
                            input.value = py % 1 === 0 ? py.toString() : py.toFixed(1);
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                    if (calcBtn) {
                        calcBtn.style.display = 'inline-flex';
                    }
                    smokingCb.dispatchEvent(new Event('change', { bubbles: true }));
                }
                closeModal();
            });
        }
    }

    function initAlcoholCalculator() {
        const modal = document.getElementById('alcohol-calc-modal');
        if (!modal) return;

        const volumeInput = document.getElementById('alcohol-calc-volume');
        const abvInput = document.getElementById('alcohol-calc-abv');
        const drinksInput = document.getElementById('alcohol-calc-drinks');
        const daysInput = document.getElementById('alcohol-calc-days');
        const resultNum = document.getElementById('alcohol-calc-result');
        const resultUnit = document.getElementById('alcohol-calc-unit');
        const riskBadge = document.getElementById('alcohol-calc-risk');
        const applyBtn = document.getElementById('alcohol-calc-apply-btn');
        const presetBtns = modal.querySelectorAll('[data-alcohol-preset]');

        function calculate() {
            const vol = parseFloat(volumeInput?.value) || 0;
            const abv = parseFloat(abvInput?.value) || 0;
            const drinks = parseFloat(drinksInput?.value) || 0;
            const days = parseFloat(daysInput?.value) || 0;

            const unitsPerDrink = (vol * abv) / 1000;
            const totalWeekly = Math.round(unitsPerDrink * drinks * days * 10) / 10;

            if (resultNum) resultNum.textContent = totalWeekly.toFixed(1);
            if (resultUnit) {
                resultUnit.textContent = totalWeekly === 1 ? 'Unit/Week' : 'Units/Week';
            }

            if (riskBadge) {
                if (totalWeekly === 0) {
                    riskBadge.textContent = 'No Exposure (0 units/week)';
                    riskBadge.className = 'history-calc-risk-badge';
                } else if (totalWeekly <= 14) {
                    riskBadge.textContent = 'Low Risk (≤ 14 units/week - Safe Limit)';
                    riskBadge.className = 'history-calc-risk-badge mild';
                } else if (totalWeekly <= 35) {
                    riskBadge.textContent = 'Moderate Risk (15–35 units/week)';
                    riskBadge.className = 'history-calc-risk-badge moderate';
                } else {
                    riskBadge.textContent = 'High / Harmful Risk (> 35 units/week)';
                    riskBadge.className = 'history-calc-risk-badge high';
                }
            }
            return totalWeekly;
        }

        const drinksHint = document.getElementById('alcohol-calc-drinks-hint');

        presetBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                presetBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const p = btn.dataset.alcoholPreset;
                if (p === 'beer' || p === 'hunter') {
                    if (volumeInput) volumeInput.value = 500;
                    if (abvInput) abvInput.value = 5;
                    if (drinksHint) drinksHint.textContent = 'e.g. 2 cans/session';
                } else if (p === 'wine') {
                    if (volumeInput) volumeInput.value = 175;
                    if (abvInput) abvInput.value = 13;
                    if (drinksHint) drinksHint.textContent = 'e.g. 2 glasses/session';
                } else if (p === 'vodka') {
                    if (volumeInput) volumeInput.value = 30;
                    if (abvInput) abvInput.value = 40;
                    if (drinksHint) drinksHint.textContent = 'e.g. 2 shots/session';
                } else if (p === 'spirits' || p === 'whisky') {
                    if (volumeInput) volumeInput.value = 30;
                    if (abvInput) abvInput.value = 40;
                    if (drinksHint) drinksHint.textContent = 'e.g. 2 pegs/session';
                } else if (p === 'carew') {
                    if (volumeInput) volumeInput.value = 30;
                    if (abvInput) abvInput.value = 42.8;
                    if (drinksHint) drinksHint.textContent = 'e.g. 2 pegs/session';
                } else if (p === 'bangla') {
                    if (volumeInput) volumeInput.value = 30;
                    if (abvInput) abvInput.value = 40;
                    if (drinksHint) drinksHint.textContent = 'e.g. 2 pegs/session';
                } else if (p === 'tari') {
                    if (volumeInput) volumeInput.value = 250;
                    if (abvInput) abvInput.value = 5;
                    if (drinksHint) drinksHint.textContent = 'e.g. 2 glasses/session';
                } else if (p === 'custom') {
                    if (drinksHint) drinksHint.textContent = 'Number of drinks';
                }
                calculate();
            });
        });

        [volumeInput, abvInput, drinksInput, daysInput].forEach(inp => {
            if (inp) {
                inp.addEventListener('input', () => {
                    calculate();
                });
            }
        });

        document.querySelectorAll('.history-calc-info-pill').forEach(pill => {
            pill.addEventListener('click', (e) => {
                e.preventDefault();
                const ml = parseFloat(pill.dataset.guideMl);
                if (ml && volumeInput) {
                    volumeInput.value = ml;
                    presetBtns.forEach(b => b.classList.remove('active'));
                    const customBtn = document.querySelector('[data-alcohol-preset="custom"]');
                    if (customBtn) customBtn.classList.add('active');
                    calculate();
                }
            });
        });

        function openModal() {
            modal.hidden = false;
            modal.removeAttribute('hidden');
            modal.style.display = 'flex';
            calculate();
            if (drinksInput) {
                setTimeout(() => drinksInput.focus(), 50);
            }
        }

        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('hidden', '');
            modal.style.display = 'none';
        }

        document.addEventListener('click', (e) => {
            const openBtn = e.target.closest('[data-history-alcohol-open]');
            if (openBtn) {
                e.preventDefault();
                e.stopPropagation();
                openModal();
                return;
            }

            const closeBtn = e.target.closest('[data-history-alcohol-close]');
            if (closeBtn) {
                e.preventDefault();
                closeModal();
                return;
            }
        });

        if (applyBtn) {
            applyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const total = calculate();
                const alcoholCb = document.querySelector('input[data-history-field="habit"][data-history-label="Alcohol Consumption"]');
                if (alcoholCb) {
                    alcoholCb.checked = true;
                    const item = alcoholCb.closest('.history-habit-item');
                    const qtyWrap = item?.querySelector('.history-habit-qty-wrap');
                    const calcBtn = item?.querySelector('.history-calc-btn');
                    if (qtyWrap) {
                        qtyWrap.style.display = 'inline-flex';
                        const input = qtyWrap.querySelector('.history-habit-qty-input');
                        if (input) {
                            input.value = total % 1 === 0 ? total.toString() : total.toFixed(1);
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                    if (calcBtn) {
                        calcBtn.style.display = 'inline-flex';
                    }
                    alcoholCb.dispatchEvent(new Event('change', { bubbles: true }));
                }
                closeModal();
            });
        }
    }

    wireRepeatingRows({
        wrapperId: 'history-treatment-wrapper',
        tbodyId: 'history-treatment-tbody',
        templateId: 'history-treatment-row-template',
        addSelector: '.history-treatment-add-row-btn'
    });
    wireRepeatingRows({
        wrapperId: 'dh-wrapper',
        tbodyId: 'dh-tbody',
        templateId: 'dh-row-template',
        addSelector: '.dh-add-row-btn'
    });
    initHypersensitivityChips();
    initDietSelectPlaceholder();
    initDrugAutocomplete();
    initHistoryAccordions();
    initHabitQuantifiers();
    initPackYearCalculator();
    initAlcoholCalculator();
    initMedicalHistorySettings();

    function initMedicalHistorySettings() {
        const settingsBtn = document.getElementById('history-med-settings-btn');
        const modal = document.getElementById('history-med-settings-modal');
        if (!settingsBtn || !modal) return;

        const searchInput = document.getElementById('med-settings-search');
        const catListContainer = document.getElementById('med-settings-cat-list');
        const catTotalBadge = document.getElementById('med-cat-total-badge');
        const sectionTitle = document.getElementById('med-table-section-title');
        const sectionCount = document.getElementById('med-table-section-count');
        const tbody = document.getElementById('med-settings-tbody');
        const saveBtn = document.getElementById('med-settings-save-btn');
        const resetBtn = document.getElementById('med-settings-reset-btn');
        const toggleAddBtn = document.getElementById('med-toggle-add-btn');
        const addCard = document.getElementById('med-add-card');
        const addCardClose = document.getElementById('med-add-card-close');
        const addCustomBtn = document.getElementById('med-add-custom-btn');
        const customTypeSelect = document.getElementById('med-custom-field-type');
        const customChipWrap = document.getElementById('med-custom-options-chip-wrap');
        const customPlaceholderWrap = document.getElementById('med-custom-placeholder-wrap');
        const existingCatDatalist = document.getElementById('med-existing-categories');
        const groupsContainer = document.getElementById('history-medical-groups-container');

        // Presets Sub-Modal elements
        const presetsModal = document.getElementById('history-med-presets-modal');
        const presetsModalTitle = document.getElementById('med-presets-modal-title');
        const presetsDoneBtn = document.getElementById('med-presets-done-btn');
        let activeModalCondition = null;
        let activeModalChips = [];

        // Custom condition chip list
        let customChips = [];

        let currentConfig = null;
        try {
            const initialElem = document.getElementById('zimrxInitialMedHistoryConfig');
            if (initialElem) {
                currentConfig = JSON.parse(initialElem.textContent || '{}');
            }
        } catch (e) {
            currentConfig = null;
        }

        if (!currentConfig || !Array.isArray(currentConfig.conditions)) {
            currentConfig = { categories: [], conditions: [], active_groups: {} };
        }

        let activeCategory = 'All';
        let searchQuery = '';

        // Tag and chip manager with drag-and-drop support
        function createChipManager(containerEl) {
            if (!containerEl) return null;
            const listEl = containerEl.querySelector('.history-med-chip-list');
            const inputEl = containerEl.querySelector('.history-med-chip-input');
            const addBtn = containerEl.querySelector('.history-med-chip-add-btn');
            if (!listEl) return null;

            let items = [];
            let changeCb = null;
            let dragChip = null;

            function render() {
                if (!items || items.length === 0) {
                    listEl.innerHTML = '<span class="hist-item-hint">No presets yet. Type below and press Enter to add.</span>';
                } else {
                    listEl.innerHTML = items.map((opt, idx) => `
                        <div class="history-med-chip" data-index="${idx}" title="Drag handle to reorder &bull; Click text to edit">
                            <span class="history-med-chip-handle" title="Drag to reorder">⋮⋮</span>
                            <span class="history-med-chip-text" contenteditable="true" spellcheck="false">${escapeHtml(opt)}</span>
                            <button type="button" class="history-med-chip-del" data-index="${idx}" title="Remove preset">&times;</button>
                        </div>
                    `).join('');
                }
                if (typeof changeCb === 'function') {
                    changeCb([...items]);
                }
            }

            function addOption() {
                if (!inputEl) return;
                const val = inputEl.value.trim();
                if (!val) return;
                const parts = val.split(',').map(s => s.trim()).filter(Boolean);
                let added = false;
                parts.forEach(p => {
                    if (p && !items.includes(p)) {
                        items.push(p);
                        added = true;
                    }
                });
                inputEl.value = '';
                if (added) render();
                inputEl.focus();
            }

            if (inputEl) {
                inputEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        addOption();
                    }
                });
            }

            if (addBtn) {
                addBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    addOption();
                });
            }

            // Remove chip
            listEl.addEventListener('click', (e) => {
                const del = e.target.closest('.history-med-chip-del');
                if (del) {
                    e.preventDefault();
                    e.stopPropagation();
                    const idx = parseInt(del.dataset.index, 10);
                    if (!isNaN(idx) && idx >= 0 && idx < items.length) {
                        items.splice(idx, 1);
                        render();
                    }
                }
            });

            // Inline text edit
            listEl.addEventListener('input', (e) => {
                const textEl = e.target.closest('.history-med-chip-text');
                if (textEl) {
                    const chip = textEl.closest('.history-med-chip');
                    const idx = parseInt(chip?.dataset.index, 10);
                    if (!isNaN(idx) && idx >= 0 && idx < items.length) {
                        items[idx] = textEl.textContent.trim();
                        if (typeof changeCb === 'function') changeCb([...items]);
                    }
                }
            });

            listEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.target.blur();
                }
            });

            // Drag and drop reordering
            containerEl.addEventListener('mousedown', (e) => {
                const handle = e.target.closest('.history-med-chip-handle');
                const chip = e.target.closest('.history-med-chip');
                if (chip) {
                    chip.draggable = !!handle;
                }
            });

            listEl.addEventListener('dragstart', (e) => {
                const chip = e.target.closest('.history-med-chip');
                if (!chip || !chip.draggable) {
                    e.preventDefault();
                    return;
                }
                dragChip = chip;
                chip.classList.add('dragging');
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', chip.dataset.index || '');
                }
            });

            listEl.addEventListener('dragover', (e) => {
                if (!dragChip) return;
                e.preventDefault();
                if (e.dataTransfer) {
                    e.dataTransfer.dropEffect = 'move';
                }
                const targetChip = e.target.closest('.history-med-chip');
                if (!targetChip || targetChip === dragChip || targetChip.parentElement !== listEl) return;

                const rect = targetChip.getBoundingClientRect();
                const isAfter = (e.clientX > rect.left + rect.width / 2) || (e.clientY > rect.bottom - 4);
                listEl.insertBefore(dragChip, isAfter ? targetChip.nextSibling : targetChip);
            });

            function syncFromDom() {
                const chipTexts = Array.from(listEl.querySelectorAll('.history-med-chip .history-med-chip-text'))
                    .map(el => el.textContent.trim())
                    .filter(Boolean);
                items = chipTexts;
                render();
            }

            function cleanupDrag() {
                listEl.querySelectorAll('.history-med-chip').forEach(c => {
                    c.classList.remove('dragging', 'drag-over');
                    c.draggable = false;
                });
                dragChip = null;
            }

            listEl.addEventListener('drop', (e) => {
                e.preventDefault();
                if (!dragChip) return;
                cleanupDrag();
                syncFromDom();
            });

            listEl.addEventListener('dragend', () => {
                cleanupDrag();
                syncFromDom();
            });

            document.addEventListener('dragend', cleanupDrag);
            document.addEventListener('mouseup', cleanupDrag);

            return {
                setItems(newItems, callback) {
                    items = Array.isArray(newItems) ? [...newItems] : [];
                    changeCb = callback || null;
                    cleanupDrag();
                    render();
                },
                getItems() {
                    return [...items];
                }
            };
        }

        const customChipManager = createChipManager(document.getElementById('med-custom-chip-container'));
        const modalChipManager = createChipManager(document.getElementById('med-modal-chip-container'));

        // Condition type switcher (dropdown text vs textbox)
        if (customTypeSelect) {
            customTypeSelect.addEventListener('change', () => {
                const val = customTypeSelect.value;
                if (customChipWrap) {
                    customChipWrap.style.display = val === 'dropdown_text' ? 'block' : 'none';
                }
                if (customPlaceholderWrap) {
                    customPlaceholderWrap.style.display = val === 'textbox' ? 'block' : 'none';
                }
            });
        }

        function refreshCustomChipContainer() {
            if (customChipManager) {
                customChipManager.setItems(customChips, (arr) => { customChips = arr; });
            }
        }

        // Quick grid live value sync and dropdown positioning
        let activeMedDropdown = null;

        function closeActiveMedDropdown() {
            if (activeMedDropdown) {
                activeMedDropdown.remove();
                activeMedDropdown = null;
            }
        }

        function autoResizeHistoryInput(el) {
            if (!el) return;
            el.style.transition = 'none';
            el.style.height = '0';
            const natural = Math.max(22, el.scrollHeight);
            el.style.height = natural + 'px';
            requestAnimationFrame(() => { el.style.transition = ''; });
        }

        function showMedDropdown(itemEl, inputEl, filterQuery = '') {
            closeActiveMedDropdown();
            if (!itemEl || !inputEl) return;

            const rawOptions = itemEl.dataset.options || '';
            let options = rawOptions.split(',').map(s => s.trim()).filter(Boolean);
            if (options.length === 0) return;

            if (filterQuery) {
                const q = filterQuery.toLowerCase();
                const filtered = options.filter(opt => opt.toLowerCase().includes(q));
                if (filtered.length > 0) options = filtered;
            }

            const wrap = inputEl.closest('.history-med-input-wrap');
            if (!wrap) return;

            const dropdown = document.createElement('ul');
            dropdown.className = 'history-med-dropdown rx-dropdown';

            const rect = wrap.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top = (rect.bottom + 2) + 'px';
            dropdown.style.left = rect.left + 'px';
            dropdown.style.minWidth = Math.max(160, rect.width) + 'px';
            dropdown.style.zIndex = '99999';

            options.forEach(opt => {
                const li = document.createElement('li');
                li.className = 'rx-dropdown-item';
                li.textContent = opt;
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    inputEl.value = opt;
                    autoResizeHistoryInput(inputEl);
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                    inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                    closeActiveMedDropdown();
                });
                dropdown.appendChild(li);
            });

            document.body.appendChild(dropdown);
            activeMedDropdown = dropdown;

            const dropRect = dropdown.getBoundingClientRect();
            if (dropRect.bottom > window.innerHeight) {
                dropdown.style.top = Math.max(10, rect.top - dropRect.height - 2) + 'px';
            }
        }

        document.addEventListener('click', (e) => {
            if (activeMedDropdown && !activeMedDropdown.contains(e.target) && !e.target.closest('.history-med-dropdown-btn') && !e.target.closest('.history-med-value-input')) {
                closeActiveMedDropdown();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && activeMedDropdown) {
                closeActiveMedDropdown();
            }
        });

        if (groupsContainer) {
            groupsContainer.addEventListener('change', (e) => {
                const cb = e.target.closest('input[data-history-field="medical"]');
                if (!cb) return;
                const item = cb.closest('.history-med-item');
                const inputWrap = item?.querySelector('.history-med-input-wrap');
                const valInput = inputWrap?.querySelector('.history-med-value-input');
                if (inputWrap) {
                    if (cb.checked) {
                        inputWrap.style.display = 'inline-flex';
                        if (valInput) {
                            autoResizeHistoryInput(valInput);
                            setTimeout(() => valInput.focus(), 30);
                        }
                    } else {
                        inputWrap.style.display = 'none';
                        closeActiveMedDropdown();
                        if (valInput) {
                            valInput.value = '';
                            valInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                }
            });

            groupsContainer.addEventListener('input', (e) => {
                const valInput = e.target.closest('.history-med-value-input');
                if (valInput) {
                    autoResizeHistoryInput(valInput);
                    const item = valInput.closest('.history-med-item');
                    if (item && item.dataset.fieldType === 'dropdown_text') {
                        showMedDropdown(item, valInput, valInput.value.trim());
                    }
                }
            });

            groupsContainer.addEventListener('click', (e) => {
                const dropBtn = e.target.closest('.history-med-dropdown-btn');
                if (dropBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const item = dropBtn.closest('.history-med-item');
                    const valInput = item?.querySelector('.history-med-value-input');
                    if (item && valInput) {
                        if (activeMedDropdown) {
                            closeActiveMedDropdown();
                        } else {
                            showMedDropdown(item, valInput, '');
                        }
                    }
                    return;
                }

                const valInput = e.target.closest('.history-med-value-input');
                if (valInput) {
                    const item = valInput.closest('.history-med-item');
                    if (item && item.dataset.fieldType === 'dropdown') {
                        showMedDropdown(item, valInput, '');
                    }
                }
            });
        }

        function openModal() {
            modal.hidden = false;
            modal.removeAttribute('hidden');
            modal.style.display = 'flex';
            if (addCard) addCard.style.display = 'none';
            customChips = [];
            refreshCustomChipContainer();
            renderCategories();
            renderTableRows();
            if (searchInput) {
                searchInput.value = '';
                searchQuery = '';
                setTimeout(() => searchInput.focus(), 40);
            }
        }

        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('hidden', '');
            modal.style.display = 'none';
        }

        settingsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openModal();
        });

        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-med-settings-close]')) {
                e.preventDefault();
                closeModal();
            }
            if (e.target.closest('[data-med-presets-close]')) {
                e.preventDefault();
                closePresetsModal();
            }
        });

        // Settings modal tab switching
        modal.querySelectorAll('.history-settings-tab-btn').forEach(tabBtn => {
            tabBtn.addEventListener('click', () => {
                const targetTab = tabBtn.dataset.historyTab;
                modal.querySelectorAll('.history-settings-tab-btn').forEach(b => b.classList.remove('active'));
                tabBtn.classList.add('active');

                modal.querySelectorAll('.history-tab-pane').forEach(pane => {
                    pane.style.display = 'none';
                    pane.classList.remove('active');
                });

                const targetPane = document.getElementById(`history-tab-pane-${targetTab}`);
                if (targetPane) {
                    targetPane.style.display = 'flex';
                    targetPane.classList.add('active');
                }
            });
        });

        if (toggleAddBtn && addCard) {
            toggleAddBtn.addEventListener('click', () => {
                const isHidden = addCard.style.display === 'none';
                addCard.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    customChips = [];
                    refreshCustomChipContainer();
                    const labelInput = document.getElementById('med-custom-label');
                    if (labelInput) setTimeout(() => labelInput.focus(), 40);
                }
            });
        }

        if (addCardClose && addCard) {
            addCardClose.addEventListener('click', () => {
                addCard.style.display = 'none';
            });
        }

        // Presets sub-modal management
        function openPresetsModal(condKey) {
            const cond = (currentConfig.conditions || []).find(c => c.condition_key === condKey);
            if (!cond || !presetsModal) return;
            activeModalCondition = cond;

            if (presetsModalTitle) {
                presetsModalTitle.textContent = `Presets for: ${cond.display_label || cond.condition_key}`;
            }

            activeModalChips = (cond.dropdown_options || '')
                .split(',')
                .map(s => s.trim())
                .filter(Boolean);

            if (modalChipManager) {
                modalChipManager.setItems(activeModalChips, (arr) => {
                    activeModalChips = arr;
                });
            }

            presetsModal.hidden = false;
            presetsModal.removeAttribute('hidden');
            presetsModal.style.display = 'flex';
        }

        function closePresetsModal() {
            if (activeModalCondition) {
                const updatedList = modalChipManager ? modalChipManager.getItems() : activeModalChips;
                activeModalCondition.dropdown_options = updatedList.join(', ');
                updateTableRowPresetCell(activeModalCondition.condition_key);
            }
            if (presetsModal) {
                presetsModal.hidden = true;
                presetsModal.setAttribute('hidden', '');
                presetsModal.style.display = 'none';
            }
            activeModalCondition = null;
            activeModalChips = [];
        }

        if (presetsDoneBtn) {
            presetsDoneBtn.addEventListener('click', () => {
                closePresetsModal();
            });
        }

        function updateTableRowPresetCell(condKey) {
            const cond = (currentConfig.conditions || []).find(c => c.condition_key === condKey);
            if (!cond || !tbody) return;
            const tr = tbody.querySelector(`tr[data-key="${condKey}"]`);
            if (!tr) return;
            const cell = tr.querySelector('.med-row-config-cell');
            if (!cell) return;

            const optCount = (cond.dropdown_options || '').split(',').map(s => s.trim()).filter(Boolean).length;
            if (cond.field_type === 'dropdown_text') {
                cell.innerHTML = `
                    <button type="button" class="history-med-presets-badge-btn ${optCount > 0 ? '' : 'empty'}" data-key="${escapeHtml(cond.condition_key)}">
                        ⚙ ${optCount} Presets
                    </button>
                `;
            } else if (cond.field_type === 'textbox') {
                cell.innerHTML = `
                    <input type="text" class="med-row-placeholder" value="${escapeHtml(cond.placeholder || '')}" data-key="${escapeHtml(cond.condition_key)}" placeholder="e.g. Duration / notes hint">
                `;
            } else {
                cell.innerHTML = `<span class="hist-muted-sm">&mdash;</span>`;
            }
        }

        function getCategories() {
            const cats = [];
            (currentConfig.conditions || []).forEach(c => {
                const cat = (c.category || '').trim();
                if (cat && !cats.includes(cat)) cats.push(cat);
            });
            return cats;
        }

        function renderCategories() {
            if (!catListContainer) return;
            const cats = getCategories();

            if (existingCatDatalist) {
                existingCatDatalist.innerHTML = cats.map(c => `<option value="${escapeHtml(c)}"></option>`).join('');
            }

            const totalCount = (currentConfig.conditions || []).length;
            const totalActive = (currentConfig.conditions || []).filter(c => c.is_active === 1).length;

            if (catTotalBadge) {
                catTotalBadge.textContent = `${totalActive}/${totalCount}`;
            }

            let html = `
                <button type="button" class="history-med-cat-item ${activeCategory === 'All' ? 'active' : ''}" data-cat="All">
                    <span>All Conditions</span>
                    <span class="history-med-cat-count">${totalActive}/${totalCount}</span>
                </button>
            `;

            cats.forEach(cat => {
                const catItems = (currentConfig.conditions || []).filter(c => c.category === cat);
                const count = catItems.length;
                const activeCount = catItems.filter(c => c.is_active === 1).length;
                html += `
                    <button type="button" class="history-med-cat-item ${activeCategory === cat ? 'active' : ''}" data-cat="${escapeHtml(cat)}">
                        <span>${escapeHtml(cat)}</span>
                        <span class="history-med-cat-count">${activeCount}/${count}</span>
                    </button>
                `;
            });

            catListContainer.innerHTML = html;
        }

        if (catListContainer) {
            catListContainer.addEventListener('click', (e) => {
                const btn = e.target.closest('.history-med-cat-item');
                if (!btn) return;
                activeCategory = btn.dataset.cat || 'All';
                renderCategories();
                renderTableRows();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                searchQuery = (searchInput.value || '').trim().toLowerCase();
                renderTableRows();
            });
        }

        function renderTableRows() {
            if (!tbody) return;
            const q = searchQuery;
            const cat = activeCategory;

            const filtered = (currentConfig.conditions || []).filter(c => {
                if (cat !== 'All' && c.category !== cat) return false;
                if (q) {
                    const hay = `${c.category} ${c.condition_key} ${c.display_label} ${c.full_name} ${c.dropdown_options}`.toLowerCase();
                    if (!hay.includes(q)) return false;
                }
                return true;
            });

            const activeInView = filtered.filter(c => c.is_active === 1).length;

            if (sectionTitle) {
                sectionTitle.textContent = cat === 'All' ? (q ? `Search Results for "${q}"` : 'All Conditions') : cat;
            }
            if (sectionCount) {
                sectionCount.textContent = `(${activeInView} active of ${filtered.length} shown)`;
            }

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="hist-empty-cell">No matching medical history conditions found.</td></tr>`;
                return;
            }

            tbody.innerHTML = filtered.map((c) => {
                const key = escapeHtml(c.condition_key);
                const isChecked = c.is_active === 1 ? 'checked' : '';
                const rowClass = c.is_active === 1 ? '' : 'row-hidden';
                const optCount = (c.dropdown_options || '').split(',').map(s => s.trim()).filter(Boolean).length;

                let configHtml = '';
                if (c.field_type === 'dropdown_text') {
                    configHtml = `
                        <button type="button" class="history-med-presets-badge-btn ${optCount > 0 ? '' : 'empty'}" data-key="${key}">
                            ⚙ ${optCount} Presets
                        </button>
                    `;
                } else if (c.field_type === 'textbox') {
                    configHtml = `
                        <input type="text" class="med-row-placeholder" value="${escapeHtml(c.placeholder || '')}" data-key="${key}" placeholder="e.g. Duration / notes hint">
                    `;
                } else {
                    configHtml = `<span class="hist-muted-sm">&mdash;</span>`;
                }

                return `
                    <tr class="${rowClass}" data-key="${key}">
                        <td class="hist-center-cell" class="med-row-drag-cell">
                            <button type="button" class="med-row-drag-handle" title="Drag to reorder condition">⋮⋮</button>
                        </td>
                        <td class="hist-center-cell">
                            <input type="checkbox" class="med-row-active" ${isChecked} data-key="${key}" title="Show in OPD quick grid">
                        </td>
                        <td>
                            <input type="text" class="med-row-label" value="${escapeHtml(c.display_label || '')}" data-key="${key}" placeholder="Label on Rx">
                        </td>
                        <td>
                            <input type="text" class="med-row-cat" value="${escapeHtml(c.category || '')}" data-key="${key}" list="med-existing-categories">
                        </td>
                        <td>
                            <select class="med-row-field-type" data-key="${key}">
                                <option value="dropdown_text" ${c.field_type === 'dropdown_text' ? 'selected' : ''}>With Options List</option>
                                <option value="textbox" ${c.field_type === 'textbox' ? 'selected' : ''}>With Note Box</option>
                                <option value="none" ${c.field_type === 'none' ? 'selected' : ''}>Quick Checkbox</option>
                            </select>
                        </td>
                        <td class="med-row-config-cell">
                            ${configHtml}
                        </td>
                        <td class="hist-center-cell">
                            ${c.is_custom === 1 ? `
                                <button type="button" class="history-med-del-btn" data-key="${key}" title="Delete custom condition">&times;</button>
                            ` : `
                                <span class="hist-muted-xs" title="Static Master Catalog Condition">&#x25CB;</span>
                            `}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        let draggedMedRow = null;
        let draggedMedKey = null;

        function syncConditionsOrderFromDom() {
            if (!tbody) return;
            const rowKeys = Array.from(tbody.querySelectorAll('tr[data-key]')).map(r => r.dataset.key);
            if (rowKeys.length === 0) return;

            const conditionMap = {};
            (currentConfig.conditions || []).forEach(c => {
                conditionMap[c.condition_key] = c;
            });

            const newConditions = [];
            rowKeys.forEach((key, idx) => {
                if (conditionMap[key]) {
                    const c = conditionMap[key];
                    c.sort_order = (idx + 1) * 10;
                    newConditions.push(c);
                    delete conditionMap[key];
                }
            });

            Object.values(conditionMap).forEach(c => {
                newConditions.push(c);
            });

            currentConfig.conditions = newConditions;
        }

        if (tbody) {
            tbody.addEventListener('mousedown', (e) => {
                const handle = e.target.closest('.med-row-drag-handle');
                const tr = e.target.closest('tr');
                if (tr) {
                    tr.draggable = !!handle;
                }
            });

            tbody.addEventListener('dragstart', (e) => {
                const tr = e.target.closest('tr');
                if (!tr || !tr.draggable) {
                    e.preventDefault();
                    return;
                }
                draggedMedRow = tr;
                draggedMedKey = tr.dataset.key;
                tr.classList.add('row-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggedMedKey || '');
            });

            tbody.addEventListener('dragover', (e) => {
                if (!draggedMedRow) return;
                const targetTr = e.target.closest('tr');
                if (!targetTr || targetTr === draggedMedRow || targetTr.parentElement !== tbody) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                const rect = targetTr.getBoundingClientRect();
                const insertAfter = e.clientY > rect.top + rect.height / 2;
                tbody.insertBefore(draggedMedRow, insertAfter ? targetTr.nextSibling : targetTr);
            });

            tbody.addEventListener('drop', (e) => {
                e.preventDefault();
                if (!draggedMedRow || !draggedMedKey) return;
                syncConditionsOrderFromDom();
            });

            tbody.addEventListener('dragend', () => {
                if (draggedMedRow) {
                    draggedMedRow.classList.remove('row-dragging');
                    draggedMedRow.draggable = false;
                }
                draggedMedRow = null;
                draggedMedKey = null;
            });

            tbody.addEventListener('change', (e) => {
                const target = e.target;
                const key = target.dataset.key;
                if (!key) return;
                const item = (currentConfig.conditions || []).find(c => c.condition_key === key);
                if (!item) return;

                if (target.classList.contains('med-row-active')) {
                    item.is_active = target.checked ? 1 : 0;
                    const tr = target.closest('tr');
                    if (tr) tr.className = item.is_active === 1 ? '' : 'row-hidden';
                    renderCategories();
                    const activeInView = (currentConfig.conditions || []).filter(c => (activeCategory === 'All' || c.category === activeCategory) && c.is_active === 1).length;
                    const totalInView = (currentConfig.conditions || []).filter(c => activeCategory === 'All' || c.category === activeCategory).length;
                    if (sectionCount) sectionCount.textContent = `(${activeInView} active of ${totalInView} shown)`;
                } else if (target.classList.contains('med-row-cat')) {
                    item.category = target.value.trim() || 'Other';
                    renderCategories();
                } else if (target.classList.contains('med-row-label')) {
                    item.display_label = target.value.trim() || item.condition_key;
                } else if (target.classList.contains('med-row-field-type')) {
                    item.field_type = target.value;
                    updateTableRowPresetCell(key);
                } else if (target.classList.contains('med-row-placeholder')) {
                    item.placeholder = target.value.trim();
                }
            });

            tbody.addEventListener('click', (e) => {
                const presetBtn = e.target.closest('.history-med-presets-badge-btn');
                if (presetBtn) {
                    const key = presetBtn.dataset.key;
                    if (key) openPresetsModal(key);
                    return;
                }

                const delBtn = e.target.closest('.history-med-del-btn');
                if (delBtn) {
                    const key = delBtn.dataset.key;
                    if (!key) return;
                    currentConfig.conditions = (currentConfig.conditions || []).filter(c => c.condition_key !== key);
                    renderCategories();
                    renderTableRows();
                }
            });
        }

        if (addCustomBtn) {
            addCustomBtn.addEventListener('click', () => {
                const catInput = document.getElementById('med-custom-category');
                const labelInput = document.getElementById('med-custom-label');
                const typeInput = document.getElementById('med-custom-field-type');
                const placeholderInput = document.getElementById('med-custom-placeholder');

                const label = (labelInput?.value || '').trim();
                if (!label) {
                    alert('Please enter a display label for the condition.');
                    labelInput?.focus();
                    return;
                }

                let key = label.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                const exists = (currentConfig.conditions || []).some(c => c.condition_key === key);
                if (exists) {
                    key = key + '_' + Math.floor(Math.random() * 1000);
                }

                const category = (catInput?.value || '').trim() || 'Other';
                const fieldType = typeInput?.value || 'dropdown_text';
                const dropdownOptions = (customChipManager ? customChipManager.getItems() : customChips).join(', ');
                const placeholder = (placeholderInput?.value || '').trim();

                const newCondition = {
                    id: 0,
                    static_id: 0,
                    condition_key: key,
                    category: category,
                    display_label: label,
                    full_name: label,
                    field_type: fieldType,
                    dropdown_options: dropdownOptions,
                    placeholder: placeholder,
                    is_active: 1,
                    sort_order: (currentConfig.conditions.length + 1) * 10,
                    is_custom: 1,
                    is_default_active: 0
                };

                currentConfig.conditions.push(newCondition);

                if (labelInput) labelInput.value = '';
                if (placeholderInput) placeholderInput.value = '';
                customChips = [];
                refreshCustomChipContainer();
                if (addCard) addCard.style.display = 'none';

                activeCategory = category;
                renderCategories();
                renderTableRows();
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                const originalText = saveBtn.textContent;
                saveBtn.textContent = 'Saving...';

                try {
                    const resp = await fetch('api/medical_history_settings.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'save_config',
                            items: currentConfig.conditions
                        })
                    });
                    const result = await resp.json();
                    if (result.success && result.data) {
                        currentConfig = result.data;
                        rebuildOpdGrid(currentConfig.active_groups || {});
                        closeModal();
                    } else {
                        alert('Error saving settings: ' + (result.error || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Network error while saving settings: ' + err.message);
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', async () => {
                if (!confirm('Are you sure you want to reset Medical History to factory defaults? All your custom visibility and condition edits will be restored.')) {
                    return;
                }

                resetBtn.disabled = true;
                resetBtn.textContent = 'Resetting...';

                try {
                    const resp = await fetch('api/medical_history_settings.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'reset_default' })
                    });
                    const result = await resp.json();
                    if (result.success && result.data) {
                        currentConfig = result.data;
                        activeCategory = 'All';
                        searchQuery = '';
                        renderCategories();
                        renderTableRows();
                        rebuildOpdGrid(currentConfig.active_groups || {});
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

        function rebuildOpdGrid(activeGroups) {
            if (!groupsContainer) return;
            const preservedChecks = {};
            groupsContainer.querySelectorAll('input[data-history-field="medical"]').forEach(cb => {
                const key = cb.dataset.conditionKey || cb.dataset.historyLabel;
                if (key && cb.checked) {
                    const itemWrap = cb.closest('.history-med-item');
                    const val = itemWrap?.querySelector('.history-med-value-input')?.value || '';
                    preservedChecks[key] = { checked: true, value: val };
                }
            });

            let html = '';
            Object.entries(activeGroups).forEach(([groupName, items]) => {
                html += `
                    <div class="history-check-group" data-category="${escapeHtml(groupName)}">
                        <div class="history-check-group-title">${escapeHtml(groupName)}</div>
                        <div class="history-check-grid">
                `;
                items.forEach(cond => {
                    const key = escapeHtml(cond.condition_key);
                    const label = escapeHtml(cond.display_label);
                    const fieldType = escapeHtml(cond.field_type || 'none');
                    const placeholder = escapeHtml(cond.placeholder || '');
                    const prev = preservedChecks[cond.condition_key] || preservedChecks[cond.display_label] || null;
                    const isChecked = prev?.checked ? 'checked' : '';
                    const prevVal = prev?.value ? escapeHtml(prev.value) : '';
                    const wrapDisplay = prev?.checked && (fieldType === 'textbox' || fieldType === 'dropdown_text' || fieldType === 'dropdown') ? 'block' : 'none';

                    const optStr = escapeHtml(cond.dropdown_options || '');
                    html += `
                        <div class="history-med-item" data-condition-key="${key}" data-field-type="${fieldType}" data-options="${optStr}">
                            <label class="history-check">
                                <input type="checkbox" data-history-field="medical" data-history-label="${label}" data-condition-key="${key}" ${isChecked}>
                                <span>${label}</span>
                            </label>
                    `;

                    if (fieldType === 'textbox') {
                        html += `
                            <div class="history-med-input-wrap" style="display: ${wrapDisplay};">
                                <textarea rows="1" class="history-med-value-input" placeholder="${placeholder || 'e.g. Details...'}">${prevVal}</textarea>
                            </div>
                        `;
                    } else if (fieldType === 'dropdown_text' || fieldType === 'dropdown') {
                        const isReadonly = fieldType === 'dropdown' ? 'readonly' : '';
                        const ph = placeholder || (fieldType === 'dropdown' ? 'Select...' : 'Select or type...');
                        html += `
                            <div class="history-med-input-wrap" style="display: ${wrapDisplay};">
                                <textarea rows="1" class="history-med-value-input" placeholder="${escapeHtml(ph)}" ${isReadonly}>${prevVal}</textarea>
                                <button type="button" class="history-med-dropdown-btn" title="Select option" tabindex="-1">
                                    <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 1l4 4 4-4"/></svg>
                                </button>
                            </div>
                        `;
                    }

                    html += `</div>`;
                });
                html += `
                        </div>
                    </div>
                `;
            });

            groupsContainer.innerHTML = html;
        }
    }
})();

