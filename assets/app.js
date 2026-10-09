// Dziennik Praktyk — drobne usprawnienia interfejsu.
(function () {
    'use strict';

    // Nawigacja strzałkami: ← poprzedni dzień, → następny dzień.
    document.addEventListener('keydown', function (event) {
        const tag = (event.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') {
            return;
        }
        if (event.key === 'ArrowLeft') {
            const el = document.querySelector('.nav a[title="Poprzedni dzień"]');
            if (el && el.getAttribute('aria-disabled') !== 'true') el.click();
        } else if (event.key === 'ArrowRight') {
            const el = document.querySelector('.nav a[title="Następny dzień"]');
            if (el && el.getAttribute('aria-disabled') !== 'true') el.click();
        }
    });
})();
