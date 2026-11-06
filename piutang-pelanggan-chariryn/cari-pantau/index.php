<?php

declare(strict_types=1);

/**
 * Halaman: Cari & Pantau Status Piutang (DFD 4.0)
 * Fungsi: Pencarian piutang berdasarkan nama pelanggan, nomor transaksi,
 * atau nominal, dengan filter status Lunas/Belum Lunas dan aksi histori.
 * Memakai catatan piutang dan perhitungan yang sama dengan halaman Transaksi.
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Cari & Pantau';
$sub_judul_halaman = 'Cari piutang dan pantau status pelunasannya';
$halaman_aktif = 'cari-pantau';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();

$cari = bersihkan($_GET['cari'] ?? '');
$status = bersihkan($_GET['status'] ?? '');
$halaman = max(1, (int) ($_GET['halaman'] ?? 1));
$perHalaman = 10;

$kondisi = [];
$parameter = [];

if ($cari !== '') {
    $cariLike = '%' . escapeLike($cari) . '%';
    $grup = ["p.no_transaksi LIKE :cari1 ESCAPE '\\\\'", "pl.nama_pelanggan LIKE :cari2 ESCAPE '\\\\'"];
    $parameter[':cari1'] = $parameter[':cari2'] = $cariLike;

    $nominal = (float) preg_replace('/[^\d]/', '', $cari);
    if ($nominal > 0) {
        // Placeholder berbeda: PDO tanpa emulasi tidak mengizinkan satu nama dipakai dua kali.
        $grup[] = 'p.total_piutang = :nominal1';
        $grup[] = 'p.sisa_piutang = :nominal2';
        $parameter[':nominal1'] = $parameter[':nominal2'] = $nominal;
    }

    $kondisi[] = '(' . implode(' OR ', $grup) . ')';
}

if ($status === 'Lunas') {
    $kondisi[] = "p.status = 'Lunas'";
} elseif ($status === 'Belum Lunas') {
    $kondisi[] = "p.status <> 'Lunas'";
}

$where = $kondisi !== [] ? 'WHERE ' . implode(' AND ', $kondisi) : '';

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM piutang AS p
     JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan ' . $where
);
$stmt->execute($parameter);
$total = (int) $stmt->fetchColumn();
$totalHalaman = (int) ceil($total / $perHalaman);
$halaman = max(1, min($halaman, max(1, $totalHalaman)));

$stmt = $pdo->prepare(
    'SELECT p.id_piutang, p.no_transaksi, p.tanggal_transaksi, p.total_piutang,
            p.jumlah_dibayar, p.sisa_piutang, p.status,
            pl.nama_pelanggan
     FROM piutang AS p
     JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan ' . $where . '
     ORDER BY p.tanggal_transaksi DESC, p.id_piutang DESC
     LIMIT :batas OFFSET :offset'
);
foreach ($parameter as $kunci => $nilai) {
    $stmt->bindValue($kunci, $nilai);
}
$stmt->bindValue(':batas', $perHalaman, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($halaman - 1) * $perHalaman, PDO::PARAM_INT);
$stmt->execute();
$daftar = $stmt->fetchAll();
?>

<div class="aksi-halaman">
    <form method="get" action="<?= BASE_URL ?>cari-pantau/index.php" class="form-filter-halaman">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari Piutang</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Nama pelanggan, nomor transaksi, atau nominal">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="status">Status</label>
            <select class="pilih" id="status" name="status">
                <option value="">Semua Status</option>
                <option value="Belum Lunas" <?= $status === 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                <option value="Lunas" <?= $status === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
            </select>
        </div>
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Cari</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>cari-pantau/index.php">Reset</a>
        </div>
    </form>
</div>

<p class="jumlah-hasil">Menampilkan <strong><?= e(formatAngka(count($daftar))) ?></strong> dari <strong><?= e(formatAngka($total)) ?></strong> piutang.</p>

<div class="table-wrapper">
    <table class="tabel">
        <thead>
            <tr>
                <th class="col-nama">Pelanggan</th>
                <th class="col-kode">No. Transaksi</th>
                <th class="angka col-angka">Total</th>
                <th class="angka col-angka">Dibayar</th>
                <th class="angka col-angka">Sisa</th>
                <th class="col-status">Status</th>
                <th class="tabel-aksi col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftar === []): ?>
                <tr>
                    <td colspan="7" class="tabel-baris-kosong"><img class="doodle-mini" src="<?= BASE_URL ?>assets/img/doodles/decor/doodle-plane-blue.png" alt="" aria-hidden="true" width="40" height="40"><br>Tidak ada piutang yang sesuai dengan pencarian.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftar as $baris): ?>
                    <tr>
                        <td class="tabel-nama"><?= e($baris['nama_pelanggan']) ?></td>
                        <td><?= e($baris['no_transaksi']) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['jumlah_dibayar'])) ?></td>
                        <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                        <td><?= badgeStatus((string) $baris['status']) ?></td>
                        <td class="tabel-aksi">
                            <a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>">Histori</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginasiHtml($halaman, $totalHalaman, ['cari' => $cari, 'status' => $status]) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
