<?php
declare(strict_types=1);

// Combined Reports module: manual diagnostic results entry table and document file attachments.
?>
<div id="reports-wrapper" class="reports-wrapper">

    <!-- Top Section: Manual Report Entry -->
    <section class="reports-section reports-entry-section">
        <div class="reports-section-header">
            <div class="reports-section-title">
                <?= zrx_icon('file-text', 14) ?>
                <span>Report Entry</span>
            </div>
            <span class="reports-section-badge">Manual</span>
        </div>

        <div class="reports-panel">
            <div class="pc-table-container reports-table-container">
                <table class="pc-table report-table reports-entry-table" id="reports-table">
                    <colgroup>
                        <col class="col-rep-del">
                        <col class="col-rep-name">
                        <col class="col-rep-test">
                        <col class="col-rep-val">
                        <col class="col-rep-ref">
                        <col class="col-rep-date">
                        <col class="col-rep-drag">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="th-rep-first"></th>
                            <th class="zrx-ta-c">#</th>
                            <th>
                                <div class="pc-header-flex" class="zrx-w100">
                                    <span>Report Name</span>
                                </div>
                            </th>
                            <th class="zrx-ta-c">Date</th>
                            <th class="zrx-ta-c">Result / Value</th>
                            <th class="zrx-ta-c">Unit</th>
                            <th class="th-rep-last"></th>
                        </tr>
                    </thead>
                    <tbody id="reports-tbody">
                        <?php for($i=1; $i<=3; $i++): ?>
                        <tr class="pc-row" draggable="true">
                            <td class="pc-action pc-del td-rep-del-borderless"><button type="button" title="Remove Row">X</button></td>
                            <td class="pc-row-no"><?= $i ?></td>
                            <td>
                                <input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid">
                            </td>
                            <td>
                                <div class="zimrx-date-field" class="zrx-h100">
                                    <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                                </div>
                            </td>
                            <td>
                                <input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result">
                            </td>
                            <td>
                                <input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit">
                            </td>
                            <td class="pc-action pc-drag td-rep-drag-borderless">
                                <button type="button" class="pc-row-move-btn" title="Move Row">
                                    <?= zrx_icon('move', 14) ?>
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add More Button inside the white card -->
            <div class="reports-table-footer">
                <button type="button" class="pc-add-row-btn reports-add-row-btn">Add More</button>
            </div>
        </div>
    </section>

    <!-- Bottom Section: Uploads -->
    <section class="reports-section reports-upload-section">
        <div class="reports-section-header">
            <div class="reports-section-title">
                <?= zrx_icon('file', 14) ?>
                <span>Upload Reports & Documents</span>
            </div>
            <span class="reports-section-badge">Files</span>
        </div>

        <div id="reports-upload-table-container" class="reports-panel reports-upload-table-container" class="zrx-dn">
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
                            <th class="zrx-ta-c">#</th>
                            <th class="zrx-ta-l">Report Name</th>
                            <th class="zrx-ta-c">Date</th>
                            <th class="zrx-ta-l">File Name</th>
                            <th class="th-rep-last">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reports-upload-tbody">
                        <!-- Uploaded rows go here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upload Button -->
        <div class="reports-upload-area">
            <input type="file" id="report-file-input" class="zrx-dn" accept="image/*,application/pdf">
            <button type="button" id="report-upload-btn" class="report-upload-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                Upload Document (PDF/Image)
            </button>
        </div>
    </section>

    <!-- Template for new manual report rows -->
    <template id="reports-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del td-rep-del-borderless"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td>
                <input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid">
            </td>
            <td>
                <div class="zimrx-date-field" class="zrx-h100">
                    <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                </div>
            </td>
            <td>
                <input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result">
            </td>
            <td>
                <input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit">
            </td>
            <td class="pc-action pc-drag td-rep-drag-borderless">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>

    <!-- Template for uploaded files -->
    <template id="reports-upload-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-drag td-rep-del-borderless">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
            <td class="pc-row-no"></td>
            <td>
                <input type="text" class="pc-input upload-name-input" autocomplete="off" placeholder="Report Name">
            </td>
            <td>
                <div class="zimrx-date-field" class="zrx-h100">
                    <input type="text" class="pc-input custom-date-picker upload-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                </div>
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
</div>

<script src="assets/js/modules/reports.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/reports.js') ?>"></script>
