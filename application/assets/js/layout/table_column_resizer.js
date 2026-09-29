// Universal table column resizer, textarea auto-sizing, and persistent layout manager.

function autoResizeTableTextareas(table) {
  if (!table) return;
  const isRxTable = table.classList.contains('rx-table');
  if (isRxTable && !document.body.classList.contains('rx-auto-row-size-enabled')) {
    return;
  }

  const textareas = table.querySelectorAll('tbody textarea');
  textareas.forEach((ta) => {
    if (!ta.value && !ta.placeholder) {
      ta.style.height = '36px';
      return;
    }
    ta.style.transition = 'none';
    ta.style.height = '0px';
    const minH = ta.classList.contains('pe-input') || ta.classList.contains('plan-input') || ta.classList.contains('note-input') ? 30 : 36;
    const natural = Math.max(minH, ta.scrollHeight);
    ta.style.height = natural + 'px';
    requestAnimationFrame(() => {
      ta.style.removeProperty('transition');
    });
  });
}

function ensureTableLayoutActions(table, tableKey) {
  if (!table || !tableKey) return;
  const wrapper = table.closest('.module-card, .pc-wrapper, .rx-wrapper, .oh-wrapper, .history-treatment-wrapper, .history-dh-wrapper, .ot-wrapper, .ph-history-wrapper, .reports-wrapper') || table.parentElement;
  if (!wrapper) return;

  const footer = wrapper.querySelector('.pc-footer, .rx-add-row, .oh-footer, .ot-footer, .reports-footer, .ph-add-row, .reports-add-row, [class*="-footer"], [class*="-add-row"]');
  if (!footer) return;

  let actions = footer.querySelector('.zrx-tbl-layout-actions');
  if (!actions) {
    actions = document.createElement('div');
    actions.className = 'zrx-tbl-layout-actions';
    actions.innerHTML = `
      <button type="button" class="zrx-btn-layout-save" title="Save this customized column layout">Save layout</button>
      <button type="button" class="zrx-btn-layout-reset" title="Reset column widths to default">Reset</button>
      <span role="button" tabindex="0" class="zrx-help-icon-btn zrx-tbl-layout-help" data-help-type="col-resize" title="টেবিল লেআউট নির্দেশিকা" aria-label="Layout Help">
        ${typeof ZimRxIcon !== 'undefined' ? ZimRxIcon.render('help-circle', 14) : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'}
      </span>
    `;

    const saveBtn = actions.querySelector('.zrx-btn-layout-save');
    const resetBtn = actions.querySelector('.zrx-btn-layout-reset');

    saveBtn.addEventListener('click', () => {
      const allThs = Array.from(table.querySelectorAll('thead th'));
      const widths = allThs.map(th => Math.round(th.getBoundingClientRect().width));
      
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';

      fetch('api/save_interface_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          table_columns: {
            [tableKey]: widths
          }
        })
      })
      .then(res => res.json())
      .then(data => {
        saveBtn.disabled = false;
        if (data.ok) {
          saveBtn.classList.add('saved');
          saveBtn.textContent = 'Saved ✓';
          if (!window.ZimRxSavedTableColumns) window.ZimRxSavedTableColumns = {};
          window.ZimRxSavedTableColumns[tableKey] = widths;
          try {
            localStorage.setItem('zimrx_tbl_cols_' + tableKey, JSON.stringify(widths));
          } catch(e) {}

          // Disappear smoothly 5 seconds after saving
          setTimeout(() => {
            if (actions.parentElement) {
              actions.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
              actions.style.opacity = '0';
              actions.style.transform = 'scale(0.95)';
              setTimeout(() => {
                actions.remove();
              }, 350);
            }
          }, 5000);
        } else {
          saveBtn.textContent = 'Save layout';
          alert('Failed to save layout: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(err => {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save layout';
        console.error('Error saving table layout:', err);
      });
    });

    resetBtn.addEventListener('click', () => {
      resetBtn.disabled = true;
      resetBtn.textContent = 'Resetting...';

      fetch('api/save_interface_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          reset_table_column: tableKey
        })
      })
      .then(res => res.json())
      .then(data => {
        resetBtn.disabled = false;
        if (data.ok) {
          if (window.ZimRxSavedTableColumns) {
            delete window.ZimRxSavedTableColumns[tableKey];
          }
          try {
            localStorage.removeItem('zimrx_tbl_cols_' + tableKey);
          } catch(e) {}

          // Restore pristine default colgroup if saved
          if (table.dataset.zrxDefaultColgroup) {
            const currentCg = table.querySelector('colgroup');
            if (currentCg) {
              currentCg.outerHTML = table.dataset.zrxDefaultColgroup;
            }
          } else {
            const allCurrentCols = table.querySelectorAll('colgroup col');
            allCurrentCols.forEach(c => c.style.removeProperty('width'));
          }

          // Restore default th widths from dataset or standard defaults
          if (table.dataset.zrxDefaultThWidths) {
            try {
              const defaultWidths = JSON.parse(table.dataset.zrxDefaultThWidths);
              const allCurrentThs = table.querySelectorAll('thead th');
              allCurrentThs.forEach((t, idx) => {
                if (defaultWidths[idx]) {
                  t.style.width = defaultWidths[idx];
                } else {
                  t.style.removeProperty('width');
                }
              });
            } catch(e) {
              const allCurrentThs = table.querySelectorAll('thead th');
              allCurrentThs.forEach(t => t.style.removeProperty('width'));
            }
          } else if (table.classList.contains('rx-table')) {
            const rxDefaults = ['32px', '36px', '38px', '', '18%', '16%', '20%', '12%'];
            const allCurrentThs = table.querySelectorAll('thead th');
            allCurrentThs.forEach((t, idx) => {
              if (rxDefaults[idx]) t.style.width = rxDefaults[idx];
              else t.style.removeProperty('width');
            });
          } else {
            const allCurrentThs = table.querySelectorAll('thead th');
            allCurrentThs.forEach(t => t.style.removeProperty('width'));
          }

          autoResizeTableTextareas(table);

          resetBtn.classList.add('saved');
          resetBtn.textContent = 'Reset ✓';

          // Disappear smoothly 5 seconds after resetting
          setTimeout(() => {
            if (actions.parentElement) {
              actions.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
              actions.style.opacity = '0';
              actions.style.transform = 'scale(0.95)';
              setTimeout(() => {
                actions.remove();
              }, 350);
            }
          }, 5000);
        } else {
          resetBtn.textContent = 'Reset';
          alert('Failed to reset layout: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(err => {
        resetBtn.disabled = false;
        resetBtn.textContent = 'Reset';
        console.error('Error resetting table layout:', err);
      });
    });

    footer.appendChild(actions);
  }
}

function initializeUniversalColumnResize(root = document) {
  const tableSelector = '.pc-table, .rx-table, .oh-table, .ot-table, .zrx-table, #reports-table, .reports-table, .template-table-container table';
  const tables = root.querySelectorAll(tableSelector);

  tables.forEach((table) => {
    if (table.dataset.zrxResizableInit === 'true') {
      return;
    }
    table.dataset.zrxResizableInit = 'true';

    // Store pristine default colgroup & th structure for accurate reset
    const defaultCg = table.querySelector('colgroup');
    if (defaultCg && !table.dataset.zrxDefaultColgroup) {
      table.dataset.zrxDefaultColgroup = defaultCg.outerHTML;
    }
    if (!table.dataset.zrxDefaultThWidths) {
      const initialThs = table.querySelectorAll('thead th');
      const defaultWidths = Array.from(initialThs).map(th => th.style.width || '');
      table.dataset.zrxDefaultThWidths = JSON.stringify(defaultWidths);
    }

    const getTableKey = () => {
      if (table.id) return table.id;
      const parentWithId = table.closest('[id]');
      if (parentWithId) return parentWithId.id + '_' + (table.className || 'tbl').split(' ')[0];
      return (table.className || 'tbl').split(' ')[0];
    };

    const tableKey = getTableKey();

    // Restore saved custom column widths from database or localStorage
    try {
      const savedWidths = (window.ZimRxSavedTableColumns && window.ZimRxSavedTableColumns[tableKey])
        || JSON.parse(localStorage.getItem('zimrx_tbl_cols_' + tableKey) || 'null');

      if (savedWidths && Array.isArray(savedWidths) && savedWidths.length > 0) {
        const initialThs = table.querySelectorAll('thead th');
        const initialCols = table.querySelectorAll('colgroup col');
        savedWidths.forEach((w, idx) => {
          if (w && initialThs[idx]) {
            initialThs[idx].style.width = w + 'px';
          }
          if (w && initialCols[idx]) {
            initialCols[idx].style.width = w + 'px';
          }
        });
      }
    } catch (e) {}

    const ths = table.querySelectorAll('thead th');
    const cols = table.querySelectorAll('colgroup col');

    ths.forEach((th, idx) => {
      // Auto-wrap raw text inside th in a clean truncating span with tooltip
      const rawText = th.textContent.trim();
      if (!th.querySelector('.zrx-th-text') && !th.querySelector('.pc-header-flex') && !th.querySelector('.pc-settings-btn') && !th.querySelector('button') && rawText) {
        th.title = rawText;
        const span = document.createElement('span');
        span.className = 'zrx-th-text';
        span.textContent = rawText;
        th.innerHTML = '';
        th.appendChild(span);
      } else if (rawText && !th.title) {
        th.title = rawText;
      }

      // Don't add resizer on the very last column
      if (idx === ths.length - 1) return;

      let resizer = th.querySelector('.zrx-col-resizer');
      if (!resizer) {
        resizer = document.createElement('div');
        resizer.className = 'zrx-col-resizer';
        resizer.title = 'Drag to resize adjacent columns';
        th.appendChild(resizer);
      }

      let startX = 0;
      let isDragging = false;

      const onMouseDown = (e) => {
        if (e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();

        isDragging = true;
        startX = e.pageX;

        // Lock current rendered pixel widths on all columns before dragging
        const allThs = Array.from(table.querySelectorAll('thead th'));
        const allCols = Array.from(table.querySelectorAll('colgroup col'));
        const currentRects = allThs.map(t => t.getBoundingClientRect().width);

        allThs.forEach((t, i) => {
          const w = Math.round(currentRects[i]);
          t.style.width = w + 'px';
          if (allCols[i]) {
            allCols[i].style.width = w + 'px';
          }
        });

        const nextTh = ths[idx + 1];
        if (!nextTh) return;

        const startWidthA = currentRects[idx];
        const startWidthB = currentRects[idx + 1];
        const combinedWidth = startWidthA + startWidthB;
        const minWidth = 28;

        resizer.classList.add('is-resizing');
        th.classList.add('zrx-th-resizing');
        document.body.classList.add('zrx-column-resizing');

        const onMouseMove = (ev) => {
          if (!isDragging) return;
          const deltaX = ev.pageX - startX;

          const minDelta = -(startWidthA - minWidth);
          const maxDelta = (startWidthB - minWidth);
          const clampedDelta = Math.max(minDelta, Math.min(maxDelta, deltaX));

          const newWidthA = Math.round(startWidthA + clampedDelta);
          const newWidthB = combinedWidth - newWidthA;

          th.style.width = newWidthA + 'px';
          nextTh.style.width = newWidthB + 'px';

          if (cols[idx]) {
            cols[idx].style.width = newWidthA + 'px';
          }
          if (cols[idx + 1]) {
            cols[idx + 1].style.width = newWidthB + 'px';
          }

          autoResizeTableTextareas(table);
        };

        const onMouseUp = () => {
          if (!isDragging) return;
          isDragging = false;
          resizer.classList.remove('is-resizing');
          th.classList.remove('zrx-th-resizing');
          document.body.classList.remove('zrx-column-resizing');

          window.removeEventListener('mousemove', onMouseMove);
          window.removeEventListener('mouseup', onMouseUp);

          autoResizeTableTextareas(table);
          ensureTableLayoutActions(table, tableKey);
        };

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
      };

      resizer.addEventListener('mouseenter', () => th.classList.add('zrx-th-resizing'));
      resizer.addEventListener('mouseleave', () => {
        if (!isDragging) th.classList.remove('zrx-th-resizing');
      });
      resizer.addEventListener('mousedown', onMouseDown);

      // Double click to reset column widths to default immediately
      resizer.addEventListener('dblclick', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const allCurrentThs = table.querySelectorAll('thead th');
        const allCurrentCols = table.querySelectorAll('colgroup col');
        allCurrentThs.forEach(t => t.style.removeProperty('width'));
        allCurrentCols.forEach(c => c.style.removeProperty('width'));
        autoResizeTableTextareas(table);
      });
    });
  });
}

window.initializeUniversalColumnResize = initializeUniversalColumnResize;

