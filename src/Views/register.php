<?php
/**
 * register.php — Bagian 4.10. Phase 1: dummy (submit → pending-approval).
 */
View::$pageTitle = 'Register — OGES-SHIELD';
?>
<div class="auth-card" style="max-width: 460px">
  <div class="auth-logo">
    <svg viewBox="0 0 32 32" width="48" height="48" class="al-mark" aria-hidden="true">
      <path d="M16 2 L28 6 V16 C28 23.7 22.9 29.2 16 31 C9.1 29.2 4 23.7 4 16 V6 Z" fill="#0A0E18" stroke="#22D3EE" stroke-width="2"/>
      <path d="M12.5 16.2 L15 18.8 L20 13.2" stroke="#22D3EE" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span class="al-name">OGES-SHIELD</span>
    <span class="al-tag">REQUEST PLATFORM ACCESS</span>
  </div>

  <form class="auth-form" method="post" action="/register" data-register-form>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap: var(--sp-3)">
      <div class="form-field">
        <label class="form-label" for="reg-name">NAMA LENGKAP</label>
        <input class="input" type="text" id="reg-name" name="name" placeholder="Nama kamu" required>
      </div>
      <div class="form-field">
        <label class="form-label" for="reg-username">USERNAME</label>
        <input class="input mono" type="text" id="reg-username" name="username" placeholder="operator01" required pattern="[a-zA-Z0-9_.]{3,60}">
      </div>
    </div>
    <div class="form-field">
      <label class="form-label" for="reg-email">EMAIL</label>
      <input class="input mono" type="email" id="reg-email" name="email" placeholder="kamu@team.id" required>
    </div>
    <div class="form-field">
      <label class="form-label" for="reg-password">PASSWORD</label>
      <input class="input mono" type="password" id="reg-password" name="password" placeholder="min. 8 karakter" required minlength="8">
      <span class="form-hint">Disimpan sebagai hash bcrypt — tidak pernah tampil di log.</span>
    </div>
    <div class="form-field">
      <label class="form-label" for="reg-reason">ALASAN / JUSTIFIKASI AKSES</label>
      <textarea class="textarea" id="reg-reason" name="reason" placeholder="Contoh: pentester tim red untuk engagement Kartika, butuh akses scan target in-scope." required minlength="20"></textarea>
      <span class="form-hint">Dilihat superadmin saat menilai approval (min. 20 karakter).</span>
    </div>
    <button type="submit" class="btn btn-primary" style="padding: 10px">Request Access</button>
  </form>

  <p class="auth-foot">Sudah punya akun? <a href="/login">Login</a></p>
</div>

<script>
(function () {
  var form = document.querySelector("[data-register-form]");
  if (!form) return;
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var errs = [];
    var name = form.querySelector("#reg-name").value.trim();
    var user = form.querySelector("#reg-username").value.trim();
    var email = form.querySelector("#reg-email").value.trim();
    var pw = form.querySelector("#reg-password").value;
    var reason = form.querySelector("#reg-reason").value.trim();
    if (name.length < 3) errs.push("Nama minimal 3 karakter.");
    if (!/^[a-zA-Z0-9_.]{3,60}$/.test(user)) errs.push("Username 3-60 karakter (huruf/angka/_/.).");
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) errs.push("Email tidak valid.");
    if (pw.length < 8) errs.push("Password minimal 8 karakter.");
    if (reason.length < 20) errs.push("Justifikasi akses minimal 20 karakter.");
    if (errs.length) { alert(errs.join("\n")); return; }
    window.location.href = "/pending-approval";
  });
})();
</script>
