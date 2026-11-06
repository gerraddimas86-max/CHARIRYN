<?php

declare(strict_types=1);

/**
 * Halaman: Masuk - Pemilik & Manajer (proses 1.1-1.3)
 * Fungsi: Autentikasi untuk aktor Pemilik dan Manajer.
 *
 * Pelanggan memakai halaman terpisah (auth/login-pelanggan.php).
 * Keduanya memakai proses 1.0 (Autentikasi & Sesi) yang sama.
 */

require_once __DIR__ . '/../includes/init.php';

if (sudahLogin()) {
    redirect(urlBerandaSesuaiPeran());
}

$username = '';
$kesalahan = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = bersihkan($_POST['username'] ?? '');
    $kesalahan = prosesLoginPengguna($_POST, ['pemilik', 'manajer']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - <?= e(APP_NAME) ?></title>
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
    <img class="login-doodle login-doodle-1" src="<?= BASE_URL ?>assets/img/doodles/shapes/doodle-sparkle-yellow-sm.png" alt="" aria-hidden="true" width="68" height="68">
    <img class="login-doodle login-doodle-2" src="<?= BASE_URL ?>assets/img/doodles/hearts/doodle-heart-pink.png" alt="" aria-hidden="true" width="76" height="76">
    <img class="login-doodle login-doodle-3" src="<?= BASE_URL ?>assets/img/doodles/shapes/doodle-sparkle-blue-sm.png" alt="" aria-hidden="true" width="52" height="52">
    <div class="login-tata">
        <section class="login-panel-form" aria-label="Formulir masuk">
            <div class="login-kotak">
                <div class="login-brand">
                    <img src="<?= BASE_URL ?>assets/img/chariryn.png" alt="CHARIRYN" width="440" height="144">
                </div>
                <h2>Masuk</h2>
                <p class="teks-kecil" style="margin-bottom:var(--spasi-md);">Untuk <strong>Pemilik</strong> dan <strong>Manajer</strong>.</p>

                <?php if ($kesalahan !== []): ?>
                    <ul class="pesan-kesalahan" role="alert">
                        <?php foreach ($kesalahan as $pesan): ?>
                            <li><?= e($pesan) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="<?= BASE_URL ?>auth/login.php" class="form-masuk" data-anti-ganda>
                    <?= fieldCsrf() ?>
                    <div class="kolom">
                        <label for="username">Username</label>
                        <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" placeholder="Masukkan username" required autofocus>
                    </div>
                    <div class="kolom">
                        <label for="password">Password</label>
                        <input class="input" type="password" id="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
                    </div>
                    <button type="submit" class="btn btn-primer btn-lebar">Masuk</button>
                </form>
                <p class="login-demo">Akun demo:<br>pemilik / Admin123! &nbsp;•&nbsp; manajer / Manajer123!</p>
                <p class="login-demo"><a href="<?= BASE_URL ?>auth/login-pelanggan.php">Pelanggan? Masuk di sini</a></p>
            </div>
        </section>
    </div>
</body>
</html>
