import { apiEndpoints, geoStudioFetch } from './api-client.js';
import { initializeNewPack } from './pack-creation.js';
import { initializeGeoStudioHome } from './pack-library.js';
import { initializeManifestEditor } from './manifest-editor.js';
import { initializeSubplacesEditor } from './child-place-editor.js';
import { escapeHtml } from './editor-utils.js';
import { GeoStudioIcon } from './icons.js';
import { createEditorState } from './editor-state.js';
import { initializePlaceTypes } from './place-types.js';
import { initializeHierarchyLevelManager } from './hierarchy-level-manager.js';
import { createExpansionState } from './tree-expansion.js';
import { initializeStatistics } from './editor-statistics.js';
import { initializeTreeController } from './tree-controller.js';
import { initializeInspector } from './place-inspector.js';
import { initializeRouter } from './page-navigation.js';
import { createPackSaver } from './pack-save.js';
import { initializeDialogs } from './dialogs.js';
import { initializeJsonImport } from './json-import.js';

const appRoot = document.getElementById('geoStudioApp');
const preferences = { name: appRoot?.dataset.initialContributor || '' };

const state = createEditorState();
const setActiveContributorName = name => { preferences.name = name; };
let toastTimer;
function showToast(message) {
    const toast = document.getElementById('geoToast');
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
}

function setDirty(dirty) {
    state.setDirty(dirty);
    const button = document.getElementById('btnSaveJson');
    button.disabled = state.saving || !state.isDirty || !state.dataset
        || state.datasetPackId !== state.packId || state.datasetSource !== state.source;
    button.title = state.isDirty ? 'Save JSON (Unsaved changes) (Ctrl+S)' : 'Save JSON (No changes to save)';
}

const { formatTypeLabel, updateCodeLabelUI, renderTypePills } = initializePlaceTypes({
    state,
    setDirty,
    renderTree: () => renderTree(),
    updateStats: () => updateStats(),
});
const { restoreExpandedKeys, persistExpandedKeys } = createExpansionState(state);
const updateStats = initializeStatistics({ state, formatTypeLabel });
const renderTree = initializeTreeController({
    state,
    formatTypeLabel,
    GeoStudioIcon,
    selectNode: (...args) => inspector.selectNode(...args),
    selectRoot: (...args) => inspector.selectPackRoot(...args),
    persistExpandedKeys,
});
const inspector = initializeInspector({ state, setDirty, updateUrl: (...args) => router.updateUrl(...args), updateCodeLabelUI, renderTypePills, renderTree, renderSubplaces: options => subplacesEditor.render(options) });
const selectNode = (...args) => inspector.selectNode(...args);
const selectRoot = (...args) => inspector.selectPackRoot(...args);
const router = initializeRouter({ state, getHome: () => geoStudioHome, selectNode, selectRoot, renderTree, updateStats, updateCodeLabelUI, setDirty, showToast, restoreExpandedKeys });
const { updateUrl, handleUrlRoute, openNewPackPage, closeNewPackPage } = router;
const geoStudioHome = initializeGeoStudioHome({
    fetchPacks: () => geoStudioFetch(apiEndpoints.listPacks()),
    escapeHtml, renderIcon: (...args) => GeoStudioIcon.render(...args),
    updateUrl, handleUrlRoute,
});
initializeJsonImport({
    button: document.getElementById('btnImportJson'),
    input: document.getElementById('geoStudioImportFile'),
    refreshCatalog: () => geoStudioHome.refreshPacks(),
    updateUrl,
    handleUrlRoute,
    state,
    setDirty,
    showToast,
});
const subplacesEditor = initializeSubplacesEditor({
    getSelectedNode: () => state.selectedNode,
    getSelectedPath: () => state.selectedPath,
    getSelectedRoot: () => state.selectedRoot,
    getDataset: () => state.dataset,
    selectPackRoot: (...args) => inspector.selectPackRoot(...args),
    expandedKeys: state.expandedKeys,
    formatTypeLabel,
    renderIcon: (...args) => GeoStudioIcon.render(...args),
    selectNode,
    updateStats,
    renderTree,
    setDirty,
    showToast,
});

initializeHierarchyLevelManager({
    state,
    setDirty,
    refreshTypeSelection: inspector.refreshTypeSelection,
    renderTree,
    renderSubplaces: () => subplacesEditor.render(),
    updateStats,
    renderIcon: (...args) => GeoStudioIcon.render(...args),
});

const saveButton = document.getElementById('btnSaveJson');
const saveCurrentPack = createPackSaver({
    state,
    preferences,
    commitInputs: inspector.commitInputs,
    send: body => geoStudioFetch(apiEndpoints.savePack(), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body,
    }),
    onChange() {
        saveButton.disabled = state.saving || !state.isDirty || state.datasetPackId !== state.packId
            || state.datasetSource !== state.source || !state.dataset;
        saveButton.title = state.isDirty ? 'Save JSON (Unsaved changes) (Ctrl+S)' : 'Save JSON (No changes to save)';
    },
    onSaved(packId, date, source, activePackRefreshed) {
        updateUrl(packId, '', false, source);
        const activeStatus = activePackRefreshed === false
            ? ' Saved, but the active address pack could not be refreshed. Reapply it in Address Settings.'
            : '';
        showToast(`Saved zimrx_address_pack_${packId.toLowerCase()}.json (Updated: ${date}).${activeStatus}`);
        updateStats();
        void geoStudioHome.refreshPacks();
    },
    onError: showToast,
});
saveButton.onclick = () => { if (state.isDirty) void saveCurrentPack(); };
initializeManifestEditor({
    getDataset: () => state.dataset,
    getContributorName: () => preferences.name,
    updateStats,
    refreshTypeLabels: () => {
        inspector.refreshTypeSelection();
        renderTree();
        subplacesEditor.render();
    },
    updateCodeLabelUI,
    setDirty,
    showToast,
    renderIcon: (...args) => GeoStudioIcon.render(...args),
    refreshNameFields: () => inspector.refreshNameFields(),
});

initializeNewPack({
    getActiveContributorName: () => preferences.name,
    setActiveContributorName,
    showToast,
    refreshCatalog: () => geoStudioHome.refreshPacks(),
    updateUrl,
    handleUrlRoute,
    openNewPackPage,
    closeNewPackPage,
    renderIcon: (...args) => GeoStudioIcon.render(...args),
});
// Global Keyboard Shortcut: Ctrl+S / Cmd+S
window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        const btnSave = document.getElementById('btnSaveJson');
        if (btnSave && !btnSave.disabled) {
            btnSave.click();
        }
    }
});

// Warn on closing or navigating away with unsaved changes
window.addEventListener('beforeunload', (e) => {
    if (state.isDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});

initializeDialogs();
handleUrlRoute();
