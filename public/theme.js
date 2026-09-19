(() => {
    const key = 'psychic-chat-theme';
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const normalize = value => ['light', 'dark', 'system'].includes(value) ? value : 'system';
    let preference = 'system';
    try { preference = normalize(localStorage.getItem(key)); } catch {}

    function apply() {
        const dark = preference === 'dark' || (preference === 'system' && media.matches);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        window.dispatchEvent(new CustomEvent('theme-change', { detail: preference }));
    }

    window.psychicTheme = {
        get preference() { return preference; },
        set(value) {
            preference = normalize(value);
            try { localStorage.setItem(key, preference); } catch {}
            apply();
        },
    };
    media.addEventListener('change', apply);
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) {
            preference = normalize(event.newValue);
            apply();
        }
    });
    apply();
})();
