<?php
/**
 * Połączenie z bazą SQLite (PDO) + automatyczne tworzenie schematu.
 * Baza: data/dziennik.db
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dir . '/dziennik.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS wpisy (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            data       TEXT NOT NULL UNIQUE,
            godzina_od TEXT NOT NULL DEFAULT "",
            godzina_do TEXT NOT NULL DEFAULT "",
            opis       TEXT NOT NULL DEFAULT ""
        )'
    );

    return $pdo;
}
