<?php

declare(strict_types=1);

/**
 * Halaman: Akses Ditolak (403)
 * Fungsi: Ditampilkan saat pengguna tidak memiliki izin
 */

require_once __DIR__ . '/../includes/init.php';

http_response_code(403);

$tujuan = sudahLogin() ? urlBerandaSesuaiPeran() : halamanLoginPeran();
$labelTujuan = sudahLogin() ? 'Kembali ke Beranda' : 'Kembali ke Halaman Masuk';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Ditolak - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/variables.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/responsive.css">
</head>
<body>
    <main class="keadaan-kosong" style="max-width:520px;margin:10vh auto;border:none;">
        <h1 style="font-size:64px;letter-spacing:-0.03em;color:var(--warna-primer);">403</h1>
        <h2>Akses Ditolak</h2>
        <p>Akses ditolak.</p>
        <a class="btn btn-primer" href="<?= e($tujuan) ?>"><?= e($labelTujuan) ?></a>
    </main>
</body>
</html>