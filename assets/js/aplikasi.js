/**
 * aplikasi.js
 * Sidebar mobile, flash message, pencegahan submit ganda.
 */
(function () {
    'use strict';

    /* --- Sidebar: tombol di dalam panel (ciut desktop / tutup mobile), FAB khusus mobile --- */
    var tombolMenu = document.getElementById('tombolMenu');
    var tombolFab = document.getElementById('tombolFab');
    var lapisan = document.getElementById('lapisanSidebar');
    var mqDesktop = window.matchMedia('(min-width: 1024px)');

    function tutupSidebar() {
        document.body.classList.remove('sidebar-terbuka');
        if (lapisan) { lapisan.setAttribute('hidden', ''); }
        if (tombolMenu) { tombolMenu.setAttribute('aria-expanded', 'false'); }
    }

    function bukaSidebar() {
        document.body.classList.add('sidebar-terbuka');
        if (lapisan) { lapisan.removeAttribute('hidden'); }
        if (tombolMenu) { tombolMenu.setAttribute('aria-expanded', 'true'); }
    }

    try {
        if (mqDesktop.matches && localStorage.getItem('sidebarCiut') === '1') {
            document.body.classList.add('sidebar-ciut');
        }
    } catch (err) { /* abaikan: penyimpanan tidak tersedia */ }

    function labelTombolMenu() {
        if (!tombolMenu) { return; }
        var ciut = document.body.classList.contains('sidebar-ciut');
        tombolMenu.setAttribute('aria-label', ciut ? 'Buka panel samping' : 'Tutup panel samping');
    }
    labelTombolMenu();

    if (tombolMenu) {
        tombolMenu.addEventListener('click', function () {
            if (mqDesktop.matches) {
                document.body.classList.toggle('sidebar-ciut');
                try {
                    localStorage.setItem('sidebarCiut', document.body.classList.contains('sidebar-ciut') ? '1' : '0');
                } catch (err) { /* abaikan */ }
                labelTombolMenu();
            } else {
                tutupSidebar();
            }
        });
    }

    if (tombolFab) {
        tombolFab.addEventListener('click', bukaSidebar);
    }

    if (lapisan) {
        lapisan.addEventListener('click', tutupSidebar);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            tutupSidebar();
        }
    });

    /* --- Pencegahan submit ganda --- */
    document.querySelectorAll('form[data-anti-ganda]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var tombol = form.querySelector('button[type="submit"]:not([disabled])');
            if (tombol) {
                tombol.disabled = true;
                tombol.dataset.teksAsli = tombol.textContent;
                tombol.textContent = 'Memproses…';
            }
        });
    });

    /* --- Tutup flash message --- */
    document.querySelectorAll('.alert-tutup').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            var alert = tombol.closest('.alert');
            if (alert) { alert.remove(); }
        });
    });

    /* Tutup alert otomatis setelah 6 detik */
    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (alert) {
            alert.remove();
        });
    }, 6000);

    /* --- Modal baca/tindakan (detail, histori, tambah/edit, konfirmasi hapus) --- */
    var modalLapisan = document.getElementById('modalLapisan');
    var modalJudul = document.getElementById('modalJudul');
    var modalIsi = document.getElementById('modalIsi');
    var modalTutup = document.getElementById('modalTutup');
    var modalBukaHalaman = document.getElementById('modalBukaHalaman');
    var modalUrl = '';

    function tutupModal() {
        if (!modalLapisan) { return; }
        modalLapisan.setAttribute('hidden', '');
        if (modalIsi) { modalIsi.innerHTML = ''; }
    }

    function bukaModal(judul, html) {
        if (!modalLapisan || !modalIsi || !modalJudul) { return false; }
        modalJudul.textContent = judul;
        if (typeof html === 'string') { modalIsi.innerHTML = html; }
        modalLapisan.removeAttribute('hidden');
        if (modalTutup) { modalTutup.focus(); }
        return true;
    }

    /* Format angka pada input Rupiah yang dimuat di dalam modal */
    function formatRupiahModal() {
        if (!modalIsi) { return; }
        modalIsi.querySelectorAll('input[data-rupiah]').forEach(function (input) {
            if (input.dataset.modalSiap) { return; }
            input.dataset.modalSiap = '1';
            input.addEventListener('input', function () {
                var angka = input.value.replace(/[^\d]/g, '');
                input.value = angka === '' ? '' : Number(angka).toLocaleString('id-ID');
            });
        });
    }

    if (modalTutup) {
        modalTutup.addEventListener('click', tutupModal);
    }

    if (modalLapisan) {
        modalLapisan.addEventListener('click', function (e) {
            if (e.target === modalLapisan) { tutupModal(); }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modalLapisan && !modalLapisan.hasAttribute('hidden')) {
            tutupModal();
        }
    });

    /* Batal/Kembali di dalam modal: tutup modal, jangan pindah halaman.
       Di halaman penuh (bukan modal) tautan ini tetap navigasi normal. */
    if (modalIsi) {
        modalIsi.addEventListener('click', function (e) {
            var batal = e.target.closest('a.btn-netral[href]:not([data-modal])');
            if (!batal) { return; }
            e.preventDefault();
            tutupModal();
        });
    }

    /* Konfirmasi tanpa alert browser: data-konfirmasi="pesan" + href/form */
    document.addEventListener('click', function (e) {
        var pemicu = e.target.closest('[data-konfirmasi]');
        if (!pemicu || !modalLapisan) { return; }
        e.preventDefault();
        var pesan = pemicu.getAttribute('data-konfirmasi') || 'Yakin melanjutkan tindakan ini?';
        var html = '<p>' + pesan.replace(/</g, '&lt;') + '</p>'
            + '<div class="modal-konfirmasi-aksi">'
            + '<button type="button" class="btn btn-netral" data-modal-batal>Batal</button>'
            + '<button type="button" class="btn btn-primer" data-modal-ya>Ya, Lanjutkan</button>'
            + '</div>';
        if (!bukaModal('Konfirmasi', html)) { return; }
        modalIsi.querySelector('[data-modal-batal]').addEventListener('click', tutupModal);
        modalIsi.querySelector('[data-modal-ya]').addEventListener('click', function () {
            tutupModal();
            if (pemicu.tagName === 'BUTTON' && pemicu.form) { pemicu.form.submit(); }
            else if (pemicu.href) { window.location.href = pemicu.href; }
        });
    });

    /* Muat halaman Detail/Histori/Tambah/Edit/Hapus ke dalam modal */
    document.addEventListener('click', function (e) {
        var pemicu = e.target.closest('[data-modal]');
        if (!pemicu || !pemicu.href || !modalLapisan) { return; }
        e.preventDefault();
        var judul = pemicu.getAttribute('data-judul') || 'Detail';
        modalUrl = pemicu.href;
        if (modalBukaHalaman) {
            modalBukaHalaman.href = modalUrl;
            modalBukaHalaman.removeAttribute('hidden');
        }
        if (!bukaModal(judul, '<p class="modal-muat">Memuat…</p>')) {
            window.location.href = pemicu.href;
            return;
        }
        fetch(pemicu.href, { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.text();
            })
            .then(function (html) {
                var dok = new DOMParser().parseFromString(html, 'text/html');
                var konten = dok.querySelector('main.konten');
                modalIsi.innerHTML = konten ? konten.innerHTML : '<p class="modal-muat">Isi tidak ditemukan.</p>';
                formatRupiahModal();
            })
            .catch(function () {
                modalIsi.innerHTML = '<p class="modal-muat">Gagal memuat. <a href="' + pemicu.href + '">Buka halaman</a></p>';
            });
    });
})();