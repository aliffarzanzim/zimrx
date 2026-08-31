<div class="rx-wrapper">
    <!-- Top Info Bar -->
    <div id="rx-info-bar" class="rx-info-bar" title="Drug details will appear here">
        <div class="rx-info-empty">Drug details and selected warnings will appear here. Interaction display is controlled from Rx settings.</div>
    </div>

    <!-- Top Action Bar -->
    <div class="rx-top-bar" id="rx-top-bar">
        <div class="rx-symbol">Rx</div>
        <button class="btn btn-outline btn-sm" style="background:#f1f5f9; font-weight:bold; color:#0f172a;">Drugs by Dx</button>

        <div class="rx-search-group">
            <div class="rx-search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" class="rx-template-input rx-drug-template-input" placeholder="Rx Template" autocomplete="off">
            </div>
            <div class="rx-search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="Full Prescription Template">
            </div>
            <button type="button" class="rx-settings-btn" id="rx-settings-open" title="Rx Settings" aria-haspopup="dialog" aria-controls="rx-settings-modal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"></path>
                    <path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.49 1a7.2 7.2 0 0 0-1.69-.98L14.5 2.42A.5.5 0 0 0 14 2h-4a.5.5 0 0 0-.5.42L9.12 5.07c-.61.24-1.18.56-1.69.98l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.04.32-.07.65-.07.98s.02.66.07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.49-1c.51.42 1.08.74 1.69.98l.38 2.65a.5.5 0 0 0 .5.42h4a.5.5 0 0 0 .5-.42l.38-2.65c.61-.24 1.18-.56 1.69-.98l2.49 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65Z"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Table Grid -->
    <div class="rx-table-container">
        <table class="rx-table" id="rx-table">
            <colgroup>
                <col style="width: 32px;">
                <col style="width: 36px;">
                <col style="width: 38px;">
                <col>
                <col style="width: 18%;">
                <col style="width: 16%;">
                <col style="width: 20%;">
                <col style="width: 12%;">
            </colgroup>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;"></th>
                    <th style="width: 36px; text-align: center;"></th>
                    <th style="width: 38px; text-align: center; padding: 0;">#</th>
                    <th>Brand</th>
                    <th style="width: 18%;">Generic</th>
                    <th style="width: 16%;">Dose</th>
                    <th style="width: 20%;">Instruction</th>
                    <th style="width: 12%;">Duration</th>
                </tr>
            </thead>
            <tbody id="rx-tbody">
                <?php for($i=1; $i<=10; $i++): ?>
                <tr class="pc-row rx-row" draggable="true">
                    <td class="rx-action rx-drag pc-action pc-drag" style="padding: 0 !important;">
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

<div id="rx-drug-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:10000; align-items:center; justify-content:center; padding:24px;">
    <div style="width:min(1280px, 96vw); height:min(860px, 92vh); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 25px 60px rgba(0,0,0,0.35); display:flex; flex-direction:column; position:relative;">
        <div style="background:#0f172a; color:#fff; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 20px;">
            <div id="rx-drug-modal-title" style="font-size:1rem; font-weight:700;">Drug View</div>
            <button type="button" id="rx-drug-modal-close" style="border:none; background:transparent; color:#fff; font-size:1.5rem; line-height:1; cursor:pointer;">×</button>
        </div>
        <div id="rx-drug-modal-loading" style="position:absolute; inset:56px 0 0; display:flex; align-items:center; justify-content:center; background:linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%); color:#334155; font-size:1rem; font-weight:600; letter-spacing:0.01em; z-index:1;">
            Loading drug details...
        </div>
        <iframe id="rx-drug-modal-frame" title="Drug View" style="flex:1; width:100%; border:none; background:#fff; visibility:hidden;"></iframe>
    </div>
</div>
