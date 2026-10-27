<?php
/**
 * audit.php — Audit Logs fungsional (Phase 6): data asli audit_logs + filter
 * action/aktor + pagination + export CSV. Tidak ada aksi hapus (permanen by design).
 */
View::$pageTitle = 'Audit Logs — OGES-SHIELD';
View::$activeNav = 'audit';

$user = Auth::user();
$isSuper = ($user['role'] ?? '') === 'superadmin';

// ---- filter (GET, whitelist server-side) ----
$fAction = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
$fActor  = is_string($_GET['actor'] ?? null) ? $_GET['actor'] : '';
$fPage   = is_numeric($_GET['page'] ?? null) ? max(1, (int) $_GET['page']) : 1;
$perPage = 50;

$actions = [
    'login_success', 'login_failed', 'logout', 'register',
    'user_approved', 'user_rejected',
    'run_created', 'run_started', 'run_stopped', 'run_stop_requested', 'run_deleted',
    'run_resume_viewed', 'engagement_selected', 'engagement_reset',
    'tool_executed', 'scope_violation', 'tool_registry_saved', 'tools_reloaded',
    'report_download', 'report_regenerated', 'template_created', 'template_deleted',
];
if ($fAction !== '' && !in_array($fAction, $actions, true)) { $fAction = ''; }
if (strlen($fActor) > 60) { $fActor = ''; }

$where  = [];
$params = [];
if ($fAction !== '') { $where[] = 'a.action = ?'; $params[] = $fAction; }
if ($fActor !== '')  { $where[] = 'u.username LIKE ?'; $params[] = $fActor . '%'; }
if (!$isSuper) {
    // user biasa hanya melihat jejak dirinya sendiri (privacy antar user)
    $where[] = 'a.user_id = ?';
    $params[] = (int) $user['id'];
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// ---- jumlah total (untuk pagination) ----
$total = (int) Database::run(
    "SELECT COUNT(*) AS n FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id" . $whereSql,
    $params
)->fetch(PDO::FETCH_ASSOC)['n'];
$totalPages = max(1, (int) ceil($total / $perPage));
if ($fPage > $totalPages) { $fPage = $totalPages; }
$offset = ($fPage - 1) * $perPage;

// ---- rows ----
$logs = Database::run(
    "SELECT a.id, a.action, a.target_value, a.tool_id, a.detail, a.created_at,
            u.username, u.role
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.user_id" . $whereSql . "
     ORDER BY a.id DESC
     LIMIT " . $perPage . " OFFSET " . $offset,
    $params
)->fetchAll(PDO::FETCH_ASSOC);

$actBadge = fn(string $a): string => match ($a) {
    'tool_executed'         => 'sev-low',
    'scope_violation'       => 'sev-critical',
    'login_failed'          => 'sev-critical',
    'run_stopped', 'run_deleted', 'run_stop_requested' => 'sev-medium',
    'run_started', 'run_created', 'run_resume_viewed'  => 'sev-medium',
    'template_created', 'template_deleted', 'tool_registry_saved', 'tools_reloaded' => 'sev-info',
    'login_success', 'logout', 'register' => 'sev-info',
    'user_approved', 'user_rejected'      => 'sev-info',
    'engagement_selected', 'engagement_reset', 'report_download', 'report_regenerated' => 'sev-info',
    default                 => 'sev-info',
};

/** ringkas detail JSON → 1 baris teks aman (tidak render HTML) */
function auditDetailLine(?string $detail): string
{
    if ($detail === null || $detail === '') { return '—'; }
    $d = json_decode($detail, true);
    if (!is_array($d)) { return '—'; }
    $out = [];
    foreach ($d as $k => $v) {
        if ($v === null || $v === '') { continue; }
        if (is_bool($v)) { $v = $v ? 'true' : 'false'; }
        if (is_array($v)) { $v = count($v) . ' item'; }
        if (strlen((string) $v) > 60) { $v = mbTrunc((string) $v, 60); }
        $out[] = $k . '=' . $v;
    }
    return $out === [] ? '—' : implode(' ', $out);
}
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Audit Logs</h1>
    <p class="page-sub">Jejak semua eksekusi tool + keputusan AI + security events — permanen, tidak bisa dihapus dari UI</p>
  </div>
  <a class="btn" href="/audit/export?<?= http_build_query(array_filter([
    'action' => $fAction, 'actor' => $fActor, 'csrf' => Session::csrf(),
  ])) ?>">Export CSV</a>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Recent Events</h2>
      <p class="panel-subtitle"><?= $total ?> event total<?= $isSuper ? ' — semua user' : ' — milikmu saja' ?><?= $fPage > 1 ? " · hal. {$fPage}/{$totalPages}" : '' ?></p>
    </div>
    <form method="get" action="/audit" style="display:flex; gap:var(--sp-2); align-items:flex-end">
      <div class="form-group" style="margin:0">
        <label class="form-label" for="af-action">ACTION</label>
        <select class="input" id="af-action" name="action" style="min-width:170px">
          <option value="">semua action</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= e($a) ?>"<?= $fAction === $a ? ' selected' : '' ?>><?= e(str_replace('_', ' ', $a)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" for="af-actor">AKTOR</label>
        <input class="input mono" id="af-actor" name="actor" value="<?= e($fActor) ?>" placeholder="mis. admin" maxlength="60" style="min-width:130px">
      </div>
      <button type="submit" class="btn btn-primary">Filter</button>
      <?php if ($fAction !== '' || $fActor !== ''): ?>
        <a class="btn" href="/audit">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-wrap" style="max-height:560px; overflow-y:auto">
    <table class="tbl">
      <thead><tr><th>ID</th><th>Waktu</th><th>Aktor</th><th>Action</th><th>Tool</th><th>Target</th><th>Detail</th></tr></thead>
      <tbody>
        <?php if ($logs === []): ?>
          <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:24px">
            Belum ada event audit<?= $fAction !== '' || $fActor !== '' ? ' untuk filter ini' : '' ?>.
          </td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="mono">#<?= (int) $l['id'] ?></td>
            <td class="mono"><?= e(mbTrunc((string) $l['created_at'], 19)) ?></td>
            <td class="mono"><?= e($l['username'] ?? 'system') ?><?= ($l['role'] ?? '') === 'superadmin' ? ' ★' : '' ?></td>
            <td><span class="sev <?= $actBadge((string) $l['action']) ?>"><?= e(strtoupper(str_replace('_', ' ', (string) $l['action']))) ?></span></td>
            <td class="mono"><?= e($l['tool_id'] ?? '—') ?></td>
            <td class="mono"><?= e(mbTrunc((string) ($l['target_value'] ?? '—'), 34)) ?></td>
            <td class="mono" style="font-size:10px; color:var(--text-muted)"><?= e(auditDetailLine($l['detail'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <div style="display:flex; gap:var(--sp-2); align-items:center; justify-content:flex-end; margin-top:var(--sp-3)">
      <?php if ($fPage > 1): ?>
        <a class="btn btn-sm" href="/audit?<?= http_build_query(['action' => $fAction, 'actor' => $fActor, 'page' => $fPage - 1]) ?>">← Sebelumnya</a>
      <?php endif; ?>
      <span class="mono" style="font-size:var(--fs-xs); color:var(--text-muted)">hal. <?= $fPage ?> / <?= $totalPages ?></span>
      <?php if ($fPage < $totalPages): ?>
        <a class="btn btn-sm" href="/audit?<?= http_build_query(['action' => $fAction, 'actor' => $fActor, 'page' => $fPage + 1]) ?>">Berikutnya →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
