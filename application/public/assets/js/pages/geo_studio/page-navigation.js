import { getRootPlaces, hasValidPlaceTree, isCurrentPackLoad } from './editor-utils.js';
import { getRootName } from './pack-names.js';
import { apiEndpoints, geoStudioFetch, readGeoStudioJson } from './api-client.js';

export function initializeRouter({ state, getHome, selectNode, selectRoot, renderTree, updateStats, updateCodeLabelUI, setDirty, showToast, restoreExpandedKeys }) {
    let routeRequestId = 0;
    let restoreExpansionOnLoad = performance.getEntriesByType('navigation')[0]?.type === 'reload';
    let loadController = null;
    let acceptedUrl = window.location.href;
    const elTreeContainer = document.getElementById('treeContainer');
    // Router & View Switcher
    function showView(mode) {
        const homeView = document.getElementById('viewHome');
        const editorView = document.getElementById('viewEditor');
        const newPackView = document.getElementById('viewNewPack');
        const btnHome = document.getElementById('btnHome');
        const btnManifest = document.getElementById('btnOpenManifest');
        const btnSave = document.getElementById('btnSaveJson');

        homeView.hidden = mode !== 'home';
        editorView.hidden = mode !== 'editor';
        if (newPackView) newPackView.hidden = mode !== 'new';
        if (btnHome) btnHome.hidden = mode !== 'editor';
        if (btnManifest) btnManifest.hidden = mode !== 'editor';
        if (btnSave) btnSave.hidden = mode !== 'editor';
        if (mode === 'home') {
            if (btnHome) btnHome.classList.add('active');
            if (btnManifest) btnManifest.disabled = true;
            if (btnSave) btnSave.disabled = true;
            document.title = 'Geo Studio - Home';
        } else if (mode === 'new') {
            if (btnManifest) btnManifest.disabled = true;
            if (btnSave) btnSave.disabled = true;
            document.title = 'Geo Studio - New Pack';
            window.dispatchEvent(new Event('geo-studio:new-pack-opened'));
        } else if (mode === 'editor') {
            if (btnHome) btnHome.classList.remove('active');
            const ready = state.dataset !== null && state.datasetPackId === state.packId
                && state.datasetSource === state.source;
            if (btnManifest) btnManifest.disabled = !ready;
            if (btnSave) btnSave.disabled = !ready || !state.isDirty;
            document.title = `Geo Studio - ${getRootName(state.dataset?.manifest || {}, null, state.packId || 'Pack Editor')}`;
        } else {
            document.title = 'Geo Studio';
        }
    }

    function openNewPackPage() {
        const returnUrl = window.location.href;
        const params = new URLSearchParams();
        params.set('page', 'new');
        history.pushState({ page: 'new', returnUrl }, '', window.location.pathname + '?' + params.toString());
        return handleUrlRoute();
    }

    function closeNewPackPage() {
        if (history.state?.page === 'new' && typeof history.state.returnUrl === 'string') {
            history.back();
            return;
        }
        history.replaceState(null, '', window.location.pathname);
        return handleUrlRoute();
    }

    function updateUrl(packId, pathStr = '', push = true, source = state.source) {
        const params = new URLSearchParams();
        if (packId) {
            params.set('pack_id', packId);
            params.set('source', source === 'user' ? 'user' : 'system');
            if (pathStr) {
                params.set('path', pathStr);
            }
        }
        const newQuery = params.toString() ? ('?' + params.toString()) : window.location.pathname;
        const currentQuery = window.location.search || window.location.pathname;

        if (newQuery !== currentQuery) {
            if (push) {
                history.pushState({ packId, path: pathStr }, '', newQuery);
            } else {
                history.replaceState({ packId, path: pathStr }, '', newQuery);
            }
        }
    }

    async function loadPack(packId, source, targetPathStr = '', requestId, restoreExpansion = false) {
        state.packId = packId;
        state.source = source;
        state.datasetPackId = '';
        state.datasetSource = '';
        state.dataset = null;
        state.selectedNode = null;
        state.selectedRoot = false;
        state.selectedPath = [];
        selectNode(null, [], 0, false);
        state.expandedKeys.clear();
        if (!restoreExpansion) {
            try {
                sessionStorage.removeItem(`zimrx_geo_expanded_${packId}`);
            } catch {
                // Browser storage is optional.
            }
        }
        showView('editor');
        elTreeContainer.innerHTML = '<div class="geo-empty-state">Loading dataset...</div>';
        try {
            loadController = new AbortController();
            const res = await geoStudioFetch(apiEndpoints.loadPack(packId, source), { signal: loadController.signal });
            const json = await readGeoStudioJson(res);
            if (!isCurrentPackLoad(requestId, routeRequestId, packId, state.packId)) return;
            if (!res.ok || !json.success) throw new Error(json.error || 'Failed to load pack.');
            if (!hasValidPlaceTree(json.data)) {
                throw new Error('The pack contains an invalid place hierarchy. Every place must have a places array.');
            }
            state.dataset = json.data;
            state.datasetRevision = json.revision || '';
            state.datasetPackId = packId;
            state.datasetSource = source;
            const hasStoredExpansion = restoreExpansion && restoreExpandedKeys(packId);
            updateStats();
            updateCodeLabelUI();
            setDirty(false);
            showView('editor');

            document.title = `Geo Studio - ${getRootName(state.dataset.manifest || {}, null, packId)} (${packId})`;

            if (targetPathStr) {
                navigateToPath(targetPathStr, !hasStoredExpansion);
            } else {
                if (!hasStoredExpansion) state.expandedKeys.clear();
                state.selectedNode = null;
                state.selectedPath = [];
                selectNode(null, [], 0, false);
            }
        } catch (e) {
            if (isCurrentPackLoad(requestId, routeRequestId, packId, state.packId)) {
                elTreeContainer.textContent = e.message || 'Network error loading pack';
                showToast(e.message || 'Network error loading pack');
            }
        }
    }

    function navigateToPath(targetPathStr, expandAncestors = true) {
        if (!state.dataset || !Array.isArray(state.dataset.places)) return;
        if (targetPathStr === 'root') {
            selectRoot(false);
            return;
        }
        const pathIndices = /^\d+(?:-\d+)*$/.test(targetPathStr) ? targetPathStr.split('-').map(Number) : [];
        if (pathIndices.length === 0) {
            selectNode(null, [], 0, false);
            return;
        }

        let currList = getRootPlaces(state.dataset);
        let targetNode = null;
        const accumulated = [];

        for (let i = 0; i < pathIndices.length; i++) {
            const idx = pathIndices[i];
            if (!Array.isArray(currList) || !currList[idx]) break;
            targetNode = currList[idx];
            accumulated.push(idx);
            currList = Array.isArray(targetNode.places) ? targetNode.places : null;
        }

        if (expandAncestors) {
            for (let length = 1; length < accumulated.length; length++) {
                state.expandedKeys.add(accumulated.slice(0, length).join('-'));
            }
        }

        renderTree();

        if (targetNode) {
            selectNode(targetNode, accumulated, accumulated.length - 1, false);
        }
    }

    async function handleUrlRoute({ reload = false } = {}) {
        const restoreExpansion = restoreExpansionOnLoad;
        restoreExpansionOnLoad = false;
        const params = new URLSearchParams(window.location.search);
        const packId = params.get('pack_id') || '';
        const source = params.get('source') === 'user' ? 'user' : 'system';
        const path = params.get('path') || '';
        if ((state.packId !== packId || state.source !== source) && state.isDirty) {
            if (!confirm('You have unsaved changes in this pack. Discard changes and leave?')) {
                history.replaceState(null, '', acceptedUrl);
                return;
            }
            setDirty(false);
        }
        acceptedUrl = window.location.href;
        const requestId = ++routeRequestId;
        loadController?.abort();

        if (params.get('page') === 'new') {
            showView('new');
            return;
        }

        if (packId) {
            if (!getHome().hasPacks()) {
                void getHome().ensureLoaded();
            }
            if (reload || state.packId !== packId || state.source !== source || !state.dataset) {
                await loadPack(packId, source, path, requestId, restoreExpansion);
            } else {
                showView('editor');
                if (path) {
                    navigateToPath(path);
                } else {
                    state.selectedNode = null;
                    state.selectedPath = [];
                    selectNode(null, [], 0, false);
                }
            }
        } else {
            state.packId = '';
            state.source = 'system';
            state.dataset = null;
            state.datasetPackId = '';
            state.datasetSource = '';
            state.selectedNode = null;
            state.selectedPath = [];
            state.expandedKeys.clear();
            setDirty(false);
            selectNode(null, [], 0, false);
            showView('home');
            await getHome().ensureLoaded();
        }
    }

    document.getElementById('btnHome').onclick = () => {
        updateUrl('', '', true);
        handleUrlRoute();
    };

    window.addEventListener('popstate', handleUrlRoute);
    return { updateUrl, handleUrlRoute, openNewPackPage, closeNewPackPage };
}
