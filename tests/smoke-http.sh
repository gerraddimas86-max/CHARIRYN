#!/usr/bin/env bash
# Smoke test HTTP: login per role lalu cek akses halaman.
# 403 dikirim lewat pengalihan ke halaman error/403.php, jadiredirect
# ikut diikuti dan status akhir yang diperiksa.
set -u
BASE="http://localhost/piutang-pelanggan"
TMP=$(mktemp -d)
GAGAL=0

# $4 = halaman login yang dipakai (default: auth/login.php)
login() {
  local user="$1" sandi="$2" jar="$3" halaman="${4:-auth/login.php}"
  rm -f "$jar"
  local token
  token=$(curl -s -c "$jar" "$BASE/$halaman" | grep -o 'name="token_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$jar" -c "$jar" -o /dev/null -X POST "$BASE/$halaman" \
    -d "token_csrf=$token" -d "username=$user" -d "password=$sandi"
}

check() {
  local jar="$1" path="$2" expect="$3" label="$4" kode
  kode=$(curl -s -L -b "$jar" -o /dev/null -w "%{http_code}" "$BASE/$path")
  if [ "$kode" = "$expect" ]; then
    printf "OK   %-46s %s\n" "$label" "$kode"
  else
    printf "FAIL %-46s got=%s want=%s\n" "$label" "$kode" "$expect"
    GAGAL=$((GAGAL + 1))
  fi
}

login manajer 'Manajer123!' "$TMP/m.txt"
login budi 'Pelanggan123!' "$TMP/c.txt" 'auth/login-pelanggan.php'
login pemilik 'Admin123!' "$TMP/p.txt"

echo "--- MANAJER (expect 200) ---"
for p in dashboard/index.php pelanggan/index.php pelanggan/permintaan.php piutang/index.php \
         pembayaran/index.php pembayaran/detail.php?id=1 pembayaran/batal.php?id=1 \
         pengajuan/index.php cari-pantau/index.php jatuh-tempo/index.php \
         akun-pelanggan/index.php akun-pelanggan/tambah.php akun-pelanggan/aktivasi.php?id=3 \
         akun/sandi.php; do
  check "$TMP/m.txt" "$p" 200 "manajer $p"
done
for p in laporan/piutang.php laporan/pembayaran.php laporan/pelanggan.php laporan/jatuh-tempo.php; do
  check "$TMP/m.txt" "$p" 200 "manajer $p"
done

echo "--- HALAMAN MASUK (publik) ---"
for p in "auth/login.php" "auth/login-pelanggan.php" "auth/aktivasi.php"; do
  check "$TMP/none.txt" "$p" 200 "publik $p"
done
kode_pilih=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/")
if [ "$kode_pilih" = "200" ]; then
  printf "OK   %-46s %s\n" "beranda pemilih peran" "$kode_pilih"
else
  printf "FAIL %-46s got=%s want=200\n" "beranda pemilih peran" "$kode_pilih"; GAGAL=$((GAGAL + 1))
fi

echo "--- PEMISAHAN PERAN (login di halaman yang salah harus ditolak) ---"
tokenx=$(curl -s -c "$TMP/x.txt" "$BASE/auth/login-pelanggan.php" | grep -o 'name="token_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
msg=$(curl -s -b "$TMP/x.txt" -c "$TMP/x.txt" -X POST "$BASE/auth/login-pelanggan.php" \
  -d "token_csrf=$tokenx" -d "username=manajer" -d "password=Manajer123!" | grep -oE '<li>[^<]*</li>' | head -1)
case "$msg" in
  *"halaman masuk untuk Manajer dan Pemilik"*) printf "OK   %-46s ditolak\n" "manajer ditolak di halaman pelanggan" ;;
  *) printf "FAIL %-46s %s\n" "manajer ditolak di halaman pelanggan" "$msg"; GAGAL=$((GAGAL + 1)) ;;
esac

echo "--- HIGHLIGHT SIDEBAR (hanya satu menu aktif) ---"
for p in laporan/piutang.php laporan/pembayaran.php laporan/jatuh-tempo.php laporan/pelanggan.php; do
  n=$(curl -s -b "$TMP/p.txt" "$BASE/$p" | grep -c 'item-menu aktif')
  if [ "$n" = "1" ]; then
    printf "OK   %-46s %s menu aktif\n" "pemilik $p" "$n"
  else
    printf "FAIL %-46s %s menu aktif (harus 1)\n" "pemilik $p" "$n"; GAGAL=$((GAGAL + 1))
  fi
done
for p in piutang/index.php pembayaran/index.php pelanggan/index.php dashboard/index.php \
         pengajuan/index.php pelanggan/permintaan.php akun-pelanggan/index.php \
         laporan/piutang.php laporan/pembayaran.php laporan/jatuh-tempo.php laporan/pelanggan.php; do
  n=$(curl -s -b "$TMP/m.txt" "$BASE/$p" | grep -c 'item-menu aktif')
  if [ "$n" = "1" ]; then
    printf "OK   %-46s %s menu aktif\n" "manajer $p" "$n"
  else
    printf "FAIL %-46s %s menu aktif (harus 1)\n" "manajer $p" "$n"; GAGAL=$((GAGAL + 1))
  fi
done

echo "--- PELANGGAN (portal 200, operasional 403) ---"
for p in portal/index.php portal/pengajuan.php portal/status-pengajuan.php portal/profil.php \
         portal/akun.php portal/detail.php?id=1 akun/sandi.php auth/aktivasi.php; do
  check "$TMP/c.txt" "$p" 200 "pelanggan $p"
done
for p in dashboard/index.php pelanggan/index.php piutang/index.php pembayaran/index.php \
         pembayaran/detail.php?id=1 pengajuan/index.php pelanggan/permintaan.php \
         akun-pelanggan/index.php laporan/piutang.php laporan/pembayaran.php; do
  check "$TMP/c.txt" "$p" 403 "pelanggan $p (harus 403)"
done

echo "--- PEMILIK (laporan 200, operasional 403) ---"
for p in laporan/piutang.php laporan/pembayaran.php laporan/pelanggan.php \
         laporan/jatuh-tempo.php akun/sandi.php; do
  check "$TMP/p.txt" "$p" 200 "pemilik $p"
done
for p in dashboard/index.php pelanggan/index.php piutang/index.php pembayaran/index.php \
         pengajuan/index.php pelanggan/permintaan.php akun-pelanggan/index.php \
         portal/index.php; do
  check "$TMP/p.txt" "$p" 403 "pemilik $p (harus 403)"
done

echo "--- TANPA LOGIN (harus 302 ke halaman masuk) ---"
for p in pengajuan/index.php pengajuan/detail.php?id=1 pengajuan/bukti.php?id=1 \
         pelanggan/permintaan.php portal/pengajuan.php portal/status-pengajuan.php \
         portal/profil.php akun/sandi.php pembayaran/batal.php?id=1; do
  kode=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/$p")
  if [ "$kode" = "302" ]; then
    printf "OK   %-46s %s\n" "anon $p" "$kode"
  else
    printf "FAIL %-46s got=%s want=302\n" "anon $p" "$kode"
    GAGAL=$((GAGAL + 1))
  fi
done

rm -rf "$TMP"
echo "== GAGAL: $GAGAL =="
exit $((GAGAL > 0))
