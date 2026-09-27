<?php
/**
 * ZimRx - Local Examination Module
 * Systematic surgical & physical local examination of swellings, ulcers, wounds, and localized lesions.
 */
?>
<div class="pc-wrapper zrx-exam-wrapper" id="local-exam-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('target', 14) ?>
            <span>Local Examination</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-local" title="Pre-fill benign / unremarkable findings">Unremarkable</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-local" title="Clear all fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-mode Selector: General Lesion vs 9-Region Abdominal Map -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-le-mode="general">
                <?= zrx_icon('target', 13) ?> General Lesion Examination
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-le-mode="abdomen">
                <?= zrx_icon('grid', 13) ?> 9-Region Abdominal Diagram
            </button>
        </div>

        <!-- Mode 1: General Surgical Lesion View -->
        <div class="zrx-le-mode-pane active" id="zrx-le-pane-general">
            <!-- Lesion Type & Site Selection -->
            <div class="zrx-exam-top-row">
                <div class="zrx-exam-top-cell">
                    <label class="zrx-exam-lbl">Lesion / Pathology Type</label>
                    <select class="zrx-exam-select" data-le-field="lesion-type">
                        <option value="Swelling / Lump / Mass">Swelling / Lump / Mass</option>
                        <option value="Ulcer / Wound">Ulcer / Wound</option>
                        <option value="Hernia / Inguinal Swelling">Hernia / Inguinal Swelling</option>
                        <option value="Scrotal Swelling">Scrotal Swelling</option>
                        <option value="Neck / Thyroid Swelling">Neck / Thyroid Swelling</option>
                        <option value="Joint / Musculoskeletal">Joint / Musculoskeletal</option>
                        <option value="Sinus / Fistula">Sinus / Fistula</option>
                        <option value="Skin & Soft Tissue">Skin & Soft Tissue</option>
                        <option value="Other Localized Lesion">Other Localized Lesion</option>
                    </select>
                </div>
                <div class="zrx-exam-top-cell">
                    <label class="zrx-exam-lbl">Anatomical Region / Location</label>
                    <input type="text" class="zrx-exam-input" data-le-field="anatomical-site" placeholder="e.g. Right Inguinal, Forearm, Anterior Neck..." autocomplete="off">
                </div>
            </div>

            <!-- Two Column Surgical Examination (Inspection & Palpation) -->
            <div class="zrx-exam-dual-grid">
                <!-- Inspection Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">INSP</span>
                        <span>1. Inspection</span>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Size & Shape</label>
                        <div class="zrx-exam-flex-pair">
                            <input type="text" class="zrx-exam-input" placeholder="e.g. 3x2 cm" data-le-field="insp-size" autocomplete="off">
                            <select class="zrx-exam-select" data-le-field="insp-shape">
                                <option value="Oval">Oval</option>
                                <option value="Spherical">Spherical</option>
                                <option value="Irregular">Irregular</option>
                                <option value="Pear-shaped">Pear-shaped</option>
                                <option value="Lobulated">Lobulated</option>
                                <option value="Diffuse">Diffuse</option>
                            </select>
                        </div>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Surface & Overlying Skin</label>
                        <select class="zrx-exam-select" data-le-field="insp-skin">
                            <option value="Normal">Normal</option>
                            <option value="Stretched & Shiny">Stretched & Shiny</option>
                            <option value="Erythematous / Inflamed">Erythematous / Inflamed</option>
                            <option value="Punctum present">Punctum present</option>
                            <option value="Pigmented / Discolored">Pigmented / Discolored</option>
                            <option value="Ulcerated / Discharging">Ulcerated / Discharging</option>
                            <option value="Puckered / Tethered">Puckered / Tethered</option>
                        </select>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Edge / Margins</label>
                        <select class="zrx-exam-select" data-le-field="insp-edge">
                            <option value="Well-defined / Clear">Well-defined / Clear</option>
                            <option value="Ill-defined / Diffuse">Ill-defined / Diffuse</option>
                            <option value="Rolled / Everted">Rolled / Everted</option>
                            <option value="Punched-out">Punched-out</option>
                            <option value="Sloping">Sloping</option>
                            <option value="Undermined">Undermined</option>
                        </select>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Special Movement / Impulse</label>
                        <select class="zrx-exam-select" data-le-field="insp-impulse">
                            <option value="None / Negative">None / Negative</option>
                            <option value="Expansile cough impulse +ve">Expansile cough impulse +ve</option>
                            <option value="Moves with deglutition">Moves with deglutition</option>
                            <option value="Moves on tongue protrusion">Moves on tongue protrusion</option>
                            <option value="Visible pulsation present">Visible pulsation present</option>
                        </select>
                    </div>
                </div>

                <!-- Palpation Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">PALP</span>
                        <span>2. Palpation</span>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Temperature & Tenderness</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" data-le-field="palp-temp">
                                <option value="Normal temperature">Normal temp</option>
                                <option value="Local warmth / Raised">Local warmth</option>
                            </select>
                            <select class="zrx-exam-select" data-le-field="palp-tenderness">
                                <option value="Non-tender">Non-tender</option>
                                <option value="Mildly tender">Mildly tender</option>
                                <option value="Severely tender">Severely tender</option>
                            </select>
                        </div>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Consistency & Fluctuation</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" data-le-field="palp-consistency">
                                <option value="Soft">Soft</option>
                                <option value="Cystic">Cystic</option>
                                <option value="Fibroelastic / Rubbery">Fibroelastic / Rubbery</option>
                                <option value="Firm">Firm</option>
                                <option value="Hard / Bony">Hard / Bony</option>
                            </select>
                            <select class="zrx-exam-select" data-le-field="palp-fluctuation">
                                <option value="Non-fluctuant">Non-fluctuant</option>
                                <option value="Fluctuant (+ve)">Fluctuant (+ve)</option>
                            </select>
                        </div>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Transillumination & Reducibility</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" data-le-field="palp-transillumination">
                                <option value="Opaque / Negative">Opaque (-ve)</option>
                                <option value="Translucent (+ve)">Translucent (+ve)</option>
                                <option value="Brilliantly transilluminant">Brilliantly (+ve)</option>
                            </select>
                            <select class="zrx-exam-select" data-le-field="palp-reducibility">
                                <option value="Non-reducible">Non-reducible</option>
                                <option value="Reducible (+ve)">Reducible (+ve)</option>
                                <option value="Compressible">Compressible</option>
                            </select>
                        </div>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Plane & Anatomical Fixity</label>
                        <select class="zrx-exam-select" data-le-field="palp-fixity">
                            <option value="Subcutaneous (Freely mobile)">Subcutaneous (Freely mobile)</option>
                            <option value="Tethered to skin">Tethered to skin</option>
                            <option value="Intramuscular (Contracts with muscle)">Intramuscular</option>
                            <option value="Deep to deep fascia">Deep to deep fascia</option>
                            <option value="Fixed to underlying bone">Fixed to underlying bone</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Regional Nodes & Vascular Row -->
            <div class="zrx-exam-nodes-grid">
                <div class="zrx-exam-node-cell">
                    <label class="zrx-exam-lbl">Regional Lymph Nodes</label>
                    <select class="zrx-exam-select" data-le-field="regional-nodes">
                        <option value="Not enlarged / Not palpable">Not enlarged / Not palpable</option>
                        <option value="Enlarged, mobile, non-tender">Enlarged, mobile, non-tender</option>
                        <option value="Enlarged, tender (Lymphadenitis)">Enlarged, tender (Lymphadenitis)</option>
                        <option value="Matted / Fixed">Matted / Fixed</option>
                    </select>
                </div>
                <div class="zrx-exam-node-cell">
                    <label class="zrx-exam-lbl">Pulsation / Bruit</label>
                    <select class="zrx-exam-select" data-le-field="vascular-bruit">
                        <option value="Absent">Absent</option>
                        <option value="Transmitted pulsation">Transmitted pulsation</option>
                        <option value="Expansile pulsation">Expansile pulsation</option>
                        <option value="Systolic bruit present">Systolic bruit present</option>
                    </select>
                </div>
            </div>

            <!-- Quick Presets -->
            <div class="zrx-exam-chips-bar">
                <span class="zrx-exam-chips-label">Quick Presets:</span>
                <button type="button" class="zrx-exam-chip" data-preset="Subcutaneous soft, lobulated, painless swelling. Slipping sign positive. Freely mobile over underlying tissues. Clinically consistent with Lipoma.">Lipoma</button>
                <button type="button" class="zrx-exam-chip" data-preset="Spherical, firm, smooth swelling with visible central punctum on overlying skin. Tethered to epidermis. Non-tender. Clinically Sebaceous (Epidermoid) Cyst.">Sebaceous Cyst</button>
                <button type="button" class="zrx-exam-chip" data-preset="Smooth, cystic, tense swelling attached to underlying tendon sheath/joint capsule. Transillumination positive. Clinically Ganglion Cyst.">Ganglion</button>
                <button type="button" class="zrx-exam-chip" data-preset="Localized erythematous, warm, severely tender and fluctuant swelling. Draining lymph nodes mildly tender. Clinically Acute Abscess.">Acute Abscess</button>
                <button type="button" class="zrx-exam-chip" data-preset="Inguinal swelling, reducible with palpable expansile impulse on coughing. Deep ring occlusion test positive. Clinically Indirect Inguinal Hernia.">Inguinal Hernia</button>
                <button type="button" class="zrx-exam-chip" data-preset="Scrotal swelling, cystic, fluctuant, brilliantly transilluminant. Possible to get above the swelling. Testis not distinctly palpable separately. Clinically Primary Hydrocele.">Hydrocele</button>
                <button type="button" class="zrx-exam-chip" data-preset="Anterior neck mass moving upwards with deglutition. Soft to firm consistency, non-tender, no audible bruit. Clinically Goitre / Thyroid Nodule.">Thyroid Nodule</button>
            </div>
        </div>

        <!-- Mode 2: Interactive 9-Region Abdominal Map -->
        <div class="zrx-le-mode-pane" id="zrx-le-pane-abdomen" hidden>
            <div class="zrx-abdo-layout">
                <!-- Left: SVG Anatomical 9-Region Grid -->
                <div class="zrx-abdo-diagram-card">
                    <div class="zrx-abdo-diagram-head">
                        <span class="zrx-abdo-title">Anterior Abdominal Wall (9 Regions)</span>
                        <span class="zrx-abdo-sub">Click region to mark findings</span>
                    </div>
                    <div class="zrx-abdo-svg-wrap">
                        <svg class="zrx-abdo-svg" id="zrx-abdo-svg" viewBox="0 0 300 300">
                            <!-- Body Silhouette Outline -->
                            <path class="zrx-abdo-outline" d="M 60,10 C 65,70 45,150 45,210 C 45,270 70,290 150,290 C 230,290 255,270 255,210 C 255,150 235,70 240,10 Z" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2" />
                            <path d="M 60,10 C 100,30 200,30 240,10" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="3 3"/>
                            
                            <!-- 9 Regions as Interactive Cells -->
                            <!-- Row 1: Right Hypochondrium (RH), Epigastrium (EPI), Left Hypochondrium (LH) -->
                            <rect class="zrx-abdo-region" data-region="RH" data-region-name="Right Hypochondrium (RH)" x="50" y="30" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="83" y="75">RH</text>

                            <rect class="zrx-abdo-region" data-region="EPI" data-region-name="Epigastrium (Epi)" x="117" y="30" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="150" y="75">EPI</text>

                            <rect class="zrx-abdo-region" data-region="LH" data-region-name="Left Hypochondrium (LH)" x="184" y="30" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="217" y="75">LH</text>

                            <!-- Row 2: Right Lumbar (RL), Umbilical (UMB), Left Lumbar (LL) -->
                            <rect class="zrx-abdo-region" data-region="RL" data-region-name="Right Lumbar / Flank (RL)" x="50" y="111" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="83" y="156">RL</text>

                            <rect class="zrx-abdo-region" data-region="UMB" data-region-name="Umbilical Region (Umb)" x="117" y="111" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="150" y="156">UMB</text>

                            <rect class="zrx-abdo-region" data-region="LL" data-region-name="Left Lumbar / Flank (LL)" x="184" y="111" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="217" y="156">LL</text>

                            <!-- Row 3: Right Iliac Fossa (RIF), Hypogastrium (HYPO), Left Iliac Fossa (LIF) -->
                            <rect class="zrx-abdo-region" data-region="RIF" data-region-name="Right Iliac Fossa (RIF)" x="50" y="192" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="83" y="237">RIF</text>

                            <rect class="zrx-abdo-region" data-region="HYPO" data-region-name="Hypogastrium / Suprapubic (Hypo)" x="117" y="192" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="150" y="237">HYPO</text>

                            <rect class="zrx-abdo-region" data-region="LIF" data-region-name="Left Iliac Fossa (LIF)" x="184" y="192" width="66" height="80" rx="4" />
                            <text class="zrx-abdo-text" x="217" y="237">LIF</text>

                            <!-- Anatomical Reference lines & Umbilicus -->
                            <circle cx="150" cy="151" r="4.5" fill="#94a3b8" />
                            <circle cx="150" cy="151" r="2" fill="#475569" />
                        </svg>
                    </div>
                </div>

                <!-- Right: Active Region Clinical Inspector & Overall Abdomen Checklist -->
                <div class="zrx-abdo-controls-card">
                    <div class="zrx-abdo-active-head">
                        <span class="zrx-abdo-badge" id="zrx-abdo-active-badge">Selected: RIF</span>
                        <span class="zrx-abdo-selected-name" id="zrx-abdo-active-name">Right Iliac Fossa (RIF)</span>
                    </div>

                    <!-- Region specific signs buttons -->
                    <div class="zrx-abdo-sign-grid">
                        <label class="zrx-abdo-check-pill">
                            <input type="checkbox" data-abdo-sign="tenderness" id="zrx-abdo-sign-tender">
                            <span>Tenderness (T+)</span>
                        </label>
                        <label class="zrx-abdo-check-pill">
                            <input type="checkbox" data-abdo-sign="rebound" id="zrx-abdo-sign-rebound">
                            <span>Rebound (RT+)</span>
                        </label>
                        <label class="zrx-abdo-check-pill">
                            <input type="checkbox" data-abdo-sign="guarding" id="zrx-abdo-sign-guarding">
                            <span>Guarding / Rigidity</span>
                        </label>
                        <label class="zrx-abdo-check-pill">
                            <input type="checkbox" data-abdo-sign="mass" id="zrx-abdo-sign-mass">
                            <span>Palpable Mass</span>
                        </label>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Region Specific Description</label>
                        <input type="text" class="zrx-exam-input" id="zrx-abdo-region-note" placeholder="e.g. McBurney's point tenderness, Rovsing sign positive..." autocomplete="off">
                    </div>

                    <hr class="zrx-abdo-divider">

                    <!-- Global Abdomen Parameters -->
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Global Abdominal State</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" id="zrx-abdo-distension">
                                <option value="Soft, non-distended">Soft, non-distended</option>
                                <option value="Distended / Tympanitic">Distended / Tympanitic</option>
                                <option value="Scaphoid / Flat">Scaphoid / Flat</option>
                                <option value="Generalized peritonitis / Board-like rigid">Board-like rigidity</option>
                            </select>
                            <select class="zrx-exam-select" id="zrx-abdo-bowel-sounds">
                                <option value="Bowel sounds normal / present">Bowel sounds present</option>
                                <option value="Bowel sounds hyperactive">Hyperactive (Borborygmi)</option>
                                <option value="Bowel sounds sluggish / reduced">Sluggish / Reduced</option>
                                <option value="Absent / Silent abdomen (Ileus)">Silent abdomen (Ileus)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Quick Organomegaly shortcuts -->
                    <div class="zrx-exam-chips-bar">
                        <span class="zrx-exam-chips-label">Quick Findings:</span>
                        <button type="button" class="zrx-exam-chip" data-abdo-preset="rif-appendicitis">Acute Appendicitis (RIF)</button>
                        <button type="button" class="zrx-exam-chip" data-abdo-preset="rh-cholecystitis">Acute Cholecystitis (Murphy +ve)</button>
                        <button type="button" class="zrx-exam-chip" data-abdo-preset="epi-gastritis">Epigastric Tenderness (Peptic)</button>
                        <button type="button" class="zrx-exam-chip" data-abdo-preset="normal-soft">Soft & Non-tender (Normal)</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="local-exam-findings" class="zrx-exam-lbl">Findings & Clinical Impression</label>
            <textarea id="local-exam-findings" class="zrx-exam-textarea" rows="2" placeholder="Local examination findings, dimensions, and clinical notes..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
