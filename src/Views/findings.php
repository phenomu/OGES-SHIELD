<?php
/**
 * findings.php — Phase 5: data asli. Overall summary dari reports (AI),
 * tabel semua findings, filter severity (server + client), detail panel.
 */
View::$pageTitle = 'Findings — OGES-SHIELD';
View::$activeNav = 'findings';

/** @var array $findings */
/** @var array|null $report */
/** @var array $sevCounts */
/** @var string $sevFilter */
/** @var int $allCount */
$report = $report ?? null;
$sevCounts = $sevCounts ?? [];
$sevFilter = $sevFilter ?? 'all';
$allCount = $allCount ?? count($findings);
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Findings</h1>
    <p class="page-sub">Semua temuan lintas run + overall summary AI</p>
  </div>
  <div style="display:flex; gap: var(--sp-2); align-items:center">
    <?php if ($report): ?>
      <a class="btn" href="/findings?run=<?= e($report['run_id']) ?>">Run: <span class="mono"><?= e(substr((string) $report['run_id'], 0, 8)) ?></span></a>
    <?php endif; ?>
    <a class="btn btn-primary" href="/reports">Reports →</a>
  </div>
</div>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Overall Summary</h2>
        <p class="panel-subtitle">Ringkasan eksekutif — 1 panggilan AI di akhir workflow</p>
      </div>
      <span class="badge badge-cyan"><span class="dot"></span><?= e(AiEngine::model()) ?></span>
    </div>
    <?php if ($report && trim((string) ($report['overall_summary'] ?? '')) !== ''): ?>
      <?php foreach (preg_split('/\n\n/', (string) $report['overall_summary']) as $para): if (trim($para) === '') continue; ?>
        <p class="fd-value" style="margin: 0 0 var(--sp-3)"><?= e(trim($para)) ?></p>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="es-title">Belum ada overall summary</div>
        <div class="es-desc">Summary digenerate otomatis saat run selesai (phase Report Generation). Selesaikan 1 workflow run dulu.</div>
      </div>
    <?php endif; ?>
    <div class="ops-meta" style="margin-top: auto; padding-top: var(--sp-3)">
      <?php foreach ($sevCounts as $sev => $n): ?>
        <a href="/findings?sev=<?= e($sev) ?>" style="text-decoration:none">
          <div class="ops-meta-item">
            <span class="ops-meta-label"><?= strtoupper($sev) ?></span>
            <span class="ops-meta-value" style="color: var(--sev-<?= $sev ?>)"><?= (int) $n ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Severity Filter</h2>
        <p class="panel-subtitle">Klik untuk filter tabel</p>
      </div>
    </div>
    <div class="env-status">
      <?php foreach ($sevCounts as $sev => $n): ?>
        <a href="/findings?sev=<?= e($sev) ?>" class="env-item<?= $sevFilter === $sev ? ' active' : '' ?>"
           style="background:none;border:1px solid var(--border-subtle);cursor:pointer;color:var(--text-secondary);font-size:var(--fs-xs);text-decoration:none">
          <span class="sev sev-<?= $sev ?>"><?= strtoupper($sev) ?></span>
          <span class="mono" style="color:var(--text-primary)"><?= (int) $n ?> findings</span>
        </a>
      <?php endforeach; ?>
      <a href="/findings" class="env-item<?= $sevFilter === 'all' ? ' active' : '' ?>"
         style="background:none;border:1px solid var(--border-subtle);cursor:pointer;font-size:var(--fs-xs);text-decoration:none">
        <span class="sev sev-info">ALL</span>
        <span class="mono" style="color:var(--text-primary)"><?= (int) $allCount ?> findings</span>
      </a>
    </div>
  </section>
</div>

<section class="panel mt-4">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">All Findings</h2>
      <p class="panel-subtitle" id="f-count"><?= count($findings) ?> temuan ditampilkan</p>
    </div>
    <span class="mono text-muted" id="f-filter-label">filter: <?= e($sevFilter) ?></span>
  </div>
  <?php if (count($findings) === 0): ?>
    <div class="empty-state">
      <div class="es-title">Belum ada findings</div>
      <div class="es-desc">Findings muncul otomatis saat tool vulnerability (nuclei, SQLi probe) menemukan hasil positif.<?= $sevFilter !== 'all' ? ' Filter aktif: ' . e($sevFilter) . '.' : '' ?></div>
      <a class="btn btn-primary mt-3" href="/workflows">Mulai workflow baru</a>
    </div>
  <?php else: ?>
  <div class="grid-mid">
    <div class="table-wrap" style="max-height:480px; overflow-y:auto">
      <table class="tbl" id="findings-all">
        <thead><tr><th>ID</th><th>Severity</th><th>Title</th><th>Asset</th><th>CVSS</th><th>CWE</th><th>Source Tool</th></tr></thead>
        <tbody>
          <?php foreach ($findings as $f): ?>
            <tr data-sev="<?= e($f['severity']) ?>" data-fid="<?= (int) $f['id'] ?>" class="row-click" data-fidx="<?= (int) $f['id'] ?>">
              <td class="mono">#<?= (int) $f['id'] ?></td>
              <td><span class="sev sev-<?= e($f['severity']) ?>"><?= strtoupper(e($f['severity'])) ?></span></td>
              <td class="cell-strong"><?= e($f['title']) ?></td>
              <td class="mono"><?= e($f['asset'] ?? '--') ?></td>
              <td class="mono"><?= $f['cvss_score'] !== null ? number_format((float) $f['cvss_score'], 1) : '--' ?></td>
              <td class="mono"><?= e($f['cwe'] ?? '--') ?></td>
              <td class="mono"><?= e($f['source_tool']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="fd-detail" id="finding-detail">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">Detail Finding</h2>
          <p class="panel-subtitle">Klik baris tabel</p>
        </div>
      </div>
      <p class="fd-value text-faint" id="fd-placeholder">Pilih salah satu baris di tabel untuk melihat detail.</p>
      <div id="fd-body" style="display:none">
        <div style="display:flex; gap: var(--sp-2); align-items:center; margin-bottom: var(--sp-3)">
          <span id="fd-sev" class="sev"></span>
          <span id="fd-title" class="cell-strong"></span>
        </div>
        <div class="fd-fields">
          <div class="fd-field"><span class="fd-label">ASSET</span><span class="fd-value mono" id="fd-asset"></span></div>
          <div class="fd-field"><span class="fd-label">CVSS</span><span class="fd-value mono" id="fd-cvss"></span></div>
          <div class="fd-field"><span class="fd-label">CWE / CVE</span><span class="fd-value mono" id="fd-cwe"></span></div>
          <div class="fd-field"><span class="fd-label">SOURCE TOOL</span><span class="fd-value mono" id="fd-tool"></span></div>
          <div class="fd-field"><span class="fd-label">SUMMARY</span><span class="fd-value" id="fd-summary"></span></div>
          <div class="fd-field"><span class="fd-label">IMPACT</span><span class="fd-value" id="fd-impact"></span></div>
          <div class="fd-field"><span class="fd-label">REMEDIATION</span><span class="fd-value" id="fd-remed"></span></div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</section>

<script>
(function () {
  var data = <?= json_encode(array_map(fn($f) => [
      'id' => (int) $f['id'], 'severity' => $f['severity'], 'title' => $f['title'],
      'cvss' => $f['cvss_score'], 'cwe' => $f['cwe'], 'cve' => $f['cve'],
      'asset' => $f['asset'], 'tool' => $f['source_tool'],
      'summary' => $f['summary'], 'impact' => $f['impact'], 'remediation' => $f['remediation'],
  ], $findings), JSON_UNESCAPED_UNICODE) ?>;
  var byId = {};
  data.forEach(function (f) { byId[f.id] = f; });
  var body = document.getElementById("fd-body");
  var ph = document.getElementById("fd-placeholder");
  document.querySelectorAll("#findings-all tbody tr").forEach(function (row) {
    row.addEventListener("click", function () {
      var f = byId[parseInt(row.dataset.fid, 10)];
      if (!f) return;
      ph.style.display = "none";
      body.style.display = "";
      var sev = document.getElementById("fd-sev");
      sev.className = "sev sev-" + f.severity;
      sev.textContent = f.severity.toUpperCase();
      document.getElementById("fd-title").textContent = f.title;
      document.getElementById("fd-asset").textContent = f.asset || "—";
      document.getElementById("fd-cvss").textContent = f.cvss != null ? Number(f.cvss).toFixed(1) : "—";
      document.getElementById("fd-cwe").textContent = (f.cwe || "—") + (f.cve ? " / " + f.cve : "");
      document.getElementById("fd-tool").textContent = f.tool;
      document.getElementById("fd-summary").textContent = f.summary || "—";
      document.getElementById("fd-impact").textContent = f.impact || "—";
      document.getElementById("fd-remed").textContent = f.remediation || "—";
      document.querySelectorAll("#findings-all tbody tr").forEach(function (r) { r.classList.remove("row-active"); });
      row.classList.add("row-active");
    });
  });
})();
</script>
