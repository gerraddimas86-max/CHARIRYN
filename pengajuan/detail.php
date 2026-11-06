<?php

declare(strict_types=1);

/**
 * Halaman: Detail & Verifikasi Pengajuan Pembayaran (DFD 4.4)
 * Fungsi: Manajer memeriksa bukti, lalu menyetujui (membuat pembayaran
 * resmi) atau menolak pengajuan. Saldo piutang tidak berubah sebelum
 * pengajuan disetujui.
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'pengajuan/index.php');
}

$pdo = koneksiDatabase();
$pengajuan = PengajuanService::ambil($pdo, $id);

if ($pengajuan === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Verifikasi Pengajuan';
$sub_judul_halaman = (string) $pengajuan['no_transaksi'];
$halaman_aktif = 'pengajuan';

require_once BASE_PATH . '/includes/kepala.php';

$kesalahan = [];
$alasan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        setFlash('error', 'Sesi keamanan tidak valid. Silakan muat ulang halaman.');
        redirect(BASE_URL . 'pengajuan/detail.php?id=' . $id);
    }

    $tindakan = bersihkan($_POST['tindakan'] ?? '');
    $idUser = (int) userSaatIni()['id_user'];

    if ($tindakan === 'setujui') {
        $hasil = PengajuanService::setujui($pdo, $id, $idUser);
        setFlash($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
        redirect(BASE_URL . 'pengajuan/index.php');
    }

    if ($tindakan === 'tolak') {
        $alasan = bersihkan($_POST['alasan_penolakan'] ?? '');
        $hasil = PengajuanService::tolak($pdo, $id, $idUser, $alasan);
        setFlash($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
        redirect(BASE_URL . 'pengajuan/index.php');
    }

    $kesalahan[] = 'Tindakan tidak dikenal.';
}

$menunggu = (string) $pengajuan['status'] === 'menunggu';
?>

<div class="tengah-detail">
<div class="aksi-halaman">
    <a class="btn btn-netral" href="<?= BASE_URL ?>pengajuan/index.php">Kembali</a>
</div>

<?php if ($kesalahan !== []): ?>
    <ul class="pesan-kesalahan" role="alert">
        <?php foreach ($kesalahan as $pesan): ?>
            <li><?= e($pesan) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<section class="kartu mb-lg" aria-label="Informasi pengajuan">
    <div class="kartu-judul">
        <div>
            <h2 style="font-size:24px;"><?= e((string) $pengajuan['no_transaksi']) ?></h2>
            <p class="teks-kecil">
                Pelanggan: <?= e((string) $pengajuan['nama_pelanggan']) ?> (<?= e((string) $pengajuan['kode_pelanggan']) ?>)
                &middot; Pengaju: <?= e((string) $pengajuan['nama_pengaju']) ?>
            </p>
        </div>
        <?= badgeStatusVerifikasi((string) $pengajuan['status']) ?>
    </div>
    <div class="form-grid" style="margin:0;">
        <div><span class="teks-kecil">Tanggal Pengajuan</span><p><?= e(formatTanggalIndonesia((string) $pengajuan['tanggal_pengajuan'], true)) ?></p></div>
        <div><span class="teks-kecil">Nominal Diajukan</span><p><strong><?= e(formatRupiah($pengajuan['nominal'])) ?></strong></p></div>
        <div><span class="teks-kecil">Metode</span><p><?= e((string) $pengajuan['metode_pembayaran']) ?></p></div>
        <div><span class="teks-kecil">Sisa Tagihan Saat Ini</span><p><strong><?= e(formatRupiah($pengajuan['sisa_piutang'])) ?></strong></p></div>
        <div><span class="teks-kecil">Jatuh Tempo</span><p><?= e(formatTanggalIndonesia((string) $pengajuan['jatuh_tempo'], true)) ?></p></div>
        <div><span class="teks-kecil">Catatan Pelanggan</span><p><?= e((string) ($pengajuan['catatan'] ?? '-')) ?></p></div>
    </div>
</section>

<section class="kartu mb-lg" aria-label="Bukti pembayaran">
    <div class="kartu-judul">
        <h3>Bukti Pembayaran</h3>
    </div>
    <?php if ($pengajuan['bukti_pembayaran'] === null): ?>
        <p class="keadaan-kosong">Tidak ada berkas bukti terlampir.</p>
    <?php else: ?>
        <p class="teks-kecil mb-lg">
            Berkas bukti pengajuan #<?= (int) $pengajuan['id_pengajuan'] ?>.
            <a href="<?= BASE_URL ?>pengajuan/bukti.php?id=<?= (int) $pengajuan['id_pengajuan'] ?>" target="_blank" rel="noopener">Buka bukti pembayaran</a>
        </p>
    <?php endif; ?>
</section>

<?php if ((string) $pengajuan['status'] !== 'menunggu'): ?>
    <section class="kartu mb-lg" aria-label="Hasil keputusan">
        <div class="kartu-judul">
            <h3>Hasil Keputusan</h3>
        </div>
        <div class="form-grid" style="margin:0;">
            <div>
                <span class="teks-kecil">Status</span>
                <p><?= badgeStatusVerifikasi((string) $pengajuan['status']) ?></p>
            </div>
            <div><span class="teks-kecil">Diverifikasi Oleh</span><p><?= e((string) ($pengajuan['nama_verifikator'] ?? '-')) ?></p></div>
            <div><span class="teks-kecil">Waktu Keputusan</span><p><?= e(formatTanggalWaktuIndonesia((string) ($pengajuan['tanggal_verifikasi'] ?? ''))) ?></p></div>
            <?php if ($pengajuan['status'] === 'disetujui'): ?>
                <div><span class="teks-kecil">Pembayaran Resmi</span><p><?= e(formatRupiah($pengajuan['jumlah_pembayaran_resmi'] ?? 0)) ?> &middot; <?= e(formatTanggalIndonesia((string) ($pengajuan['tanggal_pembayaran_resmi'] ?? ''))) ?></p></div>
            <?php else: ?>
                <div><span class="teks-kecil">Alasan Penolakan</span><p><?= e((string) ($pengajuan['alasan_penolakan'] ?? '-')) ?></p></div>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <section class="kartu mb-lg" aria-label="Keputusan verifikasi">
        <div class="kartu-judul">
            <h3>Keputusan</h3>
        </div>
        <p class="teks-kecil mb-lg">
            Menyetujui pengajuan akan membuat satu pembayaran resmi sebesar
            <strong><?= e(formatRupiah($pengajuan['nominal'])) ?></strong> dan menghitung ulang sisa piutang.
            Menolak tidak mengubah saldo piutang.
        </p>
        <form method="post" action="<?= BASE_URL ?>pengajuan/detail.php?id=<?= (int) $id ?>" data-anti-ganda>
            <?= fieldCsrf() ?>
            <input type="hidden" name="tindakan" value="setujui">
            <div class="form-aksi">
                <button type="submit" class="btn btn-primer">Setujui &amp; Catat Pembayaran</button>
            </div>
        </form>

        <form method="post" action="<?= BASE_URL ?>pengajuan/detail.php?id=<?= (int) $id ?>" style="margin-top:var(--spasi-lg);" data-anti-ganda>
            <?= fieldCsrf() ?>
            <input type="hidden" name="tindakan" value="tolak">
            <div class="kolom">
                <label for="alasan_penolakan">Alasan Penolakan *</label>
                <textarea class="textarea" id="alasan_penolakan" name="alasan_penolakan" placeholder="Alasan ini tampil ke pelanggan." required><?= e($alasan) ?></textarea>
                <span class="bantuan">Wajib diisi</span>
            </div>
            <div class="form-aksi">
                <button type="submit" class="btn btn-hapus">Tolak Pengajuan</button>
            </div>
        </form>
    </section>
<?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
