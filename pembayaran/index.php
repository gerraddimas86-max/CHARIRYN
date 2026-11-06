<?php

declare(strict_types=1);

/**
 * Halaman: Data Pembayaran
 * Fungsi: Menampilkan daftar pembayaran dengan pencarian, filter, dan paginasi
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Data Pembayaran';
$sub_judul_halaman = 'Riwayat pembayaran piutang pelanggan';
$halaman_aktif = 'pembayaran';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$metode = bersihkan($_GET['metode'] ?? '');
$dari = parseTanggalID($_GET['dari'] ?? '') ?? '';
$sampai = parseTanggalID($_GET['sampai'] ?? '') ?? '';
$halaman = max(1, (int) bersihkan($_GET['halaman'] ?? 1));
$perHalaman = 10;

// Rentang terbalik: tukar agar tetap menampilkan data.
if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

$hasil = PembayaranService::ambilSemua($pdo, $cari, $metode, $dari, $sampai, $halaman, $perHalaman);
$daftar = $hasil['data'];
$total = (int) $hasil['total'];
$totalHalaman = (int) ceil($total / $perHalaman);
?>

<div class="aksi-halaman aksi-tumpuk">
    <form method="get" action="<?= BASE_URL ?>pembayaran/index.php" class="form-filter-halaman form-filter-tanggal">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="No. transaksi atau nama pelanggan">
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
            <label class="label-bersyarat" for="dari">Dari Tanggal</label>
            <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="dari" name="dari" value="<?= e(tampilTanggalID($_GET['dari'] ?? '')) ?>" data-tanggal-dari>
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="sampai">Sampai Tanggal</label>
            <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="sampai" name="sampai" value="<?= e(tampilTanggalID($_GET['sampai'] ?? '')) ?>" data-tanggal-sampai>
        </div>
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Terapkan Filter</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>pembayaran/index.php">Reset</a>
        </div>
    </form>
    <a class="btn btn-primer" href="<?= BASE_URL ?>pembayaran/tambah.php" data-modal data-judul="Catat Pembayaran">Catat Pembayaran</a>
</div>

<p class="jumlah-hasil">Menampilkan <strong><?= e(formatAngka(count($daftar))) ?></strong> dari <strong><?= e(formatAngka($total)) ?></strong> pembayaran.</p>

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
                <th class="tabel-aksi col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr>
                    <td colspan="7">
                        <div class="keadaan-kosong">
                            <img class="doodle-empty" src="<?= BASE_URL ?>assets/img/doodles/shapes/doodle-cloud-smile.png" alt="" aria-hidden="true" width="64" height="64">
                            <h3>Belum Ada Data</h3>
                            <p><?= $cari !== '' || $metode !== '' || $dari !== '' || $sampai !== '' ? 'Tidak ada pembayaran yang sesuai dengan filter.' : 'Belum ada pembayaran.' ?></p>
                            <a class="btn btn-primer" href="<?= BASE_URL ?>pembayaran/tambah.php" data-modal data-judul="Catat Pembayaran">Catat Pembayaran</a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <tr>
                        <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_pembayaran'])) ?></td>
                        <td><a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>"><?= e($baris['no_transaksi']) ?></a></td>
                        <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
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

<?= paginasiHtml($halaman, $totalHalaman, ['cari' => $cari, 'metode' => $metode, 'dari' => $dari, 'sampai' => $sampai]) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>