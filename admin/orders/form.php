<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['admin']);
$id = (int) ($_GET['id'] ?? 0);

$order = [
    'id' => 0,
    'customer_name' => '',
    'phone' => '',
    'address' => '',
    'order_date' => date('Y-m-d'),
    'target_date' => '',
    'quantity' => '',
    'jersey_description' => '',
    'notes' => '',
    'order_code' => '',
];

if ($id) {
    $stmt = db()->prepare('SELECT o.*, c.name AS customer_name, c.phone, c.address FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.id=?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        render_error(404, 'Pesanan tidak ditemukan.');
        return;
    }
    $order = $found;
}

$pageTitle = $id ? 'Edit Pesanan ' . $order['order_code'] : 'Tambah Pesanan Jersey';
$activePage = 'admin_orders';

require_once __DIR__ . '/../../includes/header.php';
?>
<form method="post" action="<?= e(base_url('actions/order-actions.php')) ?>" class="form-layout">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_order">
    <input type="hidden" name="id" value="<?= e($id) ?>">

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h2>Data pesanan</h2>
                <p><?= ($id ? 'Memperbarui ' . e($order['order_code']) . ' tanpa mengubah histori status.' : 'Kode pesanan dibuat otomatis setelah disimpan.') ?></p>
            </div>
        </div>
        <div class="form-grid">
            <label class="field">
                <span>Nama pelanggan <b>*</b></span>
                <input name="customer_name" required maxlength="120" value="<?= e($order['customer_name']) ?>" placeholder="Nama pemesan">
            </label>
            <label class="field">
                <span>Nomor kontak <b>*</b></span>
                <input name="customer_phone" required maxlength="40" value="<?= e($order['phone']) ?>" placeholder="08xxxxxxxxxx">
            </label>
            <label class="field">
                <span>Tanggal pesanan <b>*</b></span>
                <input type="date" name="order_date" required value="<?= e($order['order_date']) ?>">
            </label>
            <label class="field">
                <span>Target selesai</span>
                <input type="date" name="target_date" value="<?= e($order['target_date']) ?>">
            </label>
            <label class="field">
                <span>Jumlah jersey <b>*</b></span>
                <input type="number" name="quantity" min="1" max="4294967295" step="1" required value="<?= e($order['quantity']) ?>" placeholder="10">
            </label>
            <label class="field">
                <span>Alamat pelanggan</span>
                <input name="address" maxlength="255" value="<?= e($order['address']) ?>" placeholder="Alamat pengiriman / workshop">
            </label>
            <label class="field field-wide">
                <span>Jenis/model & kebutuhan jersey <b>*</b></span>
                <textarea name="jersey_description" rows="4" maxlength="2000" required placeholder="Contoh: Jersey futsal, lengan pendek, sublimasi full-print, kerah V-neck"><?= e($order['jersey_description']) ?></textarea>
            </label>
            <label class="field field-wide">
                <span>Catatan internal</span>
                <textarea name="notes" rows="3" maxlength="1000" placeholder="Catatan khusus untuk pengerjaan"><?= e($order['notes']) ?></textarea>
            </label>
        </div>
    </section>

    <div class="form-actions">
        <a class="button secondary" href="<?= e($id ? url('admin/orders/detail.php', ['id' => $id]) : url('admin/orders/index.php')) ?>">Batal</a>
        <button class="button primary">Simpan pesanan</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../../includes/footer.php';
