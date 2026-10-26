<?php
/**
 * settings.php — Settings (menu sidebar; fungsional Phase 6, placeholder sekarang).
 */
View::$pageTitle = 'Settings — OGES-SHIELD';
View::$activeNav = 'settings';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Settings</h1>
    <p class="page-sub">Konfigurasi platform — sebagian besar read-only Phase 1</p>
  </div>
</div>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Engagement</h2>
        <p class="panel-subtitle">Identitas engagement aktif</p>
      </div>
    </div>
    <div class="fd-fields">
      <div class="fd-field"><span class="fd-label">NAMA ENGAGEMENT</span><span class="fd-value mono">ACME_CORP_ASSESSMENT_2026</span></div>
      <div class="fd-field"><span class="fd-label">ENGAGEMENT ID</span><span class="fd-value mono">ENG-2026-0417</span></div>
      <div class="fd-field"><span class="fd-label">ENVIRONMENT AKTIF</span><span class="fd-value"><span class="sev sev-info">LAB</span></span></div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">AI Engine</h2>
        <p class="panel-subtitle">Model & koneksi orchestrator</p>
      </div>
      <span class="badge badge-cyan"><span class="dot"></span>ONLINE</span>
    </div>
    <div class="fd-fields">
      <div class="fd-field"><span class="fd-label">MODEL UTAMA</span><span class="fd-value mono">glm-5.3</span></div>
      <div class="fd-field"><span class="fd-label">FALLBACK</span><span class="fd-value mono">glm-5.2</span></div>
      <div class="fd-field"><span class="fd-label">SAFETY LIMIT</span><span class="fd-value mono">10 tool calls/phase · 900s/phase</span></div>
      <div class="fd-field"><span class="fd-label">API KEY</span><span class="fd-value mono">•••••••• (hidden, hanya di orchestrator .env)</span></div>
    </div>
  </section>
</div>

<section class="panel mt-4">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Integrasi & Danger Zone</h2>
      <p class="panel-subtitle">Placeholder Phase 1 — form aktif Phase 6</p>
    </div>
  </div>
  <div class="env-status">
    <div class="env-item"><span>9router base URL</span><span class="st ok mono">configured</span></div>
    <div class="env-item"><span>Go tools path (bin/)</span><span class="st ok mono">detected</span></div>
    <div class="env-item"><span>Report storage</span><span class="st ok mono">storage/reports</span></div>
  </div>
  <div class="quick-actions mt-4">
    <button type="button" class="btn" disabled title="Phase 6">Test AI Connection</button>
    <button type="button" class="btn" disabled title="Phase 6">Reload Tool Registry</button>
    <button type="button" class="btn btn-danger" data-modal-open="modal-purge" disabled title="Phase 6">Purge Audit Logs</button>
  </div>
</section>
