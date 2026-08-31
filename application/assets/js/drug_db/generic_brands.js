function loadGenericBrands(gid, gname) {
        currentGenericId = gid;
        if (gname) $('#midGenericName').text(gname);
        $('#middleColumn').css('display', 'flex');
        $('.res-row').removeClass('active');
        $(`[onclick*="'${gid}'"]`).addClass('active');

        // URL Update
        const url = new URL(window.location);
        url.searchParams.set('generic_id', gid);
        window.history.replaceState({}, '', url);

        $.getJSON(`api/drug_explorer.php?type=generic_brands&id=${gid}`, function(data) {
            allGenericBrands = data.brands;
            $('#midCountLabel').text(allGenericBrands.length + ' Brands Found');
            
            // Populate Form Filter
            const options = '<option value="">All Dosage Forms</option>' + data.forms.map(f => `<option value="${f}">${f}</option>`).join('');
            $('#midFormFilter').html(options);
            $('#modalFormFilter').html('<option value="">All Forms</option>' + data.forms.map(f => `<option value="${f}">${f}</option>`).join(''));

            renderGenericBrands(allGenericBrands);
        });
    }

    function renderGenericBrands(brands) {
        $('#midResultsList').empty();
        brands.forEach(b => {
            const activeCls = (b.id == currentBrandId) ? 'active' : '';
            const iconPath = getDosageFormIcon(b.form_new, b.pres_new_upper);
            const iconHtml = iconPath ? `<img src="${iconPath}" class="mid-icon">` : '<div class="mid-icon" style="color:#cbd5e1;"><i class="fas fa-capsules"></i></div>';
            
            const row = `
                <div class="mid-row ${activeCls}" id="mid_row_${b.id}" onclick="loadBrand('${b.id}')">
                    ${iconHtml}
                    <div class="mid-info">
                        <div class="mid-line-1">
                            <div class="mid-brand">${b.brand_name}</div>
                            <div class="mid-price">৳${b.price}</div>
                        </div>
                        <div class="mid-line-2">${b.generic}</div>
                        <div class="mid-line-3">${b.strength} | ${b.form}</div>
                        <div class="mid-line-4">${b.manufacturer}</div>
                    </div>
                </div>
            `;
            $('#midResultsList').append(row);
        });
    }

    function filterGenericBrands() {
        const q = $('#midSearchInput').val().toLowerCase();
        const f = $('#midFormFilter').val();

        const filtered = allGenericBrands.filter(b => {
            const matchesQ = b.brand_name.toLowerCase().includes(q) || b.manufacturer.toLowerCase().includes(q) || b.pres_new_upper.toLowerCase().includes(q);
            const matchesF = !f || b.form_new === f;
            return matchesQ && matchesF;
        });

        renderGenericBrands(filtered);
    }

    function openGenericTable() {
        const gName = $('#midGenericName').text();
        $('#modalGenericTitle').text(gName + ' - Full Brand List');
        $('#modalSearchInput').val('');
        $('#modalFormFilter').val('').show(); // Ensure form filter is visible for generic table
        
        renderTableRows(allGenericBrands);
        $('#otherBrandsModal').css('display', 'flex');
    }

    function openMoaTable(className = '') {
        const targetClass = getPrimaryClassLabel(className || currentClassId);
        if (!targetClass) return;

        $('#moaTableTitle').text(targetClass + ' - MOA Table');
        $('#moaSearchInput').val('');
        $('#moaTableBody').html('<tr><td colspan="3" style="text-align:center; padding: 24px; color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Loading MOA table...</td></tr>');
        $('#moaTableModal').css('display', 'flex');

        $.getJSON(`api/drug_explorer.php?type=class_moa_table&id=${encodeURIComponent(targetClass)}`, function(data) {
            currentClassMoaRows = data || [];
            renderMoaTableRows(currentClassMoaRows);
        });
    }

    function renderTableRows(brands) {
        $('#altTableBody').empty();
        brands.forEach(item => {
            const row = `
                <tr class="alt-row" onclick="loadBrand('${item.id}'); closeModal();">
                    <td style="font-weight:700; color:var(--navy-dark); cursor:pointer;">${item.pres_new_upper}</td>
                    <td>${item.strength}</td>
                    <td>${item.form_new}</td>
                    <td>${item.manufacturer}</td>
                    <td style="font-weight:700;">TK. ${item.price}</td>
                </tr>
            `;
            $('#altTableBody').append(row);
        });
    }

    function renderMoaTableRows(rows) {
        $('#moaTableBody').empty();

        if (!rows || rows.length === 0) {
            $('#moaTableBody').html('<tr><td colspan="3" style="text-align:center; padding: 24px; color:#64748b;">No mode of action data found for this class.</td></tr>');
            return;
        }

        rows.forEach(item => {
            const preview = item.brand_preview ? `<div class="moa-brand-summary">${escapeHtml(item.brand_preview)}</div>` : '';
            const moaText = item.mode_of_action ? formatMoaText(item.mode_of_action) : '<span style="color:#94a3b8;">No mode of action available.</span>';
            const row = `
                <tr class="alt-row" onclick="selectMoaGeneric('${item.id}', '${item.name.replace(/'/g, "\\'")}')">
                    <td class="moa-generic">${escapeHtml(item.name)}</td>
                    <td>
                        <div class="moa-count">${item.brand_count} Brands</div>
                        ${preview}
                    </td>
                    <td class="moa-text">${moaText}</td>
                </tr>
            `;
            $('#moaTableBody').append(row);
        });
    }

    function applyModalFilter() {
        const q = $('#modalSearchInput').val().toLowerCase();
        const f = $('#modalFormFilter').val();

        $('#altTableBody tr').each(function() {
            const text = $(this).text().toLowerCase();
            const formCell = $(this).find('td:eq(2)').text();
            
            const matchesQ = text.includes(q);
            const matchesF = !f || formCell === f;
            
            if (matchesQ && matchesF) $(this).show();
            else $(this).hide();
        });
    }

    function filterMoaTable() {
        const q = $('#moaSearchInput').val().toLowerCase();
        $('#moaTableBody tr').each(function() {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    }

    function selectMoaGeneric(gid, gname) {
        closeMoaModal();

        if (currentMode === 'class' && $('#sidebarNav').is(':visible') && $('#sidebarNavTitle').text().trim() === currentClassId) {
            loadGenericBrands(gid, gname);
            return;
        }

        switchTab('class', currentClassId, false);
        loadClassDetail(currentClassId, gid, gname);
    }
