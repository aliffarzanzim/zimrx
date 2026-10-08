// Pulmonology and respiratory examination module handling findings sync and presets.
(function() {
  'use strict';

  function initExamPulmonology() {
    // Respiratory examination handlers
    const pulmoWrapper = document.getElementById('pulmo-wrapper');
    if (pulmoWrapper) {
      const modeTabs = pulmoWrapper.querySelectorAll('.zrx-exam-mode-tab[data-pulmo-tab]');
      const panes = pulmoWrapper.querySelectorAll('.zrx-pulmo-pane');
      const textarea = document.getElementById('pulmo-findings');

      // Tab switching
      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-pulmo-tab');
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');

          panes.forEach(pane => {
            if (pane.id === `zrx-pulmo-pane-${target}`) {
              pane.removeAttribute('hidden');
              pane.classList.add('active');
            } else {
              pane.setAttribute('hidden', '');
              pane.classList.remove('active');
            }
          });
        });
      });

      function getPulmoVal(name) {
        return pulmoWrapper.querySelector(`[data-pulmo-field="${name}"]`)?.value || '';
      }

      function setPulmoVal(name, val) {
        const el = pulmoWrapper.querySelector(`[data-pulmo-field="${name}"]`);
        if (el) el.value = val;
      }

      function syncPulmoSummary() {
        if (!textarea) return;

        const breathSounds = getPulmoVal('breath-sounds');
        const wheeze = getPulmoVal('wheeze');
        const crackles = getPulmoVal('crackles');
        const vocalRes = getPulmoVal('vocal-resonance');
        const trachea = getPulmoVal('trachea');
        const chestShape = getPulmoVal('chest-shape');
        const percussion = getPulmoVal('percussion');
        const tvf = getPulmoVal('tvf');
        const spo2 = getPulmoVal('spo2-mode');
        const pefr = getPulmoVal('pefr');
        const guideline = getPulmoVal('guideline-stage');
        const curb65 = getPulmoVal('curb65');
        const sputum = getPulmoVal('sputum');

        const lines = ['Pulmonology & Respiratory Examination:'];
        lines.push(`• Inspection & Dynamics: ${trachea}; Shape/Expansion: ${chestShape}.`);
        lines.push(`• Percussion & Fremitus: Percussion: ${percussion}; TVF: ${tvf}.`);
        lines.push(`• Auscultation: Breath sounds: ${breathSounds}; Wheeze: ${wheeze}; Crackles: ${crackles}; Voice/Rub: ${vocalRes}.`);
        lines.push(`• Oxygenation & Spirometry: ${spo2}; ${pefr}; Sputum: ${sputum}.`);
        lines.push(`• Disease Staging: ${guideline}; ${curb65}.`);

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      pulmoWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', syncPulmoSummary);
      });

      // Quick presets
      pulmoWrapper.querySelectorAll('[data-pulmo-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-pulmo-preset');

          if (p === 'asthma') {
            setPulmoVal('breath-sounds', 'Vesicular with prolonged expiration (Airway obstruction - Asthma / COPD)');
            setPulmoVal('wheeze', 'Bilateral widespread polyphonic expiratory wheeze (Asthma / COPD)');
            setPulmoVal('crackles', 'No crackles or crepitations audible');
            setPulmoVal('pefr', 'PEFR 60–79% of predicted (Moderate airflow obstruction - Yellow Zone)');
            setPulmoVal('guideline-stage', 'GINA: Uncontrolled severe asthma exacerbation');
            setPulmoVal('spo2-mode', 'SpO2 92–95% on ambient Room Air (Mild hypoxemia)');
          } else if (p === 'copd') {
            setPulmoVal('chest-shape', 'Barrel-shaped chest (Increased AP diameter, horizontal ribs - COPD)');
            setPulmoVal('trachea', 'Reduced cricosternal distance (<2 fingerbreadths - Hyperinflation in COPD)');
            setPulmoVal('breath-sounds', 'Diminished vesicular breath sounds throughout (COPD / Emphysema)');
            setPulmoVal('wheeze', 'Bilateral widespread polyphonic expiratory wheeze (Asthma / COPD)');
            setPulmoVal('percussion', 'Hyper-resonant percussion note bilaterally (Emphysema / Hyperinflation)');
            setPulmoVal('guideline-stage', 'GOLD COPD: Group E (Exacerbation prone, high risk)');
            setPulmoVal('spo2-mode', 'SpO2 88–92% (Target range for hypercapnic COPD on controlled O2)');
          } else if (p === 'pneumonia') {
            setPulmoVal('breath-sounds', 'Bronchial breathing with tubular high pitch (Consolidation / Pneumonia)');
            setPulmoVal('crackles', 'Localized coarse crackles over Right lower lobe (Pneumonia)');
            setPulmoVal('percussion', 'Dull percussion note over localized lobe (Consolidation / Collapse)');
            setPulmoVal('vocal-resonance', 'Increased vocal resonance & Whispering Pectoriloquy positive (Consolidation)');
            setPulmoVal('tvf', 'Increased TVF over area of consolidation');
            setPulmoVal('sputum', 'Rust-colored sputum (Pneumococcal pneumonia)');
            setPulmoVal('curb65', 'CURB-65 Score 2 (Intermediate risk, consider short hospital admission)');
          } else if (p === 'effusion') {
            setPulmoVal('trachea', 'Trachea deviated to the Left (Right tension pneumothorax / Massive effusion)');
            setPulmoVal('breath-sounds', 'Diminished / Absent breath sounds at Right base (Effusion / Collapse)');
            setPulmoVal('percussion', 'Stony dull percussion note at Right lung base (Pleural effusion)');
            setPulmoVal('vocal-resonance', 'Decreased / Absent vocal resonance (Effusion / Pneumothorax)');
            setPulmoVal('tvf', 'Decreased / Absent TVF over effusion or pneumothorax');
          } else if (p === 'fibrosis') {
            setPulmoVal('crackles', "Fine end-inspiratory crackles ('Velcro' type - Interstitial lung disease / Fibrosis)");
            setPulmoVal('breath-sounds', 'Normal vesicular breath sounds bilaterally, no prolongation');
            setPulmoVal('wheeze', 'No wheeze or rhonchi audible');
            setPulmoVal('spo2-mode', 'SpO2 92–95% on ambient Room Air (Mild hypoxemia)');
          } else if (p === 'normal-pulmo') {
            pulmoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }

          syncPulmoSummary();
        });
      });

      const normalBtn = pulmoWrapper.querySelector('[data-action="normal-pulmo"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          pulmoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = 'Pulmonology & Respiratory Examination:\n• Inspection: Trachea central, normal cricosternal distance (>3 fingers). Chest symmetrical with normal respiratory excursion (>4 cm). No accessory muscle use or indrawing.\n• Percussion: Resonant percussion note throughout all symmetrical lung zones bilaterally. Tactile vocal fremitus normal and symmetrical.\n• Auscultation: Vesicular breath sounds bilaterally with normal expiration. No wheeze, crackles, or pleural friction rub. Vocal resonance normal bilaterally.\n• Metrics: SpO2 ≥96% on ambient room air. PEFR in green zone (≥80% of predicted). Non-productive cough.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      const clearBtn = pulmoWrapper.querySelector('[data-action="clear-pulmo"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          pulmoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }


  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamPulmonology);
  } else {
    initExamPulmonology();
  }
})();
