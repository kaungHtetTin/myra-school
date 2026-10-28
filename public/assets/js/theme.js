(() => {
    const key = 'myra-school-theme';
    const root = document.documentElement;
    let theme = 'light';
    try { theme = localStorage.getItem(key) === 'dark' ? 'dark' : 'light'; } catch (_) {}

    const apply = value => {
        root.dataset.theme = value;
        root.style.colorScheme = value;
        const button = document.querySelector('[data-theme-toggle]');
        if (button) {
            const label = value === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(value === 'dark'));
        }
    };
    // Runs in the head so the stored theme applies before the first paint.
    apply(theme);
    document.addEventListener('DOMContentLoaded', () => {
        apply(root.dataset.theme);
        document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
            const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
            apply(next);
            try { localStorage.setItem(key, next); } catch (_) {}
        });
    });
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) apply(event.newValue === 'dark' ? 'dark' : 'light');
    });
})();
