<?php
declare(strict_types=1);
?>
<div id="modalManifestBackdrop" class="geo-modal-backdrop" hidden>
    <div class="geo-modal-card">
        <div class="geo-modal-header">
            <span>Manifest &amp; Contributor Details</span>
            <button type="button" class="geo-modal-close-btn" id="btnCloseManifestModal" title="Close">
                <?= zrx_icon('x', 14) ?>
            </button>
        </div>
        <div class="geo-modal-body">
            <div class="geo-form-grid">
                <div class="geo-form-group">
                    <label class="geo-label" for="manDefaultLanguage">Default / Universal Language</label>
                    <select id="manDefaultLanguage" class="geo-select" aria-describedby="manDefaultLanguageHint"></select>
                    <span class="geo-field-hint" id="manDefaultLanguageHint">Used as the pack's primary label language. Other language names stay in the pack.</span>
                </div>
                <div class="geo-form-group">
                    <span class="geo-label" id="manLanguagesLabel">Languages in Pack</span>
                    <div class="geo-language-control">
                        <span id="manLanguageSummary" class="geo-language-summary" role="list" aria-label="Languages in pack" aria-live="polite"></span>
                        <button type="button" class="geo-btn geo-btn-primary" id="btnManageManifestLanguages" aria-haspopup="dialog" aria-controls="modalBlankLanguagesBackdrop" aria-describedby="manLanguageManagerHint">Manage languages</button>
                    </div>
                    <input type="hidden" id="manLanguages" value="en" aria-labelledby="manLanguagesLabel" aria-describedby="manLanguageManagerHint">
                    <span class="geo-field-hint" id="manLanguageManagerHint">Choose the language codes retained by this pack.</span>
                </div>
                <div class="geo-form-group full">
                    <span class="geo-label">Pack Root Names</span>
                    <div id="manRootNamesFields" class="geo-form-grid geo-language-name-fields"></div>
                    <span class="geo-field-hint">Enter the root name in each language.</span>
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manAuthor">Author</label>
                    <input type="text" id="manAuthor" class="geo-input">
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manCodeLabel">Code / Identifier Label</label>
                    <input type="text" id="manCodeLabel" class="geo-input" placeholder="e.g. Postal Code, Block Code, Clinic ID">
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manVersion">Version</label>
                    <input type="text" id="manVersion" class="geo-input" value="1.0.0">
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manLicenseSelect">License</label>
                    <select id="manLicenseSelect" class="geo-select">
                        <option value="CC-BY-4.0">CC-BY-4.0 (Creative Commons Attribution 4.0)</option>
                        <option value="CC0-1.0">CC0-1.0 (Public Domain Dedication)</option>
                        <option value="ODbL-1.0">ODbL-1.0 (Open Database License)</option>
                        <option value="MIT">MIT License</option>
                        <option value="Apache-2.0">Apache 2.0 License</option>
                        <option value="custom">Custom License (Specify below)...</option>
                    </select>
                    <div id="grpManCustomLicense" class="geo-license-custom" hidden>
                        <input type="text" id="manCustomLicense" class="geo-input" placeholder="e.g. Apache-2.0, In-house, Proprietary">
                    </div>
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manReleaseDate">Release Date</label>
                    <input type="text" id="manReleaseDate" class="geo-readonly geo-input" readonly>
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manLastUpdated">Last Updated (Auto-set on save)</label>
                    <input type="text" id="manLastUpdated" class="geo-readonly geo-input" readonly>
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label">Pack Contributors</label>
                    <div id="manContributorsChips" class="geo-contributors"></div>
                    <span class="geo-field-hint">Your saved contributor name is included automatically. Add or remove other credited contributors here.</span>
                    <div class="geo-contributor-entry">
                        <input type="text" id="inpAddContributor" class="geo-contributor-input geo-input" placeholder="Add contributor name...">
                        <button type="button" class="geo-contributor-add geo-btn" id="btnAddContributorChip">Add</button>
                    </div>
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label" for="manNotes">Attribution &amp; Release Notes</label>
                    <textarea id="manNotes" class="geo-manifest-notes geo-input"></textarea>
                </div>
            </div>
        </div>
        <div class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnCancelManifest">Close</button>
            <button type="button" class="geo-btn geo-btn-primary" id="btnApplyManifest">Apply to Active Dataset</button>
        </div>
    </div>
</div>

<div id="modalOverwriteConfirmBackdrop" class="geo-modal-backdrop geo-modal-top geo-modal-external-confirm" hidden>
    <div class="geo-modal-card geo-modal-card-sm">
        <div class="geo-modal-header">
            <span>Replace existing pack?</span>
            <button type="button" class="geo-modal-close-btn" id="btnCloseOverwriteConfirm" title="Close">
                <?= zrx_icon('x', 14) ?>
            </button>
        </div>
        <div class="geo-modal-body geo-external-confirm-body">
            <div class="geo-external-confirm-message">
                The pack <strong id="overwriteConfirmTarget"></strong> already exists. Replacing it will discard its current hierarchy, manifest, and contributors. This cannot be undone.
            </div>
        </div>
        <div class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnCancelOverwriteConfirm">Cancel</button>
            <button type="button" class="geo-btn geo-btn-primary" id="btnProceedOverwriteConfirm">Replace pack</button>
        </div>
    </div>
</div>

<div id="geoToast" class="geo-toast"></div>

<div id="modalBlankLanguagesBackdrop" class="geo-modal-backdrop" hidden>
    <section class="geo-modal-card geo-modal-card-sm geo-blank-language-modal">
        <header class="geo-modal-header">
            <span>Manage languages</span>
            <button type="button" class="geo-modal-close-btn" id="btnCloseBlankLanguageModal" title="Close" aria-label="Close language manager">
                <?= zrx_icon('x', 14) ?>
            </button>
        </header>
        <div class="geo-modal-body">
            <p class="geo-blank-language-help">These codes define the languages available for place names and hierarchy labels. Enter a code such as en, bn, or es-MX. Keep at least one language in the pack.</p>
            <div id="blankLanguageList" class="geo-blank-language-list" role="list" aria-label="Languages in pack"></div>
            <div class="geo-blank-language-add">
                <label class="geo-label" for="blankLanguageCodeInput">Add language code</label>
                <div class="geo-blank-language-add-row">
                    <input type="text" id="blankLanguageCodeInput" class="geo-input" maxlength="35" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="e.g. bn or es-MX" aria-describedby="blankLanguageCodeStatus">
                    <button type="button" class="geo-btn geo-btn-primary" id="btnAddBlankLanguage">Add</button>
                </div>
                <span id="blankLanguageCodeStatus" class="geo-field-hint" role="status" aria-live="polite"></span>
            </div>
        </div>
        <footer class="geo-modal-footer">
            <button type="button" class="geo-btn geo-btn-primary" id="btnDoneBlankLanguageModal">Done</button>
        </footer>
    </section>
</div>

<div id="modalHierarchyLevelsBackdrop" class="geo-modal-backdrop" hidden>
    <section class="geo-modal-card geo-hierarchy-levels-card">
        <header class="geo-modal-header">
            <span>Manage hierarchy depth levels</span>
            <button type="button" class="geo-modal-close-btn" id="btnCloseHierarchyLevelsModal" title="Close" aria-label="Close hierarchy depth level manager">
                <?= zrx_icon('x', 14) ?>
            </button>
        </header>
        <div class="geo-modal-body">
            <p class="geo-hierarchy-levels-help">Edit level names and place types. Existing place records and assigned type keys stay as they are.</p>
            <div class="geo-field-header-row">
                <span class="geo-label geo-label-m0">Hierarchy depth levels</span>
                <button type="button" class="geo-btn geo-btn-xs" id="btnAddHierarchyDepthLevel">
                    <?= zrx_icon('plus', 11) ?>
                    <span>Add Level</span>
                </button>
            </div>
            <div class="depth-chain-wrapper">
                <div id="hierarchyDepthList" class="depth-chain-list"></div>
            </div>
            <span id="hierarchyLevelsStatus" class="geo-field-hint geo-hierarchy-levels-status" role="alert"></span>
        </div>
        <footer class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnCancelHierarchyLevelsModal">Cancel</button>
            <button type="button" class="geo-btn geo-btn-primary" id="btnSaveHierarchyLevels">Save levels</button>
        </footer>
    </section>
</div>

<div id="modalAddChildUnitBackdrop" class="geo-modal-backdrop" hidden>
    <form id="formAddChildUnit" class="geo-modal-card geo-modal-card-sm">
        <header class="geo-modal-header">
            <span>Add child unit</span>
            <button type="button" class="geo-modal-close-btn" id="btnCloseChildType" title="Close" aria-label="Close add child unit dialog">
                <?= zrx_icon('x', 14) ?>
            </button>
        </header>
        <div class="geo-modal-body">
            <p class="geo-child-type-help">This unit will be added at <strong id="childTypeDepth"></strong>.</p>
            <div id="childTypeField" class="geo-form-group" hidden>
                <label class="geo-label" for="selChildType">Unit type</label>
                <select id="selChildType" class="geo-input"></select>
            </div>
            <div class="geo-form-group">
                <label class="geo-label" id="lblChildUnitName" for="inpChildUnitName">Unit name</label>
                <input type="text" id="inpChildUnitName" class="geo-input" autocomplete="off">
            </div>
            <span id="childUnitStatus" class="geo-field-hint geo-child-unit-status" role="alert" hidden></span>
        </div>
        <footer class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnCancelChildType">Cancel</button>
            <button type="submit" class="geo-btn geo-btn-primary" id="btnConfirmChildType">Add child unit</button>
        </footer>
    </form>
</div>

<div id="modalTypeCountsBackdrop" class="geo-modal-backdrop" hidden>
    <section class="geo-modal-card geo-modal-card-sm">
        <header class="geo-modal-header">
            <span>Place counts by type</span>
            <button type="button" class="geo-modal-close-btn" id="btnCloseTypeCounts" title="Close" aria-label="Close place counts">
                <?= zrx_icon('x', 14) ?>
            </button>
        </header>
        <div class="geo-modal-body">
            <p class="geo-type-counts-help" id="typeCountsHelp"></p>
            <div class="geo-type-counts-list" id="typeCountsList"></div>
        </div>
        <footer class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnDoneTypeCounts">Close</button>
        </footer>
    </section>
</div>
