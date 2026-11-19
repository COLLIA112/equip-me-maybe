const shell = document.documentElement;
const media = window.matchMedia('(prefers-color-scheme: dark)');
const applyTheme = () => {
    const theme = shell.dataset.theme || 'system';
    shell.classList.toggle('dark', theme === 'dark' || (theme === 'system' && media.matches));
};
applyTheme();
media.addEventListener('change', applyTheme);

document.addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) return;
    const target = event.target;
    if (target instanceof Element && target.closest('input, textarea, select, [contenteditable="true"], [role="textbox"]')) return;
    const input = document.querySelector('[data-global-search]');
    if (input && !input.closest('[inert]')) {
        event.preventDefault();
        input.focus();
    }
});

// Clear also removes remembered server preferences, including for existing list links.
document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('form[data-instant-filters] a') : null;
    if (!link || link.textContent.trim() !== 'Clear filter') return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || url.searchParams.has('model')) return;
    url.searchParams.set('clear', '1');
    link.href = url.href;
});
