<?php
/**
 * Funkcje pomocnicze i operacje CRUD na wpisach dziennika.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Pobiera wpis użytkownika dla podanej daty (YYYY-MM-DD) lub null. */
function get_wpis(int $user_id, string $data): ?array
{
    $stmt = db()->prepare('SELECT * FROM wpisy WHERE user_id = :u AND data = :data');
    $stmt->execute([':u' => $user_id, ':data' => $data]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Dodaje lub aktualizuje wpis użytkownika dla podanej daty. */
function save_wpis(int $user_id, string $data, string $od, string $do, string $opis): void
{
    $stmt = db()->prepare(
        'INSERT INTO wpisy (user_id, data, godzina_od, godzina_do, opis)
         VALUES (:u, :data, :od, :do, :opis)
         ON CONFLICT(user_id, data) DO UPDATE SET
            godzina_od = excluded.godzina_od,
            godzina_do = excluded.godzina_do,
            opis       = excluded.opis'
    );
    $stmt->execute([
        ':u'    => $user_id,
        ':data' => $data,
        ':od'   => $od,
        ':do'   => $do,
        ':opis' => $opis,
    ]);
}

/** Usuwa wpis użytkownika dla podanej daty. */
function delete_wpis(int $user_id, string $data): void
{
    $stmt = db()->prepare('DELETE FROM wpisy WHERE user_id = :u AND data = :data');
    $stmt->execute([':u' => $user_id, ':data' => $data]);
}

/** Usuwa wszystkie wpisy danego użytkownika. */
function delete_all_wpisy(int $user_id): void
{
    $stmt = db()->prepare('DELETE FROM wpisy WHERE user_id = :u');
    $stmt->execute([':u' => $user_id]);
}

/** Zwraca listę dat wpisów użytkownika, rosnąco. */
function all_dates(int $user_id): array
{
    $stmt = db()->prepare('SELECT data FROM wpisy WHERE user_id = :u ORDER BY data ASC');
    $stmt->execute([':u' => $user_id]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Data poprzedniego wpisu użytkownika względem podanej daty (lub null). */
function prev_date(int $user_id, string $data): ?string
{
    $stmt = db()->prepare(
        'SELECT data FROM wpisy WHERE user_id = :u AND data < :data ORDER BY data DESC LIMIT 1'
    );
    $stmt->execute([':u' => $user_id, ':data' => $data]);
    $row = $stmt->fetchColumn();

    return $row !== false ? (string) $row : null;
}

/** Data następnego wpisu użytkownika względem podanej daty (lub null). */
function next_date(int $user_id, string $data): ?string
{
    $stmt = db()->prepare(
        'SELECT data FROM wpisy WHERE user_id = :u AND data > :data ORDER BY data ASC LIMIT 1'
    );
    $stmt->execute([':u' => $user_id, ':data' => $data]);
    $row = $stmt->fetchColumn();

    return $row !== false ? (string) $row : null;
}

/** Data ostatniego wpisu użytkownika (do widoku startowego) lub null. */
function last_date(int $user_id): ?string
{
    $stmt = db()->prepare('SELECT MAX(data) FROM wpisy WHERE user_id = :u');
    $stmt->execute([':u' => $user_id]);
    $row = $stmt->fetchColumn();

    return $row !== null && $row !== false ? (string) $row : null;
}

/** Waliduje datę w formacie YYYY-MM-DD. */
function is_valid_date(string $data): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $data);

    return $d !== false && $d->format('Y-m-d') === $data;
}

/** Zwraca wszystkie wpisy użytkownika, posortowane rosnąco po dacie. */
function all_wpisy(int $user_id): array
{
    $stmt = db()->prepare('SELECT * FROM wpisy WHERE user_id = :u ORDER BY data ASC');
    $stmt->execute([':u' => $user_id]);

    return $stmt->fetchAll();
}

/** Wpisy użytkownika w formie mapy [data => wiersz]. */
function wpisy_map(int $user_id): array
{
    $map = [];
    foreach (all_wpisy($user_id) as $w) {
        $map[$w['data']] = $w;
    }

    return $map;
}

/** Przesuwa datę o podaną liczbę dni (wartości ujemne cofają). */
function date_shift(string $data, int $days): string
{
    $dt = new DateTime($data);
    $dt->modify(($days >= 0 ? '+' : '') . $days . ' day');

    return $dt->format('Y-m-d');
}

/** Poniedziałek tygodnia, do którego należy podana data. */
function week_start(string $data): string
{
    $dt  = new DateTime($data);
    $dow = (int) $dt->format('N'); // 1 = poniedziałek … 7 = niedziela
    if ($dow > 1) {
        $dt->modify('-' . ($dow - 1) . ' day');
    }

    return $dt->format('Y-m-d');
}

/** Niedziela tygodnia, do którego należy podana data. */
function week_end(string $data): string
{
    return date_shift(week_start($data), 6);
}

/** Numer tygodnia ISO, np. „41". */
function week_number(string $data): string
{
    return (new DateTime($data))->format('W');
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

/** Zwięzły zapis czasu trwania, np. „8 h", „45 min", „7 h 30 min". */
function format_duration_compact(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;

    if ($m === 0) {
        return $h . ' h';
    }
    if ($h === 0) {
        return $m . ' min';
    }

    return $h . ' h ' . $m . ' min';
}

/** Słownik nazw dni tygodnia: [pełna, skrócona]. */
function weekday_names(): array
{
    return [
        'Monday'    => ['poniedziałek', 'pon.'],
        'Tuesday'   => ['wtorek', 'wt.'],
        'Wednesday' => ['środa', 'śr.'],
        'Thursday'  => ['czwartek', 'czw.'],
        'Friday'    => ['piątek', 'pt.'],
        'Saturday'  => ['sobota', 'sob.'],
        'Sunday'    => ['niedziela', 'nd.'],
    ];
}

/** Krótka nazwa dnia tygodnia, np. „pt.". */
function format_weekday_short(string $data): string
{
    $names = weekday_names();

    return $names[(new DateTime($data))->format('l')][1];
}

/** Krótki zapis daty, np. „pt. 09.10.2026". */
function format_date_short(string $data): string
{
    return format_weekday_short($data) . ' ' . (new DateTime($data))->format('d.m.Y');
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

/**
 * Buduje adres kalendarza, zachowując kontekst ucznia (administrator, który
 * edytuje dziennik wybranego ucznia — parametr `uczen=ID`).
 *
 * @param string $params   dodatkowe parametry zapytania (bez wiodącego „?" ani „&")
 * @param int    $uczen_id ID ucznia lub 0, gdy oglądamy własny dziennik
 */
function kal_url(string $params = '', int $uczen_id = 0): string
{
    $parts = [];
    if ($uczen_id > 0) {
        $parts[] = 'uczen=' . $uczen_id;
    }

    $params = ltrim($params, '?&');
    if ($params !== '') {
        $parts[] = $params;
    }

    return 'kalendarz.php' . ($parts !== [] ? '?' . implode('&', $parts) : '');
}

/** Formatuje datę ISO na czytelną wersję (np. poniedziałek, 09.10.2026). */
function format_date_full(string $data): string
{
    $names = weekday_names();

    return $names[(new DateTime($data))->format('l')][0] . ', ' . (new DateTime($data))->format('d.m.Y');
}

/** Czytelny komunikat błędu kopii zapasowej dla kodu zwracanego przez backup.php. */
function backup_error_message(string $code): string
{
    $messages = [
        'brak-pliku'    => 'Nie wybrano pliku kopii zapasowej.',
        'za-duzy'       => 'Plik kopii jest za duży (przekroczono limit wgrywania na serwerze).',
        'blad-uploadu'  => 'Nie udało się wgrać pliku kopii zapasowej.',
        'nie-baza'      => 'Wybrany plik nie jest poprawną kopią dziennika (brak wymaganej struktury bazy).',
        'blad-zapisu'   => 'Nie udało się zapisać przywróconej bazy na serwerze.',
        'nieznana-akcja' => 'Nieznana operacja kopii zapasowej.',
        'csrf'          => 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.',
    ];

    return $messages[$code] ?? 'Nieznany błąd kopii zapasowej (' . $code . ').';
}

/* ------------------------------------------------------------------ *
 *  Załączniki (zdjęcia / pliki) przypisane do dnia
 * ------------------------------------------------------------------ */

/** Maksymalny rozmiar pojedynczego pliku (w bajtach). */
function zal_max_bytes(): int
{
    return 10 * 1024 * 1024; // 10 MB
}

/** Katalog na pliki załączników (obok bazy, poza docroot). */
function zal_dir(): string
{
    $dir = db_data_dir() . DIRECTORY_SEPARATOR . 'zalaczniki';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Nie mogę utworzyć katalogu na załączniki: ' . $dir);
    }

    return $dir;
}

/** Rozszerzenia, których nie przyjmujemy (skrypty / pliki wykonywalne). */
function zal_zakazane_rozszerzenia(): array
{
    return [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar',
        'phps', 'cgi', 'fcgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'bat',
        'cmd', 'com', 'scr', 'jar', 'htaccess', 'htpasswd',
    ];
}

/** Bezpieczne rozszerzenie pliku (tylko litery/cyfry, maks. 10 znaków). */
function zal_rozszerzenie(string $nazwa): string
{
    $ext = strtolower((string) pathinfo($nazwa, PATHINFO_EXTENSION));
    $ext = (string) preg_replace('/[^a-z0-9]/', '', $ext);
    if (strlen($ext) > 10) {
        $ext = substr($ext, 0, 10);
    }

    return $ext;
}

/** Lista załączników użytkownika dla dnia (rosnąco po dacie dodania). */
function zal_list(int $user_id, string $data): array
{
    $stmt = db()->prepare('SELECT * FROM zalaczniki WHERE user_id = :u AND data = :d ORDER BY id ASC');
    $stmt->execute([':u' => $user_id, ':d' => $data]);

    return $stmt->fetchAll();
}

/** Liczba załączników użytkownika wg dnia: [data => liczba]. */
function zal_counts(int $user_id): array
{
    $out  = [];
    $stmt = db()->prepare('SELECT data, COUNT(*) AS ile FROM zalaczniki WHERE user_id = :u GROUP BY data');
    $stmt->execute([':u' => $user_id]);
    foreach ($stmt->fetchAll() as $r) {
        $out[(string) $r['data']] = (int) $r['ile'];
    }

    return $out;
}

/** Pojedynczy załącznik po id lub null. */
function zal_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM zalaczniki WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Czy typ pliku nadaje się do bezpiecznego wyświetlenia jako obraz. */
function zal_is_inline_image(string $typ): bool
{
    return (bool) preg_match('#^image/(jpeg|png|gif|webp|bmp|avif)$#', strtolower($typ));
}

/** Czy typ pliku można pokazać w przeglądarce bez ryzyka (obraz lub PDF). */
function zal_is_inline(string $typ): bool
{
    $typ = strtolower($typ);

    return zal_is_inline_image($typ) || $typ === 'application/pdf';
}

/**
 * Dodaje załącznik do dnia (konto użytkownika).
 *
 * @param array|null $file wpis z $_FILES
 * @return string kod: 'ok' | 'brak' | 'za-duzy' | 'typ' | 'blad-uploadu' | 'blad-zapisu'
 */
function zal_add(int $user_id, string $data, ?array $file): string
{
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'brak';
    }

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return 'za-duzy';
    }
    if ($err !== UPLOAD_ERR_OK) {
        return 'blad-uploadu';
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return 'blad-uploadu';
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > zal_max_bytes()) {
        return 'za-duzy';
    }

    // Nazwa oryginalna — tylko do wyświetlenia i pobrania (nieużywana na dysku).
    $oryginal = (string) ($file['name'] ?? 'plik');
    $nazwa    = (string) preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $oryginal)));
    $nazwa    = trim($nazwa);
    if ($nazwa === '' || $nazwa[0] === '.') {
        return 'typ';
    }
    if (mb_strlen($nazwa) > 180) {
        $nazwa = mb_substr($nazwa, 0, 180);
    }

    $ext = zal_rozszerzenie($nazwa);
    if ($ext !== '' && in_array($ext, zal_zakazane_rozszerzenia(), true)) {
        return 'typ';
    }

    // Typ MIME rozpoznany na podstawie zawartości pliku.
    $typ = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi !== false) {
            $wykryty = finfo_file($fi, $tmp);
            finfo_close($fi);
            if (is_string($wykryty) && $wykryty !== '') {
                $typ = $wykryty;
            }
        }
    }

    $baza    = date('Ymd_His') . '_' . bin2hex(random_bytes(6));
    $naDysku = $ext !== '' ? $baza . '.' . $ext : $baza;
    $cel     = zal_dir() . DIRECTORY_SEPARATOR . $naDysku;

    if (!@move_uploaded_file($tmp, $cel)) {
        return 'blad-zapisu';
    }
    @chmod($cel, 0644);

    $stmt = db()->prepare(
        'INSERT INTO zalaczniki (user_id, data, nazwa, plik, typ, rozmiar, dodano)
         VALUES (:u, :d, :n, :p, :t, :r, :c)'
    );
    $stmt->execute([
        ':u' => $user_id,
        ':d' => $data,
        ':n' => $nazwa,
        ':p' => $naDysku,
        ':t' => $typ,
        ':r' => $size,
        ':c' => date('Y-m-d H:i:s'),
    ]);

    return 'ok';
}

/** Usuwa pojedynczy załącznik (plik z dysku + wpis w bazie). */
function zal_delete(int $id): bool
{
    $row = zal_get($id);
    if ($row === null) {
        return false;
    }

    $plik = zal_dir() . DIRECTORY_SEPARATOR . (string) $row['plik'];
    if (is_file($plik)) {
        @unlink($plik);
    }

    db()->prepare('DELETE FROM zalaczniki WHERE id = :id')->execute([':id' => $id]);

    return true;
}

/** Usuwa wszystkie załączniki danego dnia (pliki + wpisy). */
function zal_delete_for_date(int $user_id, string $data): void
{
    foreach (zal_list($user_id, $data) as $row) {
        zal_delete((int) $row['id']);
    }
}

/** Usuwa wszystkie załączniki danego użytkownika (pliki + wpisy). */
function zal_delete_all(int $user_id): void
{
    $stmt = db()->prepare('SELECT id FROM zalaczniki WHERE user_id = :u');
    $stmt->execute([':u' => $user_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        zal_delete((int) $id);
    }
}

/** Czytelny rozmiar pliku, np. „1,2 MB". */
function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' KB';
    }

    return number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' MB';
}

/** Zaszyfrowana (URL) nazwa pliku do nagłówka Content-Disposition. */
function zal_disposition_name(string $nazwa): string
{
    return rawurlencode($nazwa);
}

/* ------------------------------------------------------------------ *
 *  Ustawienia aplikacji (klucz => wartość)
 * ------------------------------------------------------------------ */

/** Pobiera wartość ustawienia lub $domyslna, gdy nie zapisano. */
function ustawienie(string $klucz, string $domyslna = ''): string
{
    $stmt = db()->prepare('SELECT wartosc FROM ustawienia WHERE klucz = :k');
    $stmt->execute([':k' => $klucz]);
    $row = $stmt->fetchColumn();

    return $row !== false ? (string) $row : $domyslna;
}

/** Zapisuje (nadpisuje) wartość ustawienia. */
function ustawienie_set(string $klucz, string $wartosc): void
{
    $stmt = db()->prepare(
        'INSERT INTO ustawienia (klucz, wartosc) VALUES (:k, :w)
         ON CONFLICT(klucz) DO UPDATE SET wartosc = excluded.wartosc'
    );
    $stmt->execute([':k' => $klucz, ':w' => $wartosc]);
}

/** Liczba godzin praktyk użytkownika do wypracowania (0 = nie ustawiono). */
function wymagane_godziny(int $user_id): int
{
    $stmt = db()->prepare('SELECT wymagane_godziny FROM uzytkownicy WHERE id = :id');
    $stmt->execute([':id' => $user_id]);
    $row = $stmt->fetchColumn();

    return $row !== false ? max(0, (int) $row) : 0;
}

/** Ustawia wymaganą liczbę godzin praktyk dla użytkownika. */
function wymagane_godziny_set(int $user_id, int $godziny): void
{
    $stmt = db()->prepare('UPDATE uzytkownicy SET wymagane_godziny = :g WHERE id = :id');
    $stmt->execute([':g' => max(0, $godziny), ':id' => $user_id]);
}
