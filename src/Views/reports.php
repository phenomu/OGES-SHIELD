<?php
/**
 * reports.php — Phase 5: list report nyata per run + download jalan + regenerate.
 */
View::$pageTitle = 'Reports — OGES-SHIELD';
View::$activeNav = 'reports';

/** @var array $reports */
$reports = $reports ?? [];
$flashErr = Session::flash('rep_error');
$flashOk = Session::flash('rep_success');
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Reports</h1>
    <p class="page-sub">Laporan PDF per workflow run — auto-generate oleh orchestrator (WeasyPrint)</p>
  </div>
</div>

<?php if ($flashErr): ?>
  <div class="auth-alert" style="border-color: var(--sev-critical)"><?= e($flashErr) ?></div>
<?php endif; ?>
<?php if ($flashOk): ?>
  <div class="auth-alert" style="border-color: var(--status-success)"><?= e($flashOk) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Report History</h2>
      <p class="panel-subtitle">PDF tersimpan di storage/reports/{run_id}.pdf — stream via PHP (auth + ownership)</p>
    </div>
    <span class="badge badge-cyan"><span class="dot"></span>weasyprint</span>
  </div>
  <?php if (count($reports) === 0): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.2" class="es-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      <div class="es-title">Belum ada report</div>
      <div class="es-desc">Selesaikan 1 workflow run sampai selesai — PDF digenerate otomatis di phase Report Generation dan muncul di sini.</div>
      <button type="button" class="btn btn-primary mt-3" onclick="location.href='/workflows'">Mulai workflow baru</button>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Run</th><th>Target</th><th>Template</th><th>Findings</th><th>Critical</th><th>Generated</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($reports as $r): ?>
            <tr>
              <td class="mono cell-strong"><a href="/run?id=<?= e($r['run_id']) ?>" style="color: var(--accent-cyan)"><?= e(substr((string) $r['run_id'], 0, 13)) ?>…</a></td>
              <td class="mono"><?= e($r['target_value']) ?></td>
              <td><?= e($r['template_name']) ?></td>
              <td class="mono"><?= (int) $r['findings_count'] ?></td>
              <td class="mono" style="color: var(--sev-critical)"><?= (int) $r['critical_count'] ?></td>
              <td class="mono"><?= e(substr((string) $r['generated_at'], 0, 16)) ?></td>
              <td style="white-space: nowrap">
                <a class="btn btn-sm" href="/report/download?id=<?= e($r['run_id']) ?>">⬇ Download PDF</a>
                <form method="post" action="/reports/regenerate" style="display:inline" data-confirm="Buat ulang PDF untuk run ini? Overall summary AI akan di-generate ulang.">
                  <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
                  <input type="hidden" name="run_id" value="<?= e($r['run_id']) ?>">
                  <button type="submit" class="btn btn-sm">Regenerate</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<script>
document.querySelectorAll("form[data-confirm]").forEach(function (f) {
  f.addEventListener("submit", function (e) {
    if (!window.confirm(f.dataset.confirm)) e.preventDefault();
  });
});
</script>
