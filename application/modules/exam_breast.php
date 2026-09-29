<?php
declare(strict_types=1);

// Breast examination module: interactive dual clock diagram, lesion mapping, and E1-E5 staging.
?>
<div class="pc-wrapper zrx-exam-wrapper" id="breast-exam-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('shield', 14) ?>
            <span>Breast Examination (CBE)</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-breast" title="Pre-fill normal bilateral findings">Normal Both</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-breast" title="Clear all findings & markers">Clear All</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Interactive Dual-Breast Clock Diagram Section -->
        <div class="zrx-breast-diagram-section">
            <div class="zrx-breast-diagram-header">
                <span class="zrx-diagram-title">Interactive Clock Diagram (Click to Mark Lesion)</span>
                <span class="zrx-diagram-subtitle">Click anywhere to place lesion marker with exact O'Clock and FN (From Nipple) distance</span>
            </div>
            
            <div class="zrx-breast-diagrams-grid">
                <!-- Right Breast Diagram (Patient's Right) -->
                <div class="zrx-breast-diagram-card" data-diagram-side="RT">
                    <div class="zrx-diagram-card-top">
                        <span class="zrx-exam-col-badge">RT</span>
                        <span class="zrx-diagram-name">Right Breast</span>
                        <button type="button" class="zrx-diagram-btn-clear" data-action="clear-diagram-rt" title="Clear Right Breast Markers">Clear RT</button>
                    </div>
                    <div class="zrx-svg-canvas-wrap">
                        <svg class="zrx-breast-svg" data-side="RT" viewBox="-105 -105 210 210" width="185" height="185">
                            <defs>
                                <radialGradient id="zrx-breast-grad-rt" cx="0" cy="0" r="1">
                                    <stop offset="0%" stop-color="#fff1f2" stop-opacity="0.8"/>
                                    <stop offset="60%" stop-color="#ffffff" stop-opacity="0.95"/>
                                    <stop offset="100%" stop-color="#f8fafc" stop-opacity="1"/>
                                </radialGradient>
                            </defs>
                            <!-- Outer Breast Circle -->
                            <circle class="zrx-svg-breast-outer" cx="0" cy="0" r="82" fill="url(#zrx-breast-grad-rt)" stroke="#94a3b8" stroke-width="1.6"/>
                            <!-- Axillary Tail of Spence (Top-Left for RT Breast) -->
                            <path class="zrx-svg-axillary-tail" d="M -58 -58 L -94 -90 L -46 -74 Z" fill="#e2e8f0" stroke="#94a3b8" stroke-width="1.2"/>
                            <text x="-88" y="-93" class="zrx-svg-label-tail">Axilla (RT)</text>
                            
                            <!-- Distance Rings: Mid zone (~3.5cm FN) and Areola (~1.5cm FN) -->
                            <circle class="zrx-svg-ring" cx="0" cy="0" r="50" fill="none" stroke="#cbd5e1" stroke-width="1.2" stroke-dasharray="3,3"/>
                            <circle class="zrx-svg-areola" cx="0" cy="0" r="22" fill="#fed7aa" fill-opacity="0.4" stroke="#f97316" stroke-width="1.2" stroke-dasharray="2,2"/>
                            <!-- Nipple Dot -->
                            <circle class="zrx-svg-nipple" cx="0" cy="0" r="3.5" fill="#ea580c"/>
                            
                            <!-- Clock Axes -->
                            <line x1="0" y1="-82" x2="0" y2="82" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="2,2"/>
                            <line x1="-82" y1="0" x2="82" y2="0" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="2,2"/>
                            
                            <!-- Clock Hour Labels -->
                            <text x="0" y="-86" text-anchor="middle" class="zrx-svg-hour">12</text>
                            <text x="91" y="3" text-anchor="middle" class="zrx-svg-hour">3</text>
                            <text x="0" y="95" text-anchor="middle" class="zrx-svg-hour">6</text>
                            <text x="-91" y="3" text-anchor="middle" class="zrx-svg-hour">9</text>
                            
                            <!-- Placed Markers Group -->
                            <g class="zrx-svg-markers-group" id="zrx-markers-rt"></g>
                        </svg>
                    </div>
                </div>

                <!-- Left Breast Diagram (Patient's Left) -->
                <div class="zrx-breast-diagram-card" data-diagram-side="LT">
                    <div class="zrx-diagram-card-top">
                        <span class="zrx-exam-col-badge">LT</span>
                        <span class="zrx-diagram-name">Left Breast</span>
                        <button type="button" class="zrx-diagram-btn-clear" data-action="clear-diagram-lt" title="Clear Left Breast Markers">Clear LT</button>
                    </div>
                    <div class="zrx-svg-canvas-wrap">
                        <svg class="zrx-breast-svg" data-side="LT" viewBox="-105 -105 210 210" width="185" height="185">
                            <defs>
                                <radialGradient id="zrx-breast-grad-lt" cx="0" cy="0" r="1">
                                    <stop offset="0%" stop-color="#fff1f2" stop-opacity="0.8"/>
                                    <stop offset="60%" stop-color="#ffffff" stop-opacity="0.95"/>
                                    <stop offset="100%" stop-color="#f8fafc" stop-opacity="1"/>
                                </radialGradient>
                            </defs>
                            <!-- Outer Breast Circle -->
                            <circle class="zrx-svg-breast-outer" cx="0" cy="0" r="82" fill="url(#zrx-breast-grad-lt)" stroke="#94a3b8" stroke-width="1.6"/>
                            <!-- Axillary Tail of Spence (Top-Right for LT Breast) -->
                            <path class="zrx-svg-axillary-tail" d="M 58 -58 L 94 -90 L 46 -74 Z" fill="#e2e8f0" stroke="#94a3b8" stroke-width="1.2"/>
                            <text x="88" y="-93" class="zrx-svg-label-tail">Axilla (LT)</text>
                            
                            <!-- Distance Rings: Mid zone and Areola -->
                            <circle class="zrx-svg-ring" cx="0" cy="0" r="50" fill="none" stroke="#cbd5e1" stroke-width="1.2" stroke-dasharray="3,3"/>
                            <circle class="zrx-svg-areola" cx="0" cy="0" r="22" fill="#fed7aa" fill-opacity="0.4" stroke="#f97316" stroke-width="1.2" stroke-dasharray="2,2"/>
                            <!-- Nipple Dot -->
                            <circle class="zrx-svg-nipple" cx="0" cy="0" r="3.5" fill="#ea580c"/>
                            
                            <!-- Clock Axes -->
                            <line x1="0" y1="-82" x2="0" y2="82" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="2,2"/>
                            <line x1="-82" y1="0" x2="82" y2="0" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="2,2"/>
                            
                            <!-- Clock Hour Labels -->
                            <text x="0" y="-86" text-anchor="middle" class="zrx-svg-hour">12</text>
                            <text x="91" y="3" text-anchor="middle" class="zrx-svg-hour">3</text>
                            <text x="0" y="95" text-anchor="middle" class="zrx-svg-hour">6</text>
                            <text x="-91" y="3" text-anchor="middle" class="zrx-svg-hour">9</text>
                            
                            <!-- Placed Markers Group -->
                            <g class="zrx-svg-markers-group" id="zrx-markers-lt"></g>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Dynamic Marked Lesions Accordion / Detail Cards -->
            <div class="zrx-diagram-lesions-wrap" id="zrx-breast-lesions-list">
                <div class="zrx-diagram-empty-hint" id="zrx-lesions-empty-hint">
                    No lesion markers placed. Click anywhere on the clock faces above to map lump coordinates.
                </div>
            </div>
        </div>

        <!-- Clinical Staging Category Bar (International E1 - E5) -->
        <div class="zrx-exam-e-score-bar">
            <span class="zrx-exam-e-score-label">Clinical Category:</span>
            <div class="zrx-exam-e-score-group">
                <button type="button" class="zrx-e-btn" data-escore="E1" title="E1: Normal / Unremarkable">E1 Normal</button>
                <button type="button" class="zrx-e-btn" data-escore="E2" title="E2: Definitely Benign (e.g. Fibroadenoma, simple cyst)">E2 Benign</button>
                <button type="button" class="zrx-e-btn" data-escore="E3" title="E3: Indeterminate / Probably Benign">E3 Indeterminate</button>
                <button type="button" class="zrx-e-btn" data-escore="E4" title="E4: Suspicious of Malignancy">E4 Suspicious</button>
                <button type="button" class="zrx-e-btn" data-escore="E5" title="E5: Highly Suggestive of Malignancy">E5 Malignant</button>
            </div>
        </div>

        <!-- Bilateral Symmetry & Nipple Inspection Columns -->
        <div class="zrx-exam-dual-grid">
            <!-- Right Breast Column -->
            <div class="zrx-exam-col" data-side="right">
                <div class="zrx-exam-col-header">
                    <span class="zrx-exam-col-badge">RT</span>
                    <span>Right Breast (General &amp; Skin)</span>
                </div>
                
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Symmetry &amp; Size</label>
                    <select class="zrx-exam-select" data-be-field="rt-symmetry">
                        <option value="Normal / Symmetrical">Normal / Symmetrical</option>
                        <option value="Enlarged / Engorged">Enlarged / Engorged</option>
                        <option value="Asymmetric / Smaller">Asymmetric / Smaller</option>
                        <option value="Ptosis">Ptosis</option>
                        <option value="Scar / Lumpectomy defect">Scar / Lumpectomy defect</option>
                    </select>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Skin Changes</label>
                    <select class="zrx-exam-select" data-be-field="rt-skin">
                        <option value="Normal">Normal (No dimpling/erythema)</option>
                        <option value="Dimpling / Tethering">Dimpling / Tethering</option>
                        <option value="Peau d'orange">Peau d'orange</option>
                        <option value="Erythema / Warm">Erythema / Warm</option>
                        <option value="Ulceration">Ulceration</option>
                    </select>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Nipple-Areola &amp; Discharge</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-be-field="rt-nipple">
                            <option value="Normal">Nipple: Normal</option>
                            <option value="Retraction / Inverted">Retraction</option>
                            <option value="Deviation">Deviation</option>
                            <option value="Crusting / Eczematous">Pagetoid</option>
                        </select>
                        <select class="zrx-exam-select" data-be-field="rt-discharge">
                            <option value="None">No discharge</option>
                            <option value="Serous / Clear">Serous</option>
                            <option value="Bloody / Sanguineous">Bloody</option>
                            <option value="Milky">Milky</option>
                            <option value="Purulent">Purulent</option>
                            <option value="Greenish / Duct ectasia">Duct ectasia</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Left Breast Column -->
            <div class="zrx-exam-col" data-side="left">
                <div class="zrx-exam-col-header">
                    <span class="zrx-exam-col-badge">LT</span>
                    <span>Left Breast (General &amp; Skin)</span>
                </div>
                
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Symmetry &amp; Size</label>
                    <select class="zrx-exam-select" data-be-field="lt-symmetry">
                        <option value="Normal / Symmetrical">Normal / Symmetrical</option>
                        <option value="Enlarged / Engorged">Enlarged / Engorged</option>
                        <option value="Asymmetric / Smaller">Asymmetric / Smaller</option>
                        <option value="Ptosis">Ptosis</option>
                        <option value="Scar / Lumpectomy defect">Scar / Lumpectomy defect</option>
                    </select>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Skin Changes</label>
                    <select class="zrx-exam-select" data-be-field="lt-skin">
                        <option value="Normal">Normal (No dimpling/erythema)</option>
                        <option value="Dimpling / Tethering">Dimpling / Tethering</option>
                        <option value="Peau d'orange">Peau d'orange</option>
                        <option value="Erythema / Warm">Erythema / Warm</option>
                        <option value="Ulceration">Ulceration</option>
                    </select>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Nipple-Areola &amp; Discharge</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-be-field="lt-nipple">
                            <option value="Normal">Nipple: Normal</option>
                            <option value="Retraction / Inverted">Retraction</option>
                            <option value="Deviation">Deviation</option>
                            <option value="Crusting / Eczematous">Pagetoid</option>
                        </select>
                        <select class="zrx-exam-select" data-be-field="lt-discharge">
                            <option value="None">No discharge</option>
                            <option value="Serous / Clear">Serous</option>
                            <option value="Bloody / Sanguineous">Bloody</option>
                            <option value="Milky">Milky</option>
                            <option value="Purulent">Purulent</option>
                            <option value="Greenish / Duct ectasia">Duct ectasia</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regional Lymph Nodes Row -->
        <div class="zrx-exam-nodes-grid">
            <div class="zrx-exam-node-cell">
                <label class="zrx-exam-lbl">Right Axillary Nodes</label>
                <select class="zrx-exam-select" data-be-field="rt-nodes">
                    <option value="Not palpable">Not palpable</option>
                    <option value="Palpable mobile">Palpable mobile</option>
                    <option value="Matted / Fixed">Matted / Fixed</option>
                    <option value="Tender">Tender</option>
                </select>
            </div>
            <div class="zrx-exam-node-cell">
                <label class="zrx-exam-lbl">Left Axillary Nodes</label>
                <select class="zrx-exam-select" data-be-field="lt-nodes">
                    <option value="Not palpable">Not palpable</option>
                    <option value="Palpable mobile">Palpable mobile</option>
                    <option value="Matted / Fixed">Matted / Fixed</option>
                    <option value="Tender">Tender</option>
                </select>
            </div>
            <div class="zrx-exam-node-cell">
                <label class="zrx-exam-lbl">Supraclavicular Nodes</label>
                <select class="zrx-exam-select" data-be-field="supraclav-nodes">
                    <option value="Not palpable">Not palpable</option>
                    <option value="Palpable Right">Palpable Right</option>
                    <option value="Palpable Left">Palpable Left</option>
                    <option value="Palpable Bilateral">Palpable Bilateral</option>
                </select>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Presets:</span>
            <button type="button" class="zrx-exam-chip" data-preset="Normal Bilateral Breast Examination: Symmetrical, no skin dimpling, everted nipples without discharge. No palpable breast mass. Axillary lymph nodes not palpable. Category: E1 (Normal).">Normal Both (E1)</button>
            <button type="button" class="zrx-exam-chip" data-preset="Right breast solitary palpable lump, firm, smooth margins, freely mobile ('breast mouse'), non-tender. Axillary lymph nodes not palpable. Category: E2 (Benign, likely Fibroadenoma).">Fibroadenoma (RT, E2)</button>
            <button type="button" class="zrx-exam-chip" data-preset="Left breast solitary palpable lump, firm, smooth margins, freely mobile, non-tender. Axillary lymph nodes not palpable. Category: E2 (Benign, likely Fibroadenoma).">Fibroadenoma (LT, E2)</button>
            <button type="button" class="zrx-exam-chip" data-preset="Bilateral lumpy/nodular breast tissue, predominantly in upper outer quadrants. Cyclical tenderness. No dominant discrete hard mass. Category: E2 (Fibrocystic Changes).">Fibrocystic Changes (E2)</button>
            <button type="button" class="zrx-exam-chip" data-preset="Localized erythema, warmth, severe tenderness, and induration. Clinically suggestive of Acute Mastitis / Breast Abscess. Advised USG of Breast.">Mastitis / Abscess</button>
            <button type="button" class="zrx-exam-chip" data-preset="Hard, irregular, ill-defined palpable breast mass with restricted mobility. Regional lymph node involvement suspected. Category: E4/E5 (Triple Assessment required).">Suspicious Mass (E4/E5)</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="breast-exam-findings" class="zrx-exam-lbl">Findings &amp; Clinical Impression</label>
            <textarea id="breast-exam-findings" class="zrx-exam-textarea" rows="3" placeholder="Clinical summary notes or auto-generated findings from clock markers..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
