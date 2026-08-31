<?php
declare(strict_types=1);

// Physical Examination (P/E) module: clinical findings table, vitals inputs, and custom parameter settings.

require_once ZIMRX_BASE_DIR . '/lib/physical_examination_lib.php';
$peDoctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));
$peDoctorConfig = physical_exam_get_doctor_config($peDoctorId);
$activePeItems = $peDoctorConfig['active_items'];
?>
<div class="pc-wrapper" id="pe-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="pe-table">
            <thead>
                <tr>
                    <th class="pe-col-drag"></th>
                    <th class="pe-col-name">Physical Examination</th>
                    <th class="pe-col-val">Value</th>
                    <th class="pe-col-unit">Unit</th>
                    <th class="pe-col-action">
                        <a class="pe-settings-btn" href="physical_examination_settings.php" title="Physical Examination Settings" aria-label="Physical Examination Settings">
                            <?= zrx_icon('settings', 14) ?>
                        </a>
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
                            <td class="zrx-va-mid">
                                <div class="pe-parts-wrap-2">
                                    <input type="text" class="pe-input pe-val-part pe-val-part-2" data-part="1" autocomplete="off">
                                    <span class="pe-part-separator"><?= $delimiter ?></span>
                                    <input type="text" class="pe-input pe-val-part pe-val-part-2" data-part="2" autocomplete="off">
                                </div>
                            </td>
                        <?php elseif ($inputType === 'multiple_textbox'): ?>
                            <td class="zrx-va-mid">
                                <div class="pe-parts-wrap-3">
                                    <input type="text" class="pe-input pe-val-part pe-val-part-3" data-part="1" autocomplete="off">
                                    <span class="pe-part-separator-sm"><?= $delimiter ?></span>
                                    <input type="text" class="pe-input pe-val-part pe-val-part-3" data-part="2" autocomplete="off">
                                    <span class="pe-part-separator-sm"><?= $delimiter ?></span>
                                    <input type="text" class="pe-input pe-val-part pe-val-part-3" data-part="3" autocomplete="off">
                                </div>
                            </td>
                        <?php elseif ($code === 'height'): ?>
                            <td><input type="text" id="pe-height-val" class="pc-input pe-input zrx-ta-c" autocomplete="off"></td>
                        <?php elseif ($code === 'weight'): ?>
                            <td><input type="text" id="pe-weight-val" class="pc-input pe-input zrx-ta-c" autocomplete="off"></td>
                        <?php elseif ($code === 'bmi'): ?>
                            <td><input type="text" id="pe-bmi-val" class="pc-input pe-input zrx-ta-c" readonly autocomplete="off"></td>
                        <?php elseif (!empty($item['dropdown_options']) || !empty($item['finding_wordlists'])): ?>
                            <td>
                                <input type="text" list="pe-dl-<?= $code ?>" class="pc-input pe-input zrx-ta-c" autocomplete="off" placeholder="<?= $normalVal ? 'Normal: ' . $normalVal : '' ?>">
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
                            <td><input type="text" class="pc-input pe-input zrx-ta-c" autocomplete="off"></td>
                        <?php endif; ?>

                        <td><input type="text" class="pc-input pe-input zrx-ta-c" value="<?= $unit ?>" autocomplete="off"></td>
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
            <td><input type="text" class="pc-input pe-input zrx-ta-c" autocomplete="off"></td>
            <td><input type="text" class="pc-input pe-input zrx-ta-c" autocomplete="off"></td>
            <td class="pc-action pc-drag">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<script src="assets/js/modules/pe.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/pe.js') ?>" defer></script>
