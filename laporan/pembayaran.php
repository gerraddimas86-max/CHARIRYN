<?php

declare(strict_types=1);

/**
 * Halaman: Laporan Pembayaran
 * Fungsi: Rekap pembayaran yang diterima per periode
 */

require_once __DIR__ . '/../includes/init.php';
requireLaporan();

$judul_halaman = 'Laporan Pembayaran';
$sub_judul_halaman = 'Rekap pembayaran yang diterima per periode';
$halaman_aktif = 'laporan-pembayaran';

$laporan_aktif = 'pembayaran';

require_once BASE_PATH . '/includes/kepala.php';

require_once BASE_PATH . '/includes/nav-laporan.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$dari = parseTanggalID($_GET['dari'] ?? '') ?? '';
$sampai = parseTanggalID($_GET['sampai'] ?? '') ?? '';
$metode = bersihkan($_GET['metode'] ?? '');
$idPelanggan = (int) ($_GET['id_pelanggan'] ?? 0);

$daftarPelanggan = PelangganService::ambilOpsi($pdo);
$hasil = LaporanService::laporanPembayaran($pdo, $cari, $dari, $sampai, $metode, $idPelanggan);
$daftar = $hasil['data'];
$total = $hasil['total'];

$deskripsiFilter = 'Periode: ' . ($dari !== '' ? formatTanggalIndonesia($dari) : 'Semua') . ' s/d ' . ($sampai !== '' ? formatTanggalIndonesia($sampai) : 'Semua');
if ($metode !== '') {
    $deskripsiFilter .= ' - Metode: ' . $metode;
}
if ($cari !== '') {
    $deskripsiFilter .= ' - Pencarian: "' . $cari . '"';
}
if ($idPelanggan > 0) {
    foreach ($daftarPelanggan as $p) {
        if ((int) $p['id_pelanggan'] === $idPelanggan) {
            $deskripsiFilter .= ' - Pelanggan: ' . $p['nama_pelanggan'];
            break;
        }
    }
}
?>

<section class="panel-filter" aria-label="Filter laporan">
    <form method="get" action="<?= BASE_URL ?>laporan/pembayaran.php" class="filter-grid form-filter-halaman form-filter-tanggal">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="No. transaksi atau nama pelanggan">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="dari">Tanggal Pembayaran Dari</label>
            <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="dari" name="dari" value="<?= e(tampilTanggalID($_GET['dari'] ?? '')) ?>" data-tanggal-dari>
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="sampai">Sampai</label>
            <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="sampai" name="sampai" value="<?= e(tampilTanggalID($_GET['sampai'] ?? '')) ?>" data-tanggal-sampai>
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="metode">Metode</label>
            <select class="pilih" id="metode" name="metode">
                <option value="">Semua Metode</option>
                <option value="Tunai" <?= $metode === 'Tunai' ? 'selected' : '' ?>>Tunai</option>
                <option value="Transfer" <?= $metode === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                <option value="E-Wallet" <?= $metode === 'E-Wallet' ? 'selected' : '' ?>>E-Wallet</option>
            </select>
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="id_pelanggan">Pelanggan</label>
            <select class="pilih" id="id_pelanggan" name="id_pelanggan">
                <option value="0">Semua Pelanggan</option>
                <?php foreach ($daftarPelanggan as $p): ?>
                    <option value="<?= (int) $p['id_pelanggan'] ?>" <?= $idPelanggan === (int) $p['id_pelanggan'] ? 'selected' : '' ?>><?= e($p['nama_pelanggan']) ?><?= ($p['status'] ?? 'aktif') !== 'aktif' ? ' (nonaktif)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Terapkan Filter</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>laporan/pembayaran.php">Reset</a>
        </div>
    </form>
</section>

<div class="judul-cetak">
    <h1>Laporan Pembayaran</h1>
    <p><?= e(formatTanggalIndonesia(date('Y-m-d'))) ?> • <?= e($deskripsiFilter) ?></p>
</div>

<div class="table-wrapper">
    <table class="tabel">
        <thead>
            <tr>
                <th class="col-tanggal">Tanggal</th>
                <th class="col-kode">No. Transaksi</th>
                <th class="col-nama">Pelanggan</th>
                <th class="angka col-angka">Jumlah Bayar</th>
                <th class="col-metode">Metode</th>
                <th class="col-status">Status</th>
                <th class="col-keterangan">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr class="tabel-baris-kosong"><td colspan="7"><?= $cari !== '' || $dari !== '' || $sampai !== '' || $metode !== '' || $idPelanggan > 0 ? 'Tidak ada data sesuai filter.' : 'Belum ada pembayaran.' ?></td></tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <tr>
                        <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_pembayaran'])) ?></td>
                        <td><?= e($baris['no_transaksi']) ?></td>
                        <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['jumlah_bayar'])) ?></td>
                        <td><?= e($baris['metode_pembayaran']) ?></td>
                        <td>
                            <?php if ((string) ($baris['status'] ?? 'berlaku') === 'berlaku'): ?>
                                <span class="badge badge-lunas">Berlaku</span>
                            <?php else: ?>
                                <span class="badge badge-jatuh-tempo">Dibatalkan</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($baris['keterangan'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" class="tabel-nama">Total pembayaran sah (<?= e(formatAngka($total['jumlah'])) ?> pembayaran)</td>
                    <td class="angka"><strong><?= e(formatRupiah($total['total_bayar'])) ?></strong></td>
                    <td colspan="3"></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>