<?php
/**
 * run.php — halaman live run (Phase 3): event real-time via /api/stream.php.
 * Stepper = phases dari run; terminal = event asli orchestrator.
 */
View::$pageTitle = 'Live Run — OGES-SHIELD';
View::$activeNav = 'workflows';

/** @var array $run */
/** @var string $runId */
/** @var array $phases */
$phases = $phases ?: [];
$statusBadge = fn(string $s) => match ($s) {
    'pending'   => '<span class="sev sev-medium">PENDING</span>',
    'running'   => '<span class="sev sev-info">RUNNING</span>',
    'awaiting_confirmation' => '<span class="sev sev-medium">AWAITING CONFIRMATION</span>',
    'completed' => '<span class="sev sev-info">COMPLETED</span>',
    'failed'    => '<span class="sev sev-critical">FAILED</span>',
    'stopped'   => '<span class="sev sev-medium">STOPPED</span>',
    default     => '<span class="sev sev-info">' . strtoupper($s) . '</span>',
};
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Live Run</h1>
    <p class="page-sub mono"><?= e($runId) ?></p>
  </div>
  <div style="display:flex; gap: var(--sp-2); align-items:center">
    <?= $statusBadge((string) $run['status']) ?>
    <form method="post" action="/runs/stop" data-confirm="Hentikan run ini? Tool berjalan saat ini akan dibatalkan.">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
      <input type="hidden" name="run_id" value="<?= e($runId) ?>">
      <button type="submit" class="btn btn-danger">Stop Run</button>
    </form>
  </div>
</div>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Run Info</h2>
        <p class="panel-subtitle"><?= e($run['template_name']) ?></p>
      </div>
    </div>
    <div class="fd-fields">
      <div class="fd-field"><span class="fd-label">TARGET</span><span class="fd-value mono"><?= e($run['target_name']) ?> (<?= e($run['target_value']) ?>)</span></div>
      <div class="fd-field"><span class="fd-label">ENVIRONMENT</span><span class="fd-value mono"><?= e(strtoupper((string) $run['environment'])) ?></span></div>
      <div class="fd-field"><span class="fd-label">TEMPLATE</span><span class="fd-value mono"><?= e($run['template_slug']) ?></span></div>
      <div class="fd-field"><span class="fd-label">CREATED BY</span><span class="fd-value mono"><?= e($run['created_by_username']) ?></span></div>
      <div class="fd-field"><span class="fd-label">CREATED AT</span><span class="fd-value mono"><?= e((string) $run['created_at']) ?></span></div>
      <div class="fd-field"><span class="fd-label">STATUS</span><span class="fd-value"><?= $statusBadge((string) $run['status']) ?></span></div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Phase Progress</h2>
        <p class="panel-subtitle"><?= count($phases) ?> phase workflow</p>
      </div>
    </div>
    <ol class="stepper" style="--steps:<?= count($phases) ?: 1 ?>">
      <?php foreach ($phases as $i => $ph): ?>
        <li class="step<?= $i === 0 ? ' active' : '' ?>" data-phase="<?= e($ph) ?>">
          <span class="step-dot"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <span class="step-label"><?= e(str_replace('_', ' ', $ph)) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <p class="form-hint mt-3">Status phase diperbarui real-time dari event orchestrator.</p>
  </section>
</div>

<section class="panel mt-4">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Execution Logs</h2>
      <p class="panel-subtitle">Event asli dari orchestrator — polling /api/stream.php</p>
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

<section class="panel mt-4">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Module Pipeline</h2>
      <p class="panel-subtitle">Kartu berubah status mengikuti event tool_started/completed</p>
    </div>
  </div>
  <div class="module-grid">
    <div class="module-row" data-module="subfinder"><span class="module-name">subfinder</span><span class="module-status pending">PENDING</span><span class="module-duration">--</span></div>
    <div class="module-row" data-module="httpx"><span class="module-name">httpx</span><span class="module-status pending">PENDING</span><span class="module-duration">--</span></div>
    <div class="module-row" data-module="nuclei"><span class="module-name">nuclei</span><span class="module-status pending">PENDING</span><span class="module-duration">--</span></div>
    <div class="module-row" data-module="custom_sqli_probe"><span class="module-name">custom_sqli_probe</span><span class="module-status pending">PENDING</span><span class="module-duration">--</span></div>
  </div>
</section>

<script>
(function () {
  // konfirmasi stop (aksi destruktif — Bagian 4.11)
  document.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) {
      if (!window.confirm(f.dataset.confirm)) e.preventDefault();
    });
  });
  // phase stepper: tandai active sesuai event phase_complete/run phase
  document.addEventListener("DOMContentLoaded", function () { /* app.js initRunStream jalan */ });
})();
</script>
