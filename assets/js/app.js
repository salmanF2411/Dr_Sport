document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            button.textContent = input.type === 'password' ? 'Lihat' : 'Sembunyikan';
        });
    });

    document.querySelectorAll('.confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm') || 'Lanjutkan tindakan ini?')) {
                event.preventDefault();
            }
        });
    });

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
});
