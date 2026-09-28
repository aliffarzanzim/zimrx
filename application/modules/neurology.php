<?php
declare(strict_types=1);

/**
 * ZimRx - Dedicated Neurology & Stroke Assessment Module
 * Glasgow Coma Scale (GCS total out of 15), Cranial Nerves (I-XII),
 * MRC Motor Power grading (0-5) across 4 limbs, Tone, DTRs, Babinski Plantar, and Cerebellar tests.
 */
?>
<div class="pc-wrapper zrx-exam-wrapper" id="neuro-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('zap', 14) ?>
            <span>Neurology &amp; Stroke Assessment</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-neuro" title="Pre-fill normal neurological findings">Normal Neuro</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-neuro" title="Clear all neurology fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-mode Selector for Neurology Components -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-neuro-tab="gcs">
                <?= zrx_icon('activity', 13) ?> 1. GCS &amp; Consciousness
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-neuro-tab="cranial">
                <?= zrx_icon('eye', 13) ?> 2. Cranial Nerves (I–XII)
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-neuro-tab="motor">
                <?= zrx_icon('user', 13) ?> 3. Motor Power (MRC 0–5) &amp; Tone
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-neuro-tab="reflexes">
                <?= zrx_icon('shield', 13) ?> 4. Reflexes, Sensory &amp; Cerebellar
            </button>
        </div>

        <!-- Section 1: GCS & Consciousness -->
        <div class="zrx-neuro-pane active" id="zrx-neuro-pane-gcs">
            <div class="zrx-exam-dual-grid">
                <!-- GCS Breakdown Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">GCS</span>
                        <span>Glasgow Coma Scale (Total / 15)</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Eye Opening Response (E 1–4)</label>
                        <select class="zrx-exam-select" data-neuro-field="gcs-e">
                            <option value="4">E4: Spontaneous (Normal)</option>
                            <option value="3">E3: To speech / sound</option>
                            <option value="2">E2: To pressure / painful stimuli</option>
                            <option value="1">E1: No eye opening (None)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Verbal Response (V 1–5)</label>
                        <select class="zrx-exam-select" data-neuro-field="gcs-v">
                            <option value="5">V5: Oriented &amp; conversing (Normal)</option>
                            <option value="4">V4: Confused conversation</option>
                            <option value="3">V3: Inappropriate words</option>
                            <option value="2">V2: Incomprehensible sounds</option>
                            <option value="1">V1: No verbal response (None)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Motor Response (M 1–6)</label>
                        <select class="zrx-exam-select" data-neuro-field="gcs-m">
                            <option value="6">M6: Obeys commands (Normal)</option>
                            <option value="5">M5: Localises to pain</option>
                            <option value="4">M4: Normal flexion / Withdrawal to pain</option>
                            <option value="3">M3: Abnormal flexion (Decorticate posturing)</option>
                            <option value="2">M2: Extension response (Decerebrate posturing)</option>
                            <option value="1">M1: No motor response (Flaccid)</option>
                        </select>
                    </div>
                </div>

                <!-- GCS Score Summary & Meningeal Signs Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge" style="background:#dcfce7; color:#166534;">STATUS</span>
                        <span>Level of Consciousness &amp; Meninges</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Calculated GCS &amp; Severity</label>
                        <div class="zrx-exam-flex-pair">
                            <input type="text" class="zrx-exam-input" id="neuro-gcs-total" value="GCS: 15/15 (E4 V5 M6)" readonly style="font-weight:700; background:#f8fafc;">
                            <input type="text" class="zrx-exam-input" id="neuro-gcs-severity" value="Normal / Mild" readonly style="font-weight:700; color:#15803d; background:#f0fdf4;">
                        </div>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Neck Stiffness &amp; Meningeal Irritation</label>
                        <select class="zrx-exam-select" data-neuro-field="meningeal-signs">
                            <option value="Neck supple, Kernig and Brudzinski signs negative">Neck supple, Kernig/Brudzinski negative (Normal)</option>
                            <option value="Nuchal rigidity / Neck stiffness positive (Meningitis / SAH)">Nuchal rigidity / Neck stiffness +ve</option>
                            <option value="Kernig sign positive (Resistance to knee extension)">Kernig sign positive</option>
                            <option value="Brudzinski neck sign positive (Involuntary hip/knee flexion)">Brudzinski sign positive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Cranial Nerves (I-XII) -->
        <div class="zrx-neuro-pane" id="zrx-neuro-pane-cranial" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Visual & Oculomotor (CN II, III, IV, VI) -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">CN II-VI</span>
                        <span>Pupils, Vision &amp; Ocular Movements</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Pupillary Reflexes (CN II, III)</label>
                        <select class="zrx-exam-select" data-neuro-field="pupils">
                            <option value="Bilateral pupils equal, round, and reactive to light & accommodation (PERRLA 3mm)">PERRLA 3mm bilaterally (Normal)</option>
                            <option value="Right pupil dilated and sluggish / fixed (Uncal herniation / 3rd nerve)">Right pupil dilated &amp; fixed</option>
                            <option value="Left pupil dilated and sluggish / fixed">Left pupil dilated &amp; fixed</option>
                            <option value="Bilateral pinpoint pupils (Pontine stroke / Opioid toxicity)">Bilateral pinpoint (Pontine / Opioid)</option>
                            <option value="Relative Afferent Pupillary Defect (RAPD / Marcus Gunn pupil)">RAPD / Marcus Gunn pupil +ve</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Extraocular Movements &amp; Nerves (CN III, IV, VI)</label>
                        <select class="zrx-exam-select" data-neuro-field="eom">
                            <option value="Full extraocular motility, no nystagmus, no diplopia">Full motility, no diplopia (Normal)</option>
                            <option value="Right 6th nerve palsy (Impaired right lateral rectus abduction)">Right 6th nerve palsy (Abduction failure)</option>
                            <option value="Left 6th nerve palsy (Impaired left lateral rectus abduction)">Left 6th nerve palsy</option>
                            <option value="Complete 3rd nerve palsy (Ptosis, eye 'down and out', dilated pupil)">Complete 3rd nerve palsy (Ptosis)</option>
                            <option value="Gaze deviation towards side of cerebral hemisphere lesion">Conjugate gaze deviation</option>
                            <option value="Horizontal gaze-evoked nystagmus present">Horizontal nystagmus</option>
                        </select>
                    </div>
                </div>

                <!-- Facial, Bulbar & Tongue (CN V, VII, IX, X, XII) -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">CN VII-XII</span>
                        <span>Facial, Bulbar &amp; Hypoglossal</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Facial Symmetry (CN VII)</label>
                        <select class="zrx-exam-select" data-neuro-field="facial-nerve">
                            <option value="Facial symmetry preserved, forehead furrowing and eye closure intact">Symmetrical facial movements (Normal)</option>
                            <option value="Right UMN facial palsy (Lower facial droop, forehead spared - Stroke)">Right UMN facial palsy (Forehead spared)</option>
                            <option value="Left UMN facial palsy (Lower facial droop, forehead spared - Stroke)">Left UMN facial palsy (Forehead spared)</option>
                            <option value="Right LMN facial palsy / Bell palsy (Complete hemi-facial weakness including forehead & eye closure)">Right Bell's palsy (Entire half face)</option>
                            <option value="Left LMN facial palsy / Bell palsy (Complete hemi-facial weakness)">Left Bell's palsy (Entire half face)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Bulbar, Palate &amp; Tongue (CN IX, X, XII)</label>
                        <select class="zrx-exam-select" data-neuro-field="bulbar-tongue">
                            <option value="Palate elevates symmetrically, gag intact, tongue midline without fasciculations">Symmetrical palate, tongue midline (Normal)</option>
                            <option value="Dysarthria (Slurred speech) without aphasia">Dysarthria (Slurred speech)</option>
                            <option value="Expressive / Broca aphasia (Non-fluent, comprehension intact)">Broca / Expressive aphasia</option>
                            <option value="Receptive / Wernicke aphasia (Fluent, impaired comprehension)">Wernicke / Receptive aphasia</option>
                            <option value="Tongue deviates to the Right on protrusion (Right CN XII lesion)">Tongue deviates to Right</option>
                            <option value="Tongue deviates to the Left on protrusion (Left CN XII lesion)">Tongue deviates to Left</option>
                            <option value="Impaired gag reflex & dysphagia (Bulbar palsy)">Impaired gag &amp; dysphagia</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Motor Power (MRC 0-5) & Tone -->
        <div class="zrx-neuro-pane" id="zrx-neuro-pane-motor" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Upper Limbs Power & Tone -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">UPPER LIMBS</span>
                        <span>Upper Extremity Power (MRC 0–5)</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Right Upper Limb Power (Shoulder, Elbow, Wrist)</label>
                        <select class="zrx-exam-select" data-neuro-field="rt-upper-power">
                            <option value="Grade 5/5 (Normal power against full resistance)">Grade 5/5: Normal power</option>
                            <option value="Grade 4/5 (Active movement against moderate resistance)">Grade 4/5: Moderate weakness</option>
                            <option value="Grade 3/5 (Active movement against gravity only)">Grade 3/5: Anti-gravity only</option>
                            <option value="Grade 2/5 (Movement with gravity eliminated)">Grade 2/5: Gravity eliminated</option>
                            <option value="Grade 1/5 (Trace flicker of contraction)">Grade 1/5: Trace contraction</option>
                            <option value="Grade 0/5 (Complete flaccid paralysis)">Grade 0/5: Complete paralysis</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Left Upper Limb Power (Shoulder, Elbow, Wrist)</label>
                        <select class="zrx-exam-select" data-neuro-field="lt-upper-power">
                            <option value="Grade 5/5 (Normal power against full resistance)">Grade 5/5: Normal power</option>
                            <option value="Grade 4/5 (Active movement against moderate resistance)">Grade 4/5: Moderate weakness</option>
                            <option value="Grade 3/5 (Active movement against gravity only)">Grade 3/5: Anti-gravity only</option>
                            <option value="Grade 2/5 (Movement with gravity eliminated)">Grade 2/5: Gravity eliminated</option>
                            <option value="Grade 1/5 (Trace flicker of contraction)">Grade 1/5: Trace contraction</option>
                            <option value="Grade 0/5 (Complete flaccid paralysis)">Grade 0/5: Complete paralysis</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Pronator Drift Test</label>
                        <select class="zrx-exam-select" data-neuro-field="pronator-drift">
                            <option value="Negative bilaterally (Arms maintain position)">Negative bilaterally (Normal)</option>
                            <option value="Positive Right pronator drift (Right pyramidal tract weakness)">Right pronator drift +ve (UMN)</option>
                            <option value="Positive Left pronator drift (Left pyramidal tract weakness)">Left pronator drift +ve (UMN)</option>
                        </select>
                    </div>
                </div>

                <!-- Lower Limbs Power & Muscle Tone -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">LOWER LIMBS</span>
                        <span>Lower Extremity Power &amp; Muscle Tone</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Right Lower Limb Power (Hip, Knee, Ankle)</label>
                        <select class="zrx-exam-select" data-neuro-field="rt-lower-power">
                            <option value="Grade 5/5 (Normal power against full resistance)">Grade 5/5: Normal power</option>
                            <option value="Grade 4/5 (Active movement against moderate resistance)">Grade 4/5: Moderate weakness</option>
                            <option value="Grade 3/5 (Active movement against gravity only)">Grade 3/5: Anti-gravity only</option>
                            <option value="Grade 2/5 (Movement with gravity eliminated)">Grade 2/5: Gravity eliminated</option>
                            <option value="Grade 1/5 (Trace flicker of contraction)">Grade 1/5: Trace contraction</option>
                            <option value="Grade 0/5 (Complete flaccid paralysis)">Grade 0/5: Complete paralysis</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Left Lower Limb Power (Hip, Knee, Ankle)</label>
                        <select class="zrx-exam-select" data-neuro-field="lt-lower-power">
                            <option value="Grade 5/5 (Normal power against full resistance)">Grade 5/5: Normal power</option>
                            <option value="Grade 4/5 (Active movement against moderate resistance)">Grade 4/5: Moderate weakness</option>
                            <option value="Grade 3/5 (Active movement against gravity only)">Grade 3/5: Anti-gravity only</option>
                            <option value="Grade 2/5 (Movement with gravity eliminated)">Grade 2/5: Gravity eliminated</option>
                            <option value="Grade 1/5 (Trace flicker of contraction)">Grade 1/5: Trace contraction</option>
                            <option value="Grade 0/5 (Complete flaccid paralysis)">Grade 0/5: Complete paralysis</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Overall Muscle Tone</label>
                        <select class="zrx-exam-select" data-neuro-field="muscle-tone">
                            <option value="Normal tone throughout all 4 extremities">Normal tone throughout (Normal)</option>
                            <option value="Spasticity / Clasp-knife hypertonia (Pyramidal / UMN lesion)">Spasticity / Clasp-knife (UMN)</option>
                            <option value="Cogwheel / Lead-pipe rigidity (Extrapyramidal / Parkinsonism)">Cogwheel rigidity (Parkinsonism)</option>
                            <option value="Flaccidity / Hypotonia (LMN lesion / Acute spinal shock)">Flaccid / Hypotonia (LMN)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Reflexes, Sensory & Cerebellar -->
        <div class="zrx-neuro-pane" id="zrx-neuro-pane-reflexes" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Deep Tendon Reflexes & Plantars -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">REFLEXES</span>
                        <span>DTRs &amp; Plantar Response</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Deep Tendon Reflexes (Biceps, Knee, Ankle)</label>
                        <select class="zrx-exam-select" data-neuro-field="dtr-reflexes">
                            <option value="Normal 2+ brisk bilaterally without clonus">Normal 2+ brisk bilaterally (Normal)</option>
                            <option value="Hyperreflexia 3+ / 4+ with sustained clonus (UMN lesion)">Hyperreflexia 3+/4+ with clonus (UMN)</option>
                            <option value="Asymmetric right hyperreflexia with clonus">Right hyperreflexia &amp; clonus</option>
                            <option value="Asymmetric left hyperreflexia with clonus">Left hyperreflexia &amp; clonus</option>
                            <option value="Hyporeflexia 1+ throughout">Hyporeflexia 1+ (Depressed)</option>
                            <option value="Areflexia 0 throughout (Absent DTRs - LMN / GBS)">Areflexia 0 (Absent - GBS/LMN)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Plantar Reflex (Babinski Sign)</label>
                        <select class="zrx-exam-select" data-neuro-field="babinski-sign">
                            <option value="Bilateral flexor plantar responses (Normal downward)">Bilateral flexor (Normal)</option>
                            <option value="Right extensor plantar response (Babinski positive)">Right extensor (Babinski +ve / UMN)</option>
                            <option value="Left extensor plantar response (Babinski positive)">Left extensor (Babinski +ve / UMN)</option>
                            <option value="Bilateral extensor plantar responses (Bilateral Babinski positive)">Bilateral extensor (Bilateral Babinski +ve)</option>
                            <option value="Equivocal / Mute plantar response">Equivocal / Mute response</option>
                        </select>
                    </div>
                </div>

                <!-- Sensory & Cerebellar Function -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">CEREBELLAR</span>
                        <span>Coordination, Gait &amp; Sensation</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Cerebellar Signs &amp; Coordination</label>
                        <select class="zrx-exam-select" data-neuro-field="cerebellar-signs">
                            <option value="Finger-nose & heel-shin intact, no dysdiadochokinesia, negative Romberg">Coordination intact, Romberg negative (Normal)</option>
                            <option value="Right-sided dysmetria, intention tremor & dysdiadochokinesia">Right cerebellar signs (Dysmetria/Tremor)</option>
                            <option value="Left-sided dysmetria, intention tremor & dysdiadochokinesia">Left cerebellar signs (Dysmetria/Tremor)</option>
                            <option value="Bilateral cerebellar ataxia with wide-based gait">Bilateral cerebellar ataxia</option>
                            <option value="Sensory ataxia (Romberg test positive with eyes closed)">Romberg positive (Sensory ataxia)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Sensory System Examination</label>
                        <select class="zrx-exam-select" data-neuro-field="sensory-exam">
                            <option value="Intact light touch, pinprick, vibration & joint position sense bilaterally">Intact sensation throughout (Normal)</option>
                            <option value="Right-sided hemisensory loss (Pain & temperature)">Right hemisensory loss (Stroke)</option>
                            <option value="Left-sided hemisensory loss (Pain & temperature)">Left hemisensory loss (Stroke)</option>
                            <option value="Stocking-and-glove distal sensory impairment (Peripheral neuropathy)">Stocking-and-glove loss (Neuropathy)</option>
                            <option value="Sensory level at umbilicus (T10 spinal cord level)">Sensory level (Spinal cord)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Scenarios:</span>
            <button type="button" class="zrx-exam-chip" data-neuro-preset="stroke-rt">Acute Left MCA Stroke (Rt Hemiparesis Grade 2/5 &amp; UMN 7th)</button>
            <button type="button" class="zrx-exam-chip" data-neuro-preset="bells-palsy">Bell's Palsy (Right LMN 7th Nerve)</button>
            <button type="button" class="zrx-exam-chip" data-neuro-preset="parkinson">Parkinsonism (Cogwheel Rigidity)</button>
            <button type="button" class="zrx-exam-chip" data-neuro-preset="diabetic-neuro">Peripheral Neuropathy (Glove-Stocking Loss)</button>
            <button type="button" class="zrx-exam-chip" data-neuro-preset="severe-gcs">Severe Head Injury / Coma (GCS 7/15)</button>
            <button type="button" class="zrx-exam-chip" data-neuro-preset="normal-neuro">Normal Neurological Exam</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="neuro-findings" class="zrx-exam-lbl">Neurological Examination &amp; Stroke Assessment Summary</label>
            <textarea id="neuro-findings" class="zrx-exam-textarea" rows="3" placeholder="GCS, cranial nerves, motor power MRC grading, reflexes, and neuro formulation..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
