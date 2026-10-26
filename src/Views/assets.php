<?php
/**
 * assets.php — Asset/host inventory (sidebar menu "Asset Discovery").
 */
View::$pageTitle = 'Assets — OGES-SHIELD';
View::$activeNav = 'assets';

$inventory = MockData::reconInventory();
$inScope = count(array_filter($inventory, fn($r) => $r['scope'] === 'in'));
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Asset Discovery</h1>
    <p class="page-sub">Inventory asset/host — hanya asset in-scope bisa di-scan</p>
  </div>
  <button type="button" class="btn btn-primary" data-modal-open="modal-add-target">+ Register Target</button>
</div>

<div class="grid-stats" style="grid-template-columns: repeat(4, 1fr)">
  <div class="stat-card">
    <div class="stat-head"><span class="stat-label">TOTAL ASSETS</span></div>
    <div class="stat-value-row"><span class="stat-value"><?= count($inventory) ?></span></div>
  </div>
  <div class="stat-card">
    <div class="stat-head"><span class="stat-label">IN SCOPE</span></div>
    <div class="stat-value-row"><span class="stat-value" style="color: #A78BFA"><?= $inScope ?></span></div>
  </div>
  <div class="stat-card">
    <div class="stat-head"><span class="stat-label">ONLINE</span></div>
    <div class="stat-value-row"><span class="stat-value" style="color: var(--status-success)"><?= count(array_filter($inventory, fn($r) => $r['status'] === 'online')) ?></span></div>
  </div>
  <div class="stat-card">
    <div class="stat-head"><span class="stat-label">OUT OF SCOPE</span></div>
    <div class="stat-value-row"><span class="stat-value" style="color: var(--text-faint)"><?= count($inventory) - $inScope ?></span></div>
  </div>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Target Inventory</h2>
      <p class="panel-subtitle">Registrasi target wajib eksplisit — tidak ada scanning di luar scope</p>
    </div>
  </div>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Asset</th><th>Type</th><th>Service</th><th>Status</th><th>Discovered</th><th>Scope</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($inventory as $row): ?>
          <tr>
            <td class="mono cell-strong"><?= htmlspecialchars($row['asset']) ?></td>
            <td class="mono"><?= strtoupper($row['type']) ?></td>
            <td class="mono"><?= htmlspecialchars($row['service']) ?></td>
            <td><span class="st-<?= $row['status'] ?> mono">● <?= strtoupper($row['status']) ?></span></td>
            <td class="mono"><?= $row['discovered'] ?></td>
            <td><span class="scope-badge <?= $row['scope'] === 'in' ? 'scope-in' : 'scope-out' ?>"><?= $row['scope'] === 'in' ? 'IN SCOPE' : 'OUT' ?></span></td>
            <td>
              <?php if ($row['scope'] === 'in'): ?>
                <button type="button" class="btn btn-sm" onclick="location.href='/workflows'">Set Workflow</button>
              <?php else: ?>
                <button type="button" class="btn btn-sm" disabled title="Out of scope — tidak bisa discan">Set Workflow</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Modal register target -->
<div class="modal-backdrop" id="modal-add-target">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Register Target (In-Scope)</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-field">
        <label class="form-label" for="at-name">LABEL TAMPILAN</label>
        <input class="input" type="text" id="at-name" placeholder="ACME web produksi">
      </div>
      <div class="form-field">
        <label class="form-label" for="at-type">TYPE</label>
        <select class="select mono" id="at-type">
          <option value="domain">domain</option>
          <option value="url">url</option>
          <option value="ip">ip</option>
          <option value="cidr">cidr</option>
        </select>
      </div>
      <div class="form-field">
        <label class="form-label" for="at-value">VALUE</label>
        <input class="input mono" type="text" id="at-value" placeholder="acme-corp.com">
        <span class="form-hint">Dengan registrasi ini kamu menyatakan punya otorisasi menguji asset tsb.</span>
      </div>
      <div class="form-field">
        <label class="form-label" for="at-env">ENVIRONMENT</label>
        <select class="select mono" id="at-env">
          <option value="lab">lab</option>
          <option value="staging">staging</option>
          <option value="production">production</option>
        </select>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" data-modal-close disabled title="Persist DB di Phase 2">Save Target</button>
    </div>
  </div>
</div>
