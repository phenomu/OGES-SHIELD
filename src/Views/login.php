<?php
/**
 * login.php — Bagian 4.10. Phase 2: autentikasi nyata (POST → AuthController::login).
 */
View::$pageTitle = 'Login — OGES-SHIELD';

$loginError = Session::flash('login_error');
$identifier = Session::flash('login_identifier') ?? '';
$stateRaw = $_GET['state'] ?? '';
$stateRaw = is_string($stateRaw) ? $stateRaw : '';
$state = in_array($stateRaw, ['pending', 'rejected', 'approved'], true) ? $stateRaw : '';
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

  <?php if ($loginError): ?>
    <div class="auth-alert error" style="margin-bottom: var(--sp-4)"><?= e($loginError) ?></div>
  <?php elseif ($state === 'pending'): ?>
    <div class="auth-alert pending" style="margin-bottom: var(--sp-4)">
      Akun kamu masih <strong>menunggu persetujuan superadmin</strong>. Coba login lagi setelah akun di-approve.
    </div>
  <?php elseif ($state === 'rejected'): ?>
    <div class="auth-alert error" style="margin-bottom: var(--sp-4)">
      Akun kamu <strong>ditolak</strong> oleh superadmin. Hubungi admin untuk keterangan lebih lanjut.
    </div>
  <?php endif; ?>

  <form class="auth-form" method="post" action="/login" data-login-form>
    <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
    <div class="form-field">
      <label class="form-label" for="login-identifier">EMAIL / USERNAME</label>
      <input class="input mono" type="text" id="login-identifier" name="identifier" placeholder="operator@team.id" value="<?= e($identifier) ?>" required autofocus>
    </div>
    <div class="form-field">
      <label class="form-label" for="login-password">PASSWORD</label>
      <input class="input mono" type="password" id="login-password" name="password" placeholder="••••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary" style="padding: 10px">Sign In</button>
  </form>

  <p class="auth-foot">Belum punya akun? <a href="/register">Register</a> · Lupa password hubungi superadmin.</p>
</div>
