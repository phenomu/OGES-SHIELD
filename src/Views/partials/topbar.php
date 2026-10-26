<?php
/**
 * Top bar (Bagian 4.1) — fixed, full width. Data asli (Phase 6):
 * engagement = run terbaru milik user, workers = AI loop aktif, notif = event penting.
 */
$user = Auth::user();
$isSuper = ($user['role'] ?? '') === 'superadmin';

// run terbaru milik user (engagement) — posisi param, superadmin lihat semua
$latestRun = Database::run(
    'SELECT id, status, current_phase FROM workflow_runs
     WHERE (? = 1 OR created_by = ?) ORDER BY created_at DESC LIMIT 1',
    [$isSuper ? 1 : 0, (int) $user['id']]
)->fetch();

// workers aktif + queue — live dari DB
$workers = Database::run(
    'SELECT COUNT(*) AS n FROM workflow_runs WHERE status IN ("running","awaiting_confirmation")'
)->fetch();
$queue = Database::run(
    'SELECT COUNT(*) AS n FROM workflow_runs WHERE status = "pending"'
)->fetch();

// notifikasi: event penting terbaru (confirmation / report / failed / stopped)
$notifRunFilter = $isSuper ? '' : ' AND wr.created_by = ' . (int) $user['id'];
$notifRows = Database::run(
    'SELECT re.type, re.payload, re.created_at, wr.id AS run_id
     FROM run_events re JOIN workflow_runs wr ON wr.id = re.run_id
     WHERE re.type IN ("confirmation_required","report_generated","run_failed","run_stopped")'
    . $notifRunFilter . '
     ORDER BY re.id DESC LIMIT 8'
)->fetchAll(PDO::FETCH_ASSOC);
$notifItems = [];
foreach ($notifRows as $nrow) {
    $msg = match ((string) $nrow['type']) {
        'confirmation_required' => 'Tool destructive menunggu konfirmasi',
        'report_generated'      => 'Report PDF siap diunduh',
        'run_failed'            => 'Run gagal',
        'run_stopped'           => 'Run dihentikan',
        default                 => (string) $nrow['type'],
    };
    $notifItems[] = [
        'msg'  => $msg,
        'time' => substr((string) $nrow['created_at'], 11, 5),
        'run'  => substr((string) $nrow['run_id'], 0, 8),
        'href' => '/run?id=' . rawurlencode((string) $nrow['run_id']),
    ];
}
$notifCount = count($notifItems);
?>
<header class="topbar">
  <div class="topbar-brand">
    <span class="logo-shield" aria-hidden="true">
      <svg viewBox="0 0 32 32" width="22" height="22">
        <path d="M16 2 L28 6 V16 C28 23.7 22.9 29.2 16 31 C9.1 29.2 4 23.7 4 16 V6 Z" fill="#0A0E18" stroke="#22D3EE" stroke-width="2"/>
        <path d="M12.5 16.2 L15 18.8 L20 13.2" stroke="#22D3EE" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>
    <span class="brand-text">
      <span class="brand-name">OGES-SHIELD</span>
      <span class="brand-tag">AI-AUGMENTED PENETRATION TESTING</span>
    </span>
  </div>

  <div class="topbar-block engagement">
    <span class="tb-label">CURRENT ENGAGEMENT</span>
    <span class="tb-value mono"><?= e($latestRun ? 'RUN-' . substr((string) $latestRun['id'], 0, 8) : '—') ?></span>
    <span class="tb-sub mono"><?= e($latestRun ? strtoupper((string) $latestRun['status']) : 'no active engagement') ?></span>
  </div>

  <div class="topbar-block authz">
    <span class="tb-label">AUTHORIZATION</span>
    <span class="tb-value">
      <span class="badge badge-success"><span class="dot"></span><?= $isSuper ? 'SUPERADMIN' : 'AUTHORIZED' ?></span>
    </span>
  </div>

  <div class="topbar-block ai-engine">
    <span class="tb-label">AI ENGINE</span>
    <span class="tb-value">
      <span class="badge badge-cyan"><span class="dot"></span>ONLINE</span>
      <span class="tb-sub mono">glm-5.3</span>
    </span>
  </div>

  <div class="topbar-block workers">
    <span class="tb-label">WORKERS</span>
    <span class="tb-value mono"><?= (int) ($workers['n'] ?? 0) ?> / 8</span>
    <span class="tb-label">QUEUE</span>
    <span class="tb-value mono"><?= (int) ($queue['n'] ?? 0) ?></span>
  </div>

  <div class="topbar-right">
    <div class="notif-wrap">
      <button type="button" class="icon-btn notif-btn" aria-label="Notifikasi" title="Notifikasi" data-dropdown="notif-menu" aria-haspopup="true" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>
        </svg>
        <?php if ($notifCount > 0): ?><span class="notif-badge"><?= $notifCount ?></span><?php endif; ?>
      </button>
      <div class="dropdown-menu" id="notif-menu" role="menu" aria-label="Notifikasi" hidden>
        <div class="dropdown-head">NOTIFIKASI</div>
        <?php if (!$notifItems): ?>
          <div class="dropdown-empty">Belum ada notifikasi.</div>
        <?php else: ?>
          <?php foreach ($notifItems as $ni): ?>
            <a class="dropdown-item" href="<?= e($ni['href']) ?>" role="menuitem">
              <span class="dropdown-msg"><?= e($ni['msg']) ?></span>
              <span class="dropdown-sub mono">run <?= e($ni['run']) ?> · <?= e($ni['time']) ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="user-wrap">
      <button type="button" class="user-chip" data-dropdown="user-menu" aria-haspopup="true" aria-expanded="false" title="Menu akun">
        <span class="avatar" aria-hidden="true"><?= e($user['username'] ? strtoupper(substr((string) $user['username'], 0, 2)) : 'US') ?></span>
        <span class="user-meta">
          <span class="user-name"><?= e($user['name'] ?? $user['username']) ?></span>
          <span class="user-role"><?= e(Auth::roleLabel((string) $user['role'])) ?></span>
        </span>
        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      <div class="dropdown-menu" id="user-menu" role="menu" aria-label="Menu akun" hidden>
        <div class="dropdown-head"><?= e($user['username']) ?></div>
        <a class="dropdown-item" href="/settings" role="menuitem">Settings</a>
        <a class="dropdown-item" href="/docs" role="menuitem">Dokumentasi</a>
        <div class="dropdown-sep"></div>
        <form method="post" action="/logout">
          <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
          <button type="submit" class="dropdown-item dropdown-danger" role="menuitem">Logout</button>
        </form>
      </div>
    </div>
  </div>
</header>
