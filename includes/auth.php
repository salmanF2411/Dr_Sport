<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Inisialisasi sesi aman
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

// Timeout sesi idle setelah 30 menit tidak ada aktivitas
if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

/**
 * Mendapatkan data pengguna yang sedang login dari database.
 */
function current_user(): ?array
{
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT id, name, username, role, status, password_changed_at FROM users WHERE id = ? LIMIT 1');
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
    } catch (Throwable $e) {
        error_log('Error fetching current user: ' . $e->getMessage());
        return null;
    }
}

/**
 * Mewajibkan login. Jika belum login, diarahkan ke halaman login.
 */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

/**
 * Mewajibkan role tertentu. Menolak akses jika role tidak cocok.
 */
function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        if (function_exists('log_activity')) {
            log_activity((int) $user['id'], 'access_denied', 'Akses ditolak untuk peran pengguna: ' . $user['role']);
        }
        http_response_code(403);
        if (function_exists('render_error')) {
            render_error(403, 'Role akun Anda tidak memiliki izin untuk membuka halaman ini.');
        } else {
            echo '403 Akses Ditolak: Anda tidak memiliki izin untuk membuka halaman ini.';
        }
        exit;
    }
    return $user;
}

/**
 * Validasi kekuatan password: 10–72 karakter, mengandung huruf dan angka.
 */
function valid_password(string $password): bool
{
    return strlen($password) >= 10 && strlen($password) <= 72 &&
        preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
}
