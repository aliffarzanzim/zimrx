<?php declare(strict_types=1); ?>
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
                                    <span>Parameter Name</span>
                                </div>
                            </th>
                            <th style="text-align: center;">Date</th>
                            <th style="text-align: center;">Result / Value</th>
                            <th style="text-align: center;">Unit</th>
                            <th style="text-align: center; border-right: none;"></th>
                        </tr>
                    </thead>
                    <tbody id="reports-tbody">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                        <tr class="pc-row" draggable="true">
                            <td class="pc-action pc-del" style="border-left: none;"><button type="button" title="Remove Row">X</button></td>
                            <td class="pc-row-no"><?= $i ?></td>
                            <td><input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid"></td>
                            <td>
                                <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
                            </td>
                            <td><input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result"></td>
                            <td><input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit"></td>
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

            <div class="reports-table-footer">
                <button type="button" class="pc-add-row-btn reports-add-row-btn">Add More</button>
            </div>
        </div>
    </section>

    <template id="reports-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del" style="border-left: none;"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td><input type="text" class="pc-input rep-name-input" autocomplete="off" placeholder="e.g. Hb, Cr, ALT, Lipid"></td>
            <td>
                <input type="text" class="pc-input custom-date-picker rep-date-input" autocomplete="off" placeholder="DD/MM/YYYY">
            </td>
            <td><input type="text" class="pc-input rep-result-input" autocomplete="off" placeholder="Result"></td>
            <td><input type="text" class="pc-input rep-unit-input" autocomplete="off" placeholder="Unit"></td>
            <td class="pc-action pc-drag" style="border-right: none;">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<script src="assets/js/modules/report_entry_module.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/modules/report_entry_module.js') ?>"></script>
