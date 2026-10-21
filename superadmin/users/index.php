<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['super_admin']);
$pageTitle = 'Manajemen Pengguna';
$activePage = 'superadmin_users';

$users = db()->query("SELECT id,name,username,email,role,status,last_login_at,created_at FROM users WHERE role IN ('admin','karyawan') ORDER BY created_at DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="toolbar">
    <div class="muted">Kelola akses Admin dan Karyawan. Akun Super Admin tidak dapat dinonaktifkan di sini.</div>
    <a class="button primary" href="<?= e(url('superadmin/users/form.php')) ?>">
        <span>＋</span> Tambah pengguna
    </a>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username / email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Login terakhir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr>
                        <td colspan="6"><div class="empty-state"><strong>Belum ada akun operasional</strong></div></td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <span class="avatar pale"><?= e(strtoupper(substr($u['name'], 0, 1))) ?></span>
                                <strong><?= e($u['name']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <?= e($u['username']) ?>
                            <small class="block muted"><?= e($u['email'] ?: 'Email belum diisi') ?></small>
                        </td>
                        <td><?= ($u['role'] === 'admin' ? 'Admin' : 'Karyawan') ?></td>
                        <td><?= status_badge($u['status']) ?></td>
                        <td><?= e($u['last_login_at'] ? date('d M Y, H:i', strtotime($u['last_login_at'])) : 'Belum pernah') ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="button small secondary" href="<?= e(url('superadmin/users/form.php', ['id' => $u['id']])) ?>">Edit</a>
                                <form method="post" action="<?= e(base_url('actions/user-actions.php')) ?>" class="inline-form confirm-form" data-confirm="Ubah status akun ini?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_user">
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <button class="button small secondary"><?= ($u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan') ?></button>
                                </form>
                                <form method="post" action="<?= e(base_url('actions/user-actions.php')) ?>" class="inline-form confirm-form" data-confirm="Reset password pengguna ini?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reset_user_password">
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <button class="button small subtle">Reset password</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
require_once __DIR__ . '/../../includes/footer.php';
