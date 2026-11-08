import { apiEndpoints, geoStudioFetch, readGeoStudioJson } from './api-client.js';
import { createDepthEditor } from './depth-editor.js';
import { initializeBlankForm } from './blank-pack-form.js';
import { isLanguageCode, parseLanguageCodes, readLanguageNameFields } from './languages.js';

export function initializeNewPack({
    getActiveContributorName,
    setActiveContributorName,
    showToast,
    refreshCatalog,
    updateUrl,
    handleUrlRoute,
    openNewPackPage,
    closeNewPackPage,
    renderIcon,
}) {
    const form = document.getElementById('newPackForm');
    const createButton = document.getElementById('btnCreateNewPack');
    const page = document.getElementById('viewNewPack');
    const overwriteModal = document.getElementById('modalOverwriteConfirmBackdrop');
    const languageInput = document.getElementById('blankLanguages');
    const defaultLanguageInput = document.getElementById('blankDefLang');
    const depthEditor = createDepthEditor(
        document.getElementById('depthChainList'),
        document.getElementById('btnAddDepthLevel'),
        () => parseLanguageCodes(languageInput.value),
        () => defaultLanguageInput.value || 'en',
        renderIcon
    );
    const blankForm = initializeBlankForm(depthEditor);
    let overwriteRequestId = 0;
    let overwriteAction = null;

    function closeOverwriteDialog() {
        overwriteRequestId++;
        overwriteModal.hidden = true;
        overwriteAction = null;
    }

    async function confirmOverwriteIfExists(packId, name, proceed) {
        const requestId = ++overwriteRequestId;
        overwriteAction = null;
        overwriteModal.hidden = true;
        try {
            const response = await geoStudioFetch(apiEndpoints.listPacks());
            const result = await readGeoStudioJson(response);
            if (!response.ok || result.success !== true || !Array.isArray(result.packs)) {
                throw new Error(result.error || 'Unable to check existing packs.');
            }
            if (requestId !== overwriteRequestId) return;
            const existing = result.packs.find(pack => String(pack.pack_id).toUpperCase() === packId.toUpperCase());
            if (!existing) {
                proceed(null);
                return;
            }
            overwriteAction = () => proceed(existing.revision);
            document.getElementById('overwriteConfirmTarget').textContent = `${existing.name || name} (${packId})`;
            overwriteModal.hidden = false;
        } catch (error) {
            if (requestId === overwriteRequestId) showToast(`Cannot check existing packs: ${error.message}`);
        }
    }

    document.getElementById('btnCloseOverwriteConfirm').addEventListener('click', closeOverwriteDialog);
    document.getElementById('btnCancelOverwriteConfirm').addEventListener('click', closeOverwriteDialog);
    document.getElementById('btnProceedOverwriteConfirm').addEventListener('click', () => {
        const confirmed = overwriteAction;
        closeOverwriteDialog();
        if (typeof confirmed === 'function') confirmed();
    });

    let pending = false;

    function resetForm() {
        form.reset();
        languageInput.dispatchEvent(new Event('input', { bubbles: true }));
        const packType = form.querySelector('input[name="blankPackType"]:checked')?.value || 'national';
        blankForm.applyPackType(packType);
        document.getElementById('blankLicenseSelect').dispatchEvent(new Event('change'));
        document.getElementById('blankContributor').value = getActiveContributorName();
    }

    function openPage() {
        resetForm();
        if (page.hidden) return;
        document.getElementById('blankPackId').focus();
    }

    async function createPack() {
        if (pending) return;
        const packType = document.querySelector('input[name="blankPackType"]:checked')?.value || 'national';
        const packId = document.getElementById('blankPackId').value.trim().toUpperCase();
        const defaultLanguage = defaultLanguageInput.value.trim().toLowerCase();
        const languages = parseLanguageCodes(languageInput.value);
        const rootNames = Object.fromEntries(Object.entries(readLanguageNameFields(
            document.getElementById('blankRootNamesFields')
        )).filter(([language]) => languages.includes(language)));
        const name = rootNames[defaultLanguage] || '';
        const contributor = document.getElementById('blankContributor').value.trim() || getActiveContributorName();
        const codeLabel = document.getElementById('blankCodeLabel').value.trim();

        if (!/^[A-Z0-9_-]{2,32}$/.test(packId)) {
            showToast('Enter a pack ID using 2-32 letters, numbers, hyphens, or underscores.');
            return;
        }
        if (packType === 'national' && !/^[A-Z]{2}$/.test(packId)) {
            showToast('Use the two-letter country code as the ID for a national pack.');
            return;
        }
        if (!name) {
            showToast(`Enter a root name in the default language (${defaultLanguage}).`);
            document.querySelector(`#blankRootNamesFields [data-language="${CSS.escape(defaultLanguage)}"]`)?.focus();
            return;
        }
        const missingRootLanguage = languages.find(language => !rootNames[language]);
        if (missingRootLanguage) {
            showToast(`Enter a root name in ${missingRootLanguage}.`);
            document.querySelector(`#blankRootNamesFields [data-language="${CSS.escape(missingRootLanguage)}"]`)?.focus();
            return;
        }
        if (!isLanguageCode(defaultLanguage)
            || !languages.includes(defaultLanguage)
            || languages.some(language => !isLanguageCode(language))) {
            showToast('Add valid language codes and include the default language in the list.');
            return;
        }

        const licenseSelect = document.getElementById('blankLicenseSelect');
        const customLicense = document.getElementById('blankCustomLicense').value.trim();
        if (licenseSelect.value === 'custom' && customLicense === '') {
            showToast('Enter the custom license name.');
            document.getElementById('blankCustomLicense').focus();
            return;
        }
        const license = licenseSelect.value === 'custom' ? customLicense : licenseSelect.value;
        const body = new FormData();
        body.append('pack_id', packId);
        body.append('pack_type', packType);
        body.append('code_label', codeLabel);
        body.append('root_names', JSON.stringify(rootNames));
        body.append('default_lang', defaultLanguage);
        body.append('languages', languages.join(','));
        body.append('contributor', contributor);
        body.append('license', license);
        body.append('notes', document.getElementById('blankNotes').value.trim());
        body.append('depth_levels', JSON.stringify(depthEditor.getLevels()));

        confirmOverwriteIfExists(packId, name, async overwriteRevision => {
            const overwriteConfirmed = typeof overwriteRevision === 'string';
            body.append('overwrite_confirmed', overwriteConfirmed ? '1' : '0');
            body.append('overwrite_revision', overwriteRevision || '');
            pending = true;
            page.setAttribute('aria-busy', 'true');
            createButton.disabled = true;
            try {
                const response = await geoStudioFetch(apiEndpoints.createPack(), { method: 'POST', body });
                const result = await readGeoStudioJson(response);
                if (!response.ok || result.success !== true) throw new Error(result.error || 'Unable to create the region pack.');
                setActiveContributorName(contributor);
                await refreshCatalog();
                showToast(result.message || `Created ${name} (${packId}).`);
                updateUrl(packId, '', true);
                await handleUrlRoute({ reload: true });
            } catch (error) {
                showToast(`Region creation failed: ${error.message}`);
            } finally {
                pending = false;
                page.removeAttribute('aria-busy');
                createButton.disabled = false;
            }
        });
    }

    document.getElementById('btnHomeNew').addEventListener('click', () => { void openNewPackPage(); });
    document.getElementById('btnCancelNewPack').addEventListener('click', () => closeNewPackPage());
    document.getElementById('btnCancelNewPackFooter').addEventListener('click', () => closeNewPackPage());
    createButton.addEventListener('click', () => { void createPack(); });
    window.addEventListener('geo-studio:new-pack-opened', openPage);

}
