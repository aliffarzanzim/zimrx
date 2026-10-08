        (function() {
            const ZimRxIcon = {
                render(name, size = 14, attrs = {}) {
                    const map = window.ZimRxIconsMap || {};
                    const key = String(name || '').toLowerCase().trim();
                    const content = map[key] || map['hash'] || '';
                    const strokeWidth = attrs['stroke-width'] || attrs.strokeWidth || '2';
                    const cls = attrs['class'] || attrs.className || 'zrx-icon';
                    const color = attrs.color || 'currentColor';
                    return `<svg class="${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="${strokeWidth}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${content}</svg>`;
                }
            };
            window.ZimRxIcon = ZimRxIcon;

            let activeDataset = null;
            let currentCountryCode = '';
            let activeContributorName = localStorage.getItem('zimrx_geo_contributor') || 'Alif Farzan Zim';
            let selectedNodeRef = null;
            let selectedNodePath = [];
            let expandedKeys = new Set();

            const elCountrySelect = document.getElementById('countrySelect');
            const elTreeContainer = document.getElementById('treeContainer');
            const elTreeSearch = document.getElementById('treeSearchInput');
            const elFormContainer = document.getElementById('editorFormContainer');
            const elEmptyState = document.getElementById('editorEmptyState');
            const elBreadcrumbs = document.getElementById('editorBreadcrumbs');
            const elInpNameEn = document.getElementById('inpNameEn');
            const elInpNameLoc = document.getElementById('inpNameLoc');
            const elInpPostcode = document.getElementById('inpPostcode');
            const elInpDepth = document.getElementById('inpDepth');
            const elTypePills = document.getElementById('typePillsContainer');
            const elSubplacesBody = document.getElementById('subplacesTableBody');
            const elSubplacesCount = document.getElementById('subPlacesCount');
            const elSubplacesFilter = document.getElementById('subplacesFilterInput');
            // Dynamic place types registry
            const basePlaceTypes = [
                'state', 'province', 'division', 'county', 'district', 'city',
                'upazila', 'thana', 'union', 'postoffice', 'place', 'village',
                'area', 'ward', 'moholla', 'municipality', 'canton', 'borough', 'parish'
            ];
            let availablePlaceTypes = new Set(basePlaceTypes);

            function formatTypeLabel(t) {
                if (!t) return 'Place';
                const s = String(t).trim().toLowerCase();
                if (activeDataset && activeDataset.hierarchy && Array.isArray(activeDataset.hierarchy.levels)) {
                    for (const lvl of activeDataset.hierarchy.levels) {
                        for (const item of (lvl.types || [])) {
                            if (String(item.key).toLowerCase() === s) {
                                return item.name_en || s;
                            }
                        }
                    }
                }
                if (s === 'postoffice') return 'Post Office';
                if (s === 'upazila') return 'Upazila';
                return s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            }

            function extractDatasetTypes(dataset) {
                const types = new Set(basePlaceTypes);
                if (!dataset) return types;

                const levels = dataset.hierarchy?.levels || [];
                levels.forEach(lvl => {
                    if (Array.isArray(lvl.types)) {
                        lvl.types.forEach(t => {
                            if (t && t.key) types.add(String(t.key).toLowerCase());
                        });
                    }
                });

                function scan(node) {
                    if (Array.isArray(node.type)) {
                        node.type.forEach(t => { if (t) types.add(String(t).toLowerCase()); });
                    } else if (typeof node.type === 'string' && node.type) {
                        types.add(node.type.toLowerCase());
                    }
                    const kids = node.places || node.children || [];
                    kids.forEach(scan);
                }
                (dataset.places || []).forEach(scan);
                return types;
            }

            function getActiveCodeLabel() {
                if (!activeDataset || !activeDataset.manifest) return 'Postal Code';
                return activeDataset.manifest.code_label || (activeDataset.manifest.pack_type === 'custom' ? 'Block Code' : 'Postal Code');
            }

            function updateCodeLabelUI() {
                const codeLabel = getActiveCodeLabel();
                const isNone = (String(codeLabel).toLowerCase() === 'none');
                const displayLabel = isNone ? 'Code / Identifier (Optional)' : codeLabel;

                const lblCurNode = document.getElementById('lblCurNodePostcode');
                if (lblCurNode) {
                    lblCurNode.textContent = displayLabel;
                }
                const inpPostcode = document.getElementById('inpPostcode');
                if (inpPostcode) {
                    inpPostcode.placeholder = isNone ? 'Optional code / ID' : `e.g. ${codeLabel === 'Block Code' ? 'BLK-01' : (codeLabel === 'ZIP Code' ? '90210' : '1216')}`;
                }
                const thSub = document.getElementById('thSubplacesPostcode');
                if (thSub) {
                    thSub.textContent = isNone ? 'Code' : codeLabel;
                }
                const treeInp = document.getElementById('treeSearchInput');
                if (treeInp) {
                    treeInp.placeholder = `Filter by English, Local Name, or ${isNone ? 'Code' : codeLabel}...`;
                }
            }

            function renderTypePills(activeTypesSet = new Set()) {
                elTypePills.innerHTML = '';
                const sorted = Array.from(availablePlaceTypes).sort((a, b) => a.localeCompare(b));
                sorted.forEach(t => {
                    const pill = document.createElement('span');
                    const isActive = activeTypesSet.has(t.toLowerCase());
                    pill.className = 'type-pill' + (isActive ? ' active' : '');
                    pill.setAttribute('data-type', t);
                    pill.textContent = formatTypeLabel(t);
                    pill.onclick = () => {
                        pill.classList.toggle('active');
                        setDirty(true);
                    };
                    elTypePills.appendChild(pill);
                });
            }

            function addNewTypeTag() {
                const inp = document.getElementById('inpNewTypeName');
                const raw = inp.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
                if (!raw) return;
                availablePlaceTypes.add(raw);

                const activeTypes = new Set();
                elTypePills.querySelectorAll('.type-pill.active').forEach(p => {
                    activeTypes.add(p.getAttribute('data-type').toLowerCase());
                });
                activeTypes.add(raw);

                renderTypePills(activeTypes);
                inp.value = '';
                setDirty(true);
                showToast(`Added place classification "${formatTypeLabel(raw)}"`);
            }

            document.getElementById('btnAddTypeTag').onclick = addNewTypeTag;
            document.getElementById('inpNewTypeName').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addNewTypeTag();
                }
            });

            function showToast(msg) {
                const toast = document.getElementById('geoToast');
                toast.textContent = msg;
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 2800);
            }

            // Dirty State Tracker
            let isDirty = false;

            function setDirty(dirty) {
                isDirty = !!dirty;
                const btnSave = document.getElementById('btnSaveJson');
                if (btnSave) {
                    btnSave.disabled = !isDirty;
                    btnSave.title = isDirty
                        ? 'Save JSON file and update timestamp (Unsaved Changes) (Ctrl+S)'
                        : 'Save JSON file (No changes to save)';
                }
            }

            // Router & View Switcher
            function showView(mode) {
                const hubView = document.getElementById('viewHub');
                const editorView = document.getElementById('viewEditor');
                const btnHome = document.getElementById('btnEditorHome');
                const btnManifest = document.getElementById('btnOpenManifest');
                const btnSave = document.getElementById('btnSaveJson');
                const btnCompileToggle = document.getElementById('btnCompileToggle');

                if (mode === 'hub') {
                    hubView.style.display = 'block';
                    editorView.style.display = 'none';
                    if (elCountrySelect) elCountrySelect.value = '';
                    if (btnHome) btnHome.classList.add('active');
                    if (btnManifest) btnManifest.disabled = true;
                    if (btnSave) btnSave.disabled = true;
                    if (btnCompileToggle) btnCompileToggle.disabled = true;
                    document.title = 'Geo Studio - Countries Directory';
                } else {
                    hubView.style.display = 'none';
                    editorView.style.display = 'flex';
                    if (btnHome) btnHome.classList.remove('active');
                    if (btnManifest) btnManifest.disabled = false;
                    if (btnSave) btnSave.disabled = !isDirty;
                    if (btnCompileToggle) btnCompileToggle.disabled = false;
                }
            }

            function updateUrl(country, pathStr = '', push = true) {
                const params = new URLSearchParams();
                if (country) {
                    params.set('country', country);
                    if (pathStr) {
                        params.set('path', pathStr);
                    }
                }
                const newQuery = params.toString() ? ('?' + params.toString()) : window.location.pathname;
                const currentQuery = window.location.search || window.location.pathname;

                if (newQuery !== currentQuery) {
                    if (push) {
                        history.pushState({ country, path: pathStr }, '', newQuery);
                    } else {
                        history.replaceState({ country, path: pathStr }, '', newQuery);
                    }
                }
            }

            let allHubCountries = [];

            async function loadCountriesHub() {
                const grid = document.getElementById('hubCardsGrid');
                grid.innerHTML = '<div class="geo-empty-state" style="grid-column: 1 / -1;">Loading available country catalogs...</div>';
                try {
                    const res = await fetch('index.php?action=list_countries');
                    const json = await res.json();
                    if (!json.success) {
                        grid.innerHTML = `<div class="geo-empty-state" style="grid-column: 1 / -1;">Failed to load countries: ${escapeHtml(json.error || '')}</div>`;
                        return;
                    }

                    allHubCountries = json.countries || [];
                    updateHubGlobalStats(allHubCountries);
                    renderHubCards(allHubCountries);

                    // Update editor country dropdown options with dash default
                    elCountrySelect.innerHTML = '<option value="">-</option>';
                    allHubCountries.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.code;
                        opt.textContent = `${c.name} (${c.code})`;
                        elCountrySelect.appendChild(opt);
                    });
                    if (currentCountryCode) {
                        elCountrySelect.value = currentCountryCode;
                    } else {
                        elCountrySelect.value = '';
                    }
                } catch (e) {
                    grid.innerHTML = `<div class="geo-empty-state" style="grid-column: 1 / -1;">Network error loading directory: ${escapeHtml(e.message)}</div>`;
                }
            }

            function updateHubGlobalStats(countries) {
                let totalPacks = countries.length;
                let globalPlaces = 0;
                let globalPostcodes = 0;

                countries.forEach(c => {
                    globalPlaces += (c.total_places || 0);
                    globalPostcodes += (c.postcode_count || 0);
                });

                document.getElementById('hubTotalPacks').textContent = totalPacks.toLocaleString();
                document.getElementById('hubGlobalPlaces').textContent = globalPlaces.toLocaleString();
                document.getElementById('hubGlobalPostcodes').textContent = globalPostcodes.toLocaleString();
            }

            function renderHubCards(countries) {
                const grid = document.getElementById('hubCardsGrid');
                grid.innerHTML = '';

                const filterVal = (document.getElementById('hubSearchInput')?.value || '').trim().toLowerCase();

                const filtered = countries.filter(c => {
                    if (!filterVal) return true;
                    const code = (c.code || '').toLowerCase();
                    const name = (c.name || '').toLowerCase();
                    const author = (c.author || '').toLowerCase();
                    return code.includes(filterVal) || name.includes(filterVal) || author.includes(filterVal);
                });

                if (filtered.length === 0) {
                    grid.innerHTML = '<div class="geo-empty-state" style="grid-column: 1 / -1;">No country packs found matching filter. Click "+ New / Import" to add one!</div>';
                    return;
                }

                filtered.forEach(c => {
                    const card = document.createElement('div');
                    card.className = 'country-card';

                    const topTypesHtml = Object.entries(c.top_types || {})
                        .map(([tKey, tCount]) => `<span class="c-stat-pill">${escapeHtml(formatTypeLabel(tKey))}: <strong>${tCount.toLocaleString()}</strong></span>`)
                        .join(' ');

                    const contributorsList = Array.isArray(c.contributors) ? c.contributors.join(', ') : (c.author || 'Alif Farzan Zim');
                    const formattedSize = c.filesize > 1048576 
                        ? (c.filesize / 1048576).toFixed(1) + ' MB' 
                        : (c.filesize / 1024).toFixed(0) + ' KB';

                    const isNat = (c.pack_type === 'national') || (c.code && c.code.length === 2 && !c.pack_type);
                    const scopeBadge = isNat 
                        ? '<span class="scope-pill scope-national">National</span>' 
                        : '<span class="scope-pill scope-custom">Custom</span>';

                    card.innerHTML = `
                        <div class="country-card-header">
                            <div class="country-card-identity">
                                <div>
                                    <div class="country-card-title">${escapeHtml(c.name)}</div>
                                    <div style="font-size:11px; color:var(--zrx-text-muted);">${formattedSize}</div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:6px;">
                                ${scopeBadge}
                                <span class="country-card-ver">v${escapeHtml(c.version || '1.0.0')}</span>
                            </div>
                        </div>
                        <div class="country-card-body">
                            <div style="display:flex; align-items:baseline; gap:8px;">
                                <span style="font-size:22px; font-weight:800; color:var(--zrx-text-dark);">${(c.total_places || 0).toLocaleString()}</span>
                                <span style="font-size:12px; font-weight:600; color:var(--zrx-text-muted);">Address Units</span>
                                ${c.postcode_count ? `<span style="font-size:11px; color:#16a34a; font-weight:600; margin-left:auto;">${c.postcode_count.toLocaleString()} ${escapeHtml(c.code_label ? (c.code_label.endsWith('s') ? c.code_label : c.code_label + 's') : (c.pack_type === 'custom' ? 'Unit Codes' : 'Postal Codes'))}</span>` : ''}
                            </div>
                            <div class="country-stat-badge-row">
                                ${topTypesHtml}
                            </div>
                            <div class="country-card-meta">
                                <div><strong>Author:</strong> ${escapeHtml(c.author || 'Alif Farzan Zim')}</div>
                                <div><strong>Contributors:</strong> ${escapeHtml(contributorsList)}</div>
                                <div><strong>License:</strong> ${escapeHtml(c.license || 'CC-BY-4.0')}</div>
                                <div style="display:flex; justify-content:space-between; margin-top:2px;">
                                    <span><strong>Released:</strong> ${escapeHtml(c.release_date || c.last_updated || '')}</span>
                                    <span><strong>Updated:</strong> ${escapeHtml(c.last_updated || '')}</span>
                                </div>
                            </div>
                        </div>
                        <div class="country-card-footer">
                            <button type="button" class="geo-btn geo-btn-primary btn-open-country" data-code="${escapeHtml(c.code)}" style="width:100%; justify-content:center;">
                                <span>Open Editor</span> ${ZimRxIcon.render('arrow-right', 12)}
                            </button>
                        </div>
                    `;

                    card.querySelector('.btn-open-country').onclick = () => {
                        updateUrl(c.code, '', true);
                        handleUrlRoute();
                    };

                    grid.appendChild(card);
                });
            }

            document.getElementById('hubSearchInput')?.addEventListener('input', () => {
                renderHubCards(allHubCountries);
            });

            // Load active country
            async function loadCountry(code, targetPathStr = '') {
                currentCountryCode = code;
                showView('editor');
                elTreeContainer.innerHTML = '<div class="geo-empty-state">Loading dataset...</div>';
                try {
                    const res = await fetch(`index.php?action=load_country&code=${encodeURIComponent(code)}`);
                    const json = await res.json();
                    if (!json.success) {
                        showToast(json.error || 'Failed to load country');
                        return;
                    }
                    activeDataset = json.data;
                    availablePlaceTypes = extractDatasetTypes(activeDataset);
                    updateStats();
                    updateCodeLabelUI();
                    setDirty(false);

                    // Update editor country dropdown
                    if (elCountrySelect) elCountrySelect.value = code;
                    document.title = `Geo Studio - ${activeDataset.manifest?.country_name || code} (${code})`;

                    if (targetPathStr) {
                        navigateToPath(targetPathStr);
                    } else {
                        expandedKeys.clear();
                        selectedNodeRef = null;
                        selectedNodePath = [];
                        selectNode(null, [], 0, false);
                    }
                } catch (e) {
                    showToast('Network error loading country');
                }
            }

            function navigateToPath(targetPathStr) {
                if (!activeDataset || !activeDataset.places) return;
                const pathIndices = targetPathStr.split('-').map(x => parseInt(x, 10)).filter(x => !isNaN(x));
                if (pathIndices.length === 0) {
                    selectNode(null, [], 0, false);
                    return;
                }

                let currList = activeDataset.places;
                let targetNode = null;
                const accumulated = [];

                for (let i = 0; i < pathIndices.length; i++) {
                    const idx = pathIndices[i];
                    if (!currList || !currList[idx]) break;
                    targetNode = currList[idx];
                    accumulated.push(idx);
                    expandedKeys.add(accumulated.join('-'));
                    currList = targetNode.places || targetNode.children || [];
                }

                renderTree();

                if (targetNode) {
                    selectNode(targetNode, pathIndices, pathIndices.length - 1, false);
                }
            }

            async function handleUrlRoute() {
                const params = new URLSearchParams(window.location.search);
                const country = params.get('country') || '';
                const path = params.get('path') || '';

                if (allHubCountries.length === 0) {
                    await loadCountriesHub();
                }

                if (country) {
                    if (currentCountryCode !== country || !activeDataset) {
                        await loadCountry(country, path);
                    } else if (path) {
                        navigateToPath(path);
                    } else {
                        expandedKeys.clear();
                        selectedNodeRef = null;
                        selectedNodePath = [];
                        selectNode(null, [], 0, false);
                    }
                } else {
                    currentCountryCode = '';
                    activeDataset = null;
                    showView('hub');
                }
            }

            elCountrySelect.addEventListener('change', () => {
                const newCode = elCountrySelect.value;
                if (newCode && newCode !== currentCountryCode) {
                    if (isDirty && !confirm('You have unsaved changes in this country pack. Discard changes and switch?')) {
                        elCountrySelect.value = currentCountryCode;
                        return;
                    }
                    updateUrl(newCode, '', true);
                    handleUrlRoute();
                }
            });

            document.getElementById('brandHomeLink').onclick = () => {
                if (isDirty && !confirm('You have unsaved changes in this country pack. Discard changes and return Home?')) {
                    return;
                }
                updateUrl('', '', true);
                handleUrlRoute();
            };

            document.getElementById('btnEditorHome').onclick = () => {
                if (isDirty && !confirm('You have unsaved changes in this country pack. Discard changes and return Home?')) {
                    return;
                }
                updateUrl('', '', true);
                handleUrlRoute();
            };

            const btnHubHero = document.getElementById('btnHubNewImportHero');
            if (btnHubHero) {
                btnHubHero.onclick = () => openImportModal();
            }

            // Calculate dataset stats & manifest info
            function updateStats() {
                if (!activeDataset || !activeDataset.places) return;
                const counts = {};
                let total = 0;
                let multiType = 0;

                function walk(node) {
                    total++;
                    const t = node.type;
                    if (Array.isArray(t)) {
                        multiType++;
                        t.forEach(k => {
                            if (k) {
                                const lk = String(k).toLowerCase();
                                counts[lk] = (counts[lk] || 0) + 1;
                            }
                        });
                    } else if (typeof t === 'string' && t) {
                        const lk = t.toLowerCase();
                        counts[lk] = (counts[lk] || 0) + 1;
                    }

                    const kids = node.places || node.children || [];
                    kids.forEach(walk);
                }

                activeDataset.places.forEach(walk);

                const elBar = document.getElementById('statsBar');
                let pillsHtml = `<span class="geo-stat-pill">Total: <strong>${total.toLocaleString()}</strong></span>`;

                const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]);
                sorted.forEach(([typeKey, count]) => {
                    pillsHtml += `<span class="geo-stat-pill">${escapeHtml(formatTypeLabel(typeKey))}: <strong>${count.toLocaleString()}</strong></span>`;
                });
                if (multiType > 0) {
                    pillsHtml += `<span class="geo-stat-pill">Multi-Type: <strong>${multiType.toLocaleString()}</strong></span>`;
                }

                const m = activeDataset.manifest || {};
                const author = escapeHtml(m.author || activeContributorName);
                const updated = escapeHtml(m.last_updated || new Date().toISOString().slice(0, 10));
                pillsHtml += `
                    <div class="geo-manifest-badge" id="statManifestBadge">
                        <span>Author: <strong>${author}</strong></span>
                        <span>Updated: <strong>${updated}</strong></span>
                    </div>
                `;
                elBar.innerHTML = pillsHtml;
            }

            // Render interactive tree
            function renderTree() {
                if (!activeDataset || !activeDataset.places) return;
                const query = elTreeSearch.value.trim().toLowerCase();
                elTreeContainer.innerHTML = '';

                let matchCount = 0;
                const frag = document.createDocumentFragment();

                function buildNodeEl(node, pathIndices, depth) {
                    const kids = node.places || node.children || [];
                    const hasKids = kids.length > 0;
                    const pathKey = pathIndices.join('-');

                    const en = (node.en || '').toLowerCase();
                    const loc = (node.loc || '').toLowerCase();
                    const pc = (node.postcode || '').toLowerCase();

                    // Search filter check
                    let isSelfMatch = true;
                    if (query) {
                        isSelfMatch = en.includes(query) || loc.includes(query) || pc.includes(query);
                    }

                    let hasMatchingDescendant = false;
                    if (query && hasKids) {
                        function checkKids(subNode) {
                            const sEn = (subNode.en || '').toLowerCase();
                            const sLoc = (subNode.loc || '').toLowerCase();
                            const sPc = (subNode.postcode || '').toLowerCase();
                            if (sEn.includes(query) || sLoc.includes(query) || sPc.includes(query)) return true;
                            const subKids = subNode.places || subNode.children || [];
                            return subKids.some(checkKids);
                        }
                        hasMatchingDescendant = kids.some(checkKids);
                    }

                    if (query && !isSelfMatch && !hasMatchingDescendant) {
                        return null;
                    }

                    matchCount++;

                    const isExpanded = query ? true : expandedKeys.has(pathKey);
                    const isSelected = selectedNodePath.join('-') === pathKey;

                    const row = document.createElement('div');
                    row.className = 'tree-row' + (isSelected ? ' selected' : '');
                    row.style.paddingLeft = `${depth * 14 + 8}px`;

                    const caret = document.createElement('span');
                    caret.className = 'tree-caret';
                    if (hasKids) {
                        caret.innerHTML = ZimRxIcon.render(isExpanded ? 'chevron-down' : 'chevron-right', 11);
                        caret.onclick = (e) => {
                            e.stopPropagation();
                            if (expandedKeys.has(pathKey)) {
                                expandedKeys.delete(pathKey);
                            } else {
                                expandedKeys.add(pathKey);
                            }
                            renderTree();
                        };
                    } else {
                        caret.innerHTML = '<span class="tree-bullet"></span>';
                    }
                    row.appendChild(caret);

                    // Type Badge
                    const rawType = node.type;
                    const badge = document.createElement('span');
                    if (Array.isArray(rawType)) {
                        badge.className = 'type-badge dual';
                        badge.textContent = rawType.map(formatTypeLabel).join('+');
                    } else {
                        const lk = (rawType || 'place').toLowerCase();
                        badge.className = `type-badge ${lk}`;
                        badge.textContent = formatTypeLabel(rawType || 'place');
                    }
                    row.appendChild(badge);

                    // Title
                    const title = document.createElement('span');
                    title.className = 'tree-title';
                    title.innerHTML = `<span>${escapeHtml(node.en || 'Untitled')}</span>` + 
                        (node.loc ? `<span class="tree-loc">${escapeHtml(node.loc)}</span>` : '');
                    row.appendChild(title);

                    // Postcode badge
                    if (node.postcode) {
                        const pcSpan = document.createElement('span');
                        pcSpan.className = 'postcode-badge';
                        pcSpan.textContent = node.postcode;
                        row.appendChild(pcSpan);
                    }

                    // Kids count
                    if (hasKids) {
                        const cnt = document.createElement('span');
                        cnt.className = 'tree-child-count';
                        cnt.textContent = kids.length;
                        row.appendChild(cnt);
                    }

                    row.onclick = () => {
                        selectNode(node, pathIndices, depth, true);
                    };

                    const container = document.createElement('div');
                    container.appendChild(row);

                    if (hasKids && isExpanded) {
                        kids.forEach((child, idx) => {
                            const childEl = buildNodeEl(child, [...pathIndices, idx], depth + 1);
                            if (childEl) container.appendChild(childEl);
                        });
                    }

                    return container;
                }

                activeDataset.places.forEach((rootPlace, idx) => {
                    const el = buildNodeEl(rootPlace, [idx], 0);
                    if (el) frag.appendChild(el);
                });

                elTreeContainer.appendChild(frag);
                document.getElementById('treeFilteredCount').textContent = query ? `${matchCount} matches` : '';
            }

            elTreeSearch.addEventListener('input', () => {
                renderTree();
            });

            document.getElementById('btnExpandAll').onclick = () => {
                if (!activeDataset || !activeDataset.places) return;
                function expandWalk(node, path) {
                    const kids = node.places || node.children || [];
                    if (kids.length > 0) {
                        expandedKeys.add(path.join('-'));
                        kids.forEach((k, i) => expandWalk(k, [...path, i]));
                    }
                }
                activeDataset.places.forEach((p, i) => expandWalk(p, [i]));
                renderTree();
            };

            document.getElementById('btnCollapseAll').onclick = () => {
                expandedKeys.clear();
                renderTree();
            };

            // Select node to inspect
            function selectNode(node, pathIndices = [], depth = 0, syncUrl = true) {
                selectedNodeRef = node;
                selectedNodePath = pathIndices;

                if (syncUrl && currentCountryCode) {
                    const pathStr = (pathIndices && pathIndices.length > 0) ? pathIndices.join('-') : '';
                    updateUrl(currentCountryCode, pathStr, true);
                }

                if (!node) {
                    elFormContainer.style.display = 'none';
                    elEmptyState.style.display = 'block';
                    elBreadcrumbs.innerHTML = '<span>Select a place on the left to inspect and edit.</span>';
                    renderTree();
                    return;
                }

                elFormContainer.style.display = 'flex';
                elEmptyState.style.display = 'none';

                // Populate fields
                updateCodeLabelUI();
                elInpNameEn.value = node.en || '';
                elInpNameLoc.value = node.loc || '';
                elInpPostcode.value = node.postcode || node.code || '';
                elInpDepth.value = `Level ${depth + 1}`;

                // Dynamic Type pills
                const rawType = node.type;
                const activeTypes = new Set(Array.isArray(rawType) ? rawType.map(x => String(x).toLowerCase()) : [rawType ? String(rawType).toLowerCase() : 'place']);
                activeTypes.forEach(t => { if (t) availablePlaceTypes.add(t); });
                renderTypePills(activeTypes);

                // Breadcrumbs
                renderBreadcrumbs(pathIndices);

                // Sub-places list
                if (elSubplacesFilter) {
                    elSubplacesFilter.value = '';
                }
                renderSubplacesTable();

                // Highlight in tree
                renderTree();
            }

            function renderBreadcrumbs(pathIndices) {
                elBreadcrumbs.innerHTML = '';
                const home = document.createElement('a');
                home.textContent = activeDataset.manifest.country_name || currentCountryCode;
                home.style.cursor = 'pointer';
                home.onclick = () => selectNode(null, [], 0, true);
                elBreadcrumbs.appendChild(home);

                let curr = activeDataset.places;
                const pathAccum = [];
                for (let i = 0; i < pathIndices.length; i++) {
                    const sep = document.createElement('span');
                    sep.textContent = '>';
                    elBreadcrumbs.appendChild(sep);

                    pathAccum.push(pathIndices[i]);
                    const item = curr[pathIndices[i]];
                    const a = document.createElement('a');
                    a.textContent = item.en || 'Place';
                    a.style.cursor = 'pointer';
                    const targetPath = [...pathAccum];
                    const targetDepth = i;
                    a.onclick = () => selectNode(item, targetPath, targetDepth, true);
                    elBreadcrumbs.appendChild(a);

                    curr = item.places || item.children || [];
                }
            }

            // Sub-places table
            function renderSubplacesTable() {
                if (!selectedNodeRef) return;
                const kids = selectedNodeRef.places || selectedNodeRef.children || [];
                selectedNodeRef.places = kids; // Normalize key to places
                elSubplacesBody.innerHTML = '';

                if (kids.length === 0) {
                    elSubplacesCount.textContent = '0';
                    const tr = document.createElement('tr');
                    tr.innerHTML = '<td colspan="5" style="text-align:center; color:var(--zrx-text-muted); padding:16px;">No sub-places nested here. Click "+ Add Child Place" to add one.</td>';
                    elSubplacesBody.appendChild(tr);
                    return;
                }

                const filterVal = elSubplacesFilter ? elSubplacesFilter.value.trim().toLowerCase() : '';
                let renderedCount = 0;

                kids.forEach((child, idx) => {
                    if (filterVal) {
                        const en = (child.en || '').toLowerCase();
                        const loc = (child.loc || '').toLowerCase();
                        const pc = (child.code || child.postcode || '').toLowerCase();
                        if (!en.includes(filterVal) && !loc.includes(filterVal) && !pc.includes(filterVal)) {
                            return;
                        }
                    }

                    renderedCount++;
                    const tr = document.createElement('tr');
                    const isDual = Array.isArray(child.type);
                    const displayType = isDual ? child.type.map(formatTypeLabel).join('+') : formatTypeLabel(child.type || 'place');
                    const badgeCls = isDual ? 'dual' : String(child.type || 'place').toLowerCase();
                    const displayPc = escapeHtml(child.code || child.postcode || '-');
                    tr.innerHTML = `
                        <td style="font-weight:600;">${escapeHtml(child.en || '')}</td>
                        <td style="color:var(--zrx-text-muted);">${escapeHtml(child.loc || '')}</td>
                        <td><span class="type-badge ${escapeHtml(badgeCls)}">${escapeHtml(displayType)}</span></td>
                        <td><span class="postcode-badge">${displayPc}</span></td>
                        <td style="text-align:center;">
                            <button type="button" class="sub-btn btn-inspect" data-idx="${idx}">Inspect</button>
                            <button type="button" class="sub-btn sub-btn-del btn-del-child" data-idx="${idx}" title="Delete Sub-place">${ZimRxIcon.render('trash', 12)}</button>
                        </td>
                    `;
                    elSubplacesBody.appendChild(tr);
                });

                elSubplacesCount.textContent = filterVal ? `${renderedCount} of ${kids.length}` : kids.length;

                if (renderedCount === 0 && filterVal) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td colspan="5" style="text-align:center; color:var(--zrx-text-muted); padding:16px;">No sub-places matching "${escapeHtml(filterVal)}".</td>`;
                    elSubplacesBody.appendChild(tr);
                }

                elSubplacesBody.querySelectorAll('.btn-inspect').forEach(btn => {
                    btn.onclick = () => {
                        const idx = parseInt(btn.getAttribute('data-idx'), 10);
                        const child = selectedNodeRef.places[idx];
                        const childPath = [...selectedNodePath, idx];
                        expandedKeys.add(selectedNodePath.join('-'));
                        selectNode(child, childPath, selectedNodePath.length, true);
                    };
                });

                elSubplacesBody.querySelectorAll('.btn-del-child').forEach(btn => {
                    btn.onclick = () => {
                        const idx = parseInt(btn.getAttribute('data-idx'), 10);
                        if (confirm(`Remove "${selectedNodeRef.places[idx].en}"?`)) {
                            selectedNodeRef.places.splice(idx, 1);
                            renderSubplacesTable();
                            updateStats();
                            renderTree();
                            setDirty(true);
                            showToast('Child place removed');
                        }
                    };
                });
            }

            if (elSubplacesFilter) {
                elSubplacesFilter.addEventListener('input', () => {
                    renderSubplacesTable();
                });
            }

            // Apply node edits
            document.getElementById('btnUpdateNode').onclick = () => {
                if (!selectedNodeRef) return;
                selectedNodeRef.en = elInpNameEn.value.trim();
                selectedNodeRef.loc = elInpNameLoc.value.trim();
                const pc = elInpPostcode.value.trim();
                selectedNodeRef.postcode = pc !== '' ? pc : null;
                selectedNodeRef.code = pc !== '' ? pc : null;

                const selectedTypes = [];
                elTypePills.querySelectorAll('.type-pill.active').forEach(p => {
                    selectedTypes.push(p.getAttribute('data-type'));
                });

                if (selectedTypes.length === 0) {
                    selectedNodeRef.type = 'place';
                } else if (selectedTypes.length === 1) {
                    selectedNodeRef.type = selectedTypes[0];
                } else {
                    selectedNodeRef.type = selectedTypes;
                }

                updateStats();
                renderTree();
                setDirty(true);
                showToast(`Applied changes to "${selectedNodeRef.en}"`);
            };

            // Add new sub-place
            document.getElementById('btnAddSubPlace').onclick = () => {
                if (!selectedNodeRef) return;
                const nameEn = prompt('Enter English Name for new child place:');
                if (!nameEn || !nameEn.trim()) return;

                const newChild = {
                    en: nameEn.trim(),
                    loc: '',
                    type: 'place',
                    places: []
                };

                if (!selectedNodeRef.places) selectedNodeRef.places = [];
                selectedNodeRef.places.push(newChild);
                expandedKeys.add(selectedNodePath.join('-'));

                renderSubplacesTable();
                updateStats();
                renderTree();
                setDirty(true);
                showToast(`Added child place "${newChild.en}"`);
            };

            // Delete current place
            document.getElementById('btnDeleteNode').onclick = () => {
                if (!selectedNodeRef || selectedNodePath.length === 0) return;
                if (!confirm(`Are you sure you want to delete "${selectedNodeRef.en}" and all its sub-places?`)) return;

                const parentPath = selectedNodePath.slice(0, -1);
                const lastIdx = selectedNodePath[selectedNodePath.length - 1];

                if (parentPath.length === 0) {
                    activeDataset.places.splice(lastIdx, 1);
                    selectNode(null, [], 0, true);
                } else {
                    let parentNode = activeDataset.places;
                    for (let i = 0; i < parentPath.length; i++) {
                        parentNode = (i === 0) ? parentNode[parentPath[i]] : (parentNode.places || parentNode.children)[parentPath[i]];
                    }
                    (parentNode.places || parentNode.children).splice(lastIdx, 1);
                    selectNode(parentNode, parentPath, parentPath.length - 1, true);
                }

                updateStats();
                renderTree();
                setDirty(true);
                showToast('Place deleted');
            };

            async function saveCurrentPack() {
                if (!activeDataset) return false;

                // Auto-commit active node inputs if modified
                if (selectedNodeRef) {
                    selectedNodeRef.en = elInpNameEn.value.trim();
                    selectedNodeRef.loc = elInpNameLoc.value.trim();
                    const pc = elInpPostcode.value.trim();
                    selectedNodeRef.postcode = pc !== '' ? pc : null;
                    selectedNodeRef.code = pc !== '' ? pc : null;
                }

                const today = new Date().toISOString().slice(0, 10);
                activeDataset.manifest = activeDataset.manifest || {};
                activeDataset.manifest.last_updated = today;

                // Add active contributor name
                activeDataset.manifest.contributors = activeDataset.manifest.contributors || [];
                if (!activeDataset.manifest.contributors.includes(activeContributorName)) {
                    activeDataset.manifest.contributors.push(activeContributorName);
                }

                try {
                    const res = await fetch('index.php?action=save_country', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            code: currentCountryCode,
                            data: activeDataset,
                            contributor: activeContributorName
                        })
                    });
                    const json = await res.json();
                    if (json.success) {
                        setDirty(false);
                        elStatUpdated.textContent = json.last_updated || today;
                        showToast(`Saved ${currentCountryCode}.json (Updated: ${json.last_updated} by ${activeContributorName})`);
                        updateStats();
                        return true;
                    } else {
                        showToast('Error saving: ' + json.error);
                        return false;
                    }
                } catch (e) {
                    showToast('Failed to save JSON');
                    return false;
                }
            }

            // Save JSON to disk (actively updates last_updated timestamp and contributor)
            document.getElementById('btnSaveJson').onclick = async () => {
                if (!isDirty) return;
                await saveCurrentPack();
            };

            async function ensureSavedBeforeCompile() {
                if (!isDirty) return true;
                const shouldSave = confirm("You have unsaved changes that have not been saved to disk yet.\n\nSave changes now before compiling?");
                if (!shouldSave) {
                    return false;
                }
                const saved = await saveCurrentPack();
                return saved;
            }

            // Compile Menu Dropdown Wireup
            const compileToggle = document.getElementById('btnCompileToggle');
            const compileMenu = document.getElementById('compileMenu');

            compileToggle.onclick = (e) => {
                e.stopPropagation();
                compileMenu.classList.toggle('show');
            };

            document.addEventListener('click', (e) => {
                if (!e.target.closest('#compileDropdownWrap')) {
                    compileMenu.classList.remove('show');
                }
            });

            document.getElementById('optCompileDb').onclick = async () => {
                compileMenu.classList.remove('show');
                if (!currentCountryCode) return;
                if (!(await ensureSavedBeforeCompile())) return;
                showToast(`Compiling ${currentCountryCode} to SQLite...`);
                try {
                    const res = await fetch(`index.php?action=compile_country&code=${encodeURIComponent(currentCountryCode)}`);
                    const json = await res.json();
                    if (json.success) {
                        showToast(`Compiled ${currentCountryCode} (${json.result?.stats?.total?.toLocaleString() || json.result?.total_places?.toLocaleString() || ''} rows)`);
                    } else {
                        alert('Compile error: ' + json.error);
                    }
                } catch (err) {
                    alert('Compile error: ' + err.message);
                }
            };

            document.getElementById('optGenerateSqlDump').onclick = async () => {
                compileMenu.classList.remove('show');
                if (!currentCountryCode) return;
                if (!(await ensureSavedBeforeCompile())) return;
                showToast(`Generating SQL dump for ${currentCountryCode}...`);
                try {
                    const res = await fetch(`index.php?action=generate_sql_dump&code=${encodeURIComponent(currentCountryCode)}`);
                    const json = await res.json();
                    if (json.success) {
                        showToast(json.message);
                    } else {
                        alert('SQL dump error: ' + json.error);
                    }
                } catch (err) {
                    alert('SQL dump error: ' + err.message);
                }
            };

            // Modal: Manifest Settings
            const modalManifest = document.getElementById('modalManifestBackdrop');
            document.getElementById('btnOpenManifest').onclick = () => {
                if (!activeDataset) return;
                const m = activeDataset.manifest || {};
                document.getElementById('manAuthor').value = m.author || activeContributorName;
                document.getElementById('manCountryName').value = m.country_name || currentCountryCode;
                document.getElementById('manVersion').value = m.version || '1.0.0';
                const standardLicenses = ['CC-BY-4.0', 'CC0-1.0', 'ODbL-1.0', 'MIT', 'Apache-2.0'];
                const lic = m.license || 'CC-BY-4.0';
                const manLicSelect = document.getElementById('manLicenseSelect');
                const grpManCustom = document.getElementById('grpManCustomLicense');
                const inpManCustom = document.getElementById('manCustomLicense');
                if (standardLicenses.includes(lic)) {
                    manLicSelect.value = lic;
                    grpManCustom.style.display = 'none';
                    inpManCustom.value = '';
                } else {
                    manLicSelect.value = 'custom';
                    grpManCustom.style.display = 'block';
                    inpManCustom.value = lic;
                }
                document.getElementById('manCodeLabel').value = m.code_label || (m.pack_type === 'custom' ? 'Block Code' : 'Postal Code');
                document.getElementById('manReleaseDate').value = m.release_date || new Date().toISOString().slice(0, 10);
                document.getElementById('manLastUpdated').value = m.last_updated || new Date().toISOString().slice(0, 10);
                document.getElementById('manActiveContributor').value = activeContributorName;
                document.getElementById('manNotes').value = m.notes || '';
                renderContributorChips(m.contributors || [activeContributorName]);
                modalManifest.style.display = 'flex';
            };

            function renderContributorChips(contributors) {
                const container = document.getElementById('manContributorsChips');
                container.innerHTML = '';
                contributors.forEach((c, idx) => {
                    const chip = document.createElement('span');
                    chip.className = 'geo-chip';
                    chip.textContent = c;
                    const del = document.createElement('span');
                    del.className = 'geo-chip-del';
                    del.title = 'Remove Contributor';
                    del.innerHTML = ZimRxIcon.render('x', 11);
                    del.onclick = () => {
                        contributors.splice(idx, 1);
                        renderContributorChips(contributors);
                    };
                    chip.appendChild(del);
                    container.appendChild(chip);
                });
            }

            document.getElementById('btnAddContributorChip').onclick = () => {
                const inp = document.getElementById('inpAddContributor');
                const val = inp.value.trim();
                if (!val) return;
                activeDataset.manifest = activeDataset.manifest || {};
                activeDataset.manifest.contributors = activeDataset.manifest.contributors || [];
                if (!activeDataset.manifest.contributors.includes(val)) {
                    activeDataset.manifest.contributors.push(val);
                }
                inp.value = '';
                renderContributorChips(activeDataset.manifest.contributors);
            };

            document.getElementById('btnApplyManifest').onclick = () => {
                if (!activeDataset) return;
                activeDataset.manifest = activeDataset.manifest || {};
                activeDataset.manifest.author = document.getElementById('manAuthor').value.trim();
                activeDataset.manifest.country_name = document.getElementById('manCountryName').value.trim();
                activeDataset.manifest.version = document.getElementById('manVersion').value.trim();
                const manLicSelect = document.getElementById('manLicenseSelect');
                const inpManCustom = document.getElementById('manCustomLicense');
                activeDataset.manifest.license = (manLicSelect.value === 'custom') ? (inpManCustom.value.trim() || 'Custom') : manLicSelect.value;
                activeDataset.manifest.code_label = document.getElementById('manCodeLabel').value.trim() || 'Postal Code';
                activeDataset.manifest.notes = document.getElementById('manNotes').value.trim();

                const newContributor = document.getElementById('manActiveContributor').value.trim();
                if (newContributor) {
                    activeContributorName = newContributor;
                    localStorage.setItem('zimrx_geo_contributor', activeContributorName);
                    if (!activeDataset.manifest.contributors.includes(activeContributorName)) {
                        activeDataset.manifest.contributors.push(activeContributorName);
                    }
                }

                updateStats();
                updateCodeLabelUI();
                modalManifest.style.display = 'none';
                setDirty(true);
                showToast('Applied manifest settings');
            };

            document.getElementById('btnCloseManifestModal').onclick = () => modalManifest.style.display = 'none';
            document.getElementById('btnCancelManifest').onclick = () => modalManifest.style.display = 'none';

            document.getElementById('manLicenseSelect').onchange = (e) => {
                const grp = document.getElementById('grpManCustomLicense');
                if (e.target.value === 'custom') {
                    grp.style.display = 'block';
                    document.getElementById('manCustomLicense').focus();
                } else {
                    grp.style.display = 'none';
                }
            };

            // Modal: New / Import Region
            const modalImport = document.getElementById('modalImportBackdrop');
            const modalImportTitle = document.getElementById('modalImportTitle');
            const btnModalBackToChoice = document.getElementById('btnModalBackToChoice');
            const modalChoiceView = document.getElementById('modalChoiceView');
            const tabImportGeonamesBody = document.getElementById('tabImportGeonamesBody');
            const tabImportWikidataBody = document.getElementById('tabImportWikidataBody');
            const tabCreateBlankBody = document.getElementById('tabCreateBlankBody');
            const btnChoiceGeoNames = document.getElementById('btnChoiceGeoNames');
            const btnChoiceWikidata = document.getElementById('btnChoiceWikidata');
            const btnChoiceBlank = document.getElementById('btnChoiceBlank');
            const btnExecuteImport = document.getElementById('btnExecuteImport');
            const depthList = document.getElementById('depthChainList');

            let activeModalView = 'choice';
            let depthLevels = [
                {
                    depth: 1,
                    types: [
                        { key: 'state', name_en: 'State / Province', name_loc: 'State / Province' }
                    ]
                },
                {
                    depth: 2,
                    types: [
                        { key: 'county', name_en: 'County / District', name_loc: 'County / District' }
                    ]
                },
                {
                    depth: 3,
                    types: [
                        { key: 'city', name_en: 'City / Locality', name_loc: 'City / Locality' },
                        { key: 'postoffice', name_en: 'Post Office', name_loc: 'Post Office' }
                    ]
                }
            ];

            function renderDepthChain() {
                if (!depthList) return;
                depthList.innerHTML = '';

                depthLevels.forEach((lvl, lvlIdx) => {
                    const card = document.createElement('div');
                    card.className = 'depth-level-card';

                    // Header
                    const hdr = document.createElement('div');
                    hdr.className = 'depth-level-header';

                    const titleBox = document.createElement('div');
                    titleBox.className = 'depth-level-title';
                    const badge = document.createElement('span');
                    badge.className = 'depth-badge';
                    badge.textContent = `L${lvlIdx + 1}`;
                    titleBox.appendChild(badge);
                    const titleText = document.createElement('span');
                    titleText.textContent = `Depth ${lvlIdx + 1}`;
                    titleBox.appendChild(titleText);
                    hdr.appendChild(titleBox);

                    const actions = document.createElement('div');
                    actions.style.display = 'flex';
                    actions.style.gap = '6px';
                    actions.style.alignItems = 'center';

                    const btnAddType = document.createElement('button');
                    btnAddType.type = 'button';
                    btnAddType.className = 'sub-btn';
                    btnAddType.style.fontSize = '10px';
                    btnAddType.style.padding = '2px 6px';
                    btnAddType.textContent = '+ Add Type';
                    btnAddType.onclick = () => {
                        const nextTypeNum = (lvl.types || []).length + 1;
                        lvl.types.push({
                            key: `type_${nextTypeNum}`,
                            name_en: `Type ${nextTypeNum}`,
                            name_loc: `Type ${nextTypeNum}`
                        });
                        renderDepthChain();
                    };
                    actions.appendChild(btnAddType);

                    if (depthLevels.length > 1) {
                        const btnDelLvl = document.createElement('button');
                        btnDelLvl.type = 'button';
                        btnDelLvl.className = 'depth-btn-remove';
                        btnDelLvl.title = 'Remove Depth Level';
                        btnDelLvl.innerHTML = ZimRxIcon.render('x', 12);
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
                    colHeaders.innerHTML = '<span>Key / Slug</span><span>English Name</span><span>Local Name</span><span></span>';
                    typesBox.appendChild(colHeaders);

                    (lvl.types || []).forEach((t, tIdx) => {
                        const row = document.createElement('div');
                        row.className = 'depth-type-row';

                        const inpKey = document.createElement('input');
                        inpKey.type = 'text';
                        inpKey.className = 'depth-inp';
                        inpKey.placeholder = 'key';
                        inpKey.value = t.key || '';
                        inpKey.oninput = (e) => {
                            t.key = e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                        };

                        const inpEn = document.createElement('input');
                        inpEn.type = 'text';
                        inpEn.className = 'depth-inp';
                        inpEn.placeholder = 'English Name';
                        inpEn.value = t.name_en || '';
                        inpEn.oninput = (e) => {
                            const val = e.target.value;
                            t.name_en = val;
                            if (!t.key || t.key.startsWith('type_') || t.key === 'level_' + (lvlIdx + 1)) {
                                t.key = val.toLowerCase().trim().replace(/[^a-z0-9_]+/g, '_');
                                inpKey.value = t.key;
                            }
                            if (!inpLoc.value || inpLoc.value === t.name_loc) {
                                t.name_loc = val;
                                inpLoc.value = val;
                            }
                        };

                        const inpLoc = document.createElement('input');
                        inpLoc.type = 'text';
                        inpLoc.className = 'depth-inp';
                        inpLoc.placeholder = 'Local Name';
                        inpLoc.value = t.name_loc || '';
                        inpLoc.oninput = (e) => {
                            t.name_loc = e.target.value;
                        };

                        row.appendChild(inpKey);
                        row.appendChild(inpEn);
                        row.appendChild(inpLoc);

                        if ((lvl.types || []).length > 1) {
                            const btnDelType = document.createElement('button');
                            btnDelType.type = 'button';
                            btnDelType.className = 'depth-btn-remove';
                            btnDelType.title = 'Remove Type';
                            btnDelType.innerHTML = ZimRxIcon.render('x', 12);
                            btnDelType.onclick = () => {
                                lvl.types.splice(tIdx, 1);
                                renderDepthChain();
                            };
                            row.appendChild(btnDelType);
                        } else {
                            const spacer = document.createElement('span');
                            row.appendChild(spacer);
                        }

                        typesBox.appendChild(row);
                    });

                    card.appendChild(typesBox);
                    depthList.appendChild(card);

                    if (lvlIdx < depthLevels.length - 1) {
                        const arrow = document.createElement('div');
                        arrow.className = 'depth-arrow-down';
                        arrow.innerHTML = ZimRxIcon.render('arrow-down', 12);
                        depthList.appendChild(arrow);
                    }
                });
            }

            document.getElementById('btnAddDepthLevel').onclick = () => {
                const nextNum = depthLevels.length + 1;
                depthLevels.push({
                    depth: nextNum,
                    types: [
                        { key: `level_${nextNum}`, name_en: `Level ${nextNum}`, name_loc: `Level ${nextNum}` }
                    ]
                });
                renderDepthChain();
                depthList.scrollTop = depthList.scrollHeight;
            };

            function setModalImportView(view) {
                activeModalView = view;
                if (view === 'choice') {
                    modalImportTitle.textContent = 'New / Import Region';
                    btnModalBackToChoice.style.display = 'none';
                    modalChoiceView.style.display = 'block';
                    tabImportGeonamesBody.style.display = 'none';
                    if (tabImportWikidataBody) tabImportWikidataBody.style.display = 'none';
                    tabCreateBlankBody.style.display = 'none';
                    btnExecuteImport.style.display = 'none';
                    hideWikiCountryLevels();
                } else if (view === 'geonames') {
                    modalImportTitle.textContent = 'Import from GeoNames (CC-BY 4.0)';
                    btnModalBackToChoice.style.display = 'inline-flex';
                    modalChoiceView.style.display = 'none';
                    tabImportGeonamesBody.style.display = 'block';
                    if (tabImportWikidataBody) tabImportWikidataBody.style.display = 'none';
                    tabCreateBlankBody.style.display = 'none';
                    btnExecuteImport.style.display = 'inline-block';
                    btnExecuteImport.textContent = 'Import & Compile';
                    hideWikiCountryLevels();
                    setTimeout(() => document.getElementById('selGeoCountryPreset')?.focus(), 50);
                } else if (view === 'wikidata') {
                    modalImportTitle.textContent = 'Import from Wikidata (CC0 1.0)';
                    btnModalBackToChoice.style.display = 'inline-flex';
                    modalChoiceView.style.display = 'none';
                    tabImportGeonamesBody.style.display = 'none';
                    if (tabImportWikidataBody) tabImportWikidataBody.style.display = 'block';
                    tabCreateBlankBody.style.display = 'none';
                    btnExecuteImport.style.display = 'inline-block';
                    btnExecuteImport.textContent = 'Import & Compile';
                    const curWikiVal = document.getElementById('selWikiCountryPreset')?.value;
                    if (curWikiVal) {
                        loadWikiCountryLevels(curWikiVal);
                    } else {
                        hideWikiCountryLevels();
                    }
                    setTimeout(() => document.getElementById('selWikiCountryPreset')?.focus(), 50);
                } else if (view === 'blank') {
                    modalImportTitle.textContent = 'Create Blank Region';
                    btnModalBackToChoice.style.display = 'inline-flex';
                    modalChoiceView.style.display = 'none';
                    tabImportGeonamesBody.style.display = 'none';
                    if (tabImportWikidataBody) tabImportWikidataBody.style.display = 'none';
                    tabCreateBlankBody.style.display = 'block';
                    btnExecuteImport.style.display = 'inline-block';
                    btnExecuteImport.textContent = 'Create Blank Region';
                    hideWikiCountryLevels();
                    document.getElementById('blankLicenseSelect').value = 'CC-BY-4.0';
                    document.getElementById('grpBlankCustomLicense').style.display = 'none';
                    document.getElementById('blankCustomLicense').value = '';
                    document.getElementById('blankNotes').value = '';
                    renderDepthChain();
                    setTimeout(() => {
                        const isNat = !document.getElementById('grpBlankCountry').hidden;
                        document.getElementById(isNat ? 'blankCountrySelect' : 'blankCode').focus();
                    }, 50);
                }
            }

            document.getElementById('blankLicenseSelect').onchange = (e) => {
                const grp = document.getElementById('grpBlankCustomLicense');
                if (e.target.value === 'custom') {
                    grp.style.display = 'block';
                    document.getElementById('blankCustomLicense').focus();
                } else {
                    grp.style.display = 'none';
                }
            };

            let cachedOfflineCountries = [];
            let cachedLiveGeoCountries = [];
            let cachedLiveWikiCountries = [];

            async function loadOfflineCountryPresets() {
                // Offline countries are not auto-injected; both GeoNames and Wikidata require live fetch with privacy opt-in
            }

            function populateGeoCountrySelect(countries) {
                const sel = document.getElementById('selGeoCountryPreset');
                if (!sel) return;
                const currentVal = sel.value;
                sel.innerHTML = '<option value="">-- Select a Country --</option>';

                countries.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.code;
                    opt.dataset.name = c.name;
                    opt.dataset.hasPostal = c.has_postal ? '1' : '0';
                    opt.textContent = `${c.name} (${c.code})`;
                    sel.appendChild(opt);
                });

                if (currentVal) sel.value = currentVal;
            }

            function populateWikiCountrySelect(countries) {
                const sel = document.getElementById('selWikiCountryPreset');
                if (!sel) return;
                const currentVal = sel.value;
                sel.innerHTML = '<option value="">-- Select a Country --</option>';

                countries.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.code;
                    opt.dataset.name = c.name;
                    opt.textContent = `${c.name} (${c.code})`;
                    sel.appendChild(opt);
                });

                if (currentVal) sel.value = currentVal;
            }

            const selGeoPreset = document.getElementById('selGeoCountryPreset');
            if (selGeoPreset) {
                selGeoPreset.addEventListener('change', () => {
                    const code = selGeoPreset.value;
                    const opt = selGeoPreset.selectedOptions[0];
                    const impCode = document.getElementById('impGeoCode');
                    const impName = document.getElementById('impGeoName');
                    const badge = document.getElementById('geoSelectedCountryBadge');
                    if (code && opt) {
                        const name = opt.dataset.name || '';
                        const hasPostal = opt.dataset.hasPostal === '1';
                        if (impCode) impCode.value = code;
                        if (impName) impName.value = name;
                        if (badge) {
                            badge.style.display = 'block';
                            badge.innerHTML = `Selected: <strong>${escapeHtml(name)} (${escapeHtml(code)})</strong>` +
                                (hasPostal ? ` &bull; <span style="color:#16a34a; font-weight:600;">Postal Dataset Available (${escapeHtml(code)}.zip)</span>` : ` &bull; <span style="color:#64748b;">Gazetteer Data Available</span>`);
                        }
                    } else {
                        if (impCode) impCode.value = '';
                        if (impName) impName.value = '';
                        if (badge) badge.style.display = 'none';
                    }
                });
            }

            const selWikiPreset = document.getElementById('selWikiCountryPreset');
            if (selWikiPreset) {
                selWikiPreset.addEventListener('change', () => {
                    const code = selWikiPreset.value;
                    const opt = selWikiPreset.selectedOptions[0];
                    const impCode = document.getElementById('impWikiCode');
                    const impName = document.getElementById('impWikiName');
                    const badge = document.getElementById('wikiSelectedCountryBadge');
                    if (code && opt) {
                        const name = opt.dataset.name || '';
                        if (impCode) impCode.value = code;
                        if (impName) impName.value = name;
                        if (badge) {
                            badge.style.display = 'block';
                            badge.innerHTML = `Selected: <strong>${escapeHtml(name)} (${escapeHtml(code)})</strong> &bull; <span style="color:#16a34a; font-weight:600;">SPARQL Pipeline Ready</span>`;
                        }
                        loadWikiCountryLevels(code);
                    } else {
                        if (impCode) impCode.value = '';
                        if (impName) impName.value = '';
                        if (badge) badge.style.display = 'none';
                        hideWikiCountryLevels();
                    }
                });
            }

            let currentWikiLevels = [];

            function hideWikiCountryLevels() {
                const grp = document.getElementById('grpWikiLevels');
                if (grp) grp.style.display = 'none';
                const list = document.getElementById('wikiLevelsList');
                if (list) list.innerHTML = '';
                const status = document.getElementById('wikiLevelsStatus');
                if (status) status.textContent = '';
                currentWikiLevels = [];
            }

            async function loadWikiCountryLevels(countryCode) {
                const grp = document.getElementById('grpWikiLevels');
                const list = document.getElementById('wikiLevelsList');
                const status = document.getElementById('wikiLevelsStatus');
                if (!grp || !list) return;

                grp.style.display = 'block';
                if (status) {
                    status.innerHTML = ZimRxIcon.render('loader', 11, { class: 'zrx-icon geo-spin' }) + ' Detecting levels...';
                }
                list.innerHTML = '<div style="padding:10px; font-size:11px; color:#64748b; text-align:center;">Discovering administrative hierarchy for ' + escapeHtml(countryCode) + '...</div>';

                try {
                    const res = await fetch('index.php?action=get_wikidata_levels&code=' + encodeURIComponent(countryCode));
                    const json = await res.json();

                    if (json.success && Array.isArray(json.levels) && json.levels.length > 0) {
                        currentWikiLevels = json.levels;
                        renderWikiLevelsList(json.levels);
                    } else {
                        currentWikiLevels = [
                            { depth: 1, name: 'First-level Division (State / Region)', count: 0 },
                            { depth: 2, name: 'Second-level Division (District / County)', count: 0 }
                        ];
                        renderWikiLevelsList(currentWikiLevels);
                        if (status) status.textContent = 'Default levels';
                    }
                } catch (e) {
                    list.innerHTML = '<div style="padding:8px; font-size:11px; color:#dc2626;">Failed to query hierarchy: ' + escapeHtml(e.message) + '</div>';
                    if (status) status.textContent = 'Error';
                }
            }

            function renderWikiLevelsList(levels) {
                const list = document.getElementById('wikiLevelsList');
                if (!list) return;

                list.innerHTML = '';
                levels.forEach(lvl => {
                    const row = document.createElement('div');
                    row.className = 'wiki-level-row active';
                    row.dataset.depth = lvl.depth;

                    const label = document.createElement('label');

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.className = 'wiki-level-cb';
                    cb.id = `wiki_cb_level_${lvl.depth}`;
                    cb.dataset.depth = lvl.depth;
                    cb.checked = true;

                    const badge = document.createElement('span');
                    badge.className = 'wiki-level-depth-badge';
                    badge.textContent = `Depth ${lvl.depth}`;

                    const nameSpan = document.createElement('span');
                    nameSpan.className = 'wiki-level-name';
                    const nameText = lvl.name || `Level ${lvl.depth}`;
                    if (lvl.name_loc && lvl.name_loc !== lvl.name) {
                        nameSpan.innerHTML = `${escapeHtml(nameText)} <span style="font-weight:400; color:#64748b;">(${escapeHtml(lvl.name_loc)})</span>`;
                    } else {
                        nameSpan.textContent = nameText;
                    }

                    label.appendChild(cb);
                    label.appendChild(badge);
                    label.appendChild(nameSpan);

                    if (lvl.count && lvl.count > 0) {
                        const cntSpan = document.createElement('span');
                        cntSpan.className = 'wiki-level-count';
                        cntSpan.textContent = `~${Number(lvl.count).toLocaleString()} units`;
                        label.appendChild(cntSpan);
                    }

                    row.appendChild(label);
                    list.appendChild(row);

                    cb.addEventListener('change', () => {
                        const checkedCbs = document.querySelectorAll('.wiki-level-cb:checked');
                        if (checkedCbs.length === 0) {
                            cb.checked = true;
                            showToast('At least one hierarchy level must be selected.');
                            return;
                        }

                        if (cb.checked) {
                            row.classList.add('active');
                        } else {
                            row.classList.remove('active');
                        }
                        updateWikiLevelsStatusText();
                    });
                });

                updateWikiLevelsStatusText();
            }

            function updateWikiLevelsStatusText() {
                const status = document.getElementById('wikiLevelsStatus');
                if (!status) return;
                const checked = Array.from(document.querySelectorAll('.wiki-level-cb:checked'));
                const total = document.querySelectorAll('.wiki-level-cb').length;
                if (checked.length === total) {
                    status.textContent = `Full hierarchy (${total} levels)`;
                } else if (checked.length > 0) {
                    const depthLabels = checked.map(c => 'Depth ' + c.dataset.depth).join(', ');
                    status.textContent = `${checked.length} of ${total} levels selected (${depthLabels})`;
                } else {
                    status.textContent = 'None selected';
                }
            }

            // External Internet Connection Confirmation Modal System
            const modalExtConfirm = document.getElementById('modalExternalConfirmBackdrop');
            const btnCloseExtConfirm = document.getElementById('btnCloseExternalConfirm');
            const btnCancelExtConfirm = document.getElementById('btnCancelExternalConfirm');
            const btnProceedExtConfirm = document.getElementById('btnProceedExternalConfirm');
            let pendingExtConfirmAction = null;

            function showExternalConfirmModal({ host = 'download.geonames.org', message = '', onConfirm = null }) {
                pendingExtConfirmAction = onConfirm;
                const hostEl = document.getElementById('extConfirmTargetHost');
                if (hostEl) hostEl.textContent = host;
                const msgEl = document.getElementById('extConfirmMessage');
                if (msgEl && message) msgEl.innerHTML = message;
                if (modalExtConfirm) modalExtConfirm.style.display = 'flex';
            }

            function closeExternalConfirmModal() {
                if (modalExtConfirm) modalExtConfirm.style.display = 'none';
                pendingExtConfirmAction = null;
            }

            if (btnCloseExtConfirm) btnCloseExtConfirm.onclick = closeExternalConfirmModal;
            if (btnCancelExtConfirm) btnCancelExtConfirm.onclick = closeExternalConfirmModal;
            if (btnProceedExtConfirm) {
                btnProceedExtConfirm.onclick = () => {
                    const action = pendingExtConfirmAction;
                    closeExternalConfirmModal();
                    if (typeof action === 'function') action();
                };
            }

            // Overwrite confirmation for packs that already exist
            const modalOverwrite = document.getElementById('modalOverwriteConfirmBackdrop');
            let pendingOverwriteAction = null;

            function closeOverwriteConfirmModal() {
                modalOverwrite.style.display = 'none';
                pendingOverwriteAction = null;
            }

            async function confirmOverwriteIfExists(code, name, proceed) {
                try {
                    const res = await fetch('index.php?action=list_countries');
                    const json = await res.json();
                    if (json.success) allHubCountries = json.countries || [];
                } catch (e) {}
                const existing = allHubCountries.find(c => String(c.code).toUpperCase() === code.toUpperCase());
                if (!existing) {
                    proceed();
                    return;
                }
                pendingOverwriteAction = proceed;
                document.getElementById('overwriteConfirmTarget').textContent = `${existing.name || name} (${code})`;
                modalOverwrite.style.display = 'flex';
            }

            document.getElementById('btnCloseOverwriteConfirm').onclick = closeOverwriteConfirmModal;
            document.getElementById('btnCancelOverwriteConfirm').onclick = closeOverwriteConfirmModal;
            document.getElementById('btnProceedOverwriteConfirm').onclick = () => {
                const action = pendingOverwriteAction;
                closeOverwriteConfirmModal();
                if (typeof action === 'function') action();
            };

            const btnFetchGeoList = document.getElementById('btnFetchGeoCountryList');
            if (btnFetchGeoList) {
                btnFetchGeoList.addEventListener('click', () => {
                    showExternalConfirmModal({
                        host: 'download.geonames.org',
                        message: 'You are connecting to the external internet (<strong>geonames.org</strong>) to fetch the full live catalog of 250+ countries and postal databases.',
                        onConfirm: () => executeFetchGeoCountryList()
                    });
                });
            }

            async function executeFetchGeoCountryList() {
                btnFetchGeoList.disabled = true;
                btnFetchGeoList.innerHTML = ZimRxIcon.render('loader', 14, { class: 'zrx-icon geo-spin' }) + '<span>Fetching from GeoNames...</span>';
                showToast('Connecting to GeoNames for latest country catalog...');

                try {
                    const res = await fetch('index.php?action=fetch_geonames_countries');
                    const json = await res.json();
                    if (json.success && Array.isArray(json.countries)) {
                        cachedLiveGeoCountries = json.countries;
                        populateGeoCountrySelect(cachedLiveGeoCountries);
                        const badge = document.getElementById('geoListStatusBadge');
                        if (badge) {
                            badge.textContent = `Live Catalog (${json.total_countries} countries, ${json.total_postal_dumps} postal archives)`;
                            badge.style.color = '#0284c7';
                        }
                        showToast(`Loaded ${json.total_countries} countries from GeoNames`);
                    } else {
                        alert('GeoNames fetch error: ' + (json.error || 'Unknown error'));
                    }
                } catch (e) {
                    alert('Network error fetching GeoNames list: ' + e.message);
                } finally {
                    btnFetchGeoList.disabled = false;
                    btnFetchGeoList.innerHTML = ZimRxIcon.render('refresh-cw', 14) + '<span>Refresh Live List</span>';
                }
            }

            const btnFetchWikiList = document.getElementById('btnFetchWikiCountryList');
            if (btnFetchWikiList) {
                btnFetchWikiList.addEventListener('click', () => {
                    showExternalConfirmModal({
                        host: 'query.wikidata.org',
                        message: 'You are connecting to the external internet (<strong>query.wikidata.org</strong>) to fetch the full live catalog of countries via Wikidata SPARQL.',
                        onConfirm: () => executeFetchWikiCountryList()
                    });
                });
            }

            async function executeFetchWikiCountryList() {
                btnFetchWikiList.disabled = true;
                btnFetchWikiList.innerHTML = ZimRxIcon.render('loader', 14, { class: 'zrx-icon geo-spin' }) + '<span>Fetching from Wikidata...</span>';
                showToast('Connecting to Wikidata SPARQL for country catalog...');

                try {
                    const res = await fetch('index.php?action=fetch_wikidata_countries');
                    const json = await res.json();
                    if (json.success && Array.isArray(json.countries)) {
                        cachedLiveWikiCountries = json.countries;
                        populateWikiCountrySelect(cachedLiveWikiCountries);
                        const badge = document.getElementById('wikiListStatusBadge');
                        if (badge) {
                            badge.textContent = `Live Catalog (${json.total_countries} countries)`;
                            badge.style.color = '#0284c7';
                        }
                        showToast(`Loaded ${json.total_countries} countries from Wikidata`);
                    } else {
                        alert('Wikidata fetch error: ' + (json.error || 'Unknown error'));
                    }
                } catch (e) {
                    alert('Network error fetching Wikidata country list: ' + e.message);
                } finally {
                    btnFetchWikiList.disabled = false;
                    btnFetchWikiList.innerHTML = ZimRxIcon.render('refresh-cw', 14) + '<span>Refresh Live List</span>';
                }
            }

            function openImportModal() {
                document.getElementById('impGeoContributor').value = activeContributorName;
                if (document.getElementById('impWikiContributor')) {
                    document.getElementById('impWikiContributor').value = activeContributorName;
                }
                document.getElementById('blankContributor').value = activeContributorName;
                loadOfflineCountryPresets();
                hideWikiCountryLevels();
                setModalImportView('choice');
                modalImport.style.display = 'flex';
            }

            document.getElementById('btnOpenImport').onclick = openImportModal;

            btnChoiceGeoNames.onclick = () => setModalImportView('geonames');
            if (btnChoiceWikidata) btnChoiceWikidata.onclick = () => setModalImportView('wikidata');
            btnChoiceBlank.onclick = () => setModalImportView('blank');
            btnModalBackToChoice.onclick = () => setModalImportView('choice');

            document.getElementById('btnCloseImportModal').onclick = () => modalImport.style.display = 'none';
            document.getElementById('btnCancelImport').onclick = () => modalImport.style.display = 'none';

            // Auto-map common ISO aliases (e.g. UK -> GB)
            document.getElementById('impGeoCode').addEventListener('input', (e) => {
                const val = e.target.value.trim().toUpperCase();
                if (val === 'UK') {
                    e.target.value = 'GB';
                    const nameInp = document.getElementById('impGeoName');
                    if (!nameInp.value || nameInp.value.toUpperCase() === 'UK') {
                        nameInp.value = 'United Kingdom';
                    }
                } else if (val === 'EL') {
                    e.target.value = 'GR';
                }
            });

            document.getElementById('impWikiCode')?.addEventListener('input', (e) => {
                const val = e.target.value.trim().toUpperCase();
                if (val === 'UK') {
                    e.target.value = 'GB';
                    const nameInp = document.getElementById('impWikiName');
                    if (!nameInp.value || nameInp.value.toUpperCase() === 'UK') {
                        nameInp.value = 'United Kingdom';
                    }
                } else if (val === 'EL') {
                    e.target.value = 'GR';
                }
            });





            // Preset chips for blankCodeLabel
            document.querySelectorAll('#blankPresetChips .preset-chip').forEach(btn => {
                btn.onclick = () => {
                    const val = btn.getAttribute('data-val');
                    const inp = document.getElementById('blankCodeLabel');
                    if (inp) inp.value = val;
                };
            });

            // Radio change for blank pack type
            document.querySelectorAll('input[name="blankPackType"]').forEach(r => {
                r.onchange = () => {
                    const isNat = r.value === 'national';
                    const codeLblInp = document.getElementById('blankCodeLabel');
                    document.getElementById('grpBlankCountry').hidden = !isNat;
                    document.getElementById('grpBlankCode').hidden = isNat;
                    document.getElementById('grpBlankName').hidden = isNat;
                    if (isNat) {
                        if (codeLblInp && codeLblInp.value === 'Block Code') {
                            codeLblInp.value = 'Postal Code';
                        }
                        depthLevels = [
                            { depth: 1, types: [{ key: 'state', name_en: 'State / Province', name_loc: 'State / Province' }] },
                            { depth: 2, types: [{ key: 'county', name_en: 'County / District', name_loc: 'County / District' }] },
                            { depth: 3, types: [
                                { key: 'city', name_en: 'City / Locality', name_loc: 'City / Locality' },
                                { key: 'postoffice', name_en: 'Post Office', name_loc: 'Post Office' }
                            ] }
                        ];
                        renderDepthChain();
                    } else {
                        if (codeLblInp && codeLblInp.value === 'Postal Code') {
                            codeLblInp.value = 'Block Code';
                        }
                        depthLevels = [
                            { depth: 1, types: [{ key: 'zone', name_en: 'Zone / Camp', name_loc: 'Zone / Camp' }] },
                            { depth: 2, types: [{ key: 'block', name_en: 'Block / Sector', name_loc: 'Block / Sector' }] },
                            { depth: 3, types: [
                                { key: 'unit', name_en: 'Unit / Shelter', name_loc: 'Unit / Shelter' },
                                { key: 'facility', name_en: 'Health Facility', name_loc: 'Health Facility' }
                            ] }
                        ];
                        renderDepthChain();
                    }
                };
            });

            async function executeGeonamesImport(code, name, contributor) {
                showToast(`Importing ${code} from GeoNames...`);

                const fd = new FormData();
                fd.append('code', code);
                fd.append('name', name);
                fd.append('contributor', contributor);

                try {
                    const res = await fetch('index.php?action=import_geonames', {
                        method: 'POST',
                        body: fd
                    });
                    const json = await res.json();
                    if (json.success) {
                        showToast(json.message);
                        modalImport.style.display = 'none';
                        activeContributorName = contributor;
                        localStorage.setItem('zimrx_geo_contributor', activeContributorName);
                        updateUrl(code, '', true);
                        await handleUrlRoute();
                    } else {
                        alert('Import error: ' + json.error);
                    }
                } catch (e) {
                    alert('Network error during GeoNames import: ' + e.message);
                }
            }

            async function executeWikidataImport(code, name, contributor, maxLevel = 0, selectedDepths = []) {
                showToast(`Querying & importing ${code} from Wikidata SPARQL...`);

                const fd = new FormData();
                fd.append('code', code);
                fd.append('name', name);
                fd.append('contributor', contributor);
                if (maxLevel > 0) {
                    fd.append('max_level', maxLevel);
                }
                if (Array.isArray(selectedDepths) && selectedDepths.length > 0) {
                    fd.append('selected_levels', selectedDepths.join(','));
                }

                try {
                    const res = await fetch('index.php?action=import_wikidata', {
                        method: 'POST',
                        body: fd
                    });
                    const json = await res.json();
                    if (json.success) {
                        showToast(json.message);
                        modalImport.style.display = 'none';
                        activeContributorName = contributor;
                        localStorage.setItem('zimrx_geo_contributor', activeContributorName);
                        updateUrl(code, '', true);
                        await handleUrlRoute();
                    } else {
                        alert('Wikidata import error: ' + json.error);
                    }
                } catch (e) {
                    alert('Network error during Wikidata import: ' + e.message);
                }
            }

            // Execute Import / Creation
            btnExecuteImport.onclick = async () => {
                if (activeModalView === 'geonames') {
                    const code = document.getElementById('impGeoCode').value.trim().toUpperCase();
                    const name = document.getElementById('impGeoName').value.trim();
                    const contributor = document.getElementById('impGeoContributor').value.trim() || activeContributorName;

                    if (!code || code.length !== 2) {
                        alert('Please select a country from the dropdown. (Click "Fetch Live GeoNames List" first if no countries are loaded).');
                        return;
                    }

                    confirmOverwriteIfExists(code, name, () => showExternalConfirmModal({
                        host: 'download.geonames.org',
                        message: `You are connecting to the external internet (<strong>geonames.org</strong>) to download and compile the geographic dataset for <strong>${escapeHtml(name)} (${code})</strong>.`,
                        onConfirm: () => executeGeonamesImport(code, name, contributor)
                    }));
                } else if (activeModalView === 'blank') {
                    // Blank pack
                    const packType = document.querySelector('input[name="blankPackType"]:checked')?.value || 'national';
                    const countrySel = document.getElementById('blankCountrySelect');
                    const isNatPack = packType === 'national';
                    const code = isNatPack ? countrySel.value : document.getElementById('blankCode').value.trim().toUpperCase();
                    const name = isNatPack ? (countrySel.selectedOptions[0]?.dataset.name || '') : document.getElementById('blankName').value.trim();
                    const defLang = document.getElementById('blankDefLang').value.trim();
                    const locLang = document.getElementById('blankLocLang').value.trim();
                    const contributor = document.getElementById('blankContributor').value.trim() || activeContributorName;
                    const codeLabel = document.getElementById('blankCodeLabel') ? document.getElementById('blankCodeLabel').value.trim() : '';

                    if (packType === 'national') {
                        if (code.length !== 2) {
                            alert('Please select a country.');
                            return;
                        }
                    } else {
                        if (!/^[A-Z0-9_-]{2,16}$/.test(code)) {
                            alert('Please enter a valid pack identifier (2-16 letters, numbers, or hyphens, e.g. UN-CXB, CAMP-A).');
                            return;
                        }
                    }

                    if (!name) {
                        alert('Please enter a name for the pack.');
                        return;
                    }

                    const blankLicSelect = document.getElementById('blankLicenseSelect');
                    const blankCustomLic = document.getElementById('blankCustomLicense');
                    const license = (blankLicSelect.value === 'custom') ? (blankCustomLic.value.trim() || 'Custom') : blankLicSelect.value;
                    const notes = document.getElementById('blankNotes').value.trim();

                    const fd = new FormData();
                    fd.append('code', code);
                    fd.append('pack_type', packType);
                    fd.append('code_label', codeLabel);
                    fd.append('name', name);
                    fd.append('default_lang', defLang);
                    fd.append('local_lang', locLang);
                    fd.append('contributor', contributor);
                    fd.append('license', license);
                    fd.append('notes', notes);
                    fd.append('depth_levels', JSON.stringify(depthLevels));

                    confirmOverwriteIfExists(code, name, async () => {
                        try {
                            const res = await fetch('index.php?action=create_blank_country', {
                                method: 'POST',
                                body: fd
                            });
                            const json = await res.json();
                            if (json.success) {
                                showToast(json.message);
                                modalImport.style.display = 'none';
                                activeContributorName = contributor;
                                localStorage.setItem('zimrx_geo_contributor', activeContributorName);
                                updateUrl(code, '', true);
                                await handleUrlRoute();
                            } else {
                                alert('Error creating pack: ' + json.error);
                            }
                        } catch (e) {
                            alert('Network error: ' + e.message);
                        }
                    });
                } else if (activeModalView === 'wikidata') {
                    const code = document.getElementById('impWikiCode').value.trim().toUpperCase();
                    const name = document.getElementById('impWikiName').value.trim();
                    const contributor = document.getElementById('impWikiContributor').value.trim() || activeContributorName;

                    if (code.length !== 2) {
                        alert('Please select a country from the dropdown.');
                        return;
                    }

                    const checkedCbs = Array.from(document.querySelectorAll('.wiki-level-cb:checked'));
                    if (checkedCbs.length === 0) {
                        alert('Please select at least one hierarchy level to import.');
                        return;
                    }

                    const selectedDepths = checkedCbs.map(cb => parseInt(cb.dataset.depth, 10)).sort((a, b) => a - b);
                    const maxLevel = Math.max(...selectedDepths);

                    const levelNames = checkedCbs.map(cb => {
                        const row = cb.closest('.wiki-level-row');
                        const nameEl = row?.querySelector('.wiki-level-name');
                        return nameEl ? nameEl.textContent.trim() : ('Depth ' + cb.dataset.depth);
                    }).join(', ');

                    confirmOverwriteIfExists(code, name, () => showExternalConfirmModal({
                        host: 'query.wikidata.org',
                        message: `You are connecting to the external internet (<strong>query.wikidata.org</strong>) to query and download the administrative dataset for <strong>${escapeHtml(name)} (${code})</strong> via Wikidata SPARQL.<br><br><strong>Selected Levels:</strong> ${escapeHtml(levelNames)}.`,
                        onConfirm: () => executeWikidataImport(code, name, contributor, maxLevel, selectedDepths)
                    }));
                }
            };

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            // Track unsaved modifications in inspector fields
            [elInpNameEn, elInpNameLoc, elInpPostcode].forEach(inp => {
                if (inp) {
                    inp.addEventListener('input', () => setDirty(true));
                }
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
                if (isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Start app with history routing
            window.addEventListener('popstate', handleUrlRoute);
            handleUrlRoute();
        })();
