<?php

declare(strict_types=1);

/**
 * Halaman: Detail Tagihan Pelanggan (DFD 8.2-8.4)
 * Fungsi: Detail satu piutang milik pelanggan yang sedang login.
 */

require_once __DIR__ . '/../includes/init.php';
requirePelanggan();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'portal/index.php');
}

$pdo = koneksiDatabase();
$idPelanggan = idPelangganUser();
$piutang = PortalService::piutangMilik($pdo, $id, $idPelanggan);

// Piutang milik pelanggan lain diperlakukan sama seperti tidak ditemukan.
if ($piutang === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Detail Tagihan';
$sub_judul_halaman = (string) $piutang['no_transaksi'];
$halaman_aktif = 'portal';

require_once BASE_PATH . '/includes/kepala.php';

$riwayat = PembayaranService::ambilPerPiutang($pdo, $id);
$selisih = selisihHariDariHariIni((string) $piutang['jatuh_tempo']);
$sisa = (float) $piutang['sisa_piutang'];
?>

<div class="tengah-detail">
<div class="aksi-halaman">
    <a class="btn btn-netral" href="<?= BASE_URL ?>portal/index.php">Kembali</a>
</div>

<?php if ($sisa > 0.005 && $selisih > 0): ?>
    <div class="alert alert-warning" role="alert">
        <div class="alert-ikon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <p>Tagihan ini sudah melewati jatuh tempo selama <strong><?= e(formatAngka($selisih)) ?> hari</strong>.</p>
    </div>
<?php elseif ($sisa > 0.005 && $selisih >= -7): ?>
    <div class="alert alert-info" role="alert">
        <div class="alert-ikon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        </div>
        <p>Jatuh tempo <?= e($selisih === 0 ? 'hari ini' : 'dalam ' . formatAngka(abs($selisih)) . ' hari') ?>. Sisa tagihan <?= e(formatRupiah($sisa)) ?>.</p>
    </div>
<?php endif; ?>

<section class="kartu mb-lg" aria-label="Informasi tagihan">
    <div class="kartu-judul">
        <div>
            <h2 style="font-size:24px;"><?= e((string) $piutang['no_transaksi']) ?></h2>
            <p class="teks-kecil">Pelanggan: <?= e((string) $piutang['nama_pelanggan']) ?> (<?= e((string) $piutang['kode_pelanggan']) ?>)</p>
        </div>
        <?= badgeStatus((string) $piutang['status']) ?>
    </div>
    <div class="form-grid" style="margin:0;">
        <div><span class="teks-kecil">Tanggal Transaksi</span><p><?= e(formatTanggalIndonesia((string) $piutang['tanggal_transaksi'], true)) ?></p></div>
        <div><span class="teks-kecil">Jatuh Tempo</span><p><?= e(formatTanggalIndonesia((string) $piutang['jatuh_tempo'], true)) ?></p></div>
        <div><span class="teks-kecil">Total Piutang</span><p><strong><?= e(formatRupiah($piutang['total_piutang'])) ?></strong></p></div>
        <div><span class="teks-kecil">Sudah Dibayar</span><p><strong><?= e(formatRupiah($piutang['jumlah_dibayar'])) ?></strong></p></div>
        <div><span class="teks-kecil">Sisa Piutang</span><p><strong><?= e(formatRupiah($piutang['sisa_piutang'])) ?></strong></p></div>
        <div><span class="teks-kecil">Keterangan</span><p><?= e((string) ($piutang['keterangan'] ?? '-')) ?></p></div>
    </div>
</section>

<section aria-label="Riwayat pembayaran tagihan">
    <div class="kartu-judul">
        <h3>Riwayat Pembayaran</h3>
    </div>
    <div class="table-wrapper">
        <table class="tabel">
            <thead>
                <tr>
                    <th class="col-tanggal">Tanggal</th>
                    <th class="angka col-angka">Jumlah Bayar</th>
                    <th class="col-metode">Metode</th>
                    <th class="col-keterangan">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($riwayat === []): ?>
                    <tr><td colspan="4" class="tabel-baris-kosong">Belum ada pembayaran.</td></tr>
                <?php else: ?>
                    <?php foreach ($riwayat as $baris): ?>
                        <tr>
                            <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_pembayaran'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['jumlah_bayar'])) ?></td>
                            <td><?= e((string) $baris['metode_pembayaran']) ?></td>
                            <td><?= e((string) ($baris['keterangan'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
