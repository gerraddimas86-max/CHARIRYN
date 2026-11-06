<?php

declare(strict_types=1);

/**
 * Halaman: Tambah Piutang
 * Fungsi: Mencatat piutang baru dari transaksi kredit
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Tambah Piutang';
$sub_judul_halaman = 'Catat piutang baru dari penjualan kredit';
$halaman_aktif = 'piutang';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();
$daftarPelanggan = PelangganService::ambilOpsi($pdo);
$kesalahan = [];

$idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
$noTransaksi = (string) ($_POST['no_transaksi'] ?? '');
$tanggalTransaksi = (string) ($_POST['tanggal_transaksi'] ?? date('Y-m-d'));
$jatuhTempo = (string) ($_POST['jatuh_tempo'] ?? date('Y-m-d', strtotime('+30 days')));
$total = (string) ($_POST['total_piutang'] ?? '');
$keterangan = '';

// Saran nomor transaksi
if ($noTransaksi === '') {
    $jumlahAda = (int) $pdo->query('SELECT COUNT(*) FROM piutang')->fetchColumn();
    $noTransaksi = 'TRX-' . date('Ymd') . '-' . str_pad((string) ($jumlahAda + 1), 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
    $noTransaksi = bersihkan($_POST['no_transaksi'] ?? '');
    $tanggalTransaksi = parseTanggalID($_POST['tanggal_transaksi'] ?? '') ?? '';
    $jatuhTempo = parseTanggalID($_POST['jatuh_tempo'] ?? '') ?? '';
    $total = bersihkan($_POST['total_piutang'] ?? '');
    $keterangan = bersihkan($_POST['keterangan'] ?? '');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    validasiWajib($noTransaksi, 'Nomor transaksi', $kesalahan);
    validasiTanggal($tanggalTransaksi, 'Tanggal transaksi', $kesalahan);
    validasiTanggal($jatuhTempo, 'Jatuh tempo', $kesalahan);

    if ($idPelanggan <= 0) {
        $kesalahan[] = 'Pelanggan wajib dipilih.';
    } elseif (PelangganService::ambil($pdo, $idPelanggan) === null) {
        $kesalahan[] = 'Pelanggan tidak terdaftar.';
    }

    $nilaiTotal = parseAngkaRupiah($total);
    validasiAngkaPositif($nilaiTotal, 'Total piutang', $kesalahan);

    if ($kesalahan === [] && $jatuhTempo < $tanggalTransaksi) {
        $kesalahan[] = 'Jatuh tempo tidak boleh lebih awal dari tanggal transaksi.';
    }

    if ($kesalahan === [] && !PiutangService::noTransaksiTersedia($pdo, $noTransaksi)) {
        $kesalahan[] = 'Nomor transaksi sudah digunakan.';
    }

    if ($kesalahan === []) {
        PiutangService::buatPiutang($pdo, [
            'id_pelanggan' => $idPelanggan,
            'no_transaksi' => $noTransaksi,
            'tanggal_transaksi' => $tanggalTransaksi,
            'total_piutang' => $nilaiTotal,
            'jatuh_tempo' => $jatuhTempo,
            'keterangan' => $keterangan,
        ]);
        setFlash('success', 'Piutang berhasil ditambahkan.');
        redirect(BASE_URL . 'piutang/index.php');
    }

    $total = (string) $nilaiTotal;
}
?>

<div class="kotak-form">
    <div class="aksi-halaman" style="margin-bottom:var(--spasi-md);">
        <a class="btn btn-netral btn-kecil" href="<?= BASE_URL ?>piutang/index.php">Kembali</a>
    </div>
    <h2>Formulir Piutang Baru</h2>
    <p class="teks-kecil mb-lg">Status piutang dihitung otomatis oleh sistem.</p>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>piutang/tambah.php" data-anti-ganda>
        <?= fieldCsrf() ?>
        <div class="form-grid">
            <div class="kolom">
                <label for="id_pelanggan">Pelanggan *</label>
                <select class="pilih" id="id_pelanggan" name="id_pelanggan" required>
                    <option value="">-- Pilih Pelanggan --</option>
                    <?php foreach ($daftarPelanggan as $p): ?>
                        <option value="<?= (int) $p['id_pelanggan'] ?>" <?= $idPelanggan === (int) $p['id_pelanggan'] ? 'selected' : '' ?><?= ($p['status'] ?? 'aktif') !== 'aktif' ? ' disabled' : '' ?>><?= e($p['nama_pelanggan']) ?> (<?= e($p['kode_pelanggan']) ?>)<?= ($p['status'] ?? 'aktif') !== 'aktif' ? ' - nonaktif' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="kolom">
                <label for="no_transaksi">Nomor Transaksi *</label>
                <input class="input" type="text" id="no_transaksi" name="no_transaksi" value="<?= e($noTransaksi) ?>" required>
                <span class="bantuan">Bersifat unik</span>
            </div>
            <div class="kolom">
                <label for="tanggal_transaksi">Tanggal Transaksi *</label>
                <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="tanggal_transaksi" name="tanggal_transaksi" value="<?= e(tampilTanggalID($tanggalTransaksi)) ?>" required>
            </div>
            <div class="kolom">
                <label for="jatuh_tempo">Jatuh Tempo *</label>
                <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="jatuh_tempo" name="jatuh_tempo" value="<?= e(tampilTanggalID($jatuhTempo)) ?>" required>
                <span class="bantuan">Tidak boleh lebih awal dari transaksi</span>
            </div>
            <div class="kolom">
                <label for="total_piutang">Total Piutang (Rp) *</label>
                <input class="input" type="text" inputmode="numeric" id="total_piutang" name="total_piutang" value="<?= e($total) ?>" placeholder="Contoh: 1500000" data-rupiah required>
                <span class="bantuan">Nilai yang diberikan secara kredit</span>
            </div>
            <div class="kolom" style="grid-column:1 / -1;">
                <label for="keterangan">Keterangan</label>
                <textarea class="textarea" id="keterangan" name="keterangan" placeholder="Catatan tambahan (opsional)"><?= e($keterangan) ?></textarea>
            </div>
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn btn-primer">Simpan Data</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>piutang/index.php">Batal</a>
        </div>
    </form>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>