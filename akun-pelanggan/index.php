<?php

declare(strict_types=1);

/**
 * Halaman: Akun Pelanggan (DFD 7.0 / 7.1)
 * Fungsi: Daftar akun pelanggan, pencarian, dan status akun.
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Akun Pelanggan';
$sub_judul_halaman = 'Kelola akun login pelanggan';
$halaman_aktif = 'akun-pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();
$cari = bersihkan($_GET['cari'] ?? '');
$status = bersihkan($_GET['status'] ?? '');

$daftar = PenggunaService::ambilAkunPelanggan($pdo, $cari, $status);
$tanpaAkun = PenggunaService::pelangganTanpaAkun($pdo);
?>

<div class="aksi-halaman aksi-tumpuk">
    <form method="get" action="<?= BASE_URL ?>akun-pelanggan/index.php" class="form-filter-halaman">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari Akun</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Username, nama, atau kode pelanggan">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="status">Status Akun</label>
            <select class="pilih" id="status" name="status">
                <option value="">Semua Status</option>
                <option value="belum aktif" <?= $status === 'belum aktif' ? 'selected' : '' ?>>Belum Aktif</option>
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Terapkan Filter</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>akun-pelanggan/index.php">Reset</a>
        </div>
    </form>
    <?php if ($tanpaAkun !== []): ?>
        <a class="btn btn-primer" href="<?= BASE_URL ?>akun-pelanggan/tambah.php" data-modal data-judul="Buat Akun Pelanggan">Buat Akun</a>
    <?php endif; ?>
</div>

<p class="jumlah-hasil">Menampilkan <strong><?= e(formatAngka(count($daftar))) ?></strong> akun pelanggan.
    <?php if ($tanpaAkun !== []): ?>
        &middot; <strong><?= e(formatAngka(count($tanpaAkun))) ?></strong> pelanggan aktif belum memiliki akun.
    <?php endif; ?>
</p>

<div class="table-wrapper">
    <table class="tabel">
        <thead>
            <tr>
                <th class="col-kode">Username</th>
                <th class="col-nama">Pelanggan</th>
                <th class="col-kode">Kode</th>
                <th class="col-status">Status Akun</th>
                <th class="col-status">Status Pelanggan</th>
                <th class="col-keterangan">Kode Aktivasi</th>
                <th class="col-tanggal">Terdaftar</th>
                <th class="tabel-aksi col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr>
                    <td colspan="8">
                        <div class="keadaan-kosong">
                            <h3>Belum Ada Akun Pelanggan</h3>
                            <p><?= $cari !== '' || $status !== '' ? 'Tidak ada akun yang sesuai dengan filter.' : 'Buat akun agar pelanggan dapat melihat piutang miliknya.' ?></p>
                            <?php if ($tanpaAkun !== []): ?>
                                <a class="btn btn-primer" href="<?= BASE_URL ?>akun-pelanggan/tambah.php" data-modal data-judul="Buat Akun Pelanggan">Buat Akun</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <?php $kelasStatus = match ((string) $baris['status']) {
                        'aktif' => 'badge-lunas',
                        'belum aktif' => 'badge-belum-lunas',
                        default => 'badge-jatuh-tempo',
                    }; ?>
                    <tr>
                        <td><strong><?= e($baris['username']) ?></strong></td>
                        <td class="tabel-nama">
                            <?php if ($baris['id_pelanggan'] !== null): ?>
                                <a href="<?= BASE_URL ?>pelanggan/detail.php?id=<?= (int) $baris['id_pelanggan'] ?>" data-modal data-judul="Detail <?= e((string) $baris['nama_pelanggan']) ?>"><?= e((string) $baris['nama_pelanggan']) ?></a>
                            <?php else: ?>
                                <?= e($baris['nama']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($baris['kode_pelanggan'] ?? '-')) ?></td>
                        <td>
                            <span class="badge <?= $kelasStatus ?>"><?= e(ucfirst((string) $baris['status'])) ?></span>
                        </td>
                        <td><?= e(ucfirst((string) ($baris['status_pelanggan'] ?? '-'))) ?></td>
                        <td>
                            <?php if ((string) $baris['status'] === 'belum aktif' && $baris['token_aktivasi'] !== null): ?>
                                <code style="font-size:12px;word-break:break-all;"><?= e((string) $baris['token_aktivasi']) ?></code>
                                <span class="teks-kecil">berlaku sampai <?= e(formatTanggalWaktuIndonesia((string) $baris['token_aktivasi_berakhir'])) ?></span>
                            <?php else: ?>
                                <span class="teks-kecil">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(formatTanggalWaktuIndonesia((string) $baris['created_at'])) ?></td>
                        <td class="tabel-aksi">
                            <?php if ((string) $baris['status'] === 'belum aktif'): ?>
                                <a href="<?= BASE_URL ?>akun-pelanggan/aktivasi.php?id=<?= (int) $baris['id_user'] ?>" data-modal data-judul="Kode Aktivasi <?= e($baris['username']) ?>">Kode Aktivasi</a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>akun-pelanggan/reset.php?id=<?= (int) $baris['id_user'] ?>" data-modal data-judul="Reset Kata Sandi <?= e($baris['username']) ?>">Reset Sandi</a>
                                <a class="<?= $baris['status'] === 'aktif' ? 'hapus' : '' ?>" href="<?= BASE_URL ?>akun-pelanggan/status.php?id=<?= (int) $baris['id_user'] ?>" data-modal data-judul="<?= $baris['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?> Akun <?= e($baris['username']) ?>"><?= $baris['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
