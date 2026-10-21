<?php
declare(strict_types=1);

/**
 * DR.SPORT — Internal Operations & Inventory System
 * Entry point & router.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = current_user();

// Jika pengguna belum masuk, alihkan ke halaman login
if (!$user) {
    redirect('login.php');
}

// Tangani parameter query ?page=... untuk backward-compatibility dengan tautan lama
$legacyPage = isset($_GET['page']) ? trim((string) $_GET['page']) : '';
if ($legacyPage !== '') {
    $query = $_GET;
    unset($query['page']);
    redirect($legacyPage, $query);
}

// Alihkan pengguna ke dashboard sesuai perannya masing-masing
switch ($user['role']) {
    case 'super_admin':
        redirect('superadmin/dashboard.php');
        break;
    case 'admin':
        redirect('admin/dashboard.php');
        break;
    case 'karyawan':
        redirect('karyawan/dashboard.php');
        break;
    default:
        redirect('login.php');
        break;
}
