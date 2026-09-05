function collectRxPreviewDrugs() {
  const rows = document.querySelectorAll('#rx-tbody tr');
  return Array.from(rows).map((row) => {
    const getValue = (selector) => {
      const input = row.querySelector(selector);
      return input ? input.value.trim() : '';
    };

    let selectedDrug = {};
    if (row.dataset.selectedDrug) {
      try {
        selectedDrug = JSON.parse(row.dataset.selectedDrug) || {};
      } catch (err) {
        selectedDrug = {};
      }
    }

    return {
      brand: getValue('.rx-brand-input'),
      generic: getValue('.rx-generic-input'),
      dose: getValue('.rx-dose-input'),
      instruction: getValue('.rx-instruction-input'),
      duration: getValue('.rx-duration-input'),
      dose_bengali: row.querySelector('.rx-dose-input')?.dataset.printBengali || getValue('.rx-dose-input'),
      dose_english: row.querySelector('.rx-dose-input')?.dataset.printEnglish || getValue('.rx-dose-input'),
      instruction_bengali: row.querySelector('.rx-instruction-input')?.dataset.printBengali || getValue('.rx-instruction-input'),
      instruction_english: row.querySelector('.rx-instruction-input')?.dataset.printEnglish || getValue('.rx-instruction-input'),
      duration_bengali: row.querySelector('.rx-duration-input')?.dataset.printBengali || getValue('.rx-duration-input'),
      duration_english: row.querySelector('.rx-duration-input')?.dataset.printEnglish || getValue('.rx-duration-input'),
      brand_id: getValue('.brand_id'),
      system_brand_id: selectedDrug.system_brand_id || selectedDrug.brand_id || '',
      brand_name: selectedDrug.brand_name || '',
      pres_new_upper: selectedDrug.pres_new_upper || selectedDrug.prescribe_brand_short || '',
      full_form_brand_name: selectedDrug.full_form_brand_name || selectedDrug.prescribe_brand_full || '',
      generic_id: selectedDrug.generic_id || '',
      generic_name: selectedDrug.generic_name || selectedDrug.generic || '',
      strength: selectedDrug.strength || '',
      form: selectedDrug.form_new || selectedDrug.form || '',
      labelled_generic_short: selectedDrug.labelled_generic_short || '',
      labelled_generic_full: selectedDrug.labelled_generic_full || '',
      prescribe_generic_short: selectedDrug.prescribe_generic_short || '',
      prescribe_generic_full: selectedDrug.prescribe_generic_full || ''
    };
  }).filter((drug) => {
    return drug.brand || drug.generic || drug.dose || drug.instruction || drug.duration;
  });
}

function readFieldValue(selector) {
  const field = document.querySelector(selector);
  return field ? field.value.trim() : '';
}

function firstNonEmptyValue(selectors) {
  for (const selector of selectors) {
    const value = readFieldValue(selector);
    if (value) {
      return value;
    }
  }
  return '';
}

function patientReferralCategoryKey(value = readFieldValue('#patient-ref-type')) {
  const typeField = document.getElementById('patient-ref-type');
  if (arguments.length === 0 && typeField?.selectedOptions?.[0]?.dataset?.referralCategory) {
    return typeField.selectedOptions[0].dataset.referralCategory;
  }

  const key = String(value || '').trim().toLowerCase().replace(/[-\s]+/g, '_');
  if (key === 'doctor') return 'doctor';
  if (key === 'other_patient') return 'other_patient';
  if (key === 'others') return 'others';
  return 'self';
}

function cleanPatientReferralName(category = patientReferralCategoryKey(), value = readFieldValue('#patient-ref-by')) {
  let name = String(value || '').replace(/\s+/g, ' ').trim();
  if (category !== 'doctor' && category !== 'others') {
    return '';
  }
  if (category === 'doctor') {
    if (!name || /^dr\.?$/i.test(name)) {
      return '';
    }
    if (!/^dr\.\s*/i.test(name)) {
      name = `Dr. ${name}`;
    }
  }
  return name;
}

function getPatientReferralPayload() {
  const category = patientReferralCategoryKey();
  return {
    category,
    name: cleanPatientReferralName(category)
  };
}

let patientReferralLookupTimer = null;
let previousPatientReferralTimer = null;

function loadPatientReferralSuggestions() {
  const typeField = document.getElementById('patient-ref-type');
  const textField = document.getElementById('patient-ref-by');
  const list = document.getElementById('patient-referral-list');
  if (!typeField || !textField || !list) return;

  const category = patientReferralCategoryKey();
  if (category !== 'doctor' && category !== 'others') {
    list.innerHTML = '';
    return;
  }

  window.clearTimeout(patientReferralLookupTimer);
  patientReferralLookupTimer = window.setTimeout(async () => {
    try {
      const params = new URLSearchParams({
        action: 'suggestions',
        category,
        q: textField.value.trim()
      });
      const response = await fetch(`api/patient_referrals.php?${params.toString()}`);
      const data = await response.json();
      const suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
      list.innerHTML = suggestions
        .map((name) => {
          const safeName = String(name)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
          return `<option value="${safeName}"></option>`;
        })
        .join('');
    } catch (err) {
      list.innerHTML = '';
    }
  }, 160);
}

function referralOptionLabel(referral) {
  const name = String(referral.name || '').trim();
  return name;
}

function removePreviousPatientReferralOptions(select) {
  select.querySelectorAll('option.patient-ref-saved-option').forEach((option) => option.remove());
}

function addPreviousPatientReferralOptions(referrals) {
  const select = document.getElementById('patient-ref-type');
  if (!select) return;

  removePreviousPatientReferralOptions(select);
  referrals.forEach((referral, index) => {
    const category = patientReferralCategoryKey(referral.category);
    if (!['doctor', 'others'].includes(category)) return;
    const name = String(referral.name || '').trim();
    if (!name) return;

    const option = document.createElement('option');
    option.className = 'patient-ref-saved-option';
    option.value = `saved:${category}:${index}`;
    option.textContent = referralOptionLabel({ category, name });
    option.dataset.referralCategory = category;
    option.dataset.referralName = name;
    select.appendChild(option);
  });
}

function loadPreviousPatientReferralOptions() {
  const regNo = readFieldValue('#patient-reg-no');
  const select = document.getElementById('patient-ref-type');
  if (!select) return;

  window.clearTimeout(previousPatientReferralTimer);
  previousPatientReferralTimer = window.setTimeout(async () => {
    if (!regNo) {
      removePreviousPatientReferralOptions(select);
      return;
    }

    try {
      const params = new URLSearchParams({ action: 'recent', reg_no: regNo });
      const response = await fetch(`api/patient_referrals.php?${params.toString()}`);
      const data = await response.json();
      addPreviousPatientReferralOptions(Array.isArray(data.referrals) ? data.referrals : []);
    } catch (err) {
      removePreviousPatientReferralOptions(select);
    }
  }, 180);
}

function focusPatientReferralInput(textField) {
  window.requestAnimationFrame(() => {
    textField.focus();
    const position = textField.value.length;
    if (typeof textField.setSelectionRange === 'function') {
      textField.setSelectionRange(position, position);
    }
  });
}

function syncPatientReferredByControl(options = {}) {
  const typeField = document.getElementById('patient-ref-type');
  const textField = document.getElementById('patient-ref-by');
  const wrapper = document.getElementById('patient-referral-control');
  if (!typeField || !textField || !wrapper) return;

  const savedName = typeField.selectedOptions?.[0]?.dataset?.referralName || '';
  const category = patientReferralCategoryKey();
  const needsText = category === 'doctor' || category === 'others';
  wrapper.classList.toggle('has-free-text', needsText);
  textField.hidden = !needsText;
  textField.placeholder = category === 'doctor' ? 'Doctor name' : 'Referral details';
  if (!needsText) {
    textField.value = '';
  } else if (savedName) {
    textField.value = savedName;
  } else if (category === 'doctor') {
    const current = textField.value.trim();
    if (!current) {
      textField.value = 'Dr. ';
    } else if (!/^dr\.\s*/i.test(current)) {
      textField.value = `Dr. ${current}`;
    }
  } else if (/^dr\.?$/i.test(textField.value.trim())) {
    textField.value = '';
  }
  if (needsText && options.focusInput) {
    focusPatientReferralInput(textField);
  }
  loadPatientReferralSuggestions();
}

function initPatientReferredByControl() {
  const typeField = document.getElementById('patient-ref-type');
  const textField = document.getElementById('patient-ref-by');
  const regNoField = document.getElementById('patient-reg-no');
  if (!typeField || !textField) return;

  typeField.addEventListener('change', () => syncPatientReferredByControl({ focusInput: true }));
  textField.addEventListener('focus', loadPatientReferralSuggestions);
  textField.addEventListener('input', loadPatientReferralSuggestions);
  regNoField?.addEventListener('change', loadPreviousPatientReferralOptions);
  regNoField?.addEventListener('blur', loadPreviousPatientReferralOptions);
  regNoField?.addEventListener('input', loadPreviousPatientReferralOptions);
  loadPreviousPatientReferralOptions();
  syncPatientReferredByControl();
}

function getPrintablePatientRefBy() {
  const category = patientReferralCategoryKey();
  if (category !== 'doctor') {
    return '';
  }
  return cleanPatientReferralName(category);
}

function getModuleCardByTitle(title) {
  const slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  const card = document.querySelector(`.module-card-${slug}`);
  if (card) {
    return card;
  }

  const cards = document.querySelectorAll('.module-card');
  return Array.from(cards).find((c) => {
    const header = c.querySelector('.module-header span');
    if (!header) return false;
    const text = header.textContent.trim().toLowerCase();
    const tLower = title.toLowerCase();
    return text === tLower || text.startsWith(tLower + ' ') || text.startsWith(tLower + '(') || text.includes('(' + tLower + ')');
  }) || null;
}

function getHistoryModuleCard() {
  const historyRoot = document.querySelector('[data-history-module]');
  return historyRoot?.closest('.module-card') || getModuleCardByTitle('History');
}

function getModuleExtractionRoot(title) {
  const historySubmodule = (title === 'History') ? 'medical' : (title === 'D/H' ? 'drug-history' : '');
  if (historySubmodule) {
    const historyCard = getHistoryModuleCard();
    const submodule = historyCard?.querySelector(`[data-history-submodule="${historySubmodule}"]`);
    if (submodule) {
      return submodule;
    }
  }

  const card = getModuleCardByTitle(title);
  if (!card) {
    return null;
  }

  return card.querySelector('.module-body') || card;
}

function extractListModuleLines(title) {
  const root = getModuleExtractionRoot(title);
  if (!root) {
    return [];
  }

  const lines = [];
  root.querySelectorAll('.mock-list li').forEach((item) => {
    const text = item.textContent.trim();
    if (text) {
      lines.push(text);
    }
  });

  root.querySelectorAll('input[type="checkbox"]:checked').forEach((field) => {
    const label = (field.dataset.lineLabel || '').trim();
    if (label) {
      lines.push(label);
    }
  });

  root.querySelectorAll('input[type="text"], textarea, select').forEach((field) => {
    if (field.offsetParent === null && field.type !== 'hidden') {
      return;
    }
    if (field.classList.contains('selectTxt') || field.closest('.nicEdit-panel') || field.closest('.nicEdit-panelContain')) {
      return;
    }
    const value = field.value.trim();
    if (value) {
      const prefix = (field.dataset.linePrefix || '').trim();
      lines.push(prefix ? `${prefix}: ${value}` : value);
    }
  });

  return lines;
}

function splitHistoryTextList(value) {
  return String(value || '')
    .split(',')
    .map((item) => item.replace(/\s+/g, ' ').trim())
    .filter(Boolean);
}

function collectCheckedHistoryLabels(root, fieldName) {
  if (!root) {
    return [];
  }
  return Array.from(root.querySelectorAll(`input[type="checkbox"][data-history-field="${fieldName}"]:checked`))
    .map((field) => {
      const label = (field.dataset.historyLabel || '').trim();
      const itemWrap = field.closest('.history-med-item') || field.closest('.history-check-item');
      const valInput = itemWrap?.querySelector('.history-med-value-input');
      const val = (valInput?.value || '').trim();
      if (val) {
        return `${label} (${val})`;
      }
      return label;
    })
    .filter(Boolean);
}

function formatHabitUnit(qty, unit) {
  const num = parseFloat(qty);
  if (num === 1) {
    if (unit === 'pack-years') return 'pack-year';
    if (unit === 'units/week') return 'unit/week';
    if (unit === 'times/day') return 'time/day';
  }
  return unit;
}

function collectCheckedHabitLabels(root) {
  if (!root) {
    return [];
  }
  return Array.from(root.querySelectorAll('input[type="checkbox"][data-history-field="habit"]:checked'))
    .map((cb) => {
      const label = (cb.dataset.historyLabel || '').trim();
      const wrapper = cb.closest('.history-habit-item');
      const qtyInput = wrapper?.querySelector('.history-habit-qty-input');
      const qty = (qtyInput?.value || '').trim();
      const unit = (qtyInput?.dataset.unit || wrapper?.querySelector('.history-habit-qty-unit')?.textContent || '').trim();
      if (qty) {
        if (unit) {
          const properUnit = formatHabitUnit(qty, unit);
          return `${label} (${qty} ${properUnit})`.trim();
        }
        return `${label} (${qty})`.trim();
      }
      return label;
    })
    .filter(Boolean);
}

function collectHistoryPreviewSection() {
  const card = getHistoryModuleCard();
  const root = card?.querySelector('[data-history-module]');
  if (!root) {
    return {
      medical: extractListModuleLines('History'),
      treatments: [],
      habits: [],
      diet: '',
      hypersensitivity: [],
      drug_history: extractListModuleLines('D/H')
    };
  }

  const medical = [
    ...collectCheckedHistoryLabels(root, 'medical'),
    ...splitHistoryTextList(root.querySelector('[data-history-field="medical-custom"]')?.value || '')
  ];

  const treatments = Array.from(root.querySelectorAll('#history-treatment-tbody tr')).map((row) => {
    const procedure = (row.querySelector('.history-treatment-procedure')?.value || '').replace(/\s+/g, ' ').trim();
    const year = (row.querySelector('.history-treatment-year')?.value || '').replace(/\s+/g, ' ').trim();
    if (!procedure && !year) {
      return null;
    }
    return { procedure, year };
  }).filter(Boolean);

  const habits = [
    ...collectCheckedHabitLabels(root),
    ...splitHistoryTextList(root.querySelector('[data-history-field="habit-notes"]')?.value || '')
  ];

  const dietRaw = (root.querySelector('[data-history-field="diet-type"]')?.value || '').replace(/\s+/g, ' ').trim();
  const diet = dietRaw === 'Standard / Normal' ? 'Standard' : dietRaw;

  const chips = Array.from(root.querySelectorAll('[data-history-chip]'))
    .map((chip) => (chip.dataset.value || '').replace(/\s+/g, ' ').trim())
    .filter(Boolean);
  const chipInput = splitHistoryTextList(root.querySelector('[data-history-chip-text]')?.value || '');

  const drugHistory = Array.from(root.querySelectorAll('#dh-tbody .dh-input'))
    .map((field) => field.value.replace(/\s+/g, ' ').trim())
    .filter(Boolean);

  return {
    medical,
    treatments,
    habits,
    diet,
    hypersensitivity: [...chips, ...chipInput],
    drug_history: drugHistory
  };
}

function collectPcEntries() {
  const rows = document.querySelectorAll('.pc-table tbody tr');
  return Array.from(rows).map((row) => {
    const complaint = row.querySelector('.pc-complaint-input')?.value.trim() || '';
    const duration = row.querySelector('.pc-duration-input')?.value.trim() || '';
    const unit = row.querySelector('.pc-unit-input')?.value.trim() || '';
    if (!complaint && !duration && !unit) {
      return null;
    }

    return { complaint, duration, unit };
  }).filter(Boolean);
}

function collectPcSection() {
  return collectPcEntries().map(({ complaint, duration, unit }) => {
    const suffix = [duration, unit].filter(Boolean).join(' ');
    return [complaint, suffix ? `(${suffix})` : ''].filter(Boolean).join(' ');
  }).filter(Boolean);
}

function collectPeSection() {
  const tbody = document.querySelector('#pe-tbody');
  if (!tbody) {
    return [];
  }

  return Array.from(tbody.querySelectorAll('tr')).map((row) => {
    const cells = row.querySelectorAll('td');
    const name = (cells[1]?.querySelector('textarea, input')?.value || '').replace(/\s+/g, ' ').trim();
    const valueParts = Array.from(cells[2]?.querySelectorAll('input, textarea') || [])
      .map((field) => field.value.replace(/\s+/g, ' ').trim())
      .filter(Boolean);
    const unit = (cells[3]?.querySelector('input, textarea, select')?.value || '').replace(/\s+/g, ' ').trim();

    let value = valueParts.join(valueParts.length === 2 ? '/' : ' ');
    if (value && unit) {
      value = `${value} ${unit}`;
    }

    if (!name || !value) {
      return null;
    }

    return { name, value };
  }).filter(Boolean);
}

function collectReportsSection() {
  const rows = document.querySelectorAll('.report-table tbody tr');
  return Array.from(rows).map((row) => {
    const inputs = row.querySelectorAll('input');
    const name = inputs[0]?.value.trim() || '';
    const date = inputs[1]?.value.trim() || '';
    const value = inputs[2]?.value.trim() || '';
    const unit = inputs[3]?.value.trim() || '';
    const displayValue = [value, unit].filter(Boolean).join(' ');

    if (!name && !date && !displayValue) {
      return null;
    }

    return {
      name,
      date,
      value: displayValue
    };
  }).filter(Boolean);
}

function collectAdviceSection() {
  return Array.from(document.querySelectorAll('.advice-list .adv-input'))
    .map((input) => {
      const value = input.value.trim();
      if (!value) return null;
      return {
        value,
        bengali: input.dataset.printBengali || value,
        english: input.dataset.printEnglish || value
      };
    })
    .filter(Boolean);
}

function collectOhSection() {
  const rows = document.querySelectorAll('.js-oh-module .oh-table tbody tr');
  return Array.from(rows).map((row) => {
    const label = row.querySelector('.oh-name-input')?.value.trim() || '';
    const plainValue = row.querySelector('.oh-value-input')?.value.trim() || '';
    const durationValue = row.querySelector('.oh-duration-input')?.value.trim() || '';
    const unitValue = row.querySelector('.oh-unit-select')?.value.trim() || '';
    const value = plainValue || (durationValue ? [durationValue, unitValue].filter(Boolean).join(' ') : '');

    if (!label || !value) {
      return null;
    }

    return `${label}: ${value}`;
  }).filter(Boolean);
}

function collectMhSection() {
  const rows = document.querySelectorAll('.js-mh-module .oh-table tbody tr');
  return Array.from(rows).map((row) => {
    const label = row.querySelector('.oh-name-input')?.value.trim() || '';
    const value = row.querySelector('.oh-value-input')?.value.trim() || '';

    if (!label || !value) {
      return null;
    }

    return `${label}: ${value}`;
  }).filter(Boolean);
}

function collectPaediatricSection() {
  const rows = document.querySelectorAll('.js-ph-module .oh-table tbody tr');
  return Array.from(rows).map((row) => {
    const label = row.querySelector('.oh-name-input')?.value.trim() || '';
    const select = row.querySelector('.ph-quick-select');
    const custom = row.querySelector('.ph-custom-input');
    let value = '';
    if (custom && !custom.hidden && custom.value.trim()) {
      value = custom.value.trim();
    } else if (select && select.value && select.value !== 'custom') {
      value = select.value.trim();
    } else {
      const plainVal = row.querySelector('.oh-value-input')?.value.trim() || '';
      const durVal = row.querySelector('.oh-duration-input')?.value.trim() || '';
      const unitVal = row.querySelector('.oh-unit-select')?.value.trim() || '';
      value = plainVal || (durVal ? [durVal, unitVal].filter(Boolean).join(' ') : '');
    }

    if (!label || !value) {
      return null;
    }

    return `${label}: ${value}`;
  }).filter(Boolean);
}

function collectOtnoteSection() {
  const root = getModuleExtractionRoot('OT Note');
  if (!root) {
    return [];
  }

  const lines = [];
  root.querySelectorAll('.ot-table tbody tr').forEach((row) => {
    const labelField = row.querySelector('input.ot-input');
    const valueField = row.querySelector('textarea.ot-value-input');
    if (!labelField || !valueField) return;

    const label = labelField.value.trim();
    const value = valueField.value.trim();

    if (label && value) {
      lines.push(`${label}: ${value}`);
    }
  });

  const editors = [
    { id: 'ot-salient-editor', label: 'Salient Feature' },
    { id: 'ot-history-editor', label: 'OT History' },
    { id: 'ot-others-editor', label: 'OT Others' }
  ];

  editors.forEach(ed => {
    const field = document.getElementById(ed.id);
    if (!field) return;

    let value = '';
    if (window.nicEditors) {
      const inst = window.nicEditors.findEditor(ed.id);
      if (inst) {
        const content = inst.getContent().trim();
        const stripped = content.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, '').trim();
        if (stripped) {
          value = content;
        }
      }
    }
    if (!value) {
      value = field.value.trim();
    }

    if (value) {
      const clean = value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
      if (clean) {
        lines.push(`${ed.label}: ${clean}`);
      }
    }
  });

  return lines;
}

function collectTextPadSection() {
  let value = '';
  if (window.nicEditors) {
    const inst = window.nicEditors.findEditor('textpad-editor');
    if (inst) {
      const content = inst.getContent().trim();
      const stripped = content.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, '').trim();
      if (stripped) {
        value = content;
      }
    }
  }
  if (!value) {
    const field = document.getElementById('textpad-editor');
    if (field) {
      value = field.value.trim();
    }
  }
  if (value) {
    let plain = value
      .replace(/<div[^>]*>/gi, '')
      .replace(/<\/div>/gi, '\n')
      .replace(/<p[^>]*>/gi, '')
      .replace(/<\/p>/gi, '\n')
      .replace(/<br\s*\/?>/gi, '\n')
      .replace(/<[^>]*>/g, '')
      .replace(/&nbsp;/g, ' ')
      .trim();
    return plain;
  }
  return '';
}

function formatPatientAge() {
  const age = readFieldValue('#patient-age');
  const unit = readFieldValue('#patient-age-unit');
  if (!age) {
    return '';
  }

  const unitMap = {
    Years: 'Y',
    Months: 'M',
    Weeks: 'W',
    Days: 'D'
  };

  return `${age}${unitMap[unit] || ''}`;
}

function formatPatientWeight() {
  const weight = readFieldValue('#patient-weight');
  if (!weight) {
    return '';
  }
  const unit = readFieldValue('#patient-weight-unit');
  return [weight, unit].filter(Boolean).join(' ');
}

function formatRevisitText() {
  const selected = readFieldValue('#advice-next-visit-select');
  const nextVisitDate = readFieldValue('#advice-next-visit-date');
  const baseDateRaw = readFieldValue('#patient-date');

  if (!selected && !nextVisitDate) {
    return '';
  }

  if (nextVisitDate) {
    const baseDate = baseDateRaw ? new Date(baseDateRaw) : new Date();
    const revisitDate = new Date(nextVisitDate);
    if (!Number.isNaN(baseDate.getTime()) && !Number.isNaN(revisitDate.getTime())) {
      const millis = revisitDate.setHours(0, 0, 0, 0) - baseDate.setHours(0, 0, 0, 0);
      const days = Math.max(0, Math.round(millis / 86400000));
      return `${days} day${days === 1 ? '' : 's'} later (${nextVisitDate})`;
    }
    return nextVisitDate;
  }

  return selected === 'প্রয়োজন নেই' ? '' : selected;
}

function collectPrescriptionPreviewSnapshot() {
  const drugs = collectRxPreviewDrugs();
  const history = collectHistoryPreviewSection();

  return {
    patient: {
      name: readFieldValue('#patient-name'),
      age: formatPatientAge(),
      sex: firstNonEmptyValue(['#patient-sex', '#patient-gender']),
      date: readFieldValue('#patient-date'),
      address: firstNonEmptyValue(['#address-input', '#patient-address']),
      regno: readFieldValue('#patient-reg-no'),
      weight: formatPatientWeight(),
      mobile: readFieldValue('#patient-mobile'),
      ref_by: getPrintablePatientRefBy(),
      referral: getPatientReferralPayload(),
      visit_no: firstNonEmptyValue(['#patient-visit-no', '#visit-no'])
    },
    clinical: {
      pc: collectPcSection(),
      history,
      ho: history.medical || extractListModuleLines('History'),
      pe: collectPeSection(),
      oe: collectPeSection(),
      reports: collectReportsSection(),
      dh: history.drug_history || extractListModuleLines('D/H'),
      plan: extractListModuleLines('Plan'),
      ix: extractListModuleLines('Ix'),
      dx: extractListModuleLines('Dx'),
      note: extractListModuleLines('Note'),
      oh: collectOhSection(),
      mh: collectMhSection(),
      paediatric: collectPaediatricSection(),
      ph: collectPaediatricSection(),
      otnote: collectOtnoteSection(),
      text_pad: collectTextPadSection(),
      drugs: drugs.map((drug) => ({
        brand: drug.brand,
        brand_name: drug.brand_name,
        pres_new_upper: drug.pres_new_upper,
        full_form_brand_name: drug.full_form_brand_name,
        generic: drug.generic,
        generic_name: drug.generic_name,
        labelled_generic_short: drug.labelled_generic_short,
        labelled_generic_full: drug.labelled_generic_full,
        prescribe_generic_short: drug.prescribe_generic_short,
        prescribe_generic_full: drug.prescribe_generic_full,
        dose: drug.dose,
        dose_bengali: drug.dose_bengali,
        dose_english: drug.dose_english,
        food: drug.instruction,
        instruction_bengali: drug.instruction_bengali,
        instruction_english: drug.instruction_english,
        duration: drug.duration,
        duration_bengali: drug.duration_bengali,
        duration_english: drug.duration_english
      })),
      advice: collectAdviceSection(),
      revisit: formatRevisitText()
    }
  };
}

function storePrescriptionPreviewSnapshot() {
  const drugs = JSON.stringify(collectRxPreviewDrugs());
  const snapshot = JSON.stringify(collectPrescriptionPreviewSnapshot());

  sessionStorage.setItem(storageKeys.previewDrugs, drugs);
  localStorage.setItem(storageKeys.previewDrugs, drugs);
  sessionStorage.setItem(storageKeys.previewSnapshot, snapshot);
  localStorage.setItem(storageKeys.previewSnapshot, snapshot);
}

function learnCurrentRxRegimens() {
  const drugs = collectRxPreviewDrugs();
  if (!drugs.length) return;

  fetch('api/rx_learn.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ drugs }),
    keepalive: true
  }).catch(() => {});
}

function learnCurrentAdviceAutocompletes() {
  const advice = collectAdviceSection().map((item) => typeof item === 'object' ? item.value : item);
  if (!advice.length) return;

  fetch('api/advice_learn.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ advice }),
    keepalive: true
  }).catch(() => {});
}

function learnCurrentPcAutocompletes() {
  const complaints = collectPcEntries();
  if (!complaints.length) return;

  fetch('api/pc_learn.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ complaints }),
    keepalive: true
  }).then(() => {
    if (typeof window.clearPcSuggestCache === 'function') {
      window.clearPcSuggestCache();
    }
  }).catch(() => {});
}

/**
 * Fetches pre-defined advice templates from the static database.
 */
async function fetchStaticAdviceTemplates() {
  try {
    const response = await fetch('api/get_static_advice.php');
    const data = await response.json();
    if (data && !data.error) {
      localStorage.setItem(storageKeys.adviceTemplates, JSON.stringify(data));
      return data;
    }
  } catch (err) {
    console.error('Failed to fetch advice templates', err);
  }
  return [];
}

function saveCurrentPrescriptionVisit() {
  const params = new URLSearchParams(window.location.search || '');
  const appointmentId = params.get('appointment_id') || '';
  const patientId = params.get('patient_id') || '';
  const regNo = params.get('reg_no') || '';

  if (!appointmentId && !patientId && !regNo) return;

  let referral = getPatientReferralPayload();
  if (referral.category === 'self' && params.get('referral_category')) {
    referral = {
      category: params.get('referral_category') || 'self',
      name: params.get('referral_name') || ''
    };
  }

  const snapshot = typeof collectPrescriptionPreviewSnapshot === 'function' ? collectPrescriptionPreviewSnapshot() : null;
  const previewPaper = document.getElementById('prescription-preview-content') || document.querySelector('.zrx-preview-paper');
  const previewHtml = previewPaper ? (previewPaper.outerHTML || previewPaper.innerHTML || '') : '';

  fetch('api/save_prescription_visit.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      appointment_id: appointmentId,
      patient_id: patientId,
      reg_no: regNo,
      visit_no: params.get('visit_no') || '',
      visit_code: params.get('visit_code') || '',
      referral,
      drugs: collectRxPreviewDrugs(),
      clinical_snapshot: snapshot,
      prescription_html: previewHtml
    })
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.error) {
        if (typeof showZrxAlert === 'function') {
          showZrxAlert(data.error, { type: 'error', title: 'Save Error' });
        } else {
          alert(data.error);
        }
      }
    })
    .catch(() => {
      if (typeof showZrxAlert === 'function') {
        showZrxAlert('Could not save visit. Please try again.', { type: 'error', title: 'Network Error' });
      } else {
        alert('Could not save visit. Please try again.');
      }
    });
}

function learnRxRegimensOnSave(event) {
  if (!event.target.closest('#btn-save-print, #btn-save-only')) return;
  storePrescriptionPreviewSnapshot();
  learnCurrentRxRegimens();
  learnCurrentAdviceAutocompletes();
  learnCurrentPcAutocompletes();
  saveCurrentPrescriptionVisit();
}

function openPrescriptionPreview(event) {
  const previewLink = event.target.closest('#btn-preview-prescription');
  if (!previewLink) return;

  event.preventDefault();
  storePrescriptionPreviewSnapshot();
  learnCurrentRxRegimens();
  window.open(previewLink.href, '_blank');
}
