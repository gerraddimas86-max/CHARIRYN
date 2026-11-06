<?php

declare(strict_types=1);

/**
 * Sidebar navigasi utama (menu pengguna disesuaikan dengan role)
 */

function ikonMenu(string $nama): string
{
    $svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    $isi = match ($nama) {
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect>',
        'pelanggan' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'pengguna' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'piutang' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line>',
        'pembayaran' => '<rect x="2" y="6" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path><circle cx="16" cy="15" r="2"></circle>',
        'jatuh-tempo' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'cari' => '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>',
        'laporan' => '<line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line>',
        'pengajuan' => '<path d="M9 3h6a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h1V5a2 2 0 0 1 2-2z"></path><path d="M9 13l2 2 4-4"></path>',
        'permintaan' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"></path>',
        'keluar' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>',
        default => '',
    };

    return $svg . $isi . '</svg>';
}

function itemMenu(string $ikon, string $label, string $url, string $kunci): string
{
    global $halaman_aktif;

    $aktif = $halaman_aktif === $kunci;
    $aria = $aktif ? ' aria-current="page"' : '';

    return '<a class="item-menu' . ($aktif ? ' aktif' : '') . '"' . $aria . ' href="' . e($url) . '">'
        . ikonMenu($ikon)
        . '<span>' . e($label) . '</span></a>';
}
?>
<?php
$peran_sidebar = roleUser();
$beranda_sidebar = urlBerandaSesuaiPeran();
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="<?= e($beranda_sidebar) ?>" aria-label="Beranda CHARIRYN">
            <img class="logo-brand" src="<?= BASE_URL ?>assets/img/chariryn.png" alt="CHARIRYN" width="336" height="112">
            <img class="logo-mark" src="<?= BASE_URL ?>assets/img/logo.png" alt="CHARIRYN" width="40" height="40">
        </a>
        <button type="button" class="sidebar-toggle" id="tombolMenu" aria-label="Tutup panel samping" aria-expanded="false" aria-controls="sidebar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>

    <nav class="sidebar-menu" aria-label="Menu utama">
        <?php if ($peran_sidebar === 'pelanggan'): ?>
            <p class="grup-menu">Piutang Saya</p>
            <?= itemMenu('dashboard', 'Dashboard', BASE_URL . 'portal/index.php', 'portal') ?>
            <?= itemMenu('pembayaran', 'Ajukan Pembayaran', BASE_URL . 'portal/pengajuan.php', 'portal-pengajuan') ?>
            <?= itemMenu('pengajuan', 'Status Pengajuan', BASE_URL . 'portal/status-pengajuan.php', 'portal-status') ?>
            <?= itemMenu('permintaan', 'Ubah Profil', BASE_URL . 'portal/profil.php', 'portal-profil') ?>
            <?= itemMenu('pengguna', 'Akun Saya', BASE_URL . 'portal/akun.php', 'portal-akun') ?>
        <?php elseif ($peran_sidebar === 'pemilik'): ?>
            <p class="grup-menu">Laporan</p>
            <?= itemMenu('laporan', 'Laporan Piutang', BASE_URL . 'laporan/piutang.php', 'laporan-piutang') ?>
            <?= itemMenu('pembayaran', 'Laporan Pembayaran', BASE_URL . 'laporan/pembayaran.php', 'laporan-pembayaran') ?>
            <?= itemMenu('jatuh-tempo', 'Laporan Jatuh Tempo', BASE_URL . 'laporan/jatuh-tempo.php', 'laporan-jatuh-tempo') ?>
            <?= itemMenu('pelanggan', 'Laporan Pelanggan', BASE_URL . 'laporan/pelanggan.php', 'laporan-pelanggan') ?>

            <p class="grup-menu">Akun</p>
            <?= itemMenu('pengguna', 'Akun Saya', BASE_URL . 'akun/sandi.php', 'akun') ?>
        <?php else: ?>
            <p class="grup-menu">Dashboard</p>
            <?= itemMenu('dashboard', 'Dashboard', BASE_URL . 'dashboard/index.php', 'dashboard') ?>

            <p class="grup-menu">Data Master</p>
            <?= itemMenu('pelanggan', 'Pelanggan', BASE_URL . 'pelanggan/index.php', 'pelanggan') ?>
            <?= itemMenu('permintaan', 'Permintaan Profil', BASE_URL . 'pelanggan/permintaan.php', 'permintaan-profil') ?>

            <p class="grup-menu">Transaksi</p>
            <?= itemMenu('piutang', 'Piutang', BASE_URL . 'piutang/index.php', 'piutang') ?>
            <?= itemMenu('pembayaran', 'Pembayaran', BASE_URL . 'pembayaran/index.php', 'pembayaran') ?>
            <?= itemMenu('pengajuan', 'Pengajuan', BASE_URL . 'pengajuan/index.php', 'pengajuan') ?>
            <?= itemMenu('cari', 'Cari & Pantau', BASE_URL . 'cari-pantau/index.php', 'cari-pantau') ?>
            <?= itemMenu('jatuh-tempo', 'Jatuh Tempo', BASE_URL . 'jatuh-tempo/index.php', 'jatuh-tempo') ?>

            <p class="grup-menu">Akun</p>
            <?= itemMenu('pengguna', 'Akun Pelanggan', BASE_URL . 'akun-pelanggan/index.php', 'akun-pelanggan') ?>
            <?= itemMenu('pengguna', 'Akun Saya', BASE_URL . 'akun/sandi.php', 'akun') ?>

            <p class="grup-menu">Laporan</p>
            <?= itemMenu('laporan', 'Laporan Piutang', BASE_URL . 'laporan/piutang.php', 'laporan-piutang') ?>
            <?= itemMenu('pembayaran', 'Laporan Pembayaran', BASE_URL . 'laporan/pembayaran.php', 'laporan-pembayaran') ?>
            <?= itemMenu('jatuh-tempo', 'Laporan Jatuh Tempo', BASE_URL . 'laporan/jatuh-tempo.php', 'laporan-jatuh-tempo') ?>
            <?= itemMenu('pelanggan', 'Laporan Pelanggan', BASE_URL . 'laporan/pelanggan.php', 'laporan-pelanggan') ?>
        <?php endif; ?>
    </nav>

<?php
$nama_profil = (string) (userSaatIni()['nama'] ?? 'Pengguna');
$label_role_profil = labelRole($peran_sidebar);
?>
    <div class="sidebar-kaki">
        <div class="profil-ringkas">
            <img class="profil-avatar" src="<?= BASE_URL ?>assets/img/logo.png" alt="" aria-hidden="true" width="36" height="36">
            <div class="profil-info">
                <span class="nama-user"><?= e($nama_profil) ?></span>
                <span class="role-user"><?= e($label_role_profil) ?></span>
            </div>
        </div>
        <form method="post" action="<?= BASE_URL ?>auth/logout.php">
            <?= fieldCsrf() ?>
            <button type="submit" class="tombol-keluar">
                <?= ikonMenu('keluar') ?>
                <span>Keluar</span>
            </button>
        </form>
    </div>
</aside>
<div class="lapisan" id="lapisanSidebar" hidden></div>
