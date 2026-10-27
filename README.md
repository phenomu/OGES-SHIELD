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

## Setup cepat — Docker (recommended)

Cukup satu perintah. Tidak perlu PHP, Python, Go, atau MySQL di host.

```bash
docker compose up -d --build
```

Setelah semua container `healthy`:

| Service      | URL                          | Keterangan                          |
| ------------ | ---------------------------- | ----------------------------------- |
| Web (aplikasi) | http://localhost:8000      | Login `admin` / `oges-admin-123`    |
| MySQL        | `127.0.0.1:3307`             | user `oges` / pass `oges_dev`       |
| Mock target  | http://127.0.0.1:8899        | WordPress+PHP palsu untuk demo      |
| Orchestrator | internal `orchestrator:8100` | sengaja **tidak** dipublikasikan    |

Cek status dan kesehatan:

```bash
docker compose ps
make health          # atau: curl -s localhost:8000/health
docker compose logs -f web orchestrator
```

### Yang dilakukan otomatis saat `up`

1. MySQL membuat database `oges_shield` + menjalankan `database/schema.sql`.
2. Container `web` menunggu DB, **menerapkan schema ulang kalau tabel belum ada**
   (penting kalau volume MySQL sudah pernah dibuat sebelumnya), lalu membuat
   superadmin dari env `OGES_ADMIN_*`.
3. Superadmin juga otomatis diberi 1 target demo `http://mockwp:8899/` (in-scope,
   environment `lab`) supaya menu **Set Workflow** langsung bisa dipakai.
4. Orchestrator memuat 9 tool dari `tools.json` dan memverifikasi binari
   `subfinder` / `httpx` / `nuclei` (diunduh saat build, versi di-pin).

### Konfigurasi AI (opsional tapi disarankan)

Key AI **tidak boleh** masuk image. Isi `orchestrator/.env` di host — file ini
dibaca container lewat `env_file` dan sudah dikecualikan di `.dockerignore`:

```bash
cp orchestrator/.env.example orchestrator/.env
# edit: AI_ROUTER_API_KEY, AI_BASE_URL, AI_MODEL_PRIMARY
docker compose up -d orchestrator     # restart agar env baru terbaca
```

Tanpa key, platform tetap jalan penuh (recon, tool manual, findings, report PDF).
Hanya keputusan otomatis AI loop yang tidak aktif.

### Perintah harian

```bash
make up        # start (build kalau belum ada)
make logs      # ikuti log
make health    # cek web + orchestrator + mock target
make down      # stop, data aman
make clean     # stop + hapus volume MySQL (DATA HILANG)
make rebuild   # rebuild tanpa cache (setelah ubah requirements.txt/Dockerfile)
```

### Catatan arsitektur container

- **Satu repository = satu layout path.** Container memakai `/app` dengan struktur
  identik repo (`/app/public`, `/app/src`, `/app/orchestrator`, `/app/storage`),
  jadi `BASE_PATH`, path `tools.json`, dan `storage/` valid tanpa patch tambahan.
- **`storage/` adalah bind mount bersama.** Orchestrator menulis
  `storage/reports/*.pdf` dan `storage/evidence/`, web mengalirkannya ke user.
  Kedua container memakai UID/GID `1000` supaya tidak ada masalah permission.
- **`orchestrator/config/` juga bind mount.** Admin UI (Tool Registry) menulis
  `tools.json` lalu memicu reload di orchestrator — keduanya harus melihat file
  yang sama.
- **`.env` host tidak ikut ke image.** `Env::load()` PHP memprioritaskan isi file
  `.env` di atas environment process; kalau `.env` host (`DB_HOST=127.0.0.1`)
  ter-bake, container tidak bisa konek ke `db`. Karena itu `.env` ada di
  `.dockerignore`.
- **Routing pakai `mod_rewrite`, bukan `FallbackResource`.** `public/` punya
  direktori nyata `admin/` dan `api/` yang bentrok dengan route aplikasi; aturan
  rewrite meniru perilaku `php -S` (file nyata dilayani, sisanya ke `index.php`).
- **Orchestrator tidak dipublikasikan.** Tidak ada `ports:` — hanya bisa dihubungi
  dari network `oges-net`. Debug manual:
  `docker compose exec orchestrator curl -s localhost:8100/health`.

### Troubleshooting

```bash
# Container web restart terus → lihat log entrypoint
docker compose logs web | head -40

# Cek orchestrator memuat tools + key AI terdeteksi
docker compose exec orchestrator curl -s localhost:8100/health | python -m json.tool

# Reset total (schema + seed + semua run/finding)
docker compose down -v && docker compose up -d --build

# Masuk MySQL
docker compose exec db mysql -uoges -poges_dev oges_shield
```

---

## Setup cepat (local dev, tanpa Docker)

### 1. Database

MySQL native / Docker / remote — apapun bisa, yang penting bisa diakses
dari PHP & orchestrator (default `127.0.0.1:3306`). Contoh pakai docker
(pilihan, bukan keharusan):

```bash
docker run -d --name oges-mysql -e MYSQL_ROOT_PASSWORD=rootpass \
  -p 127.0.0.1:3306:3306 -v oges_mysql_data:/var/lib/mysql mysql:8.0
```

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p -e "CREATE USER IF NOT EXISTS 'oges'@'127.0.0.1' IDENTIFIED BY 'oges_dev'; GRANT ALL ON oges_shield.* TO 'oges'@'127.0.0.1';"
```

> Catatan: repo ini TIDAK berisi Dockerfile/docker-compose — semua service
> jalan native (PHP dev server + Python venv). MySQL via docker di atas
> hanyalah cara cepat menyediakan DB, samakan saja akses dengan `.env`.

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

### 4. Akun superadmin (wajib — login butuh akun)

```bash
php scripts/seed_admin.php admin@oges.local admin "Admin OGES" passwordpilihanmu
```

Setelah itu buka `http://127.0.0.1:8000/` → login → menu Assets daftarkan
target in-scope → menu Workflows jalankan template.

### 5. Go tools (opsional — binari sudah tersedia di `bin/`)

```bash
bash scripts/setup.sh    # install subfinder, httpx, nuclei, naabu ke bin/
```

## Demo chain exploit otomatis (contoh teruji)

```
subfinder (semua subdomain) → httpx (tech-detect per host)
  → AI pilih cms_check      → CMS/framework PER HOST (WordPress/Laravel/cPanel/
                              webmail) + versi PHP + rekomendasi template nuclei
  → AI pilih nuclei         → per host, template_tags SESUAI CMS host itu
                              (host Laravel → tags laravel; host WP → wordpress)
  → AI pilih wp/php_check   → versi + CVE match → findings
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

## Bikin tool custom sendiri

Salin `orchestrator/tools/template.py` → isi logika di `run_check()`, daftarkan di
`orchestrator/config/tools.json` (ganti id/valid_phases/deskripsi), tambah 1 baris
di `runner.py` `_CUSTOM_TOOLS`, klik "Muat Ulang" di Tool Modules. Docstring
template.py berisi anatomu lengkap + snippet JSON + kontrak output findings
(`vulnerable` / `cves` / `exploit_path_valid`). Detail: menu Dokumentasi di
aplikasi, section 6.

## Struktur

```
public/          DocumentRoot PHP (index.php front controller)
src/             kode PHP non-publik (Core, Models, Controllers, Views)
orchestrator/    Python FastAPI — AI brain + tool runner (+ tools/ custom Python)
database/        schema.sql
scripts/         setup.sh (install tools Go), mock_wp_server.py (target demo)
bin/             binari Go (gitignored; image build mengunduhnya sendiri)
storage/         reports + bukti tool output (gitignored)
docker/          Dockerfile + config per service (php, orchestrator)
docker-compose.yml  stack lengkap: web + orchestrator + db + mock target
```

## Penting

- `AI_ROUTER_API_KEY` hanya hidup di `orchestrator/.env` (gitignored). Jangan expose ke
  PHP/frontend, jangan commit.
- Target scan wajib terdaftar `in_scope=1` di tabel `targets` — orchestrator tolak eksekusi
  di luar scope (server-side).
- Tracker progress: lihat `tracker.md`.
