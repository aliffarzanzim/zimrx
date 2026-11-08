import { escapeHtml, getRootPlaces } from './editor-utils.js';

export function initializeStatistics({ state, formatTypeLabel }) {
    const statsBar = document.getElementById('statsBar');
    const countsModal = document.getElementById('modalTypeCountsBackdrop');
    const countsHelp = document.getElementById('typeCountsHelp');
    const countsList = document.getElementById('typeCountsList');

    function closeCountsModal() {
        countsModal.hidden = true;
    }

    statsBar.addEventListener('click', event => {
        if (event.target instanceof Element && event.target.closest('[data-action="show-type-counts"]')) {
            countsModal.hidden = false;
        }
    });
    document.getElementById('btnCloseTypeCounts').addEventListener('click', closeCountsModal);
    document.getElementById('btnDoneTypeCounts').addEventListener('click', closeCountsModal);
    countsModal.addEventListener('click', event => {
        if (event.target === countsModal) closeCountsModal();
    });

    function createCountRow(labelText, count, className = '') {
        const row = document.createElement('div');
        row.className = className ? `geo-type-count-row ${className}` : 'geo-type-count-row';
        const label = document.createElement('span');
        label.textContent = labelText;
        const value = document.createElement('strong');
        value.textContent = count.toLocaleString();
        row.append(label, value);
        return row;
    }

    function updateStats() {
        if (!state.dataset || !Array.isArray(state.dataset.places)) return;
        const counts = Object.create(null);
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

            if (Array.isArray(node.places)) node.places.forEach(walk);
        }

        getRootPlaces(state.dataset).forEach(walk);

        const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
        const topTypes = sorted.slice(0, 4);
        let pillsHtml = `<span class="geo-stat-pill">Total: <strong>${total.toLocaleString()}</strong></span>`;
        for (const [typeKey, count] of topTypes) {
            pillsHtml += `<span class="geo-stat-pill">${escapeHtml(formatTypeLabel(typeKey))}: <strong>${count.toLocaleString()}</strong></span>`;
        }
        if (sorted.length > topTypes.length) {
            pillsHtml += '<button type="button" class="geo-stat-more" data-action="show-type-counts" aria-haspopup="dialog" aria-controls="modalTypeCountsBackdrop">More</button>';
        }

        const m = state.dataset.manifest || {};
        const version = escapeHtml(m.version || 'Not specified');
        const released = escapeHtml(m.release_date || 'Not specified');
        const updated = escapeHtml(m.last_updated || new Date().toISOString().slice(0, 10));
        pillsHtml += `
            <div class="geo-manifest-badge" id="statManifestBadge">
                <span>Version: <strong>${version}</strong></span>
                <span>Released: <strong>${released}</strong></span>
                <span>Updated: <strong>${updated}</strong></span>
            </div>
        `;
        statsBar.innerHTML = pillsHtml;

        countsHelp.textContent = `${total.toLocaleString()} places across ${sorted.length} types. Places assigned to multiple types appear in each type count.`;
        const rows = [createCountRow('Total places', total, 'geo-type-count-summary')];
        for (const [typeKey, count] of sorted) {
            rows.push(createCountRow(formatTypeLabel(typeKey), count));
        }
        if (multiType > 0) {
            rows.push(createCountRow('Places with multiple types', multiType, 'geo-type-count-summary'));
        }
        countsList.replaceChildren(...rows);
    }

    return updateStats;
}
