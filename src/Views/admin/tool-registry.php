<?php
/**
 * admin/tool-registry.php — 1D. Editor tools.json (dummy read-only Phase 1).
 * Phase 4: tulis balik ke file + POST /internal/reload-tools.
 */
View::$pageTitle = 'Tool Registry — OGES-SHIELD';
View::$activeNav = ($_GET['nav'] ?? 'toolregistry');

$toolsJson = file_get_contents(BASE_PATH . '/orchestrator/config/tools.json');
$tools = json_decode($toolsJson, true)['tools'] ?? [];
$jsonError = json_last_error() !== JSON_ERROR_NONE;

$sevFor = fn(string $risk) => match ($risk) {
    'safe'       => 'sev-info',
    'intrusive'  => 'sev-medium',
    'destructive'=> 'sev-critical',
    default      => 'sev-info',
};
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Tool Registry</h1>
    <p class="page-sub">Definisi tool yang bisa dipilih AI — sumber: orchestrator/config/tools.json</p>
  </div>
  <div style="display:flex; gap: var(--sp-2)">
    <button type="button" class="btn" data-modal-open="modal-raw-json">Raw JSON</button>
    <button type="button" class="btn btn-primary" disabled title="Editing aktif di Phase 4">Save Changes</button>
  </div>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Registered Tools</h2>
      <p class="panel-subtitle"><?= count($tools) ?> tool terdaftar</p>
    </div>
    <span class="badge <?= $jsonError ? 'badge-danger' : 'badge-success' ?>"><span class="dot"></span><?= $jsonError ? 'JSON INVALID' : 'SCHEMA OK' ?></span>
  </div>

  <?php if ($jsonError): ?>
    <div class="auth-alert error">tools.json tidak valid — orchestrator akan menolak reload.</div>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Phases</th><th>Risk</th><th>Timeout</th><th>Enabled</th><th>Command Template</th></tr></thead>
      <tbody>
        <?php foreach ($tools as $t): ?>
          <tr>
            <td class="mono cell-strong"><?= htmlspecialchars($t['id']) ?></td>
            <td><?= htmlspecialchars($t['name']) ?></td>
            <td class="mono"><?= htmlspecialchars($t['category']) ?></td>
            <td class="mono" style="font-size:10px"><?= htmlspecialchars(implode(', ', $t['valid_phases'])) ?></td>
            <td><span class="sev <?= $sevFor($t['risk_level']) ?>"><?= strtoupper($t['risk_level']) ?></span></td>
            <td class="mono"><?= $t['timeout_seconds'] ?>s</td>
            <td>
              <?php if (!empty($t['enabled'])): ?>
                <span class="sev sev-info">ON</span>
              <?php else: ?>
                <span class="sev sev-medium">OFF</span>
              <?php endif; ?>
            </td>
            <td class="mono" style="font-size:10px; max-width:320px"><?= htmlspecialchars($t['command_template']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="form-hint mt-3">Read-only Phase 1. Phase 4: form editor + validasi schema + simpan + trigger <span class="mono">POST /internal/reload-tools</span> ke orchestrator.</p>
</section>

<section class="panel mt-4">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Input Schema per Tool</h2>
      <p class="panel-subtitle">Parameter yang boleh diisi AI (typed, divalidasi orchestrator — AI tidak menulis command bebas)</p>
    </div>
  </div>
  <?php foreach ($tools as $t): ?>
    <div class="mt-3" style="border:1px solid var(--border-subtle); border-radius: var(--r-md); padding: var(--sp-3)">
      <div style="display:flex; align-items:center; gap:var(--sp-2); margin-bottom: var(--sp-2)">
        <span class="mono cell-strong"><?= htmlspecialchars($t['id']) ?></span>
        <span class="sev <?= $sevFor($t['risk_level']) ?>"><?= strtoupper($t['risk_level']) ?></span>
        <?php if (($t['risk_level'] ?? '') === 'destructive'): ?>
          <span class="sev sev-critical">REQUIRES CONFIRMATION</span>
        <?php endif; ?>
      </div>
      <div class="fd-fields">
        <div class="fd-field">
          <span class="fd-label">DESCRIPTION</span>
          <span class="fd-value"><?= htmlspecialchars($t['description'] ?? '') ?></span>
        </div>
        <div class="fd-field">
          <span class="fd-label">INPUT_SCHEMA</span>
          <pre class="fd-value mono" style="margin:0; white-space:pre-wrap; font-size:10px"><?= htmlspecialchars(json_encode($t['input_schema'], JSON_PRETTY_PRINT)) ?></pre>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<!-- Modal raw JSON viewer -->
<div class="modal-backdrop" id="modal-raw-json">
  <div class="modal" style="max-width:720px">
    <div class="modal-head">
      <span class="modal-title">tools.json (raw)</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <pre class="fd-value mono" style="margin:0; white-space:pre-wrap; font-size:10px; max-height:60vh; overflow-y:auto"><?= htmlspecialchars($toolsJson) ?></pre>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Close</button>
    </div>
  </div>
</div>
