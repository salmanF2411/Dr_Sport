<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['admin']);
$materialId = (int) ($_GET['id'] ?? 0);

$materials = db()->query('SELECT id,material_code,name,current_stock,unit FROM materials WHERE is_active=1 ORDER BY name')->fetchAll();
$orders = db()->query("SELECT id,order_code FROM orders WHERE status NOT IN ('Selesai','Dibatalkan') ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Catat Mutasi Stok';
$activePage = 'admin_inventory';

require_once __DIR__ . '/../../includes/header.php';
?>
<form method="post" action="<?= e(base_url('actions/inventory-actions.php')) ?>" class="form-layout">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="inventory_move">

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h2>Mutasi inventory</h2>
                <p>Saldo akan divalidasi ulang dengan transaksi database saat disimpan.</p>
            </div>
        </div>
        <div class="form-grid">
            <label class="field field-wide">
                <span>Bahan <b>*</b></span>
                <select name="material_id" required>
                    <option value="">Pilih bahan</option>
                    <?php foreach ($materials as $mat): ?>
                        <option value="<?= e($mat['id']) ?>"<?= ($materialId === (int) $mat['id'] ? ' selected' : '') ?>>
                            <?= e($mat['material_code'] . ' · ' . $mat['name'] . ' — tersedia ' . format_stock($mat['current_stock']) . ' ' . $mat['unit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Jenis transaksi <b>*</b></span>
                <select name="transaction_type" required>
                    <option value="in">Stok masuk (pembelian/restock)</option>
                    <option value="out">Stok keluar / pemakaian</option>
                    <option value="adjustment_in">Penyesuaian stok + (koreksi bertambah)</option>
                    <option value="adjustment_out">Penyesuaian stok − (koreksi berkurang)</option>
                </select>
            </label>
            <label class="field">
                <span>Jumlah <b>*</b></span>
                <input type="number" name="quantity" min="0.001" step="0.001" required placeholder="0">
            </label>
            <label class="field">
                <span>Pesanan terkait</span>
                <select name="order_id">
                    <option value="">Tidak terkait</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?= e($order['id']) ?>"><?= e($order['order_code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Tanggal</span>
                <input type="date" value="<?= e(date('Y-m-d')) ?>" disabled>
                <small>Tanggal transaksi dicatat otomatis oleh server.</small>
            </label>
            <label class="field field-wide">
                <span>Alasan / keterangan <b>*</b></span>
                <input name="reason" maxlength="180" required placeholder="Contoh: Pembelian bahan supplier A / pemakaian produksi pesanan">
            </label>
            <label class="field field-wide">
                <span>Catatan tambahan</span>
                <textarea name="notes" rows="3" maxlength="1000" placeholder="Nomor faktur, batch, atau keterangan tambahan"></textarea>
            </label>
        </div>
    </section>

    <div class="form-actions">
        <a class="button secondary" href="<?= e(url('admin/inventory/index.php')) ?>">Batal</a>
        <button class="button primary">Simpan transaksi</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../../includes/footer.php';
