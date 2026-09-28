<div class="advice-wrapper" id="advice-wrapper">
    <div class="advice-left">
        <div class="advice-header-row">
            <span class="advice-title">&#x0989;&#x09AA;&#x09A6;&#x09C7;&#x09B6;&#x0983;</span>
            <div class="rx-search-box advice-template-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" class="advice-template-input" placeholder="Advice Template / Category" autocomplete="off">
            </div>
        </div>

        <div class="advice-list" id="advice-list">
            <?php for($i=1; $i<=5; $i++): ?>
            <div class="advice-row pc-row" draggable="true">
                <div class="adv-drag pc-drag">
                    <button type="button" class="pc-row-move-btn zrx-drag-handle" style="width:100%; height:100%; border:none; background:transparent; padding:0; display:flex; align-items:center; justify-content:center; cursor:grab;" title="Move Row">
                        <?= zrx_icon('move', 12) ?>
                    </button>
                </div>
                <div class="adv-del pc-del">
                    <button type="button" title="Remove Row">X</button>
                </div>
                <input type="text" class="adv-input" autocomplete="off">
            </div>
            <?php endfor; ?>
        </div>

        <div class="advice-footer">
            <button type="button" class="adv-add-row-btn">Add More</button>
        </div>
    </div>

    <div class="advice-right">
        <div class="adv-form-group">
            <label>&#x09AA;&#x09B0;&#x09AC;&#x09B0;&#x09CD;&#x09A4;&#x09C0; &#x09B8;&#x09BE;&#x0995;&#x09CD;&#x09B7;&#x09BE;&#x09CE;</label>
            <select id="advice-next-visit-select">
                <option>&#x09AA;&#x09CD;&#x09B0;&#x09DF;&#x09CB;&#x099C;&#x09A8; &#x09A8;&#x09C7;&#x0987;</option>
                <option>&#x09E7; &#x09A6;&#x09BF;&#x09A8; &#x09AA;&#x09B0;</option>
                <option>&#x09E9; &#x09A6;&#x09BF;&#x09A8; &#x09AA;&#x09B0;</option>
                <option>&#x09ED; &#x09A6;&#x09BF;&#x09A8; &#x09AA;&#x09B0;</option>
                <option>&#x09E7;&#x09EB; &#x09A6;&#x09BF;&#x09A8; &#x09AA;&#x09B0;</option>
                <option>&#x09E7; &#x09AE;&#x09BE;&#x09B8; &#x09AA;&#x09B0;</option>
            </select>
        </div>
        <div class="adv-form-group">
            <label>&#x09A4;&#x09BE;&#x09B0;&#x09BF;&#x0996;</label>
            <input type="text" id="advice-next-visit-date" class="custom-date-picker" placeholder="">
        </div>
    </div>

    <template id="advice-row-template">
        <div class="advice-row pc-row" draggable="true">
            <div class="adv-drag pc-drag">
                <button type="button" class="pc-row-move-btn zrx-drag-handle" style="width:100%; height:100%; border:none; background:transparent; padding:0; display:flex; align-items:center; justify-content:center; cursor:grab;" title="Move Row">
                    <?= zrx_icon('move', 12) ?>
                </button>
            </div>
            <div class="adv-del pc-del">
                <button type="button" title="Remove Row">X</button>
            </div>
            <input type="text" class="adv-input" autocomplete="off">
        </div>
    </template>
</div>

<script src="assets/js/modules/advice_module.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/modules/advice_module.js') ?>"></script>
