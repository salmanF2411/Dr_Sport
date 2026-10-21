<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role(['super_admin']);
$pageTitle = 'Log Aktivitas';
$activePage = 'superadmin_logs';

$rows = db()->query('SELECT l.*,u.name username FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 300')->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>
<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Aktivitas</th>
                    <th>Detail</th>
                    <th>Entitas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="5" class="muted">Log aktivitas masih kosong.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(date('d M Y, H:i:s', strtotime($row['created_at']))) ?></td>
                        <td><?= e($row['username'] ?: 'Pengguna tidak dikenal') ?></td>
                        <td><span class="table-primary"><?= e($row['action']) ?></span></td>
                        <td><?= e($row['description']) ?></td>
                        <td><?= e($row['entity_type'] ? $row['entity_type'] . ' #' . $row['entity_id'] : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-foot">Log bersifat append-only dan tidak dapat dihapus melalui aplikasi.</div>
</section>

<?php
require_once __DIR__ . '/../../includes/footer.php';
