<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['admin']);
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('
    SELECT t.*, 
           m.material_code, m.name AS material_name, m.category, m.unit, m.location, m.image_path,
           o.order_code, o.customer_id,
           c.name AS customer_name, c.phone AS customer_phone,
           u.name AS actor_name, u.role AS actor_role
    FROM inventory_transactions t
    JOIN materials m ON m.id = t.material_id
    JOIN users u ON u.id = t.created_by
    LEFT JOIN orders o ON o.id = t.order_id
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE t.id = ?
');
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    render_error(404, 'Data mutasi stok tidak ditemukan.');
    return;
}

$incoming = transaction_is_in($trx['transaction_type']);
$roleLabels = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'karyawan' => 'Karyawan'];
$pageTitle = 'Detail Mutasi ' . $trx['transaction_code'];
$activePage = 'admin_history';

require_once __DIR__ . '/../../includes/header.php';
?>
<div style="margin-bottom:18px;">
    <a class="button small secondary" href="<?= e(url('admin/inventory/history.php')) ?>">← Kembali ke Riwayat Stok</a>
</div>

<div class="detail-layout">
    <section class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow" style="margin:0 0 4px;"><?= e(indonesian_date('l, d F Y · H:i', strtotime($trx['created_at']))) ?></p>
                <h2 style="margin:0;"><?= e($trx['transaction_code']) ?></h2>
            </div>
            <span class="badge badge-<?= ($incoming ? 'success' : 'danger') ?>"><?= e(transaction_label($trx['transaction_type'])) ?></span>
        </div>

        <div style="padding:24px;">
            <div class="mutation-flow">
                <div class="flow-step">
                    <small>Saldo Sebelum</small>
                    <strong><?= e(format_stock($trx['stock_before'])) ?> <?= e($trx['unit']) ?></strong>
                </div>
                <div class="flow-arrow">→</div>
                <div class="flow-delta <?= ($incoming ? 'in' : 'out') ?>">
                    <small><?= e(transaction_label($trx['transaction_type'])) ?></small>
                    <?= ($incoming ? '+' : '−') . e(format_stock($trx['quantity'])) ?> <?= e($trx['unit']) ?>
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
                        <a href="<?= e(url('admin/inventory/detail.php', ['id' => $trx['material_id']])) ?>" class="table-primary">
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
                    <span class="muted">(<?= e($roleLabels[$trx['actor_role']] ?? $trx['actor_role']) ?>)</span>
                </div>
                <div class="detail-box">
                    <small>Waktu Pencatatan</small>
                    <strong><?= e(indonesian_date('d M Y, H:i:s', strtotime($trx['created_at']))) ?></strong>
                </div>
                <div class="detail-box full">
                    <small>Keperluan / Alasan</small>
                    <strong style="font-size:14px;"><?= e($trx['reason']) ?></strong>
                </div>
                <?php if ($trx['order_code']): ?>
                    <div class="detail-box full">
                        <small>Pesanan Terkait</small>
                        <strong>
                            <a href="<?= e(url('admin/orders/detail.php', ['id' => $trx['order_id']])) ?>" class="table-primary">
                                <?= e($trx['order_code']) ?>
                            </a>
                        </strong>
                        — Pemesan: <?= e($trx['customer_name'] ?: '—') ?> (<?= e($trx['customer_phone'] ?: '—') ?>)
                    </div>
                <?php endif; ?>
                <?php if ($trx['notes']): ?>
                    <div class="detail-box full">
                        <small>Catatan Tambahan</small>
                        <p style="margin:4px 0 0; white-space:pre-wrap;"><?= e($trx['notes']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions" style="margin:0; padding:16px 24px; border-top:1px solid var(--border); background:#fafbfd; display:flex; gap:10px;">
            <a class="button secondary" href="<?= e(url('admin/inventory/history.php')) ?>">Kembali</a>
            <a class="button secondary" href="<?= e(url('admin/inventory/history.php', ['material_id' => $trx['material_id']])) ?>">Riwayat Bahan Ini</a>
            <a class="button primary" href="<?= e(url('admin/inventory/detail.php', ['id' => $trx['material_id']])) ?>">Lihat Bahan</a>
        </div>
    </section>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
