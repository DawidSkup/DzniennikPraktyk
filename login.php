<?php
/**
 * Dziennik Praktyk — logowanie i kreator pierwszego konta.
 *
 * Gdy konto jeszcze nie istnieje, ten sam ekran pokazuje kreator (login +
 * hasło + powtórzenie). Po utworzeniu konta użytkownik jest od razu zalogowany.
 * Gdy konto istnieje — zwykły formularz logowania.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';

auth_session_start();

$setup = !auth_has_account();

// Zalogowany użytkownik nie ma czego szukać na ekranie logowania.
if (!$setup && auth_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login  = trim((string) ($_POST['login'] ?? ''));
    $haslo  = (string) ($_POST['haslo'] ?? '');
    $haslo2 = (string) ($_POST['haslo2'] ?? '');

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (!$setup) {
        // --- Logowanie ---
        if ($login === '' || $haslo === '') {
            $error = 'Podaj login i hasło.';
        } elseif (auth_login($login, $haslo)) {
            header('Location: index.php');
            exit;
        } else {
            usleep(400000); // drobne opóźnienie utrudniające zgadywanie hasła
            $error = 'Nieprawidłowy login lub hasło.';
        }
    } else {
        // --- Kreator pierwszego konta ---
        $setup = !auth_has_account(); // ktoś mógł zdążyć utworzyć konto
        if ($login === '') {
            $error = 'Podaj login.';
        } elseif (strlen($login) < 3) {
            $error = 'Login musi mieć co najmniej 3 znaki.';
        } elseif (strlen($haslo) < 6) {
            $error = 'Hasło musi mieć co najmniej 6 znaków.';
        } elseif ($haslo !== $haslo2) {
            $error = 'Hasła nie są identyczne.';
        } elseif (!auth_create_account($login, $haslo)) {
            $error = 'Konto już istnieje. Zaloguj się.';
            $setup = false;
        } elseif (auth_login($login, $haslo)) {
            header('Location: index.php?witaj=1');
            exit;
        } else {
            $error = 'Konto utworzone, ale logowanie nie powiodło się. Spróbuj się zalogować.';
            $setup = false;
        }
    }
}

$page_title = $setup ? 'Utwórz konto' : 'Zaloguj się';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicon.png">
    <title>Dziennik Praktyk — <?= e($page_title) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
    <script src="assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?: time() ?>"></script>
</head>
<body class="auth-body">
<div class="auth">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon">📘</span>
            <div>
                <h1 class="auth-title">Dziennik Praktyk</h1>
                <p class="brand-sub"><?= $setup
                    ? 'Utwórz konto, aby zacząć prowadzić dziennik.'
                    : 'Zaloguj się, aby kontynuować.' ?></p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <p class="alert alert-danger">⚠ <?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php" class="auth-form" autocomplete="on">
            <?= csrf_field() ?>

            <div class="field">
                <label for="a-login">Login</label>
                <input id="a-login" type="text" name="login" value="<?= e($login) ?>"
                       autocomplete="username" autofocus required>
            </div>

            <div class="field">
                <label for="a-haslo">Hasło</label>
                <input id="a-haslo" type="password" name="haslo"
                       autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" required>
            </div>

            <?php if ($setup): ?>
                <div class="field">
                    <label for="a-haslo2">Powtórz hasło</label>
                    <input id="a-haslo2" type="password" name="haslo2" autocomplete="new-password" required>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-save auth-submit">
                <?= $setup ? '✅ Utwórz konto i zaloguj się' : '🔓 Zaloguj się' ?>
            </button>
        </form>
    </div>

    <p class="auth-foot">Dziennik Praktyk · dostęp do danych po zalogowaniu</p>
</div>
</body>
</html>
