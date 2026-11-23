import './bootstrap';

const frame = document.querySelector('[data-app-frame]');
const sidebar = document.querySelector('[data-sidebar]');
const collapseButton = document.querySelector('[data-sidebar-toggle]');
const menuButton = document.querySelector('[data-mobile-menu]');
const scrim = document.querySelector('[data-sidebar-scrim]');

if (frame && sidebar) {
    const storageKey = 'lumenix.sidebar.collapsed';
    const setCollapsed = (collapsed) => {
        frame.classList.toggle('sidebar-collapsed', collapsed);
        collapseButton?.setAttribute('aria-expanded', String(!collapsed));
        collapseButton?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        collapseButton?.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    };

    try {
        setCollapsed(localStorage.getItem(storageKey) === 'true');
    } catch {
        setCollapsed(false);
    }

    collapseButton?.addEventListener('click', () => {
        const collapsed = !frame.classList.contains('sidebar-collapsed');
        setCollapsed(collapsed);
        try { localStorage.setItem(storageKey, String(collapsed)); } catch { /* Storage can be disabled. */ }
    });

    const closeMobile = () => {
        frame.classList.remove('mobile-sidebar-open');
        menuButton?.setAttribute('aria-expanded', 'false');
        menuButton?.setAttribute('aria-label', 'Open navigation');
    };

    menuButton?.addEventListener('click', () => {
        const open = frame.classList.toggle('mobile-sidebar-open');
        menuButton.setAttribute('aria-expanded', String(open));
        menuButton.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    });
    scrim?.addEventListener('click', closeMobile);
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMobile));
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMobile(); });
    window.addEventListener('resize', () => { if (window.innerWidth > 900) closeMobile(); });
}
