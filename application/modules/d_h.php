<div class="pc-wrapper" id="dh-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="dh-table">
            <colgroup>
                <col style="width: 32px;">
                <col style="width: 42px;">
                <col>
                <col style="width: 38px;">
            </colgroup>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;"></th>
                    <th style="width: 42px; text-align: center;">#</th>
                    <th>
                        <div class="pc-header-flex" style="width: 100%;">
                            <span>D/H (Drug History)</span>
                        </div>
                    </th>
                    <th style="width: 38px; text-align: center;"></th>
                </tr>
            </thead>
            <tbody id="dh-tbody">
                <?php for($i=1; $i<=5; $i++): ?>
                <tr class="pc-row" draggable="true">
                    <td class="pc-action pc-drag">
                        <button type="button" class="pc-row-move-btn" title="Move Row">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                        </button>
                    </td>
                    <td class="pc-row-no"><?= $i ?></td>
                    <td>
                        <textarea class="pc-input dh-input" autocomplete="off" rows="1"></textarea>
                    </td>
                    <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="pc-footer">
        <button type="button" class="pc-add-row-btn dh-add-row-btn">Add More</button>
    </div>

    <!-- Template for new rows -->
    <template id="dh-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-drag">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                </button>
            </td>
            <td class="pc-row-no"></td>
            <td>
                <textarea class="pc-input dh-input" autocomplete="off" rows="1"></textarea>
            </td>
            <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
        </tr>
    </template>
</div>

<script>
(function() {
    const wrapper = document.getElementById('dh-wrapper');
    const tbody = document.getElementById('dh-tbody');
    const template = document.getElementById('dh-row-template');
    const addBtn = wrapper.querySelector('.dh-add-row-btn');

    // --- Row Management ---
    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            const noCell = row.querySelector('.pc-row-no');
            if (noCell) noCell.textContent = index + 1;
        });
    }

    addBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const tr = template.content.firstElementChild.cloneNode(true);
        tbody.appendChild(tr);
        updateRowNumbers();

        const textarea = tr.querySelector('.dh-input');
        if (textarea) {
            autoResizeTextarea(textarea);
            textarea.focus();
        }
    });

    wrapper.addEventListener('click', (e) => {
        const delBtn = e.target.closest('.pc-del button');
        if (delBtn) {
            e.stopPropagation();
            const row = delBtn.closest('tr');
            row.remove();
            updateRowNumbers();
        }
    });

    // --- Textarea Auto-Resize ---
    function autoResizeTextarea(textarea) {
        if (!textarea || textarea.tagName !== 'TEXTAREA') return;
        textarea.style.transition = 'none';
        textarea.style.height = '0';
        const natural = Math.max(36, textarea.scrollHeight);
        textarea.style.height = natural + 'px';
        requestAnimationFrame(() => { textarea.style.transition = ''; });
    }

    document.querySelectorAll('#dh-table textarea').forEach(autoResizeTextarea);

    wrapper.addEventListener('input', (e) => {
        if (e.target.tagName === 'TEXTAREA') {
            autoResizeTextarea(e.target);
        }
    });

    // --- Drug Autocomplete ---
    let debounceTimer;
    let activeDropdown = null;

    function closeDropdown() {
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.autocomplete-list') && !e.target.classList.contains('dh-input')) {
            closeDropdown();
        }
    });

    wrapper.addEventListener('input', (e) => {
        if (e.target.classList.contains('dh-input')) {
            const input = e.target;
            const query = input.value.trim();

            clearTimeout(debounceTimer);
            if (query.length < 2) {
                closeDropdown();
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch('api/search_drug.php?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        closeDropdown();
                        if (!data || data.error || data.length === 0) return;

                        const ul = document.createElement('ul');
                        ul.className = 'autocomplete-list show appointment-lookup-list';
                        ul.style.position = 'absolute';
                        ul.style.width = input.offsetWidth + 'px';
                        ul.style.zIndex = '1000';

                        const rect = input.getBoundingClientRect();
                        ul.style.top = (rect.bottom + window.scrollY) + 'px';
                        ul.style.left = (rect.left + window.scrollX) + 'px';

                        // 1. Extract Unique Generics from results
                        const generics = new Set();
                        data.forEach(item => {
                            if (item.generic) generics.add(item.generic);
                        });

                        // 2. Add Generic Options first
                        generics.forEach(generic => {
                            const li = document.createElement('li');
                            li.className = 'patient-lookup-option';
                            li.innerHTML = `<strong>${generic}</strong><span class="meta">Generic</span>`;
                            li.addEventListener('mousedown', (ev) => {
                                ev.preventDefault();
                                input.value = generic;
                                autoResizeTextarea(input);
                                closeDropdown();
                            });
                            ul.appendChild(li);
                        });

                        // 3. Add Brand Options
                        data.forEach((item) => {
                            const li = document.createElement('li');
                            li.className = 'patient-lookup-option';
                            const man = item.man_short || item.manufacturer || '';
                            li.innerHTML = `<strong>${item.pres_new_upper}</strong><span class="meta">${item.generic} | ${man}</span>`;

                            li.addEventListener('mousedown', (ev) => {
                                ev.preventDefault();
                                // Format: Generic (Brand)
                                let val = '';
                                if (item.generic) {
                                    val = `${item.generic} (${item.pres_new_upper})`;
                                } else {
                                    val = item.pres_new_upper;
                                }
                                input.value = val;
                                autoResizeTextarea(input);
                                closeDropdown();
                            });
                            ul.appendChild(li);
                        });

                        // Set first item active
                        if (ul.firstElementChild) {
                            ul.firstElementChild.classList.add('active');
                        }

                        document.body.appendChild(ul);
                        activeDropdown = ul;

                        const updatePos = () => {
                            if(activeDropdown) {
                                const r = input.getBoundingClientRect();
                                activeDropdown.style.top = (r.bottom + window.scrollY) + 'px';
                                activeDropdown.style.left = (r.left + window.scrollX) + 'px';
                            }
                        };
                        window.addEventListener('scroll', updatePos, {passive: true});
                    })
                    .catch(err => console.error('Drug Search Error:', err));
            }, 300);
        }
    });

    // Keyboard navigation
    wrapper.addEventListener('keydown', (e) => {
        if (e.target.classList.contains('dh-input') && activeDropdown) {
            const items = activeDropdown.querySelectorAll('li');
            let activeIdx = Array.from(items).findIndex(item => item.classList.contains('active'));

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (activeIdx > -1) items[activeIdx].classList.remove('active');
                activeIdx = (activeIdx + 1) % items.length;
                items[activeIdx].classList.add('active');
                items[activeIdx].scrollIntoView({block: 'nearest'});
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (activeIdx > -1) items[activeIdx].classList.remove('active');
                activeIdx = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
                items[activeIdx].classList.add('active');
                items[activeIdx].scrollIntoView({block: 'nearest'});
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIdx > -1) items[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        }
    });
})();
</script>
