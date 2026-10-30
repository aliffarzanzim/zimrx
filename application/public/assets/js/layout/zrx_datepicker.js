// global date picker
(function (window, document) {
    'use strict';

    const defaultConfig = {
        dateFormat: 'd/m/Y',
        allowInput: true,
        disableMobile: true
    };

    const ZimRxDatePicker = {
        defaultConfig,

        init(root = document, customConfig = {}) {
            if (typeof flatpickr !== 'function') return;

            const target = root || document;
            const inputs = (target instanceof Element && target.matches('.custom-date-picker'))
                ? [target]
                : Array.from(target.querySelectorAll('.custom-date-picker'));

            inputs.forEach(input => {
                if (input._flatpickr) return;

                const cfg = Object.assign({}, defaultConfig, customConfig, {
                    clickOpens: !input.readOnly,
                    onChange: (selectedDates, dateStr, instance) => {
                        instance.input.dispatchEvent(new Event('input', { bubbles: true }));
                        instance.input.dispatchEvent(new Event('change', { bubbles: true }));
                        if (typeof customConfig.onChange === 'function') {
                            customConfig.onChange(selectedDates, dateStr, instance);
                        }
                    }
                });

                flatpickr(input, cfg);
            });
        }
    };

    window.ZimRxDatePicker = ZimRxDatePicker;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ZimRxDatePicker.init());
    } else {
        ZimRxDatePicker.init();
    }
})(window, document);
