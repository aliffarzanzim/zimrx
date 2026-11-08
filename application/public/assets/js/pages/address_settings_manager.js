function initAddressSettings(overlay, addrUi) {
  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  };

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
      if (confirm(addrUi.confirm_reset_addr || 'Reset address customizations to default?')) {
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'reset' })
        }).then(() => {
          loadAddressSettings();
        });
      }
    };
  }

  loadAddressSettings();
}

window.ZimRxAddressSettings = { initManager: initAddressSettings };
