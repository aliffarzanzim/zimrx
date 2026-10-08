<?php
declare(strict_types=1);

// Obstetric History (O/H) module: parity, gravida, ALC, LMP, EDD, and obstetric calculator modal.
?>
<div class="oh-wrapper js-oh-module">
    <div class="oh-table-container">
        <table class="oh-table" id="oh-table">
            <colgroup>
                <col style="width: 32px;">
                <col style="width: 42px;">
                <col style="width: 34%;">
                <col>
                <col style="width: 38px;">
            </colgroup>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;"></th>
                    <th style="width: 42px; text-align: center;">#</th>
                    <th colspan="2">
                        <div class="oh-header-flex">
                            <span>O/H</span>
                            <div class="oh-header-actions">
                                <button type="button" class="oh-calc-btn" title="Obstetric Calculators" aria-haspopup="dialog" aria-controls="oh-calc-modal">
                                    <?= zrx_icon('calculator', 14) ?>
                                </button>
                                <button type="button" class="oh-chart-btn" aria-haspopup="dialog" aria-controls="oh-chart-modal">Obstetric Chart</button>
                                <button type="button" class="oh-pv-btn" id="oh-pv-toggle-btn" title="Toggle Pelvic Examination (P/S & P/V)">P/S &amp; P/V Exam</button>
                            </div>
                        </div>
                    </th>
                    <th style="width: 38px; text-align: center;"></th>
                </tr>
            </thead>
            <tbody>
                <tr class="oh-row" data-row-kind="married_for" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">1</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Married for" readonly></td>
                    <td class="oh-value-cell">
                        <div class="oh-value-split">
                            <input type="text" class="oh-input oh-duration-input" inputmode="numeric" autocomplete="off">
                            <select class="oh-input oh-unit-select">
                                <option value="Years" selected>Years</option>
                                <option value="Year">Year</option>
                                <option value="Months">Months</option>
                                <option value="Month">Month</option>
                                <option value="Weeks">Weeks</option>
                                <option value="Week">Week</option>
                                <option value="Days">Days</option>
                                <option value="Day">Day</option>
                            </select>
                        </div>
                    </td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <tr class="oh-row" data-row-kind="para" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">2</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Para" readonly></td>
                    <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input" inputmode="numeric" autocomplete="off"></td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <tr class="oh-row" data-row-kind="gravida" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">3</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="Gravida" readonly></td>
                    <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input" inputmode="numeric" autocomplete="off"></td>
                    <td class="oh-action oh-drag">
                        <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <tr class="oh-row" data-row-kind="alc" draggable="true">
                    <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="oh-row-no">4</td>
                    <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" value="ALC" readonly></td>
                    <td class="oh-value-cell">
                        <div class="oh-value-split">
                            <input type="text" class="oh-input oh-duration-input" inputmode="numeric" autocomplete="off">
                            <select class="oh-input oh-unit-select">
                                <option value="Years" selected>Years</option>
                                <option value="Year">Year</option>
                                <option value="Months">Months</option>
                                <option value="Month">Month</option>
                                <option value="Weeks">Weeks</option>
                                <option value="Week">Week</option>
                                <option value="Days">Days</option>
                                <option value="Day">Day</option>
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
        <button type="button" class="oh-add-row-btn">Add More</button>
    </div>

    <!-- Pelvic Examination (P/S & P/V) Section -->
    <div class="oh-pelvic-section" id="oh-pelvic-section" hidden>
        <div class="oh-pelvic-header">
            <div class="oh-pelvic-title-group">
                <?= zrx_icon('target', 13) ?>
                <span>Pelvic Examination (P/S &amp; P/V)</span>
            </div>
            <div class="oh-pelvic-actions">
                <button type="button" class="oh-pelvic-quick-btn" id="oh-pelvic-normal-btn" title="Pre-fill normal pelvic examination">Normal P/S &amp; P/V</button>
                <button type="button" class="oh-pelvic-quick-btn" id="oh-pelvic-clear-btn" title="Clear pelvic examination fields">Clear</button>
            </div>
        </div>

        <div class="oh-pelvic-body">
            <!-- Dual Grid: P/S on Left, P/V on Right -->
            <div class="oh-pelvic-dual-grid">
                <!-- P/S (Per Speculum) Column -->
                <div class="oh-pelvic-col">
                    <div class="oh-pelvic-col-head">
                        <span class="oh-pelvic-col-tag">P/S</span>
                        <span>Per Speculum Examination</span>
                    </div>
                    <div class="oh-pelvic-field">
                        <label class="oh-pelvic-lbl">Cervix Appearance</label>
                        <select class="oh-pelvic-select" id="oh-ps-cervix">
                            <option value="Healthy / Smooth">Healthy / Smooth</option>
                            <option value="Cervical Erosion / Ectropion">Cervical Erosion / Ectropion</option>
                            <option value="Endocervical Polyp">Endocervical Polyp</option>
                            <option value="Hypertrophied / Chronic Cervicitis">Hypertrophied / Cervicitis</option>
                            <option value="Contact Bleeding Present">Contact Bleeding Present</option>
                            <option value="Suspicious Cauliflower Growth">Suspicious Growth / Malignancy</option>
                        </select>
                    </div>
                    <div class="oh-pelvic-field">
                        <label class="oh-pelvic-lbl">External Os</label>
                        <select class="oh-pelvic-select" id="oh-ps-os">
                            <option value="Closed">Closed</option>
                            <option value="Parous (transverse slit)">Parous (transverse slit)</option>
                            <option value="Nulliparous (pinpoint)">Nulliparous (pinpoint)</option>
                            <option value="Patulous / Open">Patulous / Open</option>
                        </select>
                    </div>
                    <div class="oh-pelvic-field">
                        <label class="oh-pelvic-lbl">Vaginal Discharge</label>
                        <select class="oh-pelvic-select" id="oh-ps-discharge">
                            <option value="None / Physiological">None / Physiological</option>
                            <option value="Curdy White (Candida)">Curdy White (Candida)</option>
                            <option value="Frothy Greenish-Yellow (Trichomoniasis)">Frothy Greenish-Yellow</option>
                            <option value="Purulent / Mucopurulent">Purulent / Mucopurulent</option>
                            <option value="Blood-stained">Blood-stained</option>
                            <option value="Foul-smelling watery">Foul-smelling watery</option>
                        </select>
                    </div>
                    <div class="oh-pelvic-field">
                        <label class="oh-pelvic-lbl">Vaginal Walls</label>
                        <select class="oh-pelvic-select" id="oh-ps-walls">
                            <option value="Healthy / Rugose">Healthy / Rugose</option>
                            <option value="Congested / Erythematous">Congested / Erythematous</option>
                            <option value="Cystocele present">Cystocele present</option>
                            <option value="Rectocele present">Rectocele present</option>
                            <option value="Uterovaginal Prolapse">UV Prolapse</option>
                        </select>
                    </div>
                </div>

                <!-- P/V (Per Vaginal / Bimanual) Column -->
                <div class="oh-pelvic-col">
                    <div class="oh-pelvic-col-head">
                        <span class="oh-pelvic-col-tag">P/V</span>
                        <span>Bimanual Examination</span>
                    </div>
                    <div class="oh-pelvic-field">
                        <label class="oh-pelvic-lbl">Uterine Position &amp; Size</label>
                        <div class="oh-pelvic-flex">
                            <select class="oh-pelvic-select" id="oh-pv-position">
                                <option value="Anteverted (AV), Anteflexed">Anteverted (AV)</option>
                                <option value="Retroverted (RV), Retroflexed">Retroverted (RV)</option>
                                <option value="Midposition">Midposition</option>
                            </select>
                            <select class="oh-pelvic-select" id="oh-pv-size">
                                <option value="Normal size">Normal size</option>
                                <option value="Bulky">Bulky</option>
                                <option value="6-8 wks size">6-8 wks size</option>
                                <option value="8-10 wks size">8-10 wks size</option>
                                <option value="10-12 wks size">10-12 wks size</option>
                                <option value=">12 wks size">>12 wks size</option>
                            </select>
                        </div>
                    </div>
                    <div class="oh-pelvic-field">
                        <label class="oh-pelvic-lbl">Mobility &amp; Tenderness</label>
                        <div class="oh-pelvic-flex">
                            <select class="oh-pelvic-select" id="oh-pv-mobility">
                                <option value="Freely mobile">Freely mobile</option>
                                <option value="Restricted mobility">Restricted mobility</option>
                                <option value="Fixed / Frozen pelvis">Fixed / Frozen pelvis</option>
                            </select>
                            <select class="oh-pelvic-select" id="oh-pv-tenderness">
                                <option value="Non-tender">Non-tender</option>
                                <option value="Tender on palpation">Tender</option>
                            </select>
                        </div>
                    </div>
                    <div class="oh-pelvic-field">
                        <label class="oh-pelvic-lbl">Cervical Motion Tenderness (CMT)</label>
                        <select class="oh-pelvic-select" id="oh-pv-cmt">
                            <option value="Absent (Negative)">Absent (-ve)</option>
                            <option value="Present (Positive / Chandelier sign)">Present (+ve / Chandelier sign)</option>
                        </select>
                    </div>
                    <div class="oh-pelvic-field">
                        <label class="oh-pelvic-lbl">Fornices &amp; Adnexa</label>
                        <select class="oh-pelvic-select" id="oh-pv-adnexa">
                            <option value="Fornices free, bilateral adnexa clear and non-tender">Fornices free, adnexa clear</option>
                            <option value="Right adnexal tenderness / fullness">Right adnexal tenderness</option>
                            <option value="Left adnexal tenderness / fullness">Left adnexal tenderness</option>
                            <option value="Bilateral adnexal tenderness (PID)">Bilateral tenderness (PID)</option>
                            <option value="Palpable adnexal mass / cyst">Adnexal mass / cyst</option>
                            <option value="Pouch of Douglas nodularity / tenderness">POD nodularity (Endometriosis)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Findings summary field -->
            <div class="oh-pelvic-notes-row">
                <label class="oh-pelvic-lbl" for="oh-pelvic-summary-input">P/S &amp; P/V Summary Note</label>
                <input type="text" class="oh-pelvic-input" id="oh-pelvic-summary-input" placeholder="e.g. P/S: Healthy cervix, no discharge. P/V: Uterus AV normal size, mobile, fornices free." autocomplete="off">
            </div>
        </div>
    </div>

    <template id="oh-row-template">
        <tr class="oh-row" draggable="true">
            <td class="oh-action oh-del"><button type="button" title="Remove Row">X</button></td>
            <td class="oh-row-no"></td>
            <td class="oh-name-cell"><input type="text" class="oh-input oh-name-input" autocomplete="off"></td>
            <td class="oh-value-cell"><input type="text" class="oh-input oh-value-input" autocomplete="off"></td>
            <td class="oh-action oh-drag">
                <button type="button" class="oh-row-move-btn" title="Move Row" draggable="true">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<div class="oh-chart-modal" id="oh-chart-modal" hidden>
    <div class="oh-chart-backdrop" data-oh-chart-close></div>
    <div class="oh-chart-panel" role="dialog" aria-modal="true" aria-labelledby="oh-chart-title">
        <div class="oh-chart-header">
            <div>
                <h3 id="oh-chart-title">Obstetric Chart</h3>
                <p>Pregnancy, labour, puerperium, and baby history in one table.</p>
            </div>
            <button type="button" class="oh-chart-close" data-oh-chart-close aria-label="Close Obstetric Chart"><?= zrx_icon('x', 16) ?></button>
        </div>

        <div class="oh-chart-table-wrap">
            <table class="oh-chart-table">
                <colgroup>
                    <col style="width: 32px;">
                    <col style="width: 42px;">
                    <col style="width: 96px;">
                    <col style="width: 140px;">
                    <col style="width: 84px;">
                    <col style="width: 140px;">
                    <col style="width: 110px;">
                    <col style="width: 96px;">
                    <col style="width: 140px;">
                    <col style="width: 84px;">
                    <col style="width: 84px;">
                    <col style="width: 120px;">
                    <col style="width: 120px;">
                    <col style="width: 120px;">
                    <col style="width: 38px;">
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2"></th>
                        <th rowspan="2">Sl No</th>
                        <th colspan="4">Pregnancy</th>
                        <th colspan="3">Labour</th>
                        <th colspan="1">Puerperium</th>
                        <th colspan="4">Baby</th>
                        <th rowspan="2"></th>
                    </tr>
                    <tr>
                        <th>Year</th>
                        <th>Duration</th>
                        <th>ANC</th>
                        <th>Normal / Complication</th>
                        <th>Place</th>
                        <th>Mode</th>
                        <th>Normal / Complication</th>
                        <th>N/C</th>
                        <th>Sex</th>
                        <th>Status</th>
                        <th>Feeding</th>
                        <th>Immunization</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= 1; $i++): ?>
                    <tr class="oh-chart-row" draggable="true">
                        <td class="oh-chart-action oh-chart-del"><button type="button" title="Remove Row">X</button></td>
                        <td class="oh-chart-row-no"><?= $i ?></td>
                        <td><input type="text" class="oh-chart-input" inputmode="numeric" autocomplete="off"></td>
                        <td>
                            <div class="oh-chart-split">
                                <input type="text" class="oh-chart-input" inputmode="numeric" autocomplete="off">
                                <select class="oh-chart-input oh-chart-select">
                                    <option value="Weeks" selected>Weeks</option>
                                    <option value="Week">Week</option>
                                    <option value="Months">Months</option>
                                    <option value="Month">Month</option>
                                    <option value="Days">Days</option>
                                    <option value="Day">Day</option>
                                </select>
                            </div>
                        </td>
                        <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                        <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                        <td>
                            <div class="oh-chart-place-wrap">
                                <select class="oh-chart-input oh-chart-select oh-chart-place-select">
                                    <option value=""></option>
                                    <option value="Home">Home</option>
                                    <option value="Hospital">Hospital</option>
                                    <option value="Custom">Custom</option>
                                </select>
                                <input type="text" class="oh-chart-input oh-chart-place-custom" placeholder="Custom place" autocomplete="off" hidden>
                            </div>
                        </td>
                        <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                        <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                        <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                        <td>
                            <select class="oh-chart-input oh-chart-select">
                                <option value=""></option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Others">Others</option>
                                <option value="Underdetermined">Underdetermined</option>
                            </select>
                        </td>
                        <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                        <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                        <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                        <td class="oh-chart-action oh-chart-drag">
                            <button type="button" class="oh-chart-row-move-btn" title="Move Row" draggable="true">
                                <?= zrx_icon('move', 14) ?>
                            </button>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="oh-chart-footer">
            <label class="oh-chart-print-toggle">
                <input type="checkbox" class="oh-chart-print-checkbox">
                <span>Include obstetrics chart in print (Will be printed in a separate page)</span>
            </label>
            <div class="oh-chart-footer-actions">
                <button type="button" class="oh-chart-save-btn">Save</button>
                <button type="button" class="oh-chart-add-row-btn">Add More</button>
            </div>
        </div>

        <template id="oh-chart-row-template">
            <tr class="oh-chart-row" draggable="true">
                <td class="oh-chart-action oh-chart-del"><button type="button" title="Remove Row">X</button></td>
                <td class="oh-chart-row-no"></td>
                <td><input type="text" class="oh-chart-input" inputmode="numeric" autocomplete="off"></td>
                <td>
                    <div class="oh-chart-split">
                        <input type="text" class="oh-chart-input" inputmode="numeric" autocomplete="off">
                        <select class="oh-chart-input oh-chart-select">
                            <option value="Weeks" selected>Weeks</option>
                            <option value="Week">Week</option>
                            <option value="Months">Months</option>
                            <option value="Month">Month</option>
                            <option value="Days">Days</option>
                            <option value="Day">Day</option>
                        </select>
                    </div>
                </td>
                <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                <td>
                    <div class="oh-chart-place-wrap">
                        <select class="oh-chart-input oh-chart-select oh-chart-place-select">
                            <option value=""></option>
                            <option value="Home">Home</option>
                            <option value="Hospital">Hospital</option>
                            <option value="Custom">Custom</option>
                        </select>
                        <input type="text" class="oh-chart-input oh-chart-place-custom" placeholder="Custom place" autocomplete="off" hidden>
                    </div>
                </td>
                <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                <td>
                    <select class="oh-chart-input oh-chart-select">
                        <option value=""></option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Others">Others</option>
                        <option value="Underdetermined">Underdetermined</option>
                    </select>
                </td>
                <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                <td><input type="text" class="oh-chart-input" autocomplete="off"></td>
                <td class="oh-chart-action oh-chart-drag">
                    <button type="button" class="oh-chart-row-move-btn" title="Move Row" draggable="true">
                        <?= zrx_icon('move', 14) ?>
                    </button>
                </td>
            </tr>
        </template>
    </div>
</div>

<div class="oh-calc-modal" id="oh-calc-modal" hidden>
    <div class="oh-calc-backdrop" data-oh-calc-close></div>
    <div class="oh-calc-panel" role="dialog" aria-modal="true" aria-labelledby="oh-calc-title">
        <div class="oh-calc-header">
            <div>
                <h3 id="oh-calc-title">Obstetric Calculators</h3>
                <p>Quick helpers for Para, Gravida, Married for, and ALC.</p>
            </div>
            <button type="button" class="oh-calc-close" data-oh-calc-close aria-label="Close Obstetric Calculators">&times;</button>
        </div>

        <div class="oh-calc-grid">
            <section class="oh-calc-card">
                <div class="oh-calc-card-head">
                    <h4>Para / Gravida Calculator</h4>
                    <p>Use para, abortions, and current pregnancy to fill the rows.</p>
                </div>
                <div class="oh-calc-form">
                    <label>
                        <span>Para</span>
                        <input type="number" min="0" step="1" class="oh-calc-input" id="oh-calc-para" value="0">
                    </label>
                    <label>
                        <span>Abortions / Miscarriages</span>
                        <input type="number" min="0" step="1" class="oh-calc-input" id="oh-calc-abortion" value="0">
                    </label>
                    <label>
                        <span>Current Pregnancy</span>
                        <select class="oh-calc-input" id="oh-calc-current-pregnancy">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </label>
                </div>
                <div class="oh-calc-result">
                    <div><strong>Gravida</strong> <span class="oh-calc-output" id="oh-calc-gravida-output">0</span></div>
                </div>
                <div class="oh-calc-actions">
                    <button type="button" class="oh-calc-apply-btn" data-oh-calc-apply="para-gravida">Apply to O/H</button>
                </div>
            </section>

            <section class="oh-calc-card">
                <div class="oh-calc-card-head">
                    <h4>Date Calculator</h4>
                    <p>Calculate a duration for Married for or ALC from dates.</p>
                </div>
                <div class="oh-calc-form">
                    <label>
                        <span>Target Row</span>
                        <select class="oh-calc-input" id="oh-date-target">
                            <option value="married_for">Married for</option>
                            <option value="alc">ALC</option>
                        </select>
                    </label>
                    <label>
                        <span>From Date</span>
                        <input type="date" class="oh-calc-input" id="oh-date-from">
                    </label>
                    <label>
                        <span>To Date</span>
                        <input type="date" class="oh-calc-input" id="oh-date-to">
                    </label>
                </div>
                <div class="oh-calc-result">
                    <div><strong>Calculated</strong> <span class="oh-calc-output" id="oh-date-output">0 Years</span></div>
                </div>
                <div class="oh-calc-actions">
                    <button type="button" class="oh-calc-apply-btn" data-oh-calc-apply="date">Apply to O/H</button>
                </div>
            </section>
        </div>
    </div>
</div>
