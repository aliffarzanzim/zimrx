// Electronic Medical Record (EMR) longitudinal dashboard, encounter drawer, vitals graphing, and analytics.
const emrConfig = (() => {
    const el = document.getElementById('emrConfigData');
    return el ? JSON.parse(el.textContent || '{}') : {};
})();
const emrPatientId = Number(emrConfig.patientId || 0);
const emrFirstPastVisitId = String(emrConfig.firstPastVisitId || '');

// Encounter drawer and past visit details
function openPastRxDrawer(visitIdentifier) {
    const drawer = document.getElementById('emr-past-rx-drawer');
    const overlay = document.getElementById('emr-drawer-overlay');
    const content = document.getElementById('emr-drawer-content');
    
    if (drawer && overlay) {
        drawer.classList.add('open');
        overlay.classList.add('open');
        content.replaceChildren();
        const loadingDiv = document.createElement('div');
        loadingDiv.style.cssText = 'text-align: center; color: #94a3b8; padding: 2rem;';
        loadingDiv.textContent = 'Loading encounter data...';
        content.appendChild(loadingDiv);

        fetch('api/emr_api.php?action=get_visit_details&visit_id=' + encodeURIComponent(visitIdentifier))
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.visit) {
                    content.replaceChildren();
                    const errDiv = document.createElement('div');
                    errDiv.style.cssText = 'color: #dc2626; padding: 1.5rem;';
                    errDiv.textContent = 'Could not load past prescription details.';
                    content.appendChild(errDiv);
                    return;
                }

                content.replaceChildren();

                const appendText = (parent, tag, value, className = '', style = '') => {
                    const node = document.createElement(tag);
                    if (className) node.className = className;
                    if (style) node.style.cssText = style;
                    node.textContent = String(value ?? '');
                    parent.appendChild(node);
                    return node;
                };

                const visit = data.visit ?? {};
                const heading = document.createElement('div');
                heading.style.cssText = 'margin-bottom: 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.85rem;';
                appendText(heading, 'h4', 'Encounter ' + (visit.visit_id || ('V' + (visit.id ?? ''))), '', 'margin: 0 0 0.35rem; font-size: 1.1rem; color: #0f172a;');
                appendText(heading, 'div', 'Date: ' + (visit.visit_date ?? ''), '', 'font-size: 0.82rem; color: #64748b;');
                content.appendChild(heading);

                if (Array.isArray(data.drugs) && data.drugs.length > 0) {
                    appendText(content, 'h5', 'Prescribed Medications (Rx)', '', 'font-size: 0.85rem; text-transform: uppercase; color: #475569; margin: 1rem 0 0.5rem;');
                    const list = document.createElement('ul');
                    list.style.cssText = 'padding-left: 1.25rem; font-size: 0.88rem; line-height: 1.6;';

                    for (const drug of data.drugs) {
                        const item = document.createElement('li');
                        appendText(item, 'strong', drug.drug_name || drug.brand_name || drug.generic_name || '');
                        appendText(item, 'span', ' (' + (drug.form || '') + ' ' + (drug.strength || '') + ')');
                        item.appendChild(document.createElement('br'));
                        appendText(item, 'span', (drug.dosage || drug.dose || '') + ' - ' + (drug.duration || '') + ' - ' + (drug.instructions || drug.instruction || ''), '', 'color: #64748b; font-size: 0.82rem;');
                        list.appendChild(item);
                    }

                    content.appendChild(list);
                }

                if (data.prescription && data.prescription.module_json) {
                    try {
                        const mod = JSON.parse(data.prescription.module_json);
                        if (Array.isArray(mod.chief_complaints) && mod.chief_complaints.length > 0) {
                            appendText(content, 'h5', 'Chief Complaints', '', 'font-size: 0.85rem; text-transform: uppercase; color: #475569; margin: 1rem 0 0.5rem;');
                            appendText(content, 'p', mod.chief_complaints.map(c => c.name || '').filter(Boolean).join(', '), '', 'font-size: 0.86rem; color: #1e293b;');
                        }
                        if (Array.isArray(mod.diagnosis) && mod.diagnosis.length > 0) {
                            appendText(content, 'h5', 'Diagnosis', '', 'font-size: 0.85rem; text-transform: uppercase; color: #475569; margin: 1rem 0 0.5rem;');
                            appendText(content, 'p', mod.diagnosis.map(d => d.name || '').filter(Boolean).join(', '), '', 'font-size: 0.86rem; color: #1e293b;');
                        }
                    } catch(e) {}
                }
            })
            .catch(err => {
                content.replaceChildren();
                const errDiv = document.createElement('div');
                errDiv.style.cssText = 'color: #dc2626; padding: 1.5rem;';
                errDiv.textContent = 'Error fetching encounter: ' + err.message;
                content.appendChild(errDiv);
            });
    }
}

function closePastRxDrawer() {
    const drawer = document.getElementById('emr-past-rx-drawer');
    const overlay = document.getElementById('emr-drawer-overlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
}

function openEditDemographicsModal() {
    const modal = document.getElementById('emr-edit-demographics-modal');
    if (modal) modal.classList.add('open');
}

function closeEditDemographicsModal() {
    const modal = document.getElementById('emr-edit-demographics-modal');
    if (modal) modal.classList.remove('open');
}

document.addEventListener('DOMContentLoaded', () => {
    // Start New Visit handler
    const btnStartVisit = document.getElementById('btn-start-visit');
    if (btnStartVisit) {
        btnStartVisit.addEventListener('click', () => {
            const patientId = btnStartVisit.dataset.patientId;
            const regNo = btnStartVisit.dataset.reg;

            btnStartVisit.disabled = true;
            btnStartVisit.textContent = 'Generating Visit...';

            const formData = new FormData();
            formData.append('action', 'start_new_visit');
            formData.append('patient_id', patientId);
            formData.append('reg_no', regNo);

            fetch('api/emr_api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.visit_id) {
                    window.location.href = 'emr.php?visit=' + encodeURIComponent(data.visit_id);
                } else {
                    alert('Error starting new visit: ' + (data.message || 'Unknown error'));
                    btnStartVisit.disabled = false;
                    btnStartVisit.textContent = 'Start New Visit';
                }
            })
            .catch(err => {
                alert('Network error: ' + err.message);
                btnStartVisit.disabled = false;
                btnStartVisit.textContent = 'Start New Visit';
            });
        });
    }

    // Edit Demographics form submit
    const editForm = document.getElementById('emr-edit-demographics-form');
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);

            fetch('api/emr_api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update demographics');
                }
            })
            .catch(err => alert('Error: ' + err.message));
        });
    }

    // Past Rx Drawer trigger in active workspace
    const btnOpenDrawer = document.getElementById('btn-open-past-rx-drawer');
    if (btnOpenDrawer) {
        btnOpenDrawer.addEventListener('click', () => {
            openPastRxDrawer(emrFirstPastVisitId);
        });
    }
});

function openParticularsAuditModal() {
    const modal = document.getElementById('emr-particulars-audit-modal');
    const body = document.getElementById('emr-particulars-audit-body');
    if (!modal) return;
    modal.classList.add('open');
    body.innerHTML = '<div class="emr-js-loading">Loading audit trail...</div>';

    fetch('api/emr_api.php?action=get_particulars_audit_log&patient_id=' + emrPatientId)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.history) || data.history.length === 0) {
                body.innerHTML = `
                    <div class="emr-audit-empty">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <p class="emr-js-no-change-title">No changes logged yet</p>
                        <span class="emr-js-no-change-desc">The particulars for this patient are at their initial registration values.</span>
                    </div>
                `;
                return;
            }

            let html = '<div class="emr-audit-timeline">';
            data.history.forEach(item => {
                const sourceMap = {
                    'prescription': '<span class="audit-source-pill source-prescription">Prescription Desk</span>',
                    'appointment': '<span class="audit-source-pill source-appointment">Appointment Desk</span>',
                    'emr': '<span class="audit-source-pill source-emr">EMR Portal</span>'
                };
                const sourceBadge = sourceMap[item.action_source] || `<span class="audit-source-pill">${escapeHtml(item.action_source)}</span>`;
                const roleBadge = `<span class="audit-role-pill role-${escapeHtml(item.changed_by_role || 'staff')}">${escapeHtml(item.changed_by_role || 'Staff')}</span>`;

                let diffsHtml = '';
                const changes = item.changes || {};
                if (changes.initial) {
                    diffsHtml = `<div class="audit-diff-item initial-reg">✨ Initial patient profile registered</div>`;
                } else {
                    for (const [key, diff] of Object.entries(changes)) {
                        diffsHtml += `
                            <div class="audit-diff-item">
                                <span class="diff-field">${escapeHtml(diff.label || key)}:</span>
                                <span class="diff-old">${escapeHtml(diff.old)}</span>
                                <span class="diff-arrow">➔</span>
                                <span class="diff-new">${escapeHtml(diff.new)}</span>
                            </div>
                        `;
                    }
                }

                html += `
                    <div class="audit-timeline-event">
                        <div class="audit-event-dot"></div>
                        <div class="audit-event-header">
                            <div class="audit-event-actor">
                                <strong>${escapeHtml(item.changed_by_name || 'Staff')}</strong>
                                ${roleBadge}
                                ${sourceBadge}
                            </div>
                            <div class="audit-event-time">${escapeHtml(item.created_at_formatted)}</div>
                        </div>
                        <div class="audit-event-diffs">
                            ${diffsHtml}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = `<div class="emr-js-error-msg">Failed to load audit history: ${escapeHtml(err.message)}</div>`;
        });
}

function closeParticularsAuditModal() {
    const modal = document.getElementById('emr-particulars-audit-modal');
    if (modal) modal.classList.remove('open');
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// Vitals and longitudinal trajectory engine
const METRIC_CONFIG = {
    // Daily and general vitals
    'weight':        { title: 'Weight Trajectory', unit: 'kg', icon: '⚖️', placeholder: 'e.g. 78.5', color: '#2563eb' },
    'bp':            { title: 'Blood Pressure', unit: 'mmHg', icon: '❤️', placeholder: 'e.g. 125/80', colorSys: '#dc2626', colorDia: '#059669' },
    'pulse':         { title: 'Pulse / Heart Rate', unit: 'bpm', icon: '💓', placeholder: 'e.g. 74', color: '#e11d48' },
    'spo2':          { title: 'Oxygen Saturation', unit: '%', icon: '🫁', placeholder: 'e.g. 98', color: '#0284c7' },
    'temp':          { title: 'Body Temperature', unit: '°F', icon: '🌡️', placeholder: 'e.g. 98.6', color: '#ea580c' },
    'rr':            { title: 'Respiratory Rate', unit: '/min', icon: '💨', placeholder: 'e.g. 18', color: '#0d9488' },

    // Metabolic and chronic care
    'glucose':       { title: 'Blood Glucose (FBS/PP)', unit: 'mmol/L', icon: '🩸', placeholder: 'e.g. 6.8', color: '#8b5cf6' },
    'hba1c':         { title: 'Glycated Hb (HbA1c)', unit: '%', icon: '📊', placeholder: 'e.g. 7.2', color: '#ec4899' },
    'creatinine':    { title: 'Serum Creatinine', unit: 'mg/dL', icon: '🧪', placeholder: 'e.g. 1.1', color: '#6366f1' },
    'egfr':          { title: 'eGFR Filtration Rate', unit: 'mL/min', icon: '📉', placeholder: 'e.g. 88', color: '#10b981' },
    'ldl':           { title: 'LDL Cholesterol', unit: 'mg/dL', icon: '🫀', placeholder: 'e.g. 110', color: '#d97706' },
    'triglycerides': { title: 'Serum Triglycerides', unit: 'mg/dL', icon: '🥓', placeholder: 'e.g. 150', color: '#b45309' },
    'tsh':           { title: 'Thyroid TSH', unit: 'µIU/mL', icon: '🦋', placeholder: 'e.g. 2.5', color: '#7c3aed' },
    'uric_acid':     { title: 'Serum Uric Acid', unit: 'mg/dL', icon: '🦶', placeholder: 'e.g. 6.2', color: '#c026d3' },

    // Pediatric growth
    'height':        { title: 'Height / Length', unit: 'inch', icon: '📏', placeholder: 'e.g. 66', color: '#059669' },
    'ofc':           { title: 'Head Circumference (OFC)', unit: 'cm', icon: '👶', placeholder: 'e.g. 42.5', color: '#f59e0b' },
    'muac':          { title: 'Arm Circumference (MUAC)', unit: 'cm', icon: '📐', placeholder: 'e.g. 14.5', color: '#14b8a6' },

    // Clinical curves
    'platelets':     { title: 'Platelet Count', unit: 'k/µL', icon: '🩸', placeholder: 'e.g. 185', color: '#e11d48' },
    'hb':            { title: 'Hemoglobin (Hb)', unit: 'g/dL', icon: '🔴', placeholder: 'e.g. 13.2', color: '#be123c' },
    'crp':           { title: 'C-Reactive Protein (CRP)', unit: 'mg/L', icon: '⚡', placeholder: 'e.g. 4.5', color: '#f97316' },
    'esr':           { title: 'ESR (1st Hour)', unit: 'mm/h', icon: '⏱️', placeholder: 'e.g. 15', color: '#9333ea' },
    'sfh':           { title: 'Fundal Height (SFH)', unit: 'cm', icon: '🤰', placeholder: 'e.g. 28', color: '#db2777' }
};

let currentTrackedMetrics = ['weight'];
let currentSeriesData = {};

function loadTrajectories() {
    const grid = document.getElementById('emr-trajectories-grid');
    if (!grid) return;

    fetch('api/emr_api.php?action=get_patient_trajectories&patient_id=' + emrPatientId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                grid.innerHTML = `<div class="emr-js-error-grid">${escapeHtml(data.message || 'Failed to load trajectories')}</div>`;
                return;
            }
            currentTrackedMetrics = Array.isArray(data.tracked_metrics) && data.tracked_metrics.length ? data.tracked_metrics : ['weight'];
            currentSeriesData = data.series || {};
            renderTrajectories();
        })
        .catch(err => {
            grid.innerHTML = `<div class="emr-js-error-grid">Network error loading trajectories: ${escapeHtml(err.message)}</div>`;
        });
}

function renderTrajectories() {
    const grid = document.getElementById('emr-trajectories-grid');
    if (!grid) return;

    if (currentTrackedMetrics.length === 0) {
        grid.innerHTML = `
            <div class="emr-js-empty-trajectory">
                <div class="emr-js-empty-icon">📊</div>
                <div class="emr-js-empty-title">No Trajectories Active for This Patient</div>
                <div class="emr-js-empty-subtitle">Click "Add Tracker ▾" above to select and monitor metrics for this patient.</div>
                <button type="button" class="btn-emr btn-emr-outline btn-emr-sm" onclick="addTracker('weight')">＋ Show Weight Trajectory</button>
            </div>
        `;
        return;
    }

    let html = '';
    currentTrackedMetrics.forEach(mType => {
        const conf = METRIC_CONFIG[mType] || { title: mType, unit: '', icon: '📈', color: '#2563eb' };
        const points = currentSeriesData[mType] || [];
        const latestPt = points.length ? points[points.length - 1] : null;
        let latestText = '--';
        if (latestPt) {
            latestText = latestPt.value + (conf.unit && String(latestPt.value).indexOf(conf.unit) === -1 ? ' ' + conf.unit : '');
        }

        // Trend delta calculation
        let deltaHtml = '';
        if (points.length >= 2 && mType !== 'bp') {
            const lastVal = parseFloat(points[points.length - 1].value);
            const prevVal = parseFloat(points[points.length - 2].value);
            if (!isNaN(lastVal) && !isNaN(prevVal)) {
                const diff = lastVal - prevVal;
                if (Math.abs(diff) > 0.0001) {
                    const isUp = diff > 0;
                    const sign = isUp ? '▲ +' : '▼ ';
                    const diffFormatted = Math.abs(diff) >= 10 ? Math.round(Math.abs(diff)) : Math.abs(diff).toFixed(1);
                    deltaHtml = `<span class="emr-trend-delta ${isUp ? 'delta-up' : 'delta-down'}">${sign}${diffFormatted}</span>`;
                } else {
                    deltaHtml = `<span class="emr-trend-delta delta-stable">— Stable</span>`;
                }
            }
        }

        html += `
            <div class="emr-trend-box" id="tracker-box-${mType}">
                <div class="emr-trend-box-header">
                    <div class="emr-trend-box-title">
                        <span>${conf.icon}</span>
                        <span>${escapeHtml(conf.title)}</span>
                        ${latestPt ? `<span class="emr-trend-latest-val">${escapeHtml(latestText)}</span>` : ''}
                        ${deltaHtml}
                    </div>
                    <div class="emr-trend-actions">
                        <button type="button" class="btn-trend-action" onclick="openLogReadingModal('${mType}')" title="Log Home or Desk Reading">
                            ＋ Log
                        </button>
                        <button type="button" class="btn-trend-action btn-trend-remove" onclick="removeTracker('${mType}')" title="Hide this trajectory for this patient">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="emr-trend-svg-wrap">
                    ${buildTrajectorySvg(mType, points, conf)}
                </div>
            </div>
        `;
    });

    grid.innerHTML = html;
}

const NORMAL_RANGES = {
    'pulse':        { min: 60, max: 100, label: 'Normal (60-100 bpm)' },
    'spo2':         { min: 95, max: 100, label: 'Normal (95-100%)' },
    'temp':         { min: 97.0, max: 99.0, label: 'Normal (97-99°F)' },
    'rr':           { min: 12, max: 20, label: 'Normal (12-20/min)' },
    'glucose':      { min: 4.0, max: 7.8, label: 'Normal (4.0-7.8 mmol/L)' },
    'hba1c':        { min: 4.0, max: 5.7, label: 'Normal (<5.7%)' },
    'creatinine':   { min: 0.6, max: 1.2, label: 'Normal (0.6-1.2 mg/dL)' },
    'egfr':         { min: 60, max: 120, label: 'Normal (>60 mL/min)' },
    'ldl':          { min: 50, max: 100, label: 'Optimal (<100 mg/dL)' },
    'triglycerides':{ min: 50, max: 150, label: 'Normal (<150 mg/dL)' },
    'tsh':          { min: 0.4, max: 4.0, label: 'Normal (0.4-4.0 µIU/mL)' },
    'uric_acid':    { min: 3.5, max: 7.2, label: 'Normal (3.5-7.2 mg/dL)' },
    'platelets':    { min: 150, max: 450, label: 'Normal (150-450 k/µL)' },
    'hb':           { min: 12.0, max: 16.5, label: 'Normal (12.0-16.5 g/dL)' },
    'crp':          { min: 0, max: 5.0, label: 'Normal (<5.0 mg/L)' },
    'esr':          { min: 0, max: 20, label: 'Normal (<20 mm/h)' }
};

function formatChartDate(dateStr) {
    if (!dateStr) return '';
    try {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const m = parseInt(parts[1], 10) - 1;
            const d = parseInt(parts[2], 10);
            return `${d} ${months[m] || parts[1]}`;
        }
    } catch (e) {}
    return dateStr.length > 5 ? dateStr.slice(5) : dateStr;
}

function calculateNiceTicks(minVal, maxVal, ticksCount = 4) {
    let span = maxVal - minVal;
    if (span <= 0.001) {
        span = Math.max(1.5, minVal * 0.15);
    }
    // Generous breathing room (25% buffer) so data points never hug the axes
    const pad = Math.max(span * 0.25, 1.0);
    const rawMin = Math.max(0, minVal - pad);
    const rawMax = maxVal + pad;

    const rawRange = rawMax - rawMin;
    const rawStep = rawRange / ticksCount;
    const mag = Math.floor(Math.log10(rawStep));
    const magPow = Math.pow(10, mag);
    const magMsd = rawStep / magPow;

    let stepSize;
    if (magMsd > 5) stepSize = 10 * magPow;
    else if (magMsd > 2) stepSize = 5 * magPow;
    else if (magMsd > 1) stepSize = 2 * magPow;
    else stepSize = magPow;

    const niceMin = Math.floor(rawMin / stepSize) * stepSize;
    const niceMax = Math.ceil(rawMax / stepSize) * stepSize;

    const ticks = [];
    for (let v = niceMin; v <= niceMax + (stepSize * 0.001); v += stepSize) {
        ticks.push(Number(v.toFixed(2)));
    }
    return { niceMin, niceMax, ticks };
}

function getSmoothCurvePath(pts) {
    if (!pts || pts.length === 0) return "";
    if (pts.length === 1) return `M ${pts[0].x},${pts[0].y}`;
    if (pts.length === 2) {
        return `M ${pts[0].x.toFixed(1)},${pts[0].y.toFixed(1)} L ${pts[1].x.toFixed(1)},${pts[1].y.toFixed(1)}`;
    }

    let d = `M ${pts[0].x.toFixed(1)},${pts[0].y.toFixed(1)}`;
    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i === 0 ? 0 : i - 1];
        const p1 = pts[i];
        const p2 = pts[i + 1];
        const p3 = pts[i + 2] || p2;

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${cp1x.toFixed(1)},${cp1y.toFixed(1)} ${cp2x.toFixed(1)},${cp2y.toFixed(1)} ${p2.x.toFixed(1)},${p2.y.toFixed(1)}`;
    }
    return d;
}

function getSmoothAreaPath(pts, baselineY) {
    if (!pts || pts.length === 0) return "";
    if (pts.length === 1) return "";
    const curve = getSmoothCurvePath(pts);
    return `${curve} L ${pts[pts.length - 1].x.toFixed(1)},${baselineY} L ${pts[0].x.toFixed(1)},${baselineY} Z`;
}

function buildTrajectorySvg(mType, points, conf) {
    if (!points || points.length === 0) {
        return `
            <div class="emr-chart-empty">
                <span class="emr-js-no-readings-title">No readings recorded yet</span>
                <span class="emr-js-no-readings-hint">Click <strong>＋ Log</strong> to add home readings or clinic measurements.</span>
            </div>
        `;
    }

    const width = 600;
    const height = 210;
    const padding = { top: 22, right: 25, bottom: 35, left: 45 };
    const chartW = width - padding.left - padding.right;
    const chartH = height - padding.top - padding.bottom;
    const baselineY = padding.top + chartH;

    // Single reading baseline milestone
    if (points.length === 1) {
        const pt = points[0];
        let valNum = parseFloat(pt.value) || 0;
        let diaNum = 0;
        let isBp = (mType === 'bp');
        if (isBp) {
            valNum = parseFloat(pt.sys) || 120;
            diaNum = parseFloat(pt.dia) || 80;
        }

        const { niceMin, niceMax, ticks } = calculateNiceTicks(
            isBp ? Math.min(valNum, diaNum) : valNum,
            isBp ? Math.max(valNum, diaNum) : valNum,
            4
        );

        const getY = (val) => padding.top + chartH - ((val - niceMin) / (niceMax - niceMin)) * chartH;
        const x = padding.left + (chartW / 2);
        const y = getY(valNum);
        const isHome = (pt.source === 'home');
        const color = isHome ? '#f59e0b' : (conf.color || '#2563eb');

        // Y grid lines
        const gridHtml = ticks.map(tickVal => {
            const yTick = getY(tickVal);
            return `
                <g>
                    <line x1="${padding.left}" y1="${yTick.toFixed(1)}" x2="${width - padding.right}" y2="${yTick.toFixed(1)}" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="2 3" />
                    <text x="${padding.left - 8}" y="${(yTick + 3.5).toFixed(1)}" text-anchor="end" font-size="10" font-family="monospace" fill="#94a3b8">${tickVal}</text>
                </g>
            `;
        }).join('');

        let pointVisual = '';
        if (isBp) {
            const yDia = getY(diaNum);
            pointVisual = `
                <line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="#dc2626" stroke-width="1.2" stroke-dasharray="4 4" stroke-opacity="0.3" />
                <line x1="${padding.left}" y1="${yDia.toFixed(1)}" x2="${width - padding.right}" y2="${yDia.toFixed(1)}" stroke="#059669" stroke-width="1.2" stroke-dasharray="4 4" stroke-opacity="0.3" />
                
                <circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="14" fill="#dc2626" fill-opacity="0.12" />
                <circle id="dot-${mType}-sys-0" cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="5" fill="#ffffff" stroke="#dc2626" stroke-width="2.5" />
                <text x="${x.toFixed(1)}" y="${(y - 12).toFixed(1)}" text-anchor="middle" font-size="11" font-weight="700" fill="#dc2626">Sys: ${pt.sys}</text>

                <circle cx="${x.toFixed(1)}" cy="${yDia.toFixed(1)}" r="14" fill="#059669" fill-opacity="0.12" />
                <circle id="dot-${mType}-dia-0" cx="${x.toFixed(1)}" cy="${yDia.toFixed(1)}" r="5" fill="#ffffff" stroke="#059669" stroke-width="2.5" />
                <text x="${x.toFixed(1)}" y="${(yDia + 20).toFixed(1)}" text-anchor="middle" font-size="11" font-weight="700" fill="#059669">Dia: ${pt.dia}</text>
            `;
        } else {
            pointVisual = `
                <line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="${color}" stroke-width="1.2" stroke-dasharray="4 4" stroke-opacity="0.35" />
                <circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="16" fill="${color}" fill-opacity="0.10" />
                <circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="9" fill="${color}" fill-opacity="0.22" />
                <circle id="dot-${mType}-0" cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="5" fill="#ffffff" stroke="${color}" stroke-width="2.8" />
                <text x="${x.toFixed(1)}" y="${(y - 14).toFixed(1)}" text-anchor="middle" font-size="11" font-weight="800" fill="${color}">${pt.value} ${conf.unit || ''}</text>
            `;
        }

        return `
            <svg viewBox="0 0 ${width} ${height}" class="emr-chart-svg" id="svg-${mType}">
                ${gridHtml}
                ${pointVisual}
                <text x="${x.toFixed(1)}" y="${baselineY + 16}" text-anchor="middle" font-size="10.5" font-weight="600" fill="#64748b">${formatChartDate(pt.date)} (Baseline)</text>
                <rect x="${padding.left}" y="${padding.top}" width="${chartW}" height="${chartH}"
                      fill="transparent" class="chart-hit-slice"
                      onmouseenter="onTrajectorySliceHover(event, '${mType}', 0)"
                      onmouseleave="onTrajectorySliceLeave('${mType}')" />
            </svg>
            <div class="emr-chart-tooltip" id="tooltip-${mType}" class="zrx-dn"></div>
        `;
    }

    // Multiple points (>= 2)
    if (mType === 'bp') {
        const sysVals = points.map(p => Number(p.sys) || 0).filter(v => v > 0);
        const diaVals = points.map(p => Number(p.dia) || 0).filter(v => v > 0);
        const rawMin = Math.min(40, ...diaVals.length ? diaVals : [60]);
        const rawMax = Math.max(180, ...sysVals.length ? sysVals : [140]);
        const { niceMin, niceMax, ticks } = calculateNiceTicks(rawMin, rawMax, 4);

        const getY = (val) => padding.top + chartH - ((val - niceMin) / (niceMax - niceMin)) * chartH;
        const getX = (idx) => padding.left + (idx / (points.length - 1)) * chartW;

        const sysPts = points.map((p, i) => ({ x: getX(i), y: getY(p.sys || 120) }));
        const diaPts = points.map((p, i) => ({ x: getX(i), y: getY(p.dia || 80) }));

        // Grid lines Y
        let gridHtml = ticks.map(tickVal => {
            const y = getY(tickVal);
            return `
                <g>
                    <line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="2 3" />
                    <text x="${padding.left - 8}" y="${(y + 3.5).toFixed(1)}" text-anchor="end" font-size="10" font-family="monospace" fill="#94a3b8">${tickVal}</text>
                </g>
            `;
        }).join('');

        // Grid lines X (dates)
        const stepX = points.length <= 6 ? 1 : Math.ceil(points.length / 5);
        points.forEach((p, i) => {
            if (i % stepX === 0 || i === points.length - 1) {
                const x = getX(i);
                gridHtml += `
                    <g>
                        <line x1="${x.toFixed(1)}" y1="${padding.top}" x2="${x.toFixed(1)}" y2="${baselineY}" stroke="#f8fafc" stroke-width="1" stroke-dasharray="3 3" />
                        <text x="${x.toFixed(1)}" y="${baselineY + 16}" text-anchor="middle" font-size="10" fill="#64748b" font-weight="600">${formatChartDate(p.date)}</text>
                    </g>
                `;
            }
        });

        // Smooth Bezier paths
        const sysPathD = getSmoothCurvePath(sysPts);
        const sysAreaD = getSmoothAreaPath(sysPts, baselineY);
        const diaPathD = getSmoothCurvePath(diaPts);
        const diaAreaD = getSmoothAreaPath(diaPts, baselineY);

        // Point dots & value tags
        let pointsHtml = '';
        const showAllLabelsBp = (points.length <= 12);
        points.forEach((pt, i) => {
            const x = getX(i);
            const ySys = getY(pt.sys || 120);
            const yDia = getY(pt.dia || 80);
            const isHome = (pt.source === 'home');
            const strokeSys = isHome ? '#f59e0b' : '#dc2626';
            const strokeDia = isHome ? '#f59e0b' : '#059669';
            const fillDot = isHome ? '#fef3c7' : '#ffffff';

            pointsHtml += `
                <circle id="dot-${mType}-sys-${i}" class="emr-chart-dot" cx="${x.toFixed(1)}" cy="${ySys.toFixed(1)}" r="4.5" fill="${fillDot}" stroke="${strokeSys}" stroke-width="2.5" />
                <circle id="dot-${mType}-dia-${i}" class="emr-chart-dot" cx="${x.toFixed(1)}" cy="${yDia.toFixed(1)}" r="4.5" fill="${fillDot}" stroke="${strokeDia}" stroke-width="2.5" />
                <text id="val-tag-sys-${mType}-${i}" class="emr-chart-point-val ${showAllLabelsBp ? 'visible' : ''}" data-always="${showAllLabelsBp ? '1' : '0'}" x="${x.toFixed(1)}" y="${(ySys - 9).toFixed(1)}" text-anchor="middle" font-size="10.5" font-weight="700" fill="#dc2626">${pt.sys}</text>
                <text id="val-tag-dia-${mType}-${i}" class="emr-chart-point-val ${showAllLabelsBp ? 'visible' : ''}" data-always="${showAllLabelsBp ? '1' : '0'}" x="${x.toFixed(1)}" y="${(yDia + 16).toFixed(1)}" text-anchor="middle" font-size="10.5" font-weight="700" fill="#059669">${pt.dia}</text>
            `;
        });

        // Hit-test overlay slices
        let hitSlicesHtml = '';
        const sliceW = chartW / (points.length - 1);
        points.forEach((pt, i) => {
            const x = i === 0 ? padding.left : getX(i) - (sliceW / 2);
            const w = (i === 0 || i === points.length - 1) ? (sliceW / 2) : sliceW;
            hitSlicesHtml += `
                <rect x="${x.toFixed(1)}" y="${padding.top}" width="${w.toFixed(1)}" height="${chartH}"
                      fill="transparent" class="chart-hit-slice"
                      onmouseenter="onTrajectorySliceHover(event, '${mType}', ${i})"
                      onmouseleave="onTrajectorySliceLeave('${mType}')" />
            `;
        });

        return `
            <svg viewBox="0 0 ${width} ${height}" class="emr-chart-svg" id="svg-${mType}">
                <defs>
                    <linearGradient id="grad-${mType}-sys" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#dc2626" stop-opacity="0.18" />
                        <stop offset="100%" stop-color="#dc2626" stop-opacity="0.01" />
                    </linearGradient>
                    <linearGradient id="grad-${mType}-dia" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#059669" stop-opacity="0.16" />
                        <stop offset="100%" stop-color="#059669" stop-opacity="0.01" />
                    </linearGradient>
                </defs>

                ${gridHtml}
                <path d="${sysAreaD}" fill="url(#grad-${mType}-sys)" />
                <path d="${diaAreaD}" fill="url(#grad-${mType}-dia)" />
                <path d="${sysPathD}" fill="none" stroke="#dc2626" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" />
                <path d="${diaPathD}" fill="none" stroke="#059669" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4 3" />
                ${pointsHtml}

                <line id="crosshair-${mType}" class="emr-chart-crosshair" x1="0" y1="${padding.top}" x2="0" y2="${baselineY}" class="zrx-dn" />
                ${hitSlicesHtml}
            </svg>
            <div class="emr-chart-tooltip" id="tooltip-${mType}" class="zrx-dn"></div>
        `;
    }

    // Single value metrics (Weight, Glucose, HbA1c, Pulse, Platelets, etc.)
    const vals = points.map(p => Number(p.value) || 0).filter(v => v > 0);
    const rawMin = Math.min(...vals.length ? vals : [0]);
    const rawMax = Math.max(...vals.length ? vals : [100]);
    const { niceMin, niceMax, ticks } = calculateNiceTicks(rawMin, rawMax, 4);

    const getY = (val) => padding.top + chartH - ((val - niceMin) / (niceMax - niceMin)) * chartH;
    const getX = (idx) => padding.left + (idx / (points.length - 1)) * chartW;

    const pts = points.map((p, i) => ({ x: getX(i), y: getY(Number(p.value) || 0) }));

    // Reference Normal Range Band (if applicable)
    let normalBandHtml = '';
    const norm = NORMAL_RANGES[mType];
    if (norm && norm.min !== undefined && norm.max !== undefined) {
        const yTop = Math.max(padding.top, getY(norm.max));
        const yBtm = Math.min(baselineY, getY(norm.min));
        const h = Math.max(0, yBtm - yTop);
        if (h > 0 && yTop < baselineY && yBtm > padding.top) {
            normalBandHtml = `
                <rect x="${padding.left}" y="${yTop.toFixed(1)}" width="${chartW}" height="${h.toFixed(1)}"
                      fill="#10b981" fill-opacity="0.04" stroke="#10b981" stroke-opacity="0.16" stroke-dasharray="3 3" stroke-width="0.8" />
                <text x="${width - padding.right - 4}" y="${(yTop + 11).toFixed(1)}" text-anchor="end" font-size="8.5" font-weight="700" fill="#10b981" opacity="0.85">${escapeHtml(norm.label)}</text>
            `;
        }
    }

    // Grid lines Y
    let gridHtml = ticks.map(tickVal => {
        const y = getY(tickVal);
        return `
            <g>
                <line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="2 3" />
                <text x="${padding.left - 8}" y="${(y + 3.5).toFixed(1)}" text-anchor="end" font-size="10" font-family="monospace" fill="#94a3b8">${tickVal}</text>
            </g>
        `;
    }).join('');

    // Grid lines X (dates)
    const stepX = points.length <= 6 ? 1 : Math.ceil(points.length / 5);
    points.forEach((p, i) => {
        if (i % stepX === 0 || i === points.length - 1) {
            const x = getX(i);
            gridHtml += `
                <g>
                    <line x1="${x.toFixed(1)}" y1="${padding.top}" x2="${x.toFixed(1)}" y2="${baselineY}" stroke="#f8fafc" stroke-width="1" stroke-dasharray="3 3" />
                    <text x="${x.toFixed(1)}" y="${baselineY + 16}" text-anchor="middle" font-size="10" fill="#64748b" font-weight="600">${formatChartDate(p.date)}</text>
                </g>
            `;
        }
    });

    // Smooth Bezier Paths
    const pathD = getSmoothCurvePath(pts);
    const areaD = getSmoothAreaPath(pts, baselineY);

    // Points & value tags above dots
    let pointsHtml = '';
    const showAllLabels = (points.length <= 14);
    points.forEach((pt, i) => {
        const x = getX(i);
        const y = getY(Number(pt.value) || 0);
        const isHome = (pt.source === 'home');
        const strokeColor = isHome ? '#f59e0b' : (conf.color || '#2563eb');
        const fillDot = isHome ? '#fef3c7' : '#ffffff';

        // Keep label inside bounds
        const yTag = (y - 9 < padding.top + 6) ? (y + 16) : (y - 9);

        pointsHtml += `
            <circle id="dot-${mType}-${i}" class="emr-chart-dot" cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="4.5" fill="${fillDot}" stroke="${strokeColor}" stroke-width="2.5" />
            <text id="val-tag-${mType}-${i}" class="emr-chart-point-val ${showAllLabels ? 'visible' : ''}" data-always="${showAllLabels ? '1' : '0'}" x="${x.toFixed(1)}" y="${yTag.toFixed(1)}" text-anchor="middle">${escapeHtml(pt.value)}</text>
        `;
    });

    // Hit-test overlay slices
    let hitSlicesHtml = '';
    const sliceW = chartW / (points.length - 1);
    points.forEach((pt, i) => {
        const x = i === 0 ? padding.left : getX(i) - (sliceW / 2);
        const w = (i === 0 || i === points.length - 1) ? (sliceW / 2) : sliceW;
        hitSlicesHtml += `
            <rect x="${x.toFixed(1)}" y="${padding.top}" width="${w.toFixed(1)}" height="${chartH}"
                  fill="transparent" class="chart-hit-slice"
                  onmouseenter="onTrajectorySliceHover(event, '${mType}', ${i})"
                  onmouseleave="onTrajectorySliceLeave('${mType}')" />
        `;
    });

    return `
        <svg viewBox="0 0 ${width} ${height}" class="emr-chart-svg" id="svg-${mType}">
            <defs>
                <linearGradient id="grad-${mType}" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="${conf.color || '#2563eb'}" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="${conf.color || '#2563eb'}" stop-opacity="0.01" />
                </linearGradient>
            </defs>

            ${normalBandHtml}
            ${gridHtml}
            <path d="${areaD}" fill="url(#grad-${mType})" />
            <path d="${pathD}" fill="none" stroke="${conf.color || '#2563eb'}" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
            ${pointsHtml}

            <line id="crosshair-${mType}" class="emr-chart-crosshair" x1="0" y1="${padding.top}" x2="0" y2="${baselineY}" class="zrx-dn" />
            ${hitSlicesHtml}
        </svg>
        <div class="emr-chart-tooltip" id="tooltip-${mType}" class="zrx-dn"></div>
    `;
}

function onTrajectorySliceHover(event, mType, idx) {
    const points = currentSeriesData[mType] || [];
    const pt = points[idx];
    if (!pt) return;

    const conf = METRIC_CONFIG[mType] || { title: mType, unit: '', color: '#2563eb' };
    const width = 600;
    const height = 210;
    const padding = { top: 22, right: 25, bottom: 35, left: 45 };
    const chartW = width - padding.left - padding.right;

    // Locate the exact point dot in the SVG DOM to extract its precise x and y coordinates
    const dot = document.getElementById(`dot-${mType}-${idx}`) || document.getElementById(`dot-${mType}-sys-${idx}`);
    let x = points.length <= 1 ? (padding.left + chartW / 2) : padding.left + (idx / (points.length - 1)) * chartW;
    let y = 100;

    if (dot) {
        x = parseFloat(dot.getAttribute('cx')) || x;
        y = parseFloat(dot.getAttribute('cy')) || y;
    }

    // Move and show crosshair
    const crosshair = document.getElementById(`crosshair-${mType}`);
    if (crosshair) {
        crosshair.setAttribute('x1', x.toFixed(1));
        crosshair.setAttribute('x2', x.toFixed(1));
        crosshair.style.display = 'block';
    }

    // Highlight point dot
    document.querySelectorAll(`.emr-chart-dot[id^="dot-${mType}-"]`).forEach(d => d.classList.remove('active'));
    if (dot) dot.classList.add('active');
    const dotSys = document.getElementById(`dot-${mType}-sys-${idx}`);
    if (dotSys) dotSys.classList.add('active');
    const dotDia = document.getElementById(`dot-${mType}-dia-${idx}`);
    if (dotDia) dotDia.classList.add('active');

    // Highlight point value label
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-${mType}-"]`).forEach(t => t.classList.remove('active'));
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-sys-${mType}-"]`).forEach(t => t.classList.remove('active'));
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-dia-${mType}-"]`).forEach(t => t.classList.remove('active'));

    const tag = document.getElementById(`val-tag-${mType}-${idx}`);
    if (tag) tag.classList.add('active', 'visible');
    const tagSys = document.getElementById(`val-tag-sys-${mType}-${idx}`);
    if (tagSys) tagSys.classList.add('active', 'visible');
    const tagDia = document.getElementById(`val-tag-dia-${mType}-${idx}`);
    if (tagDia) tagDia.classList.add('active', 'visible');

    // Populate and position clinical details tooltip
    const tooltip = document.getElementById(`tooltip-${mType}`);
    if (tooltip) {
        const isHome = (pt.source === 'home');
        const fullDate = pt.date + (pt.time ? ' ' + pt.time : '');
        const delBtn = (pt.id && String(pt.id).indexOf('visit_') === -1) 
            ? `<button type="button" onclick="event.stopPropagation(); deleteLogReading(${pt.id}, '${mType}')" class="emr-btn-del-reading" title="Delete logbook entry">✕ Delete</button>` 
            : '';

        let metricContent = '';
        if (mType === 'bp') {
            metricContent = `
                <div class="tooltip-metric-row">
                    <span class="tooltip-metric-title" class="zrx-c-danger">Systolic</span>
                    <strong class="tooltip-metric-value" class="zrx-c-danger">${pt.sys || '--'} <small class="emr-tooltip-unit-muted">mmHg</small></strong>
                </div>
                <div class="tooltip-metric-row">
                    <span class="tooltip-metric-title" class="zrx-c-success">Diastolic</span>
                    <strong class="tooltip-metric-value" class="zrx-c-success">${pt.dia || '--'} <small class="emr-tooltip-unit-muted">mmHg</small></strong>
                </div>
            `;
        } else {
            metricContent = `
                <div class="tooltip-metric-row">
                    <span class="tooltip-metric-title">${escapeHtml(conf.title)}</span>
                    <strong class="tooltip-metric-value" style="color: ${conf.color || '#2563eb'}; font-size: 0.88rem;">${pt.value} ${conf.unit || ''}</strong>
                </div>
            `;
        }

        tooltip.innerHTML = `
            <div class="tooltip-header">
                <span class="tooltip-date">${escapeHtml(fullDate)}</span>
                <span class="tooltip-badge ${isHome ? 'badge-home' : 'badge-clinic'}">
                    ${isHome ? '🏠 Home Logbook' : '🏥 Clinic Consultation'}
                </span>
            </div>
            ${metricContent}
            ${pt.notes ? `<div class="tooltip-notes"><strong>Note:</strong> ${escapeHtml(pt.notes)}</div>` : ''}
            ${delBtn ? `<div class="emr-tooltip-del-wrap">${delBtn}</div>` : ''}
        `;

        // Position floating comfortably above the exact hovered point
        const leftPct = Math.max(16, Math.min(84, (x / width) * 100));
        const topPct = (y / height) * 100;
        const isNearTop = (y < 75);

        tooltip.style.left = `${leftPct}%`;
        tooltip.style.top = `${topPct}%`;
        tooltip.style.right = 'auto';

        if (isNearTop) {
            // High peak near top: float 16px below the dot so it doesn't collide with card header
            tooltip.style.transform = 'translate(-50%, 16px)';
            tooltip.className = 'emr-chart-tooltip caret-top';
        } else {
            // Normal: float 16px above the dot so the graph line, dot, and curve stay fully visible
            tooltip.style.transform = 'translate(-50%, calc(-100% - 16px))';
            tooltip.className = 'emr-chart-tooltip caret-bottom';
        }
        tooltip.style.display = 'block';
    }
}

function onTrajectorySliceLeave(mType) {
    const crosshair = document.getElementById(`crosshair-${mType}`);
    if (crosshair) crosshair.style.display = 'none';

    const tooltip = document.getElementById(`tooltip-${mType}`);
    if (tooltip) tooltip.style.display = 'none';

    document.querySelectorAll(`.emr-chart-dot[id^="dot-${mType}-"]`).forEach(d => d.classList.remove('active'));

    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-${mType}-"]`).forEach(t => {
        t.classList.remove('active');
        if (t.getAttribute('data-always') !== '1') t.classList.remove('visible');
    });
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-sys-${mType}-"]`).forEach(t => {
        t.classList.remove('active');
        if (t.getAttribute('data-always') !== '1') t.classList.remove('visible');
    });
    document.querySelectorAll(`.emr-chart-point-val[id^="val-tag-dia-${mType}-"]`).forEach(t => {
        t.classList.remove('active');
        if (t.getAttribute('data-always') !== '1') t.classList.remove('visible');
    });
}

function deleteLogReading(readingId, metricType) {
    if (!confirm('Are you sure you want to delete this logbook entry?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_metric_reading');
    formData.append('id', readingId);
    formData.append('patient_id', String(emrPatientId));

    fetch('api/emr_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadTrajectories();
            } else {
                alert(data.message || 'Failed to delete reading');
            }
        });
}

function toggleAddTrackerMenu() {
    const menu = document.getElementById('add-tracker-menu');
    if (menu) menu.classList.toggle('open');
}

document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.emr-dropdown-wrap');
    const menu = document.getElementById('add-tracker-menu');
    if (menu && wrap && !wrap.contains(e.target)) {
        menu.classList.remove('open');
    }
});

function addTracker(metricType) {
    const menu = document.getElementById('add-tracker-menu');
    if (menu) menu.classList.remove('open');

    if (!currentTrackedMetrics.includes(metricType)) {
        currentTrackedMetrics.push(metricType);
        saveTrackedMetrics();
    }
}

function removeTracker(metricType) {
    currentTrackedMetrics = currentTrackedMetrics.filter(m => m !== metricType);
    saveTrackedMetrics();
}

function saveTrackedMetrics() {
    const formData = new FormData();
    formData.append('action', 'update_tracked_metrics');
    formData.append('patient_id', String(emrPatientId));
    currentTrackedMetrics.forEach(m => formData.append('metrics[]', m));

    fetch('api/emr_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderTrajectories();
            }
        });
}

function openLogReadingModal(metricType) {
    const modal = document.getElementById('emr-log-reading-modal');
    const titleEl = document.getElementById('log-reading-modal-title');
    const typeInput = document.getElementById('log-metric-type');
    const valInput = document.getElementById('log-reading-value');
    const valLabel = document.getElementById('log-reading-value-label');

    const conf = METRIC_CONFIG[metricType] || { title: metricType, unit: '', placeholder: '' };
    if (titleEl) titleEl.innerText = `Log ${conf.title} Reading`;
    if (typeInput) typeInput.value = metricType;
    if (valLabel) valLabel.innerText = `Reading Value (${conf.unit || 'units'})`;
    if (valInput) {
        valInput.value = '';
        valInput.placeholder = conf.placeholder || 'e.g. value';
    }

    if (modal) modal.classList.add('open');
    setTimeout(() => valInput && valInput.focus(), 60);
}

function closeLogReadingModal() {
    const modal = document.getElementById('emr-log-reading-modal');
    if (modal) modal.classList.remove('open');
}

// Log reading submission
const logReadingForm = document.getElementById('emr-log-reading-form');
if (logReadingForm) {
    logReadingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-log-reading');
        if (btn) btn.disabled = true;

        const formData = new FormData(this);
        fetch('api/emr_api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (btn) btn.disabled = false;
                if (data.success) {
                    closeLogReadingModal();
                    loadTrajectories();
                } else {
                    alert(data.message || 'Failed to save reading');
                }
            })
            .catch(err => {
                if (btn) btn.disabled = false;
                alert('Network error: ' + err.message);
            });
    });
}

// Page load initialization
document.addEventListener('DOMContentLoaded', function() {
    loadTrajectories();
});
