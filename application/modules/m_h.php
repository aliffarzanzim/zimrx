<div class="oh-wrapper js-mh-module">
    <div class="oh-table-container">
        <table class="oh-table" id="mh-table">
            <colgroup>
                <col style="width: 32px;">
                <col style="width: 42px;">
                <col style="width: 34%;">
                <col>
                <col style="width: 38px;">
            </colgroup>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;"></th>
                    <th style="width: 42px; text-align: center;">#</th>
                    <th colspan="2">
                        <div class="oh-header-flex">
                            <span>M/H</span>
                        </div>
                    </th>
                    <th style="width: 38px; text-align: center;"></th>
                </tr>
            </thead>
            <tbody>
                <tr class="oh-row" data-row-kind="age_of_menarche" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">1</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Age of Menarche" readonly></td>
                    <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input" data-mh-keypad="1" autocomplete="off"></td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <tr class="oh-row" data-row-kind="mp" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">2</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="MP" readonly></td>
                    <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input" data-mh-keypad="1" autocomplete="off"></td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <tr class="oh-row" data-row-kind="mc" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">3</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="MC" readonly></td>
                    <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input" data-mh-keypad="1" autocomplete="off"></td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <tr class="oh-row" data-row-kind="lmp" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">4</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="LMP" readonly></td>
                    <td class="oh-value-cell">
                        <div class="zimrx-date-field">
                            <input type="text" class="oh-input oh-value-input mh-lmp-input custom-date-picker" autocomplete="off" inputmode="numeric" placeholder="dd/mm/yy">
                            <button type="button" class="zimrx-date-trigger" title="Open Calendar" aria-label="Open Calendar">
                                <?= zrx_icon('calendar', 14) ?>
                            </button>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <tr class="oh-row" data-row-kind="edd" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">5</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="EDD" readonly></td>
                    <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input mh-edd-input" autocomplete="off" readonly></td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="oh-footer">
        <button type="button" class="mh-add-row-btn">Add More</button>
    </div>

    <template id="mh-row-template">
        <tr class="oh-row" draggable="true">
            <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
            <td class="oh-row-no"></td>
            <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" autocomplete="off"></td>
            <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input" autocomplete="off"></td>
            <td class="oh-action oh-drag">
                <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>
