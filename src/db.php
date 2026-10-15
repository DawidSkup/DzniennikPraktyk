<?php
/**
 * Połączenie z bazą SQLite (PDO) + automatyczne tworzenie schematu.
 *
 * Plik bazy leży POZA katalogiem serwera (docroot), żeby nie dało się go
 * pobrać bezpośrednio przez HTTP. Lokalizację ustala db_data_dir():
 *   1. zmienna środowiskowa DZIENNIK_DATA_DIR (pełna ścieżka katalogu),
 *   2. katalog danych Laragona: <laragon>/data/DziennikPraktyk,
 *   3. katalog danych użytkownika (Windows: %LOCALAPPDATA%, Linux: ~/.local/share).
 *
 * Starsza baza z data/dziennik.db jest przenoszona automatycznie.
 *
 * Tryb „wiele kont": dziennik każdego ucznia jest osobny (kolumna user_id
 * w tabelach `wpisy` i `zalaczniki`). Konta mają rolę (administrator/uczeń).
 */

declare(strict_types=1);

/**
 * Zwraca katalog, w którym trzymana jest baza — poza katalogiem serwera.
 */
function db_data_dir(): string
{
    $env = getenv('DZIENNIK_DATA_DIR');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), "/\\");
    }

    // Projekt: .../<docroot>/DziennikPraktyk, więc dwa poziomy wyżej jest
    // katalog nadrzędny wobec docroot (np. C:\laragon).
    $project = dirname(__DIR__);
    $webroot = dirname($project);
    $parent  = dirname($webroot);

    // Laragon: C:\laragon\www\DziennikPraktyk -> C:\laragon\data\DziennikPraktyk
    if (is_dir($parent . DIRECTORY_SEPARATOR . 'data')) {
        return $parent . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'DziennikPraktyk';
    }

    // Fallback: katalog danych użytkownika (poza jakimkolwiek docroot).
    $base = '';
    if (DIRECTORY_SEPARATOR === '\\') {
        $localAppData = getenv('LOCALAPPDATA');
        if (is_string($localAppData) && $localAppData !== '') {
            $base = $localAppData;
        } else {
            $profile = getenv('USERPROFILE');
            if (is_string($profile) && $profile !== '') {
                $base = $profile . '\\AppData\\Local';
            }
        }
    } else {
        $xdg = getenv('XDG_DATA_HOME');
        if (is_string($xdg) && $xdg !== '') {
            $base = $xdg;
        } else {
            $home = getenv('HOME');
            if (is_string($home) && $home !== '') {
                $base = $home . '/.local/share';
            }
        }
    }

    if ($base === '') {
        $base = sys_get_temp_dir();
    }

    return rtrim($base, "/\\") . DIRECTORY_SEPARATOR . 'DziennikPraktyk';
}

/**
 * Pełna ścieżka do pliku bazy. W razie potrzeby przenosi bazę ze starej
 * lokalizacji (data/dziennik.db) i tworzy katalog docelowy.
 */
function db_path(): string
{
    static $path = null;
    if (is_string($path)) {
        return $path;
    }

    $dir = db_data_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Nie mogę utworzyć katalogu danych: ' . $dir);
    }

    $path = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . 'dziennik.db';

    // Jednorazowa migracja ze starej lokalizacji (data/dziennik.db).
    $legacy = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'dziennik.db';
    if (!is_file($path) && is_file($legacy)) {
        if (!@rename($legacy, $path)) {
            @copy($legacy, $path);
        }
    }

    return $path;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $file = db_path();

    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Strojenie SQLite pod szybkość i współbieżność (odczyt w trakcie zapisu):
    //  - WAL: zapisy nie blokują odczytów, mniej fsync,
    //  - synchronous=NORMAL: bezpieczne przy WAL, wyraźnie szybsze zapisy,
    //  - busy_timeout: krótko czekamy, gdy baza jest chwilowo zajęta,
    //  - temp_store/cache_size: mniejsze zużycie dysku i szybsze zapytania.
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA temp_store = MEMORY');
    $pdo->exec('PRAGMA cache_size = -20000');

    // Konta: użytkownicy z rolami (administrator / uczeń). Hasła hashowane.
    // Kolumny reset_* obsługują reset hasła kodem wysyłanym e-mailem.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS uzytkownicy (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            login            TEXT NOT NULL UNIQUE,
            haslo_hash       TEXT NOT NULL,
            email            TEXT NOT NULL DEFAULT "",
            rola             TEXT NOT NULL DEFAULT "uczen",
            imie_nazwisko    TEXT NOT NULL DEFAULT "",
            klasa            TEXT NOT NULL DEFAULT "",
            wymagane_godziny INTEGER NOT NULL DEFAULT 0,
            reset_hash       TEXT NOT NULL DEFAULT "",
            reset_wygasa     INTEGER NOT NULL DEFAULT 0,
            reset_proby      INTEGER NOT NULL DEFAULT 0,
            reset_wyslano    INTEGER NOT NULL DEFAULT 0,
            created_at       TEXT NOT NULL DEFAULT ""
        )'
    );

    // Czy to starsza (jednokontowa) baza? Brak kolumny `rola` to sygnał.
    $baza_jednokontowa = !db_column_exists($pdo, 'uzytkownicy', 'rola');

    // Migracja starszych baz: dokładamy brakujące kolumny (SQLite: ADD COLUMN).
    db_ensure_column($pdo, 'uzytkownicy', 'email', 'TEXT NOT NULL DEFAULT ""');
    db_ensure_column($pdo, 'uzytkownicy', 'rola', 'TEXT NOT NULL DEFAULT "uczen"');
    db_ensure_column($pdo, 'uzytkownicy', 'imie_nazwisko', 'TEXT NOT NULL DEFAULT ""');
    db_ensure_column($pdo, 'uzytkownicy', 'klasa', 'TEXT NOT NULL DEFAULT ""');
    db_ensure_column($pdo, 'uzytkownicy', 'wymagane_godziny', 'INTEGER NOT NULL DEFAULT 0');
    db_ensure_column($pdo, 'uzytkownicy', 'reset_hash', 'TEXT NOT NULL DEFAULT ""');
    db_ensure_column($pdo, 'uzytkownicy', 'reset_wygasa', 'INTEGER NOT NULL DEFAULT 0');
    db_ensure_column($pdo, 'uzytkownicy', 'reset_proby', 'INTEGER NOT NULL DEFAULT 0');
    db_ensure_column($pdo, 'uzytkownicy', 'reset_wyslano', 'INTEGER NOT NULL DEFAULT 0');

    // Wpisy dziennika — przypisane do konta; data unikalna w obrębie konta.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS wpisy (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL DEFAULT 0,
            data       TEXT NOT NULL,
            godzina_od TEXT NOT NULL DEFAULT "",
            godzina_do TEXT NOT NULL DEFAULT "",
            opis       TEXT NOT NULL DEFAULT "",
            UNIQUE (user_id, data)
        )'
    );

    // Migracja starej tabeli `wpisy` (jedno konto, data unikalna globalnie).
    if (!db_column_exists($pdo, 'wpisy', 'user_id')) {
        $domyslny = db_default_user_id($pdo);
        $pdo->exec('ALTER TABLE wpisy RENAME TO wpisy_stare');
        $pdo->exec(
            'CREATE TABLE wpisy (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL DEFAULT 0,
                data       TEXT NOT NULL,
                godzina_od TEXT NOT NULL DEFAULT "",
                godzina_do TEXT NOT NULL DEFAULT "",
                opis       TEXT NOT NULL DEFAULT "",
                UNIQUE (user_id, data)
            )'
        );
        $pdo->exec(
            'INSERT INTO wpisy (id, user_id, data, godzina_od, godzina_do, opis)
             SELECT id, ' . $domyslny . ', data, godzina_od, godzina_do, opis FROM wpisy_stare'
        );
        $pdo->exec('DROP TABLE wpisy_stare');
    }

    // Załączniki do dni (zdjęcia / pliki). Same pliki leżą na dysku w katalogu
    // danych (poza docroot) — tutaj trzymamy tylko metadane, także per konto.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zalaczniki (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL DEFAULT 0,
            data    TEXT NOT NULL,
            nazwa   TEXT NOT NULL DEFAULT "",
            plik    TEXT NOT NULL,
            typ     TEXT NOT NULL DEFAULT "",
            rozmiar INTEGER NOT NULL DEFAULT 0,
            dodano  TEXT NOT NULL DEFAULT ""
        )'
    );
    db_ensure_column($pdo, 'zalaczniki', 'user_id', 'INTEGER NOT NULL DEFAULT 0');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_zalaczniki_data ON zalaczniki (data)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_zalaczniki_user ON zalaczniki (user_id, data)');

    // Ustawienia aplikacji (klucz => wartość) — na przyszłość, ustawienia globalne.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ustawienia (
            klucz   TEXT PRIMARY KEY,
            wartosc TEXT NOT NULL DEFAULT ""
        )'
    );

    // Migracja ze starej, jednokontowej bazy: pierwsze konto zostaje
    // administratorem, a wszystkie istniejące wpisy/pliki należą do niego.
    if ($baza_jednokontowa) {
        $admin_id = db_default_user_id($pdo);
        if ($admin_id > 0) {
            $pdo->exec('UPDATE uzytkownicy SET rola = "admin" WHERE id = ' . $admin_id);
            $pdo->exec('UPDATE wpisy SET user_id = ' . $admin_id . ' WHERE user_id = 0');
            $pdo->exec('UPDATE zalaczniki SET user_id = ' . $admin_id . ' WHERE user_id = 0');

            // Przenieś globalne „wymagane godziny" na konto administratora.
            $stare = $pdo->query('SELECT wartosc FROM ustawienia WHERE klucz = "wymagane_godziny"')->fetchColumn();
            if ($stare !== false && (int) $stare > 0) {
                $pdo->prepare('UPDATE uzytkownicy SET wymagane_godziny = :g WHERE id = :id')
                    ->execute([':g' => (int) $stare, ':id' => $admin_id]);
            }
        }
    }

    return $pdo;
}

/** Czy tabela ma daną kolumnę (prosta migracja SQLite). */
function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll() as $kol) {
        if (($kol['name'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

/** Identyfikator pierwszego (najstarszego) konta lub 0, gdy brak kont. */
function db_default_user_id(PDO $pdo): int
{
    $id = $pdo->query('SELECT id FROM uzytkownicy ORDER BY id ASC LIMIT 1')->fetchColumn();

    return $id !== false ? (int) $id : 0;
}

/**
 * Dodaje kolumnę do tabeli, jeśli jeszcze nie istnieje (prosta migracja SQLite).
 * Nazwy tabeli/kolumny pochodzą z kodu, nie od użytkownika.
 */
function db_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (db_column_exists($pdo, $table, $column)) {
        return;
    }

    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}
