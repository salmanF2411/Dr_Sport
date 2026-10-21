<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Escape string untuk output HTML yang aman dari XSS.
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka stok tanpa angka nol desimal berlebih (misal 10 atau 1.5).
 */
function format_stock($value): string
{
    return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
}

/**
 * Simpan pesan flash ke sesi.
 */
function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Pemetaan rute nama ke file fisik berdasarkan role pengguna.
 */
function resolve_route(string $target, ?string $role = null): string
{
    if ($role === null && isset($_SESSION['user']['role'])) {
        $role = (string) $_SESSION['user']['role'];
    }

    $routeMap = [
        'dashboard' => [
            'super_admin' => 'superadmin/dashboard.php',
            'admin' => 'admin/dashboard.php',
            'karyawan' => 'karyawan/dashboard.php',
        ],
        'login' => 'login.php',
        'logout' => 'logout.php',
        'profile' => 'profile.php',

        // Pesanan (Orders)
        'orders' => [
            'super_admin' => 'superadmin/orders/index.php',
            'admin' => 'admin/orders/index.php',
            'karyawan' => 'karyawan/orders.php',
        ],
        'order' => [
            'super_admin' => 'superadmin/orders/detail.php',
            'admin' => 'admin/orders/detail.php',
            'karyawan' => 'karyawan/orders.php',
        ],
        'order-form' => 'admin/orders/form.php',
        'order-edit' => 'admin/orders/form.php',

        // Inventory
        'inventory' => [
            'super_admin' => 'superadmin/inventory/index.php',
            'admin' => 'admin/inventory/index.php',
            'karyawan' => 'karyawan/availability.php',
        ],
        'material' => [
            'super_admin' => 'superadmin/inventory/detail.php',
            'admin' => 'admin/inventory/detail.php',
            'karyawan' => 'karyawan/materials.php',
        ],
        'material-form' => 'admin/inventory/form.php',
        'stock' => 'admin/inventory/stock.php',
        'storage-locations' => 'admin/inventory/storage-locations.php',
        'history' => [
            'super_admin' => 'superadmin/inventory/history.php',
            'admin' => 'admin/inventory/history.php',
            'karyawan' => 'karyawan/usage-history.php',
        ],
        'history-detail' => [
            'super_admin' => 'superadmin/inventory/history-detail.php',
            'admin' => 'admin/inventory/history-detail.php',
            'karyawan' => 'karyawan/history-detail.php',
        ],

        // Karyawan spesifik
        'availability' => 'karyawan/availability.php',
        'use-stock' => 'karyawan/use-stock.php',
        'usage-history' => 'karyawan/usage-history.php',

        // Super Admin spesifik
        'users' => 'superadmin/users/index.php',
        'user-form' => 'superadmin/users/form.php',
        'reports' => [
            'super_admin' => 'superadmin/reports/index.php',
            'admin' => 'admin/reports/index.php',
        ],
        'logs' => 'superadmin/activity-logs/index.php',
    ];

    if (isset($routeMap[$target])) {
        $val = $routeMap[$target];
        if (is_array($val)) {
            return $val[$role ?? 'admin'] ?? reset($val);
        }
        return $val;
    }

    return $target;
}

/**
 * Menghasilkan URL lengkap ke suatu rute atau file dalam aplikasi.
 */
function url(string $target, array $query = []): string
{
    $resolved = resolve_route($target);
    $url = base_url($resolved);
    if (!empty($query)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }
    return $url;
}

/**
 * Mengarahkan pengguna (redirect) ke rute atau URL tertentu.
 */
function redirect(string $target, array $query = []): void
{
    if (strpos($target, 'http://') === 0 || strpos($target, 'https://') === 0 || strpos($target, '/') === 0) {
        $dest = $target . (!empty($query) ? (strpos($target, '?') === false ? '?' : '&') . http_build_query($query) : '');
    } else {
        $dest = url($target, $query);
    }
    header('Location: ' . $dest);
    exit;
}

/**
 * Mencatat aktivitas sistem ke tabel activity_logs.
 */
function log_activity(?int $userId, string $action, string $description, ?string $entity = null, ?int $entityId = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $stmt->execute([$userId, $action, $entity, $entityId, $description, $ip]);
    } catch (Throwable $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

/**
 * Deteksi error duplikasi kunci SQL (MySQL 1062).
 */
function is_duplicate_key_error(PDOException $error): bool
{
    return is_array($error->errorInfo) && isset($error->errorInfo[1]) &&
        (int) $error->errorInfo[1] === 1062;
}

/**
 * Label kategori bahan.
 */
function category_label(string $category): string
{
    $labels = [
        'kain' => 'Kain',
        'tinta_printing' => 'Tinta printing',
        'benang' => 'Benang'
    ];
    return $labels[$category] ?? 'Bahan';
}

/**
 * Kelas CSS status.
 */
function status_class(string $status): string
{
    if (in_array($status, ['Selesai', 'active', 'in', 'Tersedia'], true)) {
        return 'success';
    }
    if (in_array($status, ['Dibatalkan', 'inactive', 'out', 'Habis'], true)) {
        return 'danger';
    }
    if (in_array($status, ['Menipis', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC'], true)) {
        return 'warning';
    }
    return 'info';
}

/**
 * Badge status HTML.
 */
function status_badge(string $status): string
{
    return '<span class="badge badge-' . e(status_class($status)) . '">' . e($status) . '</span>';
}

/**
 * Kartu statistik ringkasan dashboard.
 */
function stat_card(string $label, string $value, string $hint, string $tone = ''): void
{
    echo '<article class="stat-card"><div class="stat-top"><span>' . e($label) . '</span><span class="stat-mark ' . e($tone) . '"></span></div><strong>' . e($value) . '</strong><small>' . e($hint) . '</small></article>';
}

/**
 * Tautan navigasi paginasi.
 */
function pagination_links(string $target, int $current, int $totalPages, array $filters): string
{
    if ($totalPages < 2) {
        return '';
    }
    $previous = max(1, $current - 1);
    $next = min($totalPages, $current + 1);
    $previousUrl = url($target, array_merge($filters, ['p' => $previous]));
    $nextUrl = url($target, array_merge($filters, ['p' => $next]));

    return '<nav class="pagination" aria-label="Navigasi halaman">' .
        '<a class="button small secondary' . ($current === 1 ? ' disabled' : '') . '" href="' . e($previousUrl) . '">← Sebelumnya</a>' .
        '<span>Halaman ' . $current . ' dari ' . $totalPages . '</span>' .
        '<a class="button small secondary' . ($current === $totalPages ? ' disabled' : '') . '" href="' . e($nextUrl) . '">Berikutnya →</a>' .
        '</nav>';
}

/**
 * Memeriksa apakah transaksi stok bernilai positif/menambah stok.
 */
function transaction_is_in(string $type): bool
{
    return in_array($type, ['in', 'adjustment_in', 'return'], true);
}

/**
 * Label ramah untuk jenis transaksi mutasi inventory.
 */
function transaction_label(string $type): string
{
    $labels = [
        'in' => 'Masuk',
        'out' => 'Keluar',
        'adjustment_in' => 'Penyesuaian +',
        'adjustment_out' => 'Penyesuaian −',
        'return' => 'Pengembalian'
    ];
    return $labels[$type] ?? $type;
}

/**
 * Format tanggal Indonesia lengkap.
 */
function indonesian_date(string $format, ?int $timestamp = null): string
{
    $map = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April',
        'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus',
        'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember',
        'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'Jun' => 'Jun',
        'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des',
    ];
    return strtr(date($format, $timestamp ?? time()), $map);
}

/**
 * Menghitung status ketersediaan bahan berdasarkan saldo dan batas minimum.
 */
function material_state(array $material): string
{
    $stock = (float) $material['current_stock'];
    if ($stock <= 0) {
        return 'Habis';
    }
    if ($stock <= (float) $material['minimum_stock']) {
        return 'Menipis';
    }
    return 'Tersedia';
}

/**
 * Mendapatkan URL gambar bahan atau placeholder kategori fallback.
 */
function material_image(array $material): string
{
    $path = isset($material['image_path']) ? (string) $material['image_path'] : '';
    $rootDir = dirname(__DIR__);

    if ($path !== '' && preg_match('#^uploads/materials/[a-f0-9]{40}\.(jpg|jpeg|png|webp|gif|bmp|tiff|tif|svg|heic|heif|avif|ico)$#i', $path) && is_file($rootDir . '/' . $path)) {
        return base_url($path);
    }

    $fallbacks = [
        'kain' => 'assets/images/materials/default-kain.svg',
        'tinta_printing' => 'assets/images/materials/default-tinta.svg',
        'benang' => 'assets/images/materials/default-benang.svg',
    ];

    $fallbackPath = $fallbacks[$material['category']] ?? 'assets/images/materials/default-material.svg';
    return base_url($fallbackPath);
}

/**
 * Validasi tanggal format Y-m-d.
 */
function valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

/**
 * Validasi jumlah stok angka positif dengan maksimal 3 angka desimal.
 */
function valid_stock_amount(string $value, bool $allowZero): bool
{
    if (!preg_match('/^\d{1,11}(?:\.\d{1,3})?$/', $value)) {
        return false;
    }
    $amount = (float) $value;
    return is_finite($amount) && ($allowZero ? $amount >= 0 : $amount > 0);
}

/**
 * Proses aman unggah foto bahan baku (JPG, PNG, WEBP, GIF, BMP, TIFF, SVG, HEIC, AVIF, ICO · Maks 2MB).
 */
function upload_material_image(): ?string
{
    if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Foto gagal diunggah atau ukurannya melebihi 2 MB.');
    }
    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/x-bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/tiff' => 'tiff',
        'image/svg+xml' => 'svg',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/avif' => 'avif',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tiff', 'tif', 'svg', 'heic', 'heif', 'avif', 'ico'];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Format foto tidak didukung. Format yang diizinkan: JPG, PNG, WEBP, GIF, BMP, TIFF, SVG, HEIC, HEIF, AVIF, ICO.');
    }

    if (!class_exists('finfo')) {
        throw new RuntimeException('Ekstensi fileinfo tidak tersedia di server.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    if (!isset($mimeToExt[$detectedMime])) {
        throw new RuntimeException('Isi file bukan gambar yang valid. MIME terdeteksi: ' . $detectedMime);
    }

    $safeExtension = $mimeToExt[$detectedMime];

    $skipDimensionCheck = in_array($safeExtension, ['svg', 'heic', 'heif', 'avif', 'ico'], true);
    if (!$skipDimensionCheck) {
        $image = @getimagesize($file['tmp_name']);
        if (!$image || $image[0] < 1 || $image[1] < 1 || $image[0] > 3000 || $image[1] > 3000) {
            throw new RuntimeException('Foto tidak valid atau dimensinya melebihi 3000 × 3000 piksel.');
        }
    }

    $rootDir = dirname(__DIR__);
    $directory = $rootDir . '/uploads/materials';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Direktori foto bahan tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(20)) . '.' . $safeExtension;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Foto bahan tidak dapat disimpan.');
    }

    return 'uploads/materials/' . $filename;
}

/**
 * Hapus file foto lama jika tidak digunakan lagi oleh bahan lain.
 */
function remove_material_image(?string $path): void
{
    if ($path && preg_match('#^uploads/materials/[a-f0-9]{40}\.(jpg|jpeg|png|webp|gif|bmp|tiff|tif|svg|heic|heif|avif|ico)$#i', $path)) {
        $reference = db()->prepare('SELECT COUNT(*) FROM materials WHERE image_path = ?');
        $reference->execute([$path]);
        if ((int) $reference->fetchColumn() > 0) {
            return;
        }
        $rootDir = dirname(__DIR__);
        $absolute = $rootDir . '/' . $path;
        if (is_file($absolute) && !unlink($absolute)) {
            error_log('Unable to remove replaced material image: ' . $path);
        }
    }
}

/**
 * Mencatat transaksi mutasi stok inventory secara atomik dengan SELECT FOR UPDATE.
 */
function record_inventory(PDO $pdo, int $materialId, string $type, float $quantity, ?int $orderId, string $reason, string $notes, int $userId): int
{
    $stmt = $pdo->prepare('SELECT id, current_stock, unit, is_active FROM materials WHERE id = ? FOR UPDATE');
    $stmt->execute([$materialId]);
    $material = $stmt->fetch();

    if (!$material || !(int) $material['is_active']) {
        throw new RuntimeException('Bahan tidak ditemukan atau sudah dinonaktifkan.');
    }

    $before = (float) $material['current_stock'];
    if (!is_finite($quantity) || $quantity <= 0 || $quantity > 99999999999.999) {
        throw new RuntimeException('Jumlah transaksi berada di luar batas yang didukung.');
    }

    $isIncoming = transaction_is_in($type);
    if (!$isIncoming && $quantity > $before) {
        throw new RuntimeException('Stok tidak mencukupi. Saldo tersedia ' . format_stock($before) . ' ' . $material['unit'] . '.');
    }

    if ($orderId) {
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND status <> 'Dibatalkan'");
        $stmt->execute([$orderId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('Pesanan terkait tidak ditemukan atau sudah dibatalkan.');
        }
    }

    $after = $isIncoming ? $before + $quantity : $before - $quantity;
    if ($after > 99999999999.999) {
        throw new RuntimeException('Saldo bahan akan melebihi kapasitas penyimpanan.');
    }

    $pdo->prepare('UPDATE materials SET current_stock = ?, updated_at = NOW() WHERE id = ?')->execute([
        number_format($after, 3, '.', ''),
        $materialId
    ]);

    $code = 'INV-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare('INSERT INTO inventory_transactions (transaction_code, material_id, transaction_type, quantity, stock_before, stock_after, order_id, reason, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        $code,
        $materialId,
        $type,
        number_format($quantity, 3, '.', ''),
        number_format($before, 3, '.', ''),
        number_format($after, 3, '.', ''),
        $orderId,
        $reason,
        $notes ?: null,
        $userId
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Tampilkan halaman pesan error terstandar.
 */
function render_error(int $code, string $message): void
{
    $cssUrl = base_url('assets/css/app.css');
    $homeUrl = base_url();
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $code . ' · DR.SPORT</title><link rel="stylesheet" href="' . e($cssUrl) . '"></head><body><main class="error-page"><div class="brand-mark">DS</div><p class="eyebrow">DR.SPORT · INVENTORY CONSOLE</p><h1>' . $code . '</h1><p>' . e($message) . '</p><a class="button primary" href="' . e($homeUrl) . '">Kembali ke aplikasi</a></main></body></html>';
}
