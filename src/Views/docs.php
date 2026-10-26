<?php
/**
 * docs.php — Dokumentasi cara pakai OGES-SHIELD (Phase 6).
 * Step-by-step: onboarding, workflow, approval, tool registry, live run, findings, report.
 */
View::$pageTitle = 'Dokumentasi — OGES-SHIELD';
View::$activeNav = 'docs';
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
          <p class="ailog-text">Superadmin membuka <b>Admin → Approvals</b>, melihat justifikasi kamu, lalu <b>Approve</b> (akun aktif) atau <b>Reject</b> + alasan (akun ditandai rejected, tetap tercatat di audit). Untuk apa: kontrol akses — tidak ada orang asing yang bisa memakai platform scanning ini.</p>
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
          <p class="ailog-text">Menu <b>Automation Workflows</b> → tombol <b>Set Workflow</b>: pilih target in-scope + template. Template = urutan phase, mis. <span class="mono">full-web-app-assessment</span> (7 phase: scope validation → recon → service enum → vulnerability assessment → exploitation → cleanup → report) atau <span class="mono">external-recon-only</span> (ringan, tanpa scanning intrusif).</p>
              <div class="ailog-entry"><span class="ailog-time">01b</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">BUAT TEMPLATE SENDIRI</span>
          <p class="ailog-text">Superadmin bisa bikin template custom: tombol <b>+ Buat Template</b> di Workflows. Klik kartu phase untuk menyusun alur (nomor urut + preview pipeline update live) — mis. recon + persistence simulation + report. Kartu menampilkan tool yang tersedia per phase; phase <i>tanpa tool</i> (mis. persistence) akan auto-complete saat run — supaya phase itu benar-benar jalan, tambahkan tool di menu <b>Python/Tool Modules</b> dengan <span class="mono">valid_phases: ["persistence_simulation"]</span>. Guardrail server-side: scope validation selalu pertama, report generation selalu terakhir, minimal 2 phase.</p>
        </div>
      </div>
</div>
      </div>
      <div class="ailog-entry"><span class="ailog-time">02</span>
        <div class="ailog-body"><span class="ailog-tag ai_decision">AI LOOP</span>
          <p class="ailog-text">Setelah Start, halaman Live Run terbuka. Di tiap phase, AI (GLM-5.3) membaca hasil sejauh ini → memilih 1 tool paling relevan → tool dieksekusi → hasil diringkas AI → ulang sampai AI menilai phase cukup (<span class="mono">mark_phase_complete</span>) atau safety limit tercapai (<span class="mono">max 10 tool/phase, 15 menit/phase</span> — loop tidak bisa infinite).</p>
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
          <p class="ailog-text"><b>Stop Run</b> = hentikan paksa (tool berjalan di-terminate, status STOPPED — permanen, tidak bisa di-resume). <b>Resume</b> hanya muncul untuk run <span class="sev sev-medium">PAUSED</span> (menunggu konfirmasi destructive) — membuka kembali modal keputusan. <b>Delete</b> = hapus run + semua event + findings-nya (konfirmasi modal, tercatat di audit). Run completed/stopped tidak bisa di-resume — buat run baru di Workflows.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">4. Findings &amp; Report PDF</h2>
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
</div>

<div class="grid-mid mt-4">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">5. Admin — Tool Registry</h2>
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
        <div class="ailog-body"><span class="ailog-tag ai_decision">GUARDRAIL</span>
          <p class="ailog-text">AI tidak pernah melihat command_template — hanya input_schema. Orchestrator merakit command dari template + parameter tervalidasi (subprocess array arg, tanpa shell). Parameter di luar scope target ditolak.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">6. Halaman Lain</h2>
        <p class="panel-subtitle">Referensi cepat</p>
      </div>
    </div>
    <div class="fd-fields">
      <div class="fd-field"><span class="fd-label">OVERVIEW</span><span class="fd-value">Dashboard snapshot — stat real-time, AI Orchestration Log (keputusan AI live), Security Summary.</span></div>
      <div class="fd-field"><span class="fd-label">RECONNAISSANCE</span><span class="fd-value">Hasil recon run terbaru (subdomain, host live, tech stack, WAF).</span></div>
      <div class="fd-field"><span class="fd-label">VULN ASSESSMENT</span><span class="fd-value">Tabel findings dengan detail panel (sama sumber dengan menu Findings).</span></div>
      <div class="fd-field"><span class="fd-label">AUDIT LOGS</span><span class="fd-value">Jejak semua aksi: login, tool eksekusi, scope violation, konfirmasi, report. Tidak bisa dihapus dari UI.</span></div>
      <div class="fd-field"><span class="fd-label">SETTINGS</span><span class="fd-value">Info akun + keamanan.</span></div>
      <div class="fd-field"><span class="fd-label">AKUN DEMO</span><span class="fd-value mono">admin (superadmin) · rani@team.id (user)</span></div>
    </div>
  </section>
</div>
