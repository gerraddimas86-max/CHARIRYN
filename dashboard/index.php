<?php

declare(strict_types=1);

/**
 * Halaman: Dashboard
 * Fungsi: Ringkasan piutang, status, piutang terbaru, dan jatuh tempo
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Dashboard';
$sub_judul_halaman = 'Ringkasan piutang pelanggan';
$halaman_aktif = 'dashboard';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();

$totalSisa = (float) $pdo->query('SELECT COALESCE(SUM(sisa_piutang), 0) FROM piutang')->fetchColumn();
$totalPiutang = (float) $pdo->query('SELECT COALESCE(SUM(total_piutang), 0) FROM piutang')->fetchColumn();
$totalPembayaran = (float) $pdo->query("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE status = 'berlaku'")->fetchColumn();
$transaksiBelumLunas = (int) $pdo->query("SELECT COUNT(*) FROM piutang WHERE status <> 'Lunas' AND sisa_piutang > 0.005")->fetchColumn();
$transaksiLunas = (int) $pdo->query("SELECT COUNT(*) FROM piutang WHERE status = 'Lunas'")->fetchColumn();
$transaksiJatuhTempo = (int) $pdo->query("SELECT COUNT(*) FROM piutang WHERE status = 'Jatuh Tempo' AND sisa_piutang > 0.005")->fetchColumn();
$pelangganAktif = (int) $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE status = 'aktif'")->fetchColumn();
$bayarBulanIni = $pdo->query(
    'SELECT COALESCE(SUM(jumlah_bayar), 0) AS total, COUNT(*) AS jumlah
     FROM pembayaran
     WHERE status = \'berlaku\'
       AND MONTH(tanggal_pembayaran) = MONTH(CURDATE())
       AND YEAR(tanggal_pembayaran) = YEAR(CURDATE())'
)->fetch();

$transaksiTerbaru = $pdo->query(
    'SELECT p.id_piutang, p.no_transaksi, p.tanggal_transaksi, p.total_piutang,
            p.sisa_piutang, p.status, p.jatuh_tempo, pl.nama_pelanggan
     FROM piutang AS p
     JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
     ORDER BY p.tanggal_transaksi DESC, p.id_piutang DESC
     LIMIT 5'
)->fetchAll();

$piutangBelumLunas = $pdo->query(
    'SELECT p.id_piutang, p.no_transaksi, p.total_piutang, p.jumlah_dibayar,
            p.sisa_piutang, p.status, p.jatuh_tempo, pl.nama_pelanggan
     FROM piutang AS p
     JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
     WHERE p.status <> \'Lunas\'
     ORDER BY p.jatuh_tempo ASC, p.id_piutang DESC
     LIMIT 5'
)->fetchAll();

$pembayaranTerbaru = $pdo->query(
    'SELECT b.id_pembayaran, b.tanggal_pembayaran, b.jumlah_bayar, b.metode_pembayaran,
            b.status, p.no_transaksi, pl.nama_pelanggan
     FROM pembayaran AS b
     JOIN piutang AS p ON p.id_piutang = b.id_piutang
     JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
     ORDER BY b.tanggal_pembayaran DESC, b.id_pembayaran DESC
     LIMIT 5'
)->fetchAll();

$perhatianTempo = $pdo->query(
    'SELECT p.id_piutang, p.no_transaksi, p.jatuh_tempo, p.sisa_piutang, p.status,
            pl.nama_pelanggan
     FROM piutang AS p
     JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
     WHERE p.status <> \'Lunas\'
       AND p.jatuh_tempo <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY p.jatuh_tempo ASC
     LIMIT 5'
)->fetchAll();
?>

<?php
$namaPengguna = (string) (userSaatIni()['nama'] ?? 'Pengguna');
$labelPeran = labelRole(roleUser());
?>

<section class="dasbor-kepala">
    <div class="dasbor-kepala-teks">
        <h2>Ringkasan Piutang</h2>
    </div>
    <div class="dasbor-kepala-meta">
        <span class="chip-peran"><?= e($namaPengguna) ?> &middot; <?= e($labelPeran) ?></span>
    </div>
    <img class="doodle-judul" src="<?= BASE_URL ?>assets/img/doodles/shapes/doodle-sparkle-yellow.png" alt="" aria-hidden="true" width="26" height="26">
</section>

<section class="bagian-metrik mb-lg" aria-label="Ringkasan metrik">
    <div class="kisi-metrik">
        <div class="kartu-metrik">
            <p class="label-metrik">Total Piutang</p>
            <p class="nilai-metrik"><?= e(formatRupiah($totalPiutang)) ?></p>
            <p class="teks-kecil">Semua transaksi</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Total Pembayaran</p>
            <p class="nilai-metrik"><?= e(formatRupiah($totalPembayaran)) ?></p>
            <p class="teks-kecil">Pembayaran sah</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Total Sisa Piutang</p>
            <p class="nilai-metrik"><?= e(formatRupiah($totalSisa)) ?></p>
            <p class="teks-kecil"><?= e(formatAngka($transaksiBelumLunas)) ?> belum lunas</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Piutang Lunas</p>
            <p class="nilai-metrik"><?= e(formatAngka($transaksiLunas)) ?></p>
            <p class="teks-kecil">Selesai</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Jatuh Tempo</p>
            <p class="nilai-metrik"><?= e(formatAngka($transaksiJatuhTempo)) ?></p>
            <p class="teks-kecil">Lewat tempo</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Pelanggan Aktif</p>
            <p class="nilai-metrik"><?= e(formatAngka($pelangganAktif)) ?></p>
            <p class="teks-kecil">Aktif</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Pembayaran Bulan Ini</p>
            <p class="nilai-metrik"><?= e(formatRupiah($bayarBulanIni['total'])) ?></p>
            <p class="teks-kecil"><?= e(formatAngka($bayarBulanIni['jumlah'])) ?> pembayaran</p>
        </div>
    </div>
</section>

<section class="kartu-panel mb-lg" aria-label="Transaksi terbaru">
    <div class="kartu-judul">
        <h3>Transaksi Terbaru</h3>
        <a class="btn btn-sekunder btn-kecil" href="<?= BASE_URL ?>piutang/index.php">Lihat Semua</a>
    </div>
    <?php if ($transaksiTerbaru === []): ?>
        <p class="keadaan-kosong">Belum ada transaksi.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="tabel">
                <thead>
                    <tr>
                        <th class="col-kode">No. Transaksi</th>
                        <th class="col-nama">Pelanggan</th>
                        <th class="col-tanggal">Tanggal</th>
                        <th class="angka col-angka">Total</th>
                        <th class="angka col-angka">Sisa</th>
                        <th class="col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transaksiTerbaru as $baris): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>"><?= e((string) $baris['no_transaksi']) ?></a></td>
                            <td class="tabel-nama"><?= e((string) $baris['nama_pelanggan']) ?></td>
                            <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_transaksi'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                            <td><?= badgeStatus((string) $baris['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="kartu-panel mb-lg" aria-label="Pembayaran terbaru">
        <div class="kartu-judul">
            <h3>Pembayaran Terbaru</h3>
            <a class="btn btn-sekunder btn-kecil" href="<?= BASE_URL ?>pembayaran/index.php">Lihat Semua</a>
        </div>
        <?php if ($pembayaranTerbaru === []): ?>
            <p class="keadaan-kosong">Belum ada pembayaran.</p>
        <?php else: ?>
            <ul class="daftar-pembayaran">
                <?php foreach ($pembayaranTerbaru as $baris): ?>
                    <li>
                        <div class="daftar-teks">
                            <span class="daftar-judul"><?= e($baris['nama_pelanggan']) ?></span>
                            <span class="teks-kecil"><?= e($baris['no_transaksi']) ?> &middot; <?= e(formatTanggalIndonesia((string) $baris['tanggal_pembayaran'])) ?> &middot; <?= e((string) $baris['metode_pembayaran']) ?></span>
                        </div>
                        <span class="daftar-nilai"><?= e(formatRupiah($baris['jumlah_bayar'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
</section>

<?php if ($piutangBelumLunas === []): ?>
    <section class="kartu-panel" aria-label="Piutang belum lunas">
        <div class="kartu-judul">
            <h3>Piutang Belum Lunas</h3>
            <a class="btn btn-sekunder btn-kecil" href="<?= BASE_URL ?>piutang/index.php">Lihat Semua</a>
        </div>
        <p class="keadaan-kosong">Semua piutang sudah lunas.</p>
    </section>
<?php else: ?>
    <section class="kartu-panel mb-lg" aria-label="Piutang belum lunas">
        <div class="kartu-judul">
            <h3>Piutang Belum Lunas</h3>
            <a class="btn btn-sekunder btn-kecil" href="<?= BASE_URL ?>piutang/index.php">Lihat Semua</a>
        </div>
        <div class="table-wrapper">
            <table class="tabel">
                <thead>
                    <tr>
                        <th class="col-kode">No. Transaksi</th>
                        <th class="col-nama">Pelanggan</th>
                        <th class="angka col-angka">Total</th>
                        <th class="angka col-angka">Dibayar</th>
                        <th class="angka col-angka">Sisa</th>
                        <th class="col-status">Status</th>
                        <th class="kanan col-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($piutangBelumLunas as $baris): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>"><?= e($baris['no_transaksi']) ?></a></td>
                            <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['jumlah_dibayar'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                            <td><?= badgeStatus((string) $baris['status']) ?></td>
                            <td class="kanan">
                                <div class="grup-aksi">
                                    <a class="btn btn-sekunder btn-kecil" href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>">Lihat</a>
                                    <a class="btn btn-primer btn-kecil" href="<?= BASE_URL ?>pembayaran/tambah.php?id_piutang=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Catat Pembayaran <?= e($baris['no_transaksi']) ?>">Bayar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($perhatianTempo !== []): ?>
    <section class="kartu-panel strip-perhatian" aria-label="Perhatian jatuh tempo">
        <div class="kartu-judul">
            <h3>Perhatian</h3>
            <a class="btn btn-sekunder btn-kecil" href="<?= BASE_URL ?>jatuh-tempo/index.php">Lihat Semua</a>
        </div>
        <div class="table-wrapper">
            <table class="tabel">
                <thead>
                    <tr>
                        <th class="col-nama">Pelanggan</th>
                        <th class="col-kode">No. Transaksi</th>
                        <th class="col-tempo">Jatuh Tempo</th>
                        <th class="angka col-angka">Sisa</th>
                        <th class="col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($perhatianTempo as $baris): ?>
                        <?php
                            $selisih = selisihHariDariHariIni((string) $baris['jatuh_tempo']);
                            if ($selisih > 0) {
                                $labelTempo = 'Terlambat ' . $selisih . ' hari';
                                $kelasTempo = 'lewat';
                            } elseif ($selisih === 0) {
                                $labelTempo = 'Hari ini';
                                $kelasTempo = '';
                            } else {
                                $labelTempo = abs($selisih) . ' hari lagi';
                                $kelasTempo = abs($selisih) <= 7 ? 'segera' : '';
                            }
                        ?>
                        <tr>
                            <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                            <td><a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>"><?= e($baris['no_transaksi']) ?></a></td>
                            <td><?= e(formatTanggalIndonesia((string) $baris['jatuh_tempo'])) ?> <span class="label-tempo <?= e($kelasTempo) ?>">(<?= e($labelTempo) ?>)</span></td>
                            <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                            <td><?= badgeStatus((string) $baris['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
