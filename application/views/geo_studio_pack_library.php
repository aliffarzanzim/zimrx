<?php
declare(strict_types=1);
?>
<div id="viewHome" class="geo-home-view" <?= $geoStudioInitialPage === 'new' ? 'hidden' : '' ?>>
    <div class="geo-home-inner">
        <div class="geo-home-toolbar">
            <input type="text" id="homeSearchInput" class="geo-input geo-home-search-input" placeholder="Search packs (e.g. Bangladesh, United States)" aria-label="Search address packs">
            <div class="geo-home-toolbar-actions">
                <button type="button" class="geo-btn geo-btn-primary" id="btnHomeNew">
                    <?= zrx_icon('plus', 13) ?>
                    <span>New</span>
                </button>
            </div>
        </div>

        <div class="geo-home-grid is-status" id="homeCardsGrid">
            <div class="geo-grid-message geo-empty-state">Loading available packs...</div>
        </div>
    </div>
</div>
