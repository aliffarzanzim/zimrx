// Clinical neurological exam: Glasgow Coma Scale (GCS), cranial nerve mapping, motor power (MRC), and stroke assessment.
(function() {
  'use strict';

  function initExamNeurology() {
    // Neurology and stroke assessment handlers
    const neuroWrapper = document.getElementById('neuro-wrapper');
    if (neuroWrapper) {
      const modeTabs = neuroWrapper.querySelectorAll('.zrx-exam-mode-tab[data-neuro-tab]');
      const panes = neuroWrapper.querySelectorAll('.zrx-neuro-pane');
      const textarea = document.getElementById('neuro-findings');
      const gcsTotalInput = document.getElementById('neuro-gcs-total');
      const gcsSeverityInput = document.getElementById('neuro-gcs-severity');

      // Tab switching
      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-neuro-tab');
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');

          panes.forEach(pane => {
            if (pane.id === `zrx-neuro-pane-${target}`) {
              pane.removeAttribute('hidden');
              pane.classList.add('active');
            } else {
              pane.setAttribute('hidden', '');
              pane.classList.remove('active');
            }
          });
        });
      });

      function getNeuroVal(name) {
        return neuroWrapper.querySelector(`[data-neuro-field="${name}"]`)?.value || '';
      }

      function setNeuroVal(name, val) {
        const el = neuroWrapper.querySelector(`[data-neuro-field="${name}"]`);
        if (el) el.value = val;
      }

      function updateGCSCalculation() {
        const e = parseInt(getNeuroVal('gcs-e'), 10) || 4;
        const v = parseInt(getNeuroVal('gcs-v'), 10) || 5;
        const m = parseInt(getNeuroVal('gcs-m'), 10) || 6;
        const total = e + v + m;

        if (gcsTotalInput) {
          gcsTotalInput.value = `GCS: ${total}/15 (E${e} V${v} M${m})`;
        }
        if (gcsSeverityInput) {
          if (total >= 13) {
            gcsSeverityInput.value = 'Mild / Minor Injury';
            gcsSeverityInput.style.color = '#15803d';
            gcsSeverityInput.style.background = '#f0fdf4';
          } else if (total >= 9) {
            gcsSeverityInput.value = 'Moderate Impairment';
            gcsSeverityInput.style.color = '#b45309';
            gcsSeverityInput.style.background = '#fefce8';
          } else {
            gcsSeverityInput.value = 'Severe Coma (≤8 Intubate)';
            gcsSeverityInput.style.color = '#b91c1c';
            gcsSeverityInput.style.background = '#fee2e2';
          }
        }
        return { total, e, v, m };
      }

      function syncNeuroSummary() {
        if (!textarea) return;

        const gcs = updateGCSCalculation();
        const meningeal = getNeuroVal('meningeal-signs');
        const pupils = getNeuroVal('pupils');
        const eom = getNeuroVal('eom');
        const facial = getNeuroVal('facial-nerve');
        const bulbar = getNeuroVal('bulbar-tongue');
        const rtUpper = getNeuroVal('rt-upper-power');
        const ltUpper = getNeuroVal('lt-upper-power');
        const rtLower = getNeuroVal('rt-lower-power');
        const ltLower = getNeuroVal('lt-lower-power');
        const drift = getNeuroVal('pronator-drift');
        const tone = getNeuroVal('muscle-tone');
        const dtrs = getNeuroVal('dtr-reflexes');
        const babinski = getNeuroVal('babinski-sign');
        const cerebellar = getNeuroVal('cerebellar-signs');
        const sensory = getNeuroVal('sensory-exam');

        const lines = ['Neurology & Stroke Examination:'];
        lines.push(`• Consciousness & GCS: Total ${gcs.total}/15 (E${gcs.e} V${gcs.v} M${gcs.m}); Meninges: ${meningeal}.`);
        lines.push(`• Cranial Nerves: Pupils: ${pupils}; EOM: ${eom}; Facial VII: ${facial}; Bulbar/Speech: ${bulbar}.`);
        lines.push(`• Motor System (MRC): Rt Upper: ${rtUpper}, Lt Upper: ${ltUpper}; Rt Lower: ${rtLower}, Lt Lower: ${ltLower}.`);
        lines.push(`• Tone & Drift: Tone: ${tone}; Drift: ${drift}.`);
        lines.push(`• Reflexes & Plantars: DTRs: ${dtrs}; Plantar: ${babinski}.`);
        lines.push(`• Sensation & Coordination: Sensory: ${sensory}; Cerebellar: ${cerebellar}.`);

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      neuroWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', syncNeuroSummary);
      });

      // Quick presets
      neuroWrapper.querySelectorAll('[data-neuro-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-neuro-preset');

          if (p === 'stroke-rt') {
            setNeuroVal('gcs-e', '4');
            setNeuroVal('gcs-v', '4');
            setNeuroVal('gcs-m', '6');
            setNeuroVal('facial-nerve', 'Right UMN facial palsy (Lower facial droop, forehead spared - Stroke)');
            setNeuroVal('bulbar-tongue', 'Dysarthria (Slurred speech) without aphasia');
            setNeuroVal('rt-upper-power', 'Grade 2/5 (Movement with gravity eliminated)');
            setNeuroVal('lt-upper-power', 'Grade 5/5 (Normal power against full resistance)');
            setNeuroVal('rt-lower-power', 'Grade 3/5 (Active movement against gravity only)');
            setNeuroVal('lt-lower-power', 'Grade 5/5 (Normal power against full resistance)');
            setNeuroVal('pronator-drift', 'Positive Right pronator drift (Right pyramidal tract weakness)');
            setNeuroVal('muscle-tone', 'Spasticity / Clasp-knife hypertonia (Pyramidal / UMN lesion)');
            setNeuroVal('dtr-reflexes', 'Asymmetric right hyperreflexia with clonus');
            setNeuroVal('babinski-sign', 'Right extensor plantar response (Babinski positive)');
            setNeuroVal('sensory-exam', 'Right-sided hemisensory loss (Pain & temperature)');
          } else if (p === 'bells-palsy') {
            setNeuroVal('facial-nerve', 'Right LMN facial palsy / Bell palsy (Complete hemi-facial weakness including forehead & eye closure)');
            setNeuroVal('rt-upper-power', 'Grade 5/5 (Normal power against full resistance)');
            setNeuroVal('lt-upper-power', 'Grade 5/5 (Normal power against full resistance)');
            setNeuroVal('rt-lower-power', 'Grade 5/5 (Normal power against full resistance)');
            setNeuroVal('lt-lower-power', 'Grade 5/5 (Normal power against full resistance)');
            setNeuroVal('babinski-sign', 'Bilateral flexor plantar responses (Normal downward)');
          } else if (p === 'parkinson') {
            setNeuroVal('muscle-tone', 'Cogwheel / Lead-pipe rigidity (Extrapyramidal / Parkinsonism)');
            setNeuroVal('cerebellar-signs', 'Finger-nose & heel-shin intact, no dysdiadochokinesia, negative Romberg');
            setNeuroVal('babinski-sign', 'Bilateral flexor plantar responses (Normal downward)');
          } else if (p === 'diabetic-neuro') {
            setNeuroVal('sensory-exam', 'Stocking-and-glove distal sensory impairment (Peripheral neuropathy)');
            setNeuroVal('dtr-reflexes', 'Hyporeflexia 1+ throughout');
            setNeuroVal('babinski-sign', 'Bilateral flexor plantar responses (Normal downward)');
          } else if (p === 'severe-gcs') {
            setNeuroVal('gcs-e', '2');
            setNeuroVal('gcs-v', '2');
            setNeuroVal('gcs-m', '3');
            setNeuroVal('pupils', 'Right pupil dilated and sluggish / fixed (Uncal herniation / 3rd nerve)');
            setNeuroVal('muscle-tone', 'Spasticity / Clasp-knife hypertonia (Pyramidal / UMN lesion)');
            setNeuroVal('babinski-sign', 'Bilateral extensor plantar responses (Bilateral Babinski positive)');
          } else if (p === 'normal-neuro') {
            neuroWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }

          syncNeuroSummary();
        });
      });

      const normalBtn = neuroWrapper.querySelector('[data-action="normal-neuro"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          neuroWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          updateGCSCalculation();
          if (textarea) {
            textarea.value = 'Neurology & Stroke Examination:\n• Consciousness & Meninges: GCS 15/15 (E4 V5 M6). Fully oriented. Neck supple, Kernig and Brudzinski negative.\n• Cranial Nerves: Pupils equal, round, and reactive to light & accommodation (PERRLA 3mm). Full extraocular motility without nystagmus. Symmetrical facial movements, palate elevates symmetrically, tongue protrudes midline.\n• Motor System: Power Grade 5/5 against full resistance across all 4 extremities. Normal muscle tone throughout, negative pronator drift.\n• Reflexes & Sensation: DTRs 2+ brisk bilaterally. Bilateral plantar responses flexor (Babinski negative). Sensation intact to light touch and pinprick. Finger-nose coordination intact, Romberg negative.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      const clearBtn = neuroWrapper.querySelector('[data-action="clear-neuro"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          neuroWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          updateGCSCalculation();
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      updateGCSCalculation();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamNeurology);
  } else {
    initExamNeurology();
  }
})();
