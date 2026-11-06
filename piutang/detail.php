<?php

declare(strict_types=1);

/**
 * Halaman: Detail Piutang
 * Fungsi: Menampilkan informasi piutang dan riwayat pembayarannya
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$piutang = PiutangService::ambil($pdo, $id);

if ($piutang === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Detail Piutang';
$sub_judul_halaman = $piutang['no_transaksi'];
$halaman_aktif = 'piutang';

require_once BASE_PATH . '/includes/kepala.php';

$daftarPembayaran = PembayaranService::ambilPerPiutang($pdo, $id);
$totalPembayaran = hitungTotalPembayaran($pdo, $id);
?>

<div class="tengah-detail">
<div class="aksi-halaman">
    <a class="btn btn-netral" href="<?= BASE_URL ?>piutang/index.php">Kembali</a>
    <span class="spasi-bebas"></span>
    <a class="btn btn-sekunder" href="<?= BASE_URL ?>piutang/edit.php?id=<?= (int) $id ?>" data-modal data-judul="Edit <?= e($piutang['no_transaksi']) ?>">Edit Piutang</a>
    <a class="btn btn-primer" href="<?= BASE_URL ?>pembayaran/tambah.php?id_piutang=<?= (int) $id ?>" data-modal data-judul="Catat Pembayaran">Catat Pembayaran</a>
    <?php if ((float) $piutang['sisa_piutang'] > 0 && (string) $piutang['status'] !== 'Lunas'): ?>
        <a class="btn btn-primer" href="<?= BASE_URL ?>pembayaran/tambah.php?id_piutang=<?= (int) $id ?>&lunasi=1" data-konfirmasi="Yakin mencatat pelunasan penuh sebesar <?= e(formatRupiah($piutang['sisa_piutang'])) ?>?">Lunasi</a>
    <?php endif; ?>
</div>

<section class="kartu mb-lg" aria-label="Informasi piutang">
    <div class="kartu-judul">
        <div>
            <h2 style="font-size:24px;"><?= e($piutang['no_transaksi']) ?></h2>
            <p class="teks-kecil">Pelanggan: <a href="<?= BASE_URL ?>pelanggan/detail.php?id=<?= (int) $piutang['id_pelanggan'] ?>" data-modal data-judul="Detail <?= e($piutang['nama_pelanggan']) ?>"><?= e($piutang['nama_pelanggan']) ?></a> (<?= e($piutang['kode_pelanggan']) ?>)</p>
        </div>
        <?= badgeStatus((string) $piutang['status']) ?>
    </div>
    <div class="form-grid" style="margin:0;">
        <div><span class="teks-kecil">Tanggal Transaksi</span><p><?= e(formatTanggalIndonesia((string) $piutang['tanggal_transaksi'], true)) ?></p></div>
        <div><span class="teks-kecil">Jatuh Tempo</span><p><?= e(formatTanggalIndonesia((string) $piutang['jatuh_tempo'], true)) ?></p></div>
        <div><span class="teks-kecil">Total Piutang</span><p><strong><?= e(formatRupiah($piutang['total_piutang'])) ?></strong></p></div>
        <div><span class="teks-kecil">Sudah Dibayar</span><p><strong><?= e(formatRupiah($piutang['jumlah_dibayar'])) ?></strong></p></div>
        <div><span class="teks-kecil">Sisa Piutang</span><p><strong><?= e(formatRupiah($piutang['sisa_piutang'])) ?></strong></p></div>
        <div><span class="teks-kecil">Keterangan</span><p><?= e($piutang['keterangan'] ?? '-') ?></p></div>
        <div><span class="teks-kecil">Terdaftar</span><p><?= e(formatTanggalWaktuIndonesia((string) $piutang['created_at'])) ?></p></div>
        <div><span class="teks-kecil">Terakhir Diubah</span><p><?= e(formatTanggalWaktuIndonesia((string) $piutang['updated_at'])) ?></p></div>
    </div>
</section>

<section aria-label="Riwayat pembayaran piutang">
    <div class="kartu-judul">
        <h3>Riwayat Pembayaran</h3>
        <span class="teks-kecil">Total terkumpul: <strong><?= e(formatRupiah($totalPembayaran)) ?></strong></span>
    </div>
    <div class="table-wrapper">
        <table class="tabel">
            <thead>
                <tr>
                    <th class="col-tanggal">Tanggal</th>
                    <th class="angka col-angka">Jumlah Bayar</th>
                    <th class="col-metode">Metode</th>
                    <th class="col-status">Status</th>
                    <th class="col-keterangan">Keterangan</th>
                    <th class="tabel-aksi col-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($daftarPembayaran === []): ?>
                    <tr><td colspan="6" class="tabel-baris-kosong">Belum ada pembayaran untuk piutang ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($daftarPembayaran as $baris): ?>
                        <tr>
                            <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_pembayaran'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['jumlah_bayar'])) ?></td>
                            <td><?= e($baris['metode_pembayaran']) ?></td>
                            <td>
                                <?php if ((string) $baris['status'] === 'berlaku'): ?>
                                    <span class="badge badge-lunas">Berlaku</span>
                                <?php else: ?>
                                    <span class="badge badge-jatuh-tempo">Dibatalkan</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($baris['keterangan'] ?? '-') ?></td>
                            <td class="tabel-aksi">
                                <a href="<?= BASE_URL ?>pembayaran/detail.php?id=<?= (int) $baris['id_pembayaran'] ?>" data-modal data-judul="Detail Pembayaran">Detail</a>
                                <?php if ((string) $baris['status'] === 'berlaku'): ?>
                                    <a href="<?= BASE_URL ?>pembayaran/edit.php?id=<?= (int) $baris['id_pembayaran'] ?>" data-modal data-judul="Edit Pembayaran">Edit</a>
                                    <a class="hapus" href="<?= BASE_URL ?>pembayaran/batal.php?id=<?= (int) $baris['id_pembayaran'] ?>" data-modal data-judul="Batalkan Pembayaran">Batalkan</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>