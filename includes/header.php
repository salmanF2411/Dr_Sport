<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

$user = $user ?? require_login();

// Demi keamanan, wajib ganti password sementara sebelum mengakses fitur lain
if (!empty($user['must_change_password']) && basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    flash('error', 'Demi keamanan, ubah password sementara sebelum melanjutkan.');
    redirect('profile.php');
}

$title = $pageTitle ?? 'DR.SPORT';
$roleLabels = [
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'karyawan' => 'Karyawan',
];
$userRoleLabel = $roleLabels[$user['role']] ?? $user['role'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#172033">
    <title><?= e($title) ?> · DR.SPORT</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main-area">
        <?php require __DIR__ . '/navbar.php'; ?>
        <main class="content">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">DR.SPORT <span>/</span> <?= e($userRoleLabel) ?></p>
                    <h1><?= e($title) ?></h1>
                </div>
            </div>

            <?php if (!empty($_SESSION['flash'])): ?>
                <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                <div class="alert <?= $flash['type'] === 'error' ? 'alert-error' : 'alert-success' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['temporary_password'])): ?>
                <div class="alert alert-warning">
                    <strong>Password sementara:</strong>
                    <code><?= e($_SESSION['temporary_password']) ?></code>
                    <span>Salin dan berikan secara langsung kepada pengguna.</span>
                </div>
                <?php unset($_SESSION['temporary_password']); ?>
            <?php endif; ?>
