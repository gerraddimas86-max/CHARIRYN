<?php

declare(strict_types=1);

/**
 * Halaman: Data Piutang
 * Fungsi: Menampilkan daftar piutang dengan pencarian, filter, dan paginasi
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Data Piutang';
$sub_judul_halaman = 'Daftar seluruh piutang pelanggan';
$halaman_aktif = 'piutang';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$status = bersihkan($_GET['status'] ?? '');
$halaman = max(1, (int) bersihkan($_GET['halaman'] ?? 1));
$perHalaman = 10;

$hasil = PiutangService::ambilSemua($pdo, $cari, $status, $halaman, $perHalaman);
$daftar = $hasil['data'];
$total = (int) $hasil['total'];
$totalHalaman = (int) ceil($total / $perHalaman);
?>

<div class="aksi-halaman aksi-tumpuk">
    <form method="get" action="<?= BASE_URL ?>piutang/index.php" class="form-filter-halaman">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari Piutang</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Nomor transaksi atau nama pelanggan">
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
            <a class="btn btn-netral" href="<?= BASE_URL ?>piutang/index.php">Reset</a>
        </div>
    </form>
    <a class="btn btn-primer" href="<?= BASE_URL ?>piutang/tambah.php" data-modal data-judul="Catat Piutang">Tambah Piutang</a>
</div>

<p class="jumlah-hasil">Menampilkan <strong><?= e(formatAngka(count($daftar))) ?></strong> dari <strong><?= e(formatAngka($total)) ?></strong> piutang.</p>

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
                <th class="tabel-aksi col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr>
                    <td colspan="9">
                        <div class="keadaan-kosong">
                            <img class="doodle-empty" src="<?= BASE_URL ?>assets/img/doodles/nature/doodle-rainbow-clouds.png" alt="" aria-hidden="true" width="64" height="64">
                            <h3>Belum Ada Data</h3>
                            <p><?= $cari !== '' || $status !== '' ? 'Tidak ada piutang yang sesuai dengan filter.' : 'Belum ada piutang yang tercatat.' ?></p>
                            <a class="btn btn-primer" href="<?= BASE_URL ?>piutang/tambah.php" data-modal data-judul="Catat Piutang">Tambah Piutang</a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>"><?= e($baris['no_transaksi']) ?></a></td>
                        <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                        <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_transaksi'])) ?></td>
                        <td><?= e(formatTanggalIndonesia((string) $baris['jatuh_tempo'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['jumlah_dibayar'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                        <td><?= badgeStatus((string) $baris['status']) ?></td>
                        <td class="tabel-aksi">
                            <a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>">Detail</a>
                            <a href="<?= BASE_URL ?>piutang/edit.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Edit <?= e($baris['no_transaksi']) ?>">Edit</a>
                            <a class="hapus" href="<?= BASE_URL ?>piutang/hapus.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Hapus <?= e($baris['no_transaksi']) ?>">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginasiHtml($halaman, $totalHalaman, ['cari' => $cari, 'status' => $status]) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>