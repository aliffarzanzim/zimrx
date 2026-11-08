import { apiEndpoints, readGeoStudioJson } from './api-client.js';
import { escapeHtml } from './editor-utils.js';

let iconMap = Object.create(null);
let loadPromise = null;

async function loadIcons() {
    if (Object.keys(iconMap).length > 0) return;
    if (!loadPromise) {
        loadPromise = fetch(apiEndpoints.iconCatalog(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        }).then(async response => {
            const payload = await readGeoStudioJson(response);
            if (!response.ok || payload.success !== true || !payload.icons || typeof payload.icons !== 'object') {
                throw new Error(payload.error || 'Geo Studio icons could not be loaded.');
            }
            const entries = Object.entries(payload.icons);
            if (entries.some(([name, markup]) => !/^[a-z0-9-]+$/.test(name) || typeof markup !== 'string')) {
                throw new Error('Geo Studio returned an invalid icon catalog.');
            }
            iconMap = Object.create(null);
            for (const [name, markup] of entries) iconMap[name] = markup;
        }).catch(error => {
            loadPromise = null;
            throw error;
        });
    }
    await loadPromise;
}

export const GeoStudioIcon = Object.freeze({
    load: loadIcons,
    render(name, size = 14, attrs = {}) {
        const key = String(name || '').toLowerCase().trim();
        const content = Object.hasOwn(iconMap, key) ? iconMap[key] : iconMap.globe || '';
        const iconSize = Number.isInteger(size) ? Math.min(64, Math.max(8, size)) : 14;
        const strokeWidth = /^\d+(?:\.\d+)?$/.test(String(attrs['stroke-width'] || attrs.strokeWidth || '2'))
            ? String(attrs['stroke-width'] || attrs.strokeWidth || '2')
            : '2';
        const className = String(attrs.class || attrs.className || 'geo-icon');
        const color = String(attrs.color || 'currentColor');
        return `<svg class="${escapeHtml(className)}" width="${iconSize}" height="${iconSize}" viewBox="0 0 24 24" fill="none" stroke="${escapeHtml(color)}" stroke-width="${strokeWidth}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${content}</svg>`;
    },
});
