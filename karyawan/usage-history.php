<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$pageTitle = 'Riwayat Pengajuan Bahan Saya';
$activePage = 'karyawan_usage_history';

$userId = (int) $user['id'];
$pdo = db();

$start = (string) ($_GET['start'] ?? date('Y-m-01'));
$end = (string) ($_GET['end'] ?? date('Y-m-d'));
$status = trim((string) ($_GET['status'] ?? ''));
$materialId = (int) ($_GET['material_id'] ?? 0);

if (!valid_date($start)) {
    $start = date('Y-m-01');
}
if (!valid_date($end)) {
    $end = date('Y-m-d');
}
if ($end < $start) {
    $end = $start;
}

$where = ['r.user_id = ?', 'DATE(r.created_at) BETWEEN ? AND ?'];
$params = [$userId, $start, $end];

if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'r.status = ?';
    $params[] = $status;
}

if ($materialId > 0) {
    $where[] = 'r.material_id = ?';
    $params[] = $materialId;
}

$stmt = $pdo->prepare('
    SELECT r.*,
           m.material_code, m.name AS material_name, m.unit, m.category, m.image_path,
           a.name AS approver_name,
           t.transaction_code, t.stock_before, t.stock_after
    FROM material_requests r
    JOIN materials m ON m.id = r.material_id
    LEFT JOIN users a ON a.id = r.approved_by
    LEFT JOIN inventory_transactions t ON t.id = r.inventory_transaction_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY r.created_at DESC
    LIMIT 300
');
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Statistik ringkasan akun karyawan ini
$statPending = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE user_id = {$userId} AND status='pending'")->fetchColumn();
$statApproved = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE user_id = {$userId} AND status='approved'")->fetchColumn();
$statRejected = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE user_id = {$userId} AND status='rejected'")->fetchColumn();
$statTotal = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE user_id = {$userId}")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
    <?php
    stat_card('Menunggu Konfirmasi', (string) $statPending, 'Sedang ditinjau oleh Admin', $statPending > 0 ? 'warning' : '');
    stat_card('Disetujui', (string) $statApproved, 'Stok telah dialokasikan');
    stat_card('Ditolak', (string) $statRejected, 'Pengajuan tidak disetujui');
    stat_card('Total Pengajuan', (string) $statTotal, 'Seluruh riwayat akun Anda');
    ?>
</div>

<div class="toolbar">
    <form method="get" action="<?= e(url('karyawan/usage-history.php')) ?>" class="filter-form">
        <?php if ($materialId > 0): ?>
            <input type="hidden" name="material_id" value="<?= e($materialId) ?>">
        <?php endif; ?>
        <select name="status" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="pending"<?= ($status === 'pending' ? ' selected' : '') ?>>Menunggu Konfirmasi (<?= $statPending ?>)</option>
            <option value="approved"<?= ($status === 'approved' ? ' selected' : '') ?>>Disetujui</option>
            <option value="rejected"<?= ($status === 'rejected' ? ' selected' : '') ?>>Ditolak</option>
        </select>
        <label class="field-inline">
            <span>Dari</span>
            <input type="date" name="start" value="<?= e($start) ?>" onchange="if(!this.value||this.value.length===10)this.form.submit()">
        </label>
        <label class="field-inline">
            <span>Sampai</span>
            <input type="date" name="end" value="<?= e($end) ?>" onchange="if(!this.value||this.value.length===10)this.form.submit()">
        </label>
        <?php if ($materialId > 0 || $status !== ''): ?>
            <a href="<?= e(url('karyawan/usage-history.php')) ?>" class="button small subtle">Reset filter</a>
        <?php endif; ?>
        <a class="button primary" href="<?= e(url('karyawan/use-stock.php')) ?>">＋ Ajukan Bahan Baru</a>
    </form>
</div>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Riwayat Pengajuan Pemakaian Bahan</h2>
            <p>Daftar seluruh permintaan bahan yang telah Anda ajukan beserta status persetujuan dari Admin.</p>
        </div>
        <small class="muted">Saldo hanya berkurang bila pengajuan berstatus Disetujui</small>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No. Pengajuan</th>
                    <th>Bahan</th>
                    <th>Jumlah Diminta</th>
                    <th>Keperluan</th>
                    <th>Status</th>
                    <th>Konfirmasi Admin</th>
                    <th>Waktu Diajukan</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$requests): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <strong>Belum ada pengajuan bahan pada periode ini</strong>
                                <p>Pengajuan yang Anda kirimkan ke Admin akan tampil di sini.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($requests as $row): ?>
                    <?php
                    $isPending = ($row['status'] === 'pending');
                    $isApproved = ($row['status'] === 'approved');
                    $isRejected = ($row['status'] === 'rejected');
                    ?>
                    <tr style="<?= $isPending ? 'background: #fffcf5;' : '' ?>">
                        <td>
                            <strong><?= e($row['request_code']) ?></strong>
                            <?php if ($row['transaction_code']): ?>
                                <small class="block muted">Trx: <?= e($row['transaction_code']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="material-cell">
                                <img src="<?= e(material_image($row)) ?>" alt="">
                                <span>
                                    <a class="table-primary" href="<?= e(url('karyawan/materials.php', ['id' => $row['material_id']])) ?>">
                                        <?= e($row['material_name']) ?>
                                    </a>
                                    <small><?= e($row['material_code']) ?> · <?= e(category_label($row['category'])) ?></small>
                                </span>
                            </div>
                        </td>
                        <td>
                            <strong style="font-size:13px; color: <?= $isApproved ? 'var(--danger)' : '#1e293b' ?>;">
                                <?= $isApproved ? '−' : '' ?><?= e(format_stock($row['quantity'])) ?>
                            </strong>
                            <span class="muted"><?= e($row['unit']) ?></span>
                        </td>
                        <td style="max-width: 230px; white-space: normal;">
                            <strong><?= e($row['reason']) ?></strong>
                            <?php if ($row['notes']): ?>
                                <small class="block muted"><?= e($row['notes']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= status_badge(request_status_label($row['status'])) ?>
                        </td>
                        <td style="max-width: 220px; white-space: normal;">
                            <?php if ($isPending): ?>
                                <span class="muted">⏳ Menunggu persetujuan Admin</span>
                            <?php elseif ($isApproved): ?>
                                <strong class="text-success">Disetujui oleh <?= e($row['approver_name'] ?? 'Admin') ?></strong>
                                <small class="block muted"><?= e(date('d M Y, H:i', strtotime((string)$row['approved_at']))) ?></small>
                                <?php if ($row['admin_notes']): ?>
                                    <small class="block text-success"><?= e($row['admin_notes']) ?></small>
                                <?php endif; ?>
                            <?php elseif ($isRejected): ?>
                                <strong class="text-danger">Ditolak oleh <?= e($row['approver_name'] ?? 'Admin') ?></strong>
                                <small class="block muted"><?= e(date('d M Y, H:i', strtotime((string)$row['approved_at']))) ?></small>
                                <?php if ($row['admin_notes']): ?>
                                    <small class="block text-danger">Alasan: <?= e($row['admin_notes']) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e(date('d M Y, H:i', strtotime($row['created_at']))) ?>
                        </td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end;">
                                <a href="<?= e(url('karyawan/history-detail.php', ['request_id' => $row['id']])) ?>"
                                    class="button small secondary">Detail ↗</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-foot">Maksimal 300 pengajuan per tampilan · riwayat hanya menampilkan pengajuan akun Anda</div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
