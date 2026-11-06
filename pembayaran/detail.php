<?php

declare(strict_types=1);

/**
 * Halaman: Detail Pembayaran
 * Fungsi: Menampilkan informasi lengkap satu pembayaran
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$pembayaran = PembayaranService::ambil($pdo, $id);

if ($pembayaran === null) {
    redirect(BASE_URL . 'error/404.php');
}

$riwayat = PembayaranService::ambilPerPiutang($pdo, (int) $pembayaran['id_piutang']);
$totalTerkumpul = 0.0;
foreach ($riwayat as $barisRiwayat) {
    $totalTerkumpul += (float) $barisRiwayat['jumlah_bayar'];
}
$totalTerkumpul = round($totalTerkumpul, 2);

$judul_halaman = 'Detail Pembayaran';
$sub_judul_halaman = 'Pembayaran #' . $id;
$halaman_aktif = 'pembayaran';

require_once BASE_PATH . '/includes/kepala.php';
?>

<div class="tengah-detail">
<div class="aksi-halaman">
    <a class="btn btn-netral" href="<?= BASE_URL ?>pembayaran/index.php">Kembali</a>
    <span class="spasi-bebas"></span>
    <a class="btn btn-sekunder" href="<?= BASE_URL ?>pembayaran/edit.php?id=<?= (int) $id ?>" data-modal data-judul="Edit Pembayaran">Edit Pembayaran</a>
    <?php if ((string) ($pembayaran['status'] ?? 'berlaku') === 'berlaku'): ?>
        <a class="btn btn-hapus" href="<?= BASE_URL ?>pembayaran/batal.php?id=<?= (int) $id ?>" data-modal data-judul="Batalkan Pembayaran">Batalkan Pembayaran</a>
    <?php endif; ?>
</div>

<section class="kartu kartu-sempit mb-lg" aria-label="Detail pembayaran">
    <div class="kartu-judul">
        <div>
            <h2 style="font-size:24px;">Pembayaran <?= e(formatRupiah($pembayaran['jumlah_bayar'])) ?></h2>
            <p class="teks-kecil">Pada <?= e(formatTanggalIndonesia((string) $pembayaran['tanggal_pembayaran'], true)) ?></p>
        </div>
        <span class="badge badge-sebagian"><?= e($pembayaran['metode_pembayaran']) ?></span>
    </div>
    <?php if ((string) ($pembayaran['status'] ?? 'berlaku') !== 'berlaku'): ?>
        <div class="alert alert-warning" role="alert">
            <div class="alert-ikon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <p>
                Pembayaran ini <strong>dibatalkan</strong> pada
                <?= e(formatTanggalWaktuIndonesia((string) ($pembayaran['dibatalkan_at'] ?? ''))) ?><br>
                Alasan: <?= e((string) ($pembayaran['alasan_pembatalan'] ?? '-')) ?><br>
                Nilai ini tidak lagi dihitung sebagai pembayaran sah.
            </p>
        </div>
    <?php endif; ?>
    <div class="form-grid" style="margin:0;">
        <div><span class="teks-kecil">Pelanggan</span><p><a href="<?= BASE_URL ?>pelanggan/detail.php?id=<?= (int) $pembayaran['id_pelanggan'] ?>" data-modal data-judul="Detail <?= e($pembayaran['nama_pelanggan']) ?>"><?= e($pembayaran['nama_pelanggan']) ?></a> (<?= e($pembayaran['kode_pelanggan']) ?>)</p></div>
        <div><span class="teks-kecil">Nomor Transaksi</span><p><a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $pembayaran['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($pembayaran['no_transaksi']) ?>"><?= e($pembayaran['no_transaksi']) ?></a></p></div>
        <div><span class="teks-kecil">Total Piutang</span><p><?= e(formatRupiah($pembayaran['total_piutang'])) ?></p></div>
        <div><span class="teks-kecil">Jumlah Bayar Ini</span><p><strong><?= e(formatRupiah($pembayaran['jumlah_bayar'])) ?></strong></p></div>
        <div><span class="teks-kecil">Total Dibayar</span><p><?= e(formatRupiah($pembayaran['jumlah_dibayar'])) ?></p></div>
        <div><span class="teks-kecil">Sisa Piutang</span><p><strong><?= e(formatRupiah($pembayaran['sisa_piutang'])) ?></strong></p></div>
        <div><span class="teks-kecil">Status Piutang</span><p><?= badgeStatus((string) $pembayaran['status']) ?></p></div>
        <div><span class="teks-kecil">Jatuh Tempo</span><p><?= e(formatTanggalIndonesia((string) $pembayaran['jatuh_tempo'])) ?></p></div>
        <div><span class="teks-kecil">Terdaftar</span><p><?= e(formatTanggalWaktuIndonesia((string) $pembayaran['created_at'])) ?></p></div>
        <div><span class="teks-kecil">Terakhir Diubah</span><p><?= e(formatTanggalWaktuIndonesia((string) $pembayaran['updated_at'])) ?></p></div>
    </div>
</section>

<section class="kartu kartu-sempit" aria-label="Buku pembayaran piutang">
    <div class="kartu-judul">
        <div>
            <h3>Buku Pembayaran</h3>
            <p class="teks-kecil">Transaksi <a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $pembayaran['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($pembayaran['no_transaksi']) ?>"><?= e($pembayaran['no_transaksi']) ?></a></p>
        </div>
        <?= badgeStatus((string) $pembayaran['status']) ?>
    </div>
    <div class="table-wrapper">
        <table class="tabel">
            <thead>
                <tr>
                    <th class="col-tanggal">Tanggal</th>
                    <th class="angka col-angka">Jumlah Bayar</th>
                    <th class="col-metode">Metode</th>
                    <th class="angka col-angka">Sisa Berjalan</th>
                </tr>
            </thead>
            <tbody>
                <?php $jalan = round((float) $pembayaran['total_piutang'], 2); ?>
                <?php foreach ($riwayat as $barisRiwayat): ?>
                    <?php $jalan = round($jalan - (float) $barisRiwayat['jumlah_bayar'], 2); ?>
                    <tr <?= (int) $barisRiwayat['id_pembayaran'] === $id ? 'aria-current="true"' : '' ?>>
                        <td><?= e(formatTanggalIndonesia((string) $barisRiwayat['tanggal_pembayaran'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($barisRiwayat['jumlah_bayar'])) ?></td>
                        <td><?= e($barisRiwayat['metode_pembayaran']) ?></td>
                        <td class="angka"><?= e(formatRupiah(max(0.0, $jalan))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="col-tanggal">Total Terkumpul</th>
                    <th class="angka col-angka"><?= e(formatRupiah($totalTerkumpul)) ?></th>
                    <th class="angka col-metode">Sisa</th>
                    <th class="angka col-angka"><?= e(formatRupiah($pembayaran['sisa_piutang'])) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>