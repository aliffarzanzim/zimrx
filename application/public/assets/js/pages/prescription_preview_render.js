// Prescription print preview renderer generating HTML layout, slots, drug table, and interactive doctor stamp.
const previewOptionsEl = document.getElementById('previewOptionsData');
let previewOptions = previewOptionsEl ? JSON.parse(previewOptionsEl.textContent || '{}') : {};
const previewDefaultEl = document.getElementById('previewDefaultDataJson');
const previewDefaultData = previewDefaultEl ? JSON.parse(previewDefaultEl.textContent || '{}') : {};
let previewCurrentData = previewDefaultData;

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
    return '';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function cleanText(value) {
    if (Array.isArray(value)) {
        return value.map(cleanText).filter(Boolean).join(' ').trim();
    }
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function cleanList(items) {
    return Array.isArray(items) ? items.map(cleanText).filter(Boolean) : [];
}

function isPlaceholderSidebarItem(text, slot) {
    const normalizedSlot = String(slot || '').toLowerCase();
    if (!['oh', 'mh'].includes(normalizedSlot)) return false;

    const normalized = cleanText(text);
    if (!normalized) return true;

    if (normalized.includes(':')) {
        const value = cleanText(normalized.split(':').slice(1).join(':'));
        return !value || /^(years?|months?|days?)$/i.test(value);
    }

    return /^(married for|alc|para|gravida|age of menarche|mp|mc|lmp|edd)$/i.test(normalized);
}

function cleanSidebarList(items, slot) {
    return cleanList(items).filter(item => !isPlaceholderSidebarItem(item, slot));
}

function fieldLabel(field) {
    const labels = {
        name: 'Name', age: 'Age', sex: 'Sex', date: 'Date',
        address: 'Address', regno: 'Reg No.', bmi_weigh: 'Wt',
        mobile: 'Mobile', ref_by: 'Ref By', visit_no: 'Visit No'
    };
    const optionKey = optionFieldKey(field);
    const configuredLabel = `patient_label_${optionKey}`;
    return Object.prototype.hasOwnProperty.call(previewOptions, configuredLabel)
        ? cleanText(previewOptions[configuredLabel])
        : (labels[field] || '');
}

function optionFieldKey(field) {
    if (field === 'bmi_weigh') return 'weight';
    if (field === 'regno') return 'reg_no';
    return field;
}

function patientValueKey(field) {
    return field === 'bmi_weigh' ? 'weight' : field;
}

function showPatientValue(field) {
    if (!field) return false;
    return (previewOptions[`display_${optionFieldKey(field)}`] || 'yes') === 'yes';
}

function showPatientLabel(field) {
    return (previewOptions[`display_${optionFieldKey(field)}_t`] || 'yes') === 'yes';
}

function patientOrderHtml(order, patient, singleRow) {
    const fieldOrder = {
        1: 'name',
        2: 'age',
        3: 'sex',
        4: 'date',
        5: 'address',
        6: 'regno',
        7: 'bmi_weigh',
        8: 'mobile'
    };
    const field = fieldOrder[order] || '';
    if (!showPatientValue(field)) return '';
    let value = cleanText(patient[patientValueKey(field)] || '');
    if (field === 'bmi_weigh' && /^(kg|kgs|kilogram|kilograms|lb|lbs)$/i.test(value)) {
        value = '';
    }

    const label = fieldLabel(field);
    let html = '';
    if (showPatientLabel(field) && label) {
        if (singleRow) {
            html += `<td class="zrx-patient-label zrx-patient-label-slot-${order}">${escapeHtml(label)} :</td>`;
        } else {
            html += `<td class="zrx-patient-label zrx-patient-label-slot-${order}">${escapeHtml(label)}</td><td class="zrx-patient-colon">:</td>`;
        }
    }
    const addressClass = field === 'address' ? ' zrx-patient-address-value' : '';
    const addressData = field === 'address' ? ` data-full-text="${escapeHtml(value)}"` : '';
    html += `<td id="preview-field-${order}" class="zrx-patient-value zrx-patient-position-${order}${addressClass}"${addressData}>${escapeHtml(value)}</td>`;
    return html;
}

function addressCandidates(value) {
    const text = cleanText(value);
    if (!text) return [''];

    const commaParts = text.split(',').map(cleanText).filter(Boolean);
    if (commaParts.length > 1) {
        const candidates = [];
        for (let count = commaParts.length; count >= 1; count -= 1) {
            candidates.push(commaParts.slice(0, count).join(', '));
        }
        return candidates;
    }

    const words = text.split(/\s+/).filter(Boolean);
    if (words.length <= 1) return [text];

    const candidates = [];
    for (let count = words.length; count >= 1; count -= 1) {
        candidates.push(words.slice(0, count).join(' '));
    }
    return candidates;
}

function textWidthForElement(text, element) {
    const style = window.getComputedStyle(element);
    const canvas = textWidthForElement.canvas || document.createElement('canvas');
    textWidthForElement.canvas = canvas;
    const context = canvas.getContext('2d');
    context.font = `${style.fontStyle} ${style.fontVariant} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
    return context.measureText(text).width;
}

function fitPatientAddresses() {
    document.querySelectorAll('.zrx-patient-address-value').forEach(cell => {
        const fullText = cleanText(cell.dataset.fullText || cell.textContent || '');
        if (!fullText) {
            cell.textContent = '';
            cell.removeAttribute('title');
            return;
        }

        cell.textContent = '';
        const style = window.getComputedStyle(cell);
        const padding = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
        const availableWidth = Math.max(0, cell.getBoundingClientRect().width - padding - 1);
        const candidates = addressCandidates(fullText);
        const fitted = candidates.find(candidate => textWidthForElement(candidate, cell) <= availableWidth) || candidates[candidates.length - 1] || fullText;

        cell.textContent = fitted;
        if (fitted !== fullText) {
            cell.title = fullText;
        } else {
            cell.removeAttribute('title');
        }
    });
}

function schedulePatientAddressFit() {
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(fitPatientAddresses);
    });
}

function fitHistoryLabelGaps() {
    document.querySelectorAll('.zrx-history-label').forEach(label => {
        label.style.marginRight = '';
        const layoutWidth = label.offsetWidth;
        const visualWidth = label.getBoundingClientRect().width;
        const trimmedWidth = Math.max(0, layoutWidth - visualWidth);
        label.style.marginRight = trimmedWidth ? `${-trimmedWidth}px` : '';
    });
}

function scheduleHistoryLabelGapFit() {
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(fitHistoryLabelGaps);
    });
}

function renderPatientTable(patient) {
    const singleRow = String(previewOptions.info_row || '2') === '1';
    if (singleRow) {
        let row = '';
        for (let i = 1; i <= 8; i += 1) row += patientOrderHtml(i, patient, true);
        return `<table class="zrx-patient-table"><tbody><tr>${row}</tr></tbody></table>`;
    }

    let first = '';
    let second = '';
    for (let i = 1; i <= 4; i += 1) first += patientOrderHtml(i, patient, false);
    for (let i = 5; i <= 8; i += 1) second += patientOrderHtml(i, patient, false);
    return `<table class="zrx-patient-table"><tbody><tr>${first}</tr><tr>${second}</tr></tbody></table>`;
}

function sectionTable(title, rows, modifier) {
    if (!rows.length) return '';
    return `<div class="zrx-clinical-section ${modifier || ''}"><div class="zrx-section-title">${escapeHtml(title)}</div><table class="zrx-section-table"><tbody>${rows.join('')}</tbody></table></div>`;
}

function simpleSection(items, title, bullet, modifier, slot) {
    const pcFormat = previewOptions.pc_format || 'parentheses';
    const countUnits = ['episode', 'episodes', 'attack', 'attacks', 'time', 'times', 'occasion', 'occasions'];
    const rows = cleanSidebarList(items, slot).map(item => {
        let text = item;
        if (slot === 'pc') {
            const matches = item.match(/^(.+?)\s*\(([^)]+)\)$/);
            if (matches) {
                const complaint = matches[1].trim();
                const durationText = matches[2].trim();

                const durMatches = durationText.match(/^([\d\.\-\s০-৯]+)\s*(.+)$/);
                if (durMatches) {
                    const count = durMatches[1].trim();
                    const unit = durMatches[2].trim();
                    const unitLower = unit.toLowerCase();

                    const isCountUnit = countUnits.some(cu => unitLower.includes(cu));
                    if (isCountUnit) {
                        if (pcFormat === 'for') {
                            text = `${count} ${unit} of ${complaint}`;
                        } else if (pcFormat === 'hyphen') {
                            text = `${complaint} - ${count} ${unit}`;
                        } else {
                            text = `${complaint} (${count} ${unit})`;
                        }
                    } else {
                        if (pcFormat === 'for') {
                            text = `${complaint} for ${count} ${unit}`;
                        } else if (pcFormat === 'hyphen') {
                            text = `${complaint} - ${count} ${unit}`;
                        } else {
                            text = `${complaint} (${count} ${unit})`;
                        }
                    }
                } else {
                    if (pcFormat === 'for') {
                        text = `${complaint} for ${durationText}`;
                    } else if (pcFormat === 'hyphen') {
                        text = `${complaint} - ${durationText}`;
                    } else {
                        text = `${complaint} (${durationText})`;
                    }
                }
            }
        }
        return `<tr><td class="zrx-bullet-cell">${escapeHtml(bullet)}</td><td>${escapeHtml(text)}</td></tr>`;
    });
    return sectionTable(title, rows, modifier);
}

function renderDxSection(items, title) {
    const format = previewOptions.dx_format || 'per_line';
    let dxBullet = cleanText(previewOptions.dx_bullet || '');
    if (!dxBullet) {
        let bullet = cleanText(previewOptions.bullet_text || '');
        if (!bullet || bullet === '\u00e2\u2014\u2039') bullet = '○';
        dxBullet = bullet;
    }

    const cleaned = cleanSidebarList(items, 'dx').map(item => cleanText(item)).filter(Boolean);
    if (!cleaned.length) return '';

    let rows;
    if (format === 'single_line') {
        const line = cleaned.map(t => escapeHtml(t)).join(' ē ');
        rows = [`<tr><td class="zrx-bullet-cell">${escapeHtml(dxBullet)}</td><td>${line}</td></tr>`];
    } else {
        rows = cleaned.map(t => `<tr><td class="zrx-bullet-cell">${escapeHtml(dxBullet)}</td><td>${escapeHtml(t)}</td></tr>`);
    }

    return sectionTable(title, rows, 'zrx-clinical-section--dx');
}

function renderPeSection(items, title) {
    const rows = [];
    (Array.isArray(items) ? items : []).forEach(item => {
        const name = typeof item === 'object' && item ? cleanText(item.name || '') : '';
        const value = typeof item === 'object' && item ? cleanText(item.value || '') : cleanText(item);
        if (!name && !value) return;
        const colon = name && value ? ':' : '';
        rows.push(`<tr><td class="zrx-label-cell">${escapeHtml(name)}</td><td class="zrx-colon-cell">${colon}</td><td>${escapeHtml(value)}</td></tr>`);
    });
    return sectionTable(title, rows, 'zrx-clinical-section--pe zrx-clinical-section--oe');
}
const renderOeSection = renderPeSection;

function renderReportSection(items, title) {
    const rows = [];
    (Array.isArray(items) ? items : []).forEach(item => {
        if (!item || typeof item !== 'object') return;
        const name = cleanText(item.name || '');
        const date = cleanText(item.date || '');
        const value = cleanText(item.value || '');
        if (!name && !date && !value) return;
        const left = `${name ? `<b>${escapeHtml(name)}</b>` : ''}${date ? `<br><span class="zrx-report-date">${escapeHtml(date)}</span>` : ''}`;
        const right = value ? ` : ${escapeHtml(value)}` : '';
        rows.push(`<tr><td class="zrx-report-name">${left}</td><td class="zrx-report-value">${right}</td></tr>`);
    });
    return sectionTable(title, rows, 'zrx-clinical-section--reports');
}

function historyList(items) {
    if (!Array.isArray(items)) {
        const text = cleanText(items);
        return text ? [text] : [];
    }
    return items.map(item => {
        if (item && typeof item === 'object') {
            return cleanText(item.label || item.value || item.name || '');
        }
        return cleanText(item);
    }).filter(Boolean);
}

function historyTreatments(items) {
    if (!Array.isArray(items)) return [];
    return items.map(item => {
        if (item && typeof item === 'object') {
            const procedure = cleanText(item.procedure || item.name || '');
            const year = cleanText(item.year || '');
            if (!procedure && !year) return '';
            return procedure && year ? `${procedure} (${year})` : (procedure || year);
        }
        return cleanText(item);
    }).filter(Boolean);
}

function historyPayload(clinical) {
    const history = clinical && typeof clinical.history === 'object' && clinical.history ? clinical.history : {};
    let diet = cleanText(history.diet || '');
    if (diet === 'Standard / Normal') diet = 'Standard';

    return {
        medical: historyList(history.medical || clinical.ho || []),
        treatments: historyTreatments(history.treatments || []),
        habits: historyList(history.habits || []),
        diet,
        hypersensitivity: historyList(history.hypersensitivity || []),
        drug_history: historyList(history.drug_history || clinical.dh || [])
    };
}

function renderHistorySection(clinical, bullet) {
    const history = historyPayload(clinical || {});
    const submoduleHtml = {};

    const medicalLabel = previewOptions.lbl_history_medical || 'Medical History:';
    const treatmentsLabel = previewOptions.lbl_history_treatments || 'Treatment History:';
    const habitsLabel = previewOptions.lbl_history_habits || 'Habits:';
    const dietLabel = previewOptions.lbl_history_diet || 'Diet:';
    const hypersensitivityLabel = previewOptions.lbl_history_hypersensitivity || 'Hypersensitivity:';
    const drugLabel = previewOptions.lbl_history_drug || 'Drug History:';

    // Medical history
    const medicalItems = history.medical || [];
    if (medicalItems.length) {
        submoduleHtml['medical'] = `<tr><td><span class="zrx-history-label zrx-history-label--medical">${escapeHtml(medicalLabel)}</span> ${escapeHtml(medicalItems.join(', '))}</td></tr>`;
    }

    // Treatment history
    const treatmentItems = history.treatments || [];
    if (treatmentItems.length) {
        submoduleHtml['treatment'] = `<tr><td><span class="zrx-history-label zrx-history-label--treatments">${escapeHtml(treatmentsLabel)}</span> ${escapeHtml(treatmentItems.join(', '))}</td></tr>`;
    }

    // Habits and lifestyle
    const habitsItems = history.habits || [];
    if (habitsItems.length) {
        submoduleHtml['habits'] = `<tr><td><span class="zrx-history-label zrx-history-label--habits">${escapeHtml(habitsLabel)}</span> ${escapeHtml(habitsItems.join(', '))}</td></tr>`;
    }

    // Diet and hypersensitivity
    let dietHypersensitivityHtml = '';
    if (history.diet) {
        dietHypersensitivityHtml += `<tr><td><span class="zrx-history-label zrx-history-label--diet">${escapeHtml(dietLabel)}</span> ${escapeHtml(history.diet)}</td></tr>`;
    }
    if (history.hypersensitivity.length) {
        dietHypersensitivityHtml += `<tr><td><span class="zrx-history-label zrx-history-label--hypersensitivity">${escapeHtml(hypersensitivityLabel)}</span> ${escapeHtml(history.hypersensitivity.join(', '))}</td></tr>`;
    }
    if (dietHypersensitivityHtml) {
        submoduleHtml['diet-hypersensitivity'] = dietHypersensitivityHtml;
    }

    // Drug history
    if (history.drug_history.length) {
        const drugLines = history.drug_history.map(drug => `<span class="zrx-history-bullet">&#9675; ${escapeHtml(drug)}</span>`).join('');
        submoduleHtml['drug-history'] = `<tr><td><span class="zrx-history-label zrx-history-label--drug-history">${escapeHtml(drugLabel)}</span>${drugLines}</td></tr>`;
    }

    // Sort according to layout
    let historyLayout = ['medical', 'treatment', 'habits', 'diet-hypersensitivity', 'drug-history'];
    const cookieVal = getCookie('zimrx_history_layout');
    if (cookieVal) {
        try {
            const decoded = JSON.parse(cookieVal);
            if (Array.isArray(decoded)) {
                historyLayout = decoded;
            }
        } catch(e) {}
    }

    const rows = [];
    historyLayout.forEach(subName => {
        if (subName !== '' && submoduleHtml[subName]) {
            rows.push(submoduleHtml[subName]);
        }
    });

    return sectionTable('History', rows, 'zrx-clinical-section--history');
}

function previewSectionTitle(title, fallback, fallbackAliases = []) {
    const cleaned = cleanText(title || '');
    const aliases = [fallback, ...fallbackAliases].map(alias => String(alias).toLowerCase());
    return !cleaned || aliases.includes(cleaned.toLowerCase()) ? fallback : cleaned;
}

function renderLeftSections(clinical) {
    const titles = {
        pc: previewSectionTitle(previewOptions.pc_name, 'Presenting Complaints', ['P/C', 'P/C']),
        ho: previewOptions.history_name || 'History',
        pe: previewSectionTitle(previewOptions.pe_name || previewOptions.oe_name, 'Physical Examination', ['P/E']),
        oe: previewSectionTitle(previewOptions.pe_name || previewOptions.oe_name, 'Physical Examination', ['P/E']),
        reports: previewOptions.report_name || 'Reports',
        dh: previewOptions.dh_name || 'D/H',
        plan: previewOptions.plan_name || 'Plan',
        advice: previewOptions.ix_name || 'Investigations',
        note: previewOptions.note_name || 'Note',
        oh: previewOptions.oh_name || 'O/H',
        mh: previewOptions.mh_name || 'M/H',
        paediatric: previewOptions.paediatric_name || 'Paediatric History',
        ph: previewOptions.ph_name || 'Paediatric History',
        dx: previewOptions.dx_name || 'Dx',
        edd: previewOptions.edd_name || 'OT Note',
        otnote: previewOptions.edd_name || 'OT Note'
    };

    let bullet = cleanText(previewOptions.bullet_text || '');
    if (!bullet || bullet === '\u00e2\u2014\u2039') bullet = '○';

    let html = '';
    let historyRendered = false;
    for (let i = 1; i <= 14; i += 1) {
        const slot = previewOptions[`print_pos_${i}`] || 'none';
        if (slot === 'none') continue;

        if (slot === 'ho' || slot === 'dh' || slot === 'history') {
            if (!historyRendered) {
                html += renderHistorySection(clinical, bullet);
                historyRendered = true;
            }
            continue;
        }

        let items = slot === 'advice' ? (clinical.ix || []) : (slot === 'edd' ? (clinical.edd || clinical.otnote || []) : (clinical[slot] || []));
        if (!Array.isArray(items) || !items.length) continue;

        if (slot === 'pe' || slot === 'oe') {
            html += renderPeSection(items, titles.pe || titles.oe);
        } else if (slot === 'reports') {
            html += renderReportSection(items, titles.reports);
        } else if (slot === 'dx') {
            html += renderDxSection(items, titles.dx || 'Dx');
        } else {
            html += simpleSection(items, titles[slot] || slot, bullet, `zrx-clinical-section--${slot.replace(/[^a-z0-9_-]/gi, '')}`, slot);
        }
    }
    return html;
}

function getPrintDrugValue(drug, keys, fallback = '') {
    for (const key of keys) {
        const value = cleanText(drug && drug[key]);
        if (value) return value;
    }
    return cleanText(fallback);
}

function getPrintDrugBrand(drug) {
    const isShort = String(previewOptions.suffix_prefix_usage || 'full') === 'short';
    const keys = isShort
        ? ['pres_new_upper', 'prescribe_brand_short', 'full_form_brand_name', 'prescribe_brand_full', 'brand_name', 'brand']
        : ['full_form_brand_name', 'prescribe_brand_full', 'pres_new_upper', 'prescribe_brand_short', 'brand_name', 'brand'];
    return getPrintDrugValue(drug, keys, drug && drug.brand);
}

function getPrintDrugGeneric(drug) {
    const format = String(previewOptions.print_generic_name_format || 'plain');
    const isShort = String(previewOptions.suffix_prefix_usage || 'full') === 'short';
    const plain = getPrintDrugValue(drug, ['generic_name', 'generic'], drug && drug.generic);

    if (format === 'prescribe') {
        const keys = isShort
            ? ['prescribe_generic_short', 'prescribe_generic_full', 'generic_name', 'generic']
            : ['prescribe_generic_full', 'prescribe_generic_short', 'generic_name', 'generic'];
        return getPrintDrugValue(drug, keys, plain);
    }

    if (format === 'labelled') {
        const keys = isShort
            ? ['labelled_generic_short', 'labelled_generic_full', 'generic_name', 'generic']
            : ['labelled_generic_full', 'labelled_generic_short', 'generic_name', 'generic'];
        return getPrintDrugValue(drug, keys, plain);
    }

    return plain;
}

function getPrintDrugNumberLabel(number) {
    const style = String(previewOptions.drug_no_style || 'period');
    if (style === 'round_brackets') return `(${number})`;
    if (style === 'closing_bracket') return `${number})`;
    if (style === 'square_brackets') return `[${number}]`;
    return `${number}.`;
}

function getPrintLanguageValue(item, field) {
    const language = String(previewOptions[`${field}_language`] || 'bengali');
    const preferred = cleanText(item?.[`${field}_${language}`] || '');
    return preferred || cleanText(item?.[field] || '');
}

function renderDrugRows(drugs) {
    const showGeneric = (previewOptions.disp_generic || 'yes') === 'yes';
    const showDrugNo = (previewOptions.display_drug_no || 'yes') !== 'no';
    let drugBullet = cleanText(previewOptions.drug_bullet || '');
    if (!drugBullet || drugBullet === 'â€¢') drugBullet = '•';

    let html = '';
    let counter = 1;
    let isFirstDrug = true;
    (Array.isArray(drugs) ? drugs : []).forEach(drug => {
        const brand = getPrintDrugBrand(drug);
        const generic = getPrintDrugGeneric(drug);
        const drugRowFormat = String(previewOptions.drug_row_format || 'standard');

        const dose = getPrintLanguageValue(drug, 'dose');
        const instruction = getPrintLanguageValue({
            ...drug,
            instruction: drug.instruction || drug.food || ''
        }, 'instruction');
        const duration = getPrintLanguageValue(drug, 'duration');
        if (!brand && !generic && !dose && !instruction && !duration) return;

        const isContinuation = (!brand && !generic);

        if (!isContinuation) {
            if (!isFirstDrug) {
                html += '<tr><td colspan="4" class="zrx-drug-gap">&nbsp;</td></tr>';
            }
            isFirstDrug = false;
        }

        const drugName = brand || generic;

        let numberHtml = '';
        if (!isContinuation) {
            numberHtml = showDrugNo ? `<td class="zrx-drug-number">${escapeHtml(getPrintDrugNumberLabel(counter))}</td>` : `<td class="zrx-drug-number zrx-drug-bullet">${escapeHtml(drugBullet)}</td>`;
        } else {
            numberHtml = `<td class="zrx-drug-number"></td>`;
        }

        const format = previewOptions.drug_row_format || 'standard';

        if (format === 'labelled') {
            const brandFontSize = (previewOptions.right_font_size || '11') + 'pt';
            const lblGeneric = previewOptions.lbl_generic || 'Generic Name:';
            const lblBrand = previewOptions.lbl_brand || 'Brand Name Recommendation:';
            const lblInstruction = previewOptions.lbl_instruction || 'Instruction:';
            html += `<tr class="zrx-drug-name-row">${numberHtml}<td colspan="3" class="zrx-drug-name-cell">`;
            if (generic) {
                html += `<div style="margin-bottom: 3px;"><span style="font-weight: bold; font-family: 'Tinos';" class="zrx-lbl-generic">${escapeHtml(lblGeneric)} </span><span class="zrx-drug-generic" data-generic="${escapeHtml(generic)}" style="font-family: 'Tinos', serif; font-style: normal; font-weight: normal; font-size: ${brandFontSize}; ${showGeneric ? 'display: inline-block;' : 'display: none;'}">${escapeHtml(generic)}</span></div>`;
            }
            if (brand) {
                html += `<div style="margin-bottom: 3px;"><span style="font-weight: bold; font-family: 'Tinos';" class="zrx-lbl-brand">${escapeHtml(lblBrand)} </span><span class="zrx-drug-brand" style="font-weight: normal;">${escapeHtml(brand)}</span></div>`;
            }
            if (dose || instruction || duration) {
                html += `<div><span style="${isContinuation ? 'visibility: hidden;' : ''} font-weight: bold; font-family: 'Tinos';" class="zrx-lbl-instruction">${escapeHtml(lblInstruction)} </span><span class="zrx-drug-dose">${escapeHtml(dose)}</span><span class="zrx-drug-instruction">${instruction ? '- ' + escapeHtml(instruction) : ''}</span><span class="zrx-drug-duration">${duration ? '- ' + escapeHtml(duration) : ''}</span></div>`;
            }
            html += `</td></tr>`;
        } else {
            if (!isContinuation) {
                html += `<tr class="zrx-drug-name-row">${numberHtml}`;
                html += `<td colspan="3" class="zrx-drug-name-cell"><span class="zrx-drug-brand">${escapeHtml(drugName)}</span>`;
                if (showGeneric && generic && generic !== drugName) {
                    let gFormatted = generic;
                    const wrapper = previewOptions.generic_wrapper || 'none';
                    if (wrapper === 'parentheses') gFormatted = '(' + generic + ')';
                    else if (wrapper === 'brackets') gFormatted = '[' + generic + ']';
                    else if (wrapper === 'hyphen') gFormatted = '- ' + generic;
                    const isBelow = (previewOptions.generic_position || 'below') === 'below';
                    html += ` <span class="zrx-drug-generic" data-generic="${escapeHtml(generic)}" style="display: ${isBelow ? 'block' : 'inline-block'};">${escapeHtml(gFormatted)}</span>`;
                }
                html += '</td></tr>';
            }

            if (dose || instruction || duration) {
                html += `<tr class="zrx-drug-detail-row"><td class="zrx-drug-detail-pad"></td><td class="zrx-drug-dose">${escapeHtml(dose)}</td><td class="zrx-drug-instruction">${instruction ? `- ${escapeHtml(instruction)}` : ''}</td><td class="zrx-drug-duration">${duration ? `- ${escapeHtml(duration)}` : ''}</td></tr>`;
            }
        }

        if (!isContinuation) {
            counter += 1;
        }
    });
    return html;
}

function renderAdvice(advice) {
    const language = String(previewOptions.advice_language || 'bengali');
    const items = (Array.isArray(advice) ? advice : [])
        .map((item) => typeof item === 'object'
            ? cleanText(item[language] || item.value || '')
            : cleanText(item))
        .filter(Boolean);
    if (!items.length) return '';
    return `<table class="zrx-advice-table"><tbody id="preview-advice-rows"><tr><td colspan="2"><u><b>&#x0989;&#x09AA;&#x09A6;&#x09C7;&#x09B6;&#x0983;</b></u></td></tr>${items.map(item => `<tr><td class="zrx-advice-bullet">▪</td><td>${escapeHtml(item)}</td></tr>`).join('')}</tbody></table>`;
}

function getStoredPreviewData() {
    const raw = sessionStorage.getItem('zimrx_preview_snapshot') || localStorage.getItem('zimrx_preview_snapshot');
    try { return raw ? JSON.parse(raw) : null; } catch (e) { return null; }
}

function setHtml(selector, html) {
    const node = document.querySelector(selector);
    if (node) node.innerHTML = html;
}

function toggleRef(patient) {
    const current = document.getElementById('preview-ref-by');
    const value = cleanText(patient.ref_by || '');
    if (!value) {
        if (current) current.remove();
        return;
    }
    if (current) {
        const span = document.getElementById('preview-ref-by-val');
        if (span) span.textContent = value;
        return;
    }
    document.querySelector('.zrx-body-left').insertAdjacentHTML('afterbegin', `<div id="preview-ref-by" class="zrx-ref-by"><b>Ref By:</b> <span id="preview-ref-by-val">${escapeHtml(value)}</span></div>`);
}

const code39Patterns = {
    '0': 'nnnwwnwnn', '1': 'wnnwnnnnw', '2': 'nnwwnnnnw', '3': 'wnwwnnnnn',
    '4': 'nnnwwnnnw', '5': 'wnnwwnnnn', '6': 'nnwwwnnnn', '7': 'nnnwnnwnw',
    '8': 'wnnwnnwnn', '9': 'nnwwnnwnn', 'A': 'wnnnnwnnw', 'B': 'nnwnnwnnw',
    'C': 'wnwnnwnnn', 'D': 'nnnnwwnnw', 'E': 'wnnnwwnnn', 'F': 'nnwnwwnnn',
    'G': 'nnnnnwwnw', 'H': 'wnnnnwwnn', 'I': 'nnwnnwwnn', 'J': 'nnnnwwwnn',
    'K': 'wnnnnnnww', 'L': 'nnwnnnnww', 'M': 'wnwnnnnwn', 'N': 'nnnnwnnww',
    'O': 'wnnnwnnwn', 'P': 'nnwnwnnwn', 'Q': 'nnnnnnwww', 'R': 'wnnnnnwwn',
    'S': 'nnwnnnwwn', 'T': 'nnnnwnwwn', 'U': 'wwnnnnnnw', 'V': 'nwwnnnnnw',
    'W': 'wwwnnnnnn', 'X': 'nwnnwnnnw', 'Y': 'wwnnwnnnn', 'Z': 'nwwnwnnnn',
    '-': 'nwnnnnwnw', '.': 'wwnnnnwnn', ' ': 'nwwnnnwnn', '$': 'nwnwnwnnn',
    '/': 'nwnwnnnwn', '+': 'nwnnnwnwn', '%': 'nnnwnwnwn', '*': 'nwnnwnwnn'
};

function barcodeHtml(value) {
    const clean = cleanText(value).toUpperCase().replace(/[^0-9A-Z\-. $/+%]/g, '');
    if (!clean) return '';
    const encoded = `*${clean}*`;

    const narrowWidth = 1.3;
    const wideWidth = 3.25;
    const barHeight = 32;
    const quietZone = 14;

    const charBlockWidth = (wideWidth * 3) + (narrowWidth * 6);
    const len = encoded.length;

    let rects = '';
    let texts = '';
    let x = quietZone;

    for (let c = 0; c < len; c += 1) {
        const char = encoded[c];
        const pattern = code39Patterns[char] || code39Patterns['-'];
        const charStartX = x;

        for (let i = 0; i < 9; i += 1) {
            const isBar = (i % 2 === 0);
            const width = (pattern[i] === 'w') ? wideWidth : narrowWidth;
            if (isBar) {
                rects += `<rect x="${x.toFixed(2)}" y="0" width="${width.toFixed(2)}" height="${barHeight}" fill="#000000"/>`;
            }
            x += width;
        }

        const charCenterX = charStartX + (charBlockWidth / 2);
        texts += `<text x="${charCenterX.toFixed(2)}" y="${barHeight + 14}" font-family="Cousine, Consolas, monospace" font-size="13" font-weight="bold" fill="#000000" text-anchor="middle">${escapeHtml(char)}</text>`;

        x += narrowWidth;
    }

    const totalWidth = +(x + quietZone - narrowWidth).toFixed(2);
    const totalHeight = barHeight + 18;

    const svg = `<svg class="zrx-barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${totalWidth} ${totalHeight}" width="${totalWidth}px" height="${totalHeight}px" shape-rendering="crispEdges">` +
        `<rect x="0" y="0" width="${totalWidth}" height="${totalHeight}" fill="#ffffff"/>` +
        rects +
        texts +
        `</svg>`;

    return `<div id="preview-barcode" class="zrx-barcode" data-code="${escapeHtml(clean)}">${svg}</div>`;
}

function toggleBarcode(patient) {
    const current = document.getElementById('preview-barcode');
    const value = cleanText(patient.regno || '');
    if ((previewOptions.display_barcode || 'yes') !== 'yes') {
        if (current) current.remove();
        return;
    }
    const html = barcodeHtml(value ? value.padStart(6, '0') : '0000000000');
    if (current) current.outerHTML = html;
    else document.querySelector('.zrx-left-content').insertAdjacentHTML('beforebegin', html);
}

function toggleVisitNo(patient) {
    const current = document.getElementById('preview-visit-no');
    const value = cleanText(patient.visit_no || '');
    if ((previewOptions.visit_number || 'yes') !== 'yes' || !value) {
        if (current) current.remove();
        return;
    }
    if (current) {
        const span = document.getElementById('preview-visit-no-val');
        if (span) span.textContent = value;
    } else {
        document.querySelector('.zrx-left-content').insertAdjacentHTML('beforebegin', `<div id="preview-visit-no" class="zrx-visit-no">Visit No: <span id="preview-visit-no-val">${escapeHtml(value)}</span></div>`);
    }
}

function applyPreviewData(data) {
    previewCurrentData = data && typeof data === 'object' ? data : previewDefaultData;
    const patient = previewCurrentData.patient || {};
    const clinical = previewCurrentData.clinical || {};

    setHtml('.zrx-patient-strip', renderPatientTable(patient));
    schedulePatientAddressFit();
    toggleRef(patient);
    toggleBarcode(patient);
    toggleVisitNo(patient);

    setHtml('#preview-left-sections', renderLeftSections(clinical));
    scheduleHistoryLabelGapFit();
    setHtml('#preview-drug-rows', renderDrugRows(clinical.drugs || []));

    const textPadWrap = document.getElementById('preview-text-pad-wrap');
    if (textPadWrap) {
        const text = String(clinical.text_pad || '').trim();
        textPadWrap.hidden = !text;
        textPadWrap.innerHTML = text ? escapeHtml(text).replace(/\n/g, '<br>') : '';
    }

    const adviceWrap = document.getElementById('preview-advice-wrap');
    const adviceHtml = renderAdvice(clinical.advice || []);
    if (adviceWrap) {
        adviceWrap.hidden = !adviceHtml;
        adviceWrap.innerHTML = adviceHtml;
    } else if (adviceHtml) {
        document.querySelector('.zrx-prescription-main').insertAdjacentHTML('beforeend', `<div id="preview-advice-wrap" class="zrx-advice-section">${adviceHtml}</div>`);
    }

    const revisitWrap = document.getElementById('preview-revisit-wrap');
    const revisitText = document.getElementById('preview-revisit-text');
    if (revisitWrap && revisitText) {
        const revisit = String(clinical.revisit || '').trim();
        revisitWrap.hidden = !revisit;
        revisitText.innerHTML = revisit ? escapeHtml(revisit).replace(/\n/g, '<br>') : '';
    }
}

const serverSnapshotEl = document.getElementById('previewServerSnapshotData');
const serverSnapshot = serverSnapshotEl ? JSON.parse(serverSnapshotEl.textContent || 'null') : null;
const snapshot = serverSnapshot || getStoredPreviewData();
if (snapshot) {
    applyPreviewData(snapshot);
}
if (window.parent && window.parent !== window) {
    window.parent.postMessage({ type: 'PREVIEW_DOM_READY' }, window.location.origin);
}

window.addEventListener('message', event => {
    if (!event.data) {
        return;
    }

    if (event.data.type === 'SYNC_PREVIEW_DRUG_FORMAT') {
        previewOptions = { ...previewOptions, ...(event.data.options || {}) };
        setHtml('#preview-drug-rows', renderDrugRows((previewCurrentData.clinical || {}).drugs || []));
        return;
    }

    if (event.data.type === 'SYNC_PREVIEW_PATIENT_LABELS') {
        previewOptions = { ...previewOptions, ...(event.data.options || {}) };
        setHtml('.zrx-patient-strip', renderPatientTable(previewCurrentData.patient || {}));
        schedulePatientAddressFit();
        setHtml('#preview-left-sections', renderLeftSections(previewCurrentData.clinical || {}));
        scheduleHistoryLabelGapFit();
        return;
    }

    if (event.data.type !== 'SYNC_PREVIEW_DATA') {
        return;
    }

    applyPreviewData(event.data.data || {});

    const footer = document.getElementById('preview-footer');
    if (footer && event.data.data.footer_html !== undefined) {
        const html = String(event.data.data.footer_html || '').trim();
        footer.innerHTML = html;
        footer.style.display = html && (previewOptions.display_footer || 'yes') !== 'no' ? 'block' : 'none';
    }

    const headerEl = document.getElementById('pageHeader');
    if (headerEl && event.data.data.bgcolor) {
        headerEl.style.background = '#' + event.data.data.bgcolor;
    }
});

(function waitForPrescriptionFonts() {
    const root = document.documentElement;
    const reveal = (className) => {
        root.classList.remove('zrx-fonts-loading');
        root.classList.add(className);
    };

    if (!document.fonts || !document.fonts.ready) {
        reveal('zrx-fonts-ready');
        return;
    }

    let done = false;
    const finish = (className) => {
        if (done) return;
        done = true;
        fitPatientAddresses();
        fitHistoryLabelGaps();
        reveal(className);
        schedulePatientAddressFit();
        scheduleHistoryLabelGapFit();
    };

    window.setTimeout(() => finish('zrx-fonts-timeout'), 900);
    document.fonts.ready.then(() => {
        window.requestAnimationFrame(() => finish('zrx-fonts-ready'));
    }, () => finish('zrx-fonts-timeout'));
})();

// Interactive stamp dragging, rotation, and resizing in preview
(function initStampDragging() {
    const stamp = document.getElementById('preview-stamp-layer');
    if (!stamp) return;

    // Disable ad-hoc actions and handles if inside layout setup iframe
    const isIframe = window.parent && window.parent !== window && window.parent.document.getElementById('zrx-gallery-overlay');
    if (isIframe) {
        return;
    }

    stamp.style.cursor = 'move';
    stamp.style.pointerEvents = 'auto';

    // Inject Action Buttons
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'zrx-stamp-action-btn';
    deleteBtn.innerHTML = '✕';
    deleteBtn.title = 'Remove Stamp from this print';
    stamp.appendChild(deleteBtn);

    const resizeHandle = document.createElement('div');
    resizeHandle.className = 'zrx-stamp-resize-handle';
    resizeHandle.title = 'Drag to Resize Stamp';
    stamp.appendChild(resizeHandle);

    const rotateHandle = document.createElement('div');
    rotateHandle.className = 'zrx-stamp-rotate-handle';
    rotateHandle.innerHTML = '↻';
    rotateHandle.title = 'Drag to Rotate Stamp';
    stamp.appendChild(rotateHandle);

    // Click to delete/hide (ad-hoc)
    deleteBtn.addEventListener('mousedown', (e) => e.stopPropagation());
    deleteBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        stamp.style.display = 'none';
    });

    // Proportional drag to resize
    resizeHandle.addEventListener('mousedown', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const startX = e.clientX;
        const startY = e.clientY;
        const rect = stamp.getBoundingClientRect();
        const startWidth = rect.width;

        const onMouseMove = (moveEvent) => {
            const dx = moveEvent.clientX - startX;
            const dy = moveEvent.clientY - startY;
            // Negate the sum since pulling top-left further up/left (negative directions) increases size
            const change = -(dx + dy) / 2;
            const newWidth = Math.max(30, Math.min(500, startWidth + change));

            stamp.style.width = newWidth + 'px';
            stamp.style.height = newWidth + 'px';
        };

        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // Drag to Rotate
    rotateHandle.addEventListener('mousedown', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const rect = stamp.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;

        const startX = e.clientX;
        const startY = e.clientY;
        const startAngleRad = Math.atan2(startY - centerY, startX - centerX);
        const startAngleDeg = startAngleRad * (180 / Math.PI);
        const baseAngle = parseFloat(previewOptions.stamp_angle || '0.0');

        const onMouseMove = (moveEvent) => {
            const currentAngleRad = Math.atan2(moveEvent.clientY - centerY, moveEvent.clientX - centerX);
            const currentAngleDeg = currentAngleRad * (180 / Math.PI);
            
            let deltaAngle = currentAngleDeg - startAngleDeg;
            let newAngle = Math.round(baseAngle + deltaAngle);
            
            while (newAngle > 180) newAngle -= 360;
            while (newAngle < -180) newAngle += 360;

            previewOptions.stamp_angle = newAngle;

            const opacity = parseFloat(previewOptions.stamp_opacity || '1.0');
            const scale = parseFloat(previewOptions.stamp_scale || '1.0');
            const clampedX = parseFloat(previewOptions.stamp_offset_x || '0.0');
            const clampedY = parseFloat(previewOptions.stamp_offset_y || '0.0');
            stamp.style.transform = `translate(${clampedX}px, ${clampedY}px) rotate(${newAngle}deg) scale(${scale})`;
        };

        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // Move / Drag stamp position
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let startOffsetX = 0;
    let startOffsetY = 0;

    stamp.addEventListener('mousedown', (e) => {
        e.preventDefault();
        stamp.classList.add('zrx-active');
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        startOffsetX = parseFloat(previewOptions.stamp_offset_x || '0.0');
        startOffsetY = parseFloat(previewOptions.stamp_offset_y || '0.0');

        const onMouseMove = (moveEvent) => {
            if (!isDragging) return;
            const dx = moveEvent.clientX - startX;
            const dy = moveEvent.clientY - startY;

            const newX = Math.round(startOffsetX + dx);
            const newY = Math.round(startOffsetY + dy);

            const clampedX = Math.max(-1200, Math.min(1200, newX));
            const clampedY = Math.max(-1200, Math.min(1200, newY));

            previewOptions.stamp_offset_x = clampedX;
            previewOptions.stamp_offset_y = clampedY;

            const opacity = parseFloat(previewOptions.stamp_opacity || '1.0');
            const scale = parseFloat(previewOptions.stamp_scale || '1.0');
            const angle = parseFloat(previewOptions.stamp_angle || '0.0');
            stamp.style.transform = `translate(${clampedX}px, ${clampedY}px) rotate(${angle}deg) scale(${scale})`;
        };

        const onMouseUp = () => {
            if (isDragging) {
                isDragging = false;
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            }
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // Toggle active state on click
    stamp.addEventListener('click', (e) => {
        e.stopPropagation();
        stamp.classList.add('zrx-active');
    });

    document.addEventListener('click', (e) => {
        if (!stamp.contains(e.target)) {
            stamp.classList.remove('zrx-active');
        }
    });
})();
