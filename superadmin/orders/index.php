<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['super_admin']);
$pageTitle = 'Monitoring Pesanan';
$activePage = 'superadmin_orders';

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$start = (string) ($_GET['start'] ?? '');
$end = (string) ($_GET['end'] ?? '');

if ($start !== '' && !valid_date($start)) {
    $start = '';
}
if ($end !== '' && !valid_date($end)) {
    $end = '';
}
if ($start !== '' && $end !== '' && $end < $start) {
    $end = $start;
}

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(o.order_code LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
    array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
}

$statuses = ['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'];
if (in_array($status, $statuses, true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($start !== '') {
    $where[] = 'o.order_date >= ?';
    $params[] = $start;
}
if ($end !== '') {
    $where[] = 'o.order_date <= ?';
    $params[] = $end;
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM orders o JOIN customers c ON c.id=o.customer_id WHERE ' . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($total / 20));
$pageNumber = max(1, min((int) ($_GET['p'] ?? 1), $totalPages));
$offset = ($pageNumber - 1) * 20;

$stmt = db()->prepare('SELECT o.*,c.name customer_name,c.phone,u.name creator FROM orders o JOIN customers c ON c.id=o.customer_id JOIN users u ON u.id=o.created_by WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC LIMIT 20 OFFSET ' . $offset);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="toolbar">
    <form class="filter-form" method="get" action="<?= e(url('superadmin/orders/index.php')) ?>">
        <label class="search-box">
            <span>⌕</span>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari kode, pelanggan, kontak">
        </label>
        <label class="field-inline">
            <span>Dari</span>
            <input type="date" name="start" value="<?= e($start) ?>">
        </label>
        <label class="field-inline">
            <span>Sampai</span>
            <input type="date" name="end" value="<?= e($end) ?>">
        </label>
        <select name="status">
            <option value="">Semua status</option>
            <?php foreach ($statuses as $val): ?>
                <option value="<?= e($val) ?>" <?= ($status === $val ? ' selected' : '') ?>><?= e($val) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode pesanan</th>
                    <th>Pelanggan</th>
                    <th>Tanggal</th>
                    <th>Target selesai</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Admin Pembuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state"><strong>Belum ada pesanan</strong>
                                <p>Pesanan yang cocok dengan filter akan tampil di sini.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a class="table-primary"
                                href="<?= e(url('superadmin/orders/detail.php', ['id' => $order['id']])) ?>">
                                <?= e($order['order_code']) ?>
                            </a>
                        </td>
                        <td>
                            <?= e($order['customer_name']) ?>
                            <small class="block muted"><?= e($order['phone']) ?></small>
                        </td>
                        <td><?= e(date('d M Y', strtotime($order['order_date']))) ?></td>
                        <td><?= e($order['target_date'] ? date('d M Y', strtotime($order['target_date'])) : '—') ?></td>
                        <td><?= number_format((int) $order['quantity']) ?> pcs</td>
                        <td><?= status_badge($order['status']) ?></td>
                        <td><?= e($order['creator']) ?></td>
                        <td>
                            <a class="button small secondary"
                                href="<?= e(url('superadmin/orders/detail.php', ['id' => $order['id']])) ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-foot table-foot-paged">
        Menampilkan <?= count($orders) ?> dari <?= $total ?> pesanan
        <?= pagination_links('superadmin/orders/index.php', $pageNumber, $totalPages, ['q' => $search, 'status' => $status, 'start' => $start, 'end' => $end]) ?>
    </div>
</section>

<?php
require_once __DIR__ . '/../../includes/footer.php';
