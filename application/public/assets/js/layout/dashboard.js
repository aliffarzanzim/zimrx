// Layout persistence, module grid arrangement, dropdown theme customization, and cookie sync.
function getLayoutConfig() {
  const savedLeft = localStorage.getItem(storageKeys.leftLayout);
  const savedRight = localStorage.getItem(storageKeys.rightLayout);
  const normalizeLeftLayout = (layout) => {
    let source = Array.isArray(layout) ? layout : [...defaultLeftLayout];

    const hasHistory = source.includes("History");
    let historyInserted = false;
    const normalized = [];

    source.forEach((moduleName) => {
      if (moduleName === "P/C") {
        moduleName = "P/C";
      }
      if (moduleName === "D/H") {
        if (!hasHistory && !historyInserted) {
          normalized.push("History");
          historyInserted = true;
        }
        return;
      }
      if (moduleName === "History") {
        if (historyInserted) return;
        historyInserted = true;
      }
      normalized.push(moduleName);
    });

    return normalized;
  };
  const normalizeRightLayout = (layout) => {
    const next = Array.isArray(layout) ? [...layout] : [...defaultRightLayout];
    const reportsIndex = next.indexOf("Reports");
    if (reportsIndex !== -1) {
      const replacement = [];
      if (!next.includes("Report Entry")) replacement.push("Report Entry");
      if (!next.includes("Upload Reports & Documents") && !next.includes("Uploaded Reports")) {
        replacement.push("Upload Reports & Documents");
      }
      next.splice(reportsIndex, 1, ...replacement);
    }
    return next;
  };

  return {
    left: normalizeLeftLayout(savedLeft ? JSON.parse(savedLeft) : [...defaultLeftLayout]),
    right: normalizeRightLayout(savedRight ? JSON.parse(savedRight) : [...defaultRightLayout])
  };
}

// Layout lifecycle hook (server-side pre-rendered via PHP)
async function renderMainUI() {
  return Promise.resolve();
}

// Initialize layout setup controls and event listeners
function initSetupUI() {
  const btnSave = document.getElementById('btn-save-settings');
  const btnReset = document.getElementById('btn-reset-settings');
  if (btnSave) btnSave.addEventListener('click', saveSettings);
  if (btnReset) btnReset.addEventListener('click', resetSettings);
  initModuleOrderUI();
  initDropdownThemeUI();
}

function initModuleOrderUI() {
  const setupPage = document.querySelector('.interface-settings-page');
  if (!setupPage) return;

  const moduleLists = {
    left: { container: document.getElementById('left-side-setup'), options: availableLeftModules },
    right: { container: document.getElementById('right-side-setup'), options: availableRightModules }
  };
  const appendOrder = (side, value = '') => {
    const list = moduleLists[side];
    if (!list?.container) return;
    const index = list.container.querySelectorAll('.form-group').length;
    const row = createFormGroup(`Order #${String(index + 1).padStart(2, '0')}`, side, index, value, list.options);
    list.container.querySelector('.interface-order-empty')?.remove();
    list.container.appendChild(row);
    refreshModuleOrderUI(side);
    row.querySelector('select')?.focus();
  };

  Object.keys(moduleLists).forEach((side) => {
    const container = moduleLists[side].container;
    if (container && container.querySelectorAll('.form-group').length === 0) {
      const emptyState = document.createElement('p');
      emptyState.className = 'interface-order-empty';
      emptyState.textContent = 'No modules configured. Add an order to place one.';
      container.appendChild(emptyState);
    }
    refreshModuleOrderUI(side);
  });
  setupPage.addEventListener('change', (event) => {
    const select = event.target;
    const side = select instanceof HTMLSelectElement ? select.dataset.side : null;
    if (side === 'left' || side === 'right') refreshModuleOrderOptions(side);
  });
  document.querySelectorAll('select[data-side="history"]').forEach((select) => {
    const label = document.querySelector(`label[for="${select.id}"]`);
    if (label) setSetupSelectAccessibleName(select, label.textContent.trim());
  });

  setupPage.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-order-add]');
    if (addButton) {
      appendOrder(addButton.dataset.orderAdd);
      return;
    }

    const removeButton = event.target.closest('.interface-order-remove');
    if (!removeButton) return;

    const select = removeButton.closest('.form-group')?.querySelector('select[data-side]');
    const side = select?.dataset.side;
    const list = moduleLists[side];
    if (!list?.container) return;

    removeButton.closest('.form-group').remove();
    if (list.container.querySelectorAll('.form-group').length === 0) {
      const emptyState = document.createElement('p');
      emptyState.className = 'interface-order-empty';
      emptyState.textContent = 'No modules configured. Add an order to place one.';
      list.container.appendChild(emptyState);
    }
    refreshModuleOrderUI(side);
  });
}

function refreshModuleOrderUI(side) {
  const container = document.getElementById(`${side}-side-setup`);
  if (!container) return;

  const rows = Array.from(container.querySelectorAll('.form-group'));
  rows.forEach((row, index) => {
    const label = row.querySelector('.form-label');
    const select = row.querySelector(`select[data-side="${side}"]`);
    const removeButton = row.querySelector('.interface-order-remove');
    const orderLabel = `Order #${String(index + 1).padStart(2, '0')}`;
    if (label) {
      label.textContent = orderLabel;
      label.htmlFor = `interface-${side}-order-${index}`;
    }
    if (select) {
      select.dataset.index = String(index);
      select.id = `interface-${side}-order-${index}`;
      setSetupSelectAccessibleName(select, `${side === 'left' ? 'Left' : 'Right'} panel ${orderLabel}`);
    }
    if (removeButton) removeButton.setAttribute('aria-label', `Remove ${orderLabel}`);
  });

  const count = document.querySelector(`[data-order-count="${side}"]`);
  if (count) count.textContent = `${rows.length} ${rows.length === 1 ? 'order' : 'orders'}`;
  refreshModuleOrderOptions(side);
}

function refreshModuleOrderOptions(side) {
  const list = document.getElementById(`${side}-side-setup`);
  const moduleOptions = side === 'left' ? availableLeftModules : availableRightModules;
  if (!list || !moduleOptions) return;

  const selects = Array.from(list.querySelectorAll(`select[data-side="${side}"]`));
  const claimed = new Set();

  selects.forEach((select) => {
    const selected = select.value;
    if (!selected) return;
    if (claimed.has(selected)) {
      select.value = '';
      return;
    }
    claimed.add(selected);
  });

  selects.forEach((select) => {
    const currentValue = select.value;
    const selectedElsewhere = new Set(
      selects.filter((other) => other !== select).map((other) => other.value).filter(Boolean)
    );
    const allowedOptions = moduleOptions.filter((option) =>
      option === '' || option === currentValue || !selectedElsewhere.has(option)
    );
    const currentOptions = Array.from(select.options).map((option) => option.value);

    if (currentOptions.length === allowedOptions.length
      && currentOptions.every((value, index) => value === allowedOptions[index])) {
      return;
    }

    select.replaceChildren(...allowedOptions.map((value) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value === '' ? '-- None --' : value;
      option.selected = value === currentValue;
      return option;
    }));
  });
}

function setSetupSelectAccessibleName(select, label) {
  select.setAttribute('aria-label', label);
  const trigger = select.nextElementSibling?.querySelector('.zrx-select-trigger');
  if (trigger) trigger.setAttribute('aria-label', label);
}

function initDropdownThemeUI() {
  const container = document.getElementById('dropdown-presets-container');
  if (!container) return;

  const presets = {
    'subtle-tint': { bg: '#ebedf0', text: '#0f172a', originalText: true },
    'slate-gray':  { bg: '#868e96', text: '#ffffff', originalText: false },
    'charcoal':    { bg: '#475569', text: '#ffffff', originalText: false },
    'theme-blue':  { bg: '#2563eb', text: '#ffffff', originalText: false },
    'soft-blue':   { bg: '#eff6ff', text: '#1d4ed8', originalText: false },
    'emerald':     { bg: '#16a34a', text: '#ffffff', originalText: false },
    'custom':      { bg: '#ebedf0', text: '#0f172a', originalText: true }
  };

  const getCookieVal = (name) => {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
  };

  const allowedThemes = new Set(['slate-gray', 'subtle-tint', 'charcoal', 'theme-blue', 'soft-blue', 'emerald', 'custom']);
  const savedThemeValue = getCookieVal('zimrx_dropdown_theme');
  const savedBgValue = getCookieVal('zimrx_dropdown_hover_bg');
  const savedTextValue = getCookieVal('zimrx_dropdown_hover_text');
  const savedTheme = allowedThemes.has(savedThemeValue) ? savedThemeValue : 'slate-gray';
  const savedBg = /^#[0-9a-f]{6}$/i.test(savedBgValue || '') ? savedBgValue : '#868e96';
  const savedText = /^#[0-9a-f]{6}$/i.test(savedTextValue || '') ? savedTextValue : '#ffffff';

  const customControls   = document.getElementById('dd-custom-controls');
  const customBgPicker   = document.getElementById('dd-custom-bg-picker');
  const customBgHex      = document.getElementById('dd-custom-bg-hex');
  const customTextWrapper= document.getElementById('dd-custom-text-wrapper');
  const customTextPicker = document.getElementById('dd-custom-text-picker');
  const customTextHex    = document.getElementById('dd-custom-text-hex');
  const customOriginalCb = document.getElementById('dd-custom-original-text-cb');
  const customSwatch     = document.getElementById('dd-custom-preview-swatch');

  // Track the previously selected preset so Custom Colors adopts its config
  let lastPreset = presets[savedTheme] ? { ...presets[savedTheme], theme: savedTheme } : { bg: savedBg, text: savedText, originalText: (savedTheme === 'subtle-tint'), theme: savedTheme };

  function updateLiveDemo(bg, text, isOriginalText = false) {
    if (customSwatch) {
      customSwatch.style.backgroundColor = bg;
      customSwatch.style.color = isOriginalText ? '#0f172a' : text;
    }
  }

  function syncCustomTextControls(isOriginal) {
    if (!customTextWrapper) return;
    if (isOriginal) {
      customTextWrapper.style.opacity = '0.35';
      customTextWrapper.style.pointerEvents = 'none';
    } else {
      customTextWrapper.style.opacity = '1';
      customTextWrapper.style.pointerEvents = 'auto';
    }
  }

  function applySelection(themeKey, bg, text, isOriginalText = false) {
    document.querySelectorAll('.dd-theme-card').forEach(card => {
      const isMatch = (card.dataset.theme === themeKey);
      card.classList.toggle('selected', isMatch);
      const radio = card.querySelector('input[type="radio"]');
      if (radio) radio.checked = isMatch;
    });

    if (customControls) {
      customControls.style.display = (themeKey === 'custom') ? 'flex' : 'none';
    }

    if (themeKey !== 'custom' && presets[themeKey]) {
      bg = presets[themeKey].bg;
      text = presets[themeKey].text;
      isOriginalText = presets[themeKey].originalText || false;
      lastPreset = { theme: themeKey, bg, text, originalText: isOriginalText };
    } else if (themeKey === 'custom') {
      // Seed custom inputs from the previously selected preset!
      if (lastPreset) {
        bg = lastPreset.bg;
        text = lastPreset.text;
        isOriginalText = lastPreset.originalText || false;
      }
      if (customOriginalCb) customOriginalCb.checked = isOriginalText;
      syncCustomTextControls(isOriginalText);
    }

    if (customBgPicker) customBgPicker.value = bg;
    if (customBgHex) customBgHex.value = bg;
    if (customTextPicker) customTextPicker.value = text;
    if (customTextHex) customTextHex.value = text;

    updateLiveDemo(bg, text, isOriginalText);
    document.dispatchEvent(new Event('zrx-dropdown-preview-theme-change'));
  }

  document.querySelectorAll('.dd-theme-card').forEach(card => {
    card.addEventListener('click', () => {
      const themeKey = card.dataset.theme;
      const bg = card.dataset.bg;
      const text = card.dataset.text;
      const isOriginal = (themeKey === 'subtle-tint');
      applySelection(themeKey, bg, text, isOriginal);
    });
  });

  if (customOriginalCb) {
    customOriginalCb.addEventListener('change', () => {
      const isOriginal = customOriginalCb.checked;
      syncCustomTextControls(isOriginal);
      const bg = customBgHex ? customBgHex.value : '#ebedf0';
      const text = customTextHex ? customTextHex.value : '#ffffff';
      updateLiveDemo(bg, text, isOriginal);
    });
  }

  if (customBgPicker && customBgHex) {
    customBgPicker.addEventListener('input', () => {
      customBgHex.value = customBgPicker.value;
      const isOriginal = customOriginalCb ? customOriginalCb.checked : false;
      updateLiveDemo(customBgPicker.value, customTextHex ? customTextHex.value : '#ffffff', isOriginal);
    });
    customBgHex.addEventListener('input', () => {
      if (/^#[0-9a-fA-F]{6}$/.test(customBgHex.value)) {
        customBgPicker.value = customBgHex.value;
        const isOriginal = customOriginalCb ? customOriginalCb.checked : false;
        updateLiveDemo(customBgHex.value, customTextHex ? customTextHex.value : '#ffffff', isOriginal);
      }
    });
  }

  if (customTextPicker && customTextHex) {
    customTextPicker.addEventListener('input', () => {
      customTextHex.value = customTextPicker.value;
      const isOriginal = customOriginalCb ? customOriginalCb.checked : false;
      updateLiveDemo(customBgHex ? customBgHex.value : '#868e96', customTextPicker.value, isOriginal);
    });
    customTextHex.addEventListener('input', () => {
      if (/^#[0-9a-fA-F]{6}$/.test(customTextHex.value)) {
        customTextPicker.value = customTextHex.value;
        const isOriginal = customOriginalCb ? customOriginalCb.checked : false;
        updateLiveDemo(customBgHex ? customBgHex.value : '#868e96', customTextPicker.value, isOriginal);
      }
    });
  }

  const isInitialOriginal = (savedTheme === 'subtle-tint');
  applySelection(savedTheme, savedBg, savedText, isInitialOriginal);
}

function createFormGroup(labelText, side, index, currentValue, options) {
  const group = document.createElement('div');
  group.className = 'form-group';

  const label = document.createElement('label');
  label.className = 'form-label';
  label.textContent = labelText;
  label.htmlFor = `interface-${side}-order-${index}`;

  const select = document.createElement('select');
  select.className = 'form-select zrx-select';
  select.dataset.side = side;
  select.dataset.index = index;
  select.id = `interface-${side}-order-${index}`;

  options.forEach(opt => {
    const optionElement = document.createElement('option');
    optionElement.value = opt;
    optionElement.textContent = opt === "" ? "-- None --" : opt;
    if (opt === currentValue) {
      optionElement.selected = true;
    }
    select.appendChild(optionElement);
  });

  group.appendChild(label);
  group.appendChild(select);
  if (window.ZimRxDropdown && typeof window.ZimRxDropdown.enhanceSelect === 'function') {
    window.ZimRxDropdown.enhanceSelect(select);
  }

  if (side === 'left' || side === 'right') {
    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'interface-order-remove';
    removeButton.textContent = 'Remove';
    removeButton.setAttribute('aria-label', `Remove ${labelText}`);
    group.appendChild(removeButton);
  }

  return group;
}

async function saveSettings() {
  const leftSelects    = document.querySelectorAll('select[data-side="left"]');
  const rightSelects   = document.querySelectorAll('select[data-side="right"]');
  const historySelects = document.querySelectorAll('select[data-side="history"]');

  const newLeftLayout    = Array.from(leftSelects).map(sel => sel.value).filter(Boolean);
  const newRightLayout   = Array.from(rightSelects).map(sel => sel.value).filter(Boolean);
  const newHistoryLayout = Array.from(historySelects).map(sel => sel.value);

  // Save Dropdown Theme
  const activeThemeRadio = document.querySelector('input[name="dropdown_theme"]:checked');
  let themeKey = activeThemeRadio ? activeThemeRadio.value : 'slate-gray';
  let ddBg = '#868e96';
  let ddText = '#ffffff';
  let isOriginalText = (themeKey === 'subtle-tint');

  if (themeKey === 'custom') {
    ddBg = document.getElementById('dd-custom-bg-hex')?.value || '#ebedf0';
    const isOriginalCb = document.getElementById('dd-custom-original-text-cb');
    isOriginalText = isOriginalCb ? isOriginalCb.checked : false;
    if (isOriginalText) {
      themeKey = 'subtle-tint';
      ddText = '#0f172a';
    } else {
      ddText = document.getElementById('dd-custom-text-hex')?.value || '#ffffff';
    }
  } else {
    const card = document.querySelector(`.dd-theme-card[data-theme="${themeKey}"]`);
    if (card) {
      ddBg = card.dataset.bg || '#868e96';
      ddText = card.dataset.text || '#ffffff';
    }
  }

  if (!/^#[0-9a-f]{6}$/i.test(ddBg) || !/^#[0-9a-f]{6}$/i.test(ddText)) {
    showSetupToast('Choose valid dropdown highlight colors.');
    return;
  }

  try {
    const response = await fetch('api/save_interface_layout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        left_layout: newLeftLayout,
        right_layout: newRightLayout,
        history_layout: newHistoryLayout,
        dropdown_theme: themeKey,
        dropdown_hover_bg: ddBg,
        dropdown_hover_text: ddText
      })
    });
    const data = await response.json();
    if (!response.ok || data.error) {
      throw new Error(data.error || 'Settings could not be saved.');
    }

    localStorage.setItem(storageKeys.leftLayout, JSON.stringify(newLeftLayout));
    localStorage.setItem(storageKeys.rightLayout, JSON.stringify(newRightLayout));
    localStorage.setItem(storageKeys.historyLayout, JSON.stringify(newHistoryLayout));
    localStorage.setItem('zimrx_layout_v', '3');

    const secureCookie = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `zimrx_left_layout=${encodeURIComponent(JSON.stringify(newLeftLayout))}; path=/; max-age=31536000; SameSite=Lax${secureCookie}`;
    document.cookie = `zimrx_right_layout=${encodeURIComponent(JSON.stringify(newRightLayout))}; path=/; max-age=31536000; SameSite=Lax${secureCookie}`;
    document.cookie = `zimrx_history_layout=${encodeURIComponent(JSON.stringify(newHistoryLayout))}; path=/; max-age=31536000; SameSite=Lax${secureCookie}`;
    document.cookie = `zimrx_layout_v=3; path=/; max-age=31536000; SameSite=Lax${secureCookie}`;
    document.cookie = `zimrx_dropdown_theme=${encodeURIComponent(themeKey)}; path=/; max-age=31536000; SameSite=Lax${secureCookie}`;
    document.cookie = `zimrx_dropdown_hover_bg=${encodeURIComponent(ddBg)}; path=/; max-age=31536000; SameSite=Lax${secureCookie}`;
    document.cookie = `zimrx_dropdown_hover_text=${encodeURIComponent(ddText)}; path=/; max-age=31536000; SameSite=Lax${secureCookie}`;

    document.documentElement.setAttribute('data-dropdown-theme', themeKey);
    document.documentElement.style.setProperty('--zrx-dropdown-hover-bg', ddBg);
    document.documentElement.style.setProperty('--zrx-dropdown-hover-text', ddText);
    showSetupToast('Saved successfully');
  } catch (error) {
    console.error('Failed to save interface settings:', error);
    showSetupToast('Could not save settings. Please try again.');
  }
}


function showSetupToast(message) {
  const toast = document.getElementById('setup-toast');
  const toastMessage = document.getElementById('setup-toast-message');
  const toastClose = document.getElementById('setup-toast-close');
  if (toast && toastMessage) {
    toastMessage.textContent = message;
    toast.hidden = false;
    if (toastClose) {
      toastClose.focus();
      toastClose.onclick = () => {
        toast.hidden = true;
      };
    }
    toast.onclick = (e) => {
      if (e.target === toast) {
        toast.hidden = true;
      }
    };
  } else {
    alert(message);
  }
}

function resetSettings() {
  const confirmModal = document.getElementById('setup-confirm-modal');
  const confirmYes = document.getElementById('confirm-reset-yes');
  const confirmCancel = document.getElementById('confirm-reset-cancel');

  if (confirmModal) {
    confirmModal.hidden = false;

    confirmYes.onclick = () => {
      confirmModal.hidden = true;
      const defaultHistoryLayout = ["medical", "treatment", "habits", "diet-hypersensitivity", "drug-history"];

      const leftList = document.getElementById('left-side-setup');
      const rightList = document.getElementById('right-side-setup');
      if (leftList) leftList.replaceChildren(...defaultLeftLayout.map((moduleName, index) => createFormGroup(`Order #${String(index + 1).padStart(2, '0')}`, 'left', index, moduleName, availableLeftModules)));
      if (rightList) rightList.replaceChildren(...defaultRightLayout.map((moduleName, index) => createFormGroup(`Order #${String(index + 1).padStart(2, '0')}`, 'right', index, moduleName, availableRightModules)));
      refreshModuleOrderUI('left');
      refreshModuleOrderUI('right');

      // Reset History Grid Selects
      document.querySelectorAll('select[data-side="history"]').forEach(select => {
        const idx = parseInt(select.dataset.index, 10);
        select.value = defaultHistoryLayout[idx] || "";
      });

      localStorage.removeItem('zimrx_dropdown_theme');
      localStorage.removeItem('zimrx_dropdown_hover_bg');
      localStorage.removeItem('zimrx_dropdown_hover_text');
      document.cookie = `zimrx_dropdown_theme=; path=/; max-age=0; SameSite=Lax`;
      document.cookie = `zimrx_dropdown_hover_bg=; path=/; max-age=0; SameSite=Lax`;
      document.cookie = `zimrx_dropdown_hover_text=; path=/; max-age=0; SameSite=Lax`;
      document.documentElement.setAttribute('data-dropdown-theme', 'slate-gray');
      initDropdownThemeUI();

      showSetupToast('Default layout restored. Save to apply.');
    };

    const closeConfirm = () => {
      confirmModal.hidden = true;
    };

    confirmCancel.onclick = closeConfirm;
    confirmModal.onclick = (e) => {
      if (e.target === confirmModal) {
        closeConfirm();
      }
    };
  }
}

// Auto-run on DOMContentLoaded if on setup page
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSetupUI);
} else {
  initSetupUI();
}

// Sync layout settings between localStorage and cookies
(function() {
  try {
    const getCookieValue = (name) => {
      const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
      return match ? decodeURIComponent(match[2].replace(/\+/g, ' ')) : null;
    };

    let localLeft = localStorage.getItem(storageKeys.leftLayout);
    let localRight = localStorage.getItem(storageKeys.rightLayout);
    let localHistory = localStorage.getItem(storageKeys.historyLayout);
    const localV = localStorage.getItem('zimrx_layout_v');
    const cookieV = getCookieValue('zimrx_layout_v');

    // Auto-migrate legacy layout if version 3 is not established in localStorage
    if (localV !== '3') {
      try {
        if (localLeft) {
          const parsedLeft = JSON.parse(localLeft);
          if (Array.isArray(parsedLeft)) {
            localLeft = JSON.stringify(parsedLeft.filter(m => m !== 'Note' && m !== 'Paediatric History'));
            localStorage.setItem(storageKeys.leftLayout, localLeft);
          }
        }
      } catch (_) {}

      try {
        if (localRight) {
          const parsedRight = JSON.parse(localRight);
          if (Array.isArray(parsedRight)) {
            localRight = JSON.stringify(parsedRight.filter(m => m !== 'Drug Summary & Interaction'));
            localStorage.setItem(storageKeys.rightLayout, localRight);
          }
        }
      } catch (_) {}

      localStorage.setItem('zimrx_layout_v', '3');
    }

    // Auto-migrate cookie if not version 3
    if (cookieV !== '3') {
      document.cookie = `zimrx_layout_v=3; path=/; max-age=31536000; SameSite=Lax`;
      if (localLeft) {
        document.cookie = `zimrx_left_layout=${encodeURIComponent(localLeft)}; path=/; max-age=31536000; SameSite=Lax`;
      }
      if (localRight) {
        document.cookie = `zimrx_right_layout=${encodeURIComponent(localRight)}; path=/; max-age=31536000; SameSite=Lax`;
      }
    }

    let needsCookieSync = false;

    // Sync from localStorage to cookies
    if (localLeft && !document.cookie.includes('zimrx_left_layout')) {
      document.cookie = `zimrx_left_layout=${encodeURIComponent(localLeft)}; path=/; max-age=31536000; SameSite=Lax`;
      needsCookieSync = true;
    }
    if (localRight && !document.cookie.includes('zimrx_right_layout')) {
      document.cookie = `zimrx_right_layout=${encodeURIComponent(localRight)}; path=/; max-age=31536000; SameSite=Lax`;
      needsCookieSync = true;
    }
    if (localHistory && !document.cookie.includes('zimrx_history_layout')) {
      document.cookie = `zimrx_history_layout=${encodeURIComponent(localHistory)}; path=/; max-age=31536000; SameSite=Lax`;
      needsCookieSync = true;
    }

    if (needsCookieSync) {
      window.location.reload();
      return;
    }

    const cookieLeft = getCookieValue('zimrx_left_layout');
    const cookieRight = getCookieValue('zimrx_right_layout');
    const cookieHistory = getCookieValue('zimrx_history_layout');

    if (cookieLeft && cookieLeft !== localLeft) {
      localStorage.setItem(storageKeys.leftLayout, cookieLeft);
    }
    if (cookieRight && cookieRight !== localRight) {
      localStorage.setItem(storageKeys.rightLayout, cookieRight);
    }
    if (cookieHistory && cookieHistory !== localHistory) {
      localStorage.setItem(storageKeys.historyLayout, cookieHistory);
    }
    if (cookieV === '3' && localV !== '3') {
      localStorage.setItem('zimrx_layout_v', '3');
    }
  } catch(e) {
    console.error("Layout cookie auto-sync failed:", e);
  }
})();
