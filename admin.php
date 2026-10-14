<?php
/**
 * Dziennik Praktyk — panel administratora.
 *
 * Dostępny wyłącznie dla zalogowanych administratorów. Pozwala:
 *  - zakładać konta uczniów i administratorów,
 *  - przeglądać listę kont z liczbą wpisów i wypracowanych godzin,
 *  - otwierać dzienniczki uczniów w trybie tylko do odczytu,
 *  - zmieniać hasło, rolę i nazwę oraz usuwać konta.
 *
 * Dzienniczki uczniów są dziennikami per konto (kolumna `user_id`).
 */

declare(strict_types=1);

require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/auth.php';

auth_require_admin();

$me = auth_user();

/* ------------------------------------------------------------------ *
 *  Akcje (POST/Redirect/GET)
 * ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!csrf_check($_POST['csrf'] ?? null)) {
        header('Location: admin.php?err=csrf');
        exit;
    }

    if ($action === 'create_user') {
        $kod = user_create(
            (string) ($_POST['login'] ?? ''),
            (string) ($_POST['haslo'] ?? ''),
            (string) ($_POST['rola'] ?? 'uczen'),
            (string) ($_POST['imie_nazwisko'] ?? ''),
            (string) ($_POST['email'] ?? '')
        );
        header('Location: admin.php?' . ($kod === 'ok' ? 'ok=dodano' : 'err=dodano-' . $kod));
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $me['id']) {
            header('Location: admin.php?err=usun-siebie');
            exit;
        }
        $ok = user_delete($id);
        header('Location: admin.php?' . ($ok ? 'ok=usunieto' : 'err=usun-ostatni'));
        exit;
    }

    if ($action === 'reset_password') {
        $id    = (int) ($_POST['id'] ?? 0);
        $haslo = (string) ($_POST['haslo'] ?? '');
        $ok    = user_set_password($id, $haslo);
        header('Location: admin.php?' . ($ok ? 'ok=haslo' : 'err=haslo-krotkie'));
        exit;
    }

    if ($action === 'set_role') {
        $id   = (int) ($_POST['id'] ?? 0);
        $rola = (string) ($_POST['rola'] ?? '');
        // Nie pozwalamy odebrać rangi ostatniemu administratorowi.
        $u = user_get($id);
        if ($u !== null && ($u['rola'] ?? '') === 'admin' && $rola === 'uczen') {
            $adminow = (int) db()->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'admin'")->fetchColumn();
            if ($adminow <= 1) {
                header('Location: admin.php?err=ostatni-admin');
                exit;
            }
        }
        $ok = user_set_role($id, $rola);
        header('Location: admin.php?' . ($ok ? 'ok=rola' : 'err=rola'));
        exit;
    }

    if ($action === 'set_name') {
        $id   = (int) ($_POST['id'] ?? 0);
        $imie = (string) ($_POST['imie_nazwisko'] ?? '');
        $ok   = user_set_name($id, $imie);
        header('Location: admin.php?' . ($ok ? 'ok=nazwa' : 'err=blad'));
        exit;
    }

    header('Location: admin.php');
    exit;
}

/* ------------------------------------------------------------------ *
 *  Dane do widoku
 * ------------------------------------------------------------------ */

$users = users_all();

// Statystyki per konto: liczba dni z wpisem i suma minut.
$stat = [];
foreach (db()->query('SELECT user_id, godzina_od, godzina_do FROM wpisy') as $r) {
    $uid = (int) $r['user_id'];
    if (!isset($stat[$uid])) {
        $stat[$uid] = ['dni' => 0, 'min' => 0];
    }
    $stat[$uid]['dni']++;
    $stat[$uid]['min'] += minutes_between((string) $r['godzina_od'], (string) $r['godzina_do']);
}

$ilosc_uczniow = 0;
$ilosc_adminow = 0;
foreach ($users as $u) {
    if (($u['rola'] ?? '') === 'admin') {
        $ilosc_adminow++;
    } else {
        $ilosc_uczniow++;
    }
}

$ok_kod   = trim((string) ($_GET['ok'] ?? ''));
$err_kod  = trim((string) ($_GET['err'] ?? ''));

$ok_msg = [
    'dodano'  => '✔ Konto zostało utworzone.',
    'usunieto' => '✔ Konto zostało usunięte wraz z dziennikiem.',
    'haslo'   => '✔ Hasło konta zostało zmienione.',
    'rola'    => '✔ Rola konta została zmieniona.',
    'nazwa'   => '✔ Nazwa konta została zapisana.',
][$ok_kod] ?? '';

$err_msg = [
    'csrf'           => '⚠ Sesja wygasła. Odśwież stronę i spróbuj ponownie.',
    'usun-siebie'    => '⚠ Nie możesz usunąć własnego konta.',
    'usun-ostatni'   => '⚠ Nie można usunąć ostatniego administratora.',
    'ostatni-admin'  => '⚠ Nie można odebrać rangi ostatniemu administratorowi.',
    'haslo-krotkie'  => '⚠ Hasło musi mieć co najmniej 6 znaków.',
    'rola'           => '⚠ Nie udało się zmienić roli.',
    'blad'           => '⚠ Nie udało się zapisać zmian.',
    'dodano-login'   => '⚠ Podaj nazwę konta (login).',
    'dodano-krotki'  => '⚠ Nazwa konta musi mieć co najmniej 3 znaki.',
    'dodano-haslo'   => '⚠ Hasło musi mieć co najmniej 6 znaków.',
    'dodano-email'   => '⚠ Podaj poprawny adres e-mail.',
    'dodano-rola'    => '⚠ Wybierz poprawną rolę.',
    'dodano-zajety'  => '⚠ Konto o tej nazwie już istnieje.',
][$err_kod] ?? '';

$theme      = day_theme(date('Y-m-d'));
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
    <title>Dziennik Praktyk — Panel administratora</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
    <script src="assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?: time() ?>"></script>
</head>
<body style="<?= e($theme_vars) ?>">
<div class="app">

    <header class="topbar no-print">
        <div class="brand">
            <span class="brand-icon">👑</span>
            <div>
                <h1 class="brand-title">Panel administratora</h1>
                <p class="brand-sub">Dziennik Praktyk · <?= $ilosc_uczniow ?> <?= $ilosc_uczniow === 1 ? 'uczeń' : 'uczniów' ?> · <?= $ilosc_adminow ?> <?= $ilosc_adminow === 1 ? 'administrator' : 'administratorów' ?></p>
            </div>
        </div>
    </header>

    <!-- Akcje: pasek chowający się w bok + przycisk „⋮" do przywołania -->
    <div class="actions-dock no-print">
        <div class="topbar-actions" id="actions-bar" role="toolbar" aria-label="Akcje">
            <div class="theme-switch no-print" role="group" aria-label="Motyw: jasny, ciemny lub automatyczny">
                <button type="button" class="theme-btn" data-theme-mode="light" aria-pressed="false" title="Tryb jasny (dzień)">☀️</button>
                <button type="button" class="theme-btn" data-theme-mode="dark" aria-pressed="false" title="Tryb ciemny (noc)">🌙</button>
                <button type="button" class="theme-btn" data-theme-mode="auto" aria-pressed="true" title="Automatyczny (ustawienie systemu)">🌓</button>
            </div>
            <a class="btn btn-today" href="index.php" title="Mój dziennik">📘 Mój dziennik</a>
            <a class="btn btn-today" href="kalendarz.php" title="Mój kalendarz">📅 Kalendarz</a>
            <a class="btn btn-icon" href="ustawienia.php" title="Ustawienia" aria-label="Ustawienia">⚙️</a>
            <form method="post" action="logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-logout" title="Wyloguj się">🚪 Wyloguj</button>
            </form>
        </div>
        <button type="button" class="actions-toggle" id="actions-toggle"
                aria-controls="actions-bar" aria-expanded="true"
                title="Pokaż lub ukryj pasek akcji" aria-label="Pokaż lub ukryj pasek akcji">⋮</button>
    </div>

    <main class="main admin-main">

        <?php if ($ok_msg !== ''): ?>
            <p class="alert no-print"><?= e($ok_msg) ?></p>
        <?php elseif ($err_msg !== ''): ?>
            <p class="alert alert-danger no-print"><?= e($err_msg) ?></p>
        <?php endif; ?>

        <article class="sheet">
            <header class="sheet-head">
                <div>
                    <p class="sheet-kicker">Dziennik Praktyk</p>
                    <p class="sheet-date">Konta i dzienniczki</p>
                </div>
            </header>

            <!-- Zakładanie nowego konta -->
            <section class="settings-block" id="nowe-konto">
                <div class="settings-head">
                    <span class="settings-ico" aria-hidden="true">➕</span>
                    <div>
                        <h2 class="settings-title">Nowe konto</h2>
                        <p class="settings-desc">
                            Dodaj konto ucznia albo administratora. Uczeń ma własny dziennik; administrator
                            widzi dzienniczki wszystkich uczniów i zarządza kontami.
                        </p>
                    </div>
                </div>

                <form method="post" action="admin.php" class="admin-create" autocomplete="off">
                    <input type="hidden" name="action" value="create_user">
                    <?= csrf_field() ?>

                    <div class="admin-create-grid">
                        <div class="field">
                            <label for="nu-login">👤 Nazwa konta (login)</label>
                            <input id="nu-login" type="text" name="login" minlength="3" maxlength="64"
                                   placeholder="np. jan.kowalski" required>
                        </div>
                        <div class="field">
                            <label for="nu-imie">🪪 Imię i nazwisko</label>
                            <input id="nu-imie" type="text" name="imie_nazwisko" maxlength="120"
                                   placeholder="np. Jan Kowalski">
                        </div>
                        <div class="field">
                            <label for="nu-rola">🎭 Rola</label>
                            <select id="nu-rola" name="rola">
                                <option value="uczen" selected>Uczeń</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="nu-haslo">🔑 Hasło (min. 6 znaków)</label>
                            <input id="nu-haslo" type="password" name="haslo" minlength="6"
                                   autocomplete="new-password" required>
                        </div>
                        <div class="field admin-create-wide">
                            <label for="nu-email">📧 E-mail (do resetu hasła, opcjonalnie)</label>
                            <input id="nu-email" type="email" name="email" maxlength="190"
                                   placeholder="np. jan.kowalski@example.com">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-save">➕ Utwórz konto</button>
                </form>
            </section>

            <!-- Lista kont -->
            <section class="settings-block" id="lista-kont">
                <div class="settings-head">
                    <span class="settings-ico" aria-hidden="true">👥</span>
                    <div>
                        <h2 class="settings-title">Wszystkie konta</h2>
                        <p class="settings-desc">
                            Przeglądaj dzienniczki (przycisk „Podgląd"), zmieniaj hasło, rolę i nazwę albo usuwaj konta.
                        </p>
                    </div>
                </div>

                <div class="admin-users">
                    <?php foreach ($users as $u): ?>
                        <?php
                        $uid    = (int) $u['id'];
                        $rola   = ($u['rola'] ?? '') === 'admin' ? 'admin' : 'uczen';
                        $rola_l = $rola === 'admin' ? 'Administrator' : 'Uczeń';
                        $dni    = (int) ($stat[$uid]['dni'] ?? 0);
                        $min    = (int) ($stat[$uid]['min'] ?? 0);
                        $jestem = $uid === (int) $me['id'];
                        ?>
                        <div class="admin-user<?= $jestem ? ' is-me' : '' ?>">
                            <div class="admin-user-head">
                                <div class="admin-user-id">
                                    <span class="admin-user-name"><?= e(user_label($u)) ?></span>
                                    <span class="role-badge role-<?= e($rola) ?>"><?= e($rola_l) ?></span>
                                    <?php if ($jestem): ?><span class="admin-you">to Ty</span><?php endif; ?>
                                    <span class="admin-user-login"><?= e((string) $u['login']) ?></span>
                                </div>
                                <div class="admin-user-stats">
                                    <span title="Liczba dni z wpisem">📄 <?= $dni ?> <?= $dni === 1 ? 'wpis' : 'wpisów' ?></span>
                                    <span title="Łączny czas praktyk">⏱ <?= e(format_duration_compact($min)) ?></span>
                                </div>
                            </div>

                            <div class="admin-user-actions">
                                <?php if ($rola === 'uczen'): ?>
                                    <a class="btn btn-today" href="index.php?uczen=<?= $uid ?>"
                                       title="Podejrzyj dziennik ucznia (tylko odczyt)">👀 Podgląd</a>
                                <?php endif; ?>

                                <!-- Zmiana roli -->
                                <form method="post" action="admin.php" class="admin-inline">
                                    <input type="hidden" name="action" value="set_role">
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <?= csrf_field() ?>
                                    <select name="rola" aria-label="Rola konta <?= e((string) $u['login']) ?>">
                                        <option value="uczen" <?= $rola === 'uczen' ? 'selected' : '' ?>>Uczeń</option>
                                        <option value="admin" <?= $rola === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm">🎭 Zmień rolę</button>
                                </form>

                                <!-- Zmiana nazwy -->
                                <form method="post" action="admin.php" class="admin-inline">
                                    <input type="hidden" name="action" value="set_name">
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <?= csrf_field() ?>
                                    <input type="text" name="imie_nazwisko" maxlength="120"
                                           value="<?= e((string) ($u['imie_nazwisko'] ?? '')) ?>"
                                           placeholder="Imię i nazwisko"
                                           aria-label="Imię i nazwisko <?= e((string) $u['login']) ?>">
                                    <button type="submit" class="btn btn-sm">🪪 Zapisz nazwę</button>
                                </form>

                                <!-- Nowe hasło -->
                                <form method="post" action="admin.php" class="admin-inline">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <?= csrf_field() ?>
                                    <input type="password" name="haslo" minlength="6" autocomplete="new-password"
                                           placeholder="Nowe hasło (min. 6)"
                                           aria-label="Nowe hasło dla <?= e((string) $u['login']) ?>">
                                    <button type="submit" class="btn btn-sm">🔑 Ustaw hasło</button>
                                </form>

                                <!-- Usunięcie konta -->
                                <form method="post" action="admin.php" class="admin-inline"
                                      onsubmit="return confirm('Usunąć konto „<?= e((string) $u['login']) ?>”?\nZostanie usunięty także jego dziennik wraz z załącznikami.<?= $dni > 0 ? '\n\nUwaga: konto ma ' . $dni . ' wpis(ów)!' : '' ?>');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm" title="Usuń konto">🗑 Usuń</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </article>

        <nav class="pagefoot no-print" aria-label="Informacje">
            <a href="index.php">🏠 Start</a>
            <a href="kalendarz.php">📅 Kalendarz</a>
            <a href="admin.php" aria-current="page">👑 Panel admina</a>
            <a href="ustawienia.php">⚙️ Ustawienia</a>
            <a href="o-mnie.php">ℹ️ O mnie</a>
        </nav>
    </main>
</div>

<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: time() ?>"></script>
</body>
</html>
