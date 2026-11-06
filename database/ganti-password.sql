-- =============================================================
-- Ganti / reset password akun web langsung dari phpMyAdmin.
-- Kolom users.password berisi HASH bcrypt, BUKAN teks biasa.
-- Menulis teks biasa ke kolom itu membuat login GAGAL.
--
-- A. Reset ke akun demo (cukup Jalankan file ini apa adanya):
--      pemilik   / Admin123!
--      manajer   / Manajer123!
--      budi      / Pelanggan123!
--
-- B. Password sendiri: buat dulu hash-nya di terminal, GANTI
--    'PASSWORD_BARU' dengan password yang diinginkan:
--      /opt/lampp/bin/php -r "echo password_hash('PASSWORD_BARU', PASSWORD_DEFAULT), PHP_EOL;"
--    Salin hash yang keluar, lalu jalankan:
--      UPDATE users SET password = 'HASH_YANG_DISALIN' WHERE username = 'pemilik';
--
-- Cara jalan: phpMyAdmin > database piutang_pelanggan > tab SQL >
-- salin-tempel > Jalankan. Atau: mysql -u root -p < ganti-password.sql
-- =============================================================

USE piutang_pelanggan;

UPDATE users SET password = '$2y$10$i959cThrUyUJ0zSP.oYzr.Eo0cUVRTIblcibtqtjLgR5dZlmDhVsO' WHERE username = 'pemilik';
UPDATE users SET password = '$2y$12$SMd6EfJnQtF6wwfEszQ3luTCPqIsFLnKptdspbYJeZCllH7vcQxJ2' WHERE username = 'manajer';
UPDATE users SET password = '$2y$12$efRa5ROcV8Q6PhuyWjjfhuvbJZAmPenjUykEbnT/NtxHai0VD5cM6' WHERE username = 'budi';

SELECT id_user, username, nama, role, status FROM users;
