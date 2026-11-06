<?php

declare(strict_types=1);

/**
 * Halaman: Keluar (Logout)
 * Fungsi: Menghancurkan sesi dengan aman (hanya lewat POST + CSRF) lalu
 * mengarahkan ke halaman masuk sesuai peran pengguna.
 */

require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cekTokenCsrf($_POST['token_csrf'] ?? null)) {
    redirect(halamanLoginPeran());
}

// Peran diambil sebelum sesi dihapus agar tujuan pengalihan tetap tepat.
$peran = roleUser();

hancurkanSesi();

redirect(halamanLoginPeran($peran));
