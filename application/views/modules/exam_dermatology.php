<?php
declare(strict_types=1);

// Dermatology module: primary/secondary lesion morphology, distribution, bedside signs, and body mapping.
?>
<div class="pc-wrapper zrx-exam-wrapper" id="dermatology-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('layers', 14) ?>
            <span>Dermatology &amp; Lesion Morphology</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-derma" title="Pre-fill clear, unremarkable skin">Clear Skin</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-derma" title="Clear all dermatology fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Top Row: Primary Morphology & Distribution -->
        <div class="zrx-derma-top-grid">
            <div class="zrx-exam-form-row">
                <label class="zrx-exam-lbl">Primary Lesion Morphology</label>
                <select class="zrx-exam-select" data-derma-field="primary-lesion">
                    <option value="Macule (<1 cm flat circumscribed discolored)">Macule (<1 cm flat)</option>
                    <option value="Patch (>1 cm flat circumscribed lesion)">Patch (>1 cm flat)</option>
                    <option value="Papule (<1 cm elevated solid lesion)">Papule (<1 cm elevated)</option>
                    <option value="Plaque (>1 cm elevated plateau-like)" selected>Plaque (>1 cm elevated)</option>
                    <option value="Nodule (>1 cm deep solid palpable mass)">Nodule (>1 cm deep mass)</option>
                    <option value="Wheal / Hive (Evanescent edematous swelling)">Wheal / Urticarial Hive</option>
                    <option value="Vesicle (<1 cm fluid-filled blister)">Vesicle (<1 cm blister)</option>
                    <option value="Bulla (>1 cm large fluid-filled blister)">Bulla (>1 cm large blister)</option>
                    <option value="Pustule (Pus-filled superficial lesion)">Pustule (Pus-filled)</option>
                    <option value="Comedone (Open/Closed blackhead/whitehead)">Comedone (Acne)</option>
                </select>
            </div>

            <div class="zrx-exam-form-row">
                <label class="zrx-exam-lbl">Secondary Changes</label>
                <select class="zrx-exam-select" data-derma-field="secondary-lesion">
                    <option value="None / Discrete">None (Primary only)</option>
                    <option value="Scaling / Desquamation (Silvery/Fine/Greasy)">Scaling / Desquamation</option>
                    <option value="Crusting (Dried honey-colored/serous exudate)">Crusting (Honey/Serous)</option>
                    <option value="Excoriations (Scratch marks / Pruritus)">Excoriations (Scratch marks)</option>
                    <option value="Lichenification (Thickened skin with exaggerated markings)">Lichenification (Chronic)</option>
                    <option value="Erosion (Superficial moist epidermal loss)">Erosion (Superficial)</option>
                    <option value="Ulceration (Deep loss of dermis)">Ulceration</option>
                    <option value="Fissuring (Linear painful cracks)">Fissuring</option>
                    <option value="Atrophy / Striae">Atrophy / Striae</option>
                </select>
            </div>

            <div class="zrx-exam-form-row">
                <label class="zrx-exam-lbl">Arrangement &amp; Pattern</label>
                <select class="zrx-exam-select" data-derma-field="pattern">
                    <option value="Disseminated / Scattered">Disseminated / Scattered</option>
                    <option value="Annular / Ring-shaped (Active scaly border)">Annular / Targetoid (Ring-like)</option>
                    <option value="Grouped / Herpetiform clusters">Grouped / Herpetiform</option>
                    <option value="Linear / Streaked">Linear / Streaked</option>
                    <option value="Dermatomal (Zosteriform, unilateral)">Dermatomal / Zosteriform</option>
                    <option value="Symmetrical bilateral flexural">Flexural (Antecubital/Popliteal)</option>
                    <option value="Extensor surfaces (Elbows/Knees/Scalp)">Extensor (Elbows/Knees)</option>
                    <option value="Photodistributed (Sun-exposed areas)">Sun-exposed / Photo-induced</option>
                </select>
            </div>
        </div>

        <!-- Anatomical Body Sites Checklist -->
        <div class="zrx-derma-sites-card">
            <span class="zrx-derma-card-title">Anatomical Sites Involved:</span>
            <div class="zrx-derma-sites-grid">
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Scalp & Face"><span>Scalp &amp; Face</span></label>
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Neck & Chest"><span>Neck &amp; Chest</span></label>
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Back & Torso"><span>Back &amp; Torso</span></label>
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Upper Limbs & Extensors"><span>Upper Limbs (Extensor)</span></label>
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Flexural Creases"><span>Flexural Creases</span></label>
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Palms & Soles"><span>Palms &amp; Soles</span></label>
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Lower Limbs"><span>Lower Limbs</span></label>
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Groin & Genitalia"><span>Groin &amp; Genitalia</span></label>
                <label class="zrx-abdo-check-pill"><input type="checkbox" data-derma-site="Nails (Pitting/Onycholysis)"><span>Nails / Ungual</span></label>
            </div>
        </div>

        <!-- Bedside Clinical Signs & Pruritus -->
        <div class="zrx-derma-signs-grid">
            <div class="zrx-exam-form-row">
                <label class="zrx-exam-lbl">Auspitz Sign (Psoriasis)</label>
                <select class="zrx-exam-select" data-derma-field="auspitz">
                    <option value="Negative">Negative</option>
                    <option value="Positive (Pinpoint bleeding on scraping)">Positive (Pinpoint bleeding)</option>
                    <option value="Not tested">Not tested</option>
                </select>
            </div>

            <div class="zrx-exam-form-row">
                <label class="zrx-exam-lbl">Nikolsky's Sign (Bullous Disorders)</label>
                <select class="zrx-exam-select" data-derma-field="nikolsky">
                    <option value="Negative">Negative</option>
                    <option value="Positive (Epidermal detachment on lateral pressure)">Positive (Epidermal sloughing)</option>
                    <option value="Not tested">Not tested</option>
                </select>
            </div>

            <div class="zrx-exam-form-row">
                <label class="zrx-exam-lbl">Koebner Phenomenon / Dermographism</label>
                <select class="zrx-exam-select" data-derma-field="koebner">
                    <option value="Negative">Negative</option>
                    <option value="Koebner Phenomenon Positive (Lesions in lines of trauma)">Koebner Positive</option>
                    <option value="Dermographism Positive (Wheal on firm stroke)">Dermographism Positive</option>
                    <option value="Darier's Sign Positive">Darier's Sign Positive</option>
                </select>
            </div>

            <div class="zrx-exam-form-row">
                <label class="zrx-exam-lbl">Pruritus / Itching Intensity</label>
                <select class="zrx-exam-select" data-derma-field="pruritus">
                    <option value="Non-pruritic / Asymptomatic">Non-pruritic (None)</option>
                    <option value="Mild intermittent itching">Mild itching</option>
                    <option value="Moderate pruritus">Moderate pruritus</option>
                    <option value="Severe intractable pruritus (Sleep disturbing)">Severe / Intractable</option>
                </select>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Presets:</span>
            <button type="button" class="zrx-exam-chip" data-derma-preset="psoriasis">Plaque Psoriasis (Silvery scales, Auspitz +ve)</button>
            <button type="button" class="zrx-exam-chip" data-derma-preset="atopic-eczema">Atopic Eczema (Flexural erythema, excoriations)</button>
            <button type="button" class="zrx-exam-chip" data-derma-preset="tinea-corporis">Tinea Corporis (Annular scaly ring)</button>
            <button type="button" class="zrx-exam-chip" data-derma-preset="herpes-zoster">Herpes Zoster (Unilateral dermatomal vesicles)</button>
            <button type="button" class="zrx-exam-chip" data-derma-preset="urticaria">Acute Urticaria (Evanescent wheals)</button>
            <button type="button" class="zrx-exam-chip" data-derma-preset="clear-skin">Clear Unremarkable Skin</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="dermatology-findings" class="zrx-exam-lbl">Dermatology Findings &amp; Clinical Diagnosis</label>
            <textarea id="dermatology-findings" class="zrx-exam-textarea" rows="3" placeholder="Lesion morphology, distribution, clinical signs, and dermatological impression..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
