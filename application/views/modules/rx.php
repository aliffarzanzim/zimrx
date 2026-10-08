<?php
declare(strict_types=1);

// Prescription (Rx) module: medication grid, dosage/instruction inputs, warning infobar, and Rx settings modal.
?>
<div class="rx-wrapper">
    <!-- Top Info Bar -->
    <div id="rx-info-bar" class="rx-info-bar" title="Drug details will appear here">
        <div class="rx-info-empty">Drug details and selected warnings will appear here. Interaction display is controlled from Rx settings.</div>
    </div>

    <!-- Top Action Bar -->
    <div class="rx-top-bar" id="rx-top-bar">
        <div class="rx-symbol">Rx</div>
        <button class="btn btn-outline btn-sm rx-btn-dx">Drugs by Dx</button>

        <div class="rx-search-group">
            <div class="rx-search-box">
                <?= zrx_icon('search', 14) ?>
                <input type="text" class="rx-template-input rx-drug-template-input" placeholder="Rx Template" autocomplete="off">
            </div>
            <div class="rx-search-box">
                <?= zrx_icon('search', 14) ?>
                <input type="text" placeholder="Full Prescription Template">
            </div>
            <button type="button" class="rx-settings-btn" id="rx-settings-open" title="Rx Settings" aria-haspopup="dialog" aria-controls="rx-settings-modal">
                <?= zrx_icon('settings', 14) ?>
            </button>
        </div>
    </div>

    <!-- Table Grid -->
    <div class="rx-table-container">
        <table class="rx-table" id="rx-table">
            <colgroup>
                <col class="rx-col-drag">
                <col class="rx-col-del">
                <col class="rx-col-no">
                <col>
                <col class="rx-col-generic">
                <col class="rx-col-dose">
                <col class="rx-col-instruction">
                <col class="rx-col-duration">
            </colgroup>
            <thead>
                <tr>
                    <th class="rx-th-drag"></th>
                    <th class="rx-th-del"></th>
                    <th class="rx-th-no">#</th>
                    <th>Brand</th>
                    <th class="rx-th-generic">Generic</th>
                    <th class="rx-th-dose">Dose</th>
                    <th class="rx-th-instruction">Instruction</th>
                    <th class="rx-th-duration">Duration</th>
                </tr>
            </thead>
            <tbody id="rx-tbody">
                <?php for($i=1; $i<=10; $i++): ?>
                <tr class="pc-row rx-row" draggable="true">
                    <td class="rx-action rx-drag pc-action pc-drag">
                        <button type="button" class="pc-row-move-btn rx-row-move-btn zrx-drag-handle" title="Move Row">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                    <td class="rx-action rx-del pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="rx-action rx-no pc-row-no"><?= $i ?></td>
                    <td>
                        <textarea class="rx-input rx-brand-input" autocomplete="off" rows="1"></textarea>
                        <input type="hidden" name="brand_id[]" class="brand_id">
                    </td>
                    <td><textarea class="rx-input rx-generic-input" autocomplete="off" rows="1"></textarea></td>
                    <td><textarea class="rx-input rx-dose-input" autocomplete="off" rows="1"></textarea></td>
                    <td><textarea class="rx-input rx-instruction-input" autocomplete="off" rows="1"></textarea></td>
                    <td><textarea class="rx-input rx-duration-input" autocomplete="off" rows="1"></textarea></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- Floating Footer Button -->
    <div class="rx-add-row">
        <button type="button" id="rx-add-more-btn" class="rx-add-row-btn">Add More</button>
    </div>
</div>

<div class="rx-settings-modal" id="rx-settings-modal" hidden>
    <div class="rx-settings-backdrop" data-rx-settings-close></div>
    <div class="rx-settings-panel" role="dialog" aria-modal="true" aria-labelledby="rx-settings-title">
        <div class="rx-settings-header">
            <div>
                <h3 id="rx-settings-title">Rx Settings</h3>
                <p>Prescription row display and drug form prefix preferences.</p>
            </div>
            <button type="button" class="rx-settings-close" data-rx-settings-close aria-label="Close Rx Settings">&times;</button>
        </div>

        <div class="rx-settings-body">
            <section class="rx-settings-card">
                <h4>Generic Name Formats</h4>
                <p>Choose how the generic name appears in the prescription row.</p>
                <div class="rx-segmented">
                    <label>
                        <input type="radio" name="rx-generic-name-format" value="plain" disabled>
                        <span>
                            <strong>Generic Name</strong>
                            <small>Plain generic name, e.g. Paracetamol.</small>
                        </span>
                    </label>
                    <label>
                        <input type="radio" name="rx-generic-name-format" value="prescribe" disabled>
                        <span>
                            <strong>Generic Name Suffix Format</strong>
                            <small>Form at the end, e.g. Paracetamol 500 mg tablet.</small>
                        </span>
                    </label>
                    <label>
                        <input type="radio" name="rx-generic-name-format" value="labelled" disabled>
                        <span>
                            <strong>Generic Name Prefix Format</strong>
                            <small>Form at the front, e.g. TABLET PARACETAMOL 500 mg.</small>
                        </span>
                    </label>
                </div>
            </section>

            <section class="rx-settings-card">
                <h4>Suffix Prefix Usage</h4>
                <p>Choose how drug forms appear after selecting a brand.</p>
                <div class="rx-segmented">
                    <label>
                        <input type="radio" name="rx-prefix-mode" value="full" disabled>
                        <span>
                            <strong>Full</strong>
                            <small>TABLET, SYRUP, INJECTION will be used.</small>
                        </span>
                    </label>
                    <label>
                        <input type="radio" name="rx-prefix-mode" value="short" disabled>
                        <span>
                            <strong>Short</strong>
                            <small>TAB., SYP., INJ. will be used.</small>
                        </span>
                    </label>
                </div>
            </section>

            <section class="rx-settings-card">
                <h4>Auto Expand / Auto Reduction Size</h4>
                <label class="rx-toggle-row">
                    <input type="checkbox" id="rx-auto-row-size">
                    <span>
                        <strong>Enable row auto size</strong>
                            <small>Rows expand and reduce with content, similar to the P/C module.</small>
                    </span>
                </label>
            </section>

            <section class="rx-settings-card">
                <h4>Warnings & Interactions</h4>
                <label class="rx-toggle-row">
                    <input type="checkbox" id="rx-show-warnings">
                    <span>
                        <strong>Show Warnings</strong>
                        <small>When off, the infobar uses one compact line for drug information only.</small>
                    </span>
                </label>
                <details class="rx-warning-dropdown">
                    <summary>Warning types</summary>
                    <div class="rx-warning-type-grid" aria-label="Warning types">
                        <label><input type="checkbox" data-rx-warning-type="immediate"> Immediate warning</label>
                        <label><input type="checkbox" data-rx-warning-type="antibiotic"> Antibiotic</label>
                        <label><input type="checkbox" data-rx-warning-type="highAlert"> High alert</label>
                        <label><input type="checkbox" data-rx-warning-type="renal"> Renal dose caution</label>
                        <label><input type="checkbox" data-rx-warning-type="tapering"> Tapering needed</label>
                        <label><input type="checkbox" data-rx-warning-type="pregnancy"> Pregnancy warning</label>
                        <label><input type="checkbox" data-rx-warning-type="lactation"> Lactation warning</label>
                        <label><input type="checkbox" data-rx-warning-type="hepatic"> Hepatic caution</label>
                        <label><input type="checkbox" data-rx-warning-type="paediatric"> Paediatric caution</label>
                    </div>
                </details>
                <label class="rx-toggle-row">
                    <input type="checkbox" id="rx-show-interactions">
                    <span>
                        <strong>Show Interactions</strong>
                        <small>Controls the dedicated Drug Summary & Interaction module.</small>
                    </span>
                </label>
            </section>

            <div class="rx-settings-footer">
                <button type="button" class="rx-settings-restore-btn" id="rx-settings-restore-defaults">Restore default</button>
            </div>
        </div>
    </div>
</div>

<div id="rx-drug-modal" class="rx-drug-modal-backdrop">
    <div class="rx-drug-modal-dialog">
        <div class="rx-drug-modal-header">
            <div id="rx-drug-modal-title" class="rx-drug-modal-title">Drug View</div>
            <button type="button" id="rx-drug-modal-close" class="rx-drug-modal-close">×</button>
        </div>
        <div id="rx-drug-modal-loading" class="rx-drug-modal-loading">
            Loading drug details...
        </div>
        <iframe id="rx-drug-modal-frame" title="Drug View" class="rx-drug-modal-frame"></iframe>
    </div>
</div>
