<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$pageTitle = 'Profil & Keamanan';
$activePage = 'profile';

require_once __DIR__ . '/includes/header.php';
?>
<div class="profile-layout">
    <section class="panel">
        <div class="profile-card">
            <span class="avatar large"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
            <div>
                <p class="eyebrow">PROFIL AKUN</p>
                <h2><?= e($user['name']) ?></h2>
                <p class="muted">@<?= e($user['username']) ?> · <?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></p>
            </div>
        </div>
        <div class="profile-tip">
            <strong>Keamanan akun</strong>
            <p><?= (!empty($user['must_change_password']) ? 'Password sementara harus diganti sebelum fitur lain dapat digunakan.' : 'Gunakan password unik dan jangan membagikan kredensial. Hubungi Super Admin bila perlu bantuan akses.') ?></p>
        </div>
    </section>

    <form method="post" action="<?= e(base_url('actions/user-actions.php')) ?>" class="panel form-panel">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="panel-heading">
            <div>
                <h2>Ganti password</h2>
                <p>Konfirmasi password saat ini untuk melanjutkan.</p>
            </div>
        </div>
        <label class="field">
            <span>Password saat ini <b>*</b></span>
            <input type="password" name="current_password" required maxlength="72" autocomplete="current-password">
        </label>
        <label class="field">
            <span>Password baru <b>*</b></span>
            <input type="password" name="new_password" required minlength="10" maxlength="72" autocomplete="new-password">
            <small>10–72 karakter, mengandung huruf dan angka.</small>
        </label>
        <button class="button primary">Perbarui password</button>
    </form>
</div>
<?php
require_once __DIR__ . '/includes/footer.php';
