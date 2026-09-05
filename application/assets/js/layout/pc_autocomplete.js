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

  const renderIcon = (name, size = 14, attrs = {}) => {
    if (window.ZimRxIcon && typeof window.ZimRxIcon.render === 'function') {
      return window.ZimRxIcon.render(name, size, attrs);
    }
    return '';
  };

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
      li.innerHTML = `<div class="pc-dropdown-item-content"><strong>${escapeHtml(item.label)}</strong></div>`;
      
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
  const clearPcSuggestCache = () => {
    Object.keys(suggestCache).forEach((k) => delete suggestCache[k]);
  };
  window.clearPcSuggestCache = clearPcSuggestCache;

  const load = (input) => {
    if (window.ZimRxNavSuppressDropdown) return;
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
        <td class="pc-priority-td-active"><input type="checkbox" class="pc-priority-checkbox" data-priority-toggle="${escapeHtml(row.source)}" ${row.is_enabled ? 'checked' : ''} aria-label="Toggle ${escapeHtml(row.label)}"></td>
        <td><span class="pc-priority-row-label">${escapeHtml(row.label)}</span></td>
        <td>
          <div class="pc-priority-order">
            <span class="pc-priority-badge">${index + 1}</span>
            <button type="button" class="pc-priority-move" data-priority-move="up" data-source="${escapeHtml(row.source)}" ${index === 0 ? 'disabled' : ''} title="Move Up">${renderIcon('chevron-up', 12)}</button>
            <button type="button" class="pc-priority-move" data-priority-move="down" data-source="${escapeHtml(row.source)}" ${index === priorityDraft.length - 1 ? 'disabled' : ''} title="Move Down">${renderIcon('chevron-down', 12)}</button>
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

  const renderUsageRanking = (filterQuery = '') => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-usage-ranking-list');
    if (!container) return;

    const searchInput = modal?.querySelector('.pc-usage-search-input');
    const clearBtn = modal?.querySelector('.pc-usage-search-clear-btn');
    const query = (typeof filterQuery === 'string' ? filterQuery : (searchInput?.value || '')).trim().toLowerCase();

    if (clearBtn && searchInput) {
      clearBtn.hidden = !searchInput.value.trim();
    }

    let items = Array.isArray(settingsData?.usage_ranking) ? [...settingsData.usage_ranking] : [];

    // Fallback to extracting from used_groups if usage_ranking wasn't in legacy cache
    if (!items.length && Array.isArray(settingsData?.used_groups)) {
      let rank = 0;
      settingsData.used_groups.forEach((group) => {
        (group.items || []).forEach((item) => {
          rank++;
          items.push({
            rank,
            term: item.term,
            source: group.source,
            source_label: group.label,
            usage_count: item.usage_count || 0,
            is_hidden: item.is_hidden,
          });
        });
      });
      items.sort((a, b) => (b.usage_count - a.usage_count) || a.term.localeCompare(b.term));
      items = items.slice(0, 100);
      items.forEach((item, idx) => { item.rank = idx + 1; });
    }

    if (!items.length) {
      container.innerHTML = '<div class="pc-settings-empty">No clinical usage data recorded yet. As you prescribe complaints, your Top 100 ranking will automatically appear here.</div>';
      return;
    }

    if (query) {
      items = items.filter((item) => (item.term || '').toLowerCase().includes(query));
    }

    if (!items.length) {
      container.innerHTML = `<div class="pc-settings-empty">No complaints matching "<strong>${escapeHtml(query)}</strong>" found in Top 100 usage rankings.</div>`;
      return;
    }

    container.innerHTML = items.map((item) => {
      let topClass = '';
      if (item.rank === 1) topClass = ' top-1';
      else if (item.rank === 2) topClass = ' top-2';
      else if (item.rank === 3) topClass = ' top-3';

      const usageLabel = Number(item.usage_count || 0) === 1 ? '1 use' : `${Number(item.usage_count || 0)} uses`;

      return `
        <div class="pc-usage-rank-item">
          <div class="pc-usage-rank-left">
            <div class="pc-usage-rank-badge${topClass}">#${item.rank}</div>
            <div>
              <div class="pc-search-result-term">${escapeHtml(item.term)}</div>
              <div class="pc-search-result-meta">
                <span class="pc-tag">${escapeHtml(item.source_label || pcSourceLabel(item.source))}</span>
                ${item.is_hidden ? '<span class="pc-tag hidden">Hidden</span>' : ''}
              </div>
            </div>
          </div>
          <div class="pc-usage-rank-actions">
            <span class="pc-usage-count-badge">${usageLabel}</span>
            <button type="button" class="pc-search-toggle" data-toggle-hidden="${escapeHtml(item.source || 'static_pc')}" data-term="${escapeHtml(item.term)}" data-hidden="${item.is_hidden ? '1' : '0'}">
              ${item.is_hidden ? 'Unhide' : 'Hide'}
            </button>
          </div>
        </div>
      `;
    }).join('');
  };

  const renderCustomList = () => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-custom-complaint-list');
    if (!container) return;

    const items = settingsData?.custom_terms || [];
    if (!items.length) {
      container.innerHTML = '<div class="pc-settings-empty">No custom PC has been added for this doctor yet.</div>';
      return;
    }

    container.innerHTML = items.map((item) => {
      const usageCount = Number(item.usage_count || 0);
      const usageBadge = usageCount > 0
        ? `<span class="pc-custom-usage-badge">${usageCount}× used</span>`
        : `<span class="pc-custom-usage-badge pc-custom-unused">Not used yet</span>`;

      return `
        <div class="pc-custom-item" data-custom-item="${escapeHtml(item.term)}">
          <div class="pc-custom-term-group">
            <div class="pc-custom-term">${escapeHtml(item.term)}</div>
            ${usageBadge}
          </div>
          <div class="pc-custom-actions">
            <button type="button" class="pc-custom-edit" data-edit-custom="${escapeHtml(item.term)}" title="Rename Custom PC">${renderIcon('edit', 13)}</button>
            <button type="button" class="pc-custom-remove" data-remove-custom="${escapeHtml(item.term)}" title="Remove Custom PC">${renderIcon('x', 14)}</button>
          </div>
        </div>
      `;
    }).join('');
  };

  const renderCustomDurationList = () => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-custom-duration-list');
    if (!container) return;

    const items = settingsData?.custom_durations || [];
    if (!items.length) {
      container.innerHTML = '<div class="pc-settings-empty">No custom duration has been added for this doctor yet.</div>';
      return;
    }

    container.innerHTML = items.map((item) => {
      const usageCount = Number(item.usage_count || 0);
      const usageBadge = usageCount > 0
        ? `<span class="pc-custom-usage-badge">${usageCount}× used</span>`
        : `<span class="pc-custom-usage-badge pc-custom-unused">Not used yet</span>`;

      return `
        <div class="pc-custom-item" data-custom-duration-item="${escapeHtml(item.term)}">
          <div class="pc-custom-term-group">
            <div class="pc-custom-term">${escapeHtml(item.term)}</div>
            ${usageBadge}
          </div>
          <div class="pc-custom-actions">
            <button type="button" class="pc-custom-edit pc-custom-duration-edit" data-edit-custom-duration="${escapeHtml(item.term)}" title="Rename Custom Duration">${renderIcon('edit', 13)}</button>
            <button type="button" class="pc-custom-remove pc-custom-duration-remove" data-remove-custom-duration="${escapeHtml(item.term)}" title="Remove Custom Duration">${renderIcon('x', 14)}</button>
          </div>
        </div>
      `;
    }).join('');
  };

  const renderCustomUnitList = () => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-custom-unit-list');
    if (!container) return;

    const items = settingsData?.custom_units || [];
    if (!items.length) {
      container.innerHTML = '<div class="pc-settings-empty">No custom unit has been added for this doctor yet.</div>';
      return;
    }

    container.innerHTML = items.map((item) => {
      const usageCount = Number(item.usage_count || 0);
      const usageBadge = usageCount > 0
        ? `<span class="pc-custom-usage-badge">${usageCount}× used</span>`
        : `<span class="pc-custom-usage-badge pc-custom-unused">Not used yet</span>`;

      return `
        <div class="pc-custom-item" data-custom-unit-item="${escapeHtml(item.term)}">
          <div class="pc-custom-term-group">
            <div class="pc-custom-term">${escapeHtml(item.term)}</div>
            ${usageBadge}
          </div>
          <div class="pc-custom-actions">
            <button type="button" class="pc-custom-edit pc-custom-unit-edit" data-edit-custom-unit="${escapeHtml(item.term)}" title="Rename Custom Unit">${renderIcon('edit', 13)}</button>
            <button type="button" class="pc-custom-remove pc-custom-unit-remove" data-remove-custom-unit="${escapeHtml(item.term)}" title="Remove Custom Unit">${renderIcon('x', 14)}</button>
          </div>
        </div>
      `;
    }).join('');
  };

  const renderHideResults = (results = null) => {
    const modal = getSettingsModal();
    const container = modal?.querySelector('.pc-hide-search-results');
    if (!container) return;

    const searchInput = modal?.querySelector('.pc-hide-search-input');
    const clearBtn = modal?.querySelector('.pc-search-clear-btn');
    const query = searchInput?.value.trim() || '';

    if (clearBtn) {
      clearBtn.hidden = !query;
    }

    // If no search query is typed, show all currently hidden terms cleanly
    if (!query) {
      const hiddenItems = Array.isArray(settingsData?.hidden_terms) ? settingsData.hidden_terms : [];
      if (!hiddenItems.length) {
        container.innerHTML = '<div class="pc-settings-empty">No terms are currently hidden for this doctor. Search above to find and hide any suggestion.</div>';
        return;
      }

      container.innerHTML = `
        <div class="pc-hidden-list-header">
          <span>Currently Hidden Terms (${hiddenItems.length})</span>
          <button type="button" class="pc-unhide-all-btn" data-unhide-all title="Unhide all suppressed terms">Unhide All</button>
        </div>
        ${hiddenItems.map((item) => `
          <div class="pc-search-result" data-term-card="${escapeHtml(item.term)}">
            <div class="pc-search-result-main">
              <div class="pc-search-result-term">${escapeHtml(item.term)}</div>
              <div class="pc-search-result-meta">
                <span class="pc-tag hidden">Hidden</span>
              </div>
            </div>
            <button type="button" class="pc-search-toggle" data-toggle-hidden="${escapeHtml(item.source || 'static_pc')}" data-term="${escapeHtml(item.term)}" data-hidden="1">
              Unhide
            </button>
          </div>
        `).join('')}
      `;
      return;
    }

    // When searching, show search results
    if (!Array.isArray(results) || !results.length) {
      container.innerHTML = '<div class="pc-settings-empty">No matching terms found.</div>';
      return;
    }

    container.innerHTML = results.map((item) => `
      <div class="pc-search-result" data-term-card="${escapeHtml(item.term)}">
        <div class="pc-search-result-main">
          <div class="pc-search-result-term">${escapeHtml(item.term)}</div>
          <div class="pc-search-result-meta">
            ${item.is_hidden 
              ? '<span class="pc-tag hidden">Hidden</span>' 
              : `<span class="pc-tag">${escapeHtml(item.source_label)}</span>`}
            ${!item.is_hidden && typeof item.usage_count === 'number' ? `<span>Used ${item.usage_count} time${item.usage_count === 1 ? '' : 's'}</span>` : ''}
          </div>
        </div>
        <button type="button" class="pc-search-toggle" data-toggle-hidden="${escapeHtml(item.source || 'static_pc')}" data-term="${escapeHtml(item.term)}" data-hidden="${item.is_hidden ? '1' : '0'}">
          ${item.is_hidden ? 'Unhide' : 'Hide'}
        </button>
      </div>
    `).join('');
  };

  const pcSourceLabel = (source) => {
    if (source === 'most_used') return 'Most Used P/C';
    if (source === 'custom') return 'Custom P/C';
    if (source === 'static_pc' || source === 'snomed') return 'System P/C';
    return source || '';
  };

  const refreshSettingsUi = () => {
    clearPcSuggestCache();
    renderPriorityTable();
    renderUsedGroups();
    renderUsageRanking();
    renderCustomList();
    renderCustomDurationList();
    renderCustomUnitList();
    renderHideResults();

    const modal = getSettingsModal();
    if (modal) {
      const customCount = (settingsData?.custom_terms || []).length;
      const hiddenCount = (settingsData?.hidden_terms || []).length;
      const customBadge = modal.querySelector('.pc-custom-count');
      if (customBadge) customBadge.textContent = String(customCount);
      const hiddenBadge = modal.querySelector('.pc-hidden-count');
      if (hiddenBadge) hiddenBadge.textContent = String(hiddenCount);
    }
  };

  const applySettingsData = (data) => {
    clearPcSuggestCache();
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
      renderHideResults();
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

  const resetModalScroll = () => {
    const modal = getSettingsModal();
    if (!modal) return;
    const body = modal.querySelector('.pc-settings-body');
    if (body) {
      body.scrollTop = 0;
      if (typeof body.scrollTo === 'function') {
        body.scrollTo({ top: 0, left: 0, behavior: 'instant' });
      }
    }
    const panel = modal.querySelector('.pc-settings-panel');
    if (panel) {
      panel.scrollTop = 0;
      if (typeof panel.scrollTo === 'function') {
        panel.scrollTo({ top: 0, left: 0, behavior: 'instant' });
      }
    }
  };

  const openSettings = async () => {
    const modal = getSettingsModal();
    if (!modal) return;
    close();

    // Blur any active element outside the modal
    if (document.activeElement && typeof document.activeElement.blur === 'function') {
      document.activeElement.blur();
    }

    const hasInitialData = applyInitialSettingsData();
    modal.hidden = false;
    document.body.style.overflow = 'hidden';

    // Default to the first tab (Settings)
    modal.querySelectorAll('.pc-settings-tab-btn').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.pcTab === 'settings');
    });
    modal.querySelectorAll('.pc-tab-pane').forEach((pane) => {
      const isSettings = pane.id === 'pc-tab-pane-settings';
      pane.hidden = !isSettings;
      pane.classList.toggle('active', isSettings);
    });

    // Reset Edit PC sub-tabs to 'all'
    modal.querySelectorAll('.pc-edit-tab-btn').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.editTab === 'all');
    });
    const customSec = modal.querySelector('.pc-edit-custom-section');
    const suppressSec = modal.querySelector('.pc-edit-suppress-section');
    const divider = modal.querySelector('.pc-edit-divider');
    if (customSec) customSec.hidden = false;
    if (suppressSec) suppressSec.hidden = false;
    if (divider) divider.hidden = false;

    resetModalScroll();
    requestAnimationFrame(resetModalScroll);
    setTimeout(resetModalScroll, 20);
    setTimeout(resetModalScroll, 80);
    setTimeout(resetModalScroll, 220);

    if (hasInitialData) {
      loadSettingsData().catch(() => {});
      return;
    }

    try {
      await loadSettingsData();
      resetModalScroll();
      requestAnimationFrame(resetModalScroll);
      setTimeout(resetModalScroll, 50);
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
      const clearBtn = event.target.closest('.pc-hide-search-box')?.querySelector('.pc-search-clear-btn');
      if (clearBtn) {
        clearBtn.hidden = !event.target.value.trim();
      }
      clearTimeout(hideSearchTimer);
      hideSearchTimer = setTimeout(() => performHideSearch(event.target.value), 180);
    }

    if (event.target.matches('.pc-usage-search-input')) {
      const clearBtn = event.target.closest('.pc-usage-search-box')?.querySelector('.pc-usage-search-clear-btn');
      if (clearBtn) {
        clearBtn.hidden = !event.target.value.trim();
      }
      renderUsageRanking(event.target.value);
    }
  });

  document.addEventListener('focus', (event) => {
    if (window.ZimRxNavSuppressDropdown) return;
    if (event.target.matches(fieldSelector) && isInPcRoot(event.target)) load(event.target);
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.target.matches('.pc-custom-input') && event.key === 'Enter') {
      event.preventDefault();
      event.target.closest('.pc-settings-card')?.querySelector('.pc-custom-add-btn')?.click();
      return;
    }

    if (event.key === 'Escape' && !getSettingsModal()?.hidden) {
      const searchInput = getSettingsModal()?.querySelector('.pc-hide-search-input');
      if (searchInput && searchInput === document.activeElement && searchInput.value.trim()) {
        event.preventDefault();
        searchInput.value = '';
        const clearBtn = getSettingsModal()?.querySelector('.pc-search-clear-btn');
        if (clearBtn) clearBtn.hidden = true;
        renderHideResults();
        return;
      }
      const usageInput = getSettingsModal()?.querySelector('.pc-usage-search-input');
      if (usageInput && usageInput === document.activeElement && usageInput.value.trim()) {
        event.preventDefault();
        usageInput.value = '';
        const clearBtn = getSettingsModal()?.querySelector('.pc-usage-search-clear-btn');
        if (clearBtn) clearBtn.hidden = true;
        renderUsageRanking();
        return;
      }
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
    const tabBtn = event.target.closest('[data-pc-tab]');
    if (tabBtn) {
      event.preventDefault();
      const modal = getSettingsModal();
      if (!modal) return;
      const tabName = tabBtn.dataset.pcTab;

      modal.querySelectorAll('.pc-settings-tab-btn').forEach((b) => b.classList.remove('active'));
      tabBtn.classList.add('active');

      modal.querySelectorAll('.pc-tab-pane').forEach((pane) => {
        pane.hidden = true;
        pane.classList.remove('active');
      });

      const activePane = modal.querySelector(`#pc-tab-pane-${tabName}`);
      if (activePane) {
        activePane.hidden = false;
        activePane.classList.add('active');
      }

      resetModalScroll();
      requestAnimationFrame(resetModalScroll);
      setTimeout(resetModalScroll, 30);
      return;
    }

    const searchClearBtn = event.target.closest('.pc-search-clear-btn');
    if (searchClearBtn) {
      event.preventDefault();
      const modal = getSettingsModal();
      const input = modal?.querySelector('.pc-hide-search-input');
      if (input) {
        input.value = '';
        searchClearBtn.hidden = true;
        input.focus();
      }
      renderHideResults();
      return;
    }

    const usageClearBtn = event.target.closest('.pc-usage-search-clear-btn');
    if (usageClearBtn) {
      event.preventDefault();
      const modal = getSettingsModal();
      const input = modal?.querySelector('.pc-usage-search-input');
      if (input) {
        input.value = '';
        usageClearBtn.hidden = true;
        input.focus();
      }
      renderUsageRanking();
      return;
    }

    const unhideAllBtn = event.target.closest('[data-unhide-all]');
    if (unhideAllBtn) {
      event.preventDefault();
      if (!confirm('Are you sure you want to unhide all hidden complaint terms?')) return;
      if (settingsData) {
        settingsData.hidden_terms = [];
      }
      renderHideResults();
      try {
        const data = await postSettings({ action: 'unhide_all' });
        if (data.data) {
          settingsData = data.data;
          priorityDraft = Array.isArray(settingsData?.priorities)
            ? settingsData.priorities.map((row) => ({ ...row }))
            : priorityDraft;
        }
        refreshSettingsUi();
      } catch (error) {
        alert(error.message || 'Could not unhide all terms.');
        loadSettingsData().catch(() => {});
      }
      return;
    }

    const settingsButton = event.target.closest('.pc-settings-btn');
    if (settingsButton) {
      event.preventDefault();
      openSettings();
      return;
    }

    const closeButton = event.target.closest('[data-pc-settings-close]');
    if (closeButton) {
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

    const editTabBtn = event.target.closest('.pc-edit-tab-btn');
    if (editTabBtn) {
      event.preventDefault();
      const tab = editTabBtn.dataset.editTab || 'all';
      const modal = getSettingsModal();
      if (!modal) return;
      modal.querySelectorAll('.pc-edit-tab-btn').forEach((btn) => {
        btn.classList.toggle('active', btn === editTabBtn);
      });
      const customSec = modal.querySelector('.pc-edit-custom-section');
      const suppressSec = modal.querySelector('.pc-edit-suppress-section');
      const divider = modal.querySelector('.pc-edit-divider');
      if (tab === 'all') {
        if (customSec) customSec.hidden = false;
        if (suppressSec) suppressSec.hidden = false;
        if (divider) divider.hidden = false;
      } else if (tab === 'custom') {
        if (customSec) customSec.hidden = false;
        if (suppressSec) suppressSec.hidden = true;
        if (divider) divider.hidden = true;
      } else if (tab === 'hidden') {
        if (customSec) customSec.hidden = true;
        if (suppressSec) suppressSec.hidden = false;
        if (divider) divider.hidden = true;
      }
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

    const addCustomButton = event.target.closest('.pc-custom-add-btn:not(.pc-custom-duration-add-btn):not(.pc-custom-unit-add-btn)');
    if (addCustomButton) {
      event.preventDefault();
      const modal = getSettingsModal();
      const input = modal?.querySelector('.pc-custom-input:not(.pc-custom-duration-input):not(.pc-custom-unit-input)');
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

    const addCustomDurationButton = event.target.closest('.pc-custom-duration-add-btn');
    if (addCustomDurationButton) {
      event.preventDefault();
      const modal = getSettingsModal();
      const input = modal?.querySelector('.pc-custom-duration-input');
      const term = input?.value.trim() || '';
      if (!term) {
        input?.focus();
        return;
      }
      try {
        const data = await postSettings({ action: 'add_custom_duration', term });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        refreshSettingsUi();
        input.value = '';
        input.focus();
      } catch (error) {
        alert(error.message || 'Could not add custom duration.');
      }
      return;
    }

    const addCustomUnitButton = event.target.closest('.pc-custom-unit-add-btn');
    if (addCustomUnitButton) {
      event.preventDefault();
      const modal = getSettingsModal();
      const input = modal?.querySelector('.pc-custom-unit-input');
      const term = input?.value.trim() || '';
      if (!term) {
        input?.focus();
        return;
      }
      try {
        const data = await postSettings({ action: 'add_custom_unit', term });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        refreshSettingsUi();
        input.value = '';
        input.focus();
      } catch (error) {
        alert(error.message || 'Could not add custom unit.');
      }
      return;
    }

    const editCustomButton = event.target.closest('[data-edit-custom]');
    if (editCustomButton) {
      event.preventDefault();
      const itemEl = editCustomButton.closest('.pc-custom-item');
      const term = editCustomButton.dataset.editCustom || '';
      if (!itemEl || !term) return;

      itemEl.innerHTML = `
        <div class="pc-custom-edit-form">
          <input type="text" class="pc-custom-inline-input" value="${escapeHtml(term)}">
          <div class="pc-custom-inline-actions">
            <button type="button" class="pc-custom-save-edit" data-save-custom-edit="${escapeHtml(term)}" title="Save Rename">${renderIcon('check', 14)}</button>
            <button type="button" class="pc-custom-cancel-edit" title="Cancel">${renderIcon('x', 14)}</button>
          </div>
        </div>
      `;
      const input = itemEl.querySelector('.pc-custom-inline-input');
      if (input) {
        input.focus();
        input.select();
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            itemEl.querySelector('.pc-custom-save-edit')?.click();
          } else if (e.key === 'Escape') {
            e.preventDefault();
            renderCustomList();
          }
        });
      }
      return;
    }

    const saveEditButton = event.target.closest('[data-save-custom-edit]');
    if (saveEditButton) {
      event.preventDefault();
      const itemEl = saveEditButton.closest('.pc-custom-item');
      const oldTerm = saveEditButton.dataset.saveCustomEdit || '';
      const input = itemEl?.querySelector('.pc-custom-inline-input');
      const newTerm = input?.value.trim() || '';

      if (!newTerm || newTerm.toLowerCase() === oldTerm.toLowerCase()) {
        renderCustomList();
        return;
      }

      try {
        const data = await postSettings({
          action: 'edit_custom',
          old_term: oldTerm,
          new_term: newTerm,
        });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        priorityDraft = Array.isArray(data.priorities)
          ? data.priorities.map((row) => ({ ...row }))
          : priorityDraft;
        refreshSettingsUi();
      } catch (error) {
        alert(error.message || 'Could not rename custom PC.');
        renderCustomList();
      }
      return;
    }

    const cancelEditButton = event.target.closest('.pc-custom-cancel-edit');
    if (cancelEditButton) {
      event.preventDefault();
      renderCustomList();
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

    const editCustomDurationButton = event.target.closest('[data-edit-custom-duration]');
    if (editCustomDurationButton) {
      event.preventDefault();
      const itemEl = editCustomDurationButton.closest('.pc-custom-item');
      const term = editCustomDurationButton.dataset.editCustomDuration || '';
      if (!itemEl || !term) return;

      itemEl.innerHTML = `
        <div class="pc-custom-edit-form">
          <input type="text" class="pc-custom-inline-input pc-custom-duration-inline-input" value="${escapeHtml(term)}">
          <div class="pc-custom-inline-actions">
            <button type="button" class="pc-custom-save-edit pc-custom-duration-save-edit" data-save-custom-duration-edit="${escapeHtml(term)}" title="Save Rename">${renderIcon('check', 14)}</button>
            <button type="button" class="pc-custom-cancel-edit pc-custom-duration-cancel-edit" title="Cancel">${renderIcon('x', 14)}</button>
          </div>
        </div>
      `;
      const input = itemEl.querySelector('.pc-custom-duration-inline-input');
      if (input) {
        input.focus();
        input.select();
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            itemEl.querySelector('.pc-custom-duration-save-edit')?.click();
          } else if (e.key === 'Escape') {
            e.preventDefault();
            renderCustomDurationList();
          }
        });
      }
      return;
    }

    const saveEditDurationButton = event.target.closest('[data-save-custom-duration-edit]');
    if (saveEditDurationButton) {
      event.preventDefault();
      const itemEl = saveEditDurationButton.closest('.pc-custom-item');
      const oldTerm = saveEditDurationButton.dataset.saveCustomDurationEdit || '';
      const input = itemEl?.querySelector('.pc-custom-duration-inline-input');
      const newTerm = input?.value.trim() || '';

      if (!newTerm || newTerm.toLowerCase() === oldTerm.toLowerCase()) {
        renderCustomDurationList();
        return;
      }

      try {
        const data = await postSettings({
          action: 'edit_custom_duration',
          old_term: oldTerm,
          new_term: newTerm,
        });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        refreshSettingsUi();
      } catch (error) {
        alert(error.message || 'Could not rename custom duration.');
        renderCustomDurationList();
      }
      return;
    }

    const cancelEditDurationButton = event.target.closest('.pc-custom-duration-cancel-edit');
    if (cancelEditDurationButton) {
      event.preventDefault();
      renderCustomDurationList();
      return;
    }

    const removeCustomDurationButton = event.target.closest('[data-remove-custom-duration]');
    if (removeCustomDurationButton) {
      event.preventDefault();
      try {
        const data = await postSettings({
          action: 'remove_custom_duration',
          term: removeCustomDurationButton.dataset.removeCustomDuration || '',
        });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        refreshSettingsUi();
      } catch (error) {
        alert(error.message || 'Could not remove custom duration.');
      }
      return;
    }

    const editCustomUnitButton = event.target.closest('[data-edit-custom-unit]');
    if (editCustomUnitButton) {
      event.preventDefault();
      const itemEl = editCustomUnitButton.closest('.pc-custom-item');
      const term = editCustomUnitButton.dataset.editCustomUnit || '';
      if (!itemEl || !term) return;

      itemEl.innerHTML = `
        <div class="pc-custom-edit-form">
          <input type="text" class="pc-custom-inline-input pc-custom-unit-inline-input" value="${escapeHtml(term)}">
          <div class="pc-custom-inline-actions">
            <button type="button" class="pc-custom-save-edit pc-custom-unit-save-edit" data-save-custom-unit-edit="${escapeHtml(term)}" title="Save Rename">${renderIcon('check', 14)}</button>
            <button type="button" class="pc-custom-cancel-edit pc-custom-unit-cancel-edit" title="Cancel">${renderIcon('x', 14)}</button>
          </div>
        </div>
      `;
      const input = itemEl.querySelector('.pc-custom-unit-inline-input');
      if (input) {
        input.focus();
        input.select();
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            itemEl.querySelector('.pc-custom-unit-save-edit')?.click();
          } else if (e.key === 'Escape') {
            e.preventDefault();
            renderCustomUnitList();
          }
        });
      }
      return;
    }

    const saveEditUnitButton = event.target.closest('[data-save-custom-unit-edit]');
    if (saveEditUnitButton) {
      event.preventDefault();
      const itemEl = saveEditUnitButton.closest('.pc-custom-item');
      const oldTerm = saveEditUnitButton.dataset.saveCustomUnitEdit || '';
      const input = itemEl?.querySelector('.pc-custom-unit-inline-input');
      const newTerm = input?.value.trim() || '';

      if (!newTerm || newTerm.toLowerCase() === oldTerm.toLowerCase()) {
        renderCustomUnitList();
        return;
      }

      try {
        const data = await postSettings({
          action: 'edit_custom_unit',
          old_term: oldTerm,
          new_term: newTerm,
        });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        refreshSettingsUi();
      } catch (error) {
        alert(error.message || 'Could not rename custom unit.');
        renderCustomUnitList();
      }
      return;
    }

    const cancelEditUnitButton = event.target.closest('.pc-custom-unit-cancel-edit');
    if (cancelEditUnitButton) {
      event.preventDefault();
      renderCustomUnitList();
      return;
    }

    const removeCustomUnitButton = event.target.closest('[data-remove-custom-unit]');
    if (removeCustomUnitButton) {
      event.preventDefault();
      try {
        const data = await postSettings({
          action: 'remove_custom_unit',
          term: removeCustomUnitButton.dataset.removeCustomUnit || '',
        });
        if (data.error) throw new Error(data.error);
        settingsData = data;
        refreshSettingsUi();
      } catch (error) {
        alert(error.message || 'Could not remove custom unit.');
      }
      return;
    }

    const hiddenToggle = event.target.closest('[data-toggle-hidden]');
    if (hiddenToggle) {
      event.preventDefault();
      const modal = getSettingsModal();
      const searchInput = modal?.querySelector('.pc-hide-search-input');
      const query = searchInput?.value.trim() || '';
      const source = hiddenToggle.dataset.toggleHidden || 'static_pc';
      const term = hiddenToggle.dataset.term || '';
      const nextHidden = hiddenToggle.dataset.hidden !== '1';

      // 1. Instant Optimistic UI state flip
      hiddenToggle.dataset.hidden = nextHidden ? '1' : '0';
      hiddenToggle.textContent = nextHidden ? 'Unhide' : 'Hide';

      if (Array.isArray(settingsData?.hidden_terms)) {
        const normKey = term.toLowerCase().trim();
        settingsData.hidden_terms = settingsData.hidden_terms.filter(
          (item) => (item.term || '').toLowerCase().trim() !== normKey
        );
        if (nextHidden) {
          settingsData.hidden_terms.unshift({
            source,
            source_label: pcSourceLabel(source),
            term,
            is_hidden: true,
            updated_at: new Date().toISOString(),
          });
        }
      }

      // Also update is_hidden in settingsData.usage_ranking
      if (Array.isArray(settingsData?.usage_ranking)) {
        const normKey = term.toLowerCase().trim();
        const rankingItem = settingsData.usage_ranking.find(
          (item) => (item.term || '').toLowerCase().trim() === normKey
        );
        if (rankingItem) {
          rankingItem.is_hidden = nextHidden;
        }
      }

      // If in default view (no search active), update lists
      if (!query) {
        renderHideResults();
      } else {
        const card = hiddenToggle.closest('.pc-search-result');
        const meta = card?.querySelector('.pc-search-result-meta');
        if (meta) {
          meta.innerHTML = nextHidden
            ? '<span class="pc-tag hidden">Hidden</span>'
            : `<span class="pc-tag">${escapeHtml(pcSourceLabel(source))}</span>`;
        }
      }
      renderUsageRanking();

      // 2. Background persistence
      postSettings({
        action: 'toggle_hidden',
        source,
        term,
        hidden: nextHidden ? 1 : 0,
        query,
      }).then((data) => {
        if (data.error) throw new Error(data.error);
        if (data.data) {
          settingsData = data.data;
          priorityDraft = Array.isArray(settingsData?.priorities)
            ? settingsData.priorities.map((row) => ({ ...row }))
            : priorityDraft;
        }
        const currentQuery = modal?.querySelector('.pc-hide-search-input')?.value.trim() || '';
        if (currentQuery) {
          renderHideResults(data.results || []);
        }
        renderUsageRanking();
      }).catch((error) => {
        alert(error.message || 'Could not update hidden status.');
        loadSettingsData().catch(() => {});
      });
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
