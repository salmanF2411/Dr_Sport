(function () {
    'use strict';

    function init() {
        // Toggle password visibility
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-toggle-password'));
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
                button.textContent = input.type === 'password' ? 'Lihat' : 'Sembunyikan';
            });
        });

        // Konfirmasi form submit
        document.querySelectorAll('.confirm-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm') || 'Lanjutkan tindakan ini?')) {
                    event.preventDefault();
                }
            });
        });

        // Pratinjau upload foto bahan
        document.querySelectorAll('[data-image-form]').forEach(function (form) {
            var input = form.querySelector('[data-image-input]');
            var preview = form.querySelector('[data-image-preview]');
            if (input && preview) {
                input.addEventListener('change', function () {
                    if (input.files && input.files[0]) {
                        preview.src = URL.createObjectURL(input.files[0]);
                    }
                });
            }
        });

        // Cek ketersediaan bahan inline (halaman karyawan)
        document.querySelectorAll('.availability-check').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var needed = Number(form.querySelector('input').value);
                var stock = Number(form.getAttribute('data-stock'));
                var minimum = Number(form.getAttribute('data-minimum'));
                var unit = form.getAttribute('data-unit');
                var output = form.querySelector('.availability-result');
                var formattedStock = new Intl.NumberFormat('en-US', { useGrouping: false, maximumFractionDigits: 3 }).format(stock);
                if (!Number.isFinite(needed) || needed <= 0) {
                    output.textContent = 'Masukkan jumlah yang valid.';
                    output.className = 'availability-result result-error';
                    return;
                }
                if (stock <= 0) {
                    output.textContent = 'Habis · saldo ' + formattedStock + ' ' + unit;
                    output.className = 'availability-result result-error';
                } else if (needed > stock) {
                    output.textContent = 'Tidak cukup · tersedia ' + formattedStock + ' ' + unit;
                    output.className = 'availability-result result-error';
                } else if (stock <= minimum) {
                    output.textContent = 'Stok terbatas · tersedia ' + formattedStock + ' ' + unit;
                    output.className = 'availability-result result-warning';
                } else {
                    output.textContent = 'Stok mencukupi · tersedia ' + formattedStock + ' ' + unit;
                    output.className = 'availability-result result-success';
                }
            });
        });

        // Modal dialog detail mutasi stok
        var modal = document.getElementById('history-detail-modal');
        if (modal) {
            document.querySelectorAll('[data-open-mutation-modal]').forEach(function (button) {
                button.addEventListener('click', function (e) {
                    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
                    e.preventDefault();

                    var title = document.getElementById('hdm-title');
                    var eyebrow = document.getElementById('hdm-eyebrow');
                    var body = document.getElementById('hdm-body');
                    var fullLink = document.getElementById('hdm-full-link');

                    var code = button.getAttribute('data-trx-code') || '—';
                    var typeLabel = button.getAttribute('data-trx-type-label') || '';
                    var date = button.getAttribute('data-trx-date') || '';
                    var matName = button.getAttribute('data-material-name') || '';
                    var matCode = button.getAttribute('data-material-code') || '';
                    var qty = button.getAttribute('data-quantity') || '';
                    var qtyClass = button.getAttribute('data-quantity-class') || 'in';
                    var before = button.getAttribute('data-before') || '—';
                    var after = button.getAttribute('data-after') || '—';
                    var reason = button.getAttribute('data-reason') || '—';
                    var notes = button.getAttribute('data-notes') || '—';
                    var order = button.getAttribute('data-order') || '—';
                    var actor = button.getAttribute('data-actor') || '—';
                    var detailUrl = button.getAttribute('data-detail-url') || button.href;

                    if (title) title.textContent = code;
                    if (eyebrow) eyebrow.textContent = 'DETAIL MUTASI · ' + typeLabel.toUpperCase();
                    if (fullLink) fullLink.href = detailUrl;

                    if (body) {
                        body.innerHTML = 
                            '<div class="mutation-flow">' +
                                '<div class="flow-step"><small>Sebelum</small><strong>' + escapeHtml(before) + '</strong></div>' +
                                '<div class="flow-arrow">→</div>' +
                                '<div class="flow-delta ' + (qtyClass === 'in' ? 'in' : 'out') + '"><small>' + escapeHtml(typeLabel) + '</small>' + escapeHtml(qty) + '</div>' +
                                '<div class="flow-arrow">→</div>' +
                                '<div class="flow-step"><small>Sesudah</small><strong>' + escapeHtml(after) + '</strong></div>' +
                            '</div>' +
                            '<div class="detail-grid">' +
                                '<div class="detail-box full"><small>Bahan</small><strong>' + escapeHtml(matName) + ' (' + escapeHtml(matCode) + ')</strong></div>' +
                                '<div class="detail-box"><small>Waktu Transaksi</small><strong>' + escapeHtml(date) + '</strong></div>' +
                                '<div class="detail-box"><small>Dicatat Oleh</small><strong>' + escapeHtml(actor) + '</strong></div>' +
                                '<div class="detail-box full"><small>Keperluan / Alasan</small><strong>' + escapeHtml(reason) + '</strong></div>' +
                                '<div class="detail-box"><small>Pesanan Terkait</small><strong>' + escapeHtml(order) + '</strong></div>' +
                                '<div class="detail-box"><small>Catatan</small><strong>' + escapeHtml(notes) + '</strong></div>' +
                            '</div>';
                    }

                    if (typeof modal.showModal === 'function') {
                        modal.showModal();
                    } else {
                        modal.setAttribute('open', '');
                    }
                });
            });

            modal.querySelectorAll('[data-close-modal]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (typeof modal.close === 'function') modal.close();
                    else modal.removeAttribute('open');
                });
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    if (typeof modal.close === 'function') modal.close();
                    else modal.removeAttribute('open');
                }
            });
        }

        // ========================================================
        // REAL-TIME AUTO FILTERING (Otomatis Filter saat Klik/Pilih)
        // ========================================================
        function submitFilter(form) {
            if (form.classList.contains('is-submitting')) return;
            form.classList.add('is-submitting');

            // Hapus input pagination jika ada agar kembali ke halaman 1 saat filter diubah
            var pInput = form.querySelector('input[name="p"]');
            if (pInput) pInput.remove();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                HTMLFormElement.prototype.submit.call(form);
            }
        }

        document.querySelectorAll('.filter-form').forEach(function (form) {
            var debounceTimer = null;

            // Auto-submit saat select dropdown diklik atau diubah
            form.querySelectorAll('select').forEach(function (select) {
                select.addEventListener('change', function () {
                    submitFilter(form);
                });
                select.addEventListener('input', function () {
                    submitFilter(form);
                });
            });

            // Auto-submit saat input tanggal dipilih atau diubah
            form.querySelectorAll('input[type="date"]').forEach(function (dateInput) {
                dateInput.addEventListener('change', function () {
                    if (!dateInput.value || dateInput.value.length === 10) {
                        submitFilter(form);
                    }
                });
                dateInput.addEventListener('input', function () {
                    if (dateInput.value && dateInput.value.length === 10) {
                        submitFilter(form);
                    }
                });
            });

            // Auto-submit saat checkbox diklik
            form.querySelectorAll('input[type="checkbox"]').forEach(function (check) {
                check.addEventListener('change', function () {
                    submitFilter(form);
                });
                check.addEventListener('click', function () {
                    submitFilter(form);
                });
            });

            // Auto-submit saat search box diketik (debounce 350ms) atau di-clear
            form.querySelectorAll('input[type="search"], input[name="q"]').forEach(function (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function () {
                        submitFilter(form);
                    }, 350);
                });
                searchInput.addEventListener('search', function () {
                    submitFilter(form);
                });
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitFilter(form);
                    }
                });
            });
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
