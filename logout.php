<?php
/**
 * Dziennik Praktyk — wylogowanie.
 *
 * Obsługuje POST (z tokenem CSRF) oraz zwykłe przejście linkiem (GET).
 */

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';

auth_session_start();
auth_logout();

header('Location: login.php');
exit;
