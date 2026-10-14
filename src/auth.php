<?php
/**
 * Dziennik Praktyk — uwierzytelnianie (jedno wspólne konto) i ochrona CSRF.
 *
 * Konto trzymamy w tabeli `uzytkownicy` (login + hash hasła). Hasła nigdy nie
 * zapisujemy jawnie — używamy password_hash()/password_verify().
 * Sesja oparta o pliki PHP, ciasteczko HttpOnly + SameSite=Lax.
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

/** Zwraca konto (pierwszy, jedyny wiersz) lub null, gdy jeszcze nie istnieje. */
function auth_account(): ?array
{
    $row = db()->query('SELECT * FROM uzytkownicy ORDER BY id ASC LIMIT 1')->fetch();

    return $row ?: null;
}

/** Czy konto zostało już utworzone (kreator pierwszego uruchomienia). */
function auth_has_account(): bool
{
    return auth_account() !== null;
}

/** Zalogowany użytkownik (na podstawie sesji) lub null. */
function auth_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    auth_session_start();
    $uid = $_SESSION['uid'] ?? null;
    if ($uid === null || !ctype_digit((string) $uid)) {
        $user = null;

        return $user;
    }

    $stmt = db()->prepare('SELECT * FROM uzytkownicy WHERE id = :id');
    $stmt->execute([':id' => (int) $uid]);
    $row  = $stmt->fetch();
    $user = $row ?: null;

    return $user;
}

/** Czy użytkownik jest zalogowany. */
function auth_is_logged_in(): bool
{
    return auth_user() !== null;
}

/** Tworzy konto (tylko gdy żadne jeszcze nie istnieje). */
function auth_create_account(string $login, string $haslo, string $email = ''): bool
{
    if (auth_has_account()) {
        return false;
    }

    $stmt = db()->prepare(
        'INSERT INTO uzytkownicy (login, haslo_hash, email, created_at) VALUES (:l, :h, :e, :c)'
    );
    $stmt->execute([
        ':l' => $login,
        ':h' => password_hash($haslo, PASSWORD_DEFAULT),
        ':e' => trim($email),
        ':c' => date('Y-m-d H:i:s'),
    ]);

    return true;
}

/** Weryfikuje dane logowania i zakłada sesję. */
function auth_login(string $login, string $haslo): bool
{
    $acc = auth_account();
    if ($acc === null) {
        return false;
    }

    if (!hash_equals((string) $acc['login'], $login)
        || !password_verify($haslo, (string) $acc['haslo_hash'])) {
        return false;
    }

    auth_session_start();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $acc['id'];

    return true;
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

/** Zmienia hasło — wymaga podania obecnego hasła. */
function auth_change_password(string $obecne, string $nowe): bool
{
    $acc = auth_account();
    if ($acc === null || !password_verify($obecne, (string) $acc['haslo_hash'])) {
        return false;
    }

    $stmt = db()->prepare('UPDATE uzytkownicy SET haslo_hash = :h WHERE id = :id');
    $stmt->execute([
        ':h'  => password_hash($nowe, PASSWORD_DEFAULT),
        ':id' => (int) $acc['id'],
    ]);

    return true;
}

/** Adres e-mail przypisany do konta (do resetu hasła) lub pusty tekst. */
function auth_email(): string
{
    $acc = auth_account();

    return $acc ? trim((string) ($acc['email'] ?? '')) : '';
}

/** Ustawia/zmienia adres e-mail konta. */
function auth_set_email(string $email): bool
{
    $acc = auth_account();
    if ($acc === null) {
        return false;
    }

    $stmt = db()->prepare('UPDATE uzytkownicy SET email = :e WHERE id = :id');
    $stmt->execute([':e' => trim($email), ':id' => (int) $acc['id']]);

    return true;
}

/**
 * Zmienia nazwę konta (login). Wymaga co najmniej 3 znaków.
 * Zwraca true, gdy udało się zapisać (login jest unikalny w bazie).
 */
function auth_set_login(string $login): bool
{
    $login = trim($login);
    if (strlen($login) < 3) {
        return false;
    }

    $acc = auth_account();
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

/** Ile sekund pozostało do możliwości ponownego wysłania kodu (0 = można). */
function auth_reset_wait(): int
{
    $acc = auth_account();
    if ($acc === null) {
        return 0;
    }

    $pozostalo = (int) ($acc['reset_wyslano'] ?? 0) + auth_reset_odstep() - time();

    return $pozostalo > 0 ? $pozostalo : 0;
}

/**
 * Rozpoczyna reset: generuje 6-cyfrowy kod i zapisuje jego hash.
 * Zwraca kod (jawny — tylko do wysłania mailem) albo null, gdy nie można
 * (brak konta, brak e-maila albo zbyt szybka ponowna próba).
 */
function auth_reset_start(): ?string
{
    $acc = auth_account();
    if ($acc === null || trim((string) ($acc['email'] ?? '')) === '') {
        return null;
    }
    if (auth_reset_wait() > 0) {
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
        ':id' => (int) $acc['id'],
    ]);

    return $kod;
}

/**
 * Weryfikuje kod i ustawia nowe hasło.
 * Zwraca status: 'ok' | 'zly' | 'wygasl' | 'brak' | 'proby'.
 */
function auth_reset_confirm(string $kod, string $nowe): string
{
    $acc = auth_account();
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
    $stan   = auth_throttle_state();
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
