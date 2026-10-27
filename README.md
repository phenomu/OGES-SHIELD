# OGES-SHIELD — README

**AI-Augmented Automated Penetration Testing Platform** — dashboard web yang mengorkestrasi
tools pentest (ProjectDiscovery Go + tools custom Python) secara otomatis, dengan AI
(GLM-5.3 via 9router) yang membaca hasil tiap tool, membuat summary, lalu memutuskan tool
berikutnya — sampai workflow selesai dan menghasilkan `report.pdf`.

> Hackathon Kartika Cyber — kategori offensive security. Scan HANYA asset yang didaftarkan
> user sebagai in-scope. Tidak ada hardcoded target.

## Stack

- **PHP 8.x native** (tanpa framework besar) — web UI, auth, CRUD, API endpoint
- **MySQL 8 / MariaDB** via PDO, prepared statements
- **Python 3.11+ / FastAPI** — orchestrator (otak AI + eksekusi tools), localhost only
- **Tools**: binari Go ProjectDiscovery (`subfinder`, `httpx`, `nuclei`, dst) + tools Python custom
- **PDF**: WeasyPrint (Python) — render HTML template → PDF

## Setup cepat (local dev)

### 1. Database

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p -e "CREATE USER IF NOT EXISTS 'oges'@'127.0.0.1' IDENTIFIED BY 'oges_dev'; GRANT ALL ON oges_shield.* TO 'oges'@'127.0.0.1';"
```

### 2. PHP app

```bash
cd /path/to/oges-shield
cp .env.example.php .env          # edit: DB_USER/DB_PASS sesuai DB-mu
php -S 127.0.0.1:8000 -t public   # dev server (public/ = DocumentRoot)
```

### 3. Orchestrator (Python)

```bash
cd orchestrator
python3 -m venv venv
./venv/bin/pip install -r requirements.txt
cp .env.example .env              # edit: DB + AI_ROUTER_API_KEY (key 9router-mu sendiri)
./venv/bin/python -m uvicorn main:app --host 127.0.0.1 --port 8100
# check: curl http://127.0.0.1:8100/health  → 200 {"status":"ok"}
```

### 4. Go tools (Phase 3, opsional sekarang)

```bash
bash scripts/setup.sh    # install subfinder, httpx, nuclei, naabu ke bin/
```

## Demo chain exploit otomatis (contoh teruji)

```
httpx (tech-detect: WordPress + PHP)
  → AI pilih wp_check      → versi WordPress 5.6.40 + CVE match → findings
  → AI pilih php_check     → versi PHP 5.6.40 + CVE match      → findings
  → AI pilih nuclei        → vuln scan template
  → AI pilih php2shell     → POC benign valid → finding CRITICAL "exploit path valid"
  → report.pdf otomatis
```

Target demo: mock WordPress + PHP vulnerable (`scripts/mock_wp_server.py`, jalankan
lokal lalu daftarkan `http://127.0.0.1:8899/` sebagai target in-scope). Semua tool
custom keluaran JSON → findings mapper (`orchestrator/findings.py`) → AI enrich →
PDF. Tambah tool sendiri (mis. "wp2shell") dengan pola yang sama — lihat menu
**Dokumentasi** di aplikasi (section 4 + 6).

## Struktur

```
public/          DocumentRoot PHP (index.php front controller)
src/             kode PHP non-publik (Core, Models, Controllers, Views)
orchestrator/    Python FastAPI — AI brain + tool runner (+ tools/ custom Python)
database/        schema.sql
scripts/         setup.sh (install tools Go), mock_wp_server.py (target demo)
bin/             binari Go (gitignored)
storage/         reports + bukti tool output (gitignored)
```

## Penting

- `AI_ROUTER_API_KEY` hanya hidup di `orchestrator/.env` (gitignored). Jangan expose ke
  PHP/frontend, jangan commit.
- Target scan wajib terdaftar `in_scope=1` di tabel `targets` — orchestrator tolak eksekusi
  di luar scope (server-side).
- Tracker progress: lihat `tracker.md`.
