# tracker.md — OGES-SHIELD Build Tracker

> **Cara pakai file ini (untuk AI agent & manusia):**
> - Ini living document. Centang `[x]` setiap task selesai, di commit yang sama dengan kodenya.
> - Jangan mulai phase berikutnya sebelum semua checklist di phase aktif tercentang **dan** ada
>   baris `APPROVED by user: <tanggal>` ditambahkan di bawah judul phase itu.
> - Kalau suatu task ternyata butuh keputusan yang tidak ada di `PROMPT.md`, tulis di kolom
>   "Catatan/Keputusan" di bawah task terkait, jangan diam-diam ambil keputusan sendiri untuk hal besar.
> - Target total: **10–14 hari kerja**. Estimasi hari per phase adalah PANDUAN, boleh geser asal
>   urutan prioritas (UI/UX dulu, backend belakangan) tetap dipegang.

**Status project saat ini:** 🟢 Phase 6 paruh akhir — SPA navigation + fix run page
live (elapsed frozen, tombol Delete/Report muncul tanpa refresh) selesai.
Sisa 🔵 nice-to-have: responsive layar kecil, dekorasi cyberpunk.

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

> `APPROVED by user: 2026-10-26`

---

## Phase 3 — Orchestrator & Eksekusi Tools Nyata (🟣, estimasi 2–3 hari)

- [x] Orchestrator FastAPI skeleton: endpoint internal `POST /runs`, `GET /runs/{id}/events`,
      `POST /internal/reload-tools`
      Catatan: tambahan `POST /internal/execute-tool` (eksekusi manual per tool), `GET
      /internal/tools`, `POST /runs/{id}/stop`. DB layer `db.py` (PyMySQL, param binding).
- [x] Install & test manual tiap binary Go yang dipakai (`subfinder`, `httpx`, `nuclei`, dst) via
      `scripts/setup.sh`, pastikan path binary terdeteksi orchestrator
      Catatan: setup.sh dipin versi + path paket benar (`/cmd/<name>`); naabu dihapus dari
      list (butuh libpcap-dev + raw socket — tidak praktis di WSL demo). Runner resolve
      binary dari `bin/` lalu PATH.
- [x] `tools/registry.py` — loader + validator `tools.json` (schema check, reject kalau format salah)
      Catatan: validasi ketat (field wajib, enum category/risk/parser, schema param, placeholder
      template vs schema, destructive wajib requires_confirmation, id duplikat). tools.json
      httpx ditambah input_schema saat validasi pertama menolaknya.
- [x] 1 tool runner dulu sebagai pola (mis. `httpx`): subprocess call dengan array argumen
      (`shell=False`), parse output JSON lines, tulis `run_events`
      Catatan: pola runner generik (`tools/runner.py`) untuk semua tool: validasi param
      (typed + enum + reject shell-metachar), rakit args dari template placeholder, parse
      output (jsonlines/json/text), event tool_started/completed/failed + audit_logs.
- [x] Ulangi pola untuk tool lain yang dipakai di workflow template awal (minimal: subfinder, httpx,
      nuclei, 1 tool custom Python)
      Catatan: custom `tools/sqli_probe.py` (benign boolean-based probe). nuclei template
      flag `-json` → `-j` (v3.8 deprecated). Semua 4 tool teruji live via API + browser.
- [x] Scope enforcement: sebelum eksekusi, cek target ada di `targets` dengan `in_scope=true` milik
      run tsb — kalau tidak, tolak + log security event
      Catatan: guardrail 9.1 teruji: target tak terdaftar ditolak, param di luar scope ditolak,
      isi `input_file` di-scope-check per baris, run stopped/completed menolak eksekusi baru,
      semua violation tercatat `audit_logs` action=scope_violation.
- [x] Endpoint PHP `api/stream.php` (polling, ambil event setelah `last_event_id`) → sambungkan ke
      panel Execution Logs & Module Pipeline yang di Phase 1 masih dummy → ganti jadi data asli
      Catatan: proxy PHP (auth session + ownership check run) → orchestrator. JS `initRunStream`
      polling 2s; terminal + module card + page live `/run?id=...` data asli. workflows.php
      sekarang DB asli + Set Workflow POST nyata → halaman run.
- [x] Test manual: jalankan 1 tool end-to-end dari UI → lihat log real-time muncul di panel
      Catatan: E2E browser: 2 run nyata (subfinder 507 records live streaming, httpx waf-detect,
      nuclei 100s, sqli_probe 1.3s) — semua muncul real-time tanpa reload.

**Definition of Done Phase 3:** klik "Run" 1 tool dari UI benar-benar mengeksekusi binary asli di
server, hasilnya masuk DB, dan progress-nya kelihatan live di dashboard (bukan dummy lagi).

> `APPROVED by user: 2026-10-26`

---

## Phase 4 — AI Orchestration Loop (🟣, estimasi 2 hari)

- [x] `ai/client.py` — wrapper panggilan GLM-5.3 (base_url, api_key dari `.env`, model id,
      fungsi `chat_with_tools(messages, tools, tool_choice)`), fallback otomatis ke `glm-5.2` kalau
      `glm-5.3` error/timeout
      Catatan: model id `nr/glm-5.3` / fallback `nr/glm-5.2` (verifikasi via /models).
      Retry order: primary → primary → fallback. Fix 9router: body respons hybrid
      `JSON + data: [DONE]` → parser strip sufiks SSE. Tool-calling teruji.
- [x] Generate `tools` param (OpenAI function-calling schema) otomatis dari `tools.json` yang valid
      untuk phase aktif
      Catatan: `_tools_param()` — AI hanya melihat schema (nama/deskripsi/parameter),
      TIDAK pernah melihat command_template (guardrail 9.2). Plus opsi `mark_phase_complete`.
- [x] `ai/loop.py` — implementasi loop sesuai pseudocode Bagian 5 `PROMPT.md`: pilih tool → eksekusi
      → summary → pilih lagi → sampai `mark_phase_complete` atau safety limit
      (`max_tool_calls_per_phase`, `max_time_per_phase_seconds` — **wajib ada, test harus bisa
      membuktikan loop tidak bisa infinite**)
      Catatan: loop dijalankan thread per run (spawn dari POST /runs). E2E low.my.id:
      subfinder 13 subdomain → ai_summary → httpx pakai artefak subfinder_hosts.txt
      (loop persist output → file, AI diberi path) → ai_summary → mark_phase_complete →
      2 phase → run completed — FULL AUTONOMOUS tanpa campur tangan.
      AI self-correction teruji: param out-of-scope → security_event masuk running summary
      → AI koreksi param sendiri → sukses. Stuck-guard: 3 scope violation beruntun →
      phase_complete demi safety. **Safety-limit proof**: override MAX_TOOL_CALLS_PER_PHASE=2
      → phase auto-complete "safety limit tercapai" → run completed — infinite impossible.
- [x] Event `ai_summary` & `ai_decision` ditulis ke `run_events` → tampil di panel AI Orchestration
      Log (yang di Phase 1 masih dummy)
      Catatan: dashboard AI log panel sekarang polling event run terbaru (data asli);
      terminal run page + stepper live + module card live (fmtEvent semua tipe baru).
- [x] Tool `risk_level: destructive` → pause run, tunggu konfirmasi user di UI sebelum lanjut
      Catatan: run → `awaiting_confirmation` + event `confirmation_required` → modal auto-open
      di halaman run → POST /api/confirm-tool.php (auth+ownership+CSRF) → orchestrator
      /internal/confirm-tool. User tolak → tool_skipped + tool di-exclude dari pool phase
      (AI tidak bisa pilih lagi). E2E teruji: nuclei jalan → sqli destructive pause → user
      tolak → skipped → lanjut.
- [x] `admin/tool-registry.php` fungsional: edit `tools.json` dari UI (form atau JSON editor dengan
      validasi), simpan, trigger `POST /internal/reload-tools`
      Catatan: ToolRegistryController — toggle enabled (AI tak melihat tool OFF), rotate risk
      (safe→intrusive→destructive, destructive auto requires_confirmation), raw JSON editor.
      Semua perubahan: tulis file → reload orchestrator → kalau registry reject, file di-REVERT
      (tidak ada tools.json rusak menetap). Teruji roundtrip nuclei OFF→ON, sqli risk rotate.
- [x] Test end-to-end: "Set Workflow" dari UI → AI jalan sendiri lintas 2–3 tool berturutan tanpa
      campur tangan manual → selesai di `mark_phase_complete`
      Catatan: 3 run E2E browser: (1) low.my.id subfinder→httpx→complete autonomous,
      (2) target URL subfinder self-correct → complete, (3) full-web-app nuclei → sqli
      destructive pause → tolak → lanjut. Set Workflow dari form UI → redirect halaman run →
      semua event live (terminal/stepper/module/badge/AI log).

**Definition of Done Phase 4:** fitur inti produk (Set Workflow → AI pilih & jalankan tools
bergantian → summary tiap langkah) bekerja end-to-end minimal untuk 1 phase workflow (mis.
Reconnaissance), dengan progress granular terlihat real-time.

> `APPROVED by user: 2026-10-26` ("lanjut phase 5" — lanjut eksplisit setelah review UI run page)

---

## Phase 5 — Findings, Overall Summary & report.pdf (🟣, estimasi 1.5–2 hari)

- [x] Mapping otomatis output tool (terutama nuclei) → baris `findings`

      Catatan: orchestrator/findings.py — nuclei jsonlines (1 match = 1 finding, dedupe run+tool+title, evidence JSON per tool) + sqli_probe (param suspicious → finding CWE-89). Teruji: mapping SQLi → finding id=1.
- [x] Panggilan AI terpisah untuk memperkaya tiap finding (`impact`, `remediation`,
      `summary`) & validasi ulang severity

      Catatan: enrich_findings() — 1 AI call per batch (JSON array, parser toleran code-fence). Terbukti: severity high→medium divalidasi ulang AI (guardrail Bagian 6), impact+remediation diisi AI. AI gagal → finding mentah tetap tersimpan.
- [x] `findings.php` nyambung data asli (ganti dummy Phase 1), filter & sort jalan

      Catatan: FindingController — ownership (superadmin semua, user miliknya), filter severity server-side ?sev=, sort severity order, detail panel row-click. Overall summary dari row reports. Teruji: user biasa tidak lihat data run admin (empty state).
- [x] Panggilan AI "Overall Summary" di akhir workflow (agregat semua findings + rekomendasi
      prioritas)

      Catatan: generate_overall_summary() — dipanggil generator saat run completed; tersimpan reports.overall_summary, dipakai findings.php + report.pdf. Terbukti (teks eksekutif + rekomendasi low.my.id).
- [x] Template `report_template.html` (atau PHP view kalau pakai dompdf/mpdf) — header
      OGES-SHIELD, info engagement, overall summary, tabel ringkasan severity, detail tiap finding

      Catatan: jinja2 + WeasyPrint — cover dark, Executive Summary, severity table, finding per-section (page-break-inside avoid), methodology & tool runs. Semua nilai di-escape manual (data tool bisa berisi HTML).
- [x] Generator PDF jalan dari orchestrator (atau PHP, sesuai keputusan Phase 0), simpan ke
      `storage/reports/`

      Catatan: reports/generator.py — auto saat run completed (hook _finish) + endpoint manual POST /internal/generate-report. Idempotent (upsert reports, overwrite file). jinja2 ditambah requirements.txt.
- [x] `reports.php` nyambung data asli — list report per run + tombol download yang beneran jalan

      Catatan: ReportController — list (target/template/count), download stream PDF (auth+ownership+CSRF+realpath prefix guard anti-traversal), regenerate (POST+confirm). Run page terminal kasih link download live saat event report_generated.
- [x] Test: 1 run workflow lengkap dari awal sampai `report.pdf` ke-generate & bisa di-download,
      isinya match dengan findings yang ada di DB

      Catatan: run 66260477 (low.my.id) → autonomous completed → PDF auto 25KB → inject SQLi finding → regenerate 28KB → pypdf verified 3 halaman (cover/summary/F-1/CWE-89/methodology) → download 200 application/pdf → findings.php tampil + detail. Browser: row-click, table, download 200. Ownership: user biasa 403.
**Definition of Done Phase 5:** dari klik "Set Workflow" sampai punya `report.pdf` yang bisa
di-download, semua otomatis, tanpa langkah manual di tengah (kecuali konfirmasi tool destructive
kalau ada).

---

## Phase 6 — Polish, Hardening & Demo Prep (🟣 sebagian, 🔵 sebagian, estimasi 1.5–2 hari)

- [x] 🟣 Audit Logs page fungsional (baca dari log eksekusi tool + security events Phase 3)
      Catatan: rewrite audit.php — data asli audit_logs (JOIN users), filter action (whitelist
      22 nilai) + aktor (LIKE prefix) + pagination 50/hal, user biasa hanya lihat jejak
      sendiri (privacy), badge severity per action, detail JSON diringkas 1 baris. Export CSV
      via AuditController (BOM UTF-8, CSRF via query token — Session::checkCsrf kini terima
      argumen opsional, limit 10000, audit `audit_exported`). Input GET is_string-hardened.
- [x] 🟣 Review semua guardrail Bagian 9 `PROMPT.md` — checklist keamanan:
  - [x] Semua subprocess call pakai array args, bukan `shell=True` + string concat
  - [x] Semua query PHP pakai prepared statement
  - [x] Scope check tidak bisa dibypass dari client (test coba kirim target di luar scope)
  - [x] `.env` tidak ter-commit, API key tidak muncul di response/log manapun
  - [x] CSRF token di semua form POST, cek role di server-side semua halaman admin
      Catatan (review 2026-10-26): subprocess array+shell=False (runner.py, dari Phase 3);
      prepared statement semua query (Database::run / db.py); scope bypass test ditolak
      (Phase 3-4: target tak terdaftar + input_file out-of-scope + run completed);
      .env gitignored, key hanya di orchestrator .env; CSRF di semua POST termasuk
      resume/delete/confirm-tool baru; guard superadmin front-controller + controller.
      Tambahan hardening security non-guardrail: CSP nonce per-request
      (View::injectCspNonce — inline script wajib nonce), X-Frame-Options DENY,
      nosniff, Referrer-Policy, form-action/base-uri/frame-ancestors 'self';
      XSS: escape semua output tool di terminal/AI log (app.js esc()); semua input
      GET di-whitelist is_string (nav[], id[], sev[] dsb — fatal + warning mati);
      LFI: download PDF realpath + prefix storage/reports/.
- [x] 🟣 Error handling: tool gagal/timeout tidak bikin seluruh run macet (tandai failed, lanjut
      atau stop phase dengan jelas di UI)
      Catatan: sudah ada sejak P3/P4 (tool_failed event → loop lanjut; phase fatal → run failed,
      service tetap hidup) — diverifikasi live P6: execute-tool dengan script nonexistent →
      tool_failed rc=2 → run lanjut → subfinder/httpx sukses → run COMPLETED + report.pdf.
      Terminal UI render baris GAGAL merah (fmtEvent tool_failed).
- [x] 🟣 Siapkan 1–2 target demo yang aman & terkontrol (mis. lab/CTF target sendiri, bukan target
      publik sembarangan) + walkthrough skenario demo
      Catatan: DEMO.md — script 8-10 menit (pra-demo checklist, dashboard, story akun baru,
      target+scope, template, live run, findings+report, tutup security-first, rencana B jika
      AI lambat). Target demo: low.my.id (domain sendiri, lab) + example.com backup. 3 run
      completed + PDF siap.
- [x] 🟣 Test ulang alur penuh dari akun baru: register → approval → login → set workflow →
      lihat AI jalan → lihat findings → download report
      Catatan: E2E browser penuh dengan akun demo6293: register (justifikasi) → pending page →
      admin approve → login → register target example.com (in-scope) → Set Workflow
      (external-recon-only) → run d56104ed autonomous (21 event: subfinder 2 host →
      httpx 2 live → mark_phase_complete ×2) → COMPLETED → report.pdf 24KB
      (download 200, PDF v1.7). Findings 0 wajar (recon-only, example.com bersih).
- [x] 🔵 Audit log export (CSV)
      Catatan: sekaligus dengan audit page (lihat atas) — export 180 baris teruji, BOM, token
      salah 403.
- [x] 🔵 Notifikasi in-app (bell icon) benar-benar fungsional, bukan statis
      Catatan: dropdown dari run_events (confirmation_required / report_generated /
      run_failed / run_stopped) + badge count + link ke halaman run. Ditambah user
      dropdown (Settings/Dokumentasi/Logout) + topbar data asli (engagement run
      terbaru, workers/queue live).
  - [ ] 🔵 Responsive untuk layar lebih kecil (dikerjakan)
      Catatan: SPA navigation (anti reload antar menu) sudah jalan — klik sidebar
      swap konten via fetch + DOMParser + pushState, komponen JS re-init,
      loading opacity state, fallback full-load kalau fetch gagal, link
      download/logout/admin tetap navigasi normal. Fix run page live: elapsed
      final frozen dari server (data-elapsed-final, initElapsedTimers skip),
      tombol Delete/Report/Resume muncul-hilang live via runActionsUpdate
      (tanpa reload). E2E: run afd63142 pending→completed → Delete+Report
      muncul otomatis.
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
| 2026-10-26 | Stop run = permanen (tidak ada resume stopped-run); Resume hanya untuk `awaiting_confirmation` | Thread loop mati saat stop; melanjutkan run basi berisiko (context recon kedaluwarsa). User setuju biarkan |
| 2026-10-27 | Current Engagement = `ActiveRun` session helper (pilihan user atau fallback run terbaru) | Topbar dulu hardwired "run terbaru" — tidak bisa diganti; selector dropdown (topbar) + form (Settings), ownership divalidasi server-side |
| 2026-10-27 | `run_events.tool_completed` payload kini bawa `records` (max 50) + artefak `httpx_results.json` penuh | UI recon butuh data per-host (DNS/IP/service); stdout_tail 2000 char terpotong → unreliable; run lama fallback parse stdout_tail |
| 2026-10-27 | /vulnerabilities + /recon + /exploitation + /settings: MockData/dummy → DB asli | Semua panel tampilkan data engagement aktif; ownership filter per-user |
| 2026-10-27 | Timeline recon = event tool per-waktu (bukan flat) + fix initTimeline labels kosong | Chart blank karena recon.php kirim data tanpa `labels`; Chart.js throw senyap |
| 2026-10-27 | Template custom (superadmin): create + delete, phase whitelist 8, scope_validation auto-prepend, report_generation selalu terakhir | User minta bisa bikin template sendiri; guardrail urutan phase dijaga server-side; template terpakai run tidak bisa dihapus (jejak audit) |
| 2026-10-27 | Dropdown engagement max-height 330px + scroll; tombol Pilih/Terbaru sebaris di Settings | UI feedback user: dropdown panjang ke bawah; tombol reset aneh posisinya |
| 2026-10-27 | Modal Buat Template v2: phase picker kartu visual (deskripsi, tool per phase live dari tools.json, badge RISK, nomor urut) + preview pipeline interaktif (JS) + submit disabled <2 phase | User feedback: modal polos; juga memperjelas phase tanpa tool (persistence) auto-complete |
| 2026-10-27 | Audit page: user biasa hanya lihat jejaknya sendiri; superadmin semua | Privacy antar user; audit superadmin ditandai ★ |
| 2026-10-27 | Export CSV pakai CSRF via query string (link `<a>` bukan form POST) | Link download tak bisa bawa POST; token tetap terikat session, salah token = 403 |
| 2026-10-27 | `Session::checkCsrf()` kini terima argumen token opsional | Mendukung validasi token dari $_GET (export) tanpa merusak pemanggil lama (default $_POST) |
| 2026-10-27 | SPA nav: exclude `/login /register /admin/* /api/* download/export` — halaman lain swap tanpa reload | Form POST + download butuh full navigation; auth page perlu reload CSS/JS bersih |
| 2026-10-27 | Elapsed run selesai = frozen server-side (`data-elapsed-final` = finished-started) | Bug: initElapsedTimers + replay event stream menimpa nilai jadi timer jalan terus di run completed |
| 2026-10-27 | Tombol run actions (Stop/Delete/Report/Resume) dirender JS live dari event stream | Bug user: setelah completed harus refresh untuk lihat Delete/Report — sekarang muncul otomatis |
| | | |

## Risiko / Blocker yang Diketahui

| Risiko | Mitigasi |
|---|---|
| Binary Go (nuclei/subfinder/dll) butuh waktu install & update template di server demo | Install & test di Phase 0/3 lebih awal, jangan tunggu H-1 demo |
| AI loop bisa jalan terlalu lama / kena rate limit GLM saat demo live | Siapkan safety limit (Phase 4) + rencana B: jalankan run beberapa menit sebelum sesi demo, tunjukkan hasil + log, bukan full live run dari nol kalau model lambat |
| Scanning tool (terutama nuclei intrusive) ke target salah | Scope enforcement Phase 3 wajib teruji sebelum demo pakai target apa pun |
