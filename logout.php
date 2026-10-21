<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = current_user();
if ($user) {
    try {
        log_activity((int) $user['id'], 'logout', 'Pengguna keluar dari sistem.');
    } catch (Throwable $e) {
        error_log('Unable to log logout: ' . $e->getMessage());
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
redirect('login.php');
