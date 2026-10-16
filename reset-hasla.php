<?php
/**
 * Dziennik Praktyk — reset hasła kodem wysyłanym e-mailem.
 *
 * Krok 1: podajesz login lub adres e-mail konta → wysyłamy 6-cyfrowy kod.
 * Krok 2: podajesz kod + nowe hasło.
 *
 * Kod ważny 10 minut, max 5 błędnych prób, min. 30 s odstępu między wysyłkami.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/mail.php';

auth_session_start();

if (!auth_has_account()) {
    header('Location: login.php?setup=1');
    exit;
}

// Zalogowany użytkownik zmienia hasło w aplikacji — tu nie ma czego szukać.
if (auth_is_logged_in()) {
    header('Location: ' . auth_home_url());
    exit;
}

$krok   = (($_GET['krok'] ?? '') === '2') ? 2 : 1;
$error  = '';
$info   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $akcja = (string) ($_POST['action'] ?? '');

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif ($akcja === 'wyslij') {
        // --- Krok 1: wysłanie kodu ---
        $podany = trim((string) ($_POST['podany'] ?? ''));
        $u      = user_by_login_or_email($podany);

        if ($u === null) {
            // Dane nie pasują do żadnego konta — nic nie wysyłamy i nie
            // zdradzamy, czy konto istnieje (brak komunikatu).
            $krok = 1;
        } elseif (trim((string) ($u['email'] ?? '')) === '') {
            $error = 'To konto nie ma ustawionego adresu e-mail. Zaloguj się i ustaw go w sekcji „Konto”, aby móc resetować hasło.';
        } elseif (!poczta_skonfigurowana()) {
            $error = 'Wysyłka poczty nie jest skonfigurowana. Uzupełnij plik poczta.json w katalogu danych (szczegóły w AGENTS.md).';
        } elseif (auth_reset_wait_for((int) $u['id']) > 0) {
            $error = 'Kod został już wysłany. Spróbuj ponownie za ' . auth_reset_wait_for((int) $u['id']) . ' s.';
        } elseif (($kod = auth_reset_start_for((int) $u['id'])) === null) {
            $error = 'Nie udało się wygenerować kodu. Spróbuj ponownie.';
        } else {
            $mail = trim((string) $u['email']);
            $tresc = "Dziennik Praktyk — reset hasła\n\n"
                . "Kod do zmiany hasła: " . $kod . "\n\n"
                . "Kod jest ważny " . (int) (auth_reset_ttl() / 60) . " minut.\n"
                . "Jeśli to nie Ty prosiłeś o reset — zignoruj tę wiadomość.\n";

            if (wyslij_mail($mail, 'Dziennik Praktyk — kod resetu hasła', $tresc)) {
                $krok = 2;
                $info = 'Kod został wysłany na adres: ' . $mail;
            } else {
                $error = 'Nie udało się wysłać e-maila. Sprawdź konfigurację poczty (plik poczta.json w katalogu danych).';
            }
        }
    } elseif ($akcja === 'zmien') {
        // --- Krok 2: weryfikacja kodu i zmiana hasła ---
        $krok = 2;
        $kod  = trim((string) ($_POST['kod'] ?? ''));
        $nowe = (string) ($_POST['nowe_haslo'] ?? '');
        $powt = (string) ($_POST['nowe_haslo2'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $kod)) {
            $error = 'Kod musi składać się z 6 cyfr.';
        } elseif (strlen($nowe) < 6) {
            $error = 'Nowe hasło musi mieć co najmniej 6 znaków.';
        } elseif ($nowe !== $powt) {
            $error = 'Hasła nie są identyczne.';
        } else {
            $status = auth_reset_confirm($kod, $nowe);

            if ($status === 'ok') {
                auth_login_reset(); // czyścimy też licznik blokady logowania
                header('Location: login.php?zresetowano=1');
                exit;
            }

            $error = match ($status) {
                'zly'    => 'Nieprawidłowy kod. Sprawdź go i spróbuj ponownie.',
                'wygasl' => 'Kod wygasł. Wyślij nowy kod.',
                'proby'  => 'Zbyt wiele błędnych prób. Wyślij nowy kod.',
                default  => 'Brak aktywnego resetu. Wyślij nowy kod.',
            };
        }
    }
}

$page_title = 'Reset hasła';
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
            <span class="brand-icon">🔑</span>
            <div>
                <h1 class="auth-title">Reset hasła</h1>
                <p class="brand-sub"><?= $krok === 2
                    ? 'Wpisz kod z wiadomości i ustaw nowe hasło.'
                    : 'Wyślemy kod na adres e-mail przypisany do konta.' ?></p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <p class="alert alert-danger">⚠ <?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($info !== ''): ?>
            <p class="alert alert-ok">✉ <?= e($info) ?></p>
        <?php endif; ?>

        <?php if ($krok === 2): ?>
            <form method="post" action="reset-hasla.php?krok=2" class="auth-form" autocomplete="on">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="zmien">

                <div class="field">
                    <label for="r-kod">Kod z e-maila</label>
                    <input id="r-kod" type="text" name="kod" inputmode="numeric" pattern="[0-9]{6}"
                           maxlength="6" placeholder="000000" autocomplete="one-time-code" autofocus required>
                </div>

                <div class="field">
                    <label for="r-nowe">Nowe hasło</label>
                    <input id="r-nowe" type="password" name="nowe_haslo" minlength="6"
                           autocomplete="new-password" required>
                </div>

                <div class="field">
                    <label for="r-nowe2">Powtórz nowe hasło</label>
                    <input id="r-nowe2" type="password" name="nowe_haslo2" minlength="6"
                           autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-save auth-submit">✅ Ustaw nowe hasło</button>
            </form>

            <p class="auth-links">
                <a href="reset-hasla.php">↻ Wyślij kod ponownie</a>
            </p>
        <?php else: ?>
            <form method="post" action="reset-hasla.php" class="auth-form" autocomplete="on">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="wyslij">

                <div class="field">
                    <label for="r-podany">Login lub e-mail konta</label>
                    <input id="r-podany" type="text" name="podany" autocomplete="username" autofocus required>
                </div>

                <button type="submit" class="btn btn-save auth-submit">✉ Wyślij kod</button>
            </form>
        <?php endif; ?>

        <p class="auth-links">
            <a href="login.php">← Powrót do logowania</a>
        </p>
    </div>

    <p class="auth-foot">Dziennik Praktyk · reset hasła kodem e-mail</p>
</div>
</body>
</html>
