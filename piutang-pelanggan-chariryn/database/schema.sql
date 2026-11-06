-- =============================================================
-- Sistem Informasi Pengelolaan Data Piutang Pelanggan
-- Database : piutang_pelanggan
-- Engine   : InnoDB
-- Charset  : utf8mb4
-- =============================================================

CREATE DATABASE IF NOT EXISTS piutang_pelanggan
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE piutang_pelanggan;

-- -------------------------------------------------------------
-- Tabel: pelanggan
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pelanggan (
    id_pelanggan INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode_pelanggan VARCHAR(20) NOT NULL,
    nama_pelanggan VARCHAR(100) NOT NULL,
    alamat TEXT NULL,
    no_hp VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pelanggan),
    UNIQUE KEY uq_pelanggan_kode (kode_pelanggan),
    KEY idx_pelanggan_nama (nama_pelanggan),
    KEY idx_pelanggan_status (status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel: users (akun sistem: pemilik, manajer, pelanggan)
-- Akun pelanggan wajib terhubung ke satu baris pelanggan (D1 -> D2).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id_user INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pelanggan INT UNSIGNED NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('pemilik', 'manajer', 'pelanggan') NOT NULL DEFAULT 'manajer',
    status ENUM('belum aktif', 'aktif', 'nonaktif') NOT NULL DEFAULT 'belum aktif',
    token_aktivasi VARCHAR(64) NULL,
    token_aktivasi_berakhir DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_user),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_pelanggan (id_pelanggan),
    KEY idx_users_role_status (role, status),
    CONSTRAINT fk_users_pelanggan
        FOREIGN KEY (id_pelanggan) REFERENCES pelanggan (id_pelanggan)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel: piutang
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS piutang (
    id_piutang INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pelanggan INT UNSIGNED NOT NULL,
    no_transaksi VARCHAR(30) NOT NULL,
    tanggal_transaksi DATE NOT NULL,
    total_piutang DECIMAL(15,2) NOT NULL,
    jumlah_dibayar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sisa_piutang DECIMAL(15,2) NOT NULL,
    jatuh_tempo DATE NOT NULL,
    status ENUM('Belum Lunas', 'Sebagian', 'Lunas', 'Jatuh Tempo') NOT NULL DEFAULT 'Belum Lunas',
    keterangan TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_piutang),
    UNIQUE KEY uq_piutang_no_transaksi (no_transaksi),
    KEY idx_piutang_pelanggan (id_pelanggan),
    KEY idx_piutang_tanggal (tanggal_transaksi),
    KEY idx_piutang_jatuh_tempo (jatuh_tempo),
    KEY idx_piutang_status (status),
    KEY idx_piutang_status_tempo (status, jatuh_tempo),
    CONSTRAINT fk_piutang_pelanggan
        FOREIGN KEY (id_pelanggan) REFERENCES pelanggan (id_pelanggan)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel: pembayaran
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pembayaran (
    id_pembayaran INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_piutang INT UNSIGNED NOT NULL,
    tanggal_pembayaran DATE NOT NULL,
    jumlah_bayar DECIMAL(15,2) NOT NULL,
    metode_pembayaran ENUM('Tunai', 'Transfer', 'E-Wallet') NOT NULL,
    keterangan TEXT NULL,
    status ENUM('berlaku', 'dibatalkan') NOT NULL DEFAULT 'berlaku',
    alasan_pembatalan TEXT NULL,
    dibatalkan_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pembayaran),
    KEY idx_pembayaran_piutang (id_piutang),
    KEY idx_pembayaran_tanggal (tanggal_pembayaran),
    KEY idx_pembayaran_status (status),
    CONSTRAINT fk_pembayaran_piutang
        FOREIGN KEY (id_piutang) REFERENCES piutang (id_piutang)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel: pengajuan_pembayaran (D5)
-- Pengajuan pelanggan yang menunggu verifikasi tidak mengurangi
-- saldo piutang. Pembayaran resmi dibuat saat pengajuan disetujui.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengajuan_pembayaran (
    id_pengajuan INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_piutang INT UNSIGNED NOT NULL,
    id_pelanggan INT UNSIGNED NOT NULL,
    id_user INT UNSIGNED NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    tanggal_pengajuan DATE NOT NULL,
    metode_pembayaran ENUM('Tunai', 'Transfer', 'E-Wallet') NOT NULL DEFAULT 'Transfer',
    bukti_pembayaran VARCHAR(255) NULL,
    catatan TEXT NULL,
    status ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    alasan_penolakan TEXT NULL,
    id_pembayaran INT UNSIGNED NULL,
    id_user_verifikasi INT UNSIGNED NULL,
    tanggal_verifikasi DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pengajuan),
    UNIQUE KEY uq_pengajuan_pembayaran (id_pembayaran),
    KEY idx_pengajuan_pelanggan (id_pelanggan),
    KEY idx_pengajuan_piutang (id_piutang),
    KEY idx_pengajuan_status (status),
    KEY idx_pengajuan_tanggal (tanggal_pengajuan),
    CONSTRAINT fk_pengajuan_piutang
        FOREIGN KEY (id_piutang) REFERENCES piutang (id_piutang)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pengajuan_pelanggan
        FOREIGN KEY (id_pelanggan) REFERENCES pelanggan (id_pelanggan)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pengajuan_user
        FOREIGN KEY (id_user) REFERENCES users (id_user)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pengajuan_pembayaran
        FOREIGN KEY (id_pembayaran) REFERENCES pembayaran (id_pembayaran)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pengajuan_verifikasi
        FOREIGN KEY (id_user_verifikasi) REFERENCES users (id_user)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel: permintaan_profil (D6)
-- Profil aktif tidak berubah sebelum Manajer menyetujui.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS permintaan_profil (
    id_permintaan INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pelanggan INT UNSIGNED NOT NULL,
    id_user INT UNSIGNED NOT NULL,
    data_baru TEXT NOT NULL,
    alasan TEXT NOT NULL,
    status ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    alasan_penolakan TEXT NULL,
    id_user_verifikasi INT UNSIGNED NULL,
    tanggal_verifikasi DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_permintaan),
    KEY idx_permintaan_pelanggan (id_pelanggan),
    KEY idx_permintaan_status (status),
    KEY idx_permintaan_tanggal (created_at),
    CONSTRAINT fk_permintaan_pelanggan
        FOREIGN KEY (id_pelanggan) REFERENCES pelanggan (id_pelanggan)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_permintaan_user
        FOREIGN KEY (id_user) REFERENCES users (id_user)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_permintaan_verifikasi
        FOREIGN KEY (id_user_verifikasi) REFERENCES users (id_user)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
