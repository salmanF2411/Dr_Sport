<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

// Jika sudah login, langsung alihkan ke dashboard masing-masing
if (current_user()) {
    redirect('dashboard');
}

$cssUrl = base_url('assets/css/app.css');
$brandUrl = base_url('login.php');
$actionUrl = base_url('actions/user-actions.php');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#172033">
    <title>Masuk · DR.SPORT</title>
    <link rel="stylesheet" href="<?= e($cssUrl) ?>">
</head>
<body class="login-page">
    <div class="login-decoration"></div>
    <main class="login-wrap">
        <a class="brand login-brand" href="<?= e($brandUrl) ?>">
            <span class="brand-mark">DS</span>
            <span>
                <strong>DR.SPORT</strong>
                <small>OPERATIONS CONSOLE</small>
            </span>
        </a>
        <section class="login-card">
            <p class="eyebrow">SELAMAT DATANG KEMBALI</p>
            <h1>Masuk ke akun</h1>
            <p class="muted">Kelola pesanan dan inventory dengan lebih terarah.</p>

            <?php if (!empty($_SESSION['flash'])): ?>
                <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                <div class="alert alert-error">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e($actionUrl) ?>" class="login-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <label class="field">
                    <span>Username atau email</span>
                    <input name="username" required autocomplete="username" autofocus placeholder="Masukkan username">
                </label>
                <label class="field">
                    <span>Password</span>
                    <div class="password-wrap">
                        <input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password">
                        <button type="button" class="password-toggle" data-toggle-password="login-password" aria-label="Tampilkan password">Lihat</button>
                    </div>
                </label>
                <button class="button primary login-submit">Masuk ke dashboard <span>→</span></button>
            </form>
            <div class="login-note">
                <span class="lock-icon">⌑</span> Akses internal · Akun diberikan oleh Super Admin
            </div>
        </section>
        <footer class="login-footer">
            © <?= date('Y') ?> DR.SPORT · Sistem informasi operasional
        </footer>
    </main>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
