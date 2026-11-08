function initOccupationSettings(overlay, occUi) {
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

window.ZimRxOccupationSettings = { initManager: initOccupationSettings };
