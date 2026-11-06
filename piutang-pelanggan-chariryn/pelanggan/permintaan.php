<?php

declare(strict_types=1);

/**
 * Halaman: Permintaan Perubahan Profil (DFD 2.4)
 * Fungsi: Manajer meninjau profil saat ini, data yang diajukan, alasan,
 * lalu menyetujui atau menolak. Profil aktif hanya berubah setelah
 * persetujuan.
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$pdo = koneksiDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPermintaan = (int) bersihkan($_POST['id_permintaan'] ?? 0);
    $tindakan = bersihkan($_POST['tindakan'] ?? '');
    $idUser = (int) userSaatIni()['id_user'];

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        setFlash('error', 'Sesi keamanan tidak valid. Silakan muat ulang halaman.');
        redirect(BASE_URL . 'pelanggan/permintaan.php');
    }

    if ($idPermintaan <= 0) {
        setFlash('error', 'Permintaan tidak valid.');
        redirect(BASE_URL . 'pelanggan/permintaan.php');
    }

    if ($tindakan === 'setujui') {
        $hasil = ProfilService::setujui($pdo, $idPermintaan, $idUser);
    } elseif ($tindakan === 'tolak') {
        $hasil = ProfilService::tolak($pdo, $idPermintaan, $idUser, bersihkan($_POST['alasan_penolakan'] ?? ''));
    } else {
        $hasil = ['ok' => false, 'pesan' => 'Tindakan tidak dikenal.'];
    }

    setFlash($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    redirect(BASE_URL . 'pelanggan/permintaan.php');
}

$judul_halaman = 'Permintaan Profil';
$sub_judul_halaman = 'Tinjau permintaan perubahan profil pelanggan';
$halaman_aktif = 'permintaan-profil';

require_once BASE_PATH . '/includes/kepala.php';

$cari = bersihkan($_GET['cari'] ?? '');
$status = bersihkan($_GET['status'] ?? '');

$daftar = ProfilService::daftar($pdo, $status, $cari);
?>

<div class="aksi-halaman aksi-tumpuk">
    <form method="get" action="<?= BASE_URL ?>pelanggan/permintaan.php" class="form-filter-halaman">
        <div class="kolom">
            <label class="label-bersyarat" for="cari">Cari</label>
            <input class="input input-cari" type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Nama, kode pelanggan, atau alasan">
        </div>
        <div class="kolom">
            <label class="label-bersyarat" for="status">Status</label>
            <select class="pilih" id="status" name="status">
                <option value="">Semua Status</option>
                <option value="menunggu" <?= $status === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                <option value="disetujui" <?= $status === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= $status === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </div>
        <div class="filter-aksi">
            <button type="submit" class="btn btn-primer">Terapkan Filter</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>pelanggan/permintaan.php">Reset</a>
        </div>
    </form>
</div>

<p class="jumlah-hasil">Menampilkan <strong><?= e(formatAngka(count($daftar))) ?></strong> permintaan perubahan profil.</p>

<?php if ($daftar === []): ?>
    <section class="kartu-panel">
        <div class="keadaan-kosong">
            <img class="doodle-empty" src="<?= BASE_URL ?>assets/img/doodles/shapes/doodle-cloud-smile.png" alt="" aria-hidden="true" width="64" height="64">
            <h3>Belum Ada Permintaan</h3>
            <p><?= $cari !== '' || $status !== '' ? 'Tidak ada permintaan yang sesuai dengan filter.' : 'Belum ada permintaan profil.' ?></p>
        </div>
    </section>
<?php else: ?>
    <?php foreach ($daftar as $baris): ?>
        <?php $perubahan = ProfilService::perubahanDari((string) $baris['data_baru']); ?>
        <section class="kartu-panel mb-lg" aria-label="Permintaan #<?= (int) $baris['id_permintaan'] ?>">
            <div class="kartu-judul">
                <div>
                    <h3><?= e((string) $baris['nama_pelanggan']) ?> (<?= e((string) $baris['kode_pelanggan']) ?>)</h3>
                    <p class="teks-kecil">
                        Diajukan <?= e(formatTanggalWaktuIndonesia((string) $baris['created_at'])) ?>
                        oleh <?= e((string) $baris['nama_pengaju']) ?>
                    </p>
                </div>
                <?= badgeStatusVerifikasi((string) $baris['status']) ?>
            </div>

            <div class="table-wrapper">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th class="col-deskripsi">Field</th>
                            <th class="col-deskripsi">Data Saat Ini</th>
                            <th class="col-deskripsi">Data Diajukan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($perubahan === []): ?>
                            <tr><td colspan="3" class="tabel-baris-kosong">Tidak ada data perubahan tercatat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($perubahan as $field => $nilai): ?>
                                <tr>
                                    <td><?= e(ProfilService::FIELD[$field] ?? $field) ?></td>
                                    <td><?= e((string) ($baris[$field] ?? '-')) ?: '-' ?></td>
                                    <td><strong><?= e((string) $nilai) ?: '-' ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="teks-kecil" style="margin-top:var(--spasi-sm);">
                <strong>Alasan pelanggan:</strong> <?= e((string) $baris['alasan']) ?>
            </p>

            <?php if ((string) $baris['status'] === 'menunggu'): ?>
                <form method="post" action="<?= BASE_URL ?>pelanggan/permintaan.php" class="grup-aksi" style="margin-top:var(--spasi-md);">
                    <?= fieldCsrf() ?>
                    <input type="hidden" name="id_permintaan" value="<?= (int) $baris['id_permintaan'] ?>">
                    <input type="hidden" name="tindakan" value="setujui">
                    <button type="submit" class="btn btn-primer btn-kecil">Setujui &amp; Perbarui Profil</button>
                </form>

                <form method="post" action="<?= BASE_URL ?>pelanggan/permintaan.php" style="margin-top:var(--spasi-md);">
                    <?= fieldCsrf() ?>
                    <input type="hidden" name="id_permintaan" value="<?= (int) $baris['id_permintaan'] ?>">
                    <input type="hidden" name="tindakan" value="tolak">
                    <div class="kolom">
                        <label for="alasan_penolakan_<?= (int) $baris['id_permintaan'] ?>">Alasan Penolakan *</label>
                        <textarea class="textarea" id="alasan_penolakan_<?= (int) $baris['id_permintaan'] ?>" name="alasan_penolakan" placeholder="Alasan ini tampil ke pelanggan." required></textarea>
                    </div>
                    <div class="form-aksi">
                        <button type="submit" class="btn btn-hapus btn-kecil">Tolak Permintaan</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="teks-kecil" style="margin-top:var(--spasi-sm);">
                    <?php if ((string) $baris['status'] === 'ditolak'): ?>
                        <strong>Alasan penolakan:</strong> <?= e((string) ($baris['alasan_penolakan'] ?? '-')) ?><br>
                    <?php endif; ?>
                    Diproses oleh <?= e((string) ($baris['nama_verifikator'] ?? '-')) ?>
                    pada <?= e(formatTanggalWaktuIndonesia((string) ($baris['tanggal_verifikasi'] ?? ''))) ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
