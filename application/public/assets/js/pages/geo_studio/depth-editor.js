import { orderLanguagesByDefault } from './languages.js';

export function createDepthEditor(depthList, addButton, getLanguages = () => ['en'], getDefaultLanguage = () => 'en', renderIcon, isTypeKeyInUse = () => false) {
    const initialNames = label => {
        const language = getDefaultLanguage();
        return language ? { [language]: label } : {};
    };
    let depthLevels = [
        {
            depth: 1,
            types: [
                { key: 'state', names: initialNames('State / Province') }
            ]
        },
        {
            depth: 2,
            types: [
                { key: 'county', names: initialNames('County / District') }
            ]
        },
        {
            depth: 3,
            types: [
                { key: 'city', names: initialNames('City / Locality') }
            ]
        }
    ];

    function nextTypeKey(prefix) {
        const used = new Set(depthLevels.flatMap(level => (level.types || []).map(type => type.key)));
        let index = 1;
        while (used.has(`${prefix}_${index}`)) index++;
        return `${prefix}_${index}`;
    }

    function isTypeKeyAvailable(candidate, currentType) {
        return !depthLevels.some(level => (level.types || []).some(type => type !== currentType && type.key === candidate));
    }

    function renderDepthChain() {
        if (!depthList) return;
        depthList.innerHTML = '';
        const languages = orderLanguagesByDefault([...new Set(getLanguages())], getDefaultLanguage());

        depthLevels.forEach((lvl, lvlIdx) => {
            const card = document.createElement('div');
            card.className = 'depth-level-card';

            // Header
            const hdr = document.createElement('div');
            hdr.className = 'depth-level-header';

            const titleBox = document.createElement('div');
            titleBox.className = 'depth-level-title';
            const titleText = document.createElement('span');
            titleText.textContent = `Level ${lvlIdx + 1}`;
            titleBox.appendChild(titleText);
            hdr.appendChild(titleBox);

            const actions = document.createElement('div');
            actions.className = 'depth-level-actions';

            const btnAddType = document.createElement('button');
            btnAddType.type = 'button';
            btnAddType.className = 'sub-btn depth-add-type';
            btnAddType.textContent = '+ Add Type';
            btnAddType.onclick = () => {
                const nextTypeNum = (lvl.types || []).length + 1;
                lvl.types.push({
                    key: nextTypeKey('type'),
                    names: initialNames(`Type ${nextTypeNum}`),
                });
                renderDepthChain();
            };
            actions.appendChild(btnAddType);

            if (depthLevels.length > 1) {
                const btnDelLvl = document.createElement('button');
                btnDelLvl.type = 'button';
                btnDelLvl.className = 'depth-btn-remove';
                btnDelLvl.title = 'Remove Depth Level';
                btnDelLvl.disabled = lvl.types.some(type => isTypeKeyInUse(type.key));
                if (btnDelLvl.disabled) btnDelLvl.title = 'A type in this level is assigned to place records.';
                btnDelLvl.innerHTML = renderIcon('x', 12);
                btnDelLvl.onclick = () => {
                    depthLevels.splice(lvlIdx, 1);
                    depthLevels.forEach((l, i) => { l.depth = i + 1; });
                    renderDepthChain();
                };
                actions.appendChild(btnDelLvl);
            }
            hdr.appendChild(actions);
            card.appendChild(hdr);

            // Types Container
            const typesBox = document.createElement('div');
            typesBox.className = 'depth-types-box';

            const colHeaders = document.createElement('div');
            colHeaders.className = 'depth-type-headers';
            colHeaders.replaceChildren();
            ['Key / Slug', ...languages.map(language => `${language.toUpperCase()} Name`), ''].forEach((text, index, labels) => {
                const header = document.createElement('span');
                header.className = index === 0 ? 'depth-key-label'
                    : index === labels.length - 1 ? 'depth-action-label'
                        : 'depth-name-label';
                header.textContent = text;
                colHeaders.appendChild(header);
            });
            typesBox.appendChild(colHeaders);

            (lvl.types || []).forEach((t, tIdx) => {
                const row = document.createElement('div');
                row.className = 'depth-type-row';
                const inpKey = document.createElement('input');
                inpKey.type = 'text';
                inpKey.className = 'depth-inp depth-inp-key';
                inpKey.placeholder = 'key';
                inpKey.setAttribute('aria-label', `Level ${lvlIdx + 1} type key`);
                inpKey.maxLength = 64;
                inpKey.value = t.key || '';
                inpKey.disabled = isTypeKeyInUse(t.key);
                if (inpKey.disabled) inpKey.title = 'This type key is assigned to place records.';
                inpKey.oninput = event => {
                    const candidate = event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                    event.target.value = candidate;
                    const duplicate = candidate !== '' && !isTypeKeyAvailable(candidate, t);
                    event.target.setCustomValidity(duplicate ? 'Type keys must be unique across the hierarchy.' : '');
                    t.key = candidate;
                };

                row.appendChild(inpKey);
                t.names = t.names || {};
                Object.keys(t.names).forEach(language => {
                    if (!languages.includes(language)) delete t.names[language];
                });
                languages.forEach(language => {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'depth-inp depth-inp-name';
                    input.placeholder = `${language} name`;
                    input.setAttribute('aria-label', `Level ${lvlIdx + 1} ${language} type name`);
                    input.value = t.names[language] || '';
                    input.oninput = event => {
                        const value = event.target.value;
                        if (value) t.names[language] = value;
                        else delete t.names[language];
                        if (language === getDefaultLanguage() && (!t.key || t.key.startsWith('type_') || t.key === 'level_' + (lvlIdx + 1))) {
                            const generatedKey = value.toLowerCase().trim().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 64);
                            if (generatedKey && isTypeKeyAvailable(generatedKey, t)) {
                                t.key = generatedKey;
                                inpKey.value = t.key;
                                inpKey.setCustomValidity('');
                            }
                        }
                    };
                    row.appendChild(input);
                });

                if ((lvl.types || []).length > 1) {
                    const btnDelType = document.createElement('button');
                    btnDelType.type = 'button';
                    btnDelType.className = 'depth-btn-remove';
                    btnDelType.title = 'Remove Type';
                    btnDelType.disabled = isTypeKeyInUse(t.key);
                    if (btnDelType.disabled) btnDelType.title = 'This type is assigned to place records.';
                    btnDelType.innerHTML = renderIcon('x', 12);
                    btnDelType.onclick = () => {
                        lvl.types.splice(tIdx, 1);
                        renderDepthChain();
                    };
                    row.appendChild(btnDelType);
                } else {
                    const spacer = document.createElement('span');
                    spacer.className = 'depth-type-action-slot';
                    row.appendChild(spacer);
                }

                typesBox.appendChild(row);
            });

            card.appendChild(typesBox);
            depthList.appendChild(card);

            if (lvlIdx < depthLevels.length - 1) {
                const arrow = document.createElement('div');
                arrow.className = 'depth-arrow-down';
                arrow.innerHTML = renderIcon('arrow-down', 12);
                depthList.appendChild(arrow);
            }
        });
    }

    addButton.onclick = () => {
        const nextNum = depthLevels.length + 1;
        depthLevels.push({
            depth: nextNum,
            types: [
                { key: nextTypeKey('level'), names: initialNames(`Level ${nextNum}`) }
            ]
        });
        renderDepthChain();
        depthList.scrollTop = depthList.scrollHeight;
    };
    return {
        render: renderDepthChain,
        getLevels: () => depthLevels,
        setLevels(levels) {
            depthLevels = Array.isArray(levels) ? levels.map((level, index) => {
                const source = level && typeof level === 'object' && !Array.isArray(level) ? level : {};
                const types = Array.isArray(source.types) ? source.types : [];
                return {
                    ...source,
                    depth: Number.isInteger(source.depth) && source.depth > 0 ? source.depth : index + 1,
                    types: types.map(type => {
                        const definition = type && typeof type === 'object' && !Array.isArray(type) ? type : {};
                        const names = definition.names && typeof definition.names === 'object' && !Array.isArray(definition.names)
                            ? { ...definition.names }
                            : {};
                        return { ...definition, names };
                    }),
                };
            }) : [];
            renderDepthChain();
        },
        setPackType(packType) {
            depthLevels = packType === 'custom'
                ? [
                    { depth: 1, types: [{ key: 'zone', names: initialNames('Zone / Camp') }] },
                    { depth: 2, types: [{ key: 'block', names: initialNames('Block / Sector') }] },
                    { depth: 3, types: [
                        { key: 'unit', names: initialNames('Unit / Shelter') },
                        { key: 'facility', names: initialNames('Health Facility') }
                    ] }
                ]
                : [
                    { depth: 1, types: [{ key: 'state', names: initialNames('State / Province') }] },
                    { depth: 2, types: [{ key: 'county', names: initialNames('County / District') }] },
                    { depth: 3, types: [{ key: 'city', names: initialNames('City / Locality') }] }
                ];
            renderDepthChain();
        }
    };
}
