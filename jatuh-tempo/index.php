<?php

declare(strict_types=1);

/**
 * Halaman: Monitoring Jatuh Tempo (DFD 4.0 - pantau status piutang)
 * Fungsi: Memantau piutang belum jatuh tempo, jatuh tempo hari ini, dan sudah jatuh tempo
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Monitoring Jatuh Tempo';
$sub_judul_halaman = 'Pantau piutang yang mendekati atau melewati batas jatuh tempo';
$halaman_aktif = 'jatuh-tempo';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$status = bersihkan($_GET['status'] ?? '');
$urut = bersihkan($_GET['urut'] ?? 'tempo');
$halaman = max(1, (int) ($_GET['halaman'] ?? 1));
$perHalaman = 20;

$kondisi = ["p.status <> 'Lunas'"];
$parameter = [];

if ($cari !== '') {
    $kondisi[] = "pl.nama_pelanggan LIKE :cari ESCAPE '\\\\'";
    $parameter[':cari'] = '%' . escapeLike($cari) . '%';
}

if ($status !== '') {
    $kondisi[] = 'p.status = :status';
    $parameter[':status'] = $status;
}

$where = 'WHERE ' . implode(' AND ', $kondisi);

$sqlOrder = $urut === 'sisa' ? 'p.sisa_piutang DESC' : 'p.jatuh_tempo ASC';

$stmt = $pdo->prepare(
    "SELECT
        p.id_piutang, p.no_transaksi, p.tanggal_transaksi, p.jatuh_tempo,
        p.total_piutang, p.jumlah_dibayar, p.sisa_piutang, p.status,
        pl.nama_pelanggan,
        CASE
            WHEN p.jatuh_tempo < CURDATE() THEN 'lewat'
            WHEN p.jatuh_tempo = CURDATE() THEN 'hari-ini'
            ELSE 'mendatang'
        END AS kategori
     FROM piutang AS p
     JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
     $where
     ORDER BY FIELD(kategori, 'mendatang', 'hari-ini', 'lewat') ASC, $sqlOrder
     LIMIT :batas OFFSET :offset"
);
foreach ($parameter as $kunci => $nilai) {
    $stmt->bindValue($kunci, $nilai);
}
$stmt->bindValue(':batas', $perHalaman, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($halaman - 1) * $perHalaman, PDO::PARAM_INT);
$stmt->execute();
$daftar = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT
        CASE
            WHEN p.jatuh_tempo < CURDATE() THEN 'lewat'
            WHEN p.jatuh_tempo = CURDATE() THEN 'hari-ini'
            ELSE 'mendatang'
        END AS kategori,
        COUNT(*) AS jumlah
     FROM piutang AS p
     JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
     $where
     GROUP BY kategori"
);
$stmt->execute($parameter);
$jumlahKategori = ['mendatang' => 0, 'hari-ini' => 0, 'lewat' => 0];
foreach ($stmt->fetchAll() as $baris) {
    $jumlahKategori[$baris['kategori']] = (int) $baris['jumlah'];
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM piutang AS p JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan $where");
$stmt->execute($parameter);
$total = (int) $stmt->fetchColumn();
$totalHalaman = (int) ceil($total / $perHalaman);

// Kelompokkan baris per kategori untuk bagian halaman
$bagian = ['mendatang' => [], 'hari-ini' => [], 'lewat' => []];
foreach ($daftar as $baris) {
    $bagian[$baris['kategori']][] = $baris;
}
$adaFilter = $cari !== '' || $status !== '';
?>

<div class="aksi-halaman">
    <form method="get" action="<?= BASE_URL ?>jatuh-tempo/index.php" class="form-filter-halaman">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari Pelanggan</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Nama pelanggan">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="status">Status</label>
            <select class="pilih" id="status" name="status">
                <option value="">Semua Status</option>
                <option value="Belum Lunas" <?= $status === 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                <option value="Sebagian" <?= $status === 'Sebagian' ? 'selected' : '' ?>>Sebagian</option>
                <option value="Jatuh Tempo" <?= $status === 'Jatuh Tempo' ? 'selected' : '' ?>>Jatuh Tempo</option>
            </select>
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="urut">Urutkan</label>
            <select class="pilih" id="urut" name="urut">
                <option value="tempo" <?= $urut === 'tempo' ? 'selected' : '' ?>>Jatuh Tempo Terdekat</option>
                <option value="sisa" <?= $urut === 'sisa' ? 'selected' : '' ?>>Sisa Terbesar</option>
            </select>
        </div>
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Terapkan Filter</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>jatuh-tempo/index.php">Reset</a>
        </div>
    </form>
</div>

<?php
$judulBagian = [
    'mendatang' => 'Belum Jatuh Tempo',
    'hari-ini' => 'Jatuh Tempo Hari Ini',
    'lewat' => 'Sudah Jatuh Tempo',
];

foreach ($bagian as $kunci => $barisBagian):
?>
    <section class="mb-xl" aria-label="<?= e($judulBagian[$kunci]) ?>">
        <div class="kartu-judul">
            <h3><?= e($judulBagian[$kunci]) ?> <span class="teks-kecil">(<?= (int) $jumlahKategori[$kunci] ?>)</span></h3>
        </div>
        <div class="table-wrapper">
            <table class="tabel">
                <thead>
                    <tr>
                        <th class="col-nama">Pelanggan</th>
                        <th class="col-kode">No. Transaksi</th>
                        <th class="col-tanggal">Tanggal Transaksi</th>
                        <th class="col-tempo">Jatuh Tempo</th>
                        <th class="angka col-angka">Total Piutang</th>
                        <th class="angka col-angka">Sudah Dibayar</th>
                        <th class="angka col-angka">Sisa</th>
                        <th class="col-status">Status</th>
                        <th class="tabel-aksi col-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($barisBagian === []): ?>
                        <tr class="tabel-baris-kosong"><td colspan="9"><?= $adaFilter ? 'Tidak ada data sesuai filter.' : 'Tidak ada data pada bagian ini.' ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($barisBagian as $baris): ?>
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
                                <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_transaksi'])) ?></td>
                                <td><?= e(formatTanggalIndonesia((string) $baris['jatuh_tempo'])) ?> <span class="label-tempo <?= e($kelasTempo) ?>">(<?= e($labelTempo) ?>)</span></td>
                                <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                                <td class="angka"><?= e(formatRupiah($baris['jumlah_dibayar'])) ?></td>
                                <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                                <td><?= badgeStatus((string) $baris['status']) ?></td>
                                <td class="tabel-aksi"><a href="<?= BASE_URL ?>pembayaran/tambah.php?id_piutang=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Catat Pembayaran <?= e($baris['no_transaksi']) ?>">Bayar</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>

<?= paginasiHtml($halaman, $totalHalaman, ['cari' => $cari, 'status' => $status, 'urut' => $urut]) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>