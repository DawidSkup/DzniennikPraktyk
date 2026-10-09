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

$wpis = get_wpis($data);

$prev = prev_date($data);
$next = next_date($data);
$dates = all_dates();

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
<body>
<div class="app">

    <!-- Pasek nawigacji (nie drukuje się) -->
    <nav class="nav no-print">
        <a class="btn btn-nav" href="index.php?data=<?= e($prev ?? $data) ?>"
           <?= $prev === null ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '' ?>
           title="Poprzedni dzień">← Poprzedni</a>

        <form class="jump" method="get" action="index.php">
            <select name="data" onchange="this.form.submit()" aria-label="Wybierz dzień">
                <?php if (!$dates): ?>
                    <option value="<?= e($data) ?>"><?= e($data) ?></option>
                <?php endif; ?>
                <?php foreach ($dates as $d): ?>
                    <option value="<?= e($d) ?>" <?= $d === $data ? 'selected' : '' ?>>
                        <?= e(format_date_full($d)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <a class="btn btn-nav" href="index.php?data=<?= e($next ?? $data) ?>"
           <?= $next === null ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '' ?>
           title="Następny dzień">Następny →</a>

        <span class="spacer"></span>

        <a class="btn btn-today" href="index.php?data=<?= e(date('Y-m-d')) ?>">Dzisiaj</a>
        <button type="button" class="btn btn-print" onclick="window.print()">🖨 Drukuj</button>
    </nav>

    <?php if ($zapisano): ?>
        <p class="alert no-print">Wpis został zapisany. ✔</p>
    <?php endif; ?>

    <!-- Dokument dnia (widok do wydruku) -->
    <article class="sheet">
        <header class="sheet-head">
            <h1>Dziennik Praktyk</h1>
            <p class="sheet-date"><?= e(format_date_full($data)) ?></p>
        </header>

        <?php if ($wpis): ?>
            <table class="meta">
                <tr>
                    <th>Godziny pracy</th>
                    <td>
                        <span class="chip chip-from"><?= e($wpis['godzina_od']) ?: '—' ?></span>
                        <span class="chip-sep">→</span>
                        <span class="chip chip-to"><?= e($wpis['godzina_do']) ?: '—' ?></span>
                    </td>
                </tr>
            </table>

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
    <section class="form-box no-print">
        <h2><?= $wpis ? 'Edytuj wpis' : 'Dodaj wpis' ?></h2>

        <form method="post" action="index.php">
            <input type="hidden" name="action" value="save">

            <div class="row">
                <label>Data
                    <input type="date" name="data" value="<?= e($data) ?>" required>
                </label>
                <label>Od
                    <input type="time" name="godzina_od" value="<?= e($wpis['godzina_od'] ?? '08:00') ?>">
                </label>
                <label>Do
                    <input type="time" name="godzina_do" value="<?= e($wpis['godzina_do'] ?? '16:00') ?>">
                </label>
            </div>

            <label class="full">Co robiłem/am
                <textarea name="opis" rows="6" placeholder="Opis wykonanych czynności..."><?= e($wpis['opis'] ?? '') ?></textarea>
            </label>

            <div class="actions">
                <button type="submit" class="btn btn-save">💾 Zapisz</button>
            </div>
        </form>

        <?php if ($wpis): ?>
            <form method="post" action="index.php" class="delete-form"
                  onsubmit="return confirm('Usunąć wpis z dnia <?= e($data) ?>?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="data" value="<?= e($data) ?>">
                <button type="submit" class="btn btn-danger">Usuń wpis</button>
            </form>
        <?php endif; ?>
    </section>

</div>
<script src="assets/app.js"></script>
</body>
</html>
