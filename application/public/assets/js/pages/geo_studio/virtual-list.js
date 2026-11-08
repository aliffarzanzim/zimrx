const activeLists = new WeakMap();

export function disposeVirtualList(container) {
    activeLists.get(container)?.();
    activeLists.delete(container);
}

export function renderVirtualList({ container, items, renderItem, rowHeight = 26, selectedIndex = -1 }) {
    disposeVirtualList(container);
    container.replaceChildren();
    if (items.length <= 500) {
        const fragment = document.createDocumentFragment();
        items.forEach(item => fragment.appendChild(renderItem(item)));
        container.appendChild(fragment);
        return;
    }
    const spacer = document.createElement('div');
    spacer.className = 'geo-virtual-spacer';
    spacer.style.height = `${items.length * rowHeight}px`;
    const window = document.createElement('div');
    window.className = 'geo-virtual-window';
    spacer.appendChild(window);
    container.appendChild(spacer);
    if (selectedIndex >= 0) {
        const top = selectedIndex * rowHeight;
        if (top < container.scrollTop || top + rowHeight > container.scrollTop + container.clientHeight) {
            container.scrollTop = top;
        }
    }
    let frame = 0;
    function draw() {
        frame = 0;
        const start = Math.max(0, Math.min(items.length - 1, Math.floor(container.scrollTop / rowHeight)) - 8);
        const end = Math.min(items.length, start + Math.ceil((container.clientHeight || 400) / rowHeight) + 16);
        const focusedKey = window.contains(document.activeElement) ? document.activeElement.dataset.path : null;
        window.replaceChildren();
        window.style.transform = `translateY(${start * rowHeight}px)`;
        const fragment = document.createDocumentFragment();
        for (let index = start; index < end; index++) fragment.appendChild(renderItem(items[index]));
        window.appendChild(fragment);
        if (focusedKey) window.querySelector(`[data-path="${focusedKey}"]`)?.focus({ preventScroll: true });
    }
    function schedule() { if (!frame) frame = requestAnimationFrame(draw); }
    container.addEventListener('scroll', schedule, { passive: true });
    const resize = new ResizeObserver(schedule);
    resize.observe(container);
    activeLists.set(container, () => {
        container.removeEventListener('scroll', schedule);
        resize.disconnect();
        if (frame) cancelAnimationFrame(frame);
    });
    draw();
}
