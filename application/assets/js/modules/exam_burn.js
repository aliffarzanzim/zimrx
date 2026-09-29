// Clinical burn assessment: Wallace Rule of Nines SVG mapping, TBSA estimation, and Parkland resuscitation calculation.
(function() {
  'use strict';

  function initExamBurn() {
    // Burn assessment and Parkland resuscitation handlers
    const burnWrapper = document.getElementById('burn-assessment-wrapper');
    if (burnWrapper) {
      const activeSegments = new Set();
      const tbsaDisplay = burnWrapper.querySelector('#zrx-burn-tbsa-display');
      const classDisplay = burnWrapper.querySelector('#zrx-burn-class-display');
      const fluidDisplay = burnWrapper.querySelector('#zrx-burn-fluid-display');
      const fluid8h = burnWrapper.querySelector('#zrx-burn-fluid-8h');
      const rate8h = burnWrapper.querySelector('#zrx-burn-rate-8h');
      const fluid16h = burnWrapper.querySelector('#zrx-burn-fluid-16h');
      const rate16h = burnWrapper.querySelector('#zrx-burn-rate-16h');
      const uopTarget = burnWrapper.querySelector('#zrx-burn-uop-target');

      const weightInput = burnWrapper.querySelector('#zrx-burn-weight');
      const depthSelect = burnWrapper.querySelector('#zrx-burn-depth');
      const inhalCheck = burnWrapper.querySelector('#zrx-burn-inhalation');
      const circumCheck = burnWrapper.querySelector('#zrx-burn-circumferential');
      const elecCheck = burnWrapper.querySelector('#zrx-burn-electrical');
      const escharCheck = burnWrapper.querySelector('#zrx-burn-escharotomy');
      const textarea = burnWrapper.querySelector('#burn-exam-findings');

      const minorBtn = burnWrapper.querySelector('[data-action="minor-burn"]');
      const clearBtn = burnWrapper.querySelector('[data-action="clear-burn"]');

      function calculateBurn() {
        let tbsa = 0;
        const segmentNames = [];

        burnWrapper.querySelectorAll('.zrx-burn-segment').forEach(seg => {
          const segKey = seg.getAttribute('data-segment');
          if (activeSegments.has(segKey)) {
            seg.classList.add('active');
            const pct = parseFloat(seg.getAttribute('data-pct')) || 0;
            tbsa += pct;
            segmentNames.push(`${seg.getAttribute('data-name')} (${pct}%)`);
          } else {
            seg.classList.remove('active');
          }
        });

        tbsa = Math.min(100, Math.round(tbsa * 10) / 10);
        const weight = parseFloat(weightInput?.value) || 65;
        const depth = depthSelect?.value || '2nd Degree';
        const isFirstDegOnly = depth.includes('1st Degree');

        // Update TBSA display
        if (tbsaDisplay) tbsaDisplay.textContent = `${tbsa.toFixed(1)}%`;

        // Classification
        let classification = 'No Burn Mapped';
        if (tbsa > 0) {
          if (tbsa < 10 && !inhalCheck?.checked) {
            classification = 'Minor Burn (<10% TBSA)';
            if (classDisplay) classDisplay.className = 'zrx-burn-metric-status';
          } else if (tbsa < 20 && !inhalCheck?.checked) {
            classification = 'Moderate Burn (10-20% TBSA)';
            if (classDisplay) classDisplay.className = 'zrx-burn-metric-status moderate';
          } else {
            classification = 'Major / Severe Burn (>20% or High Risk)';
            if (classDisplay) classDisplay.className = 'zrx-burn-metric-status major';
          }
        }
        if (classDisplay) classDisplay.textContent = classification;

        // Parkland Fluid Resuscitation: 4 mL x weight (kg) x %TBSA (if 2nd/3rd degree)
        let totalFluid = 0;
        if (!isFirstDegOnly && tbsa > 0) {
          totalFluid = Math.round(4 * weight * tbsa);
        }

        const halfFluid = Math.round(totalFluid / 2);
        const hourly8h = Math.round((halfFluid / 8) * 10) / 10;
        const hourly16h = Math.round((halfFluid / 16) * 10) / 10;
        const uopMin = Math.round(weight * 0.5 * 10) / 10;
        const uopMax = Math.round(weight * 1.0 * 10) / 10;

        if (fluidDisplay) fluidDisplay.textContent = `${totalFluid.toLocaleString()} mL (RL)`;
        if (fluid8h) fluid8h.textContent = `${halfFluid.toLocaleString()} mL`;
        if (rate8h) rate8h.textContent = `(${hourly8h} mL/hr)`;
        if (fluid16h) fluid16h.textContent = `${halfFluid.toLocaleString()} mL`;
        if (rate16h) rate16h.textContent = `(${hourly16h} mL/hr)`;
        if (uopTarget) uopTarget.textContent = `${uopMin} – ${uopMax} mL/hr`;

        // Update findings textarea
        if (!textarea) return;
        if (tbsa === 0) {
          return;
        }

        const lines = [
          `Burn Assessment (Wallace Rule of Nines):`,
          `• Total Body Surface Area (% TBSA): ${tbsa.toFixed(1)}% (${classification})`,
          `• Burn Depth: ${depth}`,
          `• Involved Areas: ${segmentNames.join(', ')}`
        ];

        const risks = [];
        if (inhalCheck?.checked) risks.push('Inhalation injury suspected (close airway monitoring / early intubation threshold)');
        if (circumCheck?.checked) risks.push('Circumferential burn (monitor for distal neurovascular compromise)');
        if (elecCheck?.checked) risks.push('High-voltage/chemical etiology (monitor ECG & rhabdomyolysis)');
        if (escharCheck?.checked) risks.push('Urgent bedside escharotomy indicated');
        if (risks.length > 0) {
          lines.push(`• Risk Factors / Alerts: ${risks.join('; ')}`);
        }

        if (totalFluid > 0) {
          lines.push(`• Fluid Resuscitation (Parkland Formula @ 4 mL/kg/%TBSA, Wt ${weight} kg):`);
          lines.push(`  - Total 24h Ringer's Lactate: ${totalFluid.toLocaleString()} mL`);
          lines.push(`  - 1st 8h from burn: ${halfFluid.toLocaleString()} mL at ${hourly8h} mL/hr`);
          lines.push(`  - Next 16h: ${halfFluid.toLocaleString()} mL at ${hourly16h} mL/hr`);
          lines.push(`  - Target Urine Output: ${uopMin} - ${uopMax} mL/hr (titrate fluid rate to urine output)`);
        } else if (isFirstDegOnly) {
          lines.push(`• Fluid Note: 1st-degree superficial erythema does not require Parkland fluid resuscitation.`);
        }

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Click on silhouette segments
      burnWrapper.querySelectorAll('.zrx-burn-segment').forEach(seg => {
        seg.addEventListener('click', function() {
          const segKey = this.getAttribute('data-segment');
          if (activeSegments.has(segKey)) {
            activeSegments.delete(segKey);
          } else {
            activeSegments.add(segKey);
          }
          calculateBurn();
        });
      });

      // Inputs change listeners
      [weightInput, depthSelect, inhalCheck, circumCheck, elecCheck, escharCheck].forEach(el => {
        if (el) el.addEventListener('input', calculateBurn);
        if (el) el.addEventListener('change', calculateBurn);
      });

      // Quick scenarios
      burnWrapper.querySelectorAll('[data-burn-scenario]').forEach(btn => {
        btn.addEventListener('click', function() {
          const sc = this.getAttribute('data-burn-scenario');
          activeSegments.clear();
          if (sc === 'scald-arm') {
            activeSegments.add('ant_arm_rt');
            activeSegments.add('post_arm_rt');
          } else if (sc === 'flame-torso') {
            activeSegments.add('ant_chest');
            activeSegments.add('ant_abdo');
          } else if (sc === 'major-burn') {
            activeSegments.add('ant_chest');
            activeSegments.add('ant_abdo');
            activeSegments.add('post_back_upper');
            activeSegments.add('ant_arm_rt');
            activeSegments.add('ant_arm_lt');
          } else if (sc === 'bilateral-legs') {
            activeSegments.add('ant_leg_rt');
            activeSegments.add('ant_leg_lt');
            activeSegments.add('post_leg_rt');
            activeSegments.add('post_leg_lt');
          }
          calculateBurn();
        });
      });

      if (minorBtn) {
        minorBtn.addEventListener('click', () => {
          activeSegments.clear();
          activeSegments.add('ant_arm_rt');
          if (depthSelect) depthSelect.value = '2nd Degree (Superficial Partial Thickness)';
          calculateBurn();
        });
      }

      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          activeSegments.clear();
          burnWrapper.querySelectorAll('.zrx-burn-segment').forEach(seg => seg.classList.remove('active'));
          burnWrapper.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
          if (tbsaDisplay) tbsaDisplay.textContent = '0.0%';
          if (classDisplay) {
            classDisplay.textContent = 'No Burn Mapped';
            classDisplay.className = 'zrx-burn-metric-status';
          }
          if (fluidDisplay) fluidDisplay.textContent = '0 mL (RL)';
          if (fluid8h) fluid8h.textContent = '0 mL';
          if (rate8h) rate8h.textContent = '(0 mL/hr)';
          if (fluid16h) fluid16h.textContent = '0 mL';
          if (rate16h) rate16h.textContent = '(0 mL/hr)';
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamBurn);
  } else {
    initExamBurn();
  }
})();
