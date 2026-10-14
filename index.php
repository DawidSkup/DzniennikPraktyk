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

// Kontekst: własny dziennik albo podgląd dziennika ucznia (administrator).
$ctx        = auth_view_context();
$view_user  = $ctx['user'] ?? null;
$readonly   = (bool) $ctx['readonly'];
$view_id    = (int) ($view_user['id'] ?? 0);
$view_name  = $view_user !== null ? user_label($view_user) : '';

/* ------------------------------------------------------------------ *
 *  Podsumowanie — liczone z jednego zapytania do bazy
 * ------------------------------------------------------------------ */

$wszystkie    = all_wpisy($view_id);   // posortowane rosnąco po dacie
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

$zal_total = array_sum(zal_counts($view_id));
$witaj     = isset($_GET['witaj']);

// Pasek postępu: ile godzin już wypracowano z wymaganej liczby godzin.
$wymagane_godz = wymagane_godziny($view_id);
$wymagane_min  = $wymagane_godz * 60;
$procent       = $wymagane_min > 0 ? (int) round($total_min / $wymagane_min * 100) : 0;
$procent_bar   = max(0, min(100, $procent));
$zostalo_min   = max(0, $wymagane_min - $total_min);

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
                <p class="brand-sub">
                    <?php if ($readonly): ?>
                        Podgląd dziennika · <?= e($view_name) ?> · <?= $ilosc_dni ?> <?= $ilosc_dni === 1 ? 'wpis' : 'wpisów' ?>
                    <?php else: ?>
                        Strona główna · <?= $ilosc_dni ?> <?= $ilosc_dni === 1 ? 'wpis' : 'wpisów' ?> w dzienniku
                    <?php endif; ?>
                </p>
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
            <?php if (auth_is_admin()): ?>
                <a class="btn btn-admin" href="admin.php" title="Panel administratora">👑 Panel admina</a>
            <?php endif; ?>
            <a class="btn btn-today" href="kalendarz.php<?= $readonly ? '?uczen=' . $view_id : '' ?>" title="Przejdź do kalendarza">📅 Kalendarz</a>
            <?php if ($readonly): ?>
                <a class="btn" href="index.php" title="Wróć do swojego dziennika">↩ Mój dziennik</a>
            <?php else: ?>
                <form method="post" action="logout.php" class="logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-logout" title="Wyloguj się">🚪 Wyloguj</button>
                </form>
                <a class="btn btn-icon" href="ustawienia.php" title="Ustawienia dziennika" aria-label="Ustawienia">⚙️</a>
            <?php endif; ?>
        </div>
        <button type="button" class="actions-toggle" id="actions-toggle"
                aria-controls="actions-bar" aria-expanded="true"
                title="Pokaż lub ukryj pasek akcji" aria-label="Pokaż lub ukryj pasek akcji">⋮</button>
    </div>

    <main class="main home-main">

        <?php if ($readonly): ?>
            <p class="alert alert-info no-print">👀 Podglądasz dziennik ucznia: <b><?= e($view_name) ?></b>. Tryb tylko do odczytu.</p>
        <?php endif; ?>

        <?php if ($witaj): ?>
            <p class="alert no-print">👋 Konto utworzone. Miłej pracy z dziennikiem!</p>
        <?php endif; ?>

        <article class="sheet home-sheet">
            <header class="sheet-head">
                <div>
                    <p class="sheet-kicker">Dziennik Praktyk<?= $readonly ? ' · ' . e($view_name) : '' ?></p>
                    <p class="sheet-date">Podsumowanie godzin</p>
                </div>
                <?php if ($ilosc_dni > 0): ?>
                    <span class="status-pill is-done"><?= $ilosc_dni ?> <?= $ilosc_dni === 1 ? 'dzień' : 'dni' ?> z wpisem</span>
                <?php else: ?>
                    <span class="status-pill is-empty">Brak wpisów</span>
                <?php endif; ?>
            </header>

            <?php if ($wymagane_godz > 0): ?>
                <section class="progress" aria-label="Postęp praktyk">
                    <div class="progress-head">
                        <span class="progress-title">🎯 Postęp praktyk</span>
                        <span class="progress-pct<?= $procent >= 100 ? ' is-done' : '' ?>"><?= $procent ?>%</span>
                    </div>
                    <div class="progress-track" role="progressbar"
                         aria-valuemin="0" aria-valuemax="<?= $wymagane_godz ?>"
                         aria-valuenow="<?= min($wymagane_godz, (int) round($total_min / 60)) ?>"
                         aria-label="Wypracowane godziny z <?= $wymagane_godz ?>">
                        <div class="progress-fill<?= $procent >= 100 ? ' is-done' : '' ?>" style="width: <?= $procent_bar ?>%"></div>
                    </div>
                    <div class="progress-foot">
                        <span><b><?= e(format_duration_compact($total_min)) ?></b> z <?= $wymagane_godz ?> h</span>
                        <?php if ($zostalo_min > 0): ?>
                            <span>Zostało <b><?= e(format_duration_compact($zostalo_min)) ?></b></span>
                        <?php else: ?>
                            <span class="progress-done">✔ Wypracowano wymagane godziny!</span>
                        <?php endif; ?>
                    </div>
                </section>
            <?php else: ?>
                <?php if ($readonly): ?>
                    <p class="progress-empty">🎯 Uczeń nie ustawił jeszcze wymaganej liczby godzin praktyk.</p>
                <?php else: ?>
                    <p class="progress-empty">
                        🎯 Ustaw liczbę godzin wymaganych do zaliczenia praktyk w
                        <a href="ustawienia.php">⚙️ Ustawieniach</a>, aby zobaczyć pasek postępu.
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($ilosc_dni === 0): ?>
                <p class="empty"><?= $readonly
                    ? 'Ten uczeń nie ma jeszcze żadnych wpisów.'
                    : 'Nie masz jeszcze żadnych wpisów. Zacznij od dodania pierwszego dnia praktyk.' ?></p>
                <div class="home-actions">
                    <?php if (!$readonly): ?>
                        <a class="btn btn-today home-big" href="kalendarz.php?dodaj=1">➕ Dodaj pierwszy wpis</a>
                    <?php endif; ?>
                    <a class="btn" href="kalendarz.php<?= $readonly ? '?uczen=' . $view_id : '' ?>">📅 Kalendarz</a>
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
                    <a class="btn btn-today home-big" href="kalendarz.php<?= $readonly ? '?uczen=' . $view_id : '' ?>">📅 Przejdź do kalendarza</a>
                    <?php if (!$readonly): ?>
                        <a class="btn btn-save" href="kalendarz.php?dodaj=1">➕ Dodaj wpis</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>

        <nav class="pagefoot no-print" aria-label="Informacje">
            <a href="index.php<?= $readonly ? '?uczen=' . $view_id : '' ?>" aria-current="page">🏠 Start</a>
            <a href="kalendarz.php<?= $readonly ? '?uczen=' . $view_id : '' ?>">📅 Kalendarz</a>
            <?php if (!$readonly): ?>
                <a href="ustawienia.php">⚙️ Ustawienia</a>
            <?php endif; ?>
            <a href="o-mnie.php">ℹ️ O mnie</a>
        </nav>

    </main>

</div>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: time() ?>"></script>
</body>
</html>
