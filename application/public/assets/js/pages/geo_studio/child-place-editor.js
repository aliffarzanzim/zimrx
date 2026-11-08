import { escapeHtml, getRootPlaces, placeName, placeNameEntries } from './editor-utils.js';
import { PACK_ROOT_COLLAPSED_KEY, rebaseExpansionAfterDelete } from './tree-model.js';

function filterSubplaces(children, value) {
    const query = value.trim().toLowerCase();
    return children
        .map((node, index) => ({ node, index }))
        .filter(({ node }) => !query || [...Object.values(node.names || {}), node.code]
            .some(value => String(value || '').toLowerCase().includes(query)));
}

export function initializeSubplacesEditor({
    getSelectedNode,
    getSelectedPath,
    getSelectedRoot,
    getDataset,
    selectPackRoot,
    expandedKeys,
    formatTypeLabel,
    renderIcon,
    selectNode,
    updateStats,
    renderTree,
    setDirty,
    showToast,
}) {
    const body = document.getElementById('subplacesTableBody');
    const count = document.getElementById('subPlacesCount');
    const filterInput = document.getElementById('subplacesFilterInput');
    const pagination = document.getElementById('subplacesPagination');
    const previous = document.getElementById('subplacesPrevious');
    const next = document.getElementById('subplacesNext');
    const childUnitBackdrop = document.getElementById('modalAddChildUnitBackdrop');
    const childTypeSelect = document.getElementById('selChildType');
    const childTypeDepth = document.getElementById('childTypeDepth');
    const childTypeField = document.getElementById('childTypeField');
    const childNameInput = document.getElementById('inpChildUnitName');
    const childNameLabel = document.getElementById('lblChildUnitName');
    const childUnitStatus = document.getElementById('childUnitStatus');
    const childUnitForm = document.getElementById('formAddChildUnit');
    let pendingChildContext = null;
    let page = 0;
    const pageSize = 100;

    function closeAddChildUnitDialog() {
        childUnitBackdrop.hidden = true;
        pendingChildContext = null;
        childUnitStatus.hidden = true;
        childUnitStatus.textContent = '';
    }

    function addChild(typeKey, name, context) {
        if (getDataset() !== context.dataset || getSelectedNode() !== context.node
            || getSelectedPath().join('-') !== context.path.join('-')) {
            showToast('The selected unit changed. Select it again before adding a child.');
            return;
        }
        if (!Array.isArray(context.node.places)) {
            showToast('This place has invalid child data. Reload the pack before editing it.');
            return;
        }

        const child = { names: { [context.language]: name.trim() }, type: typeKey, places: [] };
        context.node.places.push(child);
        if (context.path.length === 0) {
            expandedKeys.delete(PACK_ROOT_COLLAPSED_KEY);
        } else {
            expandedKeys.add(context.path.join('-'));
        }
        render();
        updateStats();
        renderTree();
        setDirty(true);
        showToast(`Added child unit "${placeName(child, context.language)}"`);
    }

    function render({ resetFilter = false } = {}) {
        const selectedNode = getSelectedNode();
        if (!selectedNode) {
            body.replaceChildren();
            count.textContent = '0';
            pagination.hidden = true;
            return;
        }
        if (resetFilter && filterInput) { filterInput.value = ''; page = 0; }
        const children = selectedNode.places;
        if (!Array.isArray(children)) {
            body.replaceChildren();
            count.textContent = 'Unavailable';
            pagination.hidden = true;
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'geo-table-empty';
            cell.textContent = 'This place has invalid child data. Reload the pack before editing it.';
            row.appendChild(cell);
            body.appendChild(row);
            return;
        }
        const dataset = getDataset();
        const language = dataset?.manifest?.default_lang || 'en';
        document.getElementById('subPlacesLabel').textContent = getSelectedRoot() ? 'Level 1 Units' : 'Sub-Units';
        body.replaceChildren();

        const filtered = filterSubplaces(children, filterInput?.value || '');
        const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
        page = Math.min(page, pages - 1);
        pagination.hidden = pages === 1;
        previous.disabled = page === 0;
        next.disabled = page === pages - 1;
        document.getElementById('subplacesPageStatus').textContent = `Page ${page + 1} of ${pages}`;
        if (children.length === 0) {
            count.textContent = '0';
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="5" class="geo-table-empty">No child units nested here. Click "+ Add Child Unit" to add one.</td>';
            body.appendChild(row);
            return;
        }

        filtered.slice(page * pageSize, (page + 1) * pageSize).forEach(({ node, index }) => {
            const row = document.createElement('tr');
            const isDual = Array.isArray(node.type);
            const displayType = isDual ? node.type.map(formatTypeLabel).join('+') : formatTypeLabel(node.type || 'place');
            const badgeClass = isDual ? 'dual' : String(node.type || 'place').toLowerCase();
            const displayCode = escapeHtml(node.code || '-');
            row.innerHTML = `
                <td class="geo-text-semibold">${escapeHtml(placeName(node, language))}</td>
                <td class="geo-text-muted">${escapeHtml(placeNameEntries(node).filter(([code]) => code !== language).map(([code, name]) => `${name} [${code}]`).join(' · '))}</td>
                <td><span class="type-badge ${escapeHtml(badgeClass)}">${escapeHtml(displayType)}</span></td>
                <td><span class="code-badge">${displayCode}</span></td>
                <td class="geo-text-center">
                    <button type="button" class="sub-btn btn-inspect" data-idx="${index}">Inspect</button>
                    <button type="button" class="sub-btn sub-btn-del btn-del-child" data-idx="${index}" title="Delete Sub-place">${renderIcon('trash', 12)}</button>
                </td>
            `;
            body.appendChild(row);
        });

        count.textContent = filterInput?.value.trim() ? `${filtered.length} of ${children.length}` : children.length;
        if (filtered.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `<td colspan="5" class="geo-table-empty">No sub-places matching "${escapeHtml(filterInput.value.trim())}".</td>`;
            body.appendChild(row);
        }

        body.querySelectorAll('.btn-inspect').forEach(button => {
            button.onclick = () => {
                const index = Number.parseInt(button.getAttribute('data-idx'), 10);
                const path = getSelectedPath();
                if (path.length === 0) {
                    expandedKeys.delete(PACK_ROOT_COLLAPSED_KEY);
                } else {
                    expandedKeys.add(path.join('-'));
                }
                selectNode(children[index], [...path, index], path.length, true);
            };
        });

        body.querySelectorAll('.btn-del-child').forEach(button => {
            button.onclick = () => {
                const index = Number.parseInt(button.getAttribute('data-idx'), 10);
                if (!confirm(`Remove "${placeName(children[index], language)}"?`)) return;
                children.splice(index, 1);
                rebaseExpansionAfterDelete(expandedKeys, getSelectedPath(), index);
                render();
                updateStats();
                renderTree();
                setDirty(true);
                showToast('Child unit removed');
            };
        });
    }

    filterInput?.addEventListener('input', () => { page = 0; render(); });
    previous.onclick = () => { page--; render(); };
    next.onclick = () => { page++; render(); };
    document.getElementById('btnAddSubPlace').onclick = () => {
        const selectedNode = getSelectedNode();
        if (!selectedNode) return;
        const dataset = getDataset();
        if (!dataset) return;
        const path = [...getSelectedPath()];
        const childDepth = path.length + 1;
        const level = dataset.hierarchy?.levels?.find(item => Number(item.depth) === childDepth);
        const choices = Array.isArray(level?.types) ? level.types.filter(type => type?.key) : [];
        if (choices.length === 0) {
            showToast(`No types are defined for Level ${childDepth}. Add a type in Manage hierarchy depth levels first.`);
            return;
        }

        const context = { node: selectedNode, dataset, path, language: dataset.manifest?.default_lang || 'en' };
        childTypeSelect.replaceChildren();
        const language = context.language;
        choices.forEach(type => {
            const option = document.createElement('option');
            option.value = String(type.key);
            option.textContent = type.names?.[language] || String(type.key);
            childTypeSelect.appendChild(option);
        });
        childTypeDepth.textContent = `Level ${childDepth}`;
        childTypeField.hidden = choices.length === 1;
        if (choices.length > 1) {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select a type';
            placeholder.disabled = true;
            placeholder.selected = true;
            childTypeSelect.insertBefore(placeholder, childTypeSelect.firstChild);
        }
        childNameLabel.textContent = `Unit name (${language})`;
        childNameInput.value = '';
        childUnitStatus.hidden = true;
        childUnitStatus.textContent = '';
        pendingChildContext = { context, choices: choices.map(type => String(type.key)) };
        childUnitBackdrop.hidden = false;
        requestAnimationFrame(() => {
            if (!pendingChildContext) return;
            (choices.length > 1 ? childTypeSelect : childNameInput).focus();
        });
    };

    childUnitForm.addEventListener('submit', event => {
        event.preventDefault();
        const pending = pendingChildContext;
        if (!pending) return;

        const typeKey = pending.choices.length === 1 ? pending.choices[0] : childTypeSelect.value;
        if (!pending.choices.includes(typeKey)) {
            childUnitStatus.textContent = 'Choose a type for this hierarchy level.';
            childUnitStatus.hidden = false;
            childTypeSelect.focus();
            return;
        }

        const name = childNameInput.value.trim();
        if (!name) {
            childUnitStatus.textContent = 'Enter a name for the child unit.';
            childUnitStatus.hidden = false;
            childNameInput.focus();
            return;
        }

        const { context } = pending;
        closeAddChildUnitDialog();
        addChild(typeKey, name, context);
    });
    document.getElementById('btnCloseChildType').onclick = closeAddChildUnitDialog;
    document.getElementById('btnCancelChildType').onclick = closeAddChildUnitDialog;
    childUnitBackdrop.addEventListener('click', event => {
        if (event.target === childUnitBackdrop) closeAddChildUnitDialog();
    });

    document.getElementById('btnDeleteNode').onclick = () => {
        const node = getSelectedNode();
        const path = getSelectedPath();
        const dataset = getDataset();
        if (!node || path.length === 0 || !dataset) return;
        if (!Array.isArray(dataset.places)) {
            showToast('The place hierarchy is invalid. Reload the pack before deleting places.');
            return;
        }
        const language = dataset.manifest?.default_lang || 'en';
        if (!confirm(`Are you sure you want to delete "${placeName(node, language)}" and all its sub-places?`)) return;

        const parentPath = path.slice(0, -1);
        const index = path[path.length - 1];
        if (!Number.isInteger(index) || index < 0) {
            showToast('The place path is invalid. Reload the pack before deleting places.');
            return;
        }

        let parent = null;
        let targetPlaces;
        let targetIndex = index;
        if (parentPath.length === 0) {
            targetPlaces = dataset.places;
        } else {
            let parentNodes = getRootPlaces(dataset);
            for (const parentIndex of parentPath) {
                if (!Array.isArray(parentNodes) || !parentNodes[parentIndex]) {
                    showToast('The place path is invalid. Reload the pack before deleting places.');
                    return;
                }
                parent = parentNodes[parentIndex];
                parentNodes = parent.places;
            }
            targetPlaces = parent?.places;
        }
        if (!Array.isArray(targetPlaces) || targetPlaces[targetIndex] !== node) {
            showToast('The place path is invalid. Reload the pack before deleting places.');
            return;
        }

        targetPlaces.splice(targetIndex, 1);
        rebaseExpansionAfterDelete(expandedKeys, parentPath, index);
        if (parentPath.length === 0) {
            selectPackRoot(true);
        } else {
            selectNode(parent, parentPath, parentPath.length - 1, true);
        }

        updateStats();
        renderTree();
        setDirty(true);
        showToast('Place deleted');
    };

    return { render };
}
