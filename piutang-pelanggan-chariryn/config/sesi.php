<?php

declare(strict_types=1);

/**
 * File: Konfigurasi Sesi
 * Fungsi: Inisialisasi sesi dengan pengaturan keamanan
 */

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('SIPIUTANG_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
