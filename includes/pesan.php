<?php

declare(strict_types=1);

/**
 * Menampilkan flash message (success, error, warning, info)
 */
foreach (ambilFlash() as $pesan) {
    $jenis = in_array($pesan['jenis'], ['success', 'error', 'warning', 'info'], true) ? $pesan['jenis'] : 'info';
    echo '<div class="alert alert-' . $jenis . '" role="alert">';
    echo '<div class="alert-ikon" aria-hidden="true">';
    if ($jenis === 'success') {
        echo '<img class="alert-sukses-img" src="' . BASE_URL . 'assets/img/doodles/ui/doodle-check-pink.png" alt="" aria-hidden="true" width="22" height="22">';
    } elseif ($jenis === 'error') {
        echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
    } elseif ($jenis === 'warning') {
        echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
    } else {
        echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
    }
    echo '</div>';
    echo '<p>' . e($pesan['pesan']) . '</p>';
    echo '<button type="button" class="alert-tutup" aria-label="Tutup pesan"><span aria-hidden="true">&times;</span></button>';
    echo '</div>';
}