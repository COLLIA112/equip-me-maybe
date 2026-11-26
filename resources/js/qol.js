
// ==========================================
// EQUIP ME MAYBE
// Quality of Life and Accessibility
// ==========================================

// GET filters remain independent from POST
// confirmation and submission handlers.

function instantFilters() {

    document.querySelectorAll(
        'form[data-instant-filters]'
    ).forEach((form, index) => {

        if (form.method.toLowerCase() !== 'get') {
            return;
        }

        const key =
            `equip-filter-focus:${location.pathname}:${index}`;

        const disclosure = form.querySelector(
            '[data-filter-disclosure]'
        );

        const canRestoreFocus = (control) => {

            if (!(control instanceof Element)) {
                return false;
            }

            return control.matches('[data-filter-search]')
                || (
                    disclosure?.contains(control)
                    && control.matches('select')
                );

        };

        let timer;

        // Remember the focused filter before submission.

        const rememberFocus = () => {

            clearTimeout(timer);

            const input = document.activeElement;

            if (
                !form.contains(input)
                || !canRestoreFocus(input)
            ) {
                return;
            }

            try {

                sessionStorage.setItem(
                    key,
                    JSON.stringify({
                        name: input.name,
                        value: input.value,
                        start: input.selectionStart,
                        end: input.selectionEnd,
                        saved: Date.now(),
                    })
                );

            } catch {

                // Filters remain usable without browser storage.

            }

        };

        form.addEventListener(
            'submit',
            rememberFocus
        );

        // Search after the user finishes typing.

        form.querySelectorAll(
            '[data-filter-search]'
        ).forEach((input) => {

            let composing = false;

            const scheduleSearch = () => {

                clearTimeout(timer);

                if (composing) {
                    return;
                }

                timer = setTimeout(() => {

                    // Avoid unexpected submission when the
                    // user has already moved to another field.

                    if (document.activeElement === input) {
                        form.requestSubmit();
                    }

                }, 600);

            };

            input.addEventListener(
                'compositionstart',
                () => {

                    composing = true;

                    clearTimeout(timer);

                }
            );

            input.addEventListener(
                'compositionend',
                () => {

                    composing = false;

                    scheduleSearch();

                }
            );

            input.addEventListener(
                'input',
                scheduleSearch
            );

            input.addEventListener(
                'blur',
                () => clearTimeout(timer)
            );

        });

        // Restore keyboard focus after filter submission.

        try {

            const saved = JSON.parse(
                sessionStorage.getItem(key) || 'null'
            );

            sessionStorage.removeItem(key);

            if (
                !saved
                || Date.now() - saved.saved > 15000
            ) {
                return;
            }

            const input = Array.from(
                form.elements
            ).find(
                (element) => element.name === saved.name
            );

            if (
                !input
                || !canRestoreFocus(input)
                || input.value !== saved.value
                || input.disabled
            ) {
                return;
            }

            if (disclosure?.contains(input)) {

                disclosure.open = true;

            }

            input.focus({
                preventScroll: true,
            });

            if (
                input.matches('[data-filter-search]')
                && typeof input.setSelectionRange === 'function'
                && Number.isInteger(saved.start)
                && Number.isInteger(saved.end)
            ) {

                input.setSelectionRange(
                    saved.start,
                    saved.end
                );

            }

        } catch {

            // Ignore unavailable or outdated browser storage.

        }

    });

}


// ==========================================
// Accessible Flash Notifications
// ==========================================

function flashMessages() {

    document.querySelectorAll(
        '[data-flash]'
    ).forEach((flash) => {

        const dismissButton = flash.querySelector(
            '[data-dismiss-flash]'
        );

        const dismiss = () => {

            // Restore focus before hiding a focused notification.

            if (flash.contains(document.activeElement)) {

                document.querySelector(
                    '#main-content'
                )?.focus({
                    preventScroll: true,
                });

            }

            flash.hidden = true;

        };

        dismissButton?.addEventListener(
            'click',
            dismiss
        );

        // Notifications remain visible until dismissed.
        // This allows users enough time to read messages.

    });

}


// ==========================================
// Initialization
// ==========================================

function initialize() {

    instantFilters();

    flashMessages();

}

if (document.readyState === 'loading') {

    document.addEventListener(
        'DOMContentLoaded',
        initialize,
        { once: true }
    );

} else {

    initialize();

}
