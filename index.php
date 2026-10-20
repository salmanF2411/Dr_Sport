<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

date_default_timezone_set('Asia/Jakarta');
if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $config = require __DIR__ . '/config/database.local.php';
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['database']);
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_stock($value): string
{
    return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
}

function valid_password(string $password): bool
{
    return strlen($password) >= 10 && strlen($password) <= 72 &&
        preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
}

function is_duplicate_key_error(PDOException $error): bool
{
    return is_array($error->errorInfo) && isset($error->errorInfo[1]) &&
        (int) $error->errorInfo[1] === 1062;
}

function url(string $page, array $query = []): string
{
    return '?' . http_build_query(array_merge(['page' => $page], $query));
}

function redirect(string $page, array $query = []): void
{
    header('Location: ' . url($page, $query));
    exit;
}

function current_user(): ?array
{
    if (!isset($_SESSION['user'])) {
        return null;
    }
    $stmt = db()->prepare("SELECT id, name, username, role, status, password_changed_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $_SESSION['user']['id']]);
    $account = $stmt->fetch();
    if (!$account || $account['status'] !== 'active') {
        unset($_SESSION['user']);
        return null;
    }
    $_SESSION['user'] = [
        'id' => (int) $account['id'],
        'name' => $account['name'],
        'username' => $account['username'],
        'role' => $account['role'],
        'must_change_password' => $account['password_changed_at'] === null,
    ];
    return $_SESSION['user'];
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login');
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        log_activity((int) $user['id'], 'access_denied', 'Akses ditolak untuk tindakan pengguna.');
        http_response_code(403);
        render_error(403, 'Anda tidak memiliki izin untuk membuka halaman ini.');
        exit;
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!$token || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        render_error(419, 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman dan coba kembali.');
        exit;
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function log_activity(?int $userId, string $action, string $description, ?string $entity = null, ?int $entityId = null): void
{
    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entity, $entityId, $description, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
}

function category_label(string $category): string
{
    $labels = ['kain' => 'Kain', 'tinta_printing' => 'Tinta printing', 'benang' => 'Benang'];
    return $labels[$category] ?? 'Bahan';
}

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

function transaction_is_in(string $type): bool
{
    return in_array($type, ['in', 'adjustment_in', 'return'], true);
}

function transaction_label(string $type): string
{
    $labels = ['in' => 'Masuk', 'out' => 'Keluar', 'adjustment_in' => 'Penyesuaian +', 'adjustment_out' => 'Penyesuaian −', 'return' => 'Pengembalian'];
    return $labels[$type] ?? $type;
}

function indonesian_date(string $format, ?int $timestamp = null): string
{
    $map = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis',
        'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April',
        'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus',
        'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember',
        'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'Jun' => 'Jun',
        'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des',
    ];
    return strtr(date($format, $timestamp ?? time()), $map);
}

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

function material_image(array $material): string
{
    $path = isset($material['image_path']) ? (string) $material['image_path'] : '';
    if ($path !== '' && preg_match('#^uploads/materials/[a-f0-9]{40}\.(jpg|jpeg|png|webp|gif|bmp|tiff|tif|svg|heic|heif|avif|ico)$#i', $path) && is_file(__DIR__ . '/' . $path)) {
        return $path;
    }
    $fallbacks = [
        'kain' => 'assets/images/materials/default-kain.svg',
        'tinta_printing' => 'assets/images/materials/default-tinta.svg',
        'benang' => 'assets/images/materials/default-benang.svg',
    ];
    return $fallbacks[$material['category']] ?? 'assets/images/materials/default-material.svg';
}

function render_error(int $code, string $message): void
{
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $code . ' · DR.SPORT</title><link rel="stylesheet" href="assets/css/app.css"><main class="error-page"><div class="brand-mark">DS</div><p class="eyebrow">DR.SPORT · INVENTORY CONSOLE</p><h1>' . $code . '</h1><p>' . e($message) . '</p><a class="button primary" href="' . e(url('dashboard')) . '">Kembali ke aplikasi</a></main></html>';
}

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

    // Daftar semua MIME type gambar yang diizinkan → ekstensi yang digunakan untuk menyimpan
    $mimeToExt = [
        'image/jpeg'                => 'jpg',
        'image/pjpeg'               => 'jpg',   // progressive JPEG (IE / kamera lama)
        'image/png'                 => 'png',
        'image/x-png'               => 'png',   // varian non-standar
        'image/webp'                => 'webp',
        'image/gif'                 => 'gif',
        'image/bmp'                 => 'bmp',
        'image/x-bmp'               => 'bmp',
        'image/x-ms-bmp'            => 'bmp',
        'image/tiff'                => 'tiff',
        'image/svg+xml'             => 'svg',
        'image/heic'                => 'heic',
        'image/heif'                => 'heif',
        'image/avif'                => 'avif',
        'image/x-icon'              => 'ico',
        'image/vnd.microsoft.icon'  => 'ico',
    ];

    // Daftar ekstensi file yang diizinkan (untuk filter awal)
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tiff', 'tif', 'svg', 'heic', 'heif', 'avif', 'ico'];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Format foto tidak didukung. Format yang diizinkan: JPG, PNG, WEBP, GIF, BMP, TIFF, SVG, HEIC, HEIF, AVIF, ICO.');
    }

    // Deteksi MIME type dari isi file
    if (!class_exists('finfo')) {
        throw new RuntimeException('Ekstensi fileinfo tidak tersedia di server.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    if (!isset($mimeToExt[$detectedMime])) {
        throw new RuntimeException('Isi file bukan gambar yang valid. MIME terdeteksi: ' . $detectedMime);
    }

    // Gunakan ekstensi berdasarkan isi file yang sebenarnya (bukan dari nama file)
    $safeExtension = $mimeToExt[$detectedMime];

    $skipDimensionCheck = in_array($safeExtension, ['svg', 'heic', 'heif', 'avif', 'ico'], true);
    if (!$skipDimensionCheck) {
        $image = @getimagesize($file['tmp_name']);
        if (!$image || $image[0] < 1 || $image[1] < 1 || $image[0] > 3000 || $image[1] > 3000) {
            throw new RuntimeException('Foto tidak valid atau dimensinya melebihi 3000 × 3000 piksel.');
        }
    }
    $directory = __DIR__ . '/uploads/materials';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Direktori foto bahan tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $safeExtension;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Foto bahan tidak dapat disimpan.');
    }
    return 'uploads/materials/' . $filename;
}

function remove_material_image(?string $path): void
{
    if ($path && preg_match('#^uploads/materials/[a-f0-9]{40}\.(jpg|jpeg|png|webp|gif|bmp|tiff|tif|svg|heic|heif|avif|ico)$#i', $path)) {
        $reference = db()->prepare('SELECT COUNT(*) FROM materials WHERE image_path = ?');
        $reference->execute([$path]);
        if ((int) $reference->fetchColumn() > 0) {
            return;
        }
        $absolute = __DIR__ . '/' . $path;
        if (is_file($absolute) && !unlink($absolute)) {
            error_log('Unable to remove replaced material image: ' . $path);
        }
    }
}

function handle_post(): void
{
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $user = current_user();

    if ($action === 'login') {
        $now = time();
        $attempts = $_SESSION['login_attempts'] ?? [];
        $attempts = array_values(array_filter($attempts, function ($time) use ($now) { return $time > $now - 900; }));
        $remoteAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $ipAttempts = 0;
        if ($remoteAddress !== '') {
            $rateLimit = db()->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed' AND ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $rateLimit->execute([$remoteAddress]);
            $ipAttempts = (int) $rateLimit->fetchColumn();
        }
        if (count($attempts) >= 5 || $ipAttempts >= 20) {
            flash('error', 'Terlalu banyak percobaan. Silakan coba kembali dalam 15 menit.');
            redirect('login');
        }
        $identifier = trim((string) ($_POST['username'] ?? ''));
        $stmt = db()->prepare("SELECT id, name, username, password_hash, role, status, password_changed_at FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $account = $stmt->fetch();
        if (!$account || $account['status'] !== 'active' || !password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
            $attempts[] = $now;
            $_SESSION['login_attempts'] = $attempts;
            log_activity($account ? (int) $account['id'] : null, 'login_failed', 'Percobaan login gagal.');
            flash('error', 'Username/email atau password tidak valid.');
            redirect('login');
        }
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        unset($_SESSION['login_attempts']);
        $_SESSION['user'] = [
            'id' => (int) $account['id'],
            'name' => $account['name'],
            'username' => $account['username'],
            'role' => $account['role'],
            'must_change_password' => $account['password_changed_at'] === null,
        ];
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$account['id']]);
        log_activity((int) $account['id'], 'login', 'Pengguna masuk ke sistem.');
        redirect($account['password_changed_at'] === null ? 'profile' : 'dashboard');
    }

    if ($action === 'logout') {
        if ($user) {
            try {
                log_activity((int) $user['id'], 'logout', 'Pengguna keluar dari sistem.');
            } catch (Throwable $error) {
                error_log('Unable to write logout activity log: ' . $error->getMessage());
            }
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('login');
    }

    if (!$user) {
        redirect('login');
    }
    if (!empty($user['must_change_password']) && $action !== 'change_password') {
        flash('error', 'Demi keamanan, ubah password sementara sebelum melanjutkan.');
        redirect('profile');
    }
    $actorId = (int) $user['id'];

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['new_password'] ?? '');
        if (!valid_password($password)) {
            throw new RuntimeException('Password baru harus 10–72 karakter dan mengandung huruf serta angka.');
        }
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$actorId]);
        if (!password_verify($current, (string) $stmt->fetchColumn())) {
            throw new RuntimeException('Password saat ini tidak sesuai.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $actorId]);
            log_activity($actorId, 'password_changed', 'Pengguna mengganti password.');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        flash('success', 'Password berhasil diperbarui.');
        redirect('profile');
    }

    if ($action === 'save_user') {
        require_role(['super_admin']);
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        if ($name === '' || strlen($name) > 120 || !preg_match('/^[a-zA-Z0-9._]{3,40}$/', $username) || !in_array($role, ['admin', 'karyawan'], true)) {
            throw new RuntimeException('Nama, username (3–40 karakter), dan role wajib diisi dengan benar.');
        }
        if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('Format email tidak valid.');
        }
        $password = (string) ($_POST['password'] ?? '');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('admin','karyawan')");
                $stmt->execute([$id]);
                if (!$stmt->fetch()) {
                    throw new RuntimeException('Akun yang dipilih tidak ditemukan atau tidak dapat diedit.');
                }
                $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ?, role = ?, status = ? WHERE id = ?')->execute([
                    $name, $username, $email ?: null, $role, isset($_POST['active']) ? 'active' : 'inactive', $id
                ]);
                log_activity($actorId, 'user_updated', 'Memperbarui akun ' . $username . '.', 'user', $id);
            } else {
                if (!valid_password($password)) {
                    throw new RuntimeException('Password awal harus 10–72 karakter dan mengandung huruf serta angka.');
                }
                $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password_hash, role, status, created_by) VALUES (?, ?, ?, ?, ?, 'active', ?)");
                $stmt->execute([$name, $username, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $role, $actorId]);
                log_activity($actorId, 'user_created', 'Membuat akun ' . $username . '.', 'user', (int) $pdo->lastInsertId());
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        flash('success', 'Data akun berhasil disimpan.');
        redirect('users');
    }

    if ($action === 'toggle_user') {
        require_role(['super_admin']);
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === $actorId) {
            throw new RuntimeException('Akun yang sedang digunakan tidak dapat dinonaktifkan.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if (!$target || $target['role'] === 'super_admin') {
                throw new RuntimeException('Akun Super Admin tidak dapat dinonaktifkan dari halaman ini.');
            }
            $next = $target['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$next, $id]);
            log_activity($actorId, 'user_status_changed', 'Status akun diubah menjadi ' . $next . '.', 'user', $id);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        flash('success', 'Status akun diperbarui.');
        redirect('users');
    }

    if ($action === 'reset_user_password') {
        require_role(['super_admin']);
        $id = (int) ($_POST['id'] ?? 0);
        $temporary = bin2hex(random_bytes(6)) . 'A1';
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, password_changed_at = NULL WHERE id = ? AND role IN ('admin','karyawan')");
            $stmt->execute([password_hash($temporary, PASSWORD_DEFAULT), $id]);
            if (!$stmt->rowCount()) {
                throw new RuntimeException('Akun yang dipilih tidak ditemukan atau tidak dapat direset.');
            }
            log_activity($actorId, 'user_password_reset', 'Password akun direset oleh Super Admin.', 'user', $id);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        $_SESSION['temporary_password'] = $temporary;
        flash('success', 'Password sementara dibuat. Salin sekarang dan berikan secara langsung kepada pengguna.');
        redirect('users');
    }

    if ($action === 'save_material') {
        require_role(['admin']);
        $id = (int) ($_POST['id'] ?? 0);
        $code = strtoupper(trim((string) ($_POST['material_code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = (string) ($_POST['category'] ?? '');
        $unit = trim((string) ($_POST['unit'] ?? ''));
        $minimum = trim((string) ($_POST['minimum_stock'] ?? '0'));
        $location = trim((string) ($_POST['location'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (!preg_match('/^[A-Z0-9-]{2,30}$/', $code) || $name === '' || strlen($name) > 120 || $unit === '' || strlen($unit) > 24 || strlen($location) > 120 || strlen($notes) > 1000 || !in_array($category, ['kain', 'tinta_printing', 'benang'], true) || !valid_stock_amount($minimum, true)) {
            throw new RuntimeException('Kode, nama, kategori, satuan, dan minimum stok wajib valid.');
        }
        $image = upload_material_image();
        $pdo = db();
        $oldImage = null;
        try {
            $pdo->beginTransaction();
            if ($id) {
                $stmt = $pdo->prepare('SELECT image_path FROM materials WHERE id = ? FOR UPDATE');
                $stmt->execute([$id]);
                $oldImage = $stmt->fetchColumn();
                if ($oldImage === false) {
                    throw new RuntimeException('Bahan tidak ditemukan.');
                }
                $clearImage = isset($_POST['remove_image']);
                $newImage = $image ?: ($clearImage ? null : $oldImage);
                $pdo->prepare('UPDATE materials SET material_code = ?, name = ?, category = ?, unit = ?, minimum_stock = ?, location = ?, notes = ?, image_path = ?, is_active = ? WHERE id = ?')->execute([
                    $code, $name, $category, $unit, $minimum, $location ?: null,
                    $notes ?: null, $newImage, isset($_POST['active']) ? 1 : 0, $id
                ]);
                if ($image || $clearImage) {
                    $oldImageToRemove = $oldImage;
                }
                log_activity($actorId, 'material_updated', 'Memperbarui bahan ' . $code . '.', 'material', $id);
            } else {
                $pdo->prepare('INSERT INTO materials (material_code, name, category, unit, minimum_stock, location, notes, image_path, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')->execute([
                    $code, $name, $category, $unit, $minimum, $location ?: null,
                    $notes ?: null, $image, $actorId
                ]);
                $id = (int) $pdo->lastInsertId();
                $initial = trim((string) ($_POST['initial_stock'] ?? '0'));
                if (!valid_stock_amount($initial, true)) {
                    throw new RuntimeException('Stok awal harus bernilai nol atau lebih.');
                }
                if ((float) $initial > 0) {
                    record_inventory($pdo, $id, 'in', (float) $initial, null, 'Stok awal', 'Pencatatan stok awal bahan.', $actorId);
                }
                log_activity($actorId, 'material_created', 'Membuat bahan ' . $code . '.', 'material', $id);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($image) {
                remove_material_image($image);
            }
            throw $error;
        }
        if (!empty($oldImageToRemove)) {
            remove_material_image($oldImageToRemove);
        }
        flash('success', 'Data bahan berhasil disimpan.');
        redirect('inventory');
    }

    if ($action === 'inventory_move') {
        require_role(['admin']);
        $materialId = (int) ($_POST['material_id'] ?? 0);
        $type = (string) ($_POST['transaction_type'] ?? '');
        $quantity = trim((string) ($_POST['quantity'] ?? ''));
        $orderId = (int) ($_POST['order_id'] ?? 0) ?: null;
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (!in_array($type, ['in', 'out', 'adjustment_in', 'adjustment_out'], true) || !valid_stock_amount($quantity, false) || $reason === '' || strlen($reason) > 180 || strlen($notes) > 1000) {
            throw new RuntimeException('Jenis transaksi, jumlah positif, dan alasan wajib diisi.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $transactionId = record_inventory($pdo, $materialId, $type, (float) $quantity, $orderId, $reason, $notes, $actorId);
            log_activity($actorId, transaction_is_in($type) ? 'stock_in' : 'stock_out', $reason . ' (' . $quantity . ').', 'inventory_transaction', $transactionId);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        flash('success', 'Transaksi berhasil dicatat dan saldo stok telah diperbarui.');
        redirect('inventory');
    }

    if ($action === 'employee_stock_use') {
        require_role(['karyawan']);
        $materialId = (int) ($_POST['material_id'] ?? 0);
        $quantity = trim((string) ($_POST['quantity'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (!valid_stock_amount($quantity, false) || $reason === '' || strlen($reason) > 180 || strlen($notes) > 1000) {
            throw new RuntimeException('Jumlah pemakaian dan keperluan wajib diisi dengan benar.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $transactionId = record_inventory($pdo, $materialId, 'out', (float) $quantity, null, $reason, $notes, $actorId);
            log_activity($actorId, 'employee_stock_use', $reason . ' (' . $quantity . ').', 'inventory_transaction', $transactionId);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        flash('success', 'Pemakaian berhasil dicatat dan saldo stok telah diperbarui.');
        redirect('usage-history');
    }

    if ($action === 'save_order') {
        require_role(['admin']);
        $orderId = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['customer_name'] ?? ''));
        $phone = trim((string) ($_POST['customer_phone'] ?? ''));
        $description = trim((string) ($_POST['jersey_description'] ?? ''));
        $quantityValue = (string) ($_POST['quantity'] ?? '');
        $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);
        $orderDate = (string) ($_POST['order_date'] ?? '');
        $targetDate = trim((string) ($_POST['target_date'] ?? '')) ?: null;
        $address = trim((string) ($_POST['address'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($name === '' || strlen($name) > 120 || $phone === '' || strlen($phone) > 40 || strlen($address) > 255 || $description === '' || strlen($description) > 2000 || strlen($notes) > 1000 || $quantity === false || $quantity < 1 || $quantity > 4294967295 || !valid_date($orderDate) || ($targetDate && !valid_date($targetDate))) {
            throw new RuntimeException('Lengkapi data pelanggan, deskripsi, jumlah, dan tanggal dengan benar.');
        }
        if ($targetDate && $targetDate < $orderDate) {
            throw new RuntimeException('Target selesai tidak boleh lebih awal dari tanggal pesanan.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $customerId = 0;
            if ($orderId) {
                $stmt = $pdo->prepare('SELECT id, order_code, customer_id FROM orders WHERE id = ? FOR UPDATE');
                $stmt->execute([$orderId]);
                $existingOrder = $stmt->fetch();
                if (!$existingOrder) {
                    throw new RuntimeException('Pesanan tidak ditemukan.');
                }
                $customerId = (int) $existingOrder['customer_id'];
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
                $stmt->execute([$customerId]);
                if ((int) $stmt->fetchColumn() > 1) {
                    $customerId = 0;
                }
            } else {
                $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = ? AND name = ? LIMIT 1');
                $stmt->execute([$phone, $name]);
                $customerId = (int) $stmt->fetchColumn();
            }
            if ($customerId > 0) {
                $pdo->prepare('UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?')->execute([
                    $name, $phone, $address ?: null, $customerId
                ]);
            } else {
                $pdo->prepare('INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)')->execute([$name, $phone, $address ?: null]);
                $customerId = (int) $pdo->lastInsertId();
            }
            $notes = $notes ?: null;
            if ($orderId) {
                $pdo->prepare('UPDATE orders SET customer_id = ?, order_date = ?, target_date = ?, jersey_description = ?, quantity = ?, notes = ? WHERE id = ?')->execute([
                    $customerId, $orderDate, $targetDate, $description, $quantity, $notes, $orderId
                ]);
                $orderCode = (string) $existingOrder['order_code'];
                log_activity($actorId, 'order_updated', 'Memperbarui data pesanan ' . $orderCode . '.', 'order', $orderId);
            } else {
                $orderCode = 'ORD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $pdo->prepare("INSERT INTO orders (order_code, customer_id, order_date, target_date, jersey_description, quantity, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Pesanan Masuk', ?, ?)")->execute([
                    $orderCode, $customerId, $orderDate, $targetDate, $description, $quantity, $notes, $actorId
                ]);
                $orderId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by) VALUES (?, NULL, ?, ?)')->execute([$orderId, 'Pesanan Masuk', $actorId]);
                log_activity($actorId, 'order_created', 'Membuat pesanan ' . $orderCode . '.', 'order', $orderId);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        flash('success', 'Pesanan ' . $orderCode . ' berhasil ' . ($orderId && !empty($_POST['id']) ? 'diperbarui.' : 'dibuat.'));
        redirect('order', ['id' => $orderId]);
    }

    if ($action === 'update_order_status') {
        require_role(['admin']);
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $next = (string) ($_POST['status'] ?? '');
        $allowed = ['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'];
        if (!in_array($next, $allowed, true)) {
            throw new RuntimeException('Status pesanan tidak valid.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $old = $stmt->fetchColumn();
        if ($old === false) {
            $pdo->rollBack();
            throw new RuntimeException('Pesanan tidak ditemukan.');
        }
        if ($old !== $next) {
            $pdo->prepare('UPDATE orders SET status = ?, cancelled_at = ? WHERE id = ?')->execute([$next, $next === 'Dibatalkan' ? date('Y-m-d H:i:s') : null, $orderId]);
            $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_by) VALUES (?, ?, ?, ?, ?)')->execute([$orderId, $old, $next, trim((string) ($_POST['note'] ?? '')) ?: null, $actorId]);
            log_activity($actorId, 'order_status_changed', 'Status pesanan diubah dari ' . $old . ' menjadi ' . $next . '.', 'order', $orderId);
        }
        $pdo->commit();
        flash('success', 'Status pesanan diperbarui.');
        redirect('order', ['id' => $orderId]);
    }

    throw new RuntimeException('Aksi tidak dikenal.');
}

function valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function valid_stock_amount(string $value, bool $allowZero): bool
{
    if (!preg_match('/^\d{1,11}(?:\.\d{1,3})?$/', $value)) {
        return false;
    }
    $amount = (float) $value;
    return is_finite($amount) && ($allowZero ? $amount >= 0 : $amount > 0);
}

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
    $pdo->prepare('UPDATE materials SET current_stock = ?, updated_at = NOW() WHERE id = ?')->execute([number_format($after, 3, '.', ''), $materialId]);
    $code = 'INV-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare('INSERT INTO inventory_transactions (transaction_code, material_id, transaction_type, quantity, stock_before, stock_after, order_id, reason, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        $code, $materialId, $type, number_format($quantity, 3, '.', ''), number_format($before, 3, '.', ''), number_format($after, 3, '.', ''), $orderId, $reason, $notes ?: null, $userId
    ]);
    return (int) $pdo->lastInsertId();
}

function page_title(string $page): string
{
    $titles = [
        'dashboard' => 'Dashboard', 'inventory' => 'Inventory bahan', 'material' => 'Detail bahan',
        'material-form' => 'Form bahan', 'stock' => 'Catat stok', 'use-stock' => 'Gunakan stok',
        'history' => 'Riwayat stok', 'usage-history' => 'Riwayat penggunaan',
        'orders' => 'Pesanan jersey', 'order-form' => 'Tambah pesanan', 'order-edit' => 'Edit pesanan', 'order' => 'Detail pesanan',
        'users' => 'Manajemen pengguna', 'user-form' => 'Form pengguna', 'reports' => 'Laporan',
        'logs' => 'Log aktivitas', 'availability' => 'Cek ketersediaan', 'profile' => 'Profil & keamanan',
    ];
    return $titles[$page] ?? 'DR.SPORT';
}

function render_header(string $title, array $user, string $page): void
{
    $roleLabels = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'karyawan' => 'Karyawan'];
    $role = $user['role'];
    $nav = [
        ['dashboard', 'Dashboard', '◫', ['super_admin', 'admin', 'karyawan']],
        ['orders', 'Pesanan', '▤', ['super_admin', 'admin']],
        ['inventory', 'Inventory', '▦', ['super_admin', 'admin']],
        ['availability', 'Cek ketersediaan', '⌕', ['karyawan']],
        ['use-stock', 'Gunakan stok', '−', ['karyawan']],
        ['usage-history', 'Riwayat penggunaan', '↕', ['karyawan']],
        ['history', 'Riwayat stok', '↕', ['super_admin', 'admin']],
        ['reports', 'Laporan', '▥', ['super_admin', 'admin']],
        ['users', 'Pengguna', '♙', ['super_admin']],
        ['logs', 'Log aktivitas', '≡', ['super_admin']],
    ];
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#172033"><title>' . e($title) . ' · DR.SPORT</title><link rel="stylesheet" href="assets/css/app.css"></head><body><div class="app-shell"><aside class="sidebar"><a class="brand" href="' . e(url('dashboard')) . '"><span class="brand-mark">DS</span><span><strong>DR.SPORT</strong><small>OPERATIONS CONSOLE</small></span></a><div class="workspace-label">WORKSPACE</div><nav>';
    foreach ($nav as $item) {
        if (in_array($role, $item[3], true)) {
            $active = ($page === $item[0] || ($item[0] === 'orders' && in_array($page, ['order', 'order-form', 'order-edit'], true)) || ($item[0] === 'inventory' && in_array($page, ['material', 'material-form', 'stock'], true))) ? ' active' : '';
            echo '<a class="nav-link' . $active . '" href="' . e(url($item[0])) . '"><span class="nav-icon">' . e($item[2]) . '</span>' . e($item[1]) . '</a>';
        }
    }
    echo '</nav><div class="sidebar-bottom"><div class="sidebar-note"><span class="live-dot"></span> Inventory terhubung</div><div class="side-user"><span class="avatar">' . e(strtoupper(substr($user['name'], 0, 1))) . '</span><span><strong>' . e($user['name']) . '</strong><small>' . e($roleLabels[$role] ?? $role) . '</small></span></div></div></aside><div class="main-area"><header class="topbar"><div class="mobile-brand"><span class="brand-mark">DS</span> DR.SPORT</div><div class="topbar-meta"><span class="today-label">' . e(indonesian_date('l, d F Y')) . '</span><a class="profile-link" href="' . e(url('profile')) . '">Profil & keamanan</a><form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="action" value="logout"><button class="logout-link">Keluar ↗</button></form></div></header><main class="content"><div class="page-heading"><div><p class="eyebrow">DR.SPORT <span>/</span> ' . e($roleLabels[$role] ?? $role) . '</p><h1>' . e($title) . '</h1></div></div>';
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert ' . ($flash['type'] === 'error' ? 'alert-error' : 'alert-success') . '">' . e($flash['message']) . '</div>';
    }
    if (!empty($_SESSION['temporary_password'])) {
        echo '<div class="alert alert-warning"><strong>Password sementara:</strong> <code>' . e($_SESSION['temporary_password']) . '</code> <span>Salin dan berikan secara langsung.</span></div>';
        unset($_SESSION['temporary_password']);
    }
}

function render_footer(): void
{
    echo '</main><footer class="footer"><span>DR.SPORT · Sistem internal pengelolaan pesanan & inventory</span><span>Data stok diperbarui dari database</span></footer></div></div><script src="assets/js/app.js"></script></body></html>';
}

function stat_card(string $label, string $value, string $hint, string $tone = ''): void
{
    echo '<article class="stat-card"><div class="stat-top"><span>' . e($label) . '</span><span class="stat-mark ' . e($tone) . '"></span></div><strong>' . e($value) . '</strong><small>' . e($hint) . '</small></article>';
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e(status_class($status)) . '">' . e($status) . '</span>';
}

function pagination_links(string $page, int $current, int $totalPages, array $filters): string
{
    if ($totalPages < 2) {
        return '';
    }
    $previous = max(1, $current - 1);
    $next = min($totalPages, $current + 1);
    $previousUrl = url($page, array_merge($filters, ['p' => $previous]));
    $nextUrl = url($page, array_merge($filters, ['p' => $next]));
    return '<nav class="pagination" aria-label="Navigasi halaman"><a class="button small secondary' . ($current === 1 ? ' disabled' : '') . '" href="' . e($previousUrl) . '">← Sebelumnya</a><span>Halaman ' . $current . ' dari ' . $totalPages . '</span><a class="button small secondary' . ($current === $totalPages ? ' disabled' : '') . '" href="' . e($nextUrl) . '">Berikutnya →</a></nav>';
}

function render_dashboard(array $user): void
{
    $pdo = db();
    $lowCount = (int) $pdo->query('SELECT COUNT(*) FROM materials WHERE is_active = 1 AND current_stock <= minimum_stock')->fetchColumn();
    $orderCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Selesai','Dibatalkan')")->fetchColumn();
    echo '<div class="stat-grid">';
    if ($user['role'] === 'super_admin') {
        stat_card('Akun aktif', (string) $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(), 'Pengguna dapat masuk');
        stat_card('Admin & karyawan', (string) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','karyawan')")->fetchColumn(), 'Akun operasional');
        stat_card('Pesanan berjalan', (string) $orderCount, 'Belum selesai');
        stat_card('Stok perlu perhatian', (string) $lowCount, 'Bahan menyentuh minimum', 'warning');
    } elseif ($user['role'] === 'admin') {
        stat_card('Pesanan masuk', (string) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pesanan Masuk'")->fetchColumn(), 'Menunggu proses');
        stat_card('Dalam proses', (string) $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Pesanan Masuk','Selesai','Dibatalkan')")->fetchColumn(), 'Sedang dikerjakan');
        stat_card('Pesanan selesai', (string) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Selesai'")->fetchColumn(), 'Seluruh periode');
        stat_card('Stok menipis', (string) $lowCount, 'Bahan menyentuh minimum', 'warning');
    } else {
        stat_card('Bahan aktif', (string) $pdo->query('SELECT COUNT(*) FROM materials WHERE is_active=1')->fetchColumn(), 'Siap untuk dicek');
        stat_card('Perlu perhatian', (string) $lowCount, 'Stok menipis atau habis', 'warning');
        stat_card('Pesanan berjalan', (string) $orderCount, 'Status produksi terkini');
        stat_card('Saldo stok', 'DB', 'Data terbaru saat halaman dibuka');
    }
    echo '</div>';
    if ($user['role'] === 'super_admin') {
        $counts = $pdo->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
        echo '<section class="status-summary"><div class="status-summary-heading">Pesanan menurut status</div><div class="status-summary-items">';
        foreach (['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'] as $orderStatus) {
            echo '<div><span>' . e($orderStatus) . '</span><strong>' . e($counts[$orderStatus] ?? 0) . '</strong></div>';
        }
        echo '</div></section>';
    } elseif ($user['role'] === 'admin') {
        $categoryStocks = $pdo->query('SELECT category, COUNT(*) AS total, COALESCE(SUM(current_stock),0) AS stock FROM materials WHERE is_active=1 GROUP BY category')->fetchAll();
        echo '<section class="category-summary">';
        foreach (['kain', 'tinta_printing', 'benang'] as $category) {
            $categoryStock = ['total' => 0, 'stock' => 0];
            foreach ($categoryStocks as $summary) {
                if ($summary['category'] === $category) {
                    $categoryStock = $summary;
                    break;
                }
            }
            echo '<div><span>' . e(category_label($category)) . '</span><strong>' . e($categoryStock['total']) . ' bahan</strong><small>Total saldo ' . e(format_stock($categoryStock['stock'])) . '</small></div>';
        }
        echo '</section>';
    }
    echo '<div class="dashboard-grid"><section class="panel"><div class="panel-heading"><div><h2>' . ($user['role'] === 'karyawan' ? 'Bahan terbaru' : 'Pesanan terbaru') . '</h2><p>' . ($user['role'] === 'karyawan' ? 'Periksa saldo bahan yang aktif' : 'Pantau status pengerjaan terkini') . '</p></div><a class="text-link" href="' . e(url($user['role'] === 'karyawan' ? 'availability' : 'orders')) . '">' . ($user['role'] === 'karyawan' ? 'Cek ketersediaan →' : 'Semua pesanan →') . '</a></div>';
    if ($user['role'] === 'karyawan') {
        $materials = $pdo->query('SELECT id,material_code,name,category,unit,current_stock,minimum_stock,image_path FROM materials WHERE is_active=1 ORDER BY name LIMIT 6')->fetchAll();
        if (!$materials) {
            echo '<div class="empty-state"><strong>Belum ada bahan aktif</strong><p>Bahan inventory akan muncul setelah dicatat Admin.</p></div>';
        } else {
            echo '<div class="low-list">';
            foreach ($materials as $material) {
                echo '<a class="low-item" href="' . e(url('material', ['id' => $material['id']])) . '"><img src="' . e(material_image($material)) . '" alt=""><span><strong>' . e($material['name']) . '</strong><small>' . e($material['material_code']) . ' · ' . e(category_label($material['category'])) . '</small></span><span class="low-stock">' . e(format_stock($material['current_stock'])) . ' ' . e($material['unit']) . '</span></a>';
            }
            echo '</div>';
        }
    } else {
        $orders = $pdo->query('SELECT o.id,o.order_code,o.order_date,o.quantity,o.status,c.name customer_name FROM orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.created_at DESC LIMIT 6')->fetchAll();
        if (!$orders) {
            echo '<div class="empty-state"><span class="empty-icon">▤</span><strong>Belum ada pesanan</strong><p>Pesanan yang dibuat akan muncul di sini.</p></div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Kode pesanan</th><th>Pelanggan</th><th>Tanggal</th><th>Jumlah</th><th>Status</th></tr></thead><tbody>';
            foreach ($orders as $order) {
                echo '<tr><td><a class="table-primary" href="' . e(url('order', ['id' => $order['id']])) . '">' . e($order['order_code']) . '</a></td><td>' . e($order['customer_name']) . '</td><td>' . e(date('d M Y', strtotime($order['order_date']))) . '</td><td>' . number_format((int) $order['quantity']) . '</td><td>' . status_badge($order['status']) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    }
    echo '</section><section class="panel"><div class="panel-heading"><div><h2>Stok perlu perhatian</h2><p>Saldo di bawah atau sama dengan minimum</p></div><a class="text-link" href="' . e(url($user['role'] === 'karyawan' ? 'availability' : 'inventory')) . '">Inventory →</a></div>';
    $materials = $pdo->query('SELECT id,material_code,name,category,unit,current_stock,minimum_stock,image_path FROM materials WHERE is_active=1 AND current_stock<=minimum_stock ORDER BY current_stock ASC LIMIT 6')->fetchAll();
    if (!$materials) {
        echo '<div class="empty-state compact"><span class="empty-check">✓</span><strong>Semua stok aman</strong><p>Tidak ada bahan pada batas minimum.</p></div>';
    } else {
        echo '<div class="low-list">';
        foreach ($materials as $material) {
            echo '<a class="low-item" href="' . e(url('material', ['id' => $material['id']])) . '"><img src="' . e(material_image($material)) . '" alt=""><span><strong>' . e($material['name']) . '</strong><small>' . e($material['material_code']) . ' · ' . e(category_label($material['category'])) . '</small></span><span class="low-stock">' . e(format_stock($material['current_stock'])) . ' ' . e($material['unit']) . '</span></a>';
        }
        echo '</div>';
    }
    echo '</section></div>';
    if ($user['role'] === 'super_admin') {
        $activities = $pdo->query('SELECT l.action,l.description,l.created_at,u.name actor FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 6')->fetchAll();
        echo '<section class="panel activity-panel"><div class="panel-heading"><div><h2>Aktivitas terbaru</h2><p>Perubahan penting yang tercatat di sistem</p></div><a class="text-link" href="' . e(url('logs')) . '">Semua aktivitas →</a></div>';
        if (!$activities) {
            echo '<div class="empty-state compact"><strong>Belum ada aktivitas</strong></div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Aktivitas</th><th>Ringkasan</th><th>Pengguna</th><th>Waktu</th></tr></thead><tbody>';
            foreach ($activities as $activity) {
                echo '<tr><td><span class="table-primary">' . e($activity['action']) . '</span></td><td>' . e($activity['description']) . '</td><td>' . e($activity['actor'] ?: 'Tidak dikenal') . '</td><td>' . e(indonesian_date('d M Y, H:i', strtotime($activity['created_at']))) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
    }
}

function render_inventory(array $user): void
{
    $search = trim((string) ($_GET['q'] ?? ''));
    $category = (string) ($_GET['category'] ?? '');
    $state = (string) ($_GET['state'] ?? '');
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
    echo '<div class="toolbar"><form class="filter-form" method="get"><input type="hidden" name="page" value="inventory"><label class="search-box"><span>⌕</span><input type="search" name="q" value="' . e($search) . '" placeholder="Cari nama atau kode bahan"></label><select name="category"><option value="">Semua kategori</option>';
    foreach (['kain', 'tinta_printing', 'benang'] as $value) {
        echo '<option value="' . e($value) . '"' . ($category === $value ? ' selected' : '') . '>' . e(category_label($value)) . '</option>';
    }
    echo '</select><select name="state"><option value="">Semua status</option><option value="low"' . ($state === 'low' ? ' selected' : '') . '>Menipis</option><option value="empty"' . ($state === 'empty' ? ' selected' : '') . '>Habis</option></select><label class="check-filter"><input type="checkbox" name="inactive" value="1"' . (($_GET['inactive'] ?? '') === '1' ? ' checked' : '') . '> Nonaktif</label><button class="button secondary">Filter</button></form>';
    if ($user['role'] === 'admin') {
        echo '<a class="button primary" href="' . e(url('material-form')) . '"><span>＋</span> Tambah bahan</a>';
    }
    echo '</div><section class="panel"><div class="table-wrap"><table><thead><tr><th>Bahan</th><th>Kategori</th><th>Saldo</th><th>Minimum</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
    if (!$materials) {
        echo '<tr><td colspan="6"><div class="empty-state"><strong>Tidak ada bahan ditemukan</strong><p>Coba ubah kata kunci atau filter.</p></div></td></tr>';
    }
    foreach ($materials as $material) {
        $status = material_state($material);
        echo '<tr><td><div class="material-cell"><img src="' . e(material_image($material)) . '" alt=""><span><a class="table-primary" href="' . e(url('material', ['id' => $material['id']])) . '">' . e($material['name']) . '</a><small>' . e($material['material_code']) . '</small></span></div></td><td>' . e(category_label($material['category'])) . '</td><td><strong>' . e(format_stock($material['current_stock'])) . '</strong> <span class="muted">' . e($material['unit']) . '</span></td><td>' . e(format_stock($material['minimum_stock'])) . ' ' . e($material['unit']) . '</td><td>' . status_badge($status) . '</td><td><div class="row-actions"><a class="button small secondary" href="' . e(url('material', ['id' => $material['id']])) . '">Detail</a>';
        if ($user['role'] === 'admin' && (int) $material['is_active']) {
            echo '<a class="button small secondary" href="' . e(url('material-form', ['id' => $material['id']])) . '">Edit</a><a class="button small subtle" href="' . e(url('stock', ['id' => $material['id']])) . '">＋ Stok</a>';
        }
        echo '</div></td></tr>';
    }
    $filters = ['q' => $search, 'category' => $category, 'state' => $state];
    if (($_GET['inactive'] ?? '') === '1') {
        $filters['inactive'] = '1';
    }
    echo '</tbody></table></div><div class="table-foot table-foot-paged">Menampilkan ' . count($materials) . ' dari ' . $total . ' bahan' . pagination_links('inventory', $pageNumber, $totalPages, $filters) . '</div></section>';
}

function render_material_detail(array $user): void
{
    $stmt = db()->prepare('SELECT m.*,u.name creator FROM materials m JOIN users u ON u.id=m.created_by WHERE m.id=?');
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $material = $stmt->fetch();
    if (!$material) {
        render_error(404, 'Bahan tidak ditemukan.');
        return;
    }
    echo '<div class="detail-layout"><section class="panel material-detail"><img class="material-hero" src="' . e(material_image($material)) . '" alt="' . e($material['name']) . '"><div><p class="eyebrow">' . e(category_label($material['category'])) . ' · ' . e($material['material_code']) . '</p><h2>' . e($material['name']) . '</h2><div class="detail-stock">' . e(format_stock($material['current_stock'])) . ' <small>' . e($material['unit']) . '</small></div><p>' . status_badge(material_state($material)) . ' <span class="muted">Minimum ' . e(format_stock($material['minimum_stock'])) . ' ' . e($material['unit']) . '</span></p><p class="muted">' . e($material['location'] ?: 'Lokasi belum dicatat') . '</p><p>' . nl2br(e($material['notes'] ?: 'Tidak ada catatan.')) . '</p></div></section>';
    if ($user['role'] === 'karyawan') {
        echo '<div class="row-actions"><a class="button primary" href="' . e(url('use-stock', ['id' => $material['id']])) . '">Gunakan bahan</a><a class="button secondary" href="' . e(url('usage-history', ['material_id' => $material['id']])) . '">Riwayat penggunaan</a></div></div>';
        return;
    }
    echo '<section class="panel"><div class="panel-heading"><div><h2>Mutasi terbaru</h2><p>Ledger stok bahan ini</p></div><a class="text-link" href="' . e(url('history', ['material_id' => $material['id']])) . '">Lihat riwayat →</a></div>';
    $stmt = db()->prepare('SELECT t.*,u.name actor,o.order_code FROM inventory_transactions t JOIN users u ON u.id=t.created_by LEFT JOIN orders o ON o.id=t.order_id WHERE t.material_id=? ORDER BY t.created_at DESC LIMIT 8');
    $stmt->execute([$material['id']]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        echo '<div class="empty-state compact"><strong>Belum ada transaksi</strong><p>Stok awal dan mutasi akan tercatat di sini.</p></div>';
    } else {
        echo '<div class="table-wrap"><table><thead><tr><th>Transaksi</th><th>Jenis</th><th>Jumlah</th><th>Saldo akhir</th><th>Tanggal</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td><strong>' . e($row['transaction_code']) . '</strong><small class="block muted">' . e($row['reason']) . '</small></td><td>' . e(transaction_label($row['transaction_type'])) . '</td><td>' . (transaction_is_in($row['transaction_type']) ? '+' : '−') . e(format_stock($row['quantity'])) . ' ' . e($material['unit']) . '</td><td>' . e(format_stock($row['stock_after'])) . ' ' . e($material['unit']) . '</td><td>' . e(date('d M Y, H:i', strtotime($row['created_at']))) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section></div>';
}

function render_material_form(): void
{
    require_role(['admin']);
    $id = (int) ($_GET['id'] ?? 0);
    $material = ['id' => 0, 'material_code' => '', 'name' => '', 'category' => 'kain', 'unit' => '', 'minimum_stock' => '0', 'location' => '', 'notes' => '', 'image_path' => null, 'is_active' => 1];
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
    echo '<form method="post" enctype="multipart/form-data" class="form-layout" data-image-form>' . csrf_field() . '<input type="hidden" name="action" value="save_material"><input type="hidden" name="id" value="' . e($id) . '"><section class="panel form-panel"><div class="panel-heading"><div><h2>Informasi bahan</h2><p>Gunakan kode unik dan satuan dasar yang konsisten.</p></div></div><div class="form-grid"><label class="field"><span>Kode bahan <b>*</b></span><input name="material_code" required maxlength="30" value="' . e($material['material_code']) . '" placeholder="KAIN-001"></label><label class="field"><span>Nama bahan <b>*</b></span><input name="name" required maxlength="120" value="' . e($material['name']) . '" placeholder="Kain dry-fit"></label><label class="field"><span>Kategori <b>*</b></span><select name="category" required>';
    foreach (['kain', 'tinta_printing', 'benang'] as $category) {
        echo '<option value="' . e($category) . '"' . ($material['category'] === $category ? ' selected' : '') . '>' . e(category_label($category)) . '</option>';
    }
    echo '</select></label><label class="field"><span>Satuan <b>*</b></span><input name="unit" required maxlength="24" value="' . e($material['unit']) . '" placeholder="meter, liter, gulung"></label><label class="field"><span>Minimum stok</span><input type="number" name="minimum_stock" min="0" max="99999999999.999" step="0.001" value="' . e(format_stock($material['minimum_stock'])) . '"></label><label class="field"><span>Lokasi penyimpanan</span><input name="location" maxlength="120" value="' . e($material['location']) . '" placeholder="Rak A-01"></label>';
    if (!$id) {
        echo '<label class="field"><span>Stok awal</span><input type="number" name="initial_stock" min="0" max="99999999999.999" step="0.001" value="0"><small>Dicatat sebagai transaksi stok masuk.</small></label>';
    }
    echo '<label class="field field-wide"><span>Catatan</span><textarea name="notes" rows="3" maxlength="1000">' . e($material['notes']) . '</textarea></label><div class="field field-wide"><span>Foto bahan <small>(JPG, PNG, WEBP, GIF, BMP, TIFF, SVG, HEIC, AVIF, ICO · maks. 2 MB)</small></span><div class="image-upload"><img data-image-preview src="' . e(material_image($material)) . '" alt="Preview bahan"><div><label class="button secondary file-button">Pilih foto<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,.tiff,.tif,.svg,.heic,.heif,.avif,.ico,image/jpeg,image/png,image/webp,image/gif,image/bmp,image/tiff,image/svg+xml,image/heic,image/heif,image/avif,image/x-icon" data-image-input></label><p class="muted">Foto digunakan untuk membantu identifikasi bahan.</p>';
    if ($material['image_path']) {
        echo '<label class="check-filter"><input type="checkbox" name="remove_image" value="1"> Hapus foto dan gunakan placeholder</label>';
    }
    echo '</div></div></div>';
    if ($id) {
        echo '<label class="check-filter field-wide"><input type="checkbox" name="active" value="1"' . ((int) $material['is_active'] ? ' checked' : '') . '> Bahan aktif</label>';
    }
    echo '</div></section><div class="form-actions"><a class="button secondary" href="' . e(url('inventory')) . '">Batal</a><button class="button primary">Simpan bahan</button></div></form>';
}

function render_stock_form(): void
{
    require_role(['admin']);
    $materialId = (int) ($_GET['id'] ?? 0);
    $materials = db()->query('SELECT id,material_code,name,current_stock,unit FROM materials WHERE is_active=1 ORDER BY name')->fetchAll();
    echo '<form method="post" class="form-layout">' . csrf_field() . '<input type="hidden" name="action" value="inventory_move"><section class="panel form-panel"><div class="panel-heading"><div><h2>Mutasi inventory</h2><p>Saldo akan divalidasi ulang saat transaksi disimpan.</p></div></div><div class="form-grid"><label class="field field-wide"><span>Bahan <b>*</b></span><select name="material_id" required><option value="">Pilih bahan</option>';
    foreach ($materials as $material) {
        echo '<option value="' . e($material['id']) . '"' . ($materialId === (int) $material['id'] ? ' selected' : '') . '>' . e($material['material_code'] . ' · ' . $material['name'] . ' — ' . format_stock($material['current_stock']) . ' ' . $material['unit']) . '</option>';
    }
    echo '</select></label><label class="field"><span>Jenis transaksi <b>*</b></span><select name="transaction_type" required><option value="in">Stok masuk</option><option value="out">Stok keluar / pemakaian</option><option value="adjustment_in">Penyesuaian stok +</option><option value="adjustment_out">Penyesuaian stok −</option></select></label><label class="field"><span>Jumlah <b>*</b></span><input type="number" name="quantity" min="0.001" step="0.001" required placeholder="0"></label><label class="field"><span>Pesanan terkait</span><select name="order_id"><option value="">Tidak terkait</option>';
    foreach (db()->query("SELECT id,order_code FROM orders WHERE status NOT IN ('Selesai','Dibatalkan') ORDER BY created_at DESC")->fetchAll() as $order) {
        echo '<option value="' . e($order['id']) . '">' . e($order['order_code']) . '</option>';
    }
    echo '</select></label><label class="field"><span>Tanggal</span><input type="date" value="' . e(date('Y-m-d')) . '" disabled><small>Tanggal transaksi dicatat otomatis oleh server.</small></label><label class="field field-wide"><span>Alasan / keterangan <b>*</b></span><input name="reason" maxlength="180" required placeholder="Pembelian bahan / pemakaian produksi"></label><label class="field field-wide"><span>Catatan tambahan</span><textarea name="notes" rows="3" maxlength="1000"></textarea></label></div></section><div class="form-actions"><a class="button secondary" href="' . e(url('inventory')) . '">Batal</a><button class="button primary">Simpan transaksi</button></div></form>';
}

function render_history(): void
{
    $materialId = (int) ($_GET['material_id'] ?? 0);
    $start = (string) ($_GET['start'] ?? date('Y-m-01'));
    $end = (string) ($_GET['end'] ?? date('Y-m-d'));
    $where = ['DATE(t.created_at) BETWEEN ? AND ?'];
    $params = [$start, $end];
    if ($materialId) {
        $where[] = 't.material_id=?';
        $params[] = $materialId;
    }
    $stmt = db()->prepare('SELECT t.*,m.material_code,m.name material_name,m.unit,o.order_code,u.name actor FROM inventory_transactions t JOIN materials m ON m.id=t.material_id JOIN users u ON u.id=t.created_by LEFT JOIN orders o ON o.id=t.order_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.created_at DESC LIMIT 300');
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    echo '<div class="toolbar"><form method="get" class="filter-form"><input type="hidden" name="page" value="history"><label class="field-inline"><span>Dari</span><input type="date" name="start" value="' . e($start) . '"></label><label class="field-inline"><span>Sampai</span><input type="date" name="end" value="' . e($end) . '"></label><button class="button secondary">Terapkan</button></form></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>No. transaksi</th><th>Bahan</th><th>Jenis</th><th>Jumlah</th><th>Saldo sebelum → sesudah</th><th>Pesanan</th><th>Pencatat / waktu</th></tr></thead><tbody>';
    if (!$transactions) {
        echo '<tr><td colspan="7"><div class="empty-state"><strong>Belum ada transaksi pada periode ini</strong></div></td></tr>';
    }
    foreach ($transactions as $row) {
        $incoming = transaction_is_in($row['transaction_type']);
        echo '<tr><td><strong>' . e($row['transaction_code']) . '</strong><small class="block muted">' . e($row['reason']) . '</small></td><td><a class="table-primary" href="' . e(url('material', ['id' => $row['material_id']])) . '">' . e($row['material_name']) . '</a><small class="block muted">' . e($row['material_code']) . '</small></td><td>' . e(transaction_label($row['transaction_type'])) . '</td><td class="' . ($incoming ? 'text-success' : 'text-danger') . '">' . ($incoming ? '+' : '−') . e(format_stock($row['quantity'])) . ' ' . e($row['unit']) . '</td><td>' . e(format_stock($row['stock_before'])) . ' → <strong>' . e(format_stock($row['stock_after'])) . '</strong></td><td>' . e($row['order_code'] ?: '—') . '</td><td>' . e($row['actor']) . '<small class="block muted">' . e(date('d M Y, H:i', strtotime($row['created_at']))) . '</small></td></tr>';
    }
    echo '</tbody></table></div><div class="table-foot">Maksimal 300 transaksi per tampilan · transaksi tersimpan tidak dapat dihapus</div></section>';
}

function render_employee_stock_form(): void
{
    require_role(['karyawan']);
    $materialId = (int) ($_GET['id'] ?? 0);
    $materials = db()->query('SELECT id,material_code,name,current_stock,unit FROM materials WHERE is_active=1 ORDER BY name')->fetchAll();
    echo '<form method="post" class="form-layout">' . csrf_field() . '<input type="hidden" name="action" value="employee_stock_use"><section class="panel form-panel"><div class="panel-heading"><div><h2>Catat pemakaian bahan</h2><p>Saldo akan diperiksa kembali saat disimpan. Pemakaian akan langsung mengurangi stok.</p></div></div><div class="form-grid"><label class="field field-wide"><span>Bahan <b>*</b></span><select name="material_id" required><option value="">Pilih bahan</option>';
    foreach ($materials as $material) {
        echo '<option value="' . e($material['id']) . '"' . ($materialId === (int) $material['id'] ? ' selected' : '') . '>' . e($material['material_code'] . ' · ' . $material['name'] . ' — tersedia ' . format_stock($material['current_stock']) . ' ' . $material['unit']) . '</option>';
    }
    echo '</select></label><label class="field"><span>Jumlah dipakai <b>*</b></span><input type="number" name="quantity" min="0.001" max="99999999999.999" step="0.001" required placeholder="0"><small>Masukkan jumlah dalam satuan bahan yang dipilih.</small></label><label class="field"><span>Keperluan <b>*</b></span><input name="reason" maxlength="180" required placeholder="Contoh: produksi pesanan / sampel"></label><label class="field field-wide"><span>Catatan tambahan</span><textarea name="notes" rows="3" maxlength="1000" placeholder="Opsional"></textarea></label></div></section><div class="form-actions"><a class="button secondary" href="' . e(url('availability')) . '">Batal</a><button class="button primary">Simpan pemakaian</button></div></form>';
}

function render_employee_usage_history(): void
{
    $user = require_role(['karyawan']);
    $start = (string) ($_GET['start'] ?? date('Y-m-01'));
    $end = (string) ($_GET['end'] ?? date('Y-m-d'));
    if (!valid_date($start)) {
        $start = date('Y-m-01');
    }
    if (!valid_date($end)) {
        $end = date('Y-m-d');
    }
    if ($end < $start) {
        $end = $start;
    }
    $where = ['t.created_by = ?', "t.transaction_type = 'out'", 'DATE(t.created_at) BETWEEN ? AND ?'];
    $params = [(int) $user['id'], $start, $end];
    $materialId = (int) ($_GET['material_id'] ?? 0);
    if ($materialId > 0) {
        $where[] = 't.material_id = ?';
        $params[] = $materialId;
    }
    $stmt = db()->prepare('SELECT t.*,m.material_code,m.name material_name,m.unit FROM inventory_transactions t JOIN materials m ON m.id=t.material_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.created_at DESC LIMIT 300');
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    echo '<div class="toolbar"><form method="get" class="filter-form"><input type="hidden" name="page" value="usage-history">';
    if ($materialId > 0) {
        echo '<input type="hidden" name="material_id" value="' . e($materialId) . '">';
    }
    echo '<label class="field-inline"><span>Dari</span><input type="date" name="start" value="' . e($start) . '"></label><label class="field-inline"><span>Sampai</span><input type="date" name="end" value="' . e($end) . '"></label><button class="button secondary">Terapkan</button><a class="button primary" href="' . e(url('use-stock')) . '">＋ Catat pemakaian</a></form></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>No. transaksi</th><th>Bahan</th><th>Jumlah dipakai</th><th>Saldo sebelum → sesudah</th><th>Keperluan</th><th>Waktu</th></tr></thead><tbody>';
    if (!$transactions) {
        echo '<tr><td colspan="6"><div class="empty-state"><strong>Belum ada pemakaian pada periode ini</strong><p>Pemakaian yang Anda catat akan tampil di sini.</p></div></td></tr>';
    }
    foreach ($transactions as $row) {
        echo '<tr><td><strong>' . e($row['transaction_code']) . '</strong></td><td><strong>' . e($row['material_name']) . '</strong><small class="block muted">' . e($row['material_code']) . '</small></td><td class="text-danger">−' . e(format_stock($row['quantity'])) . ' ' . e($row['unit']) . '</td><td>' . e(format_stock($row['stock_before'])) . ' → <strong>' . e(format_stock($row['stock_after'])) . '</strong></td><td>' . e($row['reason']) . ($row['notes'] ? '<small class="block muted">' . e($row['notes']) . '</small>' : '') . '</td><td>' . e(date('d M Y, H:i', strtotime($row['created_at']))) . '</td></tr>';
    }
    echo '</tbody></table></div><div class="table-foot">Maksimal 300 pemakaian per tampilan · riwayat hanya menampilkan pemakaian akun Anda</div></section>';
}

function render_orders(): void
{
    $search = trim((string) ($_GET['q'] ?? ''));
    $status = (string) ($_GET['status'] ?? '');
    $start = (string) ($_GET['start'] ?? '');
    $end = (string) ($_GET['end'] ?? '');
    if ($start !== '' && !valid_date($start)) {
        $start = '';
    }
    if ($end !== '' && !valid_date($end)) {
        $end = '';
    }
    if ($start !== '' && $end !== '' && $end < $start) {
        $end = $start;
    }
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(o.order_code LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
        array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
    }
    $statuses = ['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'];
    if (in_array($status, $statuses, true)) {
        $where[] = 'o.status=?';
        $params[] = $status;
    }
    if ($start !== '') {
        $where[] = 'o.order_date >= ?';
        $params[] = $start;
    }
    if ($end !== '') {
        $where[] = 'o.order_date <= ?';
        $params[] = $end;
    }
    $countStmt = db()->prepare('SELECT COUNT(*) FROM orders o JOIN customers c ON c.id=o.customer_id WHERE ' . implode(' AND ', $where));
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / 20));
    $pageNumber = max(1, min((int) ($_GET['p'] ?? 1), $totalPages));
    $offset = ($pageNumber - 1) * 20;
    $stmt = db()->prepare('SELECT o.*,c.name customer_name,c.phone,u.name creator FROM orders o JOIN customers c ON c.id=o.customer_id JOIN users u ON u.id=o.created_by WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC LIMIT 20 OFFSET ' . $offset);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    echo '<div class="toolbar"><form class="filter-form" method="get"><input type="hidden" name="page" value="orders"><label class="search-box"><span>⌕</span><input type="search" name="q" value="' . e($search) . '" placeholder="Cari kode, pelanggan, kontak"></label><label class="field-inline"><span>Dari</span><input type="date" name="start" value="' . e($start) . '"></label><label class="field-inline"><span>Sampai</span><input type="date" name="end" value="' . e($end) . '"></label><select name="status"><option value="">Semua status</option>';
    foreach ($statuses as $value) {
        echo '<option value="' . e($value) . '"' . ($status === $value ? ' selected' : '') . '>' . e($value) . '</option>';
    }
    echo '</select><button class="button secondary">Filter</button></form>';
    if (current_user()['role'] === 'admin') {
        echo '<a class="button primary" href="' . e(url('order-form')) . '"><span>＋</span> Tambah pesanan</a>';
    }
    echo '</div><section class="panel"><div class="table-wrap"><table><thead><tr><th>Kode pesanan</th><th>Pelanggan</th><th>Tanggal</th><th>Target selesai</th><th>Jumlah</th><th>Status</th><th>Admin</th><th></th></tr></thead><tbody>';
    if (!$orders) {
        echo '<tr><td colspan="8"><div class="empty-state"><strong>Belum ada pesanan</strong><p>Pesanan yang cocok dengan filter akan tampil di sini.</p></div></td></tr>';
    }
    foreach ($orders as $order) {
        echo '<tr><td><a class="table-primary" href="' . e(url('order', ['id' => $order['id']])) . '">' . e($order['order_code']) . '</a></td><td>' . e($order['customer_name']) . '<small class="block muted">' . e($order['phone']) . '</small></td><td>' . e(date('d M Y', strtotime($order['order_date']))) . '</td><td>' . e($order['target_date'] ? date('d M Y', strtotime($order['target_date'])) : '—') . '</td><td>' . number_format((int) $order['quantity']) . ' pcs</td><td>' . status_badge($order['status']) . '</td><td>' . e($order['creator']) . '</td><td><a class="button small secondary" href="' . e(url('order', ['id' => $order['id']])) . '">Detail</a></td></tr>';
    }
    echo '</tbody></table></div><div class="table-foot table-foot-paged">Menampilkan ' . count($orders) . ' dari ' . $total . ' pesanan' . pagination_links('orders', $pageNumber, $totalPages, ['q' => $search, 'status' => $status, 'start' => $start, 'end' => $end]) . '</div></section>';
}

function render_order_form(): void
{
    require_role(['admin']);
    $id = (int) ($_GET['id'] ?? 0);
    $order = ['id' => 0, 'customer_name' => '', 'phone' => '', 'address' => '', 'order_date' => date('Y-m-d'), 'target_date' => '', 'quantity' => '', 'jersey_description' => '', 'notes' => '', 'order_code' => ''];
    if ($id) {
        $stmt = db()->prepare('SELECT o.*,c.name customer_name,c.phone,c.address FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.id=?');
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if (!$found) {
            render_error(404, 'Pesanan tidak ditemukan.');
            return;
        }
        $order = $found;
    }
    echo '<form method="post" class="form-layout">' . csrf_field() . '<input type="hidden" name="action" value="save_order"><input type="hidden" name="id" value="' . e($id) . '"><section class="panel form-panel"><div class="panel-heading"><div><h2>Data pesanan</h2><p>' . ($id ? 'Memperbarui ' . e($order['order_code']) . ' tanpa mengubah histori status.' : 'Kode pesanan dibuat otomatis setelah disimpan.') . '</p></div></div><div class="form-grid"><label class="field"><span>Nama pelanggan <b>*</b></span><input name="customer_name" required maxlength="120" value="' . e($order['customer_name']) . '"></label><label class="field"><span>Nomor kontak <b>*</b></span><input name="customer_phone" required maxlength="40" value="' . e($order['phone']) . '" placeholder="08xxxxxxxxxx"></label><label class="field"><span>Tanggal pesanan <b>*</b></span><input type="date" name="order_date" required value="' . e($order['order_date']) . '"></label><label class="field"><span>Target selesai</span><input type="date" name="target_date" value="' . e($order['target_date']) . '"></label><label class="field"><span>Jumlah jersey <b>*</b></span><input type="number" name="quantity" min="1" max="4294967295" step="1" required value="' . e($order['quantity']) . '"></label><label class="field"><span>Alamat pelanggan</span><input name="address" maxlength="255" value="' . e($order['address']) . '"></label><label class="field field-wide"><span>Jenis/model & kebutuhan jersey <b>*</b></span><textarea name="jersey_description" rows="4" maxlength="2000" required placeholder="Contoh: Jersey futsal, lengan pendek, sublimasi full-print">' . e($order['jersey_description']) . '</textarea></label><label class="field field-wide"><span>Catatan internal</span><textarea name="notes" rows="3" maxlength="1000">' . e($order['notes']) . '</textarea></label></div></section><div class="form-actions"><a class="button secondary" href="' . e($id ? url('order', ['id' => $id]) : url('orders')) . '">Batal</a><button class="button primary">Simpan pesanan</button></div></form>';
}

function render_order_detail(): void
{
    $stmt = db()->prepare('SELECT o.*,c.name customer_name,c.phone,c.address,u.name creator FROM orders o JOIN customers c ON c.id=o.customer_id JOIN users u ON u.id=o.created_by WHERE o.id=?');
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $order = $stmt->fetch();
    if (!$order) {
        render_error(404, 'Pesanan tidak ditemukan.');
        return;
    }
    echo '<div class="order-detail-grid"><section class="panel"><div class="panel-heading"><div><p class="eyebrow">' . e($order['order_code']) . '</p><h2>' . e($order['customer_name']) . '</h2></div>' . status_badge($order['status']) . '</div><div class="detail-facts"><div><small>Kontak</small><strong>' . e($order['phone']) . '</strong></div><div><small>Tanggal pesanan</small><strong>' . e(date('d M Y', strtotime($order['order_date']))) . '</strong></div><div><small>Target selesai</small><strong>' . e($order['target_date'] ? date('d M Y', strtotime($order['target_date'])) : 'Belum ditentukan') . '</strong></div><div><small>Jumlah</small><strong>' . number_format((int) $order['quantity']) . ' jersey</strong></div><div class="wide"><small>Jenis / kebutuhan</small><p>' . nl2br(e($order['jersey_description'])) . '</p></div><div class="wide"><small>Catatan internal</small><p>' . nl2br(e($order['notes'] ?: 'Tidak ada catatan.')) . '</p></div><div><small>Dibuat oleh</small><strong>' . e($order['creator']) . '</strong></div></div>' . (current_user()['role'] === 'admin' ? '<div class="detail-action"><a class="button secondary" href="' . e(url('order-edit', ['id' => $order['id']])) . '">Edit data pesanan</a></div>' : '') . '</section><section class="panel"><div class="panel-heading"><div><h2>Timeline status</h2><p>Riwayat perubahan pengerjaan</p></div></div>';
    $stmt = db()->prepare('SELECT h.*,u.name actor FROM order_status_history h JOIN users u ON u.id=h.changed_by WHERE order_id=? ORDER BY changed_at DESC');
    $stmt->execute([$order['id']]);
    echo '<div class="timeline">';
    foreach ($stmt->fetchAll() as $event) {
        echo '<div class="timeline-item"><span class="timeline-dot"></span><div><strong>' . e($event['new_status']) . '</strong><small>' . e(date('d M Y, H:i', strtotime($event['changed_at']))) . ' · ' . e($event['actor']) . '</small>' . ($event['note'] ? '<p>' . e($event['note']) . '</p>' : '') . '</div></div>';
    }
    echo '</div>';
    if (current_user()['role'] === 'admin') {
        echo '<form method="post" class="status-form">' . csrf_field() . '<input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="' . e($order['id']) . '"><label class="field"><span>Perbarui status</span><select name="status">';
        foreach (['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'] as $status) {
            echo '<option value="' . e($status) . '"' . ($order['status'] === $status ? ' selected' : '') . '>' . e($status) . '</option>';
        }
        echo '</select></label><label class="field"><span>Catatan perubahan</span><input name="note" maxlength="255" placeholder="Opsional"></label><button class="button primary">Simpan status</button></form>';
    }
    echo '</section><section class="panel span-two"><div class="panel-heading"><div><h2>Pemakaian bahan</h2><p>Mutasi inventory yang dikaitkan ke pesanan ini</p></div></div>';
    $stmt = db()->prepare('SELECT t.*,m.name material_name,m.unit,u.name actor FROM inventory_transactions t JOIN materials m ON m.id=t.material_id JOIN users u ON u.id=t.created_by WHERE t.order_id=? ORDER BY t.created_at DESC');
    $stmt->execute([$order['id']]);
    $consumption = $stmt->fetchAll();
    if (!$consumption) {
        echo '<div class="empty-state compact"><strong>Belum ada pemakaian bahan</strong><p>Stok keluar terkait pesanan akan muncul di sini.</p></div>';
    } else {
        echo '<div class="table-wrap"><table><thead><tr><th>Bahan</th><th>Jumlah</th><th>Alasan</th><th>Tanggal</th></tr></thead><tbody>';
        foreach ($consumption as $item) {
            echo '<tr><td>' . e($item['material_name']) . '</td><td>' . e(format_stock($item['quantity'])) . ' ' . e($item['unit']) . '</td><td>' . e($item['reason']) . '</td><td>' . e(date('d M Y, H:i', strtotime($item['created_at']))) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section></div>';
}

function render_users(): void
{
    $users = db()->query("SELECT id,name,username,email,role,status,last_login_at,created_at FROM users WHERE role IN ('admin','karyawan') ORDER BY created_at DESC")->fetchAll();
    echo '<div class="toolbar"><div class="muted">Kelola akses Admin dan Karyawan. Akun Super Admin tidak dapat dinonaktifkan di sini.</div><a class="button primary" href="' . e(url('user-form')) . '"><span>＋</span> Tambah pengguna</a></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Username / email</th><th>Role</th><th>Status</th><th>Login terakhir</th><th>Aksi</th></tr></thead><tbody>';
    if (!$users) {
        echo '<tr><td colspan="6"><div class="empty-state"><strong>Belum ada akun operasional</strong></div></td></tr>';
    }
    foreach ($users as $user) {
        echo '<tr><td><div class="user-cell"><span class="avatar pale">' . e(strtoupper(substr($user['name'], 0, 1))) . '</span><strong>' . e($user['name']) . '</strong></div></td><td>' . e($user['username']) . '<small class="block muted">' . e($user['email'] ?: 'Email belum diisi') . '</small></td><td>' . ($user['role'] === 'admin' ? 'Admin' : 'Karyawan') . '</td><td>' . status_badge($user['status']) . '</td><td>' . e($user['last_login_at'] ? date('d M Y, H:i', strtotime($user['last_login_at'])) : 'Belum pernah') . '</td><td><div class="row-actions"><a class="button small secondary" href="' . e(url('user-form', ['id' => $user['id']])) . '">Edit</a><form method="post" class="inline-form confirm-form" data-confirm="Ubah status akun ini?">' . csrf_field() . '<input type="hidden" name="action" value="toggle_user"><input type="hidden" name="id" value="' . e($user['id']) . '"><button class="button small secondary">' . ($user['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan') . '</button></form><form method="post" class="inline-form confirm-form" data-confirm="Reset password pengguna ini?">' . csrf_field() . '<input type="hidden" name="action" value="reset_user_password"><input type="hidden" name="id" value="' . e($user['id']) . '"><button class="button small subtle">Reset password</button></form></div></td></tr>';
    }
    echo '</tbody></table></div></section>';
}

function render_user_form(): void
{
    require_role(['super_admin']);
    $id = (int) ($_GET['id'] ?? 0);
    $account = ['id' => 0, 'name' => '', 'username' => '', 'email' => '', 'role' => 'admin', 'status' => 'active'];
    if ($id) {
        $stmt = db()->prepare("SELECT id,name,username,email,role,status FROM users WHERE id=? AND role IN ('admin','karyawan')");
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if (!$found) {
            render_error(404, 'Akun tidak ditemukan.');
            return;
        }
        $account = $found;
    }
    echo '<form method="post" class="form-layout">' . csrf_field() . '<input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="' . e($id) . '"><section class="panel form-panel"><div class="panel-heading"><div><h2>' . ($id ? 'Edit akun' : 'Akun baru') . '</h2><p>Role hanya dapat diubah oleh Super Admin.</p></div></div><div class="form-grid"><label class="field"><span>Nama <b>*</b></span><input name="name" required maxlength="120" value="' . e($account['name']) . '"></label><label class="field"><span>Username <b>*</b></span><input name="username" required minlength="3" maxlength="40" pattern="[A-Za-z0-9._]+" value="' . e($account['username']) . '"></label><label class="field"><span>Email</span><input type="email" name="email" maxlength="190" value="' . e($account['email']) . '"></label><label class="field"><span>Role <b>*</b></span><select name="role"><option value="admin"' . ($account['role'] === 'admin' ? ' selected' : '') . '>Admin</option><option value="karyawan"' . ($account['role'] === 'karyawan' ? ' selected' : '') . '>Karyawan</option></select></label>';
    if (!$id) {
        echo '<label class="field"><span>Password awal <b>*</b></span><input type="password" name="password" required minlength="10" maxlength="72" autocomplete="new-password"><small>10–72 karakter, berisi huruf dan angka.</small></label>';
    } else {
        echo '<label class="check-filter"><input type="checkbox" name="active" value="1"' . ($account['status'] === 'active' ? ' checked' : '') . '> Akun aktif</label>';
    }
    echo '</div></section><div class="form-actions"><a class="button secondary" href="' . e(url('users')) . '">Batal</a><button class="button primary">Simpan akun</button></div></form>';
}

function render_availability(): void
{
    $search = trim((string) ($_GET['q'] ?? ''));
    $category = (string) ($_GET['category'] ?? '');
    $stmt = db()->prepare('SELECT * FROM materials WHERE is_active=1 AND (? = "" OR name LIKE ? OR material_code LIKE ?) AND (? = "" OR category=?) ORDER BY name LIMIT 150');
    $like = '%' . $search . '%';
    $stmt->execute([$search, $like, $like, $category, $category]);
    $materials = $stmt->fetchAll();
    echo '<div class="availability-intro"><div><span class="live-dot"></span><strong>Saldo terhubung ke inventory</strong><p>Cek kebutuhan hanya membaca stok. Untuk menggunakan bahan, catat pemakaian agar saldo berkurang.</p></div><small>Terakhir dimuat ' . e(date('H:i')) . '</small></div><div class="toolbar"><form method="get" class="filter-form"><input type="hidden" name="page" value="availability"><label class="search-box"><span>⌕</span><input type="search" name="q" value="' . e($search) . '" placeholder="Cari nama atau kode bahan"></label><select name="category"><option value="">Semua kategori</option>';
    foreach (['kain', 'tinta_printing', 'benang'] as $value) {
        echo '<option value="' . e($value) . '"' . ($category === $value ? ' selected' : '') . '>' . e(category_label($value)) . '</option>';
    }
    echo '</select><button class="button secondary">Cari bahan</button></form></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>Bahan</th><th>Kategori</th><th>Stok tersedia</th><th>Status</th><th>Cek kebutuhan</th><th>Aksi</th></tr></thead><tbody>';
    if (!$materials) {
        echo '<tr><td colspan="6"><div class="empty-state"><strong>Bahan tidak ditemukan</strong></div></td></tr>';
    }
    foreach ($materials as $material) {
        $state = material_state($material);
        echo '<tr><td><div class="material-cell"><img src="' . e(material_image($material)) . '" alt=""><span><strong>' . e($material['name']) . '</strong><small>' . e($material['material_code']) . '</small></span></div></td><td>' . e(category_label($material['category'])) . '</td><td><strong>' . e(format_stock($material['current_stock'])) . '</strong> ' . e($material['unit']) . '</td><td>' . status_badge($state) . '</td><td><form class="availability-check" data-stock="' . e($material['current_stock']) . '" data-minimum="' . e($material['minimum_stock']) . '" data-unit="' . e($material['unit']) . '"><label class="sr-only">Jumlah yang dibutuhkan</label><input type="number" min="0.001" step="0.001" required placeholder="Jumlah"><button class="button small secondary">Cek</button><span class="availability-result" aria-live="polite"></span></form></td><td><a class="button small primary" href="' . e(url('use-stock', ['id' => $material['id']])) . '">Gunakan</a></td></tr>';
    }
    echo '</tbody></table></div></section>';
}

function render_reports(): void
{
    $start = (string) ($_GET['start'] ?? date('Y-m-01'));
    $end = (string) ($_GET['end'] ?? date('Y-m-d'));
    if (!valid_date($start)) {
        $start = date('Y-m-01');
    }
    if (!valid_date($end)) {
        $end = date('Y-m-d');
    }
    if ($end < $start) {
        $end = $start;
    }
    $orderStatus = (string) ($_GET['status'] ?? '');
    $orderSearch = substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $orderWhere = ['o.order_date BETWEEN ? AND ?'];
    $orderParams = [$start, $end];
    $allowedStatuses = ['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'];
    if (in_array($orderStatus, $allowedStatuses, true)) {
        $orderWhere[] = 'o.status = ?';
        $orderParams[] = $orderStatus;
    }
    if ($orderSearch !== '') {
        $orderWhere[] = '(o.order_code LIKE ? OR c.name LIKE ?)';
        $orderParams[] = '%' . $orderSearch . '%';
        $orderParams[] = '%' . $orderSearch . '%';
    }
    $ordersStmt = db()->prepare('SELECT o.*,c.name customer_name FROM orders o JOIN customers c ON c.id=o.customer_id WHERE ' . implode(' AND ', $orderWhere) . ' ORDER BY o.order_date DESC LIMIT 500');
    $ordersStmt->execute($orderParams);
    $orders = $ordersStmt->fetchAll();
    $inventoryStmt = db()->prepare("SELECT m.id,m.material_code,m.name,m.unit,m.current_stock,m.minimum_stock,
        COALESCE((SELECT t.stock_before FROM inventory_transactions t WHERE t.material_id=m.id AND t.created_at < ? ORDER BY t.created_at DESC,t.id DESC LIMIT 1),0) AS opening_stock,
        COALESCE((SELECT SUM(t.quantity) FROM inventory_transactions t WHERE t.material_id=m.id AND t.transaction_type IN ('in','adjustment_in','return') AND DATE(t.created_at) BETWEEN ? AND ?),0) AS total_in,
        COALESCE((SELECT SUM(t.quantity) FROM inventory_transactions t WHERE t.material_id=m.id AND t.transaction_type IN ('out','adjustment_out') AND DATE(t.created_at) BETWEEN ? AND ?),0) AS total_out
        FROM materials m ORDER BY m.name");
    $inventoryStmt->execute([$start, $start, $end, $start, $end]);
    $inventory = $inventoryStmt->fetchAll();
    $totalOrders = count($orders);
    $totalJerseys = array_sum(array_map(function ($order) { return (int) $order['quantity']; }, $orders));
    echo '<div class="toolbar"><form method="get" class="filter-form"><input type="hidden" name="page" value="reports"><label class="field-inline"><span>Dari</span><input type="date" name="start" value="' . e($start) . '"></label><label class="field-inline"><span>Sampai</span><input type="date" name="end" value="' . e($end) . '"></label><label class="search-box"><span>⌕</span><input type="search" name="q" value="' . e($orderSearch) . '" placeholder="Kode pesanan / pelanggan"></label><select name="status"><option value="">Semua status</option>';
    foreach ($allowedStatuses as $value) {
        echo '<option value="' . e($value) . '"' . ($orderStatus === $value ? ' selected' : '') . '>' . e($value) . '</option>';
    }
    echo '</select><button class="button secondary">Tampilkan</button></form><button class="button primary no-print" onclick="window.print()">Cetak laporan</button></div><div class="report-period">Periode laporan: <strong>' . e(indonesian_date('d M Y', strtotime($start))) . ' – ' . e(indonesian_date('d M Y', strtotime($end))) . '</strong></div><div class="stat-grid report-stats">';
    stat_card('Total pesanan', (string) $totalOrders, 'Pada periode terpilih');
    stat_card('Total jersey', number_format($totalJerseys), 'Jumlah pada pesanan');
    stat_card('Bahan terdata', (string) count($inventory), 'Saldo inventory terkini');
    $transactionCount = db()->prepare('SELECT COUNT(*) FROM inventory_transactions WHERE DATE(created_at) BETWEEN ? AND ?');
    $transactionCount->execute([$start, $end]);
    stat_card('Mutasi stok', (string) $transactionCount->fetchColumn(), 'Pada periode terpilih');
    echo '</div><section class="panel report-section"><div class="panel-heading"><div><h2>Laporan pesanan</h2><p>Data berdasarkan tanggal pesanan · maksimal 500 hasil</p></div></div><div class="table-wrap"><table><thead><tr><th>Kode</th><th>Tanggal</th><th>Pelanggan</th><th>Ringkasan</th><th>Jumlah</th><th>Status</th></tr></thead><tbody>';
    foreach ($orders as $order) {
        echo '<tr><td>' . e($order['order_code']) . '</td><td>' . e(date('d M Y', strtotime($order['order_date']))) . '</td><td>' . e($order['customer_name']) . '</td><td>' . e(mb_strimwidth($order['jersey_description'], 0, 55, '…')) . '</td><td>' . number_format((int) $order['quantity']) . '</td><td>' . status_badge($order['status']) . '</td></tr>';
    }
    if (!$orders) {
        echo '<tr><td colspan="6" class="muted">Tidak ada pesanan pada periode ini.</td></tr>';
    }
    echo '</tbody></table></div></section><section class="panel report-section"><div class="panel-heading"><div><h2>Laporan inventory</h2><p>Saldo periode dihitung dari ledger. Saldo saat ini berasal dari master inventory.</p></div></div><div class="table-wrap"><table><thead><tr><th>Bahan</th><th>Saldo awal periode</th><th>Stok masuk</th><th>Stok keluar</th><th>Saldo akhir periode</th><th>Saldo saat ini</th><th>Minimum</th></tr></thead><tbody>';
    foreach ($inventory as $item) {
        $closing = (float) $item['opening_stock'] + (float) $item['total_in'] - (float) $item['total_out'];
        echo '<tr><td><strong>' . e($item['name']) . '</strong><small class="block muted">' . e($item['material_code']) . '</small></td><td>' . e(format_stock($item['opening_stock'])) . ' ' . e($item['unit']) . '</td><td class="text-success">+' . e(format_stock($item['total_in'])) . ' ' . e($item['unit']) . '</td><td class="text-danger">−' . e(format_stock($item['total_out'])) . ' ' . e($item['unit']) . '</td><td><strong>' . e(format_stock($closing)) . ' ' . e($item['unit']) . '</strong></td><td>' . e(format_stock($item['current_stock'])) . ' ' . e($item['unit']) . '</td><td>' . e(format_stock($item['minimum_stock'])) . ' ' . e($item['unit']) . '</td></tr>';
    }
    if (!$inventory) {
        echo '<tr><td colspan="7" class="muted">Belum ada bahan.</td></tr>';
    }
    echo '</tbody></table></div></section>';
}

function render_logs(): void
{
    $rows = db()->query('SELECT l.*,u.name username FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 300')->fetchAll();
    echo '<section class="panel"><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Detail</th><th>Entitas</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . e(date('d M Y, H:i:s', strtotime($row['created_at']))) . '</td><td>' . e($row['username'] ?: 'Pengguna tidak dikenal') . '</td><td><span class="table-primary">' . e($row['action']) . '</span></td><td>' . e($row['description']) . '</td><td>' . e($row['entity_type'] ? $row['entity_type'] . ' #' . $row['entity_id'] : '—') . '</td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="5" class="muted">Log aktivitas masih kosong.</td></tr>';
    }
    echo '</tbody></table></div><div class="table-foot">Log bersifat append-only dan tidak dapat dihapus melalui aplikasi.</div></section>';
}

function render_profile(array $user): void
{
    echo '<div class="profile-layout"><section class="panel"><div class="profile-card"><span class="avatar large">' . e(strtoupper(substr($user['name'], 0, 1))) . '</span><div><p class="eyebrow">PROFIL AKUN</p><h2>' . e($user['name']) . '</h2><p class="muted">@' . e($user['username']) . ' · ' . e(ucwords(str_replace('_', ' ', $user['role']))) . '</p></div></div><div class="profile-tip"><strong>Keamanan akun</strong><p>' . (!empty($user['must_change_password']) ? 'Password sementara harus diganti sebelum fitur lain dapat digunakan.' : 'Gunakan password unik dan jangan membagikan kredensial. Hubungi Super Admin bila perlu bantuan akses.') . '</p></div></section><form method="post" class="panel form-panel">' . csrf_field() . '<input type="hidden" name="action" value="change_password"><div class="panel-heading"><div><h2>Ganti password</h2><p>Konfirmasi password saat ini untuk melanjutkan.</p></div></div><label class="field"><span>Password saat ini <b>*</b></span><input type="password" name="current_password" required maxlength="72" autocomplete="current-password"></label><label class="field"><span>Password baru <b>*</b></span><input type="password" name="new_password" required minlength="10" maxlength="72" autocomplete="new-password"><small>10–72 karakter, mengandung huruf dan angka.</small></label><button class="button primary">Perbarui password</button></form></div>';
}

function render_login(): void
{
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#172033"><title>Masuk · DR.SPORT</title><link rel="stylesheet" href="assets/css/app.css"></head><body class="login-page"><div class="login-decoration"></div><main class="login-wrap"><a class="brand login-brand" href="?page=login"><span class="brand-mark">DS</span><span><strong>DR.SPORT</strong><small>OPERATIONS CONSOLE</small></span></a><section class="login-card"><p class="eyebrow">SELAMAT DATANG KEMBALI</p><h1>Masuk ke akun</h1><p class="muted">Kelola pesanan dan inventory dengan lebih terarah.</p>';
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-error">' . e($flash['message']) . '</div>';
    }
    echo '<form method="post" class="login-form">' . csrf_field() . '<input type="hidden" name="action" value="login"><label class="field"><span>Username atau email</span><input name="username" required autocomplete="username" autofocus placeholder="Masukkan username"></label><label class="field"><span>Password</span><div class="password-wrap"><input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password"><button type="button" class="password-toggle" data-toggle-password="login-password" aria-label="Tampilkan password">Lihat</button></div></label><button class="button primary login-submit">Masuk ke dashboard <span>→</span></button></form><div class="login-note"><span class="lock-icon">⌑</span>Akses internal · Akun diberikan oleh Super Admin</div></section><footer class="login-footer">© ' . date('Y') . ' DR.SPORT · Sistem informasi operasional</footer></main></body></html>';
}

try {
    require __DIR__ . '/config/database.php';
    $page = (string) ($_GET['page'] ?? (current_user() ? 'dashboard' : 'login'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handle_post();
    }
    if ($page === 'login') {
        if (current_user()) {
            redirect('dashboard');
        }
        render_login();
        exit;
    }
    $user = require_login();
    if (!empty($user['must_change_password']) && $page !== 'profile') {
        flash('error', 'Demi keamanan, ubah password sementara sebelum melanjutkan.');
        redirect('profile');
    }
    $access = [
        'dashboard' => ['super_admin', 'admin', 'karyawan'],
        'inventory' => ['super_admin', 'admin'],
        'material' => ['super_admin', 'admin', 'karyawan'],
        'material-form' => ['admin'],
        'stock' => ['admin'],
        'history' => ['super_admin', 'admin'],
        'use-stock' => ['karyawan'],
        'usage-history' => ['karyawan'],
        'orders' => ['super_admin', 'admin'],
        'order' => ['super_admin', 'admin'],
        'order-form' => ['admin'],
        'order-edit' => ['admin'],
        'users' => ['super_admin'],
        'user-form' => ['super_admin'],
        'reports' => ['super_admin', 'admin'],
        'logs' => ['super_admin'],
        'availability' => ['karyawan'],
        'profile' => ['super_admin', 'admin', 'karyawan'],
    ];
    if (!isset($access[$page])) {
        http_response_code(404);
        render_error(404, 'Halaman yang Anda cari tidak tersedia.');
        exit;
    }
    if (!in_array($user['role'], $access[$page], true)) {
        log_activity((int) $user['id'], 'access_denied', 'Akses ditolak ke halaman ' . $page . '.');
        http_response_code(403);
        render_error(403, 'Role akun Anda tidak memiliki izin untuk membuka halaman ini.');
        exit;
    }
    render_header(page_title($page), $user, $page);
    switch ($page) {
        case 'dashboard': render_dashboard($user); break;
        case 'inventory': render_inventory($user); break;
        case 'material': render_material_detail($user); break;
        case 'material-form': render_material_form(); break;
        case 'stock': render_stock_form(); break;
        case 'use-stock': render_employee_stock_form(); break;
        case 'history': render_history(); break;
        case 'usage-history': render_employee_usage_history(); break;
        case 'orders': render_orders(); break;
        case 'order-form': render_order_form(); break;
        case 'order-edit': render_order_form(); break;
        case 'order': render_order_detail(); break;
        case 'users': render_users(); break;
        case 'user-form': render_user_form(); break;
        case 'reports': render_reports(); break;
        case 'logs': render_logs(); break;
        case 'availability': render_availability(); break;
        case 'profile': render_profile($user); break;
    }
    render_footer();
} catch (Throwable $error) {
    if (isset($_SESSION['user']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($error instanceof PDOException) {
            if (is_duplicate_key_error($error)) {
                $message = 'Data tersebut sudah digunakan. Periksa kembali kode, username, atau email.';
            } else {
                error_log((string) $error);
                $message = 'Terjadi masalah saat menyimpan ke database. Periksa kembali konfigurasi atau hubungi administrator.';
            }
        } elseif ($error instanceof RuntimeException) {
            $message = $error->getMessage();
        } else {
            error_log((string) $error);
            $message = 'Tindakan tidak dapat diselesaikan. Periksa kembali data yang dikirim atau hubungi administrator.';
        }
        flash('error', $message);
        $fallback = (string) ($_GET['page'] ?? 'dashboard');
        $query = $_GET;
        unset($query['page']);
        redirect($fallback, $query);
    }
    error_log((string) $error);
    http_response_code(500);
    render_error(500, 'Aplikasi tidak dapat memuat data. Periksa koneksi database dan konfigurasi, lalu coba kembali.');
}
