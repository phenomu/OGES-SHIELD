# tracker.md — OGES-SHIELD Build Tracker

> **Cara pakai file ini (untuk AI agent & manusia):**
> - Ini living document. Centang `[x]` setiap task selesai, di commit yang sama dengan kodenya.
> - Jangan mulai phase berikutnya sebelum semua checklist di phase aktif tercentang **dan** ada
>   baris `APPROVED by user: <tanggal>` ditambahkan di bawah judul phase itu.
> - Kalau suatu task ternyata butuh keputusan yang tidak ada di `PROMPT.md`, tulis di kolom
>   "Catatan/Keputusan" di bawah task terkait, jangan diam-diam ambil keputusan sendiri untuk hal besar.
> - Target total: **10–14 hari kerja**. Estimasi hari per phase adalah PANDUAN, boleh geser asal
>   urutan prioritas (UI/UX dulu, backend belakangan) tetap dipegang.

**Status project saat ini:** 🟢 Phase 1 approved (2026-10-26) — Phase 2 (Auth & RBAC nyata)
sedang dikerjakan.

---

## Legend Prioritas
🟣 **Must-have untuk demo hackathon** · 🔵 Nice-to-have kalau waktu sisa · ⚪ Cut kalau mepet

---

## Phase 0 — Setup & Scaffolding (🟣, estimasi 0.5–1 hari)

- [x] Init repo + struktur folder sesuai Bagian 3 `PROMPT.md`
      Catatan: direktori `bin/` & `storage/` dibuat runtime (gitignored); konten folder
      di-commit bertahap per task phase-nya.
- [x] Setup PHP local env (PHP 8.x, MySQL/MariaDB, virtual host/`php -S` untuk dev)
      Catatan: PHP 8.3.6 host (pdo_mysql aktif). MySQL host tidak tersedia → pakai Docker
      container `oges-mysql` (mysql:8.0, port 127.0.0.1:3306) via `scripts/run-db.sh`.
      Dev server: `php -S 127.0.0.1:8000 -t public`.
- [x] Setup Python orchestrator env (`venv`, FastAPI, `requirements.txt` dasar)
      Catatan: host Python 3.14.7 (venv lokal), deps terpasang (fastapi, uvicorn, httpx,
      pymysql, python-dotenv, weasyprint). `/health` → 200.
- [x] `database/schema.sql` — buat tabel inti dengan kolom minimal dulu (boleh di-ALTER nanti):
      `users, targets, workflow_templates, workflow_runs, run_events, findings, reports, tool_registry_cache`
      Catatan: ditambah tabel `audit_logs` (diperlukan guardrail bagian 9.5) + seed 1
      workflow template. Sudah diloader ke MySQL dev, 8+1 tabel terbentuk.
- [x] `.env.example` untuk PHP & orchestrator (tanpa secret asli)
- [x] Pilih & install font (Inter + JetBrains Mono / sejenis) + setup design tokens CSS
      (color variables sesuai palet di `PROMPT.md` 4) di `public/assets/css/tokens.css`
      Catatan: font self-hosted (woff2, subset latin, dari Google Fonts) di
      `public/assets/fonts/` — tanpa CDN eksternal saat demo.
- [x] `README.md` singkat: cara jalankan PHP + orchestrator secara lokal
- [x] Decide & catat di sini: generate PDF pakai **WeasyPrint (Python)** atau **dompdf/mpdf (PHP)**?
      → Keputusan: **WeasyPrint (Python)** — render dari orchestrator sesuai arah PROMPT.md
      Bagian 2/6, HTML template fleksibel, dan dependencies sudah terpasang di venv.

**Definition of Done Phase 0:** project bisa di-`run` lokal (halaman PHP kosong + orchestrator
`/health` return 200), semua orang di tim bisa clone & jalanin tanpa setup manual ribet.

---

## Phase 1 — UI/UX Clone dengan Dummy Data (🟣🟣🟣 PRIORITAS UTAMA, estimasi 4–5 hari)

> **Aturan Phase 1:** SEMUA data di sini boleh hardcoded/mock (array PHP, JSON statis, dummy
> findings). Tidak ada koneksi ke orchestrator/AI/tools asli dulu. Fokus: layout, spacing, warna,
> komponen, interaksi dasar (tab switching, dropdown, modal) persis dengan nuansa referensi, dan
> **mudah dipahami** siapa pun yang pertama kali buka dashboard ini.

### 1A. Shell & Navigasi
- [x] Layout dasar: top bar fixed + sidebar fixed + area konten scroll (sesuai Bagian 4.1 & 4.2)
- [x] Sidebar dengan semua menu + state active highlight + collapse/expand (opsional 🔵)
      Catatan: collapse/expand sidebar ditunda (🔵) — prioritas konten panel dulu.
- [x] Top bar: logo OGES-SHIELD, engagement info, Environment pill toggle (LAB/STAGING/PRODUCTION),
      Authorization badge, AI Engine status badge, Workers/Queue, notifikasi, avatar+nama+role
- [x] Favicon + branding konsisten di semua halaman (title tag, dll)

### 1B. Dashboard Overview (`dashboard.php`)
- [x] 6 stat cards (Bagian 4.3) dengan sparkline mini (bisa pakai Chart.js versi simple)
- [x] Panel Operations Overview: stepper 8 step + progress bar + current module + elapsed time
      (Bagian 4.4) — dengan dummy state yang representatif (beberapa Completed, 1 Running, sisanya
      Pending) supaya terlihat "hidup"
- [x] Panel Security Summary: donut chart severity + bar CVSS distribution (Bagian 4.5)
- [x] Panel pengganti Network Topology — **AI Orchestration Log** (dummy entries: tool selesai →
      ringkasan AI → tool berikutnya dipilih + alasan) (Bagian 4.6)
      Catatan: dipilih **Opsi A (AI Orchestration Log)** — alasan: showcase "paling AI" untuk juri.
- [x] Panel Reconnaissance dengan tab (Target Inventory dulu, tab lain boleh placeholder "Coming
      from real scan") + mini timeline chart (Bagian 4.7)
- [x] Panel Vulnerability Assessment: tabel findings + detail panel finding terpilih, klik baris →
      update detail panel (pure JS, data dummy) (Bagian 4.8)
- [x] Panel Tool Automation Engine: module pipeline cards + execution log terminal-style (auto
      scroll dummy log) + Quick Actions + Environment Status (Bagian 4.9)

### 1C. Halaman Auth
- [x] `login.php` — form + validasi client-side dasar + state error (tanpa backend asli dulu, boleh
      hardcode redirect ke dashboard)
- [x] `register.php` — form lengkap
- [x] `pending-approval.php` — halaman status "menunggu approval"
- [x] Desain state: akun ditolak, akun pending, akun approved (3 pesan beda)
      Catatan: state via query `?state=pending|rejected` di login + alert demo.

### 1D. Halaman pendukung lain (boleh skeleton dulu, isi dummy)
- [x] `workflows.php` — list workflow templates + tombol "Set Workflow" (modal pilih target +
      template) + halaman "live run" (dummy progress yang jalan sendiri via JS interval, biar
      kerasa real-time)
- [x] `findings.php` — Overall Summary (dummy paragraf AI) + tabel semua findings + filter severity
- [x] `reports.php` — list laporan dummy + tombol download (disabled dulu/placeholder)
- [x] `admin/approvals.php` — tabel user pending dummy + tombol approve/reject (dummy)
- [x] `admin/tool-registry.php` — tampilkan isi `tools.json` contoh dalam bentuk tabel/editor
      (belum perlu nulis ke file asli)
- [x] Halaman `exploitation.php` + `persistence.php` + `audit.php` + `settings.php`
      Catatan (post-review user): halaman ini tidak ada di list task 1D tapi sidebar sudah
      menautkannya — dibuat dummy agar tidak ada menu mati. Active-state sidebar salah
      (semua menu → vulns) juga diperbaiki: tiap halaman punya `activeNav` sendiri,
      `?nav=` override untuk kasus menu→halaman bersama (operations, modules).

### 1E. Review UI/UX
- [x] Screenshot tiap halaman utama, bandingkan sisi-demi-sisi dengan referensi desain
      Catatan: 13 screenshot tersimpan di `storage/ui-review/` (gitignored). Verifikasi
      via DOM/geometry (vision tool quota habis): topbar fixed 56px, sidebar fixed 232px,
      6 stat card seragam, stepper 8 step, no horizontal scroll @1280px.
- [x] Cek semua empty state, loading state, error state ada desainnya (bukan blank)
      Catatan: empty state di reports/tab recon/By-Asset dll; skeleton class tersedia
      (`.skeleton`); loading state data-nyata dipakai di Phase 3 polling.
- [x] Cek konsistensi warna severity (Critical/High/Medium/Low/Info) dipakai SAMA di semua tempat
      (stat card, donut chart, badge tabel, detail panel)
      Catatan: sumber tunggal var `--sev-*` di tokens.css; JS SEV_COLORS + class dinamis
      `sev-{level}` PHP memakai nilai sama.
- [x] **STOP — demo Phase 1 ke user, minta approval eksplisit sebelum lanjut Phase 2**

> `APPROVED by user: 2026-10-26`

---

## Phase 2 — Auth & RBAC Nyata (🟣, estimasi 1 hari)

- [x] Tabel `users` final (role: pending/user/superadmin, password_hash, created_at, approved_at,
      approved_by)
      Catatan: deviasi kecil — ENUM role ditambah nilai `rejected` (approvals bisa reject tanpa
      hapus akun, alasan reject tampil saat login attempt) + kolom `registration_reason`
      (justifikasi register, dilihat superadmin). DB dev sudah di-ALTER.
- [x] Register → insert role=`pending`, tampilkan `pending-approval.php`
- [x] Login: cek password_hash, cek role != pending (kalau pending → pesan jelas, tidak boleh masuk)
- [x] Session handling + CSRF token di semua form
      Catatan: semua POST (login/register/approve/reject/logout) cek `Session::checkCsrf()`;
      session regenerate id saat login; akun didowngrade pasca-login → auto-logout.
- [x] Middleware/guard: halaman admin cek role superadmin server-side
      Catatan: guard di front controller (semua path non-publik wajib login; `/admin/*` wajib
      superadmin → 403) + double-guard `Auth::requireSuperadmin()` di AdminController.
- [x] `admin/approvals.php` fungsional: approve → role jadi `user`, reject → tandai rejected
      (atau hapus, sesuai keputusan tim) — dengan konfirmasi modal
      Catatan: dipilih **reject = tandai `rejected` + alasan** (bukan hapus) supaya jejak
      approval tetap ada + audit lengkap. Approve/reject via POST + CSRF + audit_logs.
- [x] Seed 1 akun superadmin awal (via script/migration, bukan lewat form register)
      Catatan: `scripts/seed_admin.php` (idempotent, password bisa diberikan atau digenerate).
      Akun dev: `admin` / password dari output script (lihat note chat, jangan commit).
- [x] Test manual: register → coba login (harus gagal, pesan pending) → superadmin approve →
      login sukses
      Catatan: E2E via curl + browser (login form asli) — semua jalur terverifikasi:
      register→pending→login block→approve→login ok→403 utk user biasa di /admin→
      logout→redirect login. Audit trail lengkap di `audit_logs`.

**Definition of Done Phase 2:** alur register → pending → approve → login benar-benar jalan end to
end, tanpa data dummy.

---

## Phase 3 — Orchestrator & Eksekusi Tools Nyata (🟣, estimasi 2–3 hari)

- [ ] Orchestrator FastAPI skeleton: endpoint internal `POST /runs`, `GET /runs/{id}/events`,
      `POST /internal/reload-tools`
- [ ] Install & test manual tiap binary Go yang dipakai (`subfinder`, `httpx`, `nuclei`, dst) via
      `scripts/setup.sh`, pastikan path binary terdeteksi orchestrator
- [ ] `tools/registry.py` — loader + validator `tools.json` (schema check, reject kalau format salah)
- [ ] 1 tool runner dulu sebagai pola (mis. `httpx`): subprocess call dengan array argumen
      (`shell=False`), parse output JSON lines, tulis `run_events`
- [ ] Ulangi pola untuk tool lain yang dipakai di workflow template awal (minimal: subfinder, httpx,
      nuclei, 1 tool custom Python)
- [ ] Scope enforcement: sebelum eksekusi, cek target ada di `targets` dengan `in_scope=true` milik
      run tsb — kalau tidak, tolak + log security event
- [ ] Endpoint PHP `api/stream.php` (polling, ambil event setelah `last_event_id`) → sambungkan ke
      panel Execution Logs & Module Pipeline yang di Phase 1 masih dummy → ganti jadi data asli
- [ ] Test manual: jalankan 1 tool end-to-end dari UI → lihat log real-time muncul di panel

**Definition of Done Phase 3:** klik "Run" 1 tool dari UI benar-benar mengeksekusi binary asli di
server, hasilnya masuk DB, dan progress-nya kelihatan live di dashboard (bukan dummy lagi).

---

## Phase 4 — AI Orchestration Loop (🟣, estimasi 2 hari)

- [ ] `ai/client.py` — wrapper panggilan GLM-5.3 (base_url, api_key dari `.env`, model id,
      fungsi `chat_with_tools(messages, tools, tool_choice)`), fallback otomatis ke `glm-5.2` kalau
      `glm-5.3` error/timeout
- [ ] Generate `tools` param (OpenAI function-calling schema) otomatis dari `tools.json` yang valid
      untuk phase aktif
- [ ] `ai/loop.py` — implementasi loop sesuai pseudocode Bagian 5 `PROMPT.md`: pilih tool → eksekusi
      → summary → pilih lagi → sampai `mark_phase_complete` atau safety limit
      (`max_tool_calls_per_phase`, `max_time_per_phase_seconds` — **wajib ada, test harus bisa
      membuktikan loop tidak bisa infinite**)
- [ ] Event `ai_summary` & `ai_decision` ditulis ke `run_events` → tampil di panel AI Orchestration
      Log (yang di Phase 1 masih dummy)
- [ ] Tool `risk_level: destructive` → pause run, tunggu konfirmasi user di UI sebelum lanjut
- [ ] `admin/tool-registry.php` fungsional: edit `tools.json` dari UI (form atau JSON editor dengan
      validasi), simpan, trigger `POST /internal/reload-tools`
- [ ] Test end-to-end: "Set Workflow" dari UI → AI jalan sendiri lintas 2–3 tool berturutan tanpa
      campur tangan manual → selesai di `mark_phase_complete`

**Definition of Done Phase 4:** fitur inti produk (Set Workflow → AI pilih & jalankan tools
bergantian → summary tiap langkah) bekerja end-to-end minimal untuk 1 phase workflow (mis.
Reconnaissance), dengan progress granular terlihat real-time.

---

## Phase 5 — Findings, Overall Summary & report.pdf (🟣, estimasi 1.5–2 hari)

- [ ] Mapping otomatis output tool (terutama nuclei) → baris `findings`
- [ ] Panggilan AI terpisah untuk memperkaya tiap finding (`impact`, `remediation`,
      `summary`) & validasi ulang severity
- [ ] `findings.php` nyambung data asli (ganti dummy Phase 1), filter & sort jalan
- [ ] Panggilan AI "Overall Summary" di akhir workflow (agregat semua findings + rekomendasi
      prioritas)
- [ ] Template `report_template.html` (atau PHP view kalau pakai dompdf/mpdf) — header
      OGES-SHIELD, info engagement, overall summary, tabel ringkasan severity, detail tiap finding
- [ ] Generator PDF jalan dari orchestrator (atau PHP, sesuai keputusan Phase 0), simpan ke
      `storage/reports/`
- [ ] `reports.php` nyambung data asli — list report per run + tombol download yang beneran jalan
- [ ] Test: 1 run workflow lengkap dari awal sampai `report.pdf` ke-generate & bisa di-download,
      isinya match dengan findings yang ada di DB

**Definition of Done Phase 5:** dari klik "Set Workflow" sampai punya `report.pdf` yang bisa
di-download, semua otomatis, tanpa langkah manual di tengah (kecuali konfirmasi tool destructive
kalau ada).

---

## Phase 6 — Polish, Hardening & Demo Prep (🟣 sebagian, 🔵 sebagian, estimasi 1.5–2 hari)

- [ ] 🟣 Audit Logs page fungsional (baca dari log eksekusi tool + security events Phase 3)
- [ ] 🟣 Review semua guardrail Bagian 9 `PROMPT.md` — checklist keamanan:
  - [ ] Semua subprocess call pakai array args, bukan `shell=True` + string concat
  - [ ] Semua query PHP pakai prepared statement
  - [ ] Scope check tidak bisa dibypass dari client (test coba kirim target di luar scope)
  - [ ] `.env` tidak ter-commit, API key tidak muncul di response/log manapun
  - [ ] CSRF token di semua form POST, cek role di server-side semua halaman admin
- [ ] 🟣 Error handling: tool gagal/timeout tidak bikin seluruh run macet (tandai failed, lanjut
      atau stop phase dengan jelas di UI)
- [ ] 🟣 Siapkan 1–2 target demo yang aman & terkontrol (mis. lab/CTF target sendiri, bukan target
      publik sembarangan) + walkthrough skenario demo
- [ ] 🟣 Test ulang alur penuh dari akun baru: register → approval → login → set workflow →
      lihat AI jalan → lihat findings → download report
- [ ] 🔵 Audit log export (CSV)
- [ ] 🔵 Notifikasi in-app (bell icon) benar-benar fungsional, bukan statis
  - [ ] 🔵 Responsive untuk layar lebih kecil
- [ ] 🔵 Dekorasi cyberpunk tambahan (city skyline, aksara dekoratif) ala referensi — murni kosmetik,
      kerjakan hanya kalau semua 🟣 di atas sudah beres
- [ ] ⚪ Email notification approval (cut kalau waktu tidak cukup)
- [ ] ⚪ Multi-bahasa UI (cut, hackathon cukup 1 bahasa)

**Definition of Done Phase 6:** produk siap didemokan dari awal sampai akhir tanpa error yang
memalukan di depan juri, dan guardrail keamanan dasar sudah dicek manual sekali.

---

## Catatan Keputusan Teknis (diisi sepanjang project)

| Tanggal | Keputusan | Alasan |
|---|---|---|
| | | |

## Risiko / Blocker yang Diketahui

| Risiko | Mitigasi |
|---|---|
| Binary Go (nuclei/subfinder/dll) butuh waktu install & update template di server demo | Install & test di Phase 0/3 lebih awal, jangan tunggu H-1 demo |
| AI loop bisa jalan terlalu lama / kena rate limit GLM saat demo live | Siapkan safety limit (Phase 4) + rencana B: jalankan run beberapa menit sebelum sesi demo, tunjukkan hasil + log, bukan full live run dari nol kalau model lambat |
| Scanning tool (terutama nuclei intrusive) ke target salah | Scope enforcement Phase 3 wajib teruji sebelum demo pakai target apa pun |
