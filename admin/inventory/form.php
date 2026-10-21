<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['admin']);
$id = (int) ($_GET['id'] ?? 0);

$material = [
    'id' => 0,
    'material_code' => '',
    'name' => '',
    'category' => 'kain',
    'unit' => '',
    'minimum_stock' => '0',
    'location' => '',
    'notes' => '',
    'image_path' => null,
    'is_active' => 1,
];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM materials WHERE id=?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        render_error(404, 'Bahan tidak ditemukan.');
        return;
    }
    $material = $found;
}

$storageLocations = db()->query('SELECT id, code, name, description FROM storage_locations WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$currentLocation = (string) ($material['location'] ?? '');
$hasCurrent = false;
foreach ($storageLocations as $sl) {
    if ($sl['name'] === $currentLocation || $sl['code'] === $currentLocation) {
        $hasCurrent = true;
        break;
    }
}

$pageTitle = $id ? 'Edit Bahan ' . $material['material_code'] : 'Tambah Bahan Baku';
$activePage = 'admin_inventory';

require_once __DIR__ . '/../../includes/header.php';
?>
<form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" enctype="multipart/form-data" class="form-layout" data-image-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_material">
    <input type="hidden" name="id" value="<?= e($id) ?>">

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h2>Informasi bahan</h2>
                <p>Gunakan kode unik dan satuan dasar yang konsisten.</p>
            </div>
        </div>
        <div class="form-grid">
            <label class="field">
                <span>Kode bahan <b>*</b></span>
                <input name="material_code" required maxlength="30" value="<?= e($material['material_code']) ?>" placeholder="KAIN-001" style="text-transform:uppercase;">
            </label>
            <label class="field">
                <span>Nama bahan <b>*</b></span>
                <input name="name" required maxlength="120" value="<?= e($material['name']) ?>" placeholder="Kain dry-fit">
            </label>
            <label class="field">
                <span>Kategori <b>*</b></span>
                <select name="category" required>
                    <?php foreach (['kain', 'tinta_printing', 'benang'] as $cat): ?>
                        <option value="<?= e($cat) ?>"<?= ($material['category'] === $cat ? ' selected' : '') ?>><?= e(category_label($cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Satuan <b>*</b></span>
                <input name="unit" required maxlength="24" value="<?= e($material['unit']) ?>" placeholder="meter, liter, gulung">
            </label>
            <label class="field">
                <span>Minimum stok</span>
                <input type="number" name="minimum_stock" min="0" max="99999999999.999" step="0.001" value="<?= e(format_stock($material['minimum_stock'])) ?>">
            </label>
            <label class="field">
                <span>Lokasi rak penyimpanan</span>
                <select name="location">
                    <option value="">— Pilih rak penyimpanan —</option>
                    <?php if ($currentLocation !== '' && !$hasCurrent): ?>
                        <option value="<?= e($currentLocation) ?>" selected><?= e($currentLocation) ?> (Tersimpan sebelumnya)</option>
                    <?php endif; ?>
                    <?php foreach ($storageLocations as $sl): ?>
                        <?php $selected = ($currentLocation === $sl['name'] || $currentLocation === $sl['code']) ? ' selected' : ''; ?>
                        <option value="<?= e($sl['name']) ?>"<?= $selected ?>>
                            <?= e($sl['name'] . ' (' . $sl['code'] . ')') ?><?= ($sl['description'] ? ' — ' . e($sl['description']) : '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="block muted" style="margin-top:4px;">
                    Pilih rak yang tersedia atau <a href="<?= e(url('admin/inventory/storage-locations.php')) ?>" target="_blank" style="color:var(--primary); text-decoration:underline;">kelola master rak ↗</a>
                </small>
            </label>

            <?php if (!$id): ?>
                <label class="field">
                    <span>Stok awal</span>
                    <input type="number" name="initial_stock" min="0" max="99999999999.999" step="0.001" value="0">
                    <small>Dicatat otomatis sebagai transaksi stok masuk.</small>
                </label>
            <?php endif; ?>

            <label class="field field-wide">
                <span>Catatan</span>
                <textarea name="notes" rows="3" maxlength="1000" placeholder="Keterangan warna, grade, atau spesifikasi supplier"><?= e($material['notes']) ?></textarea>
            </label>

            <div class="field field-wide">
                <span>Foto bahan <small>(JPG, PNG, WEBP, GIF, BMP, TIFF, SVG, HEIC, AVIF, ICO · maks. 2 MB)</small></span>
                <div class="image-upload">
                    <img data-image-preview src="<?= e(material_image($material)) ?>" alt="Preview bahan">
                    <div>
                        <label class="button secondary file-button">
                            Pilih foto
                            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,.tiff,.tif,.svg,.heic,.heif,.avif,.ico,image/jpeg,image/png,image/webp,image/gif,image/bmp,image/tiff,image/svg+xml,image/heic,image/heif,image/avif,image/x-icon" data-image-input>
                        </label>
                        <p class="muted">Foto digunakan untuk membantu identifikasi bahan secara visual.</p>
                        <?php if ($material['image_path']): ?>
                            <label class="check-filter" style="margin-top:8px;">
                                <input type="checkbox" name="remove_image" value="1"> Hapus foto dan gunakan placeholder
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($id): ?>
                <label class="check-filter field-wide">
                    <input type="checkbox" name="active" value="1"<?= ((int) $material['is_active'] ? ' checked' : '') ?>> Bahan aktif
                </label>
            <?php endif; ?>
        </div>
    </section>

    <div class="form-actions">
        <a class="button secondary" href="<?= e(url('admin/inventory/index.php')) ?>">Batal</a>
        <button class="button primary">Simpan bahan</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../../includes/footer.php';
