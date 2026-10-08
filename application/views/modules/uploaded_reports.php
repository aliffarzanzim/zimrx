<?php
declare(strict_types=1);

// Uploaded Reports module: patient document attachments list, local file picker, and QR code phone upload modal.
?>
<div id="uploaded-reports-wrapper" class="reports-wrapper reports-single-wrapper">
    <section class="reports-section reports-upload-section">
        <div class="reports-section-header">
            <div class="reports-section-title">
                <?= zrx_icon('upload', 14) ?>
                <span>Upload Reports & Documents</span>
            </div>
            <button type="button" class="btn-phone-upload" id="btn-phone-upload-reports" title="Scan QR Code to Upload Reports from Phone Camera">
                <?= zrx_icon('smartphone', 13) ?>
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
                <?= zrx_icon('upload', 14) ?>
                Upload Document (PDF/Image)
            </button>
        </div>
    </section>

    <template id="reports-upload-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-drag td-rep-del-borderless">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
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
                        <?= zrx_icon('smartphone', 18) ?>
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
                            <li><strong>Tip:</strong> Bookmark it on your phone - no need to scan for every patient</li>
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
