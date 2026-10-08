// Clinical dental examination: FDI 2-digit odontogram charting, surface mapping (OMDBL), DMFT scoring, and treatment plans.
(function() {
  'use strict';

  function initExamDental() {
    // Dental chart and FDI odontogram handlers
    const dentalWrapper = document.getElementById('dental-chart-wrapper');
    if (dentalWrapper) {
      const toothState = {}; // { toothNum: { status, surfaces: [], plan } }
      let activeTooth = 46;

      const permanentUpper = dentalWrapper.querySelector('#zrx-arch-upper');
      const permanentLower = dentalWrapper.querySelector('#zrx-arch-lower');
      const decUpper = dentalWrapper.querySelector('#zrx-arch-upper-dec');
      const decLower = dentalWrapper.querySelector('#zrx-arch-lower-dec');

      const dmftVal = dentalWrapper.querySelector('#zrx-dmft-val');
      const activeBadge = dentalWrapper.querySelector('#zrx-dental-active-badge');
      const activeTitle = dentalWrapper.querySelector('#zrx-dental-active-title');
      const statusSelect = dentalWrapper.querySelector('#zrx-tooth-status');
      const planSelect = dentalWrapper.querySelector('#zrx-tooth-plan');
      const perioSelect = dentalWrapper.querySelector('#zrx-dental-perio');
      const calcSelect = dentalWrapper.querySelector('#zrx-dental-calculus');
      const textarea = dentalWrapper.querySelector('#dental-exam-findings');

      const surfaceBoxes = {
        O: dentalWrapper.querySelector('#zrx-surf-o'),
        M: dentalWrapper.querySelector('#zrx-surf-m'),
        D: dentalWrapper.querySelector('#zrx-surf-d'),
        B: dentalWrapper.querySelector('#zrx-surf-b'),
        L: dentalWrapper.querySelector('#zrx-surf-l')
      };

      // Dentition Mode Switcher
      dentalWrapper.querySelectorAll('.zrx-dental-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          dentalWrapper.querySelectorAll('.zrx-dental-type-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          const isDec = this.getAttribute('data-dentition') === 'deciduous';
          if (permanentUpper) permanentUpper.hidden = isDec;
          if (permanentLower) permanentLower.hidden = isDec;
          if (decUpper) decUpper.hidden = !isDec;
          if (decLower) decLower.hidden = !isDec;
          selectTooth(isDec ? 55 : 46);
        });
      });

      function getToothName(num) {
        const n = parseInt(num, 10);
        const q = Math.floor(n / 10);
        const t = n % 10;
        const qNames = {
          1: 'Upper Right', 2: 'Upper Left', 3: 'Lower Left', 4: 'Lower Right',
          5: 'Primary Upper Right', 6: 'Primary Upper Left', 7: 'Primary Lower Left', 8: 'Primary Lower Right'
        };
        const tNames = {
          1: 'Central Incisor', 2: 'Lateral Incisor', 3: 'Canine', 4: 'First Premolar',
          5: 'Second Premolar', 6: 'First Molar', 7: 'Second Molar', 8: 'Third Molar'
        };
        const decNames = { 1: 'Central Incisor', 2: 'Lateral Incisor', 3: 'Canine', 4: 'First Molar', 5: 'Second Molar' };
        const qName = qNames[q] || '';
        const name = q <= 4 ? tNames[t] : decNames[t];
        return `${qName} ${name || 'Tooth'}`;
      }

      function selectTooth(num) {
        activeTooth = parseInt(num, 10);
        if (activeBadge) activeBadge.textContent = `Tooth #${activeTooth}`;
        if (activeTitle) activeTitle.textContent = getToothName(activeTooth);

        const cur = toothState[activeTooth] || { status: 'Sound / Normal', surfaces: [], plan: 'None / Observation' };
        if (statusSelect) statusSelect.value = cur.status;
        if (planSelect) planSelect.value = cur.plan;

        Object.keys(surfaceBoxes).forEach(s => {
          if (surfaceBoxes[s]) surfaceBoxes[s].checked = cur.surfaces.includes(s);
        });

        dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(btn => {
          btn.classList.toggle('active', parseInt(btn.getAttribute('data-tooth'), 10) === activeTooth);
        });
      }

      function updateToothGraphic(num) {
        const btn = dentalWrapper.querySelector(`.zrx-tooth-btn[data-tooth="${num}"]`);
        if (!btn) return;
        const cur = toothState[num];
        btn.classList.remove('caries', 'filled', 'missing', 'rct', 'crown');
        if (!cur || cur.status.includes('Sound')) return;

        if (cur.status.includes('Caries') || cur.status.includes('Carious')) {
          btn.classList.add('caries');
        } else if (cur.status.includes('Restoration') || cur.status.includes('Filled')) {
          btn.classList.add('filled');
        } else if (cur.status.includes('Missing') || cur.status.includes('Extraction')) {
          btn.classList.add('missing');
        } else if (cur.status.includes('RCT') || cur.status.includes('Root Canal')) {
          btn.classList.add('rct');
        } else if (cur.status.includes('Crown')) {
          btn.classList.add('crown');
        }
      }

      function syncDentalSummary() {
        let d = 0, m = 0, f = 0;
        const findings = [];

        Object.keys(toothState).forEach(tNum => {
          const tooth = toothState[tNum];
          const st = tooth.status;
          if (st.includes('Caries') || st.includes('Carious')) d++;
          else if (st.includes('Missing') || st.includes('Extraction')) m++;
          else if (st.includes('Filled') || st.includes('Restoration') || st.includes('Crown')) f++;

          if (!st.includes('Sound')) {
            const surfStr = tooth.surfaces.length > 0 ? ` (${tooth.surfaces.join('')})` : '';
            const planStr = tooth.plan !== 'None / Observation' ? ` → Plan: ${tooth.plan}` : '';
            findings.push(`• Tooth #${tNum} (${getToothName(tNum)}): ${st}${surfStr}${planStr}`);
          }
        });

        if (dmftVal) {
          dmftVal.textContent = `D: ${d} | M: ${m} | F: ${f} (Total: ${d + m + f})`;
        }

        if (!textarea) return;
        const perio = perioSelect?.value || 'Healthy Gingiva (Normal)';
        const calc = calcSelect?.value || 'Negligible / Clear';

        const lines = [
          'Dental Examination & Odontogram:',
          `• DMFT Score: Decayed (D): ${d}, Missing (M): ${m}, Filled (F): ${f} [Total DMFT = ${d + m + f}]`,
          `• Periodontal Status: ${perio}; Calculus: ${calc}`
        ];

        if (findings.length > 0) {
          lines.push(`• Specific Tooth Pathologies & Planned Interventions:\n${findings.join('\n')}`);
        } else {
          lines.push('• Dentition: Intact, sound permanent dentition without visible cavitations or restorations.');
        }

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Tooth button click
      dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const num = this.getAttribute('data-tooth');
          selectTooth(num);
        });
      });

      // Inspector listeners
      if (statusSelect) {
        statusSelect.addEventListener('change', () => {
          if (!toothState[activeTooth]) toothState[activeTooth] = { status: 'Sound / Normal', surfaces: [], plan: 'None / Observation' };
          toothState[activeTooth].status = statusSelect.value;
          updateToothGraphic(activeTooth);
          syncDentalSummary();
        });
      }

      if (planSelect) {
        planSelect.addEventListener('change', () => {
          if (!toothState[activeTooth]) toothState[activeTooth] = { status: 'Sound / Normal', surfaces: [], plan: 'None / Observation' };
          toothState[activeTooth].plan = planSelect.value;
          syncDentalSummary();
        });
      }

      Object.keys(surfaceBoxes).forEach(s => {
        const box = surfaceBoxes[s];
        if (box) {
          box.addEventListener('change', () => {
            if (!toothState[activeTooth]) toothState[activeTooth] = { status: 'Sound / Normal', surfaces: [], plan: 'None / Observation' };
            const surfs = new Set(toothState[activeTooth].surfaces || []);
            if (box.checked) surfs.add(s);
            else surfs.delete(s);
            toothState[activeTooth].surfaces = Array.from(surfs);
            syncDentalSummary();
          });
        }
      });

      if (perioSelect) perioSelect.addEventListener('change', syncDentalSummary);
      if (calcSelect) calcSelect.addEventListener('change', syncDentalSummary);

      // Quick presets
      dentalWrapper.querySelectorAll('[data-dental-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-dental-preset');
          if (p === 'caries-46') {
            toothState[46] = { status: 'Dental Caries (Cavity)', surfaces: ['O'], plan: 'Composite Light-cure Restoration' };
            updateToothGraphic(46);
            selectTooth(46);
          } else if (p === 'rct-11') {
            toothState[11] = { status: 'Fractured Tooth', surfaces: ['M', 'D'], plan: 'Root Canal Treatment (RCT)' };
            updateToothGraphic(11);
            selectTooth(11);
          } else if (p === 'impaction-38') {
            toothState[38] = { status: 'Impaction (Partially Erupted)', surfaces: [], plan: 'Surgical Disimpaction' };
            updateToothGraphic(38);
            selectTooth(38);
          } else if (p === 'scaling-needed') {
            if (perioSelect) perioSelect.value = 'Marginal Gingivitis (BOP positive, erythematous)';
            if (calcSelect) calcSelect.value = 'Moderate calculus with subgingival deposits';
          } else if (p === 'sound-all') {
            Object.keys(toothState).forEach(k => delete toothState[k]);
            dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(b => b.classList.remove('caries', 'filled', 'missing', 'rct', 'crown'));
            if (perioSelect) perioSelect.selectedIndex = 0;
            if (calcSelect) calcSelect.selectedIndex = 3;
            selectTooth(46);
          }
          syncDentalSummary();
        });
      });

      const soundBtn = dentalWrapper.querySelector('[data-action="sound-dental"]');
      if (soundBtn) {
        soundBtn.addEventListener('click', () => {
          Object.keys(toothState).forEach(k => delete toothState[k]);
          dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(b => b.classList.remove('caries', 'filled', 'missing', 'rct', 'crown'));
          selectTooth(46);
          syncDentalSummary();
        });
      }

      const clearBtn = dentalWrapper.querySelector('[data-action="clear-dental"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          Object.keys(toothState).forEach(k => delete toothState[k]);
          dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(b => b.classList.remove('caries', 'filled', 'missing', 'rct', 'crown'));
          selectTooth(46);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      selectTooth(46);
    }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamDental);
  } else {
    initExamDental();
  }
})();
