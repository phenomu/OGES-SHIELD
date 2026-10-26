# PROMPT.md — Master Build Instructions untuk AI Coding Agent (Hermes Agent)

> File ini adalah **system/task prompt** yang kamu paste/baca-kan ke Hermes Agent di awal sesi
> (`hermes` → paste isi file ini, atau simpan sebagai context file yang kamu reference tiap sesi).
> File ini **tidak berubah** selama project berjalan — yang berubah status-nya adalah `tracker.md`.

---

## 0. ATURAN WAJIB SEBELUM MULAI KERJA

1. **Selalu baca `tracker.md` dulu** sebelum mengerjakan apa pun. `tracker.md` adalah single source
   of truth untuk: phase mana yang aktif, task mana yang sudah/belum selesai, dan apa Definition of
   Done tiap task.
2. **Jangan loncat phase.** Jangan mulai Phase 2 kalau Phase 1 belum semua checklist-nya tercentang
   DAN belum ada approval eksplisit dari user (user akan bilang "approved phase 1" / "lanjut ke
   phase 2" di chat). Kalau ragu, berhenti dan tanya.
3. Setelah menyelesaikan satu task, **update checkbox di `tracker.md`** jadi `[x]` di commit yang
   sama dengan kode-nya. Tambahkan 1 baris catatan singkat di bawah task itu kalau ada deviasi dari
   spec (misal: "pakai dompdf bukan mpdf karena alasan X").
4. Kalau ada instruksi di bawah yang bentrok dengan permintaan user di chat saat itu, **instruksi
   terbaru dari user yang menang** — tapi tanyakan dulu kalau itu mengubah tech stack inti (PHP/
   Python/Go) atau menghapus guardrail keamanan di bagian 9.
5. Commit kecil & sering. Satu commit = satu task/sub-task dari tracker, bukan satu phase sekaligus.
6. Tulis kode untuk dibaca manusia dulu, pintar belakangan. Ini project hackathon offensive-security
   yang akan di-demo & kemungkinan di-review juri — kerapian dan kejelasan alur penting.

---

## 1. Identitas Project

- **Nama:** OGES-SHIELD
- **Tagline:** AI-Augmented Automated Penetration Testing Platform
- **Konteks:** dibangun untuk hackathon **Kartika Cyber** (kategori offensive security). Target
  scan HANYA boleh asset yang secara eksplisit didaftarkan user sebagai in-scope di platform ini —
  tidak ada hardcoded target, tidak ada scanning di luar scope yang didefinisikan user sendiri.
- **Inti produk:** dashboard web yang mengorkestrasi tools pentest (ProjectDiscovery suite via Go +
  tools custom via Python) secara otomatis, dengan AI yang membaca hasil tiap tool, membuat summary,
  lalu memutuskan tool berikutnya yang paling relevan — sampai workflow selesai — lalu
  menghasilkan laporan temuan (findings) dan auto-generate `report.pdf`.
- **Gaya visual:** dark cyberpunk/SOC dashboard — background navy/near-black, aksen neon cyan
  (`#22D3EE`/`#06B6D4`) + magenta/pink (`#EC4899`/`#D946EF`) untuk status critical, kuning/orange
  untuk warning/medium, hijau untuk success/online, border tipis bercahaya (glow), font monospace
  untuk angka/ID/kode (mis. `JetBrains Mono`), font sans untuk label (mis. `Inter`). Referensi
  layout lengkap ada di Bagian 4.

---

## 2. Tech Stack (HARD CONSTRAINT — jangan diganti tanpa approval user)

| Layer | Teknologi | Catatan |
|---|---|---|
| Web app (UI + routing + auth + CRUD) | **PHP native 8.x** | Tanpa framework besar (no Laravel/Symfony). Composer boleh dipakai hanya untuk library kecil & tunggal-fungsi (PDO helper, dotenv, PDF lib). Pakai pola front-controller sederhana buatan sendiri. |
| Database | **MySQL/MariaDB** via PDO, prepared statements wajib di semua query | |
| Orchestrator / "otak" AI + eksekusi tools | **Python 3.11+ (FastAPI)**, service terpisah yang long-running | Ini yang menjalankan Go/Python tools sebagai subprocess, panggil GLM API, dan tulis progress ke DB. **Tidak pernah diakses langsung dari browser** — hanya PHP backend yang boleh memanggilnya (internal network/localhost saja). |
| Scan tools | Binari **Go** dari ProjectDiscovery (`subfinder`, `httpx`, `nuclei`, `naabu`, dll) + tools **Python custom** | Dipanggil via subprocess oleh orchestrator, output di-parse ke JSON terstruktur. |
| AI model (orchestration brain) | **GLM-5.3** (fallback: `GLM-5.2`) diakses lewat **9router self-hosted** (bukan langsung ke Z.ai) | Base URL: `https://api.ate.my.id/v1` → endpoint lengkap `https://api.ate.my.id/v1/chat/completions`. Ini proxy OpenAI-compatible (9router) yang route ke GLM di belakangnya, jadi dipanggil persis seperti client OpenAI biasa — cukup override `base_url`. Auth: header `Authorization: Bearer $AI_ROUTER_API_KEY` (key yang di-issue oleh instance 9router kamu sendiri, **bukan** raw key Zhipu — jangan disamakan). Model id: **wajib dicek dulu** via `GET https://api.ate.my.id/v1/models` sebelum hardcode di kode, karena 9router kadang me-remap/alias nama model (bisa jadi `glm-5.3`, bisa juga ada prefix provider seperti `zai/glm-5.3` tergantung konfigurasi instance-mu). Support `tools`/function-calling, streaming, structured output selama provider di baliknya (GLM-5.3/5.2) juga support — sudah terverifikasi keduanya support tool-calling. **`AI_ROUTER_API_KEY` hanya hidup di orchestrator (.env), jangan pernah expose ke PHP/frontend.** |
| Realtime progress ke UI | Orchestrator tulis event ke tabel `run_events` (atau Redis kalau mau lebih cepat) → PHP expose endpoint SSE/polling `GET /api/stream.php?run_id=...` yang baca event terbaru | Untuk hackathon, polling tiap 1-2 detik via `fetch()` sudah cukup kalau waktu mepet; SSE kalau sempat. Jangan WebSocket dulu — kompleksitas tidak sepadan untuk 1-2 minggu. |
| PDF report | Python **WeasyPrint** (render HTML template → PDF) dijalankan oleh orchestrator, lalu file disimpan & di-link dari PHP | Alternatif kalau WeasyPrint susah di-install: `dompdf`/`mpdf` langsung dari PHP memakai data findings dari DB. Pilih salah satu di awal Phase 5, jangan dua-duanya. |

---

## 3. Struktur Repo Target

```
oges-shield/
├── public/                     # DocumentRoot PHP
│   ├── index.php               # front controller + router sederhana
│   ├── login.php  register.php  pending-approval.php
│   ├── dashboard.php
│   ├── assets.php              # Asset/host inventory
│   ├── recon.php               # panel reconnaissance (tabs)
│   ├── vulnerabilities.php     # panel vulnerability assessment
│   ├── workflows.php           # Set Workflow + live run view
│   ├── findings.php            # detail temuan + overall summary
│   ├── reports.php             # list & download report.pdf
│   ├── admin/
│   │   ├── approvals.php       # superadmin approve/reject register
│   │   └── tool-registry.php   # editor JSON tool set
│   ├── api/                    # endpoint JSON yang dipanggil JS (bukan untuk browser langsung)
│   │   ├── auth.php  assets.php  workflows.php  findings.php  stream.php
│   └── assets/ (css, js, img, fonts)
├── src/                         # kode PHP non-publik
│   ├── Core/      (Router, DB, Session, Auth, View, Guard/Middleware)
│   ├── Models/    (User, Target, Workflow, WorkflowRun, RunEvent, Finding, Report)
│   ├── Controllers/
│   └── Views/     (template + partial per panel dashboard)
├── orchestrator/                # Python FastAPI — "otak" AI
│   ├── main.py
│   ├── ai/client.py             # wrapper GLM API (OpenAI-compatible)
│   ├── ai/loop.py               # workflow loop engine (lihat Bagian 5)
│   ├── tools/registry.py        # loader untuk tools.json
│   ├── tools/runners/           # 1 file per tool/kategori tool (subprocess wrapper)
│   ├── config/tools.json        # JSON tool set (bisa dimodifikasi via admin UI)
│   ├── config/workflow_templates.json
│   └── reports/generator.py, reports/templates/report_template.html
├── bin/                          # binari Go (gitignored, diinstall via scripts/setup.sh)
├── database/schema.sql
├── scripts/setup.sh
├── tracker.md
├── PROMPT.md
└── README.md
```

---

## 4. Spesifikasi UI/UX — CLONE INI DI PHASE 1 (prioritas utama)

Referensi visual: dashboard bertema SOC/pentest-platform dark neon. Berikut breakdown tiap
komponen berdasarkan layout referensi. **Ganti semua branding "NEON//SHIELD" → "OGES-SHIELD"**,
dan **hapus panel Network Topology** (lihat catatan di bawah untuk penggantinya).

### 4.1 Top Bar (fixed, full width)
- Kiri: logo shield + wordmark **OGES-SHIELD** + tagline kecil "AI-AUGMENTED PENETRATION TESTING".
- Blok "Current Engagement": nama engagement aktif (mis. `ACME_CORP_ASSESSMENT_2026`) + ID.
- Toggle **Environment**: `LAB | STAGING | PRODUCTION` (pill selector, 1 aktif highlighted).
- Blok **Authorization**: status `AUTHORIZED` (hijau, ikon check) + rasio target in-scope (`5/6
  targets in scope`).
- Blok **AI Engine** (pengganti "Python Engine" di referensi — tampilkan status koneksi ke GLM API +
  model aktif, mis. `glm-5.3 • online`).
- Blok **Workers** (worker pool orchestrator, mis. `4 / 8`) + **Task Queue** (jumlah job pending).
- Ikon notifikasi (badge angka) + avatar user + nama + role (mis. "Security Researcher").

### 4.2 Sidebar (kiri, fixed)
Menu (ikon + label), state aktif di-highlight dengan garis/glow cyan:
Overview · Operations · Asset Discovery · Reconnaissance · Vulnerability Assessment ·
Exploitation Validation · Persistence Simulation · Findings · Reports · Automation Workflows ·
Python/Tool Modules · Audit Logs · (Admin → Approvals, Tool Registry — khusus role superadmin) ·
Settings. Footer sidebar: tagline singkat + logo kecil.

### 4.3 Baris Stat Cards (6 kartu, top of main content)
Tiap kartu: ikon, label, angka besar, "/ total" kecil, trend kecil (↑/↓ + %), sparkline mini.
Kartu: **Authorized Assets**, **Hosts Discovered**, **Open Vulnerabilities** (merah/pink, ikon
warning), **Critical Findings** (merah terang), **Completed Workflow Runs**, **AI Orchestrations
Active** (pengganti "Python Workers Active" — tampilkan berapa AI loop yang sedang jalan, dengan
progress bar kecil).

### 4.4 Panel "Operations Overview" (kiri, lebar)
- Judul + subjudul "Penetration Testing Workflow".
- **Stepper horizontal 8 step** dengan ikon lingkaran bernomor, status per step (✓ Completed /
  spinner Running / ⚠ Awaiting Approval / Pending `--`), label di bawah tiap step + waktu
  tempuh (mis. `12m 34s`). Step default (bisa disesuaikan per workflow template):
  `01 Scope Validation → 02 Reconnaissance → 03 Service Enumeration → 04 Vulnerability
  Assessment → 05 Exploitation Validation → 06 Persistence Simulation → 07 Cleanup &
  Verification → 08 Report Generation`.
- Baris bawah: progress bar keseluruhan (%), **Current Module** (nama tool/script yang lagi jalan,
  font monospace, mis. `nuclei_runner.py`), **Elapsed Time**, **Completed Tasks** (`x / y`).

### 4.5 Panel "Security Summary" (kanan, sejajar 4.4)
- Donut chart jumlah **Open findings** dengan breakdown Critical/High/Medium/Low/Info (warna
  sesuai severity) + angka di tengah donut + legend dengan jumlah & %.
- Mini bar-chart horizontal **CVSS Score Distribution** per rentang (9.0-10.0, 7.0-8.9, 4.0-6.9,
  0.1-3.9) dengan jumlah di kanan.

### 4.6 Baris Tengah — **GANTI Network Topology**
Di referensi ada panel Network Topology interaktif — **tidak perlu dibuat**. Gantikan slot ini
dengan salah satu (putuskan saat Phase 1, catat pilihannya di tracker.md):
- **Opsi A (disarankan):** panel **"AI Orchestration Log"** — live feed keputusan AI: tool apa yang
  baru selesai, ringkasan AI, tool apa yang dipilih AI berikutnya & alasannya (reasoning singkat).
  Ini justru jadi fitur showcase paling menarik untuk juri hackathon karena bagian ini yang paling
  "AI" dari seluruh produk.
- Opsi B: perlebar panel Reconnaissance (4.7) jadi full-width di baris itu.

### 4.7 Panel "Reconnaissance" (kanan)
Tab bar: `Target Inventory | DNS Records | Subdomains | IP Addresses | Services`. Isi tab default
(Target Inventory): tabel kolom `Asset · Type · Service · Status · Discovered · Scope`, baris
dengan ikon per tipe asset, badge status (Online hijau), badge scope ("In Scope" ungu).
Di bawah tabel: mini line-chart "Asset Discovery Timeline" (Passive vs Active, 2 warna) + angka
besar "Total Assets" di kanan.

### 4.8 Panel "Vulnerability Assessment" (kiri bawah, lebar)
Tab: `Findings | By Asset | By CVSS | By CWE`. Tabel findings: `Severity (badge warna) · Title ·
Asset · CVSS · CWE · Status (Open/In Progress)`. Di sebelah kanan tabel (card terpisah): detail
finding yang sedang dipilih — judul, badge severity+skor, lalu field: Asset, CWE, Summary,
Evidence (path file bukti), Impact, Remediation, References (link CVE/CWE/OWASP), Verification
status.

### 4.9 Panel "Tool Automation Engine" (kanan bawah, lebar) — pengganti "Python Automation Engine"
- Header: status Runtime (online), versi model AI aktif, jumlah Active Workers, Queue, Dependencies
  health.
- **Module Pipeline**: kartu-kartu kecil nama script/tool + status (Done/Running/Pending) + durasi.
- **Execution Logs**: terminal-style live log (monospace, warna per level INFO/WARN/ERROR), auto
  scroll, tombol "Live Logs" toggle + "Clear".
- Panel **Quick Actions** (kanan paling luar, mengikuti layout referensi): tombol `Run Assessment`,
  `View Full Report`, `Manage Targets`, `Stop All Tasks` (merah, perlu konfirmasi).
- Panel **Environment Status**: checklist kecil Target Authorization / Go Tools Engine / Python
  Engine / Network Access, masing-masing dengan badge status.

### 4.10 Halaman Auth (belum ada di referensi, desain baru konsisten dengan tema)
- `login.php`: form email/username + password, dark card dengan glow border, logo besar di atas.
- `register.php`: form registrasi (nama, email, username, password, alasan/justifikasi akses
  singkat) → setelah submit, redirect ke `pending-approval.php` ("Akun kamu menunggu persetujuan
  superadmin").
- Role `pending` **tidak bisa login** sampai di-approve. Tampilkan pesan jelas kalau user pending
  coba login.
- `admin/approvals.php` (khusus superadmin): tabel user pending, tombol Approve/Reject, alasan
  reject opsional.

### 4.11 Prinsip UX tambahan
- Semua state kosong (belum ada scan/workflow) harus punya empty-state yang jelas + CTA ("Mulai
  workflow baru").
- Semua aksi destruktif (Stop All Tasks, Reject user, Delete target) wajib konfirmasi modal.
- Loading state eksplisit di tiap panel yang fetch data async (skeleton/spinner, bukan layar putih).
- Responsive minimal untuk layar laptop 1366px ke atas dulu (prioritas demo di laptop), mobile
  nice-to-have di akhir kalau waktu cukup.

---

## 5. Fitur Inti: AI-Orchestrated Workflow Loop

Ini fitur pembeda utama produk. Alurnya:

```
User klik "Set Workflow" → pilih target (asset in-scope) + pilih workflow template
    (mis. "Full Web App Assessment")
        ↓
Orchestrator mulai LOOP per phase (Scope Validation, Recon, Service Enum, Vuln Assessment, ...):
    1. Ambil daftar tools yang VALID untuk phase ini & statusnya enabled=true dari tools.json
    2. Panggil GLM-5.3 dengan:
         - system prompt: peran AI sebagai "pentest orchestrator", phase aktif, target info,
           ringkasan hasil sejauh ini (running summary, bukan full raw log — jaga context tetap kecil)
         - tools param: schema tiap tool yang valid (OpenAI function-calling format, diturunkan
           otomatis dari tools.json)
         - tool_choice: "required" (AI WAJIB pilih satu tool) atau beri opsi khusus
           `mark_phase_complete` kalau AI menilai phase sudah cukup
    3. Orchestrator eksekusi tool yang dipilih AI (subprocess Go/Python binary) dengan parameter
       yang AI tentukan (tetap divalidasi/sanitasi oleh orchestrator — AI tidak boleh inject
       argumen bebas ke subprocess, lihat Bagian 9)
    4. Tiap tool selesai → tulis event ke run_events (tool_started, tool_output, tool_completed)
       → ini yang muncul real-time di panel "Execution Logs" & "Module Pipeline"
    5. Hasil tool (JSON terstruktur) dikirim lagi ke GLM-5.3 untuk di-summary (natural language,
       ringkas) → tulis event ai_summary → muncul di panel "AI Orchestration Log"
    6. Balik ke langkah 2 (AI pilih tool berikutnya) — looping sampai AI panggil
       `mark_phase_complete` ATAU batas aman tercapai (max_tool_calls_per_phase,
       max_time_per_phase_seconds — keduanya configurable, safety net wajib ada supaya demo tidak
       infinite loop)
    7. Lanjut ke phase berikutnya, ulangi dari langkah 1
        ↓
Setelah phase terakhir (Report Generation):
    - Generate Overall Summary (1 pemanggilan GLM-5.3 terpisah, rangkum SEMUA findings +
      statistik, ditulis natural language untuk eksekutif)
    - Compile Findings Detail (dari tabel findings yang terkumpul sepanjang workflow)
    - Generate report.pdf dari template (Bagian 6)
```

### 5.1 Skema `tools.json` (contoh, taruh di `orchestrator/config/tools.json`)

```json
{
  "tools": [
    {
      "id": "subfinder",
      "name": "Subfinder — Subdomain Discovery",
      "category": "recon",
      "valid_phases": ["reconnaissance"],
      "risk_level": "safe",
      "command_template": "subfinder -d {domain} -silent -json",
      "input_schema": {
        "type": "object",
        "properties": { "domain": { "type": "string" } },
        "required": ["domain"]
      },
      "output_parser": "jsonlines",
      "timeout_seconds": 120,
      "enabled": true,
      "description": "Mencari subdomain dari domain target secara pasif. Gunakan di awal phase reconnaissance."
    },
    {
      "id": "httpx",
      "name": "httpx — Live Host Probe",
      "category": "recon",
      "valid_phases": ["reconnaissance", "service_enumeration"],
      "risk_level": "safe",
      "command_template": "httpx -l {input_file} -json -status-code -title -tech-detect",
      "output_parser": "jsonlines",
      "timeout_seconds": 180,
      "enabled": true,
      "description": "Probe daftar host/subdomain untuk cek mana yang live, ambil status code, title, dan tech stack."
    },
    {
      "id": "nuclei",
      "name": "Nuclei — Vulnerability Scan",
      "category": "vulnerability",
      "valid_phases": ["vulnerability_assessment"],
      "risk_level": "intrusive",
      "command_template": "nuclei -u {target_url} -json -severity {severity_filter}",
      "output_parser": "jsonlines",
      "timeout_seconds": 600,
      "enabled": true,
      "description": "Scan kerentanan berbasis template signature pada target. Hasil langsung dipetakan ke tabel findings."
    },
    {
      "id": "custom_sqli_probe",
      "name": "Custom SQLi Probe (Python)",
      "category": "vulnerability",
      "valid_phases": ["vulnerability_assessment", "exploitation_validation"],
      "risk_level": "intrusive",
      "command_template": "python3 tools/sqli_probe.py --url {target_url}",
      "output_parser": "json",
      "timeout_seconds": 300,
      "enabled": true,
      "description": "Tool custom tim sendiri untuk validasi SQL injection pada parameter yang dicurigai."
    }
  ]
}
```
- Field ini **boleh ditambah** sesuai kebutuhan (mis. `requires_confirmation: true` untuk tool yang
  `risk_level: "destructive"` — kalau ada, workflow HARUS pause dan minta klik konfirmasi user
  sebelum AI boleh mengeksekusinya).
- Halaman `admin/tool-registry.php` menyediakan editor (form per tool ATAU raw JSON editor dengan
  validasi schema) yang menulis balik ke file ini / tabel DB cermin-nya, lalu memberi sinyal reload
  ke orchestrator (endpoint internal `POST /internal/reload-tools`).

### 5.2 Progress detail (wajib granular, bukan cuma "running...")
Tiap event di `run_events` minimal punya: `run_id, phase, type (tool_started|tool_output|
tool_completed|ai_summary|ai_decision|phase_complete|error), payload (json), created_at`. UI polling
endpoint ambil event setelah `last_event_id` tertentu (incremental, bukan refetch semua tiap kali).

---

## 6. Findings, Overall Summary & Report PDF

- Tabel `findings`: `id, run_id, asset_id, severity, title, cvss_score, cwe, cve (nullable),
  summary, evidence_path, impact, remediation, references (json array), verification_status,
  source_tool, created_at`.
- Nuclei/tool output di-mapping otomatis ke baris `findings` (1 finding = 1 hasil positif dari tool,
  AI boleh menambahkan/memperkaya field `summary`, `impact`, `remediation` via panggilan terpisah
  setelah tool selesai — jangan percaya mentah-mentah severity dari tool, biarkan AI
  mem-validasi ulang severity berdasarkan konteks sebelum final).
- **Overall Summary**: hasil 1 panggilan GLM-5.3 di akhir workflow, prompt berisi agregat findings
  (jumlah per severity, daftar judul+CVSS, target scope, durasi) → output: ringkasan eksekutif
  (3-6 paragraf) + top 3 rekomendasi prioritas.
- **report.pdf**: generate dari `orchestrator/reports/templates/report_template.html` (header
  logo OGES-SHIELD, info engagement, overall summary, tabel ringkasan severity, detail tiap
  finding per halaman/section, lampiran metodologi & tools dipakai) → render via WeasyPrint →
  simpan ke `storage/reports/{run_id}.pdf` → PHP expose link download di `reports.php`.

---

## 7. Auth & RBAC

- Role: `pending` (baru daftar, belum bisa login) · `user`/`researcher` (approved, bisa jalankan
  workflow) · `superadmin` (approve/reject user + kelola tool registry + lihat semua data).
- Password: `password_hash()` (bcrypt/argon2), tidak ada password di log manapun.
- Session PHP native (`session_start`, regenerate id saat login), CSRF token di semua form POST.
- Semua halaman admin wajib middleware/guard cek role `superadmin` di server-side (bukan cuma
  sembunyikan menu di UI).

---

## 8. Yang TIDAK Perlu Dibangun (scope cut eksplisit)

- Panel Network Topology interaktif (lihat 4.6).
- Multi-tenant/multi-organization (asumsikan 1 instance = 1 tim/organisasi untuk hackathon).
- Mobile app / PWA.
- Payment/billing apa pun.
- Integrasi email transactional (approval superadmin cukup via in-app notification, bukan kirim
  email — kecuali waktu sangat longgar di Phase 6).

---

## 9. Guardrail Keamanan & Etika (NON-NEGOTIABLE)

1. **Scope enforcement di server-side, bukan cuma UI.** Sebelum tool apa pun dieksekusi, orchestrator
   WAJIB cek target yang akan di-scan ada di tabel `targets` dengan status `in_scope=true` milik
   workflow/run yang sah. Tolak eksekusi kalau tidak match (log sebagai security event).
2. **AI tidak pernah membangun command string bebas.** AI hanya boleh memilih `tool_id` dari
   registry + mengisi parameter sesuai `input_schema` tool tsb (typed, divalidasi). Orchestrator
   yang merakit command dari `command_template` + parameter tervalidasi — bukan AI yang menulis
   raw shell command. Ini mencegah command injection dari AI output.
3. Semua parameter yang masuk ke subprocess **di-escape/quote dengan benar** (gunakan
   `subprocess.run([...], shell=False)` di Python, array argumen — jangan `shell=True` dengan
   string concat).
4. Tool dengan `risk_level: "destructive"` wajib `requires_confirmation: true` → user manusia
   harus klik konfirmasi sebelum eksekusi, walau AI yang "memilih"-nya.
5. **Audit log lengkap**: setiap eksekusi tool (siapa, target apa, tool apa, kapan, hasil apa)
   tercatat permanen, bisa dilihat di `Audit Logs` page, tidak bisa dihapus dari UI biasa.
6. Rate limit / timeout wajib per tool (`timeout_seconds` di registry) supaya 1 tool macet tidak
   menggantung seluruh workflow.
7. Simpan `AI_ROUTER_API_KEY` dan kredensial DB hanya di `.env` orchestrator (gitignored), jangan pernah
   commit, jangan pernah kirim ke browser.
