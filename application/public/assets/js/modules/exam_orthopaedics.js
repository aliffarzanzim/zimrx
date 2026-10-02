// Clinical orthopaedic exam: spine provocative tests (SLR/FABER), extremity range-of-motion, joint stability, and rheumatological scoring.
(function() {
  'use strict';

  function initExamOrthopaedics() {
    // Orthopaedics and musculoskeletal examination handlers
    const orthoWrapper = document.getElementById('ortho-wrapper');
    if (orthoWrapper) {
      const modeTabs = orthoWrapper.querySelectorAll('.zrx-exam-mode-tab[data-ortho-tab]');
      const panes = orthoWrapper.querySelectorAll('.zrx-ortho-pane');
      const textarea = document.getElementById('ortho-findings');

      // Tab switching
      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-ortho-tab');
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');

          panes.forEach(pane => {
            if (pane.id === `zrx-ortho-pane-${target}`) {
              pane.removeAttribute('hidden');
              pane.classList.add('active');
            } else {
              pane.setAttribute('hidden', '');
              pane.classList.remove('active');
            }
          });
        });
      });

      function getOrthoVal(name) {
        return orthoWrapper.querySelector(`[data-ortho-field="${name}"]`)?.value || '';
      }

      function setOrthoVal(name, val) {
        const el = orthoWrapper.querySelector(`[data-ortho-field="${name}"]`);
        if (el) el.value = val;
      }

      function syncOrthoSummary() {
        if (!textarea) return;

        const gait = getOrthoVal('gait');
        const cSpine = getOrthoVal('cervical-spine');
        const lSpine = getOrthoVal('lumbar-spine');
        const slr = getOrthoVal('slr-test');
        const siJoint = getOrthoVal('si-joint');
        const shoulderRom = getOrthoVal('shoulder-rom');
        const shoulderCuff = getOrthoVal('shoulder-cuff');
        const elbow = getOrthoVal('elbow');
        const wristHand = getOrthoVal('wrist-hand');
        const handDeform = getOrthoVal('hand-deformity');
        const hip = getOrthoVal('hip-joint');
        const kneeEff = getOrthoVal('knee-effusion');
        const kneeLig = getOrthoVal('knee-ligaments');
        const ankle = getOrthoVal('ankle-stability');
        const foot = getOrthoVal('foot-achilles');
        const tjc = getOrthoVal('tjc');
        const sjc = getOrthoVal('sjc');
        const morningStiff = getOrthoVal('morning-stiffness');
        const spa = getOrthoVal('spa-markers');

        const lines = ['Orthopaedic & Musculoskeletal Examination:'];
        lines.push(`• Spine & Gait: ${gait}; C-Spine: ${cSpine}; L-Spine: ${lSpine}; SLR: ${slr}; SI Joint: ${siJoint}.`);
        lines.push(`• Upper Limb: Shoulder: ${shoulderRom}, ${shoulderCuff}; Elbow: ${elbow}; Wrist/Hand: ${wristHand}, ${handDeform}.`);
        lines.push(`• Lower Limb: Hip: ${hip}; Knee: ${kneeEff}, ${kneeLig}; Ankle/Foot: ${ankle}, ${foot}.`);
        if (tjc !== '0 (No tender joints)' || sjc !== '0 (No active joint synovitis/swelling)' || morningStiff !== 'None / Absent') {
          lines.push(`• Rheumatology Assessment: TJC: ${tjc}; SJC: ${sjc}; Morning Stiffness: ${morningStiff}; SpA Markers: ${spa}.`);
        }

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      orthoWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', syncOrthoSummary);
      });

      // Quick presets
      orthoWrapper.querySelectorAll('[data-ortho-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-ortho-preset');

          if (p === 'knee-oa') {
            setOrthoVal('gait', 'Antalgic gait (Shortened stance phase)');
            setOrthoVal('hip-joint', 'Normal hip ROM, Thomas test negative, Trendelenburg negative');
            setOrthoVal('knee-effusion', 'Coarse crepitus during active & passive flexion (OA Knee)');
            setOrthoVal('knee-ligaments', 'Lachman, Drawer, Collaterals, and McMurray negative');
            setOrthoVal('morning-stiffness', '<30 minutes (Mechanical / Non-inflammatory / OA)');
            setOrthoVal('tjc', '1 to 3 tender joints (Mild disease activity)');
          } else if (p === 'lumbar-pivd') {
            setOrthoVal('gait', 'Antalgic gait (Shortened stance phase)');
            setOrthoVal('lumbar-spine', 'Loss of lumbar lordosis with paraspinal spasm');
            setOrthoVal('slr-test', 'Right SLR positive at 30° with radiating nerve pain');
            setOrthoVal('si-joint', 'FABER/Patrick test negative, non-tender SI joints');
          } else if (p === 'frozen-shoulder') {
            setOrthoVal('shoulder-rom', 'Frozen shoulder (Capsular restriction: External rotation > Abduction)');
            setOrthoVal('shoulder-cuff', 'Subacromial impingement (Neer & Hawkins +ve)');
          } else if (p === 'ra-poly') {
            setOrthoVal('wrist-hand', 'Tests negative (Normal wrist/hand)');
            setOrthoVal('hand-deformity', 'Ulnar deviation of MCP joints (Rheumatoid hand)');
            setOrthoVal('tjc', '>10 tender joints (High disease activity / Polyarthritis)');
            setOrthoVal('sjc', '4 to 10 swollen joints (Moderate synovitis)');
            setOrthoVal('morning-stiffness', '>1 hour (Severe inflammatory stiffness / Active RA)');
          } else if (p === 'cts') {
            setOrthoVal('wrist-hand', 'Carpal Tunnel Syndrome (Phalen & Tinel +ve over median nerve)');
            setOrthoVal('hand-deformity', 'No joint swelling, nodes, or deformities');
          } else if (p === 'normal-ortho') {
            orthoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }

          syncOrthoSummary();
        });
      });

      const normalBtn = orthoWrapper.querySelector('[data-action="normal-ortho"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          orthoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = 'Orthopaedic & Musculoskeletal Examination:\n• Spine & Posture: Normal physiological curves, full pain-free cervical and lumbar ROM. Bilateral SLR >80° negative.\n• Extremities & Joints: Shoulders, elbows, wrists, hips, knees, and ankles demonstrate full active and passive ROM without effusion, joint line tenderness, or crepitus.\n• Joint Stability & Provocative Tests: Spurling, Neer, Hawkins, Phalen, Tinel, Lachman, and McMurray tests all negative. Steady, non-antalgic gait.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      const clearBtn = orthoWrapper.querySelector('[data-action="clear-ortho"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          orthoWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamOrthopaedics);
  } else {
    initExamOrthopaedics();
  }
})();
