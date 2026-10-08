<?php
declare(strict_types=1);
?>
<div id="viewWorkspace" class="locale-workspace-view" style="display:none;">
    <!-- Language Meta Subbar -->
    <div class="locale-meta-subbar">
        <div class="locale-meta-left">
            <button type="button" class="locale-btn-back" id="btnWorkspaceBackToHub" title="Back to Languages Directory">
                <?= zrx_icon('arrow-left', 14) ?>
                <span>Directory</span>
            </button>
            <div class="locale-target-badge-wrap">
                <span class="locale-code-badge" id="wsTargetCode">BN</span>
                <div class="locale-target-titles">
                    <span class="locale-target-name" id="wsTargetName">Bengali</span>
                    <span class="locale-target-native" id="wsTargetNative">বাংলা</span>
                </div>
                <span class="locale-dir-badge" id="wsTargetDirection">LTR</span>
            </div>
            <div class="locale-ref-indicator">
                <span class="locale-ref-label">Reference:</span>
                <select id="wsRefLangSelect" class="locale-select-inline" title="Switch Reference Language">
                    <option value="en">English (en)</option>
                </select>
            </div>
        </div>

        <div class="locale-meta-right">
            <span class="locale-meta-item">
                <?= zrx_icon('user', 12) ?>
                <span id="wsAuthor">ZimRx Author</span>
            </span>
            <span class="locale-meta-item">
                <?= zrx_icon('clock', 12) ?>
                <span id="wsLastUpdated"><?= date('Y-m-d') ?></span>
            </span>
            <span class="locale-status-badge-live" id="wsSaveStatusBadge">All saved</span>
        </div>
    </div>

    <!-- Dynamic Catalog Tabs Strip -->
    <div class="locale-tabs-bar">
        <div class="locale-tabs-scroll" id="catalogTabsContainer">
            <!-- Dynamic tabs inserted via JavaScript -->
        </div>
        <button type="button" class="locale-tab-add-btn" id="btnWsAddNewCatalog" title="Create a new JSON catalog file across all locales">
            <?= zrx_icon('plus', 12) ?>
            <span>Add Catalog</span>
        </button>
    </div>

    <!-- Active Catalog Control & Status Strip -->
    <div class="locale-catalog-control-strip">
        <div class="locale-control-left">
            <!-- Stats Counters -->
            <div class="locale-counts-badge">
                <span class="locale-count-pill">Total: <strong id="catTotalKeys">0</strong></span>
                <span class="locale-count-pill success">Translated: <strong id="catTranslatedKeys">0</strong></span>
                <span class="locale-count-pill warning">Pending: <strong id="catUntranslatedKeys">0</strong></span>
                <span class="locale-count-pill primary"><strong id="catCompletionPct">0%</strong></span>
            </div>

            <!-- Status Filter Switcher -->
            <div class="locale-filter-group">
                <button type="button" class="locale-filter-btn active" data-filter="all">All</button>
                <button type="button" class="locale-filter-btn" data-filter="untranslated">Untranslated Only</button>
                <button type="button" class="locale-filter-btn" data-filter="translated">Translated</button>
            </div>

            <!-- In-catalog Search Filter -->
            <div class="locale-search-box-inline">
                <span class="locale-search-icon-sm"><?= zrx_icon('search', 12) ?></span>
                <input type="text" id="wsCatalogSearchInput" class="locale-input-search-inline" placeholder="Filter keys or text...">
                <button type="button" class="locale-clear-search-btn" id="btnWsClearSearch" style="display:none;" title="Clear search">
                    <?= zrx_icon('x', 11) ?>
                </button>
            </div>
        </div>

        <div class="locale-control-right">
            <!-- TWO OPTION TOGGLES REQUIRED BY USER: Mark as Complete & Mark as Verified -->
            <div class="locale-status-toggles-box">
                <!-- Option 1: Mark as Complete Toggle -->
                <button type="button" class="locale-toggle-btn" id="btnToggleComplete" title="Mark this JSON catalog translation as complete">
                    <span class="locale-toggle-icon"><?= zrx_icon('check', 13) ?></span>
                    <span class="locale-toggle-label" id="lblToggleComplete">Mark as Complete</span>
                </button>

                <!-- Option 2: Mark as Verified Toggle -->
                <button type="button" class="locale-toggle-btn verified-btn" id="btnToggleVerified" title="Mark this JSON catalog as clinically doctor-verified">
                    <span class="locale-toggle-icon"><?= zrx_icon('shield', 13) ?></span>
                    <span class="locale-toggle-label" id="lblToggleVerified">Mark as Verified</span>
                </button>
            </div>

            <!-- Helper: Copy untranslated from reference -->
            <button type="button" class="geo-btn geo-btn-sm" id="btnCopyAllUntranslated" title="Copy all untranslated strings from reference language">
                <?= zrx_icon('download', 12) ?>
                <span>Copy Untranslated</span>
            </button>

            <!-- Save JSON Button -->
            <button type="button" class="geo-btn geo-btn-primary geo-btn-sm" id="btnWsSaveCatalog" title="Save changes (Ctrl+S)">
                <?= zrx_icon('save', 12) ?>
                <span>Save JSON</span>
            </button>
        </div>
    </div>

    <!-- Verified Contributor Bar (shown when catalog is verified) -->
    <div class="locale-verified-strip" id="wsVerifiedNotice" style="display:none;">
        <span class="locale-verified-icon"><?= zrx_icon('shield', 14) ?></span>
        <span>Clinically Verified Translation. Verified by: <strong id="wsVerifiedAuthorName">-</strong> on <span id="wsVerifiedDate">-</span></span>
        <button type="button" class="locale-verified-edit-btn" id="btnEditVerifier" title="Change verifier name">
            <?= zrx_icon('edit', 11) ?>
            <span>Edit</span>
        </button>
    </div>

    <!-- Side-by-Side Synchronized Translation Grid -->
    <main class="locale-translation-main">
        <div class="locale-grid-header-row">
            <div class="locale-col-header ref-col-header">
                <div class="locale-header-title">
                    <span class="locale-pill-tag">REFERENCE</span>
                    <span id="headerRefLangTitle">English (en)</span>
                </div>
                <span class="locale-header-sub">Read-only clinical reference</span>
            </div>

            <div class="locale-col-header target-col-header">
                <div class="locale-header-title">
                    <span class="locale-pill-tag target">TARGET</span>
                    <span id="headerTargetLangTitle">Bengali (bn)</span>
                    <span class="locale-pill-dir" id="headerTargetDir">LTR</span>
                </div>
                <span class="locale-header-sub">Editable localization &amp; search aliases</span>
            </div>
        </div>

        <div class="locale-rows-scrollable-container" id="translationRowsContainer">
            <div class="locale-empty-state">Select a catalog to begin translation.</div>
        </div>
    </main>
</div>
