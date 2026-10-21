<?php
declare(strict_types=1);

if (!is_file(__DIR__ . '/database.local.php')) {
    throw new RuntimeException('Konfigurasi database belum dibuat. Salin config/database.example.php menjadi config/database.local.php dan sesuaikan kredensialnya.');
}

/**
 * Singleton database connection helper.
 */
function db(): PDO
{
    static $pdo = null;
    if (!$pdo) {
        $config = require __DIR__ . '/database.local.php';
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['database']);
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
