<div class="pc-wrapper" id="ix-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="ix-table">
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
                            <span>Investigations</span>

                            <!-- Template Search Bar -->
                            <div class="rx-search-box" style="flex: 0 0 200px; margin-left: auto;">
                                <?= zrx_icon('search', 14) ?>
                                <input type="text" placeholder="Template" autocomplete="off" style="height: 28px;">
                            </div>
                        </div>
                    </th>
                    <th style="width: 38px; text-align: center;"></th>
                </tr>
            </thead>
            <tbody id="ix-tbody">
                <?php for($i=1; $i<=5; $i++): ?>
                <tr class="pc-row" draggable="true">
                    <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="pc-row-no"><?= $i ?></td>
                    <td>
                        <textarea class="pc-input ix-input" autocomplete="off" rows="1"></textarea>
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
        <button type="button" class="pc-add-row-btn ix-add-row-btn">Add More</button>
    </div>

    <!-- Template for new rows -->
    <template id="ix-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td>
                <textarea class="pc-input ix-input" autocomplete="off" rows="1"></textarea>
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
    const wrapper = document.getElementById('ix-wrapper');
    const tbody = document.getElementById('ix-tbody');
    const template = document.getElementById('ix-row-template');
    const addBtn = wrapper.querySelector('.ix-add-row-btn');

    // --- Row Management ---
    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('tr.pc-row');
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

        const textarea = tr.querySelector('.ix-input');
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

    // Initialize textareas on load
    document.querySelectorAll('#ix-table textarea').forEach(autoResizeTextarea);

    // Dynamic resize while typing
    wrapper.addEventListener('input', (e) => {
        if (e.target.tagName === 'TEXTAREA') {
            autoResizeTextarea(e.target);
        }
    });

    // --- Investigation Autocomplete ---
    let ixTimer = null;
    let ixDropdown = null;
    let ixActiveIndex = -1;

    function closeIxDropdown() {
        if (ixDropdown) {
            ixDropdown.remove();
            ixDropdown = null;
        }
        ixActiveIndex = -1;
    }

    function positionIxDropdown(input) {
        if (!ixDropdown) return;
        const rect = input.getBoundingClientRect();
        ixDropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        ixDropdown.style.left = (rect.left + window.scrollX) + 'px';
        ixDropdown.style.minWidth = rect.width + 'px';
    }

    function renderIxDropdown(input, items) {
        closeIxDropdown();
        if (!Array.isArray(items) || !items.length || items.error) return;

        const ul = document.createElement('ul');
        ul.className = 'zrx-dropdown rx-dropdown ix-dropdown show';
        ul.style.position = 'absolute';
        ul.style.zIndex = '9999';

        items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'zrx-dropdown-item rx-dropdown-item';
            if (index === 0) li.classList.add('active');
            const meta = [item.category, item.price ? `${item.price} TK.` : '', item.institute].filter(Boolean).join(' | ');
            li.innerHTML = `<div class="rx-dropdown-main"><strong>${escapeHtml(String(item.name || ''))}</strong>${meta ? `<span>${escapeHtml(meta)}</span>` : ''}</div>`;
            li.addEventListener('mouseenter', () => {
                const allItems = ul.querySelectorAll('.zrx-dropdown-item');
                allItems.forEach(el => el.classList.remove('active'));
                li.classList.add('active');
                ixActiveIndex = Array.from(allItems).indexOf(li);
            });
            li.addEventListener('mousedown', (event) => {
                event.preventDefault();
                input.value = item.name || '';
                input.dataset.ixId = item.id || '';
                input.dataset.lineMeta = meta;
                autoResizeTextarea(input);
                closeIxDropdown();
            });
            ul.appendChild(li);
        });

        document.body.appendChild(ul);
        ixDropdown = ul;
        ixActiveIndex = 0;
        positionIxDropdown(input);
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    wrapper.addEventListener('input', (e) => {
        if (!e.target.classList.contains('ix-input')) return;
        const input = e.target;
        const q = input.value.trim();
        window.clearTimeout(ixTimer);
        if (q.length < 1) {
            closeIxDropdown();
            return;
        }
        ixTimer = window.setTimeout(() => {
            fetch('api/search_ix.php?q=' + encodeURIComponent(q))
                .then((res) => res.json())
                .then((items) => renderIxDropdown(input, items))
                .catch(closeIxDropdown);
        }, 140);
    });

    wrapper.addEventListener('focusin', (e) => {
        if (e.target.classList.contains('ix-input') && e.target.value.trim()) {
            e.target.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    wrapper.addEventListener('keydown', (e) => {
        if (!e.target.classList.contains('ix-input') || !ixDropdown) return;
        const items = Array.from(ixDropdown.querySelectorAll('.rx-dropdown-item'));
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            items[ixActiveIndex]?.classList.remove('active');
            ixActiveIndex = (ixActiveIndex + 1) % items.length;
            items[ixActiveIndex].classList.add('active');
            items[ixActiveIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            items[ixActiveIndex]?.classList.remove('active');
            ixActiveIndex = (ixActiveIndex - 1 + items.length) % items.length;
            items[ixActiveIndex].classList.add('active');
            items[ixActiveIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            items[ixActiveIndex]?.dispatchEvent(new MouseEvent('mousedown'));
        } else if (e.key === 'Escape') {
            closeIxDropdown();
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ix-dropdown') && !e.target.classList.contains('ix-input')) {
            closeIxDropdown();
        }
    });

    window.addEventListener('resize', closeIxDropdown);
})();
</script>
