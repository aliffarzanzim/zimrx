// EMR identifier settings page managing patient registration and visit token generation formats.
const emrSettingsCfg = (() => {
    const el = document.getElementById('emrSettingsConfig');
    return el ? JSON.parse(el.textContent || '{}') : {};
})();
const currentYearCode = emrSettingsCfg.currentYearCode || '';
const currentDateCode = emrSettingsCfg.currentDateCode || '';
const isMultiDoctorMode = Boolean(emrSettingsCfg.isMultiDoctorMode);

function getDigits(num) {
    const val = Math.max(1, parseInt(num, 10) || 1);
    return String(val).length;
}

function setDailyFlow(val) {
    document.getElementById('daily_patient_flow').value = val;
    renderEmrPreview();
}

function setYearlyFlow(val) {
    document.getElementById('yearly_patient_flow').value = val;
    renderEmrPreview();
}

function updateEmrModes() {
    const regMode = document.querySelector('input[name="reg_id_mode"]:checked')?.value || 'sequential';
    const visitMode = document.querySelector('input[name="visit_id_mode"]:checked')?.value || 'sequential';

    document.getElementById('opt-reg-seq').classList.toggle('selected', regMode === 'sequential');
    document.getElementById('opt-reg-rand').classList.toggle('selected', regMode === 'random');
    document.getElementById('opt-visit-seq').classList.toggle('selected', visitMode === 'sequential');
    document.getElementById('opt-visit-rand').classList.toggle('selected', visitMode === 'random');

    renderEmrPreview();
}

function renderEmrPreview() {
    const dailyFlow = parseInt(document.getElementById('daily_patient_flow').value, 10) || 999;
    const yearlyFlow = parseInt(document.getElementById('yearly_patient_flow').value, 10) || 99999;
    const regMode = document.querySelector('input[name="reg_id_mode"]:checked')?.value || 'sequential';
    const visitMode = document.querySelector('input[name="visit_id_mode"]:checked')?.value || 'sequential';

    const dailyDigits = getDigits(dailyFlow);
    const yearlyDigits = getDigits(yearlyFlow);

    // Update badges
    document.getElementById('reg-digit-badge').textContent = yearlyDigits + ' Digits (' + yearlyFlow.toLocaleString() + '/yr)';
    document.getElementById('visit-digit-badge').textContent = dailyDigits + ' Digits (' + dailyFlow.toLocaleString() + '/day)';
    document.getElementById('preview-reg-tag').textContent = yearlyDigits + ' Digits';
    document.getElementById('preview-visit-tag').textContent = dailyDigits + ' Digits';

    // Update sample cards
    document.getElementById('sample-reg-seq').textContent = 'P' + currentYearCode + String(1).padStart(yearlyDigits, '0');
    const randRegSample = String(Math.floor((Math.pow(10, yearlyDigits) - 1) * 0.84932) || 84932).padStart(yearlyDigits, '0').slice(-yearlyDigits);
    document.getElementById('sample-reg-rand').textContent = 'P' + currentYearCode + randRegSample;

    document.getElementById('sample-visit-seq').textContent = 'V' + currentDateCode + String(1).padStart(dailyDigits, '0');
    const randVisitSample = String(Math.floor((Math.pow(10, dailyDigits) - 1) * 0.849) || 849).padStart(dailyDigits, '0').slice(-dailyDigits);
    document.getElementById('sample-visit-rand').textContent = 'V' + currentDateCode + randVisitSample;

    // Live registration ID preview
    let sampleReg = '';
    if (regMode === 'sequential') {
        sampleReg = 'P' + currentYearCode + String(1).padStart(yearlyDigits, '0');
    } else {
        sampleReg = 'P' + currentYearCode + randRegSample;
    }
    document.getElementById('preview-reg-val').textContent = sampleReg;
    document.getElementById('preview-reg-meta').textContent = `${regMode === 'sequential' ? 'Sequential' : 'Random'} Mode • Up to ${yearlyFlow.toLocaleString()} patients/yr`;

    // Live visit token preview
    let sampleVisit = '';
    if (visitMode === 'sequential') {
        sampleVisit = 'V' + currentDateCode + String(1).padStart(dailyDigits, '0');
    } else {
        sampleVisit = 'V' + currentDateCode + randVisitSample;
    }
    document.getElementById('preview-visit-val').textContent = sampleVisit;
    document.getElementById('preview-visit-meta').textContent = `${visitMode === 'sequential' ? 'Sequential' : 'Random'} Mode • Up to ${dailyFlow.toLocaleString()} encounters/day`;
}

// Reset to defaults confirmation modal
const resetModal = document.getElementById('emr-confirm-modal');
document.getElementById('factory-reset-btn').addEventListener('click', () => {
    resetModal.hidden = false;
});
document.getElementById('confirm-reset-cancel').addEventListener('click', () => {
    resetModal.hidden = true;
});
document.getElementById('confirm-reset-proceed').addEventListener('click', () => {
    resetModal.hidden = true;
    document.getElementById('daily_patient_flow').value = 999;
    document.getElementById('yearly_patient_flow').value = 99999;
    document.querySelector('input[name="reg_id_mode"][value="sequential"]').checked = true;
    document.querySelector('input[name="visit_id_mode"][value="sequential"]').checked = true;
    document.querySelector('input[name="auto_expand"]').checked = true;
    updateEmrModes();
});

document.getElementById('daily_patient_flow').addEventListener('input', renderEmrPreview);
document.getElementById('yearly_patient_flow').addEventListener('input', renderEmrPreview);
document.querySelectorAll('input[name="reg_id_mode"]').forEach(r => r.addEventListener('change', updateEmrModes));
document.querySelectorAll('input[name="visit_id_mode"]').forEach(r => r.addEventListener('change', updateEmrModes));

// Initial preview render
renderEmrPreview();
