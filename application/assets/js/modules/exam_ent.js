/**
 * ZimRx - Clinical Exam: ENT Examination
 */
(function() {
  'use strict';

  function initExamEnt() {
    // 6. ENT Examination Module Handlers
    const entWrapper = document.getElementById('ent-exam-wrapper');
    if (entWrapper) {
      const modeTabs = entWrapper.querySelectorAll('.zrx-exam-mode-tab');
      const paneEar = entWrapper.querySelector('#zrx-ent-pane-ear');
      const paneNose = entWrapper.querySelector('#zrx-ent-pane-nose');
      const paneThroat = entWrapper.querySelector('#zrx-ent-pane-throat');
      const textarea = entWrapper.querySelector('#ent-exam-findings');

      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          const t = this.getAttribute('data-ent-tab');
          if (paneEar) paneEar.hidden = (t !== 'ear');
          if (paneNose) paneNose.hidden = (t !== 'nose');
          if (paneThroat) paneThroat.hidden = (t !== 'throat');
        });
      });

      function updateEntSummary() {
        if (!textarea) return;
        const adCanal = entWrapper.querySelector('[data-ent-field="ad-canal"]')?.value;
        const adTm = entWrapper.querySelector('[data-ent-field="ad-tm"]')?.value;
        const adRinne = entWrapper.querySelector('[data-ent-field="ad-rinne"]')?.value;
        const asCanal = entWrapper.querySelector('[data-ent-field="as-canal"]')?.value;
        const asTm = entWrapper.querySelector('[data-ent-field="as-tm"]')?.value;
        const asRinne = entWrapper.querySelector('[data-ent-field="as-rinne"]')?.value;
        const weber = entWrapper.querySelector('[data-ent-field="weber"]')?.value;
        const mastoid = entWrapper.querySelector('[data-ent-field="mastoid-tenderness"]')?.value;

        const septum = entWrapper.querySelector('[data-ent-field="nasal-septum"]')?.value;
        const turbinates = entWrapper.querySelector('[data-ent-field="turbinates"]')?.value;
        const mucosa = entWrapper.querySelector('[data-ent-field="nasal-mucosa"]')?.value;
        const polyps = entWrapper.querySelector('[data-ent-field="nasal-polyps"]')?.value;
        const pns = entWrapper.querySelector('[data-ent-field="pns-tenderness"]')?.value;

        const tonsilGrade = entWrapper.querySelector('[data-ent-field="tonsil-grade"]')?.value;
        const tonsilApp = entWrapper.querySelector('[data-ent-field="tonsil-appearance"]')?.value;
        const ppw = entWrapper.querySelector('[data-ent-field="ppw"]')?.value;
        const uvula = entWrapper.querySelector('[data-ent-field="uvula"]')?.value;

        const lines = ['ENT Examination:'];
        // Ear
        const earDetails = [];
        if (adCanal !== 'Normal / Clear' || adTm !== 'Intact, pearly grey, cone of light +ve') {
          earDetails.push(`RT (AD): Canal ${adCanal}, TM ${adTm}`);
        }
        if (asCanal !== 'Normal / Clear' || asTm !== 'Intact, pearly grey, cone of light +ve') {
          earDetails.push(`LT (AS): Canal ${asCanal}, TM ${asTm}`);
        }
        if (earDetails.length > 0) {
          lines.push(`• Otoscopy: ${earDetails.join('; ')}.`);
        } else {
          lines.push('• Otoscopy: Bilateral external auditory canals clear. Both tympanic membranes intact, pearly grey with sharp cone of light.');
        }

        // Tuning fork
        if (weber && !weber.includes('Centred')) {
          lines.push(`• Tuning Fork (512 Hz): ${weber}, RT ${adRinne}, LT ${asRinne}.`);
        }

        // Nose
        const noseDetails = [];
        if (septum && !septum.includes('Midline')) noseDetails.push(`Septum: ${septum}`);
        if (turbinates && !turbinates.includes('Normal')) noseDetails.push(`Turbinates: ${turbinates}`);
        if (mucosa && !mucosa.includes('Healthy')) noseDetails.push(`Mucosa: ${mucosa}`);
        if (polyps && !polyps.includes('Absent')) noseDetails.push(`Polyps: ${polyps}`);
        if (pns && !pns.includes('None')) noseDetails.push(`Sinuses: ${pns}`);

        if (noseDetails.length > 0) {
          lines.push(`• Rhinoscopy & PNS: ${noseDetails.join(', ')}.`);
        } else {
          lines.push('• Anterior Rhinoscopy: Septum midline, inferior turbinates normal, no polyps, paranasal sinuses non-tender.');
        }

        // Throat
        const throatDetails = [];
        throatDetails.push(`Tonsils: ${tonsilGrade} (${tonsilApp})`);
        if (ppw && !ppw.includes('Smooth')) throatDetails.push(`PPW: ${ppw}`);
        if (uvula && !uvula.includes('Central')) throatDetails.push(`Uvula: ${uvula}`);

        lines.push(`• Oral Cavity & Oropharynx: ${throatDetails.join(', ')}.`);

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      entWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', updateEntSummary);
      });

      // Quick Presets
      entWrapper.querySelectorAll('[data-ent-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-ent-preset');
          if (p === 'safe-csom-ad') {
            const adCanal = entWrapper.querySelector('[data-ent-field="ad-canal"]');
            const adTm = entWrapper.querySelector('[data-ent-field="ad-tm"]');
            const adRinne = entWrapper.querySelector('[data-ent-field="ad-rinne"]');
            const weber = entWrapper.querySelector('[data-ent-field="weber"]');
            if (adCanal) adCanal.value = 'Mucopurulent non-foul discharge';
            if (adTm) adTm.value = 'Central perforation (Pars Tensa, Safe CSOM)';
            if (adRinne) adRinne.value = 'Rinne Negative (BC > AC, Conductive Loss)';
            if (weber) weber.value = 'Weber Lateralized to Right Ear (AD)';
          } else if (p === 'allergic-rhinitis') {
            const septum = entWrapper.querySelector('[data-ent-field="nasal-septum"]');
            const turb = entWrapper.querySelector('[data-ent-field="turbinates"]');
            const muc = entWrapper.querySelector('[data-ent-field="nasal-mucosa"]');
            if (septum) septum.value = 'DNS to Right with spur';
            if (turb) turb.value = 'Bilateral Inferior Turbinate Hypertrophy';
            if (muc) muc.value = 'Pale, boggy, watery rhinorrhea (Allergic)';
          } else if (p === 'acute-tonsillitis') {
            const grade = entWrapper.querySelector('[data-ent-field="tonsil-grade"]');
            const app = entWrapper.querySelector('[data-ent-field="tonsil-appearance"]');
            const ppw = entWrapper.querySelector('[data-ent-field="ppw"]');
            if (grade) grade.value = 'Grade 3 (Significant airway narrowing, 50-75%)';
            if (app) app.value = 'Cryptic, with follicular white exudates';
            if (ppw) ppw.value = 'Congested with mucosal erythema';
          } else if (p === 'impacted-wax') {
            const adCanal = entWrapper.querySelector('[data-ent-field="ad-canal"]');
            const asCanal = entWrapper.querySelector('[data-ent-field="as-canal"]');
            if (adCanal) adCanal.value = 'Impacted Cerumen (Wax)';
            if (asCanal) asCanal.value = 'Impacted Cerumen (Wax)';
          } else if (p === 'normal-all') {
            entWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }
          updateEntSummary();
        });
      });

      const normalBtn = entWrapper.querySelector('[data-action="normal-ent"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          entWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          updateEntSummary();
        });
      }

      const clearBtn = entWrapper.querySelector('[data-action="clear-ent"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          entWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamEnt);
  } else {
    initExamEnt();
  }
})();
