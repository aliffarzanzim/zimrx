(function () {
    function collectVisitFormModules() {
        const values = {};
        const modules = [];
        document.querySelectorAll('.visit-form-module[data-module-key]').forEach(module => {
            const moduleKey = module.dataset.moduleKey;
            const fields = {};
            const printFields = [];
            module.querySelectorAll('[data-field-key]').forEach(field => {
                fields[field.dataset.fieldKey] = field.value;
                const label = field.closest('.visit-form-field');
                printFields.push({
                    key: field.dataset.fieldKey,
                    label: label?.dataset.fieldLabel || '',
                    value: field.value,
                    print: label?.dataset.fieldPrint === 'true'
                });
            });
            values[moduleKey] = fields;
            modules.push({ key: moduleKey, title: module.dataset.moduleTitle || '', fields: printFields });
        });
        return { values, modules };
    }

    const initialValuesNode = document.getElementById('visit-form-initial-values');
    if (initialValuesNode) {
        try {
            const initialValues = JSON.parse(initialValuesNode.textContent || '{}');
            document.querySelectorAll('.visit-form-module[data-module-key]').forEach(module => {
                const saved = initialValues[module.dataset.moduleKey];
                if (!saved || typeof saved !== 'object') return;
                module.querySelectorAll('[data-field-key]').forEach(field => {
                    if (Object.prototype.hasOwnProperty.call(saved, field.dataset.fieldKey)
                        && !field.value) {
                        field.value = String(saved[field.dataset.fieldKey]);
                    }
                });
            });
        } catch (error) {
            console.error('Could not restore visit form values.', error);
        }
    }

    window.collectVisitFormModules = collectVisitFormModules;
})();
