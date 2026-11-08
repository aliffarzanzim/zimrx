import { createDepthEditor } from './depth-editor.js';

export function initializeHierarchyLevelManager({ state, setDirty, refreshTypeSelection, renderTree, renderSubplaces, updateStats, renderIcon }) {
    const backdrop = document.getElementById('modalHierarchyLevelsBackdrop');
    const depthList = document.getElementById('hierarchyDepthList');
    const status = document.getElementById('hierarchyLevelsStatus');
    let usedTypeDepths = new Map();
    const editor = createDepthEditor(
        depthList,
        document.getElementById('btnAddHierarchyDepthLevel'),
        () => state.dataset?.manifest?.languages || ['en'],
        () => state.dataset?.manifest?.default_lang || 'en',
        renderIcon,
        key => usedTypeDepths.has(key)
    );

    function close() {
        backdrop.hidden = true;
        status.textContent = '';
    }

    function showError(message) {
        status.textContent = message;
        status.hidden = false;
    }

    function copyLevels(levels) {
        return levels.map(level => ({
            ...level,
            types: level.types.map(type => ({ ...type, names: { ...type.names } })),
        }));
    }

    function validateLevels(levels) {
        const languages = state.dataset?.manifest?.languages;
        const defaultLanguage = state.dataset?.manifest?.default_lang;
        if (!Array.isArray(languages) || !languages.includes(defaultLanguage)) {
            return 'The pack default language is missing from its language list.';
        }
        if (!Array.isArray(levels) || levels.length === 0) {
            return 'Add at least one hierarchy level.';
        }
        if (levels.length > 64) return 'A pack can have no more than 64 hierarchy levels.';

        const seenKeys = new Set();
        for (const [levelIndex, level] of levels.entries()) {
            if (!Array.isArray(level.types) || level.types.length === 0) {
                return `Level ${levelIndex + 1} needs at least one place type.`;
            }
            for (const type of level.types) {
                const key = typeof type.key === 'string' ? type.key.trim() : '';
                if (!/^[a-z0-9_]{1,64}$/.test(key)) {
                    return `Every type in Level ${levelIndex + 1} needs a lowercase key using letters, numbers, or underscores.`;
                }
                if (seenKeys.has(key)) return `The type key "${key}" is already used in another level.`;
                seenKeys.add(key);
                const defaultName = typeof type.names?.[defaultLanguage] === 'string'
                    ? type.names[defaultLanguage].trim()
                    : '';
                if (!defaultName) {
                    return `Every type in Level ${levelIndex + 1} needs a name in ${defaultLanguage}.`;
                }
            }
        }
        const definitionDepths = new Map();
        levels.forEach((level, index) => level.types.forEach(type => definitionDepths.set(type.key, index + 1)));
        for (const [key, usedDepths] of usedTypeDepths) {
            if (!seenKeys.has(key)) return `The type key "${key}" is assigned to place records. Keep it in the hierarchy.`;
            for (const usedDepth of usedDepths) {
                if (definitionDepths.get(key) !== usedDepth) {
                    return `The type key "${key}" is assigned to Level ${usedDepth} places. Keep it at that level.`;
                }
            }
        }
        return '';
    }

    document.getElementById('btnManageHierarchyLevels').addEventListener('click', () => {
        if (!state.dataset) return;
        if (!Array.isArray(state.dataset.places)) {
            showError('The place hierarchy is invalid. Reload the pack before editing hierarchy levels.');
            return;
        }
        usedTypeDepths = new Map();
        const pending = state.dataset.places.map(node => ({ node, depth: 1 }));
        while (pending.length > 0) {
            const { node, depth } = pending.pop();
            if (!node || typeof node !== 'object' || !Array.isArray(node.places)) {
                showError('The place hierarchy is invalid. Every place must have a places array. Reload the pack before editing hierarchy levels.');
                return;
            }
            const types = Array.isArray(node.type) ? node.type : [node.type];
            for (const key of types) {
                if (typeof key !== 'string') {
                    showError('The place hierarchy contains an invalid type. Reload the pack before editing hierarchy levels.');
                    return;
                }
                const depths = usedTypeDepths.get(key) || new Set();
                depths.add(depth);
                usedTypeDepths.set(key, depths);
            }
            for (const child of node.places) pending.push({ node: child, depth: depth + 1 });
        }
        editor.setLevels(state.dataset.hierarchy?.levels || []);
        backdrop.hidden = false;
    });

    document.getElementById('btnCloseHierarchyLevelsModal').addEventListener('click', close);
    document.getElementById('btnCancelHierarchyLevelsModal').addEventListener('click', close);
    backdrop.addEventListener('click', event => {
        if (event.target === backdrop) close();
    });
    document.getElementById('btnSaveHierarchyLevels').addEventListener('click', () => {
        if (!state.dataset) return;
        const levels = copyLevels(editor.getLevels());
        const error = validateLevels(levels);
        if (error) {
            showError(error);
            return;
        }

        const previous = state.dataset.hierarchy?.levels || [];
        if (JSON.stringify(previous) !== JSON.stringify(levels)) {
            state.dataset.hierarchy = { ...(state.dataset.hierarchy || {}), levels };
            setDirty(true);
            refreshTypeSelection();
            renderTree();
            renderSubplaces();
            updateStats();
        }
        close();
    });
}
