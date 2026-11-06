<?php

declare(strict_types=1);

/**
 * Halaman: Data Pelanggan
 * Fungsi: Menampilkan daftar pelanggan dengan pencarian, filter, dan paginasi
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Data Pelanggan';
$sub_judul_halaman = 'Kelola data pelanggan yang tercatat dalam sistem';
$halaman_aktif = 'pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$status = bersihkan($_GET['status'] ?? '');
$halaman = max(1, (int) bersihkan($_GET['halaman'] ?? 1));
$perHalaman = 10;

$hasil = PelangganService::ambilSemua($pdo, $cari, $status, $halaman, $perHalaman);
$daftar = $hasil['data'];
$total = (int) $hasil['total'];
$totalHalaman = (int) ceil($total / $perHalaman);
?>

<div class="aksi-halaman aksi-tumpuk">
    <form method="get" action="<?= BASE_URL ?>pelanggan/index.php" class="form-filter-halaman">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari Pelanggan</label>
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
            <a class="btn btn-netral" href="<?= BASE_URL ?>pelanggan/index.php">Reset</a>
        </div>
    </form>
    <a class="btn btn-primer" href="<?= BASE_URL ?>pelanggan/tambah.php" data-modal data-judul="Tambah Pelanggan">Tambah Pelanggan</a>
</div>

<p class="jumlah-hasil">Menampilkan <strong><?= e(formatAngka(count($daftar))) ?></strong> dari <strong><?= e(formatAngka($total)) ?></strong> pelanggan.</p>

<div class="table-wrapper">
    <table class="tabel">
        <thead>
            <tr>
                <th class="col-kode">Kode</th>
                <th class="col-nama">Nama Pelanggan</th>
                <th class="col-kontak">No. HP</th>
                <th class="col-kontak">Email</th>
                <th class="col-status">Status</th>
                <th class="angka col-angka">Total Sisa Piutang</th>
                <th class="tabel-aksi col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr>
                    <td colspan="7">
                        <div class="keadaan-kosong">
                            <img class="doodle-empty" src="<?= BASE_URL ?>assets/img/doodles/decor/doodle-bunny-blue.png" alt="" aria-hidden="true" width="64" height="64">
                            <h3>Belum Ada Data</h3>
                            <p><?= $cari !== '' || $status !== '' ? 'Tidak ada pelanggan yang sesuai dengan filter.' : 'Belum ada pelanggan yang terdaftar.' ?></p>
                            <a class="btn btn-primer" href="<?= BASE_URL ?>pelanggan/tambah.php" data-modal data-judul="Tambah Pelanggan">Tambah Pelanggan</a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <tr>
                        <td><?= e($baris['kode_pelanggan']) ?></td>
                        <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                        <td><?= e($baris['no_hp'] ?? '-') ?></td>
                        <td><?= e($baris['email'] ?? '-') ?></td>
                        <td>
                            <?php if ($baris['status'] === 'aktif'): ?>
                                <span class="badge badge-sebagian">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-netral">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="angka"><?= e(formatRupiah($baris['total_sisa'])) ?></td>
                        <td class="tabel-aksi">
                            <a href="<?= BASE_URL ?>pelanggan/detail.php?id=<?= (int) $baris['id_pelanggan'] ?>" data-modal data-judul="Detail <?= e($baris['nama_pelanggan']) ?>">Detail</a>
                            <a href="<?= BASE_URL ?>pelanggan/edit.php?id=<?= (int) $baris['id_pelanggan'] ?>" data-modal data-judul="Edit <?= e($baris['nama_pelanggan']) ?>">Edit</a>
                            <a class="hapus" href="<?= BASE_URL ?>pelanggan/hapus.php?id=<?= (int) $baris['id_pelanggan'] ?>" data-modal data-judul="Hapus <?= e($baris['nama_pelanggan']) ?>">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginasiHtml($halaman, $totalHalaman, ['cari' => $cari, 'status' => $status]) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>