<?php
declare(strict_types=1);

// Clinical calculators panel: BMI, insulin dosing, pediatric Z-score, BMR/TDEE, eGFR/CrCl, EDD, and vaccine schedules.
?>
<div id="calculators-wrapper" class="calculators-module-wrapper">
    
    <div class="calc-header-wrap">
        <!-- Centered Module Title -->
        <h3 class="calc-module-heading">
            Calculators
        </h3>

        <!-- Main Card Body -->
        <div class="calc-main-card">
            
            <!-- Tabs -->
            <div class="calc-tabs-bar">
                <button type="button" class="calc-tab-btn active" data-target="pane-bmi">BMI</button>
                <button type="button" class="calc-tab-btn" data-target="pane-insulin">Insulin</button>
                <button type="button" class="calc-tab-btn" data-target="pane-zscore">Z-Score</button>
                <button type="button" class="calc-tab-btn" data-target="pane-bmr">BMR</button>
                <button type="button" class="calc-tab-btn" data-target="pane-egfr">eGFR</button>
                <button type="button" class="calc-tab-btn" data-target="pane-edd">EDD</button>
                <button type="button" class="calc-tab-btn" data-target="pane-td">TD Vaccine</button>
                <button type="button" class="calc-tab-btn" data-target="pane-rabies">Rabies Vaccine</button>
            </div>

            <!-- Panes Container -->
            <div class="calc-panes-body">
                
                <!-- 1. BMI Pane -->
                <div class="calc-pane active" id="pane-bmi">
                    <div class="calc-row">
                        <div class="calc-col">
                            <label class="calc-label">Weight (kg)</label>
                            <input type="number" id="bmi-kg" class="calc-inp calc-trigger-bmi" placeholder="0">
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Height (feet)</label>
                            <input type="number" id="bmi-ft" class="calc-inp calc-trigger-bmi" placeholder="0">
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Height (inch)</label>
                            <input type="number" id="bmi-in" class="calc-inp calc-trigger-bmi" placeholder="0">
                        </div>
                    </div>
                    <div class="calc-row calc-result-box">
                        <div class="calc-col">
                            <label class="calc-label">BMI Result</label>
                            <input type="text" id="bmi-res" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col calc-col-flex-15">
                            <label class="calc-label">Class</label>
                            <input type="text" id="bmi-cls" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Ideal Wt</label>
                            <input type="text" id="bmi-ideal" class="calc-inp" readonly>
                        </div>
                    </div>
                </div>

                <!-- 2. Insulin Pane -->
                <div class="calc-pane" id="pane-insulin">
                    <div class="calc-row">
                        <div class="calc-col">
                            <label class="calc-label">Weight (kg)</label>
                            <input type="number" id="ins-kg" class="calc-inp calc-trigger-ins" value="54">
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Unit / Kg</label>
                            <input type="number" step="0.1" id="ins-unit" class="calc-inp calc-trigger-ins" value="0.3">
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Schedule</label>
                            <select id="ins-time" class="calc-inp calc-trigger-ins">
                                <option value="BD">BD (2 times)</option>
                                <option value="TDS">TDS (3 times)</option>
                            </select>
                        </div>
                    </div>
                    <div class="calc-row calc-result-box">
                        <div class="calc-col">
                            <label class="calc-label">Total Unit</label>
                            <input type="text" id="ins-total" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col calc-col-flex-20">
                            <label class="calc-label">Dose Distribution</label>
                            <input type="text" id="ins-dose" class="calc-inp calc-inp-dose" readonly>
                        </div>
                    </div>
                </div>

                <!-- 3. Z-Score Pane (Approximate) -->
                <div class="calc-pane" id="pane-zscore">
                    <div class="calc-row">
                        <div class="calc-col calc-col-flex-08">
                            <label class="calc-label">Age (Months)</label>
                            <input type="number" id="z-age" class="calc-inp calc-trigger-z" placeholder="0-60">
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Gender</label>
                            <div class="calc-radio-group">
                                <label><input type="radio" name="z-gen" value="M" class="calc-trigger-z" checked> Boy</label>
                                <label><input type="radio" name="z-gen" value="F" class="calc-trigger-z"> Girl</label>
                            </div>
                        </div>
                        <div class="calc-col calc-col-flex-08">
                            <label class="calc-label">Weight (kg)</label>
                            <input type="number" step="0.1" id="z-wt" class="calc-inp calc-trigger-z" placeholder="0.0">
                        </div>
                    </div>
                    <div class="calc-row calc-result-box">
                        <div class="calc-col">
                            <label class="calc-label">Z-Score</label>
                            <input type="text" id="z-res" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Ideal Wt</label>
                            <input type="text" id="z-ideal" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Diff (kg)</label>
                            <input type="text" id="z-diff" class="calc-inp" readonly>
                        </div>
                    </div>
                    <p class="calc-footnote-text">* Calibrated with official WHO Child Growth Standards (LMS data tables).</p>
                </div>

                <!-- 4. BMR Pane -->
                <div class="calc-pane" id="pane-bmr">
                    <div class="calc-row">
                        <div class="calc-col"><label class="calc-label">Weight (kg)</label><input type="number" id="bmr-kg" class="calc-inp calc-trigger-bmr"></div>
                        <div class="calc-col"><label class="calc-label">Ht (ft)</label><input type="number" id="bmr-ft" class="calc-inp calc-trigger-bmr"></div>
                        <div class="calc-col"><label class="calc-label">Ht (in)</label><input type="number" id="bmr-in" class="calc-inp calc-trigger-bmr"></div>
                    </div>
                    <div class="calc-row">
                        <div class="calc-col"><label class="calc-label">Age</label><input type="number" id="bmr-age" class="calc-inp calc-trigger-bmr"></div>
                        <div class="calc-col">
                            <label class="calc-label">Gender</label>
                            <div class="calc-radio-group">
                                <label><input type="radio" name="bmr-gen" value="M" class="calc-trigger-bmr" checked> Male</label>
                                <label><input type="radio" name="bmr-gen" value="F" class="calc-trigger-bmr"> Fem</label>
                            </div>
                        </div>
                        <div class="calc-col calc-col-flex-15">
                            <label class="calc-label">Activity</label>
                            <select id="bmr-act" class="calc-inp calc-trigger-bmr">
                                <option value="1.2">Sedentary</option>
                                <option value="1.375">Lightly Active</option>
                                <option value="1.55">Moderately Active</option>
                                <option value="1.725">Very Active</option>
                            </select>
                        </div>
                    </div>
                    <div class="calc-row calc-result-box">
                        <div class="calc-col">
                            <label class="calc-label">BMR (kcal/day)</label>
                            <input type="text" id="bmr-res" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">TDEE (Maintenance)</label>
                            <input type="text" id="bmr-tdee" class="calc-inp" readonly>
                        </div>
                    </div>
                </div>

                <!-- 5. eGFR Pane -->
                <div class="calc-pane" id="pane-egfr">
                    <div class="calc-row">
                        <div class="calc-col calc-col-flex-15">
                            <label class="calc-label">S. Creatinine</label>
                            <input type="number" step="0.1" id="egfr-cr" class="calc-inp calc-trigger-egfr">
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Unit</label>
                            <select id="egfr-unit" class="calc-inp calc-trigger-egfr">
                                <option value="mg">mg/dL</option>
                                <option value="umol">µmol/L</option>
                            </select>
                        </div>
                    </div>
                    <div class="calc-row">
                        <div class="calc-col"><label class="calc-label">Weight (kg)</label><input type="number" id="egfr-kg" class="calc-inp calc-trigger-egfr"></div>
                        <div class="calc-col"><label class="calc-label">Age</label><input type="number" id="egfr-age" class="calc-inp calc-trigger-egfr"></div>
                        <div class="calc-col">
                            <label class="calc-label">Gender</label>
                            <div class="calc-radio-group">
                                <label><input type="radio" name="egfr-gen" value="M" class="calc-trigger-egfr" checked> M</label>
                                <label><input type="radio" name="egfr-gen" value="F" class="calc-trigger-egfr"> F</label>
                            </div>
                        </div>
                    </div>
                    <div class="calc-row calc-result-box">
                        <div class="calc-col">
                            <label class="calc-label">CrCl (Cockcroft-Gault)</label>
                            <input type="text" id="egfr-res" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col calc-col-flex-15">
                            <label class="calc-label">CKD Stage Estimation</label>
                            <input type="text" id="egfr-stage" class="calc-inp" readonly>
                        </div>
                    </div>
                </div>

                <!-- 6. EDD Pane -->
                <div class="calc-pane" id="pane-edd">
                    <div class="calc-row">
                        <div class="calc-col">
                            <label class="calc-label">LMP Date</label>
                            <input type="date" id="edd-lmp" class="calc-inp calc-trigger-edd">
                        </div>
                    </div>
                    <div class="calc-row calc-result-box">
                        <div class="calc-col">
                            <label class="calc-label">Estimated Delivery Date (EDD)</label>
                            <input type="text" id="edd-res" class="calc-inp calc-inp-edd-res" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Gestational Age (Today)</label>
                            <input type="text" id="edd-ga" class="calc-inp" readonly>
                        </div>
                    </div>
                </div>

                <!-- 7. TD Vaccine Pane -->
                <div class="calc-pane" id="pane-td">
                    <div class="calc-row">
                        <div class="calc-col">
                            <label class="calc-label">TD Dose 1 Date</label>
                            <input type="date" id="td-date-1" class="calc-inp calc-trigger-td">
                        </div>
                    </div>
                    <div class="calc-row calc-result-box">
                        <div class="calc-col">
                            <label class="calc-label">Dose 2 (+4 weeks)</label>
                            <input type="text" id="td-date-2" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Dose 3 (+6 months)</label>
                            <input type="text" id="td-date-3" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Dose 4 (+1 year)</label>
                            <input type="text" id="td-date-4" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Dose 5 (+1 year)</label>
                            <input type="text" id="td-date-5" class="calc-inp" readonly>
                        </div>
                    </div>
                </div>

                <!-- 8. Rabies Vaccine Pane -->
                <div class="calc-pane" id="pane-rabies">
                    <div class="calc-row">
                        <div class="calc-col">
                            <label class="calc-label">Day 0 (Exposure / Dose 1)</label>
                            <input type="date" id="rabies-date-0" class="calc-inp calc-trigger-rabies">
                        </div>
                    </div>
                    <div class="calc-row calc-result-box">
                        <div class="calc-col">
                            <label class="calc-label">Day 3</label>
                            <input type="text" id="rabies-date-3" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Day 7</label>
                            <input type="text" id="rabies-date-7" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Day 14</label>
                            <input type="text" id="rabies-date-14" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col">
                            <label class="calc-label">Day 28</label>
                            <input type="text" id="rabies-date-28" class="calc-inp" readonly>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="assets/js/modules/calculators.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/calculators.js') ?>"></script>
