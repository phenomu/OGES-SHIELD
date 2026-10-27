#!/usr/bin/env bash
# OGES-SHIELD web entrypoint — tunggu MySQL, provision superadmin, lalu Apache.
set -euo pipefail

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"

echo "[web] menunggu MySQL ${DB_HOST}:${DB_PORT}..."
for i in $(seq 1 60); do
  if DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" php -r '
      $c = @fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT"), $e, $s, 2);
      exit($c ? 0 : 1);
  '; then
    echo "[web] MySQL siap."
    break
  fi
  if [ "$i" = "60" ]; then
    echo "[web] FATAL: MySQL tidak pernah siap di ${DB_HOST}:${DB_PORT}" >&2
    exit 1
  fi
  sleep 2
done

# Provisioning idempotent (schema dari MySQL initdb, seed superadmin dari env).
# JANGAN telan error: container tetap start (supaya /login bisa dibuka), tapi
# kegagalan harus terlihat jelas di log — dulu `|| echo` menyembunyikan bug
# sehingga admin tidak pernah ter-seed dan login selalu gagal tanpa petunjuk.
if [ "${OGES_SEED_ADMIN:-1}" = "1" ]; then
  if php /app/docker/php/bootstrap.php; then
    :
  else
    rc=$?
    echo "[web] ############################################################" >&2
    echo "[web] PERINGATAN: provisioning GAGAL (exit ${rc})." >&2
    echo "[web] Akun login mungkin belum dibuat — lihat pesan di atas." >&2
    echo "[web] Perbaiki lalu jalankan:" >&2
    echo "[web]   docker compose exec web php /app/docker/php/bootstrap.php" >&2
    echo "[web] ############################################################" >&2
  fi
fi

# Pastikan direktori runtime bisa ditulis (bind mount bisa bawa ownership host)
mkdir -p /app/storage/reports /app/storage/evidence 2>/dev/null || true
chown -R www-data:www-data /app/storage 2>/dev/null || true

exec apache2-foreground
