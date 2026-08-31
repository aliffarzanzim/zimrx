/**
 * ZimRx Global Dropdown Engine
 * Provides unified, accessible, keyboard-synchronized dropdown and autocomplete functionality.
 */

(function () {
    window.ZimRxDropdown = {
        attach({
            input,
            list,
            wrapper = null,
            fetcher = null,
            onSelect = null,
            renderItem = null,
            debounceMs = 120,
            minLength = 0,
            allowEmptyClick = false
        }) {
            if (!input || !list) return null;

            let activeIdx = -1;
            let timer = null;
            let isKeyboardNav = false;
            let keyboardNavTimer = null;

            function setKeyboardNavMode() {
                isKeyboardNav = true;
                clearTimeout(keyboardNavTimer);
                keyboardNavTimer = setTimeout(() => {
                    isKeyboardNav = false;
                }, 200);
            }

            function close() {
                list.classList.remove('show');
                if (wrapper) wrapper.classList.remove('open');
                activeIdx = -1;
            }

            function open() {
                list.classList.add('show');
                if (wrapper) wrapper.classList.add('open');
            }

            function setActive(items, idx, shouldScroll = true) {
                Array.from(items).forEach(it => it.classList.remove('active'));
                if (items[idx]) {
                    items[idx].classList.add('active');
                    if (shouldScroll) {
                        items[idx].scrollIntoView({ block: 'nearest' });
                    }
                }
                activeIdx = idx;
            }

            async function queryAndRender(val = '') {
                if (val.length < minLength && !allowEmptyClick) {
                    close();
                    return;
                }

                if (typeof fetcher !== 'function') return;

                const results = await fetcher(val);
                list.innerHTML = '';

                if (!results || !Array.isArray(results) || results.length === 0) {
                    close();
                    return;
                }

                results.forEach((item, index) => {
                    let li;
                    if (typeof renderItem === 'function') {
                        li = renderItem(item, index);
                    } else {
                        li = document.createElement('li');
                        li.textContent = typeof item === 'string' ? item : (item.name || item.title || JSON.stringify(item));
                    }

                    li.classList.add('zrx-dropdown-item');
                    if (index === 0) li.classList.add('active');

                    li.addEventListener('mousemove', () => {
                        isKeyboardNav = false;
                        const items = list.querySelectorAll('.zrx-dropdown-item, li');
                        const currentIndex = Array.from(items).indexOf(li);
                        if (activeIdx !== currentIndex) {
                            setActive(items, currentIndex, false);
                        }
                    });

                    li.addEventListener('mouseenter', () => {
                        if (isKeyboardNav) return;
                        const items = list.querySelectorAll('.zrx-dropdown-item, li');
                        const currentIndex = Array.from(items).indexOf(li);
                        if (activeIdx !== currentIndex) {
                            setActive(items, currentIndex, false);
                        }
                    });

                    li.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        if (typeof onSelect === 'function') {
                            onSelect(item, li);
                        }
                        close();
                    });

                    list.appendChild(li);
                });

                activeIdx = 0;
                open();
            }

            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    queryAndRender(input.value.trim());
                }, debounceMs);
            });

            if (allowEmptyClick) {
                input.addEventListener('click', () => queryAndRender(input.value.trim()));
                input.addEventListener('focus', () => queryAndRender(input.value.trim()));
            }

            input.addEventListener('keydown', (e) => {
                if (!list.classList.contains('show')) return;
                const items = list.querySelectorAll('.zrx-dropdown-item, li');
                if (items.length === 0) return;

                if (['ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'Escape'].includes(e.key)) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setKeyboardNavMode();
                        const nextIdx = (activeIdx + 1) % items.length;
                        setActive(items, nextIdx, true);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setKeyboardNavMode();
                        const prevIdx = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
                        setActive(items, prevIdx, true);
                    } else if (e.key === 'Enter' || e.key === 'Tab') {
                        if (activeIdx > -1 && items[activeIdx]) {
                            e.preventDefault();
                            items[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
                        }
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        close();
                    }
                }
            });

            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !list.contains(e.target) && (!wrapper || !wrapper.contains(e.target))) {
                    close();
                }
            });

            return {
                close,
                open,
                refresh: () => queryAndRender(input.value.trim())
            };
        },

        enhanceSelect(select) {
            if (!select || select.dataset.zrxEnhanced === 'true') return;
            select.dataset.zrxEnhanced = 'true';

            // Hide original select completely from layout while preserving DOM value & events
            select.style.display = 'none';
            select.setAttribute('aria-hidden', 'true');
            select.setAttribute('tabindex', '-1');

            const wrapper = document.createElement('div');
            wrapper.className = 'zrx-custom-select autocomplete-wrapper';
            if (select.id) wrapper.id = `zrx-wrap-${select.id}`;

            const trigger = document.createElement('div');
            trigger.className = 'zrx-select-trigger';
            trigger.setAttribute('tabindex', '0');
            trigger.setAttribute('role', 'combobox');
            trigger.setAttribute('aria-expanded', 'false');

            const labelSpan = document.createElement('span');
            labelSpan.className = 'zrx-select-label';

            const arrowSpan = document.createElement('span');
            arrowSpan.className = 'dropdown-arrow';
            arrowSpan.innerHTML = window.ZimRxIcon ? ZimRxIcon.render('chevron-down', 14) : '▾';

            trigger.appendChild(labelSpan);
            trigger.appendChild(arrowSpan);

            const list = document.createElement('ul');
            list.className = 'zrx-dropdown autocomplete-list';

            wrapper.appendChild(trigger);
            wrapper.appendChild(list);

            select.parentNode.insertBefore(wrapper, select.nextSibling);

            let activeIdx = -1;
            let isKeyboardNav = false;
            let keyboardNavTimer = null;

            function setKeyboardNavMode() {
                isKeyboardNav = true;
                clearTimeout(keyboardNavTimer);
                keyboardNavTimer = setTimeout(() => {
                    isKeyboardNav = false;
                }, 200);
            }

            function rebuildOptions() {
                list.innerHTML = '';
                const options = Array.from(select.options);
                const selectedOpt = select.selectedOptions[0] || options[0];
                labelSpan.textContent = selectedOpt ? selectedOpt.text : '--';

                options.forEach((opt, index) => {
                    const li = document.createElement('li');
                    li.className = 'zrx-dropdown-item';
                    li.textContent = opt.text;
                    li.dataset.value = opt.value;
                    if (opt.selected) {
                        li.classList.add('selected');
                        activeIdx = index;
                    }

                    li.addEventListener('mousemove', () => {
                        isKeyboardNav = false;
                        if (activeIdx !== index) {
                            setActiveOption(index, false);
                        }
                    });

                    li.addEventListener('mouseenter', () => {
                        if (isKeyboardNav) return;
                        if (activeIdx !== index) {
                            setActiveOption(index, false);
                        }
                    });

                    li.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        selectOption(index);
                        close();
                        trigger.focus();
                    });

                    list.appendChild(li);
                });
            }

            function syncFromSelect() {
                const options = Array.from(select.options);
                const selectedIndex = select.selectedIndex >= 0 ? select.selectedIndex : 0;
                const opt = options[selectedIndex];
                labelSpan.textContent = opt ? opt.text : '--';
                
                const items = list.querySelectorAll('.zrx-dropdown-item');
                items.forEach((it, idx) => {
                    it.classList.toggle('selected', idx === selectedIndex);
                });
                activeIdx = selectedIndex;
            }

            function setActiveOption(idx, shouldScroll = true) {
                const items = list.querySelectorAll('.zrx-dropdown-item');
                items.forEach(it => it.classList.remove('active'));
                if (items[idx]) {
                    items[idx].classList.add('active');
                    if (shouldScroll) {
                        items[idx].scrollIntoView({ block: 'nearest' });
                    }
                }
                activeIdx = idx;
            }

            function selectOption(index) {
                const options = Array.from(select.options);
                if (options[index]) {
                    select.selectedIndex = index;
                    syncFromSelect();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }

            function open() {
                rebuildOptions();
                syncFromSelect();
                list.classList.add('show');
                wrapper.classList.add('open');
                const parentCompound = wrapper.closest('.input-group, .patient-referral-control');
                if (parentCompound) parentCompound.classList.add('open');
                trigger.setAttribute('aria-expanded', 'true');
                if (activeIdx >= 0) setActiveOption(activeIdx, true);
            }

            function close() {
                list.classList.remove('show');
                wrapper.classList.remove('open');
                const parentCompound = wrapper.closest('.input-group, .patient-referral-control');
                if (parentCompound) parentCompound.classList.remove('open');
                trigger.setAttribute('aria-expanded', 'false');
            }

            function toggle() {
                if (list.classList.contains('show')) {
                    close();
                } else {
                    open();
                }
            }

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                toggle();
            });

            trigger.addEventListener('keydown', (e) => {
                const isOpen = list.classList.contains('show');
                const items = list.querySelectorAll('.zrx-dropdown-item');

                if (['ArrowDown', 'ArrowUp', 'Enter', ' ', 'Escape', 'Tab'].includes(e.key)) {
                    if (e.key === ' ' || (e.key === 'Enter' && !isOpen)) {
                        e.preventDefault();
                        toggle();
                        return;
                    }
                    if (e.key === 'Escape' || e.key === 'Tab') {
                        if (isOpen) {
                            if (e.key === 'Escape') e.preventDefault();
                            close();
                        }
                        return;
                    }
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (!isOpen) {
                            open();
                        } else if (items.length > 0) {
                            setKeyboardNavMode();
                            const next = (activeIdx + 1) % items.length;
                            setActiveOption(next, true);
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (!isOpen) {
                            open();
                        } else if (items.length > 0) {
                            setKeyboardNavMode();
                            const prev = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
                            setActiveOption(prev, true);
                        }
                    } else if (e.key === 'Enter' && isOpen) {
                        e.preventDefault();
                        if (activeIdx >= 0) {
                            selectOption(activeIdx);
                            close();
                        }
                    }
                }
            });

            document.addEventListener('click', (e) => {
                if (!wrapper.contains(e.target)) {
                    close();
                }
            });

            select.addEventListener('change', syncFromSelect);

            const observer = new MutationObserver(() => {
                rebuildOptions();
            });
            observer.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['selected'] });

            rebuildOptions();
            syncFromSelect();
        },

        autoEnhance() {
            const selector = '#patient-gender, #patient-blood-group, #patient-age-unit, #patient-weight-unit, #patient-height-unit, #patient-ref-type, select.zrx-select';
            document.querySelectorAll(selector).forEach(sel => {
                window.ZimRxDropdown.enhanceSelect(sel);
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.ZimRxDropdown.autoEnhance());
    } else {
        window.ZimRxDropdown.autoEnhance();
    }
})();
