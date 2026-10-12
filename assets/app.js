// Dziennik Praktyk — drobne usprawnienia interfejsu.
(function () {
    'use strict';

    function clicked(el) {
        if (el && el.getAttribute('aria-disabled') !== 'true') {
            el.click();
            return true;
        }
        return false;
    }

    // Nawigacja strzałkami: ← poprzedni dzień/tydzień, → następny dzień/tydzień.
    // T: przełączenie widoku dzień ⇄ tydzień.
    document.addEventListener('keydown', function (event) {
        const tag = (event.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') {
            return;
        }
        if (event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        if (event.key === 'ArrowLeft') {
            if (clicked(document.querySelector('.daynav .daynav-prev'))) event.preventDefault();
        } else if (event.key === 'ArrowRight') {
            if (clicked(document.querySelector('.daynav .daynav-next'))) event.preventDefault();
        } else if (event.key === 't' || event.key === 'T') {
            // Przełącz na drugi widok (nieaktywny przycisk przełącznika).
            const inactive = document.querySelector('.viewswitch .viewswitch-btn:not(.active)');
            if (clicked(inactive)) event.preventDefault();
        }
    });
})();
