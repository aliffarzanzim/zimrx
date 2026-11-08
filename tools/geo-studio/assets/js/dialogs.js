export function initializeDialogs() {
    const backdrops = [...document.querySelectorAll('.geo-modal-backdrop')];
    const opened = new Map();
    const previousInert = new Map();
    const top = () => [...opened.keys()].at(-1);
    const focusable = backdrop => [...backdrop.querySelectorAll('button, input, select, textarea, a[href], [tabindex="0"]')]
        .filter(element => !element.disabled && element.getClientRects().length > 0);

    function synchronize() {
        for (const backdrop of backdrops) {
            const visible = !backdrop.hidden;
            if (visible && !opened.has(backdrop)) {
                opened.set(backdrop, document.activeElement);
                queueMicrotask(() => { if (top() === backdrop) (focusable(backdrop)[0] || backdrop.firstElementChild).focus(); });
            } else if (!visible && opened.has(backdrop)) {
                const returnTo = opened.get(backdrop);
                opened.delete(backdrop);
                queueMicrotask(() => { if (returnTo?.isConnected && !returnTo.closest('[inert]')) returnTo.focus(); });
            }
        }
        const active = top();
        if (!active) {
            for (const [element, inert] of previousInert) element.inert = inert;
            previousInert.clear();
            return;
        }
        const desiredInert = new Map();
        for (let branch = active; branch && branch !== document.body; branch = branch.parentElement) {
            const parent = branch.parentElement;
            if (!parent) break;
            for (const sibling of parent.children) {
                if (sibling.tagName !== 'SCRIPT') desiredInert.set(sibling, sibling !== branch);
            }
        }
        for (const [element, inert] of previousInert) {
            if (!desiredInert.has(element)) {
                element.inert = inert;
                previousInert.delete(element);
            }
        }
        for (const [element, inert] of desiredInert) {
            if (!previousInert.has(element)) previousInert.set(element, element.inert);
            element.inert = inert;
        }
    }

    for (const backdrop of backdrops) {
        const card = backdrop.querySelector('.geo-modal-card');
        const title = card.querySelector('.geo-modal-header-nav > span, .geo-modal-header > span');
        if (title && !title.id) title.id = `${backdrop.id}-title`;
        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-modal', 'true');
        if (title) card.setAttribute('aria-labelledby', title.id);
        card.tabIndex = -1;
        new MutationObserver(synchronize).observe(backdrop, { attributes: true, attributeFilter: ['hidden'] });
    }
    document.addEventListener('keydown', event => {
        const action = event.target.closest('[role="button"]');
        if (action && (event.key === 'Enter' || event.key === ' ') && !event.defaultPrevented) {
            event.preventDefault();
            action.click();
            return;
        }
        const active = top();
        if (!active || event.defaultPrevented) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            if (active.getAttribute('aria-busy') !== 'true') active.querySelector('.geo-modal-close-btn')?.click();
        } else if (event.key === 'Tab') {
            const targets = focusable(active);
            if (!targets.length) { event.preventDefault(); return; }
            const index = targets.indexOf(document.activeElement);
            if (index < 0 || (!event.shiftKey && index === targets.length - 1) || (event.shiftKey && index === 0)) {
                event.preventDefault();
                targets[event.shiftKey ? targets.length - 1 : 0].focus();
            }
        }
    });
    synchronize();
}
