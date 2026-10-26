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

<!-- Modal Buat Template (superadmin) — phase picker visual -->
<div class="modal-backdrop" id="modal-new-template">
  <div class="modal" style="max-width:720px">
    <div class="modal-head">
      <span class="modal-title">Buat Workflow Template</span>
      <button type="button" class="modal-x" data-modal-close>&times;</button>
    </div>
    <form method="post" action="/templates/create" id="form-new-template">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group" style="flex:2">
            <label class="form-label" for="nt-name">NAMA TEMPLATE</label>
            <input class="input" id="nt-name" name="name" maxlength="190" required placeholder="mis. Recon Ringan Custom">
          </div>
          <div class="form-group" style="flex:1">
            <label class="form-label" for="nt-slug">SLUG</label>
            <input class="input mono" id="nt-slug" name="slug" maxlength="120" required placeholder="recon-ringan" pattern="[a-z0-9-]{3,120}" title="huruf kecil, angka, minus">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="nt-desc">DESKRIPSI (opsional)</label>
          <input class="input" id="nt-desc" name="description" maxlength="300" placeholder="mis. scope + recon saja, ringan, tanpa eksploitasi">
        </div>

        <div class="form-group">
          <label class="form-label">PHASES — klik untuk aktif/nonaktif (scope validation otomatis di depan)</label>
          <div class="phase-picker">
            <?php
            // info tool per phase (dari tools.json enabled)
            $tjTools = json_decode((string) @file_get_contents(BASE_PATH . '/orchestrator/config/tools.json'), true);
            $phaseTools = [];
            if (is_array($tjTools) && isset($tjTools['tools'])) {
                foreach ($tjTools['tools'] as $tt) {
                    if (!is_array($tt) || !($tt['enabled'] ?? false)) continue;
                    foreach ((array) ($tt['valid_phases'] ?? []) as $tp) {
                        $phaseTools[$tp][] = (string) $tt['id'];
                    }
                }
            }
            $DASH = '—';
            $phaseMeta = [
                'reconnaissance'          => ['Passive discovery', 'Subdomain enumeration, DNS, WAF deteksi'],
                'service_enumeration'     => ['Live host & service', 'Probe HTTP, tech stack, web server'],
                'vulnerability_assessment'=> ['Vuln scanning', 'Nuclei template, indikasi SQLi'],
                'exploitation_validation' => ['Validasi exploit', 'Payload benign, konfirmasi temuan'],
                'persistence_simulation'  => ['Simulasi persistensi', 'Butuh tool khusus (belum ada ' . $DASH . ' tambah di Tool Modules)'],
                'cleanup_verification'    => ['Cleanup artefak', 'Verifikasi jejak eksploitasi dibersihkan'],
                'report_generation'       => ['Report PDF', 'Ringkasan eksekutif + findings, selalu terakhir'],
            ];
            $phaseIdx = 0;
            foreach ($phaseMeta as $ph => $meta): $phaseIdx++; ?>
              <label class="phase-card<?= in_array($ph, ['exploitation_validation', 'persistence_simulation'], true) ? ' phase-risk' : '' ?>">
                <input type="checkbox" name="phases[]" value="<?= e($ph) ?>" id="nt-ph-<?= $phaseIdx ?>" data-phase="<?= e($ph) ?>">
                <span class="phase-num mono" data-num>–</span>
                <span class="phase-body">
                  <span class="phase-name"><?= e(ucwords(str_replace('_', ' ', $ph))) ?></span>
                  <span class="phase-desc"><?= e($meta[1]) ?></span>
                  <span class="phase-tools mono<?= isset($phaseTools[$ph]) ? '' : ' none' ?>"><?= isset($phaseTools[$ph]) ? e(implode(', ', $phaseTools[$ph])) : 'no tools ' . $DASH . ' auto-complete' ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">PREVIEW PIPELINE (urutan eksekusi)</label>
          <div class="pipeline-preview mono" id="nt-preview">
            <span class="pp-phase pp-fixed">01 scope validation</span>
            <span class="pp-arrow">→</span>
            <span class="pp-empty">pilih phase di atas…</span>
          </div>
          <p class="form-hint" id="nt-hint">Pilih phase di atas — scope validation selalu pertama; report generation selalu terakhir. Minimal 2 phase.</p>
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
  // konfirmasi aksi destruktif (hapus run/template)
  document.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) {
      if (!window.confirm(f.dataset.confirm)) e.preventDefault();
    });
  });

  // ---- Phase picker interaktif: preview pipeline + nomor urut (modal buat template) ----
  var picker = document.getElementById("modal-new-template");
  if (picker) {
    var boxes = Array.prototype.slice.call(picker.querySelectorAll(".phase-card"));
    var preview = document.getElementById("nt-preview");
    var hint = document.getElementById("nt-hint");
    var submitBtn = picker.querySelector("button[type=submit]");

    function esc(s) {
      return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
      });
    }

    function render() {
      var phases = boxes.filter(function (c) {
        return c.querySelector("input").checked;
      }).map(function (c) { return c.querySelector("input").value; });
      // scope validation selalu pertama (fixed), report generation dipaksa ke akhir
      var rest = phases.filter(function (p) { return p !== "report_generation"; });
      var hasReport = phases.indexOf("report_generation") >= 0;
      var seq = ["scope_validation"].concat(rest);
      if (hasReport) seq.push("report_generation");

      // nomor urut di kartu sesuai urutan eksekusi
      boxes.forEach(function (c) {
        var v = c.querySelector("input").value;
        var idx = seq.indexOf(v);
        var num = c.querySelector("[data-num]");
        if (num) num.textContent = idx >= 0 ? String(idx + 1).padStart(2, "0") : "–";
        c.classList.toggle("on", idx >= 0);
      });

      // preview pipeline
      if (preview) {
        preview.innerHTML = seq.length > 1
          ? seq.map(function (v, i) {
              var risk = (v === "exploitation_validation" || v === "persistence_simulation");
              return (i ? '<span class="pp-arrow">→</span>' : "") +
                '<span class="pp-phase' + (risk ? " pp-risk" : "") + (i === 0 ? " pp-fixed" : "") + '">' +
                String(i + 1).padStart(2, "0") + " " + esc(v.replace(/_/g, " ")) + "</span>";
            }).join("")
          : '<span class="pp-empty">pilih phase di atas…</span>';
      }

      var total = seq.length;
      if (hint) {
        hint.textContent = total < 2
          ? "Minimal 2 phase (termasuk scope validation) — pilih lagi."
          : total + " phase akan dieksekusi berurutan. Scope validation selalu pertama" +
            (hasReport ? "; report generation selalu terakhir." : ". Tanpa report generation, PDF tidak dibuat otomatis.");
      }
      if (submitBtn) submitBtn.disabled = total < 2;
    }

    boxes.forEach(function (c) {
      c.addEventListener("click", function () {
        var cb = c.querySelector("input");
        cb.checked = !cb.checked;
        render();
      });
    });
    render();
  }
})();
</script>
