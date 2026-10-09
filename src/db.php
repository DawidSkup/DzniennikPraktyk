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
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS wpisy (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            data       TEXT NOT NULL UNIQUE,
            godzina_od TEXT NOT NULL DEFAULT "",
            godzina_do TEXT NOT NULL DEFAULT "",
            opis       TEXT NOT NULL DEFAULT ""
        )'
    );

    return $pdo;
}
