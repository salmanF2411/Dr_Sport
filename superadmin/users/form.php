<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['super_admin']);
$id = (int) ($_GET['id'] ?? 0);
$account = [
    'id' => 0,
    'name' => '',
    'username' => '',
    'email' => '',
    'role' => 'admin',
    'status' => 'active',
];

if ($id) {
    $stmt = db()->prepare("SELECT id, name, username, email, role, status FROM users WHERE id = ? AND role IN ('admin','karyawan')");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        render_error(404, 'Akun tidak ditemukan.');
        return;
    }
    $account = $found;
}

$pageTitle = $id ? 'Edit Pengguna' : 'Tambah Pengguna';
$activePage = 'superadmin_users';

require_once __DIR__ . '/../../includes/header.php';
?>
<form method="post" action="<?= e(base_url('actions/user-actions.php')) ?>" class="form-layout">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_user">
    <input type="hidden" name="id" value="<?= e($id) ?>">

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h2><?= ($id ? 'Edit akun' : 'Akun baru') ?></h2>
                <p>Role hanya dapat diubah oleh Super Admin.</p>
            </div>
        </div>
        <div class="form-grid">
            <label class="field">
                <span>Nama <b>*</b></span>
                <input name="name" required maxlength="120" value="<?= e($account['name']) ?>" placeholder="Nama lengkap">
            </label>
            <label class="field">
                <span>Username <b>*</b></span>
                <input name="username" required minlength="3" maxlength="40" pattern="[A-Za-z0-9._]+" value="<?= e($account['username']) ?>" placeholder="username_pengguna">
            </label>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" maxlength="190" value="<?= e($account['email']) ?>" placeholder="email@contoh.com">
            </label>
            <label class="field">
                <span>Role <b>*</b></span>
                <select name="role">
                    <option value="admin"<?= ($account['role'] === 'admin' ? ' selected' : '') ?>>Admin</option>
                    <option value="karyawan"<?= ($account['role'] === 'karyawan' ? ' selected' : '') ?>>Karyawan</option>
                </select>
            </label>
            <?php if (!$id): ?>
                <label class="field">
                    <span>Password awal <b>*</b></span>
                    <input type="password" name="password" required minlength="10" maxlength="72" autocomplete="new-password" placeholder="Minimal 10 karakter">
                    <small>10–72 karakter, berisi huruf dan angka.</small>
                </label>
            <?php else: ?>
                <label class="check-filter">
                    <input type="checkbox" name="active" value="1"<?= ($account['status'] === 'active' ? ' checked' : '') ?>> Akun aktif
                </label>
            <?php endif; ?>
        </div>
    </section>

    <div class="form-actions">
        <a class="button secondary" href="<?= e(url('superadmin/users/index.php')) ?>">Batal</a>
        <button class="button primary">Simpan akun</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../../includes/footer.php';
