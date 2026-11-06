<?php

declare(strict_types=1);

/**
 * Service: Pengajuan Pembayaran (D5 - DFD 4.4 dan 8.5/8.6)
 * Fungsi: Menyimpan pengajuan pembayaran pelanggan, memverifikasinya,
 * dan membuat pembayaran resmi HANYA saat pengajuan disetujui.
 *
 * Aturan penting: pengajuan yang masih "menunggu" tidak boleh
 * mengurangi saldo piutang (DFD 4.3).
 */

class PengajuanService
{
    /** Field profil pembayaran yang boleh dicatat pelanggan. */
    public const METODE = ['Tunai', 'Transfer', 'E-Wallet'];

    /**
     * Daftar pengajuan untuk Manajer (DFD 4.4).
     */
    public static function daftar(PDO $pdo, string $status = '', string $cari = '', int $halaman = 1, int $perHalaman = 10): array
    {
        $kondisi = [];
        $parameter = [];

        if ($status !== '') {
            $kondisi[] = 'pg.status = :status';
            $parameter[':status'] = $status;
        }

        if ($cari !== '') {
            $kondisi[] = "(p.no_transaksi LIKE :cari1 ESCAPE '\\\\'
                           OR pl.nama_pelanggan LIKE :cari2 ESCAPE '\\\\'
                           OR pl.kode_pelanggan LIKE :cari3 ESCAPE '\\\\')";
            $nilai = '%' . escapeLike($cari) . '%';
            $parameter[':cari1'] = $parameter[':cari2'] = $parameter[':cari3'] = $nilai;
        }

        $where = $kondisi !== [] ? 'WHERE ' . implode(' AND ', $kondisi) : '';

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM pengajuan_pembayaran AS pg
             JOIN piutang AS p ON p.id_piutang = pg.id_piutang
             JOIN pelanggan AS pl ON pl.id_pelanggan = pg.id_pelanggan
             ' . $where
        );
        $stmt->execute($parameter);
        $total = (int) $stmt->fetchColumn();

        $halaman = max(1, min($halaman, max(1, (int) ceil($total / $perHalaman))));
        $offset = ($halaman - 1) * $perHalaman;

        $sql = 'SELECT
                    pg.id_pengajuan, pg.nominal, pg.tanggal_pengajuan, pg.metode_pembayaran,
                    pg.catatan, pg.status, pg.alasan_penolakan, pg.bukti_pembayaran,
                    pg.created_at, pg.tanggal_verifikasi,
                    p.id_piutang, p.no_transaksi, p.total_piutang, p.sisa_piutang, p.jatuh_tempo,
                    pl.id_pelanggan, pl.kode_pelanggan, pl.nama_pelanggan,
                    u.nama AS nama_pengaju
                FROM pengajuan_pembayaran AS pg
                JOIN piutang AS p ON p.id_piutang = pg.id_piutang
                JOIN pelanggan AS pl ON pl.id_pelanggan = pg.id_pelanggan
                JOIN users AS u ON u.id_user = pg.id_user
                ' . $where . '
                ORDER BY (pg.status = \'menunggu\') DESC, pg.tanggal_pengajuan DESC, pg.id_pengajuan DESC
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

    /**
     * Detail satu pengajuan.
     */
    public static function ambil(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT pg.*, p.no_transaksi, p.total_piutang, p.sisa_piutang, p.jatuh_tempo, p.status AS status_piutang,
                    pl.kode_pelanggan, pl.nama_pelanggan,
                    u.nama AS nama_pengaju,
                    uv.nama AS nama_verifikator,
                    b.jumlah_bayar AS jumlah_pembayaran_resmi,
                    b.tanggal_pembayaran AS tanggal_pembayaran_resmi
             FROM pengajuan_pembayaran AS pg
             JOIN piutang AS p ON p.id_piutang = pg.id_piutang
             JOIN pelanggan AS pl ON pl.id_pelanggan = pg.id_pelanggan
             JOIN users AS u ON u.id_user = pg.id_user
             LEFT JOIN users AS uv ON uv.id_user = pg.id_user_verifikasi
             LEFT JOIN pembayaran AS b ON b.id_pembayaran = pg.id_pembayaran
             WHERE pg.id_pengajuan = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $hasil = $stmt->fetch();

        return $hasil === false ? null : $hasil;
    }

    /**
     * Pengajuan milik satu pelanggan (DFD 8.6).
     */
    public static function daftarMilikPelanggan(PDO $pdo, int $idPelanggan): array
    {
        $stmt = $pdo->prepare(
            'SELECT pg.id_pengajuan, pg.nominal, pg.tanggal_pengajuan, pg.metode_pembayaran,
                    pg.catatan, pg.status, pg.alasan_penolakan, pg.bukti_pembayaran,
                    pg.created_at, pg.tanggal_verifikasi,
                    p.id_piutang, p.no_transaksi, p.sisa_piutang, p.jatuh_tempo
             FROM pengajuan_pembayaran AS pg
             JOIN piutang AS p ON p.id_piutang = pg.id_piutang
             WHERE pg.id_pelanggan = :id
             ORDER BY pg.tanggal_pengajuan DESC, pg.id_pengajuan DESC'
        );
        $stmt->execute([':id' => $idPelanggan]);

        return $stmt->fetchAll();
    }

    /**
     * Pelanggan mengajukan pembayaran (DFD 8.5).
     * $data['nama_berkas_simpan'] = nama acak berkas bukti (sudah diunggah).
     */
    public static function buat(PDO $pdo, int $idPelanggan, int $idUser, array $data): array
    {
        $idPiutang = (int) ($data['id_piutang'] ?? 0);
        $nominal = round((float) ($data['nominal'] ?? 0), 2);
        $metode = (string) ($data['metode_pembayaran'] ?? '');
        $tanggal = (string) ($data['tanggal_pengajuan'] ?? '');
        $namaBerkas = (string) ($data['nama_berkas_simpan'] ?? '');

        if ($idPiutang <= 0) {
            return ['ok' => false, 'pesan' => 'Pilih tagihan yang akan dibayar.'];
        }

        if (!in_array($metode, self::METODE, true)) {
            return ['ok' => false, 'pesan' => 'Metode pembayaran tidak valid.'];
        }

        if ($nominal <= 0) {
            return ['ok' => false, 'pesan' => 'Nominal pengajuan harus lebih dari 0.'];
        }

        if ($namaBerkas === '') {
            return ['ok' => false, 'pesan' => 'Bukti pembayaran wajib diunggah.'];
        }

        $stmt = $pdo->prepare(
            'SELECT p.id_piutang, p.id_pelanggan, p.sisa_piutang, p.status
             FROM piutang AS p WHERE p.id_piutang = :id LIMIT 1'
        );
        $stmt->execute([':id' => $idPiutang]);
        $piutang = $stmt->fetch();

        if ($piutang === false) {
            return ['ok' => false, 'pesan' => 'Tagihan tidak ditemukan.'];
        }

        // Kepemilikan data diperiksa di server, bukan dari parameter frontend.
        if ((int) $piutang['id_pelanggan'] !== $idPelanggan) {
            return ['ok' => false, 'pesan' => 'Tagihan tersebut bukan milik Anda.'];
        }

        $sisa = round((float) $piutang['sisa_piutang'], 2);
        if ($sisa <= 0.005) {
            return ['ok' => false, 'pesan' => 'Tagihan ini sudah lunas.'];
        }

        if ($nominal - $sisa > 0.005) {
            return ['ok' => false, 'pesan' => 'Nominal pengajuan melebihi sisa tagihan sebesar ' . formatRupiah($sisa) . '.'];
        }

        // Cegah pengajuan ganda untuk tagihan yang sama.
        $cek = $pdo->prepare(
            "SELECT COUNT(*) FROM pengajuan_pembayaran
             WHERE id_piutang = :id_piutang AND id_pelanggan = :id_pelanggan AND status = 'menunggu'"
        );
        $cek->execute([':id_piutang' => $idPiutang, ':id_pelanggan' => $idPelanggan]);
        if ((int) $cek->fetchColumn() > 0) {
            return ['ok' => false, 'pesan' => 'Sudah ada pengajuan menunggu verifikasi untuk tagihan ini.'];
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pengajuan_pembayaran
                    (id_piutang, id_pelanggan, id_user, nominal, tanggal_pengajuan,
                     metode_pembayaran, bukti_pembayaran, catatan, status)
                 VALUES
                    (:id_piutang, :id_pelanggan, :id_user, :nominal, :tanggal,
                     :metode, :bukti, :catatan, \'menunggu\')'
            );
            $stmt->execute([
                ':id_piutang' => $idPiutang,
                ':id_pelanggan' => $idPelanggan,
                ':id_user' => $idUser,
                ':nominal' => $nominal,
                ':tanggal' => $tanggal,
                ':metode' => $metode,
                ':bukti' => $namaBerkas,
                ':catatan' => $data['catatan'] ?? null,
            ]);

            return [
                'ok' => true,
                'pesan' => 'Pengajuan pembayaran tersimpan dan menunggu verifikasi manajer.',
                'id_pengajuan' => (int) $pdo->lastInsertId(),
            ];
        } catch (Throwable $e) {
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Pengajuan gagal disimpan. Silakan coba lagi.'];
        }
    }

    /**
     * Manajer menyetujui pengajuan (DFD 4.4).
     * Membuat SATU pembayaran resmi lalu menghitung ulang saldo.
     * Request berulang tidak menghasilkan pembayaran kedua.
     */
    public static function setujui(PDO $pdo, int $id, int $idUserVerifikasi): array
    {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM pengajuan_pembayaran WHERE id_pengajuan = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $pengajuan = $stmt->fetch();

            if ($pengajuan === false) {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Pengajuan tidak ditemukan.'];
            }

            if ((string) $pengajuan['status'] !== 'menunggu') {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'pesan' => 'Pengajuan ini sudah diproses sebelumnya (' . (string) $pengajuan['status'] . ').',
                ];
            }

            $idPiutang = (int) $pengajuan['id_piutang'];
            $nominal = round((float) $pengajuan['nominal'], 2);

            $stmt = $pdo->prepare('SELECT sisa_piutang FROM piutang WHERE id_piutang = :id FOR UPDATE');
            $stmt->execute([':id' => $idPiutang]);
            $piutang = $stmt->fetch();

            if ($piutang === false) {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Tagihan tidak ditemukan.'];
            }

            $sisa = round((float) $piutang['sisa_piutang'], 2);
            if ($nominal <= 0) {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Nominal pengajuan tidak valid.'];
            }

            if ($nominal - $sisa > 0.005) {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'pesan' => 'Nominal pengajuan melebihi sisa tagihan saat ini (' . formatRupiah($sisa) . '). Tolak pengajuan atau koreksi nominalnya.',
                ];
            }

            $stmt = $pdo->prepare(
                'INSERT INTO pembayaran (id_piutang, tanggal_pembayaran, jumlah_bayar, metode_pembayaran, keterangan, status)
                 VALUES (:id_piutang, :tanggal, :jumlah, :metode, :keterangan, \'berlaku\')'
            );
            $stmt->execute([
                ':id_piutang' => $idPiutang,
                ':tanggal' => date('Y-m-d'),
                ':jumlah' => $nominal,
                ':metode' => (string) $pengajuan['metode_pembayaran'],
                ':keterangan' => 'Pembayaran dari pengajuan #' . (int) $pengajuan['id_pengajuan'],
            ]);
            $idPembayaran = (int) $pdo->lastInsertId();

            perbaruiRingkasanPiutang($pdo, $idPiutang);

            $update = $pdo->prepare(
                "UPDATE pengajuan_pembayaran
                 SET status = 'disetujui', id_pembayaran = :id_pembayaran,
                     id_user_verifikasi = :id_user, tanggal_verifikasi = NOW()
                 WHERE id_pengajuan = :id AND status = 'menunggu'"
            );
            $update->execute([
                ':id_pembayaran' => $idPembayaran,
                ':id_user' => $idUserVerifikasi,
                ':id' => $id,
            ]);

            $pdo->commit();

            return [
                'ok' => true,
                'pesan' => 'Pengajuan disetujui. Pembayaran resmi dicatat dan saldo piutang dihitung ulang.',
                'id_pembayaran' => $idPembayaran,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Terjadi kesalahan. Coba lagi.'];
        }
    }

    /**
     * Manajer menolak pengajuan (DFD 4.4). Saldo piutang tidak berubah.
     */
    public static function tolak(PDO $pdo, int $id, int $idUserVerifikasi, string $alasan): array
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            return ['ok' => false, 'pesan' => 'Alasan penolakan wajib diisi.'];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT status FROM pengajuan_pembayaran WHERE id_pengajuan = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $baris = $stmt->fetch();

            if ($baris === false) {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Pengajuan tidak ditemukan.'];
            }

            if ((string) $baris['status'] !== 'menunggu') {
                $pdo->rollBack();

                return ['ok' => false, 'pesan' => 'Pengajuan ini sudah diproses sebelumnya.'];
            }

            $stmt = $pdo->prepare(
                "UPDATE pengajuan_pembayaran
                 SET status = 'ditolak', alasan_penolakan = :alasan,
                     id_user_verifikasi = :id_user, tanggal_verifikasi = NOW()
                 WHERE id_pengajuan = :id"
            );
            $stmt->execute([
                ':alasan' => $alasan,
                ':id_user' => $idUserVerifikasi,
                ':id' => $id,
            ]);

            $pdo->commit();

            return ['ok' => true, 'pesan' => 'Pengajuan ditolak. Alasan penolakan tersimpan dan tampil ke pelanggan.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Terjadi kesalahan. Coba lagi.'];
        }
    }
}
