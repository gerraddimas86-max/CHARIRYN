<?php

declare(strict_types=1);

/**
 * Halaman: Tambah Pembayaran
 * Fungsi: Mencatat pembayaran piutang dengan validasi terhadap sisa piutang
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Tambah Pembayaran';
$sub_judul_halaman = 'Catat pembayaran piutang pelanggan';
$halaman_aktif = 'pembayaran';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();
$daftarPiutang = PiutangService::ambilOpsiUntukBayar($pdo);
$kesalahan = [];

 $idPiutang = (int) ($_POST['id_piutang'] ?? $_GET['id_piutang'] ?? 0);
 $tanggal = (string) ($_POST['tanggal_pembayaran'] ?? date('Y-m-d'));
 $jumlah = '';
 $metode = (string) ($_POST['metode_pembayaran'] ?? 'Transfer');
 $keterangan = '';
 $lunasiMode = ($_GET['lunasi'] ?? '') === '1';
 $piutangTerpilih = null;
 foreach ($daftarPiutang as $opsi) {
     if ((int) $opsi['id_piutang'] === $idPiutang) {
         $piutangTerpilih = $opsi;
         break;
     }
 }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $lunasiMode && $piutangTerpilih !== null) {
    $sisaLunasi = round((float) $piutangTerpilih['sisa_piutang'], 2);
    $jumlah = $sisaLunasi == (int) $sisaLunasi ? (string) (int) $sisaLunasi : number_format($sisaLunasi, 2, ',', '.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPiutang = (int) ($_POST['id_piutang'] ?? 0);
    $tanggal = parseTanggalID($_POST['tanggal_pembayaran'] ?? '') ?? '';
    $jumlah = bersihkan($_POST['jumlah_bayar'] ?? '');
    $metode = bersihkan($_POST['metode_pembayaran'] ?? '');
    $keterangan = bersihkan($_POST['keterangan'] ?? '');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    validasiTanggal($tanggal, 'Tanggal pembayaran', $kesalahan);

    $nilaiJumlah = parseAngkaRupiah($jumlah);

    if ($idPiutang <= 0) {
        $kesalahan[] = 'Piutang wajib dipilih.';
    } elseif ($nilaiJumlah <= 0) {
        $kesalahan[] = 'Jumlah bayar harus lebih dari 0.';
    } elseif (!in_array($metode, ['Tunai', 'Transfer', 'E-Wallet'], true)) {
        $kesalahan[] = 'Metode pembayaran tidak valid.';
    } elseif ($kesalahan === []) {
        $hasil = PembayaranService::buatPembayaran($pdo, [
            'id_piutang' => $idPiutang,
            'tanggal_pembayaran' => $tanggal,
            'jumlah_bayar' => $nilaiJumlah,
            'metode_pembayaran' => $metode,
            'keterangan' => $keterangan,
        ]);

        if ($hasil['ok']) {
            setFlash('success', $hasil['pesan']);
            redirect(BASE_URL . 'piutang/detail.php?id=' . $idPiutang);
        }

        $kesalahan[] = $hasil['pesan'];
    }

    $jumlah = (string) $nilaiJumlah;
    $piutangTerpilih = null;
    foreach ($daftarPiutang as $opsi) {
        if ((int) $opsi['id_piutang'] === $idPiutang) {
            $piutangTerpilih = $opsi;
            break;
        }
    }
}
?>

<div class="kotak-form">
    <div class="aksi-halaman" style="margin-bottom:var(--spasi-md);">
        <a class="btn btn-netral btn-kecil" href="<?= BASE_URL ?>pembayaran/index.php">Kembali</a>
    </div>
    <h2>Formulir Pembayaran Baru</h2>
    <p class="teks-kecil mb-lg">Jumlah pembayaran tidak boleh melebihi sisa piutang.</p>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($daftarPiutang === []): ?>
        <div class="keadaan-kosong">
            <img class="doodle-empty" src="<?= BASE_URL ?>assets/img/doodles/ui/doodle-check-pink.png" alt="" aria-hidden="true" width="64" height="64">
            <h3>Tidak Ada Piutang Aktif</h3>
            <p>Semua piutang sudah lunas.</p>
            <a class="btn btn-primer" href="<?= BASE_URL ?>piutang/tambah.php">Tambah Piutang</a>
        </div>
    <?php else: ?>
        <script type="application/json" id="dataPiutang"><?= json_encode(array_values($daftarPiutang), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

        <form method="post" action="<?= BASE_URL ?>pembayaran/tambah.php<?= $lunasiMode && $idPiutang > 0 ? '?id_piutang=' . (int) $idPiutang . '&lunasi=1' : '' ?>" data-anti-ganda>
            <?= fieldCsrf() ?>
            <div class="form-grid">
                <div class="kolom">
                    <label for="id_piutang">Piutang *</label>
                    <select class="pilih" id="id_piutang" name="id_piutang" required>
                        <option value="">-- Pilih Piutang --</option>
                        <?php foreach ($daftarPiutang as $p): ?>
                            <option value="<?= (int) $p['id_piutang'] ?>" <?= $idPiutang === (int) $p['id_piutang'] ? 'selected' : '' ?> data-status="<?= e($p['status']) ?>">
                                <?= e($p['no_transaksi']) ?> - <?= e($p['nama_pelanggan']) ?> (sisa <?= e(formatRupiah($p['sisa_piutang'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="kolom">
                    <label for="tanggal_pembayaran">Tanggal Pembayaran *</label>
                    <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="tanggal_pembayaran" name="tanggal_pembayaran" value="<?= e(tampilTanggalID($tanggal)) ?>" required>
                </div>
            </div>

            <div class="kartu-pratinjau mb-lg" aria-live="polite">
                <h4 style="margin-bottom:var(--spasi-sm);">Konteks Piutang</h4>
                <div class="form-grid" style="margin:0;">
                    <div><span class="teks-kecil">Pelanggan</span><p id="pratinjauNama"><?= $piutangTerpilih !== null ? e($piutangTerpilih['nama_pelanggan']) : '-' ?></p></div>
                    <div><span class="teks-kecil">Total Piutang</span><p id="pratinjauTotal"><?= $piutangTerpilih !== null ? e(formatRupiah($piutangTerpilih['total_piutang'])) : '-' ?></p></div>
                    <div><span class="teks-kecil">Sudah Dibayar</span><p id="pratinjauDibayar"><?= $piutangTerpilih !== null ? e(formatRupiah($piutangTerpilih['jumlah_dibayar'])) : '-' ?></p></div>
                    <div><span class="teks-kecil">Sisa Piutang</span><p id="pratinjauSisa"><?= $piutangTerpilih !== null ? e(formatRupiah($piutangTerpilih['sisa_piutang'])) : '-' ?></p></div>
                </div>
                <p class="teks-kecil" style="margin-top:var(--spasi-sm);">Sisa setelah pembayaran ini: <strong id="pratinjauSisaSetelah">-</strong></p>
                <div class="alert alert-warning" id="peringatanLebih" role="alert" hidden>
                    <p>Melebihi sisa piutang.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="kolom">
                    <label for="jumlah_bayar">Jumlah Bayar (Rp) *</label>
                    <input class="input" type="text" inputmode="numeric" id="jumlah_bayar" name="jumlah_bayar" value="<?= e($jumlah) ?>" placeholder="Contoh: 500000" data-rupiah required>
                </div>
                <div class="kolom">
                    <label for="metode_pembayaran">Metode Pembayaran *</label>
                    <select class="pilih" id="metode_pembayaran" name="metode_pembayaran">
                        <option value="Tunai" <?= $metode === 'Tunai' ? 'selected' : '' ?>>Tunai</option>
                        <option value="Transfer" <?= $metode === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                        <option value="E-Wallet" <?= $metode === 'E-Wallet' ? 'selected' : '' ?>>E-Wallet</option>
                    </select>
                </div>
                <div class="kolom" style="grid-column:1 / -1;">
                    <label for="keterangan">Keterangan</label>
                    <textarea class="textarea" id="keterangan" name="keterangan" placeholder="Catatan pembayaran (opsional)"><?= e($keterangan) ?></textarea>
                </div>
            </div>


            <div class="form-aksi">
                <?php if ($lunasiMode && $piutangTerpilih !== null): ?>
                <button type="submit" class="btn btn-primer" data-konfirmasi="Lunasi piutang <?= e($piutangTerpilih['no_transaksi']) ?> sebesar <?= e(formatRupiah($piutangTerpilih['sisa_piutang'])) ?>?">Lunasi Piutang</button>
                <?php else: ?>
                <button type="submit" class="btn btn-primer">Simpan Pembayaran</button>
                <?php endif; ?>
                <a class="btn btn-netral" href="<?= BASE_URL ?>pembayaran/index.php">Batal</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>