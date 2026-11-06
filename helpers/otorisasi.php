<?php

declare(strict_types=1);

/**
 * File: Otorisasi
 * Fungsi: Pemeriksaan peran (role) - selalu dilakukan di sisi server.
 *
 * Pemilik  : laporan piutang.
 * Manajer  : operasional (pelanggan, piutang, pembayaran, pemantauan, akun pelanggan).
 * Pelanggan: portal piutang pribadi.
 */

function tolakAkses(): void
{
    redirect(BASE_URL . 'error/403.php');
}

function requireManajer(): void
{
    requireLogin();

    if (roleUser() !== 'manajer') {
        tolakAkses();
    }
}

/**
 * Laporan piutang (DFD 6.0).
 * Pelanggan tidak punya akses; DFD Level 1 & 2 menetapkan Pemilik dan
 * Manajer sebagai aktor proses 6.0.
 */
function requireLaporan(): void
{
    requireLogin();

    if (!in_array(roleUser(), ['pemilik', 'manajer'], true)) {
        tolakAkses();
    }
}

function requirePelanggan(): void
{
    requireLogin();

    if (roleUser() !== 'pelanggan' || idPelangganUser() <= 0) {
        tolakAkses();
    }
}
