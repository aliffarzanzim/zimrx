<?php
$ot_predefined_particulars = [
    'Date',
    'Time',
    'Indication',
    'Name of the operation',
    'Procedure',
    'Pre Operative Dx',
    'Post Operative Finding',
    'Type of Anesthesia',
    'Name of the Surgeon',
    'Name of the Anesthesiologist',
    'Name of the assistant',
    'Hospital Stay Time',
    'Special Note'
];
?>

<div class="ot-wrapper">
    <!-- Main Header -->
    <div class="ot-header">
        <span class="ot-title">OT Note</span>
    </div>

    <div class="ot-print-options">
        <div class="ot-print-group" role="radiogroup" aria-label="OT note print status">
            <span class="ot-print-group-title">Print</span>
            <label><input type="radio" name="ot_print_status" value="print" checked> Print OT Note</label>
            <label><input type="radio" name="ot_print_status" value="no_print"> Do not print OT Note</label>
        </div>
        <div class="ot-print-group" role="radiogroup" aria-label="OT note print layout">
            <span class="ot-print-group-title">Layout</span>
            <label><input type="radio" name="ot_print_layout" value="sidebar" checked> Sidebar</label>
            <label><input type="radio" name="ot_print_layout" value="full_page"> Full Page</label>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="ot-tabs-container">
        <div class="ot-tab-list">
            <button type="button" class="ot-tab active" data-target="tab-ot-table">OT Notes</button>
            <button type="button" class="ot-tab" data-target="tab-ot-salient">Salient Feature</button>
            <button type="button" class="ot-tab" data-target="tab-ot-history">History</button>
            <button type="button" class="ot-tab" data-target="tab-ot-others">Others</button>
        </div>
    </div>

    <!-- Tabs Content -->
    <div class="ot-panes">
        
        <!-- 1. OT Notes Table Tab -->
        <div class="ot-pane active" id="tab-ot-table">
            <div class="ot-table-container">
                <table class="ot-table" id="ot-table">
                    <colgroup>
                        <col style="width: 32px;">
                        <col style="width: 35%;">
                        <col>
                        <col style="width: 38px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="text-align: center;"></th>
                            <th>Particulars</th>
                            <th>Value</th>
                            <th style="text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody id="ot-tbody">
                        <?php foreach ($ot_predefined_particulars as $particular): ?>
                        <tr class="ot-row pc-row" draggable="true">
                            <td class="ot-action ot-del pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                            <td><input type="text" class="ot-input" value="<?= htmlspecialchars($particular) ?>" readonly></td>
                            <td><textarea class="ot-input ot-value-input" autocomplete="off" rows="1"></textarea></td>
                            <td class="ot-action ot-drag pc-action pc-drag">
                                <button type="button" class="ot-row-move-btn pc-row-move-btn zrx-drag-handle" title="Move Row">
                                    <?= zrx_icon('move', 14) ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Empty rows at the bottom -->
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <tr class="ot-row pc-row" draggable="true">
                            <td class="ot-action ot-del pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                            <td><input type="text" class="ot-input" placeholder="Custom Particular..." autocomplete="off"></td>
                            <td><textarea class="ot-input ot-value-input" autocomplete="off" rows="1"></textarea></td>
                            <td class="ot-action ot-drag pc-action pc-drag">
                                <button type="button" class="ot-row-move-btn pc-row-move-btn zrx-drag-handle" title="Move Row">
                                    <?= zrx_icon('move', 14) ?>
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="ot-footer">
                <button type="button" class="ot-add-row-btn">Add More</button>
            </div>
        </div>

        <!-- 2. Salient Feature Tab -->
        <div class="ot-pane ot-nicedit-container" id="tab-ot-salient">
            <textarea id="ot-salient-editor" style="width: 100%;"></textarea>
        </div>

        <!-- 3. History Tab -->
        <div class="ot-pane ot-nicedit-container" id="tab-ot-history">
            <textarea id="ot-history-editor" style="width: 100%;"></textarea>
        </div>

        <!-- 4. Others Tab -->
        <div class="ot-pane ot-nicedit-container" id="tab-ot-others">
            <textarea id="ot-others-editor" style="width: 100%;"></textarea>
        </div>

    </div>

    <!-- Template for new table rows -->
    <template id="ot-row-template">
        <tr class="ot-row pc-row" draggable="true">
            <td class="ot-action ot-del pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
            <td><input type="text" class="ot-input" placeholder="Custom Particular..." autocomplete="off"></td>
            <td><textarea class="ot-input ot-value-input" autocomplete="off" rows="1"></textarea></td>
            <td class="ot-action ot-drag pc-action pc-drag">
                <button type="button" class="ot-row-move-btn pc-row-move-btn zrx-drag-handle" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<!-- Load NicEditor -->
<script src="vendor/nicedit/nicEdit-latest.js?v=<?= filemtime(__DIR__ . '/../vendor/nicedit/nicEdit-latest.js') ?>"></script>
<script src="vendor/nicedit/nicEdit-zimrx-custom.js?v=<?= filemtime(__DIR__ . '/../vendor/nicedit/nicEdit-zimrx-custom.js') ?>"></script>

<script src="assets/js/modules/ot_note.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/modules/ot_note.js') ?>"></script>
