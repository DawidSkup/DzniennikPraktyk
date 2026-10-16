<?php
/**
 * Dziennik Praktyk — kopia zapasowa.
 *
 * GET  backup.php                  → pobiera kopię bazy jako plik .db
 * POST backup.php (action=restore) → przywraca dziennik z wgranej kopii
 *
 * Kopię tworzymy przez `VACUUM INTO`, żeby plik był spójny nawet przy
 * włączonym trybie WAL. Gdyby SQLite tego nie wspierał — zwykły `copy`.
 * Przed przywróceniem zapisujemy kopię obecnego stanu (na wypadek pomyłki).
 */

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';

// Kopia zapasowa (całej bazy) dostępna tylko dla administratora.
auth_require_admin();

/**
 * Tworzy tymczasowy plik ze spójną kopią bazy i zwraca jego ścieżkę.
 */
function backup_snapshot(): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'dpbak');
    if ($tmp === false) {
        throw new RuntimeException('Nie mogę utworzyć pliku tymczasowego.');
    }
    @unlink($tmp); // VACUUM INTO wymaga, aby plik docelowy nie istniał

    try {
        $pdo = db();
        $pdo->exec('VACUUM INTO ' . $pdo->quote($tmp));
        if (is_file($tmp) && (int) filesize($tmp) > 0) {
            return $tmp;
        }
    } catch (Throwable $e) {
        // Nowszy/nietypowy SQLite — schodzimy do zwykłego kopiowania.
    }

    if (!@copy(db_path(), $tmp)) {
        @unlink($tmp);
        throw new RuntimeException('Nie mogę skopiować pliku bazy.');
    }

    return $tmp;
}

/** Wysyła kopię zapasową jako plik do pobrania. */
function backup_download(): void
{
    $snapshot = backup_snapshot();
    $name     = 'dziennik-praktyk-kopia-' . date('Y-m-d_His') . '.db';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . (string) filesize($snapshot));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    readfile($snapshot);
    @unlink($snapshot);
    exit;
}

/**
 * Przywraca dziennik z wgranej kopii.
 *
 * @return string Kod błędu ('' gdy sukces) — czytelny opis w backup_error_message().
 */
function backup_restore(): string
{
    $file = $_FILES['kopia'] ?? null;
    if (!is_array($file)) {
        return 'brak-pliku';
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return 'za-duzy';
    }
    if ($error !== UPLOAD_ERR_OK) {
        return 'blad-uploadu';
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return 'blad-uploadu';
    }

    // Walidacja: to musi być baza SQLite z tabelą `wpisy` i właściwymi kolumnami.
    $check = null;
    try {
        $check = new PDO('sqlite:' . $tmp);
        $check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $table = $check->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'wpisy'")
            ->fetchAll(PDO::FETCH_COLUMN);
        if ($table === []) {
            return 'nie-baza';
        }

        $cols = $check->query('PRAGMA table_info(wpisy)')->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach (['data', 'godzina_od', 'godzina_do', 'opis'] as $need) {
            if (!in_array($need, $cols, true)) {
                return 'nie-baza';
            }
        }
    } catch (Throwable $e) {
        return 'nie-baza';
    } finally {
        $check = null; // zwolnij uchwyt do pliku tymczasowego przed podmianą
    }

    $target = db_path();
    $dir    = dirname($target);

    // Kopia bezpieczeństwa obecnego stanu — gdyby przywrócenie było pomyłką.
    if (is_file($target)) {
        @copy($target, $dir . DIRECTORY_SEPARATOR . 'dziennik-przed-przywroceniem-' . date('Y-m-d_His') . '.db');
    }

    if (!@copy($tmp, $target)) {
        return 'blad-zapisu';
    }

    // Po podmianie usuń osierocone pliki dziennika transakcji (WAL/SHM).
    @unlink($target . '-wal');
    @unlink($target . '-shm');

    return '';
}

/* ------------------------------------------------------------------ *
 *  Dyspozytor
 * ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $err = 'csrf';
    } elseif (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $err = 'za-duzy'; // przekroczony limit post_max_size
    } elseif (($_POST['action'] ?? '') !== 'restore') {
        $err = 'nieznana-akcja';
    } else {
        $err = backup_restore();
    }

    header('Location: ustawienia.php?' . ($err === '' ? 'przywrocono=1' : 'blad=' . urlencode($err)));
    exit;
}

try {
    backup_download();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nie udało się utworzyć kopii zapasowej: ' . $e->getMessage();
}
