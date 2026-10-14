<?php
/**
 * Dziennik Praktyk — strona główna.
 *
 * Pokazuje podsumowanie godzin praktyk (łącznie, średnio, ten tydzień,
 * ten miesiąc) i prowadzi przyciskiem do kalendarza (`kalendarz.php`).
 */

declare(strict_types=1);

require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/auth.php';

// Wymagane zalogowanie (brak konta → kreator, brak sesji → logowanie).
auth_require_login();

/* ------------------------------------------------------------------ *
 *  Podsumowanie — liczone z jednego zapytania do bazy
 * ------------------------------------------------------------------ */

$wszystkie    = all_wpisy();          // posortowane rosnąco po dacie
$ilosc_dni    = count($wszystkie);
$mapa_min     = [];                   // data => minuty (null, gdy brak godzin)
$total_min    = 0;
$dni_z_czasem = 0;
$pierwsza     = null;
$ostatnia     = null;

foreach ($wszystkie as $w) {
    $d   = (string) $w['data'];
    $min = minutes_between((string) $w['godzina_od'], (string) $w['godzina_do']);

    $mapa_min[$d] = $min;
    if ($pierwsza === null) {
        $pierwsza = $d;
    }
    $ostatnia = $d;

    if ($min !== null) {
        $total_min += $min;
        $dni_z_czasem++;
    }
}
$srednia_min = $dni_z_czasem > 0 ? intdiv($total_min, $dni_z_czasem) : 0;

$dzis = date('Y-m-d');

// Bieżący tydzień (poniedziałek–niedziela).
$tydz_start = week_start($dzis);
$tydz_min   = 0;
$tydz_dni   = 0;
for ($i = 0; $i < 7; $i++) {
    $d = date_shift($tydz_start, $i);
    if (array_key_exists($d, $mapa_min)) {
        $tydz_dni++;
        if ($mapa_min[$d] !== null) {
            $tydz_min += $mapa_min[$d];
        }
    }
}

// Bieżący miesiąc.
$mies_start = date('Y-m-01');
$mies_end   = date('Y-m-t');
$mies_min   = 0;
$mies_dni   = 0;
for ($d = $mies_start; $d <= $mies_end; $d = date_shift($d, 1)) {
    if (array_key_exists($d, $mapa_min)) {
        $mies_dni++;
        if ($mapa_min[$d] !== null) {
            $mies_min += $mapa_min[$d];
        }
    }
}

$zal_total = array_sum(zal_counts());
$witaj     = isset($_GET['witaj']);

$theme = day_theme($dzis);
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
    <title>Dziennik Praktyk — strona główna</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
    <script src="assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?: time() ?>"></script>
</head>
<body style="<?= e($theme_vars) ?>">
<div class="app">

    <header class="topbar no-print">
        <div class="brand">
            <span class="brand-icon">📘</span>
            <div>
                <h1 class="brand-title">Dziennik Praktyk</h1>
                <p class="brand-sub">Strona główna · <?= $ilosc_dni ?> <?= $ilosc_dni === 1 ? 'wpis' : 'wpisów' ?> w dzienniku</p>
            </div>
        </div>
        <div class="topbar-actions" role="toolbar" aria-label="Akcje">
            <div class="theme-switch no-print" role="group" aria-label="Motyw: jasny, ciemny lub automatyczny">
                <button type="button" class="theme-btn" data-theme-mode="light" aria-pressed="false" title="Tryb jasny (dzień)">☀️</button>
                <button type="button" class="theme-btn" data-theme-mode="dark" aria-pressed="false" title="Tryb ciemny (noc)">🌙</button>
                <button type="button" class="theme-btn" data-theme-mode="auto" aria-pressed="true" title="Automatyczny (ustawienie systemu)">🌓</button>
            </div>
            <a class="btn btn-today" href="kalendarz.php" title="Przejdź do kalendarza">📅 Kalendarz</a>
            <form method="post" action="logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-logout" title="Wyloguj się">🚪 Wyloguj</button>
            </form>
        </div>
    </header>

    <main class="main home-main">

        <?php if ($witaj): ?>
            <p class="alert no-print">👋 Konto utworzone. Miłej pracy z dziennikiem!</p>
        <?php endif; ?>

        <article class="sheet home-sheet">
            <header class="sheet-head">
                <div>
                    <p class="sheet-kicker">Dziennik Praktyk</p>
                    <p class="sheet-date">Podsumowanie godzin</p>
                </div>
                <?php if ($ilosc_dni > 0): ?>
                    <span class="status-pill is-done"><?= $ilosc_dni ?> <?= $ilosc_dni === 1 ? 'dzień' : 'dni' ?> z wpisem</span>
                <?php else: ?>
                    <span class="status-pill is-empty">Brak wpisów</span>
                <?php endif; ?>
            </header>

            <?php if ($ilosc_dni === 0): ?>
                <p class="empty">Nie masz jeszcze żadnych wpisów. Zacznij od dodania pierwszego dnia praktyk.</p>
                <div class="home-actions">
                    <a class="btn btn-today home-big" href="kalendarz.php?dodaj=1">➕ Dodaj pierwszy wpis</a>
                    <a class="btn" href="kalendarz.php">📅 Kalendarz</a>
                </div>
            <?php else: ?>
                <div class="home-hero">
                    <span class="home-hero-label">Łącznie przepracowane</span>
                    <span class="home-hero-value"><?= $total_min > 0 ? e(format_duration($total_min)) : '—' ?></span>
                    <?php if ($pierwsza !== null && $ostatnia !== null): ?>
                        <span class="home-hero-sub">
                            <?= e((new DateTime($pierwsza))->format('d.m.Y')) ?> – <?= e((new DateTime($ostatnia))->format('d.m.Y')) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="home-facts">
                    <div class="home-fact">
                        <span>Dni z wpisem</span>
                        <b><?= $ilosc_dni ?></b>
                    </div>
                    <div class="home-fact">
                        <span>Średnio / dzień</span>
                        <b><?= $dni_z_czasem > 0 ? e(format_duration($srednia_min)) : '—' ?></b>
                    </div>
                    <div class="home-fact">
                        <span>Ten tydzień</span>
                        <b><?= $tydz_min > 0 ? e(format_duration($tydz_min)) : '—' ?></b>
                        <small><?= $tydz_dni ?> <?= $tydz_dni === 1 ? 'dzień' : 'dni' ?> z wpisem</small>
                    </div>
                    <div class="home-fact">
                        <span>Ten miesiąc</span>
                        <b><?= $mies_min > 0 ? e(format_duration($mies_min)) : '—' ?></b>
                        <small><?= $mies_dni ?> <?= $mies_dni === 1 ? 'dzień' : 'dni' ?> z wpisem</small>
                    </div>
                    <div class="home-fact">
                        <span>Pierwszy wpis</span>
                        <b><?= e((new DateTime($pierwsza))->format('d.m.Y')) ?></b>
                    </div>
                    <div class="home-fact">
                        <span>Ostatni wpis</span>
                        <b><?= e((new DateTime($ostatnia))->format('d.m.Y')) ?></b>
                    </div>
                    <div class="home-fact">
                        <span>Załączniki</span>
                        <b><?= $zal_total ?></b>
                    </div>
                </div>

                <div class="home-actions">
                    <a class="btn btn-today home-big" href="kalendarz.php">📅 Przejdź do kalendarza</a>
                    <a class="btn btn-save" href="kalendarz.php?dodaj=1">➕ Dodaj wpis</a>
                </div>
            <?php endif; ?>
        </article>

        <nav class="pagefoot no-print" aria-label="Informacje">
            <a href="index.php" aria-current="page">🏠 Start</a>
            <a href="kalendarz.php">📅 Kalendarz</a>
            <a href="o-mnie.php">ℹ️ O mnie</a>
        </nav>

    </main>

</div>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: time() ?>"></script>
</body>
</html>
