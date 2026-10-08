// Clinical local examination: 9-region abdominal diagram, surgical lump inspection/palpation, and pelvic assessment (P/S & P/V).
(function() {
  'use strict';

  function initExamLocal() {
    // Local and 9-region abdominal examination handlers
    const leWrapper = document.getElementById('local-exam-wrapper');
    if (leWrapper) {
      const normalBtn = leWrapper.querySelector('[data-action="normal-local"]');
      const clearBtn = leWrapper.querySelector('[data-action="clear-local"]');
      const textarea = leWrapper.querySelector('#local-exam-findings');

      // Mode Switcher (General vs 9-Region Abdomen)
      const modeTabs = leWrapper.querySelectorAll('.zrx-exam-mode-tab');
      const paneGeneral = leWrapper.querySelector('#zrx-le-pane-general');
      const paneAbdo = leWrapper.querySelector('#zrx-le-pane-abdomen');

      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          const mode = this.getAttribute('data-le-mode');
          if (mode === 'abdomen') {
            paneGeneral.hidden = true;
            paneAbdo.hidden = false;
          } else {
            paneGeneral.hidden = false;
            paneAbdo.hidden = true;
          }
        });
      });

      // 9-Region Abdominal Diagram State & Handling
      const abdoRegions = {
        RH: { name: 'Right Hypochondrium (RH)', tender: false, rebound: false, guarding: false, mass: false, note: '' },
        EPI: { name: 'Epigastrium (Epi)', tender: false, rebound: false, guarding: false, mass: false, note: '' },
        LH: { name: 'Left Hypochondrium (LH)', tender: false, rebound: false, guarding: false, mass: false, note: '' },
        RL: { name: 'Right Lumbar (RL)', tender: false, rebound: false, guarding: false, mass: false, note: '' },
        UMB: { name: 'Umbilical Region (Umb)', tender: false, rebound: false, guarding: false, mass: false, note: '' },
        LL: { name: 'Left Lumbar (LL)', tender: false, rebound: false, guarding: false, mass: false, note: '' },
        RIF: { name: 'Right Iliac Fossa (RIF)', tender: false, rebound: false, guarding: false, mass: false, note: '' },
        HYPO: { name: 'Hypogastrium (Hypo)', tender: false, rebound: false, guarding: false, mass: false, note: '' },
        LIF: { name: 'Left Iliac Fossa (LIF)', tender: false, rebound: false, guarding: false, mass: false, note: '' }
      };
      let activeAbdoRegion = 'RIF';

      const badgeElem = leWrapper.querySelector('#zrx-abdo-active-badge');
      const nameElem = leWrapper.querySelector('#zrx-abdo-active-name');
      const tenderCheck = leWrapper.querySelector('#zrx-abdo-sign-tender');
      const reboundCheck = leWrapper.querySelector('#zrx-abdo-sign-rebound');
      const guardingCheck = leWrapper.querySelector('#zrx-abdo-sign-guarding');
      const massCheck = leWrapper.querySelector('#zrx-abdo-sign-mass');
      const noteInput = leWrapper.querySelector('#zrx-abdo-region-note');
      const distensionSelect = leWrapper.querySelector('#zrx-abdo-distension');
      const bowelSelect = leWrapper.querySelector('#zrx-abdo-bowel-sounds');

      function syncAbdoUI() {
        const r = abdoRegions[activeAbdoRegion];
        if (badgeElem) badgeElem.textContent = `Selected: ${activeAbdoRegion}`;
        if (nameElem) nameElem.textContent = r.name;
        if (tenderCheck) tenderCheck.checked = r.tender;
        if (reboundCheck) reboundCheck.checked = r.rebound;
        if (guardingCheck) guardingCheck.checked = r.guarding;
        if (massCheck) massCheck.checked = r.mass;
        if (noteInput) noteInput.value = r.note || '';

        // Update svg region colors
        leWrapper.querySelectorAll('.zrx-abdo-region').forEach(rect => {
          const code = rect.getAttribute('data-region');
          const data = abdoRegions[code];
          rect.classList.toggle('active', code === activeAbdoRegion);
          const hasAny = data && (data.tender || data.rebound || data.guarding || data.mass || data.note);
          rect.classList.toggle('has-findings', !!hasAny);
        });
      }

      function updateAbdoSummary() {
        if (!textarea) return;
        const findings = [];
        Object.keys(abdoRegions).forEach(code => {
          const r = abdoRegions[code];
          const signs = [];
          if (r.tender) signs.push('Tenderness (+)');
          if (r.rebound) signs.push('Rebound Tenderness (+)');
          if (r.guarding) signs.push('Guarding/Rigidity (+)');
          if (r.mass) signs.push('Palpable Mass');
          if (r.note) signs.push(r.note);
          if (signs.length > 0) {
            findings.push(`• ${code} (${r.name}): ${signs.join(', ')}`);
          }
        });

        const distText = distensionSelect ? distensionSelect.value : 'Soft, non-distended';
        const bowelText = bowelSelect ? bowelSelect.value : 'Bowel sounds present';

        let out = `Abdominal Examination (9-Region Assessment):\n• Inspection & General: ${distText}.\n• Auscultation: ${bowelText}.`;
        if (findings.length > 0) {
          out += `\n• Regional Signs:\n${findings.join('\n')}`;
        } else {
          out += '\n• Palpation: Soft, non-tender across all 9 quadrants. No organomegaly or palpable mass.';
        }

        textarea.value = out;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Click on region in SVG
      leWrapper.querySelectorAll('.zrx-abdo-region').forEach(rect => {
        rect.addEventListener('click', function() {
          activeAbdoRegion = this.getAttribute('data-region') || 'RIF';
          syncAbdoUI();
        });
      });

      // Sign check events
      if (tenderCheck) tenderCheck.addEventListener('change', () => { abdoRegions[activeAbdoRegion].tender = tenderCheck.checked; syncAbdoUI(); updateAbdoSummary(); });
      if (reboundCheck) reboundCheck.addEventListener('change', () => { abdoRegions[activeAbdoRegion].rebound = reboundCheck.checked; syncAbdoUI(); updateAbdoSummary(); });
      if (guardingCheck) guardingCheck.addEventListener('change', () => { abdoRegions[activeAbdoRegion].guarding = guardingCheck.checked; syncAbdoUI(); updateAbdoSummary(); });
      if (massCheck) massCheck.addEventListener('change', () => { abdoRegions[activeAbdoRegion].mass = massCheck.checked; syncAbdoUI(); updateAbdoSummary(); });
      if (noteInput) noteInput.addEventListener('input', () => { abdoRegions[activeAbdoRegion].note = noteInput.value.trim(); syncAbdoUI(); updateAbdoSummary(); });
      if (distensionSelect) distensionSelect.addEventListener('change', updateAbdoSummary);
      if (bowelSelect) bowelSelect.addEventListener('change', updateAbdoSummary);

      // Abdo preset buttons
      leWrapper.querySelectorAll('[data-abdo-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-abdo-preset');
          if (p === 'rif-appendicitis') {
            activeAbdoRegion = 'RIF';
            abdoRegions.RIF.tender = true;
            abdoRegions.RIF.rebound = true;
            abdoRegions.RIF.guarding = true;
            abdoRegions.RIF.note = "McBurney's point tenderness, Rovsing sign +ve";
            if (distensionSelect) distensionSelect.value = 'Distended / Tympanitic';
          } else if (p === 'rh-cholecystitis') {
            activeAbdoRegion = 'RH';
            abdoRegions.RH.tender = true;
            abdoRegions.RH.guarding = true;
            abdoRegions.RH.note = "Murphy's sign positive, tender subcostal palpation";
          } else if (p === 'epi-gastritis') {
            activeAbdoRegion = 'EPI';
            abdoRegions.EPI.tender = true;
            abdoRegions.EPI.note = "Epigastric tenderness on deep palpation, no rebound";
          } else if (p === 'normal-soft') {
            Object.keys(abdoRegions).forEach(k => {
              abdoRegions[k].tender = false;
              abdoRegions[k].rebound = false;
              abdoRegions[k].guarding = false;
              abdoRegions[k].mass = false;
              abdoRegions[k].note = '';
            });
            if (distensionSelect) distensionSelect.value = 'Soft, non-distended';
            if (bowelSelect) bowelSelect.value = 'Bowel sounds normal / present';
          }
          syncAbdoUI();
          updateAbdoSummary();
        });
      });

      // Normal Local Button
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
                    const LE_NORMAL_DEFAULTS = {
            'insp-skin':              'Normal',
            'insp-edge':              'Well-defined / Clear',
            'insp-impulse':           'None / Negative',
            'palp-temp':              'Normal temperature',
            'palp-tenderness':        'Non-tender',
            'palp-fluctuation':       'Non-fluctuant',
            'palp-transillumination': 'Opaque / Negative',
            'palp-reducibility':      'Non-reducible',
            'palp-fixity':            'Subcutaneous (Freely mobile)',
            'regional-nodes':         'Not enlarged / Not palpable',
            'vascular-bruit':         'Absent',
          };
          leWrapper.querySelectorAll('select').forEach(sel => {
            const field = sel.dataset.leField;
            if (field && field in LE_NORMAL_DEFAULTS) sel.value = LE_NORMAL_DEFAULTS[field];
          });

          const site = leWrapper.querySelector('[data-le-field="anatomical-site"]')?.value.trim();
          if (textarea) {
            const locText = site ? ` at ${site}` : '';
            textarea.value = `Local Examination${locText}: Smooth surface, well-defined margins, non-tender, normal local temperature. No signs of acute inflammation. Regional lymph nodes not enlarged.`;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      // Clear Button
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          leWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          leWrapper.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
          Object.keys(abdoRegions).forEach(k => {
            abdoRegions[k].tender = false;
            abdoRegions[k].rebound = false;
            abdoRegions[k].guarding = false;
            abdoRegions[k].mass = false;
            abdoRegions[k].note = '';
          });
          syncAbdoUI();
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      syncAbdoUI();
    }

    // Pelvic examination handlers (P/S and P/V)
    const ohToggleBtn = document.getElementById('oh-pv-toggle-btn');
    const ohSection = document.getElementById('oh-pelvic-section');
    if (ohToggleBtn && ohSection) {
      ohToggleBtn.addEventListener('click', () => {
        const isHidden = ohSection.hidden;
        ohSection.hidden = !isHidden;
        ohToggleBtn.classList.toggle('active', isHidden);
      });

      const psCervix = document.getElementById('oh-ps-cervix');
      const psOs = document.getElementById('oh-ps-os');
      const psDischarge = document.getElementById('oh-ps-discharge');
      const psWalls = document.getElementById('oh-ps-walls');
      const pvPos = document.getElementById('oh-pv-position');
      const pvSize = document.getElementById('oh-pv-size');
      const pvMob = document.getElementById('oh-pv-mobility');
      const pvTend = document.getElementById('oh-pv-tenderness');
      const pvCmt = document.getElementById('oh-pv-cmt');
      const pvAdnexa = document.getElementById('oh-pv-adnexa');
      const pelvicSummaryInput = document.getElementById('oh-pelvic-summary-input');

      function syncPelvicSummary() {
        if (!pelvicSummaryInput) return;
        const psStr = `P/S: Cervix ${psCervix?.value || 'healthy'}, Os ${psOs?.value || 'closed'}, Discharge: ${psDischarge?.value || 'none'}`;
        const pvStr = `P/V: Uterus ${pvPos?.value || 'AV'}, ${pvSize?.value || 'normal size'}, ${pvMob?.value || 'mobile'}, CMT ${pvCmt?.value?.includes('Present') ? '+ve' : '-ve'}, Fornices: ${pvAdnexa?.value || 'free'}`;
        pelvicSummaryInput.value = `${psStr}. ${pvStr}.`;
      }

      [psCervix, psOs, psDischarge, psWalls, pvPos, pvSize, pvMob, pvTend, pvCmt, pvAdnexa].forEach(el => {
        if (el) el.addEventListener('change', syncPelvicSummary);
      });

      const normalPelvicBtn = document.getElementById('oh-pelvic-normal-btn');
      if (normalPelvicBtn) {
        normalPelvicBtn.addEventListener('click', () => {
          if (psCervix) psCervix.value = 'Healthy / Smooth';
          if (psOs) psOs.value = 'Closed';
          if (psDischarge) psDischarge.value = 'None / Physiological';
          if (psWalls) psWalls.value = 'Healthy / Rugose';
          if (pvPos) pvPos.value = 'Anteverted (AV), Anteflexed';
          if (pvSize) pvSize.value = 'Normal size';
          if (pvMob) pvMob.value = 'Freely mobile';
          if (pvTend) pvTend.value = 'Non-tender';
          if (pvCmt) pvCmt.value = 'Absent (Negative)';
          if (pvAdnexa) pvAdnexa.value = 'Fornices free, bilateral adnexa clear and non-tender';
          syncPelvicSummary();
        });
      }

      const clearPelvicBtn = document.getElementById('oh-pelvic-clear-btn');
      if (clearPelvicBtn) {
        clearPelvicBtn.addEventListener('click', () => {
          [psCervix, psOs, psDischarge, psWalls, pvPos, pvSize, pvMob, pvTend, pvCmt, pvAdnexa].forEach(el => {
            if (el) el.selectedIndex = 0;
          });
          if (pelvicSummaryInput) pelvicSummaryInput.value = '';
        });
      }
    }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamLocal);
  } else {
    initExamLocal();
  }
})();
