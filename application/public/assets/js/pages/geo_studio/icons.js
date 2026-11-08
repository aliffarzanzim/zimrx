export const GeoStudioIcon = Object.freeze({
    render(name, size = 14, attrs = {}) {
        if (!window.ZimRxIcon || typeof window.ZimRxIcon.render !== 'function') {
            return '';
        }
        return window.ZimRxIcon.render(name, size, attrs);
    },
});
