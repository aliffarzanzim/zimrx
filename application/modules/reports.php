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
                <table class="pc-table report-table reports-entry-table" id="reports-table" style="border-style: hidden; margin-bottom: 0;">
                    <colgroup>
                        <col style="width: 32px;">
                        <col style="width: 42px;">
                        <col style="width: 35%;">
                        <col style="width: 20%;">
                        <col style="width: 25%;">
                        <col style="width: 15%;">
                        <col style="width: 38px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="text-align: center; border-left: none;"></th>
                            <th style="text-align: center;">#</th>
                            <th>
                                <div class="pc-header-flex" style="width: 100%;">
                                    <span>Report Name</span>
                                </div>
                            </th>
                            <th style="text-align: center;">Date</th>
                            <th style="text-align: center;">Result / Value</th>
                            <th style="text-align: center;">Unit</th>
                            <th style="text-align: center; border-right: none;"></th>
                        </tr>
                    </thead>
                    <tbody id="reports-tbody">
                        <?php for($i=1; $i<=3; $i++): ?>
                        <tr class="pc-row" draggable="true">
                            <td class="pc-action pc-del" style="border-left: none;"><button type="button" title="Remove Row">X</button></td>
                            <td class="pc-row-no"><?= $i ?></td>
                            <td>
                                <input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid">
                            </td>
                            <td>
                                <div class="zimrx-date-field" style="height: 100%;">
                                    <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                                </div>
                            </td>
                            <td>
                                <input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result">
                            </td>
                            <td>
                                <input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit">
                            </td>
                            <td class="pc-action pc-drag" style="border-right: none;">
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

        <div id="reports-upload-table-container" class="reports-panel reports-upload-table-container" style="display: none;">
            <div class="pc-table-container reports-table-container">
                <table class="pc-table" id="reports-upload-table" style="border-style: hidden; margin-bottom: 0;">
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
                    <tbody id="reports-upload-tbody">
                        <!-- Uploaded rows go here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upload Button -->
        <div class="reports-upload-area">
            <input type="file" id="report-file-input" style="display: none;" accept="image/*,application/pdf">
            <button type="button" id="report-upload-btn" class="report-upload-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                Upload Document (PDF/Image)
            </button>
        </div>
    </section>

    <!-- Template for new manual report rows -->
    <template id="reports-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del" style="border-left: none;"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td>
                <input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid">
            </td>
            <td>
                <div class="zimrx-date-field" style="height: 100%;">
                    <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                </div>
            </td>
            <td>
                <input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result">
            </td>
            <td>
                <input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit">
            </td>
            <td class="pc-action pc-drag" style="border-right: none;">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>

    <!-- Template for uploaded files -->
    <template id="reports-upload-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-drag" style="border-left: none;">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
            <td class="pc-row-no"></td>
            <td>
                <input type="text" class="pc-input upload-name-input" autocomplete="off" placeholder="Report Name">
            </td>
            <td>
                <div class="zimrx-date-field" style="height: 100%;">
                    <input type="text" class="pc-input custom-date-picker upload-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                </div>
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
</div>

<script src="assets/js/layout/reports_module.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/layout/reports_module.js') ?>"></script>
