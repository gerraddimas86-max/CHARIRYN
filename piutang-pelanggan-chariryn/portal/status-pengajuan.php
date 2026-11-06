<?php

declare(strict_types=1);

/**
 * Halaman: Status Pengajuan Pembayaran (DFD 8.6)
 * Fungsi: Pelanggan melihat status pengajuan pembayarannya beserta
 * alasan penolakan bila ada. Status dibaca langsung dari database.
 */

require_once __DIR__ . '/../includes/init.php';
requirePelanggan();

$pdo = koneksiDatabase();
$idPelanggan = idPelangganUser();

$judul_halaman = 'Status Pengajuan';
$sub_judul_halaman = 'Riwayat pengajuan pembayaran Anda';
$halaman_aktif = 'portal-status';

require_once BASE_PATH . '/includes/kepala.php';

$daftar = PengajuanService::daftarMilikPelanggan($pdo, $idPelanggan);
?>

<div class="aksi-halaman">
    <a class="btn btn-primer" href="<?= BASE_URL ?>portal/pengajuan.php">Ajukan Pembayaran</a>
</div>

<p class="jumlah-hasil">Menampilkan <strong><?= e(formatAngka(count($daftar))) ?></strong> pengajuan pembayaran.</p>

<section class="kartu-panel" aria-label="Daftar pengajuan pembayaran">
    <div class="table-wrapper">
        <table class="tabel">
            <thead>
                <tr>
                    <th class="col-tanggal">Tanggal</th>
                    <th class="col-kode">No. Transaksi</th>
                    <th class="angka col-angka">Nominal</th>
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
                                <h3>Belum Ada Pengajuan</h3>
                                <p>Belum ada pengajuan.</p>
                                <a class="btn btn-primer" href="<?= BASE_URL ?>portal/pengajuan.php">Ajukan Pembayaran</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar as $baris): ?>
                        <tr>
                            <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_pengajuan'])) ?></td>
                            <td><?= e((string) $baris['no_transaksi']) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['nominal'])) ?></td>
                            <td><?= e((string) $baris['metode_pembayaran']) ?></td>
                            <td><?= badgeStatusVerifikasi((string) $baris['status']) ?></td>
                            <td>
                                <?php if ((string) $baris['status'] === 'ditolak'): ?>
                                    <span class="teks-bahaya"><?= e((string) ($baris['alasan_penolakan'] ?? '-')) ?></span>
                                <?php elseif ((string) $baris['status'] === 'disetujui'): ?>
                                    <span class="teks-kecil">Disetujui pada <?= e(formatTanggalIndonesia((string) ($baris['tanggal_verifikasi'] ?? ''))) ?></span>
                                <?php else: ?>
                                    <span class="teks-kecil">Menunggu verifikasi manajer</span>
                                <?php endif; ?>
                            </td>
                            <td class="tabel-aksi">
                                <?php if ($baris['bukti_pembayaran'] !== null): ?>
                                    <a href="<?= BASE_URL ?>pengajuan/bukti.php?id=<?= (int) $baris['id_pengajuan'] ?>" target="_blank" rel="noopener">Bukti</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
