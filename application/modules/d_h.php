<div class="pc-wrapper" id="dh-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="dh-table">
            <colgroup>
                <col style="width: 32px;">
                <col style="width: 42px;">
                <col>
                <col style="width: 38px;">
            </colgroup>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;"></th>
                    <th style="width: 42px; text-align: center;">#</th>
                    <th>
                        <div class="pc-header-flex" style="width: 100%;">
                            <span>D/H (Drug History)</span>
                        </div>
                    </th>
                    <th style="width: 38px; text-align: center;"></th>
                </tr>
            </thead>
            <tbody id="dh-tbody">
                <?php for($i=1; $i<=5; $i++): ?>
                <tr class="pc-row" draggable="true">
                    <td class="pc-action pc-drag">
                        <button type="button" class="pc-row-move-btn" title="Move Row">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                        </button>
                    </td>
                    <td class="pc-row-no"><?= $i ?></td>
                    <td>
                        <textarea class="pc-input dh-input" autocomplete="off" rows="1"></textarea>
                    </td>
                    <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="pc-footer">
        <button type="button" class="pc-add-row-btn dh-add-row-btn">Add More</button>
    </div>

    <!-- Template for new rows -->
    <template id="dh-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-drag">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                </button>
            </td>
            <td class="pc-row-no"></td>
            <td>
                <textarea class="pc-input dh-input" autocomplete="off" rows="1"></textarea>
            </td>
            <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
        </tr>
    </template>
</div>

<script src="assets/js/layout/dh_module.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/layout/dh_module.js') ?>"></script>
