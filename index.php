<?php
/**
 * Dziennik Praktyk — strona główna.
 * Wyświetla jeden dzień na ekranie z nawigacją ← / → i formularzem wpisu.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/functions.php';

/* ------------------------------------------------------------------ *
 *  Obsługa akcji (POST) — wzorzec POST/Redirect/GET
 * ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $data   = trim((string) ($_POST['data'] ?? ''));

    if ($action === 'delete_all') {
        delete_all_wpisy();
        header('Location: index.php?usunieto=1');
        exit;
    }

    if (($action === 'save' || $action === 'delete') && is_valid_date($data)) {
        if ($action === 'delete') {
            delete_wpis($data);
            $target = prev_date($data) ?? next_date($data);
            header('Location: index.php' . ($target ? '?data=' . urlencode($target) : ''));
            exit;
        }

        $od   = trim((string) ($_POST['godzina_od'] ?? ''));
        $do   = trim((string) ($_POST['godzina_do'] ?? ''));
        $opis = trim((string) ($_POST['opis'] ?? ''));

        if (is_valid_time($od) && is_valid_time($do)) {
            save_wpis($data, $od, $do, $opis);
            header('Location: index.php?data=' . urlencode($data) . '&zapisano=1');
            exit;
        }
    }

    // Nieprawidłowe dane – wracamy na stronę bez zapisu.
    header('Location: index.php' . (is_valid_date($data) ? '?data=' . urlencode($data) : ''));
    exit;
}

// --- Bieżąca data widoku -------------------------------------------------
$data = isset($_GET['data']) ? trim((string) $_GET['data']) : '';
if (!is_valid_date($data)) {
    $data = last_date() ?? date('Y-m-d');
}

// --- Wybrany widok: „dzien" (jeden dzień) lub „tydzien" ------------------
$widok      = (($_GET['widok'] ?? 'dzien') === 'tydzien') ? 'tydzien' : 'dzien';
$tydzien    = $widok === 'tydzien';
$widok_qs   = $tydzien ? '&widok=tydzien' : ''; // doklejane do linków GET

$wpis  = get_wpis($data);
$prev  = prev_date($data);
$next  = next_date($data);
$dates = all_dates();
$dzis  = date('Y-m-d');

// --- Dane widoku tygodniowego (poniedziałek–niedziela) -------------------
$tydz_start  = week_start($data);
$tydz_end    = week_end($data);
$mapa_wpisow = wpisy_map();

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

$zapisano = isset($_GET['zapisano']);
$usunieto = isset($_GET['usunieto']);

// --- Statystyki do panelu bocznego --------------------------------------
$wszystkie = all_wpisy();
$total_min  = 0;
$dni_z_czasem = 0;
foreach ($wszystkie as $w) {
    $min = minutes_between((string) $w['godzina_od'], (string) $w['godzina_do']);
    if ($min !== null) {
        $total_min   += $min;
        $dni_z_czasem++;
    }
}
$ilosc_dni   = count($wszystkie);
$srednia_min = $dni_z_czasem > 0 ? intdiv($total_min, $dni_z_czasem) : 0;
$dzien_min   = $wpis ? minutes_between((string) $wpis['godzina_od'], (string) $wpis['godzina_do']) : null;

// Czas pracy każdego dnia (do listy po lewej) + zakres dat
$czas_wg_dnia = [];
foreach ($wszystkie as $w) {
    $czas_wg_dnia[$w['data']] = minutes_between((string) $w['godzina_od'], (string) $w['godzina_do']);
}
$pierwszy = $ilosc_dni > 0 ? format_date_short($wszystkie[0]['data']) : '—';
$ostatni  = $ilosc_dni > 0 ? format_date_short($wszystkie[$ilosc_dni - 1]['data']) : '—';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dziennik Praktyk — <?= $tydzien ? 'tydzień ' . e(week_number($data)) : e($data) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
</head>
<body style="<?= e($theme_vars) ?>">
<div class="app">

    <!-- Nagłówek aplikacji -->
    <header class="topbar no-print">
        <div class="brand">
            <span class="brand-icon">📘</span>
            <div>
                <h1 class="brand-title">Dziennik Praktyk</h1>
                <p class="brand-sub"><?= count($dates) ?> <?= count($dates) === 1 ? 'wpis' : 'wpisów' ?> w dzienniku</p>
            </div>
        </div>
        <div class="topbar-actions">
            <a class="btn btn-today" href="index.php?data=<?= e(date('Y-m-d')) ?><?= $widok_qs ?>">Dzisiaj</a>
            <button type="button" class="btn btn-print" onclick="window.print()">🖨 Drukuj</button>
        </div>
    </header>

    <div class="layout">

        <!-- Panel lewy: lista dni -->
        <aside class="side no-print">
            <div class="panel">
                <h3>Dni w dzienniku</h3>
                <?php if ($dates): ?>
                    <ul class="day-list">
                        <?php foreach ($dates as $d): ?>
                            <li>
                                <a href="index.php?data=<?= e($d) ?><?= $widok_qs ?>" class="<?= $d === $data ? 'active' : '' ?>">
                                    <span class="day-list-date"><?= e(format_date_short($d)) ?></span>
                                    <?php $mm = $czas_wg_dnia[$d] ?? null; ?>
                                    <?php if ($mm !== null): ?>
                                        <span class="day-list-time"><?= e(format_duration($mm)) ?></span>
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
           href="index.php?data=<?= e($data) ?>"<?= $tydzien ? '' : ' aria-current="true"' ?>>
            <span class="viewswitch-ico" aria-hidden="true">📅</span> Dzień
        </a>
        <a class="btn viewswitch-btn<?= $tydzien ? ' btn-today' : '' ?>"
           href="index.php?data=<?= e($data) ?>&widok=tydzien"<?= $tydzien ? ' aria-current="true"' : '' ?>>
            <span class="viewswitch-ico" aria-hidden="true">🗓️</span> Tydzień
        </a>
    </div>

    <!-- Nawigacja między dniami / tygodniami -->
    <nav class="daynav no-print">
        <a class="daynav-arrow daynav-prev" href="index.php?data=<?= e($nav_prev ?? $data) ?><?= $widok_qs ?>"
           <?= $prev_off ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '' ?>
           title="<?= e($nav_label_prev) ?>">←</a>

        <form class="jump" method="get" action="index.php">
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

        <a class="daynav-arrow daynav-next" href="index.php?data=<?= e($nav_next ?? $data) ?><?= $widok_qs ?>"
           <?= $next_off ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '' ?>
           title="<?= e($nav_label_next) ?>">→</a>
    </nav>

    <?php if ($zapisano): ?>
        <p class="alert no-print">✔ Wpis został zapisany.</p>
    <?php endif; ?>

    <?php if ($usunieto): ?>
        <p class="alert alert-danger no-print">🧹 Usunięto wszystkie wpisy z dziennika.</p>
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
                                <a class="btn wk-edit-link" href="index.php?data=<?= e($d) ?>">
                                    <span class="wk-edit-ico" aria-hidden="true">📄</span> Otwórz
                                </a>
                            <?php elseif ($weekend): ?>
                                <a class="btn btn-today wk-edit-link" href="index.php?data=<?= e($d) ?>"
                                   onclick="return confirm('Czy na pewno chcesz dodać wpis w weekend?\nSobota i niedziela to dni bez praktyk.');">
                                    <span class="wk-edit-ico" aria-hidden="true">➕</span> Dodaj
                                </a>
                            <?php else: ?>
                                <a class="btn btn-today wk-edit-link" href="index.php?data=<?= e($d) ?>">
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
    <!-- Dokument dnia (widok do wydruku) -->
    <article class="sheet">
        <header class="sheet-head">
            <div>
                <p class="sheet-kicker">Dziennik Praktyk</p>
                <p class="sheet-date"><?= e(format_date_full($data)) ?></p>
            </div>
            <span class="status-pill <?= $wpis ? 'is-done' : 'is-empty' ?>">
                <?= $wpis ? 'Uzupełniony' : 'Brak wpisu' ?>
            </span>
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

            <div class="signature">
                <span>Podpis opiekuna praktyk</span>
                <span class="line"></span>
            </div>
        <?php else: ?>
            <p class="empty">Brak wpisu na ten dzień. Uzupełnij formularz poniżej i zapisz.</p>
        <?php endif; ?>
    </article>
    <?php endif; ?>

    <?php if ($tydzien): ?>
        <p class="week-notice no-print">
            To podgląd całego tygodnia — do przeglądu i wydruku. Aby dodać nowy lub poprawić
            istniejący wpis, <a href="index.php?data=<?= e($data) ?>">przejdź do widoku dnia</a>
            albo skorzystaj z przycisku przy wybranym dniu.
        </p>
    <?php else: ?>
    <!-- Formularz (nie drukuje się) -->
    <section class="form-card no-print">
        <header class="form-head">
            <span class="form-head-icon"><?= $wpis ? '✏️' : '📝' ?></span>
            <div>
                <h2 class="form-title"><?= $wpis ? 'Edytuj wpis' : 'Dodaj wpis' ?></h2>
                <p class="form-hint"><?= $wpis
                    ? 'Zmień godziny lub opis, a następnie zapisz zmiany.'
                    : 'Uzupełnij godziny pracy i opisz wykonane czynności.' ?></p>
            </div>
        </header>

        <form method="post" action="index.php" class="form-body">
            <input type="hidden" name="action" value="save">

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
            <form id="delete-form" method="post" action="index.php"
                  onsubmit="return confirm('Usunąć wpis z dnia <?= e($data) ?>?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="data" value="<?= e($data) ?>">
            </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

        <!-- Panel prawy: statystyki -->
        <aside class="side no-print">
            <div class="side-stack">
                <div class="panel">
                    <h3>Podsumowanie</h3>
                    <div class="stat"><span>Dni z wpisem</span><b><?= $ilosc_dni ?></b></div>
                    <div class="stat"><span>Łącznie godzin</span><b><?= $total_min > 0 ? e(format_duration($total_min)) : '—' ?></b></div>
                    <div class="stat"><span>Średnio / dzień</span><b><?= $dni_z_czasem > 0 ? e(format_duration($srednia_min)) : '—' ?></b></div>
                    <div class="stat"><span>Od</span><b><?= $ilosc_dni > 0 ? e((new DateTime($wszystkie[0]['data']))->format('d.m.Y')) : '—' ?></b></div>
                    <div class="stat"><span>Do</span><b><?= $ilosc_dni > 0 ? e((new DateTime($wszystkie[$ilosc_dni - 1]['data']))->format('d.m.Y')) : '—' ?></b></div>
                </div>

                <?php if ($tydzien): ?>
                <div class="panel">
                    <h3>Ten tydzień</h3>
                    <div class="stat"><span>Dni z wpisem</span><b><?= $tydz_filled_robocze ?> / <?= $tydz_dni_roboczych ?></b></div>
                    <div class="stat"><span>Łącznie godzin</span><b><?= $tydz_total > 0 ? e(format_duration($tydz_total)) : '—' ?></b></div>
                    <div class="stat"><span>Tydzień</span><b>nr <?= e(week_number($data)) ?></b></div>
                </div>
                <?php else: ?>
                <div class="panel">
                    <h3>Ten dzień</h3>
                    <div class="stat"><span>Godziny</span><b><?= $dzien_min !== null ? e(format_duration($dzien_min)) : '—' ?></b></div>
                    <div class="stat"><span>Status</span><b><?= $wpis ? 'Uzupełniony' : 'Pusty' ?></b></div>
                </div>
                <?php endif; ?>

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
    <?php if ($ilosc_dni > 0): ?>
        <section class="danger-zone no-print">
            <div class="danger-text">
                <strong>Strefa usuwania</strong>
                <span>Trwale usuwa wszystkie wpisy z dziennika. Tej operacji nie można cofnąć.</span>
            </div>
            <form method="post" action="index.php"
                  onsubmit="return confirm('UWAGA! Trwale usuniesz WSZYSTKIE wpisy z dziennika. Kontynuować?');">
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn btn-danger">🗑 Usuń wszystko</button>
            </form>
        </section>
    <?php endif; ?>

</div>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: time() ?>"></script>
</body>
</html>
