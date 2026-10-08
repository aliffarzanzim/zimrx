<?php
declare(strict_types=1);
?>
<div id="modalImportBackdrop" class="geo-modal-backdrop">
    <div class="geo-modal-card">
        <div class="geo-modal-header">
            <div class="geo-modal-header-nav">
                <button type="button" class="geo-modal-back-btn" id="btnModalBackToChoice" style="display:none;">
                    <?= zrx_icon('arrow-left', 14) ?>
                    <span>Back</span>
                </button>
                <span id="modalImportTitle">New / Import Region</span>
            </div>
            <button type="button" class="geo-modal-close-btn" id="btnCloseImportModal" title="Close">
                <?= zrx_icon('x', 14) ?>
            </button>
        </div>
        <div class="geo-modal-body">
            <div id="modalChoiceView">
                <div class="geo-modal-subtitle">
                    Select how you would like to create or import a geographic region pack:
                </div>
                <div class="modal-choice-grid">
                    <div class="modal-choice-card" id="btnChoiceBlank">
                        <div class="choice-icon"><?= zrx_icon('edit', 20) ?></div>
                        <div class="choice-title">Create Blank Region</div>
                        <div class="choice-desc">Build a National or Custom geographic hierarchy from scratch with interactive depth levels.</div>
                        <div class="choice-arrow">
                            <span>Continue</span>
                            <?= zrx_icon('arrow-right', 12) ?>
                        </div>
                    </div>
                    <div class="modal-choice-card" id="btnChoiceGeoNames">
                        <div class="choice-icon"><?= zrx_icon('globe', 20) ?></div>
                        <div class="choice-title">Import from GeoNames</div>
                        <div class="choice-desc">Country-level administrative divisions and postal codes under Creative Commons CC-BY 4.0.</div>
                        <div class="choice-arrow">
                            <span>Continue</span>
                            <?= zrx_icon('arrow-right', 12) ?>
                        </div>
                    </div>
                    <div class="modal-choice-card" id="btnChoiceWikidata">
                        <div class="choice-icon"><?= zrx_icon('building', 20) ?></div>
                        <div class="choice-title">Import from Wikidata</div>
                        <div class="choice-desc">Administrative divisions, districts, and localities from Wikimedia SPARQL under Public Domain CC0 1.0.</div>
                        <div class="choice-arrow">
                            <span>Continue</span>
                            <?= zrx_icon('arrow-right', 12) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tabImportGeonamesBody" style="display:none;">
                <div class="geo-notice-box">
                    <strong>GeoNames License &amp; Attribution:</strong> GeoNames is licensed under <strong>Creative Commons Attribution 4.0 (CC-BY 4.0)</strong>. Geo Studio will automatically embed the required legal attribution to GeoNames (geonames.org) in the country manifest.
                </div>
                <div class="geo-form-grid" style="margin-top:12px;">
                    <div class="geo-form-group full" style="margin-bottom:2px;">
                        <div class="geo-field-header-row">
                            <label class="geo-label" for="selGeoCountryPreset">Select Country</label>
                            <button type="button" class="geo-btn-action" id="btnFetchGeoCountryList" title="Connects to geonames.org to fetch country list">
                                <?= zrx_icon('globe', 14) ?>
                                <span>Fetch Live GeoNames List</span>
                            </button>
                        </div>
                        <select id="selGeoCountryPreset" class="geo-select geo-select-full">
                            <option value="">-- No Countries Loaded (Click 'Fetch Live GeoNames List') --</option>
                        </select>
                        <input type="hidden" id="impGeoCode">
                        <input type="hidden" id="impGeoName">
                        <div id="geoSelectedCountryBadge" class="geo-selection-badge" style="display:none;"></div>
                        <div class="geo-privacy-row">
                            <span><?= zrx_icon('lock', 12) ?> <strong>Privacy Notice:</strong> External connection to geonames.org required to fetch data.</span>
                            <span id="geoListStatusBadge" class="geo-status-pending">Fetch Required</span>
                        </div>
                    </div>
                    <div class="geo-form-group full">
                        <label class="geo-label" for="impGeoContributor">Your Name (Added as Contributor)</label>
                        <input type="text" id="impGeoContributor" class="geo-input" value="Alif Farzan Zim">
                    </div>
                </div>
            </div>

            <div id="tabImportWikidataBody" style="display:none;">
                <div class="geo-notice-box geo-notice-box-success">
                    <strong>Wikidata Public Domain (CC0 1.0):</strong> Data from Wikidata and Wikimedia is dedicated to the public domain under <strong>Creative Commons CC0 1.0 Universal</strong>. Geo Studio will automatically embed official CC0-1.0 attribution to Wikidata contributors in the country manifest.
                </div>
                <div class="geo-form-grid" style="margin-top:12px;">
                    <div class="geo-form-group full" style="margin-bottom:2px;">
                        <div class="geo-field-header-row">
                            <label class="geo-label" for="selWikiCountryPreset">Select Country</label>
                            <button type="button" class="geo-btn-action" id="btnFetchWikiCountryList" title="Connects to Wikidata SPARQL to fetch country catalog">
                                <?= zrx_icon('building', 14) ?>
                                <span>Fetch Live Wikidata Countries</span>
                            </button>
                        </div>
                        <select id="selWikiCountryPreset" class="geo-select geo-select-full">
                            <option value="">-- No Countries Loaded (Click 'Fetch Live Wikidata Countries') --</option>
                        </select>
                        <input type="hidden" id="impWikiCode">
                        <input type="hidden" id="impWikiName">
                        <div id="wikiSelectedCountryBadge" class="geo-selection-badge" style="display:none;"></div>
                        <div class="geo-privacy-row">
                            <span><?= zrx_icon('lock', 12) ?> <strong>Privacy Notice:</strong> External connection to query.wikidata.org required to fetch data.</span>
                            <span id="wikiListStatusBadge" class="geo-status-pending">Fetch Required</span>
                        </div>
                    </div>
                    <div class="geo-form-group full" id="grpWikiLevels" style="display:none; margin-top:4px;">
                        <div class="geo-field-header-row">
                            <label class="geo-label">Hierarchy Levels to Import</label>
                            <span id="wikiLevelsStatus" style="font-size:11px; color:var(--zrx-primary); font-weight:600;"></span>
                        </div>
                        <div id="wikiLevelsList" class="wiki-levels-checkbox-list"></div>
                        <span style="font-size:10px; color:var(--zrx-text-muted); margin-top:3px;">
                            Select the hierarchy levels to import into your dataset. Unchecked levels will be omitted.
                        </span>
                    </div>
                    <div class="geo-form-group full">
                        <label class="geo-label" for="impWikiContributor">Your Name (Added as Contributor)</label>
                        <input type="text" id="impWikiContributor" class="geo-input" value="Alif Farzan Zim">
                    </div>
                </div>
            </div>

            <div id="tabCreateBlankBody" style="display:none;">
                <div class="geo-form-grid">
                    <div class="geo-form-group full">
                        <label class="geo-label">Pack Type</label>
                        <div style="display:flex; gap:24px; margin-top:4px;">
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; font-size:12px;">
                                <input type="radio" name="blankPackType" value="national" checked>
                                National (Country Level)
                            </label>
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; font-size:12px;">
                                <input type="radio" name="blankPackType" value="custom">
                                Custom (Humanitarian, Disaster Zone, Camp, Clinic Network)
                            </label>
                        </div>
                    </div>
                    <div class="geo-form-group full" id="grpBlankCountry">
                        <label class="geo-label" for="blankCountrySelect">Country</label>
                        <select id="blankCountrySelect" class="geo-select geo-select-full">
                            <option value="">-- Select Country --</option>
                            <?php foreach (($zrxCountryCatalog['grouped']['Countries & Territories'] ?? []) as $iso => $cName): ?>
                                <option value="<?= htmlspecialchars($iso) ?>" data-name="<?= htmlspecialchars($cName) ?>"><?= htmlspecialchars($cName) ?> (<?= htmlspecialchars($iso) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="geo-form-group" id="grpBlankCode" hidden>
                        <label class="geo-label" id="lblBlankCode" for="blankCode">Pack Identifier / Code</label>
                        <input type="text" id="blankCode" class="geo-input" placeholder="e.g. UN-CXB, CAMP-A, MSF-01" maxlength="16" style="text-transform:uppercase;">
                        <span id="hintBlankCode" style="font-size:10px; color:var(--zrx-text-muted); margin-top:2px;">2-16 characters identifier (e.g. UN-CXB, CAMP-A, ZONE-1)</span>
                    </div>
                    <div class="geo-form-group" id="grpBlankName" hidden>
                        <label class="geo-label" id="lblBlankName" for="blankName">Pack / Settlement / Zone Name</label>
                        <input type="text" id="blankName" class="geo-input" placeholder="e.g. Cox's Bazar Camps">
                    </div>
                    <div class="geo-form-group">
                        <label class="geo-label" for="blankDefLang">Default Language</label>
                        <input type="text" id="blankDefLang" class="geo-input" value="en">
                    </div>
                    <div class="geo-form-group">
                        <label class="geo-label" for="blankLocLang">Local Language</label>
                        <input type="text" id="blankLocLang" class="geo-input" value="en">
                    </div>
                    <div class="geo-form-group full">
                        <label class="geo-label" for="blankContributor">Author / Contributor</label>
                        <input type="text" id="blankContributor" class="geo-input" value="Alif Farzan Zim">
                    </div>
                    <div class="geo-form-group full">
                        <label class="geo-label" for="blankCodeLabel">Code / Identifier Name (Optional)</label>
                        <input type="text" id="blankCodeLabel" class="geo-input" placeholder="e.g. Postal Code, Block Code, Clinic ID, Zone ID" value="Postal Code">
                        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:5px;" id="blankPresetChips">
                            <button type="button" class="sub-btn preset-chip" data-val="Postal Code">Postal Code</button>
                            <button type="button" class="sub-btn preset-chip" data-val="ZIP Code">ZIP Code</button>
                            <button type="button" class="sub-btn preset-chip" data-val="Block Code">Block Code</button>
                            <button type="button" class="sub-btn preset-chip" data-val="Facility ID">Facility ID</button>
                            <button type="button" class="sub-btn preset-chip" data-val="Area Code">Area Code</button>
                            <button type="button" class="sub-btn preset-chip" data-val="None">None</button>
                        </div>
                        <span style="font-size:10px; color:var(--zrx-text-muted); margin-top:3px;">Define custom identifier name for this pack.</span>
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
                        <div id="grpBlankCustomLicense" style="display:none; margin-top:6px;">
                            <input type="text" id="blankCustomLicense" class="geo-input" placeholder="e.g. Apache-2.0, In-house, Proprietary">
                        </div>
                    </div>
                    <div class="geo-form-group full">
                        <label class="geo-label" for="blankNotes">Notes &amp; Attribution (Optional)</label>
                        <textarea id="blankNotes" class="geo-input" style="height:48px; padding:6px 10px; font-family:inherit; resize:vertical;" placeholder="e.g. Administrative divisions sourced from national gazette, clinic survey, or census data..."></textarea>
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
                        <span style="font-size:10px; color:var(--zrx-text-muted); margin-top:4px;">Define hierarchy levels from top to bottom.</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnCancelImport">Cancel</button>
            <button type="button" class="geo-btn geo-btn-primary" id="btnExecuteImport" style="display:none;">Continue</button>
        </div>
    </div>
</div>

<div id="modalManifestBackdrop" class="geo-modal-backdrop">
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
                    <label class="geo-label" for="manAuthor">Author</label>
                    <input type="text" id="manAuthor" class="geo-input" value="Alif Farzan Zim">
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manCountryName">Country Name</label>
                    <input type="text" id="manCountryName" class="geo-input">
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
                    <div id="grpManCustomLicense" style="display:none; margin-top:6px;">
                        <input type="text" id="manCustomLicense" class="geo-input" placeholder="e.g. Apache-2.0, In-house, Proprietary">
                    </div>
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manReleaseDate">Release Date</label>
                    <input type="text" id="manReleaseDate" class="geo-input" readonly style="background:#f8fafc; color:#64748b;">
                </div>
                <div class="geo-form-group">
                    <label class="geo-label" for="manLastUpdated">Last Updated (Auto-set on save)</label>
                    <input type="text" id="manLastUpdated" class="geo-input" readonly style="background:#f8fafc; color:#64748b;">
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label" for="manActiveContributor">Your Contributor Name (Automatically added on save)</label>
                    <input type="text" id="manActiveContributor" class="geo-input" value="Alif Farzan Zim">
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label">Registered Contributors</label>
                    <div id="manContributorsChips" style="display:flex; flex-wrap:wrap; gap:6px; margin:4px 0;"></div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <input type="text" id="inpAddContributor" class="geo-input" placeholder="Add contributor name..." style="flex:1; height:28px;">
                        <button type="button" class="geo-btn" id="btnAddContributorChip" style="height:28px;">Add</button>
                    </div>
                </div>
                <div class="geo-form-group full">
                    <label class="geo-label" for="manNotes">Attribution &amp; Release Notes</label>
                    <textarea id="manNotes" class="geo-input" style="height:60px; padding:6px 10px; font-family:inherit; resize:vertical;"></textarea>
                </div>
            </div>
        </div>
        <div class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnCancelManifest">Close</button>
            <button type="button" class="geo-btn geo-btn-primary" id="btnApplyManifest">Apply to Active Dataset</button>
        </div>
    </div>
</div>

<div id="modalExternalConfirmBackdrop" class="geo-modal-backdrop" style="z-index:3000;">
    <div class="geo-modal-card" style="width:480px; max-width:95vw;">
        <div class="geo-modal-header geo-confirm-header">
            <div class="geo-confirm-title">
                <?= zrx_icon('alert-circle', 16) ?>
                <span>External Network Connection</span>
            </div>
            <button type="button" class="geo-modal-close-btn" id="btnCloseExternalConfirm" title="Close">
                <?= zrx_icon('x', 14) ?>
            </button>
        </div>
        <div class="geo-modal-body" style="padding:16px 20px;">
            <div id="extConfirmMessage" style="font-size:13px; color:var(--zrx-text-dark, #0f172a); line-height:1.5; margin-bottom:12px;">
                You are connecting to the external internet (<strong id="extConfirmTargetHost">geonames.org</strong>) to fetch geographic data.
            </div>
            <div class="geo-confirm-callout">
                <span><?= zrx_icon('lock', 12) ?></span>
                <span><strong>Privacy Notice:</strong> This request leaves your offline environment. Your public IP address and connection details will be visible to external servers.</span>
            </div>
            <div style="font-size:12px; color:var(--zrx-text-dark, #0f172a); font-weight:600;">
                Are you sure you want to proceed and connect to the external internet?
            </div>
        </div>
        <div class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnCancelExternalConfirm">Cancel</button>
            <button type="button" class="geo-btn geo-btn-warning" id="btnProceedExternalConfirm">Connect &amp; Fetch</button>
        </div>
    </div>
</div>

<div id="modalOverwriteConfirmBackdrop" class="geo-modal-backdrop geo-modal-top">
    <div class="geo-modal-card geo-modal-card-sm">
        <div class="geo-modal-header geo-confirm-header">
            <div class="geo-confirm-title">
                <?= zrx_icon('alert-circle', 16) ?>
                <span>Pack Already Exists</span>
            </div>
            <button type="button" class="geo-modal-close-btn" id="btnCloseOverwriteConfirm" title="Close">
                <?= zrx_icon('x', 14) ?>
            </button>
        </div>
        <div class="geo-modal-body">
            <div class="geo-confirm-text">
                The pack <strong id="overwriteConfirmTarget"></strong> already exists. Do you want to override it and create a new pack?
            </div>
            <div class="geo-notice-box geo-notice-box-warning">
                The existing hierarchy, manifest and contributors in this pack will be replaced. This cannot be undone.
            </div>
        </div>
        <div class="geo-modal-footer">
            <button type="button" class="geo-btn" id="btnCancelOverwriteConfirm">Cancel</button>
            <button type="button" class="geo-btn geo-btn-warning" id="btnProceedOverwriteConfirm">Override Pack</button>
        </div>
    </div>
</div>

<div id="geoToast" class="geo-toast"></div>
