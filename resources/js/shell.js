/**
 * App shell — sidebar collapse, mobile drawer, notification panel.
 *
 * The server renders `data-sidebar` and `data-density` onto <html> and <body>,
 * so the shell is already in the right state on first paint. Everything here
 * changes that state in response to a click and then persists it in the
 * background.
 */

const MOBILE = '(max-width: 760px)';

function isMobile() {
    return window.matchMedia(MOBILE).matches;
}

function persist(payload) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!token) {
        return Promise.resolve();
    }

    const body = new FormData();
    body.set('_token', token);
    Object.entries(payload).forEach(([key, value]) => body.set(key, value));

    return fetch('/shell', {
        method: 'POST',
        body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
}

/* ── sidebar ─────────────────────────────────────────────────────────────── */

function setSidebar(state) {
    document.documentElement.dataset.sidebar = state;
    document.body.dataset.sidebar = state;

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', String(state === 'expanded'));
    });
}

function setMobileNav(open) {
    document.body.dataset.mobileNav = open ? 'open' : 'closed';

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', String(open));
    });
}

function initSidebar() {
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');

    if (toggles.length === 0) {
        return;
    }

    toggles.forEach((button) => {
        button.addEventListener('click', () => {
            // On a phone the same control opens the drawer instead of
            // collapsing a rail that is not on screen. The drawer is
            // deliberately not persisted: it is a transient overlay, and
            // reopening it on every page load would cover the page.
            if (isMobile()) {
                setMobileNav(document.body.dataset.mobileNav !== 'open');

                return;
            }

            const next = document.body.dataset.sidebar === 'collapsed' ? 'expanded' : 'collapsed';

            setSidebar(next);
            persist({ sidebar: next }).catch(() => {
                // The preference did not save; leave the UI as the user set it
                // for this page rather than snapping back mid-interaction.
            });
        });
    });

    document.querySelector('[data-mobile-scrim]')?.addEventListener('click', () => setMobileNav(false));

    // Leaving the drawer open while the viewport grows would strand the
    // scrim over a desktop layout.
    window.matchMedia(MOBILE).addEventListener('change', (event) => {
        if (!event.matches) {
            setMobileNav(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.dataset.mobileNav === 'open') {
            setMobileNav(false);
            document.querySelector('[data-sidebar-toggle]')?.focus();
        }
    });
}

/* ── notifications ───────────────────────────────────────────────────────── */

function initNotifications() {
    const wrap = document.querySelector('[data-notifications]');
    const toggle = wrap?.querySelector('[data-notif-toggle]');

    if (!wrap || !toggle) {
        return;
    }

    let hoverTimer = null;

    const isOpen = () => wrap.dataset.open === 'true';
    const setOpen = (open) => {
        wrap.dataset.open = open ? 'true' : 'false';
        toggle.setAttribute('aria-expanded', String(open));
    };

    // Click is the primary interaction, because it is the only one that works
    // on touch and from the keyboard.
    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(!isOpen());
    });

    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            setOpen(false);
            toggle.focus();
        }
    });

    // Closing on focus leaving the panel keeps keyboard users from tabbing
    // into content that is still visually open behind them.
    wrap.addEventListener('focusout', (event) => {
        if (!wrap.contains(event.relatedTarget)) {
            setOpen(false);
        }
    });

    // Hover-to-open is an enhancement for mice only. On touch there is no
    // hover, and binding it unconditionally makes the first tap open-and-close.
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        wrap.addEventListener('mouseenter', () => {
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(() => setOpen(true), 140);
        });
        wrap.addEventListener('mouseleave', () => {
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(() => setOpen(false), 220);
        });
    }
}

/* ── boot ────────────────────────────────────────────────────────────────── */

function init() {
    initSidebar();
    initNotifications();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
