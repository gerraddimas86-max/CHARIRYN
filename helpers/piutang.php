<?php

declare(strict_types=1);

/**
 * File: Logika Bisnis Piutang
 * Fungsi: Satu-satunya tempat aturan status, sisa, dan validasi pembayaran
 */

function hitungSisaPiutang(float $total, float $dibayar): float
{
    return round(max(0.0, $total - $dibayar), 2);
}

/**
 * Aturan status piutang (satu-satunya lokasi kanonik).
 * - Sisa <= 0                     => Lunas
 * - Sudah ada pembayaran          => Sebagian (atau Jatuh Tempo bila lewat)
 * - Belum ada pembayaran          => Belum Lunas (atau Jatuh Tempo bila lewat)
 */
function tentukanStatusPiutang(float $total, float $dibayar, string $jatuhTempo, ?string $tanggalSekarang = null): string
{
    $sisa = hitungSisaPiutang($total, $dibayar);

    if ($sisa <= 0.005) {
        return 'Lunas';
    }

    $hariIni = $tanggalSekarang ?? date('Y-m-d');
    $terlambat = $hariIni > $jatuhTempo;

    if ($dibayar > 0) {
        return $terlambat ? 'Jatuh Tempo' : 'Sebagian';
    }

    return $terlambat ? 'Jatuh Tempo' : 'Belum Lunas';
}

function hitungTotalPembayaran(PDO $pdo, int $idPiutang): float
{
    // Hanya pembayaran yang masih berlaku (belum dibatalkan) yang
    // mengurangi saldo piutang (DFD 4.3).
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran
         WHERE id_piutang = :id AND status = 'berlaku'"
    );
    $stmt->execute([':id' => $idPiutang]);

    return (float) $stmt->fetchColumn();
}

/**
 * Menghitung ulang jumlah_dibayar, sisa_piutang, dan status sebuah piutang.
 * Dipanggil di dalam transaksi database setiap kali pembayaran berubah.
 */
function perbaruiRingkasanPiutang(PDO $pdo, int $idPiutang): void
{
    $stmt = $pdo->prepare('SELECT total_piutang, jatuh_tempo FROM piutang WHERE id_piutang = :id');
    $stmt->execute([':id' => $idPiutang]);
    $piutang = $stmt->fetch();

    if ($piutang === false) {
        return;
    }

    $dibayar = hitungTotalPembayaran($pdo, $idPiutang);
    $total = (float) $piutang['total_piutang'];
    $sisa = hitungSisaPiutang($total, $dibayar);
    $status = tentukanStatusPiutang($total, $dibayar, (string) $piutang['jatuh_tempo']);

    $update = $pdo->prepare(
        'UPDATE piutang
         SET jumlah_dibayar = :dibayar, sisa_piutang = :sisa, status = :status
         WHERE id_piutang = :id'
    );
    $update->execute([
        ':dibayar' => $dibayar,
        ':sisa' => $sisa,
        ':status' => $status,
        ':id' => $idPiutang,
    ]);
}

/**
 * Menyinkronkan status menjadi "Jatuh Tempo" untuk piutang yang tanggal
 * jatuh temponya sudah lewat. Dijalankan otomatis saat aplikasi diakses.
 */
function perbaruiStatusOtomatis(): void
{
    $pdo = koneksiDatabase();
    $pdo->exec(
        "UPDATE piutang
         SET status = 'Jatuh Tempo'
         WHERE status IN ('Belum Lunas', 'Sebagian')
           AND jatuh_tempo < CURDATE()"
    );
}

/**
 * Validasi pembayaran terhadap sisa piutang.
 * Saat mengubah pembayaran, jumlah lama dikeluarkan sementara (abaikanIdPembayaran).
 */
function validasiPembayaran(PDO $pdo, mixed $idPiutang, mixed $jumlah, ?int $abaikanIdPembayaran = null): array
{
    $id = (is_scalar($idPiutang) && is_numeric((string) $idPiutang)) ? (int) $idPiutang : 0;
    if ($id <= 0) {
        return ['ok' => false, 'pesan' => 'Piutang tidak ditemukan.', 'sisa' => null];
    }
    if (!is_scalar($jumlah) || trim((string) $jumlah) === '' || !is_numeric((string) $jumlah)) {
        return ['ok' => false, 'pesan' => 'Jumlah pembayaran harus lebih dari 0.', 'sisa' => null];
    }
    $stmt = $pdo->prepare(
        'SELECT id_piutang, total_piutang, jumlah_dibayar, sisa_piutang
         FROM piutang WHERE id_piutang = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $piutang = $stmt->fetch();

    if ($piutang === false) {
        return ['ok' => false, 'pesan' => 'Piutang tidak ditemukan.', 'sisa' => null];
    }

    $jumlah = round((float) $jumlah, 2);

    if ($jumlah <= 0) {
        return ['ok' => false, 'pesan' => 'Jumlah pembayaran harus lebih dari 0.', 'sisa' => null];
    }

    $sisaSaatIni = round((float) $piutang['sisa_piutang'], 2);
    $sisaEfektif = $sisaSaatIni;

    if ($abaikanIdPembayaran !== null) {
        $stmt = $pdo->prepare('SELECT jumlah_bayar, status FROM pembayaran WHERE id_pembayaran = :id LIMIT 1');
        $stmt->execute([':id' => $abaikanIdPembayaran]);
        $lama = $stmt->fetch();

        // Pembayaran yang sudah dibatalkan tidak pernah mengurangi saldo,
        // jadi tidak perlu dikembalikan ke perhitungan sisa.
        if ($lama !== false && (string) $lama['status'] === 'berlaku') {
            $sisaEfektif = round($sisaSaatIni + (float) $lama['jumlah_bayar'], 2);
        }
    }

    if ($jumlah - $sisaEfektif > 0.005) {
        return [
            'ok' => false,
            'pesan' => 'Nominal pembayaran melebihi sisa piutang.',
            'sisa' => $sisaEfektif,
        ];
    }

    return ['ok' => true, 'pesan' => '', 'sisa' => $sisaEfektif];
}

function badgeStatus(string $status): string
{
    $kelas = match ($status) {
        'Lunas' => 'badge-lunas',
        'Sebagian' => 'badge-sebagian',
        'Jatuh Tempo' => 'badge-jatuh-tempo',
        default => 'badge-belum-lunas',
    };

    return '<span class="badge ' . $kelas . '">' . e($status) . '</span>';
}

/**
 * Indikator status untuk pengajuan pembayaran (D5) dan permintaan
 * perubahan profil (D6). Keduanya memakai tiga status yang sama.
 */
function badgeStatusVerifikasi(string $status): string
{
    $kelas = match ($status) {
        'disetujui' => 'badge-lunas',
        'ditolak' => 'badge-jatuh-tempo',
        default => 'badge-belum-lunas',
    };

    $label = match ($status) {
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
        default => 'Menunggu',
    };

    return '<span class="badge ' . $kelas . '">' . e($label) . '</span>';
}