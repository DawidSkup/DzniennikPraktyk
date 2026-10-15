<?php
/**
 * Dziennik Praktyk — panel administratora.
 *
 * Dostępny wyłącznie dla zalogowanych administratorów. Pozwala:
 *  - zakładać konta uczniów i administratorów,
 *  - przeglądać listę kont z liczbą wpisów i wypracowanych godzin,
 *  - otwierać i edytować dzienniczki uczniów (w imieniu ucznia),
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
            (string) ($_POST['klasa'] ?? ''),
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
        $id    = (int) ($_POST['id'] ?? 0);
        $imie  = (string) ($_POST['imie_nazwisko'] ?? '');
        $klasa = (string) ($_POST['klasa'] ?? '');
        $ok    = user_set_name($id, $imie, $klasa);
        header('Location: admin.php?' . ($ok ? 'ok=nazwa' : 'err=blad'));
        exit;
    }

    if ($action === 'set_klasa') {
        $from = (string) ($_POST['from'] ?? '');
        $to   = (string) ($_POST['klasa'] ?? '');
        // Ta sama granica co pole w panelu (maxlength="40").
        if (mb_strlen(trim($to)) > 40) {
            header('Location: admin.php?err=klasa');
            exit;
        }
        $ile = user_set_klasa_bulk($from, $to);
        if ($ile === 0) {
            header('Location: admin.php?err=klasa-pusto');
            exit;
        }
        header('Location: admin.php?ok=klasa&ile=' . $ile);
        exit;
    }

    if ($action === 'delete_class') {
        $from = trim((string) ($_POST['from'] ?? ''));
        // Grupy „Bez klasy" nie usuwamy hurtem — tylko pojedyncze konta.
        $ile = $from === '' ? 0 : user_delete_class($from);
        if ($ile === 0) {
            header('Location: admin.php?err=klasa-pusto');
            exit;
        }
        header('Location: admin.php?ok=usun-klase&ile=' . $ile);
        exit;
    }

    if ($action === 'set_wymagane') {
        $id = (int) ($_POST['id'] ?? 0);
        $u  = user_get($id);
        // Wymagane godziny ustawiamy tylko uczniom (administrator nie ma dziennika).
        if ($u === null || ($u['rola'] ?? '') === 'admin') {
            header('Location: admin.php?err=godziny');
            exit;
        }
        $godz = trim((string) ($_POST['wymagane_godziny'] ?? ''));
        // Puste pole lub 0 = wyczyść wymaganie.
        if ($godz === '' || $godz === '0') {
            wymagane_godziny_set($id, 0);
            header('Location: admin.php?ok=godziny');
            exit;
        }
        if (!ctype_digit($godz) || (int) $godz < 1 || (int) $godz > 100000) {
            header('Location: admin.php?err=godziny');
            exit;
        }
        wymagane_godziny_set($id, (int) $godz);
        header('Location: admin.php?ok=godziny');
        exit;
    }

    header('Location: admin.php');
    exit;
}

/* ------------------------------------------------------------------ *
 *  Dane do widoku
 * ------------------------------------------------------------------ */

$users = users_all();

// Fraza wyszukiwania uczniów (po imieniu i nazwisku, dodatkowo login i klasa).
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';

// Fraza wyszukiwania administratorów (po imieniu i nazwisku oraz loginie).
$qa = is_string($_GET['qa'] ?? null) ? trim($_GET['qa']) : '';

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

// Zakładka kont wymuszona przez wyszukiwanie (albo po zmianie klasy).
$tab_wymuszony = ($q !== '' || $ok_kod === 'klasa') ? 'tab-uczniowie' : ($qa !== '' ? 'tab-admini' : '');

$ok_msg = [
    'dodano'  => '✔ Konto zostało utworzone.',
    'usunieto' => '✔ Konto zostało usunięte wraz z dziennikiem.',
    'haslo'   => '✔ Hasło konta zostało zmienione.',
    'rola'    => '✔ Rola konta została zmieniona.',
    'nazwa'   => '✔ Nazwa konta została zapisana.',
    'godziny' => '✔ Wymagane godziny praktyk zostały zapisane.',
    'klasa'   => '✔ Klasa uczniów została zmieniona.',
    'usun-klase' => '✔ Cała klasa została usunięta.',
][$ok_kod] ?? '';

// Poprawna odmiana słowa „konto" dla liczby uczniów objętych operacją.
$forma_konta = static function (int $ile): string {
    return $ile === 1
        ? 'konto'
        : (($ile % 10 >= 2 && $ile % 10 <= 4 && ($ile % 100 < 12 || $ile % 100 > 14)) ? 'konta' : 'kont');
};

// Masowa zmiana klasy: pokazujemy, ilu uczniów objęła.
if ($ok_kod === 'klasa') {
    $ile    = (int) ($_GET['ile'] ?? 0);
    $ok_msg = '✔ Zmieniono klasę (' . $ile . ' ' . $forma_konta($ile) . ').';
}

// Usunięcie całej klasy: pokazujemy, ile kont usunięto.
if ($ok_kod === 'usun-klase') {
    $ile    = (int) ($_GET['ile'] ?? 0);
    $ok_msg = '✔ Usunięto całą klasę (' . $ile . ' ' . $forma_konta($ile) . ' wraz z dziennikami).';
}

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
    'dodano-klasa'   => '⚠ Podaj klasę ucznia.',
    'dodano-zajety'  => '⚠ Konto o tej nazwie już istnieje.',
    'godziny'        => '⚠ Podaj poprawną liczbę godzin (1–100000) albo zostaw puste, aby wyczyścić.',
    'klasa'          => '⚠ Nazwa klasy może mieć maksymalnie 40 znaków.',
    'klasa-pusto'    => '⚠ Nie znaleziono uczniów w tej klasie (odśwież stronę i spróbuj ponownie).',
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
<body style="<?= e($theme_vars) ?>"<?= $ok_kod === 'dodano' ? ' data-panel-tab="ptab-edycja"' : '' ?><?= $tab_wymuszony !== '' ? ' data-admin-tab="' . $tab_wymuszony . '"' : '' ?>>
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
            <button type="button" class="btn btn-new" id="btn-new-account"
                    title="Załóż nowe konto" aria-label="Nowe konto">➕ Nowe konto</button>
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

            <div class="panel-tabs">
                <input class="panel-tab-input" type="radio" name="panel-tab" id="ptab-edycja" checked>
                <input class="panel-tab-input" type="radio" name="panel-tab" id="ptab-nowe">

                <div class="panel-tabbar" role="tablist" aria-label="Sekcja panelu">
                    <label class="panel-tab" for="ptab-edycja" role="tab">
                        <span aria-hidden="true">✏️</span> Edycja kont
                        <span class="panel-tab-count"><?= count($users) ?></span>
                    </label>
                    <label class="panel-tab" for="ptab-nowe" role="tab">
                        <span aria-hidden="true">➕</span> Nowe konto
                    </label>
                </div>

                <div class="panel-tabpanel" data-ptab="nowe">
                <!-- Zakładanie nowego konta — okienko popup (kliknięcie tła zamyka) -->
                <label class="modal-backdrop" for="ptab-edycja" title="Zamknij" aria-hidden="true"></label>
                <section class="settings-block modal-card" id="nowe-konto" role="dialog" aria-modal="true" aria-labelledby="nowe-konto-tytul">
                <div class="settings-head">
                    <span class="settings-ico" aria-hidden="true">➕</span>
                    <div>
                        <h2 class="settings-title" id="nowe-konto-tytul">Nowe konto</h2>
                        <p class="settings-desc">
                            Dodaj konto ucznia albo administratora. Dla ucznia podaj klasę.
                            Uczeń ma własny dziennik; administrator widzi dzienniczki wszystkich uczniów i zarządza kontami.
                        </p>
                    </div>
                    <button type="button" class="modal-close" id="modal-close" title="Zamknij okno" aria-label="Zamknij okno">✕</button>
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
                        <div class="field" id="nu-klasa-field">
                            <label for="nu-klasa">🎓 Klasa (wymagana dla ucznia)</label>
                            <input id="nu-klasa" type="text" name="klasa" maxlength="40"
                                   placeholder="np. 3T" required>
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
                </div>

                <div class="panel-tabpanel" data-ptab="edycja">
                <!-- Lista kont -->
                <section class="settings-block" id="lista-kont">
                <div class="settings-head">
                    <span class="settings-ico" aria-hidden="true">👥</span>
                    <div>
                        <h2 class="settings-title">Konta</h2>
                        <p class="settings-desc">
                            Uczniowie i administratorzy w osobnych zakładkach. Otwieraj i edytuj dzienniczki
                            (przycisk „Otwórz"), ustawiaj uczniom wymagane godziny praktyk, zmieniaj hasło,
                            rolę, nazwę i klasę albo usuwaj konta.
                        </p>
                    </div>
                </div>

                <?php
                // Osobne zakładki: uczniowie i administratorzy.
                $uczniowie = array_values(array_filter(
                    $users,
                    static fn(array $u): bool => ($u['rola'] ?? '') !== 'admin'
                ));
                $admini = array_values(array_filter(
                    $users,
                    static fn(array $u): bool => ($u['rola'] ?? '') === 'admin'
                ));

                // Wyszukiwanie uczniów po imieniu i nazwisku (także login i klasa).
                if ($q !== '') {
                    $uczniowie = array_values(array_filter(
                        $uczniowie,
                        static function (array $u) use ($q): bool {
                            $hay = ($u['imie_nazwisko'] ?? '') . ' '
                                 . ($u['login'] ?? '') . ' '
                                 . user_klasa($u);
                            return mb_stripos($hay, $q) !== false;
                        }
                    ));
                }

                // Wyszukiwanie administratorów po imieniu i nazwisku (także login).
                if ($qa !== '') {
                    $admini = array_values(array_filter(
                        $admini,
                        static function (array $u) use ($qa): bool {
                            $hay = ($u['imie_nazwisko'] ?? '') . ' ' . ($u['login'] ?? '');
                            return mb_stripos($hay, $qa) !== false;
                        }
                    ));
                }

                // Grupowanie uczniów według klasy („Bez klasy" na końcu).
                $klasy = [];
                foreach ($uczniowie as $u) {
                    $k = user_klasa($u);
                    $klasy[$k === '' ? 'Bez klasy' : $k][] = $u;
                }
                uksort($klasy, static function (string $a, string $b): int {
                    if ($a === 'Bez klasy') { return 1; }
                    if ($b === 'Bez klasy') { return -1; }
                    return strnatcasecmp($a, $b);
                });

                /** Karta pojedynczego konta na liście (wspólna dla obu zakładek). */
                $karta = function (array $u) use ($stat, $me): void {
                    $uid    = (int) $u['id'];
                    $rola   = ($u['rola'] ?? '') === 'admin' ? 'admin' : 'uczen';
                    $rola_l = $rola === 'admin' ? 'Administrator' : 'Uczeń';
                    $klasa  = user_klasa($u);
                    $dni    = (int) ($stat[$uid]['dni'] ?? 0);
                    $min    = (int) ($stat[$uid]['min'] ?? 0);
                    $jestem = $uid === (int) $me['id'];
                    $nazwa    = trim((string) ($u['imie_nazwisko'] ?? ''));
                    $inicjaly = '';
                    foreach (preg_split('/\s+/u', $nazwa, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $cz) {
                        $inicjaly .= mb_strtoupper(mb_substr($cz, 0, 1));
                        if (mb_strlen($inicjaly) >= 2) { break; }
                    }
                    if ($inicjaly === '') {
                        $inicjaly = mb_strtoupper(mb_substr((string) $u['login'], 0, 1));
                    }
                    ?>
                    <div class="admin-user<?= $jestem ? ' is-me' : '' ?>">
                        <div class="admin-user-head">
                            <span class="admin-user-avatar" aria-hidden="true"><?= e($inicjaly) ?></span>
                            <div class="admin-user-id">
                                <div class="admin-user-line1">
                                    <span class="admin-user-name"><?= e(user_label($u)) ?></span>
                                    <span class="role-badge role-<?= e($rola) ?>"><?= e($rola_l) ?></span>
                                    <?php if ($rola === 'uczen'): ?>
                                        <span class="admin-user-class">🎓 <?= $klasa !== '' ? e($klasa) : 'bez klasy' ?></span>
                                    <?php endif; ?>
                                    <?php if ($jestem): ?><span class="admin-you">to Ty</span><?php endif; ?>
                                </div>
                                <div class="admin-user-line2">
                                    <span class="admin-user-login" title="Login">👤 <?= e((string) $u['login']) ?></span>
                                </div>
                            </div>
                            <?php // Administrator nie ma własnego dziennika, więc nie pokazujemy mu wpisów ani godzin. ?>
                            <?php if ($rola === 'uczen'): ?>
                                <div class="admin-user-stats">
                                    <span title="Liczba dni z wpisem">📄 <?= $dni ?> <?= $dni === 1 ? 'wpis' : 'wpisów' ?></span>
                                    <span title="Łączny czas praktyk">⏱ <?= e(format_duration_compact($min)) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        // Pasek postępu praktyk ucznia: ile ma godzin i ile jeszcze brakuje.
                        $wym_u      = $rola === 'uczen' ? wymagane_godziny($uid) : 0;
                        $wym_min_u  = $wym_u * 60;
                        $proc_u     = $wym_min_u > 0 ? (int) round($min / $wym_min_u * 100) : 0;
                        $proc_bar_u = max(0, min(100, $proc_u));
                        $zostalo_u  = max(0, $wym_min_u - $min);
                        ?>
                        <?php if ($rola === 'uczen' && $wym_u > 0): ?>
                            <section class="progress admin-user-progress" aria-label="Postęp praktyk: <?= e(user_label($u)) ?>">
                                <div class="progress-head">
                                    <span class="progress-title">🎯 Postęp praktyk</span>
                                    <span class="progress-pct<?= $proc_u >= 100 ? ' is-done' : '' ?>"><?= $proc_u ?>%</span>
                                </div>
                                <div class="progress-track" role="progressbar"
                                     aria-valuemin="0" aria-valuemax="<?= $wym_u ?>"
                                     aria-valuenow="<?= min($wym_u, (int) round($min / 60)) ?>"
                                     aria-label="Wypracowane godziny z <?= $wym_u ?>">
                                    <div class="progress-fill<?= $proc_u >= 100 ? ' is-done' : '' ?>" style="width: <?= $proc_bar_u ?>%"></div>
                                </div>
                                <div class="progress-foot">
                                    <span><b><?= e(format_duration_compact($min)) ?></b> z <?= $wym_u ?> h</span>
                                    <?php if ($zostalo_u > 0): ?>
                                        <span>Zostało <b><?= e(format_duration_compact($zostalo_u)) ?></b></span>
                                    <?php else: ?>
                                        <span class="progress-done">✔ Wypracowano wymagane godziny!</span>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php elseif ($rola === 'uczen'): ?>
                            <p class="admin-user-progress-empty">🎯 Nie ustawiono wymaganej liczby godzin praktyk.</p>
                        <?php endif; ?>

                        <div class="admin-user-actions">
                            <?php if ($rola === 'uczen'): ?>
                                <a class="btn btn-today" href="index.php?uczen=<?= $uid ?>"
                                   title="Otwórz dziennik ucznia (możesz dodawać i edytować wpisy)">✏️ Otwórz dziennik</a>
                            <?php endif; ?>

                            <details class="admin-user-manage" data-user="<?= $uid ?>">
                                <summary class="btn btn-sm btn-manage">⚙️ Zarządzaj kontem</summary>
                                <div class="manage-body">
                                    <div class="manage-grid">
                                        <!-- Zmiana roli -->
                                        <form method="post" action="admin.php" class="manage-item">
                                            <input type="hidden" name="action" value="set_role">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <?= csrf_field() ?>
                                            <span class="manage-label">🎭 Rola konta</span>
                                            <div class="admin-inline">
                                                <select name="rola" aria-label="Rola konta <?= e((string) $u['login']) ?>">
                                                    <option value="uczen" <?= $rola === 'uczen' ? 'selected' : '' ?>>Uczeń</option>
                                                    <option value="admin" <?= $rola === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm">Zmień rolę</button>
                                            </div>
                                        </form>

                                        <?php if ($rola === 'uczen'): ?>
                                            <!-- Wymagane godziny praktyk -->
                                            <form method="post" action="admin.php" class="manage-item">
                                                <input type="hidden" name="action" value="set_wymagane">
                                                <input type="hidden" name="id" value="<?= $uid ?>">
                                                <?= csrf_field() ?>
                                                <span class="manage-label">🎯 Wymagane godziny praktyk</span>
                                                <div class="admin-inline">
                                                    <input type="number" name="wymagane_godziny" min="0" max="100000" step="1"
                                                           value="<?= $wym_u > 0 ? $wym_u : '' ?>"
                                                           placeholder="np. 120"
                                                           aria-label="Wymagane godziny praktyk dla <?= e((string) $u['login']) ?>">
                                                    <button type="submit" class="btn btn-sm">Zapisz godziny</button>
                                                </div>
                                                <span class="manage-hint">Puste pole (albo 0) usuwa wymaganie.</span>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Zmiana nazwy i klasy -->
                                        <form method="post" action="admin.php" class="manage-item">
                                            <input type="hidden" name="action" value="set_name">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <?= csrf_field() ?>
                                            <span class="manage-label">🪪 Imię i nazwisko<?= $rola === 'uczen' ? ' oraz klasa' : '' ?></span>
                                            <div class="admin-inline">
                                                <input type="text" name="imie_nazwisko" maxlength="120"
                                                       value="<?= e((string) ($u['imie_nazwisko'] ?? '')) ?>"
                                                       placeholder="Imię i nazwisko"
                                                       aria-label="Imię i nazwisko <?= e((string) $u['login']) ?>">
                                                <?php if ($rola === 'uczen'): ?>
                                                    <input type="text" name="klasa" maxlength="40"
                                                           value="<?= e($klasa) ?>"
                                                           placeholder="Klasa"
                                                           aria-label="Klasa <?= e((string) $u['login']) ?>">
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-sm">Zapisz dane</button>
                                            </div>
                                        </form>

                                        <!-- Nowe hasło -->
                                        <form method="post" action="admin.php" class="manage-item">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <?= csrf_field() ?>
                                            <span class="manage-label">🔑 Nowe hasło</span>
                                            <div class="admin-inline">
                                                <input type="password" name="haslo" minlength="6" autocomplete="new-password"
                                                       placeholder="min. 6 znaków"
                                                       aria-label="Nowe hasło dla <?= e((string) $u['login']) ?>">
                                                <button type="submit" class="btn btn-sm">Ustaw hasło</button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Usunięcie konta -->
                                    <form method="post" action="admin.php" class="manage-danger"
                                          onsubmit="return confirm('Usunąć konto „<?= e((string) $u['login']) ?>”?\nZostanie usunięty także jego dziennik wraz z załącznikami.<?= $dni > 0 ? '\n\nUwaga: konto ma ' . $dni . ' wpis(ów)!' : '' ?>');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $uid ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-sm" title="Usuń konto">🗑 Usuń konto</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </div>
                    <?php
                };
                ?>

                <div class="admin-tabs">
                    <input class="admin-tab-input" type="radio" name="admin-tab" id="tab-uczniowie" checked>
                    <input class="admin-tab-input" type="radio" name="admin-tab" id="tab-admini">

                    <div class="admin-tabbar" role="tablist" aria-label="Rodzaj konta">
                        <label class="admin-tab" for="tab-uczniowie" role="tab">
                            <span aria-hidden="true">🎓</span> Uczniowie
                            <span class="admin-tab-count"><?= count($uczniowie) ?></span>
                        </label>
                        <label class="admin-tab" for="tab-admini" role="tab">
                            <span aria-hidden="true">👑</span> Administratorzy
                            <span class="admin-tab-count"><?= count($admini) ?></span>
                        </label>
                    </div>

                    <div class="admin-tabpanel" data-tab="uczniowie">
                        <form method="get" action="admin.php" class="admin-search" role="search">
                            <span class="admin-search-ico" aria-hidden="true">🔎</span>
                            <input type="search" name="q" value="<?= e($q) ?>"
                                   placeholder="Szukaj ucznia po imieniu i nazwisku…"
                                   aria-label="Szukaj ucznia po imieniu i nazwisku" autocomplete="off">
                            <button type="submit" class="btn btn-sm">Szukaj</button>
                            <?php if ($q !== ''): ?>
                                <a class="btn btn-sm" href="admin.php">✕ Wyczyść</a>
                            <?php endif; ?>
                        </form>
                        <?php if ($uczniowie === []): ?>
                            <p class="admin-empty">
                                <?php if ($q !== ''): ?>
                                    Brak uczniów pasujących do „<?= e($q) ?>".
                                    <a href="admin.php">Wyczyść wyszukiwanie</a>.
                                <?php else: ?>
                                    Brak kont uczniów. Dodaj pierwsze konto w zakładce „➕ Nowe konto".
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <?php if ($q !== ''): ?>
                                <p class="admin-search-info">
                                    Znaleziono <b><?= count($uczniowie) ?></b>
                                    <?= count($uczniowie) === 1 ? 'ucznia' : 'uczniów' ?>
                                    dla „<b><?= e($q) ?></b>".
                                </p>
                            <?php endif; ?>
                            <?php if (count($klasy) > 1): ?>
                                <div class="admin-accordion-tools no-print">
                                    <button type="button" class="btn btn-sm" data-class-toggle="collapse">▾ Zwiń wszystkie</button>
                                    <button type="button" class="btn btn-sm" data-class-toggle="expand">▸ Rozwiń wszystkie</button>
                                </div>
                            <?php endif; ?>
                            <?php $idx = 0; foreach ($klasy as $nazwa => $lista): $idx++; ?>
                                <?php $stara = $nazwa === 'Bez klasy' ? '' : $nazwa; ?>
                                <?php $osob  = count($lista); ?>
                                <?php
                                // Łączna liczba wpisów w grupie — do ostrzeżenia przy usuwaniu klasy.
                                $dni_grupy = 0;
                                foreach ($lista as $u) {
                                    $dni_grupy += (int) ($stat[(int) $u['id']]['dni'] ?? 0);
                                }
                                ?>
                                <details class="admin-class-group" data-class="<?= e($nazwa) ?>" open>
                                    <summary class="admin-class-head">
                                        <span class="admin-class-chevron" aria-hidden="true">▸</span>
                                        <span class="admin-class-name" title="🎓 <?= e($nazwa) ?>">🎓 <?= e($nazwa) ?></span>
                                        <span class="admin-class-count"><?= $osob ?> <?= $osob === 1 ? 'uczeń' : 'uczniów' ?></span>
                                        <?php // „Zarządzaj klasą" — okienko działa też przy zwiniętym pasku (przy szukaniu ukryte). ?>
                                        <?php if ($q === ''): ?>
                                            <label for="class-mgr-<?= $idx ?>" class="btn btn-sm admin-class-manage no-print"
                                                   title="Zmień nazwę klasy wszystkim uczniom albo usuń całą klasę">
                                                <span aria-hidden="true">⚙️</span> Zarządzaj klasą
                                            </label>
                                        <?php endif; ?>
                                    </summary>
                                    <div class="admin-users">
                                        <?php foreach ($lista as $u) { $karta($u); } ?>
                                    </div>
                                </details>
                                <?php if ($q === ''): ?>
                                    <?php // Checkbox + okienko jako rodzeństwo paska: CSS pokazuje okno bez JS. ?>
                                    <input type="checkbox" class="class-mgr-input" id="class-mgr-<?= $idx ?>"
                                           aria-label="Zarządzaj klasą <?= e($nazwa) ?>">
                                    <div class="class-mgr no-print" role="dialog" aria-modal="true"
                                         aria-label="Zarządzaj klasą <?= e($nazwa) ?>">
                                        <label for="class-mgr-<?= $idx ?>" class="modal-backdrop" aria-label="Zamknij okno"></label>
                                        <div class="modal-card">
                                            <div class="class-mgr-head">
                                                <span class="class-mgr-ico" aria-hidden="true">⚙️</span>
                                                <div class="class-mgr-titles">
                                                    <h2 class="modal-title">Zarządzaj klasą <span class="class-mgr-name"><?= e($nazwa) ?></span></h2>
                                                    <p class="modal-hint">
                                                        <?= $osob ?> <?= $osob === 1 ? 'uczeń' : 'uczniów' ?>
                                                        · <?= $dni_grupy ?> <?= $dni_grupy === 1 ? 'wpis' : 'wpisów' ?>
                                                    </p>
                                                </div>
                                                <label for="class-mgr-<?= $idx ?>" class="modal-close no-print"
                                                       title="Zamknij (Esc)" aria-label="Zamknij">✕</label>
                                            </div>

                                            <?php // 1) Zmiana klasy całej grupy. ?>
                                            <form method="post" action="admin.php" class="class-mgr-form"
                                                  onsubmit="return confirm('Zmienić klasę wszystkim uczniom w tej grupie?');">
                                                <input type="hidden" name="action" value="set_klasa">
                                                <input type="hidden" name="from" value="<?= e($stara) ?>">
                                                <?= csrf_field() ?>
                                                <label class="class-mgr-label" for="klasa-grupa-<?= $idx ?>">Nowa nazwa klasy</label>
                                                <div class="admin-inline">
                                                    <input type="text" id="klasa-grupa-<?= $idx ?>" name="klasa" maxlength="40"
                                                           value="<?= e($stara) ?>"
                                                           placeholder="np. 3A"
                                                           aria-label="Nowa nazwa klasy dla grupy <?= e($nazwa) ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary">✏️ Zmień klasę</button>
                                                </div>
                                                <p class="modal-hint">Puste pole przenosi uczniów do grupy „Bez klasy”.</p>
                                            </form>

                                            <?php // 2) Usunięcie całej klasy (konta uczniów + dzienniki). ?>
                                            <?php if ($stara !== ''): ?>
                                                <form method="post" action="admin.php" class="manage-danger class-mgr-danger"
                                                      onsubmit="return confirm('Usunąć wszystkie konta uczniów tej klasy?\nUsunięte zostaną także ich dzienniki wraz z załącznikami. Tej operacji nie można cofnąć.<?= $dni_grupy > 0 ? '\n\nUwaga: te konta mają łącznie ' . $dni_grupy . ' wpis(ów)!' : '' ?>');">
                                                    <input type="hidden" name="action" value="delete_class">
                                                    <input type="hidden" name="from" value="<?= e($stara) ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-danger btn-sm">🗑 Usuń całą klasę (<?= $osob ?>)</button>
                                                    <p class="manage-hint">Trwale usuwa konta wszystkich uczniów tej klasy razem z ich dziennikami.</p>
                                                </form>
                                            <?php else: ?>
                                                <p class="class-mgr-note">
                                                    To uczniowie bez przypisanej klasy — usuń ich konta pojedynczo z listy powyżej.
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                        <?php endif; ?>
                    </div>

                    <div class="admin-tabpanel" data-tab="admini">
                        <form method="get" action="admin.php" class="admin-search" role="search">
                            <span class="admin-search-ico" aria-hidden="true">🔎</span>
                            <input type="search" name="qa" value="<?= e($qa) ?>"
                                   placeholder="Szukaj administratora po imieniu i nazwisku…"
                                   aria-label="Szukaj administratora po imieniu i nazwisku" autocomplete="off">
                            <button type="submit" class="btn btn-sm">Szukaj</button>
                            <?php if ($qa !== ''): ?>
                                <a class="btn btn-sm" href="admin.php">✕ Wyczyść</a>
                            <?php endif; ?>
                        </form>
                        <?php if ($admini === []): ?>
                            <p class="admin-empty">
                                <?php if ($qa !== ''): ?>
                                    Brak administratorów pasujących do „<?= e($qa) ?>".
                                    <a href="admin.php">Wyczyść wyszukiwanie</a>.
                                <?php else: ?>
                                    Brak kont administratorów.
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <?php if ($qa !== ''): ?>
                                <p class="admin-search-info">
                                    Znaleziono <b><?= count($admini) ?></b>
                                    <?= count($admini) === 1 ? 'administratora' : 'administratorów' ?>
                                    dla „<b><?= e($qa) ?></b>".
                                </p>
                            <?php endif; ?>
                            <div class="admin-users">
                                <?php foreach ($admini as $u) { $karta($u); } ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
                </div>
            </div>
        </article>

        <nav class="pagefoot no-print" aria-label="Informacje">
            <a href="admin.php" aria-current="page">👑 Panel admina</a>
            <a href="ustawienia.php">⚙️ Ustawienia</a>
            <a href="o-mnie.php">ℹ️ O mnie</a>
        </nav>
    </main>
</div>

<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: time() ?>"></script>
<script>
(function () {
    // Klasa jest wymagana tylko przy zakładaniu konta ucznia.
    var rola    = document.getElementById('nu-rola');
    var pole    = document.getElementById('nu-klasa-field');
    var klasa   = document.getElementById('nu-klasa');
    if (!rola || !pole || !klasa) { return; }
    function sync() {
        var uczen = rola.value === 'uczen';
        pole.hidden  = !uczen;
        klasa.required = uczen;
    }
    rola.addEventListener('change', sync);
    sync();
})();

(function () {
    // Zapamiętaj wybraną zakładkę kont (uczniowie / administratorzy) między odświeżeniami.
    var key    = 'dp-admin-tab';
    var inputs = document.querySelectorAll('.admin-tab-input');
    if (!inputs.length) { return; }
    var wymusz = document.body.dataset.adminTab || '';
    if (wymusz) { try { localStorage.setItem(key, wymusz); } catch (e) {} }
    try {
        var zapisana = wymusz || localStorage.getItem(key);
        var el = zapisana ? document.getElementById(zapisana) : null;
        if (el) { el.checked = true; }
    } catch (e) {}
    inputs.forEach(function (inp) {
        inp.addEventListener('change', function () {
            try { localStorage.setItem(key, inp.id); } catch (e) {}
        });
    });
})();

(function () {
    // Zapamiętaj wybraną zakładkę panelu (edycja / nowe konto).
    var key    = 'dp-admin-panel-tab';
    var inputs = document.querySelectorAll('.panel-tab-input');
    if (!inputs.length) { return; }
    var wymusz = document.body.dataset.panelTab || '';
    if (wymusz) { try { localStorage.setItem(key, wymusz); } catch (e) {} }
    try {
        var zapisana = wymusz || localStorage.getItem(key);
        var el = zapisana ? document.getElementById(zapisana) : null;
        if (el) { el.checked = true; }
    } catch (e) {}
    inputs.forEach(function (inp) {
        inp.addEventListener('change', function () {
            try { localStorage.setItem(key, inp.id); } catch (e) {}
        });
    });
})();

(function () {
    // Okienko „Nowe konto" (popup): otwieranie przyciskiem w pasku akcji ORAZ zakładką,
    // zamykanie przyciskiem ✕ i klawiszem Esc, kursor wskakuje w pole loginu.
    var tabNowe   = document.getElementById('ptab-nowe');
    var tabEdycja = document.getElementById('ptab-edycja');
    if (!tabNowe || !tabEdycja) { return; }

    function skupLogin() {
        var pole = document.getElementById('nu-login');
        if (!pole) { return; }
        try { pole.focus({ preventScroll: true }); } catch (e) { pole.focus(); }
    }
    function otworz() {
        tabNowe.checked = true;
        tabNowe.dispatchEvent(new Event('change'));
        skupLogin();
    }
    function zamknij() {
        tabEdycja.checked = true;
        tabEdycja.dispatchEvent(new Event('change'));
        var btn = document.getElementById('btn-new-account');
        if (btn) { try { btn.focus({ preventScroll: true }); } catch (e) { btn.focus(); } }
    }

    var btn = document.getElementById('btn-new-account');
    if (btn) { btn.addEventListener('click', otworz); }

    var close = document.getElementById('modal-close');
    if (close) { close.addEventListener('click', zamknij); }

    tabNowe.addEventListener('change', function () {
        if (tabNowe.checked) { skupLogin(); }
    });

    document.addEventListener('keydown', function (e) {
        if (tabNowe.checked && (e.key === 'Escape' || e.key === 'Esc')) {
            zamknij();
        }
    });

    // Prosta pułapka fokusu w okienku (spójna z aria-modal="true").
    var karta = document.getElementById('nowe-konto');
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || !tabNowe.checked || !karta) { return; }
        var f = karta.querySelectorAll(
            'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
        );
        if (!f.length) { return; }
        var first = f[0];
        var last  = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });
})();

(function () {
    // Klasy jako rozwijane paski: pamiętamy zwinięte klasy + przyciski Zwiń/Rozwiń wszystkie.
    var grupy = document.querySelectorAll('.admin-class-group');
    if (!grupy.length) { return; }
    var key = 'dp-admin-collapsed';

    function zapisz() {
        var zwiete = [];
        grupy.forEach(function (g) { if (!g.open) { zwiete.push(g.dataset.class); } });
        try { localStorage.setItem(key, JSON.stringify(zwiete)); } catch (e) {}
    }

    try {
        var zwiete = JSON.parse(localStorage.getItem(key) || '[]');
        grupy.forEach(function (g) {
            if (zwiete.indexOf(g.dataset.class) !== -1) { g.open = false; }
        });
    } catch (e) {}

    grupy.forEach(function (g) { g.addEventListener('toggle', zapisz); });

    document.querySelectorAll('[data-class-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var otworz = btn.getAttribute('data-class-toggle') === 'expand';
            grupy.forEach(function (g) { g.open = otworz; });
            zapisz();
        });
    });
})();

(function () {
    // Okienko „Zarządzaj klasą" (otwierane z nagłówka paska klasy, bez rozwijania grupy):
    // Esc zamyka, otwarcie ustawia kursor w polu nazwy klasy, zmiana zakładki zamyka okienko,
    // Tab nie ucieka poza otwarte okienko.
    var wejscia = document.querySelectorAll('.class-mgr-input');
    if (!wejscia.length) { return; }

    function okno(inp) {
        var el = inp.nextElementSibling;
        return el && el.classList.contains('class-mgr') ? el : null;
    }
    function zamknij(inp) {
        inp.checked = false;
        var lbl = document.querySelector('label.admin-class-manage[for="' + inp.id + '"]');
        if (lbl) { try { lbl.focus({ preventScroll: true }); } catch (e) { lbl.focus(); } }
    }

    wejscia.forEach(function (inp) {
        inp.addEventListener('change', function () {
            if (!inp.checked) { return; }
            var box  = inp.nextElementSibling;
            var pole = box ? box.querySelector('input[name="klasa"]') : null;
            if (!pole) { return; }
            try { pole.focus({ preventScroll: true }); } catch (e) { pole.focus(); }
            pole.select();
        });
    });

    document.addEventListener('keydown', function (e) {
        var otwarte = null;
        wejscia.forEach(function (inp) { if (inp.checked) { otwarte = inp; } });
        if (!otwarte) { return; }

        if (e.key === 'Escape' || e.key === 'Esc') {
            zamknij(otwarte);
            return;
        }

        // Prosta pułapka fokusu w otwartym okienku (spójna z aria-modal="true").
        if (e.key !== 'Tab') { return; }
        var box = okno(otwarte);
        if (!box) { return; }
        var f = box.querySelectorAll(
            'button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
        );
        if (!f.length) { return; }
        var first = f[0];
        var last  = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    // Zmiana zakładki (uczniowie / administratorzy) zamyka otwarte okienko klasy.
    document.querySelectorAll('.admin-tab-input').forEach(function (r) {
        r.addEventListener('change', function () {
            wejscia.forEach(function (inp) { inp.checked = false; });
        });
    });
})();

(function () {
    // Sekcje „Zarządzaj kontem": pamiętamy rozwinięte konta, żeby po zapisie
    // (przeładowaniu strony) sekcja nadal była otwarta i nie trzeba było jej szukać.
    var sekcje = document.querySelectorAll('.admin-user-manage');
    if (!sekcje.length) { return; }
    var key = 'dp-admin-open-user';

    function zapisz() {
        var otwarte = [];
        sekcje.forEach(function (s) { if (s.open) { otwarte.push(s.dataset.user); } });
        try { localStorage.setItem(key, JSON.stringify(otwarte)); } catch (e) {}
    }

    try {
        var otwarte = JSON.parse(localStorage.getItem(key) || '[]');
        sekcje.forEach(function (s) {
            if (otwarte.indexOf(s.dataset.user) !== -1) { s.open = true; }
        });
    } catch (e) {}

    sekcje.forEach(function (s) { s.addEventListener('toggle', zapisz); });
})();
</script>
</body>
</html>
