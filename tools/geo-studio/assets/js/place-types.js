export function initializePlaceTypes({ state, setDirty, renderTree, updateStats }) {
    const elTypePills = document.getElementById('typePillsContainer');

    function formatTypeLabel(t) {
        const s = String(t || 'place').trim().toLowerCase();
        if (state.dataset && state.dataset.hierarchy && Array.isArray(state.dataset.hierarchy.levels)) {
            for (const lvl of state.dataset.hierarchy.levels) {
                for (const item of (lvl.types || [])) {
                    if (String(item.key).toLowerCase() === s) {
                        return item.names?.[state.dataset.manifest?.default_lang || 'en'] || s;
                    }
                }
            }
        }
        return s || 'place';
    }

    function getActiveCodeLabel() {
        if (!state.dataset || !state.dataset.manifest) return 'Postal Code';
        return state.dataset.manifest.code_label || (state.dataset.manifest.pack_type === 'custom' ? 'Block Code' : 'Postal Code');
    }

    function updateCodeLabelUI() {
        const codeLabel = getActiveCodeLabel();
        const isNone = (String(codeLabel).toLowerCase() === 'none');
        const displayLabel = isNone ? 'Code / Identifier (Optional)' : codeLabel;

        const lblCurNode = document.getElementById('lblCurNodeCode');
        if (lblCurNode) {
            lblCurNode.textContent = displayLabel;
        }
        const inpCode = document.getElementById('inpCode');
        if (inpCode) {
            inpCode.placeholder = isNone ? 'Optional code / ID' : `e.g. ${codeLabel === 'Block Code' ? 'BLK-01' : (codeLabel === 'ZIP Code' ? '90210' : '1216')}`;
        }
        const thSub = document.getElementById('thSubplacesCode');
        if (thSub) {
            thSub.textContent = isNone ? 'Code' : codeLabel;
        }
        const treeInp = document.getElementById('treeSearchInput');
        if (treeInp) {
            treeInp.placeholder = `Filter by any language name or ${isNone ? 'code' : codeLabel}...`;
        }
    }

    function getConfiguredTypes() {
        const levels = state.dataset?.hierarchy?.levels;
        const selectedDepth = Array.isArray(state.selectedPath) ? state.selectedPath.length : 0;
        if (!Array.isArray(levels) || selectedDepth < 1) return [];
        const types = levels[selectedDepth - 1]?.types;
        return Array.isArray(types) ? types : [];
    }

    function commitTypes() {
        if (!state.selectedNode) return;
        const values = [...elTypePills.querySelectorAll('.type-pill.active')].map(pill => pill.dataset.type);
        if (values.length === 0) {
            const currentTypes = Array.isArray(state.selectedNode.type)
                ? state.selectedNode.type
                : [state.selectedNode.type];
            renderTypePills(new Set(currentTypes.map(type => String(type).toLowerCase())));
            return;
        }
        state.selectedNode.type = values.length > 1 ? values : values[0];
        renderTree();
        updateStats();
    }

    function renderTypePills(activeTypesSet = new Set()) {
        if (!elTypePills) return;
        elTypePills.replaceChildren();
        const defaultLanguage = state.dataset?.manifest?.default_lang || 'en';
        const definitions = getConfiguredTypes();
        if (definitions.length === 0) {
            const message = document.createElement('span');
            message.className = 'geo-field-hint';
            message.textContent = `No types are defined for Level ${state.selectedPath?.length || 1}.`;
            elTypePills.appendChild(message);
            return;
        }

        definitions.forEach(definition => {
            const typeKey = String(definition.key).toLowerCase();
            const pill = document.createElement('button');
            const isActive = activeTypesSet.has(typeKey);
            pill.type = 'button';
            pill.className = `type-pill${isActive ? ' active' : ''}`;
            pill.dataset.type = typeKey;
            pill.textContent = definition.names?.[defaultLanguage] || typeKey;
            pill.setAttribute('aria-pressed', String(isActive));
            pill.onclick = () => {
                pill.classList.toggle('active');
                pill.setAttribute('aria-pressed', String(pill.classList.contains('active')));
                commitTypes();
                setDirty(true);
            };
            elTypePills.appendChild(pill);
        });
    }

    return { formatTypeLabel, updateCodeLabelUI, renderTypePills };
}
