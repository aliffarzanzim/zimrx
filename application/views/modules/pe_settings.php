<?php
declare(strict_types=1);

$isPeSettingsPage = !empty($peSettingsPage);
$peConfigJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<script type="application/json" id="zimrxInitialPeConfig"><?= json_encode($peDoctorConfig, $peConfigJsonFlags) ?></script>

<div class="pe-settings-modal<?= $isPeSettingsPage ? ' pe-settings-page-modal' : '' ?>" id="pe-settings-modal"<?= $isPeSettingsPage ? '' : ' hidden' ?>>
    <?php if (!$isPeSettingsPage): ?>
        <div class="pe-settings-backdrop" data-pe-settings-close></div>
    <?php endif; ?>
    <div class="pe-settings-panel" role="dialog" aria-modal="<?= $isPeSettingsPage ? 'false' : 'true' ?>" aria-labelledby="pe-settings-title">
        <div class="pe-settings-header">
            <div>
                <h3 id="pe-settings-title">Physical Examination Settings</h3>
                <p>Configure which clinical findings appear in your examination table, adjust ordering, or add custom parameters.</p>
            </div>
            <button type="button" class="pe-settings-close" data-pe-settings-close aria-label="<?= $isPeSettingsPage ? 'Back to Settings' : 'Close Settings' ?>"><?= zrx_icon($isPeSettingsPage ? 'arrow-left' : 'x', 16) ?></button>
        </div>

        <div class="pe-settings-toolbar">
            <div class="pe-settings-search-box">
                <?= zrx_icon('search', 15) ?>
                <input type="text" id="pe-settings-search" placeholder="Search 90+ physical findings across catalog..." autocomplete="off">
            </div>
            <button type="button" id="pe-toggle-add-btn" class="pe-settings-btn-outline-add">
                <?= zrx_icon('plus', 14) ?>
                <span>+ Add Parameter</span>
            </button>
        </div>

        <div class="pe-settings-grid">
            <div class="pe-settings-sidebar">
                <div class="pe-settings-sidebar-title">
                    <span>ORGAN SYSTEMS</span>
                    <span id="pe-sys-total-badge" class="pe-settings-sidebar-badge"></span>
                </div>
                <div class="pe-settings-sys-list" id="pe-settings-sys-list"></div>
            </div>

            <div class="pe-settings-main">
                <div class="pe-settings-add-card" id="pe-add-card" hidden>
                    <div class="pe-settings-add-card-header">
                        <h4>+ Add Custom Physical Examination Parameter</h4>
                        <button type="button" class="pe-settings-add-card-close" id="pe-add-card-close" aria-label="Close add parameter form"><?= zrx_icon('x', 14) ?></button>
                    </div>
                    <div class="pe-settings-add-grid">
                        <div>
                            <label for="pe-custom-system">System</label>
                            <input type="text" id="pe-custom-system" placeholder="e.g. Vitals or MSK" list="pe-existing-systems" autocomplete="off">
                            <datalist id="pe-existing-systems"></datalist>
                        </div>
                        <div>
                            <label for="pe-custom-category">Category</label>
                            <input type="text" id="pe-custom-category" placeholder="e.g. General" autocomplete="off">
                        </div>
                        <div>
                            <label for="pe-custom-label">Display Label (on Rx)</label>
                            <input type="text" id="pe-custom-label" placeholder="e.g. Waist Circumference" autocomplete="off">
                        </div>
                        <div>
                            <label for="pe-custom-input-type">Input Type</label>
                            <select id="pe-custom-input-type">
                                <option value="dropdown+textbox">Dropdown + Textbox (Hybrid)</option>
                                <option value="textbox">Single Textbox</option>
                                <option value="double_textbox">Double Textbox (e.g. BP 120/80)</option>
                                <option value="multiple_textbox">Multiple Textbox (e.g. GCS E/V/M)</option>
                                <option value="dropdown">Dropdown Only</option>
                            </select>
                        </div>
                        <div>
                            <label for="pe-custom-delimiter">Delimiter (if double/multi)</label>
                            <input type="text" id="pe-custom-delimiter" placeholder="e.g. /" value="/" autocomplete="off">
                        </div>
                        <div>
                            <label for="pe-custom-unit">Default Unit</label>
                            <input type="text" id="pe-custom-unit" placeholder="e.g. cm, mmHg, bpm" autocomplete="off">
                        </div>
                        <div>
                            <label for="pe-custom-normal">Normal Value</label>
                            <input type="text" id="pe-custom-normal" placeholder="e.g. Normal, Absent" autocomplete="off">
                        </div>
                        <div>
                            <label for="pe-custom-wordlists">Wordlists / Presets (comma-separated)</label>
                            <input type="text" id="pe-custom-wordlists" placeholder="e.g. Absent, Present, Mild, Severe" autocomplete="off">
                        </div>
                    </div>
                    <div class="pe-settings-footer-actions">
                        <button type="button" id="pe-add-custom-btn" class="pe-settings-add-btn">+ Add Parameter</button>
                    </div>
                </div>

                <div class="pe-settings-table-header-info">
                    <span id="pe-table-section-title" class="pe-settings-current-sys-title">All Systems</span>
                    <span id="pe-table-section-count" class="pe-settings-current-sys-count"></span>
                </div>

                <div class="pe-settings-table-wrap">
                    <table class="pe-settings-table">
                        <thead>
                            <tr>
                                <th class="pe-cfg-col-drag"></th>
                                <th class="pe-cfg-col-active">Active</th>
                                <th class="pe-cfg-col-name">Display Name</th>
                                <th class="pe-cfg-col-system">System</th>
                                <th class="pe-cfg-col-type">Input Type</th>
                                <th class="pe-cfg-col-delim">Delim</th>
                                <th class="pe-cfg-col-unit">Unit</th>
                                <th>Finding Wordlists / Dropdown Options</th>
                                <th class="pe-cfg-col-del"></th>
                            </tr>
                        </thead>
                        <tbody id="pe-settings-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="pe-settings-footer">
            <button type="button" id="pe-settings-reset-btn" class="pe-settings-btn-reset" title="Restore factory defaults">Reset to Defaults</button>
            <div class="pe-settings-footer-right">
                <span id="pe-settings-status" role="status" aria-live="polite"></span>
                <button type="button" class="pe-settings-btn-cancel" data-pe-settings-close><?= $isPeSettingsPage ? 'Back to Settings' : 'Cancel' ?></button>
                <button type="button" id="pe-settings-save-btn" class="pe-settings-btn-save">Save Settings</button>
            </div>
        </div>
    </div>
</div>
