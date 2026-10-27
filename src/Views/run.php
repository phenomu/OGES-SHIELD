<?php
/**
 * run.php — halaman live run (Phase 4): event real-time via /api/stream.php.
 * Stepper dashboard-style (circle, label, durasi, status) + ops-meta live
 * (progress, current module, elapsed, completed tasks) + module pipeline baru.
 */
View::$pageTitle = 'Live Run — OGES-SHIELD';
View::$activeNav = 'workflows';

/** @var array $run */
/** @var string $runId */
/** @var array $phases */
/** @var array $tools */
$phases = $phases ?: [];
$tools  = $tools ?: [];

$statusBadge = fn(string $s) => match ($s) {
    'pending'   => '<span class="sev sev-medium">PENDING</span>',
    'running'   => '<span class="sev sev-info">RUNNING</span>',
    'awaiting_confirmation' => '<span class="sev sev-medium">AWAITING CONFIRMATION</span>',
    'completed' => '<span class="sev sev-info">COMPLETED</span>',
    'failed'    => '<span class="sev sev-critical">FAILED</span>',
    'stopped'   => '<span class="sev sev-medium">STOPPED</span>',
    default     => '<span class="sev sev-info">' . strtoupper($s) . '</span>',
};
$labelFor = fn(string $p) => match ($p) {
    'scope_validation'        => 'Scope Validation',
    'reconnaissance'          => 'Reconnaissance',
    'service_enumeration'     => 'Service Enumeration',
    'vulnerability_assessment' => 'Vulnerability Assess.',
    'exploitation_validation' => 'Exploitation Validation',
    'persistence_simulation'  => 'Persistence Simulation',
    'cleanup_verification'    => 'Cleanup & Verification',
    'report_generation'       => 'Report Generation',
    default                   => str_replace('_', ' ', $p),
};
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Live Run</h1>
    <p class="page-sub mono"><?= e($runId) ?></p>
  </div>
  <div style="display:flex; gap: var(--sp-2); align-items:center" data-run-actions data-run-id="<?= e($runId) ?>" data-run-status="<?= e((string) $run['status']) ?>" data-csrf="<?= e(Session::csrf()) ?>">
    <?= $statusBadge((string) $run['status']) ?>
    <?php if ($run['status'] === 'awaiting_confirmation'): ?>
      <form method="post" action="/runs/resume">
        <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
        <input type="hidden" name="run_id" value="<?= e($runId) ?>">
        <button type="submit" class="btn btn-primary">Resume</button>
      </form>
    <?php endif; ?>
    <?php if (in_array($run['status'], ['running', 'awaiting_confirmation'], true)): ?>
      <form method="post" action="/runs/stop" data-confirm="Hentikan run ini? Tool berjalan saat ini akan dibatalkan.">
        <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
        <input type="hidden" name="run_id" value="<?= e($runId) ?>">
        <button type="submit" class="btn btn-danger">Stop Run</button>
      </form>
    <?php elseif ($run['status'] !== 'pending'): ?>
      <form method="post" action="/runs/delete" data-confirm="Hapus run ini? Semua event, findings, dan report milik run ikut terhapus. Tidak bisa dibatalkan.">
        <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
        <input type="hidden" name="run_id" value="<?= e($runId) ?>">
        <button type="submit" class="btn btn-danger">Delete Run</button>
      </form>
    <?php endif; ?>
    <?php if ($run['status'] === 'completed'): ?>
      <a class="btn" href="/report/download?id=<?= e($runId) ?>" title="Download report.pdf">⬇ Report PDF</a>
    <?php endif; ?>
  </div>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Operations Overview</h2>
      <p class="panel-subtitle"><?= e($run['template_name']) ?> — <?= e($run['target_value']) ?></p>
    </div>
    <span class="badge badge-cyan"><span class="dot"></span><?= strtoupper((string) $run['status']) ?></span>
  </div>

  <div class="stepper run-stepper" style="grid-template-columns: repeat(<?= count($phases) ?: 1 ?>, 1fr)">
    <?php foreach ($phases as $i => $ph): ?>
      <div class="step<?= $i === 0 ? ' running' : '' ?>" data-phase="<?= e($ph) ?>">
        <span class="step-circle"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <span class="step-label"><?= e($labelFor($ph)) ?></span>
        <span class="step-time" data-step-time>--</span>
        <span class="step-status" data-step-status>PENDING</span>
      </div>
    <?php endforeach; ?>
    <?php if (!$phases): ?>
      <p class="form-hint">Template run tidak punya phases.</p>
    <?php endif; ?>
  </div>

  <div class="ops-meta">
    <div class="ops-meta-item" style="flex:1;min-width:160px">
      <span class="ops-meta-label">OVERALL PROGRESS</span>
      <div class="progressbar mt-3"><div class="bar" style="width:0%" data-progress-bar></div></div>
      <span class="ops-meta-value" data-progress-pct>0%</span>
    </div>
    <div class="ops-meta-item">
      <span class="ops-meta-label">CURRENT MODULE</span>
      <span class="ops-meta-value mono" data-current-module>--</span>
    </div>
    <div class="ops-meta-item">
      <span class="ops-meta-label">ELAPSED TIME</span>
      <?php
      // elapsed awal dari DB — run selesai dipakai durasi final, tidak jalan lagi
      $elStart = $run['started_at'] ?: $run['created_at'];
      $elEnd = $run['finished_at'] ?: null;
      $elSecs = $elStart ? max(0, strtotime($elEnd ?: 'now') - strtotime($elStart)) : 0;
      ?>
      <span class="ops-meta-value" data-elapsed="0" data-elapsed-final="<?= $elEnd ? (int) $elSecs : '' ?>"><?= e(intdiv($elSecs, 3600) > 0 ? intdiv($elSecs, 3600) . 'h ' : '' . '') ?><?= intdiv($elSecs % 3600, 60) ?>m <?= str_pad((string) ($elSecs % 60), 2, '0', STR_PAD_LEFT) ?>s</span>
    </div>
    <div class="ops-meta-item">
      <span class="ops-meta-label">COMPLETED TASKS</span>
      <span class="ops-meta-value mono" data-tasks-done>0 / 0</span>
    </div>
  </div>
</section>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Run Info</h2>
        <p class="panel-subtitle">Detail engagement</p>
      </div>
    </div>
    <div class="fd-fields">
      <div class="fd-field"><span class="fd-label">TARGET</span><span class="fd-value mono"><?= e($run['target_name']) ?> (<?= e($run['target_value']) ?>)</span></div>
      <div class="fd-field"><span class="fd-label">ENVIRONMENT</span><span class="fd-value mono"><?= e(strtoupper((string) $run['environment'])) ?></span></div>
      <div class="fd-field"><span class="fd-label">TEMPLATE</span><span class="fd-value mono"><?= e($run['template_slug']) ?></span></div>
      <div class="fd-field"><span class="fd-label">CREATED BY</span><span class="fd-value mono"><?= e($run['created_by_username']) ?></span></div>
      <div class="fd-field"><span class="fd-label">CREATED AT</span><span class="fd-value mono"><?= e((string) $run['created_at']) ?></span></div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Execution Logs</h2>
        <p class="panel-subtitle">Event asli orchestrator — polling 2s</p>
      </div>
      <div style="display:flex; gap: var(--sp-2)">
        <button type="button" class="btn" data-term-toggle="runlog">Live Logs: ON</button>
        <button type="button" class="btn" data-term-clear="runlog">Clear</button>
      </div>
    </div>
    <div class="terminal" data-terminal="runlog" data-run-id="<?= e($runId) ?>">
      <div class="term-line"><span class="term-time">--:--:--</span><span class="term-lv lv-info">INFO</span><span class="term-msg">menyambung ke stream run...</span></div>
    </div>
  </section>
</div>

<section class="panel mt-4">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Module Pipeline</h2>
      <p class="panel-subtitle">Tool enabled dari registry — status &amp; durasi live mengikuti event</p>
    </div>
    <span class="badge"><span class="dot"></span><?= count($tools) ?> MODULES</span>
  </div>
  <div class="module-grid">
    <?php foreach ($tools as $m): ?>
      <div class="mod-card" data-module="<?= e($m['id']) ?>">
        <div class="mod-head">
          <span class="mod-name"><?= e($m['name']) ?></span>
          <span class="module-status pending" data-mod-status>PENDING</span>
        </div>
        <div class="mod-bar"><div class="bar" data-mod-bar></div></div>
        <div class="mod-meta">
          <span class="mono mod-risk risk-<?= e($m['risk']) ?>"><?= strtoupper($m['risk']) ?></span>
          <span class="mono text-faint" data-mod-extra>--</span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$tools): ?>
      <div class="empty-state" style="grid-column:1/-1">
        <div class="es-title">Tidak ada tool enabled</div>
        <div class="es-desc">Aktifkan tool di Admin → Tool Registry.</div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Modal konfirmasi tool destructive (guardrail 9.4 — Phase 4) -->
<div class="modal-backdrop" id="modal-confirm-tool">
  <div class="modal" style="max-width:520px">
    <div class="modal-head">
      <span class="modal-title">Konfirmasi Tool Destructive</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p class="fd-value" id="confirm-tool-text">
        AI memilih tool berisiko <span class="sev sev-critical">DESTRUCTIVE</span>.
        Run di-pause sampai kamu memutuskan. Tool tidak dieksekusi tanpa persetujuan eksplisit.
      </p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close id="btn-tool-reject">Tolak</button>
      <button type="button" class="btn btn-primary" data-modal-close id="btn-tool-confirm">Izinkan Eksekusi</button>
    </div>
  </div>
</div>

<script>
(function () {
  // konfirmasi stop (aksi destruktif — Bagian 4.11)
  document.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) {
      if (!window.confirm(f.dataset.confirm)) e.preventDefault();
    });
  });

  // Konfirmasi tool destructive (guardrail 9.4): modal auto-open + kirim ke PHP proxy
  var RUN_ID = "<?= e($runId) ?>";
  function sendConfirm(ok) {
    var fd = new FormData();
    fd.append("run_id", RUN_ID);
    fd.append("confirmed", ok ? "1" : "0");
    fd.append("csrf", "<?= e(Session::csrf()) ?>");
    fetch("/api/confirm-tool.php", { method: "POST", body: fd })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (!d || !d.ok) console.warn("confirm gagal", d); });
  }
  document.addEventListener("DOMContentLoaded", function () {
    var btnC = document.getElementById("btn-tool-confirm");
    var btnR = document.getElementById("btn-tool-reject");
    if (btnC) btnC.addEventListener("click", function () { sendConfirm(true); });
    if (btnR) btnR.addEventListener("click", function () { sendConfirm(false); });
  });
  window.addEventListener("oges:confirmation_required", function () {
    var m = document.getElementById("modal-confirm-tool");
    if (m) m.classList.add("open");
  });
  window.addEventListener("oges:run_completed", function () {
    var m = document.getElementById("modal-confirm-tool");
    if (m) m.classList.remove("open");
  });
})();
</script>
