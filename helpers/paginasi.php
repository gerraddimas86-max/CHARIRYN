<?php

declare(strict_types=1);

/**
 * File: Paginasi
 * Fungsi: Membuat tautan navigasi halaman (Sebelumnya / nomor / Berikutnya)
 */

function paginasiHtml(int $halaman, int $totalHalaman, array $parameter = []): string
{
    $halaman = max(1, min($halaman, max(1, $totalHalaman)));
    if ($totalHalaman <= 1) {
        return '';
    }

    unset($parameter['halaman']);
    $parameter = array_filter($parameter, static fn ($nilai): bool => $nilai !== '' && $nilai !== null);

    $buatUrl = static function (int $hal) use ($parameter): string {
        $parameter['halaman'] = $hal;
        return $_SERVER['PHP_SELF'] . '?' . http_build_query($parameter);
    };

    $nomor = [];
    for ($i = 1; $i <= $totalHalaman; $i++) {
        if ($i === 1 || $i === $totalHalaman || abs($i - $halaman) <= 2) {
            $nomor[] = $i;
        }
    }

    $html = '<nav class="paginasi" aria-label="Navigasi halaman">';
    $html .= '<a class="paginasi-tombol' . ($halaman <= 1 ? ' nonaktif' : '') . '" href="'
        . e($buatUrl($halaman - 1)) . '">Sebelumnya</a>';

    $sebelumnya = 0;
    foreach ($nomor as $n) {
        if ($n - $sebelumnya > 1) {
            $html .= '<span class="paginasi-titik">…</span>';
        }
        $kelas = $n === $halaman ? 'paginasi-angka aktif' : 'paginasi-angka';
        $html .= '<a class="' . $kelas . '" href="' . e($buatUrl($n)) . '">' . $n . '</a>';
        $sebelumnya = $n;
    }

    $html .= '<a class="paginasi-tombol' . ($halaman >= $totalHalaman ? ' nonaktif' : '') . '" href="'
        . e($buatUrl($halaman + 1)) . '">Berikutnya</a>';
    $html .= '</nav>';

    return $html;
}