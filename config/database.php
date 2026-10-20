<?php
declare(strict_types=1);

if (!is_file(__DIR__ . '/database.local.php')) {
    throw new RuntimeException('Konfigurasi database belum dibuat. Salin config/database.example.php menjadi config/database.local.php dan sesuaikan kredensialnya.');
}
