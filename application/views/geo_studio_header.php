<?php
declare(strict_types=1);
?>
<header class="geo-page-header">
    <div class="geo-page-header-inner">
        <div class="geo-page-heading">
            <h1 class="geo-page-title">Geo Studio</h1>
            <p class="geo-page-description">Manage geographic and custom regional address packs.</p>
        </div>
        <div class="geo-actions" id="geoActionsBar">
            <button type="button" class="geo-btn" id="btnHome" title="Return Home" <?= $geoStudioInitialPackId === '' || $geoStudioInitialPage === 'new' ? 'hidden' : '' ?>>
                <?= zrx_icon('globe', 13) ?>
                <span>Home</span>
            </button>

            <button type="button" class="geo-btn" id="btnImportJson" title="Import an address pack JSON file">
                <?= zrx_icon('upload', 13) ?>
                <span>Import JSON</span>
            </button>
            <input class="geo-header-import-input" id="geoStudioImportFile" type="file" accept=".json,application/json" aria-label="Choose an address pack JSON file">

            <button type="button" class="geo-btn" id="btnOpenManifest" title="Edit author, contributors, and license metadata" <?= $geoStudioInitialPackId === '' || $geoStudioInitialPage === 'new' ? 'hidden' : '' ?> disabled>
                <?= zrx_icon('edit', 13) ?>
                <span>Manifest</span>
            </button>
            <button type="button" class="geo-btn geo-btn-primary" id="btnSaveJson" title="Save JSON file and update timestamp (Ctrl+S)" <?= $geoStudioInitialPackId === '' || $geoStudioInitialPage === 'new' ? 'hidden' : '' ?> disabled>
                <?= zrx_icon('save', 13) ?>
                <span>Save JSON</span>
            </button>
        </div>
    </div>
</header>
