<?php
declare(strict_types=1);
?>

<!-- 1. New Language Pack Modal -->
<div class="locale-modal-overlay" id="modalNewLanguage" style="display:none;">
    <div class="locale-modal">
        <div class="locale-modal-header">
            <h3><?= zrx_icon('plus', 15) ?> Create New Language Pack</h3>
            <button type="button" class="locale-modal-close" id="btnCloseNewLangModal">&times;</button>
        </div>
        <form id="formNewLanguage" class="locale-modal-body">
            <p class="locale-modal-desc">
                Scaffold a new medical language pack. All catalog files will be cloned as translation templates with identical keys and structures.
            </p>

            <div class="locale-form-grid">
                <div class="locale-form-group">
                    <label class="locale-label" for="inpNewLangCode">ISO Language Code *</label>
                    <input type="text" id="inpNewLangCode" class="locale-input" placeholder="e.g. es, fr, ar, hi, ur, de" required>
                    <span class="locale-hint">Standard 2 or 3 letter lowercase code (e.g. 'bn', 'es-mx')</span>
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpNewLangName">English Name *</label>
                    <input type="text" id="inpNewLangName" class="locale-input" placeholder="e.g. Spanish, French, Arabic" required>
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpNewLangNative">Native Name *</label>
                    <input type="text" id="inpNewLangNative" class="locale-input" placeholder="e.g. Español, Français, العربية" required>
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpNewLangDirection">Script Direction</label>
                    <select id="inpNewLangDirection" class="locale-select">
                        <option value="ltr">LTR (Left to Right)</option>
                        <option value="rtl">RTL (Right to Left - Arabic, Urdu, Hebrew, Persian)</option>
                    </select>
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpNewLangAuthor">Author / Clinician</label>
                    <input type="text" id="inpNewLangAuthor" class="locale-input" placeholder="e.g. Dr. Jane Doe">
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpNewLangCloneFrom">Clone Template From</label>
                    <select id="inpNewLangCloneFrom" class="locale-select">
                        <option value="en">English (en) - Master Reference</option>
                    </select>
                </div>
            </div>

            <div class="locale-modal-footer">
                <button type="button" class="locale-btn" id="btnCancelNewLang">Cancel</button>
                <button type="submit" class="locale-btn locale-btn-primary" id="btnSubmitNewLang">
                    <?= zrx_icon('check', 13) ?>
                    <span>Create Language Pack</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Dynamic Add Catalog JSON Modal -->
<div class="locale-modal-overlay" id="modalNewCatalog" style="display:none;">
    <div class="locale-modal">
        <div class="locale-modal-header">
            <h3><?= zrx_icon('file-text', 15) ?> Add Dynamic Catalog JSON</h3>
            <button type="button" class="locale-modal-close" id="btnCloseNewCatalogModal">&times;</button>
        </div>
        <form id="formNewCatalog" class="locale-modal-body">
            <p class="locale-modal-desc">
                Create a new clinical catalog file (e.g. <code>symptoms.json</code>, <code>specialties.json</code>, <code>followups.json</code>).
                This will automatically generate the file across all language packs and register it in their <code>manifest.json</code>.
            </p>

            <div class="locale-form-group">
                <label class="locale-label" for="inpNewCatalogName">Catalog Name *</label>
                <div class="locale-input-suffix-wrap">
                    <input type="text" id="inpNewCatalogName" class="locale-input" placeholder="e.g. symptoms, investigations, specialties" required>
                    <span class="locale-input-suffix">.json</span>
                </div>
                <span class="locale-hint">Letters, numbers, and underscores only. Lowercase.</span>
            </div>

            <div class="locale-modal-footer">
                <button type="button" class="locale-btn" id="btnCancelNewCatalog">Cancel</button>
                <button type="submit" class="locale-btn locale-btn-primary" id="btnSubmitNewCatalog">
                    <?= zrx_icon('plus', 13) ?>
                    <span>Create Catalog File</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Language Manifest Modal -->
<div class="locale-modal-overlay" id="modalManifest" style="display:none;">
    <div class="locale-modal">
        <div class="locale-modal-header">
            <h3><?= zrx_icon('edit', 15) ?> Edit Language Manifest</h3>
            <button type="button" class="locale-modal-close" id="btnCloseManifestModal">&times;</button>
        </div>
        <form id="formManifest" class="locale-modal-body">
            <div class="locale-form-grid">
                <div class="locale-form-group">
                    <label class="locale-label">Language Code</label>
                    <input type="text" id="inpManCode" class="locale-input locale-input-readonly" readonly>
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpManName">English Name</label>
                    <input type="text" id="inpManName" class="locale-input" required>
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpManNative">Native Name</label>
                    <input type="text" id="inpManNative" class="locale-input" required>
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpManDirection">Direction</label>
                    <select id="inpManDirection" class="locale-select">
                        <option value="ltr">LTR (Left to Right)</option>
                        <option value="rtl">RTL (Right to Left)</option>
                    </select>
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpManVersion">Version</label>
                    <input type="text" id="inpManVersion" class="locale-input" placeholder="1.0.0">
                </div>

                <div class="locale-form-group">
                    <label class="locale-label" for="inpManAuthor">Author / Lead</label>
                    <input type="text" id="inpManAuthor" class="locale-input">
                </div>

                <div class="locale-form-group full">
                    <label class="locale-label" for="inpManContributors">Contributors (comma separated)</label>
                    <input type="text" id="inpManContributors" class="locale-input" placeholder="Dr. Jane, Dr. John">
                </div>
            </div>

            <div class="locale-modal-footer">
                <button type="button" class="locale-btn" id="btnCancelManifest">Cancel</button>
                <button type="submit" class="locale-btn locale-btn-primary" id="btnSubmitManifest">
                    <?= zrx_icon('save', 13) ?>
                    <span>Save Manifest</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Locale Validation Report Modal -->
<div class="locale-modal-overlay" id="modalValidation" style="display:none;">
    <div class="locale-modal locale-modal-lg">
        <div class="locale-modal-header">
            <h3><?= zrx_icon('shield', 15) ?> Locales Health &amp; Integrity Report</h3>
            <button type="button" class="locale-modal-close" id="btnCloseValidationModal">&times;</button>
        </div>
        <div class="locale-modal-body">
            <div id="validationReportContent">
                <div class="locale-empty-state">Running validation checks...</div>
            </div>
        </div>
        <div class="locale-modal-footer">
            <button type="button" class="locale-btn locale-btn-primary" id="btnCloseValidationReport">Close</button>
        </div>
    </div>
</div>

<!-- 5. Clinician Verifier Modal Prompt -->
<div class="locale-modal-overlay" id="modalVerifierPrompt" style="display:none;">
    <div class="locale-modal locale-modal-sm">
        <div class="locale-modal-header">
            <h3><?= zrx_icon('shield', 15) ?> Clinical Verification</h3>
            <button type="button" class="locale-modal-close" id="btnCloseVerifierPrompt">&times;</button>
        </div>
        <form id="formVerifierPrompt" class="locale-modal-body">
            <p class="locale-modal-desc">
                Please enter the clinician or medical translator's name who verified this catalog:
            </p>
            <div class="locale-form-group">
                <label class="locale-label" for="inpVerifierName">Verifier Name *</label>
                <input type="text" id="inpVerifierName" class="locale-input" placeholder="e.g. Dr. Alif Farzan Zim" required autofocus>
            </div>
            <div class="locale-modal-footer">
                <button type="button" class="locale-btn" id="btnCancelVerifierPrompt">Cancel</button>
                <button type="submit" class="locale-btn locale-btn-primary">
                    <?= zrx_icon('check', 13) ?>
                    <span>Confirm Verification</span>
                </button>
            </div>
        </form>
    </div>
</div>
