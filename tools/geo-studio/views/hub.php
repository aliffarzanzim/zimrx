<?php
declare(strict_types=1);
?>
<div id="viewHub" class="geo-hub-view">
    <div class="geo-hub-inner">
        <div class="geo-hub-hero">
            <div>
                <h1 class="geo-hub-title">Geographic &amp; Custom Regional Packs</h1>
                <p class="geo-hub-subtitle">
                    Geographic catalog designer for prescription headers, patient demographics, and healthcare regions.
                </p>
            </div>
            <div class="geo-hub-stats-row">
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalPacks">0</span>
                    <span class="hub-stat-lbl">Active Packs</span>
                </div>
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubGlobalPlaces">0</span>
                    <span class="hub-stat-lbl">Address Units</span>
                </div>
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubGlobalPostcodes">0</span>
                    <span class="hub-stat-lbl">Postal Codes</span>
                </div>
            </div>
        </div>

        <div class="geo-hub-toolbar">
            <input type="text" id="hubSearchInput" class="geo-input geo-hub-search-input" placeholder="Search country packs (e.g. Bangladesh, GB, US)...">
            <button type="button" class="geo-btn geo-btn-primary" id="btnHubNewImportHero">
                <?= zrx_icon('plus', 13) ?>
                <span>New / Import Region</span>
            </button>
        </div>

        <div class="geo-hub-grid" id="hubCardsGrid">
            <div class="geo-empty-state geo-col-full">Loading country catalogs...</div>
        </div>
    </div>
</div>
