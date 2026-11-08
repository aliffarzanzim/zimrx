<?php
declare(strict_types=1);
?>
<section id="viewNewPack" class="geo-new-pack-page" <?= $geoStudioInitialPage === 'new' ? '' : 'hidden' ?>>
    <section class="geo-card geo-new-pack-card">
        <header class="geo-card-header geo-new-pack-header">
            <div class="geo-new-pack-heading">
                <button type="button" class="geo-modal-back-btn" id="btnCancelNewPack" aria-label="Cancel and return home" title="Cancel and return home">
                    <?= zrx_icon('arrow-left', 14) ?>
                </button>
                <h1 class="geo-new-pack-title">Create New Region</h1>
            </div>
        </header>
        <form id="newPackForm" class="geo-new-pack-content" novalidate>
            <div class="geo-form-grid">
                <div class="geo-form-group full">
                    <label class="geo-label">Pack Type</label>
                    <div class="geo-pack-types">
                        <label class="geo-radio-label">
                            <input type="radio" name="blankPackType" value="national" checked>
                            National (Country Level)
                        </label>
                        <label class="geo-radio-label">
                            <input type="radio" name="blankPackType" value="custom">
                            Custom (Humanitarian, Disaster Zone, Camp, Clinic Network)
                        </label>
                    </div>
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="blankDefLang">Default / Universal Language</label>
                    <select id="blankDefLang" class="geo-select" aria-describedby="blankDefaultLanguageHint"></select>
                    <span class="geo-field-hint" id="blankDefaultLanguageHint">Used as the pack's primary label language. Other language names stay in the pack.</span>
                </div>
                <div class="geo-form-group">
                    <span class="geo-label" id="blankLanguagesLabel">Languages in Pack</span>
                    <div class="geo-language-control">
                        <span id="blankLanguageSummary" class="geo-language-summary" role="list" aria-label="Languages in pack" aria-live="polite">en</span>
                        <button type="button" class="geo-btn geo-btn-primary" id="btnManageBlankLanguages" aria-haspopup="dialog" aria-controls="modalBlankLanguagesBackdrop" aria-describedby="blankLanguageManagerHint">Manage languages</button>
                    </div>
                    <input type="hidden" id="blankLanguages" value="en">
                    <span class="geo-field-hint" id="blankLanguageManagerHint">Choose the language codes used for names and level labels in this pack.</span>
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label" for="blankPackId">Pack ID</label>
                    <input type="text" id="blankPackId" class="geo-code-input geo-input" placeholder="e.g. BD (national) or CAMP-A (custom)" maxlength="32" autocomplete="off" spellcheck="false" required>
                    <span class="geo-code-hint">National packs use their two-letter country code as the ID. Custom packs use a unique ID.</span>
                </div>
                <div class="geo-form-group full">
                    <span class="geo-label">Pack Root Names</span>
                    <div id="blankRootNamesFields" class="geo-form-grid geo-language-name-fields"></div>
                    <span class="geo-field-hint">Enter the root name in each language.</span>
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label" for="blankContributor">Author / Contributor</label>
                    <input type="text" id="blankContributor" class="geo-input">
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label" for="blankCodeLabel">Code / Identifier Name (Optional)</label>
                    <input type="text" id="blankCodeLabel" class="geo-input" placeholder="e.g. Postal Code, Block Code, Clinic ID, Zone ID" value="Postal Code">
                    <div id="blankPresetChips" class="geo-preset-chips">
                        <button type="button" class="sub-btn preset-chip" data-val="Postal Code">Postal Code</button>
                        <button type="button" class="sub-btn preset-chip" data-val="ZIP Code">ZIP Code</button>
                        <button type="button" class="sub-btn preset-chip" data-val="Block Code">Block Code</button>
                        <button type="button" class="sub-btn preset-chip" data-val="Facility ID">Facility ID</button>
                        <button type="button" class="sub-btn preset-chip" data-val="Area Code">Area Code</button>
                        <button type="button" class="sub-btn preset-chip" data-val="None">None</button>
                    </div>
                    <span class="geo-identifier-hint">Define a custom identifier name for this pack.</span>
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label" for="blankLicenseSelect">License</label>
                    <select id="blankLicenseSelect" class="geo-select">
                        <option value="CC-BY-4.0" selected>CC-BY-4.0 (Creative Commons Attribution 4.0)</option>
                        <option value="CC0-1.0">CC0-1.0 (Public Domain Dedication)</option>
                        <option value="ODbL-1.0">ODbL-1.0 (Open Database License)</option>
                        <option value="MIT">MIT License</option>
                        <option value="Apache-2.0">Apache 2.0 License</option>
                        <option value="custom">Custom License (Specify below)...</option>
                    </select>
                    <div id="grpBlankCustomLicense" class="geo-license-custom" hidden>
                        <input type="text" id="blankCustomLicense" class="geo-input" placeholder="e.g. Apache-2.0, In-house, Proprietary">
                    </div>
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label" for="blankNotes">Notes &amp; Attribution (Optional)</label>
                    <input type="text" id="blankNotes" class="geo-pack-notes geo-input" placeholder="e.g. National gazette, clinic survey, or census data">
                </div>
                <div class="geo-form-group full">
                    <div class="geo-field-header-row">
                        <label class="geo-label">Hierarchy Depth Levels</label>
                        <button type="button" class="geo-btn geo-btn-xs" id="btnAddDepthLevel">
                            <?= zrx_icon('plus', 11) ?>
                            <span>Add Level</span>
                        </button>
                    </div>
                    <div class="depth-chain-wrapper">
                        <div id="depthChainList" class="depth-chain-list"></div>
                    </div>
                    <span class="geo-hierarchy-hint">Define hierarchy levels from top to bottom.</span>
                </div>
            </div>
        </form>
        <footer class="geo-new-pack-actions">
            <button type="button" class="geo-btn" id="btnCancelNewPackFooter">Cancel</button>
            <button type="button" class="geo-btn geo-btn-primary" id="btnCreateNewPack">Create Region</button>
        </footer>
    </section>
</section>
