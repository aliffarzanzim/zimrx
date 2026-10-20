// Clinical advice module: row management, category template selection, and multilingual autocomplete.
(function () {
    const wrapper = document.getElementById('advice-wrapper');
    const list = document.getElementById('advice-list');
    const rowTemplate = document.getElementById('advice-row-template');
    const templateInput = wrapper?.querySelector('.advice-template-input');
    const templateList = wrapper?.querySelector('.advice-template-dropdown');
    const templateBox = wrapper?.querySelector('.advice-template-box');

    if (!wrapper || !list || !rowTemplate) {
        return;
    }

    function addRow(value = '', focus = true, languages = {}) {
        const row = rowTemplate.content.firstElementChild.cloneNode(true);
        const input = row.querySelector('.adv-input');
        input.value = value;
        input.dataset.printBengali = languages.bengali || value;
        input.dataset.printEnglish = languages.english || value;
        list.appendChild(row);
        attachRowAutocomplete(input);
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
        list.querySelectorAll('.adv-input').forEach((inp) => {
            if (inp._zrxDropdownInstance && typeof inp._zrxDropdownInstance.destroy === 'function') {
                inp._zrxDropdownInstance.destroy();
            }
        });
        list.innerHTML = '';
        values.slice(0, Math.max(5, values.length)).forEach((item) => {
            addRow(item.advice, false, {
                bengali: item.advice_bn || item.advice,
                english: item.advice_en || item.advice
            });
        });
        ensureRows(5);
    }

    function renderPinIcon() {
        if (window.ZimRxIcon && typeof window.ZimRxIcon.render === 'function') {
            return window.ZimRxIcon.render('pin', 14, { class: 'rx-dropdown-pin' });
        }
        return '';
    }

    function escapeHtml(val) {
        if (window.ZimRxDropdown && typeof window.ZimRxDropdown.escapeHtml === 'function') {
            return window.ZimRxDropdown.escapeHtml(val);
        }
        const div = document.createElement('div');
        div.textContent = val == null ? '' : String(val);
        return div.innerHTML;
    }

    function attachRowAutocomplete(input) {
        if (!input || !window.ZimRxDropdown) return;
        const dropdownList = document.createElement('ul');
        dropdownList.className = 'zrx-dropdown rx-dropdown advice-dropdown';

        const instance = window.ZimRxDropdown.attach({
            input: input,
            list: dropdownList,
            floating: true,
            minLength: 1,
            debounceMs: 140,
            fetcher: async (query) => {
                const lang = window.ZimRxClinicalLang || '';
                const langParam = lang ? '&lang=' + encodeURIComponent(lang) : '';
                const res = await fetch('api/search_advice.php?q=' + encodeURIComponent(query) + langParam);
                return await res.json();
            },
            renderItem: (item) => {
                const li = document.createElement('li');
                li.className = 'zrx-dropdown-item rx-dropdown-item';
                if (item.is_pinned) li.classList.add('pinned-item');

                const pinHtml = item.is_pinned ? renderPinIcon() : '';
                li.innerHTML = `
                    ${pinHtml}
                    <div class="rx-dropdown-main" style="${item.is_pinned ? 'padding-left: 12px;' : ''}">
                        <strong>${escapeHtml(item.advice)}</strong>
                        ${item.category ? `<span>${escapeHtml(item.category)}</span>` : ''}
                    </div>
                `;
                return li;
            },
            onSelect: (item) => {
                input.value = item.advice || '';
                input.dataset.printBengali = item.advice_bn || item.advice || '';
                input.dataset.printEnglish = item.advice_en || item.advice || '';
            }
        });

        input._zrxDropdownInstance = instance;
    }

    if (templateInput && templateList && window.ZimRxDropdown) {
        window.ZimRxDropdown.attach({
            input: templateInput,
            list: templateList,
            wrapper: templateBox,
            allowEmptyClick: true,
            minLength: 0,
            debounceMs: 140,
            fetcher: async (query) => {
                const lang = window.ZimRxClinicalLang || '';
                const langParam = lang ? '&lang=' + encodeURIComponent(lang) : '';
                const res = await fetch('api/search_advice.php?mode=category&q=' + encodeURIComponent(query) + langParam);
                return await res.json();
            },
            renderItem: (item) => {
                const li = document.createElement('li');
                li.className = 'zrx-dropdown-item rx-dropdown-item';
                if (item.is_pinned) li.classList.add('pinned-item');

                const pinHtml = item.is_pinned ? renderPinIcon() : '';
                const countText = Number(item.total || 0) + ' advice';
                li.innerHTML = `
                    ${pinHtml}
                    <div class="rx-dropdown-main" style="${item.is_pinned ? 'padding-left: 12px;' : ''}">
                        <strong>${escapeHtml(item.category)}</strong>
                        <span>${escapeHtml(countText)}</span>
                    </div>
                `;
                return li;
            },
            onSelect: (item) => {
                templateInput.value = item.category || '';
                const lang = window.ZimRxClinicalLang || '';
                fetch('api/search_advice.php?category=' + encodeURIComponent(item.category || '') + (lang ? '&lang=' + encodeURIComponent(lang) : ''))
                    .then((res) => res.json())
                    .then(fillAdvices)
                    .catch(() => {});
            }
        });
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
            const row = delButton.closest('.advice-row');
            if (row) {
                const input = row.querySelector('.adv-input');
                if (input?._zrxDropdownInstance && typeof input._zrxDropdownInstance.destroy === 'function') {
                    input._zrxDropdownInstance.destroy();
                }
                row.remove();
                ensureRows(1);
            }
        }
    });

    wrapper.addEventListener('input', (event) => {
        if (event.target.matches('.adv-input')) {
            delete event.target.dataset.printBengali;
            delete event.target.dataset.printEnglish;
        }
    });

    list.querySelectorAll('.adv-input').forEach((input) => {
        attachRowAutocomplete(input);
    });

    ensureRows(5);
})();
