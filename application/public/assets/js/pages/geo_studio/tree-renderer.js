import { escapeHtml, getRootPlaces, indexHierarchySearchMatches, placeName, placeNameEntries } from './editor-utils.js';
import { getRootName, getRootNames } from './pack-names.js';
import { PACK_ROOT_COLLAPSED_KEY, visibleHierarchyRows } from './tree-model.js';
import { renderVirtualList, disposeVirtualList } from './virtual-list.js';

export function renderHierarchyTree({
    dataset,
    query,
    expandedKeys,
    selectedPath,
    selectedRoot,
    container,
    countElement,
    formatTypeLabel,
    renderIcon,
    onSelectNode,
    onSelectRoot,
    onRender,
    onPersistExpansion,
}) {
    if (!dataset || !Array.isArray(dataset.places)) {
        disposeVirtualList(container);
        container.replaceChildren();
        return;
    }

    const roots = getRootPlaces(dataset);
    const normalizedQuery = query.trim().toLowerCase();
    const subtreeMatches = normalizedQuery ? indexHierarchySearchMatches(roots, normalizedQuery) : null;
    const packRootExpanded = normalizedQuery !== '' || !expandedKeys.has(PACK_ROOT_COLLAPSED_KEY);
    const rows = packRootExpanded ? visibleHierarchyRows(roots, normalizedQuery, expandedKeys, subtreeMatches) : [];
    const displayRows = [{ section: 'pack_root' }, ...rows.map(row => ({ row }))];
    const selectedKey = selectedPath.join('-');
    const selectedRowVisible = rows.some(item => item.key === selectedKey);

    function buildNodeElement(node, pathIndices, depth) {
        const children = Array.isArray(node.places) ? node.places : [];
        const hasChildren = children.length > 0;
        const pathKey = pathIndices.join('-');
        const isExpanded = normalizedQuery !== '' || expandedKeys.has(pathKey);
        const isSelected = !selectedRoot && selectedKey === pathKey;
        const row = document.createElement('div');
        row.className = `tree-row depth-${depth}` + (isSelected ? ' selected' : '');

        const caret = document.createElement('span');
        caret.className = 'tree-caret';
        if (hasChildren) {
            caret.innerHTML = renderIcon(isExpanded ? 'chevron-down' : 'chevron-right', 11);
            caret.onclick = event => {
                event.stopPropagation();
                toggleExpanded(pathKey);
            };
        } else {
            caret.innerHTML = '<span class="tree-bullet"></span>';
        }
        row.appendChild(caret);
        row.tabIndex = isSelected ? 0 : -1;
        row.dataset.path = pathKey;
        row.setAttribute('role', 'treeitem');
        row.setAttribute('aria-level', String(depth + 2));
        row.setAttribute('aria-selected', String(isSelected));
        if (hasChildren) row.setAttribute('aria-expanded', String(isExpanded));

        const rawType = node.type;
        const badge = document.createElement('span');
        if (Array.isArray(rawType)) {
            badge.className = 'type-badge dual';
            badge.textContent = rawType.map(formatTypeLabel).join('+');
        } else {
            const type = rawType || 'place';
            badge.className = `type-badge ${String(type).toLowerCase()}`;
            badge.textContent = formatTypeLabel(type);
        }
        row.appendChild(badge);

        const title = document.createElement('span');
        title.className = 'tree-title';
        const language = dataset.manifest?.default_lang || 'en';
        const translations = placeNameEntries(node).filter(([code]) => code !== language);
        title.innerHTML = `<span>${escapeHtml(placeName(node, language, 'Untitled'))}</span>`
            + (translations.length ? `<span class="tree-loc">${escapeHtml(translations.map(([code, name]) => `${name} [${code}]`).join(' · '))}</span>` : '');
        row.appendChild(title);

        if (node.code !== null && node.code !== undefined && node.code !== '') {
            const codeBadge = document.createElement('span');
            codeBadge.className = 'code-badge';
            codeBadge.textContent = node.code;
            row.appendChild(codeBadge);
        }

        if (hasChildren) {
            const childCount = document.createElement('span');
            childCount.className = 'tree-child-count';
            childCount.textContent = children.length;
            row.appendChild(childCount);
        }

        row.onclick = () => onSelectNode(node, pathIndices, depth, true);
        row.ondblclick = event => {
            if (!hasChildren || normalizedQuery) return;
            event.preventDefault();
            toggleExpanded(pathKey);
        };

        row.onkeydown = event => {
            const index = rows.findIndex(item => item.key === pathKey);
            if (event.key === 'Home') {
                event.preventDefault();
                onSelectRoot(true);
                requestAnimationFrame(focusPackRoot);
            } else if (event.key === 'ArrowUp' && index === 0) {
                event.preventDefault();
                onSelectRoot(true);
                requestAnimationFrame(focusPackRoot);
            } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'End') {
                event.preventDefault();
                const next = event.key === 'End' ? rows.length - 1
                    : Math.max(0, Math.min(rows.length - 1, index + (event.key === 'ArrowDown' ? 1 : -1)));
                const target = rows[next];
                onSelectNode(target.node, target.path, target.depth, true);
                container.querySelector(`[data-path="${target.key}"]`)?.focus();
            } else if ((event.key === 'ArrowRight' && hasChildren && !isExpanded)
                || (event.key === 'ArrowLeft' && hasChildren && isExpanded)) {
                event.preventDefault();
                toggleExpanded(pathKey);
                container.querySelector(`[data-path="${pathKey}"]`)?.focus();
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                onSelectNode(node, pathIndices, depth, true);
            }
        };
        return row;
    }

    function toggleExpanded(pathKey) {
        if (expandedKeys.has(pathKey)) {
            expandedKeys.delete(pathKey);
        } else {
            expandedKeys.add(pathKey);
        }
        onRender();
    }

    function togglePackRoot() {
        if (expandedKeys.has(PACK_ROOT_COLLAPSED_KEY)) {
            expandedKeys.delete(PACK_ROOT_COLLAPSED_KEY);
        } else {
            expandedKeys.add(PACK_ROOT_COLLAPSED_KEY);
        }
        onRender();
        requestAnimationFrame(focusPackRoot);
    }

    function focusPackRoot() {
        container.querySelector('[data-pack-root="true"]')?.focus();
    }

    function focusRow(item) {
        if (!item) return;
        onSelectNode(item.node, item.path, item.depth, true);
        requestAnimationFrame(() => container.querySelector(`[data-path="${item.key}"]`)?.focus());
    }

    function buildPackRootElement() {
        const row = document.createElement('div');
        row.className = 'tree-row tree-pack-root-row';
        if (selectedRoot) row.classList.add('selected');
        row.setAttribute('role', 'treeitem');
        row.setAttribute('aria-level', '1');
        const childCount = roots.length;
        const hasChildren = childCount > 0;
        if (hasChildren) row.setAttribute('aria-expanded', String(packRootExpanded));
        row.setAttribute('aria-selected', String(Boolean(selectedRoot)));
        row.dataset.packRoot = 'true';
        row.tabIndex = selectedRoot || !selectedRowVisible ? 0 : -1;

        const caret = document.createElement('span');
        caret.className = 'tree-caret';
        if (hasChildren) {
            caret.innerHTML = renderIcon(packRootExpanded ? 'chevron-down' : 'chevron-right', 11);
            caret.onclick = event => {
                event.stopPropagation();
                togglePackRoot();
            };
        } else {
            caret.innerHTML = '<span class="tree-bullet"></span>';
        }
        row.appendChild(caret);

        const badge = document.createElement('span');
        badge.className = 'type-badge pack-root';
        badge.textContent = dataset.manifest?.pack_type === 'custom' ? 'Custom' : 'Country';
        row.appendChild(badge);

        const name = document.createElement('span');
        name.className = 'tree-title';
        const language = dataset.manifest?.default_lang || 'en';
        const rootNames = getRootNames(dataset.manifest);
        const rootName = getRootName(dataset.manifest, language, dataset.manifest?.pack_id || 'Pack');
        const primaryName = document.createElement('span');
        primaryName.textContent = rootName;
        name.appendChild(primaryName);
        Object.entries(rootNames).forEach(([code, translatedName]) => {
            if (code === language || !translatedName) return;
            const translation = document.createElement('span');
            translation.className = 'tree-loc';
            translation.textContent = `${translatedName} [${code}]`;
            name.appendChild(translation);
        });
        row.appendChild(name);

        if (hasChildren) {
            const count = document.createElement('span');
            count.className = 'tree-child-count';
            count.textContent = String(childCount);
            row.appendChild(count);
        }

        row.onclick = () => onSelectRoot(true);
        row.ondblclick = event => {
            if (!hasChildren || normalizedQuery) return;
            event.preventDefault();
            togglePackRoot();
        };
        row.onkeydown = event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                onSelectRoot(true);
                requestAnimationFrame(focusPackRoot);
            } else if (event.key === 'ArrowRight' && hasChildren) {
                event.preventDefault();
                if (!packRootExpanded) togglePackRoot();
                else focusRow(rows[0]);
            } else if (event.key === 'ArrowLeft' && hasChildren && packRootExpanded) {
                event.preventDefault();
                togglePackRoot();
                requestAnimationFrame(focusPackRoot);
            } else if (event.key === 'ArrowDown' && packRootExpanded) {
                event.preventDefault();
                focusRow(rows[0]);
            } else if (event.key === 'End' && rows.length > 0) {
                event.preventDefault();
                focusRow(rows[rows.length - 1]);
            } else if (event.key === 'Home') {
                event.preventDefault();
                onSelectRoot(true);
                requestAnimationFrame(focusPackRoot);
            }
        };
        return row;
    }

    container.setAttribute('role', 'tree');
    container.setAttribute('aria-label', 'Pack and place hierarchy');
    renderVirtualList({
        container, items: displayRows, selectedIndex: selectedRoot ? 0 : displayRows.findIndex(item => item.row?.key === selectedKey),
        renderItem: item => item.section === 'pack_root' ? buildPackRootElement()
            : buildNodeElement(item.row.node, item.row.path, item.row.depth),
    });
    countElement.textContent = normalizedQuery ? `${rows.length} matches` : '';
    onPersistExpansion();
}
