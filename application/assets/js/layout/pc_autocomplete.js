function initPcAutocomplete() {
  let dropdown = null;
  let focusIndex = -1;
  let timeout = null;
  let activeInput = null;
  let settingsData = null;
  let priorityDraft = [];
  let hideSearchTimer = null;
  let hideSearchRequestId = 0;
  let loadRequestId = 0;
  let dragRow = null;

  const fieldSelector = '.pc-complaint-input, .pc-duration-input, .pc-unit-input';
  const getPcRoot = () => document.querySelector('.pc-wrapper:not([id])');
  const getPcTableBody = () => getPcRoot()?.querySelector('.pc-table tbody');
  const isInPcRoot = (element) => Boolean(element && getPcRoot()?.contains(element));

  const getFieldConfig = (input) => {
    if (input.matches('.pc-duration-input')) {
      return { field: 'duration', nextSelector: '.pc-unit-input' };
    }
    if (input.matches('.pc-unit-input')) {
      return { field: 'unit', nextSelector: null };
    }
    return { field: 'complaint', nextSelector: '.pc-duration-input' };
  };

  const getSettingsModal = () => document.getElementById('pc-settings-modal');

  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  };

  const renumberPcRows = () => {
    getPcTableBody()?.querySelectorAll('.pc-row').forEach((row, index) => {
      const numberCell = row.querySelector('.pc-row-no');
      if (numberCell) {
        numberCell.textContent = String(index + 1);
      }
    });
  };

  const clearPcRow = (row) => {
    row?.querySelectorAll('.pc-input').forEach((input) => {
      input.value = '';
      delete input.dataset.pcSource;
      input.style.height = '36px';
    });
  };

  const createPcRow = () => {
    const template = document.getElementById('pc-row-template');
    const row = template?.content?.firstElementChild?.cloneNode(true);
    if (!row) {
      return null;
    }
    clearPcRow(row);
    return row;
  };

  const addPcRow = (focusComplaint = true) => {
    const tbody = getPcTableBody();
    if (!tbody) {
      return null;
    }
    const row = createPcRow();
    if (!row) {
      return null;
    }
    tbody.appendChild(row);
    renumberPcRows();
    if (focusComplaint) {
      row.querySelector('.pc-complaint-input')?.focus();
    }
    return row;
  };

  const ensureAtLeastOnePcRow = () => {
    const tbody = getPcTableBody();
    if (!tbody) {
      return;
    }
    if (!tbody.querySelector('.pc-row')) {
      addPcRow(false);
    } else {
      renumberPcRows();
    }
  };

  const removePcRow = (row) => {
    const tbody = getPcTableBody();
    if (!tbody || !row) {
      return;
    }

    if (activeInput && row.contains(activeInput)) {
      close();
    }
    row.remove();
    renumberPcRows();
  };

  const clearDropTargets = () => {
    getPcTableBody()?.querySelectorAll('.pc-row.drop-target').forEach((row) => {
      row.classList.remove('drop-target');
    });
  };

  const autoResizePcInput = (input) => {
    if (!input || !input.matches('.pc-complaint-input')) return;
    // Disable transition temporarily so the '0' reset doesn't animate visually
    // and scrollHeight is measured against actual content, not an animating height.
    input.style.transition = 'none';
    input.style.height = '0';
    const natural = Math.max(36, input.scrollHeight);
    input.style.height = natural + 'px';
    // Re-enable transition on next paint so any further changes animate smoothly
    requestAnimationFrame(() => { input.style.transition = ''; });
  };

  const close = () => {
    if (dropdown) {
      dropdown.remove();
    }
    dropdown = null;
    focusIndex = -1;
    activeInput = null;
  };

  const position = (input, list) => {
    const anchor = input?.closest('td') || input;
    const rect = anchor.getBoundingClientRect();
    list.style.position = 'absolute';
    list.style.top = `${rect.bottom + window.scrollY}px`;
    list.style.left = `${rect.left + window.scrollX}px`;
    list.style.minWidth = `${rect.width}px`;
    list.style.zIndex = '9999';
  };

  const getDurationCardinality = (input) => {
    const row = input.closest('tr');
    const durationInput = row?.querySelector('.pc-duration-input');
    const duration = String(durationInput?.value || '').trim();
    if (!duration) {
      return '';
    }
    const numeric = Number(duration.replace(/[^\d.]/g, ''));
    return Number.isFinite(numeric) && numeric === 1 ? 'singular' : 'plural';
  };

  const singularUnits = new Set([
    'hour', 'day', 'week', 'month', 'year', 'minute', 'second', 'episode', 'time', 'attack', 'session', 'dose', 'occasion'
  ]);
  const pluralUnits = new Set([
    'hours', 'days', 'weeks', 'months', 'years', 'minutes', 'seconds', 'episodes', 'times', 'attacks', 'sessions', 'doses', 'occasions'
  ]);

  const singularToPlural = {
    'hour': 'Hours',
    'day': 'Days',
    'week': 'Weeks',
    'month': 'Months',
    'year': 'Years',
    'minute': 'Minutes',
    'second': 'Seconds',
    'episode': 'Episodes',
    'time': 'Times',
    'attack': 'Attacks',
    'session': 'Sessions',
    'dose': 'Doses',
    'occasion': 'Occasions',
  };

  const pluralToSingular = {
    'hours': 'Hour',
    'days': 'Day',
    'weeks': 'Week',
    'months': 'Month',
    'years': 'Year',
    'minutes': 'Minute',
    'seconds': 'Second',
    'episodes': 'Episode',
    'times': 'Time',
    'attacks': 'Attack',
    'sessions': 'Session',
    'doses': 'Dose',
    'occasions': 'Occasion',
  };

  const autoAdjustUnitForDuration = (durationInput) => {
    const row = durationInput.closest('tr');
    const unitInput = row?.querySelector('.pc-unit-input');
    if (!unitInput || !unitInput.value.trim()) return;

    const val = unitInput.value.trim();
    const lower = val.toLowerCase();
    const duration = durationInput.value.trim();
    if (!duration) return;

    const numeric = Number(duration.replace(/[^\d.]/g, ''));
    if (!Number.isFinite(numeric)) return;

    if (numeric === 1 && pluralToSingular[lower]) {
      unitInput.value = pluralToSingular[lower];
    } else if (numeric > 1 && singularToPlural[lower]) {
      unitInput.value = singularToPlural[lower];
    }
  };

  const filterUnitItemsForDuration = (input, items) => {
    if (!input.matches('.pc-unit-input') || !Array.isArray(items)) {
      return items;
    }

    const cardinality = getDurationCardinality(input);
    if (!cardinality) {
      return items;
    }

    return items.filter((item) => {
      const value = String(item.value || item.label || '').trim().toLowerCase();
      if (cardinality === 'singular') {
        return !pluralUnits.has(value);
      }
      return !singularUnits.has(value);
    });
  };

  const render = (input, items) => {
    close();
    items = filterUnitItemsForDuration(input, items);
    if (!Array.isArray(items) || !items.length || items.error) return;

    const config = getFieldConfig(input);
    const ul = document.createElement('ul');
    ul.className = 'zrx-dropdown rx-dropdown show';
    items.forEach((item, index) => {
      const li = document.createElement('li');
      li.className = 'zrx-dropdown-item rx-dropdown-item';
      if (index === 0) li.classList.add('active');
      li.innerHTML = `<div style="padding:2px 0; width:100%;"><strong>${escapeHtml(item.label)}</strong></div>`;
      
      li.addEventListener('mouseenter', () => {
        const allItems = ul.querySelectorAll('.zrx-dropdown-item, .rx-dropdown-item');
        allItems.forEach((it) => it.classList.remove('active'));
        li.classList.add('active');
        focusIndex = Array.from(allItems).indexOf(li);
      });

      li.addEventListener('mousedown', (event) => {
        event.preventDefault();
        input.value = item.value || item.label;
        input.dataset.pcSource = item.source || '';
        autoResizePcInput(input);
        close();
        if (config.nextSelector) {
          const nextField = input.closest('tr')?.querySelector(config.nextSelector);
          if (nextField) nextField.focus();
        }
      });
      ul.appendChild(li);
    });

    dropdown = ul;
    activeInput = input;
    document.body.appendChild(ul);
    position(input, ul);
    focusIndex = 0;
  };

  // Cache for all PC suggestion results
  const suggestCache = {};

  const load = (input) => {
    clearTimeout(timeout);
    close(); // Immediately remove old dropdown
    activeInput = input;
    const term = input.value.trim();
    const config = getFieldConfig(input);
    const reqId = ++loadRequestId;
    const cacheKey = `${config.field}::${term.toLowerCase()}`;

    // Use cached results if available (instant render)
    if (suggestCache[cacheKey]) {
      render(input, suggestCache[cacheKey]);
      return;
    }

    fetch(`api/pc_suggestions.php?field=${encodeURIComponent(config.field)}&term=${encodeURIComponent(term)}`)
      .then((res) => res.json())
      .then((data) => {
        suggestCache[cacheKey] = data;
        if (reqId === loadRequestId && activeInput === input) {
          render(input, data);
        }
      })
      .catch(() => {
        if (reqId === loadRequestId) close();
      });
  };

  const renderPriorityTable = () => {
    const modal = getSettingsModal();
    const tbody = modal?.querySelector('.pc-priority-body');
    if (!tbody) return;

    if (!Array.isArray(priorityDraft) || !priorityDraft.length) {
      tbody.innerHTML = '';
      return;
    }

    tbody.innerHTML = priorityDraft.map((row, index) => `
      <tr data-source="${escapeHtml(row.source)}">
        <td><input type="checkbox" class="pc-priority-checkbox" data-priority-toggle="${escapeHtml(row.source)}" ${row.is_enabled ? 'checked' : ''}></td>
        <td><span class="pc-priority-row-label">${escapeHtml(row.label)}</span></td>
        <td>
          <div class="pc-priority-order">
            <span class="pc-priority-badge">${index + 1}</span>
            <button type="button" class="pc-priority-move" data-priority-move="up" data-source="${escapeHtml(row.source)}" ${index === 0 ? 'disabled' : ''}>&uarr;</button>
            <button type="button" class="pc-priority-move" data-priority-move="down" data-source="${escapeHtml(row.source)}" ${index === priorityDraft.length - 1 ? 'disabled' : ''}>&darr;</button>
          </div>
        </td>
      </tr>
    `).join('');
  };

  const renderUsedGroups = () => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-used-groups');
    if (!container) return;

    const groups = settingsData?.used_groups || [];
    if (!groups.length) {
      container.innerHTML = '<div class="pc-settings-empty">No used PC has been learned for this doctor yet.</div>';
      return;
    }

    container.innerHTML = groups.map((group) => {
      const items = Array.isArray(group.items) ? group.items : [];
      const itemMarkup = items.length
        ? `<div class="pc-used-list">${items.map((item) => `
            <div class="pc-used-item">
              <div class="pc-used-term">${escapeHtml(item.term)}</div>
              <div class="pc-used-meta">
                ${item.is_hidden ? '<span class="pc-tag hidden">Hidden</span>' : ''}
                <span class="pc-used-usage">${Number(item.usage_count || 0)}</span>
              </div>
            </div>
          `).join('')}</div>`
        : '<div class="pc-settings-empty">No used PC mapped into this source yet.</div>';

      return `
        <div class="pc-used-group">
          <div class="pc-used-group-header">
            <div class="pc-used-group-title">
              <strong>${escapeHtml(group.label)}</strong>
              ${group.is_enabled ? '' : '<span class="pc-tag hidden">Disabled</span>'}
            </div>
            <span class="pc-used-count">${items.length} item${items.length === 1 ? '' : 's'}</span>
          </div>
          ${itemMarkup}
        </div>
      `;
    }).join('');
  };

  const renderCustomList = () => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-custom-list');
    if (!container) return;

    const items = settingsData?.custom_terms || [];
    if (!items.length) {
      container.innerHTML = '<div class="pc-settings-empty">No custom PC has been added for this doctor yet.</div>';
      return;
    }

    container.innerHTML = items.map((item) => `
      <div class="pc-custom-item">
        <div class="pc-custom-term">${escapeHtml(item.term)}</div>
        <button type="button" class="pc-custom-remove" data-remove-custom="${escapeHtml(item.term)}" title="Remove Custom PC">&times;</button>
      </div>
    `).join('');
  };

  const renderHideResults = (results) => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-hide-search-results');
    if (!container) return;

    if (!Array.isArray(results) || !results.length) {
      container.innerHTML = '<div class="pc-settings-empty">Search a complaint to hide or unhide it for this doctor.</div>';
      return;
    }

    container.innerHTML = results.map((item) => `
      <div class="pc-search-result">
        <div class="pc-search-result-main">
          <div class="pc-search-result-term">${escapeHtml(item.term)}</div>
          <div class="pc-search-result-meta">
            <span class="pc-tag">${escapeHtml(item.source_label)}</span>
            ${typeof item.usage_count === 'number' ? `<span>Used ${item.usage_count} time${item.usage_count === 1 ? '' : 's'}</span>` : ''}
            ${item.is_hidden ? '<span class="pc-tag hidden">Hidden</span>' : ''}
          </div>
        </div>
        <button type="button" class="pc-search-toggle" data-toggle-hidden="${escapeHtml(item.source)}" data-term="${escapeHtml(item.term)}" data-hidden="${item.is_hidden ? '1' : '0'}">
          ${item.is_hidden ? 'Unhide' : 'Hide'}
        </button>
      </div>
    `).join('');
  };

  const refreshSettingsUi = () => {
    renderPriorityTable();
    renderUsedGroups();
    renderCustomList();
  };

  const applySettingsData = (data) => {
    settingsData = data;
    priorityDraft = Array.isArray(data?.priorities)
      ? data.priorities.map((row) => ({ ...row }))
      : [];
    refreshSettingsUi();

    const modal = getSettingsModal();
    const query = modal?.querySelector('.pc-hide-search-input')?.value.trim() || '';
    if (query) {
      performHideSearch(query);
    } else {
      renderHideResults([]);
    }
  };

  const applyInitialSettingsData = () => {
    if (settingsData) {
      return true;
    }

    const node = document.getElementById('zimrxInitialPcSettings');
    if (!node?.textContent?.trim()) {
      return false;
    }

    try {
      const data = JSON.parse(node.textContent);
      if (!data || data.error) {
        return false;
      }
      applySettingsData(data);
      return true;
    } catch (error) {
      return false;
    }
  };

  const postSettings = async (payload) => {
    const response = await fetch('api/pc_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return response.json();
  };

  const loadSettingsData = async () => {
    const modal = getSettingsModal();
    if (!modal) return;

    const response = await fetch('api/pc_settings.php');
    const data = await response.json();
    if (data.error) {
      throw new Error(data.error);
    }

    applySettingsData(data);
  };

  const openSettings = async () => {
    const modal = getSettingsModal();
    if (!modal) return;
    close();
    const hasInitialData = applyInitialSettingsData();
    modal.hidden = false;
    document.body.style.overflow = 'hidden';

    if (hasInitialData) {
      loadSettingsData().catch(() => {});
      modal.querySelector('.pc-custom-input')?.focus();
      return;
    }

    try {
      await loadSettingsData();
      modal.querySelector('.pc-custom-input')?.focus();
    } catch (error) {
      alert(error.message || 'Could not load P/C settings.');
    }
  };

  const closeSettings = () => {
    const modal = getSettingsModal();
    if (!modal) return;
    modal.hidden = true;
    document.body.style.overflow = '';
  };

  const movePriority = (source, direction) => {
    const index = priorityDraft.findIndex((row) => row.source === source);
    if (index < 0) return;
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= priorityDraft.length) return;
    const [row] = priorityDraft.splice(index, 1);
    priorityDraft.splice(targetIndex, 0, row);
    renderPriorityTable();
  };

  const savePriorityDraft = async () => {
    const data = await postSettings({
      action: 'save_priorities',
      priorities: priorityDraft.map((row) => ({
        source: row.source,
        is_enabled: row.is_enabled ? 1 : 0,
      })),
    });
    if (data.error) {
      throw new Error(data.error);
    }
    settingsData = data;
    priorityDraft = Array.isArray(data.priorities)
      ? data.priorities.map((row) => ({ ...row }))
      : [];
    refreshSettingsUi();

    const query = getSettingsModal()?.querySelector('.pc-hide-search-input')?.value.trim() || '';
    if (query) {
      performHideSearch(query);
    }
  };

  const performHideSearch = (query) => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-hide-search-results');
    if (!container) return;

    const value = String(query || '').trim();
    if (!value) {
      renderHideResults([]);
      return;
    }

    const requestId = ++hideSearchRequestId;
    container.innerHTML = '<div class="pc-settings-empty">Searching...</div>';

    fetch(`api/pc_settings.php?action=search&q=${encodeURIComponent(value)}`)
      .then((res) => res.json())
      .then((data) => {
        if (requestId !== hideSearchRequestId) return;
        renderHideResults(data.results || []);
      })
      .catch(() => {
        if (requestId !== hideSearchRequestId) return;
        container.innerHTML = '<div class="pc-settings-empty">Could not search right now.</div>';
      });
  };

  document.addEventListener('input', (event) => {
    if (event.target.matches(fieldSelector) && isInPcRoot(event.target)) {
      if (event.target.matches('.pc-duration-input')) {
        autoAdjustUnitForDuration(event.target);
      }
      autoResizePcInput(event.target);
      load(event.target);
      return;
    }

    if (event.target.matches('.pc-hide-search-input')) {
      clearTimeout(hideSearchTimer);
      hideSearchTimer = setTimeout(() => performHideSearch(event.target.value), 180);
    }
  });

  document.addEventListener('focus', (event) => {
    if (event.target.matches(fieldSelector) && isInPcRoot(event.target)) load(event.target);
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.target.matches('.pc-custom-input') && event.key === 'Enter') {
      event.preventDefault();
      event.target.closest('.pc-settings-card')?.querySelector('.pc-custom-add-btn')?.click();
      return;
    }

    if (event.key === 'Escape' && !getSettingsModal()?.hidden) {
      close();
      closeSettings();
      return;
    }

    if (!dropdown || !event.target.matches(fieldSelector) || !isInPcRoot(event.target)) return;
    const items = dropdown.querySelectorAll('.rx-dropdown-item');
    if (!items.length) return;

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      items[focusIndex]?.classList.remove('active');
      focusIndex = (focusIndex + 1) % items.length;
      items[focusIndex].classList.add('active');
      items[focusIndex].scrollIntoView({ block: 'nearest' });
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      items[focusIndex]?.classList.remove('active');
      focusIndex = (focusIndex - 1 + items.length) % items.length;
      items[focusIndex].classList.add('active');
      items[focusIndex].scrollIntoView({ block: 'nearest' });
    } else if (event.key === 'Enter' && focusIndex > -1) {
      event.preventDefault();
      items[focusIndex].dispatchEvent(new MouseEvent('mousedown'));
      autoResizePcInput(event.target);
    } else if (event.key === 'Escape') {
      close();
      if (!getSettingsModal()?.hidden) {
        closeSettings();
      }
    }
  });

  document.addEventListener('click', async (event) => {
    const settingsButton = event.target.closest('.pc-settings-btn');
    if (settingsButton) {
      event.preventDefault();
      openSettings();
      return;
    }

    if (event.target.closest('[data-pc-settings-close]')) {
      event.preventDefault();
      closeSettings();
      return;
    }

    const clearButton = event.target.closest('.pc-del button');
    if (clearButton && isInPcRoot(clearButton)) {
      event.preventDefault();
      removePcRow(clearButton.closest('.pc-row'));
      return;
    }

    const addRowButton = event.target.closest('.pc-add-row-btn');
    // Skip Physical Examination add-more buttons — they are handled by p_e.php's own scoped script
    if (addRowButton && isInPcRoot(addRowButton)) {
      event.preventDefault();
      addPcRow(false);
      return;
    }

    const moveButton = event.target.closest('[data-priority-move]');
    if (moveButton) {
      event.preventDefault();
      movePriority(moveButton.dataset.source, moveButton.dataset.priorityMove);
      return;
    }

    const saveButton = event.target.closest('.pc-settings-save-btn');
    if (saveButton) {
      event.preventDefault();
      try {
        await savePriorityDraft();
      } catch (error) {
        alert(error.message || 'Could not save priority settings.');
      }
      return;
    }

    const addCustomButton = event.target.closest('.pc-custom-add-btn');
    if (addCustomButton) {
      event.preventDefault();
      const modal = getSettingsModal();
      const input = modal?.querySelector('.pc-custom-input');
      const term = input?.value.trim() || '';
      if (!term) {
        input?.focus();
        return;
      }
      try {
        const data = await postSettings({ action: 'add_custom', term });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        priorityDraft = Array.isArray(data.priorities)
          ? data.priorities.map((row) => ({ ...row }))
          : priorityDraft;
        refreshSettingsUi();
        input.value = '';
        input.focus();
        const query = modal?.querySelector('.pc-hide-search-input')?.value.trim() || '';
        if (query) performHideSearch(query);
      } catch (error) {
        alert(error.message || 'Could not add custom PC.');
      }
      return;
    }

    const removeCustomButton = event.target.closest('[data-remove-custom]');
    if (removeCustomButton) {
      event.preventDefault();
      try {
        const data = await postSettings({
          action: 'remove_custom',
          term: removeCustomButton.dataset.removeCustom || '',
        });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        priorityDraft = Array.isArray(data.priorities)
          ? data.priorities.map((row) => ({ ...row }))
          : priorityDraft;
        refreshSettingsUi();
      } catch (error) {
        alert(error.message || 'Could not remove custom PC.');
      }
      return;
    }

    const hiddenToggle = event.target.closest('[data-toggle-hidden]');
    if (hiddenToggle) {
      event.preventDefault();
      const modal = getSettingsModal();
      const query = modal?.querySelector('.pc-hide-search-input')?.value.trim() || '';
      const nextHidden = hiddenToggle.dataset.hidden !== '1';
      try {
        const data = await postSettings({
          action: 'toggle_hidden',
          source: hiddenToggle.dataset.toggleHidden || '',
          term: hiddenToggle.dataset.term || '',
          hidden: nextHidden ? 1 : 0,
          query,
        });
        if (data.error) throw new Error(data.error);
        settingsData = data.data || settingsData;
        priorityDraft = Array.isArray(settingsData?.priorities)
          ? settingsData.priorities.map((row) => ({ ...row }))
          : priorityDraft;
        refreshSettingsUi();
        renderHideResults(data.results || []);
      } catch (error) {
        alert(error.message || 'Could not update hidden status.');
      }
      return;
    }

    if (dropdown && !dropdown.contains(event.target) && (!event.target.matches(fieldSelector) || !isInPcRoot(event.target))) {
      close();
    }
  });

  document.addEventListener('change', (event) => {
    const toggle = event.target.closest('[data-priority-toggle]');
    if (!toggle) return;
    const row = priorityDraft.find((item) => item.source === toggle.dataset.priorityToggle);
    if (row) {
      row.is_enabled = toggle.checked ? 1 : 0;
    }
  });

  document.addEventListener('blur', (event) => {
    if (event.target.matches(fieldSelector) && isInPcRoot(event.target)) {
      const blurredInput = event.target;
      setTimeout(() => {
        if (dropdown && document.activeElement && dropdown.contains(document.activeElement)) {
          return;
        }
        // Only close if no other PC field has taken over
        if (activeInput === blurredInput || activeInput === null) {
          close();
        }
      }, 120);
    }
  }, true);

  window.addEventListener('resize', close);

  // Prefetch all blank-term PC suggestions in one call after page settles
  setTimeout(() => {
    ensureAtLeastOnePcRow();
  }, 400);

  setTimeout(() => {
    fetch('api/pc_suggestions.php?field=bulk&term=')
      .then((res) => res.json())
      .then((data) => {
        if (data.complaint && !suggestCache['complaint::']) suggestCache['complaint::'] = data.complaint;
        if (data.duration && !suggestCache['duration::'])   suggestCache['duration::'] = data.duration;
        if (data.unit && !suggestCache['unit::'])           suggestCache['unit::'] = data.unit;
      })
      .catch(() => {});
  }, 3000);
}
