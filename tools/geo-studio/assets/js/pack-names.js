export function getRootNames(manifest = {}) {
    const rootNames = manifest.root_names;
    if (rootNames && typeof rootNames === 'object' && !Array.isArray(rootNames)) {
        return { ...rootNames };
    }
    return {};
}

export function getRootName(manifest = {}, language = null, fallback = '') {
    const names = getRootNames(manifest);
    const defaultLanguage = String(manifest.default_lang || 'en').toLowerCase();
    const preferredLanguage = String(language || defaultLanguage).toLowerCase();
    for (const code of [...new Set([preferredLanguage, defaultLanguage, 'en', ...Object.keys(names)])]) {
        const name = names[code];
        if (typeof name === 'string' && name.trim()) return name.trim();
    }
    return fallback;
}

export function setRootNames(manifest, names) {
    manifest.root_names = Object.fromEntries(
        Object.entries(names).filter(([, name]) => typeof name === 'string' && name.trim())
    );
}
