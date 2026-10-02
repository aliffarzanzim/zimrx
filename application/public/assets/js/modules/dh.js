// Drug history (DH) module: row insertion, textarea auto-sizing, and dual generic/brand autocomplete.
(function() {
    const wrapper = document.getElementById('dh-wrapper');
    const tbody = document.getElementById('dh-tbody');
    const template = document.getElementById('dh-row-template');
    const addBtn = wrapper.querySelector('.dh-add-row-btn');

    // Row management and sequencing
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

    // Textarea auto-resize
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

    // Drug autocomplete search
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

                        // Extract unique generics from results
                        const generics = new Set();
                        data.forEach(item => {
                            if (item.generic) generics.add(item.generic);
                        });

                        const _esc = (v) => ZimRxDropdown ? ZimRxDropdown.escapeHtml(v) : String(v || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        // Render generic suggestions first
                        generics.forEach(generic => {
                            const li = document.createElement('li');
                            li.className = 'patient-lookup-option';
                            li.innerHTML = `<strong>${_esc(generic)}</strong><span class="meta">Generic</span>`;
                            li.addEventListener('mousedown', (ev) => {
                                ev.preventDefault();
                                input.value = generic;
                                autoResizeTextarea(input);
                                closeDropdown();
                            });
                            ul.appendChild(li);
                        });

                        // Render brand suggestions
                        data.forEach((item) => {
                            const li = document.createElement('li');
                            li.className = 'patient-lookup-option';
                            const man = item.man_short || item.manufacturer || '';
                            const _e = (v) => ZimRxDropdown.escapeHtml(v);
                            li.innerHTML = `<strong>${_e(item.pres_new_upper)}</strong><span class="meta">${_e(item.generic)} | ${_e(man)}</span>`;

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
