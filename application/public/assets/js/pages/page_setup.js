// Prescription print layout and page dimensions setup controller with live scaled preview.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('page-setup-form');
    const resetButton = document.getElementById('zps-factory-reset');
    const previewScale = document.getElementById('zps-preview-scale');
    const previewPage = document.getElementById('prev_page_size');
    const pxPerCm = 37.7952755906;
    const scale = 0.62;

    const toast = document.getElementById('print-setup-toast');
    const toastMessage = document.getElementById('print-setup-toast-message');
    const toastClose = document.getElementById('print-setup-toast-close');

    const showToast = (message, type = 'success') => {
        if (!toast || !toastMessage) return;
        toastMessage.textContent = message;
        toast.dataset.type = type;
        toast.hidden = false;
        if (toastClose) toastClose.focus();
    };

    toastClose?.addEventListener('click', () => {
        if (toast) toast.hidden = true;
    });

    toast?.addEventListener('click', (e) => {
        if (e.target === toast) {
            toast.hidden = true;
        }
    });

    const defaults = {
        header_height: '5.3',
        header_width: '21',
        pt_info_height: '1.6',
        pt_info_width: '21',
        pt_info_table_width: '90',
        left_height: '20',
        left_width: '9',
        right_height: '20',
        right_width: '11',
        footer_height: '2',
        footer_width: '21',
        page_height: '29.7',
        page_width: '21'
    };

    const controls = {
        header_height: ['.zps-preview-header', 'height', 'cm'],
        header_width: ['.zps-preview-header', 'width', 'cm'],
        pt_info_height: ['.zps-preview-patient', 'height', 'cm'],
        pt_info_width: ['.zps-preview-patient', 'width', 'cm'],
        pt_info_table_width: ['.zps-preview-patient table', 'width', '%'],
        left_height: ['.zps-preview-left', 'height', 'cm'],
        left_width: ['.zps-preview-left', 'width', 'cm'],
        right_height: ['.zps-preview-right', 'height', 'cm'],
        right_width: ['.zps-preview-right', 'width', 'cm'],
        footer_height: ['.zps-preview-footer', 'height', 'cm'],
        footer_width: ['.zps-preview-footer', 'width', 'cm'],
        page_height: ['.zps-preview-page', 'height', 'cm'],
        page_width: ['.zps-preview-page', 'width', 'cm']
    };

    const getInput = (id) => document.getElementById(id);
    const getNumber = (id, fallback) => {
        const input = getInput(id);
        const value = input ? parseFloat(input.value) : NaN;
        return Number.isFinite(value) ? value : fallback;
    };

    const clearActive = () => {
        document.querySelectorAll('.zps-preview-active').forEach(node => node.classList.remove('zps-preview-active'));
    };

    const setActive = (part) => {
        clearActive();
        document.querySelectorAll(`[data-preview-part="${part}"]`).forEach(node => node.classList.add('zps-preview-active'));
    };

    const applyPreview = () => {
        try {
            Object.entries(controls).forEach(([id, config]) => {
                const input = getInput(id);
                const value = input && input.value !== '' ? input.value : defaults[id];
                const unit = config[2] || 'cm';
                document.querySelectorAll(config[0]).forEach(node => {
                    node.style[config[1]] = `${value}${unit}`;
                });
            });
        } catch (e) {
            console.error('Error updating preview styles:', e);
        }

        try {
            const pageWidth = getNumber('page_width', 21);
            const pageHeight = getNumber('page_height', 29.7);

            // Update live dimensions display text
            const liveDim = document.getElementById('zps-live-dimensions');
            if (liveDim) {
                liveDim.textContent = `${pageWidth} x ${pageHeight} cm`;
            }

            if (previewScale) {
                previewScale.style.width = `${pageWidth * pxPerCm * scale}px`;
                previewScale.style.height = `${pageHeight * pxPerCm * scale}px`;
            }
            if (previewPage) {
                previewPage.style.transform = `scale(${scale})`;
                previewPage.style.transformOrigin = 'top left';
            }
        } catch (e) {
            console.error('Error scaling preview:', e);
        }
    };

    form.querySelectorAll('.zps-size-input').forEach(input => {
        input.addEventListener('focus', () => setActive(input.dataset.part || 'page'));
        input.addEventListener('input', () => {
            setActive(input.dataset.part || 'page');
            applyPreview();
        });
        input.addEventListener('change', applyPreview);
    });

    form.querySelectorAll('[data-part]').forEach(row => {
        row.addEventListener('mouseenter', () => setActive(row.dataset.part || 'page'));
    });

    resetButton.addEventListener('click', () => {
        const confirmModal = document.getElementById('page-setup-confirm-modal');
        const confirmYes = document.getElementById('confirm-page-reset-yes');
        const confirmCancel = document.getElementById('confirm-page-reset-cancel');

        if (confirmModal) {
            confirmModal.hidden = false;

            confirmYes.onclick = () => {
                confirmModal.hidden = true;
                Object.entries(defaults).forEach(([id, value]) => {
                    const input = getInput(id);
                    if (input) input.value = value;
                });
                setActive('page');
                applyPreview();
                showToast('Default sizes loaded. Save to apply.');
            };

            const closeConfirm = () => {
                confirmModal.hidden = true;
            };

            confirmCancel.onclick = closeConfirm;
            confirmModal.onclick = (e) => {
                if (e.target === confirmModal) {
                    closeConfirm();
                }
            };
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const response = await fetch('api/print_setup_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(new FormData(form))
            });
            const text = (await response.text()).trim();
            if (!response.ok || text !== '1') {
                throw new Error(text || 'Save failed');
            }
            showToast('Saved successfully');
        } catch (error) {
            showToast(error.message || 'Save failed', 'error');
        }
    });

    // Common page sizes modal
    const pageSizesModal = document.getElementById('page-sizes-modal');
    const pageSizesCloseX = document.getElementById('page-sizes-close-x');
    const pageSizesCloseBtn = document.getElementById('page-sizes-close-btn');
    const btnPageSizesHelp = document.getElementById('btn-page-sizes-help');

    if (btnPageSizesHelp) {
        btnPageSizesHelp.onclick = () => {
            if (pageSizesModal) pageSizesModal.hidden = false;
        };
    }

    const closePageSizesModal = () => {
        if (pageSizesModal) pageSizesModal.hidden = true;
    };

    if (pageSizesCloseX) pageSizesCloseX.onclick = closePageSizesModal;
    if (pageSizesCloseBtn) pageSizesCloseBtn.onclick = closePageSizesModal;

    pageSizesModal?.addEventListener('click', (e) => {
        if (e.target === pageSizesModal) {
            closePageSizesModal();
        }
    });

    document.querySelectorAll('.page-size-opt-btn').forEach(btn => {
        btn.onclick = () => {
            const w = btn.dataset.width;
            const h = btn.dataset.height;
            const widthInput = document.getElementById('page_width');
            const heightInput = document.getElementById('page_height');
            if (widthInput && heightInput) {
                widthInput.value = w;
                heightInput.value = h;
                // Trigger preview refresh
                widthInput.dispatchEvent(new Event('input', { bubbles: true }));
                widthInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            closePageSizesModal();
        };
    });

    applyPreview();
});
