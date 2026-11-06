<?php

declare(strict_types=1);

/**
 * File: Konfigurasi Database
 * Fungsi: Koneksi PDO tunggal ke MariaDB/MySQL
 *
 * Sesuaikan nilai berikut dengan lingkungan Anda (default XAMPP).
 */

// Lingkungan ini: MySQL XAMPP berjalan di port 3307 (lihat /opt/lampp/etc/my.cnf).
// 127.0.0.1 memaksa TCP; 'localhost' jatuh ke unix socket yang tidak cocok.
const DB_HOST = '127.0.0.1';
const DB_PORT = '3307';
const DB_NAMA = 'piutang_pelanggan';
const DB_USER = 'root';
const DB_PASSWORD = '';

function koneksiDatabase(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAMA . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        if (function_exists('catatKesalahan')) {
            catatKesalahan($e);
        }
        http_response_code(500);
        require BASE_PATH . '/error/500.php';
        exit;
    }

    return $pdo;
}
