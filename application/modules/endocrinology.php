<?php
/**
 * ZimRx - Endocrinology & Metabolic Assessment Module
 * Glycemic control (HbA1c target, SMBG pattern, hypoglycemia Gold score),
 * Thyroid examination (Goitre WHO grade 0-2, bruit, eye signs, reflex relaxation),
 * Adrenal/Cushingoid features, and Metabolic syndrome / Insulin resistance stigmata.
 */
?>
<div class="pc-wrapper zrx-exam-wrapper" id="endo-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('sun', 14) ?>
            <span>Endocrinology &amp; Metabolic Assessment</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-endo" title="Pre-fill normal endocrine findings">Normal Endo</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-endo" title="Clear all endocrinology fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-mode Selector for Endocrinology Components -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-endo-tab="diabetes">
                <?= zrx_icon('droplet', 13) ?> 1. Diabetes &amp; Glycemia
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-endo-tab="thyroid">
                <?= zrx_icon('eye', 13) ?> 2. Thyroid &amp; Orbitopathy
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-endo-tab="adrenal">
                <?= zrx_icon('shield', 13) ?> 3. Adrenal &amp; Metabolic Stigmata
            </button>
        </div>

        <!-- Section 1: Diabetes & Glycemia -->
        <div class="zrx-endo-pane active" id="zrx-endo-pane-diabetes">
            <div class="zrx-exam-dual-grid">
                <!-- Classification & HbA1c Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">GLYCEMIA</span>
                        <span>Diabetes Classification &amp; HbA1c</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Diabetes Type &amp; Staging</label>
                        <select class="zrx-exam-select" data-endo-field="dm-type">
                            <option value="Non-diabetic / Normoglycemic">Non-diabetic / Normoglycemic</option>
                            <option value="Type 2 Diabetes Mellitus (T2DM)">Type 2 Diabetes (T2DM)</option>
                            <option value="Type 1 Diabetes Mellitus (T1DM)">Type 1 Diabetes (T1DM)</option>
                            <option value="Gestational Diabetes Mellitus (GDM)">Gestational Diabetes (GDM)</option>
                            <option value="Prediabetes (Impaired Fasting Glucose / IGT)">Prediabetes (IFG / IGT)</option>
                            <option value="Secondary / Steroid-induced Diabetes">Steroid-induced Diabetes</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Glycemic Control (HbA1c Target)</label>
                        <select class="zrx-exam-select" data-endo-field="hba1c-status">
                            <option value="Optimal glycemic control (HbA1c <7.0% / In target)">Optimal: HbA1c &lt;7.0% (In target)</option>
                            <option value="Fair glycemic control (HbA1c 7.0–7.9%)">Fair: HbA1c 7.0–7.9%</option>
                            <option value="Suboptimal glycemic control (HbA1c 8.0–9.9%)">Suboptimal: HbA1c 8.0–9.9%</option>
                            <option value="Poor glycemic control (HbA1c ≥10.0% / High complication risk)">Poor: HbA1c ≥10.0% (High risk)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Self-Monitoring (SMBG Profile)</label>
                        <select class="zrx-exam-select" data-endo-field="smbg-profile">
                            <option value="SMBG readings consistently in target (FBG 4.4-7.0, PPBG <10.0 mmol/L)">In target (FBG 4.4–7.0, PPBG &lt;10.0)</option>
                            <option value="Fasting morning hyperglycemia (Dawn phenomenon)">Fasting morning hyperglycemia</option>
                            <option value="Post-prandial glycemic excursions (>11.1 mmol/L)">Post-prandial excursions (>11.1)</option>
                            <option value="Erratic glycemic variability with frequent spikes">Erratic glycemic variability</option>
                        </select>
                    </div>
                </div>

                <!-- Hypoglycemia & Regimen Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">SAFETY</span>
                        <span>Hypoglycemia &amp; Regimen</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Hypoglycemia Risk &amp; Awareness</label>
                        <select class="zrx-exam-select" data-endo-field="hypo-awareness">
                            <option value="No hypoglycemia, intact autonomic awareness (Gold Score 1)">No hypos, intact awareness (Normal)</option>
                            <option value="Occasional mild symptomatic hypos (Self-treated)">Occasional mild hypos (Self-treated)</option>
                            <option value="Frequent nocturnal hypoglycemia">Frequent nocturnal hypoglycemia</option>
                            <option value="Impaired hypoglycemia awareness (Gold Score ≥4)">Impaired awareness (Gold Score ≥4)</option>
                            <option value="Severe hypoglycemia requiring third-party assistance / Glucagon">Severe hypo (Assistance required)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Antidiabetic Treatment Modality</label>
                        <select class="zrx-exam-select" data-endo-field="dm-regimen">
                            <option value="Diet and lifestyle modification alone">Lifestyle &amp; Diet alone</option>
                            <option value="Oral Antidiabetic Agents (OAD) monotherapy (Metformin)">OAD Monotherapy (Metformin)</option>
                            <option value="Dual OAD therapy (Metformin + SGLT2i / DPP4i / SU)">Dual OAD Therapy</option>
                            <option value="Triple OAD combination therapy">Triple OAD Therapy</option>
                            <option value="Basal insulin injection once daily + OADs">Basal Insulin + OADs</option>
                            <option value="Premix insulin twice daily (BID)">Premix Insulin BID</option>
                            <option value="Basal-bolus MDI multiple daily insulin injections">Basal-Bolus MDI Injections</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Thyroid & Orbitopathy -->
        <div class="zrx-endo-pane" id="zrx-endo-pane-thyroid" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Goitre Palpation Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">THYROID GLAND</span>
                        <span>Goitre Grading &amp; Character</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Goitre WHO Grade</label>
                        <select class="zrx-exam-select" data-endo-field="goitre-grade">
                            <option value="Grade 0 (No palpable or visible thyroid enlargement)">Grade 0: No goitre (Normal)</option>
                            <option value="Grade 1 (Palpable goitre moving with swallowing, not visible in normal neck)">Grade 1: Palpable, not visible</option>
                            <option value="Grade 2 (Clearly visible goitre with neck in neutral position)">Grade 2: Clearly visible goitre</option>
                            <option value="Retrosternal goitre suspected (Lower border not palpable)">Retrosternal goitre suspected</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Thyroid Consistency &amp; Bruit</label>
                        <select class="zrx-exam-select" data-endo-field="thyroid-consistency">
                            <option value="Non-enlarged, normal soft consistency, non-tender, no bruit">Normal soft, non-tender, no bruit</option>
                            <option value="Smooth, diffuse enlargement with audible vascular bruit (Graves' disease)">Smooth diffuse + Bruit (Graves)</option>
                            <option value="Multinodular goitre (MNG - Multiple firm irregular nodules)">Multinodular (MNG)</option>
                            <option value="Solitary firm/hard thyroid nodule (Requires FNAC evaluation)">Solitary firm nodule (Needs FNAC)</option>
                            <option value="Exquisitely tender diffusely enlarged thyroid (Subacute thyroiditis)">Exquisitely tender (Subacute)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Pemberton's Sign (Thoracic Inlet)</label>
                        <select class="zrx-exam-select" data-endo-field="pemberton-sign">
                            <option value="Pemberton sign negative (No facial congestion on arm raising)">Pemberton sign negative (Normal)</option>
                            <option value="Pemberton sign positive (Facial plethora, cyanosis & stridor on arm elevation)">Pemberton sign positive (Thoracic inlet compression)</option>
                        </select>
                    </div>
                </div>

                <!-- Orbitopathy & Peripheral Thyroid Signs Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">SIGNS</span>
                        <span>Eye Signs &amp; Peripheral Markers</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Thyroid Eye Signs (Orbitopathy)</label>
                        <select class="zrx-exam-select" data-endo-field="thyroid-eyes">
                            <option value="No exophthalmos, lid lag or retraction (Eyes normal)">No orbitopathy signs (Normal)</option>
                            <option value="Bilateral proptosis / Exophthalmos present">Exophthalmos / Proptosis present</option>
                            <option value="Lid retraction (Dalrymple's sign) & Lid lag on down-gaze (von Graefe's sign)">Lid retraction &amp; Lid lag (von Graefe +ve)</option>
                            <option value="Conjunctival chemosis, periorbital edema & redness (Active TAO)">Chemosis &amp; Periorbital edema</option>
                            <option value="Diplopia on upward / lateral gaze (Extraocular muscle restriction)">Diplopia / Muscle restriction</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Peripheral Signs of Thyroid Status</label>
                        <select class="zrx-exam-select" data-endo-field="thyroid-peripheral">
                            <option value="Clinically euthyroid (No tremors, normal skin texture and reflexes)">Clinically euthyroid (Normal)</option>
                            <option value="Hyperthyroid: Fine resting finger tremor, warm moist palms, tachycardia">Hyperthyroid: Tremor, warm palms, tachy</option>
                            <option value="Hyperthyroid: Pretibial myxedema & thyroid acropachy present">Pretibial myxedema &amp; Acropachy</option>
                            <option value="Hypothyroid: Dry coarse skin, periorbital puffiness, slow-relaxing ankle reflex (Hung-up jerk)">Hypothyroid: Dry skin, slow ankle jerk</option>
                            <option value="Hypothyroid: Hoarse voice & sluggish mentation">Hoarse voice &amp; Sluggish mentation</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Adrenal, Pituitary & Metabolic Stigmata -->
        <div class="zrx-endo-pane" id="zrx-endo-pane-adrenal" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Cushingoid & Adrenal Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">ADRENAL</span>
                        <span>Cushingoid &amp; Addisonian Features</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Cushingoid Physical Features</label>
                        <select class="zrx-exam-select" data-endo-field="cushing-signs">
                            <option value="No Cushingoid features, normal fat distribution">No Cushingoid features (Normal)</option>
                            <option value="Moon facies, plethoric cheeks, dorsocervical fat pad (Buffalo hump)">Moon face &amp; Buffalo hump</option>
                            <option value="Wide violaceous abdominal striae (>1 cm width) with easy bruising">Wide purple striae &amp; Bruising</option>
                            <option value="Proximal muscle myopathy (Unable to rise from squatting position)">Proximal myopathy (Gowers/Squat test +ve)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Adrenal Insufficiency &amp; Pigmentation</label>
                        <select class="zrx-exam-select" data-endo-field="adrenal-insuff">
                            <option value="Normal cutaneous pigmentation, hemodynamically stable">Normal pigmentation (Normal)</option>
                            <option value="Mucocutaneous hyperpigmentation of palmar creases, buccal mucosa & scars (Addison's)">Hyperpigmentation (Palms/Buccal)</option>
                            <option value="Postural orthostatic hypotension (>20 mmHg drop in SBP on standing)">Postural hypotension (>20 mmHg drop)</option>
                        </select>
                    </div>
                </div>

                <!-- Metabolic Syndrome & PCOS Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">METABOLIC</span>
                        <span>Insulin Resistance &amp; PCOS</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Cutaneous Insulin Resistance Markers</label>
                        <select class="zrx-exam-select" data-endo-field="insulin-resistance">
                            <option value="Clear skin, no acanthosis nigricans or skin tags">No insulin resistance markers (Normal)</option>
                            <option value="Acanthosis nigricans present over posterior neck & axillae">Acanthosis nigricans (Neck/Axillae)</option>
                            <option value="Multiple acrochordons (Skin tags) in flexural areas">Multiple skin tags (Acrochordons)</option>
                            <option value="Xanthelasma palpebrarum & tendon xanthomas (Severe dyslipidemia)">Xanthelasma / Tendon xanthomas</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Hyperandrogenism &amp; Hirsutism (PCOS)</label>
                        <select class="zrx-exam-select" data-endo-field="pcos-hirsutism">
                            <option value="No hirsutism or signs of hyperandrogenism">No hirsutism (Normal)</option>
                            <option value="Mild hirsutism (Modified Ferriman-Gallwey Score 8–15)">Mild hirsutism (FG 8–15)</option>
                            <option value="Moderate to severe hirsutism (Ferriman-Gallwey Score >15)">Severe hirsutism (FG >15)</option>
                            <option value="Severe cystic acne & female-pattern androgenic alopecia">Severe cystic acne &amp; Alopecia</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Scenarios:</span>
            <button type="button" class="zrx-exam-chip" data-endo-preset="t2dm-poor">T2DM (Suboptimal HbA1c 8.5% &amp; Acanthosis)</button>
            <button type="button" class="zrx-exam-chip" data-endo-preset="graves">Graves' Hyperthyroidism (Goitre Grade 2, Bruit &amp; Tremor)</button>
            <button type="button" class="zrx-exam-chip" data-endo-preset="hypothyroid">Primary Hypothyroidism (Dry Skin &amp; Slow Reflexes)</button>
            <button type="button" class="zrx-exam-chip" data-endo-preset="cushing">Cushing's Syndrome (Moon Face &amp; Purple Striae)</button>
            <button type="button" class="zrx-exam-chip" data-endo-preset="pcos">PCOS (Insulin Resistance &amp; Hirsutism)</button>
            <button type="button" class="zrx-exam-chip" data-endo-preset="normal-endo">Normal Endocrine Assessment</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="endo-findings" class="zrx-exam-lbl">Endocrinology &amp; Metabolic Assessment Summary</label>
            <textarea id="endo-findings" class="zrx-exam-textarea" rows="3" placeholder="Endocrine status, glycemic control, thyroid examination, metabolic stigmata..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
