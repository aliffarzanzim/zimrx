<?php
declare(strict_types=1);

// ENT exam module: bilateral ear (otoscopy/tuning fork), nose/PNS (DNS, turbinates), and throat (Brodsky tonsils).
?>
<div class="pc-wrapper zrx-exam-wrapper" id="ent-exam-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('ear', 14) ?>
            <span>ENT Specialized Examination</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-ent" title="Pre-fill unremarkable bilateral ENT findings">Unremarkable</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-ent" title="Clear all ENT fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-nav tabs for Ear, Nose, Throat -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-ent-tab="ear">
                <?= zrx_icon('ear', 13) ?> 1. Ear (Otoscopy &amp; Tuning Fork)
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-ent-tab="nose">
                <?= zrx_icon('target', 13) ?> 2. Nose &amp; Paranasal Sinuses
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-ent-tab="throat">
                <?= zrx_icon('activity', 13) ?> 3. Throat &amp; Oral Cavity
            </button>
        </div>

        <!-- Section 1: Ear (Bilateral Dual Grid) -->
        <div class="zrx-ent-pane active" id="zrx-ent-pane-ear">
            <div class="zrx-ent-ear-grid">
                <!-- Right Ear (AD) -->
                <div class="zrx-ent-ear-card">
                    <div class="zrx-ent-card-head">
                        <span class="zrx-ent-badge ad">AD</span>
                        <span>Right Ear (Auris Dextra)</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">External Canal &amp; Discharge</label>
                        <select class="zrx-exam-select" data-ent-field="ad-canal">
                            <option value="Normal / Clear">Normal / Clear</option>
                            <option value="Impacted Cerumen (Wax)">Impacted Wax</option>
                            <option value="Otitis Externa (Erythema & Edema)">Otitis Externa</option>
                            <option value="Purulent Discharge present">Purulent Discharge</option>
                            <option value="Mucopurulent non-foul discharge">Mucopurulent (Tubotympanic)</option>
                            <option value="Scanty foul-smelling discharge">Foul Smelling (Atticoantral)</option>
                            <option value="Fungal Debri (Otomycosis)">Otomycosis (Fungal)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Tympanic Membrane (TM)</label>
                        <select class="zrx-exam-select" data-ent-field="ad-tm">
                            <option value="Intact, pearly grey, cone of light +ve">Intact, Pearly Grey (Normal)</option>
                            <option value="Congested & Bulging (AOM)">Congested &amp; Bulging (AOM)</option>
                            <option value="Retracted, prominent malleus handle">Retracted (OME)</option>
                            <option value="Central perforation (Pars Tensa, Safe CSOM)">Central Perforation (Safe CSOM)</option>
                            <option value="Subtotal perforation">Subtotal Perforation</option>
                            <option value="Attic / Marginal perforation with Cholesteatoma">Attic / Marginal (Unsafe CSOM)</option>
                            <option value="Tympanosclerosis patches">Tympanosclerosis</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Rinne Test (512 Hz)</label>
                        <select class="zrx-exam-select" data-ent-field="ad-rinne">
                            <option value="Rinne Positive (AC > BC, Normal/SNHL)">Positive (AC > BC, Normal)</option>
                            <option value="Rinne Negative (BC > AC, Conductive Loss)">Negative (BC > AC, Conductive)</option>
                            <option value="Reduced Positive (Sensori-neural)">Reduced Positive (SNHL)</option>
                        </select>
                    </div>
                </div>

                <!-- Left Ear (AS) -->
                <div class="zrx-ent-ear-card">
                    <div class="zrx-ent-card-head">
                        <span class="zrx-ent-badge as">AS</span>
                        <span>Left Ear (Auris Sinistra)</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">External Canal &amp; Discharge</label>
                        <select class="zrx-exam-select" data-ent-field="as-canal">
                            <option value="Normal / Clear">Normal / Clear</option>
                            <option value="Impacted Cerumen (Wax)">Impacted Wax</option>
                            <option value="Otitis Externa (Erythema & Edema)">Otitis Externa</option>
                            <option value="Purulent Discharge present">Purulent Discharge</option>
                            <option value="Mucopurulent non-foul discharge">Mucopurulent (Tubotympanic)</option>
                            <option value="Scanty foul-smelling discharge">Foul Smelling (Atticoantral)</option>
                            <option value="Fungal Debri (Otomycosis)">Otomycosis (Fungal)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Tympanic Membrane (TM)</label>
                        <select class="zrx-exam-select" data-ent-field="as-tm">
                            <option value="Intact, pearly grey, cone of light +ve">Intact, Pearly Grey (Normal)</option>
                            <option value="Congested & Bulging (AOM)">Congested &amp; Bulging (AOM)</option>
                            <option value="Retracted, prominent malleus handle">Retracted (OME)</option>
                            <option value="Central perforation (Pars Tensa, Safe CSOM)">Central Perforation (Safe CSOM)</option>
                            <option value="Subtotal perforation">Subtotal Perforation</option>
                            <option value="Attic / Marginal perforation with Cholesteatoma">Attic / Marginal (Unsafe CSOM)</option>
                            <option value="Tympanosclerosis patches">Tympanosclerosis</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Rinne Test (512 Hz)</label>
                        <select class="zrx-exam-select" data-ent-field="as-rinne">
                            <option value="Rinne Positive (AC > BC, Normal/SNHL)">Positive (AC > BC, Normal)</option>
                            <option value="Rinne Negative (BC > AC, Conductive Loss)">Negative (BC > AC, Conductive)</option>
                            <option value="Reduced Positive (Sensori-neural)">Reduced Positive (SNHL)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Central Weber & Vestibular row -->
            <div class="zrx-ent-weber-row">
                <div class="zrx-exam-form-row" style="flex: 1;">
                    <label class="zrx-exam-lbl">Weber Test (512 Hz Tuning Fork)</label>
                    <select class="zrx-exam-select" data-ent-field="weber">
                        <option value="Weber Centred (Not lateralized, Normal)">Centred / Not Lateralized (Normal)</option>
                        <option value="Weber Lateralized to Right Ear (AD)">Lateralized to Right (AD)</option>
                        <option value="Weber Lateralized to Left Ear (AS)">Lateralized to Left (AS)</option>
                    </select>
                </div>
                <div class="zrx-exam-form-row" style="flex: 1;">
                    <label class="zrx-exam-lbl">Mastoid &amp; Tragus Tenderness</label>
                    <select class="zrx-exam-select" data-ent-field="mastoid-tenderness">
                        <option value="None / Non-tender">None (Non-tender)</option>
                        <option value="Tragal tenderness +ve (Otitis Externa)">Tragal tenderness +ve</option>
                        <option value="Right Mastoid tenderness (Acute Mastoiditis)">Right Mastoid tenderness</option>
                        <option value="Left Mastoid tenderness (Acute Mastoiditis)">Left Mastoid tenderness</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section 2: Nose & PNS -->
        <div class="zrx-ent-pane" id="zrx-ent-pane-nose" hidden>
            <div class="zrx-ent-two-col">
                <div class="zrx-ent-col-card">
                    <div class="zrx-ent-card-head">
                        <span class="zrx-ent-badge nose">NOSE</span>
                        <span>Anterior Rhinoscopy</span>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Nasal Septum (DNS)</label>
                        <select class="zrx-exam-select" data-ent-field="nasal-septum">
                            <option value="Midline / Straight">Midline / Straight (Normal)</option>
                            <option value="DNS to Right with spur">DNS to Right (Spur +ve)</option>
                            <option value="DNS to Left with spur">DNS to Left (Spur +ve)</option>
                            <option value="S-shaped DNS (bilateral obstruction)">S-shaped DNS (Bilateral)</option>
                            <option value="Septal perforation">Septal Perforation</option>
                            <option value="Septal hematoma / abscess">Septal Hematoma</option>
                        </select>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Inferior Turbinates</label>
                        <select class="zrx-exam-select" data-ent-field="turbinates">
                            <option value="Normal size, non-obstructive">Normal size</option>
                            <option value="Hypertrophied Right Turbinate (Compensatory)">Hypertrophied Right</option>
                            <option value="Hypertrophied Left Turbinate (Compensatory)">Hypertrophied Left</option>
                            <option value="Bilateral Inferior Turbinate Hypertrophy">Bilateral Hypertrophy (BITH)</option>
                            <option value="Atrophic mucosa with crusting">Atrophic / Crusted</option>
                        </select>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Mucosa &amp; Secretions</label>
                        <select class="zrx-exam-select" data-ent-field="nasal-mucosa">
                            <option value="Healthy pink, no excess secretion">Healthy pink (Normal)</option>
                            <option value="Pale, boggy, watery rhinorrhea (Allergic)">Pale &amp; Boggy (Allergic)</option>
                            <option value="Hyperemic, congested, mucopurulent">Congested / Purulent</option>
                            <option value="Blood-stained discharge / Epistaxis">Epistaxis / Bleeding</option>
                        </select>
                    </div>
                </div>

                <div class="zrx-ent-col-card">
                    <div class="zrx-ent-card-head">
                        <span class="zrx-ent-badge pns">PNS</span>
                        <span>Sinuses &amp; Polyps</span>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Nasal Polyps</label>
                        <select class="zrx-exam-select" data-ent-field="nasal-polyps">
                            <option value="Absent / Clear meatus">Absent / Clear (Normal)</option>
                            <option value="Multiple pale ethmoidal polyps (grape-like)">Multiple Ethmoidal Polyps</option>
                            <option value="Solitary Antrochoanal polyp (Right)">Antrochoanal Polyp (Right)</option>
                            <option value="Solitary Antrochoanal polyp (Left)">Antrochoanal Polyp (Left)</option>
                        </select>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Paranasal Sinus Tenderness</label>
                        <select class="zrx-exam-select" data-ent-field="pns-tenderness">
                            <option value="None / Non-tender">None (Non-tender)</option>
                            <option value="Frontal sinus tenderness +ve">Frontal Sinus Tenderness</option>
                            <option value="Maxillary sinus tenderness +ve">Maxillary Sinus Tenderness</option>
                            <option value="Frontal & Maxillary tenderness (Pansinusitis)">Pansinusitis Tenderness</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Throat & Oral Cavity -->
        <div class="zrx-ent-pane" id="zrx-ent-pane-throat" hidden>
            <div class="zrx-ent-two-col">
                <div class="zrx-ent-col-card">
                    <div class="zrx-ent-card-head">
                        <span class="zrx-ent-badge throat">TONSILS</span>
                        <span>Palatine Tonsils &amp; Pillars</span>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Tonsillar Size (Brodsky Grading)</label>
                        <select class="zrx-exam-select" data-ent-field="tonsil-grade">
                            <option value="Grade 1 (Within tonsillar pillars, <25%)">Grade 1 (<25% - Normal/Mild)</option>
                            <option value="Grade 2 (Extending beyond pillars, 25-50%)">Grade 2 (25-50% - Moderate)</option>
                            <option value="Grade 3 (Significant airway narrowing, 50-75%)">Grade 3 (50-75% - Severe)</option>
                            <option value="Grade 4 (Kissing tonsils, >75% obstruction)">Grade 4 (>75% - Kissing Tonsils)</option>
                            <option value="Grade 0 (Absent / Surgically excised)">Grade 0 (Post-tonsillectomy)</option>
                        </select>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Tonsillar Appearance &amp; Exudate</label>
                        <select class="zrx-exam-select" data-ent-field="tonsil-appearance">
                            <option value="Normal pink, non-congested">Normal pink (Normal)</option>
                            <option value="Hyperemic, congested, no exudate">Congested / Erythematous</option>
                            <option value="Cryptic, with follicular white exudates">Follicular Exudates</option>
                            <option value="Confluent white pseudo-membrane">Membranous Tonsillitis</option>
                            <option value="Peritonsillar bulge (Quinsy suspected)">Peritonsillar Bulge (Quinsy)</option>
                        </select>
                    </div>
                </div>

                <div class="zrx-ent-col-card">
                    <div class="zrx-ent-card-head">
                        <span class="zrx-ent-badge throat">PHARYNX</span>
                        <span>Oropharynx &amp; Uvula</span>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Posterior Pharyngeal Wall (PPW)</label>
                        <select class="zrx-exam-select" data-ent-field="ppw">
                            <option value="Smooth, pink, quiet">Smooth &amp; Pink (Normal)</option>
                            <option value="Congested with mucosal erythema">Congested (Acute Pharyngitis)</option>
                            <option value="Granular cobblestoning (Chronic pharyngitis / PND)">Cobblestoning / Granular (PND)</option>
                            <option value="Mucopurulent streak from nasopharynx">Mucopurulent Post-nasal Drip</option>
                        </select>
                    </div>
                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Uvula Position &amp; Soft Palate</label>
                        <select class="zrx-exam-select" data-ent-field="uvula">
                            <option value="Central, mobile on phonation">Central &amp; Mobile (Normal)</option>
                            <option value="Edematous uvula (Uvulitis)">Edematous (Uvulitis)</option>
                            <option value="Deviated to Right (Left Quinsy)">Deviated to Right (Left Peritonsillar)</option>
                            <option value="Deviated to Left (Right Quinsy)">Deviated to Left (Right Peritonsillar)</option>
                            <option value="Bifid uvula">Bifid Uvula</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Presets:</span>
            <button type="button" class="zrx-exam-chip" data-ent-preset="safe-csom-ad">Right Safe CSOM (Central Perforation)</button>
            <button type="button" class="zrx-exam-chip" data-ent-preset="allergic-rhinitis">Allergic Rhinitis (Pale Turbinates + DNS)</button>
            <button type="button" class="zrx-exam-chip" data-ent-preset="acute-tonsillitis">Acute Follicular Tonsillitis (Grade 3)</button>
            <button type="button" class="zrx-exam-chip" data-ent-preset="impacted-wax">Bilateral Impacted Wax</button>
            <button type="button" class="zrx-exam-chip" data-ent-preset="normal-all">Unremarkable ENT Bilaterally</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="ent-exam-findings" class="zrx-exam-lbl">ENT Examination Findings &amp; Clinical Impression</label>
            <textarea id="ent-exam-findings" class="zrx-exam-textarea" rows="3" placeholder="ENT clinical findings summary..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
