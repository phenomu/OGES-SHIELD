<?php
/**
 * admin/tool-registry.php — Phase 4: editor tools.json fungsional.
 * Toggle enabled/risk + save raw JSON (validasi) → tulis file → trigger reload orchestrator.
 */

View::$pageTitle = 'Tool Registry — OGES-SHIELD';
$navKey = $_GET['nav'] ?? 'toolregistry';
$navKey = is_string($navKey) ? $navKey : 'toolregistry';
if (!in_array($navKey, ['toolregistry', 'modules'], true)) { $navKey = 'toolregistry'; }
View::$activeNav = $navKey;

$toolsPath = BASE_PATH . '/orchestrator/config/tools.json';
$toolsJson = file_get_contents($toolsPath);
$tools = json_decode($toolsJson, true)['tools'] ?? [];
$jsonError = json_last_error() !== JSON_ERROR_NONE;

$flashOk  = Session::flash('tr_ok');
$flashErr = Session::flash('tr_error');

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
    <button type="button" class="btn" data-modal-open="modal-edit-json">Edit JSON</button>
  </div>
</div>

<?php if ($flashOk): ?>
  <div class="auth-alert ok mb-3" style="border-radius: var(--r-md)"><?= e($flashOk) ?></div>
<?php elseif ($flashErr): ?>
  <div class="auth-alert error mb-3" style="border-radius: var(--r-md)"><?= e($flashErr) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Registered Tools</h2>
      <p class="panel-subtitle"><?= count($tools) ?> tool terdaftar — toggle enabled langsung menyimpan + reload orchestrator</p>
    </div>
    <span class="badge <?= $jsonError ? 'badge-danger' : 'badge-success' ?>"><span class="dot"></span><?= $jsonError ? 'JSON INVALID' : 'SCHEMA OK' ?></span>
  </div>

  <?php if ($jsonError): ?>
    <div class="auth-alert error">tools.json tidak valid — orchestrator akan menolak reload.</div>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Phases</th><th>Risk</th><th>Timeout</th><th>Enabled</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tools as $t): ?>
          <tr>
            <td class="mono cell-strong"><?= htmlspecialchars($t['id']) ?></td>
            <td><?= htmlspecialchars($t['name']) ?></td>
            <td class="mono"><?= htmlspecialchars($t['category']) ?></td>
            <td class="mono" style="font-size:10px"><?= htmlspecialchars(implode(', ', $t['valid_phases'])) ?></td>
            <td>
              <form method="post" action="/admin/tool-registry/save" style="display:inline-flex; gap:4px; align-items:center">
                <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
                <input type="hidden" name="tool_id" value="<?= e((string) $t['id']) ?>">
                <input type="hidden" name="action" value="risk">
                <input type="hidden" name="risk_level" value="<?= match ($t['risk_level']) { 'safe' => 'intrusive', 'intrusive' => 'destructive', default => 'safe' } ?>">
                <button type="submit" class="sev <?= $sevFor((string) $t['risk_level']) ?>" style="cursor:pointer; border:none; background:none" title="Rotate risk level"><?= strtoupper((string) $t['risk_level']) ?></button>
              </form>
            </td>
            <td class="mono"><?= $t['timeout_seconds'] ?>s</td>
            <td>
              <form method="post" action="/admin/tool-registry/save" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
                <input type="hidden" name="tool_id" value="<?= e((string) $t['id']) ?>">
                <input type="hidden" name="action" value="toggle">
                <button type="submit" class="sev <?= !empty($t['enabled']) ? 'sev-info' : 'sev-medium' ?>" style="cursor:pointer; border:none; background:none"><?= !empty($t['enabled']) ? 'ON' : 'OFF' ?></button>
              </form>
            </td>
            <td class="mono" style="font-size:10px; max-width:320px"><?= htmlspecialchars($t['command_template']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="form-hint mt-3">Klik badge RISK untuk rotate safe → intrusive → destructive (destructive wajib konfirmasi user saat run). Klik ON/OFF untuk enable/disable tool — AI tidak akan melihat tool disabled.</p>
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
        <span class="sev <?= $sevFor((string) $t['risk_level']) ?>"><?= strtoupper((string) $t['risk_level']) ?></span>
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

<!-- Modal edit JSON — POST nyata (validasi di controller) -->
<div class="modal-backdrop" id="modal-edit-json">
  <div class="modal" style="max-width:720px">
    <div class="modal-head">
      <span class="modal-title">Edit tools.json</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form class="modal-body" method="post" action="/admin/tool-registry/save" id="form-edit-json">
      <input type="hidden" name="csrf" value="<?= e(Session::csrf()) ?>">
      <input type="hidden" name="action" value="raw">
      <div class="form-field">
        <label class="form-label" for="raw-json">ISI tools.json (JSON)</label>
        <textarea class="input mono" id="raw-json" name="raw_json" rows="20" style="font-size:10px; min-height:340px; resize:vertical"><?= htmlspecialchars($toolsJson) ?></textarea>
        <span class="form-hint">Disimpan hanya kalau JSON valid + struktur 'tools' benar. Orchestrator di-reload otomatis — kalau schema ditolak, edit di-revert.</span>
      </div>
    </form>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="submit" form="form-edit-json" class="btn btn-primary">Save &amp; Reload Orchestrator</button>
    </div>
  </div>
</div>
