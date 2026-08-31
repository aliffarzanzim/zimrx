// Universal keyboard grid navigation engine (cell navigation, row insertion, deletion, reordering).
function initializeUniversalGridNavigation(root = document) {
  if (document.documentElement.dataset.universalGridNavReady === '1') {
    return;
  }
  document.documentElement.dataset.universalGridNavReady = '1';

  let isModifierDown = false;
  let navSuppressTimeout = null;

  const setDropdownSuppression = (active) => {
    if (active) {
      window.ZimRxNavSuppressDropdown = true;
      document.body.classList.add('zrx-suppress-dropdowns');
    } else {
      if (navSuppressTimeout) clearTimeout(navSuppressTimeout);
      navSuppressTimeout = setTimeout(() => {
        if (!isModifierDown) {
          window.ZimRxNavSuppressDropdown = false;
          document.body.classList.remove('zrx-suppress-dropdowns');
        }
      }, 150);
    }
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Alt' || e.key === 'Shift') {
      isModifierDown = true;
      setDropdownSuppression(true);
    }
  }, true);

  document.addEventListener('keyup', (e) => {
    if (e.key === 'Alt' || e.key === 'Shift') {
      if (!e.altKey && !e.shiftKey) {
        isModifierDown = false;
        setDropdownSuppression(false);
      }
    }
  }, true);

  const isGridEditableCell = (el) => {
    if (!el || !el.matches('input, textarea, select')) return false;
    if (el.type === 'hidden' || el.disabled || el.readOnly) return false;
    const tr = el.closest('tr');
    if (!tr) return false;
    const table = tr.closest('table');
    if (!table) return false;
    // Exclude settings modals or dialog popups
    if (el.closest('.pc-settings-modal, .rx-settings-modal, .modal, [role="dialog"]')) return false;
    // Exclude table action buttons, delete triggers, row move handles
    if (el.closest('.pc-del, .rx-del, .oh-del, .pc-drag, .rx-drag, .oh-drag, .pc-action, .rx-action, .pc-row-no, .rx-no, .oh-row-no')) return false;
    return true;
  };

  const getRowCells = (tr) => {
    if (!tr) return [];
    return Array.from(tr.querySelectorAll(
      'input:not([type="hidden"]):not([disabled]):not([readonly]), textarea:not([disabled]):not([readonly]), select:not([disabled]):not([readonly])'
    )).filter((el) => {
      if (el.closest('.pc-del, .rx-del, .oh-del, .pc-drag, .rx-drag, .oh-drag, .pc-action, .rx-action, .pc-row-no, .rx-no, .oh-row-no')) return false;
      return el.offsetParent !== null; // visible
    });
  };

  const getTableRows = (table) => {
    if (!table) return [];
    const tbody = table.querySelector('tbody') || table;
    return Array.from(tbody.querySelectorAll('tr')).filter((tr) => {
      if (tr.closest('template') || tr.classList.contains('zrx-drag-ghost') || tr.classList.contains('zrx-drag-ghost-floating')) return false;
      return tr.offsetParent !== null;
    });
  };

  const updateGridRowNumbers = (tbody) => {
    if (!tbody) return;
    tbody.querySelectorAll('.pc-row-no, .rx-no, .oh-row-no, .phrase-sl, .row-no, .sl-no').forEach((cell, index) => {
      cell.textContent = index + 1;
    });
  };

  const findAddRowButton = (table) => {
    const container = table.closest(
      '#dx-wrapper, #ix-wrapper, #plan-wrapper, #note-wrapper, #pe-wrapper, #pc-wrapper, #oh-wrapper, #history-wrapper, .advice-wrapper, .reports-wrapper, .rx-wrapper, .pc-wrapper, .oh-wrapper, .module-card, section'
    ) || table.parentElement;
    if (!container) return null;
    return container.querySelector(
      '.pc-add-row-btn, .rx-add-row-btn, #rx-add-more-btn, .oh-add-row-btn, .ot-add-row-btn, .reports-add-row-btn, .zrx-add-row-btn, [data-add-row]'
    );
  };

  const focusCell = (target) => {
    if (!target) return false;
    try {
      target.focus({ preventScroll: true });
    } catch (_) {
      target.focus();
    }
    if (target.tagName === 'INPUT' && typeof target.select === 'function' && target.value) {
      try { target.select(); } catch (_) {}
    }
    return true;
  };

  const triggerAddRowAndFocus = (table) => {
    const addBtn = findAddRowButton(table);
    if (!addBtn) return false;
    const rowsBefore = getTableRows(table).length;
    addBtn.click();
    let attempts = 0;
    const poll = () => {
      const rowsAfter = getTableRows(table);
      if (rowsAfter.length > rowsBefore) {
        const newRow = rowsAfter[rowsAfter.length - 1];
        const cells = getRowCells(newRow);
        if (cells.length) {
          focusCell(cells[0]);
          return;
        }
      }
      if (++attempts < 15) {
        requestAnimationFrame(poll);
      }
    };
    requestAnimationFrame(poll);
    return true;
  };

  const getOpenDropdown = () => {
    const openLists = document.querySelectorAll(
      '.zrx-dropdown.show, .rx-dropdown.show, .autocomplete-list.show, .appointment-lookup-list.show, ul.show, .rx-dropdown:not([hidden]):not([style*="display: none"])'
    );
    for (const list of openLists) {
      if (list.offsetParent !== null) {
        const activeItem = list.querySelector('.zrx-dropdown-item.active, .rx-dropdown-item.active, li.active');
        return { list, activeItem };
      }
    }
    return null;
  };

  document.addEventListener('keydown', (e) => {
    const input = e.target;
    if (!isGridEditableCell(input)) return;

    const row = input.closest('tr');
    const table = row ? row.closest('table') : null;
    if (!row || !table) return;

    const rowCells = getRowCells(row);
    const cellIdx = rowCells.indexOf(input);
    if (cellIdx === -1) return;

    const allRows = getTableRows(table);
    const rowIdx = allRows.indexOf(row);
    if (rowIdx === -1) return;

    const isFirstCol = (cellIdx === 0);
    const isLastCol = (cellIdx === rowCells.length - 1);
    const isFirstRow = (rowIdx === 0);
    const isLastRow = (rowIdx === allRows.length - 1);

    const openDd = getOpenDropdown();

    // Escape: blur input if dropdown is closed
    if (e.key === 'Escape') {
      if (openDd) {
        return; // Let dropdown handler close it
      }
      e.preventDefault();
      input.blur();
      return;
    }

    // Textarea newline: Alt+Enter or Ctrl+Shift+Enter
    if (input.tagName === 'TEXTAREA' && ((e.altKey && e.key === 'Enter') || (e.ctrlKey && e.shiftKey && e.key === 'Enter'))) {
      e.preventDefault();
      const start = input.selectionStart || 0;
      const end = input.selectionEnd || 0;
      const val = input.value;
      input.value = val.substring(0, start) + "\n" + val.substring(end);
      input.selectionStart = input.selectionEnd = start + 1;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }

    // Ctrl+Enter: advance to column 1 of next row or append row
    if (e.ctrlKey && !e.shiftKey && !e.altKey && e.key === 'Enter') {
      e.preventDefault();
      if (!isLastRow) {
        const nextRow = allRows[rowIdx + 1];
        const nextCells = getRowCells(nextRow);
        if (nextCells.length) focusCell(nextCells[0]);
      } else {
        triggerAddRowAndFocus(table);
      }
      return;
    }

    // Shift+Enter: move to column 1 of previous row
    if (e.shiftKey && !e.ctrlKey && !e.altKey && e.key === 'Enter') {
      e.preventDefault();
      if (!isFirstRow) {
        const prevRow = allRows[rowIdx - 1];
        const prevCells = getRowCells(prevRow);
        if (prevCells.length) focusCell(prevCells[0]);
      }
      return;
    }

    // Alt+Delete: delete current row and refocus adjacent cell
    if (e.altKey && !e.ctrlKey && !e.shiftKey && (e.key === 'Delete' || e.key === 'Del')) {
      e.preventDefault();
      const tbody = table.querySelector('tbody') || table;
      const targetRow = (!isLastRow) ? allRows[rowIdx + 1] : (allRows.length > 1 ? allRows[rowIdx - 1] : null);
      const targetCells = targetRow ? getRowCells(targetRow) : null;
      const targetInput = targetCells ? targetCells[Math.min(cellIdx, targetCells.length - 1)] : null;

      const delBtn = row.querySelector(
        '.pc-del button, .rx-del button, .oh-del button, .del-row-btn, button[title*="Remove Row" i], button[title*="Delete" i]'
      );
      if (delBtn) {
        delBtn.click();
      } else {
        row.remove();
      }

      updateGridRowNumbers(tbody);
      tbody.dispatchEvent(new CustomEvent('zrx:reordered', { bubbles: true }));

      if (targetInput) {
        setTimeout(() => focusCell(targetInput), 20);
      }
      return;
    }

    // Alt+Shift+Up/Down: reorder row up or down preserving caret
    if (e.altKey && e.shiftKey && !e.ctrlKey) {
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!isFirstRow) {
          const tbody = table.querySelector('tbody') || table;
          const prevRow = allRows[rowIdx - 1];
          const caret = (typeof input.selectionStart === 'number') ? input.selectionStart : null;
          tbody.insertBefore(row, prevRow);
          updateGridRowNumbers(tbody);
          tbody.dispatchEvent(new CustomEvent('zrx:reordered', { bubbles: true }));
          input.focus({ preventScroll: true });
          if (caret !== null && typeof input.setSelectionRange === 'function') {
            try { input.setSelectionRange(caret, caret); } catch (_) {}
          }
        }
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!isLastRow) {
          const tbody = table.querySelector('tbody') || table;
          const nextRow = allRows[rowIdx + 1];
          const caret = (typeof input.selectionStart === 'number') ? input.selectionStart : null;
          tbody.insertBefore(nextRow, row);
          updateGridRowNumbers(tbody);
          tbody.dispatchEvent(new CustomEvent('zrx:reordered', { bubbles: true }));
          input.focus({ preventScroll: true });
          if (caret !== null && typeof input.setSelectionRange === 'function') {
            try { input.setSelectionRange(caret, caret); } catch (_) {}
          }
        }
        return;
      }
    }

    // Alt+Arrow: spatial navigation across grid cells
    if (e.altKey && !e.ctrlKey && !e.shiftKey) {
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!isFirstRow) {
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          const target = prevCells[Math.min(cellIdx, prevCells.length - 1)];
          focusCell(target);
        }
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!isLastRow) {
          const nextRow = allRows[rowIdx + 1];
          const nextCells = getRowCells(nextRow);
          const target = nextCells[Math.min(cellIdx, nextCells.length - 1)];
          focusCell(target);
        }
        return;
      }
      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        if (!isFirstCol) {
          focusCell(rowCells[cellIdx - 1]);
        } else if (!isFirstRow) {
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          if (prevCells.length) focusCell(prevCells[prevCells.length - 1]);
        }
        return;
      }
      if (e.key === 'ArrowRight') {
        e.preventDefault();
        if (!isLastCol) {
          focusCell(rowCells[cellIdx + 1]);
        } else if (!isLastRow) {
          const nextRow = allRows[rowIdx + 1];
          const nextCells = getRowCells(nextRow);
          if (nextCells.length) focusCell(nextCells[0]);
        }
        return;
      }
    }

    // Tab: advance to next cell or append row without selecting dropdown
    if (e.key === 'Tab' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
      e.preventDefault();
      // If a dropdown is currently open, close it without selecting anything
      if (openDd?.list) {
        openDd.list.classList.remove('show');
        openDd.list.style.display = 'none';
      }

      if (!isLastCol) {
        focusCell(rowCells[cellIdx + 1]);
      } else if (!isLastRow) {
        const nextRow = allRows[rowIdx + 1];
        const nextCells = getRowCells(nextRow);
        if (nextCells.length) focusCell(nextCells[0]);
      } else {
        triggerAddRowAndFocus(table);
      }
      return;
    }

    // Enter: select active dropdown item or advance to next cell
    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
      if (openDd?.activeItem) {
        // Let the autocomplete module's own keydown handler select the item and advance
        return;
      }

      e.preventDefault();
      if (!isLastCol) {
        focusCell(rowCells[cellIdx + 1]);
      } else if (!isLastRow) {
        const nextRow = allRows[rowIdx + 1];
        const nextCells = getRowCells(nextRow);
        if (nextCells.length) focusCell(nextCells[0]);
      } else {
        triggerAddRowAndFocus(table);
      }
      return;
    }

    // Shift+Tab: move to previous cell
    if (e.shiftKey && e.key === 'Tab' && !e.ctrlKey && !e.altKey) {
      e.preventDefault();
      if (openDd?.list) {
        openDd.list.classList.remove('show');
        openDd.list.style.display = 'none';
      }

      if (!isFirstCol) {
        focusCell(rowCells[cellIdx - 1]);
      } else if (!isFirstRow) {
        const prevRow = allRows[rowIdx - 1];
        const prevCells = getRowCells(prevRow);
        if (prevCells.length) focusCell(prevCells[prevCells.length - 1]);
      }
      return;
    }

    // Arrow Up/Down: navigate between rows when dropdown is closed
    if (!openDd && !e.altKey && !e.ctrlKey && !e.shiftKey) {
      if (e.key === 'ArrowUp') {
        if (!isFirstRow) {
          e.preventDefault();
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          const target = prevCells[Math.min(cellIdx, prevCells.length - 1)];
          focusCell(target);
          return;
        }
      }
      if (e.key === 'ArrowDown') {
        if (!isLastRow) {
          e.preventDefault();
          const nextRow = allRows[rowIdx + 1];
          const nextCells = getRowCells(nextRow);
          const target = nextCells[Math.min(cellIdx, nextCells.length - 1)];
          focusCell(target);
          return;
        }
      }
    }

    // Arrow Left/Right: navigate across columns at text boundaries
    if (!e.altKey && !e.shiftKey && !e.ctrlKey) {
      const len = input.value.length;
      const start = input.selectionStart;
      const end = input.selectionEnd;

      if (e.key === 'ArrowLeft' && start === 0 && end === 0) {
        e.preventDefault();
        if (!isFirstCol) {
          focusCell(rowCells[cellIdx - 1]);
        } else if (!isFirstRow) {
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          if (prevCells.length) focusCell(prevCells[prevCells.length - 1]);
        }
        return;
      }

      if (e.key === 'ArrowRight' && start === len && end === len) {
        e.preventDefault();
        if (!isLastCol) {
          focusCell(rowCells[cellIdx + 1]);
        } else if (!isLastRow) {
          const nextRow = allRows[rowIdx + 1];
          const nextCells = getRowCells(nextRow);
          if (nextCells.length) focusCell(nextCells[0]);
        }
        return;
      }
    }

    // Backspace: delete empty row at first column boundary
    if (e.key === 'Backspace' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
      if (isFirstCol && input.selectionStart === 0 && input.selectionEnd === 0) {
        const isBlankRow = rowCells.every((c) => !c.value.trim());
        if (isBlankRow && allRows.length > 1 && !isFirstRow) {
          e.preventDefault();
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          const targetCell = prevCells.length ? prevCells[prevCells.length - 1] : null;

          const delBtn = row.querySelector(
            '.pc-del button, .rx-del button, .oh-del button, .del-row-btn, button[title*="Remove Row" i], button[title*="Delete" i]'
          );
          if (delBtn) {
            delBtn.click();
          } else {
            row.remove();
          }

          if (targetCell) {
            setTimeout(() => focusCell(targetCell), 20);
          }
          return;
        }
      }
    }
  });
}

window.initializeUniversalGridNavigation = initializeUniversalGridNavigation;

initializeHelpGuidelineModals();
initializeUniversalColumnResize();
initializeUniversalGridNavigation();

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    initializeUniversalColumnResize();
    initializeUniversalGridNavigation();
  });
}

