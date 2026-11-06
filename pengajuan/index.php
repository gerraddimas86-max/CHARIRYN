<?php

declare(strict_types=1);

/**
 * Halaman: Verifikasi Pengajuan Pembayaran (DFD 4.4)
 * Fungsi: Daftar pengajuan pembayaran dari pelanggan yang menunggu
 * keputusan manajer.
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Pengajuan Pembayaran';
$sub_judul_halaman = 'Verifikasi pengajuan pembayaran pelanggan';
$halaman_aktif = 'pengajuan';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$status = bersihkan($_GET['status'] ?? '');
$halaman = max(1, (int) bersihkan($_GET['halaman'] ?? 1));
$perHalaman = 10;

$hasil = PengajuanService::daftar($pdo, $status, $cari, $halaman, $perHalaman);
$daftar = $hasil['data'];
$total = (int) $hasil['total'];
$totalHalaman = (int) ceil($total / $perHalaman);
?>

<div class="aksi-halaman aksi-tumpuk">
    <form method="get" action="<?= BASE_URL ?>pengajuan/index.php" class="form-filter-halaman">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="No. transaksi, nama, atau kode pelanggan">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="status">Status</label>
            <select class="pilih" id="status" name="status">
                <option value="">Semua Status</option>
                <option value="menunggu" <?= $status === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                <option value="disetujui" <?= $status === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= $status === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </div>
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Terapkan Filter</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>pengajuan/index.php">Reset</a>
        </div>
    </form>
</div>

<p class="jumlah-hasil">Menampilkan <strong><?= e(formatAngka(count($daftar))) ?></strong> dari <strong><?= e(formatAngka($total)) ?></strong> pengajuan.</p>

<div class="table-wrapper">
    <table class="tabel">
        <thead>
            <tr>
                <th class="col-tanggal">Tanggal</th>
                <th class="col-kode">No. Transaksi</th>
                <th class="col-nama">Pelanggan</th>
                <th class="angka col-angka">Nominal</th>
                <th class="col-metode">Metode</th>
                <th class="col-status">Status</th>
                <th class="tabel-aksi col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr>
                    <td colspan="7">
                        <div class="keadaan-kosong">
                            <img class="doodle-empty" src="<?= BASE_URL ?>assets/img/doodles/shapes/doodle-cloud-smile.png" alt="" aria-hidden="true" width="64" height="64">
                            <h3>Belum Ada Pengajuan</h3>
                            <p><?= $cari !== '' || $status !== '' ? 'Tidak ada pengajuan sesuai filter.' : 'Belum ada pengajuan.' ?></p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <tr>
                        <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_pengajuan'])) ?></td>
                        <td><?= e((string) $baris['no_transaksi']) ?></td>
                        <td class="tabel-nama"><?= e((string) $baris['nama_pelanggan']) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['nominal'])) ?></td>
                        <td><?= e((string) $baris['metode_pembayaran']) ?></td>
                        <td><?= badgeStatusVerifikasi((string) $baris['status']) ?></td>
                        <td class="tabel-aksi">
                            <a href="<?= BASE_URL ?>pengajuan/detail.php?id=<?= (int) $baris['id_pengajuan'] ?>" data-modal data-judul="Verifikasi Pengajuan">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginasiHtml($halaman, $totalHalaman, ['cari' => $cari, 'status' => $status]) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
