<?php

declare(strict_types=1);

/**
 * Halaman: Aktivasi Akun Pelanggan (DFD 7.4)
 * Fungsi: Pelanggan memasukkan kode aktivasi dari manajer dan
 * menetapkan kata sandi sendiri. Token bersifat satu kali.
 */

require_once __DIR__ . '/../includes/init.php';

if (sudahLogin()) {
    redirect(urlBerandaSesuaiPeran());
}

$token = '';
$kesalahan = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = bersihkan($_POST['token_aktivasi'] ?? '');
    $baru = (string) ($_POST['password_baru'] ?? '');
    $konfirmasi = (string) ($_POST['konfirmasi_password'] ?? '');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    if ($token === '') {
        $kesalahan[] = 'Kode aktivasi wajib diisi.';
    }

    if ($baru !== $konfirmasi) {
        $kesalahan[] = 'Konfirmasi kata sandi baru tidak sama.';
    }

    if ($kesalahan === []) {
        $hasil = PenggunaService::aktivasi(koneksiDatabase(), $token, $baru);

        if ($hasil['ok']) {
            setFlash('success', $hasil['pesan']);
            redirect(BASE_URL . 'auth/login-pelanggan.php');
        }

        $kesalahan[] = $hasil['pesan'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aktivasi Akun - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/variables.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/forms.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css">
    <style>
        .halaman-login { background: #fff url('<?= BASE_URL ?>assets/img/login-bg.jpg') center / cover no-repeat; }
        .halaman-login::before, .halaman-login::after { display: none; }
        .login-tata { background: rgba(255, 255, 255, 0.92); border-color: var(--warna-divider); box-shadow: var(--bayangan-menu); }
    </style>
</head>
<body class="halaman-login">
    <div class="login-tata">
        <section class="login-panel-form" aria-label="Formulir aktivasi akun">
            <div class="login-kotak">
                <div class="login-brand">
                    <img src="<?= BASE_URL ?>assets/img/chariryn.png" alt="CHARIRYN" width="440" height="144">
                </div>
                <h2>Aktivasi Akun</h2>
                <p class="teks-kecil" style="margin-bottom:var(--spasi-md);">
                    Masukkan kode aktivasi yang diberikan manajer, lalu tentukan kata sandi Anda sendiri.
                </p>

                <?php if ($kesalahan !== []): ?>
                    <ul class="pesan-kesalahan" role="alert">
                        <?php foreach ($kesalahan as $pesan): ?>
                            <li><?= e($pesan) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="<?= BASE_URL ?>auth/aktivasi.php" class="form-masuk" data-anti-ganda>
                    <?= fieldCsrf() ?>
                    <div class="kolom">
                        <label for="token_aktivasi">Kode Aktivasi *</label>
                        <input class="input" type="text" id="token_aktivasi" name="token_aktivasi" value="<?= e($token) ?>" autocomplete="one-time-code" placeholder="Masukkan kode aktivasi" required autofocus>
                        <span class="bantuan">Berlaku 24 jam, sekali pakai</span>
                    </div>
                    <div class="kolom">
                        <label for="password_baru">Kata Sandi Baru *</label>
                        <input class="input" type="password" id="password_baru" name="password_baru" autocomplete="new-password" required>
                        <span class="bantuan">Minimal 8 karakter.</span>
                    </div>
                    <div class="kolom">
                        <label for="konfirmasi_password">Konfirmasi Kata Sandi *</label>
                        <input class="input" type="password" id="konfirmasi_password" name="konfirmasi_password" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primer btn-lebar">Aktifkan Akun</button>
                </form>
                <p class="login-demo"><a href="<?= BASE_URL ?>auth/login-pelanggan.php">Kembali ke halaman masuk pelanggan</a></p>
            </div>
        </section>
    </div>
</body>
</html>
