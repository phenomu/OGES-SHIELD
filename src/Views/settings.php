<?php
/**
 * settings.php — Settings (Phase 6): data asli.
 * Akun, AI engine (health orchestrator), Current Engagement selector.
 */
View::$pageTitle = 'Settings — OGES-SHIELD';
View::$activeNav = 'settings';

$user = Auth::user();
$isSuper = ($user['role'] ?? '') === 'superadmin';
$activeRun = ActiveRun::current($user);
$runOptions = ActiveRun::list($user, 30);

// health orchestrator (internal, localhost-only — jangan expose lebih dari status)
$aiHealth = null;
$ch = @curl_init('http://127.0.0.1:8100/health');
if ($ch) {
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
    $resp = @curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp !== false && $code === 200) {
        $aiHealth = json_decode((string) $resp, true);
    }
}
$aiOnline = is_array($aiHealth) && ($aiHealth['status'] ?? '') === 'ok';
$aiVersion = (string) ($aiHealth['version'] ?? '—');
$toolsLoaded = (int) ($aiHealth['tools_loaded'] ?? $aiHealth['tools'] ?? 0);
$model = (string) ($aiHealth['model'] ?? 'glm-5.3');

$flash = Session::flash('wf_success') ?? Session::flash('run_error');
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Settings</h1>
    <p class="page-sub">Konfigurasi platform — akun, AI engine, current engagement</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="auth-alert ok"><?= e($flash) ?></div>
<?php endif; ?>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Current Engagement</h2>
        <p class="panel-subtitle">Run aktif — dipakai topbar &amp; halaman Recon / Vulnerability / Exploitation</p>
      </div>
      <?= $activeRun ? '<span class="badge badge-cyan"><span class="dot"></span>' . e(strtoupper((string) $activeRun['status'])) . '</span>' : '' ?>
    </div>
    <?php if ($activeRun): ?>
      <div class="fd-fields">
        <div class="fd-field"><span class="fd-label">RUN ID</span><span class="fd-value mono">RUN-<?= e(substr((string) $activeRun['id'], 0, 12)) ?>…</span></div>
        <div class="fd-field"><span class="fd-label">TEMPLATE</span><span class="fd-value"><?= e((string) $activeRun['template_name']) ?></span></div>
        <div class="fd-field"><span class="fd-label">TARGET</span><span class="fd-value mono"><?= e((string) $activeRun['target_value']) ?></span></div>
        <div class="fd-field"><span class="fd-label">ENVIRONMENT</span><span class="fd-value"><span class="sev sev-info"><?= e(strtoupper((string) $activeRun['environment'])) ?></span></span></div>
        <div class="fd-field"><span class="fd-label">DIBUAT</span><span class="fd-value mono"><?= e(substr((string) $activeRun['created_at'], 0, 16)) ?></span></div>
      </div>
    <?php else: ?>
      <p class="form-hint">Belum ada run — buat via menu Workflows.</p>
    <?php endif; ?>

    <form method="post" action="/engagement/select" class="mt-4">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
      <div class="form-row">
        <div class="form-group" style="flex:1">
          <label class="form-label" for="eng-run">Ganti engagement (run milikmu)</label>
          <select class="input mono" id="eng-run" name="run_id" required>
            <?php foreach ($runOptions as $ro): ?>
              <option value="<?= e($ro['id']) ?>"<?= $activeRun && $ro['id'] === $activeRun['id'] ? ' selected' : '' ?>>
                RUN-<?= e(substr((string) $ro['id'], 0, 8)) ?> · <?= e(mbTrunc((string) $ro['target_value'], 30)) ?> · <?= e(strtoupper((string) $ro['status'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">&nbsp;</label>
          <div style="display:flex; gap:var(--sp-2)">
            <button type="submit" class="btn btn-primary">Pilih</button>
            <button type="submit" class="btn" form="form-eng-reset">⟲ Terbaru</button>
          </div>
        </div>
      </div>
    </form>
    <form method="post" action="/engagement/reset" id="form-eng-reset" class="hidden-form">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
    </form>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">AI Engine</h2>
        <p class="panel-subtitle">Orchestrator &amp; model — koneksi internal localhost</p>
      </div>
      <span class="badge <?= $aiOnline ? 'badge-cyan' : 'badge-danger' ?>"><span class="dot"></span><?= $aiOnline ? 'ONLINE' : 'OFFLINE' ?></span>
    </div>
    <div class="fd-fields">
      <div class="fd-field"><span class="fd-label">MODEL UTAMA</span><span class="fd-value mono"><?= e($model) ?></span></div>
      <div class="fd-field"><span class="fd-label">FALLBACK</span><span class="fd-value mono">glm-5.2</span></div>
      <div class="fd-field"><span class="fd-label">ORCHESTRATOR</span><span class="fd-value mono">127.0.0.1:8100 · v<?= e($aiVersion) ?></span></div>
      <div class="fd-field"><span class="fd-label">TOOLS TERBACA</span><span class="fd-value mono"><?= $toolsLoaded ?> tool</span></div>
      <div class="fd-field"><span class="fd-label">SAFETY LIMIT</span><span class="fd-value mono">10 tool calls/phase · 900s/phase</span></div>
      <div class="fd-field"><span class="fd-label">API KEY</span><span class="fd-value mono">•••••••• (hidden, hanya di orchestrator .env)</span></div>
    </div>
  </section>
</div>

<div class="grid-mid mt-4">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Akun</h2>
        <p class="panel-subtitle">Identitas &amp; keamanan sesi</p>
      </div>
    </div>
    <div class="fd-fields">
      <div class="fd-field"><span class="fd-label">NAMA</span><span class="fd-value"><?= e((string) $user['name']) ?></span></div>
      <div class="fd-field"><span class="fd-label">USERNAME</span><span class="fd-value mono"><?= e((string) $user['username']) ?></span></div>
      <div class="fd-field"><span class="fd-label">EMAIL</span><span class="fd-value mono"><?= e((string) $user['email']) ?></span></div>
      <div class="fd-field"><span class="fd-label">ROLE</span><span class="fd-value"><span class="sev <?= $isSuper ? 'sev-critical' : 'sev-info' ?>"><?= e(Auth::roleLabel((string) $user['role'])) ?></span></span></div>
      <div class="fd-field"><span class="fd-label">STATUS</span><span class="fd-value"><span class="sev sev-low">ACTIVE</span></span></div>
    </div>
    <p class="form-hint mt-3">Sesi: cookie httponly + samesite Lax; CSRF token di semua form POST; CSP nonce per-request. Ganti password belum tersedia (roadmap).</p>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Integrasi</h2>
        <p class="panel-subtitle">Status dependency platform</p>
      </div>
    </div>
    <div class="env-status">
      <div class="env-item"><span>9router base URL</span><span class="st <?= $aiOnline ? 'ok' : 'err' ?> mono"><?= $aiOnline ? 'configured' : 'unreachable' ?></span></div>
      <div class="env-item"><span>MySQL database</span><span class="st ok mono">connected</span></div>
      <div class="env-item"><span>Go tools path (bin/)</span><span class="st ok mono">detected</span></div>
      <div class="env-item"><span>Report storage</span><span class="st ok mono">storage/reports</span></div>
      <div class="env-item"><span>Evidence storage</span><span class="st ok mono">storage/evidence</span></div>
    </div>
    <div class="quick-actions mt-4">
      <a class="btn" href="/docs">Buka Dokumentasi</a>
      <a class="btn" href="/admin/tool-registry">Tool Registry</a>
      <a class="btn" href="/audit">Audit Logs</a>
    </div>
  </section>
</div>
