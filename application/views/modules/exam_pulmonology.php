<?php
declare(strict_types=1);

// Pulmonology module: breath sounds, adventitious sounds, chest inspection/percussion, SpO2, and GINA/GOLD staging.
?>
<div class="pc-wrapper zrx-exam-wrapper" id="pulmo-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('wind', 14) ?>
            <span>Pulmonology &amp; Respiratory Examination</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-pulmo" title="Pre-fill normal respiratory findings">Normal Chest</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-pulmo" title="Clear all pulmonology fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-mode Selector for Pulmonology Components -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-pulmo-tab="auscultation">
                <?= zrx_icon('ear', 13) ?> 1. Auscultation &amp; Breath Sounds
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-pulmo-tab="inspection">
                <?= zrx_icon('activity', 13) ?> 2. Inspection, Trachea &amp; Percussion
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-pulmo-tab="metrics">
                <?= zrx_icon('shield', 13) ?> 3. SpO2, PEFR &amp; Severity Staging
            </button>
        </div>

        <!-- Section 1: Auscultation & Breath Sounds -->
        <div class="zrx-pulmo-pane active" id="zrx-pulmo-pane-auscultation">
            <div class="zrx-exam-dual-grid">
                <!-- Breath Sounds & Wheeze Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">BREATH SOUNDS</span>
                        <span>Character &amp; Added Wheeze</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Breath Sounds Character</label>
                        <select class="zrx-exam-select" data-pulmo-field="breath-sounds">
                            <option value="Normal vesicular breath sounds bilaterally, no prolongation">Normal vesicular breath sounds (Normal)</option>
                            <option value="Vesicular with prolonged expiration (Airway obstruction - Asthma / COPD)">Vesicular with prolonged expiration</option>
                            <option value="Bronchial breathing with tubular high pitch (Consolidation / Pneumonia)">Bronchial breathing (Consolidation)</option>
                            <option value="Diminished vesicular breath sounds throughout (COPD / Emphysema)">Diminished breath sounds throughout</option>
                            <option value="Diminished / Absent breath sounds at Right base (Effusion / Collapse)">Diminished / Absent at Right base</option>
                            <option value="Diminished / Absent breath sounds at Left base">Diminished / Absent at Left base</option>
                            <option value="Absent breath sounds unilaterally (Pneumothorax / Massive effusion)">Absent breath sounds unilaterally</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Wheeze / Rhonchi</label>
                        <select class="zrx-exam-select" data-pulmo-field="wheeze">
                            <option value="No wheeze or rhonchi audible">No wheeze or rhonchi (Clear)</option>
                            <option value="Bilateral widespread polyphonic expiratory wheeze (Asthma / COPD)">Bilateral polyphonic expiratory wheeze</option>
                            <option value="Localized monophonic wheeze (Fixed bronchial obstruction / Foreign body)">Localized monophonic wheeze</option>
                            <option value="Inspiratory and expiratory wheeze (Severe acute bronchospasm)">Inspiratory &amp; expiratory wheeze</option>
                            <option value="Inspiratory stridor audible without stethoscope (Upper airway obstruction)">Inspiratory stridor (Laryngeal/Tracheal)</option>
                        </select>
                    </div>
                </div>

                <!-- Crackles & Voice Transmission Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">CRACKLES</span>
                        <span>Crackles, Rub &amp; Vocal Resonance</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Crackles / Crepitations</label>
                        <select class="zrx-exam-select" data-pulmo-field="crackles">
                            <option value="No crackles or crepitations audible">No crackles (Clear)</option>
                            <option value="Fine end-inspiratory crackles ('Velcro' type - Interstitial lung disease / Fibrosis)">Fine end-inspiratory 'Velcro' crackles</option>
                            <option value="Basal fine inspiratory crackles (Early left ventricular heart failure)">Basal fine crackles (Heart failure)</option>
                            <option value="Coarse pan-inspiratory crackles (Bronchiectasis / Copious secretions)">Coarse crackles (Bronchiectasis / Secretions)</option>
                            <option value="Localized coarse crackles over Right lower lobe (Pneumonia)">Localized crackles (Right lower lobe)</option>
                            <option value="Localized coarse crackles over Left lower lobe (Pneumonia)">Localized crackles (Left lower lobe)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Pleural Rub &amp; Vocal Resonance</label>
                        <select class="zrx-exam-select" data-pulmo-field="vocal-resonance">
                            <option value="Normal vocal resonance bilaterally, no pleural friction rub">Normal vocal resonance, no rub</option>
                            <option value="Pleural friction rub audible (Superficial grating sound - Pleurisy)">Pleural friction rub audible (Pleurisy)</option>
                            <option value="Increased vocal resonance & Whispering Pectoriloquy positive (Consolidation)">Whispering pectoriloquy +ve (Consolidation)</option>
                            <option value="Aegophony positive ('E' to 'A' nasal change over upper border of effusion)">Aegophony positive (Effusion border)</option>
                            <option value="Decreased / Absent vocal resonance (Effusion / Pneumothorax)">Decreased / Absent vocal resonance</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Inspection, Trachea & Percussion -->
        <div class="zrx-pulmo-pane" id="zrx-pulmo-pane-inspection" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Inspection & Trachea Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">INSPECTION</span>
                        <span>Trachea &amp; Chest Wall Dynamics</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Tracheal Position &amp; Cricosternal Distance</label>
                        <select class="zrx-exam-select" data-pulmo-field="trachea">
                            <option value="Trachea central, cricosternal distance normal (>3 fingers)">Trachea central, normal cricosternal distance</option>
                            <option value="Trachea deviated to the Right (Right upper lobe collapse / Fibrosis)">Trachea deviated to Right (Pulled)</option>
                            <option value="Trachea deviated to the Left (Right tension pneumothorax / Massive effusion)">Trachea deviated to Left (Pushed)</option>
                            <option value="Reduced cricosternal distance (<2 fingerbreadths - Hyperinflation in COPD)">Reduced cricosternal distance (&lt;2 fingers)</option>
                            <option value="Tracheal tug present on inspiration">Tracheal tug present</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Chest Shape &amp; Respiratory Distress</label>
                        <select class="zrx-exam-select" data-pulmo-field="chest-shape">
                            <option value="Elliptical chest shape, symmetrical expansion (>4 cm), no distress">Normal chest, symmetrical expansion (>4 cm)</option>
                            <option value="Barrel-shaped chest (Increased AP diameter, horizontal ribs - COPD)">Barrel-shaped chest (COPD)</option>
                            <option value="Active intercostal indrawing & supraclavicular retractions">Intercostal indrawing &amp; Retractions</option>
                            <option value="Sternocleidomastoid & scalene accessory muscle use">Accessory muscle use prominent</option>
                            <option value="Paradoxical abdominal motion (Diaphragmatic fatigue / Failure)">Paradoxical abdominal breathing</option>
                            <option value="Kyphoscoliosis / Pectus excavatum deformity">Thoracic cage deformity (Kyphoscoliosis)</option>
                        </select>
                    </div>
                </div>

                <!-- Percussion & Tactile Fremitus Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">PERCUSSION</span>
                        <span>Percussion Note &amp; Tactile Fremitus</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Percussion Note Distribution</label>
                        <select class="zrx-exam-select" data-pulmo-field="percussion">
                            <option value="Resonant throughout all symmetrical lung zones bilaterally">Resonant throughout (Normal)</option>
                            <option value="Stony dull percussion note at Right lung base (Pleural effusion)">Stony dull at Right base (Effusion)</option>
                            <option value="Stony dull percussion note at Left lung base (Pleural effusion)">Stony dull at Left base (Effusion)</option>
                            <option value="Dull percussion note over localized lobe (Consolidation / Collapse)">Dull over localized lobe (Pneumonia)</option>
                            <option value="Hyper-resonant percussion note bilaterally (Emphysema / Hyperinflation)">Hyper-resonant bilaterally (Emphysema)</option>
                            <option value="Hyper-resonant note unilaterally with loss of cardiac/liver dullness (Pneumothorax)">Hyper-resonant unilaterally (Pneumothorax)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Tactile Vocal Fremitus (TVF)</label>
                        <select class="zrx-exam-select" data-pulmo-field="tvf">
                            <option value="Normal tactile vocal fremitus symmetrical bilaterally">Normal symmetrical TVF</option>
                            <option value="Increased TVF over area of consolidation">Increased TVF (Consolidation)</option>
                            <option value="Decreased / Absent TVF over effusion or pneumothorax">Decreased / Absent TVF (Effusion/PTX)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: SpO2, PEFR & Severity Staging -->
        <div class="zrx-pulmo-pane" id="zrx-pulmo-pane-metrics" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Oxygenation & PEFR Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">OXYGEN &amp; PEFR</span>
                        <span>SpO2, Delivery Mode &amp; Flow Rate</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">SpO2 &amp; Supplemental Oxygen</label>
                        <select class="zrx-exam-select" data-pulmo-field="spo2-mode">
                            <option value="SpO2 ≥96% on ambient Room Air">SpO2 ≥96% on Room Air (Normal)</option>
                            <option value="SpO2 92–95% on ambient Room Air (Mild hypoxemia)">SpO2 92–95% on Room Air</option>
                            <option value="SpO2 88–92% (Target range for hypercapnic COPD on controlled O2)">SpO2 88–92% (Target COPD range)</option>
                            <option value="SpO2 <90% on ambient Room Air (Severe hypoxemia)">SpO2 &lt;90% on Room Air (Severe)</option>
                            <option value="SpO2 maintained >95% via Nasal Cannula (2–4 L/min)">On Nasal Cannula (2–4 L/min)</option>
                            <option value="On Venturi Mask (28%–35% FiO2)">On Venturi Mask (Controlled FiO2)</option>
                            <option value="On Non-Rebreather Mask (10–15 L/min NRBM)">On Non-Rebreather Mask (NRBM)</option>
                            <option value="Non-invasive ventilation (BiPAP / CPAP support in situ)">Non-invasive ventilation (BiPAP/CPAP)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Peak Expiratory Flow Rate (PEFR)</label>
                        <select class="zrx-exam-select" data-pulmo-field="pefr">
                            <option value="PEFR normal (≥80% of predicted / personal best - Green Zone)">Normal PEFR ≥80% (Green Zone)</option>
                            <option value="PEFR 60–79% of predicted (Moderate airflow obstruction - Yellow Zone)">PEFR 60–79% (Yellow Zone)</option>
                            <option value="PEFR <60% of predicted (Severe airflow obstruction - Red Zone)">PEFR &lt;60% (Red Zone / Emergency)</option>
                            <option value="Patient unable to perform PEFR due to acute severe distress">Unable to perform (Severe distress)</option>
                        </select>
                    </div>
                </div>

                <!-- Guidelines & Severity Scores Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">STAGING</span>
                        <span>GINA, GOLD &amp; CURB-65</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Asthma / COPD Clinical Stratification</label>
                        <select class="zrx-exam-select" data-pulmo-field="guideline-stage">
                            <option value="GINA: Well-controlled bronchial asthma">GINA: Well-controlled Asthma</option>
                            <option value="GINA: Partly controlled asthma">GINA: Partly controlled Asthma</option>
                            <option value="GINA: Uncontrolled severe asthma exacerbation">GINA: Uncontrolled Exacerbation</option>
                            <option value="GOLD COPD: Group A (Low risk, fewer symptoms)">GOLD COPD: Group A (Low risk)</option>
                            <option value="GOLD COPD: Group B (Low risk, more symptoms)">GOLD COPD: Group B (Symptomatic)</option>
                            <option value="GOLD COPD: Group E (Exacerbation prone, high risk)">GOLD COPD: Group E (Frequent exacerbations)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Pneumonia CURB-65 Severity Score</label>
                        <select class="zrx-exam-select" data-pulmo-field="curb65">
                            <option value="CURB-65 Score 0–1 (Low mortality risk, suitable for outpatient management)">CURB-65: 0–1 (Outpatient care)</option>
                            <option value="CURB-65 Score 2 (Intermediate risk, consider short hospital admission)">CURB-65: 2 (Inpatient admission)</option>
                            <option value="CURB-65 Score 3–5 (High mortality risk, urgent hospital/ICU admission)">CURB-65: 3–5 (Severe / ICU admission)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Sputum Production &amp; Character</label>
                        <select class="zrx-exam-select" data-pulmo-field="sputum">
                            <option value="Dry cough / Non-productive">Dry cough / Non-productive</option>
                            <option value="Scant clear / Mucoid sputum">Scant clear / Mucoid</option>
                            <option value="Copious purulent yellow/green sputum (Bacterial infection)">Copious purulent (Bacterial)</option>
                            <option value="Rust-colored sputum (Pneumococcal pneumonia)">Rust-colored (Pneumococcal)</option>
                            <option value="Blood-streaked sputum / Hemoptysis">Blood-streaked hemoptysis</option>
                            <option value="Copious foul-smelling three-layered sputum (Bronchiectasis / Lung abscess)">Foul-smelling layered (Bronchiectasis)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Scenarios:</span>
            <button type="button" class="zrx-exam-chip" data-pulmo-preset="asthma">Acute Asthma (Expiratory Wheeze &amp; Yellow PEFR)</button>
            <button type="button" class="zrx-exam-chip" data-pulmo-preset="copd">COPD Exacerbation (Barrel Chest &amp; Prolonged Expiration)</button>
            <button type="button" class="zrx-exam-chip" data-pulmo-preset="pneumonia">Lobar Pneumonia (Bronchial Breathing &amp; Dull Percussion)</button>
            <button type="button" class="zrx-exam-chip" data-pulmo-preset="effusion">Pleural Effusion (Stony Dull &amp; Absent Breath Sounds)</button>
            <button type="button" class="zrx-exam-chip" data-pulmo-preset="fibrosis">Pulmonary Fibrosis (Velcro End-Inspiratory Crackles)</button>
            <button type="button" class="zrx-exam-chip" data-pulmo-preset="normal-pulmo">Normal Chest Examination</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="pulmo-findings" class="zrx-exam-lbl">Pulmonology &amp; Respiratory Examination Summary</label>
            <textarea id="pulmo-findings" class="zrx-exam-textarea" rows="3" placeholder="Respiratory findings, auscultation, percussion, SpO2, and pulmonology formulation..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
