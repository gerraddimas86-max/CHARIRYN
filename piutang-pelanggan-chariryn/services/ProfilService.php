<?php

declare(strict_types=1);

/**
 * Service: Permintaan Perubahan Profil (D6 - DFD 2.4 dan 8.7)
 * Fungsi: Mencatat permintaan perubahan profil pelanggan, meninjau dan
 * memutuskan. Profil AKTIF hanya berubah setelah Manajer menyetujui.
 */

class ProfilService
{
    /**
     * Field profil yang boleh diubah lewat permintaan.
     * kode_pelanggan dan status tidak termasuk: keduanya bukan wewenang
     * pelanggan dan bukan bagian dari permintaan profil.
     */
    public const FIELD = [
        'nama_pelanggan' => 'Nama Pelanggan',
        'alamat' => 'Alamat',
        'no_hp' => 'No. HP',
        'email' => 'Email',
    ];

    /**
     * Daftar permintaan untuk Manajer (DFD 2.4).
     */
    public static function daftar(PDO $pdo, string $status = '', string $cari = ''): array
    {
        $kondisi = [];
        $parameter = [];

        if ($status !== '') {
            $kondisi[] = 'pp.status = :status';
            $parameter[':status'] = $status;
        }

        if ($cari !== '') {
            $kondisi[] = "(pl.nama_pelanggan LIKE :cari1 ESCAPE '\\\\'
                           OR pl.kode_pelanggan LIKE :cari2 ESCAPE '\\\\'
                           OR pp.alasan LIKE :cari3 ESCAPE '\\\\')";
            $nilai = '%' . escapeLike($cari) . '%';
            $parameter[':cari1'] = $parameter[':cari2'] = $parameter[':cari3'] = $nilai;
        }

        $where = $kondisi !== [] ? 'WHERE ' . implode(' AND ', $kondisi) : '';

        $stmt = $pdo->prepare(
            'SELECT pp.id_permintaan, pp.data_baru, pp.alasan, pp.status, pp.alasan_penolakan,
                    pp.created_at, pp.tanggal_verifikasi,
                    pl.id_pelanggan, pl.kode_pelanggan, pl.nama_pelanggan,
                    u.nama AS nama_pengaju,
                    uv.nama AS nama_verifikator
             FROM permintaan_profil AS pp
             JOIN pelanggan AS pl ON pl.id_pelanggan = pp.id_pelanggan
             JOIN users AS u ON u.id_user = pp.id_user
             LEFT JOIN users AS uv ON uv.id_user = pp.id_user_verifikasi
             ' . $where . '
             ORDER BY (pp.status = \'menunggu\') DESC, pp.created_at DESC, pp.id_permintaan DESC'
        );
        $stmt->execute($parameter);

        return $stmt->fetchAll();
    }

    public static function ambil(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT pp.*, pl.kode_pelanggan, pl.nama_pelanggan, pl.alamat, pl.no_hp, pl.email, pl.status AS status_pelanggan,
                    u.nama AS nama_pengaju,
                    uv.nama AS nama_verifikator
             FROM permintaan_profil AS pp
             JOIN pelanggan AS pl ON pl.id_pelanggan = pp.id_pelanggan
             JOIN users AS u ON u.id_user = pp.id_user
             LEFT JOIN users AS uv ON uv.id_user = pp.id_user_verifikasi
             WHERE pp.id_permintaan = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $hasil = $stmt->fetch();

        return $hasil === false ? null : $hasil;
    }

    /**
     * Permintaan milik satu pelanggan (DFD 8.7).
     */
    public static function daftarMilikPelanggan(PDO $pdo, int $idPelanggan): array
    {
        $stmt = $pdo->prepare(
            'SELECT pp.* FROM permintaan_profil AS pp
             WHERE pp.id_pelanggan = :id
             ORDER BY pp.created_at DESC, pp.id_permintaan DESC'
        );
        $stmt->execute([':id' => $idPelanggan]);

        return $stmt->fetchAll();
    }

    /**
     * Pelanggan mengajukan perubahan profil (DFD 8.7).
     * $data berisi nilai baru per field; hanya field yang berubah
     * yang disimpan.
     */
    public static function buat(PDO $pdo, int $idPelanggan, int $idUser, array $data, string $alasan): array
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            return ['ok' => false, 'pesan' => 'Alasan perubahan wajib diisi.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM pelanggan WHERE id_pelanggan = :id LIMIT 1');
        $stmt->execute([':id' => $idPelanggan]);
        $pelanggan = $stmt->fetch();

        if ($pelanggan === false) {
            return ['ok' => false, 'pesan' => 'Data pelanggan tidak ditemukan.'];
        }

        $perubahan = [];
        foreach (self::FIELD as $field => $label) {
            $nilai = trim((string) ($data[$field] ?? ''));
            $lama = trim((string) ($pelanggan[$field] ?? ''));

            if ($nilai === $lama) {
                continue;
            }

            if ($nilai === '' && $field !== 'alamat') {
                return ['ok' => false, 'pesan' => $label . ' tidak boleh kosong.'];
            }

            $perubahan[$field] = $nilai;
        }

        if ($perubahan === []) {
            return ['ok' => false, 'pesan' => 'Tidak ada data profil yang berubah.'];
        }

        if (isset($perubahan['email']) && $perubahan['email'] !== '' && filter_var($perubahan['email'], FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'pesan' => 'Format email tidak valid.'];
        }

        // Satu pelanggan tidak boleh punya dua permintaan menunggu.
        $cek = $pdo->prepare(
            "SELECT COUNT(*) FROM permintaan_profil
             WHERE id_pelanggan = :id AND status = 'menunggu'"
        );
        $cek->execute([':id' => $idPelanggan]);
        if ((int) $cek->fetchColumn() > 0) {
            return ['ok' => false, 'pesan' => 'Sudah ada permintaan perubahan profil yang menunggu peninjauan.'];
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO permintaan_profil (id_pelanggan, id_user, data_baru, alasan, status)
                 VALUES (:id_pelanggan, :id_user, :data_baru, :alasan, 'menunggu')"
            );
            $stmt->execute([
                ':id_pelanggan' => $idPelanggan,
                ':id_user' => $idUser,
                ':data_baru' => json_encode($perubahan, JSON_UNESCAPED_UNICODE),
                ':alasan' => $alasan,
            ]);

            return [
                'ok' => true,
                'pesan' => 'Permintaan perubahan profil tersimpan dan menunggu peninjauan manajer.',
                'id_permintaan' => (int) $pdo->lastInsertId(),
            ];
        } catch (Throwable $e) {
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Permintaan gagal disimpan. Silakan coba lagi.'];
        }
    }

    /**
     * Manajer menyetujui permintaan (DFD 2.4).
     * Data yang disetujui baru ditulis ke profil aktif.
     */
    public static function setujui(PDO $pdo, int $id, int $idUserVerifikasi): array
    {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM permintaan_profil WHERE id_permintaan = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $permintaan = $stmt->fetch();

            if ($permintaan === false) {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Permintaan tidak ditemukan.'];
            }

            if ((string) $permintaan['status'] !== 'menunggu') {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Permintaan ini sudah diproses sebelumnya.'];
            }

            $perubahan = json_decode((string) $permintaan['data_baru'], true);
            if (!is_array($perubahan) || $perubahan === []) {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Data perubahan tidak valid.'];
            }

            $kolom = [];
            $parameter = [];
            foreach ($perubahan as $field => $nilai) {
                if (!isset(self::FIELD[$field])) {
                    continue;
                }
                $kolom[] = $field . ' = :' . $field;
                $parameter[':' . $field] = (string) $nilai;
            }

            if ($kolom === []) {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Tidak ada data profil valid untuk disetujui.'];
            }

            $parameter[':id'] = (int) $permintaan['id_pelanggan'];
            $sql = 'UPDATE pelanggan SET ' . implode(', ', $kolom) . ' WHERE id_pelanggan = :id';
            $pdo->prepare($sql)->execute($parameter);

            // Nama akun (D1) mengikuti nama pelanggan yang baru disetujui.
            if (isset($perubahan['nama_pelanggan'])) {
                $stmt = $pdo->prepare('UPDATE users SET nama = :nama WHERE id_pelanggan = :id AND role = \'pelanggan\'');
                $stmt->execute([
                    ':nama' => (string) $perubahan['nama_pelanggan'],
                    ':id' => (int) $permintaan['id_pelanggan'],
                ]);
            }

            $stmt = $pdo->prepare(
                "UPDATE permintaan_profil
                 SET status = 'disetujui', id_user_verifikasi = :id_user, tanggal_verifikasi = NOW()
                 WHERE id_permintaan = :id AND status = 'menunggu'"
            );
            $stmt->execute([':id_user' => $idUserVerifikasi, ':id' => $id]);

            $pdo->commit();

            return ['ok' => true, 'pesan' => 'Permintaan disetujui. Data profil pelanggan telah diperbarui.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Terjadi kesalahan. Coba lagi.'];
        }
    }

    /**
     * Manajer menolak permintaan (DFD 2.4). Profil aktif tetap apa adanya.
     */
    public static function tolak(PDO $pdo, int $id, int $idUserVerifikasi, string $alasan): array
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            return ['ok' => false, 'pesan' => 'Alasan penolakan wajib diisi.'];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT status FROM permintaan_profil WHERE id_permintaan = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $baris = $stmt->fetch();

            if ($baris === false) {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Permintaan tidak ditemukan.'];
            }

            if ((string) $baris['status'] !== 'menunggu') {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Permintaan ini sudah diproses sebelumnya.'];
            }

            $stmt = $pdo->prepare(
                "UPDATE permintaan_profil
                 SET status = 'ditolak', alasan_penolakan = :alasan,
                     id_user_verifikasi = :id_user, tanggal_verifikasi = NOW()
                 WHERE id_permintaan = :id"
            );
            $stmt->execute([
                ':alasan' => $alasan,
                ':id_user' => $idUserVerifikasi,
                ':id' => $id,
            ]);

            $pdo->commit();

            return ['ok' => true, 'pesan' => 'Permintaan ditolak. Profil pelanggan tidak diubah.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Terjadi kesalahan. Coba lagi.'];
        }
    }

    /**
     * Membaca data perubahan yang tersimpan (JSON) menjadi array.
     */
    public static function perubahanDari(?string $json): array
    {
        $data = json_decode((string) $json, true);

        return is_array($data) ? $data : [];
    }
}
