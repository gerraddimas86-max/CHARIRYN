#!/usr/bin/env bash
# Menjalankan uji aturan bisnis terhadap salinan database sementara.
# Data asli (piutang_pelanggan) tidak pernah disentuh.
set -eu

cd "$(dirname "$0")/.."

DB="piutang_pelanggan_uji"
HOST=127.0.0.1
PORT=3307
USER=root
MYSQL=/opt/lampp/bin/mysql
MYSQLDUMP=/opt/lampp/bin/mysqldump
DUMP=$(mktemp)

cleanup() {
  "$MYSQL" -h"$HOST" -P"$PORT" -u"$USER" -e "DROP DATABASE IF EXISTS \`$DB\`;" >/dev/null 2>&1 || true
  rm -f "$DUMP"
}
trap cleanup EXIT

cleanup
"$MYSQL" -h"$HOST" -P"$PORT" -u"$USER" -e "CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"$MYSQLDUMP" -h"$HOST" -P"$PORT" -u"$USER" --single-transaction --no-tablespaces piutang_pelanggan > "$DUMP"
"$MYSQL" -h"$HOST" -P"$PORT" -u"$USER" "$DB" < "$DUMP"

# Pemeriksaan 1: sintaks seluruh berkas PHP
echo "== A. Lint sintaks PHP =="
lint_gagal=0
while IFS= read -r f; do
  if ! /opt/lampp/bin/php -l "$f" >/dev/null 2>&1; then
    echo "FAIL lint $f"
    /opt/lampp/bin/php -l "$f" || true
    lint_gagal=$((lint_gagal + 1))
  fi
done < <(find . -name '*.php' -not -path './docs/*')
echo "OK   lint $(( $(find . -name '*.php' -not -path './docs/*' | wc -l) - lint_gagal )) berkas, $lint_gagal gagal"
[ "$lint_gagal" -eq 0 ] || exit 1

# Pemeriksaan 2: aturan bisnis
echo
echo "== B. Aturan bisnis (DFD bagian 16) =="
/opt/lampp/bin/php tests/self-check.php "$DB"
