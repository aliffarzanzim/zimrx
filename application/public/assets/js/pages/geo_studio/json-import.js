import { apiEndpoints, geoStudioFetch, readGeoStudioJson } from './api-client.js';

export function initializeJsonImport({ button, input, refreshCatalog, updateUrl, handleUrlRoute, state, setDirty, showToast }) {
    let pending = false;

    async function importFile(file) {
        if (!file || pending) return;
        if (!file.name.toLowerCase().endsWith('.json') || file.size < 1 || file.size > 4 * 1024 * 1024) {
            showToast('Choose a JSON address pack file no larger than 4 MB.');
            return;
        }

        pending = true;
        button.disabled = true;
        try {
            const catalogResponse = await geoStudioFetch(apiEndpoints.listPacks());
            const catalog = await readGeoStudioJson(catalogResponse);
            if (!catalogResponse.ok || catalog.success !== true || !Array.isArray(catalog.packs)) {
                throw new Error(catalog.error || 'Could not check existing packs.');
            }

            const preview = JSON.parse(await file.text());
            const packId = String(preview?.manifest?.pack_id || '').trim().toUpperCase();
            if (!/^[A-Z0-9_-]{2,32}$/.test(packId)) {
                throw new Error('The JSON file does not contain a valid address pack ID.');
            }
            const existing = catalog.packs.find(pack => pack.pack_id === packId && pack.source === 'user');
            if (existing && !window.confirm(`Replace the modified pack ${existing.name} (${packId})?`)) return;
            if (state.isDirty && !window.confirm('Discard unsaved editor changes and import this pack?')) return;

            const body = new FormData();
            body.append('pack_json', file);
            if (existing) body.append('overwrite_revision', existing.revision);
            const response = await geoStudioFetch(apiEndpoints.importPack(), { method: 'POST', body });
            const result = await readGeoStudioJson(response);
            if (!response.ok || result.success !== true) throw new Error(result.error || 'Import failed.');

            await refreshCatalog();
            if (state.isDirty) setDirty(false);
            const refreshNotice = result.active_pack_refreshed === false
                ? ' The active address search could not be refreshed. Reapply this pack in Address Settings.'
                : '';
            showToast(`Imported ${result.pack_id}.${refreshNotice}`);
            updateUrl(result.pack_id, '', true, 'user');
            await handleUrlRoute({ reload: true });
        } catch (error) {
            showToast(`Unable to import pack: ${error.message}`);
        } finally {
            pending = false;
            button.disabled = false;
            input.value = '';
        }
    }

    button.addEventListener('click', () => input.click());
    input.addEventListener('change', () => { void importFile(input.files?.[0]); });
}
