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

    // Przełącznik motywu: jasny / ciemny / automatyczny.
    const theme = window.__dpTheme || { key: 'dp-theme', getMode: () => 'auto', applyTheme: () => {} };

    function paintThemeButtons() {
        const mode = theme.getMode();
        document.querySelectorAll('.theme-btn').forEach((btn) => {
            const active = btn.getAttribute('data-theme-mode') === mode;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    document.querySelectorAll('.theme-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const mode = btn.getAttribute('data-theme-mode');
            try { localStorage.setItem(theme.key, mode); } catch (e) { /* brak dostępu do localStorage */ }
            theme.applyTheme();
            paintThemeButtons();
        });
    });
    paintThemeButtons();

    // W trybie „auto'' reaguj na zmianę ustawień systemu (dzień ↔ noc).
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (theme.getMode() === 'auto') {
                theme.applyTheme();
            }
        });
    }

    // Popup „Dodaj wpis".
    const dodajModal = document.getElementById('dodaj-modal');
    const openDodajBtn = document.querySelector('[data-open-dodaj]');

    function openDodajModal() {
        if (!dodajModal) return;
        dodajModal.hidden = false;
        document.body.classList.add('modal-open');
        const first = dodajModal.querySelector('input, textarea, select');
        if (first) first.focus();
    }

    function closeDodajModal() {
        if (!dodajModal) return;
        dodajModal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    if (openDodajBtn) {
        openDodajBtn.addEventListener('click', openDodajModal);
    }
    if (dodajModal) {
        dodajModal.querySelectorAll('[data-close-modal]').forEach(function (el) {
            el.addEventListener('click', closeDodajModal);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !dodajModal.hidden) {
                closeDodajModal();
            }
        });
    }
})();
