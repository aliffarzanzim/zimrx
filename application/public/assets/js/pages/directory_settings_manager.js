// Address and occupation directory settings controllers.

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
          const moveIcon = ZimRxIcon.render('move', 14);

          return `
            <tr class="pc-row phrase-row ${isPinned ? 'pinned' : ''} ${isHidden ? 'hidden' : ''}" data-name="${name}" draggable="true">
              <td class="pc-action pc-move phrase-handle zrx-help-cell-drag">
                ${isPinned ? '<img class="phrase-handle-pin zrx-help-pin-img" src="assets/images/pin.svg" alt="Pinned">' : ''}
                <button type="button" class="pc-row-move-btn zrx-drag-handle" title="Move Row">${moveIcon}</button>
              </td>
              <td class="zrx-ta-c">
                <div class="phrase-actions zrx-help-actions-flex">
                  <button type="button" class="phrase-btn ${isPinned ? 'active zrx-help-pin-btn-active' : ''}" data-occ-action="pin" data-name="${name}">${isPinned ? (occUi.btn_unpin || 'Unpin') : (occUi.btn_pin || 'Pin')}</button>
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
                <div class="phrase-tags zrx-help-justify-center">
                  <span class="phrase-tag ${isSystem ? 'system' : (kind || 'custom')}">${isSystem ? (occUi.tag_system || 'System') : (kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : (occUi.tag_custom || 'Custom'))}</span>
                  ${isHidden ? `<span class="phrase-tag hidden zrx-help-badge-danger">${occUi.tag_hidden || 'Hidden'}</span>` : ''}
                </div>
              </td>
              <td>
                <div class="phrase-text ${isHidden ? 'zrx-help-row-hidden' : 'zrx-help-row-visible'}">${name}</div>
              </td>
              <td class="phrase-usage zrx-help-usage-val">
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
            body: JSON.stringify({ action: 'reorder', names })
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
        allDistricts = resData.districts || [];
        selectedDistrictNames = resData.preferred_districts || [];

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
      return (d.name && d.name.toLowerCase().includes(filterVal)) ||
             (d.bn_name && d.bn_name.includes(filterVal));
    });

    if (!filtered.length) {
      container.innerHTML = '<span class="zrx-help-text-muted">No matching districts.</span>';
      return;
    }

    container.innerHTML = filtered.map(d => {
      const isSel = selectedDistrictNames.length === 0 || selectedDistrictNames.includes(d.name);
      return `
        <span class="zrx-district-chip ${isSel ? 'selected' : ''}" data-name="${escapeHtml(d.name)}" title="${escapeHtml(d.bn_name || '')}">
          ${escapeHtml(d.name)}
          ${d.bn_name ? `<small class="zrx-help-text-dim">(${escapeHtml(d.bn_name)})</small>` : ''}
        </span>
      `;
    }).join('');

    container.querySelectorAll('.zrx-district-chip').forEach(chip => {
      chip.onclick = () => {
        const name = chip.getAttribute('data-name');
        if (selectedDistrictNames.length === 0) {
          selectedDistrictNames = allDistricts.map(d => d.name).filter(item => item !== name);
          chip.classList.remove('selected');
        } else if (selectedDistrictNames.includes(name)) {
          selectedDistrictNames = selectedDistrictNames.filter(n => n !== name);
          chip.classList.remove('selected');
        } else {
          selectedDistrictNames.push(name);
          chip.classList.add('selected');
        }
        if (allDistricts.length > 0 && selectedDistrictNames.length === allDistricts.length) {
          selectedDistrictNames = [];
          renderDistrictChips();
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
      const text = escapeHtml(String(addr.name ?? ''));
      const isSystem = addr.kind === 'system';

      return `
        <tr class="pc-row phrase-row ${isPinned ? 'pinned' : ''} ${isHidden ? 'hidden' : ''}" data-name="${text}">
          <td class="zrx-ta-c">
            <div class="phrase-actions zrx-help-actions-flex">
              ${!isSystem ? `<button type="button" class="phrase-btn ${isPinned ? 'active zrx-help-pin-btn-active' : ''}" data-addr-action="pin" data-name="${text}">${isPinned ? 'Unpin' : 'Pin'}</button>` : ''}
              ${!isSystem ? `<button type="button" class="phrase-btn" data-addr-action="edit" data-name="${text}">Edit</button>` : ''}
              ${isSystem
                ? ''
                : `<button type="button" class="phrase-btn danger" data-addr-action="delete" data-name="${text}">Delete</button>`
              }
            </div>
          </td>
          <td class="zrx-ta-c">
            <div class="phrase-tags zrx-help-justify-center">
              <span class="phrase-tag ${isSystem ? 'system' : (kind || 'custom')}">${isSystem ? 'System' : (kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : 'Custom')}</span>
              ${isHidden ? '<span class="phrase-tag hidden zrx-help-badge-danger">Hidden</span>' : ''}
            </div>
          </td>
          <td>
            <div class="phrase-text ${isHidden ? 'zrx-help-row-hidden' : 'zrx-help-row-visible'}">
              ${isPinned ? '<img src="assets/images/pin.svg" alt="Pinned" class="zrx-help-pin-icon-inline">' : ''}
              ${text}
            </div>
          </td>
          <td class="phrase-usage zrx-help-usage-val">
            ${Number(addr.usage_count || 0)}
          </td>
        </tr>
      `;
    }).join('');

    tbody.querySelectorAll('[data-addr-action="pin"]').forEach(btn => {
      btn.onclick = () => {
        const name = btn.getAttribute('data-name');
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'toggle_pin', name })
        }).then(() => loadAddressSettings());
      };
    });

    tbody.querySelectorAll('[data-addr-action="edit"]').forEach(btn => {
      btn.onclick = () => {
        const name = btn.getAttribute('data-name');
        const text = name;
        const newText = prompt(addrUi.prompt_edit_addr || 'Edit address name:', text);
        if (newText && newText.trim() && newText.trim() !== text) {
          fetch('api/address_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'edit', name, new_name: newText.trim() })
          }).then(() => loadAddressSettings());
        }
      };
    });

    tbody.querySelectorAll('[data-addr-action="toggle_hide"]').forEach(btn => {
      btn.onclick = () => {
        const name = btn.getAttribute('data-name');
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'toggle_hide', name })
        }).then(() => loadAddressSettings());
      };
    });

    tbody.querySelectorAll('[data-addr-action="delete"]').forEach(btn => {
      btn.onclick = () => {
        const name = btn.getAttribute('data-name');
        if (confirm('Delete this custom address?')) {
          fetch('api/address_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', name })
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
      const name = addAddrInput.value.trim();
      if (!name) return;
      fetch('api/address_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', name })
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

window.ZimRxDirectorySettings = { initOccupationManager, initAddressManager };
