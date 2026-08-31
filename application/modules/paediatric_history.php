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
                            <div class="oh-header-actions">
                                <button type="button" class="ph-chart-btn" id="ph-open-growth-chart-btn" aria-haspopup="dialog" aria-controls="ph-growth-modal" title="Open Interactive WHO & CDC Growth Chart">
                                    <?= zrx_icon('bar-chart', 14) ?>
                                    <span>Growth Chart</span>
                                </button>
                            </div>
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
                            <span class="ph-muac-badge" id="ph-muac-badge" title="MUAC status">--</span>
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

<!-- Interactive WHO & CDC Growth Chart Modal -->
<div class="ph-growth-modal" id="ph-growth-modal" hidden>
    <div class="ph-growth-backdrop" data-ph-growth-close></div>
    <div class="ph-growth-panel" role="dialog" aria-modal="true" aria-labelledby="ph-growth-title">
        
        <!-- Header -->
        <div class="ph-growth-header">
            <div class="ph-growth-header-info">
                <h3 id="ph-growth-title">
                    <?= zrx_icon('bar-chart', 20) ?>
                    Paediatric Growth Chart & Anthropometry
                </h3>
                <p>WHO Child Growth Standards (0-5 yrs) & CDC Growth Reference (2-20 yrs) with automatic age mapping.</p>
            </div>
            <div class="ph-growth-header-controls">
                <div class="ph-gender-toggle-wrap">
                    <button type="button" class="ph-pill-btn ph-gender-btn active" data-ph-gender="Male">👦 Boy</button>
                    <button type="button" class="ph-pill-btn ph-gender-btn" data-ph-gender="Female">👧 Girl</button>
                </div>
                <button type="button" class="ph-growth-close" data-ph-growth-close aria-label="Close Growth Chart"><?= zrx_icon('x', 16) ?></button>
            </div>
        </div>

        <!-- Live Patient Stats & Classification Ribbon -->
        <div class="ph-patient-stats-bar" id="ph-patient-stats-bar">
            <div class="ph-stat-card">
                <span class="ph-stat-label">Age</span>
                <strong class="ph-stat-val" id="ph-stat-age">--</strong>
                <small class="ph-stat-sub" id="ph-stat-age-months">-- mo</small>
            </div>
            <div class="ph-stat-card">
                <span class="ph-stat-label">Weight</span>
                <strong class="ph-stat-val" id="ph-stat-wt">--</strong>
                <small class="ph-stat-badge" id="ph-badge-wfa">--</small>
            </div>
            <div class="ph-stat-card">
                <span class="ph-stat-label">Height / Length</span>
                <strong class="ph-stat-val" id="ph-stat-ht">--</strong>
                <small class="ph-stat-badge" id="ph-badge-hfa">--</small>
            </div>
            <div class="ph-stat-card">
                <span class="ph-stat-label">Head Circ. (OFC)</span>
                <strong class="ph-stat-val" id="ph-stat-hc">--</strong>
                <small class="ph-stat-badge" id="ph-badge-hcfa">--</small>
            </div>
            <div class="ph-stat-card">
                <span class="ph-stat-label">BMI</span>
                <strong class="ph-stat-val" id="ph-stat-bmi">--</strong>
                <small class="ph-stat-badge" id="ph-badge-bmifa">--</small>
            </div>
            <div class="ph-stat-card">
                <span class="ph-stat-label">MUAC</span>
                <strong class="ph-stat-val" id="ph-stat-muac">--</strong>
                <small class="ph-stat-badge" id="ph-badge-muac">--</small>
            </div>
        </div>

        <!-- Toolbars: Tabs, Standard Toggle, Curve View -->
        <div class="ph-chart-toolbar">
            <div class="ph-metric-tabs" id="ph-metric-tabs">
                <button type="button" class="ph-tab-btn active" data-metric="weight">
                    <span>⚖️ Weight-for-Age</span>
                </button>
                <button type="button" class="ph-tab-btn" data-metric="height">
                    <span>📏 Length/Height-for-Age</span>
                </button>
                <button type="button" class="ph-tab-btn" data-metric="hc">
                    <span>🧠 Head Circ. (0-5y)</span>
                </button>
                <button type="button" class="ph-tab-btn" data-metric="bmi">
                    <span>📊 BMI-for-Age</span>
                </button>
            </div>

            <div class="ph-toolbar-right">
                <!-- Standard selector -->
                <div class="ph-standard-wrap">
                    <span class="ph-tool-label">Standard:</span>
                    <div class="ph-btn-group" id="ph-standard-group">
                        <button type="button" class="ph-group-btn active" data-standard="auto" title="Auto-switch based on age (0-2y WHO, 2-5y WHO/CDC, 5-20y CDC)">Auto</button>
                        <button type="button" class="ph-group-btn" data-standard="who" title="WHO Child Growth Standards (0-5 Years)">WHO</button>
                        <button type="button" class="ph-group-btn" data-standard="cdc" title="CDC Growth Reference (2-20 Years)">CDC</button>
                    </div>
                </div>

                <!-- Curve mode (Percentile vs Z-Score) -->
                <div class="ph-curve-mode-wrap">
                    <span class="ph-tool-label">View:</span>
                    <div class="ph-btn-group" id="ph-curve-mode-group">
                        <button type="button" class="ph-group-btn active" data-mode="percentile">Percentiles</button>
                        <button type="button" class="ph-group-btn" data-mode="zscore">Z-Score (SD)</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canvas Container & Chart Area -->
        <div class="ph-chart-canvas-container">
            <div class="ph-canvas-wrapper" id="ph-canvas-wrapper">
                <canvas id="ph-growth-canvas" width="980" height="460"></canvas>
                <div class="ph-canvas-tooltip" id="ph-canvas-tooltip" hidden></div>
            </div>
            
            <!-- Side panel: Interpretation & Multi-visit tracker -->
            <div class="ph-chart-side-panel">
                <div class="ph-side-section">
                    <h4>Clinical Interpretation</h4>
                    <div class="ph-interpret-box" id="ph-interpret-box">
                        <div class="ph-interpret-item">
                            <span class="ph-ii-label">Growth Status:</span>
                            <strong class="ph-ii-val" id="ph-ii-growth">Normal growth pattern</strong>
                        </div>
                        <div class="ph-interpret-item">
                            <span class="ph-ii-label">Percentile:</span>
                            <span class="ph-ii-val" id="ph-ii-pct">50th Percentile</span>
                        </div>
                        <div class="ph-interpret-item">
                            <span class="ph-ii-label">Z-Score:</span>
                            <span class="ph-ii-val" id="ph-ii-zscore">0.00 SD</span>
                        </div>
                        <div class="ph-interpret-item">
                            <span class="ph-ii-label">Nutritional State:</span>
                            <span class="ph-ii-val" id="ph-ii-nutrition">Adequate</span>
                        </div>
                    </div>
                </div>

                <div class="ph-side-section">
                    <div class="ph-multi-visit-header">
                        <h4>Visit Trajectory</h4>
                        <button type="button" class="ph-mini-btn" id="ph-add-visit-pt-btn" title="Add previous visit point for growth velocity">+ Add Point</button>
                    </div>
                    <div class="ph-visit-points-list" id="ph-visit-points-list">
                        <!-- Populated dynamically -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="ph-growth-footer">
            <div class="ph-growth-footer-left">
                <label class="ph-growth-print-toggle">
                    <input type="checkbox" id="ph-include-print-checkbox" class="oh-chart-print-checkbox">
                    <span>Include Paediatric Growth Chart in prescription print (Attached page)</span>
                </label>
            </div>
            <div class="ph-growth-footer-actions">
                <button type="button" class="btn btn-outline" id="ph-insert-summary-btn" title="Insert Growth Assessment into Prescription Note">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Insert to Note
                </button>
                <button type="button" class="btn btn-secondary" id="ph-print-chart-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print Chart
                </button>
                <button type="button" class="btn btn-primary" data-ph-growth-close>Done</button>
            </div>
        </div>

    </div>
</div>
