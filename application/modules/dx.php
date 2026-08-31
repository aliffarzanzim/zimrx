<div class="pc-wrapper" id="dx-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="dx-table">
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
                        <div class="pc-header-flex">
                            <span>Dx</span>
                        </div>
                    </th>
                    <th style="width: 38px; text-align: center;"></th>
                </tr>
            </thead>
            <tbody id="dx-tbody">
                <?php for($i=1; $i<=5; $i++): ?>
                <tr class="pc-row" draggable="true">
                    <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="pc-row-no"><?= $i ?></td>
                    <td>
                        <textarea class="pc-input dx-input" autocomplete="off" rows="1" placeholder=""></textarea>
                        <input type="hidden" class="dx-id-input" name="dx_id[]">
                    </td>
                    <td class="pc-action pc-drag">
                        <button type="button" class="pc-row-move-btn" title="Move Row">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="pc-footer">
        <button type="button" class="pc-add-row-btn dx-add-row-btn">Add More</button>
    </div>

    <!-- Template for new rows -->
    <template id="dx-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td>
                <textarea class="pc-input dx-input" autocomplete="off" rows="1" placeholder=""></textarea>
                <input type="hidden" class="dx-id-input" name="dx_id[]">
            </td>
            <td class="pc-action pc-drag">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<script>
(function() {
    const wrapper = document.getElementById('dx-wrapper');
    const tbody = document.getElementById('dx-tbody');
    const template = document.getElementById('dx-row-template');
    const addBtn = wrapper.querySelector('.dx-add-row-btn');

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

        const textarea = tr.querySelector('.dx-input');
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

    document.querySelectorAll('#dx-table textarea').forEach(autoResizeTextarea);

    wrapper.addEventListener('input', (e) => {
        if (e.target.tagName === 'TEXTAREA') {
            autoResizeTextarea(e.target);
        }
    });

    // --- Static Dx Autocomplete ---
    let debounceTimer;
    let activeDropdown = null;
    let activeDropdownScrollHandler = null;
    let activeAbortController = null;
    let dxRequestId = 0;
    const dxSearchCache = new Map();

    function closeDropdown() {
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
        if (activeDropdownScrollHandler) {
            window.removeEventListener('scroll', activeDropdownScrollHandler);
            activeDropdownScrollHandler = null;
        }
    }

    function abortDxSearch() {
        dxRequestId++;
        if (activeAbortController) {
            activeAbortController.abort();
            activeAbortController = null;
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function positionDropdown(dropdown, input) {
        const anchor = input?.closest('td') || input;
        const rect = anchor.getBoundingClientRect();
        dropdown.style.position = 'absolute';
        dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        dropdown.style.left = (rect.left + window.scrollX) + 'px';
        dropdown.style.minWidth = rect.width + 'px';
        dropdown.style.zIndex = '9999';
    }

    function selectDxItem(input, item) {
        const row = input.closest('tr');
        const idInput = row ? row.querySelector('.dx-id-input') : null;
        input.value = item.dx_short || item.search_term || '';
        if (idInput) idInput.value = item.id || '';
        autoResizeTextarea(input);
        closeDropdown();
    }

    function renderDxDropdown(input, data) {
        closeDropdown();
        if (!data || data.error || data.length === 0) return;

        const ul = document.createElement('ul');
        ul.className = 'zrx-dropdown rx-dropdown dx-dropdown show';
        positionDropdown(ul, input);

        data.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'zrx-dropdown-item rx-dropdown-item' + (index === 0 ? ' active' : '');
            
            const shortName = item.dx_short || item.search_term || '';
            li.innerHTML = `<div style="padding:2px 0; width:100%;"><strong>${escapeHtml(shortName)}</strong></div>`;
            li.addEventListener('mouseenter', () => {
                const allItems = ul.querySelectorAll('.zrx-dropdown-item');
                allItems.forEach(el => el.classList.remove('active'));
                li.classList.add('active');
            });
            li.addEventListener('mousedown', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                selectDxItem(input, item);
            });
            ul.appendChild(li);
        });

        document.body.appendChild(ul);
        activeDropdown = ul;
        activeDropdownScrollHandler = () => {
            if (activeDropdown) positionDropdown(activeDropdown, input);
        };
        window.addEventListener('scroll', activeDropdownScrollHandler, { passive: true });
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dx-dropdown') && !e.target.classList.contains('dx-input')) {
            closeDropdown();
        }
    });

    wrapper.addEventListener('input', (e) => {
        if (e.target.classList.contains('dx-input')) {
            const input = e.target;
            const query = input.value.trim();
            const row = input.closest('tr');
            const idInput = row ? row.querySelector('.dx-id-input') : null;

            // Clear hidden ID if user is editing manually
            if (idInput) idInput.value = '';

            clearTimeout(debounceTimer);
            if (query.length < 2) {
                abortDxSearch();
                closeDropdown();
                return;
            }

            const cacheKey = query.toLocaleLowerCase();
            if (dxSearchCache.has(cacheKey)) {
                renderDxDropdown(input, dxSearchCache.get(cacheKey));
                return;
            }

            debounceTimer = setTimeout(() => {
                abortDxSearch();
                const requestId = dxRequestId;
                activeAbortController = new AbortController();
                fetch('api/search_dx.php?q=' + encodeURIComponent(query), {
                    signal: activeAbortController.signal
                })
                    .then(res => res.json())
                    .then(data => {
                        if (requestId !== dxRequestId) return;
                        if (Array.isArray(data)) dxSearchCache.set(cacheKey, data);
                        renderDxDropdown(input, data);
                    })
                    .catch(err => {
                        if (err.name !== 'AbortError') console.error('Dx Search Error:', err);
                    });
            }, 120);
        }
    });

    // Keyboard navigation for dropdown
    wrapper.addEventListener('keydown', (e) => {
        if (e.target.classList.contains('dx-input') && activeDropdown) {
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

