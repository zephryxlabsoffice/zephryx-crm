/**
 * Theme toggle — foundation spec §7.
 *
 * The toggle is a real <form method="post" action="/theme">, so it works with
 * JavaScript disabled (full page round-trip). This script upgrades it to an
 * in-place swap: flip `data-theme` immediately, then persist in the background.
 *
 * The server always renders `data-theme` on <html>, so there is no flash of the
 * wrong palette and no need for a blocking inline script — which also keeps the
 * Content-Security-Policy free of `unsafe-inline`.
 */

const THEMES = ['dark', 'light'];

function currentTheme() {
    const value = document.documentElement.dataset.theme;

    return THEMES.includes(value) ? value : 'dark';
}

function apply(form, theme) {
    document.documentElement.dataset.theme = theme;

    // Keep the no-JS fallback and the accessible label in step with what the
    // button would now do, since the page is not re-rendered.
    const next = theme === 'dark' ? 'light' : 'dark';
    const label = `Switch to ${next} mode`;

    const input = form.querySelector('input[name="theme"]');
    if (input) {
        input.value = next;
    }

    const button = form.querySelector('button');
    if (button) {
        button.title = label;
    }

    const description = form.querySelector('.sr-only');
    if (description) {
        description.textContent = label;
    }
}

function persist(form, theme) {
    const body = new FormData(form);
    body.set('theme', theme);

    return fetch(form.action, {
        method: 'POST',
        body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
}

function init() {
    const form = document.querySelector('[data-theme-form]');

    if (!form) {
        return;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const previous = currentTheme();
        const next = previous === 'dark' ? 'light' : 'dark';

        apply(form, next);

        persist(form, next).catch(() => {
            // The preference could not be stored; don't leave the user looking
            // at a theme that will revert on their next navigation.
            apply(form, previous);
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
