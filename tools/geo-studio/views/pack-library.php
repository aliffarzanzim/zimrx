<?php
declare(strict_types=1);
?>
<div id="viewHome" class="geo-home-view" <?= $geoStudioInitialPage === 'new' ? 'hidden' : '' ?>>
    <div class="geo-home-inner">
        <div class="geo-home-hero">
            <div>
                <h1 class="geo-home-title">Geographic &amp; Custom Regional Packs</h1>
                <p class="geo-home-subtitle">
                    Geographic catalog designer for prescription headers, patient demographics, and healthcare regions.
                </p>
            </div>
            <div class="geo-home-stats-row">
                <div class="home-stat-item">
                    <span class="home-stat-val" id="homeTotalPacks">0</span>
                    <span class="home-stat-lbl">Active Packs</span>
                </div>
                <div class="home-stat-item">
                    <span class="home-stat-val" id="homeGlobalPlaces">0</span>
                    <span class="home-stat-lbl">Address Units</span>
                </div>
            </div>
        </div>

        <div class="geo-home-toolbar">
            <input type="text" id="homeSearchInput" class="geo-input geo-home-search-input" placeholder="Search packs (e.g. Bangladesh, United States)" aria-label="Search address packs">
            <div class="geo-home-toolbar-actions">
                <button type="button" class="geo-btn geo-btn-primary" id="btnHomeNew">
                    <?= \GeoStudio\IconRegistry::svg('plus', 13) ?>
                    <span>New</span>
                </button>
            </div>
        </div>

        <div class="geo-home-grid is-status" id="homeCardsGrid">
            <div class="geo-grid-message geo-empty-state">Loading available packs...</div>
        </div>
    </div>
</div>
