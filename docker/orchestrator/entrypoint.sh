#!/usr/bin/env bash
# OGES-SHIELD orchestrator entrypoint — tunggu MySQL, cek registry, start uvicorn.
set -euo pipefail

cd /app/orchestrator

echo "[orchestrator] HOME=${HOME} (nuclei templates: ${HOME}/.local/nuclei-templates)"
for b in subfinder httpx nuclei; do
  if [ -x "/app/bin/${b}" ]; then
    echo "[orchestrator] tool ok: ${b}"
  else
    echo "[orchestrator] WARN: binari ${b} tidak ada di /app/bin" >&2
  fi
done

if [ "${OGES_WAIT_DB:-1}" = "1" ]; then
  echo "[orchestrator] menunggu MySQL ${DB_HOST:-db}:${DB_PORT:-3306}..."
  for i in $(seq 1 60); do
    if python - <<'PY'
import os, socket, sys
try:
    s = socket.create_connection(
        (os.getenv("DB_HOST", "db"), int(os.getenv("DB_PORT", "3306"))), timeout=2)
    s.close()
except OSError:
    sys.exit(1)
PY
    then
      echo "[orchestrator] MySQL siap."
      break
    fi
    if [ "$i" = "60" ]; then
      echo "[orchestrator] FATAL: MySQL tidak pernah siap" >&2
      exit 1
    fi
    sleep 2
  done
fi

# Registry invalid tidak boleh mematikan service (main.py sudah handle), tapi
# lebih baik terlihat di log saat boot.
python - <<'PY' || true
import json, os, sys
p = "/app/orchestrator/config/tools.json"
try:
    d = json.load(open(p, encoding="utf-8"))
    print(f"[orchestrator] tools.json ok: {len(d.get('tools', []))} tool")
except Exception as e:
    print(f"[orchestrator] WARN tools.json: {e}", file=sys.stderr)
PY

mkdir -p /app/storage/reports /app/storage/evidence

exec python -m uvicorn main:app \
  --host "${ORCHESTRATOR_HOST:-0.0.0.0}" \
  --port "${ORCHESTRATOR_PORT:-8100}" \
  --proxy-headers \
  --log-level "${ORCHESTRATOR_LOG_LEVEL:-info}"
