/**
 * ZimRx Locale Studio - Client-Side Engine
 * Clinical Translations, Dynamic JSON Catalogs & Side-by-Side Workspace
 */
(function () {
    'use strict';

    // Global SVG Icon Renderer from header map
    window.ZimRxIcon = {
        render: function (name, size, attrs) {
            size = size || 14;
            attrs = attrs || {};
            var iconName = (name || '').toLowerCase().trim();
            var icons = window.ZimRxIconsMap || {};
            var path = icons[iconName] || icons['hash'] || '';
            var stroke = attrs['stroke-width'] || '2';
            var cls = attrs['class'] ? 'zrx-icon ' + attrs['class'] : 'zrx-icon';
            return '<svg class="' + cls + '" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' + stroke + '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
        }
    };

    var App = {
        languages: [],
        allCatalogs: [],
        currentView: 'hub',
        refLang: 'en',
        targetLang: 'bn',
        currentCatalog: 'instructions.json',
        targetManifest: null,
        catalogStatus: { complete: false, verified: false, verified_by: null, updated_at: null },
        refData: {},
        targetData: {},
        flattenedRows: [],
        filter: 'all',
        searchQuery: '',
        isDirty: false,
        cachedChanges: {},

        init: function () {
            this.bindEvents();
            this.loadInitialData();
        },

        bindEvents: function () {
            var self = this;

            // Brand link returns to Hub
            var brand = document.getElementById('brandHomeLink');
            if (brand) {
                brand.addEventListener('click', function () {
                    self.switchView('hub');
                });
            }

            var btnViewHub = document.getElementById('btnViewHub');
            if (btnViewHub) {
                btnViewHub.addEventListener('click', function () {
                    self.switchView('hub');
                });
            }

            var btnWorkspaceBackToHub = document.getElementById('btnWorkspaceBackToHub');
            if (btnWorkspaceBackToHub) {
                btnWorkspaceBackToHub.addEventListener('click', function () {
                    self.switchView('hub');
                });
            }

            // Language switchers in Appbar
            var targetLangSelect = document.getElementById('targetLangSelect');
            if (targetLangSelect) {
                targetLangSelect.addEventListener('change', function (e) {
                    var val = e.target.value;
                    if (val && val !== self.targetLang) {
                        if (self.isDirty && !window.confirm('You have unsaved translations. Discard changes and switch language?')) {
                            targetLangSelect.value = self.targetLang;
                            return;
                        }
                        self.openWorkspace(val, self.currentCatalog);
                    }
                });
            }

            var refLangSelect = document.getElementById('refLangSelect');
            if (refLangSelect) {
                refLangSelect.addEventListener('change', function (e) {
                    var val = e.target.value;
                    if (val) {
                        self.refLang = val;
                        var wsRef = document.getElementById('wsRefLangSelect');
                        if (wsRef) wsRef.value = val;
                        self.loadActiveCatalog();
                    }
                });
            }

            var wsRefLangSelect = document.getElementById('wsRefLangSelect');
            if (wsRefLangSelect) {
                wsRefLangSelect.addEventListener('change', function (e) {
                    var val = e.target.value;
                    if (val) {
                        self.refLang = val;
                        if (refLangSelect) refLangSelect.value = val;
                        self.loadActiveCatalog();
                    }
                });
            }

            // Hub Refresh & Search
            var btnHubRefresh = document.getElementById('btnHubRefresh');
            if (btnHubRefresh) {
                btnHubRefresh.addEventListener('click', function () {
                    self.loadInitialData();
                });
            }

            var hubSearchInput = document.getElementById('hubSearchInput');
            if (hubSearchInput) {
                hubSearchInput.addEventListener('input', function (e) {
                    self.renderHubCards(e.target.value);
                });
            }

            // In-workspace Search & Clear
            var wsCatalogSearchInput = document.getElementById('wsCatalogSearchInput');
            var btnWsClearSearch = document.getElementById('btnWsClearSearch');
            if (wsCatalogSearchInput) {
                wsCatalogSearchInput.addEventListener('input', function (e) {
                    self.searchQuery = e.target.value;
                    if (btnWsClearSearch) {
                        btnWsClearSearch.style.display = self.searchQuery ? 'flex' : 'none';
                    }
                    self.renderTranslationRows();
                });
            }

            if (btnWsClearSearch) {
                btnWsClearSearch.addEventListener('click', function () {
                    if (wsCatalogSearchInput) wsCatalogSearchInput.value = '';
                    self.searchQuery = '';
                    btnWsClearSearch.style.display = 'none';
                    self.renderTranslationRows();
                });
            }

            // Filter Buttons (All / Untranslated / Translated)
            var filterBtns = document.querySelectorAll('.locale-filter-btn');
            filterBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    filterBtns.forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    self.filter = btn.getAttribute('data-filter') || 'all';
                    self.renderTranslationRows();
                });
            });

            // TWO OPTION TOGGLES
            var btnToggleComplete = document.getElementById('btnToggleComplete');
            if (btnToggleComplete) {
                btnToggleComplete.addEventListener('click', function () {
                    self.toggleCompleteStatus();
                });
            }

            var btnToggleVerified = document.getElementById('btnToggleVerified');
            if (btnToggleVerified) {
                btnToggleVerified.addEventListener('click', function () {
                    self.toggleVerifiedStatus();
                });
            }

            var btnEditVerifier = document.getElementById('btnEditVerifier');
            if (btnEditVerifier) {
                btnEditVerifier.addEventListener('click', function () {
                    self.openVerifierModal(true);
                });
            }

            // Bulk Helpers
            var btnCopyAllUntranslated = document.getElementById('btnCopyAllUntranslated');
            if (btnCopyAllUntranslated) {
                btnCopyAllUntranslated.addEventListener('click', function () {
                    self.copyAllUntranslatedFromRef();
                });
            }

            // Save Buttons
            var btnSaveCatalog = document.getElementById('btnSaveCatalog');
            if (btnSaveCatalog) {
                btnSaveCatalog.addEventListener('click', function () {
                    self.saveActiveCatalog();
                });
            }

            var btnWsSaveCatalog = document.getElementById('btnWsSaveCatalog');
            if (btnWsSaveCatalog) {
                btnWsSaveCatalog.addEventListener('click', function () {
                    self.saveActiveCatalog();
                });
            }

            // Modals Opening
            var btnOpenNewLangModal = document.getElementById('btnOpenNewLangModal');
            var btnHubNewLangHero = document.getElementById('btnHubNewLangHero');
            if (btnOpenNewLangModal) btnOpenNewLangModal.addEventListener('click', function () { self.openModal('modalNewLanguage'); });
            if (btnHubNewLangHero) btnHubNewLangHero.addEventListener('click', function () { self.openModal('modalNewLanguage'); });

            var btnOpenNewCatalogModal = document.getElementById('btnOpenNewCatalogModal');
            var btnHubQuickAddCatalog = document.getElementById('btnHubQuickAddCatalog');
            var btnWsAddNewCatalog = document.getElementById('btnWsAddNewCatalog');
            if (btnOpenNewCatalogModal) btnOpenNewCatalogModal.addEventListener('click', function () { self.openModal('modalNewCatalog'); });
            if (btnHubQuickAddCatalog) btnHubQuickAddCatalog.addEventListener('click', function () { self.openModal('modalNewCatalog'); });
            if (btnWsAddNewCatalog) btnWsAddNewCatalog.addEventListener('click', function () { self.openModal('modalNewCatalog'); });

            var btnOpenManifestModal = document.getElementById('btnOpenManifestModal');
            if (btnOpenManifestModal) {
                btnOpenManifestModal.addEventListener('click', function () {
                    self.openManifestModal();
                });
            }

            var btnOpenValidateModal = document.getElementById('btnOpenValidateModal');
            if (btnOpenValidateModal) {
                btnOpenValidateModal.addEventListener('click', function () {
                    self.runValidationReport();
                });
            }

            // Modals Closing & Forms
            this.bindModalForms();

            // Keyboard Shortcuts
            window.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    if (self.currentView === 'workspace') {
                        self.saveActiveCatalog();
                    }
                } else if (e.key === 'Escape') {
                    self.closeAllModals();
                }
            });

            // Warn on closing or navigating away with unsaved translation changes
            window.addEventListener('beforeunload', function (e) {
                if (self.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Browser hash navigation
            window.addEventListener('hashchange', function () {
                self.handleHashNavigation();
            });
        },

        bindModalForms: function () {
            var self = this;

            // Close buttons
            var closeIds = [
                'btnCloseNewLangModal', 'btnCancelNewLang',
                'btnCloseNewCatalogModal', 'btnCancelNewCatalog',
                'btnCloseManifestModal', 'btnCancelManifest',
                'btnCloseValidationModal', 'btnCloseValidationReport',
                'btnCloseVerifierPrompt', 'btnCancelVerifierPrompt'
            ];
            closeIds.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.addEventListener('click', function () {
                        self.closeAllModals();
                    });
                }
            });

            // New Language Form
            var formNewLanguage = document.getElementById('formNewLanguage');
            if (formNewLanguage) {
                formNewLanguage.addEventListener('submit', function (e) {
                    e.preventDefault();
                    self.submitNewLanguage();
                });
            }

            // New Catalog Form
            var formNewCatalog = document.getElementById('formNewCatalog');
            if (formNewCatalog) {
                formNewCatalog.addEventListener('submit', function (e) {
                    e.preventDefault();
                    self.submitNewCatalog();
                });
            }

            // Manifest Form
            var formManifest = document.getElementById('formManifest');
            if (formManifest) {
                formManifest.addEventListener('submit', function (e) {
                    e.preventDefault();
                    self.submitManifest();
                });
            }

            // Verifier Form
            var formVerifierPrompt = document.getElementById('formVerifierPrompt');
            if (formVerifierPrompt) {
                formVerifierPrompt.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var nameInp = document.getElementById('inpVerifierName');
                    var name = nameInp ? nameInp.value.trim() : '';
                    if (name) {
                        self.confirmVerification(name);
                    }
                });
            }
        },

        openModal: function (modalId) {
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                var firstInput = modal.querySelector('input:not([readonly]), select');
                if (firstInput) firstInput.focus();
            }
        },

        closeAllModals: function () {
            var modals = document.querySelectorAll('.locale-modal-overlay');
            modals.forEach(function (m) {
                m.style.display = 'none';
            });
        },

        showToast: function (message, type) {
            type = type || 'info';
            var container = document.getElementById('toastContainer');
            if (!container) return;

            var toast = document.createElement('div');
            toast.className = 'locale-toast ' + type;
            var icon = type === 'success' ? 'check' : (type === 'error' ? 'alert-circle' : 'info-circle');
            toast.innerHTML = window.ZimRxIcon.render(icon, 14) + '<span>' + escapeHtml(message) + '</span>';

            container.appendChild(toast);
            setTimeout(function () {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'all 0.2s ease';
                setTimeout(function () {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 250);
            }, 3000);
        },

        // 1. Initial Data Loading
        loadInitialData: function () {
            var self = this;
            fetch('?action=list_languages')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        self.showToast(data.error || 'Failed to list languages.', 'error');
                        return;
                    }
                    self.languages = data.languages || [];
                    self.allCatalogs = data.all_catalogs || [];

                    self.populateLanguageSelects();
                    self.renderHubHeroStats();
                    self.renderDiscoveredCatalogChips();
                    self.renderHubCards();

                    // Check hash for direct deep-link
                    self.handleHashNavigation();
                })
                .catch(function (err) {
                    self.showToast('Network error loading locales: ' + err.message, 'error');
                });
        },

        handleHashNavigation: function () {
            var hash = window.location.hash.replace(/^#/, '');
            if (hash.indexOf('workspace/') === 0) {
                var parts = hash.split('/');
                var target = parts[1] || 'bn';
                var catalog = parts[2] || this.allCatalogs[0] || 'instructions.json';
                this.openWorkspace(target, catalog);
            } else {
                this.switchView('hub');
            }
        },

        populateLanguageSelects: function () {
            var targetSelect = document.getElementById('targetLangSelect');
            var refSelect = document.getElementById('refLangSelect');
            var wsRefSelect = document.getElementById('wsRefLangSelect');
            var cloneSelect = document.getElementById('inpNewLangCloneFrom');

            var optionsHtml = '';
            this.languages.forEach(function (l) {
                optionsHtml += '<option value="' + l.code + '">' + l.name + ' (' + l.code + ')' + (l.native_name !== l.name ? ' - ' + l.native_name : '') + '</option>';
            });

            if (targetSelect) targetSelect.innerHTML = optionsHtml;
            if (refSelect) refSelect.innerHTML = optionsHtml;
            if (wsRefSelect) wsRefSelect.innerHTML = optionsHtml;
            if (cloneSelect) cloneSelect.innerHTML = optionsHtml;

            if (refSelect) refSelect.value = this.refLang;
            if (wsRefSelect) wsRefSelect.value = this.refLang;
        },

        renderHubHeroStats: function () {
            var totalLangs = this.languages.length;
            var totalCats = this.allCatalogs.length;
            var totalStrings = 0;
            var totalVerified = 0;

            this.languages.forEach(function (l) {
                totalStrings += (l.total_keys || 0);
                totalVerified += (l.verified_catalogs || 0);
            });

            var elLangs = document.getElementById('hubTotalLangs');
            var elCats = document.getElementById('hubTotalCatalogs');
            var elStrings = document.getElementById('hubTotalStrings');
            var elVerified = document.getElementById('hubTotalVerified');

            if (elLangs) elLangs.textContent = totalLangs;
            if (elCats) elCats.textContent = totalCats;
            if (elStrings) elStrings.textContent = totalStrings.toLocaleString();
            if (elVerified) elVerified.textContent = totalVerified;
        },

        renderDiscoveredCatalogChips: function () {
            var container = document.getElementById('hubDiscoveredCatalogsChips');
            if (!container) return;

            var html = '';
            this.allCatalogs.forEach(function (cat) {
                html += '<span class="locale-cat-chip">' + window.ZimRxIcon.render('file-text', 11) + ' ' + cat + '</span>';
            });
            container.innerHTML = html;
        },

        renderHubCards: function (query) {
            var grid = document.getElementById('hubCardsGrid');
            if (!grid) return;

            var q = (query || '').toLowerCase().trim();
            var filtered = this.languages.filter(function (l) {
                if (!q) return true;
                return l.name.toLowerCase().indexOf(q) !== -1 ||
                    l.native_name.toLowerCase().indexOf(q) !== -1 ||
                    l.code.toLowerCase().indexOf(q) !== -1;
            });

            if (filtered.length === 0) {
                grid.innerHTML = '<div class="locale-empty-state locale-col-full">No matching language packs found.</div>';
                return;
            }

            var self = this;
            var html = '';
            filtered.forEach(function (lang) {
                var pct = lang.completion_pct || 0;
                var isRtl = (lang.direction || 'ltr') === 'rtl';
                var contribs = Array.isArray(lang.contributors) ? lang.contributors.join(', ') : (lang.contributors || lang.author || 'Alif Farzan Zim');

                html += '<div class="country-card">';
                html += '  <div class="country-card-header">';
                html += '    <div class="country-card-identity">';
                html += '      <span class="country-card-title">' + escapeHtml(lang.name) + '</span>';
                html += '      <span class="country-card-native">' + escapeHtml(lang.native_name) + '</span>';
                html += '    </div>';
                html += '    <div class="country-card-badges">';
                html += '      <span class="scope-pill scope-national">' + escapeHtml(lang.direction.toUpperCase()) + '</span>';
                html += '      <span class="country-card-ver">v' + escapeHtml(lang.version || '1.0.0') + '</span>';
                html += '    </div>';
                html += '  </div>';

                html += '  <div class="country-card-body">';
                html += '    <div style="display:flex; align-items:baseline; gap:8px;">';
                html += '      <span style="font-size:22px; font-weight:800; color:var(--zrx-text-dark);">' + (lang.total_keys || 0).toLocaleString() + '</span>';
                html += '      <span style="font-size:12px; font-weight:600; color:var(--zrx-text-muted);">Clinical Strings</span>';
                html += '      <span style="font-size:11px; color:#16a34a; font-weight:600; margin-left:auto;">' + pct + '% Translated</span>';
                html += '    </div>';

                // Miniature catalog badges
                html += '    <div class="country-stat-badge-row">';
                self.allCatalogs.forEach(function (cat) {
                    var status = (lang.catalogs_status && lang.catalogs_status[cat]) ? lang.catalogs_status[cat] : {};
                    var isComplete = !!status.complete;
                    var isVerified = !!status.verified;
                    var shortName = cat.replace('.json', '');

                    html += '      <span class="c-stat-pill ' + (isVerified ? 'verified' : '') + '">';
                    html += '        <strong>' + escapeHtml(shortName) + ':</strong> ' + (isVerified ? 'Verified' : (isComplete ? 'Complete' : 'Draft'));
                    html += '      </span>';
                });
                html += '    </div>';

                // Metadata list
                html += '    <div class="country-card-meta">';
                html += '      <div><strong>Author:</strong> ' + escapeHtml(lang.author || 'Alif Farzan Zim') + '</div>';
                html += '      <div><strong>Contributors:</strong> ' + escapeHtml(contribs) + '</div>';
                html += '      <div style="display:flex; justify-content:space-between; margin-top:2px;">';
                html += '        <span><strong>Released:</strong> ' + escapeHtml(lang.release_date || '') + '</span>';
                html += '        <span><strong>Updated:</strong> ' + escapeHtml(lang.last_updated || '') + '</span>';
                html += '      </div>';
                html += '    </div>';
                html += '  </div>';

                html += '  <div class="country-card-footer">';
                html += '    <button type="button" class="geo-btn geo-btn-primary btn-open-country" data-action="open-studio" data-code="' + escapeHtml(lang.code) + '" style="width:100%; justify-content:center;">';
                html += '      <span>Open Studio</span> ' + window.ZimRxIcon.render('arrow-right', 12);
                html += '    </button>';
                html += '  </div>';
                html += '</div>';
            });

            grid.innerHTML = html;

            // Attach studio button listeners
            var openBtns = grid.querySelectorAll('[data-action="open-studio"]');
            openBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var code = btn.getAttribute('data-code');
                    self.openWorkspace(code, self.allCatalogs[0] || 'instructions.json');
                });
            });
        },

        // 2. View Switching & Workspace Open
        switchView: function (view) {
            if (view === 'hub' && this.isDirty) {
                if (!window.confirm('You have unsaved translation modifications. Discard changes and return to directory?')) {
                    return;
                }
            }

            this.currentView = view;
            var viewHub = document.getElementById('viewHub');
            var viewWorkspace = document.getElementById('viewWorkspace');
            var targetLangWrap = document.getElementById('targetLangWrap');
            var refLangWrap = document.getElementById('refLangWrap');
            var btnOpenManifestModal = document.getElementById('btnOpenManifestModal');
            var btnSaveCatalog = document.getElementById('btnSaveCatalog');

            if (view === 'workspace') {
                if (viewHub) viewHub.style.display = 'none';
                if (viewWorkspace) viewWorkspace.style.display = 'flex';
                if (targetLangWrap) targetLangWrap.style.display = 'flex';
                if (refLangWrap) refLangWrap.style.display = 'flex';
                if (btnOpenManifestModal) btnOpenManifestModal.disabled = false;
                if (btnSaveCatalog) btnSaveCatalog.disabled = false;
            } else {
                if (viewHub) viewHub.style.display = 'block';
                if (viewWorkspace) viewWorkspace.style.display = 'none';
                if (targetLangWrap) targetLangWrap.style.display = 'none';
                if (refLangWrap) refLangWrap.style.display = 'none';
                if (btnOpenManifestModal) btnOpenManifestModal.disabled = true;
                if (btnSaveCatalog) btnSaveCatalog.disabled = true;
                window.location.hash = '';
            }
        },

        openWorkspace: function (targetLang, catalogFile) {
            if (this.currentView === 'workspace' && this.isDirty) {
                if (!window.confirm('You have unsaved translation modifications. Discard changes and switch?')) {
                    return;
                }
            }

            this.targetLang = targetLang;
            this.currentCatalog = catalogFile || this.allCatalogs[0] || 'instructions.json';
            this.switchView('workspace');

            var targetSelect = document.getElementById('targetLangSelect');
            if (targetSelect) targetSelect.value = targetLang;

            window.location.hash = 'workspace/' + targetLang + '/' + this.currentCatalog;
            this.loadActiveCatalog();
        },

        // 3. Dynamic Catalog Loading & Tab Generation
        loadActiveCatalog: function () {
            var self = this;
            var container = document.getElementById('translationRowsContainer');
            if (container) {
                container.innerHTML = '<div class="locale-empty-state">Loading ' + escapeHtml(this.currentCatalog) + ' for ' + escapeHtml(this.targetLang) + '...</div>';
            }

            var url = '?action=load_catalog&target=' + encodeURIComponent(this.targetLang) +
                '&ref=' + encodeURIComponent(this.refLang) +
                '&catalog=' + encodeURIComponent(this.currentCatalog);

            fetch(url)
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        self.showToast(data.error || 'Failed to load catalog.', 'error');
                        return;
                    }

                    self.refData = data.ref_data || {};
                    self.targetData = data.target_data || {};
                    self.catalogStatus = data.catalog_status || { complete: false, verified: false };
                    self.targetManifest = data.target_manifest || {};
                    self.isDirty = false;

                    self.updateWorkspaceMetaHeaders();
                    self.renderCatalogTabs();
                    self.updateCatalogControlStrip();
                    self.flattenDataForTranslation();
                    self.renderTranslationRows();
                })
                .catch(function (err) {
                    self.showToast('Network error loading catalog: ' + err.message, 'error');
                });
        },

        updateWorkspaceMetaHeaders: function () {
            var wsTargetCode = document.getElementById('wsTargetCode');
            var wsTargetName = document.getElementById('wsTargetName');
            var wsTargetNative = document.getElementById('wsTargetNative');
            var wsTargetDirection = document.getElementById('wsTargetDirection');
            var wsAuthor = document.getElementById('wsAuthor');
            var wsLastUpdated = document.getElementById('wsLastUpdated');
            var headerTargetLangTitle = document.getElementById('headerTargetLangTitle');
            var headerTargetDir = document.getElementById('headerTargetDir');
            var headerRefLangTitle = document.getElementById('headerRefLangTitle');

            var meta = this.targetManifest || {};
            var name = meta.name || this.targetLang.toUpperCase();
            var nativeName = meta.native_name || name;
            var dir = (meta.direction || 'ltr').toUpperCase();

            if (wsTargetCode) wsTargetCode.textContent = this.targetLang.toUpperCase();
            if (wsTargetName) wsTargetName.textContent = name;
            if (wsTargetNative) wsTargetNative.textContent = nativeName;
            if (wsTargetDirection) {
                wsTargetDirection.textContent = dir;
                wsTargetDirection.className = 'locale-dir-badge ' + (dir === 'RTL' ? 'rtl' : '');
            }
            if (wsAuthor) wsAuthor.textContent = meta.author || 'ZimRx Author';
            if (wsLastUpdated) wsLastUpdated.textContent = meta.last_updated || '-';

            if (headerTargetLangTitle) headerTargetLangTitle.textContent = name + ' (' + this.targetLang + ')';
            if (headerTargetDir) headerTargetDir.textContent = dir;

            // Ref header
            var refName = this.refLang.toUpperCase();
            var refFound = this.languages.find(function (l) { return l.code === self.refLang; });
            if (refFound) refName = refFound.name;
            if (headerRefLangTitle) headerRefLangTitle.textContent = refName + ' (' + this.refLang + ')';

            this.updateLiveSaveBadge();
        },

        updateLiveSaveBadge: function () {
            var badge = document.getElementById('wsSaveStatusBadge');
            if (!badge) return;
            if (this.isDirty) {
                badge.textContent = 'Unsaved changes';
                badge.className = 'locale-status-badge-live dirty';
            } else {
                badge.textContent = 'All saved';
                badge.className = 'locale-status-badge-live';
            }
        },

        renderCatalogTabs: function () {
            var container = document.getElementById('catalogTabsContainer');
            if (!container) return;

            var self = this;
            var html = '';
            var statusMap = (this.targetManifest && this.targetManifest.catalogs_status) ? this.targetManifest.catalogs_status : {};

            this.allCatalogs.forEach(function (cat) {
                var isActive = cat === self.currentCatalog;
                var st = statusMap[cat] || {};
                var isComplete = !!st.complete;
                var isVerified = !!st.verified;

                html += '<button type="button" class="locale-tab ' + (isActive ? 'active' : '') + '" data-catalog="' + cat + '">';
                html += '  <span class="locale-tab-name">' + cat + '</span>';
                html += '  <span class="locale-tab-icons">';
                if (isComplete) {
                    html += '    <span class="locale-tab-badge-complete" title="Marked as Complete"></span>';
                }
                if (isVerified) {
                    html += '    <span class="locale-tab-badge-verified" title="Clinically Verified">' + window.ZimRxIcon.render('shield', 10) + '</span>';
                }
                html += '  </span>';
                html += '</button>';
            });

            container.innerHTML = html;

            // Attach click handler
            var tabs = container.querySelectorAll('.locale-tab');
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var cat = tab.getAttribute('data-catalog');
                    if (cat !== self.currentCatalog) {
                        if (self.isDirty && !window.confirm('You have unsaved translation modifications in this catalog. Discard changes and switch?')) {
                            return;
                        }
                        self.currentCatalog = cat;
                        window.location.hash = 'workspace/' + self.targetLang + '/' + cat;
                        self.loadActiveCatalog();
                    }
                });
            });
        },

        updateCatalogControlStrip: function () {
            var self = this;

            // Update Option 1: Mark as Complete toggle
            var btnToggleComplete = document.getElementById('btnToggleComplete');
            var lblToggleComplete = document.getElementById('lblToggleComplete');
            var isComplete = !!(this.catalogStatus && this.catalogStatus.complete);

            if (btnToggleComplete && lblToggleComplete) {
                if (isComplete) {
                    btnToggleComplete.classList.add('is-active');
                    lblToggleComplete.textContent = 'Completed';
                } else {
                    btnToggleComplete.classList.remove('is-active');
                    lblToggleComplete.textContent = 'Mark as Complete';
                }
            }

            // Update Option 2: Mark as Verified toggle
            var btnToggleVerified = document.getElementById('btnToggleVerified');
            var lblToggleVerified = document.getElementById('lblToggleVerified');
            var isVerified = !!(this.catalogStatus && this.catalogStatus.verified);

            if (btnToggleVerified && lblToggleVerified) {
                if (isVerified) {
                    btnToggleVerified.classList.add('is-active');
                    lblToggleVerified.textContent = 'Verified';
                } else {
                    btnToggleVerified.classList.remove('is-active');
                    lblToggleVerified.textContent = 'Mark as Verified';
                }
            }

            // Update Verified banner
            var wsVerifiedNotice = document.getElementById('wsVerifiedNotice');
            var wsVerifiedAuthorName = document.getElementById('wsVerifiedAuthorName');
            var wsVerifiedDate = document.getElementById('wsVerifiedDate');

            if (wsVerifiedNotice) {
                if (isVerified) {
                    wsVerifiedNotice.style.display = 'flex';
                    if (wsVerifiedAuthorName) wsVerifiedAuthorName.textContent = this.catalogStatus.verified_by || this.targetManifest.author || 'Lead Clinician';
                    if (wsVerifiedDate) wsVerifiedDate.textContent = this.catalogStatus.updated_at || dateStr();
                } else {
                    wsVerifiedNotice.style.display = 'none';
                }
            }
        },

        // 4. Data Flattening & Side-by-Side Synchronization
        flattenDataForTranslation: function () {
            this.flattenedRows = [];
            var self = this;

            // Collect all unique keys from reference and target data
            // Supports: flat dictionaries, dictionaries of {text, alias}, and nested sections (e.g. advices categories/items)
            var flattenRecursive = function (refObj, targetObj, prefix) {
                refObj = refObj || {};
                targetObj = targetObj || {};

                var allKeys = [];
                Object.keys(refObj).forEach(function (k) { if (allKeys.indexOf(k) === -1) allKeys.push(k); });
                Object.keys(targetObj).forEach(function (k) { if (allKeys.indexOf(k) === -1) allKeys.push(k); });

                allKeys.forEach(function (key) {
                    var fullPath = prefix ? prefix + '.' + key : key;
                    var refVal = refObj[key];
                    var targetVal = targetObj[key];

                    // Check if item is a structured object with 'text'
                    var isRefStructured = refVal && typeof refVal === 'object' && typeof refVal.text !== 'undefined';
                    var isTargetStructured = targetVal && typeof targetVal === 'object' && typeof targetVal.text !== 'undefined';

                    if (isRefStructured || isTargetStructured) {
                        var rText = (refVal && typeof refVal.text === 'string') ? refVal.text : '';
                        var rAlias = (refVal && typeof refVal.alias === 'string') ? refVal.alias : null;
                        var tText = (targetVal && typeof targetVal.text === 'string') ? targetVal.text : '';
                        var tAlias = (targetVal && typeof targetVal.alias === 'string') ? targetVal.alias : (rAlias !== null ? '' : null);

                        self.flattenedRows.push({
                            path: fullPath,
                            keyLabel: fullPath,
                            isStructured: true,
                            refText: rText,
                            refAlias: rAlias,
                            targetText: tText,
                            targetAlias: tAlias,
                            originalTargetText: tText,
                            originalTargetAlias: tAlias
                        });
                    } else if (typeof refVal === 'string' || typeof targetVal === 'string') {
                        // Simple flat key-value string
                        var rStr = typeof refVal === 'string' ? refVal : '';
                        var tStr = typeof targetVal === 'string' ? targetVal : '';

                        self.flattenedRows.push({
                            path: fullPath,
                            keyLabel: fullPath,
                            isStructured: false,
                            refText: rStr,
                            refAlias: null,
                            targetText: tStr,
                            targetAlias: null,
                            originalTargetText: tStr,
                            originalTargetAlias: null
                        });
                    } else if (typeof refVal === 'object' || typeof targetVal === 'object') {
                        // Recurse into nested object
                        flattenRecursive(refVal || {}, targetVal || {}, fullPath);
                    }
                });
            };

            flattenRecursive(this.refData, this.targetData, '');
            this.updateStatsCounters();
        },

        updateStatsCounters: function () {
            var total = this.flattenedRows.length;
            var translated = 0;
            this.flattenedRows.forEach(function (row) {
                if (row.targetText && row.targetText.trim() !== '') {
                    translated++;
                }
            });
            var pending = total - translated;
            var pct = total > 0 ? Math.round((translated / total) * 100) : 100;

            var elTotal = document.getElementById('catTotalKeys');
            var elTrans = document.getElementById('catTranslatedKeys');
            var elPend = document.getElementById('catUntranslatedKeys');
            var elPct = document.getElementById('catCompletionPct');

            if (elTotal) elTotal.textContent = total;
            if (elTrans) elTrans.textContent = translated;
            if (elPend) elPend.textContent = pending;
            if (elPct) elPct.textContent = pct + '%';
        },

        // 5. Render Translation Rows
        renderTranslationRows: function () {
            var container = document.getElementById('translationRowsContainer');
            if (!container) return;

            var self = this;
            var q = (this.searchQuery || '').toLowerCase().trim();
            var filter = this.filter;
            var isTargetRtl = (this.targetManifest && this.targetManifest.direction === 'rtl');

            var filtered = this.flattenedRows.filter(function (row) {
                var isTranslated = !!(row.targetText && row.targetText.trim() !== '');

                // Filter check
                if (filter === 'untranslated' && isTranslated) return false;
                if (filter === 'translated' && !isTranslated) return false;

                // Search check
                if (q) {
                    var matchPath = row.path.toLowerCase().indexOf(q) !== -1;
                    var matchRef = row.refText.toLowerCase().indexOf(q) !== -1;
                    var matchTarget = row.targetText.toLowerCase().indexOf(q) !== -1;
                    var matchRefAlias = row.refAlias && row.refAlias.toLowerCase().indexOf(q) !== -1;
                    var matchTargetAlias = row.targetAlias && row.targetAlias.toLowerCase().indexOf(q) !== -1;
                    if (!matchPath && !matchRef && !matchTarget && !matchRefAlias && !matchTargetAlias) {
                        return false;
                    }
                }

                return true;
            });

            if (filtered.length === 0) {
                container.innerHTML = '<div class="locale-empty-state">No matching translation strings found for filter.</div>';
                return;
            }

            var html = '';
            filtered.forEach(function (row, idx) {
                var isTranslated = !!(row.targetText && row.targetText.trim() !== '');
                var isModified = (row.targetText !== row.originalTargetText) || (row.targetAlias !== row.originalTargetAlias);

                var statusClass = 'untranslated';
                var statusLabel = 'Untranslated';
                if (isModified) {
                    statusClass = 'modified';
                    statusLabel = 'Modified';
                } else if (isTranslated) {
                    statusClass = 'translated';
                    statusLabel = 'Translated';
                }

                html += '<div class="locale-translation-row" data-path="' + escapeHtml(row.path) + '">';
                html += '  <div class="locale-row-header-strip">';
                html += '    <span class="locale-row-key-badge">' + escapeHtml(row.keyLabel) + '</span>';
                html += '    <div class="locale-row-meta-right">';
                html += '      <span class="locale-row-status-pill ' + statusClass + '">' + statusLabel + '</span>';
                html += '    </div>';
                html += '  </div>';

                html += '  <div class="locale-row-columns">';
                // Left Column: Read-Only Reference
                html += '    <div class="locale-row-ref-col">';
                html += '      <div>';
                html += '        <div class="locale-ref-text-box">' + escapeHtml(row.refText || '(Empty)') + '</div>';
                if (row.refAlias) {
                    html += '        <div class="locale-ref-alias-box">';
                    html += '          <span>Aliases:</span>';
                    html += '          <span class="locale-ref-alias-pill">' + escapeHtml(row.refAlias) + '</span>';
                    html += '        </div>';
                }
                html += '      </div>';
                html += '      <div class="locale-row-ref-actions">';
                html += '        <button type="button" class="locale-copy-ref-btn" data-action="copy-ref" data-path="' + escapeHtml(row.path) + '" title="Copy reference text to translation field">';
                html += '          ' + window.ZimRxIcon.render('download', 11) + ' <span>Copy to Target</span>';
                html += '        </button>';
                html += '      </div>';
                html += '    </div>';

                // Right Column: Editable Target
                html += '    <div class="locale-row-target-col">';
                html += '      <textarea class="locale-target-textarea ' + (isTargetRtl ? 'rtl' : '') + '" data-field="text" data-path="' + escapeHtml(row.path) + '" placeholder="Enter translation...">' + escapeHtml(row.targetText) + '</textarea>';
                if (row.isStructured && row.refAlias !== null) {
                    html += '      <div class="locale-target-alias-wrap">';
                    html += '        <span class="locale-target-alias-label">Search Aliases:</span>';
                    html += '        <input type="text" class="locale-target-alias-input ' + (isTargetRtl ? 'rtl' : '') + '" data-field="alias" data-path="' + escapeHtml(row.path) + '" value="' + escapeHtml(row.targetAlias || '') + '" placeholder="e.g. localized keywords for instant search">';
                    html += '      </div>';
                }
                html += '    </div>';

                html += '  </div>';
                html += '</div>';
            });

            container.innerHTML = html;

            // Attach input event listeners
            var textareas = container.querySelectorAll('.locale-target-textarea');
            textareas.forEach(function (ta) {
                // Auto resize height
                self.autoResizeTextarea(ta);
                ta.addEventListener('input', function (e) {
                    self.autoResizeTextarea(ta);
                    var path = ta.getAttribute('data-path');
                    self.updateRowValue(path, 'text', e.target.value);
                });
            });

            var aliasInputs = container.querySelectorAll('.locale-target-alias-input');
            aliasInputs.forEach(function (inp) {
                inp.addEventListener('input', function (e) {
                    var path = inp.getAttribute('data-path');
                    self.updateRowValue(path, 'alias', e.target.value);
                });
            });

            var copyBtns = container.querySelectorAll('[data-action="copy-ref"]');
            copyBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var path = btn.getAttribute('data-path');
                    self.copyReferenceToTarget(path);
                });
            });
        },

        autoResizeTextarea: function (el) {
            el.style.height = 'auto';
            el.style.height = Math.max(48, el.scrollHeight) + 'px';
        },

        updateRowValue: function (path, field, val) {
            var row = this.flattenedRows.find(function (r) { return r.path === path; });
            if (!row) return;

            if (field === 'text') {
                row.targetText = val;
            } else if (field === 'alias') {
                row.targetAlias = val;
            }

            this.isDirty = true;
            this.updateLiveSaveBadge();
            this.updateStatsCounters();

            // Update row status pill
            var card = document.querySelector('.locale-translation-row[data-path="' + path + '"]');
            if (card) {
                var pill = card.querySelector('.locale-row-status-pill');
                if (pill) {
                    pill.className = 'locale-row-status-pill modified';
                    pill.textContent = 'Modified';
                }
            }
        },

        copyReferenceToTarget: function (path) {
            var row = this.flattenedRows.find(function (r) { return r.path === path; });
            if (!row) return;

            row.targetText = row.refText;
            if (row.isStructured && row.refAlias) {
                row.targetAlias = row.refAlias;
            }

            this.isDirty = true;
            this.updateLiveSaveBadge();
            this.updateStatsCounters();

            // Update in DOM
            var card = document.querySelector('.locale-translation-row[data-path="' + path + '"]');
            if (card) {
                var ta = card.querySelector('.locale-target-textarea');
                if (ta) {
                    ta.value = row.targetText;
                    this.autoResizeTextarea(ta);
                }
                var aliasInp = card.querySelector('.locale-target-alias-input');
                if (aliasInp && row.targetAlias) {
                    aliasInp.value = row.targetAlias;
                }
                var pill = card.querySelector('.locale-row-status-pill');
                if (pill) {
                    pill.className = 'locale-row-status-pill modified';
                    pill.textContent = 'Modified';
                }
            }
            this.showToast('Copied from reference for: ' + path, 'info');
        },

        copyAllUntranslatedFromRef: function () {
            var self = this;
            var copiedCount = 0;
            this.flattenedRows.forEach(function (row) {
                if (!row.targetText || row.targetText.trim() === '') {
                    row.targetText = row.refText;
                    if (row.isStructured && row.refAlias) {
                        row.targetAlias = row.refAlias;
                    }
                    copiedCount++;
                }
            });

            if (copiedCount > 0) {
                this.isDirty = true;
                this.updateLiveSaveBadge();
                this.updateStatsCounters();
                this.renderTranslationRows();
                this.showToast('Bootstrapped ' + copiedCount + ' strings from reference.', 'success');
            } else {
                this.showToast('No pending untranslated strings to copy.', 'info');
            }
        },

        // 6. TWO OPTIONS: Mark as Complete & Mark as Verified Handlers
        toggleCompleteStatus: function () {
            var current = !!(this.catalogStatus && this.catalogStatus.complete);
            var next = !current;
            this.catalogStatus.complete = next;

            this.updateCatalogControlStrip();
            this.renderCatalogTabs();

            // Immediate API status update
            this.syncStatusToApi(next, null, null);
            this.showToast(next ? 'Catalog marked as Complete.' : 'Catalog marked as Draft.', 'success');
        },

        toggleVerifiedStatus: function () {
            var current = !!(this.catalogStatus && this.catalogStatus.verified);
            if (current) {
                // Unverify
                this.catalogStatus.verified = false;
                this.catalogStatus.verified_by = null;
                this.updateCatalogControlStrip();
                this.renderCatalogTabs();
                this.syncStatusToApi(null, false, null);
                this.showToast('Catalog verification removed.', 'info');
            } else {
                // Prompt for clinician name
                this.openVerifierModal(false);
            }
        },

        openVerifierModal: function (isEditOnly) {
            var modal = document.getElementById('modalVerifierPrompt');
            var nameInp = document.getElementById('inpVerifierName');
            if (modal && nameInp) {
                nameInp.value = this.catalogStatus.verified_by || (this.targetManifest ? this.targetManifest.author : '') || '';
                modal.style.display = 'flex';
                nameInp.focus();
            }
        },

        confirmVerification: function (verifierName) {
            this.closeAllModals();
            this.catalogStatus.verified = true;
            this.catalogStatus.verified_by = verifierName;
            this.catalogStatus.updated_at = dateStr();

            this.updateCatalogControlStrip();
            this.renderCatalogTabs();
            this.syncStatusToApi(null, true, verifierName);
            this.showToast('Clinically verified by: ' + verifierName, 'success');
        },

        syncStatusToApi: function (isComplete, isVerified, verifierName) {
            var self = this;
            var payload = {
                target: this.targetLang,
                catalog: this.currentCatalog,
                complete: isComplete,
                verified: isVerified,
                contributor: verifierName
            };

            fetch('?action=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        self.showToast(data.error || 'Failed to update status.', 'error');
                    }
                })
                .catch(function (err) {
                    self.showToast('Network error updating status: ' + err.message, 'error');
                });
        },

        // 7. Reconstruct JSON Tree & Save Catalog
        reconstructTargetJson: function () {
            var out = JSON.parse(JSON.stringify(this.targetData || {}));

            this.flattenedRows.forEach(function (row) {
                var keys = row.path.split('.');
                var curr = out;
                for (var i = 0; i < keys.length - 1; i++) {
                    var k = keys[i];
                    if (!curr[k] || typeof curr[k] !== 'object') {
                        curr[k] = {};
                    }
                    curr = curr[k];
                }

                var lastKey = keys[keys.length - 1];
                if (row.isStructured) {
                    if (!curr[lastKey] || typeof curr[lastKey] !== 'object') {
                        curr[lastKey] = {};
                    }
                    curr[lastKey].text = row.targetText;
                    if (row.targetAlias !== null) {
                        curr[lastKey].alias = row.targetAlias;
                    }
                } else {
                    curr[lastKey] = row.targetText;
                }
            });

            return out;
        },

        saveActiveCatalog: function () {
            var self = this;
            var reconstructed = this.reconstructTargetJson();
            var payload = {
                target: this.targetLang,
                catalog: this.currentCatalog,
                data: reconstructed,
                complete: !!(this.catalogStatus && this.catalogStatus.complete),
                verified: !!(this.catalogStatus && this.catalogStatus.verified),
                contributor: this.catalogStatus.verified_by || null
            };

            var btn = document.getElementById('btnWsSaveCatalog');
            var btnTop = document.getElementById('btnSaveCatalog');
            if (btn) btn.disabled = true;
            if (btnTop) btnTop.disabled = true;

            fetch('?action=save_catalog', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (btn) btn.disabled = false;
                    if (btnTop) btnTop.disabled = false;

                    if (!data.success) {
                        self.showToast(data.error || 'Failed to save catalog.', 'error');
                        return;
                    }

                    self.isDirty = false;
                    self.targetData = reconstructed;
                    self.flattenedRows.forEach(function (r) {
                        r.originalTargetText = r.targetText;
                        r.originalTargetAlias = r.targetAlias;
                    });

                    self.updateLiveSaveBadge();
                    self.renderTranslationRows();
                    self.showToast(data.message || 'Saved successfully!', 'success');
                })
                .catch(function (err) {
                    if (btn) btn.disabled = false;
                    if (btnTop) btnTop.disabled = false;
                    self.showToast('Network error saving: ' + err.message, 'error');
                });
        },

        // 8. Modals Submission & Actions
        submitNewLanguage: function () {
            var self = this;
            var code = document.getElementById('inpNewLangCode').value.trim();
            var name = document.getElementById('inpNewLangName').value.trim();
            var native = document.getElementById('inpNewLangNative').value.trim();
            var direction = document.getElementById('inpNewLangDirection').value;
            var author = document.getElementById('inpNewLangAuthor').value.trim();
            var cloneFrom = document.getElementById('inpNewLangCloneFrom').value;

            var payload = {
                code: code,
                meta: {
                    name: name,
                    native_name: native,
                    direction: direction,
                    author: author
                },
                clone_from: cloneFrom
            };

            fetch('?action=create_language', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        self.showToast(data.error || 'Failed to create language pack.', 'error');
                        return;
                    }

                    self.closeAllModals();
                    self.showToast('Language ' + name + ' (' + code + ') created successfully!', 'success');
                    self.loadInitialData();
                    self.openWorkspace(code, self.allCatalogs[0] || 'instructions.json');
                })
                .catch(function (err) {
                    self.showToast('Network error: ' + err.message, 'error');
                });
        },

        submitNewCatalog: function () {
            var self = this;
            var nameInp = document.getElementById('inpNewCatalogName');
            var name = nameInp ? nameInp.value.trim() : '';

            if (!name) return;

            fetch('?action=create_catalog', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name })
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        self.showToast(data.error || 'Failed to create catalog file.', 'error');
                        return;
                    }

                    self.closeAllModals();
                    self.showToast(data.message || 'Catalog created successfully!', 'success');
                    self.loadInitialData();
                    if (self.currentView === 'workspace') {
                        self.currentCatalog = data.catalog_file;
                        self.loadActiveCatalog();
                    }
                })
                .catch(function (err) {
                    self.showToast('Network error: ' + err.message, 'error');
                });
        },

        openManifestModal: function () {
            var meta = this.targetManifest || {};
            var inpCode = document.getElementById('inpManCode');
            var inpName = document.getElementById('inpManName');
            var inpNative = document.getElementById('inpManNative');
            var inpDirection = document.getElementById('inpManDirection');
            var inpVersion = document.getElementById('inpManVersion');
            var inpAuthor = document.getElementById('inpManAuthor');
            var inpContributors = document.getElementById('inpManContributors');

            if (inpCode) inpCode.value = this.targetLang;
            if (inpName) inpName.value = meta.name || '';
            if (inpNative) inpNative.value = meta.native_name || '';
            if (inpDirection) inpDirection.value = meta.direction || 'ltr';
            if (inpVersion) inpVersion.value = meta.version || '1.0.0';
            if (inpAuthor) inpAuthor.value = meta.author || '';
            if (inpContributors) {
                var contribs = meta.contributors || [];
                inpContributors.value = Array.isArray(contribs) ? contribs.join(', ') : contribs;
            }

            this.openModal('modalManifest');
        },

        submitManifest: function () {
            var self = this;
            var contribsRaw = document.getElementById('inpManContributors').value;
            var contribsList = contribsRaw.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return !!s; });

            var manifestData = {
                name: document.getElementById('inpManName').value.trim(),
                native_name: document.getElementById('inpManNative').value.trim(),
                direction: document.getElementById('inpManDirection').value,
                version: document.getElementById('inpManVersion').value.trim() || '1.0.0',
                author: document.getElementById('inpManAuthor').value.trim(),
                contributors: contribsList
            };

            fetch('?action=save_manifest', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: this.targetLang, manifest: manifestData })
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        self.showToast(data.error || 'Failed to save manifest.', 'error');
                        return;
                    }

                    self.closeAllModals();
                    self.showToast('Manifest saved successfully!', 'success');
                    self.loadActiveCatalog();
                })
                .catch(function (err) {
                    self.showToast('Network error: ' + err.message, 'error');
                });
        },

        runValidationReport: function () {
            var self = this;
            var content = document.getElementById('validationReportContent');
            if (content) content.innerHTML = '<div class="locale-empty-state">Running validation checks across all JSON catalogs...</div>';

            this.openModal('modalValidation');

            fetch('?action=validate_locales')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        if (content) content.innerHTML = '<div class="locale-empty-state">Error: ' + escapeHtml(data.error || 'Validation failed') + '</div>';
                        return;
                    }

                    var report = data.report || {};
                    var html = '<table class="locale-val-table">';
                    html += '<thead><tr><th>Locale</th><th>Status</th><th>Catalogs Breakdown</th><th>Errors / Warnings</th></tr></thead>';
                    html += '<tbody>';

                    Object.keys(report.languages || {}).forEach(function (code) {
                        var l = report.languages[code];
                        var hasErr = (l.errors && l.errors.length > 0);
                        var hasWarn = (l.warnings && l.warnings.length > 0);

                        html += '<tr>';
                        html += '  <td><strong>' + escapeHtml(l.name) + '</strong> (' + escapeHtml(code) + ')</td>';
                        html += '  <td>';
                        if (hasErr) {
                            html += '    <span class="locale-pill-status" style="background:#fee2e2;color:#991b1b;">Syntax Error</span>';
                        } else if (hasWarn) {
                            html += '    <span class="locale-pill-status" style="background:#fef3c7;color:#92400e;">Warning</span>';
                        } else {
                            html += '    <span class="locale-pill-status complete">' + window.ZimRxIcon.render('check', 10) + ' Valid</span>';
                        }
                        html += '  </td>';

                        html += '  <td>';
                        Object.keys(l.catalogs || {}).forEach(function (cat) {
                            var cs = l.catalogs[cat];
                            html += '    <div style="font-size:11px;margin-bottom:2px;">';
                            html += '      <code>' + escapeHtml(cat) + '</code>: ' + cs.translated + '/' + cs.total + ' (' + cs.pct + '%) ';
                            if (cs.complete) html += '<span style="color:#059669;">[Complete]</span> ';
                            if (cs.verified) html += '<span style="color:#047857;font-weight:700;">[Verified]</span>';
                            html += '    </div>';
                        });
                        html += '  </td>';

                        html += '  <td>';
                        if (hasErr) {
                            l.errors.forEach(function (e) {
                                html += '<div style="color:#dc2626;font-size:11px;">' + window.ZimRxIcon.render('alert-circle', 11) + ' ' + escapeHtml(e) + '</div>';
                            });
                        }
                        if (hasWarn) {
                            l.warnings.forEach(function (w) {
                                html += '<div style="color:#d97706;font-size:11px;">' + window.ZimRxIcon.render('info-circle', 11) + ' ' + escapeHtml(w) + '</div>';
                            });
                        }
                        if (!hasErr && !hasWarn) {
                            html += '<span style="color:#64748b;font-size:11px;">None</span>';
                        }
                        html += '  </td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    if (content) content.innerHTML = html;
                })
                .catch(function (err) {
                    if (content) content.innerHTML = '<div class="locale-empty-state">Network error running validation: ' + escapeHtml(err.message) + '</div>';
                });
        }
    };

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function dateStr() {
        var d = new Date();
        var month = '' + (d.getMonth() + 1);
        var day = '' + d.getDate();
        var year = d.getFullYear();
        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;
        return [year, month, day].join('-');
    }

    // Auto initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            App.init();
        });
    } else {
        App.init();
    }
})();
