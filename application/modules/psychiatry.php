<?php
declare(strict_types=1);

/**
 * ZimRx - Psychiatry Specialized Examination Module
 * Comprehensive Mental State Examination (MSE), Mood & Affect, Thought Form/Content,
 * Perception/Hallucinations, Suicide & Violence Risk Assessment, and Insight Grading (1-6).
 */
?>
<div class="pc-wrapper zrx-exam-wrapper" id="psychiatry-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('brain', 14) ?>
            <span>Psychiatry &amp; Mental State Exam (MSE)</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-psych" title="Pre-fill normal unremarkable MSE findings">Normal MSE</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-psych" title="Clear all psychiatry fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-mode Selector for MSE Components -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-psych-tab="general">
                <?= zrx_icon('user', 13) ?> 1. Appearance, Speech &amp; Mood
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-psych-tab="thought">
                <?= zrx_icon('activity', 13) ?> 2. Thought, Perception &amp; Cognition
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-psych-tab="risk">
                <?= zrx_icon('shield', 13) ?> 3. Risk &amp; Insight Grading
            </button>
        </div>

        <!-- Section 1: Appearance, Behavior, Speech, Mood & Affect -->
        <div class="zrx-psych-pane active" id="zrx-psych-pane-general">
            <div class="zrx-exam-dual-grid">
                <!-- Appearance & Behaviour Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">A&amp;B</span>
                        <span>Appearance &amp; Behaviour</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Grooming &amp; Dress</label>
                        <select class="zrx-exam-select" data-psych-field="grooming">
                            <option value="Well-groomed, dressed appropriately">Well-groomed, appropriate</option>
                            <option value="Disheveled, unkempt, self-neglect">Disheveled / Unkempt (Neglect)</option>
                            <option value="Eccentric / Bizarre attire">Eccentric / Flamboyant</option>
                            <option value="Poor hygiene, malodorous">Poor hygiene / Malodorous</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Eye Contact &amp; Rapport</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" data-psych-field="eye-contact">
                                <option value="Appropriate / Normal">Appropriate Eye Contact</option>
                                <option value="Poor / Downcast / Avoidant">Poor / Downcast (Avoidant)</option>
                                <option value="Intense / Staring / Intrusive">Intense / Staring</option>
                            </select>
                            <select class="zrx-exam-select" data-psych-field="rapport">
                                <option value="Cooperative, easily established">Cooperative</option>
                                <option value="Guarded / Suspicious">Guarded / Suspicious</option>
                                <option value="Hostile / Irritable">Hostile / Irritable</option>
                                <option value="Over-familiar / Disinhibited">Over-familiar</option>
                            </select>
                        </div>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Psychomotor Activity</label>
                        <select class="zrx-exam-select" data-psych-field="psychomotor">
                            <option value="Normal psychomotor activity">Normal psychomotor activity</option>
                            <option value="Psychomotor Agitation (Restless, pacing)">Psychomotor Agitation (Restless)</option>
                            <option value="Psychomotor Retardation (Slowed movements)">Psychomotor Retardation (Slowed)</option>
                            <option value="Tremors / Akathisia / Tics present">Tremors / Akathisia / Tics</option>
                            <option value="Catatonic stupor / Waxy flexibility">Catatonia / Posturing</option>
                        </select>
                    </div>
                </div>

                <!-- Speech, Mood & Affect Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">M&amp;A</span>
                        <span>Speech, Mood &amp; Affect</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Speech Rate, Volume &amp; Coherence</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" data-psych-field="speech-rate">
                                <option value="Normal rate, rhythm, and volume">Normal rate &amp; tone</option>
                                <option value="Pressured, rapid, loud (Mania)">Pressured &amp; Rapid (Mania)</option>
                                <option value="Slowed, low volume, increased latency">Slowed / Low volume / Hesitant</option>
                                <option value="Poverty of speech (Alogia) / Monotonous">Poverty of speech (Alogia)</option>
                                <option value="Mute">Mute</option>
                            </select>
                            <select class="zrx-exam-select" data-psych-field="speech-coherence">
                                <option value="Coherent & relevant">Coherent &amp; Relevant</option>
                                <option value="Incoherent / Word salad">Incoherent</option>
                                <option value="Perseveration present">Perseveration present</option>
                            </select>
                        </div>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Subjective Mood (Patient's Words)</label>
                        <select class="zrx-exam-select" data-psych-field="mood">
                            <option value="Euthymic (Normal, balanced mood)">Euthymic (Normal)</option>
                            <option value="Depressed / Low / Sad / Hopeless">Depressed / Low / Despondent</option>
                            <option value="Anxious / Apprehensive / Tense">Anxious / Apprehensive</option>
                            <option value="Euphoric / Elated / Expansive">Euphoric / Elated (Manic)</option>
                            <option value="Irritable / Dysphoric">Irritable / Dysphoric</option>
                            <option value="Apathetic / Anhedonic">Apathetic / Anhedonic</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Objective Affect (Observed Expression)</label>
                        <select class="zrx-exam-select" data-psych-field="affect">
                            <option value="Reactive & congruent to mood">Reactive &amp; Congruent (Normal)</option>
                            <option value="Blunted / Flat affect (Restricted range)">Blunted / Flat affect</option>
                            <option value="Constricted affect">Constricted affect</option>
                            <option value="Labile affect (Rapid mood swings)">Labile affect (Fluctuating)</option>
                            <option value="Incongruent to thought content">Incongruent to thought content</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Thought Form, Content, Perception & Cognition -->
        <div class="zrx-psych-pane" id="zrx-psych-pane-thought" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Thought Process & Delusions -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">THOUGHT</span>
                        <span>Thought Form &amp; Content</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Thought Stream &amp; Form</label>
                        <select class="zrx-exam-select" data-psych-field="thought-form">
                            <option value="Coherent, logical, goal-directed">Goal-directed &amp; Logical (Normal)</option>
                            <option value="Flight of ideas with rhyming/punning">Flight of ideas (Mania)</option>
                            <option value="Tangentiality (Answers stray from topic)">Tangentiality</option>
                            <option value="Circumstantiality (Excessive trivial details)">Circumstantiality</option>
                            <option value="Loosening of associations / Derailment">Derailment / Loosening</option>
                            <option value="Thought blocking (Sudden arrest in train)">Thought blocking</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Thought Content &amp; Delusions</label>
                        <select class="zrx-exam-select" data-psych-field="thought-content">
                            <option value="No delusions or obsessions elicited">No delusions or obsessions (Normal)</option>
                            <option value="Persecutory / Paranoid delusions">Persecutory / Paranoid Delusions</option>
                            <option value="Delusions of Grandeur (Omnipotent powers)">Delusions of Grandeur</option>
                            <option value="Delusions of Reference (Media messages)">Delusions of Reference</option>
                            <option value="Depressive cognitions (Worthlessness, Guilt, Hopelessness)">Depressive Cognitions (Guilt/Hopeless)</option>
                            <option value="Obsessions (Intrusive, ego-dystonic thoughts)">Obsessive Intrusive Thoughts</option>
                            <option value="Somatic delusions / Hypochondriacal">Somatic Delusions</option>
                            <option value="Thought insertion / Withdrawal / Broadcasting">Thought Alienation (Broadcasting)</option>
                        </select>
                    </div>
                </div>

                <!-- Perception & Cognition -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">PERCEPT</span>
                        <span>Perception &amp; Cognition</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Perceptual Disturbances / Hallucinations</label>
                        <select class="zrx-exam-select" data-psych-field="hallucinations">
                            <option value="No hallucinations or illusions">No hallucinations elicited (Normal)</option>
                            <option value="Auditory hallucinations (2nd person addressing)">Auditory Hallucinations (2nd person)</option>
                            <option value="Auditory hallucinations (3rd person running commentary)">Auditory (3rd person commentary)</option>
                            <option value="Auditory hallucinations (Commanding type)">Command Hallucinations (High Risk)</option>
                            <option value="Visual hallucinations">Visual Hallucinations</option>
                            <option value="Tactile / Olfactory hallucinations">Tactile / Olfactory</option>
                            <option value="Depersonalization / Derealization">Depersonalization / Derealization</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Orientation &amp; Concentration</label>
                        <div class="zrx-exam-flex-pair">
                            <select class="zrx-exam-select" data-psych-field="orientation">
                                <option value="Fully oriented to time, place, and person">Oriented (Time, Place, Person)</option>
                                <option value="Disoriented to Time">Disoriented to Time</option>
                                <option value="Disoriented to Time & Place">Disoriented (Time &amp; Place)</option>
                                <option value="Globally disoriented (Delirium/Dementia)">Globally disoriented</option>
                            </select>
                            <select class="zrx-exam-select" data-psych-field="concentration">
                                <option value="Attention & concentration intact">Concentration intact</option>
                                <option value="Easily distractible">Distractible</option>
                                <option value="Significantly impaired concentration">Impaired concentration</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Risk Assessment, Judgment & Insight -->
        <div class="zrx-psych-pane" id="zrx-psych-pane-risk" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Suicide & Safety Risk Assessment -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge" style="background:#fee2e2; color:#b91c1c;">RISK</span>
                        <span>Suicide &amp; Harm Risk Stratification</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Suicide / Self-Harm Risk</label>
                        <select class="zrx-exam-select" data-psych-field="suicide-risk">
                            <option value="No suicidal ideation, intent, or plan (Low Risk)">No suicidal ideation (Low Risk)</option>
                            <option value="Passive death wish without active intent / plan">Passive death wish (No active plan)</option>
                            <option value="Active suicidal ideation without intent or lethal plan">Active SI without intent (Moderate)</option>
                            <option value="Active suicidal ideation with plan and high intent">Active SI with plan (High / Acute Risk)</option>
                            <option value="Recent suicide attempt / Non-suicidal self-injury (NSSI)">Recent attempt / Self-harm</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Violence / Homicidal Risk</label>
                        <select class="zrx-exam-select" data-psych-field="violence-risk">
                            <option value="No violence or homicidal ideation">No violence / Low risk</option>
                            <option value="Expressed verbal threats / Agitation">Verbal aggression / Agitated</option>
                            <option value="High acute risk of physical violence">High acute violence risk</option>
                        </select>
                    </div>
                </div>

                <!-- Insight & Judgment -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">INSIGHT</span>
                        <span>Clinical Insight (Grades 1–6) &amp; Judgment</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Insight Grading (Scale 1 to 6)</label>
                        <select class="zrx-exam-select" data-psych-field="insight-grade">
                            <option value="Grade 6: True emotional insight (full awareness & treatment adherence)">Grade 6: True emotional insight (Full)</option>
                            <option value="Grade 5: Intellectual insight (accepts illness, does not apply to behavior)">Grade 5: Intellectual insight</option>
                            <option value="Grade 4: Awareness of being sick due to psychological cause">Grade 4: Awareness of psychological cause</option>
                            <option value="Grade 3: Awareness of being sick, attributes to physical cause">Grade 3: Attributes to physical illness</option>
                            <option value="Grade 2: Slight awareness of being sick, blames external factors">Grade 2: Blames external factors</option>
                            <option value="Grade 1: Complete denial of mental illness">Grade 1: Complete denial of illness</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Judgment (Social &amp; Test Judgment)</label>
                        <select class="zrx-exam-select" data-psych-field="judgment">
                            <option value="Intact social and personal judgment">Intact judgment (Normal)</option>
                            <option value="Impaired judgment due to psychosis / mania">Impaired judgment (Psychosis/Mania)</option>
                            <option value="Poor impulse control">Poor impulse control</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Scenarios:</span>
            <button type="button" class="zrx-exam-chip" data-psych-preset="mdd">Major Depressive Disorder (MDD)</button>
            <button type="button" class="zrx-exam-chip" data-psych-preset="mania">Bipolar Mania (Pressured speech &amp; Grandeur)</button>
            <button type="button" class="zrx-exam-chip" data-psych-preset="psychosis">Schizophrenia (Paranoid delusions &amp; Hallucinations)</button>
            <button type="button" class="zrx-exam-chip" data-psych-preset="gad">Generalized Anxiety Disorder (Apprehensive)</button>
            <button type="button" class="zrx-exam-chip" data-psych-preset="normal-mse">Unremarkable Mental State (Euthymic)</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="psychiatry-findings" class="zrx-exam-lbl">Mental State Examination (MSE) Summary &amp; Assessment</label>
            <textarea id="psychiatry-findings" class="zrx-exam-textarea" rows="3" placeholder="Mental state findings, risk evaluation, and psychiatric formulation..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
