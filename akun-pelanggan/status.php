<?php

declare(strict_types=1);

/**
 * Halaman: Ubah Status Akun Pelanggan (DFD 7.3)
 * Fungsi: Mengaktifkan atau menonaktifkan akun login pelanggan.
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$akun = PenggunaService::ambilAkunPelangganById($pdo, $id);

if ($akun === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Ubah Status Akun';
$sub_judul_halaman = (string) $akun['username'];
$halaman_aktif = 'akun-pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

$statusBaru = $akun['status'] === 'aktif' ? 'nonaktif' : 'aktif';
$namaTindakan = $statusBaru === 'aktif' ? 'Aktifkan Akun' : 'Nonaktifkan Akun';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        setFlash('error', 'Sesi keamanan tidak valid. Silakan muat ulang halaman.');
        redirect(BASE_URL . 'akun-pelanggan/index.php');
    }

    $hasil = PenggunaService::ubahStatusAkun($pdo, $id, $statusBaru);
    setFlash($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    redirect(BASE_URL . 'akun-pelanggan/index.php');
}
?>

<div class="kotak-form kotak-hapus">
    <div class="modal-ikon" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
    </div>
    <h2><?= e($namaTindakan) ?></h2>
    <p>
        Akun <strong><?= e($akun['username']) ?></strong>
        <?php if ($akun['nama_pelanggan'] !== null): ?>milik <strong><?= e($akun['nama_pelanggan']) ?></strong><?php endif; ?>
        akan diubah menjadi <strong><?= e($statusBaru) ?></strong>.
    </p>
    <p class="teks-kecil mb-lg">
        <?php if ($statusBaru === 'nonaktif'): ?>
            Pelanggan tidak akan bisa login. Sesi yang sedang berjalan juga berakhir pada permintaan berikutnya.
        <?php else: ?>
            Pelanggan dapat kembali login dan melihat piutang miliknya.
        <?php endif; ?>
        Data pelanggan, piutang, dan pembayaran tidak diubah.
    </p>

    <?php if ((string) $akun['status'] === 'belum aktif'): ?>
        <div class="alert alert-warning" role="alert">
            <div class="alert-ikon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <p>
                Akun ini belum pernah diaktivasi, jadi belum punya kata sandi yang diketahui pelanggan.
                Buat <strong>kode aktivasi</strong> dan suruh pelanggan mengaktifkan akunnya sendiri.
            </p>
        </div>
        <div class="form-aksi" style="justify-content:center;">
            <a class="btn btn-netral" href="<?= BASE_URL ?>akun-pelanggan/index.php">Kembali</a>
            <a class="btn btn-primer" href="<?= BASE_URL ?>akun-pelanggan/aktivasi.php?id=<?= (int) $id ?>">Kode Aktivasi</a>
        </div>
    <?php else: ?>
    <form method="post" action="<?= BASE_URL ?>akun-pelanggan/status.php?id=<?= (int) $id ?>" data-anti-ganda>
        <?= fieldCsrf() ?>
        <div class="form-aksi" style="justify-content:center;">
            <a class="btn btn-netral" href="<?= BASE_URL ?>akun-pelanggan/index.php">Batal</a>
            <button type="submit" class="btn <?= $statusBaru === 'nonaktif' ? 'btn-hapus' : 'btn-primer' ?>"><?= e($namaTindakan) ?></button>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
