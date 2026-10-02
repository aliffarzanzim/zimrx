// Appointment settings page handling factory reset confirmation and default values.
document.addEventListener('DOMContentLoaded', () => {
    const resetBtn = document.getElementById('factory-reset-btn');
    const modal = document.getElementById('apt-confirm-modal');
    const cancelBtn = document.getElementById('confirm-reset-cancel');
    const proceedBtn = document.getElementById('confirm-reset-proceed');

    if (resetBtn && modal) {
        resetBtn.addEventListener('click', () => {
            modal.hidden = false;
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                modal.hidden = true;
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.hidden = true;
        });

        if (proceedBtn) {
            proceedBtn.addEventListener('click', () => {
                // Reset form fields to defaults
                document.getElementById('default_start_time').value = '14:00';
                document.getElementById('minutes_per_patient').value = '5';
                document.getElementById('blank_slots').value = '3';
                document.getElementById('visit_fee').value = '500';
                document.getElementById('revisit_fee').value = '400';
                document.getElementById('revisit_validity_days').value = '60';

                document.querySelectorAll('.weekday-closed-cb').forEach(cb => {
                    cb.checked = false;
                });
                document.querySelectorAll('.weekday-time-input').forEach(ti => {
                    ti.value = '14:00';
                });
                document.querySelectorAll('.token-cb').forEach(cb => {
                    cb.checked = true;
                });

                modal.hidden = true;

                // Auto-submit to persist defaults
                document.getElementById('appointment-settings-form').submit();
            });
        }
    }
});
