// Application bootstrap, date picker initialization, table row drag-and-drop reordering, and global modals.
window.ZimRxUtils = window.ZimRxUtils || {};
if (!window.ZimRxUtils.escapeHtml) {
  window.ZimRxUtils.escapeHtml = function (value) {
    if (value == null) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };
}
window.escapeHtml = window.escapeHtml || window.ZimRxUtils.escapeHtml;

function initializeDynamicDatePickers(root = document) {
  if (typeof flatpickr !== 'function') {
    return;
  }

  root.querySelectorAll('.custom-date-picker').forEach((input) => {
    if (input._flatpickr) {
      return;
    }

    flatpickr(input, {
      dateFormat: 'd/m/Y',
      allowInput: true,
      onChange: (_, dateStr, instance) => {
        instance.input.dispatchEvent(new Event('input', { bubbles: true }));
        instance.input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });
}

function initializeSimplePcTableReorder(root = document) {
  const wrapperSelector = [
    '#dx-wrapper',
    '#ix-wrapper',
    '#plan-wrapper',
    '#note-wrapper',
    '#report-entry-wrapper',
    '#uploaded-reports-wrapper',
    '#pe-wrapper',
    '#pc-wrapper',
    '#oh-wrapper',
    '#history-wrapper',
    '#paediatric-history-wrapper',
    '#ph-module-wrapper',
    '#ot-wrapper',
    '#ot-note-wrapper',
    '#advice-wrapper',
    '.advice-wrapper',
    '.ot-wrapper',
    '.reports-wrapper',
    '.rx-wrapper',
    '.pc-wrapper',
    '.oh-wrapper',
    '.zrx-occ-table',
    '.zrx-table',
    '.phrase-template-card',
    '.phrase-table-wrap',
    '.template-table-container',
    '[data-zrx-reorderable]'
  ].join(',');

  const rowSelector = 'tr.pc-row, tr.rx-row, tr.oh-row, tr.ot-row, tr.phrase-row, tr.template-row, .advice-row, tr[draggable="true"]';
  const handleSelector = '.pc-row-move-btn, .rx-row-move-btn, .oh-row-move-btn, .ot-row-move-btn, .zrx-drag-handle, .pc-drag, .rx-drag, .oh-drag, .ot-drag, .adv-drag, .phrase-handle, .instruction-handle, [data-drag-handle]';

  if (document.documentElement.dataset.simplePcReorderReady === '1') {
    return;
  }
  document.documentElement.dataset.simplePcReorderReady = '1';

  let draggedRow = null;
  let draggedTbody = null;
  let floatingDragElement = null;
  let grabOffsetX = 0;
  let grabOffsetY = 0;
  let dragClassTimeout = null;

  function getWrapper(target) {
    return target?.closest?.(wrapperSelector) || null;
  }

  function updateRowNumbers(tbody) {
    if (!tbody) return;
    tbody.querySelectorAll('.pc-row-no, .rx-no, .oh-row-no, .phrase-sl, .row-no, .sl-no').forEach((cell, index) => {
      cell.textContent = index + 1;
    });
  }

  function clearDropTargets(tbody) {
    if (!tbody) return;
    tbody.querySelectorAll('.drop-target').forEach((row) => {
      row.classList.remove('drop-target');
    });
  }

  const removeFloatingDrag = () => {
    if (dragClassTimeout) {
      clearTimeout(dragClassTimeout);
      dragClassTimeout = null;
    }
    if (floatingDragElement) {
      floatingDragElement.remove();
      floatingDragElement = null;
    }
    document.querySelectorAll('.dragging').forEach((r) => r.classList.remove('dragging'));
    document.querySelectorAll('.zrx-drag-ghost-floating').forEach((el) => el.remove());
  };

  const createFloatingDrag = (row, clientX, clientY) => {
    removeFloatingDrag();
    const rect = row.getBoundingClientRect();
    const isTable = row.tagName === 'TR';
    const table = isTable ? row.closest('table') : null;
    let ghostElement;

    if (isTable) {
      const ghostTable = document.createElement('table');
      ghostTable.className = (table ? table.className : 'pc-table') + ' zrx-drag-ghost-floating';
      ghostTable.style.position = 'fixed';
      ghostTable.style.top = `${rect.top}px`;
      ghostTable.style.left = `${rect.left}px`;
      ghostTable.style.width = `${rect.width}px`;
      ghostTable.style.tableLayout = 'fixed';
      ghostTable.style.zIndex = '999999';
      ghostTable.style.pointerEvents = 'none';
      ghostTable.style.opacity = '0.92';
      ghostTable.style.boxShadow = 'var(--zrx-shadow-modal)';
      ghostTable.style.background = '#ffffff';

      const clonedRow = row.cloneNode(true);
      clonedRow.classList.remove('dragging', 'drop-target');

      // Copy exact column widths
      const origCells = row.children;
      const cloneCells = clonedRow.children;
      for (let i = 0; i < origCells.length; i++) {
        const origTd = origCells[i];
        const cloneTd = cloneCells[i];
        if (origTd && cloneTd) {
          const cellRect = origTd.getBoundingClientRect();
          cloneTd.style.width = `${cellRect.width}px`;
          cloneTd.style.minWidth = `${cellRect.width}px`;
          cloneTd.style.maxWidth = `${cellRect.width}px`;
          cloneTd.style.boxSizing = 'border-box';
        }
      }

      const origInputs = row.querySelectorAll('input, textarea, select');
      const cloneInputs = clonedRow.querySelectorAll('input, textarea, select');
      origInputs.forEach((inp, idx) => {
        if (cloneInputs[idx]) {
          cloneInputs[idx].value = inp.value;
        }
      });

      const tbody = document.createElement('tbody');
      tbody.appendChild(clonedRow);
      ghostTable.appendChild(tbody);
      ghostElement = ghostTable;
    } else {
      const clonedDiv = row.cloneNode(true);
      clonedDiv.classList.remove('dragging', 'drop-target');
      clonedDiv.classList.add('zrx-drag-ghost-floating');
      clonedDiv.style.position = 'fixed';
      clonedDiv.style.top = `${rect.top}px`;
      clonedDiv.style.left = `${rect.left}px`;
      clonedDiv.style.width = `${rect.width}px`;
      clonedDiv.style.zIndex = '999999';
      clonedDiv.style.pointerEvents = 'none';
      clonedDiv.style.opacity = '0.92';
      clonedDiv.style.boxShadow = 'var(--zrx-shadow-lg)';
      clonedDiv.style.background = '#ffffff';

      const origInputs = row.querySelectorAll('input, textarea, select');
      const cloneInputs = clonedDiv.querySelectorAll('input, textarea, select');
      origInputs.forEach((inp, idx) => {
        if (cloneInputs[idx]) {
          cloneInputs[idx].value = inp.value;
        }
      });
      ghostElement = clonedDiv;
    }

    document.body.appendChild(ghostElement);

    grabOffsetX = clientX ? (clientX - rect.left) : (rect.width / 2);
    grabOffsetY = clientY ? (clientY - rect.top) : (rect.height / 2);
    floatingDragElement = ghostElement;
  };

  const armRowForDrag = (event) => {
    const moveButton = event.target.closest(handleSelector);
    const wrapper = getWrapper(moveButton);
    if (!moveButton || !wrapper) {
      return;
    }
    moveButton.closest(rowSelector)?.setAttribute('data-drag-ready', '1');
  };

  document.addEventListener('pointerdown', armRowForDrag);
  document.addEventListener('mousedown', armRowForDrag);

  document.addEventListener('dragstart', (event) => {
    const row = event.target.closest(rowSelector);
    const wrapper = getWrapper(row);
    if (!row || !wrapper) {
      return;
    }

    if (row.dataset.dragReady !== '1') {
      const handle = event.target.closest(handleSelector);
      if (!handle) {
        event.preventDefault();
        return;
      }
    }

    draggedRow = row;
    draggedTbody = row.parentElement;
    createFloatingDrag(row, event.clientX, event.clientY);

    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', row.querySelector('.pc-row-no, .rx-no, .oh-row-no, .phrase-sl')?.textContent || row.getAttribute('data-name') || '');

      try {
        const blankCanvas = document.createElement('canvas');
        blankCanvas.width = 1;
        blankCanvas.height = 1;
        event.dataTransfer.setDragImage(blankCanvas, 0, 0);
      } catch (_) {}
    }

    if (dragClassTimeout) clearTimeout(dragClassTimeout);
    dragClassTimeout = setTimeout(() => {
      if (draggedRow) draggedRow.classList.add('dragging');
    }, 0);
  });

  document.addEventListener('dragenter', (event) => {
    if (draggedRow) {
      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
      }
    }
  });

  document.addEventListener('dragover', (event) => {
    if (!draggedRow || !draggedTbody) {
      return;
    }

    event.preventDefault();
    if (event.dataTransfer) {
      event.dataTransfer.dropEffect = 'move';
    }

    if (floatingDragElement && event.clientX && event.clientY) {
      floatingDragElement.style.left = `${event.clientX - grabOffsetX}px`;
      floatingDragElement.style.top = `${event.clientY - grabOffsetY}px`;
    }

    const targetRow = event.target.closest(rowSelector);
    if (!targetRow || targetRow === draggedRow || targetRow.parentElement !== draggedTbody || !getWrapper(targetRow)) {
      return;
    }

    clearDropTargets(draggedTbody);
    targetRow.classList.add('drop-target');

    const rect = targetRow.getBoundingClientRect();
    const insertAfter = event.clientY > rect.top + rect.height / 2;
    if (insertAfter) {
      targetRow.after(draggedRow);
    } else {
      targetRow.before(draggedRow);
    }
    updateRowNumbers(draggedTbody);
  });

  document.addEventListener('drop', (event) => {
    removeFloatingDrag();
    if (draggedRow) {
      event.preventDefault();
    }
    if (draggedTbody) {
      clearDropTargets(draggedTbody);
      updateRowNumbers(draggedTbody);
      draggedTbody.dispatchEvent(new CustomEvent('zrx:reordered', { bubbles: true }));
    }
  });

  document.addEventListener('dragend', () => {
    removeFloatingDrag();
    draggedRow?.classList.remove('dragging');
    draggedRow?.removeAttribute('data-drag-ready');
    clearDropTargets(draggedTbody);
    updateRowNumbers(draggedTbody);
    document.querySelectorAll(rowSelector).forEach((r) => {
      r.classList.remove('dragging', 'drop-target');
      r.removeAttribute('data-drag-ready');
    });
    draggedRow = null;
    draggedTbody = null;
  });
}

// Bootstrap page components on DOM content loaded
document.addEventListener('DOMContentLoaded', async () => {
  document.addEventListener('click', (event) => {
    const interactiveTarget = event.target.closest('input, textarea, select, button, a, .rx-dropdown, .patient-lookup-list');
    if (!interactiveTarget) {
      const cell = event.target.closest('.rx-table td, .pc-table td');
      const field = cell?.querySelector(':scope > .rx-input, :scope > .pc-input');
      if (field && !field.disabled && !field.readOnly) {
        field.focus({ preventScroll: true });
        if (typeof field.setSelectionRange === 'function' && typeof field.value === 'string') {
          const caret = field.value.length;
          field.setSelectionRange(caret, caret);
        }
        return;
      }
    }

    const trigger = event.target.closest('.zimrx-date-trigger');
    if (!trigger) {
      return;
    }

    const input = trigger.parentElement?.querySelector('.custom-date-picker');
    if (!input) {
      return;
    }

    event.preventDefault();
    input.focus();
    input._flatpickr?.open();
  });

  if (typeof openPrescriptionPreview === 'function') {
    document.addEventListener('click', openPrescriptionPreview);
  }

  if (typeof learnRxRegimensOnSave === 'function') {
    document.addEventListener('click', learnRxRegimensOnSave);
  }

  // Initialize core input and form handlers immediately
  initializeDynamicDatePickers();
  initializeSimplePcTableReorder();
  initializeUniversalGridNavigation();

  if (typeof initPatientReferredByControl === 'function') {
    initPatientReferredByControl();
  }

  if (document.getElementById('sidebar-modules')) {
    try {
      await renderMainUI();
    } catch (err) {
      console.error('renderMainUI error:', err);
    }

    if (typeof initRxAutocomplete === 'function') {
      initRxAutocomplete();
    }

    if (typeof initPcAutocomplete === 'function') {
      initPcAutocomplete();
    }

    if (typeof initOhModule === 'function') {
      initOhModule();
    }

    if (typeof initPaediatricModule === 'function') {
      initPaediatricModule();
    }

    if (typeof initHoDietDropdown === 'function') {
      initHoDietDropdown();
    }
  }

  if (document.getElementById('left-side-setup')) {
    // Selects are pre-rendered by PHP: just wire the buttons
    const saveBtn = document.getElementById('btn-save-settings');
    const resetBtn = document.getElementById('btn-reset-settings');
    if (saveBtn) saveBtn.addEventListener('click', saveSettings);
    if (resetBtn) resetBtn.addEventListener('click', resetSettings);
  }

  initializeHelpGuidelineModals();
});

function showZrxAlert(message, options = {}) {
  const type = options.type || 'warning';
  const title = options.title || (type === 'warning' ? 'Attention Required' : (type === 'error' ? 'Error' : 'Notification'));
  const btnText = options.btnText || 'Okay';
  const onOk = typeof options.onOk === 'function' ? options.onOk : null;

  let overlay = document.getElementById('zrx-alert-modal');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'zrx-alert-modal';
    overlay.className = 'zrx-alert-modal-overlay';
    overlay.innerHTML = `
      <div class="zrx-alert-card" role="dialog" aria-modal="true">
        <div class="zrx-alert-icon-wrap" id="zrx-alert-icon-wrap"></div>
        <div class="zrx-alert-content">
          <strong class="zrx-alert-title" id="zrx-alert-title"></strong>
          <p class="zrx-alert-message" id="zrx-alert-message"></p>
        </div>
        <div class="zrx-alert-footer">
          <button type="button" class="zrx-alert-ok-btn" id="zrx-alert-ok-btn">Okay</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
  }

  const card = overlay.querySelector('.zrx-alert-card');
  const iconWrap = overlay.querySelector('#zrx-alert-icon-wrap');
  const titleEl = overlay.querySelector('#zrx-alert-title');
  const msgEl = overlay.querySelector('#zrx-alert-message');
  const okBtn = overlay.querySelector('#zrx-alert-ok-btn');

  card.setAttribute('data-type', type);
  titleEl.textContent = title;
  msgEl.textContent = message;
  okBtn.textContent = btnText;

  if (type === 'warning') {
    iconWrap.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
  } else if (type === 'error') {
    iconWrap.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
  } else if (type === 'success') {
    iconWrap.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
  } else {
    iconWrap.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  }

  overlay.classList.add('active');
  okBtn.focus();

  const close = () => {
    overlay.classList.remove('active');
    if (onOk) onOk();
  };

  okBtn.onclick = close;
  overlay.onclick = (e) => {
    if (e.target === overlay) close();
  };
  const onKey = (e) => {
    if (e.key === 'Escape' || e.key === 'Enter') {
      if (overlay.classList.contains('active')) {
        e.preventDefault();
        close();
        document.removeEventListener('keydown', onKey);
      }
    }
  };
  document.addEventListener('keydown', onKey);
}

window.showZrxAlert = showZrxAlert;

function zrxShowFieldValidation(input, message = 'Please fill out this field.') {
  if (!input) return;

  // Clean up any existing validation tooltips
  const existing = document.querySelectorAll('.zrx-field-tooltip');
  existing.forEach((el) => el.remove());
  document.querySelectorAll('.zrx-input-invalid').forEach((el) => el.classList.remove('zrx-input-invalid'));

  input.focus();
  input.classList.add('zrx-input-invalid');

  const tooltip = document.createElement('div');
  tooltip.className = 'zrx-field-tooltip';
  tooltip.setAttribute('role', 'tooltip');
  tooltip.innerHTML = `
    <div class="zrx-field-tooltip-arrow"></div>
    <div class="zrx-field-tooltip-icon">!</div>
    <span class="zrx-field-tooltip-text">${String(message)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')}</span>
  `;

  document.body.appendChild(tooltip);

  function position() {
    const rect = input.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    let top = rect.bottom + window.scrollY + 6;
    let left = rect.left + window.scrollX;

    if (left + tooltipRect.width > window.innerWidth - 12) {
      left = Math.max(12, window.innerWidth - tooltipRect.width - 12);
    }

    tooltip.style.top = `${top}px`;
    tooltip.style.left = `${left}px`;
  }

  position();

  let isDismissed = false;
  let autoTimer = null;

  function dismiss() {
    if (isDismissed) return;
    isDismissed = true;
    clearTimeout(autoTimer);
    input.classList.remove('zrx-input-invalid');
    tooltip.classList.add('hiding');
    setTimeout(() => {
      tooltip.remove();
    }, 180);
    input.removeEventListener('input', dismiss);
    input.removeEventListener('focus', dismiss);
    document.removeEventListener('click', onDocClick);
    window.removeEventListener('resize', position);
    window.removeEventListener('scroll', position, true);
  }

  function onDocClick(e) {
    if (e.target !== input) {
      dismiss();
    }
  }

  input.addEventListener('input', dismiss, { once: true });
  input.addEventListener('focus', dismiss, { once: true });
  setTimeout(() => {
    document.addEventListener('click', onDocClick, { once: true });
  }, 50);

  window.addEventListener('resize', position, { passive: true });
  window.addEventListener('scroll', position, { passive: true, capture: true });

  autoTimer = setTimeout(dismiss, 4000);
}

window.zrxShowFieldValidation = zrxShowFieldValidation;
