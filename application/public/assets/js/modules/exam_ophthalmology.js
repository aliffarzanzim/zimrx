// Clinical ophthalmology exam: visual acuity (DVA/NVA), slit-lamp anterior segment, IOP tonometry, and fundoscopy.
(function() {
  'use strict';

  function initExamOphthalmology() {
    // Ophthalmology examination handlers
    const ophWrapper = document.getElementById('ophthalmology-wrapper');
    if (ophWrapper) {
      const normalBtn = ophWrapper.querySelector('[data-action="normal-eye"]');
      const clearBtn = ophWrapper.querySelector('[data-action="clear-eye"]');
      const textarea = ophWrapper.querySelector('#ophthalmology-findings');

      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
                    const NORMAL_EYE_DEFAULTS = {
            'dva':        '6/6',
            'nva':        'N6',
            'lids':       'Normal',
            'conjunctiva':'Normal / Clear',
            'cornea':     'Clear',
            'ac':         'Normal depth, quiet',
            'pupil':      'Round Regular Reactive (RRR)',
            'lens':       'Clear',
            'disc':       'Normal pink, distinct margin',
            'cdr':        'CDR 0.3',
            'macula':     'Normal foveal reflex',
            'retina':     'Normal background',
          };
          ophWrapper.querySelectorAll('select').forEach(sel => {
            const suffix = sel.dataset.ophField?.split('-').pop();
            if (suffix && suffix in NORMAL_EYE_DEFAULTS) sel.value = NORMAL_EYE_DEFAULTS[suffix];
          });

          const odIop = ophWrapper.querySelector('[data-oph-field="od-iop"]');
          const osIop = ophWrapper.querySelector('[data-oph-field="os-iop"]');
          if (odIop) odIop.value = '14';
          if (osIop) osIop.value = '14';

          if (textarea) {
            textarea.value = 'Visual Acuity: DVA 6/6 OU, NVA N6 OU. IOP: OD 14 mmHg, OS 14 mmHg. Anterior segments quiet, corneas clear, pupils active and reactive (RRR OU), crystalline lenses clear. Fundus: Healthy pink optic discs with CDR 0.3 OU, normal foveal reflex, normal retinal vasculature.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          ophWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          ophWrapper.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamOphthalmology);
  } else {
    initExamOphthalmology();
  }
})();
