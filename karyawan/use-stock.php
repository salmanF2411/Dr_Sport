<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role(['karyawan']);
$materialId = (int) ($_GET['id'] ?? 0);

$materials = db()->query('SELECT id, material_code, name, current_stock, unit FROM materials WHERE is_active=1 ORDER BY name')->fetchAll();

$pageTitle = 'Catat Pemakaian Bahan';
$activePage = 'karyawan_use_stock';

require_once __DIR__ . '/../includes/header.php';
?>
<form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" class="form-layout">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="employee_stock_use">

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h2>Catat pemakaian bahan</h2>
                <p>Saldo akan diperiksa kembali saat disimpan. Pemakaian akan langsung mengurangi stok sistem.</p>
            </div>
        </div>
        <div class="form-grid">
            <label class="field field-wide">
                <span>Bahan <b>*</b></span>
                <select name="material_id" required>
                    <option value="">Pilih bahan yang digunakan</option>
                    <?php foreach ($materials as $mat): ?>
                        <option value="<?= e($mat['id']) ?>"<?= ($materialId === (int) $mat['id'] ? ' selected' : '') ?>>
                            <?= e($mat['material_code'] . ' · ' . $mat['name'] . ' — tersedia ' . format_stock($mat['current_stock']) . ' ' . $mat['unit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Jumlah dipakai <b>*</b></span>
                <input type="number" name="quantity" min="0.001" max="99999999999.999" step="0.001" required placeholder="0">
                <small>Masukkan jumlah dalam satuan bahan yang dipilih.</small>
            </label>
            <label class="field">
                <span>Keperluan / Alasan <b>*</b></span>
                <input name="reason" maxlength="180" required placeholder="Contoh: Produksi jersey order A / uji sampel warna">
            </label>
            <label class="field field-wide">
                <span>Catatan tambahan</span>
                <textarea name="notes" rows="3" maxlength="1000" placeholder="Opsional (catatan pengerjaan atau kondisi bahan)"></textarea>
            </label>
        </div>
    </section>

    <div class="form-actions">
        <a class="button secondary" href="<?= e(url('karyawan/availability.php')) ?>">Batal</a>
        <button class="button primary">Simpan pemakaian</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../includes/footer.php';
