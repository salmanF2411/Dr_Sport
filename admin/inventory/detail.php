<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['admin']);
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT m.*, u.name AS creator FROM materials m JOIN users u ON u.id=m.created_by WHERE m.id=?');
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    render_error(404, 'Bahan tidak ditemukan.');
    return;
}

$pageTitle = 'Detail Bahan ' . $material['material_code'];
$activePage = 'admin_inventory';

require_once __DIR__ . '/../../includes/header.php';
?>
<div style="margin-bottom:18px;">
    <a class="button small secondary" href="<?= e(url('admin/inventory/index.php')) ?>">← Kembali ke Inventory</a>
</div>

<div class="detail-layout">
    <section class="panel material-detail">
        <img class="material-hero" src="<?= e(material_image($material)) ?>" alt="<?= e($material['name']) ?>">
        <div>
            <p class="eyebrow"><?= e(category_label($material['category'])) ?> · <?= e($material['material_code']) ?></p>
            <h2><?= e($material['name']) ?></h2>
            <div class="detail-stock">
                <?= e(format_stock($material['current_stock'])) ?> <small><?= e($material['unit']) ?></small>
            </div>
            <p>
                <?= status_badge(material_state($material)) ?>
                <span class="muted">Minimum <?= e(format_stock($material['minimum_stock'])) ?> <?= e($material['unit']) ?></span>
            </p>
            <p class="muted">
                <?= ($material['location'] ? 'Lokasi Rak: ' . e($material['location']) : 'Lokasi rak belum dicatat') ?>
            </p>
            <p><?= nl2br(e($material['notes'] ?: 'Tidak ada catatan.')) ?></p>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Mutasi terbaru</h2>
                <p>Ledger mutasi stok bahan ini</p>
            </div>
            <a class="text-link" href="<?= e(url('admin/inventory/history.php', ['material_id' => $material['id']])) ?>">Lihat semua riwayat →</a>
        </div>
        <?php
        $stmtTrx = db()->prepare('SELECT t.*, u.name AS actor, o.order_code FROM inventory_transactions t JOIN users u ON u.id=t.created_by LEFT JOIN orders o ON o.id=t.order_id WHERE t.material_id=? ORDER BY t.created_at DESC LIMIT 8');
        $stmtTrx->execute([$material['id']]);
        $rows = $stmtTrx->fetchAll();
        ?>
        <?php if (!$rows): ?>
            <div class="empty-state compact">
                <strong>Belum ada transaksi</strong>
                <p>Stok awal dan mutasi akan tercatat di sini.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Transaksi</th>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                            <th>Saldo akhir</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['transaction_code']) ?></strong>
                                    <small class="block muted"><?= e($row['reason']) ?></small>
                                </td>
                                <td><?= e(transaction_label($row['transaction_type'])) ?></td>
                                <td class="<?= transaction_is_in($row['transaction_type']) ? 'text-success' : 'text-danger' ?>">
                                    <?= (transaction_is_in($row['transaction_type']) ? '+' : '−') . e(format_stock($row['quantity'])) ?> <?= e($material['unit']) ?>
                                </td>
                                <td><?= e(format_stock($row['stock_after'])) ?> <?= e($material['unit']) ?></td>
                                <td><?= e(date('d M Y, H:i', strtotime($row['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="row-actions">
        <a class="button primary" href="<?= e(url('admin/inventory/form.php', ['id' => $material['id']])) ?>">Edit bahan</a>
        <?php if ((int) $material['is_active']): ?>
            <a class="button secondary" href="<?= e(url('admin/inventory/stock.php', ['id' => $material['id']])) ?>">＋ Stok masuk</a>
        <?php else: ?>
            <span class="badge badge-dimmed">Nonaktif — Edit untuk mengaktifkan kembali</span>
        <?php endif; ?>
        <a class="button secondary" href="<?= e(url('admin/inventory/history.php', ['material_id' => $material['id']])) ?>">Riwayat lengkap</a>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
