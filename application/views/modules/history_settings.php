<?php
declare(strict_types=1);

$medHistoryDoctorConfig = is_array($medHistoryDoctorConfig ?? null) ? $medHistoryDoctorConfig : [];
?>
<section class="history-med-settings-page" id="history-med-settings-modal" data-settings-page="true">
    <div class="history-med-settings-panel">
        <div class="history-med-settings-header">
            <div>
                <h2>Medical History Catalog</h2>
                <p>Control the terms available in the history entry grid for this doctor.</p>
            </div>
        </div>

        <div class="history-tab-pane active" id="history-tab-pane-medical">
            <div class="history-med-settings-toolbar">
                <div class="history-med-search-box">
                    <?= zrx_icon('search', 15) ?>
                    <input type="text" id="med-settings-search" placeholder="Search condition or category across catalog..." autocomplete="off">
                </div>
                <button type="button" id="med-toggle-add-btn" class="history-med-btn-outline-add">
                    <?= zrx_icon('plus', 14) ?>
                    <span>Add Condition</span>
                </button>
            </div>

            <div class="history-med-settings-grid">
                <aside class="history-med-sidebar" aria-label="Medical history categories">
                    <div class="history-med-sidebar-title">
                        <span>Categories</span>
                        <span id="med-cat-total-badge" class="history-med-sidebar-badge"></span>
                    </div>
                    <div class="history-med-cat-list" id="med-settings-cat-list"></div>
                </aside>

                <div class="history-med-main">
                    <div class="history-med-add-card" id="med-add-card">
                        <div class="history-med-add-card-header">
                            <h3>Add Custom Condition</h3>
                            <button type="button" class="history-med-add-card-close" id="med-add-card-close" aria-label="Close add condition form"><?= zrx_icon('x', 14) ?></button>
                        </div>
                        <div class="history-med-add-grid">
                            <div>
                                <label for="med-custom-category">Category</label>
                                <input type="text" id="med-custom-category" placeholder="e.g. Ophthalmology" list="med-existing-categories" autocomplete="off">
                                <datalist id="med-existing-categories"></datalist>
                            </div>
                            <div>
                                <label for="med-custom-label">Display Label</label>
                                <input type="text" id="med-custom-label" placeholder="e.g. Diabetic Retinopathy" autocomplete="off">
                            </div>
                            <div>
                                <label for="med-custom-field-type">Input Style</label>
                                <select id="med-custom-field-type">
                                    <option value="dropdown_text">Options List</option>
                                    <option value="textbox">Note Box</option>
                                    <option value="none">Quick Checkbox</option>
                                </select>
                            </div>
                        </div>

                        <div id="med-custom-options-chip-wrap" class="med-custom-options-wrap">
                            <label class="med-custom-label">Preset options</label>
                            <div class="history-med-chip-container" id="med-custom-chip-container">
                                <div class="history-med-chip-list"></div>
                                <div class="history-med-chip-add-row">
                                    <input type="text" class="history-med-chip-input" placeholder="Type an option and press Enter" autocomplete="off">
                                    <button type="button" class="history-med-chip-add-btn">Add</button>
                                </div>
                            </div>
                        </div>

                        <div id="med-custom-placeholder-wrap" class="med-custom-options-wrap" hidden>
                            <label class="med-custom-label" for="med-custom-placeholder">Guidance Hint</label>
                            <input type="text" id="med-custom-placeholder" placeholder="e.g. Both eyes, on anti-VEGF" autocomplete="off" class="med-custom-input-full">
                        </div>

                        <div class="med-settings-footer-actions">
                            <button type="button" id="med-add-custom-btn" class="history-med-add-btn">Add Condition</button>
                        </div>
                    </div>

                    <div class="history-med-table-header-info">
                        <span id="med-table-section-title" class="history-med-current-cat-title">All Conditions</span>
                        <span id="med-table-section-count" class="history-med-current-cat-count"></span>
                    </div>
                    <div class="history-med-table-wrap">
                        <table class="history-med-table">
                            <thead>
                                <tr>
                                    <th class="hist-cfg-col-drag"></th>
                                    <th class="hist-cfg-col-active">Active</th>
                                    <th class="hist-cfg-col-label">Display Label</th>
                                    <th class="hist-cfg-col-cat">Category</th>
                                    <th class="hist-cfg-col-style">Input Style</th>
                                    <th>Presets / Notes Hint</th>
                                    <th class="hist-cfg-col-del"></th>
                                </tr>
                            </thead>
                            <tbody id="med-settings-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="history-med-settings-footer">
                <button type="button" id="med-settings-reset-btn" class="history-med-btn-reset" title="Restore static factory defaults">Reset to Defaults</button>
                <div class="history-med-footer-right">
                    <span id="med-settings-status" role="status" aria-live="polite"></span>
                    <a class="history-med-btn-cancel" href="settings.php">Cancel</a>
                    <button type="button" id="med-settings-save-btn" class="history-med-btn-save">Save Settings</button>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="history-med-presets-modal" id="history-med-presets-modal" hidden>
    <div class="history-med-presets-backdrop" data-med-presets-close></div>
    <div class="history-med-presets-panel" role="dialog" aria-modal="true" aria-labelledby="med-presets-modal-title">
        <div class="history-med-presets-header">
            <div>
                <h2 id="med-presets-modal-title">Manage Presets</h2>
                <p>Drag to reorder, edit text, or remove a preset.</p>
            </div>
            <button type="button" class="history-med-presets-close" data-med-presets-close aria-label="Close presets"><?= zrx_icon('x', 14) ?></button>
        </div>
        <div class="history-med-presets-body">
            <div class="history-med-chip-container" id="med-modal-chip-container">
                <div class="history-med-chip-list"></div>
                <div class="history-med-chip-add-row">
                    <input type="text" class="history-med-chip-input" placeholder="Type a preset and press Enter" autocomplete="off">
                    <button type="button" class="history-med-chip-add-btn">Add</button>
                </div>
            </div>
        </div>
        <div class="history-med-presets-footer">
            <button type="button" class="history-med-presets-done-btn" id="med-presets-done-btn">Done</button>
        </div>
    </div>
</div>

<script type="application/json" id="zimrxInitialMedHistoryConfig"><?= json_encode($medHistoryDoctorConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
