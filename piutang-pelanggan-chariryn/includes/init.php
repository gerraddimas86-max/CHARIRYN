<?php

declare(strict_types=1);

// Buffer output agar setFlash + redirect (PRG) tetap valid walau POST diproses setelah layout dirender
ob_start();

/**
 * Bootstrap aplikasi: memuat konfigurasi, helper, dan service.
 * Sertakan file ini di awal setiap halaman.
 */

require_once __DIR__ . '/../config/aplikasi.php';
require_once BASE_PATH . '/config/sesi.php';
require_once BASE_PATH . '/config/database.php';

require_once BASE_PATH . '/helpers/kesalahan.php';
require_once BASE_PATH . '/helpers/autentikasi.php';
require_once BASE_PATH . '/helpers/otorisasi.php';
require_once BASE_PATH . '/helpers/validasi.php';
require_once BASE_PATH . '/helpers/format.php';
require_once BASE_PATH . '/helpers/tanggal.php';
require_once BASE_PATH . '/helpers/flash.php';
require_once BASE_PATH . '/helpers/piutang.php';
require_once BASE_PATH . '/helpers/paginasi.php';

require_once BASE_PATH . '/services/PelangganService.php';
require_once BASE_PATH . '/services/PiutangService.php';
require_once BASE_PATH . '/services/PembayaranService.php';
require_once BASE_PATH . '/services/PenggunaService.php';
require_once BASE_PATH . '/services/PengajuanService.php';
require_once BASE_PATH . '/services/ProfilService.php';
require_once BASE_PATH . '/services/LaporanService.php';
require_once BASE_PATH . '/services/PortalService.php';

// Setiap error dibuat menjadi exception lalu ditangani dengan aman
set_error_handler(static function (int $tingkat, string $pesan, string $file, int $baris): bool {
    throw new ErrorException($pesan, 0, $tingkat, $file, $baris);
});

set_exception_handler(static function (Throwable $e): void {
    catatKesalahan($e);
    http_response_code(500);
    require BASE_PATH . '/error/500.php';
    exit;
});

// Nilai default yang akan dipakai pada setiap halaman
$judul_halaman = '';
$sub_judul_halaman = '';
$halaman_aktif = '';
$kelas_body = '';

// Sinkronkan status "Jatuh Tempo" yang lewat tanggalnya.
// Hanya untuk pengguna yang sudah masuk: penulisan tidak perlu terjadi
// pada halaman publik.
if (sudahLogin()) {
    perbaruiStatusOtomatis();
}