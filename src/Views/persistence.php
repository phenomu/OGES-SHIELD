<?php
/**
 * persistence.php — Persistence Simulation (workflow phase 06).
 * Dummy Phase 1: simulasi dwell time & re-entry path (tanpa benar-benar persist di target).
 */
View::$pageTitle = 'Persistence Simulation — OGES-SHIELD';
View::$activeNav = 'persistence';

$simulations = [
  ['vector' => 'Webshell drop (imagetragick)', 'asset' => 'api.acme-corp.com', 'dwell' => '4m 12s', 'reentry' => 'ok', 'cleanup' => 'cleaned', 'note' => 'File .php di /uploads dihapus otomatis oleh cleanup_verification.'],
  ['vector' => 'Scheduled task (SQLi xp_cmdshell)', 'asset' => 'staging.acme-corp.com', 'dwell' => '0m 00s', 'reentry' => 'blocked', 'cleanup' => 'n/a', 'note' => 'MySQL user non-root — xp_cmdshell tidak tersedia, simulasi dihentikan.'],
  ['vector' => 'JWT token replay (24h validity)', 'asset' => 'api.acme-corp.com', 'dwell' => '24h simulated', 'reentry' => 'ok', 'cleanup' => 'token revoked', 'note' => 'Replay window = masa expiry token; rekomendasi refresh 15m.'],
];
$reBadge = fn(string $r) => match ($r) {
    'ok'      => '<span class="sev sev-critical">RE-ENTRY OK</span>',
    'blocked' => '<span class="sev sev-info">BLOCKED</span>',
    default   => '<span class="sev sev-info">--</span>',
};
$clBadge = fn(string $c) => match ($c) {
    'cleaned'      => '<span class="sev sev-info">CLEANED</span>',
    'token revoked'=> '<span class="sev sev-info">REVOKED</span>',
    default        => '<span class="sev sev-medium">PENDING</span>',
};
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Persistence Simulation</h1>
    <p class="page-sub">Simulasi re-entry & dwell time — SEMUA artefak dihapus di phase Cleanup & Verification</p>
  </div>
  <span class="badge badge-cyan"><span class="dot"></span>LAB-ONLY</span>
</div>

<div class="grid-stats" style="grid-template-columns: repeat(3, 1fr)">
  <div class="stat-card"><div class="stat-head"><span class="stat-label">VEKTOR DISIMULASI</span></div><div class="stat-value-row"><span class="stat-value"><?= count($simulations) ?></span></div></div>
  <div class="stat-card"><div class="stat-head"><span class="stat-label">RE-ENTRY BERHASIL</span></div><div class="stat-value-row"><span class="stat-value" style="color: var(--sev-critical)"><?= count(array_filter($simulations, fn($s) => $s['reentry'] === 'ok')) ?></span></div></div>
  <div class="stat-card"><div class="stat-head"><span class="stat-label">ARTEFAK DIBERSIHKAN</span></div><div class="stat-value-row"><span class="stat-value" style="color: var(--status-success)"><?= count(array_filter($simulations, fn($s) => in_array($s['cleanup'], ['cleaned', 'token revoked'], true))) ?></span></div></div>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Simulation Matrix</h2>
      <p class="panel-subtitle">Vektor persistensi yang dicoba AI berdasarkan finding confirmed</p>
    </div>
  </div>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Vector</th><th>Asset</th><th>Dwell Time</th><th>Re-entry</th><th>Cleanup</th><th>Catatan</th></tr></thead>
      <tbody>
        <?php foreach ($simulations as $s): ?>
          <tr>
            <td class="cell-strong"><?= htmlspecialchars($s['vector']) ?></td>
            <td class="mono"><?= htmlspecialchars($s['asset']) ?></td>
            <td class="mono"><?= $s['dwell'] ?></td>
            <td><?= $reBadge($s['reentry']) ?></td>
            <td><?= $clBadge($s['cleanup']) ?></td>
            <td style="max-width:300px"><?= htmlspecialchars($s['note']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="auth-alert ok mt-3" style="border-radius: var(--r-md)">
    <strong>Guardrail:</strong> fase ini hanya berjalan di environment <span class="mono">lab</span> dan target in-scope.
    Simulasi tidak meninggalkan artefak — cleanup_verification wajib sukses sebelum phase Report Generation.
  </div>
</section>
