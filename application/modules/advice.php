<div class="advice-wrapper" id="advice-wrapper">
    <div class="advice-left">
        <div class="advice-header-row">
            <span class="advice-title">&#x0989;&#x09AA;&#x09A6;&#x09C7;&#x09B6;&#x0983;</span>
            <div class="rx-search-box advice-template-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" class="advice-template-input" placeholder="Advice Template / Category" autocomplete="off">
            </div>
        </div>

        <div class="advice-list" id="advice-list">
            <?php for($i=1; $i<=5; $i++): ?>
            <div class="advice-row pc-row" draggable="true">
                <div class="adv-drag pc-drag">
                    <button type="button" class="pc-row-move-btn zrx-drag-handle" style="width:100%; height:100%; border:none; background:transparent; padding:0; display:flex; align-items:center; justify-content:center; cursor:grab;" title="Move Row">
                        <?= zrx_icon('move', 12) ?>
                    </button>
                </div>
                <div class="adv-del pc-del">
                    <button type="button" title="Remove Row">X</button>
                </div>
                <input type="text" class="adv-input" autocomplete="off">
            </div>
            <?php endfor; ?>
        </div>

        <div class="advice-footer">
            <button type="button" class="adv-add-row-btn">Add More</button>
        </div>
    </div>

    <div class="advice-right">
        <div class="adv-form-group">
            <label>&#x09AA;&#x09B0;&#x09AC;&#x09B0;&#x09CD;&#x09A4;&#x09C0; &#x09B8;&#x09BE;&#x0995;&#x09CD;&#x09B7;&#x09BE;&#x09CE;</label>
            <select id="advice-next-visit-select">
                <option>&#x09AA;&#x09CD;&#x09B0;&#x09DF;&#x09CB;&#x099C;&#x09A8; &#x09A8;&#x09C7;&#x0987;</option>
                <option>&#x09E7; &#x09A6;&#x09BF;&#x09A8; &#x09AA;&#x09B0;</option>
                <option>&#x09E9; &#x09A6;&#x09BF;&#x09A8; &#x09AA;&#x09B0;</option>
                <option>&#x09ED; &#x09A6;&#x09BF;&#x09A8; &#x09AA;&#x09B0;</option>
                <option>&#x09E7;&#x09EB; &#x09A6;&#x09BF;&#x09A8; &#x09AA;&#x09B0;</option>
                <option>&#x09E7; &#x09AE;&#x09BE;&#x09B8; &#x09AA;&#x09B0;</option>
            </select>
        </div>
        <div class="adv-form-group">
            <label>&#x09A4;&#x09BE;&#x09B0;&#x09BF;&#x0996;</label>
            <input type="text" id="advice-next-visit-date" class="custom-date-picker" placeholder="">
        </div>
    </div>

    <template id="advice-row-template">
        <div class="advice-row pc-row" draggable="true">
            <div class="adv-drag pc-drag">
                <button type="button" class="pc-row-move-btn zrx-drag-handle" style="width:100%; height:100%; border:none; background:transparent; padding:0; display:flex; align-items:center; justify-content:center; cursor:grab;" title="Move Row">
                    <?= zrx_icon('move', 12) ?>
                </button>
            </div>
            <div class="adv-del pc-del">
                <button type="button" title="Remove Row">X</button>
            </div>
            <input type="text" class="adv-input" autocomplete="off">
        </div>
    </template>
</div>

<script>
(function () {
    const wrapper = document.getElementById('advice-wrapper');
    const list = document.getElementById('advice-list');
    const rowTemplate = document.getElementById('advice-row-template');
    const templateInput = wrapper?.querySelector('.advice-template-input');
    let timer = null;
    let dropdown = null;
    let activeIndex = -1;

    if (!wrapper || !list || !rowTemplate) {
        return;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function addRow(value = '', focus = true, languages = {}) {
        const row = rowTemplate.content.firstElementChild.cloneNode(true);
        const input = row.querySelector('.adv-input');
        input.value = value;
        input.dataset.printBengali = languages.bengali || value;
        input.dataset.printEnglish = languages.english || value;
        list.appendChild(row);
        if (focus) {
            input.focus();
        }
        return row;
    }

    function ensureRows(count) {
        while (list.children.length < count) {
            addRow('', false);
        }
    }

    function fillAdvices(items) {
        const values = Array.isArray(items) ? items.filter((item) => item.advice) : [];
        list.innerHTML = '';
        values.slice(0, Math.max(5, values.length)).forEach((item) => {
            addRow(item.advice, false, {
                bengali: item.advice,
                english: item.advice_en || item.advice
            });
        });
        ensureRows(5);
    }

    function closeDropdown() {
        if (dropdown) {
            dropdown.remove();
            dropdown = null;
        }
        activeIndex = -1;
    }

    function positionDropdown(input) {
        if (!dropdown) return;
        const rect = input.getBoundingClientRect();
        dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        dropdown.style.left = (rect.left + window.scrollX) + 'px';
        dropdown.style.minWidth = rect.width + 'px';
    }

    function renderDropdown(input, items, type) {
        closeDropdown();
        if (!Array.isArray(items) || !items.length || items.error) return;

        const ul = document.createElement('ul');
        ul.className = 'rx-dropdown advice-dropdown';
        ul.style.position = 'absolute';
        ul.style.zIndex = '9999';

        items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'rx-dropdown-item';
            if (index === 0) li.classList.add('active');

            let pinHtml = '';
            if (item.is_pinned) {
                li.classList.add('pinned-item');
                pinHtml = `<img class="rx-dropdown-pin" src="assets/images/pin.svg" alt="Pinned">`;
            }

            if (type === 'category') {
                li.innerHTML = `
                    ${pinHtml}
                    <div class="rx-dropdown-main" style="${item.is_pinned ? 'padding-left: 12px;' : ''}">
                        <strong>${escapeHtml(item.category)}</strong>
                        <span>${Number(item.total || 0)} advice</span>
                    </div>
                `;
                li.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    input.value = item.category || '';
                    fetch('api/search_advice.php?category=' + encodeURIComponent(item.category || ''))
                        .then((res) => res.json())
                        .then(fillAdvices)
                        .catch(() => {});
                    closeDropdown();
                });
            } else {
                li.innerHTML = `
                    ${pinHtml}
                    <div class="rx-dropdown-main" style="${item.is_pinned ? 'padding-left: 12px;' : ''}">
                        <strong>${escapeHtml(item.advice)}</strong>
                        ${item.category ? `<span>${escapeHtml(item.category)}</span>` : ''}
                    </div>
                `;
                li.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    input.value = item.advice || '';
                    input.dataset.printBengali = item.advice || '';
                    input.dataset.printEnglish = item.advice_en || item.advice || '';
                    closeDropdown();
                });
            }

            ul.appendChild(li);
        });

        document.body.appendChild(ul);
        dropdown = ul;
        activeIndex = 0;
        positionDropdown(input);
    }

    function search(input, type) {
        window.clearTimeout(timer);
        const q = input.value.trim();
        if (!q && type !== 'category') {
            closeDropdown();
            return;
        }
        timer = window.setTimeout(() => {
            const url = type === 'category'
                ? 'api/search_advice.php?mode=category&q=' + encodeURIComponent(q)
                : 'api/search_advice.php?q=' + encodeURIComponent(q);
            fetch(url)
                .then((res) => res.json())
                .then((items) => renderDropdown(input, items, type))
                .catch(closeDropdown);
        }, 140);
    }

    wrapper.addEventListener('click', (event) => {
        if (event.target.closest('.adv-add-row-btn')) {
            event.preventDefault();
            addRow();
            return;
        }

        const delButton = event.target.closest('.adv-del button');
        if (delButton) {
            event.preventDefault();
            delButton.closest('.advice-row')?.remove();
            ensureRows(1);
        }
    });

    wrapper.addEventListener('input', (event) => {
        if (event.target.matches('.advice-template-input')) {
            search(event.target, 'category');
            return;
        }
        if (event.target.matches('.adv-input')) {
            delete event.target.dataset.printBengali;
            delete event.target.dataset.printEnglish;
            search(event.target, 'advice');
        }
    });

    wrapper.addEventListener('focusin', (event) => {
        if (event.target.matches('.advice-template-input')) {
            search(event.target, 'category');
        }
    });

    wrapper.addEventListener('keydown', (event) => {
        if (!dropdown || !event.target.matches('.adv-input, .advice-template-input')) return;
        const items = Array.from(dropdown.querySelectorAll('.rx-dropdown-item'));
        if (!items.length) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            items[activeIndex]?.classList.remove('active');
            activeIndex = (activeIndex + 1) % items.length;
            items[activeIndex].classList.add('active');
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            items[activeIndex]?.classList.remove('active');
            activeIndex = (activeIndex - 1 + items.length) % items.length;
            items[activeIndex].classList.add('active');
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter') {
            event.preventDefault();
            items[activeIndex]?.dispatchEvent(new MouseEvent('mousedown'));
        } else if (event.key === 'Escape') {
            closeDropdown();
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.advice-dropdown') && !wrapper.contains(event.target)) {
            closeDropdown();
        }
    });

    window.addEventListener('resize', closeDropdown);
    ensureRows(5);
})();
</script>
