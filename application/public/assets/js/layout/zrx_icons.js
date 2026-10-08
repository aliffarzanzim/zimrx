// Global icon registry: renders SVG icons defined in window.ZimRxIconsMap (from zrx_icons.php).


(function () {
    window.ZimRxIcon = {
        render(name, size = 14, attrs = {}) {
            const key = (name || '').toLowerCase().trim();
            const icons = window.ZimRxIconsMap || {};
            const content = icons[key] || icons['hash'] || '';
            const cls = attrs.class || 'zrx-icon';
            const color = attrs.color || 'currentColor';
            const stroke = attrs.strokeWidth || attrs['stroke-width'] || '2';

            return `<svg class="${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="${stroke}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${content}</svg>`;
        }
    };
})();
