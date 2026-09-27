<?php
/**
 * ZimRx - Dental Specialized Examination & Interactive Odontogram Module
 * International FDI 2-digit tooth notation (Adult 11-48, Primary 51-85),
 * surface condition mapping, live DMFT counter, and periodontal scoring.
 */
?>
<div class="pc-wrapper zrx-exam-wrapper" id="dental-chart-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('tooth', 14) ?>
            <span>Dental Chart &amp; Odontogram</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="sound-dental" title="Pre-fill intact, sound dentition">Sound Dentition</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-dental" title="Clear all tooth markers">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Top Metrics & Dentition Type Bar -->
        <div class="zrx-dental-top-bar">
            <div class="zrx-dental-mode-toggle">
                <button type="button" class="zrx-dental-type-btn active" data-dentition="permanent">Adult (Permanent 11–48)</button>
                <button type="button" class="zrx-dental-type-btn" data-dentition="deciduous">Pediatric (Deciduous 51–85)</button>
            </div>
            <div class="zrx-dental-dmft-badge">
                <span>DMFT Index:</span>
                <strong id="zrx-dmft-val">D: 0 | M: 0 | F: 0 (Total: 0)</strong>
            </div>
        </div>

        <!-- Interactive FDI Odontogram Grid -->
        <div class="zrx-odontogram-card">
            <!-- Maxillary Arch (Upper) -->
            <div class="zrx-odontogram-arch upper">
                <div class="zrx-arch-label">Maxillary (Upper) Arch</div>
                <div class="zrx-arch-row permanent-teeth" id="zrx-arch-upper">
                    <!-- Q1 Upper Right: 18 to 11 -->
                    <div class="zrx-quadrant q1">
                        <?php for ($t = 18; $t >= 11; $t--): ?>
                            <button type="button" class="zrx-tooth-btn" data-tooth="<?= $t ?>" title="Tooth <?= $t ?>">
                                <span class="zrx-tooth-num"><?= $t ?></span>
                                <span class="zrx-tooth-box"></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="zrx-arch-midline"></div>
                    <!-- Q2 Upper Left: 21 to 28 -->
                    <div class="zrx-quadrant q2">
                        <?php for ($t = 21; $t <= 28; $t++): ?>
                            <button type="button" class="zrx-tooth-btn" data-tooth="<?= $t ?>" title="Tooth <?= $t ?>">
                                <span class="zrx-tooth-num"><?= $t ?></span>
                                <span class="zrx-tooth-box"></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Deciduous Upper Arch (55..51 | 61..65) -->
                <div class="zrx-arch-row deciduous-teeth" id="zrx-arch-upper-dec" hidden>
                    <div class="zrx-quadrant q5">
                        <?php for ($t = 55; $t >= 51; $t--): ?>
                            <button type="button" class="zrx-tooth-btn deciduous" data-tooth="<?= $t ?>" title="Primary Tooth <?= $t ?>">
                                <span class="zrx-tooth-num"><?= $t ?></span>
                                <span class="zrx-tooth-box"></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="zrx-arch-midline"></div>
                    <div class="zrx-quadrant q6">
                        <?php for ($t = 61; $t <= 65; $t++): ?>
                            <button type="button" class="zrx-tooth-btn deciduous" data-tooth="<?= $t ?>" title="Primary Tooth <?= $t ?>">
                                <span class="zrx-tooth-num"><?= $t ?></span>
                                <span class="zrx-tooth-box"></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Horizontal Arch Separator -->
            <div class="zrx-arch-divider">
                <span>R (Right Patient Side)</span>
                <span>MIDLINE</span>
                <span>L (Left Patient Side)</span>
            </div>

            <!-- Mandibular Arch (Lower) -->
            <div class="zrx-odontogram-arch lower">
                <div class="zrx-arch-row permanent-teeth" id="zrx-arch-lower">
                    <!-- Q4 Lower Right: 48 to 41 -->
                    <div class="zrx-quadrant q4">
                        <?php for ($t = 48; $t >= 41; $t--): ?>
                            <button type="button" class="zrx-tooth-btn" data-tooth="<?= $t ?>" title="Tooth <?= $t ?>">
                                <span class="zrx-tooth-box"></span>
                                <span class="zrx-tooth-num"><?= $t ?></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="zrx-arch-midline"></div>
                    <!-- Q3 Lower Left: 31 to 38 -->
                    <div class="zrx-quadrant q3">
                        <?php for ($t = 31; $t <= 38; $t++): ?>
                            <button type="button" class="zrx-tooth-btn" data-tooth="<?= $t ?>" title="Tooth <?= $t ?>">
                                <span class="zrx-tooth-box"></span>
                                <span class="zrx-tooth-num"><?= $t ?></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Deciduous Lower Arch (85..81 | 71..75) -->
                <div class="zrx-arch-row deciduous-teeth" id="zrx-arch-lower-dec" hidden>
                    <div class="zrx-quadrant q8">
                        <?php for ($t = 85; $t >= 81; $t--): ?>
                            <button type="button" class="zrx-tooth-btn deciduous" data-tooth="<?= $t ?>" title="Primary Tooth <?= $t ?>">
                                <span class="zrx-tooth-box"></span>
                                <span class="zrx-tooth-num"><?= $t ?></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="zrx-arch-midline"></div>
                    <div class="zrx-quadrant q7">
                        <?php for ($t = 71; $t <= 75; $t++): ?>
                            <button type="button" class="zrx-tooth-btn deciduous" data-tooth="<?= $t ?>" title="Primary Tooth <?= $t ?>">
                                <span class="zrx-tooth-box"></span>
                                <span class="zrx-tooth-num"><?= $t ?></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="zrx-arch-label">Mandibular (Lower) Arch</div>
            </div>
        </div>

        <!-- Active Tooth Inspector & Treatment Planner Panel -->
        <div class="zrx-dental-inspector-card">
            <div class="zrx-dental-insp-head">
                <span class="zrx-dental-insp-badge" id="zrx-dental-active-badge">Tooth #46</span>
                <span class="zrx-dental-insp-title" id="zrx-dental-active-title">Lower Right First Molar</span>
            </div>

            <div class="zrx-dental-insp-grid">
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Tooth Status / Pathology</label>
                    <select class="zrx-exam-select" id="zrx-tooth-status">
                        <option value="Sound / Normal">Sound / Normal (No Pathology)</option>
                        <option value="Dental Caries (Cavity)">Dental Caries (Cavity)</option>
                        <option value="Restoration / Filled">Existing Restoration / Filled</option>
                        <option value="Missing Tooth">Missing Tooth</option>
                        <option value="Root Canal Treated (RCT)">Root Canal Treated (RCT)</option>
                        <option value="Crown / Bridge Abutment">Crown / Bridge Abutment</option>
                        <option value="Grossly Carious / Root Stump">Grossly Carious / Retained Root</option>
                        <option value="Impaction (Partially Erupted)">Impaction / Pericoronitis</option>
                        <option value="Fractured Tooth">Fractured / Traumatized</option>
                        <option value="Indicated for Extraction">Indicated for Extraction</option>
                    </select>
                </div>

                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Surface Involvement</label>
                    <div class="zrx-dental-surfaces-group">
                        <label class="zrx-dental-surf-pill"><input type="checkbox" data-surf="O" id="zrx-surf-o"><span>O (Occlusal)</span></label>
                        <label class="zrx-dental-surf-pill"><input type="checkbox" data-surf="M" id="zrx-surf-m"><span>M (Mesial)</span></label>
                        <label class="zrx-dental-surf-pill"><input type="checkbox" data-surf="D" id="zrx-surf-d"><span>D (Distal)</span></label>
                        <label class="zrx-dental-surf-pill"><input type="checkbox" data-surf="B" id="zrx-surf-b"><span>B (Buccal)</span></label>
                        <label class="zrx-dental-surf-pill"><input type="checkbox" data-surf="L" id="zrx-surf-l"><span>L (Lingual)</span></label>
                    </div>
                </div>

                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Recommended Dental Procedure</label>
                    <select class="zrx-exam-select" id="zrx-tooth-plan">
                        <option value="None / Observation">None / Observation</option>
                        <option value="Composite Light-cure Restoration">Composite Restoration</option>
                        <option value="GIC Base + Permanent Restoration">GIC Restoration</option>
                        <option value="Root Canal Treatment (RCT)">Root Canal Treatment (RCT)</option>
                        <option value="Crown Placement (PFM / Zirconia)">Crown (PFM/Zirconia)</option>
                        <option value="Dental Extraction">Dental Extraction</option>
                        <option value="Surgical Disimpaction">Surgical Disimpaction</option>
                        <option value="Dental Implant">Dental Implant</option>
                    </select>
                </div>
            </div>

            <!-- Periodontal & Oral Hygiene Row -->
            <div class="zrx-dental-perio-row">
                <div class="zrx-exam-form-row" style="flex: 1;">
                    <label class="zrx-exam-lbl">Gingival &amp; Periodontal Health</label>
                    <select class="zrx-exam-select" id="zrx-dental-perio">
                        <option value="Healthy pink gingiva, no bleeding on probing">Healthy Gingiva (Normal)</option>
                        <option value="Marginal Gingivitis (BOP positive, erythematous)">Marginal Gingivitis</option>
                        <option value="Chronic Generalized Periodontitis (Pocket >4mm)">Generalized Periodontitis</option>
                        <option value="Localized Periodontitis / Periodontal Abscess">Periodontal Abscess</option>
                        <option value="Gingival Recession / Hypersensitivity">Gingival Recession</option>
                    </select>
                </div>
                <div class="zrx-exam-form-row" style="flex: 1;">
                    <label class="zrx-exam-lbl">Calculus &amp; Plaque Index</label>
                    <select class="zrx-exam-select" id="zrx-dental-calculus">
                        <option value="Mild supragingival calculus">Mild (Supragingival)</option>
                        <option value="Moderate calculus with subgingival deposits">Moderate (Subgingival)</option>
                        <option value="Heavy calculus / Gross extrinsic stains">Heavy Calculus / Stains</option>
                        <option value="Negligible / Clear plaque">None / Clean</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Presets:</span>
            <button type="button" class="zrx-exam-chip" data-dental-preset="caries-46">Tooth 46 (Deep Occlusal Caries)</button>
            <button type="button" class="zrx-exam-chip" data-dental-preset="rct-11">Tooth 11 (Traumatized / RCT)</button>
            <button type="button" class="zrx-exam-chip" data-dental-preset="impaction-38">Tooth 38 (Impacted Wisdom Tooth)</button>
            <button type="button" class="zrx-exam-chip" data-dental-preset="scaling-needed">Generalized Calculus (Full Mouth Scaling)</button>
            <button type="button" class="zrx-exam-chip" data-dental-preset="sound-all">Sound Dentition (Unremarkable)</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="dental-exam-findings" class="zrx-exam-lbl">Dental Examination &amp; Treatment Plan</label>
            <textarea id="dental-exam-findings" class="zrx-exam-textarea" rows="3" placeholder="Odontogram findings, tooth diagnoses, and planned procedures..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
