<?php
/**
 * 403 — akses ditolak (guard role superadmin).
 */
View::$pageTitle = '403 Forbidden — OGES-SHIELD';
?>
<div class="empty-state" style="padding: var(--sp-8) var(--sp-4)">
  <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--sev-critical)" stroke-width="1.4" class="es-icon"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
  <div class="es-title" style="color: var(--sev-critical)">403 — Access Denied</div>
  <div class="es-desc">Halaman ini hanya untuk superadmin. Peran kamu tidak punya akses.</div>
  <div class="mt-4"><a class="btn btn-primary" href="/dashboard">Kembali ke Dashboard</a></div>
</div>
