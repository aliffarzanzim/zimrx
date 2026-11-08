import { getRootPlaces } from './editor-utils.js';
import { PACK_ROOT_COLLAPSED_KEY } from './tree-model.js';

export function createExpansionState(state) {
    function isExpandablePath(pathKey) {
        if (!state.dataset || !Array.isArray(state.dataset.places) || !/^\d+(?:-\d+)*$/.test(pathKey)) return false;
        let nodes = getRootPlaces(state.dataset);
        let node = null;
        for (const part of pathKey.split('-')) {
            const index = Number(part);
            if (!Array.isArray(nodes) || !nodes[index]) return false;
            node = nodes[index];
            if (!Array.isArray(node.places)) return false;
            nodes = node.places;
        }
        return Array.isArray(nodes) && nodes.length > 0;
    }

    function restoreExpandedKeys(packId) {
        state.expandedKeys.clear();
        try {
            const stored = sessionStorage.getItem(`zimrx_geo_expanded_${packId}`);
            if (stored === null) return false;
            const paths = JSON.parse(stored);
            if (!Array.isArray(paths)) return false;
            paths.forEach(pathKey => {
                if (pathKey === PACK_ROOT_COLLAPSED_KEY || (typeof pathKey === 'string' && isExpandablePath(pathKey))) {
                    state.expandedKeys.add(pathKey);
                }
            });
            return true;
        } catch {
            state.expandedKeys.clear();
            return false;
        }
    }

    function persistExpandedKeys() {
        if (!state.packId) return;
        try {
            sessionStorage.setItem(`zimrx_geo_expanded_${state.packId}`, JSON.stringify([...state.expandedKeys]));
        } catch {
            // Expansion persistence is optional when browser storage is unavailable.
        }
    }

    return { restoreExpandedKeys, persistExpandedKeys };
}
