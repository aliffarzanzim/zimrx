// Clinical endocrinology exam: glycemic regulation, thyroid gland palpation/signs, and adrenal/metabolic stigmata.
(function() {
  'use strict';

  function initExamEndocrinology() {
    // Endocrinology and metabolic examination handlers
    const endoWrapper = document.getElementById('endo-wrapper');
    if (endoWrapper) {
      const modeTabs = endoWrapper.querySelectorAll('.zrx-exam-mode-tab[data-endo-tab]');
      const panes = endoWrapper.querySelectorAll('.zrx-endo-pane');
      const textarea = document.getElementById('endo-findings');

      // Tab switching
      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-endo-tab');
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');

          panes.forEach(pane => {
            if (pane.id === `zrx-endo-pane-${target}`) {
              pane.removeAttribute('hidden');
              pane.classList.add('active');
            } else {
              pane.setAttribute('hidden', '');
              pane.classList.remove('active');
            }
          });
        });
      });

      function getEndoVal(name) {
        return endoWrapper.querySelector(`[data-endo-field="${name}"]`)?.value || '';
      }

      function setEndoVal(name, val) {
        const el = endoWrapper.querySelector(`[data-endo-field="${name}"]`);
        if (el) el.value = val;
      }

      function syncEndoSummary() {
        if (!textarea) return;

        const dmType = getEndoVal('dm-type');
        const hba1c = getEndoVal('hba1c-status');
        const smbg = getEndoVal('smbg-profile');
        const hypo = getEndoVal('hypo-awareness');
        const regimen = getEndoVal('dm-regimen');
        const goitre = getEndoVal('goitre-grade');
        const thyroidConst = getEndoVal('thyroid-consistency');
        const pemberton = getEndoVal('pemberton-sign');
        const eyes = getEndoVal('thyroid-eyes');
        const peripheral = getEndoVal('thyroid-peripheral');
        const cushing = getEndoVal('cushing-signs');
        const adrenal = getEndoVal('adrenal-insuff');
        const insulinRes = getEndoVal('insulin-resistance');
        const pcos = getEndoVal('pcos-hirsutism');

        const lines = ['Endocrinology & Metabolic Assessment:'];
        if (dmType !== 'Non-diabetic / Normoglycemic' || regimen !== 'Diet and lifestyle modification alone') {
          lines.push(`• Glycemia & Diabetes: ${dmType}; Status: ${hba1c}; SMBG: ${smbg}; Hypo Risk: ${hypo}; Regimen: ${regimen}.`);
        } else {
          lines.push(`• Glycemia & Diabetes: Normoglycemic / Non-diabetic; HbA1c in optimal range; No hypoglycemia.`);
        }
        lines.push(`• Thyroid Gland & Signs: ${goitre}, ${thyroidConst}; ${pemberton}; Eye signs: ${eyes}; Status: ${peripheral}.`);
        lines.push(`• Adrenal & Metabolic Stigmata: Cushingoid: ${cushing}; Adrenal: ${adrenal}; Insulin Resistance: ${insulinRes}; Androgens: ${pcos}.`);

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      endoWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', syncEndoSummary);
      });

      // Quick presets
      endoWrapper.querySelectorAll('[data-endo-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-endo-preset');

          if (p === 't2dm-poor') {
            setEndoVal('dm-type', 'Type 2 Diabetes Mellitus (T2DM)');
            setEndoVal('hba1c-status', 'Suboptimal glycemic control (HbA1c 8.0–9.9%)');
            setEndoVal('smbg-profile', 'Post-prandial glycemic excursions (>11.1 mmol/L)');
            setEndoVal('hypo-awareness', 'No hypoglycemia, intact autonomic awareness (Gold Score 1)');
            setEndoVal('dm-regimen', 'Dual OAD therapy (Metformin + SGLT2i / DPP4i / SU)');
            setEndoVal('insulin-resistance', 'Acanthosis nigricans present over posterior neck & axillae');
          } else if (p === 'graves') {
            setEndoVal('goitre-grade', 'Grade 2 (Clearly visible goitre with neck in neutral position)');
            setEndoVal('thyroid-consistency', "Smooth, diffuse enlargement with audible vascular bruit (Graves' disease)");
            setEndoVal('pemberton-sign', 'Pemberton sign negative (No facial congestion on arm raising)');
            setEndoVal('thyroid-eyes', 'Bilateral proptosis / Exophthalmos present');
            setEndoVal('thyroid-peripheral', 'Hyperthyroid: Fine resting finger tremor, warm moist palms, tachycardia');
          } else if (p === 'hypothyroid') {
            setEndoVal('goitre-grade', 'Grade 1 (Palpable goitre moving with swallowing, not visible in normal neck)');
            setEndoVal('thyroid-consistency', 'Multinodular goitre (MNG - Multiple firm irregular nodules)');
            setEndoVal('thyroid-eyes', 'No exophthalmos, lid lag or retraction (Eyes normal)');
            setEndoVal('thyroid-peripheral', 'Hypothyroid: Dry coarse skin, periorbital puffiness, slow-relaxing ankle reflex (Hung-up jerk)');
          } else if (p === 'cushing') {
            setEndoVal('cushing-signs', 'Moon facies, plethoric cheeks, dorsocervical fat pad (Buffalo hump)');
            setEndoVal('insulin-resistance', 'Multiple acrochordons (Skin tags) in flexural areas');
            setEndoVal('dm-type', 'Prediabetes (Impaired Fasting Glucose / IGT)');
          } else if (p === 'pcos') {
            setEndoVal('pcos-hirsutism', 'Mild hirsutism (Modified Ferriman-Gallwey Score 8–15)');
            setEndoVal('insulin-resistance', 'Acanthosis nigricans present over posterior neck & axillae');
            setEndoVal('dm-type', 'Prediabetes (Impaired Fasting Glucose / IGT)');
          } else if (p === 'normal-endo') {
            endoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }

          syncEndoSummary();
        });
      });

      const normalBtn = endoWrapper.querySelector('[data-action="normal-endo"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          endoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = 'Endocrinology & Metabolic Assessment:\n• Glycemic Status: Non-diabetic, optimal glucose regulation, no symptoms or episodes of hypoglycemia.\n• Thyroid Examination: Thyroid gland non-palpable, normal soft consistency, non-tender, no audible bruit. Pemberton sign negative. No proptosis, lid lag, or orbitopathy signs. Bilateral ankle jerks brisk with normal relaxation phase.\n• Metabolic & Adrenal Features: No Cushingoid features, dorsocervical fat pad, or violaceous striae. Normal skin pigmentation, no acanthosis nigricans or cutaneous markers of insulin resistance. No hirsutism.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      const clearBtn = endoWrapper.querySelector('[data-action="clear-endo"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          endoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamEndocrinology);
  } else {
    initExamEndocrinology();
  }
})();
