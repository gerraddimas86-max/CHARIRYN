<?php

declare(strict_types=1);

/**
 * Halaman: Tidak Ditemukan (404)
 */

require_once __DIR__ . '/../includes/init.php';

http_response_code(404);

$tujuan = sudahLogin() ? BASE_URL . 'dashboard/index.php' : halamanLoginPeran();
$labelTujuan = sudahLogin() ? 'Kembali ke Dashboard' : 'Kembali ke Halaman Masuk';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Halaman Tidak Ditemukan - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/variables.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/responsive.css">
</head>
<body>
    <main class="keadaan-kosong" style="max-width:520px;margin:10vh auto;border:none;">
        <h1 style="font-size:64px;letter-spacing:-0.03em;color:var(--warna-primer);">404</h1>
        <h2>Halaman Tidak Ditemukan</h2>
        <p>Halaman tidak ditemukan.</p>
        <a class="btn btn-primer" href="<?= e($tujuan) ?>"><?= e($labelTujuan) ?></a>
    </main>
</body>
</html>