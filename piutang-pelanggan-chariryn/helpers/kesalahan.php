<?php

declare(strict_types=1);

/**
 * File: Penanganan Kesalahan
 * Fungsi: Mencatat kesalahan ke file log tanpa membocorkan detail ke pengguna
 */

function catatKesalahan(Throwable $kesalahan): void
{
    $baris = '[' . date('Y-m-d H:i:s') . '] '
        . $kesalahan->getMessage()
        . ' di ' . $kesalahan->getFile()
        . ':' . $kesalahan->getLine()
        . PHP_EOL;

    @file_put_contents(BASE_PATH . '/logs/error.log', $baris, FILE_APPEND | LOCK_EX);
}