<?php

declare(strict_types=1);

/**
 * Halaman: Edit Piutang
 * Fungsi: Memperbarui data piutang (jumlah dibayar dan status tetap otomatis)
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$piutang = PiutangService::ambil($pdo, $id);

if ($piutang === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Edit Piutang';
$sub_judul_halaman = $piutang['no_transaksi'];
$halaman_aktif = 'piutang';

require_once BASE_PATH . '/includes/kepala.php';

$daftarPelanggan = PelangganService::ambilOpsi($pdo);
$kesalahan = [];

$idPelanggan = (int) $piutang['id_pelanggan'];
$noTransaksi = (string) $piutang['no_transaksi'];
$tanggalTransaksi = (string) $piutang['tanggal_transaksi'];
$jatuhTempo = (string) $piutang['jatuh_tempo'];
$total = (string) $piutang['total_piutang'];
$keterangan = (string) ($piutang['keterangan'] ?? '');

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

    if ($kesalahan === [] && !PiutangService::noTransaksiTersedia($pdo, $noTransaksi, $id)) {
        $kesalahan[] = 'Nomor transaksi sudah digunakan.';
    }

    $sudahDibayar = (float) $piutang['jumlah_dibayar'];
    if ($kesalahan === [] && $nilaiTotal < $sudahDibayar) {
        $kesalahan[] = 'Total piutang tidak boleh kurang dari jumlah yang sudah dibayar (' . formatRupiah($sudahDibayar) . ').';
    }

    if ($kesalahan === []) {
        $hasil = PiutangService::ubahPiutang($pdo, $id, [
            'id_pelanggan' => $idPelanggan,
            'no_transaksi' => $noTransaksi,
            'tanggal_transaksi' => $tanggalTransaksi,
            'total_piutang' => $nilaiTotal,
            'jatuh_tempo' => $jatuhTempo,
            'keterangan' => $keterangan,
        ]);

        if ($hasil['ok']) {
            setFlash('success', $hasil['pesan']);
            redirect(BASE_URL . 'piutang/detail.php?id=' . $id);
        }

        $kesalahan[] = $hasil['pesan'];
    }

    $total = (string) $nilaiTotal;
}
?>

<div class="kotak-form">
    <div class="aksi-halaman" style="margin-bottom:var(--spasi-md);">
        <a class="btn btn-netral btn-kecil" href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $id ?>">Kembali</a>
    </div>
    <h2>Formulir Edit Piutang</h2>
    <p class="teks-kecil mb-lg">Dihitung otomatis dari riwayat pembayaran.</p>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>piutang/edit.php?id=<?= (int) $id ?>" data-anti-ganda>
        <?= fieldCsrf() ?>
        <div class="form-grid">
            <div class="kolom">
                <label for="id_pelanggan">Pelanggan *</label>
                <select class="pilih" id="id_pelanggan" name="id_pelanggan" required>
                    <?php foreach ($daftarPelanggan as $p): ?>
                        <option value="<?= (int) $p['id_pelanggan'] ?>" <?= $idPelanggan === (int) $p['id_pelanggan'] ? 'selected' : '' ?><?= ($p['status'] ?? 'aktif') !== 'aktif' ? ' disabled' : '' ?>><?= e($p['nama_pelanggan']) ?> (<?= e($p['kode_pelanggan']) ?>)<?= ($p['status'] ?? 'aktif') !== 'aktif' ? ' - nonaktif' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="kolom">
                <label for="no_transaksi">Nomor Transaksi *</label>
                <input class="input" type="text" id="no_transaksi" name="no_transaksi" value="<?= e($noTransaksi) ?>" required>
            </div>
            <div class="kolom">
                <label for="tanggal_transaksi">Tanggal Transaksi *</label>
                <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="tanggal_transaksi" name="tanggal_transaksi" value="<?= e(tampilTanggalID($tanggalTransaksi)) ?>" required>
            </div>
            <div class="kolom">
                <label for="jatuh_tempo">Jatuh Tempo *</label>
                <input class="input" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" maxlength="10" data-tanggal-id id="jatuh_tempo" name="jatuh_tempo" value="<?= e(tampilTanggalID($jatuhTempo)) ?>" required>
            </div>
            <div class="kolom">
                <label for="total_piutang">Total Piutang (Rp) *</label>
                <input class="input" type="text" inputmode="numeric" id="total_piutang" name="total_piutang" value="<?= e($total) ?>" data-rupiah required>
                <span class="bantuan">Sudah dibayar: <?= e(formatRupiah($piutang['jumlah_dibayar'])) ?> - total tidak boleh kurang dari nilai tersebut.</span>
            </div>
            <div class="kolom" style="grid-column:1 / -1;">
                <label for="keterangan">Keterangan</label>
                <textarea class="textarea" id="keterangan" name="keterangan"><?= e($keterangan) ?></textarea>
            </div>
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn btn-primer">Perbarui Data</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $id ?>">Batal</a>
        </div>
    </form>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>