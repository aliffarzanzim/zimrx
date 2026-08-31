const searchCache = new Map();
const initialSidebarState = window.ZIMRX_INITIAL_SIDEBAR_STATE || null;
let sidebarScrollTimer = null;

function cacheKey(mode, query) {
    return `${mode}::${(query || '').trim().toLowerCase()}`;
}

function renderSearchResults(data, mode = currentMode) {
    $('#resultsList').empty();
    if (!data || data.length === 0) return;

    data.forEach(item => {
        let html = '';
        if (mode === 'brand') {
            const iconPath = getDosageFormIcon(item.form, item.pres_new_upper);
            const iconHtml = iconPath ? `<img src="${iconPath}" class="res-icon">` : '<span class="res-star">â˜…</span>';

            html = `
                <div class="res-row" data-brand-id="${item.id}" onclick="loadBrand('${item.id}')">
                    ${iconHtml}
                    <div class="res-info">
                        <div class="res-line-1">
                            <span class="res-brand">${item.brand_name}</span>
                            <span class="res-price">&#2547;${item.price}</span>
                        </div>
                        <div class="res-line-2">${item.generic}</div>
                        <div class="res-line-3">${item.strength} | ${item.form}</div>
                        <div class="res-line-4">${item.manufacturer}</div>
                    </div>
                </div>
            `;
        } else if (mode === 'generic') {
            html = `
                <div class="res-row" onclick="loadGenericBrands('${item.generic_id}', '${item.generic.replace(/'/g, "\\'")}')">
                    <i class="fas fa-microscope" style="color:#64748b;"></i>
                    <div class="res-info">
                        <span class="res-brand" style="color:var(--accent-blue);">${item.generic}</span>
                        <span class="res-price">${item.brand_count} Brands</span>
                    </div>
                </div>
            `;
        } else if (mode === 'class') {
            html = `
                <div class="res-row" onclick="loadClassDetail('${item.cls.replace(/'/g, "\\'")}')">
                    <i class="fas fa-layer-group" style="color:var(--accent-blue); font-size: 1.1rem;"></i>
                    <div class="res-info">
                        <span class="res-brand" style="color:var(--navy-dark);">${item.cls}</span>
                    </div>
                </div>
            `;
        } else if (mode === 'indication') {
            html = `
                <div class="res-row" onclick="loadIndicationDetail('${item.indication_id}', '${item.indication_name.replace(/'/g, "\\'")}')">
                    <i class="fas fa-stethoscope" style="color:#64748b; font-size: 1.2rem;"></i>
                    <div class="res-info">
                        <span class="res-brand" style="color:var(--navy-dark);">${item.indication_name}</span>
                    </div>
                </div>
            `;
        }
        $('#resultsList').append(html);
    });

    if (mode === 'brand') {
        scheduleSidebarBrandPreload();
    }
}

function scheduleSidebarBrandPreload() {
    const run = () => {
        if (typeof warmBrandDetail !== 'function') return;
        $('#resultsList .res-row[data-brand-id]').slice(0, 8).each(function() {
            const brandId = this.dataset.brandId || '';
            if (brandId) {
                warmBrandDetail(brandId);
            }
        });
    };

    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(run, { timeout: 800 });
    } else {
        window.setTimeout(run, 120);
    }
}

function docsBookmarks() {
    return $('#docsBooksPapersArea .docs-paper-section').map(function() {
        return {
            id: this.id,
            title: $(this).data('docsTitle') || '',
            keywords: $(this).data('docsKeywords') || '',
            text: $(this).text()
        };
    }).get();
}

function renderDocsBookmarks(query = '') {
    const q = String(query || '').trim().toLowerCase();
    const rows = docsBookmarks().filter(item => {
        const haystack = `${item.title} ${item.keywords} ${item.text}`.toLowerCase();
        return !q || haystack.includes(q);
    });

    $('#resultsList').empty();
    if (!rows.length) {
        $('#resultsList').append('<div style="padding: 20px; text-align:center; color:#64748b;">No bookmarks found.</div>');
        return;
    }

    rows.forEach(item => {
        $('#resultsList').append(`
            <div class="res-row docs-bookmark-row" data-target="${item.id}">
                <i class="fas fa-bookmark" style="color:var(--accent-blue);"></i>
                <div class="res-info">
                    <span class="res-brand" style="color:var(--navy-dark);">${item.title}</span>
                    <div class="res-line-2">Jump to ${item.title} section</div>
                </div>
            </div>
        `);
    });
}

function openDocsBooksPapers() {
    currentMode = 'docs';
    $('.db-tab').removeClass('active');
    $('#docsBooksPapersTab').addClass('active');
    $('#dbSearchInput').attr('placeholder', 'Search bookmarks...').val('');
    $('#middleColumn, #sidebarNav, #drugDetailArea, #emptyState').hide();
    $('#docsBooksPapersArea').show();
    renderDocsBookmarks('');

    const url = new URL(window.location);
    url.searchParams.set('mode', 'docs');
    ['brand_id', 'generic_id', 'indication_id', 'class_id', 'brand_search', 'generic_search', 'indication_search', 'class_search'].forEach(param => {
        url.searchParams.delete(param);
    });
    window.history.replaceState({}, '', url);
}

function switchTab(mode, searchValue = '', runSearch = null) {
    $('.db-tab').removeClass('active');
    $('.db-subtab').removeClass('active');
    $(`.db-tab[data-type="${mode}"]`).addClass('active');
    currentMode = mode;

    $('#dbSearchInput')
        .attr('placeholder', 'Search ' + mode.charAt(0).toUpperCase() + mode.slice(1) + '...')
        .val(searchValue);

    $('#resultsList').empty();
    $('#middleColumn').hide();
    $('#sidebarNav').hide();
    $('#docsBooksPapersArea').hide();
    $('#drugDetailArea').toggle(!!currentBrandId);
    $('#emptyState').toggle(!currentBrandId);

    const url = new URL(window.location);
    url.searchParams.set('mode', currentMode);
    ['brand_id', 'generic_id', 'indication_id', 'class_id', 'brand_search', 'generic_search', 'indication_search', 'class_search'].forEach(param => {
        url.searchParams.delete(param);
    });
    window.history.replaceState({}, '', url);

    const shouldSearch = runSearch !== null ? runSearch : true;
    if (shouldSearch) performSearch(searchValue, false);
}

$(document).ready(function() {
    $('.db-tab').click(function() {
        const mode = $(this).data('type');
        if (!mode) return;
        switchTab(mode);
        $('#dbSearchInput').focus();
    });

    $('#docsBooksPapersTab').on('click', function() {
        openDocsBooksPapers();
        $('#dbSearchInput').focus();
    });

    $('#dbSearchInput').on('input', function() {
        clearTimeout(searchTimer);
        const q = $(this).val();
        searchTimer = setTimeout(() => {
            if (currentMode === 'docs') renderDocsBookmarks(q);
            else performSearch(q, false);
        }, 180);
    });

    $('#resultsList').on('click', '.docs-bookmark-row', function() {
        const target = document.getElementById($(this).data('target'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    $('#resultsList').on('mouseenter mousedown', '.res-row[data-brand-id]', function() {
        if (typeof warmBrandDetail !== 'function') return;
        const brandId = this.dataset.brandId || '';
        if (brandId) {
            warmBrandDetail(brandId);
        }
    });

    $('#resultsList').on('scroll', function() {
        clearTimeout(sidebarScrollTimer);
        const el = this;
        sidebarScrollTimer = setTimeout(() => {
            const url = new URL(window.location);
            url.searchParams.set('sidebar_top', String(el.scrollTop || 0));
            window.history.replaceState({}, '', url);
        }, 80);
    });

    $(document).on('click', '.acc-header', function() {
        const item = $(this).parent();
        item.toggleClass('open');
        item.find('.acc-content').slideToggle(200);
    });

    $(window).on('resize', function() {
        if (typeof syncDrugHeaderLayout === 'function') {
            syncDrugHeaderLayout();
        }
    });

    const params = new URLSearchParams(window.location.search);
    const urlMode = params.get('mode') || 'brand';
    const searchBrandQ = params.get('brand_search');
    const searchGenericQ = params.get('generic_search');
    const searchIndicationQ = params.get('indication_search');
    const searchClassQ = params.get('class_search');

    const brandId = params.get('brand_id');
    const genericId = params.get('generic_id');
    const indicationId = params.get('indication_id');
    const classId = params.get('class_id');
    const initialQueryByMode = {
        brand: searchBrandQ || '',
        generic: searchGenericQ || '',
        indication: searchIndicationQ || '',
        class: searchClassQ || ''
    };

    currentMode = urlMode;
    const modeTab = $(`.db-tab[data-type="${urlMode}"]`);
    if (modeTab.length) {
        $('.db-tab').removeClass('active');
        modeTab.addClass('active');
    }
    $('#dbSearchInput').attr('placeholder', 'Search ' + urlMode.charAt(0).toUpperCase() + urlMode.slice(1) + '...');

    const hasInitialSidebar = initialSidebarState && initialSidebarState.mode === urlMode && initialSidebarState.query === (initialQueryByMode[urlMode] || '');
    if (hasInitialSidebar) {
        searchCache.set(cacheKey(urlMode, initialQueryByMode[urlMode] || ''), initialSidebarState.results || []);
    }

    if (brandId) {
        const initialDrugDetail = window.ZIMRX_INITIAL_DRUG_DETAIL || null;
        const hasPreRenderedBrand =
            initialDrugDetail &&
            initialDrugDetail.brand &&
            String(initialDrugDetail.brand.id || '') === String(brandId);

        if (hasPreRenderedBrand) {
            currentBrandId = brandId;
            currentGenericId = initialDrugDetail.brand.generic_id || null;
            currentFormNew = initialDrugDetail.brand.form_new || null;
            $('.res-row, .mid-row').removeClass('active');
            $(`[onclick*="'${brandId}'"]`).addClass('active');
            $(`#mid_row_${brandId}`).addClass('active');
            $('#emptyState').hide();
            $('#docsBooksPapersArea').hide();
            $('#drugDetailArea').css('display', 'flex');
        } else {
            loadBrand(brandId, initialDrugDetail);
        }
    }

    if (genericId) loadGenericBrands(genericId);

    if (urlMode === 'indication' && indicationId) {
        loadIndicationDetail(indicationId, searchIndicationQ || 'Active Indication');
    } else if (urlMode === 'class' && classId) {
        loadClassDetail(classId);
    } else if (urlMode === 'brand' && searchBrandQ) {
        $('#dbSearchInput').val(searchBrandQ);
        if (!hasInitialSidebar) performSearch(searchBrandQ, true);
    } else if (urlMode === 'generic' && searchGenericQ) {
        $('#dbSearchInput').val(searchGenericQ);
        if (!hasInitialSidebar) performSearch(searchGenericQ, true);
    } else if (urlMode === 'class' && searchClassQ) {
        $('#dbSearchInput').val(searchClassQ);
        if (!hasInitialSidebar) performSearch(searchClassQ, true);
    } else if (urlMode === 'indication' && searchIndicationQ) {
        $('#dbSearchInput').val(searchIndicationQ);
        if (!hasInitialSidebar) performSearch(searchIndicationQ, true);
    } else if (urlMode === 'docs') {
        openDocsBooksPapers();
    } else if (!brandId && !genericId && !indicationId && !classId && (urlMode === 'brand' || urlMode === 'generic' || urlMode === 'class' || urlMode === 'indication')) {
        if (!hasInitialSidebar) performSearch('');
    }

    if (brandId) {
        toggleSidebarMoaButton(false);
        if (typeof syncDrugHeaderLayout === 'function') {
            requestAnimationFrame(syncDrugHeaderLayout);
        }
    } else if (urlMode === 'brand') {
        scheduleSidebarBrandPreload();
    }

    $('#midSearchInput, #midFormFilter').on('input change', function() {
        filterGenericBrands();
    });

    $(document).on('input change', '#modalSearchInput, #modalFormFilter', function() {
        applyModalFilter();
    });

    $(document).on('input', '#moaSearchInput', function() {
        filterMoaTable();
    });

    $(document).on('input', '#modalSearchInput', function() {
        const q = $(this).val().toLowerCase();
        $('#altTableBody tr').each(function() {
            const brand = $(this).find('td:eq(0)').text().toLowerCase();
            const company = $(this).find('td:eq(3)').text().toLowerCase();
            $(this).toggle(brand.includes(q) || company.includes(q));
        });
    });
});

function performSearch(query, skipUrlUpdate = false) {
    query = (query || '').trim();
    if (currentMode === 'docs') {
        renderDocsBookmarks(query);
        return;
    }

    if (!skipUrlUpdate) {
        $('#resultsList').scrollTop(0);
        const url = new URL(window.location);
        if (query) url.searchParams.set(currentMode + '_search', query);
        else url.searchParams.delete(currentMode + '_search');
        url.searchParams.delete('sidebar_top');
        if (currentMode === 'brand') url.searchParams.delete('brand_id');
        if (currentMode === 'generic') {
            url.searchParams.delete('generic_id');
            url.searchParams.delete('brand_id');
        }
        if (currentMode === 'indication') {
            url.searchParams.delete('indication_id');
            url.searchParams.delete('generic_id');
            url.searchParams.delete('brand_id');
        }
        if (currentMode === 'class') {
            url.searchParams.delete('class_id');
            url.searchParams.delete('indication_id');
            url.searchParams.delete('generic_id');
            url.searchParams.delete('brand_id');
        }
        window.history.replaceState({}, '', url);
    }

    const key = cacheKey(currentMode, query);
    if (searchCache.has(key)) {
        renderSearchResults(searchCache.get(key), currentMode);
        return;
    }

    $.getJSON(`api/drug_explorer.php?type=${currentMode}&q=${encodeURIComponent(query)}`, function(data) {
        searchCache.set(key, data || []);
        renderSearchResults(data || [], currentMode);
    });
}

function loadIndicationDetail(id, name) {
    setCurrentClassId('');
    toggleSidebarMoaButton(false);
    $('#sidebarNav').css('display', 'flex');
    $('#sidebarNavTitle').text(name);
    $('#resultsList').empty().append('<div style="padding: 20px; text-align:center; color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Loading medications...</div>');

    $.getJSON(`api/drug_explorer.php?type=indication_generics&id=${id}`, function(data) {
        $('#resultsList').empty();
        if (!data || data.length === 0) {
            $('#resultsList').append('<div style="padding: 20px; text-align:center; color:#64748b;">No drugs mapped to this indication yet.</div>');
            return;
        }

        data.forEach(item => {
            const html = `
                <div class="res-row" onclick="loadGenericBrands('${item.id}', '${item.name.replace(/'/g, "\\'")}')">
                    <i class="fas fa-microscope" style="color:var(--accent-blue); font-size: 1.1rem;"></i>
                    <div class="res-info">
                        <span class="res-brand" style="color:var(--accent-blue);">${item.name}</span>
                        <span class="res-price">${item.brand_count} Brands</span>
                    </div>
                </div>
            `;
            $('#resultsList').append(html);
        });
    });

    const url = new URL(window.location);
    url.searchParams.set('indication_id', id);
    window.history.pushState({}, '', url);
}

function loadClassDetail(cls, autoGenericId = '', autoGenericName = '') {
    const className = getPrimaryClassLabel(cls);
    setCurrentClassId(className);
    toggleSidebarMoaButton(!!className);

    $('#sidebarNav').css('display', 'flex');
    $('#sidebarNavTitle').text(className);
    $('#resultsList').empty().append('<div style="padding: 20px; text-align:center; color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Loading generics...</div>');

    $.getJSON(`api/drug_explorer.php?type=class_generics&id=${encodeURIComponent(className)}`, function(data) {
        $('#resultsList').empty();
        if (!data || data.length === 0) {
            $('#resultsList').append('<div style="padding: 20px; text-align:center; color:#64748b;">No generics found for this therapeutic class.</div>');
            return;
        }

        data.forEach(item => {
            const html = `
                <div class="res-row" onclick="loadGenericBrands('${item.id}', '${item.name.replace(/'/g, "\\'")}')">
                    <i class="fas fa-microscope" style="color:var(--accent-blue); font-size: 1.1rem;"></i>
                    <div class="res-info">
                        <span class="res-brand" style="color:var(--accent-blue);">${item.name}</span>
                        <span class="res-price">${item.brand_count} Brands</span>
                    </div>
                </div>
            `;
            $('#resultsList').append(html);
        });

        if (autoGenericId) {
            loadGenericBrands(autoGenericId, autoGenericName);
        }
    });

    const url = new URL(window.location);
    url.searchParams.set('class_id', className);
    window.history.pushState({}, '', url);
}

function goBackToSidebarResults() {
    $('#sidebarNav').hide();
    toggleSidebarMoaButton(false);

    const url = new URL(window.location);
    url.searchParams.delete('indication_id');
    url.searchParams.delete('class_id');
    window.history.pushState({}, '', url);

        performSearch($('#dbSearchInput').val());
    }

function runDrillDown(type, id) {
    $.getJSON(`api/drug_explorer.php?type=${type}&id=${encodeURIComponent(id)}`, function(data) {
        $('#resultsList').empty();
        $('#resultsList').append('<div style="padding:10px; border-bottom:1px solid #ddd; background:#f1f5f9; cursor:pointer;" onclick="performSearch(\'\')"><i class="fas fa-arrow-left"></i> BACK</div>');
        data.forEach(item => {
            const iconPath = getDosageFormIcon(item.form, item.pres_new_upper);
            const iconHtml = iconPath ? `<img src="${iconPath}" class="res-icon">` : '<span class="res-star">â˜…</span>';
            const html = `
                <div class="res-row" onclick="loadBrand('${item.id}')">
                     ${iconHtml}
                    <div class="res-info">
                        <span class="res-brand">${item.pres_new_upper}</span>
                        <span class="res-man">-${item.man_short}</span>
                    </div>
                </div>
            `;
            $('#resultsList').append(html);
        });
    });
}
