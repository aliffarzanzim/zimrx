(function () {
    const root = document.getElementById('visit-form-modules');
    const dataNode = document.getElementById('visit-form-definitions');
    if (!root || !dataNode) return;

    let definitions = JSON.parse(dataNode.textContent || '[]');
    const status = document.getElementById('visit-form-settings-status');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
    function render() {
        root.replaceChildren();
        definitions.forEach((module, moduleIndex) => {
            const card = document.createElement('section');
            card.className = 'visit-form-module-editor';
            card.dataset.index = String(moduleIndex);
            const moduleFields = Array.isArray(module.fields) ? module.fields : [];
            const fields = moduleFields.map((field, fieldIndex) => `
                <div class="visit-form-field-editor" data-field-index="${fieldIndex}">
                    <label>Field label<input data-property="label" maxlength="60" value="${escapeHtml(field.label)}"></label>
                    <label>Type<select data-property="type">${['text','textarea','number','date','select'].map(type => `<option value="${type}"${field.type === type ? ' selected' : ''}>${type === 'textarea' ? 'Paragraph' : type[0].toUpperCase() + type.slice(1)}</option>`).join('')}</select></label>
                    <label class="visit-form-options"${field.type === 'select' ? '' : ' hidden'}>Choices<input data-property="options" maxlength="500" value="${escapeHtml((field.options || []).join(', '))}" placeholder="Comma-separated choices"></label>
                    <label class="visit-form-check"><input type="checkbox" data-property="required"${field.required ? ' checked' : ''}>Required</label>
                    <label class="visit-form-check"><input type="checkbox" data-property="print"${field.print ? ' checked' : ''}>Include in print</label>
                    <button type="button" class="btn btn-outline btn-sm" data-action="remove-field">Remove field</button>
                </div>`).join('');
            card.innerHTML = `
                <header><label>Module name<input data-module-property="title" maxlength="60" value="${escapeHtml(module.title)}"></label>
                <label>Workspace column<select data-module-property="column"><option value="left"${module.column === 'left' ? ' selected' : ''}>Left</option><option value="right"${module.column === 'right' ? ' selected' : ''}>Right</option></select></label>
                <button type="button" class="btn btn-outline btn-sm" data-action="remove-module">Remove module</button></header>
                <div class="visit-form-fields">${fields}</div>
                <button type="button" class="btn btn-outline btn-sm" data-action="add-field">Add field</button>`;
            root.append(card);
        });
        if (!definitions.length) {
            const empty = document.createElement('p');
            empty.className = 'visit-form-empty';
            empty.textContent = 'No visit form modules configured.';
            root.append(empty);
        }
    }

    function collect() {
        return Array.from(root.querySelectorAll('.visit-form-module-editor')).map(card => {
            const current = definitions[Number(card.dataset.index)] || { key: '', fields: [] };
            return {
                key: current.key || '',
                title: card.querySelector('[data-module-property="title"]').value.trim(),
                column: card.querySelector('[data-module-property="column"]').value,
                fields: Array.from(card.querySelectorAll('.visit-form-field-editor')).map(fieldNode => {
                    const field = (Array.isArray(current.fields) ? current.fields : [])[Number(fieldNode.dataset.fieldIndex)] || {};
                    const type = fieldNode.querySelector('[data-property="type"]').value;
                    const options = fieldNode.querySelector('[data-property="options"]')?.value || '';
                    return {
                        key: field.key || '',
                        label: fieldNode.querySelector('[data-property="label"]').value.trim(),
                        type,
                        required: fieldNode.querySelector('[data-property="required"]').checked,
                        print: fieldNode.querySelector('[data-property="print"]').checked,
                        options: type === 'select' ? options.split(',').map(value => value.trim()).filter(Boolean) : []
                    };
                })
            };
        });
    }

    root.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const card = button.closest('.visit-form-module-editor');
        const moduleIndex = card ? Number(card.dataset.index) : -1;
        if (button.dataset.action === 'remove-module') {
            definitions = collect();
            definitions.splice(moduleIndex, 1);
        }
        if (button.dataset.action === 'add-field') {
            const current = collect();
            definitions = current;
            definitions[moduleIndex].fields.push({ key: '', label: '', type: 'text', required: false, print: false, options: [] });
        }
        if (button.dataset.action === 'remove-field') {
            const current = collect();
            current[moduleIndex].fields.splice(Number(button.closest('.visit-form-field-editor').dataset.fieldIndex), 1);
            definitions = current;
        }
        render();
    });
    root.addEventListener('change', event => {
        if (event.target.matches('[data-property="type"]')) {
            const options = event.target.closest('.visit-form-field-editor').querySelector('.visit-form-options');
            if (options) options.hidden = event.target.value !== 'select';
        }
    });

    document.getElementById('visit-form-add-module')?.addEventListener('click', () => {
        if (definitions.length >= 8) {
            status.textContent = 'A maximum of 8 modules is allowed.';
            return;
        }
        definitions = collect();
        definitions.push({ key: '', title: '', column: 'left', fields: [{ key: '', label: '', type: 'text', required: false, print: false, options: [] }] });
        render();
    });

    document.getElementById('visit-form-save')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        status.textContent = 'Saving...';
        try {
            const response = await fetch('api/save_interface_layout.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ visit_form_modules: collect() })
            });
            const result = await response.json();
            if (!response.ok || result.ok !== true || !Array.isArray(result.visit_form_modules)) {
                throw new Error(result.error || 'Could not save visit form modules.');
            }
            definitions = result.visit_form_modules;
            render();
            status.textContent = 'Visit form modules saved.';
        } catch (error) {
            status.textContent = error.message || 'Could not save visit form modules.';
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('visit-form-export')?.addEventListener('click', () => {
        const file = new Blob([JSON.stringify({ format: 'zimrx-visit-forms', version: 1, modules: collect() }, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(file);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'zimrx-visit-forms.json';
        link.click();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    const packageInput = document.getElementById('visit-form-package-file');
    document.getElementById('visit-form-import')?.addEventListener('click', () => packageInput?.click());
    packageInput?.addEventListener('change', async () => {
        const file = packageInput.files?.[0];
        packageInput.value = '';
        if (!file) return;
        if (file.size > 65536) { status.textContent = 'Form packages must be 64 KB or smaller.'; return; }
        try {
            const pack = JSON.parse(await file.text());
            if (pack?.format !== 'zimrx-visit-forms' || pack.version !== 1 || !Array.isArray(pack.modules)
                || pack.modules.length > 8
                || !pack.modules.every(module => module && typeof module === 'object'
                    && typeof module.title === 'string' && Array.isArray(module.fields)
                    && module.fields.length > 0 && module.fields.length <= 20
                    && module.fields.every(field => field && typeof field === 'object' && typeof field.label === 'string'))) {
                throw new Error('Unsupported visit form package.');
            }
            if (!window.confirm('Replace the current visit form modules with this package?')) return;
            definitions = pack.modules;
            render();
            status.textContent = 'Package loaded. Review it, then save modules to install these declarative forms.';
        } catch (error) {
            status.textContent = error.message || 'Could not read the package.';
        }
    });
    render();
})();
