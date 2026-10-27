<?php
/**
 * docs.php — Dokumentasi cara pakai OGES-SHIELD (Phase 6).
 * Step-by-step: onboarding, workflow, template custom, approval, tool registry, findings, report.
 */
View::$pageTitle = 'Dokumentasi — OGES-SHIELD';
View::$activeNav = 'docs';
$AR = '→';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Dokumentasi</h1>
    <p class="page-sub">Panduan penggunaan platform — dari akun baru sampai laporan PDF</p>
  </div>
  <span class="badge badge-cyan"><span class="dot"></span>OGES-SHIELD v1.0</span>
</div>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">1. Mulai — Akun &amp; Approval</h2>
        <p class="panel-subtitle">Alur onboarding pengguna baru</p>
      </div>
    </div>
    <div class="ailog">
      <div class="ailog-entry"><span class="ailog-time">01</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">REGISTER</span>
          <p class="ailog-text">Buka halaman <span class="mono">/register</span> — isi nama, email, username, password, dan <b>justifikasi akses</b> (alasan kamu butuh akses, akan dibaca superadmin). Akun baru otomatis berstatus <span class="sev sev-medium">PENDING</span>.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">02</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">APPROVAL</span>
          <p class="ailog-text">Superadmin membuka <b>Admin → Approvals</b>, melihat justifikasi kamu, lalu <b>Approve</b> (akun aktif) atau <b>Reject</b> + alasan (akun ditandai rejected, tetap tercatat di audit). <b>Untuk apa:</b> kontrol akses — tidak ada orang asing yang bisa memakai platform scanning ini.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">03</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">LOGIN</span>
          <p class="ailog-text">Setelah approve → login di <span class="mono">/login</span>. Akun pending/rejected akan ditolak dengan pesan jelas. Semua password di-hash bcrypt, session PHP + CSRF token di setiap form.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">2. Daftarkan Target (Scope)</h2>
        <p class="panel-subtitle">Asset Discovery — HANYA asset terdaftar yang bisa di-scan</p>
      </div>
    </div>
    <div class="ailog">
      <div class="ailog-entry"><span class="ailog-time">01</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">REGISTER TARGET</span>
          <p class="ailog-text">Menu <b>Asset Discovery</b> → tombol <b>Register Target</b>. Isi: nama, tipe (<span class="mono">domain</span> / <span class="mono">url</span> / <span class="mono">ip</span> / <span class="mono">cidr</span>), nilai (mis. <span class="mono">low.my.id</span> atau <span class="mono">https://target.com/?id=1</span>), environment. Validasi per-tipe dijalankan server-side.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">02</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">SCOPE</span>
          <p class="ailog-text">Setiap target punya toggle <b>In Scope / Out of Scope</b>. Guardrail server-side: orchestrator menolak eksekusi tool ke host di luar scope milik run — parameter AI yang menyimpang akan ditolak dan tercatat sebagai security event. Tidak ada hardcoded target.</p>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="grid-mid mt-4">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">3. Jalankan Workflow (Fitur Inti)</h2>
        <p class="panel-subtitle">AI orchestrator memilih &amp; menjalankan tools secara otonom</p>
      </div>
    </div>
    <div class="ailog">
      <div class="ailog-entry"><span class="ailog-time">01</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">SET WORKFLOW</span>
          <p class="ailog-text">Menu <b>Automation Workflows</b> → tombol <b>Set Workflow</b>: pilih target in-scope + template. Template = urutan phase, mis. <span class="mono">full-web-app-assessment</span> (7 phase: scope validation <?= $AR ?> recon <?= $AR ?> service enum <?= $AR ?> vulnerability <?= $AR ?> exploitation <?= $AR ?> cleanup <?= $AR ?> report) atau <span class="mono">external-recon-only</span> (ringan, tanpa scanning intrusif). Klik <b>Start Run</b> → halaman Live Run terbuka.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">02</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">AI LOOP</span>
          <p class="ailog-text">Di tiap phase, AI (GLM-5.3) membaca hasil sejauh ini → memilih 1 tool paling relevan → tool dieksekusi → hasil diringkas AI → ulang sampai AI menilai phase cukup (<span class="mono">mark_phase_complete</span>) atau safety limit tercapai (<span class="mono">max 10 tool/phase, 15 menit/phase</span> — loop tidak bisa infinite).</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">03</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">LIVE</span>
          <p class="ailog-text">Panel <b>Operations Overview</b> (stepper phase + progress + elapsed) dan <b>Module Pipeline</b> update real-time (polling 2 detik). Terminal menampilkan event asli orchestrator: tool start/complete, ai_decision, ai_summary, scope violation.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">04</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">KONFIRMASI</span>
          <p class="ailog-text">Kalau AI memilih tool <span class="sev sev-critical">DESTRUCTIVE</span> → run otomatis PAUSE, modal konfirmasi muncul, kamu klik <b>Izinkan</b> / <b>Tolak</b>. Ditolak = tool di-skip dan tidak ditawarkan lagi di phase itu. Tool dianggap destructive lewat risk level di Tool Registry.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">05</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">STOP / RESUME / DELETE</span>
          <p class="ailog-text"><b>Stop Run</b> = hentikan paksa (tool berjalan di-terminate, status STOPPED — permanen, tidak bisa di-resume). <b>Resume</b> hanya muncul untuk run <span class="sev sev-medium">AWAITING CONFIRMATION</span> (menunggu konfirmasi destructive) — membuka kembali modal keputusan. <b>Delete</b> = hapus run + semua event + findings-nya (konfirmasi modal, tercatat di audit). Run completed/stopped tidak bisa di-resume — buat run baru di Workflows.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">06</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">CURRENT ENGAGEMENT</span>
          <p class="ailog-text">Topbar kiri menampilkan <b>CURRENT ENGAGEMENT</b> (run aktif) — klik untuk ganti: pilih run mana pun milikmu dari dropdown, atau <b>⟲ Kembali ke run terbaru</b>. Halaman <b>Reconnaissance / Vulnerability / Exploitation / Settings</b> menampilkan data dari run yang dipilih ini. Bisa juga diganti lewat <b>Settings</b> (dropdown + tombol Pilih / ⟲ Terbaru sebaris).</p>
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">4. Buat Template Workflow Sendiri</h2>
        <p class="panel-subtitle">Susun urutan phase sesuai kebutuhan — khusus superadmin</p>
      </div>
    </div>
    <div class="ailog">
      <div class="ailog-entry"><span class="ailog-time">01</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">BUKA MODAL</span>
          <p class="ailog-text">Menu <b>Automation Workflows</b> → tombol <b>+ Buat Template</b> (hanya muncul untuk superadmin). Template bawaan (full-web-app-assessment, external-recon-only) tidak bisa dihapus — template buatanmu bisa.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">02</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">ISI IDENTITAS</span>
          <p class="ailog-text"><b>Nama</b> bebas (mis. "Recon Ringan Custom") · <b>Slug</b> = identitas unik huruf kecil/angka/minus tanpa spasi (mis. <span class="mono">recon-ringan-custom</span>) · <b>Deskripsi</b> opsional. Slug dipakai sistem — jangan duplikat template lain.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">03</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">PILIH PHASE</span>
          <p class="ailog-text">Klik kartu phase untuk aktif/nonaktif. Tiap kartu menampilkan: nomor urut eksekusi (live), nama + deskripsi singkat, dan <b>tool yang tersedia</b> di phase itu. Kartu dengan badge <span class="sev sev-medium">RISK</span> (exploitation, persistence) menandai phase intrusif. Contoh isi kartu: <i>Reconnaissance — subdomain, dns, waf deteksi — subfinder, httpx</i>.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">04</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">LIHAT PREVIEW</span>
          <p class="ailog-text">Panel <b>PREVIEW PIPELINE</b> di bawah menunjukkan urutan eksekusi final secara live, mis.: <span class="mono">01 scope validation <?= $AR ?> 02 reconnaissance <?= $AR ?> 03 persistence simulation <?= $AR ?> 04 report generation</span>. Aturan otomatis (dijaga server-side, tidak bisa diakali): <b>scope validation selalu pertama</b>, <b>report generation selalu terakhir</b> (biar PDF dibuat), minimal 2 phase.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">05</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">PHASE TANPA TOOL</span>
          <p class="ailog-text">Kartu bertanda <i>"no tools — auto-complete"</i> (mis. <b>persistence simulation</b>) = belum ada tool terdaftar untuk phase itu. Run tetap jalan — phase-nya hanya langsung selesai sendiri. <b>Supaya phase benar-benar bekerja:</b> tambahkan tool untuk phase itu (lihat section 6 — Tool Registry, set <span class="mono">valid_phases</span> ke phase tsb). Setelah tool ditambah, template tidak perlu diubah — phase otomatis pakai tool baru.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">06</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">SIMPAN &amp; PAKAI</span>
          <p class="ailog-text">Klik <b>Simpan Template</b> → template muncul di daftar (kolom Phases menampilkan urutannya). Pakai lewat <b>Use</b> / <b>Set Workflow</b> seperti biasa. <b>Hapus</b>: tombol Hapus di baris template — hanya bisa kalau template belum pernah dipakai run (jejak audit dijaga).</p>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="grid-mid mt-4">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">5. Findings &amp; Report PDF</h2>
        <p class="panel-subtitle">Output akhir workflow — otomatis</p>
      </div>
    </div>
    <div class="ailog">
      <div class="ailog-entry"><span class="ailog-time">01</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">FINDINGS</span>
          <p class="ailog-text">Hasil positif tool (nuclei match, indikasi SQLi) otomatis masuk tabel findings. AI lalu memperkaya tiap temuan: mengisi summary/impact/remediation + <b>validasi ulang severity</b> (tool kadang over-report). Buka menu <b>Findings</b>: filter severity, klik baris → detail lengkap.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">02</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">REPORT</span>
          <p class="ailog-text">Saat run completed → 1 panggilan AI terakhir membuat <b>Overall Summary</b> (ringkasan eksekutif + 3 rekomendasi prioritas) → <span class="mono">report.pdf</span> digenerate otomatis (WeasyPrint). Buka menu <b>Reports</b> → <b>Download PDF</b> (3+ halaman: cover, executive summary, severity table, detail per finding, methodology). Tombol <b>Regenerate</b> membuat ulang PDF dengan data terbaru.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">6. Admin — Tool Registry</h2>
        <p class="panel-subtitle">Kelola tools.json yang dipakai AI (khusus superadmin)</p>
      </div>
    </div>
    <div class="ailog">
      <div class="ailog-entry"><span class="ailog-time">01</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">AKTIF/NONAKTIF</span>
          <p class="ailog-text">Menu <b>Python/Tool Modules</b> (atau Admin → Tool Registry). Toggle <b>enabled</b> ON/OFF — tool OFF tidak terlihat AI (tidak akan dipilih). Bisa juga <b>rotate risk level</b>: safe → intrusive → destructive (destructive otomatis wajib konfirmasi user).</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">02</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">NAMBAH TOOL</span>
          <p class="ailog-text">Klik <b>Raw JSON</b> di tool mana pun → editor terbuka berisi array tools.json lengkap. Tambah objek tool baru dengan field wajib: <span class="mono">id</span>, <span class="mono">name</span>, <span class="mono">category</span> (recon/vulnerability/…), <span class="mono">valid_phases</span>, <span class="mono">risk_level</span>, <span class="mono">command_template</span> (placeholder <span class="mono">{param}</span>), <span class="mono">input_schema</span> (JSON schema — AI hanya melihat ini), <span class="mono">output_parser</span> (jsonlines/json/text), <span class="mono">timeout_seconds</span>, <span class="mono">enabled</span>, <span class="mono">description</span>. Simpan → kalau schema ditolak validator, file otomatis <b>di-revert</b> (registry tidak pernah rusak menetap) dan pesan error tampil. Binari tool Go ditaruh di <span class="mono">bin/</span>, tool Python di <span class="mono">orchestrator/tools/</span> dengan output JSON ke stdout.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">03</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">CONTOH: TOOL PERSISTENCE</span>
          <p class="ailog-text">Pola: bikin file di <span class="mono">orchestrator/tools/</span> (baca param dari arg CLI, output JSON per baris ke stdout, payload benign), lalu daftarkan di Raw JSON (contoh lengkap: <span class="mono">wp_check.py</span> deteksi versi WordPress + match CVE offline, <span class="mono">php_check.py</span> deteksi versi PHP + CVE, <span class="mono">php2shell.py</span> validasi exploit POC benign). Untuk persistence: <span class="mono">"id": "custom_persistence_check"</span>, <span class="mono">"valid_phases": ["persistence_simulation"]</span>, <span class="mono">"command_template": "python3 {script_path} --url {target_url}"</span>, dst. Field <span class="mono">script_path</span> diisi OTOMATIS orchestrator (AI tidak melihat field itu). Tool dengan output <span class="mono">vulnerable:true</span> / <span class="mono">exploit_path_valid:true</span> / daftar <span class="mono">cves</span> otomatis jadi findings (mapper di <span class="mono">orchestrator/findings.py</span>).</p>
              <div class="ailog-entry"><span class="ailog-time">03b</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">CONTOH: CHAIN EXPLOIT OTOMATIS</span>
          <p class="ailog-text">Contoh nyata yang sudah teruji di platform (target demo mock WP di <span class="mono">127.0.0.1:8899</span>): <b>recon</b> (httpx deteksi tech) → <b>service enum</b> (AI pilih <span class="mono">wp_check</span> + <span class="mono">php_check</span> karena tech WordPress/PHP) → <b>findings otomatis</b> (versi WP 5.6.40 + PHP 5.6.40 + daftar CVE match) → <b>exploitation</b> (AI pilih <span class="mono">php2shell</span> validasi POC benign → finding CRITICAL "exploit path valid") → <b>report.pdf</b>. AI memutuskan tool berikutnya dari hasil tool sebelumnya — full autonomous, tanpa campur tangan. Ganti/daftarkan tool sembarang (mis. "wp2shell") dengan pola yang sama: file tool + Raw JSON.</p>
        </div>
      </div>
</div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">04</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">GUARDRAIL</span>
          <p class="ailog-text">AI tidak pernah melihat command_template — hanya input_schema. Orchestrator merakit command dari template + parameter tervalidasi (subprocess array arg, tanpa shell). Parameter di luar scope target ditolak.</p>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="grid-mid mt-4">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">7. Halaman Lain</h2>
        <p class="panel-subtitle">Referensi cepat</p>
      </div>
    </div>
    <div class="fd-fields">
      <div class="fd-field"><span class="fd-label">OVERVIEW</span><span class="fd-value">Dashboard snapshot — stat real-time, AI Orchestration Log (keputusan AI live), Security Summary.</span></div>
      <div class="fd-field"><span class="fd-label">RECONNAISSANCE</span><span class="fd-value">Hasil recon run aktif: subdomain, host live + IP, DNS, service, timeline discovery. Ikut Current Engagement.</span></div>
      <div class="fd-field"><span class="fd-label">VULN ASSESSMENT</span><span class="fd-value">Tabel findings semua run milikmu + detail panel (sumber sama dengan menu Findings).</span></div>
      <div class="fd-field"><span class="fd-label">EXPLOITATION</span><span class="fd-value">Attempt validasi (custom_sqli_probe, nuclei) dari run aktif + findings terkait.</span></div>
      <div class="fd-field"><span class="fd-label">PERSISTENCE</span><span class="fd-value">Slot fase persistensi — akan aktif setelah tool persistence didaftarkan (lihat section 6).</span></div>
      <div class="fd-field"><span class="fd-label">AUDIT LOGS</span><span class="fd-value">Jejak semua aksi: login, tool eksekusi, scope violation, konfirmasi, report. Tidak bisa dihapus dari UI.</span></div>
      <div class="fd-field"><span class="fd-label">SETTINGS</span><span class="fd-value">Akun, AI engine (health orchestrator), integrasi, dan pengganti Current Engagement.</span></div>
      <div class="fd-field"><span class="fd-label">AKUN DEMO</span><span class="fd-value mono">admin (superadmin) · rani@team.id (user) · tester@oges.local (user)</span></div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">8. Urutan Cepat (TL;DR)</h2>
        <p class="panel-subtitle">6 langkah dari nol sampai PDF</p>
      </div>
    </div>
    <div class="ailog">
      <div class="ailog-entry"><span class="ailog-time">1</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">AKUN</span>
          <p class="ailog-text">Register → admin approve → login.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">2</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">TARGET</span>
          <p class="ailog-text">Asset Discovery → Register Target (mis. domain kamu) → pastikan In Scope.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">3</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">TEMPLATE</span>
          <p class="ailog-text">Pakai template bawaan, atau bikin sendiri (+ Buat Template — section 4).</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">4</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">RUN</span>
          <p class="ailog-text">Set Workflow → target + template → Start Run → pantau Live Run (jawab konfirmasi destructive kalau muncul).</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">5</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">FINDINGS</span>
          <p class="ailog-text">Menu Findings / Vulnerability Assessment — lihat detail tiap temuan.</p>
        </div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">6</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">REPORT</span>
          <p class="ailog-text">Menu Reports → Download PDF. Selesai.</p>
        </div>
      </div>
    </div>
  </section>
</div>
