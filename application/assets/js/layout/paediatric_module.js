/**
 * ZimRx Paediatric History & WHO/CDC Growth Chart Controller
 */

function initPaediatricModule() {
  if (window.__zimrxPaediatricInitialized) {
    refreshPaediatricPatientData();
    return;
  }
  window.__zimrxPaediatricInitialized = true;

  let phDragRow = null;
  let currentMetric = 'weight'; // 'weight' | 'height' | 'hc' | 'bmi'
  let currentStandard = 'auto'; // 'auto' | 'who' | 'cdc'
  let currentMode = 'percentile'; // 'percentile' | 'zscore'
  let currentGender = 'Male';
  let multiVisitPoints = []; // [{ ageMonths, weight, height, hc, bmi, date }]

  const getPhModule = () => document.getElementById('ph-module-wrapper');
  const getPhTableBody = () => document.getElementById('ph-table-body');
  const getPhModal = () => document.getElementById('ph-growth-modal');
  const getPhCanvas = () => document.getElementById('ph-growth-canvas');
  const getPhTooltip = () => document.getElementById('ph-canvas-tooltip');

  // --- Row numbering and Reordering ---
  function updatePhRowNumbers() {
    const rows = getPhTableBody()?.querySelectorAll('tr.oh-row') || [];
    rows.forEach((row, index) => {
      const numCell = row.querySelector('.oh-row-no');
      if (numCell) numCell.textContent = String(index + 1);
    });
  }

  function bindPhRowEvents(row) {
    const delBtn = row.querySelector('.oh-del button');
    if (delBtn) {
      delBtn.onclick = (e) => {
        e.preventDefault();
        row.remove();
        updatePhRowNumbers();
      };
    }

    // Quick select vs custom text input
    const quickSelect = row.querySelector('.ph-quick-select');
    const customInput = row.querySelector('.ph-custom-input');
    if (quickSelect && customInput) {
      quickSelect.addEventListener('change', () => {
        if (quickSelect.value === 'custom') {
          customInput.hidden = false;
          customInput.value = '';
          customInput.focus();
        } else {
          customInput.hidden = true;
          customInput.value = quickSelect.value;
        }
      });

      customInput.addEventListener('input', () => {
        // keep updated
      });
    }

    // MUAC auto classification listener
    const muacInput = row.querySelector('.ph-muac-input');
    const muacUnit = row.querySelector('.ph-muac-unit');
    if (muacInput) {
      const updateMuacBadge = () => {
        let val = parseFloat(muacInput.value.trim());
        if (isNaN(val) || val <= 0) {
          const badge = document.getElementById('ph-muac-badge');
          if (badge) {
            badge.textContent = '--';
            badge.style.background = '#e2e8f0';
            badge.style.color = '#64748b';
          }
          return;
        }
        if (muacUnit && muacUnit.value === 'mm') {
          val = val / 10.0;
        }
        if (window.ZimRxGrowthData) {
          const cls = window.ZimRxGrowthData.getMUACClassification(val);
          const badge = document.getElementById('ph-muac-badge');
          if (badge && cls) {
            badge.textContent = cls.category;
            badge.style.background = cls.category === 'Green' ? '#dcfce7' : (cls.category === 'Yellow' ? '#fef3c7' : '#fee2e2');
            badge.style.color = cls.color;
            badge.title = cls.label;
          }
        }
      };
      muacInput.addEventListener('input', updateMuacBadge);
      if (muacUnit) muacUnit.addEventListener('change', updateMuacBadge);
    }
  }

  // --- Add More Row ---
  const addRowBtn = document.getElementById('ph-add-row-btn');
  const rowTemplate = document.getElementById('ph-row-template');
  if (addRowBtn && rowTemplate) {
    addRowBtn.addEventListener('click', () => {
      const clone = rowTemplate.content.cloneNode(true);
      const newRow = clone.querySelector('tr');
      bindPhRowEvents(newRow);
      getPhTableBody()?.appendChild(newRow);
      updatePhRowNumbers();
      newRow.querySelector('.oh-name-input')?.focus();
    });
  }

  // Bind initial rows
  getPhTableBody()?.querySelectorAll('tr.oh-row').forEach(bindPhRowEvents);
  updatePhRowNumbers();

  // --- Extract Live Patient Particulars & Metrics ---
  function getPatientAnthropometrics() {
    const ageVal = document.getElementById('patient-age')?.value || '';
    const ageUnit = document.getElementById('patient-age-unit')?.value || 'Years';
    const dobVal = document.getElementById('patient-dob')?.value || '';
    const genderVal = document.getElementById('patient-gender')?.value || currentGender || 'Male';

    const wtVal = document.getElementById('patient-weight')?.value || '';
    const wtUnit = document.getElementById('patient-weight-unit')?.value || 'kg';

    const htVal = document.getElementById('patient-height')?.value || '';
    const htUnit = document.getElementById('patient-height-unit')?.value || 'inch';

    const ofcInput = document.querySelector('.ph-ofc-input');
    const ofcUnit = document.querySelector('.ph-ofc-unit')?.value || 'cm';
    const ofcVal = ofcInput?.value || '';

    const muacInput = document.querySelector('.ph-muac-input');
    const muacUnit = document.querySelector('.ph-muac-unit')?.value || 'cm';
    const muacVal = muacInput?.value || '';

    // Standardize Age to Months
    let ageMonths = 0;
    if (window.ZimRxGrowthData) {
      ageMonths = window.ZimRxGrowthData.getAgeInMonths(ageVal, ageUnit, dobVal);
    } else {
      const numAge = parseFloat(ageVal) || 0;
      ageMonths = ageUnit.startsWith('m') ? numAge : (ageUnit.startsWith('d') ? numAge / 30.4 : numAge * 12);
    }

    // Standardize Weight to kg
    let weightKg = parseFloat(wtVal);
    if (!isNaN(weightKg) && wtUnit === 'lb') {
      weightKg = weightKg * 0.45359237;
    }

    // Standardize Height to cm
    let heightCm = parseFloat(htVal);
    if (!isNaN(heightCm)) {
      if (htUnit === 'inch') heightCm = heightCm * 2.54;
      else if (htUnit === 'feet') heightCm = heightCm * 30.48;
      else if (htUnit === 'meter') heightCm = heightCm * 100.0;
    }

    // Standardize Head Circumference to cm
    let hcCm = parseFloat(ofcVal);
    if (!isNaN(hcCm) && ofcUnit === 'inch') {
      hcCm = hcCm * 2.54;
    }

    // Standardize MUAC to cm
    let muacCm = parseFloat(muacVal);
    if (!isNaN(muacCm) && muacUnit === 'mm') {
      muacCm = muacCm / 10.0;
    }

    // Calculate BMI
    let bmi = null;
    if (!isNaN(weightKg) && weightKg > 0 && !isNaN(heightCm) && heightCm > 0) {
      const heightM = heightCm / 100.0;
      bmi = Math.round((weightKg / (heightM * heightM)) * 10) / 10;
    }

    const isBoy = !genderVal.toLowerCase().startsWith('f');

    return {
      ageVal,
      ageUnit,
      dobVal,
      ageMonths: Math.round(ageMonths * 10) / 10,
      gender: isBoy ? 'Male' : 'Female',
      weightKg: !isNaN(weightKg) && weightKg > 0 ? Math.round(weightKg * 100) / 100 : null,
      heightCm: !isNaN(heightCm) && heightCm > 0 ? Math.round(heightCm * 10) / 10 : null,
      hcCm: !isNaN(hcCm) && hcCm > 0 ? Math.round(hcCm * 10) / 10 : null,
      muacCm: !isNaN(muacCm) && muacCm > 0 ? Math.round(muacCm * 10) / 10 : null,
      bmi: bmi
    };
  }

  function refreshPaediatricPatientData() {
    const data = getPatientAnthropometrics();
    currentGender = data.gender;

    // Update gender toggle button
    document.querySelectorAll('.ph-gender-btn').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.phGender === currentGender);
    });

    // Update stats bar
    const ageEl = document.getElementById('ph-stat-age');
    const ageMoEl = document.getElementById('ph-stat-age-months');
    if (ageEl) {
      ageEl.textContent = data.ageVal ? `${data.ageVal} ${data.ageUnit}` : (data.dobVal || '--');
    }
    if (ageMoEl) {
      ageMoEl.textContent = `${data.ageMonths} months`;
    }

    const wtEl = document.getElementById('ph-stat-wt');
    if (wtEl) wtEl.textContent = data.weightKg ? `${data.weightKg} kg` : '--';

    const htEl = document.getElementById('ph-stat-ht');
    if (htEl) htEl.textContent = data.heightCm ? `${data.heightCm} cm` : '--';

    const hcEl = document.getElementById('ph-stat-hc');
    if (hcEl) hcEl.textContent = data.hcCm ? `${data.hcCm} cm` : '--';

    const bmiEl = document.getElementById('ph-stat-bmi');
    if (bmiEl) bmiEl.textContent = data.bmi ? `${data.bmi} kg/m²` : '--';

    const muacEl = document.getElementById('ph-stat-muac');
    if (muacEl) muacEl.textContent = data.muacCm ? `${data.muacCm} cm` : '--';

    // Calculate classifications & badges
    if (window.ZimRxGrowthData) {
      // 1. Weight for age
      if (data.weightKg && data.ageMonths >= 0) {
        const res = window.ZimRxGrowthData.calculateZScore(data.weightKg, data.ageMonths, currentGender, 'weight', currentStandard);
        const cls = window.ZimRxGrowthData.getGrowthClassification(res);
        const b = document.getElementById('ph-badge-wfa');
        if (b && res) {
          b.textContent = `${res.percentile}th % (${res.zScore > 0 ? '+' : ''}${res.zScore} SD)`;
          b.style.background = cls.status === 'normal' ? '#dcfce7' : (cls.status === 'warning' ? '#fef3c7' : '#fee2e2');
          b.style.color = cls.color;
        }
      }

      // 2. Height for age
      if (data.heightCm && data.ageMonths >= 0) {
        const res = window.ZimRxGrowthData.calculateZScore(data.heightCm, data.ageMonths, currentGender, 'height', currentStandard);
        const cls = window.ZimRxGrowthData.getGrowthClassification(res);
        const b = document.getElementById('ph-badge-hfa');
        if (b && res) {
          b.textContent = `${res.percentile}th % (${res.zScore > 0 ? '+' : ''}${res.zScore} SD)`;
          b.style.background = cls.status === 'normal' ? '#dcfce7' : (cls.status === 'warning' ? '#fef3c7' : '#fee2e2');
          b.style.color = cls.color;
        }
      }

      // 3. Head circumference for age
      if (data.hcCm && data.ageMonths >= 0) {
        const res = window.ZimRxGrowthData.calculateZScore(data.hcCm, data.ageMonths, currentGender, 'hc', 'who');
        const cls = window.ZimRxGrowthData.getGrowthClassification(res);
        const b = document.getElementById('ph-badge-hcfa');
        if (b && res) {
          b.textContent = `${res.percentile}th % (${res.zScore > 0 ? '+' : ''}${res.zScore} SD)`;
          b.style.background = cls.status === 'normal' ? '#dcfce7' : '#fee2e2';
          b.style.color = cls.color;
        }
      }

      // 4. BMI for age
      if (data.bmi && data.ageMonths >= 0) {
        const res = window.ZimRxGrowthData.calculateZScore(data.bmi, data.ageMonths, currentGender, 'bmi', currentStandard);
        const cls = window.ZimRxGrowthData.getGrowthClassification(res);
        const b = document.getElementById('ph-badge-bmifa');
        if (b && res) {
          b.textContent = `${res.percentile}th % (${res.zScore > 0 ? '+' : ''}${res.zScore} SD)`;
          b.style.background = cls.status === 'normal' ? '#dcfce7' : (cls.status === 'warning' ? '#fef3c7' : '#fee2e2');
          b.style.color = cls.color;
        }
      }

      // 5. MUAC
      if (data.muacCm) {
        const cls = window.ZimRxGrowthData.getMUACClassification(data.muacCm, data.ageMonths);
        const b = document.getElementById('ph-badge-muac');
        if (b && cls) {
          b.textContent = cls.category + (cls.isOutOfStandardAge ? '*' : '');
          b.style.background = cls.category === 'Green' ? '#dcfce7' : (cls.category === 'Yellow' ? '#fef3c7' : '#fee2e2');
          b.style.color = cls.color;
          b.title = cls.label;
        }
      }
    }

    renderInterpretation(data);
    renderGrowthChart();
  }

  // --- Clinical Interpretation Box ---
  function renderInterpretation(data) {
    if (!window.ZimRxGrowthData) return;
    const pt = data || getPatientAnthropometrics();

    let targetVal = null;
    let metricName = 'weight';
    if (currentMetric === 'weight') { targetVal = pt.weightKg; metricName = 'weight'; }
    else if (currentMetric === 'height') { targetVal = pt.heightCm; metricName = 'height'; }
    else if (currentMetric === 'hc') { targetVal = pt.hcCm; metricName = 'hc'; }
    else if (currentMetric === 'bmi') { targetVal = pt.bmi; metricName = 'bmi'; }

    const res = targetVal ? window.ZimRxGrowthData.calculateZScore(targetVal, pt.ageMonths, currentGender, metricName, currentStandard) : null;
    const cls = res ? window.ZimRxGrowthData.getGrowthClassification(res) : null;

    const growthEl = document.getElementById('ph-ii-growth');
    const pctEl = document.getElementById('ph-ii-pct');
    const zscoreEl = document.getElementById('ph-ii-zscore');
    const nutEl = document.getElementById('ph-ii-nutrition');

    if (res && cls) {
      if (growthEl) {
        growthEl.textContent = cls.label;
        growthEl.style.color = cls.color;
      }
      if (pctEl) pctEl.textContent = `${res.percentile}th Percentile (${res.standard})`;
      if (zscoreEl) zscoreEl.textContent = `${res.zScore > 0 ? '+' : ''}${res.zScore} SD (Median: ${res.median})`;
      if (nutEl) {
        if (res.zScore >= -2 && res.zScore <= 1) nutEl.textContent = 'Normal / Adequate Growth';
        else if (res.zScore < -2) nutEl.textContent = 'Undernourished / Growth Faltering';
        else nutEl.textContent = 'Excess Weight / Overnutrition';
      }
    } else {
      if (growthEl) { growthEl.textContent = 'Enter patient age & anthropometrics to calculate'; growthEl.style.color = '#64748b'; }
      if (pctEl) pctEl.textContent = '--';
      if (zscoreEl) zscoreEl.textContent = '--';
      if (nutEl) nutEl.textContent = '--';
    }
  }

  // --- Growth Chart Canvas Vector Plotting Engine ---
  function renderGrowthChart() {
    const canvas = getPhCanvas();
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Retina display scaling
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const displayWidth = rect.width || 980;
    const displayHeight = rect.height || 460;

    canvas.width = displayWidth * dpr;
    canvas.height = displayHeight * dpr;
    ctx.scale(dpr, dpr);

    const W = displayWidth;
    const H = displayHeight;

    ctx.clearRect(0, 0, W, H);

    const pt = getPatientAnthropometrics();
    const isBoy = currentGender === 'Male';

    // Determine Dataset & Boundaries
    let activeStandard = currentStandard;
    if (activeStandard === 'auto') {
      activeStandard = pt.ageMonths <= 60 ? 'who' : 'cdc';
      if (currentMetric === 'hc') activeStandard = 'who';
    }

    const isWho = activeStandard === 'who';
    const maxAge = isWho ? 60 : 240;
    const minAge = isWho ? 0 : 24;

    const gSuffix = isBoy ? '_boys' : '_girls';
    let dsKey = '';
    if (currentMetric === 'weight') dsKey = 'wfa' + gSuffix;
    else if (currentMetric === 'height') dsKey = (isWho ? 'lhfa' : 'hfa') + gSuffix;
    else if (currentMetric === 'hc') { dsKey = 'hcfa' + gSuffix; activeStandard = 'who'; }
    else if (currentMetric === 'bmi') dsKey = 'bmifa' + gSuffix;

    const store = (isWho ? window.ZimRxGrowthData?.WHO : window.ZimRxGrowthData?.CDC) || {};
    const dataset = store[dsKey] || [];

    if (!dataset || dataset.length === 0) {
      ctx.fillStyle = '#64748b';
      ctx.font = '14px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Growth dataset loading...', W / 2, H / 2);
      return;
    }

    // Determine Y-range
    let minY = Infinity;
    let maxY = -Infinity;
    dataset.forEach((row) => {
      const top = currentMode === 'percentile' ? (row.p97 || row.sd_plus_3 || row.M * 1.5) : (row.sd_plus_3 || row.M * 1.6);
      const btm = currentMode === 'percentile' ? (row.p3 || row.sd_minus_3 || row.M * 0.5) : (row.sd_minus_3 || row.M * 0.4);
      if (top > maxY) maxY = top;
      if (btm < minY) minY = btm;
    });

    // Check if patient point is out of standard range and pad
    let currentVal = null;
    if (currentMetric === 'weight') currentVal = pt.weightKg;
    else if (currentMetric === 'height') currentVal = pt.heightCm;
    else if (currentMetric === 'hc') currentVal = pt.hcCm;
    else if (currentMetric === 'bmi') currentVal = pt.bmi;

    if (currentVal) {
      if (currentVal > maxY) maxY = currentVal * 1.08;
      if (currentVal < minY) minY = currentVal * 0.92;
    }

    // Margin & Plot Area
    const margin = { top: 40, right: 60, bottom: 50, left: 65 };
    const plotW = W - margin.left - margin.right;
    const plotH = H - margin.top - margin.bottom;

    const mapX = (age) => margin.left + ((age - minAge) / (maxAge - minAge)) * plotW;
    const mapY = (val) => margin.top + plotH - ((val - minY) / (maxY - minY)) * plotH;

    // Background & Card Border
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, W, H);

    // Normal Zone Shading (Between 3rd and 97th percentile / -2SD and +2SD)
    const topKey = currentMode === 'percentile' ? 'p97' : 'sd_plus_2';
    const btmKey = currentMode === 'percentile' ? 'p3' : 'sd_minus_2';
    const midKey = currentMode === 'percentile' ? 'p50' : 'sd_0';

    ctx.beginPath();
    dataset.forEach((row, i) => {
      const x = mapX(row.m);
      const yTop = mapY(row[topKey] || row.M);
      if (i === 0) ctx.moveTo(x, yTop);
      else ctx.lineTo(x, yTop);
    });
    for (let i = dataset.length - 1; i >= 0; i--) {
      const row = dataset[i];
      const x = mapX(row.m);
      const yBtm = mapY(row[btmKey] || row.M);
      ctx.lineTo(x, yBtm);
    }
    ctx.closePath();
    ctx.fillStyle = isBoy ? 'rgba(224, 242, 254, 0.45)' : 'rgba(252, 231, 243, 0.45)';
    ctx.fill();

    // Gridlines - Horizontal (Y)
    const yTicksCount = 8;
    const yStep = (maxY - minY) / yTicksCount;
    ctx.strokeStyle = '#f1f5f9';
    ctx.lineWidth = 1;
    ctx.fillStyle = '#64748b';
    ctx.font = '11px Inter, sans-serif';
    ctx.textAlign = 'right';

    for (let i = 0; i <= yTicksCount; i++) {
      const val = minY + i * yStep;
      const y = mapY(val);
      ctx.beginPath();
      ctx.moveTo(margin.left, y);
      ctx.lineTo(margin.left + plotW, y);
      ctx.stroke();

      const unit = currentMetric === 'weight' ? 'kg' : (currentMetric === 'height' || currentMetric === 'hc' ? 'cm' : '');
      ctx.fillText(`${val.toFixed(1)} ${unit}`, margin.left - 10, y + 4);
    }

    // Gridlines - Vertical (X: Age in months/years)
    const xStepMonths = isWho ? 6 : 24;
    ctx.textAlign = 'center';
    for (let m = minAge; m <= maxAge; m += xStepMonths) {
      const x = mapX(m);
      ctx.beginPath();
      ctx.moveTo(x, margin.top);
      ctx.lineTo(x, margin.top + plotH);
      ctx.strokeStyle = (m % 12 === 0) ? '#e2e8f0' : '#f8fafc';
      ctx.stroke();

      let label = `${m}m`;
      if (m >= 12 && m % 12 === 0) {
        label = `${m / 12}y`;
      }
      ctx.fillStyle = (m % 12 === 0) ? '#0f172a' : '#64748b';
      ctx.font = (m % 12 === 0) ? '600 11px Inter, sans-serif' : '11px Inter, sans-serif';
      ctx.fillText(label, x, margin.top + plotH + 18);
    }

    // Axis Labels
    ctx.fillStyle = '#475569';
    ctx.font = '600 12px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`Age (${isWho ? 'Months / Years' : 'Years'})`, margin.left + plotW / 2, H - 12);

    ctx.save();
    ctx.translate(16, margin.top + plotH / 2);
    ctx.rotate(-Math.PI / 2);
    const metricTitle = currentMetric === 'weight' ? 'Weight (kg)' : (currentMetric === 'height' ? 'Length / Height (cm)' : (currentMetric === 'hc' ? 'Head Circumference (cm)' : 'BMI (kg/m²)'));
    ctx.fillText(`${metricTitle} [${activeStandard.toUpperCase()}]`, 0, 0);
    ctx.restore();

    // Draw Curves
    const curves = currentMode === 'percentile'
      ? [
          { key: 'p97', label: '97th', color: isBoy ? '#0284c7' : '#db2777', width: 1.5, dash: [4, 4] },
          { key: 'p85', label: '85th', color: isBoy ? '#38bdf8' : '#f472b6', width: 1, dash: [2, 2] },
          { key: 'p50', label: '50th (Median)', color: isBoy ? '#0369a1' : '#be185d', width: 2.5, dash: [] },
          { key: 'p15', label: '15th', color: isBoy ? '#38bdf8' : '#f472b6', width: 1, dash: [2, 2] },
          { key: 'p3', label: '3rd', color: isBoy ? '#0284c7' : '#db2777', width: 1.5, dash: [4, 4] }
        ]
      : [
          { key: 'sd_plus_3', label: '+3 SD', color: '#dc2626', width: 1, dash: [4, 4] },
          { key: 'sd_plus_2', label: '+2 SD (97%)', color: '#ea580c', width: 1.5, dash: [3, 3] },
          { key: 'sd_plus_1', label: '+1 SD (85%)', color: '#16a34a', width: 1, dash: [2, 2] },
          { key: 'sd_0', label: 'Median (0 SD)', color: '#0f172a', width: 2.5, dash: [] },
          { key: 'sd_minus_1', label: '-1 SD (15%)', color: '#16a34a', width: 1, dash: [2, 2] },
          { key: 'sd_minus_2', label: '-2 SD (3%)', color: '#ea580c', width: 1.5, dash: [3, 3] },
          { key: 'sd_minus_3', label: '-3 SD', color: '#dc2626', width: 1, dash: [4, 4] }
        ];

    curves.forEach((curve) => {
      ctx.beginPath();
      ctx.setLineDash(curve.dash);
      ctx.lineWidth = curve.width;
      ctx.strokeStyle = curve.color;

      dataset.forEach((row, idx) => {
        const x = mapX(row.m);
        const y = mapY(row[curve.key] || row.M);
        if (idx === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();

      // Right-side curve label
      const lastRow = dataset[dataset.length - 1];
      const lastX = mapX(lastRow.m);
      const lastY = mapY(lastRow[curve.key] || lastRow.M);
      ctx.fillStyle = curve.color;
      ctx.font = '10px Inter, sans-serif';
      ctx.textAlign = 'left';
      ctx.fillText(curve.label, lastX + 6, lastY + 3);
    });

    ctx.setLineDash([]); // reset dash

    // Plot Historical Visit Trajectory Points
    if (multiVisitPoints.length > 0) {
      ctx.beginPath();
      ctx.strokeStyle = '#8b5cf6';
      ctx.lineWidth = 2;
      let hasStarted = false;

      multiVisitPoints.forEach((ptItem) => {
        let v = null;
        if (currentMetric === 'weight') v = ptItem.weight;
        else if (currentMetric === 'height') v = ptItem.height;
        else if (currentMetric === 'hc') v = ptItem.hc;
        else if (currentMetric === 'bmi') v = ptItem.bmi;

        if (v && ptItem.ageMonths >= minAge && ptItem.ageMonths <= maxAge) {
          const px = mapX(ptItem.ageMonths);
          const py = mapY(v);
          if (!hasStarted) { ctx.moveTo(px, py); hasStarted = true; }
          else { ctx.lineTo(px, py); }
        }
      });
      if (hasStarted) ctx.stroke();

      multiVisitPoints.forEach((ptItem) => {
        let v = null;
        if (currentMetric === 'weight') v = ptItem.weight;
        else if (currentMetric === 'height') v = ptItem.height;
        else if (currentMetric === 'hc') v = ptItem.hc;
        else if (currentMetric === 'bmi') v = ptItem.bmi;

        if (v && ptItem.ageMonths >= minAge && ptItem.ageMonths <= maxAge) {
          const px = mapX(ptItem.ageMonths);
          const py = mapY(v);
          ctx.beginPath();
          ctx.arc(px, py, 4.5, 0, Math.PI * 2);
          ctx.fillStyle = '#8b5cf6';
          ctx.fill();
          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 1.5;
          ctx.stroke();
        }
      });
    }

    // Plot Current Patient Point with Glowing Target Marker
    if (currentVal && pt.ageMonths >= minAge && pt.ageMonths <= maxAge) {
      const ptX = mapX(pt.ageMonths);
      const ptY = mapY(currentVal);

      // Outer glow circle
      ctx.beginPath();
      ctx.arc(ptX, ptY, 12, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(239, 68, 68, 0.2)';
      ctx.fill();

      // Middle ring
      ctx.beginPath();
      ctx.arc(ptX, ptY, 7, 0, Math.PI * 2);
      ctx.fillStyle = '#ef4444';
      ctx.fill();
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 2;
      ctx.stroke();

      // Center dot
      ctx.beginPath();
      ctx.arc(ptX, ptY, 2.5, 0, Math.PI * 2);
      ctx.fillStyle = '#ffffff';
      ctx.fill();

      // Callout Pin Badge
      const calloutText = `${currentVal} ${currentMetric === 'weight' ? 'kg' : (currentMetric === 'height' || currentMetric === 'hc' ? 'cm' : '')}`;
      ctx.font = 'bold 11px Inter, sans-serif';
      const textW = ctx.measureText(calloutText).width + 12;

      ctx.fillStyle = '#0f172a';
      ctx.beginPath();
      const bx = Math.min(W - margin.right - textW, Math.max(margin.left, ptX - textW / 2));
      const by = ptY - 28;
      ctx.roundRect ? ctx.roundRect(bx, by, textW, 20, 4) : ctx.fillRect(bx, by, textW, 20);
      ctx.fill();

      ctx.fillStyle = '#ffffff';
      ctx.textAlign = 'center';
      ctx.fillText(calloutText, bx + textW / 2, by + 14);
    }

    // Store coordinate mapping for interactive tooltip
    canvas.__growthMap = { mapX, mapY, minAge, maxAge, minY, maxY, dataset, margin, plotW, plotH };
  }

  // --- Interactive Canvas Hover Tooltip ---
  const canvas = getPhCanvas();
  const tooltip = getPhTooltip();
  if (canvas && tooltip) {
    canvas.addEventListener('mousemove', (e) => {
      const map = canvas.__growthMap;
      if (!map) return;

      const rect = canvas.getBoundingClientRect();
      const mouseX = e.clientX - rect.left;
      const mouseY = e.clientY - rect.top;

      if (mouseX < map.margin.left || mouseX > map.margin.left + map.plotW ||
          mouseY < map.margin.top || mouseY > map.margin.top + map.plotH) {
        tooltip.hidden = true;
        return;
      }

      const fracX = (mouseX - map.margin.left) / map.plotW;
      const ageMonths = Math.round(map.minAge + fracX * (map.maxAge - map.minAge));

      if (window.ZimRxGrowthData) {
        const pt = getPatientAnthropometrics();
        const lms = window.ZimRxGrowthData.getLmsAtAge(map.dataset, ageMonths);
        if (lms) {
          const mVal = Math.round(lms.M * 10) / 10;
          const unit = currentMetric === 'weight' ? 'kg' : (currentMetric === 'height' || currentMetric === 'hc' ? 'cm' : '');
          tooltip.innerHTML = `
            <strong>Age: ${ageMonths} months (${(ageMonths / 12).toFixed(1)} yrs)</strong>
            <div>Median (50th %): <b>${mVal} ${unit}</b></div>
            <div>3rd %ile: <b>${(mVal * (1 - 2 * lms.S)).toFixed(1)} ${unit}</b></div>
            <div>97th %ile: <b>${(mVal * (1 + 2 * lms.S)).toFixed(1)} ${unit}</b></div>
          `;
          tooltip.hidden = false;
          tooltip.style.left = `${Math.min(rect.width - 160, mouseX + 15)}px`;
          tooltip.style.top = `${Math.max(10, mouseY - 60)}px`;
        }
      }
    });

    canvas.addEventListener('mouseleave', () => {
      tooltip.hidden = true;
    });
  }

  // --- Modal Open & Close Triggers ---
  const openModalBtn = document.getElementById('ph-open-growth-chart-btn');
  const modal = getPhModal();

  function openGrowthModal() {
    if (!modal) return;
    modal.hidden = false;
    refreshPaediatricPatientData();
    window.setTimeout(() => {
      renderGrowthChart();
    }, 50);
  }

  function closeGrowthModal() {
    if (!modal) return;
    modal.hidden = true;
  }

  openModalBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    openGrowthModal();
  });

  document.querySelectorAll('[data-ph-growth-close]').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      closeGrowthModal();
    });
  });

  // --- Gender Toggle Buttons ---
  document.querySelectorAll('.ph-gender-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      currentGender = btn.dataset.phGender;
      document.querySelectorAll('.ph-gender-btn').forEach((b) => b.classList.toggle('active', b === btn));
      const genderSelect = document.getElementById('patient-gender');
      if (genderSelect) genderSelect.value = currentGender;
      refreshPaediatricPatientData();
    });
  });

  // --- Metric Tabs ---
  document.querySelectorAll('#ph-metric-tabs .ph-tab-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#ph-metric-tabs .ph-tab-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentMetric = btn.dataset.metric;
      refreshPaediatricPatientData();
    });
  });

  // --- Standard Selector Buttons (Auto / WHO / CDC) ---
  document.querySelectorAll('#ph-standard-group .ph-group-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#ph-standard-group .ph-group-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentStandard = btn.dataset.standard;
      refreshPaediatricPatientData();
    });
  });

  // --- Curve Mode Selector (Percentiles / Z-Scores) ---
  document.querySelectorAll('#ph-curve-mode-group .ph-group-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#ph-curve-mode-group .ph-group-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentMode = btn.dataset.mode;
      renderGrowthChart();
    });
  });

  // --- Multi-visit Trajectory Tracker ---
  const addVisitBtn = document.getElementById('ph-add-visit-pt-btn');
  const visitList = document.getElementById('ph-visit-points-list');

  function renderVisitPointsList() {
    if (!visitList) return;
    if (multiVisitPoints.length === 0) {
      visitList.innerHTML = '<div class="ph-no-visits">No previous points added yet.</div>';
      return;
    }

    visitList.innerHTML = multiVisitPoints.map((pt, idx) => `
      <div class="ph-visit-item" data-idx="${idx}">
        <span class="ph-vi-age">${pt.ageMonths} mo:</span>
        <span class="ph-vi-val">${pt.weight ? pt.weight + 'kg' : ''} ${pt.height ? pt.height + 'cm' : ''}</span>
        <button type="button" class="ph-vi-del" data-del-idx="${idx}" title="Remove Point">&times;</button>
      </div>
    `).join('');

    visitList.querySelectorAll('.ph-vi-del').forEach((btn) => {
      btn.onclick = () => {
        const idx = parseInt(btn.dataset.delIdx, 10);
        multiVisitPoints.splice(idx, 1);
        renderVisitPointsList();
        renderGrowthChart();
      };
    });
  }

  addVisitBtn?.addEventListener('click', () => {
    const agePrompt = prompt('Enter Age in Months for previous visit:', '6');
    if (!agePrompt) return;
    const ageM = parseFloat(agePrompt);
    if (isNaN(ageM) || ageM < 0) return;

    const wtPrompt = prompt('Enter Weight (kg) [Leave blank to skip]:', '');
    const htPrompt = prompt('Enter Height (cm) [Leave blank to skip]:', '');
    const hcPrompt = prompt('Enter Head Circumference (cm) [Leave blank to skip]:', '');

    multiVisitPoints.push({
      ageMonths: ageM,
      weight: wtPrompt ? parseFloat(wtPrompt) : null,
      height: htPrompt ? parseFloat(htPrompt) : null,
      hc: hcPrompt ? parseFloat(hcPrompt) : null,
      date: new Date().toLocaleDateString('en-GB')
    });
    multiVisitPoints.sort((a, b) => a.ageMonths - b.ageMonths);
    renderVisitPointsList();
    renderGrowthChart();
  });

  renderVisitPointsList();

  // --- Insert Summary into Prescription Note ---
  const insertBtn = document.getElementById('ph-insert-summary-btn');
  insertBtn?.addEventListener('click', () => {
    const pt = getPatientAnthropometrics();
    if (!window.ZimRxGrowthData) return;

    let summaryParts = [];
    summaryParts.push(`[Paediatric Assessment (${pt.gender}, ${pt.ageMonths} mo)]`);

    if (pt.weightKg) {
      const z = window.ZimRxGrowthData.calculateZScore(pt.weightKg, pt.ageMonths, pt.gender, 'weight', currentStandard);
      const c = window.ZimRxGrowthData.getGrowthClassification(z);
      summaryParts.push(`• Weight: ${pt.weightKg} kg (${z?.percentile}th %ile, Z: ${z?.zScore} SD - ${c?.label})`);
    }

    if (pt.heightCm) {
      const z = window.ZimRxGrowthData.calculateZScore(pt.heightCm, pt.ageMonths, pt.gender, 'height', currentStandard);
      const c = window.ZimRxGrowthData.getGrowthClassification(z);
      summaryParts.push(`• Height/Length: ${pt.heightCm} cm (${z?.percentile}th %ile, Z: ${z?.zScore} SD - ${c?.label})`);
    }

    if (pt.hcCm) {
      const z = window.ZimRxGrowthData.calculateZScore(pt.hcCm, pt.ageMonths, pt.gender, 'hc', 'who');
      const c = window.ZimRxGrowthData.getGrowthClassification(z);
      summaryParts.push(`• Head Circ (OFC): ${pt.hcCm} cm (${z?.percentile}th %ile - ${c?.label})`);
    }

    if (pt.muacCm) {
      const c = window.ZimRxGrowthData.getMUACClassification(pt.muacCm, pt.ageMonths);
      summaryParts.push(`• MUAC: ${pt.muacCm} cm (${c?.category} - ${c?.label})`);
    }

    if (pt.bmi) {
      const z = window.ZimRxGrowthData.calculateZScore(pt.bmi, pt.ageMonths, pt.gender, 'bmi', currentStandard);
      summaryParts.push(`• BMI: ${pt.bmi} kg/m² (${z?.percentile}th %ile)`);
    }

    const fullText = summaryParts.join('\n');

    // Find Note or Plan module textarea
    const noteArea = document.querySelector('#note-textarea, .note-textarea, textarea[name="note"], .js-note-module textarea, .nicEdit-main');
    if (noteArea) {
      if (noteArea.isContentEditable) {
        noteArea.innerHTML += (noteArea.innerHTML ? '<br>' : '') + fullText.replace(/\n/g, '<br>');
      } else {
        noteArea.value = (noteArea.value ? noteArea.value + '\n\n' : '') + fullText;
      }
      alert('Growth chart summary inserted into prescription notes successfully!');
    } else {
      alert(fullText);
    }
  });

  // --- Print Chart Button ---
  const printChartBtn = document.getElementById('ph-print-chart-btn');
  printChartBtn?.addEventListener('click', () => {
    const canvas = getPhCanvas();
    if (!canvas) return;

    const pt = getPatientAnthropometrics();
    const dataUrl = canvas.toDataURL('image/png');
    const ptName = document.getElementById('patient-name')?.value || 'Patient';
    const regNo = document.getElementById('patient-reg-no')?.value || '';
    const dateStr = document.getElementById('patient-date')?.value || new Date().toLocaleDateString('en-GB');

    const printWin = window.open('', '_blank');
    if (!printWin) {
      alert('Please allow popups to print growth chart');
      return;
    }

    printWin.document.write(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>Paediatric Growth Chart - ${ptName}</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; color: #0f172a; }
          .header { border-bottom: 2px solid #0284c7; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
          .title { font-size: 20px; font-weight: 700; color: #0284c7; margin: 0; }
          .meta { font-size: 13px; color: #475569; }
          .chart-img { width: 100%; max-height: 600px; object-fit: contain; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 20px; }
          .summary { font-size: 13px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 16px; border-radius: 6px; }
          @media print { body { margin: 0; } .header { margin-top: 0; } }
        </style>
      </head>
      <body>
        <div class="header">
          <div>
            <h1 class="title">Paediatric Growth Chart (${currentStandard.toUpperCase()})</h1>
            <div class="meta">Patient: <strong>${ptName}</strong> ${regNo ? `| Reg: <strong>${regNo}</strong>` : ''} | Date: ${dateStr}</div>
          </div>
          <div class="meta">
            Age: <strong>${pt.ageVal} ${pt.ageUnit}</strong> (${pt.ageMonths} mo) | Sex: <strong>${pt.gender}</strong>
          </div>
        </div>
        <img class="chart-img" src="${dataUrl}">
        <div class="summary">
          <strong>Anthropometric Summary:</strong>
          Weight: ${pt.weightKg ? pt.weightKg + ' kg' : '--'} | Height: ${pt.heightCm ? pt.heightCm + ' cm' : '--'} | Head Circumference: ${pt.hcCm ? pt.hcCm + ' cm' : '--'} | BMI: ${pt.bmi ? pt.bmi + ' kg/m²' : '--'} | MUAC: ${pt.muacCm ? pt.muacCm + ' cm' : '--'}
        </div>
        <script>
          window.onload = function() { window.print(); };
        </script>
      </body>
      </html>
    `);
    printWin.document.close();
  });

  // Watch for patient particulars changes to auto update
  ['patient-age', 'patient-age-unit', 'patient-dob', 'patient-gender', 'patient-weight', 'patient-height'].forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', refreshPaediatricPatientData);
      el.addEventListener('change', refreshPaediatricPatientData);
    }
  });

  // Trigger initial calculation
  refreshPaediatricPatientData();
}

// Auto boot on DOM ready or boot.js invocation
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPaediatricModule);
} else {
  initPaediatricModule();
}
