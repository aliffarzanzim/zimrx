// Prescription workspace: patient demographics, clinical entries, and save workflow.
document.addEventListener('DOMContentLoaded', () => {
            let currentVisitRevision = parseInt(document.getElementById('visit-revision')?.value || '1', 10) || 1;

            // Datepicker initialization
            flatpickr(".custom-date-picker", {
                dateFormat: "d/m/Y",
                allowInput: true,
                onChange: function(_, __, instance) {
                    instance.input.dispatchEvent(new Event('input', { bubbles: true }));
                    instance.input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            function setInputValue(field, value) {
                if (!field) return;
                field.value = value ?? '';
            }

            function setSelectValue(field, value) {
                if (!field) return;
                const normalized = String(value ?? '');
                const match = Array.from(field.options).find((option) => option.value === normalized);
                if (match || normalized === '') {
                    field.value = normalized;
                }
            }

            function formatIsoDateForInput(value) {
                const date = String(value || '').slice(0, 10);
                const match = date.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
            }

            function setDateValue(id, value) {
                const field = document.getElementById(id);
                if (!field || !value) return;
                const formatted = formatIsoDateForInput(value);
                if (field._flatpickr) {
                    field._flatpickr.setDate(formatted, false, 'd/m/Y');
                } else {
                    setInputValue(field, formatted);
                }
            }

            function selectPatient(p) {
                window.PatientParticulars?.setValues(p);
                syncPatientProfileButton();
            }

            function syncPatientProfileButton() {
                const btn = document.getElementById('btn-open-patient-profile');
                if (!btn) return;
                const patientId = parseInt(document.getElementById('patient-id')?.value || '0', 10);
                const hasPatient = Number.isInteger(patientId) && patientId > 0;
                btn.disabled = !hasPatient;
                btn.classList.toggle('disabled', !hasPatient);
            }

            const profileBtn = document.getElementById('btn-open-patient-profile');
            if (profileBtn) {
                profileBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const patientId = parseInt(document.getElementById('patient-id')?.value || '0', 10);
                    if (patientId > 0) {
                        window.open(`emr.php?patient_id=${encodeURIComponent(patientId)}`, '_blank');
                    }
                });
            }

            syncPatientProfileButton();

            window.PatientParticulars?.init({
                baseDateId: 'patient-date',
                onPatientSelected: selectPatient
            });

            function applyAppointmentToForm(appointment) {
                if (!appointment || typeof appointment !== 'object') return;
                const has = (key) => Object.prototype.hasOwnProperty.call(appointment, key);
                const fillInput = (key, id) => {
                    if (has(key)) setInputValue(document.getElementById(id), appointment[key] || '');
                };
                const fillSelect = (key, id, fallback = '') => {
                    if (has(key)) setSelectValue(document.getElementById(id), appointment[key] || fallback);
                };
                const fillDate = (key, id) => {
                    if (has(key)) setDateValue(id, appointment[key] || '');
                };

                fillInput('id', 'appointment-id');
                fillInput('appointment_no', 'appointment-no');
                fillInput('appointment_time', 'appointment-time');
                fillInput('patient_id', 'patient-id');
                fillInput('reg_no', 'patient-reg-no');
                fillInput('patient_name', 'patient-name');
                fillInput('age', 'patient-age');
                fillSelect('age_unit', 'patient-age-unit', 'Years');
                fillDate('dob', 'patient-dob');
                fillSelect('gender', 'patient-gender');
                fillSelect('blood_group', 'patient-blood-group');
                fillInput('mobile', 'patient-mobile');
                fillInput('occupation', 'patient-occupation');
                fillInput('address', 'patient-address');
                fillInput('weight', 'patient-weight');
                fillSelect('weight_unit', 'patient-weight-unit', 'kg');
                fillInput('height', 'patient-height');
                fillSelect('height_unit', 'patient-height-unit', 'inch');
                fillInput('visit_no', 'visit-no');
                fillInput('visit_code', 'visit-code');
                fillInput('visit_record_id', 'visit-record-id');
                fillInput('revision', 'visit-revision');
                if (has('revision') && appointment.revision !== undefined) {
                    currentVisitRevision = Number(appointment.revision || 1);
                }
                fillDate('appointment_date', 'patient-date');

                if (has('referral_category') || has('referral_name')) {
                    window.PatientParticulars?.setReferralValue({
                        category: appointment.referral_category,
                        name: appointment.referral_name
                    });
                }
                syncPatientProfileButton();
            }

            function applyQueryFallbackToForm() {
                const params = new URLSearchParams(window.location.search || '');
                if (!params.get('appointment_id') && !params.get('patient_id') && !params.get('reg_no')) {
                    return;
                }

                applyAppointmentToForm({
                    id: params.get('appointment_id') || '',
                    patient_id: params.get('patient_id') || '',
                    reg_no: params.get('reg_no') || '',
                    visit_no: params.get('visit_no') || '',
                    visit_code: params.get('visit_code') || '',
                    referral_category: params.get('referral_category') || 'self',
                    referral_name: params.get('referral_name') || ''
                });
            }

            async function loadAppointmentFromQuery() {
                const params = new URLSearchParams(window.location.search || '');
                const appointmentId = params.get('appointment_id') || '';
                if (!appointmentId) {
                    return;
                }

                try {
                    const response = await fetch(`api/appointments.php?action=appointment_detail&id=${encodeURIComponent(appointmentId)}`);
                    const data = await response.json();
                    if (data.appointment) {
                        applyAppointmentToForm(data.appointment);
                    }
                } catch (err) {
                    console.error('Could not load appointment for prescription.', err);
                }
            }

            applyQueryFallbackToForm();
            loadAppointmentFromQuery();

            // Prescription visit saving logic
            async function learnRxRegimensFromRows(drugs) {
                if (!Array.isArray(drugs) || drugs.length === 0) {
                    return;
                }

                try {
                    await fetch('api/rx_learn.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ drugs }),
                        keepalive: true
                    });
                } catch (error) {
                    console.error('Could not learn Rx regimens', error);
                }
            }

            async function savePrescription(printAfter = false) {
                const refPayload = window.PatientParticulars?.getReferralPayload() || { category: 'self', name: '' };
                const patientData = {
                    action: 'create',
                    source: 'prescription',
                    status: 'Done', // Auto-complete appointment
                    id: document.getElementById('appointment-id').value,
                    appointment_date: document.getElementById('patient-date').value,
                    appointment_no: document.getElementById('appointment-no').value || 0,
                    appointment_time: document.getElementById('appointment-time').value || '',
                    patient_id: document.getElementById('patient-id').value,
                    reg_no: document.getElementById('patient-reg-no').value,
                    patient_name: document.getElementById('patient-name').value,
                    age: document.getElementById('patient-age').value,
                    age_unit: document.getElementById('patient-age-unit').value,
                    dob: document.getElementById('patient-dob').value,
                    gender: document.getElementById('patient-gender').value,
                    blood_group: document.getElementById('patient-blood-group').value,
                    mobile: document.getElementById('patient-mobile').value,
                    occupation: document.getElementById('patient-occupation').value,
                    address: document.getElementById('patient-address').value,
                    weight: document.getElementById('patient-weight').value,
                    weight_unit: document.getElementById('patient-weight-unit').value,
                    height: document.getElementById('patient-height').value,
                    height_unit: document.getElementById('patient-height-unit').value,
                    referral_category: refPayload.category || 'self',
                    referral_name: refPayload.name || '',
                    visit_no: document.getElementById('visit-no').value,
                    visit_code: document.getElementById('visit-code').value,
                };

                if (!patientData.patient_name) {
                    const nameInput = document.getElementById('patient-name');
                    if (typeof zrxShowFieldValidation === 'function') {
                        zrxShowFieldValidation(nameInput, 'Please fill out this field.');
                    } else if (nameInput) {
                        nameInput.focus();
                    }
                    return;
                }

                try {
                    // Create or update patient and appointment
                    const apptRes = await fetch('api/appointments.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(patientData)
                    });
                    const apptData = await apptRes.json();
                    if (apptData.error) throw new Error(apptData.error);

                    document.getElementById('patient-id').value = apptData.patient.id;
                    document.getElementById('patient-reg-no').value = apptData.patient.reg_no;
                    document.getElementById('appointment-id').value = apptData.id;
                    if (apptData.appointment_no) {
                        document.getElementById('appointment-no').value = apptData.appointment_no;
                    }
                    if (typeof syncPatientProfileButton === 'function') syncPatientProfileButton();

                    // Extract prescribed drugs
                    const drugs = [];
                    document.querySelectorAll('#rx-tbody tr').forEach(tr => {
                        const brand = tr.querySelector('.rx-brand-input')?.value;
                        const generic = tr.querySelector('.rx-generic-input')?.value;
                        const dose = tr.querySelector('.rx-dose-input')?.value;
                        const instruction = tr.querySelector('.rx-instruction-input')?.value;
                        const duration = tr.querySelector('.rx-duration-input')?.value;
                        const brandId = tr.querySelector('.brand_id')?.value;

                        if (brand || dose || instruction || duration) {
                            drugs.push({ brand, generic, dose, instruction, duration, brand_id: brandId });
                        }
                    });

                    // Save visit link with clinical snapshot and rendered preview
                    let clinicalSnapshot = null;
                    if (typeof collectPrescriptionPreviewSnapshot === 'function') {
                        clinicalSnapshot = collectPrescriptionPreviewSnapshot();
                    }
                    let previewHtml = '';
                    const previewElem = document.getElementById('prescription-preview-content') || document.querySelector('.zrx-preview-paper');
                    if (previewElem) {
                        previewHtml = previewElem.outerHTML || previewElem.innerHTML || '';
                    }

                    const visitData = {
                        appointment_id: document.getElementById('appointment-id').value || apptData.id,
                        patient_id: apptData.patient.id,
                        reg_no: apptData.patient.reg_no,
                        visit_no: document.getElementById('visit-no').value || apptData.patient.next_visit_no,
                        visit_code: document.getElementById('visit-code').value || apptData.patient.next_visit_code,
                        revision: currentVisitRevision,
                        referral: typeof getPatientReferralPayload === 'function' ? getPatientReferralPayload() : { category: 'self', name: '' },
                        drugs: drugs,
                        clinical_snapshot: clinicalSnapshot,
                        prescription_html: previewHtml
                    };

                    const visitRes = await fetch('api/save_prescription_visit.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(visitData)
                    });
                    const visitResult = await visitRes.json();
                    if (visitRes.status === 409 || (visitResult && !visitResult.ok && visitResult.error && visitResult.error.includes('Conflict'))) {
                        alert(visitResult.error || 'Conflict: This visit has been updated by another session. Please reload before saving.');
                        return;
                    }
                    if (visitResult.error) throw new Error(visitResult.error);

                    if (visitResult.revision !== undefined) {
                        currentVisitRevision = Number(visitResult.revision || 0);
                        const revEl = document.getElementById('visit-revision');
                        if (revEl) revEl.value = currentVisitRevision;
                    }
                    if (visitResult.visit_record_id) {
                        const vrEl = document.getElementById('visit-record-id');
                        if (vrEl) vrEl.value = visitResult.visit_record_id;
                    }

                    if (visitResult.visit_no) document.getElementById('visit-no').value = visitResult.visit_no;
                    if (visitResult.visit_code) document.getElementById('visit-code').value = visitResult.visit_code;

                    await learnRxRegimensFromRows(drugs);

                    if (typeof learnCurrentPcAutocompletes === 'function') {
                        learnCurrentPcAutocompletes();
                    }

                    // Learn new address segments
                    const addressVal = document.getElementById('patient-address').value.trim();
                    const cleanAddress = addressVal.replace(/,\s*$/, "");
                    if (cleanAddress) {
                        fetch('api/save_custom_address.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ address: cleanAddress })
                        }).catch(console.error);
                    }

                    // Learn custom occupation
                    const occVal = document.getElementById('patient-occupation')?.value?.trim();
                    if (occVal) {
                        fetch('api/save_custom_occupation.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ occupation: occVal })
                        }).catch(console.error);
                    }

                    if (printAfter) {
                        window.open('prescription_preview.php', '_blank');
                    } else {
                        alert('Prescription Saved Successfully!');
                    }

                } catch (e) {
                    alert('Error: ' + e.message);
                }
            }

            function dispatchFieldChange(field) {
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function setFieldValue(field, value, options = {}) {
                field.value = value;
                if (options.dispatch === true) {
                    dispatchFieldChange(field);
                }
            }

            function clearFieldControl(field, options = {}) {
                if (!field || field.disabled) return;
                const type = (field.type || '').toLowerCase();

                if (type === 'checkbox' || type === 'radio') {
                    field.checked = false;
                    if (options.dispatch === true) {
                        dispatchFieldChange(field);
                    }
                    return;
                }

                if (field.tagName === 'SELECT') {
                    field.selectedIndex = 0;
                    if (options.dispatch === true) {
                        dispatchFieldChange(field);
                    }
                    return;
                }

                setFieldValue(field, '', options);
            }

            function closePrescriptionPopups() {
                if (typeof closeOccList === 'function') closeOccList();
                if (typeof closeAddrList === 'function') {
                    window.clearTimeout(addrTimeout);
                    closeAddrList();
                }
                document.querySelectorAll('.autocomplete-list.show, .appointment-lookup-list.show').forEach((list) => {
                    list.classList.remove('show');
                });
                document.querySelectorAll('.autocomplete-wrapper.open').forEach((wrapper) => {
                    wrapper.classList.remove('open');
                });
                document.querySelectorAll('.rx-dropdown').forEach((dropdown) => {
                    dropdown.remove();
                });
                document.querySelectorAll('.rx-warning-dropdown[open]').forEach((details) => {
                    details.open = false;
                });
                const active = document.activeElement;
                if (active && active.matches('input, textarea, select')) {
                    active.blur();
                }
            }

            function shouldSkipPrescriptionClear(field) {
                return Boolean(
                    field.closest('.patient-particulars') ||
                    field.closest('template') ||
                    field.closest('.rx-settings-modal, .pc-settings-modal, #ai-settings-panel, .tp-sub-options, .ot-print-options') ||
                    field.closest('[class*="settings"]') ||
                    field.closest('#pe-tbody')
                );
            }

            function clearPrescriptionOnly() {
                const root = document.querySelector('.app-container');
                if (!root) return;

                window.dispatchEvent(new CustomEvent('zimrx:clear-prescription-ui'));

                root.querySelectorAll('input, textarea, select').forEach((field) => {
                    if (shouldSkipPrescriptionClear(field)) return;
                    clearFieldControl(field);
                    if (field.dataset) {
                        Object.keys(field.dataset).forEach((key) => delete field.dataset[key]);
                    }
                    field.style.height = '';
                });

                document.querySelectorAll('#pe-tbody tr').forEach((row) => {
                    row.querySelectorAll('td:nth-child(3) input, td:nth-child(3) textarea').forEach(clearFieldControl);
                });

                document.querySelectorAll('#rx-tbody tr').forEach((row) => {
                    delete row.dataset.selectedDrug;
                    row.querySelectorAll('.rx-input, .brand_id').forEach((field) => {
                        clearFieldControl(field);
                        if (field.dataset) {
                            Object.keys(field.dataset).forEach((key) => delete field.dataset[key]);
                        }
                    });
                });

                const rxInfoBar = document.getElementById('rx-info-bar');
                if (rxInfoBar) {
                    rxInfoBar.innerHTML = '<div class="rx-info-empty">Drug details and selected warnings will appear here. Interaction display is controlled from Rx settings.</div>';
                    rxInfoBar.dataset.brandId = '';
                    rxInfoBar.dataset.brandName = '';
                    rxInfoBar.title = 'Drug details will appear here';
                }

                const uploadTbody = document.getElementById('reports-upload-tbody');
                if (uploadTbody) uploadTbody.innerHTML = '';
                const uploadContainer = document.getElementById('reports-upload-table-container');
                if (uploadContainer) uploadContainer.style.display = 'none';
                const reportFileInput = document.getElementById('report-file-input');
                if (reportFileInput) reportFileInput.value = '';

                document.querySelectorAll('.nicEdit-main, [contenteditable="true"]').forEach((editor) => {
                    if (editor.closest('[class*="settings"]')) return;
                    editor.innerHTML = '';
                    editor.dispatchEvent(new Event('input', { bubbles: true }));
                });

                ['zimrx_preview_snapshot', 'zimrx_preview_drugs'].forEach((key) => {
                    sessionStorage.removeItem(key);
                    localStorage.removeItem(key);
                });

                closePrescriptionPopups();
                window.setTimeout(closePrescriptionPopups, 0);
                window.setTimeout(closePrescriptionPopups, 300);
            }

            function todayDmy() {
                const now = new Date();
                const d = String(now.getDate()).padStart(2, '0');
                const m = String(now.getMonth() + 1).padStart(2, '0');
                return `${d}/${m}/${now.getFullYear()}`;
            }

            function clearPatientParticulars() {
                window.PatientParticulars?.clearValues();
                const dateField = document.getElementById('patient-date');
                if (dateField) {
                    if (dateField._flatpickr) dateField._flatpickr.setDate(todayDmy(), false, 'd/m/Y');
                    else setFieldValue(dateField, todayDmy());
                }
                if (typeof fetchNextRegNo === 'function') fetchNextRegNo();
                syncPatientProfileButton();
                closePrescriptionPopups();
                window.setTimeout(closePrescriptionPopups, 0);
                window.setTimeout(closePrescriptionPopups, 300);
            }

            const clearBtn = document.getElementById('btn-clear-fields');
            const clearMenu = document.getElementById('clear-options-menu');

            function closeClearMenu() {
                if (!clearMenu || !clearBtn) return;
                clearMenu.hidden = true;
                clearBtn.setAttribute('aria-expanded', 'false');
            }

            function toggleClearMenu() {
                if (!clearMenu || !clearBtn) return;
                clearMenu.hidden = !clearMenu.hidden;
                clearBtn.setAttribute('aria-expanded', clearMenu.hidden ? 'false' : 'true');
            }

            document.getElementById('btn-save-print').addEventListener('click', (e) => { e.preventDefault(); savePrescription(true); });
            document.getElementById('btn-save-only').addEventListener('click', (e) => { e.preventDefault(); savePrescription(false); });
            clearBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleClearMenu();
            });
            clearMenu?.addEventListener('click', (e) => {
                const action = e.target.closest('[data-clear-action]')?.dataset.clearAction;
                if (!action) return;
                e.preventDefault();
                closeClearMenu();
                if (action === 'all') {
                    clearPrescriptionOnly();
                    clearPatientParticulars();
                    return;
                }
                clearPrescriptionOnly();
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.clear-action-wrap')) {
                    closeClearMenu();
                }
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeClearMenu();
            });

        });

// Prescription page reveal guard
(function () {
    let revealed = false;

    function revealPrescriptionPage() {
        if (revealed || !document.body) {
            return;
        }

        revealed = true;
        requestAnimationFrame(function () {
            document.body.classList.add('zimrx-prescription-ready');
        });
    }

    if (document.readyState === 'complete') {
        revealPrescriptionPage();
    } else {
        window.addEventListener('load', revealPrescriptionPage, { once: true });
        window.setTimeout(revealPrescriptionPage, 2500);
    }
})();
