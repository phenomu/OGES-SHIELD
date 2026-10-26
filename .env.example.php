# local config untuk PHP app — SALIN ke .env lalu isi
# (gitignored, jangan commit .env)

# --- Database ---
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=oges_shield
DB_USER=oges
DB_PASS=ganti_password_disini

# --- App ---
APP_ENV=development
APP_URL=http://localhost:8000
# Base URL internal orchestrator (localhost only, tidak pernah dari browser)
ORCHESTRATOR_URL=http://127.0.0.1:8100
