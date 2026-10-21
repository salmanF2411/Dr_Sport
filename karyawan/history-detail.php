<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('
    SELECT t.*, 
           m.material_code, m.name AS material_name, m.category, m.unit, m.location, m.image_path,
           u.name AS actor_name, u.role AS actor_role
    FROM inventory_transactions t
    JOIN materials m ON m.id = t.material_id
    JOIN users u ON u.id = t.created_by
    WHERE t.id = ?
');
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    render_error(404, 'Data mutasi stok tidak ditemukan.');
    return;
}

if ((int) $trx['created_by'] !== (int) $user['id']) {
    render_error(403, 'Anda hanya dapat melihat detail pemakaian yang Anda catat sendiri.');
    return;
}

$pageTitle = 'Detail Pemakaian ' . $trx['transaction_code'];
$activePage = 'karyawan_usage_history';

require_once __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom:18px;">
    <a class="button small secondary" href="<?= e(url('karyawan/usage-history.php')) ?>">← Kembali ke Riwayat Pemakaian Saya</a>
</div>

<div class="detail-layout">
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
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
