<?php

declare(strict_types=1);

/**
 * File: Konfigurasi Aplikasi
 * Fungsi: Konstanta aplikasi, base URL, dan zona waktu
 */

define('APP_NAME', 'Sistem Informasi Pengelolaan Data Piutang Pelanggan');
define('APP_SINGKAT', 'SIPiutang');
define('BASE_PATH', dirname(__DIR__));

// Penyimpanan berkas bukti pembayaran. Folder ini tidak dapat diakses
// lewat HTTP (storage/.htaccess); berkas hanya dibuka lewat endpoint
// yang memeriksa hak akses.
define('STORAGE_PATH', BASE_PATH . '/storage');
define('STORAGE_BUKTI_PATH', STORAGE_PATH . '/bukti');

date_default_timezone_set('Asia/Jakarta');

// Deteksi otomatis base URL (contoh: http://localhost/piutang-pelanggan/)
if (PHP_SAPI === 'cli' || empty($_SERVER['DOCUMENT_ROOT'])) {
    define('BASE_URL', 'http://localhost/piutang-pelanggan/');
} else {
    $skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dokumen = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
    $jalur = str_replace('\\', '/', BASE_PATH);
    $relatif = $jalur;
    if (str_starts_with($jalur, $dokumen)) {
        $relatif = substr($jalur, strlen($dokumen));
    } else {
        // Fallback: folder aplikasi di luar DOCUMENT_ROOT (misal symlink)
        $skrip = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $penanda = '/' . basename(BASE_PATH);
        $pos = strpos($skrip, $penanda . '/');
        if ($pos !== false) {
            $relatif = substr($skrip, 0, $pos) . $penanda;
        }
    }
    define('BASE_URL', $skema . '://' . $_SERVER['HTTP_HOST'] . $relatif . '/');
}
