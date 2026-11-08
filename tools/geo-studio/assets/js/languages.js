const LANGUAGE_CODE = /^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/;

export function isLanguageCode(value) {
    return typeof value === 'string' && value.length <= 35 && LANGUAGE_CODE.test(value);
}

export function parseLanguageCodes(value) {
    return [...new Set(value.toLowerCase().split(/[\s,]+/).filter(isLanguageCode))];
}

export function orderLanguagesByDefault(languages, defaultLanguage = '') {
    const uniqueLanguages = [...new Set(languages)];
    return uniqueLanguages.includes(defaultLanguage)
        ? [defaultLanguage, ...uniqueLanguages.filter(language => language !== defaultLanguage)]
        : uniqueLanguages;
}

export function renderLanguageNameFields(container, languages, defaultLanguage, names = {}, idPrefix = 'language-name') {
    const orderedLanguages = orderLanguagesByDefault(languages, defaultLanguage);
    container.replaceChildren();
    orderedLanguages.forEach(language => {
        const group = document.createElement('div');
        group.className = 'geo-form-group';

        const inputId = `${idPrefix}-${language.replace(/[^a-z0-9-]/g, '-')}`;
        const label = document.createElement('label');
        label.className = 'geo-label';
        label.htmlFor = inputId;
        label.textContent = `${language.toUpperCase()} Name`;

        const input = document.createElement('input');
        input.type = 'text';
        input.id = inputId;
        input.className = 'geo-input geo-language-name-input';
        input.dataset.language = language;
        input.value = typeof names[language] === 'string' ? names[language] : '';
        input.required = true;
        group.append(label, input);
        container.appendChild(group);
    });
}

export function readLanguageNameFields(container) {
    return Object.fromEntries([...container.querySelectorAll('.geo-language-name-input')]
        .map(input => [input.dataset.language, input.value.trim()])
        .filter(([, value]) => value !== ''));
}

export function updateLanguageSelect(select, languages, selectedLanguage = '') {
    const uniqueLanguages = [...new Set(languages.filter(language =>
        isLanguageCode(language)
    ))];
    const options = document.createDocumentFragment();
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select a language';
    options.appendChild(placeholder);

    orderLanguagesByDefault(uniqueLanguages, selectedLanguage).forEach(language => {
        const option = document.createElement('option');
        option.value = language;
        option.textContent = language;
        options.appendChild(option);
    });

    select.replaceChildren(options);
    select.value = uniqueLanguages.includes(selectedLanguage) ? selectedLanguage : (uniqueLanguages[0] || '');
}
