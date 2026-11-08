import { apiEndpoints, geoStudioFetch, readGeoStudioJson } from './api-client.js';

function filterPacks(packs, value) {
    const query = value.trim().toLowerCase();
    if (!query) return packs;

    return packs.filter(pack => {
        const packId = (pack.pack_id || '').toLowerCase();
        const name = (pack.name || '').toLowerCase();
        const author = (pack.author || '').toLowerCase();
        return packId.includes(query) || name.includes(query) || author.includes(query);
    });
}

export function initializeGeoStudioHome({
    fetchPacks,
    escapeHtml,
    renderIcon,
    updateUrl,
    handleUrlRoute,
}) {
    const grid = document.getElementById('homeCardsGrid');
    let packs = [];
    let packIssues = [];
    let loadPromise = null;
    let catalogRequestVersion = 0;

    function showGridStatus(message) {
        const status = document.createElement('div');
        status.className = 'geo-grid-message geo-empty-state';
        status.textContent = message;
        grid.classList.add('is-status');
        grid.replaceChildren(status);
    }

    function renderPackIssues() {
        if (packIssues.length === 0) return;
        const panel = document.createElement('section');
        panel.className = 'geo-pack-issues';
        const heading = document.createElement('h2');
        heading.textContent = 'Pack files needing attention';
        panel.appendChild(heading);
        const list = document.createElement('ul');
        packIssues.forEach(issue => {
            const item = document.createElement('li');
            const file = document.createElement('strong');
            file.textContent = issue.file;
            const reason = document.createElement('span');
            reason.textContent = issue.reason;
            item.append(file, reason);
            list.appendChild(item);
        });
        panel.appendChild(list);
        grid.appendChild(panel);
    }

    function updateStats() {
        const totalPlaces = packs.reduce((sum, pack) => sum + (pack.total_places || 0), 0);
        document.getElementById('homeTotalPacks').textContent = packs.length.toLocaleString();
        document.getElementById('homeGlobalPlaces').textContent = totalPlaces.toLocaleString();
    }

    function renderCards() {
        grid.replaceChildren();
        grid.classList.remove('is-status');
        renderPackIssues();
        const query = document.getElementById('homeSearchInput')?.value || '';
        const filtered = filterPacks(packs, query);

        if (filtered.length === 0) {
            if (packIssues.length === 0) {
                showGridStatus('No packs found matching filter.');
            } else {
                const status = document.createElement('div');
                status.className = 'geo-grid-message geo-empty-state';
                status.textContent = packs.length === 0 ? 'No valid address packs are available.' : 'No packs found matching filter.';
                grid.appendChild(status);
            }
            return;
        }

        filtered.forEach(pack => {
            const card = document.createElement('div');
            card.className = 'pack-card';

            const topTypesHtml = Object.entries(pack.top_types || {})
                .map(([typeKey, count]) => `<span class="c-stat-pill">${escapeHtml(pack.type_labels?.[typeKey] || typeKey)}: <strong>${count.toLocaleString()}</strong></span>`)
                .join(' ');
            const codeLabel = pack.code_label || (pack.pack_type === 'custom' ? 'Unit Code' : 'Postal Code');
            const codeCountHtml = pack.code_count
                ? `<span class="c-stat-pill">${escapeHtml(codeLabel)}: <strong>${pack.code_count.toLocaleString()}</strong></span>`
                : '';
            const contributors = Array.isArray(pack.contributors)
                ? pack.contributors.join(', ')
                : (pack.author || 'None recorded');
            const formattedSize = pack.filesize > 1048576
                ? `${(pack.filesize / 1048576).toFixed(1)} MB`
                : `${(pack.filesize / 1024).toFixed(0)} KB`;
            const isNational = pack.pack_type === 'national';
            const scopeBadge = isNational
                ? '<span class="scope-pill scope-national">National</span>'
                : '<span class="scope-pill scope-custom">Custom</span>';

            card.innerHTML = `
                <div class="pack-card-header">
                    <div class="pack-card-identity">
                        <div>
                            <div class="pack-card-title">${escapeHtml(pack.name)}</div>
                            <div class="pack-card-size">${formattedSize}</div>
                        </div>
                    </div>
                    <div class="pack-card-badges">
                        ${scopeBadge}
                        <span class="pack-card-ver">v${escapeHtml(pack.version || '1.0.0')}</span>
                    </div>
                </div>
                <div class="pack-card-body">
                    <div class="pack-card-count">
                        <span class="pack-card-total">${(pack.total_places || 0).toLocaleString()}</span>
                        <span class="pack-card-units">Address Units</span>
                    </div>
                    <div class="pack-stat-badge-row">
                        ${topTypesHtml}
                        ${codeCountHtml}
                    </div>
                    <div class="pack-card-meta">
                        <div><strong>Author:</strong> ${escapeHtml(pack.author || 'Not specified')}</div>
                        <div><strong>Contributors:</strong> ${escapeHtml(contributors || 'None recorded')}</div>
                        <div><strong>License:</strong> ${escapeHtml(pack.license || 'CC-BY-4.0')}</div>
                        <div class="pack-card-dates">
                            <span><strong>Released:</strong> ${escapeHtml(pack.release_date || pack.last_updated || '')}</span>
                            <span><strong>Updated:</strong> ${escapeHtml(pack.last_updated || '')}</span>
                        </div>
                    </div>
                </div>
                <div class="pack-card-footer">
                    <button type="button" class="pack-card-open geo-btn geo-btn-primary btn-open-pack" data-pack-id="${escapeHtml(pack.pack_id)}">
                        <span>Open Editor</span> ${renderIcon('arrow-right', 12)}
                    </button>
                    <button type="button" class="geo-btn pack-card-delete btn-delete-pack" data-pack-id="${escapeHtml(pack.pack_id)}">
                        <span>Delete</span>
                    </button>
                </div>
            `;

            card.querySelector('.btn-open-pack').onclick = () => {
                updateUrl(pack.pack_id, '', true);
                handleUrlRoute();
            };
            card.querySelector('.btn-delete-pack').onclick = async event => {
                const button = event.currentTarget;
                if (!confirm(`Delete ${pack.name} (${pack.pack_id}) from systemdata/seeds? The next static database build will remove it from zimrx_static.db.`)) return;
                button.disabled = true;
                try {
                    const response = await geoStudioFetch(apiEndpoints.deletePack(pack.pack_id), {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ revision: pack.revision }),
                    });
                    const result = await readGeoStudioJson(response);
                    if (!response.ok || !result.success) throw new Error(result.error || 'Unable to delete pack.');
                    await loadPacks({ showLoading: false });
                } catch (error) {
                    button.disabled = false;
                    showGridStatus(`Unable to delete ${pack.pack_id}: ${error.message}`);
                }
            };
            grid.appendChild(card);
        });
    }

    async function loadPacks({ showLoading = true } = {}) {
        const requestVersion = ++catalogRequestVersion;
        if (showLoading) {
            showGridStatus('Loading available packs...');
        }
        try {
            const response = await fetchPacks();
            const result = await readGeoStudioJson(response);
            if (requestVersion !== catalogRequestVersion) return;
            if (!result.success) {
                showGridStatus(`Failed to load packs: ${result.error || ''}`);
                return;
            }

            packs = result.packs || [];
            packIssues = Array.isArray(result.issues) ? result.issues : [];
            updateStats();
            renderCards();
        } catch (error) {
            if (requestVersion !== catalogRequestVersion) return;
            showGridStatus(`Network error loading packs: ${error.message}`);
        }
    }

    async function ensureLoaded() {
        if (packs.length > 0) return;
        if (!loadPromise) {
            loadPromise = loadPacks().finally(() => {
                loadPromise = null;
            });
        }
        await loadPromise;
    }

    document.getElementById('homeSearchInput')?.addEventListener('input', renderCards);

    return {
        ensureLoaded,
        refreshPacks: () => loadPacks({ showLoading: false }),
        hasPacks: () => packs.length > 0,
        renderCards,
    };
}
