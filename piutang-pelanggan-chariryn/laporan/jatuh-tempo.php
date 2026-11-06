<?php

declare(strict_types=1);

/**
 * Halaman: Laporan Piutang Jatuh Tempo
 * Fungsi: Rekap piutang yang belum lunas beserta posisi jatuh temponya
 */

require_once __DIR__ . '/../includes/init.php';
requireLaporan();

$judul_halaman = 'Laporan Jatuh Tempo';
$sub_judul_halaman = 'Rekap piutang berdasarkan jatuh tempo';
$halaman_aktif = 'laporan-jatuh-tempo';

$laporan_aktif = 'jatuh-tempo';

require_once BASE_PATH . '/includes/kepala.php';

require_once BASE_PATH . '/includes/nav-laporan.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$dari = parseTanggalID($_GET['dari'] ?? '') ?? '';
$sampai = parseTanggalID($_GET['sampai'] ?? '') ?? '';
$status = bersihkan($_GET['status'] ?? '');

$hasil = LaporanService::laporanJatuhTempo($pdo, $cari, $dari, $sampai, $status);
$daftar = $hasil['data'];
$total = $hasil['total'];

$deskripsiFilter = 'Periode jatuh tempo: ' . ($dari !== '' ? formatTanggalIndonesia($dari) : 'Semua') . ' s/d ' . ($sampai !== '' ? formatTanggalIndonesia($sampai) : 'Semua');
if ($status !== '') {
    $deskripsiFilter .= ' - Status: ' . $status;
}
if ($cari !== '') {
    $deskripsiFilter .= ' - Pencarian: "' . $cari . '"';
}
?>

<section class="panel-filter" aria-label="Filter laporan">
    <form method="get" action="<?= BASE_URL ?>laporan/jatuh-tempo.php" class="filter-grid form-filter-halaman form-filter-tanggal">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="No. transaksi atau nama pelanggan">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="dari">Jatuh Tempo Dari</label>
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
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Terapkan Filter</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>laporan/jatuh-tempo.php">Reset</a>
        </div>
    </form>
</section>

<div class="judul-cetak">
    <h1>Laporan Piutang Jatuh Tempo</h1>
    <p><?= e(formatTanggalIndonesia(date('Y-m-d'))) ?> • <?= e($deskripsiFilter) ?></p>
</div>

<div class="table-wrapper">
    <table class="tabel">
        <thead>
            <tr>
                <th class="col-kode">No. Transaksi</th>
                <th class="col-nama">Pelanggan</th>
                <th class="col-tanggal">Tanggal Transaksi</th>
                <th class="col-tempo">Jatuh Tempo</th>
                <th class="angka col-angka">Total Piutang</th>
                <th class="angka col-angka">Dibayar</th>
                <th class="angka col-angka">Sisa</th>
                <th class="col-status">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr class="tabel-baris-kosong"><td colspan="8"><?= $cari !== '' || $dari !== '' || $sampai !== '' || $status !== '' ? 'Tidak ada data sesuai filter.' : 'Belum ada piutang jatuh tempo.' ?></td></tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <?php
                        $selisih = selisihHariDariHariIni((string) $baris['jatuh_tempo']);
                        if ($selisih > 0) {
                            $labelTempo = ' (terlambat ' . $selisih . ' hari)';
                        } elseif ($selisih === 0) {
                            $labelTempo = ' (hari ini)';
                        } else {
                            $labelTempo = ' (' . abs($selisih) . ' hari lagi)';
                        }
                    ?>
                    <tr>
                        <td><?= e($baris['no_transaksi']) ?></td>
                        <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                        <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_transaksi'])) ?></td>
                        <td><?= e(formatTanggalIndonesia((string) $baris['jatuh_tempo'])) ?><?= e($labelTempo) ?></td>
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