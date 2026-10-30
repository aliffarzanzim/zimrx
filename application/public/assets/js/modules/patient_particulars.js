// Patient particulars controller: age/dob calculation, referrals, and registry lookup.
(function (window, document) {
    'use strict';

    let isSyncingAgeDob = false;
    let referralSuggestionsTimer = null;
    let previousReferralsTimer = null;
    let occupationsCache = null;

    function getEl(idOrEl) {
        if (!idOrEl) return null;
        return typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
    }

    function getValue(idOrEl) {
        const el = getEl(idOrEl);
        return el ? (el.value || '').trim() : '';
    }

    function setValue(idOrEl, val) {
        const el = getEl(idOrEl);
        if (!el) return;
        el.value = val ?? '';
        if (el.tagName === 'SELECT' && el.dataset.zrxEnhanced === 'true') {
            const wrap = getEl(`zrx-wrap-${el.id}`);
            const labelSpan = wrap?.querySelector('.zrx-select-label');
            if (labelSpan) {
                const opt = el.selectedOptions[0];
                labelSpan.textContent = opt ? opt.text : '--';
            }
        }
    }

    function parseDate(value) {
        const v = String(value || '').trim();
        if (!v) return null;
        if (v.includes('/')) {
            const parts = v.split('/');
            if (parts.length === 3) {
                const day = parseInt(parts[0], 10);
                const month = parseInt(parts[1], 10) - 1;
                const year = parseInt(parts[2], 10);
                const d = new Date(year, month, day);
                return isNaN(d.getTime()) ? null : d;
            }
        }
        if (v.match(/^\d{4}-\d{2}-\d{2}/)) {
            const parts = v.slice(0, 10).split('-');
            const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return isNaN(d.getTime()) ? null : d;
        }
        const d = new Date(v);
        return isNaN(d.getTime()) ? null : d;
    }

    function formatDmy(date) {
        if (!date || !(date instanceof Date) || isNaN(date.getTime())) return '';
        const d = String(date.getDate()).padStart(2, '0');
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const y = date.getFullYear();
        return `${d}/${m}/${y}`;
    }

    function getBaseDate(baseDateElId) {
        if (baseDateElId) {
            const el = getEl(baseDateElId);
            if (el && el.value) {
                const parsed = parseDate(el.value);
                if (parsed) return parsed;
            }
        }
        const apptDate = getEl('appointment-date') || getEl('patient-date');
        if (apptDate && apptDate.value) {
            const parsed = parseDate(apptDate.value);
            if (parsed) return parsed;
        }
        return new Date();
    }

    function setDobType(type) {
        const badge = getEl('patient-dob-type-badge');
        const dobInput = getEl('patient-dob');
        const hasDob = dobInput ? Boolean((dobInput.value || '').trim()) : false;

        if (!type || !hasDob) {
            setValue('patient-dob-type', type || 'exact');
            if (badge) {
                badge.hidden = true;
                badge.style.display = 'none';
                badge.textContent = '';
            }
            return;
        }

        const normalized = type === 'estimated' ? 'estimated' : 'exact';
        setValue('patient-dob-type', normalized);
        if (badge) {
            badge.hidden = false;
            badge.style.display = 'inline';
            badge.textContent = normalized === 'estimated' ? ' (Estimated)' : ' (Exact)';
            badge.className = 'dob-type-text ' + normalized;
            badge.title = normalized === 'estimated'
                ? 'Age was approximated (click to mark Exact)'
                : 'Exact Date of Birth (click to mark Estimated)';
        }
    }

    function toggleDobType() {
        const current = getValue('patient-dob-type') || 'exact';
        setDobType(current === 'estimated' ? 'exact' : 'estimated');
    }

    function syncAgeFromDob(options = {}) {
        if (isSyncingAgeDob) return;
        const dobInput = getEl(options.dobId || 'patient-dob');
        const ageInput = getEl(options.ageId || 'patient-age');
        const ageUnit = getEl(options.unitId || 'patient-age-unit');
        if (!dobInput) return;

        let dob = null;
        if (dobInput._flatpickr && dobInput._flatpickr.selectedDates && dobInput._flatpickr.selectedDates[0]) {
            dob = dobInput._flatpickr.selectedDates[0];
        } else {
            dob = parseDate(dobInput.value);
        }
        if (!dob) {
            const ageYearsInput = getEl('patient-age-years');
            const ageMonthsInput = getEl('patient-age-months');
            const ageDaysInput = getEl('patient-age-days');
            if (ageYearsInput) ageYearsInput.value = '';
            if (ageMonthsInput) ageMonthsInput.value = '';
            if (ageDaysInput) ageDaysInput.value = '';
            if (ageInput) ageInput.value = '';
            setDobType(null);
            return;
        }

        const base = getBaseDate(options.baseDateId);
        if (dob > base) return;

        isSyncingAgeDob = true;
        let years = base.getFullYear() - dob.getFullYear();
        let months = base.getMonth() - dob.getMonth();
        let days = base.getDate() - dob.getDate();
        if (days < 0) {
            months -= 1;
            days += new Date(base.getFullYear(), base.getMonth(), 0).getDate();
        }
        if (months < 0) {
            years -= 1;
            months += 12;
        }

        const ageYearsInput = getEl('patient-age-years');
        const ageMonthsInput = getEl('patient-age-months');
        const ageDaysInput = getEl('patient-age-days');
        if (ageYearsInput) ageYearsInput.value = years > 0 ? years : (months > 0 || days > 0 ? '' : '0');
        if (ageMonthsInput) ageMonthsInput.value = months > 0 ? months : '';
        if (ageDaysInput) ageDaysInput.value = days > 0 ? days : '';

        if (ageInput && ageUnit) {
            if (years > 0) {
                ageInput.value = years;
                ageUnit.value = 'Years';
            } else if (months > 0) {
                ageInput.value = months;
                ageUnit.value = 'Months';
            } else if (days >= 7) {
                ageInput.value = Math.floor(days / 7);
                ageUnit.value = 'Weeks';
            } else {
                ageInput.value = Math.max(days, 0);
                ageUnit.value = 'Days';
            }
            ageInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        isSyncingAgeDob = false;

        if (!options.keepDobType) {
            setDobType('exact');
        } else {
            setDobType(getValue('patient-dob-type') || 'exact');
        }
    }

    function syncDobFromAgeTriple(options = {}) {
        if (isSyncingAgeDob) return;
        const dobInput = getEl(options.dobId || 'patient-dob');
        const ageYearsInput = getEl('patient-age-years');
        const ageMonthsInput = getEl('patient-age-months');
        const ageDaysInput = getEl('patient-age-days');
        const ageInput = getEl(options.ageId || 'patient-age');
        const ageUnit = getEl(options.unitId || 'patient-age-unit');
        if (!dobInput || !ageYearsInput) return;

        const rawY = ageYearsInput.value.trim();
        const rawM = ageMonthsInput ? ageMonthsInput.value.trim() : '';
        const rawD = ageDaysInput ? ageDaysInput.value.trim() : '';

        if (rawY === '' && rawM === '' && rawD === '') {
            isSyncingAgeDob = true;
            if (dobInput._flatpickr) {
                dobInput._flatpickr.clear();
            } else {
                dobInput.value = '';
            }
            if (ageInput) ageInput.value = '';
            setDobType(null);
            isSyncingAgeDob = false;
            return;
        }

        const parseAgePart = (raw, max) => {
            if (raw === '') return 0;
            if (!/^\d+$/.test(raw)) return null;
            const value = Number(raw);
            return Number.isInteger(value) && value <= max ? value : null;
        };
        const years = parseAgePart(rawY, 150);
        const months = parseAgePart(rawM, 11);
        const days = parseAgePart(rawD, 30);
        if (years === null || months === null || days === null) return;

        isSyncingAgeDob = true;
        const base = getBaseDate(options.baseDateId);
        const dob = new Date(base);
        dob.setDate(dob.getDate() - days);
        const dayOfMonth = dob.getDate();
        dob.setDate(1);
        dob.setMonth(dob.getMonth() - (years * 12 + months));
        const lastDayOfTargetMonth = new Date(dob.getFullYear(), dob.getMonth() + 1, 0).getDate();
        dob.setDate(Math.min(dayOfMonth, lastDayOfTargetMonth));

        if (dobInput._flatpickr) {
            dobInput._flatpickr.setDate(dob, false);
        } else {
            dobInput.value = formatDmy(dob);
        }

        if (ageInput && ageUnit) {
            if (years > 0) {
                ageInput.value = years;
                ageUnit.value = 'Years';
            } else if (months > 0) {
                ageInput.value = months;
                ageUnit.value = 'Months';
            } else {
                ageInput.value = Math.max(days, 0);
                ageUnit.value = 'Days';
            }
            ageInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        setDobType('estimated');
        isSyncingAgeDob = false;
    }

    function syncDobFromAge(options = {}) {
        if (isSyncingAgeDob) return;
        const dobInput = getEl(options.dobId || 'patient-dob');
        const ageInput = getEl(options.ageId || 'patient-age');
        const ageUnit = getEl(options.unitId || 'patient-age-unit');
        if (!dobInput || !ageInput || !ageUnit) return;

        const age = parseInt(ageInput.value, 10);
        if (!Number.isFinite(age) || age < 0) return;

        isSyncingAgeDob = true;
        const unit = ageUnit.value || 'Years';
        const base = getBaseDate(options.baseDateId);
        let dob;

        if (unit === 'Months') {
            dob = new Date(base.getFullYear(), base.getMonth() - age, 1);
        } else if (unit === 'Weeks') {
            dob = new Date(base.getFullYear(), base.getMonth(), base.getDate() - (age * 7));
        } else if (unit === 'Days') {
            dob = new Date(base.getFullYear(), base.getMonth(), base.getDate() - age);
        } else {
            dob = new Date(base.getFullYear() - age, 0, 1);
        }

        if (dobInput._flatpickr) {
            dobInput._flatpickr.setDate(dob, false);
        } else {
            dobInput.value = formatDmy(dob);
        }
        setDobType('estimated');
        isSyncingAgeDob = false;
    }

    function patientReferralCategoryKey(value) {
        const typeField = getEl('patient-ref-type');
        if (value === undefined && typeField?.selectedOptions?.[0]?.dataset?.referralCategory) {
            return typeField.selectedOptions[0].dataset.referralCategory;
        }
        const val = value !== undefined ? value : (typeField ? typeField.value : '');
        const key = String(val || '').trim().toLowerCase().replace(/[-\s]+/g, '_');
        if (key === 'doctor') return 'doctor';
        if (key === 'other_patient') return 'other_patient';
        if (key === 'others') return 'others';
        return 'self';
    }

    function referralSelectValue(category) {
        const cat = patientReferralCategoryKey(category);
        if (cat === 'doctor') return 'Doctor';
        if (cat === 'other_patient') return 'Other Patient';
        if (cat === 'others') return 'Others';
        return 'Self';
    }

    function syncReferralControl(options = {}) {
        const typeField = getEl('patient-ref-type');
        const textField = getEl('patient-ref-by');
        const wrapper = getEl('patient-referral-control');
        if (!typeField || !textField || !wrapper) return;

        const savedName = typeField.selectedOptions?.[0]?.dataset?.referralName || '';
        const category = patientReferralCategoryKey();
        const needsText = category === 'doctor' || category === 'others';
        wrapper.classList.toggle('has-free-text', needsText);
        textField.hidden = !needsText;
        textField.placeholder = category === 'doctor' ? 'Doctor name' : 'Referral details';

        if (!needsText) {
            textField.value = '';
        } else if (savedName) {
            textField.value = savedName;
        } else if (category === 'doctor') {
            const current = textField.value.trim();
            if (!current) {
                textField.value = 'Dr. ';
            } else if (!/^dr\.\s*/i.test(current)) {
                textField.value = `Dr. ${current}`;
            }
        } else if (/^dr\.?$/i.test(textField.value.trim())) {
            textField.value = '';
        }

        if (needsText && options.focusInput) {
            window.requestAnimationFrame(() => {
                textField.focus();
                const pos = textField.value.length;
                if (typeof textField.setSelectionRange === 'function') {
                    textField.setSelectionRange(pos, pos);
                }
            });
        }
        loadReferralSuggestions();
    }

    function loadReferralSuggestions() {
        const typeField = getEl('patient-ref-type');
        const textField = getEl('patient-ref-by');
        const list = getEl('patient-referral-list');
        if (!typeField || !textField || !list) return;

        const category = patientReferralCategoryKey();
        if (category !== 'doctor' && category !== 'others') {
            list.innerHTML = '';
            return;
        }

        window.clearTimeout(referralSuggestionsTimer);
        referralSuggestionsTimer = window.setTimeout(async () => {
            try {
                const params = new URLSearchParams({
                    action: 'suggestions',
                    category,
                    q: textField.value.trim()
                });
                const response = await fetch(`api/patient_referrals.php?${params.toString()}`);
                const data = await response.json();
                const suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
                list.innerHTML = suggestions.map((name) => {
                    const safe = String(name).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                    return `<option value="${safe}"></option>`;
                }).join('');
            } catch (err) {
                list.innerHTML = '';
            }
        }, 160);
    }

    function loadPreviousReferralOptions() {
        const regNoField = getEl('patient-reg-no');
        const select = getEl('patient-ref-type');
        if (!select) return;

        window.clearTimeout(previousReferralsTimer);
        previousReferralsTimer = window.setTimeout(async () => {
            const regNo = regNoField ? regNoField.value.trim() : '';
            select.querySelectorAll('option.patient-ref-saved-option').forEach(o => o.remove());
            if (!regNo) return;

            try {
                const params = new URLSearchParams({ action: 'recent', reg_no: regNo });
                const res = await fetch(`api/patient_referrals.php?${params.toString()}`);
                const data = await res.json();
                if (Array.isArray(data.referrals)) {
                    data.referrals.forEach((ref, idx) => {
                        const cat = patientReferralCategoryKey(ref.category);
                        if (!['doctor', 'others'].includes(cat)) return;
                        const name = String(ref.name || '').trim();
                        if (!name) return;
                        const opt = document.createElement('option');
                        opt.className = 'patient-ref-saved-option';
                        opt.value = `saved:${cat}:${idx}`;
                        opt.textContent = name;
                        opt.dataset.referralCategory = cat;
                        opt.dataset.referralName = name;
                        select.appendChild(opt);
                    });
                }
            } catch (err) {
                // Non-blocking
            }
        }, 180);
    }

    function initReferralControl() {
        const typeField = getEl('patient-ref-type');
        const textField = getEl('patient-ref-by');
        const regNoField = getEl('patient-reg-no');
        if (!typeField || !textField) return;

        typeField.addEventListener('change', () => syncReferralControl({ focusInput: true }));
        textField.addEventListener('focus', loadReferralSuggestions);
        textField.addEventListener('input', loadReferralSuggestions);
        if (regNoField) {
            regNoField.addEventListener('change', loadPreviousReferralOptions);
            regNoField.addEventListener('blur', loadPreviousReferralOptions);
            regNoField.addEventListener('input', loadPreviousReferralOptions);
        }
        loadPreviousReferralOptions();
        syncReferralControl();
    }

    function getReferralPayload() {
        const category = patientReferralCategoryKey();
        let name = getValue('patient-ref-by').replace(/\s+/g, ' ').trim();
        if (category !== 'doctor' && category !== 'others') {
            return { category, name: '' };
        }
        if (category === 'doctor') {
            if (!name || /^dr\.?$/i.test(name)) {
                return { category, name: '' };
            }
            if (!/^dr\.\s*/i.test(name)) {
                name = `Dr. ${name}`;
            }
        }
        return { category, name };
    }

    function setReferralValue(referral = {}) {
        const typeField = getEl('patient-ref-type');
        const textField = getEl('patient-ref-by');
        if (!typeField) return;

        const cat = patientReferralCategoryKey(referral.category || referral.referral_category);
        const name = referral.name || referral.referral_name || '';

        const savedOption = Array.from(typeField.options).find(
            opt => opt.dataset.referralCategory === cat && (opt.dataset.referralName || '').toLowerCase() === name.toLowerCase()
        );
        typeField.value = savedOption ? savedOption.value : referralSelectValue(cat);
        if (textField) textField.value = name;
        syncReferralControl();
    }

    function fetchOccupations() {
        if (occupationsCache) return Promise.resolve(occupationsCache);
        return fetch('api/get_occupations.php')
            .then(res => res.json())
            .then(data => {
                occupationsCache = Array.isArray(data) ? data : [];
                return occupationsCache;
            })
            .catch(() => {
                occupationsCache = [];
                return occupationsCache;
            });
    }

    function initOccupationAutocomplete(options = {}) {
        const input = getEl(options.inputId || 'patient-occupation');
        const list = getEl(options.listId || 'occupation-list');
        const wrapper = getEl(options.wrapperId || 'occ-wrapper');
        if (!input || !list || !window.ZimRxDropdown) return null;

        fetchOccupations();

        return window.ZimRxDropdown.attach({
            input,
            list,
            wrapper,
            allowEmptyClick: true,
            fetcher: async (val) => {
                const listData = await fetchOccupations();
                const q = (val || '').toLowerCase().trim();
                return listData.filter(occ => {
                    const name = typeof occ === 'string' ? occ : (occ.name || '');
                    return name.toLowerCase().includes(q);
                }).slice(0, 15);
            },
            renderItem: (occ) => {
                const name = typeof occ === 'string' ? occ : (occ.name || '');
                const isPinned = typeof occ === 'object' && Number(occ.is_pinned) === 1;
                const li = document.createElement('li');
                li.className = 'zrx-dropdown-item';
                li.style.position = 'relative';
                if (isPinned) {
                    li.innerHTML = `<img class="rx-dropdown-pin" src="assets/images/pin.svg" alt="Pinned">${window.ZimRxDropdown.escapeHtml(name)}`;
                } else {
                    li.textContent = name;
                }
                return li;
            },
            onSelect: (occ) => {
                input.value = typeof occ === 'string' ? occ : (occ.name || '');
            }
        });
    }

    function initAddressAutocomplete(options = {}) {
        const input = getEl(options.inputId || 'patient-address');
        const list = getEl(options.listId || 'address-list');
        const wrapper = getEl(options.wrapperId || 'address-wrapper');
        if (!input || !list || !window.ZimRxDropdown) return null;

        function getCurrentSegmentData(inputElem) {
            const text = inputElem.value;
            const cursorPos = inputElem.selectionStart || text.length;
            const textBeforeCursor = text.substring(0, cursorPos);
            const segmentIndex = (textBeforeCursor.match(/,/g) || []).length;
            const parts = text.split(',');
            const currentWord = parts[segmentIndex] ? parts[segmentIndex].trim() : '';
            const previousWord = (segmentIndex > 0 && parts[segmentIndex - 1]) ? parts[segmentIndex - 1].trim() : '';
            return { text, segmentIndex, currentWord, previousWord, parts };
        }

        return window.ZimRxDropdown.attach({
            input,
            list,
            wrapper,
            debounceMs: 180,
            fetcher: async () => {
                const { currentWord, previousWord, segmentIndex } = getCurrentSegmentData(input);
                if (currentWord.length < 1 && previousWord === '') {
                    return [];
                }
                try {
                    const url = `api/search_address.php?q=${encodeURIComponent(currentWord)}&segment=${segmentIndex}&prev=${encodeURIComponent(previousWord)}`;
                    const res = await fetch(url);
                    const data = await res.json();
                    return Array.isArray(data) ? data.slice(0, 15) : [];
                } catch (err) {
                    return [];
                }
            },
            renderItem: (suggestion) => {
                const li = document.createElement('li');
                li.className = 'zrx-dropdown-item';
                li.textContent = suggestion;
                return li;
            },
            onSelect: (suggestion) => {
                if (suggestion.includes(',')) {
                    input.value = suggestion;
                } else {
                    const { parts, segmentIndex } = getCurrentSegmentData(input);
                    parts[segmentIndex] = (segmentIndex === 0 ? '' : ' ') + suggestion;
                    input.value = parts.join(',');
                }
                input.focus();
            }
        });
    }

    function attachPatientLookup({
        inputId,
        listId,
        wrapperId,
        paramName = 'reg_no',
        allowNewPatient = false,
        getDate = null,
        onClearId = null,
        onSelect = null,
        onNewPatient = null
    }) {
        const input = getEl(inputId);
        const list = getEl(listId);
        const wrapper = getEl(wrapperId);
        if (!input || !list || !window.ZimRxDropdown) return null;

        if (typeof onClearId === 'function') {
            input.addEventListener('input', () => {
                onClearId();
            });
        }

        let cachedNewRegNo = '';

        return window.ZimRxDropdown.attach({
            input,
            list,
            wrapper,
            debounceMs: 180,
            minLength: 1,
            fetcher: async (query) => {
                const val = (query || '').trim();
                if (!val) return [];
                try {
                    const dateParam = typeof getDate === 'function' && getDate() ? `&date=${encodeURIComponent(getDate())}` : '';
                    const url = `api/appointments.php?action=patient_lookup&${encodeURIComponent(paramName)}=${encodeURIComponent(val)}${dateParam}`;
                    const res = await fetch(url);
                    const data = await res.json();
                    cachedNewRegNo = data.new_reg_no || '';
                    const listItems = Array.isArray(data.patients) ? [...data.patients] : [];
                    if (paramName === 'mobile' && allowNewPatient && val.length > 0) {
                        listItems.push({
                            __isNewPatient: true,
                            mobile: val,
                            newRegNo: cachedNewRegNo
                        });
                    }
                    return listItems;
                } catch (err) {
                    return [];
                }
            },
            renderItem: (item) => {
                const li = document.createElement('li');
                li.className = 'zrx-dropdown-item';
                const escape = window.ZimRxDropdown.escapeHtml;

                if (item.__isNewPatient) {
                    li.classList.add('new-patient-option');
                    const regText = item.newRegNo ? ` with ${escape(item.newRegNo)}` : '';
                    li.innerHTML = `<strong>+ It's a new patient</strong><span>Use ${escape(item.mobile)}${regText}</span>`;
                    return li;
                }

                li.classList.add('patient-lookup-option');
                const isMobile = paramName === 'mobile';
                const codeLabel = isMobile ? (item.mobile || '') : (item.reg_no || '');
                const subText = isMobile ? (item.reg_no || '') : (item.mobile || 'No Phone');
                const addressLabel = item.address || 'No Address';
                const metaText = subText ? `${subText} | ${addressLabel}` : addressLabel;

                li.innerHTML = `<span class="patient-lookup-code">${escape(codeLabel)}</span><strong class="patient-lookup-name">${escape(item.full_name || item.patient_name || 'No Name')}</strong><span class="patient-lookup-meta">${escape(metaText)}</span>`;
                return li;
            },
            onSelect: (item) => {
                if (item.__isNewPatient) {
                    if (typeof onNewPatient === 'function') {
                        onNewPatient(item.mobile, item.newRegNo);
                    }
                } else if (typeof onSelect === 'function') {
                    onSelect(item);
                }
            }
        });
    }

    function openExtraModal(e) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        const modal = getEl('patient-extra-modal');
        if (modal && window.ZimRxModal) {
            window.ZimRxModal.open(modal);
        }
    }

    function closeExtraModal(e) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        const modal = getEl('patient-extra-modal');
        if (modal && window.ZimRxModal) {
            window.ZimRxModal.close(modal);
        }
        updateExtraDot();
    }

    function getExtraDemographics() {
        return {
            spouse_name: getValue('patient-spouse-name'),
            father_name: getValue('patient-father-name'),
            mother_name: getValue('patient-mother-name'),
            emergency_contact: {
                name: getValue('patient-emergency-name'),
                mobile: getValue('patient-emergency-mobile'),
                relation: getValue('patient-emergency-relation'),
                address: getValue('patient-emergency-address')
            },
            nationality: getValue('patient-nationality'),
            company_name: getValue('patient-company-name'),
            company_address: getValue('patient-company-address'),
            custom_fields: getCustomFieldValues()
        };
    }

    function updateExtraDot() {
        const dot = getEl('extra-demographics-dot');
        if (!dot) return;

        const extra = getExtraDemographics();
        const hasExtra = Boolean(
            getValue('patient-email') ||
            getValue('patient-nid-no') ||
            getValue('patient-passport-no') ||
            extra.spouse_name ||
            extra.father_name ||
            extra.mother_name ||
            extra.nationality ||
            extra.company_name ||
            extra.company_address ||
            Object.values(extra.custom_fields || {}).some(value => value !== '') ||
            extra.emergency_contact.name ||
            extra.emergency_contact.mobile ||
            extra.emergency_contact.relation ||
            extra.emergency_contact.address
        );

        dot.hidden = !hasExtra;
        if (hasExtra) {
            dot.style.display = 'inline-block';
        } else {
            dot.style.display = 'none';
        }
    }

    const CONFIGURABLE_FIELDS = [
        { key: 'marital_status', label: 'Marital Status', defaultPlacement: 'main' },
        { key: 'email', label: 'Email Address', defaultPlacement: 'more_info' },
        { key: 'nid_no', label: 'National ID (NID)', defaultPlacement: 'more_info' },
        { key: 'passport_no', label: 'Passport No', defaultPlacement: 'more_info' },
        { key: 'spouse_name', label: "Spouse's Name", defaultPlacement: 'more_info' },
        { key: 'father_name', label: "Father's Name", defaultPlacement: 'more_info' },
        { key: 'mother_name', label: "Mother's Name", defaultPlacement: 'more_info' },
        { key: 'emergency_name', label: 'Emergency Contact Person', defaultPlacement: 'more_info' },
        { key: 'emergency_mobile', label: 'Emergency Contact Phone', defaultPlacement: 'more_info' },
        { key: 'emergency_relation', label: 'Emergency Contact Relationship', defaultPlacement: 'more_info' },
        { key: 'emergency_address', label: 'Emergency Contact Address', defaultPlacement: 'more_info' },
        { key: 'nationality', label: 'Nationality', defaultPlacement: 'more_info' },
        { key: 'company_name', label: 'Company / Organization', defaultPlacement: 'more_info' },
        { key: 'company_address', label: 'Company Address', defaultPlacement: 'more_info' }
    ];

    const CONFIGURABLE_FIELD_NODES = new Map();

    function getCustomFieldDefinitions() {
        return Array.isArray(window.ZimRxCustomPatientFields) ? window.ZimRxCustomPatientFields : [];
    }

    function getCustomFieldValues() {
        return getCustomFieldDefinitions().reduce((values, definition) => {
            values[definition.key] = getValue(`patient-custom-${definition.key}`);
            return values;
        }, {});
    }

    function createCustomFieldNode(definition, placement, value = '') {
        const outer = document.createElement('div');
        outer.className = placement === 'main' ? 'p-field' : 'extra-field-col';
        outer.dataset.customFieldKey = definition.key;

        const inputId = `patient-custom-${definition.key}`;
        const label = document.createElement('label');
        label.htmlFor = inputId;
        label.textContent = definition.label;
        outer.append(label);

        let control;
        if (definition.type === 'select') {
            control = document.createElement('select');
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = '--';
            control.append(blank);
            (definition.options || []).forEach(option => {
                const choice = document.createElement('option');
                choice.value = option;
                choice.textContent = option;
                control.append(choice);
            });
        } else {
            control = document.createElement('input');
            control.type = definition.type === 'number' || definition.type === 'date' ? definition.type : 'text';
            if (definition.type === 'text') control.maxLength = 500;
        }
        control.id = inputId;
        control.value = value;
        control.dataset.customPatientField = definition.key;
        outer.append(control);
        return outer;
    }

    function renderCustomPatientFields(values = null) {
        const currentValues = values || getCustomFieldValues();
        const mainHost = getEl('patient-custom-main-fields');
        const modalHost = getEl('patient-custom-modal-fields');
        const modalSection = getEl('patient-custom-modal-section');
        if (!mainHost && !modalHost) return;

        if (mainHost) mainHost.replaceChildren();
        if (modalHost) modalHost.replaceChildren();

        getCustomFieldDefinitions().forEach(definition => {
            if (definition.placement === 'hidden') return;
            const host = definition.placement === 'main' ? mainHost : modalHost;
            if (!host) return;
            host.append(createCustomFieldNode(definition, definition.placement, currentValues[definition.key] || ''));
        });

        if (mainHost) mainHost.hidden = mainHost.childElementCount === 0;
        if (modalSection) modalSection.hidden = !modalHost || modalHost.childElementCount === 0;
    }

    function renderCustomFieldDefinitionOptions() {
        const grid = getEl('custom-fields-grid');
        if (!grid) return;
        grid.replaceChildren();

        getCustomFieldDefinitions().forEach(definition => {
            const row = document.createElement('div');
            row.className = 'config-field-row custom-definition-row';
            row.dataset.customKey = definition.key;
            row.dataset.customType = definition.type;
            row.dataset.customOptions = JSON.stringify(definition.options || []);

            const label = document.createElement('label');
            label.className = 'config-field-name';
            label.htmlFor = `custom-label-${definition.key}`;
            label.textContent = 'Field label';
            const input = document.createElement('input');
            input.id = `custom-label-${definition.key}`;
            input.type = 'text';
            input.maxLength = 60;
            input.value = definition.label;
            label.append(input);

            const type = document.createElement('span');
            type.className = 'custom-field-type';
            type.textContent = definition.type === 'select' ? 'Choice' : definition.type.charAt(0).toUpperCase() + definition.type.slice(1);

            const placementLabel = document.createElement('label');
            placementLabel.className = 'config-field-name';
            placementLabel.textContent = 'Placement';
            const placement = document.createElement('select');
            ['main', 'more_info', 'hidden'].forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value === 'main' ? 'Main form' : value === 'more_info' ? 'More Info' : 'Hidden';
                option.selected = definition.placement === value;
                placement.append(option);
            });
            placementLabel.append(placement);

            row.append(label, type, placementLabel);
            grid.append(row);
        });
    }

    function addCustomFieldDefinition() {
        const label = getValue('new-custom-field-label');
        const type = getValue('new-custom-field-type') || 'text';
        const placement = getValue('new-custom-field-placement') || 'more_info';
        const optionsValue = getValue('new-custom-field-options');
        const typeSelect = getEl('new-custom-field-type');
        const status = getEl('custom-fields-settings-status');
        if (label === '') {
            if (status) status.textContent = 'Enter a field label.';
            return;
        }
        if (type === 'select' && optionsValue.trim() === '') {
            if (status) status.textContent = 'Enter at least two comma-separated choices.';
            return;
        }
        const options = type === 'select'
            ? optionsValue.split(',').map(option => option.trim()).filter(Boolean)
            : [];
        if (type === 'select' && (options.length < 2 || new Set(options).size !== options.length || options.some(option => option.length > 60))) {
            if (status) status.textContent = 'Choice fields need 2 to 20 unique choices, each 60 characters or fewer.';
            return;
        }
        const draft = { key: '', label, type, placement, options };
        window.ZimRxCustomPatientFields = [...getCustomFieldDefinitions(), draft];
        renderCustomFieldDefinitionOptions();
        setValue('new-custom-field-label', '');
        setValue('new-custom-field-options', '');
        if (typeSelect) typeSelect.value = 'text';
        if (status) status.textContent = 'Custom field added. Save custom fields to apply it.';
    }

    function collectCustomFieldDefinitions() {
        return Array.from(document.querySelectorAll('#custom-fields-grid .custom-definition-row')).map(row => {
            const label = row.querySelector('input')?.value || '';
            const placement = row.querySelector('select')?.value || 'more_info';
            let options = [];
            try {
                options = JSON.parse(row.dataset.customOptions || '[]');
            } catch (_) {
                options = [];
            }
            return {
                key: row.dataset.customKey || '',
                label,
                type: row.dataset.customType || 'text',
                placement,
                options
            };
        });
    }

    async function saveCustomFieldDefinitions() {
        const status = getEl('custom-fields-settings-status');
        try {
            const res = await fetch('api/save_interface_layout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ particulars_custom_fields: collectCustomFieldDefinitions() })
            });
            const json = await res.json();
            if (!res.ok || json.ok !== true || !Array.isArray(json.particulars_custom_fields)) {
                throw new Error(json.error || 'The server rejected the custom fields.');
            }
            window.ZimRxCustomPatientFields = json.particulars_custom_fields;
            renderCustomFieldDefinitionOptions();
            renderCustomPatientFields();
            if (status) status.textContent = 'Custom fields saved.';
            return true;
        } catch (error) {
            console.error('Failed to save custom patient fields', error);
            if (status) status.textContent = error.message || 'Could not save custom fields.';
            return false;
        }
    }

    function getSavedFieldPlacement() {
        return window.ZimRxSavedParticularsFields && typeof window.ZimRxSavedParticularsFields === 'object'
            ? window.ZimRxSavedParticularsFields
            : {};
    }

    function normalizeFieldPlacement(value, field) {
        if (value === 'hidden') return 'hidden';
        if (value === 'main' || value === 'more_info') return value;
        return field.defaultPlacement;
    }

    function getConfigurableFieldNode(field) {
        if (CONFIGURABLE_FIELD_NODES.has(field.key)) {
            return CONFIGURABLE_FIELD_NODES.get(field.key);
        }

        const node = document.querySelector(`[data-config-key="${field.key}"]`);
        if (!node || !node.parentNode) return null;

        const marker = document.createComment(`patient-field:${field.key}`);
        node.parentNode.insertBefore(marker, node);
        const state = {
            node,
            marker,
            originalClassName: node.className,
            originalLocationIsModal: Boolean(node.closest('#patient-extra-modal'))
        };
        CONFIGURABLE_FIELD_NODES.set(field.key, state);
        return state;
    }

    function restoreConfigurableField(state) {
        state.node.className = state.originalClassName;
        if (state.marker.parentNode) {
            state.marker.parentNode.insertBefore(state.node, state.marker.nextSibling);
        }
    }

    function applyFieldPlacement(placements = null) {
        const map = placements || getSavedFieldPlacement();
        const mainFields = getEl('patient-configurable-main-fields');
        const modalFields = getEl('patient-configurable-modal-fields');
        const modalSection = getEl('patient-configurable-modal-section');

        CONFIGURABLE_FIELDS.forEach(field => {
            const state = getConfigurableFieldNode(field);
            if (!state) return;

            const placement = normalizeFieldPlacement(map[field.key], field);
            const { node, originalLocationIsModal } = state;
            node.hidden = false;

            if (placement === 'main' && originalLocationIsModal && mainFields) {
                node.className = state.originalClassName.replace('extra-field-col', 'p-field');
                mainFields.append(node);
            } else if (placement === 'more_info' && !originalLocationIsModal && modalFields) {
                node.className = state.originalClassName.replace('p-field', 'extra-field-col');
                modalFields.append(node);
            } else {
                restoreConfigurableField(state);
            }

            if (placement === 'hidden') node.hidden = true;
        });

        if (mainFields) mainFields.hidden = mainFields.childElementCount === 0;
        if (modalSection) modalSection.hidden = !modalFields || modalFields.childElementCount === 0;

        document.querySelectorAll('#patient-extra-modal .extra-field-row').forEach(row => {
            row.hidden = Array.from(row.children).every(child => child.hidden);
        });
        document.querySelectorAll('#patient-extra-modal [data-field-group]:not([data-field-group="referral"])').forEach(section => {
            section.hidden = !section.querySelector('.extra-field-col:not([hidden])');
        });
    }

    function renderFieldPlacementOptions() {
        const grid = getEl('config-fields-grid');
        if (!grid) return;
        const saved = getSavedFieldPlacement();

        grid.innerHTML = CONFIGURABLE_FIELDS.map(field => {
            const cur = normalizeFieldPlacement(saved[field.key], field);
            return `
                <div class="config-field-row" data-key="${field.key}">
                    <label class="config-field-name" for="cfg-${field.key}">${field.label}</label>
                    <select id="cfg-${field.key}" name="cfg_${field.key}">
                        <option value="main" ${cur === 'main' ? 'selected' : ''}>Main form</option>
                        <option value="more_info" ${cur === 'more_info' ? 'selected' : ''}>More Info</option>
                        <option value="hidden" ${cur === 'hidden' ? 'selected' : ''}>Hidden</option>
                    </select>
                </div>
            `;
        }).join('');
    }

    async function saveFieldPlacement() {
        const placements = {};
        CONFIGURABLE_FIELDS.forEach(field => {
            const select = getEl(`cfg-${field.key}`);
            if (select) placements[field.key] = select.value;
        });

        try {
            const csrfToken = window.ZimRxCsrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('api/save_interface_layout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    particulars_fields: placements
                })
            });
            const json = await res.json();
            if (!res.ok || json.ok !== true) {
                throw new Error(json.error || 'The server rejected the settings.');
            }
            window.ZimRxSavedParticularsFields = placements;
            applyFieldPlacement(placements);
            const panel = getEl('patient-fields-config-panel');
            if (panel && !panel.hasAttribute('data-settings-page')) panel.hidden = true;
            const status = getEl('patient-fields-settings-status');
            if (status) status.textContent = 'Field placement saved.';
            return true;
        } catch (e) {
            console.error('Failed to save patient field placement', e);
            const status = getEl('patient-fields-settings-status');
            if (status) status.textContent = 'Could not save field placement.';
            return false;
        }
    }

    async function resetFieldPlacement() {
        try {
            const csrfToken = window.ZimRxCsrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('api/save_interface_layout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    reset_particulars_fields: true
                })
            });
            const json = await res.json();
            if (!res.ok || json.ok !== true) {
                throw new Error(json.error || 'The server rejected the reset.');
            }
            window.ZimRxSavedParticularsFields = {};
            applyFieldPlacement({});
            renderFieldPlacementOptions();
            const panel = getEl('patient-fields-config-panel');
            if (panel && !panel.hasAttribute('data-settings-page')) panel.hidden = true;
            const status = getEl('patient-fields-settings-status');
            if (status) status.textContent = 'Default field placement restored.';
            return true;
        } catch (e) {
            console.error('Failed to reset patient field placement', e);
            const status = getEl('patient-fields-settings-status');
            if (status) status.textContent = 'Could not reset field placement.';
            return false;
        }
    }

    function setValues(p = {}, options = {}) {
        setValue('patient-id', p.id || p.patient_id || '');
        setValue('patient-reg-no', p.reg_no || '');
        setValue('patient-name', p.full_name || p.patient_name || '');
        setValue('patient-age', p.age || '');
        if (p.age_unit) setValue('patient-age-unit', p.age_unit);

        setDobType(p.dob_type || 'exact');

        const dobInput = getEl('patient-dob');
        if (dobInput) {
            if (p.dob) {
                if (dobInput._flatpickr) {
                    dobInput._flatpickr.setDate(p.dob);
                } else {
                    const parts = p.dob.split('-');
                    dobInput.value = parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : p.dob;
                }
                syncAgeFromDob(Object.assign({}, options, { keepDobType: Boolean(p.dob_type) }));
            } else {
                dobInput.value = '';
                const ageNum = parseInt(p.age, 10);
                const yEl = getEl('patient-age-years');
                const mEl = getEl('patient-age-months');
                const dEl = getEl('patient-age-days');
                if (yEl && Number.isFinite(ageNum)) {
                    const unit = (p.age_unit || 'Years').toLowerCase();
                    if (unit.startsWith('y')) {
                        yEl.value = ageNum;
                        if (mEl) mEl.value = '';
                        if (dEl) dEl.value = '';
                    } else if (unit.startsWith('m')) {
                        yEl.value = '';
                        if (mEl) mEl.value = ageNum;
                        if (dEl) dEl.value = '';
                    } else {
                        yEl.value = '';
                        if (mEl) mEl.value = '';
                        if (dEl) dEl.value = ageNum;
                    }
                    setDobType(p.dob_type || 'exact');
                } else {
                    setDobType(null);
                }
            }
        }

        if (p.gender !== undefined) setValue('patient-gender', p.gender || '');
        if (p.blood_group !== undefined) setValue('patient-blood-group', p.blood_group || '');
        setValue('patient-marital-status', p.marital_status || '');
        setValue('patient-mobile', p.mobile || '');
        setValue('patient-email', p.email || '');
        setValue('patient-nid-no', p.nid_no || '');
        setValue('patient-passport-no', p.passport_no || '');
        setValue('patient-occupation', p.occupation || '');
        setValue('patient-address', p.address || '');
        setValue('patient-weight', p.weight || '');
        if (p.weight_unit) setValue('patient-weight-unit', p.weight_unit);
        setValue('patient-height', p.height || '');
        if (p.height_unit) setValue('patient-height-unit', p.height_unit);

        const extra = typeof p.extra_demographics === 'object' && p.extra_demographics !== null
            ? p.extra_demographics
            : {};

        setValue('patient-spouse-name', extra.spouse_name || '');
        setValue('patient-father-name', extra.father_name || '');
        setValue('patient-mother-name', extra.mother_name || '');
        const ec = extra.emergency_contact || {};
        setValue('patient-emergency-name', ec.name || '');
        setValue('patient-emergency-mobile', ec.mobile || '');
        setValue('patient-emergency-relation', ec.relation || '');
        setValue('patient-emergency-address', ec.address || '');
        setValue('patient-nationality', extra.nationality || '');
        setValue('patient-company-name', extra.company_name || '');
        setValue('patient-company-address', extra.company_address || '');
        renderCustomPatientFields(extra.custom_fields || {});

        updateExtraDot();

        if (p.next_visit_no || p.visit_no) setValue('visit-no', p.next_visit_no || p.visit_no || '1');
        if (p.next_visit_code || p.visit_code || p.visit_id) {
            setValue('visit-code', p.next_visit_code || p.visit_code || p.visit_id || '');
        }

        setReferralValue(p);
        loadPreviousReferralOptions();
    }

    function clearValues(options = {}) {
        setValue('patient-id', '');
        setValue('visit-no', '');
        setValue('visit-code', '');
        setValue('visit-record-id', '0');
        setValue('visit-revision', '1');

        if (!options.keepName) setValue('patient-name', '');
        setValue('patient-age', '');
        setValue('patient-age-unit', 'Years');
        setValue('patient-age-years', '');
        setValue('patient-age-months', '');
        setValue('patient-age-days', '');

        const dobInput = getEl('patient-dob');
        if (dobInput) {
            if (dobInput._flatpickr) dobInput._flatpickr.clear();
            else dobInput.value = '';
        }
        setDobType(null);

        setValue('patient-gender', '');
        setValue('patient-blood-group', '');
        setValue('patient-marital-status', '');
        if (!options.keepMobile) setValue('patient-mobile', '');
        setValue('patient-email', '');
        setValue('patient-nid-no', '');
        setValue('patient-passport-no', '');
        setValue('patient-reg-no', '');
        setValue('patient-occupation', '');
        setValue('patient-address', '');
        setValue('patient-weight', '');
        setValue('patient-weight-unit', 'kg');
        setValue('patient-height', '');
        setValue('patient-height-unit', 'inch');

        setValue('patient-spouse-name', '');
        setValue('patient-father-name', '');
        setValue('patient-mother-name', '');
        setValue('patient-emergency-name', '');
        setValue('patient-emergency-mobile', '');
        setValue('patient-emergency-relation', '');
        setValue('patient-emergency-address', '');
        setValue('patient-nationality', '');
        setValue('patient-company-name', '');
        setValue('patient-company-address', '');
        renderCustomPatientFields({});

        updateExtraDot();
        setReferralValue({ category: 'self', name: '' });
    }

    function bindModalEvents() {
        const modal = getEl('patient-extra-modal');
        if (modal && !modal.dataset.zrxBound) {
            modal.dataset.zrxBound = 'true';
            modal.addEventListener('zrx:modalclosed', updateExtraDot);
        }

        const modalBody = document.querySelector('.patient-extra-modal-body');
        if (modalBody && !modalBody.dataset.zrxBound) {
            modalBody.dataset.zrxBound = 'true';
            modalBody.addEventListener('input', updateExtraDot);
            modalBody.addEventListener('change', updateExtraDot);
        }
    }

    function init(cfg = {}) {
        const baseDateId = cfg.baseDateId || 'patient-date';
        const dobInput = getEl('patient-dob');
        const ageInput = getEl('patient-age');
        const ageUnit = getEl('patient-age-unit');

        if (dobInput) {
            dobInput.addEventListener('change', () => syncAgeFromDob({ baseDateId }));
            dobInput.addEventListener('input', () => syncAgeFromDob({ baseDateId }));
        }
        if (ageInput) {
            ageInput.addEventListener('input', () => syncDobFromAge({ baseDateId }));
        }
        if (ageUnit) {
            ageUnit.addEventListener('change', () => syncDobFromAge({ baseDateId }));
        }

        ['patient-age-years', 'patient-age-months', 'patient-age-days'].forEach(id => {
            const el = getEl(id);
            if (el) {
                el.addEventListener('input', () => syncDobFromAgeTriple({ baseDateId }));
            }
        });

        const badge = getEl('patient-dob-type-badge');
        if (badge && !badge.dataset.zrxBound) {
            badge.dataset.zrxBound = 'true';
            badge.addEventListener('click', toggleDobType);
            badge.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleDobType();
                }
            });
        }

        bindModalEvents();
        applyFieldPlacement();
        renderCustomPatientFields();
        updateExtraDot();
        setDobType(getValue('patient-dob-type') || 'exact');

        initReferralControl();
        initOccupationAutocomplete();
        initAddressAutocomplete();

        attachPatientLookup({
            inputId: 'patient-mobile',
            listId: 'mobile-list',
            wrapperId: 'mobile-wrapper',
            paramName: 'mobile',
            allowNewPatient: cfg.allowNewPatient ?? false,
            getDate: cfg.getDate || (() => getValue(baseDateId)),
            onClearId: cfg.onClearId || (() => setValue('patient-id', '')),
            onSelect: cfg.onPatientSelected,
            onNewPatient: cfg.onNewPatient
        });

        attachPatientLookup({
            inputId: 'patient-reg-no',
            listId: 'reg-list',
            wrapperId: 'reg-wrapper',
            paramName: 'reg_no',
            allowNewPatient: false,
            getDate: cfg.getDate || (() => getValue(baseDateId)),
            onClearId: cfg.onClearId || (() => setValue('patient-id', '')),
            onSelect: cfg.onPatientSelected
        });
    }

    window.PatientParticulars = {
        syncAgeFromDob,
        syncDobFromAge,
        syncDobFromAgeTriple,
        setDobType,
        toggleDobType,
        openExtraModal,
        closeExtraModal,
        getExtraDemographics,
        updateExtraDot,
        patientReferralCategoryKey,
        referralSelectValue,
        syncPatientReferredByControl: syncReferralControl,
        syncReferralControl,
        loadPatientReferralSuggestions: loadReferralSuggestions,
        loadReferralSuggestions,
        loadPreviousPatientReferralOptions: loadPreviousReferralOptions,
        loadPreviousReferralOptions,
        initPatientReferredByControl: initReferralControl,
        initReferralControl,
        getReferralPayload,
        setReferralValue,
        fetchOccupations,
        initOccupationAutocomplete,
        initAddressAutocomplete,
        attachPatientLookup,
        setValues,
        clearValues,
        renderFieldPlacementOptions,
        saveFieldPlacement,
        resetFieldPlacement,
        renderCustomPatientFields,
        renderCustomFieldDefinitionOptions,
        collectCustomFieldDefinitions,
        addCustomFieldDefinition,
        saveCustomFieldDefinitions,
        init
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            bindModalEvents();
            updateExtraDot();
            applyFieldPlacement();
            renderCustomPatientFields();
            setDobType(getValue('patient-dob-type') || 'exact');
        });
    } else {
        bindModalEvents();
        updateExtraDot();
        applyFieldPlacement();
        renderCustomPatientFields();
        setDobType(getValue('patient-dob-type') || 'exact');
    }

})(window, document);
