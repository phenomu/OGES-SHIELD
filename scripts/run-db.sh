#!/usr/bin/env bash
# OGES-SHIELD — start MySQL dev (Docker).
# Dipakai kalau host tidak punya MySQL/MariaDB server sendiri.
# Usage: bash scripts/run-db.sh
# Setelah jalan: docker exec -i oges-mysql mysql -uroot -poges_root_dev < database/schema.sql
set -euo pipefail

NAME=oges-mysql
PORT=3306

if docker ps --format '{{.Names}}' | grep -qx "$NAME"; then
  echo "[skip] container $NAME sudah jalan di port $PORT"
  exit 0
fi

docker run -d --name "$NAME" \
  -p "127.0.0.1:${PORT}:3306" \
  -e MYSQL_ROOT_PASSWORD=oges_root_dev \
  -e MYSQL_DATABASE=oges_shield \
  -e MYSQL_USER=oges \
  -e MYSQL_PASSWORD=oges_dev \
  -v oges_mysql_data:/var/lib/mysql \
  mysql:8.0

echo "Menunggu MySQL siap..."
until docker exec "$NAME" mysqladmin ping -uroot -poges_root_dev --silent 2>/dev/null; do
  sleep 2
done
echo "MySQL up: 127.0.0.1:${PORT} (db=oges_shield user=oges pass=oges_dev root=oges_root_dev)"
echo "Load schema: docker exec -i $NAME mysql -uroot -poges_root_dev < database/schema.sql"
