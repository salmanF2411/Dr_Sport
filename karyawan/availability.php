<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$pageTitle = 'Cek Ketersediaan Bahan Baku';
$activePage = 'karyawan_availability';

$search = trim((string) ($_GET['q'] ?? ''));
$category = (string) ($_GET['category'] ?? '');

$stmt = db()->prepare('SELECT * FROM materials WHERE is_active=1 AND (? = "" OR name LIKE ? OR material_code LIKE ?) AND (? = "" OR category=?) ORDER BY name LIMIT 150');
$like = '%' . $search . '%';
$stmt->execute([$search, $like, $like, $category, $category]);
$materials = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="availability-intro">
    <div>
        <span class="live-dot"></span>
        <strong>Saldo terhubung ke database inventory</strong>
        <p>Cek kebutuhan hanya membaca saldo tanpa mengubah stok. Untuk menggunakan bahan pada pekerjaan, klik Gunakan
            untuk mencatat pemakaian.</p>
    </div>
    <small>Terakhir dimuat <?= e(date('H:i')) ?></small>
</div>

<div class="toolbar">
    <form method="get" action="<?= e(url('karyawan/availability.php')) ?>" class="filter-form">
        <label class="search-box">
            <span>⌕</span>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau kode bahan" onsearch="this.form.submit()">
        </label>
        <select name="category" onchange="this.form.submit()">
            <option value="">Semua kategori</option>
            <?php foreach (['kain', 'tinta_printing', 'benang'] as $val): ?>
                <option value="<?= e($val) ?>" <?= ($category === $val ? ' selected' : '') ?>><?= e(category_label($val)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bahan</th>
                    <th>Kategori</th>
                    <th>Stok tersedia</th>
                    <th>Status</th>
                    <th>Cek kebutuhan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$materials): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state"><strong>Bahan tidak ditemukan</strong>
                                <p>Coba kata kunci atau filter lain.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($materials as $material): ?>
                    <?php $state = material_state($material); ?>
                    <tr>
                        <td>
                            <div class="material-cell">
                                <img src="<?= e(material_image($material)) ?>" alt="">
                                <span>
                                    <a class="table-primary"
                                        href="<?= e(url('karyawan/materials.php', ['id' => $material['id']])) ?>">
                                        <?= e($material['name']) ?>
                                    </a>
                                    <small><?= e($material['material_code']) ?></small>
                                </span>
                            </div>
                        </td>
                        <td><?= e(category_label($material['category'])) ?></td>
                        <td>
                            <strong><?= e(format_stock($material['current_stock'])) ?></strong> <?= e($material['unit']) ?>
                        </td>
                        <td><?= status_badge($state) ?></td>
                        <td>
                            <form class="availability-check" data-stock="<?= e($material['current_stock']) ?>"
                                data-minimum="<?= e($material['minimum_stock']) ?>" data-unit="<?= e($material['unit']) ?>">
                                <label class="sr-only">Jumlah yang dibutuhkan</label>
                                <input type="number" min="0.001" step="0.001" required placeholder="Jumlah">
                                <button class="button small secondary">Cek</button>
                                <span class="availability-result" aria-live="polite"></span>
                            </form>
                        </td>
                        <td>
                            <a class="button small primary"
                                href="<?= e(url('karyawan/use-stock.php', ['id' => $material['id']])) ?>">Gunakan</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
