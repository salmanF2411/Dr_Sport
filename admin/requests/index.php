<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['super_admin', 'admin']);
$pageTitle = 'Persetujuan Pengajuan Bahan';
$activePage = ($user['role'] === 'super_admin' ? 'superadmin_requests' : 'admin_requests');

$pdo = db();

$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
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

$where = ['DATE(r.created_at) BETWEEN ? AND ?'];
$params = [$start, $end];

if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'r.status = ?';
    $params[] = $status;
}

if ($search !== '') {
    $where[] = '(r.request_code LIKE ? OR m.name LIKE ? OR m.material_code LIKE ? OR u.name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query = '
    SELECT r.*,
           m.material_code, m.name AS material_name, m.category, m.unit, m.current_stock, m.minimum_stock, m.image_path,
           u.name AS requester_name,
           a.name AS approver_name,
           t.transaction_code, t.stock_before, t.stock_after
    FROM material_requests r
    JOIN materials m ON m.id = r.material_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN users a ON a.id = r.approved_by
    LEFT JOIN inventory_transactions t ON t.id = r.inventory_transaction_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY CASE WHEN r.status = "pending" THEN 0 ELSE 1 END, r.created_at DESC
    LIMIT 300
';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Statistik ringkasan
$statPending = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status='pending'")->fetchColumn();
$statApprovedMonth = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status='approved' AND MONTH(approved_at) = MONTH(CURRENT_DATE()) AND YEAR(approved_at) = YEAR(CURRENT_DATE())")->fetchColumn();
$statRejectedMonth = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status='rejected' AND MONTH(approved_at) = MONTH(CURRENT_DATE()) AND YEAR(approved_at) = YEAR(CURRENT_DATE())")->fetchColumn();
$statTotalMonth = (int) $pdo->query("SELECT COUNT(*) FROM material_requests WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="stat-grid">
    <?php
    stat_card('Menunggu Konfirmasi', (string) $statPending, 'Pengajuan bahan belum diproses', $statPending > 0 ? 'warning' : '');
    stat_card('Disetujui Bulan Ini', (string) $statApprovedMonth, 'Stok telah dikurangi');
    stat_card('Ditolak Bulan Ini', (string) $statRejectedMonth, 'Pengajuan tidak disetujui');
    stat_card('Total Pengajuan', (string) $statTotalMonth, 'Pengajuan bulan ini');
    ?>
</div>

<div class="toolbar">
    <form method="get" action="<?= e(url('admin/requests/index.php')) ?>" class="filter-form">
        <?= search_box('q', $search, 'Cari kode, bahan, atau pemohon') ?>
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
        <?php if ($search !== '' || $status !== ''): ?>
            <a href="<?= e(url('admin/requests/index.php')) ?>" class="button small subtle">Reset filter</a>
        <?php endif; ?>
    </form>
</div>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Daftar Pengajuan Pemakaian Bahan</h2>
            <p>Admin memverifikasi dan menyetujui pengajuan bahan dari karyawan sebelum stok sistem dikurangi.</p>
        </div>
        <?php if ($statPending > 0): ?>
            <span class="badge badge-warning"><?= $statPending ?> pengajuan menunggu konfirmasi</span>
        <?php endif; ?>
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
                    <th>Keperluan & Catatan</th>
                    <th>Status</th>
                    <th>Konfirmasi Admin</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$requests): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <strong>Tidak ada pengajuan bahan</strong>
                                <p>Pengajuan yang dikirimkan oleh karyawan akan tampil di sini untuk dikonfirmasi.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($requests as $req): ?>
                    <?php
                    $isPending = ($req['status'] === 'pending');
                    $isApproved = ($req['status'] === 'approved');
                    $isRejected = ($req['status'] === 'rejected');
                    $hasEnoughStock = ((float) $req['current_stock'] >= (float) $req['quantity']);
                    ?>
                    <tr style="<?= $isPending ? 'background: #fffcf5;' : '' ?>">
                        <td>
                            <strong><?= e($req['request_code']) ?></strong>
                            <small class="block muted"><?= e(date('d M Y, H:i', strtotime($req['created_at']))) ?></small>
                        </td>
                        <td>
                            <strong><?= e($req['requester_name']) ?></strong>
                            <small class="block muted">Karyawan</small>
                        </td>
                        <td>
                            <div class="material-cell">
                                <img src="<?= e(material_image($req)) ?>" alt="">
                                <span>
                                    <a class="table-primary" href="<?= e(url('admin/inventory/detail.php', ['id' => $req['material_id']])) ?>">
                                        <?= e($req['material_name']) ?>
                                    </a>
                                    <small><?= e($req['material_code']) ?> · <?= e(category_label($req['category'])) ?></small>
                                </span>
                            </div>
                        </td>
                        <td>
                            <strong style="font-size:13px; color:#1e293b;">
                                <?= e(format_stock($req['quantity'])) ?>
                            </strong>
                            <span class="muted"><?= e($req['unit']) ?></span>
                        </td>
                        <td>
                            <strong><?= e(format_stock($req['current_stock'])) ?></strong> <?= e($req['unit']) ?>
                            <?php if ($isPending && !$hasEnoughStock): ?>
                                <small class="block text-danger">⚠ Stok kurang</small>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 250px; white-space: normal;">
                            <strong><?= e($req['reason']) ?></strong>
                            <?php if ($req['notes']): ?>
                                <small class="block muted"><?= e($req['notes']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= status_badge(request_status_label($req['status'])) ?>
                        </td>
                        <td style="max-width: 220px; white-space: normal;">
                            <?php if ($isPending): ?>
                                <span class="muted">Menunggu tindakan Admin</span>
                            <?php elseif ($isApproved): ?>
                                <strong><?= e($req['approver_name'] ?? 'Admin') ?></strong>
                                <small class="block muted"><?= e(date('d M Y, H:i', strtotime((string)$req['approved_at']))) ?></small>
                                <?php if ($req['admin_notes']): ?>
                                    <small class="block text-success"><?= e($req['admin_notes']) ?></small>
                                <?php endif; ?>
                                <?php if ($req['transaction_code']): ?>
                                    <small class="block muted">Trx: <?= e($req['transaction_code']) ?></small>
                                <?php endif; ?>
                            <?php elseif ($isRejected): ?>
                                <strong class="text-danger"><?= e($req['approver_name'] ?? 'Admin') ?></strong>
                                <small class="block muted"><?= e(date('d M Y, H:i', strtotime((string)$req['approved_at']))) ?></small>
                                <?php if ($req['admin_notes']): ?>
                                    <small class="block text-danger"><?= e($req['admin_notes']) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end;">
                                <?php if ($isPending): ?>
                                    <?php if ($hasEnoughStock): ?>
                                        <form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" class="inline-form" onsubmit="return confirm('Setujui pengajuan <?= e($req['request_code']) ?>?\n\nBahan: <?= e($req['material_name']) ?>\nJumlah: <?= format_stock($req['quantity']) ?> <?= e($req['unit']) ?>\nSaldo saat ini: <?= format_stock($req['current_stock']) ?> <?= e($req['unit']) ?>\n\nStok akan langsung dikurangi sebesar <?= format_stock($req['quantity']) ?> <?= e($req['unit']) ?>.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="approve_material_request">
                                            <input type="hidden" name="request_id" value="<?= e($req['id']) ?>">
                                            <input type="hidden" name="redirect_to" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                            <button type="submit" class="button small primary" title="Setujui pengajuan dan kurangi saldo stok">✓ Setujui</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="button small secondary disabled" title="Stok tidak mencukupi untuk disetujui" disabled>Stok Kurang</button>
                                    <?php endif; ?>

                                    <button type="button" class="button small secondary" style="color:#c0362c; border-color:#f0d1cf;" onclick="openRejectModal(<?= (int) $req['id'] ?>, '<?= e(addslashes($req['request_code'])) ?>', '<?= e(addslashes($req['material_name'])) ?>', '<?= e(format_stock($req['quantity']) . ' ' . $req['unit']) ?>')" title="Tolak pengajuan">✕ Tolak</button>
                                <?php else: ?>
                                    <?php if ($isApproved && $req['inventory_transaction_id']): ?>
                                        <a href="<?= e(url('admin/inventory/history-detail.php', ['id' => $req['inventory_transaction_id']])) ?>" class="button small secondary" title="Lihat mutasi stok pengurangan">Mutasi ↗</a>
                                    <?php else: ?>
                                        <span class="muted" style="font-size:11px;">Selesai</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-foot">Maksimal 300 pengajuan per tampilan · pengajuan yang telah disetujui otomatis mengurangi saldo inventory</div>
</section>

<!-- Dialog Tolak Pengajuan -->
<dialog id="reject-modal" class="modal-dialog">
    <form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject_material_request">
        <input type="hidden" name="request_id" id="reject-modal-id" value="">
        <input type="hidden" name="redirect_to" value="<?= e($_SERVER['REQUEST_URI']) ?>">

        <div class="modal-header">
            <div>
                <p class="eyebrow" style="margin:0 0 2px; font-size:10px; color:#c0362c;">KONFIRMASI PENOLAKAN</p>
                <h3 id="reject-modal-title" style="margin:0; font-size:16px;">Tolak Pengajuan Bahan</h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="document.getElementById('reject-modal').close()">✕</button>
        </div>
        <div class="modal-body">
            <p id="reject-modal-desc" style="margin:0 0 15px; color:#475467; font-size:12px;"></p>
            <label class="field">
                <span>Alasan Penolakan <b>*</b></span>
                <textarea name="admin_notes" id="reject-modal-notes" rows="3" required maxlength="255" placeholder="Contoh: Stok sedang dialokasikan untuk order mendesak / silakan gunakan bahan sisa di rak B"></textarea>
                <small>Alasan penolakan akan dapat dibaca oleh karyawan pemohon.</small>
            </label>
        </div>
        <div class="modal-footer">
            <button type="button" class="button secondary small" onclick="document.getElementById('reject-modal').close()">Batal</button>
            <button type="submit" class="button primary small" style="background:#c0362c; border-color:#c0362c;">Tolak Pengajuan</button>
        </div>
    </form>
</dialog>

<script>
function openRejectModal(id, code, name, qty) {
    document.getElementById('reject-modal-id').value = id;
    document.getElementById('reject-modal-title').textContent = 'Tolak Pengajuan ' + code;
    document.getElementById('reject-modal-desc').innerHTML = 'Anda akan menolak pengajuan <strong>' + name + '</strong> sejumlah <strong>' + qty + '</strong>. Stok <strong>tidak akan berkurang</strong>.';
    document.getElementById('reject-modal-notes').value = '';
    var modal = document.getElementById('reject-modal');
    if (typeof modal.showModal === 'function') {
        modal.showModal();
    } else {
        modal.setAttribute('open', '');
    }
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
