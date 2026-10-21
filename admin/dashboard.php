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
$completedOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Selesai'")->fetchColumn();
$lowCount = (int) $pdo->query('SELECT COUNT(*) FROM materials WHERE is_active = 1 AND current_stock <= minimum_stock')->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="stat-grid">
    <?php
    stat_card('Pesanan masuk', (string) $newOrders, 'Menunggu proses');
    stat_card('Dalam proses', (string) $inProgressOrders, 'Sedang dikerjakan');
    stat_card('Pesanan selesai', (string) $completedOrders, 'Seluruh periode');
    stat_card('Stok menipis', (string) $lowCount, 'Bahan menyentuh minimum', 'warning');
    ?>
</div>

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
