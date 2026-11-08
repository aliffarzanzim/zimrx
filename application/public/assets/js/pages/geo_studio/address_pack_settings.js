import { apiEndpoints, geoStudioFetch, readGeoStudioJson } from './api-client.js';

const root = document.getElementById('zrx-address-pack-settings-root');
if (root) {
    void loadAddressPackSettings();
}

async function loadAddressPackSettings() {
    try {
        const response = await geoStudioFetch(apiEndpoints.listPacks());
        const result = await readGeoStudioJson(response);
        if (!response.ok || result.success !== true || !Array.isArray(result.packs)) {
            throw new Error(result.error || 'Address packs could not be loaded.');
        }
        renderAddressPackSettings(result.packs, result.active);
    } catch (error) {
        root.textContent = `Address pack settings could not be loaded: ${error.message}`;
    }
}

function renderAddressPackSettings(packs, active) {
    const panel = document.createElement('section');
    panel.className = 'zrx-address-pack-panel';

    const heading = document.createElement('div');
    heading.className = 'zrx-address-pack-heading';
    const copy = document.createElement('div');
    const title = document.createElement('h2');
    title.textContent = 'Practice Address Pack';
    const description = document.createElement('p');
    description.textContent = 'Choose a pack and language for patient address search.';
    copy.append(title, description);
    const studioLink = document.createElement('a');
    studioLink.href = 'geo_studio.php';
    studioLink.className = 'btn btn-outline btn-sm';
    studioLink.textContent = 'Open Geo Studio';
    heading.append(copy, studioLink);

    const controls = document.createElement('div');
    controls.className = 'zrx-address-pack-controls';
    const packLabel = document.createElement('label');
    packLabel.htmlFor = 'zrx-address-pack-select';
    packLabel.textContent = 'Address pack';
    const packSelect = document.createElement('select');
    packSelect.id = 'zrx-address-pack-select';
    packSelect.className = 'zrx-form-control';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select an address pack';
    packSelect.appendChild(placeholder);
    const languageLabel = document.createElement('label');
    languageLabel.htmlFor = 'zrx-address-language-select';
    languageLabel.textContent = 'Address language';
    const languageSelect = document.createElement('select');
    languageSelect.id = 'zrx-address-language-select';
    languageSelect.className = 'zrx-form-control';
    const packField = document.createElement('div');
    packField.className = 'zrx-address-pack-field';
    packField.append(packLabel, packSelect);
    const languageField = document.createElement('div');
    languageField.className = 'zrx-address-pack-field';
    languageField.append(languageLabel, languageSelect);
    const saveButton = document.createElement('button');
    saveButton.type = 'button';
    saveButton.className = 'btn btn-primary btn-sm';
    saveButton.textContent = 'Use Selected Pack';
    const status = document.createElement('p');
    status.className = 'zrx-address-pack-status';
    status.setAttribute('role', 'status');

    for (const pack of packs) {
        if (typeof pack.pack_id !== 'string' || !Array.isArray(pack.languages)) continue;
        const option = document.createElement('option');
        option.value = `${pack.source}:${pack.pack_id}`;
        const sourceLabel = pack.source === 'system' ? 'System' : pack.has_system_pack ? 'Modified' : 'User';
        option.textContent = `${pack.name} (${sourceLabel})`;
        packSelect.appendChild(option);
    }

    function selectedPack() {
        const [source, ...idParts] = packSelect.value.split(':');
        const packId = idParts.join(':');
        return packs.find(pack => pack.pack_id === packId && pack.source === source) || null;
    }

    function updateLanguages(preferred = '') {
        const pack = selectedPack();
        languageSelect.replaceChildren();
        if (!pack) return;
        for (const language of pack.languages) {
            if (typeof language !== 'string') continue;
            const option = document.createElement('option');
            option.value = language;
            option.textContent = language;
            languageSelect.appendChild(option);
        }
        languageSelect.value = pack.languages.includes(preferred) ? preferred : pack.default_language;
    }

    if (active) {
        packSelect.value = `${active.source}:${active.pack_id}`;
    }
    updateLanguages(active?.language || '');
    saveButton.disabled = !selectedPack();
    packSelect.addEventListener('change', () => {
        updateLanguages();
        saveButton.disabled = !selectedPack();
    });
    saveButton.addEventListener('click', async () => {
        const pack = selectedPack();
        if (!pack) {
            status.textContent = 'Choose an address pack first.';
            return;
        }
        saveButton.disabled = true;
        status.textContent = 'Applying address pack...';
        try {
            const response = await geoStudioFetch(apiEndpoints.selectPack(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    pack_id: pack.pack_id,
                    source: pack.source,
                    language: languageSelect.value,
                }),
            });
            const result = await readGeoStudioJson(response);
            if (!response.ok || result.success !== true) throw new Error(result.error || 'Could not apply the address pack.');
            window.location.reload();
        } catch (error) {
            status.textContent = error.message;
            saveButton.disabled = false;
        }
    });

    controls.append(packField, languageField, saveButton);
    panel.append(heading, controls, status);
    root.replaceChildren(panel);
    if (packs.length === 0) {
        packSelect.disabled = true;
        languageSelect.disabled = true;
        saveButton.disabled = true;
        status.textContent = 'No address packs are available. Create or import one in Geo Studio.';
    }
}
