<?php

declare(strict_types=1);

/**
 * Halaman: Laporan Pelanggan
 * Fungsi: Rekap data pelanggan beserta ringkasan piutangnya
 */

require_once __DIR__ . '/../includes/init.php';
requireLaporan();

$judul_halaman = 'Laporan Pelanggan';
$sub_judul_halaman = 'Rekap pelanggan dan ringkasan piutang';
$halaman_aktif = 'laporan-pelanggan';

$laporan_aktif = 'pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

require_once BASE_PATH . '/includes/nav-laporan.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$status = bersihkan($_GET['status'] ?? '');

$hasil = LaporanService::laporanPelanggan($pdo, $cari, $status);
$daftar = $hasil['data'];
$total = $hasil['total'];

$deskripsiFilter = 'Semua pelanggan';
if ($cari !== '') {
    $deskripsiFilter = 'Pencarian: "' . $cari . '"';
}
if ($status !== '') {
    $deskripsiFilter .= $cari !== '' ? ' - ' : '';
    $deskripsiFilter .= 'Status: ' . $status;
}
?>

<section class="panel-filter" aria-label="Filter laporan">
    <form method="get" action="<?= BASE_URL ?>laporan/pelanggan.php" class="filter-grid form-filter-halaman">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Kode, nama, atau nomor HP">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="status">Status</label>
            <select class="pilih" id="status" name="status">
                <option value="">Semua Status</option>
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Terapkan Filter</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>laporan/pelanggan.php">Reset</a>
        </div>
    </form>
</section>

<div class="judul-cetak">
    <h1>Laporan Pelanggan</h1>
    <p><?= e(formatTanggalIndonesia(date('Y-m-d'))) ?> • <?= e($deskripsiFilter) ?></p>
</div>

<div class="table-wrapper">
    <table class="tabel">
        <thead>
            <tr>
                <th class="col-kode">Kode</th>
                <th class="col-nama">Nama Pelanggan</th>
                <th class="col-kontak">No. HP</th>
                <th class="col-kontak">Email</th>
                <th class="col-status">Status</th>
                <th class="angka col-angka">Jumlah Piutang</th>
                <th class="angka col-angka">Total Piutang</th>
                <th class="angka col-angka">Dibayar</th>
                <th class="angka col-angka">Sisa</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr class="tabel-baris-kosong"><td colspan="9"><?= $cari !== '' || $status !== '' ? 'Tidak ada data sesuai filter.' : 'Belum ada data pelanggan.' ?></td></tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <tr>
                        <td><?= e($baris['kode_pelanggan']) ?></td>
                        <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                        <td><?= e($baris['no_hp'] ?? '-') ?></td>
                        <td><?= e($baris['email'] ?? '-') ?></td>
                        <td><?= $baris['status'] === 'aktif' ? '<span class="badge badge-sebagian">Aktif</span>' : '<span class="badge badge-netral">Nonaktif</span>' ?></td>
                        <td class="angka"><?= e(formatAngka($baris['jumlah_piutang'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['total_dibayar'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['total_sisa'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="5" class="tabel-nama">Total</td>
                    <td class="angka"><strong><?= e(formatAngka($total['jumlah_pelanggan'])) ?></strong></td>
                    <td class="angka"><strong><?= e(formatRupiah($total['total_piutang'])) ?></strong></td>
                    <td class="angka"><strong><?= e(formatRupiah($total['total_dibayar'])) ?></strong></td>
                    <td class="angka"><strong><?= e(formatRupiah($total['total_sisa'])) ?></strong></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>