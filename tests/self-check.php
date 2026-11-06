<?php

declare(strict_types=1);

/**
 * Uji aturan bisnis DFD (bagian 16).
 *
 * Dijalankan terhadap salinan database sementara (lihat run-tests.sh),
 * sehingga data asli tidak tersentuh.
 *
 *   php tests/self-check.php <nama_database>
 */

$dbUji = $argv[1] ?? 'piutang_pelanggan_uji';

require_once __DIR__ . '/../config/aplikasi.php';
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/helpers/kesalahan.php';
require_once BASE_PATH . '/helpers/format.php';
require_once BASE_PATH . '/helpers/validasi.php';
require_once BASE_PATH . '/helpers/piutang.php';
require_once BASE_PATH . '/services/PelangganService.php';
require_once BASE_PATH . '/services/PiutangService.php';
require_once BASE_PATH . '/services/PembayaranService.php';
require_once BASE_PATH . '/services/PenggunaService.php';
require_once BASE_PATH . '/services/PengajuanService.php';
require_once BASE_PATH . '/services/ProfilService.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . $dbUji . ';charset=utf8mb4',
    DB_USER,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$total = 0;
$gagal = 0;

function cek(string $nama, bool $ok, string $info = ''): void
{
    global $total, $gagal;
    $total++;
    if ($ok) {
        echo "OK   $nama\n";
    } else {
        $gagal++;
        echo "FAIL $nama" . ($info !== '' ? " -- $info" : '') . "\n";
    }
}

function sisaPiutang(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT total_piutang, jumlah_dibayar, sisa_piutang, status FROM piutang WHERE id_piutang = :id');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch();
}

function hitungPembayaran(PDO $pdo, int $id): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM pembayaran WHERE id_piutang = :id');
    $stmt->execute([':id' => $id]);

    return (int) $stmt->fetchColumn();
}

$idManajer = (int) $pdo->query("SELECT id_user FROM users WHERE role = 'manajer' LIMIT 1")->fetchColumn();
// Pelanggan yang sedang login: id_pelanggan (D2) dan id_user (D1) berbeda.
$idUserPelanggan = (int) $pdo->query("SELECT id_user FROM users WHERE role = 'pelanggan' LIMIT 1")->fetchColumn();
$idPelanggan = (int) $pdo->query('SELECT id_pelanggan FROM users WHERE id_user = ' . $idUserPelanggan)->fetchColumn();
$idPelangganLain = (int) $pdo->query(
    'SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan <> ' . $idPelanggan . ' AND status = \'aktif\' LIMIT 1'
)->fetchColumn();
// Pelanggan tanpa riwayat transaksi (aman untuk uji hapus).
$idPelangganKosong = (int) $pdo->query(
    'SELECT p.id_pelanggan FROM pelanggan AS p
     WHERE p.status = \'aktif\'
       AND p.id_pelanggan NOT IN (SELECT id_pelanggan FROM piutang)
       AND p.id_pelanggan NOT IN (SELECT id_pelanggan FROM users WHERE id_pelanggan IS NOT NULL)
     LIMIT 1'
)->fetchColumn();

echo "== A. Pengajuan pembayaran (D5 / DFD 4.4, 8.5) ==\n";

$idPiutang = PiutangService::buatPiutang($pdo, [
    'id_pelanggan' => $idPelanggan,
    'no_transaksi' => 'UJI-' . bin2hex(random_bytes(4)),
    'tanggal_transaksi' => date('Y-m-d'),
    'total_piutang' => 1000000,
    'jatuh_tempo' => date('Y-m-d', strtotime('+30 day')),
    'keterangan' => 'Uji otomatis',
]);

$hasil = PengajuanService::buat($pdo, $idPelanggan, $idUserPelanggan, [
    'id_piutang' => $idPiutang,
    'nominal' => 200000,
    'metode_pembayaran' => 'Transfer',
    'tanggal_pengajuan' => date('Y-m-d'),
    'nama_berkas_simpan' => str_repeat('a', 32) . '.png',
]);
cek('pengajuan baru tersimpan', $hasil['ok'], $hasil['pesan']);
$idPengajuan = (int) ($hasil['id_pengajuan'] ?? 0);

$piutang = sisaPiutang($pdo, $idPiutang);
cek('pengajuan menunggu tidak mengurangi saldo', (float) $piutang['sisa_piutang'] === 1000000.0, 'sisa=' . $piutang['sisa_piutang']);
cek('belum ada pembayaran resmi', hitungPembayaran($pdo, $idPiutang) === 0);

$hasil = PengajuanService::setujui($pdo, $idPengajuan, $idManajer);
cek('pengajuan disetujui', $hasil['ok'], $hasil['pesan']);
$piutang = sisaPiutang($pdo, $idPiutang);
cek('saldo berkurang setelah disetujui', (float) $piutang['sisa_piutang'] === 800000.0, 'sisa=' . $piutang['sisa_piutang']);
cek('tepat satu pembayaran resmi tercatat', hitungPembayaran($pdo, $idPiutang) === 1);

$hasil = PengajuanService::setujui($pdo, $idPengajuan, $idManajer);
cek('persetujuan ulang ditolak', !$hasil['ok'], $hasil['pesan']);
cek('tidak ada pembayaran ganda', hitungPembayaran($pdo, $idPiutang) === 1);

$hasil = PengajuanService::tolak($pdo, $idPengajuan, $idManajer, 'Sudah diproses');
cek('penolakan pada pengajuan yang sudah diproses ditolak', !$hasil['ok']);

$idPiutang2 = PiutangService::buatPiutang($pdo, [
    'id_pelanggan' => $idPelanggan,
    'no_transaksi' => 'UJI-' . bin2hex(random_bytes(4)),
    'tanggal_transaksi' => date('Y-m-d'),
    'total_piutang' => 500000,
    'jatuh_tempo' => date('Y-m-d', strtotime('+30 day')),
    'keterangan' => 'Uji otomatis',
]);
$hasil = PengajuanService::buat($pdo, $idPelanggan, $idUserPelanggan, [
    'id_piutang' => $idPiutang2,
    'nominal' => 300000,
    'metode_pembayaran' => 'Tunai',
    'tanggal_pengajuan' => date('Y-m-d'),
    'nama_berkas_simpan' => str_repeat('b', 32) . '.png',
]);
cek('pengajuan kedua tersimpan', $hasil['ok'], $hasil['pesan']);
$idPengajuan2 = (int) ($hasil['id_pengajuan'] ?? 0);

$hasil = PengajuanService::tolak($pdo, $idPengajuan2, $idManajer, 'Bukti tidak terbaca');
cek('pengajuan ditolak', $hasil['ok'], $hasil['pesan']);
$piutang = sisaPiutang($pdo, $idPiutang2);
cek('penolakan tidak mengurangi saldo', (float) $piutang['sisa_piutang'] === 500000.0, 'sisa=' . $piutang['sisa_piutang']);
cek('penolakan tidak membuat pembayaran', hitungPembayaran($pdo, $idPiutang2) === 0);
$stmt = $pdo->prepare('SELECT status, alasan_penolakan FROM pengajuan_pembayaran WHERE id_pengajuan = :id');
$stmt->execute([':id' => $idPengajuan2]);
$baris = $stmt->fetch();
cek('status pengajuan ditolak tersimpan', $baris['status'] === 'ditolak');
cek('alasan penolakan tersimpan', $baris['alasan_penolakan'] === 'Bukti tidak terbaca');

$hasil = PengajuanService::buat($pdo, $idPelanggan, $idUserPelanggan, [
    'id_piutang' => $idPiutang2,
    'nominal' => 9000000,
    'metode_pembayaran' => 'Transfer',
    'tanggal_pengajuan' => date('Y-m-d'),
    'nama_berkas_simpan' => str_repeat('c', 32) . '.png',
]);
cek('nominal melebihi sisa ditolak', !$hasil['ok'], $hasil['pesan']);

$idPiutangGanda = PiutangService::buatPiutang($pdo, [
    'id_pelanggan' => $idPelanggan,
    'no_transaksi' => 'UJI-' . bin2hex(random_bytes(4)),
    'tanggal_transaksi' => date('Y-m-d'),
    'total_piutang' => 250000,
    'jatuh_tempo' => date('Y-m-d', strtotime('+30 day')),
    'keterangan' => 'Uji otomatis',
]);
$hasil = PengajuanService::buat($pdo, $idPelanggan, $idUserPelanggan, [
    'id_piutang' => $idPiutangGanda,
    'nominal' => 1000,
    'metode_pembayaran' => 'Transfer',
    'tanggal_pengajuan' => date('Y-m-d'),
    'nama_berkas_simpan' => str_repeat('d', 32) . '.png',
]);
cek('pengajuan pertama untuk tagihan baru tersimpan', $hasil['ok'], $hasil['pesan']);
$hasil = PengajuanService::buat($pdo, $idPelanggan, $idUserPelanggan, [
    'id_piutang' => $idPiutangGanda,
    'nominal' => 1000,
    'metode_pembayaran' => 'Transfer',
    'tanggal_pengajuan' => date('Y-m-d'),
    'nama_berkas_simpan' => str_repeat('d', 32) . '.png',
]);
cek('pengajuan ganda untuk tagihan sama ditolak', !$hasil['ok'], $hasil['pesan']);

$idPiutangLain = PiutangService::buatPiutang($pdo, [
    'id_pelanggan' => $idPelangganLain,
    'no_transaksi' => 'UJI-' . bin2hex(random_bytes(4)),
    'tanggal_transaksi' => date('Y-m-d'),
    'total_piutang' => 100000,
    'jatuh_tempo' => date('Y-m-d', strtotime('+30 day')),
    'keterangan' => 'Uji otomatis',
]);
$hasil = PengajuanService::buat($pdo, $idPelanggan, $idUserPelanggan, [
    'id_piutang' => $idPiutangLain,
    'nominal' => 1000,
    'metode_pembayaran' => 'Transfer',
    'tanggal_pengajuan' => date('Y-m-d'),
    'nama_berkas_simpan' => str_repeat('e', 32) . '.png',
]);
cek('pengajuan atas tagihan orang lain ditolak', !$hasil['ok'], $hasil['pesan']);

echo "== B. Pembayaran resmi & pembatalan (D4 / DFD 4.2, 4.3) ==\n";

$idPembayaran = (int) $pdo->query('SELECT id_pembayaran FROM pembayaran WHERE id_piutang = ' . $idPiutang . ' LIMIT 1')->fetchColumn();
$hasil = PembayaranService::batalkanPembayaran($pdo, $idPembayaran, 'Salah input nominal');
cek('pembayaran dibatalkan', $hasil['ok'], $hasil['pesan']);
$piutang = sisaPiutang($pdo, $idPiutang);
cek('saldo kembali setelah pembatalan', (float) $piutang['sisa_piutang'] === 1000000.0, 'sisa=' . $piutang['sisa_piutang']);
cek('jumlah dibayar kembali nol', (float) $piutang['jumlah_dibayar'] === 0.0);
cek('catatan pembayaran tidak dihapus', hitungPembayaran($pdo, $idPiutang) === 1);
cek('pembayaran dibatalkan tidak dihitung sah', hitungTotalPembayaran($pdo, $idPiutang) === 0.0);

$stmt = $pdo->prepare('SELECT status, alasan_pembatalan FROM pembayaran WHERE id_pembayaran = :id');
$stmt->execute([':id' => $idPembayaran]);
$baris = $stmt->fetch();
cek('status pembayaran = dibatalkan', $baris['status'] === 'dibatalkan');
cek('alasan pembatalan tersimpan', $baris['alasan_pembatalan'] === 'Salah input nominal');

$hasil = PembayaranService::batalkanPembayaran($pdo, $idPembayaran, ' lagi');
cek('pembatalan kedua ditolak', !$hasil['ok'], $hasil['pesan']);

$hasil = PembayaranService::batalkanPembayaran($pdo, $idPembayaran, '');
cek('pembatalan tanpa alasan ditolak', !$hasil['ok'], $hasil['pesan']);

$hasil = PiutangService::hapusPiutang($pdo, $idPiutang);
cek('piutang dengan pembayaran tidak dapat dihapus', !$hasil['ok'], $hasil['pesan']);

echo "== C. Pelanggan & akun (D1, D2 / DFD 2.3, 7.2, 7.4) ==\n";

$hasil = PelangganService::hapusPelanggan($pdo, $idPelanggan);
cek('pelanggan dengan transaksi tidak dapat dihapus', !$hasil['ok'], $hasil['pesan']);

// Nonaktifkan pelanggan: akunnya harus ikut nonaktif (DFD 2.0 -> D1).
$stmt = $pdo->prepare('SELECT kode_pelanggan, nama_pelanggan, alamat, no_hp, email FROM pelanggan WHERE id_pelanggan = :id');
$stmt->execute([':id' => $idPelanggan]);
$dataPelanggan = $stmt->fetch();
PelangganService::ubahPelanggan($pdo, $idPelanggan, [
    'kode_pelanggan' => $dataPelanggan['kode_pelanggan'],
    'nama_pelanggan' => $dataPelanggan['nama_pelanggan'],
    'alamat' => $dataPelanggan['alamat'],
    'no_hp' => $dataPelanggan['no_hp'],
    'email' => $dataPelanggan['email'],
    'status' => 'nonaktif',
]);
$stmt = $pdo->prepare("SELECT status FROM users WHERE id_pelanggan = :id");
$stmt->execute([':id' => $idPelanggan]);
$statusAkun = (string) $stmt->fetchColumn();
cek('akun pelanggan ikut nonaktif', $statusAkun === 'nonaktif', 'status=' . $statusAkun);
cek('pelanggan nonaktif tidak bisa login', PenggunaService::autentikasi($pdo, 'budi', 'Pelanggan123!') === null);

// Pelanggan tanpa transaksi boleh dihapus.
$idPelangganUji = PelangganService::buatPelanggan($pdo, [
    'kode_pelanggan' => 'UJI-' . bin2hex(random_bytes(3)),
    'nama_pelanggan' => 'Pelanggan Uji Hapus',
    'alamat' => 'Alamat uji',
    'no_hp' => '081200000001',
    'email' => 'ujihapus@example.com',
    'status' => 'aktif',
]);
$hasil = PelangganService::hapusPelanggan($pdo, $idPelangganUji);
cek('pelanggan tanpa transaksi boleh dihapus', $hasil['ok'], $hasil['pesan']);

$idPelangganBaru = (int) $pdo->query(
    'SELECT id_pelanggan FROM pelanggan WHERE status = \'aktif\' AND id_pelanggan NOT IN (SELECT id_pelanggan FROM users WHERE id_pelanggan IS NOT NULL) LIMIT 1'
)->fetchColumn();

$usernameBaru = 'uji' . bin2hex(random_bytes(3));
$hasil = PenggunaService::buatAkunPelanggan($pdo, [
    'id_pelanggan' => $idPelangganBaru,
    'username' => $usernameBaru,
    'nama' => 'Uji Otomatis',
]);
cek('akun pelanggan dibuat', $hasil['ok'], $hasil['pesan']);
cek('kode aktivasi dibuat', !empty($hasil['token']));
$token = (string) $hasil['token'];

$stmt = $pdo->prepare('SELECT status FROM users WHERE username = :u');
$stmt->execute([':u' => $usernameBaru]);
cek('akun berstatus belum aktif', (string) $stmt->fetchColumn() === 'belum aktif');

$stmt = $pdo->prepare('SELECT id_user FROM users WHERE username = :u');
$stmt->execute([':u' => $usernameBaru]);
$idUserBaru = (int) $stmt->fetchColumn();
PenggunaService::resetKataSandi($pdo, $idUserBaru, 'SandiAwal123!');
cek('akun belum aktif tidak bisa login', PenggunaService::autentikasi($pdo, $usernameBaru, 'SandiAwal123!') === null);

$hasil = PenggunaService::aktivasi($pdo, $token, 'SandiBaru123!');
cek('aktivasi akun berhasil', $hasil['ok'], $hasil['pesan']);
cek('akun aktif bisa login', PenggunaService::autentikasi($pdo, $usernameBaru, 'SandiBaru123!') !== null);

$hasil = PenggunaService::aktivasi($pdo, $token, 'SandiBaru123!');
cek('kode aktivasi hanya bisa dipakai sekali', !$hasil['ok'], $hasil['pesan']);

echo "== D. Permintaan perubahan profil (D6 / DFD 2.4, 8.7) ==\n";

$stmt = $pdo->prepare('SELECT nama_pelanggan, alamat FROM pelanggan WHERE id_pelanggan = :id');
$stmt->execute([':id' => $idPelanggan]);
$profilAwal = $stmt->fetch();

$hasil = ProfilService::buat($pdo, $idPelanggan, $idUserPelanggan, [
    'nama_pelanggan' => 'Nama Baru Uji',
    'alamat' => 'Alamat Baru Uji',
    'no_hp' => '081200000000',
    'email' => 'uji@example.com',
], 'Pindah alamat');
cek('permintaan profil tersimpan', $hasil['ok'], $hasil['pesan']);
$idPermintaan = (int) ($hasil['id_permintaan'] ?? 0);

$stmt = $pdo->prepare('SELECT nama_pelanggan, alamat FROM pelanggan WHERE id_pelanggan = :id');
$stmt->execute([':id' => $idPelanggan]);
$profilSekarang = $stmt->fetch();
cek('profil aktif tidak berubah sebelum disetujui', $profilSekarang['nama_pelanggan'] === $profilAwal['nama_pelanggan']);

$hasil = ProfilService::setujui($pdo, $idPermintaan, $idManajer);
cek('permintaan profil disetujui', $hasil['ok'], $hasil['pesan']);
$stmt = $pdo->prepare('SELECT nama_pelanggan, alamat FROM pelanggan WHERE id_pelanggan = :id');
$stmt->execute([':id' => $idPelanggan]);
$profilBaru = $stmt->fetch();
cek('profil aktif berubah setelah disetujui', $profilBaru['nama_pelanggan'] === 'Nama Baru Uji');
cek('alamat profil ikut berubah', $profilBaru['alamat'] === 'Alamat Baru Uji');

$hasil = ProfilService::setujui($pdo, $idPermintaan, $idManajer);
cek('persetujuan ulang permintaan profil ditolak', !$hasil['ok'], $hasil['pesan']);

$hasil = ProfilService::buat($pdo, $idPelanggan, $idUserPelanggan, [
    'nama_pelanggan' => 'Nama Ditolak',
    'alamat' => $profilBaru['alamat'],
    'no_hp' => '081200000000',
    'email' => 'uji@example.com',
], 'Alasan uji');
$idPermintaan2 = (int) ($hasil['id_permintaan'] ?? 0);
$hasil = ProfilService::tolak($pdo, $idPermintaan2, $idManajer, 'Data tidak sesuai');
cek('permintaan profil ditolak', $hasil['ok'], $hasil['pesan']);
$stmt = $pdo->prepare('SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = :id');
$stmt->execute([':id' => $idPelanggan]);
cek('profil aktif tetap saat ditolak', (string) $stmt->fetchColumn() === 'Nama Baru Uji');
$stmt = $pdo->prepare('SELECT status, alasan_penolakan FROM permintaan_profil WHERE id_permintaan = :id');
$stmt->execute([':id' => $idPermintaan2]);
$baris = $stmt->fetch();
cek('alasan penolakan profil tersimpan', $baris['alasan_penolakan'] === 'Data tidak sesuai');

echo "== E. Status jatuh tempo & konsistensi saldo ==\n";

$idPiutangTempo = PiutangService::buatPiutang($pdo, [
    'id_pelanggan' => $idPelanggan,
    'no_transaksi' => 'UJI-' . bin2hex(random_bytes(4)),
    'tanggal_transaksi' => date('Y-m-d', strtotime('-60 day')),
    'total_piutang' => 750000,
    'jatuh_tempo' => date('Y-m-d', strtotime('-10 day')),
    'keterangan' => 'Uji otomatis',
]);
$piutang = sisaPiutang($pdo, $idPiutangTempo);
cek('piutang lewat tanggal terdeteksi jatuh tempo', $piutang['status'] === 'Jatuh Tempo', 'status=' . $piutang['status']);

$idPiutangLunas = PiutangService::buatPiutang($pdo, [
    'id_pelanggan' => $idPelanggan,
    'no_transaksi' => 'UJI-' . bin2hex(random_bytes(4)),
    'tanggal_transaksi' => date('Y-m-d', strtotime('-60 day')),
    'total_piutang' => 400000,
    'jatuh_tempo' => date('Y-m-d', strtotime('-10 day')),
    'keterangan' => 'Uji otomatis',
]);
PembayaranService::buatPembayaran($pdo, [
    'id_piutang' => $idPiutangLunas,
    'tanggal_pembayaran' => date('Y-m-d'),
    'jumlah_bayar' => 400000,
    'metode_pembayaran' => 'Transfer',
    'keterangan' => 'Uji otomatis',
]);
$piutang = sisaPiutang($pdo, $idPiutangLunas);
cek('piutang lunas tidak ditandai jatuh tempo', $piutang['status'] === 'Lunas', 'status=' . $piutang['status']);

echo "\n== TOTAL: $total pemeriksaan, $gagal gagal ==\n";
exit($gagal > 0 ? 1 : 0);
