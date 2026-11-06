<?php

declare(strict_types=1);

/**
 * Halaman: Detail Pelanggan
 * Fungsi: Menampilkan profil, ringkasan keuangan, dan riwayat transaksi pelanggan
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$pelanggan = PelangganService::ambil($pdo, $id);

if ($pelanggan === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Detail Pelanggan';
$sub_judul_halaman = $pelanggan['nama_pelanggan'];
$halaman_aktif = 'pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

$ringkasan = PelangganService::ringkasan($pdo, $id);
$riwayatPiutang = PelangganService::riwayatPiutang($pdo, $id);
$riwayatPembayaran = PelangganService::riwayatPembayaran($pdo, $id);
?>

<div class="tengah-detail">
<div class="aksi-halaman">
    <a class="btn btn-netral" href="<?= BASE_URL ?>pelanggan/index.php">Kembali</a>
    <span class="spasi-bebas"></span>
    <a class="btn btn-sekunder" href="<?= BASE_URL ?>piutang/tambah.php">Tambah Piutang</a>
    <a class="btn btn-primer" href="<?= BASE_URL ?>pelanggan/edit.php?id=<?= (int) $id ?>" data-modal data-judul="Edit Pelanggan">Edit Pelanggan</a>
</div>

<section class="kartu mb-lg" aria-label="Profil pelanggan">
    <div class="kartu-judul">
        <div>
            <h2 style="font-size:24px;"><?= e($pelanggan['nama_pelanggan']) ?></h2>
            <p class="teks-kecil"><?= e($pelanggan['kode_pelanggan']) ?></p>
        </div>
        <?= $pelanggan['status'] === 'aktif' ? '<span class="badge badge-sebagian">Aktif</span>' : '<span class="badge badge-netral">Nonaktif</span>' ?>
    </div>
    <div class="form-grid" style="margin:0;">
        <div><span class="teks-kecil">Alamat</span><p><?= e($pelanggan['alamat'] ?? '-') ?></p></div>
        <div><span class="teks-kecil">No. HP</span><p><?= e($pelanggan['no_hp'] ?? '-') ?></p></div>
        <div><span class="teks-kecil">Email</span><p><?= e($pelanggan['email'] ?? '-') ?></p></div>
        <div><span class="teks-kecil">Terdaftar</span><p><?= e(formatTanggalWaktuIndonesia((string) $pelanggan['created_at'])) ?></p></div>
        <div><span class="teks-kecil">Terakhir Diubah</span><p><?= e(formatTanggalWaktuIndonesia((string) $pelanggan['updated_at'])) ?></p></div>
    </div>
</section>

<div class="kisi-metrik mb-xl">
    <div class="kartu-metrik">
        <p class="label-metrik">Total Piutang</p>
        <p class="nilai-metrik"><?= e(formatRupiah($ringkasan['total_piutang'])) ?></p>
    </div>
    <div class="kartu-metrik">
        <p class="label-metrik">Total Dibayar</p>
        <p class="nilai-metrik"><?= e(formatRupiah($ringkasan['total_dibayar'])) ?></p>
    </div>
    <div class="kartu-metrik">
        <p class="label-metrik">Total Sisa Piutang</p>
        <p class="nilai-metrik"><?= e(formatRupiah($ringkasan['total_sisa'])) ?></p>
    </div>
    <div class="kartu-metrik">
        <p class="label-metrik">Jumlah Transaksi</p>
        <p class="nilai-metrik kecil"><?= e(formatAngka($ringkasan['jumlah_piutang'])) ?></p>
    </div>
</div>

<section style="margin-bottom:var(--spasi-xl);" aria-label="Riwayat piutang">
    <div class="kartu-judul">
        <h3>Riwayat Piutang</h3>
    </div>
    <div class="table-wrapper">
        <table class="tabel">
            <thead>
                <tr>
                    <th class="col-kode">No. Transaksi</th>
                    <th class="col-tanggal">Tanggal</th>
                    <th class="col-tempo">Jatuh Tempo</th>
                    <th class="angka col-angka">Total</th>
                    <th class="angka col-angka">Dibayar</th>
                    <th class="angka col-angka">Sisa</th>
                    <th class="col-status">Status</th>
                    <th class="tabel-aksi col-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($riwayatPiutang === []): ?>
                    <tr><td colspan="8" class="tabel-baris-kosong">Pelanggan ini belum memiliki riwayat piutang.</td></tr>
                <?php else: ?>
                    <?php foreach ($riwayatPiutang as $baris): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>"><?= e($baris['no_transaksi']) ?></a></td>
                            <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_transaksi'])) ?></td>
                            <td><?= e(formatTanggalIndonesia((string) $baris['jatuh_tempo'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['jumlah_dibayar'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                            <td><?= badgeStatus((string) $baris['status']) ?></td>
                            <td class="tabel-aksi"><a href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $baris['id_piutang'] ?>" data-modal data-judul="Histori Piutang <?= e($baris['no_transaksi']) ?>">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section aria-label="Riwayat pembayaran">
    <div class="kartu-judul">
        <h3>Riwayat Pembayaran</h3>
    </div>
    <div class="table-wrapper">
        <table class="tabel">
            <thead>
                <tr>
                    <th class="col-tanggal">Tanggal</th>
                    <th class="col-kode">No. Transaksi</th>
                    <th class="angka col-angka">Jumlah</th>
                    <th class="col-metode">Metode</th>
                    <th class="col-status">Status</th>
                    <th class="col-keterangan">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($riwayatPembayaran === []): ?>
                    <tr><td colspan="6" class="tabel-baris-kosong">Belum ada pembayaran.</td></tr>
                <?php else: ?>
                    <?php foreach ($riwayatPembayaran as $baris): ?>
                        <tr>
                            <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_pembayaran'])) ?></td>
                            <td><?= e($baris['no_transaksi']) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['jumlah_bayar'])) ?></td>
                            <td><?= e($baris['metode_pembayaran']) ?></td>
                            <td>
                                <?php if ((string) ($baris['status'] ?? 'berlaku') === 'berlaku'): ?>
                                    <span class="badge badge-lunas">Berlaku</span>
                                <?php else: ?>
                                    <span class="badge badge-jatuh-tempo">Dibatalkan</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($baris['keterangan'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>