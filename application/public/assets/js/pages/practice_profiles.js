(() => {
    const page = document.getElementById('practice-profiles-page');
    const status = document.getElementById('practice-profile-status');
    const createForm = document.getElementById('practice-profile-create-form');
    if (!page || !status || !createForm) return;

    const send = async (form, action, extra = {}) => {
        const body = new FormData(form);
        body.set('action', action);
        Object.entries(extra).forEach(([key, value]) => body.set(key, value));
        const response = await fetch('api/practice_profiles.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': window.ZimRxCsrfToken || '' },
            body
        });
        const result = await response.json();
        if (!response.ok || result.ok !== true) throw new Error(result.error || 'Unable to save practice profile.');
        return result;
    };

    const showStatus = (message, isError = false) => {
        status.textContent = message;
        status.dataset.state = isError ? 'error' : 'success';
    };

    page.querySelectorAll('[data-profile-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                await send(form, 'save');
                showStatus('Practice profile saved.');
            } catch (error) {
                showStatus(error instanceof Error ? error.message : 'Unable to save practice profile.', true);
            }
        });

        form.querySelector('[data-archive-profile]')?.addEventListener('click', async () => {
            if (!window.confirm('Deactivate this practice profile? Its records will remain stored.')) return;
            try {
                await send(form, 'archive');
                window.location.reload();
            } catch (error) {
                showStatus(error instanceof Error ? error.message : 'Unable to deactivate practice profile.', true);
            }
        });

        form.querySelector('[data-reactivate-profile]')?.addEventListener('click', async () => {
            try {
                await send(form, 'reactivate');
                window.location.reload();
            } catch (error) {
                showStatus(error instanceof Error ? error.message : 'Unable to reactivate practice profile.', true);
            }
        });

        form.querySelector('[data-remove-profile]')?.addEventListener('click', async () => {
            const name = form.elements.profile_name.value.trim();
            if (!window.confirm(`Remove "${name}" from profile selection and keep its records?`)) return;
            try {
                await send(form, 'remove');
                window.location.reload();
            } catch (error) {
                showStatus(error instanceof Error ? error.message : 'Unable to remove practice profile.', true);
            }
        });

        form.querySelector('[data-delete-profile-data]')?.addEventListener('click', async () => {
            const name = form.elements.profile_name.value.trim();
            if (!window.confirm(`Permanently delete "${name}" and all patient data owned by this profile? This cannot be undone.`)) return;
            const phrase = window.prompt(`Type DELETE ${name} to confirm permanent data deletion:`);
            if (phrase !== `DELETE ${name}`) {
                showStatus('Deletion cancelled. The confirmation text did not match.', true);
                return;
            }
            try {
                await send(form, 'delete_data', { confirmation: phrase });
                window.location.reload();
            } catch (error) {
                showStatus(error instanceof Error ? error.message : 'Unable to delete profile data.', true);
            }
        });
    });

    createForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const result = await send(createForm, 'save');
            const body = new FormData();
            body.set('action', 'activate');
            body.set('profile_id', String(result.profile_id));
            const response = await fetch('api/practice_profiles.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': window.ZimRxCsrfToken || '' },
                body
            });
            const activation = await response.json();
            if (!response.ok || activation.ok !== true) throw new Error(activation.error || 'Profile was added but could not be activated.');
            window.location.reload();
        } catch (error) {
            showStatus(error instanceof Error ? error.message : 'Unable to add practice profile.', true);
        }
    });
})();
