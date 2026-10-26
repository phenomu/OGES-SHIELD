<?php
/**
 * audit.php — Audit Logs (menu sidebar; fungsional penuh Phase 6, dummy sekarang).
 */
View::$pageTitle = 'Audit Logs — OGES-SHIELD';
View::$activeNav = 'audit';

$logs = [
  ['time' => '2026-10-26 15:47:31', 'user' => 'ai-orchestrator', 'action' => 'tool_executed', 'tool' => 'nuclei', 'target' => 'www.acme-corp.com', 'result' => '6 findings'],
  ['time' => '2026-10-26 15:42:10', 'user' => 'ai-orchestrator', 'action' => 'ai_decision', 'tool' => 'nuclei', 'target' => 'www.acme-corp.com', 'result' => 'selected'],
  ['time' => '2026-10-26 15:41:55', 'user' => 'ai-orchestrator', 'action' => 'tool_executed', 'tool' => 'httpx', 'target' => '23 hosts', 'result' => '19 live'],
  ['time' => '2026-10-26 15:39:02', 'user' => 'ai-orchestrator', 'action' => 'ai_decision', 'tool' => 'httpx', 'target' => 'acme-corp.com', 'result' => 'selected'],
  ['time' => '2026-10-26 15:38:41', 'user' => 'ai-orchestrator', 'action' => 'tool_executed', 'tool' => 'subfinder', 'target' => 'acme-corp.com', 'result' => '23 subs'],
  ['time' => '2026-10-26 15:37:58', 'user' => 'capt.rakha', 'action' => 'run_started', 'tool' => '--', 'target' => 'acme-corp.com', 'result' => 'RUN-2026-0417-A7F3'],
  ['time' => '2026-10-26 15:37:55', 'user' => 'scope_guard', 'action' => 'scope_check', 'tool' => '--', 'target' => 'acme-corp.com', 'result' => 'in_scope=true'],
  ['time' => '2026-10-26 09:12:04', 'user' => 'admin', 'action' => 'user_approved', 'tool' => '--', 'target' => '--', 'result' => 'user=operator02'],
];
$actBadge = fn(string $a) => match ($a) {
    'tool_executed' => 'sev-low',
    'ai_decision'   => 'sev-info',
    'scope_check'   => 'sev-info',
    'run_started'   => 'sev-medium',
    'user_approved' => 'sev-info',
    default         => 'sev-info',
};
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Audit Logs</h1>
    <p class="page-sub">Jejak semua eksekusi tool + keputusan AI + security events — permanen, tidak bisa dihapus dari UI</p>
  </div>
  <button type="button" class="btn" disabled title="Phase 6">Export CSV</button>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Recent Events</h2>
      <p class="panel-subtitle"><?= count($logs) ?> event (dummy Phase 1 — sumber nyata: tabel audit_logs Phase 3)</p>
    </div>
  </div>
  <div class="table-wrap" style="max-height:520px; overflow-y:auto">
    <table class="tbl">
      <thead><tr><th>Waktu</th><th>Aktor</th><th>Action</th><th>Tool</th><th>Target</th><th>Hasil</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="mono"><?= $l['time'] ?></td>
            <td class="mono"><?= htmlspecialchars($l['user']) ?></td>
            <td><span class="sev <?= $actBadge($l['action']) ?>"><?= strtoupper($l['action']) ?></span></td>
            <td class="mono"><?= $l['tool'] ?></td>
            <td class="mono"><?= htmlspecialchars($l['target']) ?></td>
            <td class="mono"><?= htmlspecialchars($l['result']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
