<?php
/**
 * vulnerabilities.php — Panel vulnerability assessment (findings + detail).
 */
View::$pageTitle = 'Vulnerability Assessment — OGES-SHIELD';
View::$activeNav = 'vulns';

// data asli: findings milik user (Phase 6 — ganti MockData)
$user = Auth::user();
$isSuper = ($user['role'] ?? '') === 'superadmin';
$ownFilter = $isSuper ? '1=1' : 'wr.created_by = ' . (int) $user['id'];
$rows = Database::run(
    "SELECT f.id, f.severity, f.title, f.cvss_score, f.cwe, f.cve, f.summary,
            f.impact, f.remediation, f.evidence_path, f.source_tool, f.verification_status,
            f.`references` AS refs_json, t.value AS asset
     FROM findings f JOIN workflow_runs wr ON wr.id = f.run_id
     LEFT JOIN targets t ON t.id = f.asset_id
     WHERE $ownFilter
     ORDER BY FIELD(f.severity,'critical','high','medium','low','info'), f.id DESC LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);
$findings = array_map(function ($f) {
    $refs = json_decode((string) ($f['refs_json'] ?? ''), true);
    return [
        'id' => (int) $f['id'], 'severity' => (string) $f['severity'],
        'title' => (string) $f['title'],
        'cvss' => $f['cvss_score'] !== null ? (float) $f['cvss_score'] : null,
        'cwe' => $f['cwe'], 'cve' => $f['cve'], 'summary' => $f['summary'],
        'impact' => $f['impact'], 'remediation' => $f['remediation'],
        'evidence' => $f['evidence_path'], 'asset' => $f['asset'] ?? '—',
        'tool' => $f['source_tool'], 'refs' => is_array($refs) ? $refs : [],
        'status' => (string) $f['verification_status'],
    ];
}, $rows);
$first = $findings[0] ?? [
    'id' => 0, 'severity' => 'info', 'title' => 'Belum ada findings', 'cvss' => null,
    'cwe' => null, 'cve' => null, 'summary' => 'Jalankan workflow untuk mengumpulkan temuan.',
    'impact' => '—', 'remediation' => '—', 'evidence' => null, 'asset' => '—',
    'tool' => '—', 'refs' => [], 'status' => 'unverified',
];
$sevCounts = [];
foreach (['critical', 'high', 'medium', 'low', 'info'] as $sev) {
    $sevCounts[$sev] = count(array_filter($findings, fn($f) => $f['severity'] === $sev));
}
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Vulnerability Assessment</h1>
    <p class="page-sub">Temuan aktif + detail per finding — data asli, semua run milikmu</p>
  </div>
</div>

<div class="grid-stats" style="grid-template-columns: repeat(5, 1fr)">
  <?php foreach ($sevCounts as $sev => $n): ?>
    <div class="stat-card<?= in_array($sev, ['critical'], true) ? ' tone-critical' : ($sev === 'high' ? ' tone-high' : '') ?>">
      <div class="stat-head"><span class="stat-label"><?= strtoupper($sev) ?></span></div>
      <div class="stat-value-row"><span class="stat-value"><?= $n ?></span></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid-bottom">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Findings</h2>
        <p class="panel-subtitle">Klik baris untuk detail — filter severity di kolom kanan atas tabel</p>
      </div>
    </div>
    <div class="table-wrap" style="max-height:460px; overflow-y:auto">
      <table class="tbl" id="findings-table">
        <thead><tr><th>Severity</th><th>Title</th><th>Asset</th><th>CVSS</th><th>CWE</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($findings as $f): ?>
            <tr class="finding-row<?= $f['id'] === $first['id'] ? ' row-selected' : '' ?>" data-finding="<?= $f['id'] ?>">
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
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Finding Detail</h2>
        <p class="panel-subtitle">Field lengkap temuan terpilih</p>
      </div>
      <span class="mono text-muted" id="fd-id">#<?= $first['id'] ?></span>
    </div>
    <div class="finding-detail" id="finding-detail">
      <div class="fd-head">
        <span class="fd-title" id="fd-title"><?= htmlspecialchars($first['title']) ?></span>
        <span class="sev sev-<?= $first['severity'] ?>" id="fd-sev"><?= strtoupper($first['severity']) ?></span>
        <span class="fd-score sev-<?= $first['severity'] ?>" id="fd-cvss"><?= number_format($first['cvss'], 1) ?></span>
      </div>
      <div class="fd-fields">
        <div class="fd-field"><span class="fd-label">ASSET</span><span class="fd-value mono" id="fd-asset"><?= htmlspecialchars($first['asset']) ?></span></div>
        <div class="fd-field"><span class="fd-label">CWE / CVE</span><span class="fd-value mono" id="fd-cwe"><?= htmlspecialchars(($first['cwe'] ?? '--') . ' / ' . ($first['cve'] ?? '--')) ?></span></div>
        <div class="fd-field"><span class="fd-label">SUMMARY</span><span class="fd-value" id="fd-summary"><?= htmlspecialchars($first['summary'] ?? '—') ?></span></div>
        <div class="fd-field"><span class="fd-label">EVIDENCE</span><span class="fd-value mono" id="fd-evidence"><?= htmlspecialchars($first['evidence'] ?? '--') ?></span></div>
        <div class="fd-field"><span class="fd-label">IMPACT</span><span class="fd-value" id="fd-impact"><?= htmlspecialchars($first['impact'] ?? '—') ?></span></div>
        <div class="fd-field"><span class="fd-label">REMEDIATION</span><span class="fd-value" id="fd-remediation"><?= htmlspecialchars($first['remediation'] ?? '—') ?></span></div>
        <div class="fd-field"><span class="fd-label">REFERENCES</span><span class="fd-value" id="fd-refs">
          <?php if (!empty($first['refs'])): $refTotal = count($first['refs']); foreach (array_values($first['refs']) as $refIdx => $ref): ?>
            <a href="<?= htmlspecialchars($ref) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(parse_url($ref, PHP_URL_HOST) ?: $ref) ?></a><?= $refIdx < $refTotal - 1 ? ' · ' : '' ?>
          <?php endforeach; else: ?>--<?php endif; ?>
        </span></div>
        <div class="fd-field"><span class="fd-label">VERIFICATION</span><span class="fd-value" id="fd-verification"><?= strtoupper(str_replace(' ', '_', $first['status'])) ?> · source <?= htmlspecialchars($first['tool']) ?></span></div>
      </div>
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
      set("fd-cvss", f.cvss != null ? Number(f.cvss).toFixed(1) : "--");
      set("fd-asset", f.asset);
      set("fd-cwe", (f.cwe || "--") + " / " + (f.cve || "--"));
      set("fd-summary", f.summary);
      set("fd-evidence", f.evidence || "--");
      set("fd-impact", f.impact);
      set("fd-remediation", f.remediation);
      var refs = document.getElementById("fd-refs");
      if (refs) refs.textContent = f.refs && f.refs.length ? f.refs.join(" · ") : "--";
      set("fd-verification", String(f.status || "UNVERIFIED").toUpperCase() + " · source " + f.tool);
      ["fd-sev", "fd-cvss"].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.className = el.className.replace(/sev-\w+/, "sev-" + f.severity);
      });
    });
  });
})();
</script>
