let drugDetailRequestSeq = 0;
let drugHeaderLayoutObserver = null;
let paediatricCalcSeq = 0;
const paediatricCalcStore = new Map();
let currentDrugBrandData = null;
let currentDrugClinicalData = null;
const drugDetailCache = new Map();
let selectedCustomDrug = null;
let selectedOverrideDrug = null;
let selectedDeleteDrug = null;
let selectedDeleteDrugHidden = false;
let newDrugSearchTimer = null;
let editDrugSearchTimer = null;
let editDrugSystemSearchTimer = null;
let deleteDrugSearchTimer = null;
let deleteDrugSystemSearchTimer = null;
const deletedDrugRowStore = new Map();
let newDrugShortManual = false;
let newDrugLongManual = false;
let editDrugShortManual = false;
let editDrugLongManual = false;
const pillCheckIconSvg = '<svg class="pill-inline-icon pill-inline-icon-check" viewBox="0 0 16 16" aria-hidden="true" width="14" height="14"><path d="M3 8.5 6.2 11.7 13 4.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
const pillExternalIconSvg = '<svg class="pill-inline-icon pill-inline-icon-external" viewBox="0 0 16 16" aria-hidden="true" width="13" height="13"><path d="M9.5 2.5H13.5V6.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 9 13.2 2.8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 9.5V12.2C13 12.9 12.4 13.5 11.7 13.5H3.8C3.1 13.5 2.5 12.9 2.5 12.2V4.3C2.5 3.6 3.1 3 3.8 3H6.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';

function syncDrugHeaderLayout() {
        const meta = document.getElementById('headerMeta');
        const snapshot = document.getElementById('clinicalSnapshot');
        if (!meta || !snapshot) return;

        snapshot.style.marginTop = '0px';
        if (window.innerWidth <= 1180) return;

        const metaRect = meta.getBoundingClientRect();
        const snapshotRect = snapshot.getBoundingClientRect();
        const overlap = Math.ceil(metaRect.bottom - snapshotRect.top + 12);
        if (overlap > 0) {
            snapshot.style.marginTop = `${overlap}px`;
        }
    }

function ensureDrugHeaderLayoutObserver() {
        const meta = document.getElementById('headerMeta');
        if (!meta || typeof ResizeObserver === 'undefined') return;

        if (drugHeaderLayoutObserver) {
            drugHeaderLayoutObserver.disconnect();
        }

        drugHeaderLayoutObserver = new ResizeObserver(() => {
            requestAnimationFrame(syncDrugHeaderLayout);
        });

        drugHeaderLayoutObserver.observe(meta);
    }

function cacheDrugDetail(id, data) {
        if (!id || !data || data.error) return;
        drugDetailCache.set(String(id), {
            status: 'resolved',
            value: data
        });
    }

function fetchBrandDetail(id) {
        const key = String(id || '');
        if (!key) {
            return Promise.reject(new Error('Missing brand id'));
        }

        const cached = drugDetailCache.get(key);
        if (cached) {
            if (cached.status === 'resolved') {
                return Promise.resolve(cached.value);
            }
            if (cached.status === 'pending' && cached.promise) {
                return cached.promise;
            }
        }

        const promise = Promise.resolve($.getJSON(`api/drug_explorer.php?type=details&id=${encodeURIComponent(key)}`))
            .then((data) => {
                cacheDrugDetail(key, data);
                return data;
            })
            .catch((error) => {
                drugDetailCache.delete(key);
                throw error;
            });

        drugDetailCache.set(key, {
            status: 'pending',
            promise
        });
        return promise;
    }

function warmBrandDetail(id) {
        fetchBrandDetail(id).catch(() => null);
    }

function loadBrand(id, preloadedData = null) {
        const requestSeq = ++drugDetailRequestSeq;
        currentBrandId = id;
        toggleSidebarMoaButton(false);
        $('.res-row, .mid-row').removeClass('active');
        $(`[onclick*="'${id}'"]`).addClass('active');
        $(`#mid_row_${id}`).addClass('active');
        $('#docsBooksPapersArea').hide();

        // Update URL with Brand ID
        const url = new URL(window.location);
        url.searchParams.set('brand_id', id);
        const resultsList = document.getElementById('resultsList');
        if (resultsList) {
            url.searchParams.set('sidebar_top', String(resultsList.scrollTop || 0));
        }
        window.history.replaceState({}, '', url);

        const renderDetail = function(data) {
            if (requestSeq !== drugDetailRequestSeq) {
                return;
            }
            if (data.error) {
                console.error(data.error);
                return;
            }

            const b = data.brand;
            const c = data.clinical || {};
            currentDrugBrandData = b;
            currentDrugClinicalData = c;
            currentGenericId = b.generic_id;
            currentFormNew = b.form_new;

            // 1. Update all content silently
            $('#h_brand').text(b.brand_name);
            $('#h_strength').text(b.strength);
            $('#h_form').text(b.form);
            $('#h_generic').text(b.generic);
            $('#h_manufacturer').text(b.manufacturer);
            $('#h_price').text(b.price + ' BDT');

            // Clinical Meta Simplified
            if(b.preg_cat) {
                $('#h_preg_letter').text(b.preg_cat);
                const pColors = { 'A': '#34d399', 'B': '#34d399', 'C': '#fbbf24', 'D': '#fb923c', 'X': '#f87171' };
                $('#h_preg_letter').css('color', pColors[b.preg_cat] || '#fff');
                $('#h_preg_letter').css('text-decoration-color', pColors[b.preg_cat] || '#fff');
                $('#pregPopupDesc').text(data.preg_desc || 'No pregnancy description available.');
                $('#pregRow').show();
            } else {
                $('#h_preg_letter').text('Not Classified').css('color', '#94a3b8').css('text-decoration', 'none');
                $('#pregPopupDesc').text('No clinical classification available for this medication.');
                $('#pregRow').show();
            }
            $('#h_class_text').html(renderClassLinks(b.cls || ''));
            $('#h_pubmed_link').html(renderHeaderPubmedLink(c.pubmed_query_base || ''));
            if (!(currentMode === 'class' && $('#sidebarNav').is(':visible') && currentClassId)) {
                setCurrentClassId(b.cls || '');
            }

            const iconPath = getDosageFormIcon(b.form, b.pres_new_upper);
            const iconHtml = iconPath ? `<img src="${iconPath}" style="width: 32px; height: 32px; filter: invert(1); opacity: 0.9;">` : '';
            $('#headerIcon').html(iconHtml);

            renderVariants(data.variants, b.id, b.strength, b.form_new);
            renderClinicalSnapshot(b, c);
            renderAccordions(c, b.preg_cat, data.preg_desc, b.price, b.packsize, b.cls, b.form_new || b.form || '', b.strength || '');
            ensureDrugHeaderLayoutObserver();
            requestAnimationFrame(syncDrugHeaderLayout);
            setTimeout(syncDrugHeaderLayout, 60);
            setTimeout(syncDrugHeaderLayout, 180);

            // Show the detail only after content is fully ready.
            $('#emptyState').hide();
            $('#drugDetailArea').css('display', 'flex');
        };

        if (preloadedData && String(preloadedData?.brand?.id) === String(id)) {
            cacheDrugDetail(id, preloadedData);
            renderDetail(preloadedData);
            return;
        }

        fetchBrandDetail(id)
            .then(renderDetail)
            .catch((error) => {
                if (requestSeq !== drugDetailRequestSeq) {
                    return;
                }
                console.error(error);
            });
    }

    function togglePregPopup(event) {
        event.stopPropagation();
        $('#pregPopup').fadeToggle(150);
    }

    // Close popup on outside click
    $(document).on('click', function() {
        $('#pregPopup').fadeOut(100);
    });

    /**
     * Replaces loadVariants AJAX with a synchronous renderer
     */
    function renderVariants(data, currentId, currentStrength, currentFormNew) {
        $('#sameCompanyPills, #otherCompanyPills').empty();
        if (!data) return;

        let hasOther = false;
        let hasSame = false;

        data.forEach(v => {
            const isExactMatch = (v.id == currentId) || (v.strength === currentStrength && v.form_new === currentFormNew);
            const activeCls = isExactMatch ? 'active' : '';
            const otherCls = !v.has_same_company ? 'other-company' : '';

            const html = `
                <div class="pill ${activeCls} ${otherCls}" onclick="loadBrand('${v.id}')" title="${!v.has_same_company ? 'Switching to another brand' : ''}">
                    ${activeCls ? pillCheckIconSvg : ''}
                    ${v.strength} | ${v.form_new}
                    ${!v.has_same_company && !isExactMatch ? ` ${pillExternalIconSvg}` : ''}
                </div>
            `;

            if (v.has_same_company) {
                $('#sameCompanyPills').append(html);
                hasSame = true;
            } else {
                $('#otherCompanyPills').append(html);
                hasOther = true;
            }
        });

        $('#sameCompanySection').toggle(hasSame);
        $('#otherCompanySection').toggle(hasOther);
    }

    function truthyClinicalFlag(value) {
        if (value === true || value === 1) return true;
        const normalized = String(value ?? '').trim().toLowerCase();
        return ['1', 'true', 'yes', 'y'].includes(normalized);
    }

    function firstPresent(...values) {
        return values.find(value => value !== undefined && value !== null && String(value).trim() !== '') || '';
    }

    function parseClinicalJson(value) {
        if (!value || typeof value !== 'string') return null;
        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }

    function formatClinicalMarkdown(text) {
        return escapeHtml(text)
            .replace(/^###\s+(.+)$/gm, '<h3 class="clinical-md-heading">$1</h3>')
            .replace(/^##\s+(.+)$/gm, '<h3 class="clinical-md-heading">$1</h3>')
            .replace(/^#\s+(.+)$/gm, '<h3 class="clinical-md-heading">$1</h3>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/\*([^*]+)\*/g, '<em>$1</em>');
    }

    function renderClinicalSnapshot(brand, clinical) {
        const b = brand || {};
        const c = clinical || {};
        const warnings = parseClinicalJson(firstPresent(b.immediate_warning, c.immediate_warning));
        const hasImmediateWarning = Array.isArray(warnings)
            ? warnings.length > 0
            : !!String(firstPresent(b.immediate_warning, c.immediate_warning)).trim();

        const badges = [];
        if (truthyClinicalFlag(firstPresent(b.is_antibiotic, c.is_antibiotic))) badges.push({ label: 'Antibiotic', cls: 'info' });
        if (truthyClinicalFlag(firstPresent(b.is_high_alert_medicine, c.is_high_alert_medicine))) badges.push({ label: 'High alert medicine', cls: 'danger' });
        if (truthyClinicalFlag(firstPresent(b.require_renal_adjustment, c.require_renal_adjustments))) badges.push({ label: 'Renal dose caution', cls: 'warning' });
        if (!truthyClinicalFlag(firstPresent(b.is_safe_in_pregnancy, c.is_safe_in_pregnancy))) badges.push({ label: 'Pregnancy caution', cls: 'warning' });
        if (!truthyClinicalFlag(firstPresent(b.is_safe_in_lactation, c.is_safe_in_lactation))) badges.push({ label: 'Lactation caution', cls: 'warning' });
        if (!truthyClinicalFlag(firstPresent(b.is_safe_in_hepatic_impairment, c.is_safe_in_hepatic_impairment))) badges.push({ label: 'Hepatic caution', cls: 'warning' });
        if (!truthyClinicalFlag(firstPresent(b.is_safe_in_paediatrics, c.is_safe_in_paediatric))) badges.push({ label: 'Paediatric caution', cls: 'warning' });
        if (truthyClinicalFlag(firstPresent(b.requires_tapering, c.requires_tapering))) badges.push({ label: 'Tapering needed', cls: 'warning' });
        if (hasImmediateWarning) badges.push({ label: 'Immediate warning', cls: 'danger' });

        const meta = [
            firstPresent(b.us_generic_name, c.us_generic_name) ? `<span class="clinical-meta-pill">US: ${escapeHtml(firstPresent(b.us_generic_name, c.us_generic_name))}</span>` : '',
            firstPresent(b.who_atc_class, c.who_atc_class) ? `<span class="clinical-meta-pill">ATC: ${escapeHtml(firstPresent(b.who_atc_class, c.who_atc_class))}</span>` : ''
        ].filter(Boolean).join('');

        const badgeHtml = (badges.length ? badges : [{ label: 'No major caution flags in summary', cls: 'safe' }]).map(item => {
            return `<span class="clinical-flag ${item.cls}">${escapeHtml(item.label)}</span>`;
        }).join('');

        const warningHeroHtml = Array.isArray(warnings) && warnings.length
            ? warnings.map((item) => {
                const title = firstPresent(item.alert_type, item.severity, 'Immediate warning');
                const severity = firstPresent(item.severity, 'Warning');
                const trigger = firstPresent(item.trigger_condition, item.trigger, '');
                const message = firstPresent(item.message, item.warning, '');
                return `
                    <div class="clinical-warning-hero">
                        <div class="clinical-warning-hero-head">
                            <span class="clinical-warning-hero-title">${escapeHtml(title)}</span>
                            <span class="clinical-warning-hero-severity">${escapeHtml(severity)}</span>
                        </div>
                        ${trigger ? `<div class="clinical-warning-hero-trigger">${escapeHtml(trigger)}</div>` : ''}
                        ${message ? `<div class="clinical-warning-hero-body">${escapeHtml(message)}</div>` : ''}
                    </div>
                `;
            }).join('')
            : '';

        $('#clinicalSnapshot').html(`
            <div class="clinical-flag-row">${badgeHtml}${meta}</div>
            ${warningHeroHtml}
        `);
    }

    function renderImmediateWarnings(value) {
        const parsed = parseClinicalJson(value);
        if (!Array.isArray(parsed) || !parsed.length) return '';

        return parsed.map(item => {
            const title = firstPresent(item.alert_type, item.severity, 'Immediate warning');
            const severity = firstPresent(item.severity, 'Warning');
            const trigger = firstPresent(item.trigger_condition, item.trigger, '');
            const message = firstPresent(item.message, item.warning, '');
            return `
                <div class="clinical-warning-card">
                    <div class="clinical-warning-title">
                        <span>${escapeHtml(title)}</span>
                        <span class="clinical-warning-severity">${escapeHtml(severity)}</span>
                    </div>
                    ${trigger ? `<div class="clinical-warning-trigger">${formatClinicalMarkdown(trigger)}</div>` : ''}
                    ${message ? `<div>${formatClinicalMarkdown(message)}</div>` : ''}
                </div>
            `;
        }).join('');
    }

    function renderJsonList(value) {
        const parsed = parseClinicalJson(value);
        if (Array.isArray(parsed)) {
            return `
                <div class="clinical-flow-list">
                    ${parsed.map((item, index) => `
                        <div class="clinical-flow-step">
                            <div class="clinical-flow-step-card">
                                <div class="clinical-flow-step-head">
                                    <span class="clinical-flow-badge">${index + 1}</span>
                                </div>
                                <div class="clinical-flow-step-text">${formatClinicalMarkdown(String(item))}</div>
                            </div>
                            ${index < parsed.length - 1 ? `
                                <div class="clinical-flow-connector" aria-hidden="true">
                                    <span class="clinical-flow-connector-arrow">
                                        <svg viewBox="0 0 16 16" width="14" height="14">
                                            <path d="M8 3.5V12.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M4.8 9.5 8 12.7 11.2 9.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </div>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            `;
        }
        return '';
    }

    function renderJsonTable(value) {
        const parsed = parseClinicalJson(value);
        if (!parsed || typeof parsed !== 'object') return '';
        const rows = Array.isArray(parsed) ? parsed.map((item, index) => [`Item ${index + 1}`, item]) : Object.entries(parsed);
        return `
            <div class="clinical-json-table">
                ${rows.map(([key, val]) => `
                    <div class="clinical-json-row">
                        <div class="clinical-json-key">${escapeHtml(String(key).replace(/_/g, ' '))}</div>
                        <div class="clinical-json-val">${formatClinicalMarkdown(typeof val === 'object' ? JSON.stringify(val, null, 2) : String(val))}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function numberOrNull(value) {
        if (value === null || value === undefined || String(value).trim() === '') return null;
        const numeric = Number(value);
        return Number.isFinite(numeric) ? numeric : null;
    }

    function formatDoseNumber(value) {
        if (!Number.isFinite(value)) return '';
        const rounded = Math.round(value * 100) / 100;
        return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2).replace(/\.?0+$/, '');
    }

    function formatDoseRange(min, max, unit) {
        if (!Number.isFinite(min) && !Number.isFinite(max)) return '';
        if (!Number.isFinite(min)) return `${formatDoseNumber(max)} ${unit}`;
        if (!Number.isFinite(max) || Math.abs(min - max) < 0.0001) return `${formatDoseNumber(min)} ${unit}`;
        return `${formatDoseNumber(min)}-${formatDoseNumber(max)} ${unit}`;
    }

    function capDose(value, cap) {
        if (!Number.isFinite(value)) return value;
        if (!Number.isFinite(cap) || cap <= 0) return value;
        return Math.min(value, cap);
    }

    function parseMgPerMl(value, type = '') {
        const text = String(value || '').toLowerCase().replace(/\s+/g, '');
        const percent = text.match(/^(\d+(?:\.\d+)?)%$/);
        if (percent && (type === '%' || type === 'w/v' || !type)) return Number(percent[1]) * 10;

        const ratio = text.match(/(\d+(?:\.\d+)?)mg\/(\d+(?:\.\d+)?)(?:ml|mL|pc)/i);
        if (ratio) {
            const mg = Number(ratio[1]);
            const ml = Number(ratio[2]);
            return ml > 0 ? mg / ml : null;
        }

        const perMl = text.match(/(\d+(?:\.\d+)?)mg\/(?:ml|mL|pc)/i);
        return perMl ? Number(perMl[1]) : null;
    }

    function formatTakeAmount(minMg, maxMg, patient, suffix = 'mL') {
        const mgPerMl = patient.mgPerMl;
        if (!Number.isFinite(mgPerMl) || mgPerMl <= 0) return '';
        const min = Number.isFinite(minMg) ? minMg / mgPerMl : null;
        const max = Number.isFinite(maxMg) ? maxMg / mgPerMl : null;
        const amount = formatDoseRange(min, max, suffix);
        return amount ? `take ${amount}` : '';
    }

    function getPaediatricAgeRange(rule) {
        return {
            min: numberOrNull(firstPresent(rule.min_age_months, rule.min_min_age_months, 0)) ?? 0,
            max: numberOrNull(firstPresent(rule.max_age_months, 216)) ?? 216
        };
    }

    function formatAgeRange(rule) {
        const range = getPaediatricAgeRange(rule);
        const fmt = (months) => {
            if (months < 12) return `${formatDoseNumber(months)} mo`;
            return `${formatDoseNumber(months / 12)} yr`;
        };
        return `${fmt(range.min)} - ${fmt(range.max)}`;
    }

    function paediatricDoseEntryOptions(rule, index) {
        const p = rule.parameters || {};
        const target = firstPresent(rule.target_group, `Regimen ${index + 1}`);
        const options = [];
        const addRange = (minKey, maxKey, singleKey, unit) => {
            const single = numberOrNull(p[singleKey]);
            const min = numberOrNull(p[minKey]);
            const max = numberOrNull(p[maxKey]);
            const value = Number.isFinite(single) ? formatDoseNumber(single) : formatDoseRange(min, max, '').trim();
            if (value) options.push({ value, label: `${target} - ${unit}` });
        };

        addRange('mg_per_kg_per_day_min', 'mg_per_kg_per_day_max', 'mg_per_kg_per_day', 'mg/kg/day');
        addRange('mg_per_kg_per_dose_min', 'mg_per_kg_per_dose_max', 'mg_per_kg_per_dose', 'mg/kg/dose');
        addRange('ml_per_kg_min', 'ml_per_kg_max', 'ml_per_kg', 'mL/kg');
        addRange('mg_per_m2_per_day_min', 'mg_per_m2_per_day_max', 'mg_per_m2_per_day', 'mg/m2/day');
        addRange('mg_per_m2_per_dose_min', 'mg_per_m2_per_dose_max', 'mg_per_m2_per_dose', 'mg/m2/dose');
        addRange('units_per_m2_min', 'units_per_m2_max', 'units_per_m2', 'units/m2');
        addRange('', '', 'fixed_dose_mg', 'mg fixed');
        addRange('', '', 'fixed_dose_ml', 'mL fixed');
        addRange('', '', 'fixed_dose_sprays', 'spray fixed');

        return options;
    }

    function renderPaediatricDoseCalculator(value, brandForm = '', brandStrength = '') {
        const parsed = parseClinicalJson(value);
        if (!Array.isArray(parsed) || !parsed.length) return '';

        const calcId = `paed_calc_${++paediatricCalcSeq}`;
        paediatricCalcStore.set(calcId, parsed);
        const formOptions = [brandForm, 'Suspension', 'Syrup', 'Drops', 'Injection', 'Infusion', 'Tablet', 'Capsule']
            .filter(Boolean)
            .filter((form, index, list) => list.findIndex(item => String(item).toLowerCase() === String(form).toLowerCase()) === index)
            .map(form => `<option value="${escapeHtml(form)}">${escapeHtml(form)}</option>`)
            .join('');

        const options = parsed.map((rule, index) => {
            const label = firstPresent(rule.target_group, `Regimen ${index + 1}`);
            return `<option value="${index}">${escapeHtml(label)}</option>`;
        }).join('');

        return `
            <div class="paed-calc" data-paed-calc-id="${calcId}">
                <datalist id="${calcId}_dose_options" class="paed-dose-options"></datalist>
                <datalist id="${calcId}_strength_options">
                    ${brandStrength ? `<option value="${escapeHtml(brandStrength)}"></option>` : ''}
                    <option value="200 mg/5 mL"></option>
                    <option value="500 mg/100 mL"></option>
                    <option value="0.5%"></option>
                </datalist>
                <div class="paed-calc-intro">
                    <strong>Patient dose calculator</strong>
                    <span>Enter age and weight. Add strength when you need mg-to-mL conversion.</span>
                </div>
                <div class="paed-calc-controls">
                    <label>
                        <span>Age</span>
                        <div class="paed-calc-inline">
                            <input type="number" min="0" step="0.1" class="paed-age-value" placeholder="Age">
                            <select class="paed-age-unit">
                                <option value="years">Years</option>
                                <option value="months">Months</option>
                            </select>
                        </div>
                    </label>
                    <label>
                        <span>Weight (kg)</span>
                        <input type="number" min="0" step="0.1" class="paed-weight" placeholder="kg">
                    </label>
                    <label>
                        <span>Height (cm)</span>
                        <input type="number" min="0" step="0.1" class="paed-height" placeholder="cm">
                    </label>
                    <label>
                        <span>BSA (m2)</span>
                        <input type="number" min="0" step="0.01" class="paed-bsa" placeholder="auto/manual">
                    </label>
                    <label>
                        <span>Dose</span>
                        <input type="text" class="paed-dose-entry" list="${calcId}_dose_options" placeholder="10 or 10-15">
                    </label>
                    <label>
                        <span>Strength type</span>
                        <select class="paed-strength-type">
                            <option value="">Custom</option>
                            <option value="w/v">w/v</option>
                            <option value="w/w">w/w</option>
                            <option value="%">Percentage</option>
                            <option value="mg/mL">mg/mL</option>
                            <option value="units/mL">units/mL</option>
                            <option value="ratio">Ratio</option>
                        </select>
                    </label>
                    <label>
                        <span>Dosage form</span>
                        <select class="paed-dosage-form">
                            ${formOptions}
                        </select>
                    </label>
                    <label>
                        <span>Strength entry</span>
                        <input type="text" class="paed-strength-entry" list="${calcId}_strength_options" placeholder="200mg/5mL, 0.5%" value="${escapeHtml(brandStrength)}">
                    </label>
                    <label class="paed-calc-group-field">
                        <span>Target group</span>
                        <select class="paed-group">
                            <option value="">Auto by age</option>
                            ${options}
                        </select>
                    </label>
                </div>
                <div class="paed-calc-output"></div>
                <div class="paed-calc-actions">
                    <button type="button" class="paed-json-toggle">See parameter</button>
                </div>
                <pre class="paed-json-view" hidden>${escapeHtml(JSON.stringify(parsed, null, 2))}</pre>
            </div>
        `;
    }

    function paediatricLine(label, value, detail = '') {
        if (!value) return '';
        return `<div class="paed-result-line"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong>${detail ? `<em>${escapeHtml(detail)}</em>` : ''}</div>`;
    }

    function paediatricDoseLines(rule, patient) {
        const p = rule.parameters || {};
        const lines = [];
        const weight = patient.weight;
        const bsa = patient.bsa;
        const dosingType = String(rule.dosing_type || '').toLowerCase();
        const needsWeight = ['weight_based'].includes(dosingType) || Object.keys(p).some(key => key.includes('per_kg'));
        const needsBsa = ['body_surface_area', 'bsa_based'].includes(dosingType) || Object.keys(p).some(key => key.includes('per_m2'));

        const dailyMinKg = numberOrNull(p.mg_per_kg_per_day_min);
        const dailyMaxKg = numberOrNull(p.mg_per_kg_per_day_max);
        if ((Number.isFinite(dailyMinKg) || Number.isFinite(dailyMaxKg)) && Number.isFinite(weight)) {
            const cap = numberOrNull(p.absolute_max_daily_dose_mg);
            const dailyMin = capDose((dailyMinKg ?? dailyMaxKg) * weight, cap);
            const dailyMax = capDose((dailyMaxKg ?? dailyMinKg) * weight, cap);
            lines.push(paediatricLine('Daily dose', formatDoseRange(dailyMin, dailyMax, 'mg/day'), firstPresent(formatTakeAmount(dailyMin, dailyMax, patient, 'mL/day'), cap ? `cap ${formatDoseNumber(cap)} mg/day` : '')));

            if (Array.isArray(p.divided_doses_per_day)) {
                p.divided_doses_per_day.filter(n => Number(n) > 0).forEach((parts) => {
                    lines.push(paediatricLine(`${parts} divided dose${Number(parts) > 1 ? 's' : ''}`, formatDoseRange(dailyMin / parts, dailyMax / parts, 'mg/dose'), formatTakeAmount(dailyMin / parts, dailyMax / parts, patient, 'mL/dose')));
                });
            }
        } else if (Number.isFinite(dailyMinKg) || Number.isFinite(dailyMaxKg)) {
            lines.push(paediatricLine('Needs weight', 'Enter weight to calculate mg/kg/day'));
        }

        const doseKg = numberOrNull(p.mg_per_kg_per_dose);
        const doseKgMin = numberOrNull(p.mg_per_kg_per_dose_min);
        const doseKgMax = numberOrNull(p.mg_per_kg_per_dose_max);
        if ((Number.isFinite(doseKg) || Number.isFinite(doseKgMin) || Number.isFinite(doseKgMax)) && Number.isFinite(weight)) {
            const cap = numberOrNull(firstPresent(p.absolute_max_single_dose_mg, p.absolute_max_dose_mg));
            const perDoseMin = capDose((doseKgMin ?? doseKg ?? doseKgMax) * weight, cap);
            const perDoseMax = capDose((doseKgMax ?? doseKg ?? doseKgMin) * weight, cap);
            const freq = numberOrNull(p.frequency_hours);
            lines.push(paediatricLine('Per dose', formatDoseRange(perDoseMin, perDoseMax, 'mg/dose'), firstPresent(formatTakeAmount(perDoseMin, perDoseMax, patient, 'mL/dose'), freq ? `every ${formatDoseNumber(freq)} hours` : '')));
            if (freq && freq > 0) {
                const dailyFactor = 24 / freq;
                lines.push(paediatricLine('Estimated daily total', formatDoseRange(perDoseMin * dailyFactor, perDoseMax * dailyFactor, 'mg/day'), formatTakeAmount(perDoseMin * dailyFactor, perDoseMax * dailyFactor, patient, 'mL/day')));
            }
        } else if (Number.isFinite(doseKg) || Number.isFinite(doseKgMin) || Number.isFinite(doseKgMax)) {
            lines.push(paediatricLine('Needs weight', 'Enter weight to calculate mg/kg/dose'));
        }

        const mlKgMin = numberOrNull(p.ml_per_kg_min);
        const mlKgMax = numberOrNull(p.ml_per_kg_max);
        if ((Number.isFinite(mlKgMin) || Number.isFinite(mlKgMax)) && Number.isFinite(weight)) {
            const capPerKg = numberOrNull(p.absolute_max_dose_ml_per_kg);
            const totalMin = (mlKgMin ?? mlKgMax) * weight;
            const totalMax = capDose((mlKgMax ?? mlKgMin) * weight, capPerKg ? capPerKg * weight : null);
            lines.push(paediatricLine('Volume dose', formatDoseRange(totalMin, totalMax, 'mL'), capPerKg ? `cap ${formatDoseNumber(capPerKg)} mL/kg` : ''));
        } else if (Number.isFinite(mlKgMin) || Number.isFinite(mlKgMax)) {
            lines.push(paediatricLine('Needs weight', 'Enter weight to calculate mL/kg'));
        }

        const exchangeMin = numberOrNull(p.ml_per_kg_per_exchange_min);
        const exchangeMax = numberOrNull(p.ml_per_kg_per_exchange_max);
        if ((Number.isFinite(exchangeMin) || Number.isFinite(exchangeMax)) && Number.isFinite(weight)) {
            lines.push(paediatricLine('Per exchange', formatDoseRange((exchangeMin ?? exchangeMax) * weight, (exchangeMax ?? exchangeMin) * weight, 'mL/exchange')));
        }

        const dailyM2Min = numberOrNull(p.mg_per_m2_per_day_min);
        const dailyM2Max = numberOrNull(p.mg_per_m2_per_day_max);
        if ((Number.isFinite(dailyM2Min) || Number.isFinite(dailyM2Max)) && Number.isFinite(bsa)) {
            const cap = numberOrNull(p.absolute_max_daily_dose_mg);
            const dailyMin = capDose((dailyM2Min ?? dailyM2Max) * bsa, cap);
            const dailyMax = capDose((dailyM2Max ?? dailyM2Min) * bsa, cap);
            lines.push(paediatricLine('BSA daily dose', formatDoseRange(dailyMin, dailyMax, 'mg/day'), firstPresent(formatTakeAmount(dailyMin, dailyMax, patient, 'mL/day'), cap ? `cap ${formatDoseNumber(cap)} mg/day` : '')));
            if (Array.isArray(p.divided_doses_per_day)) {
                p.divided_doses_per_day.filter(n => Number(n) > 0).forEach((parts) => {
                    lines.push(paediatricLine(`${parts} divided dose${Number(parts) > 1 ? 's' : ''}`, formatDoseRange(dailyMin / parts, dailyMax / parts, 'mg/dose'), formatTakeAmount(dailyMin / parts, dailyMax / parts, patient, 'mL/dose')));
                });
            }
        } else if (Number.isFinite(dailyM2Min) || Number.isFinite(dailyM2Max)) {
            lines.push(paediatricLine('Needs BSA', 'Enter BSA or height + weight'));
        }

        const m2Dose = numberOrNull(p.mg_per_m2_per_dose);
        const m2DoseMin = numberOrNull(p.mg_per_m2_per_dose_min);
        const m2DoseMax = numberOrNull(p.mg_per_m2_per_dose_max);
        if ((Number.isFinite(m2Dose) || Number.isFinite(m2DoseMin) || Number.isFinite(m2DoseMax)) && Number.isFinite(bsa)) {
            const capPerM2 = numberOrNull(p.absolute_max_dose_mg_per_m2);
            const cap = capPerM2 ? capPerM2 * bsa : null;
            const perDoseMin = capDose((m2DoseMin ?? m2Dose ?? m2DoseMax) * bsa, cap);
            const perDoseMax = capDose((m2DoseMax ?? m2Dose ?? m2DoseMin) * bsa, cap);
            lines.push(paediatricLine('BSA per dose', formatDoseRange(perDoseMin, perDoseMax, 'mg/dose'), firstPresent(formatTakeAmount(perDoseMin, perDoseMax, patient, 'mL/dose'), p.frequency, p.frequency_days ? `every ${p.frequency_days} days` : '', p.frequency_hours ? `every ${p.frequency_hours} hours` : '')));
        } else if (Number.isFinite(m2Dose) || Number.isFinite(m2DoseMin) || Number.isFinite(m2DoseMax)) {
            lines.push(paediatricLine('Needs BSA', 'Enter BSA or height + weight'));
        }

        const loadingM2 = numberOrNull(p.mg_per_m2_loading);
        const maintenanceM2 = numberOrNull(p.mg_per_m2_maintenance);
        const weeklyM2 = numberOrNull(p.mg_per_m2_per_week);
        const weeklyMcgM2 = numberOrNull(p.mcg_per_m2_per_week);
        const unitsM2Min = numberOrNull(p.units_per_m2_min);
        const unitsM2Max = numberOrNull(p.units_per_m2_max);
        const dailyGM2 = numberOrNull(p.g_per_m2_per_day);

        if (Number.isFinite(loadingM2) && Number.isFinite(bsa)) {
            const cap = numberOrNull(p.absolute_max_daily_dose_mg);
            const dose = capDose(loadingM2 * bsa, cap);
            lines.push(paediatricLine('Loading dose', `${formatDoseNumber(dose)} mg`, firstPresent(formatTakeAmount(dose, dose, patient, 'mL'), cap ? `cap ${formatDoseNumber(cap)} mg/day` : '')));
        }
        if (Number.isFinite(maintenanceM2) && Number.isFinite(bsa)) {
            const cap = numberOrNull(p.absolute_max_daily_dose_mg);
            const dose = capDose(maintenanceM2 * bsa, cap);
            lines.push(paediatricLine('Maintenance dose', `${formatDoseNumber(dose)} mg/day`, firstPresent(formatTakeAmount(dose, dose, patient, 'mL/day'), cap ? `cap ${formatDoseNumber(cap)} mg/day` : '')));
        }
        if (Number.isFinite(weeklyM2) && Number.isFinite(bsa)) {
            const dose = weeklyM2 * bsa;
            lines.push(paediatricLine('Weekly BSA dose', `${formatDoseNumber(dose)} mg/week`, formatTakeAmount(dose, dose, patient, 'mL/week')));
        }
        if (Number.isFinite(weeklyMcgM2) && Number.isFinite(bsa)) {
            const cap = numberOrNull(p.absolute_max_weekly_dose_mcg);
            lines.push(paediatricLine('Weekly BSA dose', `${formatDoseNumber(capDose(weeklyMcgM2 * bsa, cap))} mcg/week`, cap ? `cap ${formatDoseNumber(cap)} mcg/week` : ''));
        }
        if ((Number.isFinite(unitsM2Min) || Number.isFinite(unitsM2Max)) && Number.isFinite(bsa)) {
            const cap = numberOrNull(p.absolute_max_daily_dose_units);
            lines.push(paediatricLine('BSA units dose', formatDoseRange(capDose((unitsM2Min ?? unitsM2Max) * bsa, cap), capDose((unitsM2Max ?? unitsM2Min) * bsa, cap), 'units'), cap ? `cap ${formatDoseNumber(cap)} units/day` : ''));
        }
        if (Number.isFinite(dailyGM2) && Number.isFinite(bsa)) {
            lines.push(paediatricLine('BSA daily dose', `${formatDoseNumber(dailyGM2 * bsa)} g/day`));
        }

        if (Number.isFinite(numberOrNull(p.fixed_dose_mg))) {
            const fixedDose = numberOrNull(p.fixed_dose_mg);
            lines.push(paediatricLine('Fixed dose', `${formatDoseNumber(fixedDose)} mg`, firstPresent(formatTakeAmount(fixedDose, fixedDose, patient, 'mL'), p.frequency_hours ? `every ${p.frequency_hours} hours` : '')));
        }
        if (Number.isFinite(numberOrNull(p.fixed_dose_ml))) {
            lines.push(paediatricLine('Fixed volume', `${formatDoseNumber(p.fixed_dose_ml)} mL`, firstPresent(p.max_dose_ml ? `max ${p.max_dose_ml} mL` : '', p.frequency_hours ? `every ${p.frequency_hours} hours` : '')));
        }
        if (Number.isFinite(numberOrNull(p.fixed_dose_sprays))) {
            lines.push(paediatricLine('Fixed dose', `${formatDoseNumber(p.fixed_dose_sprays)} spray${Number(p.fixed_dose_sprays) === 1 ? '' : 's'}`, p.frequency_hours ? `every ${p.frequency_hours} hours` : ''));
        }

        if (!lines.length && (needsWeight || needsBsa)) {
            lines.push(paediatricLine('Input needed', needsWeight ? 'Enter weight' : 'Enter BSA or height + weight'));
        }
        if (!lines.length && firstPresent(p.frequency, p.frequency_days, p.frequency_hours)) {
            lines.push(paediatricLine('Frequency', firstPresent(p.frequency, p.frequency_days ? `every ${p.frequency_days} days` : '', p.frequency_hours ? `every ${p.frequency_hours} hours` : '')));
        }

        return lines.join('');
    }

    function runPaediatricCalculator(container) {
        const data = paediatricCalcStore.get(container.dataset.paedCalcId) || [];
        const ageValue = numberOrNull(container.querySelector('.paed-age-value')?.value);
        const ageUnit = container.querySelector('.paed-age-unit')?.value || 'years';
        const ageMonths = Number.isFinite(ageValue) ? (ageUnit === 'months' ? ageValue : ageValue * 12) : null;
        const weight = numberOrNull(container.querySelector('.paed-weight')?.value);
        const height = numberOrNull(container.querySelector('.paed-height')?.value);
        const manualBsa = numberOrNull(container.querySelector('.paed-bsa')?.value);
        const autoBsa = Number.isFinite(weight) && Number.isFinite(height) ? Math.sqrt((height * weight) / 3600) : null;
        const bsa = manualBsa ?? autoBsa;
        const doseInput = container.querySelector('.paed-dose-entry');
        const strengthType = String(container.querySelector('.paed-strength-type')?.value || '').trim();
        const dosageForm = String(container.querySelector('.paed-dosage-form')?.value || '').trim();
        const strengthEntry = String(container.querySelector('.paed-strength-entry')?.value || '').trim();
        const mgPerMl = parseMgPerMl(strengthEntry, strengthType);
        const groupValue = container.querySelector('.paed-group')?.value || '';
        const output = container.querySelector('.paed-calc-output');
        if (!output) return;

        const matches = data
            .map((rule, index) => ({ rule, index }))
            .filter(({ rule, index }) => {
                if (groupValue !== '') return String(index) === groupValue;
                if (!Number.isFinite(ageMonths)) return true;
                const range = getPaediatricAgeRange(rule);
                return ageMonths >= range.min && ageMonths <= range.max;
            });

        const doseOptions = matches.flatMap(({ rule, index }) => paediatricDoseEntryOptions(rule, index));
        const doseList = container.querySelector('.paed-dose-options');
        if (doseList) {
            doseList.innerHTML = doseOptions.map((item) => (
                `<option value="${escapeHtml(item.value)}" label="${escapeHtml(item.label)}"></option>`
            )).join('');
        }
        if (doseInput) {
            const previousAuto = doseInput.dataset.autoDoseEntry || '';
            const nextAuto = (Number.isFinite(ageMonths) || groupValue !== '') && doseOptions.length ? doseOptions[0].value : '';
            if (nextAuto && (!doseInput.value.trim() || doseInput.value.trim() === previousAuto)) {
                doseInput.value = nextAuto;
                doseInput.dataset.autoDoseEntry = nextAuto;
            } else if (!nextAuto && doseInput.value.trim() === previousAuto) {
                doseInput.value = '';
                doseInput.dataset.autoDoseEntry = '';
            }
        }
        const doseEntry = String(doseInput?.value || '').trim();

        const patient = { ageMonths, weight, height, bsa, dosageForm, mgPerMl };
        const bsaText = Number.isFinite(bsa) ? `<span>BSA: ${formatDoseNumber(bsa)} m2${manualBsa ? '' : ' auto'}</span>` : '';
        const summary = [
            Number.isFinite(ageMonths) ? `<span>Age: ${formatDoseNumber(ageMonths)} months</span>` : '',
            Number.isFinite(weight) ? `<span>Weight: ${formatDoseNumber(weight)} kg</span>` : '',
            bsaText,
            doseEntry ? `<span>Dose: ${escapeHtml(doseEntry)}</span>` : '',
            dosageForm ? `<span>Form: ${escapeHtml(dosageForm)}</span>` : '',
            strengthEntry || strengthType ? `<span>Strength: ${escapeHtml([strengthType, strengthEntry].filter(Boolean).join(' '))}${Number.isFinite(mgPerMl) ? ` = ${formatDoseNumber(mgPerMl)} mg/mL` : ''}</span>` : ''
        ].filter(Boolean).join('');

        if (!matches.length) {
            output.innerHTML = `<div class="paed-calc-empty">No age-matched paediatric regimen found. Choose a target group manually or re-check age.</div>`;
            return;
        }

        output.innerHTML = `
            ${summary ? `<div class="paed-calc-summary">${summary}</div>` : '<div class="paed-calc-empty">Enter age to filter target group. Enter weight/BSA when needed for calculation.</div>'}
            <div class="paed-result-grid">
                ${matches.map(({ rule, index }) => {
                    const lines = paediatricDoseLines(rule, patient);
                    const note = rule.parameters?.note ? `<div class="paed-result-note">${formatClinicalMarkdown(rule.parameters.note)}</div>` : '';
                    return `
                        <div class="paed-result-card">
                            <div class="paed-result-head">
                                <strong>${escapeHtml(firstPresent(rule.target_group, `Regimen ${index + 1}`))}</strong>
                                <span>${escapeHtml(formatAgeRange(rule))}</span>
                            </div>
                            <div class="paed-result-type">${escapeHtml(String(rule.dosing_type || 'dose').replace(/_/g, ' '))}</div>
                            ${lines || '<div class="paed-calc-empty">No numeric calculator available for this regimen. Follow note/reference text.</div>'}
                            ${note}
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function initPaediatricCalculators(root = document) {
        root.querySelectorAll('.paed-calc').forEach((container) => {
            if (container.dataset.ready === '1') return;
            container.dataset.ready = '1';
            container.querySelectorAll('input, select').forEach((field) => {
                field.addEventListener('input', () => runPaediatricCalculator(container));
                field.addEventListener('change', () => runPaediatricCalculator(container));
            });
            const toggle = container.querySelector('.paed-json-toggle');
            const jsonView = container.querySelector('.paed-json-view');
            if (toggle && jsonView) {
                toggle.addEventListener('click', () => {
                    const nextHidden = !jsonView.hidden;
                    jsonView.hidden = nextHidden;
                    toggle.textContent = nextHidden ? 'See parameter' : 'Hide parameter';
                });
            }
            runPaediatricCalculator(container);
        });
    }

    function renderPubmedQuery(query) {
        if (!query) return '';
        const q = String(query).trim();
        const href = `https://pubmed.ncbi.nlm.nih.gov/?term=${encodeURIComponent(q)}`;
        return `
            <div class="pubmed-query-box">
                <div>${escapeHtml(q)}</div>
                <a href="${href}" target="_blank" rel="noopener">Search PubMed <i class="fas fa-external-link-alt"></i></a>
            </div>
        `;
    }

    function renderHeaderPubmedLink(query) {
        if (!query) return '';
        const q = String(query).trim();
        const href = `https://pubmed.ncbi.nlm.nih.gov/?term=${encodeURIComponent(q)}`;
        return `<a class="header-pubmed-link" href="${href}" target="_blank" rel="noopener">Search PubMed ${pillExternalIconSvg}</a>`;
    }

    function formatClinicalText(text) {
        if (!text) return '';
        let formatted = formatClinicalMarkdown(String(text))
            .replace(/\\n/g, '<br>')
            .replace(/\r\n/g, '<br>')
            .replace(/\n/g, '<br>');
        formatted = formatted
            .replace(/<br>\s*(<h3 class="clinical-md-heading">)/g, '$1')
            .replace(/(<\/h3>)\s*<br>/g, '$1');

        // 2. Bold clinical headers followed by a colon
        formatted = formatted.replace(/((?:^|<br>)\s*)([^<:\n]+:)/gi, '$1<span class="clinical-inline-heading">$2</span>');

        // 3. Bold Pregnancy Category specifically (since it uses a dash)
        formatted = formatted.replace(/((?:^|<br>)\s*)(Pregnancy Category\s*-\s*[A-DX]+)/gi, '$1<span class="clinical-inline-heading">$2</span>');

        // 4. Highlight standalone routes or forms
        formatted = formatted.replace(/((?:^|<br>)\s*)(Oral|Rectal|Tablet|Capsule|Injection|Syrup\/Suspension|Suspension|Syrup|Paediatric Drops|Infusion|Topical|Ophthalmic)(\b)/gi, '$1<strong style="border-bottom: 2px solid #e2e8f0; padding-bottom: 2px; margin-bottom: 4px; display: inline-block;">$2</strong>$3');

        return formatted;
    }

    function renderAccordions(c, pregCat = '', pregDesc = '', price = '', packsize = '', brandCls = '', brandForm = '', brandStrength = '') {
        const safeC = c || {};
        // Prepend Category letter and description to clinical notes
        let pregContent = '';
        if (pregCat) pregContent += `Pregnancy Category - ${pregCat}\n`;
        if (pregDesc) pregContent += `${pregDesc}\n\n`;
        pregContent += (safeC.pregnancy_category_note || '');

        // Construct Pack Size and Price content from Brand data
        let priceContent = '';
        if (packsize) priceContent += `Pack Size : ${packsize}\n`;
        if (price) priceContent += `Unit Price : ${price}`;
        if (!priceContent && safeC.pack_size_price) priceContent = safeC.pack_size_price;

        const clsVal = brandCls || safeC.cls || '';
        const therapeuticContent = clsVal ? `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>${clsVal}</span>
                <i class="fas fa-eye" onclick="searchByClass('${clsVal.replace(/'/g, "\\'")}')"
                   style="color: var(--accent-blue); cursor: pointer; padding: 5px;" title="Browse this class"></i>
            </div>
        ` : '';

        const sections = [
            { title: 'Indications', val: safeC.indication },
            { title: 'Adult dose', val: safeC.adult_dose },
            { title: 'Child dose', val: safeC.child_dose },
            { title: 'Paediatric dose calculator', val: renderPaediatricDoseCalculator(safeC.paediatric_calc_parameter, brandForm, brandStrength), isHtml: true },
            { title: 'Renal dose', val: safeC.renal_dose || 'No information is available from controlled clinical studies regarding the use of this drug in patients with advanced renal disease.' },
            { title: 'Administration', val: safeC.administration },
            { title: 'Contraindications', val: safeC.contra_indication },
            { title: 'Side effects', val: safeC.side_effect },
            { title: 'Precautions & warnings', val: safeC.precaution },
            { title: 'Pregnancy & Lactation', val: pregContent },
            { title: 'Trimester safety', val: renderJsonTable(safeC.pregnancy_trimester_safety), isHtml: true },
            { title: 'Therapeutic Class', val: therapeuticContent, isHtml: true },
            { title: 'Mode of Action', val: safeC.mode_of_action },
            { title: 'Mode of Action Flow', val: renderJsonList(safeC.mode_of_action_flow), isHtml: true },
            { title: 'Interaction', val: safeC.interaction },
            { title: 'Overdose Effects', val: safeC.overdose_effect },
            { title: 'Overdose Treatment', val: safeC.overdose_treatment },
            { title: 'Storage', val: safeC.storage },
            { title: 'Counselling Pearl', val: safeC.counselling_pearl },
            { title: 'Pack size & Price', val: priceContent }
        ];

        $('#clinicalAccordions').empty();
        sections.forEach(s => {
            if (s.val && s.val !== 'null' && (typeof s.val !== 'string' || s.val.trim() !== '')) {
                const formattedVal = s.isHtml ? s.val : formatClinicalText(s.val);
                const contentClass = s.isHtml ? 'acc-content html-content' : 'acc-content';
                const html = `
                    <div class="acc-item">
                        <div class="acc-header">
                            <span class="acc-title">${s.title}</span>
                            <i class="fas fa-chevron-down acc-icon"></i>
                        </div>
                        <div class="${contentClass}">${formattedVal}</div>
                    </div>
                `;
                $('#clinicalAccordions').append(html);
            }
        });
        initPaediatricCalculators(document.getElementById('clinicalAccordions'));
    }

    function searchByClass(cls) {
        const targetClass = getPrimaryClassLabel(cls);
        switchTab('class', targetClass, false);
        loadClassDetail(targetClass);
        $('#dbSearchInput').focus();
    }

    function openOtherBrands() {
        $('#modalGenericTitle').text($('#h_generic').text() + ' - Alternatives');
        $('#altTableBody').empty();
        $('#modalSearchInput').val('');
        $('#modalFormFilter').hide(); // Hide form filter for Other Brands popup

        $.getJSON(`api/drug_explorer.php?type=get_alternatives&id=${currentGenericId}&form_new=${encodeURIComponent(currentFormNew)}`, function(data) {
            data.forEach(item => {
                const row = `
                    <tr class="alt-row" onclick="loadBrand('${item.id}'); closeModal();" style="cursor:pointer;">
                        <td style="font-weight:700; color:var(--navy-dark);">${item.pres_new_upper}</td>
                        <td>${item.strength}</td>
                        <td>${item.form}</td>
                        <td>${item.manufacturer}</td>
                        <td style="font-weight:700;">TK. ${item.price}</td>
                    </tr>
                `;
                $('#altTableBody').append(row);
            });
            $('#otherBrandsModal').css('display', 'flex');
        });
    }

    $('#modalSearchInput').on('keyup', function() {
        const val = $(this).val().toLowerCase();
        $('#altTableBody .alt-row').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
        });
    });

    function closeModal() { $('#otherBrandsModal').hide(); }

    function closeMoaModal() { $('#moaTableModal').hide(); }

    function drugEditorDefaults() {
        const brand = ($('#drugEditorBrand').val() || '').trim();
        const strength = ($('#drugEditorStrength').val() || '').trim();
        const form = ($('#drugEditorFormName').val() || '').trim();
        return {
            shortText: [brand, strength].filter(Boolean).join(' '),
            longText: [brand, strength, form].filter(Boolean).join(' ')
        };
    }

    function refreshDrugEditorAutoText(force = false) {
        const defaults = drugEditorDefaults();
        const shortField = $('#drugEditorShort');
        const longField = $('#drugEditorLong');
        if (force || !shortField.val()) shortField.val(defaults.shortText);
        if (force || !longField.val()) longField.val(defaults.longText);
    }

    function fillDrugEditor(data = {}, sourceType = 'custom') {
        const isOverride = sourceType === 'override';
        $('#drugEditorTitle').text(isOverride ? 'Edit Drug' : 'New Drug');
        $('#drugEditorSourceType').val(sourceType);
        $('#drugEditorSystemBrandId').val(isOverride ? (data.system_brand_id || data.brand_id || data.id || currentBrandId || '') : '');
        $('#drugEditorLocalDrugId').val(!isOverride ? (data.local_drug_id || data.id || '') : (data.local_drug_id || ''));
        $('#drugEditorGenericId').val(data.generic_id || '');
        $('#drugEditorBrand').val(data.brand_name || data.pres_new_upper || '');
        $('#drugEditorGeneric').val(data.generic_name || data.generic || '');
        $('#drugEditorStrength').val(data.strength || '');
        $('#drugEditorFormName').val(data.form || data.form_new || '');
        $('#drugEditorManufacturer').val(data.manufacturer || data.manufacturer_name || '');
        $('#drugEditorPrice').val(data.price || '');
        $('#drugEditorShort').val(data.short_prescription || data.pres_new_upper || '');
        $('#drugEditorLong').val(data.long_prescription || data.full_form_brand_name || '');
        refreshDrugEditorAutoText(false);
    }

    function openDrugEditor(mode = 'custom') {
        if (mode === 'override' && !currentBrandId) {
            alert('Select a drug first.');
            return;
        }
        fillDrugEditor(mode === 'override' ? (currentDrugBrandData || {}) : {}, mode === 'override' ? 'override' : 'custom');
        $('#drugEditorModal').css('display', 'flex');
        setTimeout(() => $('#drugEditorBrand').focus(), 50);
    }

    function closeDrugEditor(event) {
        if (event && event.target && event.target.id !== 'drugEditorModal') return;
        $('#drugEditorModal').hide();
    }

    function formToObject(form) {
        const data = {};
        new FormData(form).forEach((value, key) => { data[key] = value; });
        return data;
    }

    function saveUserDrug(event) {
        event.preventDefault();
        refreshDrugEditorAutoText(false);
        const payload = formToObject(event.currentTarget);
        fetch('api/user_drugs.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not save drug.');
                closeDrugEditor();
                if (typeof performSearch === 'function') {
                    performSearch($('#dbSearchInput').val(), true);
                }
                loadBrand(data.drug.id);
            })
            .catch(err => alert(err.message || 'Could not save drug.'));
    }

    function openHiddenDrugModal() {
        const currentName = currentDrugBrandData
            ? [currentDrugBrandData.brand_name || currentDrugBrandData.pres_new_upper, currentDrugBrandData.strength].filter(Boolean).join(' ')
            : 'No drug selected';
        $('#hideCurrentTitle').text(currentName);
        $('#hideCurrentDrugBtn').prop('disabled', !currentBrandId);
        $('#hiddenDrugModal').css('display', 'flex');
        loadHiddenDrugs();
    }

    function closeHiddenDrugModal(event) {
        if (event && event.target && event.target.id !== 'hiddenDrugModal') return;
        $('#hiddenDrugModal').hide();
    }

    function hideCurrentDrug() {
        if (!currentBrandId) {
            alert('Select a drug first.');
            return;
        }
        const name = currentDrugBrandData?.brand_name || currentDrugBrandData?.pres_new_upper || 'this drug';
        if (!confirm(`Hide ${name} from search? You can restore it later.`)) return;

        fetch('api/user_drugs.php?action=hide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentBrandId, snapshot: currentDrugBrandData || {} })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not hide drug.');
                currentBrandId = null;
                currentDrugBrandData = null;
                $('#drugDetailArea').hide();
                $('#emptyState').show();
                if (typeof performSearch === 'function') {
                    performSearch($('#dbSearchInput').val(), true);
                }
                loadHiddenDrugs();
            })
            .catch(err => alert(err.message || 'Could not hide drug.'));
    }

    function loadHiddenDrugs() {
        $('#hiddenDrugList').html('<div class="hidden-drug-empty">Loading hidden drugs...</div>');
        fetch('api/user_drugs.php?action=hidden')
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not load hidden drugs.');
                const rows = data.rows || [];
                if (!rows.length) {
                    $('#hiddenDrugList').html('<div class="hidden-drug-empty">No hidden drugs.</div>');
                    return;
                }
                $('#hiddenDrugList').html(rows.map(row => `
                    <div class="hidden-drug-row">
                        <div>
                            <strong>${escapeHtml(row.brand_name || row.id)}</strong>
                            <span>${escapeHtml([row.generic_name, row.strength, row.form].filter(Boolean).join(' | '))}</span>
                        </div>
                        <button type="button" class="btn-light restore-hidden-drug-btn" data-restore-id="${escapeHtml(row.id)}">Restore</button>
                    </div>
                `).join(''));
            })
            .catch(err => $('#hiddenDrugList').html(`<div class="hidden-drug-empty">${escapeHtml(err.message || 'Could not load hidden drugs.')}</div>`));
    }

    function restoreHiddenDrug(id) {
        fetch('api/user_drugs.php?action=restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not restore drug.');
                loadHiddenDrugs();
                if (typeof performSearch === 'function') {
                    performSearch($('#dbSearchInput').val(), true);
                }
            })
            .catch(err => alert(err.message || 'Could not restore drug.'));
    }

    function setNewDrugModeVisible(visible) {
        if (visible) {
            $('#newDrugSidebarPanel, #newDrugWorkspace').css('display', 'flex');
            $('#editDrugSidebarPanel, #editDrugWorkspace, #deleteDrugSidebarPanel, #deleteDrugWorkspace').hide();
        } else {
            $('#newDrugSidebarPanel, #newDrugWorkspace').hide();
        }
        $('.db-actions').show();
        $('.db-search-box, #resultsList').toggle(!visible);
        if (visible) {
            $('#sidebarNav, #middleColumn, #docsBooksPapersArea, #drugDetailArea, #emptyState').hide();
        }
    }

    function setEditDrugModeVisible(visible) {
        if (visible) {
            $('#editDrugSidebarPanel, #editDrugWorkspace').css('display', 'flex');
            $('#newDrugSidebarPanel, #newDrugWorkspace, #deleteDrugSidebarPanel, #deleteDrugWorkspace').hide();
        } else {
            $('#editDrugSidebarPanel, #editDrugWorkspace').hide();
        }
        $('.db-actions').show();
        $('.db-search-box, #resultsList').toggle(!visible);
        if (visible) {
            $('#sidebarNav, #middleColumn, #docsBooksPapersArea, #drugDetailArea, #emptyState').hide();
        }
    }

    function setDeleteDrugModeVisible(visible) {
        if (visible) {
            $('#deleteDrugSidebarPanel, #deleteDrugWorkspace').css('display', 'flex');
            $('#newDrugSidebarPanel, #newDrugWorkspace, #editDrugSidebarPanel, #editDrugWorkspace').hide();
        } else {
            $('#deleteDrugSidebarPanel, #deleteDrugWorkspace').hide();
        }
        $('.db-actions').show();
        $('.db-search-box, #resultsList').toggle(!visible);
        if (visible) {
            $('#sidebarNav, #middleColumn, #docsBooksPapersArea, #drugDetailArea, #emptyState').hide();
        }
    }

    function showNewDrugWorkspaceEmpty() {
        selectedCustomDrug = null;
        $('#newDrugInlineForm, #newDrugReadonly').hide();
        $('#newDrugWorkspaceEmpty').show();
        $('#newDrugResultsList .res-row').removeClass('active');
    }

    function newDrugDefaults() {
        const brand = ($('#newDrugBrand').val() || '').trim();
        const strength = ($('#newDrugStrength').val() || '').trim();
        const form = ($('#newDrugFormName').val() || '').trim();
        return {
            shortText: [brand, strength].filter(Boolean).join(' '),
            longText: [brand, strength, form].filter(Boolean).join(' ')
        };
    }

    function refreshNewDrugAutoText(force = false) {
        const defaults = newDrugDefaults();
        if (force || !newDrugShortManual) $('#newDrugShort').val(defaults.shortText);
        if (force || !newDrugLongManual) $('#newDrugLong').val(defaults.longText);
    }

    function showNewDrugForm(data = null) {
        const drug = data || {};
        const isEdit = !!(drug.local_drug_id || drug.id);
        newDrugShortManual = isEdit && !!(drug.short_prescription || drug.pres_new_upper);
        newDrugLongManual = isEdit && !!(drug.long_prescription || drug.full_form_brand_name);
        $('#newDrugFormTitle').text(isEdit ? 'Edit New Drug' : 'Add New Drug');
        $('#newDrugLocalId').val(drug.local_drug_id || (drug.source_type === 'custom' ? drug.id : '') || '');
        $('#newDrugGenericId').val(drug.generic_id || '');
        $('#newDrugBrand').val(drug.brand_name || drug.pres_new_upper || '');
        $('#newDrugGeneric').val(drug.generic_name || drug.generic || '');
        $('#newDrugStrength').val(drug.strength || '');
        $('#newDrugFormName').val(drug.form || drug.form_new || '');
        $('#newDrugManufacturer').val(drug.manufacturer_name || drug.manufacturer || '');
        $('#newDrugPrice').val(drug.price || '');
        $('#newDrugShort').val(drug.short_prescription || drug.pres_new_upper || '');
        $('#newDrugLong').val(drug.long_prescription || drug.full_form_brand_name || '');
        refreshNewDrugAutoText(false);

        $('#newDrugWorkspaceEmpty, #newDrugReadonly').hide();
        $('#newDrugInlineForm').css('display', 'grid');
        setTimeout(() => $('#newDrugBrand').focus(), 0);
    }

    function renderCustomDrugDetail(drug) {
        selectedCustomDrug = drug;
        $('#newDrugViewBrand').text(drug.brand_name || drug.pres_new_upper || '');
        $('#newDrugViewGeneric').text(drug.generic_name || drug.generic || '-');
        $('#newDrugViewStrength').text(drug.strength || '-');
        $('#newDrugViewForm').text(drug.form || drug.form_new || '-');
        $('#newDrugViewManufacturer').text(drug.manufacturer_name || drug.manufacturer || '-');
        $('#newDrugViewPrice').text(drug.price || '-');
        $('#newDrugViewShort').text(drug.short_prescription || drug.pres_new_upper || '-');
        $('#newDrugViewLong').text(drug.long_prescription || drug.full_form_brand_name || '-');
        $('#newDrugWorkspaceEmpty, #newDrugInlineForm').hide();
        $('#newDrugReadonly').show();
        $('#newDrugResultsList .res-row').removeClass('active');
        $(`#newDrugResultsList .res-row[data-custom-id="${CSS.escape(String(drug.id || drug.local_drug_id || ''))}"]`).addClass('active');
    }

    function loadCustomDrug(id) {
        fetch(`api/user_drugs.php?action=get&id=${encodeURIComponent(id)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.drug) throw new Error(data.error || 'Could not load drug.');
                renderCustomDrugDetail(data.drug);
            })
            .catch(err => alert(err.message || 'Could not load drug.'));
    }

    function renderCustomDrugList(rows) {
        const list = $('#newDrugResultsList').empty();
        if (!rows.length) {
            list.html('<div class="new-drug-list-empty">No new drugs found.</div>');
            return;
        }

        rows.forEach(row => {
            const id = String(row.id || row.local_drug_id || '');
            list.append(`
                <div class="res-row" data-custom-id="${escapeHtml(id)}">
                    <i class="fas fa-capsules new-drug-row-icon"></i>
                    <div class="res-info">
                        <div class="res-line-1">
                            <span class="res-brand">${escapeHtml(row.brand_name || '')}</span>
                            <span class="res-price">${escapeHtml(row.price || '')}</span>
                        </div>
                        <div class="res-line-2">${escapeHtml(row.generic_name || row.generic || '')}</div>
                        <div class="res-line-3">${escapeHtml([row.strength, row.form].filter(Boolean).join(' | '))}</div>
                        <div class="res-line-4">${escapeHtml(row.manufacturer_name || row.manufacturer || '')}</div>
                    </div>
                </div>
            `);
        });
    }

    function loadCustomDrugList(query = '') {
        $('#newDrugResultsList').scrollTop(0).html('<div class="new-drug-list-empty">Loading...</div>');
        fetch(`api/user_drugs.php?action=custom&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not load new drugs.');
                renderCustomDrugList(data.rows || []);
                if (selectedCustomDrug) {
                    const id = String(selectedCustomDrug.id || selectedCustomDrug.local_drug_id || '');
                    $(`#newDrugResultsList .res-row[data-custom-id="${CSS.escape(id)}"]`).addClass('active');
                }
            })
            .catch(err => $('#newDrugResultsList').html(`<div class="new-drug-list-empty">${escapeHtml(err.message || 'Could not load new drugs.')}</div>`));
    }

    function enterNewDrugMode() {
        $('.db-tab, .db-subtab').removeClass('active');
        $('#newDrugTab').addClass('active');
        currentMode = 'new';
        setNewDrugModeVisible(true);
        showNewDrugWorkspaceEmpty();
        $('#newDrugSearchInput').val('');
        loadCustomDrugList('');

        const url = new URL(window.location);
        url.searchParams.set('mode', 'new');
        ['brand_id', 'generic_id', 'indication_id', 'class_id', 'brand_search', 'generic_search', 'indication_search', 'class_search'].forEach(param => {
            url.searchParams.delete(param);
        });
        window.history.replaceState({}, '', url);
    }

    function leaveNewDrugMode() {
        if (!$('#newDrugWorkspace').is(':visible')) return;
        setNewDrugModeVisible(false);
    }

    function saveNewDrug(event) {
        event.preventDefault();
        refreshNewDrugAutoText(false);
        const payload = formToObject(event.currentTarget);
        fetch('api/user_drugs.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.drug) throw new Error(data.error || 'Could not save drug.');
                renderCustomDrugDetail(data.drug);
                loadCustomDrugList($('#newDrugSearchInput').val());
            })
            .catch(err => alert(err.message || 'Could not save drug.'));
    }

    function deleteSelectedCustomDrug() {
        if (!selectedCustomDrug) return;
        const id = selectedCustomDrug.id || selectedCustomDrug.local_drug_id;
        const name = selectedCustomDrug.brand_name || 'this drug';
        if (!confirm(`Delete ${name}? You can restore it later from Delete.`)) return;

        fetch('api/user_drugs.php?action=hide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, snapshot: selectedCustomDrug })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not delete drug.');
                showNewDrugWorkspaceEmpty();
                loadCustomDrugList($('#newDrugSearchInput').val());
            })
            .catch(err => alert(err.message || 'Could not delete drug.'));
    }

    function showEditDrugWorkspaceEmpty() {
        selectedOverrideDrug = null;
        $('#editDrugPicker, #editDrugInlineForm, #editDrugReadonly').hide();
        $('#editDrugWorkspaceEmpty').show();
        $('#editDrugResultsList .res-row').removeClass('active');
    }

    function editDrugDefaults() {
        const brand = ($('#editDrugBrand').val() || '').trim();
        const strength = ($('#editDrugStrength').val() || '').trim();
        const form = ($('#editDrugFormName').val() || '').trim();
        return {
            shortText: [brand, strength].filter(Boolean).join(' '),
            longText: [brand, strength, form].filter(Boolean).join(' ')
        };
    }

    function refreshEditDrugAutoText(force = false) {
        const defaults = editDrugDefaults();
        if (force || !editDrugShortManual) $('#editDrugShort').val(defaults.shortText);
        if (force || !editDrugLongManual) $('#editDrugLong').val(defaults.longText);
    }

    function showEditDrugPicker() {
        $('#editDrugWorkspaceEmpty, #editDrugInlineForm, #editDrugReadonly').hide();
        $('#editDrugPicker').show();
        $('#editDrugSystemSearchInput').val('').focus();
        loadEditDrugSystemResults('');
    }

    function fillEditDrugForm(drug) {
        const systemId = drug.system_brand_id || drug.brand_id || drug.id || '';
        editDrugShortManual = !!(drug.short_prescription || drug.pres_new_upper);
        editDrugLongManual = !!(drug.long_prescription || drug.full_form_brand_name);
        $('#editDrugFormTitle').text(`Edit ${drug.brand_name || drug.pres_new_upper || 'Drug'}`);
        $('#editDrugSystemBrandId').val(systemId);
        $('#editDrugLocalId').val(drug.local_drug_id || '');
        $('#editDrugGenericId').val(drug.generic_id || '');
        $('#editDrugBrand').val(drug.brand_name || drug.pres_new_upper || '');
        $('#editDrugGeneric').val(drug.generic_name || drug.generic || '');
        $('#editDrugStrength').val(drug.strength || '');
        $('#editDrugFormName').val(drug.form || drug.form_new || '');
        $('#editDrugManufacturer').val(drug.manufacturer_name || drug.manufacturer || '');
        $('#editDrugPrice').val(drug.price || '');
        $('#editDrugShort').val(drug.short_prescription || drug.pres_new_upper || '');
        $('#editDrugLong').val(drug.long_prescription || drug.full_form_brand_name || '');
        refreshEditDrugAutoText(false);

        $('#editDrugWorkspaceEmpty, #editDrugPicker, #editDrugReadonly').hide();
        $('#editDrugInlineForm').css('display', 'grid');
        setTimeout(() => $('#editDrugBrand').focus(), 0);
    }

    function renderOverrideDrugDetail(drug) {
        selectedOverrideDrug = drug;
        $('#editDrugViewBrand').text(drug.brand_name || drug.pres_new_upper || '');
        $('#editDrugViewGeneric').text(drug.generic_name || drug.generic || '-');
        $('#editDrugViewStrength').text(drug.strength || '-');
        $('#editDrugViewForm').text(drug.form || drug.form_new || '-');
        $('#editDrugViewManufacturer').text(drug.manufacturer_name || drug.manufacturer || '-');
        $('#editDrugViewPrice').text(drug.price || '-');
        $('#editDrugViewShort').text(drug.short_prescription || drug.pres_new_upper || '-');
        $('#editDrugViewLong').text(drug.long_prescription || drug.full_form_brand_name || '-');
        $('#editDrugWorkspaceEmpty, #editDrugPicker, #editDrugInlineForm').hide();
        $('#editDrugReadonly').show();
        $('#editDrugResultsList .res-row').removeClass('active');
        $(`#editDrugResultsList .res-row[data-override-id="${CSS.escape(String(drug.id || drug.system_brand_id || ''))}"]`).addClass('active');
    }

    function loadOverrideDrug(id) {
        fetch(`api/user_drugs.php?action=get&id=${encodeURIComponent(id)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.drug) throw new Error(data.error || 'Could not load drug.');
                renderOverrideDrugDetail(data.drug);
            })
            .catch(err => alert(err.message || 'Could not load drug.'));
    }

    function renderEditedDrugList(rows) {
        const list = $('#editDrugResultsList').empty();
        if (!rows.length) {
            list.html('<div class="new-drug-list-empty">No edited drugs found.</div>');
            return;
        }

        rows.forEach(row => {
            const id = String(row.system_brand_id || row.id || '');
            list.append(`
                <div class="res-row" data-override-id="${escapeHtml(id)}">
                    <i class="fas fa-pen new-drug-row-icon"></i>
                    <div class="res-info">
                        <div class="res-line-1">
                            <span class="res-brand">${escapeHtml(row.brand_name || '')}</span>
                            <span class="res-price">${escapeHtml(row.price || '')}</span>
                        </div>
                        <div class="res-line-2">${escapeHtml(row.generic_name || row.generic || '')}</div>
                        <div class="res-line-3">${escapeHtml([row.strength, row.form].filter(Boolean).join(' | '))}</div>
                        <div class="res-line-4">${escapeHtml(row.manufacturer_name || row.manufacturer || '')}</div>
                    </div>
                </div>
            `);
        });
    }

    function loadEditedDrugList(query = '') {
        $('#editDrugResultsList').scrollTop(0).html('<div class="new-drug-list-empty">Loading...</div>');
        fetch(`api/user_drugs.php?action=overrides&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not load edited drugs.');
                renderEditedDrugList(data.rows || []);
                if (selectedOverrideDrug) {
                    const id = String(selectedOverrideDrug.system_brand_id || selectedOverrideDrug.id || '');
                    $(`#editDrugResultsList .res-row[data-override-id="${CSS.escape(id)}"]`).addClass('active');
                }
            })
            .catch(err => $('#editDrugResultsList').html(`<div class="new-drug-list-empty">${escapeHtml(err.message || 'Could not load edited drugs.')}</div>`));
    }

    function renderEditDrugSystemResults(rows) {
        const list = $('#editDrugSystemResults').empty();
        if (!rows.length) {
            list.html('<div class="new-drug-list-empty">No drugs found.</div>');
            return;
        }

        rows.forEach(row => {
            const id = String(row.id || row.brand_id || '');
            list.append(`
                <div class="res-row" data-system-id="${escapeHtml(id)}">
                    <img src="${escapeHtml(getDosageFormIcon(row.form, row.pres_new_upper) || 'assets/images/dosage-form-images/fallback.svg')}" class="res-icon" alt="">
                    <div class="res-info">
                        <div class="res-line-1">
                            <span class="res-brand">${escapeHtml(row.brand_name || row.pres_new_upper || '')}</span>
                            <span class="res-price">${escapeHtml(row.price || '')}</span>
                        </div>
                        <div class="res-line-2">${escapeHtml(row.generic_name || row.generic || '')}</div>
                        <div class="res-line-3">${escapeHtml([row.strength, row.form].filter(Boolean).join(' | '))}</div>
                        <div class="res-line-4">${escapeHtml(row.manufacturer || row.manufacturer_name || '')}</div>
                    </div>
                </div>
            `);
        });
    }

    function loadEditDrugSystemResults(query = '') {
        $('#editDrugSystemResults').scrollTop(0).html('<div class="new-drug-list-empty">Loading...</div>');
        $.getJSON(`api/drug_explorer.php?type=brand&q=${encodeURIComponent(query)}`, function(data) {
            renderEditDrugSystemResults(data || []);
        }).fail(function() {
            $('#editDrugSystemResults').html('<div class="new-drug-list-empty">Could not load drugs.</div>');
        });
    }

    function startEditOverrideFromBrand(id) {
        fetch(`api/user_drugs.php?action=get&id=${encodeURIComponent(id)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.drug) throw new Error(data.error || 'Could not load drug.');
                fillEditDrugForm(data.drug);
            })
            .catch(err => alert(err.message || 'Could not load drug.'));
    }

    function enterEditDrugMode() {
        $('.db-tab, .db-subtab').removeClass('active');
        $('#editDrugTab').addClass('active');
        currentMode = 'edit';
        setEditDrugModeVisible(true);
        showEditDrugWorkspaceEmpty();
        $('#editDrugSearchInput').val('');
        loadEditedDrugList('');

        const url = new URL(window.location);
        url.searchParams.set('mode', 'edit');
        ['brand_id', 'generic_id', 'indication_id', 'class_id', 'brand_search', 'generic_search', 'indication_search', 'class_search'].forEach(param => {
            url.searchParams.delete(param);
        });
        window.history.replaceState({}, '', url);
    }

    function leaveEditDrugMode() {
        if (!$('#editDrugWorkspace').is(':visible')) return;
        setEditDrugModeVisible(false);
    }

    function saveEditedDrug(event) {
        event.preventDefault();
        refreshEditDrugAutoText(false);
        const payload = formToObject(event.currentTarget);
        fetch('api/user_drugs.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.drug) throw new Error(data.error || 'Could not save edit.');
                renderOverrideDrugDetail(data.drug);
                loadEditedDrugList($('#editDrugSearchInput').val());
                if (typeof performSearch === 'function') performSearch($('#dbSearchInput').val(), true);
            })
            .catch(err => alert(err.message || 'Could not save edit.'));
    }

    function deleteSelectedOverrideDrug() {
        if (!selectedOverrideDrug) return;
        const id = selectedOverrideDrug.system_brand_id || selectedOverrideDrug.id;
        const name = selectedOverrideDrug.brand_name || 'this edit';
        if (!confirm(`Delete edit for ${name}? Original drug will remain.`)) return;

        fetch('api/user_drugs.php?action=remove_override', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not delete edit.');
                showEditDrugWorkspaceEmpty();
                loadEditedDrugList($('#editDrugSearchInput').val());
                if (typeof performSearch === 'function') performSearch($('#dbSearchInput').val(), true);
            })
            .catch(err => alert(err.message || 'Could not delete edit.'));
    }

    function showDeleteDrugWorkspaceEmpty() {
        selectedDeleteDrug = null;
        selectedDeleteDrugHidden = false;
        $('#deleteDrugPicker, #deleteDrugReadonly').hide();
        $('#deleteDrugWorkspaceEmpty').show();
        $('#deleteDrugResultsList .res-row').removeClass('active');
    }

    function showDeleteDrugPicker() {
        $('#deleteDrugWorkspaceEmpty, #deleteDrugReadonly').hide();
        $('#deleteDrugPicker').show();
        $('#deleteDrugSystemSearchInput').val('').focus();
        loadDeleteDrugSystemResults('');
    }

    function renderDeleteDrugDetail(drug, isHidden = false) {
        selectedDeleteDrug = drug;
        selectedDeleteDrugHidden = isHidden;
        $('#deleteDrugViewLabel').text(isHidden ? 'Deleted Drug' : 'Delete Drug');
        $('#deleteDrugViewBrand').text(drug.brand_name || drug.pres_new_upper || drug.id || '');
        $('#deleteDrugViewGeneric').text(drug.generic_name || drug.generic || '-');
        $('#deleteDrugViewStrength').text(drug.strength || '-');
        $('#deleteDrugViewForm').text(drug.form || drug.form_new || '-');
        $('#deleteDrugViewManufacturer').text(drug.manufacturer_name || drug.manufacturer || '-');
        $('#deleteDrugViewPrice').text(drug.price || '-');
        $('#deleteDrugViewStatus').text(isHidden ? 'Hidden from search.' : 'Active. Click Delete to hide from search.');
        $('#restoreDeletedDrugBtn').toggle(isHidden);
        $('#confirmDeleteDrugBtn').toggle(!isHidden);
        $('#deleteDrugWorkspaceEmpty, #deleteDrugPicker').hide();
        $('#deleteDrugReadonly').show();
        $('#deleteDrugResultsList .res-row').removeClass('active');
        if (isHidden) {
            const activeId = String(drug.id || drug.brand_id || '');
            $('#deleteDrugResultsList .res-row').filter(function() {
                return String($(this).data('hiddenId') || '') === activeId;
            }).addClass('active');
        }
    }

    function renderDeletedDrugList(rows) {
        const list = $('#deleteDrugResultsList').empty();
        deletedDrugRowStore.clear();
        if (!rows.length) {
            list.html('<div class="new-drug-list-empty">No deleted drugs found.</div>');
            return;
        }

        rows.forEach(row => {
            const id = String(row.id || '');
            deletedDrugRowStore.set(id, row);
            list.append(`
                <div class="res-row" data-hidden-id="${escapeHtml(id)}">
                    <i class="fas fa-trash new-drug-row-icon"></i>
                    <div class="res-info">
                        <div class="res-line-1">
                            <span class="res-brand">${escapeHtml(row.brand_name || id)}</span>
                            <span class="res-price">${escapeHtml(row.price || '')}</span>
                        </div>
                        <div class="res-line-2">${escapeHtml(row.generic_name || '')}</div>
                        <div class="res-line-3">${escapeHtml([row.strength, row.form].filter(Boolean).join(' | '))}</div>
                        <div class="res-line-4">${escapeHtml(row.manufacturer || '')}</div>
                    </div>
                </div>
            `);
        });
    }

    function loadDeletedDrugList(query = '') {
        $('#deleteDrugResultsList').scrollTop(0).html('<div class="new-drug-list-empty">Loading...</div>');
        fetch(`api/user_drugs.php?action=hidden&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not load deleted drugs.');
                renderDeletedDrugList(data.rows || []);
            })
            .catch(err => $('#deleteDrugResultsList').html(`<div class="new-drug-list-empty">${escapeHtml(err.message || 'Could not load deleted drugs.')}</div>`));
    }

    function renderDeleteDrugSystemResults(rows) {
        const list = $('#deleteDrugSystemResults').empty();
        if (!rows.length) {
            list.html('<div class="new-drug-list-empty">No drugs found.</div>');
            return;
        }

        rows.forEach(row => {
            const id = String(row.id || row.brand_id || '');
            list.append(`
                <div class="res-row" data-system-id="${escapeHtml(id)}">
                    <img src="${escapeHtml(getDosageFormIcon(row.form, row.pres_new_upper) || 'assets/images/dosage-form-images/fallback.svg')}" class="res-icon" alt="">
                    <div class="res-info">
                        <div class="res-line-1">
                            <span class="res-brand">${escapeHtml(row.brand_name || row.pres_new_upper || '')}</span>
                            <span class="res-price">${escapeHtml(row.price || '')}</span>
                        </div>
                        <div class="res-line-2">${escapeHtml(row.generic_name || row.generic || '')}</div>
                        <div class="res-line-3">${escapeHtml([row.strength, row.form].filter(Boolean).join(' | '))}</div>
                        <div class="res-line-4">${escapeHtml(row.manufacturer || row.manufacturer_name || '')}</div>
                    </div>
                </div>
            `);
        });
    }

    function loadDeleteDrugSystemResults(query = '') {
        $('#deleteDrugSystemResults').scrollTop(0).html('<div class="new-drug-list-empty">Loading...</div>');
        $.getJSON(`api/drug_explorer.php?type=brand&q=${encodeURIComponent(query)}`, function(data) {
            renderDeleteDrugSystemResults(data || []);
        }).fail(function() {
            $('#deleteDrugSystemResults').html('<div class="new-drug-list-empty">Could not load drugs.</div>');
        });
    }

    function startDeleteFromBrand(id) {
        fetch(`api/user_drugs.php?action=get&id=${encodeURIComponent(id)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.drug) throw new Error(data.error || 'Could not load drug.');
                renderDeleteDrugDetail(data.drug, false);
            })
            .catch(err => alert(err.message || 'Could not load drug.'));
    }

    function enterDeleteDrugMode() {
        $('.db-tab, .db-subtab').removeClass('active');
        $('#deleteDrugTab').addClass('active');
        currentMode = 'delete';
        setDeleteDrugModeVisible(true);
        showDeleteDrugWorkspaceEmpty();
        $('#deleteDrugSearchInput').val('');
        loadDeletedDrugList('');

        const url = new URL(window.location);
        url.searchParams.set('mode', 'delete');
        ['brand_id', 'generic_id', 'indication_id', 'class_id', 'brand_search', 'generic_search', 'indication_search', 'class_search'].forEach(param => {
            url.searchParams.delete(param);
        });
        window.history.replaceState({}, '', url);
    }

    function leaveDeleteDrugMode() {
        if (!$('#deleteDrugWorkspace').is(':visible')) return;
        setDeleteDrugModeVisible(false);
    }

    function confirmSelectedDeleteDrug() {
        if (!selectedDeleteDrug || selectedDeleteDrugHidden) return;
        const id = selectedDeleteDrug.id || selectedDeleteDrug.brand_id || selectedDeleteDrug.system_brand_id || selectedDeleteDrug.local_drug_id;
        const name = selectedDeleteDrug.brand_name || selectedDeleteDrug.pres_new_upper || 'this drug';
        if (!confirm(`Delete ${name} from search? You can restore it later.`)) return;

        fetch('api/user_drugs.php?action=hide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, snapshot: selectedDeleteDrug })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not delete drug.');
                showDeleteDrugWorkspaceEmpty();
                loadDeletedDrugList($('#deleteDrugSearchInput').val());
                if (typeof performSearch === 'function') performSearch($('#dbSearchInput').val(), true);
            })
            .catch(err => alert(err.message || 'Could not delete drug.'));
    }

    function restoreSelectedDeletedDrug() {
        if (!selectedDeleteDrug || !selectedDeleteDrugHidden) return;
        const id = selectedDeleteDrug.id || selectedDeleteDrug.brand_id;
        fetch('api/user_drugs.php?action=restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) throw new Error(data.error || 'Could not restore drug.');
                showDeleteDrugWorkspaceEmpty();
                loadDeletedDrugList($('#deleteDrugSearchInput').val());
                if (typeof performSearch === 'function') performSearch($('#dbSearchInput').val(), true);
            })
            .catch(err => alert(err.message || 'Could not restore drug.'));
    }

    function bindUserDrugManagement() {
        $('#newDrugTab').on('click', function() {
            enterNewDrugMode();
        });
        $('.db-tab[data-type], #docsBooksPapersTab').on('click', function() {
            leaveNewDrugMode();
            leaveEditDrugMode();
            leaveDeleteDrugMode();
        });
        $('#editDrugTab').on('click', function() {
            enterEditDrugMode();
        });
        $('#deleteDrugTab').on('click', function() {
            leaveNewDrugMode();
            leaveEditDrugMode();
            enterDeleteDrugMode();
        });
        $('#drugEditorForm').on('submit', saveUserDrug);
        $('#hideCurrentDrugBtn').on('click', hideCurrentDrug);
        $('#hiddenDrugList').on('click', '.restore-hidden-drug-btn', function() {
            restoreHiddenDrug($(this).data('restoreId'));
        });
        $('#drugEditorBrand, #drugEditorStrength, #drugEditorFormName').on('input', function() {
            refreshDrugEditorAutoText(false);
        });
        $('#addNewDrugBtn').on('click', function() {
            selectedCustomDrug = null;
            $('#newDrugResultsList .res-row').removeClass('active');
            showNewDrugForm();
        });
        $('#cancelNewDrugFormBtn').on('click', function() {
            if (selectedCustomDrug) renderCustomDrugDetail(selectedCustomDrug);
            else showNewDrugWorkspaceEmpty();
        });
        $('#newDrugInlineForm').on('submit', saveNewDrug);
        $('#newDrugBrand, #newDrugStrength, #newDrugFormName').on('input', function() {
            refreshNewDrugAutoText(false);
        });
        $('#newDrugShort').on('input', function() {
            newDrugShortManual = true;
        });
        $('#newDrugLong').on('input', function() {
            newDrugLongManual = true;
        });
        $('#newDrugSearchInput').on('input', function() {
            clearTimeout(newDrugSearchTimer);
            const query = $(this).val();
            newDrugSearchTimer = setTimeout(() => loadCustomDrugList(query), 120);
        });
        $('#newDrugResultsList').on('click', '.res-row', function() {
            loadCustomDrug($(this).data('customId'));
        });
        $('#editCustomDrugBtn').on('click', function() {
            if (selectedCustomDrug) showNewDrugForm(selectedCustomDrug);
        });
        $('#deleteCustomDrugBtn').on('click', deleteSelectedCustomDrug);
        $('#editDrugPickBtn').on('click', showEditDrugPicker);
        $('#cancelEditDrugPickerBtn').on('click', showEditDrugWorkspaceEmpty);
        $('#cancelEditDrugFormBtn').on('click', function() {
            if (selectedOverrideDrug) renderOverrideDrugDetail(selectedOverrideDrug);
            else showEditDrugWorkspaceEmpty();
        });
        $('#editDrugInlineForm').on('submit', saveEditedDrug);
        $('#editDrugBrand, #editDrugStrength, #editDrugFormName').on('input', function() {
            refreshEditDrugAutoText(false);
        });
        $('#editDrugShort').on('input', function() {
            editDrugShortManual = true;
        });
        $('#editDrugLong').on('input', function() {
            editDrugLongManual = true;
        });
        $('#editDrugSearchInput').on('input', function() {
            clearTimeout(editDrugSearchTimer);
            const query = $(this).val();
            editDrugSearchTimer = setTimeout(() => loadEditedDrugList(query), 120);
        });
        $('#editDrugSystemSearchInput').on('input', function() {
            clearTimeout(editDrugSystemSearchTimer);
            const query = $(this).val();
            editDrugSystemSearchTimer = setTimeout(() => loadEditDrugSystemResults(query), 160);
        });
        $('#editDrugResultsList').on('click', '.res-row', function() {
            loadOverrideDrug($(this).data('overrideId'));
        });
        $('#editDrugSystemResults').on('click', '.res-row', function() {
            startEditOverrideFromBrand($(this).data('systemId'));
        });
        $('#editOverrideDrugBtn').on('click', function() {
            if (selectedOverrideDrug) fillEditDrugForm(selectedOverrideDrug);
        });
        $('#deleteOverrideDrugBtn').on('click', deleteSelectedOverrideDrug);
        $('#deleteDrugPickBtn').on('click', showDeleteDrugPicker);
        $('#cancelDeleteDrugPickerBtn').on('click', showDeleteDrugWorkspaceEmpty);
        $('#deleteDrugSearchInput').on('input', function() {
            clearTimeout(deleteDrugSearchTimer);
            const query = $(this).val();
            deleteDrugSearchTimer = setTimeout(() => loadDeletedDrugList(query), 120);
        });
        $('#deleteDrugSystemSearchInput').on('input', function() {
            clearTimeout(deleteDrugSystemSearchTimer);
            const query = $(this).val();
            deleteDrugSystemSearchTimer = setTimeout(() => loadDeleteDrugSystemResults(query), 160);
        });
        $('#deleteDrugResultsList').on('click', '.res-row', function() {
            const drug = deletedDrugRowStore.get(String($(this).data('hiddenId') || ''));
            if (!drug) {
                alert('Could not load deleted drug.');
                return;
            }
            renderDeleteDrugDetail(drug, true);
        });
        $('#deleteDrugSystemResults').on('click', '.res-row', function() {
            startDeleteFromBrand($(this).data('systemId'));
        });
        $('#confirmDeleteDrugBtn').on('click', confirmSelectedDeleteDrug);
        $('#restoreDeletedDrugBtn').on('click', restoreSelectedDeletedDrug);
        if (new URLSearchParams(window.location.search).get('mode') === 'new') {
            enterNewDrugMode();
        }
        if (new URLSearchParams(window.location.search).get('mode') === 'edit') {
            enterEditDrugMode();
        }
        if (new URLSearchParams(window.location.search).get('mode') === 'delete') {
            enterDeleteDrugMode();
        }
    }

    $(document).ready(bindUserDrugManagement);

    function sortAltTable(columnIndex) {
        const table = document.getElementById("altTable");
        let rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
        switching = true; dir = "asc";
        while (switching) {
            switching = false; rows = table.rows;
            for (i = 1; i < (rows.length - 1); i++) {
                shouldSwitch = false;
                x = rows[i].getElementsByTagName("TD")[columnIndex];
                y = rows[i + 1].getElementsByTagName("TD")[columnIndex];
                if (dir == "asc") { if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) { shouldSwitch = true; break; } }
                else if (dir == "desc") { if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) { shouldSwitch = true; break; } }
            }
            if (shouldSwitch) { rows[i].parentNode.insertBefore(rows[i + 1], rows[i]); switching = true; switchcount++; }
            else { if (switchcount == 0 && dir == "asc") { dir = "desc"; switching = true; } }
        }
    }
