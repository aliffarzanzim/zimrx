// Uploaded lab reports module managing desktop uploads, mobile QR code sync, and background upload polling.
(function() {
    const wrapper = document.getElementById('uploaded-reports-wrapper');
    if (!wrapper) return;

    const fileInput = document.getElementById('report-file-input');
    const uploadBtn = document.getElementById('report-upload-btn');
    const uploadTbody = document.getElementById('reports-upload-tbody');
    const uploadContainer = document.getElementById('reports-upload-table-container');
    const uploadTemplate = document.getElementById('reports-upload-template');

    const phoneUploadBtn = document.getElementById('btn-phone-upload-reports');
    const phoneModal = document.getElementById('phone-upload-modal');
    const qrImg = document.getElementById('phone-upload-qr-img');
    const qrSpinner = document.getElementById('phone-upload-spinner');
    const pupPatientName = document.getElementById('pup-patient-name');
    const pupPatientReg = document.getElementById('pup-patient-reg');
    const pupPatientRegWrap = document.getElementById('pup-patient-reg-wrap');
    const pupUrlInput = document.getElementById('phone-upload-url-input');
    const pupCopyBtn = document.getElementById('phone-upload-copy-btn');
    const pupOpenBtn = document.getElementById('phone-upload-open-btn');
    const pupStatusText = document.getElementById('pup-status-text');

    function updateUploadRowNumbers() {
        const rows = uploadTbody.querySelectorAll('tr.pc-row');
        rows.forEach((row, index) => {
            const noCell = row.querySelector('.pc-row-no');
            if (noCell) noCell.textContent = index + 1;
        });
        uploadContainer.style.display = rows.length > 0 ? 'block' : 'none';
    }

    function appendUploadedReportRow(filePath, originalName, reportName, dateStr) {
        if (!uploadTemplate || !uploadTbody) return;
        const tr = uploadTemplate.content.firstElementChild.cloneNode(true);
        const dateInput = tr.querySelector('.upload-date-input');

        let cleanPath = (filePath || '').trim();
        if (cleanPath.startsWith('userdata/uploads/reports/') || cleanPath.startsWith('uploads/reports/')) {
            const fileName = cleanPath.split('/').pop();
            cleanPath = 'api/view_report.php?file=' + encodeURIComponent(fileName);
        } else if (!cleanPath.startsWith('api/view_report.php') && cleanPath !== '') {
            const fileName = cleanPath.split('/').pop();
            cleanPath = 'api/view_report.php?file=' + encodeURIComponent(fileName);
        }

        tr.querySelector('.upload-name-input').value = reportName || originalName || 'Lab Report';
        tr.querySelector('.upload-filename-display').textContent = originalName || 'report';
        tr.querySelector('.upload-view-btn').href = cleanPath;
        tr.querySelector('.upload-file-path').value = cleanPath;
        dateInput.value = dateStr || new Date().toLocaleDateString('en-GB');

        if (typeof flatpickr !== 'undefined') {
            flatpickr(dateInput, { dateFormat: "d/m/Y", allowInput: true });
        }

        uploadTbody.appendChild(tr);
        updateUploadRowNumbers();

        // Subtle highlight animation
        tr.style.backgroundColor = '#ecfdf5';
        setTimeout(() => { tr.style.backgroundColor = ''; }, 2000);
    }

    // Desktop upload handling
    uploadBtn?.addEventListener('click', () => {
        fileInput?.click();
    });

    fileInput?.addEventListener('change', async () => {
        if (!fileInput.files.length) return;

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('file', file);

        const originalText = uploadBtn.innerHTML;
        uploadBtn.textContent = 'Uploading...';
        uploadBtn.disabled = true;

        try {
            const res = await fetch('api/upload_report.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.ok) {
                const fileNameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                const today = new Date();
                const d = String(today.getDate()).padStart(2, '0');
                const m = String(today.getMonth() + 1).padStart(2, '0');
                const y = today.getFullYear();
                appendUploadedReportRow(data.file_path, file.name, fileNameWithoutExt, `${d}/${m}/${y}`);
            } else {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Upload error: ' + err.message);
        } finally {
            uploadBtn.innerHTML = originalText;
            uploadBtn.disabled = false;
            fileInput.value = '';
        }
    });

    uploadTbody?.addEventListener('click', (e) => {
        if (!e.target.classList.contains('upload-del-btn')) return;
        e.preventDefault();
        if (confirm('Are you sure you want to remove this report?')) {
            e.target.closest('tr')?.remove();
            updateUploadRowNumbers();
        }
    });

    // Live patient synchronization (desktop to mobile)
    let currentDesktopActiveRevision = 1;

    function syncActivePatientToMobile() {
        const pName = document.getElementById('patient-name')?.value.trim() || 'Walk-in Patient';
        const pReg = document.getElementById('patient-reg-no')?.value.trim() || '';
        const pAge = document.getElementById('patient-age')?.value.trim() || '';
        const pGender = document.getElementById('patient-gender')?.value.trim() || '';
        const pDate = document.getElementById('patient-date')?.value.trim() || '';
        const patientId = parseInt(document.getElementById('patient-id')?.value || '0', 10);
        const visitRecordId = parseInt(document.getElementById('visit-record-id')?.value || '0', 10);

        // Update modal banner if visible
        if (pupPatientName) pupPatientName.textContent = pName;
        if (pupPatientReg && pupPatientRegWrap) {
            pupPatientReg.textContent = pReg || '--';
            pupPatientRegWrap.style.display = pReg ? 'inline-block' : 'none';
        }

        const params = new URLSearchParams({
            action: 'update_active_patient',
            csrf_token: window.ZimRxCsrfToken || '',
            patient_name: pName,
            patient_reg: pReg,
            patient_age: pAge,
            patient_gender: pGender,
            patient_date: pDate,
            patient_id: patientId,
            visit_record_id: visitRecordId
        });

        fetch('api/mobile_sync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        }).then(r => r.json()).then(res => {
            if (res.ok && res.active_revision) {
                currentDesktopActiveRevision = Number(res.active_revision);
            }
        }).catch(() => {});
    }

    // Publish active patient on load and on any change
    syncActivePatientToMobile();
    ['patient-name', 'patient-reg-no', 'patient-age', 'patient-gender', 'patient-date', 'patient-id', 'visit-record-id'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', syncActivePatientToMobile);
            el.addEventListener('change', syncActivePatientToMobile);
        }
    });
    setInterval(syncActivePatientToMobile, 8000);

    // Background upload poller (mobile to desktop table)
    setInterval(async () => {
        try {
            const patientId = parseInt(document.getElementById('patient-id')?.value || '0', 10);
            const visitRecordId = parseInt(document.getElementById('visit-record-id')?.value || '0', 10);

            const params = new URLSearchParams({
                action: 'check_uploads',
                csrf_token: window.ZimRxCsrfToken || '',
                active_revision: currentDesktopActiveRevision,
                patient_id: patientId,
                visit_record_id: visitRecordId
            });
            const res = await fetch('api/mobile_sync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            const data = await res.json();
            if (data.ok && Array.isArray(data.uploads) && data.uploads.length > 0) {
                const curPId = parseInt(document.getElementById('patient-id')?.value || '0', 10);
                data.uploads.forEach(u => {
                    const uPId = Number(u.patient_id || 0);
                    if (curPId > 0 && uPId > 0 && curPId !== uPId) {
                        return; // Reject report that does not match current active patient
                    }
                    appendUploadedReportRow(u.file_path, u.original_name, u.report_name, u.date);
                });
                if (pupStatusText) {
                    pupStatusText.textContent = `✅ Received ${data.uploads.length} new report(s) from phone!`;
                }
            }
        } catch (e) {
            // Background check failure is non-blocking
        }
    }, 2500);

    // Phone upload QR modal controller
    let cachedQrData = null;

    function openPhoneUploadModal() {
        if (!phoneModal) return;

        phoneModal.hidden = false;
        phoneModal.removeAttribute('hidden');
        phoneModal.style.display = 'flex';

        syncActivePatientToMobile();

        if (cachedQrData) {
            if (qrImg) {
                qrImg.src = cachedQrData.qr_image;
                qrImg.style.display = 'block';
            }
            if (qrSpinner) qrSpinner.style.display = 'none';
            if (pupUrlInput) pupUrlInput.value = cachedQrData.upload_url;
            if (pupOpenBtn) pupOpenBtn.href = cachedQrData.upload_url;
            return;
        }

        if (qrSpinner) qrSpinner.style.display = 'block';
        if (qrImg) qrImg.style.display = 'none';

        fetch('api/mobile_sync.php?action=get_qr')
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    cachedQrData = data;
                    if (qrImg) {
                        qrImg.src = data.qr_image;
                        qrImg.style.display = 'block';
                    }
                    if (qrSpinner) qrSpinner.style.display = 'none';
                    if (pupUrlInput) pupUrlInput.value = data.upload_url;
                    if (pupOpenBtn) pupOpenBtn.href = data.upload_url;
                } else {
                    if (qrSpinner) qrSpinner.textContent = 'Failed to generate QR';
                }
            })
            .catch(err => {
                if (qrSpinner) qrSpinner.textContent = 'Error: ' + err.message;
            });
    }

    function closePhoneUploadModal() {
        if (!phoneModal) return;
        phoneModal.hidden = true;
        phoneModal.setAttribute('hidden', '');
        phoneModal.style.display = 'none';
    }

    phoneUploadBtn?.addEventListener('click', openPhoneUploadModal);

    phoneModal?.querySelectorAll('[data-phone-upload-close]').forEach(el => {
        el.addEventListener('click', closePhoneUploadModal);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && phoneModal && !phoneModal.hidden) {
            closePhoneUploadModal();
        }
    });

    pupCopyBtn?.addEventListener('click', () => {
        if (!pupUrlInput || !pupUrlInput.value) return;
        pupUrlInput.select();
        navigator.clipboard?.writeText(pupUrlInput.value);
        const orig = pupCopyBtn.textContent;
        pupCopyBtn.textContent = 'Copied!';
        setTimeout(() => { pupCopyBtn.textContent = orig; }, 1500);
    });
})();
