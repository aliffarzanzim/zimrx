<?php
declare(strict_types=1);
?>
<!-- Main Workspace Container -->
<div id="viewWorkspace" class="drug-view-wrapper" style="display:none;">

    <!-- ===================================================================== -->
    <!-- 1. GENERICS WORKSPACE                                                 -->
    <!-- ===================================================================== -->
    <div id="wsGenerics" class="drug-subworkspace" style="display:none;">

        <!-- Generics Meta Subbar -->
        <div class="drug-meta-subbar">
            <div class="drug-meta-left">
                <button type="button" class="drug-btn-back" id="btnGenericsBackToHub" title="Return to Studio Hub">
                    <?= zrx_icon('arrow-left', 14) ?>
                    <span>Hub</span>
                </button>
                <div class="drug-scope-badge-wrap">
                    <span class="drug-scope-code">CORE</span>
                    <div class="drug-scope-titles">
                        <span class="drug-scope-title">Generics &amp; Clinical Monographs</span>
                        <span class="drug-scope-subtitle">Universal Active Substances, WHO ATC &amp; Clinical Guidelines</span>
                    </div>
                </div>
            </div>
            <div class="drug-meta-right">
                <span class="drug-meta-item">
                    <?= zrx_icon('user', 12) ?>
                    <span>Alif Farzan Zim</span>
                </span>
                <span class="drug-meta-item">
                    <?= zrx_icon('clock', 12) ?>
                    <span><?= date('Y-m-d') ?></span>
                </span>
                <span class="drug-status-badge-live" id="genericsSaveStatusBadge">All saved</span>
            </div>
        </div>

        <!-- Generics Tabs Strip -->
        <div class="drug-ws-tabs-bar">
            <div class="drug-ws-tabs" id="tabsGenerics">
                <button type="button" class="drug-ws-tab active" data-tab="tabGenList">
                    <?= zrx_icon('pill', 13) ?>
                    <span>Generics</span>
                </button>
                <button type="button" class="drug-ws-tab" data-tab="tabGenAdd">
                    <?= zrx_icon('plus', 13) ?>
                    <span>Add a Generic</span>
                </button>
                <button type="button" class="drug-ws-tab" data-tab="tabClassList">
                    <?= zrx_icon('layers', 13) ?>
                    <span>Drug Classification</span>
                </button>
                <button type="button" class="drug-ws-tab" data-tab="tabClassAdd">
                    <?= zrx_icon('plus', 13) ?>
                    <span>Add a Drug Classification</span>
                </button>
                <button type="button" class="drug-ws-tab" data-tab="tabIndList">
                    <?= zrx_icon('activity', 13) ?>
                    <span>Drug Indication</span>
                </button>
                <button type="button" class="drug-ws-tab" data-tab="tabIndAdd">
                    <?= zrx_icon('plus', 13) ?>
                    <span>Add a Drug Indication</span>
                </button>
            </div>
            <div class="drug-ws-meta" id="genericsMetaBadge">
                <span class="drug-scope-pill">Universal Core</span>
            </div>
        </div>

        <div class="drug-split-pane">
            <!-- Left Search / List Pane -->
            <section class="drug-card drug-list-pane" id="genericsListPane">
                <div class="drug-card-header">
                    <span id="genListHeaderTitle">Generics Catalog</span>
                    <span class="drug-badge-count" id="genListCount">0</span>
                </div>
                <div class="drug-search-box">
                    <input type="text" id="genSearchInput" class="drug-input drug-input-search" placeholder="Search generic name or WHO ATC...">
                    <button type="button" class="drug-btn drug-btn-sm" id="btnGenClearSearch" title="Clear search">
                        <?= zrx_icon('x', 11) ?>
                    </button>
                </div>
                <div class="drug-scroll-list" id="genItemsList">
                    <div class="drug-empty-state">Loading generics...</div>
                </div>
            </section>

            <!-- Right Detail / Form Pane -->
            <section class="drug-card drug-detail-pane" id="genericsDetailPane">
                <div class="drug-card-header">
                    <span id="genDetailHeaderTitle">Generic Monograph Inspector</span>
                    <div id="genDetailHeaderActions" style="display: flex; gap: 6px; align-items: center;"></div>
                </div>

                <div class="drug-detail-scroll" id="genDetailContainer">

                    <!-- Empty State -->
                    <div id="genEmptyState" class="drug-empty-state">
                        Select a generic substance, classification, or indication from the list on the left to inspect clinical monographs.
                    </div>

                    <!-- Panel: Generic Inspector (7 Structured Accordions) -->
                    <div id="panelGenInspector" style="display:none;">
                        <input type="hidden" id="inpEditGenId">

                        <!-- Accordion 1: Names and classification -->
                        <div class="drug-acc-item open" id="accGen1">
                            <div class="drug-acc-header" data-section="1">
                                <div class="drug-acc-title-wrap">
                                    <span class="drug-acc-toggle-icon"><?= zrx_icon('chevron-down', 13) ?></span>
                                    <span class="drug-acc-title">1. Names &amp; Classification</span>
                                </div>
                                <div class="drug-acc-actions">
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-edit" data-section="1">
                                        <?= zrx_icon('edit', 12) ?> <span>Edit Section</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary drug-btn-sm drug-acc-btn-save" data-section="1" style="display:none;">
                                        <?= zrx_icon('check', 12) ?> <span>Save</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-cancel" data-section="1" style="display:none;">
                                        <span>Cancel</span>
                                    </button>
                                </div>
                            </div>
                            <div class="drug-acc-content">
                                <!-- Read View -->
                                <div class="drug-acc-read-view" id="accGenRead1">
                                    <div class="drug-read-grid">
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Generic Name (INN / International Nonproprietary Name)</span>
                                            <span class="drug-read-val drug-val-title" id="readGenName">-</span>
                                        </div>
                                        <div class="drug-read-item">
                                            <span class="drug-read-lbl">US Adopted Name (USAN)</span>
                                            <span class="drug-read-val" id="readGenUsName">-</span>
                                        </div>
                                        <div class="drug-read-item">
                                            <span class="drug-read-lbl">WHO ATC Classification Code</span>
                                            <span class="drug-read-val" id="readGenAtc">-</span>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Linked Therapeutic Classifications</span>
                                            <div class="drug-tags-wrap" id="readGenClasses"></div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Linked Clinical Indications</span>
                                            <div class="drug-tags-wrap" id="readGenIndications"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit View -->
                                <div class="drug-acc-edit-view" id="accGenEdit1" style="display:none;">
                                    <div class="drug-form-grid">
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenName">Generic Name *</label>
                                            <input type="text" id="inpEditGenName" class="drug-input" required>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="inpEditGenUsName">US Adopted Name</label>
                                            <input type="text" id="inpEditGenUsName" class="drug-input" placeholder="e.g. Acetaminophen">
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="inpEditGenAtc">WHO ATC Code</label>
                                            <input type="text" id="inpEditGenAtc" class="drug-input" placeholder="e.g. A02BC01">
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label">Therapeutic Classifications</label>
                                            <div style="position: relative;">
                                                <input type="text" id="inpSearchLinkClass" class="drug-input" placeholder="Type to link therapeutic classification...">
                                                <div id="dropLinkClass" class="zrx-dropdown" style="display:none; width: 100%;"></div>
                                            </div>
                                            <div class="drug-tags-wrap" id="tagsGenClasses"></div>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label">Clinical Indications</label>
                                            <div style="position: relative;">
                                                <input type="text" id="inpSearchLinkInd" class="drug-input" placeholder="Type to link medical indication...">
                                                <div id="dropLinkInd" class="zrx-dropdown" style="display:none; width: 100%;"></div>
                                            </div>
                                            <div class="drug-tags-wrap" id="tagsGenIndications"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 2: Safety flags -->
                        <div class="drug-acc-item open" id="accGen2">
                            <div class="drug-acc-header" data-section="2">
                                <div class="drug-acc-title-wrap">
                                    <span class="drug-acc-toggle-icon"><?= zrx_icon('chevron-down', 13) ?></span>
                                    <span class="drug-acc-title">2. Safety Flags &amp; High-Alert Status</span>
                                </div>
                                <div class="drug-acc-actions">
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-edit" data-section="2">
                                        <?= zrx_icon('edit', 12) ?> <span>Edit Section</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary drug-btn-sm drug-acc-btn-save" data-section="2" style="display:none;">
                                        <?= zrx_icon('check', 12) ?> <span>Save</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-cancel" data-section="2" style="display:none;">
                                        <span>Cancel</span>
                                    </button>
                                </div>
                            </div>
                            <div class="drug-acc-content">
                                <!-- Read View -->
                                <div class="drug-acc-read-view" id="accGenRead2">
                                    <div class="drug-safety-grid" id="readSafetyPillsGrid"></div>
                                </div>

                                <!-- Edit View -->
                                <div class="drug-acc-edit-view" id="accGenEdit2" style="display:none;">
                                    <div class="drug-form-grid">
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditIsAntibiotic">Antibiotic Classification</label>
                                            <select id="selEditIsAntibiotic" class="drug-input">
                                                <option value="0">No (Not an antibiotic)</option>
                                                <option value="1">Yes (Antibiotic substance)</option>
                                            </select>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditIsHighAlert">High-Alert Medicine</label>
                                            <select id="selEditIsHighAlert" class="drug-input">
                                                <option value="0">No</option>
                                                <option value="1">Yes (High-Alert / Caution Required)</option>
                                            </select>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditSafePregnancy">Safe in Pregnancy</label>
                                            <select id="selEditSafePregnancy" class="drug-input">
                                                <option value="">Not Recorded</option>
                                                <option value="1">Yes (Considered safe)</option>
                                                <option value="0">No (Avoid in pregnancy)</option>
                                            </select>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditSafeLactation">Safe in Lactation</label>
                                            <select id="selEditSafeLactation" class="drug-input">
                                                <option value="">Not Recorded</option>
                                                <option value="1">Yes (Compatible with breastfeeding)</option>
                                                <option value="0">No (Avoid in lactation)</option>
                                            </select>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditRenalAdj">Renal Adjustment Required</label>
                                            <select id="selEditRenalAdj" class="drug-input">
                                                <option value="">Not Recorded</option>
                                                <option value="1">Yes (Dose adjustment required in renal impairment)</option>
                                                <option value="0">No</option>
                                            </select>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditHepaticSafe">Safe in Hepatic Impairment</label>
                                            <select id="selEditHepaticSafe" class="drug-input">
                                                <option value="">Not Recorded</option>
                                                <option value="1">Yes (Generally safe)</option>
                                                <option value="0">No (Use caution / contraindicated)</option>
                                            </select>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditPaediatricSafe">Safe in Paediatric Care</label>
                                            <select id="selEditPaediatricSafe" class="drug-input">
                                                <option value="">Not Recorded</option>
                                                <option value="1">Yes (Approved for pediatric use)</option>
                                                <option value="0">No (Contraindicated or safety not established)</option>
                                            </select>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditRequiresTapering">Requires Dose Tapering</label>
                                            <select id="selEditRequiresTapering" class="drug-input">
                                                <option value="">Not Recorded</option>
                                                <option value="1">Yes (Must be tapered off gradually)</option>
                                                <option value="0">No</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 3: Warnings and precautions -->
                        <div class="drug-acc-item open" id="accGen3">
                            <div class="drug-acc-header" data-section="3">
                                <div class="drug-acc-title-wrap">
                                    <span class="drug-acc-toggle-icon"><?= zrx_icon('chevron-down', 13) ?></span>
                                    <span class="drug-acc-title">3. Warnings &amp; Precautions</span>
                                </div>
                                <div class="drug-acc-actions">
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-edit" data-section="3">
                                        <?= zrx_icon('edit', 12) ?> <span>Edit Section</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary drug-btn-sm drug-acc-btn-save" data-section="3" style="display:none;">
                                        <?= zrx_icon('check', 12) ?> <span>Save</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-cancel" data-section="3" style="display:none;">
                                        <span>Cancel</span>
                                    </button>
                                </div>
                            </div>
                            <div class="drug-acc-content">
                                <!-- Read View -->
                                <div class="drug-acc-read-view" id="accGenRead3">
                                    <div class="drug-read-grid">
                                        <div class="drug-read-item full" id="readWrapGenWarning">
                                            <span class="drug-read-lbl drug-lbl-danger">Immediate / Black Box Warning</span>
                                            <div class="drug-read-text drug-text-warning" id="readGenWarning">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Contraindications</span>
                                            <div class="drug-read-text" id="readGenContra">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Clinical Precautions</span>
                                            <div class="drug-read-text" id="readGenPrecautions">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Adverse Effects &amp; Side Effects</span>
                                            <div class="drug-read-text" id="readGenSideEffects">-</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit View -->
                                <div class="drug-acc-edit-view" id="accGenEdit3" style="display:none;">
                                    <div class="drug-form-grid">
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenWarning" style="color: var(--zrx-danger);">Immediate / Black Box Warning</label>
                                            <textarea id="inpEditGenWarning" class="drug-textarea" rows="3" placeholder="Critical black-box warnings or life-threatening hazards..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenContra">Contraindications</label>
                                            <textarea id="inpEditGenContra" class="drug-textarea" rows="3" placeholder="Absolute contraindications..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenPrecautions">Clinical Precautions</label>
                                            <textarea id="inpEditGenPrecautions" class="drug-textarea" rows="3" placeholder="Special clinical precautions and monitoring..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenSideEffects">Adverse Effects &amp; Side Effects</label>
                                            <textarea id="inpEditGenSideEffects" class="drug-textarea" rows="3" placeholder="Common and severe adverse effects..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 4: Indications and mechanism -->
                        <div class="drug-acc-item open" id="accGen4">
                            <div class="drug-acc-header" data-section="4">
                                <div class="drug-acc-title-wrap">
                                    <span class="drug-acc-toggle-icon"><?= zrx_icon('chevron-down', 13) ?></span>
                                    <span class="drug-acc-title">4. Indications &amp; Mechanism of Action</span>
                                </div>
                                <div class="drug-acc-actions">
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-edit" data-section="4">
                                        <?= zrx_icon('edit', 12) ?> <span>Edit Section</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary drug-btn-sm drug-acc-btn-save" data-section="4" style="display:none;">
                                        <?= zrx_icon('check', 12) ?> <span>Save</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-cancel" data-section="4" style="display:none;">
                                        <span>Cancel</span>
                                    </button>
                                </div>
                            </div>
                            <div class="drug-acc-content">
                                <!-- Read View -->
                                <div class="drug-acc-read-view" id="accGenRead4">
                                    <div class="drug-read-grid">
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Clinical Indications Overview</span>
                                            <div class="drug-read-text" id="readGenIndicationText">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Mode of Action Summary</span>
                                            <div class="drug-read-text" id="readGenMoaSummary">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Pharmacodynamics / Mode of Action Flow</span>
                                            <div class="drug-read-text" id="readGenMoaFlow">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Drug Interactions</span>
                                            <div class="drug-read-text" id="readGenInteraction">-</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit View -->
                                <div class="drug-acc-edit-view" id="accGenEdit4" style="display:none;">
                                    <div class="drug-form-grid">
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenIndicationText">Clinical Indications Overview</label>
                                            <textarea id="inpEditGenIndicationText" class="drug-textarea" rows="3" placeholder="Overview of clinical indications..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenMoaSummary">Mode of Action Summary</label>
                                            <textarea id="inpEditGenMoaSummary" class="drug-textarea" rows="3" placeholder="Biochemical mechanism of action summary..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenMoaFlow">Pharmacodynamics / Mode of Action Flow</label>
                                            <textarea id="inpEditGenMoaFlow" class="drug-textarea" rows="3" placeholder="Receptor binding, enzyme inhibition, or cellular cascade flow..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenInteraction">Drug Interactions</label>
                                            <textarea id="inpEditGenInteraction" class="drug-textarea" rows="3" placeholder="Interactions with other drugs or food..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 5: Pregnancy and lactation -->
                        <div class="drug-acc-item open" id="accGen5">
                            <div class="drug-acc-header" data-section="5">
                                <div class="drug-acc-title-wrap">
                                    <span class="drug-acc-toggle-icon"><?= zrx_icon('chevron-down', 13) ?></span>
                                    <span class="drug-acc-title">5. Pregnancy &amp; Lactation Profiles</span>
                                </div>
                                <div class="drug-acc-actions">
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-edit" data-section="5">
                                        <?= zrx_icon('edit', 12) ?> <span>Edit Section</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary drug-btn-sm drug-acc-btn-save" data-section="5" style="display:none;">
                                        <?= zrx_icon('check', 12) ?> <span>Save</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-cancel" data-section="5" style="display:none;">
                                        <span>Cancel</span>
                                    </button>
                                </div>
                            </div>
                            <div class="drug-acc-content">
                                <!-- Read View -->
                                <div class="drug-acc-read-view" id="accGenRead5">
                                    <div class="drug-read-grid">
                                        <div class="drug-read-item">
                                            <span class="drug-read-lbl">US FDA Pregnancy Category</span>
                                            <span class="drug-read-val" id="readGenPregCategory">-</span>
                                        </div>
                                        <div class="drug-read-item">
                                            <span class="drug-read-lbl">Modern Narrative Category</span>
                                            <span class="drug-read-val" id="readGenPregModern">-</span>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Trimester-Specific Safety &amp; Risk Notes</span>
                                            <div class="drug-read-text" id="readGenTrimester">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Pregnancy &amp; Lactation Detailed Notes</span>
                                            <div class="drug-read-text" id="readGenLactationNote">-</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit View -->
                                <div class="drug-acc-edit-view" id="accGenEdit5" style="display:none;">
                                    <div class="drug-form-grid">
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="selEditGenPregnancy">US FDA Pregnancy Category</label>
                                            <select id="selEditGenPregnancy" class="drug-input">
                                                <option value="">None / Unclassified</option>
                                                <option value="A">A - Controlled studies show no risk</option>
                                                <option value="B">B - No evidence of human risk</option>
                                                <option value="C">C - Risk cannot be ruled out</option>
                                                <option value="D">D - Positive evidence of risk</option>
                                                <option value="X">X - Contraindicated in pregnancy</option>
                                                <option value="N">N - Not classified</option>
                                            </select>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="inpEditGenPregModern">Modern Narrative Category</label>
                                            <input type="text" id="inpEditGenPregModern" class="drug-input" placeholder="e.g. Compatible / Potential Risk">
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenTrimester">Trimester-Specific Safety</label>
                                            <textarea id="inpEditGenTrimester" class="drug-textarea" rows="2" placeholder="First, second, and third trimester guidance..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenLactationNote">Pregnancy &amp; Lactation Detailed Notes</label>
                                            <textarea id="inpEditGenLactationNote" class="drug-textarea" rows="3" placeholder="Excretion in breast milk, infant monitoring, clinical advice..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 6: Dosing and administration -->
                        <div class="drug-acc-item open" id="accGen6">
                            <div class="drug-acc-header" data-section="6">
                                <div class="drug-acc-title-wrap">
                                    <span class="drug-acc-toggle-icon"><?= zrx_icon('chevron-down', 13) ?></span>
                                    <span class="drug-acc-title">6. Dosing &amp; Clinical Administration</span>
                                </div>
                                <div class="drug-acc-actions">
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-edit" data-section="6">
                                        <?= zrx_icon('edit', 12) ?> <span>Edit Section</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary drug-btn-sm drug-acc-btn-save" data-section="6" style="display:none;">
                                        <?= zrx_icon('check', 12) ?> <span>Save</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-cancel" data-section="6" style="display:none;">
                                        <span>Cancel</span>
                                    </button>
                                </div>
                            </div>
                            <div class="drug-acc-content">
                                <!-- Read View -->
                                <div class="drug-acc-read-view" id="accGenRead6">
                                    <div class="drug-read-grid">
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Standard Adult Dose</span>
                                            <div class="drug-read-text" id="readGenAdultDose">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Child &amp; Pediatric Dose</span>
                                            <div class="drug-read-text" id="readGenChildDose">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Paediatric Calculation Parameter</span>
                                            <div class="drug-read-text" id="readGenPaedCalc">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Renal Dose Adjustment</span>
                                            <div class="drug-read-text" id="readGenRenalDose">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Mode of Administration Instructions</span>
                                            <div class="drug-read-text" id="readGenAdministration">-</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit View -->
                                <div class="drug-acc-edit-view" id="accGenEdit6" style="display:none;">
                                    <div class="drug-form-grid">
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenAdultDose">Standard Adult Dose</label>
                                            <textarea id="inpEditGenAdultDose" class="drug-textarea" rows="3" placeholder="Standard adult dosage and schedules..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenChildDose">Child &amp; Pediatric Dose</label>
                                            <textarea id="inpEditGenChildDose" class="drug-textarea" rows="3" placeholder="Weight or age-based pediatric dosage..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenPaedCalc">Paediatric Calculation Parameter</label>
                                            <textarea id="inpEditGenPaedCalc" class="drug-textarea" rows="2" placeholder="Calculation parameter (e.g. mg/kg/day divided q8h)..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenRenalDose">Renal Dose Adjustment</label>
                                            <textarea id="inpEditGenRenalDose" class="drug-textarea" rows="3" placeholder="GFR / CrCl dose adjustments..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenAdministration">Mode of Administration Instructions</label>
                                            <textarea id="inpEditGenAdministration" class="drug-textarea" rows="3" placeholder="Oral, IV, timing with meals, administration guidelines..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 7: Overdose, storage, and counselling -->
                        <div class="drug-acc-item open" id="accGen7">
                            <div class="drug-acc-header" data-section="7">
                                <div class="drug-acc-title-wrap">
                                    <span class="drug-acc-toggle-icon"><?= zrx_icon('chevron-down', 13) ?></span>
                                    <span class="drug-acc-title">7. Overdose, Storage &amp; Counselling Pearls</span>
                                </div>
                                <div class="drug-acc-actions">
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-edit" data-section="7">
                                        <?= zrx_icon('edit', 12) ?> <span>Edit Section</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary drug-btn-sm drug-acc-btn-save" data-section="7" style="display:none;">
                                        <?= zrx_icon('check', 12) ?> <span>Save</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-sm drug-acc-btn-cancel" data-section="7" style="display:none;">
                                        <span>Cancel</span>
                                    </button>
                                </div>
                            </div>
                            <div class="drug-acc-content">
                                <!-- Read View -->
                                <div class="drug-acc-read-view" id="accGenRead7">
                                    <div class="drug-read-grid">
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Overdose Manifestations &amp; Toxic Effects</span>
                                            <div class="drug-read-text" id="readGenOverdoseEffect">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Overdose Treatment &amp; Antidote Protocol</span>
                                            <div class="drug-read-text" id="readGenOverdoseTreatment">-</div>
                                        </div>
                                        <div class="drug-read-item">
                                            <span class="drug-read-lbl">Storage Conditions</span>
                                            <div class="drug-read-text" id="readGenStorage">-</div>
                                        </div>
                                        <div class="drug-read-item">
                                            <span class="drug-read-lbl">PubMed Reference Query Base</span>
                                            <div class="drug-read-text" id="readGenPubmed">-</div>
                                        </div>
                                        <div class="drug-read-item full">
                                            <span class="drug-read-lbl">Patient Counselling Pearls</span>
                                            <div class="drug-read-text" id="readGenCounselling">-</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit View -->
                                <div class="drug-acc-edit-view" id="accGenEdit7" style="display:none;">
                                    <div class="drug-form-grid">
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenOverdoseEffect">Overdose Manifestations &amp; Toxic Effects</label>
                                            <textarea id="inpEditGenOverdoseEffect" class="drug-textarea" rows="3" placeholder="Clinical signs and symptoms of acute toxicity..."></textarea>
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenOverdoseTreatment">Overdose Treatment &amp; Antidote Protocol</label>
                                            <textarea id="inpEditGenOverdoseTreatment" class="drug-textarea" rows="3" placeholder="Specific antidotes, gastric lavage, supportive measures..."></textarea>
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="inpEditGenStorage">Storage Conditions</label>
                                            <input type="text" id="inpEditGenStorage" class="drug-input" placeholder="e.g. Store below 25°C in a dry place">
                                        </div>
                                        <div class="drug-form-group">
                                            <label class="drug-label" for="inpEditGenPubmed">PubMed Query Base</label>
                                            <input type="text" id="inpEditGenPubmed" class="drug-input" placeholder="e.g. fluorouracil[mesh] AND toxicity">
                                        </div>
                                        <div class="drug-form-group full">
                                            <label class="drug-label" for="inpEditGenCounselling">Patient Counselling Pearls</label>
                                            <textarea id="inpEditGenCounselling" class="drug-textarea" rows="3" placeholder="Crucial points to communicate to the patient..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Panel: Add Generic Form -->
                    <form id="panelAddGeneric" style="display:none;" class="drug-form-grid">
                        <div class="drug-form-group full">
                            <h3 style="font-size: 14px; color: var(--zrx-text-dark); margin-bottom: 2px;">
                                <?= zrx_icon('plus', 14) ?> Add Clinical Generic Substance
                            </h3>
                            <p class="drug-hint">Register an international active pharmaceutical entity into the core database.</p>
                        </div>
                        <div class="drug-form-group full">
                            <label class="drug-label" for="inpAddGenName">Generic Name *</label>
                            <input type="text" id="inpAddGenName" class="drug-input" placeholder="e.g. Esomeprazole" required>
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="inpAddGenUsName">US Adopted Name (USAN)</label>
                            <input type="text" id="inpAddGenUsName" class="drug-input" placeholder="e.g. Esomeprazole Magnesium">
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="inpAddGenAtc">WHO ATC Code</label>
                            <input type="text" id="inpAddGenAtc" class="drug-input" placeholder="e.g. A02BC05">
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="selAddGenPregnancy">Pregnancy Category</label>
                            <select id="selAddGenPregnancy" class="drug-input">
                                <option value="">None / Unclassified</option>
                                <option value="A">A - Controlled studies show no risk</option>
                                <option value="B">B - No evidence of human risk</option>
                                <option value="C">C - Risk cannot be ruled out</option>
                                <option value="D">D - Positive evidence of risk</option>
                                <option value="X">X - Contraindicated</option>
                                <option value="N">N - Not classified</option>
                            </select>
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="selAddGenAntibiotic">Antibiotic Flag</label>
                            <select id="selAddGenAntibiotic" class="drug-input">
                                <option value="0">No</option>
                                <option value="1">Yes (Antibiotic)</option>
                            </select>
                        </div>
                        <div class="drug-form-group full">
                            <label class="drug-label" for="inpAddGenAdultDose">Adult Dosage</label>
                            <textarea id="inpAddGenAdultDose" class="drug-textarea" rows="2" placeholder="e.g. 20 mg once daily for 4-8 weeks..."></textarea>
                        </div>
                        <div class="drug-form-group full">
                            <label class="drug-label" for="inpAddGenChildDose">Child Dosage</label>
                            <textarea id="inpAddGenChildDose" class="drug-textarea" rows="2" placeholder="Weight-based dosing..."></textarea>
                        </div>
                        <div class="drug-form-group full">
                            <label class="drug-label" for="inpAddGenContra">Contraindications</label>
                            <textarea id="inpAddGenContra" class="drug-textarea" rows="2" placeholder="Known hypersensitivity to substituted benzimidazoles..."></textarea>
                        </div>
                        <div class="drug-form-group full" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                            <button type="button" class="drug-btn" id="btnCancelAddGen">Cancel</button>
                            <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitAddGen">
                                <?= zrx_icon('check', 13) ?>
                                <span>Create Generic</span>
                            </button>
                        </div>
                    </form>

                    <!-- Panel: Classification Inspector -->
                    <div id="panelClassInspector" style="display:none;">
                        <input type="hidden" id="inpEditClassId">
                        
                        <!-- Read View -->
                        <div id="classReadView" class="drug-read-grid" style="margin-bottom: 16px;">
                            <div class="drug-read-item full">
                                <span class="drug-read-lbl">Classification Name</span>
                                <span class="drug-read-val drug-val-title" id="readClassName">-</span>
                            </div>
                            <div class="drug-read-item">
                                <span class="drug-read-lbl">Category</span>
                                <span class="drug-read-val" id="readClassCategory">-</span>
                            </div>
                            <div class="drug-read-item">
                                <span class="drug-read-lbl">Subcategory</span>
                                <span class="drug-read-val" id="readClassSubcategory">-</span>
                            </div>
                            <div class="drug-read-item full">
                                <span class="drug-read-lbl">Linked Active Generics</span>
                                <div class="drug-tags-wrap" id="readClassGenericsTags"></div>
                            </div>
                        </div>

                        <!-- Edit View (Collapsible) -->
                        <div id="classEditView" style="display:none;">
                            <div class="drug-form-grid">
                                <div class="drug-form-group full">
                                    <label class="drug-label" for="inpEditClassName">Classification Name *</label>
                                    <input type="text" id="inpEditClassName" class="drug-input" required>
                                </div>
                                <div class="drug-form-group">
                                    <label class="drug-label" for="inpEditClassCategory">Category</label>
                                    <input type="text" id="inpEditClassCategory" class="drug-input" placeholder="e.g. Alimentary tract and metabolism">
                                </div>
                                <div class="drug-form-group">
                                    <label class="drug-label" for="inpEditClassSubcategory">Subcategory</label>
                                    <input type="text" id="inpEditClassSubcategory" class="drug-input" placeholder="e.g. Drugs for acid related disorders">
                                </div>
                                <div class="drug-form-group full">
                                    <label class="drug-label">Linked Generics</label>
                                    <div style="position: relative;">
                                        <input type="text" id="inpSearchClassLinkGen" class="drug-input" placeholder="Search generics to link to this class...">
                                        <div id="dropClassLinkGen" class="zrx-dropdown" style="display:none; width: 100%;"></div>
                                    </div>
                                    <div class="drug-tags-wrap" id="tagsClassGenerics"></div>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--zrx-border-subtle);">
                            <button type="button" class="drug-btn drug-btn-danger drug-btn-sm" id="btnDeleteClass">
                                <?= zrx_icon('trash', 12) ?>
                                <span>Delete Classification</span>
                            </button>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="drug-btn drug-btn-sm" id="btnToggleEditClass">
                                    <?= zrx_icon('edit', 12) ?>
                                    <span id="lblToggleEditClass">Edit</span>
                                </button>
                                <button type="button" class="drug-btn drug-btn-primary" id="btnSaveClass" style="display:none;">
                                    <?= zrx_icon('check', 13) ?>
                                    <span>Save Changes</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel: Add Classification Form -->
                    <form id="panelAddClass" style="display:none;" class="drug-form-grid">
                        <div class="drug-form-group full">
                            <h3 style="font-size: 14px; color: var(--zrx-text-dark); margin-bottom: 2px;">
                                <?= zrx_icon('plus', 14) ?> Create Drug Classification
                            </h3>
                            <p class="drug-hint">Define a therapeutic grouping and categorize pharmaceutical entities.</p>
                        </div>
                        <div class="drug-form-group full">
                            <label class="drug-label" for="inpAddClassName">Classification Name *</label>
                            <input type="text" id="inpAddClassName" class="drug-input" placeholder="e.g. Proton Pump Inhibitors" required>
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="inpAddClassCategory">Category Name</label>
                            <input type="text" id="inpAddClassCategory" class="drug-input" placeholder="e.g. Gastrointestinal system drugs">
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="inpAddClassSubcategory">Subcategory Name</label>
                            <input type="text" id="inpAddClassSubcategory" class="drug-input" placeholder="e.g. Drugs for Peptic Ulcer">
                        </div>
                        <div class="drug-form-group full" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                            <button type="button" class="drug-btn" id="btnCancelAddClass">Cancel</button>
                            <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitAddClass">
                                <?= zrx_icon('check', 13) ?>
                                <span>Create Classification</span>
                            </button>
                        </div>
                    </form>

                    <!-- Panel: Indication Inspector -->
                    <div id="panelIndInspector" style="display:none;">
                        <input type="hidden" id="inpEditIndId">
                        
                        <!-- Read View -->
                        <div id="indReadView" class="drug-read-grid" style="margin-bottom: 16px;">
                            <div class="drug-read-item full">
                                <span class="drug-read-lbl">Indication Name</span>
                                <span class="drug-read-val drug-val-title" id="readIndName">-</span>
                            </div>
                            <div class="drug-read-item full">
                                <span class="drug-read-lbl">Clinical Definition / Notes</span>
                                <div class="drug-read-text" id="readIndDesc">-</div>
                            </div>
                            <div class="drug-read-item full">
                                <span class="drug-read-lbl">Linked Active Generics</span>
                                <div class="drug-tags-wrap" id="readIndGenericsTags"></div>
                            </div>
                        </div>

                        <!-- Edit View (Collapsible) -->
                        <div id="indEditView" style="display:none;">
                            <div class="drug-form-grid">
                                <div class="drug-form-group full">
                                    <label class="drug-label" for="inpEditIndName">Indication Name *</label>
                                    <input type="text" id="inpEditIndName" class="drug-input" required>
                                </div>
                                <div class="drug-form-group full">
                                    <label class="drug-label" for="inpEditIndDesc">Description / Clinical Definition</label>
                                    <textarea id="inpEditIndDesc" class="drug-textarea" rows="3" placeholder="Clinical definition of disease or syndrome..."></textarea>
                                </div>
                                <div class="drug-form-group full">
                                    <label class="drug-label">Linked Generics</label>
                                    <div style="position: relative;">
                                        <input type="text" id="inpSearchIndLinkGen" class="drug-input" placeholder="Search generics to link to this indication...">
                                        <div id="dropIndLinkGen" class="zrx-dropdown" style="display:none; width: 100%;"></div>
                                    </div>
                                    <div class="drug-tags-wrap" id="tagsIndGenerics"></div>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--zrx-border-subtle);">
                            <button type="button" class="drug-btn drug-btn-danger drug-btn-sm" id="btnDeleteInd">
                                <?= zrx_icon('trash', 12) ?>
                                <span>Delete Indication</span>
                            </button>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="drug-btn drug-btn-sm" id="btnToggleEditInd">
                                    <?= zrx_icon('edit', 12) ?>
                                    <span id="lblToggleEditInd">Edit</span>
                                </button>
                                <button type="button" class="drug-btn drug-btn-primary" id="btnSaveInd" style="display:none;">
                                    <?= zrx_icon('check', 13) ?>
                                    <span>Save Changes</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel: Add Indication Form -->
                    <form id="panelAddIndication" style="display:none;" class="drug-form-grid">
                        <div class="drug-form-group full">
                            <h3 style="font-size: 14px; color: var(--zrx-text-dark); margin-bottom: 2px;">
                                <?= zrx_icon('plus', 14) ?> Add Clinical Indication
                            </h3>
                            <p class="drug-hint">Define a medical condition, disease, or syndrome treated by pharmaceutical agents.</p>
                        </div>
                        <div class="drug-form-group full">
                            <label class="drug-label" for="inpAddIndName">Indication Name *</label>
                            <input type="text" id="inpAddIndName" class="drug-input" placeholder="e.g. Duodenal Ulcer" required>
                        </div>
                        <div class="drug-form-group full">
                            <label class="drug-label" for="inpAddIndDesc">Description / Clinical Notes</label>
                            <textarea id="inpAddIndDesc" class="drug-textarea" rows="3" placeholder="Clinical notes, diagnostic criteria..."></textarea>
                        </div>
                        <div class="drug-form-group full" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                            <button type="button" class="drug-btn" id="btnCancelAddInd">Cancel</button>
                            <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitAddInd">
                                <?= zrx_icon('check', 13) ?>
                                <span>Create Indication</span>
                            </button>
                        </div>
                    </form>

                </div>
            </section>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 2. FORMULARIES WORKSPACE                                              -->
    <!-- ===================================================================== -->
    <div id="wsFormularies" class="drug-subworkspace" style="display:none;">

        <!-- Country Selector Hub Screen -->
        <div id="formulariesCountryHub" class="drug-countries-hub" style="display:none;">
            <div class="drug-ch-header">
                <div>
                    <h2 class="drug-ch-title">National Commercial Formularies</h2>
                    <p class="drug-ch-subtitle">Select an active country commercial formulary pack or initialize a new regional trade-brand catalog.</p>
                </div>
                <button type="button" class="drug-btn drug-btn-primary" id="btnOpenAddCountry">
                    <?= zrx_icon('plus', 13) ?>
                    <span>Add Country Pack</span>
                </button>
            </div>
            <div class="drug-country-cards-grid" id="countryCardsGrid">
                <div class="drug-empty-state drug-col-full">Loading country catalogs...</div>
            </div>
        </div>

        <!-- Single Country Workspace View -->
        <div id="formulariesCountryWorkspace" class="drug-country-ws" style="display:none;">

            <!-- Formularies Meta Subbar -->
            <div class="drug-meta-subbar">
                <div class="drug-meta-left">
                    <button type="button" class="drug-btn-back" id="btnBackToCountryHub" title="Return to Countries list">
                        <?= zrx_icon('arrow-left', 14) ?>
                        <span>Packs</span>
                    </button>
                    <div class="drug-scope-badge-wrap">
                        <span class="drug-scope-code" id="wsActiveCountryCode">BD</span>
                        <div class="drug-scope-titles">
                            <span class="drug-scope-title" id="wsActiveCountryTitle">Bangladesh Commercial Formulary</span>
                            <span class="drug-scope-subtitle">Commercial Trade Names, Strengths, Pack Sizes &amp; Manufacturers</span>
                        </div>
                    </div>
                </div>
                <div class="drug-meta-right">
                    <span class="drug-meta-item">
                        <?= zrx_icon('user', 12) ?>
                        <span>Alif Farzan Zim</span>
                    </span>
                    <span class="drug-meta-item">
                        <?= zrx_icon('clock', 12) ?>
                        <span><?= date('Y-m-d') ?></span>
                    </span>
                    <span class="drug-status-badge-live" id="formularySaveStatusBadge">All saved</span>
                </div>
            </div>

            <!-- Formularies Tabs Bar -->
            <div class="drug-ws-tabs-bar">
                <div class="drug-ws-tabs" id="tabsFormularies">
                    <button type="button" class="drug-ws-tab active" data-tab="tabBrandsList">
                        <?= zrx_icon('globe', 13) ?>
                        <span>Formularies</span>
                    </button>
                    <button type="button" class="drug-ws-tab" data-tab="tabBrandsAdd">
                        <?= zrx_icon('plus', 13) ?>
                        <span>Add a Trade Name</span>
                    </button>
                    <button type="button" class="drug-ws-tab" data-tab="tabMfgList">
                        <?= zrx_icon('building', 13) ?>
                        <span>Manufacturers</span>
                    </button>
                    <button type="button" class="drug-ws-tab" data-tab="tabMfgAdd">
                        <?= zrx_icon('plus', 13) ?>
                        <span>Add a Manufacturer</span>
                    </button>
                </div>
                <div class="drug-ws-meta">
                    <span class="drug-scope-pill" id="activeCountryBadge">BD Pack</span>
                </div>
            </div>

            <div class="drug-split-pane">
                <!-- Left Brands / Manufacturers List -->
                <section class="drug-card drug-list-pane" id="brandsListPane">
                    <div class="drug-card-header">
                        <span id="brandListHeaderTitle">Trade Brands</span>
                        <span class="drug-badge-count" id="brandListCount">0</span>
                    </div>
                    <div class="drug-search-box">
                        <input type="text" id="brandSearchInput" class="drug-input drug-input-search" placeholder="Search trade brand or manufacturer...">
                        <button type="button" class="drug-btn drug-btn-sm" id="btnBrandClearSearch" title="Clear search">
                            <?= zrx_icon('x', 11) ?>
                        </button>
                    </div>
                    <div class="drug-scroll-list" id="brandItemsList">
                        <div class="drug-empty-state">Loading trade names...</div>
                    </div>
                </section>

                <!-- Right Brand Detail / Form Pane -->
                <section class="drug-card drug-detail-pane" id="brandsDetailPane">
                    <div class="drug-card-header">
                        <span id="brandDetailHeaderTitle">Brand Inspector</span>
                        <div id="brandDetailHeaderActions" style="display: flex; gap: 6px; align-items: center;"></div>
                    </div>

                    <div class="drug-detail-scroll" id="brandDetailContainer">

                        <!-- Empty State -->
                        <div id="brandEmptyState" class="drug-empty-state">
                            Select a commercial trade brand or pharmaceutical manufacturer from the list to inspect metadata.
                        </div>

                        <!-- Panel: Brand Inspector -->
                        <div id="panelBrandInspector" style="display:none;">
                            <input type="hidden" id="inpEditBrandId">

                            <!-- Read View -->
                            <div id="brandReadView" class="drug-read-grid" style="margin-bottom: 16px;">
                                <div class="drug-read-item full">
                                    <span class="drug-read-lbl">Trade / Commercial Brand Name</span>
                                    <span class="drug-read-val drug-val-title" id="readBrandName">-</span>
                                </div>
                                <div class="drug-read-item">
                                    <span class="drug-read-lbl">Dosage Form</span>
                                    <span class="drug-read-val" id="readBrandForm">-</span>
                                </div>
                                <div class="drug-read-item">
                                    <span class="drug-read-lbl">Strength / Concentration</span>
                                    <span class="drug-read-val" id="readBrandStrength">-</span>
                                </div>
                                <div class="drug-read-item full">
                                    <span class="drug-read-lbl">Active Core Generic</span>
                                    <span class="drug-read-val" id="readBrandGeneric">-</span>
                                </div>
                                <div class="drug-read-item full">
                                    <span class="drug-read-lbl">Pharmaceutical Manufacturer</span>
                                    <span class="drug-read-val" id="readBrandMfg">-</span>
                                </div>
                                <div class="drug-read-item">
                                    <span class="drug-read-lbl">Package Presentation / Size</span>
                                    <span class="drug-read-val" id="readBrandPack">-</span>
                                </div>
                                <div class="drug-read-item">
                                    <span class="drug-read-lbl">Unit Retail Price</span>
                                    <span class="drug-read-val" id="readBrandPrice">-</span>
                                </div>
                            </div>

                            <!-- Edit View (Collapsible) -->
                            <div id="brandEditView" style="display:none;">
                                <div class="drug-form-grid">
                                    <div class="drug-form-group full">
                                        <label class="drug-label" for="inpEditBrandName">Trade / Brand Name *</label>
                                        <input type="text" id="inpEditBrandName" class="drug-input" required>
                                    </div>
                                    <div class="drug-form-group">
                                        <label class="drug-label" for="selEditBrandForm">Dosage Form *</label>
                                        <select id="selEditBrandForm" class="drug-input" required></select>
                                    </div>
                                    <div class="drug-form-group">
                                        <label class="drug-label" for="inpEditBrandStrength">Strength *</label>
                                        <input type="text" id="inpEditBrandStrength" class="drug-input" placeholder="e.g. 20 mg, 500 mg / 5 ml" required>
                                    </div>
                                    <div class="drug-form-group full">
                                        <label class="drug-label">Active Generic Mapping *</label>
                                        <div style="position: relative;">
                                            <input type="text" id="inpSearchEditBrandGen" class="drug-input" placeholder="Search active generic...">
                                            <div id="dropEditBrandGen" class="zrx-dropdown" style="display:none; width: 100%;"></div>
                                        </div>
                                        <input type="hidden" id="inpEditBrandGenId">
                                        <div id="tagEditBrandGen" style="margin-top: 6px;"></div>
                                    </div>
                                    <div class="drug-form-group full">
                                        <label class="drug-label">Manufacturer Mapping *</label>
                                        <div style="position: relative;">
                                            <input type="text" id="inpSearchEditBrandMfg" class="drug-input" placeholder="Search manufacturer...">
                                            <div id="dropEditBrandMfg" class="zrx-dropdown" style="display:none; width: 100%;"></div>
                                        </div>
                                        <input type="hidden" id="inpEditBrandMfgId">
                                        <div id="tagEditBrandMfg" style="margin-top: 6px;"></div>
                                    </div>
                                    <div class="drug-form-group">
                                        <label class="drug-label" for="inpEditBrandPack">Packaging / Size</label>
                                        <input type="text" id="inpEditBrandPack" class="drug-input" placeholder="e.g. 10x10 Strip">
                                    </div>
                                    <div class="drug-form-group">
                                        <label class="drug-label" for="inpEditBrandPrice">Unit Price</label>
                                        <input type="text" id="inpEditBrandPrice" class="drug-input" placeholder="e.g. 5.00">
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--zrx-border-subtle);">
                                <button type="button" class="drug-btn drug-btn-danger drug-btn-sm" id="btnDeleteBrand">
                                    <?= zrx_icon('trash', 12) ?>
                                    <span>Delete Trade Name</span>
                                </button>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="drug-btn drug-btn-sm" id="btnToggleEditBrand">
                                        <?= zrx_icon('edit', 12) ?>
                                        <span id="lblToggleEditBrand">Edit</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary" id="btnSaveBrand" style="display:none;">
                                        <?= zrx_icon('check', 13) ?>
                                        <span>Save Trade Name</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Panel: Add Brand Form -->
                        <form id="panelAddBrand" style="display:none;" class="drug-form-grid">
                            <div class="drug-form-group full">
                                <h3 style="font-size: 14px; color: var(--zrx-text-dark); margin-bottom: 2px;">
                                    <?= zrx_icon('plus', 14) ?> Add Commercial Trade Name
                                </h3>
                                <p class="drug-hint">Register a branded commercial pharmaceutical package linked to a core generic.</p>
                            </div>
                            <div class="drug-form-group full">
                                <label class="drug-label" for="inpAddBrandName">Trade Brand Name *</label>
                                <input type="text" id="inpAddBrandName" class="drug-input" placeholder="e.g. Nexum" required>
                            </div>
                            <div class="drug-form-group">
                                <label class="drug-label" for="selAddBrandForm">Dosage Form *</label>
                                <select id="selAddBrandForm" class="drug-input" required></select>
                            </div>
                            <div class="drug-form-group">
                                <label class="drug-label" for="inpAddBrandStrength">Strength *</label>
                                <input type="text" id="inpAddBrandStrength" class="drug-input" placeholder="e.g. 20 mg" required>
                            </div>
                            <div class="drug-form-group full">
                                <label class="drug-label">Active Generic *</label>
                                <div style="position: relative;">
                                    <input type="text" id="inpSearchAddBrandGen" class="drug-input" placeholder="Type generic name to search..." required>
                                    <div id="dropAddBrandGen" class="zrx-dropdown" style="display:none; width: 100%;"></div>
                                </div>
                                <input type="hidden" id="inpAddBrandGenId" required>
                                <div id="tagAddBrandGen" style="margin-top: 6px;"></div>
                            </div>
                            <div class="drug-form-group full">
                                <label class="drug-label">Manufacturer *</label>
                                <div style="position: relative;">
                                    <input type="text" id="inpSearchAddBrandMfg" class="drug-input" placeholder="Type manufacturer to search..." required>
                                    <div id="dropAddBrandMfg" class="zrx-dropdown" style="display:none; width: 100%;"></div>
                                </div>
                                <input type="hidden" id="inpAddBrandMfgId" required>
                                <div id="tagAddBrandMfg" style="margin-top: 6px;"></div>
                            </div>
                            <div class="drug-form-group">
                                <label class="drug-label" for="inpAddBrandPack">Package Size</label>
                                <input type="text" id="inpAddBrandPack" class="drug-input" placeholder="e.g. 10x10 Tablets">
                            </div>
                            <div class="drug-form-group">
                                <label class="drug-label" for="inpAddBrandPrice">Unit Price</label>
                                <input type="text" id="inpAddBrandPrice" class="drug-input" placeholder="e.g. 7.00">
                            </div>
                            <div class="drug-form-group full" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                                <button type="button" class="drug-btn" id="btnCancelAddBrand">Cancel</button>
                                <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitAddBrand">
                                    <?= zrx_icon('check', 13) ?>
                                    <span>Create Trade Name</span>
                                </button>
                            </div>
                        </form>

                        <!-- Panel: Manufacturer Inspector -->
                        <div id="panelMfgInspector" style="display:none;">
                            <input type="hidden" id="inpEditMfgId">

                            <!-- Read View -->
                            <div id="mfgReadView" class="drug-read-grid" style="margin-bottom: 16px;">
                                <div class="drug-read-item full">
                                    <span class="drug-read-lbl">Pharmaceutical Manufacturer Name</span>
                                    <span class="drug-read-val drug-val-title" id="readMfgName">-</span>
                                </div>
                                <div class="drug-read-item">
                                    <span class="drug-read-lbl">Short / Trading Name</span>
                                    <span class="drug-read-val" id="readMfgShort">-</span>
                                </div>
                                <div class="drug-read-item">
                                    <span class="drug-read-lbl">Country Code</span>
                                    <span class="drug-read-val" id="readMfgCountry">-</span>
                                </div>
                                <div class="drug-read-item">
                                    <span class="drug-read-lbl">Total Commercial Brands</span>
                                    <span class="drug-read-val" id="readMfgBrandCount">0</span>
                                </div>
                            </div>

                            <!-- Edit View (Collapsible) -->
                            <div id="mfgEditView" style="display:none;">
                                <div class="drug-form-grid">
                                    <div class="drug-form-group full">
                                        <label class="drug-label" for="inpEditMfgName">Manufacturer Name *</label>
                                        <input type="text" id="inpEditMfgName" class="drug-input" required>
                                    </div>
                                    <div class="drug-form-group">
                                        <label class="drug-label" for="inpEditMfgShort">Short Name</label>
                                        <input type="text" id="inpEditMfgShort" class="drug-input" placeholder="e.g. Square">
                                    </div>
                                    <div class="drug-form-group">
                                        <label class="drug-label" for="inpEditMfgCountry">Country Code</label>
                                        <input type="text" id="inpEditMfgCountry" class="drug-input drug-input-readonly" readonly>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--zrx-border-subtle);">
                                <button type="button" class="drug-btn drug-btn-danger drug-btn-sm" id="btnDeleteMfg">
                                    <?= zrx_icon('trash', 12) ?>
                                    <span>Delete Manufacturer</span>
                                </button>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="drug-btn drug-btn-sm" id="btnToggleEditMfg">
                                        <?= zrx_icon('edit', 12) ?>
                                        <span id="lblToggleEditMfg">Edit</span>
                                    </button>
                                    <button type="button" class="drug-btn drug-btn-primary" id="btnSaveMfg" style="display:none;">
                                        <?= zrx_icon('check', 13) ?>
                                        <span>Save Manufacturer</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Panel: Add Manufacturer Form -->
                        <form id="panelAddMfg" style="display:none;" class="drug-form-grid">
                            <div class="drug-form-group full">
                                <h3 style="font-size: 14px; color: var(--zrx-text-dark); margin-bottom: 2px;">
                                    <?= zrx_icon('plus', 14) ?> Add Pharmaceutical Manufacturer
                                </h3>
                                <p class="drug-hint">Register a licensed pharmaceutical manufacturing laboratory.</p>
                            </div>
                            <div class="drug-form-group full">
                                <label class="drug-label" for="inpAddMfgName">Company / Manufacturer Name *</label>
                                <input type="text" id="inpAddMfgName" class="drug-input" placeholder="e.g. Square Pharmaceuticals PLC" required>
                            </div>
                            <div class="drug-form-group">
                                <label class="drug-label" for="inpAddMfgShort">Short / Trading Name</label>
                                <input type="text" id="inpAddMfgShort" class="drug-input" placeholder="e.g. Square">
                            </div>
                            <div class="drug-form-group full" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                                <button type="button" class="drug-btn" id="btnCancelAddMfg">Cancel</button>
                                <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitAddMfg">
                                    <?= zrx_icon('check', 13) ?>
                                    <span>Create Manufacturer</span>
                                </button>
                            </div>
                        </form>

                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 3. DOSAGE FORMS WORKSPACE                                             -->
    <!-- ===================================================================== -->
    <div id="wsDosageForms" class="drug-subworkspace" style="display:none;">

        <!-- Dosage Forms Meta Subbar -->
        <div class="drug-meta-subbar">
            <div class="drug-meta-left">
                <button type="button" class="drug-btn-back" id="btnDosageBackToHub" title="Return to Studio Hub">
                    <?= zrx_icon('arrow-left', 14) ?>
                    <span>Hub</span>
                </button>
                <div class="drug-scope-badge-wrap">
                    <span class="drug-scope-code">FORMS</span>
                    <div class="drug-scope-titles">
                        <span class="drug-scope-title">Standardized Dosage Forms &amp; Delivery</span>
                        <span class="drug-scope-subtitle">Prescription Delivery Prefixes, Suffixes &amp; Strength Appending Rules</span>
                    </div>
                </div>
            </div>
            <div class="drug-meta-right">
                <span class="drug-meta-item">
                    <?= zrx_icon('user', 12) ?>
                    <span>Alif Farzan Zim</span>
                </span>
                <span class="drug-meta-item">
                    <?= zrx_icon('clock', 12) ?>
                    <span><?= date('Y-m-d') ?></span>
                </span>
                <span class="drug-status-badge-live" id="dosageSaveStatusBadge">All saved</span>
            </div>
        </div>

        <!-- Dosage Forms Tabs Bar -->
        <div class="drug-ws-tabs-bar">
            <div class="drug-ws-tabs" id="tabsDosageForms">
                <button type="button" class="drug-ws-tab active" data-tab="tabFormsList">
                    <?= zrx_icon('capsules', 13) ?>
                    <span>Dosage Forms</span>
                </button>
                <button type="button" class="drug-ws-tab" data-tab="tabFormsAdd">
                    <?= zrx_icon('plus', 13) ?>
                    <span>Add Dosage Form</span>
                </button>
            </div>
            <div class="drug-ws-meta">
                <span class="drug-scope-pill">Clinical Delivery Standards</span>
            </div>
        </div>

        <div class="drug-split-pane">
            <!-- Left Dosage Forms List -->
            <section class="drug-card drug-list-pane" id="formsListPane">
                <div class="drug-card-header">
                    <span>Dosage Forms Catalog</span>
                    <span class="drug-badge-count" id="formsListCount">0</span>
                </div>
                <div class="drug-search-box">
                    <input type="text" id="formsSearchInput" class="drug-input drug-input-search" placeholder="Search dosage form or abbreviation...">
                    <button type="button" class="drug-btn drug-btn-sm" id="btnFormsClearSearch" title="Clear search">
                        <?= zrx_icon('x', 11) ?>
                    </button>
                </div>
                <div class="drug-scroll-list" id="formsItemsList">
                    <div class="drug-empty-state">Loading dosage forms...</div>
                </div>
            </section>

            <!-- Right Dosage Form Detail / Edit Pane -->
            <section class="drug-card drug-detail-pane" id="formsDetailPane">
                <div class="drug-card-header">
                    <span id="formsDetailHeaderTitle">Dosage Form Inspector</span>
                    <div id="formsDetailHeaderActions" style="display: flex; gap: 6px; align-items: center;"></div>
                </div>

                <div class="drug-detail-scroll" id="formsDetailContainer">

                    <!-- Empty State -->
                    <div id="formsEmptyState" class="drug-empty-state">
                        Select a dosage form on the left to inspect prescription prefixes and display rules.
                    </div>

                    <!-- Panel: Dosage Form Inspector -->
                    <div id="panelDosageInspector" style="display:none;">
                        <input type="hidden" id="inpEditDosageId">

                        <!-- Read View -->
                        <div id="dosageReadView" class="drug-read-grid" style="margin-bottom: 16px;">
                            <div class="drug-read-item full">
                                <span class="drug-read-lbl">Dosage Form Name</span>
                                <span class="drug-read-val drug-val-title" id="readDosageName">-</span>
                            </div>
                            <div class="drug-read-item">
                                <span class="drug-read-lbl">Short Prescription Prefix</span>
                                <span class="drug-read-val" id="readDosagePrefix">-</span>
                            </div>
                            <div class="drug-read-item">
                                <span class="drug-read-lbl">Full Formal Prefix</span>
                                <span class="drug-read-val" id="readDosageFullPrefix">-</span>
                            </div>
                            <div class="drug-read-item">
                                <span class="drug-read-lbl">Append Strength Rule</span>
                                <span class="drug-read-val" id="readDosageAppendRule">-</span>
                            </div>
                            <div class="drug-read-item">
                                <span class="drug-read-lbl">Display Order Weight</span>
                                <span class="drug-read-val" id="readDosageOrder">0</span>
                            </div>
                        </div>

                        <!-- Edit View (Collapsible) -->
                        <div id="dosageEditView" style="display:none;">
                            <div class="drug-form-grid">
                                <div class="drug-form-group full">
                                    <label class="drug-label" for="inpEditDosageName">Dosage Form Name *</label>
                                    <input type="text" id="inpEditDosageName" class="drug-input" required>
                                </div>
                                <div class="drug-form-group">
                                    <label class="drug-label" for="inpEditDosagePrefix">Short Prescription Prefix</label>
                                    <input type="text" id="inpEditDosagePrefix" class="drug-input" placeholder="e.g. Tab., Cap., Syr.">
                                </div>
                                <div class="drug-form-group">
                                    <label class="drug-label" for="inpEditDosageFullPrefix">Full Formal Prefix</label>
                                    <input type="text" id="inpEditDosageFullPrefix" class="drug-input" placeholder="e.g. Tablet, Capsule, Syrup">
                                </div>
                                <div class="drug-form-group">
                                    <label class="drug-label" for="inpEditDosageOrder">Display Order Weight</label>
                                    <input type="number" id="inpEditDosageOrder" class="drug-input" value="0">
                                </div>
                                <div class="drug-form-group">
                                    <label class="drug-label" for="selEditDosageAppendRule">Append Strength Rule</label>
                                    <select id="selEditDosageAppendRule" class="drug-input">
                                        <option value="1">Yes (e.g. Tab. Napa 500mg)</option>
                                        <option value="0">No (Do not append strength)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--zrx-border-subtle);">
                            <button type="button" class="drug-btn drug-btn-danger drug-btn-sm" id="btnDeleteDosage">
                                <?= zrx_icon('trash', 12) ?>
                                <span>Delete Dosage Form</span>
                            </button>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="drug-btn drug-btn-sm" id="btnToggleEditDosage">
                                    <?= zrx_icon('edit', 12) ?>
                                    <span id="lblToggleEditDosage">Edit</span>
                                </button>
                                <button type="button" class="drug-btn drug-btn-primary" id="btnSaveDosage" style="display:none;">
                                    <?= zrx_icon('check', 13) ?>
                                    <span>Save Dosage Form</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel: Add Dosage Form Form -->
                    <form id="panelAddDosage" style="display:none;" class="drug-form-grid">
                        <div class="drug-form-group full">
                            <h3 style="font-size: 14px; color: var(--zrx-text-dark); margin-bottom: 2px;">
                                <?= zrx_icon('plus', 14) ?> Add Standard Dosage Form
                            </h3>
                            <p class="drug-hint">Define standardized pharmaceutical presentation formatting for clinical prescriptions.</p>
                        </div>
                        <div class="drug-form-group full">
                            <label class="drug-label" for="inpAddDosageName">Dosage Form Name *</label>
                            <input type="text" id="inpAddDosageName" class="drug-input" placeholder="e.g. Tablet, Oral Suspension" required>
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="inpAddDosagePrefix">Short Prefix</label>
                            <input type="text" id="inpAddDosagePrefix" class="drug-input" placeholder="e.g. Tab.">
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="inpAddDosageFullPrefix">Full Formal Prefix</label>
                            <input type="text" id="inpAddDosageFullPrefix" class="drug-input" placeholder="e.g. Tablet">
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="inpAddDosageOrder">Display Order</label>
                            <input type="number" id="inpAddDosageOrder" class="drug-input" value="0">
                        </div>
                        <div class="drug-form-group">
                            <label class="drug-label" for="selAddDosageAppendRule">Append Strength Rule</label>
                            <select id="selAddDosageAppendRule" class="drug-input">
                                <option value="1">Yes (Append strength in prescription line)</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="drug-form-group full" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                            <button type="button" class="drug-btn" id="btnCancelAddDosage">Cancel</button>
                            <button type="submit" class="drug-btn drug-btn-primary" id="btnSubmitAddDosage">
                                <?= zrx_icon('check', 13) ?>
                                <span>Create Dosage Form</span>
                            </button>
                        </div>
                    </form>

                </div>
            </section>
        </div>
    </div>

</div>
