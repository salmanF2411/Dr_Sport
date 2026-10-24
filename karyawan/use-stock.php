<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$materialId = (int) ($_GET['id'] ?? 0);

$materials = db()->query('SELECT id, material_code, name, current_stock, unit FROM materials WHERE is_active=1 ORDER BY name')->fetchAll();

$pageTitle = 'Pengajuan Pemakaian Bahan';
$activePage = 'karyawan_use_stock';

require_once __DIR__ . '/../includes/header.php';
?>
<form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" class="form-layout">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="employee_request_stock">

    <div class="alert alert-info" style="margin-bottom: 20px;">
        <strong>Alur Pengajuan Bahan:</strong> Karyawan mengajukan kebutuhan bahan (kain, benang, tinta, dll) dan harus menunggu persetujuan dari Admin. Saldo stok <strong>tidak akan langsung berkurang</strong> sampai Admin mengonfirmasi.
    </div>

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h2>Formulir Pengajuan Bahan</h2>
                <p>Isi rincian bahan yang dibutuhkan untuk pengerjaan pesanan atau keperluan workshop.</p>
            </div>
        </div>
        <div class="form-grid">
            <label class="field field-wide">
                <span>Bahan yang dibutuhkan <b>*</b></span>
                <select name="material_id" required>
                    <option value="">Pilih bahan yang ingin diajukan</option>
                    <?php foreach ($materials as $mat): ?>
                        <option value="<?= e($mat['id']) ?>"<?= ($materialId === (int) $mat['id'] ? ' selected' : '') ?>>
                            <?= e($mat['material_code'] . ' · ' . $mat['name'] . ' — Saldo tersedia: ' . format_stock($mat['current_stock']) . ' ' . $mat['unit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Jumlah yang diajukan <b>*</b></span>
                <input type="number" name="quantity" min="0.001" max="99999999999.999" step="0.001" required placeholder="Contoh: 5">
                <small>Masukkan jumlah dalam satuan bahan yang dipilih (misal: meter, pcs, kg).</small>
            </label>
            <label class="field">
                <span>Keperluan / Alasan Pengajuan <b>*</b></span>
                <input name="reason" maxlength="180" required placeholder="Contoh: Produksi jersey order custom 20 pcs">
            </label>
            <label class="field field-wide">
                <span>Catatan tambahan</span>
                <textarea name="notes" rows="3" maxlength="1000" placeholder="Opsional (catatan pengerjaan, spesifikasi bahan, atau nomor pesanan terkait)"></textarea>
            </label>
        </div>
    </section>

    <div class="form-actions">
        <a class="button secondary" href="<?= e(url('karyawan/availability.php')) ?>">Batal</a>
        <button class="button primary">Kirim Pengajuan ke Admin ↗</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../includes/footer.php';
