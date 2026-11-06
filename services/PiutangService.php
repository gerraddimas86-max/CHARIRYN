<?php

declare(strict_types=1);

/**
 * Service: Piutang
 * Fungsi: Operasi data piutang (CRUD, pencarian, ketersediaan nomor transaksi)
 */

class PiutangService
{
    public static function ambilSemua(PDO $pdo, string $cari = '', string $status = '', int $halaman = 1, int $perHalaman = 10): array
    {
        $kondisi = [];
        $parameter = [];

        if ($cari !== '') {
            $kondisi[] = "(p.no_transaksi LIKE :cari1 ESCAPE '\\\\' OR pl.nama_pelanggan LIKE :cari2 ESCAPE '\\\\')";
            $parameter[':cari1'] = $parameter[':cari2'] = '%' . escapeLike($cari) . '%';
        }

        if ($status !== '') {
            $kondisi[] = 'p.status = :status';
            $parameter[':status'] = $status;
        }

        $where = $kondisi !== [] ? 'WHERE ' . implode(' AND ', $kondisi) : '';

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM piutang AS p
             JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
             ' . $where
        );
        $stmt->execute($parameter);
        $total = (int) $stmt->fetchColumn();

        $halaman = max(1, min($halaman, max(1, (int) ceil($total / $perHalaman))));
        $offset = ($halaman - 1) * $perHalaman;
        $sql = 'SELECT
                    p.id_piutang, p.no_transaksi, p.tanggal_transaksi, p.total_piutang,
                    p.jumlah_dibayar, p.sisa_piutang, p.jatuh_tempo, p.status, p.keterangan,
                    pl.id_pelanggan, pl.kode_pelanggan, pl.nama_pelanggan
                FROM piutang AS p
                JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
                ' . $where . '
                ORDER BY p.tanggal_transaksi DESC, p.id_piutang DESC
                LIMIT :batas OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        foreach ($parameter as $kunci => $nilai) {
            $stmt->bindValue($kunci, $nilai);
        }
        $stmt->bindValue(':batas', $perHalaman, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function ambil(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT p.*, pl.kode_pelanggan, pl.nama_pelanggan, pl.no_hp, pl.email, pl.alamat
             FROM piutang AS p
             JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
             WHERE p.id_piutang = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $hasil = $stmt->fetch();

        return $hasil === false ? null : $hasil;
    }

    public static function noTransaksiTersedia(PDO $pdo, string $no, ?int $abaikanId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM piutang WHERE no_transaksi = :no';
        $parameter = [':no' => $no];

        if ($abaikanId !== null) {
            $sql .= ' AND id_piutang <> :id';
            $parameter[':id'] = $abaikanId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameter);

        return (int) $stmt->fetchColumn() === 0;
    }

    public static function buatPiutang(PDO $pdo, array $data): int
    {
        $total = round((float) $data['total_piutang'], 2);
        $status = tentukanStatusPiutang($total, 0.0, (string) $data['jatuh_tempo']);

        $stmt = $pdo->prepare(
            'INSERT INTO piutang
                (id_pelanggan, no_transaksi, tanggal_transaksi, total_piutang,
                 jumlah_dibayar, sisa_piutang, jatuh_tempo, status, keterangan)
             VALUES
                (:id_pelanggan, :no_transaksi, :tanggal_transaksi, :total_piutang,
                 0, :sisa, :jatuh_tempo, :status, :keterangan)'
        );
        $stmt->execute([
            ':id_pelanggan' => $data['id_pelanggan'],
            ':no_transaksi' => $data['no_transaksi'],
            ':tanggal_transaksi' => $data['tanggal_transaksi'],
            ':total_piutang' => $total,
            ':sisa' => $total,
            ':jatuh_tempo' => $data['jatuh_tempo'],
            ':status' => $status,
            ':keterangan' => $data['keterangan'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function ubahPiutang(PDO $pdo, int $id, array $data): array
    {
        $total = round((float) $data['total_piutang'], 2);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT jumlah_dibayar FROM piutang WHERE id_piutang = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $lama = $stmt->fetch();

            if ($lama === false) {
                $pdo->rollBack();
                return ['ok' => false, 'pesan' => 'Piutang tidak ditemukan.'];
            }

            if ($total < (float) $lama['jumlah_dibayar'] - 0.005) {
                $pdo->rollBack();
                return ['ok' => false, 'pesan' => 'Total piutang tidak boleh kurang dari jumlah yang sudah dibayar.'];
            }

            $stmt = $pdo->prepare(
                'UPDATE piutang
                 SET id_pelanggan = :id_pelanggan,
                     no_transaksi = :no_transaksi,
                     tanggal_transaksi = :tanggal_transaksi,
                     total_piutang = :total_piutang,
                     jatuh_tempo = :jatuh_tempo,
                     keterangan = :keterangan
                 WHERE id_piutang = :id'
            );
            $stmt->execute([
                ':id_pelanggan' => $data['id_pelanggan'],
                ':no_transaksi' => $data['no_transaksi'],
                ':tanggal_transaksi' => $data['tanggal_transaksi'],
                ':total_piutang' => $total,
                ':jatuh_tempo' => $data['jatuh_tempo'],
                ':keterangan' => $data['keterangan'] ?? null,
                ':id' => $id,
            ]);

            // Sisa dan status dihitung ulang dari riwayat pembayaran.
            perbaruiRingkasanPiutang($pdo, $id);

            $pdo->commit();

            return ['ok' => true, 'pesan' => 'Piutang berhasil diperbarui.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Terjadi kesalahan. Coba lagi.'];
        }
    }

    public static function punyaPembayaran(PDO $pdo, int $id): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pembayaran WHERE id_piutang = :id');
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function hapusPiutang(PDO $pdo, int $id): array
    {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id_piutang FROM piutang WHERE id_piutang = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            if ($stmt->fetch() === false) {
                $pdo->rollBack();
                return ['ok' => false, 'pesan' => 'Piutang tidak ditemukan.'];
            }

            if (self::punyaPembayaran($pdo, $id)) {
                $pdo->rollBack();
                return ['ok' => false, 'pesan' => 'Piutang tidak dapat dihapus karena sudah memiliki riwayat pembayaran.'];
            }

            $stmt = $pdo->prepare('DELETE FROM piutang WHERE id_piutang = :id');
            $stmt->execute([':id' => $id]);

            $pdo->commit();

            return ['ok' => true, 'pesan' => ''];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Terjadi kesalahan. Coba lagi.'];
        }
    }

    public static function ambilOpsiUntukBayar(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT p.id_piutang, p.no_transaksi, p.total_piutang, p.jumlah_dibayar,
                    p.sisa_piutang, p.jatuh_tempo, p.status, pl.nama_pelanggan
             FROM piutang AS p
             JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
             WHERE p.status <> 'Lunas'
             ORDER BY p.jatuh_tempo ASC, p.id_piutang ASC"
        )->fetchAll();
    }
}