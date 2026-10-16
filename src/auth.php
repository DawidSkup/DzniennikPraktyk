<?php
/**
 * Dziennik Praktyk — uwierzytelnianie (wiele kont, role) i ochrona CSRF.
 *
 * Konta trzymamy w tabeli `uzytkownicy` (login + hash hasła + rola).
 * Hasła nigdy nie zapisujemy jawnie — używamy password_hash()/password_verify().
 * Sesja oparta o pliki PHP, ciasteczko HttpOnly + SameSite=Lax.
 *
 * Role: 'admin' (administrator) i 'uczen' (uczeń). Administratorzy mają panel
 * (`admin.php`) do zakładania kont i mogą przeglądać dzienniczki uczniów.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/** Uruchamia sesję (raz) z bezpiecznym ciasteczkiem. */
function auth_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_name('dp_sid');
    session_start();
}

/* ------------------------------------------------------------------ *
 *  Konta (użytkownicy) i role
 * ------------------------------------------------------------------ */

/** Pojedyncze konto po id lub null. */
function user_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM uzytkownicy WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Konto po loginie lub null. */
function user_by_login(string $login): ?array
{
    $stmt = db()->prepare('SELECT * FROM uzytkownicy WHERE login = :l');
    $stmt->execute([':l' => trim($login)]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Konto po loginie albo adresie e-mail (do resetu hasła) lub null. */
function user_by_login_or_email(string $wartosc): ?array
{
    $wartosc = trim($wartosc);
    if ($wartosc === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM uzytkownicy WHERE login = :w OR email = :w LIMIT 1');
    $stmt->execute([':w' => $wartosc]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Wszystkie konta: uczniowie najpierw alfabetycznie, potem administratorzy. */
function users_all(): array
{
    return db()->query(
        "SELECT * FROM uzytkownicy
          ORDER BY CASE rola WHEN 'uczen' THEN 0 ELSE 1 END,
                   COALESCE(NULLIF(imie_nazwisko, ''), login) COLLATE NOCASE ASC"
    )->fetchAll();
}

/** Liczba kont. */
function users_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM uzytkownicy')->fetchColumn();
}

/** Czytelna nazwa użytkownika (imię i nazwisko, a gdy brak — login). */
function user_label(array $user): string
{
    $imie = trim((string) ($user['imie_nazwisko'] ?? ''));

    return $imie !== '' ? $imie : (string) ($user['login'] ?? '');
}

/** Klasa ucznia (pusta dla kont bez klasy albo administratorów). */
function user_klasa(array $user): string
{
    return trim((string) ($user['klasa'] ?? ''));
}

/** Etykieta dziennika z klasą, np. „Jan Kowalski (3T)". */
function user_label_klasa(array $user): string
{
    $klasa = user_klasa($user);

    return user_label($user) . ($klasa !== '' ? ' (' . $klasa . ')' : '');
}

/** Czy konto zostało już utworzone (kreator pierwszego uruchomienia). */
function auth_has_account(): bool
{
    return users_count() > 0;
}

/** Zalogowany użytkownik (na podstawie sesji) lub null. */
function auth_user(): ?array
{
    auth_session_start();

    // Cache w obrębie żądania, ale kluczowany po id z sesji — dzięki temu
    // zmiana użytkownika (logowanie/wylogowanie) zawsze daje świeże dane.
    static $cache_id   = null;
    static $cache_user = null;

    $uid = $_SESSION['uid'] ?? null;
    if ($uid === null || !ctype_digit((string) $uid)) {
        return null;
    }

    $uid = (int) $uid;
    if ($cache_id === $uid) {
        return $cache_user;
    }

    $cache_id   = $uid;
    $cache_user = user_get($uid);

    return $cache_user;
}

/** Czy użytkownik jest zalogowany. */
function auth_is_logged_in(): bool
{
    return auth_user() !== null;
}

/** Rola zalogowanego użytkownika ('admin' | 'uczen' | '' gdy niezalogowany). */
function auth_role(): string
{
    return (string) (auth_user()['rola'] ?? '');
}

/** Czy zalogowany użytkownik jest administratorem. */
function auth_is_admin(): bool
{
    return auth_role() === 'admin';
}

/** Tworzy pierwsze konto (administratora). Tylko gdy żadne jeszcze nie istnieje. */
function auth_create_account(string $login, string $haslo, string $email = ''): bool
{
    if (auth_has_account()) {
        return false;
    }

    $stmt = db()->prepare(
        'INSERT INTO uzytkownicy (login, haslo_hash, email, rola, imie_nazwisko, created_at)
         VALUES (:l, :h, :e, "admin", :n, :c)'
    );
    $stmt->execute([
        ':l' => trim($login),
        ':h' => password_hash($haslo, PASSWORD_DEFAULT),
        ':e' => trim($email),
        ':n' => trim($login),
        ':c' => date('Y-m-d H:i:s'),
    ]);

    return true;
}

/**
 * Zakłada konto (panel administratora).
 * Zwraca kod: 'ok' | 'login' | 'krotki' | 'haslo' | 'email' | 'rola' | 'klasa' | 'zajety'.
 */
function user_create(string $login, string $haslo, string $rola, string $imie, string $klasa, string $email): string
{
    $login = trim($login);
    $imie  = trim($imie);
    $klasa = trim($klasa);
    $email = trim($email);

    if ($login === '') {
        return 'login';
    }
    if (strlen($login) < 3) {
        return 'krotki';
    }
    if (!in_array($rola, ['admin', 'uczen'], true)) {
        return 'rola';
    }
    // Klasę podaje się obowiązkowo przy zakładaniu konta ucznia.
    if ($rola === 'uczen' && $klasa === '') {
        return 'klasa';
    }
    if (strlen($haslo) < 6) {
        return 'haslo';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'email';
    }
    if (user_by_login($login) !== null) {
        return 'zajety';
    }

    $stmt = db()->prepare(
        'INSERT INTO uzytkownicy (login, haslo_hash, email, rola, imie_nazwisko, klasa, created_at)
         VALUES (:l, :h, :e, :r, :n, :k, :c)'
    );
    $stmt->execute([
        ':l' => $login,
        ':h' => password_hash($haslo, PASSWORD_DEFAULT),
        ':e' => $email,
        ':r' => $rola,
        ':n' => $imie,
        ':k' => $rola === 'uczen' ? $klasa : '', // klasa dotyczy tylko uczniów
        ':c' => date('Y-m-d H:i:s'),
    ]);

    return 'ok';
}

/** Ustawia nowe hasło wskazanego konta (panel administratora). */
function user_set_password(int $id, string $haslo): bool
{
    if (strlen($haslo) < 6 || user_get($id) === null) {
        return false;
    }

    $stmt = db()->prepare('UPDATE uzytkownicy SET haslo_hash = :h WHERE id = :id');
    $stmt->execute([':h' => password_hash($haslo, PASSWORD_DEFAULT), ':id' => $id]);

    return true;
}

/** Etykieta „konto o takiej nazwie już istnieje" dla panelu administratora. */
function user_login_exists(string $login, int $pomin = 0): bool
{
    $istnieje = user_by_login($login);

    return $istnieje !== null && (int) $istnieje['id'] !== $pomin;
}

/**
 * Ustawia login (nazwę konta) wskazanego użytkownika.
 * Zwraca false, gdy login jest za krótki albo zajęty.
 */
function user_set_login(int $id, string $login): bool
{
    $login = trim($login);
    if (strlen($login) < 3 || user_get($id) === null) {
        return false;
    }
    if (user_login_exists($login, $id)) {
        return false;
    }

    $stmt = db()->prepare('UPDATE uzytkownicy SET login = :l WHERE id = :id');
    $stmt->execute([':l' => $login, ':id' => $id]);

    return true;
}

/** Ustawia imię i nazwisko (etykietę) konta oraz opcjonalnie klasę. */
function user_set_name(int $id, string $imie, ?string $klasa = null): bool
{
    if (user_get($id) === null) {
        return false;
    }

    if ($klasa === null) {
        $stmt = db()->prepare('UPDATE uzytkownicy SET imie_nazwisko = :n WHERE id = :id');
        $stmt->execute([':n' => trim($imie), ':id' => $id]);

        return true;
    }

    $stmt = db()->prepare('UPDATE uzytkownicy SET imie_nazwisko = :n, klasa = :k WHERE id = :id');
    $stmt->execute([':n' => trim($imie), ':k' => trim($klasa), ':id' => $id]);

    return true;
}

/**
 * Masowa zmiana klasy: wszystkim uczniom starej klasy ustawia nową nazwę klasy.
 * Pusta nowa klasa przenosi uczniów do grupy „Bez klasy".
 *
 * @return int liczba uczniów w grupie (0 gdy nie ma takiej grupy albo nazwa za długa)
 */
function user_set_klasa_bulk(string $from, string $to): int
{
    $from = trim($from);
    $to   = trim($to);

    // Ta sama granica co pole w panelu (maxlength="40").
    if (mb_strlen($to) > 40) {
        return 0;
    }

    // Ilu uczniów należy teraz do wskazanej klasy? (0 = grupa nie istnieje)
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'uczen' AND COALESCE(klasa, '') = :stara"
    );
    $stmt->execute([':stara' => $from]);
    $ile = (int) $stmt->fetchColumn();

    if ($ile === 0) {
        return 0;
    }

    $stmt = db()->prepare(
        "UPDATE uzytkownicy SET klasa = :nowa
          WHERE rola = 'uczen' AND COALESCE(klasa, '') = :stara"
    );
    $stmt->execute([':nowa' => $to, ':stara' => $from]);

    return $ile;
}

/**
 * Masowo ustawia wymagane godziny praktyk i/lub okres (od – do) wszystkim
 * uczniom podanej klasy. Wartość null oznacza „nie zmieniaj" (w godzinach
 * 0 czyści wymaganie). Pusta nazwa klasy to grupa „Bez klasy".
 * Administratorzy nie są objęci — ustawiamy tylko konta o roli 'uczen'.
 *
 * @return int liczba uczniów w klasie (0 = brak uczniów / nie ma takiej grupy)
 */
function user_set_klasa_parametry(string $klasa, ?int $godz, ?string $od, ?string $do): int
{
    $klasa = trim($klasa);

    $stmt = db()->prepare(
        "SELECT id FROM uzytkownicy WHERE rola = 'uczen' AND COALESCE(klasa, '') = :klasa"
    );
    $stmt->execute([':klasa' => $klasa]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($ids as $id) {
        if ($godz !== null) {
            wymagane_godziny_set($id, $godz);
        }
        if ($od !== null && $do !== null) {
            praktyki_okres_set($id, $od, $do);
        }
    }

    return count($ids);
}

/**
 * Usuwa wszystkie konta uczniów z podanej klasy wraz z ich dziennikami.
 * Pusta nazwa oznacza grupę „Bez klasy".
 *
 * @return int liczba usuniętych kont (0 = brak uczniów w tej klasie)
 */
function user_delete_class(string $klasa): int
{
    $klasa = trim($klasa);

    $stmt = db()->prepare(
        "SELECT id FROM uzytkownicy WHERE rola = 'uczen' AND COALESCE(klasa, '') = :klasa"
    );
    $stmt->execute([':klasa' => $klasa]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $usuniete = 0;
    foreach ($ids as $id) {
        if (user_delete($id)) {
            $usuniete++;
        }
    }

    return $usuniete;
}

/**
 * Usuwa konto wraz z jego dziennikiem (wpisy + metadane załączników).
 * Nie pozwala usunąć ostatniego administratora.
 */
function user_delete(int $id): bool
{
    $user = user_get($id);
    if ($user === null) {
        return false;
    }

    // Zabezpieczenie: nie usuwamy ostatniego administratora.
    if (($user['rola'] ?? '') === 'admin') {
        $adminow = (int) db()->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'admin'")->fetchColumn();
        if ($adminow <= 1) {
            return false;
        }
    }

    // Usuń pliki i metadane załączników tego konta.
    zal_delete_all($id);

    db()->prepare('DELETE FROM wpisy WHERE user_id = :u')->execute([':u' => $id]);
    db()->prepare('DELETE FROM uzytkownicy WHERE id = :id')->execute([':id' => $id]);

    return true;
}

/**
 * Weryfikuje dane logowania i rolę, po czym zakłada sesję.
 * Pusta rola = brak sprawdzania roli (zachowanie jak wcześniej).
 *
 * @return string 'ok' (zalogowano) | 'dane' (błędny login/hasło) | 'rola' (konto ma inną rolę)
 */
function auth_login_role(string $login, string $haslo, string $rola = ''): string
{
    $acc = user_by_login($login);
    if ($acc === null || !password_verify($haslo, (string) $acc['haslo_hash'])) {
        return 'dane';
    }

    // Wybrana na ekranie logowania rola musi zgadzać się z rolą konta.
    if ($rola !== '' && (string) $acc['rola'] !== $rola) {
        return 'rola';
    }

    auth_session_start();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $acc['id'];

    return 'ok';
}

/** Weryfikuje dane logowania i zakłada sesję. */
function auth_login(string $login, string $haslo): bool
{
    return auth_login_role($login, $haslo) === 'ok';
}

/** Kończy sesję i usuwa ciasteczko sesyjne. */
function auth_logout(): void
{
    auth_session_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'] ?? '/',
            $p['domain'] ?? '',
            (bool) ($p['secure'] ?? false),
            (bool) ($p['httponly'] ?? true)
        );
    }

    session_destroy();
}

/** Zmienia hasło zalogowanego użytkownika — wymaga podania obecnego hasła. */
function auth_change_password(string $obecne, string $nowe): bool
{
    $acc = auth_user();
    if ($acc === null || !password_verify($obecne, (string) $acc['haslo_hash'])) {
        return false;
    }

    return user_set_password((int) $acc['id'], $nowe);
}

/** Adres e-mail zalogowanego użytkownika (do resetu hasła) lub pusty tekst. */
function auth_email(): string
{
    return trim((string) (auth_user()['email'] ?? ''));
}

/** Ustawia/zmienia adres e-mail zalogowanego użytkownika. */
function auth_set_email(string $email): bool
{
    $acc = auth_user();
    if ($acc === null) {
        return false;
    }

    $stmt = db()->prepare('UPDATE uzytkownicy SET email = :e WHERE id = :id');
    $stmt->execute([':e' => trim($email), ':id' => (int) $acc['id']]);

    return true;
}

/**
 * Zmienia nazwę konta (login) zalogowanego użytkownika.
 * Wymaga co najmniej 3 znaków. Zwraca true, gdy udało się zapisać.
 */
function auth_set_login(string $login): bool
{
    $login = trim($login);
    if (strlen($login) < 3) {
        return false;
    }

    $acc = auth_user();
    if ($acc === null) {
        return false;
    }

    try {
        $stmt = db()->prepare('UPDATE uzytkownicy SET login = :l WHERE id = :id');
        $stmt->execute([':l' => $login, ':id' => (int) $acc['id']]);
    } catch (PDOException $e) {
        // Np. naruszenie unikalności loginu — nie zmieniamy konta.
        return false;
    }

    return true;
}

/**
 * Ustawia imię i nazwisko (etykietę) zalogowanego użytkownika.
 * Puste pole czyści nazwę — w dzienniku pokaże się wtedy login.
 * Klasa ucznia pozostaje bez zmian.
 */
function auth_set_name(string $imie): bool
{
    $acc = auth_user();
    if ($acc === null) {
        return false;
    }

    return user_set_name((int) $acc['id'], $imie, null);
}

/* ------------------------------------------------------------------ *
 *  Kontekst widoku: własny dziennik albo podgląd dziennika ucznia
 * ------------------------------------------------------------------ */

/**
 * Ustala, czyj dziennik oglądamy.
 *
 * Administrator może wejść na dziennik ucznia przez `?uczen=ID` (GET) albo
 * przez formularz (`uczen` w POST) i tam dodawać, edytować i usuwać wpisy
 * w imieniu ucznia (`as_admin=true`). Uczeń ogląda własny dziennik,
 * a administrator — który nie prowadzi własnego — trafia do panelu
 * (`admin.php`).
 *
 * @return array{viewer:?array, user:?array, as_admin:bool}
 */
function auth_view_context(): array
{
    $viewer = auth_user();
    $ctx    = ['viewer' => $viewer, 'user' => $viewer, 'as_admin' => false];

    if ($viewer !== null && ($viewer['rola'] ?? '') === 'admin') {
        // Kontekst ucznia przekazujemy w adresie (GET) albo w formularzu (POST).
        $uczen_id = $_GET['uczen'] ?? $_POST['uczen'] ?? null;
        if ($uczen_id !== null && $uczen_id !== '') {
            $uczen = user_get((int) $uczen_id);
            if ($uczen !== null && ($uczen['rola'] ?? '') === 'uczen') {
                $ctx['user']     = $uczen;
                $ctx['as_admin'] = true;
            }
        }
    }

    return $ctx;
}

/**
 * Administrator nie prowadzi własnego dziennika (kalendarza) — jego miejscem
 * pracy jest panel (`admin.php`). Gdy administrator wejdzie na stronę dziennika
 * bez wybranego ucznia (`?uczen=ID`), odsyłamy go do panelu.
 *
 * @param bool $viewing_student true, gdy administrator pracuje na dzienniku ucznia.
 */
function auth_guard_own_journal(bool $viewing_student): void
{
    if (auth_is_admin() && !$viewing_student) {
        header('Location: admin.php');
        exit;
    }
}

/**
 * Adres strony startowej zalogowanego użytkownika. Administrator nie ma
 * własnego dziennika, więc jego „startem" jest panel (`admin.php`).
 */
function auth_home_url(): string
{
    return auth_is_admin() ? 'admin.php' : 'index.php';
}

/** Pilnuje dostępu: brak konta → kreator, brak sesji → ekran logowania. */
function auth_require_login(): void
{
    auth_session_start();

    if (!auth_has_account()) {
        header('Location: login.php?setup=1');
        exit;
    }
    if (!auth_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** Pilnuje dostępu administratora — pozostałych odsyła na stronę główną. */
function auth_require_admin(): void
{
    auth_require_login();

    if (!auth_is_admin()) {
        header('Location: index.php');
        exit;
    }
}

/* ------------------------------------------------------------------ *
 *  Reset hasła — kod wysyłany e-mailem
 * ------------------------------------------------------------------ */

/** Jak długo ważny jest kod resetu (w sekundach). */
function auth_reset_ttl(): int
{
    return 600; // 10 minut
}

/** Po ilu błędnych kodach reset zostaje unieważniony. */
function auth_reset_max_proby(): int
{
    return 5;
}

/** Minimalny odstęp między wysyłkami kodu (w sekundach). */
function auth_reset_odstep(): int
{
    return 30;
}

/** Ile sekund pozostało do ponownej wysyłki kodu dla konta (0 = można). */
function auth_reset_wait_for(int $user_id): int
{
    $u = user_get($user_id);
    if ($u === null) {
        return 0;
    }

    $pozostalo = (int) ($u['reset_wyslano'] ?? 0) + auth_reset_odstep() - time();

    return $pozostalo > 0 ? $pozostalo : 0;
}

/** Konto, dla którego trwa reset w tej sesji (lub null). */
function auth_reset_target(): ?array
{
    auth_session_start();
    $uid = $_SESSION['reset_uid'] ?? null;
    if ($uid === null || !ctype_digit((string) $uid)) {
        return null;
    }

    return user_get((int) $uid);
}

/** Ile sekund pozostało do ponownej wysyłki kodu (sesja bieżącego resetu). */
function auth_reset_wait(): int
{
    $u = auth_reset_target();

    return $u !== null ? auth_reset_wait_for((int) $u['id']) : 0;
}

/**
 * Rozpoczyna reset dla wskazanego konta: generuje 6-cyfrowy kod i zapisuje hash.
 * Zwraca kod (jawny — tylko do wysłania mailem) albo null, gdy nie można.
 */
function auth_reset_start_for(int $user_id): ?string
{
    $u = user_get($user_id);
    if ($u === null || trim((string) ($u['email'] ?? '')) === '') {
        return null;
    }
    if (auth_reset_wait_for($user_id) > 0) {
        return null;
    }

    $kod = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = db()->prepare(
        'UPDATE uzytkownicy
            SET reset_hash = :h, reset_wygasa = :w, reset_proby = 0, reset_wyslano = :t
          WHERE id = :id'
    );
    $stmt->execute([
        ':h'  => password_hash($kod, PASSWORD_DEFAULT),
        ':w'  => time() + auth_reset_ttl(),
        ':t'  => time(),
        ':id' => $user_id,
    ]);

    auth_session_start();
    $_SESSION['reset_uid'] = $user_id;

    return $kod;
}

/**
 * Weryfikuje kod i ustawia nowe hasło dla konta z sesji resetu.
 * Zwraca status: 'ok' | 'zly' | 'wygasl' | 'brak' | 'proby'.
 */
function auth_reset_confirm(string $kod, string $nowe): string
{
    $acc = auth_reset_target();
    if ($acc === null) {
        return 'brak';
    }

    $hash   = (string) ($acc['reset_hash'] ?? '');
    $wygasa = (int) ($acc['reset_wygasa'] ?? 0);

    if ($hash === '' || $wygasa === 0) {
        return 'brak';
    }
    if (time() > $wygasa) {
        return 'wygasl';
    }
    if ((int) ($acc['reset_proby'] ?? 0) >= auth_reset_max_proby()) {
        return 'proby';
    }

    if (!password_verify($kod, $hash)) {
        db()->prepare('UPDATE uzytkownicy SET reset_proby = reset_proby + 1 WHERE id = :id')
            ->execute([':id' => (int) $acc['id']]);

        return 'zly';
    }

    db()->prepare(
        'UPDATE uzytkownicy
            SET haslo_hash = :h, reset_hash = "", reset_wygasa = 0, reset_proby = 0
          WHERE id = :id'
    )->execute([
        ':h'  => password_hash($nowe, PASSWORD_DEFAULT),
        ':id' => (int) $acc['id'],
    ]);

    auth_session_start();
    unset($_SESSION['reset_uid']);

    return 'ok';
}

/* ------------------------------------------------------------------ *
 *  Ograniczanie liczby prób logowania (po 5 nieudanych próbach — 10 s)
 * ------------------------------------------------------------------ */

/** Maksymalna liczba nieudanych prób przed blokadą logowania. */
function auth_max_attempts(): int
{
    return 5;
}

/** Czas blokady logowania po przekroczeniu limitu (w sekundach). */
function auth_lock_seconds(): int
{
    return 10;
}

/** Ścieżka do pliku ze stanem prób (obok bazy, poza katalogiem serwera). */
function auth_throttle_path(): string
{
    return db_data_dir() . DIRECTORY_SEPARATOR . 'logowanie.json';
}

/** Wczytuje stan prób logowania: ['proby' => int, 'blokada_do' => int]. */
function auth_throttle_state(): array
{
    $stan = ['proby' => 0, 'blokada_do' => 0];
    $plik = auth_throttle_path();

    if (is_file($plik)) {
        $raw = @file_get_contents($plik);
        if (is_string($raw) && $raw !== '') {
            $dane = json_decode($raw, true);
            if (is_array($dane)) {
                $stan['proby']      = max(0, (int) ($dane['proby'] ?? 0));
                $stan['blokada_do'] = max(0, (int) ($dane['blokada_do'] ?? 0));
            }
        }
    }

    return $stan;
}

/** Zapisuje stan prób (z blokadą pliku, by uniknąć wyścigów). */
function auth_throttle_save(array $stan): void
{
    $plik = auth_throttle_path();
    $dir  = dirname($plik);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $json = json_encode([
        'proby'      => max(0, (int) ($stan['proby'] ?? 0)),
        'blokada_do' => max(0, (int) ($stan['blokada_do'] ?? 0)),
    ]);

    @file_put_contents($plik, (string) $json, LOCK_EX);
}

/** Ile sekund pozostało blokady logowania (0 = brak blokady). */
function auth_login_lock_remaining(): int
{
    $stan      = auth_throttle_state();
    $pozostalo = $stan['blokada_do'] - time();

    return $pozostalo > 0 ? $pozostalo : 0;
}

/** Liczba pozostałych prób przed blokadą. */
function auth_login_attempts_left(): int
{
    $stan    = auth_throttle_state();
    $zostalo = auth_max_attempts() - $stan['proby'];

    return $zostalo > 0 ? $zostalo : 0;
}

/**
 * Rejestruje nieudaną próbę. Po przekroczeniu limitu zakłada blokadę
 * i zwraca liczbę sekund, na jaką logowanie zostało zablokowane (0 = brak).
 */
function auth_login_register_failure(): int
{
    $stan = auth_throttle_state();
    $stan['proby']++;

    if ($stan['proby'] >= auth_max_attempts()) {
        $stan['proby']      = 0;
        $stan['blokada_do'] = time() + auth_lock_seconds();
    }

    auth_throttle_save($stan);

    return auth_login_lock_remaining();
}

/** Czyści licznik prób (po udanym logowaniu). */
function auth_login_reset(): void
{
    auth_throttle_save(['proby' => 0, 'blokada_do' => 0]);
}

/* ------------------------------------------------------------------ *
 *  CSRF — token per sesja, wymagany przy operacjach zmieniających stan
 * ------------------------------------------------------------------ */

/** Zwraca (i w razie potrzeby tworzy) token CSRF bieżącej sesji. */
function csrf_token(): string
{
    auth_session_start();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf'];
}

/** Ukryte pole formularza z tokenem CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Sprawdza zgodność przesłanego tokenu CSRF z sesją. */
function csrf_check(?string $token): bool
{
    auth_session_start();

    return is_string($token) && $token !== ''
        && !empty($_SESSION['csrf'])
        && hash_equals((string) $_SESSION['csrf'], $token);
}
