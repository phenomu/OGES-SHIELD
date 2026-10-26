<?php
/**
 * workflows.php — 1D. Template list + Set Workflow modal + live run view (dummy JS).
 */
View::$pageTitle = 'Automation Workflows — OGES-SHIELD';
View::$activeNav = 'workflows';

$templates = MockData::workflowTemplates();
$runs = MockData::recentRuns();
$targets = array_filter(MockData::reconInventory(), fn($r) => $r['scope'] === 'in');

$runStatusBadge = fn(string $s) => match ($s) {
    'running'   => '<span class="sev sev-low">RUNNING</span>',
    'completed' => '<span class="sev sev-info">COMPLETED</span>', // hijau sukses dipakai via badge-success
    'failed'    => '<span class="sev sev-critical">FAILED</span>',
    default     => '<span class="sev sev-medium">PENDING</span>',
};
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Automation Workflows</h1>
    <p class="page-sub">Set workflow → AI orchestrator menjalankan tools lintas phase secara otonom</p>
  </div>
  <button type="button" class="btn btn-primary" data-modal-open="modal-set-workflow">+ Set Workflow</button>
</div>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Workflow Templates</h2>
        <p class="panel-subtitle">Blueprint alur phase + pool tool per phase</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Template</th><th>Phases</th><th>Runs</th><th>Last Run</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($templates as $t): ?>
            <tr>
              <td>
                <span class="cell-strong"><?= htmlspecialchars($t['name']) ?></span>
                <div class="text-muted" style="font-size: var(--fs-xxs)"><?= htmlspecialchars($t['desc']) ?></div>
              </td>
              <td class="mono"><?= $t['phases'] ?></td>
              <td class="mono"><?= $t['runs'] ?></td>
              <td class="mono"><?= $t['last'] ?></td>
              <td><button type="button" class="btn btn-sm" data-modal-open="modal-set-workflow">Use</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Recent Runs</h2>
        <p class="panel-subtitle">Eksekusi workflow terakhir</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Run ID</th><th>Target</th><th>Template</th><th>Status</th><th>Progress</th></tr></thead>
        <tbody>
          <?php foreach ($runs as $r): ?>
            <tr data-run-row="<?= $r['id'] ?>">
              <td class="mono cell-strong"><a href="#" data-run-open="<?= $r['id'] ?>"><?= $r['id'] ?></a></td>
              <td class="mono"><?= htmlspecialchars($r['target']) ?></td>
              <td><?= htmlspecialchars($r['template']) ?></td>
              <td><?= $runStatusBadge($r['status']) ?></td>
              <td style="min-width:110px">
                <div class="progressbar"><div class="bar" style="width: <?= $r['progress'] ?>%"></div></div>
                <span class="mono" style="font-size:9px; color:var(--text-muted)"><?= $r['progress'] ?>%</span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<!-- Live run view (dummy, muncul saat klik run) -->
<section class="panel mt-4" id="live-run-panel" style="display:none">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Live Run — <span class="mono" id="lr-id">--</span></h2>
      <p class="panel-subtitle">Progress real-time dari orchestrator (dummy Phase 1)</p>
    </div>
    <span class="badge badge-cyan" id="lr-status"><span class="dot"></span>RUNNING</span>
  </div>
  <div class="ops-meta">
    <div class="ops-meta-item" style="flex:1;min-width:180px">
      <span class="ops-meta-label">PROGRESS</span>
      <div class="progressbar mt-3"><div class="bar" id="lr-bar" style="width:46%"></div></div>
      <span class="ops-meta-value" id="lr-pct">46%</span>
    </div>
    <div class="ops-meta-item">
      <span class="ops-meta-label">CURRENT MODULE</span>
      <span class="ops-meta-value" id="lr-module">nuclei_runner.py</span>
    </div>
    <div class="ops-meta-item">
      <span class="ops-meta-label">ELAPSED</span>
      <span class="ops-meta-value" id="lr-elapsed">0m 00s</span>
    </div>
    <div class="ops-meta-item">
      <span class="ops-meta-label">TOOL CALLS</span>
      <span class="ops-meta-value" id="lr-calls">6 / 10</span>
    </div>
  </div>
  <div class="terminal mt-3" data-terminal="runview"></div>
  <div class="term-actions">
    <button type="button" class="btn btn-sm btn-primary" data-term-toggle="runview">Live Logs: ON</button>
    <button type="button" class="btn btn-sm btn-danger" data-modal-open="modal-stop">Stop Run</button>
  </div>
</section>

<!-- Modal Set Workflow -->
<div class="modal-backdrop" id="modal-set-workflow">
  <div class="modal" style="max-width:560px">
    <div class="modal-head">
      <span class="modal-title">Set Workflow</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form class="modal-body" id="form-set-workflow">
      <div class="form-field">
        <label class="form-label" for="sw-target">TARGET (IN-SCOPE SAJA)</label>
        <select class="select mono" id="sw-target" required>
          <?php foreach ($targets as $t): ?>
            <option value="<?= htmlspecialchars($t['asset']) ?>"><?= htmlspecialchars($t['asset']) ?> — <?= htmlspecialchars($t['service']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="form-hint">Hanya asset in-scope yang bisa dipilih — orchestrator menolak target lain (server-side).</span>
      </div>
      <div class="form-field">
        <label class="form-label" for="sw-template">WORKFLOW TEMPLATE</label>
        <select class="select" id="sw-template" required>
          <?php foreach ($templates as $t): ?>
            <option value="<?= $t['slug'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['phases'] ?> phases)</option>
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
      <button type="button" class="btn btn-primary" id="btn-start-run">Start Run</button>
    </div>
  </div>
</div>

<!-- Modal Stop (dipakai bersama dashboard) -->
<div class="modal-backdrop" id="modal-stop">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Stop Run?</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p class="fd-value">Run yang sedang berjalan akan dihentikan paksa. Tool aktif di-terminate, phase berjalan ditandai <span class="sev sev-high">STOPPED</span>.</p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-danger" data-modal-close>Stop Run</button>
    </div>
  </div>
</div>

<script>
(function () {
  var panel = document.getElementById("live-run-panel");
  var pct = 46, calls = 6, secs = 0, timer = null;
  var modules = ["subfinder_runner.py", "httpx_runner.py", "nuclei_runner.py", "sqli_probe.py", "report_generator.py"];

  function openRun(id) {
    panel.style.display = "";
    document.getElementById("lr-id").textContent = id;
    if (timer) clearInterval(timer);
    pct = 46; calls = 6; secs = 0;
    timer = setInterval(function () {
      secs++;
      pct = Math.min(100, pct + Math.random() * 2.5);
      document.getElementById("lr-bar").style.width = pct.toFixed(0) + "%";
      document.getElementById("lr-pct").textContent = pct.toFixed(0) + "%";
      document.getElementById("lr-elapsed").textContent =
        Math.floor(secs / 60) + "m " + String(secs % 60).padStart(2, "0") + "s";
      if (secs % 5 === 0) {
        document.getElementById("lr-module").textContent = modules[Math.floor(Math.random() * modules.length)];
        if (calls < 10) document.getElementById("lr-calls").textContent = (++calls) + " / 10";
      }
    }, 1000);
  }

  document.querySelectorAll("[data-run-open]").forEach(function (a) {
    a.addEventListener("click", function (e) {
      e.preventDefault();
      openRun(a.dataset.runOpen);
      panel.scrollIntoView({ behavior: "smooth" });
    });
  });

  document.getElementById("btn-start-run").addEventListener("click", function () {
    var modal = document.getElementById("modal-set-workflow");
    modal.classList.remove("open");
    var target = document.getElementById("sw-target").value;
    var tpl = document.getElementById("sw-template").value;
    openRun("RUN-DEMO-" + Date.now().toString(36).toUpperCase());
    var row = document.createElement("tr");
    row.innerHTML = '<td class="mono cell-strong">' + target + "</td><td>" + tpl +
      '</td><td><span class="sev sev-low">RUNNING</span></td><td style="min-width:110px">' +
      '<div class="progressbar"><div class="bar" style="width:0%"></div></div></td>';
    document.querySelector(".tbl tbody").prepend(row);
    panel.scrollIntoView({ behavior: "smooth" });
  });
})();
</script>
