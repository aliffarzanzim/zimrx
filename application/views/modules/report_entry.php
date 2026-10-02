<?php
declare(strict_types=1);

// Manual Report Entry module: tabular lab/diagnostic test results entry with date, value, and unit.
?>
<div id="report-entry-wrapper" class="reports-wrapper reports-single-wrapper">
    <section class="reports-section reports-entry-section">
        <div class="reports-section-header">
            <div class="reports-section-title">
                <?= zrx_icon('file-text', 14) ?>
                <span>Report Entry (Manual)</span>
            </div>
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
                            <th class="text-center">#</th>
                            <th>
                                <div class="pc-header-flex">
                                    <span>Parameter Name</span>
                                </div>
                            </th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Result / Value</th>
                            <th class="text-center">Unit</th>
                            <th class="th-rep-last"></th>
                        </tr>
                    </thead>
                    <tbody id="reports-tbody">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                        <tr class="pc-row" draggable="true">
                            <td class="pc-action pc-del td-rep-del-borderless"><button type="button" title="Remove Row">X</button></td>
                            <td class="pc-row-no"><?= $i ?></td>
                            <td><input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid"></td>
                            <td>
                                <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                            </td>
                            <td><input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result"></td>
                            <td><input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit"></td>
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

            <div class="reports-table-footer">
                <button type="button" class="pc-add-row-btn reports-add-row-btn">Add More</button>
            </div>
        </div>
    </section>

    <template id="reports-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del td-rep-del-borderless"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td><input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid"></td>
            <td>
                <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
            </td>
            <td><input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result"></td>
            <td><input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit"></td>
            <td class="pc-action pc-drag td-rep-drag-borderless">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<script src="assets/js/modules/report_entry.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/report_entry.js') ?>"></script>
