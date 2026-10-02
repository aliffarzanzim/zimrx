<?php
declare(strict_types=1);

// Urology module: kidneys & bladder palpation, DRE/prostate grading, LUTS/IPSS, and external genitalia.
?>
<div class="pc-wrapper zrx-exam-wrapper" id="urology-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('droplet', 14) ?>
            <span>Urology &amp; Nephrology Assessment</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-uro" title="Pre-fill normal urological findings">Normal Uro</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-uro" title="Clear all urology fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-mode Selector for Urology Regions -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-uro-tab="renal">
                <?= zrx_icon('activity', 13) ?> 1. Kidneys &amp; Bladder
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-uro-tab="dre">
                <?= zrx_icon('shield', 13) ?> 2. DRE &amp; Prostate
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-uro-tab="luts">
                <?= zrx_icon('grid', 13) ?> 3. LUTS &amp; IPSS
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-uro-tab="genitalia">
                <?= zrx_icon('user', 13) ?> 4. Scrotum &amp; Genitalia
            </button>
        </div>

        <!-- Section 1: Kidneys & Bladder -->
        <div class="zrx-uro-pane active" id="zrx-uro-pane-renal">
            <div class="zrx-exam-dual-grid">
                <!-- Kidneys Palpation Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">KIDNEYS</span>
                        <span>Bimanual Kidney Palpation</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Right Kidney Palpation</label>
                        <select class="zrx-exam-select" data-uro-field="rt-kidney">
                            <option value="Non-palpable, non-tender">Non-palpable, non-tender (Normal)</option>
                            <option value="Palpable, ballotable, smooth surface (Hydronephrosis)">Palpable &amp; ballotable (Hydronephrosis)</option>
                            <option value="Tender palpable mass with irregular surface">Tender palpable mass (Tumor/RCC)</option>
                            <option value="Bilateral enlarged nodular kidneys (ADPKD)">Enlarged nodular (ADPKD)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Left Kidney Palpation</label>
                        <select class="zrx-exam-select" data-uro-field="lt-kidney">
                            <option value="Non-palpable, non-tender">Non-palpable, non-tender (Normal)</option>
                            <option value="Palpable, ballotable, smooth surface">Palpable &amp; ballotable</option>
                            <option value="Tender palpable mass with irregular surface">Tender palpable mass</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Renal Angle (Murphy's Punch Tenderness)</label>
                        <select class="zrx-exam-select" data-uro-field="renal-angle">
                            <option value="Bilateral renal angle punch tenderness negative">Bilateral negative (Non-tender)</option>
                            <option value="Right renal angle tenderness positive (Pyelonephritis / Calculus)">Right punch +ve (Pyelonephritis/Staghorn)</option>
                            <option value="Left renal angle tenderness positive (Pyelonephritis / Calculus)">Left punch +ve (Pyelonephritis/Staghorn)</option>
                            <option value="Bilateral renal angle tenderness positive">Bilateral punch tenderness +ve</option>
                        </select>
                    </div>
                </div>

                <!-- Urinary Bladder Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">BLADDER</span>
                        <span>Urinary Bladder Assessment</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Bladder Palpation &amp; Percussion</label>
                        <select class="zrx-exam-select" data-uro-field="bladder-palp">
                            <option value="Not palpable or percussible (Empty bladder)">Not palpable / Empty (Normal)</option>
                            <option value="Palpable up to umbilicus, tense, exquisitely tender (Acute retention)">Palpable up to umbilicus (Acute retention)</option>
                            <option value="Palpable midway to umbilicus, dull, painless (Chronic retention)">Midway to umbilicus (Chronic retention)</option>
                            <option value="Suprapubic tenderness without frank distension (Cystitis)">Suprapubic tenderness (Cystitis)</option>
                            <option value="Indwelling Foley catheter in situ draining clear amber urine">Foley catheter in situ (Draining)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Ureteric Points Tenderness</label>
                        <select class="zrx-exam-select" data-uro-field="ureteric-points">
                            <option value="Upper and mid ureteric points non-tender">Non-tender (Normal)</option>
                            <option value="Right upper ureteric point tender (PUJ / Upper calculus)">Right upper ureteric tenderness</option>
                            <option value="Right mid ureteric point tender (Iliac vessel crossing)">Right mid ureteric tenderness</option>
                            <option value="Left upper ureteric point tender">Left upper ureteric tenderness</option>
                            <option value="Left mid ureteric point tender">Left mid ureteric tenderness</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: DRE & Prostate -->
        <div class="zrx-uro-pane" id="zrx-uro-pane-dre" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- DRE Findings Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">DRE</span>
                        <span>Digital Rectal Examination</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Anal Tone &amp; Rectal Mucosa</label>
                        <select class="zrx-exam-select" data-uro-field="dre-tone">
                            <option value="Normal sphincter tone, smooth mucosa, empty vault">Normal sphincter tone &amp; mucosa</option>
                            <option value="Lax anal sphincter tone (Neurogenic bladder/Spinal shock)">Lax sphincter (Neurogenic bladder)</option>
                            <option value="Hypertonic / Spastic sphincter with tenderness">Hypertonic / Tender</option>
                            <option value="Palpable rectal induration / shelf">Palpable rectal mass / shelf</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Glove Staining upon Withdrawal</label>
                        <select class="zrx-exam-select" data-uro-field="dre-glove">
                            <option value="Clean / Stained with normal brown stool">Normal stool stained</option>
                            <option value="Stained with fresh red blood">Fresh blood stained</option>
                            <option value="Melaena / Tar-black stool stained">Melaena stained</option>
                            <option value="Purulent discharge present">Purulent discharge</option>
                        </select>
                    </div>
                </div>

                <!-- Prostate Exam Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">PROSTATE</span>
                        <span>Prostate Examination &amp; Staging</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Prostate Size &amp; Clinical Grade</label>
                        <select class="zrx-exam-select" data-uro-field="prostate-grade">
                            <option value="Normal size (~20g, non-enlarged)">Normal size (~20g, non-enlarged)</option>
                            <option value="Grade I enlargement (~25-30g, 1 fingerbreadth)">Grade I enlargement (~25-30g)</option>
                            <option value="Grade II enlargement (~30-50g, 2 fingerbreadths)">Grade II enlargement (~30-50g)</option>
                            <option value="Grade III enlargement (>50g, upper border difficult to reach)">Grade III enlargement (>50g)</option>
                            <option value="Atrophic / Small prostate">Atrophic / Small prostate</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Prostate Consistency &amp; Median Sulcus</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" data-uro-field="prostate-consistency">
                                <option value="Smooth, firm, elastic (BPH)">Smooth, elastic (BPH)</option>
                                <option value="Hard, nodular, woody (Carcinoma)">Hard, nodular (Carcinoma)</option>
                                <option value="Soft, boggy, exquisitely tender (Prostatitis)">Soft, tender (Prostatitis)</option>
                            </select>
                            <select class="zrx-exam-select" data-uro-field="median-sulcus">
                                <option value="Median sulcus well-defined & palpable">Sulcus well-preserved</option>
                                <option value="Median sulcus shallow">Sulcus shallow</option>
                                <option value="Median sulcus obliterated">Sulcus obliterated</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: LUTS & IPSS -->
        <div class="zrx-uro-pane" id="zrx-uro-pane-luts" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Obstructive & Irritative LUTS -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">LUTS</span>
                        <span>Lower Urinary Tract Symptoms</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Voiding / Obstructive Symptoms</label>
                        <select class="zrx-exam-select" data-uro-field="luts-voiding">
                            <option value="No voiding obstructive symptoms">None (Normal urinary stream)</option>
                            <option value="Poor / Weak urinary stream with straining">Weak stream &amp; Straining</option>
                            <option value="Hesitancy & intermittency of stream">Hesitancy &amp; Intermittency</option>
                            <option value="Terminal dribbling & sensation of incomplete emptying">Terminal dribbling &amp; Incomplete emptying</option>
                            <option value="Severe urinary retention (Unable to void)">Acute complete retention</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Storage / Irritative Symptoms</label>
                        <select class="zrx-exam-select" data-uro-field="luts-storage">
                            <option value="No irritative symptoms">None (Normal storage capacity)</option>
                            <option value="Daytime frequency (>8 times/day)">Frequency (>8 times/day)</option>
                            <option value="Urgency with urge incontinence">Urgency &amp; Urge incontinence</option>
                            <option value="Nocturia (1 to 2 times/night)">Nocturia (1–2 times)</option>
                            <option value="Severe nocturia (≥3 times/night)">Severe nocturia (≥3 times)</option>
                            <option value="Dysuria / Burning micturition (Active UTI)">Dysuria / Burning micturition</option>
                        </select>
                    </div>
                </div>

                <!-- IPSS Category & Hematuria -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">IPSS</span>
                        <span>IPSS Score &amp; Macroscopic Urine</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">International Prostate Symptom Score (IPSS)</label>
                        <select class="zrx-exam-select" data-uro-field="ipss-category">
                            <option value="Mild symptoms (IPSS Score 0–7)">Mild symptoms (IPSS 0–7)</option>
                            <option value="Moderate symptoms (IPSS Score 8–19)">Moderate symptoms (IPSS 8–19)</option>
                            <option value="Severe symptoms (IPSS Score 20–35)">Severe symptoms (IPSS 20–35)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Macroscopic Urine Characteristics</label>
                        <select class="zrx-exam-select" data-uro-field="urine-char">
                            <option value="Clear amber urine, no hematuria">Clear amber (Normal)</option>
                            <option value="Painless gross / Frank hematuria">Painless gross hematuria (Malignancy risk)</option>
                            <option value="Hematuria with worm-like clots (Upper tract source)">Hematuria with clots</option>
                            <option value="Turbid / Cloudy pyuria (Urinary tract infection)">Turbid pyuria (Infection)</option>
                            <option value="Tea-colored / Concentrated urine">Tea-colored / Concentrated</option>
                            <option value="Pneumaturia (Air bubbles - Colovesical fistula)">Pneumaturia (Gas/bubbles)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: External Genitalia & Scrotum -->
        <div class="zrx-uro-pane" id="zrx-uro-pane-genitalia" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Testes & Scrotum -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">SCROTUM</span>
                        <span>Testicular &amp; Scrotal Assessment</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Testes &amp; Epididymis</label>
                        <select class="zrx-exam-select" data-uro-field="testes-exam">
                            <option value="Bilateral normal descended testes, non-tender, normal consistency">Bilateral normal descended testes</option>
                            <option value="Acute Epididymo-orchitis (Swollen, tender, Prehn sign positive)">Epididymo-orchitis (Prehn +ve)</option>
                            <option value="Suspected Testicular Torsion (High-riding, tender, absent cremasteric)">Testicular Torsion (Surgical Emergency)</option>
                            <option value="Hydrocele (Fluctuant, transilluminant, get above swelling)">Hydrocele (Transilluminant)</option>
                            <option value="Varicocele (Bag of worms palpable on Valsalva, Left-sided)">Varicocele (Bag of worms)</option>
                            <option value="Undescended testis / Cryptorchidism">Undescended testis / Cryptorchidism</option>
                            <option value="Hard, painless testicular mass (Suspected testicular neoplasm)">Painless hard mass (Testicular Tumor)</option>
                        </select>
                    </div>
                </div>

                <!-- Penis, Prepuce & Urethra -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">PENIS</span>
                        <span>Penis, Prepuce &amp; Urethra</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Prepuce &amp; Glans Examination</label>
                        <select class="zrx-exam-select" data-uro-field="penis-prepuce">
                            <option value="Normal circumcised / Fully retractile prepuce">Normal retractile / Circumcised</option>
                            <option value="Phimosis (Non-retractile prepuce, pinpoint opening)">Phimosis (Non-retractile)</option>
                            <option value="Paraphimosis (Constricting band behind corona)">Paraphimosis (Constricting band)</option>
                            <option value="Balanoposthitis (Erythema & inflammation of glans/prepuce)">Balanoposthitis (Inflamed glans)</option>
                            <option value="Peyronie disease (Palpable fibrous plaque on shaft)">Peyronie fibrous plaque</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Urethral Meatus &amp; Discharge</label>
                        <select class="zrx-exam-select" data-uro-field="urethral-meatus">
                            <option value="Normal meatus position, no discharge">Normal meatus, no discharge</option>
                            <option value="Hypospadias (Ventral opening of meatus)">Hypospadias (Ventral)</option>
                            <option value="Meatal stenosis (Pinpoint opening with spraying)">Meatal stenosis</option>
                            <option value="Purulent urethral discharge (Gonococcal urethritis)">Purulent discharge (Gonococcal)</option>
                            <option value="Mucopurulent / Scant discharge (Non-gonococcal urethritis)">Mucopurulent discharge (NGU)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Scenarios:</span>
            <button type="button" class="zrx-exam-chip" data-uro-preset="bph">Benign Prostatic Hyperplasia (Grade II BPH &amp; LUTS)</button>
            <button type="button" class="zrx-exam-chip" data-uro-preset="renal-colic">Acute Ureteric Colic (Renal Angle Tender)</button>
            <button type="button" class="zrx-exam-chip" data-uro-preset="pyelonephritis">Acute Pyelonephritis (High Fever &amp; Punch +ve)</button>
            <button type="button" class="zrx-exam-chip" data-uro-preset="retention">Acute Urinary Retention (Palpable Bladder)</button>
            <button type="button" class="zrx-exam-chip" data-uro-preset="prostatitis">Acute Prostatitis (Tender Boggy Prostate)</button>
            <button type="button" class="zrx-exam-chip" data-uro-preset="normal-uro">Normal Urological Exam</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="urology-findings" class="zrx-exam-lbl">Urology &amp; Nephrology Assessment Summary</label>
            <textarea id="urology-findings" class="zrx-exam-textarea" rows="3" placeholder="Urological assessment, DRE/prostate findings, LUTS, and clinical formulation..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
