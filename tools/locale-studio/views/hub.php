<?php
declare(strict_types=1);
?>
<div id="viewHub" class="geo-hub-view">
    <div class="geo-hub-inner">
        <!-- Hero Header matching Geo Studio -->
        <div class="geo-hub-hero">
            <div>
                <h1 class="geo-hub-title">Clinical Language Packs &amp; Catalogs</h1>
                <p class="geo-hub-subtitle">
                    Medical translation workspace for prescribing instructions, dosages, clinical advices, and patient guidelines with doctor-verified status tracking.
                </p>
            </div>
            <div class="geo-hub-stats-row">
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalLangs">0</span>
                    <span class="hub-stat-lbl">Active Locales</span>
                </div>
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalCatalogs">0</span>
                    <span class="hub-stat-lbl">JSON Catalogs</span>
                </div>
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalStrings">0</span>
                    <span class="hub-stat-lbl">Medical Strings</span>
                </div>
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalVerified">0</span>
                    <span class="hub-stat-lbl">Verified Packs</span>
                </div>
            </div>
        </div>

        <!-- Toolbar matching Geo Studio -->
        <div class="geo-hub-toolbar">
            <input type="text" id="hubSearchInput" class="geo-input geo-hub-search-input" placeholder="Search languages by name, native script, or code (e.g. English, বাংলা, bn, es)...">
            <button type="button" class="geo-btn geo-btn-primary" id="btnHubNewLangHero">
                <?= zrx_icon('plus', 13) ?>
                <span>New Language Pack</span>
            </button>
        </div>

        <!-- Language Cards Grid matching Geo Studio country cards -->
        <div class="geo-hub-grid" id="hubCardsGrid">
            <div class="geo-empty-state geo-col-full">Loading language catalogs...</div>
        </div>
    </div>
</div>
