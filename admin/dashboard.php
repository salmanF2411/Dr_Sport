<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['admin']);
$pageTitle = 'Dashboard Operasional Admin';
$activePage = 'admin_dashboard';

$pdo = db();
$newOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pesanan Masuk'")->fetchColumn();
$inProgressOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Pesanan Masuk','Selesai','Dibatalkan')")->fetchColumn();
$pendingRequests = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status='pending'")->fetchColumn();
$lowCount = (int) $pdo->query('SELECT COUNT(*) FROM materials WHERE is_active = 1 AND current_stock <= minimum_stock')->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="stat-grid">
    <?php
    stat_card('Pesanan masuk', (string) $newOrders, 'Menunggu proses');
    stat_card('Dalam proses', (string) $inProgressOrders, 'Sedang dikerjakan');
    stat_card('Pengajuan bahan', (string) $pendingRequests, 'Menunggu konfirmasi admin', $pendingRequests > 0 ? 'warning' : '');
    stat_card('Stok menipis', (string) $lowCount, 'Bahan menyentuh minimum', 'warning');
    ?>
</div>

<?php if ($pendingRequests > 0): ?>
    <?php
    $pendingItems = $pdo->query("
        SELECT r.*, m.name AS material_name, m.material_code, m.unit, m.current_stock, u.name AS requester_name
        FROM material_requests r
        JOIN materials m ON m.id = r.material_id
        JOIN users u ON u.id = r.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC
        LIMIT 5
    ")->fetchAll();
    ?>
    <section class="panel" style="margin-bottom: 19px; border-left: 4px solid var(--warning);">
        <div class="panel-heading">
            <div>
                <h2 style="color: var(--warning);">✉ Pengajuan Bahan Menunggu Konfirmasi (<?= $pendingRequests ?>)</h2>
                <p>Karyawan mengajukan kebutuhan bahan dan menunggu persetujuan Anda agar stok dapat dipotong.</p>
            </div>
            <a class="text-link" href="<?= e(url('admin/requests/index.php', ['status' => 'pending'])) ?>">Buka semua pengajuan →</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No. Pengajuan</th>
                        <th>Pemohon</th>
                        <th>Bahan</th>
                        <th>Jumlah Diminta</th>
                        <th>Saldo Tersedia</th>
                        <th>Keperluan</th>
                        <th class="text-right">Aksi Cepat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingItems as $pi): ?>
                        <?php $enough = ((float)$pi['current_stock'] >= (float)$pi['quantity']); ?>
                        <tr>
                            <td><strong><?= e($pi['request_code']) ?></strong></td>
                            <td><?= e($pi['requester_name']) ?></td>
                            <td><strong><?= e($pi['material_name']) ?></strong> <small class="muted">(<?= e($pi['material_code']) ?>)</small></td>
                            <td><strong style="color:#1e293b;"><?= e(format_stock($pi['quantity'])) ?></strong> <?= e($pi['unit']) ?></td>
                            <td>
                                <strong><?= e(format_stock($pi['current_stock'])) ?></strong> <?= e($pi['unit']) ?>
                                <?php if (!$enough): ?>
                                    <small class="block text-danger">⚠ Stok kurang</small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($pi['reason']) ?></td>
                            <td>
                                <div class="row-actions" style="justify-content:flex-end;">
                                    <?php if ($enough): ?>
                                        <form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" class="inline-form" onsubmit="return confirm('Setujui pengajuan <?= e($pi['request_code']) ?> (<?= e($pi['material_name']) ?> <?= format_stock($pi['quantity']) ?> <?= e($pi['unit']) ?>)?\n\nStok akan langsung dikurangi.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="approve_material_request">
                                            <input type="hidden" name="request_id" value="<?= e($pi['id']) ?>">
                                            <input type="hidden" name="redirect_to" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                            <button type="submit" class="button small primary" title="Setujui dan potong stok">✓ Setujui</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= e(url('admin/requests/index.php')) ?>" class="button small secondary">Review ↗</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php
$categoryStocks = $pdo->query('SELECT category, COUNT(*) AS total, COALESCE(SUM(current_stock),0) AS stock FROM materials WHERE is_active=1 GROUP BY category')->fetchAll();
?>
<section class="category-summary">
    <?php foreach (['kain', 'tinta_printing', 'benang'] as $category): ?>
        <?php
        $categoryStock = ['total' => 0, 'stock' => 0];
        foreach ($categoryStocks as $summary) {
            if ($summary['category'] === $category) {
                $categoryStock = $summary;
                break;
            }
        }
        ?>
        <div>
            <span><?= e(category_label($category)) ?></span>
            <strong><?= e($categoryStock['total']) ?> bahan</strong>
            <small>Total saldo <?= e(format_stock($categoryStock['stock'])) ?></small>
        </div>
    <?php endforeach; ?>
</section>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Pesanan terbaru</h2>
                <p>Pantau status pengerjaan terkini</p>
            </div>
            <a class="text-link" href="<?= e(url('admin/orders/index.php')) ?>">Semua pesanan →</a>
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
                                <td><a class="table-primary" href="<?= e(url('admin/orders/detail.php', ['id' => $order['id']])) ?>"><?= e($order['order_code']) ?></a></td>
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
            <a class="text-link" href="<?= e(url('admin/inventory/index.php')) ?>">Inventory →</a>
        </div>
        <?php
        $lowMaterials = $pdo->query('SELECT id,material_code,name,category,unit,current_stock,minimum_stock,image_path FROM materials WHERE is_active=1 AND current_stock<=minimum_stock ORDER BY current_stock ASC LIMIT 6')->fetchAll();
        if (!$lowMaterials):
        ?>
            <div class="empty-state compact"><span class="empty-check">✓</span><strong>Semua stok aman</strong><p>Tidak ada bahan pada batas minimum.</p></div>
        <?php else: ?>
            <div class="low-list">
                <?php foreach ($lowMaterials as $mat): ?>
                    <a class="low-item" href="<?= e(url('admin/inventory/detail.php', ['id' => $mat['id']])) ?>">
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
require_once __DIR__ . '/../includes/footer.php';
