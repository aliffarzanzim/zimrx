// Mobile document upload client for scanning reports directly into active patient visits.
window.ZimRxCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    (function () {
        const cardPatientName = document.getElementById('card-patient-name');
        const cardPatientReg = document.getElementById('card-patient-reg');
        const cardRegWrap = document.getElementById('card-reg-wrap');
        const cardPatientAge = document.getElementById('card-patient-age');
        const cardAgeWrap = document.getElementById('card-age-wrap');
        const cardPatientGender = document.getElementById('card-patient-gender');
        const cardGenderWrap = document.getElementById('card-gender-wrap');
        const cardPatientDate = document.getElementById('card-patient-date');
        const patientCard = document.getElementById('patient-card');

        const cameraInput = document.getElementById('input-camera');
        const galleryInput = document.getElementById('input-gallery');
        const btnTriggerCamera = document.getElementById('btn-trigger-camera');
        const btnTriggerGallery = document.getElementById('btn-trigger-gallery');
        const previewBox = document.getElementById('preview-box');
        const previewImage = document.getElementById('preview-image');
        const previewFileIcon = document.getElementById('preview-file-icon');
        const previewFilename = document.getElementById('preview-filename');
        const btnRetake = document.getElementById('btn-retake');
        const reportNameInput = document.getElementById('report-name');
        const reportDateInput = document.getElementById('report-date');
        const btnSubmit = document.getElementById('btn-submit-upload');
        const progressWrap = document.getElementById('progress-wrap');
        const progressFill = document.getElementById('progress-fill');
        const successBox = document.getElementById('success-box');
        const successText = document.getElementById('success-text');
        const btnUploadAnother = document.getElementById('btn-upload-another');
        const historySection = document.getElementById('history-section');
        const historyList = document.getElementById('history-list');
        const historyCount = document.getElementById('history-count');

        let selectedFile = null;
        let lastPatientName = '';
        let currentPatientId = 0;
        let currentVisitRecordId = 0;
        let currentActiveRevision = 1;

        // Poll active patient from doctor desktop
        async function fetchActivePatient() {
            try {
                const res = await fetch('api/mobile_sync.php?action=get_active_patient');
                const data = await res.json();
                if (data.ok && data.patient) {
                    const p = data.patient;
                    currentPatientId = Number(p.patient_id || 0);
                    currentVisitRecordId = Number(p.visit_record_id || 0);
                    currentActiveRevision = Number(p.active_revision || 1);
                    const pName = p.patient_name || 'Walk-in Patient';

                    // Highlight change if desktop switched to another patient
                    if (lastPatientName && lastPatientName !== pName && patientCard) {
                        patientCard.style.backgroundColor = '#fef08a';
                        setTimeout(() => { patientCard.style.backgroundColor = ''; }, 1200);
                    }
                    lastPatientName = pName;

                    if (cardPatientName) cardPatientName.textContent = pName;

                    if (cardPatientReg && cardRegWrap) {
                        cardPatientReg.textContent = p.patient_reg || '--';
                        cardRegWrap.style.display = p.patient_reg ? 'inline-block' : 'none';
                    }
                    if (cardPatientAge && cardAgeWrap) {
                        cardPatientAge.textContent = p.patient_age || '--';
                        cardAgeWrap.style.display = p.patient_age ? 'inline-block' : 'none';
                    }
                    if (cardPatientGender && cardGenderWrap) {
                        cardPatientGender.textContent = p.patient_gender || '--';
                        cardGenderWrap.style.display = p.patient_gender ? 'inline-block' : 'none';
                    }
                    if (cardPatientDate && p.patient_date) {
                        cardPatientDate.textContent = p.patient_date;
                        if (reportDateInput && !reportDateInput.value) {
                            reportDateInput.value = p.patient_date;
                        }
                    }
                }
            } catch (e) {
                // Background sync error non-blocking
            }
        }

        fetchActivePatient();
        setInterval(fetchActivePatient, 3000);

        // Capture triggers
        btnTriggerCamera?.addEventListener('click', () => cameraInput.click());
        btnTriggerGallery?.addEventListener('click', () => galleryInput.click());
        btnRetake?.addEventListener('click', () => {
            selectedFile = null;
            previewBox.style.display = 'none';
            previewImage.style.display = 'none';
            previewFileIcon.style.display = 'none';
            cameraInput.value = '';
            galleryInput.value = '';
        });

        // Quick preset chips
        document.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', () => {
                if (reportNameInput) {
                    reportNameInput.value = chip.dataset.val;
                    reportNameInput.focus();
                }
            });
        });

        function handleFileSelect(file) {
            if (!file) return;
            selectedFile = file;
            successBox.style.display = 'none';

            if (reportNameInput && !reportNameInput.value.trim()) {
                const baseName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                if (!baseName.toLowerCase().startsWith('image') && !baseName.toLowerCase().startsWith('img')) {
                    reportNameInput.value = baseName;
                }
            }

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                    previewFileIcon.style.display = 'none';
                    previewBox.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewFilename.textContent = file.name;
                previewImage.style.display = 'none';
                previewFileIcon.style.display = 'flex';
                previewBox.style.display = 'block';
            }
        }

        cameraInput?.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                handleFileSelect(e.target.files[0]);
            }
        });

        galleryInput?.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                handleFileSelect(e.target.files[0]);
            }
        });

        // Upload handler
        btnSubmit?.addEventListener('click', async () => {
            if (!selectedFile) {
                alert('Please take a photo or select a file to upload first.');
                return;
            }

            const name = reportNameInput?.value.trim() || 'Lab Report';
            const date = reportDateInput?.value.trim() || new Date().toLocaleDateString('en-GB');

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span>Uploading to desktop...</span>';
            progressWrap.style.display = 'block';
            progressFill.style.width = '25%';

            const formData = new FormData();
            formData.append('csrf_token', window.ZimRxCsrfToken || '');
            formData.append('file', selectedFile);
            formData.append('report_name', name);
            formData.append('report_date', date);
            formData.append('patient_id', currentPatientId);
            formData.append('visit_record_id', currentVisitRecordId);
            formData.append('active_revision', currentActiveRevision);

            try {
                progressFill.style.width = '65%';

                const resp = await fetch('api/mobile_sync.php?action=upload', {
                    method: 'POST',
                    body: formData
                });

                progressFill.style.width = '95%';
                const result = await resp.json();

                if (result.ok) {
                    progressFill.style.width = '100%';
                    setTimeout(() => {
                        progressWrap.style.display = 'none';
                        previewBox.style.display = 'none';
                        successBox.style.display = 'block';
                        if (successText) {
                            successText.textContent = `Attached "${name}" directly to ${lastPatientName || 'active patient'}'s desktop prescription.`;
                        }

                        if (historySection && historyList) {
                            historySection.style.display = 'block';
                            const item = document.createElement('div');
                            item.className = 'history-item';
                            item.innerHTML = `
                                <span class="history-name">${escapeHtml(name)}</span>
                                <span class="history-date">${escapeHtml(date)}</span>
                            `;
                            historyList.prepend(item);
                            if (historyCount) {
                                const cur = parseInt(historyCount.textContent || '0', 10);
                                historyCount.textContent = cur + 1;
                            }
                        }

                        selectedFile = null;
                        if (cameraInput) cameraInput.value = '';
                        if (galleryInput) galleryInput.value = '';
                        if (reportNameInput) reportNameInput.value = '';
                    }, 300);
                } else {
                    alert('Upload failed: ' + (result.error || 'Unknown server error'));
                    progressWrap.style.display = 'none';
                }
            } catch (err) {
                alert('Network error while uploading: ' + err.message);
                progressWrap.style.display = 'none';
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = `
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <span>Upload to Desktop Prescription</span>
                `;
            }
        });

        btnUploadAnother?.addEventListener('click', () => {
            successBox.style.display = 'none';
            cameraInput.click();
        });

        function escapeHtml(str) {
            return (str || '').replace(/[&<>"']/g, function (m) {
                switch (m) {
                    case '&': return '&amp;';
                    case '<': return '&lt;';
                    case '>': return '&gt;';
                    case '"': return '&quot;';
                    case "'": return '&#39;';
                }
            });
        }
    })();
