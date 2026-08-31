(() => {
    const select = document.getElementById('header-practice-profile-select');
    if (!select) return;

    select.addEventListener('change', async () => {
        const previous = select.dataset.activeProfile || '';
        select.disabled = true;
        try {
            const body = new FormData();
            body.set('action', 'activate');
            body.set('profile_id', select.value);
            const response = await fetch('api/practice_profiles.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': window.ZimRxCsrfToken || '' },
                body
            });
            const result = await response.json();
            if (!response.ok || result.ok !== true) throw new Error(result.error || 'Unable to switch practice.');
            select.dataset.activeProfile = select.value;
            window.location.reload();
        } catch (error) {
            select.value = previous;
            window.alert(error instanceof Error ? error.message : 'Unable to switch practice.');
        } finally {
            select.disabled = false;
        }
    });
})();
