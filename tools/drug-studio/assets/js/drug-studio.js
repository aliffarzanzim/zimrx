/**
 * ZimRx Drug Studio - Clean Client-Side Engine
 * Multi-view deep linking, URL synchronization, and per-section monograph editor.
 */
(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Icon Renderer from global SVG registry
    // -------------------------------------------------------------------------
    function renderIcon(name, size, className) {
        size = size || 14;
        className = className || 'zrx-icon';
        var map = window.ZimRxIconsMap || {};
        var path = map[name] || map['hash'] || '';
        return '<svg class="' + className + '" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>';
    }

    // -------------------------------------------------------------------------
    // State Store
    // -------------------------------------------------------------------------
    var state = {
        activeView: 'hub', // 'hub' or 'workspace'
        activeWorkspace: 'generics', // 'generics', 'formularies', 'dosage_forms'
        activeGenTab: 'tabGenList',
        activeFormTab: 'tabBrandsList',
        activeDosageTab: 'tabFormsList',
        activeCountry: 'BD',
        selectedGenericId: null,
        selectedGenericData: null,
        selectedClassId: null,
        selectedClassData: null,
        selectedIndicationId: null,
        selectedIndicationData: null,
        selectedBrandId: null,
        selectedBrandData: null,
        selectedMfgId: null,
        selectedMfgData: null,
        selectedDosageId: null,
        selectedDosageData: null,
        dosageFormsList: [],
        countries: [],
        isFormularyWorkspaceOpen: false
    };

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showToast(msg, type) {
        type = type || 'info';
        var container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'drug-toast-container';
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'drug-toast' + (type === 'success' ? ' drug-toast-success' : (type === 'error' ? ' drug-toast-error' : ''));
        var icon = type === 'success' ? 'check' : (type === 'error' ? 'alert-circle' : 'info-circle');
        toast.innerHTML = renderIcon(icon, 14) + '<span>' + escapeHtml(msg) + '</span>';
        container.appendChild(toast);
        setTimeout(function () {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 3500);
    }

    function apiFetch(action, params, payload) {
        var url = 'index.php?action=' + encodeURIComponent(action);
        if (params) {
            for (var k in params) {
                if (params.hasOwnProperty(k) && params[k] !== undefined && params[k] !== null) {
                    url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                }
            }
        }
        var opts = {
            method: payload ? 'POST' : 'GET',
            headers: { 'Accept': 'application/json' }
        };
        if (payload) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(payload);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok || !json.success) {
                    throw new Error(json.error || ('API Error ' + res.status));
                }
                return json.data;
            });
        });
    }

    // -------------------------------------------------------------------------
    // URL Deep-Linking & History Synchronization
    // -------------------------------------------------------------------------
    function syncUrl(push) {
        if (push === undefined) push = true;
        var params = new URLSearchParams();

        if (state.activeView === 'hub') {
            params.set('view', 'hub');
        } else if (state.activeView === 'workspace') {
            params.set('view', state.activeWorkspace);

            if (state.activeWorkspace === 'generics') {
                params.set('tab', state.activeGenTab);
                if (state.activeGenTab === 'tabGenList' && state.selectedGenericId) {
                    params.set('id', state.selectedGenericId);
                } else if (state.activeGenTab === 'tabClassList' && state.selectedClassId) {
                    params.set('id', state.selectedClassId);
                } else if (state.activeGenTab === 'tabIndList' && state.selectedIndicationId) {
                    params.set('id', state.selectedIndicationId);
                }
            } else if (state.activeWorkspace === 'formularies') {
                if (state.isFormularyWorkspaceOpen && state.activeCountry) {
                    params.set('country', state.activeCountry);
                    params.set('tab', state.activeFormTab);
                    if (state.activeFormTab === 'tabBrandsList' && state.selectedBrandId) {
                        params.set('id', state.selectedBrandId);
                    } else if (state.activeFormTab === 'tabMfgList' && state.selectedMfgId) {
                        params.set('id', state.selectedMfgId);
                    }
                }
            } else if (state.activeWorkspace === 'dosage_forms') {
                params.set('tab', state.activeDosageTab);
                if (state.activeDosageTab === 'tabFormsList' && state.selectedDosageId) {
                    params.set('id', state.selectedDosageId);
                }
            }
        }

        var newSearch = '?' + params.toString();
        var currentSearch = window.location.search || '';

        if (newSearch !== currentSearch) {
            if (push) {
                history.pushState(null, '', newSearch);
            } else {
                history.replaceState(null, '', newSearch);
            }
        }
    }

    function restoreFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var view = params.get('view') || 'hub';
        var tab = params.get('tab');
        var id = params.get('id') ? parseInt(params.get('id'), 10) : null;
        var country = params.get('country');

        if (view === 'hub') {
            showHub(false);
        } else if (view === 'generics') {
            showWorkspace('generics', false);
            if (tab) {
                switchGenTab(tab, false);
            }
            if (id) {
                if (tab === 'tabClassList') {
                    selectClass(id, false);
                } else if (tab === 'tabIndList') {
                    selectIndication(id, false);
                } else {
                    selectGeneric(id, false);
                }
            }
        } else if (view === 'formularies') {
            showWorkspace('formularies', false);
            if (country) {
                openCountryWorkspace(country, false);
                if (tab) {
                    switchFormTab(tab, false);
                }
                if (id) {
                    if (tab === 'tabMfgList') {
                        selectManufacturer(id, false);
                    } else {
                        selectBrand(id, false);
                    }
                }
            } else {
                showCountryHub(false);
            }
        } else if (view === 'dosage_forms') {
            showWorkspace('dosage_forms', false);
            if (tab) {
                switchDosageTab(tab, false);
            }
            if (id) {
                selectDosageForm(id, false);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Top App Bar Controls & Navigation
    // -------------------------------------------------------------------------
    function updateNavHighlights() {
        var btnHome = document.getElementById('btnStudioHome');
        var btnGen = document.getElementById('btnNavGenerics');
        var btnForm = document.getElementById('btnNavFormularies');
        var btnDos = document.getElementById('btnNavDosageForms');

        if (btnHome) btnHome.classList.toggle('active', state.activeView === 'hub');
        if (btnGen) btnGen.classList.toggle('active', state.activeView === 'workspace' && state.activeWorkspace === 'generics');
        if (btnForm) btnForm.classList.toggle('active', state.activeView === 'workspace' && state.activeWorkspace === 'formularies');
        if (btnDos) btnDos.classList.toggle('active', state.activeView === 'workspace' && state.activeWorkspace === 'dosage_forms');
    }

    function showHub(pushUrl) {
        state.activeView = 'hub';
        document.getElementById('viewHub').style.display = 'block';
        document.getElementById('viewWorkspace').style.display = 'none';
        updateNavHighlights();
        loadHubStats();
        if (pushUrl !== false) syncUrl(true);
    }

    function showWorkspace(wsName, pushUrl) {
        state.activeView = 'workspace';
        state.activeWorkspace = wsName;
        document.getElementById('viewHub').style.display = 'none';
        document.getElementById('viewWorkspace').style.display = 'flex';

        document.getElementById('wsGenerics').style.display = (wsName === 'generics') ? 'flex' : 'none';
        document.getElementById('wsFormularies').style.display = (wsName === 'formularies') ? 'flex' : 'none';
        document.getElementById('wsDosageForms').style.display = (wsName === 'dosage_forms') ? 'flex' : 'none';

        updateNavHighlights();

        if (wsName === 'generics') {
            switchGenTab(state.activeGenTab, false);
        } else if (wsName === 'formularies') {
            if (!state.isFormularyWorkspaceOpen) {
                showCountryHub(false);
            }
        } else if (wsName === 'dosage_forms') {
            switchDosageTab(state.activeDosageTab, false);
        }

        if (pushUrl !== false) syncUrl(true);
    }

    function loadHubStats() {
        apiFetch('get_stats', { country: state.activeCountry }).then(function (data) {
            var elGen = document.getElementById('hubTotalGenerics');
            var elBrands = document.getElementById('hubTotalBrands');
            var elForms = document.getElementById('hubTotalDosageForms');
            var elPacks = document.getElementById('hubTotalPacks');
            if (elGen) elGen.textContent = (data.generics || 0).toLocaleString();
            if (elBrands) elBrands.textContent = (data.brands || 0).toLocaleString();
            if (elForms) elForms.textContent = (data.dosage_forms || 0).toLocaleString();
            if (elPacks) elPacks.textContent = (data.countries_count || 1);
        }).catch(function (err) {
            console.error(err);
        });
    }

    // -------------------------------------------------------------------------
    // 1. GENERICS SUBWORKSPACE
    // -------------------------------------------------------------------------
    function switchGenTab(tabName, pushUrl) {
        state.activeGenTab = tabName;
        var tabs = document.querySelectorAll('#tabsGenerics .drug-ws-tab');
        tabs.forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
        });

        // Hide all detail panels
        document.getElementById('genEmptyState').style.display = 'none';
        document.getElementById('panelGenInspector').style.display = 'none';
        document.getElementById('panelAddGeneric').style.display = 'none';
        document.getElementById('panelClassInspector').style.display = 'none';
        document.getElementById('panelAddClass').style.display = 'none';
        document.getElementById('panelIndInspector').style.display = 'none';
        document.getElementById('panelAddIndication').style.display = 'none';

        var listTitle = document.getElementById('genListHeaderTitle');
        var searchInput = document.getElementById('genSearchInput');
        var detailTitle = document.getElementById('genDetailHeaderTitle');

        if (tabName === 'tabGenList') {
            if (listTitle) listTitle.textContent = 'Generics Catalog';
            if (searchInput) searchInput.placeholder = 'Search generic name or WHO ATC...';
            if (detailTitle) detailTitle.textContent = 'Generic Monograph Inspector';
            if (state.selectedGenericId) {
                selectGeneric(state.selectedGenericId, false);
            } else {
                document.getElementById('genEmptyState').style.display = 'block';
            }
            fetchGenericsList(searchInput ? searchInput.value : '');
        } else if (tabName === 'tabGenAdd') {
            if (detailTitle) detailTitle.textContent = 'Add Active Generic';
            document.getElementById('panelAddGeneric').style.display = 'grid';
        } else if (tabName === 'tabClassList') {
            if (listTitle) listTitle.textContent = 'Classifications Catalog';
            if (searchInput) searchInput.placeholder = 'Search therapeutic classifications...';
            if (detailTitle) detailTitle.textContent = 'Classification Inspector';
            if (state.selectedClassId) {
                selectClass(state.selectedClassId, false);
            } else {
                document.getElementById('genEmptyState').style.display = 'block';
            }
            fetchClassesList(searchInput ? searchInput.value : '');
        } else if (tabName === 'tabClassAdd') {
            if (detailTitle) detailTitle.textContent = 'Add Drug Classification';
            document.getElementById('panelAddClass').style.display = 'grid';
        } else if (tabName === 'tabIndList') {
            if (listTitle) listTitle.textContent = 'Indications Catalog';
            if (searchInput) searchInput.placeholder = 'Search clinical indications...';
            if (detailTitle) detailTitle.textContent = 'Indication Inspector';
            if (state.selectedIndicationId) {
                selectIndication(state.selectedIndicationId, false);
            } else {
                document.getElementById('genEmptyState').style.display = 'block';
            }
            fetchIndicationsList(searchInput ? searchInput.value : '');
        } else if (tabName === 'tabIndAdd') {
            if (detailTitle) detailTitle.textContent = 'Add Clinical Indication';
            document.getElementById('panelAddIndication').style.display = 'grid';
        }

        if (pushUrl !== false) syncUrl(true);
    }

    function renderListItems(container, items, formatFn, selectedId, clickFn) {
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="drug-cautious-empty">No matching records in this catalog. The item may be listed under another name or may not yet be included. Verify against an authoritative source before adding it.</div>';
            return;
        }
        var html = '';
        items.forEach(function (item) {
            var f = formatFn(item);
            var isSel = (f.id === selectedId);
            html += '<div class="drug-list-item' + (isSel ? ' active' : '') + '" data-id="' + escapeHtml(f.id) + '">';
            html += '  <div class="drug-item-primary"><span>' + escapeHtml(f.primary) + '</span>' + (f.badge ? '<span class="drug-pill-tag">' + escapeHtml(f.badge) + '</span>' : '') + '</div>';
            if (f.secondary) {
                html += '  <div class="drug-item-secondary">' + escapeHtml(f.secondary) + '</div>';
            }
            html += '</div>';
        });
        container.innerHTML = html;

        container.querySelectorAll('.drug-list-item').forEach(function (el) {
            el.addEventListener('click', function () {
                var id = parseInt(el.getAttribute('data-id'), 10) || el.getAttribute('data-id');
                clickFn(id);
            });
        });
    }

    function markActiveListItem(container, id) {
        container.querySelectorAll('.drug-list-item').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-id') == String(id));
        });
    }

    function fetchGenericsList(q) {
        var list = document.getElementById('genItemsList');
        var countEl = document.getElementById('genListCount');
        apiFetch('list_generics', { q: q || '', limit: 100 }).then(function (res) {
            if (countEl) countEl.textContent = (res.total || 0).toLocaleString();
            renderListItems(list, res.items, function (item) {
                return {
                    id: item.generic_id,
                    primary: item.generic_name,
                    secondary: (item.who_atc_class ? item.who_atc_class + ' • ' : '') + (item.pregnancy_category ? 'Preg: ' + item.pregnancy_category : 'No ATC')
                };
            }, state.selectedGenericId, function (id) {
                selectGeneric(id, true);
            });
        }).catch(function () {
            list.innerHTML = '<div class="drug-empty-state">Failed to load generics.</div>';
        });
    }

    function selectGeneric(id, pushUrl) {
        state.selectedGenericId = id;
        markActiveListItem(document.getElementById('genItemsList'), id);

        apiFetch('get_generic', { id: id }).then(function (data) {
            state.selectedGenericData = data;
            document.getElementById('genEmptyState').style.display = 'none';
            document.getElementById('panelGenInspector').style.display = 'block';
            document.getElementById('genDetailHeaderTitle').textContent = data.generic_name + ' (ID: ' + data.generic_id + ')';

            // Top action buttons in header
            var actionsContainer = document.getElementById('genDetailHeaderActions');
            if (actionsContainer) {
                actionsContainer.innerHTML = '<button type="button" class="drug-btn drug-btn-danger drug-btn-xs" id="btnDeleteCurrentGen" title="Delete Generic">' + renderIcon('trash', 11) + '<span>Delete</span></button>';
                document.getElementById('btnDeleteCurrentGen').onclick = function () {
                    deleteCurrentGeneric(data.generic_id, data.generic_name);
                };
            }

            renderGenericMonograph(data);
            if (pushUrl !== false) syncUrl(true);
        }).catch(function (err) {
            showToast('Failed to load generic details: ' + err.message, 'error');
        });
    }

    function renderGenericMonograph(data) {
        // Section 1: Names & Classification
        setText('readGenName', data.generic_name);
        setText('readGenUsName', data.us_generic_name);
        setText('readGenAtc', data.who_atc_class);

        document.getElementById('inpEditGenId').value = data.generic_id;
        setVal('inpEditGenName', data.generic_name);
        setVal('inpEditGenUsName', data.us_generic_name);
        setVal('inpEditGenAtc', data.who_atc_class);

        renderTagsList('readGenClasses', data.linked_classes || [], 'class_name', function (c) {
            unlinkGenericFromClass(c.class_id, data.generic_id);
        });
        renderTagsList('readGenIndications', data.linked_indications || [], 'indication_name', function (ind) {
            unlinkGenericFromIndication(ind.indication_id, data.generic_id);
        });

        // Section 2: Safety Flags
        renderSafetyFlags(data);
        setVal('selEditIsAntibiotic', data.is_antibiotic ? '1' : '0');
        setVal('selEditIsHighAlert', data.is_high_alert_medicine ? '1' : '0');
        setVal('selEditSafePregnancy', data.is_safe_in_pregnancy !== null && data.is_safe_in_pregnancy !== undefined ? String(data.is_safe_in_pregnancy) : '');
        setVal('selEditSafeLactation', data.is_safe_in_lactation !== null && data.is_safe_in_lactation !== undefined ? String(data.is_safe_in_lactation) : '');
        setVal('selEditRenalAdj', data.require_renal_adjustments !== null && data.require_renal_adjustments !== undefined ? String(data.require_renal_adjustments) : '');
        setVal('selEditHepaticSafe', data.is_safe_in_hepatic_impairment !== null && data.is_safe_in_hepatic_impairment !== undefined ? String(data.is_safe_in_hepatic_impairment) : '');
        setVal('selEditPaediatricSafe', data.is_safe_in_paediatric !== null && data.is_safe_in_paediatric !== undefined ? String(data.is_safe_in_paediatric) : '');
        setVal('selEditRequiresTapering', data.requires_tapering !== null && data.requires_tapering !== undefined ? String(data.requires_tapering) : '');

        // Section 3: Warnings & Precautions
        setText('readGenWarning', data.immediate_warning);
        setText('readGenContra', data.contra_indication);
        setText('readGenPrecautions', data.precaution);
        setText('readGenSideEffects', data.side_effect);
        setVal('inpEditGenWarning', data.immediate_warning);
        setVal('inpEditGenContra', data.contra_indication);
        setVal('inpEditGenPrecautions', data.precaution);
        setVal('inpEditGenSideEffects', data.side_effect);

        // Section 4: Indications & MoA
        setText('readGenIndicationText', data.indication);
        setText('readGenMoaSummary', data.mode_of_action_summary);
        setText('readGenMoaFlow', data.mode_of_action_flow);
        setText('readGenInteraction', data.interaction);
        setVal('inpEditGenIndicationText', data.indication);
        setVal('inpEditGenMoaSummary', data.mode_of_action_summary);
        setVal('inpEditGenMoaFlow', data.mode_of_action_flow);
        setVal('inpEditGenInteraction', data.interaction);

        // Section 5: Pregnancy & Lactation
        setText('readGenPregCategory', data.pregnancy_category);
        setText('readGenPregModern', data.pregnancy_modern_category);
        setText('readGenTrimester', data.pregnancy_trimester_safety);
        setText('readGenLactationNote', data.pregnancy_category_and_lactation_note);
        setVal('selEditGenPregnancy', data.pregnancy_category);
        setVal('inpEditGenPregModern', data.pregnancy_modern_category);
        setVal('inpEditGenTrimester', data.pregnancy_trimester_safety);
        setVal('inpEditGenLactationNote', data.pregnancy_category_and_lactation_note);

        // Section 6: Dosing & Administration
        setText('readGenAdultDose', data.adult_dose);
        setText('readGenChildDose', data.child_dose);
        setText('readGenPaedCalc', data.paediatric_calc_parameter);
        setText('readGenRenalDose', data.renal_dose);
        setText('readGenAdministration', data.administration);
        setVal('inpEditGenAdultDose', data.adult_dose);
        setVal('inpEditGenChildDose', data.child_dose);
        setVal('inpEditGenPaedCalc', data.paediatric_calc_parameter);
        setVal('inpEditGenRenalDose', data.renal_dose);
        setVal('inpEditGenAdministration', data.administration);

        // Section 7: Overdose & Storage
        setText('readGenOverdoseEffect', data.overdose_effect);
        setText('readGenOverdoseTreatment', data.overdose_treatment);
        setText('readGenStorage', data.storage);
        setText('readGenPubmed', data.pubmed_query_base);
        setText('readGenCounselling', data.counselling_pearl);
        setVal('inpEditGenOverdoseEffect', data.overdose_effect);
        setVal('inpEditGenOverdoseTreatment', data.overdose_treatment);
        setVal('inpEditGenStorage', data.storage);
        setVal('inpEditGenPubmed', data.pubmed_query_base);
        setVal('inpEditGenCounselling', data.counselling_pearl);

        // Ensure all sections are closed in read mode
        for (var i = 1; i <= 7; i++) {
            setAccordionMode(i, 'read');
        }
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (!el) return;
        if (val === null || val === undefined || String(val).trim() === '') {
            el.innerHTML = '<span class="drug-field-empty">Not recorded</span>';
        } else {
            el.textContent = String(val);
        }
    }

    function setVal(id, val) {
        var el = document.getElementById(id);
        if (!el) return;
        el.value = (val !== null && val !== undefined) ? String(val) : '';
    }

    function renderTagsList(containerId, items, labelKey, onUnlink) {
        var container = document.getElementById(containerId);
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<span class="drug-field-empty">None linked</span>';
            return;
        }
        var html = '';
        items.forEach(function (it, idx) {
            html += '<span class="drug-tag-item">';
            html += '  <span>' + escapeHtml(it[labelKey] || it.name || '') + '</span>';
            html += '  <button type="button" class="drug-tag-remove" data-idx="' + idx + '" title="Unlink">' + renderIcon('x', 10) + '</button>';
            html += '</span>';
        });
        container.innerHTML = html;

        container.querySelectorAll('.drug-tag-remove').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                if (items[idx]) onUnlink(items[idx]);
            });
        });
    }

    function renderSafetyFlags(data) {
        var grid = document.getElementById('readSafetyPillsGrid');
        if (!grid) return;

        var flags = [
            { label: 'Antibiotic', val: data.is_antibiotic, type: data.is_antibiotic ? 'caution' : 'neutral', text: data.is_antibiotic ? 'Yes (Antibiotic)' : 'No' },
            { label: 'High-Alert', val: data.is_high_alert_medicine, type: data.is_high_alert_medicine ? 'danger' : 'neutral', text: data.is_high_alert_medicine ? 'Yes (High-Alert)' : 'No' },
            { label: 'Pregnancy', val: data.is_safe_in_pregnancy, type: (data.is_safe_in_pregnancy === 1 ? 'safe' : (data.is_safe_in_pregnancy === 0 ? 'danger' : 'neutral')), text: formatTriState(data.is_safe_in_pregnancy, 'Safe', 'Avoid') },
            { label: 'Lactation', val: data.is_safe_in_lactation, type: (data.is_safe_in_lactation === 1 ? 'safe' : (data.is_safe_in_lactation === 0 ? 'danger' : 'neutral')), text: formatTriState(data.is_safe_in_lactation, 'Safe', 'Avoid') },
            { label: 'Renal Adjustment', val: data.require_renal_adjustments, type: (data.require_renal_adjustments === 1 ? 'caution' : 'neutral'), text: formatTriState(data.require_renal_adjustments, 'Required', 'Not Required') },
            { label: 'Hepatic Safety', val: data.is_safe_in_hepatic_impairment, type: (data.is_safe_in_hepatic_impairment === 1 ? 'safe' : (data.is_safe_in_hepatic_impairment === 0 ? 'danger' : 'neutral')), text: formatTriState(data.is_safe_in_hepatic_impairment, 'Safe', 'Caution / Avoid') },
            { label: 'Paediatric Safety', val: data.is_safe_in_paediatric, type: (data.is_safe_in_paediatric === 1 ? 'safe' : (data.is_safe_in_paediatric === 0 ? 'danger' : 'neutral')), text: formatTriState(data.is_safe_in_paediatric, 'Safe', 'Caution / Avoid') },
            { label: 'Requires Tapering', val: data.requires_tapering, type: (data.requires_tapering === 1 ? 'caution' : 'neutral'), text: formatTriState(data.requires_tapering, 'Yes', 'No') }
        ];

        var html = '';
        flags.forEach(function (f) {
            html += '<div class="drug-safety-pill ' + f.type + '">';
            html += '  <span class="drug-safety-pill-lbl">' + escapeHtml(f.label) + '</span>';
            html += '  <span class="drug-safety-pill-val">' + escapeHtml(f.text) + '</span>';
            html += '</div>';
        });
        grid.innerHTML = html;
    }

    function formatTriState(val, trueText, falseText) {
        if (val === 1 || val === '1' || val === true) return trueText;
        if (val === 0 || val === '0' || val === false) return falseText;
        return 'Not recorded';
    }

    function setAccordionMode(secNum, mode) {
        var readView = document.getElementById('accGenRead' + secNum);
        var editView = document.getElementById('accGenEdit' + secNum);
        var acc = document.getElementById('accGen' + secNum);
        if (!acc) return;

        var btnEdit = acc.querySelector('.drug-acc-btn-edit');
        var btnSave = acc.querySelector('.drug-acc-btn-save');
        var btnCancel = acc.querySelector('.drug-acc-btn-cancel');

        if (mode === 'edit') {
            if (readView) readView.style.display = 'none';
            if (editView) editView.style.display = 'block';
            if (btnEdit) btnEdit.style.display = 'none';
            if (btnSave) btnSave.style.display = 'inline-flex';
            if (btnCancel) btnCancel.style.display = 'inline-flex';
        } else {
            if (readView) readView.style.display = 'block';
            if (editView) editView.style.display = 'none';
            if (btnEdit) btnEdit.style.display = 'inline-flex';
            if (btnSave) btnSave.style.display = 'none';
            if (btnCancel) btnCancel.style.display = 'none';
        }
    }

    function saveGenericSection(secNum) {
        if (!state.selectedGenericId) return;
        var fields = {};

        if (secNum === 1) {
            fields.generic_name = document.getElementById('inpEditGenName').value.trim();
            fields.us_generic_name = document.getElementById('inpEditGenUsName').value.trim();
            fields.who_atc_class = document.getElementById('inpEditGenAtc').value.trim();
        } else if (secNum === 2) {
            fields.is_antibiotic = parseInt(document.getElementById('selEditIsAntibiotic').value, 10);
            fields.is_high_alert_medicine = parseInt(document.getElementById('selEditIsHighAlert').value, 10);
            fields.is_safe_in_pregnancy = parseNullableInt(document.getElementById('selEditSafePregnancy').value);
            fields.is_safe_in_lactation = parseNullableInt(document.getElementById('selEditSafeLactation').value);
            fields.require_renal_adjustments = parseNullableInt(document.getElementById('selEditRenalAdj').value);
            fields.is_safe_in_hepatic_impairment = parseNullableInt(document.getElementById('selEditHepaticSafe').value);
            fields.is_safe_in_paediatric = parseNullableInt(document.getElementById('selEditPaediatricSafe').value);
            fields.requires_tapering = parseNullableInt(document.getElementById('selEditRequiresTapering').value);
        } else if (secNum === 3) {
            fields.immediate_warning = document.getElementById('inpEditGenWarning').value.trim();
            fields.contra_indication = document.getElementById('inpEditGenContra').value.trim();
            fields.precaution = document.getElementById('inpEditGenPrecautions').value.trim();
            fields.side_effect = document.getElementById('inpEditGenSideEffects').value.trim();
        } else if (secNum === 4) {
            fields.indication = document.getElementById('inpEditGenIndicationText').value.trim();
            fields.mode_of_action_summary = document.getElementById('inpEditGenMoaSummary').value.trim();
            fields.mode_of_action_flow = document.getElementById('inpEditGenMoaFlow').value.trim();
            fields.interaction = document.getElementById('inpEditGenInteraction').value.trim();
        } else if (secNum === 5) {
            fields.pregnancy_category = document.getElementById('selEditGenPregnancy').value.trim();
            fields.pregnancy_modern_category = document.getElementById('inpEditGenPregModern').value.trim();
            fields.pregnancy_trimester_safety = document.getElementById('inpEditGenTrimester').value.trim();
            fields.pregnancy_category_and_lactation_note = document.getElementById('inpEditGenLactationNote').value.trim();
        } else if (secNum === 6) {
            fields.adult_dose = document.getElementById('inpEditGenAdultDose').value.trim();
            fields.child_dose = document.getElementById('inpEditGenChildDose').value.trim();
            fields.paediatric_calc_parameter = document.getElementById('inpEditGenPaedCalc').value.trim();
            fields.renal_dose = document.getElementById('inpEditGenRenalDose').value.trim();
            fields.administration = document.getElementById('inpEditGenAdministration').value.trim();
        } else if (secNum === 7) {
            fields.overdose_effect = document.getElementById('inpEditGenOverdoseEffect').value.trim();
            fields.overdose_treatment = document.getElementById('inpEditGenOverdoseTreatment').value.trim();
            fields.storage = document.getElementById('inpEditGenStorage').value.trim();
            fields.pubmed_query_base = document.getElementById('inpEditGenPubmed').value.trim();
            fields.counselling_pearl = document.getElementById('inpEditGenCounselling').value.trim();
        }

        apiFetch('save_generic_section', null, { id: state.selectedGenericId, fields: fields }).then(function (updated) {
            state.selectedGenericData = updated;
            renderGenericMonograph(updated);
            showToast('Section ' + secNum + ' saved successfully.', 'success');
            setAccordionMode(secNum, 'read');

            // Refresh list item if name changed
            if (fields.generic_name) {
                fetchGenericsList(document.getElementById('genSearchInput').value);
            }
        }).catch(function (err) {
            showToast('Failed to save section: ' + err.message, 'error');
        });
    }

    function parseNullableInt(val) {
        if (val === '' || val === null || val === undefined) return null;
        return parseInt(val, 10);
    }

    function unlinkGenericFromClass(classId, genericId) {
        apiFetch('unlink_class_generic', null, { class_id: classId, generic_id: genericId }).then(function (updated) {
            state.selectedGenericData = updated;
            renderGenericMonograph(updated);
            showToast('Classification unlinked.');
        }).catch(function (err) {
            showToast(err.message, 'error');
        });
    }

    function unlinkGenericFromIndication(indId, genericId) {
        apiFetch('unlink_indication_generic', null, { indication_id: indId, generic_id: genericId }).then(function (updated) {
            state.selectedGenericData = updated;
            renderGenericMonograph(updated);
            showToast('Indication unlinked.');
        }).catch(function (err) {
            showToast(err.message, 'error');
        });
    }

    function deleteCurrentGeneric(id, name) {
        if (!confirm('Are you sure you want to delete generic "' + name + '" (ID: ' + id + ')? This will remove it from the catalog.')) {
            return;
        }
        apiFetch('delete_generic', null, { id: id }).then(function () {
            showToast('Generic deleted successfully.', 'success');
            state.selectedGenericId = null;
            state.selectedGenericData = null;
            document.getElementById('panelGenInspector').style.display = 'none';
            document.getElementById('genEmptyState').style.display = 'block';
            fetchGenericsList('');
            syncUrl(true);
        }).catch(function (err) {
            showToast(err.message, 'error');
        });
    }

    // -------------------------------------------------------------------------
    // Classifications & Indications Handling
    // -------------------------------------------------------------------------
    function fetchClassesList(q) {
        var list = document.getElementById('genItemsList');
        var countEl = document.getElementById('genListCount');
        apiFetch('list_classes', { q: q || '', limit: 100 }).then(function (res) {
            if (countEl) countEl.textContent = (res.total || 0).toLocaleString();
            renderListItems(list, res.items, function (item) {
                return {
                    id: item.class_id,
                    primary: item.class_name,
                    secondary: item.category_name || item.subcategory_name || 'Therapeutic Classification'
                };
            }, state.selectedClassId, function (id) {
                selectClass(id, true);
            });
        }).catch(function () {
            list.innerHTML = '<div class="drug-empty-state">Failed to load classifications.</div>';
        });
    }

    function selectClass(id, pushUrl) {
        state.selectedClassId = id;
        markActiveListItem(document.getElementById('genItemsList'), id);

        apiFetch('get_class', { id: id }).then(function (data) {
            state.selectedClassData = data;
            document.getElementById('genEmptyState').style.display = 'none';
            document.getElementById('panelClassInspector').style.display = 'block';
            document.getElementById('genDetailHeaderTitle').textContent = data.class_name;

            setText('readClassName', data.class_name);
            setText('readClassCategory', data.category_name);
            setText('readClassSubcategory', data.subcategory_name);

            document.getElementById('inpEditClassId').value = data.class_id;
            setVal('inpEditClassName', data.class_name);
            setVal('inpEditClassCategory', data.category_name);
            setVal('inpEditClassSubcategory', data.subcategory_name);

            renderTagsList('readClassGenericsTags', data.linked_generics || [], 'generic_name', function (g) {
                unlinkGenericFromClass(data.class_id, g.generic_id);
            });

            // Set read mode initially
            setClassEditMode(false);
            if (pushUrl !== false) syncUrl(true);
        }).catch(function (err) {
            showToast(err.message, 'error');
        });
    }

    function setClassEditMode(isEdit) {
        var readView = document.getElementById('classReadView');
        var editView = document.getElementById('classEditView');
        var btnSave = document.getElementById('btnSaveClass');
        var lbl = document.getElementById('lblToggleEditClass');
        if (readView) readView.style.display = isEdit ? 'none' : 'grid';
        if (editView) editView.style.display = isEdit ? 'block' : 'none';
        if (btnSave) btnSave.style.display = isEdit ? 'inline-flex' : 'none';
        if (lbl) lbl.textContent = isEdit ? 'Cancel' : 'Edit';
    }

    function fetchIndicationsList(q) {
        var list = document.getElementById('genItemsList');
        var countEl = document.getElementById('genListCount');
        apiFetch('list_indications', { q: q || '', limit: 100 }).then(function (res) {
            if (countEl) countEl.textContent = (res.total || 0).toLocaleString();
            renderListItems(list, res.items, function (item) {
                return {
                    id: item.indication_id,
                    primary: item.indication_name,
                    secondary: item.indication_desc || 'Medical Indication'
                };
            }, state.selectedIndicationId, function (id) {
                selectIndication(id, true);
            });
        }).catch(function () {
            list.innerHTML = '<div class="drug-empty-state">Failed to load indications.</div>';
        });
    }

    function selectIndication(id, pushUrl) {
        state.selectedIndicationId = id;
        markActiveListItem(document.getElementById('genItemsList'), id);

        apiFetch('get_indication', { id: id }).then(function (data) {
            state.selectedIndicationData = data;
            document.getElementById('genEmptyState').style.display = 'none';
            document.getElementById('panelIndInspector').style.display = 'block';
            document.getElementById('genDetailHeaderTitle').textContent = data.indication_name;

            setText('readIndName', data.indication_name);
            setText('readIndDesc', data.indication_desc);

            document.getElementById('inpEditIndId').value = data.indication_id;
            setVal('inpEditIndName', data.indication_name);
            setVal('inpEditIndDesc', data.indication_desc);

            renderTagsList('readIndGenericsTags', data.linked_generics || [], 'generic_name', function (g) {
                unlinkGenericFromIndication(data.indication_id, g.generic_id);
            });

            setIndicationEditMode(false);
            if (pushUrl !== false) syncUrl(true);
        }).catch(function (err) {
            showToast(err.message, 'error');
        });
    }

    function setIndicationEditMode(isEdit) {
        var readView = document.getElementById('indReadView');
        var editView = document.getElementById('indEditView');
        var btnSave = document.getElementById('btnSaveInd');
        var lbl = document.getElementById('lblToggleEditInd');
        if (readView) readView.style.display = isEdit ? 'none' : 'grid';
        if (editView) editView.style.display = isEdit ? 'block' : 'none';
        if (btnSave) btnSave.style.display = isEdit ? 'inline-flex' : 'none';
        if (lbl) lbl.textContent = isEdit ? 'Cancel' : 'Edit';
    }

    // -------------------------------------------------------------------------
    // 2. FORMULARIES SUBWORKSPACE
    // -------------------------------------------------------------------------
    function showCountryHub(pushUrl) {
        state.isFormularyWorkspaceOpen = false;
        document.getElementById('formulariesCountryHub').style.display = 'block';
        document.getElementById('formulariesCountryWorkspace').style.display = 'none';
        loadCountryPacks();
        if (pushUrl !== false) syncUrl(true);
    }

    function openCountryWorkspace(countryCode, pushUrl) {
        state.activeCountry = countryCode || 'BD';
        state.isFormularyWorkspaceOpen = true;
        document.getElementById('formulariesCountryHub').style.display = 'none';
        document.getElementById('formulariesCountryWorkspace').style.display = 'flex';

        var codeEl = document.getElementById('wsActiveCountryCode');
        var titleEl = document.getElementById('wsActiveCountryTitle');
        var badgeEl = document.getElementById('activeCountryBadge');
        if (codeEl) codeEl.textContent = state.activeCountry;
        if (titleEl) titleEl.textContent = (state.activeCountry === 'BD' ? 'Bangladesh Commercial Formulary' : state.activeCountry + ' Commercial Formulary');
        if (badgeEl) badgeEl.textContent = state.activeCountry + ' Pack';

        switchFormTab(state.activeFormTab, false);
        if (pushUrl !== false) syncUrl(true);
    }

    function loadCountryPacks() {
        var grid = document.getElementById('countryCardsGrid');
        grid.innerHTML = '<div class="drug-empty-state drug-col-full">Loading available country formularies...</div>';
        apiFetch('list_countries').then(function (countries) {
            state.countries = countries;
            var html = '';
            countries.forEach(function (c) {
                html += '<div class="drug-country-card" data-code="' + escapeHtml(c.code) + '">';
                html += '  <div class="drug-cc-header">';
                html += '    <span class="drug-cc-code">' + escapeHtml(c.code) + '</span>';
                html += '    <span class="drug-cc-version">v' + escapeHtml(c.version || '1.0.0') + '</span>';
                html += '  </div>';
                html += '  <div class="drug-cc-name">' + escapeHtml(c.name) + '</div>';
                html += '  <div class="drug-cc-stats">';
                html += '    <span><strong>' + (c.brands_count || 0).toLocaleString() + '</strong> Trade Names</span>';
                html += '    <span><strong>' + (c.manufacturers_count || 0).toLocaleString() + '</strong> Manufacturers</span>';
                html += '  </div>';
                html += '</div>';
            });
            grid.innerHTML = html;

            grid.querySelectorAll('.drug-country-card').forEach(function (card) {
                card.addEventListener('click', function () {
                    var code = card.getAttribute('data-code');
                    openCountryWorkspace(code, true);
                });
            });
        }).catch(function () {
            grid.innerHTML = '<div class="drug-empty-state drug-col-full">Failed to load country packs.</div>';
        });
    }

    function switchFormTab(tabName, pushUrl) {
        state.activeFormTab = tabName;
        var tabs = document.querySelectorAll('#tabsFormularies .drug-ws-tab');
        tabs.forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
        });

        document.getElementById('brandEmptyState').style.display = 'none';
        document.getElementById('panelBrandInspector').style.display = 'none';
        document.getElementById('panelAddBrand').style.display = 'none';
        document.getElementById('panelMfgInspector').style.display = 'none';
        document.getElementById('panelAddMfg').style.display = 'none';

        var listTitle = document.getElementById('brandListHeaderTitle');
        var searchInput = document.getElementById('brandSearchInput');
        var detailTitle = document.getElementById('brandDetailHeaderTitle');

        if (tabName === 'tabBrandsList') {
            if (listTitle) listTitle.textContent = 'Trade Brands';
            if (searchInput) searchInput.placeholder = 'Search trade brand or manufacturer...';
            if (detailTitle) detailTitle.textContent = 'Brand Inspector';
            if (state.selectedBrandId) {
                selectBrand(state.selectedBrandId, false);
            } else {
                document.getElementById('brandEmptyState').style.display = 'block';
            }
            fetchBrandsList(searchInput ? searchInput.value : '');
        } else if (tabName === 'tabBrandsAdd') {
            if (detailTitle) detailTitle.textContent = 'Add Commercial Trade Name';
            document.getElementById('panelAddBrand').style.display = 'grid';
            loadDosageFormsDropdown('selAddBrandForm');
        } else if (tabName === 'tabMfgList') {
            if (listTitle) listTitle.textContent = 'Manufacturers Catalog';
            if (searchInput) searchInput.placeholder = 'Search pharmaceutical manufacturers...';
            if (detailTitle) detailTitle.textContent = 'Manufacturer Inspector';
            if (state.selectedMfgId) {
                selectManufacturer(state.selectedMfgId, false);
            } else {
                document.getElementById('brandEmptyState').style.display = 'block';
            }
            fetchManufacturersList(searchInput ? searchInput.value : '');
        } else if (tabName === 'tabMfgAdd') {
            if (detailTitle) detailTitle.textContent = 'Add Manufacturer';
            document.getElementById('panelAddMfg').style.display = 'grid';
        }

        if (pushUrl !== false) syncUrl(true);
    }

    function fetchBrandsList(q) {
        var list = document.getElementById('brandItemsList');
        var countEl = document.getElementById('brandListCount');
        apiFetch('list_brands', { country: state.activeCountry, q: q || '', limit: 100 }).then(function (res) {
            if (countEl) countEl.textContent = (res.total || 0).toLocaleString();
            renderListItems(list, res.items, function (item) {
                return {
                    id: item.brand_id,
                    primary: item.brand_name,
                    secondary: (item.form ? item.form + ' ' : '') + (item.strength ? item.strength + ' • ' : '') + (item.generic_name || item.manufacturer_name || '')
                };
            }, state.selectedBrandId, function (id) {
                selectBrand(id, true);
            });
        }).catch(function () {
            list.innerHTML = '<div class="drug-empty-state">Failed to load trade names.</div>';
        });
    }

    function selectBrand(id, pushUrl) {
        state.selectedBrandId = id;
        markActiveListItem(document.getElementById('brandItemsList'), id);

        apiFetch('get_brand', { country: state.activeCountry, id: id }).then(function (data) {
            state.selectedBrandData = data;
            document.getElementById('brandEmptyState').style.display = 'none';
            document.getElementById('panelBrandInspector').style.display = 'block';
            document.getElementById('brandDetailHeaderTitle').textContent = data.brand_name;

            setText('readBrandName', data.brand_name);
            setText('readBrandForm', data.form);
            setText('readBrandStrength', data.strength);
            setText('readBrandGeneric', data.generic_name ? data.generic_name + ' (ID: ' + data.generic_id + ')' : data.generic_id);
            setText('readBrandMfg', data.manufacturer_name ? data.manufacturer_name + ' (ID: ' + data.manufacturer_id + ')' : data.manufacturer_id);
            setText('readBrandPack', data.packsize);
            setText('readBrandPrice', data.price);

            document.getElementById('inpEditBrandId').value = data.brand_id;
            setVal('inpEditBrandName', data.brand_name);
            setVal('inpEditBrandStrength', data.strength);
            setVal('inpEditBrandPack', data.packsize);
            setVal('inpEditBrandPrice', data.price);
            document.getElementById('inpEditBrandGenId').value = data.generic_id || '';
            document.getElementById('inpEditBrandMfgId').value = data.manufacturer_id || '';

            loadDosageFormsDropdown('selEditBrandForm', data.form);
            setBrandEditMode(false);
            if (pushUrl !== false) syncUrl(true);
        }).catch(function (err) {
            showToast(err.message, 'error');
        });
    }

    function setBrandEditMode(isEdit) {
        var readView = document.getElementById('brandReadView');
        var editView = document.getElementById('brandEditView');
        var btnSave = document.getElementById('btnSaveBrand');
        var lbl = document.getElementById('lblToggleEditBrand');
        if (readView) readView.style.display = isEdit ? 'none' : 'grid';
        if (editView) editView.style.display = isEdit ? 'block' : 'none';
        if (btnSave) btnSave.style.display = isEdit ? 'inline-flex' : 'none';
        if (lbl) lbl.textContent = isEdit ? 'Cancel' : 'Edit';
    }

    function fetchManufacturersList(q) {
        var list = document.getElementById('brandItemsList');
        var countEl = document.getElementById('brandListCount');
        apiFetch('list_manufacturers', { country: state.activeCountry, q: q || '', limit: 100 }).then(function (res) {
            if (countEl) countEl.textContent = (res.total || 0).toLocaleString();
            renderListItems(list, res.items, function (item) {
                return {
                    id: item.manufacturer_id,
                    primary: item.manufacturer_name,
                    secondary: (item.brands_count || 0) + ' licensed brands'
                };
            }, state.selectedMfgId, function (id) {
                selectManufacturer(id, true);
            });
        }).catch(function () {
            list.innerHTML = '<div class="drug-empty-state">Failed to load manufacturers.</div>';
        });
    }

    function selectManufacturer(id, pushUrl) {
        state.selectedMfgId = id;
        markActiveListItem(document.getElementById('brandItemsList'), id);

        apiFetch('get_manufacturer', { country: state.activeCountry, id: id }).then(function (data) {
            state.selectedMfgData = data;
            document.getElementById('brandEmptyState').style.display = 'none';
            document.getElementById('panelMfgInspector').style.display = 'block';
            document.getElementById('brandDetailHeaderTitle').textContent = data.manufacturer_name;

            setText('readMfgName', data.manufacturer_name);
            setText('readMfgShort', data.manufacturer_name_short);
            setText('readMfgCountry', state.activeCountry);
            setText('readMfgBrandCount', data.brands_count || 0);

            document.getElementById('inpEditMfgId').value = data.manufacturer_id;
            setVal('inpEditMfgName', data.manufacturer_name);
            setVal('inpEditMfgShort', data.manufacturer_name_short);
            setVal('inpEditMfgCountry', state.activeCountry);

            setMfgEditMode(false);
            if (pushUrl !== false) syncUrl(true);
        }).catch(function (err) {
            showToast(err.message, 'error');
        });
    }

    function setMfgEditMode(isEdit) {
        var readView = document.getElementById('mfgReadView');
        var editView = document.getElementById('mfgEditView');
        var btnSave = document.getElementById('btnSaveMfg');
        var lbl = document.getElementById('lblToggleEditMfg');
        if (readView) readView.style.display = isEdit ? 'none' : 'grid';
        if (editView) editView.style.display = isEdit ? 'block' : 'none';
        if (btnSave) btnSave.style.display = isEdit ? 'inline-flex' : 'none';
        if (lbl) lbl.textContent = isEdit ? 'Cancel' : 'Edit';
    }

    function loadDosageFormsDropdown(selectId, selectedVal) {
        var sel = document.getElementById(selectId);
        if (!sel) return;
        if (state.dosageFormsList && state.dosageFormsList.length > 0) {
            populateDropdown(sel, state.dosageFormsList, selectedVal);
            return;
        }
        apiFetch('list_dosage_forms', { limit: 200 }).then(function (forms) {
            state.dosageFormsList = forms;
            populateDropdown(sel, forms, selectedVal);
        });
    }

    function populateDropdown(sel, forms, selectedVal) {
        sel.innerHTML = '<option value="">-- Select Dosage Form --</option>';
        forms.forEach(function (f) {
            var opt = document.createElement('option');
            opt.value = f.form;
            opt.textContent = f.form + (f.prefix_short ? ' (' + f.prefix_short + ')' : '');
            if (selectedVal && f.form.toLowerCase() === selectedVal.toLowerCase()) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });
    }

    // -------------------------------------------------------------------------
    // 3. DOSAGE FORMS SUBWORKSPACE
    // -------------------------------------------------------------------------
    function switchDosageTab(tabName, pushUrl) {
        state.activeDosageTab = tabName;
        var tabs = document.querySelectorAll('#tabsDosageForms .drug-ws-tab');
        tabs.forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
        });

        document.getElementById('formsEmptyState').style.display = 'none';
        document.getElementById('panelDosageInspector').style.display = 'none';
        document.getElementById('panelAddDosage').style.display = 'none';

        var detailTitle = document.getElementById('formsDetailHeaderTitle');
        var searchInput = document.getElementById('formsSearchInput');

        if (tabName === 'tabFormsList') {
            if (detailTitle) detailTitle.textContent = 'Dosage Form Inspector';
            if (state.selectedDosageId) {
                selectDosageForm(state.selectedDosageId, false);
            } else {
                document.getElementById('formsEmptyState').style.display = 'block';
            }
            fetchDosageFormsList(searchInput ? searchInput.value : '');
        } else if (tabName === 'tabFormsAdd') {
            if (detailTitle) detailTitle.textContent = 'Add Standard Dosage Form';
            document.getElementById('panelAddDosage').style.display = 'grid';
        }

        if (pushUrl !== false) syncUrl(true);
    }

    function fetchDosageFormsList(q) {
        var list = document.getElementById('formsItemsList');
        var countEl = document.getElementById('formsListCount');
        apiFetch('list_dosage_forms', { q: q || '', limit: 150 }).then(function (items) {
            state.dosageFormsList = items;
            if (countEl) countEl.textContent = items.length.toLocaleString();
            renderListItems(list, items, function (item) {
                return {
                    id: item.form_id,
                    primary: item.form,
                    secondary: (item.prefix_short ? item.prefix_short + ' • ' : '') + (item.add_strength_brand ? 'Append Strength' : 'Fixed Form')
                };
            }, state.selectedDosageId, function (id) {
                selectDosageForm(id, true);
            });
        }).catch(function () {
            list.innerHTML = '<div class="drug-empty-state">Failed to load dosage forms.</div>';
        });
    }

    function selectDosageForm(id, pushUrl) {
        state.selectedDosageId = id;
        markActiveListItem(document.getElementById('formsItemsList'), id);

        apiFetch('get_dosage_form', { id: id }).then(function (data) {
            state.selectedDosageData = data;
            document.getElementById('formsEmptyState').style.display = 'none';
            document.getElementById('panelDosageInspector').style.display = 'block';
            document.getElementById('formsDetailHeaderTitle').textContent = data.form;

            setText('readDosageName', data.form);
            setText('readDosagePrefix', data.prefix_short);
            setText('readDosageFullPrefix', data.prefix_full);
            setText('readDosageAppendRule', data.add_strength_brand ? 'Yes (Append strength in prescription line)' : 'No');
            setText('readDosageOrder', data.form_order || 0);

            document.getElementById('inpEditDosageId').value = data.form_id;
            setVal('inpEditDosageName', data.form);
            setVal('inpEditDosagePrefix', data.prefix_short);
            setVal('inpEditDosageFullPrefix', data.prefix_full);
            setVal('inpEditDosageOrder', data.form_order || 0);
            setVal('selEditDosageAppendRule', data.add_strength_brand ? '1' : '0');

            setDosageEditMode(false);
            if (pushUrl !== false) syncUrl(true);
        }).catch(function (err) {
            showToast(err.message, 'error');
        });
    }

    function setDosageEditMode(isEdit) {
        var readView = document.getElementById('dosageReadView');
        var editView = document.getElementById('dosageEditView');
        var btnSave = document.getElementById('btnSaveDosage');
        var lbl = document.getElementById('lblToggleEditDosage');
        if (readView) readView.style.display = isEdit ? 'none' : 'grid';
        if (editView) editView.style.display = isEdit ? 'block' : 'none';
        if (btnSave) btnSave.style.display = isEdit ? 'inline-flex' : 'none';
        if (lbl) lbl.textContent = isEdit ? 'Cancel' : 'Edit';
    }

    // -------------------------------------------------------------------------
    // Event Listeners & Bootstrapping
    // -------------------------------------------------------------------------
    function initEventBindings() {
        // Appbar Navigation
        var brandHome = document.getElementById('brandHomeLink');
        var btnHome = document.getElementById('btnStudioHome');
        var btnGen = document.getElementById('btnNavGenerics');
        var btnForm = document.getElementById('btnNavFormularies');
        var btnDos = document.getElementById('btnNavDosageForms');

        if (brandHome) brandHome.addEventListener('click', function () { showHub(true); });
        if (btnHome) btnHome.addEventListener('click', function () { showHub(true); });
        if (btnGen) btnGen.addEventListener('click', function () { showWorkspace('generics', true); });
        if (btnForm) btnForm.addEventListener('click', function () { showWorkspace('formularies', true); });
        if (btnDos) btnDos.addEventListener('click', function () { showWorkspace('dosage_forms', true); });

        // Hub Cards
        var hubGen = document.getElementById('btnHubOpenGenerics');
        var hubForm = document.getElementById('btnHubOpenFormularies');
        var hubDos = document.getElementById('btnHubOpenDosageForms');
        if (hubGen) hubGen.addEventListener('click', function () { showWorkspace('generics', true); });
        if (hubForm) hubForm.addEventListener('click', function () { showWorkspace('formularies', true); });
        if (hubDos) hubDos.addEventListener('click', function () { showWorkspace('dosage_forms', true); });

        // Meta Subbars Back Buttons
        var backGen = document.getElementById('btnGenericsBackToHub');
        var backForm = document.getElementById('btnBackToCountryHub');
        var backDos = document.getElementById('btnDosageBackToHub');
        if (backGen) backGen.addEventListener('click', function () { showHub(true); });
        if (backForm) backForm.addEventListener('click', function () { showCountryHub(true); });
        if (backDos) backDos.addEventListener('click', function () { showHub(true); });

        // Generics Tabs
        document.querySelectorAll('#tabsGenerics .drug-ws-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchGenTab(btn.getAttribute('data-tab'), true);
            });
        });

        // Formularies Tabs
        document.querySelectorAll('#tabsFormularies .drug-ws-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchFormTab(btn.getAttribute('data-tab'), true);
            });
        });

        // Dosage Forms Tabs
        document.querySelectorAll('#tabsDosageForms .drug-ws-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchDosageTab(btn.getAttribute('data-tab'), true);
            });
        });

        // Search Inputs (debounced)
        bindSearchInput('genSearchInput', 'btnGenClearSearch', function (val) {
            if (state.activeGenTab === 'tabClassList') fetchClassesList(val);
            else if (state.activeGenTab === 'tabIndList') fetchIndicationsList(val);
            else fetchGenericsList(val);
        });

        bindSearchInput('brandSearchInput', 'btnBrandClearSearch', function (val) {
            if (state.activeFormTab === 'tabMfgList') fetchManufacturersList(val);
            else fetchBrandsList(val);
        });

        bindSearchInput('formsSearchInput', 'btnFormsClearSearch', function (val) {
            fetchDosageFormsList(val);
        });

        // Accordion Section Headers (Toggles + Edit / Save / Cancel)
        for (var i = 1; i <= 7; i++) {
            (function (secNum) {
                var acc = document.getElementById('accGen' + secNum);
                if (!acc) return;

                var header = acc.querySelector('.drug-acc-header');
                var titleWrap = acc.querySelector('.drug-acc-title-wrap');
                var btnEdit = acc.querySelector('.drug-acc-btn-edit');
                var btnSave = acc.querySelector('.drug-acc-btn-save');
                var btnCancel = acc.querySelector('.drug-acc-btn-cancel');

                if (titleWrap) {
                    titleWrap.addEventListener('click', function () {
                        acc.classList.toggle('collapsed');
                    });
                }

                if (btnEdit) {
                    btnEdit.addEventListener('click', function (e) {
                        e.stopPropagation();
                        setAccordionMode(secNum, 'edit');
                    });
                }

                if (btnCancel) {
                    btnCancel.addEventListener('click', function (e) {
                        e.stopPropagation();
                        if (state.selectedGenericData) {
                            renderGenericMonograph(state.selectedGenericData);
                        }
                        setAccordionMode(secNum, 'read');
                    });
                }

                if (btnSave) {
                    btnSave.addEventListener('click', function (e) {
                        e.stopPropagation();
                        saveGenericSection(secNum);
                    });
                }
            })(i);
        }

        // Classification Inspector Buttons
        var btnToggleEditClass = document.getElementById('btnToggleEditClass');
        var btnSaveClass = document.getElementById('btnSaveClass');
        var btnDeleteClass = document.getElementById('btnDeleteClass');
        if (btnToggleEditClass) {
            btnToggleEditClass.addEventListener('click', function () {
                var isCurrentlyEdit = (document.getElementById('classEditView').style.display === 'block');
                setClassEditMode(!isCurrentlyEdit);
            });
        }
        if (btnSaveClass) {
            btnSaveClass.addEventListener('click', function () {
                if (!state.selectedClassId) return;
                var fields = {
                    class_name: document.getElementById('inpEditClassName').value.trim(),
                    category_name: document.getElementById('inpEditClassCategory').value.trim(),
                    subcategory_name: document.getElementById('inpEditClassSubcategory').value.trim()
                };
                apiFetch('save_class', null, { id: state.selectedClassId, fields: fields }).then(function (upd) {
                    state.selectedClassData = upd;
                    showToast('Classification saved successfully.', 'success');
                    selectClass(upd.class_id, false);
                    fetchClassesList('');
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }
        if (btnDeleteClass) {
            btnDeleteClass.addEventListener('click', function () {
                if (!state.selectedClassId) return;
                if (!confirm('Delete classification "' + (state.selectedClassData ? state.selectedClassData.class_name : '') + '"?')) return;
                apiFetch('delete_class', null, { id: state.selectedClassId }).then(function () {
                    showToast('Classification deleted.', 'success');
                    state.selectedClassId = null;
                    document.getElementById('panelClassInspector').style.display = 'none';
                    document.getElementById('genEmptyState').style.display = 'block';
                    fetchClassesList('');
                    syncUrl(true);
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }

        // Indication Inspector Buttons
        var btnToggleEditInd = document.getElementById('btnToggleEditInd');
        var btnSaveInd = document.getElementById('btnSaveInd');
        var btnDeleteInd = document.getElementById('btnDeleteInd');
        if (btnToggleEditInd) {
            btnToggleEditInd.addEventListener('click', function () {
                var isCurrentlyEdit = (document.getElementById('indEditView').style.display === 'block');
                setIndicationEditMode(!isCurrentlyEdit);
            });
        }
        if (btnSaveInd) {
            btnSaveInd.addEventListener('click', function () {
                if (!state.selectedIndicationId) return;
                var fields = {
                    indication_name: document.getElementById('inpEditIndName').value.trim(),
                    indication_desc: document.getElementById('inpEditIndDesc').value.trim()
                };
                apiFetch('save_indication', null, { id: state.selectedIndicationId, fields: fields }).then(function (upd) {
                    state.selectedIndicationData = upd;
                    showToast('Indication saved successfully.', 'success');
                    selectIndication(upd.indication_id, false);
                    fetchIndicationsList('');
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }
        if (btnDeleteInd) {
            btnDeleteInd.addEventListener('click', function () {
                if (!state.selectedIndicationId) return;
                if (!confirm('Delete indication "' + (state.selectedIndicationData ? state.selectedIndicationData.indication_name : '') + '"?')) return;
                apiFetch('delete_indication', null, { id: state.selectedIndicationId }).then(function () {
                    showToast('Indication deleted.', 'success');
                    state.selectedIndicationId = null;
                    document.getElementById('panelIndInspector').style.display = 'none';
                    document.getElementById('genEmptyState').style.display = 'block';
                    fetchIndicationsList('');
                    syncUrl(true);
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }

        // Brand Inspector Buttons
        var btnToggleEditBrand = document.getElementById('btnToggleEditBrand');
        var btnSaveBrand = document.getElementById('btnSaveBrand');
        var btnDeleteBrand = document.getElementById('btnDeleteBrand');
        if (btnToggleEditBrand) {
            btnToggleEditBrand.addEventListener('click', function () {
                var isCurrentlyEdit = (document.getElementById('brandEditView').style.display === 'block');
                setBrandEditMode(!isCurrentlyEdit);
            });
        }
        if (btnSaveBrand) {
            btnSaveBrand.addEventListener('click', function () {
                if (!state.selectedBrandId) return;
                var fields = {
                    brand_name: document.getElementById('inpEditBrandName').value.trim(),
                    form: document.getElementById('selEditBrandForm').value.trim(),
                    strength: document.getElementById('inpEditBrandStrength').value.trim(),
                    packsize: document.getElementById('inpEditBrandPack').value.trim(),
                    price: document.getElementById('inpEditBrandPrice').value.trim(),
                    generic_id: document.getElementById('inpEditBrandGenId').value.trim(),
                    manufacturer_id: document.getElementById('inpEditBrandMfgId').value.trim()
                };
                apiFetch('save_brand', { country: state.activeCountry }, { id: state.selectedBrandId, fields: fields }).then(function (upd) {
                    state.selectedBrandData = upd;
                    showToast('Brand saved successfully.', 'success');
                    selectBrand(upd.brand_id, false);
                    fetchBrandsList('');
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }
        if (btnDeleteBrand) {
            btnDeleteBrand.addEventListener('click', function () {
                if (!state.selectedBrandId) return;
                if (!confirm('Delete trade name "' + (state.selectedBrandData ? state.selectedBrandData.brand_name : '') + '"?')) return;
                apiFetch('delete_brand', { country: state.activeCountry }, { id: state.selectedBrandId }).then(function () {
                    showToast('Trade name deleted.', 'success');
                    state.selectedBrandId = null;
                    document.getElementById('panelBrandInspector').style.display = 'none';
                    document.getElementById('brandEmptyState').style.display = 'block';
                    fetchBrandsList('');
                    syncUrl(true);
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }

        // Manufacturer Inspector Buttons
        var btnToggleEditMfg = document.getElementById('btnToggleEditMfg');
        var btnSaveMfg = document.getElementById('btnSaveMfg');
        var btnDeleteMfg = document.getElementById('btnDeleteMfg');
        if (btnToggleEditMfg) {
            btnToggleEditMfg.addEventListener('click', function () {
                var isCurrentlyEdit = (document.getElementById('mfgEditView').style.display === 'block');
                setMfgEditMode(!isCurrentlyEdit);
            });
        }
        if (btnSaveMfg) {
            btnSaveMfg.addEventListener('click', function () {
                if (!state.selectedMfgId) return;
                var fields = {
                    manufacturer_name: document.getElementById('inpEditMfgName').value.trim(),
                    manufacturer_name_short: document.getElementById('inpEditMfgShort').value.trim()
                };
                apiFetch('save_manufacturer', { country: state.activeCountry }, { id: state.selectedMfgId, fields: fields }).then(function (upd) {
                    state.selectedMfgData = upd;
                    showToast('Manufacturer saved successfully.', 'success');
                    selectManufacturer(upd.manufacturer_id, false);
                    fetchManufacturersList('');
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }
        if (btnDeleteMfg) {
            btnDeleteMfg.addEventListener('click', function () {
                if (!state.selectedMfgId) return;
                if (!confirm('Delete manufacturer "' + (state.selectedMfgData ? state.selectedMfgData.manufacturer_name : '') + '"?')) return;
                apiFetch('delete_manufacturer', { country: state.activeCountry }, { id: state.selectedMfgId }).then(function () {
                    showToast('Manufacturer deleted.', 'success');
                    state.selectedMfgId = null;
                    document.getElementById('panelMfgInspector').style.display = 'none';
                    document.getElementById('brandEmptyState').style.display = 'block';
                    fetchManufacturersList('');
                    syncUrl(true);
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }

        // Dosage Form Inspector Buttons
        var btnToggleEditDosage = document.getElementById('btnToggleEditDosage');
        var btnSaveDosage = document.getElementById('btnSaveDosage');
        var btnDeleteDosage = document.getElementById('btnDeleteDosage');
        if (btnToggleEditDosage) {
            btnToggleEditDosage.addEventListener('click', function () {
                var isCurrentlyEdit = (document.getElementById('dosageEditView').style.display === 'block');
                setDosageEditMode(!isCurrentlyEdit);
            });
        }
        if (btnSaveDosage) {
            btnSaveDosage.addEventListener('click', function () {
                if (!state.selectedDosageId) return;
                var fields = {
                    form: document.getElementById('inpEditDosageName').value.trim(),
                    prefix_short: document.getElementById('inpEditDosagePrefix').value.trim(),
                    prefix_full: document.getElementById('inpEditDosageFullPrefix').value.trim(),
                    form_order: parseInt(document.getElementById('inpEditDosageOrder').value, 10) || 0,
                    add_strength_brand: parseInt(document.getElementById('selEditDosageAppendRule').value, 10)
                };
                apiFetch('save_dosage_form', null, { id: state.selectedDosageId, fields: fields }).then(function (upd) {
                    state.selectedDosageData = upd;
                    showToast('Dosage form saved successfully.', 'success');
                    selectDosageForm(upd.form_id, false);
                    fetchDosageFormsList('');
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }
        if (btnDeleteDosage) {
            btnDeleteDosage.addEventListener('click', function () {
                if (!state.selectedDosageId) return;
                if (!confirm('Delete dosage form "' + (state.selectedDosageData ? state.selectedDosageData.form : '') + '"?')) return;
                apiFetch('delete_dosage_form', null, { id: state.selectedDosageId }).then(function () {
                    showToast('Dosage form deleted.', 'success');
                    state.selectedDosageId = null;
                    document.getElementById('panelDosageInspector').style.display = 'none';
                    document.getElementById('formsEmptyState').style.display = 'block';
                    fetchDosageFormsList('');
                    syncUrl(true);
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }

        // Creation Forms Submissions
        bindFormSubmit('panelAddGeneric', function () {
            var fields = {
                generic_name: document.getElementById('inpAddGenName').value.trim(),
                us_generic_name: document.getElementById('inpAddGenUsName').value.trim(),
                who_atc_class: document.getElementById('inpAddGenAtc').value.trim(),
                pregnancy_category: document.getElementById('selAddGenPregnancy').value.trim(),
                is_antibiotic: parseInt(document.getElementById('selAddGenAntibiotic').value, 10),
                adult_dose: document.getElementById('inpAddGenAdultDose').value.trim(),
                child_dose: document.getElementById('inpAddGenChildDose').value.trim(),
                contra_indication: document.getElementById('inpAddGenContra').value.trim()
            };
            apiFetch('add_generic', null, { fields: fields }).then(function (newRecord) {
                showToast('Generic created successfully.', 'success');
                switchGenTab('tabGenList', false);
                selectGeneric(newRecord.generic_id, true);
            }).catch(function (err) {
                showToast(err.message, 'error');
            });
        });

        bindFormSubmit('panelAddClass', function () {
            var fields = {
                class_name: document.getElementById('inpAddClassName').value.trim(),
                category_name: document.getElementById('inpAddClassCategory').value.trim(),
                subcategory_name: document.getElementById('inpAddClassSubcategory').value.trim()
            };
            apiFetch('add_class', null, { fields: fields }).then(function (newRecord) {
                showToast('Classification created.', 'success');
                switchGenTab('tabClassList', false);
                selectClass(newRecord.class_id, true);
            }).catch(function (err) {
                showToast(err.message, 'error');
            });
        });

        bindFormSubmit('panelAddIndication', function () {
            var fields = {
                indication_name: document.getElementById('inpAddIndName').value.trim(),
                indication_desc: document.getElementById('inpAddIndDesc').value.trim()
            };
            apiFetch('add_indication', null, { fields: fields }).then(function (newRecord) {
                showToast('Indication created.', 'success');
                switchGenTab('tabIndList', false);
                selectIndication(newRecord.indication_id, true);
            }).catch(function (err) {
                showToast(err.message, 'error');
            });
        });

        bindFormSubmit('panelAddBrand', function () {
            var fields = {
                brand_name: document.getElementById('inpAddBrandName').value.trim(),
                form: document.getElementById('selAddBrandForm').value.trim(),
                strength: document.getElementById('inpAddBrandStrength').value.trim(),
                packsize: document.getElementById('inpAddBrandPack').value.trim(),
                price: document.getElementById('inpAddBrandPrice').value.trim(),
                generic_id: document.getElementById('inpAddBrandGenId').value.trim(),
                manufacturer_id: document.getElementById('inpAddBrandMfgId').value.trim()
            };
            apiFetch('add_brand', { country: state.activeCountry }, { fields: fields }).then(function (newRecord) {
                showToast('Trade brand created.', 'success');
                switchFormTab('tabBrandsList', false);
                selectBrand(newRecord.brand_id, true);
            }).catch(function (err) {
                showToast(err.message, 'error');
            });
        });

        bindFormSubmit('panelAddMfg', function () {
            var fields = {
                manufacturer_name: document.getElementById('inpAddMfgName').value.trim(),
                manufacturer_name_short: document.getElementById('inpAddMfgShort').value.trim()
            };
            apiFetch('add_manufacturer', { country: state.activeCountry }, { fields: fields }).then(function (newRecord) {
                showToast('Manufacturer created.', 'success');
                switchFormTab('tabMfgList', false);
                selectManufacturer(newRecord.manufacturer_id, true);
            }).catch(function (err) {
                showToast(err.message, 'error');
            });
        });

        bindFormSubmit('panelAddDosage', function () {
            var fields = {
                form: document.getElementById('inpAddDosageName').value.trim(),
                prefix_short: document.getElementById('inpAddDosagePrefix').value.trim(),
                prefix_full: document.getElementById('inpAddDosageFullPrefix').value.trim(),
                form_order: parseInt(document.getElementById('inpAddDosageOrder').value, 10) || 0,
                add_strength_brand: parseInt(document.getElementById('selAddDosageAppendRule').value, 10)
            };
            apiFetch('add_dosage_form', null, { fields: fields }).then(function (newRecord) {
                showToast('Dosage form created.', 'success');
                switchDosageTab('tabFormsList', false);
                selectDosageForm(newRecord.form_id, true);
            }).catch(function (err) {
                showToast(err.message, 'error');
            });
        });

        // Autocomplete bindings
        setupAutocomplete('inpSearchLinkClass', 'dropLinkClass', 'list_classes', 'class_name', function (item) {
            if (state.selectedGenericId) {
                apiFetch('link_class_generic', null, { class_id: item.class_id, generic_id: state.selectedGenericId }).then(function (upd) {
                    state.selectedGenericData = upd;
                    renderGenericMonograph(upd);
                    showToast('Linked to ' + item.class_name);
                });
            }
        });

        setupAutocomplete('inpSearchLinkInd', 'dropLinkInd', 'list_indications', 'indication_name', function (item) {
            if (state.selectedGenericId) {
                apiFetch('link_indication_generic', null, { indication_id: item.indication_id, generic_id: state.selectedGenericId }).then(function (upd) {
                    state.selectedGenericData = upd;
                    renderGenericMonograph(upd);
                    showToast('Linked to ' + item.indication_name);
                });
            }
        });

        setupAutocomplete('inpSearchClassLinkGen', 'dropClassLinkGen', 'list_generics', 'generic_name', function (item) {
            if (state.selectedClassId) {
                apiFetch('link_class_generic', null, { class_id: state.selectedClassId, generic_id: item.generic_id }).then(function () {
                    selectClass(state.selectedClassId, false);
                    showToast('Linked ' + item.generic_name);
                });
            }
        });

        setupAutocomplete('inpSearchIndLinkGen', 'dropIndLinkGen', 'list_generics', 'generic_name', function (item) {
            if (state.selectedIndicationId) {
                apiFetch('link_indication_generic', null, { indication_id: state.selectedIndicationId, generic_id: item.generic_id }).then(function () {
                    selectIndication(state.selectedIndicationId, false);
                    showToast('Linked ' + item.generic_name);
                });
            }
        });

        setupAutocomplete('inpSearchAddBrandGen', 'dropAddBrandGen', 'list_generics', 'generic_name', function (item) {
            document.getElementById('inpAddBrandGenId').value = item.generic_id;
            document.getElementById('inpSearchAddBrandGen').value = item.generic_name;
            var tag = document.getElementById('tagAddBrandGen');
            if (tag) tag.innerHTML = '<span class="drug-tag-item">' + escapeHtml(item.generic_name) + ' (ID: ' + item.generic_id + ')</span>';
        });

        setupAutocomplete('inpSearchAddBrandMfg', 'dropAddBrandMfg', 'list_manufacturers', 'manufacturer_name', function (item) {
            document.getElementById('inpAddBrandMfgId').value = item.manufacturer_id;
            document.getElementById('inpSearchAddBrandMfg').value = item.manufacturer_name;
            var tag = document.getElementById('tagAddBrandMfg');
            if (tag) tag.innerHTML = '<span class="drug-tag-item">' + escapeHtml(item.manufacturer_name) + ' (ID: ' + item.manufacturer_id + ')</span>';
        }, function () { return { country: state.activeCountry }; });

        setupAutocomplete('inpSearchEditBrandGen', 'dropEditBrandGen', 'list_generics', 'generic_name', function (item) {
            document.getElementById('inpEditBrandGenId').value = item.generic_id;
            document.getElementById('inpSearchEditBrandGen').value = item.generic_name;
            var tag = document.getElementById('tagEditBrandGen');
            if (tag) tag.innerHTML = '<span class="drug-tag-item">' + escapeHtml(item.generic_name) + ' (ID: ' + item.generic_id + ')</span>';
        });

        setupAutocomplete('inpSearchEditBrandMfg', 'dropEditBrandMfg', 'list_manufacturers', 'manufacturer_name', function (item) {
            document.getElementById('inpEditBrandMfgId').value = item.manufacturer_id;
            document.getElementById('inpSearchEditBrandMfg').value = item.manufacturer_name;
            var tag = document.getElementById('tagEditBrandMfg');
            if (tag) tag.innerHTML = '<span class="drug-tag-item">' + escapeHtml(item.manufacturer_name) + ' (ID: ' + item.manufacturer_id + ')</span>';
        }, function () { return { country: state.activeCountry }; });

        // Export Seeds Menu & Actions
        var btnExportToggle = document.getElementById('btnExportToggle');
        var exportMenu = document.getElementById('exportMenu');
        if (btnExportToggle && exportMenu) {
            btnExportToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                exportMenu.classList.toggle('show');
            });
            document.addEventListener('click', function () {
                exportMenu.classList.remove('show');
            });
        }

        var optCore = document.getElementById('optExportCoreSeed');
        var optCountry = document.getElementById('optExportCountrySeed');
        if (optCore) {
            optCore.addEventListener('click', function () {
                if (exportMenu) exportMenu.classList.remove('show');
                runExport('core');
            });
        }
        if (optCountry) {
            optCountry.addEventListener('click', function () {
                if (exportMenu) exportMenu.classList.remove('show');
                runExport(state.activeCountry.toLowerCase());
            });
        }

        // Export Result Modal Closing
        var btnCloseModal = document.getElementById('btnCloseExportModal');
        var btnOkModal = document.getElementById('btnOkExportModal');
        if (btnCloseModal) btnCloseModal.addEventListener('click', closeExportModal);
        if (btnOkModal) btnOkModal.addEventListener('click', closeExportModal);

        // Add Country Modal
        var modalCountry = document.getElementById('modalAddCountry');
        var btnOpenCountry = document.getElementById('btnOpenAddCountry');
        var btnCloseCountry = document.getElementById('btnCloseAddCountryModal');
        var btnCancelCountry = document.getElementById('btnCancelAddCountry');
        var formAddCountry = document.getElementById('formAddCountry');

        if (btnOpenCountry && modalCountry) {
            btnOpenCountry.addEventListener('click', function () {
                modalCountry.style.display = 'flex';
            });
        }
        if (btnCloseCountry && modalCountry) {
            btnCloseCountry.addEventListener('click', function () {
                modalCountry.style.display = 'none';
            });
        }
        if (btnCancelCountry && modalCountry) {
            btnCancelCountry.addEventListener('click', function () {
                modalCountry.style.display = 'none';
            });
        }
        if (formAddCountry && modalCountry) {
            formAddCountry.addEventListener('submit', function (e) {
                e.preventDefault();
                var code = document.getElementById('inpCountryCode').value.trim().toUpperCase();
                var name = document.getElementById('inpCountryName').value.trim();
                var author = document.getElementById('inpCountryAuthor').value.trim();
                var license = document.getElementById('inpCountryLicense').value.trim();
                var attrib = document.getElementById('inpCountryAttribution').value.trim();

                apiFetch('add_country', null, {
                    country_code: code,
                    country_name: name,
                    author: author,
                    license: license,
                    attribution: attrib
                }).then(function () {
                    showToast('Country pack ' + code + ' created successfully.', 'success');
                    modalCountry.style.display = 'none';
                    openCountryWorkspace(code, true);
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }

        // Manifest Modal
        var modalManifest = document.getElementById('modalManifest');
        var btnOpenManifest = document.getElementById('btnOpenManifest');
        var btnCloseManifest = document.getElementById('btnCloseManifestModal');
        var btnCancelManifest = document.getElementById('btnCancelManifest');
        var formManifest = document.getElementById('formManifest');

        if (btnOpenManifest && modalManifest) {
            btnOpenManifest.addEventListener('click', function () {
                var scope = (state.activeWorkspace === 'formularies' && state.isFormularyWorkspaceOpen) ? state.activeCountry.toLowerCase() : 'core';
                apiFetch('get_manifest', { scope: scope }).then(function (man) {
                    document.getElementById('inpManScope').value = man.scope || scope;
                    document.getElementById('inpManVersion').value = man.version || '1.0.0';
                    document.getElementById('inpManName').value = man.name || '';
                    document.getElementById('inpManAuthor').value = man.author || '';
                    document.getElementById('inpManLicense').value = man.license || '';
                    document.getElementById('inpManContributors').value = Array.isArray(man.contributors) ? man.contributors.join(', ') : (man.contributors || '');
                    document.getElementById('inpManAttribution').value = man.attribution || '';
                    document.getElementById('inpManNotes').value = man.release_notes || '';
                    modalManifest.style.display = 'flex';
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }
        if (btnCloseManifest && modalManifest) {
            btnCloseManifest.addEventListener('click', function () {
                modalManifest.style.display = 'none';
            });
        }
        if (btnCancelManifest && modalManifest) {
            btnCancelManifest.addEventListener('click', function () {
                modalManifest.style.display = 'none';
            });
        }
        if (formManifest && modalManifest) {
            formManifest.addEventListener('submit', function (e) {
                e.preventDefault();
                var scope = document.getElementById('inpManScope').value.trim();
                var contribs = document.getElementById('inpManContributors').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
                var payload = {
                    scope: scope,
                    version: document.getElementById('inpManVersion').value.trim(),
                    name: document.getElementById('inpManName').value.trim(),
                    author: document.getElementById('inpManAuthor').value.trim(),
                    license: document.getElementById('inpManLicense').value.trim(),
                    contributors: contribs,
                    attribution: document.getElementById('inpManAttribution').value.trim(),
                    release_notes: document.getElementById('inpManNotes').value.trim()
                };
                apiFetch('save_manifest', null, payload).then(function () {
                    showToast('Manifest updated successfully.', 'success');
                    modalManifest.style.display = 'none';
                }).catch(function (err) {
                    showToast(err.message, 'error');
                });
            });
        }

        // Popstate Router
        window.addEventListener('popstate', function () {
            restoreFromUrl();
        });
    }

    function runExport(scope) {
        showToast('Compiling deterministic SQL export for scope ' + scope.toUpperCase() + '...', 'info');
        apiFetch('export_seed', null, { scope: scope }).then(function (res) {
            showExportModal(res);
        }).catch(function (err) {
            showToast('Export failed: ' + err.message, 'error');
        });
    }

    function showExportModal(res) {
        var modal = document.getElementById('modalExportResult');
        if (!modal) return;
        var summaryEl = document.getElementById('exportSummaryText');
        var detailsEl = document.getElementById('exportResultDetails');
        if (summaryEl) summaryEl.textContent = 'Compiled deterministic SQL seed and companion JSON manifest for scope ' + (res.scope || 'core').toUpperCase() + '.';
        if (detailsEl) {
            var text = 'SQL File: ' + (res.sql_file || '') + '\n';
            text += 'Manifest: ' + (res.manifest_file || '') + '\n';
            text += 'Tables Compiled: ' + (res.tables ? res.tables.join(', ') : '') + '\n';
            text += 'Total Records: ' + (res.total_records ? res.total_records.toLocaleString() : '0') + '\n';
            text += 'SHA-256 Checksum: ' + (res.checksum || 'N/A');
            detailsEl.textContent = text;
        }
        modal.style.display = 'flex';
    }

    function closeExportModal() {
        var modal = document.getElementById('modalExportResult');
        if (modal) modal.style.display = 'none';
    }

    function bindSearchInput(inputId, clearBtnId, onSearch) {
        var input = document.getElementById(inputId);
        var btnClear = document.getElementById(clearBtnId);
        if (!input) return;

        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var val = input.value;
            if (btnClear) btnClear.style.display = val ? 'flex' : 'none';
            timer = setTimeout(function () {
                onSearch(val.trim());
            }, 250);
        });

        if (btnClear) {
            btnClear.addEventListener('click', function () {
                input.value = '';
                btnClear.style.display = 'none';
                onSearch('');
                input.focus();
            });
        }
    }

    function bindFormSubmit(formId, onSubmit) {
        var form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            onSubmit();
        });
    }

    function setupAutocomplete(inputId, dropId, apiAction, labelKey, onSelect, getExtraParams) {
        var input = document.getElementById(inputId);
        var drop = document.getElementById(dropId);
        if (!input || !drop) return;

        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var val = input.value.trim();
            if (val.length < 2) {
                drop.style.display = 'none';
                return;
            }
            timer = setTimeout(function () {
                var params = { q: val, limit: 15 };
                if (getExtraParams) {
                    var extra = getExtraParams();
                    for (var k in extra) { params[k] = extra[k]; }
                }
                apiFetch(apiAction, params).then(function (res) {
                    var items = res.items || (Array.isArray(res) ? res : []);
                    if (items.length === 0) {
                        drop.innerHTML = '<div class="zrx-dropdown-item" style="color:var(--zrx-text-muted); cursor:default;">No matches</div>';
                        drop.style.display = 'block';
                        return;
                    }
                    var html = '';
                    items.forEach(function (it, idx) {
                        html += '<div class="zrx-dropdown-item" data-idx="' + idx + '">';
                        html += '  <span>' + escapeHtml(it[labelKey] || it.name || '') + '</span>';
                        html += '</div>';
                    });
                    drop.innerHTML = html;
                    drop.style.display = 'block';

                    drop.querySelectorAll('.zrx-dropdown-item').forEach(function (el) {
                        el.addEventListener('click', function () {
                            var idx = parseInt(el.getAttribute('data-idx'), 10);
                            if (items[idx]) {
                                onSelect(items[idx]);
                                input.value = '';
                                drop.style.display = 'none';
                            }
                        });
                    });
                }).catch(function () {
                    drop.style.display = 'none';
                });
            }, 200);
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !drop.contains(e.target)) {
                drop.style.display = 'none';
            }
        });
    }

    // -------------------------------------------------------------------------
    // Boot Initialization
    // -------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        initEventBindings();
        restoreFromUrl();
    });

})();
