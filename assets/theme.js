// Dziennik Praktyk — ustawianie motywu (jasny / ciemny / automatyczny).
// Wspólne dla strony głównej i ekranów logowania.
(function () {
    'use strict';
    var key = 'dp-theme';
    function getMode() {
        try { return localStorage.getItem(key) || 'auto'; } catch (e) { return 'auto'; }
    }
    function applyTheme() {
        var m = getMode();
        var dark = m === 'dark' ||
            (m === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme-mode', m);
    }
    applyTheme();
    window.__dpTheme = { key: key, getMode: getMode, applyTheme: applyTheme };
})();
