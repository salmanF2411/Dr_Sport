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
                    $code,
                    $name,
                    $category,
                    $unit,
                    $minimum,
                    $location ?: null,
                    $notes ?: null,
                    $newImage,
                    isset($_POST['active']) ? 1 : 0,
                    $id
                ]);
                if ($image || $clearImage) {
                    $oldImageToRemove = $oldImage;
                }
                log_activity($actorId, 'material_updated', 'Memperbarui bahan ' . $code . '.', 'material', $id);
            } else {
                $pdo->prepare('INSERT INTO materials (material_code, name, category, unit, minimum_stock, location, notes, image_path, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')->execute([
                    $code,
                    $name,
                    $category,
                    $unit,
                    $minimum,
                    $location ?: null,
                    $notes ?: null,
                    $image,
                    $actorId
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
        redirect('admin/inventory/index.php');
    }

    if ($action === 'save_storage_location') {
        require_role(['super_admin', 'admin']);
        $id = (int) ($_POST['id'] ?? 0);
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!preg_match('/^[A-Z0-9-_.]{2,40}$/', $code) || $name === '' || strlen($name) > 120 || strlen($description) > 255) {
            throw new RuntimeException('Kode rak (2–40 karakter huruf/angka/tanda hubung) dan nama rak wajib diisi.');
        }

        $pdo = db();
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM storage_locations WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                throw new RuntimeException('Data rak tidak ditemukan.');
            }
            $dup = $pdo->prepare('SELECT COUNT(*) FROM storage_locations WHERE code = ? AND id <> ?');
            $dup->execute([$code, $id]);
            if ((int) $dup->fetchColumn() > 0) {
                throw new RuntimeException('Kode rak ' . $code . ' sudah digunakan oleh rak lain.');
            }
            $oldName = (string) $existing['name'];
            $pdo->prepare('UPDATE storage_locations SET code = ?, name = ?, description = ?, is_active = ? WHERE id = ?')->execute([
                $code, $name, $description ?: null, $isActive, $id
            ]);
            if ($oldName !== '' && $oldName !== $name) {
                $pdo->prepare('UPDATE materials SET location = ? WHERE location = ?')->execute([$name, $oldName]);
            }
            log_activity($actorId, 'storage_location_updated', 'Memperbarui data rak ' . $code . ' (' . $name . ').', 'storage_location', $id);
            flash('success', 'Data rak ' . $name . ' berhasil diperbarui.');
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM storage_locations WHERE code = ?');
            $dup->execute([$code]);
            if ((int) $dup->fetchColumn() > 0) {
                throw new RuntimeException('Kode rak ' . $code . ' sudah terdaftar.');
            }
            $pdo->prepare('INSERT INTO storage_locations (code, name, description, is_active, created_by) VALUES (?, ?, ?, 1, ?)')->execute([
                $code, $name, $description ?: null, $actorId
            ]);
            $id = (int) $pdo->lastInsertId();
            log_activity($actorId, 'storage_location_created', 'Menambahkan rak baru ' . $code . ' (' . $name . ').', 'storage_location', $id);
            flash('success', 'Rak ' . $name . ' berhasil ditambahkan.');
        }
        redirect('admin/inventory/storage-locations.php');
    }

    if ($action === 'toggle_storage_location') {
        require_role(['super_admin', 'admin']);
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT id, code, name, is_active FROM storage_locations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Rak tidak ditemukan.');
        }
        $next = (int) $row['is_active'] ? 0 : 1;
        db()->prepare('UPDATE storage_locations SET is_active = ? WHERE id = ?')->execute([$next, $id]);
        log_activity($actorId, 'storage_location_toggle', 'Mengubah status rak ' . $row['code'] . ' menjadi ' . ($next ? 'aktif' : 'nonaktif') . '.', 'storage_location', $id);
        flash('success', 'Status rak ' . $row['name'] . ' berhasil diubah menjadi ' . ($next ? 'aktif' : 'nonaktif') . '.');
        redirect('admin/inventory/storage-locations.php');
    }

    if ($action === 'delete_storage_location') {
        require_role(['super_admin', 'admin']);
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT id, code, name FROM storage_locations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Rak tidak ditemukan.');
        }
        $chk = db()->prepare('SELECT COUNT(*) FROM materials WHERE location = ? OR location = ?');
        $chk->execute([$row['name'], $row['code']]);
        $usedCount = (int) $chk->fetchColumn();
        if ($usedCount > 0) {
            throw new RuntimeException('Rak tidak dapat dihapus karena masih digunakan oleh ' . $usedCount . ' bahan di inventory. Silakan nonaktifkan rak atau ubah lokasi bahan terlebih dahulu.');
        }
        db()->prepare('DELETE FROM storage_locations WHERE id = ?')->execute([$id]);
        log_activity($actorId, 'storage_location_deleted', 'Menghapus rak ' . $row['code'] . ' (' . $row['name'] . ').', 'storage_location', $id);
        flash('success', 'Rak ' . $row['name'] . ' berhasil dihapus.');
        redirect('admin/inventory/storage-locations.php');
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
        redirect('admin/inventory/index.php');
    }

    if ($action === 'employee_stock_use' || $action === 'employee_request_stock') {
        require_role(['karyawan']);
        $materialId = (int) ($_POST['material_id'] ?? 0);
        $quantity = trim((string) ($_POST['quantity'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if (!valid_stock_amount($quantity, false) || $reason === '' || strlen($reason) > 180 || strlen($notes) > 1000) {
            throw new RuntimeException('Jumlah pengajuan dan keperluan wajib diisi dengan benar.');
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, material_code, name, current_stock, unit, is_active FROM materials WHERE id = ?');
        $stmt->execute([$materialId]);
        $mat = $stmt->fetch();
        if (!$mat || !(int) $mat['is_active']) {
            throw new RuntimeException('Bahan tidak ditemukan atau sudah dinonaktifkan.');
        }

        // Generate kode pengajuan unik
        $requestCode = 'REQ-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $pdo->prepare('INSERT INTO material_requests (request_code, material_id, user_id, quantity, reason, notes, status) VALUES (?, ?, ?, ?, ?, ?, "pending")')->execute([
            $requestCode,
            $materialId,
            $actorId,
            number_format((float) $quantity, 3, '.', ''),
            $reason,
            $notes ?: null
        ]);
        $requestId = (int) $pdo->lastInsertId();

        log_activity($actorId, 'material_request_created', 'Mengajukan pemakaian ' . $mat['name'] . ' sebanyak ' . format_stock($quantity) . ' ' . $mat['unit'] . ' (' . $requestCode . ').', 'material_request', $requestId);

        flash('success', 'Pengajuan pemakaian bahan (' . $requestCode . ') berhasil dikirim ke Admin. Stok akan berkurang setelah disetujui.');
        redirect('karyawan/usage-history.php');
    }

    if ($action === 'approve_material_request') {
        require_role(['super_admin', 'admin']);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));

        if ($requestId <= 0) {
            throw new RuntimeException('ID pengajuan tidak valid.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT r.*, m.material_code, m.name AS material_name, m.unit, m.current_stock, u.name AS requester_name FROM material_requests r JOIN materials m ON m.id = r.material_id JOIN users u ON u.id = r.user_id WHERE r.id = ? FOR UPDATE');
            $stmt->execute([$requestId]);
            $req = $stmt->fetch();

            if (!$req) {
                throw new RuntimeException('Pengajuan tidak ditemukan.');
            }
            if ($req['status'] !== 'pending') {
                throw new RuntimeException('Pengajuan ini sudah pernah diproses sebelumnya (' . request_status_label($req['status']) . ').');
            }

            $reqQty = (float) $req['quantity'];
            $currentStock = (float) $req['current_stock'];

            if ($reqQty > $currentStock) {
                throw new RuntimeException('Stok bahan tidak mencukupi untuk menyetujui pengajuan ini. Saldo tersedia saat ini: ' . format_stock($currentStock) . ' ' . $req['unit'] . ', dibutuhkan: ' . format_stock($reqQty) . ' ' . $req['unit'] . '.');
            }

            // Catat ke inventory_transactions dan kurangi saldo stok
            $transactionReason = 'Pengajuan disetujui: ' . $req['reason'];
            $transactionNotes = 'Pemohon: ' . $req['requester_name'] . ' (' . $req['request_code'] . ')' . ($adminNotes !== '' ? ' | Catatan Admin: ' . $adminNotes : '') . ($req['notes'] ? ' | Catatan Pemohon: ' . $req['notes'] : '');
            $transactionId = record_inventory($pdo, (int) $req['material_id'], 'out', $reqQty, null, $transactionReason, $transactionNotes, $actorId);

            // Perbarui status pengajuan
            $pdo->prepare('UPDATE material_requests SET status = "approved", approved_by = ?, approved_at = NOW(), admin_notes = ?, inventory_transaction_id = ? WHERE id = ?')->execute([
                $actorId,
                $adminNotes ?: null,
                $transactionId,
                $requestId
            ]);

            log_activity($actorId, 'material_request_approved', 'Menyetujui pengajuan ' . $req['request_code'] . ' (' . $req['material_name'] . ' ' . format_stock($reqQty) . ' ' . $req['unit'] . ').', 'material_request', $requestId);

            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        flash('success', 'Pengajuan ' . $req['request_code'] . ' berhasil disetujui. Stok bahan telah berkurang sebesar ' . format_stock($req['quantity']) . ' ' . $req['unit'] . '.');
        $redirectUrl = (string) ($_POST['redirect_to'] ?? '');
        if ($redirectUrl !== '') {
            header('Location: ' . $redirectUrl);
            exit;
        }
        redirect('admin/requests/index.php');
    }

    if ($action === 'reject_material_request') {
        require_role(['super_admin', 'admin']);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));

        if ($requestId <= 0) {
            throw new RuntimeException('ID pengajuan tidak valid.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT r.*, m.name AS material_name, m.unit FROM material_requests r JOIN materials m ON m.id = r.material_id WHERE r.id = ? FOR UPDATE');
            $stmt->execute([$requestId]);
            $req = $stmt->fetch();

            if (!$req) {
                throw new RuntimeException('Pengajuan tidak ditemukan.');
            }
            if ($req['status'] !== 'pending') {
                throw new RuntimeException('Pengajuan ini sudah pernah diproses sebelumnya (' . request_status_label($req['status']) . ').');
            }

            $pdo->prepare('UPDATE material_requests SET status = "rejected", approved_by = ?, approved_at = NOW(), admin_notes = ? WHERE id = ?')->execute([
                $actorId,
                $adminNotes ?: 'Pengajuan ditolak oleh Admin',
                $requestId
            ]);

            log_activity($actorId, 'material_request_rejected', 'Menolak pengajuan ' . $req['request_code'] . ' (' . $req['material_name'] . ').' . ($adminNotes ? ' Alasan: ' . $adminNotes : ''), 'material_request', $requestId);

            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        flash('warning', 'Pengajuan ' . $req['request_code'] . ' telah ditolak.');
        $redirectUrl = (string) ($_POST['redirect_to'] ?? '');
        if ($redirectUrl !== '') {
            header('Location: ' . $redirectUrl);
            exit;
        }
        redirect('admin/requests/index.php');
    }

    throw new RuntimeException('Aksi inventory tidak dikenal.');
} catch (Throwable $error) {
    if ($error instanceof PDOException) {
        if (is_duplicate_key_error($error)) {
            $message = 'Kode bahan, kode rak, atau data tersebut sudah ada. Gunakan kode yang berbeda.';
        } else {
            error_log((string) $error);
            $message = 'Terjadi kesalahan saat memproses data inventory.';
        }
    } elseif ($error instanceof RuntimeException) {
        $message = $error->getMessage();
    } else {
        error_log((string) $error);
        $message = 'Tindakan inventory gagal diselesaikan.';
    }
    flash('error', $message);
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referrer !== '') {
        header('Location: ' . $referrer);
        exit;
    }
    redirect('admin/inventory/index.php');
}
