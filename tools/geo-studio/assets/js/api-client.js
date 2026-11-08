const apiBase = new URL('api/', document.baseURI);

const apiPath = path => new URL(path, apiBase).href;
const packPath = (endpoint, packId) => {
    const url = new URL(endpoint, apiBase);
    url.searchParams.set('pack_id', packId);
    return url.href;
};

export const apiEndpoints = Object.freeze({
    iconCatalog: () => apiPath('icon-catalog.php'),
    listPacks: () => apiPath('pack-list.php'),
    createPack: () => apiPath('pack-create.php'),
    loadPack: packId => packPath('pack-load.php', packId),
    savePack: packId => packPath('pack-save.php', packId),
    deletePack: packId => packPath('pack-delete.php', packId),
});

export async function geoStudioFetch(input, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});

    if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS' && !headers.has('X-CSRF-Token')) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
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
