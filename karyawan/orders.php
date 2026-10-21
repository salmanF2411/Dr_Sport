<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$pageTitle = 'Status Pesanan Produksi';
$activePage = 'karyawan_orders';

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');

$where = ["o.status <> 'Dibatalkan'"];
$params = [];

if ($search !== '') {
    $where[] = '(o.order_code LIKE ? OR o.jersey_description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$statuses = ['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai'];
if (in_array($status, $statuses, true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM orders o WHERE ' . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($total / 20));
$pageNumber = max(1, min((int) ($_GET['p'] ?? 1), $totalPages));
$offset = ($pageNumber - 1) * 20;

$stmt = db()->prepare('SELECT o.*, c.name AS customer_name FROM orders o JOIN customers c ON c.id=o.customer_id WHERE ' . implode(' AND ', $where) . ' ORDER BY o.order_date DESC, o.created_at DESC LIMIT 20 OFFSET ' . $offset);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="toolbar">
    <form class="filter-form" method="get" action="<?= e(url('karyawan/orders.php')) ?>">
        <label class="search-box">
            <span>⌕</span>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari kode pesanan / deskripsi" onsearch="this.form.submit()">
        </label>
        <select name="status" onchange="this.form.submit()">
            <option value="">Semua status aktif</option>
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
                    <th>Deskripsi Jersey</th>
                    <th>Jumlah</th>
                    <th>Tanggal Pesanan</th>
                    <th>Target Selesai</th>
                    <th>Status Pengerjaan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state"><strong>Tidak ada pesanan aktif</strong>
                                <p>Pesanan yang sedang dikerjakan akan tampil di sini.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?= e($order['order_code']) ?></strong></td>
                        <td><?= e(mb_strimwidth($order['jersey_description'], 0, 60, '…')) ?></td>
                        <td><?= number_format((int) $order['quantity']) ?> pcs</td>
                        <td><?= e(date('d M Y', strtotime($order['order_date']))) ?></td>
                        <td><?= e($order['target_date'] ? date('d M Y', strtotime($order['target_date'])) : '—') ?></td>
                        <td><?= status_badge($order['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-foot table-foot-paged">
        Menampilkan <?= count($orders) ?> dari <?= $total ?> pesanan berjalan
        <?= pagination_links('karyawan/orders.php', $pageNumber, $totalPages, ['q' => $search, 'status' => $status]) ?>
    </div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
