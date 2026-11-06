<?php

declare(strict_types=1);

/**
 * Halaman: Hapus Pelanggan (DFD 2.3)
 * Fungsi: Menilai keamanan penghapusan. Pelanggan yang punya riwayat
 * piutang/pembayaran tidak boleh dihapus (histori keuangan tidak boleh
 * hilang) - ia harus dinonaktifkan.
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$pelanggan = PelangganService::ambil($pdo, $id);

if ($pelanggan === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Hapus Pelanggan';
$sub_judul_halaman = $pelanggan['nama_pelanggan'];
$halaman_aktif = 'pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        setFlash('error', 'Sesi keamanan tidak valid. Silakan muat ulang halaman.');
        redirect(BASE_URL . 'pelanggan/detail.php?id=' . $id);
    }

    $hasil = PelangganService::hapusPelanggan($pdo, $id);

    if (!$hasil['ok']) {
        setFlash('error', $hasil['pesan']);
        redirect(BASE_URL . 'pelanggan/detail.php?id=' . $id);
    }

    $pesan = 'Data pelanggan berhasil dihapus.';
    if ((int) $hasil['akun'] > 0) {
        $pesan .= ' Akun login pelanggan ikut dihapus.';
    }
    setFlash('success', $pesan);
    redirect(BASE_URL . 'pelanggan/index.php');
}

$ringkasanHapus = PelangganService::ringkasan($pdo, $id);
$punyaAkunHapus = PenggunaService::pelangganPunyaAkun($pdo, $id);
$bolehDihapus = ((int) $ringkasanHapus['jumlah_piutang'] === 0);
?>

<div class="kotak-form kotak-hapus">
    <div class="modal-ikon" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
    </div>
    <h2>Hapus Pelanggan</h2>
    <p style="color:var(--warna-teks-muted);">
        Apakah Anda yakin ingin menghapus <strong><?= e($pelanggan['nama_pelanggan']) ?></strong> (<?= e($pelanggan['kode_pelanggan']) ?>)?
    </p>

    <?php if (!$bolehDihapus): ?>
        <div class="alert alert-warning" role="alert">
            <div class="alert-ikon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <p>
                Pelanggan ini memiliki <strong><?= e(formatAngka((int) $ringkasanHapus['jumlah_piutang'])) ?> transaksi piutang</strong>.
                Riwayat keuangan tidak boleh hilang, jadi pelanggan tidak dapat dihapus.
                Ubah statusnya menjadi <strong>nonaktif</strong> agar pelanggan berhenti memakai layanan
                tanpa merusak riwayat transaksi.
            </p>
        </div>
        <div class="form-aksi" style="justify-content:center;">
            <a class="btn btn-netral" href="<?= BASE_URL ?>pelanggan/detail.php?id=<?= (int) $id ?>">Kembali</a>
            <a class="btn btn-primer" href="<?= BASE_URL ?>pelanggan/edit.php?id=<?= (int) $id ?>">Ubah Status Pelanggan</a>
        </div>
    <?php else: ?>
        <?php if ($punyaAkunHapus): ?>
            <p class="teks-bahaya" style="margin-bottom:var(--spasi-sm);">Akun login pelanggan ini juga akan dihapus.</p>
        <?php endif; ?>
        <p class="teks-kecil" style="margin-bottom:var(--spasi-lg);">Tanpa riwayat piutang.</p>

        <form method="post" action="<?= BASE_URL ?>pelanggan/hapus.php?id=<?= (int) $id ?>" data-anti-ganda>
            <?= fieldCsrf() ?>
            <div class="form-aksi" style="justify-content:center;">
                <a class="btn btn-netral" href="<?= BASE_URL ?>pelanggan/detail.php?id=<?= (int) $id ?>">Batal</a>
                <button type="submit" class="btn btn-hapus">Hapus Data</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
