<?php
/**
 * ZimRx Mobile Upload Page
 * Directly adopts the doctor's active patient from the desktop in real time.
 * No per-patient tokens required.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();

$doctorId = current_user_doctor_id();
$doctorName = current_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ZimRx - Mobile Document Upload</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #eff6ff;
            --success: #16a34a;
            --success-light: #f0fdf4;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --card-bg: #ffffff;
            --bg-page: #f8fafc;
            --radius: 14px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-page);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.45;
        }

        .mobile-nav {
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .mobile-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--primary);
            text-decoration: none;
        }

        .mobile-logo svg {
            width: 22px;
            height: 22px;
        }

        .doctor-badge {
            font-size: 0.76rem;
            font-weight: 600;
            color: #334155;
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .doctor-badge .status-dot {
            width: 7px;
            height: 7px;
            background: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            animation: pulseSync 2s infinite;
        }

        @keyframes pulseSync {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        .mobile-container {
            flex: 1;
            padding: 1rem;
            max-width: 540px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .m-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 1.1rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
            transition: border-color 0.2s, background-color 0.2s;
        }

        /* Patient Header Card */
        .patient-card {
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
            border-color: #bfdbfe;
            position: relative;
            overflow: hidden;
        }

        .patient-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .patient-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .patient-tag {
            font-size: 0.70rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--primary);
            letter-spacing: 0.05em;
        }

        .live-sync-badge {
            font-size: 0.68rem;
            font-weight: 600;
            color: #166534;
            background: #dcfce7;
            padding: 2px 7px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .patient-name {
            font-size: 1.28rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
            margin-bottom: 6px;
        }

        .patient-meta-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            font-size: 0.82rem;
            color: #475569;
        }

        .patient-meta-item strong {
            color: #0f172a;
            font-weight: 600;
        }

        /* Choice Buttons */
        .choice-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .btn-choice {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 1.15rem 0.5rem;
            border-radius: 12px;
            border: 2px dashed #cbd5e1;
            background: #f8fafc;
            color: #334155;
            font-weight: 600;
            font-size: 0.86rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-choice:active {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary-dark);
            transform: scale(0.98);
        }

        .btn-choice svg {
            width: 28px;
            height: 28px;
            color: var(--primary);
        }

        /* Preview Box */
        .preview-box {
            display: none;
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: #0f172a;
            margin-bottom: 1rem;
            text-align: center;
        }

        .preview-box img {
            max-width: 100%;
            max-height: 240px;
            display: block;
            margin: 0 auto;
            object-fit: contain;
        }

        .preview-file-icon {
            padding: 2rem 1rem;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .preview-file-icon svg {
            width: 44px;
            height: 44px;
            color: #60a5fa;
        }

        .btn-retake {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(15, 23, 42, 0.75);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            backdrop-filter: blur(4px);
        }

        /* Inputs */
        .form-group {
            margin-bottom: 0.9rem;
        }

        .form-group label {
            display: block;
            font-size: 0.76rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .m-input {
            width: 100%;
            height: 44px;
            padding: 0 0.85rem;
            border-radius: 9px;
            border: 1px solid #cbd5e1;
            font-size: 0.92rem;
            color: #0f172a;
            background: #ffffff;
            outline: none;
            transition: all 0.15s ease;
        }

        .m-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .chip-scroll {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .chip {
            font-size: 0.74rem;
            font-weight: 500;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 9px;
            cursor: pointer;
            user-select: none;
            transition: all 0.12s;
        }

        .chip:active {
            background: #dbeafe;
            color: #1d4ed8;
            border-color: #93c5fd;
        }

        .btn-submit {
            width: 100%;
            height: 48px;
            border-radius: 10px;
            border: none;
            background: var(--primary);
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.28);
            transition: all 0.15s ease;
            margin-top: 0.5rem;
        }

        .btn-submit:active {
            background: var(--primary-dark);
            transform: scale(0.99);
        }

        .btn-submit:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }

        .progress-bar-wrap {
            display: none;
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 0.75rem;
        }

        .progress-bar-fill {
            width: 0%;
            height: 100%;
            background: var(--primary);
            transition: width 0.2s ease;
        }

        .success-box {
            display: none;
            background: var(--success-light);
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 1.25rem 1rem;
            text-align: center;
            margin-top: 1rem;
        }

        .success-icon {
            width: 42px;
            height: 42px;
            background: #22c55e;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.65rem;
        }

        .success-box h3 {
            font-size: 1.05rem;
            color: #14532d;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .success-box p {
            font-size: 0.82rem;
            color: #166534;
            margin-bottom: 0.85rem;
        }

        .btn-another {
            background: #ffffff;
            border: 1px solid #86efac;
            color: #166534;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            cursor: pointer;
        }

        .history-section {
            margin-top: 0.5rem;
        }

        .history-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.5rem;
        }

        .history-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.65rem 0.85rem;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.84rem;
            margin-bottom: 6px;
        }

        .history-name {
            font-weight: 600;
            color: #0f172a;
        }

        .history-date {
            font-size: 0.75rem;
            color: #64748b;
        }
    </style>
</head>
<body>

    <header class="mobile-nav">
        <a href="#" class="mobile-logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
            </svg>
            <span>ZimRx</span>
        </a>
        <div class="doctor-badge">
            <span class="status-dot"></span>
            <span>Dr. <?= htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="mobile-container">
        <!-- Patient Particulars Card (Auto-adopted from Desktop) -->
        <div class="m-card patient-card" id="patient-card">
            <div class="patient-header-top">
                <span class="patient-tag">Active Desktop Patient</span>
                <span class="live-sync-badge">Live Synced</span>
            </div>
            <div class="patient-name" id="card-patient-name">Loading patient from desktop...</div>
            <div class="patient-meta-grid">
                <div class="patient-meta-item" id="card-reg-wrap" style="display: none;">Reg: <strong id="card-patient-reg">--</strong></div>
                <div class="patient-meta-item" id="card-age-wrap" style="display: none;">Age: <strong id="card-patient-age">--</strong></div>
                <div class="patient-meta-item" id="card-gender-wrap" style="display: none;">Gender: <strong id="card-patient-gender">--</strong></div>
                <div class="patient-meta-item">Date: <strong id="card-patient-date"><?= date('d/m/Y') ?></strong></div>
            </div>
        </div>

        <!-- Upload Form Card -->
        <div class="m-card" id="upload-card">
            <input type="file" id="input-camera" accept="image/*" capture="environment" style="display: none;">
            <input type="file" id="input-gallery" accept="image/*,application/pdf" style="display: none;">

            <div class="choice-row" id="choice-buttons">
                <button type="button" class="btn-choice" id="btn-trigger-camera">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>Camera Snap</span>
                </button>
                <button type="button" class="btn-choice" id="btn-trigger-gallery">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span>Choose File / PDF</span>
                </button>
            </div>

            <div class="preview-box" id="preview-box">
                <img id="preview-image" src="" alt="Report Preview" style="display: none;">
                <div class="preview-file-icon" id="preview-file-icon" style="display: none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    <span id="preview-filename">document.pdf</span>
                </div>
                <button type="button" class="btn-retake" id="btn-retake">Change</button>
            </div>

            <div class="form-group">
                <label for="report-name">Report / Document Name</label>
                <input type="text" id="report-name" class="m-input" placeholder="e.g. Complete Blood Count (CBC)" autocomplete="off">
                <div class="chip-scroll">
                    <span class="chip" data-val="CBC">CBC</span>
                    <span class="chip" data-val="Chest X-Ray">Chest X-Ray</span>
                    <span class="chip" data-val="ECG">ECG</span>
                    <span class="chip" data-val="USG Whole Abdomen">USG</span>
                    <span class="chip" data-val="Urine R/E">Urine R/E</span>
                    <span class="chip" data-val="RBS">RBS</span>
                    <span class="chip" data-val="S. Creatinine">Creatinine</span>
                    <span class="chip" data-val="Lipid Profile">Lipid</span>
                    <span class="chip" data-val="CT Scan">CT Scan</span>
                    <span class="chip" data-val="MRI">MRI</span>
                    <span class="chip" data-val="Previous Prescription">Old Rx</span>
                    <span class="chip" data-val="Discharge Letter">Discharge</span>
                </div>
            </div>

            <div class="form-group">
                <label for="report-date">Report Date</label>
                <input type="text" id="report-date" class="m-input" value="<?= date('d/m/Y') ?>" placeholder="DD/MM/YYYY">
            </div>

            <button type="button" id="btn-submit-upload" class="btn-submit">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <span>Upload to Desktop Prescription</span>
            </button>

            <div class="progress-bar-wrap" id="progress-wrap">
                <div class="progress-bar-fill" id="progress-fill"></div>
            </div>

            <div class="success-box" id="success-box">
                <div class="success-icon">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <h3>Uploaded Successfully!</h3>
                <p id="success-text">Document attached directly to your desktop prescription table.</p>
                <button type="button" class="btn-another" id="btn-upload-another">📸 Upload Another Document</button>
            </div>
        </div>

        <div class="history-section" id="history-section" style="display: none;">
            <div class="history-title">Uploaded in this session (<span id="history-count">0</span>)</div>
            <div id="history-list"></div>
        </div>
    </div>

    <script>
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

        // Live Poll Active Patient from Doctor's Desktop
        async function fetchActivePatient() {
            try {
                const res = await fetch('api/mobile_sync.php?action=get_active_patient');
                const data = await res.json();
                if (data.ok && data.patient) {
                    const p = data.patient;
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

        // Quick Preset Chips
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

        // Upload Action
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
            formData.append('file', selectedFile);
            formData.append('report_name', name);
            formData.append('report_date', date);

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
    </script>
</body>
</html>
