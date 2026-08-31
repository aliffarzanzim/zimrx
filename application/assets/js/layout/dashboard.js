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
    if (next.includes("Rx") && !next.includes("Drug Summary & Interaction")) {
      next.splice(next.indexOf("Rx") + 1, 0, "Drug Summary & Interaction");
    }
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

/**
 * Main UI: Render the modules dynamically into the grid via AJAX
 */
async function renderMainUI() {
  // Modules are fully prerendered on the server using PHP for instant, seamless load!
  return Promise.resolve();
}

/**
 * Setup UI: Populate dropdowns and handle saving
 */
function initSetupUI() {
  const btnSave = document.getElementById('btn-save-settings');
  const btnReset = document.getElementById('btn-reset-settings');
  if (btnSave) btnSave.addEventListener('click', saveSettings);
  if (btnReset) btnReset.addEventListener('click', resetSettings);
  initDropdownThemeUI();
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

  let savedTheme = localStorage.getItem(storageKeys.dropdownTheme) || getCookieVal('zimrx_dropdown_theme') || 'subtle-tint';
  let savedBg    = localStorage.getItem(storageKeys.dropdownHoverBg) || getCookieVal('zimrx_dropdown_hover_bg') || (presets[savedTheme] ? presets[savedTheme].bg : '#868e96');
  let savedText  = localStorage.getItem(storageKeys.dropdownHoverText) || getCookieVal('zimrx_dropdown_hover_text') || (presets[savedTheme] ? presets[savedTheme].text : '#ffffff');

  const customControls   = document.getElementById('dd-custom-controls');
  const customBgPicker   = document.getElementById('dd-custom-bg-picker');
  const customBgHex      = document.getElementById('dd-custom-bg-hex');
  const customTextWrapper= document.getElementById('dd-custom-text-wrapper');
  const customTextPicker = document.getElementById('dd-custom-text-picker');
  const customTextHex    = document.getElementById('dd-custom-text-hex');
  const customOriginalCb = document.getElementById('dd-custom-original-text-cb');
  const customSwatch     = document.getElementById('dd-custom-preview-swatch');

  const demoActiveItem = document.getElementById('demo-dd-active-item');
  const demoItems      = document.querySelectorAll('#demo-dd-menu .demo-dd-item');

  // Track the previously selected preset so Custom Colors adopts its config
  let lastPreset = presets[savedTheme] ? { ...presets[savedTheme], theme: savedTheme } : { bg: savedBg, text: savedText, originalText: (savedTheme === 'subtle-tint'), theme: savedTheme };

  function updateLiveDemo(bg, text, isOriginalText = false) {
    if (demoActiveItem) {
      demoActiveItem.style.backgroundColor = bg;
      const code = demoActiveItem.querySelector('.demo-code');
      const name = demoActiveItem.querySelector('.demo-name');
      const meta = demoActiveItem.querySelector('.demo-meta');
      if (isOriginalText) {
        if (code) code.style.color = '#0284c7';
        if (name) name.style.color = '#0f172a';
        if (meta) meta.style.color = '#64748b';
      } else {
        if (code) code.style.color = text;
        if (name) name.style.color = text;
        if (meta) meta.style.color = text;
      }
    }
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

  demoItems.forEach(item => {
    item.addEventListener('mouseenter', () => {
      demoItems.forEach(it => {
        it.classList.remove('active');
        it.style.backgroundColor = '';
        const c = it.querySelector('.demo-code');
        const n = it.querySelector('.demo-name');
        const m = it.querySelector('.demo-meta');
        if (c) c.style.color = '#0284c7';
        if (n) n.style.color = '#0f172a';
        if (m) m.style.color = '#64748b';
      });
      item.classList.add('active');
      const activeRadio = document.querySelector('input[name="dropdown_theme"]:checked');
      const activeTheme = activeRadio ? activeRadio.value : 'slate-gray';
      let bg = '#868e96';
      let text = '#ffffff';
      let isOriginal = false;

      if (activeTheme === 'custom') {
        bg = customBgHex?.value || '#ebedf0';
        isOriginal = customOriginalCb ? customOriginalCb.checked : false;
        text = isOriginal ? '#0f172a' : (customTextHex?.value || '#ffffff');
      } else {
        bg = presets[activeTheme]?.bg || '#868e96';
        isOriginal = presets[activeTheme]?.originalText || false;
        text = presets[activeTheme]?.text || '#ffffff';
      }

      item.style.backgroundColor = bg;
      const c = item.querySelector('.demo-code');
      const n = item.querySelector('.demo-name');
      const m = item.querySelector('.demo-meta');
      if (isOriginal) {
        if (c) c.style.color = '#0284c7';
        if (n) n.style.color = '#0f172a';
        if (m) m.style.color = '#64748b';
      } else {
        if (c) c.style.color = text;
        if (n) n.style.color = text;
        if (m) m.style.color = text;
      }
    });
  });

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
  label.innerText = labelText;

  const select = document.createElement('select');
  select.className = 'form-select';
  select.dataset.side = side;
  select.dataset.index = index;

  options.forEach(opt => {
    const optionElement = document.createElement('option');
    optionElement.value = opt;
    optionElement.innerText = opt === "" ? "-- None --" : opt;
    if (opt === currentValue) {
      optionElement.selected = true;
    }
    select.appendChild(optionElement);
  });

  group.appendChild(label);
  group.appendChild(select);

  return group;
}

function saveSettings() {
  const leftSelects    = document.querySelectorAll('select[data-side="left"]');
  const rightSelects   = document.querySelectorAll('select[data-side="right"]');
  const historySelects = document.querySelectorAll('select[data-side="history"]');

  const newLeftLayout    = Array.from(leftSelects).map(sel => sel.value);
  const newRightLayout   = Array.from(rightSelects).map(sel => sel.value);
  const newHistoryLayout = Array.from(historySelects).map(sel => sel.value);

  localStorage.setItem(storageKeys.leftLayout,    JSON.stringify(newLeftLayout));
  localStorage.setItem(storageKeys.rightLayout,   JSON.stringify(newRightLayout));
  localStorage.setItem(storageKeys.historyLayout, JSON.stringify(newHistoryLayout));

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

  localStorage.setItem(storageKeys.dropdownTheme, themeKey);
  localStorage.setItem(storageKeys.dropdownHoverBg, ddBg);
  localStorage.setItem(storageKeys.dropdownHoverText, ddText);

  // Save to Cookies so that the server can prerender instantly
  document.cookie = `zimrx_left_layout=${encodeURIComponent(JSON.stringify(newLeftLayout))}; path=/; max-age=31536000; SameSite=Lax`;
  document.cookie = `zimrx_right_layout=${encodeURIComponent(JSON.stringify(newRightLayout))}; path=/; max-age=31536000; SameSite=Lax`;
  document.cookie = `zimrx_history_layout=${encodeURIComponent(JSON.stringify(newHistoryLayout))}; path=/; max-age=31536000; SameSite=Lax`;
  document.cookie = `zimrx_dropdown_theme=${encodeURIComponent(themeKey)}; path=/; max-age=31536000; SameSite=Lax`;
  document.cookie = `zimrx_dropdown_hover_bg=${encodeURIComponent(ddBg)}; path=/; max-age=31536000; SameSite=Lax`;
  document.cookie = `zimrx_dropdown_hover_text=${encodeURIComponent(ddText)}; path=/; max-age=31536000; SameSite=Lax`;

  document.documentElement.setAttribute('data-dropdown-theme', themeKey);
  document.documentElement.style.setProperty('--zrx-dropdown-hover-bg', ddBg);
  document.documentElement.style.setProperty('--zrx-dropdown-hover-text', ddText);

  // Save to Database via AJAX
  fetch('api/save_interface_layout.php', {
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
  })
  .then(res => res.json())
  .then(data => {
    if (data.error) {
      console.error('Failed to sync layout to database:', data.error);
    }
  })
  .catch(err => console.error('Database sync error:', err));

  // Show Toast modal dialog
  showSetupToast('Saved successfully');
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

      // Reset Left Grid Selects
      document.querySelectorAll('select[data-side="left"]').forEach(select => {
        const idx = parseInt(select.dataset.index, 10);
        select.value = defaultLeftLayout[idx] || "";
      });

      // Reset Right Grid Selects
      document.querySelectorAll('select[data-side="right"]').forEach(select => {
        const idx = parseInt(select.dataset.index, 10);
        select.value = defaultRightLayout[idx] || "";
      });

      // Reset History Grid Selects
      document.querySelectorAll('select[data-side="history"]').forEach(select => {
        const idx = parseInt(select.dataset.index, 10);
        select.value = defaultHistoryLayout[idx] || "";
      });

      localStorage.removeItem(storageKeys.dropdownTheme);
      localStorage.removeItem(storageKeys.dropdownHoverBg);
      localStorage.removeItem(storageKeys.dropdownHoverText);
      document.cookie = `zimrx_dropdown_theme=; path=/; max-age=0; SameSite=Lax`;
      document.cookie = `zimrx_dropdown_hover_bg=; path=/; max-age=0; SameSite=Lax`;
      document.cookie = `zimrx_dropdown_hover_text=; path=/; max-age=0; SameSite=Lax`;
      document.documentElement.setAttribute('data-dropdown-theme', 'subtle-tint');
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

// Auto-Sync Hook: Bidirectional sync between Cookies and LocalStorage
(function() {
  try {
    const localLeft = localStorage.getItem(storageKeys.leftLayout);
    const localRight = localStorage.getItem(storageKeys.rightLayout);
    const localHistory = localStorage.getItem(storageKeys.historyLayout);
    let needsCookieSync = false;

    // 1. LocalStorage -> Cookies (e.g. cookies cleared or fresh browser with local data)
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

    // 2. Cookies -> LocalStorage (e.g. server pulled new layout from DB and updated the cookie)
    const getCookieValue = (name) => {
      const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
      return match ? decodeURIComponent(match[2]) : null;
    };

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
  } catch(e) {
    console.error("Layout cookie auto-sync failed:", e);
  }
})();

/**
 * Handle Rx Autocomplete Dropdown Logic
 */
