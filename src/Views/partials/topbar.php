<?php
/**
 * Top bar (Bagian 4.1) — fixed, full width.
 * $engine & $engagement dari layout app.php.
 */
$envActive = $_COOKIE['oges_env'] ?? 'lab';
$envs = ['lab' => 'LAB', 'staging' => 'STAGING', 'production' => 'PRODUCTION'];
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
    <span class="tb-value mono"><?= htmlspecialchars($engagement['name']) ?></span>
    <span class="tb-sub mono"><?= htmlspecialchars($engagement['id']) ?></span>
  </div>

  <div class="topbar-block env-toggle" role="group" aria-label="Environment">
    <?php foreach ($envs as $k => $label): ?>
      <button type="button" class="env-pill<?= $envActive === $k ? ' active' : '' ?>" data-env="<?= $k ?>"><?= $label ?></button>
    <?php endforeach; ?>
  </div>

  <div class="topbar-block authz">
    <span class="tb-label">AUTHORIZATION</span>
    <span class="tb-value">
      <span class="badge badge-success"><span class="dot"></span>AUTHORIZED</span>
      <span class="tb-sub">5/6 targets in scope</span>
    </span>
  </div>

  <div class="topbar-block ai-engine">
    <span class="tb-label">AI ENGINE</span>
    <span class="tb-value">
      <span class="badge badge-cyan"><span class="dot"></span>ONLINE</span>
      <span class="tb-sub mono"><?= htmlspecialchars($engine['ai_model']) ?> • online</span>
    </span>
  </div>

  <div class="topbar-block workers">
    <span class="tb-label">WORKERS</span>
    <span class="tb-value mono"><?= $engine['workers_used'] ?> / <?= $engine['workers_total'] ?></span>
    <span class="tb-label">QUEUE</span>
    <span class="tb-value mono"><?= $engine['queue'] ?></span>
  </div>

  <div class="topbar-right">
    <button type="button" class="icon-btn notif-btn" aria-label="Notifikasi" title="Notifikasi">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>
      </svg>
      <span class="notif-badge"><?= $engine['notifications'] ?></span>
    </button>
    <div class="user-chip">
      <span class="avatar" aria-hidden="true">CR</span>
      <span class="user-meta">
        <span class="user-name"><?= htmlspecialchars($engine['user']['name']) ?></span>
        <span class="user-role"><?= htmlspecialchars($engine['user']['role']) ?></span>
      </span>
    </div>
  </div>
</header>
