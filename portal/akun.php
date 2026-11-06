<?php

declare(strict_types=1);

/**
 * Halaman: Akun Saya (portal pelanggan)
 * Fungsi: Menampilkan data akun pelanggan yang sedang login.
 * Penggantian kata sandi dilakukan di halaman "Akun Saya" (DFD 1.4).
 */

require_once __DIR__ . '/../includes/init.php';
requirePelanggan();

$judul_halaman = 'Akun Saya';
$sub_judul_halaman = 'Informasi akun dan keamanan';
$halaman_aktif = 'portal-akun';

require_once BASE_PATH . '/includes/kepala.php';

$pdo = koneksiDatabase();
$idPelanggan = idPelangganUser();
$profil = PortalService::profil($pdo, $idPelanggan);

if ($profil === null) {
    redirect(BASE_URL . 'error/404.php');
}

$akun = userSaatIni();
?>

<section class="kartu mb-lg" aria-label="Profil akun">
    <div class="kartu-judul">
        <div>
            <h3>Profil</h3>
            <p class="teks-kecil">Ajukan perubahan; berlaku setelah disetujui manajer.</p>
        </div>
        <a class="btn btn-sekunder btn-kecil" href="<?= BASE_URL ?>portal/profil.php">Ajukan Perubahan Profil</a>
    </div>
    <div class="form-grid" style="margin:0;">
        <div><span class="teks-kecil">Nama Akun</span><p><?= e((string) $akun['nama']) ?></p></div>
        <div><span class="teks-kecil">Username</span><p><?= e((string) $akun['username']) ?></p></div>
        <div><span class="teks-kecil">Pelanggan</span><p><?= e((string) $profil['nama_pelanggan']) ?> (<?= e((string) $profil['kode_pelanggan']) ?>)</p></div>
        <div><span class="teks-kecil">Email</span><p><?= e((string) ($profil['email'] ?? '-')) ?></p></div>
        <div><span class="teks-kecil">No. HP</span><p><?= e((string) ($profil['no_hp'] ?? '-')) ?></p></div>
        <div><span class="teks-kecil">Alamat</span><p><?= e((string) ($profil['alamat'] ?? '-')) ?></p></div>
    </div>
</section>

<div class="kartu kartu-sempit" aria-label="Keamanan akun">
    <div class="kartu-judul">
        <h3>Keamanan</h3>
    </div>
    <p class="teks-kecil mb-lg">Ganti kata sandi secara berkala.</p>
    <a class="btn btn-primer" href="<?= BASE_URL ?>akun/sandi.php">Ganti Kata Sandi</a>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
