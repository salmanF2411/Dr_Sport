<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$pageTitle = 'Dashboard Karyawan';
$activePage = 'karyawan_dashboard';

$pdo = db();
$activeMaterials = (int) $pdo->query('SELECT COUNT(*) FROM materials WHERE is_active=1')->fetchColumn();
$lowCount = (int) $pdo->query('SELECT COUNT(*) FROM materials WHERE is_active=1 AND current_stock<=minimum_stock')->fetchColumn();
$orderCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Selesai','Dibatalkan')")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="stat-grid">
    <?php
    stat_card('Bahan aktif', (string) $activeMaterials, 'Siap untuk dicek & digunakan');
    stat_card('Perlu perhatian', (string) $lowCount, 'Stok menipis atau habis', 'warning');
    stat_card('Pesanan berjalan', (string) $orderCount, 'Status produksi workshop');
    stat_card('Saldo stok', 'DB', 'Data real-time dari database');
    ?>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Bahan terbaru</h2>
                <p>Periksa saldo bahan baku aktif</p>
            </div>
            <a class="text-link" href="<?= e(url('karyawan/availability.php')) ?>">Cek ketersediaan →</a>
        </div>
        <?php
        $materials = $pdo->query('SELECT id, material_code, name, category, unit, current_stock, minimum_stock, image_path FROM materials WHERE is_active=1 ORDER BY name LIMIT 6')->fetchAll();
        if (!$materials):
        ?>
            <div class="empty-state">
                <strong>Belum ada bahan aktif</strong>
                <p>Bahan inventory akan muncul setelah dicatat oleh Admin.</p>
            </div>
        <?php else: ?>
            <div class="low-list">
                <?php foreach ($materials as $material): ?>
                    <a class="low-item" href="<?= e(url('karyawan/materials.php', ['id' => $material['id']])) ?>">
                        <img src="<?= e(material_image($material)) ?>" alt="">
                        <span>
                            <strong><?= e($material['name']) ?></strong>
                            <small><?= e($material['material_code']) ?> · <?= e(category_label($material['category'])) ?></small>
                        </span>
                        <span class="low-stock"><?= e(format_stock($material['current_stock'])) ?> <?= e($material['unit']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Stok perlu perhatian</h2>
                <p>Saldo di bawah atau sama dengan minimum</p>
            </div>
            <a class="text-link" href="<?= e(url('karyawan/availability.php')) ?>">Inventory →</a>
        </div>
        <?php
        $lowMaterials = $pdo->query('SELECT id, material_code, name, category, unit, current_stock, minimum_stock, image_path FROM materials WHERE is_active=1 AND current_stock<=minimum_stock ORDER BY current_stock ASC LIMIT 6')->fetchAll();
        if (!$lowMaterials):
        ?>
            <div class="empty-state compact">
                <span class="empty-check">✓</span>
                <strong>Semua stok aman</strong>
                <p>Tidak ada bahan pada batas minimum.</p>
            </div>
        <?php else: ?>
            <div class="low-list">
                <?php foreach ($lowMaterials as $mat): ?>
                    <a class="low-item" href="<?= e(url('karyawan/materials.php', ['id' => $mat['id']])) ?>">
                        <img src="<?= e(material_image($mat)) ?>" alt="">
                        <span>
                            <strong><?= e($mat['name']) ?></strong>
                            <small><?= e($mat['material_code']) ?> · <?= e(category_label($mat['category'])) ?></small>
                        </span>
                        <span class="low-stock"><?= e(format_stock($mat['current_stock'])) ?> <?= e($mat['unit']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
