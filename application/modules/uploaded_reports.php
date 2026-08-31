<div id="uploaded-reports-wrapper" class="reports-wrapper reports-single-wrapper">
    <section class="reports-section reports-upload-section">
        <div class="reports-section-header">
            <div class="reports-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <path d="M17 8 12 3 7 8"></path>
                    <path d="M12 3v12"></path>
                </svg>
                <span>Upload Reports & Documents</span>
            </div>
            <button type="button" class="btn-phone-upload" id="btn-phone-upload-reports" title="Scan QR Code to Upload Reports from Phone Camera">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                    <line x1="12" y1="18" x2="12.01" y2="18"></line>
                </svg>
                <span>Upload from Phone</span>
            </button>
        </div>

        <div id="reports-upload-table-container" class="reports-panel reports-upload-table-container" style="display: none;">
            <div class="pc-table-container reports-table-container">
                <table class="pc-table reports-upload-table" id="reports-upload-table" style="border-style: hidden; margin-bottom: 0;">
                    <colgroup>
                        <col style="width: 32px;">
                        <col style="width: 42px;">
                        <col style="width: 32%;">
                        <col style="width: 18%;">
                        <col style="width: 25%;">
                        <col style="width: 110px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="text-align: center; border-left: none;"></th>
                            <th style="text-align: center;">#</th>
                            <th style="text-align: left;">Report Name</th>
                            <th style="text-align: center;">Date</th>
                            <th style="text-align: left;">File Name</th>
                            <th style="text-align: center; border-right: none;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reports-upload-tbody"></tbody>
                </table>
            </div>
        </div>

        <div class="reports-upload-area">
            <input type="file" id="report-file-input" style="display: none;" accept="image/*,application/pdf">
            <button type="button" id="report-upload-btn" class="report-upload-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                Upload Document (PDF/Image)
            </button>
        </div>
    </section>

    <template id="reports-upload-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-drag" style="border-left: none;">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                </button>
            </td>
            <td class="pc-row-no"></td>
            <td><input type="text" class="pc-input upload-name-input" autocomplete="off" placeholder="Report Name"></td>
            <td>
                <input type="text" class="pc-input custom-date-picker upload-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
            </td>
            <td style="vertical-align: middle; padding: 0 10px;">
                <span class="upload-filename-display" style="font-size: 0.85rem; color: #475569; word-break: break-all; font-weight: 500;"></span>
                <input type="hidden" class="upload-file-path">
            </td>
            <td class="pc-action" style="vertical-align: middle; border-right: none;">
                <div style="display: flex; gap: 6px; justify-content: center; align-items: center; height: 100%;">
                    <a href="#" target="_blank" class="upload-view-btn" style="padding: 3px 8px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-decoration: none;">View</a>
                    <button type="button" class="upload-del-btn" style="padding: 3px 8px; background: #ffffff; color: #b91c1c; border: 1px solid #94a3b8; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 1px rgba(0,0,0,0.05);">Del</button>
                </div>
            </td>
        </tr>
    </template>

    <!-- Phone Upload QR Code Modal -->
    <div class="phone-upload-modal" id="phone-upload-modal" hidden style="display: none;">
        <div class="phone-upload-backdrop" data-phone-upload-close></div>
        <div class="phone-upload-panel" role="dialog" aria-modal="true" aria-labelledby="phone-upload-title">
            <div class="phone-upload-header">
                <div class="phone-upload-title-wrap">
                    <div class="phone-upload-icon-box">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                            <line x1="12" y1="18" x2="12.01" y2="18"></line>
                        </svg>
                    </div>
                    <div>
                        <h3 id="phone-upload-title">Upload Reports from Phone</h3>
                        <p>Scan once or bookmark. Phone automatically syncs with your active desktop patient.</p>
                    </div>
                </div>
                <button type="button" class="phone-upload-close" data-phone-upload-close aria-label="Close Modal">&times;</button>
            </div>

            <div class="phone-upload-body">
                <!-- Active Patient Banner -->
                <div class="phone-upload-patient-banner">
                    <div>
                        <span class="pup-label">Active Patient:</span>
                        <strong id="pup-patient-name">Walk-in Patient</strong>
                    </div>
                    <div id="pup-patient-reg-wrap" style="display: none;">
                        <span class="pup-label">Reg:</span>
                        <strong id="pup-patient-reg">--</strong>
                    </div>
                </div>

                <!-- QR Display -->
                <div class="phone-upload-qr-container">
                    <div class="phone-upload-qr-box" id="phone-upload-qr-box">
                        <div class="phone-upload-spinner" id="phone-upload-spinner">Generating QR Code...</div>
                        <img id="phone-upload-qr-img" src="" alt="QR Code for Mobile Upload" style="display: none;">
                    </div>
                    <div class="phone-upload-instructions">
                        <ol>
                            <li>Scan this QR code with your phone camera</li>
                            <li>Log in once with your doctor credentials</li>
                            <li><strong>Tip:</strong> Bookmark it on your phone — no need to scan for every patient</li>
                            <li>Photos uploaded on phone attach instantly to whichever patient is open on this computer</li>
                        </ol>
                    </div>
                </div>

                <!-- Direct Link & Copy -->
                <div class="phone-upload-link-row">
                    <input type="text" id="phone-upload-url-input" readonly placeholder="Upload URL">
                    <button type="button" id="phone-upload-copy-btn">Copy Link</button>
                    <a href="#" target="_blank" id="phone-upload-open-btn">Open Page</a>
                </div>

                <!-- Live Status Indicator -->
                <div class="phone-upload-status-bar">
                    <span class="pup-pulse-dot"></span>
                    <span id="pup-status-text">Connected & listening for phone uploads in real-time...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
        if (cleanPath.startsWith('userdata/uploads/')) {
            cleanPath = cleanPath.replace('userdata/uploads/', 'uploads/');
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

    // Standard Desktop Upload
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

    /* ==========================================================
       Live Patient Synchronization (Desktop -> Mobile)
    ========================================================== */
    function syncActivePatientToMobile() {
        const pName = document.getElementById('patient-name')?.value.trim() || 'Walk-in Patient';
        const pReg = document.getElementById('patient-reg-no')?.value.trim() || '';
        const pAge = document.getElementById('patient-age')?.value.trim() || '';
        const pGender = document.getElementById('patient-gender')?.value.trim() || '';
        const pDate = document.getElementById('patient-date')?.value.trim() || '';

        // Update modal banner if visible
        if (pupPatientName) pupPatientName.textContent = pName;
        if (pupPatientReg && pupPatientRegWrap) {
            pupPatientReg.textContent = pReg || '--';
            pupPatientRegWrap.style.display = pReg ? 'inline-block' : 'none';
        }

        const params = new URLSearchParams({
            action: 'update_active_patient',
            patient_name: pName,
            patient_reg: pReg,
            patient_age: pAge,
            patient_gender: pGender,
            patient_date: pDate
        });

        fetch('api/mobile_sync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        }).catch(() => {});
    }

    // Publish active patient on load and on any change
    syncActivePatientToMobile();
    ['patient-name', 'patient-reg-no', 'patient-age', 'patient-gender', 'patient-date'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', syncActivePatientToMobile);
            el.addEventListener('change', syncActivePatientToMobile);
        }
    });
    setInterval(syncActivePatientToMobile, 8000);

    /* ==========================================================
       Live Background Upload Poller (Mobile -> Desktop Table)
    ========================================================== */
    setInterval(async () => {
        try {
            const res = await fetch('api/mobile_sync.php?action=check_uploads');
            const data = await res.json();
            if (data.ok && Array.isArray(data.uploads) && data.uploads.length > 0) {
                data.uploads.forEach(u => {
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

    /* ==========================================================
       Phone Upload QR Modal Controller
    ========================================================== */
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
</script>
