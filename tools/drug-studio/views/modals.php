<?php
declare(strict_types=1);
?>

<!-- 1. Add Country Pack Modal -->
<div class="drug-modal-overlay" id="modalAddCountry" style="display:none;">
    <div class="drug-modal">
        <div class="drug-modal-header">
            <h3><?= zrx_icon('plus', 15) ?> Add National Commercial Formulary</h3>
            <button type="button" class="drug-modal-close" id="btnCloseAddCountryModal">&times;</button>
        </div>
        <form id="formAddCountry" class="drug-modal-body">
            <p class="drug-modal-desc">
                Initialize an isolated country brand catalog (<code>drug_brand_{code}</code>) and manufacturer registry (<code>drug_manufacturer_{code}</code>).
            </p>

            <div class="drug-form-grid">
                <div class="drug-form-group">
                    <label class="drug-label" for="inpCountryCode">ISO Country Code *</label>
                    <input type="text" id="inpCountryCode" class="drug-input" placeholder="e.g. US, GB, IN, KE" maxlength="2" required style="text-transform: uppercase;">
                    <span class="drug-hint">Standard 2-letter ISO 3166-1 alpha-2 code</span>
                </div>

                <div class="drug-form-group">
                    <label class="drug-label" for="inpCountryName">Country / Region Name *</label>
                    <input type="text" id="inpCountryName" class="drug-input" placeholder="e.g. United States, Kenya" required>
                </div>

                <div class="drug-form-group">
                    <label class="drug-label" for="inpCountryAuthor">Curator / Clinician</label>
                    <input type="text" id="inpCountryAuthor" class="drug-input" placeholder="e.g. Alif Farzan Zim">
                </div>

                <div class="drug-form-group">
                    <label class="drug-label" for="inpCountryLicense">Catalog License</label>
                    <input type="text" id="inpCountryLicense" class="drug-input" value="Proprietary Clinical / Open Medical Reference">
                </div>

                <div class="drug-form-group full">
                    <label class="drug-label" for="inpCountryAttribution">Attribution / Regulatory Source</label>
                    <input type="text" id="inpCountryAttribution" class="drug-input" placeholder="e.g. National Drug Authority / DGDA Catalog">
                </div>
            </div>

            <div class="drug-modal-footer">
                <button type="button" class="drug-btn" id="btnCancelAddCountry">Cancel</button>
                <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitAddCountry">
                    <?= zrx_icon('check', 13) ?>
                    <span>Create Country Pack</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Edit Manifest Modal -->
<div class="drug-modal-overlay" id="modalManifest" style="display:none;">
    <div class="drug-modal">
        <div class="drug-modal-header">
            <h3><?= zrx_icon('edit', 15) ?> Edit Catalog Manifest</h3>
            <button type="button" class="drug-modal-close" id="btnCloseManifestModal">&times;</button>
        </div>
        <form id="formManifest" class="drug-modal-body">
            <div class="drug-form-grid">
                <div class="drug-form-group">
                    <label class="drug-label" for="inpManScope">Scope</label>
                    <input type="text" id="inpManScope" class="drug-input drug-input-readonly" readonly>
                </div>

                <div class="drug-form-group">
                    <label class="drug-label" for="inpManVersion">Version</label>
                    <input type="text" id="inpManVersion" class="drug-input" placeholder="1.0.0">
                </div>

                <div class="drug-form-group full">
                    <label class="drug-label" for="inpManName">Catalog Dataset Name</label>
                    <input type="text" id="inpManName" class="drug-input" required>
                </div>

                <div class="drug-form-group">
                    <label class="drug-label" for="inpManAuthor">Author / Lead</label>
                    <input type="text" id="inpManAuthor" class="drug-input">
                </div>

                <div class="drug-form-group">
                    <label class="drug-label" for="inpManLicense">License Identifier</label>
                    <input type="text" id="inpManLicense" class="drug-input">
                </div>

                <div class="drug-form-group full">
                    <label class="drug-label" for="inpManContributors">Contributors (comma separated)</label>
                    <input type="text" id="inpManContributors" class="drug-input" placeholder="Alif Farzan Zim, Dr. John">
                </div>

                <div class="drug-form-group full">
                    <label class="drug-label" for="inpManAttribution">Source / Regulatory Attribution</label>
                    <input type="text" id="inpManAttribution" class="drug-input">
                </div>

                <div class="drug-form-group full">
                    <label class="drug-label" for="inpManNotes">Release Notes</label>
                    <textarea id="inpManNotes" class="drug-textarea" rows="3"></textarea>
                </div>
            </div>

            <div class="drug-modal-footer">
                <button type="button" class="drug-btn" id="btnCancelManifest">Cancel</button>
                <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitManifest">
                    <?= zrx_icon('save', 13) ?>
                    <span>Save Manifest</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Export Success / Result Modal -->
<div class="drug-modal-overlay" id="modalExportResult" style="display:none;">
    <div class="drug-modal drug-modal-lg">
        <div class="drug-modal-header">
            <h3><?= zrx_icon('check', 15) ?> Seed Exported Successfully</h3>
            <button type="button" class="drug-modal-close" id="btnCloseExportModal">&times;</button>
        </div>
        <div class="drug-modal-body">
            <p class="drug-modal-desc">
                Deterministic SQL seed and companion manifest have been written atomically to disk and are ready for Git commit.
            </p>

            <div class="drug-export-summary">
                <div class="drug-export-row">
                    <span class="drug-export-label">Seed SQL File:</span>
                    <code class="drug-export-value" id="resExportSqlPath">-</code>
                </div>
                <div class="drug-export-row">
                    <span class="drug-export-label">Manifest File:</span>
                    <code class="drug-export-value" id="resExportManifestPath">-</code>
                </div>
                <div class="drug-export-row">
                    <span class="drug-export-label">SHA-256 Checksum:</span>
                    <code class="drug-export-value" id="resExportSha256">-</code>
                </div>
                <div class="drug-export-row">
                    <span class="drug-export-label">File Size:</span>
                    <span class="drug-export-value" id="resExportSize">-</span>
                </div>
            </div>

            <div class="drug-export-counts-wrap">
                <h4 class="drug-section-subtitle">Compiled Table Records</h4>
                <div id="resExportCountsContainer" class="drug-counts-grid"></div>
            </div>

            <div class="drug-export-rebuild-box">
                <h4 class="drug-section-subtitle">Rebuild Shipped Database Command</h4>
                <p class="drug-hint">Run this command to compile the shipped <code>zimrx_drugs.db</code> from seeds with FTS5 search index:</p>
                <div class="drug-code-box">
                    <code id="resRebuildCommand">php application/systemdata/seeds/build_db.php --force --country=BD</code>
                </div>
            </div>
        </div>
        <div class="drug-modal-footer">
            <button type="button" class="drug-btn drug-btn-primary" id="btnOkExportModal">Done</button>
        </div>
    </div>
</div>

<!-- 4. Quick Add Manufacturer Modal -->
<div class="drug-modal-overlay" id="modalQuickMfg" style="display:none;">
    <div class="drug-modal">
        <div class="drug-modal-header">
            <h3><?= zrx_icon('plus', 15) ?> Quick Add Manufacturer</h3>
            <button type="button" class="drug-modal-close" id="btnCloseQuickMfgModal">&times;</button>
        </div>
        <form id="formQuickMfg" class="drug-modal-body">
            <div class="drug-form-group">
                <label class="drug-label" for="inpQuickMfgName">Manufacturer Name *</label>
                <input type="text" id="inpQuickMfgName" class="drug-input" placeholder="e.g. Square Pharmaceuticals Ltd." required>
            </div>
            <div class="drug-form-group">
                <label class="drug-label" for="inpQuickMfgShort">Short / Display Name</label>
                <input type="text" id="inpQuickMfgShort" class="drug-input" placeholder="e.g. Square">
            </div>
            <div class="drug-modal-footer">
                <button type="button" class="drug-btn" id="btnCancelQuickMfg">Cancel</button>
                <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitQuickMfg">
                    <?= zrx_icon('check', 13) ?>
                    <span>Save &amp; Select</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Notifications Container -->
<div class="drug-toast-container" id="toastContainer"></div>
