<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['admin']);
$pageTitle = 'Riwayat Stok & Ledger Mutasi';
$activePage = 'admin_history';

$materialId = (int) ($_GET['material_id'] ?? 0);
$start = (string) ($_GET['start'] ?? date('Y-m-01'));
$end = (string) ($_GET['end'] ?? date('Y-m-d'));

if (!valid_date($start)) {
    $start = date('Y-m-01');
}
if (!valid_date($end)) {
    $end = date('Y-m-d');
}
if ($end < $start) {
    $end = $start;
}

$where = ['DATE(t.created_at) BETWEEN ? AND ?'];
$params = [$start, $end];

if ($materialId > 0) {
    $where[] = 't.material_id = ?';
    $params[] = $materialId;
}

$stmt = db()->prepare('SELECT t.*, m.material_code, m.name AS material_name, m.unit, o.order_code, u.name AS actor FROM inventory_transactions t JOIN materials m ON m.id=t.material_id JOIN users u ON u.id=t.created_by LEFT JOIN orders o ON o.id=t.order_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.created_at DESC LIMIT 300');
$stmt->execute($params);
$transactions = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="toolbar">
    <form method="get" action="<?= e(url('admin/inventory/history.php')) ?>" class="filter-form">
        <?php if ($materialId > 0): ?>
            <input type="hidden" name="material_id" value="<?= e($materialId) ?>">
        <?php endif; ?>
        <label class="field-inline">
            <span>Dari</span>
            <input type="date" name="start" value="<?= e($start) ?>">
        </label>
        <label class="field-inline">
            <span>Sampai</span>
            <input type="date" name="end" value="<?= e($end) ?>">
        </label>
        <?php if ($materialId > 0): ?>
            <a href="<?= e(url('admin/inventory/history.php')) ?>" class="button small subtle">Reset filter bahan</a>
        <?php endif; ?>
    </form>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No. transaksi</th>
                    <th>Bahan</th>
                    <th>Jenis</th>
                    <th>Jumlah</th>
                    <th>Saldo sebelum → sesudah</th>
                    <th>Pesanan</th>
                    <th>Pencatat / waktu</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$transactions): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state"><strong>Belum ada transaksi pada periode ini</strong></div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($transactions as $row): ?>
                    <?php $incoming = transaction_is_in($row['transaction_type']); ?>
                    <tr>
                        <td>
                            <strong><?= e($row['transaction_code']) ?></strong>
                            <small class="block muted"><?= e($row['reason']) ?></small>
                        </td>
                        <td>
                            <a class="table-primary"
                                href="<?= e(url('admin/inventory/detail.php', ['id' => $row['material_id']])) ?>">
                                <?= e($row['material_name']) ?>
                            </a>
                            <small class="block muted"><?= e($row['material_code']) ?></small>
                        </td>
                        <td><?= e(transaction_label($row['transaction_type'])) ?></td>
                        <td class="<?= ($incoming ? 'text-success' : 'text-danger') ?>">
                            <?= ($incoming ? '+' : '−') . e(format_stock($row['quantity'])) ?>     <?= e($row['unit']) ?>
                        </td>
                        <td>
                            <?= e(format_stock($row['stock_before'])) ?> →
                            <strong><?= e(format_stock($row['stock_after'])) ?></strong>
                        </td>
                        <td><?= e($row['order_code'] ?: '—') ?></td>
                        <td>
                            <?= e($row['actor']) ?>
                            <small class="block muted"><?= e(date('d M Y, H:i', strtotime($row['created_at']))) ?></small>
                        </td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end;">
                                <a href="<?= e(url('admin/inventory/history-detail.php', ['id' => $row['id']])) ?>"
                                    class="button small secondary" data-open-mutation-modal
                                    data-trx-code="<?= e($row['transaction_code']) ?>"
                                    data-trx-type="<?= e($row['transaction_type']) ?>"
                                    data-trx-type-label="<?= e(transaction_label($row['transaction_type'])) ?>"
                                    data-trx-date="<?= e(indonesian_date('l, d F Y · H:i', strtotime($row['created_at']))) ?>"
                                    data-material-name="<?= e($row['material_name']) ?>"
                                    data-material-code="<?= e($row['material_code']) ?>"
                                    data-quantity="<?= ($incoming ? '+' : '−') . e(format_stock($row['quantity'])) . ' ' . e($row['unit']) ?>"
                                    data-quantity-class="<?= ($incoming ? 'in' : 'out') ?>"
                                    data-before="<?= e(format_stock($row['stock_before'])) . ' ' . e($row['unit']) ?>"
                                    data-after="<?= e(format_stock($row['stock_after'])) . ' ' . e($row['unit']) ?>"
                                    data-reason="<?= e($row['reason']) ?>"
                                    data-notes="<?= e($row['notes'] ?: 'Tidak ada catatan tambahan') ?>"
                                    data-order="<?= e($row['order_code'] ?: '— Tidak terkait pesanan —') ?>"
                                    data-actor="<?= e($row['actor']) ?>"
                                    data-detail-url="<?= e(url('admin/inventory/history-detail.php', ['id' => $row['id']])) ?>">Detail
                                    ↗</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-foot">Maksimal 300 transaksi per tampilan · transaksi tersimpan tidak dapat dihapus</div>
</section>

<?php
require_once __DIR__ . '/../../includes/footer.php';
