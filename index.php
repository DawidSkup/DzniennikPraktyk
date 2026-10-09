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
                <h2>Wykonane czynności</span></h2>
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
        <h2 class="form-title"><?= $wpis ? '✏️ Edytuj wpis' : '➕ Dodaj wpis' ?></h2>

        <form method="post" action="index.php">
            <input type="hidden" name="action" value="save">

            <div class="form-grid">
                <div class="field field-wide">
                    <label for="f-data">Data</label>
                    <input id="f-data" type="date" name="data" value="<?= e($data) ?>" required>
                </div>
                <div class="field">
                    <label for="f-od">Od</label>
                    <input id="f-od" type="time" name="godzina_od" value="<?= e($wpis['godzina_od'] ?? '08:00') ?>">
                </div>
                <div class="field">
                    <label for="f-do">Do</label>
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
                    <button type="submit" class="btn btn-danger" form="delete-form">Usuń</button>
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

    <footer class="footer no-print">
        Dziennik Praktyk · jeden dzień na stronę · przełączaj strzałkami ← →
    </footer>

</div>
<script src="assets/app.js"></script>
</body>
</html>
