// Clinic appointment queue and booking page with vitals, payment tracking, and patient lookups.
(() => {
    const bootstrapEl = document.getElementById('zimrxAppointmentBootstrap');
    if (bootstrapEl) {
        try {
            const data = JSON.parse(bootstrapEl.textContent || '{}');
            if (data.role !== undefined) window.ZIMRX_APPOINTMENT_ROLE = data.role;
            if (data.doctors !== undefined) window.ZIMRX_APPOINTMENT_DOCTORS = data.doctors;
            if (data.initial !== undefined) window.ZIMRX_APPOINTMENT_INITIAL = data.initial;
        } catch (e) {
            console.error('Failed to parse appointment bootstrap data', e);
        }
    }
})();

(() => {
    const field = (id) => document.getElementById(id);
    const form = field('appointment-form');
    if (!form) return;

    const list = field('appointment-list');
    const count = field('appointment-count');
    const queueDateInput = field('queue-date-input');
    const filterDate = field('appointment-date-filter');
    const lookupStatus = field('patient-lookup-status');

    const api = 'api/appointments.php';
    const role = String(window.ZIMRX_APPOINTMENT_ROLE || '').toLowerCase();
    const isAssistant = role === 'assistant';
    const appointmentDoctors = Array.isArray(window.ZIMRX_APPOINTMENT_DOCTORS) ? window.ZIMRX_APPOINTMENT_DOCTORS : [];
    const initialState = window.ZIMRX_APPOINTMENT_INITIAL && typeof window.ZIMRX_APPOINTMENT_INITIAL === 'object'
        ? window.ZIMRX_APPOINTMENT_INITIAL
        : {};
    const vitalsModal = field('vitals-modal');
    const vitalsForm = field('vitals-form');
    const settingsModal = field('appointment-settings-modal');
    const settingsForm = field('appointment-settings-form');
    const paymentModal = field('payment-modal');
    const paymentForm = field('payment-form');
    const historyModal = field('history-modal');
    const historyList = field('history-list');
    const defaultTokenFields = ['name', 'age', 'sex', 'reg', 'visit_no', 'visit_id', 'visit_fee', 'discount', 'paid'];
    let currentSettings = initialState.settings || null;
    let paymentSubmitMode = 'save';
    let discountCausesLoaded = false;

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const setValue = (id, value) => {
        const target = field(id);
        if (!target) return;
        target.value = value ?? '';
        if (target.tagName === 'SELECT' && target.dataset.zrxEnhanced === 'true') {
            const wrap = field(`zrx-wrap-${id}`);
            const labelSpan = wrap?.querySelector('.zrx-select-label');
            if (labelSpan) {
                const opt = target.selectedOptions[0];
                labelSpan.textContent = opt ? opt.text : '--';
            }
        }
    };

    const getValue = (id) => (field(id)?.value || '').trim();

    const getActiveDoctorId = () => getValue('active-doctor-id');

    const needsDoctorSelection = () => isAssistant && appointmentDoctors.length > 1 && getActiveDoctorId() === '';

    const apiUrl = (params = {}) => {
        const search = new URLSearchParams(params);
        const doctorId = getActiveDoctorId();
        if (doctorId) {
            search.set('doctor_id', doctorId);
        }
        const query = search.toString();
        return query ? `${api}?${query}` : api;
    };

    const apiPayload = (payload = {}) => ({
        ...payload,
        doctor_id: getActiveDoctorId()
    });



    const formatDisplayDate = (dateString) => {
        const parts = String(dateString || '').split('-');
        if (parts.length !== 3) return dateString || '';
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    };

    const parseDisplayDate = (dateString) => {
        const value = String(dateString || '').trim();
        const isoMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (isoMatch) return value;

        const match = value.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
        if (!match) return '';
        const day = Number.parseInt(match[1], 10);
        const month = Number.parseInt(match[2], 10);
        const year = Number.parseInt(match[3], 10);
        const date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return '';
        }
        return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    };

    const getIsoDate = (id) => parseDisplayDate(getValue(id));

    const setDisplayDate = (id, isoDate) => {
        setValue(id, isoDate ? formatDisplayDate(isoDate) : '');
    };

    const values = () => {
        const referral = window.PatientParticulars?.getReferralPayload() || { category: 'self', name: '' };
        return {
            doctor_id: getActiveDoctorId(),
            id: getValue('appointment-id'),
            patient_id: getValue('patient-id'),
            appointment_no: getValue('appointment-no'),
            appointment_date: getIsoDate('appointment-date'),
            appointment_time: getValue('appointment-time'),
            reg_no: getValue('patient-id') ? getValue('patient-reg-no') : '',
            patient_name: getValue('patient-name'),
            age: getValue('patient-age'),
            age_unit: getValue('patient-age-unit'),
            dob: getIsoDate('patient-dob'),
            gender: getValue('patient-gender'),
            blood_group: getValue('patient-blood-group'),
            mobile: getValue('patient-mobile'),
            occupation: getValue('patient-occupation'),
            address: getValue('patient-address'),
            weight: getValue('patient-weight'),
            weight_unit: getValue('patient-weight-unit'),
            height: getValue('patient-height'),
            height_unit: getValue('patient-height-unit'),
            visit_no: getValue('appointment-id') ? getValue('visit-no') : '',
            visit_id: getValue('visit-code'),
            visit_code: getValue('visit-code'),
            referral,
            referral_category: referral.category,
            referral_name: referral.name,
            notes: getValue('appointment-notes')
        };
    };

    const closeList = (listId, wrapperId) => {
        const el = field(listId);
        if (el) {
            el.classList.remove('show');
            el.innerHTML = '';
        }
        field(wrapperId)?.classList.remove('open');
    };

    const closeAllLookups = () => {
        closeList('mobile-list', 'mobile-wrapper');
        closeList('reg-list', 'reg-wrapper');
        closeList('occupation-list', 'occ-wrapper');
        closeList('address-list', 'address-wrapper');
        closeList('appointment-doctor-list', 'appointment-doctor-wrapper');
    };

    const updateActiveListItem = (items, index) => {
        Array.from(items).forEach(item => item.classList.remove('active'));
        if (items[index]) {
            items[index].classList.add('active');
            items[index].scrollIntoView({ block: 'nearest' });
        }
    };

    const handleListKeydown = (e, listId, wrapperId) => {
        const list = field(listId);
        if (!list || !list.classList.contains('show')) return;
        
        const items = list.querySelectorAll('li');
        if (items.length === 0) return;

        if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(e.key)) {
            e.preventDefault();
        } else {
            return;
        }

        let activeIdx = Array.from(items).findIndex(item => item.classList.contains('active'));
        
        if (e.key === 'ArrowDown') {
            activeIdx = (activeIdx + 1) % items.length;
            updateActiveListItem(items, activeIdx);
        } else if (e.key === 'ArrowUp') {
            activeIdx = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
            updateActiveListItem(items, activeIdx);
        } else if (e.key === 'Enter') {
            const targetIdx = activeIdx > -1 ? activeIdx : 0;
            if (items[targetIdx]) {
                items[targetIdx].dispatchEvent(new MouseEvent('mousedown'));
            }
        } else if (e.key === 'Escape') {
            closeList(listId, wrapperId);
        }
    };

    const requestNextIdentifiers = () => {
        if (needsDoctorSelection()) {
            setLookupStatus('Select a doctor first.');
            return Promise.resolve();
        }
        const date = getIsoDate('appointment-date') || getIsoDate('appointment-date-filter');
        return fetch(apiUrl({ action: 'next_reg', date }))
            .then((res) => res.json())
            .then((data) => {
                if (!getValue('appointment-id') && !getValue('appointment-no')) {
                    setValue('appointment-no', data.appointment_no || '');
                }
                if (!getValue('appointment-id') && !getValue('appointment-time')) {
                    setValue('appointment-time', data.appointment_time || '');
                }
                return data;
            })
            .catch(console.error);
    };

    const setLookupStatus = (message) => {
        if (lookupStatus) {
            lookupStatus.textContent = message;
        }
    };

    const updateQueueDateLabel = () => {
        const isoDate = getIsoDate('appointment-date-filter');
        setDisplayDate('appointment-date-filter', isoDate);
        if (queueDateInput) {
            queueDateInput.value = formatDisplayDate(isoDate);
        }
    };

    const applyQueueDate = (date) => {
        const isoDate = parseDisplayDate(date);
        if (!isoDate) {
            alert('Please enter date as Day/Month/Year, for example 17/04/2026.');
            updateQueueDateLabel();
            return;
        }
        setDisplayDate('appointment-date-filter', isoDate);
        setDisplayDate('appointment-date', isoDate);
        if (queueDateInput) {
            queueDateInput.value = formatDisplayDate(isoDate);
        }
        if (!getValue('appointment-id')) {
            setValue('appointment-no', '');
            if (!getValue('patient-id')) {
                setValue('patient-reg-no', '');
            }
        }
        loadAppointments();
        requestNextIdentifiers();
    };

    const attachDateTrigger = (input) => {
        if (input.dataset.dateTriggerReady === '1') return;

        const wrapper = document.createElement('span');
        wrapper.className = 'zimrx-date-wrap';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'zimrx-date-trigger';
        trigger.setAttribute('aria-label', 'Open calendar');
        trigger.addEventListener('mousedown', (event) => event.preventDefault());
        trigger.addEventListener('click', () => {
            input.focus({ preventScroll: true });
            if (input._flatpickr) {
                input._flatpickr.open();
            }
        });
        wrapper.appendChild(trigger);
        input.dataset.dateTriggerReady = '1';
    };

    const initDatePickers = () => {
        document.querySelectorAll('.custom-date-picker').forEach((input) => {
            if (typeof flatpickr !== 'function' || input._flatpickr) return;
            flatpickr(input, {
                dateFormat: 'd/m/Y',
                allowInput: true,
                disableMobile: true,
                onChange: (_dates, dateStr) => {
                    input.value = dateStr;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    };

    const dateFromInput = (value) => {
        const isoDate = parseDisplayDate(value);
        if (!isoDate) return null;
        const [year, month, day] = isoDate.split('-').map(Number);
        return new Date(year, month - 1, day);
    };

    const appointmentQuery = (item) => {
        const params = new URLSearchParams();
        params.set('appointment_id', item.id || '');
        params.set('patient_id', item.patient_id || '');
        params.set('reg_no', item.reg_no || '');
        params.set('visit_no', item.visit_no || '');
        params.set('visit_id', item.visit_id || item.visit_code || '');
        params.set('visit_code', item.visit_id || item.visit_code || '');
        params.set('referral_category', item.referral_category || 'self');
        params.set('referral_name', item.referral_name || '');
        return params.toString();
    };

    const renderStartAction = (item) => {
        if (isAssistant) {
            return '<button type="button" class="queue-start-btn vitals-btn" data-action="vitals">Enter Vitals</button>';
        }
        const href = `prescription.php?${appointmentQuery(item)}`;
        return `<a class="queue-start-btn prescribe-btn" href="${escapeHtml(href)}">Prescribe</a>`;
    };

    const tokenPrintUrl = (itemOrId) => {
        const id = typeof itemOrId === 'object' ? itemOrId.id : itemOrId;
        const doctorId = getActiveDoctorId();
        return `appointment_token_print.php?id=${encodeURIComponent(id || '')}${doctorId ? `&doctor_id=${encodeURIComponent(doctorId)}` : ''}`;
    };

    const formatVitalsMeta = (item) => [
        item.bp ? `BP ${item.bp}` : '',
        item.pulse ? `Pulse ${item.pulse}` : '',
        item.temperature ? `Temp ${item.temperature}` : '',
        item.spo2 ? `SpO2 ${item.spo2}` : '',
        item.resp_rate ? `RR ${item.resp_rate}` : ''
    ].filter(Boolean).join(' | ');

    const formatVisitCode = (regNo, visitNo) => {
        const no = Number.parseInt(visitNo, 10);
        if (!regNo || !Number.isFinite(no) || no <= 0) return '';
        return `${regNo}-V${String(no).padStart(3, '0')}`;
    };

    const setVisitIdentifiers = (visitNo, visitId, regNo = getValue('patient-reg-no')) => {
        const normalizedVisitNo = visitNo || '';
        setValue('visit-no', normalizedVisitNo);
        setValue('visit-code', visitId || formatVisitCode(regNo, normalizedVisitNo) || '');
    };

    const fillPatient = (patient, message = 'Existing patient selected.') => {
        closeAllLookups();
        window.PatientParticulars?.setValues(patient, { baseDateId: 'appointment-date' });
        setVisitIdentifiers(patient.next_visit_no || '1', patient.next_visit_id || patient.next_visit_code || '', patient.reg_no || '');
        setLookupStatus(message);
    };

    const clearPatientFields = ({ keepMobile = false, keepName = false } = {}) => {
        window.PatientParticulars?.clearValues({ keepMobile, keepName });
        setVisitIdentifiers('', '');
    };

    const startNewPatient = (mobile, newRegNo) => {
        const typedName = getValue('patient-id') ? '' : getValue('patient-name');
        clearPatientFields({ keepName: Boolean(typedName) });
        setValue('patient-mobile', mobile);
        setValue('patient-name', typedName);
        setValue('patient-reg-no', '');
        setVisitIdentifiers('', '');
        setLookupStatus('New patient selected. Enter name and save the appointment.');
        closeAllLookups();
        field('patient-name')?.focus();
    };

    const setForm = (item = null, options = {}) => {
        if (!item) {
            setValue('appointment-id', '');
            if (!options.preserveIdentifiers) {
                setValue('appointment-no', '');
            }
            setDisplayDate('appointment-date', getIsoDate('appointment-date-filter'));
            if (!options.preserveIdentifiers) {
                setValue('appointment-time', '');
            }
            setValue('appointment-notes', '');
            clearPatientFields();
            setLookupStatus('New registration will be generated automatically.');
            if (!options.skipNextIdentifiers) {
                requestNextIdentifiers();
            }
            return;
        }

        setValue('appointment-id', item.id || '');
        setValue('appointment-no', item.appointment_no || '');
        setDisplayDate('appointment-date', item.appointment_date || getIsoDate('appointment-date-filter'));
        setValue('appointment-time', item.appointment_time || '');
        setValue('appointment-notes', item.notes || '');
        setVisitIdentifiers(item.visit_no || '1', item.visit_id || item.visit_code || '', item.reg_no || '');
        fillPatient({
            id: item.patient_id || '',
            reg_no: item.reg_no || '',
            patient_name: item.patient_name || '',
            age: item.age || '',
            age_unit: item.age_unit || 'Years',
            dob: item.dob || '',
            gender: item.gender || '',
            blood_group: item.blood_group || '',
            mobile: item.mobile || '',
            occupation: item.occupation || '',
            address: item.address || '',
            weight: item.weight || '',
            weight_unit: item.weight_unit || 'kg',
            height: item.height || '',
            height_unit: item.height_unit || 'inch',
            referral_category: item.referral_category || 'self',
            referral_name: item.referral_name || '',
            next_visit_no: item.visit_no || '1',
            next_visit_id: item.visit_id || item.visit_code || '',
            next_visit_code: item.visit_id || item.visit_code || ''
        }, `Editing appointment for ${item.reg_no || 'selected patient'}.`);
        window.scrollTo({ top: form.offsetTop - 70, behavior: 'smooth' });
    };

    const formatPatientMeta = (item) => [
        [item.age, item.age_unit].filter(Boolean).join(' '),
        item.gender,
        item.blood_group
    ].filter(Boolean).join(' | ');

    const openVitalsModal = (item) => {
        setValue('vitals-appointment-id', item.id || '');
        setValue('vitals-bp', item.bp || '');
        setValue('vitals-pulse', item.pulse || '');
        setValue('vitals-temperature', item.temperature || '');
        setValue('vitals-spo2', item.spo2 || '');
        setValue('vitals-resp-rate', item.resp_rate || '');
        setValue('vitals-note', item.vitals_note || '');

        const label = [
            item.appointment_no ? `SL ${item.appointment_no}` : '',
            item.reg_no || '',
            item.patient_name || '',
            item.visit_id || item.visit_code || ''
        ].filter(Boolean).join(' | ');
        field('vitals-patient-label').textContent = label;
        vitalsModal.hidden = false;
        field('vitals-bp')?.focus();
    };

    const closeVitalsModal = () => {
        if (vitalsModal) {
            vitalsModal.hidden = true;
        }
    };

    const openPaymentModal = (item) => {
        setValue('payment-appointment-id', item.id || '');
        setValue('payment-visit-fee', item.visit_fee ?? item.calculated_visit_fee ?? currentSettings?.visit_fee ?? 500);
        setValue('payment-discount', item.discount ?? 0);
        setValue('payment-discount-note', item.discount_note || '');
        setValue('payment-paid-amount', item.paid_amount ?? '');
        updatePaymentPayable();
        loadDiscountCauses();
        field('payment-patient-label').textContent = [
            item.appointment_no ? `SL ${item.appointment_no}` : '',
            item.reg_no || '',
            item.patient_name || '',
            item.visit_id || item.visit_code || ''
        ].filter(Boolean).join(' | ');
        paymentModal.hidden = false;
        field('payment-paid-amount')?.focus();
    };

    const closePaymentModal = () => {
        if (paymentModal) {
            paymentModal.hidden = true;
        }
    };

    const openSettingsModal = () => {
        if (!settingsModal) return;
        renderSettingsForm(currentSettings || {});
        settingsModal.hidden = false;
        field('setting-default-start-time')?.focus();
    };

    const closeSettingsModal = () => {
        if (settingsModal) {
            settingsModal.hidden = true;
        }
    };

    const renderSettingsForm = (settings) => {
        setValue('setting-default-start-time', settings.default_start_time || '14:00');
        setValue('setting-minutes-per-patient', settings.minutes_per_patient ?? 5);
        setValue('setting-blank-slots', settings.blank_slots ?? 3);
        setValue('setting-visit-fee', settings.visit_fee ?? 500);
        setValue('setting-revisit-fee', settings.revisit_fee ?? 400);
        setValue('setting-revisit-validity', settings.revisit_validity_days ?? 60);

        const overrides = settings.weekday_overrides || {};
        document.querySelectorAll('.weekday-row').forEach((row) => {
            const day = row.dataset.weekday;
            const rule = overrides[day] || {};
            row.querySelector('.weekday-closed').checked = Boolean(rule.closed);
            row.querySelector('.weekday-start-time').value = rule.start_time || '';
        });

        const selectedFields = new Set(settings.token_fields || defaultTokenFields);
        document.querySelectorAll('#token-field-grid input[type="checkbox"]').forEach((checkbox) => {
            checkbox.checked = selectedFields.has(checkbox.value);
        });
    };

    const collectSettingsForm = () => {
        const weekdayOverrides = {};
        document.querySelectorAll('.weekday-row').forEach((row) => {
            const day = row.dataset.weekday;
            const closed = row.querySelector('.weekday-closed').checked;
            const startTime = row.querySelector('.weekday-start-time').value;
            if (closed || startTime) {
                weekdayOverrides[day] = { closed, start_time: startTime };
            }
        });

        return {
            action: 'settings',
            default_start_time: getValue('setting-default-start-time') || '14:00',
            minutes_per_patient: getValue('setting-minutes-per-patient') || '5',
            blank_slots: getValue('setting-blank-slots') || '0',
            visit_fee: getValue('setting-visit-fee') || '0',
            revisit_fee: getValue('setting-revisit-fee') || '0',
            revisit_validity_days: getValue('setting-revisit-validity') || '0',
            weekday_overrides: weekdayOverrides,
            token_fields: Array.from(document.querySelectorAll('#token-field-grid input[type="checkbox"]:checked')).map((checkbox) => checkbox.value)
        };
    };

    const loadSettings = () => {
        if (needsDoctorSelection()) return Promise.resolve();
        return fetch(apiUrl({ action: 'settings' }))
        .then((res) => res.json())
        .then((data) => {
            currentSettings = data.settings || currentSettings;
            return currentSettings;
        })
        .catch(console.error);
    };

    const money = (value) => {
        const amount = Number.parseFloat(value ?? 0);
        if (!Number.isFinite(amount)) return '0';
        return amount % 1 === 0 ? String(amount.toFixed(0)) : amount.toFixed(2);
    };

    const updatePaymentPayable = () => {
        const fee = Number.parseFloat(getValue('payment-visit-fee') || '0');
        const discount = Number.parseFloat(getValue('payment-discount') || '0');
        const payable = Math.max(0, (Number.isFinite(fee) ? fee : 0) - (Number.isFinite(discount) ? discount : 0));
        setValue('payment-payable-amount', payable % 1 === 0 ? String(payable.toFixed(0)) : payable.toFixed(2));
    };

    const loadDiscountCauses = () => {
        if (discountCausesLoaded) return;
        fetch(apiUrl({ action: 'discount_causes' }))
            .then((res) => res.json())
            .then((data) => {
                const datalist = field('discount-cause-list');
                if (!datalist) return;
                datalist.innerHTML = '';
                (data.causes || []).forEach((cause) => {
                    const option = document.createElement('option');
                    option.value = cause;
                    datalist.appendChild(option);
                });
                discountCausesLoaded = true;
            })
            .catch(console.error);
    };

    const formatLastVisitLabel = (label) => {
        const value = String(label || '').trim();
        const match = value.match(/^(.+?)\s+(\(.+\))$/);
        if (!match) return escapeHtml(value);
        return `${escapeHtml(match[1])}<span>${escapeHtml(match[2])}</span>`;
    };

    const closeHistoryModal = () => {
        historyModal.hidden = true;
    };

    const renderHistoryRows = (rows) => {
        historyList.innerHTML = '';
        if (!rows.length) {
            historyList.innerHTML = '<tr><td colspan="7" class="empty-appointments">No previous appointments found.</td></tr>';
            return;
        }

        rows.forEach((row) => {
            const statusClass = String(row.status || 'Pending').toLowerCase();
            const paidClass = String(row.paid_status || 'Not Paid').toLowerCase().replace(/\s+/g, '-');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(formatDisplayDate(row.appointment_date || ''))}</td>
                <td>${escapeHtml(row.doctor_name || '')}</td>
                <td>${escapeHtml(row.appointment_no || '')}</td>
                <td>${escapeHtml(row.visit_no || '')}</td>
                <td>${escapeHtml(row.appointment_time || '')}</td>
                <td><span class="status-pill ${escapeHtml(statusClass)}">${escapeHtml(row.status || 'Pending')}</span></td>
                <td><span class="payment-pill ${escapeHtml(paidClass)}">${escapeHtml(row.paid_status || 'Not Paid')}</span></td>
            `;
            historyList.appendChild(tr);
        });
    };

    const openAppointmentHistory = (item) => {
        field('history-patient-title').textContent = item.patient_name || 'Previous Appointments';
        field('history-patient-label').textContent = [item.reg_no, item.mobile].filter(Boolean).join(' | ');
        historyList.innerHTML = '<tr><td colspan="7" class="empty-appointments">Loading history...</td></tr>';
        historyModal.hidden = false;
        const params = new URLSearchParams();
        params.set('action', 'appointment_history');
        params.set('id', item.id || '');
        params.set('patient_id', item.patient_id || '');
        params.set('reg_no', item.reg_no || '');
        const doctorId = getActiveDoctorId();
        if (doctorId) {
            params.set('doctor_id', doctorId);
        }
        fetch(`${api}?${params.toString()}`)
            .then((res) => res.json())
            .then((data) => renderHistoryRows(data.history || []))
            .catch(() => {
                historyList.innerHTML = '<tr><td colspan="7" class="empty-appointments">Could not load history.</td></tr>';
            });
    };

    const renderRows = (items) => {
        list.innerHTML = '';
        updateQueueDateLabel();
        count.textContent = `${items.length} appointment${items.length === 1 ? '' : 's'}`;

        if (needsDoctorSelection()) {
            count.textContent = 'Select doctor';
            list.innerHTML = '<tr><td colspan="12" class="empty-appointments">Select a doctor to load appointments.</td></tr>';
            return;
        }

        if (!items.length) {
            list.innerHTML = '<tr><td colspan="12" class="empty-appointments">No appointments for this date.</td></tr>';
            return;
        }

        items.forEach((item) => {
            const statusClass = String(item.status || 'Pending').toLowerCase();
            const vitalsMeta = formatVitalsMeta(item);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="queue-start-cell">${renderStartAction(item)}</td>
                <td><button type="button" class="status-pill ${escapeHtml(statusClass)}">${escapeHtml(item.status || 'Pending')}</button></td>
                <td class="serial-cell">${escapeHtml(item.appointment_no || '')}</td>
                <td class="reg-cell">${escapeHtml(item.reg_no || '')}</td>
                <td>
                    <strong>${escapeHtml(item.patient_name || '')}</strong>
                    ${vitalsMeta ? `<span class="vitals-summary">${escapeHtml(vitalsMeta)}</span>` : ''}
                    ${item.address ? `<span>${escapeHtml(item.address)}</span>` : ''}
                </td>
                <td>${escapeHtml(item.mobile || '')}</td>
                <td>${escapeHtml(item.visit_no || '')}</td>
                <td class="last-visit-cell">${formatLastVisitLabel(item.last_visit_label)}</td>
                <td class="money-cell">${escapeHtml(money(item.visit_fee ?? item.calculated_visit_fee))}</td>
                <td><span class="payment-pill ${String(item.paid_status || '').toLowerCase().replace(/\s+/g, '-')}">${escapeHtml(item.paid_status || 'Not Paid')}</span></td>
                <td>${escapeHtml(item.appointment_time || '')}</td>
                <td class="row-actions">
                    <button type="button" data-action="edit">Edit</button>
                    <button type="button" data-action="done">${statusClass === 'done' ? 'Set Pending' : 'Set Done'}</button>
                    <button type="button" data-action="payment">Payment</button>
                    <a href="${tokenPrintUrl(item)}" target="_blank">Print</a>
                    <button type="button" data-action="history">Appointment History</button>
                    <button type="button" data-action="cancel">Cancel</button>
                    <button type="button" data-action="delete">Delete</button>
                </td>
            `;
            tr.querySelector('[data-action="edit"]').addEventListener('click', () => setForm(item));
            tr.querySelector('[data-action="history"]').addEventListener('click', () => openAppointmentHistory(item));
            tr.querySelector('[data-action="done"]').addEventListener('click', () => saveStatus(item.id, statusClass === 'done' ? 'Pending' : 'Done'));
            tr.querySelector('[data-action="payment"]').addEventListener('click', () => openPaymentModal(item));
            tr.querySelector('[data-action="cancel"]').addEventListener('click', () => saveStatus(item.id, 'Cancelled'));
            tr.querySelector('[data-action="delete"]').addEventListener('click', () => deleteAppointment(item.id));
            tr.querySelector('[data-action="vitals"]')?.addEventListener('click', () => openVitalsModal(item));
            list.appendChild(tr);
        });
    };

    const loadAppointments = () => {
        if (needsDoctorSelection()) {
            renderRows([]);
            return Promise.resolve();
        }
        return fetch(apiUrl({ date: getIsoDate('appointment-date-filter') }))
        .then((res) => res.json())
        .then((data) => {
            if (data.needs_doctor) {
                renderRows([]);
                return;
            }
            currentSettings = data.settings || currentSettings;
            renderRows(data.appointments || []);
            if (!getValue('appointment-id') && !getValue('appointment-no')) {
                setValue('appointment-no', data.appointment_no || '');
            }
            if (!getValue('appointment-id') && !getValue('appointment-time')) {
                setValue('appointment-time', data.appointment_time || '');
            }
        })
        .catch(console.error);
    };

    const saveStatus = (id, status) => fetch(api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(apiPayload({ action: 'status', id, status }))
    }).then(loadAppointments);

    const deleteAppointment = (id) => {
        if (!confirm('Delete this appointment?')) return Promise.resolve();
        return fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(apiPayload({ action: 'delete', id }))
        }).then(loadAppointments);
    };


    const renderDoctorOptions = () => {
        const search = field('appointment-doctor-search');
        const doctorList = field('appointment-doctor-list');
        if (!search || !doctorList) return;
        const term = search.value.trim().toLowerCase();
        doctorList.innerHTML = '';
        appointmentDoctors
            .filter((doctor) => String(doctor.display_name || '').toLowerCase().includes(term))
            .slice(0, 20)
            .forEach((doctor) => {
                const li = document.createElement('li');
                li.className = 'patient-lookup-option';
                li.innerHTML = `<strong>${escapeHtml(doctor.display_name || '')}</strong><span>${escapeHtml(doctor.doctor_code || '')}${doctor.specialty ? ' | ' + escapeHtml(doctor.specialty) : ''}</span>`;
                li.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    setValue('active-doctor-id', doctor.id || '');
                    setValue('appointment-doctor-search', doctor.display_name || '');
                    closeList('appointment-doctor-list', 'appointment-doctor-wrapper');
                    discountCausesLoaded = false;
                    setForm();
                    loadSettings();
                    loadAppointments();
                });
                doctorList.appendChild(li);
            });
        if (doctorList.children.length) {
            if (doctorList.firstElementChild) {
                doctorList.firstElementChild.classList.add('active');
            }
            doctorList.classList.add('show');
            field('appointment-doctor-wrapper')?.classList.add('open');
        } else {
            closeList('appointment-doctor-list', 'appointment-doctor-wrapper');
        }
    };

    const initDoctorSelector = () => {
        const search = field('appointment-doctor-search');
        if (!search) return;
        if (appointmentDoctors.length === 1) {
            setValue('active-doctor-id', appointmentDoctors[0].id || '');
            setValue('appointment-doctor-search', appointmentDoctors[0].display_name || '');
            return;
        }
        search.addEventListener('input', () => {
            setValue('active-doctor-id', '');
            renderDoctorOptions();
            renderRows([]);
        });
        search.addEventListener('focus', renderDoctorOptions);
        search.addEventListener('click', renderDoctorOptions);
        search.addEventListener('keydown', (e) => handleListKeydown(e, 'appointment-doctor-list', 'appointment-doctor-wrapper'));
        search.addEventListener('blur', () => {
            window.setTimeout(() => closeList('appointment-doctor-list', 'appointment-doctor-wrapper'), 120);
        });
    };

    const refreshAppointments = () => {
        const button = field('appointment-refresh');
        button?.classList.add('appointment-refreshing');
        return loadAppointments().finally(() => {
            button?.classList.remove('appointment-refreshing');
            button?.classList.add('appointment-refreshed');
            window.setTimeout(() => button?.classList.remove('appointment-refreshed'), 900);
        });
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const patientName = getValue('patient-name');
        if (!patientName) {
            if (typeof zrxShowFieldValidation === 'function') {
                zrxShowFieldValidation(field('patient-name'), 'Please fill out this field.');
            } else {
                field('patient-name')?.focus();
            }
            return;
        }
        if (needsDoctorSelection()) {
            if (typeof zrxShowFieldValidation === 'function' && field('appointment-doctor-search')) {
                zrxShowFieldValidation(field('appointment-doctor-search'), 'Please select a doctor first.');
            } else {
                alert('Please select a doctor first.');
                field('appointment-doctor-search')?.focus();
            }
            return;
        }
        if (!getIsoDate('appointment-date')) {
            if (typeof zrxShowFieldValidation === 'function' && field('appointment-date')) {
                zrxShowFieldValidation(field('appointment-date'), 'Please enter appointment date as DD/MM/YYYY.');
            } else {
                alert('Please enter appointment date as Day/Month/Year, for example 17/04/2026.');
                field('appointment-date')?.focus();
            }
            return;
        }
        if (getValue('patient-dob') && !getIsoDate('patient-dob')) {
            if (typeof zrxShowFieldValidation === 'function' && field('patient-dob')) {
                zrxShowFieldValidation(field('patient-dob'), 'Please enter DOB as DD/MM/YYYY.');
            } else {
                alert('Please enter DOB as Day/Month/Year, for example 17/04/2026.');
                field('patient-dob')?.focus();
            }
            return;
        }
        fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(values())
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                setForm();
                loadAppointments();
            })
            .catch(console.error);
    });

    vitalsForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(apiPayload({
                action: 'vitals',
                id: getValue('vitals-appointment-id'),
                bp: getValue('vitals-bp'),
                pulse: getValue('vitals-pulse'),
                temperature: getValue('vitals-temperature'),
                spo2: getValue('vitals-spo2'),
                resp_rate: getValue('vitals-resp-rate'),
                vitals_note: getValue('vitals-note')
            }))
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                closeVitalsModal();
                loadAppointments();
            })
            .catch(console.error);
    });

    settingsForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(apiPayload(collectSettingsForm()))
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                currentSettings = data.settings || currentSettings;
                closeSettingsModal();
                setValue('appointment-no', '');
                setValue('appointment-time', '');
                loadAppointments();
            })
            .catch(console.error);
    });

    paymentForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        const mode = paymentSubmitMode;
        const appointmentId = getValue('payment-appointment-id');
        fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(apiPayload({
                action: 'payment',
                id: appointmentId,
                visit_fee: getValue('payment-visit-fee'),
                discount: getValue('payment-discount'),
                discount_note: getValue('payment-discount-note'),
                paid_amount: getValue('payment-paid-amount')
            }))
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                closePaymentModal();
                loadAppointments();
                if (mode === 'print') {
                    window.open(tokenPrintUrl(appointmentId), '_blank');
                }
            })
            .catch(console.error);
    });

    field('payment-visit-fee')?.addEventListener('input', updatePaymentPayable);
    field('payment-discount')?.addEventListener('input', updatePaymentPayable);

    document.querySelectorAll('[data-payment-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            paymentSubmitMode = button.dataset.paymentMode || 'save';
        });
    });

    document.querySelectorAll('[data-vitals-close]').forEach((button) => {
        button.addEventListener('click', closeVitalsModal);
    });

    document.querySelectorAll('[data-payment-close]').forEach((button) => {
        button.addEventListener('click', closePaymentModal);
    });

    document.querySelectorAll('[data-history-close]').forEach((button) => {
        button.addEventListener('click', closeHistoryModal);
    });

    document.querySelectorAll('[data-settings-close]').forEach((button) => {
        button.addEventListener('click', closeSettingsModal);
    });

    field('appointment-settings-open')?.addEventListener('click', openSettingsModal);

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.autocomplete-wrapper')) {
            closeAllLookups();
        }
    });

    filterDate.addEventListener('change', () => applyQueueDate(filterDate.value));

    queueDateInput?.addEventListener('change', () => applyQueueDate(queueDateInput.value));

    field('appointment-date').addEventListener('change', () => {
        applyQueueDate(getValue('appointment-date'));
        window.PatientParticulars?.syncAgeFromDob({ baseDateId: 'appointment-date' });
    });

    field('appointment-refresh').addEventListener('click', refreshAppointments);
    field('appointment-reset').addEventListener('click', () => setForm());

    initDatePickers();
    initDoctorSelector();
    loadSettings();
    window.PatientParticulars?.init({
        baseDateId: 'appointment-date',
        allowNewPatient: true,
        getDate: () => getIsoDate('appointment-date'),
        onClearId: () => {
            if (field('patient-id')?.value) setValue('patient-id', '');
        },
        onPatientSelected: (patient) => {
            fillPatient(patient, `Loaded ${patient.reg_no || 'old patient'} from patient registry.`);
        },
        onNewPatient: (mobile, newRegNo) => {
            startNewPatient(mobile, newRegNo);
        }
    });
    setForm(null, { preserveIdentifiers: true, skipNextIdentifiers: true });
    loadAppointments();
    if (window.ZimRxDropdown && typeof window.ZimRxDropdown.autoEnhance === 'function') {
        window.ZimRxDropdown.autoEnhance();
    }
})();


(function () {
        let revealed = false;

        function revealAppointmentsPage() {
            if (revealed || !document.body) {
                return;
            }

            revealed = true;
            requestAnimationFrame(function () {
                document.body.classList.add('zimrx-appointments-ready');
            });
        }

        if (document.readyState === 'complete') {
            revealAppointmentsPage();
        } else {
            window.addEventListener('load', revealAppointmentsPage, { once: true });
            window.setTimeout(revealAppointmentsPage, 2500);
        }
    })();

function switchAppointmentTab(tab) {
    const isQueue = tab === 'queue';
    const btnQueue = document.getElementById('btn-tab-queue');
    const btnFollowup = document.getElementById('btn-tab-followup');
    const panelQueue = document.getElementById('panel-tab-queue');
    const panelFollowup = document.getElementById('panel-tab-followup');

    if (btnQueue) {
        btnQueue.classList.toggle('active', isQueue);
        btnQueue.setAttribute('aria-selected', isQueue ? 'true' : 'false');
    }
    if (btnFollowup) {
        btnFollowup.classList.toggle('active', !isQueue);
        btnFollowup.setAttribute('aria-selected', !isQueue ? 'true' : 'false');
    }
    if (panelQueue) panelQueue.hidden = !isQueue;
    if (panelFollowup) panelFollowup.hidden = isQueue;

    try {
        if (history.replaceState) {
            history.replaceState(null, null, isQueue ? '#appointments' : '#followups');
        }
    } catch (e) {}
}

function getCurrentFollowupFilter() {
    const activeChip = document.querySelector('.followup-chip-btn.active');
    return activeChip ? (activeChip.getAttribute('data-filter') || 'all') : 'all';
}

function filterFollowupStatus(status, btn) {
    if (btn) {
        document.querySelectorAll('.followup-chip-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    const targetFilter = status || getCurrentFollowupFilter();

    const rows = document.querySelectorAll('#followup-table-body tr.followup-row');
    const query = (document.getElementById('followup-search-input')?.value || '').toLowerCase().trim();
    let visibleCount = 0;

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status') || '';
        const rowText = row.textContent.toLowerCase();
        const matchesStatus = (targetFilter === 'all' || rowStatus === targetFilter);
        const matchesQuery = !query || rowText.includes(query);
        const show = matchesStatus && matchesQuery;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    const countLabel = document.getElementById('followup-count-label');
    if (countLabel) {
        countLabel.textContent = `${visibleCount} scheduled follow-up${visibleCount === 1 ? '' : 's'}`;
    }
}

function quickQueueFollowup(p) {
    switchAppointmentTab('queue');
    if (!p) return;

    if (p.reg_no) {
        const regInput = document.getElementById('patient-reg-no');
        if (regInput) {
            regInput.value = p.reg_no;
            regInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    if (p.patient_name) {
        const nameInput = document.getElementById('patient-name');
        if (nameInput) nameInput.value = p.patient_name;
    }
    if (p.mobile) {
        const mobInput = document.getElementById('patient-mobile');
        if (mobInput) mobInput.value = p.mobile;
    }
    if (p.age) {
        const ageInput = document.getElementById('patient-age');
        if (ageInput) ageInput.value = p.age;
    }
    if (p.gender) {
        const genderSelect = document.getElementById('patient-gender');
        if (genderSelect) genderSelect.value = p.gender;
    }
    if (p.address) {
        const addrInput = document.getElementById('patient-address');
        if (addrInput) addrInput.value = p.address;
    }

    const formCard = document.getElementById('appointment-form');
    if (formCard) {
        formCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        formCard.style.outline = '3px solid #2563eb';
        setTimeout(() => { formCard.style.outline = ''; }, 1800);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#followups' || window.location.hash === '#followup') {
        switchAppointmentTab('followup');
    }
});
