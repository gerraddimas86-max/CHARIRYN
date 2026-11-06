<?php

declare(strict_types=1);

/**
 * Halaman: Ajukan Pembayaran Piutang (DFD 8.5)
 * Fungsi: Pelanggan mengajukan pembayaran atas tagihan miliknya
 * sendiri dan mengunggah bukti. Pengajuan disimpan sebagai pengajuan
 * (D5) dan menunggu verifikasi manajer - belum mengurangi saldo.
 */

require_once __DIR__ . '/../includes/init.php';
requirePelanggan();

$pdo = koneksiDatabase();
$idPelanggan = idPelangganUser();

$judul_halaman = 'Ajukan Pembayaran';
$sub_judul_halaman = 'Ajukan pembayaran atas tagihan Anda';
$halaman_aktif = 'portal-pengajuan';

$kesalahan = [];
$idPiutang = (int) ($_POST['id_piutang'] ?? $_GET['id_piutang'] ?? 0);
$nominal = '';
$metode = 'Transfer';
$tanggal = date('Y-m-d');
$catatan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPiutang = (int) ($_POST['id_piutang'] ?? 0);
    $nominal = bersihkan($_POST['nominal'] ?? '');
    $metode = bersihkan($_POST['metode_pembayaran'] ?? '');
    $tanggal = parseTanggalID($_POST['tanggal_pengajuan'] ?? '') ?? '';
    $catatan = bersihkan($_POST['catatan'] ?? '');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    validasiTanggal($tanggal, 'Tanggal pengajuan', $kesalahan);

    $nilaiNominal = parseAngkaRupiah($nominal);
    if ($nilaiNominal <= 0) {
        $kesalahan[] = 'Nominal pengajuan harus lebih dari 0.';
    }

    if (!in_array($metode, PengajuanService::METODE, true)) {
        $kesalahan[] = 'Metode pembayaran tidak valid.';
    }

    $berkas = validasiUnggahanBukti($_FILES['bukti_pembayaran'] ?? null);
    if (!$berkas['ok']) {
        $kesalahan[] = $berkas['pesan'];
    }

    if ($kesalahan === []) {
        $simpan = simpanBerkasBukti((string) $berkas['nama']);
        if (!$simpan['ok']) {
            $kesalahan[] = $simpan['pesan'];
        } else {
            $hasil = PengajuanService::buat($pdo, $idPelanggan, (int) userSaatIni()['id_user'], [
                'id_piutang' => $idPiutang,
                'nominal' => $nilaiNominal,
                'metode_pembayaran' => $metode,
                'tanggal_pengajuan' => $tanggal,
                'catatan' => $catatan,
                'nama_berkas_simpan' => (string) $berkas['nama'],
            ]);

            if ($hasil['ok']) {
                setFlash('success', $hasil['pesan']);
                redirect(BASE_URL . 'portal/status-pengajuan.php');
            }

            // Pengajuan gagal disimpan: berkas bukti tidak boleh tertinggal.
            hapusBerkasBukti((string) $berkas['nama']);
            $kesalahan[] = $hasil['pesan'];
        }
    }

    $nominal = (string) $nilaiNominal;
}

require_once BASE_PATH . '/includes/kepala.php';

$stmtDaftar = $pdo->prepare(
    'SELECT p.id_piutang, p.no_transaksi, p.sisa_piutang, p.jatuh_tempo, p.status
     FROM piutang AS p
     WHERE p.id_pelanggan = :id AND p.sisa_piutang > 0.005
     ORDER BY p.jatuh_tempo ASC, p.id_piutang ASC'
);
$stmtDaftar->execute([':id' => $idPelanggan]);
$daftarPiutang = $stmtDaftar->fetchAll();
?>

<div class="kartu mb-lg" aria-label="Informasi pengajuan">
    <div class="alert alert-info" role="alert">
        <div class="alert-ikon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        </div>
        <p>Ditinjau manajer. Saldo tagihan Anda <strong>belum berkurang</strong> sampai pengajuan disetujui dan pembayaran dicatat.</p>
    </div>
</div>

<?php if ($kesalahan !== []): ?>
    <div class="kartu mb-lg">
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($daftarPiutang === []): ?>
    <section class="kartu-panel">
        <div class="keadaan-kosong">
            <img class="doodle-empty" src="<?= BASE_URL ?>assets/img/doodles/ui/doodle-check-pink.png" alt="" aria-hidden="true" width="64" height="64">
            <h3>Tidak Ada Tagihan Menunggu Pembayaran</h3>
            <p>Semua tagihan Anda sudah lunas.</p>
            <a class="btn btn-primer" href="<?= BASE_URL ?>portal/index.php">Kembali ke Dashboard</a>
        </div>
    </section>
<?php else: ?>
    <div class="kotak-form">
        <h2>Formulir Pengajuan Pembayaran</h2>

        <form method="post" action="<?= BASE_URL ?>portal/pengajuan.php" enctype="multipart/form-data" data-anti-ganda>
            <?= fieldCsrf() ?>
            <div class="form-grid">
                <div class="kolom" style="grid-column:1 / -1;">
                    <label for="id_piutang">Tagihan *</label>
                    <select class="pilih" id="id_piutang" name="id_piutang" required>
                        <option value="">-- Pilih Tagihan --</option>
                        <?php foreach ($daftarPiutang as $p): ?>
                            <option value="<?= (int) $p['id_piutang'] ?>" <?= $idPiutang === (int) $p['id_piutang'] ? 'selected' : '' ?>>
                                <?= e((string) $p['no_transaksi']) ?> - sisa <?= e(formatRupiah($p['sisa_piutang'])) ?> (jatuh tempo <?= e(formatTanggalIndonesia((string) $p['jatuh_tempo'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="kolom">
                    <label for="nominal">Nominal Pengajuan (Rp) *</label>
                    <input class="input" type="text" inputmode="numeric" id="nominal" name="nominal" value="<?= e($nominal) ?>" placeholder="Contoh: 500000" data-rupiah required>
                </div>
                <div class="kolom">
                    <label for="tanggal_pengajuan">Tanggal Pengajuan *</label>
                    <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="tanggal_pengajuan" name="tanggal_pengajuan" value="<?= e(tampilTanggalID($tanggal)) ?>" required>
                </div>
                <div class="kolom">
                    <label for="metode_pembayaran">Metode Pembayaran *</label>
                    <select class="pilih" id="metode_pembayaran" name="metode_pembayaran">
                        <?php foreach (PengajuanService::METODE as $pilihan): ?>
                            <option value="<?= e($pilihan) ?>" <?= $metode === $pilihan ? 'selected' : '' ?>><?= e($pilihan) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="kolom">
                    <label for="bukti_pembayaran">Bukti Pembayaran *</label>
                    <input class="input" type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.pdf" required>
                    <span class="bantuan">JPG, PNG, atau PDF. Maks. 2 MB</span>
                </div>
                <div class="kolom" style="grid-column:1 / -1;">
                    <label for="catatan">Catatan</label>
                    <textarea class="textarea" id="catatan" name="catatan" placeholder="Keterangan tambahan untuk manajer (opsional)"><?= e($catatan) ?></textarea>
                </div>
            </div>
            <div class="form-aksi">
                <button type="submit" class="btn btn-primer">Kirim Pengajuan</button>
                <a class="btn btn-netral" href="<?= BASE_URL ?>portal/index.php">Batal</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
