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

    // 5. Burn Assessment & Parkland Resuscitation Handlers
    const burnWrapper = document.getElementById('burn-assessment-wrapper');
    if (burnWrapper) {
      const activeSegments = new Set();
      const tbsaDisplay = burnWrapper.querySelector('#zrx-burn-tbsa-display');
      const classDisplay = burnWrapper.querySelector('#zrx-burn-class-display');
      const fluidDisplay = burnWrapper.querySelector('#zrx-burn-fluid-display');
      const fluid8h = burnWrapper.querySelector('#zrx-burn-fluid-8h');
      const rate8h = burnWrapper.querySelector('#zrx-burn-rate-8h');
      const fluid16h = burnWrapper.querySelector('#zrx-burn-fluid-16h');
      const rate16h = burnWrapper.querySelector('#zrx-burn-rate-16h');
      const uopTarget = burnWrapper.querySelector('#zrx-burn-uop-target');

      const weightInput = burnWrapper.querySelector('#zrx-burn-weight');
      const depthSelect = burnWrapper.querySelector('#zrx-burn-depth');
      const inhalCheck = burnWrapper.querySelector('#zrx-burn-inhalation');
      const circumCheck = burnWrapper.querySelector('#zrx-burn-circumferential');
      const elecCheck = burnWrapper.querySelector('#zrx-burn-electrical');
      const escharCheck = burnWrapper.querySelector('#zrx-burn-escharotomy');
      const textarea = burnWrapper.querySelector('#burn-exam-findings');

      const minorBtn = burnWrapper.querySelector('[data-action="minor-burn"]');
      const clearBtn = burnWrapper.querySelector('[data-action="clear-burn"]');

      function calculateBurn() {
        let tbsa = 0;
        const segmentNames = [];

        burnWrapper.querySelectorAll('.zrx-burn-segment').forEach(seg => {
          const segKey = seg.getAttribute('data-segment');
          if (activeSegments.has(segKey)) {
            seg.classList.add('active');
            const pct = parseFloat(seg.getAttribute('data-pct')) || 0;
            tbsa += pct;
            segmentNames.push(`${seg.getAttribute('data-name')} (${pct}%)`);
          } else {
            seg.classList.remove('active');
          }
        });

        tbsa = Math.min(100, Math.round(tbsa * 10) / 10);
        const weight = parseFloat(weightInput?.value) || 65;
        const depth = depthSelect?.value || '2nd Degree';
        const isFirstDegOnly = depth.includes('1st Degree');

        // Update TBSA display
        if (tbsaDisplay) tbsaDisplay.textContent = `${tbsa.toFixed(1)}%`;

        // Classification
        let classification = 'No Burn Mapped';
        if (tbsa > 0) {
          if (tbsa < 10 && !inhalCheck?.checked) {
            classification = 'Minor Burn (<10% TBSA)';
            if (classDisplay) classDisplay.className = 'zrx-burn-metric-status';
          } else if (tbsa < 20 && !inhalCheck?.checked) {
            classification = 'Moderate Burn (10-20% TBSA)';
            if (classDisplay) classDisplay.className = 'zrx-burn-metric-status moderate';
          } else {
            classification = 'Major / Severe Burn (>20% or High Risk)';
            if (classDisplay) classDisplay.className = 'zrx-burn-metric-status major';
          }
        }
        if (classDisplay) classDisplay.textContent = classification;

        // Parkland Fluid Resuscitation: 4 mL x weight (kg) x %TBSA (if 2nd/3rd degree)
        let totalFluid = 0;
        if (!isFirstDegOnly && tbsa > 0) {
          totalFluid = Math.round(4 * weight * tbsa);
        }

        const halfFluid = Math.round(totalFluid / 2);
        const hourly8h = Math.round((halfFluid / 8) * 10) / 10;
        const hourly16h = Math.round((halfFluid / 16) * 10) / 10;
        const uopMin = Math.round(weight * 0.5 * 10) / 10;
        const uopMax = Math.round(weight * 1.0 * 10) / 10;

        if (fluidDisplay) fluidDisplay.textContent = `${totalFluid.toLocaleString()} mL (RL)`;
        if (fluid8h) fluid8h.textContent = `${halfFluid.toLocaleString()} mL`;
        if (rate8h) rate8h.textContent = `(${hourly8h} mL/hr)`;
        if (fluid16h) fluid16h.textContent = `${halfFluid.toLocaleString()} mL`;
        if (rate16h) rate16h.textContent = `(${hourly16h} mL/hr)`;
        if (uopTarget) uopTarget.textContent = `${uopMin} – ${uopMax} mL/hr`;

        // Update findings textarea
        if (!textarea) return;
        if (tbsa === 0) {
          return;
        }

        const lines = [
          `Burn Assessment (Wallace Rule of Nines):`,
          `• Total Body Surface Area (% TBSA): ${tbsa.toFixed(1)}% (${classification})`,
          `• Burn Depth: ${depth}`,
          `• Involved Areas: ${segmentNames.join(', ')}`
        ];

        const risks = [];
        if (inhalCheck?.checked) risks.push('Inhalation injury suspected (close airway monitoring / early intubation threshold)');
        if (circumCheck?.checked) risks.push('Circumferential burn (monitor for distal neurovascular compromise)');
        if (elecCheck?.checked) risks.push('High-voltage/chemical etiology (monitor ECG & rhabdomyolysis)');
        if (escharCheck?.checked) risks.push('Urgent bedside escharotomy indicated');
        if (risks.length > 0) {
          lines.push(`• Risk Factors / Alerts: ${risks.join('; ')}`);
        }

        if (totalFluid > 0) {
          lines.push(`• Fluid Resuscitation (Parkland Formula @ 4 mL/kg/%TBSA, Wt ${weight} kg):`);
          lines.push(`  - Total 24h Ringer's Lactate: ${totalFluid.toLocaleString()} mL`);
          lines.push(`  - 1st 8h from burn: ${halfFluid.toLocaleString()} mL at ${hourly8h} mL/hr`);
          lines.push(`  - Next 16h: ${halfFluid.toLocaleString()} mL at ${hourly16h} mL/hr`);
          lines.push(`  - Target Urine Output: ${uopMin} - ${uopMax} mL/hr (titrate fluid rate to urine output)`);
        } else if (isFirstDegOnly) {
          lines.push(`• Fluid Note: 1st-degree superficial erythema does not require Parkland fluid resuscitation.`);
        }

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Click on silhouette segments
      burnWrapper.querySelectorAll('.zrx-burn-segment').forEach(seg => {
        seg.addEventListener('click', function() {
          const segKey = this.getAttribute('data-segment');
          if (activeSegments.has(segKey)) {
            activeSegments.delete(segKey);
          } else {
            activeSegments.add(segKey);
          }
          calculateBurn();
        });
      });

      // Inputs change listeners
      [weightInput, depthSelect, inhalCheck, circumCheck, elecCheck, escharCheck].forEach(el => {
        if (el) el.addEventListener('input', calculateBurn);
        if (el) el.addEventListener('change', calculateBurn);
      });

      // Quick scenarios
      burnWrapper.querySelectorAll('[data-burn-scenario]').forEach(btn => {
        btn.addEventListener('click', function() {
          const sc = this.getAttribute('data-burn-scenario');
          activeSegments.clear();
          if (sc === 'scald-arm') {
            activeSegments.add('ant_arm_rt');
            activeSegments.add('post_arm_rt');
          } else if (sc === 'flame-torso') {
            activeSegments.add('ant_chest');
            activeSegments.add('ant_abdo');
          } else if (sc === 'major-burn') {
            activeSegments.add('ant_chest');
            activeSegments.add('ant_abdo');
            activeSegments.add('post_back_upper');
            activeSegments.add('ant_arm_rt');
            activeSegments.add('ant_arm_lt');
          } else if (sc === 'bilateral-legs') {
            activeSegments.add('ant_leg_rt');
            activeSegments.add('ant_leg_lt');
            activeSegments.add('post_leg_rt');
            activeSegments.add('post_leg_lt');
          }
          calculateBurn();
        });
      });

      if (minorBtn) {
        minorBtn.addEventListener('click', () => {
          activeSegments.clear();
          activeSegments.add('ant_arm_rt');
          if (depthSelect) depthSelect.value = '2nd Degree (Superficial Partial Thickness)';
          calculateBurn();
        });
      }

      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          activeSegments.clear();
          burnWrapper.querySelectorAll('.zrx-burn-segment').forEach(seg => seg.classList.remove('active'));
          burnWrapper.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
          if (tbsaDisplay) tbsaDisplay.textContent = '0.0%';
          if (classDisplay) {
            classDisplay.textContent = 'No Burn Mapped';
            classDisplay.className = 'zrx-burn-metric-status';
          }
          if (fluidDisplay) fluidDisplay.textContent = '0 mL (RL)';
          if (fluid8h) fluid8h.textContent = '0 mL';
          if (rate8h) rate8h.textContent = '(0 mL/hr)';
          if (fluid16h) fluid16h.textContent = '0 mL';
          if (rate16h) rate16h.textContent = '(0 mL/hr)';
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }

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

    // 7. Dental Chart & FDI Odontogram Handlers
    const dentalWrapper = document.getElementById('dental-chart-wrapper');
    if (dentalWrapper) {
      const toothState = {}; // { toothNum: { status, surfaces: [], plan } }
      let activeTooth = 46;

      const permanentUpper = dentalWrapper.querySelector('#zrx-arch-upper');
      const permanentLower = dentalWrapper.querySelector('#zrx-arch-lower');
      const decUpper = dentalWrapper.querySelector('#zrx-arch-upper-dec');
      const decLower = dentalWrapper.querySelector('#zrx-arch-lower-dec');

      const dmftVal = dentalWrapper.querySelector('#zrx-dmft-val');
      const activeBadge = dentalWrapper.querySelector('#zrx-dental-active-badge');
      const activeTitle = dentalWrapper.querySelector('#zrx-dental-active-title');
      const statusSelect = dentalWrapper.querySelector('#zrx-tooth-status');
      const planSelect = dentalWrapper.querySelector('#zrx-tooth-plan');
      const perioSelect = dentalWrapper.querySelector('#zrx-dental-perio');
      const calcSelect = dentalWrapper.querySelector('#zrx-dental-calculus');
      const textarea = dentalWrapper.querySelector('#dental-exam-findings');

      const surfaceBoxes = {
        O: dentalWrapper.querySelector('#zrx-surf-o'),
        M: dentalWrapper.querySelector('#zrx-surf-m'),
        D: dentalWrapper.querySelector('#zrx-surf-d'),
        B: dentalWrapper.querySelector('#zrx-surf-b'),
        L: dentalWrapper.querySelector('#zrx-surf-l')
      };

      // Dentition Mode Switcher
      dentalWrapper.querySelectorAll('.zrx-dental-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          dentalWrapper.querySelectorAll('.zrx-dental-type-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          const isDec = this.getAttribute('data-dentition') === 'deciduous';
          if (permanentUpper) permanentUpper.hidden = isDec;
          if (permanentLower) permanentLower.hidden = isDec;
          if (decUpper) decUpper.hidden = !isDec;
          if (decLower) decLower.hidden = !isDec;
          selectTooth(isDec ? 55 : 46);
        });
      });

      function getToothName(num) {
        const n = parseInt(num, 10);
        const q = Math.floor(n / 10);
        const t = n % 10;
        const qNames = {
          1: 'Upper Right', 2: 'Upper Left', 3: 'Lower Left', 4: 'Lower Right',
          5: 'Primary Upper Right', 6: 'Primary Upper Left', 7: 'Primary Lower Left', 8: 'Primary Lower Right'
        };
        const tNames = {
          1: 'Central Incisor', 2: 'Lateral Incisor', 3: 'Canine', 4: 'First Premolar',
          5: 'Second Premolar', 6: 'First Molar', 7: 'Second Molar', 8: 'Third Molar'
        };
        const decNames = { 1: 'Central Incisor', 2: 'Lateral Incisor', 3: 'Canine', 4: 'First Molar', 5: 'Second Molar' };
        const qName = qNames[q] || '';
        const name = q <= 4 ? tNames[t] : decNames[t];
        return `${qName} ${name || 'Tooth'}`;
      }

      function selectTooth(num) {
        activeTooth = parseInt(num, 10);
        if (activeBadge) activeBadge.textContent = `Tooth #${activeTooth}`;
        if (activeTitle) activeTitle.textContent = getToothName(activeTooth);

        const cur = toothState[activeTooth] || { status: 'Sound / Normal', surfaces: [], plan: 'None / Observation' };
        if (statusSelect) statusSelect.value = cur.status;
        if (planSelect) planSelect.value = cur.plan;

        Object.keys(surfaceBoxes).forEach(s => {
          if (surfaceBoxes[s]) surfaceBoxes[s].checked = cur.surfaces.includes(s);
        });

        dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(btn => {
          btn.classList.toggle('active', parseInt(btn.getAttribute('data-tooth'), 10) === activeTooth);
        });
      }

      function updateToothGraphic(num) {
        const btn = dentalWrapper.querySelector(`.zrx-tooth-btn[data-tooth="${num}"]`);
        if (!btn) return;
        const cur = toothState[num];
        btn.classList.remove('caries', 'filled', 'missing', 'rct', 'crown');
        if (!cur || cur.status.includes('Sound')) return;

        if (cur.status.includes('Caries') || cur.status.includes('Carious')) {
          btn.classList.add('caries');
        } else if (cur.status.includes('Restoration') || cur.status.includes('Filled')) {
          btn.classList.add('filled');
        } else if (cur.status.includes('Missing') || cur.status.includes('Extraction')) {
          btn.classList.add('missing');
        } else if (cur.status.includes('RCT') || cur.status.includes('Root Canal')) {
          btn.classList.add('rct');
        } else if (cur.status.includes('Crown')) {
          btn.classList.add('crown');
        }
      }

      function syncDentalSummary() {
        let d = 0, m = 0, f = 0;
        const findings = [];

        Object.keys(toothState).forEach(tNum => {
          const tooth = toothState[tNum];
          const st = tooth.status;
          if (st.includes('Caries') || st.includes('Carious')) d++;
          else if (st.includes('Missing') || st.includes('Extraction')) m++;
          else if (st.includes('Filled') || st.includes('Restoration') || st.includes('Crown')) f++;

          if (!st.includes('Sound')) {
            const surfStr = tooth.surfaces.length > 0 ? ` (${tooth.surfaces.join('')})` : '';
            const planStr = tooth.plan !== 'None / Observation' ? ` → Plan: ${tooth.plan}` : '';
            findings.push(`• Tooth #${tNum} (${getToothName(tNum)}): ${st}${surfStr}${planStr}`);
          }
        });

        if (dmftVal) {
          dmftVal.textContent = `D: ${d} | M: ${m} | F: ${f} (Total: ${d + m + f})`;
        }

        if (!textarea) return;
        const perio = perioSelect?.value || 'Healthy Gingiva (Normal)';
        const calc = calcSelect?.value || 'Negligible / Clear';

        const lines = [
          'Dental Examination & Odontogram:',
          `• DMFT Score: Decayed (D): ${d}, Missing (M): ${m}, Filled (F): ${f} [Total DMFT = ${d + m + f}]`,
          `• Periodontal Status: ${perio}; Calculus: ${calc}`
        ];

        if (findings.length > 0) {
          lines.push(`• Specific Tooth Pathologies & Planned Interventions:\n${findings.join('\n')}`);
        } else {
          lines.push('• Dentition: Intact, sound permanent dentition without visible cavitations or restorations.');
        }

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Tooth button click
      dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const num = this.getAttribute('data-tooth');
          selectTooth(num);
        });
      });

      // Inspector listeners
      if (statusSelect) {
        statusSelect.addEventListener('change', () => {
          if (!toothState[activeTooth]) toothState[activeTooth] = { status: 'Sound / Normal', surfaces: [], plan: 'None / Observation' };
          toothState[activeTooth].status = statusSelect.value;
          updateToothGraphic(activeTooth);
          syncDentalSummary();
        });
      }

      if (planSelect) {
        planSelect.addEventListener('change', () => {
          if (!toothState[activeTooth]) toothState[activeTooth] = { status: 'Sound / Normal', surfaces: [], plan: 'None / Observation' };
          toothState[activeTooth].plan = planSelect.value;
          syncDentalSummary();
        });
      }

      Object.keys(surfaceBoxes).forEach(s => {
        const box = surfaceBoxes[s];
        if (box) {
          box.addEventListener('change', () => {
            if (!toothState[activeTooth]) toothState[activeTooth] = { status: 'Sound / Normal', surfaces: [], plan: 'None / Observation' };
            const surfs = new Set(toothState[activeTooth].surfaces || []);
            if (box.checked) surfs.add(s);
            else surfs.delete(s);
            toothState[activeTooth].surfaces = Array.from(surfs);
            syncDentalSummary();
          });
        }
      });

      if (perioSelect) perioSelect.addEventListener('change', syncDentalSummary);
      if (calcSelect) calcSelect.addEventListener('change', syncDentalSummary);

      // Quick presets
      dentalWrapper.querySelectorAll('[data-dental-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-dental-preset');
          if (p === 'caries-46') {
            toothState[46] = { status: 'Dental Caries (Cavity)', surfaces: ['O'], plan: 'Composite Light-cure Restoration' };
            updateToothGraphic(46);
            selectTooth(46);
          } else if (p === 'rct-11') {
            toothState[11] = { status: 'Fractured Tooth', surfaces: ['M', 'D'], plan: 'Root Canal Treatment (RCT)' };
            updateToothGraphic(11);
            selectTooth(11);
          } else if (p === 'impaction-38') {
            toothState[38] = { status: 'Impaction (Partially Erupted)', surfaces: [], plan: 'Surgical Disimpaction' };
            updateToothGraphic(38);
            selectTooth(38);
          } else if (p === 'scaling-needed') {
            if (perioSelect) perioSelect.value = 'Marginal Gingivitis (BOP positive, erythematous)';
            if (calcSelect) calcSelect.value = 'Moderate calculus with subgingival deposits';
          } else if (p === 'sound-all') {
            Object.keys(toothState).forEach(k => delete toothState[k]);
            dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(b => b.classList.remove('caries', 'filled', 'missing', 'rct', 'crown'));
            if (perioSelect) perioSelect.selectedIndex = 0;
            if (calcSelect) calcSelect.selectedIndex = 3;
            selectTooth(46);
          }
          syncDentalSummary();
        });
      });

      const soundBtn = dentalWrapper.querySelector('[data-action="sound-dental"]');
      if (soundBtn) {
        soundBtn.addEventListener('click', () => {
          Object.keys(toothState).forEach(k => delete toothState[k]);
          dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(b => b.classList.remove('caries', 'filled', 'missing', 'rct', 'crown'));
          selectTooth(46);
          syncDentalSummary();
        });
      }

      const clearBtn = dentalWrapper.querySelector('[data-action="clear-dental"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          Object.keys(toothState).forEach(k => delete toothState[k]);
          dentalWrapper.querySelectorAll('.zrx-tooth-btn').forEach(b => b.classList.remove('caries', 'filled', 'missing', 'rct', 'crown'));
          selectTooth(46);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      selectTooth(46);
    }

    // 8. Diabetic Foot Assessment Handlers
    const dfWrapper = document.getElementById('diabetic-foot-wrapper');
    if (dfWrapper) {
      const absentPoints = new Set();
      const wagnerDisplay = dfWrapper.querySelector('#zrx-df-wagner-val');
      const sensoryDisplay = dfWrapper.querySelector('#zrx-df-sensory-val');
      const riskDisplay = dfWrapper.querySelector('#zrx-df-risk-val');

      const wagnerSelect = dfWrapper.querySelector('#zrx-df-wagner-select');
      const dpPulseSelect = dfWrapper.querySelector('#zrx-df-dp-pulse');
      const ptPulseSelect = dfWrapper.querySelector('#zrx-df-pt-pulse');
      const crtSelect = dfWrapper.querySelector('#zrx-df-crt');
      const skinSelect = dfWrapper.querySelector('#zrx-df-skin');
      const deformSelect = dfWrapper.querySelector('#zrx-df-deformity');
      const textarea = dfWrapper.querySelector('#diabetic-foot-findings');

      function calculateFoot() {
        const intactCount = 10 - absentPoints.size;
        const hasLops = intactCount < 8; // Loss of protective sensation
        const wagner = wagnerSelect?.value || 'Grade 0: Intact skin';
        const dp = dpPulseSelect?.value || 'Palpable';
        const pt = ptPulseSelect?.value || 'Palpable';
        const hasPvd = dp.includes('Absent') || pt.includes('Absent') || dp.includes('Weak') && pt.includes('Weak');
        const deform = deformSelect?.value || 'None';
        const hasDeformity = !deform.includes('None');

        // Update displays
        if (wagnerDisplay) wagnerDisplay.textContent = wagner.split(':')[0];
        if (sensoryDisplay) {
          sensoryDisplay.textContent = `${intactCount} / 10 Intact ${hasLops ? '(LOPS +ve)' : '(Normal)'}`;
        }

        // IWGDF Risk Category
        let category = 'Category 0 (Very Low Risk - Annual Review)';
        let catClass = 'zrx-df-metric-status';

        if (!wagner.includes('Grade 0')) {
          category = 'Category 3 (Active Ulcer / Very High Risk)';
          catClass = 'zrx-df-metric-status high';
        } else if ((hasLops && hasPvd) || (hasLops && hasDeformity)) {
          category = 'Category 2 (Moderate-High Risk - Review q2-3mo)';
          catClass = 'zrx-df-metric-status moderate';
        } else if (hasLops || hasPvd) {
          category = 'Category 1 (Low-Moderate Risk - Review q3-6mo)';
          catClass = 'zrx-df-metric-status moderate';
        }

        if (riskDisplay) {
          riskDisplay.textContent = category;
          riskDisplay.className = catClass;
        }

        // Update SVG circles
        dfWrapper.querySelectorAll('.zrx-df-point').forEach(circle => {
          const ptId = parseInt(circle.getAttribute('data-point'), 10);
          const isAbsent = absentPoints.has(ptId);
          circle.classList.toggle('absent', isAbsent);
          circle.classList.toggle('intact', !isAbsent);
        });

        // Update textarea
        if (!textarea) return;
        const lines = [
          'Diabetic Foot Assessment & Stratification:',
          `• Wagner Staging: ${wagner}`,
          `• 10g Monofilament Sensation: ${intactCount}/10 sites intact ${hasLops ? '— Loss of Protective Sensation (LOPS) Present' : '— Protective Sensation Intact'}`,
          `• Vascular Pulses: ${dp}; ${pt}; CRT: ${crtSelect?.value || '<2s'}`,
          `• Foot Biomechanics & Skin: ${deform}; Skin: ${skinSelect?.value || 'Warm'}`,
          `• IWGDF Stratification: ${category}`
        ];

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Circle click
      dfWrapper.querySelectorAll('.zrx-df-point').forEach(circle => {
        circle.addEventListener('click', function() {
          const ptId = parseInt(this.getAttribute('data-point'), 10);
          if (absentPoints.has(ptId)) {
            absentPoints.delete(ptId);
          } else {
            absentPoints.add(ptId);
          }
          calculateFoot();
        });
      });

      // Selects listeners
      [wagnerSelect, dpPulseSelect, ptPulseSelect, crtSelect, skinSelect, deformSelect].forEach(el => {
        if (el) el.addEventListener('change', calculateFoot);
      });

      // Quick presets
      dfWrapper.querySelectorAll('[data-df-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-df-preset');
          absentPoints.clear();
          if (p === 'grade1-ulcer') {
            if (wagnerSelect) wagnerSelect.value = 'Grade 1: Superficial ulcer (not involving tendon/capsule/bone)';
            absentPoints.add(4); // 1st MT head
            absentPoints.add(5); // 3rd MT head
          } else if (p === 'grade2-deep') {
            if (wagnerSelect) wagnerSelect.value = 'Grade 2: Deep ulcer penetrating to tendon, bone or joint capsule';
            [1, 4, 5, 7, 9].forEach(pt => absentPoints.add(pt));
            if (skinSelect) skinSelect.value = 'Dry, anhidrotic with fissures (Autonomic)';
          } else if (p === 'charcot') {
            if (deformSelect) deformSelect.value = 'Charcot neuroarthropathy (Rocker bottom foot)';
            [1, 2, 3, 4, 5, 6, 7, 8].forEach(pt => absentPoints.add(pt));
          } else if (p === 'pvd-diminished') {
            if (dpPulseSelect) dpPulseSelect.value = 'Dorsalis Pedis: Absent (0)';
            if (ptPulseSelect) ptPulseSelect.value = 'Posterior Tibial: Absent (0)';
            if (crtSelect) crtSelect.value = 'CRT > 2 seconds (Delayed / PVD)';
            if (skinSelect) skinSelect.value = 'Cold, pale / cyanotic on elevation';
          } else if (p === 'normal-foot') {
            if (wagnerSelect) wagnerSelect.selectedIndex = 0;
            if (dpPulseSelect) dpPulseSelect.selectedIndex = 0;
            if (ptPulseSelect) ptPulseSelect.selectedIndex = 0;
            if (crtSelect) crtSelect.selectedIndex = 0;
            if (skinSelect) skinSelect.selectedIndex = 0;
            if (deformSelect) deformSelect.selectedIndex = 0;
          }
          calculateFoot();
        });
      });

      const normalBtn = dfWrapper.querySelector('[data-action="normal-foot"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          absentPoints.clear();
          dfWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          calculateFoot();
        });
      }

      const clearBtn = dfWrapper.querySelector('[data-action="clear-foot"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          absentPoints.clear();
          dfWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          calculateFoot();
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      calculateFoot();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initClinicalExamModules);
  } else {
    initClinicalExamModules();
  }
})();
