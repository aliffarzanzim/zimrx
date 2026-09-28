/**
 * ZimRx - Clinical Exam: Cardiology & Hemodynamic
 */
(function() {
  'use strict';

  function initExamCardiology() {
    // 13. Cardiology & Hemodynamic Assessment Handlers
    // =======================================================
    const cardioWrapper = document.getElementById('cardio-wrapper');
    if (cardioWrapper) {
      const modeTabs = cardioWrapper.querySelectorAll('.zrx-exam-mode-tab[data-cardio-tab]');
      const panes = cardioWrapper.querySelectorAll('.zrx-cardio-pane');
      const textarea = document.getElementById('cardio-findings');

      // Tab switching
      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-cardio-tab');
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');

          panes.forEach(pane => {
            if (pane.id === `zrx-cardio-pane-${target}`) {
              pane.removeAttribute('hidden');
              pane.classList.add('active');
            } else {
              pane.setAttribute('hidden', '');
              pane.classList.remove('active');
            }
          });
        });
      });

      function getCardioVal(name) {
        return cardioWrapper.querySelector(`[data-cardio-field="${name}"]`)?.value || '';
      }

      function setCardioVal(name, val) {
        const el = cardioWrapper.querySelector(`[data-cardio-field="${name}"]`);
        if (el) el.value = val;
      }

      function syncCardioSummary() {
        if (!textarea) return;

        const nyha = getCardioVal('nyha-class');
        const killip = getCardioVal('killip-class');
        const jvp = getCardioVal('jvp');
        const apex = getCardioVal('apex-beat');
        const sounds = getCardioVal('heart-sounds');
        const murmur = getCardioVal('murmur-desc');
        const murmurGrade = getCardioVal('murmur-grade');
        const murmurRad = getCardioVal('murmur-radiation');
        const pulses = getCardioVal('peripheral-pulses');
        const crt = getCardioVal('crt');
        const edema = getCardioVal('edema-grade');
        const sacral = getCardioVal('sacral-edema');
        const rhythm = getCardioVal('rhythm');
        const ecg = getCardioVal('ecg-summary');
        const chestPain = getCardioVal('chest-pain');

        const lines = ['Cardiology & Hemodynamic Assessment:'];
        lines.push(`• Hemodynamics & Venous: ${nyha}; ${killip}; JVP: ${jvp}; Apex Beat: ${apex}.`);
        const murmurStr = murmur !== 'No heart murmur audible in any precordial area' ? `; Murmur: ${murmur} (${murmurGrade}, Radiation: ${murmurRad})` : '; No murmurs audible';
        lines.push(`• Auscultation: Heart Sounds: ${sounds}${murmurStr}.`);
        lines.push(`• Perfusion & Edema: Pulses: ${pulses}; CRT: ${crt}; Peripheral Edema: ${edema}; Sacral: ${sacral}.`);
        lines.push(`• Rhythm & ECG: Rhythm: ${rhythm}; ECG: ${ecg}; Chest Pain: ${chestPain}.`);

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      cardioWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', syncCardioSummary);
      });

      // Quick presets
      cardioWrapper.querySelectorAll('[data-cardio-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-cardio-preset');

          if (p === 'stemi') {
            setCardioVal('chest-pain', 'Typical Angina Pectoris (Substernal crushing pain, radiates to left arm/jaw, exertional, relieved by GTN/rest)');
            setCardioVal('ecg-summary', 'ST-Elevation Myocardial Infarction (STEMI: Inferior II, III, aVF)');
            setCardioVal('killip-class', 'Killip Class I (No clinical signs of heart failure)');
            setCardioVal('rhythm', 'Normal regular sinus rhythm (60–100 bpm)');
          } else if (p === 'chf') {
            setCardioVal('nyha-class', 'NYHA Class III (Marked limitation, comfortable only at rest)');
            setCardioVal('jvp', 'Elevated JVP (4 cm above sternal angle)');
            setCardioVal('apex-beat', 'Displaced downwards and laterally to 6th ICS AAL (Left ventricular dilatation)');
            setCardioVal('heart-sounds', 'S3 ventricular gallop audible (Volume overload / LV failure)');
            setCardioVal('edema-grade', '3+ Pitting edema (Deep 6mm depression, lasts >1 minute, obvious swelling)');
            setCardioVal('killip-class', 'Killip Class II (Rales <50% lung base, S3 gallop)');
          } else if (p === 'as') {
            setCardioVal('murmur-desc', 'Aortic area harsh ejection systolic murmur (Aortic stenosis)');
            setCardioVal('murmur-grade', 'Grade 4/6 (Loud with palpable thrill)');
            setCardioVal('murmur-radiation', 'Radiates to Carotids bilaterally (AS)');
            setCardioVal('apex-beat', 'Heaving apex beat (Left ventricular pressure overload / AS / HTN)');
            setCardioVal('peripheral-pulses', 'Weak / Thready peripheral pulses (Low cardiac output / Hypovolemia)');
          } else if (p === 'mr') {
            setCardioVal('murmur-desc', 'Mitral area pansystolic murmur (Mitral regurgitation)');
            setCardioVal('murmur-grade', 'Grade 3/6 (Moderate, no thrill)');
            setCardioVal('murmur-radiation', 'Radiates to Left Axilla (MR)');
            setCardioVal('apex-beat', 'Hyperdynamic apex beat (Volume overload / AR / MR)');
          } else if (p === 'afib') {
            setCardioVal('rhythm', 'Irregularly irregular rhythm with pulse deficit (Atrial Fibrillation)');
            setCardioVal('ecg-summary', 'Atrial Fibrillation with rapid ventricular response (AF RVR)');
            setCardioVal('peripheral-pulses', 'All peripheral pulses palpable, symmetrical, normal volume (Radials, Femorals, DP, PT)');
          } else if (p === 'normal-cardio') {
            cardioWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }

          syncCardioSummary();
        });
      });

      const normalBtn = cardioWrapper.querySelector('[data-action="normal-cardio"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          cardioWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = 'Cardiology & Hemodynamic Assessment:\n• Precordium & Venous: JVP not elevated (<3 cm above sternal angle at 45°). Apex beat located at left 5th intercostal space midclavicular line, tapping non-displaced. No precordial heaves or thrills.\n• Auscultation: S1 and S2 audible, normal intensity. No murmurs, gallops, or friction rubs audible.\n• Vascular Perfusion: All peripheral pulses (radials, brachials, femorals, dorsalis pedis) palpable, symmetrical, and of normal volume. CRT <2 seconds. No peripheral or sacral edema.\n• Rhythm: Regular sinus rhythm, no anginal chest symptoms reported.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      const clearBtn = cardioWrapper.querySelector('[data-action="clear-cardio"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          cardioWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }

    // =======================================================
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamCardiology);
  } else {
    initExamCardiology();
  }
})();
