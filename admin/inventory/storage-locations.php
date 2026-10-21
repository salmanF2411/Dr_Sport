<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['super_admin', 'admin']);
$pdo = db();

$editId = (int) ($_GET['edit'] ?? 0);
$editItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM storage_locations WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();
    if (!$editItem) {
        flash('error', 'Data rak yang akan diedit tidak ditemukan.');
        redirect('admin/inventory/storage-locations.php');
    }
}

$listStmt = $pdo->query('
    SELECT sl.*, 
           u.name AS creator_name,
           (SELECT COUNT(*) FROM materials m WHERE m.location = sl.name OR m.location = sl.code) AS material_count
    FROM storage_locations sl
    LEFT JOIN users u ON u.id = sl.created_by
    ORDER BY sl.is_active DESC, sl.name ASC
');
$locations = $listStmt->fetchAll();

$totalRacks = count($locations);
$activeRacks = 0;
$totalStoredMaterials = 0;
foreach ($locations as $l) {
    if ((int) $l['is_active']) {
        $activeRacks++;
    }
    $totalStoredMaterials += (int) $l['material_count'];
}

$pageTitle = 'Master Rak & Lokasi Penyimpanan';
$activePage = $user['role'] === 'super_admin' ? 'superadmin_storage' : 'admin_storage';

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="stat-grid" style="margin-bottom:24px;">
    <?php
    stat_card('Total rak', (string) $totalRacks, 'Master lokasi gudang');
    stat_card('Rak aktif', (string) $activeRacks, 'Dapat dipilih di form');
    stat_card('Bahan terpetakan', (string) $totalStoredMaterials, 'Item bahan di rak', 'success');
    ?>
</div>

<div class="storage-layout">
    <section class="panel form-panel" style="padding:0 20px 20px;">
        <div class="panel-heading" style="margin:0 -20px 20px; padding:18px 20px; border-bottom:1px solid var(--border);">
            <?php if ($editItem): ?>
                <div>
                    <h2 style="margin:0;">Edit Rak</h2>
                    <p style="margin:3px 0 0; font-size:12px; color:var(--muted);">Ubah informasi rak <?= e($editItem['name']) ?></p>
                </div>
                <a href="<?= e(url('admin/inventory/storage-locations.php')) ?>" class="button small secondary">＋ Tambah Baru</a>
            <?php else: ?>
                <div>
                    <h2 style="margin:0;">Tambah Rak Baru</h2>
                    <p style="margin:3px 0 0; font-size:12px; color:var(--muted);">Master rak penyimpanan untuk inventory</p>
                </div>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" class="form-layout" style="display:flex; flex-direction:column; gap:16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_storage_location">
            <input type="hidden" name="id" value="<?= e($editItem['id'] ?? 0) ?>">

            <label class="field">
                <span>Kode Rak <b>*</b></span>
                <input name="code" required maxlength="40" placeholder="Contoh: RAK-A, LEM-01" value="<?= e($editItem['code'] ?? '') ?>" style="text-transform:uppercase;">
                <small class="muted">Gunakan huruf kapital, angka, tanda strip (unik).</small>
            </label>

            <label class="field">
                <span>Nama Rak / Area <b>*</b></span>
                <input name="name" required maxlength="120" placeholder="Contoh: Rak Kain Baris A" value="<?= e($editItem['name'] ?? '') ?>">
                <small class="muted">Nama yang akan tampil pada pilihan dropdown bahan.</small>
            </label>

            <label class="field">
                <span>Deskripsi / Keterangan</span>
                <textarea name="description" rows="3" maxlength="255" placeholder="Contoh: Lantai 1 sisi timur, khusus bahan dry-fit"><?= e($editItem['description'] ?? '') ?></textarea>
            </label>

            <?php if ($editItem): ?>
                <label class="check-filter">
                    <input type="checkbox" name="is_active" value="1"<?= ((int) $editItem['is_active'] ? ' checked' : '') ?>> Rak aktif (dapat dipilih pada form)
                </label>
            <?php endif; ?>

            <div class="form-actions" style="margin-top:8px; display:flex; gap:10px;">
                <button class="button primary" style="flex:1;"><?= ($editItem ? 'Simpan Perubahan' : 'Tambahkan Rak') ?></button>
                <?php if ($editItem): ?>
                    <a href="<?= e(url('admin/inventory/storage-locations.php')) ?>" class="button secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Daftar Rak Gudang & Penyimpanan</h2>
                <p>Rak yang terdaftar dapat dipilih saat menginput atau mengedit bahan di inventory.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Rak & Keterangan</th>
                        <th>Bahan Tersimpan</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$locations): ?>
                        <tr>
                            <td colspan="5"><div class="empty-state"><strong>Belum ada rak penyimpanan</strong><p>Tambahkan rak pertama menggunakan formulir di sebelah kiri.</p></div></td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($locations as $loc): ?>
                        <?php
                        $isActive = (int) $loc['is_active'];
                        $count = (int) $loc['material_count'];
                        $isCurrentlyEditing = $editItem && (int) $editItem['id'] === (int) $loc['id'];
                        $inventoryUrl = $user['role'] === 'super_admin' ? 'superadmin/inventory/index.php' : 'admin/inventory/index.php';
                        ?>
                        <tr<?= ($isCurrentlyEditing ? ' style="background:var(--soft-blue);"' : '') ?>>
                            <td>
                                <span class="location-badge">
                                    <span class="location-badge-icon">◧</span>
                                    <strong><?= e($loc['code']) ?></strong>
                                </span>
                            </td>
                            <td>
                                <strong><?= e($loc['name']) ?></strong>
                                <?php if ($loc['description']): ?>
                                    <small class="block muted"><?= e($loc['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($count > 0): ?>
                                    <a href="<?= e(url($inventoryUrl, ['location' => $loc['name']])) ?>" class="table-primary" title="Lihat bahan di rak ini">
                                        <strong><?= e($count) ?> bahan</strong> ↗
                                    </a>
                                <?php else: ?>
                                    <span class="muted">0 bahan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= ($isActive ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-dimmed">Nonaktif</span>') ?>
                            </td>
                            <td>
                                <div class="row-actions" style="justify-content:flex-end;">
                                    <a href="<?= e(url('admin/inventory/storage-locations.php', ['edit' => $loc['id']])) ?>" class="button small secondary">Edit</a>
                                    <form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" class="inline-form" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_storage_location">
                                        <input type="hidden" name="id" value="<?= e($loc['id']) ?>">
                                        <button class="button small subtle" title="<?= ($isActive ? 'Nonaktifkan rak' : 'Aktifkan rak') ?>">
                                            <?= ($isActive ? 'Nonaktifkan' : 'Aktifkan') ?>
                                        </button>
                                    </form>
                                    <?php if ($count === 0): ?>
                                        <form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" class="inline-form confirm-form" data-confirm="Hapus rak <?= e($loc['name']) ?>?" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_storage_location">
                                            <input type="hidden" name="id" value="<?= e($loc['id']) ?>">
                                            <button class="button small subtle" style="color:var(--danger);" title="Hapus rak">Hapus</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="button small subtle disabled" title="Rak tidak dapat dihapus karena masih berisi bahan" disabled style="opacity:0.4; cursor:not-allowed;">Hapus</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">Total <?= $totalRacks ?> rak · Rak yang masih memiliki bahan tersimpan tidak dapat dihapus untuk menjaga integritas data</div>
    </section>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
