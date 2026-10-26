<?php
/**
 * findings.php — 1D. Overall Summary (dummy AI) + tabel semua findings + filter severity.
 */
View::$pageTitle = 'Findings — OGES-SHIELD';
View::$activeNav = 'findings';

$findings = MockData::findings();
$summary = MockData::overallSummary();
$sevCounts = [];
foreach (['critical', 'high', 'medium', 'low', 'info'] as $sev) {
    $sevCounts[$sev] = count(array_filter($findings, fn($f) => $f['severity'] === $sev));
}
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Findings</h1>
    <p class="page-sub">Semua temuan lintas run + overall summary AI</p>
  </div>
  <span class="mono text-muted">ACME_CORP_ASSESSMENT_2026</span>
</div>

<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Overall Summary</h2>
        <p class="panel-subtitle">Ringkasan eksekutif — 1 panggilan AI di akhir workflow</p>
      </div>
      <span class="badge badge-cyan"><span class="dot"></span>glm-5.3</span>
    </div>
    <?php foreach (preg_split('/\n\n/', $summary) as $para): if (trim($para) === '') continue; ?>
      <p class="fd-value" style="margin: 0 0 var(--sp-3)"><?= htmlspecialchars(trim($para)) ?></p>
    <?php endforeach; ?>
    <div class="ops-meta" style="margin-top: auto; padding-top: var(--sp-3)">
      <?php foreach ($sevCounts as $sev => $n): ?>
        <div class="ops-meta-item">
          <span class="ops-meta-label"><?= strtoupper($sev) ?></span>
          <span class="ops-meta-value" style="color: var(--sev-<?= $sev ?>)"><?= $n ?></span>
        </div>
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
        <button type="button" class="env-item" style="background:none;border:none;cursor:pointer;color:var(--text-secondary);font-size:var(--fs-xs)" data-sev-filter="<?= $sev ?>">
          <span class="sev sev-<?= $sev ?>"><?= strtoupper($sev) ?></span>
          <span class="mono" style="color:var(--text-primary)"><?= $n ?> findings</span>
        </button>
      <?php endforeach; ?>
      <button type="button" class="env-item" style="background:none;border:none;cursor:pointer;font-size:var(--fs-xs)" data-sev-filter="all">
        <span class="sev sev-info">ALL</span>
        <span class="mono" style="color:var(--text-primary)"><?= count($findings) ?> findings</span>
      </button>
    </div>
  </section>
</div>

<section class="panel mt-4">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">All Findings</h2>
      <p class="panel-subtitle" id="f-count"><?= count($findings) ?> temuan ditampilkan</p>
    </div>
    <span class="mono text-muted" id="f-filter-label">filter: none</span>
  </div>
  <div class="table-wrap" style="max-height:480px; overflow-y:auto">
    <table class="tbl" id="findings-all">
      <thead><tr><th>ID</th><th>Severity</th><th>Title</th><th>Asset</th><th>CVSS</th><th>CWE</th><th>Source Tool</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($findings as $f): ?>
          <tr data-sev="<?= $f['severity'] ?>">
            <td class="mono">#<?= $f['id'] ?></td>
            <td><span class="sev sev-<?= $f['severity'] ?>"><?= strtoupper($f['severity']) ?></span></td>
            <td class="cell-strong"><?= htmlspecialchars($f['title']) ?></td>
            <td class="mono"><?= htmlspecialchars($f['asset']) ?></td>
            <td class="mono"><?= number_format($f['cvss'], 1) ?></td>
            <td class="mono"><?= $f['cwe'] ?? '--' ?></td>
            <td class="mono"><?= htmlspecialchars($f['tool']) ?></td>
            <td><span class="mono text-muted"><?= strtoupper(str_replace('_', ' ', $f['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
(function () {
  var rows = document.querySelectorAll("#findings-all tbody tr");
  var label = document.getElementById("f-filter-label");
  var count = document.getElementById("f-count");
  function filter(sev) {
    var shown = 0;
    rows.forEach(function (r) {
      var ok = sev === "all" || r.dataset.sev === sev;
      r.style.display = ok ? "" : "none";
      if (ok) shown++;
    });
    label.textContent = "filter: " + (sev === "all" ? "none" : sev);
    count.textContent = shown + " temuan ditampilkan";
  }
  document.querySelectorAll("[data-sev-filter]").forEach(function (btn) {
    btn.addEventListener("click", function () { filter(btn.dataset.sevFilter); });
  });
})();
</script>
