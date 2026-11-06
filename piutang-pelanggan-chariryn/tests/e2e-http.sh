#!/usr/bin/env bash
# Uji alur end-to-end lewat HTTP:
#   buat akun -> aktivasi -> login pelanggan -> ajukan pembayaran + bukti
#   -> manajer verifikasi -> saldo berkurang -> bukti hanya untuk berhak.
#
# Semua data uji dibuat khusus dan DIBERSIHKAN kembali di akhir skrip,
# sehingga database asli tetap utuh.
set -u
BASE="http://localhost/piutang-pelanggan"
MYSQL="/opt/lampp/bin/mysql -h127.0.0.1 -P3307 -uroot piutang_pelanggan"
TMP=$(mktemp -d)
GAGAL=0
SUFFIX="uji$(date +%s)"
KODE_PEL="UJI-$SUFFIX"
NAMA_PEL="Pelanggan Uji $SUFFIX"
USERNAME="uji$SUFFIX"

cek() {
  if [ "$2" = "ya" ]; then printf "OK   %s\n" "$1"
  else printf "FAIL %s -- %s\n" "$1" "${3:-}"; GAGAL=$((GAGAL + 1)); fi
}

csrf() { curl -s -b "$1" -c "$1" "$BASE$2" | grep -o 'name="token_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
# Status TANPA mengikuti redirect: dipakai untuk mendeteksi status login.
kode() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$BASE$2"; }
q() { $MYSQL -N -B -e "$1" 2>/dev/null; }

# $4 = halaman login yang dipakai (default: auth/login.php)
login() {
  local user="$1" sandi="$2" jar="$3" halaman="${4:-auth/login.php}" t
  rm -f "$jar"
  t=$(csrf "$jar" "/$halaman")
  curl -s -b "$jar" -c "$jar" -o /dev/null -X POST "$BASE/$halaman" \
    -d "token_csrf=$t" -d "username=$user" -d "password=$sandi"
}

cleanup() {
  # Id piutang dikumpulkan lebih dulu: trigger pembayaran menyentuh tabel
  # piutang, jadi MySQL menolak subquery piutang pada DELETE pembayaran
  # (error 1442).
  local idpiutang
  idpiutang=$(q "SELECT id_piutang FROM piutang WHERE no_transaksi LIKE 'UJI-$SUFFIX%'" | tr '\n' ',' | sed 's/,$//')
  if [ -n "$idpiutang" ]; then
    $MYSQL -e "
      DELETE FROM pengajuan_pembayaran WHERE id_piutang IN ($idpiutang);
      DELETE FROM pembayaran WHERE id_piutang IN ($idpiutang);
      DELETE FROM piutang WHERE id_piutang IN ($idpiutang);" >/dev/null 2>&1
  fi
  $MYSQL -e "
    DELETE FROM users WHERE username = '$USERNAME';
    DELETE FROM pelanggan WHERE kode_pelanggan = '$KODE_PEL';" >/dev/null 2>&1
  rm -f "$TMP"/bukti.png
  rm -rf "$TMP"
}
trap cleanup EXIT

# --- 0. Data uji: pelanggan + piutang milik pelanggan itu ---
ID_PEL=$($MYSQL -N -B -e "INSERT INTO pelanggan (kode_pelanggan, nama_pelanggan, alamat, no_hp, email, status)
  VALUES ('$KODE_PEL', '$NAMA_PEL', 'Alamat uji', '081200000000', 'uji@example.com', 'aktif');
  SELECT LAST_INSERT_ID();")
ID_PIUTANG=$($MYSQL -N -B -e "INSERT INTO piutang (id_pelanggan, no_transaksi, tanggal_transaksi, total_piutang, jumlah_dibayar, sisa_piutang, jatuh_tempo, status, keterangan)
  VALUES ($ID_PEL, 'UJI-$SUFFIX-01', CURDATE(), 1000000, 0, 1000000, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'Belum Lunas', 'Uji e2e');
  SELECT LAST_INSERT_ID();")
cek "data uji disiapkan" "$([ -n "$ID_PIUTANG" ] && echo ya || echo tidak)" "id_pelanggan=$ID_PEL id_piutang=$ID_PIUTANG"

# --- 1. Manajer membuat akun pelanggan (DFD 7.2) ---
login manajer 'Manajer123!' "$TMP/m.txt"
t=$(csrf "$TMP/m.txt" "/akun-pelanggan/tambah.php")
curl -s -b "$TMP/m.txt" -c "$TMP/m.txt" -o /dev/null -X POST "$BASE/akun-pelanggan/tambah.php" \
  -d "token_csrf=$t" -d "id_pelanggan=$ID_PEL" -d "username=$USERNAME" -d "nama=$NAMA_PEL"
STATUS_AKUN=$(q "SELECT status FROM users WHERE username='$USERNAME'")
[ "$STATUS_AKUN" = "belum aktif" ] && cek "akun dibuat berstatus belum aktif (DFD 7.2)" ya || cek "akun dibuat berstatus belum aktif (DFD 7.2)" tidak "status=$STATUS_AKUN"

TOKEN=$(q "SELECT token_aktivasi FROM users WHERE username='$USERNAME'")
[ -n "$TOKEN" ] && cek "kode aktivasi dibuat (DFD 7.2)" ya || cek "kode aktivasi dibuat (DFD 7.2)" tidak "token kosong"

# --- 2. Pelanggan mengaktifkan akun (DFD 7.4) ---
rm -f "$TMP/a.txt"
t=$(csrf "$TMP/a.txt" "/auth/aktivasi.php")
AKTIF=$(curl -s -b "$TMP/a.txt" -c "$TMP/a.txt" -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/aktivasi.php" \
  -d "token_csrf=$t" -d "token_aktivasi=$TOKEN" -d "password_baru=UjiSandi123!" -d "konfirmasi_password=UjiSandi123!")
STATUS_AKUN=$(q "SELECT status FROM users WHERE username='$USERNAME'")
[ "$AKTIF" = "302" ] && [ "$STATUS_AKUN" = "aktif" ] && cek "aktivasi akun (DFD 7.4)" ya || cek "aktivasi akun (DFD 7.4)" tidak "code=$AKTIF status=$STATUS_AKUN"

# --- 3. Pelanggan login dengan sandi baru ---
login "$USERNAME" 'UjiSandi123!' "$TMP/c.txt" 'auth/login-pelanggan.php'
Halaman=$(kode "$TMP/c.txt" "/portal/index.php")
[ "$Halaman" = "200" ] && cek "pelanggan login setelah aktivasi" ya || cek "pelanggan login setelah aktivasi" tidak "code=$Halaman"

# --- 4. Pelanggan mengajukan pembayaran + unggah bukti ---
python3 - "$TMP/bukti.png" <<'PY'
import struct, sys, zlib
raw = b''
for _ in range(8):
    raw += b'\x00' + bytes([200, 120, 200] * 8)
def chunk(t, d):
    c = t + d
    return struct.pack('>I', len(d)) + c + struct.pack('>I', zlib.crc32(c) & 0xffffffff)
png = b'\x89PNG\r\n\x1a\n'
png += chunk(b'IHDR', struct.pack('>IIBBBBB', 8, 8, 8, 2, 0, 0, 0))
png += chunk(b'IDAT', zlib.compress(raw))
png += chunk(b'IEND', b'')
open(sys.argv[1], 'wb').write(png)
PY
t=$(csrf "$TMP/c.txt" "/portal/pengajuan.php")
KODE=$(curl -s -b "$TMP/c.txt" -c "$TMP/c.txt" -o /dev/null -w "%{http_code}" -X POST "$BASE/portal/pengajuan.php" \
  -F "token_csrf=$t" -F "id_piutang=$ID_PIUTANG" -F "nominal=100000" -F "tanggal_pengajuan=$(date +%d/%m/%Y)" \
  -F "metode_pembayaran=Transfer" -F "catatan=Uji e2e" -F "bukti_pembayaran=@$TMP/bukti.png;type=image/png")
ID_PENGAJIAN=$(q "SELECT id_pengajuan FROM pengajuan_pembayaran WHERE id_piutang=$ID_PIUTANG")
[ -n "$ID_PENGAJIAN" ] && cek "pengajuan pembayaran tersimpan (DFD 8.5)" ya || cek "pengajuan pembayaran tersimpan (DFD 8.5)" tidak "code=$KODE"

SISA=$(q "SELECT sisa_piutang FROM piutang WHERE id_piutang=$ID_PIUTANG")
[ "$SISA" = "1000000.00" ] && cek "pengajuan menunggu tidak mengurangi saldo" ya || cek "pengajuan menunggu tidak mengurangi saldo" tidak "sisa=$SISA"

STATUS_PENGAJIAN=$(q "SELECT status FROM pengajuan_pembayaran WHERE id_pengajuan=$ID_PENGAJIAN")
[ "$STATUS_PENGAJIAN" = "menunggu" ] && cek "status pengajuan = menunggu" ya || cek "status pengajuan = menunggu" tidak "status=$STATUS_PENGAJIAN"

# --- 5. Pelanggan melihat status pengajuan (DFD 8.6) ---
HITS=$(curl -s -b "$TMP/c.txt" "$BASE/portal/status-pengajuan.php" | grep -c 'Menunggu')
[ "$HITS" -ge 1 ] && cek "status pengajuan tampil ke pelanggan (DFD 8.6)" ya || cek "status pengajuan tampil ke pelanggan (DFD 8.6)" tidak "hits=$HITS"

# --- 6. Manajer memverifikasi (DFD 4.4) ---
t=$(csrf "$TMP/m.txt" "/pengajuan/detail.php?id=$ID_PENGAJIAN")
curl -s -b "$TMP/m.txt" -c "$TMP/m.txt" -o /dev/null -X POST "$BASE/pengajuan/detail.php?id=$ID_PENGAJIAN" \
  -d "token_csrf=$t" -d "tindakan=setujui"
STATUS_PENGAJIAN=$(q "SELECT status FROM pengajuan_pembayaran WHERE id_pengajuan=$ID_PENGAJIAN")
SISA=$(q "SELECT sisa_piutang FROM piutang WHERE id_piutang=$ID_PIUTANG")
JUMLAH_BAYAR=$(q "SELECT COUNT(*) FROM pembayaran WHERE id_piutang=$ID_PIUTANG")
[ "$STATUS_PENGAJIAN" = "disetujui" ] && cek "pengajuan disetujui manajer (DFD 4.4)" ya || cek "pengajuan disetujui manajer (DFD 4.4)" tidak "status=$STATUS_PENGAJIAN"
[ "$SISA" = "900000.00" ] && cek "saldo berkurang setelah persetujuan" ya || cek "saldo berkurang setelah persetujuan" tidak "sisa=$SISA"
[ "$JUMLAH_BAYAR" = "1" ] && cek "tepat satu pembayaran resmi tercatat" ya || cek "tepat satu pembayaran resmi tercatat" tidak "jumlah=$JUMLAH_BAYAR"

# --- 7. Persetujuan ulang tidak menggandakan (DFD 4.4) ---
t=$(csrf "$TMP/m.txt" "/pengajuan/detail.php?id=$ID_PENGAJIAN")
curl -s -b "$TMP/m.txt" -c "$TMP/m.txt" -o /dev/null -X POST "$BASE/pengajuan/detail.php?id=$ID_PENGAJIAN" \
  -d "token_csrf=$t" -d "tindakan=setujui"
SISA2=$(q "SELECT sisa_piutang FROM piutang WHERE id_piutang=$ID_PIUTANG")
JUMLAH_BAYAR2=$(q "SELECT COUNT(*) FROM pembayaran WHERE id_piutang=$ID_PIUTANG")
[ "$SISA2" = "$SISA" ] && [ "$JUMLAH_BAYAR2" = "1" ] && cek "persetujuan ulang tidak menggandakan pembayaran" ya || cek "persetujuan ulang tidak menggandakan pembayaran" tidak "sisa=$SISA2 jumlah=$JUMLAH_BAYAR2"

# --- 8. Berkas bukti hanya untuk yang berhak ---
KODE_BUKTI=$(curl -s -b "$TMP/m.txt" -o "$TMP/out.bin" -w "%{http_code}" "$BASE/pengajuan/bukti.php?id=$ID_PENGAJIAN")
head -c4 "$TMP/out.bin" | od -An -tx1 | grep -q '89 50 4e 47' && cek "manajer boleh membuka bukti pembayaran" ya || cek "manajer boleh membuka bukti pembayaran" tidak "code=$KODE_BUKTI"

KODE_ANON=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/pengajuan/bukti.php?id=$ID_PENGAJIAN")
[ "$KODE_ANON" = "302" ] && cek "bukti tidak bisa diakses tanpa login" ya || cek "bukti tidak bisa diakses tanpa login" tidak "code=$KODE_ANON"

login budi 'Pelanggan123!' "$TMP/p.txt" 'auth/login-pelanggan.php'
KODE_LAIN=$(kode "$TMP/p.txt" "/pengajuan/bukti.php?id=$ID_PENGAJIAN")
# 302 = belum login, 403 = login tetapi bukan pemilik pengajuan. Keduanya penolakan.
{ [ "$KODE_LAIN" = "302" ] || [ "$KODE_LAIN" = "403" ]; } && cek "pelanggan lain tidak bisa membuka bukti" ya || cek "pelanggan lain tidak bisa membuka bukti" tidak "code=$KODE_LAIN"

# --- 9. Pelanggan lain tidak melihat tagihan orang lain (DFD 8.2) ---
LAIN=$(kode "$TMP/p.txt" "/portal/detail.php?id=$ID_PIUTANG")
[ "$LAIN" = "302" ] && cek "pelanggan lain tidak bisa membuka tagihan orang lain" ya || cek "pelanggan lain tidak bisa membuka tagihan orang lain" tidak "code=$LAIN"

echo "== GAGAL: $GAGAL =="
exit $((GAGAL > 0))
