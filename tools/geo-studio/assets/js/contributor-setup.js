export function createContributorPreferences(storage) {
    const key = 'geo_studio_contributor_name';
    let name = '';
    try {
        storage ??= globalThis.localStorage;
        name = (storage.getItem(key) || '').trim();
    } catch {
        // Keep the name in memory when browser storage is unavailable.
    }
    return {
        get name() { return name; },
        set name(value) {
            name = String(value || '').trim();
            try {
                if (name) storage.setItem(key, name);
                else storage.removeItem(key);
            } catch {
                // Keep the preference for this document only.
            }
        },
    };
}

export function initializeFirstRun({ preferences }) {
    const page = document.getElementById('geoSetupPage');
    const application = document.getElementById('geoStudioApp');
    const form = document.getElementById('geoSetupForm');
    const contributorInput = document.getElementById('geoSetupContributor');
    const status = document.getElementById('geoSetupStatus');

    if (preferences.name) {
        application.inert = false;
        page.hidden = true;
        return false;
    }

    application.inert = true;
    page.hidden = false;
    contributorInput.focus();
    form.addEventListener('submit', event => {
        event.preventDefault();
        const name = contributorInput.value.trim();
        if (!name) {
            status.textContent = 'Enter your name to continue.';
            status.dataset.state = 'error';
            contributorInput.focus();
            return;
        }

        preferences.name = name;
        application.inert = false;
        page.hidden = true;
    });
    return true;
}
