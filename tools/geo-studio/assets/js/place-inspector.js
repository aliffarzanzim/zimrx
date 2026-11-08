import { getRootPlaces } from './editor-utils.js';
import { getRootName, getRootNames, setRootNames } from './pack-names.js';
import { orderLanguagesByDefault } from './languages.js';

export function initializeInspector({ state, setDirty, updateUrl, updateCodeLabelUI, renderTypePills, renderTree, renderSubplaces }) {
    const elFormContainer = document.getElementById('editorFormContainer');
    const elEmptyState = document.getElementById('editorEmptyState');
    const elBreadcrumbs = document.getElementById('editorBreadcrumbs');
    const elNames = document.getElementById('placeNamesFields');
    const elCodeGroup = document.getElementById('grpCurNodeCode');
    const elInpCode = document.getElementById('inpCode');
    const elInpDepth = document.getElementById('inpDepth');
    const elTypePills = document.getElementById('typePillsContainer');
    const elDeleteNode = document.getElementById('btnDeleteNode');
    let renderTimer;
    function renderNameFields(names = {}, languages = state.dataset?.manifest?.languages || ['en']) {
        if (!elNames) return;
        elNames.replaceChildren();
        orderLanguagesByDefault(languages, state.dataset?.manifest?.default_lang || 'en').forEach(language => {
            const group = document.createElement('div');
            group.className = 'geo-form-group';
            const label = document.createElement('label');
            label.className = 'geo-label';
            const inputId = `place-name-${language}`;
            label.htmlFor = inputId;
            label.textContent = `${language.toUpperCase()} Name`;
            const input = document.createElement('input');
            input.type = 'text';
            input.id = inputId;
            input.className = 'geo-input place-name-input';
            input.dataset.language = language;
            input.value = names[language] || '';
            input.required = state.selectedRoot || language === (state.dataset?.manifest?.default_lang || 'en');
            group.append(label, input);
            elNames.appendChild(group);
        });
    }

    function commitInputs() {
        if (!state.selectedNode || state.datasetPackId !== state.packId) return;
        const names = {};
        elNames?.querySelectorAll('.place-name-input').forEach(input => {
            const value = input.value.trim();
            if (value) names[input.dataset.language] = value;
        });
        if (state.selectedRoot) {
            const manifest = state.dataset.manifest;
            setRootNames(manifest, names);
            state.selectedNode.names = { ...names };
            state.selectedNode.places = state.dataset.places;
            return;
        }
        state.selectedNode.names = names;
        state.selectedNode.code = elInpCode.value.trim() || null;
    }
    elNames?.addEventListener('input', () => {
        commitInputs();
        setDirty(true);
        clearTimeout(renderTimer);
        renderTimer = setTimeout(renderTree, 150);
    });
    [elInpCode].forEach(input => {
        input.addEventListener('input', () => {
            commitInputs();
            setDirty(true);
            clearTimeout(renderTimer);
            renderTimer = setTimeout(renderTree, 150);
        });
    });
    // Select node to inspect
    function selectNode(node, pathIndices = [], depth = 0, syncUrl = true, isPackRoot = false) {
        state.selectedNode = node;
        state.selectedRoot = Boolean(node && isPackRoot);
        state.selectedPath = pathIndices;

        if (syncUrl && state.packId) {
            const pathStr = state.selectedRoot ? 'root' : ((pathIndices && pathIndices.length > 0) ? pathIndices.join('-') : '');
            updateUrl(state.packId, pathStr, true);
        }

        if (!node) {
            elFormContainer.hidden = true;
            elEmptyState.hidden = false;
            elBreadcrumbs.innerHTML = '<span>Select the pack root or a place on the left to inspect and edit.</span>';
            renderSubplaces({ resetFilter: true });
            renderTree();
            return;
        }

        elFormContainer.hidden = false;
        elEmptyState.hidden = true;

        // Populate fields
        updateCodeLabelUI();
        renderNameFields(node.names || {});
        elCodeGroup.hidden = state.selectedRoot;
        elInpCode.value = node.code || '';
        elInpDepth.value = state.selectedRoot ? 'Pack root' : `Level ${depth + 1}`;
        document.getElementById('lblCurNodeDepth').textContent = state.selectedRoot ? 'Position' : 'Depth Level';
        document.getElementById('lblCurNodeType').textContent = state.selectedRoot ? 'Pack Type' : 'Unit Type';
        elDeleteNode.hidden = state.selectedRoot;

        if (state.selectedRoot) {
            const pill = document.createElement('span');
            pill.className = 'type-badge pack-root';
            pill.textContent = state.dataset.manifest?.pack_type === 'custom' ? 'Custom' : 'Country';
            elTypePills.replaceChildren(pill);
        } else {
            const rawType = node.type;
            const activeTypes = new Set(Array.isArray(rawType)
                ? rawType.map(type => String(type).toLowerCase())
                : [rawType ? String(rawType).toLowerCase() : 'place']);
            renderTypePills(activeTypes);
        }

        // Breadcrumbs
        renderBreadcrumbs(pathIndices);

        renderSubplaces({ resetFilter: true });
        // Highlight in tree
        renderTree();
    }

    function selectPackRoot(syncUrl = true) {
        const dataset = state.dataset;
        if (!dataset || state.datasetPackId !== state.packId) return false;
        const manifest = dataset.manifest || {};
        const language = manifest.default_lang || 'en';
        const names = getRootNames(manifest);
        names[language] ||= getRootName(manifest, language, manifest.pack_id || 'Pack');
        const root = {
            names,
            type: manifest.pack_type === 'custom' ? 'custom' : 'country',
            places: dataset.places,
        };
        selectNode(root, [], -1, syncUrl, true);
        return true;
    }

    function renderBreadcrumbs(pathIndices) {
        elBreadcrumbs.innerHTML = '';
        const home = document.createElement('a');
        const language = state.dataset.manifest.default_lang || 'en';
        home.textContent = getRootName(state.dataset.manifest, language, state.dataset.manifest.pack_id);
        home.className = 'geo-breadcrumb-link';
        home.setAttribute('role', 'button');
        home.tabIndex = 0;
        home.onclick = () => selectPackRoot(true);
        elBreadcrumbs.appendChild(home);

        let curr = getRootPlaces(state.dataset);
        const pathAccum = [];
        for (let i = 0; i < pathIndices.length; i++) {
            if (!Array.isArray(curr)) return;
            pathAccum.push(pathIndices[i]);
            const item = curr[pathIndices[i]];
            if (!item || typeof item !== 'object' || Array.isArray(item)) return;
            const separator = document.createElement('span');
            separator.textContent = '>';
            elBreadcrumbs.appendChild(separator);
            const a = document.createElement('a');
            a.textContent = item.names?.[language] || item.names?.en || 'Place';
            a.className = 'geo-breadcrumb-link';
            a.setAttribute('role', 'button');
            a.tabIndex = 0;
            const targetPath = [...pathAccum];
            const targetDepth = i;
            a.onclick = () => selectNode(item, targetPath, targetDepth, true);
            elBreadcrumbs.appendChild(a);

            if (!Array.isArray(item.places)) return;
            curr = item.places;
        }
    }

    return {
        selectNode,
        selectPackRoot,
        commitInputs,
        refreshNameFields: () => state.selectedRoot ? selectPackRoot(false) : renderNameFields(state.selectedNode?.names || {}),
        refreshTypeSelection: () => {
            if (!state.selectedNode || !elTypePills) return;
            if (state.selectedRoot) {
                const pill = document.createElement('span');
                pill.className = 'type-badge pack-root';
                pill.textContent = state.dataset.manifest?.pack_type === 'custom' ? 'Custom' : 'Country';
                elTypePills.replaceChildren(pill);
                return;
            }
            const rawType = state.selectedNode.type;
            const activeTypes = new Set(Array.isArray(rawType)
                ? rawType.map(type => String(type).toLowerCase())
                : [rawType ? String(rawType).toLowerCase() : 'place']);
            renderTypePills(activeTypes);
        },
    };
}
