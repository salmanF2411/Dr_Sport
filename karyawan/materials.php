<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT m.*, u.name AS creator FROM materials m JOIN users u ON u.id=m.created_by WHERE m.id=?');
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    render_error(404, 'Bahan tidak ditemukan.');
    return;
}

$pageTitle = 'Detail Bahan ' . $material['material_code'];
$activePage = 'karyawan_availability';

require_once __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom:18px;">
    <a class="button small secondary" href="<?= e(url('karyawan/availability.php')) ?>">← Kembali ke Cek Ketersediaan</a>
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

    <div class="row-actions">
        <a class="button primary" href="<?= e(url('karyawan/use-stock.php', ['id' => $material['id']])) ?>">Gunakan bahan</a>
        <a class="button secondary" href="<?= e(url('karyawan/usage-history.php', ['material_id' => $material['id']])) ?>">Riwayat penggunaan saya</a>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
