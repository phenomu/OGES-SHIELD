<?php
/**
 * pending-approval.php — Bagian 4.10. Status menunggu approval superadmin.
 */
View::$pageTitle = 'Menunggu Approval — OGES-SHIELD';
?>
<div class="auth-card" style="max-width: 440px">
  <div class="auth-logo">
    <svg viewBox="0 0 32 32" width="56" height="56" class="al-mark" aria-hidden="true">
      <path d="M16 2 L28 6 V16 C28 23.7 22.9 29.2 16 31 C9.1 29.2 4 23.7 4 16 V6 Z" fill="#0A0E18" stroke="#F59E0B" stroke-width="2"/>
      <path d="M16 9v8" stroke="#F59E0B" stroke-width="2.4" stroke-linecap="round"/>
      <circle cx="16" cy="21" r="1.4" fill="#F59E0B"/>
    </svg>
    <span class="al-name" style="color: var(--sev-medium)">MENUNGGU APPROVAL</span>
    <span class="al-tag">ACCOUNT PENDING REVIEW</span>
  </div>

  <div class="auth-alert pending">
    Request akses kamu sudah tercatat dan <strong>menunggu persetujuan superadmin</strong>.
    Kamu belum bisa login sampai akun di-approve. Notifikasi approval muncul di aplikasi
    (tidak dikirim via email).
  </div>

  <div class="fd-fields mt-4">
    <div class="fd-field">
      <span class="fd-label">STATUS AKUN</span>
      <span class="fd-value"><span class="sev sev-medium">PENDING</span></span>
    </div>
    <div class="fd-field">
      <span class="fd-label">ESTIMASI REVIEW</span>
      <span class="fd-value mono">1×24 jam kerja</span>
    </div>
    <div class="fd-field">
      <span class="fd-label">YANG DILIHAT ADMIN</span>
      <span class="fd-value">Nama, email, username, dan justifikasi akses yang kamu isi saat register.</span>
    </div>
  </div>

  <p class="auth-foot">Sudah di-approve? <a href="/login">Coba login</a> · Ditolak? Login akan menampilkan keterangan reject.</p>
</div>
