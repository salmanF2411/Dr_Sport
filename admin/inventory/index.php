<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['admin']);
$pageTitle = 'Inventory Bahan Baku';
$activePage = 'admin_inventory';

$search = trim((string) ($_GET['q'] ?? ''));
$category = (string) ($_GET['category'] ?? '');
$state = (string) ($_GET['state'] ?? '');
$locationFilter = trim((string) ($_GET['location'] ?? ''));

$where = ['1=1'];
$params = [];

if (($_GET['inactive'] ?? '') !== '1') {
    $where[] = 'm.is_active=1';
}
if ($search !== '') {
    $where[] = '(m.name LIKE ? OR m.material_code LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if (in_array($category, ['kain', 'tinta_printing', 'benang'], true)) {
    $where[] = 'm.category=?';
    $params[] = $category;
}
if ($locationFilter !== '') {
    $where[] = 'm.location=?';
    $params[] = $locationFilter;
}
if ($state === 'low') {
    $where[] = 'm.current_stock > 0 AND m.current_stock <= m.minimum_stock';
} elseif ($state === 'empty') {
    $where[] = 'm.current_stock <= 0';
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM materials m WHERE ' . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($total / 20));
$pageNumber = max(1, min((int) ($_GET['p'] ?? 1), $totalPages));
$offset = ($pageNumber - 1) * 20;

$stmt = db()->prepare('SELECT m.* FROM materials m WHERE ' . implode(' AND ', $where) . ' ORDER BY m.is_active DESC, m.name LIMIT 20 OFFSET ' . $offset);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$storageLocs = db()->query("SELECT DISTINCT name FROM storage_locations WHERE is_active=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$existingLocs = db()->query("SELECT DISTINCT location FROM materials WHERE location IS NOT NULL AND location <> '' ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);
$allLocations = array_values(array_unique(array_merge($storageLocs, $existingLocs)));
sort($allLocations, SORT_NATURAL | SORT_FLAG_CASE);

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="toolbar">
    <form class="filter-form" method="get" action="<?= e(url('admin/inventory/index.php')) ?>">
        <label class="search-box">
            <span>⌕</span>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau kode bahan">
        </label>
        <select name="category">
            <option value="">Semua kategori</option>
            <?php foreach (['kain', 'tinta_printing', 'benang'] as $val): ?>
                <option value="<?= e($val) ?>" <?= ($category === $val ? ' selected' : '') ?>><?= e(category_label($val)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="location">
            <option value="">Semua rak / lokasi</option>
            <?php foreach ($allLocations as $loc): ?>
                <option value="<?= e($loc) ?>" <?= ($locationFilter === $loc ? ' selected' : '') ?>><?= e($loc) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="state">
            <option value="">Semua status</option>
            <option value="low" <?= ($state === 'low' ? ' selected' : '') ?>>Menipis</option>
            <option value="empty" <?= ($state === 'empty' ? ' selected' : '') ?>>Habis</option>
        </select>
        <label class="check-filter">
            <input type="checkbox" name="inactive" value="1" <?= (($_GET['inactive'] ?? '') === '1' ? ' checked' : '') ?>>
            Nonaktif
        </label>
        <button class="button secondary">Filter</button>
    </form>
    <div style="display:flex; gap:8px;">
        <a class="button secondary" href="<?= e(url('admin/inventory/storage-locations.php')) ?>"
            title="Kelola master rak gudang">
            <span>◧</span> Kelola rak
        </a>
        <a class="button primary" href="<?= e(url('admin/inventory/form.php')) ?>">
            <span>＋</span> Tambah bahan
        </a>
    </div>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bahan</th>
                    <th>Kategori</th>
                    <th>Lokasi Rak</th>
                    <th>Saldo</th>
                    <th>Minimum</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$materials): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state"><strong>Tidak ada bahan ditemukan</strong>
                                <p>Coba ubah kata kunci atau filter.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($materials as $material): ?>
                    <?php
                    $status = material_state($material);
                    $locDisplay = $material['location'] ? '<a href="' . e(url('admin/inventory/index.php', ['location' => $material['location']])) . '" class="location-badge" title="Filter bahan di rak ' . e($material['location']) . '"><span class="location-badge-icon">◧</span> ' . e($material['location']) . '</a>' : '<span class="muted">—</span>';
                    ?>
                    <tr>
                        <td>
                            <div class="material-cell">
                                <img src="<?= e(material_image($material)) ?>" alt="">
                                <span>
                                    <a class="table-primary"
                                        href="<?= e(url('admin/inventory/detail.php', ['id' => $material['id']])) ?>">
                                        <?= e($material['name']) ?>
                                    </a>
                                    <small><?= e($material['material_code']) ?></small>
                                </span>
                            </div>
                        </td>
                        <td><?= e(category_label($material['category'])) ?></td>
                        <td><?= $locDisplay ?></td>
                        <td>
                            <strong><?= e(format_stock($material['current_stock'])) ?></strong>
                            <span class="muted"><?= e($material['unit']) ?></span>
                        </td>
                        <td><?= e(format_stock($material['minimum_stock'])) ?>     <?= e($material['unit']) ?></td>
                        <td><?= status_badge($status) ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="button small secondary"
                                    href="<?= e(url('admin/inventory/detail.php', ['id' => $material['id']])) ?>">Detail</a>
                                <a class="button small secondary"
                                    href="<?= e(url('admin/inventory/form.php', ['id' => $material['id']])) ?>">Edit</a>
                                <?php if ((int) $material['is_active']): ?>
                                    <a class="button small subtle"
                                        href="<?= e(url('admin/inventory/stock.php', ['id' => $material['id']])) ?>">＋ Stok</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $filters = ['q' => $search, 'category' => $category, 'state' => $state];
    if ($locationFilter !== '') {
        $filters['location'] = $locationFilter;
    }
    if (($_GET['inactive'] ?? '') === '1') {
        $filters['inactive'] = '1';
    }
    ?>
    <div class="table-foot table-foot-paged">
        Menampilkan <?= count($materials) ?> dari <?= $total ?> bahan
        <?= pagination_links('admin/inventory/index.php', $pageNumber, $totalPages, $filters) ?>
    </div>
</section>

<?php
require_once __DIR__ . '/../../includes/footer.php';
