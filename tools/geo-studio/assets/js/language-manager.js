import { isLanguageCode, orderLanguagesByDefault, parseLanguageCodes } from './languages.js';
let languageManager;

function createLanguageManager() {
    const backdrop = document.getElementById('modalBlankLanguagesBackdrop');
    const languageList = document.getElementById('blankLanguageList');
    const codeInput = document.getElementById('blankLanguageCodeInput');
    const addButton = document.getElementById('btnAddBlankLanguage');
    const status = document.getElementById('blankLanguageCodeStatus');
    const closeButtons = backdrop?.querySelectorAll('.geo-modal-close-btn, #btnDoneBlankLanguageModal');

    if (!backdrop || !languageList || !codeInput || !addButton || !status || !closeButtons?.length) return null;

    const targets = new Map();
    let activeTarget = null;

    function renderSummary(target) {
        const languages = orderLanguagesByDefault(
            parseLanguageCodes(target.languageInput.value),
            target.getDefaultLanguage?.() || ''
        );
        target.languageSummary.replaceChildren();
        languages.forEach(language => {
            const tag = document.createElement('span');
            tag.className = 'geo-language-tag';
            tag.textContent = language;
            tag.setAttribute('role', 'listitem');
            target.languageSummary.appendChild(tag);
        });
    }

    function renderLanguageList() {
        if (!activeTarget) return;
        const languages = orderLanguagesByDefault(
            parseLanguageCodes(activeTarget.languageInput.value),
            activeTarget.getDefaultLanguage?.() || ''
        );
        languageList.replaceChildren();

        languages.forEach((language, index) => {
            const row = document.createElement('div');
            row.className = 'geo-blank-language-row';
            row.setAttribute('role', 'listitem');

            const code = document.createElement('span');
            code.className = 'geo-blank-language-code';
            code.textContent = language;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'geo-btn-action';
            removeButton.textContent = 'Remove';
            removeButton.dataset.languageCode = language;
            removeButton.setAttribute('aria-label', `Remove ${language}`);
            removeButton.disabled = languages.length === 1;
            removeButton.title = removeButton.disabled ? 'A pack must include at least one language.' : `Remove ${language}`;
            removeButton.addEventListener('click', () => removeLanguage(language, index));

            row.append(code, removeButton);
            languageList.appendChild(row);
        });
    }

    function updateLanguages(languages) {
        if (!activeTarget) return;
        activeTarget.languageInput.value = languages.join(', ');
        activeTarget.languageInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function removeLanguage(language, index) {
        if (!activeTarget) return;
        const languages = parseLanguageCodes(activeTarget.languageInput.value).filter(item => item !== language);
        if (languages.length === 0) return;

        updateLanguages(languages);
        status.textContent = `${language} removed.`;
        const removeButtons = languageList.querySelectorAll('button[data-language-code]');
        const nextButton = removeButtons[Math.min(index, removeButtons.length - 1)];
        if (nextButton && !nextButton.disabled) nextButton.focus();
        else codeInput.focus();
    }

    function addLanguage() {
        const language = codeInput.value.trim().toLowerCase();
        if (!isLanguageCode(language)) {
            status.textContent = 'Enter a valid language code, such as bn or es-MX.';
            codeInput.focus();
            return;
        }

        const languages = parseLanguageCodes(activeTarget?.languageInput.value || '');
        if (languages.includes(language)) {
            status.textContent = 'That language is already in the pack.';
            codeInput.focus();
            return;
        }
        if (languages.length >= 64) {
            status.textContent = 'A pack can include at most 64 languages.';
            codeInput.focus();
            return;
        }

        updateLanguages([...languages, language]);
        codeInput.value = '';
        status.textContent = `${language} added.`;
        codeInput.focus();
    }

    addButton.addEventListener('click', addLanguage);
    codeInput.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        addLanguage();
    });
    closeButtons.forEach(button => {
        button.addEventListener('click', () => { backdrop.hidden = true; });
    });

    return {
        register(target) {
            if (targets.has(target.languageInput)) return;
            targets.set(target.languageInput, target);
            renderSummary(target);
            target.languageInput.addEventListener('input', () => {
                renderSummary(target);
                if (activeTarget === target) renderLanguageList();
            });
            target.defaultLanguageInput?.addEventListener('change', () => {
                renderSummary(target);
                if (activeTarget === target) renderLanguageList();
            });
            target.openButton.addEventListener('click', () => {
                activeTarget = target;
                codeInput.value = '';
                status.textContent = '';
                renderLanguageList();
                backdrop.hidden = false;
                codeInput.focus();
            });
        },
    };
}

export function registerLanguageManagerTarget(target) {
    if (!target?.languageInput || !target?.openButton || !target?.languageSummary) return;
    languageManager ??= createLanguageManager();
    languageManager?.register(target);
}
