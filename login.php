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
$info  = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login  = trim((string) ($_POST['login'] ?? ''));
    $haslo  = (string) ($_POST['haslo'] ?? '');
    $haslo2 = (string) ($_POST['haslo2'] ?? '');
    $email  = trim((string) ($_POST['email'] ?? ''));

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (!$setup) {
        // --- Logowanie ---
        $pozostalo = auth_login_lock_remaining();

        if ($pozostalo > 0) {
            $error = 'Zbyt wiele nieudanych prób. Odczekaj ' . $pozostalo . ' s.';
        } elseif ($login === '' || $haslo === '') {
            $error = 'Podaj login i hasło.';
        } elseif (auth_login($login, $haslo)) {
            auth_login_reset();
            header('Location: index.php');
            exit;
        } else {
            usleep(400000); // drobne opóźnienie utrudniające zgadywanie hasła
            $blokada_teraz = auth_login_register_failure();
            if ($blokada_teraz > 0) {
                $error = 'Zbyt wiele nieudanych prób. Logowanie zablokowane na ' . $blokada_teraz . ' s.';
            } else {
                $error = 'Nieprawidłowy login lub hasło. Pozostało prób: ' . auth_login_attempts_left() . '.';
            }
        }
    } else {
        // --- Kreator pierwszego konta ---
        $setup = !auth_has_account(); // ktoś mógł zdążyć utworzyć konto
        if ($login === '') {
            $error = 'Podaj login.';
        } elseif (strlen($login) < 3) {
            $error = 'Login musi mieć co najmniej 3 znaki.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Podaj poprawny adres e-mail — posłuży do resetu hasła.';
        } elseif (strlen($haslo) < 6) {
            $error = 'Hasło musi mieć co najmniej 6 znaków.';
        } elseif ($haslo !== $haslo2) {
            $error = 'Hasła nie są identyczne.';
        } elseif (!auth_create_account($login, $haslo, $email)) {
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

// Blokada logowania po 5 nieudanych próbach — działa też po odświeżeniu strony.
$blokada = $setup ? 0 : auth_login_lock_remaining();

// Informacja po skutecznym resecie hasła (przekierowanie z reset-hasla.php).
if (($_GET['zresetowano'] ?? '') === '1') {
    $info = 'Hasło zostało zmienione. Zaloguj się nowym hasłem.';
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
                    ? 'Utwórz konto administratora — będzie zarządzać uczniami.'
                    : 'Zaloguj się, aby kontynuować.' ?></p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <p class="alert alert-danger">⚠ <?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($info !== ''): ?>
            <p class="alert alert-ok">✓ <?= e($info) ?></p>
        <?php endif; ?>

        <?php if ($blokada > 0): ?>
            <p class="alert alert-danger auth-lock" id="auth-lock" data-until="<?= time() + $blokada ?>">
                ⏳ Zbyt wiele nieudanych prób. Odczekaj <b id="auth-lock-count"><?= (int) $blokada ?></b> s.
            </p>
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
                       autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" required<?= $blokada > 0 ? ' disabled' : '' ?>>
            </div>

            <?php if ($setup): ?>
                <div class="field">
                    <label for="a-haslo2">Powtórz hasło</label>
                    <input id="a-haslo2" type="password" name="haslo2" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="a-email">E-mail (do resetu hasła)</label>
                    <input id="a-email" type="email" name="email" autocomplete="email"
                           placeholder="np. jan.kowalski@example.com" required>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-save auth-submit"<?= $blokada > 0 ? ' disabled' : '' ?>>
                <?= $setup ? '✅ Utwórz konto i zaloguj się' : '🔓 Zaloguj się' ?>
            </button>
        </form>

        <?php if (!$setup): ?>
            <p class="auth-links">
                <a href="reset-hasla.php">Nie pamiętam hasła</a>
            </p>
        <?php endif; ?>
    </div>

    <p class="auth-foot">Dziennik Praktyk · dostęp do danych po zalogowaniu</p>
</div>

<script>
(function () {
    // Odliczanie blokady logowania po 5 nieudanych próbach.
    var info = document.getElementById('auth-lock');
    if (!info) return;

    var until  = parseInt(info.getAttribute('data-until'), 10) || 0;
    var count  = document.getElementById('auth-lock-count');
    var pass   = document.getElementById('a-haslo');
    var submit = document.querySelector('.auth-submit');

    function left() {
        return until - Math.floor(Date.now() / 1000);
    }

    function unlock() {
        info.style.display = 'none';
        if (pass) { pass.disabled = false; pass.focus(); }
        if (submit) { submit.disabled = false; }
    }

    function tick() {
        var s = left();
        if (s <= 0) { unlock(); return; }
        if (count) { count.textContent = String(s); }
        window.setTimeout(tick, 250);
    }

    if (left() <= 0) { unlock(); } else { tick(); }
})();
</script>
</body>
</html>
