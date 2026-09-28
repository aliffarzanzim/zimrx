/**
 * header_nav.js - ZimRx Top Navigation & Floating Menus Controller
 */
document.addEventListener('DOMContentLoaded', () => {
    // ── Floating Dropdown Menus Controller ────────────────────────
    const initFloatingMenu = (toggleId, panelId) => {
        const toggle = document.getElementById(toggleId);
        const menu = document.getElementById(panelId);
        if (!toggle || !menu) {
            return null;
        }

        let closeTimer = null;

        const positionMenu = () => {
            const rect = toggle.getBoundingClientRect();
            const menuWidth = menu.offsetWidth || 235;
            const left = Math.min(rect.left, window.innerWidth - menuWidth - 12);
            menu.style.top = `${Math.round(rect.bottom + 6)}px`;
            menu.style.left = `${Math.max(12, Math.round(left))}px`;
        };

        const openMenu = () => {
            window.clearTimeout(closeTimer);
            menus.forEach(m => {
                if (m && m.menu !== menu && !m.menu.hidden) {
                    m.closeMenu();
                }
            });
            menu.hidden = false;
            menu.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
            window.requestAnimationFrame(positionMenu);
        };

        const closeMenu = () => {
            window.clearTimeout(closeTimer);
            menu.classList.remove('open');
            menu.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };

        const scheduleClose = () => {
            window.clearTimeout(closeTimer);
            closeTimer = window.setTimeout(closeMenu, 140);
        };

        toggle.addEventListener('mouseenter', openMenu);
        toggle.addEventListener('mouseleave', scheduleClose);
        toggle.addEventListener('focus', openMenu);
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            if (menu.hidden) {
                openMenu();
                return;
            }
            closeMenu();
        });

        menu.addEventListener('mouseenter', () => window.clearTimeout(closeTimer));
        menu.addEventListener('mouseleave', scheduleClose);

        return { toggle, menu, openMenu, closeMenu, positionMenu };
    };

    const menus = [
        initFloatingMenu('template-menu-toggle', 'template-menu-panel'),
        initFloatingMenu('finance-menu-toggle', 'finance-menu-panel'),
        initFloatingMenu('setup-menu-toggle', 'setup-menu-panel'),
        initFloatingMenu('help-menu-toggle', 'help-menu-panel')
    ].filter(Boolean);

    document.addEventListener('click', (event) => {
        menus.forEach(m => {
            if (m && !m.menu.hidden) {
                if (event.target !== m.toggle && !m.toggle.contains(event.target) && !m.menu.contains(event.target)) {
                    m.closeMenu();
                }
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            menus.forEach(m => { if (m) m.closeMenu(); });
        }
    });

    window.addEventListener('resize', () => {
        menus.forEach(m => { if (m && !m.menu.hidden) m.positionMenu(); });
    });

    window.addEventListener('scroll', () => {
        menus.forEach(m => { if (m && !m.menu.hidden) m.positionMenu(); });
    }, true);

    // ── Top Navigation Horizontal Overflow & Scroll Controller ────
    const nav = document.getElementById('app-top-nav');
    const wrap = document.getElementById('top-nav-wrapper');
    const btnLeft = document.getElementById('nav-scroll-left');
    const btnRight = document.getElementById('nav-scroll-right');

    if (nav && wrap) {
        const updateNavOverflow = () => {
            const scrollWidth = nav.scrollWidth;
            const clientWidth = nav.clientWidth;
            const scrollLeft = nav.scrollLeft;
            const maxScroll = Math.max(0, scrollWidth - clientWidth);

            if (maxScroll <= 2) {
                wrap.classList.remove('has-overflow-left', 'has-overflow-right');
                if (btnLeft) {
                    btnLeft.hidden = true;
                    btnLeft.classList.remove('is-visible');
                }
                if (btnRight) {
                    btnRight.hidden = true;
                    btnRight.classList.remove('is-visible');
                }
                return;
            }

            const hasLeft = scrollLeft > 4;
            const hasRight = (scrollLeft + clientWidth) < (scrollWidth - 4);

            wrap.classList.toggle('has-overflow-left', hasLeft);
            wrap.classList.toggle('has-overflow-right', hasRight);

            if (btnLeft) {
                btnLeft.hidden = !hasLeft;
                btnLeft.classList.toggle('is-visible', hasLeft);
            }
            if (btnRight) {
                btnRight.hidden = !hasRight;
                btnRight.classList.toggle('is-visible', hasRight);
            }
        };

        updateNavOverflow();
        window.addEventListener('resize', updateNavOverflow);
        nav.addEventListener('scroll', updateNavOverflow, { passive: true });

        if (btnLeft) {
            btnLeft.addEventListener('click', (e) => {
                e.preventDefault();
                nav.scrollBy({ left: -140, behavior: 'smooth' });
            });
        }
        if (btnRight) {
            btnRight.addEventListener('click', (e) => {
                e.preventDefault();
                nav.scrollBy({ left: 140, behavior: 'smooth' });
            });
        }

        // Mouse wheel horizontal scrolling
        nav.addEventListener('wheel', (e) => {
            if (nav.scrollWidth > nav.clientWidth) {
                if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                    e.preventDefault();
                    nav.scrollLeft += e.deltaY;
                    updateNavOverflow();
                }
            }
        }, { passive: false });

        // Mouse click & drag to scroll
        let isDown = false;
        let startX = 0;
        let scrollStart = 0;
        nav.addEventListener('mousedown', (e) => {
            if (e.target.closest('.nav-button, .nav-link, button, a')) return;
            isDown = true;
            nav.classList.add('is-dragging');
            startX = e.pageX - nav.offsetLeft;
            scrollStart = nav.scrollLeft;
        });
        window.addEventListener('mouseup', () => {
            isDown = false;
            nav.classList.remove('is-dragging');
        });
        nav.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - nav.offsetLeft;
            const walk = (x - startX) * 1.5;
            nav.scrollLeft = scrollStart - walk;
            updateNavOverflow();
        });
    }
});
