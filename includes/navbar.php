<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
?>
<header class="topbar">
    <div class="mobile-brand">
        <img class="brand-logo" src="<?= e(base_url('assets/images/logo.jpg')) ?>" alt="DR.SPORT"> DR.SPORT
    </div>
    <div class="topbar-meta">
        <span class="today-label"><?= e(indonesian_date('l, d F Y')) ?></span>
        <a class="profile-link" href="<?= e(url('profile.php')) ?>">Profil & keamanan</a>
    </div>
</header>
