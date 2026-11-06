<?php

declare(strict_types=1);

/**
 * Halaman: Hapus Piutang
 * Fungsi: Konfirmasi dan penghapusan piutang tanpa pembayaran (POST + CSRF)
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

$judul_halaman = 'Hapus Piutang';
$sub_judul_halaman = $piutang['no_transaksi'];
$halaman_aktif = 'piutang';

require_once BASE_PATH . '/includes/kepala.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        setFlash('error', 'Sesi keamanan tidak valid. Silakan muat ulang halaman.');
        redirect(BASE_URL . 'piutang/detail.php?id=' . $id);
    }

    $hasil = PiutangService::hapusPiutang($pdo, $id);

    if (!$hasil['ok']) {
        setFlash('warning', $hasil['pesan']);
        redirect(BASE_URL . 'piutang/detail.php?id=' . $id);
    }

    setFlash('success', 'Piutang berhasil dihapus.');
    redirect(BASE_URL . 'piutang/index.php');
}
?>

<div class="kotak-form kotak-hapus">
    <div class="modal-ikon" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
    </div>
    <h2>Hapus Piutang</h2>
    <p style="color:var(--warna-teks-muted);">
        Apakah Anda yakin ingin menghapus piutang <strong><?= e($piutang['no_transaksi']) ?></strong>
        milik <strong><?= e($piutang['nama_pelanggan']) ?></strong> sebesar <?= e(formatRupiah($piutang['total_piutang'])) ?>?
    </p>
    <p class="teks-kecil" style="margin-bottom:var(--spasi-lg);">Piutang ber pembayaran tidak dapat dihapus.</p>

    <form method="post" action="<?= BASE_URL ?>piutang/hapus.php?id=<?= (int) $id ?>" data-anti-ganda>
        <?= fieldCsrf() ?>
        <div class="form-aksi" style="justify-content:center;">
            <a class="btn btn-netral" href="<?= BASE_URL ?>piutang/detail.php?id=<?= (int) $id ?>">Batal</a>
            <button type="submit" class="btn btn-hapus">Hapus Data</button>
        </div>
    </form>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>