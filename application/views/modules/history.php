<?php
declare(strict_types=1);

// Patient history module: past medical history, surgical treatments, habits, diet, and drug allergies.

require_once ZIMRX_BASE_DIR . '/lib/medical_history_lib.php';
$medHistoryDoctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));
$medHistoryDoctorConfig = med_history_get_doctor_config($medHistoryDoctorId);
$activeMedicalHistoryGroups = $medHistoryDoctorConfig['active_groups'];

$habitOptions = [
    'Smoking' => ['label' => 'Smoking', 'unit' => 'pack-years', 'placeholder' => 'e.g. 15'],
    'Smokeless Tobacco (Jorda, Gul)' => ['label' => 'Smokeless Tobacco (Jorda, Gul)', 'unit' => 'times/day', 'placeholder' => 'e.g. 5x'],
    'Betel Nut Chewing (Paan)' => ['label' => 'Betel Nut Chewing (Paan)', 'unit' => 'times/day', 'placeholder' => 'e.g. 4x'],
    'Alcohol Consumption' => ['label' => 'Alcohol Consumption', 'unit' => 'units/week', 'placeholder' => 'e.g. 14'],
    'Recreational Drug Use' => ['label' => 'Recreational Drug Use', 'unit' => '', 'placeholder' => 'e.g. Cannabis, Yaba'],
];

$dietOptions = [
    'Standard',
    'Diabetic Diet',
    'Low Salt Diet',
    'Vegetarian',
    'Vegan',
    'Inadequate Intake',
    'Therapeutic Feeding',
    'Exclusive Breastfeeding',
    'Formula Feeding',
    'Mixed Feeding',
    'Complementary Feeding'
];
?>
<div class="module-header history-card-header">
    <span>History</span>
    <div class="history-header-actions" class="zrx-flex-ac-6">
        <button type="button" class="history-toggle-all-btn" data-history-toggle-all title="Expand or Collapse All History Sections">
            <?= zrx_icon('chevron-down', 12) ?>
            <span class="history-toggle-all-text">Expand All</span>
        </button>
        <button type="button" class="history-main-settings-btn" id="history-med-settings-btn" title="History Settings" aria-haspopup="dialog" aria-controls="history-med-settings-modal">
            <?= zrx_icon('settings', 14) ?>
        </button>
    </div>
</div>

<div class="module-body history-module-body" data-history-module>
    <?php
    $submodules = [];

    // Medical conditions
    ob_start();
    ?>
    <section class="history-submodule history-submodule-medical" data-history-submodule="medical">
        <button type="button" class="history-accordion-header active" aria-expanded="true">
            <div class="history-accordion-title-wrap">
                <span class="history-accordion-icon">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
                <span class="history-accordion-title">Medical History</span>
            </div>
        </button>
        <div class="history-accordion-content" style="display: block;">
            <div class="history-medical-groups" id="history-medical-groups-container">
                <?php foreach ($activeMedicalHistoryGroups as $groupName => $items): ?>
                    <div class="history-check-group" data-category="<?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="history-check-group-title"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="history-check-grid">
                            <?php foreach ($items as $cond): 
                                $key = htmlspecialchars($cond['condition_key'], ENT_QUOTES, 'UTF-8');
                                $label = htmlspecialchars($cond['display_label'], ENT_QUOTES, 'UTF-8');
                                $fieldType = htmlspecialchars($cond['field_type'], ENT_QUOTES, 'UTF-8');
                                $placeholder = htmlspecialchars($cond['placeholder'], ENT_QUOTES, 'UTF-8');
                            ?>
                                <div class="history-med-item" data-condition-key="<?= $key ?>" data-field-type="<?= $fieldType ?>" data-options="<?= htmlspecialchars($cond['dropdown_options'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <label class="history-check">
                                        <input type="checkbox" data-history-field="medical" data-history-label="<?= $label ?>" data-condition-key="<?= $key ?>">
                                        <span><?= $label ?></span>
                                    </label>
                                    <?php if ($fieldType === 'textbox'): ?>
                                        <div class="history-med-input-wrap" class="zrx-dn">
                                            <textarea rows="1" class="history-med-value-input" placeholder="<?= $placeholder ?: 'e.g. Details...' ?>"></textarea>
                                        </div>
                                    <?php elseif ($fieldType === 'dropdown_text' || $fieldType === 'dropdown'): ?>
                                        <div class="history-med-input-wrap" class="zrx-dn">
                                            <textarea rows="1" class="history-med-value-input" placeholder="<?= $placeholder ?: ($fieldType === 'dropdown' ? 'Select...' : 'Select or type...') ?>" <?= $fieldType === 'dropdown' ? 'readonly' : '' ?>></textarea>
                                            <button type="button" class="history-med-dropdown-btn" title="Select option" tabindex="-1">
                                                <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 1l4 4 4-4"/></svg>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <label class="history-field-row">
                <span class="history-control-label">Other Conditions</span>
                <input type="text" class="module-input history-text-input" data-history-field="medical-custom" placeholder="e.g. Chronic Kidney Disease, Migraine...">
            </label>
        </div>
    </section>
    <?php
    $submodules['medical'] = ob_get_clean();

    // Surgical and procedural treatment history
    ob_start();
    ?>
    <section class="history-submodule history-submodule-treatment" data-history-submodule="treatment">
        <button type="button" class="history-accordion-header" aria-expanded="false">
            <div class="history-accordion-title-wrap">
                <span class="history-accordion-icon">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
                <span class="history-accordion-title">Treatment History</span>
            </div>
        </button>
        <div class="history-accordion-content" class="zrx-dn">
            <div class="pc-wrapper history-treatment-wrapper" id="history-treatment-wrapper">
                <div class="pc-table-container">
                    <table class="pc-table history-treatment-table" id="history-treatment-table">
                        <thead>
                            <tr>
                                <th style="width: 32px; text-align: center;"></th>
                                <th>Procedure / Surgery Name</th>
                                <th style="width: 80px; text-align: center;">Year</th>
                                <th style="width: 36px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody id="history-treatment-tbody">
                            <tr class="pc-row history-treatment-row" draggable="true">
                                <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                                <td><textarea class="pc-input history-treatment-procedure" rows="1" autocomplete="off"></textarea></td>
                                <td><input type="text" class="pc-input history-treatment-year" inputmode="numeric" maxlength="4"></td>
                                <td class="pc-action pc-drag">
                                    <button type="button" class="pc-row-move-btn history-row-move-btn" title="Move Row">
                                        <?= zrx_icon('move', 14) ?>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pc-footer">
                    <button type="button" class="pc-add-row-btn history-treatment-add-row-btn">Add More</button>
                </div>
                <template id="history-treatment-row-template">
                    <tr class="pc-row history-treatment-row" draggable="true">
                        <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                        <td><textarea class="pc-input history-treatment-procedure" rows="1" autocomplete="off"></textarea></td>
                        <td><input type="text" class="pc-input history-treatment-year" inputmode="numeric" maxlength="4"></td>
                        <td class="pc-action pc-drag">
                            <button type="button" class="pc-row-move-btn history-row-move-btn" title="Move Row">
                                <?= zrx_icon('move', 14) ?>
                            </button>
                        </td>
                    </tr>
                </template>
            </div>
        </div>
    </section>
    <?php
    $submodules['treatment'] = ob_get_clean();

    // Habits and lifestyle risks
    ob_start();
    ?>
    <section class="history-submodule history-submodule-habits" data-history-submodule="habits">
        <button type="button" class="history-accordion-header" aria-expanded="false">
            <div class="history-accordion-title-wrap">
                <span class="history-accordion-icon">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
                <span class="history-accordion-title">Habits</span>
            </div>
        </button>
        <div class="history-accordion-content" class="zrx-dn">
            <div class="history-habits-grid">
                <?php foreach ($habitOptions as $key => $habit): ?>
                    <div class="history-habit-item">
                        <label class="history-check">
                            <input type="checkbox" data-history-field="habit" data-history-label="<?= htmlspecialchars($habit['label'], ENT_QUOTES, 'UTF-8') ?>">
                            <span><?= htmlspecialchars($habit['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                        <?php if (!empty($habit['unit']) || !empty($habit['placeholder'])): ?>
                            <span class="history-habit-qty-wrap <?= empty($habit['unit']) ? 'history-habit-text-wrap' : '' ?>" class="zrx-dn">
                                <input type="text" class="history-habit-qty-input <?= empty($habit['unit']) ? 'history-habit-text-input-field' : '' ?>" data-unit="<?= htmlspecialchars($habit['unit'], ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($habit['placeholder'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($habit['unit'])): ?>
                                    <span class="history-habit-qty-unit"><?= htmlspecialchars($habit['unit'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if ($key === 'Smoking'): ?>
                                <button type="button" class="history-calc-btn" data-history-packyear-open title="Calculate Pack-Years" class="zrx-dn">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                                        <line x1="8" y1="6" x2="16" y2="6"></line>
                                        <line x1="8" y1="10" x2="8" y2="10"></line>
                                        <line x1="12" y1="10" x2="12" y2="10"></line>
                                        <line x1="16" y1="10" x2="16" y2="10"></line>
                                        <line x1="8" y1="14" x2="8" y2="14"></line>
                                        <line x1="12" y1="14" x2="12" y2="14"></line>
                                        <line x1="16" y1="14" x2="16" y2="14"></line>
                                        <line x1="8" y1="18" x2="8" y2="18"></line>
                                        <line x1="12" y1="18" x2="16" y2="18"></line>
                                    </svg>
                                </button>
                            <?php elseif ($key === 'Alcohol Consumption'): ?>
                                <button type="button" class="history-calc-btn" data-history-alcohol-open title="Calculate Alcohol Units/Week" class="zrx-dn">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                                        <line x1="8" y1="6" x2="16" y2="6"></line>
                                        <line x1="8" y1="10" x2="8" y2="10"></line>
                                        <line x1="12" y1="10" x2="12" y2="10"></line>
                                        <line x1="16" y1="10" x2="16" y2="10"></line>
                                        <line x1="8" y1="14" x2="8" y2="14"></line>
                                        <line x1="12" y1="14" x2="12" y2="14"></line>
                                        <line x1="16" y1="14" x2="16" y2="14"></line>
                                        <line x1="8" y1="18" x2="8" y2="18"></line>
                                        <line x1="12" y1="18" x2="16" y2="18"></line>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <label class="history-field-row">
                <span class="history-control-label">Others</span>
                <input type="text" class="module-input history-text-input" data-history-field="habit-notes" placeholder="e.g. Shisha, caffeine, other habits...">
            </label>
        </div>
    </section>
    <?php
    $submodules['habits'] = ob_get_clean();

    // Diet and hypersensitivities
    ob_start();
    ?>
    <section class="history-submodule history-submodule-diet" data-history-submodule="diet-hypersensitivity">
        <button type="button" class="history-accordion-header" aria-expanded="false">
            <div class="history-accordion-title-wrap">
                <span class="history-accordion-icon">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
                <span class="history-accordion-title">Diet &amp; Hypersensitivity</span>
            </div>
        </button>
        <div class="history-accordion-content" class="zrx-dn">
            <label class="history-field-row">
                <span class="history-control-label">Diet Type</span>
                <select class="module-input history-select" data-history-field="diet-type">
                    <option value="">Select Diet...</option>
                    <?php foreach ($dietOptions as $item): ?>
                        <option value="<?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="history-field-row history-chip-row">
                <span class="history-control-label">Hypersensitivity</span>
                <span class="history-chip-input" data-history-chip-input>
                    <span class="history-chip-list" data-history-chip-list></span>
                    <input type="text" class="history-chip-text" data-history-chip-text placeholder="Type allergy & press Enter or comma...">
                </span>
            </label>
        </div>
    </section>
    <?php
    $submodules['diet-hypersensitivity'] = ob_get_clean();

    // Past medications and drug history
    ob_start();
    ?>
    <section class="history-submodule history-submodule-dh" data-history-submodule="drug-history">
        <button type="button" class="history-accordion-header" aria-expanded="false">
            <div class="history-accordion-title-wrap">
                <span class="history-accordion-icon">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
                <span class="history-accordion-title">Drug History</span>
            </div>
        </button>
        <div class="history-accordion-content" class="zrx-dn">
            <div class="pc-wrapper history-dh-wrapper" id="dh-wrapper">
                <div class="pc-table-container">
                    <table class="pc-table history-dh-table" id="dh-table">
                        <thead>
                            <tr>
                                <th style="width: 32px; text-align: center;"></th>
                                <th style="width: 36px; text-align: center;">#</th>
                                <th>Drug Name / Regimen</th>
                                <th style="width: 36px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody id="dh-tbody">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                            <tr class="pc-row" draggable="true">
                                <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                                <td class="pc-row-no"><?= $i ?></td>
                                <td>
                                    <textarea class="pc-input dh-input" autocomplete="off" rows="1"></textarea>
                                </td>
                                <td class="pc-action pc-drag">
                                    <button type="button" class="pc-row-move-btn history-row-move-btn" title="Move Row">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pc-footer">
                    <button type="button" class="pc-add-row-btn dh-add-row-btn">Add More</button>
                </div>

                <template id="dh-row-template">
                    <tr class="pc-row" draggable="true">
                        <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                        <td class="pc-row-no"></td>
                        <td>
                            <textarea class="pc-input dh-input" autocomplete="off" rows="1"></textarea>
                        </td>
                        <td class="pc-action pc-drag">
                            <button type="button" class="pc-row-move-btn history-row-move-btn" title="Move Row">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </div>
        </div>
    </section>
    <?php
    $submodules['drug-history'] = ob_get_clean();

    // Render submodules according to doctor's saved layout order
    $defaultHistoryLayout = ['medical', 'treatment', 'habits', 'diet-hypersensitivity', 'drug-history'];
    $historyLayout = $defaultHistoryLayout;
    if (isset($_COOKIE['zimrx_history_layout'])) {
        $decoded = json_decode(urldecode($_COOKIE['zimrx_history_layout']), true);
        if (is_array($decoded)) {
            $historyLayout = $decoded;
        }
    }

    foreach ($historyLayout as $subName) {
        if ($subName !== '' && isset($submodules[$subName])) {
            echo $submodules[$subName];
        }
    }
    ?>
</div>

<div class="history-calc-modal" id="packyear-calc-modal" hidden class="zrx-dn">
    <div class="history-calc-backdrop" data-history-packyear-close></div>
    <div class="history-calc-panel" role="dialog" aria-modal="true" aria-labelledby="packyear-calc-title">
        <div class="history-calc-header">
            <div>
                <h3 id="packyear-calc-title">Pack-Year Calculator</h3>
                <p>Calculate cumulative smoking exposure (Pack-Years = (Sticks/day &divide; 20) &times; Years).</p>
            </div>
            <button type="button" class="history-calc-close" data-history-packyear-close aria-label="Close Calculator">&times;</button>
        </div>
        <div class="history-calc-body">
            <div class="history-calc-formula-card">
                <div class="history-calc-fraction-top">
                    <label class="history-calc-field">
                        <span class="history-calc-label">Cigarettes (Sticks) / Day</span>
                        <input type="number" min="0" step="1" id="packyear-calc-sticks" class="history-calc-input" placeholder="e.g. 20" value="20">
                        <span class="history-calc-subhint">(or <input type="number" min="0" step="0.1" id="packyear-calc-packs" class="history-calc-mini-input" value="1"> packs/day)</span>
                    </label>
                    
                    <div class="history-calc-math-op">&times;</div>
                    
                    <label class="history-calc-field">
                        <span class="history-calc-label">Duration Smoked (Years)</span>
                        <input type="number" min="0" step="0.5" id="packyear-calc-years" class="history-calc-input" placeholder="e.g. 15" value="15">
                        <span class="history-calc-subhint">Total years smoked</span>
                    </label>
                </div>

                <div class="history-calc-fraction-bar">
                    <span class="history-calc-fraction-denom-label">20 (cigarettes per pack)</span>
                </div>
            </div>

            <div class="history-calc-summary">
                <div class="history-calc-summary-val">
                    <span class="history-calc-equal-sign">=</span>
                    <span class="history-calc-result-num" id="packyear-calc-result">15.0</span>
                    <span class="history-calc-result-unit">Pack-Years</span>
                </div>
                <div class="history-calc-risk-badge moderate" id="packyear-calc-risk">Moderate Risk (10–19.9 pack-years)</div>
            </div>
        </div>
        <div class="history-calc-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-history-packyear-close>Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="packyear-calc-apply-btn">Apply to Smoking</button>
        </div>
    </div>
</div>

<div class="history-calc-modal" id="alcohol-calc-modal" hidden class="zrx-dn">
    <div class="history-calc-backdrop" data-history-alcohol-close></div>
    <div class="history-calc-panel" role="dialog" aria-modal="true" aria-labelledby="alcohol-calc-title">
        <div class="history-calc-header">
            <div>
                <h3>Alcohol Unit Calculator</h3>
                <p>1 UK Unit = 10 mL pure alcohol &bull; Units = (Serving Size &times; Alcohol By Volume % &times; Drinks/Day &times; Days/Week) &divide; 1000.</p>
            </div>
            <button type="button" class="history-calc-close" data-history-alcohol-close aria-label="Close Calculator">&times;</button>
        </div>
        <div class="history-calc-body">
            <div class="history-calc-presets">
                <div class="history-calc-preset-header">
                    <span class="history-calc-preset-label">Quick Beverage Presets:</span>
                    <div class="history-calc-serving-infobox" title="Standard Serving Volumes: Shot/Peg = 30ml, Double Peg = 60ml, Can/Mug = 500ml, Wine = 175ml (Click any to apply)">
                        <span class="history-calc-info-pill" data-guide-ml="30" title="Click to set 30 mL">🥃 <strong>Shot / Peg</strong> = 30ml</span>
                        <span class="history-calc-info-sep">•</span>
                        <span class="history-calc-info-pill" data-guide-ml="500" title="Click to set 500 mL">🍺 <strong>Can / Mug</strong> = 500ml</span>
                        <span class="history-calc-info-sep">•</span>
                        <span class="history-calc-info-pill" data-guide-ml="175" title="Click to set 175 mL">🍷 <strong>Wine</strong> = 175ml</span>
                    </div>
                </div>

                <div class="history-calc-preset-btns">
                    <button type="button" class="history-calc-preset-btn active" data-alcohol-preset="beer">🍺 Beer / Hunter (500ml, 5%)</button>
                    <button type="button" class="history-calc-preset-btn" data-alcohol-preset="wine">🍷 Wine (175ml, 13%)</button>
                    <button type="button" class="history-calc-preset-btn" data-alcohol-preset="vodka">🍸 Vodka (30ml, 40%)</button>
                    <button type="button" class="history-calc-preset-btn" data-alcohol-preset="spirits">🥃 Whisky / Spirits (30ml, 40%)</button>
                    <button type="button" class="history-calc-preset-btn" data-alcohol-preset="carew">🍶 Carew (30ml, 42.8%)</button>
                    <button type="button" class="history-calc-preset-btn" data-alcohol-preset="bangla">🍶 Bangla / Cholai (30ml, 40%)</button>
                    <button type="button" class="history-calc-preset-btn" data-alcohol-preset="tari">🌴 Tari (250ml, 5%)</button>
                    <button type="button" class="history-calc-preset-btn" data-alcohol-preset="custom">⚙️ Custom</button>
                </div>
            </div>

            <div class="history-calc-formula-card">
                <div class="history-calc-fraction-top history-calc-alcohol-top">
                    <label class="history-calc-field">
                        <span class="history-calc-label">Serving Size (each time)</span>
                        <input type="number" min="0" step="10" id="alcohol-calc-volume" class="history-calc-input" placeholder="e.g. 500 mL" value="500">
                        <span class="history-calc-water-note">(Exclude Water Mixing)</span>
                    </label>
                    
                    <div class="history-calc-math-op">&times;</div>
                    
                    <label class="history-calc-field">
                        <span class="history-calc-label">Alcohol By Volume (%)</span>
                        <input type="number" min="0" max="100" step="0.5" id="alcohol-calc-abv" class="history-calc-input" placeholder="e.g. 5" value="5">
                        <span class="history-calc-subhint">Strength (% ethanol)</span>
                    </label>

                    <div class="history-calc-math-op">&times;</div>

                    <label class="history-calc-field">
                        <span class="history-calc-label">Drinks / Day</span>
                        <input type="number" min="0" step="1" id="alcohol-calc-drinks" class="history-calc-input" placeholder="e.g. 2" value="2">
                        <span class="history-calc-subhint" id="alcohol-calc-drinks-hint">e.g. 2 cans/session</span>
                    </label>

                    <div class="history-calc-math-op">&times;</div>

                    <label class="history-calc-field">
                        <span class="history-calc-label">Drinking Days / Week</span>
                        <input type="number" min="1" max="7" step="1" id="alcohol-calc-days" class="history-calc-input" placeholder="e.g. 3" value="3">
                        <span class="history-calc-subhint">Days active per week</span>
                    </label>
                </div>

                <div class="history-calc-fraction-bar">
                    <span class="history-calc-fraction-denom-label">1000</span>
                </div>
            </div>

            <div class="history-calc-unit-ref">
                <span class="history-calc-unit-ref-title">Clinical Unit Reference (1 UK Unit = 10 mL pure alcohol):</span>
                <div class="history-calc-unit-chips">
                    <span class="history-calc-unit-chip">🥃 <strong>1 Peg (30mL @ 40%)</strong> = 1.2 Units</span>
                    <span class="history-calc-unit-chip">🍺 <strong>1 Can Beer (500mL @ 5%)</strong> = 2.5 Units</span>
                    <span class="history-calc-unit-chip">🍷 <strong>1 Glass Wine (175mL @ 13%)</strong> = 2.3 Units</span>
                    <span class="history-calc-unit-chip">🍶 <strong>Carew (30mL @ 42.8%)</strong> = 1.3 Units</span>
                    <span class="history-calc-unit-chip">🌴 <strong>Tari (250mL @ 5%)</strong> = 1.25 Units</span>
                </div>
            </div>

            <div class="history-calc-summary">
                <div class="history-calc-summary-val">
                    <span class="history-calc-equal-sign">=</span>
                    <span class="history-calc-result-num" id="alcohol-calc-result">15.0</span>
                    <span class="history-calc-result-unit" id="alcohol-calc-unit">Units/Week</span>
                </div>
                <div class="history-calc-risk-badge moderate" id="alcohol-calc-risk">Moderate Risk (15–35 units/week)</div>
            </div>
        </div>
        <div class="history-calc-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-history-alcohol-close>Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="alcohol-calc-apply-btn">Apply to Alcohol</button>
        </div>
    </div>
</div>

<!-- History Settings Modal -->
<div class="history-med-settings-modal" id="history-med-settings-modal" hidden class="zrx-dn">
    <div class="history-med-settings-backdrop" data-med-settings-close></div>
    <div class="history-med-settings-panel" role="dialog" aria-modal="true" aria-labelledby="med-settings-title">
        <div class="history-med-settings-header">
            <div>
                <h3 id="med-settings-title">History Settings</h3>
                <p>Configure parameters across Medical History, Habits, Treatment, Diet, and Drug History.</p>
            </div>
            <button type="button" class="history-med-settings-close" data-med-settings-close aria-label="Close History Settings">&times;</button>
        </div>

        <!-- History Settings Tabs -->
        <div class="history-settings-tabs-bar">
            <button type="button" class="history-settings-tab-btn active" data-history-tab="medical">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                <span>Medical History</span>
            </button>
            <button type="button" class="history-settings-tab-btn" data-history-tab="habits">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
                <span>Habits</span>
            </button>
            <button type="button" class="history-settings-tab-btn" data-history-tab="treatment">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                <span>Treatment History</span>
            </button>
            <button type="button" class="history-settings-tab-btn" data-history-tab="diet">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"></path></svg>
                <span>Diet &amp; Hypersensitivity</span>
            </button>
            <button type="button" class="history-settings-tab-btn" data-history-tab="drug">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"></path><path d="m8.5 8.5 7 7"></path></svg>
                <span>Drug History</span>
            </button>
        </div>

        <!-- Tab Pane 1: Medical History -->
        <div class="history-tab-pane active" id="history-tab-pane-medical">
            <div class="history-med-settings-toolbar">
                <div class="history-med-search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="med-settings-search" placeholder="Search condition or category across catalog..." autocomplete="off">
                </div>
                <button type="button" id="med-toggle-add-btn" class="history-med-btn-outline-add">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>+ Add Condition</span>
                </button>
            </div>

            <div class="history-med-settings-grid">
                <!-- Left Sidebar: Categories List -->
                <div class="history-med-sidebar">
                    <div class="history-med-sidebar-title">
                        <span>CATEGORIES</span>
                        <span id="med-cat-total-badge" class="history-med-sidebar-badge"></span>
                    </div>
                    <div class="history-med-cat-list" id="med-settings-cat-list">
                        <!-- Rendered by JS -->
                    </div>
                </div>

                <!-- Right Main Pane: Table View & Collapsible Custom Form -->
                <div class="history-med-main">
                    <!-- Collapsible Custom Form (Hidden by default) -->
                    <div class="history-med-add-card" id="med-add-card" class="zrx-dn">
                        <div class="history-med-add-card-header">
                            <h4>+ Add Custom Condition / Category</h4>
                            <button type="button" class="history-med-add-card-close" id="med-add-card-close">&times;</button>
                        </div>
                        <div class="history-med-add-grid">
                            <div>
                                <label>Category</label>
                                <input type="text" id="med-custom-category" placeholder="e.g. Ophthalmology" list="med-existing-categories" autocomplete="off">
                                <datalist id="med-existing-categories"></datalist>
                            </div>
                            <div>
                                <label>Display Label (on Rx)</label>
                                <input type="text" id="med-custom-label" placeholder="e.g. Diabetic Retinopathy" autocomplete="off">
                            </div>
                            <div>
                                <label>Input Style</label>
                                <select id="med-custom-field-type">
                                    <option value="dropdown_text">With Options List (Presets)</option>
                                    <option value="textbox">With Note Box (Free text)</option>
                                    <option value="none">Quick Checkbox Only</option>
                                </select>
                            </div>
                        </div>

                        <!-- Dynamic Presets / Notes Section for Custom Condition -->
                        <div id="med-custom-options-chip-wrap" class="med-custom-options-wrap">
                            <label class="med-custom-label">
                                Preset Stages / Options (Click text to edit &bull; Drag to reorder):
                            </label>
                            <div class="history-med-chip-container" id="med-custom-chip-container">
                                <div class="history-med-chip-list"></div>
                                <div class="history-med-chip-add-row">
                                    <input type="text" class="history-med-chip-input" placeholder="Type stage/option (e.g. Mild NPDR) & press Enter..." autocomplete="off">
                                    <button type="button" class="history-med-chip-add-btn">+ Add</button>
                                </div>
                            </div>
                        </div>

                        <div id="med-custom-placeholder-wrap" class="med-custom-options-wrap" hidden class="zrx-dn">
                            <label class="med-custom-label">
                                Guidance Hint (Optional placeholder):
                            </label>
                            <input type="text" id="med-custom-placeholder" placeholder="e.g. Both eyes, on anti-VEGF" autocomplete="off" class="med-custom-input-full">
                        </div>

                        <div class="med-settings-footer-actions">
                            <button type="button" id="med-add-custom-btn" class="history-med-add-btn">+ Add Condition</button>
                        </div>
                    </div>

                    <!-- Table Header Info -->
                    <div class="history-med-table-header-info">
                        <span id="med-table-section-title" class="history-med-current-cat-title">All Conditions</span>
                        <span id="med-table-section-count" class="history-med-current-cat-count"></span>
                    </div>

                    <!-- Table Wrap -->
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
                    <button type="button" class="history-med-btn-cancel" data-med-settings-close>Cancel</button>
                    <button type="button" id="med-settings-save-btn" class="history-med-btn-save">Save Settings</button>
                </div>
            </div>
        </div>

        <!-- Tab Pane 2: Habits -->
        <div class="history-tab-pane" id="history-tab-pane-habits" class="zrx-dn">
            <div class="history-coming-soon-card">
                <div class="coming-soon-badge">Coming soon</div>
                <div class="coming-soon-icon-circle">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
                        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
                        <line x1="6" y1="1" x2="6" y2="4"></line>
                        <line x1="10" y1="1" x2="10" y2="4"></line>
                        <line x1="14" y1="1" x2="14" y2="4"></line>
                    </svg>
                </div>
                <h3>Habits Settings</h3>
                <p>Configure custom habit parameters, unit quantifiers, and risk calculation thresholds (pack-years &amp; alcohol units).</p>
                <span class="coming-soon-pill">Feature in active development</span>
            </div>
        </div>

        <!-- Tab Pane 3: Treatment History -->
        <div class="history-tab-pane" id="history-tab-pane-treatment" class="zrx-dn">
            <div class="history-coming-soon-card">
                <div class="coming-soon-badge">Coming soon</div>
                <div class="coming-soon-icon-circle">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                </div>
                <h3>Treatment History Settings</h3>
                <p>Customize surgery and medical procedure suggestion catalogs, year format, and quick table defaults.</p>
                <span class="coming-soon-pill">Feature in active development</span>
            </div>
        </div>

        <!-- Tab Pane 4: Diet & Hypersensitivity -->
        <div class="history-tab-pane" id="history-tab-pane-diet" class="zrx-dn">
            <div class="history-coming-soon-card">
                <div class="coming-soon-badge">Coming soon</div>
                <div class="coming-soon-icon-circle">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"></path>
                    </svg>
                </div>
                <h3>Diet &amp; Hypersensitivity Settings</h3>
                <p>Configure dietary option lists and quick hypersensitivity allergy chips catalog.</p>
                <span class="coming-soon-pill">Feature in active development</span>
            </div>
        </div>

        <!-- Tab Pane 5: Drug History -->
        <div class="history-tab-pane" id="history-tab-pane-drug" class="zrx-dn">
            <div class="history-coming-soon-card">
                <div class="coming-soon-badge">Coming soon</div>
                <div class="coming-soon-icon-circle">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"></path>
                        <path d="m8.5 8.5 7 7"></path>
                    </svg>
                </div>
                <h3>Drug History Settings</h3>
                <p>Configure past medications list, chronic therapy tracking, and drug regimen suggestions.</p>
                <span class="coming-soon-pill">Feature in active development</span>
            </div>
        </div>
    </div>
</div>

<!-- Focused Preset Options Sub-Modal -->
<div class="history-med-presets-modal" id="history-med-presets-modal" hidden class="zrx-dn">
    <div class="history-med-presets-backdrop" data-med-presets-close></div>
    <div class="history-med-presets-panel" role="dialog" aria-modal="true" aria-labelledby="med-presets-modal-title">
        <div class="history-med-presets-header">
            <div>
                <h4 id="med-presets-modal-title">Manage Presets</h4>
                <p>Click text to edit &bull; Drag <span class="text-bold-drag">⋮⋮</span> to swap/reorder &bull; Click &times; to remove.</p>
            </div>
            <button type="button" class="history-med-presets-close" data-med-presets-close aria-label="Close Presets">&times;</button>
        </div>
        <div class="history-med-presets-body">
            <div class="history-med-chip-container" id="med-modal-chip-container">
                <div class="history-med-chip-list"></div>
                <div class="history-med-chip-add-row">
                    <input type="text" class="history-med-chip-input" placeholder="Type preset/stage & press Enter..." autocomplete="off">
                    <button type="button" class="history-med-chip-add-btn">+ Add</button>
                </div>
            </div>
        </div>
        <div class="history-med-presets-footer">
            <button type="button" class="history-med-presets-done-btn" id="med-presets-done-btn">Done</button>
        </div>
    </div>
</div>

<script type="application/json" id="zimrxInitialMedHistoryConfig"><?= json_encode($medHistoryDoctorConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

