document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-menu-items]');
    const form = root?.closest('form');
    const seed = document.querySelector('[data-menu-initial]');
    const jsonField = form?.querySelector('[data-menu-json]');
    const errorBox = document.querySelector('[data-menu-error]');
    const rootList = root?.querySelector('[data-sortable="root"]');
    const addOptions = root?.querySelector('[data-menu-add-options]');
    if (!root || !form || !seed || !jsonField || !errorBox || !rootList || !addOptions) return;

    const initial = JSON.parse(seed.textContent);
    const catalog = initial.catalog || [];
    let menuItems = initial.items || [];
    let draggedItem = null;
    const makeId = () => `custom-${crypto.randomUUID()}`;
    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const makeButton = (label, className, action) => {
        const button = element('button', className, label);
        button.type = 'button';
        if (action) button.dataset.action = action;
        return button;
    };
    const flatItems = () => rootList ? Array.from(rootList.children).flatMap(card => {
        const group = card.dataset.type === 'group';
        const children = group ? Array.from(card.querySelector('[data-sortable="children"]').children) : [];
        return [card, ...children];
    }) : [];
    const currentIds = () => new Set(flatItems().map(item => item.dataset.id));
    const visibleLabel = item => item.label || catalog.find(option => option.id === item.id)?.label || item.id;
    const createField = (title, name, value, config = {}) => {
        const label = element('label', 'zrx-menu-field', title);
        let control;
        if (config.options) {
            control = element('select', 'zrx-input');
            config.options.forEach(([optionValue, optionLabel]) => {
                const option = element('option', '', optionLabel);
                option.value = optionValue;
                option.selected = optionValue === value;
                control.append(option);
            });
        } else {
            control = element('input', 'zrx-input');
            control.type = 'text';
            control.maxLength = config.maxLength || 500;
            control.value = value || '';
            if (config.placeholder) control.placeholder = config.placeholder;
            if (config.readOnly) control.readOnly = true;
        }
        control.dataset.field = name;
        label.append(control);
        return label;
    };
    const makePlacementSelect = (item, isChild) => {
        const select = element('select', 'zrx-input zrx-menu-placement');
        select.dataset.field = 'placement';
        select.setAttribute('aria-label', `Parent dropdown for ${visibleLabel(item)}`);
        const main = element('option', '', 'Main menu');
        main.value = '';
        main.selected = !isChild;
        select.append(main);
        menuItems.filter(group => group.type === 'group' && group.id !== item.id).forEach(group => {
            const option = element('option', '', visibleLabel(group));
            option.value = group.id;
            option.selected = isChild && (group.children || []).some(child => child.id === item.id);
            select.append(option);
        });
        return select;
    };
    const itemCard = id => root.querySelector(`.zrx-menu-item[data-id="${CSS.escape(id)}"]`);
    const makeVisibility = item => {
        const label = element('label', 'zrx-menu-visible');
        const checkbox = element('input');
        checkbox.type = 'checkbox';
        checkbox.checked = item.visible !== false;
        checkbox.dataset.field = 'visible';
        if (item.id === 'builtin-settings') {
            checkbox.checked = true;
            checkbox.disabled = true;
        }
        label.append(checkbox, element('span', '', 'Visible'));
        return label;
    };
    const detailsPanel = (item, isChild) => {
        const details = element('div', 'zrx-menu-details');
        details.hidden = true;
        const fields = element('div', 'zrx-menu-detail-fields');
        fields.append(createField('Label', 'label', item.label, {maxLength: 40, placeholder: visibleLabel(item)}));
        if (!isChild) fields.append(createField('Width', 'width', item.width || 'compact', {options: [['compact', 'Compact'], ['standard', 'Standard'], ['wide', 'Wide']]}));
        if (item.type === 'link') {
            fields.append(createField('Link destination', 'href', item.href || '', {
                placeholder: 'page.php or https://example.com',
                readOnly: item.id.startsWith('builtin-'),
            }));
        }
        fields.append(makeVisibility(item));
        if (item.type !== 'group' && item.id !== 'builtin-settings') {
            const placement = element('label', 'zrx-menu-field', 'Location');
            placement.append(makePlacementSelect(item, isChild));
            fields.append(placement);
        }
        details.append(fields);
        const actions = element('div', 'zrx-menu-detail-actions');
        actions.append(makeButton('Move up', 'zrx-menu-move', 'up'), makeButton('Move down', 'zrx-menu-move', 'down'));
        if (item.id !== 'builtin-settings') actions.append(makeButton('Remove', 'zrx-menu-remove', 'remove'));
        details.append(actions);
        if (item.type === 'action') details.append(element('p', 'zrx-menu-action-note', 'Application action: opens the support dialog.'));
        return details;
    };
    const createItem = (item, isChild = false) => {
        const card = element(item.type === 'group' ? 'section' : 'article', `zrx-menu-item zrx-sortable-item${isChild ? ' zrx-menu-child-item' : ''}${item.type === 'group' ? ' zrx-menu-group' : ''}`);
        card.dataset.id = item.id;
        card.dataset.type = item.type;
        const summary = element('div', 'zrx-menu-item-summary');
        const drag = makeButton('', 'pc-row-move-btn zrx-menu-drag zrx-drag-handle');
        drag.draggable = true;
        drag.dataset.dragHandle = '';
        drag.setAttribute('aria-label', `Drag ${visibleLabel(item)} to reorder or change its parent`);
        drag.title = 'Drag to reorder or move into a dropdown';
        drag.innerHTML = window.ZimRxIcon?.render('move', 14) || 'Move';
        summary.append(drag);
        summary.append(element('span', 'zrx-menu-item-name', visibleLabel(item)));
        summary.append(element('span', 'zrx-menu-item-kind', item.type === 'group' ? 'Dropdown' : item.type === 'action' ? 'Action' : 'Link'));
        const toggle = makeButton('Edit', 'zrx-menu-disclose', 'disclose');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', `Edit ${visibleLabel(item)}`);
        summary.append(toggle);
        card.append(summary, detailsPanel(item, isChild));
        if (item.type === 'group') {
            const childList = element('div', 'zrx-menu-child-list');
            childList.dataset.sortable = 'children';
            childList.dataset.zrxReorderable = '';
            (item.children || []).forEach(child => childList.append(createItem(child, true)));
            card.append(childList);
            card.append(makeButton('Add link to this dropdown', 'zrx-menu-add-child', 'add-child'));
        }
        return card;
    };
    const render = () => {
        rootList.replaceChildren(...menuItems.map(item => createItem(item)));
        root.querySelector('[data-menu-empty]').hidden = menuItems.length > 0;
        const used = currentIds();
        addOptions.replaceChildren();
        catalog.filter(item => !used.has(item.id)).forEach(item => {
            const label = element('label', 'zrx-menu-add-option');
            const checkbox = element('input');
            checkbox.type = 'checkbox';
            checkbox.value = item.id;
            checkbox.dataset.catalogId = item.id;
            checkbox.disabled = item.type === 'action' && !menuItems.some(entry => entry.type === 'group');
            if (checkbox.disabled) label.title = 'Add a dropdown before adding this action.';
            label.append(checkbox, element('span', '', item.label));
            addOptions.append(label);
        });
        if (!addOptions.childElementCount) addOptions.append(element('p', 'zrx-menu-no-pages', 'All available pages are already in this menu.'));
        syncHidden();
        root.querySelectorAll('[data-sortable]').forEach(bindDrag);
    };
    const newLink = (label = '', href = '') => ({id: makeId(), type: 'link', label, href, visible: true, width: 'compact'});
    const newGroup = () => ({id: makeId(), type: 'group', label: '', visible: true, width: 'compact', children: []});
    const cardList = card => card?.parentElement;
    const move = (card, direction) => {
        const list = cardList(card);
        if (direction === 'up' && card.previousElementSibling) list.insertBefore(card, card.previousElementSibling);
        if (direction === 'down' && card.nextElementSibling) list.insertBefore(card.nextElementSibling, card);
    };
    const readItem = (card, isChild = false) => {
        const read = name => card.querySelector(`:scope > .zrx-menu-details [data-field="${name}"]`);
        const item = {
            id: card.dataset.id,
            type: card.dataset.type,
            label: read('label')?.value.trim() || '',
            visible: read('visible')?.checked ?? false,
        };
        if (!isChild) item.width = read('width')?.value || 'compact';
        if (item.type === 'link') item.href = read('href')?.value.trim() || '';
        if (item.type === 'action') item.action = 'support';
        if (item.type === 'group') {
            item.children = Array.from(card.querySelector(':scope > [data-sortable="children"]').children).map(child => readItem(child, true));
        }
        return item;
    };
    const readTree = () => Array.from(rootList.children).map(card => readItem(card));
    const syncHidden = () => {
        menuItems = readTree();
        jsonField.value = JSON.stringify(menuItems);
    };
    const updateSummary = card => {
        const label = readItem(card).label || catalog.find(item => item.id === card.dataset.id)?.label || card.dataset.id;
        card.querySelector('.zrx-menu-item-name').textContent = label;
        const toggle = card.querySelector('.zrx-menu-disclose');
        toggle.setAttribute('aria-label', `Edit ${label}`);
        const placement = card.querySelector('[data-field="placement"]');
        if (placement) placement.replaceWith(makePlacementSelect(readItem(card), cardList(card)?.dataset.sortable === 'children'));
    };
    const transfer = (card, destination, before = null) => {
        if (!card || !destination || (card.dataset.type === 'group' && destination.dataset.sortable === 'children') || (card.dataset.id === 'builtin-settings' && destination.dataset.sortable === 'children')) return false;
        destination.insertBefore(card, before && before.parentElement === destination ? before : null);
        card.dispatchEvent(new CustomEvent('zrx:sortable-transfer', {bubbles: true, detail: {item: card}}));
        return true;
    };
    const bindDrag = list => {
        if (list.dataset.sortBound === '1') return;
        list.dataset.sortBound = '1';
        list.addEventListener('dragstart', event => {
            const handle = event.target.closest('.zrx-menu-drag');
            draggedItem = handle?.closest('.zrx-menu-item') || null;
            if (!draggedItem) return;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', draggedItem.dataset.id);
        });
        list.addEventListener('dragover', event => {
            if (list.dataset.sortable === 'root' && event.target.closest('[data-sortable="children"]')) return;
            if (!draggedItem) return;
            const targetCard = event.target.closest('.zrx-menu-item');
            if (targetCard?.dataset.type === 'group' && targetCard.contains(draggedItem)) return;
            const groupTarget = targetCard?.dataset.type === 'group' && !targetCard.contains(draggedItem) && !event.target.closest('.zrx-menu-child-item')
                ? targetCard.querySelector('[data-sortable="children"]')
                : null;
            if (groupTarget && draggedItem.dataset.type !== 'group' && draggedItem.dataset.id !== 'builtin-settings') {
                event.preventDefault();
                event.stopPropagation();
                targetCard.classList.add('drop-target');
                transfer(draggedItem, groupTarget);
                return;
            }
            const destination = list;
            if (draggedItem.dataset.type === 'group' && destination.dataset.sortable === 'children') {
                event.preventDefault();
                event.stopPropagation();
                return;
            }
            if (destination.dataset.sortable === 'children' && draggedItem.parentElement !== destination) {
                event.preventDefault();
                event.stopPropagation();
                if (targetCard?.parentElement === destination && targetCard !== draggedItem) {
                    const bounds = targetCard.getBoundingClientRect();
                    const before = event.clientY < bounds.top + bounds.height / 2 ? targetCard : targetCard.nextElementSibling;
                    transfer(draggedItem, destination, before);
                } else if (!targetCard || targetCard.dataset.type === 'group') {
                    transfer(draggedItem, destination);
                }
                return;
            }
            if (destination.dataset.sortable === 'root' && draggedItem.parentElement?.dataset.sortable === 'children') {
                event.preventDefault();
                event.stopPropagation();
                const bounds = targetCard?.getBoundingClientRect();
                const before = targetCard && targetCard !== draggedItem && targetCard.parentElement === destination
                    ? (event.clientY < bounds.top + bounds.height / 2 ? targetCard : targetCard.nextElementSibling)
                    : null;
                transfer(draggedItem, destination, before);
            }
        });
        list.addEventListener('drop', event => { if (draggedItem) event.preventDefault(); });
    };
    document.addEventListener('dragend', () => {
        if (!draggedItem) return;
        root.querySelectorAll('.dragging, .drop-target').forEach(node => node.classList.remove('dragging', 'drop-target'));
        draggedItem = null;
        syncHidden();
        render();
    });

    root.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const card = button.closest('.zrx-menu-item');
        if (button.dataset.action === 'disclose') {
            const details = card.querySelector('.zrx-menu-details');
            details.hidden = !details.hidden;
            button.setAttribute('aria-expanded', String(!details.hidden));
            button.textContent = details.hidden ? 'Edit' : 'Done';
        } else if (button.dataset.action === 'up' || button.dataset.action === 'down') {
            move(card, button.dataset.action);
        } else if (button.dataset.action === 'remove') {
            if (card.dataset.id === 'builtin-settings') return;
            card.remove();
        } else if (button.dataset.action === 'add-child') {
            const child = createItem(newLink(), true);
            card.querySelector('[data-sortable="children"]').append(child);
            child.querySelector('.zrx-menu-details').hidden = false;
            child.querySelector('.zrx-menu-disclose').setAttribute('aria-expanded', 'true');
            child.querySelector('.zrx-menu-disclose').textContent = 'Done';
            child.querySelector('[data-field="label"]').focus();
        } else if (button.matches('[data-menu-add-selected]')) {
            const selected = Array.from(addOptions.querySelectorAll('input:checked')).map(input => catalog.find(item => item.id === input.value)).filter(Boolean);
            selected.forEach(item => {
                if (item.type === 'group') menuItems.push({id: item.id, type: 'group', label: '', visible: true, width: 'compact', children: []});
                else if (item.type === 'link') menuItems.push({id: item.id, type: 'link', label: '', href: item.href, visible: true, width: 'compact'});
                else if (item.type === 'action') {
                    const group = menuItems.find(entry => entry.type === 'group');
                    if (group) group.children.push({id: item.id, type: 'action', label: '', action: 'support', visible: true});
                    else {
                        errorBox.textContent = 'Add a dropdown before adding the Support action.';
                        errorBox.hidden = false;
                    }
                }
            });
            render();
        } else if (button.matches('[data-menu-add-custom]')) {
            const labelInput = root.querySelector('[data-custom-label]');
            const hrefInput = root.querySelector('[data-custom-href]');
            const label = labelInput.value.trim();
            const href = hrefInput.value.trim();
            if (!label || !href) {
                errorBox.textContent = 'Enter both a label and destination for the custom link.';
                errorBox.hidden = false;
                return;
            }
            menuItems.push(newLink(label, href));
            labelInput.value = '';
            hrefInput.value = '';
            render();
        } else if (button.matches('[data-menu-add-group]')) {
            menuItems.push(newGroup());
            render();
            const group = rootList.lastElementChild;
            group.querySelector('.zrx-menu-details').hidden = false;
            group.querySelector('.zrx-menu-disclose').setAttribute('aria-expanded', 'true');
            group.querySelector('.zrx-menu-disclose').textContent = 'Done';
            group.querySelector('[data-field="label"]').focus();
        }
        syncHidden();
        errorBox.hidden = true;
    });
    root.addEventListener('change', event => {
        const placement = event.target.closest('[data-field="placement"]');
        if (placement) {
            const card = placement.closest('.zrx-menu-item');
            const destination = placement.value ? itemCard(placement.value)?.querySelector('[data-sortable="children"]') : rootList;
            if (destination && transfer(card, destination)) {
                syncHidden();
                render();
            }
        }
        const card = event.target.closest('.zrx-menu-item');
        if (card && event.target.matches('[data-field="visible"]')) {
            card.classList.toggle('is-hidden-item', !event.target.checked);
            updateSummary(card);
        }
        syncHidden();
        errorBox.hidden = true;
    });
    root.addEventListener('input', event => {
        const card = event.target.closest('.zrx-menu-item');
        if (card && event.target.matches('[data-field="label"]')) updateSummary(card);
        syncHidden();
        errorBox.hidden = true;
    });
    form.addEventListener('submit', event => {
        syncHidden();
        const items = JSON.parse(jsonField.value);
        const all = items.flatMap(item => [item, ...(item.children || [])]);
        if (all.some(item => item.id.startsWith('custom-') && (!item.label.trim() || (item.type === 'link' && !item.href.trim())))) {
            event.preventDefault();
            errorBox.textContent = 'Complete the label and destination for each custom link before saving.';
            errorBox.hidden = false;
            errorBox.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
        if (items.some(item => item.type === 'group' && item.children.length === 0)) {
            event.preventDefault();
            errorBox.textContent = 'Each dropdown needs at least one link. Add a link or move an item into the dropdown.';
            errorBox.hidden = false;
            errorBox.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    });

    render();
    root.querySelectorAll('[data-sortable]').forEach(bindDrag);
});
