<?php
declare(strict_types=1);

/**
 * ZimRx - Burn Assessment & Fluid Resuscitation Module
 * Interactive Wallace Rule of Nines anatomical mapping, % TBSA calculator,
 * and live Parkland Resuscitation formula (4 mL x kg x %TBSA).
 */
?>
<div class="pc-wrapper zrx-exam-wrapper" id="burn-assessment-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('flame', 14) ?>
            <span>Burn Assessment &amp; Resuscitation</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="minor-burn" title="Pre-fill minor partial thickness burn">Minor Preset</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-burn" title="Clear all burn fields and diagrams">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Top Metrics Bar: % TBSA, Severity, and Resuscitation Requirement -->
        <div class="zrx-burn-metrics-bar">
            <div class="zrx-burn-metric-chip">
                <span class="zrx-burn-metric-lbl">Total Burn Area (% TBSA)</span>
                <span class="zrx-burn-metric-val" id="zrx-burn-tbsa-display">0.0%</span>
            </div>
            <div class="zrx-burn-metric-chip">
                <span class="zrx-burn-metric-lbl">Classification</span>
                <span class="zrx-burn-metric-status" id="zrx-burn-class-display">No Burn Mapped</span>
            </div>
            <div class="zrx-burn-metric-chip">
                <span class="zrx-burn-metric-lbl">24h Parkland Fluid</span>
                <span class="zrx-burn-metric-val" id="zrx-burn-fluid-display">0 mL (RL)</span>
            </div>
        </div>

        <!-- Interactive Wallace Rule of Nines Silhouettes (Anterior & Posterior) -->
        <div class="zrx-burn-diagram-section">
            <div class="zrx-burn-diagrams-grid">
                <!-- Anterior Silhouette Card -->
                <div class="zrx-burn-diagram-card">
                    <div class="zrx-burn-card-title">Anterior View (50%)</div>
                    <div class="zrx-burn-svg-wrap">
                        <svg class="zrx-burn-svg" id="zrx-burn-ant-svg" viewBox="0 0 200 320">
                            <!-- Head & Neck Anterior (4.5%) -->
                            <path class="zrx-burn-segment" data-segment="ant_head" data-pct="4.5" data-name="Head & Neck (Ant)" d="M 85,15 C 85,2 115,2 115,15 C 115,35 108,44 108,48 L 92,48 C 92,44 85,35 85,15 Z" />
                            <text class="zrx-burn-label" x="100" y="28">4.5%</text>

                            <!-- Chest / Upper Trunk (9%) -->
                            <path class="zrx-burn-segment" data-segment="ant_chest" data-pct="9.0" data-name="Chest / Upper Torso" d="M 72,50 L 128,50 L 126,96 L 74,96 Z" />
                            <text class="zrx-burn-label" x="100" y="75">Chest 9%</text>

                            <!-- Abdomen / Lower Trunk (9%) -->
                            <path class="zrx-burn-segment" data-segment="ant_abdo" data-pct="9.0" data-name="Abdomen / Lower Torso" d="M 74,98 L 126,98 L 122,148 L 78,148 Z" />
                            <text class="zrx-burn-label" x="100" y="125">Abdo 9%</text>

                            <!-- Right Upper Limb Anterior (4.5%) -->
                            <path class="zrx-burn-segment" data-segment="ant_arm_rt" data-pct="4.5" data-name="Right Arm (Ant)" d="M 70,52 L 50,96 L 36,140 L 48,143 L 64,102 L 72,66 Z" />
                            <text class="zrx-burn-label" x="52" y="100">4.5%</text>

                            <!-- Left Upper Limb Anterior (4.5%) -->
                            <path class="zrx-burn-segment" data-segment="ant_arm_lt" data-pct="4.5" data-name="Left Arm (Ant)" d="M 130,52 L 150,96 L 164,140 L 152,143 L 136,102 L 128,66 Z" />
                            <text class="zrx-burn-label" x="148" y="100">4.5%</text>

                            <!-- Perineum / Genitalia (1%) -->
                            <path class="zrx-burn-segment" data-segment="ant_perineum" data-pct="1.0" data-name="Perineum / Genitalia" d="M 92,150 L 108,150 L 100,162 Z" />
                            <text class="zrx-burn-label" x="100" y="158" font-size="8">1%</text>

                            <!-- Right Lower Limb Anterior (9%) -->
                            <path class="zrx-burn-segment" data-segment="ant_leg_rt" data-pct="9.0" data-name="Right Leg (Ant)" d="M 77,150 L 93,158 L 90,224 L 91,296 L 75,296 L 74,224 Z" />
                            <text class="zrx-burn-label" x="83" y="220">9%</text>

                            <!-- Left Lower Limb Anterior (9%) -->
                            <path class="zrx-burn-segment" data-segment="ant_leg_lt" data-pct="9.0" data-name="Left Leg (Ant)" d="M 107,158 L 123,150 L 126,224 L 125,296 L 109,296 L 110,224 Z" />
                            <text class="zrx-burn-label" x="117" y="220">9%</text>
                        </svg>
                    </div>
                </div>

                <!-- Posterior Silhouette Card -->
                <div class="zrx-burn-diagram-card">
                    <div class="zrx-burn-card-title">Posterior View (50%)</div>
                    <div class="zrx-burn-svg-wrap">
                        <svg class="zrx-burn-svg" id="zrx-burn-post-svg" viewBox="0 0 200 320">
                            <!-- Head & Neck Posterior (4.5%) -->
                            <path class="zrx-burn-segment" data-segment="post_head" data-pct="4.5" data-name="Head & Neck (Post)" d="M 85,15 C 85,2 115,2 115,15 C 115,35 108,44 108,48 L 92,48 C 92,44 85,35 85,15 Z" />
                            <text class="zrx-burn-label" x="100" y="28">4.5%</text>

                            <!-- Upper Back (9%) -->
                            <path class="zrx-burn-segment" data-segment="post_back_upper" data-pct="9.0" data-name="Upper Back" d="M 72,50 L 128,50 L 126,96 L 74,96 Z" />
                            <text class="zrx-burn-label" x="100" y="75">Upper Back 9%</text>

                            <!-- Lower Back & Buttocks (9%) -->
                            <path class="zrx-burn-segment" data-segment="post_back_lower" data-pct="9.0" data-name="Lower Back & Buttocks" d="M 74,98 L 126,98 L 122,154 L 78,154 Z" />
                            <text class="zrx-burn-label" x="100" y="125">Lower Back 9%</text>

                            <!-- Left Upper Limb Posterior (4.5%) -->
                            <path class="zrx-burn-segment" data-segment="post_arm_lt" data-pct="4.5" data-name="Left Arm (Post)" d="M 70,52 L 50,96 L 36,140 L 48,143 L 64,102 L 72,66 Z" />
                            <text class="zrx-burn-label" x="52" y="100">4.5%</text>

                            <!-- Right Upper Limb Posterior (4.5%) -->
                            <path class="zrx-burn-segment" data-segment="post_arm_rt" data-pct="4.5" data-name="Right Arm (Post)" d="M 130,52 L 150,96 L 164,140 L 152,143 L 136,102 L 128,66 Z" />
                            <text class="zrx-burn-label" x="148" y="100">4.5%</text>

                            <!-- Left Lower Limb Posterior (9%) -->
                            <path class="zrx-burn-segment" data-segment="post_leg_lt" data-pct="9.0" data-name="Left Leg (Post)" d="M 77,156 L 98,156 L 92,224 L 91,296 L 75,296 L 74,224 Z" />
                            <text class="zrx-burn-label" x="83" y="220">9%</text>

                            <!-- Right Lower Limb Posterior (9%) -->
                            <path class="zrx-burn-segment" data-segment="post_leg_rt" data-pct="9.0" data-name="Right Leg (Post)" d="M 102,156 L 123,156 L 126,224 L 125,296 L 109,296 L 108,224 Z" />
                            <text class="zrx-burn-label" x="117" y="220">9%</text>
                        </svg>
                    </div>
                </div>

                <!-- Resuscitation Calculator & Depth Control Card -->
                <div class="zrx-burn-calc-card">
                    <div class="zrx-burn-calc-head">
                        <span class="zrx-burn-calc-badge">Parkland Formula</span>
                        <span class="zrx-burn-calc-formula">4 mL &times; Weight (kg) &times; %TBSA</span>
                    </div>

                    <div class="zrx-burn-form-grid">
                        <div class="zrx-exam-form-row">
                            <label class="zrx-exam-lbl">Patient Body Weight (kg)</label>
                            <input type="number" min="1" max="250" step="0.5" class="zrx-exam-input" id="zrx-burn-weight" value="65" autocomplete="off">
                        </div>
                        <div class="zrx-exam-form-row">
                            <label class="zrx-exam-lbl">Predominant Burn Depth</label>
                            <select class="zrx-exam-select" id="zrx-burn-depth">
                                <option value="2nd Degree (Superficial Partial Thickness)">2nd Deg (Superficial Partial)</option>
                                <option value="2nd Degree (Deep Partial Thickness)">2nd Deg (Deep Partial)</option>
                                <option value="3rd Degree (Full Thickness)">3rd Deg (Full Thickness)</option>
                                <option value="Mixed 2nd & 3rd Degree" selected>Mixed 2nd &amp; 3rd Degree</option>
                                <option value="1st Degree (Superficial Erythema)">1st Deg (Superficial only)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fluid Infusion Schedule breakdown -->
                    <div class="zrx-burn-infusion-card">
                        <div class="zrx-burn-infusion-row">
                            <span class="zrx-burn-infusion-period">1st 8 Hours (50%):</span>
                            <strong class="zrx-burn-infusion-val" id="zrx-burn-fluid-8h">0 mL</strong>
                            <span class="zrx-burn-infusion-rate" id="zrx-burn-rate-8h">(0 mL/hr)</span>
                        </div>
                        <div class="zrx-burn-infusion-row">
                            <span class="zrx-burn-infusion-period">Next 16 Hours (50%):</span>
                            <strong class="zrx-burn-infusion-val" id="zrx-burn-fluid-16h">0 mL</strong>
                            <span class="zrx-burn-infusion-rate" id="zrx-burn-rate-16h">(0 mL/hr)</span>
                        </div>
                        <div class="zrx-burn-infusion-target">
                            <span>Urine Output Target: <strong>0.5 – 1.0 mL/kg/hr</strong> (<span id="zrx-burn-uop-target">32.5 – 65 mL/hr</span>)</span>
                        </div>
                    </div>

                    <!-- Inhalation & Circumferential Risk Toggles -->
                    <div class="zrx-burn-risk-grid">
                        <label class="zrx-abdo-check-pill">
                            <input type="checkbox" id="zrx-burn-inhalation">
                            <span>Inhalation Injury Risk</span>
                        </label>
                        <label class="zrx-abdo-check-pill">
                            <input type="checkbox" id="zrx-burn-circumferential">
                            <span>Circumferential Burn</span>
                        </label>
                        <label class="zrx-abdo-check-pill">
                            <input type="checkbox" id="zrx-burn-electrical">
                            <span>High-Voltage / Chemical</span>
                        </label>
                        <label class="zrx-abdo-check-pill">
                            <input type="checkbox" id="zrx-burn-escharotomy">
                            <span>Escharotomy Indicated</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Burn Presets Bar -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Scenarios:</span>
            <button type="button" class="zrx-exam-chip" data-burn-scenario="scald-arm">Scald Burn (Right Upper Limb, 9%)</button>
            <button type="button" class="zrx-exam-chip" data-burn-scenario="flame-torso">Flame Burn (Chest &amp; Abdomen, 18%)</button>
            <button type="button" class="zrx-exam-chip" data-burn-scenario="major-burn">Major Burn (Torso + Arms, 36%)</button>
            <button type="button" class="zrx-exam-chip" data-burn-scenario="bilateral-legs">Bilateral Lower Limbs (36%)</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="burn-exam-findings" class="zrx-exam-lbl">Burn Assessment &amp; Resuscitation Orders</label>
            <textarea id="burn-exam-findings" class="zrx-exam-textarea" rows="3" placeholder="Burn surface mapping, TBSA %, depth, fluid resuscitation orders..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
