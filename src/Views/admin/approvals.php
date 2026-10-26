<?php
/**
 * admin/approvals.php — 1D (dummy). Phase 2: fungsional + guard superadmin server-side.
 */
View::$pageTitle = 'Approvals — OGES-SHIELD';
View::$activeNav = 'approvals';

$pending = MockData::pendingUsers();
?>
<div class="page-head">
  <div>
    <h1 class="page-title">User Approvals</h1>
    <p class="page-sub">Permintaan akses baru — approve/reject (khusus superadmin)</p>
  </div>
  <span class="badge badge-success"><span class="dot"></span>ADMIN</span>
</div>

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
            <tr data-user="<?= $u['id'] ?>">
              <td class="cell-strong"><?= htmlspecialchars($u['name']) ?></td>
              <td class="mono"><?= htmlspecialchars($u['email']) ?></td>
              <td class="mono"><?= htmlspecialchars($u['username']) ?></td>
              <td style="max-width:280px"><?= htmlspecialchars($u['reason']) ?></td>
              <td class="mono"><?= $u['registered'] ?></td>
              <td style="white-space:nowrap">
                <button type="button" class="btn btn-sm btn-primary" data-approve="<?= $u['id'] ?>">Approve</button>
                <button type="button" class="btn btn-sm btn-danger" data-reject="<?= $u['id'] ?>">Reject</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="form-hint mt-3">Aksi dummy Phase 1 — approve/reject nyata (DB + audit log) di Phase 2. Reject minta konfirmasi modal.</p>
  <?php endif; ?>
</section>

<!-- Modal reject (konfirmasi + alasan opsional) -->
<div class="modal-backdrop" id="modal-reject">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Reject Akun</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p class="fd-value">Reject user <strong class="mono" id="rej-name">--</strong>? Akun tidak bisa login dan alasan reject (opsional) tersimpan untuk ditampilkan saat login attempt.</p>
      <div class="form-field mt-3">
        <label class="form-label" for="rej-reason">ALASAN REJECT (OPSIONAL)</label>
        <textarea class="textarea" id="rej-reason" placeholder="Contoh: justifikasi akses kurang jelas, hubungi SOC lead."></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-danger" data-modal-close data-reject-confirm>Reject User</button>
    </div>
  </div>
</div>

<script>
(function () {
  var rejectTarget = null;
  document.querySelectorAll("[data-approve]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var row = btn.closest("tr");
      row.querySelector("td:last-child").innerHTML = '<span class="sev sev-info">APPROVED ✓</span>';
    });
  });
  document.querySelectorAll("[data-reject]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      rejectTarget = btn.closest("tr");
      var name = rejectTarget.querySelector("td").textContent;
      document.getElementById("rej-name").textContent = name;
      document.getElementById("modal-reject").classList.add("open");
    });
  });
  document.querySelectorAll("[data-reject-confirm]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      if (rejectTarget) {
        rejectTarget.querySelector("td:last-child").innerHTML = '<span class="sev sev-critical">REJECTED</span>';
      }
    });
  });
})();
</script>
