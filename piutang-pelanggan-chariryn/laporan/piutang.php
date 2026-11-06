<?php

declare(strict_types=1);

/**
 * Halaman: Laporan Piutang
 * Fungsi: Rekap piutang per periode dengan filter status dan pelanggan
 */

require_once __DIR__ . '/../includes/init.php';
requireLaporan();

$judul_halaman = 'Laporan Piutang';
$sub_judul_halaman = 'Rekap piutang per periode';
$halaman_aktif = 'laporan-piutang';

$laporan_aktif = 'piutang';

require_once BASE_PATH . '/includes/kepala.php';

require_once BASE_PATH . '/includes/nav-laporan.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$dari = parseTanggalID($_GET['dari'] ?? '') ?? '';
$sampai = parseTanggalID($_GET['sampai'] ?? '') ?? '';
$status = bersihkan($_GET['status'] ?? '');
$idPelanggan = (int) ($_GET['id_pelanggan'] ?? 0);

$daftarPelanggan = PelangganService::ambilOpsi($pdo);
$hasil = LaporanService::laporanPiutang($pdo, $cari, $dari, $sampai, $status, $idPelanggan);
$daftar = $hasil['data'];
$total = $hasil['total'];

$deskripsiFilter = 'Periode: ' . ($dari !== '' ? formatTanggalIndonesia($dari) : 'Semua') . ' s/d ' . ($sampai !== '' ? formatTanggalIndonesia($sampai) : 'Semua');
if ($status !== '') {
    $deskripsiFilter .= ' - Status: ' . $status;
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
    <form method="get" action="<?= BASE_URL ?>laporan/piutang.php" class="filter-grid form-filter-halaman form-filter-tanggal">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="No. transaksi atau nama pelanggan">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="dari">Tanggal Transaksi Dari</label>
            <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="dari" name="dari" value="<?= e(tampilTanggalID($_GET['dari'] ?? '')) ?>" data-tanggal-dari>
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="sampai">Sampai</label>
            <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="sampai" name="sampai" value="<?= e(tampilTanggalID($_GET['sampai'] ?? '')) ?>" data-tanggal-sampai>
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="status">Status</label>
            <select class="pilih" id="status" name="status">
                <option value="">Semua Status</option>
                <option value="Belum Lunas" <?= $status === 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                <option value="Sebagian" <?= $status === 'Sebagian' ? 'selected' : '' ?>>Sebagian</option>
                <option value="Jatuh Tempo" <?= $status === 'Jatuh Tempo' ? 'selected' : '' ?>>Jatuh Tempo</option>
                <option value="Lunas" <?= $status === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
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
            <a class="btn btn-netral" href="<?= BASE_URL ?>laporan/piutang.php">Reset</a>
        </div>
    </form>
</section>

<div class="judul-cetak">
    <h1>Laporan Piutang</h1>
    <p><?= e(formatTanggalIndonesia(date('Y-m-d'))) ?> • <?= e($deskripsiFilter) ?></p>
</div>

<div class="table-wrapper">
    <table class="tabel">
        <thead>
            <tr>
                <th class="col-kode">No. Transaksi</th>
                <th class="col-nama">Pelanggan</th>
                <th class="col-tanggal">Tanggal</th>
                <th class="col-tempo">Jatuh Tempo</th>
                <th class="angka col-angka">Total Piutang</th>
                <th class="angka col-angka">Dibayar</th>
                <th class="angka col-angka">Sisa</th>
                <th class="col-status">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr class="tabel-baris-kosong"><td colspan="8"><?= $cari !== '' || $dari !== '' || $sampai !== '' || $status !== '' || $idPelanggan > 0 ? 'Tidak ada data sesuai filter.' : 'Belum ada data piutang.' ?></td></tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <tr>
                        <td><?= e($baris['no_transaksi']) ?></td>
                        <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                        <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_transaksi'])) ?></td>
                        <td><?= e(formatTanggalIndonesia((string) $baris['jatuh_tempo'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['jumlah_dibayar'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                        <td><?= badgeStatus((string) $baris['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4" class="tabel-nama">Total (<?= e(formatAngka($total['jumlah'])) ?> piutang)</td>
                    <td class="angka"><strong><?= e(formatRupiah($total['total_piutang'])) ?></strong></td>
                    <td class="angka"><strong><?= e(formatRupiah($total['total_dibayar'])) ?></strong></td>
                    <td class="angka"><strong><?= e(formatRupiah($total['total_sisa'])) ?></strong></td>
                    <td></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>