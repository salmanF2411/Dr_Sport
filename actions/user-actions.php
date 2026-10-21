<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

verify_csrf();
$action = (string) ($_POST['action'] ?? '');
$user = current_user();

try {
    if ($action === 'login') {
        $now = time();
        $attempts = $_SESSION['login_attempts'] ?? [];
        $attempts = array_values(array_filter($attempts, function ($time) use ($now) {
            return $time > $now - 900;
        }));
        $remoteAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $ipAttempts = 0;
        if ($remoteAddress !== '') {
            $rateLimit = db()->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed' AND ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $rateLimit->execute([$remoteAddress]);
            $ipAttempts = (int) $rateLimit->fetchColumn();
        }
        if (count($attempts) >= 5 || $ipAttempts >= 20) {
            flash('error', 'Terlalu banyak percobaan. Silakan coba kembali dalam 15 menit.');
            redirect('login.php');
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
            redirect('login.php');
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
        redirect($account['password_changed_at'] === null ? 'profile.php' : 'dashboard');
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
        redirect('login.php');
    }

    if (!$user) {
        redirect('login.php');
    }

    if (!empty($user['must_change_password']) && $action !== 'change_password') {
        flash('error', 'Demi keamanan, ubah password sementara sebelum melanjutkan.');
        redirect('profile.php');
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
            $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?')->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $actorId
            ]);
            log_activity($actorId, 'password_changed', 'Pengguna mengganti password.');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        flash('success', 'Password berhasil diperbarui.');
        redirect('profile.php');
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
                    $name,
                    $username,
                    $email ?: null,
                    $role,
                    isset($_POST['active']) ? 'active' : 'inactive',
                    $id
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
        redirect('superadmin/users/index.php');
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
        redirect('superadmin/users/index.php');
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
        redirect('superadmin/users/index.php');
    }

    throw new RuntimeException('Aksi tidak dikenal.');
} catch (Throwable $error) {
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
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referrer !== '') {
        header('Location: ' . $referrer);
        exit;
    }
    redirect('dashboard');
}
