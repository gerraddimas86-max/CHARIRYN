#!/usr/bin/env bash
#
# start.sh - Menjalankan aplikasi Piutang Pelanggan (Apache + MySQL XAMPP)
#
# Cara pakai (folder ini taro/symlink di htdocs XAMPP):
#   ./start.sh          # nyalakan Apache+MySQL, import DB bila belum ada, buka browser
#   ./start.sh --reset  # import ulang schema + seed (data uji bersih)
#
# Catatan: aplikasi ini MEMBUTUHKAN Apache (bukan php -S agar .htaccess aktif).
# Penjelasan lengkap: README.md bagian "Mengapa .htaccess".

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

detect_xampp() {
    if [[ -n "${XAMPP_DIR:-}" ]]; then
        echo "$XAMPP_DIR"
        return 0
    fi
    local cand="$APP_DIR/../../.."
    if [[ -x "$cand/mysql/bin/mysql.exe" ]]; then
        echo "$cand"
        return 0
    fi
    # Linux: /opt/lampp (bin/mysql + ctlscript xampp; apache di apache2/)
    if [[ -x /opt/lampp/bin/mysql && -x /opt/lampp/xampp ]]; then
        echo /opt/lampp
        return 0
    fi
    if [[ -d /Applications/XAMPP/xamppfiles/bin ]]; then
        echo /Applications/XAMPP/xamppfiles
        return 0
    fi
    echo "XAMPP tidak ditemukan. Set variabel XAMPP_DIR secara manual." >&2
    exit 1
}

XAMPP_DIR="$(detect_xampp)"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
# mysql: flag -p hanya bila password tidak kosong (-p tanpa nilai = prompt).
AUTH=(-u "$DB_USER")
if [[ -n "$DB_PASSWORD" ]]; then
    AUTH+=(-p"$DB_PASSWORD")
fi

# sudo untuk ctlscript XAMPP di Linux (wajib root)
SUDO=""
if [[ "$(id -u)" -ne 0 && ! -f "$XAMPP_DIR/xampp_start.exe" ]]; then
    SUDO="sudo"
fi

if [[ -f "$XAMPP_DIR/xampp_start.exe" ]]; then
    # Windows / Git Bash
    MYSQL="$XAMPP_DIR/mysql/bin/mysql.exe"
    MYSQLADMIN="$XAMPP_DIR/mysql/bin/mysqladmin.exe"
    URL_PATH="${APP_DIR#"$XAMPP_DIR/htdocs/"}"
    start_mysql() {
        ( cd "$XAMPP_DIR" && cmd //c 'start /min "MySQL" mysql\bin\mysqld.exe --defaults-file=mysql\bin\my.ini --standalone' >/dev/null 2>&1 )
    }
    start_apache() {
        ( cd "$XAMPP_DIR" && cmd //c 'start /min "Apache" apache\bin\httpd.exe' >/dev/null 2>&1 )
    }
    open_url() {
        cmd //c start "" "$1"
    }
else
    # Linux / macOS
    MYSQL="$XAMPP_DIR/bin/mysql"
    MYSQLADMIN="$XAMPP_DIR/bin/mysqladmin"
    URL_PATH="$(basename "$APP_DIR")"
    start_mysql() {
        $SUDO "$XAMPP_DIR/xampp" startmysql
    }
    # Matikan apache sistem bila menduduki :80, lalu nyalakan apache XAMPP.
    start_apache() {
        if command -v ss >/dev/null 2>&1 && ss -tlnp 2>/dev/null | grep -E ':80\s' | grep -qv /opt/lampp; then
            echo "Port 80 dipakai apache sistem, menghentikan..."
            $SUDO systemctl stop httpd apache2 2>/dev/null || true
            $SUDO systemctl disable httpd apache2 2>/dev/null || true
            sleep 2
        fi
        $SUDO "$XAMPP_DIR/xampp" startapache
    }
    if [[ "$(uname -s)" == "Darwin" ]]; then
        open_url() { open "$1"; }
    else
        open_url() { ( timeout 10s xdg-open "$1" >/dev/null 2>&1 & ); true; }
    fi
fi

URL="http://localhost/$URL_PATH/"

# ---------------------------------------------------------------
# MySQL
# ---------------------------------------------------------------
if "$MYSQLADMIN" "${AUTH[@]}" ping --silent >/dev/null 2>&1; then
    echo "MySQL : sudah berjalan"
else
    echo "MySQL : menyalakan..."
    start_mysql
    for _ in $(seq 1 30); do
        "$MYSQLADMIN" "${AUTH[@]}" ping --silent >/dev/null 2>&1 && break
        sleep 1
    done
    "$MYSQLADMIN" "${AUTH[@]}" ping --silent >/dev/null 2>&1 \
        || { echo "Gagal menyalakan MySQL." >&2; exit 1; }
fi

# ---------------------------------------------------------------
# Apache (cek URL aplikasi sendiri, bukan root, agar tahu vhost benar)
# ---------------------------------------------------------------
if curl -s -o /dev/null "$URL"; then
    echo "Apache: sudah berjalan"
else
    echo "Apache: menyalakan..."
    start_apache
    for _ in $(seq 1 30); do
        curl -s -o /dev/null "$URL" && break
        sleep 1
    done
    curl -s -o /dev/null "$URL" || { echo "Gagal menyalakan Apache." >&2; exit 1; }
fi

# ---------------------------------------------------------------
# Penyimpanan bukti pembayaran
# Folder ini hanya boleh ditulis oleh web server, jadi diberi izin
# tulis untuk semua (folder aplikasi sendiri juga 777).
# ---------------------------------------------------------------
mkdir -p "$APP_DIR/storage/bukti"
chmod 777 "$APP_DIR/storage" "$APP_DIR/storage/bukti" 2>/dev/null || true

# ---------------------------------------------------------------
# Database
# ---------------------------------------------------------------
if [[ "${1:-}" == "--reset" ]]; then
    echo "Database: reset schema + seed ..."
    "$MYSQL" "${AUTH[@]}" -e "DROP DATABASE IF EXISTS piutang_pelanggan;"
fi

if "$MYSQL" "${AUTH[@]}" -e "USE piutang_pelanggan; SELECT 1 FROM users LIMIT 1;" >/dev/null 2>&1; then
    echo "Database: piutang_pelanggan siap"
else
    echo "Database: mengimpor schema + seed ..."
    "$MYSQL" "${AUTH[@]}" --default-character-set=utf8mb4 < "$APP_DIR/database/schema.sql"
    "$MYSQL" "${AUTH[@]}" --default-character-set=utf8mb4 < "$APP_DIR/database/seed.sql"
    # DROP DATABASE ikut menghapus trigger, jadi pasang ulang setiap import.
    if [[ -f "$APP_DIR/database/trigger-ringkasan.sql" ]]; then
        echo "Database: memasang trigger ringkasan ..."
        "$MYSQL" "${AUTH[@]}" < "$APP_DIR/database/trigger-ringkasan.sql"
    fi
    echo "Database: selesai"
fi

# ---------------------------------------------------------------
# Buka aplikasi
# ---------------------------------------------------------------
echo ""
echo "Aplikasi : $URL"
echo "Login demo: pemilik / Admin123!   manajer / Manajer123!   budi / Pelanggan123!"
open_url "$URL"
