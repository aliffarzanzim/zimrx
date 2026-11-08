const apiBase = new URL('api/', document.baseURI);

const apiPath = path => new URL(path, apiBase).href;
const packPath = (endpoint, packId, source) => {
    const url = new URL(endpoint, apiBase);
    url.searchParams.set('pack_id', packId);
    url.searchParams.set('source', source);
    return url.href;
};

export const apiEndpoints = Object.freeze({
    listPacks: () => apiPath('geo_studio_pack_catalog.php'),
    createPack: () => apiPath('geo_studio_pack_create.php'),
    importPack: () => apiPath('geo_studio_pack_import.php'),
    selectPack: () => apiPath('geo_studio_pack_select.php'),
    loadPack: (packId, source) => packPath('geo_studio_pack_load.php', packId, source),
    savePack: () => apiPath('geo_studio_pack_save.php'),
    deletePack: () => apiPath('geo_studio_pack_delete.php'),
    exportPack: (packId, source) => packPath('geo_studio_pack_export.php', packId, source),
});

export async function geoStudioFetch(input, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});

    if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS' && !headers.has('X-CSRF-Token')) {
        const token = window.ZimRxCsrfToken;
        if (!token) throw new Error('Geo Studio could not start a verified API session.');
        headers.set('X-CSRF-Token', token);
    }

    return fetch(input, {
        ...options,
        headers,
        credentials: 'same-origin',
    });
}
export async function readGeoStudioJson(response) {
    const body = await response.text();
    try {
        return JSON.parse(body);
    } catch {
        throw new Error('Geo Studio returned an incomplete response (HTTP ' + response.status + '). Retry, then check the PHP server output if it continues.');
    }
}
