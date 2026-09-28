/**
 * ZimRx - Clinical Exam: Diabetic Foot Assessment
 */
(function() {
  'use strict';

  function initExamDiabeticFoot() {
    // 8. Diabetic Foot Assessment Handlers
    const dfWrapper = document.getElementById('diabetic-foot-wrapper');
    if (dfWrapper) {
      const absentPoints = new Set();
      const wagnerDisplay = dfWrapper.querySelector('#zrx-df-wagner-val');
      const sensoryDisplay = dfWrapper.querySelector('#zrx-df-sensory-val');
      const riskDisplay = dfWrapper.querySelector('#zrx-df-risk-val');

      const wagnerSelect = dfWrapper.querySelector('#zrx-df-wagner-select');
      const dpPulseSelect = dfWrapper.querySelector('#zrx-df-dp-pulse');
      const ptPulseSelect = dfWrapper.querySelector('#zrx-df-pt-pulse');
      const crtSelect = dfWrapper.querySelector('#zrx-df-crt');
      const skinSelect = dfWrapper.querySelector('#zrx-df-skin');
      const deformSelect = dfWrapper.querySelector('#zrx-df-deformity');
      const textarea = dfWrapper.querySelector('#diabetic-foot-findings');

      function calculateFoot() {
        const intactCount = 10 - absentPoints.size;
        const hasLops = intactCount < 8; // Loss of protective sensation
        const wagner = wagnerSelect?.value || 'Grade 0: Intact skin';
        const dp = dpPulseSelect?.value || 'Palpable';
        const pt = ptPulseSelect?.value || 'Palpable';
        const hasPvd = dp.includes('Absent') || pt.includes('Absent') || dp.includes('Weak') && pt.includes('Weak');
        const deform = deformSelect?.value || 'None';
        const hasDeformity = !deform.includes('None');

        // Update displays
        if (wagnerDisplay) wagnerDisplay.textContent = wagner.split(':')[0];
        if (sensoryDisplay) {
          sensoryDisplay.textContent = `${intactCount} / 10 Intact ${hasLops ? '(LOPS +ve)' : '(Normal)'}`;
        }

        // IWGDF Risk Category
        let category = 'Category 0 (Very Low Risk - Annual Review)';
        let catClass = 'zrx-df-metric-status';

        if (!wagner.includes('Grade 0')) {
          category = 'Category 3 (Active Ulcer / Very High Risk)';
          catClass = 'zrx-df-metric-status high';
        } else if ((hasLops && hasPvd) || (hasLops && hasDeformity)) {
          category = 'Category 2 (Moderate-High Risk - Review q2-3mo)';
          catClass = 'zrx-df-metric-status moderate';
        } else if (hasLops || hasPvd) {
          category = 'Category 1 (Low-Moderate Risk - Review q3-6mo)';
          catClass = 'zrx-df-metric-status moderate';
        }

        if (riskDisplay) {
          riskDisplay.textContent = category;
          riskDisplay.className = catClass;
        }

        // Update SVG circles
        dfWrapper.querySelectorAll('.zrx-df-point').forEach(circle => {
          const ptId = parseInt(circle.getAttribute('data-point'), 10);
          const isAbsent = absentPoints.has(ptId);
          circle.classList.toggle('absent', isAbsent);
          circle.classList.toggle('intact', !isAbsent);
        });

        // Update textarea
        if (!textarea) return;
        const lines = [
          'Diabetic Foot Assessment & Stratification:',
          `• Wagner Staging: ${wagner}`,
          `• 10g Monofilament Sensation: ${intactCount}/10 sites intact ${hasLops ? '— Loss of Protective Sensation (LOPS) Present' : '— Protective Sensation Intact'}`,
          `• Vascular Pulses: ${dp}; ${pt}; CRT: ${crtSelect?.value || '<2s'}`,
          `• Foot Biomechanics & Skin: ${deform}; Skin: ${skinSelect?.value || 'Warm'}`,
          `• IWGDF Stratification: ${category}`
        ];

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Circle click
      dfWrapper.querySelectorAll('.zrx-df-point').forEach(circle => {
        circle.addEventListener('click', function() {
          const ptId = parseInt(this.getAttribute('data-point'), 10);
          if (absentPoints.has(ptId)) {
            absentPoints.delete(ptId);
          } else {
            absentPoints.add(ptId);
          }
          calculateFoot();
        });
      });

      // Selects listeners
      [wagnerSelect, dpPulseSelect, ptPulseSelect, crtSelect, skinSelect, deformSelect].forEach(el => {
        if (el) el.addEventListener('change', calculateFoot);
      });

      // Quick presets
      dfWrapper.querySelectorAll('[data-df-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-df-preset');
          absentPoints.clear();
          if (p === 'grade1-ulcer') {
            if (wagnerSelect) wagnerSelect.value = 'Grade 1: Superficial ulcer (not involving tendon/capsule/bone)';
            absentPoints.add(4); // 1st MT head
            absentPoints.add(5); // 3rd MT head
          } else if (p === 'grade2-deep') {
            if (wagnerSelect) wagnerSelect.value = 'Grade 2: Deep ulcer penetrating to tendon, bone or joint capsule';
            [1, 4, 5, 7, 9].forEach(pt => absentPoints.add(pt));
            if (skinSelect) skinSelect.value = 'Dry, anhidrotic with fissures (Autonomic)';
          } else if (p === 'charcot') {
            if (deformSelect) deformSelect.value = 'Charcot neuroarthropathy (Rocker bottom foot)';
            [1, 2, 3, 4, 5, 6, 7, 8].forEach(pt => absentPoints.add(pt));
          } else if (p === 'pvd-diminished') {
            if (dpPulseSelect) dpPulseSelect.value = 'Dorsalis Pedis: Absent (0)';
            if (ptPulseSelect) ptPulseSelect.value = 'Posterior Tibial: Absent (0)';
            if (crtSelect) crtSelect.value = 'CRT > 2 seconds (Delayed / PVD)';
            if (skinSelect) skinSelect.value = 'Cold, pale / cyanotic on elevation';
          } else if (p === 'normal-foot') {
            if (wagnerSelect) wagnerSelect.selectedIndex = 0;
            if (dpPulseSelect) dpPulseSelect.selectedIndex = 0;
            if (ptPulseSelect) ptPulseSelect.selectedIndex = 0;
            if (crtSelect) crtSelect.selectedIndex = 0;
            if (skinSelect) skinSelect.selectedIndex = 0;
            if (deformSelect) deformSelect.selectedIndex = 0;
          }
          calculateFoot();
        });
      });

      const normalBtn = dfWrapper.querySelector('[data-action="normal-foot"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          absentPoints.clear();
          dfWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          calculateFoot();
        });
      }

      const clearBtn = dfWrapper.querySelector('[data-action="clear-foot"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          absentPoints.clear();
          dfWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          calculateFoot();
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      calculateFoot();
    }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamDiabeticFoot);
  } else {
    initExamDiabeticFoot();
  }
})();
