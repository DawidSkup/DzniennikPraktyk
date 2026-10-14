<?php
/**
 * Dziennik Praktyk — kalendarz (widok dnia / tygodnia).
 * Wyświetla jeden dzień na ekranie z nawigacją ← / → i formularzem wpisu.
 *
 * Strona główna z podsumowaniem godzin to `index.php`.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/auth.php';

// Wymagane zalogowanie (brak konta → kreator, brak sesji → logowanie).
auth_require_login();

// Kontekst: własny dziennik albo podgląd dziennika ucznia (administrator).
$ctx       = auth_view_context();
$view_user = $ctx['user'] ?? null;
$readonly  = (bool) $ctx['readonly'];
$view_id   = (int) ($view_user['id'] ?? 0);
$view_name = $view_user !== null ? user_label($view_user) : '';
$ctx_qs    = $readonly ? '&uczen=' . $view_id : ''; // doklejane do linków GET

/* ------------------------------------------------------------------ *
 *  Obsługa akcji (POST) — wzorzec POST/Redirect/GET
 * ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Podgląd dziennika ucznia (administrator) jest tylko do odczytu.
    if ($readonly) {
        header('Location: kalendarz.php?uczen=' . $view_id);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $data   = trim((string) ($_POST['data'] ?? ''));

    // Przekroczony limit post_max_size (np. zbyt duży załącznik) — PHP nie
    // wypełnia wtedy $_POST/$_FILES. Zgłaszamy czytelny błąd zamiast CSRF.
    if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        header('Location: kalendarz.php?zal=za-duzy');
        exit;
    }

    // Token CSRF wymagany przy każdej zmianie stanu.
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $target = 'kalendarz.php' . (is_valid_date($data) ? '?data=' . urlencode($data) . '&blad_csrf=1' : '?blad_csrf=1');
        header('Location: ' . $target);
        exit;
    }

    if ($action === 'add_file' && is_valid_date($data)) {
        $kod = zal_add($view_id, $data, $_FILES['zal'] ?? null);
        header('Location: kalendarz.php?data=' . urlencode($data) . '&zal=' . urlencode($kod));
        exit;
    }

    if ($action === 'delete_file' && is_valid_date($data)) {
        $plik_id = (int) ($_POST['id'] ?? 0);
        $plik    = $plik_id > 0 ? zal_get($plik_id) : null;
        // Usuwamy tylko plik należący do oglądanego dnia i konta.
        if ($plik !== null && (string) $plik['data'] === $data && (int) $plik['user_id'] === $view_id) {
            zal_delete($plik_id);
        }
        header('Location: kalendarz.php?data=' . urlencode($data) . '&zal=usunieto');
        exit;
    }

    if ($action === 'delete_all') {
        delete_all_wpisy($view_id);
        zal_delete_all($view_id);
        header('Location: kalendarz.php?usunieto=1');
        exit;
    }

    if (($action === 'save' || $action === 'delete') && is_valid_date($data)) {
        if ($action === 'delete') {
            zal_delete_for_date($view_id, $data);
            delete_wpis($view_id, $data);
            $target = prev_date($view_id, $data) ?? next_date($view_id, $data);
            header('Location: kalendarz.php' . ($target ? '?data=' . urlencode($target) : ''));
            exit;
        }

        $od   = trim((string) ($_POST['godzina_od'] ?? ''));
        $do   = trim((string) ($_POST['godzina_do'] ?? ''));
        $opis = trim((string) ($_POST['opis'] ?? ''));

        if (is_valid_time($od) && is_valid_time($do)) {
            save_wpis($view_id, $data, $od, $do, $opis);

            // Plik opcjonalnie dołączony w oknie „Dodaj wpis" — zapisujemy
            // razem z wpisem. Gdy nie wybrano pliku, nie robimy nic (żadnego
            // komunikatu o błędzie).
            $zal_kod = null;
            if (isset($_FILES['zal']) && is_array($_FILES['zal'])
                && (int) $_FILES['zal']['error'] !== UPLOAD_ERR_NO_FILE) {
                $zal_kod = zal_add($view_id, $data, $_FILES['zal']);
            }

            $qs = 'kalendarz.php?data=' . urlencode($data) . '&zapisano=1';
            if ($zal_kod !== null) {
                $qs .= '&zal=' . urlencode($zal_kod);
            }
            header('Location: ' . $qs);
            exit;
        }
    }

    // Nieprawidłowe dane – wracamy na stronę bez zapisu.
    header('Location: kalendarz.php' . (is_valid_date($data) ? '?data=' . urlencode($data) : ''));
    exit;
}

// --- Pobranie wpisów (jedno zapytanie) i wyliczenie danych pochodnych ----
// Listę dat, mapę wpisów, czasy pracy i statystyki liczymy z jednego
// zapytania, zamiast odpytywać bazę wielokrotnie.
$wszystkie    = all_wpisy($view_id);   // posortowane rosnąco po dacie
$ilosc_dni    = count($wszystkie);
$mapa_wpisow  = [];
$czas_wg_dnia = [];
$dates        = [];
foreach ($wszystkie as $w) {
    $d                   = (string) $w['data'];
    $mapa_wpisow[$d]     = $w;
    $dates[]             = $d;
    $czas_wg_dnia[$d]    = minutes_between((string) $w['godzina_od'], (string) $w['godzina_do']);
}

// --- Bieżąca data widoku -------------------------------------------------
$data = isset($_GET['data']) ? trim((string) $_GET['data']) : '';
if (!is_valid_date($data)) {
    $data = $dates !== [] ? $dates[$ilosc_dni - 1] : date('Y-m-d');
}

// Wpis oglądanego dnia (null, gdy dzień bez wpisu).
$wpis = $mapa_wpisow[$data] ?? null;

// Najbliższy wpis przed / po oglądanej dacie — z pobranej listy dat.
$prev = null;
$next = null;
foreach ($dates as $d) {
    if ($d < $data) {
        $prev = $d;
    } elseif ($d > $data) {
        $next = $d;
        break;
    }
}

$dzis = date('Y-m-d');

// --- Wybrany widok: „dzien" (jeden dzień) lub „tydzien" ------------------
$widok      = (($_GET['widok'] ?? 'dzien') === 'tydzien') ? 'tydzien' : 'dzien';
$tydzien    = $widok === 'tydzien';
$widok_qs   = $tydzien ? '&widok=tydzien' : ''; // doklejane do linków GET
$link_qs    = $widok_qs . $ctx_qs;               // widok + kontekst (podgląd ucznia)

// Tryb „Dodaj": po kliknięciu „Dodaj dzień" pokazujemy od razu sam formularz „Dodaj wpis".
$dodaj_flow = !$tydzien && (($_GET['dodaj'] ?? '') === '1');

// Data dla przycisku „Dodaj kolejny dzień": pierwszy dzień roboczy (pon–pt)
// po ostatnim wpisie; gdy dziennik pusty — dzisiaj (lub najbliższy poniedziałek).
$ostatni_wpis = $dates !== [] ? $dates[$ilosc_dni - 1] : null;
$dodaj_data   = $ostatni_wpis !== null ? date_shift($ostatni_wpis, 1) : $dzis;
$dow = (int) (new DateTime($dodaj_data))->format('N');
if ($dow >= 6) {
    $dodaj_data = date_shift($dodaj_data, 8 - $dow); // sob → pon (+2), niedz → pon (+1)
}

// Czy oglądany dzień to weekend (sobota/niedziela) — dni bez praktyk.
$dzien_weekend = (int) (new DateTime($data))->format('N') >= 6;

// --- Dane widoku tygodniowego (poniedziałek–niedziela) -------------------
$tydz_start  = week_start($data);
$tydz_end    = week_end($data);

// Unikalne tygodnie (poniedziałki), które mają jakikolwiek wpis — do listy wyboru.
$tygodnie = [];
foreach ($dates as $d) {
    $tygodnie[week_start($d)] = true;
}
$tygodnie = array_keys($tygodnie);
sort($tygodnie);
$tydz_dni    = [];
$tydz_total  = 0;
// Dni praktyk = poniedziałek–piątek (weekend to dni bez praktyk).
$tydz_dni_roboczych  = 5;
$tydz_filled_robocze = 0;
for ($i = 0; $i < 7; $i++) {
    $d   = date_shift($tydz_start, $i);
    $w   = $mapa_wpisow[$d] ?? null;
    $min = $w ? minutes_between((string) $w['godzina_od'], (string) $w['godzina_do']) : null;
    if ($min !== null) {
        $tydz_total += $min;
    }
    if ($w && $i < $tydz_dni_roboczych) {
        $tydz_filled_robocze++;
    }
    $tydz_dni[$d] = ['wpis' => $w, 'min' => $min];
}
$tydz_srednia = $tydz_filled_robocze > 0 ? intdiv($tydz_total, $tydz_filled_robocze) : 0;

// --- Cele nawigacji: w widoku tygodniowym skaczemy o 7 dni --------------
if ($tydzien) {
    $nav_prev = date_shift($data, -7);
    $nav_next = date_shift($data, 7);
    $prev_off = false;
    $next_off = false;
} else {
    $nav_prev = $prev;
    $nav_next = $next;
    $prev_off = $prev === null;
    $next_off = $next === null;
}
$nav_label_prev = $tydzien ? 'Poprzedni tydzień' : 'Poprzedni dzień';
$nav_label_next = $tydzien ? 'Następny tydzień' : 'Następny dzień';

$theme = day_theme($data);
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

$zapisano     = isset($_GET['zapisano']);
$usunieto     = isset($_GET['usunieto']);
$blad_csrf    = isset($_GET['blad_csrf']);
$zal_kod      = trim((string) ($_GET['zal'] ?? ''));

// Załączniki oglądanego dnia oraz liczba plików wg dnia (lista + widok tygodnia).
$zalaczniki = zal_list($view_id, $data);
$zal_counts = zal_counts($view_id);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicon.png">
    <title>Dziennik Praktyk — <?= $tydzien ? 'tydzień ' . e(week_number($data)) : e($data) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
    <script src="assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?: time() ?>"></script>
</head>
<body style="<?= e($theme_vars) ?>">
<div class="app">

    <!-- Nagłówek aplikacji: tylko tytuł przyklejony u góry -->
    <header class="topbar no-print">
        <div class="brand">
            <span class="brand-icon">📘</span>
            <div>
                <h1 class="brand-title">Dziennik Praktyk</h1>
                <p class="brand-sub">
                    <?php if ($readonly): ?>
                        Podgląd dziennika · <?= e($view_name) ?> · <?= count($dates) ?> <?= count($dates) === 1 ? 'wpis' : 'wpisów' ?>
                    <?php else: ?>
                        <?= count($dates) ?> <?= count($dates) === 1 ? 'wpis' : 'wpisów' ?> w dzienniku
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </header>

    <!-- Akcje: pasek chowający się w bok + przycisk „⋮" do przywołania -->
    <div class="actions-dock no-print">
        <div class="topbar-actions" id="actions-bar" role="toolbar" aria-label="Akcje">
            <a class="btn" href="index.php<?= $readonly ? '?uczen=' . $view_id : '' ?>" title="Strona główna — podsumowanie godzin">🏠 Start</a>
            <div class="theme-switch no-print" role="group" aria-label="Motyw: jasny, ciemny lub automatyczny">
                <button type="button" class="theme-btn" data-theme-mode="light" aria-pressed="false" title="Tryb jasny (dzień)">☀️</button>
                <button type="button" class="theme-btn" data-theme-mode="dark" aria-pressed="false" title="Tryb ciemny (noc)">🌙</button>
                <button type="button" class="theme-btn" data-theme-mode="auto" aria-pressed="true" title="Automatyczny (ustawienie systemu)">🌓</button>
            </div>
            <?php if (auth_is_admin()): ?>
                <a class="btn btn-admin" href="admin.php" title="Panel administratora">👑 Panel admina</a>
            <?php endif; ?>
            <?php if ($readonly): ?>
                <a class="btn btn-today" href="index.php" title="Wróć do swojego dziennika">↩ Mój dziennik</a>
            <?php else: ?>
                <a class="btn btn-today" href="kalendarz.php?data=<?= e(date('Y-m-d')) ?><?= $widok_qs ?>">Dzisiaj</a>
                <button type="button" class="btn btn-save" data-open-dodaj
                        title="Dodaj kolejny dzień (<?= e(format_date_full($dodaj_data)) ?>)">➕ Dodaj</button>
            <?php endif; ?>
            <button type="button" class="btn btn-print" onclick="window.print()">🖨 Drukuj</button>
            <?php if (!$readonly): ?>
                <a class="btn btn-icon" href="ustawienia.php" title="Ustawienia dziennika" aria-label="Ustawienia">⚙️</a>
            <?php endif; ?>
        </div>
        <button type="button" class="actions-toggle" id="actions-toggle"
                aria-controls="actions-bar" aria-expanded="true"
                title="Pokaż lub ukryj pasek akcji" aria-label="Pokaż lub ukryj pasek akcji">⋮</button>
    </div>

    <div class="layout">

        <!-- Panel lewy: lista dni -->
        <aside class="side no-print">
            <div class="panel">
                <h3>Dni w dzienniku</h3>
                <?php if ($dates): ?>
                    <ul class="day-list">
                        <?php foreach ($dates as $d): ?>
                            <li>
                                <a href="kalendarz.php?data=<?= e($d) ?><?= $link_qs ?>"
                                   class="<?= $d === $data ? 'active' : '' ?><?= (int) (new DateTime($d))->format('N') >= 6 ? ' is-weekend' : '' ?>">
                                    <span class="day-list-date"><?= e(format_date_short($d)) ?></span>
                                    <?php $mm = $czas_wg_dnia[$d] ?? null; ?>
                                    <?php if ($mm !== null): ?>
                                        <span class="day-list-time"><?= e(format_duration($mm)) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($zal_counts[$d])): ?>
                                        <span class="day-list-files" title="<?= (int) $zal_counts[$d] ?> załączników">📎<?= (int) $zal_counts[$d] ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="panel-empty">Brak wpisów.<br>Dodaj pierwszy dzień →</p>
                <?php endif; ?>
            </div>
        </aside>

        <main class="main">

    <!-- Przełącznik widoku: dzień / tydzień -->
    <div class="viewswitch no-print" role="group" aria-label="Przełącz widok">
        <a class="btn viewswitch-btn<?= $tydzien ? '' : ' btn-today' ?>"
           href="kalendarz.php?data=<?= e($data) ?><?= $ctx_qs ?>"<?= $tydzien ? '' : ' aria-current="true"' ?>>
            <span class="viewswitch-ico" aria-hidden="true">📅</span> Dzień
        </a>
        <a class="btn viewswitch-btn<?= $tydzien ? ' btn-today' : '' ?>"
           href="kalendarz.php?data=<?= e($data) ?>&widok=tydzien<?= $ctx_qs ?>"<?= $tydzien ? ' aria-current="true"' : '' ?>>
            <span class="viewswitch-ico" aria-hidden="true">🗓️</span> Tydzień
        </a>
    </div>

    <!-- Nawigacja między dniami / tygodniami -->
    <nav class="daynav no-print">
        <a class="daynav-arrow daynav-prev" href="kalendarz.php?data=<?= e($nav_prev ?? $data) ?><?= $link_qs ?>"
           <?= $prev_off ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '' ?>
           title="<?= e($nav_label_prev) ?>">←</a>

        <form class="jump" method="get" action="kalendarz.php">
            <?php if ($readonly): ?>
                <input type="hidden" name="uczen" value="<?= $view_id ?>">
            <?php endif; ?>
            <?php if ($tydzien): ?>
                <input type="hidden" name="widok" value="tydzien">
            <?php endif; ?>
            <?php if ($tydzien): ?>
                <select name="data" onchange="this.form.submit()" aria-label="Wybierz tydzień">
                    <?php if (!in_array($tydz_start, $tygodnie, true)): ?>
                        <option value="<?= e($tydz_start) ?>" selected>
                            <?= e(format_date_short($tydz_start)) ?> – <?= e(format_date_short($tydz_end)) ?>
                        </option>
                    <?php endif; ?>
                    <?php foreach ($tygodnie as $ws): ?>
                        <option value="<?= e($ws) ?>" <?= $ws === $tydz_start ? 'selected' : '' ?>>
                            <?= e(format_date_short($ws)) ?> – <?= e(format_date_short(date_shift($ws, 6))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <select name="data" onchange="this.form.submit()" aria-label="Wybierz dzień">
                    <?php if (!in_array($data, $dates, true)): ?>
                        <option value="<?= e($data) ?>" selected><?= e(format_date_full($data)) ?></option>
                    <?php endif; ?>
                    <?php foreach ($dates as $d): ?>
                        <option value="<?= e($d) ?>" <?= $d === $data ? 'selected' : '' ?>>
                            <?= e(format_date_full($d)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>

        <a class="daynav-arrow daynav-next" href="kalendarz.php?data=<?= e($nav_next ?? $data) ?><?= $link_qs ?>"
           <?= $next_off ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '' ?>
           title="<?= e($nav_label_next) ?>">→</a>
    </nav>

    <?php if ($readonly): ?>
        <p class="alert alert-info no-print">👀 Podglądasz dziennik ucznia: <b><?= e($view_name) ?></b>. Tryb tylko do odczytu.</p>
    <?php endif; ?>

    <?php if ($zapisano): ?>
        <p class="alert no-print">✔ Wpis został zapisany.</p>
    <?php endif; ?>

    <?php if ($usunieto): ?>
        <p class="alert alert-danger no-print">🧹 Usunięto wszystkie wpisy z dziennika.</p>
    <?php endif; ?>

    <?php if ($blad_csrf): ?>
        <p class="alert alert-danger no-print">⚠ Sesja wygasła. Odśwież stronę i spróbuj ponownie.</p>
    <?php endif; ?>

    <?php if ($zal_kod === 'ok'): ?>
        <p class="alert no-print">📎 Dodano plik do tego dnia.</p>
    <?php elseif ($zal_kod === 'usunieto'): ?>
        <p class="alert no-print">🗑 Usunięto plik.</p>
    <?php elseif ($zal_kod === 'brak'): ?>
        <p class="alert alert-danger no-print">⚠ Nie wybrano pliku.</p>
    <?php elseif ($zal_kod === 'za-duzy'): ?>
        <p class="alert alert-danger no-print">⚠ Plik jest za duży (maks. <?= e(format_bytes(zal_max_bytes())) ?>).</p>
    <?php elseif ($zal_kod === 'typ'): ?>
        <p class="alert alert-danger no-print">⚠ Niedozwolony typ pliku.</p>
    <?php elseif ($zal_kod === 'blad-uploadu'): ?>
        <p class="alert alert-danger no-print">⚠ Nie udało się wgrać pliku na serwer.</p>
    <?php elseif ($zal_kod === 'blad-zapisu'): ?>
        <p class="alert alert-danger no-print">⚠ Nie udało się zapisać pliku na serwerze.</p>
    <?php endif; ?>

    <?php if ($tydzien): ?>
    <!-- Dokument tygodnia (widok do wydruku) -->
    <article class="sheet week-sheet">
        <header class="sheet-head">
            <div>
                <p class="sheet-kicker">Dziennik Praktyk · tydzień <?= e(week_number($data)) ?></p>
                <p class="sheet-date"><?= e(format_date_short($tydz_start)) ?> — <?= e(format_date_short($tydz_end)) ?></p>
            </div>
            <span class="status-pill <?= $tydz_filled_robocze > 0 ? 'is-done' : 'is-empty' ?>">
                <?= $tydz_filled_robocze ?> z <?= $tydz_dni_roboczych ?> dni praktyk
            </span>
        </header>

        <div class="week-facts no-print">
            <div class="week-fact">
                <span>Łączny czas pracy</span>
                <b><?= $tydz_total > 0 ? e(format_duration($tydz_total)) : '—' ?></b>
            </div>
            <div class="week-fact">
                <span>Dni z wpisem</span>
                <b><?= $tydz_filled_robocze ?> / <?= $tydz_dni_roboczych ?></b>
            </div>
            <div class="week-fact">
                <span>Średnio / dzień</span>
                <b><?= $tydz_filled_robocze > 0 ? e(format_duration($tydz_srednia)) : '—' ?></b>
            </div>
        </div>

        <div class="week-scroll">
            <table class="week-table">
                <thead>
                    <tr>
                        <th class="wk-day" scope="col">Dzień</th>
                        <th class="wk-hours" scope="col">Godziny i czas</th>
                        <th class="wk-opis" scope="col">Wykonane czynności</th>
                        <th class="wk-edit no-print" scope="col"><span class="sr-only">Akcje</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tydz_dni as $d => $info): $w = $info['wpis']; $mm = $info['min']; ?>
                    <?php
                        $weekend = (int) (new DateTime($d))->format('N') >= 6;
                        $klasy = $w ? 'is-done' : 'is-empty';
                        if ($weekend) {
                            $klasy .= ' is-weekend';
                        }
                        if ($d === $dzis) {
                            $klasy .= ' is-today';
                        }
                        if ($d === $data) {
                            $klasy .= ' is-active';
                        }
                    ?>
                    <tr class="<?= $klasy ?>">
                        <th scope="row" class="wk-day">
                            <span class="wk-daycell">
                                <span class="wk-dot" aria-hidden="true"></span>
                                <span class="wk-daytext">
                                    <span class="wk-weekday"><?= e(format_weekday_short($d)) ?></span>
                                    <span class="wk-date"><?= e((new DateTime($d))->format('d.m.Y')) ?><?= $d === $dzis ? ' <span class="wk-today">dziś</span>' : '' ?></span>
                                    <?php if (!empty($zal_counts[$d])): ?>
                                        <span class="wk-files" title="Załączniki: <?= (int) $zal_counts[$d] ?>">📎 <?= (int) $zal_counts[$d] ?></span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </th>
                        <td class="wk-hours">
                            <?php if ($w): ?>
                                <span class="wk-range">
                                    <span class="chip chip-from"><?= e($w['godzina_od']) ?: '—' ?></span>
                                    <span class="chip-sep">–</span>
                                    <span class="chip chip-to"><?= e($w['godzina_do']) ?: '—' ?></span>
                                </span>
                                <?php if ($mm !== null): ?>
                                    <span class="wk-dur-pill"><?= e(format_duration_compact($mm)) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="wk-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="wk-opis">
                            <?php if ($w && trim((string) $w['opis']) !== ''): ?>
                                <?= nl2br(e($w['opis'])) ?>
                            <?php elseif ($w): ?>
                                <span class="wk-none">(brak opisu)</span>
                            <?php elseif ($weekend): ?>
                                <span class="wk-off">Dzień bez praktyk</span>
                            <?php else: ?>
                                <span class="wk-none">Brak wpisu</span>
                            <?php endif; ?>
                        </td>
                        <td class="wk-edit no-print">
                            <?php if ($w): ?>
                                <a class="btn wk-edit-link" href="kalendarz.php?data=<?= e($d) ?><?= $ctx_qs ?>">
                                    <span class="wk-edit-ico" aria-hidden="true">📄</span> Otwórz
                                </a>
                            <?php elseif ($readonly): ?>
                                <span class="wk-none">—</span>
                            <?php elseif ($weekend): ?>
                                <a class="btn btn-today wk-edit-link" href="kalendarz.php?data=<?= e($d) ?><?= $ctx_qs ?>"
                                   onclick="return confirm('Czy na pewno chcesz dodać wpis w weekend?\nSobota i niedziela to dni bez praktyk.');">
                                    <span class="wk-edit-ico" aria-hidden="true">➕</span> Dodaj
                                </a>
                            <?php else: ?>
                                <a class="btn btn-today wk-edit-link" href="kalendarz.php?data=<?= e($d) ?><?= $ctx_qs ?>">
                                    <span class="wk-edit-ico" aria-hidden="true">➕</span> Dodaj
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" class="wk-day">Razem</th>
                        <td class="wk-hours wk-total"><?= $tydz_total > 0 ? e(format_duration($tydz_total)) : '—' ?></td>
                        <td class="wk-opis wk-total"><?= $tydz_filled_robocze ?> z <?= $tydz_dni_roboczych ?> dni praktyk z wpisem</td>
                        <td class="wk-edit no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="signature">
            <span>Podpis opiekuna praktyk</span>
            <span class="line"></span>
        </div>
    </article>
    <?php else: ?>
    <?php if ($dodaj_flow && !$wpis): ?>
        <!-- Tryb „Dodaj": bez pustego dokumentu — od razu formularz -->
    <?php else: ?>
    <!-- Dokument dnia (widok do wydruku) -->
    <article class="sheet<?= $dzien_weekend ? ' sheet-weekend' : '' ?>">
        <header class="sheet-head">
            <div>
                <p class="sheet-kicker">Dziennik Praktyk</p>
                <p class="sheet-date"><?= e(format_date_full($data)) ?></p>
            </div>
            <div class="sheet-pills">
                <?php if ($dzien_weekend): ?>
                    <span class="status-pill status-pill-weekend">Dzień bez praktyk</span>
                <?php endif; ?>
                <span class="status-pill <?= $wpis ? 'is-done' : 'is-empty' ?>">
                    <?= $wpis ? 'Uzupełniony' : 'Brak wpisu' ?>
                </span>
            </div>
        </header>

        <?php if ($wpis): ?>
            <div class="info-grid">
                <div class="info">
                    <span class="info-label">Godziny pracy</span>
                    <span class="info-value">
                        <span class="chip chip-from"><?= e($wpis['godzina_od']) ?: '—' ?></span>
                        <span class="chip-sep">→</span>
                        <span class="chip chip-to"><?= e($wpis['godzina_do']) ?: '—' ?></span>
                    </span>
                </div>
            </div>

            <section class="opis">
                <h2>Wykonane czynności</h2>
                <p><?= nl2br(e($wpis['opis'])) ?: '<em>(brak opisu)</em>' ?></p>
            </section>
        <?php else: ?>
            <p class="empty">Brak wpisu na ten dzień. Uzupełnij formularz poniżej i zapisz.</p>
        <?php endif; ?>

        <?php if ($zalaczniki): ?>
        <section class="zalaczniki">
            <h2>Załączniki <span class="zal-count"><?= count($zalaczniki) ?></span></h2>
            <ul class="zal-list">
                <?php foreach ($zalaczniki as $z): $img = zal_is_inline_image((string) $z['typ']); ?>
                    <li class="zal-item">
                        <a class="zal-link" href="plik.php?id=<?= (int) $z['id'] ?>"
                           target="_blank" rel="noopener" title="<?= $img ? 'Powiększ' : 'Otwórz lub pobierz' ?>">
                            <?php if ($img): ?>
                                <img class="zal-thumb" src="plik.php?id=<?= (int) $z['id'] ?>"
                                     alt="<?= e($z['nazwa']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="zal-ico" aria-hidden="true">📄</span>
                            <?php endif; ?>
                        </a>
                        <div class="zal-info">
                            <a class="zal-name" href="plik.php?id=<?= (int) $z['id'] ?>"
                               target="_blank" rel="noopener"><?= e($z['nazwa']) ?></a>
                            <span class="zal-size"><?= e(format_bytes((int) $z['rozmiar'])) ?></span>
                        </div>
                        <?php if (!$readonly): ?>
                        <form method="post" action="kalendarz.php" class="zal-del no-print"
                              onsubmit="return confirm('Usunąć plik „<?= e($z['nazwa']) ?>”?');">
                            <input type="hidden" name="action" value="delete_file">
                            <input type="hidden" name="data" value="<?= e($data) ?>">
                            <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm" title="Usuń plik">🗑</button>
                        </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($wpis): ?>
            <div class="signature">
                <span>Podpis opiekuna praktyk</span>
                <span class="line"></span>
            </div>
        <?php endif; ?>
    </article>
    <?php endif; /* tryb „Dodaj" – ukrywamy pusty dokument */ ?>
    <?php endif; ?>

    <?php if ($tydzien): ?>
        <?php if ($readonly): ?>
            <p class="week-notice no-print">
                To podgląd całego tygodnia — do przeglądu i wydruku.
                <a href="kalendarz.php?data=<?= e($data) ?><?= $ctx_qs ?>">Przejdź do widoku dnia</a>.
            </p>
        <?php else: ?>
            <p class="week-notice no-print">
                To podgląd całego tygodnia — do przeglądu i wydruku. Aby dodać nowy lub poprawić
                istniejący wpis, <a href="kalendarz.php?data=<?= e($data) ?>">przejdź do widoku dnia</a>
                albo skorzystaj z przycisku przy wybranym dniu.
            </p>
        <?php endif; ?>
    <?php else: ?>
    <?php if (!$readonly): ?>
    <!-- Formularz (nie drukuje się) -->
    <section class="form-card no-print" id="dodaj-wpis">
        <header class="form-head">
            <span class="form-head-icon"><?= $wpis ? '✏️' : '📝' ?></span>
            <div>
                <h2 class="form-title"><?= $wpis ? 'Edytuj wpis' : 'Dodaj wpis' ?></h2>
                <p class="form-hint"><?= $wpis
                    ? 'Zmień godziny lub opis, a następnie zapisz zmiany.'
                    : 'Uzupełnij godziny pracy i opisz wykonane czynności.' ?></p>
            </div>
        </header>

        <form method="post" action="kalendarz.php" class="form-body">
            <input type="hidden" name="action" value="save">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="field field-date">
                    <label for="f-data">Data</label>
                    <input id="f-data" type="date" name="data" value="<?= e($data) ?>" required>
                </div>
                <div class="field">
                    <label for="f-od">Godzina od</label>
                    <input id="f-od" type="time" name="godzina_od" value="<?= e($wpis['godzina_od'] ?? '08:00') ?>">
                </div>
                <div class="field">
                    <label for="f-do">Godzina do</label>
                    <input id="f-do" type="time" name="godzina_do" value="<?= e($wpis['godzina_do'] ?? '16:00') ?>">
                </div>
            </div>

            <div class="field">
                <label for="f-opis">Co robiłem/am</label>
                <textarea id="f-opis" name="opis" rows="6"
                          placeholder="Opisz wykonane czynności..."><?= e($wpis['opis'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-save">💾 Zapisz wpis</button>
                <?php if ($wpis): ?>
                    <button type="submit" class="btn btn-danger" form="delete-form">🗑 Usuń wpis</button>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($wpis): ?>
            <form id="delete-form" method="post" action="kalendarz.php"
                  onsubmit="return confirm('Usunąć wpis z dnia <?= e($data) ?>?\nUsunięte zostaną też załączniki tego dnia.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="data" value="<?= e($data) ?>">
                <?= csrf_field() ?>
            </form>
        <?php endif; ?>

        <div class="zal-upload">
            <h3 class="zal-upload-title">📎 Zdjęcie lub plik do tego dnia</h3>
            <form method="post" action="kalendarz.php" enctype="multipart/form-data" class="zal-upload-form">
                <input type="hidden" name="action" value="add_file">
                <input type="hidden" name="data" value="<?= e($data) ?>">
                <?= csrf_field() ?>
                <input class="zal-file" type="file" name="zal" required
                       aria-label="Wybierz zdjęcie lub plik">
                <button type="submit" class="btn btn-save">⬆ Dodaj plik</button>
            </form>
            <p class="zal-hint">
                Maksymalny rozmiar pliku: <b><?= e(format_bytes(zal_max_bytes())) ?></b>.
                Zdjęcia pokazują się jako miniatury, pozostałe pliki można pobrać.
            </p>
        </div>
    </section>
    <?php endif; /* formularz edycji — ukryty w podglądzie */ ?>
    <?php endif; ?>

        <!-- Panel prawy: motyw i skróty (podsumowanie godzin jest na stronie głównej) -->
        <aside class="side no-print">
            <div class="side-stack">
                <div class="panel">
                    <h3>Motyw dnia</h3>
                    <div class="swatches">
                        <span class="swatch" style="background: <?= e($theme['primary']) ?>"></span>
                        <span class="swatch" style="background: <?= e($theme['accent']) ?>"></span>
                        <span class="swatch" style="background: <?= e($theme['tint']) ?>"></span>
                    </div>
                </div>

                <div class="panel">
                    <h3>Skróty</h3>
                    <ul class="hints">
                        <li><span class="kbd">←</span> <span class="kbd">→</span> <?= $tydzien ? 'zmiana tygodnia' : 'zmiana dnia' ?></li>
                        <li><span class="kbd">T</span> dzień / tydzień</li>
                        <li><span class="kbd">Ctrl</span> + <span class="kbd">P</span> wydruk <?= $tydzien ? 'tygodnia' : 'dnia' ?></li>
                    </ul>
                </div>
            </div>
        </aside>

    </div><!-- /layout -->

    <footer class="footer no-print">
        Dziennik Praktyk · przełączaj dni i tygodnie strzałkami ← → · widok: <?= $tydzien ? 'tygodniowy' : 'dzienny' ?>
    </footer>

    <!-- Strefa usuwania: na samym dole strony -->
    <?php if ($ilosc_dni > 0 && !$readonly): ?>
        <section class="danger-zone no-print">
            <div class="danger-text">
                <strong>Strefa usuwania</strong>
                <span>Trwale usuwa wszystkie wpisy z dziennika. Tej operacji nie można cofnąć.</span>
            </div>
            <form method="post" action="kalendarz.php"
                  onsubmit="return confirm('UWAGA! Trwale usuniesz WSZYSTKIE wpisy z dziennika. Kontynuować?');">
                <input type="hidden" name="action" value="delete_all">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">🗑 Usuń wszystko</button>
            </form>
        </section>
    <?php endif; ?>

    <!-- Linki informacyjne na samym dole -->
    <nav class="pagefoot no-print" aria-label="Informacje">
        <a href="index.php<?= $readonly ? '?uczen=' . $view_id : '' ?>">🏠 Start</a>
        <a href="kalendarz.php?data=<?= e($data) ?><?= $link_qs ?>" aria-current="page">📅 Kalendarz</a>
        <?php if (!$readonly): ?>
            <a href="ustawienia.php">⚙️ Ustawienia</a>
        <?php endif; ?>
        <a href="o-mnie.php">ℹ️ O mnie</a>
    </nav>

    <?php if (!$readonly): ?>
    <!-- Popup „Dodaj wpis" -->
    <div class="modal no-print" id="dodaj-modal" hidden>
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="dodaj-modal-title">
            <header class="modal-head">
                <span class="modal-icon">📝</span>
                <div>
                    <h2 id="dodaj-modal-title" class="modal-title">Dodaj wpis</h2>
                    <p class="modal-hint">Uzupełnij godziny pracy i opisz wykonane czynności.</p>
                </div>
            </header>
            <form method="post" action="kalendarz.php" class="modal-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="m-data">Data</label>
                    <input id="m-data" type="date" name="data" value="<?= e($dodaj_data) ?>" required>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="m-od">Godzina od</label>
                        <input id="m-od" type="time" name="godzina_od" value="08:00">
                    </div>
                    <div class="field">
                        <label for="m-do">Godzina do</label>
                        <input id="m-do" type="time" name="godzina_do" value="16:00">
                    </div>
                </div>
                <div class="field">
                    <label for="m-opis">Co robiłem/am</label>
                    <textarea id="m-opis" name="opis" rows="5" placeholder="Opisz wykonane czynności..."></textarea>
                </div>
                <div class="field">
                    <label for="m-zal">Załącznik — zdjęcie lub plik (opcjonalnie)</label>
                    <input id="m-zal" class="zal-file" type="file" name="zal"
                           aria-label="Wybierz zdjęcie lub plik (opcjonalnie)">
                    <p class="zal-hint">
                        Maks. <b><?= e(format_bytes(zal_max_bytes())) ?></b>.
                        Zdjęcia pokazują się jako miniatury, pozostałe pliki można pobrać.
                    </p>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-save">💾 Zapisz wpis</button>
                    <button type="button" class="btn" data-close-modal>Anuluj</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; /* popup dodawania — ukryty w podglądzie */ ?>

</div>
<?php if ($dodaj_flow && !$readonly): ?><script>window.__dpDodajAutoOpen = true;</script><?php endif; ?>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: time() ?>"></script>
</body>
</html>
