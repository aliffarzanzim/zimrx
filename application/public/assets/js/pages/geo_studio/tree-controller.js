import { renderHierarchyTree } from './tree-renderer.js';
import { getRootPlaces } from './editor-utils.js';
import { PACK_ROOT_COLLAPSED_KEY } from './tree-model.js';

export function initializeTreeController({ state, formatTypeLabel, GeoStudioIcon, selectNode, selectRoot, persistExpandedKeys }) {
    const elTreeContainer = document.getElementById('treeContainer');
    const elTreeSearch = document.getElementById('treeSearchInput');
    function renderTree() {
        renderHierarchyTree({
            dataset: state.dataset,
            query: elTreeSearch.value,
            expandedKeys: state.expandedKeys,
            selectedPath: state.selectedPath,
            selectedRoot: state.selectedRoot,
            container: elTreeContainer,
            countElement: document.getElementById('treeFilteredCount'),
            formatTypeLabel,
            renderIcon: (name, size) => GeoStudioIcon.render(name, size),
            onSelectNode: selectNode,
            onSelectRoot: selectRoot,
            onRender: renderTree,
            onPersistExpansion: persistExpandedKeys,
        });
    }

    elTreeSearch.addEventListener('input', () => {
        renderTree();
    });

    document.getElementById('btnExpandAll').onclick = () => {
        if (!state.dataset || !Array.isArray(state.dataset.places)) return;
        state.expandedKeys.delete(PACK_ROOT_COLLAPSED_KEY);
        function expandWalk(node, path) {
            const kids = Array.isArray(node?.places) ? node.places : [];
            if (kids.length > 0) {
                state.expandedKeys.add(path.join('-'));
                kids.forEach((k, i) => expandWalk(k, [...path, i]));
            }
        }
        getRootPlaces(state.dataset).forEach((p, i) => expandWalk(p, [i]));
        renderTree();
    };

    document.getElementById('btnCollapseAll').onclick = () => {
        state.expandedKeys.clear();
        state.expandedKeys.add(PACK_ROOT_COLLAPSED_KEY);
        renderTree();
    };

    return renderTree;
}
