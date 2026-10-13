<?php
/**
 * Dziennik Praktyk — wysyłka e-maili (np. kod resetu hasła).
 *
 * Konfiguracja (kolejność ważności):
 *   1. zmienne środowiskowe DZIENNIK_SMTP_* (patrz poczta_config()),
 *   2. plik JSON w katalogu danych: <DZIENNIK_DATA_DIR>/poczta.json,
 *   3. brak konfiguracji → używamy wbudowanej funkcji mail() PHP.
 *
 * Plik poczta.json (przykład):
 *   {
 *     "host":   "smtp.gmail.com",
 *     "port":   587,
 *     "user":   "twoj.adres@gmail.com",
 *     "pass":   "haslo-aplikacji",
 *     "secure": "tls",
 *     "from":   "twoj.adres@gmail.com",
 *     "debug":  false
 *   }
 *
 * Tryb "debug": true NIE wysyła maila — zapisuje go do pliku
 * <DZIENNIK_DATA_DIR>/poczta-log.txt. Przydatne lokalnie bez serwera SMTP.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Wczytuje konfigurację poczty (raz na żądanie). */
function poczta_config(): array
{
    static $cfg = null;
    if (is_array($cfg)) {
        return $cfg;
    }

    $cfg = [
        'host'    => '',
        'port'    => 587,
        'user'    => '',
        'pass'    => '',
        'secure'  => 'tls', // 'tls' (STARTTLS 587), 'ssl' (465) lub '' (brak)
        'from'    => '',
        'debug'   => false,
        'timeout' => 15,
    ];

    // Plik w katalogu danych (poza docroot — nie da się go pobrać przez HTTP).
    $plik = db_data_dir() . DIRECTORY_SEPARATOR . 'poczta.json';
    if (is_file($plik)) {
        $dane = json_decode((string) @file_get_contents($plik), true);
        if (is_array($dane)) {
            $cfg = array_merge($cfg, $dane);
        }
    }

    // Zmienne środowiskowe mają pierwszeństwo nad plikiem.
    $envMap = [
        'DZIENNIK_SMTP_HOST'   => 'host',
        'DZIENNIK_SMTP_USER'   => 'user',
        'DZIENNIK_SMTP_PASS'   => 'pass',
        'DZIENNIK_SMTP_SECURE' => 'secure',
        'DZIENNIK_SMTP_FROM'   => 'from',
    ];
    foreach ($envMap as $env => $klucz) {
        $v = getenv($env);
        if (is_string($v) && $v !== '') {
            $cfg[$klucz] = $v;
        }
    }
    $port = getenv('DZIENNIK_SMTP_PORT');
    if (is_string($port) && $port !== '') {
        $cfg['port'] = (int) $port;
    }

    return $cfg;
}

/** Czy poczta jest w ogóle skonfigurowana (SMTP albo tryb debug)? */
function poczta_skonfigurowana(): bool
{
    $cfg = poczta_config();

    return !empty($cfg['debug']) || (string) $cfg['host'] !== '';
}

/** Zapisuje wysyłkę do pliku dziennika (tryb debug). */
function poczta_log(string $do, string $temat, string $tresc): void
{
    $plik = db_data_dir() . DIRECTORY_SEPARATOR . 'poczta-log.txt';
    $wpis = '[' . date('Y-m-d H:i:s') . '] do: ' . $do . "\n"
        . 'Temat: ' . $temat . "\n"
        . $tresc . "\n"
        . str_repeat('-', 60) . "\n";
    @file_put_contents($plik, $wpis, FILE_APPEND | LOCK_EX);
}

/** Koduje temat maila (UTF-8) do postaci nagłówka MIME. */
function poczta_temat_mime(string $temat): string
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($temat, 'UTF-8', 'B');
    }

    return '=?UTF-8?B?' . base64_encode($temat) . '?=';
}

/**
 * Wysyła e-mail. Zwraca true, gdy udało się wysłać (albo zapisać w trybie debug).
 */
function wyslij_mail(string $do, string $temat, string $tresc): bool
{
    $cfg = poczta_config();
    $do  = trim($do);

    if ($do === '' || !filter_var($do, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (!empty($cfg['debug'])) {
        poczta_log($do, $temat, $tresc);

        return true;
    }

    $od = trim((string) $cfg['from']);
    if ($od === '' || !filter_var($od, FILTER_VALIDATE_EMAIL)) {
        $od = 'dziennik@' . (parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost');
    }

    if ((string) $cfg['host'] !== '') {
        return poczta_smtp($cfg, $od, $do, $temat, $tresc);
    }

    // Ostatnia deska ratunku: wbudowana mail() (wymaga serwera pocztowego w PHP).
    $naglowki = 'From: ' . $od . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: base64' . "\r\n";

    $cialo = chunk_split(base64_encode($tresc), 76, "\r\n");

    return @mail($do, poczta_temat_mime($temat), $cialo, $naglowki);
}

/** Wysyła przez SMTP (obsługa STARTTLS/SSL + AUTH LOGIN). */
function poczta_smtp(array $cfg, string $od, string $do, string $temat, string $tresc): bool
{
    $host    = (string) $cfg['host'];
    $port    = (int) $cfg['port'];
    $secure  = strtolower((string) $cfg['secure']);
    $user    = (string) $cfg['user'];
    $pass    = (string) $cfg['pass'];
    $timeout = max(5, (int) $cfg['timeout']);

    $prefix = $secure === 'ssl' ? 'ssl://' : '';
    $errno  = 0;
    $errstr = '';
    $fp     = @stream_socket_client(
        $prefix . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT
    );
    if (!is_resource($fp)) {
        return false;
    }
    stream_set_timeout($fp, $timeout);

    $czytaj = static function () use ($fp): array {
        $buf = '';
        while (($linia = fgets($fp, 1024)) !== false) {
            $buf .= $linia;
            if (strlen($linia) < 4 || $linia[3] === ' ') {
                break;
            }
        }

        return [(int) substr($buf, 0, 3), $buf];
    };
    $pisz = static function (string $tekst) use ($fp): void {
        fwrite($fp, $tekst . "\r\n");
    };
    $ehlo = static function () use ($pisz, $czytaj): bool {
        $pisz('EHLO ' . (gethostname() ?: 'localhost'));
        [$kod] = $czytaj();

        return $kod === 250;
    };

    [$kod] = $czytaj(); // powitanie serwera
    if ($kod !== 220) {
        fclose($fp);

        return false;
    }

    if (!$ehlo()) {
        fclose($fp);

        return false;
    }

    if ($secure === 'tls') {
        $pisz('STARTTLS');
        [$kod] = $czytaj();
        if ($kod !== 220
            || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            || !$ehlo()) {
            fclose($fp);

            return false;
        }
    }

    if ($user !== '') {
        $pisz('AUTH LOGIN');
        [$kod] = $czytaj();
        if ($kod !== 334) {
            fclose($fp);

            return false;
        }
        $pisz(base64_encode($user));
        [$kod] = $czytaj();
        if ($kod !== 334) {
            fclose($fp);

            return false;
        }
        $pisz(base64_encode($pass));
        [$kod] = $czytaj();
        if ($kod !== 235) {
            fclose($fp);

            return false;
        }
    }

    $pisz('MAIL FROM:<' . $od . '>');
    [$kod] = $czytaj();
    if ($kod !== 250) {
        fclose($fp);

        return false;
    }

    $pisz('RCPT TO:<' . $do . '>');
    [$kod] = $czytaj();
    if ($kod !== 250 && $kod !== 251) {
        fclose($fp);

        return false;
    }

    $pisz('DATA');
    [$kod] = $czytaj();
    if ($kod !== 354) {
        fclose($fp);

        return false;
    }

    $naglowki = 'From: ' . $od . "\r\n"
        . 'To: ' . $do . "\r\n"
        . 'Subject: ' . poczta_temat_mime($temat) . "\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: base64' . "\r\n";

    $cialo = chunk_split(base64_encode($tresc), 76, "\r\n");
    $dane  = preg_replace('/^\./m', '..', $naglowki . "\r\n" . $cialo);

    fwrite($fp, $dane . "\r\n.\r\n");
    [$kod] = $czytaj();
    if ($kod !== 250) {
        fclose($fp);

        return false;
    }

    $pisz('QUIT');
    fclose($fp);

    return true;
}
