// Clinical dermatological exam: primary/secondary lesion morphology, distribution mapping, and bedside signs (Auspitz/Nikolsky/Koebner).
(function() {
  'use strict';

  function initExamDermatology() {
    // Dermatology and lesion morphology handlers
    const dermaWrapper = document.getElementById('dermatology-wrapper');
    if (dermaWrapper) {
      const primarySelect = dermaWrapper.querySelector('[data-derma-field="primary-lesion"]');
      const secSelect = dermaWrapper.querySelector('[data-derma-field="secondary-lesion"]');
      const patternSelect = dermaWrapper.querySelector('[data-derma-field="pattern"]');
      const auspitzSelect = dermaWrapper.querySelector('[data-derma-field="auspitz"]');
      const nikolskySelect = dermaWrapper.querySelector('[data-derma-field="nikolsky"]');
      const koebnerSelect = dermaWrapper.querySelector('[data-derma-field="koebner"]');
      const pruritusSelect = dermaWrapper.querySelector('[data-derma-field="pruritus"]');
      const textarea = dermaWrapper.querySelector('#dermatology-findings');

      function syncDermaSummary() {
        if (!textarea) return;
        const sites = [];
        dermaWrapper.querySelectorAll('input[data-derma-site]:checked').forEach(cb => {
          sites.push(cb.getAttribute('data-derma-site'));
        });

        const prim = primarySelect?.value || 'Plaque';
        const sec = secSelect?.value || 'None';
        const pattern = patternSelect?.value || 'Disseminated';
        const auspitz = auspitzSelect?.value || 'Negative';
        const nikolsky = nikolskySelect?.value || 'Negative';
        const koebner = koebnerSelect?.value || 'Negative';
        const pruritus = pruritusSelect?.value || 'Non-pruritic';

        const lines = ['Dermatological Examination:'];
        const siteStr = sites.length > 0 ? `Involved Sites: ${sites.join(', ')}` : 'Site: Localized / Generalized';
        const secStr = sec !== 'None / Discrete' ? ` with secondary ${sec}` : '';
        lines.push(`• Morphology: ${prim}${secStr}; Pattern: ${pattern}.`);
        lines.push(`• Topographical Distribution: ${siteStr}.`);
        lines.push(`• Symptom: ${pruritus}.`);

        const signs = [];
        if (auspitz && !auspitz.includes('Negative') && !auspitz.includes('Not tested')) signs.push(`Auspitz: ${auspitz}`);
        if (nikolsky && !nikolsky.includes('Negative') && !nikolsky.includes('Not tested')) signs.push(`Nikolsky: ${nikolsky}`);
        if (koebner && !koebner.includes('Negative')) signs.push(`Koebner/Dermographism: ${koebner}`);
        if (signs.length > 0) {
          lines.push(`• Clinical Bedside Signs: ${signs.join('; ')}.`);
        }

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      dermaWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', syncDermaSummary);
      });
      dermaWrapper.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', syncDermaSummary);
      });

      // Quick presets
      dermaWrapper.querySelectorAll('[data-derma-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-derma-preset');
          dermaWrapper.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);

          if (p === 'psoriasis') {
            if (primarySelect) primarySelect.value = 'Plaque (>1 cm elevated plateau-like)';
            if (secSelect) secSelect.value = 'Scaling / Desquamation (Silvery/Fine/Greasy)';
            if (patternSelect) patternSelect.value = 'Extensor surfaces (Elbows/Knees/Scalp)';
            if (auspitzSelect) auspitzSelect.value = 'Positive (Pinpoint bleeding on scraping)';
            if (koebnerSelect) koebnerSelect.value = 'Koebner Phenomenon Positive (Lesions in lines of trauma)';
            if (pruritusSelect) pruritusSelect.value = 'Mild intermittent itching';
            dermaWrapper.querySelector('[data-derma-site="Scalp & Face"]')?.click();
            dermaWrapper.querySelector('[data-derma-site="Upper Limbs & Extensors"]')?.click();
            dermaWrapper.querySelector('[data-derma-site="Nails (Pitting/Onycholysis)"]')?.click();
          } else if (p === 'atopic-eczema') {
            if (primarySelect) primarySelect.value = 'Papule (<1 cm elevated solid lesion)';
            if (secSelect) secSelect.value = 'Lichenification (Thickened skin with exaggerated markings)';
            if (patternSelect) patternSelect.value = 'Symmetrical bilateral flexural';
            if (auspitzSelect) auspitzSelect.value = 'Negative';
            if (pruritusSelect) pruritusSelect.value = 'Severe intractable pruritus (Sleep disturbing)';
            dermaWrapper.querySelector('[data-derma-site="Flexural Creases"]')?.click();
            dermaWrapper.querySelector('[data-derma-site="Neck & Chest"]')?.click();
          } else if (p === 'tinea-corporis') {
            if (primarySelect) primarySelect.value = 'Plaque (>1 cm elevated plateau-like)';
            if (secSelect) secSelect.value = 'Scaling / Desquamation (Silvery/Fine/Greasy)';
            if (patternSelect) patternSelect.value = 'Annular / Ring-shaped (Active scaly border)';
            if (pruritusSelect) pruritusSelect.value = 'Moderate pruritus';
            dermaWrapper.querySelector('[data-derma-site="Back & Torso"]')?.click();
          } else if (p === 'herpes-zoster') {
            if (primarySelect) primarySelect.value = 'Vesicle (<1 cm fluid-filled blister)';
            if (secSelect) secSelect.value = 'Crusting (Dried honey-colored/serous exudate)';
            if (patternSelect) patternSelect.value = 'Dermatomal (Zosteriform, unilateral)';
            if (pruritusSelect) pruritusSelect.value = 'Moderate pruritus';
            dermaWrapper.querySelector('[data-derma-site="Back & Torso"]')?.click();
          } else if (p === 'urticaria') {
            if (primarySelect) primarySelect.value = 'Wheal / Hive (Evanescent edematous swelling)';
            if (secSelect) secSelect.value = 'None / Discrete';
            if (patternSelect) patternSelect.value = 'Disseminated / Scattered';
            if (koebnerSelect) koebnerSelect.value = 'Dermographism Positive (Wheal on firm stroke)';
            if (pruritusSelect) pruritusSelect.value = 'Severe intractable pruritus (Sleep disturbing)';
          } else if (p === 'clear-skin') {
            dermaWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }

          syncDermaSummary();
        });
      });

      const normalBtn = dermaWrapper.querySelector('[data-action="normal-derma"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          dermaWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          dermaWrapper.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
          if (textarea) {
            textarea.value = 'Dermatological Examination:\n• Skin & Mucosa: Clear, warm, intact epidermis. No active erythema, primary or secondary lesions.\n• Hair & Nails: Healthy sheen, normal nail plates without pitting or dystrophy.\n• Bedside Signs: Auspitz, Nikolsky, and Koebner negative. Non-pruritic.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      const clearBtn = dermaWrapper.querySelector('[data-action="clear-derma"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          dermaWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          dermaWrapper.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamDermatology);
  } else {
    initExamDermatology();
  }
})();
