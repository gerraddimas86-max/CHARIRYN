<?php

declare(strict_types=1);

/**
 * Halaman: Beranda / Pilih Peran
 * Fungsi: DFD Level 0 memuat tiga aktor (Pemilik, Manajer, Pelanggan)
 * yang masing-masing memiliki alur "Kredensial login" ke proses 1.0.
 * Halaman ini meminta pengguna memilih jalur masuknya.
 */

require_once __DIR__ . '/includes/init.php';

if (sudahLogin()) {
    redirect(urlBerandaSesuaiPeran());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/variables.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/forms.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css">
    <style>
        .halaman-pilih { background: #F4F6F1 url('<?= BASE_URL ?>assets/img/login-bg.jpg') center / cover no-repeat; }
        .pilih-kotak { max-width: 720px; margin: 8vh auto; padding: var(--spasi-xl); background: rgba(255,255,255,.95); border: 1px solid var(--warna-divider); border-radius: var(--radius-lg); box-shadow: var(--bayangan-menu); text-align: center; }
        .pilih-kotak h1 { font-size: 26px; margin-bottom: var(--spasi-sm); }
        .pilih-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spasi-md); margin-top: var(--spasi-lg); }
        .pilih-kartu { display: block; padding: var(--spasi-lg); border: 1px solid var(--warna-divider); border-radius: var(--radius-md); background: #fff; text-decoration: none; color: inherit; transition: var(--transisi-normal); }
        .pilih-kartu:hover { border-color: var(--warna-primer); background: var(--chariryn-moss-soft); text-decoration: none; }
        .pilih-kartu strong { display: block; font-size: 18px; margin-bottom: 4px; color: var(--warna-primer); }
        .pilih-kartu span { font-size: 13.5px; color: var(--warna-teks-muted); line-height: 1.5; }
        @media (max-width: 560px) { .pilih-grid { grid-template-columns: 1fr; } .pilih-kotak { margin: 4vh 12px; padding: var(--spasi-lg); } }
    </style>
</head>
<body class="halaman-pilih">
    <main class="pilih-kotak">
        <img src="<?= BASE_URL ?>assets/img/chariryn.png" alt="CHARIRYN" width="360" height="120" style="max-width:100%;height:auto;">
        <h1>Sistem Informasi Pengelolaan Data Piutang Pelanggan</h1>
        <p class="teks-kecil">Pilih jenis akun Anda.</p>

        <div class="pilih-grid">
            <a class="pilih-kartu" href="<?= BASE_URL ?>auth/login-pelanggan.php">
                <strong>Masuk Pelanggan</strong>
                <span>Lihat tagihan, saldo piutang, dan ajukan pembayaran.</span>
            </a>
            <a class="pilih-kartu" href="<?= BASE_URL ?>auth/login.php">
                <strong>Masuk Manajer / Pemilik</strong>
                <span>Kelola pelanggan, piutang, pembayaran, dan laporan.</span>
            </a>
        </div>
    </main>
</body>
</html>
