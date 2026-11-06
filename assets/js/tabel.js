/**
 * tabel.js
 * Bantuan tabel: tombol cetak dan validasi rentang tanggal pada filter.
 */
(function () {
    'use strict';

    /* Tombol cetak laporan */
    document.querySelectorAll('.tombol-cetak').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            window.print();
        });
    });

    /* Validasi rentang tanggal pada filter (dari tidak boleh melebihi sampai) */
    document.querySelectorAll('form.form-filter-tanggal').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            function normalisasi(nilai) {
                var m = /^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/.exec((nilai || '').trim());
                if (!m) { return ''; }
                return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
            }
            var dari = form.querySelector('[data-tanggal-dari]');
            var sampai = form.querySelector('[data-tanggal-sampai]');

            if (dari && sampai && dari.value && sampai.value) {
                var d = normalisasi(dari.value) || dari.value;
                var s = normalisasi(sampai.value) || sampai.value;
                if (d > s) {
                    e.preventDefault();
                    alert('Tanggal awal tidak boleh melebihi tanggal akhir.');
                }
            }
        });
    });
    /* Filter realtime: ketik di search langsung cari (debounce),
       ubah select/tanggal langsung terapkan. Fokus search dikembalikan
       setelah reload agar bisa lanjut mengetik. */
    document.querySelectorAll('form.form-filter-halaman').forEach(function (form) {
        var pemicu = null;
        var terakhir = null;
        var teks = form.querySelector('input[type="text"], input[type="search"], input:not([type])');
        if (teks) {
            terakhir = teks.value;
            teks.addEventListener('input', function () {
                if (teks.value === terakhir) { return; }
                clearTimeout(pemicu);
                pemicu = setTimeout(function () {
                    terakhir = teks.value;
                    if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
                }, 400);
            });
            /* Lanjut mengetik setelah reload hasil */
            if (teks.value !== '' && !teks.disabled) {
                try {
                    teks.focus({ preventScroll: true });
                    teks.setSelectionRange(teks.value.length, teks.value.length);
                } catch (err) { /* abaikan */ }
            }
        }
        form.querySelectorAll('select, input[type="date"]').forEach(function (masukan) {
            masukan.addEventListener('change', function () {
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            });
        });
    });
})();