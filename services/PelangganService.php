<?php

declare(strict_types=1);

/**
 * Service: Pelanggan
 * Fungsi: Operasi data pelanggan (CRUD, pencarian, ringkasan)
 */

class PelangganService
{
    public static function ambilSemua(PDO $pdo, string $cari = '', string $status = '', int $halaman = 1, int $perHalaman = 10): array
    {
        $kondisi = [];
        $parameter = [];

        if ($cari !== '') {
            $kondisi[] = "(p.kode_pelanggan LIKE :cari1 ESCAPE '\\\\' OR p.nama_pelanggan LIKE :cari2 ESCAPE '\\\\' OR p.no_hp LIKE :cari3 ESCAPE '\\\\')";
            $parameter[':cari1'] = $parameter[':cari2'] = $parameter[':cari3'] = '%' . escapeLike($cari) . '%';
        }

        if ($status !== '') {
            $kondisi[] = 'p.status = :status';
            $parameter[':status'] = $status;
        }

        $where = $kondisi !== [] ? 'WHERE ' . implode(' AND ', $kondisi) : '';

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pelanggan AS p ' . $where);
        $stmt->execute($parameter);
        $total = (int) $stmt->fetchColumn();

        $halaman = max(1, min($halaman, max(1, (int) ceil($total / $perHalaman))));
        $offset = ($halaman - 1) * $perHalaman;
        $sql = 'SELECT
                    p.id_pelanggan,
                    p.kode_pelanggan,
                    p.nama_pelanggan,
                    p.no_hp,
                    p.email,
                    p.status,
                    p.created_at,
                    (SELECT COUNT(*) FROM piutang AS x WHERE x.id_pelanggan = p.id_pelanggan) AS jumlah_piutang,
                    (SELECT COALESCE(SUM(x.sisa_piutang), 0) FROM piutang AS x WHERE x.id_pelanggan = p.id_pelanggan) AS total_sisa
                FROM pelanggan AS p
                ' . $where . '
                ORDER BY p.nama_pelanggan ASC
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
        $stmt = $pdo->prepare('SELECT * FROM pelanggan WHERE id_pelanggan = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $hasil = $stmt->fetch();

        return $hasil === false ? null : $hasil;
    }

    public static function ambilOpsi(PDO $pdo): array
    {
        // Semua status ikut (pelanggan baru langsung terindeks di filter).
        // Dropdown transaksi menonaktifkan opsi nonaktif di sisi tampilan.
        return $pdo->query(
            "SELECT id_pelanggan, kode_pelanggan, nama_pelanggan, status
             FROM pelanggan
             ORDER BY nama_pelanggan ASC"
        )->fetchAll();
    }

    public static function kodeTersedia(PDO $pdo, string $kode, ?int $abaikanId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pelanggan WHERE kode_pelanggan = :kode';
        $parameter = [':kode' => $kode];

        if ($abaikanId !== null) {
            $sql .= ' AND id_pelanggan <> :id';
            $parameter[':id'] = $abaikanId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameter);

        return (int) $stmt->fetchColumn() === 0;
    }

    public static function buatPelanggan(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO pelanggan (kode_pelanggan, nama_pelanggan, alamat, no_hp, email, status)
             VALUES (:kode, :nama, :alamat, :no_hp, :email, :status)'
        );
        $stmt->execute([
            ':kode' => $data['kode_pelanggan'],
            ':nama' => $data['nama_pelanggan'],
            ':alamat' => $data['alamat'] ?? null,
            ':no_hp' => $data['no_hp'] ?? null,
            ':email' => $data['email'] ?? null,
            ':status' => $data['status'] ?? 'aktif',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Memperbarui data pelanggan (DFD 2.2).
     * Perubahan status pelanggan ikut menyinkronkan status akunnya (D1)
     * supaya pelanggan nonaktif tidak bisa masuk melihat piutang.
     */
    public static function ubahPelanggan(PDO $pdo, int $id, array $data): bool
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE pelanggan
                 SET kode_pelanggan = :kode,
                     nama_pelanggan = :nama,
                     alamat = :alamat,
                     no_hp = :no_hp,
                     email = :email,
                     status = :status
                 WHERE id_pelanggan = :id'
            );
            $stmt->execute([
                ':kode' => $data['kode_pelanggan'],
                ':nama' => $data['nama_pelanggan'],
                ':alamat' => $data['alamat'] ?? null,
                ':no_hp' => $data['no_hp'] ?? null,
                ':email' => $data['email'] ?? null,
                ':status' => $data['status'] ?? 'aktif',
                ':id' => $id,
            ]);

            $status = (string) ($data['status'] ?? 'aktif');
            if ($status === 'nonaktif') {
                $pdo->prepare("UPDATE users SET status = 'nonaktif' WHERE id_pelanggan = :id AND role = 'pelanggan'")
                    ->execute([':id' => $id]);
            } else {
                // Akun "belum aktif" tetap menunggu aktivasi pelanggan sendiri.
                $pdo->prepare("UPDATE users SET status = 'aktif' WHERE id_pelanggan = :id AND role = 'pelanggan' AND status = 'nonaktif'")
                    ->execute([':id' => $id]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return true;
    }

    public static function punyaRiwayat(PDO $pdo, int $id): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM piutang WHERE id_pelanggan = :id');
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Hapus pelanggan (DFD 2.3).
     *
     * Penghapusan hanya aman bila pelanggan TIDAK memiliki riwayat
     * piutang/pembayaran. Riwayat keuangan tidak boleh hilang, jadi
     * pelanggan yang sudah punya transaksi harus dinonaktifkan, bukan
     * dihapus. Akun login (D1) ikut terhapus hanya pada kasus itu.
     */
    public static function hapusPelanggan(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM piutang WHERE id_pelanggan = :id');
        $stmt->execute([':id' => $id]);
        $jumlahPiutang = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM pembayaran AS b
             JOIN piutang AS p ON p.id_piutang = b.id_piutang
             WHERE p.id_pelanggan = :id'
        );
        $stmt->execute([':id' => $id]);
        $jumlahPembayaran = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id_pelanggan = :id');
        $stmt->execute([':id' => $id]);
        $jumlahAkun = (int) $stmt->fetchColumn();

        if ($jumlahPiutang > 0 || $jumlahPembayaran > 0) {
            return [
                'ok' => false,
                'pesan' => 'Pelanggan memiliki '
                    . formatAngka($jumlahPiutang) . ' transaksi piutang dan '
                    . formatAngka($jumlahPembayaran) . ' pembayaran. '
                    . 'Riwayat keuangan tidak boleh dihapus. Nonaktifkan pelanggan sebagai gantinya.',
                'piutang' => $jumlahPiutang,
                'pembayaran' => $jumlahPembayaran,
                'akun' => $jumlahAkun,
            ];
        }

        try {
            $pdo->beginTransaction();
            // D1 lebih dulu: akun pelanggan tidak boleh menjadi yatim.
            $pdo->prepare('DELETE FROM users WHERE id_pelanggan = :id')->execute([':id' => $id]);
            $hapus = $pdo->prepare('DELETE FROM pelanggan WHERE id_pelanggan = :id');
            $hapus->execute([':id' => $id]);
            $ok = $hapus->rowCount() > 0;
            if ($ok) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['ok' => $ok, 'pesan' => '', 'piutang' => 0, 'pembayaran' => 0, 'akun' => $jumlahAkun];
    }

    public static function ringkasan(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                COUNT(*) AS jumlah_piutang,
                COALESCE(SUM(total_piutang), 0) AS total_piutang,
                COALESCE(SUM(jumlah_dibayar), 0) AS total_dibayar,
                COALESCE(SUM(sisa_piutang), 0) AS total_sisa
             FROM piutang
             WHERE id_pelanggan = :id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    public static function riwayatPiutang(PDO $pdo, int $idPelanggan): array
    {
        $stmt = $pdo->prepare(
            'SELECT id_piutang, no_transaksi, tanggal_transaksi, total_piutang,
                    jumlah_dibayar, sisa_piutang, jatuh_tempo, status
             FROM piutang
             WHERE id_pelanggan = :id
             ORDER BY tanggal_transaksi DESC, id_piutang DESC'
        );
        $stmt->execute([':id' => $idPelanggan]);

        return $stmt->fetchAll();
    }

    public static function riwayatPembayaran(PDO $pdo, int $idPelanggan): array
    {
        $stmt = $pdo->prepare(
            'SELECT b.id_pembayaran, b.tanggal_pembayaran, b.jumlah_bayar, b.metode_pembayaran, b.keterangan,
                    b.status, b.alasan_pembatalan, p.no_transaksi
             FROM pembayaran AS b
             JOIN piutang AS p ON p.id_piutang = b.id_piutang
             WHERE p.id_pelanggan = :id
             ORDER BY b.tanggal_pembayaran DESC, b.id_pembayaran DESC'
        );
        $stmt->execute([':id' => $idPelanggan]);

        return $stmt->fetchAll();
    }
}