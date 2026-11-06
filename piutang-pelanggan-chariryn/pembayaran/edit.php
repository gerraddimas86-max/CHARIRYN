<?php

declare(strict_types=1);

/**
 * Halaman: Edit Pembayaran
 * Fungsi: Memperbarui pembayaran dan menghitung ulang piutang terkait
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$pembayaran = PembayaranService::ambil($pdo, $id);

if ($pembayaran === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Edit Pembayaran';
$sub_judul_halaman = $pembayaran['no_transaksi'];
$halaman_aktif = 'pembayaran';

require_once BASE_PATH . '/includes/kepala.php';

$kesalahan = [];

$tanggal = (string) $pembayaran['tanggal_pembayaran'];
$jumlah = (string) $pembayaran['jumlah_bayar'];
$metode = (string) $pembayaran['metode_pembayaran'];
$keterangan = (string) ($pembayaran['keterangan'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = parseTanggalID($_POST['tanggal_pembayaran'] ?? '') ?? '';
    $jumlah = bersihkan($_POST['jumlah_bayar'] ?? '');
    $metode = bersihkan($_POST['metode_pembayaran'] ?? '');
    $keterangan = bersihkan($_POST['keterangan'] ?? '');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    validasiTanggal($tanggal, 'Tanggal pembayaran', $kesalahan);

    $nilaiJumlah = parseAngkaRupiah($jumlah);

    if ($nilaiJumlah <= 0) {
        $kesalahan[] = 'Jumlah bayar harus lebih dari 0.';
    } elseif (!in_array($metode, ['Tunai', 'Transfer', 'E-Wallet'], true)) {
        $kesalahan[] = 'Metode pembayaran tidak valid.';
    } elseif ($kesalahan === []) {
        $hasil = PembayaranService::ubahPembayaran($pdo, $id, [
            'tanggal_pembayaran' => $tanggal,
            'jumlah_bayar' => $nilaiJumlah,
            'metode_pembayaran' => $metode,
            'keterangan' => $keterangan,
        ]);

        if ($hasil['ok']) {
            setFlash('success', $hasil['pesan']);
            redirect(BASE_URL . 'piutang/detail.php?id=' . (int) $pembayaran['id_piutang']);
        }

        $kesalahan[] = $hasil['pesan'];
    }

    $jumlah = (string) $nilaiJumlah;
}

$daftarPiutang = [[
    'id_piutang' => (int) $pembayaran['id_piutang'],
    'no_transaksi' => $pembayaran['no_transaksi'],
    'total_piutang' => (float) $pembayaran['total_piutang'],
    'jumlah_dibayar' => (float) $pembayaran['jumlah_dibayar'],
    'sisa_piutang' => (float) $pembayaran['sisa_piutang'],
    'nama_pelanggan' => $pembayaran['nama_pelanggan'],
]];
?>

<div class="kotak-form">
    <div class="aksi-halaman" style="margin-bottom:var(--spasi-md);">
        <a class="btn btn-netral btn-kecil" href="<?= BASE_URL ?>pembayaran/detail.php?id=<?= (int) $id ?>">Kembali</a>
    </div>
    <h2>Formulir Edit Pembayaran</h2>
    <p class="teks-kecil mb-lg">Perubahan jumlah menghitung ulang sisa piutang.</p>

    <?php if ((string) ($pembayaran['status'] ?? 'berlaku') !== 'berlaku'): ?>
        <div class="alert alert-warning" role="alert">
            <div class="alert-ikon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <p>Pembayaran ini sudah dibatalkan.</p>
        </div>
    <?php endif; ?>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <script type="application/json" id="dataPiutang"><?= json_encode($daftarPiutang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <?php if ((string) ($pembayaran['status'] ?? 'berlaku') !== 'berlaku'): ?>
        <div class="form-aksi">
            <a class="btn btn-netral" href="<?= BASE_URL ?>pembayaran/detail.php?id=<?= (int) $id ?>">Kembali ke Detail</a>
        </div>
    <?php else: ?>

    <form method="post" action="<?= BASE_URL ?>pembayaran/edit.php?id=<?= (int) $id ?>" data-anti-ganda>
        <?= fieldCsrf() ?>
        <input type="hidden" id="id_piutang" name="id_piutang" value="<?= (int) $pembayaran['id_piutang'] ?>">
        <input type="hidden" id="jumlah_lama" value="<?= e($pembayaran['jumlah_bayar']) ?>">
        <div class="form-grid">
            <div class="kolom">
                <label>Piutang</label>
                <input class="input" type="text" value="<?= e($pembayaran['no_transaksi']) ?> - <?= e($pembayaran['nama_pelanggan']) ?>" disabled>
            </div>
            <div class="kolom">
                <label for="tanggal_pembayaran">Tanggal Pembayaran *</label>
                <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="tanggal_pembayaran" name="tanggal_pembayaran" value="<?= e(tampilTanggalID($tanggal)) ?>" required>
            </div>
            <div class="kolom">
                <label for="jumlah_bayar">Jumlah Bayar (Rp) *</label>
                <input class="input" type="text" inputmode="numeric" id="jumlah_bayar" name="jumlah_bayar" value="<?= e($jumlah) ?>" data-rupiah required>
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
                <textarea class="textarea" id="keterangan" name="keterangan"><?= e($keterangan) ?></textarea>
            </div>
        </div>

        <div class="kartu-pratinjau mb-lg">
            <h4 style="margin-bottom:var(--spasi-sm);">Pratinjau Pembayaran</h4>
            <div class="form-grid" style="margin:0;">
                <div><span class="teks-kecil">Pelanggan</span><p id="pratinjauNama">-</p></div>
                <div><span class="teks-kecil">Total Piutang</span><p id="pratinjauTotal">-</p></div>
                <div><span class="teks-kecil">Sudah Dibayar</span><p id="pratinjauDibayar">-</p></div>
                <div><span class="teks-kecil">Sisa Piutang</span><p id="pratinjauSisa">-</p></div>
            </div>
            <p class="teks-kecil" style="margin-top:var(--spasi-sm);">Sisa setelah pembayaran ini: <strong id="pratinjauSisaSetelah">-</strong></p>
            <div class="alert alert-warning" id="peringatanLebih" role="alert" hidden>
                <p>Melebihi sisa piutang.</p>
            </div>
        </div>

        <div class="form-aksi">
            <button type="submit" class="btn btn-primer">Perbarui Pembayaran</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>pembayaran/detail.php?id=<?= (int) $id ?>">Batal</a>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>