<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['super_admin']);
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT o.*, c.name AS customer_name, c.phone, c.address, u.name AS creator FROM orders o JOIN customers c ON c.id=o.customer_id JOIN users u ON u.id=o.created_by WHERE o.id=?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    render_error(404, 'Pesanan tidak ditemukan.');
    return;
}

$pageTitle = 'Detail Pesanan ' . $order['order_code'];
$activePage = 'superadmin_orders';

require_once __DIR__ . '/../../includes/header.php';
?>
<div style="margin-bottom:18px;">
    <a class="button small secondary" href="<?= e(url('superadmin/orders/index.php')) ?>">← Kembali ke Monitoring Pesanan</a>
</div>

<div class="order-detail-grid">
    <section class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow"><?= e($order['order_code']) ?></p>
                <h2><?= e($order['customer_name']) ?></h2>
            </div>
            <?= status_badge($order['status']) ?>
        </div>
        <div class="detail-facts">
            <div>
                <small>Kontak</small>
                <strong><?= e($order['phone']) ?></strong>
            </div>
            <div>
                <small>Tanggal pesanan</small>
                <strong><?= e(date('d M Y', strtotime($order['order_date']))) ?></strong>
            </div>
            <div>
                <small>Target selesai</small>
                <strong><?= e($order['target_date'] ? date('d M Y', strtotime($order['target_date'])) : 'Belum ditentukan') ?></strong>
            </div>
            <div>
                <small>Jumlah</small>
                <strong><?= number_format((int) $order['quantity']) ?> jersey</strong>
            </div>
            <div class="wide">
                <small>Jenis / kebutuhan</small>
                <p><?= nl2br(e($order['jersey_description'])) ?></p>
            </div>
            <div class="wide">
                <small>Catatan internal</small>
                <p><?= nl2br(e($order['notes'] ?: 'Tidak ada catatan.')) ?></p>
            </div>
            <div>
                <small>Dibuat oleh</small>
                <strong><?= e($order['creator']) ?></strong>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Timeline status</h2>
                <p>Riwayat perubahan pengerjaan</p>
            </div>
        </div>
        <?php
        $stmtHistory = db()->prepare('SELECT h.*, u.name AS actor FROM order_status_history h JOIN users u ON u.id=h.changed_by WHERE order_id=? ORDER BY changed_at DESC');
        $stmtHistory->execute([$order['id']]);
        $historyEvents = $stmtHistory->fetchAll();
        ?>
        <div class="timeline">
            <?php foreach ($historyEvents as $event): ?>
                <div class="timeline-item">
                    <span class="timeline-dot"></span>
                    <div>
                        <strong><?= e($event['new_status']) ?></strong>
                        <small><?= e(date('d M Y, H:i', strtotime($event['changed_at']))) ?> · <?= e($event['actor']) ?></small>
                        <?php if ($event['note']): ?>
                            <p><?= e($event['note']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel span-two">
        <div class="panel-heading">
            <div>
                <h2>Pemakaian bahan</h2>
                <p>Mutasi inventory yang dikaitkan ke pesanan ini</p>
            </div>
        </div>
        <?php
        $stmtCons = db()->prepare('SELECT t.*, m.name AS material_name, m.unit, u.name AS actor FROM inventory_transactions t JOIN materials m ON m.id=t.material_id JOIN users u ON u.id=t.created_by WHERE t.order_id=? ORDER BY t.created_at DESC');
        $stmtCons->execute([$order['id']]);
        $consumption = $stmtCons->fetchAll();
        ?>
        <?php if (!$consumption): ?>
            <div class="empty-state compact">
                <strong>Belum ada pemakaian bahan</strong>
                <p>Stok keluar terkait pesanan akan muncul di sini saat dicatat pada menu Inventory.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th>Jumlah</th>
                            <th>Alasan</th>
                            <th>Dicatat Oleh</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consumption as $item): ?>
                            <tr>
                                <td><strong><?= e($item['material_name']) ?></strong></td>
                                <td class="text-danger">−<?= e(format_stock($item['quantity'])) ?> <?= e($item['unit']) ?></td>
                                <td><?= e($item['reason']) ?></td>
                                <td><?= e($item['actor']) ?></td>
                                <td><?= e(date('d M Y, H:i', strtotime($item['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
