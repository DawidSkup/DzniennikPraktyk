<?php
/**
 * Funkcje pomocnicze i operacje CRUD na wpisach dziennika.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Pobiera wpis dla podanej daty (YYYY-MM-DD) lub null. */
function get_wpis(string $data): ?array
{
    $stmt = db()->prepare('SELECT * FROM wpisy WHERE data = :data');
    $stmt->execute([':data' => $data]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Dodaje lub aktualizuje wpis dla podanej daty. */
function save_wpis(string $data, string $od, string $do, string $opis): void
{
    $stmt = db()->prepare(
        'INSERT INTO wpisy (data, godzina_od, godzina_do, opis)
         VALUES (:data, :od, :do, :opis)
         ON CONFLICT(data) DO UPDATE SET
            godzina_od = excluded.godzina_od,
            godzina_do = excluded.godzina_do,
            opis       = excluded.opis'
    );
    $stmt->execute([
        ':data' => $data,
        ':od'   => $od,
        ':do'   => $do,
        ':opis' => $opis,
    ]);
}

/** Usuwa wpis dla podanej daty. */
function delete_wpis(string $data): void
{
    $stmt = db()->prepare('DELETE FROM wpisy WHERE data = :data');
    $stmt->execute([':data' => $data]);
}

/** Zwraca listę wszystkich dat wpisów, rosnąco. */
function all_dates(): array
{
    return db()->query('SELECT data FROM wpisy ORDER BY data ASC')
        ->fetchAll(PDO::FETCH_COLUMN);
}

/** Data poprzedniego wpisu względem podanej daty (lub null). */
function prev_date(string $data): ?string
{
    $stmt = db()->prepare('SELECT data FROM wpisy WHERE data < :data ORDER BY data DESC LIMIT 1');
    $stmt->execute([':data' => $data]);
    $row = $stmt->fetchColumn();

    return $row !== false ? (string) $row : null;
}

/** Data następnego wpisu względem podanej daty (lub null). */
function next_date(string $data): ?string
{
    $stmt = db()->prepare('SELECT data FROM wpisy WHERE data > :data ORDER BY data ASC LIMIT 1');
    $stmt->execute([':data' => $data]);
    $row = $stmt->fetchColumn();

    return $row !== false ? (string) $row : null;
}

/** Data ostatniego wpisu (do widoku startowego) lub null. */
function last_date(): ?string
{
    $row = db()->query('SELECT MAX(data) FROM wpisy')->fetchColumn();

    return $row !== null ? (string) $row : null;
}

/** Waliduje datę w formacie YYYY-MM-DD. */
function is_valid_date(string $data): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $data);

    return $d !== false && $d->format('Y-m-d') === $data;
}

/** Zwraca wszystkie wpisy, posortowane rosnąco po dacie. */
function all_wpisy(): array
{
    return db()->query('SELECT * FROM wpisy ORDER BY data ASC')->fetchAll();
}

/** Liczba minut między godzinami (od–do) lub null, gdy nieprawidłowe. */
function minutes_between(string $od, string $do): ?int
{
    if ($od === '' || $do === '' || !is_valid_time($od) || !is_valid_time($do)) {
        return null;
    }

    [$h1, $m1] = array_map('intval', explode(':', $od));
    [$h2, $m2] = array_map('intval', explode(':', $do));

    $diff = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);

    return $diff > 0 ? $diff : null;
}

/** Formatuje liczbę minut jako „X h Y min". */
function format_duration(int $minutes): string
{
    return intdiv($minutes, 60) . ' h ' . ($minutes % 60) . ' min';
}

/** Krótki zapis daty, np. „pt. 09.10.2026". */
function format_date_short(string $data): string
{
    $short = [
        'Monday'    => 'pon.',
        'Tuesday'   => 'wt.',
        'Wednesday' => 'śr.',
        'Thursday'  => 'czw.',
        'Friday'    => 'pt.',
        'Saturday'  => 'sob.',
        'Sunday'    => 'nd.',
    ];

    $dt = new DateTime($data);

    return $short[$dt->format('l')] . ' ' . $dt->format('d.m.Y');
}

/** Waliduje godzinę w formacie HH:MM (lub pusty string). */
function is_valid_time(string $time): bool
{
    if ($time === '') {
        return true;
    }
    $t = DateTime::createFromFormat('H:i', $time);

    return $t !== false && $t->format('H:i') === $time;
}

/**
 * Zwraca motyw (kolor + czcionka) przypisany do danego dnia.
 * Motyw wybierany deterministycznie z numeru dnia w roku — każdy kolejny
 * dzień dostaje inny motyw, cyklicznie.
 *
 * @return array{primary:string, primary_dark:string, accent:string, accent_dark:string, tint:string, tint2:string, font:string}
 */
function day_theme(string $data): array
{
    $themes = [
        [   // 1 — indygo / bezszeryfowa
            'primary'      => '#4f46e5',
            'primary_dark' => '#4338ca',
            'accent'       => '#0891b2',
            'accent_dark'  => '#0e7490',
            'tint'         => '#eef2ff',
            'tint2'        => '#ecfeff',
            'font'         => '"Segoe UI", system-ui, Roboto, Arial, sans-serif',
        ],
        [   // 2 — szmaragdowa / szeryfowa
            'primary'      => '#059669',
            'primary_dark' => '#047857',
            'accent'       => '#0d9488',
            'accent_dark'  => '#0f766e',
            'tint'         => '#ecfdf5',
            'tint2'        => '#f0fdfa',
            'font'         => 'Georgia, "Times New Roman", serif',
        ],
        [   // 3 — bursztynowa / Trebuchet
            'primary'      => '#d97706',
            'primary_dark' => '#b45309',
            'accent'       => '#ea580c',
            'accent_dark'  => '#c2410c',
            'tint'         => '#fffbeb',
            'tint2'        => '#fff7ed',
            'font'         => '"Trebuchet MS", "Segoe UI", sans-serif',
        ],
        [   // 4 — różana / Verdana
            'primary'      => '#e11d48',
            'primary_dark' => '#be123c',
            'accent'       => '#c026d3',
            'accent_dark'  => '#a21caf',
            'tint'         => '#fff1f2',
            'tint2'        => '#fdf4ff',
            'font'         => 'Verdana, Geneva, sans-serif',
        ],
        [   // 5 — błękitna / Palatino (szeryfowa alt.)
            'primary'      => '#0284c7',
            'primary_dark' => '#0369a1',
            'accent'       => '#2563eb',
            'accent_dark'  => '#1d4ed8',
            'tint'         => '#f0f9ff',
            'tint2'        => '#eff6ff',
            'font'         => '"Palatino Linotype", "Book Antiqua", Georgia, serif',
        ],
        [   // 6 — fioletowa / maszynowa (mono)
            'primary'      => '#7c3aed',
            'primary_dark' => '#6d28d9',
            'accent'       => '#db2777',
            'accent_dark'  => '#be185d',
            'tint'         => '#f5f3ff',
            'tint2'        => '#fdf2f8',
            'font'         => '"Courier New", "Consolas", monospace',
        ],
    ];

    $nr    = (int) (new DateTime($data))->format('z'); // 0..365
    $index = $nr % count($themes);

    return $themes[$index];
}

/** Bezpieczne wypisanie tekstu na stronie. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Formatuje datę ISO na czytelną wersję (np. poniedziałek, 09.10.2026). */
function format_date_full(string $data): string
{
    $dni = [
        'Monday'    => 'poniedziałek',
        'Tuesday'   => 'wtorek',
        'Wednesday' => 'środa',
        'Thursday'  => 'czwartek',
        'Friday'    => 'piątek',
        'Saturday'  => 'sobota',
        'Sunday'    => 'niedziela',
    ];

    $dt = new DateTime($data);

    return $dni[$dt->format('l')] . ', ' . $dt->format('d.m.Y');
}
