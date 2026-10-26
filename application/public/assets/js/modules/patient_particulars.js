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

    function syncAgeFromDob(options = {}) {
        if (isSyncingAgeDob) return;
        const dobInput = getEl(options.dobId || 'patient-dob');
        const ageInput = getEl(options.ageId || 'patient-age');
        const ageUnit = getEl(options.unitId || 'patient-age-unit');
        if (!dobInput || !ageInput || !ageUnit) return;

        let dob = null;
        if (dobInput._flatpickr && dobInput._flatpickr.selectedDates && dobInput._flatpickr.selectedDates[0]) {
            dob = dobInput._flatpickr.selectedDates[0];
        } else {
            dob = parseDate(dobInput.value);
        }
        if (!dob) return;

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
        isSyncingAgeDob = false;

        ageInput.dispatchEvent(new Event('change', { bubbles: true }));
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
            dobInput._flatpickr.setDate(dob);
        } else {
            dobInput.value = formatDmy(dob);
        }
        isSyncingAgeDob = false;

        dobInput.dispatchEvent(new Event('change', { bubbles: true }));
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

    function setValues(p = {}, options = {}) {
        setValue('patient-id', p.id || p.patient_id || '');
        setValue('patient-reg-no', p.reg_no || '');
        setValue('patient-name', p.full_name || p.patient_name || '');
        setValue('patient-age', p.age || '');
        if (p.age_unit) setValue('patient-age-unit', p.age_unit);

        const dobInput = getEl('patient-dob');
        if (dobInput) {
            if (p.dob) {
                if (dobInput._flatpickr) {
                    dobInput._flatpickr.setDate(p.dob);
                } else {
                    const parts = p.dob.split('-');
                    dobInput.value = parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : p.dob;
                }
                syncAgeFromDob(options);
            } else {
                dobInput.value = '';
            }
        }

        if (p.gender !== undefined) setValue('patient-gender', p.gender || '');
        if (p.blood_group !== undefined) setValue('patient-blood-group', p.blood_group || '');
        setValue('patient-mobile', p.mobile || '');
        setValue('patient-occupation', p.occupation || '');
        setValue('patient-address', p.address || '');
        setValue('patient-weight', p.weight || '');
        if (p.weight_unit) setValue('patient-weight-unit', p.weight_unit);
        setValue('patient-height', p.height || '');
        if (p.height_unit) setValue('patient-height-unit', p.height_unit);

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
        const dobInput = getEl('patient-dob');
        if (dobInput) {
            if (dobInput._flatpickr) dobInput._flatpickr.clear();
            else dobInput.value = '';
        }
        setValue('patient-gender', '');
        setValue('patient-blood-group', '');
        if (!options.keepMobile) setValue('patient-mobile', '');
        setValue('patient-reg-no', '');
        setValue('patient-occupation', '');
        setValue('patient-address', '');
        setValue('patient-weight', '');
        setValue('patient-weight-unit', 'kg');
        setValue('patient-height', '');
        setValue('patient-height-unit', 'inch');

        setReferralValue({ category: 'self', name: '' });
    }

    function init(cfg = {}) {
        const baseDateId = cfg.baseDateId || 'patient-date';
        const dobInput = getEl('patient-dob');
        const ageInput = getEl('patient-age');
        const ageUnit = getEl('patient-age-unit');

        if (dobInput) {
            dobInput.addEventListener('change', () => syncAgeFromDob({ baseDateId }));
        }
        if (ageInput) {
            ageInput.addEventListener('input', () => syncDobFromAge({ baseDateId }));
        }
        if (ageUnit) {
            ageUnit.addEventListener('change', () => syncDobFromAge({ baseDateId }));
        }

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
        init
    };

})(window, document);
