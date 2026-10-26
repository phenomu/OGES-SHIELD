<?php
/**
 * recon.php — Panel reconnaissance (tabs) — reuse inventory + timeline mock.
 */
View::$pageTitle = 'Reconnaissance — OGES-SHIELD';
View::$activeNav = 'recon';

$inventory = MockData::reconInventory();
$timeline = MockData::assetTimeline();
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Reconnaissance</h1>
    <p class="page-sub">Hasil discovery: DNS, subdomain, IP, service — dari tool recon pool</p>
  </div>
</div>

<section class="panel">
  <div data-tabs>
    <div class="tabs">
      <button type="button" class="tab-btn active" data-tab="inventory">Target Inventory</button>
      <button type="button" class="tab-btn" data-tab="dns">DNS Records</button>
      <button type="button" class="tab-btn" data-tab="subdomains">Subdomains</button>
      <button type="button" class="tab-btn" data-tab="ips">IP Addresses</button>
      <button type="button" class="tab-btn" data-tab="services">Services</button>
    </div>
    <div class="tab-pane active" data-pane="inventory">
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Asset</th><th>Type</th><th>Service</th><th>Status</th><th>Discovered</th><th>Scope</th></tr></thead>
          <tbody>
            <?php foreach ($inventory as $row): ?>
              <tr>
                <td class="mono cell-strong"><?= htmlspecialchars($row['asset']) ?></td>
                <td class="mono"><?= strtoupper($row['type']) ?></td>
                <td class="mono"><?= htmlspecialchars($row['service']) ?></td>
                <td><span class="st-<?= $row['status'] ?> mono">● <?= strtoupper($row['status']) ?></span></td>
                <td class="mono"><?= $row['discovered'] ?></td>
                <td><span class="scope-badge <?= $row['scope'] === 'in' ? 'scope-in' : 'scope-out' ?>"><?= $row['scope'] === 'in' ? 'IN SCOPE' : 'OUT' ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php foreach (['dns' => 'DNS Records', 'subdomains' => 'Subdomains', 'ips' => 'IP Addresses', 'services' => 'Services'] as $paneKey => $paneLabel): ?>
      <div class="tab-pane" data-pane="<?= $paneKey ?>">
        <div class="empty-state">
          <div class="es-title"><?= $paneLabel ?></div>
          <div class="es-desc">Coming from real scan (Phase 3+).</div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="chart-box mt-3" style="height:140px">
    <canvas data-timeline="<?= htmlspecialchars(json_encode($timeline)) ?>"></canvas>
  </div>
  <div class="ops-meta">
    <div class="ops-meta-item"><span class="ops-meta-label">TOTAL ASSETS</span><span class="ops-meta-value"><?= count($inventory) ?></span></div>
    <div class="ops-meta-item"><span class="ops-meta-label">IN SCOPE</span><span class="ops-meta-value"><?= count(array_filter($inventory, fn($r) => $r['scope'] === 'in')) ?></span></div>
    <div class="ops-meta-item"><span class="ops-meta-label">ONLINE</span><span class="ops-meta-value"><?= count(array_filter($inventory, fn($r) => $r['status'] === 'online')) ?></span></div>
  </div>
</section>
