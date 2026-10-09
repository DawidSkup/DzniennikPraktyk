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

$wpis  = get_wpis($data);
$prev  = prev_date($data);
$next  = next_date($data);
$dates = all_dates();

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
    <title>Dziennik Praktyk — <?= e($data) ?></title>
    <link rel="stylesheet" href="assets/style.css">
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
            <a class="btn btn-today" href="index.php?data=<?= e(date('Y-m-d')) ?>">Dzisiaj</a>
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
                                <a href="index.php?data=<?= e($d) ?>" class="<?= $d === $data ? 'active' : '' ?>">
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

    <!-- Nawigacja między dniami -->
    <nav class="daynav no-print">
        <a class="daynav-arrow" href="index.php?data=<?= e($prev ?? $data) ?>"
           <?= $prev === null ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '' ?>
           title="Poprzedni dzień">←</a>

        <form class="jump" method="get" action="index.php">
            <select name="data" onchange="this.form.submit()" aria-label="Wybierz dzień">
                <?php if (!$dates): ?>
                    <option value="<?= e($data) ?>"><?= e(format_date_full($data)) ?></option>
                <?php endif; ?>
                <?php foreach ($dates as $d): ?>
                    <option value="<?= e($d) ?>" <?= $d === $data ? 'selected' : '' ?>>
                        <?= e(format_date_full($d)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <a class="daynav-arrow" href="index.php?data=<?= e($next ?? $data) ?>"
           <?= $next === null ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '' ?>
           title="Następny dzień">→</a>
    </nav>

    <?php if ($zapisano): ?>
        <p class="alert no-print">✔ Wpis został zapisany.</p>
    <?php endif; ?>

    <?php if ($usunieto): ?>
        <p class="alert alert-danger no-print">🧹 Usunięto wszystkie wpisy z dziennika.</p>
    <?php endif; ?>

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

                <div class="panel">
                    <h3>Ten dzień</h3>
                    <div class="stat"><span>Godziny</span><b><?= $dzien_min !== null ? e(format_duration($dzien_min)) : '—' ?></b></div>
                    <div class="stat"><span>Status</span><b><?= $wpis ? 'Uzupełniony' : 'Pusty' ?></b></div>
                </div>

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
                        <li><span class="kbd">←</span> <span class="kbd">→</span> zmiana dnia</li>
                        <li><span class="kbd">Ctrl</span> + <span class="kbd">P</span> wydruk dnia</li>
                    </ul>
                </div>
            </div>
        </aside>

    </div><!-- /layout -->

    <footer class="footer no-print">
        Dziennik Praktyk · jeden dzień na stronę · przełączaj strzałkami ← →
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
<script src="assets/app.js"></script>
</body>
</html>
