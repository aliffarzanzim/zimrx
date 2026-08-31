<?php
declare(strict_types=1);

// Paediatric history fields and visit measurements.
?>
<div class="oh-wrapper js-ph-module" id="ph-module-wrapper">
    <div class="oh-table-container">
        <table class="oh-table" id="ph-table">
            <colgroup>
                <col style="width: 32px;">
                <col style="width: 42px;">
                <col style="width: 36%;">
                <col>
                <col style="width: 38px;">
            </colgroup>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;"></th>
                    <th style="width: 42px; text-align: center;">#</th>
                    <th colspan="2">
                        <div class="oh-header-flex">
                            <span>Paediatric History</span>
                        </div>
                    </th>
                    <th style="width: 38px; text-align: center;"></th>
                </tr>
            </thead>
            <tbody id="ph-table-body">
                <!-- 1. Gestation / Term -->
                <tr class="oh-row" data-row-kind="gestation" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">1</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Gestation" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-select-custom-wrap">
                            <select class="oh-input ph-quick-select">
                                <option value="Full term" selected>Full term</option>
                                <option value="Preterm (Late 34-36w)">Preterm (Late 34-36w)</option>
                                <option value="Preterm (Very <32w)">Preterm (Very <32w)</option>
                                <option value="Post-term (>42w)">Post-term (>42w)</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="text" class="oh-input oh-value-input ph-custom-input" value="Full term" autocomplete="off" hidden>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 2. Delivery Mode -->
                <tr class="oh-row" data-row-kind="delivery" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">2</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Delivery" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-select-custom-wrap">
                            <select class="oh-input ph-quick-select">
                                <option value="LUCS" selected>LUCS</option>
                                <option value="NVD (Normal Vaginal)">NVD (Normal Vaginal)</option>
                                <option value="Forceps / Vacuum">Forceps / Vacuum</option>
                                <option value="Home Delivery">Home Delivery</option>
                                <option value="Hospital Delivery">Hospital Delivery</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="text" class="oh-input oh-value-input ph-custom-input" value="LUCS" autocomplete="off" hidden>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 3. Birth Weight -->
                <tr class="oh-row" data-row-kind="birth_weight" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">3</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Birth Weight" readonly></td>
                    <td class="oh-value-cell">
                        <div class="oh-value-split">
                            <input type="text" class="oh-input oh-duration-input ph-birth-weight-input" placeholder="e.g. 2.8" inputmode="decimal" autocomplete="off">
                            <select class="oh-input oh-unit-select ph-birth-weight-unit">
                                <option value="kg" selected>kg</option>
                                <option value="g">g</option>
                                <option value="lb">lb</option>
                            </select>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 4. Cry at Birth -->
                <tr class="oh-row" data-row-kind="cry_at_birth" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">4</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Cry at Birth" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-select-custom-wrap">
                            <select class="oh-input ph-quick-select">
                                <option value="Immediate & lusty" selected>Immediate & lusty</option>
                                <option value="Delayed">Delayed</option>
                                <option value="Required resuscitation">Required resuscitation</option>
                                <option value="Birth asphyxia">Birth asphyxia</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="text" class="oh-input oh-value-input ph-custom-input" value="Immediate & lusty" autocomplete="off" hidden>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 5. NICU Admission -->
                <tr class="oh-row" data-row-kind="nicu" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">5</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="NICU Stay" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-select-custom-wrap">
                            <select class="oh-input ph-quick-select">
                                <option value="None" selected>None</option>
                                <option value="Yes - Neonatal Jaundice (Phototherapy)">Yes - Jaundice (Phototherapy)</option>
                                <option value="Yes - Neonatal Sepsis">Yes - Neonatal Sepsis</option>
                                <option value="Yes - RDS / TTN">Yes - RDS / TTN</option>
                                <option value="Yes - Perinatal Asphyxia">Yes - Perinatal Asphyxia</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="text" class="oh-input oh-value-input ph-custom-input" value="None" autocomplete="off" hidden>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 6. Feeding History -->
                <tr class="oh-row" data-row-kind="feeding" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">6</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Feeding" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-select-custom-wrap">
                            <select class="oh-input ph-quick-select">
                                <option value="Exclusive Breastfeeding (EBF) up to 6m" selected>Exclusive Breastfeeding (EBF) up to 6m</option>
                                <option value="Mixed Feeding (Breast milk + Formula)">Mixed Feeding (Breast + Formula)</option>
                                <option value="Complementary Feeding started at 6m">Complementary Feeding at 6m</option>
                                <option value="Family diet + Milk">Family diet + Milk</option>
                                <option value="Formula fed">Formula fed</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="text" class="oh-input oh-value-input ph-custom-input" value="Exclusive Breastfeeding (EBF) up to 6m" autocomplete="off" hidden>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 7. Immunization -->
                <tr class="oh-row" data-row-kind="immunization" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">7</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Immunization" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-select-custom-wrap">
                            <select class="oh-input ph-quick-select">
                                <option value="EPI complete as per age" selected>EPI complete as per age</option>
                                <option value="EPI fully completed">EPI fully completed</option>
                                <option value="EPI due / ongoing">EPI due / ongoing</option>
                                <option value="EPI + Optional vaccines taken">EPI + Optional vaccines taken</option>
                                <option value="Incomplete / Delayed">Incomplete / Delayed</option>
                                <option value="Unvaccinated">Unvaccinated</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="text" class="oh-input oh-value-input ph-custom-input" value="EPI complete as per age" autocomplete="off" hidden>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 8. BCG Scar -->
                <tr class="oh-row" data-row-kind="bcg_scar" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">8</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="BCG Scar" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-select-custom-wrap">
                            <select class="oh-input ph-quick-select">
                                <option value="Present (Left Deltoid)" selected>Present (Left Deltoid)</option>
                                <option value="Absent / Not Visible">Absent / Not Visible</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="text" class="oh-input oh-value-input ph-custom-input" value="Present (Left Deltoid)" autocomplete="off" hidden>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 9. Developmental Milestones -->
                <tr class="oh-row" data-row-kind="milestones" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">9</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Milestones" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-select-custom-wrap">
                            <select class="oh-input ph-quick-select">
                                <option value="Appropriate for age" selected>Appropriate for age</option>
                                <option value="Gross motor delayed">Gross motor delayed</option>
                                <option value="Speech / Language delayed">Speech / Language delayed</option>
                                <option value="Global developmental delay">Global developmental delay</option>
                                <option value="Neck holding (3m), Sitting (6m), Standing (9m)">Neck (3m), Sit (6m), Stand (9m)</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="text" class="oh-input oh-value-input ph-custom-input" value="Appropriate for age" autocomplete="off" hidden>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 10. Head Circumference (OFC) -->
                <tr class="oh-row" data-row-kind="ofc" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">10</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Head Circ. (OFC)" readonly></td>
                    <td class="oh-value-cell">
                        <div class="oh-value-split">
                            <input type="text" class="oh-input oh-duration-input ph-ofc-input" placeholder="e.g. 45" inputmode="decimal" autocomplete="off">
                            <select class="oh-input oh-unit-select ph-ofc-unit">
                                <option value="cm" selected>cm</option>
                                <option value="inch">inch</option>
                            </select>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>

                <!-- 11. MUAC -->
                <tr class="oh-row" data-row-kind="muac" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">11</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="MUAC" readonly></td>
                    <td class="oh-value-cell">
                        <div class="ph-muac-split">
                            <input type="text" class="oh-input oh-duration-input ph-muac-input" placeholder="e.g. 13.5" inputmode="decimal" autocomplete="off">
                            <select class="oh-input oh-unit-select ph-muac-unit">
                                <option value="cm" selected>cm</option>
                                <option value="mm">mm</option>
                            </select>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="oh-footer">
        <button type="button" class="ph-add-row-btn" id="ph-add-row-btn">Add More</button>
    </div>

    <template id="ph-row-template">
        <tr class="oh-row" draggable="true">
            <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
            <td class="oh-row-no"></td>
            <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" placeholder="Field name" autocomplete="off"></td>
            <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input" placeholder="Value / Findings" autocomplete="off"></td>
            <td class="oh-action oh-drag">
                <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>
