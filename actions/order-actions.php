<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

verify_csrf();
$action = (string) ($_POST['action'] ?? '');
$user = require_login();
$actorId = (int) $user['id'];

try {
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
                    $name,
                    $phone,
                    $address ?: null,
                    $customerId
                ]);
            } else {
                $pdo->prepare('INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)')->execute([
                    $name,
                    $phone,
                    $address ?: null
                ]);
                $customerId = (int) $pdo->lastInsertId();
            }

            $notes = $notes ?: null;
            if ($orderId) {
                $pdo->prepare('UPDATE orders SET customer_id = ?, order_date = ?, target_date = ?, jersey_description = ?, quantity = ?, notes = ? WHERE id = ?')->execute([
                    $customerId,
                    $orderDate,
                    $targetDate,
                    $description,
                    $quantity,
                    $notes,
                    $orderId
                ]);
                $orderCode = (string) $existingOrder['order_code'];
                log_activity($actorId, 'order_updated', 'Memperbarui data pesanan ' . $orderCode . '.', 'order', $orderId);
            } else {
                $orderCode = 'ORD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $pdo->prepare("INSERT INTO orders (order_code, customer_id, order_date, target_date, jersey_description, quantity, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Pesanan Masuk', ?, ?)")->execute([
                    $orderCode,
                    $customerId,
                    $orderDate,
                    $targetDate,
                    $description,
                    $quantity,
                    $notes,
                    $actorId
                ]);
                $orderId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by) VALUES (?, NULL, ?, ?)')->execute([
                    $orderId,
                    'Pesanan Masuk',
                    $actorId
                ]);
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
        redirect('admin/orders/detail.php', ['id' => $orderId]);
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
            $pdo->prepare('UPDATE orders SET status = ?, cancelled_at = ? WHERE id = ?')->execute([
                $next,
                $next === 'Dibatalkan' ? date('Y-m-d H:i:s') : null,
                $orderId
            ]);
            $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_by) VALUES (?, ?, ?, ?, ?)')->execute([
                $orderId,
                $old,
                $next,
                trim((string) ($_POST['note'] ?? '')) ?: null,
                $actorId
            ]);
            log_activity($actorId, 'order_status_changed', 'Status pesanan diubah dari ' . $old . ' menjadi ' . $next . '.', 'order', $orderId);
        }
        $pdo->commit();
        flash('success', 'Status pesanan diperbarui.');
        redirect('admin/orders/detail.php', ['id' => $orderId]);
    }

    throw new RuntimeException('Aksi pesanan tidak dikenal.');
} catch (Throwable $error) {
    if ($error instanceof PDOException) {
        if (is_duplicate_key_error($error)) {
            $message = 'Kode pesanan atau data tersebut sudah ada. Silakan coba kembali.';
        } else {
            error_log((string) $error);
            $message = 'Terjadi kesalahan saat menyimpan pesanan. Silakan periksa data Anda.';
        }
    } elseif ($error instanceof RuntimeException) {
        $message = $error->getMessage();
    } else {
        error_log((string) $error);
        $message = 'Tindakan pesanan gagal diselesaikan.';
    }
    flash('error', $message);
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referrer !== '') {
        header('Location: ' . $referrer);
        exit;
    }
    redirect('admin/orders/index.php');
}
