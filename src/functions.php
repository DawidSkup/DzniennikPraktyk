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

/** Waliduje godzinę w formacie HH:MM (lub pusty string). */
function is_valid_time(string $time): bool
{
    if ($time === '') {
        return true;
    }
    $t = DateTime::createFromFormat('H:i', $time);

    return $t !== false && $t->format('H:i') === $time;
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
