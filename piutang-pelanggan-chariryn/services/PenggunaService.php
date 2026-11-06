<?php

declare(strict_types=1);

/**
 * Service: Pengguna
 * Fungsi: Autentikasi dan pengelolaan akun pelanggan (D1).
 *
 * Akun Pemilik/Manajer tidak dibuat dari halaman web (sesuai DFD final);
 * akun tersebut disediakan melalui seed/administrasi database.
 */

class PenggunaService
{
    public static function autentikasi(PDO $pdo, string $username, string $kataSandi): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT u.id_user, u.username, u.password, u.nama, u.role, u.status, u.id_pelanggan,
                    pl.nama_pelanggan
             FROM users AS u
             LEFT JOIN pelanggan AS pl ON pl.id_pelanggan = u.id_pelanggan
             WHERE u.username = :username LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user === false || !password_verify($kataSandi, (string) $user['password'])) {
            return null;
        }

        if ($user['status'] !== 'aktif') {
            return null;
        }

        if ($user['role'] === 'pelanggan' && (int) ($user['id_pelanggan'] ?? 0) <= 0) {
            return null;
        }

        return $user;
    }

    public static function ambilAkunPelanggan(PDO $pdo, string $cari = '', string $status = ''): array
    {
        $kondisi = ["u.role = 'pelanggan'"];
        $parameter = [];

        if ($cari !== '') {
            $kondisi[] = "(u.username LIKE :cari1 ESCAPE '\\\\' OR u.nama LIKE :cari2 ESCAPE '\\\\'
                           OR pl.kode_pelanggan LIKE :cari3 ESCAPE '\\\\' OR pl.nama_pelanggan LIKE :cari4 ESCAPE '\\\\')";
            $nilai = '%' . escapeLike($cari) . '%';
            $parameter[':cari1'] = $parameter[':cari2'] = $parameter[':cari3'] = $parameter[':cari4'] = $nilai;
        }

        if ($status !== '') {
            $kondisi[] = 'u.status = :status';
            $parameter[':status'] = $status;
        }

        $stmt = $pdo->prepare(
            'SELECT u.id_user, u.username, u.nama, u.role, u.status, u.id_pelanggan,
                    u.created_at, u.updated_at, u.token_aktivasi, u.token_aktivasi_berakhir,
                    pl.kode_pelanggan, pl.nama_pelanggan, pl.status AS status_pelanggan
             FROM users AS u
             LEFT JOIN pelanggan AS pl ON pl.id_pelanggan = u.id_pelanggan
             WHERE ' . implode(' AND ', $kondisi) . '
             ORDER BY pl.nama_pelanggan ASC, u.username ASC'
        );
        $stmt->execute($parameter);

        return $stmt->fetchAll();
    }

    public static function ambilAkunPelangganById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT u.id_user, u.username, u.nama, u.role, u.status, u.id_pelanggan,
                    u.created_at, u.updated_at, u.token_aktivasi, u.token_aktivasi_berakhir,
                    pl.kode_pelanggan, pl.nama_pelanggan, pl.status AS status_pelanggan
             FROM users AS u
             LEFT JOIN pelanggan AS pl ON pl.id_pelanggan = u.id_pelanggan
             WHERE u.id_user = :id AND u.role = 'pelanggan'
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $hasil = $stmt->fetch();

        return $hasil === false ? null : $hasil;
    }

    public static function pelangganTanpaAkun(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT p.id_pelanggan, p.kode_pelanggan, p.nama_pelanggan, p.email
             FROM pelanggan AS p
             LEFT JOIN users AS u ON u.id_pelanggan = p.id_pelanggan
             WHERE u.id_user IS NULL
               AND p.status = 'aktif'
             ORDER BY p.nama_pelanggan ASC"
        )->fetchAll();
    }

    public static function pelangganPunyaAkun(PDO $pdo, int $idPelanggan, ?int $abaikanId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE id_pelanggan = :id';
        $parameter = [':id' => $idPelanggan];

        if ($abaikanId !== null) {
            $sql .= ' AND id_user <> :id_user';
            $parameter[':id_user'] = $abaikanId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameter);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function usernameTersedia(PDO $pdo, string $username, ?int $abaikanId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $parameter = [':username' => $username];

        if ($abaikanId !== null) {
            $sql .= ' AND id_user <> :id';
            $parameter[':id'] = $abaikanId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameter);

        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * Buat akun pelanggan baru (DFD 7.2).
     * Akun disimpan dengan status "belum aktif" beserta token aktivasi.
     * Pelanggan sendiri yang menetapkan kata sandi lewat token tersebut
     * (DFD 7.4), sehingga manajer tidak pernah menentukan sandi awal.
     */
    public static function buatAkunPelanggan(PDO $pdo, array $data): array
    {
        $idPelanggan = (int) ($data['id_pelanggan'] ?? 0);
        $username = trim((string) ($data['username'] ?? ''));
        $nama = trim((string) ($data['nama'] ?? ''));

        if ($idPelanggan <= 0 || $username === '' || $nama === '') {
            return ['ok' => false, 'pesan' => 'Data akun belum lengkap.'];
        }

        if (PelangganService::ambil($pdo, $idPelanggan) === null) {
            return ['ok' => false, 'pesan' => 'Pelanggan tidak terdaftar.'];
        }

        if (self::pelangganPunyaAkun($pdo, $idPelanggan)) {
            return ['ok' => false, 'pesan' => 'Pelanggan ini sudah memiliki akun.'];
        }

        if (!self::usernameTersedia($pdo, $username)) {
            return ['ok' => false, 'pesan' => 'Username sudah digunakan.'];
        }

        $token = bin2hex(random_bytes(32));
        $berakhir = date('Y-m-d H:i:s', time() + 86400); // berlaku 24 jam

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (id_pelanggan, username, password, nama, role, status, token_aktivasi, token_aktivasi_berakhir)
                 VALUES (:id_pelanggan, :username, :password, :nama, 'pelanggan', 'belum aktif', :token, :berakhir)"
            );
            $stmt->execute([
                ':id_pelanggan' => $idPelanggan,
                ':username' => $username,
                // Sandi acak yang tidak diketahui siapa pun: akun belum boleh login.
                ':password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                ':nama' => $nama,
                ':token' => $token,
                ':berakhir' => $berakhir,
            ]);

            return [
                'ok' => true,
                'pesan' => 'Akun pelanggan berhasil dibuat dengan status belum aktif.',
                'token' => $token,
                'berakhir' => $berakhir,
            ];
        } catch (PDOException $e) {
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Akun gagal dibuat. Periksa kembali data yang diisi.'];
        }
    }

    /**
     * Aktivasi akun pelanggan memakai token (DFD 7.4).
     * Token bersifat satu kali: setelah dipakai, token dihapus.
     */
    public static function aktivasi(PDO $pdo, string $token, string $kataSandiBaru): array
    {
        $token = trim($token);

        if ($token === '') {
            return ['ok' => false, 'pesan' => 'Kode aktivasi wajib diisi.'];
        }

        if (strlen($kataSandiBaru) < 8) {
            return ['ok' => false, 'pesan' => 'Kata sandi minimal 8 karakter.'];
        }

        $stmt = $pdo->prepare(
            "SELECT id_user, username, status, token_aktivasi_berakhir
             FROM users WHERE token_aktivasi = :token LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $akun = $stmt->fetch();

        if ($akun === false) {
            return ['ok' => false, 'pesan' => 'Kode aktivasi tidak valid.'];
        }

        if ((string) $akun['status'] === 'aktif') {
            return ['ok' => false, 'pesan' => 'Akun ini sudah aktif. Silakan masuk.'];
        }

        if ((string) $akun['status'] === 'nonaktif') {
            return ['ok' => false, 'pesan' => 'Akun ini dinonaktifkan. Silakan hubungi manajer.'];
        }

        $berakhir = (string) ($akun['token_aktivasi_berakhir'] ?? '');
        if ($berakhir !== '' && strtotime($berakhir) < time()) {
            return ['ok' => false, 'pesan' => 'Kode aktivasi sudah kedaluwarsa. Minta manajer membuat kode baru.'];
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET password = :password, status = 'aktif',
                     token_aktivasi = NULL, token_aktivasi_berakhir = NULL
                 WHERE id_user = :id AND token_aktivasi = :token"
            );
            $stmt->execute([
                ':password' => password_hash($kataSandiBaru, PASSWORD_DEFAULT),
                ':id' => (int) $akun['id_user'],
                ':token' => $token,
            ]);

            return ['ok' => true, 'pesan' => 'Akun berhasil diaktifkan. Silakan masuk dengan kata sandi baru Anda.'];
        } catch (Throwable $e) {
            catatKesalahan($e);

            return ['ok' => false, 'pesan' => 'Aktivasi gagal. Silakan coba lagi.'];
        }
    }

    /**
     * Membuat ulang token aktivasi untuk akun yang belum aktif.
     */
    public static function buatTokenAktivasiBaru(PDO $pdo, int $id): array
    {
        $akun = self::ambilAkunPelangganById($pdo, $id);
        if ($akun === null) {
            return ['ok' => false, 'pesan' => 'Akun pelanggan tidak ditemukan.'];
        }

        if ((string) $akun['status'] === 'aktif') {
            return ['ok' => false, 'pesan' => 'Akun ini sudah aktif.'];
        }

        $token = bin2hex(random_bytes(32));
        $berakhir = date('Y-m-d H:i:s', time() + 86400);

        $stmt = $pdo->prepare(
            'UPDATE users SET token_aktivasi = :token, token_aktivasi_berakhir = :berakhir WHERE id_user = :id'
        );
        $stmt->execute([':token' => $token, ':berakhir' => $berakhir, ':id' => $id]);

        return ['ok' => true, 'pesan' => 'Kode aktivasi baru dibuat.', 'token' => $token, 'berakhir' => $berakhir];
    }

    public static function resetKataSandi(PDO $pdo, int $id, string $password): array
    {
        $akun = self::ambilAkunPelangganById($pdo, $id);
        if ($akun === null) {
            return ['ok' => false, 'pesan' => 'Akun pelanggan tidak ditemukan.'];
        }

        if (strlen($password) < 8) {
            return ['ok' => false, 'pesan' => 'Kata sandi minimal 8 karakter.'];
        }

        $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id_user = :id');
        $stmt->execute([
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $id,
        ]);

        return ['ok' => true, 'pesan' => 'Kata sandi akun berhasil direset.'];
    }

    public static function ubahStatusAkun(PDO $pdo, int $id, string $status): array
    {
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            return ['ok' => false, 'pesan' => 'Status akun tidak valid.'];
        }

        $akun = self::ambilAkunPelangganById($pdo, $id);
        if ($akun === null) {
            return ['ok' => false, 'pesan' => 'Akun pelanggan tidak ditemukan.'];
        }

        // Akun "belum aktif" hanya boleh aktif lewat kode aktivasi (DFD 7.4):
        // pelanggan sendiri yang menentukan kata sandinya.
        if ((string) $akun['status'] === 'belum aktif' && $status === 'aktif') {
            return [
                'ok' => false,
                'pesan' => 'Akun ini belum diaktivasi. Buat kode aktivasi dan suruh pelanggan mengaktifkan akunnya sendiri.',
            ];
        }

        $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id_user = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        return ['ok' => true, 'pesan' => 'Status akun berhasil diubah menjadi ' . $status . '.'];
    }

    public static function ubahKataSandiSendiri(PDO $pdo, int $id, string $lama, string $baru): array
    {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id_user = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $akun = $stmt->fetch();

        if ($akun === false || !password_verify($lama, (string) $akun['password'])) {
            return ['ok' => false, 'pesan' => 'Kata sandi lama tidak sesuai.'];
        }

        if (strlen($baru) < 8) {
            return ['ok' => false, 'pesan' => 'Kata sandi baru minimal 8 karakter.'];
        }

        $update = $pdo->prepare('UPDATE users SET password = :password WHERE id_user = :id');
        $update->execute([
            ':password' => password_hash($baru, PASSWORD_DEFAULT),
            ':id' => $id,
        ]);

        return ['ok' => true, 'pesan' => 'Kata sandi berhasil diperbarui.'];
    }
}
