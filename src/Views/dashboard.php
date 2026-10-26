<?php
/**
 * dashboard.php — Overview (Bagian 4.3 – 4.9). Semua data dummy Phase 1.
 */
View::$pageTitle = 'Overview — OGES-SHIELD';
View::$activeNav = 'overview';

$stats      = MockData::statCards();
$sparks     = MockData::sparklines();
$stepper    = MockData::stepper();
$ops        = MockData::operations();
$secSum     = MockData::securitySummary();
$aiLog      = MockData::aiLog();
$inventory  = MockData::reconInventory();
$timeline   = MockData::assetTimeline();
$findings   = MockData::findings();
$pipeline   = MockData::modulePipeline();
$engineP    = MockData::enginePanel();
$envStatus  = MockData::environmentStatus();
$firstFinding = $findings[0];

$icons = [
  'db'    => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
  'host'  => '<rect x="2" y="3" width="20" height="6" rx="1"/><rect x="2" y="13" width="20" height="6" rx="1"/><path d="M6 6h.01M6 16h.01"/>',
  'warn'  => '<path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
  'crit'  => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
  'run'   => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
  'ai'    => '<path d="M12 2a4 4 0 0 1 4 4c1.7 0 3 1.3 3 3s-1.3 3-3 3h-1v2h-2v-2H9v2H7v-2c-1.7 0-3-1.3-3-3s1.3-3 3-3a4 4 0 0 1 4-4z"/><circle cx="12" cy="8" r="1"/>',
];

function step_status_icon(string $status): string
{
    return match ($status) {
        'completed' => '<span class="step-status">✓ COMPLETED</span>',
        'running'   => '<span class="step-status">◌ RUNNING</span>',
        default     => '<span class="step-status">PENDING</span>',
    };
}
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Overview</h1>
    <p class="page-sub">Executive snapshot — engagement <?= htmlspecialchars(MockData::engagement()['name']) ?></p>
  </div>
  <div class="mono text-muted"><?= $ops['run_id'] ?></div>
</div>

<!-- 4.3 Stat cards -->
<div class="grid-stats">
  <?php foreach ($stats as $card): ?>
    <div class="stat-card<?= isset($card['tone']) ? ' tone-' . $card['tone'] : '' ?>">
      <div class="stat-head">
        <span class="stat-icon">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$card['icon']] ?></svg>
        </span>
        <span class="stat-label"><?= htmlspecialchars($card['label']) ?></span>
      </div>
      <div class="stat-value-row">
        <span class="stat-value"><?= $card['value'] ?></span>
        <span class="stat-total">/ <?= $card['total'] ?></span>
        <span class="spacer"></span>
        <span class="stat-trend<?= $card['trend_up'] ? '' : ' down' ?>"><?= $card['trend_up'] ? '↑' : '↓' ?> <?= $card['trend'] ?></span>
      </div>
      <?php if (isset($card['progress'])): ?>
        <div class="stat-progress" title="<?= $card['progress'] ?>%"><div class="bar" style="width: <?= $card['progress'] ?>%"></div></div>
      <?php else: ?>
        <div class="stat-spark"><canvas data-spark="<?= htmlspecialchars(json_encode($sparks[$card['key']])) ?>"<?= isset($card['tone']) ? ' data-spark-tone="' . $card['tone'] . '"' : '' ?>></canvas></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- 4.4 Operations Overview + 4.5 Security Summary -->
<div class="grid-mid">
  <section class="panel" id="operations">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Operations Overview</h2>
        <p class="panel-subtitle">Penetration Testing Workflow — <?= htmlspecialchars($ops['run_id']) ?></p>
      </div>
      <span class="badge badge-cyan"><span class="dot"></span>RUNNING</span>
    </div>

    <div class="stepper">
      <?php foreach ($stepper as $st): ?>
        <div class="step <?= $st['status'] ?>">
          <span class="step-circle"><?= $st['num'] ?></span>
          <span class="step-label"><?= htmlspecialchars($st['label']) ?></span>
          <span class="step-time"><?= $st['time'] ?? '--' ?></span>
          <?= step_status_icon($st['status']) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="ops-meta">
      <div class="ops-meta-item" style="flex:1;min-width:160px">
        <span class="ops-meta-label">OVERALL PROGRESS</span>
        <div class="progressbar mt-3"><div class="bar" style="width: <?= $ops['progress_pct'] ?>%"></div></div>
        <span class="ops-meta-value"><?= $ops['progress_pct'] ?>%</span>
      </div>
      <div class="ops-meta-item">
        <span class="ops-meta-label">CURRENT MODULE</span>
        <span class="ops-meta-value"><?= htmlspecialchars($ops['current_module']) ?></span>
      </div>
      <div class="ops-meta-item">
        <span class="ops-meta-label">ELAPSED TIME</span>
        <span class="ops-meta-value" data-elapsed="<?= 26 * 60 + 17 ?>">26m 17s</span>
      </div>
      <div class="ops-meta-item">
        <span class="ops-meta-label">COMPLETED TASKS</span>
        <span class="ops-meta-value"><?= $ops['tasks_done'] ?> / <?= $ops['tasks_total'] ?></span>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Security Summary</h2>
        <p class="panel-subtitle">Open findings breakdown</p>
      </div>
    </div>
    <div class="donut-wrap">
      <div class="donut-chart">
        <canvas data-donut="<?= htmlspecialchars(json_encode($secSum['severity'])) ?>"></canvas>
        <div class="donut-center">
          <div>
            <div class="num"><?= $secSum['open_findings'] ?></div>
            <div class="lbl">OPEN</div>
          </div>
        </div>
      </div>
      <div class="donut-legend">
        <?php
        $total = max(1, $secSum['open_findings']);
        foreach ($secSum['severity'] as $sev):
          $pct = round($sev['value'] / $total * 100);
        ?>
          <div class="legend-row">
            <span class="legend-swatch" style="background: var(--sev-<?= $sev['color'] ?>); box-shadow: 0 0 6px var(--sev-<?= $sev['color'] ?>)"></span>
            <span class="legend-label"><?= $sev['label'] ?></span>
            <span class="legend-val"><?= $sev['value'] ?></span>
            <span class="legend-pct"><?= $pct ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="cvss-rows">
      <?php
      $cvssMax = max(array_column($secSum['cvss'], 'value'));
      $cvssColor = fn($i) => ['#F43F5E', '#EC4899', '#F59E0B', '#22D3EE'][$i] ?? '#64748B';
      foreach ($secSum['cvss'] as $i => $row): ?>
        <div class="cvss-row">
          <span class="cvss-range"><?= htmlspecialchars($row['range']) ?></span>
          <div class="cvss-bar"><div class="bar" style="width: <?= round($row['value'] / max(1, $cvssMax) * 100) ?>%; background: <?= $cvssColor($i) ?>"></div></div>
          <span class="cvss-count"><?= $row['value'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<!-- 4.6 AI Orchestration Log (pengganti Network Topology — Opsi A) + 4.7 Reconnaissance -->
<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">AI Orchestration Log</h2>
        <p class="panel-subtitle">Live feed keputusan AI — tool selesai, ringkasan, &amp; pilihan berikutnya</p>
      </div>
      <span class="badge badge-cyan"><span class="dot"></span>glm-5.3</span>
    </div>
    <div class="ailog">
      <?php foreach ($aiLog as $entry): ?>
        <div class="ailog-entry">
          <span class="ailog-time"><?= $entry['time'] ?></span>
          <div class="ailog-body">
            <span class="ailog-tag <?= $entry['type'] ?>"><?= $entry['type'] === 'ai_decision' ? 'AI DECISION' : 'TOOL DONE' ?></span>
            <span class="ailog-tool"><?= htmlspecialchars($entry['tool']) ?></span>
            <p class="ailog-text"><?= htmlspecialchars($entry['type'] === 'ai_decision' ? $entry['reasoning'] : $entry['summary']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Reconnaissance</h2>
        <p class="panel-subtitle">Target inventory &amp; discovery timeline</p>
      </div>
    </div>
    <div data-tabs>
      <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="inventory">Target Inventory</button>
        <button type="button" class="tab-btn" data-tab="dns">DNS Records</button>
        <button type="button" class="tab-btn" data-tab="subdomains">Subdomains</button>
        <button type="button" class="tab-btn" data-tab="ips">IP Addresses</button>
        <button type="button" class="tab-btn" data-tab="services">Services</button>
      </div>
      <div class="tab-pane active" data-pane="inventory">
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr><th>Asset</th><th>Type</th><th>Service</th><th>Status</th><th>Discovered</th><th>Scope</th></tr>
            </thead>
            <tbody>
              <?php foreach ($inventory as $row): ?>
                <tr>
                  <td class="mono cell-strong"><?= htmlspecialchars($row['asset']) ?></td>
                  <td class="mono"><?= strtoupper($row['type']) ?></td>
                  <td class="mono"><?= htmlspecialchars($row['service']) ?></td>
                  <td><span class="st-<?= $row['status'] ?> mono">● <?= strtoupper($row['status']) ?></span></td>
                  <td class="mono"><?= $row['discovered'] ?></td>
                  <td><span class="scope-badge <?= $row['scope'] === 'in' ? 'scope-in' : 'scope-out' ?>"><?= $row['scope'] === 'in' ? 'IN SCOPE' : 'OUT' ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php foreach (['dns' => 'DNS Records', 'subdomains' => 'Subdomains', 'ips' => 'IP Addresses', 'services' => 'Services'] as $paneKey => $paneLabel): ?>
        <div class="tab-pane" data-pane="<?= $paneKey ?>">
          <div class="empty-state">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.2" class="es-icon"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
            <div class="es-title"><?= $paneLabel ?></div>
            <div class="es-desc">Coming from real scan — panel ini akan terisi otomatis dari hasil tool recon (Phase 3+).</div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="chart-box mt-3" style="height:120px">
      <canvas data-timeline="<?= htmlspecialchars(json_encode($timeline)) ?>"></canvas>
    </div>
    <div class="ops-meta">
      <div class="ops-meta-item"><span class="ops-meta-label">TOTAL ASSETS</span><span class="ops-meta-value"><?= count($inventory) ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">IN SCOPE</span><span class="ops-meta-value"><?= count(array_filter($inventory, fn($r) => $r['scope'] === 'in')) ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">ONLINE</span><span class="ops-meta-value"><?= count(array_filter($inventory, fn($r) => $r['status'] === 'online')) ?></span></div>
    </div>
  </section>
</div>

<!-- 4.8 Vulnerability Assessment + 4.9 Tool Automation Engine -->
<div class="grid-bottom">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Vulnerability Assessment</h2>
        <p class="panel-subtitle">Temuan aktif — klik baris untuk detail</p>
      </div>
    </div>
    <div data-tabs>
      <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="f-list">Findings</button>
        <button type="button" class="tab-btn" data-tab="f-asset">By Asset</button>
        <button type="button" class="tab-btn" data-tab="f-cvss">By CVSS</button>
        <button type="button" class="tab-btn" data-tab="f-cwe">By CWE</button>
      </div>
      <div class="tab-pane active" data-pane="f-list">
        <div class="table-wrap" style="max-height:320px; overflow-y:auto">
          <table class="tbl" id="findings-table">
            <thead><tr><th>Severity</th><th>Title</th><th>Asset</th><th>CVSS</th><th>CWE</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($findings as $f): ?>
                <tr class="finding-row<?= $f['id'] === $firstFinding['id'] ? ' row-selected' : '' ?>" data-finding="<?= $f['id'] ?>">
                  <td><span class="sev sev-<?= $f['severity'] ?>"><?= strtoupper($f['severity']) ?></span></td>
                  <td class="cell-strong"><?= htmlspecialchars($f['title']) ?></td>
                  <td class="mono"><?= htmlspecialchars($f['asset']) ?></td>
                  <td class="mono"><?= number_format($f['cvss'], 1) ?></td>
                  <td class="mono"><?= $f['cwe'] ?? '--' ?></td>
                  <td><span class="mono text-muted"><?= strtoupper(str_replace('_', ' ', $f['status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php foreach (['f-asset' => 'By Asset', 'f-cvss' => 'By CVSS', 'f-cwe' => 'By CWE'] as $k2 => $l2): ?>
        <div class="tab-pane" data-pane="<?= $k2 ?>">
          <div class="empty-state"><div class="es-title"><?= $l2 ?></div><div class="es-desc">Agregasi per <?= strtolower(str_replace('f-', '', $k2)) ?> — menyusul bersama data scan nyata.</div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Tool Automation Engine</h2>
        <p class="panel-subtitle">Runtime <?= strtoupper($engineP['runtime']) ?> · model <?= htmlspecialchars($engineP['model']) ?></p>
      </div>
    </div>
    <div class="ops-meta" style="margin-bottom: var(--sp-3); padding-top:0; border-top:none">
      <div class="ops-meta-item"><span class="ops-meta-label">RUNTIME</span><span class="ops-meta-value" style="color: var(--status-success)"><?= strtoupper($engineP['runtime']) ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">WORKERS</span><span class="ops-meta-value"><?= $engineP['workers'] ?> / <?= $engineP['workers_total'] ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">QUEUE</span><span class="ops-meta-value"><?= $engineP['queue'] ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">DEPS</span><span class="ops-meta-value" style="color: var(--status-success)"><?= strtoupper($engineP['deps']) ?></span></div>
    </div>
    <div class="pipeline">
      <?php foreach ($pipeline as $m): ?>
        <div class="module-card">
          <span class="module-name"><?= htmlspecialchars($m['name']) ?></span>
          <span class="module-duration"><?= $m['duration'] ?></span>
          <span class="module-status <?= $m['status'] ?>"><?= strtoupper($m['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="terminal mt-3" data-terminal="main"></div>
    <div class="term-actions">
      <button type="button" class="btn btn-sm btn-primary" data-term-toggle="main">Live Logs: ON</button>
      <button type="button" class="btn btn-sm" data-term-clear="main">Clear</button>
    </div>

    <div class="mt-4" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--sp-3)">
      <div class="panel" style="padding: var(--sp-3)">
        <h3 class="panel-title" style="font-size: var(--fs-sm)">Quick Actions</h3>
        <div class="quick-actions mt-3">
          <button type="button" class="btn btn-primary">Run Assessment</button>
          <button type="button" class="btn" onclick="location.href='/reports'">View Full Report</button>
          <button type="button" class="btn" onclick="location.href='/assets'">Manage Targets</button>
          <button type="button" class="btn btn-danger" data-modal-open="modal-stop">Stop All Tasks</button>
        </div>
      </div>
      <div class="panel" style="padding: var(--sp-3)">
        <h3 class="panel-title" style="font-size: var(--fs-sm)">Environment Status</h3>
        <div class="env-status mt-3">
          <?php foreach ($envStatus as $e): ?>
            <div class="env-item"><span><?= htmlspecialchars($e['label']) ?></span><span class="st <?= $e['status'] ?>"><?= strtoupper($e['status']) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Detail finding panel (4.8 kanan) — data via JS dari window.FINDINGS -->
<div class="grid-bottom" id="vuln-detail-row">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Finding Detail</h2>
        <p class="panel-subtitle">Field lengkap temuan terpilih</p>
      </div>
      <span class="mono text-muted" id="fd-id">#<?= $firstFinding['id'] ?></span>
    </div>
    <div class="finding-detail" id="finding-detail">
      <div class="fd-head">
        <span class="fd-title" id="fd-title"><?= htmlspecialchars($firstFinding['title']) ?></span>
        <span class="sev sev-<?= $firstFinding['severity'] ?>" id="fd-sev"><?= strtoupper($firstFinding['severity']) ?></span>
        <span class="fd-score sev-<?= $firstFinding['severity'] ?>" id="fd-cvss"><?= number_format($firstFinding['cvss'], 1) ?></span>
      </div>
      <div class="fd-fields">
        <div class="fd-field"><span class="fd-label">ASSET</span><span class="fd-value mono" id="fd-asset"><?= htmlspecialchars($firstFinding['asset']) ?></span></div>
        <div class="fd-field"><span class="fd-label">CWE / CVE</span><span class="fd-value mono" id="fd-cwe"><?= htmlspecialchars(($firstFinding['cwe'] ?? '--') . ' / ' . ($firstFinding['cve'] ?? '--')) ?></span></div>
        <div class="fd-field"><span class="fd-label">SUMMARY</span><span class="fd-value" id="fd-summary"><?= htmlspecialchars($firstFinding['summary']) ?></span></div>
        <div class="fd-field"><span class="fd-label">EVIDENCE</span><span class="fd-value mono" id="fd-evidence"><?= htmlspecialchars($firstFinding['evidence'] ?? '--') ?></span></div>
        <div class="fd-field"><span class="fd-label">IMPACT</span><span class="fd-value" id="fd-impact"><?= htmlspecialchars($firstFinding['impact']) ?></span></div>
        <div class="fd-field"><span class="fd-label">REMEDIATION</span><span class="fd-value" id="fd-remediation"><?= htmlspecialchars($firstFinding['remediation']) ?></span></div>
        <div class="fd-field"><span class="fd-label">VERIFICATION</span><span class="fd-value" id="fd-verification">UNVERIFIED · source <?= htmlspecialchars($firstFinding['tool']) ?></span></div>
      </div>
    </div>
  </section>
  <section class="panel" id="audit">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Audit Trail (Ringkas)</h2>
        <p class="panel-subtitle">5 event eksekusi terakhir — log lengkap di menu Audit Logs</p>
      </div>
    </div>
    <div class="ailog">
      <?php
      $auditTrail = [
        ['15:47:31', 'nuclei → www.acme-corp.com', 'tool_executed', 'ok'],
        ['15:42:10', 'ai_decision → nuclei', 'ai', 'ok'],
        ['15:41:55', 'httpx → 23 hosts', 'tool_executed', 'ok'],
        ['15:39:02', 'ai_decision → httpx', 'ai', 'ok'],
        ['15:38:41', 'subfinder → acme-corp.com', 'tool_executed', 'ok'],
      ];
      foreach ($auditTrail as $a): ?>
        <div class="ailog-entry">
          <span class="ailog-time"><?= $a[0] ?></span>
          <div class="ailog-body"><span class="mono" style="font-size:11px; color: var(--text-secondary)"><?= htmlspecialchars($a[1]) ?></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<script>
window.FINDINGS = <?= json_encode($findings) ?>;
(function () {
  var rows = document.querySelectorAll(".finding-row");
  rows.forEach(function (row) {
    row.addEventListener("click", function () {
      var f = window.FINDINGS.find(function (x) { return String(x.id) === row.dataset.finding; });
      if (!f) return;
      rows.forEach(function (r) { r.classList.toggle("row-selected", r === row); });
      var set = function (id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
      set("fd-id", "#" + f.id);
      set("fd-title", f.title);
      set("fd-sev", f.severity.toUpperCase());
      set("fd-cvss", Number(f.cvss).toFixed(1));
      set("fd-asset", f.asset);
      set("fd-cwe", (f.cwe || "--") + " / " + (f.cve || "--"));
      set("fd-summary", f.summary);
      set("fd-evidence", f.evidence || "--");
      set("fd-impact", f.impact);
      set("fd-remediation", f.remediation);
      set("fd-verification", "UNVERIFIED · source " + f.tool);
      ["fd-sev", "fd-cvss"].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.className = el.className.replace(/sev-\w+/, "sev-" + f.severity);
      });
    });
  });
})();
</script>

<!-- Modal Stop All Tasks (konfirmasi aksi destruktif) -->
<div class="modal-backdrop" id="modal-stop">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Stop All Tasks?</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p class="fd-value">Semua run aktif (<?= count(array_filter(MockData::recentRuns(), fn($r) => $r['status'] === 'running')) ?> run) akan dihentikan paksa. Tool yang sedang jalan di-terminate, phase berjalan ditandai <span class="sev sev-high">STOPPED</span>. Aksi ini tidak bisa dibatalkan.</p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-danger" data-modal-close id="btn-stop-all">Stop All</button>
    </div>
  </div>
</div>
