<?php

declare(strict_types=1);

/**
 * Halaman: Dashboard Pelanggan (DFD 8.0)
 * Fungsi: Menampilkan piutang, saldo, riwayat, dan pengingat jatuh tempo
 * milik pelanggan yang sedang login. Identitas diambil dari sesi server.
 */

require_once __DIR__ . '/../includes/init.php';
requirePelanggan();

$judul_halaman = 'Dashboard Pelanggan';
$sub_judul_halaman = 'Informasi piutang milik Anda';
$halaman_aktif = 'portal';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();
$idPelanggan = idPelangganUser();

$profil = PortalService::profil($pdo, $idPelanggan);
if ($profil === null) {
    hancurkanSesi();
    setFlash('error', 'Data pelanggan tidak ditemukan. Silakan hubungi manajer.');
    redirect(halamanLoginPeran('pelanggan'));
}

$ringkasan = PortalService::ringkasan($pdo, $idPelanggan);
$daftarPiutang = PortalService::daftarPiutang($pdo, $idPelanggan);
$riwayatPembayaran = PortalService::riwayatPembayaran($pdo, $idPelanggan);

$lewatTempo = array_values(array_filter($daftarPiutang, static fn (array $baris): bool => $baris['kategori_tempo'] === 'lewat'));
$segeraTempo = array_values(array_filter($daftarPiutang, static fn (array $baris): bool => in_array($baris['kategori_tempo'], ['hari-ini', 'mendatang'], true) && selisihHariDariHariIni((string) $baris['jatuh_tempo']) >= -7));
?>

<section class="dasbor-kepala">
    <div class="dasbor-kepala-teks">
        <h2>Halo, <?= e((string) $profil['nama_pelanggan']) ?></h2>
        <p class="teks-kecil"><?= e((string) $profil['kode_pelanggan']) ?> &middot; <?= e((string) ($profil['no_hp'] ?? '-')) ?></p>
    </div>
    <div class="dasbor-kepala-meta">
        <span class="chip-peran">Pelanggan</span>
    </div>
</section>

<?php if ($lewatTempo !== []): ?>
    <div class="alert alert-warning" role="alert">
        <div class="alert-ikon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <p>Anda memiliki <strong><?= e(formatAngka(count($lewatTempo))) ?> tagihan</strong> yang sudah melewati jatuh tempo. Segera lakukan pembayaran.</p>
    </div>
<?php endif; ?>

<section class="bagian-metrik mb-lg" aria-label="Ringkasan piutang saya">
    <div class="kisi-metrik">
        <div class="kartu-metrik">
            <p class="label-metrik">Total Sisa Piutang</p>
            <p class="nilai-metrik"><?= e(formatRupiah($ringkasan['total_sisa'])) ?></p>
            <p class="teks-kecil"><?= e(formatAngka($ringkasan['belum_lunas'])) ?> belum lunas</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Total Piutang</p>
            <p class="nilai-metrik"><?= e(formatRupiah($ringkasan['total_piutang'])) ?></p>
            <p class="teks-kecil"><?= e(formatAngka($ringkasan['jumlah_piutang'])) ?> transaksi</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Total Dibayar</p>
            <p class="nilai-metrik"><?= e(formatRupiah($ringkasan['total_dibayar'])) ?></p>
            <p class="teks-kecil"><?= e(formatAngka($ringkasan['lunas'])) ?> lunas</p>
        </div>
        <div class="kartu-metrik">
            <p class="label-metrik">Jatuh Tempo</p>
            <p class="nilai-metrik"><?= e(formatAngka($ringkasan['lewat_tempo'])) ?></p>
            <p class="teks-kecil"><?= e(formatAngka($ringkasan['segera_tempo'])) ?> dalam 7 hari</p>
        </div>
    </div>
</section>

<?php if ($segeraTempo !== []): ?>
    <section class="kartu-panel strip-perhatian mb-lg" aria-label="Pengingat jatuh tempo">
        <div class="kartu-judul">
            <h3>Pengingat Jatuh Tempo</h3>
        </div>
        <ul class="daftar-pembayaran">
            <?php foreach ($segeraTempo as $baris): ?>
                <?php $selisih = selisihHariDariHariIni((string) $baris['jatuh_tempo']); ?>
                <li>
                    <div class="daftar-teks">
                        <span class="daftar-judul"><?= e((string) $baris['no_transaksi']) ?></span>
                        <span class="teks-kecil">
                            Jatuh tempo <?= e(formatTanggalIndonesia((string) $baris['jatuh_tempo'], true)) ?>
                            &middot;
                            <?= $selisih === 0 ? 'hari ini' : 'dalam ' . abs($selisih) . ' hari' ?>
                        </span>
                    </div>
                    <span class="daftar-nilai"><?= e(formatRupiah($baris['sisa_piutang'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="kartu-panel mb-lg" aria-label="Daftar tagihan">
    <div class="kartu-judul">
        <h3>Tagihan Saya</h3>
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
                <?php if ($daftarPiutang === []): ?>
                    <tr><td colspan="8" class="tabel-baris-kosong">Belum ada tagihan.</td></tr>
                <?php else: ?>
                    <?php foreach ($daftarPiutang as $baris): ?>
                        <tr>
                            <td><?= e((string) $baris['no_transaksi']) ?></td>
                            <td><?= e(formatTanggalIndonesia((string) $baris['tanggal_transaksi'])) ?></td>
                            <td>
                                <?= e(formatTanggalIndonesia((string) $baris['jatuh_tempo'])) ?>
                                <?php if ($baris['kategori_tempo'] === 'lewat'): ?>
                                    <span class="label-tempo lewat">(lewat <?= e(formatAngka(abs(selisihHariDariHariIni((string) $baris['jatuh_tempo'])))) ?> hari)</span>
                                <?php elseif ($baris['kategori_tempo'] === 'hari-ini'): ?>
                                    <span class="label-tempo">(hari ini)</span>
                                <?php endif; ?>
                            </td>
                            <td class="angka"><?= e(formatRupiah($baris['total_piutang'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['jumlah_dibayar'])) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['sisa_piutang'])) ?></td>
                            <td><?= badgeStatus((string) $baris['status']) ?></td>
                            <td class="tabel-aksi"><a href="<?= BASE_URL ?>portal/detail.php?id=<?= (int) $baris['id_piutang'] ?>">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="kartu-panel" aria-label="Riwayat pembayaran">
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
                            <td><?= e((string) $baris['no_transaksi']) ?></td>
                            <td class="angka"><?= e(formatRupiah($baris['jumlah_bayar'])) ?></td>
                            <td><?= e((string) $baris['metode_pembayaran']) ?></td>
                            <td>
                                <?php if ((string) ($baris['status'] ?? 'berlaku') === 'berlaku'): ?>
                                    <span class="badge badge-lunas">Berlaku</span>
                                <?php else: ?>
                                    <span class="badge badge-jatuh-tempo">Dibatalkan</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($baris['keterangan'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
