<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

$currentUser = $user ?? current_user() ?? [
    'name' => 'Pengguna',
    'role' => 'karyawan',
];
$role = $currentUser['role'];
$roleLabels = [
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'karyawan' => 'Karyawan',
];

$active = $activePage ?? '';

$pendingCount = in_array($role, ['super_admin', 'admin'], true) ? count_pending_requests() : 0;

// Definisi menu navigasi per peran
$navItems = [
    // Super Admin
    ['id' => 'superadmin_dashboard', 'label' => 'Dashboard', 'icon' => '◫', 'url' => url('superadmin/dashboard.php'), 'roles' => ['super_admin']],
    ['id' => 'superadmin_requests', 'label' => 'Pengajuan Bahan', 'icon' => '✉', 'url' => url('admin/requests/index.php'), 'roles' => ['super_admin'], 'badge' => $pendingCount],
    ['id' => 'superadmin_orders', 'label' => 'Pesanan', 'icon' => '▤', 'url' => url('superadmin/orders/index.php'), 'roles' => ['super_admin']],
    ['id' => 'superadmin_inventory', 'label' => 'Inventory', 'icon' => '▦', 'url' => url('superadmin/inventory/index.php'), 'roles' => ['super_admin']],
    ['id' => 'superadmin_storage', 'label' => 'Rak Gudang', 'icon' => '◧', 'url' => url('admin/inventory/storage-locations.php'), 'roles' => ['super_admin']],
    ['id' => 'superadmin_history', 'label' => 'Riwayat Stok', 'icon' => '↕', 'url' => url('superadmin/inventory/history.php'), 'roles' => ['super_admin']],
    ['id' => 'superadmin_reports', 'label' => 'Laporan', 'icon' => '▥', 'url' => url('superadmin/reports/index.php'), 'roles' => ['super_admin']],
    ['id' => 'superadmin_users', 'label' => 'Pengguna', 'icon' => '♙', 'url' => url('superadmin/users/index.php'), 'roles' => ['super_admin']],
    ['id' => 'superadmin_logs', 'label' => 'Log Aktivitas', 'icon' => '≡', 'url' => url('superadmin/activity-logs/index.php'), 'roles' => ['super_admin']],

    // Admin
    ['id' => 'admin_dashboard', 'label' => 'Dashboard', 'icon' => '◫', 'url' => url('admin/dashboard.php'), 'roles' => ['admin']],
    ['id' => 'admin_requests', 'label' => 'Pengajuan Bahan', 'icon' => '✉', 'url' => url('admin/requests/index.php'), 'roles' => ['admin'], 'badge' => $pendingCount],
    ['id' => 'admin_orders', 'label' => 'Pesanan', 'icon' => '▤', 'url' => url('admin/orders/index.php'), 'roles' => ['admin']],
    ['id' => 'admin_inventory', 'label' => 'Inventory', 'icon' => '▦', 'url' => url('admin/inventory/index.php'), 'roles' => ['admin']],
    ['id' => 'admin_storage', 'label' => 'Rak Gudang', 'icon' => '◧', 'url' => url('admin/inventory/storage-locations.php'), 'roles' => ['admin']],
    ['id' => 'admin_history', 'label' => 'Riwayat Stok', 'icon' => '↕', 'url' => url('admin/inventory/history.php'), 'roles' => ['admin']],
    ['id' => 'admin_reports', 'label' => 'Laporan', 'icon' => '▥', 'url' => url('admin/reports/index.php'), 'roles' => ['admin']],

    // Karyawan
    ['id' => 'karyawan_dashboard', 'label' => 'Dashboard', 'icon' => '◫', 'url' => url('karyawan/dashboard.php'), 'roles' => ['karyawan']],
    ['id' => 'karyawan_availability', 'label' => 'Cek Ketersediaan', 'icon' => '⌕', 'url' => url('karyawan/availability.php'), 'roles' => ['karyawan']],
    ['id' => 'karyawan_use_stock', 'label' => 'Ajukan Bahan', 'icon' => '＋', 'url' => url('karyawan/use-stock.php'), 'roles' => ['karyawan']],
    ['id' => 'karyawan_usage_history', 'label' => 'Riwayat Pengajuan', 'icon' => '↕', 'url' => url('karyawan/usage-history.php'), 'roles' => ['karyawan']],
    ['id' => 'karyawan_orders', 'label' => 'Status Pesanan', 'icon' => '▤', 'url' => url('karyawan/orders.php'), 'roles' => ['karyawan']],
];

$dashboardUrl = url('dashboard');
?>
<aside class="sidebar">
    <a class="brand" href="<?= e($dashboardUrl) ?>">
        <img class="brand-logo" src="<?= e(base_url('assets/images/logo.jpg')) ?>" alt="DR.SPORT">
        <span>
            <strong>DR.SPORT</strong>
            <small>OPERATIONS CONSOLE</small>
        </span>
    </a>
    <div class="workspace-label">WORKSPACE</div>
    <nav>
        <?php foreach ($navItems as $item): ?>
            <?php if (in_array($role, $item['roles'], true)): ?>
                <?php $isActive = ($active === $item['id']) ? ' active' : ''; ?>
                <a class="nav-link<?= $isActive ?>" href="<?= e($item['url']) ?>">
                    <span class="nav-icon"><?= e($item['icon']) ?></span>
                    <span><?= e($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="badge badge-warning" style="margin-left:auto; font-size:10px; padding:2px 7px; border-radius:10px;"><?= (int) $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <form method="post" action="<?= e(base_url('actions/user-actions.php')) ?>" class="sidebar-logout-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="sidebar-logout-btn" title="Logout dari sistem">
                <span class="logout-icon" aria-hidden="true">↪</span>
                <span class="logout-text">Logout</span>
            </button>
        </form>
        <div class="side-user">
            <span class="avatar"><?= e(strtoupper(substr($currentUser['name'], 0, 1))) ?></span>
            <span>
                <strong><?= e($currentUser['name']) ?></strong>
                <small><?= e($roleLabels[$role] ?? $role) ?></small>
            </span>
        </div>
    </div>
</aside>
