<?php
/**
 * Dziennik Praktyk — pobieranie / podgląd załącznika dnia.
 *
 * GET plik.php?id=N          → wyświetla obraz (inline) lub PDF, resztę pobiera
 * Pliki leżą w katalogu danych (poza docroot), więc nie da się ich pobrać
 * z pominięciem logowania. Nagłówki wymuszają pobranie dla niebezpiecznych
 * typów (HTML, SVG itp.), żeby nie uruchomić skryptu w kontekście aplikacji.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/auth.php';

auth_require_login();

$id  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = $id > 0 ? zal_get($id) : null;

if ($row === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nie znaleziono pliku.';
    exit;
}

// Ochrona przed wyjściem poza katalog załączników (nazwa pochodzi z bazy).
$plik = basename((string) $row['plik']);
$cel  = zal_dir() . DIRECTORY_SEPARATOR . $plik;

if ($plik === '' || !is_file($cel)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Plik nie istnieje na serwerze.';
    exit;
}

$typ    = (string) $row['typ'];
$nazwa  = (string) ($row['nazwa'] !== '' ? $row['nazwa'] : $plik);
$inline = zal_is_inline($typ);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=604800');
header('Content-Length: ' . (string) filesize($cel));

$disp = $inline ? 'inline' : 'attachment';
header('Content-Type: ' . ($inline ? strtolower($typ) : 'application/octet-stream'));
header(
    'Content-Disposition: ' . $disp
    . '; filename="' . zal_disposition_name($nazwa) . '"'
    . "; filename*=UTF-8''" . zal_disposition_name($nazwa)
);

readfile($cel);
exit;
