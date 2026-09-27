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

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="local-exam-findings" class="zrx-exam-lbl">Findings & Clinical Impression</label>
            <textarea id="local-exam-findings" class="zrx-exam-textarea" rows="2" placeholder="Local examination findings, dimensions, and clinical notes..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
