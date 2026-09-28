<?php
require_once __DIR__ . '/../api/physical_examination_lib.php';
$peDoctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));
$peDoctorConfig = physical_exam_get_doctor_config($peDoctorId);
$activePeItems = $peDoctorConfig['active_items'];
?>
<div class="pc-wrapper" id="pe-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="pe-table">
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;"></th>
                    <th style="width: 44%; white-space: nowrap;">Physical Examination</th>
                    <th style="width: 30%; text-align: center; white-space: nowrap;">Value</th>
                    <th style="width: 26%; text-align: center; white-space: nowrap;">Unit</th>
                    <th style="width: 36px; text-align: center;">
                        <button type="button" class="pe-settings-btn" id="pe-settings-btn" title="Physical Examination Settings" aria-haspopup="dialog" aria-controls="pe-settings-modal">
                            <?= zrx_icon('settings', 14) ?>
                        </button>
                    </th>
                </tr>
            </thead>
            <tbody id="pe-tbody">
                <?php foreach ($activePeItems as $item): 
                    $code = htmlspecialchars($item['item_code'], ENT_QUOTES, 'UTF-8');
                    $name = htmlspecialchars($item['display_name'], ENT_QUOTES, 'UTF-8');
                    $unit = htmlspecialchars($item['default_unit'], ENT_QUOTES, 'UTF-8');
                    $inputType = $item['input_type'];
                    $delimiter = htmlspecialchars($item['delimiter'] ?: '/', ENT_QUOTES, 'UTF-8');
                    $normalVal = htmlspecialchars($item['normal_value'] ?? '', ENT_QUOTES, 'UTF-8');
                ?>
                    <tr class="pc-row" draggable="true" data-item-code="<?= $code ?>" data-input-type="<?= htmlspecialchars($inputType, ENT_QUOTES, 'UTF-8') ?>" data-delimiter="<?= $delimiter ?>">
                        <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                        <td><textarea class="pc-input pe-input" autocomplete="off" rows="1"><?= $name ?></textarea></td>

                        <?php if ($inputType === 'double_textbox'): ?>
                            <td style="vertical-align: middle;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 0.35rem; width: 100%; height: 30px; box-sizing: border-box; padding: 0 0.5rem;">
                                    <input type="text" class="pe-input pe-val-part" data-part="1" style="width: 45%; height: 26px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; outline: none; font-family: var(--font-family);" autocomplete="off">
                                    <span style="font-size: 1.1rem; color: #64748b; font-weight: 600; line-height: 1;"><?= $delimiter ?></span>
                                    <input type="text" class="pe-input pe-val-part" data-part="2" style="width: 45%; height: 26px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; outline: none; font-family: var(--font-family);" autocomplete="off">
                                </div>
                            </td>
                        <?php elseif ($inputType === 'multiple_textbox'): ?>
                            <td style="vertical-align: middle;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 0.25rem; width: 100%; height: 30px; box-sizing: border-box; padding: 0 0.25rem;">
                                    <input type="text" class="pe-input pe-val-part" data-part="1" style="width: 28%; height: 26px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; outline: none; font-family: var(--font-family);" autocomplete="off">
                                    <span style="font-size: 1rem; color: #64748b; font-weight: 600; line-height: 1;"><?= $delimiter ?></span>
                                    <input type="text" class="pe-input pe-val-part" data-part="2" style="width: 28%; height: 26px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; outline: none; font-family: var(--font-family);" autocomplete="off">
                                    <span style="font-size: 1rem; color: #64748b; font-weight: 600; line-height: 1;"><?= $delimiter ?></span>
                                    <input type="text" class="pe-input pe-val-part" data-part="3" style="width: 28%; height: 26px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; outline: none; font-family: var(--font-family);" autocomplete="off">
                                </div>
                            </td>
                        <?php elseif ($code === 'height'): ?>
                            <td><input type="text" id="pe-height-val" class="pc-input pe-input" style="text-align: center;" autocomplete="off"></td>
                        <?php elseif ($code === 'weight'): ?>
                            <td><input type="text" id="pe-weight-val" class="pc-input pe-input" style="text-align: center;" autocomplete="off"></td>
                        <?php elseif ($code === 'bmi'): ?>
                            <td><input type="text" id="pe-bmi-val" class="pc-input pe-input" style="text-align: center;" readonly autocomplete="off"></td>
                        <?php elseif (!empty($item['dropdown_options']) || !empty($item['finding_wordlists'])): ?>
                            <td>
                                <input type="text" list="pe-dl-<?= $code ?>" class="pc-input pe-input" style="text-align: center;" autocomplete="off" placeholder="<?= $normalVal ? 'Normal: ' . $normalVal : '' ?>">
                                <datalist id="pe-dl-<?= $code ?>">
                                    <?php 
                                        $options = array_filter(array_map('trim', explode('|', $item['dropdown_options'] ?? '')));
                                        $words = array_filter(array_map('trim', explode(',', $item['finding_wordlists'] ?? '')));
                                        $allOpts = array_unique(array_merge($options, $words));
                                        foreach ($allOpts as $opt): 
                                    ?>
                                        <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </td>
                        <?php else: ?>
                            <td><input type="text" class="pc-input pe-input" style="text-align: center;" autocomplete="off"></td>
                        <?php endif; ?>

                        <td><input type="text" class="pc-input pe-input" style="text-align: center;" value="<?= $unit ?>" autocomplete="off"></td>
                        <td class="pc-action pc-drag">
                            <button type="button" class="pc-row-move-btn" title="Move Row">
                                <?= zrx_icon('move', 14) ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pc-footer">
        <button type="button" class="pc-add-row-btn pe-add-row-btn">Add More</button>
    </div>

    <!-- Physical Examination Row Template -->
    <template id="pe-row-template">
        <tr class="pc-row" draggable="true" data-item-code="" data-input-type="textbox">
            <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
            <td><textarea class="pc-input pe-input" autocomplete="off" rows="1"></textarea></td>
            <td><input type="text" class="pc-input pe-input" style="text-align: center;" autocomplete="off"></td>
            <td><input type="text" class="pc-input pe-input" style="text-align: center;" autocomplete="off"></td>
            <td class="pc-action pc-drag">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<!-- Initial Configuration JSON for Client-side JS -->
<script type="application/json" id="zimrxInitialPeConfig">
<?= json_encode($peDoctorConfig, JSON_UNESCAPED_UNICODE) ?>
</script>

<!-- Physical Examination Settings Modal -->
<div class="pe-settings-modal" id="pe-settings-modal" hidden style="display: none;">
    <div class="pe-settings-backdrop" data-pe-settings-close></div>
    <div class="pe-settings-panel" role="dialog" aria-modal="true" aria-labelledby="pe-settings-title">
        <div class="pe-settings-header">
            <div>
                <h3 id="pe-settings-title">Physical Examination Settings</h3>
                <p>Configure which clinical findings appear in your examination table, adjust ordering, or add custom parameters.</p>
            </div>
            <button type="button" class="pe-settings-close" data-pe-settings-close aria-label="Close Settings"><?= zrx_icon('x', 16) ?></button>
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
            <!-- Left Sidebar: Systems List -->
            <div class="pe-settings-sidebar">
                <div class="pe-settings-sidebar-title">
                    <span>ORGAN SYSTEMS</span>
                    <span id="pe-sys-total-badge" class="pe-settings-sidebar-badge"></span>
                </div>
                <div class="pe-settings-sys-list" id="pe-settings-sys-list">
                    <!-- Rendered by JS -->
                </div>
            </div>

            <!-- Right Main Pane: Table View & Collapsible Custom Form -->
            <div class="pe-settings-main">
                <!-- Collapsible Custom Form -->
                <div class="pe-settings-add-card" id="pe-add-card" style="display: none;">
                    <div class="pe-settings-add-card-header">
                        <h4>+ Add Custom Physical Examination Parameter</h4>
                        <button type="button" class="pe-settings-add-card-close" id="pe-add-card-close"><?= zrx_icon('x', 14) ?></button>
                    </div>
                    <div class="pe-settings-add-grid">
                        <div>
                            <label>System</label>
                            <input type="text" id="pe-custom-system" placeholder="e.g. Vitals or MSK" list="pe-existing-systems" autocomplete="off">
                            <datalist id="pe-existing-systems"></datalist>
                        </div>
                        <div>
                            <label>Category</label>
                            <input type="text" id="pe-custom-category" placeholder="e.g. General" autocomplete="off">
                        </div>
                        <div>
                            <label>Display Label (on Rx)</label>
                            <input type="text" id="pe-custom-label" placeholder="e.g. Waist Circumference" autocomplete="off">
                        </div>
                        <div>
                            <label>Input Type</label>
                            <select id="pe-custom-input-type">
                                <option value="dropdown+textbox">Dropdown + Textbox (Hybrid)</option>
                                <option value="textbox">Single Textbox</option>
                                <option value="double_textbox">Double Textbox (e.g. BP 120/80)</option>
                                <option value="multiple_textbox">Multiple Textbox (e.g. GCS E/V/M)</option>
                                <option value="dropdown">Dropdown Only</option>
                            </select>
                        </div>
                        <div>
                            <label>Delimiter (if double/multi)</label>
                            <input type="text" id="pe-custom-delimiter" placeholder="e.g. /" value="/" autocomplete="off">
                        </div>
                        <div>
                            <label>Default Unit</label>
                            <input type="text" id="pe-custom-unit" placeholder="e.g. cm, mmHg, bpm" autocomplete="off">
                        </div>
                        <div>
                            <label>Normal Value</label>
                            <input type="text" id="pe-custom-normal" placeholder="e.g. Normal, Absent" autocomplete="off">
                        </div>
                        <div>
                            <label>Wordlists / Presets (comma-separated)</label>
                            <input type="text" id="pe-custom-wordlists" placeholder="e.g. Absent, Present, Mild, Severe" autocomplete="off">
                        </div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; margin-top: 0.65rem;">
                        <button type="button" id="pe-add-custom-btn" class="pe-settings-add-btn">+ Add Parameter</button>
                    </div>
                </div>

                <!-- Table Header Info -->
                <div class="pe-settings-table-header-info">
                    <span id="pe-table-section-title" class="pe-settings-current-sys-title">All Systems</span>
                    <span id="pe-table-section-count" class="pe-settings-current-sys-count"></span>
                </div>

                <!-- Table Wrap -->
                <div class="pe-settings-table-wrap">
                    <table class="pe-settings-table">
                        <thead>
                            <tr>
                                <th style="width: 32px; text-align: center;"></th>
                                <th style="width: 48px; text-align: center;">Active</th>
                                <th style="width: 160px;">Display Name</th>
                                <th style="width: 110px;">System</th>
                                <th style="width: 130px;">Input Type</th>
                                <th style="width: 60px; text-align: center;">Delim</th>
                                <th style="width: 75px; text-align: center;">Unit</th>
                                <th>Finding Wordlists / Dropdown Options</th>
                                <th style="width: 36px; text-align: center;"></th>
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
                <button type="button" class="pe-settings-btn-cancel" data-pe-settings-close>Cancel</button>
                <button type="button" id="pe-settings-save-btn" class="pe-settings-btn-save">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/modules/pe_module.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/modules/pe_module.js') ?>"></script>
