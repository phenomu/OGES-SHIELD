<?php
/**
 * recon.php — Panel reconnaissance (tabs) — reuse inventory + timeline mock.
 */
View::$pageTitle = 'Reconnaissance — OGES-SHIELD';
View::$activeNav = 'recon';

// data asli (Phase 6 — ganti MockData): recon dari run aktif (engagement) milik user
$user = Auth::user();
$isSuper = ($user['role'] ?? '') === 'superadmin';
$activeRun = ActiveRun::current($user);
$runId = $activeRun['id'] ?? null;

// helper: records tool dari event tool_completed (payload JSON)
// fallback: artefak file (httpx_results.json) lalu parse stdout_tail (run lama)
$toolRecords = [];
if ($runId) {
    foreach (Database::run(
        "SELECT payload FROM run_events WHERE run_id = ? AND type = 'tool_completed' ORDER BY id",
        [$runId]
    )->fetchAll(PDO::FETCH_ASSOC) as $ev) {
        $pl = json_decode((string) $ev['payload'], true) ?: [];
        $tool = (string) ($pl['tool'] ?? '');
        if ($tool === '') {
            continue;
        }
        $recs = is_array($pl['records'] ?? null) ? $pl['records'] : null;
        if ($recs === null && isset($pl['stdout_tail']) && is_string($pl['stdout_tail'])) {
            // stdout_tail = 2000 char terakhir jsonlines — parse baris yang utuh saja
            $recs = [];
            foreach (preg_split('/\r?\n/', $pl['stdout_tail']) ?: [] as $ln) {
                $ln = trim((string) $ln);
                if ($ln === '' || $ln[0] !== '{') {
                    continue;
                }
                $dec = json_decode($ln, true);
                if (is_array($dec)) {
                    $recs[] = $dec;
                }
            }
        }
        $toolRecords[$tool] = is_array($recs) ? $recs : [];
    }
    // artefak httpx_results.json (data penuh, tidak terpotong) menimpa fallback
    $artFile = BASE_PATH . '/storage/evidence/' . $runId . '/httpx_results.json';
    if (is_file($artFile)) {
        $dec = json_decode((string) file_get_contents($artFile), true);
        if (is_array($dec)) {
            $toolRecords['httpx'] = $dec;
        }
    }
}

// subdomains: artefak subfinder_hosts.txt (kalau ada) + records event
$subdomains = [];
$hostsFile = $runId ? BASE_PATH . '/storage/evidence/' . $runId . '/subfinder_hosts.txt' : null;
if ($hostsFile && is_file($hostsFile)) {
    foreach (file($hostsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $h) {
        $subdomains[] = ['host' => trim((string) $h), 'source' => 'subfinder'];
    }
}
foreach ($toolRecords['subfinder'] ?? [] as $r) {
    $h = trim((string) ($r['host'] ?? $r['input'] ?? ''));
    if ($h !== '' && !in_array($h, array_column($subdomains, 'host'), true)) {
        $subdomains[] = ['host' => $h, 'source' => (string) ($r['source'] ?? 'subfinder')];
    }
}
$subdomains = array_slice($subdomains, 0, 200);

// live hosts + tech + server: httpx records (field asli httpx projectdiscovery)
$liveHosts = [];
foreach ($toolRecords['httpx'] ?? [] as $r) {
    $url = (string) ($r['url'] ?? '');
    if ($url === '' && !empty($r['host'])) {
        $url = (string) ($r['scheme'] ?? 'http') . '://' . $r['host'];
    }
    if ($url === '') continue;
    $techArr = $r['tech'] ?? [];
    $liveHosts[] = [
        'url' => $url,
        'status' => (int) ($r['status_code'] ?? 0),
        'title' => (string) ($r['title'] ?? ''),
        'tech' => is_array($techArr) ? implode(', ', $techArr) : (string) $techArr,
        'server' => (string) ($r['webserver'] ?? ''),
        'ip' => (string) ($r['host_ip'] ?? $r['a'][0] ?? $r['ip'] ?? ''),
        'waf' => (string) ($r['cdn_name'] ?? ($r['cdn']['name'] ?? '')),
    ];
}

// services unik: httpx live = HTTP service per host
$services = [];
foreach ($liveHosts as $lh) {
    $label = ($lh['status'] > 0 ? 'HTTP/' : '') . ($lh['status'] > 0 ? 'TCP' : 'TCP');
    $services[] = [
        'host' => parse_url($lh['url'], PHP_URL_HOST) ?: $lh['ip'],
        'port' => parse_url($lh['url'], PHP_URL_PORT) ?: (str_starts_with($lh['url'], 'https') ? 443 : 80),
        'proto' => str_starts_with($lh['url'], 'https') ? 'https' : 'http',
        'status' => $lh['status'] > 0 ? 'online' : 'offline',
        'service' => ($lh['server'] !== '' ? $lh['server'] . ' ' : '') . ($lh['status'] > 0 ? '(' . $lh['status'] . ')' : ''),
    ];
}

// IP unik
$ips = [];
foreach ($liveHosts as $lh) {
    if ($lh['ip'] !== '' && !in_array($lh['ip'], $ips, true)) $ips[] = $lh['ip'];
}

// inventory: gabungan target terdaftar + live host
$inventory = [];
if ($activeRun) {
    $inventory[] = [
        'asset' => (string) $activeRun['target_value'], 'type' => (string) $activeRun['target_type'],
        'service' => $liveHosts ? (string) ($liveHosts[0]['server'] ?: 'http') : '—',
        'status' => $liveHosts ? 'online' : 'offline', 'discovered' => substr((string) $activeRun['created_at'], 0, 10),
        'scope' => 'in',
    ];
}
foreach ($liveHosts as $lh) {
    $host = parse_url($lh['url'], PHP_URL_HOST) ?: $lh['ip'];
    if ($host === (string) ($activeRun['target_value'] ?? null)) continue;
    $inventory[] = [
        'asset' => $host, 'type' => 'subdomain',
        'service' => $lh['server'] !== '' ? $lh['server'] : ($lh['tech'] !== '' ? $lh['tech'] : 'http'),
        'status' => 'online', 'discovered' => substr((string) ($activeRun['created_at'] ?? ''), 0, 10),
        'scope' => 'in',
    ];
}

// timeline discovery per event tool (passive: subdomain kumulatif, active: live host)
// dibangun dari urutan event tool_completed run aktif
$timelineEvents = [];
$timeline = ['labels' => [], 'passive' => [], 'active' => []];
if ($runId) {
    foreach (Database::run(
        "SELECT payload, created_at FROM run_events
         WHERE run_id = ? AND type = 'tool_completed' AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.tool')) IN ('subfinder','httpx')
         ORDER BY id",
        [$runId]
    )->fetchAll(PDO::FETCH_ASSOC) as $ev) {
        $pl = json_decode((string) $ev['payload'], true) ?: [];
        $timelineEvents[] = [
            'tool' => (string) ($pl['tool'] ?? ''),
            'time' => substr((string) $ev['created_at'], 11, 5),
        ];
    }
}
$cumSub = 0;
$cumLive = 0;
foreach ($timelineEvents as $te) {
    if ($te['tool'] === 'subfinder') {
        $cumSub += 1; // satu event subfinder = satu batch discovery
    } else {
        $cumLive += 1;
    }
    $timeline['labels'][] = $te['time'] . ' ' . $te['tool'];
    $timeline['passive'][] = $cumSub;
    $timeline['active'][] = $cumLive;
}
// minimal 2 titik biar garis terlihat
if (count($timeline['labels']) < 2) {
    $timeline = [
        'labels'  => ['start', 'now'],
        'passive' => [0, count($subdomains)],
        'active'  => [0, count($liveHosts)],
    ];
}
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Reconnaissance</h1>
    <p class="page-sub">Hasil discovery: DNS, subdomain, IP, service — dari run aktif <?= $runId ? e('RUN-' . substr((string) $runId, 0, 8)) : '—' ?><?= $activeRun ? ' · ' . e((string) $activeRun['target_value']) : '' ?></p>
  </div>
</div>

<section class="panel">
  <div data-tabs>
    <div class="tabs">
      <button type="button" class="tab-btn active" data-tab="inventory">Target Inventory</button>
      <button type="button" class="tab-btn" data-tab="dns">DNS Records</button>
      <button type="button" class="tab-btn" data-tab="subdomains">Subdomains</button>
      <button type="button" class="tab-btn" data-tab="ips">IP Addresses</button>
      <button type="button" class="tab-btn" data-tab="services">Services</button>
    </div>
    <div class="tab-pane active" data-pane="inventory">
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Asset</th><th>Type</th><th>Service</th><th>Status</th><th>Discovered</th><th>Scope</th></tr></thead>
          <tbody>
            <?php foreach ($inventory as $row): ?>
              <tr>
                <td class="mono cell-strong"><?= htmlspecialchars($row['asset']) ?></td>
                <td class="mono"><?= strtoupper($row['type']) ?></td>
                <td class="mono"><?= htmlspecialchars($row['service']) ?></td>
                <td><span class="st-<?= $row['status'] ?> mono">● <?= strtoupper($row['status']) ?></span></td>
                <td class="mono"><?= $row['discovered'] ?></td>
                <td><span class="scope-badge <?= $row['scope'] === 'in' ? 'scope-in' : 'scope-out' ?>"><?= $row['scope'] === 'in' ? 'IN SCOPE' : 'OUT' ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="tab-pane" data-pane="subdomains">
      <div class="table-wrap" style="max-height:360px; overflow-y:auto">
        <table class="tbl">
          <thead><tr><th>#</th><th>Subdomain</th><th>Source</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$subdomains): ?>
              <tr><td colspan="4" class="text-muted">Belum ada data — jalankan workflow dengan tool subfinder.</td></tr>
            <?php else: foreach ($subdomains as $i => $sd): ?>
              <tr>
                <td class="mono text-muted"><?= $i + 1 ?></td>
                <td class="mono cell-strong"><?= e($sd['host']) ?></td>
                <td class="mono"><?= e($sd['source']) ?></td>
                <td><span class="st-online mono">● DISCOVERED</span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane" data-pane="dns">
      <div class="table-wrap" style="max-height:360px; overflow-y:auto">
        <table class="tbl">
          <thead><tr><th>Host</th><th>Resolved IP</th><th>Type</th></tr></thead>
          <tbody>
            <?php $dnsRows = []; foreach ($liveHosts as $lh): $h = parse_url($lh['url'], PHP_URL_HOST) ?: $lh['ip']; if ($h && $lh['ip']): $dnsRows[] = ['host' => $h, 'ip' => $lh['ip']]; endif; endforeach; ?>
            <?php if (!$dnsRows): ?>
              <tr><td colspan="3" class="text-muted">Belum ada data DNS — jalankan httpx untuk resolusi.</td></tr>
            <?php else: foreach ($dnsRows as $d): ?>
              <tr><td class="mono cell-strong"><?= e($d['host']) ?></td><td class="mono"><?= e($d['ip']) ?></td><td class="mono">A</td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane" data-pane="ips">
      <div class="table-wrap" style="max-height:360px; overflow-y:auto">
        <table class="tbl">
          <thead><tr><th>#</th><th>IP Address</th><th>Hosts</th></tr></thead>
          <tbody>
            <?php if (!$ips): ?>
              <tr><td colspan="3" class="text-muted">Belum ada data IP — jalankan httpx untuk resolusi.</td></tr>
            <?php else: foreach ($ips as $i => $ip): ?>
              <tr>
                <td class="mono text-muted"><?= $i + 1 ?></td>
                <td class="mono cell-strong"><?= e($ip) ?></td>
                <td class="mono"><?= count(array_filter($liveHosts, fn($lh) => $lh['ip'] === $ip)) ?> host</td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane" data-pane="services">
      <div class="table-wrap" style="max-height:360px; overflow-y:auto">
        <table class="tbl">
          <thead><tr><th>Host</th><th>Port</th><th>Proto</th><th>Service</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$services): ?>
              <tr><td colspan="5" class="text-muted">Belum ada data service — jalankan httpx.</td></tr>
            <?php else: foreach ($services as $sv): ?>
              <tr>
                <td class="mono cell-strong"><?= e($sv['host']) ?></td>
                <td class="mono"><?= e((string) $sv['port']) ?></td>
                <td class="mono"><?= e($sv['proto']) ?></td>
                <td class="mono"><?= e($sv['service']) ?></td>
                <td><span class="st-<?= e($sv['status']) ?> mono">● <?= strtoupper(e($sv['status'])) ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="chart-box mt-3" style="height:140px">
    <canvas data-timeline="<?= htmlspecialchars(json_encode($timeline)) ?>"></canvas>
  </div>
  <div class="ops-meta">
    <div class="ops-meta-item"><span class="ops-meta-label">TOTAL ASSETS</span><span class="ops-meta-value"><?= count($inventory) ?></span></div>
    <div class="ops-meta-item"><span class="ops-meta-label">IN SCOPE</span><span class="ops-meta-value"><?= count(array_filter($inventory, fn($r) => $r['scope'] === 'in')) ?></span></div>
    <div class="ops-meta-item"><span class="ops-meta-label">ONLINE</span><span class="ops-meta-value"><?= count(array_filter($inventory, fn($r) => $r['status'] === 'online')) ?></span></div>
  </div>
</section>
