// Paediatric history module controller.

function initPaediatricModule() {
  if (!document.getElementById('ph-module-wrapper')) {
    return;
  }
  if (window.__zimrxPaediatricInitialized) {
    return;
  }
  window.__zimrxPaediatricInitialized = true;

  const getPhTableBody = () => document.getElementById('ph-table-body');

  // Row numbering and reordering
  function updatePhRowNumbers() {
    const rows = getPhTableBody()?.querySelectorAll('tr.oh-row') || [];
    rows.forEach((row, index) => {
      const numCell = row.querySelector('.oh-row-no');
      if (numCell) numCell.textContent = String(index + 1);
    });
  }

  function bindPhRowEvents(row) {
    const delBtn = row.querySelector('.oh-del button');
    if (delBtn) {
      delBtn.onclick = (e) => {
        e.preventDefault();
        row.remove();
        updatePhRowNumbers();
      };
    }

    // Quick select vs custom text input
    const quickSelect = row.querySelector('.ph-quick-select');
    const customInput = row.querySelector('.ph-custom-input');
    if (quickSelect && customInput) {
      quickSelect.addEventListener('change', () => {
        if (quickSelect.value === 'custom') {
          customInput.hidden = false;
          customInput.value = '';
          customInput.focus();
        } else {
          customInput.hidden = true;
          customInput.value = quickSelect.value;
        }
      });
    }
  }

  // Add row handler
  const addRowBtn = document.getElementById('ph-add-row-btn');
  const rowTemplate = document.getElementById('ph-row-template');
  if (addRowBtn && rowTemplate) {
    addRowBtn.addEventListener('click', () => {
      const clone = rowTemplate.content.cloneNode(true);
      const newRow = clone.querySelector('tr');
      bindPhRowEvents(newRow);
      getPhTableBody()?.appendChild(newRow);
      updatePhRowNumbers();
      newRow.querySelector('.oh-name-input')?.focus();
    });
  }

  // Bind initial rows
  getPhTableBody()?.querySelectorAll('tr.oh-row').forEach(bindPhRowEvents);
  updatePhRowNumbers();

}

// Boot initialization
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPaediatricModule);
} else {
  initPaediatricModule();
}
