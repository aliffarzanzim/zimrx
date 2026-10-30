(function (window, document) {
    'use strict';

    let activeModal = null;
    let returnFocusTo = null;

    const FOCUSABLE = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function resolveModal(target) {
        if (typeof target === 'string') {
            return document.getElementById(target);
        }
        return target instanceof HTMLElement ? target : null;
    }

    function getFocusable(modal) {
        return Array.from(modal.querySelectorAll(FOCUSABLE)).filter((element) => {
            return !element.hidden && element.getClientRects().length > 0;
        });
    }

    function open(target) {
        const modal = resolveModal(target);
        if (!modal || !modal.matches('.zrx-modal-overlay')) {
            return false;
        }

        if (activeModal && activeModal !== modal) {
            close(activeModal);
        }

        if (modal.hidden) {
            returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        }
        modal.hidden = false;
        modal.classList.add('open', 'active');
        activeModal = modal;

        window.requestAnimationFrame(() => {
            const preferred = modal.querySelector('[data-zrx-modal-autofocus]');
            const focusTarget = preferred && !preferred.hidden && preferred.getClientRects().length > 0
                ? preferred
                : getFocusable(modal)[0];
            if (focusTarget instanceof HTMLElement) {
                focusTarget.focus();
            }
        });
        return true;
    }

    function close(target = activeModal) {
        const modal = resolveModal(target);
        if (!modal || !modal.matches('.zrx-modal-overlay')) {
            return false;
        }

        modal.hidden = true;
        modal.classList.remove('open', 'active');
        if (activeModal === modal) {
            activeModal = null;
            if (returnFocusTo && returnFocusTo.isConnected) {
                returnFocusTo.focus();
            }
            returnFocusTo = null;
        }
        modal.dispatchEvent(new CustomEvent('zrx:modalclosed', { bubbles: true }));
        return true;
    }

    document.addEventListener('click', (event) => {
        const openButton = event.target instanceof Element
            ? event.target.closest('[data-zrx-modal-open]')
            : null;
        if (openButton) {
            event.preventDefault();
            open(openButton.dataset.zrxModalOpen);
            return;
        }

        const closeButton = event.target instanceof Element
            ? event.target.closest('[data-zrx-modal-close]')
            : null;
        if (closeButton) {
            const modal = closeButton.closest('.zrx-modal-overlay');
            if (modal) {
                event.preventDefault();
                close(modal);
                return;
            }
        }

        if (activeModal && event.target === activeModal) {
            close(activeModal);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!activeModal) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close(activeModal);
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = getFocusable(activeModal);
        if (focusable.length === 0) {
            event.preventDefault();
            activeModal.querySelector('[role="dialog"]')?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !activeModal.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !activeModal.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });

    window.ZimRxModal = Object.freeze({ open, close });
})(window, document);
