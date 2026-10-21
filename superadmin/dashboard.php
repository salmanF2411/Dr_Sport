<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['super_admin']);
$pageTitle = 'Dashboard Super Admin';
$activePage = 'superadmin_dashboard';

$pdo = db();
$activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$staffUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','karyawan')")->fetchColumn();
$orderCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Selesai','Dibatalkan')")->fetchColumn();
$lowCount = (int) $pdo->query('SELECT COUNT(*) FROM materials WHERE is_active = 1 AND current_stock <= minimum_stock')->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="stat-grid">
    <?php
    stat_card('Akun aktif', (string) $activeUsers, 'Pengguna dapat masuk');
    stat_card('Admin & karyawan', (string) $staffUsers, 'Akun operasional');
    stat_card('Pesanan berjalan', (string) $orderCount, 'Belum selesai');
    stat_card('Stok perlu perhatian', (string) $lowCount, 'Bahan menyentuh minimum', 'warning');
    ?>
</div>

<?php
$counts = $pdo->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<section class="status-summary">
    <div class="status-summary-heading">Pesanan menurut status</div>
    <div class="status-summary-items">
        <?php foreach (['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'] as $orderStatus): ?>
            <div>
                <span><?= e($orderStatus) ?></span>
                <strong><?= e($counts[$orderStatus] ?? 0) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Pesanan terbaru</h2>
                <p>Pantau status pengerjaan terkini</p>
            </div>
            <a class="text-link" href="<?= e(url('superadmin/orders/index.php')) ?>">Semua pesanan →</a>
        </div>
        <?php
        $orders = $pdo->query('SELECT o.id,o.order_code,o.order_date,o.quantity,o.status,c.name customer_name FROM orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.created_at DESC LIMIT 6')->fetchAll();
        if (!$orders):
        ?>
            <div class="empty-state"><span class="empty-icon">▤</span><strong>Belum ada pesanan</strong><p>Pesanan yang dibuat akan muncul di sini.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode pesanan</th>
                            <th>Pelanggan</th>
                            <th>Tanggal</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><a class="table-primary" href="<?= e(url('superadmin/orders/detail.php', ['id' => $order['id']])) ?>"><?= e($order['order_code']) ?></a></td>
                                <td><?= e($order['customer_name']) ?></td>
                                <td><?= e(date('d M Y', strtotime($order['order_date']))) ?></td>
                                <td><?= number_format((int) $order['quantity']) ?> pcs</td>
                                <td><?= status_badge($order['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Stok perlu perhatian</h2>
                <p>Saldo di bawah atau sama dengan minimum</p>
            </div>
            <a class="text-link" href="<?= e(url('superadmin/inventory/index.php')) ?>">Inventory →</a>
        </div>
        <?php
        $lowMaterials = $pdo->query('SELECT id,material_code,name,category,unit,current_stock,minimum_stock,image_path FROM materials WHERE is_active=1 AND current_stock<=minimum_stock ORDER BY current_stock ASC LIMIT 6')->fetchAll();
        if (!$lowMaterials):
        ?>
            <div class="empty-state compact"><span class="empty-check">✓</span><strong>Semua stok aman</strong><p>Tidak ada bahan pada batas minimum.</p></div>
        <?php else: ?>
            <div class="low-list">
                <?php foreach ($lowMaterials as $mat): ?>
                    <a class="low-item" href="<?= e(url('superadmin/inventory/detail.php', ['id' => $mat['id']])) ?>">
                        <img src="<?= e(material_image($mat)) ?>" alt="">
                        <span>
                            <strong><?= e($mat['name']) ?></strong>
                            <small><?= e($mat['material_code']) ?> · <?= e(category_label($mat['category'])) ?></small>
                        </span>
                        <span class="low-stock"><?= e(format_stock($mat['current_stock'])) ?> <?= e($mat['unit']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
$activities = $pdo->query('SELECT l.action,l.description,l.created_at,u.name actor FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 6')->fetchAll();
?>
<section class="panel activity-panel">
    <div class="panel-heading">
        <div>
            <h2>Aktivitas terbaru</h2>
            <p>Perubahan penting yang tercatat di sistem</p>
        </div>
        <a class="text-link" href="<?= e(url('superadmin/activity-logs/index.php')) ?>">Semua aktivitas →</a>
    </div>
    <?php if (!$activities): ?>
        <div class="empty-state compact"><strong>Belum ada aktivitas</strong></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Aktivitas</th>
                        <th>Ringkasan</th>
                        <th>Pengguna</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $act): ?>
                        <tr>
                            <td><span class="table-primary"><?= e($act['action']) ?></span></td>
                            <td><?= e($act['description']) ?></td>
                            <td><?= e($act['actor'] ?: 'Tidak dikenal') ?></td>
                            <td><?= e(indonesian_date('d M Y, H:i', strtotime($act['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
