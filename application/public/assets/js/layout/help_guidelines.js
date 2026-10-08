// Clinical help guidelines modal controller for patient particulars, address directories, and occupation management.

let currentHelpModalType = null;
let currentHelpModalLang = null;
window.ZimRxHelpGuidelinesCatalogs = window.ZimRxHelpGuidelinesCatalogs || {};

function getActiveHelpLanguage() {
  const saved = localStorage.getItem('zimrx_help_lang');
  if (saved === 'bn' || saved === 'en') {
    return saved;
  }
  if (window.ZimRxHelpInitialLang === 'bn' || window.ZimRxHelpInitialLang === 'en') {
    return window.ZimRxHelpInitialLang;
  }
  const isBd = (window.ZimRxPracticeCountry === 'BD') ||
               (window.ZimRxState && window.ZimRxState.practiceCountry === 'BD') ||
               (document.documentElement.lang === 'bn');
  return isBd ? 'bn' : 'en';
}

async function loadHelpGuidelinesCatalog(lang) {
  if (window.ZimRxHelpGuidelinesCatalogs[lang] && Object.keys(window.ZimRxHelpGuidelinesCatalogs[lang]).length > 0) {
    return window.ZimRxHelpGuidelinesCatalogs[lang];
  }
  try {
    const res = await fetch(`api/get_help_guidelines.php?lang=${encodeURIComponent(lang)}`);
    if (res.ok) {
      const data = await res.json();
      if (data && typeof data === 'object') {
        window.ZimRxHelpGuidelinesCatalogs[lang] = data;
        return data;
      }
    }
  } catch (e) {
    console.error('Failed to load help guidelines catalog:', e);
  }
  return window.ZimRxHelpGuidelinesCatalogs['en'] || window.ZimRxHelpGuidelinesCatalogs['bn'] || {};
}

async function showHelpGuidelineModal(type, requestedLang) {
  currentHelpModalType = type;
  const lang = requestedLang || getActiveHelpLanguage();
  currentHelpModalLang = lang;

  const catalog = await loadHelpGuidelinesCatalog(lang);
  const data = catalog[type] || {};
  if (!data || !data.title) return;

  const modalUi = catalog.modal_ui || {};

  let overlay = document.getElementById('zrx-help-modal-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'zrx-help-modal-overlay';
    overlay.className = 'zrx-help-modal-overlay';
    overlay.innerHTML = `
      <div class="zrx-help-modal-card" id="zrx-help-modal-card" role="dialog" aria-modal="true">
        <div class="zrx-help-modal-header">
          <div class="zrx-help-modal-title-wrap">
            <span class="zrx-help-modal-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </span>
            <div>
              <h3 id="zrx-help-modal-title"></h3>
              <span id="zrx-help-modal-badge" class="zrx-help-modal-badge"></span>
            </div>
          </div>
          <div class="zrx-help-modal-header-actions">
            <div class="zrx-help-lang-toggle" role="group" aria-label="Help Language">
              <button type="button" class="zrx-help-lang-btn" data-help-lang="bn">বাংলা</button>
              <button type="button" class="zrx-help-lang-btn" data-help-lang="en">EN</button>
            </div>
            <button type="button" class="zrx-help-modal-close" id="zrx-help-modal-close-btn" aria-label="Close">✕</button>
          </div>
        </div>
        <div class="zrx-help-modal-body" id="zrx-help-modal-body"></div>
        <div class="zrx-help-modal-footer">
          <button type="button" class="zrx-help-modal-btn" id="zrx-help-modal-ok-btn">বুঝেছি</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const closeHelp = () => {
      overlay.classList.remove('active');
    };

    overlay.querySelector('#zrx-help-modal-close-btn').addEventListener('click', closeHelp);
    overlay.querySelector('#zrx-help-modal-ok-btn').addEventListener('click', closeHelp);
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeHelp();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('active')) closeHelp();
    });

    overlay.querySelectorAll('.zrx-help-lang-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const targetLang = btn.getAttribute('data-help-lang');
        localStorage.setItem('zimrx_help_lang', targetLang);
        showHelpGuidelineModal(currentHelpModalType, targetLang);
      });
    });
  }

  const card = overlay.querySelector('#zrx-help-modal-card');
  if (card) {
    if (data.wide) card.classList.add('zrx-help-modal-wide');
    else card.classList.remove('zrx-help-modal-wide');
  }

  document.getElementById('zrx-help-modal-title').textContent = data.title;
  document.getElementById('zrx-help-modal-badge').textContent = data.badge;

  overlay.querySelectorAll('.zrx-help-lang-btn').forEach(btn => {
    btn.classList.toggle('active', btn.getAttribute('data-help-lang') === lang);
  });

  const okBtn = overlay.querySelector('#zrx-help-modal-ok-btn');
  if (okBtn) {
    okBtn.textContent = modalUi.btn_ok || ((lang === 'bn') ? 'বুঝেছি' : 'Got it');
  }

  let bodyHtml = (data.steps || []).map(step => `
    <div class="zrx-help-step">
      <div class="zrx-help-step-icon">${step.icon || '•'}</div>
      <div class="zrx-help-step-text">${step.text || ''}</div>
    </div>
  `).join('');

  if (type === 'occupation') {
    const occUi = data.ui || {};
    bodyHtml += `
      <div class="zrx-occ-toolbar">
        <div class="zrx-occ-add-form">
          <input type="text" id="zrx-new-occ-input" placeholder="${occUi.placeholder_input || 'Type new occupation...'}" autocomplete="off">
          <button type="button" id="zrx-btn-add-occ" class="zrx-btn-primary">${occUi.btn_add || '+ Add'}</button>
        </div>
        <button type="button" id="zrx-btn-reset-occ" class="zrx-btn-outline" title="${occUi.btn_reset || 'Reset to Default'}">${occUi.btn_reset || 'Reset to Default'}</button>
      </div>

      <table class="zrx-occ-table">
        <thead>
          <tr>
            <th class="zrx-help-th-drag">${occUi.th_move || 'Move'}</th>
            <th class="zrx-help-th-actions">${occUi.th_actions || 'Actions'}</th>
            <th class="zrx-help-th-sl">${occUi.th_type || 'Type'}</th>
            <th class="zrx-ta-l">${occUi.th_name || 'Occupation Name'}</th>
            <th class="zrx-help-th-usage">${occUi.th_usage || 'Usage'}</th>
          </tr>
        </thead>
        <tbody id="zrx-occ-table-body">
          <tr><td colspan="5" class="zrx-empty-cell">${occUi.loading || 'Loading occupations...'}</td></tr>
        </tbody>
      </table>
    `;
  }

  if (type === 'address') {
    const addrUi = data.ui || {};
    bodyHtml += `
      <!-- Section A: Preferred Districts -->
      <div class="zrx-addr-section">
        <div class="zrx-addr-section-header">
          <div class="zrx-addr-section-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            ${addrUi.section_districts_title || 'Practice Districts Filter'}
          </div>
          <div class="zrx-help-flex-gap6">
            <button type="button" id="zrx-btn-dist-all" class="zrx-btn-outline" class="zrx-help-btn-sm">${addrUi.btn_dist_all || 'All Districts'}</button>
            <button type="button" id="zrx-btn-dist-save" class="zrx-btn-primary" class="zrx-help-btn-sm-px10">${addrUi.btn_dist_save || 'Save Districts'}</button>
          </div>
        </div>
        <div class="zrx-district-search-bar">
          <input type="text" id="zrx-district-filter-input" placeholder="${addrUi.district_search_placeholder || 'Search District...'}" autocomplete="off">
        </div>
        <div class="zrx-district-chips-wrap" id="zrx-district-chips-container">
          <span class="zrx-help-text-meta">${addrUi.districts_loading || 'Loading districts...'}</span>
        </div>
      </div>

      <!-- Section B: Custom & System Addresses Table -->
      <div class="zrx-addr-section">
        <div class="zrx-addr-section-header">
          <div class="zrx-addr-section-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            ${addrUi.section_addr_title || 'Address Directory & Management (Top 100)'}
          </div>
          <button type="button" id="zrx-btn-reset-addr" class="zrx-btn-outline" class="zrx-help-btn-sm-px10" title="${addrUi.btn_reset_addr || 'Reset to Default'}">${addrUi.btn_reset_addr || 'Reset to Default'}</button>
        </div>

        <!-- Search & Filter Controls -->
        <div class="zrx-addr-filter-row">
          <div class="zrx-addr-search-wrap">
            <input type="text" id="zrx-addr-search-input" placeholder="${addrUi.addr_search_placeholder || 'Search locality, thana, upazila, district...'}" autocomplete="off">
          </div>
          <div class="zrx-addr-filter-tabs" id="zrx-addr-filter-tabs-container">
            <button type="button" class="zrx-addr-tab-btn active" data-addr-filter="all">${addrUi.tab_all || 'All (Top 100)'}</button>
            <button type="button" class="zrx-addr-tab-btn" data-addr-filter="custom">${addrUi.tab_custom || 'Custom Only'}</button>
            <button type="button" class="zrx-addr-tab-btn" data-addr-filter="system">${addrUi.tab_system || 'System (National)'}</button>
            <button type="button" class="zrx-addr-tab-btn" data-addr-filter="pinned">${addrUi.tab_pinned || 'Pinned'}</button>
          </div>
        </div>

        <div class="zrx-occ-toolbar" class="zrx-help-mt8">
          <div class="zrx-occ-add-form">
            <input type="text" id="zrx-new-addr-input" placeholder="${addrUi.new_addr_placeholder || 'Type new custom address/area...'}" autocomplete="off">
            <button type="button" id="zrx-btn-add-addr" class="zrx-btn-primary">${addrUi.btn_add_addr || '+ Add'}</button>
          </div>
        </div>

        <table class="zrx-occ-table" class="zrx-help-mt8">
          <thead>
            <tr>
              <th class="zrx-help-th-actions-alt">${addrUi.th_actions || 'Actions'}</th>
              <th class="zrx-help-th-sl-alt">${addrUi.th_type || 'Type'}</th>
              <th class="zrx-ta-l">${addrUi.th_address || 'Address / Combination'}</th>
              <th class="zrx-help-th-usage">${addrUi.th_usage || 'Usage'}</th>
            </tr>
          </thead>
          <tbody id="zrx-addr-table-body">
            <tr><td colspan="4" class="zrx-empty-cell">${addrUi.addr_loading || 'Loading addresses...'}</td></tr>
          </tbody>
        </table>
      </div>
    `;
  }

  // SAFETY: bodyHtml is constructed from sanitized localized JSON catalog text
  // and local layout controls.
  document.getElementById('zrx-help-modal-body').innerHTML = bodyHtml;
  overlay.classList.add('active');

  if (type === 'occupation') {
    initOccupationManager(overlay, data.ui || {});
  } else if (type === 'address') {
    initAddressManager(overlay, data.ui || {});
  }
}

function initOccupationManager(overlay, occUi) {
  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  };

  let draggingRow = null;

  const loadOccupationTable = () => {
    const tbody = document.getElementById('zrx-occ-table-body');
    if (!tbody) return;

    fetch('api/occupation_settings.php?action=list')
      .then(res => res.json())
      .then(resData => {
        const list = resData.occupations || [];
        if (!list.length) {
          tbody.innerHTML = `<tr><td colspan="5" class="zrx-empty-cell">${escapeHtml(occUi.empty || 'No occupations found.')}</td></tr>`;
          return;
        }

        tbody.innerHTML = list.map(occ => {
          const isPinned = Number(occ.is_pinned) === 1;
          const isHidden = Number(occ.is_hidden) === 1;
          const kind = escapeHtml(String(occ.kind ?? ''));
          const name = escapeHtml(String(occ.name ?? ''));
          const isSystem = occ.kind === 'system';
          const moveIcon = typeof ZimRxIcon !== 'undefined' 
            ? ZimRxIcon.render('move', 14) 
            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>';
          
          return `
            <tr class="pc-row phrase-row ${isPinned ? 'pinned' : ''} ${isHidden ? 'hidden' : ''}" data-name="${name}" draggable="true">
              <td class="pc-action pc-move phrase-handle" class="zrx-help-cell-drag">
                ${isPinned ? '<img class="phrase-handle-pin" src="assets/images/pin.svg" alt="Pinned" class="zrx-help-pin-img">' : ''}
                <button type="button" class="pc-row-move-btn zrx-drag-handle" title="Move Row">${moveIcon}</button>
              </td>
              <td class="zrx-ta-c">
                <div class="phrase-actions" class="zrx-help-actions-flex">
                  <button type="button" class="phrase-btn ${isPinned ? 'active' : ''}" data-occ-action="pin" data-name="${name}" class="${isPinned ? 'zrx-help-pin-btn-active' : ''}">${isPinned ? (occUi.btn_unpin || 'Unpin') : (occUi.btn_pin || 'Pin')}</button>
                  <button type="button" class="phrase-btn" data-occ-action="edit" data-name="${name}">${occUi.btn_edit || 'Edit'}</button>
                  ${isSystem
                    ? (isHidden
                        ? `<button type="button" class="phrase-btn primary" data-occ-action="toggle_hide" data-name="${name}">${occUi.btn_restore || 'Restore'}</button>`
                        : `<button type="button" class="phrase-btn danger" data-occ-action="toggle_hide" data-name="${name}">${occUi.btn_remove || 'Remove'}</button>`)
                    : `<button type="button" class="phrase-btn danger" data-occ-action="delete" data-name="${name}">${occUi.btn_delete || 'Delete'}</button>`
                  }
                </div>
              </td>
              <td class="zrx-ta-c">
                <div class="phrase-tags" class="zrx-help-justify-center">
                  <span class="phrase-tag ${isSystem ? 'system' : (kind || 'custom')}">${isSystem ? (occUi.tag_system || 'System') : (kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : (occUi.tag_custom || 'Custom'))}</span>
                  ${isHidden ? `<span class="phrase-tag hidden" class="zrx-help-badge-danger">${occUi.tag_hidden || 'Hidden'}</span>` : ''}
                </div>
              </td>
              <td>
                <div class="phrase-text" class="${isHidden ? 'zrx-help-row-hidden' : 'zrx-help-row-visible'}">${name}</div>
              </td>
              <td class="phrase-usage" class="zrx-help-usage-val">
                ${Number(occ.usage_count || 0)}
              </td>
            </tr>
          `;
        }).join('');

        if (typeof window.refreshOccupations === 'function') {
          window.refreshOccupations();
        }

        // Bind Action Buttons
        tbody.querySelectorAll('[data-occ-action="pin"]').forEach(btn => {
          btn.onclick = () => {
            const name = btn.getAttribute('data-name');
            fetch('api/occupation_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'toggle_pin', name })
            }).then(() => loadOccupationTable());
          };
        });

        tbody.querySelectorAll('[data-occ-action="edit"]').forEach(btn => {
          btn.onclick = () => {
            const name = btn.getAttribute('data-name');
            const newName = prompt(occUi.prompt_edit || 'Edit occupation name:', name);
            if (newName && newName.trim() && newName.trim() !== name) {
              fetch('api/occupation_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'edit', name, new_name: newName.trim() })
              }).then(() => loadOccupationTable());
            }
          };
        });

        tbody.querySelectorAll('[data-occ-action="toggle_hide"]').forEach(btn => {
          btn.onclick = () => {
            const name = btn.getAttribute('data-name');
            fetch('api/occupation_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'toggle_hide', name })
            }).then(() => loadOccupationTable());
          };
        });

        tbody.querySelectorAll('[data-occ-action="delete"]').forEach(btn => {
          btn.onclick = () => {
            const name = btn.getAttribute('data-name');
            if (confirm(`Delete custom occupation "${name}"?`)) {
              fetch('api/occupation_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', name })
              }).then(() => loadOccupationTable());
            }
          };
        });

        // Drag and drop sorting
        const rows = tbody.querySelectorAll('tr[draggable="true"]');
        rows.forEach(row => {
          row.ondragstart = (e) => {
            draggingRow = row;
            row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
          };
          row.ondragend = () => {
            if (draggingRow) draggingRow.classList.remove('dragging');
            draggingRow = null;
            tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over', 'drag-over-bottom'));
          };
          row.ondragover = (e) => {
            e.preventDefault();
            if (!draggingRow || draggingRow === row) return;
            const rect = row.getBoundingClientRect();
            const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
            tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over', 'drag-over-bottom'));
            if (next) {
              row.classList.add('drag-over-bottom');
            } else {
              row.classList.add('drag-over');
            }
          };
          row.ondrop = (e) => {
            e.preventDefault();
            if (!draggingRow || draggingRow === row) return;
            const rect = row.getBoundingClientRect();
            const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
            row.classList.remove('drag-over', 'drag-over-bottom');
            if (next) {
              row.after(draggingRow);
            } else {
              row.before(draggingRow);
            }
            tbody.dispatchEvent(new CustomEvent('zrx:reordered'));
          };
        });

        if (tbody.onreorder) {
          tbody.removeEventListener('zrx:reordered', tbody.onreorder);
        }

        tbody.onreorder = () => {
          const names = Array.from(tbody.querySelectorAll('tr')).map(r => r.getAttribute('data-name')).filter(Boolean);
          fetch('api/occupation_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reorder', order: names })
          }).then(() => {
            if (typeof window.refreshOccupations === 'function') {
              window.refreshOccupations();
            }
          });
        };

        tbody.addEventListener('zrx:reordered', tbody.onreorder);
      })
      .catch(() => {
        tbody.innerHTML = '<tr><td colspan="5" class="zrx-empty-cell-err">Failed to load occupations.</td></tr>';
      });
  };

  loadOccupationTable();

  const addBtn = overlay.querySelector('#zrx-btn-add-occ');
  const addInput = overlay.querySelector('#zrx-new-occ-input');
  if (addBtn && addInput) {
    const doAdd = () => {
      const name = addInput.value.trim();
      if (!name) return;
      fetch('api/occupation_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', name })
      }).then(() => {
        addInput.value = '';
        loadOccupationTable();
      });
    };
    addBtn.onclick = doAdd;
    addInput.onkeydown = (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        doAdd();
      }
    };
  }

  const resetBtn = overlay.querySelector('#zrx-btn-reset-occ');
  if (resetBtn) {
    resetBtn.onclick = () => {
      if (confirm(occUi.confirm_reset || 'Reset all occupation customizations to default? This will unpin, unhide, and restore all system occupations.')) {
        fetch('api/occupation_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'reset' })
        }).then(() => loadOccupationTable());
      }
    };
  }
}

function initAddressManager(overlay, addrUi) {
  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  };

  let allDistricts = [];
  let selectedDistrictNames = [];
  let currentFilter = 'all';
  let currentSearchQ = '';
  let searchDebounceTimer = null;

  const loadAddressSettings = () => {
    const tbody = document.getElementById('zrx-addr-table-body');
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="4" class="zrx-empty-cell">${escapeHtml(addrUi.addr_loading || 'Loading addresses (Top 100)...')}</td></tr>`;
    }

    const params = new URLSearchParams({
      action: 'list',
      filter: currentFilter,
      q: currentSearchQ
    });

    fetch(`api/address_settings.php?${params.toString()}`)
      .then(res => res.json())
      .then(resData => {
        allDistricts = resData.all_districts || [];
        selectedDistrictNames = resData.selected_districts || [];

        renderDistrictChips();
        renderAddressTable(resData.addresses || []);

        if (typeof window.refreshAddressAutocomplete === 'function') {
          window.refreshAddressAutocomplete();
        }
      })
      .catch(() => {
        if (tbody) {
          tbody.innerHTML = '<tr><td colspan="4" class="zrx-empty-cell-err">Failed to load addresses.</td></tr>';
        }
      });
  };

  const renderDistrictChips = () => {
    const container = overlay.querySelector('#zrx-district-chips-container');
    const filterInput = overlay.querySelector('#zrx-district-filter-input');
    if (!container) return;

    const filterVal = (filterInput ? filterInput.value.trim().toLowerCase() : '');

    const filtered = allDistricts.filter(d => {
      if (!filterVal) return true;
      return (d.name_en && d.name_en.toLowerCase().includes(filterVal)) ||
             (d.name_bn && d.name_bn.includes(filterVal));
    });

    if (!filtered.length) {
      container.innerHTML = '<span class="zrx-help-text-muted">No matching districts.</span>';
      return;
    }

    container.innerHTML = filtered.map(d => {
      const isSel = selectedDistrictNames.includes(d.name_en);
      return `
        <span class="zrx-district-chip ${isSel ? 'selected' : ''}" data-name="${escapeHtml(d.name_en)}" title="${escapeHtml(d.name_bn || '')}">
          ${escapeHtml(d.name_en)}
          ${d.name_bn ? `<small class="zrx-help-text-dim">(${escapeHtml(d.name_bn)})</small>` : ''}
        </span>
      `;
    }).join('');

    container.querySelectorAll('.zrx-district-chip').forEach(chip => {
      chip.onclick = () => {
        const name = chip.getAttribute('data-name');
        if (selectedDistrictNames.includes(name)) {
          selectedDistrictNames = selectedDistrictNames.filter(n => n !== name);
          chip.classList.remove('selected');
        } else {
          selectedDistrictNames.push(name);
          chip.classList.add('selected');
        }
      };
    });
  };

  const renderAddressTable = (list) => {
    const tbody = overlay.querySelector('#zrx-addr-table-body');
    if (!tbody) return;

    if (!list.length) {
      tbody.innerHTML = `<tr><td colspan="4" class="zrx-empty-cell">${escapeHtml(addrUi.addr_empty || 'No addresses found.')}</td></tr>`;
      return;
    }

    tbody.innerHTML = list.map(addr => {
      const isPinned = Number(addr.is_pinned) === 1;
      const isHidden = Number(addr.is_hidden) === 1;
      const kind = escapeHtml(String(addr.kind ?? ''));
      const text = escapeHtml(String(addr.address_text ?? ''));
      const isSystem = addr.kind === 'system';

      return `
        <tr class="pc-row phrase-row ${isPinned ? 'pinned' : ''} ${isHidden ? 'hidden' : ''}" data-id="${addr.id}" data-text="${text}">
          <td class="zrx-ta-c">
            <div class="phrase-actions" class="zrx-help-actions-flex">
              <button type="button" class="phrase-btn ${isPinned ? 'active' : ''}" data-addr-action="pin" data-id="${addr.id}" class="${isPinned ? 'zrx-help-pin-btn-active' : ''}">${isPinned ? 'Unpin' : 'Pin'}</button>
              ${!isSystem ? `<button type="button" class="phrase-btn" data-addr-action="edit" data-id="${addr.id}" data-text="${text}">Edit</button>` : ''}
              ${isSystem
                ? (isHidden
                    ? `<button type="button" class="phrase-btn primary" data-addr-action="toggle_hide" data-id="${addr.id}">Restore</button>`
                    : `<button type="button" class="phrase-btn danger" data-addr-action="toggle_hide" data-id="${addr.id}">Remove</button>`)
                : `<button type="button" class="phrase-btn danger" data-addr-action="delete" data-id="${addr.id}">Delete</button>`
              }
            </div>
          </td>
          <td class="zrx-ta-c">
            <div class="phrase-tags" class="zrx-help-justify-center">
              <span class="phrase-tag ${isSystem ? 'system' : (kind || 'custom')}">${isSystem ? 'System' : (kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : 'Custom')}</span>
              ${isHidden ? '<span class="phrase-tag hidden" class="zrx-help-badge-danger">Hidden</span>' : ''}
            </div>
          </td>
          <td>
            <div class="phrase-text" class="${isHidden ? 'zrx-help-row-hidden' : 'zrx-help-row-visible'}">
              ${isPinned ? '<img src="assets/images/pin.svg" alt="Pinned" class="zrx-help-pin-icon-inline">' : ''}
              ${text}
            </div>
          </td>
          <td class="phrase-usage" class="zrx-help-usage-val">
            ${Number(addr.usage_count || 0)}
          </td>
        </tr>
      `;
    }).join('');

    tbody.querySelectorAll('[data-addr-action="pin"]').forEach(btn => {
      btn.onclick = () => {
        const id = btn.getAttribute('data-id');
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'toggle_pin', id })
        }).then(() => loadAddressSettings());
      };
    });

    tbody.querySelectorAll('[data-addr-action="edit"]').forEach(btn => {
      btn.onclick = () => {
        const id = btn.getAttribute('data-id');
        const text = btn.getAttribute('data-text');
        const newText = prompt(addrUi.prompt_edit_addr || 'Edit address name:', text);
        if (newText && newText.trim() && newText.trim() !== text) {
          fetch('api/address_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'edit', id, new_address_text: newText.trim() })
          }).then(() => loadAddressSettings());
        }
      };
    });

    tbody.querySelectorAll('[data-addr-action="toggle_hide"]').forEach(btn => {
      btn.onclick = () => {
        const id = btn.getAttribute('data-id');
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'toggle_hide', id })
        }).then(() => loadAddressSettings());
      };
    });

    tbody.querySelectorAll('[data-addr-action="delete"]').forEach(btn => {
      btn.onclick = () => {
        const id = btn.getAttribute('data-id');
        if (confirm('Delete this custom address?')) {
          fetch('api/address_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id })
          }).then(() => loadAddressSettings());
        }
      };
    });
  };

  const distSearchInput = overlay.querySelector('#zrx-district-filter-input');
  if (distSearchInput) {
    distSearchInput.oninput = () => renderDistrictChips();
  }

  const distAllBtn = overlay.querySelector('#zrx-btn-dist-all');
  if (distAllBtn) {
    distAllBtn.onclick = () => {
      selectedDistrictNames = [];
      renderDistrictChips();
    };
  }

  const distSaveBtn = overlay.querySelector('#zrx-btn-dist-save');
  if (distSaveBtn) {
    distSaveBtn.onclick = () => {
      distSaveBtn.disabled = true;
      fetch('api/address_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_districts', districts: selectedDistrictNames })
      })
      .then(res => res.json())
      .then(data => {
        distSaveBtn.disabled = false;
        if (data.ok) {
          distSaveBtn.textContent = addrUi.btn_dist_saved || 'Saved ✓';
          setTimeout(() => { distSaveBtn.textContent = addrUi.btn_dist_save || 'Save Districts'; }, 2000);
        }
      })
      .catch(() => {
        distSaveBtn.disabled = false;
        distSaveBtn.textContent = addrUi.btn_dist_save || 'Save Districts';
      });
    };
  }

  const addrSearchInput = overlay.querySelector('#zrx-addr-search-input');
  if (addrSearchInput) {
    addrSearchInput.oninput = () => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        currentSearchQ = addrSearchInput.value.trim();
        loadAddressSettings();
      }, 250);
    };
  }

  const filterTabs = overlay.querySelectorAll('.zrx-addr-tab-btn');
  filterTabs.forEach(tab => {
    tab.onclick = () => {
      filterTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentFilter = tab.getAttribute('data-addr-filter') || 'all';
      loadAddressSettings();
    };
  });

  const addAddrBtn = overlay.querySelector('#zrx-btn-add-addr');
  const addAddrInput = overlay.querySelector('#zrx-new-addr-input');
  if (addAddrBtn && addAddrInput) {
    const doAdd = () => {
      const address_text = addAddrInput.value.trim();
      if (!address_text) return;
      fetch('api/address_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', address_text })
      }).then(() => {
        addAddrInput.value = '';
        loadAddressSettings();
      });
    };
    addAddrBtn.onclick = doAdd;
    addAddrInput.onkeydown = (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        doAdd();
      }
    };
  }

  const resetAddrBtn = overlay.querySelector('#zrx-btn-reset-addr');
  if (resetAddrBtn) {
    resetAddrBtn.onclick = () => {
      if (confirm(addrUi.confirm_reset_addr || 'Reset all address customizations and district preferences to default?')) {
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'reset' })
        }).then(() => {
          allDistricts = [];
          selectedDistrictNames = [];
          loadAddressSettings();
        });
      }
    };
  }

  loadAddressSettings();
}

function initializeHelpGuidelineModals() {
  const handleHelpTrigger = (e) => {
    const btn = e.target?.closest?.('.zrx-help-icon-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    const type = btn.getAttribute('data-help-type');
    if (type) {
      showHelpGuidelineModal(type);
    }
  };

  document.addEventListener('click', handleHelpTrigger, true);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      const btn = e.target?.closest?.('.zrx-help-icon-btn');
      if (btn) {
        handleHelpTrigger(e);
      }
    }
  }, true);
}
