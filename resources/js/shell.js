
// ==========================================
// EQUIP ME MAYBE
// Application Shell and Accessibility
// ==========================================

const shell = document.documentElement;

const media = window.matchMedia(
    '(prefers-color-scheme: dark)'
);


// ==========================================
// Theme Management
// ==========================================

const applyTheme = () => {

    const theme = shell.dataset.theme || 'system';

    const useDarkTheme =
        theme === 'dark'
        || (
            theme === 'system'
            && media.matches
        );

    shell.classList.toggle(
        'dark',
        useDarkTheme
    );

};

applyTheme();

media.addEventListener(
    'change',
    applyTheme
);


// ==========================================
// Accessible Global Search Shortcut
// Alt + /
// ==========================================

document.addEventListener('keydown', (event) => {

    if (
        event.code !== 'Slash'
        || !event.altKey
        || event.ctrlKey
        || event.metaKey
        || event.shiftKey
        || event.defaultPrevented
        || event.isComposing
        || event.repeat
    ) {
        return;
    }

    // Do not interfere with open modal dialogs.

    if (document.querySelector('dialog[open]')) {
        return;
    }

    const target = event.target;

    // Do not interrupt users typing in form controls.

    if (
        target instanceof Element
        && (
            target.closest(
                'input, textarea, select, [role="textbox"]'
            )
            || target.isContentEditable
        )
    ) {
        return;
    }

    const inputs = Array.from(
        document.querySelectorAll('[data-global-search]')
    );

    const input = inputs.find((element) => {

        return !element.disabled
            && !element.closest('[inert]')
            && !element.closest('[hidden]')
            && element.getClientRects().length > 0
            && getComputedStyle(element).visibility === 'visible';

    });

    if (input) {

        event.preventDefault();

        input.focus();

    }

});


// ==========================================
// Clear Saved Filter Preferences
// ==========================================

document.addEventListener('click', (event) => {

    const link =
        event.target instanceof Element

            ? event.target.closest(
                'form[data-instant-filters] a'
            )

            : null;

    if (!link) {
        return;
    }

    const label = link.textContent.trim();

    if (
        label !== 'Clear filter'
        && label !== 'Clear filters'
    ) {
        return;
    }

    const url = new URL(
        link.href,
        location.href
    );

    if (
        url.origin !== location.origin
        || url.searchParams.has('model')
    ) {
        return;
    }

    url.searchParams.set(
        'clear',
        '1'
    );

    link.href = url.href;

});
