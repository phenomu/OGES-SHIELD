<?php
/**
 * login.php — Bagian 4.10. Phase 1: dummy (submit → dashboard).
 */
View::$pageTitle = 'Login — OGES-SHIELD';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Phase 2: validasi password_hash + role check di sini.
    $error = 'demo';
}
?>
<div class="auth-card">
  <div class="auth-logo">
    <svg viewBox="0 0 32 32" width="56" height="56" class="al-mark" aria-hidden="true">
      <path d="M16 2 L28 6 V16 C28 23.7 22.9 29.2 16 31 C9.1 29.2 4 23.7 4 16 V6 Z" fill="#0A0E18" stroke="#22D3EE" stroke-width="2"/>
      <path d="M12.5 16.2 L15 18.8 L20 13.2" stroke="#22D3EE" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span class="al-name">OGES-SHIELD</span>
    <span class="al-tag">AI-AUGMENTED PENETRATION TESTING</span>
  </div>

  <?php if ($error === 'demo'): ?>
    <div class="auth-alert error" style="margin-bottom: var(--sp-4)">
      Autentikasi belum aktif di Phase 1 (demo). Klik "Sign In" untuk masuk ke dashboard dummy.
    </div>
  <?php elseif (($_GET['state'] ?? '') === 'pending'): ?>
    <div class="auth-alert pending" style="margin-bottom: var(--sp-4)">
      Akun kamu masih <strong>menunggu persetujuan superadmin</strong>. Coba login lagi setelah akun di-approve.
    </div>
  <?php elseif (($_GET['state'] ?? '') === 'rejected'): ?>
    <div class="auth-alert error" style="margin-bottom: var(--sp-4)">
      Akun kamu <strong>ditolak</strong> oleh superadmin. Hubungi admin untuk keterangan lebih lanjut.
    </div>
  <?php endif; ?>

  <form class="auth-form" method="post" action="/login" data-login-form>
    <div class="form-field">
      <label class="form-label" for="login-identifier">EMAIL / USERNAME</label>
      <input class="input mono" type="text" id="login-identifier" name="identifier" placeholder="operator@team.id" required>
    </div>
    <div class="form-field">
      <label class="form-label" for="login-password">PASSWORD</label>
      <input class="input mono" type="password" id="login-password" name="password" placeholder="••••••••••" required minlength="8">
    </div>
    <button type="submit" class="btn btn-primary" style="padding: 10px">Sign In</button>
  </form>

  <p class="auth-foot">Belum punya akun? <a href="/register">Register</a> · Lupa password hubungi superadmin.</p>
</div>

<script>
(function () {
  var form = document.querySelector("[data-login-form]");
  if (!form) return;
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    // Phase 1 dummy: validasi client-side minimal lalu redirect
    var id = form.querySelector("#login-identifier").value.trim();
    var pw = form.querySelector("#login-password").value;
    var err = null;
    if (!id) err = "Email/username wajib diisi.";
    else if (pw.length < 8) err = "Password minimal 8 karakter.";
    if (err) { alert(err); return; }
    window.location.href = "/dashboard";
  });
})();
</script>
