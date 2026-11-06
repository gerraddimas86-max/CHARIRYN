<?php

declare(strict_types=1);

/**
 * Halaman: Batalkan Pembayaran (DFD 4.2)
 * Fungsi: Manajer membatalkan pembayaran resmi dengan alasan. Catatan
 * pembayaran tetap tersimpan sebagai riwayat, tetapi tidak lagi
 * mengurangi saldo piutang.
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

$judul_halaman = 'Batalkan Pembayaran';
$sub_judul_halaman = (string) $pembayaran['no_transaksi'];
$halaman_aktif = 'pembayaran';

require_once BASE_PATH . '/includes/kepala.php';

$kesalahan = [];
$alasan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alasan = bersihkan($_POST['alasan_pembatalan'] ?? '');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    if ($alasan === '') {
        $kesalahan[] = 'Alasan pembatalan wajib diisi.';
    }

    if ($kesalahan === []) {
        $hasil = PembayaranService::batalkanPembayaran($pdo, $id, $alasan);
        if ($hasil['ok']) {
            setFlash('success', $hasil['pesan']);
            redirect(BASE_URL . 'piutang/detail.php?id=' . (int) $pembayaran['id_piutang']);
        }
        $kesalahan[] = $hasil['pesan'];
    }
}

$sudahDibatalkan = (string) ($pembayaran['status'] ?? 'berlaku') !== 'berlaku';
?>

<div class="kotak-form">
    <div class="aksi-halaman" style="margin-bottom:var(--spasi-md);">
        <a class="btn btn-netral btn-kecil" href="<?= BASE_URL ?>pembayaran/detail.php?id=<?= (int) $id ?>">Kembali</a>
    </div>
    <h2>Batalkan Pembayaran</h2>
    <p class="teks-kecil mb-lg">
        Pembayaran atas <strong><?= e((string) $pembayaran['no_transaksi']) ?></strong>
        (<?= e((string) $pembayaran['nama_pelanggan']) ?>) sebesar
        <strong><?= e(formatRupiah($pembayaran['jumlah_bayar'])) ?></strong>.
    </p>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($sudahDibatalkan): ?>
        <div class="alert alert-warning" role="alert">
            <div class="alert-ikon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <p>Pembayaran ini sudah dibatalkan pada <?= e(formatTanggalWaktuIndonesia((string) ($pembayaran['dibatalkan_at'] ?? ''))) ?>.</p>
        </div>
        <div class="form-aksi">
            <a class="btn btn-netral" href="<?= BASE_URL ?>pembayaran/detail.php?id=<?= (int) $id ?>">Kembali ke Detail</a>
        </div>
    <?php else: ?>
        <div class="alert alert-warning" role="alert">
            <div class="alert-ikon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <p>
                Pembayaran <strong>tidak dihapus</strong>, melainkan ditandai dibatalkan.
                Catatan dan riwayatnya tetap terlihat, tetapi tidak lagi mengurangi sisa piutang.
            </p>
        </div>

        <form method="post" action="<?= BASE_URL ?>pembayaran/batal.php?id=<?= (int) $id ?>" data-anti-ganda>
            <?= fieldCsrf() ?>
            <div class="kolom">
                <label for="alasan_pembatalan">Alasan Pembatalan *</label>
                <textarea class="textarea" id="alasan_pembatalan" name="alasan_pembatalan" placeholder="Contoh: salah input nominal, transfer belum masuk." required><?= e($alasan) ?></textarea>
                <span class="bantuan">Wajib diisi</span>
            </div>
            <div class="form-aksi">
                <button type="submit" class="btn btn-hapus">Batalkan Pembayaran</button>
                <a class="btn btn-netral" href="<?= BASE_URL ?>pembayaran/detail.php?id=<?= (int) $id ?>">Batal</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
