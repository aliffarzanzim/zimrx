import { parseLanguageCodes, readLanguageNameFields, renderLanguageNameFields, updateLanguageSelect } from './languages.js';
import { registerLanguageManagerTarget } from './language-manager.js';

export function initializeBlankForm(depthEditor) {
    const form = document.getElementById('newPackForm');
    const languageInput = document.getElementById('blankLanguages');
    const defaultLanguage = document.getElementById('blankDefLang');
    const rootNamesContainer = document.getElementById('blankRootNamesFields');
    let draftRootNames = {};

    form.addEventListener('reset', () => {
        draftRootNames = {};
    });

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
            parseLanguageCodes(languageInput.value),
            defaultLanguage.value,
            draftRootNames,
            'blank-root-name'
        );
    }

    const updateLanguages = () => {
        collectRootNames();
        const languages = parseLanguageCodes(languageInput.value);
        const selectedLanguage = defaultLanguage.value;
        updateLanguageSelect(defaultLanguage, languages, selectedLanguage);
        depthEditor.render();
        renderRootNameFields();
    };
    languageInput.addEventListener('input', updateLanguages);
    defaultLanguage.addEventListener('change', () => {
        collectRootNames();
        depthEditor.render();
        renderRootNameFields();
    });
    updateLanguages();
    registerLanguageManagerTarget({
        languageInput,
        openButton: document.getElementById('btnManageBlankLanguages'),
        languageSummary: document.getElementById('blankLanguageSummary'),
        defaultLanguageInput: defaultLanguage,
        getDefaultLanguage: () => defaultLanguage.value,
    });
    depthEditor.render();

    document.getElementById('blankLicenseSelect').onchange = (e) => {
        const grp = document.getElementById('grpBlankCustomLicense');
        if (e.target.value === 'custom') {
            grp.hidden = false;
            document.getElementById('blankCustomLicense').focus();
        } else {
            grp.hidden = true;
        }
    };

    document.querySelectorAll('#blankPresetChips .preset-chip').forEach(btn => {
        btn.onclick = () => {
            const val = btn.getAttribute('data-val');
            const inp = document.getElementById('blankCodeLabel');
            if (inp) inp.value = val;
        };
    });

    function applyPackType(packType) {
        const isNational = packType === 'national';
        const codeLabelInput = document.getElementById('blankCodeLabel');
        if (isNational && codeLabelInput?.value === 'Block Code') {
            codeLabelInput.value = 'Postal Code';
        } else if (!isNational && codeLabelInput?.value === 'Postal Code') {
            codeLabelInput.value = 'Block Code';
        }
        depthEditor.setPackType(isNational ? 'national' : 'custom');
    }

    form.addEventListener('change', event => {
        if (event.target?.name !== 'blankPackType') return;
        applyPackType(event.target.value);
    });

    return { applyPackType };

}
