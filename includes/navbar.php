<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
?>
<header class="topbar">
    <div class="mobile-brand">
        <span class="brand-mark">DS</span> DR.SPORT
    </div>
    <div class="topbar-meta">
        <span class="today-label"><?= e(indonesian_date('l, d F Y')) ?></span>
        <a class="profile-link" href="<?= e(url('profile.php')) ?>">Profil & keamanan</a>
        <form method="post" action="<?= e(base_url('actions/user-actions.php')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button class="logout-link">Keluar ↗</button>
        </form>
    </div>
</header>
