<?php
declare(strict_types=1);

// Uploaded Reports module: patient document attachments list, local file picker, and QR code phone upload modal.
?>
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

        <div id="reports-upload-table-container" class="reports-panel reports-upload-table-container is-hidden">
            <div class="pc-table-container reports-table-container">
                <table class="pc-table reports-upload-table" id="reports-upload-table">
                    <colgroup>
                        <col class="col-up-del">
                        <col class="col-up-name">
                        <col class="col-up-type">
                        <col class="col-up-date">
                        <col class="col-up-file">
                        <col class="col-up-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="th-rep-first"></th>
                            <th class="text-center">#</th>
                            <th class="text-left">Report Name</th>
                            <th class="text-center">Date</th>
                            <th class="text-left">File Name</th>
                            <th class="th-rep-last">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reports-upload-tbody"></tbody>
                </table>
            </div>
        </div>

        <div class="reports-upload-area">
            <input type="file" id="report-file-input" class="is-hidden" accept="image/*,application/pdf">
            <button type="button" id="report-upload-btn" class="report-upload-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                Upload Document (PDF/Image)
            </button>
        </div>
    </section>

    <template id="reports-upload-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-drag td-rep-del-borderless">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                </button>
            </td>
            <td class="pc-row-no"></td>
            <td><input type="text" class="pc-input upload-name-input" autocomplete="off" placeholder="Report Name"></td>
            <td>
                <input type="text" class="pc-input custom-date-picker upload-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
            </td>
            <td class="td-upload-filename">
                <span class="upload-filename-display"></span>
                <input type="hidden" class="upload-file-path">
            </td>
            <td class="pc-action td-upload-actions">
                <div class="upload-actions-wrap">
                    <a href="#" target="_blank" class="upload-view-btn upload-view-btn-custom">View</a>
                    <button type="button" class="upload-del-btn upload-del-btn-custom">Del</button>
                </div>
            </td>
        </tr>
    </template>

    <!-- Phone Upload QR Code Modal -->
    <div class="phone-upload-modal" id="phone-upload-modal" hidden>
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
                    <div id="pup-patient-reg-wrap" class="is-hidden">
                        <span class="pup-label">Reg:</span>
                        <strong id="pup-patient-reg">--</strong>
                    </div>
                </div>

                <!-- QR Display -->
                <div class="phone-upload-qr-container">
                    <div class="phone-upload-qr-box" id="phone-upload-qr-box">
                        <div class="phone-upload-spinner" id="phone-upload-spinner">Generating QR Code...</div>
                        <img id="phone-upload-qr-img" src="" alt="QR Code for Mobile Upload" class="is-hidden">
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

<script src="assets/js/modules/uploaded_reports.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/uploaded_reports.js') ?>"></script>
