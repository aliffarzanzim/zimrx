export function escapeHtml(value) {
    if (!value) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function placeNameEntries(node) {
    const names = node && node.names && typeof node.names === 'object' ? node.names : {};
    return Object.entries(names)
        .filter(([, value]) => typeof value === 'string' && value.trim() !== '')
        .map(([language, value]) => [language, value.trim()]);
}

export function placeName(node, language = 'en', fallback = '') {
    const names = node && node.names && typeof node.names === 'object' ? node.names : {};
    if (typeof names[language] === 'string' && names[language].trim()) return names[language].trim();
    return fallback;
}

export function getRootPlaces(dataset) {
    return Array.isArray(dataset?.places) ? dataset.places : [];
}

export function hasValidPlaceTree(dataset) {
    if (!dataset || typeof dataset !== 'object' || !Array.isArray(dataset.places)) return false;

    const pending = [...dataset.places];
    const seen = new WeakSet();
    while (pending.length > 0) {
        const node = pending.pop();
        if (!node || typeof node !== 'object' || Array.isArray(node) || seen.has(node) || !Array.isArray(node.places)) return false;
        seen.add(node);
        for (const child of node.places) pending.push(child);
    }
    return true;
}

export function isCurrentPackLoad(requestId, currentRequestId, requestedPackId, currentPackId) {
    return requestId === currentRequestId && requestedPackId === currentPackId;
}

export function indexHierarchySearchMatches(roots, query) {
    const needle = query.trim().toLowerCase();
    const matches = new Map();
    if (!needle || !Array.isArray(roots)) return matches;

    const stack = [];
    for (let index = roots.length - 1; index >= 0; index--) {
        stack.push({ node: roots[index], path: String(index), visited: false });
    }

    while (stack.length > 0) {
        const entry = stack.pop();
        if (!entry.node || typeof entry.node !== 'object') continue;
        const children = Array.isArray(entry.node.places) ? entry.node.places : [];

        if (entry.visited) {
            const ownMatch = [...Object.values(entry.node.names || {}), entry.node.code]
                .some(value => String(value ?? '').toLowerCase().includes(needle));
            const childMatch = children.some((child, index) => matches.get(`${entry.path}-${index}`) === true);
            matches.set(entry.path, ownMatch || childMatch);
            continue;
        }

        stack.push({ ...entry, visited: true });
        for (let index = children.length - 1; index >= 0; index--) {
            stack.push({ node: children[index], path: `${entry.path}-${index}`, visited: false });
        }
    }

    return matches;
}
