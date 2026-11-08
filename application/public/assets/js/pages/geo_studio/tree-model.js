export const PACK_ROOT_COLLAPSED_KEY = '@pack-root-collapsed';

export function visibleHierarchyRows(roots, query, expandedKeys, matches) {
    const rows = [];
    function walk(nodes, parentPath, depth) {
        if (!Array.isArray(nodes)) return;
        nodes.forEach((node, index) => {
            if (!node || typeof node !== 'object' || Array.isArray(node)) return;
            const path = [...parentPath, index];
            const key = path.join('-');
            if (query && !matches.get(key)) return;
            rows.push({ node, path, depth, key });
            if ((query || expandedKeys.has(key)) && Array.isArray(node.places)) walk(node.places, path, depth + 1);
        });
    }
    walk(roots, [], 0);
    return rows;
}

export function rebaseExpansionAfterDelete(expandedKeys, parentPath, removedIndex) {
    const prefix = parentPath.length ? parentPath.join('-') + '-' : '';
    const updated = [];
    for (const key of expandedKeys) {
        if (!/^\d+(?:-\d+)*$/.test(key)) { updated.push(key); continue; }
        if (!key.startsWith(prefix)) { updated.push(key); continue; }
        const parts = key.slice(prefix.length).split('-');
        const index = Number(parts[0]);
        if (index === removedIndex) continue;
        if (index > removedIndex) parts[0] = String(index - 1);
        updated.push(prefix + parts.join('-'));
    }
    expandedKeys.clear();
    updated.forEach(key => expandedKeys.add(key));
}
