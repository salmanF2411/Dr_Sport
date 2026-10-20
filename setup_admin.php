<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Gunakan skrip ini melalui PHP CLI.\n");
}

require __DIR__ . '/config/database.php';
$config = require __DIR__ . '/config/database.local.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['database']),
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$lock = (int) $pdo->query("SELECT GET_LOCK('drsport_initial_super_admin', 10)")->fetchColumn();
if ($lock !== 1) {
    fwrite(STDERR, "Setup Super Admin sedang berjalan atau tidak dapat mengunci proses. Coba kembali.\n");
    exit(1);
}

$count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn();
if ($count > 0) {
    $pdo->query("SELECT RELEASE_LOCK('drsport_initial_super_admin')");
    fwrite(STDERR, "Super Admin sudah tersedia. Setup dihentikan.\n");
    exit(1);
}

$read = function (string $prompt): string {
    fwrite(STDOUT, $prompt);
    return trim((string) fgets(STDIN));
};
$name = $read('Nama Super Admin: ');
$username = $read('Username (3-40 karakter): ');
$email = $read('Email (opsional): ');
$password = $read('Password (10-72 karakter, huruf dan angka): ');

if ($name === '' || !preg_match('/^[a-zA-Z0-9._]{3,40}$/', $username) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
    strlen($password) < 10 || strlen($password) > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $pdo->query("SELECT RELEASE_LOCK('drsport_initial_super_admin')");
    fwrite(STDERR, "Input tidak valid. Username harus 3-40 karakter; password harus 10-72 karakter. Tidak ada akun yang dibuat.\n");
    exit(1);
}

$stmt = $pdo->prepare("INSERT INTO users (name, username, email, password_hash, role, status, password_changed_at) VALUES (?, ?, ?, ?, 'super_admin', 'active', NOW())");
$stmt->execute([$name, $username, $email ?: null, password_hash($password, PASSWORD_DEFAULT)]);
$pdo->query("SELECT RELEASE_LOCK('drsport_initial_super_admin')");
fwrite(STDOUT, "Akun Super Admin berhasil dibuat. Hapus atau pindahkan setup_admin.php setelah setup selesai.\n");
