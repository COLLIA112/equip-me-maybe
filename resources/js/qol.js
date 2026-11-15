// GET filters stay independent from the POST confirmation/submitting handler.
function instantFilters() {
    document.querySelectorAll('form[data-instant-filters]').forEach((form, index) => {
        if (form.method.toLowerCase() !== 'get') return;

        const key = `equip-filter-focus:${location.pathname}:${index}`;
        let timer;
        const rememberFocus = () => {
            clearTimeout(timer);
            const input = document.activeElement;
            if (!form.contains(input) || !input.matches('[data-filter-search]')) return;
            try {
                sessionStorage.setItem(key, JSON.stringify({
                    name: input.name,
                    value: input.value,
                    start: input.selectionStart,
                    end: input.selectionEnd,
                    saved: Date.now(),
                }));
            } catch { /* Filters also work when browser storage is unavailable. */ }
        };
        form.addEventListener('submit', rememberFocus);
        form.querySelectorAll('[data-filter-search]').forEach((input) => {
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => form.requestSubmit(), 400);
            });
        });

        try {
            const saved = JSON.parse(sessionStorage.getItem(key) || 'null');
            sessionStorage.removeItem(key);
            if (!saved || Date.now() - saved.saved > 15000) return;
            const input = Array.from(form.elements).find((element) => element.name === saved.name);
            if (!input?.matches('[data-filter-search]') || input.value !== saved.value) return;
            input.focus({ preventScroll: true });
            input.setSelectionRange(saved.start, saved.end);
        } catch { /* Ignore stale or unavailable browser storage. */ }
    });
}

function flashMessages() {
    document.querySelectorAll('[data-flash]').forEach((flash) => {
        let remaining = 8000;
        let started;
        let timer;
        let hovered = false;
        const dismiss = () => {
            clearTimeout(timer);
            if (flash.contains(document.activeElement)) {
                document.querySelector('#main-content')?.focus({ preventScroll: true });
            }
            flash.hidden = true;
        };
        const pause = () => {
            clearTimeout(timer);
            if (started) remaining = Math.max(0, remaining - (Date.now() - started));
            started = null;
        };
        const resume = () => {
            if (hovered || flash.contains(document.activeElement) || flash.hidden) return;
            clearTimeout(timer);
            started = Date.now();
            timer = setTimeout(dismiss, remaining);
        };
        flash.addEventListener('mouseenter', () => { hovered = true; pause(); });
        flash.addEventListener('mouseleave', () => { hovered = false; resume(); });
        flash.addEventListener('focusin', pause);
        flash.addEventListener('focusout', () => setTimeout(resume, 0));
        flash.querySelector('[data-dismiss-flash]')?.addEventListener('click', dismiss);
        resume();
    });
}

function initialize() {
    instantFilters();
    flashMessages();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
