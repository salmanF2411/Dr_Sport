<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$pageTitle = 'Riwayat Pemakaian Bahan Saya';
$activePage = 'karyawan_usage_history';

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

$where = ['t.created_by = ?', "t.transaction_type = 'out'", 'DATE(t.created_at) BETWEEN ? AND ?'];
$params = [(int) $user['id'], $start, $end];
$materialId = (int) ($_GET['material_id'] ?? 0);

if ($materialId > 0) {
    $where[] = 't.material_id = ?';
    $params[] = $materialId;
}

$stmt = db()->prepare('SELECT t.*, m.material_code, m.name AS material_name, m.unit FROM inventory_transactions t JOIN materials m ON m.id=t.material_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.created_at DESC LIMIT 300');
$stmt->execute($params);
$transactions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="toolbar">
    <form method="get" action="<?= e(url('karyawan/usage-history.php')) ?>" class="filter-form">
        <?php if ($materialId > 0): ?>
            <input type="hidden" name="material_id" value="<?= e($materialId) ?>">
        <?php endif; ?>
        <label class="field-inline">
            <span>Dari</span>
            <input type="date" name="start" value="<?= e($start) ?>" onchange="if(!this.value||this.value.length===10)this.form.submit()">
        </label>
        <label class="field-inline">
            <span>Sampai</span>
            <input type="date" name="end" value="<?= e($end) ?>" onchange="if(!this.value||this.value.length===10)this.form.submit()">
        </label>
        <?php if ($materialId > 0): ?>
            <a href="<?= e(url('karyawan/usage-history.php')) ?>" class="button small subtle">Reset filter bahan</a>
        <?php endif; ?>
        <a class="button primary" href="<?= e(url('karyawan/use-stock.php')) ?>">＋ Catat pemakaian</a>
    </form>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No. transaksi</th>
                    <th>Bahan</th>
                    <th>Jumlah dipakai</th>
                    <th>Saldo sebelum → sesudah</th>
                    <th>Keperluan</th>
                    <th>Waktu</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$transactions): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state"><strong>Belum ada pemakaian pada periode ini</strong>
                                <p>Pemakaian yang Anda catat akan tampil di sini.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($transactions as $row): ?>
                    <tr>
                        <td><strong><?= e($row['transaction_code']) ?></strong></td>
                        <td>
                            <strong><?= e($row['material_name']) ?></strong>
                            <small class="block muted"><?= e($row['material_code']) ?></small>
                        </td>
                        <td class="text-danger">−<?= e(format_stock($row['quantity'])) ?>     <?= e($row['unit']) ?></td>
                        <td><?= e(format_stock($row['stock_before'])) ?> →
                            <strong><?= e(format_stock($row['stock_after'])) ?></strong></td>
                        <td>
                            <?= e($row['reason']) ?>
                            <?php if ($row['notes']): ?>
                                <small class="block muted"><?= e($row['notes']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e(date('d M Y, H:i', strtotime($row['created_at']))) ?></td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end;">
                                <a href="<?= e(url('karyawan/history-detail.php', ['id' => $row['id']])) ?>"
                                    class="button small secondary" data-open-mutation-modal
                                    data-trx-code="<?= e($row['transaction_code']) ?>"
                                    data-trx-type="<?= e($row['transaction_type']) ?>"
                                    data-trx-type-label="<?= e(transaction_label($row['transaction_type'])) ?>"
                                    data-trx-date="<?= e(indonesian_date('l, d F Y · H:i', strtotime($row['created_at']))) ?>"
                                    data-material-name="<?= e($row['material_name']) ?>"
                                    data-material-code="<?= e($row['material_code']) ?>"
                                    data-quantity="−<?= e(format_stock($row['quantity'])) . ' ' . e($row['unit']) ?>"
                                    data-quantity-class="out"
                                    data-before="<?= e(format_stock($row['stock_before'])) . ' ' . e($row['unit']) ?>"
                                    data-after="<?= e(format_stock($row['stock_after'])) . ' ' . e($row['unit']) ?>"
                                    data-reason="<?= e($row['reason']) ?>"
                                    data-notes="<?= e($row['notes'] ?: 'Tidak ada catatan tambahan') ?>"
                                    data-order="— Tidak terkait pesanan —" data-actor="<?= e($user['name']) ?>"
                                    data-detail-url="<?= e(url('karyawan/history-detail.php', ['id' => $row['id']])) ?>">Detail
                                    ↗</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-foot">Maksimal 300 pemakaian per tampilan · riwayat hanya menampilkan pemakaian akun Anda</div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
