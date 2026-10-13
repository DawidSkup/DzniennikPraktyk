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
function auth_create_account(string $login, string $haslo): bool
{
    if (auth_has_account()) {
        return false;
    }

    $stmt = db()->prepare(
        'INSERT INTO uzytkownicy (login, haslo_hash, created_at) VALUES (:l, :h, :c)'
    );
    $stmt->execute([
        ':l' => $login,
        ':h' => password_hash($haslo, PASSWORD_DEFAULT),
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
