<?php
declare(strict_types=1);
?>
<header class="geo-appbar">
    <div class="geo-brand" id="brandHomeLink" title="Return to Languages Directory">
        <?= zrx_icon('activity', 20) ?>
        <span>ZimRx Locale Studio</span>
    </div>

    <div class="locale-appbar-controls" id="appbarControls">
        <!-- Target Language Switcher -->
        <div class="locale-switcher-wrap" id="targetLangWrap" style="display:none;">
            <label for="targetLangSelect" class="locale-label-inline">Target:</label>
            <select id="targetLangSelect" class="locale-select" title="Target Language to Edit">
                <option value="">-</option>
            </select>
        </div>

        <!-- Reference Language Switcher -->
        <div class="locale-switcher-wrap" id="refLangWrap" style="display:none;">
            <label for="refLangSelect" class="locale-label-inline">Ref:</label>
            <select id="refLangSelect" class="locale-select" title="Reference Language for Comparison">
                <option value="en">English (en)</option>
            </select>
        </div>

        <!-- Hub Home Button -->
        <button type="button" class="geo-btn" id="btnViewHub" title="Return to Languages Directory">
            <?= zrx_icon('globe', 13) ?>
            <span>Directory</span>
        </button>

        <!-- New Language Pack Button -->
        <button type="button" class="geo-btn" id="btnOpenNewLangModal" title="Create a new language pack">
            <?= zrx_icon('plus', 13) ?>
            <span>New Language</span>
        </button>

        <!-- Dynamic Add Catalog Button -->
        <button type="button" class="geo-btn" id="btnOpenNewCatalogModal" title="Create a new JSON catalog file across all locales">
            <?= zrx_icon('file-text', 13) ?>
            <span>Add Catalog</span>
        </button>

        <!-- Manifest Editor Button -->
        <button type="button" class="geo-btn" id="btnOpenManifestModal" title="Edit language metadata, author, and contributors" disabled>
            <?= zrx_icon('edit', 13) ?>
            <span>Manifest</span>
        </button>

        <!-- Validate All Button -->
        <button type="button" class="geo-btn" id="btnOpenValidateModal" title="Validate JSON syntax and missing keys across all locales">
            <?= zrx_icon('shield', 13) ?>
            <span>Validate</span>
        </button>

        <!-- Save JSON Button -->
        <button type="button" class="geo-btn geo-btn-primary" id="btnSaveCatalog" title="Save JSON file to disk (Ctrl+S)" disabled>
            <?= zrx_icon('save', 13) ?>
            <span>Save JSON</span>
        </button>
    </div>
</header>
