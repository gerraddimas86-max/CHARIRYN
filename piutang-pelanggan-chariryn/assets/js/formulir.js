/**
 * formulir.js
 * Format angka Rupiah pada input dan pratinjau saldo pembayaran.
 */
(function () {
    'use strict';

    function formatRupiah(nilai) {
        return 'Rp ' + Number(nilai).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    /* Format input bertanda data-rupiah (contoh: 1500000 -> 1.500.000) */
    document.querySelectorAll('input[data-rupiah]').forEach(function (input) {
        input.addEventListener('input', function () {
            var angka = input.value.replace(/[^\d]/g, '');
            input.value = angka === '' ? '' : Number(angka).toLocaleString('id-ID');
        });
    });

    /* Mask tanggal Indonesia dd/mm/yyyy (ketik angka, garis miring otomatis) */
    document.querySelectorAll('input[data-tanggal-id]').forEach(function (input) {
        input.addEventListener('input', function () {
            var angka = input.value.replace(/[^\d]/g, '').slice(0, 8);
            var hasil = angka.slice(0, 2);
            if (angka.length > 2) { hasil += '/' + angka.slice(2, 4); }
            if (angka.length > 4) { hasil += '/' + angka.slice(4, 8); }
            input.value = hasil;
        });
    });

    /* Pratinjau pembayaran: piutang dipilih -> info pelanggan + sisa setelah bayar */
    var wadahData = document.getElementById('dataPiutang');
    var pilihPiutang = document.getElementById('id_piutang');
    var inputJumlah = document.getElementById('jumlah_bayar');
    var jumlahLama = document.getElementById('jumlah_lama');

    if (wadahData && pilihPiutang && inputJumlah) {
        var daftarPiutang = [];
        try {
            daftarPiutang = JSON.parse(wadahData.textContent || '[]');
        } catch (err) {
            daftarPiutang = [];
        }

        var elemen = {
            nama: document.getElementById('pratinjauNama'),
            total: document.getElementById('pratinjauTotal'),
            dibayar: document.getElementById('pratinjauDibayar'),
            sisa: document.getElementById('pratinjauSisa'),
            sisaSetelah: document.getElementById('pratinjauSisaSetelah'),
            peringatan: document.getElementById('peringatanLebih'),
        };

        function perbarui() {
            var data = null;
            for (var i = 0; i < daftarPiutang.length; i++) {
                if (String(daftarPiutang[i].id_piutang) === pilihPiutang.value) {
                    data = daftarPiutang[i];
                    break;
                }
            }

            if (!data) {
                return;
            }

            elemen.nama.textContent = data.nama_pelanggan;
            elemen.total.textContent = formatRupiah(data.total_piutang);
            elemen.dibayar.textContent = formatRupiah(data.jumlah_dibayar);
            elemen.sisa.textContent = formatRupiah(data.sisa_piutang);

            var masuk = parseInt((inputJumlah.value || '0').replace(/\./g, ''), 10) || 0;
            var sisaDasar = Number(data.sisa_piutang);
            if (jumlahLama && jumlahLama.value) {
                sisaDasar = sisaDasar + Number(jumlahLama.value);
            }
            var sisaBaru = sisaDasar - masuk;

            elemen.sisaSetelah.textContent = formatRupiah(Math.max(sisaBaru, 0));
            elemen.sisaSetelah.classList.toggle('teks-bahaya', sisaBaru < 0);
            elemen.peringatan.hidden = sisaBaru >= 0;
        }

        pilihPiutang.addEventListener('change', perbarui);
        inputJumlah.addEventListener('input', perbarui);
        perbarui();
    }

    /* Nonaktifkan baris piutang yang sudah lunas pada pilihan pembayaran */
    var pilihBayar = document.getElementById('id_piutang');
    if (pilihBayar && pilihBayar.options) {
        Array.prototype.forEach.call(pilihBayar.options, function (opsi) {
            if (opsi.dataset.status === 'Lunas') {
                opsi.disabled = true;
            }
        });
    }
})();