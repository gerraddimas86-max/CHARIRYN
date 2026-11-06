<?php

declare(strict_types=1);

/**
 * File: Autentikasi
 * Fungsi: Status login, sesi, pengalihan (redirect)
 */

define('BATAS_WAKTU_SESI', 7200); // 2 jam tanpa aktivitas

function sudahLogin(): bool
{
    return isset($_SESSION['user']['id_user']);
}

function userSaatIni(): array
{
    return $_SESSION['user'] ?? [];
}

function roleUser(): string
{
    return (string) ($_SESSION['user']['role'] ?? '');
}

function idPelangganUser(): int
{
    return (int) ($_SESSION['user']['id_pelanggan'] ?? 0);
}

function labelRole(string $role): string
{
    return match ($role) {
        'pemilik' => 'Pemilik',
        'manajer' => 'Manajer',
        'pelanggan' => 'Pelanggan',
        default => 'Pengguna',
    };
}

function urlBerandaSesuaiPeran(): string
{
    return match (roleUser()) {
        'pemilik' => BASE_URL . 'laporan/piutang.php',
        'pelanggan' => BASE_URL . 'portal/index.php',
        default => BASE_URL . 'dashboard/index.php',
    };
}

/**
 * Halaman masuk untuk sebuah peran.
 *
 * DFD ini menyediakan dua halaman, satu untuk Pelanggan dan satu untuk
 * Pemilik/Manajer. Ketiganya tetap memakai proses 1.0 (Autentikasi &
 * Sesi) yang sama - hanya antarmuka masuknya yang berbeda.
 */
function halamanLoginPeran(?string $role = null): string
{
    $role = $role ?? roleUser();

    return BASE_URL . ($role === 'pelanggan' ? 'auth/login-pelanggan.php' : 'auth/login.php');
}

/**
 * Memproses permintaan login (proses 1.1-1.3).
 *
 * @param array $data           Data POST: username, password, token_csrf
 * @param array $peranDiizinkan Peran yang boleh masuk lewat halaman ini
 *
 * @return array<int,string> Pesan kesalahan (kosong bila berhasil; bila
 *                         berhasil fungsi ini mengarahkan ke beranda peran).
 */
function prosesLoginPengguna(array $data, array $peranDiizinkan): array
{
    $username = bersihkan($data['username'] ?? '');
    $kataSandi = (string) ($data['password'] ?? '');

    if (!cekTokenCsrf($data['token_csrf'] ?? null)) {
        return ['Sesi keamanan tidak valid. Silakan muat ulang halaman.'];
    }

    if ($username === '' || $kataSandi === '') {
        return ['Username dan password wajib diisi.'];
    }

    // Proses 1.2: verifikasi kredensial, akun, dan statusnya.
    $user = PenggunaService::autentikasi(koneksiDatabase(), $username, $kataSandi);

    if ($user === null) {
        // Pesan sengaja sama untuk kredensial salah maupun akun belum aktif
        // agar tidak membocorkan keberadaan sebuah username.
        return ['Username atau password salah.'];
    }

    $role = (string) $user['role'];

    if (!in_array($role, $peranDiizinkan, true)) {
        return ['Akun ini tidak dapat masuk dari halaman ini. ' . (in_array($role, ['pemilik', 'manajer'], true)
            ? 'Gunakan halaman masuk untuk Manajer dan Pemilik.'
            : 'Gunakan halaman masuk Pelanggan.')];
    }

    // Proses 1.3: tentukan peran dan buat sesi.
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id_user' => (int) $user['id_user'],
        'username' => (string) $user['username'],
        'nama' => (string) $user['nama'],
        'role' => $role,
        'id_pelanggan' => $user['id_pelanggan'] !== null ? (int) $user['id_pelanggan'] : null,
    ];
    $_SESSION['waktu_terakhir'] = time();

    redirect(urlBerandaSesuaiPeran());

    return [];
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function hancurkanSesi(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameter = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parameter['path'],
            $parameter['domain'],
            (bool) $parameter['secure'],
            (bool) $parameter['httponly']
        );
    }

    session_destroy();
}

function cekLogin(): void
{
    if (!sudahLogin()) {
        redirect(halamanLoginPeran());
    }

    // Peran disimpan lebih dulu agar pengalihan tetap menuju halaman
    // masuk yang sesuai bila sesi berakhir.
    $peranAwal = roleUser();

    // Proteksi sesi kedaluwarsa
    if (isset($_SESSION['waktu_terakhir'])) {
        $selisih = time() - (int) $_SESSION['waktu_terakhir'];
        if ($selisih > BATAS_WAKTU_SESI) {
            hancurkanSesi();
            setFlash('warning', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
            redirect(halamanLoginPeran($peranAwal));
        }
    }

    // Sumber kebenaran peran/status adalah database, bukan isi sesi lama.
    $stmt = koneksiDatabase()->prepare(
        'SELECT id_user, username, nama, role, status, id_pelanggan
         FROM users WHERE id_user = :id LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['user']['id_user']]);
    $akun = $stmt->fetch();

    $akunValid = $akun !== false
        && $akun['status'] === 'aktif'
        && ($akun['role'] !== 'pelanggan' || (int) $akun['id_pelanggan'] > 0);

    if (!$akunValid) {
        hancurkanSesi();
        setFlash('warning', 'Akun Anda tidak aktif atau tidak valid. Silakan hubungi manajer.');
        redirect(halamanLoginPeran($peranAwal));
    }

    $_SESSION['user'] = [
        'id_user' => (int) $akun['id_user'],
        'username' => (string) $akun['username'],
        'nama' => (string) $akun['nama'],
        'role' => (string) $akun['role'],
        'id_pelanggan' => $akun['id_pelanggan'] !== null ? (int) $akun['id_pelanggan'] : null,
    ];
    $_SESSION['waktu_terakhir'] = time();
}

function requireLogin(): void
{
    cekLogin();
}