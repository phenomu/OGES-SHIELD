<?php
/**
 * assets.php — Asset/host inventory (Phase 3: data DB asli + registrasi fungsional).
 * Sidebar menu "Asset Discovery".
 */
View::$pageTitle = 'Assets — OGES-SHIELD';
View::$activeNav = 'assets';

$user = Auth::user();
$targets = Database::run(
    'SELECT id, name, type, value, environment, in_scope, created_at
     FROM targets
     WHERE ? = \'superadmin\' OR user_id = ?
     ORDER BY in_scope DESC, id ASC',
    [$user['role'], (int) $user['id']]
)->fetchAll();

$inScope = count(array_filter($targets, fn($t) => (int) $t['in_scope'] === 1));
$flashOk  = Session::flash('target_ok');
$flashErr = Session::flash('target_error');
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Asset Discovery</h1>
    <p class="page-sub">Inventory target terdaftar — hanya asset in-scope bisa di-scan</p>
  </div>
  <button type="button" class="btn btn-primary" data-modal-open="modal-add-target">+ Register Target</button>
</div>

<?php if ($flashOk): ?>
  <div class="auth-alert ok mb-3" style="border-radius: var(--r-md)"><?= e($flashOk) ?></div>
<?php elseif ($flashErr): ?>
  <div class="auth-alert error mb-3" style="border-radius: var(--r-md)"><?= e($flashErr) ?></div>
<?php endif; ?>

<div class="grid-stats" style="grid-template-columns: repeat(3, 1fr)">
  <div class="stat-card">
    <div class="stat-head"><span class="stat-label">TOTAL TARGET</span></div>
    <div class="stat-value-row"><span class="stat-value"><?= count($targets) ?></span></div>
  </div>
  <div class="stat-card">
    <div class="stat-head"><span class="stat-label">IN SCOPE</span></div>
    <div class="stat-value-row"><span class="stat-value" style="color: #A78BFA"><?= $inScope ?></span></div>
  </div>
  <div class="stat-card">
    <div class="stat-head"><span class="stat-label">OUT OF SCOPE</span></div>
    <div class="stat-value-row"><span class="stat-value" style="color: var(--text-faint)"><?= count($targets) - $inScope ?></span></div>
  </div>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Target Inventory</h2>
      <p class="panel-subtitle">Registrasi target wajib eksplisit — tidak ada scanning di luar scope</p>
    </div>
  </div>
  <?php if (count($targets) === 0): ?>
    <div class="empty-state">
      <div class="es-title">Belum ada target terdaftar</div>
      <div class="es-desc">Daftar dulu asset milikmu yang berhak diuji, lalu set workflow dari halaman Workflows.</div>
      <div class="mt-4"><button type="button" class="btn btn-primary" data-modal-open="modal-add-target">+ Register Target</button></div>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Asset</th><th>Nama</th><th>Type</th><th>Environment</th><th>Registered</th><th>Scope</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($targets as $t): ?>
          <tr>
            <td class="mono cell-strong"><?= e((string) $t['value']) ?></td>
            <td><?= e((string) $t['name']) ?></td>
            <td class="mono"><?= strtoupper((string) $t['type']) ?></td>
            <td class="mono"><?= e(strtoupper((string) $t['environment'])) ?></td>
            <td class="mono"><?= e(substr((string) $t['created_at'], 0, 10)) ?></td>
            <td><span class="scope-badge <?= ((int) $t['in_scope']) === 1 ? 'scope-in' : 'scope-out' ?>"><?= ((int) $t['in_scope']) === 1 ? 'IN SCOPE' : 'OUT' ?></span></td>
            <td>
              <div style="display:flex; gap: var(--sp-2); white-space:nowrap">
                <?php if (((int) $t['in_scope']) === 1): ?>
                  <a class="btn btn-sm" href="/workflows">Set Workflow</a>
                <?php else: ?>
                  <button type="button" class="btn btn-sm" disabled title="Out of scope — tidak bisa discan">Set Workflow</button>
                <?php endif; ?>
                <form method="post" action="/targets/scope" data-confirm="Ubah scope target <?= e((string) $t['value']) ?>?">
                  <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
                  <input type="hidden" name="target_id" value="<?= (int) $t['id'] ?>">
                  <button type="submit" class="btn btn-sm"><?= ((int) $t['in_scope']) === 1 ? 'Set Out' : 'Set In' ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<!-- Modal register target — POST nyata -->
<div class="modal-backdrop" id="modal-add-target">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Register Target (In-Scope)</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form class="modal-body" method="post" action="/targets/create" id="form-add-target">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
      <div class="form-field">
        <label class="form-label" for="at-name">LABEL TAMPILAN</label>
        <input class="input" type="text" id="at-name" name="name" placeholder="Blog pribadi low.my.id" required maxlength="190">
      </div>
      <div class="form-field">
        <label class="form-label" for="at-type">TYPE</label>
        <select class="select mono" id="at-type" name="type" required>
          <option value="domain">domain</option>
          <option value="url">url</option>
          <option value="ip">ip</option>
          <option value="cidr">cidr</option>
        </select>
      </div>
      <div class="form-field">
        <label class="form-label" for="at-value">VALUE</label>
        <input class="input mono" type="text" id="at-value" name="value" placeholder="low.my.id" required>
        <span class="form-hint">domain: low.my.id · url: https://low.my.id/ · ip: 1.2.3.4 · cidr: 10.0.0.0/24</span>
      </div>
      <div class="form-field">
        <label class="form-label" for="at-env">ENVIRONMENT</label>
        <select class="select mono" id="at-env" name="environment">
          <option value="lab">lab</option>
          <option value="staging">staging</option>
          <option value="production">production</option>
        </select>
      </div>
      <div class="auth-alert pending" style="border-radius: var(--r-md)">
        Dengan registrasi ini kamu menyatakan <strong>punya otorisasi</strong> menguji asset tersebut. Scan di luar daftar ini ditolak server-side.
      </div>
    </form>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="submit" form="form-add-target" class="btn btn-primary">Save Target</button>
    </div>
  </div>
</div>

<script>
(function () {
  document.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) {
      if (!window.confirm(f.dataset.confirm)) e.preventDefault();
    });
  });
})();
</script>
