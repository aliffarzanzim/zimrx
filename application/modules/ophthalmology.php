<?php
declare(strict_types=1);

/**
 * ZimRx - Ophthalmology (Eye Examination) Module
 * Comprehensive bilateral visual acuity, anterior segment, IOP, and funduscopic examination.
 */
?>
<div class="pc-wrapper zrx-exam-wrapper" id="ophthalmology-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('eye', 14) ?>
            <span>Ophthalmology (Eye Examination)</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-eye" title="Pre-fill normal 6/6 bilateral findings">Normal Both</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-eye" title="Clear all fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Visual Acuity & IOP Top Strip -->
        <div class="zrx-exam-dual-grid">
            <!-- Right Eye (OD) Column -->
            <div class="zrx-exam-col" data-side="od">
                <div class="zrx-exam-col-header">
                    <span class="zrx-exam-col-badge">OD</span>
                    <span>Right Eye (Oculus Dexter)</span>
                </div>

                <div class="zrx-exam-section-label">Visual Acuity & IOP</div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Distance & Near Vision</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="od-dva" title="Distance Visual Acuity">
                            <option value="6/6">DVA: 6/6</option>
                            <option value="6/9">DVA: 6/9</option>
                            <option value="6/12">DVA: 6/12</option>
                            <option value="6/18">DVA: 6/18</option>
                            <option value="6/24">DVA: 6/24</option>
                            <option value="6/36">DVA: 6/36</option>
                            <option value="6/60">DVA: 6/60</option>
                            <option value="CF 1m">CF 1m</option>
                            <option value="CF 2m">CF 2m</option>
                            <option value="HM">HM (Hand Movement)</option>
                            <option value="PL/PR">PL / PR</option>
                            <option value="No PL">No PL</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="od-nva" title="Near Visual Acuity">
                            <option value="N6">NVA: N6</option>
                            <option value="N8">NVA: N8</option>
                            <option value="N10">NVA: N10</option>
                            <option value="N12">NVA: N12</option>
                            <option value="N18">NVA: N18</option>
                            <option value="N36">NVA: N36</option>
                        </select>
                    </div>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Intraocular Pressure (IOP)</label>
                    <div class="zrx-exam-flex-pair">
                        <input type="text" class="zrx-exam-input" placeholder="e.g. 14" data-oph-field="od-iop" autocomplete="off">
                        <span class="zrx-exam-input-unit">mmHg</span>
                    </div>
                </div>

                <div class="zrx-exam-section-label">Anterior Segment</div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Lids & Conjunctiva</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="od-lids">
                            <option value="Normal">Lids: Normal</option>
                            <option value="Blepharitis">Blepharitis</option>
                            <option value="Chalazion">Chalazion</option>
                            <option value="Stye">Stye</option>
                            <option value="Ptosis">Ptosis</option>
                            <option value="Trichiasis">Trichiasis</option>
                            <option value="Ectropion">Ectropion</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="od-conjunctiva">
                            <option value="Normal / Clear">Conj: Normal</option>
                            <option value="Congested / Injected">Congested</option>
                            <option value="Ciliary injection">Ciliary flush</option>
                            <option value="Subconj. hemorrhage">Subconj. hem.</option>
                            <option value="Pterygium">Pterygium</option>
                            <option value="Chemosis">Chemosis</option>
                        </select>
                    </div>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Cornea & Ant. Chamber</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="od-cornea">
                            <option value="Clear">Cornea: Clear</option>
                            <option value="Opacity">Opacity</option>
                            <option value="Ulcer / Infiltrate">Ulcer/Infiltrate</option>
                            <option value="Abrasion">Abrasion</option>
                            <option value="Arcus senilis">Arcus senilis</option>
                            <option value="Edematous">Edematous</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="od-ac">
                            <option value="Normal depth, quiet">AC: Normal quiet</option>
                            <option value="Shallow">Shallow</option>
                            <option value="Deep">Deep</option>
                            <option value="Hyphema">Hyphema</option>
                            <option value="Hypopyon">Hypopyon</option>
                        </select>
                    </div>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Pupil & Crystalline Lens</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="od-pupil">
                            <option value="Round Regular Reactive (RRR)">Pupil: RRR</option>
                            <option value="Sluggish reaction">Sluggish</option>
                            <option value="Dilated">Dilated</option>
                            <option value="Constricted">Constricted</option>
                            <option value="RAPD (+ve)">RAPD (+ve)</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="od-lens">
                            <option value="Clear">Lens: Clear</option>
                            <option value="Early cataract">Early cataract</option>
                            <option value="Immature Cataract (IMSC)">IMSC</option>
                            <option value="Mature Cataract (MSC)">MSC</option>
                            <option value="Pseudophakic (PCIOL)">Pseudophakic (PCIOL)</option>
                            <option value="Aphakic">Aphakic</option>
                        </select>
                    </div>
                </div>

                <div class="zrx-exam-section-label">Posterior Segment / Fundus</div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Optic Disc & Cup/Disc</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="od-disc">
                            <option value="Normal pink, distinct margin">Disc: Normal pink</option>
                            <option value="Glaucomatous cupping">Cupping</option>
                            <option value="Pale / Atrophy">Pale / Atrophy</option>
                            <option value="Papilledema / Swollen">Papilledema</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="od-cdr" title="Cup to Disc Ratio">
                            <option value="CDR 0.3">CDR 0.3</option>
                            <option value="CDR 0.4">CDR 0.4</option>
                            <option value="CDR 0.5">CDR 0.5</option>
                            <option value="CDR 0.6">CDR 0.6</option>
                            <option value="CDR 0.7">CDR 0.7</option>
                            <option value="CDR 0.8+">CDR 0.8+</option>
                        </select>
                    </div>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Macula & Retinal Vessels</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="od-macula">
                            <option value="Normal foveal reflex">Macula: Normal</option>
                            <option value="Dull reflex">Dull reflex</option>
                            <option value="Macular edema">Edema</option>
                            <option value="Drusen / ARMD">Drusen / ARMD</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="od-retina">
                            <option value="Normal background">Retina: Normal</option>
                            <option value="Hypertensive Retinopathy">HTN Retinopathy</option>
                            <option value="Diabetic Retinopathy (NPDR)">Diabetic (NPDR)</option>
                            <option value="Retinal hemorrhage">Hemorrhage</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Left Eye (OS) Column -->
            <div class="zrx-exam-col" data-side="os">
                <div class="zrx-exam-col-header">
                    <span class="zrx-exam-col-badge">OS</span>
                    <span>Left Eye (Oculus Sinister)</span>
                </div>

                <div class="zrx-exam-section-label">Visual Acuity & IOP</div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Distance & Near Vision</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="os-dva" title="Distance Visual Acuity">
                            <option value="6/6">DVA: 6/6</option>
                            <option value="6/9">DVA: 6/9</option>
                            <option value="6/12">DVA: 6/12</option>
                            <option value="6/18">DVA: 6/18</option>
                            <option value="6/24">DVA: 6/24</option>
                            <option value="6/36">DVA: 6/36</option>
                            <option value="6/60">DVA: 6/60</option>
                            <option value="CF 1m">CF 1m</option>
                            <option value="CF 2m">CF 2m</option>
                            <option value="HM">HM (Hand Movement)</option>
                            <option value="PL/PR">PL / PR</option>
                            <option value="No PL">No PL</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="os-nva" title="Near Visual Acuity">
                            <option value="N6">NVA: N6</option>
                            <option value="N8">NVA: N8</option>
                            <option value="N10">NVA: N10</option>
                            <option value="N12">NVA: N12</option>
                            <option value="N18">NVA: N18</option>
                            <option value="N36">NVA: N36</option>
                        </select>
                    </div>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Intraocular Pressure (IOP)</label>
                    <div class="zrx-exam-flex-pair">
                        <input type="text" class="zrx-exam-input" placeholder="e.g. 14" data-oph-field="os-iop" autocomplete="off">
                        <span class="zrx-exam-input-unit">mmHg</span>
                    </div>
                </div>

                <div class="zrx-exam-section-label">Anterior Segment</div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Lids & Conjunctiva</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="os-lids">
                            <option value="Normal">Lids: Normal</option>
                            <option value="Blepharitis">Blepharitis</option>
                            <option value="Chalazion">Chalazion</option>
                            <option value="Stye">Stye</option>
                            <option value="Ptosis">Ptosis</option>
                            <option value="Trichiasis">Trichiasis</option>
                            <option value="Ectropion">Ectropion</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="os-conjunctiva">
                            <option value="Normal / Clear">Conj: Normal</option>
                            <option value="Congested / Injected">Congested</option>
                            <option value="Ciliary injection">Ciliary flush</option>
                            <option value="Subconj. hemorrhage">Subconj. hem.</option>
                            <option value="Pterygium">Pterygium</option>
                            <option value="Chemosis">Chemosis</option>
                        </select>
                    </div>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Cornea & Ant. Chamber</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="os-cornea">
                            <option value="Clear">Cornea: Clear</option>
                            <option value="Opacity">Opacity</option>
                            <option value="Ulcer / Infiltrate">Ulcer/Infiltrate</option>
                            <option value="Abrasion">Abrasion</option>
                            <option value="Arcus senilis">Arcus senilis</option>
                            <option value="Edematous">Edematous</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="os-ac">
                            <option value="Normal depth, quiet">AC: Normal quiet</option>
                            <option value="Shallow">Shallow</option>
                            <option value="Deep">Deep</option>
                            <option value="Hyphema">Hyphema</option>
                            <option value="Hypopyon">Hypopyon</option>
                        </select>
                    </div>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Pupil & Crystalline Lens</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="os-pupil">
                            <option value="Round Regular Reactive (RRR)">Pupil: RRR</option>
                            <option value="Sluggish reaction">Sluggish</option>
                            <option value="Dilated">Dilated</option>
                            <option value="Constricted">Constricted</option>
                            <option value="RAPD (+ve)">RAPD (+ve)</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="os-lens">
                            <option value="Clear">Lens: Clear</option>
                            <option value="Early cataract">Early cataract</option>
                            <option value="Immature Cataract (IMSC)">IMSC</option>
                            <option value="Mature Cataract (MSC)">MSC</option>
                            <option value="Pseudophakic (PCIOL)">Pseudophakic (PCIOL)</option>
                            <option value="Aphakic">Aphakic</option>
                        </select>
                    </div>
                </div>

                <div class="zrx-exam-section-label">Posterior Segment / Fundus</div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Optic Disc & Cup/Disc</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="os-disc">
                            <option value="Normal pink, distinct margin">Disc: Normal pink</option>
                            <option value="Glaucomatous cupping">Cupping</option>
                            <option value="Pale / Atrophy">Pale / Atrophy</option>
                            <option value="Papilledema / Swollen">Papilledema</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="os-cdr" title="Cup to Disc Ratio">
                            <option value="CDR 0.3">CDR 0.3</option>
                            <option value="CDR 0.4">CDR 0.4</option>
                            <option value="CDR 0.5">CDR 0.5</option>
                            <option value="CDR 0.6">CDR 0.6</option>
                            <option value="CDR 0.7">CDR 0.7</option>
                            <option value="CDR 0.8+">CDR 0.8+</option>
                        </select>
                    </div>
                </div>
                <div class="zrx-exam-form-row">
                    <label class="zrx-exam-lbl">Macula & Retinal Vessels</label>
                    <div class="zrx-exam-flex-pair">
                        <select class="zrx-exam-select" data-oph-field="os-macula">
                            <option value="Normal foveal reflex">Macula: Normal</option>
                            <option value="Dull reflex">Dull reflex</option>
                            <option value="Macular edema">Edema</option>
                            <option value="Drusen / ARMD">Drusen / ARMD</option>
                        </select>
                        <select class="zrx-exam-select" data-oph-field="os-retina">
                            <option value="Normal background">Retina: Normal</option>
                            <option value="Hypertensive Retinopathy">HTN Retinopathy</option>
                            <option value="Diabetic Retinopathy (NPDR)">Diabetic (NPDR)</option>
                            <option value="Retinal hemorrhage">Hemorrhage</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refraction / Spectacles Prescription Strip -->
        <div class="zrx-exam-refraction-box">
            <div class="zrx-exam-refraction-title">Refraction / Spectacles</div>
            <div class="zrx-exam-refraction-grid">
                <div class="zrx-exam-ref-row">
                    <span class="zrx-exam-ref-eye">OD:</span>
                    <input type="text" class="zrx-exam-input zrx-ref-input" placeholder="Sph" data-oph-field="od-sph" title="OD Sphere" autocomplete="off">
                    <input type="text" class="zrx-exam-input zrx-ref-input" placeholder="Cyl" data-oph-field="od-cyl" title="OD Cylinder" autocomplete="off">
                    <input type="text" class="zrx-exam-input zrx-ref-input" placeholder="Axis" data-oph-field="od-axis" title="OD Axis" autocomplete="off">
                    <input type="text" class="zrx-exam-input zrx-ref-input" placeholder="Add" data-oph-field="od-add" title="OD Near Add" autocomplete="off">
                </div>
                <div class="zrx-exam-ref-row">
                    <span class="zrx-exam-ref-eye">OS:</span>
                    <input type="text" class="zrx-exam-input zrx-ref-input" placeholder="Sph" data-oph-field="os-sph" title="OS Sphere" autocomplete="off">
                    <input type="text" class="zrx-exam-input zrx-ref-input" placeholder="Cyl" data-oph-field="os-cyl" title="OS Cylinder" autocomplete="off">
                    <input type="text" class="zrx-exam-input zrx-ref-input" placeholder="Axis" data-oph-field="os-axis" title="OS Axis" autocomplete="off">
                    <input type="text" class="zrx-exam-input zrx-ref-input" placeholder="Add" data-oph-field="os-add" title="OS Near Add" autocomplete="off">
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Presets:</span>
            <button type="button" class="zrx-exam-chip" data-preset="Normal Bilateral Eye Examination: Visual Acuity 6/6 OU, quiet anterior segments, clear corneas and lenses, normal fundus with healthy optic discs (CDR 0.3) OU. IOP within normal limits.">Normal Both Eyes</button>
            <button type="button" class="zrx-exam-chip" data-preset="Presbyopia: Normal distance visual acuity (6/6 OU). Near visual acuity impaired. Near addition required for reading.">Presbyopia</button>
            <button type="button" class="zrx-exam-chip" data-preset="Immature Senile Cataract (IMSC): Reduced visual acuity, lenticular opacities noted. Clear anterior chamber, normal intraocular pressure.">Cataract (IMSC)</button>
            <button type="button" class="zrx-exam-chip" data-preset="Pseudophakic: Well-centered posterior chamber intraocular lens (PCIOL) in situ. Posterior capsule clear.">Pseudophakic (PCIOL)</button>
            <button type="button" class="zrx-exam-chip" data-preset="Acute Conjunctivitis: Marked conjunctival congestion, foreign body sensation, mucoid discharge. Cornea clear, pupils active and reactive.">Conjunctivitis</button>
            <button type="button" class="zrx-exam-chip" data-preset="Glaucoma Suspect: Increased cup-to-disc ratio (CDR &gt; 0.6) with neuroretinal rim thinning. Elevated / borderline intraocular pressure. Advised Visual Field Analysis &amp; OCT.">Glaucoma Suspect</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="ophthalmology-findings" class="zrx-exam-lbl">Findings & Clinical Impression</label>
            <textarea id="ophthalmology-findings" class="zrx-exam-textarea" rows="2" placeholder="Ophthalmology examination summary, visual acuity notes, and clinical plan..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
