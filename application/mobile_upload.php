<?php
declare(strict_types=1);

// Mobile upload route: snaps photos or chooses PDFs from mobile devices and syncs directly into the desktop prescription.

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
    <meta name="csrf-token" content="<?= htmlspecialchars(zimrx_csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ZimRx - Mobile Document Upload</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/layout/global.css?v=<?= filemtime(__DIR__ . '/assets/css/layout/global.css') ?>">
    <link rel="stylesheet" href="assets/css/pages/mobile_upload.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/mobile_upload.css') ?>">
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

    <script src="assets/js/pages/mobile_upload.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/mobile_upload.js') ?>"></script>
</body>
</html>
