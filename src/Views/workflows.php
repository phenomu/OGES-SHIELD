<?php
/**
 * workflows.php — Phase 3: template dari DB, recent runs dari DB,
 * Set Workflow = form POST nyata → RunController::create → halaman /run.
 */
View::$pageTitle = 'Automation Workflows — OGES-SHIELD';
View::$activeNav = 'workflows';

$user = Auth::user();
$templates = Database::run('SELECT slug, name, description, phases FROM workflow_templates ORDER BY id')->fetchAll();
foreach ($templates as &$t) {
    $t['phase_list'] = json_decode((string) $t['phases'], true) ?: [];
    $t['phase_count'] = count($t['phase_list']);
}
unset($t);

// run milik user (superadmin lihat semua)
$runs = Database::run(
    "SELECT r.id, r.status, r.created_at, r.current_phase,
            t.value AS target_value, wt.slug AS template_slug, wt.name AS template_name
     FROM workflow_runs r
     JOIN targets t ON t.id = r.target_id
     JOIN workflow_templates wt ON wt.id = r.template_id
     WHERE ? = 'superadmin' OR r.created_by = ?
     ORDER BY r.created_at DESC LIMIT 15",
    [$user['role'], (int) $user['id']]
)->fetchAll();

// targets in-scope milik user utk modal
$targets = Database::run(
    'SELECT id, name, value, type FROM targets WHERE in_scope = 1 AND user_id = ? ORDER BY id',
    [(int) $user['id']]
)->fetchAll();
if ($user['role'] === 'superadmin') {
    $targets = Database::run('SELECT id, name, value, type FROM targets WHERE in_scope = 1 ORDER BY id')->fetchAll();
}

$runStatusBadge = fn(string $s) => match ($s) {
    'running'   => '<span class="sev sev-low">RUNNING</span>',
    'completed' => '<span class="sev sev-info">COMPLETED</span>',
    'failed'    => '<span class="sev sev-critical">FAILED</span>',
    'stopped'   => '<span class="sev sev-medium">STOPPED</span>',
    default     => '<span class="sev sev-medium">PENDING</span>',
};
$wfError = Session::flash('wf_error');
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Automation Workflows</h1>
    <p class="page-sub">Set workflow → orchestrator menjalankan tools lintas phase (AI loop menyusul Phase 4)</p>
  </div>
  <div style="display:flex; gap: var(--sp-2)">
    <?php if ($user['role'] === 'superadmin'): ?>
      <button type="button" class="btn" data-modal-open="modal-new-template">+ Buat Template</button>
    <?php endif; ?>
    <button type="button" class="btn btn-primary" data-modal-open="modal-set-workflow">+ Set Workflow</button>
  </div>
</div>

<?php if ($wfError): ?>
  <div class="auth-alert error mb-3" style="border-radius: var(--r-md)"><?= e($wfError) ?></div>
<?php endif; ?>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Workflow Templates</h2>
        <p class="panel-subtitle">Blueprint alur phase (dari DB workflow_templates)</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Template</th><th>Phases</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($templates as $t): ?>
            <tr>
              <td>
                <span class="cell-strong"><?= e($t['name']) ?></span>
                <div class="text-muted" style="font-size: var(--fs-xxs)"><?= e((string) $t['description']) ?></div>
              </td>
              <td class="mono" style="font-size:10px; max-width:320px"><?= e(implode(' → ', array_map(fn($ph) => str_replace('_', ' ', $ph), $t['phase_list']))) ?></td>
              <td style="white-space:nowrap">
                <button type="button" class="btn btn-sm" data-modal-open="modal-set-workflow" data-pick-template="<?= e($t['slug']) ?>">Use</button>
                <?php if ($user['role'] === 'superadmin' && !in_array($t['slug'], ['full-web-app-assessment', 'external-recon-only'], true)): ?>
                  <form method="post" action="/templates/delete" style="display:inline" data-confirm="Hapus template <?= e($t['slug']) ?>? (hanya bisa kalau belum dipakai run)">
                    <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
                    <input type="hidden" name="slug" value="<?= e($t['slug']) ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$templates): ?>
            <tr><td colspan="3" class="text-muted">Belum ada template.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Recent Runs</h2>
        <p class="panel-subtitle">Eksekusi nyata (tabel workflow_runs)</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Run ID</th><th>Target</th><th>Template</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($runs as $r): ?>
            <tr>
              <td class="mono cell-strong"><a href="/run?id=<?= e($r['id']) ?>"><?= e(substr((string) $r['id'], 0, 8)) ?>…</a></td>
              <td class="mono"><?= e((string) $r['target_value']) ?></td>
              <td><?= e((string) $r['template_name']) ?></td>
              <td><?= $runStatusBadge((string) $r['status']) ?></td>
              <td class="mono"><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
              <td>
                <?php if (!in_array($r['status'], ['running', 'awaiting_confirmation', 'pending'], true)): ?>
                  <form method="post" action="/runs/delete" style="display:inline" data-confirm="Hapus run <?= e(substr((string) $r['id'], 0, 8)) ?>…? Event, findings, dan report ikut terhapus.">
                    <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
                    <input type="hidden" name="run_id" value="<?= e($r['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$runs): ?>
            <tr><td colspan="6" class="text-muted">Belum ada run — mulai dengan Set Workflow.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<!-- Modal Set Workflow — POST nyata -->
<div class="modal-backdrop" id="modal-set-workflow">
  <div class="modal" style="max-width:560px">
    <div class="modal-head">
      <span class="modal-title">Set Workflow</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form class="modal-body" method="post" action="/runs/create" id="form-set-workflow">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
      <div class="form-field">
        <label class="form-label" for="sw-target">TARGET (IN-SCOPE SAJA)</label>
        <select class="select mono" id="sw-target" name="target_id" required>
          <?php foreach ($targets as $t): ?>
            <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['value']) ?> (<?= e((string) $t['type']) ?>) — <?= e((string) $t['name']) ?></option>
          <?php endforeach; ?>
          <?php if (!$targets): ?>
            <option value="" disabled>Belum ada target in-scope — daftar dulu di Asset Discovery</option>
          <?php endif; ?>
        </select>
        <span class="form-hint">Hanya asset in-scope milikmu yang bisa dipilih — orchestrator menolak target lain (server-side).</span>
      </div>
      <div class="form-field">
        <label class="form-label" for="sw-template">WORKFLOW TEMPLATE</label>
        <select class="select" id="sw-template" name="template_slug" required>
          <?php foreach ($templates as $t): ?>
            <option value="<?= e((string) $t['slug']) ?>"><?= e((string) $t['name']) ?> (<?= $t['phase_count'] ?> phases)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label class="form-label">SAFETY LIMITS</label>
        <div class="env-status">
          <div class="env-item"><span>Max tool calls per phase</span><span class="st ok mono">10</span></div>
          <div class="env-item"><span>Max time per phase</span><span class="st ok mono">900s</span></div>
        </div>
      </div>
    </form>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="submit" form="form-set-workflow" class="btn btn-primary">Start Run</button>
    </div>
  </div>
</div>

<!-- Modal Buat Template (superadmin) -->
<div class="modal-backdrop" id="modal-new-template">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Buat Workflow Template</span>
      <button type="button" class="modal-x" data-modal-close>&times;</button>
    </div>
    <form method="post" action="/templates/create" id="form-new-template">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="nt-name">NAMA TEMPLATE</label>
          <input class="input" id="nt-name" name="name" maxlength="190" required placeholder="mis. Quick SQLi Check">
        </div>
        <div class="form-group">
          <label class="form-label" for="nt-slug">SLUG (identitas unik)</label>
          <input class="input mono" id="nt-slug" name="slug" maxlength="120" required placeholder="mis. quick-sqli-check" pattern="[a-z0-9-]{3,120}" title="huruf kecil, angka, tanda minus">
        </div>
        <div class="form-group">
          <label class="form-label" for="nt-desc">DESKRIPSI (opsional)</label>
          <input class="input" id="nt-desc" name="description" maxlength="300" placeholder="mis. scope + vuln scan ringan tanpa eksploitasi">
        </div>
        <div class="form-group">
          <label class="form-label">PHASES (urutan eksekusi; scope_validation otomatis di depan, report_generation selalu di akhir bila dipilih)</label>
          <div class="phase-checks" style="display:grid; grid-template-columns:1fr 1fr; gap:6px 16px; margin-top:6px">
            <?php
            $allPhases = ['reconnaissance', 'service_enumeration', 'vulnerability_assessment',
                          'exploitation_validation', 'persistence_simulation',
                          'cleanup_verification', 'report_generation'];
            foreach ($allPhases as $i => $ph): ?>
              <label style="display:flex; gap:8px; align-items:center; font-size:var(--fs-xs); cursor:pointer">
                <input type="checkbox" name="phases[]" value="<?= e($ph) ?>" id="nt-ph-<?= $i ?>">
                <?= e(str_replace('_', ' ', $ph)) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="form-hint">Minimal 2 phase. Phase tanpa tool terdaftar akan auto-complete (mis. persistence_simulation).</p>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary" form="form-new-template">Simpan Template</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  // tombol Use → preselect template di modal
  document.querySelectorAll("[data-pick-template]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var sel = document.getElementById("sw-template");
      if (sel) sel.value = btn.dataset.pickTemplate;
    });
  });
  // konfirmasi aksi destruktif (hapus run)
  document.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) {
      if (!window.confirm(f.dataset.confirm)) e.preventDefault();
    });
  });
})();
</script>
