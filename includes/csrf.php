<?php
declare(strict_types=1);

/**
 * Mendapatkan atau membuat token CSRF aktif untuk sesi saat ini.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Menghasilkan input hidden HTML berisi token CSRF.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Memvalidasi token CSRF dari formulir POST.
 */
function verify_csrf(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!$token || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        if (function_exists('render_error')) {
            render_error(403, 'Sesi formulir sudah kedaluwarsa atau token CSRF tidak valid. Muat ulang halaman dan coba kembali.');
        } else {
            echo '403 Akses Ditolak: Sesi formulir sudah kedaluwarsa atau token CSRF tidak valid.';
        }
        exit;
    }
}
