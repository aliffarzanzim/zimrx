(() => {
    const payloadEl = document.getElementById('phrasePayloadData');
    const initial = payloadEl ? JSON.parse(payloadEl.textContent || '{}') : {};
    const type = initial.config.type;
    let state = initial;
    let editCategoryKey = null;
    let modalAdvices = [];
    let draggedCategoryKey = null;

    const body = document.getElementById('phrase-body');
    const status = document.getElementById('phrase-status');
    const search = document.getElementById('phrase-search');
    const field = document.getElementById('phrase-filter-field');
    const showCustom = document.getElementById('phrase-show-custom');
    const modal = document.getElementById('phrase-modal');
    
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    const norm = (value) => String(value ?? '').toLowerCase().trim();

    const setStatus = (text) => {
        status.textContent = text || '';
        if (text) window.setTimeout(() => { if (status.textContent === text) status.textContent = ''; }, 1800);
    };

    const api = async (payload = null) => {
        const options = payload ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type, ...payload }) } : {};
        const response = await fetch(`api/rx_template.php?type=${encodeURIComponent(type)}`, options);
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        state = data;
        render();
        return data;
    };

    const filteredRows = () => {
        const q = norm(search.value);
        const f = field.value;
        return state.rows.filter(row => {
            if (!q) return true;
            const values = [];
            if (f === 'all' || f === 'category') values.push(row.category_bn, row.category_en, row.category_search_alias);
            if (f === 'all' || f === 'bn') values.push(row.value_bn);
            if (f === 'all' || f === 'en') values.push(row.value_en);
            return values.some(value => norm(value).includes(q));
        });
    };

    const groupRowsByCategory = (rows) => {
        const groups = [];
        const groupMap = new Map();

        rows.forEach(row => {
            const catKey = (row.category_en || row.category_bn || 'Uncategorized').trim();
            if (!groupMap.has(catKey)) {
                const group = {
                    key: catKey,
                    category_en: row.category_en || '',
                    category_bn: row.category_bn || '',
                    category_search_alias: row.category_search_alias || '',
                    is_pinned: 0,
                    is_hidden: 1,
                    sort_order: row.sort_order,
                    advices: [],
                    kinds: new Set(),
                };
                groupMap.set(catKey, group);
                groups.push(group);
            }
            const group = groupMap.get(catKey);
            group.advices.push(row);
            group.kinds.add(row.kind);
            if (Number(row.is_pinned) === 1) {
                group.is_pinned = 1;
            }
            if (Number(row.is_hidden) === 0) {
                group.is_hidden = 0;
            }
            if (row.sort_order < group.sort_order) {
                group.sort_order = row.sort_order;
            }
        });

        if (state.settings.show_mode === 'usage') {
            groups.sort((a, b) => {
                if (a.is_pinned !== b.is_pinned) {
                    return b.is_pinned - a.is_pinned;
                }
                const aMaxUsage = Math.max(...a.advices.map(ad => ad.usage_count), 0);
                const bMaxUsage = Math.max(...b.advices.map(ad => ad.usage_count), 0);
                if (bMaxUsage !== aMaxUsage) {
                    return bMaxUsage - aMaxUsage;
                }
                return a.sort_order - b.sort_order;
            });
        } else {
            groups.sort((a, b) => {
                if (a.is_pinned !== b.is_pinned) {
                    return b.is_pinned - a.is_pinned;
                }
                return a.sort_order - b.sort_order;
            });
        }

        return groups;
    };

    const render = () => {
        document.querySelectorAll('[data-mode]').forEach(btn => btn.classList.toggle('active', btn.dataset.mode === state.settings.show_mode));
        showCustom.checked = Number(state.settings.show_custom_typed) === 1;

        const rows = filteredRows();
        const groups = groupRowsByCategory(rows);

        let html = '';
        let categoryIndex = 0;
        groups.forEach((group) => {
            const numAdvices = group.advices.length;
            const categoryTags = [];
            group.kinds.forEach(kind => {
                if (kind === 'system') categoryTags.push('<span class="phrase-tag system">System</span>');
                if (kind === 'custom_typed') categoryTags.push('<span class="phrase-tag custom">Custom Typed</span>');
                if (kind === 'added') categoryTags.push('<span class="phrase-tag added">Added</span>');
            });
            if (group.is_hidden) {
                categoryTags.push('<span class="phrase-tag hidden">Hidden</span>');
            }
            const categoryUsage = group.advices.reduce((sum, ad) => sum + Number(ad.usage_count), 0);

            group.advices.forEach((row, adviceIndex) => {
                const isFirst = (adviceIndex === 0);
                const rowClass = `phrase-row ${row.is_hidden ? 'hidden' : ''} ${row.is_pinned ? 'pinned' : ''}`;
                
                html += `<tr class="${rowClass}" data-category-key="${esc(group.key)}" data-advice-id="${row.id}" data-static-id="${row.static_id}">`;

                if (isFirst) {
                    const pinLabel = group.is_pinned ? 'Unpin' : 'Pin it';
                    const hideLabel = group.is_hidden ? 'Restore' : 'Remove';
                    html += `
                        <td class="phrase-handle category-handle" rowspan="${numAdvices}" title="Drag to reorder Category" draggable="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                            ${group.is_pinned ? '<img class="phrase-handle-pin" src="assets/images/pin.svg" alt="Pinned">' : ''}
                        </td>
                        <td rowspan="${numAdvices}" style="text-align:center;">
                            <div class="phrase-actions">
                                <button class="phrase-pin-btn btn-category-pin ${group.is_pinned ? 'active' : ''}" data-category-key="${esc(group.key)}" title="${group.is_pinned ? 'Unpin this category' : 'Pin this category to the top'}">
                                    ${pinLabel}
                                </button>
                                <button class="phrase-row-btn btn-category-edit" data-category-key="${esc(group.key)}">Edit</button>
                                <button class="phrase-row-btn toggle-hidden btn-category-hide ${group.is_hidden ? 'is-hidden' : ''}" data-category-key="${esc(group.key)}">${hideLabel}</button>
                                <button class="phrase-row-btn btn-category-reset" data-category-key="${esc(group.key)}">Reset</button>
                            </div>
                        </td>
                        <td class="phrase-sl" rowspan="${numAdvices}">${group.is_pinned ? '<div style="display:flex; align-items:center; justify-content:center; gap:0.25rem;"><span style="color:#b45309;">&#x25F3;</span><span>' + (categoryIndex + 1) + '</span></div>' : (categoryIndex + 1)}</td>
                        <td rowspan="${numAdvices}"><div class="phrase-tags">${categoryTags.join('')}</div></td>
                        <td rowspan="${numAdvices}"><div class="phrase-text" style="font-weight:700;">${esc(group.category_en || group.category_bn)}</div></td>
                    `;
                }

                html += `
                    <td><div class="phrase-text">${esc(row.value_bn)}</div></td>
                    <td><div class="phrase-text">${esc(row.value_en)}</div></td>
                `;

                if (isFirst) {
                    html += `<td class="phrase-usage" rowspan="${numAdvices}">${categoryUsage}</td>`;
                }

                html += `</tr>`;
            });
            categoryIndex++;
        });

        body.innerHTML = html;
        setupDragAndDrop();
    };

    const setupDragAndDrop = () => {
        body.addEventListener('dragstart', (e) => {
            const handle = e.target.closest('.category-handle');
            if (!handle) {
                e.preventDefault();
                return;
            }
            const tr = handle.closest('tr');
            draggedCategoryKey = tr.dataset.categoryKey;
            tr.classList.add('category-dragging');
        });

        body.addEventListener('dragover', (e) => {
            e.preventDefault();
            const tr = e.target.closest('tr');
            if (!tr) return;
            const targetCategoryKey = tr.dataset.categoryKey;
            if (!targetCategoryKey || targetCategoryKey === draggedCategoryKey) return;
            
            body.querySelectorAll('tr').forEach(row => {
                row.classList.toggle('drop-target', row.dataset.categoryKey === targetCategoryKey);
            });
        });

        body.addEventListener('dragleave', (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;
            const relatedTr = e.relatedTarget ? e.relatedTarget.closest('tr') : null;
            if (relatedTr && relatedTr.dataset.categoryKey === tr.dataset.categoryKey) {
                return;
            }
            body.querySelectorAll('.drop-target').forEach(row => row.classList.remove('drop-target'));
        });

        body.addEventListener('drop', async (e) => {
            e.preventDefault();
            body.querySelectorAll('.drop-target').forEach(row => row.classList.remove('drop-target'));
            const tr = e.target.closest('tr');
            if (!tr) return;
            const targetCategoryKey = tr.dataset.categoryKey;
            if (!targetCategoryKey || targetCategoryKey === draggedCategoryKey) return;

            const groups = groupRowsByCategory(state.rows);
            const draggedIndex = groups.findIndex(g => g.key === draggedCategoryKey);
            const targetIndex = groups.findIndex(g => g.key === targetCategoryKey);
            if (draggedIndex === -1 || targetIndex === -1) return;

            const [draggedGroup] = groups.splice(draggedIndex, 1);
            groups.splice(targetIndex, 0, draggedGroup);

            const newRows = [];
            let order = 1;
            groups.forEach(g => {
                g.advices.forEach(ad => {
                    ad.sort_order = order++;
                    newRows.push(ad);
                });
            });

            state.rows = newRows;
            render();
            await api({ action: 'save_all', rows: state.rows, settings: state.settings });
            setStatus('Category order saved');
        });

        body.addEventListener('dragend', () => {
            body.querySelectorAll('.category-dragging').forEach(row => row.classList.remove('category-dragging'));
            body.querySelectorAll('.drop-target').forEach(row => row.classList.remove('drop-target'));
            draggedCategoryKey = null;
        });
    };

    const syncModalAdvicesFromDom = () => {
        const rowsInDom = Array.from(document.querySelectorAll('.modal-advice-row'));
        rowsInDom.forEach((rowDom) => {
            const originalIndex = parseInt(rowDom.dataset.index);
            const advice = modalAdvices[originalIndex];
            if (!advice) return;
            advice.value_bn = rowDom.querySelector('.advice-bn-input').value.trim();
            advice.value_en = rowDom.querySelector('.advice-en-input').value.trim();
        });
    };

    let draggedAdviceIndex = null;
    const setupModalDragAndDrop = () => {
        const container = document.getElementById('modal-advices-container');
        if (!container) return;

        container.addEventListener('mousedown', (e) => {
            const handle = e.target.closest('.advice-row-drag-handle');
            const row = e.target.closest('.modal-advice-row');
            if (row) {
                row.draggable = !!handle;
            }
        });

        container.addEventListener('dragstart', (e) => {
            const row = e.target.closest('.modal-advice-row');
            if (!row || !row.draggable) {
                e.preventDefault();
                return;
            }
            syncModalAdvicesFromDom();
            draggedAdviceIndex = parseInt(row.dataset.index);
            row.style.opacity = '0.4';
        });

        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            const row = e.target.closest('.modal-advice-row');
            if (!row) return;
            const targetIndex = parseInt(row.dataset.index);
            if (targetIndex === draggedAdviceIndex) return;
            
            container.querySelectorAll('.modal-advice-row').forEach(r => r.style.borderTop = '');
            row.style.borderTop = '2px solid #2563eb';
        });

        container.addEventListener('dragleave', (e) => {
            const row = e.target.closest('.modal-advice-row');
            if (row) {
                row.style.borderTop = '';
            }
        });

        container.addEventListener('drop', (e) => {
            e.preventDefault();
            const row = e.target.closest('.modal-advice-row');
            if (!row) return;
            const targetIndex = parseInt(row.dataset.index);
            
            if (targetIndex !== draggedAdviceIndex) {
                const [draggedItem] = modalAdvices.splice(draggedAdviceIndex, 1);
                modalAdvices.splice(targetIndex, 0, draggedItem);
                renderModalAdvices();
            }
        });

        container.addEventListener('dragend', (e) => {
            container.querySelectorAll('.modal-advice-row').forEach(r => {
                r.style.borderTop = '';
                r.style.opacity = '1';
                r.draggable = false;
            });
            draggedAdviceIndex = null;
        });
    };

    const renderModalAdvices = () => {
        const container = document.getElementById('modal-advices-container');
        
        container.innerHTML = modalAdvices.map((advice, originalIndex) => {
            if (Number(advice.is_hidden) === 1) return '';
            
            return `
                <div class="modal-advice-row" data-index="${originalIndex}">
                    <!-- Drag Handle -->
                    <div class="advice-row-drag-handle" style="cursor: grab; display: flex; align-items: center; justify-content: center; color: #94a3b8; user-select: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="12" r="1"></circle><circle cx="9" cy="5" r="1"></circle><circle cx="9" cy="19" r="1"></circle>
                            <circle cx="15" cy="12" r="1"></circle><circle cx="15" cy="5" r="1"></circle><circle cx="15" cy="19" r="1"></circle>
                        </svg>
                    </div>
                    
                    <!-- Bangla Input -->
                    <div>
                        <textarea class="advice-bn-input modal-advice-textarea" placeholder="Advice Bangla" rows="1">${esc(advice.value_bn)}</textarea>
                    </div>

                    <!-- English Input -->
                    <div>
                        <textarea class="advice-en-input modal-advice-textarea" placeholder="Advice English" rows="1">${esc(advice.value_en)}</textarea>
                    </div>

                    <!-- Delete Button -->
                    <div style="display: flex; justify-content: center;">
                        <button type="button" class="phrase-modal-btn btn-advice-delete-row" style="width: 32px; height: 32px; min-height: unset; padding: 0; border-radius: 6px; display: flex; align-items: center; justify-content: center; border-width: 1px; border-style: solid; border-color: #fecaca; background: #fff1f2; color: #b91c1c; cursor: pointer;" title="Delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        setupModalDragAndDrop();
        
        setTimeout(() => {
            const container = document.getElementById('modal-advices-container');
            container.querySelectorAll('.modal-advice-textarea').forEach(ta => {
                ta.style.height = '';
                ta.style.height = Math.max(38, ta.scrollHeight) + 'px';
            });
        }, 10);
    };

    const openModal = (categoryKey = null) => {
        editCategoryKey = categoryKey;
        document.getElementById('phrase-modal-title').textContent = categoryKey ? 'Edit Category & Advices' : 'Add New Category';
        
        if (categoryKey) {
            const firstMatch = state.rows.find(row => (row.category_en || row.category_bn || 'Uncategorized').trim() === categoryKey);
            document.getElementById('modal-category-bn').value = firstMatch?.category_bn || '';
            document.getElementById('modal-category-en').value = firstMatch?.category_en || '';
            document.getElementById('modal-category-alias').value = firstMatch?.category_search_alias || '';
            
            modalAdvices = state.rows
                .filter(row => (row.category_en || row.category_bn || 'Uncategorized').trim() === categoryKey)
                .map(row => ({ ...row })); // Clone objects
        } else {
            document.getElementById('modal-category-bn').value = '';
            document.getElementById('modal-category-en').value = '';
            document.getElementById('modal-category-alias').value = '';
            modalAdvices = [{
                id: 0,
                static_id: 99,
                doctor_id: state.rows[0]?.doctor_id || 1,
                value_bn: '',
                value_en: '',
                is_pinned: 0,
                is_hidden: 0,
                usage_count: 0,
                kind: 'added',
                category_bn: '',
                category_en: '',
                category_search_alias: ''
            }];
        }
        
        renderModalAdvices();
        modal.hidden = false;
        
        // Trigger resize on textareas
        setTimeout(() => {
            modal.querySelectorAll('.modal-advice-textarea').forEach(ta => {
                ta.style.height = '';
                ta.style.height = Math.max(38, ta.scrollHeight) + 'px';
            });
        }, 10);
    };

    const closeModal = () => {
        modal.hidden = true;
        editCategoryKey = null;
        modalAdvices = [];
    };

    document.getElementById('phrase-add').addEventListener('click', () => openModal());
    document.getElementById('phrase-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });

    modal.addEventListener('input', (e) => {
        if (e.target.matches('.modal-advice-textarea')) {
            e.target.style.height = '';
            e.target.style.height = Math.max(38, e.target.scrollHeight) + 'px';
        }
    });

    // Handle clicks inside the modal advices container (Delete)
    document.getElementById('modal-advices-container').addEventListener('click', (event) => {
        const delBtn = event.target.closest('.btn-advice-delete-row');
        
        if (!delBtn) return;
        
        const rowDom = event.target.closest('.modal-advice-row');
        const index = parseInt(rowDom.dataset.index);
        
        if (delBtn) {
            syncModalAdvicesFromDom();
            const advice = modalAdvices[index];
            if (advice) {
                if (advice.kind === 'system') {
                    advice.is_hidden = 1;
                } else {
                    modalAdvices.splice(index, 1);
                }
                renderModalAdvices();
            }
        }
    });

    document.getElementById('modal-add-advice-btn').addEventListener('click', () => {
        syncModalAdvicesFromDom();
        const isPinned = modalAdvices.some(ad => Number(ad.is_pinned) === 1) ? 1 : 0;
        modalAdvices.push({
            id: 0,
            static_id: 99,
            doctor_id: state.rows[0]?.doctor_id || 1,
            value_bn: '',
            value_en: '',
            is_pinned: isPinned,
            is_hidden: 0,
            usage_count: 0,
            kind: 'added',
            category_bn: document.getElementById('modal-category-bn').value.trim(),
            category_en: document.getElementById('modal-category-en').value.trim()
        });
        renderModalAdvices();
    });

    document.getElementById('phrase-save').addEventListener('click', async () => {
        syncModalAdvicesFromDom();
        const categoryBn = document.getElementById('modal-category-bn').value.trim();
        const categoryEn = document.getElementById('modal-category-en').value.trim();
        const categoryAlias = document.getElementById('modal-category-alias').value.trim();
        
        if (!categoryBn && !categoryEn) {
            alert('Please specify Category Bangla or Category English.');
            return;
        }
        
        if (modalAdvices.length === 0) {
            alert('Please add at least one advice.');
            return;
        }
        
        modalAdvices.forEach(ad => {
            ad.category_bn = categoryBn;
            ad.category_en = categoryEn;
            ad.name = categoryEn;
            ad.category_search_alias = categoryAlias;
        });

        if (editCategoryKey) {
            let insertIndex = state.rows.findIndex(r => (r.category_en || r.category_bn || 'Uncategorized').trim() === editCategoryKey);
            if (insertIndex === -1) {
                insertIndex = state.rows.length;
            }
            
            // Remove old advices
            state.rows = state.rows.filter(r => (r.category_en || r.category_bn || 'Uncategorized').trim() !== editCategoryKey);
            
            // Insert updated ones
            state.rows.splice(insertIndex, 0, ...modalAdvices);
        } else {
            state.rows.push(...modalAdvices);
        }

        // Reassign sort orders
        state.rows.forEach((r, idx) => {
            r.sort_order = idx + 1;
        });

        setStatus('Saving...');
        try {
            await api({ action: 'save_all', rows: state.rows, settings: state.settings });
            closeModal();
            setStatus('Saved');
        } catch (e) {
            setStatus('Error: ' + e.message);
        }
    });

    body.addEventListener('click', async event => {
        const editButton = event.target.closest('.btn-category-edit');
        if (editButton) {
            const categoryKey = editButton.dataset.categoryKey;
            if (categoryKey) {
                openModal(categoryKey);
            }
            return;
        }

        const pinBtn = event.target.closest('.btn-category-pin');
        if (pinBtn) {
            const categoryKey = pinBtn.dataset.categoryKey;
            const categoryAdvices = state.rows.filter(r => (r.category_en || r.category_bn || 'Uncategorized').trim() === categoryKey);
            if (categoryAdvices.length > 0) {
                const anyUnpinned = categoryAdvices.some(r => Number(r.is_pinned) === 0);
                const targetPin = anyUnpinned ? 1 : 0;
                categoryAdvices.forEach(r => r.is_pinned = targetPin);
                
                setStatus('Saving...');
                await api({ action: 'save_all', rows: state.rows, settings: state.settings });
                setStatus('Saved');
            }
            return;
        }

        const hideBtn = event.target.closest('.btn-category-hide');
        if (hideBtn) {
            const categoryKey = hideBtn.dataset.categoryKey;
            const categoryAdvices = state.rows.filter(r => (r.category_en || r.category_bn || 'Uncategorized').trim() === categoryKey);
            if (categoryAdvices.length > 0) {
                const anyVisible = categoryAdvices.some(r => Number(r.is_hidden) === 0);
                const targetHidden = anyVisible ? 1 : 0;
                categoryAdvices.forEach(r => r.is_hidden = targetHidden);
                
                setStatus('Saving...');
                await api({ action: 'save_all', rows: state.rows, settings: state.settings });
                setStatus('Saved');
            }
            return;
        }

        const resetBtn = event.target.closest('.btn-category-reset');
        if (resetBtn) {
            if (!confirm('Reset this category? This cannot be reversed.')) return;
            const categoryKey = resetBtn.dataset.categoryKey;
            const categoryAdvices = state.rows.filter(r => (r.category_en || r.category_bn || 'Uncategorized').trim() === categoryKey);
            if (categoryAdvices.length > 0) {
                categoryAdvices.forEach(row => {
                    if (row.static_id > 0 && row.static_id !== 99 && state.default_rows) {
                        const defaultRow = state.default_rows.find(d => d.id === row.static_id);
                        if (defaultRow) {
                            row.value_bn = defaultRow.body || defaultRow.value_bn || '';
                            row.value_en = defaultRow.advice_en || defaultRow.value_en || '';
                            row.category_bn = defaultRow.category_bn || '';
                            row.category_en = defaultRow.category_en || defaultRow.name || '';
                            row.name = row.category_en;
                        }
                    }
                    row.is_pinned = 0;
                    row.is_hidden = 0;
                    row.usage_count = 0;
                    row.is_edited = 0;
                });
                
                setStatus('Resetting...');
                await api({ action: 'save_all', rows: state.rows, settings: state.settings });
                setStatus('Saved');
            }
            return;
        }
    });

    document.querySelectorAll('[data-bulk]').forEach(button => button.addEventListener('click', async () => {
        if (!confirm('This action cannot be reversed. Continue?')) return;
        await api({ action: button.dataset.bulk });
        setStatus('Saved');
    }));

    document.querySelectorAll('[data-mode]').forEach(button => button.addEventListener('click', async () => {
        state.settings.show_mode = button.dataset.mode;
        await api({ action: 'save_settings', settings: state.settings });
        setStatus('Saved');
    }));

    showCustom.addEventListener('change', async () => {
        state.settings.show_custom_typed = showCustom.checked ? 1 : 0;
        await api({ action: 'save_settings', settings: state.settings });
        setStatus('Saved');
    });

    document.getElementById('phrase-reset-settings').addEventListener('click', async () => {
        state.settings = { show_mode: 'serial', show_custom_typed: 1 };
        await api({ action: 'save_settings', settings: state.settings });
        setStatus('Saved');
    });

    search.addEventListener('input', render);
    field.addEventListener('change', render);
    
    render();
})();
