<?php
/**
 * Dziennik Praktyk — ustawienia.
 *
 * Dostępna tylko po zalogowaniu. Zbiera w jednym miejscu:
 *  - wymaganą liczbę godzin praktyk (steruje paskiem postępu na stronie głównej),
 *  - ustawienia konta (zmiana hasła, e-mail do resetu, wylogowanie),
 *  - kopię zapasową (pobranie i przywrócenie dziennika).
 */

declare(strict_types=1);

require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/auth.php';

// Wymagane zalogowanie (brak konta → kreator, brak sesji → logowanie).
auth_require_login();

/* ------------------------------------------------------------------ *
 *  Zapis ustawień (POST/Redirect/GET)
 * ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $kod   = $action === 'save_email'
            ? 'email=csrf'
            : ($action === 'change_password' ? 'haslo=csrf' : 'godziny=csrf');
        header('Location: ustawienia.php?' . $kod);
        exit;
    }

    if ($action === 'save_wymagane_godziny') {
        $godz = trim((string) ($_POST['wymagane_godziny'] ?? ''));
        if ($godz === '' || !ctype_digit($godz) || (int) $godz < 1 || (int) $godz > 100000) {
            header('Location: ustawienia.php?godziny=zle');
            exit;
        }
        ustawienie_set('wymagane_godziny', (string) (int) $godz);
        header('Location: ustawienia.php?godziny=ok');
        exit;
    }

    if ($action === 'change_password') {
        $obecne = (string) ($_POST['obecne_haslo'] ?? '');
        $nowe   = (string) ($_POST['nowe_haslo'] ?? '');
        $powt   = (string) ($_POST['nowe_haslo2'] ?? '');

        if (strlen($nowe) < 6) {
            $haslo_kod = 'krotkie';
        } elseif ($nowe !== $powt) {
            $haslo_kod = 'niezgodne';
        } elseif (!auth_change_password($obecne, $nowe)) {
            $haslo_kod = 'zle';
        } else {
            $haslo_kod = 'ok';
        }

        header('Location: ustawienia.php?haslo=' . urlencode($haslo_kod));
        exit;
    }

    if ($action === 'save_email') {
        $mail = trim((string) ($_POST['email'] ?? ''));
        if ($mail === '') {
            // Puste pole NIE czyści zapisanego adresu (ochrona przed przypadkiem
            // i przed starą, otwartą stroną z pustym polem).
            header('Location: ustawienia.php?email=pusty');
            exit;
        }
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            header('Location: ustawienia.php?email=zly');
            exit;
        }
        auth_set_email($mail);
        header('Location: ustawienia.php?email=ok');
        exit;
    }
}

/* ------------------------------------------------------------------ *
 *  Stan ustawień
 * ------------------------------------------------------------------ */

$wymagane_godz = wymagane_godziny();
$wymagane_min  = $wymagane_godz * 60;

// Ile godzin już wypracowano (suma z wpisów).
$total_min = 0;
foreach (all_wpisy() as $w) {
    $min = minutes_between((string) $w['godzina_od'], (string) $w['godzina_do']);
    if ($min !== null) {
        $total_min += $min;
    }
}
$zostalo_min = max(0, $wymagane_min - $total_min);
$gotowe      = $wymagane_min > 0 && $total_min >= $wymagane_min;

$godziny_kod = isset($_GET['godziny']) ? (string) $_GET['godziny'] : null;
$haslo_kod   = trim((string) ($_GET['haslo'] ?? ''));
$email_kod   = trim((string) ($_GET['email'] ?? ''));
$przywrocono = isset($_GET['przywrocono']);
$blad_kopii  = trim((string) ($_GET['blad'] ?? ''));
$konto_login = (string) (auth_user()['login'] ?? '');
$konto_email = trim((string) (auth_user()['email'] ?? ''));

$theme = day_theme(date('Y-m-d'));
$theme_vars = sprintf(
    '--primary:%s;--primary-dark:%s;--accent:%s;--accent-dark:%s;--tint:%s;--tint2:%s;--font:%s;',
    $theme['primary'],
    $theme['primary_dark'],
    $theme['accent'],
    $theme['accent_dark'],
    $theme['tint'],
    $theme['tint2'],
    $theme['font']
);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicon.png">
    <title>Dziennik Praktyk — Ustawienia</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
    <script src="assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?: time() ?>"></script>
</head>
<body style="<?= e($theme_vars) ?>">
<div class="app">

    <header class="topbar no-print">
        <div class="brand">
            <span class="brand-icon">📘</span>
            <div>
                <h1 class="brand-title">Ustawienia</h1>
                <p class="brand-sub">Dziennik Praktyk</p>
            </div>
        </div>
        <div class="topbar-actions">
            <div class="theme-switch no-print" role="group" aria-label="Motyw: jasny, ciemny lub automatyczny">
                <button type="button" class="theme-btn" data-theme-mode="light" aria-pressed="false" title="Tryb jasny (dzień)">☀️</button>
                <button type="button" class="theme-btn" data-theme-mode="dark" aria-pressed="false" title="Tryb ciemny (noc)">🌙</button>
                <button type="button" class="theme-btn" data-theme-mode="auto" aria-pressed="true" title="Automatyczny (ustawienie systemu)">🌓</button>
            </div>
            <a class="btn btn-today" href="index.php" title="Wróć na stronę główną">🏠 Start</a>
        </div>
    </header>

    <main class="main settings-main">

        <?php if ($godziny_kod === 'ok'): ?>
            <p class="alert no-print">🎯 Zapisano wymaganą liczbę godzin praktyk.</p>
        <?php elseif ($godziny_kod === 'zle'): ?>
            <p class="alert alert-danger no-print">⚠ Podaj poprawną liczbę godzin (liczba całkowita większa od zera).</p>
        <?php elseif ($godziny_kod === 'csrf'): ?>
            <p class="alert alert-danger no-print">⚠ Sesja wygasła. Odśwież stronę i spróbuj ponownie.</p>
        <?php endif; ?>

        <?php if ($haslo_kod === 'ok'): ?>
            <p class="alert no-print">✔ Hasło zostało zmienione.</p>
        <?php elseif ($haslo_kod === 'krotkie'): ?>
            <p class="alert alert-danger no-print">⚠ Nowe hasło musi mieć co najmniej 6 znaków.</p>
        <?php elseif ($haslo_kod === 'niezgodne'): ?>
            <p class="alert alert-danger no-print">⚠ Nowe hasła nie są identyczne.</p>
        <?php elseif ($haslo_kod === 'zle'): ?>
            <p class="alert alert-danger no-print">⚠ Obecne hasło jest nieprawidłowe.</p>
        <?php elseif ($haslo_kod === 'csrf'): ?>
            <p class="alert alert-danger no-print">⚠ Sesja wygasła. Odśwież stronę i spróbuj ponownie.</p>
        <?php endif; ?>

        <?php if ($email_kod === 'ok'): ?>
            <p class="alert no-print">✔ Adres e-mail konta został zapisany.</p>
        <?php elseif ($email_kod === 'zly'): ?>
            <p class="alert alert-danger no-print">⚠ Podaj poprawny adres e-mail.</p>
        <?php elseif ($email_kod === 'pusty'): ?>
            <p class="alert alert-danger no-print">⚠ Pole e-mail było puste — zapisany adres pozostał bez zmian.</p>
        <?php elseif ($email_kod === 'csrf'): ?>
            <p class="alert alert-danger no-print">⚠ Sesja wygasła. Odśwież stronę i spróbuj ponownie.</p>
        <?php endif; ?>

        <?php if ($przywrocono): ?>
            <p class="alert no-print">✔ Dziennik przywrócony z kopii zapasowej. Kopia poprzedniego stanu została zapisana na serwerze.</p>
        <?php elseif ($blad_kopii !== ''): ?>
            <p class="alert alert-danger no-print">⚠ <?= e(backup_error_message($blad_kopii)) ?></p>
        <?php endif; ?>

        <article class="sheet">
            <header class="sheet-head">
                <div>
                    <p class="sheet-kicker">Dziennik Praktyk</p>
                    <p class="sheet-date">Ustawienia</p>
                </div>
            </header>

            <section class="settings-block">
                <h2 class="settings-title">🎯 Wymagane godziny praktyk</h2>
                <p class="settings-desc">
                    Podaj łączną liczbę godzin, które musisz wypracować. Na stronie głównej
                    pojawi się pasek postępu pokazujący, ile już wypracowano.
                </p>

                <form method="post" action="ustawienia.php" class="progress-form">
                    <input type="hidden" name="action" value="save_wymagane_godziny">
                    <?= csrf_field() ?>
                    <label for="wymagane-godziny">Wymaganych godzin</label>
                    <input id="wymagane-godziny" type="number" name="wymagane_godziny" min="1" max="100000" step="1"
                           value="<?= $wymagane_godz > 0 ? $wymagane_godz : '' ?>" placeholder="np. 140" required>
                    <button type="submit" class="btn btn-save">💾 Zapisz</button>
                </form>

                <?php if ($wymagane_godz > 0): ?>
                    <p class="settings-summary<?= $gotowe ? ' is-done' : '' ?>">
                        <?php if ($gotowe): ?>
                            ✔ Wypracowano wymagane godziny (<b><?= e(format_duration_compact($total_min)) ?></b> z <?= $wymagane_godz ?> h).
                        <?php else: ?>
                            Wypracowano <b><?= e(format_duration_compact($total_min)) ?></b> z <?= $wymagane_godz ?> h ·
                            zostało <b><?= e(format_duration_compact($zostalo_min)) ?></b>.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="settings-summary">Nie ustawiono jeszcze wymaganej liczby godzin.</p>
                <?php endif; ?>
            </section>

            <section class="settings-block" id="konto">
                <h2 class="settings-title">🔐 Konto</h2>
                <p class="settings-desc">
                    Zalogowany jako <b><?= e($konto_login) ?></b>. Tutaj zmienisz hasło do dziennika
                    oraz adres e-mail używany do resetowania hasła.
                </p>

                <div class="account-actions">
                    <button type="button" class="btn btn-account" data-open-password
                            title="Zmień hasło do dziennika">🔑 Zmień hasło</button>
                    <form method="post" action="logout.php" class="logout-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-logout" title="Wyloguj się">🚪 Wyloguj</button>
                    </form>
                </div>

                <div class="account-email">
                    <form method="post" action="ustawienia.php" class="email-form">
                        <input type="hidden" name="action" value="save_email">
                        <?= csrf_field() ?>
                        <label for="acc-email">📧 E-mail do resetu hasła</label>
                        <input id="acc-email" type="email" name="email" value="<?= e($konto_email) ?>"
                               placeholder="np. jan.kowalski@example.com" autocomplete="email" required>
                        <button type="submit" class="btn btn-account" title="Zapisz adres e-mail">💾 Zapisz</button>
                    </form>
                </div>
            </section>

            <section class="settings-block" id="kopia">
                <h2 class="settings-title">💾 Kopia zapasowa</h2>
                <p class="settings-desc">
                    Zapisz dziennik do pliku <code>.db</code> albo przywróć go z wcześniejszej kopii.
                </p>

                <div class="backup-block">
                    <h3 class="backup-title">⬇ Pobierz kopię</h3>
                    <p class="backup-text">
                        Plik <code>.db</code> zawiera wszystkie wpisy dziennika. Trzymaj go w bezpiecznym miejscu.
                        Uwaga: kopia obejmuje wpisy i listę plików, ale <strong>nie zawiera samych załączników</strong>
                        (zdjęć/plików) — te leżą osobno na serwerze.
                    </p>
                    <a class="btn btn-save" href="backup.php?action=download">💾 Pobierz kopię zapasową</a>
                </div>

                <div class="backup-block backup-block-danger">
                    <h3 class="backup-title">⬆ Przywróć z kopii</h3>
                    <p class="backup-text">
                        Uwaga: przywrócenie <strong>nadpisze</strong> obecne wpisy zawartością pliku.
                        Przed przywróceniem zapisujemy kopię obecnego stanu na serwerze.
                    </p>
                    <form method="post" action="backup.php" enctype="multipart/form-data" class="backup-form"
                          onsubmit="return confirm('Przywrócić dziennik z wybranego pliku?\nObecne wpisy zostaną nadpisane.');">
                        <input type="hidden" name="action" value="restore">
                        <?= csrf_field() ?>
                        <input class="backup-file" type="file" name="kopia" required
                               accept=".db,.sqlite,.sqlite3,application/octet-stream,application/x-sqlite3">
                        <button type="submit" class="btn btn-danger">⬆ Przywróć dziennik</button>
                    </form>
                </div>
            </section>
        </article>

        <nav class="pagefoot no-print" aria-label="Informacje">
            <a href="index.php">🏠 Start</a>
            <a href="kalendarz.php">📅 Kalendarz</a>
            <a href="ustawienia.php" aria-current="page">⚙️ Ustawienia</a>
            <a href="o-mnie.php">ℹ️ O mnie</a>
        </nav>
    </main>

    <!-- Popup „Zmień hasło" -->
    <div class="modal no-print" id="password-modal" hidden>
        <div class="modal-backdrop" data-close-password></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="password-modal-title">
            <header class="modal-head">
                <span class="modal-icon">🔑</span>
                <div>
                    <h2 id="password-modal-title" class="modal-title">Zmień hasło</h2>
                    <p class="modal-hint">Konto: <?= e($konto_login) ?>. Do zmiany hasła podaj obecne hasło.</p>
                </div>
            </header>
            <form method="post" action="ustawienia.php" class="modal-form">
                <input type="hidden" name="action" value="change_password">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="p-obecne">Obecne hasło</label>
                    <input id="p-obecne" type="password" name="obecne_haslo" autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="p-nowe">Nowe hasło</label>
                    <input id="p-nowe" type="password" name="nowe_haslo" autocomplete="new-password" minlength="6" required>
                </div>
                <div class="field">
                    <label for="p-nowe2">Powtórz nowe hasło</label>
                    <input id="p-nowe2" type="password" name="nowe_haslo2" autocomplete="new-password" minlength="6" required>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-save">💾 Zmień hasło</button>
                    <button type="button" class="btn" data-close-password>Anuluj</button>
                </div>
            </form>
        </div>
    </div>

</div>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: time() ?>"></script>
</body>
</html>
