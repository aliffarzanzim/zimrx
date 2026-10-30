(function () {
  const typeSelect = document.getElementById('dropdown-preview-type');
  const globalArea = document.getElementById('dropdown-preview-global-area');
  const globalSelect = document.getElementById('dropdown-preview-global-select');
  const searchArea = document.getElementById('dropdown-preview-search-area');
  const searchLabel = document.getElementById('dropdown-preview-search-label');
  const searchInput = document.getElementById('dropdown-preview-search');
  const searchWrapper = document.getElementById('dropdown-preview-search-wrapper');
  const resultsList = document.getElementById('dropdown-preview-results');
  const preview = document.getElementById('dropdown-preview-content');
  const customBg = document.getElementById('dd-custom-bg-hex');
  const customText = document.getElementById('dd-custom-text-hex');
  const customBgPicker = document.getElementById('dd-custom-bg-picker');
  const customTextPicker = document.getElementById('dd-custom-text-picker');
  const customOriginal = document.getElementById('dd-custom-original-text-cb');

  if (!typeSelect || !globalArea || !globalSelect || !searchArea || !searchInput || !resultsList || !preview) return;

  let activeType = 'global';
  let dropdownInstance = null;

  const typeLabels = {
    registration: { label: 'Registration number', placeholder: 'Type a registration number' },
    phone: { label: 'Phone number', placeholder: 'Type a phone number' },
    rx: { label: 'Medication', placeholder: 'Type a medicine or generic name' }
  };

  function escapeText(value, fallback = '') {
    const text = String(value ?? '').trim();
    return text || fallback;
  }

  function renderPatientResult(patient, type) {
    const item = document.createElement('li');
    item.className = 'patient-lookup-option';

    const code = document.createElement('span');
    code.className = 'patient-lookup-code';
    code.textContent = type === 'phone'
      ? escapeText(patient.mobile)
      : escapeText(patient.reg_no);

    const name = document.createElement('strong');
    name.className = 'patient-lookup-name';
    name.textContent = escapeText(patient.full_name || patient.patient_name, 'No Name');

    const meta = document.createElement('span');
    meta.className = 'patient-lookup-meta';
    const secondary = type === 'phone'
      ? escapeText(patient.reg_no)
      : escapeText(patient.mobile, 'No Phone');
    const address = escapeText(patient.address, 'No Address');
    meta.textContent = secondary ? `${secondary} | ${address}` : address;

    item.append(code, name, meta);
    return item;
  }

  function renderDrugResult(drug) {
    const item = document.createElement('li');
    item.className = 'rx-dropdown-item rx-drug-item';

    const iconPath = typeof window.getDosageFormIcon === 'function'
      ? window.getDosageFormIcon(drug.form, drug.pres_new_upper)
      : '';
    if (iconPath) {
      const icon = document.createElement('img');
      icon.className = 'rx-dd-icon';
      icon.src = iconPath;
      icon.alt = '';
      item.appendChild(icon);
    }

    const brand = document.createElement('strong');
    brand.className = 'rx-dd-brand';
    brand.textContent = escapeText(drug.pres_new_upper);
    item.appendChild(brand);

    const generic = document.createElement('span');
    generic.className = 'rx-dd-generic';
    generic.textContent = escapeText(drug.generic);
    item.appendChild(generic);

    const manufacturer = document.createElement('span');
    manufacturer.className = 'rx-dd-man';
    const manufacturerName = escapeText(drug.man_short);
    manufacturer.textContent = manufacturerName ? `-${manufacturerName}` : '';
    item.appendChild(manufacturer);

    const price = Number.parseFloat(drug.price);
    const priceLabel = document.createElement('span');
    priceLabel.className = 'rx-dd-price';
    priceLabel.textContent = Number.isFinite(price) ? `৳${price.toFixed(2)}` : escapeText(drug.price);
    item.appendChild(priceLabel);

    return item;
  }

  async function fetchPreviewResults(query) {
    if (activeType === 'registration' || activeType === 'phone') {
      const parameter = activeType === 'phone' ? 'mobile' : 'reg_no';
      const url = `api/appointments.php?action=patient_lookup&${parameter}=${encodeURIComponent(query)}`;
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!response.ok) return [];
      const data = await response.json();
      return Array.isArray(data.patients)
        ? data.patients.filter((patient) => patient && typeof patient === 'object' && !Array.isArray(patient))
        : [];
    }

    if (activeType === 'rx') {
      const response = await fetch(`api/search_drug.php?q=${encodeURIComponent(query)}`, {
        headers: { Accept: 'application/json' }
      });
      if (!response.ok) return [];
      const data = await response.json();
      return Array.isArray(data)
        ? data.filter((drug) => drug && typeof drug === 'object' && !Array.isArray(drug))
        : [];
    }

    return [];
  }

  function renderResult(item) {
    return activeType === 'rx'
      ? renderDrugResult(item)
      : renderPatientResult(item, activeType);
  }

  function syncHighlightColors() {
    const selected = document.querySelector('input[name="dropdown_theme"]:checked');
    const theme = selected?.value || 'slate-gray';
    const presets = {
      'slate-gray': ['#868e96', '#ffffff'],
      'subtle-tint': ['#ebedf0', '#0f172a'],
      charcoal: ['#475569', '#ffffff'],
      'theme-blue': ['#2563eb', '#ffffff'],
      'soft-blue': ['#eff6ff', '#1d4ed8'],
      emerald: ['#16a34a', '#ffffff']
    };

    let [background, text] = presets[theme] || presets['slate-gray'];
    if (theme === 'custom') {
      background = /^#[0-9a-f]{6}$/i.test(customBg?.value || '') ? customBg.value : '#ebedf0';
      text = customOriginal?.checked
        ? '#0f172a'
        : (/^#[0-9a-f]{6}$/i.test(customText?.value || '') ? customText.value : '#ffffff');
    }

    preview.style.setProperty('--zrx-dropdown-hover-bg', background);
    preview.style.setProperty('--zrx-dropdown-hover-text', text);
    preview.style.setProperty('--zrx-interaction-option-bg', background);
    preview.style.setProperty('--zrx-interaction-option-text', text);
  }

  function populateGlobalSelect() {
    const source = document.querySelector('#left-side-setup select[data-side="left"]');
    globalSelect.replaceChildren();
    if (!source) return;

    Array.from(source.options).forEach((sourceOption) => {
      const option = document.createElement('option');
      option.value = sourceOption.value;
      option.textContent = sourceOption.textContent;
      globalSelect.appendChild(option);
    });
    globalSelect.selectedIndex = source.selectedIndex;
    window.ZimRxDropdown?.enhanceSelect(globalSelect);
  }

  function setPreviewType(type) {
    activeType = typeLabels[type] ? type : 'global';
    const isGlobal = activeType === 'global';
    globalArea.hidden = !isGlobal;
    searchArea.hidden = isGlobal;
    searchInput.value = '';
    resultsList.replaceChildren();
    dropdownInstance?.close();

    if (isGlobal) {
      populateGlobalSelect();
      return;
    }

    searchLabel.textContent = typeLabels[activeType].label;
    searchInput.placeholder = typeLabels[activeType].placeholder;
  }

  populateGlobalSelect();
  if (window.ZimRxDropdown) {
    dropdownInstance = window.ZimRxDropdown.attach({
      input: searchInput,
      list: resultsList,
      wrapper: searchWrapper,
      fetcher: (query) => fetchPreviewResults(query).catch(() => []),
      renderItem: renderResult,
      minLength: 1,
      debounceMs: 180,
      onSelect: (item) => {
        if (activeType === 'rx') {
          searchInput.value = escapeText(item.pres_new_upper || item.generic);
        } else {
          searchInput.value = activeType === 'phone'
            ? escapeText(item.mobile)
            : escapeText(item.reg_no);
        }
      }
    });
  }

  typeSelect.addEventListener('change', () => setPreviewType(typeSelect.value));
  document.addEventListener('zrx-dropdown-preview-theme-change', syncHighlightColors);
  [customBg, customText, customBgPicker, customTextPicker, customOriginal].forEach((control) => {
    control?.addEventListener('input', syncHighlightColors);
    control?.addEventListener('change', syncHighlightColors);
  });

  syncHighlightColors();
  setPreviewType(typeSelect.value);
})();
