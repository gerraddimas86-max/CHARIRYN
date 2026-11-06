<?php

declare(strict_types=1);

/**
 * Halaman: Tambah Pelanggan
 * Fungsi: Menyimpan data pelanggan baru
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$judul_halaman = 'Tambah Pelanggan';
$sub_judul_halaman = 'Lengkapi data pelanggan baru';
$halaman_aktif = 'pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();
$kesalahan = [];

$kode = '';
$nama = '';
$alamat = '';
$noHp = '';
$email = '';
$status = 'aktif';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = bersihkan($_POST['kode_pelanggan'] ?? '');
    $nama = bersihkan($_POST['nama_pelanggan'] ?? '');
    $alamat = bersihkan($_POST['alamat'] ?? '');
    $noHp = bersihkan($_POST['no_hp'] ?? '');
    $email = bersihkan($_POST['email'] ?? '');
    $status = bersihkan($_POST['status'] ?? 'aktif');

    if (!cekTokenCsrf($_POST['token_csrf'] ?? null)) {
        $kesalahan[] = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    }

    validasiWajib($kode, 'Kode pelanggan', $kesalahan);
    validasiWajib($nama, 'Nama pelanggan', $kesalahan);
    validasiEmail($email, 'Email', $kesalahan);

    if ($status !== 'aktif' && $status !== 'nonaktif') {
        $kesalahan[] = 'Status pelanggan tidak valid.';
    }

    if ($kesalahan === [] && !PelangganService::kodeTersedia($pdo, $kode)) {
        $kesalahan[] = 'Kode pelanggan sudah digunakan.';
    }

    if ($kesalahan === []) {
        PelangganService::buatPelanggan($pdo, [
            'kode_pelanggan' => $kode,
            'nama_pelanggan' => $nama,
            'alamat' => $alamat,
            'no_hp' => $noHp,
            'email' => $email,
            'status' => $status,
        ]);
        setFlash('success', 'Data pelanggan berhasil ditambahkan.');
        redirect(BASE_URL . 'pelanggan/index.php');
    }
}
?>

<div class="kotak-form">
    <h2>Formulir Pelanggan Baru</h2>
    <p class="teks-kecil" style="margin-bottom:var(--spasi-lg);">Kolom bertanda * wajib diisi.</p>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>pelanggan/tambah.php" data-anti-ganda>
        <?= fieldCsrf() ?>
        <div class="form-grid">
            <div class="kolom">
                <label for="kode_pelanggan">Kode Pelanggan *</label>
                <input class="input" type="text" id="kode_pelanggan" name="kode_pelanggan" value="<?= e($kode) ?>" placeholder="Contoh: PLG-011" required>
                <span class="bantuan">Bersifat unik</span>
            </div>
            <div class="kolom">
                <label for="nama_pelanggan">Nama Pelanggan *</label>
                <input class="input" type="text" id="nama_pelanggan" name="nama_pelanggan" value="<?= e($nama) ?>" placeholder="Contoh: Toko Sejahtera" required>
            </div>
            <div class="kolom">
                <label for="no_hp">Nomor HP</label>
                <input class="input" type="text" id="no_hp" name="no_hp" value="<?= e($noHp) ?>" placeholder="Contoh: 081234567890">
            </div>
            <div class="kolom">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="Contoh: nama@perusahaan.com">
            </div>
            <div class="kolom">
                <label for="status">Status</label>
                <select class="pilih" id="status" name="status">
                    <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="kolom" style="grid-column:1 / -1;">
                <label for="alamat">Alamat</label>
                <textarea class="textarea" id="alamat" name="alamat" placeholder="Alamat lengkap pelanggan"><?= e($alamat) ?></textarea>
            </div>
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn btn-primer">Simpan Data</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>pelanggan/index.php">Batal</a>
        </div>
    </form>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>