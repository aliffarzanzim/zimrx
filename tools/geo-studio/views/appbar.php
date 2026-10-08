<?php
declare(strict_types=1);
?>
<header class="geo-appbar">
    <div class="geo-brand" id="brandHomeLink" title="Return to Countries Directory">
        <?= zrx_icon('activity', 20) ?>
        <span>ZimRx Geo Studio</span>
    </div>

    <div class="geo-actions" id="geoActionsBar">
        <label for="countrySelect" class="geo-label-inline">Switch:</label>
        <select id="countrySelect" class="geo-select" title="Switch Country">
            <option value="">-</option>
        </select>

        <button type="button" class="geo-btn" id="btnEditorHome" title="Return to Countries Directory">
            <?= zrx_icon('globe', 13) ?>
            <span>Directory</span>
        </button>

        <button type="button" class="geo-btn" id="btnOpenImport" title="Import from GeoNames, Wikidata, or create blank region">
            <?= zrx_icon('plus', 13) ?>
            <span>New / Import</span>
        </button>
        <button type="button" class="geo-btn" id="btnOpenManifest" title="Edit author, contributors, and license metadata" disabled>
            <?= zrx_icon('edit', 13) ?>
            <span>Manifest</span>
        </button>
        <button type="button" class="geo-btn geo-btn-primary" id="btnSaveJson" title="Save JSON file and update timestamp (Ctrl+S)" disabled>
            <?= zrx_icon('save', 13) ?>
            <span>Save JSON</span>
        </button>
        <div class="geo-dropdown-wrap" id="compileDropdownWrap">
            <button type="button" class="geo-btn geo-btn-success" id="btnCompileToggle" title="Compile options" disabled>
                <span>Compile</span>
                <?= zrx_icon('chevron-down', 12) ?>
            </button>
            <div class="geo-dropdown-menu" id="compileMenu">
                <button type="button" class="geo-dropdown-item-btn" id="optCompileDb">
                    <span class="geo-menu-icon"><?= zrx_icon('zap', 14) ?></span>
                    <div>
                        <div class="geo-menu-title">Compile to SQLite (.db)</div>
                        <div class="geo-menu-desc">Write directly to zimrx_static.db</div>
                    </div>
                </button>
                <button type="button" class="geo-dropdown-item-btn" id="optGenerateSqlDump">
                    <span class="geo-menu-icon"><?= zrx_icon('file-text', 14) ?></span>
                    <div>
                        <div class="geo-menu-title">Compile to SQL Dump (.sql)</div>
                        <div class="geo-menu-desc">Save seed to systemdata/seeds/</div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</header>
