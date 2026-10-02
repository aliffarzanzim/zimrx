<?php
declare(strict_types=1);

// Diabetic foot module: Wagner ulcer staging (0-5), 10-point monofilament map, and vascular exam.
?>
<div class="pc-wrapper zrx-exam-wrapper" id="diabetic-foot-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('shield', 14) ?>
            <span>Diabetic Foot &amp; Ulcer Assessment</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-foot" title="Pre-fill normal foot with intact protective sensation">Unremarkable</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-foot" title="Clear all foot examination fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Top Metrics & Risk Stratification Bar -->
        <div class="zrx-df-metrics-bar">
            <div class="zrx-df-metric-chip">
                <span class="zrx-df-metric-lbl">Wagner Grade</span>
                <span class="zrx-df-metric-val" id="zrx-df-wagner-val">Grade 0 (Intact Skin)</span>
            </div>
            <div class="zrx-df-metric-chip">
                <span class="zrx-df-metric-lbl">Monofilament Sensation</span>
                <span class="zrx-df-metric-val" id="zrx-df-sensory-val">10 / 10 Intact (Normal)</span>
            </div>
            <div class="zrx-df-metric-chip">
                <span class="zrx-df-metric-lbl">IWGDF Risk Category</span>
                <span class="zrx-df-metric-status" id="zrx-df-risk-val">Category 0 (Very Low Risk)</span>
            </div>
        </div>

        <!-- Main Body: Sensory Map + Clinical Parameters -->
        <div class="zrx-df-layout-grid">
            <!-- Left: 10-Point Monofilament Plantar SVG Map -->
            <div class="zrx-df-map-card">
                <div class="zrx-df-map-head">
                    <span class="zrx-df-card-title">10g Monofilament Sensation Map</span>
                    <span class="zrx-df-card-sub">Click points to mark Loss of Sensation</span>
                </div>
                <div class="zrx-df-svg-wrap">
                    <svg class="zrx-df-svg" id="zrx-df-svg" viewBox="0 0 160 260">
                        <!-- Plantar Foot Contour Outline -->
                        <path class="zrx-df-foot-outline" d="M 60,15 C 38,15 32,32 30,55 C 28,75 22,95 24,120 C 26,145 34,165 34,195 C 34,225 45,245 80,245 C 115,245 126,225 126,195 C 126,165 130,135 128,105 C 126,75 130,50 115,30 C 100,15 80,15 60,15 Z" fill="#f8fafc" stroke="#94a3b8" stroke-width="2" />
                        
                        <!-- 10 Monofilament Sensory Test Points -->
                        <!-- 1. Great Toe (Hallux) -->
                        <circle class="zrx-df-point intact" data-point="1" data-name="Great Toe Pulp" cx="50" cy="35" r="7" />
                        <text class="zrx-df-pt-text" x="50" y="38">1</text>

                        <!-- 2. 3rd Toe -->
                        <circle class="zrx-df-point intact" data-point="2" data-name="3rd Toe Pulp" cx="80" cy="30" r="7" />
                        <text class="zrx-df-pt-text" x="80" y="33">2</text>

                        <!-- 3. 5th Toe -->
                        <circle class="zrx-df-point intact" data-point="3" data-name="5th Toe Pulp" cx="106" cy="40" r="7" />
                        <text class="zrx-df-pt-text" x="106" y="43">3</text>

                        <!-- 4. 1st Metatarsal Head -->
                        <circle class="zrx-df-point intact" data-point="4" data-name="1st Metatarsal Head" cx="48" cy="85" r="7" />
                        <text class="zrx-df-pt-text" x="48" y="88">4</text>

                        <!-- 5. 3rd Metatarsal Head -->
                        <circle class="zrx-df-point intact" data-point="5" data-name="3rd Metatarsal Head" cx="78" cy="80" r="7" />
                        <text class="zrx-df-pt-text" x="78" y="83">5</text>

                        <!-- 6. 5th Metatarsal Head -->
                        <circle class="zrx-df-point intact" data-point="6" data-name="5th Metatarsal Head" cx="112" cy="88" r="7" />
                        <text class="zrx-df-pt-text" x="112" y="91">6</text>

                        <!-- 7. Midfoot Medial Arch -->
                        <circle class="zrx-df-point intact" data-point="7" data-name="Medial Midfoot" cx="52" cy="140" r="7" />
                        <text class="zrx-df-pt-text" x="52" y="143">7</text>

                        <!-- 8. Midfoot Lateral Arch -->
                        <circle class="zrx-df-point intact" data-point="8" data-name="Lateral Midfoot" cx="110" cy="145" r="7" />
                        <text class="zrx-df-pt-text" x="110" y="148">8</text>

                        <!-- 9. Heel (Calcaneus) -->
                        <circle class="zrx-df-point intact" data-point="9" data-name="Plantar Heel" cx="80" cy="210" r="7" />
                        <text class="zrx-df-pt-text" x="80" y="213">9</text>

                        <!-- 10. Dorsum (1st Interspace) -->
                        <circle class="zrx-df-point intact" data-point="10" data-name="Dorsal 1st Webspace" cx="64" cy="60" r="7" />
                        <text class="zrx-df-pt-text" x="64" y="63">10</text>
                    </svg>
                </div>
                <div class="zrx-df-map-legend">
                    <span class="zrx-df-legend-item"><span class="zrx-df-dot intact"></span> Intact Sensation</span>
                    <span class="zrx-df-legend-item"><span class="zrx-df-dot absent"></span> Loss of Sensation (LOPS)</span>
                </div>
            </div>

            <!-- Right: Wagner Staging & Vascular / Deformity Checklist -->
            <div class="zrx-df-controls-card">
                <!-- Wagner Grade Dropdown -->
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Wagner Ulcer Classification</label>
                    <select class="zrx-exam-select" id="zrx-df-wagner-select">
                        <option value="Grade 0: Intact skin, high-risk foot (callus/deformity)">Grade 0: Intact skin, high risk foot</option>
                        <option value="Grade 1: Superficial ulcer (not involving tendon/capsule/bone)">Grade 1: Superficial ulcer</option>
                        <option value="Grade 2: Deep ulcer penetrating to tendon, bone or joint capsule">Grade 2: Deep ulcer to tendon / capsule</option>
                        <option value="Grade 3: Deep ulcer with abscess, osteomyelitis or joint sepsis">Grade 3: Deep ulcer with osteomyelitis / abscess</option>
                        <option value="Grade 4: Localized gangrene (forefoot or heel)">Grade 4: Localized gangrene (forefoot/heel)</option>
                        <option value="Grade 5: Extensive generalized gangrene of entire foot">Grade 5: Extensive gangrene of entire foot</option>
                    </select>
                </div>

                <!-- Peripheral Vascular Pulses -->
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Peripheral Pulses &amp; Perfusion</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" id="zrx-df-dp-pulse">
                            <option value="Dorsalis Pedis: Palpable normal (+2)">Dorsalis Pedis: Palpable (+2)</option>
                            <option value="Dorsalis Pedis: Weak / Diminished (+1)">Dorsalis Pedis: Weak (+1)</option>
                            <option value="Dorsalis Pedis: Absent (0)">Dorsalis Pedis: Absent (0)</option>
                        </select>
                        <select class="zrx-exam-select" id="zrx-df-pt-pulse">
                            <option value="Posterior Tibial: Palpable normal (+2)">Post. Tibial: Palpable (+2)</option>
                            <option value="Posterior Tibial: Weak / Diminished (+1)">Post. Tibial: Weak (+1)</option>
                            <option value="Posterior Tibial: Absent (0)">Post. Tibial: Absent (0)</option>
                        </select>
                    </div>
                </div>

                <!-- Capillary Refill & Skin Condition -->
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Capillary Refill &amp; Skin</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" id="zrx-df-crt">
                            <option value="CRT < 2 seconds (Normal perfusion)">CRT < 2s (Normal)</option>
                            <option value="CRT > 2 seconds (Delayed / PVD)">CRT > 2s (Delayed / PVD)</option>
                        </select>
                        <select class="zrx-exam-select" id="zrx-df-skin">
                            <option value="Warm, normal sweating">Warm, normal sweating</option>
                            <option value="Dry, anhidrotic with fissures (Autonomic)">Dry &amp; Fissured (Autonomic)</option>
                            <option value="Cold, pale / cyanotic on elevation">Cold &amp; Pale / Cyanotic</option>
                        </select>
                    </div>
                </div>

                <!-- Deformity & Callus Checklist -->
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Structural Foot Deformities</label>
                    <select class="zrx-exam-select" id="zrx-df-deformity">
                        <option value="None / Normal biomechanics">None / Normal alignment</option>
                        <option value="Claw / Hammer toes (Motor neuropathy)">Claw / Hammer toes</option>
                        <option value="Charcot neuroarthropathy (Rocker bottom foot)">Charcot Neuroarthropathy</option>
                        <option value="Prominent metatarsal heads with plantar callus">Prominent MT heads + Callus</option>
                        <option value="Hallux valgus (Bunion)">Hallux Valgus</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Presets:</span>
            <button type="button" class="zrx-exam-chip" data-df-preset="grade1-ulcer">Wagner 1 Superficial Ulcer (Plantar 1st MT)</button>
            <button type="button" class="zrx-exam-chip" data-df-preset="grade2-deep">Wagner 2 Deep Ulcer with Neuropathy</button>
            <button type="button" class="zrx-exam-chip" data-df-preset="charcot">Charcot Neuroarthropathy (Rocker-bottom)</button>
            <button type="button" class="zrx-exam-chip" data-df-preset="pvd-diminished">Peripheral Vascular Disease (Absent Pulses)</button>
            <button type="button" class="zrx-exam-chip" data-df-preset="normal-foot">Unremarkable Diabetic Foot (Low Risk)</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="diabetic-foot-findings" class="zrx-exam-lbl">Diabetic Foot Assessment &amp; Management Plan</label>
            <textarea id="diabetic-foot-findings" class="zrx-exam-textarea" rows="3" placeholder="Diabetic foot findings, LOPS score, Wagner ulcer grade, and podiatry orders..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
