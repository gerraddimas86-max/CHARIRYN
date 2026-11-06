<?php

declare(strict_types=1);

/**
 * Halaman: Kode Aktivasi Akun Pelanggan (DFD 7.2)
 * Fungsi: Manajer melihat kode aktivasi akun yang belum aktif dan
 * dapat membuat kode baru bila yang lama hilang atau kedaluwarsa.
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

$judul_halaman = 'Kode Aktivasi';
$sub_judul_halaman = (string) $akun['username'];
$halaman_aktif = 'akun-pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

$tokenBaru = null;
$berakhirBaru = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        setFlash('error', 'Sesi keamanan tidak valid. Silakan muat ulang halaman.');
        redirect(BASE_URL . 'akun-pelanggan/aktivasi.php?id=' . $id);
    }

    $hasil = PenggunaService::buatTokenAktivasiBaru($pdo, $id);

    if ($hasil['ok']) {
        $tokenBaru = (string) $hasil['token'];
        $berakhirBaru = (string) $hasil['berakhir'];
        setFlash('success', $hasil['pesan']);
    } else {
        setFlash('error', $hasil['pesan']);
        redirect(BASE_URL . 'akun-pelanggan/aktivasi.php?id=' . $id);
    }
}
?>

<div class="kotak-form">
    <div class="aksi-halaman">
        <a class="btn btn-netral btn-kecil" href="<?= BASE_URL ?>akun-pelanggan/index.php">Kembali</a>
    </div>
    <h2>Kode Aktivasi Akun</h2>
    <p class="teks-kecil mb-lg">
        Akun <strong><?= e((string) $akun['username']) ?></strong>
        <?php if ($akun['nama_pelanggan'] !== null): ?> untuk pelanggan <strong><?= e((string) $akun['nama_pelanggan']) ?></strong><?php endif; ?>
        berstatus <strong><?= e((string) $akun['status']) ?></strong>.
    </p>

    <?php if ((string) $akun['status'] !== 'belum aktif'): ?>
        <div class="alert alert-info" role="alert">
            <div class="alert-ikon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            </div>
            <p>Akun ini sudah aktif.</p>
        </div>
    <?php else: ?>
        <div class="kartu-pratinjau mb-lg" aria-label="Kode aktivasi">
            <h4 style="margin-bottom:var(--spasi-sm);">Kode Aktivasi</h4>
            <?php if ($tokenBaru !== null): ?>
                <p><code style="font-size:15px;word-break:break-all;"><?= e($tokenBaru) ?></code></p>
                <p class="teks-kecil">Berlaku sampai <?= e(formatTanggalWaktuIndonesia($berakhirBaru ?? '')) ?></p>
            <?php elseif ($akun['token_aktivasi'] !== null): ?>
                <p><code style="font-size:15px;word-break:break-all;"><?= e((string) $akun['token_aktivasi']) ?></code></p>
                <p class="teks-kecil">Berlaku sampai <?= e(formatTanggalWaktuIndonesia((string) $akun['token_aktivasi_berakhir'])) ?></p>
            <?php else: ?>
                <p class="teks-kecil">Belum ada kode. Buat di bawah ini.</p>
            <?php endif; ?>
        </div>

        <div class="alert alert-info" role="alert">
            <div class="alert-ikon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            </div>
            <p>
                Sampaikan kode ini kepada pelanggan. Pelanggan membukanya di halaman
                <strong>Aktivasi Akun</strong> untuk menetapkan kata sandi sendiri.
                Kode berlaku 24 jam dan hanya dapat dipakai satu kali.
            </p>
        </div>

        <form method="post" action="<?= BASE_URL ?>akun-pelanggan/aktivasi.php?id=<?= (int) $id ?>" data-anti-ganda>
            <?= fieldCsrf() ?>
            <div class="form-aksi">
                <button type="submit" class="btn btn-primer">Buat Kode Aktivasi Baru</button>
                <a class="btn btn-netral" href="<?= BASE_URL ?>akun-pelanggan/index.php">Kembali</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
