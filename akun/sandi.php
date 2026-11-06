<?php

declare(strict_types=1);

/**
 * Halaman: Akun Saya - Ganti Kata Sandi Mandiri (DFD 1.4)
 * Fungsi: Pemilik, Manajer, dan Pelanggan dapat mengganti kata sandi
 * sendiri. Validasi kata sandi lama dan hashing baru.
 */

require_once __DIR__ . '/../includes/init.php';
requireLogin();

$judul_halaman = 'Akun Saya';
$sub_judul_halaman = 'Informasi akun dan keamanan';
$halaman_aktif = 'akun';

require_once BASE_PATH . '/includes/kepala.php';

$kesalahan = [];
$lama = '';
$baru = '';
$konfirmasi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama = (string) ($_POST['password_lama'] ?? '');
    $baru = (string) ($_POST['password_baru'] ?? '');
    $konfirmasi = (string) ($_POST['konfirmasi_password'] ?? '');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    if ($lama === '') {
        $kesalahan[] = 'Kata sandi lama wajib diisi.';
    }

    if ($baru !== $konfirmasi) {
        $kesalahan[] = 'Konfirmasi kata sandi baru tidak sama.';
    }

    if ($kesalahan === []) {
        $hasil = PenggunaService::ubahKataSandiSendiri(koneksiDatabase(), (int) userSaatIni()['id_user'], $lama, $baru);
        if ($hasil['ok']) {
            setFlash('success', $hasil['pesan']);
            redirect(BASE_URL . 'akun/sandi.php');
        }
        $kesalahan[] = $hasil['pesan'];
    }
}

$akun = userSaatIni();
?>

<section class="kartu kartu-sempit mb-lg" aria-label="Informasi akun">
    <div class="kartu-judul">
        <h3>Profil</h3>
    </div>
    <div class="form-grid" style="margin:0;">
        <div><span class="teks-kecil">Nama Akun</span><p><?= e((string) $akun['nama']) ?></p></div>
        <div><span class="teks-kecil">Username</span><p><?= e((string) $akun['username']) ?></p></div>
        <div><span class="teks-kecil">Peran</span><p><?= e(labelRole(roleUser())) ?></p></div>
    </div>
</section>

<div class="kotak-form">
    <h2>Ganti Kata Sandi</h2>
    <p class="teks-kecil mb-lg">Sesi tetap berlaku setelah penggantian.</p>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>akun/sandi.php" data-anti-ganda>
        <?= fieldCsrf() ?>
        <div class="form-grid">
            <div class="kolom" style="grid-column:1 / -1;">
                <label for="password_lama">Kata Sandi Lama *</label>
                <input class="input" type="password" id="password_lama" name="password_lama" autocomplete="current-password" required>
            </div>
            <div class="kolom">
                <label for="password_baru">Kata Sandi Baru *</label>
                <input class="input" type="password" id="password_baru" name="password_baru" autocomplete="new-password" required>
                <span class="bantuan">Minimal 8 karakter.</span>
            </div>
            <div class="kolom">
                <label for="konfirmasi_password">Konfirmasi Kata Sandi Baru *</label>
                <input class="input" type="password" id="konfirmasi_password" name="konfirmasi_password" autocomplete="new-password" required>
            </div>
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn btn-primer">Simpan Kata Sandi</button>
        </div>
    </form>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
