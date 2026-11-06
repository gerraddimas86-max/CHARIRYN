<?php

declare(strict_types=1);

/**
 * Halaman: Edit Pelanggan
 * Fungsi: Memperbarui data pelanggan
 */

require_once __DIR__ . '/../includes/init.php';
requireManajer();

$id = ambilIdDariGet();
if ($id === null) {
    redirect(BASE_URL . 'error/404.php');
}

$pdo = koneksiDatabase();
$pelanggan = PelangganService::ambil($pdo, $id);

if ($pelanggan === null) {
    redirect(BASE_URL . 'error/404.php');
}

$judul_halaman = 'Edit Pelanggan';
$sub_judul_halaman = 'Perbarui data pelanggan';
$halaman_aktif = 'pelanggan';

require_once BASE_PATH . '/includes/kepala.php';

$kesalahan = [];

$kode = (string) $pelanggan['kode_pelanggan'];
$nama = (string) $pelanggan['nama_pelanggan'];
$alamat = (string) ($pelanggan['alamat'] ?? '');
$noHp = (string) ($pelanggan['no_hp'] ?? '');
$email = (string) ($pelanggan['email'] ?? '');
$status = (string) $pelanggan['status'];

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

    if ($kesalahan === [] && !PelangganService::kodeTersedia($pdo, $kode, $id)) {
        $kesalahan[] = 'Kode pelanggan sudah digunakan.';
    }

    if ($kesalahan === []) {
        PelangganService::ubahPelanggan($pdo, $id, [
            'kode_pelanggan' => $kode,
            'nama_pelanggan' => $nama,
            'alamat' => $alamat,
            'no_hp' => $noHp,
            'email' => $email,
            'status' => $status,
        ]);
        setFlash('success', 'Data pelanggan berhasil diperbarui.');
        redirect(BASE_URL . 'pelanggan/detail.php?id=' . $id);
    }
}
?>

<div class="kotak-form">
    <div class="aksi-halaman" style="margin-bottom:var(--spasi-md);">
        <a class="btn btn-netral btn-kecil" href="<?= BASE_URL ?>pelanggan/detail.php?id=<?= (int) $id ?>">Kembali</a>
    </div>
    <h2>Formulir Edit Pelanggan</h2>

    <?php if ($kesalahan !== []): ?>
        <ul class="pesan-kesalahan" role="alert">
            <?php foreach ($kesalahan as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>pelanggan/edit.php?id=<?= (int) $id ?>" data-anti-ganda>
        <?= fieldCsrf() ?>
        <div class="form-grid">
            <div class="kolom">
                <label for="kode_pelanggan">Kode Pelanggan *</label>
                <input class="input" type="text" id="kode_pelanggan" name="kode_pelanggan" value="<?= e($kode) ?>" required>
                <span class="bantuan">Bersifat unik</span>
            </div>
            <div class="kolom">
                <label for="nama_pelanggan">Nama Pelanggan *</label>
                <input class="input" type="text" id="nama_pelanggan" name="nama_pelanggan" value="<?= e($nama) ?>" required>
            </div>
            <div class="kolom">
                <label for="no_hp">Nomor HP</label>
                <input class="input" type="text" id="no_hp" name="no_hp" value="<?= e($noHp) ?>">
            </div>
            <div class="kolom">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>">
            </div>
            <div class="kolom">
                <label for="status">Status</label>
                <select class="pilih" id="status" name="status">
                    <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
                <span class="bantuan">Nonaktif = tanpa menghapus riwayat</span>
            </div>
            <div class="kolom" style="grid-column:1 / -1;">
                <label for="alamat">Alamat</label>
                <textarea class="textarea" id="alamat" name="alamat"><?= e($alamat) ?></textarea>
            </div>
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn btn-primer">Perbarui Data</button>
            <a class="btn btn-netral" href="<?= BASE_URL ?>pelanggan/detail.php?id=<?= (int) $id ?>">Batal</a>
        </div>
    </form>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>