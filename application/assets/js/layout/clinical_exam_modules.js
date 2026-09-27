/**
 * ZimRx - Clinical Examination Modules Controller
 * Interactive clock diagrams, coordinate mapping (O'Clock & FN distance),
 * E1-E5 clinical staging, and structured examination logic.
 */
(function() {
  'use strict';

  // In-memory state for placed breast lesions
  const breastMarkers = {
    RT: [],
    LT: []
  };

  let activeEScore = '';

  function getSvgPoint(svg, clientX, clientY) {
    const pt = svg.createSVGPoint();
    pt.x = clientX;
    pt.y = clientY;
    const ctm = svg.getScreenCTM();
    if (!ctm) return { x: 0, y: 0 };
    const svgPoint = pt.matrixTransform(ctm.inverse());
    return { x: svgPoint.x, y: svgPoint.y };
  }

  function calculateClockAndDistance(x, y) {
    const r = Math.sqrt(x * x + y * y);
    // Angle in degrees where top (12 o'clock) is 0 deg
    let deg = (Math.atan2(x, -y) * 180 / Math.PI + 360) % 360;
    let hour = Math.round((deg / 30) * 2) / 2;
    if (hour === 0) hour = 12;

    const fnCm = Math.max(0.5, Math.round((r / 82) * 6.5 * 10) / 10);
    let zone = 'Mid-zone';
    if (r <= 24) zone = 'Areolar / Retroareolar';
    else if (r > 52) zone = 'Outer / Peripheral';

    return { hour, fnCm, zone, r };
  }

  function renderMarkers(side) {
    const group = document.getElementById(`zrx-markers-${side.toLowerCase()}`);
    if (!group) return;
    group.innerHTML = '';

    breastMarkers[side].forEach((m, idx) => {
      const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
      g.setAttribute('class', 'zrx-svg-pin');
      g.setAttribute('data-id', m.id);

      const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      circle.setAttribute('cx', m.x);
      circle.setAttribute('cy', m.y);
      circle.setAttribute('r', '7.5');
      circle.setAttribute('fill', '#ef4444');
      circle.setAttribute('stroke', '#ffffff');
      circle.setAttribute('stroke-width', '2');
      circle.setAttribute('filter', 'drop-shadow(0 1px 2px rgba(0,0,0,0.3))');

      const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      text.setAttribute('x', m.x);
      text.setAttribute('y', m.y + 3);
      text.setAttribute('font-size', '8.5');
      text.setAttribute('font-weight', '700');
      text.setAttribute('fill', '#ffffff');
      text.setAttribute('text-anchor', 'middle');
      text.textContent = String(idx + 1);

      g.appendChild(circle);
      g.appendChild(text);
      group.appendChild(g);
    });
  }

  function renderLesionCards() {
    const listWrap = document.getElementById('zrx-breast-lesions-list');
    if (!listWrap) return;

    const allMarkers = [
      ...breastMarkers.RT.map((m, idx) => ({ ...m, side: 'Right', code: 'RT', num: idx + 1 })),
      ...breastMarkers.LT.map((m, idx) => ({ ...m, side: 'Left', code: 'LT', num: idx + 1 }))
    ];

    if (allMarkers.length === 0) {
      listWrap.innerHTML = '<div class="zrx-diagram-empty-hint" id="zrx-lesions-empty-hint">No lesion markers placed. Click anywhere on the clock faces above to map lump coordinates.</div>';
      updateBreastSummary();
      return;
    }

    listWrap.innerHTML = '';
    allMarkers.forEach(m => {
      const card = document.createElement('div');
      card.className = 'zrx-lesion-card';
      card.setAttribute('data-id', m.id);

      card.innerHTML = `
        <div class="zrx-lesion-header">
          <div class="zrx-lesion-title-wrap">
            <span class="zrx-lesion-badge">${m.code} #${m.num}</span>
            <span class="zrx-lesion-title">${m.side} Breast · <strong>${m.hour} O'Clock</strong> · ${m.zone} (~${m.fnCm} cm FN)</span>
          </div>
          <button type="button" class="zrx-lesion-del-btn" data-del-id="${m.id}" title="Remove this marker">&times;</button>
        </div>
        <div class="zrx-lesion-body">
          <div class="zrx-lesion-field">
            <label class="zrx-exam-lbl">Size</label>
            <input type="text" class="zrx-exam-input zrx-lesion-size" value="${m.size || '2.0x1.5 cm'}" placeholder="e.g. 2x1.5 cm">
          </div>
          <div class="zrx-lesion-field">
            <label class="zrx-exam-lbl">Consistency</label>
            <select class="zrx-exam-select zrx-lesion-cons">
              <option value="Fibroelastic / Firm"${m.cons === 'Fibroelastic / Firm' ? ' selected' : ''}>Fibroelastic / Firm</option>
              <option value="Hard / Stoney"${m.cons === 'Hard / Stoney' ? ' selected' : ''}>Hard / Stoney</option>
              <option value="Soft"${m.cons === 'Soft' ? ' selected' : ''}>Soft</option>
              <option value="Cystic / Fluctuant"${m.cons === 'Cystic / Fluctuant' ? ' selected' : ''}>Cystic / Fluctuant</option>
            </select>
          </div>
          <div class="zrx-lesion-field">
            <label class="zrx-exam-lbl">Mobility</label>
            <select class="zrx-exam-select zrx-lesion-mob">
              <option value="Freely mobile"${m.mob === 'Freely mobile' ? ' selected' : ''}>Freely mobile</option>
              <option value="Restricted"${m.mob === 'Restricted' ? ' selected' : ''}>Restricted</option>
              <option value="Fixed to skin"${m.mob === 'Fixed to skin' ? ' selected' : ''}>Fixed to skin</option>
              <option value="Fixed to pectoralis"${m.mob === 'Fixed to pectoralis' ? ' selected' : ''}>Fixed to pectoralis</option>
            </select>
          </div>
          <div class="zrx-lesion-field">
            <label class="zrx-exam-lbl">Tenderness / Notes</label>
            <input type="text" class="zrx-exam-input zrx-lesion-notes" value="${m.notes || ''}" placeholder="e.g. Non-tender, well-defined">
          </div>
        </div>
      `;

      // Change listeners for lesion parameters
      card.querySelector('.zrx-lesion-size').addEventListener('input', function() {
        m.size = this.value.trim();
        updateMarkerState(m);
        updateBreastSummary();
      });
      card.querySelector('.zrx-lesion-cons').addEventListener('change', function() {
        m.cons = this.value;
        updateMarkerState(m);
        updateBreastSummary();
      });
      card.querySelector('.zrx-lesion-mob').addEventListener('change', function() {
        m.mob = this.value;
        updateMarkerState(m);
        updateBreastSummary();
      });
      card.querySelector('.zrx-lesion-notes').addEventListener('input', function() {
        m.notes = this.value.trim();
        updateMarkerState(m);
        updateBreastSummary();
      });

      // Remove button listener
      card.querySelector('.zrx-lesion-del-btn').addEventListener('click', function() {
        removeMarker(m.id);
      });

      listWrap.appendChild(card);
    });

    updateBreastSummary();
  }

  function updateMarkerState(updatedMarker) {
    ['RT', 'LT'].forEach(side => {
      const found = breastMarkers[side].find(x => x.id === updatedMarker.id);
      if (found) {
        Object.assign(found, updatedMarker);
      }
    });
  }

  function removeMarker(id) {
    ['RT', 'LT'].forEach(side => {
      breastMarkers[side] = breastMarkers[side].filter(m => m.id !== id);
      renderMarkers(side);
    });
    renderLesionCards();
  }

  function updateBreastSummary() {
    const textarea = document.getElementById('breast-exam-findings');
    if (!textarea) return;

    const allMarkers = [
      ...breastMarkers.RT.map((m, idx) => ({ ...m, side: 'Right', num: idx + 1 })),
      ...breastMarkers.LT.map((m, idx) => ({ ...m, side: 'Left', num: idx + 1 }))
    ];

    if (allMarkers.length === 0 && !activeEScore) {
      return;
    }

    const lines = ['Clinical Breast Examination:'];
    if (allMarkers.length === 0) {
      lines.push('• Breasts: No discrete palpable lump mapped on clock diagram.');
    } else {
      allMarkers.forEach(m => {
        const details = [
          `approx ${m.size || '2.0x1.5 cm'}`,
          `Consistency: ${m.cons || 'Fibroelastic / Firm'}`,
          `Mobility: ${m.mob || 'Freely mobile'}`,
          m.notes
        ].filter(Boolean).join(', ');
        lines.push(`• ${m.side} Breast: Lesion #${m.num} at ${m.hour} O'Clock (${m.zone}, ${m.fnCm} cm FN) — ${details}.`);
      });
    }

    if (activeEScore) {
      lines.push(`• Clinical Category: ${activeEScore}`);
    }

    // Append lymph nodes if selected
    const rtNodes = document.querySelector('[data-be-field="rt-nodes"]')?.value;
    const ltNodes = document.querySelector('[data-be-field="lt-nodes"]')?.value;
    if (rtNodes === 'Not palpable' && ltNodes === 'Not palpable') {
      lines.push('• Axillary Lymph Nodes: Not palpable bilaterally.');
    } else if (rtNodes || ltNodes) {
      lines.push(`• Axillary Lymph Nodes: RT (${rtNodes || 'Not palpable'}), LT (${ltNodes || 'Not palpable'}).`);
    }

    textarea.value = lines.join('\n');
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function initClinicalExamModules() {
    // 1. Preset chip click handlers across all exam modules
    document.querySelectorAll('.zrx-exam-chip').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const wrapper = this.closest('.zrx-exam-wrapper');
        if (!wrapper) return;
        const textarea = wrapper.querySelector('.zrx-exam-textarea');
        if (!textarea) return;

        const presetText = this.getAttribute('data-preset') || this.textContent.trim();
        const currentVal = textarea.value.trim();
        if (!currentVal) {
          textarea.value = presetText;
        } else if (!currentVal.includes(presetText)) {
          textarea.value = currentVal + '\n' + presetText;
        }
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    // 2. Breast Examination Clock SVG Diagram Click Handlers
    document.querySelectorAll('.zrx-breast-svg').forEach(svg => {
      svg.addEventListener('click', function(e) {
        const side = this.getAttribute('data-side'); // 'RT' or 'LT'
        if (!side) return;

        const pt = getSvgPoint(this, e.clientX, e.clientY);
        const { hour, fnCm, zone, r } = calculateClockAndDistance(pt.x, pt.y);

        // Ignore clicks too far outside the breast outer boundary
        if (r > 86) return;

        const markerId = 'm_' + side + '_' + Date.now();
        breastMarkers[side].push({
          id: markerId,
          x: Math.round(pt.x * 10) / 10,
          y: Math.round(pt.y * 10) / 10,
          hour,
          fnCm,
          zone,
          size: '2.0x1.5 cm',
          cons: 'Fibroelastic / Firm',
          mob: 'Freely mobile',
          notes: 'Non-tender'
        });

        renderMarkers(side);
        renderLesionCards();
      });
    });

    // Clear buttons for diagrams
    const clearRtBtn = document.querySelector('[data-action="clear-diagram-rt"]');
    if (clearRtBtn) {
      clearRtBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        breastMarkers.RT = [];
        renderMarkers('RT');
        renderLesionCards();
      });
    }

    const clearLtBtn = document.querySelector('[data-action="clear-diagram-lt"]');
    if (clearLtBtn) {
      clearLtBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        breastMarkers.LT = [];
        renderMarkers('LT');
        renderLesionCards();
      });
    }

    // E1-E5 Clinical Staging Category Buttons
    document.querySelectorAll('.zrx-e-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const isCurrentActive = this.classList.contains('active');
        document.querySelectorAll('.zrx-e-btn').forEach(b => b.classList.remove('active'));

        if (isCurrentActive) {
          activeEScore = '';
        } else {
          this.classList.add('active');
          activeEScore = this.getAttribute('data-escore') || this.textContent.trim();
        }
        updateBreastSummary();
      });
    });

    // Breast Exam Normal Both and Clear All actions
    const beWrapper = document.getElementById('breast-exam-wrapper');
    if (beWrapper) {
      const normalBtn = beWrapper.querySelector('[data-action="normal-breast"]');
      const clearBtn = beWrapper.querySelector('[data-action="clear-breast"]');
      const textarea = beWrapper.querySelector('#breast-exam-findings');

      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          breastMarkers.RT = [];
          breastMarkers.LT = [];
          renderMarkers('RT');
          renderMarkers('LT');
          renderLesionCards();

          beWrapper.querySelectorAll('.zrx-e-btn').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-escore') === 'E1');
          });
          activeEScore = 'E1 (Normal)';

          beWrapper.querySelectorAll('select').forEach(sel => {
            if (sel.dataset.beField?.includes('symmetry')) sel.value = 'Normal / Symmetrical';
            else if (sel.dataset.beField?.includes('skin')) sel.value = 'Normal';
            else if (sel.dataset.beField?.includes('nipple')) sel.value = 'Normal';
            else if (sel.dataset.beField?.includes('discharge')) sel.value = 'None';
            else if (sel.dataset.beField?.includes('nodes')) sel.value = 'Not palpable';
          });

          if (textarea) {
            textarea.value = 'Normal Bilateral Breast Examination (CBE):\n• Breasts: Symmetrical, no skin dimpling, everted nipples without discharge.\n• Palpation: No discrete palpable breast mass bilaterally.\n• Clinical Category: E1 (Normal).\n• Axillary Lymph Nodes: Not palpable bilaterally.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          breastMarkers.RT = [];
          breastMarkers.LT = [];
          renderMarkers('RT');
          renderMarkers('LT');
          renderLesionCards();

          activeEScore = '';
          beWrapper.querySelectorAll('.zrx-e-btn').forEach(b => b.classList.remove('active'));
          beWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          beWrapper.querySelectorAll('input[type="text"]').forEach(input => input.value = '');

          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }

    // 3. Local Examination Handlers
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
          leWrapper.querySelectorAll('select').forEach(sel => {
            if (sel.dataset.leField === 'insp-skin') sel.value = 'Normal';
            else if (sel.dataset.leField === 'insp-edge') sel.value = 'Well-defined / Clear';
            else if (sel.dataset.leField === 'insp-impulse') sel.value = 'None / Negative';
            else if (sel.dataset.leField === 'palp-temp') sel.value = 'Normal temperature';
            else if (sel.dataset.leField === 'palp-tenderness') sel.value = 'Non-tender';
            else if (sel.dataset.leField === 'palp-fluctuation') sel.value = 'Non-fluctuant';
            else if (sel.dataset.leField === 'palp-transillumination') sel.value = 'Opaque / Negative';
            else if (sel.dataset.leField === 'palp-reducibility') sel.value = 'Non-reducible';
            else if (sel.dataset.leField === 'palp-fixity') sel.value = 'Subcutaneous (Freely mobile)';
            else if (sel.dataset.leField === 'regional-nodes') sel.value = 'Not enlarged / Not palpable';
            else if (sel.dataset.leField === 'vascular-bruit') sel.value = 'Absent';
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

    // 3b. O/H Pelvic Examination (P/S & P/V) Handlers
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

    // 4. Ophthalmology Handlers
    const ophWrapper = document.getElementById('ophthalmology-wrapper');
    if (ophWrapper) {
      const normalBtn = ophWrapper.querySelector('[data-action="normal-eye"]');
      const clearBtn = ophWrapper.querySelector('[data-action="clear-eye"]');
      const textarea = ophWrapper.querySelector('#ophthalmology-findings');

      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          ophWrapper.querySelectorAll('select').forEach(sel => {
            if (sel.dataset.ophField?.endsWith('-dva')) sel.value = '6/6';
            else if (sel.dataset.ophField?.endsWith('-nva')) sel.value = 'N6';
            else if (sel.dataset.ophField?.endsWith('-lids')) sel.value = 'Normal';
            else if (sel.dataset.ophField?.endsWith('-conjunctiva')) sel.value = 'Normal / Clear';
            else if (sel.dataset.ophField?.endsWith('-cornea')) sel.value = 'Clear';
            else if (sel.dataset.ophField?.endsWith('-ac')) sel.value = 'Normal depth, quiet';
            else if (sel.dataset.ophField?.endsWith('-pupil')) sel.value = 'Round Regular Reactive (RRR)';
            else if (sel.dataset.ophField?.endsWith('-lens')) sel.value = 'Clear';
            else if (sel.dataset.ophField?.endsWith('-disc')) sel.value = 'Normal pink, distinct margin';
            else if (sel.dataset.ophField?.endsWith('-cdr')) sel.value = 'CDR 0.3';
            else if (sel.dataset.ophField?.endsWith('-macula')) sel.value = 'Normal foveal reflex';
            else if (sel.dataset.ophField?.endsWith('-retina')) sel.value = 'Normal background';
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
    document.addEventListener('DOMContentLoaded', initClinicalExamModules);
  } else {
    initClinicalExamModules();
  }
})();
