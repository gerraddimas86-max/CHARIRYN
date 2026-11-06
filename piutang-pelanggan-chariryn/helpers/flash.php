<?php

declare(strict_types=1);

/**
 * File: Flash Message
 * Fungsi: Pesan sekali pakai (success, error, warning, info)
 */

function setFlash(string $jenis, string $pesan): void
{
    $_SESSION['flash'][] = ['jenis' => $jenis, 'pesan' => $pesan];
}

function ambilFlash(): array
{
    $pesan = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $pesan;
}