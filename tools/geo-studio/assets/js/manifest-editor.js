import { isLanguageCode, parseLanguageCodes, readLanguageNameFields, renderLanguageNameFields, updateLanguageSelect } from './languages.js';
import { registerLanguageManagerTarget } from './language-manager.js';
import { getRootPlaces, hasValidPlaceTree } from './editor-utils.js';
import { getRootNames, setRootNames } from './pack-names.js';

export function initializeManifestEditor({
    getDataset,
    getContributorName,
    updateStats,
    refreshTypeLabels,
    updateCodeLabelUI,
    setDirty,
    showToast,
    renderIcon,
    refreshNameFields,
}) {
    const modal = document.getElementById('modalManifestBackdrop');
    const licenseSelect = document.getElementById('manLicenseSelect');
    const languagesInput = document.getElementById('manLanguages');
    const defaultLanguageSelect = document.getElementById('manDefaultLanguage');
    const rootNamesContainer = document.getElementById('manRootNamesFields');
    let draftContributors = [];
    let draftRootNames = {};

    function collectRootNames() {
        rootNamesContainer.querySelectorAll('.geo-language-name-input').forEach(input => {
            delete draftRootNames[input.dataset.language];
        });
        draftRootNames = { ...draftRootNames, ...readLanguageNameFields(rootNamesContainer) };
    }

    function renderRootNameFields() {
        collectRootNames();
        renderLanguageNameFields(
            rootNamesContainer,
            parseLanguageCodes(languagesInput.value),
            defaultLanguageSelect.value,
            draftRootNames,
            'man-root-name'
        );
    }

    function syncDefaultLanguage(selectedLanguage = defaultLanguageSelect.value) {
        updateLanguageSelect(defaultLanguageSelect, parseLanguageCodes(languagesInput.value), selectedLanguage);
        renderRootNameFields();
    }

    languagesInput.addEventListener('input', () => {
        syncDefaultLanguage();
    });

    defaultLanguageSelect.addEventListener('change', () => {
        renderRootNameFields();
    });

    registerLanguageManagerTarget({
        languageInput: languagesInput,
        openButton: document.getElementById('btnManageManifestLanguages'),
        languageSummary: document.getElementById('manLanguageSummary'),
        defaultLanguageInput: defaultLanguageSelect,
        getDefaultLanguage: () => defaultLanguageSelect.value,
    });

    function renderContributors(contributors) {
        const container = document.getElementById('manContributorsChips');
        container.replaceChildren();
        contributors.forEach((contributor, index) => {
            const chip = document.createElement('span');
            const isCurrentContributor = contributor === getContributorName().trim();
            chip.className = isCurrentContributor ? 'geo-chip geo-chip-current' : 'geo-chip';
            chip.textContent = contributor;

            if (isCurrentContributor) {
                container.appendChild(chip);
                return;
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'geo-chip-del';
            remove.title = 'Remove Contributor';
            remove.setAttribute('aria-label', `Remove contributor ${contributor}`);
            remove.innerHTML = renderIcon('x', 11);
            remove.onclick = () => {
                contributors.splice(index, 1);
                renderContributors(contributors);
            };
            chip.appendChild(remove);
            container.appendChild(chip);
        });
    }

    document.getElementById('btnOpenManifest').onclick = () => {
        const dataset = getDataset();
        if (!dataset) return;
        const manifest = dataset.manifest || {};
        document.getElementById('manAuthor').value = manifest.author || '';
        document.getElementById('manVersion').value = manifest.version || '1.0.0';
        languagesInput.value = (manifest.languages || ['en']).join(', ');
        rootNamesContainer.replaceChildren();
        draftRootNames = getRootNames(manifest);
        syncDefaultLanguage(manifest.default_lang || 'en');
        languagesInput.dispatchEvent(new Event('input', { bubbles: true }));
        const licenses = ['CC-BY-4.0', 'CC0-1.0', 'ODbL-1.0', 'MIT', 'Apache-2.0'];
        const license = manifest.license || 'CC-BY-4.0';
        const customGroup = document.getElementById('grpManCustomLicense');
        const customLicense = document.getElementById('manCustomLicense');
        if (licenses.includes(license)) {
            licenseSelect.value = license;
            customGroup.hidden = true;
            customLicense.value = '';
        } else {
            licenseSelect.value = 'custom';
            customGroup.hidden = false;
            customLicense.value = license;
        }

        document.getElementById('manCodeLabel').value = manifest.code_label || (manifest.pack_type === 'custom' ? 'Block Code' : 'Postal Code');
        document.getElementById('manReleaseDate').value = manifest.release_date || new Date().toISOString().slice(0, 10);
        document.getElementById('manLastUpdated').value = manifest.last_updated || new Date().toISOString().slice(0, 10);
        document.getElementById('manNotes').value = manifest.notes || '';
        const currentContributor = getContributorName().trim();
        draftContributors = [...new Set([
            ...(manifest.contributors || []),
            ...(currentContributor ? [currentContributor] : []),
        ])];
        renderContributors(draftContributors);
        modal.hidden = false;
    };

    document.getElementById('btnAddContributorChip').onclick = () => {
        const dataset = getDataset();
        const input = document.getElementById('inpAddContributor');
        const name = input.value.trim();
        if (!dataset || !name) return;

        if (!draftContributors.includes(name)) {
            draftContributors.push(name);
        }
        input.value = '';
        renderContributors(draftContributors);
    };

    document.getElementById('btnApplyManifest').onclick = () => {
        const dataset = getDataset();
        if (!dataset) return;
        const manifest = dataset.manifest || (dataset.manifest = {});
        const customLicense = document.getElementById('manCustomLicense').value.trim();
        if (licenseSelect.value === 'custom' && customLicense === '') {
            alert('Enter the custom license name.');
            document.getElementById('manCustomLicense').focus();
            return;
        }
        const rawLanguages = languagesInput.value.toLowerCase().split(/[\s,]+/).filter(Boolean);
        const languages = [...new Set(rawLanguages)];
        const defaultLanguage = defaultLanguageSelect.value;
        if (!isLanguageCode(defaultLanguage) || !languages.includes(defaultLanguage) || languages.some(language => !isLanguageCode(language))) {
            alert('Add valid language codes and select the default language from the list.');
            return;
        }
        if (languages.length > 64) {
            alert('A pack can declare at most 64 languages.');
            return;
        }
        const orderedPackLanguages = [defaultLanguage, ...languages.filter(language => language !== defaultLanguage)];
        languagesInput.value = orderedPackLanguages.join(', ');
        languagesInput.dispatchEvent(new Event('input', { bubbles: true }));
        collectRootNames();
        const rootNames = {};
        for (const language of orderedPackLanguages) {
            const name = draftRootNames[language]?.trim() || '';
            if (!name) {
                alert(`Enter a pack root name in ${language}.`);
                [...rootNamesContainer.querySelectorAll('.geo-language-name-input')]
                    .find(input => input.dataset.language === language)?.focus();
                return;
            }
            rootNames[language] = name;
        }

        if (!hasValidPlaceTree(dataset)) {
            alert('Cannot update the manifest because the place hierarchy is invalid. Every place must have a places array. Reload the pack before editing it.');
            return;
        }

        let missingDefaultName = false;
        const checkPlaceNames = nodes => nodes.forEach(node => {
            if (!node.names?.[defaultLanguage]?.trim()) missingDefaultName = true;
            checkPlaceNames(node.places);
        });
        checkPlaceNames(getRootPlaces(dataset));
        for (const level of dataset.hierarchy?.levels || []) {
            for (const type of level.types || []) {
                if (!type.names?.[defaultLanguage]?.trim()) missingDefaultName = true;
            }
        }
        if (missingDefaultName) {
            alert(`Add a ${defaultLanguage} name to every place and hierarchy type before changing the default language.`);
            return;
        }

        const retainPackLanguages = names => Object.fromEntries(
            Object.entries(names || {}).filter(([language]) => orderedPackLanguages.includes(language))
        );
        const retainPlaceLanguages = nodes => nodes.forEach(node => {
            node.names = retainPackLanguages(node.names);
            retainPlaceLanguages(node.places);
        });
        retainPlaceLanguages(getRootPlaces(dataset));
        for (const level of dataset.hierarchy?.levels || []) {
            for (const type of level.types || []) type.names = retainPackLanguages(type.names);
        }

        manifest.languages = orderedPackLanguages;
        manifest.default_lang = defaultLanguage;
        manifest.author = document.getElementById('manAuthor').value.trim();
        setRootNames(manifest, rootNames);
        manifest.version = document.getElementById('manVersion').value.trim();

        manifest.license = licenseSelect.value === 'custom' ? customLicense : licenseSelect.value;
        manifest.code_label = document.getElementById('manCodeLabel').value.trim() || 'Postal Code';
        manifest.notes = document.getElementById('manNotes').value.trim();
        manifest.contributors = [...draftContributors];

        const contributor = getContributorName().trim();
        if (contributor) {
            if (!manifest.contributors.includes(contributor)) {
                manifest.contributors.push(contributor);
            }
        }

        updateStats();
        refreshTypeLabels?.();
        updateCodeLabelUI();
        refreshNameFields?.();
        modal.hidden = true;
        setDirty(true);
        showToast('Applied manifest settings');
    };

    document.getElementById('btnCloseManifestModal').onclick = () => { modal.hidden = true; };
    document.getElementById('btnCancelManifest').onclick = () => { modal.hidden = true; };
    licenseSelect.onchange = event => {
        const customGroup = document.getElementById('grpManCustomLicense');
        if (event.target.value === 'custom') {
            customGroup.hidden = false;
            document.getElementById('manCustomLicense').focus();
        } else {
            customGroup.hidden = true;
        }
    };
}
