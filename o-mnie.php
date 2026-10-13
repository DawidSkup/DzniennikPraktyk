<?php
/**
 * Dziennik Praktyk — strona „O mnie".
 *
 * Dostępna tylko po zalogowaniu. Dane autora uzupełnia się w tablicy $autor
 * poniżej — to jedyne miejsce, które trzeba edytować.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';

auth_require_login();

/* ------------------------------------------------------------------ *
 *  DANE AUTORA — edytuj tutaj
 * ------------------------------------------------------------------ */
$autor = [
    'imie'    => 'Imię Nazwisko',
    'opis'    => "Kilka zdań o sobie — czym się interesujesz, co robisz na praktykach,\nczego się uczysz. Ten tekst możesz dowolnie zmienić w pliku o-mnie.php.",
    'szkola'  => 'Nazwa szkoły',
    'klasa'   => 'Klasa / zawód',
    'opiekun' => 'Imię i nazwisko opiekuna praktyk',
    'rok'     => 'Rok szkolny 2026/2027',
    'email'   => '',
];

// Inicjały do awatara (np. „Jan Kowalski" -> „JK").
$inicjaly = '';
foreach (preg_split('/\s+/', trim($autor['imie'])) ?: [] as $czesc) {
    if ($czesc !== '') {
        $inicjaly .= mb_strtoupper(mb_substr($czesc, 0, 1));
    }
    if (mb_strlen($inicjaly) >= 2) {
        break;
    }
}
if ($inicjaly === '') {
    $inicjaly = '?';
}

// Pozycje do wyświetlenia — pokazujemy tylko te wypełnione.
$pozycje = [
    'Szkoła'         => $autor['szkola'],
    'Klasa / zawód'  => $autor['klasa'],
    'Opiekun praktyk' => $autor['opiekun'],
    'Rok szkolny'    => $autor['rok'],
    'Kontakt'        => $autor['email'],
];

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
    <title>Dziennik Praktyk — O mnie</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
    <script src="assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?: time() ?>"></script>
</head>
<body style="<?= e($theme_vars) ?>">
<div class="app">

    <header class="topbar no-print">
        <div class="brand">
            <span class="brand-icon">📘</span>
            <div>
                <h1 class="brand-title">O mnie</h1>
                <p class="brand-sub">Dziennik Praktyk</p>
            </div>
        </div>
        <div class="topbar-actions">
            <div class="theme-switch no-print" role="group" aria-label="Motyw: jasny, ciemny lub automatyczny">
                <button type="button" class="theme-btn" data-theme-mode="light" aria-pressed="false" title="Tryb jasny (dzień)">☀️</button>
                <button type="button" class="theme-btn" data-theme-mode="dark" aria-pressed="false" title="Tryb ciemny (noc)">🌙</button>
                <button type="button" class="theme-btn" data-theme-mode="auto" aria-pressed="true" title="Automatyczny (ustawienie systemu)">🌓</button>
            </div>
            <a class="btn btn-today" href="index.php">← Wróć do dziennika</a>
        </div>
    </header>

    <main class="main about-main">
        <article class="sheet about-sheet">
            <header class="sheet-head">
                <div>
                    <p class="sheet-kicker">Dziennik Praktyk</p>
                    <p class="sheet-date">O mnie</p>
                </div>
            </header>

            <div class="about-head">
                <div class="about-avatar" aria-hidden="true"><?= e($inicjaly) ?></div>
                <div>
                    <h2 class="about-name"><?= e($autor['imie']) ?></h2>
                    <?php if (trim((string) $autor['klasa']) !== ''): ?>
                        <p class="about-sub"><?= e($autor['klasa']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (trim((string) $autor['opis']) !== ''): ?>
                <p class="about-desc"><?= nl2br(e($autor['opis'])) ?></p>
            <?php endif; ?>

            <dl class="about-facts">
                <?php foreach ($pozycje as $etykieta => $wartosc): ?>
                    <?php if (trim((string) $wartosc) !== ''): ?>
                        <div class="about-fact">
                            <dt><?= e($etykieta) ?></dt>
                            <dd><?= e($wartosc) ?></dd>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </dl>

            <p class="about-note">
                Ten tekst pochodzi z pliku <code>o-mnie.php</code> — możesz go dowolnie edytować.
            </p>
        </article>

        <nav class="pagefoot" aria-label="Informacje">
            <a href="index.php">← Dziennik</a>
            <a href="o-mnie.php" aria-current="page">O mnie</a>
        </nav>
    </main>

</div>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: time() ?>"></script>
</body>
</html>
