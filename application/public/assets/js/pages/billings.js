// Clinic billing and receipts management page handling payments, totals, and receipts.
document.addEventListener('DOMContentLoaded', () => {
    // Datepicker initialization
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#filter-from-date', { dateFormat: 'Y-m-d' });
        flatpickr('#filter-to-date', { dateFormat: 'Y-m-d' });
    }

    // Modal helper functions
    const openModal = (el) => {
        if (!el) return;
        el.hidden = false;
        el.removeAttribute('hidden');
        el.style.display = 'flex';
    };
    const closeModal = (el) => {
        if (!el) return;
        el.hidden = true;
        el.setAttribute('hidden', '');
        el.style.display = 'none';
    };

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const m = btn.closest('.billing-modal');
            if (m) closeModal(m);
        });
    });

    // Edit payment modal
    const editModal = document.getElementById('modal-edit-payment');
    const editFeeDisplay = document.getElementById('edit-display-fee');
    const editNetDisplay = document.getElementById('edit-display-net');
    const editDiscInput = document.getElementById('edit-discount');
    const editPaidInput = document.getElementById('edit-paid-amount');
    let currentEditFee = 0;

    const recalcEditTotals = () => {
        const disc = Math.max(0, parseFloat(editDiscInput.value) || 0);
        const net = Math.max(0, currentEditFee - disc);
        editNetDisplay.textContent = '৳' + net.toLocaleString();
    };

    if (editDiscInput) {
        editDiscInput.addEventListener('input', recalcEditTotals);
    }

    document.querySelectorAll('.btn-edit-payment').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = btn.dataset;
            document.getElementById('edit-payment-id').value = data.id;
            document.getElementById('edit-modal-subtitle').textContent = `Receipt: ${data.receipt} • ${data.patient}`;
            currentEditFee = parseFloat(data.fee) || 0;
            editFeeDisplay.textContent = '৳' + currentEditFee.toLocaleString();
            editDiscInput.value = data.disc || 0;
            document.getElementById('edit-discount-note').value = data.discNote || '';
            editPaidInput.value = data.paid || 0;
            document.getElementById('edit-payment-method').value = data.method || 'Cash';
            document.getElementById('edit-notes').value = data.notes || '';
            recalcEditTotals();
            openModal(editModal);
        });
    });

    const editForm = document.getElementById('form-edit-payment');
    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('btn-save-edit-payment');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            const formData = new FormData(editForm);
            formData.append('action', 'save_payment');

            try {
                const resp = await fetch('api/billings_api.php', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    window.location.reload();
                } else {
                    alert('Error saving payment: ' + (res.error || 'Unknown error'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Changes';
                }
            } catch (err) {
                alert('Network error saving payment: ' + err.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        });
    }

    // New transaction modal
    const newModal = document.getElementById('modal-new-billing');
    const newBtn = document.getElementById('btn-new-billing');
    if (newBtn && newModal) {
        newBtn.addEventListener('click', () => {
            openModal(newModal);
            setTimeout(() => document.getElementById('new-patient-name')?.focus(), 50);
        });
    }

    const newFeeInput = document.getElementById('new-fee');
    const newDiscInput = document.getElementById('new-discount');
    const newPaidInput = document.getElementById('new-paid-amount');
    const syncNewPaid = () => {
        const fee = Math.max(0, parseFloat(newFeeInput.value) || 0);
        const disc = Math.max(0, parseFloat(newDiscInput.value) || 0);
        newPaidInput.value = Math.max(0, fee - disc);
    };
    if (newFeeInput && newDiscInput) {
        newFeeInput.addEventListener('input', syncNewPaid);
        newDiscInput.addEventListener('input', syncNewPaid);
    }

    const newForm = document.getElementById('form-new-billing');
    if (newForm) {
        newForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('btn-save-new-billing');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            const formData = new FormData(newForm);
            formData.append('action', 'create_transaction');

            try {
                const resp = await fetch('api/billings_api.php', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    window.location.reload();
                } else {
                    alert('Error creating transaction: ' + (res.error || 'Unknown error'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Generate Invoice & Save';
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Generate Invoice & Save';
            }
        });
    }

    // Print receipt modal
    const receiptModal = document.getElementById('modal-receipt');
    document.querySelectorAll('.btn-print-receipt').forEach(btn => {
        btn.addEventListener('click', async () => {
            const paymentId = btn.dataset.id;
            try {
                const resp = await fetch(`api/billings_api.php?action=get_receipt&payment_id=${paymentId}`);
                const res = await resp.json();
                if (res.success && res.receipt) {
                    const r = res.receipt;
                    document.getElementById('rcpt-clinic-name').textContent = r.clinic_name;
                    document.getElementById('rcpt-doctor-title').textContent = `${r.doctor_name} (${r.doctor_qualifications}) • ${r.doctor_speciality}`;
                    document.getElementById('rcpt-invoice-no').textContent = r.receipt_no;
                    document.getElementById('rcpt-date').textContent = r.date;
                    document.getElementById('rcpt-patient-name').textContent = r.patient_name;
                    document.getElementById('rcpt-patient-sub').textContent = [r.reg_no ? `Reg: ${r.reg_no}` : '', r.mobile, r.age_gender].filter(Boolean).join(' • ');
                    document.getElementById('rcpt-service-item').textContent = r.service_type;
                    document.getElementById('rcpt-gross-fee').textContent = '৳' + r.gross_fee.toLocaleString();
                    
                    const discRow = document.getElementById('rcpt-discount-row');
                    if (r.discount > 0) {
                        discRow.style.display = '';
                        document.getElementById('rcpt-discount').textContent = '-৳' + r.discount.toLocaleString();
                        document.getElementById('rcpt-disc-note').textContent = r.discount_note ? `(${r.discount_note})` : '';
                    } else {
                        discRow.style.display = 'none';
                    }

                    document.getElementById('rcpt-net-payable').textContent = '৳' + r.net_payable.toLocaleString();
                    document.getElementById('rcpt-method').textContent = r.payment_method;
                    document.getElementById('rcpt-paid-amount').textContent = '৳' + r.paid_amount.toLocaleString();
                    document.getElementById('rcpt-due-amount').textContent = r.due_amount > 0 ? '৳' + r.due_amount.toLocaleString() : '৳0 (Nil)';
                    document.getElementById('rcpt-status-text').textContent = r.payment_status;
                    openModal(receiptModal);
                } else {
                    alert('Could not fetch receipt details: ' + (res.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Network error loading receipt: ' + err.message);
            }
        });
    });

    // CSV export
    const exportBtn = document.getElementById('btn-export-csv');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            const table = document.getElementById('transactions-table');
            if (!table) return;
            const rows = [];
            table.querySelectorAll('tr').forEach(tr => {
                const cols = [];
                tr.querySelectorAll('th, td').forEach((td, idx) => {
                    if (idx === 10) return; // skip action column
                    let text = td.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
                    text = text.replace(/"/g, '""');
                    cols.push(`"${text}"`);
                });
                if (cols.length) rows.push(cols.join(','));
            });

            const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(rows.join('\n'));
            const downloadLink = document.createElement('a');
            downloadLink.setAttribute('href', csvContent);
            downloadLink.setAttribute('download', `billings_export_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });
    }
});
