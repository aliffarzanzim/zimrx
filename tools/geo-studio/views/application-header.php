<?php
declare(strict_types=1);
?>
<header class="geo-appbar">
    <div class="geo-brand" id="brandHomeLink" role="button" tabindex="0" title="Return Home">
        <?= \GeoStudio\IconRegistry::svg('activity', 20) ?>
        <span>ZimRx Geo Studio</span>
    </div>

    <div class="geo-actions" id="geoActionsBar">
        <button type="button" class="geo-btn" id="btnHome" title="Return Home">
            <?= \GeoStudio\IconRegistry::svg('globe', 13) ?>
            <span>Home</span>
        </button>

        <button type="button" class="geo-btn" id="btnOpenManifest" title="Edit author, contributors, and license metadata" disabled>
            <?= \GeoStudio\IconRegistry::svg('edit', 13) ?>
            <span>Manifest</span>
        </button>
        <button type="button" class="geo-btn geo-btn-primary" id="btnSaveJson" title="Save JSON file and update timestamp (Ctrl+S)" disabled>
            <?= \GeoStudio\IconRegistry::svg('save', 13) ?>
            <span>Save JSON</span>
        </button>
    </div>
</header>
