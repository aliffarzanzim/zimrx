<?php
declare(strict_types=1);

// Investigations (Ix) module: laboratory, pathology, and imaging diagnostic test orders.
?>
<div class="pc-wrapper" id="ix-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="ix-table">
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
                            <span>Investigations</span>

                            <!-- Template Search Bar -->
                            <div class="rx-search-box" style="flex: 0 0 200px; margin-left: auto;">
                                <?= zrx_icon('search', 14) ?>
                                <input type="text" placeholder="Template" autocomplete="off" style="height: 28px;">
                            </div>
                        </div>
                    </th>
                    <th style="width: 38px; text-align: center;"></th>
                </tr>
            </thead>
            <tbody id="ix-tbody">
                <?php for($i=1; $i<=5; $i++): ?>
                <tr class="pc-row" draggable="true">
                    <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="pc-row-no"><?= $i ?></td>
                    <td>
                        <textarea class="pc-input ix-input" autocomplete="off" rows="1"></textarea>
                    </td>
                    <td class="pc-action pc-drag">
                        <button type="button" class="pc-row-move-btn" title="Move Row">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="pc-footer">
        <button type="button" class="pc-add-row-btn ix-add-row-btn">Add More</button>
    </div>

    <!-- Template for new rows -->
    <template id="ix-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td>
                <textarea class="pc-input ix-input" autocomplete="off" rows="1"></textarea>
            </td>
            <td class="pc-action pc-drag">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<script src="assets/js/modules/ix.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/modules/ix.js') ?>"></script>
