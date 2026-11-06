-- =============================================================
-- Data Awal (Seed) - Sistem Informasi Pengelolaan Data Piutang
-- Password hash dibuat dengan password_hash() (bcrypt).
-- Akun demo:
--   pemilik   / Admin123!
--   manajer   / Manajer123!
--   pelanggan / Pelanggan123!  (terhubung ke PLG-001)
-- =============================================================

USE piutang_pelanggan;

SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- Pelanggan
-- -------------------------------------------------------------
INSERT INTO pelanggan (id_pelanggan, kode_pelanggan, nama_pelanggan, alamat, no_hp, email, status) VALUES
(1, 'PLG-001', 'Budi Santoso', 'Jl. Mawar No. 12, Jakarta Pusat', '081234567801', 'budi.santoso@example.com', 'aktif'),
(2, 'PLG-002', 'Siti Rahayu', 'Jl. Melati No. 45, Bandung', '081234567802', 'siti.rahayu@example.com', 'aktif'),
(3, 'PLG-003', 'Agus Wijaya', 'Jl. Kenanga No. 8, Semarang', '081234567803', 'agus.wijaya@example.com', 'aktif'),
(4, 'PLG-004', 'Dewi Lestari', 'Jl. Anggrek No. 21, Surabaya', '081234567804', 'dewi.lestari@example.com', 'aktif'),
(5, 'PLG-005', 'Rina Marlina', 'Jl. Cempaka No. 33, Yogyakarta', '081234567805', 'rina.marlina@example.com', 'aktif'),
(6, 'PLG-006', 'Dedi Kurniawan', 'Jl. Dahlia No. 17, Medan', '081234567806', 'dedi.kurniawan@example.com', 'aktif'),
(7, 'PLG-007', 'Fitri Handayani', 'Jl. Teratai No. 5, Makassar', '081234567807', 'fitri.handayani@example.com', 'aktif'),
(8, 'PLG-008', 'Hendra Gunawan', 'Jl. Flamboyan No. 29, Palembang', '081234567808', 'hendra.gunawan@example.com', 'aktif'),
(9, 'PLG-009', 'Maya Putri', 'Jl. Sawo No. 3, Bekasi', '081234567809', 'maya.putri@example.com', 'aktif'),
(10, 'PLG-010', 'Eko Prasetyo', 'Jl. Nangka No. 14, Denpasar', '081234567810', 'eko.prasetyo@example.com', 'nonaktif');

-- -------------------------------------------------------------
-- Pengguna (D1) - akun pelanggan wajib terhubung ke D2
-- -------------------------------------------------------------
INSERT INTO users (id_user, id_pelanggan, username, password, nama, role, status) VALUES
(1, NULL, 'pemilik', '$2y$10$i959cThrUyUJ0zSP.oYzr.Eo0cUVRTIblcibtqtjLgR5dZlmDhVsO', 'Pemilik Toko', 'pemilik', 'aktif'),
(2, NULL, 'manajer', '$2y$12$SMd6EfJnQtF6wwfEszQ3luTCPqIsFLnKptdspbYJeZCllH7vcQxJ2', 'Manajer Operasional', 'manajer', 'aktif'),
(3, 1, 'budi', '$2y$12$efRa5ROcV8Q6PhuyWjjfhuvbJZAmPenjUykEbnT/NtxHai0VD5cM6', 'Budi Santoso', 'pelanggan', 'aktif');

-- -------------------------------------------------------------
-- Piutang
-- -------------------------------------------------------------
INSERT INTO piutang (id_piutang, id_pelanggan, no_transaksi, tanggal_transaksi, total_piutang, jumlah_dibayar, sisa_piutang, jatuh_tempo, status, keterangan) VALUES
(1, 1, 'TRX-0001', DATE_SUB(CURDATE(), INTERVAL 45 DAY), 1000000.00, 1000000.00, 0.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Lunas', 'Penjualan kredit bahan bangunan'),
(2, 2, 'TRX-0002', DATE_SUB(CURDATE(), INTERVAL 40 DAY), 2000000.00, 500000.00, 1500000.00, DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'Sebagian', 'Penjualan kredit sembako'),
(3, 3, 'TRX-0003', DATE_SUB(CURDATE(), INTERVAL 60 DAY), 1000000.00, 300000.00, 700000.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'Jatuh Tempo', 'Penjualan kredit alat tulis'),
(4, 4, 'TRX-0004', DATE_SUB(CURDATE(), INTERVAL 70 DAY), 1000000.00, 0.00, 1000000.00, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Jatuh Tempo', 'Penjualan kredit perlengkapan toko'),
(5, 5, 'TRX-0005', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 750000.00, 0.00, 750000.00, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Belum Lunas', 'Penjualan kredit elektronik'),
(6, 1, 'TRX-0006', DATE_SUB(CURDATE(), INTERVAL 15 DAY), 3500000.00, 1000000.00, 2500000.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Sebagian', 'Penjualan kredit material'),
(7, 6, 'TRX-0007', DATE_SUB(CURDATE(), INTERVAL 50 DAY), 2500000.00, 1500000.00, 1000000.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Jatuh Tempo', 'Penjualan kredit furnitur'),
(8, 7, 'TRX-0008', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 1500000.00, 0.00, 1500000.00, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'Belum Lunas', 'Penjualan kredit elektronik'),
(9, 2, 'TRX-0009', DATE_SUB(CURDATE(), INTERVAL 55 DAY), 1250000.00, 1250000.00, 0.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Lunas', 'Penjualan kredit sembako'),
(10, 8, 'TRX-0010', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 4000000.00, 1000000.00, 3000000.00, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Sebagian', 'Penjualan kredit mesin'),
(11, 9, 'TRX-0011', DATE_SUB(CURDATE(), INTERVAL 25 DAY), 900000.00, 900000.00, 0.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Lunas', 'Penjualan kredit sembako'),
(12, 10, 'TRX-0012', DATE_SUB(CURDATE(), INTERVAL 30 DAY), 600000.00, 0.00, 600000.00, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Belum Lunas', 'Penjualan kredit peralatan');

-- -------------------------------------------------------------
-- Pembayaran
-- -------------------------------------------------------------
INSERT INTO pembayaran (id_pembayaran, id_piutang, tanggal_pembayaran, jumlah_bayar, metode_pembayaran, keterangan) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 250000.00, 'Transfer', 'Pembayaran tahap 1'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 25 DAY), 500000.00, 'Transfer', 'Pembayaran tahap 2'),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 250000.00, 'Tunai', 'Pelunasan'),
(4, 2, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 500000.00, 'Transfer', 'Pembayaran sebagian'),
(5, 3, DATE_SUB(CURDATE(), INTERVAL 35 DAY), 300000.00, 'Tunai', 'Pembayaran sebagian'),
(6, 6, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 1000000.00, 'Transfer', 'Pembayaran tahap 1'),
(7, 7, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 1000000.00, 'Transfer', 'Pembayaran tahap 1'),
(8, 7, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 500000.00, 'E-Wallet', 'Pembayaran tahap 2'),
(9, 9, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 1250000.00, 'Transfer', 'Pelunasan'),
(10, 10, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1000000.00, 'Tunai', 'Pembayaran sebagian'),
(11, 11, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 900000.00, 'E-Wallet', 'Pelunasan');
