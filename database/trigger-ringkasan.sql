-- =============================================================
-- Trigger: ringkasan piutang selalu sinkron
-- Alasan: web dan phpMyAdmin membaca database yang SAMA
-- (piutang_pelanggan). Web menghitung ulang ringkasan setiap ada
-- perubahan pembayaran, tetapi edit langsung dari phpMyAdmin
-- melewati kode web. Trigger ini menghitung ulang kolom
-- jumlah_dibayar, sisa_piutang, dan status dengan ATURAN YANG SAMA
-- seperti helpers/piutang.php, sehingga edit dari mana pun hasilnya
-- sama.
--
-- Catatan: tulis pembayaran dengan nilai id langsung (form phpMyAdmin
-- dan web sudah begitu). Pola INSERT ... SELECT dari tabel piutang
-- ditolak MySQL (error 1442) karena trigger menyentuh induknya.
-- Cara pasang (sekali saja):
--   mysql -u root -p < database/trigger-ringkasan.sql
-- atau lewat phpMyAdmin: pilih database piutang_pelanggan > tab SQL >
-- salin-tempel isi file ini > Jalankan.
-- =============================================================

USE piutang_pelanggan;

DROP TRIGGER IF EXISTS trg_piutang_bi;
DROP TRIGGER IF EXISTS trg_piutang_bu;
DROP TRIGGER IF EXISTS trg_bayar_ai;
DROP TRIGGER IF EXISTS trg_bayar_au;
DROP TRIGGER IF EXISTS trg_bayar_ad;

-- Satu-satunya rumus kanonik (cermin helpers/piutang.php):
--   dibayar = SUM(pembayaran yang masih berlaku), sisa = max(total - dibayar, 0)
--   sisa <= 0.005                        => Lunas
--   dibayar > 0, lewat jatuh tempo        => Jatuh Tempo
--   dibayar > 0, belum lewat             => Sebagian
--   dibayar = 0, lewat jatuh tempo        => Jatuh Tempo
--   dibayar = 0, belum lewat             => Belum Lunas
CREATE TRIGGER trg_piutang_bi BEFORE INSERT ON piutang FOR EACH ROW
    SET NEW.jumlah_dibayar = (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku'),
        NEW.sisa_piutang = ROUND(GREATEST(NEW.total_piutang - (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku'), 0), 2),
        NEW.status = CASE
            WHEN ROUND(GREATEST(NEW.total_piutang - (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku'), 0), 2) <= 0.005 THEN 'Lunas'
            WHEN (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku') > 0 AND CURDATE() > NEW.jatuh_tempo THEN 'Jatuh Tempo'
            WHEN (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku') > 0 THEN 'Sebagian'
            WHEN CURDATE() > NEW.jatuh_tempo THEN 'Jatuh Tempo'
            ELSE 'Belum Lunas'
        END;

CREATE TRIGGER trg_piutang_bu BEFORE UPDATE ON piutang FOR EACH ROW
    SET NEW.jumlah_dibayar = (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku'),
        NEW.sisa_piutang = ROUND(GREATEST(NEW.total_piutang - (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku'), 0), 2),
        NEW.status = CASE
            WHEN ROUND(GREATEST(NEW.total_piutang - (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku'), 0), 2) <= 0.005 THEN 'Lunas'
            WHEN (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku') > 0 AND CURDATE() > NEW.jatuh_tempo THEN 'Jatuh Tempo'
            WHEN (SELECT COALESCE(ROUND(SUM(jumlah_bayar), 2), 0) FROM pembayaran WHERE id_piutang = NEW.id_piutang AND status = 'berlaku') > 0 THEN 'Sebagian'
            WHEN CURDATE() > NEW.jatuh_tempo THEN 'Jatuh Tempo'
            ELSE 'Belum Lunas'
        END;

-- Setiap perubahan pembayaran "menyentuh" induknya agar hitung ulang jalan.
CREATE TRIGGER trg_bayar_ai AFTER INSERT ON pembayaran FOR EACH ROW
    UPDATE piutang SET id_piutang = id_piutang WHERE id_piutang = NEW.id_piutang;

CREATE TRIGGER trg_bayar_au AFTER UPDATE ON pembayaran FOR EACH ROW
    UPDATE piutang SET id_piutang = id_piutang WHERE id_piutang = OLD.id_piutang OR id_piutang = NEW.id_piutang;

CREATE TRIGGER trg_bayar_ad AFTER DELETE ON pembayaran FOR EACH ROW
    UPDATE piutang SET id_piutang = id_piutang WHERE id_piutang = OLD.id_piutang;
