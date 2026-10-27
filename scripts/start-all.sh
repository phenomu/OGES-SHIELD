#!/usr/bin/env bash
# OGES-SHIELD — start semua service dev (MySQL docker, PHP, orchestrator,
# mock WP demo). Idempotent: service yang sudah jalan di-skip.
# Usage: bash scripts/start-all.sh
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ok()   { echo "[ok] $*"; }
skip() { echo "[skip] $*"; }
fail() { echo "[GAGAL] $*" >&2; }

# --- 1. MySQL (docker, volume oges_mysql_data — data persist) ---
if docker ps --format '{{.Names}}' | grep -qx oges-mysql; then
  skip "MySQL oges-mysql sudah jalan"
else
  if docker ps -a --format '{{.Names}}' | grep -qx oges-mysql; then
    docker start oges-mysql >/dev/null && ok "MySQL container di-start ulang"
  else
    bash scripts/run-db.sh && ok "MySQL container baru dibuat"
  fi
  # tunggu siap menerima koneksi (max ~30s)
  for _ in $(seq 1 30); do
    docker exec oges-mysql mysqladmin ping -uroot -proot 2>/dev/null | grep -q alive && break
    sleep 1
  done
fi
docker exec oges-mysql mysqladmin ping -uroot -proot >/dev/null 2>&1 \
  && ok "MySQL siap" || fail "MySQL tidak merespons ping"

# --- 2. PHP app (port 8000) ---
if curl -s -o /dev/null http://127.0.0.1:8000/login; then
  skip "PHP sudah jalan di :8000"
else
  nohup php -S 127.0.0.1:8000 -t public >storage/php-dev.log 2>&1 &
  disown
  sleep 1
  curl -s -o /dev/null http://127.0.0.1:8000/login \
    && ok "PHP jalan di http://127.0.0.1:8000" \
    || fail "PHP gagal start — cek storage/php-dev.log"
fi

# --- 3. Orchestrator (port 8100) ---
if curl -s -o /dev/null http://127.0.0.1:8100/health; then
  skip "Orchestrator sudah jalan di :8100"
else
  # buang proses lama yang nyangkut di port (zombie uvicorn)
  OLD=$(ss -tlnp 2>/dev/null | grep ':8100' | grep -oP 'pid=\K[0-9]+' | head -1)
  [ -n "$OLD" ] && { kill -9 "$OLD" 2>/dev/null; sleep 1; }
  nohup "$ROOT/orchestrator/venv/bin/python" -m uvicorn main:app \
    --host 127.0.0.1 --port 8100 --app-dir "$ROOT/orchestrator" \
    >storage/orchestrator.log 2>&1 &
  disown
  for _ in $(seq 1 15); do
    curl -s http://127.0.0.1:8100/health 2>/dev/null | grep -q '"ok"' && break
    sleep 1
  done
  curl -s http://127.0.0.1:8100/health 2>/dev/null | grep -q '"ok"' \
    && ok "Orchestrator jalan di http://127.0.0.1:8100" \
    || fail "Orchestrator gagal start — cek storage/orchestrator.log"
fi

# --- 4. Mock WP (port 8899 — target demo in-scope, opsional) ---
if curl -s -o /dev/null --max-time 2 http://127.0.0.1:8899/; then
  skip "Mock WP sudah jalan di :8899"
else
  nohup python3 scripts/mock_wp_server.py >storage/mockwp.log 2>&1 &
  disown
  sleep 1
  curl -s -o /dev/null --max-time 2 http://127.0.0.1:8899/ \
    && ok "Mock WP jalan di http://127.0.0.1:8899 (target demo)" \
    || fail "Mock WP gagal start — cek storage/mockwp.log"
fi

echo
echo "== OGES-SHIELD siap =="
echo "   UI          : http://127.0.0.1:8000   (login admin)"
echo "   Orchestrator: http://127.0.0.1:8100/health"
echo "   Mock target : http://127.0.0.1:8899   (WordPress 5.6.40 demo)"
echo "   Log         : storage/php-dev.log, storage/orchestrator.log"
