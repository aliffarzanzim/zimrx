import { getRootNames, setRootNames } from './pack-names.js';
import { readGeoStudioJson } from './api-client.js';

export function createPackSaver({ state, preferences, commitInputs, send, onChange, onSaved, onError }) {
    let pending = false;
    return async function saveCurrentPack() {
        if (pending || !state.dataset || state.datasetPackId !== state.packId) return false;
        commitInputs();
        const dataset = state.dataset;
        const packId = state.packId;
        const revision = state.revision;
        const contributor = preferences.name;
        const today = new Date().toISOString().slice(0, 10);
        const manifest = { ...dataset.manifest, last_updated: today };
        const rootNames = getRootNames(manifest);
        const missingRootLanguage = manifest.languages.find(language => !rootNames[language]?.trim());
        if (missingRootLanguage) {
            onError(`Enter a pack root name in ${missingRootLanguage} before saving.`);
            return false;
        }
        setRootNames(manifest, rootNames);
        manifest.contributors = [...(manifest.contributors || [])];
        if (contributor && !manifest.contributors.includes(contributor)) manifest.contributors.push(contributor);
        pending = true;
        state.saving = true;
        onChange();
        try {
            // Capture the submitted revision before yielding to another edit or route.
            const body = JSON.stringify({ pack_id: packId, revision: state.datasetRevision, data: { ...dataset, manifest }, contributor });
            const response = await send(body);
            const result = await readGeoStudioJson(response);
            if (!response.ok || !result.success) throw new Error(result.error || 'Unable to save pack.');
            if (state.dataset === dataset && state.datasetPackId === packId && state.packId === packId) {
                state.datasetRevision = result.revision || state.datasetRevision;
                if (state.revision === revision) {
                    dataset.manifest = { ...manifest, last_updated: result.last_updated || today };
                    state.setDirty(false);
                }
                onSaved(packId, result.last_updated || today);
            }
            return true;
        } catch (error) {
            onError(`Error saving ${packId}: ${error.message}`);
            return false;
        } finally {
            pending = false;
            state.saving = false;
            onChange();
        }
    };
}
