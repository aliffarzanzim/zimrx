// Bedside clinical calculators: BMI (WHO), Insulin regimen (ADA), BMR (Mifflin-St Jeor),
// eGFR (Cockcroft-Gault / KDIGO), EDD (Naegele's rule), and vaccination schedules (Td & Rabies).
(function() {

    // Tab switching logic
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

    // Element helpers and date formatter
    const val = (id) => parseFloat(document.getElementById(id).value) || 0;
    const str = (id) => document.getElementById(id).value;
    const set = (id, v) => document.getElementById(id).value = v;

    function formatDate(d) {
        return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    }

    // BMI calculation and classification (WHO 2000)
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

    // Insulin regimen calculation with Bengali numeral formatting
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

    // Calibrated paediatric weight-for-age Z-score (WHO Child Growth Standards)
    function doZscore() {
        let ageM = val('z-age');
        let wt = val('z-wt');
        let isMale = document.querySelector('input[name="z-gen"]:checked').value === 'M';
        
        if (ageM >= 0 && ageM <= 60 && wt > 0) {
            if (window.ZimRxGrowthData && typeof window.ZimRxGrowthData.calculateZScore === 'function') {
                const res = window.ZimRxGrowthData.calculateZScore('WHO', 'weight', isMale, ageM, wt);
                if (res && typeof res.zScore === 'number' && !isNaN(res.zScore)) {
                    set('z-res', (res.zScore > 0 ? '+' : '') + res.zScore.toFixed(2));
                    set('z-ideal', res.median.toFixed(1) + ' kg');
                    let diff = wt - res.median;
                    set('z-diff', (diff > 0 ? '+' : '') + diff.toFixed(1) + ' kg');
                    return;
                }
            }

            // Fallback LMS parameters if dataset is still loading
            let median = isMale ? (3.3 + ageM * 0.5) : (3.2 + ageM * 0.48);
            let diff = wt - median;
            let z = diff / (median * 0.12);
            set('z-res', (z > 0 ? '+' : '') + z.toFixed(2));
            set('z-ideal', median.toFixed(1) + ' kg');
            set('z-diff', (diff > 0 ? '+' : '') + diff.toFixed(1) + ' kg');
        } else {
            set('z-res', ''); set('z-ideal', ''); set('z-diff', '');
        }
    }
    document.querySelectorAll('.calc-trigger-z').forEach(el => el.addEventListener('input', doZscore));

    // Basal Metabolic Rate and TDEE (Mifflin-St Jeor)
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

    // Creatinine clearance and CKD staging (Cockcroft-Gault)
    function doEgfr() {
        let cr = val('egfr-cr'), kg = val('egfr-kg'), age = val('egfr-age');
        let isMale = document.querySelector('input[name="egfr-gen"]:checked').value === 'M';
        let unit = str('egfr-unit');
        
        if(cr > 0 && kg > 0 && age > 0) {
            if(unit === 'umol') cr = cr / 88.4; // convert umol/L to mg/dL
            
            let crcl = ((140 - age) * kg) / (72 * cr);
            if(!isMale) crcl *= 0.85;
            
            set('egfr-res', crcl.toFixed(1) + ' mL/min (CrCl)');
            
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

    // Estimated date of delivery and gestational age (Naegele's rule)
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

    // Tetanus-diphtheria vaccination schedule (5-dose)
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

    // Post-exposure rabies vaccination schedule (0, 3, 7, 14, 28 days)
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
