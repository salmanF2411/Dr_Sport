<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$requestId = (int) ($_GET['request_id'] ?? 0);
$trxId = (int) ($_GET['id'] ?? 0);

$pdo = db();
$request = null;
$trx = null;

if ($requestId > 0) {
    $stmt = $pdo->prepare('
        SELECT r.*,
               m.material_code, m.name AS material_name, m.category, m.unit, m.location, m.image_path,
               u.name AS requester_name,
               a.name AS approver_name,
               t.transaction_code, t.stock_before, t.stock_after
        FROM material_requests r
        JOIN materials m ON m.id = r.material_id
        JOIN users u ON u.id = r.user_id
        LEFT JOIN users a ON a.id = r.approved_by
        LEFT JOIN inventory_transactions t ON t.id = r.inventory_transaction_id
        WHERE r.id = ?
    ');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
} elseif ($trxId > 0) {
    // Check if this trx is linked to a request
    $stmt = $pdo->prepare('
        SELECT r.*,
               m.material_code, m.name AS material_name, m.category, m.unit, m.location, m.image_path,
               u.name AS requester_name,
               a.name AS approver_name,
               t.transaction_code, t.stock_before, t.stock_after
        FROM material_requests r
        JOIN materials m ON m.id = r.material_id
        JOIN users u ON u.id = r.user_id
        LEFT JOIN users a ON a.id = r.approved_by
        LEFT JOIN inventory_transactions t ON t.id = r.inventory_transaction_id
        WHERE r.id = ? OR r.inventory_transaction_id = ?
    ');
    $stmt->execute([$trxId, $trxId]);
    $request = $stmt->fetch();

    if (!$request) {
        // Fallback to pure transaction
        $stmt = $pdo->prepare('
            SELECT t.*, 
                   m.material_code, m.name AS material_name, m.category, m.unit, m.location, m.image_path,
                   u.name AS actor_name, u.role AS actor_role
            FROM inventory_transactions t
            JOIN materials m ON m.id = t.material_id
            JOIN users u ON u.id = t.created_by
            WHERE t.id = ?
        ');
        $stmt->execute([$trxId]);
        $trx = $stmt->fetch();
    }
}

if (!$request && !$trx) {
    render_error(404, 'Data pengajuan atau pemakaian bahan tidak ditemukan.');
    return;
}

if ($request && (int) $request['user_id'] !== (int) $user['id']) {
    render_error(403, 'Anda hanya dapat melihat detail pengajuan yang Anda buat sendiri.');
    return;
}

if ($trx && (int) $trx['created_by'] !== (int) $user['id']) {
    render_error(403, 'Anda hanya dapat melihat detail pemakaian yang Anda catat sendiri.');
    return;
}

$pageTitle = $request ? ('Detail Pengajuan ' . $request['request_code']) : ('Detail Pemakaian ' . $trx['transaction_code']);
$activePage = 'karyawan_usage_history';

require_once __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom:18px;">
    <a class="button small secondary" href="<?= e(url('karyawan/usage-history.php')) ?>">← Kembali ke Riwayat Pengajuan</a>
</div>

<div class="detail-layout">
    <?php if ($request): ?>
        <?php
        $isPending = ($request['status'] === 'pending');
        $isApproved = ($request['status'] === 'approved');
        $isRejected = ($request['status'] === 'rejected');
        ?>
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow" style="margin:0 0 4px;"><?= e(indonesian_date('l, d F Y · H:i', strtotime($request['created_at']))) ?></p>
                    <h2 style="margin:0;"><?= e($request['request_code']) ?></h2>
                </div>
                <?= status_badge(request_status_label($request['status'])) ?>
            </div>

            <div style="padding:24px;">
                <?php if ($isPending): ?>
                    <div class="alert alert-warning" style="margin-bottom:20px;">
                        <strong>Menunggu Konfirmasi:</strong> Pengajuan ini sedang ditinjau oleh Admin. Stok sistem <strong>belum berkurang</strong> dan baru akan dipotong setelah Admin menyetujui.
                    </div>
                <?php elseif ($isRejected): ?>
                    <div class="alert alert-error" style="margin-bottom:20px;">
                        <strong>Pengajuan Ditolak:</strong> Admin menolak pengajuan ini. Stok tidak mengalami perubahan.
                        <?php if ($request['admin_notes']): ?>
                            <div style="margin-top:6px;"><strong>Alasan Penolakan:</strong> <?= e($request['admin_notes']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($isApproved && $request['stock_before'] !== null): ?>
                    <div class="mutation-flow">
                        <div class="flow-step">
                            <small>Saldo Sebelum Disetujui</small>
                            <strong><?= e(format_stock($request['stock_before'])) ?> <?= e($request['unit']) ?></strong>
                        </div>
                        <div class="flow-arrow">→</div>
                        <div class="flow-delta out">
                            <small>Disetujui & Dipotong</small>
                            −<?= e(format_stock($request['quantity'])) ?> <?= e($request['unit']) ?>
                        </div>
                        <div class="flow-arrow">→</div>
                        <div class="flow-step">
                            <small>Saldo Sesudah</small>
                            <strong><?= e(format_stock($request['stock_after'])) ?> <?= e($request['unit']) ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="detail-grid">
                    <div class="detail-box">
                        <small>Bahan yang Diajukan</small>
                        <strong>
                            <a href="<?= e(url('karyawan/materials.php', ['id' => $request['material_id']])) ?>" class="table-primary">
                                <?= e($request['material_name']) ?>
                            </a>
                        </strong>
                        <span class="muted">(<?= e($request['material_code']) ?> · <?= e(category_label($request['category'])) ?>)</span>
                    </div>
                    <div class="detail-box">
                        <small>Jumlah Diminta</small>
                        <strong style="font-size:16px; color:#1e293b;">
                            <?= e(format_stock($request['quantity'])) ?> <?= e($request['unit']) ?>
                        </strong>
                    </div>
                    <div class="detail-box">
                        <small>Pemohon</small>
                        <strong><?= e($request['requester_name']) ?></strong>
                        <span class="muted">(Karyawan)</span>
                    </div>
                    <div class="detail-box">
                        <small>Waktu Pengajuan</small>
                        <strong><?= e(indonesian_date('d M Y, H:i:s', strtotime($request['created_at']))) ?></strong>
                    </div>
                    <div class="detail-box full">
                        <small>Keperluan / Alasan</small>
                        <strong style="font-size:14px;"><?= e($request['reason']) ?></strong>
                    </div>
                    <?php if ($request['notes']): ?>
                        <div class="detail-box full">
                            <small>Catatan Tambahan Karyawan</small>
                            <p style="margin:4px 0 0; white-space:pre-wrap;"><?= e($request['notes']) ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="detail-box">
                        <small>Status Pengajuan</small>
                        <div><?= status_badge(request_status_label($request['status'])) ?></div>
                    </div>
                    <div class="detail-box">
                        <small>Ditinjau Oleh Admin</small>
                        <strong><?= e($request['approver_name'] ?? '— Belum ditinjau —') ?></strong>
                        <?php if ($request['approved_at']): ?>
                            <span class="muted">(<?= e(indonesian_date('d M Y, H:i', strtotime((string)$request['approved_at']))) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($request['admin_notes']): ?>
                        <div class="detail-box full">
                            <small>Catatan dari Admin</small>
                            <strong style="color: <?= $isApproved ? 'var(--success)' : 'var(--danger)' ?>;"><?= e($request['admin_notes']) ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($request['transaction_code']): ?>
                        <div class="detail-box full">
                            <small>Nomor Transaksi Mutasi Inventory</small>
                            <strong><?= e($request['transaction_code']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions" style="margin:0; padding:16px 24px; border-top:1px solid var(--border); background:#fafbfd; display:flex; gap:10px;">
                <a class="button secondary" href="<?= e(url('karyawan/usage-history.php')) ?>">Kembali</a>
                <a class="button primary" href="<?= e(url('karyawan/materials.php', ['id' => $request['material_id']])) ?>">Lihat Info Bahan</a>
            </div>
        </section>
    <?php else: ?>
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow" style="margin:0 0 4px;"><?= e(indonesian_date('l, d F Y · H:i', strtotime($trx['created_at']))) ?></p>
                    <h2 style="margin:0;"><?= e($trx['transaction_code']) ?></h2>
                </div>
                <span class="badge badge-danger">Pemakaian Keluar</span>
            </div>

            <div style="padding:24px;">
                <div class="mutation-flow">
                    <div class="flow-step">
                        <small>Saldo Sebelum</small>
                        <strong><?= e(format_stock($trx['stock_before'])) ?> <?= e($trx['unit']) ?></strong>
                    </div>
                    <div class="flow-arrow">→</div>
                    <div class="flow-delta out">
                        <small>Dipakai</small>
                        −<?= e(format_stock($trx['quantity'])) ?> <?= e($trx['unit']) ?>
                    </div>
                    <div class="flow-arrow">→</div>
                    <div class="flow-step">
                        <small>Saldo Sesudah</small>
                        <strong><?= e(format_stock($trx['stock_after'])) ?> <?= e($trx['unit']) ?></strong>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-box">
                        <small>Bahan</small>
                        <strong>
                            <a href="<?= e(url('karyawan/materials.php', ['id' => $trx['material_id']])) ?>" class="table-primary">
                                <?= e($trx['material_name']) ?>
                            </a>
                        </strong>
                        <span class="muted">(<?= e($trx['material_code']) ?>)</span>
                    </div>
                    <div class="detail-box">
                        <small>Lokasi Rak</small>
                        <strong>
                            <?= ($trx['location'] ? '<span class="location-badge"><span class="location-badge-icon">◧</span> ' . e($trx['location']) . '</span>' : '<span class="muted">Belum ditentukan</span>') ?>
                        </strong>
                    </div>
                    <div class="detail-box">
                        <small>Dicatat Oleh</small>
                        <strong><?= e($trx['actor_name']) ?></strong>
                        <span class="muted">(Karyawan)</span>
                    </div>
                    <div class="detail-box">
                        <small>Waktu Pencatatan</small>
                        <strong><?= e(indonesian_date('d M Y, H:i:s', strtotime($trx['created_at']))) ?></strong>
                    </div>
                    <div class="detail-box full">
                        <small>Keperluan / Alasan</small>
                        <strong style="font-size:14px;"><?= e($trx['reason']) ?></strong>
                    </div>
                    <?php if ($trx['notes']): ?>
                        <div class="detail-box full">
                            <small>Catatan Tambahan</small>
                            <p style="margin:4px 0 0; white-space:pre-wrap;"><?= e($trx['notes']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions" style="margin:0; padding:16px 24px; border-top:1px solid var(--border); background:#fafbfd; display:flex; gap:10px;">
                <a class="button secondary" href="<?= e(url('karyawan/usage-history.php')) ?>">Kembali</a>
                <a class="button primary" href="<?= e(url('karyawan/materials.php', ['id' => $trx['material_id']])) ?>">Lihat Bahan</a>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
