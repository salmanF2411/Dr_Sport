<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['admin']);
$pageTitle = 'Laporan Operasional & Inventory';
$activePage = 'admin_reports';

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

$orderStatus = (string) ($_GET['status'] ?? '');
$orderSearch = substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

$orderWhere = ['o.order_date BETWEEN ? AND ?'];
$orderParams = [$start, $end];
$allowedStatuses = ['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'];

if (in_array($orderStatus, $allowedStatuses, true)) {
    $orderWhere[] = 'o.status = ?';
    $orderParams[] = $orderStatus;
}
if ($orderSearch !== '') {
    $orderWhere[] = '(o.order_code LIKE ? OR c.name LIKE ?)';
    $orderParams[] = '%' . $orderSearch . '%';
    $orderParams[] = '%' . $orderSearch . '%';
}

$ordersStmt = db()->prepare('SELECT o.*, c.name AS customer_name FROM orders o JOIN customers c ON c.id=o.customer_id WHERE ' . implode(' AND ', $orderWhere) . ' ORDER BY o.order_date DESC LIMIT 500');
$ordersStmt->execute($orderParams);
$orders = $ordersStmt->fetchAll();

$inventoryStmt = db()->prepare("SELECT m.id, m.material_code, m.name, m.unit, m.current_stock, m.minimum_stock,
    COALESCE((SELECT t.stock_before FROM inventory_transactions t WHERE t.material_id=m.id AND t.created_at < ? ORDER BY t.created_at DESC, t.id DESC LIMIT 1), 0) AS opening_stock,
    COALESCE((SELECT SUM(t.quantity) FROM inventory_transactions t WHERE t.material_id=m.id AND t.transaction_type IN ('in','adjustment_in','return') AND DATE(t.created_at) BETWEEN ? AND ?), 0) AS total_in,
    COALESCE((SELECT SUM(t.quantity) FROM inventory_transactions t WHERE t.material_id=m.id AND t.transaction_type IN ('out','adjustment_out') AND DATE(t.created_at) BETWEEN ? AND ?), 0) AS total_out
    FROM materials m ORDER BY m.name");
$inventoryStmt->execute([$start, $start, $end, $start, $end]);
$inventory = $inventoryStmt->fetchAll();

$totalOrders = count($orders);
$totalJerseys = array_sum(array_map(function ($order) {
    return (int) $order['quantity'];
}, $orders));

$transactionCount = db()->prepare('SELECT COUNT(*) FROM inventory_transactions WHERE DATE(created_at) BETWEEN ? AND ?');
$transactionCount->execute([$start, $end]);
$totalMutations = (int) $transactionCount->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="toolbar">
    <form method="get" action="<?= e(url('admin/reports/index.php')) ?>" class="filter-form">
        <label class="field-inline">
            <span>Dari</span>
            <input type="date" name="start" value="<?= e($start) ?>">
        </label>
        <label class="field-inline">
            <span>Sampai</span>
            <input type="date" name="end" value="<?= e($end) ?>">
        </label>
        <label class="search-box">
            <span>⌕</span>
            <input type="search" name="q" value="<?= e($orderSearch) ?>" placeholder="Kode pesanan / pelanggan">
        </label>
        <select name="status">
            <option value="">Semua status</option>
            <?php foreach ($allowedStatuses as $val): ?>
                <option value="<?= e($val) ?>" <?= ($orderStatus === $val ? ' selected' : '') ?>><?= e($val) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button secondary">Tampilkan</button>
    </form>
    <button class="button primary no-print" onclick="window.print()">Cetak laporan</button>
</div>

<div class="report-period">
    Periode laporan: <strong><?= e(indonesian_date('d M Y', strtotime($start))) ?> –
        <?= e(indonesian_date('d M Y', strtotime($end))) ?></strong>
</div>

<div class="stat-grid report-stats">
    <?php
    stat_card('Total pesanan', (string) $totalOrders, 'Pada periode terpilih');
    stat_card('Total jersey', number_format($totalJerseys), 'Jumlah pada pesanan');
    stat_card('Bahan terdata', (string) count($inventory), 'Saldo inventory terkini');
    stat_card('Mutasi stok', (string) $totalMutations, 'Pada periode terpilih');
    ?>
</div>

<section class="panel report-section">
    <div class="panel-heading">
        <div>
            <h2>Laporan pesanan</h2>
            <p>Data berdasarkan tanggal pesanan · maksimal 500 hasil</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Ringkasan</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr>
                        <td colspan="6" class="muted">Tidak ada pesanan pada periode ini.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e($order['order_code']) ?></td>
                        <td><?= e(date('d M Y', strtotime($order['order_date']))) ?></td>
                        <td><?= e($order['customer_name']) ?></td>
                        <td><?= e(mb_strimwidth($order['jersey_description'], 0, 55, '…')) ?></td>
                        <td><?= number_format((int) $order['quantity']) ?> pcs</td>
                        <td><?= status_badge($order['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel report-section">
    <div class="panel-heading">
        <div>
            <h2>Laporan inventory</h2>
            <p>Saldo periode dihitung dari ledger mutasi. Saldo saat ini berasal dari saldo terkini master inventory.
            </p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bahan</th>
                    <th>Saldo awal periode</th>
                    <th>Stok masuk</th>
                    <th>Stok keluar</th>
                    <th>Saldo akhir periode</th>
                    <th>Saldo saat ini</th>
                    <th>Minimum</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$inventory): ?>
                    <tr>
                        <td colspan="7" class="muted">Belum ada bahan di inventory.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($inventory as $item): ?>
                    <?php
                    $closing = (float) $item['opening_stock'] + (float) $item['total_in'] - (float) $item['total_out'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($item['name']) ?></strong>
                            <small class="block muted"><?= e($item['material_code']) ?></small>
                        </td>
                        <td><?= e(format_stock($item['opening_stock'])) ?>     <?= e($item['unit']) ?></td>
                        <td class="text-success">+<?= e(format_stock($item['total_in'])) ?>     <?= e($item['unit']) ?></td>
                        <td class="text-danger">−<?= e(format_stock($item['total_out'])) ?>     <?= e($item['unit']) ?></td>
                        <td><strong><?= e(format_stock($closing)) ?>     <?= e($item['unit']) ?></strong></td>
                        <td><?= e(format_stock($item['current_stock'])) ?>     <?= e($item['unit']) ?></td>
                        <td><?= e(format_stock($item['minimum_stock'])) ?>     <?= e($item['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
require_once __DIR__ . '/../../includes/footer.php';
