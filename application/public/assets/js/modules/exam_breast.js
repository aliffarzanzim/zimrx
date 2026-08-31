// Clinical breast examination diagram: SVG clock face coordinate mapping (o'clock and distance from nipple), lesion cards, and E1-E5 staging.
(function() {
  'use strict';

  const escapeAttr = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

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
            <input type="text" class="zrx-exam-input zrx-lesion-size" value="${escapeAttr(m.size || '2.0x1.5 cm')}" placeholder="e.g. 2x1.5 cm">
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
            <input type="text" class="zrx-exam-input zrx-lesion-notes" value="${escapeAttr(m.notes || '')}" placeholder="e.g. Non-tender, well-defined">
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
        lines.push(`• ${m.side} Breast: Lesion #${m.num} at ${m.hour} O'Clock (${m.zone}, ${m.fnCm} cm FN) - ${details}.`);
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


  function initBreastExam() {
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

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBreastExam);
  } else {
    initBreastExam();
  }
})();
