<?php

declare(strict_types=1);

/**
 * File: Validasi
 * Fungsi: Validasi input sisi server + mekanisme CSRF
 */

function bersihkan(mixed $nilai): string
{
    if (is_array($nilai)) {
        return '';
    }

    return trim((string) $nilai);
}

function escapeLike(string $nilai): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $nilai);
}

function validasiWajib(?string $nilai, string $label, array &$kesalahan): void
{
    if (bersihkan($nilai) === '') {
        $kesalahan[] = $label . ' wajib diisi.';
    }
}

function validasiEmail(?string $nilai, string $label, array &$kesalahan): void
{
    $nilai = bersihkan($nilai);

    if ($nilai !== '' && filter_var($nilai, FILTER_VALIDATE_EMAIL) === false) {
        $kesalahan[] = 'Format ' . strtolower($label) . ' tidak valid.';
    }
}

/**
 * Mengubah masukan angka Rupiah (mis. 1.500.000 atau 1500000,50) menjadi float.
 */
function parseAngkaRupiah(?string $nilai): float
{
    $teks = str_replace('.', '', bersihkan($nilai));
    $teks = str_replace(',', '.', $teks);
    return (float) $teks;
}

function validasiAngkaPositif(float $nilai, string $label, array &$kesalahan): void
{
    if ($nilai <= 0) {
        $kesalahan[] = $label . ' harus lebih besar dari 0.';
    }
}

function validasiTanggal(?string $nilai, string $label, array &$kesalahan): void
{
    $asli = bersihkan($nilai);

    if ($asli === '') {
        $kesalahan[] = $label . ' wajib diisi.';
        return;
    }

    $tanggal = parseTanggalID($asli);
    if ($tanggal === null) {
        $kesalahan[] = 'Format ' . strtolower($label) . ' tidak valid (pakai hh/bb/tttt).';
    }
}

function buatTokenCsrf(): string
{
    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['token_csrf'];
}

function cekTokenCsrf(?string $token): bool
{
    return isset($_SESSION['token_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['token_csrf'], $token);
}

function fieldCsrf(): string
{
    return '<input type="hidden" name="token_csrf" value="' . e(buatTokenCsrf()) . '">';
}

function cekMetodePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect(BASE_URL . 'error/404.php');
    }
}

function ambilIdDariGet(): ?int
{
    $id = $_GET['id'] ?? null;

    if (!is_scalar($id) || !ctype_digit((string) $id)) {
        return null;
    }

    return (int) $id;
}

/**
 * Validasi berkas unggahan bukti pembayaran (DFD 8.5).
 * Mengembalikan ['ok' => bool, 'pesan' => string, 'nama' => string].
 * Nama berkas disimpan acak supaya URL publik tidak dapat ditebak.
 */
function validasiUnggahanBukti(?array $berkas, int $maksBytes = 2097152): array
{
    if ($berkas === null || (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'pesan' => 'Bukti pembayaran wajib diunggah.', 'nama' => null];
    }

    $galat = (int) ($berkas['error'] ?? UPLOAD_ERR_OK);
    if ($galat !== UPLOAD_ERR_OK) {
        $pesan = match ($galat) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran berkas melebihi batas.',
            UPLOAD_ERR_PARTIAL => 'Berkas hanya terunggah sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Berkas gagal ditulis ke server.',
            default => 'Berkas gagal diunggah.',
        };

        return ['ok' => false, 'pesan' => $pesan, 'nama' => null];
    }

    $ukuran = (int) ($berkas['size'] ?? 0);
    if ($ukuran <= 0) {
        return ['ok' => false, 'pesan' => 'Berkas kosong.', 'nama' => null];
    }

    if ($ukuran > $maksBytes) {
        return ['ok' => false, 'pesan' => 'Ukuran berkas maksimal 2 MB.', 'nama' => null];
    }

    // Tipe dicek dari isi berkas (finfo), bukan dari ekstensi/header kiriman.
    $tipe = '';
    $jalur = (string) ($berkas['tmp_name'] ?? '');
    if ($jalur !== '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $tipe = (string) finfo_file($finfo, $jalur);
            finfo_close($finfo);
        }
    }

    $tipeDiizinkan = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    if (!isset($tipeDiizinkan[$tipe])) {
        return ['ok' => false, 'pesan' => 'Format berkas harus JPG, PNG, atau PDF.', 'nama' => null];
    }

    $namaAcar = bin2hex(random_bytes(16)) . '.' . $tipeDiizinkan[$tipe];

    return ['ok' => true, 'pesan' => '', 'nama' => $namaAcar];
}

/**
 * Menyimpan berkas bukti pembayaran ke folder penyimpanan privat.
 * Folder ini tidak dapat diakses lewat HTTP; berkas hanya dibuka
 * lewat endpoint yang memeriksa hak akses.
 */
function simpanBerkasBukti(string $namaAcak): array
{
    $berkas = $_FILES['bukti_pembayaran'] ?? null;

    if ($berkas === null || !is_uploaded_file((string) ($berkas['tmp_name'] ?? ''))) {
        return ['ok' => false, 'pesan' => 'Berkas bukti pembayaran tidak terkirim.'];
    }

    if (!is_dir(STORAGE_BUKTI_PATH) && !@mkdir(STORAGE_BUKTI_PATH, 0775, true) && !is_dir(STORAGE_BUKTI_PATH)) {
        return ['ok' => false, 'pesan' => 'Folder penyimpanan bukti pembayaran tidak tersedia.'];
    }

    if (!is_writable(STORAGE_BUKTI_PATH)) {
        return ['ok' => false, 'pesan' => 'Folder penyimpanan bukti pembayaran tidak dapat ditulis.'];
    }

    $tujuan = STORAGE_BUKTI_PATH . '/' . $namaAcak;

    if (!move_uploaded_file((string) $berkas['tmp_name'], $tujuan)) {
        return ['ok' => false, 'pesan' => 'Berkas bukti pembayaran gagal disimpan.'];
    }

    @chmod($tujuan, 0644);

    return ['ok' => true, 'pesan' => ''];
}

/**
 * Menghapus berkas bukti pembayaran dari penyimpanan.
 */
function hapusBerkasBukti(?string $namaAcak): void
{
    if ($namaAcak === null || $namaAcak === '') {
        return;
    }

    // Hanya nama acak milik sistem yang boleh dihapus (cegah path traversal).
    if (preg_match('/^[a-f0-9]{32}\.(jpg|png|pdf)$/', $namaAcak) !== 1) {
        return;
    }

    $jalur = STORAGE_BUKTI_PATH . '/' . $namaAcak;
    if (is_file($jalur)) {
        @unlink($jalur);
    }
}