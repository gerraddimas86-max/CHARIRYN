<?php

declare(strict_types=1);

/**
 * File: Format
 * Fungsi: Pemformatan Rupiah, angka, dan escaping keluaran
 */

function e(?string $nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

function formatRupiah($nilai): string
{
    $nilai = round((float) $nilai, 2);
    $desimal = abs($nilai - round($nilai)) < 0.005 ? 0 : 2;

    return 'Rp ' . number_format($nilai, $desimal, ',', '.');
}

function formatAngka($nilai): string
{
    return number_format((float) $nilai, 0, ',', '.');
}