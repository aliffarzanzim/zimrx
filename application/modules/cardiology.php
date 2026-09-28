<?php
declare(strict_types=1);

/**
 * ZimRx - Cardiology & Hemodynamic Assessment Module
 * NYHA Functional Class (I-IV), Killip classification, JVP, Apex beat character,
 * S1/S2 and Levine murmur grading (1-6) with radiation, Peripheral pulses & Pitting edema (1-4+),
 * 12-lead ECG findings, and Anginal chest pain formulation.
 */
?>
<div class="pc-wrapper zrx-exam-wrapper" id="cardio-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('heart', 14) ?>
            <span>Cardiology &amp; Hemodynamic Assessment</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-cardio" title="Pre-fill normal cardiovascular findings">Normal Cardio</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-cardio" title="Clear all cardiology fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-mode Selector for Cardiology Components -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-cardio-tab="hemo">
                <?= zrx_icon('activity', 13) ?> 1. Heart Failure &amp; JVP
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-cardio-tab="auscultation">
                <?= zrx_icon('ear', 13) ?> 2. Sounds &amp; Murmurs
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-cardio-tab="pulses">
                <?= zrx_icon('droplet', 13) ?> 3. Pulses &amp; Edema
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-cardio-tab="ecg">
                <?= zrx_icon('zap', 13) ?> 4. Rhythm &amp; ECG
            </button>
        </div>

        <!-- Section 1: Heart Failure & JVP -->
        <div class="zrx-cardio-pane active" id="zrx-cardio-pane-hemo">
            <div class="zrx-exam-dual-grid">
                <!-- Functional Classification Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">HEART FAILURE</span>
                        <span>NYHA &amp; Killip Classification</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">NYHA Functional Class (Heart Failure)</label>
                        <select class="zrx-exam-select" data-cardio-field="nyha-class">
                            <option value="NYHA Class I (No limitation of ordinary physical activity)">NYHA Class I (No limitation)</option>
                            <option value="NYHA Class II (Slight limitation, ordinary activity causes fatigue/dyspnea)">NYHA Class II (Slight limitation)</option>
                            <option value="NYHA Class III (Marked limitation, comfortable only at rest)">NYHA Class III (Marked limitation)</option>
                            <option value="NYHA Class IV (Unable to carry on activity, symptoms at rest)">NYHA Class IV (Severe / Symptoms at rest)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Killip Classification (Acute Myocardial Infarction)</label>
                        <select class="zrx-exam-select" data-cardio-field="killip-class">
                            <option value="Killip Class I (No clinical signs of heart failure)">Killip I: No heart failure</option>
                            <option value="Killip Class II (Rales <50% lung base, S3 gallop)">Killip II: Mild-moderate HF / S3</option>
                            <option value="Killip Class III (Frank pulmonary edema >50% lung fields)">Killip III: Acute pulmonary edema</option>
                            <option value="Killip Class IV (Cardiogenic shock, SBP <90 mmHg)">Killip IV: Cardiogenic shock</option>
                        </select>
                    </div>
                </div>

                <!-- JVP & Apex Beat Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">VENOUS &amp; APEX</span>
                        <span>JVP &amp; Precordial Palpation</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Jugular Venous Pressure (JVP)</label>
                        <select class="zrx-exam-select" data-cardio-field="jvp">
                            <option value="JVP not elevated (<3 cm above sternal angle at 45°)">JVP not elevated (Normal)</option>
                            <option value="Elevated JVP (4 cm above sternal angle)">Elevated JVP (4 cm above angle)</option>
                            <option value="Markedly elevated JVP (6 cm) with prominent 'v' wave (TR)">Markedly elevated (6 cm) with 'v' wave</option>
                            <option value="Hepatojugular reflux positive (Kussmaul sign positive)">Hepatojugular reflux positive</option>
                            <option value="Cannon 'a' waves visible (Complete heart block / AV dissociation)">Cannon 'a' waves visible</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Apex Beat Position &amp; Character</label>
                        <select class="zrx-exam-select" data-cardio-field="apex-beat">
                            <option value="Normal location (Left 5th ICS in midclavicular line), tapping non-displaced">Left 5th ICS MCL, tapping (Normal)</option>
                            <option value="Displaced downwards and laterally to 6th ICS AAL (Left ventricular dilatation)">Displaced to 6th ICS AAL (LV dilatation)</option>
                            <option value="Heaving apex beat (Left ventricular pressure overload / AS / HTN)">Heaving apex beat (Pressure overload)</option>
                            <option value="Hyperdynamic apex beat (Volume overload / AR / MR)">Hyperdynamic apex beat (Volume overload)</option>
                            <option value="Tapping apex beat with palpable S1 (Mitral stenosis)">Tapping apex beat (Mitral stenosis)</option>
                            <option value="Impalpable apex beat (Obesity / COPD / Pericardial effusion)">Impalpable apex beat</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Heart Sounds & Murmurs -->
        <div class="zrx-cardio-pane" id="zrx-cardio-pane-auscultation" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Heart Sounds & Added Sounds -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">SOUNDS</span>
                        <span>S1, S2 &amp; Added Heart Sounds</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Heart Sounds (S1, S2 &amp; Gallops)</label>
                        <select class="zrx-exam-select" data-cardio-field="heart-sounds">
                            <option value="S1 and S2 audible, normal intensity, no added sounds">S1 + S2 normal, no added sounds</option>
                            <option value="S3 ventricular gallop audible (Volume overload / LV failure)">S3 gallop audible (Heart failure)</option>
                            <option value="S4 atrial gallop audible (Hypertension / Non-compliant LV)">S4 gallop audible (Hypertension)</option>
                            <option value="Summation gallop present (Tachycardia with S3 + S4)">Summation gallop present</option>
                            <option value="Loud P2 with wide splitting of S2 (Pulmonary hypertension)">Loud P2 (Pulmonary hypertension)</option>
                            <option value="Opening snap heard in early diastole (Mitral stenosis)">Opening snap heard (Mitral stenosis)</option>
                            <option value="Pericardial friction rub audible (Acute pericarditis)">Pericardial friction rub audible</option>
                            <option value="Muffled / Distant heart sounds (Pericardial effusion / Tamponade)">Muffled heart sounds (Effusion)</option>
                        </select>
                    </div>
                </div>

                <!-- Murmurs & Grading -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">MURMURS</span>
                        <span>Murmur Type, Grade &amp; Radiation</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Audible Murmur Description</label>
                        <select class="zrx-exam-select" data-cardio-field="murmur-desc">
                            <option value="No heart murmur audible in any precordial area">No murmur audible (Normal)</option>
                            <option value="Mitral area pansystolic murmur (Mitral regurgitation)">Mitral pansystolic murmur (MR)</option>
                            <option value="Mitral area low-pitched mid-diastolic rumble with presystolic accentuation (Mitral stenosis)">Mitral mid-diastolic rumble (MS)</option>
                            <option value="Aortic area harsh ejection systolic murmur (Aortic stenosis)">Aortic ejection systolic (AS)</option>
                            <option value="Aortic / Erb point early diastolic decrescendo murmur (Aortic regurgitation)">Early diastolic decrescendo (AR)</option>
                            <option value="Tricuspid area pansystolic murmur increasing with inspiration (Carvallo sign - TR)">Tricuspid pansystolic (TR)</option>
                            <option value="Continuous 'machinery' murmur at left infraclavicular area (PDA)">Continuous machinery murmur (PDA)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Levine Murmur Grade (1 to 6) &amp; Radiation</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" data-cardio-field="murmur-grade">
                                <option value="None">No murmur</option>
                                <option value="Grade 1/6 (Very faint, tuned in)">Grade 1/6 (Faint)</option>
                                <option value="Grade 2/6 (Faint, easily heard)">Grade 2/6 (Soft)</option>
                                <option value="Grade 3/6 (Moderate, no thrill)">Grade 3/6 (Moderate)</option>
                                <option value="Grade 4/6 (Loud with palpable thrill)">Grade 4/6 (Thrill +ve)</option>
                                <option value="Grade 5/6 (Very loud with thrill)">Grade 5/6 (Very loud)</option>
                                <option value="Grade 6/6 (Heard off chest wall)">Grade 6/6 (Loudest)</option>
                            </select>
                            <select class="zrx-exam-select" data-cardio-field="murmur-radiation">
                                <option value="No radiation">No radiation</option>
                                <option value="Radiates to Left Axilla (MR)">To Left Axilla (MR)</option>
                                <option value="Radiates to Carotids bilaterally (AS)">To Carotids (AS)</option>
                                <option value="Radiates along Left Sternal Border (AR)">Left Sternal Border (AR)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Peripheral Pulses & Edema -->
        <div class="zrx-cardio-pane" id="zrx-cardio-pane-pulses" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Peripheral Pulses Palpation -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">PULSES</span>
                        <span>Peripheral Vascular Examination</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Peripheral Pulses &amp; Symmetry</label>
                        <select class="zrx-exam-select" data-cardio-field="peripheral-pulses">
                            <option value="All peripheral pulses palpable, symmetrical, normal volume (Radials, Femorals, DP, PT)">All peripheral pulses palpable &amp; symmetrical</option>
                            <option value="Weak / Thready peripheral pulses (Low cardiac output / Hypovolemia)">Weak / Thready pulses (Shock)</option>
                            <option value="Water-hammer / Collapsing pulse (Aortic regurgitation / Hyperdynamic)">Water-hammer / Collapsing pulse (AR)</option>
                            <option value="Pulsus bisferiens (Aortic stenosis with regurgitation)">Pulsus bisferiens (AS + AR)</option>
                            <option value="Radio-femoral delay present (Coarctation of Aorta)">Radio-femoral delay (Coarctation)</option>
                            <option value="Absent / Diminished dorsalis pedis and posterior tibial pulses (PAD)">Absent DP / PT pulses (PAD)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Capillary Refill Time (CRT)</label>
                        <select class="zrx-exam-select" data-cardio-field="crt">
                            <option value="Normal (<2 seconds)">Normal CRT (&lt;2 seconds)</option>
                            <option value="Prolonged (2 to 4 seconds - Impaired perfusion)">Prolonged (2–4 seconds)</option>
                            <option value="Severely prolonged (>4 seconds - Cardiogenic shock)">Severely prolonged (>4 seconds)</option>
                        </select>
                    </div>
                </div>

                <!-- Peripheral Edema Grading -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">EDEMA</span>
                        <span>Pitting Edema Staging (1+ to 4+)</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Peripheral Edema Grading</label>
                        <select class="zrx-exam-select" data-cardio-field="edema-grade">
                            <option value="0 (No peripheral edema)">0: No peripheral edema</option>
                            <option value="1+ Pitting edema (Slight 2mm depression, disappears rapidly)">1+ Mild pitting (2mm, ankle)</option>
                            <option value="2+ Pitting edema (Moderate 4mm depression, rebounds in 10-15s)">2+ Moderate pitting (4mm, pretibial)</option>
                            <option value="3+ Pitting edema (Deep 6mm depression, lasts >1 minute, obvious swelling)">3+ Deep pitting (6mm, knee level)</option>
                            <option value="4+ Pitting edema (Very deep 8mm depression, lasts 2-5 minutes, severe distortion)">4+ Severe pitting / Anasarca (8mm)</option>
                            <option value="Non-pitting brawny edema (Lymphedema / Chronic venous insufficiency)">Non-pitting brawny edema</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Sacral &amp; Dependent Edema</label>
                        <select class="zrx-exam-select" data-cardio-field="sacral-edema">
                            <option value="Absent sacral edema">Absent sacral edema (Normal)</option>
                            <option value="Sacral pitting edema present (Bedridden congestive heart failure)">Sacral pitting edema present (Bedridden)</option>
                            <option value="Ascites with shifting dullness and fluid thrill">Ascites present (Cardiac cirrhosis / Cor pulmonale)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Rhythm, ECG & Chest Pain -->
        <div class="zrx-cardio-pane" id="zrx-cardio-pane-ecg" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Cardiac Rhythm & Pulse Character -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">RHYTHM</span>
                        <span>Cardiac Rhythm &amp; ECG Findings</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Cardiac Rhythm Assessment</label>
                        <select class="zrx-exam-select" data-cardio-field="rhythm">
                            <option value="Normal regular sinus rhythm (60–100 bpm)">Normal regular sinus rhythm (60–100 bpm)</option>
                            <option value="Irregularly irregular rhythm with pulse deficit (Atrial Fibrillation)">Irregularly irregular (Atrial Fibrillation)</option>
                            <option value="Sinus bradycardia (<60 bpm)">Sinus bradycardia (&lt;60 bpm)</option>
                            <option value="Sinus tachycardia (>100 bpm)">Sinus tachycardia (>100 bpm)</option>
                            <option value="Frequent premature ventricular contractions (PVCs / Extrasystoles)">Frequent PVCs / Ectopics</option>
                            <option value="Pulsus alternans present (Severe left ventricular failure)">Pulsus alternans (Severe LV failure)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">12-Lead ECG Summary</label>
                        <select class="zrx-exam-select" data-cardio-field="ecg-summary">
                            <option value="Normal sinus rhythm, normal axis, no acute ST-T wave changes">Normal sinus rhythm, no ST changes</option>
                            <option value="ST-Elevation Myocardial Infarction (STEMI: Anteroseptal V1-V4)">STEMI: Anteroseptal (V1–V4)</option>
                            <option value="ST-Elevation Myocardial Infarction (STEMI: Inferior II, III, aVF)">STEMI: Inferior (II, III, aVF)</option>
                            <option value="Non-ST Elevation ACS (NSTEMI / Unstable Angina: ST depression & T inversion)">NSTEMI / T-inversion / ST depression</option>
                            <option value="Left Bundle Branch Block (LBBB: Broad notched R waves I, aVL, V5-V6)">New Left Bundle Branch Block (LBBB)</option>
                            <option value="Right Bundle Branch Block (RBBB: rsR' in V1-V2)">Right Bundle Branch Block (RBBB)</option>
                            <option value="Atrial Fibrillation with rapid ventricular response (AF RVR)">Atrial Fibrillation (AF RVR)</option>
                            <option value="Left Ventricular Hypertrophy (LVH: Sokolow-Lyon criteria positive)">Left Ventricular Hypertrophy (LVH)</option>
                        </select>
                    </div>
                </div>

                <!-- Chest Pain Characterization -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">CHEST PAIN</span>
                        <span>Anginal Symptoms &amp; Red Flags</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Chest Pain Characteristics</label>
                        <select class="zrx-exam-select" data-cardio-field="chest-pain">
                            <option value="No chest pain or angina reported">No chest pain reported</option>
                            <option value="Typical Angina Pectoris (Substernal crushing pain, radiates to left arm/jaw, exertional, relieved by GTN/rest)">Typical Angina (Crushing, radiates to arm/jaw)</option>
                            <option value="Atypical chest pain (Sharp, localized, non-exertional)">Atypical / Non-cardiac chest pain</option>
                            <option value="Pericarditic chest pain (Pleuritic, sharp, relieved by sitting forward)">Pericarditic pain (Relieved sitting forward)</option>
                            <option value="Sudden tearing chest pain radiating to interscapular back (Suspected Aortic Dissection)">Tearing pain to back (Aortic Dissection)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Scenarios:</span>
            <button type="button" class="zrx-exam-chip" data-cardio-preset="stemi">Acute STEMI (Crushing Retrosternal Angina &amp; ST-Elevation)</button>
            <button type="button" class="zrx-exam-chip" data-cardio-preset="chf">Congestive Heart Failure (NYHA III, S3 Gallop, JVP &amp; 3+ Edema)</button>
            <button type="button" class="zrx-exam-chip" data-cardio-preset="as">Aortic Stenosis (Harsh Murmur Grade 4/6 &amp; Heaving Apex)</button>
            <button type="button" class="zrx-exam-chip" data-cardio-preset="mr">Mitral Regurgitation (Pansystolic Murmur to Axilla)</button>
            <button type="button" class="zrx-exam-chip" data-cardio-preset="afib">Atrial Fibrillation (Irregularly Irregular Rhythm)</button>
            <button type="button" class="zrx-exam-chip" data-cardio-preset="normal-cardio">Normal Cardiovascular Exam</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="cardio-findings" class="zrx-exam-lbl">Cardiology &amp; Hemodynamic Assessment Summary</label>
            <textarea id="cardio-findings" class="zrx-exam-textarea" rows="3" placeholder="Cardiovascular findings, NYHA classification, murmurs, pulses, and cardiac formulation..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
