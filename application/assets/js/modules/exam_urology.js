/**
 * ZimRx - Clinical Exam: Urology & Nephrology
 */
(function() {
  'use strict';

  function initExamUrology() {
    // 11. Urology & Nephrology Examination Handlers
    // =======================================================
    const uroWrapper = document.getElementById('urology-wrapper');
    if (uroWrapper) {
      const modeTabs = uroWrapper.querySelectorAll('.zrx-exam-mode-tab[data-uro-tab]');
      const panes = uroWrapper.querySelectorAll('.zrx-uro-pane');
      const textarea = document.getElementById('urology-findings');

      // Tab switching
      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-uro-tab');
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');

          panes.forEach(pane => {
            if (pane.id === `zrx-uro-pane-${target}`) {
              pane.removeAttribute('hidden');
              pane.classList.add('active');
            } else {
              pane.setAttribute('hidden', '');
              pane.classList.remove('active');
            }
          });
        });
      });

      function getUroVal(name) {
        return uroWrapper.querySelector(`[data-uro-field="${name}"]`)?.value || '';
      }

      function setUroVal(name, val) {
        const el = uroWrapper.querySelector(`[data-uro-field="${name}"]`);
        if (el) el.value = val;
      }

      function syncUroSummary() {
        if (!textarea) return;

        const rtKidney = getUroVal('rt-kidney');
        const ltKidney = getUroVal('lt-kidney');
        const renalAngle = getUroVal('renal-angle');
        const bladder = getUroVal('bladder-palp');
        const ureteric = getUroVal('ureteric-points');
        const dreTone = getUroVal('dre-tone');
        const dreGlove = getUroVal('dre-glove');
        const prostateGrade = getUroVal('prostate-grade');
        const prostateConst = getUroVal('prostate-consistency');
        const medianSulcus = getUroVal('median-sulcus');
        const lutsVoid = getUroVal('luts-voiding');
        const lutsStore = getUroVal('luts-storage');
        const ipss = getUroVal('ipss-category');
        const urine = getUroVal('urine-char');
        const testes = getUroVal('testes-exam');
        const prepuce = getUroVal('penis-prepuce');
        const meatus = getUroVal('urethral-meatus');

        const lines = ['Urology & Nephrology Examination:'];
        lines.push(`• Kidneys & Bladder: Rt Kidney: ${rtKidney}; Lt Kidney: ${ltKidney}; Punch: ${renalAngle}; Bladder: ${bladder}; Ureteric: ${ureteric}.`);
        lines.push(`• DRE & Prostate: Sphincter: ${dreTone}; Prostate: ${prostateGrade}, ${prostateConst}, ${medianSulcus}; Glove: ${dreGlove}.`);
        lines.push(`• LUTS & Urination: Voiding: ${lutsVoid}; Storage: ${lutsStore}; IPSS: ${ipss}; Urine: ${urine}.`);
        lines.push(`• Genitalia: Testes/Scrotum: ${testes}; Penis/Prepuce: ${prepuce}, Meatus: ${meatus}.`);

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      uroWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', syncUroSummary);
      });

      // Quick presets
      uroWrapper.querySelectorAll('[data-uro-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-uro-preset');

          if (p === 'bph') {
            setUroVal('prostate-grade', 'Grade II enlargement (~30-50g, 2 fingerbreadths)');
            setUroVal('prostate-consistency', 'Smooth, firm, elastic (BPH)');
            setUroVal('median-sulcus', 'Median sulcus shallow');
            setUroVal('luts-voiding', 'Hesitancy & intermittency of stream');
            setUroVal('luts-storage', 'Nocturia (1 to 2 times/night)');
            setUroVal('ipss-category', 'Moderate symptoms (IPSS Score 8–19)');
            setUroVal('bladder-palp', 'Not palpable or percussible (Empty bladder)');
          } else if (p === 'renal-colic') {
            setUroVal('renal-angle', 'Right renal angle tenderness positive (Pyelonephritis / Calculus)');
            setUroVal('ureteric-points', 'Right upper ureteric point tender (PUJ / Upper calculus)');
            setUroVal('urine-char', 'Tea-colored / Concentrated urine');
          } else if (p === 'pyelonephritis') {
            setUroVal('renal-angle', 'Right renal angle tenderness positive (Pyelonephritis / Calculus)');
            setUroVal('bladder-palp', 'Suprapubic tenderness without frank distension (Cystitis)');
            setUroVal('luts-storage', 'Dysuria / Burning micturition (Active UTI)');
            setUroVal('urine-char', 'Turbid / Cloudy pyuria (Urinary tract infection)');
          } else if (p === 'retention') {
            setUroVal('bladder-palp', 'Palpable up to umbilicus, tense, exquisitely tender (Acute retention)');
            setUroVal('luts-voiding', 'Severe urinary retention (Unable to void)');
            setUroVal('prostate-grade', 'Grade II enlargement (~30-50g, 2 fingerbreadths)');
          } else if (p === 'prostatitis') {
            setUroVal('prostate-consistency', 'Soft, boggy, exquisitely tender (Prostatitis)');
            setUroVal('dre-tone', 'Hypertonic / Spastic sphincter with tenderness');
            setUroVal('luts-storage', 'Dysuria / Burning micturition (Active UTI)');
          } else if (p === 'normal-uro') {
            uroWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }

          syncUroSummary();
        });
      });

      const normalBtn = uroWrapper.querySelector('[data-action="normal-uro"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          uroWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = 'Urology & Nephrology Examination:\n• Renal & Bladder: Kidneys non-palpable, non-tender bimanually. Bilateral renal angle punch tenderness negative. Bladder not palpable or percussible.\n• DRE & Prostate: Normal anal sphincter tone. Prostate non-enlarged (~20g), smooth, firm, elastic, median sulcus well-preserved and non-tender.\n• LUTS & Stream: No obstructive or irritative urinary symptoms. Clear amber urine.\n• External Genitalia: Normal bilateral descended testes, non-tender. Normal prepuce and urethral meatus without discharge.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      const clearBtn = uroWrapper.querySelector('[data-action="clear-uro"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          uroWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
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
    document.addEventListener('DOMContentLoaded', initExamUrology);
  } else {
    initExamUrology();
  }
})();
