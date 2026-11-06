<?php

declare(strict_types=1);

/**
 * Halaman: Buat Akun Pelanggan (DFD 7.2)
 * Fungsi: Manajer membuat akun pelanggan dan menyiapkan aktivasi.
 * Akun disimpan dengan status "belum aktif" beserta kode aktivasi.
 * Pelanggan sendiri yang menetapkan kata sandi (DFD 7.4).
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$pdo = koneksiDatabase();
$daftarPelanggan = PenggunaService::pelangganTanpaAkun($pdo);
$kesalahan = [];

$idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
$username = '';
$nama = '';
$tokenAktivasi = '';
$tokenBerakhir = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = bersihkan($_POST['username'] ?? '');
    $nama = bersihkan($_POST['nama'] ?? '');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    if ($idPelanggan <= 0 || PelangganService::ambil($pdo, $idPelanggan) === null) {
        $kesalahan[] = 'Pelanggan wajib dipilih dan harus terdaftar.';
    }

    validasiWajib($username, 'Username', $kesalahan);
    validasiWajib($nama, 'Nama akun', $kesalahan);

    if ($kesalahan === [] && PenggunaService::pelangganPunyaAkun($pdo, $idPelanggan)) {
        $kesalahan[] = 'Pelanggan ini sudah memiliki akun.';
    }

    if ($kesalahan === [] && !PenggunaService::usernameTersedia($pdo, $username)) {
        $kesalahan[] = 'Username sudah digunakan.';
    }

    if ($kesalahan === []) {
        $hasil = PenggunaService::buatAkunPelanggan($pdo, [
            'id_pelanggan' => $idPelanggan,
            'username' => $username,
            'nama' => $nama,
        ]);

        if ($hasil['ok']) {
            setFlash('success', $hasil['pesan'] . ' Sampaikan kode aktivasi kepada pelanggan.');
            redirect(BASE_URL . 'akun-pelanggan/index.php');
        }

        $kesalahan[] = $hasil['pesan'];
    }
}

$judul_halaman = 'Buat Akun Pelanggan';
$sub_judul_halaman = 'Hubungkan akun dengan data pelanggan';
$halaman_aktif = 'akun-pelanggan';

require_once BASE_PATH . '/includes/kepala.php';
?>

<div class="kotak-form">
    <div class="aksi-halaman">
        <a class="btn btn-netral btn-kecil" href="<?= BASE_URL ?>akun-pelanggan/index.php">Kembali</a>
    </div>
    <h2>Formulir Akun Pelanggan</h2>
    <p class="teks-kecil mb-lg">
        Satu pelanggan hanya dapat memiliki satu akun. Akun dibuat berstatus <strong>belum aktif</strong>:
        pelanggan menerima kode aktivasi dan menetapkan kata sandi sendiri lewat halaman aktivasi.
    </p>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($daftarPelanggan === []): ?>
        <div class="keadaan-kosong">
            <h3>Tidak Ada Pelanggan Tanpa Akun</h3>
            <p>Semua pelanggan aktif sudah punya akun.</p>
            <a class="btn btn-primer" href="<?= BASE_URL ?>pelanggan/tambah.php">Tambah Pelanggan</a>
        </div>
    <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>akun-pelanggan/tambah.php" data-anti-ganda>
            <?= fieldCsrf() ?>
            <div class="form-grid">
                <div class="kolom" style="grid-column:1 / -1;">
                    <label for="id_pelanggan">Pelanggan *</label>
                    <select class="pilih" id="id_pelanggan" name="id_pelanggan" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php foreach ($daftarPelanggan as $p): ?>
                            <option value="<?= (int) $p['id_pelanggan'] ?>" <?= $idPelanggan === (int) $p['id_pelanggan'] ? 'selected' : '' ?>><?= e($p['nama_pelanggan']) ?> (<?= e($p['kode_pelanggan']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="kolom">
                    <label for="username">Username *</label>
                    <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="off" required>
                </div>
                <div class="kolom">
                    <label for="nama">Nama Akun *</label>
                    <input class="input" type="text" id="nama" name="nama" value="<?= e($nama) ?>" required>
                    <span class="bantuan">Sama dengan nama pelanggan</span>
                </div>
            </div>
            <div class="form-aksi">
                <button type="submit" class="btn btn-primer">Simpan Akun &amp; Buat Kode Aktivasi</button>
                <a class="btn btn-netral" href="<?= BASE_URL ?>akun-pelanggan/index.php">Batal</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
