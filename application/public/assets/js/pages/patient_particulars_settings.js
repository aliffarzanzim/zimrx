(function () {
    const grid = document.getElementById('config-fields-grid');
    if (!grid || !window.PatientParticulars) return;

    window.PatientParticulars.renderFieldPlacementOptions();

    let saving = false;
    const saveButton = document.getElementById('btn-save-field-config');
    const resetButton = document.getElementById('btn-reset-field-config');
    const runAction = async (button, action) => {
        if (saving || !button) return;
        saving = true;
        button.disabled = true;
        try {
            await action();
        } finally {
            saving = false;
            button.disabled = false;
        }
    };

    saveButton?.addEventListener('click', () => {
        runAction(saveButton, window.PatientParticulars.saveFieldPlacement);
    });
    resetButton?.addEventListener('click', () => {
        runAction(resetButton, window.PatientParticulars.resetFieldPlacement);
    });

    const customGrid = document.getElementById('custom-fields-grid');
    const addCustomButton = document.getElementById('btn-add-custom-field');
    const saveCustomButton = document.getElementById('btn-save-custom-fields');
    const exportCustomButton = document.getElementById('btn-export-custom-fields');
    const importCustomButton = document.getElementById('btn-import-custom-fields');
    const customPackageFile = document.getElementById('custom-fields-package-file');
    if (customGrid) {
        window.PatientParticulars.renderCustomFieldDefinitionOptions();
    }
    const customType = document.getElementById('new-custom-field-type');
    const customOptions = document.getElementById('new-custom-field-options-wrap');
    const syncCustomOptions = () => {
        if (customOptions) customOptions.hidden = customType?.value !== 'select';
    };
    customType?.addEventListener('change', syncCustomOptions);
    syncCustomOptions();
    addCustomButton?.addEventListener('click', () => {
        window.PatientParticulars.addCustomFieldDefinition();
    });
    saveCustomButton?.addEventListener('click', () => {
        runAction(saveCustomButton, window.PatientParticulars.saveCustomFieldDefinitions);
    });

    exportCustomButton?.addEventListener('click', () => {
        const definitions = window.PatientParticulars.collectCustomFieldDefinitions();
        const packageData = {
            format: 'zimrx-patient-fields',
            version: 1,
            fields: definitions
        };
        const blobUrl = URL.createObjectURL(new Blob([JSON.stringify(packageData, null, 2)], { type: 'application/json' }));
        const download = document.createElement('a');
        download.href = blobUrl;
        download.download = 'zimrx-patient-fields.json';
        document.body.append(download);
        download.click();
        download.remove();
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
    });

    importCustomButton?.addEventListener('click', () => customPackageFile?.click());
    customPackageFile?.addEventListener('change', async () => {
        const file = customPackageFile.files?.[0];
        const status = document.getElementById('custom-fields-settings-status');
        customPackageFile.value = '';
        if (!file) return;
        if (file.size > 65536) {
            if (status) status.textContent = 'Field packages must be 64 KB or smaller.';
            return;
        }

        let packageData;
        try {
            packageData = JSON.parse(await file.text());
        } catch (_) {
            if (status) status.textContent = 'The selected file is not valid JSON.';
            return;
        }
        if (!packageData || packageData.format !== 'zimrx-patient-fields'
            || packageData.version !== 1 || !Array.isArray(packageData.fields)) {
            if (status) status.textContent = 'This file is not a supported patient field package.';
            return;
        }
        if (!window.confirm('Replace the current custom patient field definitions with this package?')) return;

        const previous = window.ZimRxCustomPatientFields;
        window.ZimRxCustomPatientFields = packageData.fields;
        window.PatientParticulars.renderCustomFieldDefinitionOptions();
        const saved = await window.PatientParticulars.saveCustomFieldDefinitions();
        if (!saved) {
            window.ZimRxCustomPatientFields = previous;
            window.PatientParticulars.renderCustomFieldDefinitionOptions();
            window.PatientParticulars.renderCustomPatientFields();
        }
    });
})();
