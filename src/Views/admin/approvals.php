<?php
/**
 * admin/approvals.php — Phase 2 fungsional: guard superadmin (server-side),
 * approve/reject via POST + CSRF, data dari tabel users.
 */
View::$pageTitle = 'Approvals — OGES-SHIELD';
View::$activeNav = 'approvals';

$pending = $pending ?? User::pendingList();
$flashOk   = Session::flash('approvals_ok');
$flashErr  = Session::flash('approvals_error');
$rejecting = null; // user utk modal reject (flash sekali baca)
$rejectUserId = (int) (Session::flash('reject_user_id') ?? 0);
if ($rejectUserId > 0) {
    $rejecting = User::findById($rejectUserId);
}
?>
<div class="page-head">
  <div>
    <h1 class="page-title">User Approvals</h1>
    <p class="page-sub">Permintaan akses baru — approve/reject (khusus superadmin)</p>
  </div>
  <span class="badge badge-success"><span class="dot"></span>ADMIN</span>
</div>

<?php if ($flashOk): ?>
  <div class="auth-alert ok mb-3" style="border-radius: var(--r-md)"><?= e($flashOk) ?></div>
<?php elseif ($flashErr): ?>
  <div class="auth-alert error mb-3" style="border-radius: var(--r-md)"><?= e($flashErr) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Pending Requests</h2>
      <p class="panel-subtitle"><?= count($pending) ?> akun menunggu keputusan</p>
    </div>
  </div>
  <?php if (count($pending) === 0): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.2" class="es-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M16 11l2 2 4-4"/></svg>
      <div class="es-title">Tidak ada request pending</div>
      <div class="es-desc">Semua request akses sudah diproses.</div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Name</th><th>Email</th><th>Username</th><th>Justifikasi</th><th>Registered</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pending as $u): ?>
            <tr data-user="<?= (int) $u['id'] ?>">
              <td class="cell-strong"><?= e($u['name']) ?></td>
              <td class="mono"><?= e($u['email']) ?></td>
              <td class="mono"><?= e($u['username']) ?></td>
              <td style="max-width:280px"><?= e($u['registration_reason'] ?? ($u['reason'] ?? '--')) ?></td>
              <td class="mono"><?= e((string) $u['created_at']) ?></td>
              <td style="white-space:nowrap">
                <!-- Approve: POST + CSRF, aksi langsung -->
                <form method="post" action="/admin/approve" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                </form>
                <!-- Reject: buka modal konfirmasi dulu -->
                <button type="button" class="btn btn-sm btn-danger" data-open-reject="<?= (int) $u['id'] ?>">Reject</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Modal reject (konfirmasi + alasan opsional) — submit form POST -->
<div class="modal-backdrop" id="modal-reject">
  <div class="modal">
    <form method="post" action="/admin/reject">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
      <input type="hidden" name="user_id" value="<?= $rejecting ? (int) $rejecting['id'] : 0 ?>" id="rej-user-id">
      <div class="modal-head">
        <span class="modal-title">Reject Akun</span>
        <button type="button" class="modal-close" data-modal-close>&times;</button>
      </div>
      <div class="modal-body">
        <p class="fd-value">Reject user <strong class="mono" id="rej-name"><?= $rejecting ? e($rejecting['name']) : '--' ?></strong>?
          Akun tidak bisa login; alasan reject (opsional) ditampilkan saat user mencoba login.</p>
        <div class="form-field mt-3">
          <label class="form-label" for="rej-reason">ALASAN REJECT (OPSIONAL)</label>
          <textarea class="textarea" id="rej-reason" name="reason" placeholder="Contoh: justifikasi akses kurang jelas, hubungi SOC lead."></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Reject User</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  document.querySelectorAll("[data-open-reject]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var row = btn.closest("tr");
      var id  = row.dataset.user;
      document.getElementById("rej-user-id").value = id;
      document.getElementById("rej-name").textContent = row.querySelector("td").textContent;
      document.getElementById("modal-reject").classList.add("open");
    });
  });
})();
</script>
