
<style>
    .calc-tab-btn {
        flex: 1 1 auto; 
        padding: 12px 10px; 
        text-align: center; 
        background: transparent; 
        border: none; 
        border-right: 1px solid #cbd5e1; 
        border-bottom: 1px solid #cbd5e1;
        font-weight: 600; 
        color: #64748b; 
        cursor: pointer; 
        transition: all 0.2s; 
        font-size: 0.85rem;
        white-space: nowrap;
    }
    .calc-tab-btn:last-child { 
        border-right: none; 
    }
    .calc-tab-btn.active { 
        background: #ffffff; 
        color: #2563eb; 
        border-bottom: 1px solid #ffffff;
        box-shadow: inset 0 3px 0 #2563eb; 
    }
    .calc-tab-btn:hover:not(.active) { 
        background: #e2e8f0; 
        color: #0f172a; 
    }
    .calc-pane { 
        display: none; 
        flex-direction: column; 
        gap: 15px; 
        animation: fadeIn 0.3s ease;
    }
    .calc-pane.active { 
        display: flex; 
    }
    .calc-inp { 
        width: 100%; 
        height: 34px; 
        border: 1px solid #cbd5e1; 
        border-radius: 6px; 
        padding: 0 10px; 
        font-family: inherit; 
        font-size: 0.85rem; 
        color: #0f172a; 
        transition: border-color 0.2s; 
        box-sizing: border-box; 
    }
    .calc-inp:focus { 
        border-color: #2563eb; 
        outline: none; 
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1); 
    }
    .calc-inp[readonly] { 
        background: #f8fafc; 
        font-weight: 700; 
        color: #1e40af; 
    }
    .calc-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 4px;
        display: block;
    }
    .calc-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    .calc-col {
        flex: 1;
        min-width: 120px;
    }
    .calc-radio-group {
        display: flex;
        gap: 15px;
        height: 34px;
        align-items: center;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 0 10px;
    }
    .calc-radio-group label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #0f172a;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(2px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div id="calculators-wrapper" style="background: #e2e8f0; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; height: 100%;">
    
    <div style="display: flex; flex-direction: column;">
        <!-- Centered Module Title -->
        <h3 style="text-align: center; font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'SolaimanLipi', sans-serif;">
            Calculators
        </h3>

        <!-- Main Card Body -->
        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            
            <!-- Tabs -->
            <div style="display: flex; flex-wrap: wrap; background: #f8fafc; border-bottom: 1px solid #cbd5e1;">
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
            <div style="padding: 20px;">
                
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
                    <div class="calc-row" style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <div class="calc-col">
                            <label class="calc-label">BMI Result</label>
                            <input type="text" id="bmi-res" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col" style="flex: 1.5;">
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
                    <div class="calc-row" style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <div class="calc-col">
                            <label class="calc-label">Total Unit</label>
                            <input type="text" id="ins-total" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col" style="flex: 2;">
                            <label class="calc-label">Dose Distribution</label>
                            <input type="text" id="ins-dose" class="calc-inp" readonly style="font-family: 'SolaimanLipi', sans-serif; font-size: 1rem;">
                        </div>
                    </div>
                </div>

                <!-- 3. Z-Score Pane (Approximate) -->
                <div class="calc-pane" id="pane-zscore">
                    <div class="calc-row">
                        <div class="calc-col" style="flex: 0.8;">
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
                        <div class="calc-col" style="flex: 0.8;">
                            <label class="calc-label">Weight (kg)</label>
                            <input type="number" step="0.1" id="z-wt" class="calc-inp calc-trigger-z" placeholder="0.0">
                        </div>
                    </div>
                    <div class="calc-row" style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 6px;">
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
                    <p style="margin:0; font-size:0.75rem; color:#64748b; text-align:center;">* Uses WHO approximate formulas for weight-for-age.</p>
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
                        <div class="calc-col" style="flex: 1.5;">
                            <label class="calc-label">Activity</label>
                            <select id="bmr-act" class="calc-inp calc-trigger-bmr">
                                <option value="1.2">Sedentary</option>
                                <option value="1.375">Lightly Active</option>
                                <option value="1.55">Moderately Active</option>
                                <option value="1.725">Very Active</option>
                            </select>
                        </div>
                    </div>
                    <div class="calc-row" style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 6px;">
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
                        <div class="calc-col" style="flex: 1.5;">
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
                    <div class="calc-row" style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <div class="calc-col">
                            <label class="calc-label">CrCl (Cockcroft-Gault)</label>
                            <input type="text" id="egfr-res" class="calc-inp" readonly>
                        </div>
                        <div class="calc-col" style="flex: 1.5;">
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
                    <div class="calc-row" style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <div class="calc-col">
                            <label class="calc-label">Estimated Delivery Date (EDD)</label>
                            <input type="text" id="edd-res" class="calc-inp" readonly style="color: #047857; background: #d1fae5; border-color: #6ee7b7;">
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
                    <div class="calc-row" style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 6px;">
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
                    <div class="calc-row" style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 6px;">
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

<script>
(function() {
    // --- Tab Switching Logic ---
    const tabs = document.querySelectorAll('.calc-tab-btn');
    const panes = document.querySelectorAll('.calc-pane');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            panes.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(this.getAttribute('data-target')).classList.add('active');
        });
    });

    // --- Helper ---
    const val = (id) => parseFloat(document.getElementById(id).value) || 0;
    const str = (id) => document.getElementById(id).value;
    const set = (id, v) => document.getElementById(id).value = v;

    function formatDate(d) {
        return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    }

    // --- 1. BMI ---
    function doBmi() {
        let kg = val('bmi-kg'), ft = val('bmi-ft'), inc = val('bmi-in');
        if(kg > 0 && (ft > 0 || inc > 0)) {
            let m = (ft * 12 + inc) * 0.0254;
            let bmi = kg / (m * m);
            set('bmi-res', bmi.toFixed(1));
            
            let cls = '';
            if(bmi < 18.5) cls = 'Underweight';
            else if(bmi < 25) cls = 'Normal';
            else if(bmi < 30) cls = 'Overweight';
            else cls = 'Obese';
            set('bmi-cls', cls);
            set('bmi-ideal', (22 * m * m).toFixed(1) + ' kg');
        } else {
            set('bmi-res', ''); set('bmi-cls', ''); set('bmi-ideal', '');
        }
    }
    document.querySelectorAll('.calc-trigger-bmi').forEach(el => el.addEventListener('input', doBmi));

    // --- 2. Insulin ---
    const bNum = ["০","১","২","৩","৪","৫","৬","৭","৮","৯"];
    const toBn = (num) => String(num).split('').map(c => bNum[c] || c).join('');
    
    function doInsulin() {
        let kg = val('ins-kg'), unit = val('ins-unit'), time = str('ins-time');
        if(kg > 0 && unit > 0) {
            let total = kg * unit;
            set('ins-total', total.toFixed(1));
            let doseStr = "";
            if(time === 'BD') {
                let m = Math.round(total * 0.66);
                let n = Math.round(total * 0.33);
                doseStr = `${toBn(m)} + ০ + ${toBn(n)} (±২)`;
            } else {
                let part = Math.round(total / 3);
                doseStr = `${toBn(part)} + ${toBn(part)} + ${toBn(part)} (±২)`;
            }
            set('ins-dose', doseStr);
        } else {
            set('ins-total', ''); set('ins-dose', '');
        }
    }
    document.querySelectorAll('.calc-trigger-ins').forEach(el => el.addEventListener('input', doInsulin));
    document.getElementById('ins-time').addEventListener('change', doInsulin);
    doInsulin(); // Initial calc

    // --- 3. Z-Score (Approximate) ---
    function doZscore() {
        let ageM = val('z-age');
        let wt = val('z-wt');
        let isMale = document.querySelector('input[name="z-gen"]:checked').value === 'M';
        
        if(ageM > 0 && ageM <= 60 && wt > 0) {
            // Simplified approximation for median
            let median = 0;
            if(ageM <= 12) median = (ageM + 9) / 2;
            else median = (ageM / 12) * 2 + 8;
            
            if(!isMale) median *= 0.95; // girls slightly lighter
            
            let diff = wt - median;
            let sdApprox = median * 0.12; // Approx 1 SD is 12% of median
            let z = diff / sdApprox;
            
            set('z-res', z > 0 ? '+' + z.toFixed(2) : z.toFixed(2));
            set('z-ideal', median.toFixed(1));
            set('z-diff', diff > 0 ? '+' + diff.toFixed(1) : diff.toFixed(1));
        } else {
            set('z-res', ''); set('z-ideal', ''); set('z-diff', '');
        }
    }
    document.querySelectorAll('.calc-trigger-z').forEach(el => el.addEventListener('input', doZscore));

    // --- 4. BMR ---
    function doBmr() {
        let kg = val('bmr-kg'), ft = val('bmr-ft'), inc = val('bmr-in'), age = val('bmr-age');
        let isMale = document.querySelector('input[name="bmr-gen"]:checked').value === 'M';
        let act = parseFloat(str('bmr-act')) || 1.2;
        
        if(kg > 0 && (ft > 0 || inc > 0) && age > 0) {
            let cm = (ft * 12 + inc) * 2.54;
            // Mifflin-St Jeor Equation
            let bmr = (10 * kg) + (6.25 * cm) - (5 * age) + (isMale ? 5 : -161);
            set('bmr-res', Math.round(bmr));
            set('bmr-tdee', Math.round(bmr * act));
        } else {
            set('bmr-res', ''); set('bmr-tdee', '');
        }
    }
    document.querySelectorAll('.calc-trigger-bmr').forEach(el => el.addEventListener('input', doBmr));
    document.getElementById('bmr-act').addEventListener('change', doBmr);

    // --- 5. eGFR (Cockcroft-Gault) ---
    function doEgfr() {
        let cr = val('egfr-cr'), kg = val('egfr-kg'), age = val('egfr-age');
        let isMale = document.querySelector('input[name="egfr-gen"]:checked').value === 'M';
        let unit = str('egfr-unit');
        
        if(cr > 0 && kg > 0 && age > 0) {
            if(unit === 'umol') cr = cr / 88.4; // convert umol/L to mg/dL
            
            let crcl = ((140 - age) * kg) / (72 * cr);
            if(!isMale) crcl *= 0.85;
            
            set('egfr-res', crcl.toFixed(1) + ' mL/min');
            
            let stage = '';
            if(crcl >= 90) stage = 'G1 (Normal)';
            else if(crcl >= 60) stage = 'G2 (Mild)';
            else if(crcl >= 45) stage = 'G3a (Mild-Mod)';
            else if(crcl >= 30) stage = 'G3b (Mod-Severe)';
            else if(crcl >= 15) stage = 'G4 (Severe)';
            else stage = 'G5 (Kidney Failure)';
            
            set('egfr-stage', stage);
        } else {
            set('egfr-res', ''); set('egfr-stage', '');
        }
    }
    document.querySelectorAll('.calc-trigger-egfr').forEach(el => el.addEventListener('input', doEgfr));
    document.getElementById('egfr-unit').addEventListener('change', doEgfr);

    // --- 6. EDD ---
    function doEdd() {
        let lmpStr = str('edd-lmp');
        if(lmpStr) {
            let lmp = new Date(lmpStr);
            if(!isNaN(lmp.getTime())) {
                let edd = new Date(lmp.getTime());
                edd.setDate(edd.getDate() + 280); // Naegele's rule
                
                set('edd-res', formatDate(edd));
                
                let today = new Date();
                let diffTime = Math.abs(today - lmp);
                let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                let weeks = Math.floor(diffDays / 7);
                let days = diffDays % 7;
                
                if(today < lmp) {
                    set('edd-ga', "LMP is in the future!");
                } else {
                    set('edd-ga', `${weeks} Weeks, ${days} Days`);
                }
            }
        } else {
            set('edd-res', ''); set('edd-ga', '');
        }
    }
    document.getElementById('edd-lmp').addEventListener('input', doEdd);

    // --- 7. TD Vaccine ---
    function doTd() {
        let d1Str = str('td-date-1');
        if(d1Str) {
            let d1 = new Date(d1Str);
            if(!isNaN(d1.getTime())) {
                let d2 = new Date(d1.getTime()); d2.setDate(d2.getDate() + 28); // +4 weeks
                let d3 = new Date(d2.getTime()); d3.setMonth(d3.getMonth() + 6); // +6 months
                let d4 = new Date(d3.getTime()); d4.setFullYear(d4.getFullYear() + 1); // +1 year
                let d5 = new Date(d4.getTime()); d5.setFullYear(d5.getFullYear() + 1); // +1 year

                set('td-date-2', formatDate(d2));
                set('td-date-3', formatDate(d3));
                set('td-date-4', formatDate(d4));
                set('td-date-5', formatDate(d5));
            }
        } else {
            set('td-date-2', ''); set('td-date-3', ''); set('td-date-4', ''); set('td-date-5', '');
        }
    }
    document.getElementById('td-date-1').addEventListener('input', doTd);

    // --- 8. Rabies Vaccine ---
    function doRabies() {
        let d0Str = str('rabies-date-0');
        if(d0Str) {
            let d0 = new Date(d0Str);
            if(!isNaN(d0.getTime())) {
                let d3 = new Date(d0.getTime()); d3.setDate(d3.getDate() + 3);
                let d7 = new Date(d0.getTime()); d7.setDate(d7.getDate() + 7);
                let d14 = new Date(d0.getTime()); d14.setDate(d14.getDate() + 14);
                let d28 = new Date(d0.getTime()); d28.setDate(d28.getDate() + 28);

                set('rabies-date-3', formatDate(d3));
                set('rabies-date-7', formatDate(d7));
                set('rabies-date-14', formatDate(d14));
                set('rabies-date-28', formatDate(d28));
            }
        } else {
            set('rabies-date-3', ''); set('rabies-date-7', ''); set('rabies-date-14', ''); set('rabies-date-28', '');
        }
    }
    document.getElementById('rabies-date-0').addEventListener('input', doRabies);

})();
</script>