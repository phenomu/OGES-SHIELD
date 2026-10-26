<?php
/**
 * reports.php — 1D. List laporan dummy + tombol download (disabled Phase 1).
 */
View::$pageTitle = 'Reports — OGES-SHIELD';
View::$activeNav = 'reports';

$reports = MockData::reports();
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Reports</h1>
    <p class="page-sub">Laporan PDF per workflow run — auto-generate oleh orchestrator (WeasyPrint)</p>
  </div>
  <button type="button" class="btn" disabled title="Aktif setelah Phase 5">Generate Current Run</button>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Report History</h2>
      <p class="panel-subtitle">PDF tersimpan di storage/reports/{run_id}.pdf</p>
    </div>
    <span class="badge badge-cyan"><span class="dot"></span>weasyprint</span>
  </div>
  <?php if (count($reports) === 0): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.2" class="es-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      <div class="es-title">Belum ada report</div>
      <div class="es-desc">Selesaikan 1 workflow run sampai phase Report Generation — PDF muncul di sini otomatis (Phase 5).</div>
      <button type="button" class="btn btn-primary mt-3" onclick="location.href='/workflows'">Mulai workflow baru</button>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Run</th><th>Target</th><th>Generated</th><th>Findings</th><th>Pages</th><th>Size</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($reports as $r): ?>
            <tr>
              <td class="mono cell-strong"><?= htmlspecialchars($r['run']) ?></td>
              <td class="mono"><?= htmlspecialchars($r['target']) ?></td>
              <td class="mono"><?= $r['generated'] ?></td>
              <td class="mono"><?= $r['findings'] ?></td>
              <td class="mono"><?= $r['pages'] ?></td>
              <td class="mono"><?= $r['size'] ?></td>
              <td>
                <button type="button" class="btn btn-sm" disabled title="Download aktif di Phase 5">Download PDF</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="form-hint mt-3">Tombol download disabled di Phase 1 — generator WeasyPrint + link file nyambung di Phase 5.</p>
  <?php endif; ?>
</section>
