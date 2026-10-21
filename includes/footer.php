<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
?>
        </main>
        <footer class="footer">
            <span>DR.SPORT · Sistem internal pengelolaan pesanan & inventory</span>
            <span>Data stok diperbarui dari database</span>
        </footer>
    </div>
</div>

<!-- Modal Dialog Detail Mutasi Stok -->
<dialog id="history-detail-modal" class="modal-dialog">
    <div class="modal-header">
        <div>
            <p class="eyebrow" id="hdm-eyebrow" style="margin:0 0 2px; font-size:10px;">DETAIL MUTASI STOK</p>
            <h3 id="hdm-title" style="margin:0; font-size:16px;">—</h3>
        </div>
        <button type="button" class="modal-close-btn" data-close-modal title="Tutup (Esc)">✕</button>
    </div>
    <div class="modal-body" id="hdm-body"></div>
    <div class="modal-footer">
        <a href="#" id="hdm-full-link" class="button secondary small">Buka Halaman Lengkap ↗</a>
        <button type="button" class="button primary small" data-close-modal>Tutup</button>
    </div>
</dialog>

<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
