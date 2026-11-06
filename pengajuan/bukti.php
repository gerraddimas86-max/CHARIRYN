<?php

declare(strict_types=1);

/**
 * Endpoint: Bukti Pembayaran (DFD 4.4 / 8.5)
 * Fungsi: Menampilkan berkas bukti pembayaran yang terlampir pada
 * pengajuan. Berkas tidak dapat diakses publik hanya dengan knowing
 * URL: akses hanya diberikan kepada Manajer atau pelanggan pemilik
 * pengajuan tersebut.
 */

require_once __DIR__ . '/../includes/init.php';
requireLogin();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$pengajuan = PengajuanService::ambil($pdo, $id);

if ($pengajuan === null) {
    redirect(BASE_URL . 'error/404.php');
}

// Manajer boleh melihat semua bukti; pelanggan hanya miliknya sendiri.
if (roleUser() === 'pelanggan') {
    if (idPelangganUser() !== (int) $pengajuan['id_pelanggan']) {
        http_response_code(403);
        require BASE_PATH . '/error/403.php';
        exit;
    }
} elseif (roleUser() !== 'manajer' && roleUser() !== 'pemilik') {
    http_response_code(403);
    require BASE_PATH . '/error/403.php';
    exit;
}

$namaBerkas = (string) ($pengajuan['bukti_pembayaran'] ?? '');
if ($namaBerkas === '' || preg_match('/^[a-f0-9]{32}\.(jpg|png|pdf)$/', $namaBerkas) !== 1) {
    http_response_code(404);
    require BASE_PATH . '/error/404.php';
    exit;
}

$jalur = STORAGE_BUKTI_PATH . '/' . $namaBerkas;
if (!is_file($jalur)) {
    http_response_code(404);
    require BASE_PATH . '/error/404.php';
    exit;
}

$tipe = match (pathinfo($jalur, PATHINFO_EXTENSION)) {
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    default => 'application/pdf',
};

header('Content-Type: ' . $tipe);
header('Content-Length: ' . (string) filesize($jalur));
header('Content-Disposition: inline; filename="bukti-pembayaran-' . (int) $pengajuan['id_pengajuan'] . '.' . pathinfo($jalur, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

// Buffer output dibuang agar panjang body sama persis dengan Content-Length.
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($jalur);
exit;
