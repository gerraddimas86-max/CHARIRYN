<?php

declare(strict_types=1);

/**
 * Bagian kepala layout: membuka dokumen HTML dan memuat sidebar + header
 */
$judul_penuh = ($judul_halaman !== '' ? $judul_halaman . ' - ' : '') . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(APP_NAME) ?>">
    <title><?= e($judul_penuh) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/variables.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/layout.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/forms.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tables.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/responsive.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/print.css?v=8">
</head>
<body class="<?= e($kelas_body) ?>">
<div class="tata-letak" id="tataLetak">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>
    <div class="tata-letak-utama">
        <?php require_once BASE_PATH . '/includes/header.php'; ?>
        <main class="konten" id="kontenUtama">
            <?php require_once BASE_PATH . '/includes/pesan.php'; ?>