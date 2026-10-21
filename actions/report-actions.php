<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

// Endpoint aksi laporan (ekspor data / pencetakan lanjutan)
$user = require_login();
require_role(['super_admin', 'admin']);

flash('info', 'Laporan dapat dicetak langsung melalui tombol cetak di peramban (browser).');
redirect('admin/reports/index.php');
