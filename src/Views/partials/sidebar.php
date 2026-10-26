<?php
/**
 * Sidebar (Bagian 4.2) — fixed kiri.
 * Key = key route/match View::$activeNav.
 */
$nav = [
    ['key' => 'overview',   'label' => 'Overview',              'href' => '/dashboard.php', 'icon' => 'grid'],
    ['key' => 'operations', 'label' => 'Operations',            'href' => '/dashboard.php#operations', 'icon' => 'activity'],
    ['key' => 'assets',     'label' => 'Asset Discovery',       'href' => '/assets.php',    'icon' => 'db'],
    ['key' => 'recon',      'label' => 'Reconnaissance',        'href' => '/recon.php',     'icon' => 'radar'],
    ['key' => 'vulns',      'label' => 'Vulnerability Assessment', 'href' => '/vulnerabilities.php', 'icon' => 'shield-alert'],
    ['key' => 'exploit',    'label' => 'Exploitation Validation', 'href' => '/vulnerabilities.php?tab=exploit', 'icon' => 'crosshair'],
    ['key' => 'persistence','label' => 'Persistence Simulation',  'href' => '/vulnerabilities.php?tab=persistence', 'icon' => 'link'],
    ['key' => 'findings',   'label' => 'Findings',              'href' => '/findings.php',  'icon' => 'list-alert'],
    ['key' => 'reports',    'label' => 'Reports',               'href' => '/reports.php',   'icon' => 'file-text'],
    ['key' => 'workflows',  'label' => 'Automation Workflows',  'href' => '/workflows.php', 'icon' => 'workflow'],
    ['key' => 'modules',    'label' => 'Python/Tool Modules',   'href' => '/admin/tool-registry.php', 'icon' => 'cpu'],
    ['key' => 'audit',      'label' => 'Audit Logs',            'href' => '/dashboard.php#audit', 'icon' => 'scroll'],
];
$adminNav = [
    ['key' => 'approvals',    'label' => 'Approvals',     'href' => '/admin/approvals.php', 'icon' => 'user-check'],
    ['key' => 'toolregistry', 'label' => 'Tool Registry', 'href' => '/admin/tool-registry.php', 'icon' => 'wrench'],
];
$icons = [
    'grid'         => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'activity'     => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    'db'           => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
    'radar'        => '<circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/>',
    'shield-alert' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
    'crosshair'    => '<circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/>',
    'link'         => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    'list-alert'   => '<path d="M3 6h13"/><path d="M3 12h9"/><path d="M3 18h5"/><path d="M16 15l2 2 4-4"/>',
    'file-text'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'workflow'     => '<rect x="3" y="3" width="8" height="8" rx="1"/><path d="M7 11v4"/><rect x="13" y="15" width="8" height="6" rx="1"/><path d="M13 6h4a2 2 0 0 1 2 2v2"/>',
    'cpu'          => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/>',
    'scroll'       => '<path d="M8 21h12a2 2 0 0 0 2-2v-2H10v2a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v3h4"/>',
    'user-check'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M16 11l2 2 4-4"/>',
    'wrench'       => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
];
$active = View::$activeNav;
?>
<aside class="sidebar" id="sidebar">
  <nav class="sidebar-nav" aria-label="Menu utama">
    <?php foreach ($nav as $item): ?>
      <a href="<?= $item['href'] ?>" class="nav-item<?= $active === $item['key'] ? ' active' : '' ?>" data-nav="<?= $item['key'] ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$item['icon']] ?></svg>
        <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
      </a>
    <?php endforeach; ?>

    <?php if (!empty($adminNav)): ?>
      <div class="nav-section">ADMIN</div>
      <?php foreach ($adminNav as $item): ?>
        <a href="<?= $item['href'] ?>" class="nav-item nav-admin<?= $active === $item['key'] ? ' active' : '' ?>" data-nav="<?= $item['key'] ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$item['icon']] ?></svg>
          <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="nav-section">SETTINGS</div>
    <a href="/dashboard.php#settings" class="nav-item<?= $active === 'settings' ? ' active' : '' ?>" data-nav="settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons['settings'] ?></svg>
      <span class="nav-label">Settings</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <span class="sf-logo mono">OGES</span>
    <span class="sf-tag">AUTOMATED OFFENSIVE OPS</span>
  </div>
</aside>
